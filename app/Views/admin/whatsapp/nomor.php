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

    /* di HP ikon judul dibuang (pola rumah, sama seperti halaman tugas lainnya);
       popus "Tambah/Sunting nomor" dibatasi tingginya supaya tidak tumbuh
       sampai penuh seperti dulu */
    @media (max-width: 991.98px) {
        .nm-halaman h1 > i { display: none; }
    }

    @media (max-width: 767.98px) {
        #modalNomor .modal-content { max-height: 80vh; }
    }
    .nm-kartu {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem; overflow: hidden; margin-bottom: 1rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        /* overflow:hidden saja masih menyisakan 28px ke dokumen di 320px — tabel
           yang lebih lebar dari wadahnya ikut dihitung oleh <html>. contain:paint
           benar-benar mengunci isinya: tabel tetap bisa digulir di dalam dirinya,
           halaman tidak ikut bergeser ke samping */
        contain: paint;
    }
    .nm-kartu > .nm-judul {
        padding: .7rem 1rem; border-bottom: 1px solid #eaecf0; display: flex; align-items: center; gap: .6rem;
        font-size: .78rem; font-weight: 700; color: #344054; text-transform: uppercase; letter-spacing: .05em;
    }
    .nm-kartu > .nm-judul .btn { text-transform: none; letter-spacing: 0; font-weight: 600; }
    .table.nm-daftar { margin: 0; }
    .nm-daftar th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; background: #f9fafb; }
    .nm-daftar td { vertical-align: middle; font-size: .86rem; }
    .nm-nomor { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; color: #0f172a; white-space: nowrap; }
    .nm-meta { font-size: .76rem; color: #64748b; }
    .nm-aksi { display: inline-flex; gap: .3rem; align-items: center; justify-content: center; }
    .pil-nm { display: inline-block; padding: .16rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: #eef1f6; color: #475569; }
    .pil-umum { background: #e7f6ec; color: #14663a; }
    .pil-aktif { background: #e7f6ec; color: #14663a; }
    .pil-mati { background: #fdf1f0; color: #b81c16; }
    /* sakelar status: form-nya mengirim sendiri begitu dipindah, jadi tidak perlu
       tombol simpan terpisah di kolom itu. Input aslinya disembunyikan tapi tetap
       bisa difokuskan — kapsulnya cuma gambar di atasnya, kata ON/OFF hidup di
       dalam dan berpindah sisi mengikuti tombol bundar */
    .nm-sakelar { display: inline-flex; margin: 0; cursor: pointer; }
    .nm-sakelar input { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .nm-kapsul {
        position: relative; display: inline-flex; align-items: center; justify-content: flex-end;
        width: 3.25rem; height: 1.5rem; padding: 0 .45rem 0 1.5rem; border-radius: 999px;
        background: #dc2626; border: 1px solid #b91c1c; color: #fff;
        font-size: .58rem; font-weight: 800; letter-spacing: .06em;
        transition: background .15s ease, border-color .15s ease, padding .15s ease;
    }
    .nm-kapsul::before {
        content: ""; position: absolute; top: 1px; left: 1px; width: 1.15rem; height: 1.15rem;
        border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, .35);
        transition: transform .15s ease;
    }
    .nm-kapsul .k-on { display: none; }
    .nm-kapsul .k-off { display: inline; }
    .nm-sakelar input:checked + .nm-kapsul {
        background: #16a34a; border-color: #15803d; justify-content: flex-start; padding: 0 1.5rem 0 .45rem;
    }
    .nm-sakelar input:checked + .nm-kapsul::before { transform: translateX(1.9rem); }
    .nm-sakelar input:checked + .nm-kapsul .k-on { display: inline; }
    .nm-sakelar input:checked + .nm-kapsul .k-off { display: none; }
    .nm-sakelar input:focus-visible + .nm-kapsul { outline: 3px solid rgba(22, 163, 74, .35); outline-offset: 2px; }
    .nm-kosong { padding: 2.2rem 1rem; text-align: center; color: #94a3b8; }
    .nm-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl nm-halaman">

    <div class="nm-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><a class="judul-balik" href="<?= esc(site_url('admin/tugas/whatsapp')) ?>"
                    title="Kembali ke Reminder WhatsApp" aria-label="Kembali ke Reminder WhatsApp"><i class="fal fa-arrow-left"></i></a><i class="fal fa-phone-alt me-1 text-brand-500"></i> <?= esc($title) ?></h1>
            <p>Nomor yang boleh dipakai CS saat menindaklanjuti percakapan. Nomor tanpa penugasan boleh dipakai semua CS di toko itu.</p>
        </div>
    </div>

    <?php if ($kabar !== '') : ?><div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div><?php endif; ?>
    <?php if ($peringatan !== '') : ?><div class="alert alert-warning py-2" data-flash><?= esc($peringatan) ?></div><?php endif; ?>
    <?php if ($galat !== '') : ?><div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div><?php endif; ?>

    <div class="nm-kartu">
        <div class="nm-judul">
            Daftar nomor (<?= count($daftar) ?>)
            <?php if ($toko !== []) : ?>
                <button type="button" class="btn btn-sm btn-dark rounded-pill ms-auto" data-bs-toggle="modal"
                    data-bs-target="#modalNomor" data-id="0">
                    <i class="fal fa-plus me-1"></i> Tambah nomor
                </button>
            <?php endif; ?>
        </div>
        <?php if ($daftar === []) : ?>
            <div class="nm-kosong">
                <i class="fal fa-phone-alt"></i>
                Belum ada nomor. Tambahkan satu lewat tombol "Tambah nomor" di kanan atas supaya CS tahu nomor mana yang dipakai membalas.
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle nm-daftar tabel-rapi">
                    <thead>
                        <tr>
                            <th>Nomor</th>
                            <th>Toko</th>
                            <th>Dipakai oleh</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Toggle</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftar as $n) : ?>
                            <?php $mati = (int) $n['aktif'] !== 1; ?>
                            <tr>
                                <td>
                                    <div class="nm-nomor"><?= esc($n['nomor']) ?></div>
                                    <?php if ($n['label']) : ?><div class="nm-meta"><?= esc($n['label']) ?></div><?php endif; ?>
                                    <?php if ($n['catatan']) : ?><div class="nm-meta"><?= esc($n['catatan']) ?></div><?php endif; ?>
                                </td>
                                <td><?= esc($n['nama_juragan'] ?: 'tanpa toko') ?></td>
                                <td>
                                    <?php if ($n['nama_dipakai']) : ?>
                                        <span class="nm-meta"><?= esc($n['nama_dipakai']) ?></span>
                                    <?php else : ?>
                                        <span class="pil-nm pil-umum">semua CS di toko ini</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="pil-nm <?= $mati ? 'pil-mati' : 'pil-aktif' ?>"><?= $mati ? 'Nonaktif' : 'Aktif' ?></span>
                                </td>
                                <td class="text-center">
                                    <?= form_open('admin/tugas/whatsapp/nomor-aktif') ?>
                                        <input type="hidden" name="id_nomor" value="<?= (int) $n['id_nomor'] ?>">
                                        <label class="nm-sakelar">
                                            <input type="checkbox" role="switch" name="aktif" value="1"
                                                <?= $mati ? '' : 'checked' ?> onchange="this.form.submit()"
                                                aria-label="<?= $mati ? 'Aktifkan nomor ' . esc($n['nomor'], 'attr') : 'Matikan nomor ' . esc($n['nomor'], 'attr') ?>">
                                            <span class="nm-kapsul"><span class="k-on">ON</span><span class="k-off">OFF</span></span>
                                        </label>
                                    <?= form_close() ?>
                                </td>
                                <td class="text-center">
                                    <div class="nm-aksi">
                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill"
                                            data-bs-toggle="modal" data-bs-target="#modalNomor" data-id="<?= (int) $n['id_nomor'] ?>"
                                            data-toko="<?= (int) $n['juragan_id'] ?>" data-nomor="<?= esc($n['nomor'], 'attr') ?>"
                                            data-label="<?= esc((string) ($n['label'] ?? ''), 'attr') ?>" data-user="<?= (int) $n['user_id'] ?>"
                                            data-aktif="<?= $mati ? 0 : 1 ?>" title="Sunting nomor" aria-label="Sunting nomor">
                                            <i class="fal fa-pen"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill"
                                            data-bs-toggle="modal" data-bs-target="#modalHapusNomor" data-id="<?= (int) $n['id_nomor'] ?>"
                                            data-nomor="<?= esc($n['nomor'], 'attr') ?>" data-toko="<?= esc((string) ($n['nama_juragan'] ?? ''), 'attr') ?>"
                                            title="Hapus nomor" aria-label="Hapus nomor">
                                            <i class="fal fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php if ($toko !== []) : ?>
    <!-- satu popus untuk dua keperluan: tambah (data-id="0") dan sunting (data-id
         baris yang diklik). Ditulis dua popus berarti dua set nama field di satu
         halaman, dan yang satu pasti ketinggalan saat ada perubahan -->
    <div class="modal fade" id="modalNomor" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <?= form_open('admin/tugas/whatsapp/nomor') ?>
                    <input type="hidden" name="id_nomor" value="0">
                    <!-- status TIDAK diubah di popus ini, tapi endpoint menyimpannya
                         bersama field lain — kalau tidak dikirim, menyimpan suntingan
                         justru mematikan nomor (aktif dibaca 0) -->
                    <input type="hidden" name="aktif" id="nm-aktif" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalNomorJudul">Tambah nomor baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold" for="nm-toko">Toko <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="nm-toko" name="juragan_id" required>
                                    <?php $pertama = true; ?>
                                    <?php foreach ($toko as $id_toko => $nama_t) : ?>
                                        <option value="<?= (int) $id_toko ?>" <?= $pertama ? 'selected' : '' ?>><?= esc($nama_t) ?></option>
                                        <?php $pertama = false; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold" for="nm-nomor">Nomor <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="nm-nomor" name="nomor"
                                    maxlength="32" placeholder="0812xxxxxxx" required>
                            </div>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-6">
                                <label class="form-label small fw-bold" for="nm-label">Label</label>
                                <input type="text" class="form-control form-control-sm" id="nm-label" name="label" maxlength="80"
                                    placeholder="mis. CS Cabang Utara">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold" for="nm-cs">Dipakai oleh</label>
                                <select class="form-select form-select-sm" id="nm-cs" name="user_id">
                                    <option value="0">Semua CS di toko ini</option>
                                    <?php foreach ($pegawai as $id_cs => $nama_cs) : ?>
                                        <option value="<?= (int) $id_cs ?>"><?= esc($nama_cs) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-text small mt-2">
                            Nomor tanpa penugasan boleh dipakai semua CS di toko itu; yang ditugaskan hanya muncul
                            di form Reminder punyanya. Status aktif/nonaktif diatur lewat sakelar di tabel.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-dark" id="modalNomorSimpan"><i class="fal fa-plus me-1"></i> Tambah</button>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>

    <script>
    (function () {
        'use strict';

        var modal = document.getElementById('modalNomor');

        if (! modal) {
            return;
        }

        // relatedTarget = tombol yang membuka popus; isinya dibaca dari data-*
        // tombol itu, jadi satu popus bisa melayani semua baris
        modal.addEventListener('show.bs.modal', function (event) {
            var pemicu  = event.relatedTarget;
            var id      = pemicu ? (pemicu.getAttribute('data-id') || '0') : '0';
            var sunting = id !== '0';
            var isi     = function (nama, cadangan) {
                return sunting ? (pemicu.getAttribute(nama) || cadangan) : cadangan;
            };

            modal.querySelector('[name="id_nomor"]').value = id;
            document.getElementById('modalNomorJudul').textContent = sunting ? 'Sunting nomor' : 'Tambah nomor baru';
            document.getElementById('modalNomorSimpan').innerHTML = sunting
                ? '<i class="fal fa-pen me-1"></i> Simpan'
                : '<i class="fal fa-plus me-1"></i> Tambah';

            modal.querySelector('#nm-toko').value  = String(isi('data-toko', modal.querySelector('#nm-toko option').value));
            modal.querySelector('#nm-nomor').value = isi('data-nomor', '');
            modal.querySelector('#nm-label').value = isi('data-label', '');
            modal.querySelector('#nm-cs').value    = String(isi('data-user', '0'));
            modal.querySelector('#nm-aktif').value = String(isi('data-aktif', '1'));
        });
    }());
    </script>

    <!-- konfirmasi hapus: satu popus untuk semua baris, isinya diambil dari tombol
         tong sampah yang diklik. window.confirm() tidak dipakai karena peramban
         bisa memblokirnya selamanya begitu pengguna pernah menekan "Prevent" -->
    <div class="modal fade" id="modalHapusNomor" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <?= form_open('admin/tugas/whatsapp/nomor-hapus') ?>
                    <input type="hidden" name="id_nomor" value="0">
                    <div class="modal-header">
                        <h5 class="modal-title">Hapus nomor ini?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">
                            <span class="nm-nomor" id="hapusNomorAngka">-</span>
                            <span class="nm-meta d-block mt-1" id="hapusNomorToko"></span>
                        </p>
                        <p class="small text-muted mb-0">
                            Nomor dibuang dari daftar penugasan. Reminder lama yang pernah memakainya tidak ikut
                            terhapus — kalau nomor itu masih terpakai, penghapusan ditolak dan cukup dimatikan
                            lewat sakelar.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-danger"><i class="fal fa-trash me-1"></i> Hapus</button>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>

    <script>
    (function () {
        'use strict';

        var modal = document.getElementById('modalHapusNomor');

        if (! modal) {
            return;
        }

        modal.addEventListener('show.bs.modal', function (event) {
            var pemicu = event.relatedTarget;

            if (! pemicu) {
                return;
            }

            modal.querySelector('[name="id_nomor"]').value = pemicu.getAttribute('data-id') || '0';
            document.getElementById('hapusNomorAngka').textContent = pemicu.getAttribute('data-nomor') || '-';
            document.getElementById('hapusNomorToko').textContent  = pemicu.getAttribute('data-toko') || '';
        });
    }());
    </script>
<?php endif; ?>

<?= $this->endSection() ?>
