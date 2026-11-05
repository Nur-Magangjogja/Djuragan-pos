<?php

/**
 * Dasbor kerja harian. Isinya beda per peran, cakupannya selalu toko akun ini.
 *
 * Semua variabel di bawah disuntik Admin\Tugas::dasbor() lewat view(). Satu tag
 * @var hanya mengikat satu variabel, jadi tiap variabel ditulis pada barisnya sendiri.
 *
 * @var string $title       judul halaman
 * @var string $hari        tanggal hari ini (Y-m-d)
 * @var string $level       peran yang membuka (cs/admin/superadmin)
 * @var array<string, int>  $ringkas   jumlah tugas per status
 * @var list<array<string, mixed>> $pegangan  tugas atas namaku yang belum beres
 * @var list<array<string, mixed>> $antrean   tugas yang belum ada penggarapnya
 * @var list<array<string, mixed>> $terlambat tugas yang lewat tenggat
 * @var list<array<string, mixed>> $riwayat   kejadian terakhir
 * @var list<array<string, mixed>> $beban     rekap kerja per orang
 * @var int    $sesi        lembar Cek Toko yang belum ditutup
 * @var array<string, mixed>|null $shift      jam shift saya hari ini
 * @var list<array<string, mixed>> $regu      jam shift semua CS hari ini
 * @var array<string, array<string, string>> $meta  label status/jenis/prioritas
 * @var string $kabar       pesan sukses flash (string kosong kalau tidak ada)
 * @var string $galat       pesan gagal flash (string kosong kalau tidak ada)
 */

$level  = (string) $level;
$hari   = (string) $hari;
$status = $meta['status'];

