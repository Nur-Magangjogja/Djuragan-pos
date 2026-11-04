<?php

/**
 * Daftar Reminder Bintang 5: permintaan ulasan + tab penagihan.
 *
 * Semua variabel di bawah disuntik Admin\Bintang::index() lewat view(); blok @var
 * ini hanya memberi tahunya ke analyzer, bukan kode yang dijalankan.
 *
 * @var string $title      judul halaman
 * @var array  $s          saringan yang sudah dibersihkan controller
 * @var list<array<string, mixed>> $baris
 * @var array<int, array<string, mixed>> $ulasan  invoice_id => baris crm_ulasan
 * @var int    $total      jumlah baris sesuai saringan
 * @var int    $page       halaman aktif (berbasis satu)
 * @var int    $batas      baris per halaman
 * @var array<string, string>      $tab    kunci => label
 * @var array<string, int>         $angka  kunci => jumlah baris
 * @var array<int, string>         $toko
 * @var array<int, list<string>>   $platform juragan_id => platform aktif toko itu
 * @var int    $milik      id akun yang sedang login
 * @var string $kabar      pesan sukses flash (string kosong kalau tidak ada)
 * @var string $galat      pesan gagal flash (string kosong kalau tidak ada)
 */

$level    = (string) session()->get('level');
$hari_ini = date('Y-m-d');

