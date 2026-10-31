<?php
/**
 * Variabel di bawah disuntik runtime oleh CodeIgniter lewat extract() di
 * dalam App\Controllers\Admin\Kpi::ranking(); Intelephense tidak bisa
 * menelusurinya sehingga menyangka P1008. Blok @var ini hanya penanda tipe
 * untuk analyzer, bukan kode yang dijalankan. $s, $periode, $peran,
 * $pegawai, dan $juragans dipakai juga oleh admin/kpi/saring karena include
 * memakai scope yang sama.
 *
 * @var string $title    judul halaman
 * @var array  $s        nilai saringan aktif: ['awal', 'akhir', 'periode', 'peran', 'user_id', 'juragan_id', ...]
 * @var list   $peringkat baris peringkat per pegawai: ['user_id', 'pegawai', 'level', 'jumlah', 'poin', 'total', 'gmv', 'unit', 'tercatat', 'peringkat']
 * @var list   $baris    halaman peringkat yang sedang ditampilkan (irisan $peringkat)
 * @var int    $jumlah   total orang yang dinilai (sebelum diiris halaman)
 * @var int    $batas    jumlah baris per halaman
 * @var int    $halaman  indeks halaman aktif, dimulai dari 0
 * @var list   $aturan   aturan poin per aktivitas: ['slug', 'nama', 'sumber', 'poin', 'satuan', 'tercatat']
 * @var array  $luar     sumber data yang tidak dinilai per orang: [sumber => pekerjaan]
 * @var array  $pegawai  pilihan pegawai untuk saringan: [user_id => 'nama (level)']
 * @var array  $juragans pilihan toko milik akun ini: [juragan_id => nama]
 * @var array  $periode  pilihan periode saringan: [slug => label]
 * @var array  $peran    pilihan peran saringan: [slug => label]
 */

/*
 * Peringkat karyawan dari poin aktivitas.
 *
 * Tabelnya dibaca dari atas ke bawah: semakin banyak kerja yang tercatat dan
 * semakin besar nilai nota yang dilunasi, semakin tinggi poinnya. Aturan poin
 * dan daftar aktivitas yang tidak dinilai dibuka lewat popup, jadi halaman ini
 * hanya berisi angkanya.
 */

$angka = static fn ($n): string => number_format((float) $n, fmod((float) $n, 1.0) === 0.0 ? 0 : 1, ',', '.');
$poin  = static fn ($n): string => ($n <=> 0) > 0 ? '+' . $angka($n) : $angka($n);
$sel   = static fn (int $n): string => $n === 0 ? '&mdash;' : $angka($n);

// kelompok kolom supaya tabel tidak perlu banyak kolom sekaligus. Kolom Chat,
// Konversi dan poin broadcast sudah dicabut bersama fitur Live Chat WA / Broadcast.
// Kolom Nilai dan Lainnya ikut dihapus (31 Oktober 2026): keempat aturannya
// (nilai_lunas, tindak_lanjut_selesai, aktivitas_crm, pesan_manual) juga tidak
// dihitung lagi, jadi total POIN di bawah hanya jumlah kolom yang terlihat.
$kelompok = [
    'Nota'      => ['nota_dibuat'],
    'Lunas'     => ['nota_lunas'],
    'Produksi'  => ['tahap_produksi', 'foto_progres'],
    'Pengurang' => ['void_salah'],
];

$petunjuk = [
    'Nota'      => 'nota dibuat',
    'Lunas'     => 'nota lunas',
    'Produksi'  => 'tahap produksi dikerjakan + foto progres',
    'Pengurang' => 'nota dibatalkan karena kesalahan',
];

$nama_slug = array_column($aturan, 'nama', 'slug');

