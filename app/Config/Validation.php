<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\CreditCardRules;
use CodeIgniter\Validation\FileRules;
use CodeIgniter\Validation\FormatRules;
use CodeIgniter\Validation\Rules;

class Validation extends BaseConfig
{
    //--------------------------------------------------------------------
    // Setup
    //--------------------------------------------------------------------

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var string[]
     */
    public $ruleSets = [
        Rules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class,
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public $templates = [
        'list'   => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single',
    ];

    /**
     * setRuleGroup() menukar seluruh pesan kustom dengan pesan milik grup yang
     * dipanggil, bukan menambahkannya di atas pesan grup lain. Karena grup
     * sunting COD hanya berbeda di bagian id, pesan grup tambah dipakai lebih
     * dulu lalu dilengkapi pesan id_cod di sini.
     */
    public function __construct()
    {
        parent::__construct();

        $this->updateCod_errors = [
            'id_cod' => [
                'required' => 'Catatan yang akan diubah tidak dikenali.',
                'integer'  => 'Catatan yang akan diubah tidak dikenali.',
            ],
        ] + $this->addCod_errors;
    }

    //--------------------------------------------------------------------
    // Rules
    //--------------------------------------------------------------------

    public $signin = [
        'username' => 'required',
        'password' => 'required',
    ];
    public $signup = [
        'username' => 'required|min_length[3]|max_length[100]|is_unique[user.username]|alpha_dash',
        'password' => 'required|min_length[6]',
        'nama'     => 'required|min_length[3]|max_length[50]',
        'email'    => 'required|valid_email|max_length[100]|is_unique[user.email]',
    ];
    public $addBank = [
        'nama_bank'      => 'required|in_list[bri,bni,bca,mandiri,edc]',
        'nomor_rekening' => 'required|max_length[30]|numeric|is_unique[bank.rekening]',
        'atas_nama'      => 'required|max_length[50]|alpha_space',
    ];
    public $updateBank = [
        'id_bank'                => 'required|numeric',
        'sunting_nama_bank'      => 'required|in_list[bri,bni,bca,mandiri,edc]',
        'sunting_nomor_rekening' => 'required|max_length[30]|numeric|is_unique[bank.rekening,id_bank,{id_bank}]',
        'sunting_atas_nama'      => 'required|max_length[50]|alpha_space',
    ];
    // pesan khusus grup: lok api masih 'en', jadi aturan angka di bawah ini
    // dibahasakan agar tidak mencolok di form
    public $addBank_errors = [
        'nomor_rekening' => ['numeric' => 'Nomor rekening hanya boleh angka.'],
    ];
    public $updateBank_errors = [
        'sunting_nomor_rekening' => ['numeric' => 'Nomor rekening hanya boleh angka.'],
    ];
    public $addJuragan = [
        'nama_juragan' => 'required|min_length[3]|max_length[60]|is_unique[juragan.nama_juragan]',
        'bank'         => 'required',
    ];
    public $editJuragan = [
        'id'           => 'required|integer',
        'nama_juragan' => 'required|min_length[3]|max_length[60]',
        'bank'         => 'required',
    ];
    // rekening dipilih dengan kotak centang dan tidak wajib semuanya: 'required'
    // pada array berarti "harus ada minimal satu", jadi form ditolak hanya kalau
    // daftar centangnya kosong sama sekali (CI4: required([]) === false)
    public $addJuragan_errors = [
        'bank' => ['required' => 'Pilih minimal 1 bank.'],
    ];
    public $editJuragan_errors = [
        'bank' => ['required' => 'Pilih minimal 1 bank.'],
    ];
    public $addPengguna = [
        'username' => 'required|min_length[3]|max_length[100]|is_unique[user.username]|alpha_dash',
        'password' => 'required|min_length[6]',
        'nama'     => 'required|min_length[3]|max_length[50]',
        'email'    => 'required|valid_email|max_length[100]|is_unique[user.email]',
        // viewer/reseller tidak lagi ditawarkan untuk akun baru
        'level'    => 'required|in_list[superadmin,admin,cs]',
        'status'   => 'required|in_list[pending,inactive,active,blocked]',
    ];
    public $editPengguna = [
        'id'     => 'required|numeric',
        'nama'   => 'required|min_length[3]|max_length[50]',
        'email'  => 'required|valid_email|max_length[100]|is_unique[user.email,id,{id}]',
        // viewer masih diterima di sini: 239 akun lama menyandangnya, dan suntingan
        // apa pun pada akun itu tidak boleh gagal hanya karena levelnya tidak ditawarkan
        'level'  => 'required|in_list[superadmin,admin,cs,viewer]',
        'status' => 'required|in_list[pending,inactive,active,blocked]',
    ];
    public $editProfil = [
        'id'    => 'required|numeric',
        'nama'  => 'required|min_length[3]|max_length[50]',
        'email' => 'required|valid_email|max_length[100]|is_unique[user.email,id,{id}]',
    ];
    /**
     * Kunci API. Hak dan toko tidak diperiksa di sini karena keduanya pil dari
     * daftar yang dikenal server — dibersihkan di KunciApiModel::siapan(), dan
     * controller yang menolak kalau kosong.
     */
    public $kunciApi = [
        'nama'         => 'required|min_length[3]|max_length[60]',
        'batas_harian' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[100000]',
    ];
    /**
     * Pengaturan kebersihan notifikasi. `auto_aktif` adalah sakelar: tidak terkirim
     * sama sekali kalau tidak dicentang, jadi nilainya boleh kosong dan pembacanya
     * menganggap kosong = otomatis mati.
     */
    public $notifSetelan = [
        'auto_aktif' => 'permit_empty|in_list[0,1]',
        'umur_hari'  => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[365]',
    ];
    public $addInvoice = [
        'juragan'       => 'required',
        'pengguna'      => 'required',
        'asal_orderan'  => 'required',
        'tanggal_order' => 'required',
        'juragan'       => 'required',
        'id_pemesan'    => 'required',
        'id_kirimKe'    => 'required',
        // 'keterangan' => 'required',
        'produk' => 'required',
        // 'biaya' 		=> 'required'
        // detail pesanan bersifat opsional
        'rincian.deadline' => 'permit_empty|valid_date',
        'rincian.ambil'    => 'permit_empty|valid_date',
        'rincian.kembali'  => 'permit_empty|valid_date',
        'rincian.jaminan'  => 'permit_empty|max_length[100]',
    ];
    /**
     * Kategori pesanan Rombongan: yang dijual tidak dituliskan per baris produk
     * (kartunya memang disembunyikan di layar itu), pekerjaan tercatat lewat
     * daftar anggota. Karena itu `produk` tidak bisa diminta di sini - dulu ia
     * tetap wajib sehingga nota rombongan ditolak server tanpa satu pesan pun
     * yang sampai ke pengguna. Yang menahan sekarang daftar anggotanya.
     */
    public $addInvoiceRombongan = [
        'juragan'       => 'required',
        'pengguna'      => 'required',
        'asal_orderan'  => 'required',
        'tanggal_order' => 'required',
        'id_pemesan'    => 'required',
        'id_kirimKe'    => 'required',
        'anggota'       => 'required',
        'rincian.deadline' => 'permit_empty|valid_date',
        'rincian.ambil'    => 'permit_empty|valid_date',
        'rincian.kembali'  => 'permit_empty|valid_date',
        'rincian.jaminan'  => 'permit_empty|max_length[100]',
    ];
    public $updateInvoice = [
        'id_invoice'    => 'required',
        'juragan'       => 'required',
        'pengguna'      => 'required',
        'asal_orderan'  => 'required',
        'tanggal_order' => 'required',
        'juragan'       => 'required',
        'id_pemesan'    => 'required',
        'id_kirimKe'    => 'required',
        // 'keterangan' => 'required',
        'produk' => 'required',
        // 'biaya' 		=> 'required'
        // detail pesanan bersifat opsional
        'rincian.deadline' => 'permit_empty|valid_date',
        'rincian.ambil'    => 'permit_empty|valid_date',
        'rincian.kembali'  => 'permit_empty|valid_date',
        'rincian.jaminan'  => 'permit_empty|max_length[100]',
    ];
    /** Sama seperti addInvoiceRombongan: menyunting nota rombongan tidak boleh
     *  menuntut baris produk yang memang tidak ada di kategori itu. */
    public $updateInvoiceRombongan = [
        'id_invoice'    => 'required',
        'juragan'       => 'required',
        'pengguna'      => 'required',
        'asal_orderan'  => 'required',
        'tanggal_order' => 'required',
        'id_pemesan'    => 'required',
        'id_kirimKe'    => 'required',
        'anggota'       => 'required',
        'rincian.deadline' => 'permit_empty|valid_date',
        'rincian.ambil'    => 'permit_empty|valid_date',
        'rincian.kembali'  => 'permit_empty|valid_date',
        'rincian.jaminan'  => 'permit_empty|max_length[100]',
    ];
    /**
     * Nama orang (pemesan dan penerima) dipakai bersama di layar pelanggan dan
     * di kartu alamat orderan. Angka di kolom nama hampir selalu jari yang
     * nyasar ke HP - dan begitu tersimpan, nama itu ikut di seluruh laporan.
     * Yang boleh: huruf (termasuk beraksen), spasi, titik, apostrof, hubung.
     */
    public $tambahPelanggan = [
        'nama_pelanggan' => 'required|max_length[100]|regex_match[/^[\p{L}\p{M}\s\'\.\-]+$/u]',
    ];
    // lok api masih 'en', jadi aturan nama di atas dibahasakan di sini
    public $tambahPelanggan_errors = [
        'nama_pelanggan' => [
            'required'    => 'Nama pelanggan belum diisi.',
            'max_length'  => 'Nama pelanggan terlalu panjang (maksimal 100 karakter).',
            'regex_match' => 'Nama hanya boleh berisi huruf.',
        ],
    ];
    public $simpanProgress = [
        'id_invoice' => 'required|integer',
        'status'     => 'required',
        'stat'       => 'required',
        // 'keterangan' 	=> 'required'
    ];
    /**
     * Jawaban validasi orderan dikirim apa adanya ke popup di halaman tulis,
     * jadi teks bawaan "The produk field is required" akan terbaca sebagai
     * bahasa asing di tengah pekerjaan CS. Dibahasakan di sini, bukan di JS,
     * supaya jalur non-AJAX dan API lain membawa pesan yang sama.
     */
    public $addInvoice_errors = [
        'produk'        => ['required' => 'Tambahkan dulu minimal satu produk ke daftar orderan.'],
        'juragan'       => ['required' => 'Tokonya belum dipilih.'],
        'pengguna'      => ['required' => 'Admin/CS yang melayani belum dipilih.'],
        'asal_orderan'  => ['required' => 'Asal orderan belum dipilih.'],
        'tanggal_order' => ['required' => 'Tanggal order belum diisi.'],
        'id_pemesan'    => ['required' => 'Data pemesan belum diisi.'],
        'id_kirimKe'    => ['required' => 'Data penerima belum diisi.'],
    ];
    public $addInvoiceRombongan_errors = [
        'anggota'       => ['required' => 'Isi dulu daftar anggota rombongannya - kategori Rombongan tidak memakai baris produk.'],
        'juragan'       => ['required' => 'Tokonya belum dipilih.'],
        'pengguna'      => ['required' => 'Admin/CS yang melayani belum dipilih.'],
        'asal_orderan'  => ['required' => 'Asal orderan belum dipilih.'],
        'tanggal_order' => ['required' => 'Tanggal order belum diisi.'],
        'id_pemesan'    => ['required' => 'Data pemesan belum diisi.'],
        'id_kirimKe'    => ['required' => 'Data penerima belum diisi.'],
    ];
    public $updateInvoice_errors = [
        'produk'     => ['required' => 'Tambahkan dulu minimal satu produk ke daftar orderan.'],
        'id_invoice' => ['required' => 'Nomor notanya tidak dikenali.'],
        'juragan'    => ['required' => 'Tokonya belum dipilih.'],
        'pengguna'   => ['required' => 'Admin/CS yang melayani belum dipilih.'],
    ];
    public $updateInvoiceRombongan_errors = [
        'anggota'    => ['required' => 'Isi dulu daftar anggota rombongannya - kategori Rombongan tidak memakai baris produk.'],
        'id_invoice' => ['required' => 'Nomor notanya tidak dikenali.'],
        'juragan'    => ['required' => 'Tokonya belum dipilih.'],
        'pengguna'   => ['required' => 'Admin/CS yang melayani belum dipilih.'],
    ];
    public $tambahPembayaran = [
        'invoice_id'       => 'required|integer',
        'sumber_dana'      => 'required',
        // nominal wajib lebih dari nol: minus akan mengurangi pembayaran yang sudah
        // masuk, nol hanya membuat baris kosong yang tidak pernah bisa dinilai
        'total_pembayaran' => 'required|integer|greater_than[0]',
        // 'status' 			=> 'required',
        'tanggal_pembayaran' => 'required',
    ];
    // aturan ini dipakai halaman admin dan CS, jadi pesannya dibahasakan di sini
    public $tambahPembayaran_errors = [
        'total_pembayaran' => [
            'required'     => 'Jumlah dana belum diisi.',
            'integer'      => 'Jumlah dana hanya boleh angka bulat, tanpa minus atau desimal.',
            'numeric'      => 'Jumlah dana hanya boleh angka bulat, tanpa minus atau desimal.',
            'greater_than' => 'Jumlah dana harus lebih dari nol.',
        ],
    ];
    public $updatePembayaran = [
        'id_pembayaran' => 'required|integer',
        'invoice_id'    => 'required|integer',
        'status'        => 'required',
    ];
    public $tambahPengiriman = [
        'invoice_id'    => 'required|integer',
        'kurir'         => 'required',
        'ongkir'        => 'required|integer',
        'qty'           => 'required',
        'resi'          => 'required',
        'tanggal_kirim' => 'required',
    ];
    public $addStok = [
        'juragan_id' => 'required|integer',
        'kode'       => 'required|max_length[20]',
        'ukuran'     => 'permit_empty|max_length[6]',
        // harga boleh kosong: tombol "+ Varian" pada kartu tidak menanyakannya,
        // nilainya diwarisi dari saudara sekartu oleh controller
        'harga'      => 'permit_empty|integer',
        // stok awal minimal 1: menambah varian baru tanpa stok tidak masuk akal,
        // koreksi gudang (nol/negatif) dilakukan lewat sunting, bukan lewat tambah
        'stok'       => 'required|integer|greater_than_equal_to[1]',
        'keterangan' => 'permit_empty|max_length[500]',
    ];
    // lok api masih 'en', jadi pesan stok di atas dibahasakan agar tidak mencolok
    public $addStok_errors = [
        'stok' => [
            'required'              => 'Stok wajib diisi.',
            'integer'               => 'Stok harus berupa angka bulat.',
            'greater_than_equal_to' => 'Stok minimal 1, tidak boleh kosong atau nol.',
        ],
    ];
    public $updateStok = [
        'juragan_id' => 'required|integer',
        'id_stok'    => 'required|integer',
        'kode'       => 'required|max_length[20]',
        'ukuran'     => 'permit_empty|max_length[6]',
        'harga'      => 'required|integer',
        'stok'       => 'required|integer',
        'keterangan' => 'permit_empty|max_length[500]',
    ];

    // Pencatatan COD: keperluan, status, nama CS, dan sumber informasi boleh
    // ditambah baru lewat pensil di dropdown, jadi yang diuji di sini
    // panjangnya saja; kata-katanya ikut tersimpan sebagai pilihan baru.
    public $addCod = [
        'tanggal'       => 'required|valid_date',
        'jam'           => 'required|regex_match[/^([01][0-9]|2[0-3]):[0-5][0-9]$/]',
        'nama_customer' => 'required|max_length[100]|alpha_space',
        'no_hp'         => 'required|numeric|max_length[13]',
        'keperluan'     => 'required|max_length[80]',
        'status'        => 'required|max_length[80]',
        'catatan'       => 'required|max_length[5000]',
        'cs'            => 'required|max_length[80]',
        'sumber'        => 'required|max_length[80]',
    ];
    public $updateCod = [
        'id_cod'        => 'required|integer',
        'tanggal'       => 'required|valid_date',
        'jam'           => 'required|regex_match[/^([01][0-9]|2[0-3]):[0-5][0-9]$/]',
        'nama_customer' => 'required|max_length[100]|alpha_space',
        'no_hp'         => 'required|numeric|max_length[13]',
        'keperluan'     => 'required|max_length[80]',
        'status'        => 'required|max_length[80]',
        'catatan'       => 'required|max_length[5000]',
        'cs'            => 'required|max_length[80]',
        'sumber'        => 'required|max_length[80]',
    ];
    // lok api masih 'en', jadi pesan aturan di atas dibahasakan agar tidak
    // mencolok di form
    public $addCod_errors = [
        'tanggal'       => ['required' => 'Tanggal wajib diisi.', 'valid_date' => 'Tanggal tidak berlaku.'],
        'jam'           => [
            'required'    => 'Jam datang wajib diisi.',
            'regex_match' => 'Jam memakai format 24 jam, contoh 14:30.',
        ],
        'nama_customer' => [
            'required'    => 'Nama customer wajib diisi.',
            'max_length'  => 'Nama customer maksimal 100 huruf.',
            'alpha_space' => 'Nama customer hanya boleh huruf dan spasi.',
        ],
        'no_hp' => [
            'required'    => 'Nomor HP wajib diisi.',
            'max_length'  => 'Nomor HP maksimal 13 angka.',
            'numeric'     => 'Nomor HP hanya boleh angka.',
        ],
        'keperluan' => ['required' => 'Keperluan wajib diisi.', 'max_length' => 'Keperluan maksimal 80 huruf.'],
        'status'    => ['required' => 'Status transaksi wajib diisi.', 'max_length' => 'Status maksimal 80 huruf.'],
        'catatan'   => ['required' => 'Catatan wajib diisi.', 'max_length' => 'Catatan terlalu panjang.'],
        'cs'        => ['required' => 'Nama CS yang handle wajib diisi.', 'max_length' => 'Nama CS maksimal 80 huruf.'],
        'sumber'    => ['required' => 'Sumber informasi wajib diisi.', 'max_length' => 'Sumber informasi maksimal 80 huruf.'],
    ];
    // grup sunting memakai pesan grup tambah; lihat penyatuan di konstruktor
    public $updateCod_errors = [];
}
