<?php

use CodeIgniter\I18n\Time;

/**
 * Variabel berikut dikirim User\Chart::index() lewat view() lalu di-extract
 * CI4 saat render, sehingga tidak terlihat oleh analis statis. Blok @var ini
 * memberi jenis sebenarnya ke Intelephense.
 *
 * @var string             $title   judul halaman
 * @var array<int, object> $counter hasil InvoiceModel::counter_terkirim()
 */

$sekarang = new Time('now');
$session  = \Config\Services::session();

// ── Rapikan data per juragan untuk tile KPI dan grafik ───────────────────────
$juragan           = [];
$orderBulanIni     = [];
$orderBulanLalu    = [];
$terkirimBulanIni  = [];
$terkirimBulanLalu = [];

foreach ($counter as $count) {
    $juragan[]           = (string) $count->nama_juragan;
    $orderBulanIni[]     = (int) ($count->jumlahOrderBulanIni ?? 0);
    $orderBulanLalu[]    = (int) ($count->jumlahOrderBulanLalu ?? 0);
    $terkirimBulanIni[]  = (int) ($count->terkirimBulanIni ?? 0);
    $terkirimBulanLalu[] = (int) ($count->terkirimBulanLalu ?? 0);
}

$tOrderIni  = array_sum($orderBulanIni);
$tOrderLalu = array_sum($orderBulanLalu);
$tKirimIni  = array_sum($terkirimBulanIni);
$tKirimLalu = array_sum($terkirimBulanLalu);

// Selisih persen bulan ini vs bulan lalu; bulan lalu nol hindari bagi-nol.
$pct = static function (int $ini, int $lalu): int {
    if ($lalu <= 0) {
        return $ini > 0 ? 100 : 0;
    }

    return (int) round((($ini - $lalu) / $lalu) * 100);
};
$dOrder = $pct($tOrderIni, $tOrderLalu);
$dKirim = $pct($tKirimIni, $tKirimLalu);

$adaData       = $juragan !== [];
$awalBulanIni  = tanggalDefault('mulai');
$akhirBulanIni = tanggalDefault('akhir');

