<?= $this->include('template/feedback') ?>
<?php
$di_halaman = trim(uri_string(), '/');
$levelSesi  = session()->get('level') ?? ($_SESSION['level'] ?? '');
$namaSesi   = session()->get('name') ?? ($_SESSION['name'] ?? 'Admin');
$namaInisial = strtoupper(mb_substr($namaSesi, 0, 1));

// Deteksi menu induk aktif
$isDasborAktif   = url_is('admin/dasbor*') || $di_halaman === 'admin';
$isReminderAktif = url_is('admin/reminder*');
// dua reminder itu pindah etalase: halamannya masih di bawah /admin/tugas, tapi
// menunya duduk di CRM — jadi hanya satu dari dua induk yang boleh menyala
$diReminderKerja = url_is('admin/tugas/whatsapp*') || url_is('admin/tugas/bintang*');
// Cek Toko tinggal di submenu ini, jadi induknya ikut terbuka saat orang sedang
// di halaman checklist — bukan hanya saat di /admin/tugas
$isTugasAktif    = (url_is('admin/tugas*') && ! $diReminderKerja) || url_is('admin/checklist*');
$isCklAktif      = url_is('admin/checklist*');
$isSopAktif     = url_is('admin/sop*');
$isTrxAktif      = url_is('admin/invoices*') || url_is('admin/cod*');
$isProdukAktif   = url_is('admin/produk*');
$isPoinAktif     = url_is('admin/poin*');
$isCrmAktif      = url_is('admin/crm*') || $diReminderKerja;
$isKpiAktif      = url_is('admin/kpi*');
$isLaporanAktif  = url_is('admin/laporan*');
$isSettingsAktif = url_is('admin/settings*');
$isMasukanAktif  = url_is('admin/masukan*');

// badge merah Kotak Masukan: berapa permintaan prioritas yang masih menunggu.
// Hanya Superadmin yang membaca kotak ini, jadi hitungannya tidak perlu dibuat
// untuk level lain.
$masukanPrioritas = 0;
if ($levelSesi === 'superadmin') {
    $masukanPrioritas = (new \App\Models\FeedbackModel())->hitungPrioritas();
}

// badge Reminder Penjahit: orderan pembuatan/jual yang deadline-nya sudah dekat.
// Helper fungsi tidak autoload, jadi dimuat dulu; perhitungannya memakai sesi
// toko yang sedang login, jadi angkanya cocok dengan daftar yang akan dibuka.
helper('fungsi');
$reminderCount = pengingat_penjahit_jumlah();
$tugasModel    = new \App\Models\TugasModel();
$lainTerbuka   = $tugasModel->jumlah(['jenis_not' => \App\Models\TugasModel::PUNYA_SUBMENU, 'terbuka' => true, 'juragan_ids' => juragan_dipakai_sesi()]);
$waTerbuka    = (new \App\Models\TugasWhatsappModel())->hitungTerbuka(juragan_dipakai_sesi());
$b5Model     = new \App\Models\TugasModel();
$b5Terbuka    = $b5Model->hitungPemicu('ulasan', juragan_dipakai_sesi());
$emailTerbuka = (new \App\Models\TugasModel())->jumlah(["jenis" => "pemeriksaan_email", "terbuka" => true, "juragan_ids" => juragan_dipakai_sesi()]);
$cklTerbuka    = (new \App\Models\ChecklistModel())->hitungTerbuka(juragan_dipakai_sesi(), date('Y-m-d'));
$tugasTerbuka = $lainTerbuka + $cklTerbuka + $emailTerbuka;

// Submenu Transaksi
// 'invoices' tetap satu halaman dengan 'cod': yang pertama dipakai untuk orderan
// yang jadi nota, yang kedua untuk kunjungan customer yang tidak jadi orderan
$transaksiSubmenu = [
    ['admin/invoices/lihat/semua/semua', 'Tracking Transaksi', 'fa-receipt', $di_halaman === 'admin/invoices' || str_starts_with($di_halaman, 'admin/invoices/')],
    ['admin/cod', 'Pencatatan COD', 'fa-truck-fast', str_starts_with($di_halaman, 'admin/cod')],
];

