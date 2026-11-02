<?php
/**
 * Layout ini dipakai child view lewat $this->extend('template/main'), dan $title
 * mengalir dari array $data controller (mis. 'title' => '...') yang di-extract()
 * oleh CodeIgniter di system/View/View.php. Karena analyzers tidak bisa menelusuri
 * extract(), blok @var ini hanya memberi tahu Intelephense jenis variabelnya.
 *
 * @var string $title Judul halaman.
 */
?>
<!doctype html>
<html lang="id">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Peramban HP menautkan deretan angka jadi tombol telepon sendiri, dan warna
         tautan itu menimpa warna chip nomor HP di kartu transaksi. Dilarang. -->
    <meta name="format-detection" content="telephone=no">
    <link rel="stylesheet" href="<?= vite('resources/scss/style.scss') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/tailwind.css') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/css/tailwind.css') ?>">
    <!-- perapian bersama untuk tabel daftar (class `tabel-rapi` di <table>) -->
    <link rel="stylesheet" href="<?= base_url('assets/css/tabel-rapi.css') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/css/tabel-rapi.css') ?>">

    <?= $this->renderSection('css') ?>

    <title><?= esc($title) ?></title>
    <script>
        if (localStorage.getItem('djuragan_sidebar_collapsed') === '1') {
            document.documentElement.classList.add('sidebar-collapsed');
            document.addEventListener('DOMContentLoaded', function() {
                document.body.classList.add('sidebar-collapsed');
            });
        }
    </script>
</head>

<body class="<?= ! empty(session()->get('id')) ? 'has-app-sidebar' : '' ?>">
    <?= $this->renderSection('content') ?>

    <?= $this->renderSection('modal') ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <script>
        // Semua jalur tulis app ini lewat $.ajax/$.post, jadi token CSRF dipasang
        // sekali di header agar tiap permintaan ikut terlindungi.
        $.ajaxSetup({
            headers: { '<?= csrf_header() ?>': '<?= esc(csrf_hash()) ?>' }
        });

        // Cookie CSRF berumur 2 jam; kalau halaman masih terbuka setelah itu,
        // permintaan ditolak 403. Muat ulang satu kali untuk mengambil token baru.
        $(document).ajaxError(function (_event, jqXHR) {
            if (jqXHR.status !== 403) {
                return;
            }

            var terakhir = Number(sessionStorage.getItem('csrf_muat_ulang') || 0);
            if (Date.now() - terakhir < 10000) {
                return;
            }

            sessionStorage.setItem('csrf_muat_ulang', String(Date.now()));
            window.location.reload();
        });

        // Pesan flash (sukses/gagal di bagian atas halaman) hanya perlu dibaca
        // sekali, jadi ia hilang sendiri. Yang ikut cuma alert bertanda `flash`:
        // banner info di halaman lain dibiarkan menempel, dan alert di dalam modal
        // adalah bagian dari form yang sedang diisi, bukan laporan.
        $(function() {
            $('[data-flash]').each(function() {
                var pesan = this;

                window.setTimeout(function() {
                    $(pesan).fadeOut(400, function() {
                        $(pesan).remove();
                    });
                }, 5000);
            });
        });
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- lonceng notifikasi hidup di setiap halaman, bukan per-view lagi -->
    <?= $this->include('template/common_js') ?>
    <?= $this->renderSection('js') ?>
</body>

</html>
