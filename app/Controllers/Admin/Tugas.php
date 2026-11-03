<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Controllers\Juragan;
use App\Models\ChecklistModel;
use App\Models\CrmModel;
use App\Models\InvoiceModel;
use App\Models\JuraganModel;
use App\Models\PelangganModel;
use App\Models\ShiftModel;
use App\Models\TokoOnlineModel;
use App\Models\TugasModel;
use App\Models\TugasTemplateModel;
use App\Models\UlasanModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Antrean tugas operasional CS.
 *
 * Halaman ini dipakai tiga peran dengan hak yang beda: CS mengambil dan mengerjakan
 * tugas, Admin menetapkan dan memantau, Superadmin melihat semuanya. Yang membatasi
 * adalah filter rute + pagar toko di bawah — bukan penyembunyian tombol, karena
 * aturan transisi status dijaga di TugasModel.
 *
 * Tugas yang tidak punya toko (pemeriksaan email bersama, misalnya) sengaja terlihat
 * oleh semua akun yang boleh membuka halaman ini; yang punya toko hanya terlihat
 * oleh pemegang toko itu.
 */
class Tugas extends BaseController
{
    /** Tab daftar. Kuncinya dipakai di URL, nilainya dipakai di layar. */
    private const TAB = [
        'antrean'   => 'Di antrean',
        'saya'      => 'Pegangan saya',
        'terlambat' => 'Lewat tenggat',
        'selesai'   => 'Selesai',
        'semua'     => 'Semua',
    ];

    private const BATAS = [10, 25, 50];

    /** Jumlah baris butir yang disediakan editor templat; baris kosong dibuang model. */
    private const SLOT_ITEM = 8;

    public function index()
    {
        helper('fungsi');

        $model = new TugasModel();

        // shift yang lewat dan reminder yang jatuh tempo diproses di sini supaya
        // layar tidak pernah menunjukkan angka lama; keduanya berjeda dan hanya
        // menyapu toko akun ini — seluruh toko adalah urusan cron
        $model->operanShiftKalauWaktu();
        $model->reminderKalauWaktu($this->tokoBoleh());

        $s     = $this->saring();
        $batas = $this->batas();
        $page  = $this->halaman();
        $total = $model->jumlah($s);

        // nomor halaman di luar daftar dijepit supaya label "X dari Y" tidak bohong
        $page = max(1, min($page, max(1, (int) ceil($total / $batas))));

        return view('admin/tugas/index', [
            'title'     => 'Tugas lain-lain',
            's'         => $s,
            'baris'     => $model->daftar($s, $batas, ($page - 1) * $batas),
            'total'     => $total,
            'page'      => $page,
            'batas'     => $batas,
            'tab'       => self::TAB,
            'ringkas'   => $model->ringkas($this->tokoBoleh()),
            'milik_saya' => $model->jumlah([
                'dikerjakan_oleh' => (int) pengguna_sesi(),
                'terbuka'         => true,
            ]),
            'toko'      => $this->tokoId(),
            'staf'      => $this->staf(),
            'pilihan'   => [
                'jenis'     => TugasModel::jenisLain(),
                'status'    => TugasModel::STATUS,
                'prioritas' => TugasModel::PRIORITAS,
            ],
            'kabar'     => (string) session()->getFlashdata('sukses'),
            'galat'     => (string) session()->getFlashdata('galat'),
        ]);
    }

    public function lihat(int $id)
    {
        helper('fungsi');

        $model = new TugasModel();
        $tugas = $this->tugasMilikSaya($id);

        return view('admin/tugas/lihat', [
            'title'       => 'Tugas #' . $id,
            'kembali'     => $this->layarAsal((string) $tugas['jenis']),
            'tugas'       => $tugas,
            'riwayat'     => $model->riwayat($id),
            'ulasan'      => (int) $tugas['invoice_id'] > 0
                ? (new UlasanModel())->satu((int) $tugas['invoice_id'])
                : null,
            'staf'        => $this->staf(),
            'boleh_bagi'  => $this->bolehKelola(),
            // membatalkan dan membuka kembali tugas yang dibatalkan: Admin saja;
            // CS tetap boleh melepas pegangannya lewat Operan (kembali ke antrean)
            'boleh_kelola' => $this->bolehKelola(),
            'pilihan'     => [
                'jenis'     => TugasModel::JENIS,
                'status'    => TugasModel::STATUS,
                'prioritas' => TugasModel::PRIORITAS,
            ],
            'kabar'       => (string) session()->getFlashdata('sukses'),
            'galat'       => (string) session()->getFlashdata('galat'),
        ]);
    }

