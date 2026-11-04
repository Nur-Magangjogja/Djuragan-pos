<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Perhitungan KPI Customer Service.
 *
 * Semua agregasi dikerjakan MySQL; yang kembali ke controller hanya baris per
 * pegawai (puluhan baris) atau baris detail yang sudah dipaging, bukan transaksi
 * utuh, supaya halaman tetap ringan walau nota sudah puluhan ribu.
 *
 * Halaman ini dulu juga menampung indikator kerja admin (SLA fulfillment, return
 * error rate, kecepatan master data, error/void rate). Semuanya sudah ditarik:
 * KPI dipakai menilai produktivitas CS, dan tiga dari empat indikator itu tidak
 * pernah punya jalur pencatatan sehingga selamanya tampil kosong.
 *
 * Aturan yang dipakai bersama, dan alasannya:
 *
 * - Nota ikut dinilai bila tidak dihapus, tidak dibatalkan, dan termasuk toko
 *   yang boleh dibuka akun yang login. Periode dibaca dari tanggal_pesan, bukan
 *   waktu terakhir diubah, supaya nota tidak pindah periode hanya karena disunting.
 * - Nilai transaksi = barang (order_dibeli) + biaya tambahan (order_biaya: ongkir
 *   positif, diskon/revisi negatif). Rumus ini tidak dipakai indikator KPI: sejak
 *   29 Oktober 2026 gmv_cs dibaca dari pcs produk katalog (nilaiUnit()). Rupiahnya
 *   masih ikut dihitung per pegawai (bahan di payload ranking), tetapi tidak lagi
 *   tampil di layar dan tidak lagi jadi aturan poin sejak 31 Oktober 2026.
 * - Pcs dihitung dari order_dibeli.qty baris yang punya produk_id; ejaan kode bebas
 *   yang tidak pernah masuk katalog tidak ikut dinilai.
 * - Pengurangan dua kolom INT UNSIGNED SELALU dibungkus CAST(... AS SIGNED); tanpa
 *   itu MySQL error "value is out of range".
 * - Durasi negatif dibuang dari rata-rata dan dilaporkan sebagai jumlah yang tidak
 *   memenuhi syarat. Tanggal produksi di masa lalu kadang diisi tidak urut;
 *   memaksanya jadi nol atau membuang tanda minus menipu pembaca laporan.
 * - invoice_status.user_id dan invoice.deleted_by baru ada sejak 9 Oktober 2026.
 *   Metrik per orang dari kolom itu tampil sebagai "belum tercatat", bukan nol.
 */
class KpiModel extends Model
{
    /**
     * Level akun yang dinilai untuk tiap pilihan peran. KPI gaji khusus menilai
     * Customer Service; admin dan superadmin sengaja tidak ikut dinilai, jadi
     * 'semua' pun hanya berisi 'cs'.
     */
    private const LEVEL_PERAN = [
        'cs'    => ['cs'],
        'semua' => ['cs'],
    ];

    /**
     * Indikator yang punya jalur angka dan bisa dibuka detailnya.
     *
     * Hanya yang mengukur kerja CS; sla_fulfillment dan error_void_rate ikut
     * dikeluarkan karena keduanya indikator admin. First response time dan
     * konversi lead pernah ada di sini tetapi keduanya dihitung dari tabel
     * Live Chat WA yang halamannya sudah dihapus.
     */
    public const INDIKATOR = [
        'gmv_cs',
        'order_diproses',
        'resolution_time',
    ];

    /**
     * Nama dan penjelasan indikator yang tidak punya baris di kpi_target.
     *
     * Hanya label: angkanya tetap dihitung query di atas. Tanpa ini, halaman
     * Detail menulis kode indikator apa adanya ("resolution_time") dan pembaca
     * tidak tahu indikator apa yang sedang ia audit.
     *
     * @var array<string, array<string, string>>
     */
    public const LABEL = [
        'order_diproses' => [
            'nama'     => 'Pesanan diproses',
            'satuan'   => 'nota',
            'arah'     => 'lebih_tinggi',
            'definisi' => 'Semua nota yang dibuat orang ini pada periode terpilih, sudah lunas maupun belum.',
            'aturan'   => 'Nota yang dihapus permanen tidak dihitung. Tidak ada target: yang dinilai jumlah nota per orang.',
        ],
        'resolution_time' => [
            'nama'     => 'Waktu penyelesaian tindak lanjut',
            'satuan'   => 'jam',
            'arah'     => 'lebih_rendah',
            'definisi' => 'Rata-rata durasi penyelesaian komplain atau tindak lanjut pelanggan sejak dibuat.',
            'aturan'   => 'Tindak lanjut yang masih menunggu tidak dianggap selesai. Satu tiket dihitung satu kali.',
        ],
    ];

    /** Penanda baris tanpa pelaku: history memang tidak pernah menyimpannya. */
    private const TANPA_PELAKU = 'belum tercatat';

    protected $table         = 'invoice';
    protected $primaryKey    = 'id_invoice';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps = false;
    protected $allowedFields = [];

    /** Hafalan catatan aktivitas per pegawai, kunci = kombinasi saringan. */
    private array $aktivitas = [];

    // ------------------------------------------------------------------
    // kartu ringkasan
    // ------------------------------------------------------------------

