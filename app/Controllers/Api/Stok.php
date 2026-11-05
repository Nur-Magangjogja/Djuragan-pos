<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ProdukFotoModel;
use App\Models\StokModel;

/**
 * Lookup stok untuk form tulis orderan.
 *
 * Form orderan boleh mencatat barang dengan nama yang diketik bebas, tapi kalau
 * barangnya sebenarnya sudah dipantau stoknya, lebih akurat mengambil varian yang
 * ada: harganya, ukurannya, dan foto produknya ikut terbawa. Endpoint ini hanya
 * membaca, dan hanya untuk toko yang boleh dipakai akun ini menulis orderan.
 */
class Stok extends BaseController
{
    /**
     * GET api/stok/cari[?q=<kata>]&juragan_id=<id>&tipe=<pembuatan|sewa>
     *
     * Jawaban sengaja formasinya sama dengan pencarian pelanggan
     * (query + results) supaya JS di form tidak perlu dua pola baca.
     *
     * Tipe pesanan memilih kolam stok: hanya varian kolam itu yang ditawarkan,
     * lengkap dengan harganya dan unitnya yang masih bisa diambil. `q` boleh
     * dikosongkan untuk mengambil seluruh isi kolam -- itu yang dipakai
     * dropdown "Ambil dari stok produk" supaya orang bisa melihat barang apa
     * saja yang dipantau di toko ini tanpa harus mengetik lebih dulu.
     */
    public function cari()
    {
        $cari    = trim((string) $this->request->getGet('q'));
        $juragan = (int) $this->request->getGet('juragan_id');
        $kolam   = StokModel::kolamDariTipe($this->request->getGet('tipe'));
        $ids     = $this->juraganIds();

        // orderan ditulis untuk satu toko, jadi tokonya harus dipilih dulu dan
        // harus toko milik akun ini; kalau tidak, jawabannya kosong, bukan semua
        if ($juragan < 1 || ! in_array($juragan, $ids, true)) {
            return $this->response->setJSON(['query' => $cari, 'results' => [], 'terbatas' => false]);
        }

        // tanpa kata kunci daftar utuh dibatasi longgar, tapi tetap ada batasnya:
        // satu toko bisa punya ribuan varian, dan daftar sepanjang itu tidak bisa
        // digulir masuk akal. `terbatas` ikut dijawab supaya form bisa jujur
        // menyebut daftarnya dipotong, bukan tampak seperti daftar lengkap
        $batas = $cari === '' ? 500 : 12;
        $hasil = [];

        foreach ((new StokModel())->saran($ids, $juragan, $cari, $batas, $kolam) as $b) {
            $hasil[] = [
                'id_stok'   => (int) $b['id_stok'],
                'juragan_id' => (int) $b['juragan_id'],
                'kode'      => (string) $b['kode'],
                'ukuran'    => (string) ($b['ukuran'] ?? ''),
                'harga'     => (int) $b['harga'],
                // yang ditawarkan ke form orderan adalah unit kolam ini yang masih
                // bisa diambil, bukan angka gudang
                'stok'      => (int) $b['tersedia'],
                'kolam'     => $kolam,
                'keterangan' => (string) ($b['keterangan'] ?? ''),
            ];
        }

        return $this->response->setJSON([
            'query'    => $cari,
            'results'  => $hasil,
            'terbatas' => count($hasil) >= $batas,
        ]);
    }

    /**
     * GET api/stok/jumlah
     *
     * Jumlah produk tiap toko milik akun ini, untuk angka indikator di dropdown
     * juragan pada form tulis orderan. Bentuknya peta id => jumlah supaya JS cukup
     * sekali ambil untuk mengisi semua opsi sekaligus.
     */
    public function jumlah()
    {
        $data = (new StokModel())->jumlahPerJuragan($this->juraganIds());

        return $this->response->setJSON(['status' => 'success', 'data' => (object) $data]);
    }

    /**
     * GET api/stok/katalog?juragan_id=<id>
     *
     * Daftar ringkas produk satu toko (kode, stok tersedia, harga awal, satu foto,
     * dan jenis layanan) untuk strip katalog yang bisa digulir mendatar di bawah
     * pemilih juragan. Sengaja TIDAK disaring per tipe: semua kolam (jual & sewa)
     * ditampilkan sekaligus, tiap kartu diberi label jenisnya supaya pengguna tahu
     * produk itu tipe Pembuatan atau Sewa.
     */
    public function katalog()
    {
        $juragan = (int) $this->request->getGet('juragan_id');
        $ids     = $this->juraganIds();

        if ($juragan < 1 || ! in_array($juragan, $ids, true)) {
            return $this->response->setJSON(['status' => 'success', 'data' => []]);
        }

        $model = new StokModel();
        $fotoM = new ProdukFotoModel();
        $data  = [];

        // dua kolam dibaca terpisah: kode yang sama boleh ada di jual dan sewa,
        // masing-masing jadi satu kartu dengan jenisnya sendiri
        foreach (['jual', 'sewa'] as $kolam) {
            $kartu  = $model->katalog($ids, $juragan, '', '', 40, 0, $kolam);
            $varian = array_map(static fn (array $k): array => array_column($k['varian'], 'id_stok'), $kartu);
            $foto   = $fotoM->perKartu($varian);

            foreach ($kartu as $i => $k) {
                $pertama = $foto[$i][0] ?? null;
                $data[]  = [
                    'kode'  => (string) $k['kode'],
                    'stok'  => (int) $k['total_stok'],
                    'harga' => (int) $k['harga_mulai'],
                    'jenis' => $kolam === 'sewa' ? 'Sewa' : 'Pembuatan',
                    'foto'  => $pertama !== null ? ProdukFotoModel::alamat((int) $pertama['id_foto'], 240) : null,
                ];
            }
        }

        return $this->response->setJSON(['status' => 'success', 'data' => $data]);
    }
}