// Menu Produk. Katalog dan Laporan Poin dulu berdiri sebagai dua menu utama;
// keduanya sekarang menyatu di satu induk "Produk". Bentuk submenu hanya untuk
// Superadmin, karena Laporan Poin (rute admin/poin) memang hak khusus Superadmin
// — Admin biasa tetap memakai satu tautan langsung ke katalognya.
$produkSubmenu = [
    ['admin/produk', 'Katalog Produk', 'fa-box-alt', $isProdukAktif],
];
if ($levelSesi === 'superadmin') {
    $produkSubmenu[] = ['admin/poin', 'Laporan Poin', 'fa-coins', $isPoinAktif];
}
$isProdukGrupAktif = $isProdukAktif || $isPoinAktif;

// Submenu KPI: Ringkasan dan Ranking untuk admin & superadmin. Menentukan target
// tetap hak Superadmin, jadi menunya tidak dirender untuk yang lain.
$kpiSubmenu = [
    ['admin/kpi', 'Ringkasan', 'fa-chart-pie', $di_halaman === 'admin/kpi'],
    ['admin/kpi/ranking', 'Ranking', 'fa-trophy', str_starts_with($di_halaman, 'admin/kpi/ranking')],
];

if ($levelSesi === 'superadmin') {
    $kpiSubmenu[] = ['admin/kpi/target', 'Target', 'fa-bullseye', str_starts_with($di_halaman, 'admin/kpi/target')];
}

// Submenu CRM. Live Chat WA, Broadcast, Riwayat Pesan, Template Pesan, dan
// Pengaturan WA sudah dihapus dari aplikasi; daftar Tagihan sendiri dilebur ke
// halaman Follow-up yang sekarang berganti nama jadi Tagihan, jadi isinya tinggal enam.
// Dua reminder kerja (WhatsApp & Bintang 5) ikut ke sini: halamannya masih
// /admin/tugas/..., tapi pekerjaannya memang lanjutan dari percakapan pelanggan.
// Elemen ke-5 opsional = badge hidup (url angka + satuan + jumlah awal).
$crmSubmenu = [
    ['admin/crm', 'Ringkasan', 'fa-chart-pie', $di_halaman === 'admin/crm'],
    ['admin/crm/pelanggan', 'Data Pelanggan', 'fa-users', str_starts_with($di_halaman, 'admin/crm/pelanggan')],
    ['admin/crm/followup', 'Tagihan', 'fa-tasks', str_starts_with($di_halaman, 'admin/crm/followup')],
    ['admin/crm/duplikat', 'Akun Duplikat', 'fa-clone', str_starts_with($di_halaman, 'admin/crm/duplikat')],
    [
        'admin/tugas/whatsapp', 'Reminder WhatsApp', 'fa-comment-dots',
        url_is('admin/tugas/whatsapp*'),
        ['url' => 'admin/tugas/whatsapp/jumlah', 'unit' => 'reminder WhatsApp yang belum selesai', 'angka' => $waTerbuka],
    ],
    [
        'admin/tugas/bintang', 'Reminder Bintang 5', 'fa-star',
        url_is('admin/tugas/bintang*'),
        ['url' => 'admin/tugas/bintang/jumlah', 'unit' => 'reminder Bintang 5 yang belum dikerjakan', 'angka' => $b5Terbuka],
    ],
];

// angka yang menempel di induk CRM: dua reminder yang baru pindah ke menu ini
$crmTerbuka = $waTerbuka + $b5Terbuka;

