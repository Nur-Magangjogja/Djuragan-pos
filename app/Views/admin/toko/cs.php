<?php

/**
 * Panel CS sebuah toko online: siapa yang menanganinya dan sudah sejauh mana
 * ia mengenal tokonya.
 *
 * @var array  $toko    baris toko
 * @var array  $daftar  baris crm_toko_akses + nama staf
 * @var array  $staf    id => nama CS yang belum ditautkan
 * @var array  $status  label status pengenalan
 * @var bool   $kelola  akun ini Admin/Superadmin?
 * @var string $kabar   pesan sukses flash (string kosong kalau tidak ada)
 * @var string $galat   pesan gagal flash (string kosong kalau tidak ada)
 */
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .cs-halaman { max-width: 1000px; }
    .c-kotak { background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem; padding: 1rem 1.15rem;
        box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.12); margin-bottom: 1rem; }
    .c-kotak h2 { margin: 0 0 .7rem; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #334155; }
    .c-table { width: 100%; font-size: .85rem; }
    .c-table th { font-size: .7rem; text-transform: uppercase; color: #667085; letter-spacing: .04em; text-align: left; padding-bottom: .35rem; }
    .c-table td { padding: .5rem .35rem; border-top: 1px solid #f1f5f9; vertical-align: middle; }
    .c-nama { font-weight: 700; color: #0f172a; }
    .c-sub { font-size: .76rem; color: #64748b; }
    .pil { display: inline-block; padding: .16rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; }
    .pp-belum { background: #eef1f6; color: #475569; }
    .pp-sedang { background: #fef3e2; color: #b45309; }
    .pp-sudah { background: #e7f6ec; color: #14663a; }
    .c-form { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include((string) session()->get('level') === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl cs-halaman">
    <a class="mt-4 d-inline-flex align-items-center gap-2 text-decoration-none small" style="color:#64748b"
        href="<?= esc(site_url('admin/toko')) ?>">
        <i class="fal fa-arrow-alt-left"></i> Kembali ke daftar toko
    </a>

    <h1 class="h3 mt-2"><?= esc($toko['nama']) ?></h1>
    <p class="c-sub mb-3">
        <?= esc($toko['nama_juragan']) ?>
        &middot; <?= esc($toko['platform'] !== '' ? $toko['platform'] : 'umum') ?>
        <?php if ($toko['url']) : ?>
            &middot; <a href="<?= esc($toko['url']) ?>" target="_blank" rel="noopener noreferrer"><?= esc($toko['url']) ?></a>
        <?php endif; ?>
    </p>

    <?php if ($kabar !== '') : ?><div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div><?php endif; ?>
    <?php if ($galat !== '') : ?><div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div><?php endif; ?>

    <div class="c-kotak">
        <h2><i class="fal fa-users me-1"></i> CS yang memegang toko ini</h2>

        <?php if ($daftar === []) : ?>
            <p class="c-sub">Belum ada CS yang ditautkan.</p>
        <?php else : ?>
            <table class="c-table">
                <thead>
                    <tr><th>CS</th><th>Status</th><th>Mulai / selesai</th><th>Catatan</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar as $a) : ?>
                        <tr>
                            <td>
                                <span class="c-nama"><?= esc($a['nama_staf']) ?></span>
                                <div class="c-sub"><?= esc($a['level']) ?><?php if ($a['diverifikasi_oleh']) : ?> &middot; diverifikasi <?= esc($a['nama_verifikator']) ?><?php endif; ?></div>
                            </td>
                            <td>
                                <?php if ($kelola) : ?>
                                    <?= form_open('admin/toko/status-orang', ['class' => 'c-form']) ?>
                                        <input type="hidden" name="id_toko" value="<?= (int) $toko['id_toko'] ?>">
                                        <input type="hidden" name="user_id" value="<?= (int) $a['user_id'] ?>">
                                        <input type="text" class="form-control form-control-sm" name="materi" style="max-width:150px"
                                            value="<?= esc($a['materi']) ?>" placeholder="materi">
                                        <input type="text" class="form-control form-control-sm" name="catatan" style="max-width:150px"
                                            value="<?= esc($a['catatan']) ?>" placeholder="catatan / kendala">
                                        <select class="form-select form-select-sm" name="pengenalan" style="max-width:160px">
                                            <?php foreach ($status as $kode => $nama) : ?>
                                                <option value="<?= esc($kode) ?>" <?= (string) $a['pengenalan'] === $kode ? 'selected' : '' ?>><?= esc($nama) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-dark rounded-pill">Simpan</button>
                                    <?= form_close() ?>
                                <?php else : ?>
                                    <span class="pil pp-<?= esc($a['pengenalan']) ?>"><?= esc($status[$a['pengenalan']] ?? $a['pengenalan']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="c-sub"><?= esc($a['mulai'] ?: '-') ?> &rarr; <?= esc($a['selesai'] ?: '-') ?></td>
                            <td class="c-sub"><?= esc($a['materi'] ?: '') ?> <?= esc($a['catatan'] ?: '') ?></td>
                            <td class="text-end">
                                <?php if ($kelola) : ?>
                                    <?= form_open('admin/toko/copot-cs') ?>
                                        <input type="hidden" name="id_toko" value="<?= (int) $toko['id_toko'] ?>">
                                        <input type="hidden" name="user_id" value="<?= (int) $a['user_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-link text-danger text-decoration-none">Lepas</button>
                                    <?= form_close() ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($kelola && $staf !== []) : ?>
        <div class="c-kotak">
            <h2><i class="fal fa-user-plus me-1"></i> Tambahkan CS</h2>
            <?= form_open('admin/toko/tambah-cs', ['class' => 'c-form']) ?>
                <input type="hidden" name="id_toko" value="<?= (int) $toko['id_toko'] ?>">
                <select class="form-select form-select-sm" name="user_id" style="max-width:260px" required>
                    <option value="">Pilih CS</option>
                    <?php foreach ($staf as $id => $nama) : ?>
                        <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-primary rounded-pill">Tautkan</button>
            <?= form_close() ?>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
