<?php

namespace Config;

// Create a new instance of our RouteCollection class.
$routes = Services::routes();

// Load the system's routing file first, so that the app and ENVIRONMENT
// can override as needed.
if (is_file(SYSTEMPATH . 'Config/Routes.php')) {
    require SYSTEMPATH . 'Config/Routes.php';
}

/*
 * --------------------------------------------------------------------
 * Router Setup
 * --------------------------------------------------------------------
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Auth');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
// pesan 404 sengaja tidak diteruskan ke sini: override selalu menerima pesan apa adanya
// (CodeIgniter::display404errors memanggil $override($e->getMessage())), termasuk teks
// internal router seperti "Can't find a route for ...", yang tidak layak dibaca tamu
$routes->set404Override(static fn () => view('errors/html/error_page'));
// The Auto Routing (Legacy) is very dangerous. It is easy to create vulnerable apps
// where controller filters or CSRF protection are bypassed.
// If you don't want to define all routes, please use the Auto Routing (Improved).
// Set `$autoRoutesImproved` to true in `app/Config/Feature.php` and set the following to true.
//$routes->setAutoRoute(false);

/*
 * --------------------------------------------------------------------
 * Route Definitions
 * --------------------------------------------------------------------
 */
$routes->addPlaceholder('juragan', '[a-z0-9]{40}|semua');
$routes->addPlaceholder('tab', 'semua|pembayaran|cek-bayar|dalam-proses|belum-proses|selesai|saring|masih-disewa');

// We get a performance increase by specifying the default
// route since we don't have to scan directories.
$routes->group('auth', ['filter' => 'auth'], static function ($routes) {
    $routes->match(['GET', 'POST'], 'index', 'Auth::index', ['as' => 'hal.index']);
    $routes->match(['GET', 'POST'], 'daftar', 'Auth::daftar');
    $routes->match(['GET', 'POST'], 'lupa', 'Auth::lupa');
    $routes->addRedirect('/', 'hal.index');
    $routes->get('reset', 'Auth::reset');
    $routes->post('simpan-sandi-baru', 'Auth::simpanSandiBaru');
});

$routes->get('auth/keluar', 'Auth::keluar', ['filter' => 'auth:admin,superadmin,user']);

// profil milik pengguna yang sedang login, bisa diakses semua role aktif
$routes->group('user', ['filter' => 'auth:admin,superadmin,user'], static function ($routes) {
    $routes->get('sunting', 'Profil::sunting');
    $routes->post('sunting', 'Profil::simpan');

    // daftar lengkap notifikasi; halaman yang dituju tombol "Lihat semua" di panel lonceng
    $routes->get('notifikasi', 'Notifikasi::index');
    $routes->get('notifikasi/(:segment)', 'Notifikasi::index/$1');
});

