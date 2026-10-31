<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\JuraganModel;
use App\Models\PelangganModel;
use App\Models\TugasModel;
use App\Models\TugasWhatsappModel;
use App\Models\UserModel;
use App\Models\WaNomorModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Reminder WhatsApp: percakapan pelanggan yang perlu ditindaklanjuti CS.
 *
 * Satu layar untuk satu kategori pekerjaan, tapi mesin antreannya tetap punyanya
 * `TugasModel`: ambil siapa cepat dia dapat (guard di database, bukan di PHP),
 * lepas tangan kembali ke antrean, dan setiap perpindahan tercatat di riwayat.
 */
class Whatsapp extends BaseController
{
    private const BATAS = [10, 25, 50];

    public function index()
    {
        helper('fungsi');

        $this->sapuTugasKalauWaktu();

        $model  = new TugasWhatsappModel();
        $s      = $this->saring();
        $batas  = $this->batas();
        $total  = $model->jumlah($s);
        $page   = max(1, min($this->halaman(), max(1, (int) ceil($total / $batas))));
        $tab    = array_merge([array_key_first(TugasWhatsappModel::TAB) => 0], $this->hitungTab());

        return view('admin/whatsapp/index', [
            'title'   => 'Reminder WhatsApp',
            's'       => $s,
            'baris'   => $model->daftar($s, $batas, ($page - 1) * $batas),
            'total'   => $total,
            'page'    => $page,
            'batas'   => $batas,
            'tab'     => TugasWhatsappModel::TAB,
            'angka'   => $tab,
            'tindak'  => TugasWhatsappModel::TINDAK,
            'prioritas' => TugasModel::PRIORITAS,
            'toko'    => $this->tokoPeta(),
            'nomor'   => (new WaNomorModel())->semua(['juragan_ids' => $this->tokoBoleh(), 'aktif' => '1']),
            'milik'   => (int) session()->get('id'),
            'kelola'  => $this->bolehKelola(),
            'kabar'   => (string) session()->getFlashdata('sukses'),
            'galat'   => (string) session()->getFlashdata('galat'),
        ]);
    }

    /** Buat tugas WA baru: masuk antrean, pembuatnya ditampilkan sebagai label. */
    public function simpan()
    {
        helper('fungsi');

        $toko     = (int) $this->request->getPost('juragan_id');
        $nomor_ke = trim((string) $this->request->getPost('nomor_ke'));
        $catatan  = trim((string) $this->request->getPost('catatan'));
        $pelanggan = (int) $this->request->getPost('pelanggan_id');
        $nomor_id = (int) $this->request->getPost('nomor_id');

        if ($toko < 1 || ! $this->bolehToko($toko)) {
            return redirect()->back()->with('galat', 'Pilih toko yang benar — toko itu bukan milikmu.')->withInput();
        }

        if (! WaNomorModel::nomorSah($nomor_ke)) {
            return redirect()->back()->with('galat', 'Nomor WhatsApp pelanggan belum benar (8–16 angka).')->withInput();
        }

        if ($catatan === '') {
            return redirect()->back()->with('galat', 'Tulis catatan: apa yang perlu dilakukan CS berikutnya?')->withInput();
        }

        if ($pelanggan > 0 && (new PelangganModel())->satu($toko, $pelanggan) === null) {
            return redirect()->back()->with('galat', 'Pelanggan itu tidak ada di toko terpilih.')->withInput();
        }

        if ($nomor_id > 0) {
            $boleh = array_column((new WaNomorModel())->siapDipakai($toko, (int) session()->get('id')), 'id_nomor');

            if (! in_array($nomor_id, array_map('intval', $boleh), true)) {
                return redirect()->back()->with('galat', 'Nomor pengirim itu bukan milik toko ini, atau sedang dipakai CS lain.')->withInput();
            }
        }

        try {
            $jadi = (new TugasWhatsappModel())->buat([
                'juragan_id'     => $toko,
                'pelanggan_id'   => $pelanggan,
                'nomor_id'       => $nomor_id,
                'nomor_ke'       => $nomor_ke,
                'tindak_lanjut'  => (string) $this->request->getPost('tindak_lanjut'),
                'catatan'        => $catatan,
                'prioritas'      => (string) $this->request->getPost('prioritas'),
                'tenggat'        => $this->tanggal((string) $this->request->getPost('tenggat')),
            ], (int) session()->get('id'));
        } catch (\Throwable $e) {
            log_message('error', 'simpan reminder whatsapp gagal: ' . $e->getMessage());

            return redirect()->back()->with('galat', 'Tugas gagal disimpan. Coba lagi.')->withInput();
        }

        return redirect()->to(site_url('admin/tugas/whatsapp?tab=belum'))
            ->with('sukses', 'Masuk antrean: ' . $jadi['judul']);
    }

    /** Ambil siapa cepat dia dapat; yang terlambat dikabari bahwa tugas sudah diambil. */
    public function ambil(int $id)
    {
        $this->barisMilikSaya($id);

        $hasil = (new TugasModel())->ambil($id, (int) session()->get('id'), $this->tokoBoleh());

        return $this->kembali($id, $hasil, 'Tugas diambil, sekarang di tanganmu.');
    }

    /** Lepas tangan: kembali ke antrean dengan alasan yang tercatat. */
    public function lepas(int $id)
    {
        $this->barisMilikSaya($id);

        $hasil = (new TugasModel())->kembalikan(
            $id,
            (int) session()->get('id'),
            (string) $this->request->getPost('alasan'),
            (string) $this->request->getPost('catatan')
        );

        return $this->kembali($id, $hasil, 'Tugas dilepas, kembali ke antrean.');
    }

