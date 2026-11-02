<?php

/**
 * Templat butir checklist (khusus Admin & Superadmin).
 *
 * Butir tidak pernah dihapus, hanya dimatikan: sesi lama menyimpan teks butirnya
 * sendiri, tapi jejak item_id-nya tetap harus bisa ditelusuri.
 *
 * Variabel di bawah disuntik Admin\Checklist::aturan() lewat view(); blok @var
 * ini hanya memberi tahunya ke analyzer, bukan kode yang dijalankan.
 *
 * @var string $title  judul halaman
 * @var string $jenis  'toko' atau 'email'
 * @var array  $butir  hasil ChecklistModel::semuaButir()
 * @var array  $toko   id_toko => nama toko milik akun ini
 * @var array  $meta   ChecklistModel::JENIS
 * @var string $kabar  pesan sukses flash (string kosong kalau tidak ada)
 * @var string $galat  pesan gagal flash (string kosong kalau tidak ada)
 */

$level = (string) session()->get('level');
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .ckl-halaman { max-width: 1000px; }
    .ckl-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .ckl-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }

    .ckl-kartu {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        overflow: hidden; margin-bottom: 1rem;
    }
    .ckl-kartu > .ckl-judul {
        padding: .7rem 1rem; border-bottom: 1px solid #eaecf0;
        font-size: .78rem; font-weight: 700; color: #344054;
        text-transform: uppercase; letter-spacing: .05em;
    }

    .ckl-tab { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1rem; }
    .ckl-tab a {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .34rem .85rem; border-radius: 999px; font-size: .8rem; font-weight: 600;
        background: #f4f6f9; color: #475569; text-decoration: none; border: 1px solid #e2e8f0;
    }
    .ckl-tab a.aktif { background: #101828; border-color: #101828; color: #fff; }

    .ckl-form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .5rem; padding: 1rem; }
    .ckl-form .lapangan { display: flex; flex-direction: column; gap: .18rem; }
    .ckl-form label { font-size: .68rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .ckl-form .lebar { min-width: 260px; flex: 1; }
    .ckl-form .sempit { width: 74px; }

    .ckl-baris { padding: .9rem 1rem; border-bottom: 1px solid #f1f5f9; }
    .ckl-baris:last-child { border-bottom: 0; }
    .ckl-nonaktif { background: #fbfcfd; }
    .ckl-nonaktif .ckl-nomor, .ckl-nonaktif .ckl-judul-butir { opacity: .55; }
    .ckl-nomor { font-size: .74rem; color: #94a3b8; }
    .ckl-judul-butir { font-weight: 700; color: #0f172a; font-size: .9rem; }
    .ckl-sub { font-size: .76rem; color: #64748b; margin-top: .1rem; }
    .pil-ckl { display: inline-block; padding: .16rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; white-space: nowrap; }
    .p-umum { background: #eef1f6; color: #475569; }
    .p-khusus { background: #e6f4fb; color: #0369a1; }
    .p-wajib { background: #fef3e2; color: #b45309; }
    .p-mati { background: #fdf1f0; color: #b81c16; }
    .ckl-kosong { padding: 2.4rem 1rem; text-align: center; color: #94a3b8; }
    .ckl-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl ckl-halaman">

    <div class="ckl-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><i class="fal fa-list-check me-1 text-brand-500"></i> <?= esc($title) ?></h1>
            <p>Isi checklist yang muncul setiap sesi dibuka. Butir &quot;khusus toko&quot; hanya tampil di toko itu, selainnya tampil di semua toko.</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/checklist')) ?>">
            <i class="fal fa-arrow-left me-1"></i> Daftar sesi
        </a>
    </div>

    <?php if ($kabar !== '') : ?>
        <div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div>
    <?php endif; ?>
    <?php if ($galat !== '') : ?>
        <div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div>
    <?php endif; ?>

    <div class="ckl-tab">
        <?php foreach ($meta as $kode => $nama) : ?>
            <a class="<?= $jenis === $kode ? 'aktif' : '' ?>"
                href="<?= esc(site_url('admin/checklist/aturan?jenis=' . $kode)) ?>"><?= esc($nama) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="ckl-kartu">
        <div class="ckl-judul">Tambah butir baru</div>
        <?= form_open('admin/checklist/butir', ['class' => 'ckl-form']) ?>
            <input type="hidden" name="jenis" value="<?= esc($jenis) ?>">
            <input type="hidden" name="id_item" value="0">
            <div class="lapangan lebar">
                <label for="baru-judul">Pertanyaan / tindakan <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" id="baru-judul" name="judul" maxlength="200" required>
            </div>
            <div class="lapangan lebar">
                <label for="baru-hint">Petunjuk (opsional)</label>
                <input type="text" class="form-control form-control-sm" id="baru-hint" name="hint" maxlength="255"
                    placeholder="mis. cek juga kotak spam">
            </div>
            <?php if ($jenis === 'toko' && $toko !== []) : ?>
                <div class="lapangan">
                    <label for="baru-toko">Toko</label>
                    <select class="form-select form-select-sm" id="baru-toko" name="toko_id">
                        <option value="0">Umum (semua toko)</option>
                        <?php foreach ($toko as $id_toko => $nama_t) : ?>
                            <option value="<?= (int) $id_toko ?>"><?= esc($nama_t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else : ?>
                <input type="hidden" name="toko_id" value="0">
            <?php endif; ?>
            <div class="lapangan sempit">
                <label for="baru-urut">Urut</label>
                <input type="number" class="form-control form-control-sm" id="baru-urut" name="urut" min="0" max="9999" value="0">
            </div>
            <div class="lapangan">
                <label for="baru-wajib">Sifat</label>
                <select class="form-select form-select-sm" id="baru-wajib" name="wajib">
                    <option value="1">Wajib dijawab</option>
                    <option value="0">Boleh dilewati</option>
                </select>
            </div>
            <input type="hidden" name="aktif" value="1">
            <div class="lapangan">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-plus me-1"></i> Tambah</button>
            </div>
        <?= form_close() ?>
    </div>

    <div class="ckl-kartu">
        <div class="ckl-judul">Butir <?= esc($meta[$jenis]) ?> (<?= count($butir) ?>)</div>

        <?php if ($butir === []) : ?>
            <div class="ckl-kosong">
                <i class="fal fa-clipboard-list"></i>
                Belum ada butir. Sesi yang dibuka sekarang akan kosong sampai sedikitnya satu butir ditambahkan.
            </div>
        <?php else : ?>
            <?php foreach ($butir as $b) : ?>
                <?php $mati = (int) $b['aktif'] !== 1; ?>
                <div class="ckl-baris <?= $mati ? 'ckl-nonaktif' : '' ?>">
                    <div class="ckl-judul-butir">
                        <?= esc($b['judul']) ?>
                        <span class="ckl-nomor ms-1">#<?= (int) $b['id_item'] ?></span>
                    </div>
                    <div class="ckl-sub">
                        <?php if ($b['hint']) : ?><span><i class="fal fa-info-circle"></i> <?= esc($b['hint']) ?></span><?php endif; ?>
                        <?php if ($jenis === 'toko') : ?>
                            <?php if ($b['toko_id']) : ?>
                                <span class="pil-ckl p-khusus"><i class="fal fa-store"></i> <?= esc($b['nama_juragan'] ?: 'toko #' . (int) $b['toko_id']) ?></span>
                            <?php else : ?>
                                <span class="pil-ckl p-umum">Umum</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ((int) $b['wajib'] === 1) : ?><span class="pil-ckl p-wajib">wajib</span><?php endif; ?>
                        <?php if ($mati) : ?><span class="pil-ckl p-mati">nonaktif</span><?php endif; ?>
                        <span>urut <?= (int) $b['urut'] ?></span>
                    </div>

                    <?= form_open('admin/checklist/butir', ['class' => 'ckl-form']) ?>
                        <input type="hidden" name="jenis" value="<?= esc($jenis) ?>">
                        <input type="hidden" name="id_item" value="<?= (int) $b['id_item'] ?>">
                        <div class="lapangan lebar">
                            <label for="judul-<?= (int) $b['id_item'] ?>">Pertanyaan / tindakan</label>
                            <input type="text" class="form-control form-control-sm" id="judul-<?= (int) $b['id_item'] ?>"
                                name="judul" maxlength="200" value="<?= esc($b['judul']) ?>" required>
                        </div>
                        <div class="lapangan lebar">
                            <label for="hint-<?= (int) $b['id_item'] ?>">Petunjuk</label>
                            <input type="text" class="form-control form-control-sm" id="hint-<?= (int) $b['id_item'] ?>"
                                name="hint" maxlength="255" value="<?= esc($b['hint'] ?? '') ?>">
                        </div>
                        <?php if ($jenis === 'toko' && $toko !== []) : ?>
                            <div class="lapangan">
                                <label for="toko-<?= (int) $b['id_item'] ?>">Toko</label>
                                <select class="form-select form-select-sm" id="toko-<?= (int) $b['id_item'] ?>" name="toko_id">
                                    <option value="0">Umum (semua toko)</option>
                                    <?php foreach ($toko as $id_toko => $nama_t) : ?>
                                        <option value="<?= (int) $id_toko ?>" <?= (int) $b['toko_id'] === (int) $id_toko ? 'selected' : '' ?>>
                                            <?= esc($nama_t) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else : ?>
                            <input type="hidden" name="toko_id" value="<?= (int) ($b['toko_id'] ?? 0) ?>">
                        <?php endif; ?>
                        <div class="lapangan sempit">
                            <label for="urut-<?= (int) $b['id_item'] ?>">Urut</label>
                            <input type="number" class="form-control form-control-sm" id="urut-<?= (int) $b['id_item'] ?>"
                                name="urut" min="0" max="9999" value="<?= (int) $b['urut'] ?>">
                        </div>
                        <div class="lapangan">
                            <label for="wajib-<?= (int) $b['id_item'] ?>">Sifat</label>
                            <select class="form-select form-select-sm" id="wajib-<?= (int) $b['id_item'] ?>" name="wajib">
                                <option value="1" <?= (int) $b['wajib'] === 1 ? 'selected' : '' ?>>Wajib dijawab</option>
                                <option value="0" <?= (int) $b['wajib'] !== 1 ? 'selected' : '' ?>>Boleh dilewati</option>
                            </select>
                        </div>
                        <div class="lapangan">
                            <label for="aktif-<?= (int) $b['id_item'] ?>"><?= $mati ? 'Status' : '&nbsp;' ?></label>
                            <?php if ($mati) : ?>
                                <select class="form-select form-select-sm" id="aktif-<?= (int) $b['id_item'] ?>" name="aktif">
                                    <option value="0">Nonaktif</option>
                                    <option value="1">Aktif lagi</option>
                                </select>
                            <?php else : ?>
                                <input type="hidden" name="aktif" value="1">
                            <?php endif; ?>
                        </div>
                        <div class="lapangan">
                            <label>&nbsp;</label>
                            <div class="d-inline-flex gap-2 align-items-center">
                                <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">
                                    <i class="fal fa-pen me-1"></i> Simpan
                                </button>
                            </div>
                        </div>
                    <?= form_close() ?>

                    <?php if (! $mati) : ?>
                        <?= form_open('admin/checklist/butir-aktif') ?>
                            <input type="hidden" name="jenis" value="<?= esc($jenis) ?>">
                            <input type="hidden" name="id_item" value="<?= (int) $b['id_item'] ?>">
                            <input type="hidden" name="aktif" value="0">
                            <button type="submit" class="btn btn-sm btn-link text-danger text-decoration-none">Matikan butir</button>
                        <?= form_close() ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?= $this->endSection() ?>