// Submenu Laporan
// 'produk' adalah awalan dari 'produksi', jadi pencocokan harus per segmen penuh
// supaya hanya satu entri yang ikut menyala.
$laporan_aktif = static function (string $segmen) use ($di_halaman): bool {
    return $di_halaman === 'admin/laporan/' . $segmen
        || str_starts_with($di_halaman, 'admin/laporan/' . $segmen . '/');
};
$laporanSubmenu = [
    ['admin/laporan/pesanan', 'Laporan Pesanan', 'fa-file-alt', $laporan_aktif('pesanan') || $di_halaman === 'admin/laporan'],
    ['admin/laporan/pendapatan', 'Laporan Pendapatan', 'fa-wallet', $laporan_aktif('pendapatan')],
    ['admin/laporan/pembayaran', 'Laporan Pembayaran', 'fa-money-check-edit', $laporan_aktif('pembayaran')],
    ['admin/laporan/piutang', 'Laporan Piutang', 'fa-inbox-in', $laporan_aktif('piutang')],
    ['admin/laporan/produksi', 'Laporan Produksi', 'fa-tasks', $laporan_aktif('produksi')],
    ['admin/laporan/produk', 'Laporan Produk', 'fa-box-alt', $laporan_aktif('produk')],
    ['admin/laporan/pelanggan', 'Laporan Customer', 'fa-user', $laporan_aktif('pelanggan')],
];

// Submenu Sistem. Kebersihan kotak notifikasi boleh dipakai Admin; rekening bank,
// juragan, pengguna, dan kunci API tetap khusus Superadmin.
$sistemSubmenu = [];

if (in_array($levelSesi, ['admin', 'superadmin'], true)) {
    $sistemSubmenu[] = [
        'admin/settings/notifikasi',
        'Pengaturan Notifikasi',
        'fa-bell-slash',
        str_starts_with($di_halaman, 'admin/settings/notifikasi'),
    ];
}

if ($levelSesi === 'superadmin') {
    $sistemSubmenu = array_merge($sistemSubmenu, [
        ['admin/settings/bank', 'Atur Bank', 'fa-university', str_starts_with($di_halaman, 'admin/settings/bank')],
        ['admin/settings/juragan', 'Atur Juragan', 'fa-store', str_starts_with($di_halaman, 'admin/settings/juragan')],
        ['admin/settings/pengguna', 'Atur Pengguna', 'fa-users-cog', str_starts_with($di_halaman, 'admin/settings/pengguna')],
        ['admin/settings/kunci-api', 'Kunci API', 'fa-key', str_starts_with($di_halaman, 'admin/settings/kunci-api')],
    ]);
}
?>

<!-- Topbar khusus layar kecil; di desktop lonceng notifikasi melayang sendiri
     (lihat .notif-melayang di template/notif_bar.php), bukan bar penuh. -->
<header class="mobile-topbar d-flex d-lg-none align-items-center justify-content-between px-2 sticky-top">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-link mobile-topbar-btn p-1 text-decoration-none" id="mobileSidebarToggle" type="button" aria-label="Buka Menu">
            <i class="fal fa-bars fs-5"></i>
        </button>
        <a href="<?= site_url('') ?>" class="d-flex align-items-center gap-2 mobile-topbar-brand text-decoration-none">
            <div class="sidebar-brand-icon" style="width:28px;height:28px;"><?= $this->include('template/merek') ?>
            </div>
            <span class="fw-bold small sidebar-brand-text-label">Djuragan POS</span>
        </a>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a class="mobile-topbar-btn text-decoration-none position-relative p-2" data-notif-toggle="1" href="#notifikasi" role="button" aria-label="Notifikasi" title="Notifikasi">
            <i class="fal fa-bell fs-5"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger counter"></span>
        </a>
        <div class="dropdown">
            <button class="btn btn-sm btn-link mobile-topbar-btn p-0 text-decoration-none" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="sidebar-user-avatar" style="width:28px;height:28px;font-size:0.75rem;">
                    <?= esc($namaInisial) ?>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                <li class="dropdown-header text-truncate" style="max-width: 200px;">
                    <strong><?= esc($namaSesi) ?></strong>
                    <div class="small text-muted text-uppercase"><?= esc($levelSesi) ?></div>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item py-2" href="<?= site_url('user/sunting') ?>"><i class="fal fa-user-edit me-2"></i> Ubah Profil</a></li>
                <li><a class="dropdown-item py-2 text-danger" href="<?= site_url('auth/keluar') ?>"><i class="fal fa-sign-out-alt me-2"></i> Keluar</a></li>
            </ul>
        </div>
    </div>
</header>