$tiles = [
    ['Di antrean', (int) ($ringkas['queued'] ?? 0), 'fa-inbox', 'menunggu ada yang mengambil'],
    ['Pegangan saya', (int) ($ringkas['assigned'] ?? 0) + (int) ($ringkas['in_progress'] ?? 0), 'fa-user-check', 'sudah ada penggarapnya'],
    ['Menunggu pelanggan', (int) ($ringkas['waiting'] ?? 0), 'fa-hourglass-half', 'punya jadwal tindak lanjut'],
    ['Lewat tenggat', (int) ($ringkas['terlambat'] ?? 0), 'fa-triangle-exclamation', 'belum beres tapi sudah telat'],
    ['Cek Toko hari ini', (int) $sesi, 'fa-ballot-check', 'lembar toko yang belum ditutup'],
];
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .dsb-halaman { max-width: 1180px; }
    .dsb-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .dsb-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }

    .dsb-tiles { display: flex; flex-wrap: wrap; gap: .6rem; margin-bottom: 1rem; }
    .dsb-tile {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: .75rem .9rem;
        min-width: 168px; flex: 1;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }
    .dsb-tile b { display: block; font-size: 1.4rem; color: #0f172a; line-height: 1.2; }
    .dsb-tile span { font-size: .74rem; font-weight: 700; color: #475569; }
    .dsb-tile small { display: block; font-size: .7rem; color: #94a3b8; margin-top: .1rem; }
    .dsb-tile.abis { border-color: #f4a6a1; }
    .dsb-tile.abis b { color: #b81c16; }

    .dsb-grid { display: flex; flex-wrap: wrap; gap: 1rem; }
    .dsb-kolom { flex: 1; min-width: 320px; }
    .dsb-kartu {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem; overflow: hidden;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        margin-bottom: 1rem;
    }
    .dsb-kartu > .dsb-judul {
        padding: .7rem 1rem; border-bottom: 1px solid #eaecf0; display: flex; align-items: center; gap: .5rem;
        font-size: .78rem; font-weight: 700; color: #344054; text-transform: uppercase; letter-spacing: .05em;
    }
    .dsb-kartu > .dsb-judul a { font-size: .72rem; text-transform: none; letter-spacing: 0; font-weight: 600; }

    .dsb-baris { padding: .7rem 1rem; border-bottom: 1px solid #f1f5f9; }
    .dsb-baris:last-child { border-bottom: 0; }
    .dsb-nama { font-weight: 700; color: #0f172a; font-size: .88rem; text-decoration: none; display: block; }
    .dsb-nama:hover { color: #da251d; }
    .dsb-sub { font-size: .74rem; color: #64748b; display: flex; flex-wrap: wrap; gap: .2rem .6rem; margin-top: .12rem; }
    .dsb-kosong { padding: 1.6rem 1rem; text-align: center; color: #94a3b8; font-size: .85rem; }

    .table.dsb-beban { margin: 0; }
    .dsb-beban th { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; background: #f9fafb; }
    .dsb-beban td { font-size: .84rem; vertical-align: middle; }
    .pil-status { display: inline-block; padding: .16rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; }
    .s-queued { background: #eef1f6; color: #475569; }
    .s-assigned { background: #e6f4fb; color: #0369a1; }
    .s-in_progress { background: #fef3e2; color: #b45309; }
    .s-waiting { background: #f3e8ff; color: #6b21a8; }
    .s-completed { background: #e7f6ec; color: #14663a; }
    .s-cancelled { background: #fdf1f0; color: #b81c16; }

    /* di layar sempit ikon judul dibuang dan kartu statistik disusun dua-dua
       kanan-kiri: dengan flex + min-width 168px sepasang pun tidak pernah muat
       (168 + 168 + jarak 9,6 = 345,6px sementara kartu cuma 336px), jadi isinya
       selalu jatuh satu per baris */
    @media (max-width: 991.98px) {
        .dsb-head h1 > i { display: none; }
        .dsb-tiles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .dsb-tile { min-width: 0; }
        .dsb-tile:last-child:nth-child(odd) { grid-column: 1 / -1; }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl dsb-halaman">

    <div class="dsb-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><a class="judul-balik" href="<?= esc(site_url('admin/tugas')) ?>"
                    title="Kembali ke antrean tugas" aria-label="Kembali ke antrean tugas"><i class="fal fa-arrow-left"></i></a><i class="fal fa-gauge me-1 text-brand-500"></i> <?= esc($title) ?></h1>
            <p><?= $level === 'cs' ? 'Peganganmu hari ini' : 'Kondisi antrean kerja semua CS' ?> &middot; <?= esc(tanggal_rincian($hari)) ?></p>
        </div>
        <?php if ($level !== 'cs') : ?>
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/tugas/laporan')) ?>">
                <i class="fal fa-chart-line me-1"></i> Laporan
            </a>
        <?php endif; ?>
    </div>

    <?php if ($kabar !== '') : ?><div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div><?php endif; ?>
    <?php if ($galat !== '') : ?><div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div><?php endif; ?>

    <div class="dsb-tiles">
        <?php foreach ($tiles as [$label, $angka, $ikon, $sub]) : ?>
            <div class="dsb-tile <?= $label === 'Lewat tenggat' && $angka > 0 ? 'abis' : '' ?>">
                <b><?= (int) $angka ?></b>
                <span><i class="fal <?= $ikon ?> me-1"></i><?= esc($label) ?></span>
                <small><?= esc($sub) ?></small>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="dsb-grid">
        <div class="dsb-kolom">
            <div class="dsb-kartu">
                <div class="dsb-judul">
                    <i class="fal fa-user-check"></i> Pegangan saya
                </div>
                <?php if ($pegangan === []) : ?>
                    <div class="dsb-kosong">Tidak ada pekerjaan atas namamu saat ini.</div>
                <?php else : ?>
                    <?php foreach ($pegangan as $t) : ?>
                        <div class="dsb-baris">
                            <a class="dsb-nama" href="<?= esc(site_url('admin/tugas/' . (int) $t['id_tugas'])) ?>"><?= esc($t['judul']) ?></a>
                            <div class="dsb-sub">
                                <span class="pil-status s-<?= esc($t['status']) ?>"><?= esc($status[$t['status']] ?? $t['status']) ?></span>
                                <span><?= esc($meta['jenis'][$t['jenis']] ?? $t['jenis']) ?></span>
                                <span>tenggat <?= $t['tenggat'] ? esc(tanggal_rincian((string) $t['tenggat'])) : '-' ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="dsb-kartu">
                <div class="dsb-judul">
                    <i class="fal fa-inbox"></i> Baru di antrean
                </div>
                <?php if ($antrean === []) : ?>
                    <div class="dsb-kosong">Antrean sedang bersih.</div>
                <?php else : ?>
                    <?php foreach ($antrean as $t) : ?>
                        <div class="dsb-baris">
                            <a class="dsb-nama" href="<?= esc(site_url('admin/tugas/' . (int) $t['id_tugas'])) ?>"><?= esc($t['judul']) ?></a>
                            <div class="dsb-sub">
                                <span><?= esc($meta['jenis'][$t['jenis']] ?? $t['jenis']) ?></span>
                                <span><?= $t['nama_juragan'] ? esc($t['nama_juragan']) : 'umum' ?></span>
                                <span><?= esc($meta['prioritas'][$t['prioritas']] ?? $t['prioritas']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="dsb-kolom">
            <div class="dsb-kartu">
                <div class="dsb-judul">
                    <i class="fal fa-triangle-exclamation text-danger"></i> Lewat tenggat
                </div>
                <?php if ($terlambat === []) : ?>
                    <div class="dsb-kosong">Tidak ada yang lewat tenggat. <i class="fal fa-circle-check"></i></div>
                <?php else : ?>
                    <?php foreach ($terlambat as $t) : ?>
                        <div class="dsb-baris">
                            <a class="dsb-nama" href="<?= esc(site_url('admin/tugas/' . (int) $t['id_tugas'])) ?>"><?= esc($t['judul']) ?></a>
                            <div class="dsb-sub">
                                <span class="text-danger"><?= esc(tanggal_rincian((string) $t['tenggat'])) ?></span>
                                <span><?= $t['nama_dikerjakan'] ? esc($t['nama_dikerjakan']) : 'belum ada penggarap' ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($level !== 'cs') : ?>
                <div class="dsb-kartu">
                    <div class="dsb-judul">
                        <i class="fal fa-users"></i> Beban 7 hari terakhir
                        <a class="ms-auto text-decoration-none" href="<?= esc(site_url('admin/tugas/laporan')) ?>">laporan lengkap</a>
                    </div>
                    <?php if ($beban === []) : ?>
                        <div class="dsb-kosong">Belum ada tugas yang dikerjakan orang pada rentang ini.</div>
                    <?php else : ?>
                        <div class="table-responsive">
                            <table class="table dsb-beban tabel-rapi">
                                <thead>
                                    <tr><th>Orang</th><th class="text-end">Terbuka</th><th class="text-end">Telat</th><th class="text-end">Selesai</th><th class="text-end">Dibatal</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($beban as $b) : ?>
                                        <tr>
                                            <td><?= $b['nama'] ? esc($b['nama']) : '<span class="text-muted">(akun dihapus)</span>' ?></td>
                                            <td class="text-end"><?= (int) $b['terbuka'] ?></td>
                                            <td class="text-end <?= (int) $b['terlambat'] > 0 ? 'text-danger fw-bold' : '' ?>"><?= (int) $b['terlambat'] ?></td>
                                            <td class="text-end"><?= (int) $b['selesai'] ?></td>
                                            <td class="text-end"><?= (int) $b['batal'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dsb-kartu">
                    <div class="dsb-judul"><i class="fal fa-clock"></i> Jam regu hari ini</div>
                    <?php if ($regu === []) : ?>
                        <div class="dsb-kosong">Belum ada jam shift tercatat untuk hari ini.</div>
                    <?php else : ?>
                        <?php foreach ($regu as $r) : ?>
                            <div class="dsb-baris">
                                <strong><?= esc($r['name'] ?? $r['nama'] ?? '-') ?></strong>
                                <div class="dsb-sub">
                                    <span><?= esc($r['mulai'] ?? '-') ?> &ndash; <?= esc($r['selesai'] ?? '-') ?></span>
                                    <?php if (! empty($r['nama_juragan'])) : ?><span><?= esc($r['nama_juragan']) ?></span><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="dsb-kartu">
                    <div class="dsb-judul"><i class="fal fa-clock"></i> Jam shift saya</div>
                    <?php if ($shift === null) : ?>
                        <div class="dsb-kosong">
                            Belum ada jam shift hari ini. Tugas yang jamnya sudah lewat akan dioper otomatis ke CS berikutnya.
                        </div>
                    <?php else : ?>
                        <div class="dsb-baris">
                            <strong><?= esc($shift['mulai']) ?> &ndash; <?= esc($shift['selesai']) ?></strong>
                            <div class="dsb-sub">
                                <?php if (! empty($shift['catatan'])) : ?><span><?= esc($shift['catatan']) ?></span><?php endif; ?>
                                <span>diatur Admin &middot; minta ubah lewat Admin jika salah</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="dsb-kartu">
                <div class="dsb-judul"><i class="fal fa-timeline"></i> Kejadian terakhir</div>
                <?php if ($riwayat === []) : ?>
                    <div class="dsb-kosong">Belum ada riwayat pada cakupan ini.</div>
                <?php else : ?>
                    <?php foreach ($riwayat as $e) : ?>
                        <div class="dsb-baris">
                            <a class="dsb-nama" href="<?= esc(site_url('admin/tugas/' . (int) $e['tugas_id'])) ?>"><?= esc($e['judul'] ?? '(tugas dihapus)') ?></a>
                            <div class="dsb-sub">
                                <span><?= esc($e['nama_aktor'] ?? '-') ?></span>
                                <span><?= esc($e['jenis']) ?></span>
                                <?php if ($e['status_baru']) : ?><span><?= esc($status[$e['status_baru']] ?? $e['status_baru']) ?></span><?php endif; ?>
                                <span><?= date('d M H:i', (int) $e['created_at']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?= $this->endSection() ?>
