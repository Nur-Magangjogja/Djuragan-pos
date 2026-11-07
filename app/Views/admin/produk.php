<?php
/**
 * Variabel di bawah dilempar Admin\Produk::index() ke view() lalu di-extract()
 * oleh CodeIgniter saat runtime, jadi tidak ada penugasan yang bisa dilihat
 * Intelephense di file ini; blok @var hanya memberi tahu tipenya.
 *
 * @var array<int, array<string, mixed>> $katalog   Kartu produk beserta daftar varian tiap kartu (StokModel::katalog)
 * @var array<int, list<array<string, mixed>>> $fotonya Gabungan foto varian, dikunci urutan kartu (ProdukFotoModel::perKartu)
 * @var array<string, int> $ringkas   Ringkasan stok: varian, unit, nilai, menipis, habis, belum berpoin
 * @var int $juragan      Filter toko terpilih, 0 artinya semua toko
 * @var array<int, string> $juragans  Pilihan toko pada filter (BaseController::juraganPilihan)
 * @var string $cakupan   Toko yang sedang dibaca, dipakai pesan daftar kosong
 * @var int $disewa       Unit sewa yang sedang di tangan penyewa pada cakupan aktif (StokModel::sedangDisewa)
 * @var string $tautan_disewa Alamat tab Disewa (bagian Masih Disewa) di daftar transaksi untuk cakupan aktif
 * @var string $cari         Kata kunci pencarian yang sedang dipakai
 * @var string $keadaan   Filter keadaan stok yang sedang dipakai
 * @var string $kolam      Kolam tab aktif: 'jual' atau 'sewa' (StokModel::kolam)
 * @var array<string, string> $kolams  Label tab per kolam (StokModel::KOLAM)
 * @var array<int, array<string, string>> $ukuran Master ukuran: kode, label, kelompok (StokModel::daftarUkuran)
 * @var array<int, array<string, string>> $kode   Master kode barang: kolom kode (StokModel::daftarKode)
 * @var int $total        Jumlah kartu hasil penyaringan
 * @var int $limit        Kartu per halaman
 * @var int $page         Nomor halaman aktif
 * @var ?string $sukses    Flash message berhasil, null saat tidak ada
 * @var ?string $gagal     Flash message gagal, null saat tidak ada
 * @var array<string, string> $errors Validasi flashdata: kolom => pesan
 * @var int $ambangnya   Batas stok disebut menipis (StokModel::AMBANG_MENIPIS)
 * @var bool $bacaSaja    CS hanya membaca, tombol ubah/hapus disembunyikan
 * @var bool $bisaPoin    Superadmin boleh menata poin varian
 */

$pager = \Config\Services::pager();

// error datang dari flashdata controller: ['kode' => 'pesan', ...]
$err = static fn (string $kolom): ?string => isset($errors[$kolom]) ? (string) $errors[$kolom] : null;

// Hanya modal tambah/ubah yang memakai withInput(), jadi isian lama menandai
// modal mana yang harus dibuka lagi setelah gagal. id_stok hanya ada pada
// kiriman modal ubah.
$pernahIsi   = old('kode') !== null || old('id_stok') !== null;
$dariSunting = old('id_stok') !== null;

$sunting_err = $pernahIsi && $dariSunting;
$tambah_err  = $pernahIsi && ! $dariSunting;

// Kode, harga dan stok memakai nama field yang sama di kedua modal, jadi
// pesannya hanya ditempelkan ke modal yang mengirimkannya.
$err_sunting = static fn (string $kolom): ?string => $sunting_err ? $err($kolom) : null;
$err_tambah  = static fn (string $kolom): ?string => $tambah_err ? $err($kolom) : null;

// harga ditulis penuh (Rp 120.000), bukan ringkas: formatRibuan() milik laporan
// dan nota memotong jadi 120K sehingga kolomnya tidak pernah muat penuh
$rupiah = static fn (int $n): string => 'Rp ' . number_format($n, 0, ',', '.');

// badge warna untuk keadaan stok satu varian
$keadaan_baris = static function (int $stok) use ($ambangnya): array {
    if ($stok <= 0) {
        return ['danger', 'Habis'];
    }

    return $stok <= $ambangnya ? ['warning', 'Menipis'] : ['success', 'Aman'];
};

// data satu varian untuk modal sunting; esc() yang mengemas atributnya.
// Harga dan stok baris memang angka kolamnya sendiri, karena satu varian
// hanya hidup di satu kolam.
$barang = static function (array $b): array {
    return [
        'id'         => (int) $b['id_stok'],
        'kode'       => (string) $b['kode'],
        'ukuran'     => (string) ($b['ukuran'] ?? ''),
        'harga'      => (int) $b['harga'],
        'stok'       => (int) $b['stok'],
        'keterangan' => (string) ($b['keterangan'] ?? ''),
        'juragan'    => (string) $b['nama_juragan'],
        'juragan_id' => (int) $b['juragan_id'],
    ];
};

// kartu utuh untuk tombol Edit: pemilih varian diisi dari daftar ini
$kartu_barang = static function (array $p) use ($barang): string {
    return (string) esc(json_encode([
        'kode'       => $p['kode'],
        'juragan_id' => (int) $p['juragan_id'],
        'nama'       => (string) $p['nama_juragan'],
        'poin'       => (int) ($p['poin'] ?? 0),
        'varian'     => array_map($barang, $p['varian']),
    ]));
};

// huruf pertama nama barang, dipakai waktu foto produk belum ada
$inisial = static fn (string $kode): string => mb_strtoupper(mb_substr(trim($kode), 0, 2));

// satu kata untuk menyebut kolam yang sedang dibuka: "jual" atau "sewa"
$kataKolam = $kolam === 'sewa' ? 'sewa' : 'jual';

