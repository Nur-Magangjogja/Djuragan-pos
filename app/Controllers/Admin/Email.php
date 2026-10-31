<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\JuraganModel;
use App\Models\TugasModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Cek Email: catatan singkat hasil melihat kotak masuk.
 *
 * Sengaja cuma catatan — tidak terikat akun email apa pun, tidak menyimpan
 * kredensial, dan tidak menghubungi servis mana pun. Tiap catatan masuk ke mesin
 * tugas yang sama (jenis `pemeriksaan_email`) supaya yang sudah dibaca dan yang
 * masih menunggu terlihat dari satu tempat, dan siapa yang mengerjakannya tercatat.
 */
class Email extends BaseController
{
    private const TAB = [
        'belum' => 'Belum Dikerjakan',
        'sudah' => 'Sudah Dikerjakan',
    ];

    private const BATAS = [10, 25, 50];

    public function index()
    {
        helper('fungsi');

        $this->sapuTugasKalauWaktu();

        $model  = new TugasModel();
        $s      = $this->saring();
        $batas  = $this->batas();
        $total  = $model->jumlah($this->korong($s));
        $page   = max(1, min($this->halaman(), max(1, (int) ceil($total / $batas))));

        $angka = [];

        foreach (array_keys(self::TAB) as $tab) {
            $angka[$tab] = $model->jumlah($this->korong(array_merge($s, ['tab' => $tab])));
        }

        return view('admin/email/index', [
            'title'  => 'Cek Email',
            's'      => $s,
            'baris'  => $model->daftar($this->korong($s), $batas, ($page - 1) * $batas),
            'total'  => $total,
            'page'   => $page,
            'batas'  => $batas,
            'tab'    => self::TAB,
            'angka'  => $angka,
            'toko'   => $this->tokoPeta(),
            'milik'  => (int) session()->get('id'),
            'kabar'  => (string) session()->getFlashdata('sukses'),
            'galat'  => (string) session()->getFlashdata('galat'),
        ]);
    }

    public function simpan()
    {
        helper('fungsi');

        $toko    = (int) $this->request->getPost('juragan_id');
        $catatan = trim((string) $this->request->getPost('catatan'));

        if ($toko < 1 || ! $this->bolehToko($toko)) {
            return redirect()->back()->with('galat', 'Pilih toko yang benar — toko itu bukan delegasimu.')->withInput();
        }

        if ($catatan === '') {
            return redirect()->back()->with('galat', 'Tulis dulu apa yang dilihat di kotak masuk.')->withInput();
        }

        $model = new TugasModel();
        $id    = $model->buat([
            'judul'       => 'Cek email: ' . mb_substr($catatan, 0, 60),
            'deskripsi'   => mb_substr($catatan, 0, 2000),
            'jenis'       => 'pemeriksaan_email',
            'prioritas'   => 'normal',
            'juragan_id'  => $toko,
            'tenggat'     => $this->tanggal((string) $this->request->getPost('tenggat')),
        ], (int) session()->get('id'));

        return $id > 0
            ? redirect()->to(site_url('admin/tugas/email'))->with('sukses', 'Catatan tersimpan.')
            : redirect()->back()->with('galat', 'Catatan gagal disimpan. Coba lagi.')->withInput();
    }

    public function ambil(int $id)
    {
        $this->catatanMilikSaya($id);

        $hasil = (new TugasModel())->ambil($id, (int) session()->get('id'), $this->tokoBoleh());

        return $this->kembali($hasil, 'Catatan diambil, sekarang di tanganmu.');
    }

    public function lepas(int $id)
    {
        $this->catatanMilikSaya($id);

        $hasil = (new TugasModel())->kembalikan(
            $id,
            (int) session()->get('id'),
            (string) $this->request->getPost('alasan'),
            ''
        );

        return $this->kembali($hasil, 'Catatan dilepas, kembali ke daftar.');
    }

    public function selesai(int $id)
    {
        $this->catatanMilikSaya($id);

        $hasil = (new TugasModel())->ubahStatus(
            $id,
            'completed',
            (int) session()->get('id'),
            (string) $this->request->getPost('catatan')
        );

        return $this->kembali($hasil, 'Cek email ditandai selesai.');
    }

    /** Angka badge menu. */
    public function jumlah()
    {
        return $this->response->setJSON([
            'status' => 'success',
            'count'  => (new TugasModel())->jumlah([
                'jenis'        => 'pemeriksaan_email',
                'terbuka'      => true,
                'juragan_ids'  => $this->tokoBoleh(),
            ]),
        ]);
    }

    // ------------------------------------------------------------------ dalam

    /** @param array<string, mixed> $s @return array<string, mixed> */
    private function korong(array $s): array
    {
        $q = array_merge($s, $s['tab'] === 'sudah'
            ? ['jenis' => 'pemeriksaan_email', 'status_in' => ['completed', 'cancelled']]
            : ['jenis' => 'pemeriksaan_email', 'terbuka' => true]);

        if ((int) $s['milik_saya'] > 0) {
            $q['dikerjakan_oleh'] = (int) $s['milik_saya'];
        }

        return $q;
    }

    /** @return array<string, mixed> */
    private function saring(): array
    {
        $tab = (string) $this->request->getGet('tab');

        return [
            'tab'         => array_key_exists($tab, self::TAB) ? $tab : 'belum',
            'juragan_id'  => (int) $this->request->getGet('toko'),
            'cari'        => trim((string) $this->request->getGet('cari')),
            'milik_saya'  => $this->request->getGet('saya') === '1' ? (int) session()->get('id') : 0,
            'juragan_ids' => $this->tokoBoleh(),
        ];
    }

    /** Catatan ini hanya boleh disentuh akun yang memegang tokonya, dan memang jenisnya. */
    private function catatanMilikSaya(int $id): array
    {
        $baris = (new TugasModel())->satu($id);

        if ($baris === null || (string) $baris['jenis'] !== 'pemeriksaan_email'
            || ($baris['juragan_id'] !== null && ! $this->bolehToko((int) $baris['juragan_id']))) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $baris;
    }

    private function kembali(array $hasil, string $sukses): \CodeIgniter\HTTP\RedirectResponse
    {
        return redirect()->to(site_url('admin/tugas/email'))
            ->with($hasil['ok'] ? 'sukses' : 'galat', $hasil['ok'] ? $sukses : $hasil['pesan']);
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
