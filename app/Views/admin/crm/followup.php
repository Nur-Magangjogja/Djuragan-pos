<?php
/**
 * Variabel di bawah diisi oleh Crm::followup() lewat view('admin/crm/followup', $data).
 * CI4 menyuntikkannya saat runtime melalui extract(), jadi Intelephense tidak bisa
 * menelusurinya — blok @var ini memberi tahu analyzer tanpa mengubah perilaku.
 *
 * @var string $title               judul halaman
 * @var string $tab                 tab aktif: tagihan|customer
 * @var string $cari                kata kunci pencarian dari query string (bisa string kosong)
 * @var string $status              filter status tab customer: menunggu|selesai|semua
 * @var int    $page                nomor halaman aktif (1-based)
 * @var int    $limit               baris per halaman
 * @var int    $totalPagesTagihan   total halaman daftar tagihan
 * @var int    $totalInvoicesTagihan total invoice belum lunas
 * @var array  $followupList        baris follow-up customer
 * @var array  $invoicesTagihan     baris invoice belum lunas
 * @var string $awal                batas bawah rentang tanggal pesan (Y-m-d, bisa kosong)
 * @var string $akhir               batas atas rentang tanggal pesan (Y-m-d, bisa kosong)
 * @var string $statusBarang        keranjang status aktif: belum|proses|packing|terkirim|''
 * @var array  $perStatus           jumlah per keranjang: total|belum|proses|packing|terkirim
 * @var string $templateTagihan     template pesan WA tagihan
 * @var array  $tahap               master tahap produksi (nomor => [ikon, nama, slug])
 * @var bool   $melihat_cs          layar dibuka akun CS: navbar CS, tanpa tab customer
 */
