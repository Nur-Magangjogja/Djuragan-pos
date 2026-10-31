<?php
/**
 * Variabel disuntik oleh controller Admin\Crm::pelanggan_detail() via view().
 *
 * @var string $title         judul halaman
 * @var array  $pelanggan     data profil pelanggan (Customer 360)
 * @var array  $nilai         ringkasan nilai & kontribusi pelanggan
 * @var array  $transaksiList riwayat transaksi POS & invoice
 * @var array  $pembayaranList riwayat mutasi pembayaran
 * @var array  $timeline      timeline aktivitas multi-channel
 * @var array  $followupList  daftar follow-up pelanggan
 * @var array  $allTags       master tag untuk pilihan tambah tag
 * @var array  $templates     template pesan cepat WhatsApp
 */
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    /* Berlaku di semua lebar. */

    /* Enam tab navigasi desktop (>= 992px):
       Dibuat lebih besar, proporsional, dan nyaman dibaca (font-size & padding naik)
       menggunakan clamp responsif agar otomatis menyesuaikan resolusi laptop/monitor.
       Stripnya tidak pernah dibuat wadah gulir: overflow-x: hidden + overflow-y
       bawaan memaksa Chrome menampilkan bilah gulir vertikal (15px di tepi kanan),
       dan memotong tab terakhir. Kalau enam label tidak muat, tab terakhir turun
       satu baris. */
    /* Enam tab navigasi desktop (>= 992px):
       Berbentuk tab persegi panjang utuh (tertutup 4 sisi dengan border dan 4 sudut melengkung halus).
       Tidak memakai gaya folder tab klasik yang bawahnya bolong, melainkan persegi panjang mandiri yang rapi. */
    #customer360Tabs {
        border-bottom: 0 !important;
        margin-bottom: 0 !important;
        flex: 1 1 auto;
        min-width: 0;
        flex-wrap: wrap;
        /* dikumpulkan ke kiri, tidak diuraikan sampai tepi kanan: dulu
           space-between mendorong tab terakhir tepat di atas panel "Catat
           Interaksi Baru" sehingga terlihat menumpuk. */
        justify-content: flex-start;
        gap: 0.3rem;
        row-gap: 0.3rem;
        overflow: visible;
        align-items: center;
    }

    #customer360Tabs .nav-item {
        flex: 0 0 auto;
        margin: 0 !important;
    }

    #customer360Tabs .nav-link {
        margin: 0 !important;
        margin-bottom: 0 !important;
        border: 1.5px solid transparent !important;
        border-radius: 7px !important;
        /* lebih compact: padding vertikal & horizontal dikecilkan, font diturunkan */
        padding: 0.3rem 0.6rem !important;
        font-size: 0.8rem !important;
        line-height: 1.15 !important;
        white-space: nowrap;
        transition: all 0.15s ease;
        display: inline-flex;
        align-items: center;
    }

    #customer360Tabs .nav-link > i {
        margin-right: 0.3rem !important;
        font-size: 0.8rem !important;
    }

    #customer360Tabs .nav-link .badge {
        margin-left: 0.3rem !important;
        padding: 0.05rem 0.35rem !important;
        font-size: 0.68rem !important;
        border-radius: 5px !important;
    }

    /* Hover tab tidak aktif */
    #customer360Tabs .nav-link:hover:not(.active) {
        color: #101828 !important;
        background-color: #f8fafc !important;
        border-color: #e2e8f0 !important;
    }

    #customer360Tabs .nav-link:focus-visible {
        color: #101828;
        background-color: #f8fafc;
        border-color: #cbd5e1;
        outline: none;
        box-shadow: 0 0 0 3px rgba(16, 24, 40, 0.08);
    }

    #customer360Tabs .nav-link:active:not(.active) {
        background-color: #f1f5f9;
    }

    /* Tab aktif: PERSEGI PANJANG UTUH (tertutup 4 sisi dengan border solid dan melengkung rata 4 sudut) */
    #customer360Tabs .nav-link.active,
    #customer360Tabs .nav-link.active:hover,
    #customer360Tabs .nav-link.active:focus {
        background-color: #ffffff !important;
        border: 1.5px solid #cbd5e1 !important;
        border-top: 1.5px solid #cbd5e1 !important;
        border-bottom: 1.5px solid #cbd5e1 !important;
        border-left: 1.5px solid #cbd5e1 !important;
        border-right: 1.5px solid #cbd5e1 !important;
        border-radius: 7px !important;
        color: #0f172a !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08) !important;
    }

    /* Pemilih bagian di layar sempit: font-weight 600 supaya setara dengan
       label tab yang digantikannya. */
    #customer360Pilih {
        font-weight: 600;
    }

    /* Badge jenis order di kartu ringkasan: 3 badge selalu proporsional
       dan sebaris dengan grid 3 kolom rata, teks di tengah, dan ikon presisi */
    .rincian-order {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 0.4rem;
    }

    .rincian-order > .badge {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        white-space: nowrap !important;
        padding: 0.38rem 0.25rem !important;
        font-size: 0.76rem !important;
        font-weight: 600 !important;
        border-radius: 6px !important;
        width: 100% !important;
        min-width: 0 !important;
    }

    .rincian-order > .badge > i {
        margin-right: 0.25rem !important;
        font-size: 0.72rem !important;
    }

    /* Titik timeline harus duduk di atas garisnya. Di markup posisinya inline
       (left:-33px) dan itu 7px meleset ke kanan begitu wadahnya memakai
       padding ps-4 + border 2px, jadi dikoreksi dengan !important. */
    .timeline-titik {
        left: -40px !important;
    }

    /* Hanya isi timeline yang digulir — di desktop maupun ponsel — supaya kepala
       tab ("Urutan Aktivitas & Interaksi Customer" + badge Multi-channel) dan
       pemantik "Catat Interaksi Baru" tetap di tempat, dan halaman tidak
       memanjang ribuan piksel. Wadah gulir ini pembungkus .timeline-container —
       bukan elemen itu sendiri — karena titik ikon timeline duduk 14px di luar
       garis kiri; margin/padding kiri di bawah memberi ruang itu di dalam wadah
       tanpa menggeser posisinya. */
    .gulir-timeline {
        max-height: 70vh;
        overflow-y: auto;
        overflow-x: hidden;
        margin-left: -1rem;
        padding-left: 1rem;
        padding-right: 0.5rem;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f1f5f9;
    }

    .gulir-timeline::-webkit-scrollbar {
        width: 8px;
    }

    .gulir-timeline::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }

    .gulir-timeline::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    /* Form catat interaksi cuma ada satu: #modalCatatInteraksi. Di layar sempit
       ia memang pop-up Bootstrap, tapi di desktop dipaksa tampil inline di kolom
       kanannya (persis seperti kartu sebelumnya) — tombol pemicunya disembunyikan
       di sana, jadi tidak pernah ada yang memanggil .modal('show') di desktop. */
    @media (min-width: 992px) {
        #modalCatatInteraksi {
            display: block !important;
            position: static !important;
            top: auto !important;
            left: auto !important;
            width: auto !important;
            height: auto !important;
            overflow: visible !important;
            z-index: auto;
            opacity: 1 !important;
            visibility: visible !important;
            background: none;
        }

        #modalCatatInteraksi .modal-dialog {
            max-width: 100%;
            margin: 0 !important;
            min-height: 0 !important;
            height: auto !important;
            display: block !important;
            /* Biang keroknya: .modal.fade .modal-dialog punya
               transform: translate(0, -50px) selama popus belum berkelas .show.
               Di desktop popus ini dipaksa tampil inline dan tidak pernah di-show
               lewat JS, jadi tidak pernah dapat .show -> kartunya naik terus ke atas
               menutupi strip tab. Dinetralkan di sini. */
            transform: none !important;
            transition: none !important;
        }

        #modalCatatInteraksi .modal-content {
            max-height: none !important;
            height: auto !important;
            overflow: visible !important;
            transform: none !important;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05) !important;
        }

        #modalCatatInteraksi .modal-body {
            overflow: visible;
        }

        /* Turunkan isi tab menjauh dari strip tab. Padding global .card-body
           terkunci 1.25rem oleh !important di style.css, jadi khusus kartu ini
           dinaikkan; kedua kolom (timeline & panel) ikut turun bersamaan karena
           berada di baris yang sama, jadi tetap sejajar dan proporsional. */
        .body-360 {
            padding-top: 2.25rem !important;
        }
    }

    /* Tabel di dalam tab harus bisa discroll mendatar di HP / layar sempit
       agar informasi (item pakaian, total tagihan, dibayar, sisa, aksi) tidak terpotong.
       style.css sebelumnya mengunci .table-responsive dengan overflow: hidden, jadi
       dibuka kembali dengan overflow-x: auto !important dan scrollbar di bawah */
    .table-responsive {
        overflow-x: auto !important;
        overflow-y: hidden !important;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: #94a3b8 #f1f5f9;
        padding-bottom: 6px;
    }

    .table-responsive::-webkit-scrollbar {
        height: 7px;
    }

    .table-responsive::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    /* Lebar minimum tabel agar kolom tidak terhimpit */
    #tab-transaksi .table {
        min-width: 820px;
    }

    #tab-pembayaran .table {
        min-width: 680px;
    }

    #tab-followup .table {
        min-width: 680px;
    }

    #customer360TabsContent .table th {
        white-space: nowrap;
    }

    #customer360TabsContent .table td:first-child,
    #customer360TabsContent .table td:nth-child(2),
    #customer360TabsContent .table td.text-end {
        white-space: nowrap;
    }

    /* Footer timeline: di layar HP (responsif) diberi garis pemisah dan tanggal di kiri bawah,
       di layar desktop garis dinonaktifkan karena tanggal tetap di kanan atas */
    .timeline-meta-footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        font-size: 11px;
    }

    @media (max-width: 767.98px) {
        .timeline-meta-footer {
            border-top: 1px solid #eaecf0;
            padding-top: 0.5rem;
            margin-top: 0.5rem;
        }
    }

    /* Halaman profil customer 360 di layar sempit. Semua aturan di bawah hanya
       berlaku di bawah 992px — titik di mana kolom-kolom halaman ini mulai
       menumpuk. */
    @media (max-width: 991.98px) {
        /* Kepala halaman: dulu judul dan tautan "Kembali" terimpit oleh dua
           tombol di kanannya sampai belah tiga baris. Sekarang susunannya
           bertingkat — tautan penuh satu baris, judul penuh satu baris,
           lalu tombolnya di bawah judul rata ke kanan. */
        .kepala-360 {
            flex-direction: column;
            /* Bootstrap menandai .align-items-center dengan !important, jadi
               tanpa !important anak-anaknya tetap dipusatkan dan tidak melebar */
            align-items: stretch !important;
            gap: 0.35rem;
        }

        .kepala-360 > div:first-child > a {
            display: block;
            white-space: nowrap;
        }

        .kepala-360 h4 {
            white-space: nowrap;
        }

        .tombol-360 {
            justify-content: flex-end;
        }

        /* Angka ringkasan: kartunya cuma kebagian setengah layar (126px isi)
           sehingga "Rp 13.502.500" pada ukuran h3/h4 bawaan turun ke baris
           kedua dan tingginya jadi tidak seragam */
        .ringkasan-nilai h3,
        .ringkasan-nilai h4 {
            font-size: 1.05rem;
        }

        .ringkasan-nilai .text-muted.small.fw-semibold {
            font-size: 0.75rem;
        }

        /* Judul tab: "Urutan Aktivitas & Interaksi Customer" mengambil lebar
           penuh dan badge multi-channel diletakkan di bawahnya. !important
           diperlukan karena .align-items-center / .justify-content-between di
           markup juga !important. */
        .timeline-kepala,
        .sub-kepala {
            flex-direction: column;
            align-items: flex-start !important;
            justify-content: flex-start !important;
            gap: 0.35rem;
        }

        /* Kepala tiap tab: judul penuh satu baris di atas, keterangannya di
           bawahnya */
        .sub-kepala {
            gap: 0.15rem;
        }

        /* Baris judul + tombol aksi: judul di atas, tombolnya turun ke baris
           sendiri dan memanjang seisi kartu (stretch membuat lebarnya penuh
           tanpa perlu !important pada .btn). */
        .kepala-aksi {
            flex-direction: column;
            align-items: stretch !important;
            justify-content: flex-start !important;
            gap: 0.4rem;
        }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <!-- Breadcrumb & Back -->
    <div class="d-flex align-items-center justify-content-between mb-3 kepala-360">
        <div>
            <a href="<?= site_url('admin/crm/pelanggan') ?>" class="text-decoration-none text-muted small">
                <i class="fal fa-arrow-left me-1"></i> Kembali ke Data Pelanggan
            </a>
            <h4 class="mb-0 fw-bold mt-1">
                <?php /* ikonnya hanya di layar lebar; di ponsel judulnya saja yang tersisa */ ?>
                <i class="fal fa-id-card text-primary me-2 d-none d-lg-inline"></i> Profil Customer 360
            </h4>
        </div>
        <div class="d-flex gap-2 tombol-360">
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= site_url('admin/tugas/catatan') ?>">
                <i class="fal fa-note-sticky me-1"></i> Catat Percakapan
            </a>
        </div>
    </div>

    <?php if (session()->getFlashdata('pesan')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fal fa-check-circle me-2"></i><?= session()->getFlashdata('pesan') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fal fa-exclamation-triangle me-2"></i><?= session()->getFlashdata('error') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Header Kartu Utama Customer -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <!-- Avatar & Identitas Pokok -->
                <div class="col-lg-5 col-md-6 mb-3 mb-lg-0 border-end">
                    <div class="d-flex align-items-center">
                        <div class="avatar-circle bg-primary bg-opacity-10 text-primary fw-bold fs-3 rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 70px; height: 70px;">
                            <?= strtoupper(substr($pelanggan['nama_pelanggan'], 0, 2)) ?>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h4 class="mb-0 fw-bold"><?= esc($pelanggan['nama_pelanggan']) ?></h4>
                                <?php
                                $statusBadge = match ($pelanggan['status_crm']) {
                                    'loyal'       => ['badge bg-warning text-dark', 'Customer Loyal', 'fa-crown'],
                                    'aktif'       => ['badge bg-success', 'Customer Aktif', 'fa-check-circle'],
                                    'baru'        => ['badge bg-info text-dark', 'Customer Baru', 'fa-sparkles'],
                                    'tidak_aktif' => ['badge bg-secondary', 'Tidak Aktif', 'fa-moon'],
                                    default       => ['badge bg-light text-dark border', 'Prospek / Lead', 'fa-user-clock'],
                                };
                                ?>
                                <span class="<?= $statusBadge[0] ?> px-2 py-1">
                                    <i class="fal <?= $statusBadge[2] ?> me-1"></i> <?= $statusBadge[1] ?>
                                </span>
                            </div>
                            <div class="text-muted mt-1 small">
                                <span class="d-block">
                                    <i class="fab fa-whatsapp text-success me-1"></i>
                                    <strong><?= esc($pelanggan['nomor_utama'] ?: 'Belum ada nomor') ?></strong>
                                </span>
                                <span>
                                    <i class="fal fa-map-marker-alt text-danger me-1"></i>
                                    <?= esc($pelanggan['kota'] ?: 'Kota belum diset') ?>
                                    <?= !empty($pelanggan['provinsi']) ? ', ' . esc($pelanggan['provinsi']) : '' ?>
                                </span>
                            </div>
                            <div class="text-muted small mt-1">
                                <i class="fal fa-calendar-alt me-1"></i> Terdaftar sejak: <?= !empty($pelanggan['terdaftar_sejak']) ? date('d M Y', (int) $pelanggan['terdaftar_sejak']) : '-' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status CRM, Tag, & Opt-In Control -->
                <div class="col-lg-7 col-md-6 ps-lg-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                        <!-- Tag Customer -->
                        <div>
                            <span class="text-muted small fw-semibold d-block mb-1">Tag Customer:</span>
                            <div class="d-flex flex-wrap align-items-center gap-1">
                                <?php if (!empty($pelanggan['tags'])): ?>
                                    <?php foreach ($pelanggan['tags'] as $t): ?>
                                        <span class="badge bg-<?= esc($t['warna']) ?> d-inline-flex align-items-center py-1 px-2">
                                            <?= esc($t['nama_tag']) ?>
                                            <form action="<?= site_url('admin/crm/pelanggan/hapus_tag/' . $pelanggan['id_pelanggan'] . '/' . $t['id_tag']) ?>" method="post" class="d-inline ms-1" onsubmit="return confirm('Hapus tag ini?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-link btn-xs text-white p-0 text-decoration-none" style="font-size: 10px; line-height: 1;">&times;</button>
                                            </form>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic">Belum ada tag.</span>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 11px;" data-bs-toggle="modal" data-bs-target="#modalTambahTag">
                                    <i class="fal fa-plus me-1"></i> Tambah Tag
                                </button>
                            </div>
                        </div>

                        <!-- Status CRM Edit & Opt-in -->
                        <div class="text-end">
                            <form action="<?= site_url('admin/crm/pelanggan/simpan_profil/' . $pelanggan['id_pelanggan']) ?>" method="post" class="d-flex align-items-center gap-2">
                                <?= csrf_field() ?>
                                <div class="form-check form-switch text-start me-2" title="Izin menerima broadcast promo">
                                    <input class="form-check-input" type="checkbox" name="opt_in_broadcast" value="1" id="optInSwitch" <?= ($pelanggan['opt_in_broadcast'] ?? 1) == 1 ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <label class="form-check-label small" for="optInSwitch">
                                        <?= ($pelanggan['opt_in_broadcast'] ?? 1) == 1 ? '<span class="text-success fw-semibold">Opt-in Promo</span>' : '<span class="text-danger fw-semibold">Opt-out Promo</span>' ?>
                                    </label>
                                </div>
                                <select name="status_crm" class="form-select form-select-sm" onchange="this.form.submit()" style="width: 140px;">
                                    <option value="prospek" <?= $pelanggan['status_crm'] === 'prospek' ? 'selected' : '' ?>>Prospek / Lead</option>
                                    <option value="baru" <?= $pelanggan['status_crm'] === 'baru' ? 'selected' : '' ?>>Customer Baru</option>
                                    <option value="aktif" <?= $pelanggan['status_crm'] === 'aktif' ? 'selected' : '' ?>>Customer Aktif</option>
                                    <option value="loyal" <?= $pelanggan['status_crm'] === 'loyal' ? 'selected' : '' ?>>Customer Loyal</option>
                                    <option value="tidak_aktif" <?= $pelanggan['status_crm'] === 'tidak_aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                                </select>
                            </form>
                        </div>
                    </div>

                    <!-- Alamat Lengkap & Catatan Cepat -->
                    <div class="bg-light p-2 rounded small mt-2">
                        <div class="row">
                            <div class="col-sm-6">
                                <strong>Alamat:</strong> <?= esc($pelanggan['alamat'] ?: 'Belum ada alamat lengkap') ?>
                            </div>
                            <div class="col-sm-6 text-sm-end text-muted">
                                <strong>Transaksi Terakhir:</strong> 
                                <?= !empty($nilai['transaksi_terakhir']) ? date('d M Y', strtotime($nilai['transaksi_terakhir'])) : 'Belum pernah order' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ringkasan Nilai Customer (Customer Value Metrics) -->
    <div class="row g-3 mb-4 ringkasan-nilai">
        <!-- Total Transaksi -->
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold">Total Transaksi</span>
                    <h3 class="fw-bold mb-0 text-primary mt-1"><?= number_format($nilai['total_transaksi']) ?></h3>
                    <div class="text-muted small" style="font-size: 11px;">orderan di POS</div>
                </div>
            </div>
        </div>
        <!-- Total Pengeluaran -->
        <div class="col-xl-3 col-md-4 col-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold">Total Pengeluaran</span>
                    <h3 class="fw-bold mb-0 text-success mt-1">Rp <?= number_format($nilai['total_pengeluaran'], 0, ',', '.') ?></h3>
                    <div class="text-muted small" style="font-size: 11px;">akumulasi nilai belanja</div>
                </div>
            </div>
        </div>
        <!-- Rata-rata Nilai Transaksi -->
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold">Rata-rata Transaksi</span>
                    <h4 class="fw-bold mb-0 text-dark mt-1">Rp <?= number_format($nilai['rata_rata_nilai'], 0, ',', '.') ?></h4>
                    <div class="text-muted small" style="font-size: 11px;">per transaksi</div>
                </div>
            </div>
        </div>
        <!-- Sisa Piutang / Tagihan -->
        <div class="col-xl-2 col-md-6 col-6">
            <div class="card border-0 shadow-sm h-100 <?= $nilai['sisa_piutang'] > 0 ? 'border-danger border-start border-4' : '' ?>">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold">Sisa Tagihan / Piutang</span>
                    <h4 class="fw-bold mb-0 <?= $nilai['sisa_piutang'] > 0 ? 'text-danger' : 'text-muted' ?> mt-1">
                        Rp <?= number_format($nilai['sisa_piutang'], 0, ',', '.') ?>
                    </h4>
                    <div class="small <?= $nilai['sisa_piutang'] > 0 ? 'text-danger fw-semibold' : 'text-success' ?>" style="font-size: 11px;">
                        <?= $nilai['sisa_piutang'] > 0 ? 'Belum lunas' : 'Semua transaksi lunas' ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- Breakdown Jenis Order -->
        <div class="col-xl-3 col-md-6 col-12">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold d-block mb-1">Rincian Jenis Order</span>
                    <div class="rincian-order mt-2">
                        <span class="badge bg-primary" title="Penjualan Ready Stock">
                            <i class="fal fa-shopping-bag"></i> Jual: <?= $nilai['count_penjualan'] ?>
                        </span>
                        <span class="badge bg-success" title="Custom / Pembuatan Pakaian">
                            <i class="fal fa-scissors"></i> Custom: <?= $nilai['count_custom'] ?>
                        </span>
                        <span class="badge bg-info" title="Sewa / Rental Pakaian">
                            <i class="fal fa-sync"></i> Sewa: <?= $nilai['count_sewa'] ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Navigation Tabs Customer 360 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <ul class="nav m-0 d-none d-lg-flex border-0" id="customer360Tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-semibold" id="tab-timeline-btn" data-bs-toggle="tab" data-bs-target="#tab-timeline" type="button">
                        <i class="fal fa-stream me-2 text-primary"></i> Timeline Aktivitas
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" id="tab-transaksi-btn" data-bs-toggle="tab" data-bs-target="#tab-transaksi" type="button">
                        <i class="fal fa-file-invoice-dollar me-2 text-success"></i> Riwayat Transaksi POS
                        <span class="badge bg-light text-dark border ms-1"><?= count($transaksiList) ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" id="tab-pembayaran-btn" data-bs-toggle="tab" data-bs-target="#tab-pembayaran" type="button">
                        <i class="fal fa-money-check-alt me-2 text-info"></i> Pembayaran
                        <span class="badge bg-light text-dark border ms-1"><?= count($pembayaranList) ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" id="tab-ukuran-btn" data-bs-toggle="tab" data-bs-target="#tab-ukuran" type="button">
                        <i class="fal fa-tape me-2 text-warning"></i> Ukuran &amp; Preferensi Pakaian
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" id="tab-catatan-btn" data-bs-toggle="tab" data-bs-target="#tab-catatan" type="button">
                        <i class="fal fa-comment-alt-lines me-2 text-secondary"></i> Catatan Staff
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" id="tab-followup-btn" data-bs-toggle="tab" data-bs-target="#tab-followup" type="button">
                        <i class="fal fa-calendar-check me-2 text-danger"></i> Follow-up
                        <span class="badge bg-light text-dark border ms-1"><?= count($followupList) ?></span>
                    </button>
                </li>
            </ul>
            <!-- Layar sempit: strip tab enam item jadi tiga baris dan memakan
               setengah layar, jadi diganti satu dropdown. Bawaannya sama
               dengan tab yang aktif, yaitu Timeline Aktivitas. -->
            <div class="py-1 d-lg-none">
                <select class="form-select" id="customer360Pilih" aria-label="Pilih bagian data customer">
                    <option value="tab-timeline-btn" selected>Timeline Aktivitas</option>
                    <option value="tab-transaksi-btn">Riwayat Transaksi POS (<?= count($transaksiList) ?>)</option>
                    <option value="tab-pembayaran-btn">Pembayaran (<?= count($pembayaranList) ?>)</option>
                    <option value="tab-ukuran-btn">Ukuran &amp; Preferensi Pakaian</option>
                    <option value="tab-catatan-btn">Catatan Staff</option>
                    <option value="tab-followup-btn">Follow-up (<?= count($followupList) ?>)</option>
                </select>
            </div>
        </div>
        <div class="card-body p-4 body-360">
            <div class="tab-content" id="customer360Content">
                
                <!-- ================= TAB 1: TIMELINE AKTIVITAS ================= -->
                <div class="tab-pane fade show active" id="tab-timeline" role="tabpanel">
                    <div class="row">
                        <!-- Timeline feed -->
                        <div class="col-lg-8 border-end pe-lg-4 order-2 order-lg-1 mt-4 mt-lg-0">
                            <div class="d-flex align-items-center justify-content-between mb-4 timeline-kepala">
                                <h6 class="fw-bold mb-0">Urutan Aktivitas & Interaksi Customer</h6>
                                <span class="badge bg-light text-muted border">Multi-channel (WA, Order, Fitting, Kasir)</span>
                            </div>

                            <?php if (empty($timeline)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fal fa-history fa-3x mb-3 text-secondary opacity-50"></i>
                                    <p class="mb-0">Belum ada aktivitas yang tercatat untuk customer ini.</p>
                                </div>
                            <?php else: ?>
                                <div class="gulir-timeline">
                                <div class="timeline-container position-relative ps-4" style="border-left: 2px solid #e9ecef;">
                                    <?php foreach ($timeline as $item): ?>
                                        <div class="timeline-item mb-4 position-relative">
                                            <!-- Bullet icon -->
                                            <div class="position-absolute rounded-circle d-flex align-items-center justify-content-center bg-<?= esc($item['warna']) ?> text-white shadow-sm timeline-titik"
                                                 style="left: -33px; top: 0; width: 30px; height: 30px; font-size: 13px;">
                                                <i class="fal <?= esc($item['icon']) ?>"></i>
                                            </div>
                                            <div class="card border bg-light bg-opacity-50 shadow-none">
                                                <div class="card-body p-3">
                                                    <!-- Baris Judul & Tanggal Kanan Atas (Desktop) -->
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <h6 class="fw-bold mb-0 text-dark"><?= esc($item['judul']) ?></h6>
                                                        <span class="text-muted small d-none d-md-inline flex-shrink-0 ms-2 text-nowrap" style="font-size: 11px;">
                                                            <?= date('H:i', (int) $item['waktu']) !== '00:00' 
                                                                ? date('d M Y, H:i', (int) $item['waktu']) 
                                                                : date('d M Y', (int) $item['waktu']) ?>
                                                        </span>
                                                    </div>

                                                    <!-- Deskripsi Catatan -->
                                                    <?php if (!empty($item['deskripsi'])): ?>
                                                        <p class="mb-1 text-secondary small" style="white-space: pre-wrap;"><?= esc($item['deskripsi']) ?></p>
                                                    <?php endif; ?>

                                                    <!-- Footer: Di HP tanggal di kiri bawah, di Desktop hanya 'Dicatat oleh' -->
                                                    <div class="timeline-meta-footer text-muted small">
                                                        <span class="d-md-none">
                                                            <i class="fal fa-clock me-1 text-muted"></i>
                                                            <?= date('H:i', (int) $item['waktu']) !== '00:00' 
                                                                ? date('d M Y, H:i', (int) $item['waktu']) 
                                                                : date('d M Y', (int) $item['waktu']) ?>
                                                        </span>
                                                        <span>
                                                            <i class="fal fa-user-edit me-1 text-muted"></i> Dicatat oleh: <strong><?= esc($item['actor']) ?></strong>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Panel Catat Interaksi: satu-satunya form, tampil sebagai
                             kartu di desktop dan sebagai pop-up di layar sempit. Margin
                             atasnya disamakan dengan kolom timeline (mt-4 mt-lg-0) supaya
                             puncak kartu sejajar dengan kepala "Urutan Aktivitas", bukan
                             naik lebih tinggi darinya. -->
                        <div class="col-lg-4 ps-lg-4 order-1 order-lg-2 mt-4 mt-lg-0">
                            <button type="button" class="btn btn-primary fw-semibold w-100 d-lg-none" data-bs-toggle="modal" data-bs-target="#modalCatatInteraksi">
                                <i class="fal fa-plus-circle me-1"></i> Catat Interaksi Baru
                            </button>
                            <div class="modal fade" id="modalCatatInteraksi" tabindex="-1" aria-labelledby="judulCatatInteraksi" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h6 class="modal-title fw-bold mb-0" id="judulCatatInteraksi">
                                                <i class="fal fa-plus-circle text-primary me-1"></i> Catat Interaksi Baru
                                            </h6>
                                            <button type="button" class="btn-close d-lg-none" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form action="<?= site_url('admin/crm/pelanggan/tambah_aktivitas/' . $pelanggan['id_pelanggan']) ?>" method="post">
                                                <?= csrf_field() ?>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Jenis Interaksi / Aktivitas</label>
                                                    <select name="tipe" class="form-select form-select-sm" required>
                                                        <option value="catatan">Catatan Staff</option>
                                                        <option value="fitting">Fitting Pakaian</option>
                                                        <option value="telepon">Panggilan Telepon</option>
                                                        <option value="datang">Datang ke Toko</option>
                                                        <option value="pengambilan">Pengambilan Pakaian</option>
                                                        <option value="pengembalian">Pengembalian Pakaian Sewa</option>
                                                        <option value="wa">Interaksi WhatsApp</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Judul Ringkas</label>
                                                    <input type="text" name="judul" class="form-control form-control-sm" placeholder="Contoh: Customer fitting jas kedua" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Keterangan / Hasil Interaksi</label>
                                                    <textarea name="deskripsi" class="form-control form-control-sm" rows="4" placeholder="Tuliskan catatan hasil interaksi dengan customer..."></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Hubungkan dengan Invoice (Opsional)</label>
                                                    <select name="invoice_id" class="form-select form-select-sm">
                                                        <option value="">-- Tanpa Invoice Terkait --</option>
                                                        <?php foreach ($transaksiList as $inv): ?>
                                                            <option value="<?= $inv['id_invoice'] ?>">#<?= esc($inv['seri']) ?> (<?= esc($inv['tipe_label']) ?>)</option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                                                    <i class="fal fa-save me-1"></i> Simpan ke Timeline
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================= TAB 2: RIWAYAT TRANSAKSI POS ================= -->
                <div class="tab-pane fade" id="tab-transaksi" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3 sub-kepala">
                        <h6 class="fw-bold mb-0">Seluruh Transaksi POS Customer</h6>
                        <span class="text-muted small">Menampilkan semua pembelian pakaian, pesanan custom, dan sewa</span>
                    </div>

                    <?php if (empty($transaksiList)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-receipt fa-3x mb-3 text-secondary opacity-50"></i>
                            <p class="mb-0">Belum ada transaksi POS yang terhubung dengan customer ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border">
                                <thead class="table-light">
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Tanggal</th>
                                        <th>Jenis Pesanan</th>
                                        <th>Item Pakaian / Layanan</th>
                                        <th class="text-end">Total Tagihan</th>
                                        <th class="text-end">Dibayar</th>
                                        <th class="text-end">Sisa</th>
                                        <th>Status Pembayaran</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transaksiList as $t): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-bold text-dark">#<?= esc($t['seri']) ?></span>
                                                <div class="small text-muted"><?= esc($t['nama_juragan']) ?></div>
                                            </td>
                                            <td class="small">
                                                <?= date('d/m/Y', strtotime($t['tanggal_pesan'])) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= esc($t['tipe_badge']) ?> px-2 py-1">
                                                    <?= esc($t['tipe_label']) ?>
                                                </span>
                                                <?php if (!empty($t['rincian_data']['deadline'])): ?>
                                                    <div class="text-muted" style="font-size: 11px;">
                                                        Deadline: <?= date('d/m/y', strtotime($t['rincian_data']['deadline'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($t['rincian_data']['ambil'])): ?>
                                                    <div class="text-muted" style="font-size: 11px;">
                                                        Sewa: <?= date('d/m', strtotime($t['rincian_data']['ambil'])) ?> - <?= date('d/m/y', strtotime($t['rincian_data']['kembali'] ?? '')) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($t['items'])): ?>
                                                    <ul class="list-unstyled mb-0 small">
                                                        <?php foreach ($t['items'] as $item): ?>
                                                            <li>
                                                                <i class="fal fa-tshirt text-muted me-1"></i>
                                                                <strong><?= esc($item['kode']) ?></strong> 
                                                                <?= !empty($item['ukuran']) ? '(' . esc($item['ukuran']) . ')' : '' ?> 
                                                                &times; <?= (int) $item['qty'] ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <span class="text-muted small fst-italic">Tanpa rincian item</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end fw-semibold">
                                                Rp <?= number_format($t['total_tagihan'], 0, ',', '.') ?>
                                            </td>
                                            <td class="text-end text-success">
                                                Rp <?= number_format($t['telah_dibayar'], 0, ',', '.') ?>
                                            </td>
                                            <td class="text-end <?= $t['sisa_tagihan'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                                                Rp <?= number_format($t['sisa_tagihan'], 0, ',', '.') ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusBayar = match ($t['status_pembayaran']) {
                                                    '6' => ['badge bg-success', 'Lunas'],
                                                    '4' => ['badge bg-warning text-dark', 'Kredit / Cicilan'],
                                                    '5' => ['badge bg-info', 'Kelebihan'],
                                                    '2', '3' => ['badge bg-warning text-dark', 'Konfirmasi'],
                                                    default => ['badge bg-danger', 'Belum Bayar'],
                                                };
                                                ?>
                                                <span class="<?= $statusBayar[0] ?>"><?= $statusBayar[1] ?></span>
                                            </td>
                                            <td class="text-center">
                                                <a href="<?= esc(tautan_invoice_notif($t['seri'], '')) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Buka Detail Invoice POS">
                                                    <i class="fal fa-external-link-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ================= TAB 3: RIWAYAT PEMBAYARAN ================= -->
                <div class="tab-pane fade" id="tab-pembayaran" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0">Riwayat Mutasi Pembayaran Masuk</h6>
                    </div>

                    <?php if (empty($pembayaranList)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-money-bill-wave fa-3x mb-3 text-secondary opacity-50"></i>
                            <p class="mb-0">Belum ada riwayat pembayaran yang tercatat.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Tanggal Bayar</th>
                                        <th>Terkait Invoice</th>
                                        <th>Metode / Rekening Bank</th>
                                        <th class="text-end">Nominal Pembayaran</th>
                                        <th>Status Verifikasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pembayaranList as $p): ?>
                                        <tr>
                                            <td class="text-muted small">#PAY-<?= $p['id_pembayaran'] ?></td>
                                            <td><?= date('d/m/Y H:i', (int) $p['tanggal_pembayaran']) ?></td>
                                            <td>
                                                <span class="fw-bold">#<?= esc($p['invoice_seri']) ?></span>
                                            </td>
                                            <td>
                                                <i class="fal fa-university me-1 text-muted"></i>
                                                <?= esc($p['nama_bank']) ?>
                                                <?= !empty($p['atas_nama']) ? 'a.n ' . esc($p['atas_nama']) : '' ?>
                                            </td>
                                            <td class="text-end fw-bold text-success">
                                                Rp <?= number_format($p['total_pembayaran'], 0, ',', '.') ?>
                                            </td>
                                            <td>
                                                <?php if ($p['status'] === '3'): ?>
                                                    <span class="badge bg-success"><i class="fal fa-check me-1"></i> Terverifikasi (Dana Ada)</span>
                                                <?php elseif ($p['status'] === '2'): ?>
                                                    <span class="badge bg-danger">Dana Tidak Ada</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Belum Dicek</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ================= TAB 4: DATA UKURAN & PREFERENSI PAKAIAN ================= -->
                <div class="tab-pane fade" id="tab-ukuran" role="tabpanel">
                    <form action="<?= site_url('admin/crm/pelanggan/simpan_ukuran/' . $pelanggan['id_pelanggan']) ?>" method="post">
                        <?= csrf_field() ?>
                        <div class="row">
                            <!-- Preferensi & Ukuran Standar -->
                            <div class="col-lg-4 border-end pe-lg-4">
                                <h6 class="fw-bold mb-3 text-primary">
                                    <i class="fal fa-sliders-h me-1"></i> Ukuran Standar & Preferensi
                                </h6>
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Ukuran Baju / Kemeja Standar</label>
                                    <input type="text" name="ukuran_baju_standar" class="form-control" value="<?= esc($pelanggan['ukuran_baju_standar'] ?? '') ?>" placeholder="Misal: XL / L / Custom">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Ukuran Celana Standar</label>
                                    <input type="text" name="ukuran_celana_standar" class="form-control" value="<?= esc($pelanggan['ukuran_celana_standar'] ?? '') ?>" placeholder="Misal: 33 / 34">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Ukuran Jas Standar</label>
                                    <input type="text" name="ukuran_jas_standar" class="form-control" value="<?= esc($pelanggan['ukuran_jas_standar'] ?? '') ?>" placeholder="Misal: 50 / L Slim Fit">
                                </div>

                                <hr>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Warna Favorit / Sering Dipilih</label>
                                    <input type="text" name="preferensi_warna" class="form-control" value="<?= esc($pelanggan['preferensi_warna'] ?? '') ?>" placeholder="Misal: Navy, Hitam, Earth Tone">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Bahan Kain yang Disukai</label>
                                    <input type="text" name="preferensi_bahan" class="form-control" value="<?= esc($pelanggan['preferensi_bahan'] ?? '') ?>" placeholder="Misal: Semi Wool, Katun Oxford, Toyobo">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Model Pakaian Favorit</label>
                                    <input type="text" name="preferensi_model" class="form-control" value="<?= esc($pelanggan['preferensi_model'] ?? '') ?>" placeholder="Misal: Slim Fit, Double Breasted, Kerah Shanghai">
                                </div>
                            </div>

                            <!-- Detail Ukuran Tubuh Kustom (Custom Tailor Measurements) -->
                            <div class="col-lg-8 ps-lg-4 mt-4 mt-lg-0">
                                <h6 class="fw-bold mb-3 text-primary">
                                    <i class="fal fa-ruler-combined me-1"></i> Data Ukuran Kustom Tubuh (CM)
                                </h6>
                                <p class="text-muted small">
                                    Data ukuran ini otomatis digunakan saat customer memesan pakaian custom baru agar staff tidak perlu mengulang menanyakan ukuran dari awal.
                                </p>

                                <?php $u = $pelanggan['detail_ukuran_array'] ?? []; ?>

                                <div class="row g-3">
                                    <!-- Bagian Atasan / Jas / Kemeja -->
                                    <div class="col-12">
                                        <div class="bg-light p-2 rounded fw-semibold small text-dark mb-2">
                                            <i class="fal fa-tshirt me-1 text-primary"></i> Ukuran Atasan (Baju / Kemeja / Jas)
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Lingkar Dada (LD)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="lingkar_dada" class="form-control" value="<?= esc($u['lingkar_dada'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Panjang Baju (PB)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="panjang_baju" class="form-control" value="<?= esc($u['panjang_baju'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Lebar Bahu</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="lebar_bahu" class="form-control" value="<?= esc($u['lebar_bahu'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Panjang Lengan</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="panjang_lengan" class="form-control" value="<?= esc($u['panjang_lengan'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Lingkar Leher</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="lingkar_leher" class="form-control" value="<?= esc($u['lingkar_leher'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Panjang Jas</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="panjang_jas" class="form-control" value="<?= esc($u['panjang_jas'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>

                                    <!-- Bagian Celana -->
                                    <div class="col-12 mt-3">
                                        <div class="bg-light p-2 rounded fw-semibold small text-dark mb-2">
                                            <i class="fal fa-columns me-1 text-primary"></i> Ukuran Bawahan (Celana)
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Lingkar Pinggang</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="lingkar_pinggang" class="form-control" value="<?= esc($u['lingkar_pinggang'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Panjang Celana</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="panjang_celana" class="form-control" value="<?= esc($u['panjang_celana'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Lingkar Paha</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="lingkar_paha" class="form-control" value="<?= esc($u['lingkar_paha'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Lingkar Pinggul</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="lingkar_pinggul" class="form-control" value="<?= esc($u['lingkar_pinggul'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Pesak (Selangkangan)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="pesak" class="form-control" value="<?= esc($u['pesak'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Lingkar Kaki Bawah</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="lingkar_kaki" class="form-control" value="<?= esc($u['lingkar_kaki'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>

                                    <div class="col-12 mt-3">
                                        <label class="form-label small fw-semibold">Catatan Khusus Bentuk Tubuh / Pola Jahitan</label>
                                        <textarea name="catatan_ukuran" class="form-control form-control-sm" rows="3" placeholder="Misal: Bahu kanan agak turun 1cm, perut buncit, minta celana agak longgar di paha."><?= esc($u['catatan_ukuran'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <div class="mt-4 pt-3 border-top text-end">
                                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                                        <i class="fal fa-save me-1"></i> Simpan Data Ukuran & Preferensi
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ================= TAB 5: CATATAN STAFF ================= -->
                <div class="tab-pane fade" id="tab-catatan" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-6 border-end pe-lg-4">
                            <h6 class="fw-bold mb-3">Catatan Tetap Profil Customer</h6>
                            <form action="<?= site_url('admin/crm/pelanggan/simpan_profil/' . $pelanggan['id_pelanggan']) ?>" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status_crm" value="<?= esc($pelanggan['status_crm']) ?>">
                                <input type="hidden" name="opt_in_broadcast" value="<?= esc($pelanggan['opt_in_broadcast'] ?? 1) ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label small text-muted">
                                        Catatan ini selalu terlihat saat staf membuka profil customer (misal: kebiasaan, preferensi waktu dihubungi, persiapan acara nikah, dll).
                                    </label>
                                    <textarea name="catatan" class="form-control" rows="8" placeholder="Tuliskan catatan profil penting mengenai customer ini..."><?= esc($pelanggan['catatan_crm'] ?? '') ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                                    <i class="fal fa-save me-1"></i> Simpan Catatan Profil
                                </button>
                            </form>
                        </div>
                        <div class="col-lg-6 ps-lg-4 mt-4 mt-lg-0">
                            <h6 class="fw-bold mb-3">Tambah Catatan Interaksi Cepat (Masuk Timeline)</h6>
                            <form action="<?= site_url('admin/crm/pelanggan/tambah_aktivitas/' . $pelanggan['id_pelanggan']) ?>" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="tipe" value="catatan">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Judul Catatan</label>
                                    <input type="text" name="judul" class="form-control form-control-sm" placeholder="Misal: Customer menanyakan paket wedding 2027" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Isi Catatan</label>
                                    <textarea name="deskripsi" class="form-control form-control-sm" rows="5" placeholder="Detail catatan staff..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-outline-primary btn-sm fw-semibold">
                                    <i class="fal fa-plus me-1"></i> Tambahkan ke Timeline
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ================= TAB 6: FOLLOW-UP & REMINDER ================= -->
                <div class="tab-pane fade" id="tab-followup" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3 kepala-aksi">
                        <h6 class="fw-bold mb-0">Daftar Jadwal Tindakan & Follow-up</h6>
                        <button class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambahFollowup">
                            <i class="fal fa-plus me-1"></i> Buat Follow-up Baru
                        </button>
                    </div>

                    <?php if (empty($followupList)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-calendar-times fa-3x mb-3 text-secondary opacity-50"></i>
                            <p class="mb-0">Tidak ada jadwal follow-up untuk customer ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border">
                                <thead class="table-light">
                                    <tr>
                                        <th>Jatuh Tempo</th>
                                        <th>Tipe / Kategori</th>
                                        <th>Judul & Catatan</th>
                                        <th>Status</th>
                                        <th>Staff</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($followupList as $f): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-bold <?= $f['tanggal_jatuh_tempo'] < date('Y-m-d') && $f['status'] === 'menunggu' ? 'text-danger' : 'text-dark' ?>">
                                                    <?= date('d/m/Y', strtotime($f['tanggal_jatuh_tempo'])) ?>
                                                </span>
                                                <?php if ($f['tanggal_jatuh_tempo'] < date('Y-m-d') && $f['status'] === 'menunggu'): ?>
                                                    <span class="badge bg-danger ms-1">Terlambat</span>
                                                <?php elseif ($f['tanggal_jatuh_tempo'] === date('Y-m-d') && $f['status'] === 'menunggu'): ?>
                                                    <span class="badge bg-warning text-dark ms-1">Hari Ini</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    <?= ucfirst(str_replace('_', ' ', $f['kategori'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?= esc($f['judul']) ?></strong>
                                                <?php if (!empty($f['catatan'])): ?>
                                                    <div class="small text-muted" style="white-space: pre-wrap;"><?= esc($f['catatan']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($f['status'] === 'selesai'): ?>
                                                    <span class="badge bg-success"><i class="fal fa-check me-1"></i> Selesai</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i class="fal fa-clock me-1"></i> Menunggu</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted">
                                                <?= esc($f['nama_staff'] ?? 'Staff') ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($f['status'] === 'menunggu'): ?>
                                                    <form action="<?= site_url('admin/crm/followup/selesai/' . $f['id_followup']) ?>" method="post" class="d-inline" onsubmit="return confirm('Tandai follow-up ini telah selesai?');">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn btn-sm btn-success py-1 px-2">
                                                            <i class="fal fa-check me-1"></i> Selesai
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small"><i class="fal fa-check-double text-success"></i></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL TAMBAH TAG ================= -->
<div class="modal fade" id="modalTambahTag" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= site_url('admin/crm/pelanggan/tambah_tag/' . $pelanggan['id_pelanggan']) ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Tambah Tag Customer</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pilih dari Tag yang Ada</label>
                        <select name="tag_id" class="form-select form-select-sm">
                            <option value="">-- Pilih Tag --</option>
                            <?php foreach ($allTags as $t): ?>
                                <option value="<?= $t['id_tag'] ?>"><?= esc($t['nama_tag']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="text-center text-muted small my-2">-- atau buat tag baru --</div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Nama Tag Baru</label>
                        <input type="text" name="nama_tag_baru" class="form-control form-control-sm" placeholder="Misal: Wedding 2026">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Warna Badge</label>
                        <select name="warna" class="form-select form-select-sm">
                            <option value="primary">Biru (Primary)</option>
                            <option value="success">Hijau (Success)</option>
                            <option value="info">Cyan (Info)</option>
                            <option value="warning">Kuning (Warning)</option>
                            <option value="danger">Merah (Danger)</option>
                            <option value="dark">Hitam (Dark)</option>
                            <option value="secondary">Abu-abu (Secondary)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Tambahkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL BUAT FOLLOW-UP ================= -->
<div class="modal fade" id="modalTambahFollowup" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= site_url('admin/crm/followup/simpan') ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="pelanggan_id" value="<?= $pelanggan['id_pelanggan'] ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Buat Jadwal Follow-up / Reminder</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kategori Tindakan</label>
                        <select name="kategori" class="form-select form-select-sm" required>
                            <option value="tanya_harga">Follow-up Prospek (Tanya Harga)</option>
                            <option value="fitting">Pengingat Jadwal Fitting</option>
                            <option value="pengambilan">Pengingat Pengambilan Pakaian</option>
                            <option value="pengembalian_sewa">Pengingat Pengembalian Sewa</option>
                            <option value="konfirmasi_ukuran">Konfirmasi Ukuran Pakaian</option>
                            <option value="penawaran">Penawaran Promo / Produk Baru</option>
                            <option value="tagihan">Follow-up Pembayaran</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Judul Tugas / Tindakan</label>
                        <input type="text" name="judul" class="form-control form-control-sm" placeholder="Misal: Hubungi customer untuk fitting jas tahap 2" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tanggal Jatuh Tempo Tindakan</label>
                        <input type="date" name="tanggal_jatuh_tempo" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Catatan / Detail yang Perlu Ditanyakan</label>
                        <textarea name="catatan" class="form-control form-control-sm" rows="3" placeholder="Informasi tambahan untuk staff yang akan mengeksekusi..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Jadwalkan Follow-up</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dropdown pengganti strip tab di layar sempit: nilainya id tombol tab,
    // jadi cukup panggil API tab Bootstrap-nya.
    const pilih360 = document.getElementById('customer360Pilih');
    if (pilih360 && window.bootstrap && bootstrap.Tab) {
        pilih360.addEventListener('change', function() {
            const tombol = document.getElementById(this.value);
            if (tombol) new bootstrap.Tab(tombol).show();
        });
        document.querySelectorAll('#customer360Tabs .nav-link').forEach(function(tombol) {
            tombol.addEventListener('shown.bs.tab', function() {
                pilih360.value = tombol.id;
            });
        });
    }
});
</script>
<?= $this->endSection() ?>