// admin
$routes->group('admin', static function ($routes) {
    $routes->group('notifikasi', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
        $routes->get('/', 'Notifikasi::index');
        $routes->get('(:segment)', 'Notifikasi::index/$1');
    });

    // Transaksi (nota). Layar daftarnya tetap milik admin & superadmin. CS yang
    // diberi wewenang sunting oleh Superadmin tidak dipindah ke layar admin: dia
    // membuka daftar transaksinya sendiri (user/invoices) dan hanya jalur tulis
    // di bawah ini yang ikut terbuka untuknya — sunting nota, status proses,
    // dan pembayaran tokonya (dijaga filter kelola-transaksi + notaMilikToko).
    $routes->group('invoices', ['filter' => ['auth:admin,superadmin,cs', 'kelola-transaksi']], static function ($routes) {
        $routes->get('sunting/(:any)', 'Admin\Invoices::sunting/$1');
        $routes->get('info_pembayaran', 'Admin\Invoices::info_pembayaran');
        $routes->get('detail_status/(:any)', 'Admin\Invoices::detail_status/$1');
        $routes->post('update', 'Admin\Invoices::update');
        $routes->post('simpan_pembayaran', 'Admin\Invoices::simpan_pembayaran');
        $routes->post('save_progress', 'Admin\Invoices::save_progress');
        $routes->post('update_bayar', 'Admin\Invoices::update_bayar');
        // pencatatan barang sewaan kembali: dari sinilah nota sewa berhak masuk
        // Selesai, pulih di stok, dan dihitung poinnya
        $routes->post('sewa_kembali', 'Admin\Invoices::sewa_kembali');

        // sisanya: layar daftar dan tulis orderan khusus admin & superadmin,
        // termasuk menghapus nota (CS cukup melaporkan ke admin)
        $routes->group('', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
            $routes->get('/', 'Admin\Invoices::lihat', ['as' => 'hal.admin']);
            $routes->get('tulis', 'Admin\Invoices::tulis');
            $routes->get('lihat/(:juragan)', 'Admin\Invoices::lihat/$1');
            $routes->get('lihat/(:juragan)/(:tab)', 'Admin\Invoices::lihat/$1/$2');
            $routes->get('lihat/(:juragan)/(:tab)/(:segment)', 'Admin\Invoices::lihat/$1/$2/$3');
            $routes->post('save', 'Admin\Invoices::save');
            $routes->post('hapus_orderan', 'Admin\Invoices::hapus_orderan');
        });
    });

    // dasbor: ringkasan transaksi, pembayaran, dan stok
    $routes->get('dasbor', 'Admin\Dasbor::index', ['filter' => 'auth:admin,superadmin', 'as' => 'hal.dasbor']);

    // pencatatan COD: kunjungan customer yang tidak berhenti jadi orderan
    $routes->group('cod', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
        $routes->get('/', 'Admin\Cod::index', ['as' => 'hal.cod']);
        $routes->post('simpan', 'Admin\Cod::simpan');
        $routes->post('sunting', 'Admin\Cod::sunting');
        // hapus lunak: barisnya tetap ada, hanya hilang dari daftar kartu
        $routes->post('hapus', 'Admin\Cod::hapus');
        // aksi berikut khusus superadmin; diperiksa di controller, dan dropdownnya
        // memang tidak merender tombolnya untuk admin biasa
        $routes->post('hapus-pilihan', 'Admin\Cod::hapusPilihan');
    });

    // pantau stok & tambah barang
    // CS boleh membuka daftar produk, tetapi hanya membaca; aksi tulis dipagari
    // lebih sempit di dalam, dan pengaturan poin produk khusus Superadmin.
    $routes->group('produk', ['filter' => 'auth:admin,superadmin,cs'], static function ($routes) {
        $routes->get('/', 'Admin\Produk::index', ['as' => 'hal.produk']);

        $routes->group('', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
            $routes->post('save', 'Admin\Produk::simpan');
            $routes->post('update', 'Admin\Produk::perbarui');
            $routes->post('hapus', 'Admin\Produk::hapus');
            $routes->post('hapus-kartu', 'Admin\Produk::hapus_kartu');
        });
    });

    // laporan akumulasi poin gaji (khusus Superadmin)
    $routes->group('poin', ['filter' => 'auth:superadmin'], static function ($routes) {
        $routes->get('/', 'Admin\Poin::index', ['as' => 'hal.poin']);
    });

    // laporan per jenis: pesanan, pendapatan, pembayaran, piutang, produksi, produk, pelanggan
    $routes->group('laporan', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
        $routes->get('/', 'Admin\Laporan::index', ['as' => 'hal.laporan']);
        // unduh diletakkan sebelum (:segment) supaya tidak dikira nama laporan
        $routes->get('(:segment)/unduh/(:alpha)', 'Admin\Laporan::unduh/$1/$2');
        $routes->get('(:segment)', 'Admin\Laporan::index/$1');
    });

    // KPI karyawan: angka admin dan CS, dihitung dari jejak kerja yang tercatat
    $routes->group('kpi', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
        $routes->get('/', 'Admin\Kpi::index', ['as' => 'hal.kpi']);

        // menentukan target khusus Superadmin; admin hanya melihat
        // ringkasan, ranking, dan detail angkanya. Filter grup bersarang digabung,
        // jadi admin lolos pagar luar namun dipotong pagar dalam.
        $routes->group('', ['filter' => 'auth:superadmin'], static function ($routes) {
            // target dipasang sebelum rute detail supaya 'target' tidak dikira indikator
            $routes->get('target', 'Admin\Kpi::target');
            $routes->post('target', 'Admin\Kpi::simpanTarget');
            // poin aktivitas ikut keputusan manajemen: layar yang sama, form terpisah
            $routes->post('poin', 'Admin\Kpi::simpanPoin');
        });

        $routes->get('ranking', 'Admin\Kpi::ranking');
        // (:segment), bukan (:alpha): nama indikator memakai garis bawah (gmv_cs)
        $routes->get('detail/(:segment)', 'Admin\Kpi::detail/$1');
    });

    // crm: manajemen pelanggan, follow-up tagihan, notif resi. Kirim WhatsApp
    // satu nota sudah dicabut — percakapan dengan pelanggan sekarang dicatat
    // manual lewat Tugas CS > Catatan percakapan.
    // Live Chat WA, Broadcast, Riwayat Pesan, Template Pesan, Pengaturan WA dan
    // webhook-nya sudah dihapus — dulunya CS hanya punya akses ke Live Chat, jadi
    // grup bersarang untuk memisahkan CS dari kelola CRM tidak diperlukan lagi.
    $routes->group('crm', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
        $routes->get('/', 'Admin\Crm::index', ['as' => 'hal.crm']);
        $routes->get('pelanggan', 'Admin\Crm::pelanggan');
        $routes->get('pelanggan/(:num)', 'Admin\Crm::pelanggan_detail/$1');
        $routes->post('pelanggan/simpan_profil/(:num)', 'Admin\Crm::simpan_profil/$1');
        $routes->post('pelanggan/simpan_ukuran/(:num)', 'Admin\Crm::simpan_ukuran/$1');
        $routes->post('pelanggan/tambah_aktivitas/(:num)', 'Admin\Crm::tambah_aktivitas/$1');
        $routes->post('pelanggan/tambah_tag/(:num)', 'Admin\Crm::tambah_tag/$1');
        $routes->post('pelanggan/hapus_tag/(:num)/(:num)', 'Admin\Crm::hapus_tag/$1/$2');
        $routes->get('followup', 'Admin\Crm::followup');
        $routes->post('followup/simpan', 'Admin\Crm::simpan_followup');
        $routes->post('followup/selesai/(:num)', 'Admin\Crm::selesaikan_followup/$1');
        $routes->get('duplikat', 'Admin\Crm::duplikat');
        $routes->post('duplikat/merge', 'Admin\Crm::proses_merge');
        $routes->get('tagihan', 'Admin\Crm::tagihan');
    });

    // reminder penjahit: orderan pembuatan/jual yang deadline-nya sudah dekat.
    // Terpisah dari lonceng notifikasi dan jadi menu prioritas. Admin & CS melihat
    // daftarnya; hanya Superadmin yang boleh mengubah ambang harinya.
    $routes->group('reminder', ['filter' => 'auth:admin,superadmin,cs'], static function ($routes) {
        $routes->get('/', 'Reminder::index');
        $routes->get('jumlah', 'Reminder::jumlah');

        $routes->group('', ['filter' => 'auth:superadmin'], static function ($routes) {
            $routes->post('pengaturan', 'Reminder::simpan');
        });
    });

    // antrean tugas CS: CS mengambil dan mengerjakan, Admin menetapkan dan memantau.
    // Membagi tugas khusus admin/superadmin; angka badge menu dibaca dari rute
    // jumlah yang memakai filter yang sama.
    $routes->group('tugas', ['filter' => 'auth:admin,superadmin,cs'], static function ($routes) {
        $routes->get('/', 'Admin\Tugas::index', ['as' => 'hal.tugas']);
        $routes->get('jumlah', 'Admin\Tugas::jumlah');
        $routes->get('dasbor', 'Admin\Tugas::dasbor');

        // kategori 1 dari empat kategori Tugas CS: percakapan WhatsApp yang perlu
        // ditindaklanjuti. CS mengambil sendiri (guard anti-ganda di database),
        // melepas tangan kalau tidak lanjut, dan menandai selesai. Daftar nomor
        // pengirim beserta siapa yang boleh memakainya diatur Admin/Superadmin.
        $routes->group('whatsapp', static function ($routes) {
            $routes->get('/', 'Admin\Whatsapp::index');
            $routes->get('jumlah', 'Admin\Whatsapp::jumlah');
            $routes->post('simpan', 'Admin\Whatsapp::simpan');
            $routes->post('ambil/(:num)', 'Admin\Whatsapp::ambil/$1');
            $routes->post('lepas/(:num)', 'Admin\Whatsapp::lepas/$1');
            $routes->post('selesai/(:num)', 'Admin\Whatsapp::selesai/$1');

            $routes->group('', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
                $routes->get('nomor', 'Admin\Whatsapp::nomor');
                $routes->post('nomor', 'Admin\Whatsapp::simpanNomor');
                $routes->post('nomor-aktif', 'Admin\Whatsapp::aktifNomor');
                $routes->post('nomor-hapus', 'Admin\Whatsapp::hapusNomor');
            });
        });
        // kategori 2: permintaan ulasan dari nota yang dananya sudah masuk (DP,
        // dicicil, atau lunas). Tab ketiga menampung penagihan (DP belum dikonfirmasi /
        // pelunasan mendekati tenggat) supaya pekerjaan uang tetap punya satu layar sendiri.
        $routes->group('bintang', static function ($routes) {
            $routes->get('/', 'Admin\Bintang::index');
            $routes->get('jumlah', 'Admin\Bintang::jumlah');
            $routes->post('ambil/(:num)', 'Admin\Bintang::ambil/$1');
            $routes->post('lepas/(:num)', 'Admin\Bintang::lepas/$1');
            $routes->post('selesai/(:num)', 'Admin\Bintang::selesai/$1');
        });
        // kategori 4: catatan cek email. Bukan pemeriksaan akun — hanya catatan apa
        // yang dilihat di kotak masuk, masuk ke mesin tugas yang sama. Sengaja tidak
        // ada rute ambil: catatan ditugaskan ke satu alamat, bukan diperebutkan.
        $routes->group('email', static function ($routes) {
            $routes->get('/', 'Admin\Email::index');
            $routes->get('jumlah', 'Admin\Email::jumlah');
            $routes->post('simpan', 'Admin\Email::simpan');
            $routes->post('lepas/(:num)', 'Admin\Email::lepas/$1');
            $routes->post('selesai/(:num)', 'Admin\Email::selesai/$1');

            // daftar alamat email + siapa yang memegangnya: khusus Admin/Superadmin.
            // CS hanya memakai hasilnya lewat layar Cek Email di atas. Menghapus
            // alamat bukan mematikan: catatan lama dilepas tautannya, tidak ikut hilang.
            $routes->group('', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
                $routes->post('daftar', 'Admin\Email::tambahEmail');
                $routes->post('hapus', 'Admin\Email::hapusEmail');
                $routes->post('pegang', 'Admin\Email::beriPegang');
                $routes->post('pegang-copot', 'Admin\Email::copotPegang');
            });
        });
        $routes->get('cari-pelanggan', 'Admin\Tugas::cariPelanggan');
        $routes->get('catatan', 'Admin\Tugas::catatan');
        $routes->post('catatan', 'Admin\Tugas::simpanCatatan');
        // (:num) saja, jadi 'jumlah' di atas tidak pernah dikira id tugas
        $routes->get('(:num)', 'Admin\Tugas::lihat/$1');
        $routes->post('simpan', 'Admin\Tugas::simpan');
        $routes->post('ambil/(:num)', 'Admin\Tugas::ambil/$1');
        $routes->post('status/(:num)', 'Admin\Tugas::status/$1');
        $routes->post('operan/(:num)', 'Admin\Tugas::operan/$1');

        $routes->group('', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
            $routes->get('pengaturan', 'Admin\Tugas::pengaturan');
            $routes->post('pengaturan', 'Admin\Tugas::simpanPengaturan');
            $routes->get('template', 'Admin\Tugas::template');
            $routes->post('template', 'Admin\Tugas::simpanTemplate');
            $routes->post('template-pakai', 'Admin\Tugas::pakaiTemplate');
            $routes->post('template-aktif', 'Admin\Tugas::matikanTemplate');
            $routes->get('laporan', 'Admin\Tugas::laporan');
            $routes->get('laporan/csv', 'Admin\Tugas::eksporLaporan');
            $routes->post('sinkron', 'Admin\Tugas::sinkron');
            $routes->get('shift', 'Admin\Tugas::shift');
            $routes->post('shift', 'Admin\Tugas::simpanShift');
            $routes->post('sapu', 'Admin\Tugas::sapu');
            $routes->post('tetapkan/(:num)', 'Admin\Tugas::tetapkan/$1');
        });
    });

    // checklist toko harian + pemeriksaan email manual. CS membuka dan mengisi
    // sesinya; templat butir dan penonaktifan butir khusus Admin & Superadmin;
    // menautkan toko ke akun (pendelegasian) khusus Superadmin.
    $routes->group('checklist', ['filter' => 'auth:admin,superadmin,cs'], static function ($routes) {
        $routes->get('/', 'Admin\Checklist::index', ['as' => 'hal.checklist']);
        $routes->post('mulai', 'Admin\Checklist::mulai');
        $routes->get('jumlah', 'Admin\Checklist::jumlah');
        $routes->get('(:num)', 'Admin\Checklist::run/$1');
        $routes->post('isi/(:num)', 'Admin\Checklist::isi/$1');
        $routes->post('selesai/(:num)', 'Admin\Checklist::selesai/$1');
        $routes->post('butir-tambah/(:num)', 'Admin\Checklist::butirSendiri/$1');
        $routes->post('butir-hapus/(:num)', 'Admin\Checklist::hapusSendiri/$1');
        $routes->post('catatan', 'Admin\Checklist::catatan');
        $routes->post('catatan-selesai/(:num)', 'Admin\Checklist::catatanSelesai/$1');

        $routes->group('', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
            $routes->get('aturan', 'Admin\Checklist::aturan');
            $routes->post('butir', 'Admin\Checklist::simpanButir');
            $routes->post('butir-aktif', 'Admin\Checklist::matikanButir');
            $routes->post('toko-simpan', 'Admin\Checklist::simpanToko');
        });

        // memindahkan sebuah toko ke tangan akun lain mengubah siapa yang boleh
        // membuka lembar toko itu di semua halaman, jadi khusus Superadmin
        $routes->group('', ['filter' => 'auth:superadmin'], static function ($routes) {
            $routes->post('delegasi', 'Admin\Checklist::delegasi');
            $routes->post('copot-delegasi', 'Admin\Checklist::copotDelegasi');
            $routes->post('copot-tautan', 'Admin\Checklist::copotTautan');
        });
    });

    // pustaka SOP: semua peran membaca, hanya Admin & Superadmin yang menulis.
    // Dokumen tidak pernah dihapus, hanya dimatikan.
    $routes->group('sop', ['filter' => 'auth:admin,superadmin,cs'], static function ($routes) {
        $routes->get('/', 'Admin\Sop::index', ['as' => 'hal.sop']);
        $routes->get('(:num)', 'Admin\Sop::lihat/$1');

        $routes->group('', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
            $routes->post('simpan', 'Admin\Sop::simpan');
            $routes->post('aktif', 'Admin\Sop::matikan');
            $routes->post('hapus', 'Admin\Sop::hapus');
            $routes->post('urut', 'Admin\Sop::urut');
        });
    });

    // kebersihan kotak notifikasi: Admin dan Superadmin boleh menyapu manual dan
    // memasang batas umur hapus otomatis. Rutenya berdiri sendiri, di luar grup
    // settings di bawah, karena grup itu dipagari khusus Superadmin
    $routes->group('settings/notifikasi', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
        $routes->get('/', 'Admin\Settings\Notifikasi::index', ['as' => 'hal.notif_setelan']);
        $routes->post('hapus', 'Admin\Settings\Notifikasi::hapus');
        $routes->post('simpan', 'Admin\Settings\Notifikasi::simpan');
    });

    // kotak masukan: CS dan Admin mengirim lewat tombol melayang, hanya Superadmin
    // yang membacanya. Menunya berdiri sendiri di sidebar, jadi rutenya juga tidak
    // jadi anak grup settings di bawah
    $routes->group('masukan', ['filter' => 'auth:superadmin'], static function ($routes) {
        $routes->get('/', 'Admin\Masukan::index', ['as' => 'hal.masukan']);
        $routes->post('toggle/(:any)', 'Admin\Masukan::toggle/$1');
    });

    $routes->group('settings', ['filter' => 'auth:superadmin'], static function ($routes) {
        $routes->get('/', 'Admin\Settings::index');

        // form "Tambah Juragan" memposting ke alamat ini sejak lama, rutenya yang belum dipasang
        $routes->post('save_juragan', 'Admin\Settings\Juragan::save_juragan');

        $routes->group('bank', static function ($routes) {
            $routes->get('/', 'Admin\Settings\Bank::index');
            $routes->post('save', 'Admin\Settings\Bank::simpan');
            $routes->post('update', 'Admin\Settings\Bank::perbarui');
            // hapus lunak: nota lama tetap terbaca, hanya pilihan rekeningnya hilang
            $routes->post('hapus', 'Admin\Settings\Bank::hapus');
        });

        $routes->group('juragan', static function ($routes) {
            $routes->get('/', 'Admin\Settings\Juragan::index');
            $routes->post('update', 'Admin\Settings\Juragan::update');
            // satu toko dipegang satu Admin dan satu CS
            $routes->post('pengelola', 'Admin\Settings\Juragan::pengelola');
            // hapus lunak: baris tetap ada di tabel, hanya hilang dari daftar
            $routes->post('hapus', 'Admin\Settings\Juragan::hapus');
        });

        $routes->get('pengguna', 'Admin\Settings\Pengguna::pengguna');
        $routes->post('save_pengguna', 'Admin\Settings\Pengguna::save_pengguna');
        $routes->post('update_pengguna', 'Admin\Settings\Pengguna::update_pengguna');

        // kunci untuk pihak luar (website partner): hanya Superadmin yang bisa
        // menerbitkan dan mencabut, karena isinya cakupan toko + hak baca
        $routes->group('kunci-api', static function ($routes) {
            $routes->get('/', 'Admin\Settings\KunciApi::index', ['as' => 'hal.kunci_api']);
            $routes->post('save', 'Admin\Settings\KunciApi::simpan');
            $routes->post('sunting/(:num)', 'Admin\Settings\KunciApi::sunting/$1');
            $routes->post('cabut', 'Admin\Settings\KunciApi::cabut');
            // hapus lunak: barisnya keluar dari daftar, jejaknya tetap di tabel
            $routes->post('hapus/(:num)', 'Admin\Settings\KunciApi::hapus/$1');
            $routes->post('bersihkan', 'Admin\Settings\KunciApi::bersihkan');
        });
    });

    // halaman depan admin = dasbor, daftar invoice sekarang lewat menu Transaksi
    $routes->addRedirect('/', 'hal.dasbor');
});

