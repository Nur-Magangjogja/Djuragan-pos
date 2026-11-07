<?php
/**
 * Variabel di bawah ini diisi oleh Admin\Crm::index() lewat view('admin/crm/dasbor', $data).
 * CodeIgniter meng-extract array tersebut saat render (View::render -> extract()), jadi tidak ada
 * assignment di file ini. Blok komentar ini hanya penanda tipe untuk IntelliSense, bukan untuk dijalankan.
 *
 * @var array  $ringkasan  metrik KPI dari CrmModel::getRingkasan()
 * @var array  $reminders  jadwal follow-up dari CrmModel::getReminders()
 */
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    /* Hanya untuk tampilan responsif: tanggal ditarik ke tepi kanan barisnya
       supaya baris badge + tanggal terpakai penuh. Di atas 992px tidak ada
       satu pun yang berubah dari susunan aslinya. */
    @media (max-width: 991.98px) {
        .pengingat-waktu {
            justify-content: space-between;
        }

        /* Jarak dalam kartu pengingat dulu ketumpuk: style.css mengunci
           .card-body{padding:1.25rem !important} sehingga kelas p-0 di markup
           kalah (20px), lalu tiap baris menambah isian sendiri, jadi teks baru
           mulai 33px dari tepi kartu dan judul panjang belah tiga baris.
           Isian badan kartu dinolkan di layar sempit; yang tersisa cuma isian
           tiap baris. Butuh !important karena kunci style.css dan .p-0 di
           markup sama-sama !important. */
        .kartu-pengingat > .card-body {
            padding: 0 !important;
        }

        .kartu-pengingat .card-header {
            padding: 0.5rem 0.75rem !important;
        }

        /* Isian atas-bawah tiap baris pengingat dinaikkan dari 0,5rem: dengan
           8px itu baris keterangan ("Target penyelesaian...") terasa nempel ke
           baris berikutnya. Tombol di pojok kanan bawah ikut turun supaya
           tetap sejajar dengan isian barisnya. */
        .kartu-pengingat .list-group-item {
            padding: 0.8rem 0.75rem !important;
        }

        .kartu-pengingat > .text-end {
            padding: 0 0.75rem 0.7rem !important;
        }

        /* Tombol aksi tiap baris pengingat dipindah ke pojok kanan BAWAH barisnya
           — sama seperti kartu pintasan — supaya baris badge + tanggal punya lebar
           penuh dan tanggalnya benar-benar mepet ke tepi kanan kartu. Judul dan
           keterangan diberi ruang kanan sebesar kedua tombol agar tidak tertindih. */
        .kartu-pengingat .list-group-item {
            position: relative;
        }

        .kartu-pengingat .list-group-item > .text-end {
            position: absolute;
            right: 0.75rem;
            bottom: 0.8rem;
            margin-left: 0;
        }

        .kartu-pengingat .list-group-item h6,
        .kartu-pengingat .list-group-item p {
            padding-right: 6.5rem;
        }
    }

    /* Kartu KPI di layar sempit hanya kebagian setengah/tiga per baris, sementara
       angkanya ditulis penuh: "Rp 8.332.584.447" butuh 134px pada 1,05rem dan
       190px pada ukuran h4 bawaan, sedangkan ruang isinya cuma 119px (360px) atau
       152px (800px) — "Rp" jatuh sendirian di baris atas. Angka finansial dikecilkan
       ke 0,875rem dan ditahan satu baris; isian kartunya dikurangi sedikit untuk
       membeli ruang kalau omset tumbuh. Batasnya 991,98px — sama seperti batas
       sidebar jadi laci — karena di 800px kartu sudah empat-sebaris sempit.
       Angka kartu baris pertama (h3) tidak dikecilkan: yang terpanjang ("15,056")
       baru memakai 60px dari 151px. Desktop (>=992px) tidak disentuh. */
    @media (max-width: 991.98px) {
        .card h4.fw-bold {
            font-size: 0.875rem;
            white-space: nowrap;
        }

        .kartu-kpi-sejajar {
            padding: 0.85rem !important;
        }
    }

    @media (max-width: 767.98px) {
        .card h3.fw-bold {
            font-size: 1.05rem;
        }

        .card span.text-muted.small.fw-semibold {
            font-size: 0.72rem;
        }

        /* ikon di pojok kanan enam kartu baris pertama dibuang: ruang 25px-nya
           lebih berguna untuk label yang panjangnya sudah terpotong ("Follow-up
           Tagihan"), dan warna kiri kartu sudah menandai jenisnya */
        .card-body > .d-flex.justify-content-between > i {
            display: none;
        }

        /* grup kartu kedua tidak memakai h-100 seperti grup pertama; hanya di
           layar sempit tingginya disamakan supaya dua kartu sekolom tidak beda */
        .kartu-kpi-sejajar {
            height: 100%;
        }

        /* kartu naik: kurangi jarak atas container di layar sempit */
        body.has-app-sidebar > .container-xxl.py-4 {
            padding-top: 0.75rem !important;
        }
    }

    /* kartu terakhir yang sendirian (jumlah kolom baris ganjil) melebar penuh
       sampai ke tepi di ponsel & tablet; di desktop >=1200px grup kedua sudah
       lima-sebaris lewat col-xl, jadi tidak perlu dipanjangkan */
    @media (max-width: 1199.98px) {
        .row.g-3 > .col-6:last-child:nth-child(odd) {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }

    /* Empat kartu pintasan di kolom kanan, layar sempit: judul dan keterangannya
       memakai lebar penuh (tombol tidak lagi menggantung di kanan tengah), dan
       tombolnya pindah ke pojok kanan bawah, di samping baris terakhir
       keterangannya. Ruang kanan khusus itu hanya diberikan ke keterangan,
       supaya judulnya tidak ikut tergencet. .d-flex Bootstrap dan .btn-sm di
       style.css sama-sama !important, jadi dua-duanya ikut !important. */
    @media (max-width: 991.98px) {
        .shortcut-crm .card-body {
            display: block !important;
            position: relative;
        }

        .shortcut-crm .card-body small {
            display: block;
            padding-right: 7rem;
        }

        .shortcut-crm .card-body > .btn {
            position: absolute;
            right: 1rem;
            bottom: 1rem;
            padding: 0.2rem 0.6rem !important;
            font-size: 0.74rem !important;
        }
    }

    /* Judul halaman di layar sempit ikut ukuran judul halaman lain: rumus h3
       Bootstrap, sama seperti <h1 class="h3"> di Produk / Pencatatan COD.
       Desktop tidak disentuh, tetap ukuran h4 bawaannya. */
    @media (max-width: 991.98px) {
        .judul-dasbor {
            font-size: calc(1.3rem + 0.6vw);
        }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <h4 class="mb-0 fw-bold judul-dasbor">
                <i class="fal fa-chart-pie text-primary me-2 d-none d-lg-inline"></i>
                <?php /* ikonnya hanya tampil di layar lebar; di bawah breakpoint
                        kolomnya (992px) ikon dilepas supaya judulnya muat satu baris */ ?>
                <span class="d-lg-none">Dashboard CRM</span>
                <span class="d-none d-lg-inline">Dashboard CRM</span>
            </h4>
        </div>
    </div>



    <!-- 6 KPI Bercerita Utama -->
    <div class="row g-3 mb-4">
        <!-- Customer Aktif Bulan Ini -->
        <div class="col-xl col-md-4 col-6">
            <a href="<?= site_url('admin/crm/pelanggan?status=aktif') ?>" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small fw-semibold">Customer Aktif</span>
                            <i class="fal fa-user-check text-success fs-5"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-success mt-1"><?= number_format($ringkasan['aktif_bulan_ini']) ?></h3>
                        <div class="text-muted small mt-1" style="font-size: 11px;">transaksi 30 hari terakhir</div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Customer Baru Bulan Ini -->
        <div class="col-xl col-md-4 col-6">
            <a href="<?= site_url('admin/crm/pelanggan?status=baru') ?>" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-info">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small fw-semibold">Customer Baru</span>
                            <i class="fal fa-sparkles text-info fs-5"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-info mt-1"><?= number_format($ringkasan['pelanggan_baru_bulan_ini']) ?></h3>
                        <div class="text-muted small mt-1" style="font-size: 11px;">order perdana bulan ini</div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Customer Repeat Order -->
        <div class="col-xl col-md-4 col-6">
            <a href="<?= site_url('admin/crm/pelanggan?segmen=repeat') ?>" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small fw-semibold">Repeat Order</span>
                            <i class="fal fa-sync text-primary fs-5"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-primary mt-1"><?= number_format($ringkasan['repeat_order']) ?></h3>
                        <div class="text-muted small mt-1" style="font-size: 11px;">pelanggan pesan > 1x</div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Customer Tidak Aktif / Pasif -->
        <div class="col-xl col-md-4 col-6">
            <a href="<?= site_url('admin/crm/pelanggan?status=tidak_aktif') ?>" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-secondary">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small fw-semibold">Customer Pasif</span>
                            <i class="fal fa-user-clock text-secondary fs-5"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-secondary mt-1"><?= number_format($ringkasan['pasif_pelanggan']) ?></h3>
                        <div class="text-muted small mt-1" style="font-size: 11px;">> 60 hari tanpa order</div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Follow-up Tagihan -->
        <div class="col-xl col-md-4 col-6">
            <a href="<?= site_url('admin/crm/followup?tab=tagihan') ?>" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small fw-semibold">Follow-up Tagihan</span>
                            <i class="fal fa-money-bill-wave text-danger fs-5"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-danger mt-1"><?= number_format($ringkasan['tagihan_pending']) ?></h3>
                        <div class="text-muted small mt-1" style="font-size: 11px;">invoice belum lunas</div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Total Pelanggan -->
        <div class="col-xl col-md-4 col-6">
            <a href="<?= site_url('admin/crm/pelanggan') ?>" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-dark">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small fw-semibold">Total Pelanggan</span>
                            <i class="fal fa-users text-dark fs-5"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-dark mt-1"><?= number_format($ringkasan['total_pelanggan']) ?></h3>
                        <div class="text-muted small mt-1" style="font-size: 11px;">database kontak &amp; akun</div>
                    </div>
                </div>
            </a>
        </div>

    </div>

    <!-- Ringkasan Akumulasi & Finansial -->
    <div class="row g-3 mb-4">
        <div class="col-xl col-md-3 col-6">
            <div class="card border-0 shadow-sm bg-white p-3 kartu-kpi-sejajar">
                <span class="text-muted small fw-semibold">Akumulasi Omset POS</span>
                <h4 class="fw-bold mb-0 text-success mt-1">Rp <?= number_format($ringkasan['total_omset'], 0, ',', '.') ?></h4>
                <div class="small text-muted" style="font-size: 11px;">seluruh riwayat transaksi</div>
            </div>
        </div>
        <div class="col-xl col-md-3 col-6">
            <div class="card border-0 shadow-sm bg-white p-3 kartu-kpi-sejajar">
                <span class="text-muted small fw-semibold">Peluang Merge<span class="d-none d-lg-inline"> Akun Duplikat</span></span>
                <h4 class="fw-bold mb-0 text-primary mt-1"><?= number_format($ringkasan['duplikat_terdeteksi']) ?> grup</h4>
                <div class="small text-muted" style="font-size: 11px;"><a href="<?= site_url('admin/crm/duplikat') ?>" class="text-decoration-none">Periksa & gabungkan &rarr;</a></div>
            </div>
        </div>
        <div class="col-xl col-md-3 col-6">
            <div class="card border-0 shadow-sm bg-white p-3 kartu-kpi-sejajar">
                <span class="text-muted small fw-semibold"><span class="d-none d-lg-inline">Tagihan </span>Belum Lunas</span>
                <h4 class="fw-bold mb-0 text-danger mt-1">Rp <?= number_format($ringkasan['total_piutang'], 0, ',', '.') ?></h4>
                <div class="small text-muted" style="font-size: 11px;"><?= number_format($ringkasan['tagihan_pending']) ?> invoice pending</div>
            </div>
        </div>
    </div>

    <!-- Dua Kolom: Pengingat Tindakan Hari Ini & Menu Cepat -->
    <div class="row g-4 mb-4">
        <!-- Kolom Kiri: Reminder & Jadwal Penting Hari Ini -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100 kartu-pengingat">
                <div class="card-header bg-white py-3 border-bottom d-lg-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">
                        <i class="fal fa-bell text-warning me-2"></i> Pengingat & Tindakan Hari Ini
                    </h6>
                    <?php /* desktop: tautannya satu baris dengan judulnya di sisi kanan */ ?>
                    <div class="text-end d-none d-lg-block">
                        <a href="<?= site_url('admin/crm/followup?tab=reminder') ?>" class="small text-decoration-none">
                            Lihat Semua Jadwal &rarr;
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($reminders)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-smile fa-3x mb-2 text-success opacity-50"></i>
                            <p class="mb-0">Tidak ada jadwal mendesak atau follow-up tertunda hari ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($reminders as $r): ?>
                                <div class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                    <?php /* pembungkus teksnya harus memanjang mengisi sisa
                                            baris, kalau tidak baris badge + tanggal ikut
                                            mengecil pas isi teksnya dan tanggalnya tidak
                                            bisa digeser ke kanan */ ?>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="pengingat-waktu d-flex align-items-center gap-2 mb-1">
                                            <span class="badge bg-<?= esc($r['warna']) ?>"><?= esc($r['status_waktu']) ?></span>
                                            <span class="text-muted small text-nowrap"><i class="fal fa-calendar-alt me-1"></i><?= date('d M Y', strtotime($r['tanggal'])) ?></span>
                                        </div>
                                        <h6 class="mb-1 fw-bold text-dark"><?= esc($r['judul']) ?></h6>
                                        <p class="mb-0 text-muted small"><?= esc($r['deskripsi']) ?></p>
                                    </div>
                                    <div class="text-end flex-shrink-0 ms-3">
                                        <a href="<?= site_url('admin/crm/pelanggan/' . $r['pelanggan_id']) ?>" class="btn btn-sm btn-outline-primary" title="Profil 360">
                                            <i class="fal fa-id-card"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php /* layar sempit: tautannya masuk ke dalam kartu, di pojok kanan
                        bawah daftar, jadi ia ikut turun kalau pengingatnya lebih
                        dari satu; paddingnya mengikuti isian kartu (p-3) */ ?>
                <div class="text-end px-3 pb-3 d-lg-none">
                    <a href="<?= site_url('admin/crm/followup?tab=reminder') ?>" class="small text-decoration-none">
                        Lihat Semua Jadwal &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Shortcut Aksi Cepat CRM & Info Gateway -->
        <div class="col-lg-5">
            <div class="row g-3">
                <!-- Shortcut Follow-up Customer -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm bg-white shortcut-crm">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                    <i class="fal fa-tasks fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">Follow-up Customer & Tagihan</h6>
                                    <small class="text-muted">Tindak lanjuti prospek, fitting, dan invoice</small>
                                </div>
                            </div>
                            <a href="<?= site_url('admin/crm/followup') ?>" class="btn btn-primary btn-sm fw-semibold">
                                Buka Follow-up
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Shortcut Duplikat -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm bg-white shortcut-crm">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                    <i class="fal fa-clone fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">Deteksi & Gabung Duplikat</h6>
                                    <small class="text-muted">Rapikan akun kembar tanpa hilang transaksi</small>
                                </div>
                            </div>
                            <a href="<?= site_url('admin/crm/duplikat') ?>" class="btn btn-outline-danger btn-sm fw-semibold">
                                Review Duplikat
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
