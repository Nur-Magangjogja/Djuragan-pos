<?php

/**
 * Daftar toko online.
 *
 * Variabel di bawah disuntik Admin\Toko::index() lewat view() (extract), jadi analyzer
 * tidak menelusurinya dari file ini. Blok @var hanya memberi tahunya ke Intelephense.
 *
 * @var string $title   judul halaman
 * @var array $s        saringan aktif
 * @var array $baris    hasil TokoOnlineModel::daftar()
 * @var int   $total    jumlah baris
 * @var int   $page     halaman aktif
 * @var int   $batas    baris per halaman
 * @var array $toko     id juragan => nama
 * @var array $platform daftar marketplace
 * @var bool  $kelola   akun ini boleh mengelola toko?
 * @var string $kabar   pesan sukses flash (string kosong kalau tidak ada)
 * @var string $galat   pesan gagal flash (string kosong kalau tidak ada)
 */
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .toko-halaman { max-width: 1180px; }
    .toko-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .toko-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }
    .toko-saring { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .55rem; margin-bottom: 1rem; }
    .toko-saring .lapangan { display: flex; flex-direction: column; gap: .2rem; }
    .toko-saring label { font-size: .7rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .toko-saring .form-control, .toko-saring .form-select { max-width: 200px; }
    .t-kotak { background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.12); overflow: hidden; }
    .t-table { margin: 0; }
    .t-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; background: #f9fafb; }
    .t-table td { vertical-align: middle; font-size: .86rem; }
    .t-nama { font-weight: 700; color: #0f172a; }
    .t-sub { font-size: .76rem; color: #64748b; }
    .t-mati { opacity: .55; }
    .pil { display: inline-block; padding: .18rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; }
    .p-aktif { background: #e7f6ec; color: #14663a; }
    .p-mati { background: #eef1f6; color: #667085; }
    .p-platform { background: #e6f4fb; color: #0369a1; }
    .t-pager { padding: .8rem 1rem; border-top: 1px solid #eaecf0; }
    .t-kosong { padding: 2.6rem 1rem; text-align: center; color: #94a3b8; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include((string) session()->get('level') === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl toko-halaman">
    <div class="toko-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><i class="fal fa-store me-1 text-brand-500"></i> <?= esc($title) ?></h1>
            <p>Toko di tiap marketplace, siapa CS-nya, dan sudah seberapa jauh tokonya dikenali.</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/toko/pengenalan')) ?>">
            <i class="fal fa-id-card me-1"></i> Pengenalanku
        </a>
        <?php if ($kelola) : ?>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalToko">
                <i class="fal fa-plus"></i> Tambah toko
            </button>
        <?php endif; ?>
    </div>

    <?php if ($kabar !== '') : ?><div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div><?php endif; ?>
    <?php if ($galat !== '') : ?><div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div><?php endif; ?>

    <form class="toko-saring" method="get" action="<?= esc(site_url('admin/toko')) ?>">
        <div class="lapangan">
            <label for="cari">Cari</label>
            <input type="search" class="form-control form-control-sm" id="cari" name="cari" value="<?= esc($s['cari']) ?>"
                placeholder="nama toko, url, atau juragan">
        </div>
        <div class="lapangan">
            <label for="platform">Platform</label>
            <select class="form-select form-select-sm" id="platform" name="platform">
                <option value="">Semua platform</option>
                <?php foreach ($platform as $p) : ?>
                    <option value="<?= esc($p) ?>" <?= $s['platform'] === $p ? 'selected' : '' ?>><?= esc($p) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="lapangan">
            <label for="aktif">Status</label>
            <select class="form-select form-select-sm" id="aktif" name="aktif">
                <option value="">Semua</option>
                <option value="1" <?= $s['aktif'] === '1' ? 'selected' : '' ?>>Aktif</option>
                <option value="0" <?= $s['aktif'] === '0' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
        </div>
        <?php if (count($toko) > 1) : ?>
            <div class="lapangan">
                <label for="juragan">Juragan</label>
                <select class="form-select form-select-sm" id="juragan" name="juragan">
                    <option value="">Semua juragan</option>
                    <?php foreach ($toko as $id => $nama) : ?>
                        <option value="<?= (int) $id ?>" <?= (int) $s['juragan_id'] === (int) $id ? 'selected' : '' ?>><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="lapangan">
            <label>&nbsp;</label>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-search"></i> Cari</button>
                <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/toko')) ?>">Ulangi</a>
            </div>
        </div>
    </form>

    <div class="t-kotak">
        <?php if ($baris === []) : ?>
            <div class="t-kosong"><i class="fal fa-store" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
                Belum ada toko online yang dicatat di sini.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle t-table tabel-rapi">
                    <thead>
                        <tr>
                            <th>Toko</th>
                            <th>Juragan</th>
                            <th>Platform</th>
                            <th>CS</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baris as $t) : ?>
                            <tr class="<?= (int) $t['aktif'] === 1 ? '' : 't-mati' ?>">
                                <td>
                                    <span class="t-nama"><?= esc($t['nama']) ?></span>
                                    <div class="t-sub">
                                        <?php if ($t['url']) : ?>
                                            <a href="<?= esc($t['url']) ?>" target="_blank" rel="noopener noreferrer"><?= esc($t['url']) ?></a>
                                        <?php else : ?>
                                            tanpa url
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($t['catatan']) : ?>
                                        <div class="t-sub text-muted"><?= esc(mb_substr((string) $t['catatan'], 0, 90)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($t['nama_juragan']) ?></td>
                                <td><span class="pil p-platform"><?= esc($t['platform'] !== '' ? $t['platform'] : 'umum') ?></span></td>
                                <td class="t-sub">
                                    <?= (int) $t['cs_siap'] ?> dari <?= (int) $t['jumlah_cs'] ?> CS paham
                                </td>
                                <td>
                                    <span class="pil <?= (int) $t['aktif'] === 1 ? 'p-aktif' : 'p-mati' ?>">
                                        <?= (int) $t['aktif'] === 1 ? 'aktif' : 'nonaktif' ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($kelola) : ?>
                                        <div class="d-inline-flex gap-1">
                                            <a class="btn btn-sm btn-outline-secondary rounded-pill"
                                                href="<?= esc(site_url('admin/toko/cs/' . (int) $t['id_toko'])) ?>">CS</a>
                                            <button type="button" class="btn btn-sm btn-outline-dark rounded-pill" data-bs-toggle="modal"
                                                data-bs-target="#modalToko" data-toko='<?= json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                                                Sunting
                                            </button>
                                            <?= form_open('admin/toko/aktif') ?>
                                                <input type="hidden" name="id_toko" value="<?= (int) $t['id_toko'] ?>">
                                                <input type="hidden" name="aktif" value="<?= (int) $t['aktif'] === 1 ? 0 : 1 ?>">
                                                <button type="submit" class="btn btn-sm btn-link text-decoration-none">
                                                    <?= (int) $t['aktif'] === 1 ? 'Nonaktifkan' : 'Nyalakan' ?>
                                                </button>
                                            <?= form_close() ?>
                                        </div>
                                    <?php else : ?>
                                        <a class="btn btn-sm btn-outline-secondary rounded-pill"
                                            href="<?= esc(site_url('admin/toko/cs/' . (int) $t['id_toko'])) ?>">CS</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($total > $batas) : ?>
            <div class="t-pager d-flex flex-wrap align-items-center gap-2">
                <span class="small text-muted"><?= (int) $total ?> toko &middot; halaman <?= (int) $page ?></span>
                <div class="ms-auto"><?= \Config\Services::pager()->makeLinks($page, $batas, $total, 'front_full') ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($kelola) : ?>
<div class="modal fade" id="modalToko" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <?= form_open('admin/toko/simpan') ?>
            <div class="modal-header">
                <h5 class="modal-title">Toko online</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_toko" id="f-id" value="0">
                <div class="mb-2">
                    <label class="form-label small fw-bold" for="f-nama">Nama toko <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="f-nama" name="nama" maxlength="120" required>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold" for="f-juragan">Juragan</label>
                        <select class="form-select form-select-sm" id="f-juragan" name="juragan_id" required>
                            <?php foreach ($toko as $id => $nama) : ?>
                                <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold" for="f-platform">Platform</label>
                        <select class="form-select form-select-sm" id="f-platform" name="platform">
                            <option value="">umum / sendiri</option>
                            <?php foreach ($platform as $p) : ?>
                                <option value="<?= esc($p) ?>"><?= esc($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-label small fw-bold" for="f-url">URL toko</label>
                    <input type="url" class="form-control form-control-sm" id="f-url" name="url" maxlength="255"
                        placeholder="https://shopee.co.id/...">
                </div>
                <div class="mt-2">
                    <label class="form-label small fw-bold" for="f-catatan">Catatan operasional</label>
                    <textarea class="form-control form-control-sm" id="f-catatan" name="catatan" rows="3"
                        placeholder="jam balas chat, kurir langganan, hal yang mudah salah"></textarea>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="f-aktif" name="aktif" value="1" checked>
                    <label class="form-check-label small" for="f-aktif">Toko ini masih berjalan</label>
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

<?php if ($kelola) : ?>
<?= $this->section('js') ?>
<script>
    // satu modal untuk tambah dan sunting: tombol Sunting menyalin baris tabel ke form
    document.addEventListener('show.bs.modal', function (e) {
        if (e.target.id !== 'modalToko' || !e.relatedTarget) return;

        var isi = e.relatedTarget.getAttribute('data-toko');
        var form = e.target.querySelector('form');
        if (!isi || !form) return;

        var t = JSON.parse(isi);
        form.querySelector('#f-id').value = t.id_toko;
        form.querySelector('#f-nama').value = t.nama;
        form.querySelector('#f-url').value = t.url || '';
        form.querySelector('#f-catatan').value = t.catatan || '';
        form.querySelector('#f-aktif').checked = String(t.aktif) === '1';

        ['f-juragan', 'f-platform'].forEach(function (id) {
            var el = form.querySelector('#' + id);
            var kunci = id === 'f-juragan' ? 'juragan_id' : 'platform';
            for (var i = 0; i < el.options.length; i++) {
                if (String(el.options[i].value) === String(t[kunci] || '')) { el.selectedIndex = i; }
            }
        });
    });
</script>
<?= $this->endSection() ?>
<?php endif; ?>
