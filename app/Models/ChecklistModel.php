<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Mesin checklist berulang: pemeriksaan toko harian dan pemeriksaan email.
 *
 * Butir checklist di-SNAPSHOT ke baris hasil saat sesinya dibuka. Alasannya audit:
 * kalau Admin mengubah templat besok, hasil pemeriksaan kemarin harus tetap
 * menampilkan pertanyaan yang dulu dijawab CS, bukan teks baru.
 */
class ChecklistModel extends Model
{
    public const JENIS = [
        'toko'  => 'Cek toko',
        'email' => 'Lembar email (arsip)',
    ];

    public const STATUS = [
        'belum'          => 'Belum selesai',
        'selesai'        => 'Selesai',
        'tidak_relevan'  => 'Tidak relevan',
    ];

    /** Nama tabel di dalam string SQL harus ditulis lengkap dengan prefix koneksi. */
    private function p(string $tabel): string
    {
        return $this->db->prefixTable($tabel);
    }

    /** Butir aktif untuk sebuah sesi: templat umum + tambahan khusus tokonya. */
    public function butir(string $jenis, ?int $toko_id): array
    {
        $b = $this->db->table('crm_checklist_item')
            ->where('jenis', $jenis)->where('aktif', 1);

        if ($jenis === 'toko' && $toko_id !== null) {
            $b->groupStart()->where('toko_id', null)->orWhere('toko_id', (int) $toko_id)->groupEnd();
        } elseif ($jenis === 'toko') {
            $b->where('toko_id', null);
        } else {
            $b->where('toko_id', null);
        }

        return $b->orderBy('urut', 'ASC')->orderBy('id_item', 'ASC')->get()->getResultArray();
    }

    /**
     * Buka (atau buka kembali) sesi satu sasaran pada satu tanggal.
     *
     * Idempoten: dipanggil dua kali untuk toko + tanggal yang sama mengembalikan
     * sesi yang lama, lengkap dengan jawaban yang sudah ditulis.
     *
     * `$toko_id` adalah id toko cek (crm_toko_online), sementara tugas yang lahir
     * di antrean tetap memakai `$juragan_id` — antrean tugas seluruh aplikasi
     * disaring lewat juragan, dan toko tanpa juragan memang tidak punya sana.
     *
     * @return array{run: array<string, mixed>, dibuat: bool}
     */
    public function mulai(string $jenis, ?int $toko_id, ?string $akun, string $tanggal, ?int $oleh, ?int $juragan_id = null): array
    {
        $kunci = $this->kunci($jenis, $toko_id, $akun, $tanggal);
        $lama  = $this->db->table('crm_checklist_run')->where('kunci', $kunci)->get(1)->getRowArray();

        if ($lama !== null) {
            return ['run' => $lama, 'dibuat' => false];
        }

        $waktu = time();

        $this->db->table('crm_checklist_run')->insert([
            'kunci'            => $kunci,
            'jenis'            => $jenis,
            'toko_id'          => $toko_id,
            'akun_email'       => $akun,
            'tanggal'          => $tanggal,
            'status'           => 'berjalan',
            'dikerjakan_oleh'  => $oleh,
            'created_at'       => $waktu,
            'updated_at'       => $waktu,
        ]);

        $id_run = (int) $this->db->insertID();

        foreach ($this->butir($jenis, $toko_id) as $i) {
            $this->db->table('crm_checklist_hasil')->insert([
                'run_id'      => $id_run,
                'item_id'     => (int) $i['id_item'],
                'dibuat_oleh' => $oleh,
                'judul'       => $i['judul'],
                'status'      => 'belum',
                'created_at'  => $waktu,
                'updated_at'  => $waktu,
            ]);
        }

        $tugas = (new TugasModel())->buatUnik([
            'judul'      => $this->judulTugas($jenis, $toko_id, $akun, $tanggal),
            'deskripsi'  => 'Sesi checklist dibuat otomatis dari templat. Centang semua butir lalu akhiri sesi.',
            'jenis'      => $jenis === 'email' ? 'pemeriksaan_email' : 'checklist_toko',
            'prioritas'  => 'normal',
            'sumber'     => 'sistem',
            'kunci_unik' => 'checklist-' . $id_run,
            'juragan_id' => $juragan_id,
            'tenggat'    => $tanggal,
        ], $oleh);

        $this->db->table('crm_checklist_run')->where('id_run', $id_run)->update(['tugas_id' => $tugas['id']]);

        return ['run' => $this->run($id_run), 'dibuat' => true];
    }

