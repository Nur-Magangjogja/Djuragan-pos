<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Antrean tugas operasional CS.
 *
 * Satu tabel untuk semua pekerjaan yang harus dikerjakan seseorang: catatan hasil
 * percakapan, follow-up pelanggan, reminder transaksi, checklist toko, dan
 * pemeriksaan email. Alasannya sederhana: kalau tiap jenis pekerjaan punya tabel
 * sendiri, "apa yang belum selesai hari ini" tidak bisa dijawab dengan satu angka.
 *
 * Status tugas sengaja berbeda dari label pelanggan (crm_tag) dan status
 * pembayaran nota. Yang pertama menjelaskan pekerjaannya, yang kedua menjelaskan
 * kondisinya — menggabungkan keduanya bikin laporan tidak bisa dibaca.
 *
 * Semua perintah memakai query builder dengan binding; nilai dari request tidak
 * pernah masuk SQL apa adanya.
 */
class TugasModel extends Model
{
    /** Status yang dikenal mesin tugas. */
    public const STATUS = [
        'queued'      => 'Di antrean',
        'assigned'    => 'Ditetapkan',
        'in_progress' => 'Sedang dikerjakan',
        'waiting'     => 'Menunggu respons',
        'completed'   => 'Selesai',
        'cancelled'   => 'Dibatalkan',
    ];

    public const JENIS = [
        'catatan_wa'         => 'Catatan WhatsApp',
        'followup_pelanggan' => 'Follow-up pelanggan',
        'reminder_transaksi' => 'Reminder transaksi',
        'checklist_toko'     => 'Checklist toko',
        'pemeriksaan_email'  => 'Cek email',
        'lain'               => 'Lainnya',
    ];

    public const PRIORITAS = [
        'rendah'   => 'Rendah',
        'normal'   => 'Normal',
        'tinggi'   => 'Tinggi',
        'mendesak' => 'Mendesak',
    ];

    /** Status yang masih dihitung "belum selesai". */
    public const BELUM_SELESAI = ['queued', 'assigned', 'in_progress', 'waiting'];

    /**
     * Kejadian yang berarti "sudah di tangan, tapi tidak sampai selesai" — dasar
     * tab Tidak Jadi Dikerjakan di layar reminder.
     */
    public const JEJAK_LEPAS = ['operan_manual', 'operan_otomatis', 'dibatalkan'];

    /**
     * Kelompok pemicu reminder. Tiap pemicu ditulis di `kunci_unik` dengan awalan
     * itu (mis. `ulasan-42`), jadi satu tugas hanya pernah lahir dari satu pemicu.
     * Awalan `dp-` dan `pelunasan-` tidak dipakai lagi: menagih sisa bayar bukan
     * urusan Reminder Bintang 5, itu dijalankan lewat CRM Follow-Up. Baris tugas
     * lama dengan awalan itu tetap ada, hanya tidak pernah lahir yang baru.
     */
    public const PEMICU = [
        'ulasan' => ['ulasan-'],
    ];

    /**
     * Jenis yang sudah punya submenu sendiri di Tugas CS (WhatsApp, Bintang 5,
     * Cek Toko, Cek Email). Daftar 'lain-lain' hanya mengurus sisanya, supaya
     * satu tugas tidak pernah muncul di dua layar atau malah tidak di mana-mana.
     */
    public const PUNYA_SUBMENU = [
        'catatan_wa',
        'checklist_toko',
        'pemeriksaan_email',
        'reminder_transaksi',
    ];

    /** Jeda minimal antar-sapuan operan otomatis, dalam detik. */
    private const JEDA_OPERAN = 300;

    /** Kunci di crm_setting: kapan operan otomatis terakhir jalan + hasilnya. */
    public const S_OPERAN_AKHIR  = 'operan_shift_terakhir';
    public const S_OPERAN_JUMLAH = 'operan_shift_jumlah';

    /**
     * Transisi yang boleh. Tugas selesai/dibatalkan tidak bisa "diambil" lagi —
     * hanya bisa dibuka kembali, dan itu tercatat sebagai event baru supaya
     * angka laporan tidak berubah diam-diam.
     */
    private const TRANSISI = [
        'queued'      => ['assigned', 'in_progress', 'cancelled'],
        'assigned'    => ['in_progress', 'waiting', 'queued', 'completed', 'cancelled'],
        'in_progress' => ['waiting', 'queued', 'completed', 'cancelled'],
        'waiting'     => ['in_progress', 'queued', 'completed', 'cancelled'],
        'completed'   => ['queued'],
        'cancelled'   => ['queued'],
    ];

    // ------------------------------------------------------------------
    // baca
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $s */
    public function daftar(array $s, int $limit = 25, int $offset = 0): array
    {
        return $this->bangun($s)
            ->orderBy('t.prioritas', 'DESC')
            ->orderBy('t.tenggat', 'ASC')
            ->orderBy('t.id_tugas', 'DESC')
            ->get($limit, $offset)
            ->getResultArray();
    }

    /** @param array<string, mixed> $s */
    public function jumlah(array $s): int
    {
        return (int) $this->bangun($s)->countAllResults();
    }