    /**
     * Isi kartu di halaman KPI.
     *
     * Tiap kartu membawa contoh (banyaknya sampel di balik angka) dan, kalau
     * angkanya belum bisa dihitung, alasan singkat yang bisa dibaca orang.
     * Skor nol untuk "belum ada data" sengaja tidak pernah diberikan.
     *
     * @param array $s saringan dari Kpi::saring()
     */
    public function kartu(array $s): array
    {
        $nota   = $this->notePerPegawai($s);
        $resolusi = $this->resolusiPerPegawai($s);

        $order   = (int) array_sum(array_column($nota, 'jumlah_order'));
        $lunas   = (int) array_sum(array_map(static fn ($n) => (int) $n['lunas'], $nota));
        // indikator GMV = pcs produk katalog dari nota lunas, bukan nominal rupiah
        $unit    = (int) array_sum(array_map(static fn ($n) => (int) $n['unit'], $nota));
        $res_c   = (int) array_sum(array_column($resolusi, 'contoh'));

        return [
            'pegawai_dinilai' => [
                'label'         => 'Karyawan dinilai',
                'nilai'         => count($nota),
                'satuan'        => 'orang',
                'contoh'        => $order,
                'contoh_label'  => 'nota',
            ],
            'order_diproses' => [
                'label'         => 'Pesanan diproses',
                'nilai'         => $order,
                'satuan'        => 'nota',
                'contoh'        => count($nota),
                'contoh_label'  => 'karyawan',
            ],
            'resolution_time' => [
                'label'         => 'Waktu penyelesaian tindak lanjut',
                'nilai'         => $resolusi === [] ? null : round(array_sum(array_map(
                    static fn ($r) => (float) $r['rata'] * (int) $r['contoh'], $resolusi)) / max(1, $res_c), 1),
                'satuan'        => 'jam',
                'contoh'        => $res_c,
                'contoh_label'  => 'tindak lanjut',
                'tersedia'      => $res_c > 0,
                'alasan'        => $res_c > 0 ? null
                    : 'Belum ada komplain atau tindak lanjut yang selesai pada periode ini.',
            ],
            'gmv_cs' => [
                'label'         => 'GMV produk katalog yang diatribusikan ke CS',
                'nilai'         => $unit,
                'satuan'        => 'pcs',
                'contoh'        => $lunas,
                'contoh_label'  => 'nota lunas',
            ],
        ];
    }

    // ------------------------------------------------------------------
    // rekap per pegawai
    // ------------------------------------------------------------------

    /**
     * Rekap KPI per pegawai, sudah diurut dan dipaging.
     *
     * Pegawainya puluhan, jadi tiga agregasi kecil digabung di PHP lalu dipotong
     * per halaman. Transaksinya tetap tidak pernah diambil semua.
     *
     * @return array{baris: list<array>, jumlah: int}
     */
    public function rekap(array $s, int $limit, int $offset): array
    {
        $nota  = $this->notePerPegawai($s);
        $resol = $this->resolusiPerPegawai($s);

        foreach ($nota as &$pegawai) {
            $id = (int) $pegawai['user_id'];

            $pegawai['resolusi']   = $this->bulat($resol[$id]['rata'] ?? null);
        }
        unset($pegawai);

        // pegawai yang tidak menulis nota tapi pernah menutup tindak lanjut
        // tetap dinilai, jangan hilang dari daftar
        $sudah = array_map('intval', array_column($nota, 'user_id'));
        foreach (array_keys($resol) as $id) {
            $id = (int) $id;

            if (in_array($id, $sudah, true)) {
                continue;
            }

            $nota[] = [
                'user_id' => $id, 'pegawai' => $this->namaPegawai($id), 'level' => 'cs',
                'jumlah_order' => 0, 'pelanggan' => 0, 'gmv' => 0, 'unit' => 0, 'dibayar' => 0, 'lunas' => 0,
                'resolusi' => $this->bulat($resol[$id]['rata'] ?? null),
            ];
        }

        usort($nota, static fn ($a, $b) => (int) $b['unit'] <=> (int) $a['unit']);

        return ['baris' => array_slice($nota, $offset, $limit), 'jumlah' => count($nota)];
    }

    /**
     * Deretan waktu untuk grafik tren.
     *
     * @return list<array{baki: string, order: int, unit: int, lunas: int}>
     */
    public function tren(array $s): array
    {
        // keranjang waktu ditulis ulang di GROUP BY, bukan nama aliasnya: MySQL 8
        // dengan only_full_group_by menolak ekspresi tanggal yang tidak muncul di
        // GROUP BY walau aliasnya sudah dipakai
        $bakiNota  = $this->baki('o.tanggal_pesan', $s['periode']);

        $nota = [];
        foreach ($this->db->query('SELECT ' . $bakiNota . ' baki, COUNT(*) orderan,'
            . $this->nilaiUnit() . ','
            . " SUM(o.status_pembayaran IN ('5','6')) lunas"
            . ' FROM ' . $this->t('invoice') . ' o'
            . $this->joinUser() . $this->joinNilai() . $this->whereNota($s)
            . ' GROUP BY ' . $bakiNota . ' ORDER BY baki', $this->params($s))->getResultArray() as $baris) {
            $nota[$baris['baki']] = $baris;
        }

        $deretan = [];
        foreach (array_keys($nota) as $baki) {
            $deretan[] = [
                'baki'       => $baki,
                'order'      => (int) ($nota[$baki]['orderan'] ?? 0),
                'unit'       => (int) ($nota[$baki]['unit'] ?? 0),
                'lunas'      => (int) ($nota[$baki]['lunas'] ?? 0),
            ];
        }

        usort($deretan, static fn ($a, $b) => strcmp($a['baki'], $b['baki']));

        return $deretan;
    }

