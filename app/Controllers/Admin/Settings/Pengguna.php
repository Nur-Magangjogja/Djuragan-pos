<?php

namespace App\Controllers\Admin\Settings;

use App\Controllers\BaseController;
use App\Models\RelasiModel;
use App\Models\UserModel;

class Pengguna extends BaseController
{
    public function pengguna()
    {
        $data = [
            'title'  => 'Pengaturan Pengguna',
            'sukses' => session('sukses'),
            'gagal'  => session('gagal'),
            'errors' => session('errors') ?: [],
            'isian'  => session('isian') ?: [],
        ];

        return view('admin/pengaturan/pengguna', $data);
    }

    /**
     * Isian yang boleh dikembalikan ke form saat validasi gagal.
     * Kata sandi sengaja tidak ikut: flashdata tersimpan di berkas sesi.
     *
     * @return array<string, mixed>
     */
    private function isianAman(): array
    {
        $isian = [];

        foreach (['id', 'username', 'nama', 'email', 'level', 'status', 'lihat_semua_produk', 'sunting_transaksi'] as $kolom) {
            $isian[$kolom] = (string) $this->request->getPost($kolom);
        }

        // popup dibuka lagi lewat JavaScript, jadi pilihan juragannya ikut disimpan
        $isian['juragan'] = array_values(array_map(
            'intval',
            array_filter((array) $this->request->getPost('juragan'), 'is_numeric')
        ));

        return $isian;
    }

    /**
     * Akun CS tanpa satu pun relasi juragan langsung melempar 500 di dashboardnya
     * (BaseController::juraganBy() menuntut baris order_relasi), jadi daftar
     * juragan wajib diisi untuk level itu.
     *
     * @param list<int|string> $juragans
     */
    private function juraganGalat(array $juragans): ?string
    {
        if (strtolower((string) $this->request->getPost('level')) === 'cs' && $juragans === []) {
            return 'Pilih minimal satu juragan untuk level CS.';
        }

        return null;
    }

    public function save_pengguna() // new insert
    {
        $validation = \Config\Services::validation();
        $userModel  = new UserModel();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('addPengguna');
        }

        if (! $validation->withRequest($this->request)->run()) {
            // var_dump($errors) membuat galat validasi hilang tanpa jejak:
            // pengguna diarahkan ulang tanpa pesan sehingga tidak tahu kolom mana yang salah
            return redirect()->to('/admin/settings/pengguna')
                ->with('errors', $validation->getErrors())
                ->with('isian', $this->isianAman());
        }

        $db          = \Config\Database::connect();
        $relasiModel = new RelasiModel();
        // multiselect boleh kosong; null dulu membuat foreach melempar 500
        $juragans = (array) $this->request->getPost('juragan');

        if (($galat = $this->juraganGalat($juragans)) !== null) {
            return redirect()->to('/admin/settings/pengguna')
                ->with('errors', ['juragan' => $galat])
                ->with('isian', $this->isianAman());
        }

        // akun dan daftar juragannya harus masuk bersama
        $db->transBegin();

        $userModel->save([
            'username' => strtolower($this->request->getPost('username')),
            'password' => password_hash($this->request->getPost('password'), PASSWORD_BCRYPT),
            'name'     => $this->request->getPost('nama'),
            'email'    => strtolower($this->request->getPost('email')),
            'level'    => strtolower($this->request->getPost('level')),
            'status'   => strtolower($this->request->getPost('status')),
            // hak baca katalog semua juragan; hanya kotak centang CS yang mengirimnya
            'lihat_semua_produk' => $this->request->getPost('lihat_semua_produk') ? 1 : 0,
            // hak sunting transaksi (layar Admin); sama, hanya kotak centang CS
            'sunting_transaksi'  => $this->request->getPost('sunting_transaksi') ? 1 : 0,
        ]);
        $id = (int) $db->insertID();

        foreach ($juragans as $juragan) {
            $relasiModel->insert([
                'table'      => 1, // juragan-user
                'juragan_id' => $juragan,
                'val_id'     => $id,
            ]);
        }

        if ($db->transStatus() === false) {
            $db->transRollback();

            return redirect()->to('/admin/settings/pengguna')
                ->with('isian', $this->isianAman())
                ->with('gagal', 'Pengguna gagal dibuat, coba lagi.');
        }

        $db->transCommit();

