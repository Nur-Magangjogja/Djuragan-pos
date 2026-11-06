<?php

/**
 * Daftar sesi checklist (toko harian + pemeriksaan email).
 *
 * Semua variabel di bawah disuntik Admin\Checklist::index() lewat view() (extract),
 * jadi analyzer tidak bisa menelusurinya dari file ini. Blok @var di bawah hanya
 * memberi tahunya ke Intelephense, bukan kode yang dijalankan.
 *
 * @var string $title      judul halaman
 * @var array  $baris      hasil ChecklistModel::catatan() — satu baris satu catatan
 * @var array  $s          saringan yang sudah dibersihkan controller
 * @var array  $toko       id_toko => baris toko yang boleh dibuka akun ini
 * @var bool   $kelola     akun ini admin/superadmin
 * @var bool   $kelolaToko tab "Kelola Toko" boleh dipakai (admin/superadmin, tokonya ada)
 * @var bool   $tabKelola  tab "Kelola Toko" yang sedang dibuka
 * @var array  $pegawai    id akun => "nama (level)" — Admin/CS aktif yang bisa memegang toko
 * @var array  $pemegang   id_toko => daftar akun yang boleh membuka lembar toko itu
 * @var array  $namajuragan id_juragan => nama, untuk label toko di daftar
 * @var array  $platform   daftar marketplace untuk popus toko
 * @var array  $platformInfo rincian platform asal_order untuk popus toko (id, label, aktif, toko)
 * @var array  $lembarToko   id_toko => jumlah lembar checklist yang pernah dibuka, untuk konfirmasi hapus
 * @var string $kabar      pesan sukses flash (string kosong kalau tidak ada)
 * @var string $galat      pesan gagal flash (string kosong kalau tidak ada)
 * @var int    $total      jumlah sesi sesuai saringan
 * @var int    $page       halaman aktif (berbasis satu)
 * @var int    $batas      baris per halaman
 */

$level = (string) session()->get('level');