    /**
     * Perbandingan antarpegawai untuk satu indikator, untuk grafik batang.
     *
     * @return list<array{pegawai: string, nilai: float|null, contoh: int}>
     */
    public function pembanding(array $s, string $indikator): array
    {
        $peta = match ($indikator) {
            'resolution_time' => fn () => $this->resolusiPerPegawai($s),
            default => fn () => $this->notePerPegawai($s),
        };

        $kolom = match ($indikator) {
            'resolution_time'     => 'rata',
            'order_diproses'      => 'jumlah_order',
            default               => 'unit',
        };

        $contoh = match ($indikator) {
            'gmv_cs'          => 'lunas',
            'order_diproses'  => 'jumlah_order',
            default           => 'contoh',
        };

        $hasil = [];
        foreach ($peta() as $id => $baris) {
            $nama   = match (true) {
                isset($baris['pegawai']) => $baris['pegawai'],
                default                 => $this->namaPegawai((int) $id),
            };
            $nilai  = $baris[$kolom] ?? null;
            $hasil[] = [
                'pegawai' => $nama,
                'nilai'   => $nilai === null ? null : round((float) $nilai, 1),
                'contoh'  => (int) ($baris[$contoh] ?? 0),
            ];
        }

        usort($hasil, static fn ($a, $b) => (float) $b['nilai'] <=> (float) $a['nilai']);

        return $hasil;
    }

    // ------------------------------------------------------------------
    // detail satu indikator
    // ------------------------------------------------------------------

    /**
     * Baris pembentuk satu angka indikator, untuk audit.
     *
     * Isi pesan WhatsApp dan nama pelanggan tidak dikeluarkan di sini; yang
     * dibutuhkan hanya identitas singkat dan waktunya.
     *
     * @return list<array<string, mixed>>
     */
    public function detail(array $s, string $indikator, int $limit, int $offset): array
    {
        [$sql, $bind] = $this->sqlDetail($s, $indikator);

        return $this->db->query($sql . ' LIMIT ' . $limit . ' OFFSET ' . $offset, $bind)->getResultArray();
    }

    public function detailJumlah(array $s, string $indikator): int
    {
        [$sql, $bind] = $this->sqlDetail($s, $indikator);

        $ganti = 'SELECT COUNT(*) jumlah FROM (' . $sql . ') x';

        return (int) $this->db->query($ganti, $bind)->getRowArray()['jumlah'];
    }

    private function sqlDetail(array $s, string $indikator): array
    {
        switch ($indikator) {
            case 'resolution_time':
                return ['SELECT f.id_followup, f.judul, f.kategori, f.created_at, f.selesai_at,'
                    . ' CAST(f.selesai_at - f.created_at AS SIGNED) / 3600 durasi_jam,'
                    . ' COALESCE(u.name, ' . $this->kutip(self::TANPA_PELAKU) . ') AS pelaku'
                    . ' FROM ' . $this->t('crm_followup') . ' f'
                    . ' LEFT JOIN ' . $this->t('user') . ' u ON u.id = f.selesai_oleh'
                    . " WHERE f.status = 'selesai' AND f.selesai_at IS NOT NULL"
                    . ' AND f.created_at BETWEEN :awal_unix: AND :akhir_unix:'
                    . $this->klausulPelaku($s, 'f.selesai_oleh')
                    . ' ORDER BY durasi_jam DESC',
                    $this->paramsUnix($s)];

            default: // gmv_cs dan order_diproses memakai daftar nota yang sama
                return ['SELECT o.id_invoice, o.seri, o.tanggal_pesan, o.status_pesanan, o.status_pembayaran,'
                    . ' COALESCE(b.qty, 0) qty,'
                    . ' COALESCE(bk.unit, 0) unit,'
                    . ' COALESCE(b.barang, 0) + COALESCE(bc.biaya, 0) gmv,'
                    . ' COALESCE(pm.dibayar, 0) dibayar, u.name AS pembuat'
                    . ' FROM ' . $this->t('invoice') . ' o'
                    . $this->joinUser() . $this->joinNilai() . $this->whereNota($s)
                    // GMV dihitung dari pcs nota lunas, jadi daftarnya juga begitu
                    . ($indikator === 'gmv_cs' ? " AND o.status_pembayaran IN ('5','6')" : '')
                    . ($indikator === 'gmv_cs' ? ' ORDER BY unit DESC' : ' ORDER BY o.tanggal_pesan DESC'),
                    $this->params($s)];
        }
    }

    // ------------------------------------------------------------------
    // target
    // ------------------------------------------------------------------

    /**
     * Daftar pegawai untuk dropdown saringan peran.
     *
     * Yang dikirim ke halaman hanya id-nya; controller memeriksa id itu ada di
     * daftar ini sebelum dipakai, jadi nilai dari URL tidak pernah langsung masuk
     * ke query.
     *
     * @return array<int, string>
     */
    public function pegawaiPilihan(string $peran): array
    {
        $pilihan = [];

        foreach ($this->db->query('SELECT id, name, level FROM ' . $this->t('user')
            . " WHERE status = 'active' AND deleted_at IS NULL"
            . ' AND level IN (' . $this->daftarKutip($this->levelPeran($peran)) . ')'
            . ' ORDER BY name ASC')->getResultArray() as $u) {
            $pilihan[(int) $u['id']] = $u['name'] . ' (' . $u['level'] . ')';
        }

        return $pilihan;
    }

    /** @return list<array<string, mixed>> */
    public function targetSemua(): array
    {
        return $this->db->table('kpi_target')->orderBy('peran', 'ASC')->orderBy('nama', 'ASC')->get()->getResultArray();
    }

    public function targetSatu(string $indikator): ?array
    {
        return $this->db->table('kpi_target')->where('indikator', $indikator)->get()->getRowArray();
    }

    /**
     * Simpan target sebuah indikator.
     *
     * Hanya kolom konfigurasi yang ditulis; nama, definisi, dan aturan perhitungan
     * sengaja tidak bisa diubah dari halaman ini supaya rumusnya tidak berubah diam-diam.
     * target_tim ikut ditulis hanya bila pemanggil menyertakannya (khusus gmv_cs,
     * pemisah target Tim CS vs per-CS); indikator lain tidak punya kolom nilai itu.
     */
    public function simpanTarget(string $indikator, array $data, ?int $user): bool
    {
        $ubah = [
            'target'     => $data['target'],
            'aktif'      => $data['aktif'],
            'updated_at' => time(),
            'updated_by' => $user,
        ];

        if (array_key_exists('target_tim', $data)) {
            $ubah['target_tim'] = $data['target_tim'];
        }

        return $this->db->table('kpi_target')
            ->where('indikator', $indikator)
            ->update($ubah);
    }

