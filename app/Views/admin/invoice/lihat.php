<?php

/**
 * Variabel berikut dikirim Admin\Invoices::lihat() lewat view() lalu di-extract
 * CI4 saat render, sehingga tidak terlihat oleh analis statis (Intelephense P1008).
 *
 * @var string       $title             judul halaman
 * @var array        $orderan           daftar invoice pada tab aktif
 * @var string       $juragan           slug toko aktif atau 'semua'
 * @var int          $juragan_id        id toko aktif (0 = semua juragan)
 * @var string       $nama_toko         nama toko aktif
 * @var array        $daftar_toko       pilihan toko: [slug => nama]
 * @var array|string $cari              kata kunci pencarian dari query string
 * @var string       $hal               tab/route segmen halaman aktif
 * @var string       $kategori          saringan kategori pembayaran
 * @var array        $kategori_pembayaran master kategori: [slug => ['label' => ...]]
 * @var array        $jumlah_kategori   jumlah transaksi per kategori: [slug => int]
 * @var int          $limit             jumlah baris per halaman
 * @var int          $page              nomor halaman aktif
 */

use App\Libraries\Ongkir;
use CodeIgniter\I18n\Time;

$ongkir   = new Ongkir();
$pager    = \Config\Services::pager();
$sekarang = new Time('now');
$session  = \Config\Services::session();

// Menilai apakah dana yang masuk benar-benar ada adalah keputusan pemilik toko,
// jadi hanya Superadmin yang boleh mengecek pembayaran. Tombol "Cek Pembayaran"
// sendiri tetap ada untuk semua orang: yang dibatasi cuma aksi penilaiannya.
$cekBayar = ((string) $session->get('level')) === 'superadmin';

?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
/* Toolbar transaksi dulu dirakit dari utility flex + ms-sm-auto, begitu tab dan
   tombol aksi tidak muat satu baris kelompok tombol jatuh ke baris kedua dan
   tepinya tidak lagi lurus dengan tab. Dipakai satu baris flex dengan padding
   tombol yang lebih ramping supaya tetap muat sampai layar ±1000px. */
.toolbar-transaksi {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem 0.5rem;
}
.toolbar-transaksi__tab {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex: 1 1 auto;
    min-width: 0;
    /* tab yang tidak muat digeser lewat tombol panah, jadi wadahnya selalu bisa
       digulir mendatar; gulir rodanya sendiri ditutup karena sudah ada tombolnya */
    overflow-x: auto;
    scrollbar-width: none;
    overscroll-behavior-x: contain;
}
.toolbar-transaksi__tab::-webkit-scrollbar {
    display: none;
}

/* Tombol geser strip tab. Panahnya hanya ditampilkan kalau isinya memang lebih
   lebar dari wadahnya (class .bisa-geser dipasang dari JS), jadi di layar besar
   tidak ada tombol yang tidak berguna. */
.toolbar-transaksi__geser {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex: 1 1 auto;
    min-width: 0;
}
.geser-tab {
    display: none;
    flex: 0 0 auto;
    width: 1.9rem;
    height: 1.9rem;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 1px solid #eaecf0;
    border-radius: 999px;
    background: #ffffff;
    color: #344054;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.06);
    font-size: 0.8rem;
    line-height: 1;
}
.toolbar-transaksi__geser.bisa-geser .geser-tab {
    display: inline-flex;
}
.geser-tab:disabled {
    opacity: 0.35;
    box-shadow: none;
}
/* Panah geser hanya untuk layar sempit (mobile/tablet < 992px). Di desktop strip
   tab sudah dipadatkan supaya semua tab tampil, jadi panah tidak diperlukan lagi —
   !important menjamin ia tetap mati walau JS sempat memasang .bisa-geser. */
@media (min-width: 992px) {
    .toolbar-transaksi__geser.bisa-geser .geser-tab {
        display: none !important;
    }
}
.toolbar-transaksi__aksi {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex: 0 1 auto;
    margin-left: auto;
}
.toolbar-transaksi .btn {
    padding: 0.42rem 0.62rem;
    gap: 0.3rem;
    white-space: nowrap;
}
.toolbar-transaksi .badge {
    padding: 0.2rem 0.45rem !important;
}

/* Layar lebar: tab dan tombol aksi SATU baris. Sebelumnya baris ini boleh
   membungkus, jadi di bawah ±1500px kelompok aksi jatuh ke baris kedua sementara
   strip tab melebar sampai penuh — tepi atas keduanya tidak lagi sejajar.
   Yang menyusut adalah strip tab (panahnya sudah ada untuk itu); kelompok tombol
   aksi tidak boleh terimpit karena isinya nama toko + empat aksi utama. */
@media (min-width: 1280px) {
    .toolbar-transaksi {
        flex-wrap: nowrap;
    }
    .toolbar-transaksi__aksi {
        flex: 0 0 auto;
    }
}

/* Desktop (≥992px): tab dan tombol aksi dipadatkan supaya semua tab muat satu
   baris tanpa panah. Angkanya dari pengukuran: dengan bentuk asli baris ini butuh
   ±1153px sedangkan tempatnya cuma 962 (1280px) - 1048 (1366px), jadi tanpa
   pemadatan ini tetap tidak muat. Dulu hanya 1280-1500px; dilebarkan ke ≥992px
   agar seragam di semua layar desktop dan panah benar-benar jadi milik mobile. */
@media (min-width: 992px) {

    .toolbar-transaksi {
        gap: 0.35rem;
    }

    .toolbar-transaksi__tab {
        gap: 0.2rem;
    }

    .toolbar-transaksi__tab .btn {
        min-height: 2rem;
        padding: 0.3rem 0.38rem;
        gap: 0.25rem;
        font-size: 0.76rem;
    }

    .toolbar-transaksi__tab .badge {
        padding: 0.1rem 0.3rem !important;
        font-size: 0.66rem;
    }

    .toolbar-transaksi__aksi {
        gap: 0.2rem;
    }

    .toolbar-transaksi__aksi .btn {
        min-height: 2rem;
        padding: 0.3rem 0.45rem;
        font-size: 0.76rem;
    }
}

/* Tinggi semua tombol di baris ini disamakan: "Tulis Orderan" memakai latar
   merek tanpa garis tepi, jadi 2px lebih pendek dari pil dan tombol lain. */
.toolbar-transaksi .btn {
    min-height: 2.125rem;
}

@media (max-width: 767.98px) {
    /* tab digulir mendatar supaya tetap satu baris dan sejajar, tombol aksi
       turun ke barisnya sendiri dengan nama toko mengisi sisa lebar. Yang turun
       ke baris penuh adalah pembungkusnya, strip tab tetap berbagi baris dengan
       kedua tombol panah. */
    .toolbar-transaksi__geser {
        flex: 1 1 100%;
        min-width: 0;
    }
    .toolbar-transaksi__tab {
        padding-bottom: 0.15rem;
    }
    .toolbar-transaksi__tab .btn {
        flex: 0 0 auto;
    }
    .toolbar-transaksi__aksi {
        flex: 1 1 100%;
        margin-left: 0;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }
    .toolbar-transaksi__aksi > .dropdown {
        flex: 1 1 auto;
        min-width: 0;
    }
    .toolbar-transaksi__aksi > .dropdown .btn {
        width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        text-align: left;
    }
    /* Tombol aksi ikut mengisi lebar baris. Sebelumnya hanya pemilih toko yang
       melebar sedangkan Cari/Scan/Tulis menciut di kiri, jadi di akun yang
       tokonya cuma satu — pemilih toko memang tidak dirender — separuh baris
       menganggur. Nama tokonya tetap dapat bagian terbesar karena basisnya
       auto, sementara tombol mulai dari nol. */
    .toolbar-transaksi__aksi > .btn {
        flex: 1 1 0;
        min-width: 0;
    }
}

/* Baris "juragan: / admin/cs:" dan tombol cetak di kepala kartu. Dua-duanya
   tetap tautan yang bisa diklik tapi tidak perlu bergaris bawah: tanpa Bootstrap
   CSS, tautan memakai garis bawah bawaan peramban yang membuatnya terlihat
   seperti salah potong. */
.meta-transaksi a,
.aksi-kepala a {
    text-decoration: none;
}

/* Kotak filter kategori/status pembayaran. Dulu selebar isinya saja (w-auto)
   sehingga separuh kanan kartu menganggur; sekarang ia memanjang mengisi
   sisanya. Di layar sempit labelnya keburu mendesak, jadi kotaknya yang naik
   ke baris sendiri dan melebar sampai tepi kartu. */
@media (max-width: 575.98px) {
    #filterKategori {
        width: 100%;
    }
}
@media (max-width: 1023.98px) {
    .timeliner {
        margin-left: 0 !important;
        margin-right: auto;
        /* dulu selebar isinya saja (±120px) sehingga separuh kanan baris menganggur;
           sekarang ia mengisi lebarnya dan tiap langkah kebagian sama besar, jadi
           langkah tambahan otomatis menyusut dan tetap muat sampai batasnya */
        width: 100%;
    }
    .timeliner > li {
        flex: 1 1 0;
    }
}

/* Kepala blok rangkap (Produk / Anggota Rombongan) di layar sempit. Dua hal:
   - <button> membawa text-align:center dari stylesheet peramban, jadi label dua
     kata yang turun ke baris kedua tampil di tengah; dipaksa rata kiri supaya
     sejajar dengan ikonnya.
   - ms-auto tadinya ada di panah, jadi angka "1 pcs" menggantung di tengah baris
     sementara "4 orang" kepepet ke pojok. ms-auto dipindah ke angkanya. */
@media (max-width: 575.98px) {
    .kartu-invoice .blok-lipat > h6 > button {
        text-align: left;
    }
    .kartu-invoice .blok-lipat > h6 > button > span:first-of-type {
        margin-left: auto !important;
    }
    .kartu-invoice .blok-lipat > h6 > button > i:last-child {
        margin-left: 0 !important;
    }
}

