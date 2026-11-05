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
 * @var array  $toko     id_toko => baris toko yang boleh dibuka akun ini
 * @var array  $meta     ChecklistModel::JENIS
 * @var array  $platform daftar marketplace untuk popus toko
 * @var array  $pegawai  id akun => "nama (level)" — calon pemegang toko
 * @var array  $pemegang id_toko => akun yang boleh membuka lembaran toko itu
 * @var array  $juragan  id_juragan => nama, untuk menautkan toko ke pemiliknya
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

    /* kartu daftar toko + popusnya */
    .ckl-judul-aksi { display: flex; align-items: center; gap: .6rem; }
    .ckl-judul-aksi .btn { text-transform: none; letter-spacing: 0; font-weight: 600; }
    .ckl-toko-list { max-height: 22rem; overflow-y: auto; }
    .ckl-toko-baris {
        display: flex; flex-wrap: wrap; align-items: center; gap: .5rem;
        padding: .6rem 1rem; border-bottom: 1px solid #eef1f6;
    }
    .ckl-toko-baris:last-child { border-bottom: 0; }
    .ckl-toko-baris strong { font-size: .88rem; color: #0f172a; }
    .ckl-toko-baris > .me-auto { flex: 1 1 200px; min-width: 0; }
    .ckl-toko-kenal { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .25rem; }
    .pil-toko {
        display: inline-block; padding: .12rem .5rem; border-radius: 999px;
        background: #eef1f6; color: #475569; font-size: .7rem; font-weight: 600;
    }
    .pil-toko-juragan { background: #f1f5f9; color: #334155; }
    .ckl-toko-kosong { font-size: .74rem; color: #94a3b8; }
    .ckl-cs {
        display: flex; flex-direction: column; gap: .25rem; max-height: 11rem; overflow-y: auto;
        padding: .5rem .6rem; border: 1px solid #e2e8f0; border-radius: .7rem; background: #fbfcfe;
    }
    .ckl-cs-baris { display: flex; align-items: center; gap: .45rem; font-size: .8rem; color: #334155; margin: 0; }

    @media (max-width: 991.98px) {
        .ckl-toko-baris > .btn { margin-left: 0; }
    }

    @media (min-width: 992px) {
        #modalToko .modal-content { max-height: 80vh; }
    }
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
                        <?php foreach ($toko as $id_toko => $t) : ?>
                            <option value="<?= (int) $id_toko ?>"><?= esc($t['nama']) ?></option>
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
                                <span class="pil-ckl p-khusus"><i class="fal fa-store"></i> <?= esc($b['nama_toko'] ?: 'toko #' . (int) $b['toko_id']) ?></span>
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
                                    <?php foreach ($toko as $id_toko => $t) : ?>
                                        <option value="<?= (int) $id_toko ?>" <?= (int) $b['toko_id'] === (int) $id_toko ? 'selected' : '' ?>>
                                            <?= esc($t['nama']) ?>
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
    <?php if ($jenis === 'toko') : ?>
        <!-- toko checklist dikelola di sini: namanya bebas dan juragan boleh dikosongkan,
           jadi templat "butir khusus toko" bisa menunjuk ke toko yang belum ada di
           daftar juragan sama sekali -->
        <div class="ckl-kartu">
            <div class="ckl-judul ckl-judul-aksi">
                <span>Daftar toko (<?= count($toko) ?>)</span>
                <button type="button" class="btn btn-sm btn-dark ms-auto"
                    data-bs-toggle="modal" data-bs-target="#modalToko" data-id="0">
                    <i class="fal fa-plus me-1"></i> Tambah toko
                </button>
            </div>
            <div class="ckl-toko-list">
                <?php foreach ($toko as $id_toko => $t) : ?>
                    <?php
                    $kenal     = $pemegang[(int) $id_toko] ?? [];
                    $punya_cs  = [];

                    foreach ($kenal as $o) {
                        if ($o['sumber'] === 'tautan') {
                            $punya_cs[] = (int) $o['id'];
                        }
                    }
                    ?>
                    <div class="ckl-toko-baris">
                        <div class="me-auto">
                            <strong><?= esc($t['nama']) ?></strong>
                            <?php if ($t['platform'] !== '') : ?>
                                <span class="pil-toko"><?= esc($t['platform']) ?></span>
                            <?php endif; ?>
                            <?php if ($t['juragan_id'] !== null && isset($juragan[(int) $t['juragan_id']])) : ?>
                                <span class="pil-toko pil-toko-juragan"><?= esc($juragan[(int) $t['juragan_id']]) ?></span>
                            <?php endif; ?>
                            <div class="ckl-toko-kenal">
                                <?php if ($kenal === []) : ?>
                                    <span class="ckl-toko-kosong">belum ada yang boleh membuka lembaran toko ini</span>
                                <?php else : ?>
                                    <?php foreach ($kenal as $o) : ?>
                                        <span class="pil-toko"><?= esc($o['nama']) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill"
                            data-bs-toggle="modal" data-bs-target="#modalToko"
                            data-id="<?= (int) $id_toko ?>"
                            data-nama="<?= esc($t['nama'], 'attr') ?>"
                            data-platform="<?= esc($t['platform'], 'attr') ?>"
                            data-juragan="<?= (int) ($t['juragan_id'] ?? 0) ?>"
                            data-cs="<?= implode(',', $punya_cs) ?>"
                            title="Sunting toko ini" aria-label="Sunting toko <?= esc($t['nama']) ?>">
                            <i class="fal fa-pen"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="modal fade" id="modalToko" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <?= form_open('admin/checklist/toko-simpan') ?>
                        <input type="hidden" name="id_toko" id="tk-id" value="0">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalTokoJudul">Tambah toko baru</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label small fw-bold" for="tk-nama">Nama toko <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="tk-nama" name="nama"
                                maxlength="120" placeholder="mis. Toko Jaket Kota" required>

                            <div class="row g-2 mt-1">
                                <div class="col-6">
                                    <label class="form-label small fw-bold" for="tk-platform">Platform</label>
                                    <select class="form-select form-select-sm" id="tk-platform" name="platform">
                                        <option value="">Tanpa platform</option>
                                        <?php foreach ($platform as $p) : ?>
                                            <option value="<?= esc($p) ?>"><?= esc($p) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold" for="tk-juragan">Juragan</label>
                                    <select class="form-select form-select-sm" id="tk-juragan" name="juragan_id">
                                        <option value="0">Tidak ada (toko berdiri sendiri)</option>
                                        <?php foreach ($juragan as $id_j => $nama_j) : ?>
                                            <option value="<?= (int) $id_j ?>"><?= esc($nama_j) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <label class="form-label small fw-bold mt-2" for="tk-aktif">Status</label>
                            <select class="form-select form-select-sm" id="tk-aktif" name="aktif">
                                <option value="1">Aktif</option>
                                <option value="0">Dimatikan</option>
                            </select>

                            <label class="form-label small fw-bold mt-2" for="tk-cs-0">CS yang boleh membuka lembaran toko ini</label>
                            <div class="ckl-cs" id="tk-cs">
                                <?php $i = 0; ?>
                                <?php foreach ($pegawai as $id_u => $nama_u) : ?>
                                    <label class="ckl-cs-baris" for="tk-cs-<?= $i ?>">
                                        <input class="form-check-input mt-0" type="checkbox" id="tk-cs-<?= $i ?>"
                                            name="cs[]" value="<?= (int) $id_u ?>">
                                        <?= esc($nama_u) ?>
                                    </label>
                                    <?php $i++; ?>
                                <?php endforeach; ?>
                                <?php if ($pegawai === []) : ?>
                                    <span class="ckl-toko-kosong">belum ada akun Admin/CS aktif</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-sm btn-dark" id="modalTokoSimpan"><i class="fal fa-plus me-1"></i> Simpan</button>
                        </div>
                    <?= form_close() ?>
                </div>
            </div>
        </div>

        <script>
    (function () {
        var modal = document.getElementById('modalToko');

        if (! modal) {
            return;
        }

        var isi = function (key, fallback) {
            var value = modal.querySelector('#' + key).value;
            return value === '' ? fallback : value;
        };

        modal.addEventListener('show.bs.modal', function (event) {
            var pemicu = event.relatedTarget;

            if (! pemicu) {
                return;
            }

            var id = pemicu.getAttribute('data-id') || '0';

            modal.querySelector('#tk-id').value        = id;
            modal.querySelector('#tk-nama').value      = pemicu.getAttribute('data-nama') || '';
            modal.querySelector('#tk-platform').value  = isi('tk-platform', '');
            modal.querySelector('#tk-aktif').value     = '1';

            if (id !== '0') {
                modal.querySelector('#tk-platform').value = pemicu.getAttribute('data-platform') || '';
                modal.querySelector('#tk-juragan').value  = pemicu.getAttribute('data-juragan') || '0';
            } else {
                modal.querySelector('#tk-juragan').value  = '0';
            }

            var mau = (pemicu.getAttribute('data-cs') || '').split(',');
            modal.querySelectorAll('#tk-cs input[type="checkbox"]').forEach(function (kotak) {
                kotak.checked = mau.indexOf(kotak.value) > -1;
            });

            modal.querySelector('#modalTokoJudul').textContent = id === '0' ? 'Tambah toko baru' : 'Sunting toko';
            modal.querySelector('#modalTokoSimpan').innerHTML  = id === '0'
                ? '<i class="fal fa-plus me-1"></i> Simpan'
                : '<i class="fal fa-save me-1"></i> Simpan';
        });
    }());
        </script>
    <?php endif; ?>

</div>

<?= $this->endSection() ?>
