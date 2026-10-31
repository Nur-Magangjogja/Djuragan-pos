<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ChecklistModel;
use App\Models\TokoOnlineModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Checklist toko harian + pemeriksaan email.
 *
 * CS membuka sesi, menjawab butir satu-satu, lalu menutup sesi. Sesinya selalu
 * punya tugas di antrean, jadi "sudah dicek hari ini atau belum" cukup dilihat
 * di satu tempat — tidak ada daftar pekerjaan kedua yang harus diingat orang.
 */
class Checklist extends BaseController
{
    private const BATAS = [10, 25, 50];

    public function index()
    {
        helper('fungsi');

        // layar tugas lain mengandalkan sapuan ini juga; di sini ia menjaga supaya
        // sesi yang seharusnya dioper/diingatkan tidak tertahan
        $this->sapuTugasKalauWaktu();

        $model = new ChecklistModel();
        $s     = $this->saring();
        $batas = $this->batas();
        $page  = max(1, (int) $this->request->getGet('halaman'));
        $total = $model->jumlah($s);
        $page  = max(1, min($page, max(1, (int) ceil($total / $batas))));

        return view('admin/checklist/index', [
            'title'   => 'Cek Toko',
            's'       => $s,
            'baris'   => $model->daftar($s, $batas, ($page - 1) * $batas),
            'total'   => $total,
            'page'    => $page,
            'batas'   => $batas,
            'jenis'   => ChecklistModel::JENIS,
            'toko'    => $this->tokoSiap(),
            'kelola'  => $this->bolehKelola(),
            'kabar'   => (string) session()->getFlashdata('sukses'),
            'galat'   => (string) session()->getFlashdata('galat'),
        ]);
    }

    public function run(int $id)
    {
        helper('fungsi');

        $model = new ChecklistModel();
        $run   = $this->sesi($id);

        return view('admin/checklist/run', [
            'title'   => 'Sesi Checklist',
            'run'     => $run,
            'hasil'   => $model->hasil($id),
            'status'  => ChecklistModel::STATUS,
            'kabar'   => (string) session()->getFlashdata('sukses'),
            'galat'   => (string) session()->getFlashdata('galat'),
        ]);
    }

    /** Buka (atau lanjutkan) lembar pemeriksaan satu toko pada satu tanggal. */
    public function mulai()
    {
        helper('fungsi');

        // lembar email tidak dibuka dari sini lagi: cek email sekarang murni catatan
        // lewat menu Cek Email. Sesi email lama tetap bisa dibuka dan dibaca.
        if ((string) $this->request->getPost('jenis') === 'email') {
            return redirect()->to(site_url('admin/tugas/email'))
                ->with('sukses', 'Pemeriksaan email dicatat sebagai catatan biasa, bukan lembar checklist lagi.');
        }

        $tanggal = $this->tanggal((string) $this->request->getPost('tanggal')) ?: date('Y-m-d');
        $model   = new ChecklistModel();

        $toko_id = (int) $this->request->getPost('toko_id');

        if ($toko_id < 1 || ! $this->bolehToko($toko_id)) {
            return redirect()->back()->with('galat', 'Pilih toko yang benar — toko itu bukan milikmu.')->withInput();
        }

        $sesi = $model->mulai('toko', $toko_id, null, $tanggal, (int) session()->get('id'));

        return redirect()->to(site_url('admin/checklist/' . (int) $sesi['run']['id_run']))
            ->with('sukses', $sesi['dibuat'] ? 'Sesi dibuka, tugasnya masuk antrean.' : 'Sesi hari itu sudah pernah dibuka — lanjutkan di sini.');
    }

    /** Jawab satu butir. */
    public function isi(int $id)
    {
        $model = new ChecklistModel();
        $this->sesi($id);

        $ok = $model->isi(
            $id,
            (int) $this->request->getPost('item_id'),
            (string) $this->request->getPost('status'),
            (string) $this->request->getPost('alasan'),
            (string) $this->request->getPost('catatan'),
            $this->request->getPost('perlu_tindakan') !== null,
            (int) session()->get('id')
        );

        return redirect()->to(site_url('admin/checklist/' . $id))
            ->with($ok ? 'sukses' : 'galat', $ok
                ? 'Jawaban tersimpan.'
                : 'Jawaban tidak diterima: status tidak dikenal, atau "tidak relevan" tanpa alasan.');
    }

    /** Tutup sesi; tugasnya ikut selesai. */
    public function selesai(int $id)
    {
        helper('fungsi');

        $this->sesi($id);
        $hasil = (new ChecklistModel())->selesai(
            $id,
            (int) session()->get('id'),
            mb_substr(trim((string) $this->request->getPost('catatan')), 0, 2000)
        );

        return redirect()->to(site_url('admin/checklist/' . $id))
            ->with($hasil['ok'] ? 'sukses' : 'galat', $hasil['pesan']);
    }

    /** Angka badge di menu: sesi hari ini yang masih berjalan. */
    public function jumlah()
    {
        return $this->response->setJSON([
            'status' => 'success',
            'count'  => (new ChecklistModel())->hitungTerbuka($this->tokoBoleh(), date('Y-m-d')),
        ]);
    }

