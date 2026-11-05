<?php

namespace App\Models;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Model;

/**
 * Toko online + siapa CS yang memegangnya + sudah sejauh mana ia mengenal tokonya.
 *
 * Dua kunci unik di tabel ini adalah bagian dari aturan main, bukan hiasan:
 * satu toko tidak boleh terdaftar dua kali untuk platform yang sama, dan satu CS
 * tidak punya dua baris pengenalan untuk satu toko.
 */
class TokoOnlineModel extends Model
{
    public const PENGENALAN = [
        'belum'  => 'Belum dipelajari',
        'sedang' => 'Sedang dipelajari',
        'sudah'  => 'Sudah dipahami',
    ];

    /**
     * @param array<string, mixed> $s
     * @return list<array<string, mixed>>
     */
    public function daftar(array $s, int $limit = 25, int $offset = 0): array
    {
        return $this->bangun($s)
            ->orderBy('o.aktif', 'DESC')
            ->orderBy('j.nama_juragan', 'ASC')
            ->orderBy('o.platform', 'ASC')
            ->get($limit, $offset)
            ->getResultArray();
    }

    /** @param array<string, mixed> $s */
    public function jumlah(array $s): int
    {
        return (int) $this->bangun($s)->countAllResults();
    }

    public function satu(int $id_toko): ?array
    {
        $baris = $this->pangkal()->where('o.id_toko', $id_toko)->get(1)->getRowArray();

        return $baris ?: null;
    }

    /**
     * Berapa toko yang masih memakai tiap platform.
     *
     * Dipakai rincian platform di popus toko: sebelum menyembunyikan sebuah
     * platform dari pilihan, Superadmin perlu tahu apakah ada toko yang
     * menempel padanya.
     *
     * @return array<string, int> nama platform => jumlah toko
     */
    public function jumlahPerPlatform(): array
    {
        $hasil = [];

        $baris = $this->db->table('crm_toko_online')
            ->select('platform, COUNT(*) AS jumlah')
            ->where('platform !=', '')
            ->groupBy('platform')
            ->get()
            ->getResultArray();

        foreach ($baris as $b) {
            $hasil[(string) $b['platform']] = (int) $b['jumlah'];
        }

        return $hasil;
    }

    /**
     * Berapa lembar checklist yang pernah dibuka tiap toko.
     *
     * Angka ini menjawab pertanyaan paling penting sebelum menghapus: seberapa
     * banyak catatan kerja yang ikut hilang bersama tokonya.
     *
     * @return array<int, int> id_toko => jumlah lembar
     */
    public function lembarPerToko(): array
    {
        $hasil = [];

        $baris = $this->db->table('crm_checklist_run')
            ->select('toko_id, COUNT(*) AS jumlah')
            ->where('jenis', 'toko')
            ->where('toko_id IS NOT NULL', null, false)
            ->groupBy('toko_id')
            ->get()
            ->getResultArray();

        foreach ($baris as $b) {
            $hasil[(int) $b['toko_id']] = (int) $b['jumlah'];
        }

        return $hasil;
    }