    // ------------------------------------------------------------------
    // agregasi
    // ------------------------------------------------------------------

    /**
     * Nilai per pegawai dari nota yang ditulisnya.
     *
     * @return array<int, array<string, mixed>> indexed by user_id
     */
    private function notePerPegawai(array $s): array
    {
        $bind = $this->params($s);

        $valid = $this->db->query('SELECT u.id user_id, u.name pegawai, u.level,'
            . ' COUNT(*) jumlah_order, COUNT(DISTINCT o.pemesan_id) pelanggan,'
            . ' COALESCE(SUM(b.qty), 0) qty,'
            . ' COALESCE(SUM(b.barang), 0) nilai_barang, COALESCE(SUM(bc.biaya), 0) biaya_tambahan,'
            . $this->nilaiUnit() . ','
            // GMV = indikator pcs nota lunas; nilaiGmv() rupiah untuk poin ranking,
            // jumlah_order dan dibayar tetap semua nota valid
            . $this->nilaiGmv() . ','
            . ' COALESCE(SUM(pm.dibayar), 0) dibayar,'
            . " SUM(o.status_pembayaran IN ('5','6')) lunas"
            . ' FROM ' . $this->t('invoice') . ' o'
            . $this->joinUser() . $this->joinNilai() . $this->whereNota($s)
            . ' GROUP BY u.id, u.name, u.level', $bind)->getResultArray();

        $hasil = [];
        foreach ($valid as $baris) {
            $id                = (int) $baris['user_id'];
            $baris['gmv']      = round((float) $baris['gmv']);
            $baris['unit']     = (int) $baris['unit'];
            $baris['dibayar']  = round((float) $baris['dibayar']);
            $hasil[$id]        = $baris;
        }

        return $hasil;
    }

    /** @return array<int, array{contoh: int, rata: float|null}> */
    private function resolusiPerPegawai(array $s): array
    {
        return $this->indexPegawai($this->db->query('SELECT f.selesai_oleh user_id, u.name pegawai,'
            . ' COUNT(*) contoh, AVG(CAST(f.selesai_at - f.created_at AS SIGNED) / 3600) rata'
            . ' FROM ' . $this->t('crm_followup') . ' f'
            . ' JOIN ' . $this->t('user') . " u ON u.id = f.selesai_oleh AND u.status = 'active' AND u.deleted_at IS NULL"
            . " WHERE f.status = 'selesai' AND f.selesai_at IS NOT NULL"
            . ' AND f.created_at BETWEEN :awal_unix: AND :akhir_unix:'
            . ' AND u.level IN (' . $this->daftarKutip($this->levelPeran($s['peran'])) . ')'
            . $this->klausulPelaku($s, 'f.selesai_oleh')
            . ' GROUP BY f.selesai_oleh, u.name', $this->paramsUnix($s))->getResultArray());
    }

    /**
     * Nota yang dihapus per CS pembuat nota.
     *
     * Di sini dihitung pembatalan karena kesalahan dan pembatalan sah (khusus nota belum lunas)
     * yang mengurangi poin aktivitas CS. Data lama tanpa alasan atau pembatalan sah pada
     * nota yang sudah lunas tidak mengurangi poin.
     *
     * @return array<int, array{total: int, salah: int, sah: int, sah_belum_lunas: int, tanpa_alasan: int}>
     */
    private function voidPerPegawai(array $s): array
    {
        $salah = $this->daftarKutip($this->slugKesalahan());
        $sah   = $this->daftarKutip($this->slugSah());

        $hasil = [];
        foreach ($this->db->query('SELECT o.user_id user_id, u.name pegawai, COUNT(*) total,'
            . ' SUM(o.alasan_batal IN (' . $salah . ')) salah,'
            . ' SUM(o.alasan_batal IN (' . $sah . ') AND o.status_pembayaran NOT IN (\'5\', \'6\')) sah_belum_lunas,'
            . ' SUM(o.alasan_batal IN (' . $sah . ')) sah,'
            . ' SUM(o.alasan_batal IS NULL) tanpa_alasan'
            . ' FROM ' . $this->t('invoice') . ' o'
            . ' JOIN ' . $this->t('user') . " u ON u.id = o.user_id AND u.status = 'active' AND u.deleted_at IS NULL"
            . ' WHERE o.deleted_at IS NOT NULL'
            . ' AND o.deleted_at BETWEEN :awal_unix: AND :akhir_unix:'
            . ' AND o.juragan_id IN (' . $this->daftarId($s['juragan_ids']) . ')'
            . $this->klausulPelaku($s, 'o.user_id')
            . ' GROUP BY o.user_id, u.name', $this->paramsUnix($s))->getResultArray() as $baris) {
            $hasil[(int) $baris['user_id']] = [
                'pegawai'         => $baris['pegawai'],
                'total'           => (int) $baris['total'],
                'salah'           => (int) $baris['salah'],
                'sah'             => (int) $baris['sah'],
                'sah_belum_lunas' => (int) $baris['sah_belum_lunas'],
                'tanpa_alasan'    => (int) $baris['tanpa_alasan'],
            ];
        }

        return $hasil;
    }

    private function slugKesalahan(): array
    {
        helper('fungsi');

        return array_keys(array_filter(alasan_batal(), static fn ($a) => $a['jenis'] === 'kesalahan'));
    }

    private function slugSah(): array
    {
        helper('fungsi');

        return array_keys(array_filter(alasan_batal(), static fn ($a) => $a['jenis'] === 'sah'));
    }

