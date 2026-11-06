<?php

/**
 * Daftar Cek Email: catatan hasil melihat kotak masuk, per toko.
 *
 * Semua variabel di bawah disuntik Admin\Email::index() lewat view() (extract),
 * jadi analyzer tidak bisa menelusurinya dari file ini. Blok @var di bawah hanya
 * memberi tahunya ke IntelliJ/Intelephense, bukan kode yang dijalankan.
 *
 * @var string $title      judul halaman
 * @var array<string, mixed> $s   saringan yang sudah dibersihkan controller
 * @var list<array<string, mixed>> $baris
 * @var int    $total      jumlah baris sesuai saringan
 * @var int    $page       halaman aktif (berbasis satu)
 * @var int    $batas      baris per halaman
 * @var array<string, string>    $tab   kunci => label
 * @var array<string, int>       $angka kunci => jumlah baris
 * @var string $bagian bagian yang dibuka: 'catatan' atau 'email'
 * @var array<int, string>       $toko
 * @var array<int, string>       $email  alamat yang boleh dipakai akun ini; penanda
 *                                      siapa yang boleh menandai catatan selesai
 * @var list<array<string, mixed>> $daftar baris crm_email (baris tabel pertama)
 * @var array<int, list<array>>  $pegang id_email => pemegang; dipakai juga di tab
 *                                      Catatan untuk menampilkan penggarapnya
 * @var array<int, string>       $staf   id => nama CS aktif
 * @var array<int, array>        $hitung id_email => terbuka/selesai (0 = tanpa alamat)
 * @var bool                     $kelola akun ini Admin/Superadmin?
 * @var int    $milik      id akun yang sedang login
 * @var string $kabar      pesan sukses flash (string kosong kalau tidak ada)
 * @var string $galat      pesan gagal flash (string kosong kalau tidak ada)
 */

$level    = (string) session()->get('level');
$hari_ini = date('Y-m-d');

// Catatan boleh dibuat dari dua sisi: Admin/Superadmin menugaskan ke alamat mana
// pun, CS membuat tugas untuk dirinya sendiri — $email sudah dipersempit controller
// ke alamat yang dipegang CS, jadi selama keduanya tidak kosong tombolnya hidup.
$bolehTugas = $toko !== [] && $email !== [];

// Gagal menyimpan: popup dibuka lagi supaya isian tidak hilang. old('email_id')
// hanya dikirim form penugasan, jadi tidak tertukar dengan form tambah email
// yang kolom catatannya punya nama yang sama.
$tugasBuka = $bolehTugas && $galat !== '' && old('email_id') !== null;

