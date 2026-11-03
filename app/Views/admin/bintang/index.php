<?php

/**
 * Daftar Reminder Bintang 5: empat tab status, isinya hanya permintaan ulasan.
 *
 * Bentuk dan cara kerjanya disamakan dengan Reminder WhatsApp — tab status,
 * saringan, kepala kolom, tombol aksi, dan modal konfirmasinya. Yang berbeda
 * hanya topiknya: isinya permintaan ulasan pelanggan dan penagihan yang belum
 * bergerak, bukan percakapan WhatsApp.
 *
 * Semua variabel di bawah disuntik Admin\Bintang::index() lewat view() (extract),
 * jadi analyzer tidak bisa menelusurinya dari file ini. Blok @var di bawah hanya
 * memberi tahunya ke Intelephense, bukan kode yang dijalankan.
 *
 * @var string $title      judul halaman
 * @var array  $s          saringan yang sudah dibersihkan controller
 * @var list<array<string, mixed>> $baris
 * @var array<int, array<string, mixed>> $ulasan  invoice_id => baris crm_ulasan
 * @var int    $total      jumlah baris sesuai saringan
 * @var int    $page       halaman aktif (berbasis satu)
 * @var int    $batas      baris per halaman
 * @var array<string, string>      $tab      kunci tab => label
 * @var array<string, int>         $angka    kunci tab => jumlah baris
 * @var list<array<string, mixed>> $rekap    jumlah pelepasan per orang (tab tak_selesai)
 * @var array<int, string>         $toko
 * @var array<int, list<string>>   $platform juragan_id => platform aktif toko itu
 * @var int    $milik      id akun yang sedang login
 * @var bool   $kelola     akun ini admin/superadmin
 * @var string $kabar      pesan sukses flash (string kosong kalau tidak ada)
 * @var string $galat      pesan gagal flash (string kosong kalau tidak ada)
 */

$level    = (string) session()->get('level');
$hari_ini = date('Y-m-d');

// kolom "Role" pada tab "Tidak Jadi Dikerjakan": level akun yang memegang tugasnya
$role = ['cs' => 'CS', 'admin' => 'Admin', 'superadmin' => 'Superadmin'];

