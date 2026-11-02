<?php
/**
 * @var string      $title  Halaman judul
 * @var ?string     $sukses Flash-message keberhasilan
 * @var ?string     $gagal  Flash-message kegagalan
 * @var array       $errors Validasi errors (kolom => pesan)
 * @var array       $isian  Nilai form sebelumnya
 */
$pager   = \Config\Services::pager();
$session = \Config\Services::session();

// isian + error datang dari flashdata controller: ['kode' => 'pesan', ...]
// kegagalan dari modal sunting dikenali dari kolom id yang ikut terkirim;
// galatnya jangan ditempel ke form tambah supaya tidak menyesatkan
$isian        = $isian ?? [];
$dari_sunting = ($isian['id'] ?? '') !== '';
$err          = static fn (string $kolom): ?string => ! $dari_sunting && isset($errors[$kolom]) ? (string) $errors[$kolom] : null;
$isi          = static fn (string $kolom, string $awalan = ''): string => (string) ($isian[$kolom] ?? $awalan);

// Popup dibuka lagi lewat JavaScript, jadi penanda dan isian sebelumnya dilewat
// sebagai atribut data di HTML, bukan disisipkan ke dalam string JS: skrip halaman
// dikemas Packer, jadi nilai PHP di dalamnya sulit dipastikan.
$buka_tambah = (! $dari_sunting && $errors !== []) ? '1' : '0';
$juragan_isi = json_encode(array_values(array_map('intval', (array) ($isian['juragan'] ?? []))));
$per_halaman = (int) config('Pager')->perPage;
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
/* Tombol mata dulu menempel sebagai kotak .input-group-text sendiri di samping
   kolom kata sandi; sekarang dia melayang di dalam field, sisi kanan, supaya
   kolomnya terbaca satu kesatuan seperti kolom cari. */
.sandi-merged {
    position: relative;
}
.sandi-merged .form-control {
    padding-right: 2.35rem !important;
}
.sandi-merged .sandi-lihat {
    position: absolute;
    top: 50%;
    right: 0.6rem;
    transform: translateY(-50%);
    border: 0;
    background: transparent;
    padding: 0;
    line-height: 1;
    cursor: pointer;
    color: #667085;
}
.sandi-merged .sandi-lihat:hover {
    color: #101828;
}
/* Username tidak bisa diganti, jadi kolomnya memang dikunci. Tapi style.css punya
   aturan global yang membuat SEMUA field nonaktif terbaca "mati" — latar abu,
   teks pudar, border pucat, label ikut meredup, semuanya !important — sehingga di
   popup sunting kolom ini beda rupa dengan kolom kata sandinya sendiri dan dengan
   popup Tambah Pengguna. Warnanya dikembalikan ke putih seperti kolom isian biasa;
   kursor not-allowed tetap tinggal sebagai penanda kolomnya tidak bisa diketik.
   Butuh !important karena aturan global itu juga memakai !important. */
#username_:disabled {
    background-color: #ffffff !important;
    border-color: #d0d5dd !important;
    color: #101828 !important;
}
#modalSuntingPengguna label[for="username_"] {
    color: #344054 !important;
}
/* Lebar kolom cari pengguna. Dulu inline style="width:18rem"; dipindah ke sini
   supaya di ponsel bisa dilenturkan (inline style kalah oleh !important, kelas
   tidak). */
