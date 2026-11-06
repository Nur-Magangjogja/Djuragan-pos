<?php

/**
 * Daftar Reminder WhatsApp: tiga tab status + form buat tugas baru.
 *
 * Semua variabel di bawah disuntik Admin\Whatsapp::index() lewat view() (extract),
 * jadi analyzer tidak bisa menelusurinya dari file ini. Blok @var di bawah hanya
 * memberi tahunya ke IntelliJ/Intelephense, bukan kode yang dijalankan.
 *
 * @var string $title      judul halaman
 * @var array  $s          saringan yang sudah dibersihkan controller
 * @var list<array<string, mixed>> $baris
 * @var int    $total      jumlah baris sesuai saringan
 * @var int    $page       halaman aktif (berbasis satu)
 * @var int    $batas      baris per halaman
 * @var array<string, string>      $tab     kunci => label
 * @var array<string, int>         $angka   kunci => jumlah baris
 * @var list<array<string, mixed>> $rekap   jumlah pelepasan per orang (hanya tab tak_selesai)
 * @var array<string, string>      $tindak  kunci => label jenis tindak lanjut
 * @var array<string, string>      $prioritas kunci => label prioritas
 * @var array<int, string>         $toko
 * @var list<array<string, mixed>> $nomor   nomor pengirim yang boleh dipakai akun ini
 * @var int    $milik      id akun yang sedang login
 * @var bool   $kelola     akun ini admin/superadmin
 * @var string $kabar      pesan sukses flash (string kosong kalau tidak ada)
 * @var string $galat      pesan gagal flash (string kosong kalau tidak ada)
 */

$level    = (string) session()->get('level');
$hari_ini = date('Y-m-d');