// tautan halaman: filter yang sedang aktif selalu ikut dibawa, sama seperti di
// halaman Ringkasan
$tautan = static function (array $ubah) use ($s): string {
    $q = array_merge([
        'awal'    => $s['awal'],
        'akhir'   => $s['akhir'],
        'periode' => $s['periode'],
        'peran'   => $s['peran'],
        'pegawai' => $s['user_id'],
        'juragan' => $s['juragan_id'],
    ], $ubah);

    return site_url('admin/kpi/ranking') . '?' . http_build_query(array_filter(
        $q,
        static fn ($v) => $v !== null && $v !== '' && $v !== 0
    ));
};

$total_halaman = max(1, (int) ceil($jumlah / $batas));
$nomor         = $halaman + 1;
$awal_judul    = $jumlah === 0 ? 0 : $halaman * $batas + 1;
$akhir_judul   = min($jumlah, $halaman * $batas + $batas);

$maks     = 0.0;
$tercatat = 0;
foreach ($peringkat as $b) {
    $maks = max($maks, (float) $b['total']);
}
foreach ($aturan as $a) {
    $tercatat += (int) $a['tercatat'];
}

$teratas   = $peringkat[0]['total'] ?? 0.0;
$bernilai  = array_filter($peringkat, static fn ($b) => array_sum($b['jumlah']) > 0);
$kosong    = count($peringkat) - count($bernilai);
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
/* Tabel "Aturan Poin" butuh 572px, sedangkan popusnya di ponsel/tablet cuma
   280-451px, jadi isinya harus digeser ke samping dan kolom "Sumber data"
   terpotong. Di bawah 992px tiap baris ditumpuk jadi satu kartu dengan label
   kolom dari data-label — sama seperti mode kartu di halaman CRM. Modal-lg baru
   tercapai di >=992px, jadi tampilan desktop tidak berubah sama sekali. */