// user
$routes->group('user', ['filter' => 'auth:user'], static function ($routes) {
    // halaman awal CS: kartu transaksi + SOP + tugas yang menunggu
    $routes->get('dasbor', 'User\Dasbor::index', ['as' => 'hal.dasbor.user']);
    $routes->get('chart', 'User\Chart::index');
    // akun CS yang belum punya juragan memakai ini untuk meminta penautan;
    // hasilnya notifikasi prioritas ke inbox Superadmin
    $routes->post('minta_juragan', 'User\Invoices::minta_juragan');
    $routes->group('invoices', static function ($routes) {
        $routes->get('/', 'User\Invoices::lihat', ['as' => 'hal.user']);
        $routes->get('tulis', 'User\Invoices::tulis');
        $routes->get('lihat/([a-z0-9]{40})', 'User\Invoices::lihat/$1');
        $routes->get('lihat/([a-z0-9]{40})/(:tab)', 'User\Invoices::lihat/$1/$2');
        $routes->get('lihat/([a-z0-9]{40})/(:tab)/(:segment)', 'User\Invoices::lihat/$1/$2/$3');
        $routes->get('info_pembayaran', 'User\Invoices::info_pembayaran');
        $routes->post('simpan_pembayaran', 'User\Invoices::simpan_pembayaran');
        $routes->post('save', 'User\Invoices::save');
        // CS yang menerima kembali barangnya di counter, jadi pencatatan
        // pengembalian juga ada di sisi ini
        $routes->post('sewa_kembali', 'User\Invoices::sewa_kembali');
    });
    // login CS mendarat di dasbor kerjanya; daftar transaksi tetap satu klik dari sidebar
    $routes->addRedirect('/', 'hal.dasbor.user');
});