    // ------------------------------------------------------------------
    // peringkat poin aktivitas
    // ------------------------------------------------------------------

    /**
     * Aturan poin: satu baris untuk setiap aktivitas yang meninggalkan jejak
     * beserta pelakunya di database.
     *
     * 'poin' di sini adalah bawaan kode, bukan angka final yang dipakai halaman:
     * Superadmin boleh menggantinya lewat halaman Target, dan angka simpanan itu
     * yang dibaca perhitungan (lihat poinAktif()). Nilainya disimpan di crm_setting
     * dengan kunci poin_<slug>.
     *
     * Empat aturan sudah dicabut (31 Oktober 2026) atas permintaan manajemen karena
     * kolomnya dianggap tidak penting di halaman Ranking: nilai_lunas,
     * tindak_lanjut_selesai, aktivitas_crm, dan pesan_manual. Yang terakhir ikut
     * berhenti dihitung querynya, jadi poin tidak lagi menelan kerja yang tidak
     * dibaca siapa-siapa.
     *
     * 'catatan' muncul di halaman ranking untuk aturan yang angkanya hari ini masih
     * nol karena pencatatannya baru dimulai atau tabelnya belum dipakai.
     */
    public const ATURAN_POIN = [
        'nota_dibuat'    => ['nama' => 'Nota dibuat', 'sumber' => 'order_invoice.user_id', 'satuan' => 'nota', 'poin' => 2],
        'nota_lunas'     => ['nama' => 'Nota lunas (closing)', 'sumber' => 'order_invoice.status_pembayaran 5/6', 'satuan' => 'nota', 'poin' => 3],
        'tahap_produksi' => ['nama' => 'Tahap produksi dikerjakan', 'sumber' => 'order_invoice_status pada nota CS', 'satuan' => 'catatan tahap', 'poin' => 1, 'catatan' => 'tahap produksi yang dicatat pada nota milik CS'],
        'void_salah'     => ['nama' => 'Nota dibatalkan karena kesalahan', 'sumber' => 'order_invoice.user_id + alasan_batal', 'satuan' => 'nota', 'poin' => -5, 'catatan' => 'pembatalan sah (khusus nota belum lunas) dan kesalahan input dikurangi poin'],
    ];

    /** Batas nilai poin yang boleh dipasang; di luar ini dianggap salah ketik. */
    public const POIN_MIN = -100.0;
    public const POIN_MAX = 100.0;

    /** Awalan kunci crm_setting tempat Superadmin menyimpan poin yang berlaku. */
    public const POIN_KUNCI = 'poin_';

    /**
     * Kerja yang tidak masuk poin karena tabelnya tidak menyimpan pelaku.
     *
     * Daftarnya ikut ditampilkan di halaman ranking supaya pembaca tahu angka
     * ini bukan ukuran lengkap dari semua yang dikerjakan seseorang.
     */
    public const TIDAK_TERNILAI = [
        'order_pembayaran'           => 'mencatat pembayaran',
        'order_pengiriman'           => 'mengirim barang',
        'order_produk'               => 'mengubah master data barang',
        'order_user'                 => 'membuat dan mengubah akun',
    ];

    /**
     * Poin bawaan kode, apa adanya dari ATURAN_POIN.
     *
     * @return array<string, float> slug => poin
     */
    public function poinBaku(): array
    {
        return array_map(static fn ($a) => (float) $a['poin'], self::ATURAN_POIN);
    }

    /**
     * Poin yang berlaku: angka yang dipasang Superadmin kalau ada, selain itu
     * bawaan kode.
     *
     * Disimpan di crm_setting (key-value) karena aturannya cuma beberapa baris dan
     * perubahannya keputusan manajemen, bukan data yang perlu diaudit per periode.
     * Nilai yang bukan angka (NULL dari reset, atau sisa impor lama) diabaikan,
     * jadi halaman tetap punya angka walaupun tabel setelannya kosong.
     *
     * @return array<string, float> slug => poin berlaku
     */
    public function poinAktif(): array
    {
        $berlaku = $this->poinBaku();
        $simpan  = (new PengaturanModel())->banyak(array_map(
            static fn ($slug) => self::POIN_KUNCI . $slug,
            array_keys($berlaku)
        ));

        foreach ($berlaku as $slug => $baku) {
            $nilai = $simpan[self::POIN_KUNCI . $slug] ?? null;

            if ($nilai !== null && is_numeric($nilai)) {
                $berlaku[$slug] = round((float) $nilai, 1);
            }
        }

        return $berlaku;
    }

    /**
     * Daftar aturan untuk editor poin di halaman Target: bawaan, berlaku, dan
     * apakah sudah dikustomisasi. Tidak menyentuh tabel kerja sama sekali, jadi
     * halaman Target tetap ringan walau periode yang dibuka panjang.
     *
     * @return list<array<string, mixed>>
     */
    public function poinSemua(): array
    {
        $aktif = $this->poinAktif();
        $isi   = [];

        foreach (self::ATURAN_POIN as $slug => $aturan) {
            // array_merge, bukan operator '+': kunci 'poin' sudah ada di $aturan
            // (bawaan kode), dan '+' tidak menimpa kunci yang sudah ada
            $isi[] = array_merge($aturan, [
                'slug'      => $slug,
                'poin'      => $aktif[$slug],
                'poin_baku' => (float) $aturan['poin'],
                'kustom'    => abs($aktif[$slug] - (float) $aturan['poin']) > 0.001,
            ]);
        }

        return $isi;
    }

