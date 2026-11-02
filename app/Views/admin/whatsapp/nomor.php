<?php

/**
 * Delegasi nomor WhatsApp pengirim (Admin & Superadmin).
 *
 * Nomor yang tidak disematkan ke orang boleh dipakai semua CS di toko itu;
 * nomor yang disematkan hanya muncul di form Reminder WhatsApp punyanya.
 *
 * @var string                     $title      judul halaman (dari Admin\Whatsapp::nomor())
 * @var list<array<string, mixed>> $daftar
 * @var array<int, string>         $toko
 * @var array<int, string>         $pegawai
 * @var string                     $kabar      pesan sukses flash (kosong kalau tidak ada)
 * @var string                     $galat      pesan gagal flash (kosong kalau tidak ada)
 * @var string                     $peringatan pesan peringatan flash (kosong kalau tidak ada)
 */
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .nm-halaman { max-width: 1000px; }
    .nm-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .nm-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }
    .nm-kartu {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem; overflow: hidden; margin-bottom: 1rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
    }
    .nm-kartu > .nm-judul {
        padding: .7rem 1rem; border-bottom: 1px solid #eaecf0;
        font-size: .78rem; font-weight: 700; color: #344054; text-transform: uppercase; letter-spacing: .05em;
    }
    .nm-form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .5rem; padding: 1rem; }
    .nm-form .lapangan { display: flex; flex-direction: column; gap: .18rem; }
    .nm-form label { font-size: .68rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .nm-form .lebar { min-width: 220px; flex: 1; }
    .nm-baris { padding: .85rem 1rem; border-bottom: 1px solid #f1f5f9; }
    .nm-baris:last-child { border-bottom: 0; }
    .nm-nomor { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; color: #0f172a; }
    .nm-sub { font-size: .76rem; color: #64748b; margin-top: .12rem; display: flex; flex-wrap: wrap; gap: .2rem .6rem; }
    .pil-nm { display: inline-block; padding: .16rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: #eef1f6; color: #475569; }
    .pil-mati { background: #fdf1f0; color: #b81c16; }
    .pil-umum { background: #e7f6ec; color: #14663a; }
    .nm-kosong { padding: 2.2rem 1rem; text-align: center; color: #94a3b8; }
    .nm-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl nm-halaman">

    <div class="nm-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><i class="fal fa-phone-alt me-1 text-brand-500"></i> <?= esc($title) ?></h1>
            <p>Nomor yang boleh dipakai CS saat menindaklanjuti percakapan. Nomor tanpa penugasan boleh dipakai semua CS di toko itu.</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/tugas/whatsapp')) ?>">
            <i class="fal fa-arrow-left me-1"></i> Reminder WhatsApp
        </a>
    </div>

    <?php if ($kabar !== '') : ?><div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div><?php endif; ?>
    <?php if ($peringatan !== '') : ?><div class="alert alert-warning py-2" data-flash><?= esc($peringatan) ?></div><?php endif; ?>
    <?php if ($galat !== '') : ?><div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div><?php endif; ?>

    <div class="nm-kartu">
        <div class="nm-judul">Daftar nomor (<?= count($daftar) ?>)</div>
        <?php if ($daftar === []) : ?>
            <div class="nm-kosong">
                <i class="fal fa-phone-alt"></i>
                Belum ada nomor. Tambahkan satu di form bawah ini supaya CS tahu nomor mana yang dipakai membalas.
            </div>
        <?php else : ?>
            <?php foreach ($daftar as $n) : ?>
                <?php $mati = (int) $n['aktif'] !== 1; ?>
                <div class="nm-baris">
                    <div class="nm-nomor"><?= esc($n['nomor']) ?></div>
                    <div class="nm-sub">
                        <span class="pil-nm"><?= esc($n['nama_juragan'] ?: 'tanpa toko') ?></span>
                        <?php if ($n['label']) : ?><span><?= esc($n['label']) ?></span><?php endif; ?>
                        <?php if ($n['nama_dipakai']) : ?>
                            <span>hanya dipakai <?= esc($n['nama_dipakai']) ?></span>
                        <?php else : ?>
                            <span class="pil-nm pil-umum">semua CS di toko ini</span>
                        <?php endif; ?>
                        <?php if ($mati) : ?><span class="pil-nm pil-mati">nonaktif</span><?php endif; ?>
                        <?php if ($n['catatan']) : ?><span><?= esc($n['catatan']) ?></span><?php endif; ?>
                    </div>

                    <?= form_open('admin/tugas/whatsapp/nomor', ['class' => 'nm-form']) ?>
                        <input type="hidden" name="id_nomor" value="<?= (int) $n['id_nomor'] ?>">
                        <div class="lapangan">
                            <label for="toko-<?= (int) $n['id_nomor'] ?>">Toko</label>
                            <select class="form-select form-select-sm" id="toko-<?= (int) $n['id_nomor'] ?>" name="juragan_id" required>
                                <?php foreach ($toko as $id_toko => $nama_t) : ?>
                                    <option value="<?= (int) $id_toko ?>" <?= (int) $n['juragan_id'] === (int) $id_toko ? 'selected' : '' ?>>
                                        <?= esc($nama_t) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="lapangan">
                            <label for="nomor-<?= (int) $n['id_nomor'] ?>">Nomor</label>
                            <input type="text" class="form-control form-control-sm" id="nomor-<?= (int) $n['id_nomor'] ?>"
                                name="nomor" maxlength="32" value="<?= esc($n['nomor']) ?>" required>
                        </div>
                        <div class="lapangan lebar">
                            <label for="label-<?= (int) $n['id_nomor'] ?>">Label</label>
                            <input type="text" class="form-control form-control-sm" id="label-<?= (int) $n['id_nomor'] ?>"
                                name="label" maxlength="80" value="<?= esc($n['label'] ?? '') ?>" placeholder="mis. CS Rumah Jahit">
                        </div>
                        <div class="lapangan">
                            <label for="cs-<?= (int) $n['id_nomor'] ?>">Dipakai oleh</label>
                            <select class="form-select form-select-sm" id="cs-<?= (int) $n['id_nomor'] ?>" name="user_id">
                                <option value="0">Semua CS di toko ini</option>
                                <?php foreach ($pegawai as $id_cs => $nama_cs) : ?>
                                    <option value="<?= (int) $id_cs ?>" <?= (int) $n['user_id'] === (int) $id_cs ? 'selected' : '' ?>>
                                        <?= esc($nama_cs) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="lapangan">
                            <label for="aktif-<?= (int) $n['id_nomor'] ?>"><?= $mati ? 'Status' : '&nbsp;' ?></label>
                            <?php if ($mati) : ?>
                                <select class="form-select form-select-sm" id="aktif-<?= (int) $n['id_nomor'] ?>" name="aktif">
                                    <option value="0">Nonaktif</option>
                                    <option value="1">Aktifkan</option>
                                </select>
                            <?php else : ?>
                                <input type="hidden" name="aktif" value="1">
                            <?php endif; ?>
                        </div>
                        <div class="lapangan">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">
                                <i class="fal fa-pen me-1"></i> Simpan
                            </button>
                        </div>
                    <?= form_close() ?>

                    <?php if (! $mati) : ?>
                        <?= form_open('admin/tugas/whatsapp/nomor-aktif', ['class' => 'd-inline-flex ms-2']) ?>
                            <input type="hidden" name="id_nomor" value="<?= (int) $n['id_nomor'] ?>">
                            <input type="hidden" name="aktif" value="0">
                            <button type="submit" class="btn btn-sm btn-link text-danger text-decoration-none">Matikan</button>
                        <?= form_close() ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($toko !== []) : ?>
        <div class="nm-kartu">
            <div class="nm-judul">Tambah nomor baru</div>
            <?= form_open('admin/tugas/whatsapp/nomor', ['class' => 'nm-form']) ?>
                <input type="hidden" name="id_nomor" value="0">
                <input type="hidden" name="aktif" value="1">
                <div class="lapangan">
                    <label for="baru-toko">Toko</label>
                    <select class="form-select form-select-sm" id="baru-toko" name="juragan_id" required>
                        <?php foreach ($toko as $id_toko => $nama_t) : ?>
                            <option value="<?= (int) $id_toko ?>"><?= esc($nama_t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lapangan">
                    <label for="baru-nomor">Nomor <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="baru-nomor" name="nomor"
                        maxlength="32" placeholder="0812xxxxxxx" required>
                </div>
                <div class="lapangan lebar">
                    <label for="baru-label">Label</label>
                    <input type="text" class="form-control form-control-sm" id="baru-label" name="label" maxlength="80"
                        placeholder="mis. CS Cabang Utara">
                </div>
                <div class="lapangan">
                    <label for="baru-cs">Dipakai oleh</label>
                    <select class="form-select form-select-sm" id="baru-cs" name="user_id">
                        <option value="0">Semua CS di toko ini</option>
                        <?php foreach ($pegawai as $id_cs => $nama_cs) : ?>
                            <option value="<?= (int) $id_cs ?>"><?= esc($nama_cs) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lapangan">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-plus me-1"></i> Tambah</button>
                </div>
            <?= form_close() ?>
        </div>
    <?php endif; ?>

</div>

<?= $this->endSection() ?>