// Bacaan untuk pihak luar (website partner). Sengaja didefinisikan SEBELUM grup
// `api` di bawah: grup itu dipagari sesi login, dan orang luar tidak punya sesi.
// Hak dicek per rute (bukan di filter grup) supaya satu permintaan hanya dihitung
// sekali dan tambahan endpoint baru tidak bisa diam-diam ikut terbuka.
$routes->group('api/publik', static function ($routes) {
    $routes->get('info', 'Api\Publik::info', ['filter' => 'apitoken']);
    $routes->get('katalog', 'Api\Publik::katalog', ['filter' => 'apitoken:katalog']);
    $routes->get('stok/(:num)', 'Api\Publik::stok/$1', ['filter' => 'apitoken:stok']);
    $routes->get('laporan/(:segment)', 'Api\Publik::laporan/$1', ['filter' => 'apitoken:laporan']);
    $routes->get('pesanan/(:segment)', 'Api\Publik::pesanan/$1', ['filter' => 'apitoken:lacak']);
    // poin gaji dibuka sendiri: angka internal untuk hitung-gaji pegawai, jadi
    // tidak ikut terkunci pada hak katalog/laporan
    $routes->get('poin', 'Api\Publik::poin', ['filter' => 'apitoken:poin']);
});

// seluruh endpoint API hanya dipanggil jQuery dari halaman yang sudah login,
// jadi tamu tidak boleh masuk; filter grup bersarang digabung, bukan ditimpa
$routes->group('api', ['filter' => 'auth:admin,superadmin,user'], static function ($routes) {
    $routes->get('invoice/counter_tab/(:num)', 'Api\Invoice::counter_tab/$1');

    $routes->group('juragan', static function ($routes) {
        $routes->add('(:segment)', 'Api\Juragan::$1');
    });

    $routes->group('notifikasi', static function ($routes) {
        $routes->add('(:segment)', 'Api\Notifikasi::$1');
    });

    // daftar akun + surel + login_terakhir hanya dipakai halaman
    // Pengaturan > Pengguna, yang memang khusus superadmin
    $routes->group('pengguna', ['filter' => 'auth:superadmin'], static function ($routes) {
        $routes->add('(:segment)', 'Api\Pengguna::$1');
    });

    $routes->group('pengiriman', static function ($routes) {
        $routes->add('(:segment)', 'Api\Pengiriman::$1');
        $routes->post('photos', 'Api\Pengiriman::create');
    });

    // foto orderan disimpan di writable/uploads, jadi satu-satunya jalan membacanya
    // lewat saji(); unggah dan hapus hanya boleh untuk nota milik toko sendiri
    $routes->group('foto', static function ($routes) {
        $routes->post('unggah/(:num)', 'Api\Foto::unggah/$1');
        $routes->get('saji/(:num)', 'Api\Foto::saji/$1');
        $routes->post('hapus/(:num)', 'Api\Foto::hapus/$1');
    });

    // foto produk katalog, berkasnya juga di writable/uploads: saji() boleh dibaca
    // CS karena form tulis orderan menampilkan contoh bentuk barang, sedangkan
    // panel kelola + unggah + hapus khusus Admin/superadmin seperti halaman Produk.
    // Filter grup bersarang digabung, jadi yang lebih sempit tetap memotong CS
    $routes->group('produk-foto', static function ($routes) {
        $routes->get('saji/(:num)', 'Api\ProdukFoto::saji/$1');
        // baca() cuma daftar foto, tidak bisa menulis: form tulis orderan CS
        // menampilkan contoh bentuk barang dari stok
        $routes->get('baca/(:num)', 'Api\ProdukFoto::baca/$1');

        $routes->group('', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
            $routes->get('kelola/(:num)', 'Api\ProdukFoto::kelola/$1');
            $routes->post('unggah/(:num)', 'Api\ProdukFoto::unggah/$1');
            $routes->post('hapus/(:num)', 'Api\ProdukFoto::hapus/$1');
        });
    });

    // saran varian stok untuk form tulis orderan; hanya membaca dan hanya toko
    // milik akun ini, sama seperti pencarian pelanggan
    $routes->group('stok', static function ($routes) {
        $routes->get('cari', 'Api\Stok::cari');
        $routes->get('jumlah', 'Api\Stok::jumlah');
        $routes->get('katalog', 'Api\Stok::katalog');
    });
});