    /**
     * Buat tugas baru. Semua peran boleh: catatan hasil percakapan memang lahir di
     * meja CS, dan Admin yang menetapkan penggarapnya lewat aksi terpisah.
     */
    public function simpan()
    {
        helper('fungsi');

        $aturan = [
            'judul'    => 'required|max_length[200]',
            'tenggat'  => 'permit_empty|valid_date[Y-m-d]',
            'susul'    => 'permit_empty|valid_date[Y-m-d]',
        ];

        if (! $this->validate($aturan)) {
            return redirect()->back()->with('galat', 'Judul wajib diisi (maks 200 karakter) dan tanggal harus benar.')
                ->withInput();
        }

        // jenis yang sudah punya submenu dibuat dari layar itu sendiri, supaya satu
        // tugas tidak pernah lahir di dua tempat dan hilang dari daftar ini
        if (in_array((string) $this->request->getPost('jenis'), TugasModel::PUNYA_SUBMENU, true)) {
            return redirect()->back()->with('galat', 'Jenis itu punya menunya sendiri — buat lewat submenu itu.')->withInput();
        }

        $juragan = (int) $this->request->getPost('juragan_id');

        if ($juragan > 0 && ! $this->bolehToko($juragan)) {
            return redirect()->back()->with('galat', 'Toko pilihan bukan milikmu.');
        }

        $pelanggan = (int) $this->request->getPost('pelanggan_id');

        if ($pelanggan > 0 && (new PelangganModel())->find($pelanggan) === null) {
            return redirect()->back()->with('galat', 'Pelanggan tidak ditemukan.');
        }

        // tugas menaut ke nota lewat nomor seri (angka yang dibaca orang), bukan
        // id mentah; nota toko lain dijawab sama seperti nota tidak ada
        $nota  = 0;
        $seri  = trim((string) $this->request->getPost('seri'));

        if ($seri !== '') {
            $temu = (new InvoiceModel())->where('seri', $seri)->where('deleted_at', null)->first();

            if ($temu === null || ! $this->bolehToko((int) $temu->juragan_id)) {
                return redirect()->back()->with('galat', 'Nomor nota tidak ditemukan di tokomu.');
            }

            $nota = (int) $temu->id_invoice;
        }

        $id = (new TugasModel())->buat([
            'judul'        => (string) $this->request->getPost('judul'),
            'deskripsi'    => (string) $this->request->getPost('deskripsi'),
            'jenis'        => (string) $this->request->getPost('jenis'),
            'prioritas'    => (string) $this->request->getPost('prioritas'),
            'juragan_id'   => $juragan,
            'pelanggan_id' => $pelanggan,
            'invoice_id'   => $nota,
            'tenggat'      => (string) $this->request->getPost('tenggat'),
        ], pengguna_sesi());

        return redirect()->to(site_url('admin/tugas/' . $id))->with('sukses', 'Tugas dibuat.');
    }

    /** CS mengambil satu tugas dari antrean. */
    public function ambil(int $id)
    {
        $this->tugasMilikSaya($id);

        $hasil = (new TugasModel())->ambil($id, (int) pengguna_sesi(), $this->tokoBoleh());

        return $this->kembali($id, $hasil);
    }

    /** Admin menetapkan/memindahkan penggarap. Rutenya khusus admin & superadmin. */
    public function tetapkan(int $id)
    {
        $this->tugasMilikSaya($id);

        $ke    = (int) $this->request->getPost('dikerjakan_oleh');

        if ($ke <= 0 || ! array_key_exists($ke, $this->staf())) {
            return redirect()->back()->with('galat', 'Pilih staf yang aktif.');
        }

        $hasil = (new TugasModel())->tetapkan($id, $ke, (int) pengguna_sesi(),
            (string) $this->request->getPost('alasan'));

        return $this->kembali($id, $hasil);
    }

    /** Mulai, tunggu, selesai, batal, buka kembali — satu jalur untuk semua transisi. */
    public function status(int $id)
    {
        $tugas = $this->tugasMilikSaya($id);

        $baru = (string) $this->request->getPost('status');

        if (! array_key_exists($baru, TugasModel::STATUS)) {
            return redirect()->back()->with('galat', 'Status tidak dikenal.');
        }

        // Membatalkan tugas berarti pekerjaannya berhenti: tidak ada lagi yang
        // menindaklanjuti pelanggan itu. Itu keputusan Admin, bukan keputusan orang
        // yang kebetulan sedang memegang tugasnya. CS yang lepas tangan atau
        // shift-nya habis tidak membatalkan -- dia mengoper, dan tugasnya kembali
        // ke antrean lewat operan().
        if (! $this->bolehKelola() && ($baru === 'cancelled' || (string) $tugas['status'] === 'cancelled')) {
            return redirect()->back()->with('galat', $baru === 'cancelled'
                ? 'Hanya Admin yang bisa membatalkan tugas. Kalau tugasmu harus lepas, pakai Operan supaya kembali ke antrean.'
                : 'Hanya Admin yang bisa membuka kembali tugas yang sudah dibatalkan.');
        }

        $susul = trim((string) $this->request->getPost('susul'));

        $hasil = (new TugasModel())->ubahStatus(
            $id,
            $baru,
            (int) pengguna_sesi(),
            (string) $this->request->getPost('catatan'),
            $susul === '' ? null : (int) strtotime($susul . ' 09:00:00'),
            (string) $this->request->getPost('alasan')
        );

        return $this->kembali($id, $hasil);
    }

