<?php

/**
 * Daftar Reminder WhatsApp: tiga tab status + form buat tugas baru.
 *
 * @var list<array<string, mixed>> $baris
 * @var array<string, string>      $tab     kunci => label
 * @var array<string, int>         $angka   kunci => jumlah baris
 * @var array<string, string>      $tindak  kunci => label jenis tindak lanjut
 * @var array<int, string>         $toko
 * @var list<array<string, mixed>> $nomor   nomor pengirim yang boleh dipakai akun ini
 */

$level    = (string) session()->get('level');
$hari_ini = date('Y-m-d');

$peta_nomor = [];

foreach ($nomor as $n) {
    $peta_nomor[(int) $n['juragan_id']][] = [
        'id'    => (int) $n['id_nomor'],
        'label' => (string) ($n['label'] ?: $n['nomor']),
        'nomor' => (string) $n['nomor'],
    ];
}

$tanya = static function (array $ubah): string {
    $q = array_merge($_GET, $ubah);

    if (! array_key_exists('halaman', $ubah)) {
        unset($q['halaman']);
    }

    foreach (['tab', 'tindak', 'toko', 'nomor', 'cari', 'saya', 'batas', 'halaman'] as $k) {
        if (isset($q[$k]) && $q[$k] === '') {
            unset($q[$k]);
        }
    }

    return site_url('admin/tugas/whatsapp') . '?' . http_build_query($q);
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .wa-halaman { max-width: 1180px; }
    .wa-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .wa-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }

    .wa-tab { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1rem; }
    .wa-tab a {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .34rem .85rem; border-radius: 999px; font-size: .8rem; font-weight: 600;
        background: #f4f6f9; color: #475569; text-decoration: none; border: 1px solid #e2e8f0;
    }
    .wa-tab a.aktif { background: #101828; border-color: #101828; color: #fff; }
    .wa-tab a span { font-weight: 700; }

    .wa-saring { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .55rem; margin-bottom: 1rem; }
    .wa-saring .lapangan { display: flex; flex-direction: column; gap: .2rem; }
    .wa-saring label { font-size: .7rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .wa-saring .form-control, .wa-saring .form-select { max-width: 190px; }

    .wa-kartu {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        overflow: hidden;
    }
    .table.wa-daftar { margin: 0; }
    .wa-daftar th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; background: #f9fafb; }
    .wa-daftar td { vertical-align: middle; font-size: .86rem; }
    .wa-nama { font-weight: 700; color: #0f172a; text-decoration: none; display: block; }
    .wa-nama:hover { color: #da251d; }
    .wa-sub { font-size: .76rem; color: #64748b; display: flex; flex-wrap: wrap; gap: .2rem .6rem; margin-top: .12rem; }
    .wa-nomor { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .78rem; color: #334155; }
    .wa-kosong { padding: 2.6rem 1rem; text-align: center; color: #94a3b8; }
    .wa-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }

    .pil-status { display: inline-block; padding: .18rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
    .s-queued { background: #eef1f6; color: #475569; }
    .s-assigned { background: #e6f4fb; color: #0369a1; }
    .s-in_progress { background: #fef3e2; color: #b45309; }
    .s-waiting { background: #f3e8ff; color: #6b21a8; }
    .s-completed { background: #e7f6ec; color: #14663a; }
    .s-cancelled { background: #fdf1f0; color: #b81c16; }
    .pil-tindak { display: inline-block; padding: .16rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: #f1f5f9; color: #334155; }
    .pil-orang { display: inline-block; padding: .16rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: #e6f4fb; color: #0369a1; }
    .pil-buat { background: #f4f6f9; color: #475569; }

    .wa-teenggat { font-size: .76rem; white-space: nowrap; }
    .lewat { color: #b81c16; font-weight: 700; }
    .wa-aksi { display: inline-flex; gap: .3rem; align-items: center; justify-content: flex-end; }
    .wa-form-lepas { margin-top: .4rem; }
    .wa-pager { padding: .8rem 1rem; background: #fff; border-top: 1px solid #eaecf0; }

    .wa-cari { position: relative; }
    .wa-saran {
        position: absolute; z-index: 20; top: 100%; left: 0; right: 0; background: #fff;
        border: 1px solid #e2e8f0; border-radius: .6rem; box-shadow: 0 8px 24px -12px rgba(15, 23, 42, .25);
        max-height: 210px; overflow: auto; display: none;
    }
    .wa-saran.tampil { display: block; }
    .wa-saran button { display: block; width: 100%; text-align: left; padding: .4rem .6rem; background: none; border: 0; font-size: .82rem; }
    .wa-saran button:hover { background: #f7f9fc; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl wa-halaman">

    <div class="wa-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><i class="fal fa-comment-dots me-1 text-brand-500"></i> <?= esc($title) ?></h1>
            <p>Percakapan yang perlu dibalas, ditagih, atau dikonfirmasi. Siapa cepat dia ambil &mdash; setiap perpindahan tangan tercatat.</p>
        </div>
        <?php if ($kelola) : ?>
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/tugas/whatsapp/nomor')) ?>">
                <i class="fal fa-phone-alt me-1"></i> Delegasi Nomor
            </a>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalWaBaru">
            <i class="fal fa-plus"></i> Buat Reminder
        </button>
    </div>

    <?php if ($kabar !== '') : ?><div class="alert alert-success py-2"><?= esc($kabar) ?></div><?php endif; ?>
    <?php if ($galat !== '') : ?><div class="alert alert-danger py-2"><?= esc($galat) ?></div><?php endif; ?>

    <div class="wa-tab">
        <?php foreach ($tab as $kode => $nama) : ?>
            <a class="<?= $s['tab'] === $kode ? 'aktif' : '' ?>" href="<?= esc($tanya(['tab' => $kode])) ?>">
                <?= esc($nama) ?>
                <?php if (($angka[$kode] ?? 0) > 0) : ?><span><?= (int) $angka[$kode] ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <a class="<?= (int) $s['milik_saya'] === $milik ? 'aktif' : '' ?>"
            href="<?= esc($tanya(['saya' => (int) $s['milik_saya'] === $milik ? '' : '1'])) ?>">
            <i class="fal fa-user"></i> Hanya punyaku
        </a>
    </div>

    <form class="wa-saring" method="get" action="<?= esc(site_url('admin/tugas/whatsapp')) ?>">
        <input type="hidden" name="tab" value="<?= esc($s['tab']) ?>">
        <input type="hidden" name="saya" value="<?= (int) $s['milik_saya'] === $milik ? '1' : '' ?>">
        <div class="lapangan">
            <label for="cari">Cari</label>
            <input type="search" class="form-control form-control-sm" id="cari" name="cari" value="<?= esc($s['cari']) ?>"
                placeholder="judul atau catatan">
        </div>
        <div class="lapangan">
            <label for="nomor">Nomor WA</label>
            <input type="search" class="form-control form-control-sm" id="nomor" name="nomor" value="<?= esc($s['nomor']) ?>"
                placeholder="0812...">
        </div>
        <div class="lapangan">
            <label for="tindak">Tindak lanjut</label>
            <select class="form-select form-select-sm" id="tindak" name="tindak">
                <option value="">Semua jenis</option>
                <?php foreach ($tindak as $kode => $nama) : ?>
                    <option value="<?= esc($kode) ?>" <?= $s['tindak_lanjut'] === $kode ? 'selected' : '' ?>><?= esc($nama) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (count($toko) > 1) : ?>
            <div class="lapangan">
                <label for="toko">Toko</label>
                <select class="form-select form-select-sm" id="toko" name="toko">
                    <option value="">Semua toko</option>
                    <?php foreach ($toko as $id_toko => $nama_t) : ?>
                        <option value="<?= (int) $id_toko ?>" <?= (int) $s['juragan_id'] === (int) $id_toko ? 'selected' : '' ?>><?= esc($nama_t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="lapangan">
            <label for="batas">Baris</label>
            <select class="form-select form-select-sm" id="batas" name="batas">
                <?php foreach ([10, 25, 50] as $n) : ?>
                    <option value="<?= $n ?>" <?= $batas === $n ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="lapangan">
            <label>&nbsp;</label>
            <div class="wa-aksi">
                <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-search"></i> Cari</button>
                <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/tugas/whatsapp')) ?>">Ulangi</a>
            </div>
        </div>
    </form>

    <div class="wa-kartu">
        <?php if ($baris === []) : ?>
            <div class="wa-kosong">
                <i class="fal fa-circle-check text-success"></i>
                <?php if ($s['tab'] === 'belum') : ?>Tidak ada reminder menunggu. Buat lewat tombol kanan atas.<?php else : ?>Kosong di tab ini.<?php endif; ?>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle wa-daftar">
                    <thead>
                        <tr>
                            <th>Reminder</th>
                            <th>Nomor</th>
                            <th>Tindak lanjut</th>
                            <th>Tenggat</th>
                            <th>Penanggung jawab</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baris as $w) : ?>
                            <?php
                            $status   = (string) $w['status'];
                            $punya    = (int) $w['dikerjakan_oleh'] === $milik;
                            $buka     = in_array($status, \App\Models\TugasModel::BELUM_SELESAI, true);
                            $tenggat  = (string) ($w['tenggat'] ?? '');
                            $terlambat = $buka && $tenggat !== '' && $tenggat < $hari_ini;
                            ?>
                            <tr>
                                <td>
                                    <a class="wa-nama" href="<?= esc(site_url('admin/tugas/' . (int) $w['tugas_id'])) ?>"><?= esc($w['judul']) ?></a>
                                    <div class="wa-sub">
                                        <span>#<?= (int) $w['tugas_id'] ?></span>
                                        <?php if ($w['nama_pelanggan']) : ?><span><i class="fal fa-user"></i> <?= esc($w['nama_pelanggan']) ?></span><?php endif; ?>
                                        <?php if ($w['nama_juragan']) : ?><span><i class="fal fa-store"></i> <?= esc($w['nama_juragan']) ?></span><?php endif; ?>
                                        <span class="pil-status s-<?= esc($status) ?>"><?= esc(\App\Models\TugasModel::STATUS[$status] ?? $status) ?></span>
                                        <span class="pil-buat pil-orang">dibuat: <?= esc($w['nama_dibuat'] ?: '?') ?></span>
                                    </div>
                                    <?php if ($w['deskripsi']) : ?>
                                        <div class="wa-sub mt-1"><?= nl2br(esc(mb_substr((string) $w['deskripsi'], 0, 160))) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="wa-nomor"><?= esc($w['nomor_ke'] ?: '-') ?></div>
                                    <div class="wa-sub">
                                        <span>dari <?= esc($w['nomor_dari'] ?: '-') ?></span>
                                        <?php if ($w['label_nomor']) : ?><span><?= esc($w['label_nomor']) ?></span><?php endif; ?>
                                    </div>
                                </td>
                                <td><span class="pil-tindak"><?= esc($tindak[$w['tindak_lanjut']] ?? $w['tindak_lanjut']) ?></span></td>
                                <td class="wa-teenggat <?= $terlambat ? 'lewat' : '' ?>">
                                    <?= $tenggat === '' ? '-' : esc(tanggal_rincian($tenggat)) ?>
                                    <?php if ($terlambat) : ?><i class="fal fa-exclamation-triangle"></i><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($w['nama_dikerjakan']) : ?>
                                        <span class="pil-orang"><i class="fal fa-user-check me-1"></i><?= esc($w['nama_dikerjakan']) ?></span>
                                        <?php if ($w['nama_selesai']) : ?>
                                            <div class="wa-sub"><span>selesai: <?= esc($w['nama_selesai']) ?></span></div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="text-muted">belum diambil</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="wa-aksi flex-wrap">
                                        <?php if ($status === 'queued') : ?>
                                            <form method="post" action="<?= esc(site_url('admin/tugas/whatsapp/ambil/' . (int) $w['tugas_id'])) ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">Ambil Tugas</button>
                                            </form>
                                        <?php elseif ($punya && $buka) : ?>
                                            <form method="post" action="<?= esc(site_url('admin/tugas/whatsapp/selesai/' . (int) $w['tugas_id'])) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="catatan" value="selesai dari daftar reminder">
                                                <button type="submit" class="btn btn-sm btn-success rounded-pill">Selesai</button>
                                            </form>
                                            <a class="btn btn-sm btn-link text-decoration-none" data-bs-toggle="collapse"
                                                href="#lepas-<?= (int) $w['tugas_id'] ?>" role="button">Lepas</a>
                                        <?php else : ?>
                                            <a class="btn btn-sm btn-link text-decoration-none"
                                                href="<?= esc(site_url('admin/tugas/' . (int) $w['tugas_id'])) ?>">Riwayat</a>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($punya && $buka) : ?>
                                        <div class="collapse" id="lepas-<?= (int) $w['tugas_id'] ?>">
                                            <?= form_open('admin/tugas/whatsapp/lepas/' . (int) $w['tugas_id'], ['class' => 'wa-form-lepas']) ?>
                                                <input type="text" class="form-control form-control-sm" name="alasan" maxlength="255"
                                                    placeholder="alasan dilepas (wajib)" required>
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill mt-1">Kembalikan ke antrean</button>
                                            <?= form_close() ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($total > $batas) : ?>
            <div class="wa-pager d-flex flex-wrap align-items-center gap-2">
                <span class="small text-muted"><?= (int) $total ?> reminder &middot; halaman <?= (int) $page ?></span>
                <div class="ms-auto">
                    <?= \Config\Services::pager()->makeLinks($page, $batas, $total, 'front_full') ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- modal buat -->
<div class="modal fade" id="modalWaBaru" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <?= form_open('admin/tugas/whatsapp/simpan') ?>
            <div class="modal-header">
                <h5 class="modal-title">Buat Reminder WhatsApp</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold" for="wa-toko">Toko <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="wa-toko" name="juragan_id" required>
                            <?php $pertama = true; ?>
                            <?php foreach ($toko as $id_toko => $nama_t) : ?>
                                <option value="<?= (int) $id_toko ?>" <?= $pertama ? 'selected' : '' ?>><?= esc($nama_t) ?></option>
                                <?php $pertama = false; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold" for="wa-nomor-dari">Nomor WhatsApp Pengirim</label>
                        <select class="form-select form-select-sm" id="wa-nomor-dari" name="nomor_id">
                            <option value="0">Belum ditentukan / nomor lain</option>
                        </select>
                        <div class="form-text small">
                            Daftar nomor diisi Admin di menu Delegasi Nomor. Kalau kosong, CS tetap bisa mencatat remindernya.
                        </div>
                    </div>
                </div>

                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <label class="form-label small fw-bold" for="wa-cari-pelanggan">Cari pelanggan (opsional)</label>
                        <div class="wa-cari">
                            <input type="search" class="form-control form-control-sm" id="wa-cari-pelanggan"
                                placeholder="nama atau nomor" autocomplete="off">
                            <div class="wa-saran" id="wa-saran"></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold" for="wa-nomor-ke">Nomor WhatsApp Pelanggan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="wa-nomor-ke" name="nomor_ke"
                            maxlength="32" placeholder="0812xxxxxxx" required>
                    </div>
                </div>
                <input type="hidden" name="pelanggan_id" id="wa-pelanggan" value="0">

                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold" for="wa-tindak">Jenis Tindak Lanjut</label>
                        <select class="form-select form-select-sm" id="wa-tindak" name="tindak_lanjut">
                            <?php foreach ($tindak as $kode => $nama) : ?>
                                <option value="<?= esc($kode) ?>"><?= esc($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-3">
                        <label class="form-label small fw-bold" for="wa-prioritas">Prioritas</label>
                        <select class="form-select form-select-sm" id="wa-prioritas" name="prioritas">
                            <?php foreach ($prioritas as $kode => $nama) : ?>
                                <option value="<?= esc($kode) ?>" <?= $kode === 'normal' ? 'selected' : '' ?>><?= esc($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-3">
                        <label class="form-label small fw-bold" for="wa-tenggat">Tenggat</label>
                        <input type="date" class="form-control form-control-sm" id="wa-tenggat" name="tenggat">
                    </div>
                </div>

                <div class="mt-2">
                    <label class="form-label small fw-bold" for="wa-catatan">Catatan <span class="text-danger">*</span></label>
                    <textarea class="form-control form-control-sm" id="wa-catatan" name="catatan" rows="4" required
                        placeholder="mis. pelanggan tanya ketersediaan ukuran L, janji dikabari sore ini"></textarea>
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

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    var nomorToko = <?= json_encode($peta_nomor, JSON_UNESCAPED_UNICODE) ?>;
    var pilihToko = document.getElementById('wa-toko');
    var pilihNomor = document.getElementById('wa-nomor-dari');

    function isiNomor() {
        var daftar = nomorToko[pilihToko.value] || [];
        pilihNomor.innerHTML = '';

        var bebas = document.createElement('option');
        bebas.value = '0';
        bebas.textContent = 'Belum ditentukan / nomor lain';
        pilihNomor.appendChild(bebas);

        daftar.forEach(function(n) {
            var o = document.createElement('option');
            o.value = n.id;
            o.textContent = n.label + ' — ' + n.nomor;
            pilihNomor.appendChild(o);
        });

        pilihNomor.disabled = daftar.length === 0;
    }

    pilihToko.addEventListener('change', isiNomor);
    isiNomor();

    // pengisian otomatis nomor + id pelanggan dari pencarian CRM yang sudah ada
    var kolom = document.getElementById('wa-cari-pelanggan');
    var saran = document.getElementById('wa-saran');
    var timer = null;

    kolom.addEventListener('input', function() {
        var kata = kolom.value.trim();
        window.clearTimeout(timer);

        if (kata.length < 2) {
            saran.classList.remove('tampil');
            return;
        }

        timer = window.setTimeout(function() {
            var url = '<?= site_url('admin/tugas/cari-pelanggan') ?>?q=' + encodeURIComponent(kata)
                + '&juragan_id=' + encodeURIComponent(pilihToko.value);

            fetch(url, { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    saran.innerHTML = '';
                    (d.results || []).slice(0, 8).forEach(function(p) {
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.textContent = p.nama + ' — ' + (p.nomor || 'tanpa nomor');
                        b.addEventListener('click', function() {
                            document.getElementById('wa-pelanggan').value = p.id;
                            if (p.nomor) {
                                document.getElementById('wa-nomor-ke').value = p.nomor;
                            }
                            kolom.value = p.nama;
                            saran.classList.remove('tampil');
                        });
                        saran.appendChild(b);
                    });
                    saran.classList.toggle('tampil', saran.children.length > 0);
                })
                .catch(function() { saran.classList.remove('tampil'); });
        }, 350);
    });

    document.addEventListener('click', function(e) {
        if (!saran.contains(e.target) && e.target !== kolom) saran.classList.remove('tampil');
    });
});
</script>
<?= $this->endSection() ?>