<!-- Backdrop overlay untuk mobile -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- Sidebar Utama Admin -->
<aside class="app-sidebar" id="appSidebar">
    <!-- Header Brand -->
    <div class="sidebar-header">
        <a href="<?= site_url('') ?>" class="sidebar-brand">
            <div class="sidebar-brand-icon"><?= $this->include('template/merek') ?>
            </div>
            <div class="sidebar-brand-text">
                <span>Djuragan POS</span>
            </div>
        </a>
        <div class="d-flex align-items-center">
            <!-- Tombol sembunyikan desktop -->
            <button class="btn btn-sm sidebar-close-btn sidebar-collapse-btn p-1" id="desktopSidebarCollapse" type="button" title="Sembunyikan Sidebar">
                <i class="fal fa-bars fs-6"></i>
            </button>
            <!-- Tombol tutup mobile -->
            <button class="btn btn-sm sidebar-close-btn p-1 d-lg-none" id="mobileSidebarClose" type="button" aria-label="Tutup Menu">
                <i class="fal fa-times fs-5"></i>
            </button>
        </div>
    </div>

    <!-- Menu Gulir -->
    <div class="sidebar-menu">
        <div class="sidebar-heading">Menu Utama</div>
        <ul class="sidebar-nav">
            <li class="nav-item">
                <a class="sidebar-link <?= $isDasborAktif ? 'active' : '' ?>" href="<?= site_url('admin/dasbor') ?>">
                    <i class="sidebar-link-icon fal fa-home"></i>
                    <span>Dasbor</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-link <?= $isTrxAktif ? 'active' : '' ?>" data-bs-toggle="collapse" href="#submenuTransaksi" role="button" aria-expanded="<?= $isTrxAktif ? 'true' : 'false' ?>" aria-controls="submenuTransaksi">
                    <i class="sidebar-link-icon fal fa-receipt"></i>
                    <span>Transaksi</span>
                    <i class="fal fa-chevron-down sidebar-chevron"></i>
                </a>
                <div class="collapse <?= $isTrxAktif ? 'show' : '' ?>" id="submenuTransaksi">
                    <ul class="sidebar-submenu">
                        <?php foreach ($transaksiSubmenu as [$url, $label, $ikon, $aktif]) : ?>
                            <li class="submenu-item">
                                <a class="sidebar-submenu-link <?= $aktif ? 'active' : '' ?>" href="<?= site_url($url) ?>">
                                    <i class="fal <?= $ikon ?>"></i>
                                    <span><?= esc($label) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>
            <?php if ($levelSesi === 'superadmin') : ?>
                <li class="nav-item">
                    <a class="sidebar-link <?= $isProdukGrupAktif ? 'active' : '' ?>" data-bs-toggle="collapse" href="#submenuProduk" role="button" aria-expanded="<?= $isProdukGrupAktif ? 'true' : 'false' ?>" aria-controls="submenuProduk">
                        <i class="sidebar-link-icon fal fa-box-alt"></i>
                        <span>Produk</span>
                        <i class="fal fa-chevron-down sidebar-chevron"></i>
                    </a>
                    <div class="collapse <?= $isProdukGrupAktif ? 'show' : '' ?>" id="submenuProduk">
                        <ul class="sidebar-submenu">
                            <?php foreach ($produkSubmenu as [$url, $label, $ikon, $aktif]) : ?>
                                <li class="submenu-item">
                                    <a class="sidebar-submenu-link <?= $aktif ? 'active' : '' ?>" href="<?= site_url($url) ?>">
                                        <i class="fal <?= $ikon ?>"></i>
                                        <span><?= esc($label) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>
            <?php else : ?>
                <li class="nav-item">
                    <a class="sidebar-link <?= $isProdukAktif ? 'active' : '' ?>" href="<?= site_url('admin/produk') ?>">
                        <i class="sidebar-link-icon fal fa-box-alt"></i>
                        <span>Katalog Produk</span>
                    </a>
                </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="sidebar-link <?= $isReminderAktif ? 'active' : '' ?>" href="<?= site_url('admin/reminder') ?>"
                    data-badge-url="<?= site_url('admin/reminder/jumlah') ?>" data-badge-unit="orderan mendekati/lewat deadline">
                    <i class="sidebar-link-icon fal fa-cut"></i>
                    <span>Reminder Pesanan</span>
                    <?php if ($reminderCount > 0) : ?>
                        <span class="badge rounded-pill bg-danger ms-auto badge-live" title="<?= (int) $reminderCount ?> orderan mendekati/lewat deadline"><?= (int) $reminderCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-link <?= $isTugasAktif ? 'active' : '' ?>" data-bs-toggle="collapse" href="#submenuTugas" role="button" aria-expanded="<?= $isTugasAktif ? 'true' : 'false' ?>" aria-controls="submenuTugas">
                    <i class="sidebar-link-icon fal fa-tasks"></i>
                    <span>Tugas CS</span>
                    <?php if ($tugasTerbuka > 0) : ?>
                        <span class="badge rounded-pill bg-danger ms-auto badge-live" title="<?= (int) $tugasTerbuka ?> tugas CS yang belum selesai"><?= (int) $tugasTerbuka ?></span>
                    <?php endif; ?>
                    <!-- ms-2 hanya boleh ada kalau badge ikut tampil: tanpa badge, kelas itu
                         (Bootstrap !important) menimpa margin-left:auto dari style.css dan
                         chevronnya menempel ke label alih-alih duduk di pojok kanan -->
                    <i class="fal fa-chevron-down sidebar-chevron <?= $tugasTerbuka > 0 ? 'ms-2' : '' ?>"></i>
                </a>
                <div class="collapse <?= $isTugasAktif ? 'show' : '' ?>" id="submenuTugas">
                    <ul class="sidebar-submenu">
                        <li class="submenu-item">
                            <a class="sidebar-submenu-link <?= $isCklAktif ? 'active' : '' ?>"
                                href="<?= site_url('admin/checklist') ?>"
                                data-badge-url="<?= site_url('admin/checklist/jumlah') ?>" data-badge-unit="lembar toko hari ini yang belum ditutup">
                                <i class="fal fa-ballot-check"></i>
                                <span>Cek Toko</span>
                                <?php if ($cklTerbuka > 0) : ?>
                                    <span class="badge rounded-pill bg-danger ms-auto badge-live" title="<?= (int) $cklTerbuka ?> lembar toko hari ini yang belum ditutup"><?= (int) $cklTerbuka ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="submenu-item">
                            <a class="sidebar-submenu-link <?= url_is('admin/tugas/email*') ? 'active' : '' ?>"
                                href="<?= site_url('admin/tugas/email') ?>"
                                data-badge-url="<?= site_url('admin/tugas/email/jumlah') ?>" data-badge-unit="catatan cek email yang belum selesai">
                                <i class="fal fa-envelope"></i>
                                <span>Cek Email</span>
                                <?php if ($emailTerbuka > 0) : ?>
                                    <span class="badge rounded-pill bg-danger ms-auto badge-live" title="<?= (int) $emailTerbuka ?> catatan cek email yang belum selesai"><?= (int) $emailTerbuka ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
                        <li class="nav-item">
                <a class="sidebar-link <?= $isSopAktif ? 'active' : '' ?>" href="<?= site_url('admin/sop') ?>">
                    <i class="sidebar-link-icon fal fa-book-open"></i>
                    <span>SOP &amp; Panduan</span>
                </a>
            </li>
        </ul>

        <?php if (in_array($levelSesi, ['admin', 'superadmin'], true)) : ?>
            <div class="sidebar-heading mt-2">Hubungan Pelanggan</div>
            <ul class="sidebar-nav">
                <!-- Submenu CRM -->
                <li class="nav-item">
                    <a class="sidebar-link <?= $isCrmAktif ? 'active' : '' ?>" data-bs-toggle="collapse" href="#submenuCrm" role="button" aria-expanded="<?= $isCrmAktif ? 'true' : 'false' ?>" aria-controls="submenuCrm">
                        <i class="sidebar-link-icon fal fa-comments-alt"></i>
                        <span>CRM</span>
                        <?php if ($crmTerbuka > 0) : ?>
                            <span class="badge rounded-pill bg-danger ms-auto badge-live" title="<?= (int) $crmTerbuka ?> reminder kerja yang belum selesai"><?= (int) $crmTerbuka ?></span>
                        <?php endif; ?>
                        <i class="fal fa-chevron-down sidebar-chevron <?= $crmTerbuka > 0 ? 'ms-2' : '' ?>"></i>
                    </a>
                    <div class="collapse <?= $isCrmAktif ? 'show' : '' ?>" id="submenuCrm">
                        <ul class="sidebar-submenu">
                            <?php foreach ($crmSubmenu as $item) : ?>
                                <?php [$url, $label, $ikon, $aktif] = $item; $lencana = $item[4] ?? null; ?>
                                <li class="submenu-item">
                                    <a class="sidebar-submenu-link <?= $aktif ? 'active' : '' ?>" href="<?= site_url($url) ?>"
                                        <?php if ($lencana !== null) : ?>
                                            data-badge-url="<?= site_url($lencana['url']) ?>" data-badge-unit="<?= esc($lencana['unit']) ?>"
                                        <?php endif; ?>>
                                        <i class="fal <?= $ikon ?>"></i>
                                        <span><?= esc($label) ?></span>
                                        <?php if ($lencana !== null && $lencana['angka'] > 0) : ?>
                                            <span class="badge rounded-pill bg-danger ms-auto badge-live" title="<?= (int) $lencana['angka'] ?> <?= esc($lencana['unit']) ?>"><?= (int) $lencana['angka'] ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>
            </ul>

            <div class="sidebar-heading mt-2">Kinerja & KPI</div>
            <ul class="sidebar-nav">
                <!-- Submenu KPI -->
                <li class="nav-item">
                    <a class="sidebar-link <?= $isKpiAktif ? 'active' : '' ?>" data-bs-toggle="collapse" href="#submenuKpi" role="button" aria-expanded="<?= $isKpiAktif ? 'true' : 'false' ?>" aria-controls="submenuKpi">
                        <i class="sidebar-link-icon fal fa-bullseye"></i>
                        <span>KPI</span>
                        <i class="fal fa-chevron-down sidebar-chevron"></i>
                    </a>
                    <div class="collapse <?= $isKpiAktif ? 'show' : '' ?>" id="submenuKpi">
                        <ul class="sidebar-submenu">
                            <?php foreach ($kpiSubmenu as [$url, $label, $ikon, $aktif]) : ?>
                                <li class="submenu-item">
                                    <a class="sidebar-submenu-link <?= $aktif ? 'active' : '' ?>" href="<?= site_url($url) ?>">
                                        <i class="fal <?= $ikon ?>"></i>
                                        <span><?= esc($label) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>
            </ul>
        <?php endif; ?>

        <div class="sidebar-heading mt-2">Laporan & Analisis</div>
        <ul class="sidebar-nav">
            <!-- Submenu Laporan -->
            <li class="nav-item">
                <a class="sidebar-link <?= $isLaporanAktif ? 'active' : '' ?>" data-bs-toggle="collapse" href="#submenuLaporan" role="button" aria-expanded="<?= $isLaporanAktif ? 'true' : 'false' ?>" aria-controls="submenuLaporan">
                    <i class="sidebar-link-icon fal fa-file-alt"></i>
                    <span>Laporan</span>
                    <i class="fal fa-chevron-down sidebar-chevron"></i>
                </a>
                <div class="collapse <?= $isLaporanAktif ? 'show' : '' ?>" id="submenuLaporan">
                    <ul class="sidebar-submenu">
                        <?php foreach ($laporanSubmenu as [$url, $label, $ikon, $aktif]) : ?>
                            <li class="submenu-item">
                                <a class="sidebar-submenu-link <?= $aktif ? 'active' : '' ?>" href="<?= site_url($url) ?>">
                                    <i class="fal <?= $ikon ?>"></i>
                                    <span><?= esc($label) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>
        </ul>

        <?php if ($sistemSubmenu !== [] || $levelSesi === 'superadmin') : ?>
            <div class="sidebar-heading mt-2">Sistem</div>
            <ul class="sidebar-nav">
                <!-- Submenu Pengaturan -->
                <li class="nav-item">
                    <a class="sidebar-link <?= $isSettingsAktif ? 'active' : '' ?>" data-bs-toggle="collapse" href="#submenuSettings" role="button" aria-expanded="<?= $isSettingsAktif ? 'true' : 'false' ?>" aria-controls="submenuSettings">
                        <i class="sidebar-link-icon fal fa-cogs"></i>
                        <span>Pengaturan</span>
                        <i class="fal fa-chevron-down sidebar-chevron"></i>
                    </a>
                    <div class="collapse <?= $isSettingsAktif ? 'show' : '' ?>" id="submenuSettings">
                        <ul class="sidebar-submenu">
                            <?php foreach ($sistemSubmenu as [$url, $label, $ikon, $aktif]) : ?>
                                <li class="submenu-item">
                                    <a class="sidebar-submenu-link <?= $aktif ? 'active' : '' ?>" href="<?= site_url($url) ?>">
                                        <i class="fal <?= $ikon ?>"></i>
                                        <span><?= esc($label) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>
                <?php if ($levelSesi === 'superadmin') : ?>
                    <!-- kotak masukan bukan bagian dari pengaturan: menunya berdiri
                         sendiri karena yang membacanya hanya Superadmin, dan duduk
                         paling bawah supaya tidak menumpuk di atas pengaturan -->
                    <li class="nav-item">
                        <a class="sidebar-link <?= $isMasukanAktif ? 'active' : '' ?>" href="<?= site_url('admin/masukan') ?>">
                            <i class="sidebar-link-icon fal fa-headset"></i>
                            <span>Kotak Masukan</span>
                            <?php if ($masukanPrioritas > 0) : ?>
                                <span class="badge rounded-pill bg-danger ms-auto" title="<?= (int) $masukanPrioritas ?> permintaan prioritas menunggu"><?= (int) $masukanPrioritas ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Footer Sidebar: Profil -->
    <div class="sidebar-footer">
        <div class="sidebar-user-card">
            <div class="sidebar-user-avatar">
                <?= esc($namaInisial) ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name" title="<?= esc($namaSesi) ?>"><?= esc($namaSesi) ?></div>
                <div class="sidebar-user-role"><?= esc($levelSesi ?: 'Admin') ?></div>
            </div>
            <div class="sidebar-user-actions">
                <a class="sidebar-action-btn" href="<?= site_url('user/sunting') ?>" title="Ubah Profil">
                    <i class="fal fa-user-edit"></i>
                </a>
                <a class="sidebar-action-btn btn-logout" href="<?= site_url('auth/keluar') ?>" title="Keluar">
                    <i class="fal fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>
