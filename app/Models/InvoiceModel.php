<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoiceModel extends Model
{
    protected $table              = 'invoice';
    // protected $table              = 'order_invoice';
    protected $primaryKey         = 'id_invoice';
    protected $returnType         = 'object';
    protected $useSoftDeletes     = true;
    protected $allowedFields      = ['seri', 'kode_lacak', 'tanggal_pesan', 'pemesan_id', 'kirimKepada_id', 'juragan_id', 'user_id', 'status_pesanan', 'status_pembayaran', 'status_pengiriman', 'sewa_kembali_at', 'sewa_kembali_oleh', 'keterangan', 'rincian', 'deadline'];
    protected $useTimestamps      = true;
    protected $createdField       = 'created_at';
    protected $updatedField       = 'update_at';
    protected $deletedField       = 'deleted_at';
    protected $dateFormat         = 'int';
    protected $validationRules    = [];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /** Ditulis sebagai array: CI4 memanggil callback lewat properti ini, bukan lewat nama metodanya. */
    protected $beforeInsert       = ['isiKodeLacak'];

    /**
     * Nomor nota urut dan tercetak di mana-mana, jadi tidak cukup jadi penanda
     * orderan kalau statusnya dibuka ke pihak luar lewat API. Kode lacak ditulis
     * di sini supaya jalur Admin dan CS memakai satu aturan; nota lama tetap aman
     * karena kolomnya boleh NULL dan sudah diisi migrasi.
     */
    public function isiKodeLacak(array $data): array
    {
        if (empty($data['data']['kode_lacak'])) {
            $data['data']['kode_lacak'] = KunciApiModel::kodeBaru();
        }

        return $data;
    }

    /**
     * Ambil detail semua invoice
     *
     * @param string $hal        semua, selesai, belum-proses, dalam-proses, pembayaran (cek-bayar = nama lama)
     * @param int    $juragan_id ID Juragan
     * @param string $cari       Kata Pencarian
     * @param int    $limit      Batas/Limit data yang akan ditampilkan
     * @param int    $offset     Awal data yang akan ditampilkan
     * @param string $kategori   Kunci dari kategori_tab($hal), dipakai saat $hal pembayaran/belum-proses
     * @param array<int>|null $hanya Daftar id juragan yang boleh dilihat, null = tidak membatasi
     * @param bool   $sewa_sudah Bagian tab Disewa: false = Masih Disewa, true = Sudah Kembali
     */
    public function getAll(string $hal = 'semua', int $juragan_id = 0, $cari = '', int $limit = 0, int $offset = 0, string $kategori = 'perlu-cek', ?array $hanya = null, bool $sewa_sudah = false): array
    {
        $biayaModel      = new BiayaModel();
        $anggotaModel    = new AnggotaModel();
        $dibeliModel     = new BarangDibeli();
        $fotoModel       = new FotoModel();
        $invStatusModel  = new StatusModel();
        $juraganModel    = new JuraganModel();
        $labelInvModel   = new LabelInvoice();
        $nilaiModel      = new NilaiUkuranModel();
        $pelangganModel  = new PelangganModel();
        $pembayaranModel = new PembayaranModel();
        $penggunaModel   = new UserModel();
        $pengirimanModel = new PengirimanModel();
        $builder         = $this;
        $return          = [];

        // IN () kosong adalah SQL invalid, gunakan [0] saat tidak ada id yang cocok
        $builder->whereIn('id_invoice', $this->ambilIds($cari) ?: [0]);

        $this->terapkanTab($builder, $hal, $kategori, $sewa_sudah);

        if ($juragan_id > 0) {
            $builder->where('juragan_id', $juragan_id);
        }

        $this->batasiToko($builder, $hanya);

        if ($hal === 'pembayaran' || $hal === 'cek-bayar') {
            // satu daftar lurus: nota terbaru lebih dulu, tanpa dikelompokkan per status
            $builder->orderBy('tanggal_pesan DESC, update_at DESC');
        } else {
            $builder->orderBy('status_pesanan ASC, tanggal_pesan DESC, update_at DESC');
        }

        $inv = $builder->findAll($limit, $offset);
        
        foreach ($inv as $invoice) {
            $return[$invoice->id_invoice]               = (array) $invoice;
            $return[$invoice->id_invoice]['barang']     = $dibeliModel->getSimple($invoice->id_invoice);
            $return[$invoice->id_invoice]['biaya']      = $biayaModel->getSimple($invoice->id_invoice);
            $return[$invoice->id_invoice]['juragan']    = $juraganModel->getSimple($invoice->juragan_id);
            $return[$invoice->id_invoice]['kirimKe']    = $pelangganModel->getSimple($invoice->kirimKepada_id);
            $return[$invoice->id_invoice]['pelanggan']  = $pelangganModel->getSimple($invoice->pemesan_id);
            $return[$invoice->id_invoice]['pembayaran'] = $pembayaranModel->getSimple($invoice->id_invoice);
            $return[$invoice->id_invoice]['pengguna']   = $penggunaModel->getSimple($invoice->user_id);
            $return[$invoice->id_invoice]['pengiriman'] = $pengirimanModel->getSimple($invoice->id_invoice);
            $return[$invoice->id_invoice]['source']     = $labelInvModel->getSimple($invoice->id_invoice);
            $return[$invoice->id_invoice]['status']     = $invStatusModel->getSimple($invoice->id_invoice);
        }

        // ukuran terstruktur menempel di baris produknya; order lama tidak punya
        // baris di tabel nilai_ukuran, jadi daftar ini memang kosong untuk mereka
        $ukuran = $nilaiModel->perBarang(array_keys($return));

        foreach ($return as $r) {
            foreach ($r['barang'] as $b) {
                $b->nilai_ukuran = $ukuran[$b->id] ?? [];
            }
        }

        // daftar rombongan menempel di kartunya; order satuan tidak punya baris
        // sama sekali di order_anggota jadi bloknya tidak dirender
        $rombongan = $anggotaModel->banyak(array_keys($return));

        foreach (array_keys($return) as $i) {
            $return[$i]['anggota'] = $rombongan[$i] ?? [];
        }

        // sekali query untuk semua kartu; order lama tidak punya baris foto sama
        // sekali, jadi blok fotonya tidak dirender dan kartunya seperti dulu
        $foto = $fotoModel->banyak(array_keys($return));

        foreach (array_keys($return) as $i) {
            $return[$i]['foto'] = $foto[$i] ?? [];
        }

        return [
            'data'      => json_decode(json_encode(array_values($return))),
            'totalPage' => $this->counter($hal, $juragan_id, $cari, $kategori, $hanya, $sewa_sudah),
        ];
    }

    /**
     * Nilai invoice.status_pembayaran milik satu kategori pembayaran
     */
    private static function statusKategori(string $kategori): array
    {
        helper('fungsi');

        $daftar = kategori_pembayaran();

        return $daftar[$kategori]['status'] ?? $daftar['perlu-cek']['status'];
    }

    /**
     * Jumlah orderan per kategori pembayaran untuk satu juragan (sekali query).
     * Key 'semua' berisi total orderan pada tab tersebut.
     *
     * @return array<string, int> kategori_tab() slug => jumlah
     */
    public function countPembayaran(int $juragan_id = 0, string $hal = 'pembayaran', ?array $hanya = null): array
    {
        helper('fungsi');

        $b = $this->db->table('invoice');
        $b->select('status_pembayaran, COUNT(*) AS n');
        $b->where('deleted_at', null);

        if ($hal === 'belum-proses') {
            $b->where('status_pesanan', '1');
        }

        if ($juragan_id > 0) {
            $b->where('juragan_id', $juragan_id);
        }

        $this->batasiToko($b, $hanya);

        $b->groupBy('status_pembayaran');

        $per_status = [];

        foreach ($b->get()->getResult() as $r) {
            $per_status[(string) $r->status_pembayaran] = (int) $r->n;
        }

        $jumlah = ['semua' => array_sum($per_status)];

        foreach (array_keys(kategori_tab($hal)) as $slug) {
            if ($slug === 'semua') {
                continue;
            }

            $jumlah[$slug] = 0;

            foreach (self::statusKategori($slug) as $s) {
                $jumlah[$slug] += $per_status[$s] ?? 0;
            }
        }

        return $jumlah;
    }

    /**
     * Tampilkan counter
     *
     * @param mixed $cari Bisa berupa array atau string
     */
    private function counter(string $hal = 'semua', int $juragan_id = 0, $cari = '', string $kategori = 'perlu-cek', ?array $hanya = null, bool $sewa_sudah = false): int
    {
        $builder = $this->select('id_invoice');

        $builder->whereIn('id_invoice', $this->ambilIds($cari) ?: [0]);

        $this->terapkanTab($builder, $hal, $kategori, $sewa_sudah);

        if ($juragan_id > 0) {
            $builder->where('juragan_id', $juragan_id);
        }

        $this->batasiToko($builder, $hanya);

        return $builder->countAllResults();
    }

    /**
     * Klausul satu tab daftar transaksi. Satu tempat supaya tab, counter, dan
     * angka dasbor tidak bisa lagi berbeda aturan.
     *
     * @param \CodeIgniter\Model|\CodeIgniter\Database\BaseBuilder $builder
     */
    private function terapkanTab($builder, string $hal, string $kategori = 'perlu-cek', bool $sewa_sudah = false): void
    {
        switch ($hal) {
            case 'selesai':
                $builder->where('status_pengiriman', '3');

                // nota sewa belum selesai sampai barangnya dikembalikan: tab ini dan
                // bagian Masih Disewa tidak boleh memuat nota yang sama
                $builder->where('NOT (' . self::klausulSewaMenunggu($this->db->prefixTable('invoice')) . ')', null, false);

                // sebaliknya, sewa yang sudah dicatat kembali pindah ke bagian
                // "Sudah Kembali" di tab Disewa — tidak dihitung dua kali
                $builder->where('NOT (' . self::klausulSewaKembali($this->db->prefixTable('invoice')) . ')', null, false);
                break;

            case 'masih-disewa':
                $builder->where(
                    $sewa_sudah
                        ? self::klausulSewaKembali($this->db->prefixTable('invoice'))
                        : self::klausulMasihDisewa($this->db->prefixTable('invoice')),
                    null,
                    false
                );
                break;

            case 'belum-proses':
                $builder->where('status_pesanan', '1');

                if ($kategori !== 'semua') {
                    $builder->whereIn('status_pembayaran', self::statusKategori($kategori));
                }

                break;

            case 'pembayaran':
            case 'cek-bayar':
                $builder->whereIn('status_pembayaran', self::statusKategori($kategori));
                break;

            case 'dalam-proses':
                $builder->where('status_pesanan', '2');
                $builder->whereNotIn('status_pengiriman', ['3']);
                break;

            case 'saring':
                $this->terapkanSaring($builder, $kategori);
                break;
        }
    }

    /**
     * Klausul satu saringan orderan dari kartu/panel dasbor.
     *
     * Semua saringan kerja hanya menghitung orderan yang belum diambil
     * pelanggan; yang sudah diserahkan jadi riwayat, bukan pekerjaan hari ini.
     *
     * @param \CodeIgniter\Model|\CodeIgniter\Database\BaseBuilder $builder
     */
    private function terapkanSaring($builder, string $slug): void
    {
        helper('fungsi');

        $orderan = $this->db->prefixTable('invoice');
        $tahap   = $this->db->prefixTable('invoice_status');
        $packing = array_key_last(tahap_produksi());

        // kartu yang isinya sama dengan tab yang sudah ada ikut tab itu saja
        if ($slug === 'produksi' || $slug === 'belum-diproses') {
            $this->terapkanTab(
                $builder,
                $slug === 'produksi' ? 'dalam-proses' : 'belum-proses',
                'semua'
            );

            return;
        }

        $belum_diambil = "status_pengiriman <> '3' AND ";

        switch ($slug) {
            case 'pesan-hari-ini':
                $sql = 'tanggal_pesan = CURDATE()';
                break;

            case 'aktif':
                $sql = "status_pesanan IN ('1', '2') AND status_pengiriman <> '3'";
                break;

            case 'belum-lunas':
                $sql = "status_pembayaran NOT IN ('5', '6')";
                break;

            case 'siap-diambil':
                $sql = $belum_diambil
                    . "EXISTS (SELECT 1 FROM `{$tahap}` s WHERE s.invoice_id = `{$orderan}`.id_invoice"
                    . " AND s.status = '{$packing}' AND s.tanggal_selesai IS NOT NULL)";
                break;

            case 'ukuran-belum-lengkap':
                $anggota = $this->db->prefixTable('anggota');
                $nilai   = $this->db->prefixTable('nilai_ukuran');

                $sql = $belum_diambil
                    . "EXISTS (SELECT 1 FROM `{$anggota}` a WHERE a.invoice_id = `{$orderan}`.id_invoice)"
                    . " AND NOT EXISTS (SELECT 1 FROM `{$nilai}` v WHERE v.invoice_id = `{$orderan}`.id_invoice)";
                break;

            case 'deadline-hari-ini':
                $sql = $belum_diambil . 'deadline = CURDATE()';
                break;

            case 'deadline-besok':
                $sql = $belum_diambil . 'deadline = CURDATE() + INTERVAL 1 DAY';
                break;

            case 'deadline-3-hari':
                $sql = $belum_diambil . 'deadline BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY';
                break;

            case 'deadline-terlewat':
                $sql = $belum_diambil . 'deadline < CURDATE()';
                break;

            default:
                // slug tidak dikenal: jangan kembalikan seluruh isi tabel
                $sql = '0 = 1';
        }

        $builder->where($sql, null, false);
    }

    /**
     * Klausul bagian "Masih Disewa" di tab Disewa: nota sewa yang pengembaliannya
     * belum dicatat, belum dibatalkan, DAN sudah ada dananya masuk — DP (3),
     * sedang dicicil (4), atau lunas (5/6). Nota sewa yang belum ada pembayaran
     * sama sekali belum menjadi sewa yang berjalan: dia tetap mengurus dirinya di
     * tab Pembayaran / Belum Proses, bukan di sini.
     *
     * Angka chip "unit terikat nota sewa" di halaman Produk sengaja TIDAK ikut
     * menyempit: kolom itu menghitung unit yang hilang dari "Tersedia", dan unit
     * sewa memang sudah diikat sejak notasnya ditulis (StokModel::sedangDisewa).
     * Karena itu bagian ini bisa memuat lebih sedikit nota daripada angka chip -
     * bukan bug, tapi pilihan: yang di sini hanya sewa yang sudah ada uangnya.
     *
     * Yang boleh MENekan tombol "Sudah kembali" aturannya sendiri dan TIDAK ikut
     * klausul ini: lihat masihDisewa(), yang hanya menuntut barang diserahkan dan
     * lunas kalau nota punya unit stok. Memisahkan isi tab dari gerbang tombol
     * adalah pilihan sadar — tab menjawab "sewa mana yang belum pulang", tombol
     * menjawab "boleh dicatat pulang sekarang tidak".
     *
     * $tabel adalah nama tabel invoice lengkap dengan prefix-nya, karena
     * klausul ini dipakai pada where mentah yang tidak di-escape builder.
     */
    private static function klausulMasihDisewa(string $tabel): string
    {
        return self::klausulSewaMenunggu($tabel)
            . " AND {$tabel}.status_pesanan <> '3'"
            . ' AND ' . self::klausulDanaMasuk($tabel);
    }

    /**
     * Klausul bagian "Sudah Kembali": nota sewa yang pengembaliannya sudah
     * dicatat. Scope-nya sama persis dengan klausulMasihDisewa(), hanya penanda
     * kepulangannya yang dibalik, jadi kedua bagian itu adalah dua potong dari
     * isi tab Disewa yang sama — bukan dua aturan yang berbeda.
     *
     * Disebut di sini juga supaya tab Selesai bisa membuangnya: nota sewa yang
     * sudah pulang punya riwayatnya sendiri di tab Disewa, tidak dihitung dua kali.
     */
    public static function klausulSewaKembali(string $tabel): string
    {
        return self::klausulSewaSelesai($tabel)
            . " AND {$tabel}.status_pesanan <> '3'"
            . ' AND ' . self::klausulDanaMasuk($tabel);
    }

    /**
     * Nota sewa yang pengembaliannya sudah dicatat, tanpa syarat apa pun selain
     * itu. Pasangannya klausulSewaMenunggu().
     */
    public static function klausulSewaSelesai(string $tabel): string
    {
        return "COALESCE(JSON_UNQUOTE(JSON_EXTRACT({$tabel}.rincian, '$.tipe')), '') = 'sewa'"
            . " AND {$tabel}.sewa_kembali_at IS NOT NULL";
    }

    /**
     * Status pembayaran yang dihitung "dananya sudah masuk": DP yang menunggu
     * konfirmasi (3), sedang dicicil (4), dan lunas (5/6). Satu daftar untuk SQL
     * dan PHP supaya tab dan angka di layar tidak bisa berbeda tafsir.
     */
    public const DANA_MASUK = ['3', '4', '5', '6'];

    /** Pasangannya DANA_MASUK dalam SQL mentah. */
    public static function klausulDanaMasuk(string $tabel): string
    {
        return $tabel . ".status_pembayaran IN ('" . implode("', '", self::DANA_MASUK) . "')";
    }

    /** Status pembayaran yang dihitung lunas; satu daftar untuk SQL dan PHP. */
    public const LUNAS = ['5', '6'];

    /** Klausul lunas dalam SQL mentah; pasangannya sudahLunas() di PHP. */
    public static function klausulLunas(string $tabel): string
    {
        return $tabel . ".status_pembayaran IN ('" . implode("', '", self::LUNAS) . "')";
    }

    /**
     * Dana nota ini sudah sah masuk? Yang ditanya di sini hanya keadaan bayar,
     * bukan besarnya uang: dipakai untuk memutuskan unit sewa sedang dipakai.
     *
     * @param object|array|null $nota baris invoice
     */
    public static function sudahLunas($nota): bool
    {
        $status = (string) (is_array($nota) ? ($nota['status_pembayaran'] ?? '') : ($nota->status_pembayaran ?? ''));

        return in_array($status, self::LUNAS, true);
    }

    /**
     * Klausul SQL "nota sewa yang belum dikonfirmasi kembali". Sepasang dengan
     * adalahSewa() dan sewaMenunggu() di PHP: tanggal `rincian.kembali` hanya
     * jadual, yang memutuskan barangnya benar-benar pulang adalah orang yang
     * menekan tombol "Sudah kembali" di nota.
     *
     * JSON_EXTRACT atas rincian yang NULL memberi hasil NULL, dan NULL ikut
     * lolos dari NOT (...) — tanpa COALESCE, negasi klausul ini di tab Selesai
     * akan membuang semua nota lama yang tidak punya rincian.
     */
    public static function klausulSewaMenunggu(string $tabel): string
    {
        return "COALESCE(JSON_UNQUOTE(JSON_EXTRACT({$tabel}.rincian, '$.tipe')), '') = 'sewa'"
            . " AND {$tabel}.sewa_kembali_at IS NULL";
    }

    /**
     * Nota bertipe sewa? Nota lama yang kolom rinciannya kosong (dan JSON rusak)
     * dihitung bukan sewa, jadi perilakunya tidak berubah untuk mereka.
     *
     * @param string|array|null $rincian isi kolom rincian, boleh sudah di-decode
     */
    public static function adalahSewa($rincian): bool
    {
        $isi = is_array($rincian) ? $rincian : json_decode((string) $rincian, true);

        return is_array($isi) && (string) ($isi['tipe'] ?? '') === 'sewa';
    }

    /**
     * Barang sewaan masih di tangan penyewa? Bukan sewa selalu false: kolom
     * penandanya hanya berarti untuk nota sewa.
     */
    public static function sewaMenunggu(object $nota): bool
    {
        return self::adalahSewa($nota->rincian ?? null) && (int) ($nota->sewa_kembali_at ?? 0) === 0;
    }

    /**
     * Gerbang tombol "Sudah kembali" — lebih ketat dari isi tab Disewa
     * (klausulMasihDisewa): nota yang baru diikat DP boleh muncul di tab, tapi
     * tombolnya hidup hanya kalau pengembaliannya memang boleh dicatat.
     *
     * Nota yang barangnya ditautkan ke unit stok memakai aturan lama: barang
     * sudah diserahkan (status_pengiriman '3') dan pembayaran lunas, karena
     * kepulangannya memindahkan unit di kolam sewa.
     *
     * Nota yang seluruh barangnya ditulis manual — pakaian custom yang tidak
     * ada di stok — tidak punya unit sewa yang bisa diserahterimakan lewat
     * packing/resi, jadi aturan itu tidak pernah bisa tercapai dan tombolnya
     * mati permanen. Untuk mereka penanda kepulangan boleh ditulis kapan saja.
     *
     * @param bool $terikat_stok ada baris orderan yang ditautkan ke order_stok
     */
    public static function masihDisewa(object $nota, bool $terikat_stok): bool
    {
        if (! self::sewaMenunggu($nota)) {
            return false;
        }

        if ((string) ($nota->status_pesanan ?? '') === '3') {
            return false;
        }

        if (! $terikat_stok) {
            return true;
        }

        return (string) ($nota->status_pengiriman ?? '') === '3'
            && self::sudahLunas($nota);
    }

    /**
     * Kenapa tombolnya masih mati, untuk ditulis di tooltip. null = boleh ditekan.
     * Urutan alasannya sama dengan masihDisewa(): batal lebih dulu, lalu bagi
     * barang berstok — belum diserahkan, belum dibayar.
     *
     * @param bool $terikat_stok ada baris orderan yang ditautkan ke order_stok
     */
    public static function alasanBelumDisewa(object $nota, bool $terikat_stok): ?string
    {
        if (! self::sewaMenunggu($nota)) {
            return null;
        }

        if ((string) ($nota->status_pesanan ?? '') === '3') {
            return 'Nota ini dibatalkan, pengembaliannya tidak dicatat lagi.';
        }

        if (! $terikat_stok) {
            return null;
        }

        if ((string) ($nota->status_pengiriman ?? '') !== '3') {
            return 'Belum bisa dicatat kembali: barangnya belum diserahkan ke penyewa.';
        }

        if (! self::sudahLunas($nota)) {
            return 'Belum bisa dicatat kembali: pembayaran nota ini belum lunas.';
        }

        return null;
    }

    /**
     * Ada item nota yang ditautkan ke unit stok? Item yang ditulis manual
     * menjawab false (order_dibeli.stok_id NULL), dan itu yang membedakan sewa
     * yang angka kolam sewanya bergerak dari sewa yang murni catatan tangan.
     *
     * Daftar transaksi sudah memuat baris `barang` per nota, jadi layar tidak
     * perlu memanggil ini; ia hanya untuk jalur yang memegang satu nota saja,
     * misal pencatatan pengembalian.
     */
    public function terikatStok(int $invoice_id): bool
    {
        return (new BarangDibeli())
                ->where('invoice_id', $invoice_id)
                ->where('stok_id IS NOT NULL', null, false)
                ->countAllResults() > 0;
    }

    /**
     * Catat pengembalian barang sewaan. `$tanggal` boleh diisi kalau petugas
     * mencatat kepulangan yang terjadi hari sebelumnya; kosong = sekarang.
     *
     * Stok kolam sewa dan tab Selesai membaca penanda ini, jadi tidak ada angka
     * lain yang perlu dirapikan dari sini.
     *
     * @return array{ok:bool, pesan:string, waktu:?int}
     */
    public function catatKembali(int $invoice_id, int $oleh, ?string $tanggal = null): array
    {
        $nota = $this->find($invoice_id);

        if ($nota === null) {
            return ['ok' => false, 'pesan' => 'Orderan tidak ditemukan.', 'waktu' => null];
        }

        if (! self::adalahSewa($nota->rincian ?? null)) {
            return ['ok' => false, 'pesan' => 'Hanya orderan sewa yang punya pencatatan pengembalian.', 'waktu' => null];
        }

        // penanda ini adalah pintu masuk bagian "Sudah Kembali", stok kolam sewa
        // ikut pulih, dan poin diberikan, jadi tidak boleh diisi untuk nota yang
        // penyewaannya belum sah berjalan — sama seperti tombolnya di daftar
        $terikat_stok = $this->terikatStok($invoice_id);

        if (! self::masihDisewa($nota, $terikat_stok)) {
            return [
                'ok'    => false,
                'pesan' => self::alasanBelumDisewa($nota, $terikat_stok) ?? 'Nota ini tidak sedang disewa.',
                'waktu' => null,
            ];
        }

        $waktu = $this->waktuKembali($tanggal);

        if ($waktu === null) {
            return ['ok' => false, 'pesan' => 'Tanggal pengembalian tidak dikenali atau masih di masa depan.', 'waktu' => null];
        }

        $this->update($invoice_id, [
            'sewa_kembali_at'   => $waktu,
            'sewa_kembali_oleh' => $oleh > 0 ? $oleh : null,
        ]);

        return [
            'ok'    => true,
            'pesan' => 'Barang sewaan dicatat kembali pada ' . date('d/m/Y H:i', $waktu) . '.',
            'waktu' => $waktu,
        ];
    }

    /**
     * Tarik kembali pencatatan pengembalian: barang dianggap masih disewa.
     * Dipakai kalau tombolnya kelepasan diklik — poin dan stok ikut mundur
     * sendiri karena keduanya membaca penanda yang sama.
     *
     * @return array{ok:bool, pesan:string, waktu:?int}
     */
    public function batalkanKembali(int $invoice_id): array
    {
        $nota = $this->find($invoice_id);

        if ($nota === null) {
            return ['ok' => false, 'pesan' => 'Orderan tidak ditemukan.', 'waktu' => null];
        }

        if (! self::adalahSewa($nota->rincian ?? null)) {
            return ['ok' => false, 'pesan' => 'Hanya orderan sewa yang punya pencatatan pengembalian.', 'waktu' => null];
        }

        if ((int) ($nota->sewa_kembali_at ?? 0) === 0) {
            return ['ok' => false, 'pesan' => 'Nota ini memang belum dicatat kembali.', 'waktu' => null];
        }

        $this->update($invoice_id, ['sewa_kembali_at' => null, 'sewa_kembali_oleh' => null]);

        return ['ok' => true, 'pesan' => 'Pengembalian dibatalkan; nota kembali ke bagian Masih Disewa.', 'waktu' => null];
    }

    /**
     * Waktu pencatatan: kosong berarti sekarang, teks tanggal dibaca kalau memang
     * tanggal penuh (pengembalian tidak dicatat dari masa depan).
     */
    private function waktuKembali(?string $tanggal): ?int
    {
        $teks = trim((string) $tanggal);

        if ($teks === '') {
            return time();
        }

        $waktu = strtotime($teks);

        if ($waktu === false || $waktu > time()) {
            return null;
        }

        return $waktu;
    }

    /**
     * Batasi query pada daftar toko yang boleh dibuka akun ini.
     * null = tidak membatasi, sama seperti sebelum pagar kepemilikan ada.
     *
     * @param \CodeIgniter\Model|\CodeIgniter\Database\BaseBuilder $builder
     * @param array<int>|null                                      $hanya
     */
    private function batasiToko($builder, ?array $hanya): void
    {
        if ($hanya === null) {
            return;
        }

        $ids = array_values(array_filter(array_map('intval', $hanya)));

        $builder->whereIn('juragan_id', $ids === [] ? [0] : $ids);
    }

    /**
     * Pembangun query untuk satu saringan, dibatasi pada daftar juragan pemakai.
     *
     * @param array<int> $juragan_ids
     *
     * @return \CodeIgniter\Database\BaseBuilder
     */
    private function pembangunSaring(array $juragan_ids, string $slug)
    {
        $ids = array_values(array_filter(array_map('intval', $juragan_ids)));

        $builder = $this->db->table('invoice');
        $builder->select('id_invoice');
        $builder->where('deleted_at', null);
        $builder->whereIn('juragan_id', $ids === [] ? [0] : $ids);

        $this->terapkanSaring($builder, $slug);

        return $builder;
    }

    /**
     * Jumlah orderan untuk satu saringan pada kartu/panel dasbor.
     *
     * Dipakai lewat model yang sama dengan daftar transaksinya, jadi angka
     * kartu dan halaman yang dibuka dari kartu itu tidak bisa berbeda lagi.
     *
     * @param array<int> $juragan_ids
     */
    public function hitungSaring(array $juragan_ids, string $slug): int
    {
        return $this->pembangunSaring($juragan_ids, $slug)->countAllResults();
    }

    /**
     * Sebaran tahap produksi tertinggi dari satu saringan orderan untuk blok
     * Ringkasan Produksi. Tahap 0 berarti orderan belum masuk antrean kerja.
     *
     * @param array<int> $juragan_ids
     *
     * @return array<int, int> nomor tahap => jumlah orderan
     */
    public function sebaranTahap(array $juragan_ids, string $slug = 'aktif'): array
    {
        $status = $this->db->prefixTable('invoice_status');
        $dasar  = $this->pembangunSaring($juragan_ids, $slug)->getCompiledSelect(false);

        $baris = $this->db->query('SELECT IFNULL(s.tahap, 0) AS tahap, COUNT(*) AS jumlah'
            . ' FROM (' . $dasar . ') o'
            . ' LEFT JOIN (SELECT invoice_id, MAX(CAST(status AS UNSIGNED)) AS tahap'
            . ' FROM `' . $status . '` WHERE tanggal_masuk IS NOT NULL GROUP BY invoice_id) s'
            . ' ON s.invoice_id = o.id_invoice'
            . ' GROUP BY IFNULL(s.tahap, 0) ORDER BY tahap ASC')->getResultArray();

        $sebaran = [];

        foreach ($baris as $b) {
            $sebaran[(int) $b['tahap']] = (int) $b['jumlah'];
        }

        return $sebaran;
    }

    public function getCount(int $id_juragan = 0, ?array $hanya = null): array
    {
        helper('fungsi');
        $countSemua       = $this->counts($id_juragan, 'semua', $hanya);
        $countSelesai     = $this->counts($id_juragan, 'selesai', $hanya);
        $countBelum       = $this->counts($id_juragan, 'belum-proses', $hanya);
        $countCekBayar    = $this->counts($id_juragan, 'cek-bayar', $hanya);
        $countDalamProses = $this->counts($id_juragan, 'dalam-proses', $hanya);
        $countMasihDisewa = $this->counts($id_juragan, 'masih-disewa', $hanya);
        $countSudahKembali = $this->counts($id_juragan, 'sudah-kembali', $hanya);

        return [
            'semua' => [
                'num'  => $countSemua,
                'text' => formatRibuan($countSemua),

            ],
            'selesai' => [
                'num'  => $countSelesai,
                'text' => formatRibuan($countSelesai),

            ],
            'belum_proses' => [
                'num'  => $countBelum,
                'text' => formatRibuan($countBelum),

            ],
            'cek_bayar' => [
                'num'  => $countCekBayar,
                'text' => formatRibuan($countCekBayar),

            ],
            'dalam_proses' => [
                'num'  => $countDalamProses,
                'text' => formatRibuan($countDalamProses),

            ],
            'masih_disewa' => [
                'num'  => $countMasihDisewa,
                'text' => formatRibuan($countMasihDisewa),

            ],
            'sudah_disewa' => [
                'num'  => $countSudahKembali,
                'text' => formatRibuan($countSudahKembali),

            ],
        ];
    }

    private function counts(int $id_juragan, string $output = 'semua', ?array $hanya = null): int
    {
        $q = $this->join('juragan j', 'j.id_juragan = invoice.juragan_id');

        if ($id_juragan > 0) {
            $q->where(['juragan_id' => $id_juragan]);
        }

        $this->batasiToko($q, $hanya);

        switch ($output) {
            case 'selesai':
                $q->where('invoice.status_pengiriman', '3');

                // sama dengan terapkanTab(): sewa yang barangnya belum kembali
                // belum berhak masuk Selesai, dan yang sudah kembali punya
                // tempatnya sendiri di tab Disewa — tidak dihitung dua kali
                $tabel = $this->db->prefixTable('invoice');
                $q->where('NOT (' . self::klausulSewaMenunggu($tabel) . ')', null, false);
                $q->where('NOT (' . self::klausulSewaKembali($tabel) . ')', null, false);

                break;

            case 'belum-proses':
                $q->where('invoice.status_pesanan', '1');

                break;

            case 'cek-bayar':
                $q->whereIn('invoice.status_pembayaran', ['2', '3']);

                break;

            case 'dalam-proses':
                $q->where('invoice.status_pesanan', '2');
                $q->whereNotIn('invoice.status_pengiriman', ['3']);

                break;

            case 'masih-disewa':
                // soft delete sudah disaring otomatis oleh model (getCount hanya
                // dipanggil lewat builder model ini), sisanya satu klausul mentah
                $q->where(self::klausulMasihDisewa($this->db->prefixTable('invoice')), null, false);

                break;

            case 'sudah-kembali':
                // bagian kedua tab Disewa: klausul yang sama, penanda pulang dibalik
                $q->where(self::klausulSewaKembali($this->db->prefixTable('invoice')), null, false);

                break;
        }

        return $q->countAllResults();
    }

    /**
     * Ambil ID dari Invoice berdasarkan kata kunci
     *
     * @param mixed $cari kata pencarian
     *
     * @return array list ID Invoice
     */
    /**
     * Satu nilai pencarian yang datang mentah dari GET, sudah jadi string.
     *
     * Array dikirim lewat `cari[q][]=x`; kalau diteruskan ke builder nilai itu
     * membuat TypeError (halaman mati). Di sini cukup sekali disaring.
     */
    private static function teksCari(array $cari, string $kunci): string
    {
        $nilai = $cari[$kunci] ?? '';

        return is_scalar($nilai) ? trim((string) $nilai) : '';
    }

    private function ambilIds($cari): array
    {
        $this->select('invoice.id_invoice');

        $this->join('pelanggan p', 'p.id_pelanggan=invoice.pemesan_id');
        $this->join('pelanggan k', 'k.id_pelanggan=invoice.kirimKepada_id');
        // orderan yang belum punya satu pun baris produk tetap harus muncul di
        // daftar (LEFT, bukan INNER), kalau tidak ia tidak bisa dibuka sama sekali
        $this->join('dibeli b', 'b.invoice_id=invoice.id_invoice', 'left');

        if (! empty($cari) && is_array($cari)) {
            $kata = self::teksCari($cari, 'q');

            $status_pembayaran = self::teksCari($cari, 'pembayaran');

            if ($status_pembayaran !== '') {
                if ($status_pembayaran === '2A') {
                    $this->groupStart()
                        ->where('invoice.status_pembayaran', '2')
                        ->orWhere('invoice.status_pembayaran', '3')
                        ->groupEnd();
                } else {
                    $this->where('invoice.status_pembayaran', $status_pembayaran);
                }
            }

            $status_pengiriman = self::teksCari($cari, 'pengiriman');

            if ($status_pengiriman !== '') {
                if ($status_pengiriman === '2A') {
                    $this->groupStart()
                        ->where('invoice.status_pengiriman', '2')
                        ->orWhere('invoice.status_pengiriman', '3')
                        ->groupEnd();
                } else {
                    $this->where('invoice.status_pengiriman', $status_pengiriman);
                }
            }

            $status_orderan = self::teksCari($cari, 'orderan');

            if ($status_orderan !== '') {
                $this->where('invoice.status_pesanan', $status_orderan);
            }

            if (! empty($cari['kolom'])) {
                switch ($cari['kolom']) {
                    case 'id':
                        $this->where('invoice.id_invoice', $kata);
                        break;

                    case 'faktur':
                        $this->where('invoice.seri', $kata);
                        break;

                    case 'nama':
                        $this->groupStart()
                            ->orLike('p.nama_pelanggan', $kata, 'both')
                            ->orLike('k.nama_pelanggan', $kata, 'both')
                            ->groupEnd();
                        break;

                    case 'hp':
                        $this->groupStart()
                            ->orLike('p.hp', $kata, 'both')
                            ->orLike('k.hp', $kata, 'both')
                            ->groupEnd();
                        break;

                    case 'kode':
                        $this->like('b.kode', $kata, 'both');
                        break;

                    case 'tanggal_pesan':
                        // DATE(CONVERT_TZ(FROM_UNIXTIME(tanggal_dibuat, '%Y-%m-%d %H:%i:%s'), '+00:00', '+00:00'))='".$cari['tanggal']."'
                        $this->where('invoice.tanggal_pesan', $kata);
                        break;
                }
            } else {
                // kode dicocokkan dengan kalimat utuh, kolom sisanya dengan tiap kata
                $this->groupStart()->orLike('b.kode', $kata, 'both');
                preg_match_all('/%22(?:\\\\.|(?!%22).)*%22|\S+/', $kata, $matches);

                $term = '';

                foreach ($matches[0] as $term) {
                    $term = trim($term);
                    if (! empty($term)) {
                        $term = str_replace('"', '', $term);
                        $this->orLike('invoice.id_invoice', $term, 'both')
                            ->orLike('invoice.seri', $term, 'both')
                            ->orLike('p.nama_pelanggan', $term, 'both')
                            ->orLike('k.nama_pelanggan', $term, 'both')
                            ->orLike('p.hp', $term, 'both')
                            ->orLike('k.hp', $term, 'both')
                            ->orLike('p.alamat', $term, 'both')
                            ->orLike('k.alamat', $term, 'both')
                            ->orLike('invoice.keterangan', $term, 'both');
                    }
                }

                $this->groupEnd();
            }
        }
        $return = [];

        foreach ($this->findAll() as $inv) {
            $return[] = $inv->id_invoice;
        }

        return $return;
    }

    /**
     * Ringkasan uang satu invoice: barang, lain-lain, sudah terbayar, dan yang belum dicek.
     */
    public function total_biaya($invoice_id)
    {
        $id = (int) $invoice_id;

        // masing-masing tabel dijumlah sendiri; digabung lewat JOIN malah membuat nilai
        // yang sama persis ikut terhapus oleh SUM(DISTINCT ...)
        $barang = $this->db->table('dibeli')
            ->select('IFNULL(SUM(harga * qty), 0) AS n', false)
            ->where('invoice_id', $id)
            ->getCompiledSelect();

        $lain = $this->db->table('biaya')
            ->select('IFNULL(SUM(nominal), 0) AS n', false)
            ->where('invoice_id', $id)
            ->getCompiledSelect();

        $terbayar = $this->db->table('pembayaran')
            ->select('IFNULL(SUM(total_pembayaran), 0) AS n', false)
            ->where('invoice_id', $id)
            ->where('status', '3')
            ->getCompiledSelect();

        $belumcek = $this->db->table('pembayaran')
            ->select('IFNULL(SUM(total_pembayaran), 0) AS n', false)
            ->where('invoice_id', $id)
            ->where('status', '1')
            ->getCompiledSelect();

        return $this->db->query("SELECT ({$barang}) AS barang, ({$lain}) AS lain, ({$terbayar}) AS terbayar, ({$belumcek}) AS belumcek");
    }

    /**
     * Aturan status pembayaran, hanya dari angka: 1 belum bayar, 2 menunggu konfirmasi
     * (belum ada dana masuk), 3 menunggu konfirmasi (kredit), 4 dicicil, 5 lunas ada kelebihan, 6 lunas.
     */
    public static function statusPembayaran(int $total, int $terbayar, int $belumcek): string
    {
        if ($belumcek > 0) {
            return $terbayar > 0 ? '3' : '2';
        }
        if ($terbayar === 0) {
            return '1';
        }
        if ($terbayar === $total) {
            return '6';
        }

        return $terbayar < $total ? '4' : '5';
    }

    /**
     * Hitung lalu simpan status_pembayaran satu invoice.
     */
    public function perbaruiStatusPembayaran($invoice_id): string
    {
        $cek    = $this->total_biaya($invoice_id)->getResult()[0];
        $status = self::statusPembayaran(
            (int) $cek->barang + (int) $cek->lain,
            (int) $cek->terbayar,
            (int) $cek->belumcek
        );

        $this->save([
            'id_invoice'        => $invoice_id,
            'status_pembayaran' => $status,
        ]);

        // nota yang baru lunas menyumbang poin gaji, yang balik belum lunas
        // dicabut lagi; sinkron() memutuskan sendiri arah yang benar.
        (new PoinPenjualanModel())->sinkron((int) $invoice_id);

        // reminder transaksi (konfirmasi DP, pelunasan, permintaan ulasan) ikut
        // disinkronkan di titik yang sama seperti poin gaji: begitu angka uang
        // berubah, tugasnya mengikuti. Gagal di sini tidak boleh membatalkan
        // pembayaran, jadi dibungkus dan hanya dilaporkan.
        try {
            (new \App\Models\TugasModel())->sinkronReminder((int) $invoice_id);
        } catch (Throwable $e) {
            log_message('error', 'sinkron reminder gagal untuk nota ' . $invoice_id . ': ' . $e->getMessage());
        }

        return $status;
    }

    public function status($invoice_id)
    {
        $status = $this->db->table('invoice_status s');
        $status->where('s.invoice_id', $invoice_id);
        $status->orderBy('s.id_status', 'ASC');

        return $status->get();
    }

    public function jumlah_produk($invoice_id)
    {
        $db      = \Config\Database::connect();
        $builder = $this->db->table('pengiriman p');

        $builder->select('(SELECT SUM(p.qty_kirim) FROM ' . $db->prefixTable('pengiriman') . ' p WHERE p.invoice_id=' . $invoice_id . ') AS sudah_kirim', false);

        $builder->select('(SELECT SUM(d.qty) FROM ' . $db->prefixTable('dibeli') . ' d WHERE d.invoice_id=' . $invoice_id . ') AS wajib_kirim', false);

        return $builder->get();
    }

    public function counter_terkirim()
    {
        $awalBulanIni  = tanggalDefault('mulai');
        $akhirBulanIni = tanggalDefault('akhir');

        $awalBulanLalu  = satuBulanSebelumnya($awalBulanIni);
        $akhirBulanLalu = satuBulanSebelumnya($akhirBulanIni);

        $counter = $this->db->table('juragan j');
        $counter->select('j.*');

        $counter->select('COUNT(DISTINCT(CASE WHEN i.deleted_at IS NULL AND i.tanggal_pesan between "' . $awalBulanIni . '" AND "' . $akhirBulanIni . '" THEN i.id_invoice END)) as jumlahOrderBulanIni');

        $counter->select('COUNT(DISTINCT(CASE WHEN i.deleted_at IS NULL AND i.tanggal_pesan between "' . $awalBulanLalu . '" AND "' . $akhirBulanLalu . '" THEN i.id_invoice END)) as jumlahOrderBulanLalu');

        $counter->select('SUM(CASE WHEN i.deleted_at IS NULL AND i.status_pengiriman = "3" AND DATE_FORMAT(FROM_UNIXTIME(s.tanggal_kirim), "%Y-%m-%d") between "' . $awalBulanIni . '" AND "' . $akhirBulanIni . '" THEN s.qty_kirim ELSE 0 END) as terkirimBulanIni');

        $counter->select('SUM(CASE WHEN i.deleted_at IS NULL AND i.status_pengiriman = "3" AND DATE_FORMAT(FROM_UNIXTIME(s.tanggal_kirim), "%Y-%m-%d") between "' . $awalBulanLalu . '" AND "' . $akhirBulanLalu . '" THEN s.qty_kirim ELSE 0 END) as terkirimBulanLalu');

        $counter->join('invoice i', 'j.id_juragan = i.juragan_id', 'left outer');
        $counter->join('dibeli b', 'b.invoice_id = i.id_invoice', 'left outer');
        $counter->join('pengiriman s', 's.invoice_id = i.id_invoice', 'left outer');

        // toko yang sudah dihapus tidak boleh ikut muncul di chart
        $counter->where('j.deleted_at', null);

        $counter->groupBy('j.id_juragan');

        return $counter->get();
    }

    /**
     * Klausul orderan yang berhak jadi "Reminder Penjahit": bertipe pembuatan/jual
     * (bukan sewa), punya deadline, dan masih hidup — belum selesai (belum dikirim)
     * dan belum dibatalkan. Deadline yang sudah lewat tetap ikut, supaya nota yang
     * terlewat tidak diam-diam hilang dari daftar.
     *
     * $tabel nama tabel invoice lengkap dengan prefix (dipakai pada where mentah).
     */
    private static function klausulPengingatPenjahit(string $tabel): string
    {
        return "COALESCE(JSON_UNQUOTE(JSON_EXTRACT({$tabel}.rincian, '$.tipe')), '') <> 'sewa'"
            . " AND {$tabel}.deadline IS NOT NULL"
            . " AND {$tabel}.status_pengiriman <> '3'"
            . " AND {$tabel}.status_pesanan <> '3'";
    }

    /**
     * Daftar orderan yang deadline-nya jatuh dalam H hari ke depan (termasuk yang
     * sudah lewat). Urut dari deadline paling mepet.
     *
     * @param list<int> $hanya id juragan yang boleh dibaca akun ini
     * @return array<int, array<string, mixed>>
     */
    public function pengingatPenjahit(array $hanya, int $hari, int $limit = 0, int $offset = 0, ?bool $dikirim = null): array
    {
        $tabel = $this->db->prefixTable('invoice');
        $batas = date('Y-m-d', strtotime('today +' . max(0, $hari) . ' days'));

        $builder = $this->db->table('invoice')
            ->select('invoice.id_invoice, invoice.seri, invoice.deadline, invoice.status_pesanan,'
                . ' invoice.status_pembayaran, invoice.pemesan_id, invoice.juragan_id, invoice.user_id,'
                . ' invoice.keterangan, j.nama_juragan, pl.nama_pelanggan')
            ->join('juragan j', 'j.id_juragan = invoice.juragan_id', 'left')
            ->join('pelanggan pl', 'pl.id_pelanggan = invoice.pemesan_id', 'left')
            ->join('reminder_penjahit_dikirim rd', 'rd.id_invoice = invoice.id_invoice', 'left')
            ->where('invoice.deleted_at', null)
            ->where(self::klausulPengingatPenjahit($tabel), null, false)
            ->where('invoice.deadline <=', $batas)
            ->orderBy('invoice.deadline', 'ASC')
            ->orderBy('invoice.id_invoice', 'ASC');

        // $dikirim: null = semua, true = hanya yang sudah ditandai, false = belum
        if ($dikirim === true) {
            $builder->where('rd.id_dikirim IS NOT NULL', null, false);
        } elseif ($dikirim === false) {
            $builder->where('rd.id_dikirim IS NULL', null, false);
        }

        $this->batasiToko($builder, $hanya);

        if ($limit > 0) {
            $builder->limit($limit, $offset);
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Jumlah reminder aktif — untuk badge di menu sidebar.
     *
     * @param list<int> $hanya
     */
    public function hitungPengingatPenjahit(array $hanya, int $hari): int
    {
        $tabel = $this->db->prefixTable('invoice');
        $batas = date('Y-m-d', strtotime('today +' . max(0, $hari) . ' days'));

        $builder = $this->db->table('invoice')
            ->join('reminder_penjahit_dikirim rd', 'rd.id_invoice = invoice.id_invoice', 'left')
            ->where('invoice.deleted_at', null)
            ->where(self::klausulPengingatPenjahit($tabel), null, false)
            ->where('invoice.deadline <=', $batas)
            // badge hanya menghitung yang belum diingatkan (masih perlu tindakan)
            ->where('rd.id_dikirim IS NULL', null, false);

        $this->batasiToko($builder, $hanya);

        return (int) $builder->countAllResults();
    }
}