.cari-pengguna {
    width: 18rem;
}
@media (max-width: 767.98px) {
    /* Tombol "Tambah Pengguna" di ponsel tinggal ikon dan harus duduk SEBELAH
       kolom cari: yang menyerap sisa ruang adalah kolom carinya, bukan tombolnya */
    .cari-pengguna {
        flex: 1 1 auto;
        width: auto;
        min-width: 0;
    }
    /* Popus tambah / sunting pengguna memakan 93% tinggi layar (747px di
       360x800, sisa 27px saja di atas dan bawah) sehingga terasa terlalu
       panjang. Dibatasi 70vh — dialog-nya sudah modal-dialog-scrollable, jadi
       yang bergulir adalah body-nya dan header + tombol tetap kelihatan. */
    #modalTambahPengguna .modal-content,
    #modalSuntingPengguna .modal-content {
        max-height: 70vh;
    }
    /* jarak dalam body ikut dipangkas: 16px -> 12px atas-bawah */
    #modalTambahPengguna .modal-body,
    #modalSuntingPengguna .modal-body {
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
    }
    /* Jarak antar isian ikut dipangkas supaya popusnya benar-benar lebih pendek,
       bukan cuma dimasukkan ke dalam guliran. .mb-3/.my-4 Bootstrap !important. */
    #modalTambahPengguna .modal-body > .mb-3,
    #modalSuntingPengguna .modal-body > .mb-3 {
        margin-bottom: 0.625rem !important;
    }
    #modalTambahPengguna .modal-body > h6,
    #modalSuntingPengguna .modal-body > h6 {
        margin-bottom: 0.5rem !important;
    }
    #modalTambahPengguna .modal-body > hr,
    #modalSuntingPengguna .modal-body > hr {
        margin-top: 0.75rem !important;
        margin-bottom: 0.75rem !important;
    }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">

    <h1 class="h3 mt-5">Pengaturan Pengguna</h1>

    <?php if ($sukses !== null) { ?>
        <div class="alert alert-success py-2" data-flash><?= esc($sukses) ?></div>
    <?php } ?>
    <?php if ($gagal !== null) { ?>
        <div class="alert alert-danger py-2" data-flash><?= esc($gagal) ?></div>
    <?php } ?>
    <?php if ($dari_sunting && $errors !== []) { ?>
        <div class="alert alert-danger py-2">
            <strong class="d-block">Perubahan belum tersimpan.</strong>
            <?php foreach ($errors as $pesan) { ?>
                <div class="small"><?= esc($pesan) ?></div>
            <?php } ?>
        </div>
    <?php } ?>

    <div class="card mb-5">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="cari-merged cari-pengguna">
                <span class="cari-ikon"><i class="fal fa-search"></i></span>
                <input type="search" class="form-control form-control-sm" id="cariPengguna" placeholder="nama, username, email, level" autocomplete="off">
            </div>
            <span class="text-muted small ms-auto d-none d-md-block jumlah-pengguna">memuat ...</span>
            <button type="button" class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#modalTambahPengguna"
                aria-label="Tambah Pengguna" title="Tambah Pengguna">
                <i class="fal fa-user-plus"></i><span class="d-none d-md-inline"> Tambah Pengguna</span>
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Pengguna</th>
                            <th scope="col" style="width: 9rem">Level</th>
                            <th scope="col" style="width: 9rem">Status</th>
                            <th scope="col" class="text-end" style="width: 10rem">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="listUsers"></tbody>
                </table>
            </div>
            <!-- versi ponsel: jumlah pengguna turun ke bawah, tepat di atas
                 pagination; yang di kepala kartu hanya tampil di >=768px -->
            <div class="small text-muted text-end d-md-none jumlah-pengguna mt-2">memuat ...</div>
            <div id="pagPengguna" data-per-halaman="<?= $per_halaman ?>"></div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('modal') ?>
