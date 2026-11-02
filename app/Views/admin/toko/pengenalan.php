<?php

/**
 * Pengenalan toko untuk CS: daftar toko yang ia pegang + status pengenalannya.
 *
 * Semua variabel di bawah disuntik Admin\Toko::pengenalan() lewat view(); keterangan
 * tipe hanya untuk analyzer, bukan kode yang dijalankan.
 *
 * @var string $title  judul halaman
 * @var array  $toko   hasil TokoOnlineModel::milikUser()
 * @var array  $status TokoOnlineModel::PENGENALAN
 * @var string $kabar  pesan sukses flash (string kosong kalau tidak ada)
 * @var string $galat  pesan gagal flash (string kosong kalau tidak ada)
 */
$belum = count(array_filter($toko, static fn ($t) => ($t['pengenalan_saya'] ?? 'belum') !== 'sudah'));
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .pengenalan-halaman { max-width: 940px; }
    .p-kotak { background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem; padding: 1rem 1.15rem;
        box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.12); margin-bottom: .9rem; }
    .p-atas { display: flex; flex-wrap: wrap; align-items: baseline; gap: .5rem; }
    .p-atas h2 { margin: 0; font-size: .98rem; font-weight: 800; color: #0f172a; }
    .p-sub { font-size: .78rem; color: #64748b; }
    .p-pill { display: inline-block; margin-left: auto; padding: .18rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; }
    .pp-belum { background: #eef1f6; color: #475569; }
    .pp-sedang { background: #fef3e2; color: #b45309; }
    .pp-sudah { background: #e7f6ec; color: #14663a; }
    .p-baris { display: flex; flex-wrap: wrap; gap: .5rem; align-items: flex-end; margin-top: .7rem; }
    .p-baris .lapangan { display: flex; flex-direction: column; gap: .2rem; flex: 1 1 180px; min-width: 0; }
    .p-baris label { font-size: .68rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .p-hint { font-size: .82rem; color: #64748b; margin: .3rem 0 1rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include((string) session()->get('level') === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl pengenalan-halaman">
    <h1 class="h3 mt-5"><i class="fal fa-id-card me-1 text-brand-500"></i> <?= esc($title) ?></h1>
    <p class="p-hint">
        <?= $belum === 0 ? 'Semua tokomu sudah ditandai dipahami.' : $belum . ' toko belum ditandai dipahami.' ?>
        Ini catatan pengetahuan, bukan hak akses: kamu tetap boleh bekerja di toko yang belum kamu pelajari.
        Pemeriksaan harian tetap jalan walau tokonya sudah dipahami.
    </p>

    <?php if ($kabar !== '') : ?><div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div><?php endif; ?>
    <?php if ($galat !== '') : ?><div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div><?php endif; ?>

    <?php if ($toko === []) : ?>
        <div class="p-kotak text-muted">Belum ada toko online yang ditautkan ke akunmu. Minta Admin menautkannya lewat halaman Toko Online.</div>
    <?php endif; ?>

    <?php foreach ($toko as $t) : ?>
        <?php $s = (string) ($t['pengenalan_saya'] ?? 'belum'); ?>
        <div class="p-kotak">
            <div class="p-atas">
                <h2><?= esc($t['nama']) ?></h2>
                <span class="p-sub"><?= esc($t['nama_juragan']) ?>
                    <?php if ($t['platform'] !== '') : ?> &middot; <?= esc($t['platform']) ?><?php endif; ?></span>
                <span class="p-pill pp-<?= esc($s) ?>"><?= esc($status[$s] ?? $s) ?></span>
            </div>

            <?php if ($t['url'] || $t['catatan']) : ?>
                <div class="p-sub mt-1">
                    <?php if ($t['url']) : ?><a href="<?= esc($t['url']) ?>" target="_blank" rel="noopener noreferrer"><?= esc($t['url']) ?></a><?php endif; ?>
                    <?php if ($t['catatan']) : ?><div class="mt-1"><?= esc($t['catatan']) ?></div><?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($t['mulai_saya'] || $t['selesai_saya']) : ?>
                <div class="p-sub mt-1">
                    mulai <?= esc($t['mulai_saya'] ?: '-') ?> &middot; selesai <?= esc($t['selesai_saya'] ?: '-') ?>
                </div>
            <?php endif; ?>

            <?= form_open('admin/toko/status') ?>
                <input type="hidden" name="id_toko" value="<?= (int) $t['id_toko'] ?>">
                <div class="p-baris">
                    <div class="lapangan" style="flex:0 0 190px">
                        <label for="st-<?= (int) $t['id_toko'] ?>">Statusku</label>
                        <select class="form-select form-select-sm" id="st-<?= (int) $t['id_toko'] ?>" name="pengenalan">
                            <?php foreach ($status as $kode => $nama) : ?>
                                <option value="<?= esc($kode) ?>" <?= $s === $kode ? 'selected' : '' ?>><?= esc($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="lapangan">
                        <label for="mt-<?= (int) $t['id_toko'] ?>">Yang sudah dipelajari</label>
                        <input type="text" class="form-control form-control-sm" id="mt-<?= (int) $t['id_toko'] ?>" name="materi"
                            maxlength="500" value="<?= esc($t['materi_saya'] ?? '') ?>" placeholder="katalog, ukuran, kurir, nada bahasa">
                    </div>
                    <div class="lapangan">
                        <label for="ct-<?= (int) $t['id_toko'] ?>">Kendala</label>
                        <input type="text" class="form-control form-control-sm" id="ct-<?= (int) $t['id_toko'] ?>" name="catatan"
                            maxlength="500" value="<?= esc($t['catatan_saya'] ?? '') ?>" placeholder="yang masih bikin ragu">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill">Simpan</button>
                </div>
            <?= form_close() ?>
        </div>
    <?php endforeach; ?>
</div>

<?= $this->endSection() ?>
