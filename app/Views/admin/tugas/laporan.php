<?php

/**
 * Laporan aktivitas tugas (Admin & Superadmin).
 *
 * Semua variabel di bawah disuntik Admin\Tugas::laporan() lewat bahanLaporan();
 * blok @var ini hanya memberi tahunya ke analyzer, bukan kode yang dijalankan.
 *
 * @var string $title      judul halaman
 * @var array<string, mixed> $s        saringan yang sudah dibersihkan (ts + tanggal teks)
 * @var list<array<string, mixed>> $baris    kejadian pada rentang ini
 * @var int    $total      jumlah kejadian sesuai saringan
 * @var int    $page       halaman aktif (berbasis satu)
 * @var int    $batas      baris per halaman
 * @var array<string, int>   $perjenis jumlah per jenis kejadian
 * @var list<array<string, mixed>> $beban    rekap per orang
 * @var array<int, string>   $pegawai  orang yang bisa disaring
 * @var array{jenis_event: array<string, string>} $meta  label per jenis kejadian
 * @var string $kabar      pesan sukses flash (string kosong kalau tidak ada)
 * @var string $galat      pesan gagal flash (string kosong kalau tidak ada)
 */

$tanya_csv = static function (array $tambah = []): string {
    $q = array_merge($_GET, $tambah);

    return site_url('admin/tugas/laporan/csv') . '?' . http_build_query($q);
};