    public function satu(int $id): ?array
    {
        $baris = $this->pangkal()->where('t.id_tugas', $id)->get(1)->getRowArray();

        return $baris ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function riwayat(int $id_tugas): array
    {
        return $this->db->table('crm_tugas_event e')
            ->select('e.*, u.name AS nama_aktor')
            ->join('user u', 'u.id = e.user_id', 'left')
            ->where('e.tugas_id', $id_tugas)
            ->orderBy('e.id_event', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** Jumlah tugas yang masih terbuka untuk toko yang boleh dilihat sesi ini. */
    public function hitungTerbuka(array $juragan_ids): int
    {
        $b = $this->db->table('crm_tugas')->whereIn('status', self::BELUM_SELESAI);
        $this->terapkanToko($b, $juragan_ids);

        return (int) $b->countAllResults();
    }

    /**
     * Tugas satu pemicu reminder yang masih terbuka — dasar badge kategorinya di
     * sidebar. Pemicunya dikenali dari `kunci_unik`, jadi angka badge tidak pernah
     * menghitung ulang tugas buatan orang.
     */
    public function hitungPemicu(string $pemicu, array $juragan_ids): int
    {
        return $this->jumlah(['pemicu' => $pemicu, 'terbuka' => true, 'juragan_ids' => $juragan_ids]);
    }

    /**
     * Ringkasan per status + jumlah yang lewat tenggat, untuk kartu dasbor.
     *
     * @return array<string, int>
     */
    public function ringkas(array $juragan_ids): array
    {
        $peta = array_fill_keys(array_keys(self::STATUS), 0);

        foreach ($this->hitungPerStatus($juragan_ids) as $baris) {
            $peta[$baris['status']] = (int) $baris['n'];
        }

        $terlambat = $this->db->table('crm_tugas')
            ->whereIn('status', self::BELUM_SELESAI)
            ->where('tenggat IS NOT NULL')
            ->where('tenggat <', date('Y-m-d'));
        $this->terapkanToko($terlambat, $juragan_ids);

        $peta['terlambat'] = (int) $terlambat->countAllResults();

        return $peta;
    }

    /** @return list<array<string, mixed>> */
    private function hitungPerStatus(array $juragan_ids): array
    {
        $b = $this->db->table('crm_tugas')->select('status, COUNT(*) n')->groupBy('status');
        $this->terapkanToko($b, $juragan_ids);

        return $b->get()->getResultArray();
    }

    /**
     * Beban kerja per orang, untuk dasbor Admin dan laporan produktivitas.
     *
     * Hanya tugas yang sudah punya penggarap yang ikut tercatat: yang masih di
     * antrean belum jadi pekerjaan siapa-siapa, jadi tidak boleh membuat satu
     * orang terlihat kewalahan oleh tugas yang belum diambilnya. Periode menyaring
     * `updated_at`, yaitu kapan orang itu terakhir mengutak-atik tugasnya.
     *
     * @return list<array<string, mixed>>
     */
    public function bebanPegawai(array $juragan_ids, int $dari = 0, int $sampai = 0): array
    {
        $hari  = date('Y-m-d');
        $belum = implode("','", self::BELUM_SELESAI);

        $b = $this->db->table('crm_tugas t')
            ->select('t.dikerjakan_oleh AS user_id, u.name AS nama,'
                . ' COUNT(*) AS pegangan,'
                . " SUM(CASE WHEN t.status IN ('{$belum}') THEN 1 ELSE 0 END) AS terbuka,"
                . " SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS selesai,"
                . " SUM(CASE WHEN t.status = 'cancelled' THEN 1 ELSE 0 END) AS batal,"
                . " SUM(CASE WHEN t.cara_penugasan = 'operan' THEN 1 ELSE 0 END) AS operan,"
                . " SUM(CASE WHEN t.status IN ('{$belum}') AND t.tenggat IS NOT NULL AND t.tenggat < '{$hari}' THEN 1 ELSE 0 END) AS terlambat")
            ->join('user u', 'u.id = t.dikerjakan_oleh', 'left')
            ->where('t.dikerjakan_oleh IS NOT NULL');

        $this->saringToko($b, 't.juragan_id', $juragan_ids);

        if ($dari > 0) {
            $b->where('t.updated_at >=', $dari);
        }

        if ($sampai > 0) {
            $b->where('t.updated_at <=', $sampai);
        }

        return $b->groupBy('t.dikerjakan_oleh')->orderBy('terlambat', 'DESC')->orderBy('terbuka', 'DESC')
            ->get()->getResultArray();
    }

    /**
     * Kejadian pada tugas lintas tugas (riwayat massal) untuk laporan aktivitas.
     *
     * @param array<string, mixed> $s
     * @return list<array<string, mixed>>
     */
    public function aktivitas(array $s, int $limit = 50, int $offset = 0): array
    {
        return $this->aktivitasPangkal($s)->orderBy('e.id_event', 'DESC')->get($limit, $offset)->getResultArray();
    }

    /** @param array<string, mixed> $s */
    public function aktivitasJumlah(array $s): int
    {
        return (int) $this->aktivitasPangkal($s)->countAllResults();
    }

    /**
     * Jumlah kejadian per jenis pada periode yang sama — dipakai sebagai baris
     * ringkasan di atas tabel riwayat.
     *
     * @param array<string, mixed> $s
     * @return array<string, int>
     */
    public function aktivitasPerJenis(array $s): array
    {
        $b = $this->db->table('crm_tugas_event e')
            ->select('e.jenis, COUNT(*) AS n')
            ->join('crm_tugas t', 't.id_tugas = e.tugas_id', 'left');

        if (! empty($s['dari'])) {
            $b->where('e.created_at >=', (int) $s['dari']);
        }

        if (! empty($s['sampai'])) {
            $b->where('e.created_at <=', (int) $s['sampai']);
        }

        if (! empty($s['user_id'])) {
            $b->where('e.user_id', (int) $s['user_id']);
        }

        $this->saringToko($b, 't.juragan_id', (array) ($s['juragan_ids'] ?? []));

        $peta = [];

        foreach ($b->groupBy('e.jenis')->get()->getResultArray() as $baris) {
            $peta[(string) $baris['jenis']] = (int) $baris['n'];
        }

        return $peta;
    }

    /**
     * Tugas yang dikerjakan seseorang pada periode itu, dikelompokkan per status.
     * Dipakai laporan supaya angka "selesai" tidak dihitung dari riwayat kejadian.
     *
     * @return array<string, int>
     */
    public function hasilKerja(array $juragan_ids, int $oleh, int $dari, int $sampai): array
    {
        $peta = array_fill_keys(['selesai', 'batal', 'terbuka'], 0);

        $b = $this->db->table('crm_tugas')->select('status, COUNT(*) AS n')
            ->where('dikerjakan_oleh', $oleh)
            ->groupBy('status');

        $this->saringToko($b, 'juragan_id', $juragan_ids);

        if ($dari > 0) {
            $b->where('updated_at >=', $dari);
        }

        if ($sampai > 0) {
            $b->where('updated_at <=', $sampai);
        }

        foreach ($b->get()->getResultArray() as $baris) {
            $status = (string) $baris['status'];

            $kunci = $status === 'completed' ? 'selesai' : ($status === 'cancelled' ? 'batal' : 'terbuka');
            $peta[$kunci] += (int) $baris['n'];
        }

        return $peta;
    }

    /** @param array<string, mixed> $s */
    private function aktivitasPangkal(array $s)
    {
        $b = $this->db->table('crm_tugas_event e')
            ->select('e.*, u.name AS nama_aktor, t.judul, t.jenis AS jenis_tugas, t.status AS status_sekarang, t.juragan_id, j.nama_juragan')
            ->join('user u', 'u.id = e.user_id', 'left')
            ->join('crm_tugas t', 't.id_tugas = e.tugas_id', 'left')
            ->join('juragan j', 'j.id_juragan = t.juragan_id', 'left');

        if (! empty($s['dari'])) {
            $b->where('e.created_at >=', (int) $s['dari']);
        }

        if (! empty($s['sampai'])) {
            $b->where('e.created_at <=', (int) $s['sampai']);
        }

        if (! empty($s['user_id'])) {
            $b->where('e.user_id', (int) $s['user_id']);
        }

        if (! empty($s['jenis'])) {
            $b->where('e.jenis', (string) $s['jenis']);
        }

        $this->saringToko($b, 't.juragan_id', (array) ($s['juragan_ids'] ?? []));

        return $b;
    }

    /**
     * Pembatas toko untuk query ber-alias. Tugas tanpa toko (pemeriksaan email
     * bersama, misalnya) sengaja selalu terlihat, sama seperti di daftar antrean.
     */
    private function saringToko($b, string $kolom, array $juragan_ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $juragan_ids)));

        if ($ids === []) {
            return;
        }

        $b->groupStart()->where($kolom . ' IS NULL')->orWhereIn($kolom, $ids)->groupEnd();
    }

    /**
     * Query dasar + join nama, dipakai daftar() dan jumlah() supaya filternya
     * tidak mungkin berbeda antara isi tabel dan angka pagination.
     *
     * @param array<string, mixed> $s
     */
    /** Jenis yang tidak punya submenu sendiri — isi daftar "Tugas lain-lain". */
    public static function jenisLain(): array
    {
        return array_diff_key(self::JENIS, array_flip(self::PUNYA_SUBMENU));
    }

