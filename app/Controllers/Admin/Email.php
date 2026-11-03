<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Controllers\Juragan;
use App\Models\EmailModel;
use App\Models\JuraganModel;
use App\Models\TugasModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Cek Email: catatan singkat hasil melihat kotak masuk.
 *
 * Catatan boleh menunjuk satu alamat dari daftar email (crm_email). Alamat itu
 * didelegasikan Admin/Superadmin lewat layar Daftar Email; CS hanya bisa memakai
 * alamat yang dipegangnya, sedangkan Admin/Superadmin memakai semuanya.
 *
 * Sengaja cuma catatan — tidak ada sandi, tidak ada kredensial, dan tidak ada
 * koneksi ke servis email mana pun. Tiap catatan masuk ke mesin tugas yang sama
 * (jenis `pemeriksaan_email`) supaya yang sudah dibaca dan yang masih menunggu
 * terlihat dari satu tempat, dan siapa yang mengerjakannya tercatat.
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
        $email  = new EmailModel();
        $s      = $this->saring();
        $batas  = $this->batas();
        $total  = $model->jumlah($this->korong($s));

        // baris tabel pertama = alamat email, lengkap dengan pemegang dan jumlah
        // catatannya; Admin/Superadmin melihat semua alamat, CS hanya miliknya
        $baris_email = $this->bolehKelola()
            ? $email->daftar(['cari' => $s['cari']], 100, 0)
            : $email->daftar(['milik_user' => (int) session()->get('id')], 100, 0);
        $pemegang    = [];

        foreach ($baris_email as $e) {
            $pemegang[(int) $e['id_email']] = $email->pegang((int) $e['id_email']);
        }
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
            'email'  => $this->emailPilihan(),
            'daftar' => $baris_email,
            'pegang' => $pemegang,
            'staf'   => $this->stafCs(),
            'hitung' => $model->hitungPerEmail($this->tokoBoleh()),
            'kelola' => $this->bolehKelola(),
            'milik'  => (int) session()->get('id'),
            'kabar'  => (string) session()->getFlashdata('sukses'),
            'galat'  => (string) session()->getFlashdata('galat'),
        ]);
    }

    public function simpan()
    {
        helper('fungsi');

        $toko     = (int) $this->request->getPost('juragan_id');
        $catatan  = trim((string) $this->request->getPost('catatan'));
        $email_id = (int) $this->request->getPost('email_id');

        if ($toko < 1 || ! $this->bolehToko($toko)) {
            return redirect()->back()->with('galat', 'Pilih toko yang benar — toko itu bukan delegasimu.')->withInput();
        }

        // sebuah catatan selalu ditugaskan ke satu alamat email: yang memegang
        // alamat itulah yang nanti melihat tugasnya
        $email_model = new EmailModel();
        $akun_email  = $email_id > 0 ? $email_model->satu($email_id) : null;

        if ($akun_email === null || (int) $akun_email['aktif'] !== 1) {
            return redirect()->back()->with('galat', 'Pilih dulu email yang mau dicek — hanya alamat dari daftar yang bisa ditugaskan.')->withInput();
        }

        if (! $this->bolehKelola() && ! $email_model->pegangPunya($email_id, (int) session()->get('id'))) {
            return redirect()->back()->with('galat', 'Email itu belum didelegasikan kepadamu.')->withInput();
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
            'email_id'    => $email_id,
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

    /** Tambah atau ubah satu alamat. Email dinormalkan dulu, lalu dicek dua kali: sah dan belum ada. */
    public function tambahEmail()
    {
        helper('fungsi');

        $model = new EmailModel();
        $email = EmailModel::rapikan((string) $this->request->getPost('email'));
        $id    = (int) $this->request->getPost('id_email');

        if (! EmailModel::sah($email)) {
            return redirect()->back()->with('galat', 'Itu bukan alamat email yang sah.')->withInput();
        }

        if ($model->kembar($email, $id) !== null) {
            return redirect()->back()->with('galat', 'Alamat itu sudah ada di daftar.')->withInput();
        }

        if ($id > 0 && $model->satu($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $tersimpan = $model->simpan([
            'email'   => $email,
            'label'   => (string) $this->request->getPost('label'),
            'catatan' => (string) $this->request->getPost('catatan'),
            'aktif'   => (int) ($this->request->getPost('aktif') ?? 1),
        ], (int) session()->get('id'), $id);

        if ($tersimpan <= 0) {
            return redirect()->back()->with('galat', 'Email gagal disimpan.')->withInput();
        }

        return redirect()->to(site_url('admin/tugas/email'))
            ->with('sukses', $id > 0 ? 'Email diperbarui.' : 'Email ditambahkan.');
    }

    /** Alamat dimatikan, tidak dihapus: catatan cek email lama tetap menunjuk ke sini. */
    public function setEmailAktif()
    {
        helper('fungsi');

        $model = new EmailModel();
        $id    = (int) $this->request->getPost('id_email');

        if ($model->satu($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->setAktif($id, (string) $this->request->getPost('aktif') === '1');

        return redirect()->to(site_url('admin/tugas/email'))->with('sukses', 'Status email diperbarui.');
    }

    public function beriPegang()
    {
        helper('fungsi');

        $model = new EmailModel();
        $id    = (int) $this->request->getPost('id_email');
        $user  = (int) $this->request->getPost('user_id');

        if ($model->satu($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! array_key_exists($user, $this->stafCs())) {
            return redirect()->back()->with('galat', 'Akun itu tidak dikenal atau bukan CS aktif.');
        }

        $model->beri($id, $user, (int) session()->get('id'));

        return redirect()->to(site_url('admin/tugas/email'))->with('sukses', 'Email didelegasikan ke CS itu.');
    }

    public function copotPegang()
    {
        helper('fungsi');

        $model = new EmailModel();
        $id    = (int) $this->request->getPost('id_email');

        if ($model->satu($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->copot($id, (int) $this->request->getPost('user_id'));

        return redirect()->to(site_url('admin/tugas/email'))->with('sukses', 'Delegasi dilepas; catatan lama tidak berubah.');
    }

    /** @return array<int, string> id => nama, khusus CS aktif */
    private function stafCs(): array
    {
        $staf = [];

        foreach (Juragan::get_users() as $u) {
            if ($u['level'] === 'cs') {
                $staf[(int) $u['id']] = $u['nama'];
            }
        }

        return $staf;
    }

    /**
     * Alamat yang boleh dipilih di layar Cek Email: Admin/Superadmin melihat
     * semuanya, CS hanya alamat yang didelegasikan kepadanya.
     *
     * @return array<int, string>
     */
    private function emailPilihan(): array
    {
        $model  = new EmailModel();
        $peta   = [];
        $sumber = $this->bolehKelola()
            ? $model->daftar(['aktif' => '1'], 200, 0)
            : $model->milikUser((int) session()->get('id'));

        foreach ($sumber as $e) {
            $peta[(int) $e['id_email']] = $this->labelEmail($e);
        }

        return $peta;
    }

    private function labelEmail(array $e): string
    {
        $label = trim((string) ($e['label'] ?? ''));

        return $label === '' ? (string) $e['email'] : $label . ' — ' . $e['email'];
    }

    private function bolehKelola(): bool
    {
        return in_array((string) session()->get('level'), ['admin', 'superadmin'], true);
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

        if ((int) $s['email_id'] > 0) {
            $q['email_id'] = (int) $s['email_id'];
        }

        // baris "catatan tanpa alamat" di tabel atas bisa dibuka sendiri
        if ($s['email_get'] === '0') {
            $q['email_ids']           = [];
            $q['email_ids_atau_null'] = true;

            return $q;
        }

        if (! $this->bolehKelola()) {
            $q['email_ids']           = array_map(
                static fn (array $e): int => (int) $e['id_email'],
                (new EmailModel())->milikUser((int) session()->get('id'))
            );
            $q['email_ids_atau_null'] = true;
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
            'email_id'    => (int) $this->request->getGet('email'),
            'email_get'   => trim((string) $this->request->getGet('email')),
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
