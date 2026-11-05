<?php

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\FeedbackModel;
use App\Models\FotoModel;
use App\Models\InvoiceModel;
use App\Models\JuraganModel;
use App\Models\NotifikasiModel;
use App\Models\PembayaranModel;
use App\Models\PoinPenjualanModel;
use App\Models\UkuranKomponenModel;
use App\Models\UserModel;
use CodeIgniter\I18n\Time;

class Invoices extends BaseController
{
    public function tulis()
    {
        $komponen = new UkuranKomponenModel();

        $data = [
            'title'           => 'Tulis Orderan Baru',
            'jenis_produk'    => $komponen->jenis(),
            'template_ukuran' => $komponen->semuaTemplate(),
        ];

        return view('user/invoice/tulis', $data);
    }

    public function save()
    {
        $validation = \Config\Services::validation();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('addInvoice');
        }

        if ($this->request->isAJAX()) {
            if (! $validation->withRequest($this->request)->run()) {
                $this->response->setStatusCode(406);
                $errors = $validation->getErrors();

                return $this->response->setJSON($errors);
            }

            $db           = \Config\Database::connect();
            $userModel    = new UserModel();
            $invModel     = new InvoiceModel();
            $juraganModel = new JuraganModel();

            $user_id    = (int) $this->request->getPost('pengguna');
            /** @var object|null $t */
            $t          = $userModel->find($user_id);
            $seri       = strtoupper(first_letter($t->name) . time());
            $juragan_id = $this->request->getPost('juragan');
            $rincian    = $this->request->getPost('rincian');

            // orderan hanya boleh dititipkan ke toko yang dipegang akun ini
            if (! $this->bolehToko((int) $juragan_id)) {
                $this->response->setStatusCode(406);

                return $this->response->setJSON(['juragan' => 'Toko ini tidak berada di bawah akun Anda.']);
            }

            $data_invoice = [
                'tanggal_pesan'  => $this->request->getPost('tanggal_order'),
                'seri'           => $seri,
                'pemesan_id'     => $this->request->getPost('id_pemesan'),
                'kirimKepada_id' => $this->request->getPost('id_kirimKe'),
                'juragan_id'     => $juragan_id,
                'user_id'        => $user_id,
                'keterangan'     => ($this->request->getPost('keterangan') !== '' ? trim($this->request->getPost('keterangan')) : null),
                'deadline'       => deadline_iso($rincian),
                'rincian'        => rincian_json($rincian, array_keys(meta_rincian('pesanan')), ['tipe' => array_keys(tipe_pesanan()), 'orderan' => ['satuan', 'rombongan']]),
            ];

            // sama seperti di panel admin: satu orderan, satu transaksi
            $db->transBegin();

            $invModel->insert($data_invoice);
            $invoice_id = (int) $db->insertID();
            $galat      = [];

            if ($invoice_id > 0) {
                // simpan asal orderan
                $data_asal = [
                    'invoice_id' => $invoice_id,
                    'source_id'  => $this->request->getPost('asal_orderan'),
                    'label'      => ($this->request->getPost('label') !== '' ? trim($this->request->getPost('label')) : null),
                ];
                $db->table('label_invoice')->insert($data_asal);

                // simpan produk, lalu ukuran terstruktur per item
                $produk = produk_dibeli($this->request->getPost('produk'), $invoice_id);

                // tautan stok diperiksa di server: id_stok datang dari peramban,
                // jadi bisa saja milik toko lain atau memang tidak ada
                $taut = tautkan_stok($produk['baris'], (int) $juragan_id);
                $peta = sinkron_dibeli($invoice_id, $taut);

                // sama seperti admin: Djuragan hanya kategori dan pengambilan dari
                // stok produk opsional, jadi kecukupan stok tidak membatalkan
                // penyimpanan orderan
                $galat  = array_merge(
                    simpan_ukuran_produk($invoice_id, $peta, $produk['ukuran']),
                    simpan_anggota($invoice_id, $this->request->getPost('anggota_dikirim'), $this->request->getPost('anggota'))
                );

                if ($galat === []) {
                    // simpan biaya
                    if ($this->request->getVar('biaya') !== null) {
                        $biayas = $this->request->getPost('biaya');

                        // tambahkan `invoice_id` untuk tiap biaya
                        $biaya = [];

                        foreach ($biayas as $k => $v) {
                            foreach ($v as $p => $d) {
                                $biaya[$k]['invoice_id'] = $invoice_id;
                                $biaya[$k][$p]           = ($d !== '' ? $d : null);
                            }
                        }
                        $db->table('biaya')->insertBatch($biaya);
                    }

                    // simpan notifikasi, kirim kesemua user
                    simpan_notif(1, $juragan_id, $invoice_id);
                }
            } else {
                $galat['invoice'] = 'Orderan gagal disimpan, coba lagi ya.';
            }

            if ($galat !== [] || ! $db->transStatus()) {
                $db->transRollback();
                $this->response->setStatusCode(406);

                return $this->response->setJSON($galat);
            }

            $db->transCommit();

            // sama seperti panel admin: catatan dari stok ikut membawa foto kartunya
            (new FotoModel())->salin_dari_produk($invoice_id, (int) session()->get('id'));

            $juragan = $juraganModel->byInvoiceId($invoice_id)->getResult()[0]->juragan;
            $ret     = [
                'status' => 'data tersimpan',
                // sama seperti panel admin: foto naik setelah orderan punya id
                'id'     => $invoice_id,
                'url'    => site_url('user/invoices/lihat/' . $juragan . '/semua?cari[kolom]=faktur&cari[q]=' . $seri),
            ];

            return $this->response->setJSON($ret);
        }
    }

    // menampilkan semua invoice
    public function lihat($juragan = '', $hal = 'semua', $kategori = '')
    {
        if (! in_array($hal, ['semua', 'pembayaran', 'cek-bayar', 'dalam-proses', 'belum-proses', 'selesai', 'saring', 'masih-disewa'], true)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // tiap tab punya chip filter sendiri, jadi kategori hanya boleh dari daftar tab ini
        $daftar_kategori = kategori_tab($hal);

        if ($kategori === '') {
            // tab saring dibuka dari kartu dasbor, defaultnya saringan paling luas
            $kategori = match ($hal) {
                'belum-proses' => 'semua',
                'saring'       => 'aktif',
                default        => 'perlu-cek',
            };
        }

        if (! array_key_exists($kategori, $daftar_kategori)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if ($hal === 'cek-bayar') {
            return redirect()->to(site_url('user/invoices/lihat/' . $juragan . '/pembayaran/' . $kategori));
        }

        // pencarian
        $cari       = $this->request->getGet('cari') ?? '';
        $user_id    = session('id');
        $id_juragan = 0;

        if ($juragan === '') {
            $juragan = $this->juraganBy($user_id);

            // akun warisan ada yang belum punya relasi juragan, jadi tidak ada slug untuk
            // dituju; tanpa pagar ini halaman hanya melempar property tak dikenal (HTTP 500).
            // Pesannya dikirim lewat view 404 milik app, bukan PageNotFoundException, karena
            // override 404 di Config/Routes.php membuang semua pesan supaya detail internal
            // router tidak terbaca tamu.
            if (empty($juragan->juragan)) {
                // halaman khusus (tetap berstatus 404) yang menawarkan tombol
                // "minta ditautkan"; tombol mengirim notifikasi prioritas ke inbox
                // Superadmin supaya akun baru ini bisa segera dipakai.
                return response()->setStatusCode(404)->setBody(
                    view('user/belum_juragan', [
                        'title' => 'Akun Belum Ditautkan',
                    ]),
                );
            }

            return redirect()->to('/user/invoices/lihat/' . $juragan->juragan);
        }

        // diijinkan atau tidak
        if ($this->allowedJuragan($user_id, $juragan)) {
            $invModel     = new InvoiceModel();
            $juraganModel = new JuraganModel();
            $juragans     = $juraganModel->where('juragan', $juragan)->findAll();
            $title        = $juragans[0]->nama_juragan;
            $id_juragan   = $juragans[0]->id_juragan;

            $limit = config('Pager')->perPage;
            $page  = (int) $this->request->getGet('page');

            if (! isset($page) || $page === 0 || $page === 1) {
                $page   = 1;
                $offset = 0;
            } else {
                $offset = ($page - 1) * $limit;
                $page   = $page;
            }

            $data = [
                'title'               => 'Transaksi ' . $title,
                'orderan'             => $invModel->getAll($hal, $id_juragan, $cari, $limit, $offset, $kategori),
                'juragan'             => $juragan,
                'juragan_id'          => $id_juragan,
                'nama_toko'           => $title,
                'daftar_toko'         => $this->tokoPilihan(),
                'cari'                => $cari,
                'hal'                 => $hal,
                'kategori'            => $kategori,
                'kategori_pembayaran' => $daftar_kategori,
                'jumlah_kategori'     => in_array($hal, ['pembayaran', 'belum-proses'], true) ? $invModel->countPembayaran($id_juragan, $hal) : [],
                'limit'               => $limit,
                'page'                => $page,
            ];

            return view('user/invoice/lihat', $data);
        }

        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }

    // tambah pembayaran
    public function simpan_pembayaran()
    {
        $validation = \Config\Services::validation();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('tambahPembayaran');
        }

        if ($this->request->isAJAX()) {
            if (! $validation->withRequest($this->request)->run()) {
                $this->response->setStatusCode(406);
                $errors = $validation->getErrors();

                return $this->response->setJSON($errors);
            }

            $juraganModel    = new JuraganModel();
            $pembayaranModel = new PembayaranModel();
            $invModel        = new InvoiceModel();
            $time            = Time::parse($this->request->getPost('tanggal_pembayaran'))->getTimestamp();
            $invoice_id      = $this->request->getPost('invoice_id');
            $nota            = $this->notaMilikToko((int) $invoice_id);

            // pembayaran hanya boleh dicatat untuk nota di toko sendiri
            if ($nota === null) {
                return $this->tolakToko();
            }

            // halaman lama masih bisa menampilkan tombolnya, jadi invoice lunas ditolak di sini juga
            if (tagihan_lunas($nota->status_pembayaran)) {
                $this->response->setStatusCode(406);

                return $this->response->setJSON([
                    'total_pembayaran' => 'Invoice ini sudah lunas, tidak bisa ditambah pembayaran lagi.',
                ]);
            }

            $data = [
                'invoice_id'         => $invoice_id,
                'sumber_dana'        => $this->request->getPost('sumber_dana'),
                'total_pembayaran'   => $this->request->getPost('total_pembayaran'),
                'tanggal_pembayaran' => $time,
            ];

            $pembayaranModel->save($data);

            // simpan notif
            simpan_notif(4, $nota->juragan_id, $invoice_id);

            // update status pembayaran (invoice)
            $status_baru = $invModel->perbaruiStatusPembayaran($invoice_id);

            $juragan = $juraganModel->byInvoiceId($invoice_id)->getResult()[0]->juragan;

            // sama seperti layar admin: setelah mencatat uang, antrean berikutnya
            // adalah pembayaran yang perlu dicek
            return $this->response->setJSON([
                'url' => site_url('user/invoices/lihat/' . $juragan . '/pembayaran/' . kategori_untuk_status_bayar($status_baru) . '?cari[kolom]=id&cari[q]=' . $invoice_id),
            ]);
        }
    }

    /**
     * Catat (atau tarik kembali) pengembalian barang sewaan dari sisi CS.
     *
     * CS yang menulis nota sewa dan yang menerima barangnya kembali di counter,
     * jadi tombolnya ada di daftar transaksinya juga. Aturannya sama persis dengan
     * versi admin: satu penanda dibaca oleh tab Selesai, stok kolam sewa, dan poin.
     */
    public function sewa_kembali()
    {
        if ($this->request->isAJAX()) {
            $invoice_id = (int) $this->request->getPost('invoice_id');

            // nota toko lain tidak boleh disentuh, sama seperti halaman admin
            if ($this->notaMilikToko($invoice_id) === null) {
                return $this->tolakToko();
            }

            $invModel = new InvoiceModel();

            $hasil = $this->request->getPost('aksi') === 'batalkan'
                ? $invModel->batalkanKembali($invoice_id)
                : $invModel->catatKembali($invoice_id, (int) pengguna_sesi(), (string) $this->request->getPost('tanggal'));

            (new PoinPenjualanModel())->sinkron($invoice_id);

            // tanpa url: pemanggilnya cukup memuat ulang halaman yang sedang dibuka,
            // jadi tab dan saringannya tidak berpindah setelah barang kembali
            return $this->response->setJSON([
                'ok'     => $hasil['ok'],
                'status' => $hasil['pesan'],
            ]);
        }

        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }

    // hapus invoice
    public function hapus_orderan()
    {
        if ($this->request->isAJAX()) {
            $invModel   = new InvoiceModel();
            $invoice_id = $this->request->getPost('invoice_id');

            $invModel->delete($invoice_id);

            // siapa yang menghapus dicatat; alasan pembatalan baru diminta di
            // halaman nota admin, jadi kolom itu sengaja masih kosong di sini
            \Config\Database::connect()->table('invoice')
                ->where('id_invoice', (int) $invoice_id)
                ->update(['deleted_by' => pengguna_sesi()]);

            // nota yang sudah tidak ada tidak boleh menyumbang poin gaji lagi;
            // halaman admin sudah melakukan ini, jalur CS belum
            (new PoinPenjualanModel())->cabut((int) $invoice_id);

            return $this->response->setJSON([
                'status' => 'Orderan dihapus',
                'url'    => site_url('user/invoices'),
            ]);
        }

        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }

    public function info_pembayaran()
    {
        if ($this->request->isAJAX()) {
            $pembayaranModel = new PembayaranModel();
            $invoice_id      = $this->request->getGet('id');

            // riwayat pembayaran nota toko lain tidak boleh dibaca
            if ($this->notaMilikToko((int) $invoice_id) === null) {
                return $this->tolakToko();
            }

            $x               = $pembayaranModel->ambil($invoice_id)->get()->getResult();
            $res             = [];

            foreach ($x as $bayar) {
                $res[] = [
                    'id'            => (int) $bayar->id,
                    'nama'          => $bayar->nama,
                    'atas_nama'     => $bayar->atas_nama,
                    'sumber'        => (int) $bayar->sumber,
                    'nominal'       => number_to_currency($bayar->nominal, 'IDR'), //(int) $bayar->nominal,
                    'status'        => (int) $bayar->status,
                    'tanggal_bayar' => (int) $bayar->tanggal_pembayaran,
                    'tanggal_cek'   => ($bayar->tanggal_cek !== null ? (int) $bayar->tanggal_cek : null),
                ];
            }

            return $this->response->setJSON($res);
        }
    }

    /**
     * Permintaan dari akun CS yang belum ditautkan ke juragan mana pun.
     *
     * Menaruh satu notifikasi PRIORITAS (type 24) di kotak masuk setiap akun
     * Superadmin aktif, supaya mereka tahu harus segera menautkan akun ini ke
     * sebuah juragan. juragan_id/invoice_id diisi 0 karena kolomnya NOT NULL dan
     * permintaan ini tidak menunjuk toko/nota tertentu. Klik berulang tidak
     * membanjiri inbox: selama masih ada permintaan yang belum dibaca, tidak
     * dibuat yang baru.
     */
    public function minta_juragan()
    {
        $user_id = (int) session()->get('id');

        if ($user_id <= 0) {
            return redirect()->to('/auth');
        }

        $akun = (new UserModel())->find($user_id);

        if ($akun === null) {
            return redirect()->to('/user');
        }

        $superadmin = (new UserModel())
            ->select('id')
            ->where('level', 'superadmin')
            ->where('status', 'active')
            ->where('deleted_at', null)
            ->findAll();

        if ($superadmin === []) {
            return redirect()->to('/user')->with('gagal', 'Belum ada akun Superadmin yang aktif untuk menerima permintaan ini.');
        }

        // Selalu catat ke Kotak Masukan tiap klik, supaya permintaan tetap muncul
        // (dan angkanya bertambah) walau diklik lebih dari sekali. Ini tidak boleh
        // ditaruh di belakang dedup notifikasi: kalau tidak, permintaan susulan yang
        // masih menunggu balasan jadi hilang dari menu.
        (new FeedbackModel())->catatPrioritas(
            (string) ($akun->name ?: $akun->username),
            'Akun CS "' . (string) $akun->username . '" belum punya juragan dan meminta ditautkan.'
        );

        // Lonceng tetap di-dedup: cukup satu notifikasi belum dibaca per akun, supaya
        // klik berulang tidak membanjiri kotak notifikasi Superadmin.
        $notifikasi = new NotifikasiModel();

        $menunggu = $notifikasi
            ->where('type', 24)
            ->where('from', $user_id)
            ->where('read_at', null)
            ->countAllResults();

        if ($menunggu === 0) {
            $waktu = Time::now(config('App')->appTimezone)->timestamp;

            foreach ($superadmin as $s) {
                $notifikasi->insert([
                    'juragan_id' => 0,
                    'from'       => $user_id,
                    'for'        => (int) $s->id,
                    'invoice_id' => 0,
                    'type'       => 24,
                    'prioritas'  => 1,
                    'created_at' => $waktu,
                ]);
            }
        }

        return redirect()->to('/user')->with('sukses', 'Permintaan sudah dikirim ke Superadmin. Akun kamu akan segera ditautkan ke juragan.');
    }
}
