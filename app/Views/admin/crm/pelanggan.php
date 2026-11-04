<?php
/**
 * Variables injected at runtime by \App\Controllers\Admin\Crm::pelanggan()
 * via view('admin/crm/pelanggan', $data). Declared here so static analyzers
 * (Intelephense P1008) do not flag them as undefined.
 *
 * @var string $segmen          Behavioural segment filter (from GET ?segmen)
 * @var string $status          CRM status filter (from GET ?status)
 * @var int    $tagId           Tag filter id (from GET ?tag_id)
 * @var string $cari            Search keyword (from GET ?cari)
 * @var int    $page            Active page number (1-based)
 * @var float  $totalPages      Total number of pages
 * @var int    $totalData       Total customers matching the filters
 * @var array  $daftarPelanggan List of customer rows for the current page
 * @var array  $allTags         All available tags for the filter dropdown
 */
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
/* ikon cari ikut di dalam field; radius sengaja tidak disentuh supaya bentuk
   kotaknya tetap sama seperti sebelumnya */
.form-control.crm-cari {
    padding-left: 2.1rem !important;
}
/* style.css mengunci .form-select-sm{padding:.35rem .65rem !important} sehingga
   ruang yang Bootstrap sisakan di sisi kanan untuk panah kotaknya ikut terhapus.
   Akibatnya teks pilihan yang panjang ("Prospek / Belum Closing", "Semua Segmen
   Perilaku") mengalir sampai ke bawah tanda ">". Ruang itu dikembalikan: panah
   sm duduk di 8px dari tepi dan lebarnya ±13px, 1,75rem memberi sisa aman. */
.form-select-sm {
    padding-right: 1.75rem !important;
}
/* dua tombol aksi di kolom paling kanan dibuat sama ukurannya. .btn di style.css
   pakai inline-flex + gap, jadi cukup atur lebar minimumnya saja */
td .btn.crm-aksi {
    min-width: 84px;
}
/* Desktop: tabel ini lebih lebar dari wadahnya (judul kolom dipaksa nowrap di
   style.css + kolom Customer punya min-width), sehingga .table-responsive
   memunculkan scroll horizontal dan kolom Aksi terdorong keluar. table-layout
   fixed membuat tabel tidak pernah melebar lebih dari kontainernya: kolom dibagi
   dalam persen dan isinya yang turun baris, bukan tabelnya. */
.crm-tabel {
    table-layout: fixed;
    width: 100%;
}
.crm-tabel th {
    white-space: normal !important;
}
.crm-tabel td,
.crm-tabel th {
    overflow-wrap: anywhere;
}
/* padding harus memakai selector yang lebih spesifik dari
   ".table tbody td" di style.css (paddingnya !important), kalau tidak
   aturan ini kalah dan tiap sel tetap makan 1rem kiri-kanan */
.crm-tabel tbody td {
    padding-left: 0.6rem !important;
    padding-right: 0.6rem !important;
}
/* Desktop: dua kolom uang adalah angka yang dibaca orang — "Rp 15.475.000" atau
   badge "Rp 160.000" yang belah dua lebih membingungkan daripada kolomnya sedikit
   lebih longgar, jadi keduanya (plus tanggal) tidak boleh turun baris. Lebar
   kolomnya yang menyesuaikan: lihat blok 768-1199px di bawah. <=767.98px mode
   kartu, sel-nya selebar layar jadi tidak ada yang perlu dinowrap-kan. */
@media (min-width: 768px) {
    .crm-tabel tbody td[data-label="Total Belanja"],
    .crm-tabel tbody td[data-label="Sisa Piutang"] {
        white-space: nowrap;
    }
}
/* 768-1199px: wadahnya baru ±700-730px karena sidebar masih penuh, jadi delapan
   kolom tidak muat dengan pembagian di atas (badge piutang dan tombol 360°
   terpotong tepi .table-responsive yang overflow:hidden). Di rentang ini kolom
   uang dan Aksi yang diberi ruang — nama customer boleh turun baris, angka
   uang tidak. Jatah per kolom: 136/86/55/44/104/108/57/98px, semua cukup
   (tanggal "20 Jun 2022" memang dibiarkan belah dua, kolomnya 70px). */