    public function selesai(int $id)
    {
        $this->barisMilikSaya($id);

        $hasil = (new TugasModel())->ubahStatus(
            $id,
            'completed',
            (int) session()->get('id'),
            (string) $this->request->getPost('catatan')
        );

        return $this->kembali($id, $hasil, 'Reminder WhatsApp selesai.');
    }

    /** Angka badge menu. */
    public function jumlah()
    {
        return $this->response->setJSON([
            'status' => 'success',
            'count'  => (new TugasWhatsappModel())->hitungTerbuka($this->tokoBoleh()),
        ]);
    }

    // ------------------------------------------------------- delegasi nomor (Admin)

    public function nomor()
    {
        helper('fungsi');

        $model = new WaNomorModel();

        return view('admin/whatsapp/nomor', [
            'title'   => 'Nomor WhatsApp Penugasan',
            'daftar'  => $model->semua(['juragan_ids' => $this->tokoBoleh()]),
            'toko'    => $this->tokoPeta(),
            'pegawai' => $this->stafCs(),
            'kabar'   => (string) session()->getFlashdata('sukses'),
            'galat'   => (string) session()->getFlashdata('galat'),
        ]);
    }

    public function simpanNomor()
    {
        helper('fungsi');

        $model = new WaNomorModel();
        $toko  = (int) $this->request->getPost('juragan_id');
        $nomor = trim((string) $this->request->getPost('nomor'));
        $id    = (int) $this->request->getPost('id_nomor');

        if ($toko < 1 || ! $this->bolehToko($toko)) {
            return redirect()->back()->with('galat', 'Pilih toko yang benar.')->withInput();
        }

        if (! WaNomorModel::nomorSah($nomor)) {
            return redirect()->back()->with('galat', 'Nomor pengirim belum benar (8–16 angka).')->withInput();
        }

        if ($model->kembar($toko, $nomor, $id)) {
            return redirect()->back()->with('galat', 'Nomor itu sudah terdaftar di toko ini.')->withInput();
        }

        if ($id > 0 && $model->satu($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->simpan([
            'juragan_id' => $toko,
            'label'      => (string) $this->request->getPost('label'),
            'nomor'      => $nomor,
            'user_id'    => (int) $this->request->getPost('user_id'),
            'aktif'      => (int) $this->request->getPost('aktif'),
            'catatan'    => (string) $this->request->getPost('catatan'),
        ], (int) session()->get('id'), $id);

        return redirect()->to(site_url('admin/tugas/whatsapp/nomor'))->with('sukses', 'Nomor disimpan.');
    }

    public function aktifNomor()
    {
        $model = new WaNomorModel();
        $id    = (int) $this->request->getPost('id_nomor');

        if ($model->satu($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->setAktif($id, (string) $this->request->getPost('aktif') === '1');

        return redirect()->to(site_url('admin/tugas/whatsapp/nomor'))->with('sukses', 'Status nomor diperbarui.');
    }

    // ------------------------------------------------------------------ dalam

    /** Jumlah baris per tab, untuk angka di samping nama tab. */
    private function hitungTab(): array
    {
        $model = new TugasWhatsappModel();
        $s     = $this->saring();
        $angka = [];

        foreach (array_keys(TugasWhatsappModel::TAB) as $tab) {
            $angka[$tab] = $model->jumlah(array_merge($s, ['tab' => $tab]));
        }

        return $angka;
    }

    /** @return array<string, mixed> */
    private function saring(): array
    {
        $tab    = (string) $this->request->getGet('tab');
        $tindak = (string) $this->request->getGet('tindak');

        return [
            'tab'           => array_key_exists($tab, TugasWhatsappModel::TAB) ? $tab : 'belum',
            'tindak_lanjut' => array_key_exists($tindak, TugasWhatsappModel::TINDAK) ? $tindak : '',
            'juragan_id'    => (int) $this->request->getGet('toko'),
            'nomor'         => trim((string) $this->request->getGet('nomor')),
            'cari'          => trim((string) $this->request->getGet('cari')),
            'milik_saya'    => $this->request->getGet('saya') === '1' ? (int) session()->get('id') : 0,
            'juragan_ids'   => $this->tokoBoleh(),
        ];
    }

    /** Satu baris WA + tugasnya, hanya kalau memang boleh dibuka akun ini. */
    private function barisMilikSaya(int $id): array
    {
        $baris = (new TugasWhatsappModel())->satu($id);

        if ($baris === null || ($baris['juragan_id'] !== null && ! $this->bolehToko((int) $baris['juragan_id']))) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $baris;
    }

    private function kembali(int $id, array $hasil, string $sukses): \CodeIgniter\HTTP\RedirectResponse
    {
        return redirect()->to(site_url('admin/tugas/whatsapp'))
            ->with($hasil['ok'] ? 'sukses' : 'galat', $hasil['ok'] ? $sukses : $hasil['pesan']);
    }

    private function bolehKelola(): bool
    {
        return in_array((string) session()->get('level'), ['admin', 'superadmin'], true);
    }

    /** @return array<int, string> */
    private function tokoPeta(): array
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

    /** CS aktif yang bisa ditugasi memegang sebuah nomor. */
    private function stafCs(): array
    {
        $peta = [];

        foreach ((new UserModel())->where('level', 'cs')->where('status', 'active')->orderBy('name', 'asc')
            ->get()->getResultArray() as $u) {
            $peta[(int) $u['id']] = $u['name'];
        }

        return $peta;
    }

    private function batas(): int
    {
        $n = (int) $this->request->getGet('batas');

        return in_array($n, self::BATAS, true) ? $n : 25;
    }

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