    /**
     * Toko lain yang sudah memegang kombinasi juragan + platform ini.
     *
     * Toko tanpa juragan tidak pernah kembar: kunci uniknya memuat kolom yang
     * boleh NULL, dan MySQL tidak pernah menyamakan dua NULL. Karena itu pencarian
     * hanya jalan kalau jurangannya benar-benar ada.
     */
    public function kembar(?int $juragan_id, string $platform, int $kecuali = 0): ?array
    {
        if ($juragan_id === null || $juragan_id <= 0) {
            return null;
        }

        $b = $this->db->table('crm_toko_online')
            ->where('juragan_id', $juragan_id)
            ->where('platform', mb_substr(trim($platform), 0, 40));

        if ($kecuali > 0) {
            $b->where('id_toko !=', $kecuali);
        }

        $baris = $b->get(1)->getRowArray();

        return $baris ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function simpan(array $data, ?int $oleh, int $id_toko = 0): int
    {
        $waktu = time();

        $juragan = (int) ($data['juragan_id'] ?? 0);

        $isi = [
            'juragan_id' => $juragan > 0 ? $juragan : null,
            'platform'   => mb_substr(trim((string) ($data['platform'] ?? '')), 0, 40),
            'nama'       => mb_substr(trim((string) $data['nama']), 0, 120),
            'url'        => $this->isiAtauNull($data['url'] ?? null),
            'aktif'      => ((int) ($data['aktif'] ?? 1)) === 1 ? 1 : 0,
            'catatan'    => $this->isiAtauNull($data['catatan'] ?? null),
            'updated_at' => $waktu,
        ];

        if ($id_toko > 0) {
            $this->db->table('crm_toko_online')->where('id_toko', $id_toko)->update($isi);

            return $id_toko;
        }

        $this->db->table('crm_toko_online')->insert($isi + [
            'dibuat_oleh' => $oleh,
            'created_at'  => $waktu,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * Menonaktifkan toko: jejak checklist dan tugas lama tetap menunjuk ke sini.
     * Jalan menghapus yang sebenarnya ada di hapus(), dan itu harus dipilih
     * dengan sadar di layar.
     */
    public function setAktif(int $id_toko, bool $aktif): bool
    {
        return $this->db->table('crm_toko_online')->where('id_toko', $id_toko)
            ->update(['aktif' => $aktif ? 1 : 0, 'updated_at' => time()]) >= 0;
    }

    /**
     * Menghapus satu toko untuk selamanya, bersama seluruh jejaknya.
     *
     * Empat tabel memang menunjuk ke baris ini dan tidak ada kunci asing yang
     * menahan (periksa: crm_toko_akses, crm_checklist_run, crm_checklist_item,
     * dan crm_checklist_hasil lewat run-nya), jadi semuanya bisa dibersihkan
     * dalam satu transaksi — tidak ada yang boleh tertinggal setengah.
     *
     * @return array{nama: string, lembar: int, butir: int, tautan: int, user: list<int>}|null
     *         null kalau tokonya memang tidak ada
     */
    public function hapus(int $id_toko): ?array
    {
        $toko = $this->satu($id_toko);

        if ($toko === null) {
            return null;
        }

        $run = array_map('intval', array_column(
            $this->db->table('crm_checklist_run')->select('id_run')
                ->where('jenis', 'toko')->where('toko_id', $id_toko)->get()->getResultArray(),
            'id_run'
        ));

        $butir = array_map('intval', array_column(
            $this->db->table('crm_checklist_item')->select('id_item')
                ->where('jenis', 'toko')->where('toko_id', $id_toko)->get()->getResultArray(),
            'id_item'
        ));

        // nama akun ditagih sebelum tautannya dibuang: setelah itu tidak ada lagi
        // jalan mengetahui siapa saja yang tokonya hilang dari daftar
        $tautan = array_map('intval', array_column(
            $this->db->table('crm_toko_akses')->select('user_id')
                ->where('toko_id', $id_toko)->get()->getResultArray(),
            'user_id'
        ));

        $this->db->transBegin();

        try {
            if ($run !== []) {
                $this->db->table('crm_checklist_hasil')->whereIn('run_id', $run)->delete();
            }

            if ($butir !== []) {
                // jaring pengaman: butir toko ini sebenarnya hanya dipakai lembar
                // toko ini, tapi hasil yang tertinggal tidak boleh ada
                $this->db->table('crm_checklist_hasil')->whereIn('item_id', $butir)->delete();
            }

            $this->db->table('crm_checklist_item')->where('jenis', 'toko')->where('toko_id', $id_toko)->delete();
            $this->db->table('crm_checklist_run')->where('jenis', 'toko')->where('toko_id', $id_toko)->delete();
            $this->db->table('crm_toko_akses')->where('toko_id', $id_toko)->delete();
            $this->db->table('crm_toko_online')->where('id_toko', $id_toko)->delete();

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();

                return null;
            }

            $this->db->transCommit();
        } catch (DatabaseException $e) {
            $this->db->transRollback();

            return null;
        }

        return [
            'nama'   => (string) $toko['nama'],
            'lembar' => count($run),
            'butir'  => count($butir),
            'tautan' => count($tautan),
            'user'   => $tautan,
        ];
    }

    /**
     * CS yang ditautkan ke toko ini (sudah dikenal atau belum).
     *
     * @return list<array<string, mixed>>
     */
    public function akses(int $id_toko): array
    {
        return $this->db->table('crm_toko_akses a')
            ->select('a.*, u.name AS nama_staf, u.level, v.name AS nama_verifikator')
            ->join('user u', 'u.id = a.user_id')
            ->join('user v', 'v.id = a.diverifikasi_oleh', 'left')
            ->where('a.toko_id', $id_toko)
            ->orderBy('u.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** Tautan CS ke toko. Sudah ada berarti tidak diubah apa pun (idempoten). */
    public function beriAkses(int $id_toko, int $user_id, ?int $oleh): int
    {
        $ada = $this->db->table('crm_toko_akses')
            ->where('toko_id', $id_toko)->where('user_id', $user_id)->get(1)->getRowArray();

        if ($ada !== null) {
            return (int) $ada['id_akses'];
        }

        $this->db->table('crm_toko_akses')->insert([
            'toko_id'     => $id_toko,
            'user_id'     => $user_id,
            'pengenalan'  => 'belum',
            'dibuat_oleh' => $oleh,
            'created_at'  => time(),
            'updated_at'  => time(),
        ]);

        return (int) $this->db->insertID();
    }

    public function copotAkses(int $id_toko, int $user_id): bool
    {
        $this->db->table('crm_toko_akses')->where('toko_id', $id_toko)->where('user_id', $user_id)->delete();

        return true;
    }

    /**
     * Catat kemajuan pengenalan toko.
     *
     * Tanggal mulai dan selesai diisi sendiri mengikuti statusnya, supaya "sudah
     * dipahami" tanpa tanggal tidak pernah terjadi. Verifikasi hanya dicatat kalau
     * yang mengisi memang Admin/Superadmin (dijaga di controller).
     */
    public function ubahPengenalan(int $id_toko, int $user_id, string $status, string $catatan = '', string $materi = '', ?int $verifikator = null): bool
    {
        if (! array_key_exists($status, self::PENGENALAN)) {
            return false;
        }

        $waktu = time();
        $hari  = date('Y-m-d');

        $isi = ['pengenalan' => $status, 'updated_at' => $waktu];

        if ($status === 'sedang' || $status === 'sudah') {
            $lama = $this->db->table('crm_toko_akses')
                ->where('toko_id', $id_toko)->where('user_id', $user_id)->get(1)->getRowArray();

            $isi['mulai'] = ! empty($lama['mulai']) ? $lama['mulai'] : $hari;
        }

        if ($status === 'belum') {
            $isi['mulai']   = null;
            $isi['selesai'] = null;
        }

        if ($status === 'sudah') {
            $isi['selesai'] = $hari;
        }

        if ($status !== 'sudah') {
            $isi['selesai'] = null;
        }

        if (trim($catatan) !== '') {
            $isi['catatan'] = trim($catatan);
        }

        if (trim($materi) !== '') {
            $isi['materi'] = trim($materi);
        }

        if ($verifikator !== null) {
            $isi['diverifikasi_oleh'] = $verifikator;
        }

        if ($this->db->table('crm_toko_akses')->where('toko_id', $id_toko)->where('user_id', $user_id)->countAllResults() === 0) {
            $this->beriAkses($id_toko, $user_id, $verifikator ?? $user_id);
        }

        return $this->db->table('crm_toko_akses')
            ->where('toko_id', $id_toko)
            ->where('user_id', $user_id)
            ->update($isi) >= 0;
    }

    /** Toko online milik akun ini, beserta status pengenalannya sendiri. */
    public function milikUser(int $user_id, array $juragan_ids): array
    {
        $akses = [];

        foreach ($this->db->table('crm_toko_akses')
            ->select('toko_id, pengenalan, mulai, selesai, materi, catatan, diverifikasi_oleh')
            ->where('user_id', $user_id)->get()->getResultArray() as $a) {
            $akses[(int) $a['toko_id']] = $a;
        }

        $toko = [];

        if ($juragan_ids !== []) {
            $toko = $this->db->table('crm_toko_online')->select('id_toko')->where('aktif', 1)
                ->whereIn('juragan_id', array_map('intval', $juragan_ids))->get()->getResultArray();
        }

        $dipilih = array_values(array_unique(array_merge(
            array_map('intval', array_column($toko, 'id_toko')),
            array_keys($akses)
        )));

        if ($dipilih === []) {
            return [];
        }

        $baris = $this->pangkal()->where('o.aktif', 1)->whereIn('o.id_toko', $dipilih)
            ->orderBy('j.nama_juragan', 'ASC')->orderBy('o.platform', 'ASC')
            ->get()->getResultArray();

        foreach ($baris as $i => $t) {
            $a = $akses[(int) $t['id_toko']] ?? null;

            $baris[$i]['pengenalan_saya'] = $a['pengenalan'] ?? 'belum';
            $baris[$i]['mulai_saya']      = $a['mulai'] ?? null;
            $baris[$i]['selesai_saya']    = $a['selesai'] ?? null;
            $baris[$i]['catatan_saya']    = $a['catatan'] ?? null;
            $baris[$i]['materi_saya']      = $a['materi'] ?? null;
        }

        return $baris;
    }

    /**
     * Semua toko aktif sebagai peta id_toko => baris. Dipakai Admin/Superadmin:
     * mereka mengurus toko yang belum punya pegangan juga, jadi tidak bisa
     * disaring lewat juragan saja.
     *
     * @return array<int, array<string, mixed>>
     */
    public function petaSemua(): array
    {
        return $this->petakan(
            $this->db->table('crm_toko_online')->select('id_toko, nama, juragan_id, platform')
                ->where('aktif', 1)->orderBy('nama', 'ASC')->get()->getResultArray()
        );
    }

    /** @param list<array<string, mixed>> $baris @return array<int, array<string, mixed>> */
    private function petakan(array $baris): array
    {
        $punya = [];

        foreach ($baris as $t) {
            $punya[(int) $t['id_toko']] = [
                'nama'       => (string) $t['nama'],
                'juragan_id' => $t['juragan_id'] !== null ? (int) $t['juragan_id'] : null,
                'platform'   => (string) ($t['platform'] ?? ''),
            ];
        }

        return $punya;
    }

    public function aksesPunyaUser(int $id_toko, int $user_id): bool
    {
        return (int) $this->db->table('crm_toko_akses')
            ->where('toko_id', $id_toko)->where('user_id', $user_id)->countAllResults() > 0;
    }

    /**
     * Toko yang boleh dicek seorang akun: toko milik juragan yang didelegasikan
     * kepadanya, ditambah toko yang memang ditautkan kepadanya sendiri. Jalan
     * kedua itu penting untuk toko tanpa juragan — tanpa tautan, tidak ada
     * cara lain untuk menyerahkannya ke orang.
     *
     * @param array<int, int> $juragan_ids
     *
     * @return array<int, string> id_toko => nama
     */
    public function petaCek(array $juragan_ids, int $user_id): array
    {
        $baris = [];

        if ($juragan_ids !== []) {
            $baris = array_merge($baris, $this->db->table('crm_toko_online')->select('id_toko, nama, juragan_id, platform')
                ->where('aktif', 1)->whereIn('juragan_id', array_map('intval', $juragan_ids))->get()->getResultArray());
        }

        $khusus = array_map('intval', array_column(
            $this->db->table('crm_toko_akses')->select('toko_id')->where('user_id', $user_id)
                ->get()->getResultArray(),
            'toko_id'
        ));

        if ($khusus !== []) {
            $baris = array_merge($baris, $this->db->table('crm_toko_online')->select('id_toko, nama, juragan_id, platform')
                ->where('aktif', 1)->whereIn('id_toko', $khusus)->get()->getResultArray());
        }

        $punya = $this->petakan($baris);
        uasort($punya, static fn (array $a, array $b): int => strcmp($a['nama'], $b['nama']));

        return $punya;
    }

    /**
     * Akun-akun yang ditautkan langsung ke toko cek, per toko. Ditampilkan di
     * kartu delegasi supaya kelihatan siapa saja yang boleh membuka lembar
     * toko itu di luar delegasi juragan.
     *
     * @param array<int, int> $id_tokos
     *
     * @return array<int, list<array{id:int, nama:string, level:string}>>
     */
    public function pemegang(array $id_tokos): array
    {
        if ($id_tokos === []) {
            return [];
        }

        $peta = [];

        $baris = $this->db->table('crm_toko_akses a')
            ->select('a.toko_id, a.user_id, u.name AS nama, u.level')
            ->join('user u', 'u.id = a.user_id')
            ->whereIn('a.toko_id', array_map('intval', $id_tokos))
            ->orderBy('u.name', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($baris as $a) {
            $peta[(int) $a['toko_id']][] = [
                'id'    => (int) $a['user_id'],
                'nama'  => (string) $a['nama'],
                'level' => (string) $a['level'],
            ];
        }

        return $peta;
    }

    /** Berapa toko yang belum dipahami akun ini — untuk angka badge/penanda. */
    public function hitungBelumDikenal(int $user_id, array $juragan_ids): int
    {
        $n = 0;

        foreach ($this->milikUser($user_id, $juragan_ids) as $t) {
            if ((string) ($t['pengenalan'] ?? 'belum') !== 'sudah') {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @param array<string, mixed> $s
     */
    private function bangun(array $s)
    {
        $b = $this->pangkal();

        if (isset($s['aktif']) && $s['aktif'] !== '') {
            $b->where('o.aktif', (int) $s['aktif']);
        }

        if (! empty($s['juragan_id'])) {
            $b->where('o.juragan_id', (int) $s['juragan_id']);
        }

        if (! empty($s['platform'])) {
            $b->where('o.platform', $s['platform']);
        }

        if (! empty($s['cari'])) {
            $b->groupStart()
                ->like('o.nama', $s['cari'])
                ->orLike('o.url', $s['cari'])
                ->orLike('o.catatan', $s['cari'])
                ->orLike('j.nama_juragan', $s['cari'])
                ->groupEnd();
        }

        if (! empty($s['juragan_ids'])) {
            $b->whereIn('o.juragan_id', array_map('intval', $s['juragan_ids']));
        }

        return $b;
    }

    private function pangkal()
    {
        $akses = $this->db->prefixTable('crm_toko_akses');

        // LEFT JOIN: toko cek boleh berdiri sendiri tanpa juragan, dan tokonya
        // tetap harus muncul di daftar meski kolom juragannya kosong
        return $this->db->table('crm_toko_online o')
            ->select('o.*, j.nama_juragan, j.juragan AS slug_juragan,'
                . ' (SELECT COUNT(*) FROM `' . $akses . '` a WHERE a.toko_id = o.id_toko) AS jumlah_cs,'
                . ' (SELECT COUNT(*) FROM `' . $akses . '` a WHERE a.toko_id = o.id_toko AND a.pengenalan = \'sudah\') AS cs_siap')
            ->join('juragan j', 'j.id_juragan = o.juragan_id', 'left');
    }

    private function isiAtauNull(mixed $nilai): ?string
    {
        $bersih = trim((string) ($nilai ?? ''));

        return $bersih === '' ? null : $bersih;
    }
}
