<?php

/**
 * Daftar SOP.
 *
 * @var string                    $title           judul halaman
 * @var list<array<string, mixed>> $baris          baris hasil query
 * @var string                    $cari            kata kunci pencarian
 * @var string                    $kategori        kelompok terpilih
 * @var array<int, string>        $daftar_kategori daftar kelompok yang pernah dipakai
 * @var array<string, string>     $untuk           peta kode -> label peran
 * @var bool                      $kelola          apakah boleh mengelola
 * @var string                    $kabar           pesan sukses flash
 * @var string                    $galat           pesan gagal flash
 */

$level = (string) session()->get('level');
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .sop-halaman { max-width: 980px; }
    .sop-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .sop-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }
    .sop-saring { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .55rem; margin-bottom: 1rem; }
    .sop-saring .lapangan { display: flex; flex-direction: column; gap: .2rem; }
    .sop-saring label { font-size: .7rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .sop-saring .form-control, .sop-saring .form-select { max-width: 210px; }

    .sop-kartu {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        overflow: hidden;
    }
    .sop-baris { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; padding: .85rem 1rem; border-bottom: 1px solid #f1f5f9; }
    .sop-baris:last-child { border-bottom: 0; }
    .sop-judul { font-weight: 700; color: #0f172a; text-decoration: none; font-size: .92rem; }
    .sop-judul:hover { color: #da251d; }
    .sop-sub { font-size: .76rem; color: #64748b; display: flex; flex-wrap: wrap; gap: .2rem .6rem; margin-top: .15rem; }
    .sop-mati .sop-judul { color: #94a3b8; text-decoration: line-through; }
    .sop-aksi { display: inline-flex; align-items: center; gap: .35rem; margin-left: auto; }
    .pil-sop { display: inline-block; padding: .16rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: #eef1f6; color: #475569; }
    .pil-mati { background: #fdf1f0; color: #b81c16; }
    .sop-kosong { padding: 2.6rem 1rem; text-align: center; color: #94a3b8; }
    .sop-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl sop-halaman">

    <div class="sop-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><i class="fal fa-book-open me-1 text-brand-500"></i> <?= esc($title) ?></h1>
            <p>Aturan kerja yang sama untuk semua CS. Yang sudah dimatikan tidak hilang, hanya tidak ditampilkan ke CS.</p>
        </div>
        <?php if ($kelola) : ?>
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/tugas/template')) ?>">
                <i class="fal fa-layer-group me-1"></i> Templat Tugas
            </a>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalSop">
                <i class="fal fa-plus"></i> Buat SOP
            </button>
        <?php endif; ?>
    </div>

    <?php if ($kabar !== '') : ?>
        <div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div>
    <?php endif; ?>
    <?php if ($galat !== '') : ?>
        <div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div>
    <?php endif; ?>

    <form class="sop-saring" method="get" action="<?= esc(site_url('admin/sop')) ?>">
        <div class="lapangan">
            <label for="cari">Cari</label>
            <input type="search" class="form-control form-control-sm" id="cari" name="cari" value="<?= esc($cari) ?>"
                placeholder="judul atau isi">
        </div>
        <?php if ($daftar_kategori !== []) : ?>
            <div class="lapangan">
                <label for="kategori">Kelompok</label>
                <select class="form-select form-select-sm" id="kategori" name="kategori">
                    <option value="">Semua kelompok</option>
                    <?php foreach ($daftar_kategori as $k) : ?>
                        <option value="<?= esc($k) ?>" <?= $kategori === $k ? 'selected' : '' ?>><?= esc($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="lapangan">
            <label>&nbsp;</label>
            <div class="d-inline-flex gap-2 align-items-center">
                <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-search"></i> Cari</button>
                <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/sop')) ?>">Ulangi</a>
            </div>
        </div>
    </form>

    <div class="sop-kartu">
        <?php if ($baris === []) : ?>
            <div class="sop-kosong">
                <i class="fal fa-book-open"></i>
                Belum ada SOP<?= $kelola ? ' — buat dokumen pertama di tombol kanan atas' : '' ?>.
            </div>
        <?php else : ?>
            <?php foreach ($baris as $s) : ?>
                <?php $mati = (int) $s['aktif'] !== 1; ?>
                <div class="sop-baris <?= $mati ? 'sop-mati' : '' ?>">
                    <div class="me-auto">
                        <a class="sop-judul" href="<?= esc(site_url('admin/sop/' . (int) $s['id_sop'])) ?>"><?= esc($s['judul']) ?></a>
                        <div class="sop-sub">
                            <span class="pil-sop"><?= esc($s['kategori']) ?></span>
                            <span class="pil-sop"><?= esc($untuk[$s['untuk']] ?? $s['untuk']) ?></span>
                            <span>urut <?= (int) $s['urut'] ?></span>
                            <?php if ($s['nama_diubah']) : ?><span>diubah <?= esc($s['nama_diubah']) ?></span><?php endif; ?>
                            <?php if ($mati) : ?><span class="pil-sop pil-mati">nonaktif</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="sop-aksi">
                        <?php if ($kelola) : ?>
                            <?= form_open('admin/sop/aktif') ?>
                                <input type="hidden" name="id_sop" value="<?= (int) $s['id_sop'] ?>">
                                <input type="hidden" name="aktif" value="<?= $mati ? '1' : '0' ?>">
                                <button type="submit" class="btn btn-sm btn-link text-decoration-none <?= $mati ? 'text-success' : 'text-danger' ?>">
                                    <?= $mati ? 'Nyalakan' : 'Matikan' ?>
                                </button>
                            <?= form_close() ?>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/sop/' . (int) $s['id_sop'])) ?>">Buka</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php if ($kelola) : ?>
    <div class="modal fade" id="modalSop" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <?= form_open('admin/sop/simpan') ?>
                <div class="modal-header">
                    <h5 class="modal-title">Buat SOP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_sop" value="0">
                    <input type="hidden" name="aktif" value="1">
                    <div class="mb-2">
                        <label class="form-label small fw-bold" for="sop-judul">Judul <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="sop-judul" name="judul" maxlength="200" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-4">
                            <label class="form-label small fw-bold" for="sop-kategori">Kelompok</label>
                            <input type="text" class="form-control form-control-sm" id="sop-kategori" name="kategori" maxlength="40" value="SOP">
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-bold" for="sop-untuk">Untuk</label>
                            <select class="form-select form-select-sm" id="sop-untuk" name="untuk">
                                <?php foreach ($untuk as $kode => $nama) : ?>
                                    <option value="<?= esc($kode) ?>"><?= esc($nama) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-bold" for="sop-urut">Urut</label>
                            <input type="number" class="form-control form-control-sm" id="sop-urut" name="urut" min="0" max="9999" value="0">
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small fw-bold" for="sop-isi">Isi</label>
                        <textarea class="form-control form-control-sm" id="sop-isi" name="isi" rows="10"
                            placeholder="Tulis langkahnya satu per satu. Baris baru ikut tersimpan."></textarea>
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
<?php endif; ?>

<?= $this->endSection() ?>