    private function bangun(array $s)
    {
        $b = $this->pangkal();

        if (! empty($s['status'])) {
            $b->where('t.status', $s['status']);
        }

        if (! empty($s['jenis'])) {
            $b->where('t.jenis', $s['jenis']);
        }

        if (! empty($s['jenis_not'])) {
            $b->whereNotIn('t.jenis', (array) $s['jenis_not']);
        }

        if (! empty($s['jenis_in'])) {
            $b->whereIn('t.jenis', (array) $s['jenis_in']);
        }

        if (! empty($s['prioritas'])) {
            $b->where('t.prioritas', $s['prioritas']);
        }

        if (! empty($s['juragan_id'])) {
            $b->where('t.juragan_id', (int) $s['juragan_id']);
        }

        // cakupan sesi: tugas tanpa toko (mis. pemeriksaan email bersama) tetap
        // terlihat, tugas toko lain tidak
        if (! empty($s['juragan_ids'])) {
            $ids = array_values(array_unique(array_map('intval', $s['juragan_ids'])));

            $b->groupStart()
                ->where('t.juragan_id IS NULL')
                ->orWhereIn('t.juragan_id', $ids)
                ->groupEnd();
        }

        if (! empty($s['dikerjakan_oleh'])) {
            $b->where('t.dikerjakan_oleh', (int) $s['dikerjakan_oleh']);
        }

        if (! empty($s['dibuat_oleh'])) {
            $b->where('t.dibuat_oleh', (int) $s['dibuat_oleh']);
        }

        if (! empty($s['antrean'])) {
            $b->where('t.status', 'queued')->where('t.dikerjakan_oleh', null);
        }

        // satu kelompok status sekaligus, dipakai tab "Sudah Dikerjakan" yang menghitung
        // selesai dan batal sebagai sama-sama sudah ditangani
        if (! empty($s['status_in'])) {
            $b->whereIn('t.status', (array) $s['status_in']);
        }

        if (! empty($s['pemicu'])) {
            $this->saringPemicu($b, (array) $s['pemicu']);
        }

        if (! empty($s['pelanggan_id'])) {
            $b->where('t.pelanggan_id', (int) $s['pelanggan_id']);
        }

        if (! empty($s['nomor'])) {
            $b->like('kontak.semua_nomor', $s['nomor']);
        }

        if (! empty($s['terlambat'])) {
            $b->whereIn('t.status', self::BELUM_SELESAI)
                ->where('t.tenggat IS NOT NULL')
                ->where('t.tenggat <', date('Y-m-d'));
        }

        if (! empty($s['terbuka'])) {
            $b->whereIn('t.status', self::BELUM_SELESAI);
        }

        if (! empty($s['awal'])) {
            $b->where('t.tenggat >=', $s['awal']);
        }

        if (! empty($s['akhir'])) {
            $b->where('t.tenggat <=', $s['akhir']);
        }

        if (! empty($s['cari'])) {
            $b->groupStart()
                ->like('t.judul', $s['cari'])
                ->orLike('t.catatan', $s['cari'])
                ->orLike('p.nama_pelanggan', $s['cari'])
                ->groupEnd();
        }

        return $b;
    }

    /**
     * Batasi query ke satu/sekelompok pemicu reminder. Pemicu dikenali dari awalan
     * `kunci_unik`, jadi satu tugas tidak pernah lahir dari dua pemicu. Kunci yang
     * tidak dikenal diabaikan (bukan dipakai diam-diam sebagai filter kosong).
     *
     * @param list<string> $kunci
     */
    private function saringPemicu($b, array $kunci): void
    {
        $awalan = [];

        foreach ($kunci as $k) {
            if (isset(self::PEMICU[$k])) {
                $awalan = array_merge($awalan, array_values(self::PEMICU[$k]));
            }
        }

        if ($awalan === []) {
            return;
        }

        $b->groupStart();

        foreach ($awalan as $i => $awal) {
            if ($i === 0) {
                $b->like('t.kunci_unik', $awal, 'after');
            } else {
                $b->orLike('t.kunci_unik', $awal, 'after');
            }
        }

        $b->groupEnd();
    }

    /**
     * Jejak lepas tangan: satu baris per momen orang melepas tugas yang sudah ada
     * di tangannya, atau Admin membatalkannya. Yang dibaca adalah riwayat append-only
     * (`crm_tugas_event`), bukan kondisi tugas hari ini — satu nota bisa berpindah
     * tangan beberapa kali dan setiap alasannya harus tetap terbaca, sementara baris
     * `crm_tugas` hanya pernah menyimpan penggarap terakhir.
     *
     * @param array<string, mixed> $s
     * @return list<array<string, mixed>>
     */
    public function jejakLepas(array $s, int $limit = 25, int $offset = 0): array
    {
        // daftar kolom ditulis mentah (select false): kolomnya berkualifikasi alias
        // dan CI4 akan menempelkan DBPrefix ke alias itu kalau ikut di-escape
        return $this->bangunJejak($s)
            ->select('e.id_event, e.tugas_id, e.jenis, e.status_lama, e.status_baru, e.alasan,'
                . ' e.catatan AS catatan_lepas, e.created_at AS dilepas_at,'
                . ' t.judul, t.status, t.prioritas, t.tenggat, t.juragan_id, t.pelanggan_id, t.invoice_id,'
                . ' t.dibuat_oleh, t.dikerjakan_oleh, t.selesai_oleh, t.alasan_batal, t.kunci_unik,'
                . ' inv.seri AS invoice_seri, p.nama_pelanggan, j.nama_juragan,'
                . ' du.name AS nama_dibuat, su.name AS nama_selesai, ' . self::penglepas('t.dikerjakan_oleh', 'COALESCE(e.user_id, e.dari_user_id, t.dikerjakan_oleh)') . ' AS id_lepas, '
                . self::penglepas('ku.name', 'COALESCE(au.name, ad.name, ku.name)') . ' AS nama_lepas, '
                . self::penglepas('ku.level', 'COALESCE(au.level, ad.level, ku.level)') . ' AS level_lepas', false)
            ->orderBy('e.id_event', 'DESC')
            ->get($limit, $offset)
            ->getResultArray();
    }

    /** @param array<string, mixed> $s */
    public function jejakLepasJumlah(array $s): int
    {
        return (int) $this->bangunJejak($s)->countAllResults();
    }

    /**
     * Rekap per orang pada saringan yang sama, supaya Admin melihat polanya dulu
     * sebelum membaca satu-satu. Nama dibungkus MAX() karena hanya kolom yang
     * dikelompokkan yang boleh muncul telanjang di SELECT.
     *
     * @param array<string, mixed> $s
     * @return list<array<string, mixed>>
     */
    public function jejakLepasPerOrang(array $s): array
    {
        $siapa   = self::penglepas('t.dikerjakan_oleh', 'COALESCE(e.user_id, e.dari_user_id, t.dikerjakan_oleh)');
        $namanya = self::penglepas('ku.name', 'COALESCE(au.name, ad.name, ku.name)');

        return $this->bangunJejak($s)
            ->select($siapa . ' AS pemegang, MAX(' . $namanya . ') AS nama, COUNT(*) AS n', false)
            ->groupBy($siapa, false)
            ->orderBy('n', 'DESC')
            ->get(12)
            ->getResultArray();
    }

    /**
     * Ekspresi SQL "orang yang memegang tugas ini tapi tidak menyelesaikannya".
     * Penggarap pada baris tugas tidak bisa dipercaya: kolomnya dikosongkan saat
     * tugas dioper dan sudah berganti orang kalau tugasnya diambil lagi. Pelepasan
     * manual menulis user_id, operan otomatis hanya dari_user_id, dan pembatalan
     * meninggalkan penggarap lamanya di baris tugas — dibaca per jenis kejadian.
     *
     * Kedua cabang harus sejenis: cabang pembatalan menunjuk penggarap lama di
     * baris tugas, cabang operan menunjuk penulis kejadiannya. Kalau satu ekspresi
     * dipakai untuk kolom id, nama dan level sekaligus, kolom namanya ikut
     * mengembalikan angka id.
     *
     * @param string $saatBatal ekspresi penggarap lama, dipakai saat tugas dibatalkan
     * @param string $saatOper  ekspresi pelaku kejadian, dipakai saat tugas dioper
     */
    public static function penglepas(string $saatBatal, string $saatOper): string
    {
        return "CASE WHEN e.jenis = 'dibatalkan' AND t.dikerjakan_oleh IS NOT NULL"
            . " THEN {$saatBatal} ELSE {$saatOper} END";
    }

