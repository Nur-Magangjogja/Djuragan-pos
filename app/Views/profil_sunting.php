<?php

// Variabel berikut dikirim Profil::sunting() lewat view() lalu di-extract CI4 saat
// render, sehingga tidak terlihat oleh analis statis. Deklarasi di bawah memberi
// jenis sebenarnya ke Intelephense (P1008 pada $pengguna & $validation).

/**
 * @var object                              $pengguna  data user dari UserModel::find(): id, username, level, name, email
 * @var \CodeIgniter\Validation\Validation $validation hasil \Config\Services::validation()
 */

$session = \Config\Services::session();
?>
<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?php if ($session->get('level') === 'cs') : ?>
    <?= $this->include('user/navbar') ?>
<?php else : ?>
    <?= $this->include('admin/navbar') ?>
<?php endif; ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">Ubah Profil</h1>

    <?= ($_SESSION['status'] ?? '') ?>

    <div class="card">
                <div class="card-body px-4 py-3">
                    <?= form_open('user/sunting', [], ['id' => $pengguna->id]) ?>

                    <div class="row gx-0">
                        <div class="col-md-6 pe-md-4">
                            <h6 class="fw-bold text-muted small text-uppercase mb-1" style="letter-spacing:.05em">Data Akun</h6>
                            <p class="text-muted small mb-3">Biarkan jika tidak ingin mengubah.</p>

                            <div class="row gx-2">
                                <div class="col-6 mb-2">
                                    <?= form_label('Username', 'username', ['class' => 'form-label']) ?>
                                    <?= form_input('username', $pengguna->username, ['class' => 'form-control', 'id' => 'username', 'disabled' => '']) ?>
                                </div>
                                <div class="col-6 mb-2">
                                    <?= form_label('Level', 'level', ['class' => 'form-label']) ?>
                                    <?= form_input('level', $pengguna->level, ['class' => 'form-control', 'id' => 'level', 'disabled' => '']) ?>
                                </div>
                            </div>

                            <div class="mb-2">
                                <?php $kelasNama = 'form-control' . ($validation->hasError('nama') ? ' is-invalid' : ''); ?>
                                <?= form_label('Nama Lengkap', 'nama', ['class' => 'form-label']) ?>
                                <?= form_input('nama', set_value('nama', $pengguna->name), ['class' => $kelasNama, 'id' => 'nama', 'required' => '', 'placeholder' => 'nama lengkap']) ?>
                                <?php if ($validation->hasError('nama')) : ?>
                                    <div class="invalid-feedback"><?= $validation->getError('nama') ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-2">
                                <?php $kelasEmail = 'form-control' . ($validation->hasError('email') ? ' is-invalid' : ''); ?>
                                <?= form_label('Email', 'email', ['class' => 'form-label']) ?>
                                <?= form_input('email', set_value('email', $pengguna->email), ['class' => $kelasEmail, 'id' => 'email', 'required' => '', 'placeholder' => 'alamat@email'], 'email') ?>
                                <?php if ($validation->hasError('email')) : ?>
                                    <div class="invalid-feedback"><?= $validation->getError('email') ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-6 ps-md-4 mt-4 mt-md-0 pemisah-profil">
                            <h6 class="fw-bold text-muted small text-uppercase mb-1" style="letter-spacing:.05em">Ubah Kata Sandi</h6>
                            <p class="text-muted small mb-3">Kosongkan ketiga kolom ini kalau tidak ingin mengganti kata sandi.</p>

                            <div class="mb-2">
                                <?= form_label('Kata Sandi Lama', 'sandi_lama', ['class' => 'form-label']) ?>
                                <div class="position-relative">
                                    <?= form_password('sandi_lama', '', ['class' => 'form-control pe-5', 'id' => 'sandi_lama', 'placeholder' => 'sandi saat ini', 'autocomplete' => 'current-password']) ?>
                                    <button type="button" class="btn btn-link p-0 position-absolute top-50 translate-middle-y end-0 me-3 text-muted sandi-lihat" data-target="sandi_lama" title="Tampilkan atau sembunyikan sandi" aria-label="Tampilkan sandi"><i class="fal fa-eye"></i></button>
                                </div>
                            </div>
                            <div class="mb-2">
                                <?= form_label('Kata Sandi Baru', 'sandi_baru', ['class' => 'form-label']) ?>
                                <div class="position-relative">
                                    <?= form_password('sandi_baru', '', ['class' => 'form-control pe-5', 'id' => 'sandi_baru', 'placeholder' => 'minimal 6 karakter', 'autocomplete' => 'new-password']) ?>
                                    <button type="button" class="btn btn-link p-0 position-absolute top-50 translate-middle-y end-0 me-3 text-muted sandi-lihat" data-target="sandi_baru" title="Tampilkan atau sembunyikan sandi" aria-label="Tampilkan sandi"><i class="fal fa-eye"></i></button>
                                </div>
                            </div>
                            <div class="mb-2">
                                <?= form_label('Ulangi Kata Sandi Baru', 'ulangi_sandi_baru', ['class' => 'form-label']) ?>
                                <div class="position-relative">
                                    <?= form_password('ulangi_sandi_baru', '', ['class' => 'form-control pe-5', 'id' => 'ulangi_sandi_baru', 'placeholder' => 'ulangi sandi baru', 'autocomplete' => 'new-password']) ?>
                                    <button type="button" class="btn btn-link p-0 position-absolute top-50 translate-middle-y end-0 me-3 text-muted sandi-lihat" data-target="ulangi_sandi_baru" title="Tampilkan atau sembunyikan sandi" aria-label="Tampilkan sandi"><i class="fal fa-eye"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">
                    <div class="d-flex justify-content-end">
                        <?= form_button([
                            'class'   => 'btn btn-primary',
                            'content' => '<i class="fal fa-save"></i> Simpan',
                            'type'    => 'submit',
                        ]) ?>
                    </div>
                    <?= form_close() ?>
                </div>
            </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('css') ?>
<style>
    /* garis pemisah antar kolom hanya di layar menengah ke atas; di layar kecil
       kedua kolom ditumpuk sehingga garis di kiri justru terlihat aneh */
    @media (min-width: 768px) {
        .pemisah-profil { border-left: 1px solid #e2e8f0; }
    }

    /* ikon mata toggle: paksa tegak, supaya tidak ikut miring bawaan tag <i> */
    .sandi-lihat i {
        font-style: normal;
    }

    /* tombol mata bergaya btn-link: buang garis bawah agar ikon bersih */
    .sandi-lihat,
    .sandi-lihat:hover,
    .sandi-lihat:focus {
        text-decoration: none;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
    // ikon mata di kolom kata sandi: ganti type password <-> teks supaya isian
    // bisa dibaca sebelum dikirim. Satu pendengar untuk ketiga kolom.
    document.addEventListener('click', function (e) {
        var tombol = e.target.closest ? e.target.closest('.sandi-lihat') : null;

        if (! tombol) {
            return;
        }

        var kolom = document.getElementById(tombol.getAttribute('data-target'));

        if (! kolom) {
            return;
        }

        var tampil = kolom.type === 'password';
        kolom.type = tampil ? 'text' : 'password';

        var ikon = tombol.querySelector('i');

        if (ikon) {
            ikon.className = tampil ? 'fal fa-eye-slash' : 'fal fa-eye';
        }

        tombol.setAttribute('aria-label', tampil ? 'Sembunyikan sandi' : 'Tampilkan sandi');
    });
</script>
<?= $this->endSection() ?>