    public function run(int $id_run): ?array
    {
        $baris = $this->pangkal()->where('r.id_run', $id_run)->get(1)->getRowArray();

        return $baris ?: null;
    }

    /** @return array<string, mixed>|null sesi yang sudah ada untuk sasaran+tanggal ini */
    public function runKunci(string $jenis, ?int $toko_id, ?string $akun, string $tanggal): ?array
    {
        $baris = $this->pangkal()
            ->where('r.kunci', $this->kunci($jenis, $toko_id, $akun, $tanggal))
            ->get(1)->getRowArray();

        return $baris ?: null;
    }

    /** @param array<string, mixed> $s */
    public function daftar(array $s, int $limit = 25, int $offset = 0): array
    {
        return $this->bangun($s)
            ->orderBy('r.tanggal', 'DESC')
            ->orderBy('r.id_run', 'DESC')
            ->get($limit, $offset)
            ->getResultArray();
    }

    /** @param array<string, mixed> $s */
    public function jumlah(array $s): int
    {
        return (int) $this->bangun($s)->countAllResults();
    }

    /** @return list<array<string, mixed>> */
    public function hasil(int $id_run): array
    {
        return $this->db->table('crm_checklist_hasil h')
            ->select('h.*, u.name AS nama_diisi')
            ->join('user u', 'u.id = h.diisi_oleh', 'left')
            ->where('h.run_id', $id_run)
            ->orderBy('h.id_hasil', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Jawab satu butir, dikenali dari baris hasilnya. Butir templat dan butir
     * tulisan CS di satu lembar memakai jalur yang sama.
     *
     * 'tidak relevan' wajib beralasan, dan satu butir yang butuh tindakan membuat
     * sesinya ikut ditandai — itu signal bagi CS berikutnya.
     */
    public function isi(int $id_run, int $id_hasil, string $status, string $alasan = '', string $catatan = '', bool $tindakan = false, ?int $oleh = null): bool
    {
        if (! array_key_exists($status, self::STATUS)) {
            return false;
        }

        if ($status === 'tidak_relevan' && trim($alasan) === '') {
            return false;
        }

        $ada = $this->db->table('crm_checklist_hasil')
            ->where('id_hasil', $id_hasil)->where('run_id', $id_run)->get(1)->getRowArray();

        if ($ada === null) {
            return false;
        }

        $this->db->table('crm_checklist_hasil')->where('id_hasil', $id_hasil)->update([
            'status'          => $status,
            'alasan'          => trim($alasan) === '' ? null : mb_substr($alasan, 0, 255),
            'catatan'         => trim($catatan) === '' ? null : mb_substr($catatan, 0, 500),
            'perlu_tindakan'  => $tindakan ? 1 : 0,
            'diisi_oleh'      => $oleh,
            'updated_at'      => time(),
        ]);

        $sisa = (int) $this->db->table('crm_checklist_hasil')
            ->where('run_id', $id_run)->where('perlu_tindakan', 1)->countAllResults();

        $this->db->table('crm_checklist_run')->where('id_run', $id_run)->update([
            'ada_tindakan' => $sisa > 0 ? 1 : 0,
            'updated_at'   => time(),
        ]);

        if ($oleh !== null) {
            $this->klaimTugas($id_run, $oleh);
        }

        return true;
    }

    /**
     * Butir yang ditulis orang sendiri di satu lembar — catatan apa yang sudah
     * dikerjakan di toko itu. Baris seperti ini tidak punya templat, jadi item_id
     * dibiarkan kosong; kunci unik (run_id, item_id) tidak masalah karena MySQL
     * tidak pernah menganggap dua NULL sama.
     *
     * @return array{ok: bool, pesan: string}
     */
    public function tambahButir(int $id_run, string $judul, ?int $oleh): array
    {
        $judul = trim($judul);

        if ($judul === '') {
            return ['ok' => false, 'pesan' => 'Tulis dulu butirnya.'];
        }

        $run = $this->run($id_run);

        if ($run === null) {
            return ['ok' => false, 'pesan' => 'Sesi tidak ditemukan.'];
        }

        if ((string) $run['status'] === 'selesai') {
            return ['ok' => false, 'pesan' => 'Sesi ini sudah ditutup, butir baru tidak bisa masuk lagi.'];
        }

        $waktu = time();

        $this->db->table('crm_checklist_hasil')->insert([
            'run_id'     => $id_run,
            'item_id'    => null,
            'judul'      => mb_substr($judul, 0, 200),
            'status'     => 'belum',
            'diisi_oleh' => $oleh,
            'created_at' => $waktu,
            'updated_at' => $waktu,
        ]);

        return ['ok' => true, 'pesan' => 'Butir ditambahkan.'];
    }

    /**
     * Hapus butir tulisan sendiri. Butir templat tidak pernah bisa dihapus dari
     * lembar: kalau memang bukan bagiannya, tandai tidak relevan dengan alasan —
     * jejaknya tetap terbaca orang berikutnya.
     *
     * @return array{ok: bool, pesan: string}
     */
    public function hapusButir(int $id_run, int $id_hasil): array
    {
        $baris = $this->db->table('crm_checklist_hasil')
            ->where('id_hasil', $id_hasil)->where('run_id', $id_run)->get(1)->getRowArray();

        if ($baris === null) {
            return ['ok' => false, 'pesan' => 'Butir itu tidak ada di sesi ini.'];
        }

        if ($baris['item_id'] !== null) {
            return ['ok' => false, 'pesan' => 'Butir templat tidak bisa dihapus. Tandai tidak relevan kalau memang bukan bagiannya.'];
        }

        $run = $this->run($id_run);

        if ($run === null || (string) $run['status'] === 'selesai') {
            return ['ok' => false, 'pesan' => 'Sesi yang sudah ditutup tidak bisa diubah lagi.'];
        }

        $this->db->table('crm_checklist_hasil')->where('id_hasil', $id_hasil)->delete();

        $sisa = (int) $this->db->table('crm_checklist_hasil')
            ->where('run_id', $id_run)->where('perlu_tindakan', 1)->countAllResults();

        $this->db->table('crm_checklist_run')->where('id_run', $id_run)->update([
            'ada_tindakan' => $sisa > 0 ? 1 : 0,
            'updated_at'   => time(),
        ]);

        return ['ok' => true, 'pesan' => 'Butir dihapus.'];
    }

    /**
     * Akhiri sesi. Selama masih ada butir 'belum', sesi tidak boleh ditutup:
     * checklist yang dibiarkan menggantung lebih berbahaya daripada yang belum dikerjakan.
     * Tugas yang tertaut ikut diselesaikan, dan itu tercatat di riwayat tugas.
     */
    public function selesai(int $id_run, ?int $oleh, string $catatan = ''): array
    {
        $run = $this->run($id_run);

        if ($run === null) {
            return ['ok' => false, 'pesan' => 'Sesi tidak ditemukan.'];
        }

        if ((string) $run['status'] === 'selesai') {
            return ['ok' => false, 'pesan' => 'Sesi ini sudah ditutup.'];
        }

        $belum = (int) $this->db->table('crm_checklist_hasil')
            ->where('run_id', $id_run)->where('status', 'belum')->countAllResults();

        if ($belum > 0) {
            return ['ok' => false, 'pesan' => $belum . ' butir masih belum ditandai. Centang selesai atau tidak relevan lebih dulu.'];
        }

        $waktu = time();

        $ubah = [
            'status'     => 'selesai',
            'selesai_at' => $waktu,
            'updated_at' => $waktu,
        ];

        if (trim($catatan) !== '') {
            $ubah['catatan'] = mb_substr(trim($catatan), 0, 2000);
        }

        $this->db->table('crm_checklist_run')->where('id_run', $id_run)->update($ubah);

        if ((int) $run['tugas_id'] > 0) {
            $tugas    = new TugasModel();
            $id_tugas = (int) $run['tugas_id'];

            $ini = $tugas->satu($id_tugas);

            if ($ini !== null && $ini['status'] === 'queued' && $ini['dikerjakan_oleh'] === null) {
                $tugas->ambil($id_tugas, (int) $oleh);
            }

            $tutup = $tugas->ubahStatus($id_tugas, 'completed', (int) $oleh, 'sesi checklist ditutup');

            return $tutup['ok']
                ? ['ok' => true, 'pesan' => 'Sesi checklist selesai.']
                : ['ok' => true, 'pesan' => 'Sesi checklist selesai, tapi tugasnya tidak ikut ditutup: ' . $tutup['pesan']];
        }

        return ['ok' => true, 'pesan' => 'Sesi checklist selesai.'];
    }

    // ------------------------------------------------------------------ catatan

    /**
     * Tulis satu catatan di toko pada hari ini.
     *
     * $beres membedakan dua maksud yang selama ini tertukar: catatan dari CS adalah
     * laporan kerja mandiri — dia sudah mengerjakannya sendiri tanpa menunggu tugas
     * didelegasikan — jadi butuhnya lahir langsung selesai dan sesinya ikut ditutup
     * kalau memang tidak ada lagi yang terbuka. Catatan dari Admin/Superadmin justru
     * permintaan kerja: butuhnya 'belum' dan tugasnya tinggal di antrean.
     *
     * @param array<string, mixed> $toko satu baris hasil petaCek/petaSemua
     *
     * @return array{ok: bool, pesan: string}
     */
    public function buatCatatan(array $toko, string $catatan, int $oleh, bool $beres, ?int $kepada = null): array
    {
        $catatan = trim($catatan);

        if ($catatan === '') {
            return ['ok' => false, 'pesan' => 'Catatannya ditulis dulu.'];
        }

        $judul = mb_substr($catatan, 0, 200);

        // dua klik pada tombol yang sama pernah melahirkan dua catatan yang identik
        // dalam hitungan detik, jadi teks yang sama pada toko dan hari yang sama
        // ditolak sebelum sesinya dibuat
        $lama = $this->runKunci('toko', (int) $toko['id_toko'], null, date('Y-m-d'));

        if ($lama !== null && (int) $this->db->table('crm_checklist_hasil')
            ->where('run_id', (int) $lama['id_run'])->where('judul', $judul)->countAllResults() > 0) {
            return ['ok' => false, 'pesan' => 'Catatan begitu sudah tertulis di toko ini hari ini — tidak perlu dua kali.'];
        }

        $sesi   = $this->mulai('toko', (int) $toko['id_toko'], null, date('Y-m-d'), $oleh, $toko['juragan_id']);
        $id_run = (int) $sesi['run']['id_run'];
        $waktu  = time();

        $this->db->table('crm_checklist_hasil')->insert([
            'run_id'      => $id_run,
            'item_id'     => null,
            'dibuat_oleh' => $oleh,
            'ditujuk_ke'  => $kepada,
            'judul'       => $judul,
            'status'      => $beres ? 'selesai' : 'belum',
            'diisi_oleh'  => $beres ? $oleh : null,
            'created_at'  => $waktu,
            'updated_at'  => $waktu,
        ]);

        if ($beres) {
            // best effort: kalau masih ada butir templat yang belum ditandai,
            // sesinya tetap berjalan tapi catatan ini tetap terhitung selesai
            $this->selesai($id_run, $oleh, '');
        }

        return ['ok' => true, 'pesan' => $beres
            ? 'Catatan tersimpan dan langsung terhitung sudah dikerjakan.'
            : 'Catatan dibuat — tugasnya masuk antrean toko itu.'];
    }

    /**
     * Selesaikan satu catatan: apa yang dikerjakan ditulis di kolom catatan, dan
     * kalau tidak ada lagi yang terbuka di sesi itu, sesinya ikut ditutup supaya
     * tugasnya tidak tinggal menggantung di antrean.
     *
     * @return array{ok: bool, pesan: string}
     */
    public function selesaikanCatatan(int $id_run, int $id_hasil, string $catatan, int $oleh): array
    {
        $catatan = trim($catatan);

        if ($catatan === '') {
            return ['ok' => false, 'pesan' => 'Tulis dulu apa yang sudah dikerjakan.'];
        }

        if (! $this->isi($id_run, $id_hasil, 'selesai', '', mb_substr($catatan, 0, 500), false, $oleh)) {
            return ['ok' => false, 'pesan' => 'Catatan tidak diterima: butirnya bukan milik sesi ini.'];
        }

        $belum = (int) $this->db->table('crm_checklist_hasil')
            ->where('run_id', $id_run)->where('status', 'belum')->countAllResults();

        if ($belum === 0) {
            $this->selesai($id_run, $oleh, '');
        }

        return ['ok' => true, 'pesan' => 'Catatan ditandai sudah dikerjakan.'];
    }

    /** @param array<string, mixed> $s */
    public function catatan(array $s, int $limit = 25, int $offset = 0): array
    {
        return $this->pangkalCatatan($s)
            ->orderBy('h.id_hasil', 'DESC')
            ->get($limit, $offset)
            ->getResultArray();
    }

    /** @param array<string, mixed> $s */
    public function jumlahCatatan(array $s): int
    {
        return (int) $this->pangkalCatatan($s)->countAllResults();
    }

    /**
     * Satu baris = satu catatan. Sesi tidak ikut ditampilkan lagi, jadi catatan dari
     * toko berbeda boleh nyampur di satu halaman.
     *
     * @param array<string, mixed> $s
     */
    private function pangkalCatatan(array $s)
    {
        $b = $this->db->table('crm_checklist_hasil h')
            ->select('h.id_hasil, h.run_id, h.item_id, h.judul, h.status, h.alasan, h.perlu_tindakan,'
                . ' h.catatan AS catatan_butir, h.created_at AS dibuat_pada, h.updated_at,'
                . ' h.dibuat_oleh, h.ditujuk_ke, r.tanggal, r.status AS status_sesi, r.tugas_id,'
                . ' o.id_toko, o.nama AS nama_toko,'
                . ' buat.name AS nama_dibuat, kerja.name AS nama_dikerjakan, ujuk.name AS nama_tujuan')
            ->join('crm_checklist_run r', 'r.id_run = h.run_id')
            ->join('crm_toko_online o', 'o.id_toko = r.toko_id', 'left')
            ->join('user buat', 'buat.id = h.dibuat_oleh', 'left')
            ->join('user kerja', 'kerja.id = h.diisi_oleh', 'left')
            ->join('user ujuk', 'ujuk.id = h.ditujuk_ke', 'left')
            ->where('r.jenis', 'toko');

        if (! empty($s['tanggal'])) {
            $b->where('r.tanggal', $s['tanggal']);
        }

        if (! empty($s['toko_id'])) {
            $b->where('r.toko_id', (int) $s['toko_id']);
        }

        if (! empty($s['toko_ids'])) {
            $b->whereIn('r.toko_id', array_map('intval', $s['toko_ids']));
        }

        // 'sudah' ikut menghitung butir yang ditandai tidak relevan: keduanya
        // sudah tidak menunggu pekerjaan lagi
        $kerja = (string) ($s['kerja'] ?? '');

        if ($kerja === 'sudah') {
            $b->groupStart()->whereIn('h.status', ['selesai', 'tidak_relevan'])->groupEnd();
        } elseif ($kerja === 'belum') {
            $b->where('h.status', 'belum');
        }

        return $b;
    }

    /** Sesi yang belum ditutup untuk toko-toko ini pada satu tanggal. */
    public function hitungTerbuka(array $toko_ids, string $tanggal): int
    {
        $b = $this->db->table('crm_checklist_run')
            ->where('status', 'berjalan')
            ->where('tanggal', $tanggal);

        if ($toko_ids !== []) {
            $b->whereIn('toko_id', array_map('intval', $toko_ids));
        }

        return (int) $b->countAllResults();
    }

    /** @param array<string, mixed> $s */
    private function bangun(array $s)
    {
        $b = $this->pangkal();

        if (! empty($s['jenis'])) {
            $b->where('r.jenis', $s['jenis']);
        }

        if (! empty($s['status'])) {
            $b->where('r.status', $s['status']);
        }

        if (! empty($s['tanggal'])) {
            $b->where('r.tanggal', $s['tanggal']);
        }

        if (! empty($s['toko_id'])) {
            $b->where('r.toko_id', (int) $s['toko_id']);
        }

        if (isset($s['dari'])) {
            $b->where('r.tanggal >=', $s['dari']);
        }

        if (isset($s['sampai'])) {
            $b->where('r.tanggal <=', $s['sampai']);
        }

        if (! empty($s['toko_ids'])) {
            $b->groupStart()->whereIn('r.toko_id', array_map('intval', $s['toko_ids']))
                ->orWhere('r.jenis', 'email')->groupEnd();
        }

        return $b;
    }

    private function pangkal()
    {
        $hasil = $this->p('crm_checklist_hasil');

        return $this->db->table('crm_checklist_run r')
            ->select('r.*, o.nama AS nama_toko, j.nama_juragan, u.name AS nama_dikerjakan, t.status AS status_tugas, t.judul AS judul_tugas,'
                . ' (SELECT COUNT(*) FROM `' . $hasil . '` x WHERE x.run_id = r.id_run) AS jumlah_butir,'
                . ' (SELECT COUNT(*) FROM `' . $hasil . '` x WHERE x.run_id = r.id_run AND x.status = \'belum\') AS jumlah_belum,'
                . ' (SELECT COUNT(*) FROM `' . $hasil . '` x WHERE x.run_id = r.id_run AND x.perlu_tindakan = 1) AS jumlah_tindakan')
            ->join('crm_toko_online o', 'o.id_toko = r.toko_id', 'left')
            ->join('juragan j', 'j.id_juragan = o.juragan_id', 'left')
            ->join('user u', 'u.id = r.dikerjakan_oleh', 'left')
            ->join('crm_tugas t', 't.id_tugas = r.tugas_id', 'left');
    }

    /** Kunci sesi: satu sasaran + satu tanggal = satu sesi, tidak peduli siapa yang membuka. */
    private function kunci(string $jenis, ?int $toko_id, ?string $akun, string $tanggal): string
    {
        return $jenis === 'email'
            ? 'email-' . mb_strtolower(trim((string) $akun)) . '-' . $tanggal
            : 'toko-' . (int) $toko_id . '-' . $tanggal;
    }

    private function judulTugas(string $jenis, ?int $toko_id, ?string $akun, string $tanggal): string
    {
        if ($jenis === 'email') {
            return 'Periksa email ' . ($akun ?: 'umum') . ' (' . $tanggal . ')';
        }

        $baris = $this->db->table('crm_toko_online')->select('nama')->where('id_toko', (int) $toko_id)
            ->get(1)->getRowArray();

        return 'Checklist toko ' . (($baris['nama'] ?? null) ?: '#' . (int) $toko_id) . ' (' . $tanggal . ')';
    }

    /**
     * Tugas checklist tidak boleh tinggal di antrean tanpa penggarap: CS yang
     * mulai menjawab butir ikut mengambil tugasnya, dengan guard anti-klaim ganda.
     */
    private function klaimTugas(int $id_run, int $oleh): void
    {
        $baris = $this->db->table('crm_checklist_run')->select('tugas_id')->where('id_run', $id_run)->get(1)->getRowArray();

        if ($baris === null || (int) $baris['tugas_id'] < 1) {
            return;
        }

        $tugas = new TugasModel();
        $ini   = $tugas->satu((int) $baris['tugas_id']);

        if ($ini !== null && $ini['status'] === 'queued' && $ini['dikerjakan_oleh'] === null) {
            $tugas->ambil((int) $baris['tugas_id'], $oleh);
        }
    }
}