    /**
     * Dasar ketiga metode jejak di atas: riwayat pelepasan/pembatalan yang sudah
     * dibatasi ke pemicu layar ini dan ke orang yang benar-benar pernah memegangnya.
     *
     * `status_lama` ikut disaring karena ketiga kejadian itu bisa saja lahir dari
     * tugas yang masih di antrean (Admin membatalkan reminder yang belum diambil
     * siapa pun) — dan itu bukan cerita tab ini.
     *
     * @param array<string, mixed> $s
     */
    private function bangunJejak(array $s)
    {
        $b = $this->db->table('crm_tugas_event e')
            ->join('crm_tugas t', 't.id_tugas = e.tugas_id')
            ->join('pelanggan p', 'p.id_pelanggan = t.pelanggan_id', 'left')
            ->join('invoice inv', 'inv.id_invoice = t.invoice_id', 'left')
            ->join('juragan j', 'j.id_juragan = t.juragan_id', 'left')
            ->join('user du', 'du.id = t.dibuat_oleh', 'left')
            ->join('user ku', 'ku.id = t.dikerjakan_oleh', 'left')
            ->join('user su', 'su.id = t.selesai_oleh', 'left')
            // dua tabel user untuk pelaku: pelepasan manual menulis user_id,
            // operan otomatis hanya menulis dari_user_id (tidak ada yang klik apa-apa)
            ->join('user au', 'au.id = e.user_id', 'left')
            ->join('user ad', 'ad.id = e.dari_user_id', 'left')
            ->whereIn('e.jenis', self::JEJAK_LEPAS)
            ->whereIn('e.status_lama', ['assigned', 'in_progress', 'waiting']);

        $this->saringPemicu($b, (array) ($s['pemicu'] ?? []));

        if (! empty($s['juragan_id'])) {
            $b->where('t.juragan_id', (int) $s['juragan_id']);
        }

        $this->saringToko($b, 't.juragan_id', (array) ($s['juragan_ids'] ?? []));

        // "hanya punyaku" di tab ini berarti akulah yang menulis alasannya
        if (! empty($s['milik_saya'])) {
            $b->groupStart()->where('e.user_id', (int) $s['milik_saya'])
                ->orWhere('e.dari_user_id', (int) $s['milik_saya'])->groupEnd();
        }

        if (! empty($s['cari'])) {
            $cari = (string) $s['cari'];
            $b->groupStart()->like('t.judul', $cari)->orLike('e.alasan', $cari)
                ->orLike('e.catatan', $cari)->orLike('inv.seri', $cari)
                ->orLike('p.nama_pelanggan', $cari)->groupEnd();
        }

        if (! empty($s['dari'])) {
            $b->where('e.created_at >=', (int) strtotime((string) $s['dari'] . ' 00:00:00'));
        }

        if (! empty($s['sampai'])) {
            $b->where('e.created_at <=', (int) strtotime((string) $s['sampai'] . ' 23:59:59'));
        }

        return $b;
    }

    private function pangkal()
    {
        $kontak = $this->db->prefixTable('pelanggan_kontak');

        return $this->db->table('crm_tugas t')
            ->select('t.*, p.nama_pelanggan, kontak.nomor AS nomor_wa, inv.seri AS invoice_seri, j.nama_juragan,'
                . ' du.name AS nama_dibuat, ku.name AS nama_dikerjakan, su.name AS nama_selesai')
            ->join('pelanggan p', 'p.id_pelanggan = t.pelanggan_id', 'left')
            // satu nomor per pelanggan saja yang ikut tampil; pencarian tetap
            // mencocokkan SEMUA nomor lewat subquery di bawah
            ->join('(SELECT pelanggan_id, MIN(nomor) AS nomor, GROUP_CONCAT(nomor) AS semua_nomor FROM `' . $kontak . '` GROUP BY pelanggan_id) kontak',
                'kontak.pelanggan_id = t.pelanggan_id', 'left')
            ->join('invoice inv', 'inv.id_invoice = t.invoice_id', 'left')
            ->join('juragan j', 'j.id_juragan = t.juragan_id', 'left')
            ->join('user du', 'du.id = t.dibuat_oleh', 'left')
            ->join('user ku', 'ku.id = t.dikerjakan_oleh', 'left')
            ->join('user su', 'su.id = t.selesai_oleh', 'left');
    }

    // ------------------------------------------------------------------
    // tulis
    // ------------------------------------------------------------------

    /**
     * Buat tugas baru. Selalu lahir di antrean; penugasan adalah langkah terpisah
     * supaya "siapa yang pegang" selalu punya jejak waktunya sendiri.
     *
     * @param array<string, mixed> $data
     */
    public function buat(array $data, ?int $user): int
    {
        $waktu = time();

        $payload = [
            'judul'        => mb_substr(trim((string) $data['judul']), 0, 200),
            'deskripsi'    => $this->isiAtauNull($data['deskripsi'] ?? null),
            'jenis'        => $this->pilih($data['jenis'] ?? 'lain', array_keys(self::JENIS), 'lain'),
            'status'       => 'queued',
            'prioritas'    => $this->pilih($data['prioritas'] ?? 'normal', array_keys(self::PRIORITAS), 'normal'),
            'sumber'       => ($data['sumber'] ?? 'manual') === 'sistem' ? 'sistem' : 'manual',
            'kunci_unik'   => $this->isiAtauNull($data['kunci_unik'] ?? null),
            'pelanggan_id' => $this->idAtauNull($data['pelanggan_id'] ?? null),
            'invoice_id'   => $this->idAtauNull($data['invoice_id'] ?? null),
            'juragan_id'   => $this->idAtauNull($data['juragan_id'] ?? null),
            'dibuat_oleh'  => $user,
            'tenggat'      => $this->tanggalAtauNull($data['tenggat'] ?? null),
            'catatan'      => $this->isiAtauNull($data['catatan'] ?? null),
            'created_at'   => $waktu,
            'updated_at'   => $waktu,
        ];

        $this->db->table('crm_tugas')->insert($payload);
        $id = (int) $this->db->insertID();

        $this->catat([
            'tugas_id'    => $id,
            'jenis'       => 'dibuat',
            'user'        => $user,
            'status_baru' => 'queued',
            'catatan'     => $payload['deskripsi'],
        ]);

        return $id;
    }

    /**
     * Buat tugas hasil pemicu sistem (permintaan ulasan) tanpa duplikat.
     *
     * Yang menjaga adalah kolom kunci_unik ber-index unik, bukan PHP yang
     * mengingat-ingat: pemicu nota yang sama dijalankan dua kali tetap satu tugas.
     *
     * @param array<string, mixed> $data
     *
     * @return array{id: int, baru: bool}
     */
    public function buatUnik(array $data, ?int $user): array
    {
        $kunci = (string) ($data['kunci_unik'] ?? '');

        if ($kunci !== '') {
            $ada = $this->db->table('crm_tugas')->where('kunci_unik', $kunci)->get(1)->getRowArray();

            if ($ada) {
                return ['id' => (int) $ada['id_tugas'], 'baru' => false];
            }
        }

        return ['id' => $this->buat($data, $user), 'baru' => true];
    }