$tanya = static function (array $ubah): string {
    $q = array_merge($_GET, $ubah);

    if (! array_key_exists('halaman', $ubah)) {
        unset($q['halaman']);
    }

    // 'saya' (saringan "hanya punyaku") sudah tidak dipakai layar ini; tautan lama
    // yang masih membawa parameternya dibersihkan supaya tidak ikut terbawa terus
    unset($q['saya']);

    foreach (['tab', 'toko', 'email', 'cari', 'batas', 'halaman', 'bagian', 'awal', 'akhir'] as $k) {
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

    /* kepala: jarak atas tidak ditata di sini — style.css sudah mematok
       body.has-app-sidebar > .container-xxl { padding-top: 1.75rem } (1.25rem di
       layar kecil) dan menormalkan h1.h3.mt-5, jadi halaman ini otomatis sama
       tingginya dengan Produk & Stok */
    .em-head { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem 1rem; margin-bottom: 1.1rem; }
    /* bobotnya disamakan dengan aturan global h1.h3.mt-5 (margin-bottom 1.35rem
       untuk blok setelah judul) — keterangan halaman harus menempel ke judulnya */
    body.has-app-sidebar .em-head > div > h1.h3.mt-5 { margin-bottom: 0 !important; }
    .em-head h1 { margin-bottom: 0 !important; }
    .em-head p { margin: .4rem 0 0; font-size: .9rem; color: #64748b; max-width: 64ch; }
    .em-head-aksi { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .em-head-aksi .btn { display: inline-flex; align-items: center; gap: .35rem; }

    /* semua chip tab dipaksa satu baris lurus: tidak pernah melipat, digulir
       halus kalau layar lebih sempit daripada isinya */
    .em-tab { display: flex; align-items: center; flex-wrap: nowrap; gap: .45rem; margin-bottom: 1rem;
        padding-bottom: .15rem; overflow-x: auto; overscroll-behavior-x: contain; scrollbar-width: none; }
    .em-tab::-webkit-scrollbar { display: none; }
    .em-tab a {
        flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
        min-height: 2.05rem; padding: .3rem .9rem; border-radius: 999px; font-size: .8rem; font-weight: 600;
        line-height: 1.25; white-space: nowrap;
        background: #f4f6f9; color: #475569; text-decoration: none; border: 1px solid #e2e8f0;
    }
    .em-tab a:hover { background: #eaeef4; color: #0f172a; }
    .em-tab a.aktif { background: #101828; border-color: #101828; color: #fff; }
    .em-tab a.aktif:hover { background: #101828; color: #fff; }
    .em-tab a span { display: inline-flex; align-items: center; justify-content: center; min-width: 1.3rem;
        padding: 0 .34rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: rgba(15, 23, 42, .08); }
    .em-tab a.aktif span { background: rgba(255, 255, 255, .2); }
    /* tepi pudar sebagai isyarat masih ada chip di balik guliran; kelasnya
       dipasang js hanya kalau barisnya benar-benar bisa digulir */
    .em-tab.kanan { -webkit-mask-image: linear-gradient(90deg, #000 calc(100% - 2.2rem), transparent); mask-image: linear-gradient(90deg, #000 calc(100% - 2.2rem), transparent); }
    .em-tab.kiri { -webkit-mask-image: linear-gradient(90deg, transparent, #000 2.2rem); mask-image: linear-gradient(90deg, transparent, #000 2.2rem); }
    .em-tab.kiri.kanan { -webkit-mask-image: linear-gradient(90deg, transparent, #000 2.2rem, #000 calc(100% - 2.2rem), transparent);
        mask-image: linear-gradient(90deg, transparent, #000 2.2rem, #000 calc(100% - 2.2rem), transparent); }

    .em-saring {
        display: flex; flex-wrap: nowrap; align-items: flex-end;
        gap: .55rem .6rem; margin-bottom: 1rem;
        padding: .9rem 1rem; background: #fff;
        border: 1px solid #e2e8f0; border-radius: 1rem;
    }
    .em-saring .lapangan { display: flex; flex-direction: column; gap: .22rem; flex: 1 1 0; min-width: 0; }
    .em-saring .lapangan-cari { flex: 2 1 0; }
    .em-saring .lapangan-sempit { flex: 1 1 0; min-width: 0; }
    .em-saring .lapangan-aksi { flex: 0 0 auto; }
    .em-saring label { font-size: .7rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .em-saring .form-control, .em-saring .form-select { width: 100%; max-width: none; }
    .em-saring-pemisah { display: none; }
    .em-saring-grup { display: contents; }

    .em-kartu { background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12); overflow: hidden; }
    .table.em-daftar { margin: 0; }
    .em-daftar th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; background: #f9fafb; }
    .em-daftar td { vertical-align: middle; font-size: .86rem; padding-block: .72rem; }
    .em-daftar thead th:first-child { width: 46%; }
    .em-catatan { color: #0f172a; font-size: .88rem; line-height: 1.45; }
    .em-sub { font-size: .76rem; color: #64748b; display: flex; flex-wrap: wrap; gap: .2rem .6rem; margin-top: .22rem; }
    .em-kosong { padding: 2.8rem 1rem; text-align: center; color: #94a3b8; font-size: .9rem; }
    .em-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }

    .pil-status { display: inline-block; padding: .18rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
    .s-queued { background: #eef1f6; color: #475569; }
    .s-assigned { background: #e6f4fb; color: #0369a1; }
    .s-in_progress { background: #fef3e2; color: #b45309; }
    .s-waiting { background: #f3e8ff; color: #6b21a8; }
    .s-completed { background: #e7f6ec; color: #14663a; }
    .s-cancelled { background: #fdf1f0; color: #b81c16; }
    .pil-buat { background: #f4f6f9; color: #475569; }

    .em-teenggat { font-size: .78rem; white-space: nowrap; }
    .lewat { color: #b81c16; font-weight: 700; }
    .em-aksi { display: inline-flex; flex-wrap: nowrap; gap: .3rem; align-items: center; justify-content: flex-end; }
    .em-form-lepas { margin-top: .4rem; }
    .em-pager { padding: .8rem 1rem; background: #fff; border-top: 1px solid #eaecf0; }
    .em-tenggat-label { display: none; }

    .em-email-kartu { margin-bottom: 1rem; }
    /* lima kolom dengan bagian yang dipatok: selama lebarnya dibiarkan ikut isi,
       kolom Email melebar sendiri sementara kolom Aksi (lebar 1%) melipat dua
       ikon jadi atas-bawah. Layout dipatok ke lebar tabel, bukan ke konten */
    .em-email-kartu .em-daftar { table-layout: fixed; min-width: 720px; }
    .em-email-kartu .em-daftar :is(th, td) { overflow-wrap: anywhere; }
    .em-email-kartu .em-daftar thead th:nth-child(1) { width: 32%; }
    .em-email-kartu .em-daftar thead th:nth-child(2) { width: 18%; }
    .em-email-kartu .em-daftar thead th:nth-child(3) { width: 18%; }
    .em-email-kartu .em-daftar thead th:nth-child(4) { width: 19%; }
    .em-email-kartu .em-daftar thead th:nth-child(5) { width: 13%; }
    .em-email { font-weight: 700; color: #0f172a; }
    .em-mati .em-email { color: #94a3b8; text-decoration: line-through; }
    .em-pegang { display: flex; flex-wrap: wrap; gap: .3rem; align-items: center; }
    /* kelas ini menempel di <td>, jadi tidak boleh dijadikan flex container:
       sel kehilangan display: table-cell-nya, vertical-align: middle ikut hilang,
       dan isinya naik ke atas baris. Chip dibiarkan inline — mereka sudah bisa
       melipat sendiri begitu kolomnya menyempit, dan tetap rata tengah */
    .em-angka { white-space: normal; }
    .em-angka a { text-decoration: none; }
    .em-kosong-baris { padding: 1.4rem 1rem; color: #94a3b8; font-size: .86rem; }
    .em-pil-aktif { font-size: .84rem; color: #475569; margin: 0 0 .5rem; }
    .em-tab-layar { margin-bottom: .9rem; }
    .em-tab-layar a { padding: .3rem 1.05rem; font-size: .84rem; }
    .em-hint { padding: .85rem 1rem; margin-bottom: 1rem; background: #fff; border: 1px dashed #cbd5e1;
        border-radius: 1.15rem; color: #64748b; font-size: .86rem; }
    .pil-orang { display: inline-flex; align-items: center; gap: .3rem; padding: .16rem .3rem .16rem .55rem;
        border-radius: 999px; font-size: .72rem; font-weight: 700; background: #e6f4fb; color: #0369a1; }
    .pil-orang form { display: inline; }
    .pil-orang button { border: 0; background: none; padding: 0 .2rem; color: inherit; font-size: .85rem; line-height: 1; cursor: pointer; }

    /* popup penugasan: label kecil rata kiri supaya isian terbaca satu kolom */
    .em-modal .form-label { margin-bottom: .28rem; font-size: .72rem; font-weight: 700; color: #667085;
        text-transform: uppercase; letter-spacing: .04em; }

    @media (max-width: 575.98px) {
        .em-head-aksi { width: 100%; }
        .em-head-aksi .btn { flex: 1 1 0; justify-content: center; }

        /* tiga baris tombol sejajar: setiap tombol melebar sama rata */
        .em-tab-layar,
        .em-tab { flex-wrap: wrap; overflow: visible; }
        .em-tab-layar a,
        .em-tab a { flex: 1 1 0; justify-content: center; }
        .em-saring { flex-wrap: wrap; }
        .em-saring .lapangan,
        .em-saring .lapangan-cari,
        .em-saring .lapangan-sempit { flex: 1 1 calc(50% - .3rem); min-width: 0; max-width: none; }
        .em-saring .lapangan-aksi { flex: 1 1 100%; }
        .em-daftar td { font-size: .82rem; }
        .em-aksi { justify-content: center; }

        /* ---- mobile card layout untuk tab Catatan ---- */
        .em-kartu:not(.em-email-kartu) .table-responsive { overflow: visible; }
        .em-kartu:not(.em-email-kartu) .em-daftar { display: block; }
        .em-kartu:not(.em-email-kartu) .em-daftar thead { display: none; }
        .em-kartu:not(.em-email-kartu) .em-daftar tbody { display: block; }
        .em-kartu:not(.em-email-kartu) .em-daftar tr {
            display: grid;
            grid-template-columns: 1fr auto;
            grid-template-rows: auto auto;
            gap: 0;
            padding: .85rem 1rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .em-kartu:not(.em-email-kartu) .em-daftar tr:last-child { border-bottom: none; }

        /* kolom Catatan: baris atas, kolom kiri */
        .em-kartu:not(.em-email-kartu) .em-daftar td:nth-child(1) {
            display: block;
            grid-column: 1 / 2;
            grid-row: 1;
            padding: 0 0 .5rem 0;
            border: none;
        }
        /* kolom Tenggat: baris bawah, kolom kiri */
        .em-kartu:not(.em-email-kartu) .em-daftar td:nth-child(2) {
            display: flex;
            align-items: center;
            gap: .3rem;
            grid-column: 1 / 2;
            grid-row: 2;
            padding: 0;
            border: none;
            font-size: .76rem;
        }
        .em-tenggat-label { display: inline; }
        /* kolom Penanggung jawab: baris atas, kolom kanan */
        .em-kartu:not(.em-email-kartu) .em-daftar td:nth-child(3) {
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            grid-column: 2 / 3;
            grid-row: 1;
            padding: 0 0 .5rem .75rem;
            border: none;
            max-width: 120px;
        }
        .em-kartu:not(.em-email-kartu) .em-daftar td:nth-child(3) .pil-orang {
            max-width: 110px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        /* kolom Aksi: baris bawah, kolom kanan */
        .em-kartu:not(.em-email-kartu) .em-daftar td:nth-child(4) {
            display: block;
            grid-column: 2 / 3;
            grid-row: 2;
            padding: 0 0 0 .75rem;
            border: none;
            text-align: right;
        }
        .em-kartu:not(.em-email-kartu) .em-daftar .em-aksi {
            flex-direction: column;
            align-items: flex-end;
            gap: .25rem;
        }
        /* form lepas: input dan tombol full-width di dalam collapse */
        .em-kartu:not(.em-email-kartu) .em-daftar .em-form-lepas input { width: 100%; }
        .em-kartu:not(.em-email-kartu) .em-daftar .em-form-lepas button {
            width: 100%;
            margin-top: .25rem;
        }
        .em-kartu:not(.em-email-kartu) .em-daftar td:nth-child(4) .collapse { margin-top: .5rem; }
    }

    /* ------------------------------------------------------------------ aksi */
    .em-taut { width: fit-content; margin-inline: auto; }
    .em-taut select { width: 100%; max-width: 210px; cursor: pointer; }
    /* dikirim = kontrol dikunci sementara supaya tidak ada klik ganda */
    .em-taktik { opacity: .55; pointer-events: none; }

    /* tombol aksi berupa ikon bulat kecil, bukan teks lagi */
    .em-ikon { display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem;
        padding: 0; border: 1px solid #e2e8f0; border-radius: 999px; background: #fff; color: #475569; cursor: pointer; }
    .em-ikon:hover { border-color: #cbd5e1; background: #f4f6f9; color: #101828; }
    .em-ikon:focus-visible { outline: 2px solid #8fa8ff; outline-offset: 2px; }
    /* sampah = hapus permanen, jadi warnanya dibedakan dari ubah */
    .em-ikon-bahaya { color: #b81c16; }
    .em-ikon-bahaya:hover { border-color: #fbd9d6; background: #fdf1f0; color: #b81c16; }

    /* laporan hasil tulis: popup kecil di kanan bawah, hilang sendiri */
    .em-toast-wadah { position: fixed; right: 1rem; bottom: 1rem; z-index: 1080; display: flex;
        flex-direction: column; align-items: flex-end; gap: .5rem; max-width: min(26rem, calc(100vw - 2rem)); }
    .em-toast { display: flex; align-items: flex-start; gap: .55rem; padding: .7rem .55rem .7rem .8rem;
        border: 1px solid #d7f0e1; border-radius: .9rem; background: #fff; color: #14663a; font-size: .84rem;
        line-height: 1.4; box-shadow: 0 12px 32px -12px rgba(15, 23, 42, .35);
        transition: opacity .3s ease, transform .3s ease; }
    .em-toast > i { margin-top: .12rem; font-size: 1rem; }
    .em-toast span { flex: 1; }
    .em-toast button { flex: 0 0 auto; border: 0; background: none; padding: 0 .25rem; color: inherit;
        font-size: 1.05rem; line-height: 1; cursor: pointer; opacity: .55; }
    .em-toast button:hover { opacity: 1; }
    .em-toast-galat { border-color: #fbd9d6; color: #b81c16; }
    .em-toast.kirim { opacity: 0; transform: translateY(.6rem); }

    @media (max-width: 575.98px) {
        .em-toast-wadah { right: .75rem; bottom: .75rem; left: .75rem; max-width: none; align-items: stretch; }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl em-halaman">

    <div class="em-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><i class="fal fa-envelope me-1 text-brand-500"></i> <?= esc($title) ?></h1>
            <p><?= $bagian === 'email'
                ? 'Satu baris = satu alamat email dan orang yang memegangnya. Siapa yang memegang alamatnya, dia yang dapat tugas cek email di alamat itu.'
                : 'Catatan ditugaskan ke salah satu alamat di tab Daftar Email — siapa yang memegang alamatnya, dia yang dapat tugasnya.' ?></p>
        </div>
        <?php if ($kelola || $email !== []) : ?>
            <div class="em-head-aksi">
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalTugas"
                    <?= $bolehTugas ? '' : 'disabled' ?> title="<?= $kelola ? 'Tulis catatan lalu tugaskan ke satu alamat email' : 'Tambah tugas cek email sendiri — masuk ke Belum Dikerjakan' ?>">
                    <i class="fal fa-plus"></i> <?= $kelola ? 'Penugasan' : 'Tambah Tugas' ?>
                </button>
                <?php if ($kelola) : ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEmailBaru"
                        data-id="" data-email="" data-label="" data-catatan="">
                        <i class="fal fa-at"></i> Tambah Email
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php /* laporan hasil tulis tidak lagi menempel di kepala: lihat .em-toast-wadah
             di akhir halaman — popup kanan bawah yang hilang sendiri */ ?>

    <div class="em-tab em-tab-layar">
        <a class="<?= $bagian === 'catatan' ? 'aktif' : '' ?>" href="<?= esc($tanya(['bagian' => ''])) ?>">
            <i class="fal fa-inbox"></i> Catatan
        </a>
        <a class="<?= $bagian === 'email' ? 'aktif' : '' ?>" href="<?= esc($tanya(['bagian' => 'email'])) ?>">
            <i class="fal fa-at"></i> Daftar Email
        </a>
    </div>

    <?php if ($bagian === 'catatan' && $toko !== [] && $email === []) : ?>
        <p class="em-hint">
            <?= $kelola
                ? 'Belum ada email aktif di daftar. Tambahkan dulu lewat tombol "Tambah Email", baru catatan bisa ditugaskan ke satu alamat.'
                : 'Admin belum mendelegasikan alamat email apa pun kepadamu, jadi catatan belum bisa dibuat.' ?>
        </p>
    <?php endif; ?>

    <?php if ($bagian === 'catatan') : ?>
    <?php /* tidak ada saringan "hanya punyaku" di sini: catatan ditugaskan ke satu
       alamat email, jadi daftar yang tampil sudah milik orang itu — CS hanya melihat
       alamat yang dipegangnya, Admin/Superadmin memang melihat semuanya */ ?>
    <div class="em-tab">
        <?php foreach ($tab as $kode => $nama) : ?>
            <a class="<?= $s['tab'] === $kode ? 'aktif' : '' ?>" href="<?= esc($tanya(['tab' => $kode])) ?>">
                <?= esc($nama) ?>
                <?php if (($angka[$kode] ?? 0) > 0) : ?><span><?= (int) $angka[$kode] ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="em-saring" method="get" action="<?= esc(site_url('admin/tugas/email')) ?>">
        <input type="hidden" name="tab" value="<?= esc($s['tab']) ?>">
        <?php if ($s['email_get'] !== '') : ?>
            <input type="hidden" name="email" value="<?= esc($s['email_get'], 'attr') ?>">
        <?php endif; ?>

        <div class="lapangan lapangan-cari">
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

        <div class="lapangan lapangan-sempit">
            <label for="awal">Tenggat dari</label>
            <input type="date" class="form-control form-control-sm" id="awal" name="awal"
                value="<?= esc($s['awal']) ?>" max="<?= esc($s['akhir'] ?: '') ?>">
        </div>
        <div class="lapangan lapangan-sempit">
            <label for="akhir">Sampai</label>
            <input type="date" class="form-control form-control-sm" id="akhir" name="akhir"
                value="<?= esc($s['akhir']) ?>" min="<?= esc($s['awal'] ?: '') ?>">
        </div>

        <div class="lapangan lapangan-sempit">
            <label for="batas">Baris</label>
            <select class="form-select form-select-sm" id="batas" name="batas">
                <?php foreach ([10, 25, 50] as $n) : ?>
                    <option value="<?= $n ?>" <?= $batas === $n ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="lapangan lapangan-aksi">
            <label>&nbsp;</label>
            <div class="em-aksi">
                <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-search"></i> Cari</button>
                <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/tugas/email')) ?>">Ulangi</a>
            </div>
        </div>
    </form>

    <?php if ((int) $s['email_id'] > 0) : ?>
        <?php
        $pil_saring = $email[(int) $s['email_id']] ?? '';

        if ($pil_saring === '') {
            foreach ($daftar as $e) {
                if ((int) $e['id_email'] === (int) $s['email_id']) {
                    $pil_saring = (string) $e['email'];
                    break;
                }
            }
        }
        ?>
        <p class="em-pil-aktif">Menampilkan catatan untuk: <strong><?= esc($pil_saring) ?></strong>
            <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/tugas/email')) ?>">lihat semua</a>
        </p>
    <?php endif; ?>
    <div class="em-kartu">
        <?php if ($baris === []) : ?>
            <div class="em-kosong">
                <i class="fal fa-circle-check text-success"></i>
                <?= $s['tab'] === 'belum' ? 'Belum ada catatan cek email yang menunggu.' : 'Kosong di tab ini.' ?>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle em-daftar tabel-rapi">
                    <thead>
                        <tr>
                            <th>Catatan</th>
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

                            // tidak ada tombol ambil di layar ini: catatan sudah ditugaskan ke satu
                            // alamat, jadi pemegang alamatnya yang boleh menandai selesai — sama
                            // seperti penjaga catatanMilikSaya() di controller
                            $id_e_t  = (int) ($w['email_id'] ?? 0);
                            $pemegang = $id_e_t > 0 ? ($pegang[$id_e_t] ?? []) : [];
                            $bolehAksi = $punya || $kelola || $id_e_t < 1 || isset($email[$id_e_t]);
                            ?>
                            <tr>
                                <td>
                                    <div class="em-catatan"><?= nl2br(esc($w['deskripsi'] ?: $w['judul'])) ?></div>
                                    <div class="em-sub">
                                        <span>#<?= $id ?></span>
                                        <?php if (! empty($w['email_alamat'])) : ?>
                                            <span><i class="fal fa-inbox"></i> <?= esc($w['email_label'] ?: $w['email_alamat']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($w['nama_juragan']) : ?><span><i class="fal fa-store"></i> <?= esc($w['nama_juragan']) ?></span><?php endif; ?>
                                        <span class="pil-status s-<?= esc($st) ?>"><?= esc(\App\Models\TugasModel::STATUS[$st] ?? $st) ?></span>
                                        <span class="pil-orang pil-buat">dibuat: <?= esc($w['nama_dibuat'] ?: '?') ?></span>
                                    </div>
                                </td>
                                <td class="em-teenggat <?= $terlambat ? 'lewat' : '' ?>">
                                    <span class="em-tenggat-label">Tenggat:</span>
                                    <?= $tenggat === '' ? '-' : esc(tanggal_rincian($tenggat)) ?>
                                    <?php if ($terlambat) : ?><i class="fal fa-exclamation-triangle"></i><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($w['nama_dikerjakan']) : ?>
                                        <span class="pil-orang"><i class="fal fa-user-check me-1"></i><?= esc($w['nama_dikerjakan']) ?></span>
                                        <?php if ($w['nama_selesai']) : ?>
                                            <div class="em-sub"><span>selesai: <?= esc($w['nama_selesai']) ?></span></div>
                                        <?php endif; ?>
                                    <?php elseif ($pemegang !== []) : ?>
                                        <?php /* belum ada yang menandai selesai: yang memegang alamatnya sudah jadi penggarapnya */ ?>
                                        <div class="em-pegang">
                                            <?php foreach ($pemegang as $p) : ?>
                                                <span class="pil-orang pil-buat"><i class="fal fa-user"></i> <?= esc($p['nama_staf']) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                    <?php else : ?>
                                        <span class="text-muted"><?= $id_e_t > 0 ? 'belum ada yang memegang alamatnya' : 'belum ada penggarapnya' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="em-aksi">
                                        <?php if ($buka && $bolehAksi) : ?>
                                            <form method="post" action="<?= esc(site_url('admin/tugas/email/selesai/' . $id)) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="catatan" value="sudah dibaca">
                                                <button type="submit" class="btn btn-sm btn-success rounded-pill">Selesai</button>
                                            </form>
                                            <?php if ($punya) : ?>
                                                <a class="btn btn-sm btn-link text-decoration-none" data-bs-toggle="collapse"
                                                    href="#lepas-<?= $id ?>" role="button">Lepas</a>
                                            <?php endif; ?>
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

    <?php else : ?>
    <div class="em-kartu em-email-kartu">
        <div class="table-responsive">
            <table class="table table-hover align-middle em-daftar tabel-rapi">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Dipegang oleh</th>
                        <th>Catatan</th>
                        <?php if ($kelola) : ?>
                            <th>Delegasikan</th>
                            <th class="text-center">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar as $e) : ?>
                        <?php
                        $id_e    = (int) $e['id_email'];
                        $isi_e   = $pegang[$id_e] ?? [];
                        $sudah_e = array_map(static fn (array $p): int => (int) $p['user_id'], $isi_e);
                        $pilih_e = $staf;

                        foreach ($sudah_e as $y) {
                            unset($pilih_e[$y]);
                        }

                        $terbuka = (int) ($hitung[$id_e]['terbuka'] ?? 0);
                        $selesai = (int) ($hitung[$id_e]['selesai'] ?? 0);
                        ?>
                        <tr class="<?= (int) $e['aktif'] === 1 ? '' : 'em-mati' ?>">
                            <td>
                                <div class="em-email"><?= esc($e['email']) ?></div>
                                <div class="em-sub">
                                    <?php if ($e['label'] !== '') : ?><span><i class="fal fa-tag"></i> <?= esc($e['label']) ?></span><?php endif; ?>
                                    <?php if ($e['catatan']) : ?><span><?= esc($e['catatan']) ?></span><?php endif; ?>
                                    <?php if ((int) $e['aktif'] === 0) : ?><span class="pil-status s-cancelled">dimatikan</span><?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($isi_e === []) : ?>
                                    <span class="text-muted small">belum ada yang memegang</span>
                                <?php else : ?>
                                    <div class="em-pegang">
                                        <?php foreach ($isi_e as $p) : ?>
                                            <span class="pil-orang">
                                                <i class="fal fa-user"></i> <?= esc($p['nama_staf']) ?>
                                                <?php if ($kelola) : ?>
                                                    <?= form_open('admin/tugas/email/pegang-copot') ?>
                                                        <input type="hidden" name="id_email" value="<?= $id_e ?>">
                                                        <input type="hidden" name="user_id" value="<?= (int) $p['user_id'] ?>">
                                                        <button type="submit" title="lepas dari <?= esc($p['nama_staf']) ?>" aria-label="lepas">&times;</button>
                                                    <?= form_close() ?>
                                                <?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="em-angka">
                                <?php if ($terbuka === 0 && $selesai === 0) : ?>
                                    <span class="text-muted small">belum ada catatan</span>
                                <?php else : ?>
                                    <a class="pil-status s-queued" href="<?= esc($tanya(['email' => (string) $id_e, 'tab' => 'belum', 'bagian' => ''])) ?>"><?= $terbuka ?> menunggu</a>
                                    <a class="pil-status s-completed" href="<?= esc($tanya(['email' => (string) $id_e, 'tab' => 'sudah', 'bagian' => ''])) ?>"><?= $selesai ?> beres</a>
                                <?php endif; ?>
                            </td>
                            <?php if ($kelola) : ?>
                                <td>
                                    <?php if ($pilih_e !== [] && (int) $e['aktif'] === 1) : ?>
                                        <?php /* memilih nama langsung menyimpan tautan; tombol Tautkan dihapus */ ?>
                                        <?= form_open('admin/tugas/email/pegang', ['class' => 'em-taut']) ?>
                                            <input type="hidden" name="id_email" value="<?= $id_e ?>">
                                            <select class="form-select form-select-sm" name="user_id" data-taut
                                                title="Pilih CS-nya, tautan langsung tersimpan">
                                                <option value="">Tautkan CS…</option>
                                                <?php foreach ($pilih_e as $uid => $nama) : ?>
                                                    <option value="<?= (int) $uid ?>"><?= esc($nama) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?= form_close() ?>
                                    <?php else : ?>
                                        <span class="text-muted small"><?= (int) $e['aktif'] === 1 ? 'semua CS sudah tertaut' : 'email dimatikan' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="em-aksi">
                                        <button type="button" class="em-ikon" data-bs-toggle="modal"
                                            data-bs-target="#modalEmailBaru" data-id="<?= $id_e ?>"
                                            data-email="<?= esc($e['email'], 'attr') ?>"
                                            data-label="<?= esc($e['label'], 'attr') ?>"
                                            data-catatan="<?= esc((string) $e['catatan'], 'attr') ?>"
                                            title="Ubah email" aria-label="Ubah <?= esc($e['email'], 'attr') ?>">
                                            <i class="fal fa-edit"></i>
                                        </button>
                                        <?php /* sampah = hapus permanen, lewat popup konfirmasi; jumlah catatannya
                                                 ikut dibawa supaya pesan konfirmasi bisa menyebut angka */ ?>
                                        <button type="button" class="em-ikon em-ikon-bahaya" data-bs-toggle="modal"
                                            data-bs-target="#modalHapusEmail" data-id="<?= $id_e ?>"
                                            data-email="<?= esc($e['email'], 'attr') ?>"
                                            data-catatan="<?= $terbuka + $selesai ?>"
                                            title="Hapus email" aria-label="Hapus <?= esc($e['email'], 'attr') ?>">
                                            <i class="fal fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($hitung[0]['terbuka'] ?? 0) > 0 || ($hitung[0]['selesai'] ?? 0) > 0) : ?>
                        <tr>
                            <td>
                                <div class="em-email text-muted">catatan tanpa alamat</div>
                                <div class="em-sub"><span>catatan lama sebelum daftar email dipakai</span></div>
                            </td>
                            <td><span class="text-muted small">tidak ditugaskan</span></td>
                            <td class="em-angka">
                                <a class="pil-status s-queued" href="<?= esc($tanya(['email' => '0', 'tab' => 'belum', 'bagian' => ''])) ?>"><?= (int) ($hitung[0]['terbuka'] ?? 0) ?> menunggu</a>
                                <a class="pil-status s-completed" href="<?= esc($tanya(['email' => '0', 'tab' => 'sudah', 'bagian' => ''])) ?>"><?= (int) ($hitung[0]['selesai'] ?? 0) ?> beres</a>
                            </td>
                            <?php if ($kelola) : ?>
                                <td></td>
                                <td class="text-center"><span class="text-muted small">-</span></td>
                            <?php endif; ?>
                        </tr>
                    <?php endif; ?>
                    <?php if ($daftar === []) : ?>
                        <tr>
                            <td colspan="5" class="em-kosong-baris">
                                Belum ada email di daftar. <?= $kelola ? 'Tambahkan lewat tombol "Tambah Email" di kanan atas, lalu tautkan CS-nya.' : 'Admin belum mendelegasikan alamat apa pun.' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- popup penugasan: form yang dulu menempel di atas daftar dipindah ke sini,
     supaya halaman langsung dibuka dengan isinya, bukan dengan kolom isian.
     CS yang memegang minimal satu alamat memakai popup yang sama untuk
     menambah tugasnya sendiri; pilihan emailnya sudah dipersempit controller. -->
<?php if ($kelola || $email !== []) : ?>
<div class="modal fade" id="modalTugas" tabindex="-1" aria-hidden="true" data-buka="<?= $tugasBuka ? '1' : '0' ?>">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <?= form_open('admin/tugas/email/simpan', ['class' => 'em-modal']) ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fal fa-plus-circle me-1 text-brand-500"></i> <?= $kelola ? 'Penugasan Cek Email' : 'Tambah Tugas Cek Email' ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="tugas-email"><?= $kelola ? 'Tugaskan ke email' : 'Email yang mau dicek' ?> <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="tugas-email" name="email_id" required>
                        <option value="">Pilih email</option>
                        <?php foreach ($email as $id_email => $nama_e) : ?>
                            <option value="<?= (int) $id_email ?>" <?= (string) old('email_id') === (string) $id_email ? 'selected' : '' ?>>
                                <?= esc($nama_e) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label class="form-label mt-2" for="tugas-catatan">Catatan <span class="text-danger">*</span></label>
                    <textarea class="form-control form-control-sm" id="tugas-catatan" name="catatan" rows="3" required
                        maxlength="2000" placeholder="mis. belum ada balasan ukuran dari pelanggan 0812..., nota sudah dikirim kemarin"><?= esc((string) old('catatan')) ?></textarea>

                    <div class="row g-2 mt-1">
                        <div class="col-12 col-sm-7">
                            <label class="form-label" for="tugas-toko">Toko <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="tugas-toko" name="juragan_id" required>
                                <?php foreach ($toko as $id_toko => $nama_t) : ?>
                                    <option value="<?= (int) $id_toko ?>" <?= (string) old('juragan_id') === (string) $id_toko ? 'selected' : '' ?>><?= esc($nama_t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-5">
                            <label class="form-label" for="tugas-tenggat">Tenggat</label>
                            <input type="date" class="form-control form-control-sm" id="tugas-tenggat" name="tenggat"
                                value="<?= esc((string) old('tenggat')) ?>">
                        </div>
                    </div>

                    <div class="form-text small mt-2">
                        <?= $kelola
                            ? 'Catatan masuk ke antrean orang yang memegang alamat email itu, jadi pilih alamatnya lebih dulu.'
                            : 'Tugas baru masuk ke tab Belum Dikerjakan; hanya alamat email yang kamu pegang yang bisa dipilih.' ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fal fa-save me-1"></i> <?= $kelola ? 'Simpan Penugasan' : 'Tambah Tugas' ?></button>
                </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- satu popup untuk tambah dan ubah alamat: isian dan judulnya diambil dari
     tombol yang diklik (lihat bagian js), jadi tidak ada popup per baris -->
<?php if ($kelola) : ?>
<div class="modal fade" id="modalEmailBaru" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <?= form_open('admin/tugas/email/daftar') ?>
                <input type="hidden" name="id_email" id="em-modal-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="em-modal-judul">Tambah Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label small fw-bold" for="em-modal-email">Alamat email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control form-control-sm" id="em-modal-email" name="email" required
                        maxlength="120" placeholder="mis. jahit.toko@gmail.com">
                    <label class="form-label small fw-bold mt-2" for="em-modal-label">Label (opsional)</label>
                    <input type="text" class="form-control form-control-sm" id="em-modal-label" name="label"
                        maxlength="60" placeholder="mis. Email Penjahit">
                    <label class="form-label small fw-bold mt-2" for="em-modal-catatan">Catatan (opsional)</label>
                    <input type="text" class="form-control form-control-sm" id="em-modal-catatan" name="catatan"
                        maxlength="255" placeholder="dipakai untuk apa">
                    <div class="form-text small mt-2">
                        Alamat yang dihapus tidak kembali lagi. Catatan lama yang menunjuknya tetap ada, hanya tanpa alamat.
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
<?php endif; ?>

<!-- konfirmasi hapus: penghapusan permanen tidak boleh selesai dalam satu klik,
     jadi alamat, jumlah catatan terdampak, dan tombol hapus duduk di satu popup -->
<?php if ($kelola) : ?>
<div class="modal fade" id="modalHapusEmail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <?= form_open('admin/tugas/email/hapus') ?>
                <input type="hidden" name="id_email" id="em-hapus-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fal fa-trash me-1 text-danger"></i> Hapus alamat email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Hapus <strong id="em-hapus-email">&mdash;</strong> dari daftar? Alamatnya tidak kembali lagi.</p>
                    <div class="form-text small" id="em-hapus-catatan"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fal fa-trash me-1"></i> Hapus alamat</button>
                </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- laporan hasil tulis: popup di kanan bawah yang menutup dirinya sendiri -->
<?php if ($kabar !== '' || $galat !== '') : ?>
<div class="em-toast-wadah" aria-live="polite" aria-atomic="true">
    <?php if ($kabar !== '') : ?>
        <div class="em-toast" data-hapus="4200">
            <i class="fal fa-check-circle"></i><span><?= esc($kabar) ?></span>
            <button type="button" data-toast-tutup aria-label="Tutup pesan">&times;</button>
        </div>
    <?php endif; ?>
    <?php if ($galat !== '') : ?>
        <div class="em-toast em-toast-galat" data-hapus="6500">
            <i class="fal fa-exclamation-circle"></i><span><?= esc($galat) ?></span>
            <button type="button" data-toast-tutup aria-label="Tutup pesan">&times;</button>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // sinkronisasi min/max antar field tanggal filter agar rentang selalu valid
    var inpAwal  = document.getElementById('awal');
    var inpAkhir = document.getElementById('akhir');

    if (inpAwal && inpAkhir) {
        inpAwal.addEventListener('change', function () {
            inpAkhir.min = inpAwal.value || '';
        });
        inpAkhir.addEventListener('change', function () {
            inpAwal.max = inpAkhir.value || '';
        });
    }

    // chip tab tidak pernah melipat; kalau layar sempit barisnya digulir, dan
    // tepi yang masih punya sisa digelapkan supaya chip tersembunyi terbaca
    document.querySelectorAll('.em-tab').forEach(function (baris) {
        var tanda = function () {
            var sisa  = baris.scrollWidth - baris.clientWidth;
            var gulir = baris.scrollLeft;

            baris.classList.toggle('kanan', sisa > 2 && gulir < sisa - 2);
            baris.classList.toggle('kiri', gulir > 2);
        };

        baris.addEventListener('scroll', tanda, { passive: true });
        window.addEventListener('resize', tanda);
        tanda();
    });

    // gagal menyimpan penugasan: popup dibuka lagi supaya isian lama tidak hilang
    var modalTugas = document.getElementById('modalTugas');

    if (modalTugas && modalTugas.dataset.buka === '1' && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(modalTugas).show();
    }

    // satu aksi satu pengiriman: kontrol dikunci begitu form pertama jalan, jadi
    // klik ganda tidak menghasilkan delegasi atau status rangkap
    var kirim = function (form) {
        if (! form || form.dataset.kirim) {
            return false;
        }

        form.dataset.kirim = '1';
        form.classList.add('em-taktik');
        form.submit();

        return true;
    };

    // delegasi: memilih nama langsung menyimpan, tanpa tombol Tautkan
    document.querySelectorAll('select[data-taut]').forEach(function (pilih) {
        pilih.addEventListener('change', function () {
            if (pilih.value === '') {
                return;
            }

            if (! kirim(pilih.form)) {
                pilih.selectedIndex = 0;
            }
        });
    });

    // status email tidak lagi ditata tombol hidup/mati: ikon sampah membuka popup
    // konfirmasi, dan popup itu yang diisi dari data tombol yang diklik
    var modalHapus = document.getElementById('modalHapusEmail');

    if (modalHapus) {
        modalHapus.addEventListener('show.bs.modal', function (event) {
            var data = event.relatedTarget ? event.relatedTarget.dataset : {};
            var n    = parseInt(data.catatan || '0', 10);

            modalHapus.querySelector('#em-hapus-id').value    = data.id || '';
            modalHapus.querySelector('#em-hapus-email').textContent = data.email || 'alamat ini';
            modalHapus.querySelector('#em-hapus-catatan').textContent = n > 0
                ? 'Ada ' + n + ' catatan cek email yang menunjuk alamat ini. Catatannya tetap ada, hanya jadi tanpa alamat.'
                : 'Belum ada catatan yang menunjuk alamat ini.';
        });
    }

    // toast kanan bawah: muncul sendiri, bisa ditutup sekarang, hilang sendiri
    document.querySelectorAll('.em-toast').forEach(function (pesan) {
        var tutup = function () {
            pesan.classList.add('kirim');

            window.setTimeout(function () {
                pesan.remove();
            }, 300);
        };

        pesan.querySelector('[data-toast-tutup]').addEventListener('click', tutup);
        window.setTimeout(tutup, parseInt(pesan.dataset.hapus, 10) || 4200);
    });

    var modal = document.getElementById('modalEmailBaru');

    if (! modal) {
        return;
    }

    modal.addEventListener('show.bs.modal', function (event) {
        var pemicu = event.relatedTarget;

        if (! pemicu) {
            return;
        }

        var data = pemicu.dataset;

        document.getElementById('em-modal-id').value = data.id || '';
        document.getElementById('em-modal-email').value = data.email || '';
        document.getElementById('em-modal-label').value = data.label || '';
        document.getElementById('em-modal-catatan').value = data.catatan || '';
        document.getElementById('em-modal-judul').textContent = (data.id ? 'Ubah email' : 'Tambah Email');
    });
});
</script>
<?= $this->endSection() ?>
