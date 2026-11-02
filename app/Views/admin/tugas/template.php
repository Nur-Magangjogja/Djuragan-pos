<?php

/**
 * Kelola templat tugas: daftar, editor butir, dan tombol tuang ke antrean.
 *
 * @var string                     $title   judul halaman
 * @var list<array<string, mixed>> $daftar  hasil TugasTemplateModel::daftar()
 * @var array<string, mixed>|null  $edit
 * @var list<array<string, mixed>> $item    selalu 8 baris (yang kosong dibuang model)
 * @var array<string, array>       $pilihan
 * @var array<int, string>         $toko
 * @var string                     $kabar   pesan sukses flash (string kosong kalau tidak ada)
 * @var string                     $galat   pesan gagal flash (string kosong kalau tidak ada)
 */

$level   = (string) session()->get('level');
$slot    = count($item);
$id_edit = $edit !== null ? (int) $edit['id_template'] : 0;
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .tpl-halaman { max-width: 1060px; }
    .tpl-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .tpl-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }

    .tpl-kartu {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        overflow: hidden; margin-bottom: 1rem;
    }
    .tpl-kartu > .tpl-judul {
        padding: .7rem 1rem; border-bottom: 1px solid #eaecf0;
        font-size: .78rem; font-weight: 700; color: #344054;
        text-transform: uppercase; letter-spacing: .05em;
    }

    .tpl-baris { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; padding: .8rem 1rem; border-bottom: 1px solid #f1f5f9; }
    .tpl-baris:last-child { border-bottom: 0; }
    .tpl-nama { font-weight: 700; color: #0f172a; font-size: .9rem; text-decoration: none; }
    .tpl-sub { font-size: .76rem; color: #64748b; margin-top: .12rem; }
    .tpl-pakai { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .4rem; margin-left: auto; }
    .tpl-pakai .lapangan { display: flex; flex-direction: column; gap: .15rem; }
    .tpl-pakai label { font-size: .66rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .tpl-pakai .form-select, .tpl-pakai .form-control { min-width: 130px; }

    .tpl-item { padding: .75rem 1rem; border-bottom: 1px solid #f1f5f9; }
    .tpl-item:last-of-type { border-bottom: 0; }
    .tpl-baris-item { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .45rem; }
    .tpl-baris-item label { display: block; font-size: .66rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .tpl-baris-item .lebar { flex: 1; min-width: 220px; }
    .tpl-baris-item .kecil { width: 116px; }
    .tpl-nomor { font-size: .7rem; color: #94a3b8; font-weight: 700; }
    .tpl-kosong { padding: 2.2rem 1rem; text-align: center; color: #94a3b8; }
    .tpl-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }
    .pil-tpl { display: inline-block; padding: .16rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: #eef1f6; color: #475569; }
    .pil-mati { background: #fdf1f0; color: #b81c16; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl tpl-halaman">

    <div class="tpl-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><i class="fal fa-layer-group me-1 text-brand-500"></i> <?= esc($title) ?></h1>
            <p>Pekerjaan yang selalu berulang cukup ditulis sekali di sini, lalu dituang ke daftar Tugas lain-lain untuk satu toko pada satu tanggal.</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/tugas')) ?>">
            <i class="fal fa-arrow-left me-1"></i> Tugas lain-lain
        </a>
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/sop')) ?>">
            <i class="fal fa-book-open me-1"></i> Pustaka SOP
        </a>
        <a class="btn btn-sm btn-primary" href="<?= esc(site_url('admin/tugas/template')) ?>">
            <i class="fal fa-plus me-1"></i> Templat Baru
        </a>
    </div>

    <?php if ($kabar !== '') : ?>
        <div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div>
    <?php endif; ?>
    <?php if ($galat !== '') : ?>
        <div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div>
    <?php endif; ?>

    <div class="tpl-kartu">
        <div class="tpl-judul">Daftar templat (<?= count($daftar) ?>)</div>
        <?php if ($daftar === []) : ?>
            <div class="tpl-kosong">
                <i class="fal fa-layer-group"></i>
                Belum ada templat. Susun satu di form di bawah, misalnya "Pagi: buka toko + cek stok".
            </div>
        <?php else : ?>
            <?php foreach ($daftar as $t) : ?>
                <?php $mati = (int) $t['aktif'] !== 1; ?>
                <div class="tpl-baris">
                    <div class="me-auto">
                        <a class="tpl-nama" href="<?= esc(site_url('admin/tugas/template?templat=' . (int) $t['id_template'])) ?>">
                            <?= esc($t['nama']) ?>
                        </a>
                        <div class="tpl-sub">
                            <span class="pil-tpl"><?= (int) $t['jumlah_item'] ?> butir</span>
                            <?php if ($mati) : ?><span class="pil-tpl pil-mati">nonaktif</span><?php endif; ?>
                            <?php if ($t['keterangan']) : ?><span><?= esc($t['keterangan']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <?php if (! $mati && (int) $t['jumlah_item'] > 0) : ?>
                        <?= form_open('admin/tugas/template-pakai', ['class' => 'tpl-pakai']) ?>
                            <input type="hidden" name="id_template" value="<?= (int) $t['id_template'] ?>">
                            <?php if ($toko !== []) : ?>
                                <div class="lapangan">
                                    <label for="toko-<?= (int) $t['id_template'] ?>">Toko</label>
                                    <select class="form-select form-select-sm" id="toko-<?= (int) $t['id_template'] ?>" name="toko_id">
                                        <option value="0">Umum (tanpa toko)</option>
                                        <?php foreach ($toko as $id_toko => $nama_t) : ?>
                                            <option value="<?= (int) $id_toko ?>"><?= esc($nama_t) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else : ?>
                                <input type="hidden" name="toko_id" value="0">
                            <?php endif; ?>
                            <div class="lapangan">
                                <label for="tgl-<?= (int) $t['id_template'] ?>">Tanggal dasar</label>
                                <input type="date" class="form-control form-control-sm" id="tgl-<?= (int) $t['id_template'] ?>"
                                    name="tanggal" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="lapangan">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-sm btn-dark text-nowrap"><i class="fal fa-list-check me-1"></i> Tuang ke antrean</button>
                            </div>
                        <?= form_close() ?>
                    <?php endif; ?>
                    <?= form_open('admin/tugas/template-aktif', ['class' => 'd-inline-flex']) ?>
                        <input type="hidden" name="id_template" value="<?= (int) $t['id_template'] ?>">
                        <input type="hidden" name="aktif" value="<?= $mati ? '1' : '0' ?>">
                        <button type="submit" class="btn btn-sm btn-link text-decoration-none <?= $mati ? 'text-success' : 'text-danger' ?>">
                            <?= $mati ? 'Nyalakan' : 'Matikan' ?>
                        </button>
                    <?= form_close() ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="tpl-kartu">
        <div class="tpl-judul"><?= $id_edit > 0 ? 'Sunting templat #' . $id_edit : 'Templat baru' ?></div>
        <?= form_open('admin/tugas/template') ?>
            <input type="hidden" name="id_template" value="<?= $id_edit ?>">
            <input type="hidden" name="aktif" value="<?= $edit !== null ? (int) $edit['aktif'] : 1 ?>">
            <div class="tpl-item">
                <div class="tpl-baris-item">
                    <div class="lebar">
                        <label for="tpl-nama">Nama templat <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="tpl-nama" name="nama"
                            maxlength="120" value="<?= esc($edit['nama'] ?? '') ?>" required>
                    </div>
                    <div class="lebar">
                        <label for="tpl-ket">Keterangan</label>
                        <input type="text" class="form-control form-control-sm" id="tpl-ket" name="keterangan"
                            maxlength="255" value="<?= esc($edit['keterangan'] ?? '') ?>" placeholder="kapan templat ini dipakai">
                    </div>
                </div>
            </div>

            <?php for ($i = 0; $i < $slot; $i++) : ?>
                <?php $baris = $item[$i]; ?>
                <div class="tpl-item">
                    <div class="tpl-baris-item">
                        <div class="lebar">
                            <label for="judul-<?= $i ?>">
                                <span class="tpl-nomor"><?= $i + 1 ?>.</span> Judul tugas
                            </label>
                            <input type="text" class="form-control form-control-sm" id="judul-<?= $i ?>"
                                name="item[<?= $i ?>][judul]" maxlength="200" value="<?= esc($baris['judul']) ?>"
                                placeholder="kosongkan kalau tidak dipakai">
                        </div>
                        <div class="kecil">
                            <label for="jenis-<?= $i ?>">Jenis</label>
                            <select class="form-select form-select-sm" id="jenis-<?= $i ?>" name="item[<?= $i ?>][jenis]">
                                <?php foreach ($pilihan['jenis'] as $kode => $nama) : ?>
                                    <option value="<?= esc($kode) ?>" <?= (string) $baris['jenis'] === $kode ? 'selected' : '' ?>><?= esc($nama) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="kecil">
                            <label for="prio-<?= $i ?>">Prioritas</label>
                            <select class="form-select form-select-sm" id="prio-<?= $i ?>" name="item[<?= $i ?>][prioritas]">
                                <?php foreach ($pilihan['prioritas'] as $kode => $nama) : ?>
                                    <option value="<?= esc($kode) ?>" <?= (string) $baris['prioritas'] === $kode ? 'selected' : '' ?>><?= esc($nama) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="kecil">
                            <label for="hari-<?= $i ?>">Tenggat +hari</label>
                            <input type="number" class="form-control form-control-sm" id="hari-<?= $i ?>"
                                name="item[<?= $i ?>][tenggat_hari]" min="0" max="365" value="<?= (int) $baris['tenggat_hari'] ?>">
                        </div>
                    </div>
                    <div class="tpl-baris-item mt-2">
                        <div class="lebar">
                            <label for="desk-<?= $i ?>">Catatan kerja (opsional)</label>
                            <textarea class="form-control form-control-sm" id="desk-<?= $i ?>" name="item[<?= $i ?>][deskripsi]"
                                rows="2" maxlength="2000"><?= esc($baris['deskripsi'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>

            <div class="tpl-item d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fal fa-save me-1"></i> Simpan templat</button>
                <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/tugas/template')) ?>">Buat dari nol</a>
            </div>
        <?= form_close() ?>
    </div>

</div>

<?= $this->endSection() ?>