// tautan tab: saringan yang sedang dipakai ikut dibawa, hanya kolamnya yang ganti
$kueriTab = array_filter([
    'juragan' => $juragan > 0 ? (string) $juragan : '',
    'keadaan' => $keadaan,
    'cari'    => $cari,
], static fn (string $v): bool => $v !== '');
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    /* galeri thumbnail di modal detail: dulu flex-wrap biasa, jadi baris terakhir
       yang isinya sedikit tidak ikut menyusut dan kotaknya terlihat lebih besar
       dari baris di atasnya; grid membuat tiap kolom sama lebar dan barisnya
       sejajar, dan aspect-ratio menjaga kotaknya tetap persegi */
    .foto-galeri {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(3.2rem, 1fr));
    }

    .foto-thumbs {
        width: 100%;
        height: auto;
        aspect-ratio: 1 / 1;
    }

    /* popus foto produk di layar sempit: kalau fotonya banyak, daftar kotaknya
       digulir di dalam dirinya sendiri supaya popusnya tidak terus memanjang ke
       bawah — kepala popus, petunjuk, dan kotak "Pilih foto" tetap terlihat */
    @media (max-width: 767.98px) {
        #modalFotoProduk .foto-daftar {
            max-height: 14rem;
            overflow-y: auto;
            /* .row membawa margin negatif; tanpa ini gulir vertikalnya ikut
               memunculkan gulir mendatar */
            overflow-x: hidden;
        }

        /* Popus "Tambah Barang" punya sembilan isian: tanpa batas ini tingginya
           menyentuh tepi layar (624px dari 640px) dan terasa "terlalu panjang".
           72vh — sama seperti popus catatan COD — menyisakan latar yang terlihat
           di atas-bawahnya sehingga kepala dan kaki tombol selalu terbaca, sementara
           isian sisanya digulir di dalam body (modal-dialog-scrollable). */
        #modalTambahBarang .modal-content {
            max-height: 72vh;
        }
    }

    /* "Tambah Barang" memenuhi lebar kartu di layar sempit; dulu ia cuma
       seukuran tulisannya dan berdempetan dengan baris filter, padahal itu
       tombol utama halaman ini */
    @media (max-width: 767.98px) {
        .toolbar-produk > .btn {
            width: 100%;
        }

        /* Pil kolam "Untuk Dijual" / "Untuk Disewa" adalah cara berpindah tab
           utama di halaman ini; dulu keduanya cuma seukuran tulisannya dan duduk
           di kiri sementara separuh kanan baris menganggur. */
        .kolam-pilih > .btn {
            flex: 1 1 0;
            min-width: 0;
        }

        /* Angka ringkasan "Rp 5.000.000" jangan pecah di belakang "Rp". Di kolom
           6/12 fs-4 (1.5rem — Bootstrap menguncinya dengan !important, jadi ikut
           !important) tidak muat, jadi turun satu langkah dan dipaksa satu baris. */
        .ringkas-nilai {
            font-size: 1.15rem !important;
            white-space: nowrap;
        }
    }

    /* Pil "sedang disewa" di bawah kartu produk: seukuran isinya, ditempel di
       tepi kiri barisnya. Titik merahnya berkedip supaya menyala diam-diam
       tanpa perlu animasi lain; mati saja kalau pengguna mematikan animasi. */
    .pil-disewa {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.38rem 0.95rem;
        border: 1px solid #eaecf0;
        border-radius: 999px;
        background: #ffffff;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.06);
        font-size: 0.8rem;
        font-weight: 600;
        color: #344054;
        text-decoration: none;
    }
    .pil-disewa:hover {
        background: #f9fafb;
        color: #101828;
    }
    .pil-disewa__titik {
        flex: 0 0 auto;
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 50%;
        background: #da251d;
        animation: pil-disewa-kedip 1.4s ease-in-out infinite;
    }
    @keyframes pil-disewa-kedip {
        50% { opacity: 0.15; }
    }
    @media (prefers-reduced-motion: reduce) {
        .pil-disewa__titik {
            animation: none;
        }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php /* CS membuka halaman yang sama untuk membaca katalog, tapi harus memakai
   sidebar-nya sendiri (Transaksi/Chart/Katalog), bukan chrome admin yang penuh
   tautan yang tidak boleh dia buka */ ?>
<?= $this->include((string) session()->get('level') === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">Produk &amp; Stok</h1>

    <?php /* stok dipisah dua kolam: unit yang boleh dijual dan unit yang boleh
       disewa; satu tab hanya menampilkan dan menyaring kolamnya sendiri */ ?>
    <div class="d-flex flex-wrap gap-2 mb-4 kolam-pilih">
        <?php foreach ($kolams as $slug => $label) {
            $tautan = $kueriTab + ['kolam' => $slug];
            ?>
            <a class="btn rounded-pill <?= $kolam === $slug ? 'btn-primary' : 'btn-outline-secondary' ?>"
                title="<?= $slug === 'sewa' ? 'Barang yang boleh disewa' : 'Barang yang boleh dibeli' ?>"
                href="<?= site_url('admin/produk?' . http_build_query($tautan)) ?>"><?= esc($label) ?></a>
        <?php } ?>
    </div>

    <div class="row gx-2 gy-3 mb-4">
        <?php
        // elemen keempat: kelas warna untuk angka + ikonya, dipakai penanda poin
        $ringkasan = [
            ['Varian tercatat', (string) $ringkas['varian'], 'fa-shapes', ''],
            ['Total unit ' . $kataKolam, (string) $ringkas['unit'], 'fa-boxes-stacked', ''],
            ['Nilai stok ' . $kataKolam, $rupiah((int) $ringkas['nilai']), 'fa-coins', ''],
            ['Menipis', (string) $ringkas['menipis'], 'fa-triangle-exclamation', ''],
            ['Habis', (string) $ringkas['habis'], 'fa-circle-xmark', ''],
            // poin gaji hanya boleh diisi Superadmin: angka ini mengingatkan
            // Admin dan CS bahwa ada barang yang belum bisa dihitung gajinya
            [
                'Belum berpoin',
                (string) $ringkas['belum_poin'],
                'fa-coins',
                $ringkas['belum_poin'] > 0 ? 'text-amber-800' : '',
            ],
        ];

        foreach ($ringkasan as [$label, $nilai, $ikon, $warna]) { ?>
            <div class="col-6 col-lg">
                <div class="h-100 rounded-3 border border-ink-200 bg-white px-3 py-2">
                    <div class="text-muted small text-uppercase <?= esc($warna) ?>">
                        <i class="fal <?= $ikon ?>"></i> <?= esc($label) ?>
                    </div>
                    <div class="fs-4 fw-bold ringkas-nilai <?= esc($warna) ?>"><?= esc($nilai) ?></div>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="row gx-3">
        <div class="col-12 mb-4">
            <?php if ($sukses !== null) { ?>
                <div class="alert alert-success py-2" data-flash><?= esc($sukses) ?></div>
            <?php } ?>
            <?php if ($gagal !== null) { ?>
                <div class="alert alert-danger py-2" data-flash><?= esc($gagal) ?></div>
            <?php } ?>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 align-items-end mb-3 toolbar-produk">
                        <?= form_open('admin/produk', ['method' => 'get', 'class' => 'row g-2 align-items-end mb-0 flex-grow-1']) ?>
                        <?php /* form GET: kolom isian yang tidak dipakai tetap ikut
   terbawa sebagai ?, jadi tab yang sedang dibuka tidak hilang saat menyaring */ ?>
                        <input type="hidden" name="kolam" value="<?= esc($kolam) ?>">
                        <input type="hidden" name="page" value="">
                        <div class="col-6 col-md-3">
                            <?= form_label('Juragan', 'juragan', ['class' => 'form-label small']) ?>
                            <?= form_dropdown([
                                'class'    => 'form-select form-select-sm',
                                'name'     => 'juragan',
                                'options'  => [0 => 'Semua juragan'] + $juragans,
                                'selected' => $juragan,
                            ]) ?>
                        </div>
                        <div class="col-6 col-md-3">
                            <?= form_label('Keadaan', 'keadaan', ['class' => 'form-label small']) ?>
                            <?= form_dropdown([
                                'class'    => 'form-select form-select-sm',
                                'name'     => 'keadaan',
                                'options'  => [
                                    ''        => 'Semua',
                                    'ada'     => 'Stok aman',
                                    'menipis' => 'Menipis (≤ ' . $ambangnya . ')',
                                    'habis'   => 'Habis',
                                ],
                                'selected' => $keadaan,
                            ]) ?>
                        </div>
                        <div class="col-8 col-md-4">
                            <?= form_label('Cari', 'cari', ['class' => 'form-label small']) ?>
                            <?= form_input([
                                'class'       => 'form-control form-control-sm',
                                'name'        => 'cari',
                                'placeholder' => 'nama barang atau keterangan',
                                'value'       => $cari,
                            ]) ?>
                        </div>
                        <?php /* tombolnya duduk di samping kolom carinya di semua lebar;
                                dulu kolom cari col-12 sehingga tombolnya turun sendiri
                                ke baris berikutnya di layar sempit */ ?>
                        <div class="col-4 col-md-auto">
                            <button class="btn btn-sm btn-dark w-100" type="submit"><i class="fal fa-search"></i> Cari</button>
                        </div>
                        <?= form_close() ?>
                        <?php if (! $bacaSaja) { ?>
                        <button class="btn btn-sm btn-primary flex-shrink-0" type="button" data-bs-toggle="modal" data-bs-target="#modalTambahBarang">
                            <i class="fal fa-plus"></i> Tambah Barang
                        </button>
                        <?php } ?>
                    </div>

                    <?php /* chip "sedang disewa" ditaruh di ATAS daftar supaya langsung
                       terlihat: angkanya = unit yang diikat nota sewa dan belum kembali
                       (sama dengan pengurang kolom Tersedia, berapa pun status
                       pembayarannya). Tab yang dituju lebih sempit dari angka ini: di
                       sana hanya nota yang dananya sudah masuk (DP/cicilan/lunas),
                       jadi tooltipnya menyebut keduanya supaya tidak dibaca sebagai bug. */ ?>
                    <?php if ($disewa > 0) { ?>
                        <a class="pil-disewa mb-3" href="<?= esc($tautan_disewa) ?>"
                            title="Unit yang sudah diikat nota sewa dan belum dicatat kembali — inilah pengurang kolom Tersedia. Bagian Masih Disewa di tab Disewa hanya memuat nota yang dananya sudah masuk (DP, dicicil, atau lunas), jadi angkanya bisa lebih sedikit daripada chip ini.">
                            <span class="pil-disewa__titik" aria-hidden="true"></span>
                            <?= formatRibuan($disewa) ?> unit terikat nota sewa
                            <i class="fal fa-arrow-alt-right text-ink-400" aria-hidden="true"></i>
                        </a>
                    <?php } ?>

                    <?php if ($katalog === []) { ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-box-open fa-3x mb-2 d-block"></i>
                            <?php if ($kolam === 'sewa') { ?>
                                <?php /* tab sewa sengaja hanya memuat unit yang ada; yang masih kosong lewat saringan Habis */ ?>
                                Belum ada barang dengan stok sewa yang tersisa<?= $cari !== '' ? ' untuk pencarian "' . esc($cari) . '"' : '' ?>.
                                <?php if ($keadaan === '') { ?>
                                    Lihat
                                    <a href="<?= site_url('admin/produk?' . http_build_query($kueriTab + ['keadaan' => 'habis'])) ?>">saringan <strong>Habis</strong></a>
                                    untuk barang yang stok sewanya belum diisi.
                                <?php } ?>
                            <?php } else { ?>
                                Belum ada barang yang dipantau<?= $cari !== '' ? ' untuk pencarian "' . esc($cari) . '"' : '' ?>.
                            <?php } ?>
                            <?php /* Admin/CS hanya dibatasi ke tokonya sendiri, jadi daftar yang
                              kosong sering terlihat seperti bug — sebutkan cakupan yang dibaca. */ ?>
                            Yang dibaca di halaman ini hanya barang dari <strong><?= esc($cakupan) ?></strong>.
                            <?php if (! $bacaSaja) { ?>Klik tombol <strong>Tambah Barang</strong> di atas untuk menambah varian.<?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="grid grid-cols-[repeat(auto-fill,minmax(15rem,1fr))] gap-3">
                            <?php foreach ($katalog as $i => $p) {
                                $varian = $p['varian'];
                                $jumlah = count($varian);
                                [$vw_kartu, $teks] = $keadaan_baris((int) $p['total_stok']);
                                $rentang = $p['harga_akhir'] > $p['harga_mulai'];

                                // foto kartu = gabungan foto semua variannya, jadi
                                // satu unggahan terlihat di semua ukuran
                                $fotonya_kartu = $fotonya[$i] ?? [];
                                $sampul        = $fotonya_kartu[0] ?? null;
                                ?>
                                <div class="card produk-kartu h-100" tabindex="0" role="button" aria-label="Lihat detail <?= esc($p['kode'], 'attr') ?>">
                                    <div class="rounded-kartu overflow-hidden">
                                        <div class="produk-sampul position-relative d-flex align-items-center justify-content-center">
                                            <?php if ($sampul !== null) { ?>
                                                <img src="<?= esc(\App\Models\ProdukFotoModel::alamat((int) $sampul['id_foto'], 480)) ?>"
                                                    alt="Foto <?= esc($p['kode']) ?>" loading="lazy">
                                            <?php } else { ?>
                                                <span class="produk-inisial"><?= esc($inisial($p['kode'])) ?></span>
                                            <?php } ?>
                                            <?php if ($juragan === 0) { ?>
                                                <span class="badge position-absolute top-0 start-0 m-2 rounded-pill bg-white text-ink-700 border border-ink-200">
                                                    <?= esc($p['nama_juragan']) ?>
                                                </span>
                                            <?php } ?>
                                            <?php if (! $bacaSaja) { ?>
                                            <button type="button" class="produk-foto-btn position-absolute top-0 end-0 m-2 rounded-circle d-flex align-items-center justify-content-center"
                                                title="Kelola foto produk ini" aria-label="Kelola foto produk"
                                                data-stok="<?= (int) $varian[0]['id_stok'] ?>"
                                                data-nama="<?= esc($p['kode'], 'attr') ?>"
                                                data-bs-toggle="modal" data-bs-target="#modalFotoProduk">
                                                <i class="fal fa-camera"></i>
                                            </button>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <div class="card-body p-3 d-flex flex-column">
                                        <h2 class="produk-nama mb-1 text-truncate" title="<?= esc($p['kode']) ?>"><?= esc($p['kode']) ?></h2>
                                        <div class="produk-harga mb-2">
                                            <?php if ($rentang) { ?>
                                                <span class="produk-harga-mulai">mulai</span>
                                            <?php } ?>
                                            <span><?= $rupiah((int) $p['harga_mulai']) ?></span>
                                        </div>
                                        <?php if (($p['poin'] ?? 0) > 0) { ?>
                                            <?php if ($bisaPoin) { ?>
                                            <div class="produk-poin mb-2 small">
                                                <i class="fal fa-coins text-brand-600"></i>
                                                <span class="text-muted">Poin</span>
                                                <span class="fw-semibold text-ink-700"><?= number_format((int) $p['poin'], 0, ',', '.') ?></span>
                                            </div>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <?php /* penanda untuk semua role: Admin dan CS boleh membaca daftar
                                               ini tapi hanya Superadmin yang bisa mengisi poin gajinya */ ?>
                                            <div class="produk-poin mb-2 small">
                                                <span class="badge rounded-pill bg-warning text-dark"
                                                    title="Poin gaji <?= esc($p['kode'], 'attr') ?> belum diisi. Hanya Superadmin yang bisa menentukannya.">
                                                    <i class="fal fa-coins me-1"></i>Poin belum diisi
                                                </span>
                                            </div>
                                        <?php } ?>

                                        <div class="varian-daftar mb-2">
                                            <?php foreach ($varian as $b) {
                                                $ukuran_baris = (string) ($b['ukuran'] ?? '');
                                                [$vw, $label_keadaan] = $keadaan_baris((int) $b['tersedia']);
                                                ?>
                                                <button type="button" class="varian-chip"
                                                    title="<?= esc($ukuran_baris === '' ? 'Tanpa ukuran' : 'Ukuran ' . $ukuran_baris) ?> · <?= esc($label_keadaan) ?> · klik untuk melihat detail"
                                                    data-varian="<?= (int) $b['id_stok'] ?>">
                                                    <span class="varian-dot<?= $vw === 'success' ? '' : ' ' . $vw ?>"></span>
                                                    <span class="text-truncate"><?= esc($ukuran_baris === '' ? 'Tanpa ukuran' : $ukuran_baris) ?> (<?= (int) $b['tersedia'] ?>)</span>
                                                </button>
                                            <?php } ?>
                                        </div>

                                        <div class="d-flex mt-auto mb-2 pt-2">
                                            <div class="flex-fill text-center">
                                                <div class="produk-stat-label">Varian</div>
                                                <div class="produk-stat-angka"><?= $jumlah ?></div>
                                            </div>
                                            <div class="flex-fill text-center">
                                                <div class="produk-stat-label">Total Stok</div>
                                                <div class="produk-stat-angka"><?= (int) $p['total_stok'] ?></div>
                                            </div>
                                            <div class="flex-fill text-center">
                                                <div class="produk-stat-label">Keadaan</div>
                                                <div><span class="badge bg-<?= $vw_kartu ?> mt-1"><?= esc($teks) ?></span></div>
                                            </div>
                                        </div>

                                        <?php if (! $bacaSaja) { ?>
                                        <div class="d-flex produk-aksi pt-2">
                                            <button type="button" class="btn btn-sm produk-aksi-tombol"
                                                title="Tambah varian untuk <?= esc($p['kode'], 'attr') ?>"
                                                data-tambah="<?= (string) esc(json_encode(['kode' => $p['kode'], 'juragan' => $p['juragan_id'], 'poin' => (int) ($p['poin'] ?? 0)])) ?>"
                                                data-bs-toggle="modal" data-bs-target="#modalTambahBarang">
                                                <i class="fal fa-plus"></i> Varian
                                            </button>
                                            <button type="button" class="btn btn-sm produk-aksi-tombol"
                                                title="Sunting <?= esc($p['kode'], 'attr') ?>"
                                                data-kartu="<?= $kartu_barang($p) ?>"
                                                data-bs-toggle="modal" data-bs-target="#modalSuntingBarang">
                                                <i class="fal fa-pen"></i> Edit
                                            </button>
                                            <button type="submit" class="btn btn-sm produk-aksi-tombol"
                                                title="Hapus <?= esc($p['kode'], 'attr') ?> beserta semua variannya dari daftar stok"
                                                form="formHapusKartu"
                                                data-hapus-kartu="<?= (string) esc(json_encode(['kode' => $p['kode'], 'juragan' => (int) $p['juragan_id'], 'varian' => $jumlah])) ?>">
                                                <i class="fal fa-trash"></i> Hapus
                                            </button>
                                        </div>
                                        <?php } ?>
                                    </div>

                                    <?php
                                    // rangkapan detail kartu; baru dipindah ke modal saat kartunya dibuka
                                    $harga_lain = $rentang ? ' – ' . $rupiah((int) $p['harga_akhir']) : '';
                                    $foto_rinci = $fotonya_kartu;
                                    $utama      = $foto_rinci[0] ?? null;
                                    ?>
                                    <div class="produk-rincian d-none">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <?php if ($utama !== null) { ?>
                                                    <button type="button" class="foto-utama w-100 border-0 rounded-3 overflow-hidden p-0"
                                                        data-full="<?= esc(\App\Models\ProdukFotoModel::alamat((int) $utama['id_foto'])) ?>"
                                                        title="Klik untuk ukuran penuh">
                                                        <img src="<?= esc(\App\Models\ProdukFotoModel::alamat((int) $utama['id_foto'], 480)) ?>" alt="Foto <?= esc($p['kode'], 'attr') ?>">
                                                    </button>
                                                    <?php if (count($foto_rinci) > 1) { ?>
                                                        <div class="foto-galeri mt-2">
                                                            <?php foreach ($foto_rinci as $j => $f) { ?>
                                                                <button type="button" class="foto-thumbs<?= $j === 0 ? ' aktif' : '' ?>"
                                                                    data-full="<?= esc(\App\Models\ProdukFotoModel::alamat((int) $f['id_foto'])) ?>"
                                                                    aria-label="Lihat foto <?= $j + 1 ?>">
                                                                    <img src="<?= esc(\App\Models\ProdukFotoModel::alamat((int) $f['id_foto'], 240)) ?>" alt="" loading="lazy">
                                                                </button>
                                                            <?php } ?>
                                                        </div>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <div class="produk-inisial-kotak rounded-3 d-flex align-items-center justify-content-center">
                                                        <span class="produk-inisial"><?= esc($inisial($p['kode'])) ?></span>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="fs-5 fw-bold"><?= esc($p['kode']) ?></div>
                                                <div class="fw-bold text-brand-600"><?= $rupiah((int) $p['harga_mulai']) ?><?= $harga_lain ?></div>
                                                <div class="small text-muted mt-1"><i class="fal fa-store me-1"></i><?= esc($p['nama_juragan']) ?></div>
                                                <?php if (($p['poin'] ?? 0) > 0) { ?>
                                                    <?php if ($bisaPoin) { ?>
                                                    <div class="small mt-1"><i class="fal fa-coins text-brand-600 me-1"></i>Poin: <span class="fw-semibold text-ink-700"><?= number_format((int) $p['poin'], 0, ',', '.') ?></span></div>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <div class="small mt-1">
                                                        <span class="badge rounded-pill bg-warning text-dark"
                                                            title="Poin gaji <?= esc($p['kode'], 'attr') ?> belum diisi. Hanya Superadmin yang bisa menentukannya.">
                                                            <i class="fal fa-coins me-1"></i>Poin belum diisi
                                                        </span>
                                                    </div>
                                                <?php } ?>
                                                <div class="d-flex gap-2 mt-2">
                                                    <?php /* keadaan cukup dibaca dari warna titiknya */ ?>
                                                    <span class="varian-dot<?= $vw_kartu === 'success' ? '' : ' ' . $vw_kartu ?> align-self-center"
                                                        title="<?= esc($teks) ?>"></span>
                                                    <span class="small text-muted align-self-center"><?= (int) $p['terjual'] ?> terjual</span>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <table class="table table-sm align-middle mb-0">
                                                    <thead>
                                                        <tr class="small text-muted">
                                                            <th>Ukuran</th>
                                                            <th class="text-nowrap">Harga</th>
                                                            <th>Stok</th>
                                                            <th class="d-none d-sm-table-cell">Keterangan</th>
                                                            <?php if (! $bacaSaja) { ?><th class="w-1"></th><?php } ?>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($varian as $b) {
                                                            $ukuran_baris = (string) ($b['ukuran'] ?? '');
                                                            $ket_baris    = trim((string) ($b['keterangan'] ?? ''));
                                                            [$bw, $bteks]  = $keadaan_baris((int) $b['tersedia']);
                                                            ?>
                                                            <tr data-varian="<?= (int) $b['id_stok'] ?>">
                                                                <td><?= $ukuran_baris === '' ? 'Tanpa ukuran' : esc($ukuran_baris) ?></td>
                                                                <td class="text-nowrap"><?= $rupiah((int) $b['harga']) ?></td>
                                                                <td>
                                                                    <?php /* angkanya saja: warna badge-nya yang menyatakan aman/menipis/habis */ ?>
                                                                    <span class="badge bg-<?= $bw ?> <?= $bw === 'warning' ? 'text-dark' : 'text-white' ?>"
                                                        title="<?= esc($bteks) ?>"><?= (int) $b['tersedia'] ?></span>
                                                                </td>
                                                                <td class="small text-muted d-none d-sm-table-cell">
                                                                    <?php /* catatannya sepanjang yang diketik (maks 500), jadi tingginya
   dibatasi tiga baris dan tiap baris digulir sendiri-sendiri */ ?>
                                                                    <div class="varian-ket"><?= $ket_baris === '' ? '&mdash;' : esc($ket_baris) ?></div>
                                                                </td>
                                                                <?php if (! $bacaSaja) { ?>
                                                                <td class="text-end">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger produk-aksi-ikon varian-hapus"
                                                                        title="Hapus varian ini dari daftar stok"
                                                                        form="formHapusBarang"
                                                                        data-hapus-varian="<?= (string) esc(json_encode([
                                                                            'id'    => (int) $b['id_stok'],
                                                                            'label' => trim($p['kode'] . ' ' . $ukuran_baris),
                                                                        ])) ?>">
                                                                        <i class="fal fa-trash"></i><span class="visually-hidden">Hapus varian</span>
                                                                    </button>
                                                                </td>
                                                                <?php } ?>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>

                        <?php if ($katalog !== []) { ?>
                            <div class="mt-3">
                                <?= $pager->makeLinks($page, $limit, $total, 'front_full') ?>
                            </div>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <datalist id="daftarKode">
        <?php foreach ($kode as $k) { ?>
            <option value="<?= esc($k['kode']) ?>"></option>
        <?php } ?>
    </datalist>
    <datalist id="daftarUkuran">
        <?php foreach ($ukuran as $u) { ?>
            <option value="<?= esc($u['kode']) ?>"><?= esc($u['kelompok']) ?></option>
        <?php } ?>
    </datalist>
</div>
<?= $this->endSection() ?>

<?= $this->section('modal') ?>
<div class="modal fade" id="modalTambahBarang" tabindex="-1" aria-labelledby="modalTambahBarangLabel" aria-hidden="true">
    <?php /* scrollable: di layar HP sembilan isian ini lebih tinggi dari layar,
             jadi badan popusnya yang digulir — kepala dan kaki tombol tetap
             terlihat. Dialognya tidak dipaksa setinggi layar karena
             modal-dialog-centered menahan .modal-content seukuran isinya. */ ?>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <?= form_open('admin/produk/save', ['class' => 'modal-content', 'enctype' => 'multipart/form-data'], [
            'kembali_juragan' => (string) $juragan,
            'kembali_keadaan' => $keadaan,
            'kembali_cari'    => $cari,
            // 1 = dibuka dari tombol "Varian" pada kartu: juragan dan harga ikut
            // kartunya, jadi keduanya tidak ditanyakan lagi
            'kartu'           => set_value('kartu', '0'),
            // isian stok/harga form ini masuk ke kolam tab yang dibuka
            'kolam'           => $kolam,
        ]) ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalTambahBarangLabel">Tambah Barang</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3 tambah-khusus-barang">
                <?= form_label('Juragan', 'juragan_id', ['class' => 'form-label']) ?>
                <?= form_dropdown([
                    'class'    => 'form-select' . ($err_tambah('juragan_id') ? ' is-invalid' : ''),
                    'name'     => 'juragan_id',
                    'options'  => [0 => 'Pilih Juragan'] + $juragans,
                    'required' => '',
                    'selected' => (int) old('juragan_id', $juragan),
                ]) ?>
                <?php if ($err_tambah('juragan_id')) { ?>
                    <div class="invalid-feedback d-block"><?= esc($err_tambah('juragan_id')) ?></div>
                <?php } ?>
            </div>
            <div class="row gx-2">
                <div class="col-6 mb-3">
                    <?= form_label('Nama Barang', 'kode', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'       => 'form-control' . ($err_tambah('kode') ? ' is-invalid' : ''),
                        'name'        => 'kode',
                        'list'        => 'daftarKode',
                        'placeholder' => 'JAS PREMIUM',
                        'required'    => '',
                        'max_length'  => 20,
                        'value'       => set_value('kode'),
                    ]) ?>
                    <?php if ($err_tambah('kode')) { ?>
                        <div class="invalid-feedback d-block"><?= esc($err_tambah('kode')) ?></div>
                    <?php } ?>
                </div>
                <div class="col-6 mb-3">
                    <?= form_label('Ukuran', 'ukuran', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'       => 'form-control' . ($err_tambah('ukuran') ? ' is-invalid' : ''),
                        'name'        => 'ukuran',
                        'list'        => 'daftarUkuran',
                        'placeholder' => 'L / 32 / custom',
                        'max_length'  => 6,
                        'value'       => set_value('ukuran'),
                    ]) ?>
                    <?php if ($err_tambah('ukuran')) { ?>
                        <div class="invalid-feedback d-block"><?= esc($err_tambah('ukuran')) ?></div>
                    <?php } ?>
                </div>
            </div>
            <div class="row gx-2">
                <div class="col-6 mb-3 tambah-khusus-barang">
                    <?= form_label('Harga ' . $kataKolam, 'harga', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'       => 'form-control' . ($err_tambah('harga') ? ' is-invalid' : ''),
                        'type'        => 'number',
                        'name'        => 'harga',
                        'placeholder' => '250000',
                        'min'         => 0,
                        'required'    => '',
                        'value'       => set_value('harga'),
                    ]) ?>
                    <?php if ($err_tambah('harga')) { ?>
                        <div class="invalid-feedback d-block"><?= esc($err_tambah('harga')) ?></div>
                    <?php } ?>
                </div>
                <div class="col-6 mb-3">
                    <?= form_label('Stok ' . $kataKolam, 'stok', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'       => 'form-control' . ($err_tambah('stok') ? ' is-invalid' : ''),
                        'type'        => 'number',
                        'name'        => 'stok',
                        'placeholder' => '10',
                        'min'         => 1,
                        'required'    => '',
                        'value'       => set_value('stok', '1'),
                    ]) ?>
                    <?php if ($err_tambah('stok')) { ?>
                        <div class="invalid-feedback d-block"><?= esc($err_tambah('stok')) ?></div>
                    <?php } ?>
                </div>
            </div>
            <?php if ($bisaPoin) { ?>
            <div class="mb-3">
                <?= form_label('Poin Gaji', 'poin', ['class' => 'form-label']) ?>
                <?= form_input([
                    'class'       => 'form-control' . ($err_tambah('poin') ? ' is-invalid' : ''),
                    'type'        => 'number',
                    'name'        => 'poin',
                    'min'         => 0,
                    'step'        => 1,
                    'placeholder' => 'mis. 1000',
                    'value'       => set_value('poin', '0'),
                ]) ?>
                <?php if ($err_tambah('poin')) { ?>
                    <div class="invalid-feedback d-block"><?= esc($err_tambah('poin')) ?></div>
                <?php } ?>
            </div>
            <?php } ?>
            <div class="mb-3 tambah-khusus-barang">
                <?= form_label('Catatan (opsional)', 'keterangan', ['class' => 'form-label']) ?>
                <?= form_textarea([
                    'class'       => 'form-control',
                    'name'        => 'keterangan',
                    'rows'        => 3,
                    'maxlength'   => 500,
                    'placeholder' => 'mis. warna navy, bahan wool',
                    'value'       => set_value('keterangan'),
                ]) ?>
                <?php if ($err_tambah('keterangan')) { ?>
                    <div class="invalid-feedback d-block"><?= esc($err_tambah('keterangan')) ?></div>
                <?php } ?>
            </div>
            <div class="mb-0">
                <?= form_label('Foto produk (opsional)', 'foto', ['class' => 'form-label']) ?>
                <input type="file" class="form-control" id="foto" name="foto[]" multiple accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPG, PNG, atau WebP - maks. 3 MB/foto - hingga 12 foto per barang</div>
            </div>
        </div>
        <div class="modal-footer flex-nowrap p-0">
            <button type="button" data-bs-dismiss="modal" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0">Batal</button>
            <button type="submit" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0 border-start"><i class="fal fa-save"></i> Tambahkan</button>
        </div>
        <?= form_close() ?>
    </div>
</div>

<div class="modal fade" id="modalSuntingBarang" tabindex="-1" aria-labelledby="modalSuntingBarangLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <?= form_open('admin/produk/update', ['class' => 'modal-content'], [
            'id_stok'         => set_value('id_stok'),
            'kembali_juragan' => (string) $juragan,
            'kembali_keadaan' => $keadaan,
            'kembali_cari'    => $cari,
            // sunting hanya menulis kolam tab yang dibuka
            'kolam'           => $kolam,
        ]) ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalSuntingBarangLabel">Sunting Barang</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <?php /* varian dipilih dulu; inputannya baru muncul sesudah satu varian dipilih,
   supaya tidak ada sunting yang diam-diam mengenai ukuran pertama kartu */ ?>
            <div class="mb-3">
                <span class="form-label d-block">Pilih varian</span>
                <div class="varian-daftar" id="suntingVarian"></div>
            </div>

            <div class="sunting-detail d-none">
                <div class="mb-3">
                    <?= form_label('Juragan', 'sunting_juragan', ['class' => 'form-label']) ?>
                    <?= form_dropdown([
                        'class'    => 'form-select' . ($err_sunting('juragan_id') ? ' is-invalid' : ''),
                        'name'     => 'juragan_id',
                        'id'       => 'sunting_juragan',
                        'options'  => $juragans,
                        'required' => '',
                    ]) ?>
                    <?php if ($err_sunting('juragan_id')) { ?>
                        <div class="invalid-feedback d-block"><?= esc($err_sunting('juragan_id')) ?></div>
                    <?php } ?>
                </div>
                <div class="row gx-2">
                    <div class="col-6 mb-3">
                        <?= form_label('Nama Barang', 'sunting_kode', ['class' => 'form-label']) ?>
                        <?= form_input(['class' => 'form-control', 'name' => 'kode', 'id' => 'sunting_kode', 'list' => 'daftarKode', 'required' => '', 'max_length' => 20]) ?>
                        <?php if ($err_sunting('kode')) { ?>
                            <div class="text-danger small mt-1"><?= esc($err_sunting('kode')) ?></div>
                        <?php } ?>
                    </div>
                    <div class="col-6 mb-3">
                        <?= form_label('Ukuran', 'sunting_ukuran', ['class' => 'form-label']) ?>
                        <?= form_input(['class' => 'form-control', 'name' => 'ukuran', 'id' => 'sunting_ukuran', 'list' => 'daftarUkuran', 'max_length' => 6]) ?>
                        <?php if ($err_sunting('ukuran')) { ?>
                            <div class="text-danger small mt-1"><?= esc($err_sunting('ukuran')) ?></div>
                        <?php } ?>
                    </div>
                </div>
                <div class="row gx-2">
                    <div class="col-6 mb-3">
                        <?= form_label('Harga ' . $kataKolam, 'sunting_harga', ['class' => 'form-label']) ?>
                        <?= form_input(['class' => 'form-control', 'type' => 'number', 'name' => 'harga', 'id' => 'sunting_harga', 'min' => 0, 'required' => '']) ?>
                        <?php if ($err_sunting('harga')) { ?>
                            <div class="text-danger small mt-1"><?= esc($err_sunting('harga')) ?></div>
                        <?php } ?>
                    </div>
                    <div class="col-6 mb-3">
                        <?= form_label('Stok ' . $kataKolam, 'sunting_stok', ['class' => 'form-label']) ?>
                        <?= form_input(['class' => 'form-control', 'type' => 'number', 'name' => 'stok', 'id' => 'sunting_stok', 'required' => '']) ?>
                        <?php if ($err_sunting('stok')) { ?>
                            <div class="text-danger small mt-1"><?= esc($err_sunting('stok')) ?></div>
                        <?php } ?>
                    </div>
                </div>
                <?php if ($bisaPoin) { ?>
                <div class="mb-3">
                    <?= form_label('Poin Gaji', 'sunting_poin', ['class' => 'form-label']) ?>
                    <?= form_input(['class' => 'form-control', 'type' => 'number', 'name' => 'poin', 'id' => 'sunting_poin', 'min' => 0, 'step' => 1, 'placeholder' => 'mis. 1000']) ?>
                    <div class="form-text">Poin per produk (sama untuk semua ukuran); 0 = tanpa poin.</div>
                </div>
                <?php } ?>
                <div class="mb-0">
                    <?= form_label('Keterangan', 'sunting_keterangan', ['class' => 'form-label']) ?>
                    <?= form_textarea(['class' => 'form-control', 'name' => 'keterangan', 'id' => 'sunting_keterangan', 'rows' => 3, 'maxlength' => 500, 'placeholder' => 'mis. warna navy, bahan wool, catatan stok...']) ?>
                    <?php if ($err_sunting('keterangan')) { ?>
                        <div class="text-danger small mt-1"><?= esc($err_sunting('keterangan')) ?></div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="modal-footer flex-nowrap p-0">
            <button type="button" data-bs-dismiss="modal" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0">Batal</button>
            <button type="submit" id="suntingSimpan" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0 border-start" disabled>Simpan</button>
        </div>
        <?= form_close() ?>
    </div>
</div>

<?php
// Dipakai tombol sampah di tabel varian modal detail. Form-nya berdiri sendiri
// di luar modal supaya tidak ada form yang bersarang, dan ditambatkan lewat
// atribut `form`.
echo form_open('admin/produk/hapus', ['id' => 'formHapusBarang', 'class' => 'd-none'])
    . form_hidden('id_stok')
    . form_hidden('kembali_juragan', (string) $juragan)
    . form_hidden('kembali_keadaan', $keadaan)
    . form_hidden('kembali_cari', $cari)
    . form_hidden('kolam', $kolam)
    . form_close();

// Tombol hapus per kartu mengirim form ini; kode dan toko tujuan diisi JS
// sesaat sebelum kirim, supaya kartu tidak perlu menyimpan satu form sendiri
echo form_open('admin/produk/hapus-kartu', ['id' => 'formHapusKartu', 'class' => 'd-none'])
    . form_hidden('kode')
    . form_hidden('juragan_id')
    . form_hidden('kembali_juragan', (string) $juragan)
    . form_hidden('kembali_keadaan', $keadaan)
    . form_hidden('kembali_cari', $cari)
    . form_hidden('kolam', $kolam)
    . form_close();
?>

<?php
// Panel fotonya tidak ikut ter-render untuk tiap kartu: 20 kartu x daftar foto
// per halaman berat, dan yang dibuka cuma satu. Isinya dimuat saat tombolnya
// ditekan (lihat js di bawah), memakai panel yang sama dengan form orderan.
?>
<div class="modal fade" id="modalFotoProduk" tabindex="-1" aria-labelledby="modalFotoProdukLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalFotoProdukLabel">Foto Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body foto-muat">
                <div class="text-center text-muted py-4"><i class="fal fa-spinner fa-spin me-2"></i>Fotonya dimuat...</div>
            </div>
            <div class="modal-footer border-top-0 pt-0 pb-3 px-3 px-sm-4">
                <span class="foto-status small text-muted me-auto d-none"><i class="fal fa-exclamation-circle text-warning me-1"></i>Ada perubahan belum disimpan</span>
                <button type="button" class="btn btn-outline-secondary rounded-kartu" data-bs-dismiss="modal">Tutup</button>
                <button type="button" id="btnSimpanFoto" class="btn btn-primary rounded-kartu d-none">
                    <i class="fal fa-save me-1"></i>Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Isi modal detail tidak ditulis di sini: tiap kartu menyimpan rangkapannya
// sendiri (.produk-rincian, tersembunyi) dan tinggal dipindah ke modal saat
// kartunya dibuka. Foto memang sudah terlanjur ter-query untuk sampul kartu,
// dan gambar di dalam blok d-none baru dimuat saat muncul, jadi tidak ada
// permintaan tambahan sama sekali.
?>
<div class="modal fade" id="modalDetailProduk" tabindex="-1" aria-labelledby="modalDetailProdukLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetailProdukLabel">Detail Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"></div>
        </div>
    </div>
</div>

<?php
/* konfirmasi hapus memakai modal sendiri: dialog bawaan browser tidak ikut
   tema dan tidak bisa menjelaskan bahwa transaksi lama tetap utuh */
?>
<div class="modal fade" id="modalHapusProduk" tabindex="-1" aria-labelledby="modalHapusProdukLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-kartu">
            <div class="modal-body p-4 text-center">
                <div class="konfirmasi-tanda d-inline-flex align-items-center justify-content-center mb-3">
                    <i class="fal fa-trash"></i>
                </div>
                <h5 class="konfirmasi-judul mb-1" id="modalHapusProdukLabel">Hapus dari daftar stok?</h5>
                <p class="konfirmasi-objek mb-1" id="hapusObjek"></p>
                <p class="small text-muted mb-4">Transaksi lama tetap utuh, dan stok bisa ditambahkan lagi kapan pun.</p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm konfirmasi-batal flex-fill" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-sm konfirmasi-ya flex-fill" id="hapusYa">Hapus</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="lightbox-produk" id="lightboxProduk" aria-hidden="true">
    <img src="" alt="Foto ukuran penuh">
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<script defer src="<?= base_url('assets/js/foto-form.js') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/js/foto-form.js') ?>"></script>
<?php
$showModalSunting = $sunting_err ? 'true' : 'false';
$showModalTambah  = $tambah_err ? 'true' : 'false';

// gagal menambah varian: modal dibuka lagi dalam mode varian, bukan mode barang baru
$tambah_varian = ($tambah_err && set_value('kartu') === '1') ? 'true' : 'false';
$alamat_foto      = site_url('api/produk-foto/kelola');
$isiUlang         = $sunting_err ? json_encode([
    'id'         => (int) set_value('id_stok'),
    'kode'       => (string) set_value('kode'),
    'ukuran'     => (string) set_value('ukuran'),
    'harga'      => (string) set_value('harga'),
    'poin'       => (string) set_value('poin'),
    'stok'       => (string) set_value('stok'),
    'keterangan' => (string) set_value('keterangan'),
    'juragan'    => '',
    'juragan_id' => (int) set_value('juragan_id'),
], JSON_THROW_ON_ERROR) : 'null';

$js = <<< JS
    $(function() {
    	'use strict';

        const suntingErr = {$showModalSunting};
        let gagalTambah = {$showModalTambah};
        let varianLama = {$tambah_varian};
        const ambang = {$ambangnya};
        const alamatFoto = '{$alamat_foto}';
        let suntingLama = {$isiUlang};
        const modalSunting = document.getElementById('modalSuntingBarang');
        const modalTambah = document.getElementById('modalTambahBarang');
        const formHapus = document.getElementById('formHapusBarang');
        const formHapusKartu = document.getElementById('formHapusKartu');
        const judulSunting = document.getElementById('modalSuntingBarangLabel');
        const judulTambah = document.getElementById('modalTambahBarangLabel');
        const daftarVarian = document.getElementById('suntingVarian');
        const detailSunting = modalSunting.querySelector('.sunting-detail');
        const simpanSunting = document.getElementById('suntingSimpan');
        const fields = {
            id_stok: modalSunting.querySelector('[name="id_stok"]'),
            kode: modalSunting.querySelector('[name="kode"]'),
            ukuran: modalSunting.querySelector('[name="ukuran"]'),
            harga: modalSunting.querySelector('[name="harga"]'),
            stok: modalSunting.querySelector('[name="stok"]'),
            poin: modalSunting.querySelector('[name="poin"]'),
            keterangan: modalSunting.querySelector('[name="keterangan"]'),
            juragan: modalSunting.querySelector('[name="juragan_id"]'),
        };

        // ---------- modal sunting: pilih varian dulu, baru inputannya muncul ----------

        let suntingAktif = { kode: '', varian: [] };

        function keadaan(stok) {
            if (stok <= 0) {
                return 'danger';
            }

            return stok <= ambang ? 'warning' : '';
        }

        function sembunyikanDetail() {
            detailSunting.classList.add('d-none');
            simpanSunting.disabled = true;
        }

        function kosongSunting() {
            suntingAktif = { kode: '', varian: [] };
            daftarVarian.innerHTML = '';
            judulSunting.textContent = 'Sunting Barang';
            sembunyikanDetail();
        }

        function pilihVarian(varian) {
            fields.id_stok.value = varian.id;
            fields.kode.value = varian.kode;
            fields.ukuran.value = varian.ukuran;
            fields.harga.value = varian.harga;
            fields.stok.value = varian.stok;
            fields.keterangan.value = varian.keterangan;
            fields.juragan.value = String(varian.juragan_id);

            daftarVarian.querySelectorAll('.varian-chip').forEach(chip => {
                chip.classList.toggle('aktif', chip.dataset.id === String(varian.id));
            });

            detailSunting.classList.remove('d-none');
            simpanSunting.disabled = false;
            judulSunting.textContent = 'Sunting ' + varian.kode + (varian.ukuran === '' ? '' : ' ' + varian.ukuran);
        }

        // chip pemilih dibangun ulang tiap modal dibuka: kartu yang satu tidak
        // boleh meninggalkan varian milik kartu sebelumnya di dalam modal
        function isiPemilih(daftar) {
            daftarVarian.innerHTML = '';

            daftar.forEach(varian => {
                const chip = document.createElement('button');
                const titik = document.createElement('span');
                const teks = document.createElement('span');

                chip.type = 'button';
                chip.className = 'varian-chip';
                chip.dataset.id = varian.id;
                chip.title = (varian.keterangan === '' ? varian.ukuran : varian.keterangan) + ' · stok ' + varian.stok;

                titik.className = 'varian-dot ' + keadaan(varian.stok);
                teks.className = 'text-truncate';
                teks.textContent = (varian.ukuran === '' ? 'Tanpa ukuran' : varian.ukuran) + ' (' + varian.stok + ')';

                chip.append(titik, teks);
                daftarVarian.append(chip);
            });
        }

        daftarVarian.addEventListener('click', event => {
            const chip = event.target.closest('.varian-chip');

            if (chip) {
                pilihVarian(suntingAktif.varian.find(v => String(v.id) === chip.dataset.id));
            }
        });

        modalSunting.addEventListener('show.bs.modal', event => {
            const tombol = event.relatedTarget;

            // sunting yang gagal: nilai yang tadi diketik dipulihkan, bukan nilai lama
            if (suntingLama) {
                suntingAktif = { kode: suntingLama.kode, varian: [suntingLama], poin: suntingLama.poin };
                isiPemilih(suntingAktif.varian);
                pilihVarian(suntingLama);

                if (fields.poin) {
                    fields.poin.value = suntingLama.poin || 0;
                }

                suntingLama = null;

                return;
            }

            const kartu = tombol ? tombol.getAttribute('data-kartu') : null;

            if (!kartu) {
                kosongSunting();

                return;
            }

            suntingAktif = JSON.parse(kartu);
            isiPemilih(suntingAktif.varian);
            judulSunting.textContent = 'Sunting ' + suntingAktif.kode;

            // poin milik produk (satu nilai untuk semua ukuran): diisi sekali saat kartu dibuka
            if (fields.poin) {
                fields.poin.value = suntingAktif.poin || 0;
            }

            // tombol Edit memaksa pengguna memilih varian dulu supaya tidak salah ukuran
            sembunyikanDetail();
        });

        // hapus varian dan hapus kartu berbagi satu modal konfirmasi: pemicunya
        // selalu mencegah submit lebih dulu, jadi bentukannya ikut dipakai tombol
        // keyboard dan tidak ada jalan menghapus tanpa pertanyaan
        const modalHapus = document.getElementById('modalHapusProduk');
        const objekHapus = document.getElementById('hapusObjek');
        const tombolHapus = document.getElementById('hapusYa');

        let jadiHapus = null;

        function tanyaHapus(objek, kirim) {
            objekHapus.textContent = objek;
            jadiHapus = kirim;
            bootstrap.Modal.getOrCreateInstance(modalHapus).show();
        }

        tombolHapus.addEventListener('click', () => {
            const kirim = jadiHapus;

            jadiHapus = null;
            bootstrap.Modal.getOrCreateInstance(modalHapus).hide();

            if (kirim) {
                kirim();
            }
        });

        // hapus per varian: tombolnya hidup di tabel modal detail (klon per kartu),
        // jadi delegasi ke dokumen; id varian diisi sesaat sebelum form-nya berangkat
        $(document).on('click', '[form="formHapusBarang"]', function (event) {
            const data = JSON.parse($(this).attr('data-hapus-varian'));

            event.preventDefault();

            tanyaHapus(data.label, () => {
                formHapus.querySelector('[name="id_stok"]').value = data.id;
                formHapus.submit();
            });
        });

        // hapus per kartu: satu produk pergi bersama semua variannya, jadi
        // konfirmasinya menyebut jumlah varian supaya tidak terasa kejutan
        document.querySelectorAll('[form="formHapusKartu"]').forEach(button => {
            button.addEventListener('click', event => {
                const data = JSON.parse(button.getAttribute('data-hapus-kartu'));

                event.preventDefault();

                tanyaHapus(data.kode + ' · ' + data.varian + ' varian', () => {
                    formHapusKartu.querySelector('[name="kode"]').value = data.kode;
                    formHapusKartu.querySelector('[name="juragan_id"]').value = data.juragan;
                    formHapusKartu.submit();
                });
            });
        });

        // "+ Varian" pada kartu hanya menanyakan ukuran, stok, dan foto: nama
        // barang dikunci ke kartunya, juragan dan harga ikut kartu. Tombol
        // "Tambah Barang" di kepala daftar tetap form lengkap karena kartunya
        // belum ada sama sekali. `bersih` menandai dibuka lewat tombol: isian
        // lama dibuang supaya kartu terakhir tidak jadi bayangan di form
        // berikutnya; pemulihan habis mengirim memanggil ini dengan `bersih`
        // false agar nilai yang diketik tetap ada.
        function tataTambah(kartu, bersih) {
            const kode = modalTambah.querySelector('[name="kode"]');
            const ukuran = modalTambah.querySelector('[name="ukuran"]');
            const stok = modalTambah.querySelector('[name="stok"]');
            const juragan = modalTambah.querySelector('[name="juragan_id"]');
            const harga = modalTambah.querySelector('[name="harga"]');
            const petunjuk = document.getElementById('tambahKodePetunjuk');

            modalTambah.querySelectorAll('.tambah-khusus-barang').forEach(blok => {
                blok.classList.toggle('d-none', kartu !== null);
            });

            kode.readOnly = kartu !== null;
            harga.required = kartu === null;
            modalTambah.querySelector('[name="kartu"]').value = kartu === null ? '0' : '1';

            if (bersih) {
                ukuran.value = '';
                stok.value = '1';
                harga.value = '';
                modalTambah.querySelector('[name="keterangan"]').value = '';
                kode.value = kartu === null ? '' : kartu.kode;

                const poinTambah = modalTambah.querySelector('[name="poin"]');

                if (poinTambah) {
                    poinTambah.value = kartu ? (kartu.poin || 0) : '0';
                }
            }

            if (kartu === null) {
                judulTambah.textContent = 'Tambah Barang';
                return;
            }

            if (!bersih) {
                kode.value = kartu.kode;
            }

            for (let i = 0; i < juragan.options.length; i++) {
                if (juragan.options[i].value === String(kartu.juragan)) {
                    juragan.value = juragan.options[i].value;

                    break;
                }
            }

            judulTambah.textContent = 'Tambah Varian ' + kartu.kode;
            petunjuk.textContent = 'Nama barang mengikuti kartu ini; harganya diwarisi dari varian yang sudah ada dan bisa diubah lewat Edit.';
        }

        modalTambah.addEventListener('show.bs.modal', event => {
            const tombol = event.relatedTarget;
            const bawaan = tombol ? tombol.getAttribute('data-tambah') : null;

            if (bawaan) {
                tataTambah(JSON.parse(bawaan), true);

                return;
            }

            // modal dibuka sendiri sesudah gagal simpan: modalitenya dipulihkan
            // dari nilai yang masih ada di dalam form, isian tidak dibuang
            if (gagalTambah) {
                gagalTambah = false;
                const kode = modalTambah.querySelector('[name="kode"]').value;
                const juragan = modalTambah.querySelector('[name="juragan_id"]').value;

                tataTambah(varianLama && kode !== '' ? { kode: kode, juragan: juragan } : null, false);

                return;
            }

            tataTambah(null, true);
        });

        // min="1" hanya mengunci spinner dan memvalidasi saat form dikirim; tanda
        // minus dan angka nol tetap bisa diketik manual. Dibersihkan di sini: apa
        // pun selain angka dibuang, nol di depan dilucuti, dan kolom kosong diisi
        // ulang jadi 1 saat kehilangan fokus. Hanya untuk tambah; sunting masih
        // boleh 0/negatif untuk koreksi salah hitung gudang.
        const stokTambah = modalTambah.querySelector('[name="stok"]');

        if (stokTambah) {
            stokTambah.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').replace(/^0+/, '');
            });
            stokTambah.addEventListener('blur', function () {
                if (this.value === '') {
                    this.value = '1';
                }
            });
        }

        // Stok pada form sunting juga jangan bisa diketik minus atau nol: non-digit
        // dibuang, nol di depan dilucuti. Sengaja tanpa min="1" dan tanpa blur->1,
        // supaya stok 0 yang sudah tersimpan (habis) tetap bisa disimpan saat
        // pengguna hanya mengubah field lain pada varian itu.
        if (fields.stok) {
            fields.stok.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').replace(/^0+/, '');
            });
        }

        if (suntingErr) {
            bootstrap.Modal.getOrCreateInstance(modalSunting).show();
        }

        // gagal menambah: modal dibuka lagi supaya isian tadi tidak perlu diketik ulang
        if (gagalTambah) {
            bootstrap.Modal.getOrCreateInstance(modalTambah).show();
        }

        // Panel foto produk dimuat saat tombolnya ditekan: satu halaman bisa
        // berisi 20 kartu dan hampir semuanya tidak dibuka, jadi fotonya tidak
        // perlu ditarik semua. Isi lama dibuang tiap modal ditutup supaya kartu
        // berikutnya tidak sempat menampilkan foto kartu sebelumnya.
        const modalFoto = document.getElementById('modalFotoProduk');
        const muatFoto = modalFoto.querySelector('.foto-muat');
        const judulFoto = modalFoto.querySelector('.modal-title');

        // Penanda permintaan terakhir. 'show.bs.modal' dilempar SEBELUM Bootstrap
        // memasang kelas .show (kelas itu baru ada setelah transisi fade selesai),
        // jadi mengecek .show di sini balapan: jawaban pertama (kirim lambat) lolos,
        // jawaban berikutnya (sudah ter-cache, balik instan) datang sebelum .show
        // terpasang sehingga kontennya tidak pernah dipasang. Urutan permintaan yang
        // jadi acuan, bukan kelas .show.
        let fotoReq = 0;

        modalFoto.addEventListener('show.bs.modal', event => {
            const tombol = event.relatedTarget;

            if (!tombol || !tombol.hasAttribute('data-stok')) {
                return;
            }

            const req = ++fotoReq;

            judulFoto.textContent = 'Foto ' + tombol.getAttribute('data-nama');
            muatFoto.innerHTML = '<div class="text-center text-muted py-4"><i class="fal fa-spinner fa-spin me-2"></i>Fotonya dimuat...</div>';

            const id = tombol.getAttribute('data-stok');

            $.get(alamatFoto + '/' + id)
                .done(html => {
                    // hanya jawaban permintaan terakhir yang dipakai; modal bisa
                    // sempat ditutup atau berpindah kartu sebelum jawaban datang
                    if (req === fotoReq) {
                        muatFoto.innerHTML = html;
                    }
                })
                .fail(() => {
                    if (req === fotoReq) {
                        muatFoto.innerHTML = '<div class="alert alert-danger py-2 mb-0">Foto produk gagal dimuat, coba lagi ya.</div>';
                    }
                });
        });

        modalFoto.addEventListener('hidden.bs.modal', () => {
            fotoReq++; // batalkan permintaan yang masih mengantung
            muatFoto.innerHTML = '';
            if (window.FOTO) { window.FOTO.reset(); }
            document.getElementById('btnSimpanFoto').classList.add('d-none');
            modalFoto.querySelector('.foto-status').classList.add('d-none');
        });

        // klik di mana pun pada kartu membuka rangkapan detailnya; tombol aksi
        // dan tombol foto berhenti sendiri karena punya handler masing-masing
        const modalDetail = document.getElementById('modalDetailProduk');
        const isiDetail = modalDetail.querySelector('.modal-body');
        const judulDetail = modalDetail.querySelector('.modal-title');

        function bukaDetail(kartu, sorot) {
            judulDetail.textContent = kartu.querySelector('h2').textContent.trim();
            isiDetail.innerHTML = '';

            const cetakan = kartu.querySelector('.produk-rincian').cloneNode(true);
            cetakan.classList.remove('d-none');
            isiDetail.appendChild(cetakan);

            // ukuran yang tadi diklit ditandai barisnya di tabel supaya pengguna
            // tahu varian mana yang dicarinya
            if (sorot) {
                const baris = isiDetail.querySelector('tr[data-varian="' + sorot + '"]');

                if (baris) {
                    baris.classList.add('varian-sorot');
                    baris.scrollIntoView({ block: 'nearest' });
                }
            }

            bootstrap.Modal.getOrCreateInstance(modalDetail).show();
        }

        document.querySelectorAll('.produk-kartu').forEach(kartu => {
            kartu.addEventListener('click', event => {
                // klik tombol apa pun (termasuk anak-anaknya: ikon, angka) tidak
                // membuka detail; svg dilewati dulu karena font-awesome kit bisa
                // mengganti <i> dengan <svg> yang tidak punya parent HTML
                const dipencet = event.target.closest ? (event.target.closest('svg') ? event.target.closest('svg').parentElement : event.target) : event.target;

                if (dipencet.closest && dipencet.closest('button, a, [data-bs-toggle], [form]')) {
                    return;
                }

                bukaDetail(kartu);
            });
        });

        // chip varian tidak lagi membuka form sunting per ukuran: ukuran hanya
        // penanda, jadi klik membawa pengguna ke detail kartunya
        document.querySelectorAll('.varian-chip[data-varian]').forEach(chip => {
            chip.addEventListener('click', () => {
                bukaDetail(chip.closest('.produk-kartu'), chip.getAttribute('data-varian'));
            });
        });

        // galeri mini di modal detail: thumbnail menukar foto utama, foto utama
        // membuka versi penuh di lapisan lightbox (berkas asli, bukan thumbnail)
        $(document)
            .on('click', '#modalDetailProduk .foto-thumbs', function () {
                // sengaja tanpa awalan dollar: PHP 8.3 ke atas me-interpolasi
                // variabel di dalam heredoc walau ditulis dengan backslash,
                // dan nama "this" menabrak objek View yang sedang merender
                const thumb = $(this);

                thumb.closest('.foto-galeri').find('.foto-thumbs').removeClass('aktif');
                thumb.addClass('aktif');
                thumb.closest('.row').find('.foto-utama')
                    .attr('data-full', thumb.attr('data-full'))
                    .find('img')
                    .attr('src', thumb.find('img').attr('src'));
            })
            .on('click', '#modalDetailProduk .foto-utama', function () {
                $('#lightboxProduk img').attr('src', $(this).attr('data-full') || $(this).find('img').attr('src'));
                $('#lightboxProduk').addClass('tampil');
            })
            .on('click', '#lightboxProduk', function () {
                $(this).removeClass('tampil');
            });

        // Esc ditangkap lebih dulu (capture) supaya lightbox yang menutup, bukan
        // modal di baliknya; tanpa ini satu Esc menutup dua-duanya sekaligus
        document.addEventListener('keydown', event => {
            const lapisan = document.getElementById('lightboxProduk');

            if (event.key === 'Escape' && lapisan.classList.contains('tampil')) {
                event.stopPropagation();
                event.preventDefault();
                lapisan.classList.remove('tampil');
            }
        }, true);
    });
    JS;

echo '<script>' . (new Tholu\Packer\Packer($js, 'Normal', true, false, true))->pack() . '</script>';
?>
<?= $this->endSection() ?>
