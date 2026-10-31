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
     * @return array{run: array<string, mixed>, dibuat: bool}
     */
    public function mulai(string $jenis, ?int $toko_id, ?string $akun, string $tanggal, ?int $oleh): array
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
                'run_id'     => $id_run,
                'item_id'    => (int) $i['id_item'],
                'judul'      => $i['judul'],
                'status'     => 'belum',
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ]);
        }

        $tugas = (new TugasModel())->buatUnik([
            'judul'      => $this->judulTugas($jenis, $toko_id, $akun, $tanggal),
            'deskripsi'  => 'Sesi checklist dibuat otomatis dari templat. Centang semua butir lalu akhiri sesi.',
            'jenis'      => $jenis === 'email' ? 'pemeriksaan_email' : 'checklist_toko',
            'prioritas'  => 'normal',
            'sumber'     => 'sistem',
            'kunci_unik' => 'checklist-' . $id_run,
            'juragan_id' => $toko_id,
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
     * Jawab satu butir. 'tidak relevan' wajib beralasan, dan satu butir yang
     * butuh tindakan membuat sesinya ikut ditandai — itu signal bagi CS berikutnya.
     */
    public function isi(int $id_run, int $item_id, string $status, string $alasan = '', string $catatan = '', bool $tindakan = false, ?int $oleh = null): bool
    {
        if (! array_key_exists($status, self::STATUS)) {
            return false;
        }

        if ($status === 'tidak_relevan' && trim($alasan) === '') {
            return false;
        }

        $ada = $this->db->table('crm_checklist_hasil')
            ->where('run_id', $id_run)->where('item_id', $item_id)->get(1)->getRowArray();

        if ($ada === null) {
            return false;
        }

        $this->db->table('crm_checklist_hasil')->where('id_hasil', (int) $ada['id_hasil'])->update([
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

    // ------------------------------------------------------------------ templat

    /** @param array<string, mixed> $data */
    public function simpanButir(array $data, ?int $oleh, int $id_item = 0): int
    {
        $waktu = time();

        $isi = [
            'jenis'     => $data['jenis'] === 'email' ? 'email' : 'toko',
            'toko_id'   => ! empty($data['toko_id']) ? (int) $data['toko_id'] : null,
            'judul'     => mb_substr(trim((string) $data['judul']), 0, 200),
            'hint'      => trim((string) ($data['hint'] ?? '')) === '' ? null : mb_substr(trim((string) $data['hint']), 0, 255),
            'urut'      => max(0, min(9999, (int) ($data['urut'] ?? 0))),
            'wajib'     => ((int) ($data['wajib'] ?? 1)) === 1 ? 1 : 0,
            'aktif'     => ((int) ($data['aktif'] ?? 1)) === 1 ? 1 : 0,
            'updated_at' => $waktu,
        ];

        if ($isi['jenis'] === 'email') {
            $isi['toko_id'] = null;
        }

        if ($id_item > 0) {
            $this->db->table('crm_checklist_item')->where('id_item', $id_item)->update($isi);

            return $id_item;
        }

        $this->db->table('crm_checklist_item')->insert($isi + [
            'dibuat_oleh' => $oleh,
            'created_at'  => $waktu,
        ]);

        return (int) $this->db->insertID();
    }

    /** @return list<array<string, mixed>> */
    public function semuaButir(string $jenis): array
    {
        return $this->db->table('crm_checklist_item i')
            ->select('i.*, j.nama_juragan')
            ->join('juragan j', 'j.id_juragan = i.toko_id', 'left')
            ->where('i.jenis', $jenis)
            ->orderBy('i.aktif', 'DESC')
            ->orderBy('i.urut', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** Butir tidak pernah dihapus: matikan, riwayat sesi lama tetap menunjuk judulnya. */
    public function setButirAktif(int $id_item, bool $aktif): bool
    {
        $this->db->table('crm_checklist_item')->where('id_item', $id_item)
            ->update(['aktif' => $aktif ? 1 : 0, 'updated_at' => time()]);

        return true;
    }

    /** Sesi yang belum ditutup untuk toko-toko ini pada satu tanggal. */
    public function hitungTerbuka(array $juragan_ids, string $tanggal): int
    {
        $b = $this->db->table('crm_checklist_run')
            ->where('status', 'berjalan')
            ->where('tanggal', $tanggal);

        if ($juragan_ids !== []) {
            $b->whereIn('toko_id', array_map('intval', $juragan_ids));
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

        if (! empty($s['juragan_ids'])) {
            $b->groupStart()->whereIn('r.toko_id', array_map('intval', $s['juragan_ids']))
                ->orWhere('r.jenis', 'email')->groupEnd();
        }

        return $b;
    }

    private function pangkal()
    {
        $hasil = $this->p('crm_checklist_hasil');

        return $this->db->table('crm_checklist_run r')
            ->select('r.*, j.nama_juragan, u.name AS nama_dikerjakan, t.status AS status_tugas, t.judul AS judul_tugas,'
                . ' (SELECT COUNT(*) FROM `' . $hasil . '` x WHERE x.run_id = r.id_run) AS jumlah_butir,'
                . ' (SELECT COUNT(*) FROM `' . $hasil . '` x WHERE x.run_id = r.id_run AND x.status = \'belum\') AS jumlah_belum,'
                . ' (SELECT COUNT(*) FROM `' . $hasil . '` x WHERE x.run_id = r.id_run AND x.perlu_tindakan = 1) AS jumlah_tindakan')
            ->join('juragan j', 'j.id_juragan = r.toko_id', 'left')
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

        $nama = $this->db->table('juragan')->select('nama_juragan')->where('id_juragan', (int) $toko_id)
            ->get(1)->getRowArray()['nama_juragan'] ?? null;

        return 'Checklist toko ' . ($nama ?: '#' . (int) $toko_id) . ' (' . $tanggal . ')';
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