    /**
     * CS mengambil tugas dari antrean.
     *
     * Perebutan diselesaikan di database: UPDATE diberi syarat status masih
     * 'queued' dan belum ada penggarap. Nol baris berubah berarti tugasnya baru
     * saja diambil orang lain, jadi tidak ada dua CS yang memegang satu tugas.
     *
     * @return array{ok: bool, pesan: string}
     */
    public function ambil(int $id_tugas, int $user, array $juragan_ids = []): array
    {
        $tugas = $this->satu($id_tugas);

        if (! $tugas) {
            return ['ok' => false, 'pesan' => 'Tugas tidak ditemukan.'];
        }

        $ids = array_map('intval', $juragan_ids);

        if ($ids !== [] && $tugas['juragan_id'] !== null && ! in_array((int) $tugas['juragan_id'], $ids, true)) {
            return ['ok' => false, 'pesan' => 'Tugas ini di luar toko yang kamu pegang.'];
        }

        if ($tugas['status'] !== 'queued' || $tugas['dikerjakan_oleh'] !== null) {
            return ['ok' => false, 'pesan' => 'Tugas ini sudah diambil atau bukan lagi di antrean.'];
        }

        $waktu = time();

        $ubah = $this->db->table('crm_tugas')
            ->where('id_tugas', $id_tugas)
            ->where('status', 'queued')
            ->where('dikerjakan_oleh', null)
            ->update([
                'status'          => 'assigned',
                'dikerjakan_oleh' => $user,
                'dikerjakan_at'   => $waktu,
                'cara_penugasan'  => 'ambil',
                'updated_at'      => $waktu,
            ]);

        if ($ubah === 0) {
            return ['ok' => false, 'pesan' => 'Tugas baru saja diambil CS lain. Muat ulang daftar ya.'];
        }

        $this->catat([
            'tugas_id'    => $id_tugas,
            'jenis'       => 'diambil',
            'user'        => $user,
            'status_lama' => 'queued',
            'status_baru' => 'assigned',
            'ke_user'     => $user,
        ]);

        return ['ok' => true, 'pesan' => 'Tugas menjadi tanggung jawabmu.'];
    }

    /** @return array{ok: bool, pesan: string} */
    public function tetapkan(int $id_tugas, int $ke_user, int $oleh, string $alasan = ''): array
    {
        $tugas = $this->satu($id_tugas);

        if (! $tugas) {
            return ['ok' => false, 'pesan' => 'Tugas tidak ditemukan.'];
        }

        if (in_array($tugas['status'], ['completed', 'cancelled'], true)) {
            return ['ok' => false, 'pesan' => 'Tugas yang sudah selesai atau dibatalkan tidak bisa ditetapkan ulang.'];
        }

        $lama  = $tugas['dikerjakan_oleh'] !== null ? (int) $tugas['dikerjakan_oleh'] : null;
        $waktu = time();

        $this->db->table('crm_tugas')
            ->where('id_tugas', $id_tugas)
            ->update([
                'status'          => 'assigned',
                'dikerjakan_oleh' => $ke_user,
                'dikerjakan_at'   => $waktu,
                'cara_penugasan'  => 'ditetapkan',
                'updated_at'      => $waktu,
            ]);

        // pemindahan penggarap wajib tercatat: tanpanya laporan per-CS salah hitung
        $this->catat([
            'tugas_id'    => $id_tugas,
            'jenis'       => $lama === null ? 'ditetapkan' : 'dipindahkan',
            'user'        => $oleh,
            'status_lama' => $tugas['status'],
            'status_baru' => 'assigned',
            'catatan'     => $alasan !== '' ? $alasan : null,
            'ke_user'     => $ke_user,
            'dari_user'   => $lama,
        ]);

        return ['ok' => true, 'pesan' => 'Tugas ditetapkan.'];
    }

    /**
     * Pindahkan tugas kembali ke antrean atas kesadaran penggarapnya (operan manual).
     *
     * @return array{ok: bool, pesan: string}
     */
    public function kembalikan(int $id_tugas, int $user, string $alasan, string $catatan = ''): array
    {
        $tugas = $this->satu($id_tugas);

        if (! $tugas) {
            return ['ok' => false, 'pesan' => 'Tugas tidak ditemukan.'];
        }

        if ((int) $tugas['dikerjakan_oleh'] !== $user) {
            return ['ok' => false, 'pesan' => 'Hanya penggarap saat ini yang bisa mengoperkan tugas ini.'];
        }

        if (in_array($tugas['status'], ['completed', 'cancelled'], true)) {
            return ['ok' => false, 'pesan' => 'Tugas yang sudah selesai atau dibatalkan tidak dioperkan.'];
        }

        if (trim($alasan) === '') {
            return ['ok' => false, 'pesan' => 'Alasan operan wajib diisi supaya CS berikutnya paham.'];
        }

        $lama    = (string) $tugas['status'];
        $waktu   = time();
        $bergabung = trim($catatan) === ''
            ? $this->isiAtauNull($tugas['catatan'])
            : trim((string) $tugas['catatan']) . "\n---\nOperan: " . trim($catatan);

        $this->db->table('crm_tugas')
            ->where('id_tugas', $id_tugas)
            ->update([
                'status'          => 'queued',
                'dikerjakan_oleh' => null,
                'dikerjakan_at'   => null,
                'cara_penugasan'  => 'operan',
                'catatan'         => $bergabung,
                'updated_at'      => $waktu,
            ]);

        $this->catat([
            'tugas_id'    => $id_tugas,
            'jenis'       => 'operan_manual',
            'user'        => $user,
            'status_lama' => $lama,
            'status_baru' => 'queued',
            'catatan'     => trim($catatan) === '' ? null : $catatan,
            'dari_user'   => $user,
            'alasan'      => $alasan,
        ]);

        return ['ok' => true, 'pesan' => 'Tugas dikembalikan ke antrean.'];
    }

    /**
     * Ubah status dengan transisi yang divalidasi di backend, bukan cuma tombol
     * yang disembunyikan di frontend.
     *
     * 'waiting' tanpa jadwal tindak lanjut dilarang: tugas yang menunggu tanpa
     * tanggal adalah cara paling mudah untuk lupa.
     *
     * @return array{ok: bool, pesan: string}
     */
    public function ubahStatus(int $id_tugas, string $baru, int $user, string $catatan = '', ?int $susul = null, string $alasan = ''): array
    {
        $tugas = $this->satu($id_tugas);

        if (! $tugas) {
            return ['ok' => false, 'pesan' => 'Tugas tidak ditemukan.'];
        }

        $lama = (string) $tugas['status'];

        if (! isset(self::TRANSISI[$lama]) || ! in_array($baru, self::TRANSISI[$lama], true)) {
            return ['ok' => false, 'pesan' => 'Perubahan ' . $lama . ' ke ' . $baru . ' tidak diizinkan.'];
        }

        if ($baru === 'cancelled' && trim($alasan) === '') {
            return ['ok' => false, 'pesan' => 'Pembatalan harus disertai alasan.'];
        }

        if ($baru === 'waiting' && $susul === null) {
            return ['ok' => false, 'pesan' => 'Tugas yang menunggu respons harus punya jadwal tindak lanjut.'];
        }

        $waktu = time();

        $ubah = ['status' => $baru, 'updated_at' => $waktu];

        if ($baru === 'completed') {
            $ubah['selesai_oleh'] = $user;
            $ubah['selesai_at']   = $waktu;
        }

        if ($baru === 'cancelled') {
            $ubah['alasan_batal'] = mb_substr(trim($alasan), 0, 255);
        }

        if ($baru === 'queued') {
            // invariant antrean: queued selalu tanpa penggarap, termasuk saat
            // tugas selesai/dibatalkan dibuka kembali
            $ubah['dikerjakan_oleh'] = null;
            $ubah['dikerjakan_at']   = null;
            $ubah['cara_penugasan']  = 'operan';
        }

        if ($susul !== null) {
            $ubah['susul_at'] = $susul;
        }

        if (trim($catatan) !== '') {
            $ubah['catatan'] = trim((string) $tugas['catatan']) . "\n---\n" . trim($catatan);
        }

        $this->db->table('crm_tugas')->where('id_tugas', $id_tugas)->update($ubah);

        $this->catat([
            'tugas_id'    => $id_tugas,
            'jenis'       => $baru === 'completed' ? 'selesai' : ($baru === 'cancelled' ? 'dibatalkan' : 'status'),
            'user'        => $user,
            'status_lama' => $lama,
            'status_baru' => $baru,
            'catatan'     => trim($catatan) === '' ? null : $catatan,
            'alasan'      => trim($alasan) === '' ? null : $alasan,
        ]);

        return ['ok' => true, 'pesan' => 'Status diperbarui.'];
    }

