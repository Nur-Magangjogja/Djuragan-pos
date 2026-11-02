<?php

/**
 * Daftar antrean tugas CS.
 *
 * Semua variabel di bawah disuntik Admin\Tugas::index() lewat view(); blok @var
 * ini hanya memberi tahunya ke analyzer, bukan kode yang dijalankan.
 *
 * @var string $title      judul halaman
 * @var array  $s          saringan yang sudah dibersihkan controller
 * @var array  $baris      hasil TugasModel::daftar()
 * @var int    $total      jumlah baris sesuai saringan
 * @var int    $page       halaman aktif (berbasis satu)
 * @var int    $batas      baris per halaman
 * @var array  $tab        nama tab => label
 * @var array  $ringkas    jumlah per status + 'terlambat'
 * @var int    $milik_saya jumlah tugas terbuka yang dipegang akun ini
 * @var array  $toko       id => nama toko milik akun ini
 * @var array  $staf       id => nama staf yang bisa ditunjuk
 * @var array  $pilihan    jenis/status/prioritas untuk filter dan form
 * @var string $kabar      pesan sukses flash (string kosong kalau tidak ada)
 * @var string $galat      pesan gagal flash (string kosong kalau tidak ada)
 */

$level    = (string) session()->get('level');
$hari_ini = date('Y-m-d');

/**
 * Tautan daftar yang mempertahankan saringan aktif. 'halaman' dibuang kecuali
 * memang diminta, supaya pindah tab tidak meninggalkan pembaca di halaman 7.
 */
$tanya = static function (array $ubah): string {
    $q = array_merge($_GET, $ubah);

    if (! array_key_exists('halaman', $ubah)) {
        unset($q['halaman']);
    }

    foreach (['tab', 'jenis', 'status', 'prioritas', 'cari', 'nomor', 'staf', 'juragan', 'awal', 'akhir', 'batas', 'halaman'] as $k) {
        if (isset($q[$k]) && $q[$k] === '') {
            unset($q[$k]);
        }
    }

    return site_url('admin/tugas') . '?' . http_build_query($q);
};