?>
<?php
// perakit query string untuk kartu status dan pager: saringan yang sedang aktif ikut
// terbawa, dan mengganti saringan selalu mengembalikan daftar ke halaman pertama
$tanya = static function (array $ubah): string {
    $q = array_merge($_GET, ['tab' => 'tagihan'], $ubah);
    if (array_intersect_key($ubah, array_flip(['cari', 'status_barang', 'awal', 'akhir']))) {
        unset($q['page']);
    }
    unset($q['status']); // milik tab customer, tidak boleh nebeng di tautan tagihan
    foreach (['tab', 'cari', 'status_barang', 'awal', 'akhir', 'page'] as $k) {
        if (isset($q[$k]) && (string) $q[$k] === '') {
            unset($q[$k]);
        }
    }
    if (($q['page'] ?? 1) == 1) {
        unset($q['page']);
    }
    return site_url('admin/crm/followup') . '?' . http_build_query($q);
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
/* judul, teks penjelasan dan tab di halaman ini dikecilkan */
.fup-judul {
    font-size: 1.15rem;
}
.fup-teks {
    font-size: 0.82rem !important;
}
/* kartu saringan status barang: seluruh kotak bisa diklik, warna aksen per
   keranjang dibawa lewat --fup-warna supaya border aktif dan angkanya satu rupa */
.fup-kartu-status {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    background: #fff;
    border: 1px solid #eaecf0;
    border-radius: 0.75rem;
    padding: 0.6rem 0.85rem;
    text-decoration: none;
    height: 100%;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.fup-kartu-status:hover {
    border-color: var(--fup-warna, #98a2b3);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}
.fup-kartu-status.aktif {
    border-color: var(--fup-warna, #101828);
    box-shadow: inset 0 0 0 1px var(--fup-warna, #101828);
    background: #f9fafb;
}
.fup-kartu-status .angka {
    font-size: 1.2rem;
    font-weight: 800;
    line-height: 1.1;
}
.fup-kartu-status .label {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #667085;
    min-width: 0;
}
.fup-kartu-status .label > i {
    flex: 0 0 auto;
}
.fup-kartu-status .label > span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.fup-tanggal {
    width: 145px;
}
/* saringan duduk di kepala kartu tabel: formnya membentang seukuran kartu,
   dan hanya kolom cari yang memanjang mengisi sisa ruang — tanggal, label,
   dan tombol Ulangi cukup seperlunya (flex 0) supaya proporsional */
.fup-saring {
    width: 100%;
}
.fup-saring .fup-cari {
    flex: 1 1 240px;
    min-width: 0;
}
.fup-saring > div,
.fup-saring > a {
    flex: 0 0 auto;
}
.fup-saring > div.d-flex {
    flex: 0 1 auto;
}
@media (max-width: 575.98px) {
    .fup-tanggal {
        width: 126px;
    }
    /* kartu saringan dua per baris di HP; kartu terakhir bisa menggantung setengah
       baris (mis. Terkirim), jadi ia melebar memenuhi ruang tersisa */
    .fup-kartu-row > div:last-child:nth-child(odd) {
        flex: 0 0 100%;
        max-width: 100%;
    }
}
/* Khusus mobile (<=768px). Tampilan desktop tidak diubah sama sekali:
   di atas lebar ini tabel tetap 7 kolom seperti sebelumnya.
   Di HP, tabel digeser ke samping membuat kolom kanannya tidak terbaca, jadi
   tiap baris ditumpuk jadi kartu dan setiap sel menampilkan label kolomnya
   (data-label), ditambah nama pelanggan yang boleh turun baris. */
/* Tabel Follow-up di Layar Desktop (>=768px): rapi, proporsional, dan teks tidak pecah/terpotong */
@media (min-width: 768px) {
    .fup-tabel th {
        white-space: nowrap !important;
        font-size: 0.76rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #475467;
        background-color: #f8fafc !important;
        padding: 0.85rem 1rem !important;
        vertical-align: middle;
        border-bottom: 1px solid #eaecf0 !important;
    }
    .fup-tabel td {
        padding: 0.85rem 1rem !important;
        vertical-align: middle;
        font-size: 0.875rem;
        border-bottom: 1px solid #f2f4f7;
    }
    .fup-tabel .text-nowrap {
        white-space: nowrap !important;
    }
    .btn-hubungi-tagihan {
        white-space: nowrap !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        padding: 0.4rem 0.85rem;
        font-weight: 600;
        border-radius: 6px;
    }
}
@media (max-width: 767.98px) {
    .fup-tabel thead {
        display: none;
    }
    .fup-tabel,
    .fup-tabel tbody,
    .fup-tabel td {
        display: block;
        width: 100%;
    }
    /* dua kolom: Pelanggan bersebelahan dengan Nomor WhatsApp,
       Total Dibayar dengan Sisa Piutang; sisanya satu baris penuh.
       minmax(0, 1fr) wajib: 1fr saja punya batas bawah "auto", jadi satu sel
       yang isinya panjang (nomor tagihan, tombol) memaksa kartunya melebar
       melewati layar dan halaman jadi bisa digeser ke samping. */
    .fup-tabel tr {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        column-gap: 0.9rem;
        border: 1px solid #eaecf0;
        border-radius: 10px;
        margin-bottom: 0.6rem;
        padding: 0.15rem 0.7rem;
    }
    .fup-tabel td:nth-child(1),
    .fup-tabel td:nth-child(4),
    .fup-tabel td:nth-child(7) {
        grid-column: 1 / -1;
    }
    .fup-tabel td {
        padding: 0.5rem 0 !important;
        border-bottom: 1px dashed #eaecf0 !important;
        box-shadow: none;
        text-align: left !important;
        white-space: normal !important;
        overflow-wrap: anywhere;
    }
    .fup-tabel tr td:last-child,
    .fup-tabel td:last-child,
    .fup-tabel td[data-label="Aksi"] {
        border-bottom: 0 !important;
    }
    /* Hover di mode kartu. style.css mewarnai SETIAP SEL saat baris disorot
       (.table tbody tr:hover td, !important), dan di mode kartu selnya tidak punya
       padding kiri-kanan — hasilnya blok abu-abu yang tidak menyentuh tepi kartu
       dengan garis putih di antaranya. Pewarnaan sel dimatikan, lalu kartunya
       (tr) yang disorot sekaligus. Dibungkus @media (hover: hover) supaya efeknya
       tidak menempel setelah disentuh di HP. */
    .fup-tabel tbody tr:hover td {
        background-color: transparent !important;
    }
    .fup-tabel tbody tr {
        background-color: #ffffff;
        transition: background-color 0.15s ease, border-color 0.15s ease;
    }
    @media (hover: hover) {
        .fup-tabel tbody tr:hover {
            background-color: #f9fafb;
            border-color: #d0d5dd;
        }
    }
    .fup-tabel td::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 0.15rem;
        font-size: 0.72rem !important;
        font-weight: 600;
        color: #98a2b3;
    }
    .fup-tabel td .btn {
        margin-right: 0.4rem;
    }
    /* Kolom "Aksi Tagih" cuma berisi tombol Hubungi, jadi ia ikut memanjang sebaris
       penuh di mode kartu: kalau tidak, tombolnya duduk di kolom kiri saja dan
       labelnya belah dua seperti di layar 360px. */
    .fup-tabel td[data-label="Aksi Tagih"] {
        grid-column: 1 / -1;
    }
    .fup-tabel td[data-label="Aksi Tagih"] .btn {
        width: 100%;
        margin-right: 0;
    }
    /* Wadahnya tidak boleh jadi area geser lagi di HP (kedua sumbu), tapi juga
       tidak boleh meluber: "visible" membuat isi yang kepanjangan ikut melebar
       ke kanan sampai melewati tepi layar (bar atas ikut terlihat terpotong).
       "hidden" menjaga semuanya di dalam bingkai. */
    .fup-halaman .table-responsive {
        overflow: hidden !important;
    }
    /* Judul + satu baris penjelasan di bawahnya boleh memotong di tengah kata. */
    .fup-halaman .fup-judul,
    .fup-halaman .fup-teks {
        overflow-wrap: anywhere;
        min-width: 0;
    }
    /* Semua paragraf penjelasan di halaman ini diberi ruang kanan yang sama,
       jadi barisnya berhenti sebelum tepi layar dan tidak terlihat "penuh". */
    .fup-halaman .fup-teks {
        padding-right: 0.9rem;
        line-height: 1.45;
    }
    /* Baris judul di HP disusun vertikal: judul + penjelasan duluan, aksi di
       kanan turun ke baris sendiri. Flex row bikin kotak teksnya
       melebar sampai sebaris penuh, jadi di sini dijadikan blok biasa. */
    .fup-judul-baris {
        /* !important karena baris ini membawa kelas BS d-flex (utility-nya
           !important, jadi tanpa !important aturan ini kalah) */
        display: block !important;
    }
    /* Seluruh isi halaman dirapatkan ke tepi kiri-kanan layar HP (band 768px
       ke bawah, bukan hanya 576px) supaya bar tab dan teks punya ruang lebih. */
    body.has-app-sidebar > div.container-xxl {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
    }
    /* Kotak cari ikut melebar penuh seperti tab di atasnya. width:100% saja
       tidak cukup: form-nya adalah flex item yang lebarnya mengikuti isi, jadi
       form-nya juga yang diminta mengambil satu baris penuh. */
    .fup-halaman form.d-flex {
        flex: 1 1 100%;
    }
    .fup-halaman .cari-merged {
        width: 100% !important;
    }
    /* Catatan follow-up memakai pre-wrap supaya barisnya terjaga, tapi token
       panjang (nomor WA / kode nota) tetap boleh dipotong agar tidak meluber. */
    .fup-halaman .fup-catatan {
        overflow-wrap: anywhere;
    }
}
@media (max-width: 575.98px) {
    .fup-judul {
        font-size: 1.05rem;
    }
    .fup-halaman .table thead th,
    .fup-halaman .table tbody td {
        padding: 0.5rem 0.6rem !important;
    }
}
/* tombol filter segmen boleh turun baris, tidak mendorong tabel ke samping.
   btn-group bawaan Bootstrap menempelkan tombol-tombolnya (margin-left: -1px
   dan hanya sudut terluar yang dibulatkan), jadi kedua tombol terlihat menyatu.
   Di sini jarak antar tombol dikembalikan dan tiap tombol dibulatkan sendiri
   supaya terbaca sebagai dua pilihan terpisah, termasuk saat turun baris. */
.fup-segmen {
    flex-wrap: wrap;
    gap: 0.5rem;
}
.fup-segmen > .btn {
    margin-left: 0 !important;
    border-radius: 0.375rem !important;
}
/* Kelas .cari-merged (kotak cari satu kolom) sekarang ada di style.css karena
   dipakai juga oleh halaman Pengiriman. */
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($melihat_cs ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl py-4 fup-halaman">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 fup-judul-baris">
        <div>
            <h4 class="mb-0 fw-bold fup-judul">
                <i class="fal fa-tasks text-primary me-2 d-none d-lg-inline"></i> Pusat Tagihan & Follow-up
            </h4>
            <p class="text-muted small mb-0 fup-teks"><?= $melihat_cs ? 'Kelola penagihan invoice yang belum lunas di toko yang kamu pegang.' : 'Kelola penagihan invoice yang belum lunas dan tindakan follow-up customer.' ?></p>
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

    <!-- chip tab lama sudah dilepas; tiap branch membawa kartunya sendiri supaya
         kartu status bisa duduk terpisah dari tabel di branch tagihan -->
    <!-- ================= FOLLOW-UP CUSTOMER ================= -->
    <?php if ($tab === 'customer' && ! $melihat_cs): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="btn-group btn-group-sm fup-segmen">
                        <a href="<?= site_url('admin/crm/followup?tab=customer&status=menunggu') ?>" class="btn <?= $status === 'menunggu' ? 'btn-primary fw-semibold' : 'btn-outline-secondary' ?>">
                            Menunggu Tindakan
                        </a>
                        <a href="<?= site_url('admin/crm/followup?tab=customer&status=selesai') ?>" class="btn <?= $status === 'selesai' ? 'btn-primary fw-semibold' : 'btn-outline-secondary' ?>">
                            Riwayat Selesai
                        </a>
                        <a href="<?= site_url('admin/crm/followup?tab=customer&status=semua') ?>" class="btn <?= $status === 'semua' ? 'btn-primary fw-semibold' : 'btn-outline-secondary' ?>">
                            Semua
                        </a>
                    </div>
                    <form action="<?= site_url('admin/crm/followup') ?>" method="get" class="d-flex gap-2">
                        <input type="hidden" name="tab" value="customer">
                        <input type="hidden" name="status" value="<?= esc($status) ?>">
                        <div class="cari-merged" style="width: 250px; max-width: 100%;">
                            <button type="submit" class="cari-kirim" title="Cari"><i class="fal fa-search"></i></button>
                            <input type="text" name="cari" class="form-control form-control-sm" placeholder="Cari nama atau judul..." value="<?= esc($cari) ?>">
                        </div>
                    </form>
                </div>

                <?php if (empty($followupList)): ?>
                    <?php if (trim((string) $cari) !== ''): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-search fa-3x mb-3 text-secondary opacity-50"></i>
                            <h5>Tidak ada hasil untuk "<?= esc($cari) ?>"</h5>
                            <p class="mb-0">Tidak ketemu follow-up yang cocok dengan kata itu pada bagian ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-clipboard-check fa-3x mb-3 text-secondary opacity-50"></i>
                            <h5>Tidak ada follow-up customer</h5>
                            <p class="mb-0">Semua tugas follow-up staff telah terselesaikan atau belum ada jadwal baru.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle border fup-tabel mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 140px;">Jatuh Tempo</th>
                                    <th style="min-width: 180px;">Customer</th>
                                    <th style="min-width: 150px;">Kategori Tindakan</th>
                                    <th>Judul & Instruksi</th>
                                    <th class="text-center" style="min-width: 110px;">Status</th>
                                    <th style="min-width: 120px;">Staff PIC</th>
                                    <th class="text-center" style="min-width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($followupList as $f): ?>
                                    <tr>
                                        <td data-label="Jatuh Tempo" class="text-nowrap">
                                            <span class="fw-bold <?= $f['tanggal_jatuh_tempo'] < date('Y-m-d') && $f['status'] === 'menunggu' ? 'text-danger' : 'text-dark' ?>">
                                                <?= date('d/m/Y', strtotime($f['tanggal_jatuh_tempo'])) ?>
                                            </span>
                                            <?php if ($f['tanggal_jatuh_tempo'] < date('Y-m-d') && $f['status'] === 'menunggu'): ?>
                                                <span class="badge bg-danger ms-1">Terlambat</span>
                                            <?php elseif ($f['tanggal_jatuh_tempo'] === date('Y-m-d') && $f['status'] === 'menunggu'): ?>
                                                <span class="badge bg-warning text-dark ms-1">Hari Ini</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Customer">
                                            <a href="<?= site_url('admin/crm/pelanggan/' . $f['pelanggan_id']) ?>" class="fw-bold text-decoration-none text-primary">
                                                <?= esc($f['nama_pelanggan']) ?>
                                            </a>
                                            <?php if (!empty($f['nomor_wa'])): ?>
                                                <div class="small text-muted text-nowrap">
                                                    <i class="fab fa-whatsapp text-success me-1"></i><?= esc($f['nomor_wa']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Kategori Tindakan" class="text-nowrap">
                                            <span class="badge bg-light text-dark border">
                                                <?= ucfirst(str_replace('_', ' ', $f['kategori'])) ?>
                                            </span>
                                        </td>
                                        <td data-label="Judul & Instruksi">
                                            <div class="fw-semibold text-dark"><?= esc($f['judul']) ?></div>
                                            <?php if (!empty($f['catatan'])): ?>
                                                <div class="small text-muted fup-catatan" style="white-space: pre-wrap;"><?= esc($f['catatan']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Status" class="text-center text-nowrap">
                                            <?php if ($f['status'] === 'selesai'): ?>
                                                <span class="badge bg-success"><i class="fal fa-check me-1"></i> Selesai</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><i class="fal fa-clock me-1"></i> Menunggu</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted text-nowrap" data-label="Staff PIC">
                                            <?= esc($f['nama_staff'] ?? 'Staff') ?>
                                        </td>
                                        <td class="text-center text-nowrap" data-label="Aksi">
                                            <a href="<?= site_url('admin/crm/pelanggan/' . $f['pelanggan_id']) ?>" class="btn btn-sm btn-outline-primary me-2" title="Buka Profil 360">
                                                <i class="fal fa-id-card"></i>
                                            </a>
                                            <?php if ($f['status'] === 'menunggu'): ?>
                                                <form action="<?= site_url('admin/crm/followup/selesai/' . $f['id_followup']) ?>" method="post" class="d-inline" onsubmit="return confirm('Tandai follow-up ini telah selesai?');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-sm btn-success" title="Tandai Selesai">
                                                        <i class="fal fa-check"></i>
                                                    </button>
                                                </form>
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
    <?php else: ?>
    <!-- ================= TAGIHAN ================= -->
                <?php
                // kartu saringan status barang; kodenya sama dengan keranjangStatusBarang()
                // di model, string kosong berarti tanpa saring (Semua Tagihan)
                $kartuStatus = [
                    ''         => ['Semua Tagihan',    'fa-receipt',    '#0f172a'],
                    'belum'    => ['Belum Diproses',   'fa-inbox',      '#64748b'],
                    'proses'   => ['Dalam Pengerjaan', 'fa-spinner',    '#b45309'],
                    'packing'  => ['Sudah Packing',    'fa-box-check',  '#0369a1'],
                    'terkirim' => ['Terkirim',         'fa-truck-fast', '#14663a'],
                ];
                ?>
                <!-- kartu saringan duduk terpisah, di luar kartu tabel -->
                <div class="row g-2 mb-3 fup-kartu-row">
                    <?php foreach ($kartuStatus as $kode => [$nama, $ikon, $warna]) : ?>
                        <div class="col-6 col-sm-4 col-lg">
                            <a class="fup-kartu-status <?= $statusBarang === $kode ? 'aktif' : '' ?>"
                                style="--fup-warna: <?= $warna ?>"
                                href="<?= esc($tanya(['status_barang' => $kode])) ?>">
                                <span class="angka" style="color: <?= $warna ?>"><?= number_format((int) ($perStatus[$kode === '' ? 'total' : $kode] ?? 0)) ?></span>
                                <span class="label"><i class="fal <?= $ikon ?>"></i> <span><?= $nama ?></span></span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- saringan jadi satu dengan tabel: paling atas di dalam kartu -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <form action="<?= site_url('admin/crm/followup') ?>" method="get" class="d-flex flex-wrap align-items-stretch gap-2 fup-saring">
                        <input type="hidden" name="tab" value="tagihan">
                        <?php if ($statusBarang !== '') : ?>
                            <input type="hidden" name="status_barang" value="<?= esc($statusBarang) ?>">
                        <?php endif; ?>
                        <div class="d-flex align-items-center gap-1">
                            <label class="small text-muted mb-0 text-nowrap d-none d-sm-inline" for="fup-awal">Tanggal</label>
                            <input type="date" id="fup-awal" name="awal" class="form-control form-control-sm fup-tanggal"
                                value="<?= esc($awal) ?>" max="<?= esc($akhir) ?>" title="Tanggal pesan dari">
                            <span class="text-muted small">s.d.</span>
                            <input type="date" id="fup-akhir" name="akhir" class="form-control form-control-sm fup-tanggal"
                                value="<?= esc($akhir) ?>" min="<?= esc($awal) ?>" title="Sampai tanggal pesan">
                        </div>
                        <div class="cari-merged fup-cari">
                            <button type="submit" class="cari-kirim" title="Cari"><i class="fal fa-search"></i></button>
                            <input type="text" name="cari" class="form-control form-control-sm" placeholder="Cari invoice / pelanggan..." value="<?= esc($cari) ?>">
                        </div>
                        <?php if ($cari !== '' || $statusBarang !== '' || $awal !== '' || $akhir !== '') : ?>
                            <a class="btn btn-sm btn-outline-secondary text-nowrap" href="<?= esc(site_url('admin/crm/followup')) ?>" title="Kosongkan semua saringan">Ulangi</a>
                        <?php endif; ?>
                    </form>
                </div>

                <p class="text-muted small mb-3 fup-teks">
                    Seluruh invoice yang belum lunas (Kredit, Belum Bayar, atau Menunggu Konfirmasi),
                    termasuk yang barangnya masih dalam pengerjaan. Yang sudah selesai ditaruh lebih dulu.
                    <?php if (!empty($totalInvoicesTagihan) && $totalInvoicesTagihan > count($invoicesTagihan)): ?>
                        Menampilkan <?= count($invoicesTagihan) ?> dari <strong><?= number_format($totalInvoicesTagihan) ?></strong> invoice.
                    <?php endif; ?>
                </p>

                <?php if (empty($invoicesTagihan)): ?>
                    <?php if (trim((string) $cari) !== ''): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-search fa-3x mb-3 text-secondary opacity-50"></i>
                            <h5>Tidak ada hasil untuk "<?= esc($cari) ?>"</h5>
                            <p class="mb-0">Tidak ketemu invoice yang cocok dengan kata itu. Cek ejaan namanya atau nomornya.</p>
                        </div>
                    <?php elseif ($statusBarang !== '' || $awal !== '' || $akhir !== ''): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-inbox fa-3x mb-3 text-secondary opacity-50"></i>
                            <h5>Kosong pada saringan ini</h5>
                            <p class="mb-0">Tidak ada invoice yang cocok dengan saringan tanggal atau status barang sekarang.
                                <a href="<?= esc(site_url('admin/crm/followup')) ?>">Lihat semua tagihan</a>.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-check-circle fa-3x mb-3 text-success"></i>
                            <h5>Semua Tagihan Lunas!</h5>
                            <p class="mb-0">Tidak ada piutang invoice pending saat ini.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle border fup-tabel mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 140px;">Invoice</th>
                                    <th style="min-width: 180px;">Pelanggan</th>
                                    <th class="text-center" style="min-width: 130px;">Status Barang</th>
                                    <th style="min-width: 150px;">Nomor WhatsApp</th>
                                    <th class="text-end" style="min-width: 130px;">Total Tagihan</th>
                                    <th class="text-end" style="min-width: 130px;">Telah Dibayar</th>
                                    <th class="text-end" style="min-width: 130px;">Sisa Piutang</th>
                                    <th class="text-center" style="min-width: 140px;">Aksi Tagih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoicesTagihan as $inv): ?>
                                    <tr>
                                        <td data-label="Invoice" class="text-nowrap">
                                            <span class="fw-bold text-dark">#<?= esc($inv['seri']) ?></span>
                                            <div class="small text-muted"><?= date('d/m/Y', strtotime($inv['tanggal_pesan'])) ?></div>
                                        </td>
                                        <td data-label="Pelanggan">
                                            <a href="<?= site_url('admin/crm/pelanggan/' . $inv['id_pelanggan']) ?>" class="fw-bold text-decoration-none text-primary">
                                                <?= esc($inv['nama_pelanggan']) ?>
                                            </a>
                                            <div class="small text-muted"><?= esc($inv['nama_juragan']) ?></div>
                                        </td>
                                        <td data-label="Status Barang" class="text-center text-nowrap">
                                            <?php
                                            // penanda yang sama dengan tab "Siap Diambil" di Tracking
                                            // Transaksi: tahap packing selesai, atau paket sudah dikirim
                                            if ((string) ($inv['status_pengiriman'] ?? '') === '3') {
                                                $wama = 'success'; $teks = 'Terkirim';
                                            } elseif ((int) ($inv['packing_selesai'] ?? 0) === 1) {
                                                $wama = 'success'; $teks = 'Sudah Packing';
                                            } elseif ((int) ($inv['tahap'] ?? 0) > 0) {
                                                $wama = 'warning text-dark'; $teks = 'Tahap: ' . ($tahap[(int) $inv['tahap']][1] ?? '-');
                                            } else {
                                                $wama = 'secondary'; $teks = 'Belum diproses';
                                            }
                                            ?>
                                            <span class="badge bg-<?= $wama ?>"><?= esc($teks) ?></span>
                                        </td>
                                        <td data-label="Nomor WhatsApp" class="text-nowrap">
                                            <?php if (!empty($inv['nomor_hp'])): ?>
                                                <i class="fab fa-whatsapp text-success me-1"></i><span class="font-monospace"><?= esc($inv['nomor_hp']) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Tanpa Nomor</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-semibold text-nowrap" data-label="Total Tagihan">
                                            Rp <?= number_format($inv['total_tagihan'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-end text-success text-nowrap" data-label="Telah Dibayar">
                                            Rp <?= number_format($inv['telah_dibayar'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-end text-danger fw-bold text-nowrap" data-label="Sisa Piutang">
                                            Rp <?= number_format($inv['sisa_tagihan'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-center text-nowrap" data-label="Aksi Tagih">
                                            <?php if (!empty($inv['nomor_hp'])): ?>
                                                <?php
                                                // Hubungi = langsung geser ke WA pembeli; draf template tagihan
                                                // ikut terisi di kolom chat, tanpa langkah perantara lagi.
                                                $pesanWa = str_replace(
                                                    ['{nama}', '{invoice}', '{total}', '{sisa}', '{link_invoice}'],
                                                    [$inv['nama_pelanggan'], '#' . $inv['seri'], 'Rp ' . number_format($inv['total_tagihan'], 0, ',', '.'), 'Rp ' . number_format($inv['sisa_tagihan'], 0, ',', '.'), site_url('download/invoice/' . $inv['seri'])],
                                                    (string) ($templateTagihan ?? '')
                                                );
                                                $tautanWa = 'https://wa.me/' . nomor_wa_link($inv['nomor_hp'])
                                                    . ($pesanWa !== '' ? '?text=' . rawurlencode($pesanWa) : '');
                                                ?>
                                                <a class="btn btn-sm btn-outline-success fw-semibold text-nowrap btn-hubungi-tagihan"
                                                        href="<?= esc($tautanWa) ?>" target="_blank" rel="noopener">
                                                    <i class="fab fa-whatsapp"></i> Hubungi
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($totalPagesTagihan) && $totalPagesTagihan > 1) : ?>
                        <div class="d-flex flex-wrap justify-content-between align-items-center pt-3 border-top mt-3 crm-pager">
                            <div class="small text-muted mb-2 mb-md-0">
                                Halaman <?= $page ?> dari <?= $totalPagesTagihan ?> (Total <?= number_format($totalInvoicesTagihan) ?> invoice pending)
                            </div>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($page > 1) : ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= esc($tanya(['page' => $page - 1])) ?>">« Sebelumnya</a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start = max(1, $page - 2);
                                $end   = min($totalPagesTagihan, $page + 2);
                                for ($i = $start; $i <= $end; $i++) : ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= esc($tanya(['page' => $i])) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPagesTagihan) : ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= esc($tanya(['page' => $page + 1])) ?>">Berikutnya »</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                    </div>
                </div>
    <?php endif; ?>
</div>



<?= $this->endSection() ?>