$juraganJS           = json_encode($juragan);
$orderBulanIniJS     = json_encode($orderBulanIni);
$orderBulanLaluJS    = json_encode($orderBulanLalu);
$terkirimBulanIniJS  = json_encode($terkirimBulanIni);
$terkirimBulanLaluJS = json_encode($terkirimBulanLalu);
$adaDataJs           = $adaData ? 'true' : 'false';
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    /* Halaman Chart CS — kulitnya mengikuti tema situs: kartu putih membulat,
       garis tepi slate tipis, bayangan lembut, aksen merah merek untuk "bulan
       ini" dan hijau untuk "terkirim". Warna ditulis literal (bukan utility
       Tailwind) agar tidak bergantung pada proses build ulang CSS. */
    /* baseline, bukan center: h1 di baris ini masih membawa mt-5 (3rem) yang
       dibutuhkan selector normal judul di style.css, jadi margin box-nya jauh
       lebih tinggi dari teksnya — kalau dipusatkan, chip tanggal justru naik di
       atas kata "Chart". */
    .chart-judul-baris {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: .5rem;
    }
    .chart-periode {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        padding: .3rem .7rem;
        font-size: .78rem;
        font-weight: 600;
        color: #475569;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        white-space: nowrap;
    }
    .chart-periode i { color: #da251d; }

    /* Baris tile KPI */
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .9rem;
        margin-bottom: 1.25rem;
    }
    @media (min-width: 768px) {
        .stat-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }
    .stat-kartu {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1.15rem;
        padding: 1rem 1.1rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
    }
    .stat-atas {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
    }
    .stat-label {
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #64748b;
    }
    .stat-ikon {
        width: 1.9rem;
        height: 1.9rem;
        border-radius: .6rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .9rem;
        flex-shrink: 0;
    }
    .stat-ikon.merah { background: #fdf1f0; color: #da251d; }
    .stat-ikon.hijau { background: #e7f6ec; color: #16a34a; }
    .stat-ikon.abu   { background: #f1f5f9; color: #64748b; }
    .stat-angka {
        font-size: 1.7rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
        margin-top: .65rem;
        font-variant-numeric: tabular-nums;
    }
    .stat-bawah {
        display: flex;
        align-items: center;
        gap: .4rem;
        margin-top: .2rem;
        font-size: .76rem;
        color: #94a3b8;
    }
    .stat-badge {
        display: inline-flex;
        align-items: center;
        gap: .2rem;
        font-weight: 700;
        font-size: .72rem;
    }
    .stat-badge.naik  { color: #16a34a; }
    .stat-badge.turun { color: #da251d; }
    .stat-badge.datar { color: #94a3b8; }

    /* Grid dua kartu grafik */
    .chart-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.1rem;
    }
    @media (min-width: 992px) {
        .chart-grid { grid-template-columns: 1fr 1fr; }
    }
    .chart-kartu {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        overflow: hidden;
    }
    .chart-kartu-kepala {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        gap: .35rem .7rem;
        padding: 1rem 1.15rem;
        border-bottom: 1px solid #f1f5f9;
        background: rgba(248, 250, 252, .6);
    }
    .badge-ikon {
        width: 2rem;
        height: 2rem;
        border-radius: .65rem;
        border: 1px solid #fadcd9;
        background: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #da251d;
        flex-shrink: 0;
    }
    .badge-ikon.hijau { border-color: #cdeed7; color: #16a34a; }
    .chart-kartu-kepala h2 {
        margin: 0;
        font-size: .82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #334155;
    }
    .chart-kartu-kepala .sub {
        margin: .1rem 0 0;
        font-size: .76rem;
        font-weight: 500;
        color: #94a3b8;
    }
    .chart-legend {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem .9rem;
        margin-left: auto;
    }
    .chart-legend .item {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        font-size: .76rem;
        font-weight: 600;
        color: #475569;
        white-space: nowrap;
    }
    .chart-legend .dot {
        width: .7rem;
        height: .7rem;
        border-radius: .25rem;
        display: inline-block;
    }
    .chart-box {
        position: relative;
        height: 340px;
        padding: 1.1rem 1.15rem 1.25rem;
    }
    @media (max-width: 767.98px) {
        /* tinggi kanvas sengaja TETAP sama dengan desktop (340px); yang dipangkas
           cuma jarak dalam kartu supaya sumbu X dapat lebar penuh */
        .chart-box {
            padding: .9rem .8rem 1rem;
        }
        .chart-kartu-kepala {
            padding: .8rem .9rem;
        }
        /* legendar turun ke baris sendiri: selama ia duduk di kanan, judul kartu
           terdesak jadi tiga baris ("JUMLAH / ORDERAN" + sub yang patah) */
        .chart-legend {
            flex-basis: 100%;
            margin-left: 0;
        }
    }
    .chart-kosong {
        padding: 3rem 1rem;
        text-align: center;
        color: #94a3b8;
    }
    .chart-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('user/navbar') ?>

<div class="container-xxl">

    <div class="chart-judul-baris">
        <h1 class="h3 mt-5 mb-0"><?= esc($title) ?></h1>
        <span class="chart-periode">
            <i class="fal fa-calendar"></i> per <?= esc($awalBulanIni) ?> &ndash; <?= esc($akhirBulanIni) ?>
        </span>
    </div>
    <p class="text-muted small mt-2 mb-4">
        Ringkasan orderan &amp; pengiriman per juragan, bulan ini dibanding bulan lalu.
    </p>

    <div class="stat-grid">
        <div class="stat-kartu">
            <div class="stat-atas">
                <span class="stat-label">Orderan Bulan Ini</span>
                <span class="stat-ikon merah"><i class="fal fa-receipt"></i></span>
            </div>
            <div class="stat-angka"><?= number_format($tOrderIni) ?></div>
            <div class="stat-bawah">
                <?php if ($dOrder > 0) : ?>
                    <span class="stat-badge naik"><i class="fal fa-arrow-trend-up"></i> +<?= $dOrder ?>%</span>
                <?php elseif ($dOrder < 0) : ?>
                    <span class="stat-badge turun"><i class="fal fa-arrow-trend-down"></i> <?= $dOrder ?>%</span>
                <?php else : ?>
                    <span class="stat-badge datar"><i class="fal fa-equals"></i> tetap</span>
                <?php endif; ?>
                <span>vs bulan lalu</span>
            </div>
        </div>

        <div class="stat-kartu">
            <div class="stat-atas">
                <span class="stat-label">Orderan Bulan Lalu</span>
                <span class="stat-ikon abu"><i class="fal fa-clock-rotate-left"></i></span>
            </div>
            <div class="stat-angka"><?= number_format($tOrderLalu) ?></div>
            <div class="stat-bawah"><span>acuan perbandingan</span></div>
        </div>

        <div class="stat-kartu">
            <div class="stat-atas">
                <span class="stat-label">Terkirim Bulan Ini</span>
                <span class="stat-ikon hijau"><i class="fal fa-truck-fast"></i></span>
            </div>
            <div class="stat-angka"><?= number_format($tKirimIni) ?></div>
            <div class="stat-bawah">
                <?php if ($dKirim > 0) : ?>
                    <span class="stat-badge naik"><i class="fal fa-arrow-trend-up"></i> +<?= $dKirim ?>%</span>
                <?php elseif ($dKirim < 0) : ?>
                    <span class="stat-badge turun"><i class="fal fa-arrow-trend-down"></i> <?= $dKirim ?>%</span>
                <?php else : ?>
                    <span class="stat-badge datar"><i class="fal fa-equals"></i> tetap</span>
                <?php endif; ?>
                <span>unit (pcs)</span>
            </div>
        </div>

        <div class="stat-kartu">
            <div class="stat-atas">
                <span class="stat-label">Terkirim Bulan Lalu</span>
                <span class="stat-ikon abu"><i class="fal fa-boxes-stacked"></i></span>
            </div>
            <div class="stat-angka"><?= number_format($tKirimLalu) ?></div>
            <div class="stat-bawah"><span>unit (pcs)</span></div>
        </div>
    </div>

    <?php if (! $adaData) : ?>
        <div class="chart-kartu">
            <div class="chart-kosong">
                <i class="fal fa-chart-column"></i>
                Belum ada data juragan untuk ditampilkan.
            </div>
        </div>
    <?php else : ?>
        <div class="chart-grid">
            <div class="chart-kartu">
                <div class="chart-kartu-kepala">
                    <span class="badge-ikon"><i class="fal fa-receipt"></i></span>
                    <div>
                        <h2>Jumlah Orderan</h2>
                        <p class="sub">Total nota per juragan</p>
                    </div>
                    <div class="chart-legend">
                        <span class="item"><span class="dot" style="background:#cbd5e1"></span>Bulan Lalu</span>
                        <span class="item"><span class="dot" style="background:#da251d"></span>Bulan Ini</span>
                    </div>
                </div>
                <div class="chart-box"><canvas id="chartOrder"></canvas></div>
            </div>

            <div class="chart-kartu">
                <div class="chart-kartu-kepala">
                    <span class="badge-ikon hijau"><i class="fal fa-truck-fast"></i></span>
                    <div>
                        <h2>Orderan Terkirim</h2>
                        <p class="sub">Unit terkirim (pcs) per juragan</p>
                    </div>
                    <div class="chart-legend">
                        <span class="item"><span class="dot" style="background:#cbd5e1"></span>Bulan Lalu</span>
                        <span class="item"><span class="dot" style="background:#16a34a"></span>Bulan Ini</span>
                    </div>
                </div>
                <div class="chart-box"><canvas id="chartTerkirim"></canvas></div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<?php

/* Sumbu X: SEMUA nama juragan harus tampil (autoSkip:false — tidak ada nama yang
   hilang) dan tulisannya miring, bukan tegak lurus: min/maxRotation 45-90 membuat
   Chart.js memakai kemiringan paling kendor yang masih muat, jadi desktop dapat
   ±45° sementara layar sempit naik sendiri sampai yang perlu. Nama panjang dipotong
   12 karakter dan lengkapnya tetap muncul di tooltip (callbacks.title mengambil
   label asli). Huruf sumbu 11px: dengan 21 nama, pitch 14px masih muat di kanvas
   314px, jadi tidak perlu mengecilkan teks. */
$js = <<< JS
    $(function() {
        'use strict';

        if (typeof Chart === 'undefined' || !{$adaDataJs}) {
            return;
        }

        // Ikuti tipografi situs supaya grafik terasa bagian dari halaman.
        Chart.defaults.font.family = "'Outfit', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif";
        Chart.defaults.color = '#64748b';

        var warnaLalu = '#cbd5e1';   // slate muda: pembanding (bulan lalu)

        function potong(teks) {
            return teks.length > 12 ? teks.slice(0, 11) + '…' : teks;
        }

        function buatChart(canvasId, labels, dataLalu, dataIni, warnaIni) {
            var el = document.getElementById(canvasId);
            if (!el || !labels || labels.length === 0) {
                return null;
            }

            return new Chart(el.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Bulan Lalu',
                            data: dataLalu,
                            backgroundColor: warnaLalu,
                            hoverBackgroundColor: '#b6c2d2',
                            borderRadius: 6,
                            borderSkipped: false,
                            maxBarThickness: 22
                        },
                        {
                            label: 'Bulan Ini',
                            data: dataIni,
                            backgroundColor: warnaIni,
                            hoverBackgroundColor: warnaIni,
                            borderRadius: 6,
                            borderSkipped: false,
                            maxBarThickness: 22
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 600, easing: 'easeOutQuart' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#f8fafc',
                            bodyColor: '#e2e8f0',
                            padding: 10,
                            cornerRadius: 10,
                            displayColors: true,
                            usePointStyle: true,
                            boxPadding: 4,
                            callbacks: {
                                title: function (butir) {
                                    return labels[butir[0].dataIndex];
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: {
                                color: '#64748b',
                                font: { size: 11 },
                                autoSkip: false,
                                maxRotation: 90,
                                minRotation: 45,
                                callback: function (nilai) {
                                    return potong(this.getLabelForValue(nilai));
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            border: { display: false },
                            ticks: { color: '#94a3b8', precision: 0, font: { size: 11 } }
                        }
                    }
                }
            });
        }

        buatChart('chartOrder', {$juraganJS}, {$orderBulanLaluJS}, {$orderBulanIniJS}, '#da251d');
        buatChart('chartTerkirim', {$juraganJS}, {$terkirimBulanLaluJS}, {$terkirimBulanIniJS}, '#16a34a');
    });
    JS;

$packer    = new Tholu\Packer\Packer($js, 'Normal', true, false, true);
$packed_js = $packer->pack();
echo '<script>' . $packed_js . '</script>';
?>
<?= $this->endSection() ?>