    /** Kelola templat butir (Admin & Superadmin). */
    public function aturan()
    {
        helper('fungsi');

        $model  = new ChecklistModel();
        $jenis  = (string) $this->request->getGet('jenis') === 'email' ? 'email' : 'toko';

        return view('admin/checklist/aturan', [
            'title'  => 'Templat Checklist',
            'jenis'  => $jenis,
            'butir'  => $model->semuaButir($jenis),
            'toko'   => $this->tokoId(),
            'meta'   => ChecklistModel::JENIS,
            'kabar'  => (string) session()->getFlashdata('sukses'),
            'galat'  => (string) session()->getFlashdata('galat'),
        ]);
    }

    public function simpanButir()
    {
        helper('fungsi');

        $model = new ChecklistModel();
        $judul = trim((string) $this->request->getPost('judul'));
        $id    = (int) $this->request->getPost('id_item');
        $jenis = (string) $this->request->getPost('jenis') === 'email' ? 'email' : 'toko';
        $toko  = (int) $this->request->getPost('toko_id');

        if ($judul === '') {
            return redirect()->back()->with('galat', 'Tulisi dulu butirnya.')->withInput();
        }

        if ($toko > 0 && ! $this->bolehToko($toko)) {
            return redirect()->back()->with('galat', 'Toko tambahan bukan milikmu.')->withInput();
        }

        if ($id > 0) {
            $punya = $model->semuaButir($jenis);
            $kenal = false;

            foreach ($punya as $b) {
                $kenal = $kenal || (int) $b['id_item'] === $id;
            }

            if (! $kenal) {
                throw PageNotFoundException::forPageNotFound();
            }
        }

        $model->simpanButir([
            'jenis'    => $jenis,
            'toko_id'  => $toko > 0 ? $toko : null,
            'judul'    => $judul,
            'hint'     => (string) $this->request->getPost('hint'),
            'urut'     => (int) $this->request->getPost('urut'),
            'wajib'    => (int) $this->request->getPost('wajib'),
            'aktif'    => (int) $this->request->getPost('aktif'),
        ], (int) session()->get('id'), $id);

        return redirect()->to(site_url('admin/checklist/aturan?jenis=' . $jenis))->with('sukses', 'Butir disimpan.');
    }

    public function matikanButir()
    {
        $model = new ChecklistModel();
        $id    = (int) $this->request->getPost('id_item');
        $jenis = (string) $this->request->getPost('jenis') === 'email' ? 'email' : 'toko';
        $kenal = false;

        foreach ($model->semuaButir($jenis) as $b) {
            $kenal = $kenal || (int) $b['id_item'] === $id;
        }

        if (! $kenal) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->setButirAktif($id, (string) $this->request->getPost('aktif') === '1');

        return redirect()->to(site_url('admin/checklist/aturan?jenis=' . $jenis))->with('sukses', 'Status butir diperbarui.');
    }

    // ------------------------------------------------------------------ dalam

    /** @return array<string, mixed> */
    private function sesi(int $id): array
    {
        $run = (new ChecklistModel())->run($id);

        if ($run === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($run['toko_id'] !== null && ! $this->bolehToko((int) $run['toko_id'])) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $run;
    }

    private function bolehKelola(): bool
    {
        return in_array((string) session()->get('level'), ['admin', 'superadmin'], true);
    }

    /** @return array<int, string> */
    private function tokoId(): array
    {
        $ids = $this->tokoBoleh();

        if ($ids === []) {
            return [];
        }

        $peta = [];

        foreach ((new \App\Models\JuraganModel())->whereIn('id_juragan', $ids)->orderBy('nama_juragan', 'ASC')->findAll() as $t) {
            $peta[(int) $t->id_juragan] = $t->nama_juragan;
        }

        return $peta;
    }

    /** Toko yang sudah punya checklist online, atau semuanya kalau belum ada. */
    private function tokoSiap(): array
    {
        $punya = [];

        foreach ((new TokoOnlineModel())->daftar(['juragan_ids' => $this->bolehKelola() ? [] : $this->tokoBoleh()], 500, 0) as $t) {
            $punya[(int) $t['juragan_id']] = $t['nama_juragan'];
        }

        return $punya !== [] ? $punya : $this->tokoId();
    }

    /** @return array<string, mixed> */
    private function saring(): array
    {
        $jenis  = (string) $this->request->getGet('jenis');
        $status = (string) $this->request->getGet('status');
        $tanggal = $this->tanggal((string) $this->request->getGet('tanggal'));

        return [
            // layar ini adalah 'Cek Toko': lembar hari ini saja yang ditampilkan lebih
            // dulu; lembar email lama masih bisa dibuka lewat penyaring jenis
            'jenis'        => array_key_exists($jenis, ChecklistModel::JENIS) ? $jenis : 'toko',
            'status'       => in_array($status, ['berjalan', 'selesai'], true) ? $status : '',
            'tanggal'      => $tanggal,
            'toko_id'      => (int) $this->request->getGet('toko'),
            'juragan_ids'  => $this->bolehKelola() ? [] : $this->tokoBoleh(),
        ];
    }

    private function batas(): int
    {
        $n = (int) $this->request->getGet('batas');

        return in_array($n, self::BATAS, true) ? $n : 25;
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
