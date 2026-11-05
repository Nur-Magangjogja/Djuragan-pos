<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CrmModel;
use App\Models\JuraganModel;
use Config\Database;

class Crm extends BaseController
{
    protected CrmModel $crmModel;

    public function __construct()
    {
        $this->crmModel = new CrmModel();
    }

    /**
     * Dasbor Ringkasan CRM yang Bercerita
     */
    public function index()
    {
        $ringkasan  = $this->crmModel->getRingkasan();
        $reminders  = $this->crmModel->getReminders();

        // Ambil beberapa follow-up aktif hari ini
        $followupHariIni = $this->crmModel->getFollowupList(['status' => 'menunggu'], 5);

        $data = [
            'title'           => 'Dashboard CRM',
            'ringkasan'       => $ringkasan,
            'reminders'       => array_slice($reminders, 0, 6),
            'followupHariIni' => $followupHariIni,
        ];

        return view('admin/crm/dasbor', $data);
    }

    /**
     * Halaman Manajemen & Segmentasi Pelanggan (dengan filter status & tag diperkuat)
     */
    public function pelanggan()
    {
        $segmen = $this->request->getGet('segmen') ?? 'semua';
        $status = $this->request->getGet('status') ?? 'semua';
        $tagId  = (int) ($this->request->getGet('tag_id') ?? 0);
        $cari   = trim($this->request->getGet('cari') ?? '');
        $page   = max(1, (int) ($this->request->getGet('page') ?? 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $filters = [
            'segmen' => $segmen,
            'status' => $status,
            'tag_id' => $tagId,
            'cari'   => $cari,
        ];

        $daftarPelanggan = $this->crmModel->getDaftarPelanggan($filters, $limit, $offset);
        $totalData       = $this->crmModel->countDaftarPelanggan($filters);
        $totalPages      = ceil($totalData / $limit);
        $allTags         = $this->crmModel->getAllTags();

        $data = [
            'title'           => 'CRM - Data Pelanggan',
            'daftarPelanggan' => $daftarPelanggan,
            'segmen'          => $segmen,
            'status'          => $status,
            'tagId'           => $tagId,
            'allTags'         => $allTags,
            'cari'            => $cari,
            'page'            => $page,
            'totalPages'      => $totalPages,
            'totalData'       => $totalData,
        ];

        return view('admin/crm/pelanggan', $data);
    }

    /**
     * Halaman Customer 360 / Profil Detail Pelanggan
     */
    public function pelanggan_detail(int $id)
    {
        // tautan nota di daftar tagihan dibangun helper yang sama dengan lonceng
        // notifikasi, jadi tidak ada dua cara membuka satu nota
        helper('fungsi');

        $pelanggan = $this->crmModel->getPelangganDetail($id);
        if (!$pelanggan) {
            return redirect()->to(site_url('admin/crm/pelanggan'))->with('error', 'Pelanggan tidak ditemukan.');
        }

        // Ringkasan nilai & kontribusi
        $ringkasanNilai = $this->crmModel->getRingkasanNilaiPelanggan($id);

        // Riwayat transaksi POS & invoice
        $transaksiList = $this->crmModel->getRiwayatTransaksiPelanggan($id);

        // Riwayat mutasi pembayaran
        $pembayaranList = $this->crmModel->getRiwayatPembayaranPelanggan($id);

        // Timeline aktivitas multi-channel
        $timeline = $this->crmModel->getTimelinePelanggan($id);

        // Follow-up khusus customer ini
        $followupList = $this->crmModel->getFollowupList(['pelanggan_id' => $id], 50);

        // Master tag untuk pilihan tambah tag
        $allTags = $this->crmModel->getAllTags();

        // Template pesan cepat
        $db = Database::connect();
        $templates = $db->table('crm_template')->get()->getResultArray();

        $data = [
            'title'          => 'Customer 360 - ' . $pelanggan['nama_pelanggan'],
            'pelanggan'      => $pelanggan,
            'nilai'          => $ringkasanNilai,
            'transaksiList'  => $transaksiList,
            'pembayaranList' => $pembayaranList,
            'timeline'       => $timeline,
            'followupList'   => $followupList,
            'allTags'        => $allTags,
            'templates'      => $templates,
        ];

        return view('admin/crm/pelanggan_detail', $data);
    }

    /**
     * Simpan status CRM, Opt-in broadcast, dan Catatan Customer
     */
    public function simpan_profil(int $id)
    {
        $statusCrm       = $this->request->getPost('status_crm');
        $optInBroadcast  = $this->request->getPost('opt_in_broadcast') !== null ? (int) $this->request->getPost('opt_in_broadcast') : 1;
        $catatan         = trim($this->request->getPost('catatan') ?? '');

        $this->crmModel->simpanProfilCrm($id, [
            'status_crm'       => $statusCrm,
            'opt_in_broadcast' => $optInBroadcast,
            'catatan'          => $catatan,
        ]);

        // Catat di timeline jika catatan baru diisi
        $catatanBaru = trim($this->request->getPost('catatan_baru') ?? '');
        if (!empty($catatanBaru)) {
            $user = pengguna_sesi();
            $this->crmModel->tambahAktivitas([
                'pelanggan_id' => $id,
                'tipe'         => 'catatan',
                'judul'        => 'Catatan Staff Ditambahkan',
                'deskripsi'    => $catatanBaru,
                'user_id'      => $user,
            ]);
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['status' => 'success', 'message' => 'Profil CRM berhasil diperbarui.']);
        }

        return redirect()->back()->with('pesan', 'Profil customer berhasil diperbarui.');
    }

    /**
     * Simpan Data Ukuran dan Preferensi Pakaian Customer
     */
    public function simpan_ukuran(int $id)
    {
        $detailUkuran = [
            // Atasan / Baju / Jas
            'lingkar_dada'     => trim($this->request->getPost('lingkar_dada') ?? ''),
            'panjang_baju'     => trim($this->request->getPost('panjang_baju') ?? ''),
            'lebar_bahu'       => trim($this->request->getPost('lebar_bahu') ?? ''),
            'panjang_lengan'   => trim($this->request->getPost('panjang_lengan') ?? ''),
            'lingkar_leher'    => trim($this->request->getPost('lingkar_leher') ?? ''),
            'panjang_jas'      => trim($this->request->getPost('panjang_jas') ?? ''),
            // Bawahan / Celana
            'lingkar_pinggang' => trim($this->request->getPost('lingkar_pinggang') ?? ''),
            'panjang_celana'   => trim($this->request->getPost('panjang_celana') ?? ''),
            'lingkar_paha'     => trim($this->request->getPost('lingkar_paha') ?? ''),
            'lingkar_pinggul'  => trim($this->request->getPost('lingkar_pinggul') ?? ''),
            'pesak'            => trim($this->request->getPost('pesak') ?? ''),
            'lingkar_kaki'     => trim($this->request->getPost('lingkar_kaki') ?? ''),
            // Catatan kustom
            'catatan_ukuran'   => trim($this->request->getPost('catatan_ukuran') ?? ''),
        ];

        $payload = [
            'ukuran_baju_standar'   => trim($this->request->getPost('ukuran_baju_standar') ?? ''),
            'ukuran_celana_standar' => trim($this->request->getPost('ukuran_celana_standar') ?? ''),
            'ukuran_jas_standar'    => trim($this->request->getPost('ukuran_jas_standar') ?? ''),
            'preferensi_warna'      => trim($this->request->getPost('preferensi_warna') ?? ''),
            'preferensi_bahan'      => trim($this->request->getPost('preferensi_bahan') ?? ''),
            'preferensi_model'      => trim($this->request->getPost('preferensi_model') ?? ''),
            'detail_ukuran'         => $detailUkuran,
        ];

        $this->crmModel->simpanUkuranPreferensi($id, $payload);

        // Catat ke timeline
        $user = pengguna_sesi();
        $this->crmModel->tambahAktivitas([
            'pelanggan_id' => $id,
            'tipe'         => 'fitting',
            'judul'        => 'Data Ukuran & Preferensi Pakaian Diperbarui',
            'deskripsi'    => 'Data ukuran tubuh dan preferensi model/bahan diperbarui oleh staff.',
            'user_id'      => $user,
        ]);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['status' => 'success', 'message' => 'Data ukuran & preferensi berhasil disimpan.']);
        }