/* Di desktop: tombol aksi berjajar rapi di kiri, tombol Hapus berdampingan dengan Tambah Pembayaran */
.kartu-invoice .aksi {
    justify-content: flex-start !important;
}
.kartu-invoice .aksi .aksi-hapus {
    margin-left: 0 !important;
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">

    <h1 class="h3 mt-5"><?= esc($title) ?></h1>

    <?php
    // [slug tab, label pendek, id badge counter, keterangan tooltip]
    $tab = [
        ['semua', 'Semua', 'counterSemua', 'Semua transaksi'],
        ['pembayaran', 'Pembayaran', 'counterCekBayar', 'Transaksi yang pembayarannya perlu dicek'],
        ['belum-proses', 'Belum Proses', 'counterBelumProses', 'Transaksi yang belum mulai dikerjakan'],
        ['dalam-proses', 'Dalam Proses', 'counterDalamProses', 'Transaksi yang sedang dikerjakan'],
        ['selesai', 'Selesai', 'counterSelesai', 'Transaksi yang sudah selesai; nota sewa baru masuk sini setelah barangnya dicatat kembali'],
        ['masih-disewa', 'Masih Disewa', 'counterMasihDisewa', 'Nota sewa yang pengembaliannya belum dicatat dan dananya sudah masuk: DP, sedang dicicil, atau lunas'],
    ];
    ?>
    <div class="toolbar-transaksi mb-4">
        <div class="toolbar-transaksi__geser">
            <button type="button" class="geser-tab" data-geser="-1" aria-label="Geser daftar tab ke kiri" title="Geser tab ke kiri"><i class="fal fa-chevron-left"></i></button>

            <div class="toolbar-transaksi__tab">
                <?php
                foreach ($tab as [$slug, $label, $counter, $keterangan]) {
                    echo anchor(
                        'admin/invoices/lihat/' . $juragan . '/' . $slug,
                        $label . ' <span class="badge rounded-pill bg-ink-100 text-ink-600" id="' . $counter . '">0</span>',
                        [
                            'class' => 'btn rounded-pill btn-' . ($hal === $slug ? 'primary' : 'outline-secondary'),
                            'title' => $keterangan,
                        ]
                    );
                }
                ?>
            </div>

            <button type="button" class="geser-tab" data-geser="1" aria-label="Geser daftar tab ke kanan" title="Geser tab ke kanan"><i class="fal fa-chevron-right"></i></button>
        </div>

        <div class="toolbar-transaksi__aksi">
            <?php
            // Tab, filter kategori, dan kata yang sedang dicari ikut dibawa ke
            // tautan toko supaya ganti toko tidak melempar user ke tab lain.
            $kueri_cari = ($cari === '' || $cari === []) ? '' : '?' . http_build_query(['cari' => (array) $cari]);
            ?>
            <?php if (count($daftar_toko) > 1) : ?>
                <div class="dropdown">
                    <button type="button" class="btn rounded-pill btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Pilih djuragan">
                        <i class="fal fa-user-circle me-1"></i> <?= esc($nama_toko) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="max-height:60vh;overflow:auto">
                        <li>
                            <a class="dropdown-item<?= $juragan === 'semua' ? ' active' : '' ?>" href="<?= site_url('admin/invoices/lihat/semua/' . $hal . '/' . $kategori) . $kueri_cari ?>">Semua Juragan</a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <?php foreach ($daftar_toko as $slug => $nama) : ?>
                            <li>
                                <a class="dropdown-item<?= $juragan === $slug ? ' active' : '' ?>" href="<?= esc(site_url('admin/invoices/lihat/' . rawurlencode($slug) . '/' . $hal . '/' . $kategori) . $kueri_cari) ?>"><?= esc($nama) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?= form_button([
                'class'          => 'btn rounded-pill btn-outline-secondary',
                'content'        => '<i class="fal fa-search"></i> <span class="hidden sm:inline">Cari</span>',
                'title'          => 'Cari transaksi',
                'data-bs-target' => '#modalCari',
                'data-bs-toggle' => 'modal',
            ]) ?>
            <?= form_button([
                'class'          => 'btn rounded-pill btn-outline-secondary',
                'content'        => '<i class="fal fa-qrcode"></i> <span class="hidden sm:inline">Scan</span>',
                'title'          => 'Scan QR nota dari invoice / lembar penjahit untuk mencari transaksi',
                'id'             => 'tombolScan',
            ]) ?>
            <?= anchor('admin/invoices/tulis', '<i class="fal fa-pen-nib"></i> <span class="hidden sm:inline">Tulis Orderan</span>', [
                'class' => 'btn rounded-pill border-0 bg-brand-500 font-semibold text-white hover:bg-brand-600 shrink-0',
                'title' => 'Buat transaksi baru',
            ]) ?>
        </div>
    </div>

    <?php if (in_array($hal, ['pembayaran', 'belum-proses', 'saring'], true)) : ?>
        <?php
        // pilihan filter dibungkus label kategorinya plus jumlah orderan pada kategori itu.
        // tab saring angkanya sudah dibaca di dasbor, jadi labelnya polos
        $opsi_kategori = [];

        foreach ($kategori_pembayaran as $slug => $k) {
            $opsi_kategori[$slug] = $jumlah_kategori === []
                ? $k['label']
                : $k['label'] . ' (' . formatRibuan($jumlah_kategori[$slug] ?? 0) . ')';
        }

        $label_filter = match ($hal) {
            'belum-proses' => 'Status pembayaran',
            'saring'       => 'Saringan orderan',
            default        => 'Kategori pembayaran',
        };
        ?>
        <div class="mb-4 rounded-2xl border border-ink-200 bg-[#fcfdfe] px-3.5 py-3">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <label class="text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500" for="filterKategori"><?= esc($label_filter) ?></label>

                <?= form_dropdown('kategori', $opsi_kategori, $kategori, [
                    'class'       => 'form-select form-select-sm w-auto flex-fill min-w-0',
                    'id'          => 'filterKategori',
                    'data-tautan' => site_url('admin/invoices/lihat/' . $juragan . '/' . $hal),
                ]) ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($orderan['data'] === []) : ?>
        <div class="mb-4 rounded-2xl border border-dashed border-ink-200 bg-[#fcfdfe] px-4 py-6 text-center text-ink-500">
            <i class="fal fa-inbox block text-[1.35rem] text-ink-400 mb-2"></i>
            <?php if ($hal === 'saring') : ?>
                Belum ada orderan pada saringan <?= esc($kategori_pembayaran[$kategori]['label']) ?>.
            <?php elseif ($hal === 'masih-disewa') : ?>
                Belum ada barang yang sedang disewa. Tab ini hanya memuat nota sewa yang dananya sudah masuk (DP, dicicil, atau lunas) dan pengembaliannya belum dicatat — nota sewa yang belum dibayar sama sekali dicek di tab <strong>Pembayaran</strong> atau <strong>Belum Proses</strong>.
            <?php elseif ($kategori !== 'semua' && in_array($hal, ['pembayaran', 'belum-proses'], true)) : ?>
                Belum ada transaksi berkategori <?= esc($kategori_pembayaran[$kategori]['label']) ?>.
            <?php else : ?>
                Belum ada transaksi di tab ini.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php
    // satu kartu di layar = orderan dibuka sendiri. Aturan "kalau kosong tampilkan
    // -" hanya berlaku di mode itu, supaya daftar panjang tidak ikut penuh baris kosong
    $satu_orderan = count($orderan['data']) === 1;

    foreach ($orderan['data'] as $pesanan) { ?>
        <?php
        // warna pill status dan panel wajib bayar (pengganti text-bg-* dan list-group-item-*)
        $pil = [
            'danger'  => ['pill' => 'bg-red-600 text-white', 'band' => 'bg-red-100 text-red-800'],
            'warning' => ['pill' => 'bg-tunggu text-ink-900', 'band' => 'bg-amber-100 text-amber-800'],
            'primary' => ['pill' => 'bg-blue-600 text-white', 'band' => 'bg-blue-100 text-blue-800'],
            'success' => ['pill' => 'bg-selesai text-white', 'band' => 'bg-emerald-100 text-emerald-800'],
        ];
        $kelas_status = status_pembayaran($pesanan->pembayaran, $pesanan->status_pembayaran, 'l_class');
        ?>
        <?php
        $count_barang = 0;
        $harga_barang = 0;

        foreach ($pesanan->barang as $b) {
            $count_barang += $b->qty;
            $harga_barang += $b->qty * $b->harga;
        }

        $wajib_bayar = $harga_barang;
        $time        = Time::createFromFormat('Y-m-d', $pesanan->tanggal_pesan);

        // penyewaan berjalan = barang sudah diserahkan DAN pembayaran sudah lunas,
        // sementara pengembaliannya belum dicatat. Keadaan inilah yang memakan stok
        // kolam sewa dan satu-satunya yang boleh menekan tombol "Sudah kembali";
        // tanggal jadual di rincian cuma pengingat, bukan kenyataan barangnya
        $adalah_sewa    = \App\Models\InvoiceModel::adalahSewa($pesanan->rincian ?? null);
        $sewa_berjalan  = \App\Models\InvoiceModel::sewaMenunggu($pesanan);
        $sewa_pulang    = $adalah_sewa && ! $sewa_berjalan;
        $siap_kembali   = \App\Models\InvoiceModel::masihDisewa($pesanan);
        $alasan_kembali = \App\Models\InvoiceModel::alasanBelumDisewa($pesanan);
        ?>
        <div class="kartu-invoice mb-4 rounded-2xl sm:rounded-[1.5rem] border border-ink-200 bg-white shadow-sm transition hover:shadow-md">
            <div class="p-3.5 sm:p-5">
                <div class="flex flex-col lg:flex-row justify-between items-start gap-3 lg:gap-4">
                    <div class="min-w-0 w-full lg:w-auto">
                        <div class="flex flex-wrap items-center gap-2 sm:gap-x-3 sm:gap-y-2">
                            <span class="inline-flex h-[2.6rem] w-[2.6rem] shrink-0 items-center justify-center rounded-[0.9rem] border border-brand-100 bg-brand-50 text-lg text-brand-500 shadow-xs"><i class="fal fa-receipt"></i></span>

                            <div class="min-w-0">
                                <h5 class="text-[1.15rem] font-extrabold tracking-[-0.01em] text-ink-900 mb-0">#<?= esc($pesanan->seri) ?></h5>
                                <p class="text-ink-500 text-xs mb-0">( <?= $time->toLocalizedString('EEEE, d MMMM yyyy') ?> )</p>
                                <?php /* penanda kedua untuk API publik: nomor nota saja terlalu mudah ditebak,
                                   jadi status orderan baru dibuka kalau nomor + kode ini sama-sama cocok */ ?>
                                <?php if (! empty($pesanan->kode_lacak)) { ?>
                                    <p class="text-ink-400 text-xs mb-0" title="Kode pendamping nomor nota saat status orderan dibuka lewat API pihak luar">
                                        kode lacak <span class="font-semibold text-ink-500"><?= esc($pesanan->kode_lacak) ?></span>
                                    </p>
                                <?php } ?>
                            </div>

                            <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $pil[$kelas_status]['pill'] ?>">
                                <?= esc(status_pembayaran($pesanan->pembayaran, $pesanan->status_pembayaran, 'label')) ?>
                            </span>

                            <?php
                            // tenggat hanya ditampilkan kalau memang diisi; order lama tetap polos
                            $dead = label_deadline($pesanan->deadline ?? null);

                            if ($dead['teks'] !== '-') :
                                $pil_dead = $dead['kelas'] === ''
                                    ? 'border border-ink-200 bg-ink-50 text-ink-700'
                                    : $pil[$dead['kelas']]['pill']; ?>
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $pil_dead ?>" title="Tenggat: <?= esc($dead['teks']) ?>">
                                        <i class="fal fa-clock me-1"></i><?= esc($dead['teks']) ?>
                                    </span>
                                <?php endif; ?>

                            <div class="aksi-kepala flex items-center gap-1">
                                <?php /* lembar kerja penjahit tidak berlaku untuk barang sewaan:
                                       tidak ada yang dijahit, jadi tombolnya tidak dirender */ ?>
                                <?php if (! $adalah_sewa) { ?>
                                    <a class="inline-flex cursor-pointer items-center gap-1 rounded-[0.7rem] border border-ink-200 bg-ink-50 px-2.5 py-1 text-sm text-ink-700 transition hover:bg-ink-100 hover:text-ink-900" href="<?= site_url('download/penjahit/' . $pesanan->seri) ?>" target="_blank" rel="noopener" title="Cetak lembar kerja penjahit (tanpa harga)">
                                        <i class="fal fa-cut text-ink-500"></i> <span class="hidden md:inline">Penjahit</span>
                                    </a>
                                <?php } ?>
                                <a class="inline-flex cursor-pointer items-center gap-1 rounded-[0.7rem] border border-ink-200 bg-ink-50 px-2.5 py-1 text-sm text-ink-700 transition hover:bg-ink-100 hover:text-ink-900" href="<?= site_url('download/invoice/' . $pesanan->seri) ?>" target="_blank" rel="noopener" title="Cetak invoice untuk pelanggan">
                                    <i class="fal fa-print text-ink-500"></i> <span class="hidden md:inline">Invoice</span>
                                </a>
                                <button type="button" class="inline-flex cursor-pointer items-center gap-1 rounded-[0.7rem] border border-ink-200 bg-ink-50 px-2 py-1 text-sm text-ink-700 transition hover:bg-ink-100 hover:text-ink-900 salinTautan" data-link="<?= esc(site_url('download/invoice/' . $pesanan->seri), 'attr') ?>" title="Salin tautan invoice">
                                    <i class="fal fa-copy text-ink-500"></i><span class="sr-only">Salin tautan</span>
                                </button>
                            </div>
                        </div>

                        <div class="meta-transaksi mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-ink-600">
                            <span class="inline-flex min-w-0 items-center gap-1 [&>.fal]:text-[0.85rem] [&>.fal]:text-ink-400">
                                <i class="fal fa-store"></i>
                                <span class="text-lowercase text-ink-500">Juragan:</span> <?= anchor('admin/invoices/lihat/' . $pesanan->juragan->slug, esc($pesanan->juragan->nama)) ?>
                            </span>
                            <span class="inline-flex min-w-0 items-center gap-1 [&>.fal]:text-[0.85rem] [&>.fal]:text-ink-400">
                                <i class="fal fa-user"></i>
                                <span class="text-lowercase text-ink-500">Admin/CS:</span> <?= esc($pesanan->pengguna->nama) ?>
                            </span>
                            <?php if (($pj = penanggung_jawab($pesanan->source->label ?? null)) !== '') { ?>
                            <span class="inline-flex min-w-0 items-center gap-1 [&>.fal]:text-[0.85rem] [&>.fal]:text-ink-400">
                                <i class="fal fa-user-check"></i>
                                <span class="text-lowercase text-ink-500">Dilayani Oleh:</span> <?= esc($pj) ?>
                            </span>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="min-w-0 shrink grow lg:basis-40 max-lg:w-full max-lg:overflow-x-auto pb-1">
                        <ul class="list-inline mb-0 timeliner max-lg:!ms-0">
                            <?= timeline_langkah('start full', 'plus-circle', 'Buat', $time, 'Pesanan Ditambahkan') ?>

                            <?= status_pembayaran($pesanan->pembayaran, $pesanan->status_pembayaran) ?>

                            <?php
                            // satu langkah per jenis status: gabung baris status yang sama
                            // supaya timeline tidak penuh langkah berulang
                            $langkah = [];

                            foreach ($pesanan->status as $status) {
                                $no = (int) $status->status;

                                if (! isset($langkah[$no])) {
                                    $langkah[$no] = [
                                        'masuk'   => $status->tanggal_masuk,
                                        'selesai' => $status->tanggal_selesai,
                                        'ket_masuk'   => $status->keterangan_masuk,
                                        'ket_selesai' => $status->keterangan_selesai,
                                    ];
                                } else {
                                    $langkah[$no]['masuk'] = $langkah[$no]['masuk'] === null
                                        ? $status->tanggal_masuk
                                        : min($langkah[$no]['masuk'], $status->tanggal_masuk);

                                    if ($status->tanggal_selesai !== null) {
                                        $langkah[$no]['selesai'] = $langkah[$no]['selesai'] === null
                                            ? $status->tanggal_selesai
                                            : max($langkah[$no]['selesai'], $status->tanggal_selesai);
                                        $langkah[$no]['ket_selesai'] = $status->keterangan_selesai;
                                    }
                                }
                            }

                            ksort($langkah);
                            $dipacking = isset($langkah[7]) && $langkah[7]['selesai'] !== null;

                            foreach ($langkah as $no => $l) {
                                echo status_orderan($no, $l['masuk'], $l['selesai'], $l['ket_masuk'], $l['ket_selesai']);
                            }
                            ?>
                            <?= status_pengiriman($pesanan->pengiriman, $pesanan->status_pengiriman) ?>
                        </ul>
                    </div>
                </div>

                <hr class="my-3 border-0 border-t border-ink-200 opacity-100" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-5">
                    <section class="col-span-1">
                        <div class="blok h-full rounded-2xl border border-ink-200 bg-[#fcfdfe] p-3.5 sm:p-4">
                            <h6 class="mb-2 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400">
                                <i class="fal fa-user-circle"></i>
                                <?= ($pesanan->kirimKepada_id === $pesanan->pemesan_id ? 'Pemesan / Kirim Kepada' : 'Pemesan') ?>
                            </h6>

                            <?php
                            $pelanggan = $pesanan->pelanggan;
                            ?>
                            <div class="text-sm leading-[1.55] text-ink-800">
                                <span class="block font-bold"><?= strtoupper($pelanggan->nama); ?></span>
                                <span class="block">
                                    <?php
                                    $i = 0;

                                    foreach (json_decode($pelanggan->hp) as $hp) {
                                        if ($i === 1) {
                                            echo '<span class="sr-only">/</span>';
                                        }
                                        echo '<span class="chip-hp me-1 inline-block rounded-md bg-ink-500 px-1.5 py-0.5 text-[0.75em] font-normal text-white">' . esc($hp) . '</span>';
                                        $i++;
                                    }
                                    ?>
                                </span>
                                <span class="block">
                                    <?php
                                    if ($pelanggan->cod === '1') {
                                        echo 'C.O.D';
                                    } else {
                                        echo strtoupper(esc($pelanggan->alamat)) . '<br/>' .
                                            strtoupper(esc($ongkir->kecamatan($pelanggan->kabupaten, $pelanggan->kecamatan)['subdistrict_name'])) . ', ' .
                                            strtoupper(esc($ongkir->kota($pelanggan->provinsi, $pelanggan->kabupaten)['city_name'])) . ', ' .
                                            strtoupper(esc($ongkir->provinsi($pelanggan->provinsi)['province_name'])) .
                                            esc($pelanggan->kodepos === '0' ? '' : ' - ' . $pelanggan->kodepos);
                                    }
                                    ?>
                                </span>
                            </div>

                            <?php
                            if ($pesanan->kirimKepada_id !== $pesanan->pemesan_id) {
                                $kirimKe = $pesanan->kirimKe; ?>
                                <h6 class="mb-2 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400 mt-4 border-t border-ink-200 pt-3"><i class="fal fa-map-marker-alt"></i> Kirim Kepada</h6>
                                <div class="text-sm leading-[1.55] text-ink-800">
                                    <span class="block font-bold"><?= strtoupper($kirimKe->nama); ?></span>
                                    <span class="block">
                                        <?php
                                        $i = 0;

                                        foreach (json_decode($kirimKe->hp) as $hp) {
                                            if ($i === 1) {
                                                echo '<span class="sr-only">/</span>';
                                            }
                                            echo '<span class="chip-hp me-1 inline-block rounded-md bg-ink-500 px-1.5 py-0.5 text-[0.75em] font-normal text-white">' . esc($hp) . '</span>';
                                            $i++;
                                        }
                                        ?>
                                    </span>
                                    <span class="block">
                                        <?php
                                        if ($kirimKe->cod === '1') {
                                            echo 'C.O.D';
                                        } else {
                                            echo strtoupper(esc($kirimKe->alamat)) . '<br/>' .
                                                strtoupper(esc($ongkir->kecamatan($kirimKe->kabupaten, $kirimKe->kecamatan)['subdistrict_name'])) . ', ' .
                                                strtoupper(esc($ongkir->kota($kirimKe->provinsi, $kirimKe->kabupaten)['city_name'])) . ', ' .
                                                strtoupper(esc($ongkir->provinsi($kirimKe->provinsi)['province_name'])) .
                                                esc($kirimKe->kodepos === '0' ? '' : ' - ' . $kirimKe->kodepos);
                                        }
                                        ?>
                                    </span>
                                </div>
                            <?php } ?>

                            <h6 class="mb-2 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400 mt-4 border-t border-ink-200 pt-3"><i class="fal fa-inbox-in"></i> Asal Orderan</h6>
                            <div class="text-sm leading-[1.55] text-ink-800"><?= $pesanan->source !== null ? label_asal($pesanan->source->id) : '<span class="text-muted">-</span>' ?></div>
                        </div>
                    </section>

                    <section class="col-span-1">
                        <div class="blok h-full rounded-2xl border border-ink-200 bg-[#fcfdfe] p-3.5 sm:p-4">
                            <h6 class="mb-2 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400"><i class="fal fa-receipt"></i> Info Biaya</h6>

                            <?php
                            foreach ($pesanan->biaya as $c) {
                                $wajib_bayar += $c->nominal;
                            }

                            $sudah_bayar = status_pembayaran($pesanan->pembayaran, $pesanan->status_pembayaran, 'sudah_bayar');

                            $sudah_lunas = tagihan_lunas($pesanan->status_pembayaran);
                            ?>

                            <div class="mb-3 flex items-center justify-between gap-3 rounded-xl px-4 py-3 <?= $pil[$kelas_status]['band'] ?>">
                                <span class="text-[0.72rem] font-bold uppercase tracking-[0.09em]">Wajib Bayar</span>
                                <span class="text-[1.35rem] font-extrabold max-sm:text-[1.15rem]"><?= number_to_currency($wajib_bayar, 'IDR'); ?></span>
                            </div>

                            <ul class="m-0 list-none p-0 text-[0.85rem]">
                                <li class="flex items-baseline justify-between gap-3 py-1">
                                    <span class="uppercase tracking-wide text-ink-500">Harga Produk</span>
                                    <span class="font-bold whitespace-nowrap"><?= number_to_currency($harga_barang, 'IDR'); ?></span>
                                </li>

                                <?php
                                foreach ($pesanan->biaya as $c) { ?>
                                    <li class="flex items-baseline justify-between gap-3 py-1">
                                        <span class="uppercase tracking-wide text-ink-500">
                                            <?php
                                            $biaya = 'Lainnya';
                                            if ($c->biaya_id === 1) {
                                                $biaya = 'Ongkir';
                                            }
                                            $label = $c->label;
                                            if ($c->label !== 'null' && $c->biaya_id !== 1) {
                                                $biaya = $c->label;
                                                $label = '';
                                            } elseif ($c->label === 'null') {
                                                $label = '';
                                            } ?>
                                            <span class="font-bold"><?= $biaya; ?></span>
                                            <?= $label; ?>
                                        </span>
                                        <span class="font-bold whitespace-nowrap <?= ($c->nominal < 0 ? 'text-red-600' : ''); ?>"><?= number_to_currency($c->nominal, 'IDR') ?></span>
                                    </li>
                                <?php
                                } ?>

                                <?php
                                $menunggu = status_pembayaran($pesanan->pembayaran, $pesanan->status_pembayaran, 'menunggu');
                                $sisa     = $wajib_bayar - $sudah_bayar;

                                if ($sudah_bayar > 0) { ?>
                                    <li class="flex items-baseline justify-between gap-3 py-1 mt-1 border-t border-ink-200 pt-2">
                                        <span class="uppercase tracking-wide text-ink-500">
                                            <span class="font-bold">Sudah</span>&nbsp;Bayar
                                            <?= form_button([
                                                'class'          => 'ms-1 cursor-pointer border-0 bg-transparent text-ink-900 pesanBayar',
                                                'content'        => '<i class="fal fa-info-circle"></i> <span class="sr-only">info</span>',
                                                'data-invoice'   => $pesanan->id_invoice,
                                                'data-juragan'   => $pesanan->juragan_id,
                                                'data-seri'      => $pesanan->seri,
                                                'data-bs-target' => '#modalBayar',
                                                'data-bs-toggle' => 'modal',
                                                'style'          => 'border:none; background:transparent',
                                            ]); ?>
                                        </span>
                                        <span class="font-bold whitespace-nowrap"><?= number_to_currency($sudah_bayar, 'IDR'); ?></span>
                                    </li>
                                <?php
                                }
                                if ($menunggu > 0) { ?>
                                    <li class="flex items-baseline justify-between gap-3 py-1">
                                        <span class="uppercase tracking-wide text-ink-500"><span class="font-bold">Menunggu</span>&nbsp;dicek</span>
                                        <span class="font-bold whitespace-nowrap"><?= number_to_currency($menunggu, 'IDR'); ?></span>
                                    </li>
                                <?php
                                }
                                if ($sisa > 0) { ?>
                                    <li class="flex items-baseline justify-between gap-3 py-1">
                                        <span class="uppercase tracking-wide text-ink-500"><span class="font-bold">Sisa</span>&nbsp;Bayar</span>
                                        <span class="font-bold whitespace-nowrap text-red-600"><?= number_to_currency($sisa, 'IDR'); ?></span>
                                    </li>
                                <?php
                                }
                                if ($sisa < 0) { ?>
                                    <li class="flex items-baseline justify-between gap-3 py-1">
                                        <span class="uppercase tracking-wide text-ink-500"><span class="font-bold">Lebih</span>&nbsp;Bayar</span>
                                        <span class="font-bold whitespace-nowrap"><?= number_to_currency(-$sisa, 'IDR'); ?></span>
                                    </li>
                                <?php
                                } ?>
                            </ul>
                        </div>
                    </section>

                </div>

                <div class="blok blok-lipat mt-4 overflow-hidden rounded-2xl border border-ink-200 bg-[#fcfdfe]">
                    <h6 class="mb-0">
                        <button class="flex w-full cursor-pointer items-center gap-1.5 border-0 bg-transparent px-[1.15rem] py-4 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400" type="button" data-bs-toggle="collapse" data-bs-target="#produk-<?= esc($pesanan->id_invoice) ?>" aria-expanded="false" aria-controls="produk-<?= esc($pesanan->id_invoice) ?>">
                            <i class="fal fa-tshirt"></i> Produk
                            <span class="inline-block rounded-full bg-ink-900 px-2 py-0.5 text-xs font-semibold text-white"><?= $count_barang; ?></span>
                            <span class="text-[0.8rem] font-semibold normal-case tracking-normal">pcs</span>
                            <i class="fal fa-chevron-down ms-auto transition rotate-[-90deg] aria-expanded:rotate-0"></i>
                        </button>
                    </h6>

                    <div class="collapse" id="produk-<?= esc($pesanan->id_invoice) ?>">
                        <div class="border-t border-ink-200 px-[1.15rem] py-3">
                            <?php $punya_ukuran = false; ?>
                            <ul class="m-0 list-none p-0 text-sm">
                                <?php foreach ($pesanan->barang as $b) { ?>
                                    <li class="flex flex-wrap items-baseline gap-1 py-[0.2rem]">
                                        <span class="grow"><?= strtoupper($b->kode) ?> (<?= strtoupper($b->ukuran) ?>)</span>
                                        <span class="shrink-0 font-semibold whitespace-nowrap"><?= $b->qty ?> pcs</span>
                                        <span class="shrink-0 font-semibold whitespace-nowrap tabular-nums"><?= number_to_currency($b->harga * $b->qty, 'IDR'); ?></span>

                                        <?php
                                        $content = '<div class=\'text-end\'>';
                                        $content .= 'harga @: <strong>' . number_to_currency($b->harga, 'IDR') . '</strong>';
                                        if ($b->qty > 1) {
                                            $content .= '<br/>harga @ x ' . $b->qty . ': <strong>' . number_to_currency($b->harga * $b->qty, 'IDR') . '</strong>';
                                        }
                                        $content .= '</div>';

                                        echo form_button([
                                            'data-bs-toggle'  => 'popHarga',
                                            'data-bs-content' => $content,
                                            'content'         => '<i class="fal fa-info-circle"></i> <span class="sr-only">info</span>',
                                            'class'           => 'cursor-pointer border-0 bg-transparent p-0 text-xs text-ink-500 hover:underline',
                                        ]);

                                        $rincian_item = daftar_rincian($b->rincian ?? null, 'produk');

                                        if ($rincian_item !== []) { ?>
                                            <div class="basis-full mb-[0.15rem] mt-[0.3rem] flex flex-wrap gap-[0.3rem] pl-[1.1rem]">
                                                <?php foreach ($rincian_item as $item) { ?>
                                                    <span class="inline-flex max-w-full items-baseline gap-1 rounded-full border border-ink-200 bg-[#f6f8fb] px-[0.55rem] py-[0.2rem] text-xs leading-snug text-ink-600 [&>.fal]:text-[0.7rem] [&>.fal]:text-ink-400"><i class="fal <?= $item['ikon'] ?>"></i> <span class="font-bold tracking-wide text-ink-500 whitespace-nowrap"><?= esc($item['label']) ?></span> <span class="break-words"><?= esc($item['nilai']) ?></span></span>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>

                                        <?php $ukuran_item = $b->nilai_ukuran ?? []; ?>

                                        <?php if ($ukuran_item !== []) { ?>
                                            <?php $punya_ukuran = true; ?>
                                            <div class="basis-full mb-[0.15rem] mt-[0.3rem] flex flex-wrap items-baseline gap-[0.3rem] pl-[1.1rem]">
                                                <span class="text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500"><i class="fal fa-ruler-combined"></i> Ukuran</span>

                                                <?php foreach ($ukuran_item as $u) { ?>
                                                    <span class="inline-flex max-w-full items-baseline gap-1 rounded-full border border-ink-200 bg-[#f6f8fb] px-[0.55rem] py-[0.2rem] text-xs leading-snug text-ink-600"><span class="font-bold tracking-wide text-ink-500 whitespace-nowrap"><?= esc($u[0]) ?></span> <span class="break-words"><?= esc($u[1]) ?></span></span>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                    </li>
                                <?php } ?>
                            </ul>

                            <?php if (! $punya_ukuran) { ?>
                                <p class="mb-0 mt-2 text-xs text-ink-500"><i class="fal fa-ruler-combined"></i> Ukuran belum diisi</p>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <?php if (! empty($pesanan->anggota)) { ?>
                    <div class="blok blok-lipat mt-4 overflow-hidden rounded-2xl border border-ink-200 bg-[#fcfdfe]">
                        <h6 class="mb-0">
                            <button class="flex w-full cursor-pointer items-center gap-1.5 border-0 bg-transparent px-[1.15rem] py-4 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400" type="button" data-bs-toggle="collapse" data-bs-target="#anggota-<?= esc($pesanan->id_invoice) ?>" aria-expanded="false" aria-controls="anggota-<?= esc($pesanan->id_invoice) ?>">
                                <i class="fal fa-users"></i> Anggota Rombongan
                                <span class="inline-block rounded-full bg-ink-900 px-2 py-0.5 text-xs font-semibold text-white"><?= count($pesanan->anggota) ?></span>
                                <span class="text-[0.8rem] font-semibold normal-case tracking-normal">orang</span>
                                <i class="fal fa-chevron-down ms-auto transition rotate-[-90deg] aria-expanded:rotate-0"></i>
                            </button>
                        </h6>

                        <div class="collapse" id="anggota-<?= esc($pesanan->id_invoice) ?>">
                            <div class="border-t border-ink-200 px-[1.15rem] py-3">
                                <?php
                                // daftar nama orang tidak menyebut bajunya apa; produk orderan ini
                                // jadi acuannya karena ukuran anggota belum ditautkan per barang
                                $produk_rombongan = [];

                                foreach ($pesanan->barang as $b) {
                                    $produk_rombongan[] = strtoupper($b->kode) . ' (' . strtoupper($b->ukuran) . ') × ' . $b->qty;
                                }
                                ?>
                                <?php if ($produk_rombongan !== []) { ?>
                                    <p class="mb-2 text-xs text-ink-500"><i class="fal fa-tshirt"></i> Produk: <?= esc(implode(', ', $produk_rombongan)) ?></p>
                                <?php } ?>

                                <ul class="m-0 list-none p-0 text-sm">
                                    <?php foreach ($pesanan->anggota as $o) { ?>
                                        <li class="flex flex-wrap items-baseline gap-1 py-[0.2rem]">
                                            <span class="grow"><?= esc($o->nama) ?></span>

                                            <?php if (! empty($o->nomor)) { ?>
                                                <span class="shrink-0 font-semibold whitespace-nowrap"><?= esc($o->nomor) ?></span>
                                            <?php } ?>

                                            <?php if (! empty($o->catatan)) { ?>
                                                <div class="basis-full mt-[0.15rem] break-words text-xs leading-[1.5] text-ink-500"><?= esc($o->catatan) ?></div>
                                            <?php } ?>

                                            <?php if (! empty($o->nilai)) { ?>
                                                <div class="basis-full mb-[0.15rem] mt-[0.3rem] flex flex-wrap items-baseline gap-[0.3rem] pl-[1.1rem]">
                                                    <span class="text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500"><i class="fal fa-ruler-combined"></i> Ukuran</span>

                                                    <?php foreach ($o->nilai as $u) { ?>
                                                        <span class="inline-flex max-w-full items-baseline gap-1 rounded-full border border-ink-200 bg-[#f6f8fb] px-[0.55rem] py-[0.2rem] text-xs leading-snug text-ink-600"><span class="font-bold tracking-wide text-ink-500 whitespace-nowrap"><?= esc($u[0]) ?></span> <span class="break-words"><?= esc($u[1]) ?></span></span>
                                                    <?php } ?>
                                                </div>
                                            <?php } else { ?>
                                                <div class="basis-full mt-[0.15rem] text-xs text-ink-500"><i class="fal fa-ruler-combined"></i> Ukuran belum diisi</div>
                                            <?php } ?>
                                        </li>
                                    <?php } ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php } ?>

                <?= foto_kisi($pesanan->foto ?? [], (int) $pesanan->id_invoice) ?>

                <?php
                // saat satu orderan dibuka sendiri, field yang belum terisi ikut ditampilkan
                // dengan "-" supaya jelas bagian mana yang masih kosong
                $rincian_pesanan = daftar_rincian($pesanan->rincian ?? null, 'pesanan', $satu_orderan);
                $isi_keterangan  = trim((string) $pesanan->keterangan);
                ?>
                <?php if ($rincian_pesanan !== []) { ?>
                    <div class="blok rounded-2xl border border-ink-200 border-l-[3px] border-l-proses bg-[#fcfdfe] p-3.5 sm:p-4 mt-4">
                        <h6 class="mb-2.5 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400"><i class="fal fa-list-check"></i> Detail Pesanan</h6>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5 sm:gap-3">
                            <?php foreach ($rincian_pesanan as $item) { ?>
                                <div class="min-w-0 rounded-xl bg-ink-50/70 p-2 sm:p-2.5 border border-ink-100">
                                    <span class="block text-[0.68rem] font-bold uppercase tracking-[0.08em] text-ink-500 truncate"><i class="fal <?= $item['ikon'] ?> me-1 text-ink-400"></i> <?= esc($item['label']) ?></span>
                                    <span class="mt-0.5 block break-words text-xs sm:text-sm font-semibold leading-snug <?= $item['nilai'] === null ? 'text-ink-400' : 'text-ink-900' ?>"><?= $item['nilai'] === null ? '-' : esc($item['nilai']) ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

                <?php if ($adalah_sewa) {
                    /* Nota sewa: jadwal pinjam diringkas di blok sendiri, isinya sama
                       dengan kotak "Jadwal" di lembar kerja penjahit — tenggat, tanggal
                       ambil & kembali, jaminan, tahap terakhir. Tanggal di sini tetap
                       cuma jadual; barang dinyatakan pulang lewat "Sudah kembali"
                       (sewa_kembali_at), bukan lewat kolom ini. */
                    $j_tenggat  = label_deadline(($pesanan->deadline ?? '') ?: deadline_iso($pesanan->rincian ?? null));
                    $j_rincian  = baca_rincian($pesanan->rincian ?? null);
                    $j_terakhir = tahap_terakhir($pesanan->status ?? []);
                    $jadwal     = [
                        ['fa-clock', 'Tenggat selesai', $j_tenggat['teks'] === '-' ? null : $j_tenggat['teks']],
                        ['fa-arrow-down', 'Diambil', isset($j_rincian['ambil']) ? tanggal_rincian((string) $j_rincian['ambil']) : null],
                        ['fa-arrow-up', 'Kembali', isset($j_rincian['kembali']) ? tanggal_rincian((string) $j_rincian['kembali']) : null],
                        ['fa-shield-halved', 'Jaminan', $j_rincian['jaminan'] ?? null],
                        ['fa-flag-checkered', 'Tahap terakhir', $j_terakhir === null ? null : $j_terakhir['nama'] . ' (' . $j_terakhir['tanggal'] . ', ' . ($j_terakhir['selesai'] ? 'selesai' : 'berjalan') . ')'],
                    ];
                } ?>
                <?php if ($adalah_sewa) { ?>
                    <div class="blok rounded-2xl border border-ink-200 border-l-[3px] border-l-proses bg-[#fcfdfe] p-3.5 sm:p-4 mt-4">
                        <h6 class="mb-2.5 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400"><i class="fal fa-calendar-days"></i> Jadwal</h6>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2.5 sm:gap-3">
                            <?php foreach ($jadwal as [$ikon, $label, $nilai]) { ?>
                                <div class="min-w-0 rounded-xl bg-ink-50/70 p-2 sm:p-2.5 border border-ink-100">
                                    <span class="block text-[0.68rem] font-bold uppercase tracking-[0.08em] text-ink-500 truncate"><i class="fal <?= $ikon ?> me-1 text-ink-400"></i> <?= esc($label) ?></span>
                                    <span class="mt-0.5 block break-words text-xs sm:text-sm font-semibold leading-snug <?= $nilai === null ? 'text-ink-400' : 'text-ink-900' ?>"><?= $nilai === null ? '-' : esc($nilai) ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

                <?php if ($isi_keterangan !== '' || $satu_orderan) { ?>
                    <div class="blok rounded-2xl border border-ink-200 border-l-[3px] border-l-brand-500 bg-[#fcfdfe] px-[1.15rem] py-4 mt-4">
                        <h6 class="mb-2 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400"><i class="fal fa-clipboard-list"></i> Keterangan</h6>
                        <p class="keterangan mb-0 break-words text-sm leading-[1.65] <?= $isi_keterangan === '' ? 'text-ink-400' : 'text-ink-700' ?>" id="keterangan-<?= esc($pesanan->id_invoice) ?>">
                            <?= $isi_keterangan === '' ? '-' : nl2br(esc($isi_keterangan)) ?>
                        </p>
                    </div>
                <?php } ?>

                <?php if ($pesanan->status_pengiriman !== '1' && $pesanan->pengiriman !== []) { ?>
                    <div class="mt-4">
                        <h6 class="mb-2 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400"><i class="fal fa-truck-fast"></i> Resi</h6>
                        <div class="grid grid-cols-[repeat(auto-fill,minmax(15rem,1fr))] gap-3">
                            <?php
                            foreach ($pesanan->pengiriman as $key => $kirim) { ?>
                                <?php $tanggal_kirim = Time::createFromTimestamp($kirim->tanggal_kirim); ?>
                                <div class="rounded-xl border border-ink-200 bg-white px-3.5 py-3">
                                    <div class="flex items-center justify-between gap-2 text-[0.72rem] font-bold uppercase tracking-[0.08em] text-ink-500">
                                        <span class="truncate"><?= esc($kirim->kurir) ?></span>
                                        <?= form_button([
                                            'class'        => 'ms-1 cursor-pointer border-0 bg-transparent p-0 text-xs text-sky-600 hover:underline suntingResi',
                                            'content'      => '<i class="fal fa-money-check-edit fa-flip-horizontal"></i> <span class="sr-only">sunting</span>',
                                            'data-id'      => esc($kirim->id),
                                            'data-invoice' => esc($pesanan->id_invoice),
                                        ]) ?>
                                    </div>
                                    <div class="mt-[0.15rem] break-words text-[1.05rem] font-bold tracking-wide text-ink-900"><?= $kirim->resi; ?></div>
                                    <div class="text-[0.8rem] text-ink-500">
                                        <?= $tanggal_kirim->toLocalizedString('EEEE, d MMMM yyyy') ?>
                                        <?php if ($kirim->ongkir > 0) { ?>
                                            <br>ongkir fix (<?= esc($kirim->qty . 'pcs') ?>): <u><?= number_to_currency($kirim->ongkir, 'IDR') ?></u>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php
                            } ?>
                        </div>
                    </div>
                <?php } ?>

                <hr class="my-3 border-0 border-t border-ink-200 opacity-100" />

                <div class="aksi aksi-transaksi flex flex-wrap items-center gap-2">
                    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                        <?= anchor('admin/invoices/sunting/' . esc($pesanan->seri), '<i class="fal fa-pencil"></i> Sunting', ['class' => 'btn btn-sm rounded-lg btn-outline-secondary aksi-bagi', 'role' => 'button']) ?>

                        <?php if (! $sudah_lunas) : ?>
                            <?= form_button([
                                'class'          => 'btn btn-sm rounded-lg btn-outline-secondary aksi-bagi tambahBayar',
                                'content'        => '<i class="fal fa-plus-circle"></i> <span class="label-bayar"><span class="kata-depan">Tambah </span>Pembayaran</span>',
                                'data-invoice'   => $pesanan->id_invoice,
                                'data-juragan'   => $pesanan->juragan_id,
                                'data-kurang'    => max(0, $sisa),
                                'data-seri'      => $pesanan->seri,
                                'data-bs-target' => '#modalTambahBayar',
                                'data-bs-toggle' => 'modal',
                            ]); ?>
                        <?php endif; ?>

                        <?php
                        if ((int) $pesanan->status_pembayaran > 2 && ! $dipacking) {
                            echo form_button([
                                'class'          => 'btn btn-sm rounded-lg btn-dark aksi-setengah pesanStatus',
                                'content'        => '<i class="fal fa-comment-alt-plus"></i> Proses Orderan',
                                'data-invoice'   => $pesanan->id_invoice,
                                'data-seri'      => $pesanan->seri,
                                'data-bs-target' => '#modalProgress',
                                'data-bs-toggle' => 'modal',
                            ]);
                        }

                        // tombol cek pembayaran hanya tampil jika ada pembayaran yang perlu dicek
                        if ($pesanan->status_pembayaran === '2' || $pesanan->status_pembayaran === '3') {
                            echo form_button([
                                'class'          => 'btn btn-sm rounded-lg btn-warning aksi-penuh pesanBayar',
                                'content'        => '<i class="fal fa-wallet"></i> Cek Pembayaran',
                                'data-invoice'   => $pesanan->id_invoice,
                                'data-juragan'   => $pesanan->juragan_id,
                                'data-bs-target' => '#modalBayar',
                                'data-bs-toggle' => 'modal',
                            ]);
                        }

                        if ($dipacking && (int) $pesanan->status_pengiriman < 3) {
                            echo form_button([
                                'class'        => 'btn btn-sm rounded-lg btn-warning aksi-penuh kirimResi',
                                'content'      => '<i class="fal fa-paper-plane"></i> Input Resi',
                                'data-count'   => $count_barang,
                                'data-invoice' => $pesanan->id_invoice,
                            ]);
                        }

                        if ($sewa_berjalan) {
                            // tombolnya mati selama nota belum masuk tab Masih Disewa:
                            // dicatat kembali hanya masuk akal untuk barang yang
                            // memang benar-benar sedang dipinjam orang
                            $kembali = [
                                'content'      => '<i class="fal fa-tshirt"></i> <span class="label-kembali"><span class="kata-depan">Sudah </span>kembali</span>',
                                'data-invoice' => $pesanan->id_invoice,
                                'data-seri'    => $pesanan->seri,
                            ];

                            if ($siap_kembali) {
                                $kembali['class'] = 'btn btn-sm rounded-lg btn-success aksi-setengah sewaKembali';
                                $kembali['title'] = 'Barang sewaan sudah diterima kembali di toko';
                            } else {
                                $kembali['class']    = 'btn btn-sm rounded-lg btn-success aksi-setengah disabled';
                                $kembali['disabled'] = '';
                                $kembali['title']    = (string) $alasan_kembali;
                            }

                            echo form_button($kembali);
                        } elseif ($sewa_pulang) {
                            $waktu_kembali = Time::createFromTimestamp((int) $pesanan->sewa_kembali_at)
                                ->setTimezone(config('App')->appTimezone)
                                ->toLocalizedString('d MMM yyyy HH:mm'); ?>
                            <span class="btn btn-sm rounded-lg btn-success aksi-baris disabled" title="Waktu pengembaliannya dicatat">
                                <i class="fal fa-undo-alt-left"></i> Kembali <?= esc($waktu_kembali) ?>
                            </span>
                            <?php echo form_button([
                                'class'        => 'btn btn-sm rounded-lg btn-outline-secondary aksi-setengah sewaBatalkan',
                                'content'      => '<i class="fal fa-undo"></i> Batalkan',
                                'data-invoice' => $pesanan->id_invoice,
                                'data-seri'    => $pesanan->seri,
                                'title'        => 'Salah klik? Kembalikan nota ini ke status masih disewa',
                            ]);
                        }
                        ?>
                    </div>

                    <div class="aksi-hapus">
                        <?= form_button([
                            'class'        => 'btn btn-sm rounded-lg btn-outline-danger aksi-bagi hapusOrderan',
                            'content'      => '<i class="fal fa-trash-alt"></i> Hapus',
                            'data-invoice' => $pesanan->id_invoice,
                            'data-seri'    => $pesanan->seri,
                            'data-lunas'   => tagihan_lunas($pesanan->status_pembayaran) ? '1' : '0',
                        ]); ?>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }
    ?>

    <?= $pager->makeLinks($page, $limit, $orderan['totalPage'], 'front_full') ?>

</div>

<?= $this->endSection() ?>

<?= $this->section('modal') ?>
<!-- Modal orderan status-->
<div class="modal fade" id="modalProgress" tabindex="-1" aria-labelledby="modalProgressLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <?= form_open('', ['class' => 'modal-content', 'id' => 'newStatus'], ['id_invoice' => '']) ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalProgressLabel">Status Proses Orderan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <?= form_label('Pilih Status', 'status', ['class' => 'form-label']) ?>
                <div class="row g-2">
                    <div class="col-7 col-sm-8">
                        <?php
                        $list_status = [
                            ''  => 'Pilih status',
                            '1' => 'Data Pesanan',
                            '2' => 'Bahan Produk',
                            '3' => 'Sablon',
                            '4' => 'Bordir',
                            '5' => 'Penjahit',
                            '6' => 'QC',
                            '7' => 'Packing',
                        ];
                        echo form_dropdown('status', $list_status, '', ['class' => 'form-select', 'id' => 'status', 'required' => '']);
                        ?>
                    </div>
                    <div class="col-5 col-sm-4">
                        <?= form_dropdown('stat', ['' => 'Pilih', '1' => 'Ada', '0' => 'Tidak Ada'], '', ['class' => 'form-select', 'id' => 'stat', 'required' => '', 'disabled' => '']); ?>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <?= form_label('Note / Keterangan', 'keterangan', ['class' => 'form-label']); ?>
                <?= form_input(['name' => 'keterangan', 'id' => 'keterangan', 'class' => 'form-control', 'placeholder' => 'opsional']) ?>
            </div>

            <div class="alert alert-warning py-2 mb-0 small">
                Perlu diingat, penambahan "status proses orderan" ini tidak bisa diubah.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
        <?= form_close() ?>
    </div>
</div>

<!-- Modal riwayat pembayaran -->
<div class="modal fade" id="modalBayar" tabindex="-1" aria-labelledby="modalBayarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3 min-w-0">
                    <span class="bayar-ikon"><i class="fal fa-wallet"></i></span>
                    <div class="min-w-0">
                        <h5 class="modal-title mb-0" id="modalBayarLabel">Riwayat Pembayaran</h5>
                        <div class="small text-muted" id="bayarSeri"></div>
                    </div>
                </div>
                <button type="button" class="btn-close ms-auto flex-shrink-0" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="cek" class="daftar-bayar">
                    <div class="text-muted small text-center py-3">memuat pembayaran ...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal konfirmasi nilai dana. Dialog bawaan browser tidak ikut tema halaman dan
     tidak bisa menjelaskan bahwa penilaiannya permanen, jadi dipakai modal sendiri
     (mengikuti pola konfirmasi hapus di halaman Produk). -->
<div class="modal fade" id="modalCekBayar" tabindex="-1" aria-labelledby="cekBayarJudul" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-kartu">
            <div class="modal-body p-4 text-center">
                <div class="konfirmasi-tanda sukses d-inline-flex align-items-center justify-content-center mb-3" id="cekTanda">
                    <i class="fal fa-circle-check cek-ikon-ada"></i>
                    <i class="fal fa-circle-xmark cek-ikon-tidak"></i>
                </div>
                <h5 class="konfirmasi-judul mb-1" id="cekBayarJudul">Tandai dana diterima?</h5>
                <p class="konfirmasi-objek mb-1" id="cekObjek">-</p>
                <p class="small text-muted mb-4">Sekali disimpan, penilaiannya tidak bisa diubah lagi.</p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm konfirmasi-batal flex-fill" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-sm konfirmasi-ya sukses flex-fill" id="cekBayarYa">Ya, diterima</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal tambah pembayaran -->
<div class="modal fade" id="modalTambahBayar" tabindex="-1" aria-labelledby="modalTambahBayarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <?= form_open('', ['class' => 'modal-content', 'id' => 'tambahPembayaran'], ['invoice_id' => '']) ?>
        <div class="modal-header">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <span class="bayar-ikon"><i class="fal fa-plus-circle"></i></span>
                <div class="min-w-0">
                    <h5 class="modal-title mb-0" id="modalTambahBayarLabel">Tambah Pembayaran</h5>
                    <div class="small text-muted" id="tambahBayarSeri"></div>
                </div>
            </div>
            <button type="button" class="btn-close ms-auto flex-shrink-0" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
            <div class="bayar-catatan mb-3">
                <i class="fal fa-circle-info"></i>
                <span>Pembayaran yang tersimpan tidak bisa dihapus atau diubah, jadi pastikan rekening, tanggal, dan nominalnya sudah benar.</span>
            </div>

            <div class="row gx-2">
                <div class="col-12 col-sm-6 form-group mb-3">
                    <?= form_label('Tujuan Bayar', 'tujuan', ['class' => 'form-label']) ?>
                    <?= form_dropdown('sumber_dana', [], '', ['class' => 'form-select', 'required' => '', 'id' => 'tujuan']) ?>
                    <div class="small text-danger mt-1 d-none" id="tujuan-galat">
                        Toko ini belum punya rekening pembayaran. Tambahkan dulu di
                        <a href="<?= esc(site_url('admin/settings/juragan')) ?>">Pengaturan › Juragan</a>.
                    </div>
                </div>
                <div class="col-12 col-sm-6 form-group mb-3">
                    <?= form_label('Tanggal Bayar', 'tanggal_bayar', ['class' => 'form-label']) ?>
                    <?= form_input('tanggal_pembayaran', set_value('tanggal_pembayaran', $sekarang->toDateString()), ['class' => 'form-control', 'id' => 'tanggal_pembayaran', 'required' => '', 'max' => $sekarang->toDateString()], 'date') ?>
                </div>
                <?php /* type=text + inputmode=numeric + saringan JS: kolom number
                   bawaan browser masih membolehkan tanda minus, titik, dan huruf e
                   diketik, dan form ini dikirim lewat AJAX sehingga validasi native
                   tidak pernah sempat menolaknya */ ?>
                <div class="col-12 form-group mb-0">
                    <?= form_label('Jumlah Dana', 'jumlah_dana', ['class' => 'form-label']) ?>
                    <div class="bayar-rp">
                        <span class="mata">Rp</span>
                        <?= form_input([
                            'name'         => 'total_pembayaran',
                            'class'        => 'form-control',
                            'id'           => 'jumlah_dana',
                            'required'     => '',
                            'placeholder'  => '299000',
                            'type'         => 'text',
                            'inputmode'    => 'numeric',
                            'autocomplete' => 'off',
                            'pattern'      => '[0-9]+',
                        ]) ?>
                    </div>
                    <div class="form-text bayar-pratinjau" id="jumlah-bantuan">Hanya angka bulat &mdash; tanpa minus, titik, atau koma.</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-primary" type="submit"><i class="fal fa-save me-1"></i>Simpan</button>
        </div>
        <?= form_close(); ?>
    </div>
</div>

<!-- Modal input resi -->
<div class="modal fade" id="modalKirim" tabindex="-1" aria-labelledby="modalKirimLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <?= form_open('', ['id' => 'submitResi', 'class' => 'modal-content'], ['invoice_id' => '1']) ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalKirimLabel">Input Resi</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="row gx-2 mb-3">
                <div class="col-4">
                    <?= form_label('Kurir', 'kurir', ['class' => 'form-label']) ?>
                    <?php
                    $options_kurir = ['' => 'Pilih kurir'];

                    foreach (config('JuraganConfig')->kurir as $label) {
                        $options_kurir[$label] = $label;
                    }
                    ?>

                    <?= form_dropdown('kurir', $options_kurir, '', ['class' => 'form-select', 'id' => 'kurir', 'required' => '']) ?>
                </div>
                <div class="col">
                    <?= form_label('Lainnya', 'resi', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'       => 'form-control',
                        'disabled'    => '',
                        'id'          => 'lainnya',
                        'name'        => 'lainnya',
                        'placeholder' => 'kurir lain',
                    ]) ?>
                </div>
            </div>

            <div class="mb-3">
                <?= form_label('No Resi', 'resi', ['class' => 'form-label']) ?>
                <?= form_input([
                    'class'       => 'form-control',
                    'id'          => 'resi',
                    'name'        => 'resi',
                    'placeholder' => 'JOGxxxxxxxxxxx',
                    'required'    => '',
                ]); ?>
            </div>


            <div class="row gx-2 mb-3">
                <div class="col-5">
                    <div class="mb-3">
                        <?= form_label('Tanggal Kirim', 'tanggal_kirim', ['class' => 'form-label']) ?>
                        <?= form_input([
                            'class'       => 'form-control',
                            'id'          => 'tanggal_kirim',
                            'max'         => $sekarang->toDateString(),
                            'name'        => 'tanggal_kirim',
                            'placeholder' => 'JOGxxxxxxxxxxx',
                            'required'    => '',
                            'type'        => 'date',
                            'value'       => $sekarang->toDateString(),
                        ]) ?>
                    </div>
                </div>
                <div class="col">
                    <div class="mb-3">
                        <?= form_label('Isi Paket', 'isi_paket', ['class' => 'form-label']) ?>
                        <?= form_input([
                            'class'       => 'form-control',
                            'id'          => 'isi_paket',
                            'min'         => 1,
                            'name'        => 'qty',
                            'placeholder' => '1',
                            'required'    => '',
                            'type'        => 'number',
                        ]) ?>
                    </div>
                </div>
                <div class="col-4">
                    <div class="mb-3">
                        <?= form_label('Ongkir', 'ongkir', ['class' => 'form-label']) ?>
                        <?= form_input([
                            'class'       => 'form-control',
                            'id'          => 'ongkir',
                            'name'        => 'ongkir',
                            'placeholder' => '25000',
                            'required'    => '',
                            'min'         => 0,
                            'type'        => 'number',
                        ]) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn link" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
        <?= form_close(); ?>
    </div>
</div>

<!-- Modal alasan hapus orderan -->
<div class="modal fade" id="modalBatal" tabindex="-1" aria-labelledby="modalBatalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <?= form_open('', ['id' => 'submitBatal', 'class' => 'modal-content']) ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalBatalLabel">Hapus Orderan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <p class="small text-body-secondary mb-3">
                Orderan <span class="fw-bold" id="batalSeri">-</span> akan dihapus.
                Alasannya dicatat supaya pembatalan yang sah tidak dihitung sebagai kesalahan siapa pun.
            </p>
            <div>
                <?php
                $opsi_alasan = ['' => 'Pilih alasan'];
                $grup_alasan = ['kesalahan' => 'Kesalahan input', 'sah' => 'Pembatalan sah'];

                foreach (alasan_batal() as $kunci_alasan => $r) {
                    $opsi_alasan[$grup_alasan[$r['jenis']]][$kunci_alasan] = $r['label'];
                }
                ?>

                <?= form_label('Alasan pembatalan', 'alasan', ['class' => 'form-label']) ?>
                <?= form_dropdown('alasan', $opsi_alasan, '', [
                    'class'    => 'form-select',
                    'id'       => 'alasanBatal',
                    'required' => '',
                ]) ?>

                <div id="wadahCatatanBatal" class="mt-3" style="display: none;">
                    <?= form_label('Catatan alasan <span class="text-danger">*</span>', 'catatanBatal', ['class' => 'form-label']) ?>
                    <?= form_textarea([
                        'name'        => 'catatan_batal',
                        'id'          => 'catatanBatal',
                        'class'       => 'form-control',
                        'rows'        => '2',
                        'placeholder' => 'Tuliskan alasan pembatalan...',
                    ]) ?>
                </div>

                <div id="pesanBatalLunas" class="alert alert-warning py-2 small mt-3" style="display: none;">
                    <i class="fal fa-info-circle me-1"></i> Orderan ini sudah lunas. Kategori Pembatalan Sah hanya berlaku untuk orderan yang belum lunas.
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn link" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-danger">Hapus Orderan</button>
        </div>
        <?= form_close(); ?>
    </div>
</div>

<!-- Modal pencarian invoice -->
<div class="modal fade" id="modalCari" tabindex="-1" aria-labelledby="modalCariLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <?= form_open('admin/invoices/lihat/' . $juragan . '/' . $hal . '/' . $kategori, ['method' => 'get']) ?>
                <div class="row g-2 align-items-center">
                    <div class="col-4">
                        <?= form_label('Status Pembayaran', 'status_pembayaran', ['class' => 'form-label visually-hidden']) ?>
                        <?php
                        $opsi_pembayaran = [
                            ''   => 'Opsi Pembayaran',
                            '1'  => 'Belum bayar',
                            '2A' => 'Tunggu Konfirmasi Pembayaran',
                            '4'  => 'Cicilan / Kredit',
                            '5'  => 'Ada kelebihan',
                            '6'  => 'Sudah lunas',
                        ];
                        echo form_dropdown('cari[pembayaran]', $opsi_pembayaran, '', ['class' => 'form-select mb-2 me-sm-2']);
                        ?>
                    </div>

                    <div class="col-4">
                        <div class="col-12">
                            <?= form_label('Status Orderan', 'status_orderan', ['class' => 'form-label visually-hidden']) ?>
                            <?php
                            $opsi_orderan = [
                                ''  => 'Opsi Orderan',
                                '1' => 'Belum diproses',
                                '2' => 'Sedang/sudah diproses',
                                '3' => 'Dibatalkan',
                            ];
                            echo form_dropdown('cari[orderan]', $opsi_orderan, '', ['class' => 'form-select mb-2 me-sm-2']);
                            ?>
                        </div>
                    </div>

                    <div class="col-4">
                        <?= form_label('Status Pengiriman', 'status_pengiriman', ['class' => 'form-label visually-hidden']) ?>
                        <?php
                        $opsi_pengiriman = [
                            ''   => 'Opsi pengiriman',
                            '1'  => 'Belum dikirim',
                            '2A' => 'Dikirim',
                        ];
                        echo form_dropdown('cari[pengiriman]', $opsi_pengiriman, '', ['class' => 'form-select mb-2 me-sm-2']);
                        ?>
                    </div>
                </div>

                <div class="input-group mb-2">
                    <?php
                    $opsi_kolom = [
                        ''              => 'Opsi Pencarian',
                        'nama'          => 'Nama Pelanggan',
                        'hp'            => 'HP Pelanggan',
                        'faktur'        => 'Kode Invoice',
                        'tanggal_pesan' => 'Tanggal Pesan',
                        'kode'          => 'Kode Produk',
                    ];
                    echo form_dropdown('cari[kolom]', $opsi_kolom, '', ['class' => 'form-select']);
                    echo form_input(['name' => 'cari[q]', 'class' => 'form-control allow-enter', 'style' => 'flex:2', 'placeholder' => 'cari .....']);
                    ?>
                    <div class="text-muted">untuk pencarian berdasarkan tanggal, gunakan format seperti: <code>2020-09-20</code></div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
                <?= form_close() ?>

            </div>

        </div>
    </div>
</div>

<!-- Modal scan QR nota: hasil scan mengisi kolom cari (kode invoice) lalu dikirim -->
<div class="modal fade" id="modalScan" tabindex="-1" aria-labelledby="modalScanLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 30rem;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalScanLabel"><i class="fal fa-qrcode me-1"></i> Scan QR Nota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body text-center">
                <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                    <select id="scanKamera" class="form-select form-select-sm w-auto d-none" aria-label="Pilih kamera"></select>
                    <button type="button" id="scanUlang" class="btn btn-sm btn-outline-secondary d-none">
                        <i class="fal fa-redo"></i> Mulai ulang
                    </button>
                </div>

                <div id="qrPemindai" style="min-height: 16rem;"></div>

                <div class="text-muted small mt-2" id="scanPetunjuk">
                    Menyiapkan kamera...
                </div>

                <hr class="my-3">

                <div class="small text-muted mb-1">Cadangan kalau kamera tidak tersedia:</div>
                <label class="btn btn-sm btn-outline-secondary mb-0">
                    <i class="fal fa-image"></i> Scan dari foto QR
                    <input type="file" id="scanBerkas" accept="image/*" class="d-none">
                </label>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<script>
// ===== Scan QR nota =====
// Pustaka html5-qrcode dikemas lokal dan baru diunduh saat modal pertama dibuka,
// supaya halaman Transaksi tidak ikut berat. Kamera browser hanya mau menyala di
// konteks aman (https atau localhost); kalau tidak, pengguna diarahkan ke cadangan
// "Scan dari foto" yang tidak butuh akses kamera.
(function () {
    'use strict';

    var PUSTAKA = '<?= base_url('assets/js/html5-qrcode.min.js') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/js/html5-qrcode.min.js') ?>';

    var muatPustaka = null;
    var kamera      = [];
    var kameraAktif = '';
    var sedangBeres = false;
    var KUNCI_KAMERA = 'djuragan_kamera_scan';

    // Satu objek sesi. nomToken dipakai untuk membuang callback dari sesi
    // lama, jadi buka-tutup cepat atau ganti kamera tidak saling menimpa lagi
    var sesi = { nomToken: 0, pemindai: null, dinyalakan: false, diterima: false, aktif: false };

    var byId = function (id) {
        return document.getElementById(id);
    };

    function wadah() {
        return byId('qrPemindai');
    }

    function petunjuk(teks, galat) {
        var el = byId('scanPetunjuk');

        if (! el) {
            return;
        }

        el.textContent = teks;
        el.classList.toggle('text-danger', Boolean(galat));
    }

    function jeda(ms) {
        return new Promise(function (resolve) {
            window.setTimeout(resolve, ms || 250);
        });
    }

    function muatScript() {
        if (muatPustaka) {
            return muatPustaka;
        }

        muatPustaka = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = PUSTAKA;
            s.onload = resolve;
            s.onerror = function () {
                muatPustaka = null;
                reject(new Error('Pustaka scanner gagal dimuat dari server.'));
            };
            document.head.appendChild(s);
        });

        return muatPustaka;
    }

    // getUserMedia tidak ada di http non-localhost: kamera mustahil menyala
    function bolehKamera() {
        return window.isSecureContext === true
            && navigator.mediaDevices
            && typeof navigator.mediaDevices.getUserMedia === 'function';
    }

    function pesanGalat(err) {
        var nama = (err && err.name) ? err.name : '';

        if (nama === 'NotAllowedError' || nama === 'PermissionDeniedError') {
            return 'Izin kamera ditolak browser. Izinkan akses kamera untuk situs ini lewat ikon kamera di address bar, lalu klik Mulai ulang.';
        }

        if (nama === 'NotReadableError' || nama === 'TrackStartError') {
            return 'Kamera sedang dipakai aplikasi lain (Zoom/Meet/DSLR cam). Tutup aplikasi itu lalu klik Mulai ulang.';
        }

        if (nama === 'NotFoundError' || nama === 'OverconstrainedError' || nama === 'DevicesNotFoundError') {
            return 'Kamera tidak ditemukan di perangkat ini. Pakai cadangan "Scan dari foto" di bawah.';
        }

        return (err && err.message ? err.message : 'Kamera gagal dibuka.')
            + ' — klik Mulai ulang, atau pakai "Scan dari foto" / ketik nomor nota di tombol Cari.';
    }

    // Kotak baca mengikuti ukuran video: nilai tetap bisa lebih lebar dari
    // frame kamera laptop, dan kalau begitu scanner tidak membaca apa pun
    function kotakBaca(lebar, tinggi) {
        var batas = Math.min(lebar, tinggi);

        // video sangat kecil (webcam murah / jendela sempit): pakai hampir
        // seluruh frame, jangan paksa minimum yang malah keluar dari frame
        if (batas < 180) {
            var kecil = Math.max(40, Math.round(batas * 0.9));

            return { width: kecil, height: kecil };
        }

        var sisi = Math.round(Math.min(batas * 0.75, batas - 24));

        return { width: Math.max(110, sisi), height: Math.max(110, sisi) };
    }

    function matikanVideo() {
        var w = wadah();

        if (! w) {
            return;
        }

        [].forEach.call(w.querySelectorAll('video'), function (v) {
            try { v.pause(); } catch (e) { /* sudah berhenti */ }

            v.srcObject = null;
        });
    }

    // Instance baru tiap sesi: memakai ulang objek sesudah stop()/clear()
    // membuat daerah baca berdimensi 0, dan itu membuat scanner tampak hidup
    // tapi tidak pernah membaca apa pun
    function pemindaiBaru() {
        var w = wadah();

        if (w) {
            w.innerHTML = '';
        }

        return new window.Html5Qrcode('qrPemindai');
    }

    /**
     * Lepaskan kamera tanpa syarat.
     *
     * Bug lama: pelepasan dijaga flag "berjalan" yang baru bernilai true
     * setelah start() selesai. Modal yang ditutup saat kamera masih menyala
     * membuat stream tertinggal dan percobaan berikutnya gagal - sumber
     * "kadang bisa scan, kadang tidak".
     */
    function lepaskan() {
        var inst       = sesi.pemindai;
        var dinyalakan = sesi.dinyalakan;

        sesi.nomToken += 1;
        sesi.pemindai    = null;
        sesi.dinyalakan  = false;
        sesi.diterima    = false;
        sesi.aktif       = false;

        if (! inst || ! dinyalakan) {
            matikanVideo();

            return Promise.resolve();
        }

        sedangBeres = true;

        var berhenti;

        try {
            berhenti = inst.stop();
        } catch (e) {
            berhenti = Promise.reject(e);
        }

        return Promise.resolve(berhenti)
            .catch(function () { /* memang sudah mati */ })
            .then(function () {
                try { inst.clear(); } catch (e) { /* belum sempat dibuat */ }

                matikanVideo();
                sedangBeres = false;
            });
    }

    function tungguHasil(nomToken) {
        window.setTimeout(function () {
            if (nomToken !== sesi.nomToken || ! sesi.aktif || sesi.diterima) {
                return;
            }

            petunjuk('Belum ada QR terbaca. Dekatkan kamera, pastikan cahaya cukup, atau ganti kamera dari daftar di atas.');
        }, 6000);
    }

    function terima(teks, nomToken) {
        var seri = String(teks || '').trim();

        if (seri === '' || nomToken !== sesi.nomToken || ! sesi.aktif || sesi.diterima) {
            return;
        }

        sesi.diterima = true;
        sesi.aktif    = false;

        // kamera yang terbukti bekerja diingat, sesi berikutnya langsung memakai
        // perangkat yang sama
        try {
            window.localStorage.setItem(KUNCI_KAMERA, kameraAktif);
        } catch (e) { /* mode privat: abaikan */ }

        lepaskan().then(function () {
            bootstrap.Modal.getOrCreateInstance(byId('modalScan')).hide();
            cariDenganSeri(seri);
        });
    }

    function nyalakan(idKamera, ulang) {
        if (sedangBeres) {
            // tunggu pelepasan sesi sebelumnya sampai selesai, jangan menumpuk
            return jeda(200).then(function () {
                return nyalakan(idKamera, ulang);
            });
        }

        var nomToken = (sesi.nomToken += 1);
        var inst     = null;

        sesi.dinyalakan = false;
        sesi.diterima   = false;
        sesi.aktif      = true;

        return muatScript().then(function () {
            if (nomToken !== sesi.nomToken) {
                return;
            }

            inst = pemindaiBaru();
            sesi.pemindai = inst;

            return inst.start(
                idKamera,
                {
                    fps: 15,
                    qrbox: kotakBaca,
                    verbose: false,
                    // decoder bawaan browser jauh lebih seret menangkap QR
                    // kecil/kurang fokus daripada decoder JS bawaan pustaka
                    useBarCodeDetectorIfSupported: true
                },
                function (teksTerbaca) {
                    // argumen pertama = isi barcode; jangan baca properti hasil
                    // yang bentuknya berbeda antar versi pustaka
                    terima(teksTerbaca, nomToken);
                },
                function () { /* frame tanpa kode: biarkan terus mencoba */ }
            );
        }).then(function () {
            if (nomToken !== sesi.nomToken) {
                // sesi sudah digantikan: stream yatim harus ditutup sekarang
                if (inst) {
                    try {
                        inst.stop().then(function () { inst.clear(); }).catch(function () { /* mati */ });
                    } catch (e) { /* tidak started */ }
                }

                matikanVideo();

                return;
            }

            sesi.dinyalakan = true;
            tungguHasil(nomToken);
        }).catch(function (err) {
            if (nomToken !== sesi.nomToken) {
                return;
            }

            sesi.dinyalakan = false;
            sesi.aktif      = false;

            // kamera masih tertahan sesi sebelumnya: lepas lalu coba sekali lagi
            if (err && (err.name === 'NotReadableError' || err.name === 'InvalidStateError' || err.name === 'NotAllowedError')
                && (ulang || 0) < 1) {
                return lepaskan().then(jeda(400)).then(function () {
                    return nyalakan(idKamera, (ulang || 0) + 1);
                });
            }

            throw err;
        });
    }

    // Kamera infra-merah (Windows Hello) dan kamera virtual sering menempati
    // index 0, sementara urutannya bisa berubah antar sesi: gambar mengalir tapi
    // tidak ada yang terbaca. Labelnya dijadikan penanda, lalu pilihan yang
    // terbukti berhasil diingat untuk sesi berikutnya
    function dicurigai(perangkat) {
        var label = String(perangkat.label || '').toLowerCase();

        if (label === '') {
            return false;
        }

        return /\bhir\b|\bir\b|depth|hello|virtual|emulator|obs|unity|fuzzylabs|backend/.test(label);
    }

    function pilihKamera() {
        var tersimpan = '';

        try {
            tersimpan = window.localStorage.getItem(KUNCI_KAMERA) || '';
        } catch (e) { /* mode privat */ }

        var layak = kamera.filter(function (k) {
            return ! dicurigai(k);
        });
        var daftar = layak.length > 0 ? layak : kamera;

        for (var i = 0; i < daftar.length; i++) {
            if (daftar[i].id === tersimpan) {
                return daftar[i].id;
            }
        }

        return daftar.length > 0 ? daftar[0].id : '';
    }

    function isiPemilihKamera() {
        var pemilih = byId('scanKamera');

        pemilih.innerHTML = '';

        kamera.forEach(function (k, i) {
            var op = document.createElement('option');

            op.value = k.id;
            op.textContent = (k.label || ('Kamera ' + (i + 1)))
                + (dicurigai(k) ? ' (kemungkinan bukan kamera biasa)' : '');
            pemilih.appendChild(op);
        });

        pemilih.value = kameraAktif;

        // pemilih selalu tampil walau kamera cuma satu: pengguna bisa melihat
        // perangkat mana yang sedang dipakai saat hasil scan meragukan
        pemilih.classList.remove('d-none');
        byId('scanUlang').classList.remove('d-none');
    }

    function gantiKamera(id) {
        kameraAktif = id;
        petunjuk('Mengalihkan kamera...');

        lepaskan().then(function () {
            return nyalakan(id);
        }).catch(function (err) {
            petunjuk(pesanGalat(err), true);
        });
    }

    function siapkan() {
        if (sedangBeres) {
            // sesi lama masih melepas kamera: tunggu, jangan mulai dua-duanya
            jeda(250).then(siapkan);

            return;
        }

        sesi.nomToken += 1;
        sesi.aktif    = false;

        petunjuk('Menyiapkan kamera...');

        if (! bolehKamera()) {
            petunjuk('Browser hanya mengizinkan kamera di https atau localhost. Buka situs lewat http://localhost, pasang https, atau pakai cadangan "Scan dari foto" di bawah.', true);

            // pustaka tetap dimuat supaya cadangan foto bisa dipakai
            muatScript().catch(function () { /* biarkan pesan tetap tampil */ });

            return;
        }

        var pemilih = byId('scanKamera');

        pemilih.onchange = function () {
            gantiKamera(this.value);
        };

        // urutan perangkat diambil ulang tiap sesi: daftar kamera bisa berubah
        // sejak sesi terakhir (colok/tulis perangkat, mode privat, dll.)
        lepaskan()
            .then(function () {
                return muatScript();
            })
            .then(function () {
                // wajib daftar perangkat dulu: mengunci facingMode 'environment'
                // selalu gagal di kamera laptop yang cuma punya satu webcam
                return window.Html5Qrcode.getCameras();
            })
            .then(function (daftar) {
                kamera = daftar || [];

                if (kamera.length === 0) {
                    throw { name: 'NotFoundError', message: 'Tidak ada kamera yang terdeteksi.' };
                }

                kameraAktif = pilihKamera();
                isiPemilihKamera();
                petunjuk('Arahkan kamera ke QR pada invoice pelanggan atau lembar kerja penjahit.');

                return nyalakan(kameraAktif);
            })
            .catch(function (err) {
                if (err && err.name === 'NotFoundError') {
                    petunjuk(pesanGalat(err), true);

                    return;
                }

                // satu percobaan lagi setelah izin dikabulkan: getCameras() dan
                // start() berurutan kadang kalah cepat dengan prompt browser
                if (err && (err.name === 'NotAllowedError' || err.name === 'NotReadableError')) {
                    petunjuk('Mempercoba sekali lagi setelah izin kamera...');

                    jeda(900).then(function () {
                        return window.Html5Qrcode.getCameras();
                    }).then(function (daftar) {
                        kamera = daftar || [];

                        if (kamera.length === 0) {
                            throw { name: 'NotFoundError', message: 'Tidak ada kamera yang terdeteksi.' };
                        }

                        kameraAktif = pilihKamera();
                        isiPemilihKamera();

                        return nyalakan(kameraAktif);
                    }).catch(function (err2) {
                        sesi.aktif = false;
                        petunjuk(pesanGalat(err2), true);
                    });

                    return;
                }

                sesi.aktif = false;
                petunjuk(pesanGalat(err), true);
            });
    }

    // Jalur foto: dibuat tanpa menyentuh kamera, jadi tetap bekerja di http
    // biasa maupun saat webcam rusak/dipakai aplikasi lain
    function bacaBerkas(berkas) {
        var nomToken = (sesi.nomToken += 1);

        petunjuk('Membaca foto QR...');

        lepaskan()
            .then(function () {
                return muatScript();
            })
            .then(function () {
                if (nomToken !== sesi.nomToken) {
                    return;
                }

                var inst = pemindaiBaru();

                sesi.pemindai   = inst;
                sesi.dinyalakan = false;
                sesi.diterima   = false;
                sesi.aktif      = false;

                return inst.scanFile(berkas, true);
            })
            .then(function (teks) {
                var seri = String(teks || '').trim();

                if (seri === '' || nomToken !== sesi.nomToken) {
                    return;
                }

                bootstrap.Modal.getOrCreateInstance(byId('modalScan')).hide();
                cariDenganSeri(seri);
            })
            .catch(function () {
                matikanVideo();
                petunjuk('QR tidak terbaca dari foto itu. Ambil ulang lebih dekat dan terang, lalu ulangi.', true);
            });
    }

    function cariDenganSeri(seri) {
        // Hasil scan selalu diarahkan ke tab "Semua" (bebas filter status) yang
        // disaring ke satu nomor nota. Dulu scan mengirim form Cari yang menempel di
        // tab aktif, jadi nota yang tidak sedang berada di tab itu (mis. lembar
        // penjahit masih Dalam Proses tapi layar sedang di tab Pembayaran) ikut
        // tersaring keluar dan scanner tampak gagal — padahal isi QR-nya benar.
        var kueri = '?cari%5Bkolom%5D=faktur&cari%5Bq%5D=' + encodeURIComponent(seri);

        window.location.href = '<?= site_url('admin/invoices/lihat/semua/semua') ?>' + kueri;
    }

    byId('tombolScan').addEventListener('click', function () {
        bootstrap.Modal.getOrCreateInstance(byId('modalScan')).show();
    });

    byId('scanUlang').addEventListener('click', function () {
        siapkan();
    });

    byId('scanBerkas').addEventListener('change', function () {
        var berkas = this.files && this.files[0];

        if (berkas) {
            bacaBerkas(berkas);
        }

        this.value = '';
    });

    var modalScan = byId('modalScan');

    //shown: modal sudah benar-benar terlihat, jadi ukuran elemen video valid
    modalScan.addEventListener('shown.bs.modal', siapkan);
    modalScan.addEventListener('hidden.bs.modal', function () {
        if (sedangBeres) {
            jeda(300).then(function () { lepaskan(); });

            return;
        }

        lepaskan();
    });

    // browser tidak selalu sempat melepas stream saat halaman ditutup
    window.addEventListener('pagehide', function () {
        sesi.nomToken += 1;
        matikanVideo();
    });
})();
</script>
<?php

$link_api_get_bank       = site_url('api/juragan/all/');
$link_api_get_pembayaran = site_url('admin/invoices/info_pembayaran');
$link_api_pengiriman     = site_url('api/pengiriman');
$link_get_status_invoice = site_url('admin/invoices/detail_status/');
$link_hapus_orderan      = site_url('admin/invoices/hapus_orderan');
$link_save_progress      = site_url('admin/invoices/save_progress');
$link_sewa_kembali       = site_url('admin/invoices/sewa_kembali');
$link_tambah_pembayaran  = site_url('admin/invoices/simpan_pembayaran');
$link_update_pembayaran  = site_url('admin/invoices/update_bayar');
$link_api_counter_tab    = site_url('api/invoice/counter_tab/' . $juragan_id);

// dropdown "dana ada / tidak ada" di dalam modal Info Pembayaran
$boleh_cek_js            = $cekBayar ? 'true' : 'false';

$codvalue = 'COD' . $sekarang->toLocalizedString('ddMMyyyy');

$js = <<< JS
    $(function() {
    	'use strict';
    	// popover

    	var po = {};
    		po.trigger = 'focus';
    		po.placement = 'right';
    		po.container = 'body';
    		po.html = true;
    		po.selector = '[data-bs-toggle="popHarga"]';
    		po.template = '<div class="popover shadow" role="tooltip"><div class="popover-arrow"></div><div class="popover-body"></div></div>';
    	$('body').popover(po);

    	// tooltips

    	var tt = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    	var ttl = tt.map(function(e) {
    		return new bootstrap.Tooltip(e);
    	});


    	//

    	$('.pesanStatus').on('click',function(){
    		let id = $(this).data('invoice');

    		$('#newStatus [name="id_invoice"]').val(id);
    		$("#status option").prop('disabled', false);
    		$('#stat option').prop('disabled', false);
    		$.get("{$link_get_status_invoice}" + id, function(data, status){

    			if (data.length === 0) {
    				// console.log('data');
    				$("#status option").not('option[value=1]').each(function (index) {
    					$(this).prop('disabled', true);
    				});
    			} else {
    				// console.log(data);
    				if (data[0].tanggal_selesai === null) {
    					$("#status option").not('option[value='+data[0].status+']').each(function (index) {
    						$(this).prop('disabled', true);
    					});
    					$('#stat option').not('option[value=1]').prop('disabled', true);
    				} else {

    				}
    			}
    		});
    	});

    	//
    	$("#status").change(function() {
    		let val = this.value;
    		// alert("Selected value is : " + val);
    		let stat = $('#stat');
    		let v1 = $('#stat option[value=1]');
    		let v0 = $('#stat option[value=0]');

    		stat.val('');

    		if (val !== '') {
    			stat.prop('disabled', false);
    		}
    		else {
    			stat.prop('disabled', true);
    		}

    		if (val == 1) {
    			v1.text('Lengkap');
    			v0.text('Tidak Lengkap');
    		} else if (val == 2) {
    			v1.text('Ada');
    			v0.text('Belum Ada');
    		} else {
    			v1.text('Selesai');
    			v0.text('Masuk');
    		}
    	});

    	//
    	var request;
    	var submitform=$('#newStatus');
    	submitform.on('submit',function(f){
    		f.preventDefault(),
    		f.stopPropagation();

    		// Abort any pending request
    		if (request) {
    			request.abort();
    		}
    		// setup some local variables
    		var f = $(this);

    		// Let's select and cache all the fields
    		var inputs = f.find("input, select, button, textarea");

    		// Serialize the data in the form
    		var serializedData = f.serialize();

    		// Let's disable the inputs for the duration of the Ajax request.
    		// Note: we disable elements AFTER the form data has been serialized.
    		// Disabled form elements will not be serialized.
    		inputs.prop("disabled", true);

    		// Fire off the request to /form.php
    		request = $.ajax({
    			url: "{$link_save_progress}",
    			type: "post",
    			data: serializedData
    		});

    		// Callback handler that will be called on success
    		request.done(function (response, textStatus, jqXHR){
    			// redirect
    			document.location.href = response.url;
    		});

    		// Callback handler that will be called on failure
    		request.fail(function (jqXHR, textStatus, errorThrown){
    			//
    			// console.log( Object.keys(jqXHR['responseJSON']).length);
    		});

    		// Callback handler that will be called regardless
    		// if the request failed or succeeded
    		request.always(function () {
    			// Reenable the inputs
    			inputs.prop("disabled", false);

    		});
    	});

    	// wewenang cek pembayaran: Superadmin saja. Tanpa ini riwayat pembayaran
    	// tetap tampil lengkap, hanya tanpa tombol nilai
    	var boleh_cek_bayar = {$boleh_cek_js};

    	var modalInfoBayar = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBayar'));
    	var modalKonfirm   = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCekBayar'));
    	var cekTunda       = null;

    	// riwayat pembayaran: satu kartu per pembayaran, dengan pil status yang bisa
    	// dibaca sekilas dan tombol nilai untuk yang berhak
    	$('.pesanBayar').on('click', function() {
    		let id   = $(this).data('invoice'),
    			seri = $(this).data('seri') || '';

    		$('#modalBayar #bayarSeri').text(seri === '' ? '' : 'Nota ' + seri);
    		$('#modalBayar #cek').html('<div class="text-muted small text-center py-3">memuat pembayaran ...</div>');

    		$.getJSON('{$link_api_get_pembayaran}', { id: id }, function(bayar){
    			var daftar = $('<div/>').addClass('daftar-bayar');

    			if (! bayar.length) {
    				$('#modalBayar #cek').html('<div class="text-muted small text-center py-3">Belum ada pembayaran tercatat pada nota ini.</div>');
    				return;
    			}

    			for (let i = 0; i < bayar.length; i++) {
    				var sumber = bayar[i].sumber > 2
    					? bayar[i].nama + ' (' + bayar[i].atas_nama + ')'
    					: bayar[i].nama;

    				var status = '<span class="bayar-status"><i class="fal fa-question"></i> Belum dicek</span>';

    				if (bayar[i].status === 3) {
    					status = '<span class="bayar-status ada"><i class="fal fa-check"></i> Dana ada</span>';
    				} else if (bayar[i].status === 2) {
    					status = '<span class="bayar-status tidak"><i class="fal fa-xmark"></i> Dana tidak ada</span>';
    				}

    				var dinilai = bayar[i].tanggal_cek === null
    					? ''
    					: '<div class="bayar-waktu">dinilai ' + tanggal(bayar[i].tanggal_cek) + '</div>';

    				var aksi = '';

    				if (boleh_cek_bayar && bayar[i].status === 1) {
    					aksi = '<div class="bayar-aksi">'
    						+ '<button type="button" class="btn bayar-ya" data-val="3" data-invoice="' + id + '" data-id="' + bayar[i].id + '"><i class="fal fa-check"></i> Dana ada</button>'
    						+ '<button type="button" class="btn bayar-tidak" data-val="2" data-invoice="' + id + '" data-id="' + bayar[i].id + '"><i class="fal fa-xmark"></i> Tidak ada</button>'
    						+ '</div>';
    				}

    				daftar.append($(
    					'<div class="bayar-kartu">'
    						+ '<div class="d-flex align-items-center gap-3">'
    							+ '<span class="bayar-ikon' + (bayar[i].sumber > 2 ? '' : ' tunai') + '">'
    								+ '<i class="fal ' + (bayar[i].sumber > 2 ? 'fa-university' : 'fa-wallet') + '"></i>'
    							+ '</span>'
    							+ '<div class="flex-grow-1 min-w-0">'
    								+ '<div class="bayar-sumber">' + sumber + '</div>'
    								+ '<div class="d-flex flex-wrap align-items-baseline gap-2">'
    									+ '<span class="bayar-nominal">' + bayar[i].nominal + '</span>'
    									+ '<span class="bayar-waktu">' + tanggal(bayar[i].tanggal_bayar) + '</span>'
    								+ '</div>'
    								+ dinilai
    							+ '</div>'
    							+ status
    						+ '</div>'
    						+ aksi
    					+ '</div>'
    				));
    			}

    			$('#modalBayar #cek').empty().append(daftar);
    		});
    	});

    	// menilai dana dikonfirmasi lewat modal bertema, bukan dialog bawaan browser;
    	// riwayatnya ditutup dulu supaya dua modal tidak berebut backdrop
    	$(document).on('click', '.bayar-ya, .bayar-tidak', function() {
    		var tombol = $(this),
    			kartu = tombol.closest('.bayar-kartu'),
    			ada = tombol.hasClass('bayar-ya');

    		cekTunda = {
    			id: tombol.data('id'),
    			invoice: tombol.data('invoice'),
    			val: tombol.data('val')
    		};

    		$('#cekObjek').text(kartu.find('.bayar-sumber').text() + ' · ' + kartu.find('.bayar-nominal').text());
    		$('#cekTanda').toggleClass('tidak', ! ada);
    		$('#cekBayarJudul').text(ada ? 'Tandai dana diterima?' : 'Tandai dana tidak ditemukan?');
    		$('#cekBayarYa').toggleClass('sukses', ada).text(ada ? 'Ya, diterima' : 'Ya, tandai');

    		modalInfoBayar.hide();
    	});

    	// riwayat selesai ditutup -> baru buka konfirmasinya
    	document.getElementById('modalBayar').addEventListener('hidden.bs.modal', function () {
    		if (cekTunda) { modalKonfirm.show(); }
    	});

    	// konfirmasinya ditutup tanpa disimpan -> kembalikan ke riwayat nota tadi
    	document.getElementById('modalCekBayar').addEventListener('hidden.bs.modal', function () {
    		if (cekTunda) { modalInfoBayar.show(); }
    	});

    	$('#cekBayarYa').on('click', function() {
    		if (! cekTunda) { return; }

    		var kirim = cekTunda;
    		cekTunda = null;

    		modalKonfirm.hide();

    		$.post("{$link_update_pembayaran}", {
    			id_pembayaran: kirim.id, status: kirim.val, invoice_id: kirim.invoice
    		}, function (msg) {
    			document.location.href = msg.url;
    		}, 'json').fail(function (xhr) {
    			var galat = (xhr.responseJSON && xhr.responseJSON.status)
    				? xhr.responseJSON.status
    				: 'Penilaian pembayaran gagal disimpan.';

    			cekTunda = kirim;
    			modalKonfirm.show();
    			alert(galat);
    		});
    	});

    	// jumlah dana: hanya angka bulat positif. Kolomnya sengaja type=text supaya
    	// tanda minus, titik, dan huruf e tidak bisa masuk sama sekali; nol ikut
    	// dibuang karena pembayaran bernilai nol tidak berarti apa-apa
    	function rapiJumlahDana() {
    		var kolom = document.getElementById('jumlah_dana');

    		if (! kolom) { return; }

    		var bersih = kolom.value.replace(/\D/g, '').replace(/^0+/, '');

    		if (bersih !== kolom.value) {
    			kolom.value = bersih;
    		}

    		$('#jumlah-bantuan').text(bersih === ''
    			? 'Hanya angka bulat - tanpa minus, titik, atau koma.'
    			: 'Akan dicatat: Rp ' + bersih.replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
    	}

    	$('#jumlah_dana').on('input blur', rapiJumlahDana);

    	// tambah pembayaran
    	$('.tambahBayar').on('click',function(){
    		let tombol = $(this),
    			juragan = tombol.data('juragan'),
    			id = tombol.data('invoice'),
    			kekurangan = tombol.data('kurang'),
    			ttl = $('#jumlah_dana');

    		// invoice_id
    		$('#tambahPembayaran [name="invoice_id"]').val(id);
    		$('#tambahBayarSeri').text(tombol.data('seri') ? 'Nota ' + tombol.data('seri') : '');
    		ttl.val('');
    		if (parseInt(kekurangan)>0) {
    			ttl.val(kekurangan);
    		}

    		rapiJumlahDana();

    		// set dropdown sumber_dana
    		$.getJSON('{$link_api_get_bank}', function(data){
    			var banks = (data[juragan] || {}).bank || {},
    				apnd = '<option value="">Pilih Tujuan</option>',
    				kosong = Object.keys(banks).length === 0;

    			for (var i in banks) {
    				apnd += "<option value = '" + banks[i].id + " '>" + banks[i].nama.toUpperCase() + ' (' + banks[i].atas_nama + ')' + " </option>";
    			}

    			$("#modalTambahBayar #tujuan").empty().append(apnd);

    			// toko tanpa rekening: pilihannya dulu diam-diam kosong saja, jadi
    			// pengguna mengira fitur pembayarannya yang rusak
    			$("#modalTambahBayar #tujuan").prop('disabled', kosong);
    			$("#modalTambahBayar #tujuan-galat").toggleClass('d-none', !kosong);
    			$("#tambahPembayaran button[type=submit]").prop('disabled', kosong);
    		});
    	});

    	var req_pay,
    		tambah_pay=$('#tambahPembayaran');
    	tambah_pay.on('submit',function(f){
    		f.preventDefault(),
    		f.stopPropagation();

    		// minus dan nol sudah dibuang saat mengetik; ini jaring pengaman kalau
    		// kolomnya ditinggalkan kosong atau nilainya diisi lewat alat lain
    		var nominal = parseInt($('#jumlah_dana').val(), 10);

    		if (! nominal || nominal < 1) {
    			alert('Jumlah dana harus angka, lebih dari nol.');

    			return false;
    		}

    		// Abort any pending request
    		if (req_pay) {
    			req_pay.abort();
    		}
    		// setup some local variables
    		var f = $(this);

    		// Let's select and cache all the fields
    		var inputs = f.find("input, select, button, textarea");

    		// Serialize the data in the form
    		var serializedData = f.serialize();

    		// Let's disable the inputs for the duration of the Ajax request.
    		// Note: we disable elements AFTER the form data has been serialized.
    		// Disabled form elements will not be serialized.
    		inputs.prop("disabled", true);

    		// Fire off the request to /form.php
    		req_pay = $.ajax({
    			url: "{$link_tambah_pembayaran}",
    			type: "post",
    			data: serializedData
    		});

    		// Callback handler that will be called on success
    		req_pay.done(function (response, textStatus, jqXHR){
    			// Log a message to the console
    			// console.log(response);
    			// redirect
    			document.location.href = response.url;
    		});

    		// Callback handler that will be called on failure
    		req_pay.fail(function (jqXHR){
    			var err = jqXHR.responseJSON,
    				pesan = 'Gagal menyimpan pembayaran.';

    			if (err) {
    				for (var k in err) {
    					pesan = err[k];
    					break;
    				}
    			}

    			alert(pesan);
    		});

    		// Callback handler that will be called regardless
    		// if the request failed or succeeded
    		req_pay.always(function () {
    			// Reenable the inputs
    			inputs.prop("disabled", false);

    		});
    	});

    	// hapus orderan: alasannya wajib dipilih dan ikut tersimpan di nota
    	var invoiceBatal = 0;
    	
    	// pencatatan pengembalian barang sewaan. Satu klik ini yang memutuskan nota
    	// sewa boleh masuk tab Selesai, unitnya pulih di stok kolam sewa, dan poin
    	// gaji pembuat nota ikut dihitung (atau dicabut lagi kalau dibatalkan)
    	$('.sewaKembali').on('click', function() {
    		var tombol = $(this);
    	
    		if (! confirm('Catat bahwa pakaian sewaan pada nota ' + tombol.data('seri') + ' sudah kembali ke toko?')) {
    			return;
    		}
    	
    		tombol.prop('disabled', true);
    	
    		$.post("{$link_sewa_kembali}", { invoice_id: tombol.data('invoice') }, function(b) {
    			// halaman dimuat ulang saja: tab, counter, dan tombolnya ikut
    			// menyesuaikan keadaan baru tanpa berpindah saringan
    			document.location.reload();
    		});
    	});
    	
    	$('.sewaBatalkan').on('click', function() {
    		var tombol = $(this);
    	
    		if (! confirm('Batalkan pencatatan pengembalian nota ' + tombol.data('seri') + '? Barangnya dianggap masih disewa, poin dan stok ikut mundur.')) {
    			return;
    		}
    	
    		tombol.prop('disabled', true);
    	
    		$.post("{$link_sewa_kembali}", { invoice_id: tombol.data('invoice'), aksi: 'batalkan' }, function(b) {
    			document.location.reload();
    		});
    	});
    	
    	$('.hapusOrderan').on('click',function(){
    		invoiceBatal = $(this).data('invoice');
    		var isLunas = $(this).data('lunas') == '1';

    		$('#batalSeri').text($(this).data('seri'));
    		$('#alasanBatal').val('');
    		$('#catatanBatal').val('').removeAttr('required');
    		$('#wadahCatatanBatal').hide();

    		// Kategori Pembatalan Sah hanya berlaku untuk orderan yang BELUM LUNAS
    		var optgroupSah = $('#alasanBatal optgroup[label="Pembatalan sah"]');
    		if (isLunas) {
    			optgroupSah.hide();
    			optgroupSah.find('option').prop('disabled', true);
    			$('#pesanBatalLunas').show();
    		} else {
    			optgroupSah.show();
    			optgroupSah.find('option').prop('disabled', false);
    			$('#pesanBatalLunas').hide();
    		}

    		var modal = new bootstrap.Modal(document.getElementById('modalBatal'));
    		modal.show();
    	});

    	$('#alasanBatal').on('change', function(){
    		var alasan = $(this).val();
    		if (alasan === 'lainnya' || alasan === 'salah_lainnya') {
    			$('#wadahCatatanBatal').slideDown(150);
    			$('#catatanBatal').attr('required', true).focus();
    		} else {
    			$('#wadahCatatanBatal').slideUp(150);
    			$('#catatanBatal').removeAttr('required').val('');
    		}
    	});

    	$('#submitBatal').on('submit',function(e){
    		e.preventDefault();

    		var alasan = $('#alasanBatal').val();
    		var catatan = $('#catatanBatal').val();

    		if ((alasan === 'lainnya' || alasan === 'salah_lainnya') && !catatan.trim()) {
    			alert('Tuliskan catatan alasan pembatalan.');
    			$('#catatanBatal').focus();
    			return;
    		}

    		$.ajax({
    			method: "POST",
    			url: "{$link_hapus_orderan}",
    			data: {
    				invoice_id: invoiceBatal,
    				alasan: alasan,
    				catatan_batal: catatan
    			}
    		})
    		.done(function( msg ) {
    			if (msg.status && msg.status !== 'Orderan dihapus') {
    				alert(msg.status);
    				return;
    			}
    			document.location.href = msg.url;
    		});
    	});

    	// submit resi
    	$('.kirimResi').on('click',function(){
    		var modal = new bootstrap.Modal(document.getElementById('modalKirim')),
    			invoice = $(this).data('invoice'),
    			count = $(this).data('count'),
    			qty = $('#submitResi [name="qty"]');

    		$('#modalKirim .modal-title').text('Input Resi');
    		$('#submitResi [name="invoice_id"]').val(invoice);
    		qty.val(count).attr('readonly', true);

    		if (parseInt( count ) > 1 ) {
    			// remove readonly
    			qty.attr('readonly', false);
    		}

    		modal.show();
    	});

    	// source: https://stackoverflow.com/a/27521981/2094645
    	function dropdownToArray(target) {
            var targets = $(target + " option");
            var results = [];
            targets.each(function () {
                var val = $(this).val();
                if (val !== '') results.push(val);
            });
            return results;
    	}

    	// sunting resi
    	$('.suntingResi').on('click', function() {
    		var id = $(this).data('id'), // id pengiriman
    			modal = new bootstrap.Modal(document.getElementById('modalKirim')),
    			invoice = $(this).data('invoice');

    		$('#modalKirim .modal-title').text('Sunting Resi');

    		$('#submitResi').attr('id', 'suntingResi');

    		$('#suntingResi [name="invoice_id"]').val(invoice);

    		// get data
    		// source: https://stackoverflow.com/a/18867652/2094645
    		$.getJSON('{$link_api_pengiriman}/get', { id: id }, function(data) {
    			// set array
    			var arr = dropdownToArray('#suntingResi #kurir');
    			if(jQuery.inArray(data.kurir, arr) != -1) {
    				$('#suntingResi #kurir').val(data.kurir);
    				$('#suntingResi #lainnya').attr('disabled', true);
    			} else {
    				$('#suntingResi #kurir').val('lainnya');
    				$('#suntingResi #lainnya').attr('disabled', false).val(data.kurir);
    			}

    			$('#suntingResi #resi').val(data.resi);
    			var date = new Date(data.tanggal_kirim * 1000),
    				tggl = date.getFullYear() +'-'+("0" + date.getMonth()).slice(-2) + '-'+ ("0" + date.getDate()).slice(-2);

    			console.log(tggl);
    			$('#suntingResi #tanggal_kirim').val(tggl);
    			$('#suntingResi #isi_paket').val(data.qty_kirim).attr('readonly', true);
    			$('#suntingResi #ongkir').val(data.ongkir);

    			$('#suntingResi [name="invoice_id"]').after($('<input>').attr({
    				type: 'hidden',
    				name: 'id_pengiriman'
    			}));

    			$('#suntingResi [name="id_pengiriman"]').val(id);

    		});

    		modal.show();
    	});

    	// kurir
    	$('#submitResi #kurir').on('change', function() {
    		var lainnya = $('#submitResi #lainnya');
    		var resi = $('#submitResi #resi');
    		if ($(this).val() === "lainnya") {
    			lainnya.attr('disabled', false);
    			lainnya.attr('required', true);
    		} else {
    			lainnya.attr('disabled', true);
    		}

    		if ($(this).val() === "COD") {

    			resi.val('{$codvalue}');
    		} else {
    			resi.val('');
    		}
    	});


    	// Variable to hold request
    	var request_resi;

    	// Bind to the submit event of our form
    	$(document).on('submit', "#submitResi", function(event){

    		// Prevent default posting of form - put here to work in case of errors
    		event.preventDefault();

    		// Abort any pending request
    		if (request_resi) {
    			request_resi.abort();
    		}
    		// setup some local variables
    		var form = $(this);

    		// Let's select and cache all the fields
    		var inputs = form.find("input, select, button, textarea");

    		// Serialize the data in the form
    		var serializedData = form.serialize();

    		// Let's disable the inputs for the duration of the Ajax request.
    		// Note: we disable elements AFTER the form data has been serialized.
    		// Disabled form elements will not be serialized.
    		inputs.prop("disabled", true);

    		// Fire off the request to /form.php
    		request_resi = $.ajax({
    			url: "{$link_api_pengiriman}/save",
    			type: "post",
    			data: serializedData
    		});

    		// Callback handler that will be called on success
    		request_resi.done(function (response, textStatus, jqXHR){
    			document.location.href = response.url;
    		});

    		// Callback handler that will be called on failure
    		request_resi.fail(function (jqXHR, textStatus, errorThrown){
    			// Log the error to the console
    			console.error(
    				"The following error occurred: "+
    				textStatus, errorThrown
    			);
    		});

    		// Callback handler that will be called regardless
    		// if the request failed or succeeded
    		request_resi.always(function () {
    			// Reenable the inputs
    			inputs.prop("disabled", false);
    		});

    	});

    	var request_suntingResi;
    	$(document).on('submit', "#suntingResi", function(event){

    		// Prevent default posting of form - put here to work in case of errors
    		event.preventDefault();

    		// Abort any pending request
    		if (request_suntingResi) {
    			request_suntingResi.abort();
    		}
    		// setup some local variables
    		var form = $(this);

    		// Let's select and cache all the fields
    		var inputs = form.find("input, select, button, textarea");

    		// Serialize the data in the form
    		var serializedData = form.serialize();

    		// Let's disable the inputs for the duration of the Ajax request.
    		// Note: we disable elements AFTER the form data has been serialized.
    		// Disabled form elements will not be serialized.
    		inputs.prop("disabled", true);

    		// Fire off the request to /form.php
    		request_suntingResi = $.ajax({
    			url: "{$link_api_pengiriman}/save",
    			type: "post",
    			data: serializedData
    		});

    		// Callback handler that will be called on success
    		request_suntingResi.done(function (response, textStatus, jqXHR){
    			document.location.href = response.url;
    		});

    		// Callback handler that will be called on failure
    		request_suntingResi.fail(function (jqXHR, textStatus, errorThrown){
    			// Log the error to the console
    			console.error(
    				"The following error occurred: "+
    				textStatus, errorThrown
    			);
    		});

    		// Callback handler that will be called regardless
    		// if the request failed or succeeded
    		request_suntingResi.always(function () {
    			// Reenable the inputs
    			inputs.prop("disabled", false);
    		});

    	});

    	//
    	var maxLength = 7;
    	$(".keterangan").each(function(){
    		var myStr = $(this).html(),
    			lines = myStr.replace(/^\\s+|\\s+$|\\s+(?=\\s)/g, "").split("<br>"); // https://stackoverflow.com/a/18066013/2094645

    		if (lines.length > maxLength) {
    			var newStr = lines.slice(0, maxLength),
    				hideStr = lines.slice(maxLength, lines.length);

    			$(this).empty().html(newStr.join('<br/>'));
    			$(this).append('<a href="javascript:void(0);" class="read-more ms-1 font-semibold whitespace-nowrap">lanjut ...</a>');
    			$(this).append('<span class="more-text"><br/>' + hideStr.join('<br/>') + '</span>');
    		}
    	});
    	$(".read-more").on('click', function(){
    		$(this).siblings(".more-text").contents().unwrap();
    		$(this).remove();
    	});

    	// salin tautan cetak invoice ke clipboard
    	$(".salinTautan").on('click', function(){
    		var tombol  = $(this),
    			tautan  = tombol.data('link'),
    			asali   = tombol.html();

    		navigator.clipboard.writeText(tautan).then(function () {
    			tombol.html('<i class="fal fa-check"></i>');
    			window.setTimeout(function () {
    				tombol.html(asali);
    			}, 1500);
    		});
    	});

    	// filter kategori pembayaran: langsung pindah ke tab yang sama dengan kategori terpilih
    	$('#filterKategori').on('change', function() {
    		document.location.href = $(this).data('tautan') + '/' + this.value;
    	});

    	// disable hit enter
    	// https://stackoverflow.com/a/895231/2094645
    	$(document).on("keydown", ":input:not(textarea):not(.allow-enter)", function(event) {
    		if(event.keyCode == 13) {
    			event.preventDefault();
    			return false;
    		}
    	});

    	// create date from unix
    	function tanggal(unixtime) {
    		var u = new Date(unixtime*1000);

    		return ('0' + u.getDate()).slice(-2) + '/' + ('0' + (u.getMonth()+1)).slice(-2) + '/' + u.getFullYear();
    	};
    });

     // document ready

     // badge merah hanya untuk tab yang isinya bukan nol, supaya tidak semua tab terlihat mendesak
     function pasangBadge(pilihan, hitungan) {
        var badge = $(pilihan).html(hitungan.text),
            ada = parseInt(hitungan.num, 10) > 0;

        badge.toggleClass('bg-danger text-white', ada);
        badge.toggleClass('bg-ink-100 text-ink-600', !ada);
     }

     document.addEventListener("DOMContentLoaded", function(event) {
        $.getJSON('{$link_api_counter_tab}', {}, function(counter){
            pasangBadge('#counterSemua', counter.semua);
            pasangBadge('#counterCekBayar', counter.cek_bayar);
            pasangBadge('#counterBelumProses', counter.belum_proses);
            pasangBadge('#counterDalamProses', counter.dalam_proses);
            pasangBadge('#counterSelesai', counter.selesai);
            pasangBadge('#counterMasihDisewa', counter.masih_disewa);
        });
    });

     // Tombol geser strip tab: panah muncul hanya kalau tab memang lebih lebar
     // dari wadahnya, dan mati begitu mentok di ujung. Angkanya dimuat belakangan
     // lewat AJAX di atas dan bisa mengubah lebar tab, jadi ukurannya dipantau
     // dengan MutationObserver, bukan dihitung sekali.
     $(function() {
        var wadah = document.querySelector('.toolbar-transaksi__geser');

        if (! wadah) {
            return;
        }

        var jalur = wadah.querySelector('.toolbar-transaksi__tab');
        var kiri = wadah.querySelector('[data-geser="-1"]');
        var kanan = wadah.querySelector('[data-geser="1"]');
        var penggunaSedangGeser = false;

        function diukur() {
            var bisa = jalur.scrollWidth - jalur.clientWidth > 2;

            wadah.classList.toggle('bisa-geser', bisa);
            kiri.disabled = jalur.scrollLeft <= 2;
            kanan.disabled = jalur.scrollLeft + jalur.clientWidth >= jalur.scrollWidth - 2;
        }

        function selaraskanTabAktif(animasi) {
            var tabAktif = jalur.querySelector('.btn-primary');
            if (! tabAktif) return;

            var jalurRect = jalur.getBoundingClientRect();
            var tabRect = tabAktif.getBoundingClientRect();

            var paddingToleransi = 8;
            var tampakPenuh = (tabRect.left >= jalurRect.left + paddingToleransi) && (tabRect.right <= jalurRect.right - paddingToleransi);

            if (! tampakPenuh) {
                var tabPos = (tabRect.left - jalurRect.left) + jalur.scrollLeft;
                var targetScroll = tabPos - (jalur.clientWidth / 2) + (tabRect.width / 2);
                var batasMaks = Math.max(0, jalur.scrollWidth - jalur.clientWidth);
                targetScroll = Math.max(0, Math.min(targetScroll, batasMaks));

                if (animasi) {
                    jalur.scrollTo({ left: targetScroll, behavior: 'smooth' });
                } else {
                    jalur.scrollLeft = targetScroll;
                }
            }
            diukur();
        }

        function geser(tombol) {
            penggunaSedangGeser = true;
            var langkah = Math.max(120, Math.round(jalur.clientWidth * 0.75));

            jalur.scrollBy({ left: langkah * parseInt(tombol.dataset.geser, 10), behavior: 'smooth' });
        }

        // Simpan posisi scroll saat pengguna mengeklik tab status agar setelah halaman dimuat ulang
        // posisinya tetap di tempat semula dan tidak kembali ke awal (Semua)
        jalur.addEventListener('click', function(e) {
            var tautan = e.target.closest('a.btn');
            if (tautan) {
                try {
                    sessionStorage.setItem('toolbar_transaksi_tab_pos', String(jalur.scrollLeft));
                } catch (err) {}
            }
        });

        // Pulihkan posisi scroll dari sessionStorage jika ada, atau pastikan tab aktif tampak di layar
        var posTersimpan = null;
        try {
            posTersimpan = sessionStorage.getItem('toolbar_transaksi_tab_pos');
            sessionStorage.removeItem('toolbar_transaksi_tab_pos');
        } catch (err) {}

        if (posTersimpan !== null && ! isNaN(parseFloat(posTersimpan))) {
            jalur.scrollLeft = parseFloat(posTersimpan);
            diukur();
            selaraskanTabAktif(false);
        } else {
            selaraskanTabAktif(false);
        }

        jalur.addEventListener('pointerdown', function() { penggunaSedangGeser = true; }, { passive: true });
        jalur.addEventListener('touchstart', function() { penggunaSedangGeser = true; }, { passive: true });
        kiri.addEventListener('click', function() { geser(kiri); });
        kanan.addEventListener('click', function() { geser(kanan); });
        jalur.addEventListener('scroll', diukur, { passive: true });
        window.addEventListener('resize', function() {
            diukur();
            if (! penggunaSedangGeser) {
                selaraskanTabAktif(false);
            }
        });
        window.addEventListener('load', function() {
            diukur();
            if (! penggunaSedangGeser) {
                selaraskanTabAktif(false);
            }
        });

        if (window.MutationObserver) {
            new MutationObserver(function() {
                diukur();
                if (! penggunaSedangGeser) {
                    selaraskanTabAktif(false);
                }
            }).observe(jalur, { subtree: true, childList: true, characterData: true });
        }

        diukur();
    });
    JS;

$packer    = new Tholu\Packer\Packer($js, 'Normal', true, false, true);
$packed_js = $packer->pack();
echo '<script>' . $packed_js . '</script>';
?>
<?= $this->endSection() ?>
