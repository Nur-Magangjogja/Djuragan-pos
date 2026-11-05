<?php
/**
 * Layar penolakan untuk daftar transaksi toko yang tidak dipegang akun ini.
 *
 * Muncul lewat Admin\Invoices::lihat() ketika slug toko di URL bukan milik yang
 * sedang login — misalnya dari tombol "detail" nota di profil customer 360, yang
 * daftar transaksangnya memang lintas toko. Dulu kasus ini 404 datar sehingga
 * orang mengira tautannya rusak.
 *
 * @var string $title          judul tab browser, dikirim controller
 * @var string $tautan_kembali tujuan tombol kembali: halaman asal klik, atau daftar
 *                             Semua Juragan kalau asal tidak dikenal
 * @var string $label_kembali  label tombolnya, ikut tujuan
 */
?>
<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">Tracking Transaksi</h1>

    <div class="card mt-3">
        <div class="card-body text-center py-5">
            <i class="fal fa-lock fa-2x text-muted mb-3"></i>
            <p class="fw-semibold mb-1">Maaf, Anda tidak memiliki akses ke juragan ini.</p>
            <p class="text-muted small mb-4">
                Halaman ini hanya menampilkan transaksi toko yang ditautkan ke akun Anda.
            </p>
            <a class="btn btn-sm btn-outline-secondary" href="<?= esc($tautan_kembali) ?>">
                <i class="fal fa-arrow-left me-1"></i> <?= esc($label_kembali) ?>
            </a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