$routes->addRedirect('/', 'hal.index');

// tiga lookup RajaOngkir memakai kunci berbayar dan hanya dipakai form
// tulis/sunting orderan (admin & CS), jadi tidak boleh dipanggil tamu
$routes->get('rajaongkir/kecamatan', 'Rajaongkir::kecamatan', ['filter' => 'auth:admin,superadmin,user']);
$routes->get('rajaongkir/kota', 'Rajaongkir::kota', ['filter' => 'auth:admin,superadmin,user']);
$routes->get('rajaongkir/provinsi', 'Rajaongkir::provinsi', ['filter' => 'auth:admin,superadmin,user']);
$routes->get('download/invoice/(:any)', 'Download::invoice/$1', ['filter' => 'auth:admin,superadmin,user']);
// satu nota yang sama dicetak jadi dua dokumen: invoice untuk pelanggan (memuat uang),
// lembar kerja untuk penjahit (hanya spesifikasi jahit, tanpa harga)
$routes->get('download/penjahit/(:any)', 'Download::penjahit/$1', ['filter' => 'auth:admin,superadmin,user']);
$routes->get('pelanggan/cari', 'Pelanggan::cari', ['filter' => 'auth:admin,superadmin,user']);
$routes->get('pelanggan/data/(:num)', 'Pelanggan::data/$1', ['filter' => 'auth:admin,superadmin,user']);
$routes->post('pelanggan/baru', 'Pelanggan::baru', ['filter' => 'auth:admin,superadmin,user']);

$routes->post('kirim-masukan', 'Masukan::kirim', ['filter' => 'auth:admin,superadmin,user']);

/*
 * --------------------------------------------------------------------
 * Additional Routing
 * --------------------------------------------------------------------
 *
 * There will often be times that you need additional routing and you
 * need it to be able to override any defaults in this file. Environment
 * based routes is one such time. require() additional route files here
 * to make that happen.
 *
 * You will have access to the $routes object within that file without
 * needing to reload it.
 */
if (is_file(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