<div class="modal fade" id="modalTambahPengguna" data-bs-backdrop="static" data-buka="<?= $buka_tambah ?>" tabindex="-1" aria-labelledby="modalTambahPenggunaLabel" aria-hidden="true">
    <!-- kelas pembungkus dialog dipasang pada elemen formulirnya sendiri, bukan pada div
         di atasnya: baru dengan begitu badan form yang menggulir sementara judul dan
         tombol tetap terlihat -->
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <?= form_open('admin/settings/save_pengguna', ['class' => 'modal-content']); ?>
            <div class="modal-header">
                <h5 class="modal-title" id="modalTambahPenggunaLabel"><i class="fal fa-user-plus"></i> Tambah Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 class="text-uppercase small text-muted mb-3">Akun</h6>
                <div class="mb-3 row gx-2">
                    <div class="col-6">
                        <?= form_label('Username', 'username', ['class' => 'form-label']); ?>
                        <?= form_input([
                            'class'       => 'form-control' . ($err('username') ? ' is-invalid' : ''),
                            'name'        => 'username',
                            'id'          => 'username',
                            'required'    => '',
                            'placeholder' => 'username',
                            'value'       => $isi('username'),
                        ]); ?>
                        <?php if ($err('username')) { ?>
                            <div class="invalid-feedback d-block"><?= esc($err('username')) ?></div>
                        <?php } ?>
                    </div>
                    <div class="col-6">
                        <?= form_label('Password', 'password', ['class' => 'form-label']); ?>
                        <div class="sandi-merged">
                            <?= form_password([
                                'class'       => 'form-control' . ($err('password') ? ' is-invalid' : ''),
                                'name'        => 'password',
                                'id'          => 'password',
                                'required'    => '',
                                'placeholder' => 'minimal 6 karakter',
                            ]); ?>
                            <button type="button" class="sandi-lihat" data-lihat="password" aria-label="Tampilkan atau sembunyikan kata sandi"><i class="fal fa-eye"></i></button>
                        </div>
                        <?php if ($err('password')) { ?>
                            <div class="invalid-feedback d-block"><?= esc($err('password')) ?></div>
                        <?php } ?>
                    </div>
                </div>
                <div class="mb-3">
                    <?= form_label('Nama Lengkap', 'nama', ['class' => 'form-label']); ?>
                    <?= form_input([
                        'class'       => 'form-control' . ($err('nama') ? ' is-invalid' : ''),
                        'name'        => 'nama',
                        'id'          => 'nama',
                        'required'    => '',
                        'placeholder' => 'nama lengkap pengguna',
                        'value'       => $isi('nama'),
                    ]); ?>
                    <?php if ($err('nama')) { ?>
                        <div class="invalid-feedback d-block"><?= esc($err('nama')) ?></div>
                    <?php } ?>
                </div>
                <div class="mb-3">
                    <?= form_label('Email', 'email', ['class' => 'form-label']); ?>
                    <?= form_input([
                        'class'       => 'form-control' . ($err('email') ? ' is-invalid' : ''),
                        'type'        => 'email',
                        'name'        => 'email',
                        'id'          => 'email',
                        'required'    => '',
                        'placeholder' => 'alamat@email',
                        'value'       => $isi('email'),
                    ]); ?>
                    <?php if ($err('email')) { ?>
                        <div class="invalid-feedback d-block"><?= esc($err('email')) ?></div>
                    <?php } ?>
                    <div class="form-text">Surel dipakai untuk tautan reset kata sandi.</div>
                </div>
                <hr class="my-4">
                <h6 class="text-uppercase small text-muted mb-3">Akses</h6>
                <div class="mb-3 row gx-2">
                    <div class="col-6">
                        <?= form_label('Level', 'level', ['class' => 'form-label']); ?>
                        <?php
                        // viewer dan reseller sengaja tidak ditawarkan lagi untuk akun
                        // baru: tidak ada satu pun hak akses yang menunjuk namanya
                        $options_level = [
                            'superadmin' => 'Superadmin',
                            'admin'      => 'Admin',
                            'cs'         => 'CS',
                        ];

                        echo form_dropdown('level', $options_level, $isi('level', 'cs'), ['class' => 'form-select' . ($err('level') ? ' is-invalid' : ''), 'id' => 'level', 'required' => '']);
                        ?>
                        <?php if ($err('level')) { ?>
                            <div class="invalid-feedback d-block"><?= esc($err('level')) ?></div>
                        <?php } ?>

                    </div>
                    <div class="col-6">
                        <?= form_label('Status', 'status', ['class' => 'form-label']); ?>
                        <?php
                        $options_status = [
                            'pending'  => 'Pending',
                            'inactive' => 'Tidak Aktif',
                            'active'   => 'Aktif',
                            'blocked'  => 'Blokir',
                        ];

                        echo form_dropdown('status', $options_status, $isi('status', 'active'), ['class' => 'form-select' . ($err('status') ? ' is-invalid' : ''), 'id' => 'status', 'required' => '']);
                        ?>
                        <?php if ($err('status')) { ?>
                            <div class="invalid-feedback d-block"><?= esc($err('status')) ?></div>
                        <?php } ?>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex align-items-center gap-3 mb-1">
                        <?= form_label('Juragan', 'pilihJuragan', ['class' => 'form-label mb-0']); ?>
                        <span class="ms-auto d-flex gap-3">
                            <button type="button" class="btn btn-sm btn-link p-0 small fw-semibold text-nowrap"
                                data-pilih-semua="pilihJuragan" title="Centang semua juragan tanpa terkecuali">Pilih semua juragan</button>
                            <button type="button" class="btn btn-sm btn-link p-0 small text-muted text-nowrap"
                                data-kosongkan="pilihJuragan" title="Buang semua centang">Kosongkan</button>
                        </span>
                    </div>
                    <div class="gulir-pilihan border rounded-3 p-2 d-flex flex-wrap gap-2<?= $err('juragan') ? ' border-danger' : '' ?>" id="pilihJuragan" data-juragan="<?= $juragan_isi ?>">
                        <div class="text-muted small p-1">memuat daftar juragan ...</div>
                    </div>
                    <?php if ($err('juragan')) { ?>
                        <div class="invalid-feedback d-block"><?= esc($err('juragan')) ?></div>
                    <?php } ?>
                    <div class="form-text">klik untuk memilih, boleh lebih dari satu.</div>
                    <div class="form-text text-danger d-none" data-hint-cs>Level CS wajib punya minimal satu juragan.</div>
                </div>
                <?php /* hak baca lintas toko: khusus CS, dipilih Superadmin. Tanpa hak
                   ini katalog CS hanya berisi juragan yang disematkan ke akunnya. */ ?>
                <div class="form-check mb-2" data-hak-cs data-hak-produk>
                    <?= form_checkbox([
                        'name'    => 'lihat_semua_produk',
                        'id'      => 'lihatSemuaProduk',
                        'value'   => '1',
                        'class'   => 'form-check-input',
                        'checked' => ($isi('lihat_semua_produk') === '1'),
                    ]); ?>
                    <?= form_label('Boleh melihat katalog produk semua juragan', 'lihatSemuaProduk', ['class' => 'form-check-label']); ?>
                    <div class="form-text">Khusus CS: tanpa hak ini, katalog yang terlihat hanya dari juragan yang disematkan ke akun ini.</div>
                </div>
                <?php /* hak sunting transaksi: khusus CS, dipilih Superadmin. Yang
                   diberi hak membuka Tracking Transaksi miliknya Admin untuk nota
                   toko yang tertaut ke akunnya. */ ?>
                <div class="form-check mb-3" data-hak-cs data-hak-transaksi>
                    <?= form_checkbox([
                        'name'    => 'sunting_transaksi',
                        'id'      => 'suntingTransaksi',
                        'value'   => '1',
                        'class'   => 'form-check-input',
                        'checked' => ($isi('sunting_transaksi') === '1'),
                    ]); ?>
                    <?= form_label('Boleh menyunting transaksi (mirip Admin)', 'suntingTransaksi', ['class' => 'form-check-label']); ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fal fa-save"></i> Tambahkan</button>
            </div>
        <?= form_close(); ?>
    </div>