    /**
     * Operan otomatis ketika jam shift seorang CS sudah lewat.
     *
     * Yang diserahkan kembali hanyalah 'assigned' dan 'in_progress': yang belum
     * dimulai pun harus dilepas supaya CS berikutnya bisa mengambil, sedangkan
     * 'waiting' ditinggal — tugas itu sudah punya jadwal tindak lanjut dan
     * memindahkannya di tengah menunggu pelanggan cuma memutus jejak.
     *
     * Idempoten lewat kunci riwayat: satu tugas hanya pernah dioperkan untuk satu
     * tanggal shift, jadi sweep yang dijalankan ulang (halaman + cron) tidak
     * menumpuk riwayat. Konsekuensinya disengaja: CS yang kembali mengambil tugas
     * yang sama pada hari itu juga tidak dioperkan dua kali.
     */
    public function operanShift(?int $sampai = null): int
    {
        $sekarang = $sampai ?? time();
        $pindah   = 0;

        foreach ((new ShiftModel())->sudahBerakhir($sekarang) as $s) {
            $b = $this->db->table('crm_tugas')
                ->where('dikerjakan_oleh', (int) $s['user_id'])
                ->whereIn('status', ['assigned', 'in_progress']);

            if ($s['juragan_id'] !== null) {
                $b->where('juragan_id', (int) $s['juragan_id']);
            }

            foreach ($b->get()->getResultArray() as $t) {
                $kunci = 'operan-otomatis-' . (int) $t['id_tugas'] . '-' . $s['tanggal'];

                if ($this->sudahTercatat($kunci)) {
                    continue;
                }

                // syarat diulang saat UPDATE supaya sesi lain yang baru saja
                // mengubah penggarap tidak ditimpa
                $ubah = $this->db->table('crm_tugas')
                    ->where('id_tugas', (int) $t['id_tugas'])
                    ->where('dikerjakan_oleh', (int) $s['user_id'])
                    ->whereIn('status', ['assigned', 'in_progress'])
                    ->update([
                        'status'          => 'queued',
                        'dikerjakan_oleh' => null,
                        'dikerjakan_at'   => null,
                        'cara_penugasan'  => 'operan',
                        'updated_at'      => $sekarang,
                    ]);

                if ($ubah === 0) {
                    continue;
                }

                $this->catat([
                    'tugas_id'    => (int) $t['id_tugas'],
                    'jenis'       => 'operan_otomatis',
                    'user'        => null,
                    'status_lama' => $t['status'],
                    'status_baru' => 'queued',
                    'dari_user'   => (int) $s['user_id'],
                    'alasan'      => 'shift ' . $s['nama_staf'] . ' berakhir ' . $s['tanggal'] . ' ' . $s['selesai'],
                    'kunci'       => $kunci,
                ]);

                $pindah++;
            }
        }

        $setelan = new PengaturanModel();
        $setelan->tulis(self::S_OPERAN_AKHIR, (string) $sekarang);
        $setelan->tulis(self::S_OPERAN_JUMLAH, (string) $pindah);

        return $pindah;
    }

    /**
     * Versi yang boleh dipanggil dari halaman dan dari endpoint badge: jalankan
     * paling banyak satu kali per JEDA_OPERAN detik, pakai cache sebagai remnya.
     * Polling tiap 5 detik tidak boleh berujung sapuan tiap 5 detik.
     */
    public function operanShiftKalauWaktu(): int
    {
        $cache = service('cache');

        if ($cache->get('crm_operan_jeda') !== null) {
            return 0;
        }

        $pindah = $this->operanShift();
        $cache->save('crm_operan_jeda', $pindah, self::JEDA_OPERAN);

        return $pindah;
    }

    /**
     * Kapan operan otomatis terakhir jalan dan berapa tugas yang diserahkan,
     * untuk ditampilkan jujur di layar Jam shift (0 pun dicatat).
     *
     * @return array{waktu: ?int, jumlah: int}
     */
    public function operanTerakhir(): array
    {
        $nilai = (new PengaturanModel())->banyak([self::S_OPERAN_AKHIR, self::S_OPERAN_JUMLAH]);

        return [
            'waktu'  => isset($nilai[self::S_OPERAN_AKHIR]) ? (int) $nilai[self::S_OPERAN_AKHIR] : null,
            'jumlah' => (int) ($nilai[self::S_OPERAN_JUMLAH] ?? 0),
        ];
    }

    /**
     * Aturan reminder ulasan: kunci di crm_setting, label di layar, dan nilai
     * bawaan yang dipakai kalau kunci itu belum pernah diisi. Kunci lama
     * reminder_dp_* dan reminder_pelunasan_* tidak lagi dibaca di sini.
     */
    public const ATURAN_REMINDER = [
        'reminder_ulasan_aktif'    => ['label' => 'Nyalakan permintaan ulasan', 'bawaan' => 1, 'satuan' => 'nyala'],
        'reminder_ulasan_hari'     => ['label' => 'Hari sejak dananya masuk (DP/cicilan/lunas) sebelum meminta ulasan', 'bawaan' => 0, 'satuan' => 'hari'],
    ];

    /** Jendela mundur generator: nota yang tidak disentuh selama ini dilewati. */
    public const JENDELA_REMINDER = 30;

    /**
     * @return array<string, int>
     */
    public function aturanReminder(): array
    {
        $kunci  = array_keys(self::ATURAN_REMINDER);
        $isi    = (new PengaturanModel())->banyak($kunci);
        $aturan = [];

        foreach (self::ATURAN_REMINDER as $kunci_aturan => $meta) {
            $nilai         = $isi[$kunci_aturan] ?? null;
            $aturan[$kunci_aturan] = $nilai === null || $nilai === '' ? (int) $meta['bawaan'] : (int) $nilai;
        }

        // angka negatif tidak pernah berarti apa pun di aturan ini
        foreach (['reminder_ulasan_hari'] as $k) {
            $aturan[$k] = max(0, min(90, $aturan[$k]));
        }

        return $aturan;
    }

    /** @param array<string, mixed> $kirim */
    public function simpanAturanReminder(array $kirim): void
    {
        $setelan = new PengaturanModel();

        foreach (self::ATURAN_REMINDER as $kunci => $meta) {
            if (! array_key_exists($kunci, $kirim)) {
                continue;
            }

            $nilai = $meta['satuan'] === 'nyala'
                ? (in_array((string) $kirim[$kunci], ['1', 'on', 'ya'], true) ? 1 : 0)
                : max(0, min(90, (int) $kirim[$kunci]));

            $setelan->tulis($kunci, (string) $nilai);
        }
    }