$jejak = $s['tab'] === 'tak_selesai';

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

    /* Yang boleh tergulir cuma isi tabel. Deretan tab + saringan menempel di atas
       (.b5-lekat) dan kepala kolom ikut menempel di dalam wadah gulirnya
       (.b5-gulir), jadi baris yang jalan tidak pernah menutupi pilihan tab.
       Di bawah 992px dibiarkan seperti dulu: halaman yang digulir, sebab di layar
       kecil blok saringan sudah makan separuh tinggi layar kalau ikut menempel. */
    .b5-halaman { --b5-atas: 260px; }
    .b5-lekat { background: #f9fafb; }
    .b5-gulir .table { border-collapse: separate; border-spacing: 0; }
    /* separate di atas bukan hiasan: dengan border-collapse: collapse, garis kepala
       tabel ikut hilang saat baris bergerak (perilaku sticky yang diketahui). */
    .b5-gulir thead th { position: sticky; top: 0; z-index: 2; }
    /* .table-responsive menata tinggi gulir mendatar (7px); gulir tegak yang baru
       ini diberi ukuran yang sama supaya tidak terlihat dua jenis scrollbar */
    .b5-gulir::-webkit-scrollbar { width: 7px; }

    @media (min-width: 992px) {
        .b5-lekat {
            position: sticky; top: 0; z-index: 1025;
            padding-top: .3rem; margin-top: -.3rem;
        }
        .b5-gulir {
            /* 260px = judul + tab + saringan + pager; kalau perkiraan meleset,
               tab dan saringan masih menempel, hanya sisanya yang ikut tergulir */
            max-height: calc(100vh - var(--b5-atas));
            max-height: calc(100dvh - var(--b5-atas));
            min-height: 180px;
            overflow: auto;
            /* mentok gulir di tabel tidak boleh membuat halaman di belakangnya ikut bergerak */
            overscroll-behavior: contain;
        }
    }

    .b5-kartu {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        overflow: hidden;
    }
    .table.b5-daftar { margin: 0; }
    .b5-daftar th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; background: #f9fafb; }
    .b5-daftar td { vertical-align: middle; font-size: .86rem; }
    .b5-daftar thead th:first-child { width: 40%; }
    .b5-nama { font-weight: 700; color: #0f172a; text-decoration: none; display: block; }
    .b5-nama:hover { color: #da251d; }
    .b5-sub { font-size: .76rem; color: #64748b; display: flex; flex-wrap: wrap; gap: .2rem .6rem; margin-top: .12rem; }
    /* catatan ulasan dipotong dua baris supaya tinggi baris tabel seragam;
       teks lengkapnya tetap terbaca di halaman rinci tugas */
    .b5-kutipan { font-size: .76rem; color: #64748b; margin-top: .3rem; display: -webkit-box;
        -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
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

    /* Tabel jejak: lebar kolom dipatok supaya isian panjang tidak mendorong tabel
       melebar; alasan tidak dipajang di sel — ia dibuka lewat tombol Alasan di
       kolom paling kanan, dan waktu muat satu baris supaya sejajar. */
    .b5-daftar.tabel-jejak { table-layout: fixed; min-width: 840px; }
    .tabel-jejak thead th:first-child { width: auto; }
    /* tabel-rapi mematok kolom terakhir ke 1%; di layout tetap itu bikin tombol
       Alasan tercekik, jadi lebarnya ditentukan di sini */
    .tabel-jejak thead th:last-child { width: 10%; }
    .tabel-jejak td { overflow-wrap: anywhere; }
    .tabel-jejak .k-nota { width: 26%; }
    .tabel-jejak .k-ulasan { width: 18%; }
    .tabel-jejak .k-role { width: 9%; }
    .tabel-jejak .k-orang { width: 17%; }
    .tabel-jejak .k-waktu { width: 20%; }
    .pil-role { display: inline-block; padding: .16rem .5rem; border-radius: .45rem; font-size: .66rem;
        font-weight: 800; text-transform: uppercase; letter-spacing: .04em; background: #f1f5f9; color: #475569; }
    .r-cs { background: #e6f4fb; color: #0369a1; }
    .r-admin { background: #fef3e2; color: #b45309; }
    .r-superadmin { background: #fdf1f0; color: #b81c16; }
    .b5-waktu { font-size: .76rem; color: #475569; white-space: nowrap; }

    .b5-teenggat { font-size: .76rem; white-space: nowrap; }
    .lewat { color: #b81c16; font-weight: 700; }
    .b5-aksi { display: inline-flex; gap: .3rem; align-items: center; }
    .b5-pager { padding: .8rem 1rem; background: #fff; border-top: 1px solid #eaecf0; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl b5-halaman">

    <div class="b5-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><i class="fal fa-star me-1 text-warning"></i> <?= esc($title) ?></h1>
            <p>Nota yang dananya sudah masuk &mdash; DP, sedang dicicil, atau lunas &mdash; boleh diminta ulasannya secara netral: bintang lima di Google Maps. Siapa cepat dia ambil &mdash; setiap perpindahan tangan tercatat.</p>
        </div>
        <?php if ($kelola) : ?>
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/tugas/pengaturan')) ?>">
                <i class="fal fa-sliders-h me-1"></i> Aturan Reminder
            </a>
        <?php endif; ?>
    </div>

    <?php if ($kabar !== '') : ?><div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div><?php endif; ?>
    <?php if ($galat !== '') : ?><div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div><?php endif; ?>

    <div class="b5-lekat">
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
                <div class="b5-aksi">
                    <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-search"></i> Cari</button>
                    <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/tugas/bintang')) ?>">Ulangi</a>
                </div>
            </div>
        </form>
    </div>

    <div class="b5-kartu">
        <?php if ($baris === []) : ?>
            <div class="b5-kosong">
                <?php if ($s['tab'] === 'belum') : ?>
                    <i class="fal fa-circle-check text-success"></i>
                    Tidak ada yang menunggu diambil. Reminder lahir sendiri begitu notasnya punya pembayaran (DP, dicicil, atau lunas).
                <?php elseif ($s['tab'] === 'proses') : ?>
                    <i class="fal fa-mug-hot text-warning"></i>
                    Tidak ada reminder yang sedang dipegang.
                <?php elseif ($jejak) : ?>
                    <i class="fal fa-circle-check text-success"></i>
                    Tidak ada pelepasan pada saringan ini.
                <?php else : ?>
                    <i class="fal fa-inbox text-muted"></i>
                    Kosong di tab ini.
                <?php endif; ?>
            </div>
        <?php elseif ($jejak) : ?>
            <?php if ($rekap !== []) : ?>
                <div class="alert alert-light border d-flex flex-wrap align-items-center gap-2 py-2 small mb-2">
                    <span class="fw-bold"><i class="fal fa-chart-simple me-1"></i>Paling sering lepas tangan:</span>
                    <?php foreach ($rekap as $r) : ?>
                        <span class="pil-orang"><i class="fal fa-user me-1"></i><?= esc($r['nama'] ?: '(akun dihapus)') ?> &middot; <?= (int) $r['n'] ?>x</span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="table-responsive b5-gulir">
                <table class="table table-hover align-middle b5-daftar tabel-rapi tabel-jejak">
                    <colgroup>
                        <col class="k-nota"><col class="k-ulasan"><col class="k-role">
                        <col class="k-orang"><col class="k-waktu"><col class="k-aksi">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Nota</th>
                            <th>Ulasan</th>
                            <th>Role</th>
                            <th>Yang Lepas</th>
                            <th>Waktu</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baris as $w) : ?>
                            <?php
                            $id       = (int) $w['tugas_id'];
                            $st       = (string) $w['status'];
                            $alasan   = trim((string) ($w['alasan'] ?: $w['catatan_lepas']));
                            $siapa    = trim((string) $w['nama_lepas']);
                            $levelLepas = (string) $w['level_lepas'];
                            $otomatis = (string) $w['jenis'] === 'operan_otomatis';
                            $dilepas  = (int) $w['dilepas_at'];
                            $u        = $ulasan[(int) ($w['invoice_id'] ?? 0)] ?? null;
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
                                    </div>
                                </td>
                                <td>
                                    <?php if ($u !== null && (string) $u['status'] === 'diterima') : ?>
                                        <span class="pil-bintang"><i class="fal fa-star me-1"></i><?= (int) $u['bintang'] ?> bintang</span>
                                    <?php elseif ($u !== null) : ?>
                                        <span class="text-muted small">sudah diminta</span>
                                    <?php else : ?>
                                        <span class="text-muted small">belum diminta</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $siapa === '' ? '<span class="text-muted">-</span>' : '<span class="pil-role r-' . esc($levelLepas) . '">' . esc($role[$levelLepas] ?? ucfirst($levelLepas)) . '</span>' ?></td>
                                <td>
                                    <?= $siapa === '' ? '<span class="text-muted">(akun dihapus)</span>' : '<span class="pil-orang"><i class="fal fa-user"></i> ' . esc($siapa) . '</span>' ?>
                                    <?php if ($otomatis) : ?>
                                        <div class="b5-sub">dioper otomatis, shift habis</div>
                                    <?php endif; ?>
                                </td>
                                <td class="b5-waktu"><?= esc(date('d M Y H:i', $dilepas)) ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal"
                                        data-bs-target="#mtB5Alasan" role="button" data-alasan="<?= esc($alasan, 'attr') ?>"
                                        data-siapa="<?= esc($siapa === '' ? '(akun dihapus)' : $siapa, 'attr') ?>"
                                        data-judul="<?= esc($w['judul'], 'attr') ?>"
                                        data-waktu="<?= esc(date('d M Y H:i', $dilepas), 'attr') ?>"
                                        data-taut="<?= esc(site_url('admin/tugas/' . $id), 'attr') ?>">Alasan</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <div class="table-responsive b5-gulir">
                <table class="table table-hover align-middle b5-daftar tabel-rapi">
                    <thead>
                        <tr>
                            <th>Nota</th>
                            <th>Ulasan</th>
                            <th>Tenggat</th>
                            <th>Penanggung jawab</th>
                            <th class="text-center">Aksi</th>
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
                                    <?php if ($pil !== []) : ?>
                                        <div class="b5-sub mt-1">
                                            <?php foreach ($pil as $nama_p) : ?>
                                                <span class="pil-platform"><?= esc($nama_p) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u !== null && (string) $u['status'] === 'diterima') : ?>
                                        <span class="pil-bintang"><i class="fal fa-star me-1"></i><?= (int) $u['bintang'] ?> bintang</span>
                                        <?php if ($u['platform']) : ?>
                                            <div class="b5-sub"><span><?= esc($u['platform']) ?></span></div>
                                        <?php endif; ?>
                                        <?php if ($u['komentar']) : ?>
                                            <div class="b5-kutipan"><?= esc(mb_substr((string) $u['komentar'], 0, 120)) ?></div>
                                        <?php endif; ?>
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
                                <td class="text-center">
                                    <div class="b5-aksi">
                                        <?php if ($st === 'queued') : ?>
                                            <form method="post" action="<?= esc(site_url('admin/tugas/bintang/ambil/' . $id)) ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">Ambil Tugas</button>
                                            </form>
                                        <?php elseif ($punya && $buka) : ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning rounded-pill" data-bs-toggle="modal"
                                                data-bs-target="#modalBintang" data-tugas="<?= $id ?>"
                                                data-judul="<?= esc($w['judul'], 'attr') ?>"
                                                data-platform="<?= esc(implode(',', $pil), 'attr') ?>" role="button">
                                                <i class="fal fa-star"></i> Catat Ulasan
                                            </button>
                                            <button type="button" class="btn btn-sm btn-success rounded-pill" data-bs-toggle="modal"
                                                data-bs-target="#mtB5Selesai" data-tugas="<?= $id ?>"
                                                data-judul="<?= esc($w['judul'], 'attr') ?>" role="button">Selesai</button>
                                            <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-toggle="modal"
                                                data-bs-target="#mtB5Lepas" data-tugas="<?= $id ?>"
                                                data-judul="<?= esc($w['judul'], 'attr') ?>" role="button">Lepas</button>
                                        <?php else : ?>
                                            <a class="btn btn-sm btn-link text-decoration-none"
                                                href="<?= esc(site_url('admin/tugas/' . $id)) ?>">Riwayat</a>
                                        <?php endif; ?>
                                    </div>
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

<!-- satu pasang modal untuk semua baris: tujuan kirim dan judul nota diisi dari
     tombol yang diklik (lihat bagian js), jadi tidak ada modal per baris -->
<div class="modal fade" id="modalBintang" tabindex="-1" aria-hidden="true" data-modal-aksi="ulasan">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="b5-form-ulasan" method="post" action="">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Catat Ulasan Pelanggan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="b5-modal-judul small text-muted mb-3"></p>
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

<div class="modal fade" id="mtB5Selesai" tabindex="-1" aria-hidden="true" data-modal-aksi="selesai">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Selesaikan reminder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="b5-modal-judul small text-muted mb-3"></p>
                    <label class="form-label small fw-bold" for="b5-selesai-catatan">Hasil</label>
                    <textarea class="form-control form-control-sm" id="b5-selesai-catatan" name="catatan" rows="3"
                        maxlength="500" placeholder="mis. pelanggan sudah membalas dan memberi 5 bintang di Shopee"></textarea>
                    <div class="form-text small">Tertulis di riwayat tugas sebagai hasil kerja kamu.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-success">Selesai</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="mtB5Lepas" tabindex="-1" aria-hidden="true" data-modal-aksi="lepas">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Lepas reminder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="b5-modal-judul small text-muted mb-3"></p>
                    <label class="form-label small fw-bold" for="b5-lepas-alasan">Alasan <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="b5-lepas-alasan" name="alasan" required
                        maxlength="255" placeholder="shift habis / operan ke CS lain">
                    <label class="form-label small fw-bold mt-2" for="b5-lepas-catatan">Catatan penerus</label>
                    <textarea class="form-control form-control-sm" id="b5-lepas-catatan" name="catatan" rows="3"
                        maxlength="500" placeholder="sudah sampai mana"></textarea>
                    <div class="form-text small">Tugas kembali ke antrean supaya bisa diambil orang lain.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger">Kembalikan ke antrean</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="mtB5Alasan" tabindex="-1" aria-hidden="true" data-modal-aksi="alasan">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Alasan lepas tangan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="b5-modal-judul small text-muted mb-2"></p>
                <p class="b5-alasan-isi mb-2" style="white-space: pre-line"></p>
                <p class="small text-muted mb-0">
                    <i class="fal fa-user me-1"></i><span class="b5-alasan-siapa"></span>
                    &middot; <i class="fal fa-clock me-1"></i><span class="b5-alasan-waktu"></span>
                </p>
            </div>
            <div class="modal-footer">
                <a class="b5-alasan-taut btn btn-sm btn-link text-decoration-none" href="#">Lihat riwayat tugas</a>
                <button type="button" class="btn btn-sm btn-dark" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    var dasar = {
        ulasan:  '<?= site_url('admin/tugas/ulasan') ?>/',
        selesai: '<?= site_url('admin/tugas/bintang/selesai') ?>/',
        lepas:   '<?= site_url('admin/tugas/bintang/lepas') ?>/'
    };

    // platform aktif toko nota itu, supaya CS tahu ulasannya mau diminta di mana
    function isiPlatform(pemicu) {
        var pilih = document.getElementById('b5-platform');

        if (! pilih) {
            return;
        }

        pilih.innerHTML = '';

        var kosong = document.createElement('option');
        kosong.value = '';
        kosong.textContent = 'Tidak disebutkan';
        pilih.appendChild(kosong);

        (pemicu.dataset.platform || '').split(',').filter(Boolean).forEach(function (nama) {
            var o = document.createElement('option');
            o.value = nama;
            o.textContent = nama;
            pilih.appendChild(o);
        });
    }

    // satu modal per aksi dipakai bersama semua baris: tujuan kirim, judul nota,
    // dan isian khususnya diambil dari tombol yang diklik
    document.querySelectorAll('.modal[data-modal-aksi]').forEach(function (modal) {
        modal.addEventListener('show.bs.modal', function (event) {
            var pemicu = event.relatedTarget;

            if (! pemicu) {
                return;
            }

            var jenis = modal.dataset.modalAksi;
            var form  = modal.querySelector('form');

            if (form && dasar[jenis]) {
                form.action = dasar[jenis] + (pemicu.dataset.tugas || '');
                form.reset();
            }

            var judul = modal.querySelector('.b5-modal-judul');

            if (judul) {
                judul.textContent = pemicu.dataset.judul || '';
            }

            if (jenis === 'ulasan') {
                isiPlatform(pemicu);
            }

            if (jenis === 'alasan') {
                // textContent dipakai supaya teks alasan mentah tidak pernah
                // dianggap markup
                modal.querySelector('.b5-alasan-isi').textContent = pemicu.dataset.alasan || 'Tanpa alasan tercatat.';
                modal.querySelector('.b5-alasan-siapa').textContent = pemicu.dataset.siapa || '-';
                modal.querySelector('.b5-alasan-waktu').textContent = pemicu.dataset.waktu || '';
                modal.querySelector('.b5-alasan-taut').href = pemicu.dataset.taut || '#';
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