        return redirect()->to('/admin/settings/pengguna')
            ->with('sukses', 'Pengguna ' . $this->request->getPost('username') . ' berhasil ditambahkan.');
    }

    public function update_pengguna() // update if exist
    {
        $validation = \Config\Services::validation();
        $userModel  = new UserModel();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('editPengguna');
        }

        if (! $validation->withRequest($this->request)->run()) {
            return redirect()->to('/admin/settings/pengguna')
                ->with('errors', $validation->getErrors())
                ->with('isian', $this->isianAman());
        }

        $db          = \Config\Database::connect();
        $relasiModel = new RelasiModel();
        $id          = (int) $this->request->getPost('id');
        // multiselect boleh kosong; null dulu membuat foreach melempar 500
        $juragans = (array) $this->request->getPost('juragan');

        if (($galat = $this->juraganGalat($juragans)) !== null) {
            return redirect()->to('/admin/settings/pengguna')
                ->with('errors', ['juragan' => $galat])
                ->with('isian', $this->isianAman());
        }

        $data     = [];
        $password = $this->request->getPost('password');
        if (! empty($password)) {
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        $data['id']     = $id;
        $data['name']   = $this->request->getPost('nama');
        $data['email']  = strtolower($this->request->getPost('email'));
        $data['level']  = strtolower($this->request->getPost('level'));
        $data['status'] = strtolower($this->request->getPost('status'));
        // non-CS tak pernah mengirim kotak ini, jadi nilainya ikut dinolkan
        $data['lihat_semua_produk'] = $this->request->getPost('lihat_semua_produk') ? 1 : 0;
        $data['sunting_transaksi']  = $this->request->getPost('sunting_transaksi') ? 1 : 0;

        // dulu penghapusan relasi dijaga `affectedRows() > -1` yang selalu benar,
        // jadi relasi bisa terhapus sementara sisanya gagal tersimpan
        $db->transBegin();

        $userModel->save($data);

        // simpan ulang semua data relasi
        $relasiModel->where(['table' => '1', 'val_id' => $id])->delete();

        foreach ($juragans as $juragan) {
            $relasiModel->insert([
                'table'      => 1, // juragan-user
                'juragan_id' => $juragan,
                'val_id'     => $id,
            ]);
        }

        if ($db->transStatus() === false) {
            $db->transRollback();

            return redirect()->to('/admin/settings/pengguna')
                ->with('isian', $this->isianAman())
                ->with('gagal', 'Perubahan pengguna gagal disimpan.');
        }

        $db->transCommit();

        // daftar juragan disimpan 30 menit di writable/cache (kunci juragan_by_user_<id>),
        // jadi relasi yang baru dihapus masih bisa dipakai membuka halaman akun itu sampai
        // kuncinya dibuang
        $cache = \Config\Services::cache();
        $cache->delete('juragan_by_user_' . $id);
        $cache->delete('juragan_by_user_' . $id . '_bank');

        return redirect()->to('/admin/settings/pengguna')
            ->with('sukses', 'Perubahan pengguna tersimpan.');
    }

    /**
     * Hapus lunak akun. Barisnya tetap ada di tabel user — nota, catatan, dan
     * jejak tugas yang ditulis akun itu terbaca terus; yang hilang hanya akses
     * loginnya (UserModel menyaring deleted_at, jadi verifikasi masuk tidak
     * akan pernah menemukan akun ini lagi).
     */
    public function hapus_pengguna()
    {
        $userModel = new UserModel();
        $id        = (int) $this->request->getPost('id');
        $sendiri   = (int) session()->get('id');

        $akun = $userModel->find($id);

        if ($akun === null) {
            return redirect()->to('/admin/settings/pengguna')
                ->with('gagal', 'Akun tidak ditemukan — mungkin sudah dihapus.');
        }

        // superadmin yang sedang login tidak boleh menghapus dirinya sendiri;
        // yang lain boleh, jejak datanya tetap aman karena hapusnya lunak
        if ($id === $sendiri) {
            return redirect()->to('/admin/settings/pengguna')
                ->with('gagal', 'Anda tidak bisa menghapus akun yang sedang dipakai login ini.');
        }

        // satu superadmin aktif harus selalu tersisa, kalau tidak halaman
        // settings (khusus superadmin) tidak akan pernah bisa dibuka lagi
        if ((string) $akun->level === 'superadmin' && (string) $akun->status === 'active') {
            $superAktif = $userModel
                ->where('level', 'superadmin')
                ->where('status', 'active')
                ->countAllResults();

            if ($superAktif <= 1) {
                return redirect()->to('/admin/settings/pengguna')
                    ->with('gagal', 'Superadmin aktif terakhir tidak bisa dihapus. Tunjuk dulu penggantinya, atau nonaktifkan saja akun ini.');
            }
        }

        $userModel->delete($id);

        // relasi juragan ikut dilepas: daftar toko dibangun query mentah yang
        // tidak menyaring deleted_at, jadi tanpa langkah ini tokonya tetap
        // terbawa ke perhitungan lama
        (new RelasiModel())->where(['table' => 1, 'val_id' => $id])->delete();

        // daftar juragan tersimpan 30 menit di writable/cache — kunci lama dibuang
        $cache = \Config\Services::cache();
        $cache->delete('juragan_by_user_' . $id);
        $cache->delete('juragan_by_user_' . $id . '_bank');

        return redirect()->to('/admin/settings/pengguna')
            ->with('sukses', 'Akun ' . $akun->username . ' berhasil dihapus.');
    }
}