// kolom "Role" pada tab "Tidak Jadi Dikerjakan": level akun yang memegang tugasnya
$role = ['cs' => 'CS', 'admin' => 'Admin', 'superadmin' => 'Superadmin'];

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

    /* pemicu filter hanya dipakai di layar sempit; di desktop saringan itu sudah
       terlihat, jadi kotaknya tidak perlu — selector harus sedalam .wa-saring
       .lapangan kalau tidak mau kalah darinya */
    .wa-saring > .wa-saring-pemicu { display: none; }
    .wa-saring > .wa-saring-lapis { display: contents; }
    #saringWadah .wa-saring-lapis { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .55rem; }
    #saringWadah .wa-saring-lapis .lapangan:last-child:nth-child(odd) { grid-column: 1 / -1; }
    .wa-saring-kotak {
        display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
        padding: .3rem .7rem; font: inherit; font-size: .84rem; font-weight: 600; color: #fff;
        background: #101828; border: 1px solid #101828; border-radius: 8px; white-space: nowrap;
    }
    .wa-saring-kotak:hover { background: #1e293b; border-color: #1e293b; color: #fff; }
    .wa-saring-jumlah {
        min-width: 1.15rem; padding: 0 .3rem; border-radius: 999px; font-size: .72rem;
        line-height: 1.2; text-align: center; background: #fff; color: #101828;
    }
    /* ikon saja lebih pendek dari tombol bertuliskan (FontAwesome line-height:1) */
    #modalSaringWa .modal-footer { align-items: stretch; }
    .wa-saring .wa-aksi { align-items: stretch; }

    /* Yang boleh tergulir cuma isi tabel. Deretan tab + saringan menempel di atas
       (.wa-lekat) dan kepala kolom ikut menempel di dalam wadah gulirnya
       (.wa-gulir), jadi baris yang jalan tidak pernah menutupi pilihan tab.
       Di bawah 992px dibiarkan seperti dulu: halaman yang digulir, sebab di layar
       kecil blok saringan sudah makan separuh tinggi layar kalau ikut menempel. */
    .wa-halaman { --wa-atas: 290px; }
    .wa-lekat { background: #f9fafb; }
    .wa-gulir .table { border-collapse: separate; border-spacing: 0; }
    /* separate di atas bukan hiasan: dengan border-collapse: collapse, garis kepala
       tabel ikut hilang saat baris bergerak (perilaku sticky yang diketahui). */
    .wa-gulir thead th { position: sticky; top: 0; z-index: 2; }
    /* .table-responsive menata tinggi gulir mendatar (7px); gulir tegak yang baru
       ini diberi ukuran yang sama supaya tidak terlihat dua jenis scrollbar */
    .wa-gulir::-webkit-scrollbar { width: 7px; }

    @media (min-width: 992px) {
        .wa-lekat {
            position: sticky; top: 0; z-index: 1025;
            padding-top: .3rem; margin-top: -.3rem;
        }
        .wa-gulir {
            /* 290px = judul + tab + saringan + pager; kalau perkiraan meleset,
               tab dan saringan masih menempel, hanya sisanya yang ikut tergulir */
            max-height: calc(100vh - var(--wa-atas));
            max-height: calc(100dvh - var(--wa-atas));
            min-height: 180px;
            overflow: auto;
            /* mentok gulir di tabel tidak boleh membuat halaman di belakangnya ikut bergerak */
            overscroll-behavior: contain;
        }
    }

    .wa-kartu {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        overflow: hidden;
    }
    .table.wa-daftar { margin: 0; }
    .wa-daftar th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; background: #f9fafb; }
    .wa-daftar td { vertical-align: middle; font-size: .86rem; }
    .wa-daftar thead th:first-child { width: 40%; }
    .wa-nama { font-weight: 700; color: #0f172a; text-decoration: none; display: block; }
    .wa-nama:hover { color: #da251d; }
    .wa-sub { font-size: .76rem; color: #64748b; display: flex; flex-wrap: wrap; gap: .2rem .6rem; margin-top: .12rem; }
    /* catatan dipotong dua baris supaya tinggi baris tabel seragam;
       teks lengkapnya tetap terbaca di halaman rinci tugas */
    .wa-catatan { font-size: .76rem; color: #64748b; margin-top: .3rem; display: -webkit-box;
        -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
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

    /* Tabel jejak: lebar kolom dipatok supaya isian panjang tidak mendorong
       tabel melebar; alasan tidak lagi dipajang di sel — ia dibuka lewat tombol
       Alasan di kolom paling kanan, dan waktu muat satu baris supaya sejajar. */
    .wa-daftar.tabel-jejak { table-layout: fixed; min-width: 860px; }
    .tabel-jejak thead th:first-child { width: auto; }
    /* tabel-rapi mematok kolom terakhir ke 1%; di layout tetap itu bikin tombol
       Alasan tercekik, jadi lebarnya ditentukan di sini */
    .tabel-jejak thead th:last-child { width: 9%; }
    .tabel-jejak td { overflow-wrap: anywhere; }
    .tabel-jejak .k-reminder { width: 22%; }
    .tabel-jejak .k-nomor { width: 11%; }
    .tabel-jejak .k-tindak { width: 10%; }
    .tabel-jejak .k-role { width: 8%; }
    .tabel-jejak .k-orang { width: 15%; }
    .tabel-jejak .k-waktu { width: 17%; }
    .tabel-jejak .k-aksi { width: 9%; }
    .pil-role { display: inline-block; padding: .16rem .5rem; border-radius: .45rem; font-size: .66rem;
        font-weight: 800; text-transform: uppercase; letter-spacing: .04em; background: #f1f5f9; color: #475569; }
    .r-cs { background: #e6f4fb; color: #0369a1; }
    .r-admin { background: #fef3e2; color: #b45309; }
    .r-superadmin { background: #fdf1f0; color: #b81c16; }
    .wa-waktu { font-size: .76rem; color: #475569; white-space: nowrap; }

    .wa-teenggat { font-size: .76rem; white-space: nowrap; }
    .lewat { color: #b81c16; font-weight: 700; }
    .wa-aksi { display: inline-flex; gap: .3rem; align-items: center; justify-content: flex-end; }
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

    /* Resep responsif rumah, sama seperti halaman SOP/Tugas: ikon judul dibuang
       di layar sempit, tombol kepala & pil tab mengisi lebar kartu, dan isian
       filter tidak lagi terpaku 190px sehingga separuh kartu tidak kosong. */
    @media (max-width: 991.98px) {
        .wa-head h1 > i { display: none; }
        .wa-head > .me-auto { flex: 1 1 100%; }
        .wa-head > a.btn, .wa-head > button.btn { flex: 1 1 auto; min-width: 0; }
        .wa-tab { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .wa-tab a { justify-content: center; }
        .wa-tab a:last-child:nth-child(odd) { grid-column: 1 / -1; }
        /* di HP yang tersisa cuma kotak cari dan kotak filter; Cari & Ulangi
           pindah ke kaki popus supaya tidak ada dua tombol cari sebaris */
        .wa-saring { display: grid; grid-template-columns: minmax(0, 1fr) auto; }
        .wa-saring .form-control, .wa-saring .form-select { max-width: none; width: 100%; }
        .wa-saring > .wa-saring-pemicu { display: flex; }
        .wa-saring > .wa-saring-aksi { display: none; }
    }
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

    <?php if ($kabar !== '') : ?><div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div><?php endif; ?>
    <?php if ($galat !== '') : ?><div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div><?php endif; ?>

    <div class="wa-lekat">
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
            <!-- di layar sempit saringan lain disembunyikan di balik kotak ini -->
            <div class="lapangan wa-saring-pemicu">
                <label>&nbsp;</label>
                <button type="button" class="wa-saring-kotak" id="saring"
                    data-bs-toggle="modal" data-bs-target="#modalSaringWa" aria-label="Buka filter">
                    <i class="fal fa-filter"></i> Filter
                </button>
            </div>
            <div class="wa-saring-lapis" id="saringLapis">
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
            </div>
            <div class="lapangan wa-saring-aksi">
                <label>&nbsp;</label>
                <div class="wa-aksi">
                    <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-search"></i> Cari</button>
                    <button type="button" class="btn btn-sm btn-dark d-inline-flex" data-saring-reset="tab,saya,batas"
                        title="Kosongkan saringan" aria-label="Kosongkan saringan"><i class="fal fa-sync"></i></button>
                </div>
            </div>

            <!-- popus filter layar sempit: ditulis DI DALAM form yang sama supaya
                 field yang dipindah JS ke dalamnya tetap ikut terkirim saat submit -->
            <div class="modal fade" id="modalSaringWa" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Filter</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <div id="saringWadah"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-dark d-inline-flex" data-saring-reset="tab,saya,batas"
                                title="Kosongkan saringan" aria-label="Kosongkan saringan"><i class="fal fa-sync"></i></button>
                            <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-search me-1"></i> Cari</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <script>
        (function () {
            'use strict';

            var lapis = document.getElementById('saringLapis');
            var wadah = document.getElementById('saringWadah');

            if (! lapis || ! wadah) {
                return;
            }

            var asal = lapis.parentNode;
            var sesudah = lapis.nextElementSibling;
            var sempit = window.matchMedia('(max-width: 991.98px)');

            // satu set field yang sama dipindah antara baris filter dan popus —
            // dua salinan akan mendobelkan nama input dan mengacaukan nilai terkirim
            function tata() {
                if (sempit.matches) {
                    wadah.appendChild(lapis);
                } else {
                    asal.insertBefore(lapis, sesudah);
                }
            }

            tata();
            sempit.addEventListener('change', tata);
        }());
        </script>
    </div>

    <div class="wa-kartu" data-saring-wadah>
        <?php if ($baris === []) : ?>
            <div class="wa-kosong">
                <i class="fal fa-circle-check text-success"></i>
                <?php if ($s['tab'] === 'belum') : ?>Tidak ada reminder menunggu. Buat lewat tombol kanan atas.<?php else : ?>Kosong di tab ini.<?php endif; ?>
            </div>
        <?php elseif ($s['tab'] === 'tak_selesai') : ?>
            <?php if ($rekap !== []) : ?>
                <div class="alert alert-light border d-flex flex-wrap align-items-center gap-2 py-2 small mb-2">
                    <span class="fw-bold"><i class="fal fa-chart-simple me-1"></i>Paling sering lepas tangan:</span>
                    <?php foreach ($rekap as $r) : ?>
                        <span class="pil-orang"><i class="fal fa-user me-1"></i><?= esc($r['nama'] ?: '(akun dihapus)') ?> &middot; <?= (int) $r['n'] ?>x</span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="table-responsive wa-gulir">
                <table class="table table-hover align-middle wa-daftar tabel-rapi tabel-jejak">
                    <colgroup>
                        <col class="k-reminder"><col class="k-nomor"><col class="k-tindak">
                        <col class="k-role"><col class="k-orang"><col class="k-waktu"><col class="k-aksi">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Reminder</th>
                            <th>Nomor</th>
                            <th>Tindak lanjut</th>
                            <th>Role</th>
                            <th>Yang Lepas</th>
                            <th>Waktu</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baris as $w) : ?>
                            <?php
                            $status   = (string) $w['status'];
                            $alasan   = trim((string) ($w['alasan'] ?: $w['catatan_lepas']));
                            $siapa    = trim((string) $w['nama_lepas']);
                            $levelLepas = (string) $w['level_lepas'];
                            $otomatis = (string) $w['jenis'] === 'operan_otomatis';
                            ?>
                            <tr>
                                <td>
                                    <a class="wa-nama" href="<?= esc(site_url('admin/tugas/' . (int) $w['tugas_id'])) ?>"><?= esc($w['judul']) ?></a>
                                    <div class="wa-sub">
                                        <span>#<?= (int) $w['tugas_id'] ?></span>
                                        <?php if ($w['nama_pelanggan']) : ?><span><i class="fal fa-user"></i> <?= esc($w['nama_pelanggan']) ?></span><?php endif; ?>
                                        <?php if ($w['nama_juragan']) : ?><span><i class="fal fa-store"></i> <?= esc($w['nama_juragan']) ?></span><?php endif; ?>
                                        <span class="pil-status s-<?= esc($status) ?>"><?= esc(\App\Models\TugasModel::STATUS[$status] ?? $status) ?></span>
                                    </div>
                                </td>
                                <td><div class="wa-nomor"><?= esc($w['nomor_ke'] ?: '-') ?></div></td>
                                <td><span class="pil-tindak"><?= esc($tindak[$w['tindak_lanjut']] ?? $w['tindak_lanjut']) ?></span></td>
                                <td><?= $siapa === '' ? '<span class="text-muted">-</span>' : '<span class="pil-role r-' . esc($levelLepas) . '">' . esc($role[$levelLepas] ?? ucfirst($levelLepas)) . '</span>' ?></td>
                                <td>
                                    <?= $siapa === '' ? '<span class="text-muted">(akun dihapus)</span>' : '<span class="pil-orang"><i class="fal fa-user"></i> ' . esc($siapa) . '</span>' ?>
                                    <?php if ($otomatis) : ?>
                                        <div class="wa-sub">dioper otomatis, shift habis</div>
                                    <?php endif; ?>
                                </td>
                                <td class="wa-waktu"><?= esc(date('d M Y H:i', (int) $w['dilepas_at'])) ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal"
                                        data-bs-target="#mtWaAlasan" role="button" data-alasan="<?= esc($alasan, 'attr') ?>"
                                        data-siapa="<?= esc($siapa === '' ? '(akun dihapus)' : $siapa, 'attr') ?>"
                                        data-judul="<?= esc($w['judul'], 'attr') ?>"
                                        data-waktu="<?= esc(date('d M Y H:i', (int) $w['dilepas_at']), 'attr') ?>"
                                        data-taut="<?= esc(site_url('admin/tugas/' . (int) $w['tugas_id']), 'attr') ?>">Alasan</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <div class="table-responsive wa-gulir">                <table class="table table-hover align-middle wa-daftar tabel-rapi">
                    <thead>
                        <tr>
                            <th>Reminder</th>
                            <th>Nomor</th>
                            <th>Tindak lanjut</th>
                            <th>Tenggat</th>
                            <th>Penanggung jawab</th>
                            <th class="text-center">Aksi</th>
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
                                        <div class="wa-catatan"><?= nl2br(esc(mb_substr((string) $w['deskripsi'], 0, 160))) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="wa-nomor"><?= esc($w['nomor_ke'] ?: '-') ?></div>
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
                                <td class="text-center">
                                    <div class="wa-aksi">
                                        <?php if ($status === 'queued') : ?>
                                            <form method="post" action="<?= esc(site_url('admin/tugas/whatsapp/ambil/' . (int) $w['tugas_id'])) ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">Ambil Tugas</button>
                                            </form>
                                        <?php elseif ($punya && $buka) : ?>
                                            <button type="button" class="btn btn-sm btn-success rounded-pill" data-bs-toggle="modal"
                                                data-bs-target="#mtWaSelesai" data-tugas="<?= (int) $w['tugas_id'] ?>"
                                                data-judul="<?= esc($w['judul'], 'attr') ?>" role="button">Selesai</button>
                                            <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-toggle="modal"
                                                data-bs-target="#mtWaLepas" data-tugas="<?= (int) $w['tugas_id'] ?>"
                                                data-judul="<?= esc($w['judul'], 'attr') ?>" role="button">Lepas</button>
                                        <?php else : ?>
                                            <a class="btn btn-sm btn-link text-decoration-none"
                                                href="<?= esc(site_url('admin/tugas/' . (int) $w['tugas_id'])) ?>">Riwayat</a>
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
                            <option value="0">Belum ditentukan (Bisa dilewati)</option>
                        </select>
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

<!-- modal aksi baris: satu pasang dipakai bersama semua baris, kolom isinya
     diisi dari tombol yang diklik (lihat bagian js) -->
<div class="modal fade" id="mtWaSelesai" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formWaSelesai" method="post" action="" data-base="<?= esc(site_url('admin/tugas/whatsapp/selesai/')) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Selesaikan reminder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="wa-modal-judul small text-muted mb-3"></p>
                    <label class="form-label small fw-bold" for="wa-selesai-catatan">Hasil</label>
                    <textarea class="form-control form-control-sm" id="wa-selesai-catatan" name="catatan" rows="3"
                        maxlength="500" placeholder="apa yang sudah dilakukan"></textarea>
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

<div class="modal fade" id="mtWaLepas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formWaLepas" method="post" action="" data-base="<?= esc(site_url('admin/tugas/whatsapp/lepas/')) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Lepas reminder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="wa-modal-judul small text-muted mb-3"></p>
                    <label class="form-label small fw-bold" for="wa-lepas-alasan">Alasan <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="wa-lepas-alasan" name="alasan" required
                        maxlength="255" placeholder="shift habis / operan ke CS lain">
                    <label class="form-label small fw-bold mt-2" for="wa-lepas-catatan">Catatan penerus</label>
                    <textarea class="form-control form-control-sm" id="wa-lepas-catatan" name="catatan" rows="3"
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

<!-- satu modal alasan untuk seluruh baris tabel jejak; isinya diambil dari
     tombol yang diklik (lihat bagian js) -->
<div class="modal fade" id="mtWaAlasan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Alasan lepas tangan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="wa-alasan-judul small text-muted mb-2"></p>
                <p class="wa-alasan-isi mb-2" style="white-space: pre-line"></p>
                <p class="small text-muted mb-0">
                    <i class="fal fa-user me-1"></i><span class="wa-alasan-siapa"></span>
                    &middot; <i class="fal fa-clock me-1"></i><span class="wa-alasan-waktu"></span>
                </p>
            </div>
            <div class="modal-footer">
                <a class="wa-alasan-taut btn btn-sm btn-link text-decoration-none" href="#">Lihat riwayat tugas</a>
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

    var nomorToko = <?= json_encode($peta_nomor, JSON_UNESCAPED_UNICODE) ?>;
    var pilihToko = document.getElementById('wa-toko');
    var pilihNomor = document.getElementById('wa-nomor-dari');

    function isiNomor() {
        var daftar = nomorToko[pilihToko.value] || [];
        pilihNomor.innerHTML = '';

        var bebas = document.createElement('option');
        bebas.value = '0';
        // tanpa nomor terdaftar opsi inilah jawabannya, jadi tulisannya jujur
        // boleh dilewati; begitu ada pilihan, ia turun jadi penuntun supaya CS
        // memilih dengan sadar, bukan lolos tanpa sengaja
        bebas.textContent = daftar.length === 0 ? 'Belum ditentukan (Bisa dilewati)' : 'Pilih Nomor';
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

    // satu pasang modal untuk semua baris: tujuan kirim dan judul reminder
    // diambil dari tombol yang diklik, jadi tidak ada puluhan modal tersembunyi
    ['mtWaSelesai', 'mtWaLepas'].forEach(function (id) {
        var modal = document.getElementById(id);

        if (! modal) {
            return;
        }

        modal.addEventListener('show.bs.modal', function (event) {
            var pemicu = event.relatedTarget;

            if (! pemicu) {
                return;
            }

            var form = modal.querySelector('form');
            form.action = form.dataset.base + pemicu.dataset.tugas;
            modal.querySelector('.wa-modal-judul').textContent = pemicu.dataset.judul || '';
            form.reset();
        });
    });

    // tombol "Alasan" di tabel jejak: isi modal diambil dari atribut data
    // tombol yang diklik, dan textContent dipakai supaya teks alasan mentah
    // tidak pernah dianggap markup
    var modalAlasan = document.getElementById('mtWaAlasan');

    if (modalAlasan) {
        modalAlasan.addEventListener('show.bs.modal', function (event) {
            var pemicu = event.relatedTarget;

            if (! pemicu) {
                return;
            }

            var d = pemicu.dataset;
            modalAlasan.querySelector('.wa-alasan-judul').textContent = d.judul || '';
            modalAlasan.querySelector('.wa-alasan-isi').textContent = d.alasan || 'Tanpa alasan tercatat.';
            modalAlasan.querySelector('.wa-alasan-siapa').textContent = d.siapa || '-';
            modalAlasan.querySelector('.wa-alasan-waktu').textContent = d.waktu || '';
            modalAlasan.querySelector('.wa-alasan-taut').href = d.taut || '#';
        });
    }
});
</script>
<?= $this->endSection() ?>
