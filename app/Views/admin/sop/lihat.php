<?php

/**
 * Satu dokumen SOP: isinya untuk dibaca, bentuknya untuk Admin.
 *
 * @var string                     $title         judul halaman
 * @var array<string, mixed>       $sop           isi dokumen
 * @var array<string, string>      $untuk         label peran, dipakai di baris meta
 * @var bool                       $kelola        akun ini boleh menulis SOP?
 * @var array<int, string>         $kategori_sop  dua pilihan kategori di form
 * @var string                     $sop_kategori  kategori dokumen ini, sudah dinormalkan
 * @var string                     $sop_teks      isi untuk textarea form
 * @var list<string>               $sop_poin      isi untuk dibaca
 * @var string                     $kabar         pesan sukses flash
 * @var string                     $galat         pesan gagal flash
 */
$level  = (string) session()->get('level');
$dibaca = static function ($detik): string {
    return $detik !== null && (int) $detik > 0 ? date('d M Y H:i', (int) $detik) : '-';
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .sop-halaman { max-width: 880px; }
    .sop-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .sop-kartu {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        overflow: hidden; margin-bottom: 1rem;
    }
    .sop-meta { display: flex; flex-wrap: wrap; gap: .3rem .9rem; padding: .7rem 1.25rem; border-bottom: 1px solid #eaecf0; font-size: .78rem; color: #667085; }
    .pil-sop { display: inline-block; padding: .16rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: #eef1f6; color: #475569; }
    .pil-mati { background: #fdf1f0; color: #b81c16; }
    .sop-form { padding: 1.1rem 1.25rem; }
    .sop-form label { font-size: .7rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl sop-halaman">

    <div class="sop-head">
        <div class="me-auto">
            <h1 class="h4 mt-5"><?= esc($title) ?></h1>
        </div>
        <?php if ((int) $sop['aktif'] !== 1) : ?>
            <span class="pil-sop pil-mati">nonaktif</span>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/sop')) ?>">
            <i class="fal fa-arrow-left me-1"></i> Daftar SOP
        </a>
    </div>

    <?php if ($kabar !== '') : ?>
        <div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div>
    <?php endif; ?>
    <?php if ($galat !== '') : ?>
        <div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div>
    <?php endif; ?>

    <div class="sop-kartu">
        <div class="sop-meta">
            <span class="pil-sop"><?= esc($sop_kategori) ?></span>
            <span class="pil-sop"><?= esc($untuk[$sop['untuk']] ?? $sop['untuk']) ?></span>
            <span>diubah <?= $dibaca($sop['updated_at']) ?><?= $sop['nama_diubah'] ? ' oleh ' . esc($sop['nama_diubah']) : '' ?></span>
        </div>
        <?php if ($kelola) : ?>
            <?= form_open('admin/sop/simpan', ['class' => 'sop-form']) ?>
                <input type="hidden" name="id_sop" value="<?= (int) $sop['id_sop'] ?>">
                <input type="hidden" name="aktif" value="<?= (int) $sop['aktif'] ?>">
                <div class="row g-3">
                    <div class="col-12 col-sm-8">
                        <label class="form-label" for="sop-judul">Judul <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="sop-judul" name="judul"
                            maxlength="200" value="<?= esc($sop['judul']) ?>" required>
                    </div>
                    <div class="col-12 col-sm-4">
                        <label class="form-label" for="sop-kategori">Kategori</label>
                        <select class="form-select form-select-sm" id="sop-kategori" name="kategori">
                            <?php foreach ($kategori_sop as $kode) : ?>
                                <option value="<?= esc($kode) ?>" <?= $sop_kategori === $kode ? 'selected' : '' ?>><?= esc($kode) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label" for="sop-isi">Isi</label>
                    <textarea class="form-control form-control-sm" id="sop-isi" name="isi" rows="14"
                        placeholder="Tulis satu aturan per baris. Setiap baris jadi satu poin."><?= esc($sop_teks) ?></textarea>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fal fa-save me-1"></i> Simpan</button>
                </div>
            <?= form_close() ?>
        <?php else : ?>
            <div class="sop-baca">
                <?php if ($sop_poin === []) : ?>
                    <p class="sop-kosong-isi">Belum ada isi.</p>
                <?php else : ?>
                    <ul class="sop-poin">
                        <?php foreach ($sop_poin as $p) : ?>
                            <li><?= esc($p) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($kelola) : ?>
        <div class="sop-kartu">
            <div class="sop-form d-flex flex-wrap align-items-center gap-2">
                <span class="small text-muted me-auto">
                    Menonaktifkan SOP tidak menghapusnya; dokumen tetap bisa dibuka lewat tautan dan riwayat tugas tidak ikut berubah.
                </span>
                <?= form_open('admin/sop/aktif') ?>
                    <input type="hidden" name="id_sop" value="<?= (int) $sop['id_sop'] ?>">
                    <input type="hidden" name="aktif" value="<?= (int) $sop['aktif'] === 1 ? '0' : '1' ?>">
                    <button type="submit" class="btn btn-sm <?= (int) $sop['aktif'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?> rounded-pill text-nowrap">
                        <?= (int) $sop['aktif'] === 1 ? 'Matikan dokumen ini' : 'Nyalakan lagi' ?>
                    </button>
                <?= form_close() ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?= $this->endSection() ?>