    /** Operan manual: tugas kembali ke antrean beserta alasan dan catatan penerus. */
    public function operan(int $id)
    {
        $this->tugasMilikSaya($id);

        $hasil = (new TugasModel())->kembalikan(
            $id,
            (int) pengguna_sesi(),
            (string) $this->request->getPost('alasan'),
            (string) $this->request->getPost('catatan')
        );

        return $this->kembali($id, $hasil);
    }

    /**
     * Angka badge menu. Sama persis dengan yang dipakai navbar saat halaman
     * pertama kali dirender, jadi polling tidak pernah meloncat-loncat.
     */
    public function jumlah()
    {
        $model = new TugasModel();
        $model->operanShiftKalauWaktu();
        $model->reminderKalauWaktu($this->tokoBoleh());

        return $this->response->setJSON([
            'status' => 'success',
            'count'  => $model->jumlah([
                'jenis_not'   => TugasModel::PUNYA_SUBMENU,
                'terbuka'     => true,
                'juragan_ids' => $this->tokoBoleh(),
            ]),
        ]);
    }

    /**
     * Layar Jam shift (Admin & Superadmin): satu jendela kerja per orang per tanggal.
     *
     * Tanpa jendela, sistem menganggap orang itu tidak sedang bertugas dan tidak
     * mengoperkan apa pun — jam yang belum diisi lebih jujur daripada jam karangan.
     */
    public function shift()
    {
        helper('fungsi');

        $tanggal = $this->tanggal((string) $this->request->getGet('tanggal')) ?: date('Y-m-d');

        $sudah = [];

        foreach ((new ShiftModel())->padaTanggal($tanggal) as $s) {
            $sudah[(int) $s['user_id']] = $s;
        }

        $baris = [];

        foreach ($this->stafAktif() as $u) {
            $id     = (int) $u['id'];
            $shift  = $sudah[$id] ?? null;
            $berakhir = $shift === null ? null : (new ShiftModel())->akhir($shift);

            $baris[] = [
                'id'      => $id,
                'nama'    => $u['nama'],
                'level'   => $u['level'],
                'mulai'   => $shift['mulai'] ?? '',
                'selesai' => $shift['selesai'] ?? '',
                'sudah'   => $shift !== null,
                'lewat'   => $berakhir !== null && $berakhir <= time(),
            ];
        }

        return view('admin/tugas/shift', [
            'title'    => 'Jam Shift CS',
            'tanggal'  => $tanggal,
            'baris'    => $baris,
            'operan'   => (new TugasModel())->operanTerakhir(),
            'kabar'    => (string) session()->getFlashdata('sukses'),
            'galat'    => (string) session()->getFlashdata('galat'),
        ]);
    }

    /**
     * Simpan seluruh jam shift sekaligus. Kolom yang dibiarkan kosong kedua jamnya
     * menghapus jendela orang itu pada tanggal tersebut (berarti tidak bertugas).
     */
    public function simpanShift()
    {
        helper('fungsi');

        $mulai    = (array) $this->request->getPost('mulai');
        $selesai  = (array) $this->request->getPost('selesai');
        $tanggal  = $this->tanggal((string) $this->request->getPost('tanggal')) ?: date('Y-m-d');
        $model    = new ShiftModel();
        $known    = array_column($this->stafAktif(), 'id');
        $yangbisa = array_map('intval', $known);

        if (! $model->tanggalSah($tanggal)) {
            return redirect()->back()->with('galat', 'Tanggal tidak valid.');
        }

        $disimpan = 0;
        $dihapus  = 0;
        $galat    = [];

        foreach ($mulai as $id => $awal) {
            $id   = (int) $id;
            $akhir = (string) ($selesai[$id] ?? '');
            $awal = (string) $awal;

            if (! in_array($id, $yangbisa, true)) {
                continue;
            }

            if (trim($awal) === '' && trim($akhir) === '') {
                $dihapus += $model->hapus($id, $tanggal);

                continue;
            }

            if (! $model->jamSah($awal) || ! $model->jamSah($akhir)) {
                $galat[] = 'jam ' . $id . ' bukan HH:MM';

                continue;
            }

            if (trim($awal) === trim($akhir)) {
                $galat[] = 'jam mulai dan selesai tidak boleh sama';

                continue;
            }

            $model->simpan($id, $tanggal, trim($awal), trim($akhir), null, pengguna_sesi());
            $disimpan++;
        }

        $kabar = $disimpan . ' jam shift disimpan';
        $kabar .= $dihapus > 0 ? ', ' . $dihapus . ' jendela kosong dihapus' : '';
        $kabar .= $galat === '' ? '.' : ' — yang gagal: ' . implode('; ', $galat) . '.';

        return redirect()->to(site_url('admin/tugas/shift?tanggal=' . $tanggal))
            ->with($galat === '' ? 'sukses' : 'galat', $kabar);
    }

    /** Jalankan operan otomatis sekarang juga, tanpa menunggu jeda. */
    public function sapu()
    {
        helper('fungsi');

        $jumlah = (new TugasModel())->operanShift();

        return redirect()->to(site_url('admin/tugas/shift'))
            ->with('sukses', $jumlah . ' tugas dikembalikan ke antrean karena shift-nya sudah berakhir.');
    }