    /**
     * Simpan poin per aktivitas.
     *
     * Kolom yang dibiarkan kosong, atau diisi sama persis dengan bawaan kode,
     * menghapus kunci simpanannya: angka yang berlaku kembali ke ATURAN_POIN.
     * Dengan begitu tabel setelan tidak mengumpul nilai yang sebenarnya tidak
     * membedakan apa-apa, dan upgrade bawaan di kode nanti tetap sampai ke layar.
     *
     * @param array<string, mixed> $poin slug => nilai mentah dari form
     * @return array{diubah: int, lewat: int, di luar: int}
     */
    public function simpanPoin(array $poin, ?int $user): array
    {
        $setelan = new PengaturanModel();
        $baku    = $this->poinBaku();
        $diubah  = 0;
        $lewat   = 0;
        $keluar  = 0;

        foreach ($poin as $slug => $nilai) {
            $slug = (string) $slug;

            if (! array_key_exists($slug, $baku)) {
                $keluar++;

                continue;
            }

            $bersih = trim((string) $nilai);

            if ($bersih === '' || ! is_numeric($bersih)) {
                $setelan->tulis(self::POIN_KUNCI . $slug, null);
                $diubah++;

                continue;
            }

            $angka = round((float) $bersih, 1);

            if ($angka < self::POIN_MIN || $angka > self::POIN_MAX) {
                $lewat++;

                continue;
            }

            $setelan->tulis(
                self::POIN_KUNCI . $slug,
                abs($angka - $baku[$slug]) < 0.001 ? null : (string) $angka
            );

            $diubah++;
        }

        log_message('info', 'Poin KPI diubah oleh user ' . ($user ?? 0) . ': ' . $diubah . ' aturan disimpan, '
            . $lewat . ' dilewati (di luar ' . self::POIN_MIN . '..' . self::POIN_MAX . '), '
            . $keluar . ' slug tidak dikenal.');

        return ['diubah' => $diubah, 'lewat' => $lewat, 'di luar' => $keluar];
    }

    /**
     * Peringkat karyawan dari poin aktivitas pada periode terpilih.
     *
     * Hitungannya memakai query per pegawai yang sama dengan kartu KPI, jadi
     * poin tidak mungkin bertentangan dengan angka di halaman Ringkasan.
     *
     * @return list<array<string, mixed>>
     */
    public function peringkat(array $s): array
    {
        $catatan = $this->catatanAktivitas($s);
        $orang   = $this->pegawaiDetil($s['peran']);
        $berlaku = $this->poinAktif();

        if (! empty($s['user_id'])) {
            $orang = array_intersect_key($orang, [(int) $s['user_id'] => 1]);
        }

        $hasil = [];
        foreach ($orang as $id => $u) {
            $jumlah = [];
            $poin   = [];
            $total  = 0.0;

            foreach (self::ATURAN_POIN as $slug => $aturan) {
                $n          = (int) ($catatan[$id][$slug] ?? 0);
                $jumlah[$slug] = $n;
                $poin[$slug]   = round($n * ($berlaku[$slug] ?? 0.0), 1);
                $total        += $poin[$slug];
            }

            $hasil[] = [
                'user_id'  => $id,
                'pegawai'  => $u['nama'],
                'level'    => $u['level'],
                'jumlah'   => $jumlah,
                'poin'     => $poin,
                'total'    => round($total, 1),
                'gmv'      => (float) ($catatan[$id]['gmv'] ?? 0),
                'unit'     => (int) ($catatan[$id]['unit'] ?? 0),
                'tercatat' => array_sum(array_map(
                    static fn ($slug) => (int) ($jumlah[$slug] ?? 0),
                    array_keys(self::ATURAN_POIN)
                )),
            ];
        }

        usort($hasil, static fn ($a, $b) => [$b['total'], $a['pegawai']] <=> [$a['total'], $b['pegawai']]);

        // poin yang sama mendapat peringkat yang sama, peringkat berikutnya ikut melompat
        $nomor  = 0;
        $poinLalu = null;
        foreach ($hasil as $i => $baris) {
            if ($baris['total'] !== $poinLalu) {
                $nomor    = $i + 1;
                $poinLalu = $baris['total'];
            }
            $hasil[$i]['peringkat'] = $nomor;
        }

        return $hasil;
    }

    /**
     * Aturan poin beserta berapa banyak aktivitasnya tercatat pada periode ini.
     *
     * Dipakai tabel "aturan poin" supaya aktivitas yang belum bernilai apa-apa
     * terlihat sebagai 0 tercatat, bukan sebagai aturan yang hilang.
     *
     * @return list<array<string, mixed>>
     */
    public function aturanPoin(array $s): array
    {
        $catatan = $this->catatanAktivitas($s);
        $berlaku = $this->poinAktif();

        $hasil = [];
        foreach (self::ATURAN_POIN as $slug => $aturan) {
            $tercatat = 0;
            foreach ($catatan as $baris) {
                $tercatat += (int) ($baris[$slug] ?? 0);
            }

            $hasil[] = array_merge($aturan, [
                'slug'         => $slug,
                'tercatat'     => $tercatat,
                // yang ditampilkan angka yang berlaku, bukan bawaan kode: pembaca
                // popup harus melihat dasar hitung gaji yang sebenarnya
                'poin'         => $berlaku[$slug] ?? (float) $aturan['poin'],
                'poin_baku'    => (float) $aturan['poin'],
            ]);
        }

        return $hasil;
    }

    /**
     * Jumlah tiap aktivitas per pegawai, digabung dari query nota dan jejak CRM.
     *
     * Halaman ranking memakai hasil ini dua kali (tabel peringkat dan tabel
     * aturan), jadi dihafal per kombinasi saringan supaya query berat tidak
     * dijalankan dua kali dalam satu permintaan.
     *
     * @return array<int, array<string, int|float>>
     */
    private function catatanAktivitas(array $s): array
    {
        $kunci = $s['awal'] . '|' . $s['akhir'] . '|' . $s['peran'] . '|'
            . ($s['user_id'] ?? '') . '|' . implode(',', $s['juragan_ids']);

        if (! isset($this->aktivitas[$kunci])) {
            $this->aktivitas[$kunci] = $this->hitungAktivitas($s);
        }

        return $this->aktivitas[$kunci];
    }