</aside>

<?= $this->include('template/notif_bar') ?>

<script>
    (function() {
        document.body.classList.add('has-app-sidebar');
        if (localStorage.getItem('djuragan_sidebar_collapsed') === '1') {
            document.body.classList.add('sidebar-collapsed');
        }

        // Mobile sidebar toggle
        var mobToggle = document.getElementById('mobileSidebarToggle');
        var mobClose  = document.getElementById('mobileSidebarClose');
        var backdrop  = document.getElementById('sidebarBackdrop');
        var sidebar   = document.getElementById('appSidebar');

        function openSidebar() {
            if (sidebar) sidebar.classList.add('show');
            if (backdrop) backdrop.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('show');
            if (backdrop) backdrop.classList.remove('show');
            document.body.style.overflow = '';
        }

        if (mobToggle) mobToggle.addEventListener('click', openSidebar);
        if (mobClose) mobClose.addEventListener('click', closeSidebar);
        if (backdrop) backdrop.addEventListener('click', closeSidebar);

        // Desktop collapse toggle — rail ikon tetap terlihat dan melebar sendiri
        // saat disentuh kursor (lihat .sidebar-collapsed di style.css).
        var deskCollapse = document.getElementById('desktopSidebarCollapse');

        if (deskCollapse) {
            deskCollapse.addEventListener('click', function() {
                document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('djuragan_sidebar_collapsed',
                    document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
            });
        }
    })();
</script>