    /**
     * Sinkronkan satu nota dengan antrean tugas: membuat reminder yang jatuh tempo
     * dan menutup reminder yang pemicunya sudah tidak berlaku.
     *
     * Fungsi yang sama dipakai oleh hook pembayaran, sapuan terjadwal, dan perintah
     * CLI — jadi aturannya cuma ada satu versi. Menjalankannya dua kali pada nota
     * yang sama tidak pernah menghasilkan tugas kedua (kunci_unik).
     *
     * @return array{dibuat: int, ditutup: int}
     */
    public function sinkronReminder(int $invoice_id, ?int $sekarang = null): array
    {
        $sekarang = $sekarang ?? time();
        $aturan   = $this->aturanReminder();
        $nota     = $this->db->table('invoice')->where('id_invoice', $invoice_id)->get(1)->getRowArray();

        if ($nota === null) {
            return ['dibuat' => 0, 'ditutup' => 0];
        }

        // pemicunya mati: nota dibatalkan atau dihapus, jadi permintaan ulasan yang
        // masih terbuka ditutup supaya tidak tinggal di antrean selamanya
        if ((string) $nota['status_pesanan'] === '3' || $nota['deleted_at'] !== null) {
            return ['dibuat' => 0, 'ditutup' => $this->tutupUlasan($invoice_id)];
        }

        // permintaan ulasan: cukup dananya sudah masuk — DP (3), sedang dicicil (4),
        // atau lunas (5/6). Tidak menunggu notasnya selesai dikirim: begitu ada
        // pembayaran sah, CS boleh mulai menanyakan pengalamannya. Hook
        // perbaruiStatusPembayaran() memanggil sinkron ini, jadi remindernya
        // lahir pada detik yang sama saat uangnya dicatat. Menagih sisa bayar bukan
        // pekerjaan layar ini — itu CRM Follow-Up.
        return [
            'dibuat'  => $this->ulasanReminder($nota, $sekarang, $sekarang - self::JENDELA_REMINDER * 86400, $aturan),
            'ditutup' => 0,
        ];
    }

    /**
     * Tutup permintaan ulasan yang masih terbuka saat notanya dibatalkan/dihapus.
     *
     * @return int 1 kalau ada tugas yang ditutup
     */
    private function tutupUlasan(int $invoice_id): int
    {
        $lama = $this->db->table('crm_tugas')->where('kunci_unik', 'ulasan-' . $invoice_id)->get(1)->getRowArray();

        if ($lama === null || ! in_array((string) $lama['status'], self::BELUM_SELESAI, true)) {
            return 0;
        }

        $this->tutupOtomatis((int) $lama['id_tugas'], 'nota dibatalkan atau dihapus');

        return 1;
    }

    /**
     * Tugas "Minta ulasan" untuk satu nota. Aturan yang sama dipakai sapuan rutin
     * (sinkronReminder, $umur_maks = sekarang - JENDELA_REMINDER hari) dan backfill
     * nota lama (backfillUlasan, $umur_maks = 0: batas umur dilepas).
     *
     * @param array<string, mixed> $nota      baris invoice: id_invoice, seri,
     *                                        pemesan_id, juragan_id,
     *                                        status_pembayaran, update_at
     * @param int                  $umur_maks update_at paling tua yang boleh
     *                                        diproses; 0 = tanpa batas
     * @param array<string, int>   $aturan    aturan reminder yang sudah dibaca
     *
     * @return int 1 kalau tugas baru lahir, selain itu 0
     */
    private function ulasanReminder(array $nota, int $sekarang, int $umur_maks, array $aturan): int
    {
        if ($aturan['reminder_ulasan_aktif'] !== 1) {
            return 0;
        }

        if (! in_array((string) ($nota['status_pembayaran'] ?? ''), InvoiceModel::DANA_MASUK, true)) {
            return 0;
        }

        $id     = (int) ($nota['id_invoice'] ?? 0);
        $update = (int) ($nota['update_at'] ?? 0);

        // jeda tunggu belum lewat: belum waktunya menanyakan pengalaman pelanggan
        if ($id === 0 || $update > $sekarang - $aturan['reminder_ulasan_hari'] * 86400) {
            return 0;
        }

        if ($umur_maks > 0 && $update < $umur_maks) {
            return 0;
        }

        $hasil = $this->buatUnik([
            'judul'        => 'Minta ulasan nota ' . (string) ($nota['seri'] ?? ''),
            'deskripsi'    => 'Tanyakan pengalaman pelanggan secara netral. Jangan meminta bintang tertentu.',
            'jenis'        => 'reminder_transaksi',
            'prioritas'    => 'rendah',
            'sumber'       => 'sistem',
            'kunci_unik'   => 'ulasan-' . $id,
            'invoice_id'   => $id,
            'pelanggan_id' => (int) ($nota['pemesan_id'] ?? 0),
            'juragan_id'   => (int) ($nota['juragan_id'] ?? 0),
            'tenggat'      => date('Y-m-d', $sekarang + 86400),
        ], null);

        // baris ulasan ditulis walau tugasnya sudah ada: status 'diminta' yang
        // terbaru tetap tercatat, dan 'diterima' tidak pernah ditimpa
        (new UlasanModel())->minta($id, (int) $hasil['id'], null);

        return (int) $hasil['baru'];
    }

    /**
     * Backfill permintaan ulasan untuk nota LAMA. Aturannya identik dengan sapuan
     * rutin — dananya sudah masuk dan jeda reminder_ulasan_hari lewat — yang
     * dilepas hanya batas umurnya.
     *
     * Berdiri sendiri dan dipanggil dari CLI (php spark crm:ulasan-lama): kalau
     * diboncengkan ke sapuan halaman, satu klik bisa melahirkan ratusan tugas dan
     * membuat badge antrean melonjak tanpa diminta. Tugas tidak pernah dibuat dua
     * kali karena kunci_unik 'ulasan-<id>' dijaga buatUnik().
     *
     * @param array<int>|null $juragan_ids batasi ke toko ini; null = semua toko
     *
     * @return array{dibuat: int, nota: int, dilewati: int}
     */
    public function backfillUlasan(?array $juragan_ids = null, int $maks = 500): array
    {
        $sekarang = time();
        $aturan   = $this->aturanReminder();

        $b = $this->db->table('invoice')
            ->select('id_invoice, seri, pemesan_id, juragan_id, status_pembayaran, update_at')
            ->where('deleted_at', null)
            ->where("status_pesanan <> '3'")
            ->whereIn('status_pembayaran', InvoiceModel::DANA_MASUK)
            ->orderBy('update_at', 'DESC');

        if ($juragan_ids !== null && $juragan_ids !== []) {
            $b->whereIn('juragan_id', array_map('intval', $juragan_ids));
        }

        $hasil = ['dibuat' => 0, 'nota' => 0, 'dilewati' => 0];

        foreach ($b->get(max(1, $maks))->getResultArray() as $nota) {
            $hasil['nota']++;

            // dilewati = tugasnya sudah ada, atau jeda tunggunya belum lewat
            if ($this->ulasanReminder($nota, $sekarang, 0, $aturan) === 1) {
                $hasil['dibuat']++;
            } else {
                $hasil['dilewati']++;
            }
        }

        return $hasil;
    }