    /** @return array<int, array<string, int|float>> */
    private function hitungAktivitas(array $s): array
    {
        $catatan = [];

        foreach ($this->notePerPegawai($s) as $id => $b) {
            $catatan[$id] = [
                'nota_dibuat' => (int) $b['jumlah_order'],
                'nota_lunas'  => (int) $b['lunas'],
                // gmv dan unit ikut dibawa untuk kolom catatan halaman ranking;
                // keduanya bukan aturan poin lagi sejak nilai nota dicabut dari poin
                'gmv'         => (float) $b['gmv'],
                'unit'        => (int) $b['unit'],
            ];
        }

        // pengurang: nota yang dibatalkan karena kesalahan dan pembatalan sah (belum lunas)
        foreach ($this->voidPerPegawai($s) as $id => $baris) {
            if ($id === 0) {
                continue;
            }

            $catatan[$id]['void_salah'] = (int) $baris['salah'] + (int) $baris['sah_belum_lunas'];
        }

        foreach ($this->jejakLain($s) as $slug => $perPegawai) {
            foreach ($perPegawai as $id => $jumlah) {
                $catatan[$id][$slug] = $jumlah;
            }
        }

        return $catatan;
    }

    /**
     * Aktivitas di luar nota yang tetap dihitung poin: tahap produksi.
     * Dihitung per CS pemilik nota yang tahap produksinya dikerjakan.
     *
     * Tahap produksi ikut menilai nota yang kemudian dibatalkan: kerjanya memang terjadi.
     *
     * @return array<string, array<int, int>>
     */
    private function jejakLain(array $s): array
    {
        $bind  = $this->paramsUnix($s);
        $level = ' AND u.level IN (' . $this->daftarKutip($this->levelPeran($s['peran'])) . ')';
        $toko  = ' AND o.juragan_id IN (' . $this->daftarId($s['juragan_ids']) . ')';

        $query = [
            'tahap_produksi' => 'SELECT o.user_id user_id, COUNT(1) jumlah FROM ' . $this->t('invoice_status') . ' st'
                . ' JOIN ' . $this->t('invoice') . ' o ON o.id_invoice = st.invoice_id'
                . $this->sambungkan('o', 'user_id')
                . ' WHERE st.tanggal_masuk BETWEEN :awal_unix: AND :akhir_unix:' . $toko . $level
                . $this->klausulPelaku($s, 'o.user_id') . ' GROUP BY o.user_id',
        ];

        $hasil = [];
        foreach ($query as $slug => $sql) {
            $isi = [];
            foreach ($this->db->query($sql, $bind)->getResultArray() as $baris) {
                $isi[(int) $baris['user_id']] = (int) $baris['jumlah'];
            }
            $hasil[$slug] = $isi;
        }

        return $hasil;
    }

    /** Join ke tabel akun aktif; kolom pelakunya bisa berbeda nama per tabel. */
    private function sambungkan(string $alias, string $kolom = 'user_id'): string
    {
        return ' JOIN ' . $this->t('user') . ' u ON u.id = ' . $alias . '.' . $kolom
            . " AND u.status = 'active' AND u.deleted_at IS NULL";
    }

    /** Akun yang dinilai, terpisah nama dan levelnya untuk tabel peringkat. */
    private function pegawaiDetil(string $peran): array
    {
        $isi = [];
        foreach ($this->db->query('SELECT id, name, level FROM ' . $this->t('user')
            . " WHERE status = 'active' AND deleted_at IS NULL"
            . ' AND level IN (' . $this->daftarKutip($this->levelPeran($peran)) . ')'
            . ' ORDER BY name ASC')->getResultArray() as $u) {
            $isi[(int) $u['id']] = ['nama' => $u['name'], 'level' => $u['level']];
        }

        return $isi;
    }

    // ------------------------------------------------------------------
    // pembantu
    // ------------------------------------------------------------------

    /**
     * Median tanpa fungsi MEDIAN (tidak ada di MySQL 8): hitung barisnya dulu,
     * lalu ambil satu atau dua baris tengah dengan LIMIT/OFFSET.
     *
     * $kondisi memakai $alias yang sama dengan subquerynya dan ditulis tanpa WHERE.
     */
    private function median(string $kondisi, string $dasar, array $bind, string $kolom, string $alias): ?float
    {
        $jumlah = (int) $this->db->query('SELECT COUNT(*) j FROM (' . $dasar . ') ' . $alias
            . ' WHERE ' . $kondisi, $bind)->getRowArray()['j'];

        if ($jumlah === 0) {
            return null;
        }

        $mulai = intdiv($jumlah - 1, 2);
        $ambil = $jumlah % 2 === 1 ? 1 : 2;

        $tengah = $this->db->query('SELECT ' . $alias . '.' . $kolom . ' nilai FROM (' . $dasar . ') ' . $alias
            . ' WHERE ' . $kondisi
            . ' ORDER BY ' . $alias . '.' . $kolom . ' ASC LIMIT ' . $ambil . ' OFFSET ' . $mulai, $bind)->getResultArray();

        return $tengah === [] ? null : array_sum(array_column($tengah, 'nilai')) / count($tengah);
    }

    /** Angka satu pegawai: tetap kosong kalau datangnya memang tidak ada. */
    private function bulat(mixed $nilai): ?float
    {
        return $nilai === null ? null : round((float) $nilai, 1);
    }