@media (min-width: 768px) and (max-width: 1199.98px) {
    .crm-tabel thead th:nth-child(1) { width: 17% !important; }
    .crm-tabel thead th:nth-child(2) { width: 13% !important; }
    .crm-tabel thead th:nth-child(3) { width: 8% !important; }
    .crm-tabel thead th:nth-child(4) { width: 7% !important; }
    .crm-tabel thead th:nth-child(5) { width: 15% !important; }
    .crm-tabel thead th:nth-child(6) { width: 16% !important; }
    .crm-tabel thead th:nth-child(7) { width: 10% !important; }
    .crm-tabel thead th:nth-child(8) { width: 14% !important; }

    .crm-tabel tbody td {
        padding-left: 0.45rem !important;
        padding-right: 0.45rem !important;
    }
}
/* Desktop lebar (>=1280px): judul kolom tidak boleh belah dua ("ORDER / POS",
   "ORDER / TERAKHIR"). Yang membuat itu baru mungkin di lebar ini: delapan judul
   butuh 950px sekali baris (teks 11.52px huruf besar + jarak antar huruf 0.58px,
   plus padding 0,6rem dua sisi), sedangkan wadahnya 960px di 1280px dan baru 890px
   di 1200px — di bawah itu tetap boleh belah daripada terpotong. */
