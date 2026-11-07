<?php

/**
 * Daftar SOP: dua panel bernomor, SOP dan PANDUAN.
 *
 * @var string                                    $title           judul halaman
 * @var array<string, list<array<string, mixed>>> $sop_grup
 * @var bool                                      $sop_kelola
 * @var bool                                      $kelola
 * @var string                                    $cari            kata kunci pencarian
 * @var array<int, string>                        $kategori_sop    dua pilihan kategori di form
 * @var string                                    $kabar           pesan sukses flash
 * @var string                                    $galat           pesan gagal flash
 */

$level = (string) session()->get('level');
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .sop-halaman { max-width: 980px; }
    /* tombol di kanan kepala halaman digeser kiri 72px: lonceng notifikasi desktop
       melayang di pojok kanan atas dan menutupinya begitu jendela tidak terlalu lebar */
    .sop-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; padding-right: 4.5rem; }
    /* blok judul boleh memendek (keterangan turun satu baris) supaya kedua tombol
       tetap satu baris di kanan, tidak jatuh jadi dua baris */
    .sop-head > .me-auto { flex: 1 1 0; min-width: 0; }
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
    .sop-kartu > .sop-panel-luar { padding: 1rem; }
    .sop-kosong { padding: 2.6rem 1rem; text-align: center; color: #94a3b8; }
    .sop-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }

    /* di layar sempit judul menempati barisnya sendiri lalu kedua tombol berbagi
       baris berikutnya, satu menempel tepi kiri dan satunya tepi kanan; jarak ke
       lonceng tidak diperlukan lagi karena lonceng melayang hanya di desktop */
    @media (max-width: 991.98px) {
        .sop-head { padding-right: 0; }
        .sop-head h1 > i { display: none; }
        .sop-head > .me-auto { flex: 1 1 100%; }
        .sop-head > a.btn, .sop-head > button.btn { flex: 1 1 0; min-width: 0; }

        /* baris cari ikut menutup lebar kartu: kolom input yang membesar, tombol
           tetap selebar teksnya di ujung kanan supaya tidak ada sisa kosong */
        .sop-saring > .lapangan:first-child { flex: 1 1 0; min-width: 0; }
        .sop-saring .form-control { max-width: none; width: 100%; }
    }
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
        <div class="lapangan">
            <label>&nbsp;</label>
            <!-- sejajar dan setinggi: tombol ikon saja lebih pendek karena garis
                 FontAwesome memakai line-height:1, jadi keduanya direntangkan -->
            <div class="d-inline-flex gap-2 align-items-stretch">
                <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-search"></i> Cari</button>
                <button type="button" class="btn btn-sm btn-dark d-inline-flex" data-saring-reset
                    title="Kosongkan saringan" aria-label="Kosongkan saringan"><i class="fal fa-sync"></i></button>
            </div>
        </div>
    </form>

    <div class="sop-kartu">
        <?php if ($sop_grup['SOP'] === [] && $sop_grup['PANDUAN'] === []) : ?>
            <div class="sop-kosong">
                <i class="fal fa-book-open"></i>
                Belum ada SOP<?= $kelola ? ' — buat dokumen pertama di tombol kanan atas' : '' ?>.
            </div>
        <?php else : ?>
            <?= $this->include('admin/sop/panel') ?>
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
                    <div class="row g-2">
                        <div class="col-12 col-sm-8">
                            <label class="form-label small fw-bold" for="sop-judul">Judul <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="sop-judul" name="judul" maxlength="200" required>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-bold" for="sop-kategori">Kategori</label>
                            <select class="form-select form-select-sm" id="sop-kategori" name="kategori">
                                <?php foreach ($kategori_sop as $kode) : ?>
                                    <option value="<?= esc($kode) ?>"><?= esc($kode) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small fw-bold" for="sop-isi">Isi</label>
                        <textarea class="form-control form-control-sm" id="sop-isi" name="isi" rows="10"
                            placeholder="Tulis satu aturan per baris. Setiap baris jadi satu poin."></textarea>
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