</div>

<div class="modal fade" id="modalSuntingPengguna" data-backdrop="static" tabindex="-1" aria-labelledby="modalSuntingPenggunaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <?= form_open('admin/settings/update_pengguna', ['class' => 'modal-content'], ['id' => '']); ?>
            <div class="modal-header">
                <h5 class="modal-title" id="modalSuntingPenggunaLabel">Update Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">

                </button>
            </div>
            <div class="modal-body">
                <h6 class="text-uppercase small text-muted mb-3">Akun</h6>

                <div class="mb-3 row gx-2">
                    <div class="col-6">
                        <?= form_label('Username', 'username_', ['class' => 'form-label']); ?>
                        <?= form_input('username', '', ['class' => 'form-control', 'id' => 'username_', 'required' => '', 'placeholder' => 'username', 'disabled' => '']); ?>
                    </div>
                    <div class="col-6">
                        <?= form_label('Password', 'password_', ['class' => 'form-label']); ?>
                        <div class="sandi-merged">
                            <?= form_password('password', '', ['class' => 'form-control', 'id' => 'password_', 'placeholder' => 'password - isi jika diganti']); ?>
                            <button type="button" class="sandi-lihat" data-lihat="password_" aria-label="Tampilkan atau sembunyikan kata sandi"><i class="fal fa-eye"></i></button>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <?= form_label('Nama Lengkap', 'nama_', ['class' => 'form-label']); ?>
                    <?= form_input('nama', '', ['class' => 'form-control', 'id' => 'nama_', 'required' => '', 'placeholder' => 'nama lengkap pengguna']); ?>
                </div>
                <div class="mb-3">
                    <?= form_label('Email', 'email_', ['class' => 'form-label']); ?>
                    <?= form_input('email', '', ['class' => 'form-control', 'id' => 'email_', 'required' => '', 'placeholder' => 'alamat@email'], 'email'); ?>
                </div>
                <hr class="my-4">
                <h6 class="text-uppercase small text-muted mb-3">Akses</h6>
                <div class="mb-3 row gx-2">
                    <div class="col-6">
                        <?= form_label('Level', 'level_', ['class' => 'form-label']); ?>
                        <?php
                        // Level lama (viewer) tidak dipasang di sini: daftar penggunanya
                        // dibangun JavaScript, jadi opsi untuk akun yang sudah menyandang
                        // level itu ditambahkan show.bs.modal di bawah — tanpa opsi itu,
                        // kotak ini jadi kosong dan menyimpan suntingan apa pun akan
                        // mengubah level akun tersebut tanpa sengaja.
                        $options_level = [
                            'superadmin' => 'Superadmin',
                            'admin'      => 'Admin',
                            'cs'         => 'CS',
                        ];

                        echo form_dropdown('level', $options_level, 'cs', ['class' => 'form-select', 'id' => 'level_', 'required' => '']);
                        ?>

                    </div>
                    <div class="col-6">
                        <?= form_label('Status', 'status_', ['class' => 'form-label']); ?>
                        <?php
                        $options_status = [
                            'pending'  => 'Pending',
                            'inactive' => 'Tidak Aktif',
                            'active'   => 'Aktif',
                            'blocked'  => 'Blokir',
                        ];

                        echo form_dropdown('status', $options_status, 'active', ['class' => 'form-select', 'id' => 'status_', 'required' => '']);
                        ?>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex align-items-center gap-3 mb-1">
                        <?= form_label('Juragan', 'pilihJuraganSunting', ['class' => 'form-label mb-0']); ?>
                        <span class="ms-auto d-flex gap-3">
                            <button type="button" class="btn btn-sm btn-link p-0 small fw-semibold text-nowrap"
                                data-pilih-semua="pilihJuraganSunting" title="Centang semua juragan tanpa terkecuali">Pilih semua juragan</button>
                            <button type="button" class="btn btn-sm btn-link p-0 small text-muted text-nowrap"
                                data-kosongkan="pilihJuraganSunting" title="Buang semua centang">Kosongkan</button>
                        </span>
                    </div>
                    <div class="gulir-pilihan border rounded-3 p-2 d-flex flex-wrap gap-2" id="pilihJuraganSunting">
                        <div class="text-muted small p-1">memuat daftar juragan ...</div>
                    </div>
                    <div class="form-text">klik untuk memilih, boleh lebih dari satu.</div>
                    <div class="form-text text-danger d-none" data-hint-cs>Level CS wajib punya minimal satu juragan.</div>
                </div>
                <div class="form-check mb-2" data-hak-cs data-hak-produk>
                    <?= form_checkbox([
                        'name'  => 'lihat_semua_produk',
                        'id'    => 'lihatSemuaProduk_',
                        'value' => '1',
                        'class' => 'form-check-input',
                    ]); ?>
                    <?= form_label('Boleh melihat katalog produk semua juragan', 'lihatSemuaProduk_', ['class' => 'form-check-label']); ?>
                    <div class="form-text">Khusus CS: tanpa hak ini, katalog yang terlihat hanya dari juragan yang disematkan ke akun ini.</div>
                </div>
                <div class="form-check mb-3" data-hak-cs data-hak-transaksi>
                    <?= form_checkbox([
                        'name'  => 'sunting_transaksi',
                        'id'    => 'suntingTransaksi_',
                        'value' => '1',
                        'class' => 'form-check-input',
                    ]); ?>
                    <?= form_label('Boleh menyunting transaksi (mirip Admin)', 'suntingTransaksi_', ['class' => 'form-check-label']); ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fal fa-save"></i> Simpan</button>
            </div>
        <?= form_close(); ?>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<?php