    /** @param list<array<string, mixed>> $baris @return array<int, array<string, mixed>> */
    private function indexPegawai(array $baris): array
    {
        $hasil = [];
        foreach ($baris as $b) {
            $hasil[(int) $b['user_id']] = $b;
        }

        return $hasil;
    }

    private function namaPegawai(int $id): string
    {
        return (string) ($this->db->table('user')->select('name')->where('id', $id)->get()->getRowArray()['name'] ?? '');
    }

    /** Periode dibaca dari kolom tanggal; $baki memilih bentuk keranjangnya. */
    private function baki(string $kolom, string $periode): string
    {
        return match ($periode) {
            'harian'   => 'DATE(' . $kolom . ')',
            'mingguan' => "DATE_FORMAT(" . $kolom . ", '%x-%v')",
            default    => "DATE_FORMAT(" . $kolom . ", '%Y-%m')",
        };
    }

    private function joinUser(): string
    {
        return ' JOIN ' . $this->t('user') . " u ON u.id = o.user_id AND u.status = 'active' AND u.deleted_at IS NULL";
    }

    /** Turunan nilai satu nota: pcs katalog (bk), barang, biaya tambahan, pembayaran. */
    private function joinNilai(): string
    {
        return ' LEFT JOIN (SELECT invoice_id, SUM(qty * harga) AS barang, SUM(qty) AS qty'
            . ' FROM ' . $this->t('dibeli') . ' GROUP BY invoice_id) b ON b.invoice_id = o.id_invoice'
            // pcs katalog: hanya baris yang ejaan kodenya pernah ditautkan ke master produk
            . ' LEFT JOIN (SELECT invoice_id, SUM(qty) AS unit'
            . ' FROM ' . $this->t('dibeli') . ' WHERE produk_id IS NOT NULL GROUP BY invoice_id) bk'
            . ' ON bk.invoice_id = o.id_invoice'
            . ' LEFT JOIN (SELECT invoice_id, SUM(nominal) AS biaya'
            . ' FROM ' . $this->t('biaya') . ' GROUP BY invoice_id) bc ON bc.invoice_id = o.id_invoice'
            . ' LEFT JOIN (SELECT invoice_id, SUM(total_pembayaran) AS dibayar'
            . ' FROM ' . $this->t('pembayaran') . ' GROUP BY invoice_id) pm ON pm.invoice_id = o.id_invoice';
    }

    /**
     * GMV dalam satu agregat nota: hanya nota lunas (status pembayaran 5/6) yang
     * dinilai, supaya angka karyawan, grafik, dan rekap memakai dasar yang sama
     * dengan definisi indikator. Nota yang masih berjalan ikut terlihat di
     * jumlah_order dan dibayar, bukan di GMV.
     */
    private function nilaiGmv(): string
    {
        return "COALESCE(SUM(CASE WHEN o.status_pembayaran IN ('5','6')"
            . ' THEN COALESCE(b.barang, 0) + COALESCE(bc.biaya, 0) ELSE 0 END), 0) gmv';
    }

    /**
     * GMV dalam satu agregat nota: jumlah pcs produk katalog dari nota lunas.
     *
     * Indikator ini dulu dibaca sebagai nilai rupiah nota lunas; sejak manajemen
     * menilai CS dari unit yang ditutup, pembilangnya order_dibeli.qty per baris
     * yang produk_id-nya terisi. Kolam jual dan sewa dihitung sama: satu unit
     * tetap satu pcs. Nota yang masih berjalan ikut terlihat di jumlah_order dan
     * dibayar, bukan di GMV.
     */
    private function nilaiUnit(): string
    {
        return "COALESCE(SUM(CASE WHEN o.status_pembayaran IN ('5','6')"
            . ' THEN COALESCE(bk.unit, 0) ELSE 0 END), 0) unit';
    }

    private function whereNota(array $s): string
    {
        return ' WHERE o.tanggal_pesan BETWEEN :awal: AND :akhir:'
            . ' AND o.deleted_at IS NULL AND o.status_pesanan <> \'3\''
            . ' AND o.juragan_id IN (' . $this->daftarId($s['juragan_ids']) . ')'
            . $this->klausulLevel($s['peran'])
            . $this->klausulPelaku($s, 'o.user_id');
    }

    private function klausulLevel(string $peran): string
    {
        return ' AND u.level IN (' . $this->daftarKutip($this->levelPeran($peran)) . ')';
    }

    private function levelPeran(string $peran): array
    {
        return self::LEVEL_PERAN[$peran] ?? self::LEVEL_PERAN['semua'];
    }

    /** Pembatas satu pegawai, dipakai dari halaman detail; id-nya sudah divali controller. */
    private function klausulPelaku(array $s, string $kolom): string
    {
        return empty($s['user_id']) ? '' : ' AND ' . $kolom . ' = :user_id:';
    }

    private function params(array $s): array
    {
        return array_filter([
            'awal'    => $s['awal'],
            'akhir'   => $s['akhir'],
            'user_id' => $s['user_id'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /** Versi periode untuk kolom unix (deleted_at, created_at tindak lanjut). */
    private function paramsUnix(array $s): array
    {
        return $this->params($s) + [
            'awal_unix'  => strtotime($s['awal'] . ' 00:00:00'),
            'akhir_unix' => strtotime($s['akhir'] . ' 23:59:59'),
        ];
    }

    private function daftarId(array $ids): string
    {
        $bersih = array_values(array_filter(array_map('intval', $ids)));

        return $bersih === [] ? '0' : implode(',', $bersih);
    }

    private function daftarKutip(array $nilai): string
    {
        return implode(',', array_map(
            fn ($n) => $this->kutip((string) $n),
            $nilai
        ));
    }

    /** Kutip teks konstanta di dalam SQL. Tidak pernah dipakai untuk input pengguna. */
    private function kutip(string $nilai): string
    {
        return "'" . str_replace("'", '', $nilai) . "'";
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }
}