$dibaca = static function ($detik): string {
    return $detik !== null && (int) $detik > 0 ? date('d M Y H:i', (int) $detik) : '-';
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .lp-halaman { max-width: 1180px; }
    .lp-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .lp-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }

    .lp-saring { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .55rem; margin-bottom: 1rem; }
    .lp-saring .lapangan { display: flex; flex-direction: column; gap: .2rem; }
    .lp-saring label { font-size: .7rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .lp-saring .form-control, .lp-saring .form-select { max-width: 200px; }

    .lp-kartu {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem; overflow: hidden; margin-bottom: 1rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
    }
    .lp-kartu > .lp-judul {
        padding: .7rem 1rem; border-bottom: 1px solid #eaecf0;
        font-size: .78rem; font-weight: 700; color: #344054; text-transform: uppercase; letter-spacing: .05em;
    }

    .lp-chip { display: flex; flex-wrap: wrap; gap: .4rem; padding: .9rem 1rem; }
    .lp-chip span {
        background: #f4f6f9; border: 1px solid #e2e8f0; border-radius: .8rem; padding: .45rem .7rem; min-width: 108px;
    }
    .lp-chip b { display: block; font-size: 1.05rem; color: #0f172a; }
    .lp-chip small { font-size: .7rem; color: #667085; }

    .table.lp-tabel { margin: 0; }
    .lp-tabel th { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; background: #f9fafb; }
    .lp-tabel td { font-size: .84rem; vertical-align: middle; }
    .lp-kosong { padding: 2.2rem 1rem; text-align: center; color: #94a3b8; }
    .lp-pager { padding: .8rem 1rem; border-top: 1px solid #eaecf0; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl lp-halaman">

    <div class="lp-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><i class="fal fa-chart-line me-1 text-brand-500"></i> <?= esc($title) ?></h1>
            <p>Semua perpindahan tugas tercatat di sini: siapa mengambil, siapa menetapkan, apa yang dioper.</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/tugas')) ?>">
            <i class="fal fa-list-check me-1"></i> Antrean
        </a>
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/tugas/dasbor')) ?>">
            <i class="fal fa-gauge me-1"></i> Dasbor
        </a>
        <a class="btn btn-sm btn-dark rounded-pill" href="<?= esc($tanya_csv()) ?>">
            <i class="fal fa-file-csv me-1"></i> Unduh CSV
        </a>
    </div>

    <?php if ($kabar !== '') : ?><div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div><?php endif; ?>
    <?php if ($galat !== '') : ?><div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div><?php endif; ?>

    <form class="lp-saring" method="get" action="<?= esc(site_url('admin/tugas/laporan')) ?>">
        <div class="lapangan">
            <label for="dari">Dari</label>
            <input type="date" class="form-control form-control-sm" id="dari" name="dari" value="<?= esc($s['hari_dari']) ?>">
        </div>
        <div class="lapangan">
            <label for="sampai">Sampai</label>
            <input type="date" class="form-control form-control-sm" id="sampai" name="sampai" value="<?= esc($s['hari_sampai']) ?>">
        </div>
        <div class="lapangan">
            <label for="orang">Orang</label>
            <select class="form-select form-select-sm" id="orang" name="orang">
                <option value="">Siapa pun</option>
                <?php foreach ($pegawai as $id_orang => $nama_orang) : ?>
                    <option value="<?= (int) $id_orang ?>" <?= (int) $s['user_id'] === (int) $id_orang ? 'selected' : '' ?>>
                        <?= esc($nama_orang) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="lapangan">
            <label for="jenis">Kejadian</label>
            <select class="form-select form-select-sm" id="jenis" name="jenis">
                <option value="">Semua kejadian</option>
                <?php foreach ($meta['jenis_event'] as $kode => $nama) : ?>
                    <option value="<?= esc($kode) ?>" <?= $s['jenis'] === $kode ? 'selected' : '' ?>><?= esc($nama) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="lapangan">
            <label for="batas">Baris</label>
            <select class="form-select form-select-sm" id="batas" name="batas">
                <?php foreach ([10, 25, 50] as $n) : ?>
                    <option value="<?= $n ?>" <?= $batas === $n ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="lapangan">
            <label>&nbsp;</label>
            <div class="d-inline-flex gap-2 align-items-center">
                <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-search"></i> Terapkan</button>
                <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/tugas/laporan')) ?>">Ulangi</a>
            </div>
        </div>
    </form>

    <div class="lp-kartu">
        <div class="lp-judul">Rekap per orang &middot; <?= esc($s['hari_dari']) ?> &rarr; <?= esc($s['hari_sampai']) ?></div>
        <?php if ($beban === []) : ?>
            <div class="lp-kosong">Tidak ada tugas yang dipegang orang pada rentang ini.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover lp-tabel tabel-rapi">
                    <thead>
                        <tr>
                            <th>Orang</th><th class="text-end">Dipegang</th><th class="text-end">Terbuka</th>
                            <th class="text-end">Lewat tenggat</th><th class="text-end">Selesai</th>
                            <th class="text-end">Dibatalkan</th><th class="text-end">Hasil operan</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($beban as $b) : ?>
                            <tr>
                                <td><?= $b['nama'] ? esc($b['nama']) : '<span class="text-muted">(akun dihapus)</span>' ?></td>
                                <td class="text-end"><?= (int) $b['pegangan'] ?></td>
                                <td class="text-end"><?= (int) $b['terbuka'] ?></td>
                                <td class="text-end <?= (int) $b['terlambat'] > 0 ? 'text-danger fw-bold' : '' ?>"><?= (int) $b['terlambat'] ?></td>
                                <td class="text-end"><?= (int) $b['selesai'] ?></td>
                                <td class="text-end"><?= (int) $b['batal'] ?></td>
                                <td class="text-end"><?= (int) $b['operan'] ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-link text-decoration-none"
                                        href="<?= esc($tanya_csv(['orang' => (int) $b['user_id'], 'batas' => 50])) ?>">riwayatnya</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="lp-kartu">
        <div class="lp-judul">Kejadian per jenis</div>
        <div class="lp-chip">
            <?php foreach ($meta['jenis_event'] as $kode => $nama) : ?>
                <?php $n = (int) ($perjenis[$kode] ?? 0); ?>
                <span>
                    <b><?= $n ?></b>
                    <small><a class="text-decoration-none"
                        href="<?= esc(site_url('admin/tugas/laporan?' . http_build_query(['dari' => $s['hari_dari'], 'sampai' => $s['hari_sampai'], 'jenis' => $kode]))) ?>"><?= esc($nama) ?></a></small>
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="lp-kartu">
        <div class="lp-judul">Riwayat (<?= (int) $total ?> kejadian)</div>
        <?php if ($baris === []) : ?>
            <div class="lp-kosong">Tidak ada kejadian pada saringan ini.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle lp-tabel">
                    <thead>
                        <tr><th>Waktu</th><th>Orang</th><th>Kejadian</th><th>Tugas</th><th>Perubahan</th><th>Toko</th><th>Alasan / catatan</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baris as $e) : ?>
                            <tr>
                                <td class="text-nowrap"><?= $dibaca($e['created_at']) ?></td>
                                <td><?= esc($e['nama_aktor'] ?? '-') ?></td>
                                <td><?= esc($meta['jenis_event'][$e['jenis']] ?? $e['jenis']) ?></td>
                                <td>
                                    <a class="text-decoration-none" href="<?= esc(site_url('admin/tugas/' . (int) $e['tugas_id'])) ?>">
                                        <?= esc($e['judul'] ?? '(tugas dihapus)') ?>
                                    </a>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ($e['status_lama'] || $e['status_baru']) : ?>
                                        <span class="text-muted"><?= esc((string) $e['status_lama'] ?: '-') ?></span>
                                        <i class="fal fa-arrow-right mx-1"></i>
                                        <span><?= esc((string) $e['status_baru'] ?: '-') ?></span>
                                    <?php else : ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $e['nama_juragan'] ? esc($e['nama_juragan']) : 'umum' ?></td>
                                <td class="small text-muted">
                                    <?php if ($e['alasan']) : ?><span class="text-danger"><?= esc($e['alasan']) ?></span><?php endif; ?>
                                    <?= esc($e['catatan'] ?? '') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($total > $batas) : ?>
            <div class="lp-pager d-flex flex-wrap align-items-center gap-2">
                <span class="small text-muted">halaman <?= (int) $page ?></span>
                <div class="ms-auto">
                    <?= \Config\Services::pager()->makeLinks($page, $batas, $total, 'front_full') ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<?= $this->endSection() ?>
