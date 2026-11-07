<?php

// Variabel berikut dikirim Auth::index() lewat view() lalu di-extract CI4 saat
// render, sehingga tidak terlihat oleh analis statis. Deklarasi di bawah memberi
// jenis sebenarnya ke Intelephense (P1008 pada $validation).

/**
 * @var \CodeIgniter\Validation\Validation $validation hasil \Config\Services::validation()
 */
?>
<?= $this->extend('template/auth') ?>

<?= $this->section('authTitle') ?>
Seven Inc.  
<?= $this->endSection() ?>

<?= $this->section('authHeading') ?>
Masuk
<?= $this->endSection() ?>

<?= $this->section('authSub') ?>
Hanya akun valid yang bisa mengakses halaman ini.
<?= $this->endSection() ?>

<?= $this->section('authBody') ?>
<?= form_open() ?>
<div class="auth-field">
    <?php
    $class_username = 'form-control';
    if ($validation->hasError('username')) {
        $class_username .= ' is-invalid';
    }
    ?>

    <?= form_label('Pengguna', 'username', ['class' => 'form-label']) ?>
    <?= form_input([
        'class'       => $class_username,
        'id'          => 'username',
        'name'        => 'username',
        'placeholder' => 'username',
        'required'    => true,
        'tabindex'    => '1',
        'value'       => set_value('username'),
    ]) ?>
    <?php if ($validation->hasError('username')) { ?>
        <div class="invalid-feedback">
            <?= $validation->getError('username') ?>
        </div>
    <?php } ?>
</div>
<div class="auth-field">
    <?php
    $class_password = 'form-control';
    if ($validation->hasError('password')) {
        $class_password .= ' is-invalid';
    }
    ?>

    <?= form_label('Kata sandi', 'password', ['class' => 'form-label']) ?>
    <?php /* mata ikut di dalam pembungkus supaya Bootstrap masih menemukan
       .invalid-feedback sebagai saudara kolomnya (selektor ~) */ ?>
    <div class="sandi-merged">
        <?= form_password([
            'class'       => $class_password,
            'id'          => 'password',
            'name'        => 'password',
            'placeholder' => 'kata sandi',
            'required'    => true,
            'tabindex'    => '2',
            'autocomplete' => 'current-password',
        ]) ?>
        <button type="button" class="sandi-lihat" data-lihat="password" title="Lihat kata sandi" aria-label="Lihat kata sandi">
            <i class="fal fa-eye"></i>
        </button>

        <?php if ($validation->hasError('password')) { ?>
            <div class="invalid-feedback">
                <?= $validation->getError('password') ?>
            </div>
        <?php } ?>
    </div>

    <?php /* "Lupa sandi?" dulu duduk di baris paling bawah berdampingan dengan
              "daftar disini" — jauh dari kolom yang bersangkutan. */ ?>
    <div class="auth-lupa">
        <?= anchor('auth/lupa', 'Lupa sandi?', ['title' => 'Reset kata sandi']) ?>
    </div>
</div>
<?= form_button([
    'class'    => 'btn auth-submit',
    'content'  => 'Masuk',
    'tabindex' => '3',
    'type'     => 'submit',
]) ?>
<?= form_close() ?>
<style>
    /* sama seperti tombol mata di popup Atur Pengguna: mata melayang di dalam
       kolom, sisi kanan, jadi kolomnya terbaca satu kesatuan */
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
    .sandi-merged .invalid-feedback ~ .sandi-lihat {
        top: 1rem;
        transform: none;
    }
    .sandi-merged .sandi-lihat:hover {
        color: #101828;
    }
</style>
<script>
    // tombol mata: tipe input diganti, ikonnya ikut bertukar (polanya sama
    // dengan button[data-lihat] di halaman Atur Pengguna)
    document.querySelectorAll('button[data-lihat]').forEach(function (tombol) {
        tombol.addEventListener('click', function () {
            var input  = document.getElementById(tombol.getAttribute('data-lihat'));
            var tampil = input.type === 'password';
            input.type = tampil ? 'text' : 'password';
            tombol.querySelector('i').className = 'fal ' + (tampil ? 'fa-eye-slash' : 'fa-eye');
            tombol.setAttribute('title', tampil ? 'Sembunyikan kata sandi' : 'Lihat kata sandi');
        });
    });
</script>
<?= $this->endSection() ?>

<?= $this->section('authHint') ?>
Belum punya akun? <?= anchor('auth/daftar', 'daftar disini', ['title' => 'Daftar sekarang']) ?>
<?= $this->endSection() ?>
