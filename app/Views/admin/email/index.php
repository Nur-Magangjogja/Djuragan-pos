<?php

/**
 * Daftar Cek Email: catatan hasil melihat kotak masuk, per toko.
 *
 * @var list<array<string, mixed>> $baris
 * @var array<string, string>      $tab   kunci => label
 * @var array<string, int>         $angka kunci => jumlah baris
 * @var array<int, string>         $toko
 */

$level    = (string) session()->get('level');
$hari_ini = date('Y-m-d');

$tanya = static function (array $ubah): string {
    $q = array_merge($_GET, $ubah);

    if (! array_key_exists('halaman', $ubah)) {
        unset($q['halaman']);
    }

    foreach (['tab', 'toko', 'cari', 'saya', 'batas', 'halaman'] as $k) {
        if (isset($q[$k]) && $q[$k] === '') {
            unset($q[$k]);
        }
    }

    return site_url('admin/tugas/email') . '?' . http_build_query($q);
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .em-halaman { max-width: 1080px; }
    .em-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .em-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }

    .em-buat { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .55rem; padding: .9rem 1rem; margin-bottom: 1rem;
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem; }
    .em-buat .lapangan { display: flex; flex-direction: column; gap: .2rem; }
    .em-buat .lebar { flex: 1; min-width: 260px; }
    .em-buat label, .em-saring label { font-size: .7rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }

    .em-tab { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1rem; }
    .em-tab a {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .34rem .85rem; border-radius: 999px; font-size: .8rem; font-weight: 600;
        background: #f4f6f9; color: #475569; text-decoration: none; border: 1px solid #e2e8f0;
    }
    .em-tab a.aktif { background: #101828; border-color: #101828; color: #fff; }
    .em-tab a span { font-weight: 700; }

    .em-saring { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .55rem; margin-bottom: 1rem; }
    .em-saring .lapangan { display: flex; flex-direction: column; gap: .2rem; }
    .em-saring .form-control, .em-saring .form-select { max-width: 190px; }

    .em-kartu { background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12); overflow: hidden; }
    .table.em-daftar { margin: 0; }
    .em-daftar th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; background: #f9fafb; }
    .em-daftar td { vertical-align: middle; font-size: .86rem; }
    .em-catatan { color: #0f172a; font-size: .88rem; }
    .em-sub { font-size: .76rem; color: #64748b; display: flex; flex-wrap: wrap; gap: .2rem .6rem; margin-top: .12rem; }
    .em-kosong { padding: 2.6rem 1rem; text-align: center; color: #94a3b8; }
    .em-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }

    .pil-status { display: inline-block; padding: .18rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
    .s-queued { background: #eef1f6; color: #475569; }
    .s-assigned { background: #e6f4fb; color: #0369a1; }
    .s-in_progress { background: #fef3e2; color: #b45309; }
    .s-waiting { background: #f3e8ff; color: #6b21a8; }
    .s-completed { background: #e7f6ec; color: #14663a; }
    .s-cancelled { background: #fdf1f0; color: #b81c16; }
    .pil-orang { display: inline-block; padding: .16rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: #e6f4fb; color: #0369a1; }
    .pil-buat { background: #f4f6f9; color: #475569; }

    .em-teenggat { font-size: .76rem; white-space: nowrap; }
    .lewat { color: #b81c16; font-weight: 700; }
    .em-aksi { display: inline-flex; gap: .3rem; align-items: center; justify-content: flex-end; }
    .em-form-lepas { margin-top: .4rem; }
    .em-pager { padding: .8rem 1rem; background: #fff; border-top: 1px solid #eaecf0; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl em-halaman">

    <div class="em-head">
        <div>
            <h1 class="h3 mt-5"><i class="fal fa-envelope me-1 text-brand-500"></i> <?= esc($title) ?></h1>
            <p>Catat apa yang dilihat di kotak masuk hari ini. Tidak ada akun yang perlu ditulis, tidak ada sandi yang disimpan.</p>
        </div>
    </div>

    <?php if ($kabar !== '') : ?><div class="alert alert-success py-2"><?= esc($kabar) ?></div><?php endif; ?>
    <?php if ($galat !== '') : ?><div class="alert alert-danger py-2"><?= esc($galat) ?></div><?php endif; ?>

    <?php if ($toko !== []) : ?>
        <?= form_open('admin/tugas/email/simpan', ['class' => 'em-buat']) ?>
            <div class="lebar">
                <label class="form-label small fw-bold mb-1" for="catatan">Catatan cek email</label>
                <textarea class="form-control form-control-sm" id="catatan" name="catatan" rows="2" required
                    maxlength="2000" placeholder="mis. belum ada balasan ukuran dari pelanggan 0812..., nota sudah dikirim kemarin"></textarea>
            </div>
            <div class="lapangan">
                <label for="email-toko">Toko</label>
                <select class="form-select form-select-sm" id="email-toko" name="juragan_id" required>
                    <?php foreach ($toko as $id_toko => $nama_t) : ?>
                        <option value="<?= (int) $id_toko ?>"><?= esc($nama_t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lapangan">
                <label for="email-tenggat">Tenggat</label>
                <input type="date" class="form-control form-control-sm" id="email-tenggat" name="tenggat">
            </div>
            <div class="lapangan">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-sm btn-primary"><i class="fal fa-plus me-1"></i> Simpan catatan</button>
            </div>
        <?= form_close() ?>
    <?php endif; ?>

    <div class="em-tab">
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

    <form class="em-saring" method="get" action="<?= esc(site_url('admin/tugas/email')) ?>">
        <input type="hidden" name="tab" value="<?= esc($s['tab']) ?>">
        <input type="hidden" name="saya" value="<?= (int) $s['milik_saya'] === $milik ? '1' : '' ?>">
        <div class="lapangan">
            <label for="cari">Cari</label>
            <input type="search" class="form-control form-control-sm" id="cari" name="cari" value="<?= esc($s['cari']) ?>"
                placeholder="isi catatan">
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
            <div class="em-aksi">
                <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-search"></i> Cari</button>
                <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/tugas/email')) ?>">Ulangi</a>
            </div>
        </div>
    </form>

    <div class="em-kartu">
        <?php if ($baris === []) : ?>
            <div class="em-kosong">
                <i class="fal fa-circle-check text-success"></i>
                <?= $s['tab'] === 'belum' ? 'Belum ada catatan cek email yang menunggu.' : 'Kosong di tab ini.' ?>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle em-daftar">
                    <thead>
                        <tr>
                            <th>Catatan</th>
                            <th>Tenggat</th>
                            <th>Penanggung jawab</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baris as $w) : ?>
                            <?php
                            $id      = (int) $w['id_tugas'];
                            $st      = (string) $w['status'];
                            $punya   = (int) $w['dikerjakan_oleh'] === $milik;
                            $buka    = in_array($st, \App\Models\TugasModel::BELUM_SELESAI, true);
                            $tenggat = (string) ($w['tenggat'] ?? '');
                            $terlambat = $buka && $tenggat !== '' && $tenggat < $hari_ini;
                            ?>
                            <tr>
                                <td>
                                    <div class="em-catatan"><?= nl2br(esc($w['deskripsi'] ?: $w['judul'])) ?></div>
                                    <div class="em-sub">
                                        <span>#<?= $id ?></span>
                                        <?php if ($w['nama_juragan']) : ?><span><i class="fal fa-store"></i> <?= esc($w['nama_juragan']) ?></span><?php endif; ?>
                                        <span class="pil-status s-<?= esc($st) ?>"><?= esc(\App\Models\TugasModel::STATUS[$st] ?? $st) ?></span>
                                        <span class="pil-orang pil-buat">dibuat: <?= esc($w['nama_dibuat'] ?: '?') ?></span>
                                    </div>
                                </td>
                                <td class="em-teenggat <?= $terlambat ? 'lewat' : '' ?>">
                                    <?= $tenggat === '' ? '-' : esc(tanggal_rincian($tenggat)) ?>
                                    <?php if ($terlambat) : ?><i class="fal fa-exclamation-triangle"></i><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($w['nama_dikerjakan']) : ?>
                                        <span class="pil-orang"><i class="fal fa-user-check me-1"></i><?= esc($w['nama_dikerjakan']) ?></span>
                                        <?php if ($w['nama_selesai']) : ?>
                                            <div class="em-sub"><span>selesai: <?= esc($w['nama_selesai']) ?></span></div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="text-muted">belum diambil</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="em-aksi flex-wrap">
                                        <?php if ($st === 'queued') : ?>
                                            <form method="post" action="<?= esc(site_url('admin/tugas/email/ambil/' . $id)) ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">Ambil Catatan</button>
                                            </form>
                                        <?php elseif ($punya && $buka) : ?>
                                            <form method="post" action="<?= esc(site_url('admin/tugas/email/selesai/' . $id)) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="catatan" value="sudah dibaca">
                                                <button type="submit" class="btn btn-sm btn-success rounded-pill">Selesai</button>
                                            </form>
                                            <a class="btn btn-sm btn-link text-decoration-none" data-bs-toggle="collapse"
                                                href="#lepas-<?= $id ?>" role="button">Lepas</a>
                                        <?php else : ?>
                                            <a class="btn btn-sm btn-link text-decoration-none"
                                                href="<?= esc(site_url('admin/tugas/' . $id)) ?>">Riwayat</a>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($punya && $buka) : ?>
                                        <div class="collapse" id="lepas-<?= $id ?>">
                                            <?= form_open('admin/tugas/email/lepas/' . $id, ['class' => 'em-form-lepas']) ?>
                                                <input type="text" class="form-control form-control-sm" name="alasan" maxlength="255"
                                                    placeholder="alasan dilepas (wajib)" required>
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill mt-1">Kembalikan ke daftar</button>
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
            <div class="em-pager d-flex flex-wrap align-items-center gap-2">
                <span class="small text-muted"><?= (int) $total ?> catatan &middot; halaman <?= (int) $page ?></span>
                <div class="ms-auto">
                    <?= \Config\Services::pager()->makeLinks($page, $batas, $total, 'front_full') ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<?= $this->endSection() ?>