$tanya = static function (array $ubah): string {
    $q = array_merge($_GET, $ubah);

    if (! array_key_exists('halaman', $ubah)) {
        unset($q['halaman']);
    }

    foreach (['kerja', 'tab', 'jenis', 'status', 'tanggal', 'toko', 'batas', 'halaman'] as $k) {
        if (isset($q[$k]) && $q[$k] === '') {
            unset($q[$k]);
        }
    }

    return site_url('admin/checklist') . '?' . http_build_query($q);
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .ckl-halaman { max-width: 1180px; }
    /* tombol di kanan kepala halaman digeser kiri 72px: lonceng notifikasi desktop
       melayang di pojok kanan atas dan menutupinya begitu jendela tidak terlalu lebar */
    .ckl-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; padding-right: 4.5rem; }
    /* blok judul boleh memendek (keterangan turun satu baris) supaya tombol tetap
       satu baris di kanan, tidak jatuh ke baris sendiri */
    .ckl-head > .me-auto { flex: 1 1 0; min-width: 0; }
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

    .ckl-saring { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .55rem; padding: 1rem 1rem 1.6rem; border-bottom: 1px solid #eaecf0; }
    .ckl-saring .lapangan { display: flex; flex-direction: column; gap: .2rem; }
    .ckl-saring label { font-size: .7rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .ckl-saring .form-control, .ckl-saring .form-select { max-width: 180px; }
    /* tombol ikon saja selalu lebih pendek dari tombol bertulisan di sampingnya;
       stretch + tinggi minimum 2rem membuat keduanya setinggi sama */
    .ckl-saring-aksi .btn { min-height: 2rem; }
    .ckl-saring-aksi a.btn { align-items: center; justify-content: center; min-width: 2.4rem; }

    .table.ckl-daftar { margin: 0; }
    .ckl-daftar th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; background: #f9fafb; }
    .ckl-daftar td { vertical-align: middle; font-size: .86rem; }
    .ckl-daftar thead th:first-child { width: 34%; }
    .ckl-nama { font-weight: 700; color: #0f172a; text-decoration: none; display: block; }
    .ckl-nama:hover { color: #da251d; }
    .ckl-sub { font-size: .76rem; color: #64748b; display: flex; flex-wrap: wrap; gap: .2rem .6rem; margin-top: .1rem; }
    .ckl-kosong { padding: 2.6rem 1rem; text-align: center; color: #94a3b8; }
    .ckl-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }

    .pil-ckl { display: inline-block; padding: .18rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
    .c-berjalan { background: #fef3e2; color: #b45309; }
    .c-selesai { background: #e7f6ec; color: #14663a; }

    /* judul catatan yang sedang diselesaikan, ditampilkan di dalam popus supaya
       orang tidak perlu menutup daftar untuk ingat yang mana yang dipilih */
    .ckl-selesai-judul {
        font-size: .86rem; font-weight: 700; color: #0f172a;
        padding: .55rem .75rem; border-left: 3px solid #101828; background: #f6f8fb; border-radius: .4rem;
    }

    .ckl-pager { padding: .8rem 1rem; background: #fff; border-top: 1px solid #eaecf0; }

    /* dua keadaan kerja, bukan filter bertumpuk: garis bawah tipis seperti tab
       rumah, bukan kotak Bootstrap. Tombol "Buat catatan" duduk sejajar dengan
       tab di ujung kanan baris yang sama */
    .ckl-tabs { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .5rem; padding: .9rem 1rem .2rem; }
    .ckl-tab {
        padding: .3rem .85rem; border-radius: 999px; font-size: .78rem; font-weight: 700;
        color: #475569; text-decoration: none;
    }
    .ckl-tab:hover { color: #0f172a; background: #f4f6f9; }
    .ckl-tab.aktif { color: #fff; background: #101828; }

    /* tombol aksi utama ikut baris tab, bukan baris sendiri di atasnya: "Buat
       catatan" saat daftar catatan dibuka — tab-nya dan tombolnya selalu
       sejajar, jadi mata tidak mencari dua posisi berbeda */
    .ckl-tabs > .btn { text-transform: none; letter-spacing: 0; }

    /* tab kelola toko: daftar toko + siapa saja yang boleh membukanya, sekaligus
       tempat menyunting tokonya. Daftarnya bisa panjang, jadi bagian dalam
       kartunya yang bergulir sementara tinggi kartu tetap — halaman tidak
       memanjang cuma untuk mencar satu toko */
    .ckl-toko-aksi {
        display: flex; flex-wrap: wrap; align-items: center; gap: .6rem;
        padding: .45rem 1rem .6rem; border-bottom: 1px solid #eef1f6;
    }
    .ckl-toko-aksi .btn { text-transform: none; letter-spacing: 0; }
    .ckl-toko-catat { flex: 1 1 18rem; min-width: 0; font-size: .74rem; color: #94a3b8; }
    .ckl-toko-list { max-height: 30rem; overflow-y: auto; }
    .ckl-toko-baris {
        display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .5rem;
        padding: .55rem 1rem; border-bottom: 1px solid #eef1f6;
    }
    .ckl-toko-baris:last-child { border-bottom: 0; }
    .ckl-toko-utama { flex: 1 1 16rem; min-width: 0; }
    .ckl-toko-nama { font-size: .86rem; font-weight: 700; color: #0f172a; }
    .ckl-toko-kenal { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; margin-top: .3rem; }
    /* aksi tiap toko berupa ikon biasa, bukan tombol berteks: barisnya sudah
       penuh nama toko + pemegang, tombol berteks mendorongnya ke baris baru */
    .ckl-toko-tar {
        display: inline-flex; align-items: center; justify-content: center;
        width: 1.85rem; height: 1.85rem; padding: 0; flex: 0 0 auto;
        border: 1px solid #e4e7ec; border-radius: .45rem; background: #fff;
        color: #475569; font-size: .78rem; line-height: 1; cursor: pointer;
    }
    .ckl-toko-tar:hover, .ckl-toko-tar:focus-visible { border-color: #101828; color: #101828; }
    .ckl-toko-tar.utama { margin-left: auto; }
    /* sampah toko: warna galat baru muncul saat disentuh, supaya barisnya tidak
       terlihat mengancam sepanjang waktu */
    .ckl-toko-tar.bahaya:hover, .ckl-toko-tar.bahaya:focus-visible { border-color: #b81c16; color: #b81c16; }
    /* satu kolom platform yang sekaligus jadi rinciannya: kolomnya diklik, panel
       terbuka menampilkan semua label asal_order beserta jumlah tokonya. Sampah
       khusus Superadmin dan hanya mematikan pilihan — nota lama tetap menunjuk
       id-nya, jadi barisnya tidak pernah benar-benar dibuang */
    .ckl-plat-kanan { position: relative; }
    .ckl-plat-tribun { text-align: left; cursor: pointer; }
    .ckl-plat-tribun span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ckl-plat-panel {
        position: absolute; left: 0; right: 0; top: calc(100% + .25rem); z-index: 20;
        padding: .25rem; border: 1px solid #e2e8f0; border-radius: .7rem;
        background: #fff; box-shadow: 0 .8rem 1.6rem rgba(15, 23, 42, .14);
    }
    .ckl-plat-gulir { max-height: 9.5rem; overflow-y: auto; }
    .ckl-plat-baris {
        display: flex; align-items: center; gap: .4rem; width: 100%;
        padding: .22rem .35rem; border: 0; border-radius: .45rem; background: none;
        font-size: .78rem; color: #334155; text-align: left;
    }
    .ckl-plat-baris:hover { background: #f1f5f9; }
    .ckl-plat-pilih {
        display: flex; align-items: center; gap: .4rem; flex: 1 1 auto; min-width: 0;
        padding: 0; border: 0; background: none; font: inherit; color: inherit;
        text-align: left; cursor: pointer;
    }
    .ckl-plat-baris.mati { background: none; }
    .ckl-plat-baris.mati:hover { background: #f8fafc; }
    .ckl-plat-nama { font-weight: 600; }
    .ckl-plat-nama i { font-size: .68rem; margin-right: .2rem; }
    .ckl-plat-baris.mati .ckl-plat-nama { color: #98a2b3; text-decoration: line-through; }
    .ckl-plat-pakai, .ckl-plat-mati {
        flex: 0 0 auto; font-size: .6rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .05em; color: #94a3b8;
    }
    .ckl-plat-mati { color: #b81c16; }
    .ckl-plat-x {
        display: inline-flex; align-items: center; justify-content: center;
        width: 1.5rem; height: 1.5rem; padding: 0; margin-left: auto; flex: 0 0 auto;
        border: 0; border-radius: .4rem; background: none; color: #94a3b8;
        font-size: .7rem; line-height: 1; cursor: pointer;
    }
    .ckl-plat-x:hover, .ckl-plat-x:focus-visible { background: #fde8e7; color: #b81c16; }
    .ckl-plat-tambah { border-top: 1px solid #eef1f6; border-radius: 0 0 .45rem .45rem; color: #101828; }
    .ckl-plat-ketik { padding: .35rem .35rem .15rem; }
    /* baris yang menunggu jeda urungkan: tetap terbaca, tapi meredup dan mati */
    .ckl-plat-tunggu { opacity: .35; pointer-events: none; }
    .pil-toko {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: .1rem .5rem; border-radius: 999px;
        background: #eef1f6; color: #475569; font-size: .7rem; font-weight: 600;
    }
    .pil-toko-juragan { background: #f1f5f9; color: #334155; }
    .pil-toko i { font-size: .62rem; }
    /* dua jalur, dua warna: abu-abu = ikut juragan toko itu dan berlaku di semua
       halaman (tidak dilepas dari sini), biru = tautan langsung ke toko ini saja.
       Warna itu yang memberi tahu apa yang sedang dicabut saat tanda silang ditekan */
    .pil-akses { background: #f6f8fb; color: #64748b; }
    .pil-akses em {
        font-style: normal; font-size: .58rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .05em; color: #94a3b8;
    }
    .pil-akses-taut { background: #e6f4fb; color: #0369a1; padding-right: .2rem; }
    /* chip yang tautannya sedang menunggu dilepas: hilang dari pandangan, tapi
       tidak dibuang dari halaman — "Urungkan" cukup menampilkannya lagi */
    .pil-akses-tunggu { display: none; }
    .pil-akses-x {
        display: inline-flex; align-items: center; justify-content: center;
        width: 1.15rem; height: 1.15rem; padding: 0; border: 0; border-radius: 50%;
        background: #fff; color: #b81c16; font-size: .6rem; line-height: 1; cursor: pointer;
    }
    .pil-akses-x:hover, .pil-akses-x:focus-visible { background: #b81c16; color: #fff; }
    .ckl-toko-kosong { font-size: .74rem; color: #94a3b8; }

    /* Jejak aksi di kaki layar, seperti arsip di WhatsApp: tindakannya sudah
       dijalankan, dan beberapa detik pertama masih bisa dibatalkan. Satu pesan
       per tindakan supaya pelepas beruntun tidak saling menutupi. */
    .ckl-undo-tumpuk {
        position: fixed; left: 50%; bottom: 1.25rem; transform: translateX(-50%);
        display: flex; flex-direction: column; align-items: stretch; gap: .4rem;
        width: min(92vw, 25rem); z-index: 1090; pointer-events: none;
    }
    .ckl-undo {
        display: flex; align-items: center; gap: .75rem; position: relative;
        padding: .65rem .8rem .65rem .95rem; border-radius: .55rem; overflow: hidden;
        background: #1b2333; color: #f5f7fb; font-size: .78rem;
        box-shadow: 0 .55rem 1.5rem rgba(15, 23, 42, .28);
        pointer-events: auto; animation: ckl-undo-muncul .16s ease-out;
    }
    .ckl-undo-teks { flex: 1 1 auto; }
    .ckl-undo-tombol {
        flex: 0 0 auto; border: 0; padding: .1rem .15rem; background: none;
        color: #7dd3fc; font-size: .72rem; font-weight: 700; line-height: 1;
        text-transform: uppercase; letter-spacing: .06em; cursor: pointer;
    }
    .ckl-undo-tombol:hover, .ckl-undo-tombol:focus-visible { color: #bae6fd; }
    /* sisa waktunya terbaca sebagai garis di kaki pesan; durasinya harus sama
       dengan JEDA di skrip, karena di situlah tautannya benar-benar dicabut */
    .ckl-undo::after {
        content: ""; position: absolute; left: 0; bottom: 0; height: 2px;
        width: 100%; background: #38bdf8; animation: ckl-undo-hitung 6s linear forwards;
    }
    @keyframes ckl-undo-muncul { from { opacity: 0; transform: translateY(.4rem); } }
    @keyframes ckl-undo-hitung { to { width: 0; } }

    /* popus kelola toko: kotak centang akun yang boleh membuka tokonya */
    .ckl-cs {
        display: flex; flex-direction: column; gap: .25rem; max-height: 11rem; overflow-y: auto;
        padding: .5rem .6rem; border: 1px solid #e2e8f0; border-radius: .7rem; background: #fbfcfe;
    }
    .ckl-cs-baris { display: flex; align-items: center; gap: .45rem; font-size: .8rem; color: #334155; margin: 0; }
    /* akun yang bisa membuka lewat juragan toko: sudah tercentang tapi terkunci,
       karena jalan mencabutnya pembagian juragan, bukan popus ini */
    .ckl-cs-baris.ikut-juragan { color: #98a2b3; }
    .ckl-cs-baris.ikut-juragan::after {
        content: "ikut juragan"; margin-left: auto; flex: 0 0 auto;
        font-size: .58rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .05em; color: #94a3b8;
    }
    .ckl-cs-baris.ikut-juragan .form-check-input:disabled { opacity: 1; }
    /* kotak cari akun: menempel tepat di atas daftar centang yang disaringnya */
    .ckl-cs-cari { position: relative; margin: .35rem 0 .5rem; }
    /* padding kiri dipaksa !important karena .form-control-sm di style.css juga
       memakai !important; tanpa itu ikon kaca pembesar menimpa placeholder */
    .ckl-cs-cari .form-control { padding-left: 1.9rem !important; }
    .ckl-cs-cari i {
        position: absolute; left: .6rem; top: 50%; transform: translateY(-50%);
        font-size: .72rem; color: #94a3b8; pointer-events: none;
    }

    /* Bootstrap menaruh header/body/footer sebagai anak langsung modal-content,
       sementara di sini form_open membungkus ketiganya. Akibatnya badan popus
       tidak pernah menyusut dan footer terdorong keluar kotak modal (yang
       memotong isinya) — tombol Batal/Simpan jadi tidak kelihatan. Tata letak
       kolomnya dipasang ulang di form, dan hanya badan yang bergulir. */
    #modalToko .modal-content > form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    #modalToko .modal-body { overflow-y: auto; }
    #modalToko .modal-footer { flex: 0 0 auto; }

    @media (max-width: 991.98px) {
        /* judul + keterangan harus punya barisnya sendiri: selama ia flex:1 1 0
           ia berebut ruang dengan tombol dan menyusut sampai teksnya turun
           dua puluh baris (terukur: kepala 740px tinggi di lebar 390px) */
        .ckl-head { padding-right: 0; }
        .ckl-head > .me-auto { flex: 1 1 100%; }
        .ckl-tabs { padding-left: .75rem; padding-right: .75rem; }
        .ckl-tabs > .btn { flex: 1 1 100%; }
        .ckl-tab { flex: 1 1 auto; text-align: center; padding-left: .4rem; padding-right: .4rem; }
        .ckl-saring > .lapangan { flex: 1 1 auto; min-width: 0; }
        .ckl-saring .form-control, .ckl-saring .form-select { max-width: none; }

        /* tab kelola toko adalah seluruh isi kartu saat itu dibuka, jadi tidak
           perlu dipotong lagi — biar halaman yang bergulir, bukan kotak kecil */
        .ckl-toko-list { max-height: none; }
        .ckl-toko-aksi { padding-left: .75rem; padding-right: .75rem; }
        .ckl-toko-utama { flex: 1 1 100%; }
        .ckl-toko-tar.utama { margin-left: 0; }
    }

    @media (min-width: 992px) {
        #modalToko .modal-content { max-height: 80vh; }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl ckl-halaman">

    <div class="ckl-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><i class="fal fa-ballot-check me-1 text-brand-500 judul-ikon"></i> <?= esc($title) ?></h1>
            <?php if ($tabKelola) : ?>
                <p>Daftar toko beserta siapa saja yang boleh membukanya. Akun yang memegang juragan sebuah toko otomatis memegang tokonya; tautan dipakai untuk menambah toko lain ke satu akun tanpa mengubah juragan yang dia pegang.</p>
            <?php else : ?>
                <p>Catatan kerja tiap toko yang jadi delegasimu. Yang ditulis CS langsung terhitung selesai; yang ditulis Admin atau Superadmin masuk daftar yang masih harus dikerjakan.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($kabar !== '') : ?>
        <div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div>
    <?php endif; ?>
    <?php if ($galat !== '') : ?>
        <div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div>
    <?php endif; ?>

    <div class="ckl-kartu">
        <div class="ckl-tabs">
            <a class="ckl-tab <?= ! $tabKelola && $s['kerja'] === 'belum' ? 'aktif' : '' ?>"
                href="<?= esc($tanya(['kerja' => 'belum', 'tab' => ''])) ?>">Belum dikerjakan</a>
            <a class="ckl-tab <?= ! $tabKelola && $s['kerja'] === 'sudah' ? 'aktif' : '' ?>"
                href="<?= esc($tanya(['kerja' => 'sudah', 'tab' => ''])) ?>">Sudah dikerjakan</a>
            <?php if ($kelolaToko) : ?>
                <a class="ckl-tab <?= $tabKelola ? 'aktif' : '' ?>"
                    href="<?= esc($tanya(['tab' => 'kelola'])) ?>">Kelola Toko</a>
            <?php endif; ?>
            <?php if ($toko !== [] && ! $tabKelola) : ?>
                <button type="button" class="btn btn-sm btn-dark ms-auto" id="tombolCatatan"
                    data-bs-toggle="modal" data-bs-target="#modalCatatan">
                    <i class="fal fa-plus me-1"></i> Buat catatan
                </button>
            <?php endif; ?>
        </div>
        <?php if ($tabKelola) : ?>
            <!-- isi tab kelola toko hanya dirender saat tabnya dibuka: daftar
               tokonya panjang dan bukan sesuatu yang perlu dilihat orang yang
               sedang mengerjakan catatan -->
            <div class="ckl-toko-aksi">
                <span class="ckl-toko-catat">Akun abu-abu ikut juragan tokonya dan berlaku di semua halaman; yang biru ditautkan langsung ke toko ini saja, jadi bisa dilepas dari sini tanpa menyentuh juragan siapa pun.</span>
                <button type="button" class="btn btn-sm btn-dark ms-auto"
                    data-bs-toggle="modal" data-bs-target="#modalToko" data-id="0">
                    <i class="fal fa-plus me-1"></i> Tambah toko
                </button>
            </div>
            <div class="ckl-toko-list">
                <?php if ($toko === []) : ?>
                    <div class="ckl-kosong">
                        <i class="fal fa-store-slash"></i>
                        Belum ada toko. Tambahkan satu lewat tombol &quot;Tambah toko&quot; di atas.
                    </div>
                <?php endif; ?>
                <?php foreach ($toko as $id_toko => $t) : ?>
                    <?php
                    $kenal         = $pemegang[(int) $id_toko] ?? [];
                    $warisan       = [];
                    $langsung      = [];
                    $punya_cs      = [];
                    $punya_juragan = [];

                    foreach ($kenal as $o) {
                        if ($o['sumber'] === 'tautan') {
                            $langsung[] = $o;
                            $punya_cs[] = (int) $o['id'];
                        } else {
                            $warisan[]       = $o;
                            $punya_juragan[] = (int) $o['id'];
                        }
                    }

                    $nama_j = $t['juragan_id'] !== null
                        ? ($namajuragan[(int) $t['juragan_id']] ?? 'Juragan #' . (int) $t['juragan_id'])
                        : '';
                    ?>
                    <div class="ckl-toko-baris">
                        <div class="ckl-toko-utama">
                            <div class="ckl-toko-nama">
                                <?= esc($t['nama']) ?>
                                <?php if ($t['platform'] !== '') : ?>
                                    <span class="pil-toko"><?= esc($t['platform']) ?></span>
                                <?php endif; ?>
                                <?php if ($nama_j !== '') : ?>
                                    <span class="pil-toko pil-toko-juragan" title="Toko ini berdiri di atas juragan <?= esc($nama_j) ?>">
                                        <i class="fal fa-store"></i> <?= esc($nama_j) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="ckl-toko-kenal">
                                <?php if ($kenal === []) : ?>
                                    <span class="ckl-toko-kosong">belum ada yang boleh membuka lembaran toko ini</span>
                                <?php endif; ?>
                                <?php foreach ($warisan as $o) : ?>
                                    <div class="pil-toko pil-akses"
                                        title="Ikut juragan yang dia pegang — dilepas lewat pembagian juragan, bukan dari sini">
                                        <?= esc($o['nama']) ?> <em>ikut juragan</em>
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach ($langsung as $o) : ?>
                                    <div class="pil-toko pil-akses pil-akses-taut" title="Ditautkan langsung ke toko ini">
                                        <?= esc($o['nama']) ?>
                                        <button type="button" class="pil-akses-x"
                                            data-toko="<?= (int) $id_toko ?>"
                                            data-user="<?= (int) $o['id'] ?>" data-nama="<?= esc($o['nama'], 'attr') ?>"
                                            title="Lepas tautan <?= esc($o['nama']) ?> dari toko ini"
                                            aria-label="Lepas tautan <?= esc($o['nama']) ?> dari toko ini">
                                            <i class="fal fa-xmark"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="button" class="ckl-toko-tar utama"
                            data-bs-toggle="modal" data-bs-target="#modalToko"
                            data-id="<?= (int) $id_toko ?>"
                            data-nama="<?= esc($t['nama'], 'attr') ?>"
                            data-platform="<?= esc($t['platform'], 'attr') ?>"
                            data-cs="<?= implode(',', $punya_cs) ?>"
                            data-juragan-cs="<?= implode(',', $punya_juragan) ?>"
                            title="Sunting toko ini" aria-label="Sunting toko <?= esc($t['nama']) ?>">
                            <i class="fal fa-pen"></i>
                        </button>
                        <!-- sampah menghapus tokonya sungguhan, riwayat checklist-nya
                             ikut; karena itu tidak ada jalan urungkan, hanya
                             konfirmasi dulu sebelum permintaan dikirim -->
                        <button type="button" class="ckl-toko-tar bahaya"
                            data-bs-toggle="modal" data-bs-target="#modalHapusToko"
                            data-id="<?= (int) $id_toko ?>"
                            data-nama="<?= esc($t['nama'], 'attr') ?>"
                            data-platform="<?= esc($t['platform'], 'attr') ?>"
                            data-lembar="<?= (int) ($lembarToko[(int) $id_toko] ?? 0) ?>"
                            title="Hapus toko ini beserta riwayat checklistnya"
                            aria-label="Hapus toko <?= esc($t['nama']) ?>">
                            <i class="fal fa-trash"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
        <form class="ckl-saring" method="get" action="<?= esc(site_url('admin/checklist')) ?>">
            <input type="hidden" name="kerja" value="<?= esc($s['kerja']) ?>">
            <?php if (count($toko) > 1) : ?>
                <div class="lapangan">
                    <label for="toko">Toko</label>
                    <select class="form-select form-select-sm" id="toko" name="toko">
                        <option value="">Semua toko</option>
                        <?php foreach ($toko as $id_toko => $t) : ?>
                            <option value="<?= (int) $id_toko ?>" <?= (int) $s['toko_id'] === (int) $id_toko ? 'selected' : '' ?>>
                                <?= esc($t['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="lapangan">
                <label for="tanggal">Tanggal</label>
                <input type="date" class="form-control form-control-sm" id="tanggal" name="tanggal" value="<?= esc($s['tanggal']) ?>">
            </div>
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
                <div class="d-inline-flex gap-2 align-items-stretch ckl-saring-aksi">
                    <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-search"></i> Cari</button>
                    <button type="button" class="btn btn-sm btn-dark d-inline-flex" data-saring-reset="kerja,batas"
                        title="Kosongkan saringan" aria-label="Kosongkan saringan">
                        <i class="fal fa-sync"></i>
                    </button>
                </div>
            </div>
        </form>

        <div data-saring-wadah>
        <?php if ($baris === []) : ?>
            <div class="ckl-kosong">
                <i class="fal fa-circle-check text-success"></i>
                <?= $s['kerja'] === 'sudah'
                    ? 'Belum ada catatan yang selesai di sini.'
                    : 'Tidak ada yang menunggu. Tulis catatan baru lewat tombol "Buat catatan" di atas.' ?>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle ckl-daftar tabel-rapi">
                    <thead>
                        <tr>
                            <th>Catatan</th>
                            <th>Toko</th>
                            <th>Tanggal</th>
                            <th>Siapa yang buat</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baris as $r) : ?>
                            <?php $beres = (string) $r['status'] !== 'belum'; ?>
                            <tr>
                                <td>
                                    <span class="ckl-nama"><?= esc($r['judul']) ?></span>
                                    <div class="ckl-sub">
                                        <span><?= $r['item_id'] === null ? 'catatan sendiri' : 'butir templat' ?></span>
                                        <?php if (! empty($r['nama_tujuan'])) : ?>
                                            <span><i class="fal fa-user-check"></i> untuk <?= esc($r['nama_tujuan']) ?></span>
                                        <?php endif; ?>
                                        <?php if ((int) $r['perlu_tindakan'] === 1) : ?>
                                            <span class="text-danger"><i class="fal fa-triangle-exclamation"></i> perlu tindakan</span>
                                        <?php endif; ?>
                                        <?php if ($r['alasan']) : ?>
                                            <span><i class="fal fa-info-circle"></i> <?= esc($r['alasan']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($r['catatan_butir']) : ?>
                                            <span><i class="fal fa-note-sticky"></i> <?= esc($r['catatan_butir']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-nowrap"><?= esc($r['nama_toko'] ?: 'Toko #' . (int) $r['toko_id']) ?></td>
                                <td class="text-nowrap"><?= esc(tanggal_rincian((string) $r['tanggal'])) ?></td>
                                <td class="text-muted">
                                    <?php
                                    $pembuat = $r['nama_dibuat'] ?: $r['nama_dikerjakan'];
                                    echo $pembuat ? esc($pembuat) : '<span class="text-muted">belum ada</span>';
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if (! $beres) : ?>
                                        <button type="button" class="btn btn-sm btn-link text-decoration-none"
                                            data-bs-toggle="modal" data-bs-target="#modalSelesai"
                                            data-run="<?= (int) $r['run_id'] ?>" data-hasil="<?= (int) $r['id_hasil'] ?>"
                                            data-judul="<?= esc($r['judul'], 'attr') ?>">Selesaikan</button>
                                    <?php else : ?>
                                        <a class="btn btn-sm btn-link text-decoration-none"
                                            href="<?= esc(site_url('admin/checklist/' . (int) $r['run_id'])) ?>">Lihat</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($total > $batas) : ?>
            <div class="ckl-pager d-flex flex-wrap align-items-center gap-2">
                <span class="small text-muted"><?= (int) $total ?> catatan &middot; halaman <?= (int) $page ?></span>
                <div class="ms-auto">
                    <?= \Config\Services::pager()->makeLinks($page, $batas, $total, 'front_full') ?>
                </div>
            </div>
        <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($tabKelola) : ?>
        <!-- satu popus untuk seluruh toko: tombol pensil di baris mana pun hanya
             mengisi tokonya, jadi tidak ada dua set nama field di halaman yang sama -->
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

                            <label class="form-label small fw-bold mt-2" for="tk-plat-tribun">Platform</label>

                            <!-- satu kolom, satu panel: diklik muncul rincian semua platform
                                 tersimpan lengkap dengan jumlah tokonya, dan Superadmin melihat
                                 sampah di tiap barisnya. Nilai yang dikirim ke server tetap
                                 select asli yang bersembunyi di bawah sini -->
                            <div class="ckl-plat-kanan" id="tk-plat-kanan">
                                <select class="d-none" id="tk-platform" name="platform">
                                    <option value="">Tanpa platform</option>
                                    <?php foreach ($platform as $p) : ?>
                                        <option value="<?= esc($p) ?>"><?= esc($p) ?></option>
                                    <?php endforeach; ?>
                                    <?php if ($level === 'superadmin') : ?>
                                        <!-- penanda "nama ini sedang diketik, bukan dipilih";
                                             dijaga sama dengan Checklist::PLATFORM_BARU -->
                                        <option value="__baru__">+ Tambah platform…</option>
                                    <?php endif; ?>
                                </select>

                                <button type="button" class="ckl-plat-tribun form-select form-select-sm" id="tk-plat-tribun"
                                    aria-haspopup="listbox" aria-expanded="false" aria-label="Pilih platform">
                                    <span id="tk-plat-tribun-teks">Tanpa platform</span>
                                </button>

                                <div class="ckl-plat-panel d-none" id="tk-plat-panel" role="listbox">
                                    <div class="ckl-plat-gulir" id="tk-plat-daftar">
                                        <div class="ckl-plat-baris">
                                            <button type="button" class="ckl-plat-pilih" data-plat-pilih="">
                                                <span class="ckl-plat-nama">Tanpa platform</span>
                                            </button>
                                        </div>
                                        <?php foreach ($platformInfo as $p) : ?>
                                            <div class="ckl-plat-baris<?= $p['aktif'] ? '' : ' mati' ?>"
                                                data-id="<?= (int) $p['id'] ?>" data-label="<?= esc($p['label'], 'attr') ?>">
                                                <button type="button" class="ckl-plat-pilih" data-plat-pilih="<?= esc($p['label'], 'attr') ?>"
                                                    <?= $p['aktif'] ? '' : 'disabled' ?>>
                                                    <span class="ckl-plat-nama"><?= esc($p['label']) ?></span>
                                                </button>
                                                <?php if ($p['toko'] > 0) : ?>
                                                    <span class="ckl-plat-pakai"><?= (int) $p['toko'] ?> toko</span>
                                                <?php endif; ?>
                                                <span class="ckl-plat-mati<?= $p['aktif'] ? ' d-none' : '' ?>">nonaktif</span>
                                                <?php if ($level === 'superadmin') : ?>
                                                    <button type="button" class="ckl-plat-x<?= $p['aktif'] ? '' : ' d-none' ?>"
                                                        data-plat-buang title="Sembunyikan dari pilihan platform"
                                                        aria-label="Sembunyikan platform">
                                                        <i class="fal fa-trash"></i>
                                                    </button>
                                                    <button type="button" class="ckl-plat-x<?= $p['aktif'] ? ' d-none' : '' ?>"
                                                        data-plat-pakai title="Pakai lagi platform ini"
                                                        aria-label="Pakai lagi platform ini">
                                                        <i class="fal fa-rotate-left"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <?php if ($level === 'superadmin') : ?>
                                        <button type="button" class="ckl-plat-baris ckl-plat-tambah" id="tk-plat-tambah">
                                            <span class="ckl-plat-nama"><i class="fal fa-plus"></i> Tambah platform…</span>
                                        </button>

                                        <div class="ckl-plat-ketik d-none" id="tk-plat-ketik">
                                            <!-- nama baru disimpan ke asal_order saat tokonya disimpan,
                                                 jadi pilihannya ikut bertambah untuk toko berikutnya -->
                                            <input type="text" class="form-control form-control-sm" id="tk-platform-baru"
                                                name="platform_baru" maxlength="25" placeholder="Nama platform baru, mis. TikTok Shop" disabled>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <label class="form-label small fw-bold mt-2" for="tk-aktif">Status</label>
                            <select class="form-select form-select-sm" id="tk-aktif" name="aktif">
                                <option value="1">Aktif</option>
                                <option value="0">Dimatikan</option>
                            </select>

                            <label class="form-label small fw-bold mt-2" for="tk-cs-cari">Akun yang boleh membuka toko ini</label>
                            <div class="ckl-cs-cari">
                                <i class="fal fa-search"></i>
                                <input type="search" class="form-control form-control-sm" id="tk-cs-cari"
                                    placeholder="Cari nama admin atau CS" autocomplete="off">
                            </div>
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
                                <?php else : ?>
                                    <span class="ckl-toko-kosong" id="tk-cs-kosong" hidden>tidak ada nama yang cocok</span>
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

        <!-- konfirmasi hapus toko: satu popus untuk semua baris, isinya diambil
             dari tombol sampah yang diklik. window.confirm() tidak dipakai karena
             peramban bisa memblokirnya selamanya begitu pengguna pernah menekan
             "Prevent", dan dialog bawaan tidak bisa menjelaskan seberapa banyak
             catatan kerja yang ikut hilang -->
        <div class="modal fade" id="modalHapusToko" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <?= form_open('admin/checklist/toko-hapus') ?>
                        <!-- csrf_field() ditulis sendiri: form_open hanya menyisipkan
                             tokennya kalau filter csrf benar-benar terdaftar saat ini,
                             dan penghapusan tidak boleh bergantung pada itu -->
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_toko" id="hapus-toko-id" value="0">
                        <div class="modal-header">
                            <h5 class="modal-title">Hapus toko ini?</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <div class="ckl-toko-nama" id="hapus-toko-nama">-</div>
                            <div class="ckl-toko-kenal" id="hapus-toko-platform"></div>
                            <p class="small text-muted mb-0 mt-2" id="hapus-toko-catat"></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-sm btn-danger"><i class="fal fa-trash me-1"></i> Hapus</button>
                        </div>
                    <?= form_close() ?>
                </div>
            </div>
        </div>

        <!-- Pesan sementara untuk setiap tanda silang yang ditekan: tautannya belum
             benar-benar dicabut sebelum pesan ini habis waktunya, jadi masih bisa
             diurungkan seperti arsip di WhatsApp -->
        <div class="ckl-undo-tumpuk" id="ckl-undo-tumpuk" aria-live="polite"></div>

        <script>
    (function () {
        var modal = document.getElementById('modalToko');

        if (! modal) {
            return;
        }

        var cari   = modal.querySelector('#tk-cs-cari');
        var daftar = modal.querySelector('#tk-cs');
        var baris  = Array.prototype.slice.call(daftar.querySelectorAll('.ckl-cs-baris'));
        var kosong = modal.querySelector('#tk-cs-kosong');

        var pilihPlatform = modal.querySelector('#tk-platform');
        var baruPlatform  = modal.querySelector('#tk-platform-baru');
        var tribun        = modal.querySelector('#tk-plat-tribun');
        var tribunTeks    = modal.querySelector('#tk-plat-tribun-teks');
        var panel         = modal.querySelector('#tk-plat-panel');
        var tambah        = modal.querySelector('#tk-plat-tambah');
        var ketik         = modal.querySelector('#tk-plat-ketik');

        // penanda "nama ini sedang diketik, bukan dipilih"; harus sama dengan
        // Checklist::PLATFORM_BARU di server
        var BARU = '__baru__';

        // satu kolom untuk memilih sekaligus melihat rincian: panelnya dibuka dan
        // ditutup di sini juga — tanpa request, tanpa komponen tambahan. Yang
        // dikirim ke server tetap select asli di atas
        var buka = function (ya) {
            panel.classList.toggle('d-none', ! ya);
            tribun.setAttribute('aria-expanded', ya ? 'true' : 'false');
        };

        // wajah kolom mengikuti isinya: nama yang dipilih, atau nama yang diketik
        var sinkron = function () {
            var manual = pilihPlatform.value === BARU;

            if (baruPlatform) {
                baruPlatform.disabled = ! manual;
                ketik.classList.toggle('d-none', ! manual);
            }

            var nama = manual
                ? (baruPlatform ? baruPlatform.value.trim() : '')
                : pilihPlatform.value;

            tribunTeks.textContent = nama === ''
                ? (manual ? 'Platform baru' : 'Tanpa platform')
                : nama + (manual ? ' (baru)' : '');
        };

        // platform yang sedang disembunyikan tidak ada di daftar pilihan. Untuk
        // akun yang tidak boleh mengetik (bukan Superadmin) nama itu dititipkan
        // sebagai pilihan sementara, supaya menyunting tokonya tidak mengosongkan
        // platform yang sudah menempel
        var opsiSementara = function (label) {
            var ada = Array.prototype.slice.call(pilihPlatform.options).some(function (o) {
                return o.value === label;
            });

            if (ada) {
                return;
            }

            var opsi = document.createElement('option');
            opsi.value = label;
            opsi.textContent = label + ' (nonaktif)';
            pilihPlatform.appendChild(opsi);
        };

        var pilih = function (label, manual) {
            if (manual && baruPlatform) {
                pilihPlatform.value = BARU;
                baruPlatform.value  = label;
            } else if (manual) {
                opsiSementara(label);
                pilihPlatform.value = label;
            } else {
                if (baruPlatform) {
                    baruPlatform.value = '';
                }

                pilihPlatform.value = label;
            }

            sinkron();
        };

        // daftar pilihan bisa berubah dari sisi lain layar (sampah di rincian),
        // jadi kolom ini ikut membaca ulang setiap kali nilainya berganti
        pilihPlatform.addEventListener('change', sinkron);

        tribun.addEventListener('click', function () {
            buka(panel.classList.contains('d-none'));
        });

        if (tambah) {
            tambah.addEventListener('click', function () {
                pilih(baruPlatform.value.trim(), true);
                baruPlatform.focus();
            });
        }

        if (baruPlatform) {
            baruPlatform.addEventListener('input', sinkron);
        }

        panel.addEventListener('click', function (peristiwa) {
            var opsi = peristiwa.target.closest ? peristiwa.target.closest('[data-plat-pilih]') : null;

            if (! opsi) {
                return;
            }

            peristiwa.preventDefault();
            pilih(opsi.getAttribute('data-plat-pilih') || '', false);
            buka(false);
            tribun.focus();
        });

        // menutupnya cukup ke mana pun di luar kolom diklik, atau Esc; sampah di
        // dalam panel sendiri tidak menutup, jadi beberapa platform bisa
        // disembunyikan berurutan tanpa membuka ulang — begitu juga pesan
        // "Urungkan" di kaki layar, menawarannya tidak boleh menutup rinciannya
        document.addEventListener('click', function (peristiwa) {
            var dekat = peristiwa.target.closest ? peristiwa.target.closest('#tk-plat-kanan, .ckl-undo-tumpuk') : null;

            if (! panel.classList.contains('d-none') && dekat === null) {
                buka(false);
            }
        });

        document.addEventListener('keydown', function (peristiwa) {
            if (peristiwa.key === 'Escape') {
                buka(false);
            }
        });

        modal.addEventListener('hidden.bs.modal', function () {
            buka(false);
        });

        // daftar akun disaring dari namanya: yang dicari biasanya sudah diketahui
        // namanya, jadi mengetik beberapa huruf lebih cepat daripada menggulir
        var saring = function () {
            var kunci  = (cari.value || '').toLowerCase().trim();
            var tampil = 0;

            baris.forEach(function (b) {
                var cocok = kunci === '' || b.textContent.toLowerCase().indexOf(kunci) > -1;

                b.classList.toggle('d-none', ! cocok);
                tampil += cocok ? 1 : 0;
            });

            if (kosong) {
                kosong.hidden = tampil > 0 || baris.length === 0;
            }
        };

        cari.addEventListener('input', saring);

        modal.addEventListener('show.bs.modal', function (event) {
            var pemicu = event.relatedTarget;

            if (! pemicu) {
                return;
            }

            var id = pemicu.getAttribute('data-id') || '0';

            modal.querySelector('#tk-id').value       = id;
            modal.querySelector('#tk-nama').value     = pemicu.getAttribute('data-nama') || '';
            modal.querySelector('#tk-aktif').value    = '1';

            // platform dibuka lewat kolom yang sama. Toko lama yang platformnya
            // sudah tidak ada di daftar (mis. sedang disembunyikan) tetap
            // terbaca: Superadmin mengetik namanya kembali, akun lain memegang
            // nama itu sebagai pilihan sementara
            var platformnya = id === '0' ? '' : (pemicu.getAttribute('data-platform') || '');

            pilihPlatform.value = platformnya;

            pilih(platformnya, platformnya !== '' && pilihPlatform.value === '');

            buka(false);

            // akun yang sudah punya akses dicentang semua supaya terbaca "siapa
            // saja yang bisa membuka toko ini". Tautan langsung boleh dilepas di
            // sini; yang ikut juragan dikunci karena jalurnya pembagian juragan
            var langsung = (pemicu.getAttribute('data-cs') || '').split(',');
            var warisan  = (pemicu.getAttribute('data-juragan-cs') || '').split(',');

            modal.querySelectorAll('#tk-cs input[type="checkbox"]').forEach(function (kotak) {
                kotak.checked  = langsung.indexOf(kotak.value) > -1 || warisan.indexOf(kotak.value) > -1;
                kotak.disabled = warisan.indexOf(kotak.value) > -1 && langsung.indexOf(kotak.value) === -1;
            });

            baris.forEach(function (b) {
                b.classList.toggle('ikut-juragan', b.querySelector('input').disabled);
            });

            // yang sudah dipilih dipindah ke puncak daftar: tanpa itu centangnya
            // tertimbun di bawah gulungan dan orang mengira pilihannya hilang
            var dipilih = baris.filter(function (b) { return b.querySelector('input').checked; });
            var sisa    = baris.filter(function (b) { return ! b.querySelector('input').checked; });

            dipilih.concat(sisa).forEach(function (b) { daftar.appendChild(b); });

            cari.value = '';
            saring();

            modal.querySelector('#modalTokoJudul').textContent = id === '0' ? 'Tambah toko baru' : 'Sunting toko';
            modal.querySelector('#modalTokoSimpan').innerHTML  = id === '0'
                ? '<i class="fal fa-plus me-1"></i> Simpan'
                : '<i class="fal fa-save me-1"></i> Simpan';
        });
    }());

    (function () {
        // popus konfirmasi hapus: baris mana pun memakai satu popus yang sama,
        // isinya diambil dari tombol sampah yang membuka popus ini
        var hapus = document.getElementById('modalHapusToko');

        if (! hapus) {
            return;
        }

        hapus.addEventListener('show.bs.modal', function (event) {
            var pemicu = event.relatedTarget;

            if (! pemicu) {
                return;
            }

            var nama   = pemicu.getAttribute('data-nama') || '';
            var plat   = pemicu.getAttribute('data-platform') || '';
            var lembar = parseInt(pemicu.getAttribute('data-lembar') || '0', 10);

            hapus.querySelector('#hapus-toko-id').value   = pemicu.getAttribute('data-id') || '0';
            hapus.querySelector('#hapus-toko-nama').textContent = nama === '' ? 'Toko #' + pemicu.getAttribute('data-id') : nama;

            // platform toko ditulis sebagai pil kecil, sama seperti di daftar
            var kenal = hapus.querySelector('#hapus-toko-platform');

            kenal.textContent = '';

            if (plat !== '') {
                var pil = document.createElement('span');
                pil.className = 'pil-toko';
                pil.textContent = plat;
                kenal.appendChild(pil);
            }

            // angka lembar inilah yang menentukan apakah penghapusan masih bisa
            // dianggap aman atau tidak
            hapus.querySelector('#hapus-toko-catat').textContent = lembar > 0
                ? 'Riwayat checklist toko ini ikut terhapus ' + lembar + ' lembar, dan tidak bisa dikembalikan.'
                : 'Toko ini belum pernah dibuka checklist-nya, jadi tidak ada catatan kerja yang ikut hilang.';
        });
    }());

    (function () {
        var tumpuk = document.getElementById('ckl-undo-tumpuk');

        if (! tumpuk) {
            return;
        }

        // chip-nya hilang lebih dulu, tautannya baru dicabut setelah jeda ini —
        // angkanya harus sama dengan durasi garis hitung di CSS
        var JEDA   = 6000;
        var alamat = '<?= site_url('admin/checklist/tautan-copot') ?>';

        var tarDari = function (pil) {
            var baris = pil.closest('.ckl-toko-baris');

            return baris ? baris.querySelector('.ckl-toko-tar') : null;
        };

        // daftar id akun di tombol sunting ikut dijaga: tanpa itu popus sunting
        // masih mencentang orang yang tautannya baru dilepas, lalu menyimpannya
        // memasang tautannya kembali
        var ubahDaftar = function (tar, atribut, id, tambah) {
            if (! tar) {
                return;
            }

            var kini = (tar.getAttribute(atribut) || '').split(',').filter(function (v) {
                return v !== '' && v !== id;
            });

            if (tambah) {
                kini.push(id);
            }

            tar.setAttribute(atribut, kini.join(','));
        };

        // satu pesan untuk satu tindakan: hilang sendiri setelah jedanya habis,
        // atau lebih cepat kalau tombolnya ditekan. Beberapa pelepasan beruntun
        // menumpuk ke atas, jadi tidak ada yang tertutupi
        var pesan = function (teks, label, saatUrung, saatHabis) {
            var bar = document.createElement('div');
            bar.className = 'ckl-undo';

            var isi = document.createElement('span');
            isi.className = 'ckl-undo-teks';
            isi.textContent = teks;

            var tombol = document.createElement('button');
            tombol.type = 'button';
            tombol.className = 'ckl-undo-tombol';
            tombol.textContent = label;

            bar.appendChild(isi);
            bar.appendChild(tombol);
            tumpuk.appendChild(bar);

            var buang = function () {
                window.clearTimeout(jam);

                // dilepas pada tick berikutnya: tombol pesan ini sering jadi
                // sasaran klik, dan peristiwa kliknya masih harus bisa melihat
                // bahwa asalnya bukan sembarang tempat di layar
                window.setTimeout(function () {
                    if (bar.parentNode) {
                        bar.parentNode.removeChild(bar);
                    }
                }, 0);
            };

            var jam = window.setTimeout(function () {
                buang();

                if (saatHabis) {
                    saatHabis();
                }
            }, JEDA);

            tombol.addEventListener('click', function () {
                buang();

                if (saatUrung) {
                    saatUrung();
                }
            });
        };

        // gagal di tengah jalan: chip kembali utuh beserta tanda silangnya, dan
        // pesannya berubah jadi kabar biasa tanpa tawaran mengurungkan
        var kembalikan = function (pil, tombol, teks) {
            pil.classList.remove('pil-akses-tunggu');
            tombol.disabled = false;
            pesan(teks, 'Tutup', null, null);
        };

        // akun itu masih bisa membuka toko lewat juragan yang dia pegang: chipnya
        // langsung berganti jadi bentuk abu-abu, bukan tinggal kosong
        var jadiIkutJuragan = function (pil, tar, id, nama) {
            pil.classList.remove('pil-akses-tunggu', 'pil-akses-taut');
            pil.classList.add('pil-akses');
            pil.setAttribute('title', 'Ikut juragan yang dia pegang — dilepas lewat pembagian juragan, bukan dari sini');

            pil.textContent = nama + ' ';

            var em = document.createElement('em');
            em.textContent = 'ikut juragan';
            pil.appendChild(em);

            ubahDaftar(tar, 'data-juragan-cs', id, true);
        };

        // tanpa jQuery satu-satunya jalan mengirim adalah form biasa: halaman
        // dimuat ulang dan server yang berbicara lewat pesan flash
        var kirimForm = function (tujuan, lapangan) {
            var form = document.createElement('form');
            form.method = 'post';
            form.action = tujuan;

            lapangan.push(['<?= csrf_token() ?>', '<?= csrf_hash() ?>']);

            lapangan.forEach(function (p) {
                var isian = document.createElement('input');
                isian.type  = 'hidden';
                isian.name  = p[0];
                isian.value = p[1];
                form.appendChild(isian);
            });

            document.body.appendChild(form);
            form.submit();
        };

        var lepas = function (tombol) {
            var pil = tombol.closest('.pil-akses-taut');

            if (! pil || pil.classList.contains('pil-akses-tunggu')) {
                return;
            }

            var tar     = tarDari(pil);
            var id      = tombol.getAttribute('data-user') || '0';
            var id_toko = tombol.getAttribute('data-toko') || '0';
            var nama    = tombol.getAttribute('data-nama') || '';

            pil.classList.add('pil-akses-tunggu');
            tombol.disabled = true;

            // urungkan: chipnya kembali dan tidak ada satu pun permintaan terkirim
            pesan('Tautan ' + nama + ' dilepas.', 'Urungkan', function () {
                pil.classList.remove('pil-akses-tunggu');
                tombol.disabled = false;
            }, function () {
                if (! window.jQuery) {
                    kirimForm(alamat, [['id_toko', id_toko], ['user_id', id]]);

                    return;
                }

                window.jQuery.post(alamat, { id_toko: id_toko, user_id: id })
                    .done(function (balasan) {
                        if (! balasan || ! balasan.ok) {
                            kembalikan(pil, tombol, (balasan && balasan.pesan) || 'Tautan gagal dilepas.');
                            return;
                        }

                        ubahDaftar(tar, 'data-cs', id, false);

                        if (balasan.lewat_juragan) {
                            jadiIkutJuragan(pil, tar, id, nama);
                            return;
                        }

                        if (pil.parentNode) {
                            pil.parentNode.removeChild(pil);
                        }
                    })
                    .fail(function () {
                        kembalikan(pil, tombol, 'Tautan gagal dilepas. Periksa sambungan lalu coba lagi.');
                    });
            });
        };

        document.addEventListener('click', function (peristiwa) {
            var tombol = peristiwa.target.closest ? peristiwa.target.closest('.pil-akses-x') : null;

            if (! tombol) {
                return;
            }

            peristiwa.preventDefault();
            lepas(tombol);
        });

        // ---- rincian platform di popus toko ----------------------------------
        // Sampah tidak menghapus baris asal_order: nota lama menunjuk id-nya.
        // Yang berubah hanya status pilihan, dan itu pun menunggu jeda yang sama
        // supaya salah pencet masih bisa diurungkan
        var daftarPlat = document.getElementById('tk-plat-daftar');
        var pilihPlat  = document.getElementById('tk-platform');
        var alamatPlat = '<?= site_url('admin/checklist/platform-ubah') ?>';

        var kirimPlat = function (id, aktif, selesai) {
            if (! window.jQuery) {
                // tanpa jQuery: tidak ada jalan mengubah tanpa memuat ulang,
                // dan halaman dimuat ulang pun statusnya sudah tersimpan
                window.location.reload();

                return;
            }

            window.jQuery.post(alamatPlat, { id: id, aktif: aktif })
                .done(function (balasan) { selesai(balasan || null); })
                .fail(function () { selesai(null); });
        };

        // daftar pilihan di kolom platform ikut bergerak bersama rinciannya:
        // label yang disembunyikan hilang dari pilihan, yang dipakai lagi muncul
        // kembali. Kolomnya membaca ulang penampilannya lewat event change
        var opsiPlat = function (label, ada) {
            if (! pilihPlat) {
                return;
            }

            var penanda  = pilihPlat.querySelector('option[value="__baru__"]');
            var isian    = document.getElementById('tk-platform-baru');
            var terpilih = pilihPlat.value === label;

            Array.prototype.slice.call(pilihPlat.options).forEach(function (o) {
                if (o.value === label) {
                    pilihPlat.removeChild(o);
                }
            });

            if (ada) {
                var opsi = document.createElement('option');
                opsi.value = label;
                opsi.textContent = label;

                // selalu di atas penanda "Tambah platform", supaya pilihan itu
                // tetap yang paling bawah
                if (penanda) {
                    pilihPlat.insertBefore(opsi, penanda);
                } else {
                    pilihPlat.appendChild(opsi);
                }

                // form yang memegang nama ini lewat isian ketik dikembalikan ke
                // pilihan biasa
                if (isian && penanda && pilihPlat.value === penanda.value && isian.value.trim() === label) {
                    isian.value = '';
                    pilihPlat.value = label;
                }
            } else if (terpilih && isian && penanda) {
                // yang disembunyikan justru sedang dipakai form: namanya tetap
                // terbaca lewat isian ketik, bukan hilang dari toko ini
                isian.value = label;
                pilihPlat.value = penanda.value;
            }

            pilihPlat.dispatchEvent(new Event('change'));
        };

        var tandaiPlat = function (baris, aktif) {
            var buang = baris.querySelector('[data-plat-buang]');
            var pakai = baris.querySelector('[data-plat-pakai]');
            var tanda = baris.querySelector('.ckl-plat-mati');
            var pilih = baris.querySelector('[data-plat-pilih]');

            baris.classList.toggle('mati', ! aktif);

            if (buang) { buang.classList.toggle('d-none', ! aktif); }
            if (pakai) { pakai.classList.toggle('d-none', aktif); }
            if (tanda) { tanda.classList.toggle('d-none', aktif); }

            // baris nonaktif tidak bisa dipilih lagi dari rinciannya
            if (pilih) { pilih.disabled = ! aktif; }
        };

        var ubahPlat = function (baris, aktif) {
            var id    = baris.getAttribute('data-id');
            var label = baris.getAttribute('data-label') || '';

            baris.classList.add('ckl-plat-tunggu');

            kirimPlat(id, aktif ? 1 : 0, function (balasan) {
                baris.classList.remove('ckl-plat-tunggu');

                if (! balasan || ! balasan.ok) {
                    pesan((balasan && balasan.pesan)
                        || 'Platform gagal diubah. Periksa sambungan lalu coba lagi.', 'Tutup', null, null);

                    return;
                }

                tandaiPlat(baris, aktif);
                opsiPlat(label, aktif);
            });
        };

        if (daftarPlat) {
            daftarPlat.addEventListener('click', function (peristiwa) {
                var tombol = peristiwa.target.closest
                    ? peristiwa.target.closest('[data-plat-buang], [data-plat-pakai]')
                    : null;
                var baris  = tombol ? tombol.closest('.ckl-plat-baris') : null;

                if (! baris || baris.classList.contains('ckl-plat-tunggu')) {
                    return;
                }

                peristiwa.preventDefault();

                // memakai lagi tidak perlu ditawari urungkan: hasilnya langsung
                // terlihat dan bisa dibalik dengan sampah yang sama
                if (tombol.hasAttribute('data-plat-pakai')) {
                    ubahPlat(baris, true);

                    return;
                }

                var label = baris.getAttribute('data-label') || '';

                baris.classList.add('ckl-plat-tunggu');

                pesan('Platform ' + label + ' disembunyikan.', 'Urungkan', function () {
                    baris.classList.remove('ckl-plat-tunggu');
                }, function () {
                    ubahPlat(baris, false);
                });
            });
        }
    }());
        </script>
    <?php endif; ?>

    <?php if ($toko !== []) : ?>
        <!-- popus catatan baru: toko + catatan, dua-duanya wajib. Tidak ada pilihan
             tanggal karena catatan selalu untuk hari ini -->
        <div class="modal fade" id="modalCatatan" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <?= form_open('admin/checklist/catatan') ?>
                        <div class="modal-header">
                            <h5 class="modal-title">Buat catatan</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label small fw-bold" for="ct-toko">Toko <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="ct-toko" name="toko_id" required>
                                <?php $pertama = true; ?>
                                <?php foreach ($toko as $id_toko => $t) : ?>
                                    <option value="<?= (int) $id_toko ?>" <?= $pertama ? 'selected' : '' ?>><?= esc($t['nama']) ?></option>
                                    <?php $pertama = false; ?>
                                <?php endforeach; ?>
                            </select>

                            <?php if ($kelola) : ?>
                                <?php
                                // daftar pemegang tiap toko ikut ditulis sekali di atribut
                                // select ini; berpindah toko tinggal mengisi ulang dropdown,
                                // tanpa request tambahan ke server
                                $isi = [];

                                foreach ($pemegang as $id_toko => $daftar) {
                                    foreach ($daftar as $o) {
                                        $isi[(int) $id_toko][] = [(int) $o['id'], (string) $o['nama'], (string) $o['level']];
                                    }
                                }
                                ?>
                                <label class="form-label small fw-bold mt-2" for="ct-user">Dikerjakan oleh <span class="text-danger" id="ct-user-wajib">*</span></label>
                                <select class="form-select form-select-sm" id="ct-user" name="user_id"
                                    data-pemegang="<?= esc(json_encode($isi), 'attr') ?>">
                                    <option value="">Memuat…</option>
                                </select>
                                <div class="form-text small mt-1" id="ct-user-kosong" hidden>
                                    Toko ini belum punya pemegang. Tautkan orangnya dulu di tab Kelola Toko.
                                </div>
                            <?php endif; ?>

                            <label class="form-label small fw-bold mt-2" for="ct-catatan">Catatan <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-sm" id="ct-catatan" name="catatan" rows="3"
                                maxlength="200" required placeholder="mis. sudah balas 12 chat pembeli dan rekap pesanan jam 10.00"></textarea>

                            <?php if ($level === 'cs') : ?>
                                <div class="form-text small mt-1">
                                    Catatan dari kamu terhitung tugas mandiri: langsung masuk daftar &quot;Sudah dikerjakan&quot;.
                                </div>
                            <?php else : ?>
                                <div class="form-text small mt-1">
                                    Catatan dari Admin/Superadmin masuk daftar &quot;Belum dikerjakan&quot; sampai CS menuliskan hasilnya.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-plus me-1"></i> Simpan</button>
                        </div>
                    <?= form_close() ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="modalSelesai" tabindex="-1" aria-hidden="true"
        data-base="<?= esc(site_url('admin/checklist/catatan-selesai')) ?>">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="" id="formSelesai">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_hasil" id="sl-hasil" value="0">
                    <div class="modal-header">
                        <h5 class="modal-title">Selesaikan catatan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="ckl-selesai-judul" id="sl-judul"></div>

                        <label class="form-label small fw-bold mt-2" for="sl-catatan">Catatan kerja <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="sl-catatan" name="catatan" rows="3"
                            maxlength="500" required placeholder="mis. sudah dicek satu-satu, stok aman, tidak ada komplain"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-circle-check me-1"></i> Tandai selesai</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var form = document.querySelector('#modalCatatan form');

        if (! form) {
            return;
        }

        var toko = document.getElementById('ct-toko');
        var user = document.getElementById('ct-user');

        // tombol simpan dimatikan begitu form dikirim: dua klik beruntun pernah
        // melahirkan dua catatan yang sama persis dalam enam detik
        form.addEventListener('submit', function () {
            var tombol = form.querySelector('button[type="submit"]');

            if (tombol) {
                tombol.disabled = true;
            }
        });

        if (! user) {
            return;
        }

        // daftar pemegang tiap toko sudah ikut dibawa di atribut select, jadi
        // berpindah toko cukup mengisi ulang dropdownnya tanpa request tambahan
        var peta = {};

        try {
            peta = JSON.parse(user.getAttribute('data-pemegang') || '{}');
        } catch (e) {
            peta = {};
        }

        var kosong = document.getElementById('ct-user-kosong');
        var wajib = document.getElementById('ct-user-wajib');

        var isiUser = function () {
            var punya = peta[toko ? toko.value : ''] || [];

            user.innerHTML = '';

            if (punya.length === 0) {
                user.appendChild(new Option('Belum ada pemegang di toko ini', ''));
                user.disabled = true;
                user.removeAttribute('required');
                wajib.hidden = true;
                kosong.hidden = false;

                return;
            }

            user.disabled = false;
            user.setAttribute('required', 'required');
            user.appendChild(new Option('Pilih orang', ''));

            punya.forEach(function (o) {
                user.appendChild(new Option(o[1] + ' (' + o[2] + ')', o[0]));
            });

            wajib.hidden = false;
            kosong.hidden = true;
        };

        if (toko) {
            toko.addEventListener('change', isiUser);
        }

        // popus tidak pernah di-reset saat ditutup, jadi daftarnya diisi ulang
        // tiap kali dibuka — kalau tidak, pilihan toko terakhir tertinggal di situ
        document.getElementById('modalCatatan').addEventListener('show.bs.modal', isiUser);

        isiUser();
    }());

    (function () {
        var modal = document.getElementById('modalSelesai');

        if (! modal) {
            return;
        }

        // tokonya tidak ditanya lagi di sini: catatannya sudah tahu milik toko mana,
        // yang dibutuhkan tinggal apa yang sudah dikerjakan
        modal.addEventListener('show.bs.modal', function (event) {
            var pemicu = event.relatedTarget;

            if (! pemicu) {
                return;
            }

            var run = pemicu.getAttribute('data-run') || '0';

            modal.querySelector('#formSelesai').action = modal.getAttribute('data-base') + '/' + run;
            modal.querySelector('#sl-hasil').value     = pemicu.getAttribute('data-hasil') || '0';
            modal.querySelector('#sl-judul').textContent = pemicu.getAttribute('data-judul') || '';
            modal.querySelector('#sl-catatan').value   = '';
        });
    }());
    </script>

</div>

<?= $this->endSection() ?>