$current_user_id       = $session->get('id');
$link_api_juragan      = site_url('api/juragan/by_user/');
$link_api_juragan_all  = site_url('api/juragan/all/');
$link_api_pengguna_all = site_url('api/pengguna/all/');
$link_api_relasi       = site_url('api/juragan/by_user');
$link_invoice          = site_url('admin/invoices/lihat/');

$js = <<< JS
    $(function() {
    	'use strict';
    	// sidebar

    	var juragan = document.getElementById('juragan');
        if(juragan) {
            juragan.addEventListener('show.bs.offcanvas', function () {
                var id = {$current_user_id};
                $('#listLi').empty();
                $.getJSON('{$link_api_juragan}', { id: id }, function(b){
                    var a=[];
                    // akun yang cuma pegang satu toko tidak perlu memilih
                    var ada = 0;
                    $.each(b[id].juragan, function() { ada++; });
                    if (ada > 1) {
                        a.push('<a class="list-group-item text-light list-group-item-action" href="{$link_invoice}semua"><i class="fal fa-user-circle"></i> Semua Juragan</a>');
                    }

                    $.each(b[id].juragan,function(c,b){
                        a.push('<a href="{$link_invoice}'+b.slug+'" class="list-group-item text-light list-group-item-action"><i class="fal fa-user-circle"></i> '+b.nama+'</a>');
                    }),

                    $(a.join('')).appendTo('#listLi');
                });
            });
        }


    	// daftar penuh sudah dikirim sekali, jadi pencarian dan pagination cukup di sini
    	var semuaPengguna = [];
    	var kunciCari     = '';
    	var halaman       = 1;
    	var wadahPag      = document.getElementById('pagPengguna');
    	var perHalaman    = parseInt(wadahPag.getAttribute('data-per-halaman'), 10);

    	var lokaLevel = {
    		superadmin: 'bg-dark',
    		admin: 'bg-primary',
    		cs: 'bg-secondary',
    		viewer: 'bg-light text-dark border'
    	};
    	var lokaStatus = {
    		active: 'bg-success',
    		pending: 'bg-warning text-dark',
    		inactive: 'bg-light text-dark border',
    		blocked: 'bg-danger'
    	};

    	function aman(teks) {
    		var peta = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

    		return ('' + teks).replace(/[&<>"']/g, function (c) { return peta[c]; });
    	}

    	var semuaJuragan = [];

    	// api juragan menjawab sebagai objek yang dipetakan dari id, bukan daftar,
    	// jadi diratakan dulu supaya bisa dihitung dan diurutkan seperti array
    	function keDaftar(bahan) {
    			var daftar = [];

    			for (var i in bahan) {
    					if (bahan[i] && bahan[i].id) { daftar.push(bahan[i]); }
    			}

    			return daftar;
    	}

    	function punyaId(daftar, id) {
    			for (var i = 0; i < daftar.length; i++) {
    					if ('' + daftar[i].id === '' + id) { return true; }
    			}

    			return false;
    	}

    	// satu pil = satu <input name="juragan[]">: jalur simpan controller tidak berubah,
    	// dan kelas btn-check dari Bootstrap yang mewarnai pilnya jadi tidak perlu tombol CTRL.
    	// awalan dipakai di id supaya dua pemilih di halaman ini (tambah & sunting) tidak
    	// berebut id yang sama — label[for] hanya menunjuk elemen pertama di dokumen, jadi
    	// tanpa pembeda ini pil di modal sunting mengklik checkbox modal tambah dan pilnya
    	// sendiri tidak pernah berubah keadaan.
    	function pilJuragan(item, terpilih, awalan) {
    			var id = 'pil-' + awalan + '-' + item.id;

    			return '<input class="btn-check" type="checkbox" name="juragan[]" value="' + aman(item.id) + '"'
    				+ ' id="' + id + '" autocomplete="off"' + (terpilih ? ' checked' : '') + '>'
    				+ '<label class="btn btn-sm rounded-pill btn-outline-secondary" for="' + id + '">' + aman(item.nama) + '</label>';
    	}

    	function gambarJuragan(wadah, punya, awalan) {
    			var daftar = semuaJuragan.slice();

    			// juragan milik akun yang sudah tidak ada di daftar master tetap ikut ditampilkan,
    			// kalau tidak, relasi yang sedang disunting akan ikut terhapus saat form disimpan
    			for (var i = 0; i < punya.length; i++) {
    					if (punya[i].nama !== '' && !punyaId(daftar, punya[i].id)) { daftar.push(punya[i]); }
    			}

    			var html = [];
    			for (var k = 0; k < daftar.length; k++) {
    					html.push(pilJuragan(daftar[k], punyaId(punya, daftar[k].id), awalan));
    			}

    			wadah.html(html.length ? html.join('') : '<div class="text-muted small p-1">belum ada juragan</div>');
    	}

    	$.getJSON('{$link_api_juragan_all}', function(b){
    			semuaJuragan = keDaftar(b);

    			// popup dibuka lagi setelah validasi gagal: pilihan sebelumnya ikut dipulihkan
    			var dipilih = JSON.parse($('#pilihJuragan').attr('data-juragan') || '[]');
    			var punya   = [];
    			for (var i = 0; i < dipilih.length; i++) { punya.push({ id: dipilih[i], nama: '' }); }

    			gambarJuragan($('#pilihJuragan'), punya, 'tambah');
    			// pilihan yang dipulihkan baru ada sekarang, jadi pesannya dihitung ulang
    			sinkronHintLevel(document.getElementById('modalTambahPengguna'));
    	});

    	// daftar tokonya bisa berpuluh pil; tombol ini mencontreng (atau mengosongkan)
    	// seluruh isi satu wadah supaya tidak perlu klik satu-satu. Name field-nya tetap
    	// juragan[], jadi jalur simpan controller tidak ikut berubah.
    	$(document).on('click', '[data-pilih-semua]', function () {
    			var wadah = $('#' + $(this).data('pilih-semua'));

    			wadah.find('input[type="checkbox"]').prop('checked', true);
    			sinkronHintLevel(wadah.closest('.modal').get(0));
    	});

    	$(document).on('click', '[data-kosongkan]', function () {
    			var wadah = $('#' + $(this).data('kosongkan'));

    			wadah.find('input[type="checkbox"]').prop('checked', false);
    			sinkronHintLevel(wadah.closest('.modal').get(0));
    	});

    	function saringPengguna() {
    		var k = $.trim(kunciCari).toLowerCase();
    		var hasil = [];

    		for (var i = 0; i < semuaPengguna.length; i++) {
    			var u = semuaPengguna[i];
    			var teks = [u.nama, u.username, u.email, u.level, u.status].join(' ').toLowerCase();

    			if (k === '' || teks.indexOf(k) !== -1) { hasil.push(u); }
    		}

    		return hasil;
    	}

    	function barisPengguna(u) {
    		return '<tr>'
    			+ '<td><div class="fw-semibold">' + aman(u.nama)
    			+ '<span class="ms-2 badge rounded-pill bg-light text-dark border fw-normal">' + aman(u.username) + '</span></div>'
    			+ '<div class="text-muted small">' + aman(u.email) + '</div></td>'
    			+ '<td><span class="badge rounded-pill ' + (lokaLevel[u.level] || 'bg-light text-dark border') + '">' + aman(u.level) + '</span></td>'
    			+ '<td><span class="badge rounded-pill ' + (lokaStatus[u.status] || 'bg-light text-dark border') + '">' + aman(u.status) + '</span></td>'
    			+ '<td class="text-end text-nowrap">'
    			+ '<button type="button" class="btn btn-sm btn-outline-secondary me-1" title="Sunting ' + aman(u.nama) + '" aria-label="Sunting ' + aman(u.nama) + '" data-bs-toggle="modal" data-bs-target="#modalSuntingPengguna"'
    			+ ' data-id="' + aman(u.id) + '" data-nama="' + aman(u.nama) + '" data-email="' + aman(u.email) + '"'
    			+ ' data-username="' + aman(u.username) + '" data-level="' + aman(u.level) + '" data-status="' + aman(u.status) + '"'
    			+ ' data-lihat-semua="' + aman(u.lihat_semua_produk || 0) + '"'
    			+ ' data-sunting-transaksi="' + aman(u.sunting_transaksi || 0) + '"><i class="fal fa-pencil"></i></button>'
    			+ '<button type="button" class="btn btn-sm btn-outline-danger" title="Hapus ' + aman(u.nama) + '" aria-label="Hapus ' + aman(u.nama) + '"><i class="fal fa-trash"></i></button>'
    			+ '</td></tr>';
    	}

    	function tombolHalaman(n, label, keadaan) {
    		if (keadaan === 'aktif') {
    			return '<li class="page-item active" aria-current="page"><span class="page-link">' + label + '</span></li>';
    		}
    		if (keadaan === 'mati') {
    			return '<li class="page-item disabled"><span class="page-link">' + label + '</span></li>';
    		}

    		return '<li class="page-item"><a class="page-link" href="#" data-halaman="' + n + '">' + label + '</a></li>';
    	}

    	function gambarHalaman(jumlah, terakhir) {
    		if (jumlah <= perHalaman) {
    			$('#pagPengguna').empty();

    			return;
    		}

    		var dari   = Math.max(1, Math.min(halaman - 2, terakhir - 4));
    		var sampai = Math.min(terakhir, dari + 4);
    		var item   = [];

    		item.push(tombolHalaman(1, '<i class="fal fa-arrow-alt-to-left"></i>', halaman === 1 ? 'mati' : ''));
    		item.push(tombolHalaman(halaman - 1, '<i class="fal fa-arrow-alt-left"></i>', halaman === 1 ? 'mati' : ''));

    		for (var n = dari; n <= sampai; n++) {
    			item.push(tombolHalaman(n, '' + n, n === halaman ? 'aktif' : ''));
    		}

    		item.push(tombolHalaman(halaman + 1, '<i class="fal fa-arrow-alt-right"></i>', halaman === terakhir ? 'mati' : ''));
    		item.push(tombolHalaman(terakhir, '<i class="fal fa-arrow-alt-to-right"></i>', halaman === terakhir ? 'mati' : ''));

    		$('#pagPengguna').html('<nav class="mt-3" aria-label="Navigasi halaman pengguna"><ul class="pagination pagination-sm justify-content-end mb-0">' + item.join('') + '</ul></nav>');
    	}

    	function gambarPengguna() {
    		var hasil    = saringPengguna();
    		var terakhir = Math.max(1, Math.ceil(hasil.length / perHalaman));

    		if (halaman > terakhir) { halaman = terakhir; }

    		var mulai = (halaman - 1) * perHalaman;
    		var isi   = hasil.slice(mulai, mulai + perHalaman);
    		var baris = [];

    		for (var i = 0; i < isi.length; i++) { baris.push(barisPengguna(isi[i])); }

    		if (isi.length === 0) {
    			$('#listUsers').html('<tr><td colspan="4" class="text-center text-muted py-4">Tidak ada pengguna yang cocok'
    				+ (kunciCari !== '' ? ' dengan "' + aman($.trim(kunciCari)) + '"' : '') + '.</td></tr>');
    			// dua salinan (kepala kartu di desktop, di atas pager di ponsel)
    			// selalu diisi bersamaan
    			$('.jumlah-pengguna').text('0 pengguna');
    		} else {
    			$('#listUsers').html(baris.join(''));
    			$('.jumlah-pengguna').text((mulai + 1) + '-' + (mulai + isi.length) + ' dari ' + hasil.length + ' pengguna');
    		}

    		gambarHalaman(hasil.length, terakhir);
    	}

    	$.getJSON('{$link_api_pengguna_all}', function(b){
    		semuaPengguna = b || [];
    		gambarPengguna();
    	});

    	$('#cariPengguna').on('input change keyup', function(){
    		kunciCari = $(this).val();
    		halaman   = 1;
    		gambarPengguna();
    	});

    	$('#pagPengguna').on('click', 'a[data-halaman]', function(e){
    		e.preventDefault();
    		halaman = parseInt($(this).attr('data-halaman'), 10) || 1;
    		gambarPengguna();
    	});

    	// tombol mata di kolom kata sandi: tipe input diganti, ikonnya ikut bertukar
    	$(document).on('click', 'button[data-lihat]', function () {
    			var input  = document.getElementById($(this).attr('data-lihat'));
    			var tampil = input.type === 'password';

    			input.type = tampil ? 'text' : 'password';
    			$(this).find('i').attr('class', 'fal ' + (tampil ? 'fa-eye-slash' : 'fa-eye'));
    	});

    	// aturan wajib juragan berlaku untuk level cs dan admin (dua-duanya butuh
    	// minimal satu toko); pesannya ikut padam begitu satu toko dicentang, dan
    	// kalimatnya menyebut level yang sedang dipilih supaya tidak membingungkan.
    	// Kotak wewenang produk & transaksi hanya ditampilkan untuk level CS.
    	function sinkronHintLevel(popup) {
    			var opsi  = popup.querySelector('select[name="level"]');
    			var hint  = popup.querySelector('[data-hint-cs]');
    			var wadah = popup.querySelector('.gulir-pilihan');
    			var hak   = popup.querySelectorAll('[data-hak-cs]');
    			var isCs  = !!opsi && opsi.value === 'cs';
    			var perlu = !!opsi && (isCs || opsi.value === 'admin');
    			var ada   = !!wadah && wadah.querySelectorAll('input:checked').length > 0;

    			if (hint) {
    				if (perlu) {
    					hint.textContent = 'Level ' + (opsi.value === 'admin' ? 'Admin' : 'CS') + ' wajib punya minimal satu juragan.';
    				}
    				hint.classList.toggle('d-none', ! perlu || ada);
    			}

    			hak.forEach(function (el) { el.classList.toggle('d-none', ! isCs); });
    	}

    	// kedua kotak wewenang CS diisi dari baris pengguna saat popup sunting dibuka;
    	// checkbox tidak ikut ternilai lewat value= biasa
    	function sinkronHakCs(popup, a) {
    			var produk = popup.querySelector('input[name="lihat_semua_produk"]');
    			var transaksi = popup.querySelector('input[name="sunting_transaksi"]');

    			if (produk)    { produk.checked    = ('' + a.getAttribute('data-lihat-semua') === '1'); }
    			if (transaksi) { transaksi.checked = ('' + a.getAttribute('data-sunting-transaksi') === '1'); }
    	}

    	$(document).on('change', '.gulir-pilihan input[type="checkbox"]', function () {
    			sinkronHintLevel($(this).closest('.modal').get(0));
    	});

    	$(document).on('change', 'select[name="level"]', function () {
    			sinkronHintLevel($(this).closest('.modal').get(0));
    	});

    	$('#modalTambahPengguna, #modalSuntingPengguna').each(function () {
    			sinkronHintLevel(this);
    	});

    	var popupTambah = document.getElementById('modalTambahPengguna');
    	if (popupTambah.getAttribute('data-buka') === '1') {
    		bootstrap.Modal.getOrCreateInstance(popupTambah).show();
    	}

    	// ditutup = mulai dari nol. Dulu isian percobaan sebelumnya (dan pil juragan
    	// yang sudah dicentang) tertinggal di dalam popup, jadi tombol Tambah
    	// Pengguna berikutnya membuka form yang masih terisi.
    	popupTambah.addEventListener('hidden.bs.modal', function () {
    			var form = popupTambah.querySelector('form');

    			// reset() mengembalikan tiap kontrol ke keadaan bawaan HTML, dan pil
    			// yang ikut ter-render membawa atribut checked, jadi centangnya harus
    			// dilepas sendiri setelahnya
    			if (form) { form.reset(); }

    			var centang = popupTambah.querySelectorAll('#pilihJuragan input:checked');
    			for (var i = 0; i < centang.length; i++) { centang[i].checked = false; }

    			$('#pilihJuragan').attr('data-juragan', '[]');
    			sinkronHintLevel(popupTambah);

    			var salah = popupTambah.querySelectorAll('.is-invalid');
    			for (var j = 0; j < salah.length; j++) { salah[j].classList.remove('is-invalid'); }
    	});

    	var mP=document.getElementById('modalSuntingPengguna');
    	mP.addEventListener('show.bs.modal',function(o){
    		var a=o.relatedTarget;
    		var b=a.getAttribute('data-id');
    		var c=a.getAttribute('data-nama');
    		var d=a.getAttribute('data-username');
    		var e=a.getAttribute('data-email');
    		var f=a.getAttribute('data-level');
    		var g=a.getAttribute('data-status');
    		var h=mP.querySelector('.modal-title');
    		var i=mP.querySelector('#modalSuntingPengguna input[name="id"]');
    		var j=mP.querySelector('#modalSuntingPengguna input[name="nama"]');
    		var k=mP.querySelector('#modalSuntingPengguna input[name="username"]');
    		var l=mP.querySelector('#modalSuntingPengguna input[name="email"]');
    		var m=mP.querySelector('#modalSuntingPengguna select[name="level"]');
    		var n=mP.querySelector('#modalSuntingPengguna select[name="status"]');
    		h.textContent='Perbarui '+c,
    		i.value=b,
    		j.value=c,
    		k.value=d,
    		l.value=e,
    		// level lama yang tidak lagi ditawarkan (viewer) tetap harus bisa
    		// ditampilkan, kalau tidak nilainya hilang dan tersimpan jadi level lain
    		(function(){
    			if (f && ! m.querySelector('option[value="'+f+'"]')) {
    				var o=document.createElement('option');
    				o.value=f, o.textContent=f.charAt(0).toUpperCase()+f.slice(1)+' (lama)', m.appendChild(o);
    			}
    		})(),
    		m.value=f,
    		n.value=g,
    		sinkronHintLevel(mP),
    		sinkronHakCs(mP, a),

    		$.ajax({
    			method:'GET',
    			url:'{$link_api_relasi}',
    			data:{id:b}
    		}).done(function(a){
    				var punya = keDaftar((a[b] && a[b].juragan) || []);

    				gambarJuragan($('#pilihJuraganSunting'), punya, 'sunting');
    				// tokonya baru digambar sekarang, jadi centangannya baru bisa dihitung di sini
    				sinkronHintLevel(mP);
    		});
    	});
    });
    JS;

$packer    = new Tholu\Packer\Packer($js, 'Normal', true, false, true);
$packed_js = $packer->pack();
echo '<script>' . $packed_js . '</script>';
?>
<?= $this->endSection() ?>
