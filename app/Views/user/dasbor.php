<?php
/**
 * Dasbor kerja CS.
 *
 * Variabel di bawah diisi User\Dasbor::index() lewat view('user/dasbor', $data);
 * CI4 meng-extract array itu saat render, jadi blok @var ini hanya penanda tipe
 * untuk analyzer (P1008), bukan kode yang dijalankan.
 *
 * @var string                   $title     judul tab browser
 * @var array<string, int|float> $hari      ringkasan transaksi satu hari
 * @var array<string, int>       $saringan  jumlah order per saringan
 * @var int|float                $sisa      total sisa tagihan rupiah
 * @var string                   $sasaran   slug toko tujuan kartu, '' bila akun belum ditautkan
 * @var list<string>             $nama_toko nama toko yang dipegang
 * @var array<string, list<array>> $sop_grup  dokumen aktif, dibagi ['SOP' => [...], 'PANDUAN' => [...]]
 * @var bool                     $sop_kelola  selalu false di dasbor CS: tidak ada tombol sunting/hapus
 * @var bool                     $sop_modal   panel diklik untuk membuka lapisan layar penuh
 * @var int                      $sop_tampil  dokumen yang tetap terlihat di panel sebelum dibuka
 * @var list<array>              $pegangan  tugas terbuka yang sedang dipegang akun ini
 * @var list<array>              $antrean   tugas di antrean untuk tokonya
 * @var array<string, array>     $meta      label status/jenis/prioritas/untuk
 */

$nama_hari  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$nama_bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
    'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$tanggal_hari_ini = $nama_hari[(int) date('w')] . ', ' . date('j') . ' '
    . $nama_bulan[(int) date('n')] . ' ' . date('Y');

$saring = saring_transaksi();

// kartu menunjuk ke daftar transaksi CS; rute 'lihat' menuntut slug toko, jadi
// akun tanpa relasi hanya mendapat tautan ke halaman depannya
$tautan_saring = static function (array $pil) use ($sasaran): string {
    return $sasaran === ''
        ? 'user/invoices'
        : 'user/invoices/lihat/' . $sasaran . '/' . $pil[0] . ($pil[1] === '' ? '' : '/' . $pil[1]);
};

// tanggal tenggat: merah kalau sudah lewat, "hari ini" kalau jatuh tempo sekarang
$label_tenggat = static function (?string $tenggat): array {
    if (empty($tenggat)) {
        return ['teks' => 'tanpa tenggat', 'mendesak' => false];
    }

    $hari_ini = date('Y-m-d');

    if ($tenggat < $hari_ini) {
        return [
            'teks' => 'terlambat ' . (int) floor((strtotime($hari_ini) - strtotime($tenggat)) / 86400) . ' hari',
            'mendesak' => true,
        ];
    }

    return [
        'teks' => $tenggat === $hari_ini ? 'hari ini' : date('j M Y', strtotime($tenggat)),
        'mendesak' => false,
    ];
};