$kelola = in_array($level, ['admin', 'superadmin'], true);

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

    return site_url('admin/tugas/bintang') . '?' . http_build_query($q);
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .b5-halaman { max-width: 1180px; }
    .b5-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .b5-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }

    .b5-tab { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1rem; }
    .b5-tab a {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .34rem .85rem; border-radius: 999px; font-size: .8rem; font-weight: 600;
        background: #f4f6f9; color: #475569; text-decoration: none; border: 1px solid #e2e8f0;
    }
    .b5-tab a.aktif { background: #101828; border-color: #101828; color: #fff; }
    .b5-tab a span { font-weight: 700; }

    .b5-saring { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .55rem; margin-bottom: 1rem; }
    .b5-saring .lapangan { display: flex; flex-direction: column; gap: .2rem; }
    .b5-saring label { font-size: .7rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .b5-saring .form-control, .b5-saring .form-select { max-width: 190px; }

    /* resep responsif rumah: ikon judul dibuang, tombol kepala & pil tab menutup
       lebar kartu, dan baris filter jadi satu baris [cari][toko][ikon][ikon] */
    @media (max-width: 991.98px) {
        .b5-head h1 > i { display: none; }
        .b5-head > .me-auto { flex: 1 1 100%; }
        .b5-head > a.btn { flex: 1 1 auto; min-width: 0; }
        .b5-tab { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .b5-tab a { justify-content: center; }
        .b5-tab a:last-child:nth-child(odd) { grid-column: 1 / -1; }
        /* satu baris penuh: cari melebar, Toko seperlunya, lalu ikon cari & ulangi */
        .b5-saring { flex-wrap: nowrap; gap: .5rem; }
        .b5-saring > .lapangan:first-of-type { flex: 1 1 auto; min-width: 0; }
        .b5-saring > .b5-toko { flex: 0 1 8.5rem; min-width: 64px; }
        .b5-saring .form-control, .b5-saring .form-select { max-width: none; width: 100%; }
        .b5-saring > .b5-saring-aksi { flex: 0 0 auto; }
        /* tombol ikon saja lebih pendek dari tombol bertuliskan (FontAwesome memakai
           line-height:1), jadi tingginya dipatok sama dengan kotak isian 2rem */
        .b5-saring .b5-aksi { align-items: stretch; }
        .b5-saring .b5-aksi .btn { min-height: 2rem; }
    }

    .b5-kartu {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        overflow: hidden;
    }
    .table.b5-daftar { margin: 0; }
    .b5-daftar th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; background: #f9fafb; }
    .b5-daftar td { vertical-align: middle; font-size: .86rem; }
    .b5-nama { font-weight: 700; color: #0f172a; text-decoration: none; display: block; }
    .b5-nama:hover { color: #da251d; }
    .b5-sub { font-size: .76rem; color: #64748b; display: flex; flex-wrap: wrap; gap: .2rem .6rem; margin-top: .12rem; }
    .b5-kosong { padding: 2.6rem 1rem; text-align: center; color: #94a3b8; }
    .b5-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }

    .pil-status { display: inline-block; padding: .18rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
    .s-queued { background: #eef1f6; color: #475569; }
    .s-assigned { background: #e6f4fb; color: #0369a1; }
    .s-in_progress { background: #fef3e2; color: #b45309; }
    .s-waiting { background: #f3e8ff; color: #6b21a8; }
    .s-completed { background: #e7f6ec; color: #14663a; }
    .s-cancelled { background: #fdf1f0; color: #b81c16; }
    .pil-bintang { display: inline-block; padding: .16rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: #fff4d6; color: #8a5a00; }
    .pil-platform { display: inline-block; padding: .16rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: #f1f5f9; color: #334155; }
    .pil-orang { display: inline-block; padding: .16rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: #e6f4fb; color: #0369a1; }
    .pil-buat { background: #f4f6f9; color: #475569; }

    .b5-teenggat { font-size: .76rem; white-space: nowrap; }
    .lewat { color: #b81c16; font-weight: 700; }
    .b5-aksi { display: inline-flex; gap: .3rem; align-items: center; justify-content: flex-end; }
    .b5-form-lepas { margin-top: .4rem; }
    .b5-pager { padding: .8rem 1rem; background: #fff; border-top: 1px solid #eaecf0; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl b5-halaman">

    <div class="b5-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><i class="fal fa-star me-1 text-warning"></i> <?= esc($title) ?></h1>
            <p>Nota yang sudah selesai dan lunas diminta ulasannya secara netral. Satu tab lagi menampung tagihan yang belum bergerak. Siapa cepat dia ambil.</p>
        </div>
        <?php if ($kelola) : ?>
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/tugas/pengaturan')) ?>">
                <i class="fal fa-sliders-h me-1"></i> Aturan Reminder
            </a>
        <?php endif; ?>
    </div>

    <?php if ($kabar !== '') : ?><div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div><?php endif; ?>
    <?php if ($galat !== '') : ?><div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div><?php endif; ?>

    <div class="b5-tab">
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

    <form class="b5-saring" method="get" action="<?= esc(site_url('admin/tugas/bintang')) ?>">
        <input type="hidden" name="tab" value="<?= esc($s['tab']) ?>">
        <input type="hidden" name="saya" value="<?= (int) $s['milik_saya'] === $milik ? '1' : '' ?>">
        <div class="lapangan">
            <label for="cari">Cari</label>
            <input type="search" class="form-control form-control-sm" id="cari" name="cari" value="<?= esc($s['cari']) ?>"
                placeholder="nomor nota atau pelanggan">
        </div>
        <?php if (count($toko) > 1) : ?>
            <div class="lapangan b5-toko">
                <label for="toko">Toko</label>
                <select class="form-select form-select-sm" id="toko" name="toko">
                    <option value="">Semua toko</option>
                    <?php foreach ($toko as $id_toko => $nama_t) : ?>
                        <option value="<?= (int) $id_toko ?>" <?= (int) $s['juragan_id'] === (int) $id_toko ? 'selected' : '' ?>><?= esc($nama_t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="lapangan b5-saring-aksi">
            <label>&nbsp;</label>
            <div class="b5-aksi">
                <button type="submit" class="btn btn-sm btn-dark" aria-label="Cari">
                    <i class="fal fa-search"></i><span class="d-none d-lg-inline"> Cari</span>
                </button>
                <a class="btn btn-sm btn-dark text-decoration-none d-inline-flex d-lg-none"
                    href="<?= esc(site_url('admin/tugas/bintang')) ?>" title="Ulangi" aria-label="Ulangi"><i class="fal fa-sync"></i></a>
                <a class="btn btn-sm btn-link text-decoration-none d-none d-lg-inline"
                    href="<?= esc(site_url('admin/tugas/bintang')) ?>">Ulangi</a>
            </div>
        </div>
    </form>

    <div class="b5-kartu">
        <?php if ($baris === []) : ?>
            <div class="b5-kosong">
                <?php if ($s['tab'] === 'belum') : ?>
                    <i class="fal fa-circle-check text-success"></i>
                    Tidak ada ulasan yang menunggu. Reminder lahir sendiri dari nota selesai + lunas.
                <?php elseif ($s['tab'] === 'tagih') : ?>
                    <i class="fal fa-circle-check text-success"></i>
                    Tidak ada tagihan yang perlu disusul.
                <?php else : ?>
                    <i class="fal fa-inbox text-muted"></i>
                    Kosong di tab ini.
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle b5-daftar">
                    <thead>
                        <tr>
                            <th>Nota</th>
                            <th>Ulasan</th>
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
                            $tagih   = $s['tab'] === 'tagih';
                            $u       = $ulasan[(int) $w['invoice_id']] ?? null;
                            $pil     = $platform[(int) $w['juragan_id']] ?? [];
                            ?>
                            <tr>
                                <td>
                                    <a class="b5-nama" href="<?= esc(site_url('admin/tugas/' . $id)) ?>"><?= esc($w['judul']) ?></a>
                                    <div class="b5-sub">
                                        <span>#<?= $id ?></span>
                                        <?php if ($w['invoice_seri']) : ?><span><i class="fal fa-receipt"></i> <?= esc($w['invoice_seri']) ?></span><?php endif; ?>
                                        <?php if ($w['nama_pelanggan']) : ?><span><i class="fal fa-user"></i> <?= esc($w['nama_pelanggan']) ?></span><?php endif; ?>
                                        <?php if ($w['nama_juragan']) : ?><span><i class="fal fa-store"></i> <?= esc($w['nama_juragan']) ?></span><?php endif; ?>
                                        <span class="pil-status s-<?= esc($st) ?>"><?= esc(\App\Models\TugasModel::STATUS[$st] ?? $st) ?></span>
                                        <span class="pil-orang pil-buat">dibuat: <?= esc($w['nama_dibuat'] ?: 'sistem') ?></span>
                                    </div>
                                    <?php if (! $tagih && $pil !== []) : ?>
                                        <div class="b5-sub mt-1">
                                            <?php foreach ($pil as $nama_p) : ?>
                                                <span class="pil-platform"><?= esc($nama_p) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tagih) : ?>
                                        <span class="text-muted small">penagihan</span>
                                    <?php elseif ($u !== null && (string) $u['status'] === 'diterima') : ?>
                                        <span class="pil-bintang"><i class="fal fa-star me-1"></i><?= (int) $u['bintang'] ?> bintang</span>
                                        <div class="b5-sub">
                                            <?php if ($u['platform']) : ?><span><?= esc($u['platform']) ?></span><?php endif; ?>
                                            <?php if ($u['komentar']) : ?><span><?= esc(mb_substr((string) $u['komentar'], 0, 60)) ?></span><?php endif; ?>
                                        </div>
                                    <?php elseif ($u !== null) : ?>
                                        <span class="text-muted small">sudah diminta, belum masuk</span>
                                    <?php else : ?>
                                        <span class="text-muted small">belum diminta</span>
                                    <?php endif; ?>
                                </td>
                                <td class="b5-teenggat <?= $terlambat ? 'lewat' : '' ?>">
                                    <?= $tenggat === '' ? '-' : esc(tanggal_rincian($tenggat)) ?>
                                    <?php if ($terlambat) : ?><i class="fal fa-exclamation-triangle"></i><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($w['nama_dikerjakan']) : ?>
                                        <span class="pil-orang"><i class="fal fa-user-check me-1"></i><?= esc($w['nama_dikerjakan']) ?></span>
                                        <?php if ($w['nama_selesai']) : ?>
                                            <div class="b5-sub"><span>selesai: <?= esc($w['nama_selesai']) ?></span></div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="text-muted">belum diambil</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="b5-aksi flex-wrap">
                                        <?php if ($st === 'queued') : ?>
                                            <form method="post" action="<?= esc(site_url('admin/tugas/bintang/ambil/' . $id)) ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">Ambil Tugas</button>
                                            </form>
                                        <?php elseif ($punya && $buka) : ?>
                                            <?php if (! $tagih) : ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning rounded-pill" data-bs-toggle="modal"
                                                    data-bs-target="#modalBintang" data-tugas="<?= $id ?>"
                                                    data-platform="<?= esc(implode(',', $pil)) ?>">
                                                    <i class="fal fa-star"></i> Catat Ulasan
                                                </button>
                                            <?php endif; ?>
                                            <form method="post" action="<?= esc(site_url('admin/tugas/bintang/selesai/' . $id)) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="catatan" value="selesai dari daftar reminder bintang">
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
                                            <?= form_open('admin/tugas/bintang/lepas/' . $id, ['class' => 'b5-form-lepas']) ?>
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
            <div class="b5-pager d-flex flex-wrap align-items-center gap-2">
                <span class="small text-muted"><?= (int) $total ?> reminder &middot; halaman <?= (int) $page ?></span>
                <div class="ms-auto">
                    <?= \Config\Services::pager()->makeLinks($page, $batas, $total, 'front_full') ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- modal catat ulasan: satu popup untuk semua baris, isinya diisi JS -->
<div class="modal fade" id="modalBintang" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="b5-form-ulasan" method="post" action="">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Catat Ulasan Pelanggan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3" id="b5-tugas-label"></p>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold" for="b5-bintang">Bintang <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="b5-bintang" name="bintang" required>
                                <?php for ($b = 5; $b >= 1; $b--) : ?>
                                    <option value="<?= $b ?>"><?= $b ?> bintang</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold" for="b5-platform">Platform</label>
                            <select class="form-select form-select-sm" id="b5-platform" name="platform">
                                <option value="">Tidak disebutkan</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small fw-bold" for="b5-komentar">Isi ulasan (opsional)</label>
                        <textarea class="form-control form-control-sm" id="b5-komentar" name="komentar" rows="3" maxlength="500"
                            placeholder="salin apa adanya, jangan ditambah-tambahi"></textarea>
                    </div>
                    <div class="form-text small mt-2">
                        Mencatat ulasan tidak menutup remindernya. Pakai tombol Selesai kalau pekerjaannya memang sudah beres.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fal fa-save me-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    var dasar = '<?= site_url('admin/tugas/ulasan') ?>/';
    var form    = document.getElementById('b5-form-ulasan');
    var label   = document.getElementById('b5-tugas-label');
    var pilih   = document.getElementById('b5-platform');
    var judul   = '';

    document.querySelectorAll('[data-bs-target="#modalBintang"]').forEach(function (tombol) {
        tombol.addEventListener('click', function () {
            var id = tombol.getAttribute('data-tugas') || '';

            judul = tombol.closest('tr') ? tombol.closest('tr').querySelector('.b5-nama').textContent : '';
            form.action = dasar + id;
            label.textContent = 'Nota: ' + judul;

            pilih.innerHTML = '';

            var kosong = document.createElement('option');
            kosong.value = '';
            kosong.textContent = 'Tidak disebutkan';
            pilih.appendChild(kosong);

            (tombol.getAttribute('data-platform') || '').split(',').filter(Boolean).forEach(function (nama) {
                var o = document.createElement('option');
                o.value = nama;
                o.textContent = nama;
                pilih.appendChild(o);
            });
        });
    });
});
</script>
<?= $this->endSection() ?>