    /**
     * Aturan reminder transaksi (Admin & Superadmin).
     *
     * Angka di layar ini yang menentukan kapan sistem dianggap "boleh" membuat
     * tugas dari nota; notanya sendiri tidak pernah diubah di sini.
     */
    public function pengaturan()
    {
        helper('fungsi');

        $model = new TugasModel();

        return view('admin/tugas/pengaturan', [
            'title'   => 'Aturan Reminder Transaksi',
            'aturan'  => $model->aturanReminder(),
            'meta'    => TugasModel::ATURAN_REMINDER,
            'hitung'  => $model->generateHitung(),
            'kabar'   => (string) session()->getFlashdata('sukses'),
            'galat'   => (string) session()->getFlashdata('galat'),
        ]);
    }

    public function simpanPengaturan()
    {
        $model = new TugasModel();
        $kirim = [];

        foreach (array_keys(TugasModel::ATURAN_REMINDER) as $kunci) {
            $nilai = $this->request->getPost($kunci);

            if ($nilai !== null) {
                $kirim[$kunci] = $nilai;
            }
        }

        $model->simpanAturanReminder($kirim);

        return redirect()->to(site_url('admin/tugas/pengaturan'))->with('sukses', 'Aturan reminder disimpan.');
    }

    /** Daftar templat + satu editor. Butir yang judulnya kosong diabaikan model. */
    public function template()
    {
        helper('fungsi');

        $model = new TugasTemplateModel();
        $id    = (int) $this->request->getGet('templat');
        $edit  = $id > 0 ? $model->satu($id) : null;

        if ($id > 0 && $edit === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $item = $edit !== null ? $model->item($id) : [];

        while (count($item) < self::SLOT_ITEM) {
            $item[] = ['judul' => '', 'deskripsi' => '', 'jenis' => 'lain', 'prioritas' => 'normal', 'tenggat_hari' => 1];
        }

        return view('admin/tugas/template', [
            'title'   => 'Templat Tugas',
            'daftar'  => $model->daftar(),
            'edit'    => $edit,
            'item'    => array_slice($item, 0, self::SLOT_ITEM),
            'pilihan' => ['jenis' => TugasModel::jenisLain(), 'prioritas' => TugasModel::PRIORITAS],
            'toko'    => $this->tokoId(),
            'kabar'   => (string) session()->getFlashdata('sukses'),
            'galat'   => (string) session()->getFlashdata('galat'),
        ]);
    }

    public function simpanTemplate()
    {
        helper('fungsi');

        $model = new TugasTemplateModel();
        $nama  = trim((string) $this->request->getPost('nama'));
        $id    = (int) $this->request->getPost('id_template');

        if ($nama === '') {
            return redirect()->back()->with('galat', 'Namai dulu templatnya.')->withInput();
        }

        if ($id > 0 && $model->satu($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $items = [];

        foreach ((array) $this->request->getPost('item') as $i) {
            $items[] = is_array($i) ? $i : [];
        }

        $tersimpan = $model->simpan([
            'nama'       => $nama,
            'keterangan' => (string) $this->request->getPost('keterangan'),
            'aktif'      => (int) $this->request->getPost('aktif'),
        ], $items, (int) session()->get('id'), $id);

        return redirect()->to(site_url('admin/tugas/template?templat=' . $tersimpan))->with('sukses', 'Templat disimpan.');
    }

    /** Tuangkan templat ke antrean untuk satu toko pada satu tanggal dasar. */
    public function pakaiTemplate()
    {
        helper('fungsi');

        $model   = new TugasTemplateModel();
        $id      = (int) $this->request->getPost('id_template');
        $toko    = (int) $this->request->getPost('toko_id');
        $tanggal = $this->tanggal((string) $this->request->getPost('tanggal')) ?: date('Y-m-d');

        if ($model->satu($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($toko > 0 && ! $this->bolehToko($toko)) {
            return redirect()->back()->with('galat', 'Toko pilihan bukan milikmu.');
        }

        $hasil = $model->pakai($id, $toko > 0 ? $toko : null, $tanggal, (int) session()->get('id'));

        if ($hasil['dibuat'] === 0 && $hasil['sudah'] === 0) {
            return redirect()->back()->with('galat', 'Templat itu belum punya butir, atau sedang dimatikan.');
        }

        $pesan = $hasil['dibuat'] . ' tugas masuk antrean';

        if ($hasil['sudah'] > 0) {
            $pesan .= ' (' . $hasil['sudah'] . ' sudah ada untuk tanggal itu, tidak dibuat dua kali)';
        }

        return redirect()->to(site_url('admin/tugas'))->with('sukses', $pesan . '.');
    }

    public function matikanTemplate()
    {
        $model = new TugasTemplateModel();
        $id    = (int) $this->request->getPost('id_template');

        if ($model->satu($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->setAktif($id, (string) $this->request->getPost('aktif') === '1');

        return redirect()->to(site_url('admin/tugas/template'))->with('sukses', 'Status templat diperbarui.');
    }

    /**
     * Jalankan generator reminder sekarang (Admin & Superadmin), biasanya untuk
     * memastikan angka layar sama dengan hasil cron.
     */
    public function sinkron()
    {
        $hasil = (new TugasModel())->generateReminder($this->tokoBoleh());

        return redirect()->to(site_url('admin/tugas/pengaturan'))
            ->with('sukses', $hasil['nota'] . ' nota diperiksa, ' . $hasil['dibuat'] . ' reminder baru, '
                . $hasil['ditutup'] . ' reminder ditutup.');
    }


    /**
     * Layar Catatan Percakapan: CS menyimpan hasil obrolan WhatsApp yang tadi
     * terjadi, dan kalau ada pekerjaan lanjutan langsung lahir sebagai tugas.
     *
     * Dua tempat ditulis sekali jalan: timeline pelanggan (crm_aktivitas, supaya
     * Customer 360 tetap lengkap) dan antrean tugas (crm_tugas, supaya kerjaan
     * tidak hilang di kepala orang). Tidak ada tabel catatan baru di tengah-tengah.
     */
    public function catatan()
    {
        helper('fungsi');

        $model = new TugasModel();

        return view('admin/tugas/catatan', [
            'title'     => 'Catatan Percakapan',
            'toko'      => $this->tokoId(),
            'label'     => (new CrmModel())->getAllTags(),
            'pilihan'   => ['prioritas' => TugasModel::PRIORITAS],
            'belakangan' => $model->daftar([
                'jenis_in'     => ['catatan_wa', 'followup_pelanggan'],
                'dibuat_oleh'  => (int) pengguna_sesi(),
            ], 10, 0),
            'kabar'     => (string) session()->getFlashdata('sukses'),
            'galat'     => (string) session()->getFlashdata('galat'),
        ]);
    }

    /** Pencari pelanggan untuk layar catatan — hanya id, nama, dan nomornya. */
    public function cariPelanggan()
    {
        $toko = (int) $this->request->getGet('juragan_id');

        if ($toko < 1 || ! $this->bolehToko($toko)) {
            return $this->response->setJSON(['results' => []]);
        }

        $hasil = [];

        foreach ((new PelangganModel())->cari($toko, (string) $this->request->getGet('q'))->getResult() as $u) {
            $nomor = json_decode((string) ($u->hp ?? '[]'), true);

            $hasil[] = [
                'id'    => (int) $u->id_pelanggan,
                'nama'  => (string) $u->nama_pelanggan,
                'nomor' => is_array($nomor) ? (string) ($nomor[0] ?? '') : (string) $nomor,
            ];
        }

        return $this->response->setJSON(['results' => $hasil]);
    }

    /**
     * Simpan satu catatan percakapan (+ tugas lanjutannya kalau ada tindakan).
     *
     * Pelanggan harus benar-benar ada di toko yang dipilih: layar tidak boleh
     * mengarang id pelanggan dari form.
     */
    public function simpanCatatan()
    {
        helper('fungsi');

        $pelanggan = (int) $this->request->getPost('pelanggan_id');
        $toko      = (int) $this->request->getPost('juragan_id');
        $ringkasan = trim((string) $this->request->getPost('ringkasan'));
        $tindakan  = trim((string) $this->request->getPost('tindakan'));

        if ($ringkasan === '') {
            return redirect()->back()->with('galat', 'Ringkasan percakapan wajib diisi.')->withInput();
        }

        if ($pelanggan <= 0) {
            return redirect()->back()->with('galat', 'Pilih pelanggan dari daftar dulu, jangan diketik bebas.')->withInput();
        }

        if ($toko < 1 || ! $this->bolehToko($toko)) {
            return redirect()->back()->with('galat', 'Toko pilihan bukan milikmu.')->withInput();
        }

        if ((new PelangganModel())->satu($toko, $pelanggan) === null) {
            return redirect()->back()->with('galat', 'Pelanggan itu tidak ada di toko terpilih.')->withInput();
        }

        // catatan penagihan datang dari daftar nota, jadi ditautkan lewat nomor
        // seri; nota toko lain dijawab sama seperti nota tidak ada
        $nota = 0;
        $seri = trim((string) $this->request->getPost('seri'));

        if ($seri !== '') {
            $temu = (new InvoiceModel())->where('seri', $seri)->where('deleted_at', null)->first();

            if ($temu === null || ! $this->bolehToko((int) $temu->juragan_id)) {
                return redirect()->back()->with('galat', 'Nomor nota tidak ditemukan di tokomu.')->withInput();
            }

            $nota = (int) $temu->id_invoice;
        }

        $crm   = new CrmModel();
        $model = new TugasModel();
        $user  = (int) pengguna_sesi();
        $db    = \Config\Database::connect();

        $ringkasan = mb_substr($ringkasan, 0, 2000);
        $judul     = $tindakan !== '' ? mb_substr($tindakan, 0, 200) : 'Tindak lanjut: ' . mb_substr($ringkasan, 0, 60);

        $db->transBegin();

        $crm->tambahAktivitas([
            'pelanggan_id' => $pelanggan,
            'tipe'         => 'wa',
            'judul'        => 'Catatan percakapan: ' . mb_substr($ringkasan, 0, 80),
            'deskripsi'    => $ringkasan,
            'invoice_id'   => $nota > 0 ? $nota : null,
            'user_id'      => $user,
        ]);

        $tag = (int) $this->request->getPost('tag_id');
        $baru = trim((string) $this->request->getPost('nama_tag_baru'));

        if ($tag > 0) {
            $crm->tambahTagPelanggan($pelanggan, $tag);
        } elseif ($baru !== '') {
            $crm->tambahTagPelanggan($pelanggan, $crm->buatMasterTag(mb_substr($baru, 0, 60), 'primary'));
        }

        $ada_tugas = trim($tindakan) !== '' || trim((string) $this->request->getPost('tenggat')) !== '';
        $id_tugas  = 0;

        if ($ada_tugas) {
            // jenis followup_pelanggan, bukan catatan_wa: layar Reminder WhatsApp
            // hanya mendaftar tugas yang punya baris crm_tugas_whatsapp, sedangkan
            // tugas lanjutan dari catatan ini tidak punya — ia masuk layar Lain-lain.
            $id_tugas = $model->buat([
                'judul'        => $judul,
                'deskripsi'    => $ringkasan,
                'jenis'        => 'followup_pelanggan',
                'prioritas'    => (string) $this->request->getPost('prioritas'),
                'pelanggan_id' => $pelanggan,
                'juragan_id'   => $toko,
                'invoice_id'   => $nota,
                'tenggat'      => (string) $this->request->getPost('tenggat'),
            ], $user);
        }

        if ($db->transStatus() === false) {
            $db->transRollback();

            return redirect()->back()->with('galat', 'Catatan gagal disimpan.')->withInput();
        }

        $db->transCommit();

        if ($id_tugas === 0) {
            return redirect()->to(site_url('admin/tugas/catatan'))->with('sukses', 'Catatan masuk ke timeline pelanggan.');
        }

        return redirect()->to(site_url('admin/tugas/' . $id_tugas))->with('sukses', 'Catatan tersimpan dan tugas lanjutannya masuk antrean.');
    }

    /**
     * Dasbor kerja: angka hari ini untuk peran yang sedang membuka.
     *
     * CS melihat pegangan + antrean tokonya sendiri; Admin/Superadmin melihat
     * cakupan semua tokonya plus beban per orang. Satu layar, dua isi, karena
     * pertanyaannya sama ("apa yang belum beres") — yang berbeda cuma cakupannya.
     */
    public function dasbor()
    {
        helper('fungsi');

        $model = new TugasModel();
        $toko  = $this->tokoBoleh();
        $user  = (int) session()->get('id');
        $level = (string) session()->get('level');
        $hari  = date('Y-m-d');

        $model->operanShiftKalauWaktu();
        $model->reminderKalauWaktu($toko);

        $ckl = new ChecklistModel();

        return view('admin/tugas/dasbor', [
            'title'     => 'Dasbor Kerja',
            'hari'      => $hari,
            'level'     => $level,
            'ringkas'   => $model->ringkas($toko),
            'pegangan'  => $model->daftar(['terbuka' => 1, 'dikerjakan_oleh' => $user, 'juragan_ids' => $toko], 6, 0),
            'antrean'   => $model->daftar(['antrean' => 1, 'juragan_ids' => $toko], 6, 0),
            'terlambat' => $model->daftar(['terlambat' => 1, 'juragan_ids' => $toko], 6, 0),
            'riwayat'   => $model->aktivitas(['juragan_ids' => $toko], 8, 0),
            'beban'     => $level === 'cs' ? [] : $model->bebanPegawai($toko, strtotime($hari . ' -6 days')),
            'sesi'      => $ckl->hitungTerbuka($toko, $hari),
            'shift'     => (new ShiftModel())->satu($user, $hari),
            'regu'      => $level === 'cs' ? [] : (new ShiftModel())->padaTanggal($hari),
            'pengenalan' => (new TokoOnlineModel())->hitungBelumDikenal($user, $toko),
            'meta'      => ['status' => TugasModel::STATUS, 'jenis' => TugasModel::JENIS, 'prioritas' => TugasModel::PRIORITAS],
            'kabar'     => (string) session()->getFlashdata('sukses'),
            'galat'     => (string) session()->getFlashdata('galat'),
        ]);
    }

    /** Laporan aktivitas tugas (Admin & Superadmin): riwayat kejadian + beban per orang. */
    public function laporan()
    {
        return view('admin/tugas/laporan', $this->bahanLaporan());
    }

    /** Unduhan CSV dengan saringan yang sedang dibuka. */
    public function eksporLaporan()
    {
        $s  = $this->saringLaporan();
        $db = new TugasModel();

        $baris = $db->aktivitas($s, 5000, 0);
        $kelu = [];

        foreach ($baris as $e) {
            $kelu[] = [
                date('Y-m-d H:i', (int) $e['created_at']),
                (string) $e['nama_aktor'],
                (string) $e['jenis'],
                (int) $e['tugas_id'],
                (string) $e['judul'],
                (string) ($e['status_lama'] ?? ''),
                (string) ($e['status_baru'] ?? ''),
                (string) ($e['nama_juragan'] ?? ''),
                (string) ($e['alasan'] ?? ''),
                (string) ($e['catatan'] ?? ''),
            ];
        }

        $csv = $this->csv(['Waktu', 'Orang', 'Kejadian', 'ID tugas', 'Judul tugas', 'Dari', 'Ke', 'Toko', 'Alasan', 'Catatan'], $kelu);

        return $this->response
            ->setContentType('text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="aktivitas-tugas-' . $s['label'] . '.csv"')
            ->setBody($csv);
    }

    /** @return array<string, mixed> */
    private function bahanLaporan(): array
    {
        helper('fungsi');

        $model = new TugasModel();
        $s     = $this->saringLaporan();
        $batas = $this->batas();
        $total = $model->aktivitasJumlah($s);
        $page  = max(1, min($this->halaman(), max(1, (int) ceil($total / $batas))));

        return [
            'title'   => 'Laporan Aktivitas Tugas',
            's'       => $s,
            'baris'   => $model->aktivitas($s, $batas, ($page - 1) * $batas),
            'total'   => $total,
            'page'    => $page,
            'batas'   => $batas,
            'perjenis' => $model->aktivitasPerJenis($s),
            'beban'   => $model->bebanPegawai($s['juragan_ids'], $s['dari'], $s['sampai']),
            'pegawai' => $this->staf(),
            'meta'    => ['jenis_event' => $this->jenisEvent()],
            'kabar'   => (string) session()->getFlashdata('sukses'),
            'galat'   => (string) session()->getFlashdata('galat'),
        ];
    }

    /** @return array<string, mixed> */
    private function saringLaporan(): array
    {
        $dari   = $this->tanggal((string) $this->request->getGet('dari')) ?: date('Y-m-d', strtotime('-6 days'));
        $sampai = $this->tanggal((string) $this->request->getGet('sampai')) ?: date('Y-m-d');
        $jenis  = (string) $this->request->getGet('jenis');
        $orang  = (int) $this->request->getGet('orang');

        return [
            'dari'        => strtotime($dari . ' 00:00:00'),
            'sampai'      => strtotime($sampai . ' 23:59:59'),
            'hari_dari'   => $dari,
            'hari_sampai' => $sampai,
            'label'       => $dari . '_' . $sampai,
            'user_id'     => $orang > 0 ? $orang : 0,
            'jenis'       => array_key_exists($jenis, $this->jenisEvent()) ? $jenis : '',
            'juragan_ids' => $this->tokoBoleh(),
        ];
    }

    /** Jenis kejadian yang bisa disaring di laporan. */
    private function jenisEvent(): array
    {
        return [
            'dibuat'         => 'Tugas dibuat',
            'diambil'        => 'Diambil CS',
            'ditetapkan'     => 'Ditetapkan Admin',
            'dipindahkan'    => 'Penggarap diganti',
            'status'         => 'Berubah status',
            'selesai'        => 'Selesai',
            'dibatalkan'     => 'Dibatalkan',
            'operan_manual'  => 'Dioper oleh CS',
            'operan_otomatis' => 'Dioper otomatis (shift habis)',
        ];
    }

    /**
     * CSV dengan pemisah titik-koma (Excel Indonesia) dan perlindungan rumus:
     * nilai yang diawali =, +, -, @ atau tab diberi apostrof supaya tidak
     * dieksekusi spreadsheet saat berkasnya dibuka.
     *
     * @param list<string>                 $kepala
     * @param list<list<string|int|float>> $isi
     */
    private function csv(array $kepala, array $isi): string
    {
        $baris = static function (array $kolom): string {
            $bersih = array_map(
                static fn ($nilai): string => '"' . str_replace('"', '""', (string) $nilai) . '"',
                array_map(
                    static function ($nilai) {
                        $teks = (string) $nilai;

                        return $teks !== '' && str_contains("=+-@\t", $teks[0]) ? "'" . $teks : $teks;
                    },
                    $kolom
                )
            );

            return implode(';', $bersih);
        };

        return "\xEF\xBB\xBF" . $baris($kepala) . "\r\n"
            . implode("\r\n", array_map($baris, $isi)) . "\r\n";
    }

    // ------------------------------------------------------------------
    // dalam
    // ------------------------------------------------------------------

    /**
     * Satu tugas, hanya kalau boleh dibuka akun ini. Di luar cakupan dijawab 404,
     * bukan 403, supaya tidak jadi daftar "tugas apa yang ada di toko lain".
     *
     * @return array<string, mixed>
     */
    private function tugasMilikSaya(int $id): array
    {
        $tugas = (new TugasModel())->satu($id);

        if ($tugas === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($tugas['juragan_id'] !== null && ! $this->bolehToko((int) $tugas['juragan_id'])) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $tugas;
    }

    private function kembali(int $id, array $hasil)
    {
        $ke = $this->request->isAJAX()
            ? $this->response->setJSON(['status' => $hasil['ok'] ? 'success' : 'error', 'message' => $hasil['pesan']])
            : redirect()->to(site_url('admin/tugas/' . $id))
                ->with($hasil['ok'] ? 'sukses' : 'galat', $hasil['pesan']);

        return $ke;
    }

    /** Admin & Superadmin boleh membagikan tugas; CS tidak. */
    private function bolehKelola(): bool
    {
        return in_array((string) session()->get('level'), ['admin', 'superadmin'], true);
    }

    /**
     * id_toko => nama, sebatas toko yang boleh dibuka akun ini. Pemisah tugas
     * per toko memakai id (bukan slug) karena kolom juragan_id yang menyimpannya.
     *
     * @return array<int, string>
     */
    /**
     * Submenu tempat tugas ini dikerjakan. Detail tugas bisa dibuka dari mana
     * saja, tapi tombol kembali tidak boleh mengantar orang ke daftar yang salah.
     */
    private function layarAsal(string $jenis): string
    {
        $peta = [
            'catatan_wa'         => 'admin/tugas/whatsapp',
            'reminder_transaksi' => 'admin/tugas/bintang',
            'checklist_toko'     => 'admin/checklist',
            'pemeriksaan_email'  => 'admin/tugas/email',
        ];

        return site_url($peta[$jenis] ?? 'admin/tugas');
    }

    private function tokoId(): array
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

    /** Staf aktif yang bisa memegang pekerjaan (admin + CS). Sekali per request. */
    private ?array $staf_aktif = null;

    private function stafAktif(): array
    {
        if ($this->staf_aktif === null) {
            $this->staf_aktif = Juragan::get_users();
        }

        return $this->staf_aktif;
    }

    /** Staf aktif yang bisa ditunjuk jadi penggarap. */
    private function staf(): array
    {
        $staf = [];

        foreach ($this->stafAktif() as $u) {
            if ($u['level'] === 'cs' || $this->bolehKelola()) {
                $staf[(int) $u['id']] = $u['nama'] . ' (' . $u['level'] . ')';
            }
        }

        return $staf;
    }

    /** Saringan dari query string, sudah dibersihkan. */
    private function saring(): array
    {
        $tab     = (string) $this->request->getGet('tab');
        $jenis   = (string) $this->request->getGet('jenis');
        $status  = (string) $this->request->getGet('status');
        $prior   = (string) $this->request->getGet('prioritas');
        $cari    = trim((string) $this->request->getGet('cari'));
        $staf    = (int) $this->request->getGet('staf');
        $juragan = (int) $this->request->getGet('juragan');
        $batas   = $this->batas();

        $s = [
            'tab'          => array_key_exists($tab, self::TAB) ? $tab : 'antrean',
            'awal'         => $this->tanggal((string) $this->request->getGet('awal')),
            'akhir'        => $this->tanggal((string) $this->request->getGet('akhir')),
            'cari'         => mb_substr($cari, 0, 80),
            'nomor'        => mb_substr(trim((string) $this->request->getGet('nomor')), 0, 30),
            'juragan_ids'  => $this->tokoBoleh(),
            'jenis_not'    => TugasModel::PUNYA_SUBMENU,
        ];

        if (array_key_exists($jenis, TugasModel::JENIS)) {
            $s['jenis'] = $jenis;
        }

        if (array_key_exists($status, TugasModel::STATUS)) {
            $s['status'] = $status;
        }

        if (array_key_exists($prior, TugasModel::PRIORITAS)) {
            $s['prioritas'] = $prior;
        }

        if (array_key_exists($staf, $this->staf())) {
            $s['dikerjakan_oleh'] = $staf;
        }

        if ($juragan > 0 && $this->bolehToko($juragan)) {
            $s['juragan_id'] = $juragan;
        }

        // tab adalah pintasan filter; filter eksplisit di URL tetap menang
        if ($s['tab'] === 'antrean' && ! isset($s['status'])) {
            $s['antrean'] = true;
        }

        if ($s['tab'] === 'saya') {
            $s['dikerjakan_oleh'] = (int) pengguna_sesi();
            unset($s['antrean']);
        }

        if ($s['tab'] === 'terlambat') {
            $s['terlambat'] = true;
            unset($s['antrean'], $s['status']);
        }

        if ($s['tab'] === 'selesai') {
            $s['status'] = 'completed';
        }

        if ($s['tab'] === 'semua') {
            unset($s['antrean'], $s['status']);
        }

        return $s;
    }

    private function batas(): int
    {
        $n = (int) $this->request->getGet('batas');

        return in_array($n, self::BATAS, true) ? $n : 25;
    }

    /** Nomor halaman berbasis satu. */
    private function halaman(): int
    {
        return max(1, (int) $this->request->getGet('halaman'));
    }

    private function tanggal(string $nilai): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $nilai, $m)
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $nilai;
        }

        return '';
    }
}