@media (min-width: 1280px) {
    .crm-tabel thead th {
        white-space: nowrap !important;
        padding-left: 0.6rem !important;
        padding-right: 0.6rem !important;
    }
    .crm-tabel thead th:nth-child(1) { width: 15% !important; }
    .crm-tabel thead th:nth-child(2) { width: 15% !important; }
    .crm-tabel thead th:nth-child(3) { width: 10% !important; }
    .crm-tabel thead th:nth-child(4) { width: 10% !important; }
    .crm-tabel thead th:nth-child(5) { width: 13% !important; }
    .crm-tabel thead th:nth-child(6) { width: 13% !important; }
    .crm-tabel thead th:nth-child(7) { width: 13% !important; }
    .crm-tabel thead th:nth-child(8) { width: 11% !important; }
}
/* Khusus mobile (<=768px): tiap baris ditumpuk jadi kartu. Desktop tidak diubah. */
@media (max-width: 767.98px) {
    /* Wadah tabel: bukan area geser, tapi juga tidak boleh meluber ke kanan
       sampai melewati tepi layar (pakai hidden, bukan visible). */
    div.table-responsive {
        overflow: hidden !important;
    }
    /* Panel putih yang membungkus SELURUH daftar (25 kartu, tingginya ±10.300px)
       harus mundur di mode kartu: selama panelnya ikut putih, celah antar kartu
       terbaca putih-di-atas-putih sehingga kartu-kartunya tampak digandeng jadi
       satu papan. Yang terlihat di celah sekarang adalah latar halaman (#f9fafb).
       .card dan .bg-white di style.css sama-sama !important, jadi ikut !important.
       Selector-nya ".card.crm-panel-kartu" (0,2,0), bukan kelasnya saja, supaya
       menang atas ".card, .kartu-invoice{background:#fff!important}" di style.css:1190
       tanpa bergantung pada urutan muat stylesheet. */
    .card.crm-panel-kartu,
    .crm-panel-kartu > .table-responsive {
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
    }
    /* Pager-nya tadinya menempel di dasar panel; setelah panelnya hilang ia
       jadi kartu sendiri yang sama bentuknya dengan kartu di atasnya. margin-top
       sengaja 0: .table-responsive di atas pakai overflow:hidden (BFC) sehingga
       margin-bottom kartu terakhir sudah dihitung di dalamnya — ditambah lagi
       akan jadi dua kali jarak. */
    .crm-panel-kartu > .card-footer {
        background: #ffffff !important;
        border: 1px solid #eaecf0 !important;
        border-radius: 10px !important;
        margin-top: 0;
    }
    .crm-tabel thead {
        display: none;
    }
    .crm-tabel,
    .crm-tabel tbody,
    .crm-tabel td {
        display: block;
        width: 100%;
    }
    .crm-tabel tr {
        display: grid;
        /* minmax(0,1fr), bukan 1fr: batas bawah 1fr adalah "auto" sehingga satu
           sel yang isinya panjang bisa melebar dan halaman jadi bisa digeser */
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        column-gap: 0.9rem;
        border: 1px solid #eaecf0;
        border-radius: 10px;
        /* sama rata dengan column-gap supaya jarak antar kartu tidak terasa
           lebih sumpek daripada jarak dua kolom di dalam kartu */
        margin-bottom: 0.9rem;
        padding: 0.15rem 0.7rem;
    }
    .crm-tabel td:nth-child(1),
    .crm-tabel td:nth-child(8),
    .crm-tabel td[colspan] {
        grid-column: 1 / -1;
    }
    .crm-tabel tbody td {
        padding: 0.5rem 0 !important;
        border-bottom: 1px dashed #eaecf0 !important;
        box-shadow: none;
        text-align: left !important;
        white-space: normal !important;
        overflow-wrap: anywhere;
    }
    .crm-tabel tr td:last-child,
    .crm-tabel tbody td:last-child,
    .crm-tabel td[data-label="Aksi"] {
        border-bottom: 0 !important;
    }
    .crm-tabel td::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 0.15rem;
        font-size: 0.72rem !important;
        font-weight: 600;
        color: #98a2b3;
    }
    .crm-tabel td .btn {
        margin-bottom: 0.25rem;
    }
    /* Baris Aksi dulu dua tombolnya menggantung di kiri dan menyisakan hampir
       separuh baris kosong di kanan. Sekarang labelnya satu baris sendiri dan
       kedua tombol dibagi rata sampai tepi kartu. .me-2 di markup dan padding
       td di atas sama-sama !important, jadi ikut !important juga. */
    .crm-tabel td[data-label="Aksi"] {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
    }
    .crm-tabel td[data-label="Aksi"]::before {
        width: 100%;
    }
    .crm-tabel td[data-label="Aksi"] .btn {
        flex: 1 1 0;
        min-width: 0;
        margin: 0 !important;
    }
    /* Dua angka uang sejajar di kolomnya masing-masing supaya tidak bergigi */
    .crm-tabel td[data-label="Total Belanja"],
    .crm-tabel td[data-label="Sisa Piutang"] {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    /* Sorotan baris di mode kartu. style.css mewarnai TIAP SEL saat barisnya
       disorot (.table tbody tr:hover td, !important); di mode kartu sel tidak
       punya padding kiri-kanan sementara kartunya punya column-gap + padding,
       jadi sentuhan menimbulkan kotak-kotak putih di dalam kartu. Pewarnaan sel
       dimatikan dan kartunya saja yang disorot -- khusus pointer sungguhan,
       supaya di layar sentuh tidak menempel setelah dilepas. */
    .crm-tabel tbody tr:hover td {
        background-color: transparent !important;
    }

    .crm-tabel tbody tr {
        background-color: #ffffff;
        transition: background-color 0.15s ease, border-color 0.15s ease;
    }

    @media (hover: hover) {
        .crm-tabel tbody tr:hover {
            background-color: #f9fafb;
            border-color: #d0d5dd;
        }
    }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 fw-bold">
                <?php /* ikonnya hanya untuk layar lebar; di layar sempit judulnya
                        saja yang tersisa supaya tidak ada ruang kosong di kiri */ ?>
                <i class="fal fa-users text-primary me-2 d-none d-lg-inline"></i> Data Pelanggan CRM
            </h4>
            <p class="text-muted small mb-0">Kelola profil customer, segmentasi perilaku, status interaksi, dan tag pelanggan.</p>
        </div>
    </div>


    <!-- Filter & Pencarian Diperkuat -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="<?= site_url('admin/crm/pelanggan') ?>" class="row g-2 align-items-center">
                <?php /* di bawah 992px tiap filter mengambil satu baris penuh:
                        sebelumnya dua-tiga filter sebaris sehingga teks pilihan
                        terpanjang terpotong; susunan layar lebar tidak diubah */ ?>
                <!-- Search input -->
                <div class="col-12 col-lg-3">
                    <div class="position-relative">
                        <i class="fal fa-search text-muted position-absolute top-50 start-0 translate-middle-y ms-2"></i>
                        <input type="text" name="cari" class="form-control form-control-sm crm-cari" placeholder="Cari nama, nomor WA, atau ID..." value="<?= esc($cari) ?>">
                    </div>
                </div>

                <!-- Status CRM -->
                <div class="col-12 col-lg-2">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="semua" <?= $status === 'semua' ? 'selected' : '' ?>>Semua Status CRM</option>
                        <option value="prospek" <?= $status === 'prospek' ? 'selected' : '' ?>>Prospek / Belum Closing</option>
                        <option value="baru" <?= $status === 'baru' ? 'selected' : '' ?>>Customer Baru</option>
                        <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Customer Aktif</option>
                        <option value="loyal" <?= $status === 'loyal' ? 'selected' : '' ?>>Customer Loyal</option>
                        <option value="tidak_aktif" <?= $status === 'tidak_aktif' ? 'selected' : '' ?>>Tidak Aktif / Pasif</option>
                    </select>
                </div>

                <!-- Segmen Perilaku -->
                <div class="col-12 col-lg-3">
                    <select name="segmen" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="semua" <?= $segmen === 'semua' ? 'selected' : '' ?>>Semua Segmen Perilaku</option>
                        <option value="sewa" <?= $segmen === 'sewa' ? 'selected' : '' ?>>Sering Sewa / Rental</option>
                        <option value="custom" <?= $segmen === 'custom' ? 'selected' : '' ?>>Custom Order / Pembuatan</option>
                        <option value="repeat" <?= $segmen === 'repeat' ? 'selected' : '' ?>>Repeat Order (> 1x)</option>
                        <option value="sekali" <?= $segmen === 'sekali' ? 'selected' : '' ?>>Order Sekali</option>
                        <option value="high_spender" <?= $segmen === 'high_spender' ? 'selected' : '' ?>>High Spender (> Rp 2 Jt)</option>
                        <option value="piutang" <?= $segmen === 'piutang' ? 'selected' : '' ?>>Memiliki Piutang</option>
                        <option value="pasif" <?= $segmen === 'pasif' ? 'selected' : '' ?>>Pasif (> 60 Hari)</option>
                        <option value="prospek" <?= $segmen === 'prospek' ? 'selected' : '' ?>>Belum Pernah Order (0 Transaksi)</option>
                    </select>
                </div>

                <!-- Filter Tag -->
                <div class="col-12 col-lg-2">
                    <select name="tag_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="0">Semua Tag</option>
                        <?php foreach ($allTags as $t): ?>
                            <option value="<?= $t['id_tag'] ?>" <?= $tagId == $t['id_tag'] ? 'selected' : '' ?>>
                                Tag: <?= esc($t['nama_tag']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="col-12 col-lg-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold"><i class="fal fa-filter me-1"></i> Filter</button>
                    <?php if (!empty($cari) || $segmen !== 'semua' || $status !== 'semua' || $tagId > 0) : ?>
                        <a href="<?= site_url('admin/crm/pelanggan') ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filter"><i class="fal fa-undo"></i></a>
                    <?php endif; ?>
                </div>

                <div class="col-12 text-muted small mt-2">
                    Ditemukan: <strong><?= number_format($totalData) ?></strong> customer
                    <?php if ($tagId > 0 || $status !== 'semua' || $segmen !== 'semua'): ?>
                        (difilter)
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Data Pelanggan -->
    <div class="card border-0 shadow-sm mb-4 crm-panel-kartu">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 crm-tabel">
                <thead class="table-light">
                    <tr>
                        <!-- lebar kolom diukur dari isi terlebar (lihat .crm-tabel di atas):
                             uang dan badge piutang tidak boleh turun baris, Aksi tidak lagi
                             20% untuk satu tombol 84px -->
                        <th style="width: 21%;">Customer &amp; Status</th>
                        <th style="width: 13%;">Kontak WhatsApp</th>
                        <th style="width: 10%;">Lokasi</th>
                        <th class="text-center" style="width: 7%;">Order POS</th>
                        <th class="text-end" style="width: 13%;">Total Belanja</th>
                        <th class="text-end" style="width: 13%;">Sisa Piutang</th>
                        <th style="width: 11%;">Order Terakhir</th>
                        <th class="text-center" style="width: 12%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($daftarPelanggan)) : ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fal fa-users-slash fa-3x text-muted mb-2 d-block opacity-50"></i>
                                Tidak ada data customer yang sesuai dengan filter atau kata kunci pencarian.
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($daftarPelanggan as $p) : ?>
                            <tr>
                                <td data-label="Customer">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-primary bg-opacity-10 text-primary fw-bold rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 36px; height: 36px; font-size: 13px;">
                                            <?= strtoupper(substr($p['nama_pelanggan'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <a href="<?= site_url('admin/crm/pelanggan/' . $p['id_pelanggan']) ?>" class="fw-bold text-dark text-decoration-none d-block">
                                                <?= esc($p['nama_pelanggan']) ?>
                                            </a>
                                            <!-- Status CRM badge -->
                                            <?php
                                            $st = $p['status_crm'] ?? 'prospek';
                                            $badgeInfo = match ($st) {
                                                'loyal'       => ['badge bg-warning text-dark', 'Loyal'],
                                                'aktif'       => ['badge bg-success', 'Aktif'],
                                                'baru'        => ['badge bg-info text-dark', 'Baru'],
                                                'tidak_aktif' => ['badge bg-secondary', 'Pasif'],
                                                default       => ['badge bg-light text-muted border', 'Prospek'],
                                            };
                                            ?>
                                            <span class="<?= $badgeInfo[0] ?>" style="font-size: 10px;"><?= $badgeInfo[1] ?></span>

                                            <!-- Tags -->
                                            <?php if (!empty($p['tags'])): ?>
                                                <?php foreach (array_slice($p['tags'], 0, 2) as $t): ?>
                                                    <span class="badge bg-<?= esc($t['warna']) ?>" style="font-size: 10px;"><?= esc($t['nama_tag']) ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($p['tags']) > 2): ?>
                                                    <span class="badge bg-light text-muted border" style="font-size: 10px;">+<?= count($p['tags']) - 2 ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Kontak WhatsApp">
                                    <?php if (!empty($p['nomor_utama'])): ?>
                                        <a href="https://wa.me/<?= preg_replace('/[^\d]/', '', $p['nomor_utama']) ?>" target="_blank" class="text-decoration-none text-success font-monospace fw-semibold small">
                                            <i class="fab fa-whatsapp me-1"></i><?= esc($p['nomor_utama']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">Belum ada nomor</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted" data-label="Lokasi">
                                    <?= esc($p['kota'] ?: ($p['provinsi'] ?: '-')) ?>
                                </td>
                                <td class="text-center" data-label="Order POS">
                                    <span class="fw-bold text-dark"><?= number_format($p['jumlah_order']) ?>x</span>
                                    <div class="text-muted small" style="font-size: 11px;">(<?= number_format($p['qty']) ?> pcs)</div>
                                </td>
                                <td class="text-end fw-semibold" data-label="Total Belanja">
                                    Rp <?= number_format($p['nilai'], 0, ',', '.') ?>
                                </td>
                                <td class="text-end" data-label="Sisa Piutang">
                                    <?php if ($p['sisa'] > 0) : ?>
                                        <span class="badge bg-danger">
                                            Rp <?= number_format($p['sisa'], 0, ',', '.') ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="badge bg-light text-success border">Lunas</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted" data-label="Order Terakhir">
                                    <?= !empty($p['order_terakhir']) ? date('d M Y', strtotime($p['order_terakhir'])) : '<span class="text-muted fst-italic">Belum ada</span>' ?>
                                </td>
                                <td class="text-center" data-label="Aksi">
                                    <a href="<?= site_url('admin/crm/pelanggan/' . $p['id_pelanggan']) ?>" class="btn btn-sm btn-primary crm-aksi" title="Buka Profil Customer 360">
                                        <i class="fal fa-id-card"></i> 360°
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1) : ?>
            <?php
            $akhir = (int) $totalPages;

            $tautanHalaman = static function (int $n) use ($segmen, $status, $tagId, $cari): string {
                return site_url('admin/crm/pelanggan?' . http_build_query([
                    'page'   => $n,
                    'segmen' => $segmen,
                    'status' => $status,
                    'tag_id' => $tagId,
                    'cari'   => $cari,
                ]));
            };

            // Jendela 5 nomor di sekitar halaman aktif, plus halaman pertama/terakhir
            // dengan penanda "..." supaya halaman terjauh tetap bisa diklik.
            $dari   = max(1, min($page - 2, $akhir - 4));
            $sampai = min($akhir, $dari + 4);

            $nomorHalaman = [];
            if ($dari > 1) {
                $nomorHalaman[] = 1;
                if ($dari > 2) {
                    $nomorHalaman[] = null;
                }
            }
            for ($n = $dari; $n <= $sampai; $n++) {
                $nomorHalaman[] = $n;
            }
            if ($sampai < $akhir) {
                if ($sampai < $akhir - 1) {
                    $nomorHalaman[] = null;
                }
                $nomorHalaman[] = $akhir;
            }
            ?>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3 crm-pager">
                <div class="small text-muted">
                    Halaman <?= $page ?> dari <?= $akhir ?> (Total <?= number_format($totalData) ?> data)
                </div>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1) : ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $tautanHalaman($page - 1) ?>">« Sebelumnya</a>
                        </li>
                    <?php else : ?>
                        <li class="page-item disabled">
                            <span class="page-link">« Sebelumnya</span>
                        </li>
                    <?php endif; ?>

                    <?php foreach ($nomorHalaman as $n) : ?>
                        <?php if ($n === null) : ?>
                            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                        <?php elseif ($n === $page) : ?>
                            <li class="page-item active" aria-current="page"><span class="page-link"><?= $n ?></span></li>
                        <?php else : ?>
                            <li class="page-item"><a class="page-link" href="<?= $tautanHalaman($n) ?>"><?= $n ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($page < $akhir) : ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $tautanHalaman($page + 1) ?>">Berikutnya »</a>
                        </li>
                    <?php else : ?>
                        <li class="page-item disabled">
                            <span class="page-link">Berikutnya »</span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