        return redirect()->back()->with('pesan', 'Data ukuran & preferensi pakaian berhasil disimpan.');
    }

    /**
     * Tambah Aktivitas Timeline (Fitting, Telepon, Kunjungan, dll)
     */
    public function tambah_aktivitas(int $id)
    {
        $tipe      = $this->request->getPost('tipe') ?? 'catatan';
        $judul     = trim($this->request->getPost('judul') ?? '');
        $deskripsi = trim($this->request->getPost('deskripsi') ?? '');
        $invoiceId = (int) ($this->request->getPost('invoice_id') ?? 0);
        $user      = pengguna_sesi();

        if (empty($judul)) {
            $judul = ucfirst($tipe) . ' Customer';
        }

        $this->crmModel->tambahAktivitas([
            'pelanggan_id' => $id,
            'tipe'         => $tipe,
            'judul'        => $judul,
            'deskripsi'    => $deskripsi,
            'invoice_id'   => $invoiceId > 0 ? $invoiceId : null,
            'user_id'      => $user,
        ]);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['status' => 'success', 'message' => 'Aktivitas berhasil dicatat ke timeline.']);
        }

        return redirect()->back()->with('pesan', 'Aktivitas berhasil ditambahkan ke timeline.');
    }

    /**
     * Tambah Tag ke Pelanggan
     */
    public function tambah_tag(int $id)
    {
        $tagId    = (int) $this->request->getPost('tag_id');
        $namaBaru = trim($this->request->getPost('nama_tag_baru') ?? '');
        $warna    = trim($this->request->getPost('warna') ?? 'primary');

        if ($tagId > 0) {
            $this->crmModel->tambahTagPelanggan($id, $tagId);
        } elseif (!empty($namaBaru)) {
            $newTagId = $this->crmModel->buatMasterTag($namaBaru, $warna);
            $this->crmModel->tambahTagPelanggan($id, $newTagId);
        }

        return redirect()->back()->with('pesan', 'Tag customer berhasil diperbarui.');
    }

    /**
     * Hapus Tag dari Pelanggan
     */
    public function hapus_tag(int $id, int $tag_id)
    {
        $this->crmModel->hapusTagPelanggan($id, $tag_id);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['status' => 'success']);
        }

        return redirect()->back()->with('pesan', 'Tag berhasil dihapus.');
    }

    /**
     * Halaman Tagihan Terpadu: daftar tagihan invoice + Follow-up Customer.
     * Tab Pengingat Otomatis dan pemisah "Siap Ditagih" sudah dilebur: seluruh
     * piutang tampil dalam satu tabel yang sama.
     */
    public function followup()
    {
        // nama tahap produksi diambil dari master helper yang sama dengan Tracking
        // Transaksi, jadi label di daftar tagihan tidak pernah berbeda dengan di nota
        helper('fungsi');

        $tab = $this->request->getGet('tab') ?? 'tagihan'; // tagihan, customer
        // tab 'reminder' tidak lagi punya isi; tautan lama diarahkan ke daftar tagihan
        if (! in_array($tab, ['tagihan', 'customer'], true)) {
            $tab = 'tagihan';
        }
        $cari   = trim($this->request->getGet('cari') ?? '');
        $status = $this->request->getGet('status') ?? 'menunggu';

        // rentang tanggal pesan: hanya format Y-m-d yang dipercaya, nilai aneh dibuang
        // (bukan ditolak dengan pesan); kalau terbalik, keduanya ditukar saja
        $awal   = (string) ($this->request->getGet('awal') ?? '');
        $akhir  = (string) ($this->request->getGet('akhir') ?? '');
        if ($awal !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $awal)) {
            $awal = '';
        }
        if ($akhir !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $akhir)) {
            $akhir = '';
        }
        if ($awal !== '' && $akhir !== '' && $awal > $akhir) {
            [$awal, $akhir] = [$akhir, $awal];
        }

        // keranjang status barang untuk kartu saringan; kode tak dikenal berarti "semua"
        $statusBarang = (string) ($this->request->getGet('status_barang') ?? '');
        if (! in_array($statusBarang, ['belum', 'proses', 'packing', 'terkirim'], true)) {
            $statusBarang = '';
        }

        // 1. Data Follow-up Customer
        $followupList = $this->crmModel->getFollowupList([
            'tipe'   => 'customer',
            'status' => $status !== 'semua' ? $status : '',
            'cari'   => $cari,
        ], 50);

        // 2. Data Follow-up Tagihan
        // satu tabel utuh: seluruh piutang yang belum lunas tampil bersamaan,
        // termasuk yang barangnya masih dikerjakan — pemisah "siap ditagih" dilepas;
        // kartu status barang dan rentang tanggal menyempitkan daftar lewat saringPiutang
        $page                 = max(1, (int) ($this->request->getGet('page') ?? 1));
        $limit                = 25;
        $offset               = ($page - 1) * $limit;
        $invoicesTagihan      = $this->crmModel->getInvoiceBelumLunas($cari, $limit, $offset, 'semua', $awal, $akhir, $statusBarang);
        $totalInvoicesTagihan = $this->crmModel->countInvoiceBelumLunas($cari, 'semua', $awal, $akhir, $statusBarang);
        $totalPagesTagihan    = (int) ceil($totalInvoicesTagihan / $limit);

        // angka tiap kartu status; sengaja tidak ikut dipotong $statusBarang supaya
        // pengguna tetap melihat isi keranjang lain saat satu kartu sedang aktif
        $perStatus = $this->crmModel->hitungStatusBelumLunas($cari, $awal, $akhir);

        // Template tagihan
        $db = Database::connect();
        $templateTagihan = $db->table('crm_template')->where('kode', 'tagihan_belum_lunas')->get()->getRowArray();

        $data = [
            'title'                => 'Pusat Tagihan & Follow-up',
            'tab'                  => $tab,
            'cari'                 => $cari,
            'status'               => $status,
            'page'                 => $page,
            'limit'                => $limit,
            'awal'                 => $awal,
            'akhir'                => $akhir,
            'statusBarang'         => $statusBarang,
            'perStatus'            => $perStatus,
            'tahap'                => tahap_produksi(),
            'totalPagesTagihan'    => $totalPagesTagihan,
            'followupList'         => $followupList,
            'invoicesTagihan'      => $invoicesTagihan,
            'totalInvoicesTagihan' => $totalInvoicesTagihan,
            'templateTagihan'      => $templateTagihan['pesan'] ?? '',
            // popup "Catat Penagihan" menulis catatan, jadi petanya harus sekopi
            // dengan pagar yang dipakai Tugas::simpanCatatan
            'toko'                 => $this->tokoCatatan(),
        ];

        return view('admin/crm/followup', $data);
    }

    /**
     * Simpan Follow-up Baru
     */
    public function simpan_followup()
    {
        $pelangganId = (int) $this->request->getPost('pelanggan_id');
        $judul       = trim($this->request->getPost('judul') ?? '');
        $kategori    = trim($this->request->getPost('kategori') ?? 'lainnya');
        $catatan     = trim($this->request->getPost('catatan') ?? '');
        $tglTempo    = $this->request->getPost('tanggal_jatuh_tempo') ?: date('Y-m-d');
        $invoiceId   = (int) ($this->request->getPost('invoice_id') ?? 0);
        $user        = pengguna_sesi();

        if (empty($pelangganId) || empty($judul)) {
            return redirect()->back()->with('error', 'Pelanggan dan Judul follow-up wajib diisi.');
        }

        $this->crmModel->simpanFollowup([
            'pelanggan_id'        => $pelangganId,
            'tipe'                => 'customer',
            'kategori'            => $kategori,
            'judul'               => $judul,
            'catatan'             => $catatan,
            'tanggal_jatuh_tempo' => $tglTempo,
            'invoice_id'          => $invoiceId > 0 ? $invoiceId : null,
            'user_id'             => $user,
        ]);

        return redirect()->back()->with('pesan', 'Jadwal follow-up berhasil dibuat.');
    }

    /**
     * Selesaikan Follow-up
     */
    public function selesaikan_followup(int $id)
    {
        $user    = pengguna_sesi();
        $catatan = trim($this->request->getPost('catatan_penyelesaian') ?? '');

        $this->crmModel->selesaikanFollowup($id, $user, $catatan);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['status' => 'success', 'message' => 'Follow-up berhasil ditandai selesai.']);
        }

        return redirect()->back()->with('pesan', 'Follow-up berhasil diselesaikan.');
    }

    /**
     * Halaman Deteksi & Merge Customer Duplikat
     */
    public function duplikat()
    {
        $semuaDuplikat = $this->crmModel->getDuplikatList();
        $perHalaman    = 25;
        $totalDuplikat = count($semuaDuplikat);
        $totalPages    = (int) ceil($totalDuplikat / $perHalaman);
        $page          = max(1, min($totalPages, (int) ($this->request->getGet('page') ?: 1)));

        $data = [
            'title'         => 'Deteksi & Penggabungan Akun Duplikat',
            'duplikatList'  => array_slice($semuaDuplikat, ($page - 1) * $perHalaman, $perHalaman),
            'page'          => $page,
            'totalPages'    => $totalPages,
            'totalDuplikat' => $totalDuplikat,
        ];

        return view('admin/crm/duplikat', $data);
    }

    /**
     * Eksekusi Merge Dua Customer
     */
    public function proses_merge()
    {
        $idUtama    = (int) $this->request->getPost('id_utama');
        $idDuplikat = (int) $this->request->getPost('id_duplikat');
        $user       = pengguna_sesi();

        if (empty($idUtama) || empty($idDuplikat)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Pilih customer utama dan customer yang akan digabung.']);
        }

        $res = $this->crmModel->mergeCustomer($idUtama, $idDuplikat, $user);

        if ($res['success']) {
            return $this->response->setJSON(['status' => 'success', 'message' => $res['message']]);
        }

        return $this->response->setJSON(['status' => 'error', 'message' => $res['message']]);
    }

    /**
     * Follow-up Tagihan Invoice Belum Lunas
     */
    public function tagihan()
    {
        $cari = trim($this->request->getGet('cari') ?? '');

        // daftar penuh tidak pernah dimuat sekaligus: 25 baris per halaman, dan
        // angkanya diambil dari query COUNT terpisah supaya "Ditemukan ..." tetap
        // berarti total, bukan banyaknya baris di halaman ini saja
        $perHalaman    = 25;
        $totalTagihan  = $this->crmModel->countInvoiceBelumLunas($cari);
        $totalPages    = (int) ceil($totalTagihan / $perHalaman);
        $page          = max(1, min(max($totalPages, 1), (int) ($this->request->getGet('page') ?: 1)));
        $invoices      = $this->crmModel->getInvoiceBelumLunas($cari, $perHalaman, ($page - 1) * $perHalaman);

        // Ambil template tagihan
        $db = Database::connect();
        $template = $db->table('crm_template')->where('kode', 'tagihan_belum_lunas')->get()->getRowArray();

        $data = [
            'title'        => 'Follow-up Tagihan Belum Lunas',
            'invoices'     => $invoices,
            'cari'         => $cari,
            'template'     => $template['pesan'] ?? '',
            'page'         => $page,
            'totalPages'   => $totalPages,
            'totalTagihan' => $totalTagihan,
            // sama seperti followup(): hanya toko yang boleh ditulis akun ini
            'toko'         => $this->tokoCatatan(),
        ];

        return view('admin/crm/tagihan', $data);
    }

    /**
     * Peta id_toko => nama untuk form pencatatan manual. Sengaja mengikuti
     * `tokoBoleh()` — pagar yang sama dipakai Tugas::simpanCatatan — supaya layar
     * tidak pernah menawarkan toko yang ujungnya ditolak saat disimpan.
     *
     * @return array<int, string>
     */
    private function tokoCatatan(): array
    {
        $ids = $this->tokoBoleh();

        if ($ids === []) {
            return [];
        }

        $peta = [];

        foreach ((new JuraganModel())->whereIn('id_juragan', $ids)->orderBy('nama_juragan', 'ASC')->findAll() as $t) {
            $peta[(int) $t->id_juragan] = $t->nama_juragan;
        }

        return $peta;
    }
}