@media (max-width: 991.98px) {
    /* Isi popus ini panjang (13 baris aturan), jadi jangan memakan seluruh
       tinggi layar: dibatasi 80vh dan sisanya digulir di dalamnya
       (modal-dialog-scrollable sudah memberi overflow pada .modal-body). */
    #modalAturanPoin .modal-content {
        max-height: 80vh;
    }
    /* Wadah tabelnya sendiri jangan jadi papan putih kedua di dalam popus */
    #modalAturanPoin .table-responsive {
        overflow: visible;
        border: 0;
        background: transparent;
    }
    #modalAturanPoin table,
    #modalAturanPoin tbody,
    #modalAturanPoin tr,
    #modalAturanPoin td {
        display: block;
        width: 100%;
    }
    #modalAturanPoin thead {
        display: none;
    }
    #modalAturanPoin tbody tr {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        column-gap: 0.6rem;
        border: 1px solid #eaecf0;
        border-radius: 10px;
        margin-bottom: 0.6rem;
        padding: 0.1rem 0.7rem;
    }
    /* Nama aktivitas dan sumber data butuh lebar penuh; dua angkanya berbagi
       satu baris supaya kartu tidak memanjang ke bawah */
    #modalAturanPoin tbody td:nth-child(1),
    #modalAturanPoin tbody td:nth-child(2) {
        grid-column: 1 / -1;
    }
    /* .table tbody td di style.css memakai padding dan border-bottom !important,
       jadi keduanya harus dilawan dengan !important juga */
    #modalAturanPoin tbody td {
        padding: 0.3rem 0 !important;
        border-bottom: 1px dashed #eaecf0 !important;
        text-align: left !important;
        white-space: normal !important;
        overflow-wrap: anywhere;
    }
    #modalAturanPoin tbody tr td:last-child {
        border-bottom: 0 !important;
    }
    /* Label kolom duduk SEBARIS dengan isinya ("Poin per satuan: +2 / nota"),
       bukan di baris sendiri — inilah yang memotong tinggi kartu hampir separuh */
    #modalAturanPoin tbody td[data-label]::before {
        content: attr(data-label) ": ";
        font-size: 0.72rem;
        font-weight: 600;
        color: #98a2b3;
    }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">Ranking Karyawan</h1>

    <div class="row gx-2 gy-3 mb-3">
        <?php
        $kartu = [
            ['Karyawan dinilai', $angka(count($peringkat)), 'akun Customer Service yang masih aktif'],
            ['Poin tertinggi', $teratas === 0.0 ? '&mdash;' : $angka($teratas), $peringkat[0]['pegawai'] ?? '&mdash;'],
            ['Aktivitas tercatat', $angka($tercatat), 'jumlah satuan kerja yang masuk rumus poin'],
            ['Belum ada jejak', $angka($kosong), 'poin 0 karena aktivitasnya tidak tercatat atas namanya'],
        ];
        foreach ($kartu as [$label, $nilai, $bawah]) { ?>
            <div class="col-6 col-lg-3">
                <div class="h-100 rounded-3 border border-ink-200 bg-white px-3 py-2">
                    <div class="text-muted small text-uppercase"><?= esc($label) ?></div>
                    <div class="fs-5 fw-bold"><?= $nilai ?></div>
                    <div class="small text-muted"><?= esc($bawah) ?></div>
                </div>
            </div>
        <?php } ?>
    </div>

    <?= $this->include('admin/kpi/saring') ?>

    <div class="card mb-4">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold small">
                Peringkat <?= esc($s['awal']) ?> &ndash; <?= esc($s['akhir']) ?>
                <span class="text-muted fw-normal">&middot; <?= esc($peran[$s['peran']]) ?></span>
                <?php if ($jumlah > 0) { ?>
                    <span class="text-muted fw-normal">&middot; <?= $awal_judul ?> sampai <?= $akhir_judul ?> dari <?= (int) $jumlah ?> orang</span>
                <?php } ?>
            </span>
            <a class="small" href="#" data-bs-toggle="modal" data-bs-target="#modalAturanPoin">
                <i class="fal fa-circle-info me-1"></i>Aturan poin
            </a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Karyawan</th>
                        <?php foreach (array_keys($kelompok) as $kel) { ?>
                            <th scope="col" class="text-center" title="<?= esc($petunjuk[$kel]) ?>"><?= esc($kel) ?></th>
                        <?php } ?>
                        <th scope="col" class="text-end">Poin</th>
                        <th scope="col" class="text-end">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($baris as $b) { ?>
                        <tr>
                            <td class="fw-semibold text-nowrap">
                                <?= (int) $b['peringkat'] ?>
                                <?php if ((float) $b['total'] > 0 && (int) $b['peringkat'] <= 3) { ?>
                                    <i class="fal fa-trophy text-warning ms-1"></i>
                                <?php } ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= esc($b['pegawai']) ?></div>
                                <div class="small text-muted"><?= esc($b['level']) ?></div>
                            </td>
                            <?php foreach ($kelompok as $kel => $slugs) { ?>
                                <?php
                                $satuan = 0;
                                $jumlah = 0.0;
                                $rincian = [];
                                foreach ($slugs as $slug) {
                                    $satuan += (int) $b['jumlah'][$slug];
                                    $jumlah += (float) $b['poin'][$slug];
                                    $rincian[] = $nama_slug[$slug] . ': ' . $angka((int) $b['jumlah'][$slug]);
                                }
                                ?>
                                <td class="text-center" title="<?= esc(implode(', ', $rincian)) ?>">
                                    <div><?= $satuan === 0 ? '&mdash;' : $angka($satuan) ?></div>
                                    <?php if ($jumlah != 0.0) { ?>
                                        <div class="small text-muted"><?= $poin($jumlah) ?></div>
                                    <?php } ?>
                                </td>
                            <?php } ?>
                            <td class="text-end">
                                <div class="fw-semibold <?= (float) $b['total'] < 0 ? 'text-danger' : '' ?>">
                                    <?= $b['tercatat'] === 0 ? '&mdash;' : $angka($b['total']) ?>
                                </div>
                                <?php if ($maks > 0 && (float) $b['total'] > 0) { ?>
                                    <div class="progress mt-1 ms-auto" style="height: 3px; width: 64px;">
                                        <div class="progress-bar" style="width: <?= round((float) $b['total'] / $maks * 100) ?>%"></div>
                                    </div>
                                <?php } ?>
                            </td>
                            <td class="text-end small text-muted">
                                <?php if ($b['tercatat'] === 0) { ?>
                                    tidak ada aktivitas tercatat
                                <?php } elseif ((int) $b['unit'] > 0) { ?>
                                    <!-- pcs katalog nota lunas, sama dengan indikator GMV -->
                                    <?= $angka($b['unit']) ?> pcs lunas
                                <?php } else { ?>
                                    belum ada pcs katalog lunas
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if ($baris === []) { ?>
                        <tr>
                            <td colspan="<?= 4 + count($kelompok) ?>">
                                Tidak ada akun yang dinilai pada saringan ini.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_halaman > 1) { ?>
            <div class="card-footer bg-white">
                <nav aria-label="Halaman peringkat">
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= $total_halaman; $i++) { ?>
                            <?php if ($i === 1 || $i === $total_halaman || abs($i - $nomor) <= 2) { ?>
                                <li class="page-item <?= $i === $nomor ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $tautan(['halaman' => $i]) ?>"><?= $i ?></a>
                                </li>
                            <?php } elseif (abs($i - $nomor) === 3) { ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            <?php } ?>
                        <?php } ?>
                    </ul>
                </nav>
            </div>
        <?php } ?>
    </div>
</div>

<!-- Popup aturan poin: rumus, sumber data, dan kerja yang tidak bisa dinilai -->
<div class="modal fade" id="modalAturanPoin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fal fa-circle-info me-2"></i>Aturan Poin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Poin yang sama mendapat peringkat yang sama. Nota yang dibatalkan tetap
                    memberi poin pada tahap produksi dan fotonya karena kerjanya memang terjadi.
                    Angkanya adalah yang berlaku sekarang; Superadmin boleh menggantinya di
                    halaman Target KPI.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-4">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Aktivitas</th>
                                <th scope="col">Sumber data</th>
                                <th scope="col" class="text-end">Poin per satuan</th>
                                <th scope="col" class="text-end">Tercatat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aturan as $a) { ?>
                                <tr>
                                    <td>
                                        <div><?= esc($a['nama']) ?></div>
                                        <?php if (isset($a['catatan'])) { ?>
                                            <div class="small text-muted"><?= esc($a['catatan']) ?></div>
                                        <?php } ?>
                                    </td>
                                    <td class="small text-muted" data-label="Sumber data"><code><?= esc($a['sumber']) ?></code></td>
                                    <td class="text-end" data-label="Poin per satuan">
                                        <?= $poin((float) $a['poin']) ?>
                                        <span class="small text-muted"><?= esc($a['satuan']) ?></span>
                                        <?php if (isset($a['poin_baku']) && abs((float) $a['poin'] - (float) $a['poin_baku']) > 0.001) { ?>
                                            <div class="small text-muted">bawaan <?= $poin((float) $a['poin_baku']) ?></div>
                                        <?php } ?>
                                    </td>
                                    <td class="text-end" data-label="Tercatat">
                                        <?= $sel((int) $a['tercatat']) ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <h6 class="fw-semibold small">Yang tidak masuk poin</h6>
                <p class="small text-muted mb-2">
                    Dikerjakan lewat halaman yang menyimpan waktunya tetapi tidak menyimpan
                    siapa yang mengerjakan, jadi tidak bisa dinilai per orang:
                </p>
                <ul class="small mb-0">
                    <?php foreach ($luar as $sumber => $kerja) { ?>
                        <li><code><?= esc($sumber) ?></code> &mdash; <?= esc($kerja) ?></li>
                    <?php } ?>
                </ul>
                <p class="small text-muted mt-3 mb-0">
                    Kualitas kerja (SLA pemenuhan, kecepatan balas, ketepatan pembatalan, waktu
                    penyelesaian) dinilai di halaman Ringkasan dan tidak digabung ke poin.
                    Kolom <strong>&mdash;</strong> berarti jejaknya tidak ada di database, bukan
                    berarti orangnya tidak bekerja.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