    /**
     * Batalkan tugas hasil kerja sistem dengan alasan tercatat. Dipakai saat pemicu
     * reminder hilang (nota lunas, dibatalkan, atau DP sudah dikonfirmasi).
     */
    public function tutupOtomatis(int $id_tugas, string $alasan): bool
    {
        $lama = $this->db->table('crm_tugas')->where('id_tugas', $id_tugas)->get(1)->getRowArray();

        if ($lama === null || ! in_array((string) $lama['status'], self::BELUM_SELESAI, true)) {
            return false;
        }

        $ubah = $this->db->table('crm_tugas')
            ->where('id_tugas', $id_tugas)
            ->whereIn('status', self::BELUM_SELESAI)
            ->update([
                'status'       => 'cancelled',
                'alasan_batal' => mb_substr($alasan, 0, 255),
                'updated_at'   => time(),
            ]);

        if ($ubah === 0) {
            return false;
        }

        $this->catat([
            'tugas_id'    => $id_tugas,
            'jenis'       => 'dibatalkan',
            'user'        => null,
            'status_lama' => (string) $lama['status'],
            'status_baru' => 'cancelled',
            'alasan'      => 'pemicu reminder berakhir: ' . $alasan,
            'kunci'       => 'tutup-' . $id_tugas . '-' . md5($alasan),
        ]);

        return true;
    }

    /**
     * Berapa nota yang sedang masuk ke tiap aturan reminder. Dipakai layar aturan
     * supaya Admin melihat akibat angkanya sebelum menyimpan.
     *
     * @return array{ulasan: int}
     */
    public function generateHitung(): array
    {
        $aturan = $this->aturanReminder();
        $potong = time() - self::JENDELA_REMINDER * 86400;

        $ulasan = $this->db->table('invoice')->where('deleted_at', null)
            ->where('status_pesanan <>', "'3'")
            ->whereIn('status_pembayaran', InvoiceModel::DANA_MASUK)
            ->where('update_at >=', $potong)
            ->where('update_at <=', time() - $aturan['reminder_ulasan_hari'] * 86400);

        return [
            'ulasan' => $aturan['reminder_ulasan_aktif'] ? (int) $ulasan->countAllResults() : 0,
        ];
    }

    /**
     * Jalankan sinkronisasi untuk semua nota yang mungkin sedang memicu reminder.
     *
     * Kandidatnya adalah nota yang dananya sudah masuk (DP, dicicil, atau lunas)
     * DAN yang terakhir disentuh dalam JENDELA_REMINDER hari. Jendela itu penting:
     * tanpanya satu kali sapuan melahirkan ratusan tugas "minta ulasan" dari nota
     * bertahun-tahun lalu.
     *
     * @return array{dibuat: int, ditutup: int, nota: int}
     */
    public function generateReminder(?array $juragan_ids = null, ?int $sekarang = null): array
    {
        $sekarang = $sekarang ?? time();

        $b = $this->db->table('invoice')
            ->select('id_invoice')
            ->where('deleted_at', null)
            ->where("status_pesanan <> '3'")
            ->where('update_at >=', $sekarang - self::JENDELA_REMINDER * 86400)
            ->whereIn('status_pembayaran', InvoiceModel::DANA_MASUK);

        if ($juragan_ids !== null && $juragan_ids !== []) {
            $b->whereIn('juragan_id', array_map('intval', $juragan_ids));
        }

        $hasil = ['dibuat' => 0, 'ditutup' => 0, 'nota' => 0];

        foreach ($b->get(600)->getResultArray() as $n) {
            $sinkron = $this->sinkronReminder((int) $n['id_invoice'], $sekarang);
            $hasil['dibuat']   += $sinkron['dibuat'];
            $hasil['ditutup']  += $sinkron['ditutup'];
            $hasil['nota']++;
        }

        return $hasil;
    }

    /**
     * Versi berjeda untuk halaman dan badge: paling banyak satu kali per 5 menit.
     *
     * Layar hanya menyapu toko milik akun yang sedang login; sapuan seluruh toko
     * adalah pekerjaan cron (php spark crm:reminder), bukan efek samping membuka
     * sebuah halaman.
     *
     * @return array{dibuat: int, ditutup: int, nota: int}
     */
    public function reminderKalauWaktu(?array $juragan_ids = null): array
    {
        $cache = service('cache');

        if ($cache->get('crm_reminder_jeda') !== null) {
            return ['dibuat' => 0, 'ditutup' => 0, 'nota' => 0];
        }

        $hasil = $this->generateReminder($juragan_ids);
        $cache->save('crm_reminder_jeda', $hasil['dibuat'], 300);

        return $hasil;
    }

    /**
     * Tulis satu baris riwayat. Append-only: tidak ada jalur update/hapus di model
     * ini, jadi penanggung jawab sebelumnya tidak pernah hilang.
     *
     * Kunci idempoten dipakai operan otomatis: pemrosesan ulang pada tugas yang
     * sama tidak menghasilkan riwayat ganda.
     *
     * @param array<string, mixed> $d
     */
    public function catat(array $d): int
    {
        $kunci = isset($d['kunci']) ? (string) $d['kunci'] : null;

        if ($kunci !== null && $this->sudahTercatat($kunci)) {
            return 0;
        }

        $this->db->table('crm_tugas_event')->insert([
            'tugas_id'        => (int) $d['tugas_id'],
            'jenis'           => mb_substr((string) $d['jenis'], 0, 30),
            'dari_user_id'    => isset($d['dari_user']) ? (int) $d['dari_user'] : null,
            'ke_user_id'      => isset($d['ke_user']) ? (int) $d['ke_user'] : null,
            'status_lama'     => isset($d['status_lama']) ? (string) $d['status_lama'] : null,
            'status_baru'     => isset($d['status_baru']) ? (string) $d['status_baru'] : null,
            'catatan'         => isset($d['catatan']) ? (string) $d['catatan'] : null,
            'alasan'          => isset($d['alasan']) ? mb_substr((string) $d['alasan'], 0, 255) : null,
            'kunci_idempoten' => $kunci,
            'user_id'         => isset($d['user']) ? (int) $d['user'] : null,
            'created_at'      => time(),
        ]);

        return (int) $this->db->insertID();
    }

    public function sudahTercatat(string $kunci): bool
    {
        return (int) $this->db->table('crm_tugas_event')
            ->where('kunci_idempoten', $kunci)
            ->countAllResults() > 0;
    }

    /**
     * Filter toko untuk hitungTerbuka/ringkas. Daftar kosong berarti pemanggil
     * boleh melihat semua toko (superadmin).
     *
     * @param \CodeIgniter\Database\BaseBuilder $b
     * @param list<int>                         $juragan_ids
     */
    private function terapkanToko($b, array $juragan_ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $juragan_ids)));

        if ($ids === []) {
            return;
        }

        $b->groupStart()->where('juragan_id IS NULL')->orWhereIn('juragan_id', $ids)->groupEnd();
    }

    /** Pilihan harus salah satu yang dikenal; nilai apa pun dari request jadi default. */
    private function pilih(mixed $nilai, array $boleh, string $default): string
    {
        $bersih = (string) $nilai;

        return in_array($bersih, $boleh, true) ? $bersih : $default;
    }

    private function idAtauNull(mixed $nilai): ?int
    {
        $angka = (int) $nilai;

        return $angka > 0 ? $angka : null;
    }

    private function isiAtauNull(mixed $nilai): ?string
    {
        $bersih = trim((string) ($nilai ?? ''));

        return $bersih === '' ? null : $bersih;
    }

    private function tanggalAtauNull(mixed $nilai): ?string
    {
        $bersih = trim((string) ($nilai ?? ''));

        if ($bersih === '' || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $bersih, $m)) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $bersih : null;
    }
}
