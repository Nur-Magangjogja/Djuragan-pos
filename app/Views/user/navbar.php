<?= $this->include('template/feedback') ?>
<?php
$di_halaman  = trim(uri_string(), '/');
$levelSesi   = session()->get('level') ?? ($_SESSION['level'] ?? 'CS');
$namaSesi    = session()->get('name') ?? ($_SESSION['name'] ?? 'Pengguna');
$namaInisial = strtoupper(mb_substr($namaSesi, 0, 1));

$isTrxAktif   = url_is('user/invoices*');
$isChartAktif = url_is('user/chart*');
// katalog produk CS dilayani halaman yang sama dengan admin (read-only)
$isProdukAktif = url_is('admin/produk*');
$isReminderAktif = url_is('admin/reminder*');
$isTugasAktif    = url_is('admin/tugas*');
$isCklAktif      = url_is('admin/checklist*');
$isSopAktif     = url_is('admin/sop*');
$isTokoAktif     = url_is('admin/toko*');
// badge Reminder Penjahit (helper fungsi tidak autoload): orderan dekat/lewat deadline
helper('fungsi');
$reminderCount = pengingat_penjahit_jumlah();
$tugasModel    = new \App\Models\TugasModel();
$lainTerbuka   = $tugasModel->jumlah(['jenis_not' => \App\Models\TugasModel::PUNYA_SUBMENU, 'terbuka' => true, 'juragan_ids' => juragan_dipakai_sesi()]);
$waTerbuka    = (new \App\Models\TugasWhatsappModel())->hitungTerbuka(juragan_dipakai_sesi());
$b5Model     = new \App\Models\TugasModel();
$b5Terbuka    = $b5Model->hitungPemicu('ulasan', juragan_dipakai_sesi());
$emailTerbuka = (new \App\Models\TugasModel())->jumlah(["jenis" => "pemeriksaan_email", "terbuka" => true, "juragan_ids" => juragan_dipakai_sesi()]);
$cklTerbuka    = (new \App\Models\ChecklistModel())->hitungTerbuka(juragan_dipakai_sesi(), date('Y-m-d'));
$tugasTerbuka = $lainTerbuka + $b5Terbuka + $waTerbuka + $cklTerbuka + $emailTerbuka;
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
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger counter" style="font-size: 0.65rem;"></span>
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

<!-- Sidebar Utama CS / User -->
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
                <a class="sidebar-link <?= $isTrxAktif ? 'active' : '' ?>" href="<?= site_url('user/invoices') ?>">
                    <i class="sidebar-link-icon fal fa-receipt"></i>
                    <span>Transaksi</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-link <?= $isChartAktif ? 'active' : '' ?>" href="<?= site_url('user/chart') ?>">
                    <i class="sidebar-link-icon fal fa-chart-line"></i>
                    <span>Chart</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-link <?= $isProdukAktif ? 'active' : '' ?>" href="<?= site_url('admin/produk') ?>">
                    <i class="sidebar-link-icon fal fa-box-alt"></i>
                    <span>Katalog Produk</span>
                </a>
            </li>
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
                    <i class="fal fa-chevron-down sidebar-chevron"></i>
                </a>
                <div class="collapse <?= $isTugasAktif ? 'show' : '' ?>" id="submenuTugas">
                    <ul class="sidebar-submenu">
                        <li class="submenu-item">
                            <a class="sidebar-submenu-link <?= url_is('admin/tugas/whatsapp*') ? 'active' : '' ?>"
                                href="<?= site_url('admin/tugas/whatsapp') ?>"
                                data-badge-url="<?= site_url('admin/tugas/whatsapp/jumlah') ?>" data-badge-unit="reminder WhatsApp yang belum selesai">
                                <i class="fal fa-comment-dots"></i>
                                <span>Reminder WhatsApp</span>
                                <?php if ($waTerbuka > 0) : ?>
                                    <span class="badge rounded-pill bg-danger ms-auto badge-live" title="<?= (int) $waTerbuka ?> reminder WhatsApp yang belum selesai"><?= (int) $waTerbuka ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="submenu-item">
                            <a class="sidebar-submenu-link <?= url_is('admin/tugas/bintang*') ? 'active' : '' ?>"
                                href="<?= site_url('admin/tugas/bintang') ?>"
                                data-badge-url="<?= site_url('admin/tugas/bintang/jumlah') ?>" data-badge-unit="reminder Bintang 5 yang belum dikerjakan">
                                <i class="fal fa-star"></i>
                                <span>Reminder Bintang 5</span>
                                <?php if ($b5Terbuka > 0) : ?>
                                    <span class="badge rounded-pill bg-danger ms-auto badge-live" title="<?= (int) $b5Terbuka ?> reminder Bintang 5 yang belum dikerjakan"><?= (int) $b5Terbuka ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
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
                        <li class="submenu-item">
                            <a class="sidebar-submenu-link <?= url_is('admin/tugas') ? 'active' : '' ?>"
                                href="<?= site_url('admin/tugas') ?>"
                                data-badge-url="<?= site_url('admin/tugas/jumlah') ?>" data-badge-unit="tugas lain-lain yang belum selesai">
                                <i class="fal fa-layer-group"></i>
                                <span>Lain-lain</span>
                                <?php if ($lainTerbuka > 0) : ?>
                                    <span class="badge rounded-pill bg-danger ms-auto badge-live" title="<?= (int) $lainTerbuka ?> tugas lain-lain yang belum selesai"><?= (int) $lainTerbuka ?></span>
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
            <li class="nav-item">
                <a class="sidebar-link <?= $isTokoAktif ? 'active' : '' ?>" href="<?= site_url('admin/toko/pengenalan') ?>">
                    <i class="sidebar-link-icon fal fa-store"></i>
                    <span>Pengenalan Toko</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Footer Sidebar: Profil -->
    <div class="sidebar-footer">
        <div class="sidebar-user-card">
            <div class="sidebar-user-avatar">
                <?= esc($namaInisial) ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name" title="<?= esc($namaSesi) ?>"><?= esc($namaSesi) ?></div>
                <div class="sidebar-user-role"><?= esc($levelSesi ?: 'CS') ?></div>
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