$label_status = static function (string $kode) use ($pilihan): string {
    return $pilihan['status'][$kode] ?? $kode;
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .tugas-halaman { max-width: 1180px; }
    .tugas-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .tugas-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }

    /* tab gaya pil, sama seperti pemilih kolam di halaman Produk */
    .tugas-tab { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1rem; }
    .tugas-tab a {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .34rem .85rem; border-radius: 999px; font-size: .8rem; font-weight: 600;
        background: #f4f6f9; color: #475569; text-decoration: none; border: 1px solid #e2e8f0;
    }
    .tugas-tab a.aktif { background: #101828; border-color: #101828; color: #fff; }
    .tugas-tab a span { font-weight: 700; }

    .tugas-saring { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .55rem; margin-bottom: 1rem; }
    .tugas-saring .lapangan { display: flex; flex-direction: column; gap: .2rem; }
    .tugas-saring label { font-size: .7rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .tugas-saring .form-control, .tugas-saring .form-select { max-width: 190px; }

    .tugas-kartu {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        overflow: hidden;
    }
    .table.tugas-daftar { margin: 0; }
    .tugas-daftar th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; background: #f9fafb; }
    .tugas-daftar td { vertical-align: middle; font-size: .86rem; }
    .tugas-daftar thead th:first-child { width: 34%; }
    .tugas-nama { font-weight: 700; color: #0f172a; text-decoration: none; display: block; }
    .tugas-nama:hover { color: #da251d; }
    .tugas-sub { font-size: .76rem; color: #64748b; display: flex; flex-wrap: wrap; gap: .2rem .6rem; margin-top: .1rem; }
    .tugas-kosong { padding: 2.6rem 1rem; text-align: center; color: #94a3b8; }
    .tugas-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }

    .pil-status {
        display: inline-block; padding: .18rem .6rem; border-radius: 999px;
        font-size: .72rem; font-weight: 700; white-space: nowrap;
    }
    .s-queued { background: #eef1f6; color: #475569; }
    .s-assigned { background: #e6f4fb; color: #0369a1; }
    .s-in_progress { background: #fef3e2; color: #b45309; }
    .s-waiting { background: #f3e8ff; color: #6b21a8; }
    .s-completed { background: #e7f6ec; color: #14663a; }
    .s-cancelled { background: #fdf1f0; color: #b81c16; }

    .pil-prioritas { font-size: .74rem; font-weight: 700; white-space: nowrap; }
    .p-mendesak { color: #b81c16; }
    .p-tinggi { color: #b45309; }
    .p-normal { color: #64748b; }
    .p-rendah { color: #94a3b8; }

    .tugas-teenggat { font-size: .76rem; white-space: nowrap; }
    .lewat { color: #b81c16; font-weight: 700; }

    .tugas-aksi { display: inline-flex; gap: .35rem; align-items: center; }
    .tugas-pager { padding: .8rem 1rem; background: #fff; border-top: 1px solid #eaecf0; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl tugas-halaman">

    <div class="tugas-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><i class="fal fa-tasks me-1 text-brand-500"></i> <?= esc($title) ?></h1>
            <p>Sisa pekerjaan yang tidak punya menu sendiri: follow-up pelanggan dan tugas buatan tangan. WhatsApp, Bintang 5, Cek Toko, dan Cek Email ada di submenu masing-masing.</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/tugas/dasbor')) ?>">
            <i class="fal fa-gauge me-1"></i> Dasbor Kerja
        </a>
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/tugas/catatan')) ?>">
            <i class="fal fa-comments me-1"></i> Catatan percakapan
        </a>
        <?php if (in_array($level, ['admin', 'superadmin'], true)) : ?>
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/tugas/pengaturan')) ?>">
                <i class="fal fa-bell me-1"></i> Aturan reminder
            </a>
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/tugas/shift')) ?>">
                <i class="fal fa-clock me-1"></i> Jam shift
            </a>
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/tugas/template')) ?>">
                <i class="fal fa-layer-group me-1"></i> Templat Tugas
            </a>
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/tugas/laporan')) ?>">
                <i class="fal fa-chart-line me-1"></i> Laporan aktivitas
            </a>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalBuatTugas">
            <i class="fal fa-plus"></i> Buat Tugas
        </button>
    </div>

    <?php if ($kabar !== '') : ?>
        <div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div>
    <?php endif; ?>
    <?php if ($galat !== '') : ?>
        <div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div>
    <?php endif; ?>

    <div class="tugas-tab">
        <?php foreach ($tab as $kode => $nama) : ?>
            <?php
            $angka = match ($kode) {
                'antrean'   => $ringkas['queued'] ?? 0,
                'saya'      => $milik_saya,
                'terlambat' => $ringkas['terlambat'] ?? 0,
                'selesai'   => $ringkas['completed'] ?? 0,
                default     => $total,
            };
            ?>
            <a class="<?= $s['tab'] === $kode ? 'aktif' : '' ?>" href="<?= esc($tanya(['tab' => $kode])) ?>">
                <?= esc($nama) ?>
                <?php if ($angka > 0) : ?><span><?= (int) $angka ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="tugas-saring" method="get" action="<?= esc(site_url('admin/tugas')) ?>">
        <input type="hidden" name="tab" value="<?= esc($s['tab']) ?>">
        <div class="lapangan">
            <label for="cari">Cari</label>
            <input type="search" class="form-control form-control-sm" id="cari" name="cari"
                value="<?= esc($s['cari']) ?>" placeholder="judul, catatan, atau nama pelanggan">
        </div>
        <div class="lapangan">
            <label for="nomor">Nomor WA</label>
            <input type="search" class="form-control form-control-sm" id="nomor" name="nomor"
                value="<?= esc($s['nomor'] ?? '') ?>" placeholder="0812...">
        </div>
        <div class="lapangan">
            <label for="jenis">Jenis</label>
            <select class="form-select form-select-sm" id="jenis" name="jenis">
                <option value="">Semua jenis</option>
                <?php foreach ($pilihan['jenis'] as $kode => $nama) : ?>
                    <option value="<?= esc($kode) ?>" <?= ($s['jenis'] ?? '') === $kode ? 'selected' : '' ?>><?= esc($nama) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="lapangan">
            <label for="prioritas">Prioritas</label>
            <select class="form-select form-select-sm" id="prioritas" name="prioritas">
                <option value="">Semua</option>
                <?php foreach ($pilihan['prioritas'] as $kode => $nama) : ?>
                    <option value="<?= esc($kode) ?>" <?= ($s['prioritas'] ?? '') === $kode ? 'selected' : '' ?>><?= esc($nama) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (count($toko) > 1) : ?>
            <div class="lapangan">
                <label for="juragan">Toko</label>
                <select class="form-select form-select-sm" id="juragan" name="juragan">
                    <option value="">Semua toko</option>
                    <?php foreach ($toko as $id_toko => $nama_t) : ?>
                        <option value="<?= (int) $id_toko ?>" <?= (int) ($s['juragan_id'] ?? 0) === (int) $id_toko ? 'selected' : '' ?>>
                            <?= esc($nama_t) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if (in_array($level, ['admin', 'superadmin'], true) && $staf !== []) : ?>
            <div class="lapangan">
                <label for="staf">Penggarap</label>
                <select class="form-select form-select-sm" id="staf" name="staf">
                    <option value="">Siapa pun</option>
                    <?php foreach ($staf as $id_staf => $nama_staf) : ?>
                        <option value="<?= (int) $id_staf ?>" <?= ($s['dikerjakan_oleh'] ?? 0) === (int) $id_staf ? 'selected' : '' ?>>
                            <?= esc($nama_staf) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="lapangan">
            <label for="awal">Tenggat dari</label>
            <input type="date" class="form-control form-control-sm" id="awal" name="awal" value="<?= esc($s['awal']) ?>">
        </div>
        <div class="lapangan">
            <label for="akhir">s/d</label>
            <input type="date" class="form-control form-control-sm" id="akhir" name="akhir" value="<?= esc($s['akhir']) ?>">
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
            <div class="tugas-aksi">
                <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-search"></i> Cari</button>
                <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/tugas')) ?>">Ulangi</a>
            </div>
        </div>
    </form>

    <div class="tugas-kartu">
        <?php if ($baris === []) : ?>
            <div class="tugas-kosong">
                <i class="fal fa-circle-check text-success"></i>
                Tidak ada tugas di sini. Antrean sedang bersih.
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle tugas-daftar tabel-rapi">
                    <thead>
                        <tr>
                            <th>Tugas</th>
                            <th>Status</th>
                            <th>Prioritas</th>
                            <th>Tenggat</th>
                            <th>Penggarap</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baris as $t) : ?>
                            <?php
                            $status   = (string) $t['status'];
                            $tenggat  = (string) ($t['tenggat'] ?? '');
                            $terlambat = in_array($status, ['queued', 'assigned', 'in_progress', 'waiting'], true)
                                && $tenggat !== '' && $tenggat < $hari_ini;
                            ?>
                            <tr>
                                <td>
                                    <a class="tugas-nama" href="<?= esc(site_url('admin/tugas/' . (int) $t['id_tugas'])) ?>">
                                        <?= esc($t['judul']) ?>
                                    </a>
                                    <div class="tugas-sub">
                                        <span>#<?= (int) $t['id_tugas'] ?></span>
                                        <span><?= esc($pilihan['jenis'][$t['jenis']] ?? $t['jenis']) ?></span>
                                        <?php if ($t['nama_pelanggan']) : ?><span><i class="fal fa-user"></i> <?= esc($t['nama_pelanggan']) ?></span><?php endif; ?>
                                        <?php if ($t['invoice_seri']) : ?><span><i class="fal fa-receipt"></i> <?= esc($t['invoice_seri']) ?></span><?php endif; ?>
                                        <?php if ($t['nama_juragan']) : ?><span><i class="fal fa-store"></i> <?= esc($t['nama_juragan']) ?></span><?php endif; ?>
                                    </div>
                                </td>
                                <td><span class="pil-status s-<?= esc($status) ?>"><?= esc($label_status($status)) ?></span></td>
                                <td><span class="pil-prioritas p-<?= esc($t['prioritas']) ?>"><?= esc($pilihan['prioritas'][$t['prioritas']] ?? $t['prioritas']) ?></span></td>
                                <td class="tugas-teenggat <?= $terlambat ? 'lewat' : '' ?>">
                                    <?= $tenggat === '' ? '-' : esc(tanggal_rincian($tenggat)) ?>
                                    <?php if ($terlambat) : ?><i class="fal fa-exclamation-triangle"></i><?php endif; ?>
                                </td>
                                <td class="text-muted">
                                    <?= $t['nama_dikerjakan'] ? esc($t['nama_dikerjakan']) : '<span class="text-muted">belum ada</span>' ?>
                                </td>
                                <td class="text-center">
                                    <div class="tugas-aksi">
                                        <?php if ($status === 'queued') : ?>
                                            <form method="post" action="<?= esc(site_url('admin/tugas/ambil/' . (int) $t['id_tugas'])) ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">Ambil</button>
                                            </form>
                                        <?php endif; ?>
                                        <a class="btn btn-sm btn-link text-decoration-none"
                                            href="<?= esc(site_url('admin/tugas/' . (int) $t['id_tugas'])) ?>">Lihat</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($total > $batas) : ?>
            <div class="tugas-pager d-flex flex-wrap align-items-center gap-2">
                <span class="small text-muted"><?= (int) $total ?> tugas &middot; halaman <?= (int) $page ?></span>
                <div class="ms-auto">
                    <?= \Config\Services::pager()->makeLinks($page, $batas, $total, 'front_full') ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- modal buat -->
<div class="modal fade" id="modalBuatTugas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <?= form_open('admin/tugas/simpan') ?>
            <div class="modal-header">
                <h5 class="modal-title">Buat Tugas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small fw-bold" for="judul">Judul <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="judul" name="judul" maxlength="200" required>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold" for="f-jenis">Jenis</label>
                        <select class="form-select form-select-sm" id="f-jenis" name="jenis">
                            <?php foreach ($pilihan['jenis'] as $kode => $nama) : ?>
                                <option value="<?= esc($kode) ?>" <?= $kode === 'followup_pelanggan' ? 'selected' : '' ?>><?= esc($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold" for="f-prioritas">Prioritas</label>
                        <select class="form-select form-select-sm" id="f-prioritas" name="prioritas">
                            <?php foreach ($pilihan['prioritas'] as $kode => $nama) : ?>
                                <option value="<?= esc($kode) ?>" <?= $kode === 'normal' ? 'selected' : '' ?>><?= esc($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <label class="form-label small fw-bold" for="f-tenggat">Tenggat</label>
                        <input type="date" class="form-control form-control-sm" id="f-tenggat" name="tenggat">
                    </div>
                    <?php if (count($toko) > 1) : ?>
                        <div class="col-6">
                            <label class="form-label small fw-bold" for="f-toko">Toko</label>
                            <select class="form-select form-select-sm" id="f-toko" name="juragan_id">
                                <option value="0">Bukan tugas toko tertentu</option>
                                <?php foreach ($toko as $id_toko => $nama_t) : ?>
                                    <option value="<?= (int) $id_toko ?>"><?= esc($nama_t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mt-2">
                    <label class="form-label small fw-bold" for="f-seri">Nomor nota (opsional)</label>
                    <input type="text" class="form-control form-control-sm" id="f-seri" name="seri" maxlength="14"
                        placeholder="contoh: AM1792238626">
                </div>
                <div class="mt-2">
                    <label class="form-label small fw-bold" for="f-deskripsi">Catatan</label>
                    <textarea class="form-control form-control-sm" id="f-deskripsi" name="deskripsi" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-primary"><i class="fal fa-save me-1"></i> Simpan</button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