$kartu = [
    [
        'label'  => $saring['pesan-hari-ini']['label'],
        'nilai'  => (string) $saringan['pesan-hari-ini'],
        'ket'    => 'Rp ' . formatRibuan($hari['total']) . ' · ' . $hari['pelanggan'] . ' pelanggan',
        'ikon'   => $saring['pesan-hari-ini']['ikon'],
        'tautan' => $tautan_saring($saring['pesan-hari-ini']['tautan']),
    ],
    [
        'label'  => $saring['aktif']['label'],
        'nilai'  => (string) $saringan['aktif'],
        'ket'    => $saringan['belum-diproses'] . ' belum mulai dikerjakan',
        'ikon'   => $saring['aktif']['ikon'],
        'tautan' => $tautan_saring($saring['aktif']['tautan']),
    ],
    [
        'label'    => $saring['belum-lunas']['label'],
        'nilai'    => (string) $saringan['belum-lunas'],
        'ket'      => 'Sisa tagihan Rp ' . formatRibuan($sisa),
        'ikon'     => $saring['belum-lunas']['ikon'],
        'tautan'   => $tautan_saring($saring['belum-lunas']['tautan']),
        'mendesak' => true,
    ],
    [
        'label'  => $saring['produksi']['label'],
        'nilai'  => (string) $saringan['produksi'],
        'ket'    => 'Antrean kerja berjalan',
        'ikon'   => $saring['produksi']['ikon'],
        'tautan' => $tautan_saring($saring['produksi']['tautan']),
    ],
    [
        'label'    => $saring['deadline-3-hari']['label'],
        'nilai'    => (string) $saringan['deadline-3-hari'],
        'ket'      => $saringan['deadline-terlewat'] . ' sudah terlewat',
        'ikon'     => $saring['deadline-3-hari']['ikon'],
        'tautan'   => $tautan_saring($saring['deadline-3-hari']['tautan']),
        'mendesak' => $saringan['deadline-terlewat'] > 0,
    ],
    [
        'label'  => $saring['siap-diambil']['label'],
        'nilai'  => (string) $saringan['siap-diambil'],
        'ket'    => 'Packing selesai, menunggu pelanggan',
        'ikon'   => $saring['siap-diambil']['ikon'],
        'tautan' => $tautan_saring($saring['siap-diambil']['tautan']),
    ],
];
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    /* Dua panel bawah setinggi mungkin sama; daftar di dalamnya yang menggulir,
       bukan kartunya, supaya kolom kanan tidak ikut memanjang */
    .dasbor-isi {
        max-height: 26rem;
        overflow-y: auto;
    }

    .dasbor-baris {
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
        padding: 0.6rem 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .dasbor-baris:last-child {
        border-bottom: 0;
    }

    .dasbor-baris a {
        color: #101828;
        text-decoration: none;
    }

    .dasbor-baris a:hover {
        text-decoration: underline;
    }

    .dasbor-pil {
        flex-shrink: 0;
        font-size: 0.66rem;
        line-height: 1.5;
        padding: 0.1rem 0.45rem;
        border-radius: 999px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #475569;
        white-space: nowrap;
    }

    .dasbor-pil.mendesak {
        border-color: #fecdca;
        background: #fff4f2;
        color: #b42318;
    }

    .dasbor-pil.sedang {
        border-color: #fde68a;
        background: #fffbeb;
        color: #b54708;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('user/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-3">Dasbor</h1>
    <p class="text-muted small mb-4">
        <?= esc($tanggal_hari_ini) ?>
        <?php if ($nama_toko !== []) : ?>
            &middot; <?= esc((string) count($nama_toko)) ?> toko: <?= esc(implode(', ', array_slice($nama_toko, 0, 3))) ?><?php if (count($nama_toko) > 3) : ?> +<?= (int) (count($nama_toko) - 3) ?><?php endif; ?>
        <?php else : ?>
            &middot; akun ini belum ditautkan ke toko mana pun
        <?php endif; ?>
    </p>

    <div class="row gx-2 gy-3 mb-4">
        <?php foreach ($kartu as $k) {
            // angka nol tidak perlu ikut mencolok
            $mendesak = ! empty($k['mendesak']) && (int) $k['nilai'] > 0; ?>
            <div class="col-6 col-md-4 col-lg-2">
                <a class="h-100 d-block rounded-3 border border-ink-200 bg-white px-3 py-2 text-decoration-none"
                    href="<?= site_url($k['tautan']) ?>">
                    <div class="text-muted small text-uppercase">
                        <i class="fal <?= $k['ikon'] ?>"></i> <?= esc($k['label']) ?>
                    </div>
                    <div class="fs-4 fw-bold <?= $mendesak ? 'text-danger' : 'text-ink-900' ?>"><?= esc($k['nilai']) ?></div>
                    <div class="small text-ink-500"><?= esc($k['ket']) ?></div>
                </a>
            </div>
        <?php } ?>
    </div>

    <div class="row gx-3 gy-3 mb-5">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <h2 class="h6 mb-0"><i class="fal fa-book-open me-1 text-primary"></i> SOP &amp; Panduan</h2>
                    </div>

                    <?php if ($sop_grup['SOP'] === [] && $sop_grup['PANDUAN'] === []) : ?>
                        <p class="text-muted small mb-0">
                            Belum ada panduan aktif untuk akun CS. Daftar ini terisi otomatis begitu Admin
                            menuliskan SOP dan menandainya aktif.
                        </p>
                    <?php else : ?>
                        <?= $this->include('admin/sop/panel') ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="mb-2">
                        <h2 class="h6 mb-0"><i class="fal fa-list-check me-1 text-primary"></i> Tugas yang Menunggu</h2>
                    </div>

                    <?php if ($pegangan === [] && $antrean === []) : ?>
                        <p class="text-muted small mb-0">
                            Tidak ada tugas yang menunggu. Semua pekerjaan tercatat selesai.
                        </p>
                    <?php else : ?>
                        <div class="dasbor-isi">
                            <?php if ($pegangan !== []) : ?>
                                <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: .06em;">
                                    Sedang saya pegang
                                </div>
                                <?php foreach ($pegangan as $t) : ?>
                                    <?php $tg = $label_tenggat($t['tenggat'] ?? null); ?>
                                    <div class="dasbor-baris">
                                        <span class="dasbor-pil <?= ($t['prioritas'] ?? '') === 'mendesak' || $tg['mendesak'] ? 'mendesak' : '' ?>">
                                            <?= esc($meta['prioritas'][$t['prioritas']] ?? $t['prioritas']) ?>
                                        </span>
                                        <div class="min-w-0">
                                            <a class="d-block fw-semibold small" href="<?= site_url('admin/tugas/' . (int) $t['id_tugas']) ?>">
                                                <?= esc($t['judul']) ?>
                                            </a>
                                            <div class="text-muted" style="font-size: 11px;">
                                                <?= esc($meta['jenis'][$t['jenis']] ?? $t['jenis']) ?>
                                                <?php if (! empty($t['nama_pelanggan'])) : ?>
                                                    &middot; <?= esc($t['nama_pelanggan']) ?>
                                                <?php endif; ?>
                                                <?php if (! empty($t['invoice_seri'])) : ?>
                                                    &middot; <?= esc($t['invoice_seri']) ?>
                                                <?php endif; ?>
                                                &middot; <span class="<?= $tg['mendesak'] ? 'text-danger fw-semibold' : '' ?>"><?= esc($tg['teks']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if ($antrean !== []) : ?>
                                <div class="text-muted text-uppercase fw-semibold <?= $pegangan !== [] ? 'mt-3' : '' ?>" style="font-size: 0.68rem; letter-spacing: .06em;">
                                    Antrean toko
                                </div>
                                <?php foreach ($antrean as $t) : ?>
                                    <?php $tg = $label_tenggat($t['tenggat'] ?? null); ?>
                                    <div class="dasbor-baris">
                                        <span class="dasbor-pil <?= ($t['prioritas'] ?? '') === 'mendesak' ? 'mendesak' : (($t['prioritas'] ?? '') === 'tinggi' ? 'sedang' : '') ?>">
                                            <?= esc($meta['prioritas'][$t['prioritas']] ?? $t['prioritas']) ?>
                                        </span>
                                        <div class="min-w-0">
                                            <a class="d-block fw-semibold small" href="<?= site_url('admin/tugas/' . (int) $t['id_tugas']) ?>">
                                                <?= esc($t['judul']) ?>
                                            </a>
                                            <div class="text-muted" style="font-size: 11px;">
                                                <?= esc($meta['jenis'][$t['jenis']] ?? $t['jenis']) ?>
                                                <?php if (! empty($t['nama_juragan'])) : ?>
                                                    &middot; <?= esc($t['nama_juragan']) ?>
                                                <?php endif; ?>
                                                &middot; <span class="<?= $tg['mendesak'] ? 'text-danger fw-semibold' : '' ?>"><?= esc($tg['teks']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
