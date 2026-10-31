<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\JuraganModel;
use App\Models\TokoOnlineModel;
use App\Models\TugasModel;
use App\Models\UlasanModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Reminder Bintang 5: permintaan ulasan yang lahir dari nota selesai + lunas.
 *
 * Satu layar, tiga tab: dua tab untuk ulasan (belum/sudah dikerjakan) dan satu tab
 * penagihan (DP yang belum dikonfirmasi + pelunasan yang mendekati tenggat) supaya
 * pekerjaan uang tidak hilang saat layar antrean umum tidak lagi dipakai.
 *
 * Mesin antreannya tetap punyanya `TugasModel`: ambil siapa cepat dia dapat (guard
 * di database), lepas tangan kembali ke antrean, dan perpindahan tercatat di riwayat.
 */
class Bintang extends BaseController
{
    private const TAB = [
        'belum' => 'Belum Dikerjakan',
        'sudah' => 'Sudah Dikerjakan',
        'tagih' => 'Perlu Ditagih',
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
        $baris  = $model->daftar($this->korong($s), $batas, ($page - 1) * $batas);

        $angka = [];

        foreach (array_keys(self::TAB) as $tab) {
            $angka[$tab] = $model->jumlah($this->korong(array_merge($s, ['tab' => $tab])));
        }

        return view('admin/bintang/index', [
            'title'   => 'Reminder Bintang 5',
            's'       => $s,
            'baris'   => $baris,
            'ulasan'  => (new UlasanModel())->untukInvoiceList(array_filter(array_map(
                static fn (array $w): int => (int) $w['invoice_id'],
                $baris
            ))),
            'total'   => $total,
            'page'    => $page,
            'batas'   => $batas,
            'tab'     => self::TAB,
            'angka'   => $angka,
            'toko'    => $this->tokoPeta(),
            'platform' => $this->platformPeta(),
            'milik'   => (int) session()->get('id'),
            'kabar'   => (string) session()->getFlashdata('sukses'),
            'galat'   => (string) session()->getFlashdata('galat'),
        ]);
    }

    /** Ambil tugas ulasan/penagihan: siapa cepat dia dapat. */
    public function ambil(int $id)
    {
        $this->barisMilikSaya($id);

        $hasil = (new TugasModel())->ambil($id, (int) session()->get('id'), $this->tokoBoleh());

        return $this->kembali($hasil, 'Tugas diambil, sekarang di tanganmu.');
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

        return $this->kembali($hasil, 'Tugas dilepas, kembali ke antrean.');
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

        return $this->kembali($hasil, 'Reminder selesai dicatat.');
    }

    /** Angka badge menu: semua reminder layar ini yang masih terbuka. */
    public function jumlah()
    {
        $model = new TugasModel();
        $toko  = $this->tokoBoleh();

        return $this->response->setJSON([
            'status' => 'success',
            'count'  => $model->hitungPemicu('ulasan', $toko) + $model->hitungPemicu('tagih', $toko),
        ]);
    }

    // ------------------------------------------------------------------ dalam

    /**
     * Saringan + tab jadi satu klausul query. Tab yang tidak dikenal jatuh ke
     * 'belum' supaya tidak ada jalan memancing isi tabel lain.
     *
     * @param array<string, mixed> $s
     *
     * @return array<string, mixed>
     */
    private function korong(array $s): array
    {
        $dasar = match ($s['tab']) {
            'sudah' => ['pemicu' => 'ulasan', 'status_in' => ['completed', 'cancelled']],
            'tagih' => ['pemicu' => 'tagih', 'terbuka' => true],
            default => ['pemicu' => 'ulasan', 'terbuka' => true],
        };

        $q = array_merge($s, $dasar);

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
            'tab'          => array_key_exists($tab, self::TAB) ? $tab : 'belum',
            'juragan_id'   => (int) $this->request->getGet('toko'),
            'cari'         => trim((string) $this->request->getGet('cari')),
            'milik_saya'   => $this->request->getGet('saya') === '1' ? (int) session()->get('id') : 0,
            'juragan_ids'  => $this->tokoBoleh(),
        ];
    }

    /** Satu baris reminder, hanya kalau memang tugas pemicu layar ini dan tokonya boleh. */
    private function barisMilikSaya(int $id): array
    {
        $baris = (new TugasModel())->satu($id);
        $kunci = (string) ($baris['kunci_unik'] ?? '');
        $pemicu = str_contains($kunci, '-') ? strstr($kunci, '-', true) : '';

        if ($baris === null || ($baris['juragan_id'] !== null && ! $this->bolehToko((int) $baris['juragan_id']))) {
            throw PageNotFoundException::forPageNotFound();
        }

        foreach (TugasModel::PEMICU as $awalnya) {
            if (in_array($pemicu . '-', $awalnya, true)) {
                return $baris;
            }
        }

        // tugas lain tetap milik tokonya, tapi tidak diurus dari layar ini
        throw PageNotFoundException::forPageNotFound();
    }

    private function kembali(array $hasil, string $sukses): \CodeIgniter\HTTP\RedirectResponse
    {
        return redirect()->to(site_url('admin/tugas/bintang'))
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

    /**
     * Label platform belanja tiap toko — petunjuk CS mau minta ulasannya di mana.
     *
     * @return array<int, list<string>> juragan_id => daftar platform aktif
     */
    private function platformPeta(): array
    {
        $peta = [];

        foreach ((new TokoOnlineModel())->daftar(['juragan_ids' => $this->tokoBoleh(), 'aktif' => '1'], 200, 0) as $t) {
            $peta[(int) $t['juragan_id']][] = (string) $t['platform'];
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
}
