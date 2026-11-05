<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SopModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Pustaka SOP.
 *
 * CS membaca aturannya, Admin menulisnya. Dokumen yang sudah tidak dipakai bisa
 * sekadar dimatikan supaya tidak tampil ke CS, dan Admin punya hapus permanen
 * sebagai jalan keluar terakhir.
 */
class Sop extends BaseController
{
    public function index()
    {
        helper('fungsi');

        $model  = new SopModel();
        $kelola = $this->bolehKelola();
        $cari   = trim((string) $this->request->getGet('cari'));

        $baris = $model->daftar([
            'aktif'    => $kelola ? '' : 1,
            'untuk'    => $kelola ? '' : ((string) session()->get('level') === 'cs' ? 'cs' : 'admin'),
            'cari'     => $cari,
        ]);

        return view('admin/sop/index', [
            'title'    => 'SOP & Panduan',
            'sop_grup' => SopModel::grup($baris),
            'sop_kelola' => $kelola,
            // halaman ini menampilkan daftar lengkap, jadi panelnya tidak perlu lapisan
            'sop_modal' => false,
            'sop_tampil' => count($baris),
            // bahan form sunting popus: satu kali di halaman ini, diisi ulang per kartu
            'sop_bahan' => $kelola ? $this->bahanSunting($baris) : [],
            'cari'     => $cari,
            'kelola'   => $kelola,
            'kategori_sop' => SopModel::KATEGORI,
            'kabar'    => (string) session()->getFlashdata('sukses'),
            'galat'    => (string) session()->getFlashdata('galat'),
        ]);
    }

    /**
     * Nilai yang dibutuhkan popus sunting per dokumen. Isinya dikirim sebagai satu
     * blok JSON di halaman, jadi tidak ada atribut panjang di tiap kartu.
     *
     * @param list<array<string, mixed>> $baris
     * @return array<int, array<string, string>>
     */
    private function bahanSunting(array $baris): array
    {
        $bahan = [];

        foreach ($baris as $s) {
            $bahan[(int) $s['id_sop']] = [
                'judul'    => (string) $s['judul'],
                'kategori' => SopModel::kategori_satu($s['kategori'] ?? ''),
                'isi'      => SopModel::teks($s['isi'] ?? null),
                'aktif'    => (string) (int) $s['aktif'],
            ];
        }

        return $bahan;
    }

    public function lihat(int $id)
    {
        helper('fungsi');

        $model  = new SopModel();
        $sop    = $model->satu($id);
        $kelola = $this->bolehKelola();

        if ($sop === null || (! $kelola && ((int) $sop['aktif'] !== 1 || ! $this->untukPeran((string) $sop['untuk'])))) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('admin/sop/lihat', [
            'title'  => $sop['judul'],
            'sop'    => $sop,
            'untuk'  => SopModel::UNTUK,
            'kelola' => $kelola,
            'kategori_sop' => SopModel::KATEGORI,
            'sop_kategori' => SopModel::kategori_satu($sop['kategori'] ?? ''),
            'sop_teks'     => SopModel::teks($sop['isi'] ?? null),
            'sop_poin'     => SopModel::poin($sop['isi'] ?? null),
            'kabar'  => (string) session()->getFlashdata('sukses'),
            'galat'  => (string) session()->getFlashdata('galat'),
        ]);
    }

    /** Tambah atau sunting dokumen (Admin & Superadmin). */
    public function simpan()
    {
        helper('fungsi');

        $model = new SopModel();
        $judul = trim((string) $this->request->getPost('judul'));
        $id    = (int) $this->request->getPost('id_sop');

        if ($judul === '') {
            return redirect()->back()->with('galat', 'Tulisi dulu judul SOP-nya.')->withInput();
        }

        if ($id > 0 && $model->satu($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        // satu dokumen = satu judul + isi (satu baris = satu poin). 'untuk' dan
        // 'urut' tidak dikirim form, jadi nilai lamanya dibiarkan apa adanya
        $tersimpan = $model->simpan([
            'judul'    => $judul,
            'isi'      => (string) $this->request->getPost('isi'),
            'kategori' => (string) $this->request->getPost('kategori'),
            'aktif'    => (int) $this->request->getPost('aktif'),
        ], (int) session()->get('id'), $id);

        return redirect()->to(site_url('admin/sop/' . $tersimpan))->with('sukses', 'SOP disimpan.');
    }

    public function matikan()
    {
        $model = new SopModel();
        $id    = (int) $this->request->getPost('id_sop');

        if ($model->satu($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->setAktif($id, (string) $this->request->getPost('aktif') === '1');

        return redirect()->to(site_url('admin/sop'))->with('sukses', 'Status SOP diperbarui.');
    }

    /** Hapus permanen satu dokumen (Admin & Superadmin). */
    public function hapus()
    {
        $model = new SopModel();
        $id    = (int) $this->request->getPost('id_sop');
        $sop   = $model->satu($id);

        if ($sop === null) {
            return redirect()->to(site_url('admin/sop'))->with('galat', 'Dokumennya sudah tidak ada.');
        }

        $model->hapus($id);

        return redirect()->to(site_url('admin/sop'))->with('sukses', 'SOP "' . $sop['judul'] . '" dihapus.');
    }

    /**
     * Simpan urutan kartu hasil seret-lepas. Jawabannya JSON karena dipanggil
     * dari skrip panel tanpa memuat ulang halaman; angkanya sendiri sudah benar
     * begitu DOM-nya ditata ulang, jadi tidak perlu HTML yang dikirim balik.
     */
    public function urut()
    {
        $model = new SopModel();
        $nomor = $model->simpanUrut((array) $this->request->getPost('id'));

        return $this->response->setJSON(['tersimpan' => $nomor]);
    }

    // ------------------------------------------------------------------ dalam

    private function bolehKelola(): bool
    {
        return in_array((string) session()->get('level'), ['admin', 'superadmin'], true);
    }

    /** CS hanya boleh membaca dokumen yang ditujukan untuk semua peran atau untuk CS. */
    private function untukPeran(string $untuk): bool
    {
        $level = (string) session()->get('level');

        return $untuk === 'semua' || $untuk === $level;
    }
}
