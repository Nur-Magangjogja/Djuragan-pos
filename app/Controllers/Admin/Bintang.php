<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\JuraganModel;
use App\Models\TokoOnlineModel;
use App\Models\TugasModel;
use App\Models\UlasanModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Reminder Bintang 5: pengingat untuk meminta ulasan bintang lima di Google Maps
 * pada nota yang dananya sudah masuk — DP, sedang dicicil, atau lunas
 * (TugasModel::sinkronReminder). Isinya murni permintaan ulasan; sisa bayar yang
 * belum masuk ditangani CRM Follow-Up, bukan layar ini.
 *
 * Layarnya disamakan dengan Reminder WhatsApp: empat tab status (belum diambil,
 * sedang diproses, selesai, dan tab jejak lepas tangan khusus Admin).
 *
 * Mesin antreannya tetap punyanya `TugasModel`: ambil siapa cepat dia dapat (guard
 * di database), lepas tangan kembali ke antrean, dan perpindahan tercatat di riwayat.
 */
class Bintang extends BaseController
{
    /** Tab layar; nilainya dipakai sebagai filter status, labelnya dipakai di layar. */
    private const TAB = [
        'belum'       => 'Belum Diambil',
        'proses'      => 'Sedang Diproses',
        'selesai'     => 'Sudah Diselesaikan',
        'tak_selesai' => 'Tidak Jadi Dikerjakan',
    ];

    private const BATAS = [10, 25, 50];

    public function index()
    {
        helper('fungsi');

        $this->sapuTugasKalauWaktu();

        $model  = new TugasModel();
        $s      = $this->saring();
        $batas  = $this->batas();
        // tab jejak dibaca dari riwayat, bukan dari daftar tugas: querynya sendiri,
        // tapi saringan, pagination, dan cakupan tokonya tetap sama
        $jejak  = $s['tab'] === 'tak_selesai';
        $total  = $jejak ? $model->jejakLepasJumlah($this->korong($s)) : $model->jumlah($this->korong($s));
        $page   = max(1, min($this->halaman(), max(1, (int) ceil($total / $batas))));
        $baris  = $jejak
            ? $model->jejakLepas($this->korong($s), $batas, ($page - 1) * $batas)
            : $model->daftar($this->korong($s), $batas, ($page - 1) * $batas);

        $angka = [];

        foreach (array_keys($this->daftarTab()) as $tab) {
            $satu           = array_merge($s, ['tab' => $tab]);
            $angka[$tab]    = $tab === 'tak_selesai'
                ? $model->jejakLepasJumlah($this->korong($satu))
                : $model->jumlah($this->korong($satu));
        }

        return view('admin/bintang/index', [
            'title'    => 'Reminder Bintang 5',
            's'        => $s,
            'baris'    => $baris,
            'ulasan'   => (new UlasanModel())->untukInvoiceList(array_filter(array_map(
                static fn (array $w): int => (int) ($w['invoice_id'] ?? 0),
                $baris
            ))),
            'total'    => $total,
            'page'     => $page,
            'batas'    => $batas,
            'tab'      => $this->daftarTab(),
            'angka'    => $angka,
            'rekap'    => $jejak ? $model->jejakLepasPerOrang($this->korong($s)) : [],
            'toko'     => $this->tokoPeta(),
            'platform' => $this->platformPeta(),
            'milik'    => (int) session()->get('id'),
            'kelola'   => $this->bolehKelola(),
            'kabar'    => (string) session()->getFlashdata('sukses'),
            'galat'    => (string) session()->getFlashdata('galat'),
        ]);
    }

    /** Ambil tugas ulasan: siapa cepat dia dapat. */
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
            'count'  => $model->hitungPemicu('ulasan', $toko),
        ]);
    }

    // ------------------------------------------------------------------ dalam

    /**
     * Saringan + tab jadi satu klausul query. Tab yang tidak dikenal
     * jatuh ke 'belum' supaya tidak ada jalan memancing isi tabel lain.
     *
     * @param array<string, mixed> $s
     *
     * @return array<string, mixed>
     */
    private function korong(array $s): array
    {
        $q = [
            'pemicu'      => ['ulasan'],
            'juragan_id'  => (int) $s['juragan_id'],
            'juragan_ids' => $s['juragan_ids'],
            'cari'        => $s['cari'],
            'milik_saya'  => (int) $s['milik_saya'],
            'dari'        => $s['dari'],
            'sampai'      => $s['sampai'],
        ];

        if ((int) $s['milik_saya'] > 0) {
            $q['dikerjakan_oleh'] = (int) $s['milik_saya'];
        }

        // tab status: sama persis dengan layar WhatsApp, hanya 'tak_selesai' yang
        // dibaca dari riwayat (bangunJejak) dan tidak butuh filter status
        match ($s['tab']) {
            'belum'   => $q['status'] = 'queued',
            'proses'  => $q['status_in'] = ['assigned', 'in_progress', 'waiting'],
            'selesai' => $q['status'] = 'completed',
            default   => null,
        };

        return $q;
    }

    /**
     * Tab yang boleh dibuka akun ini. CS tidak punya layar yang mengungkit
     * pelepasan orang lain, jadi tab alasannya dilepas dari daftar — dan `saring()`
     * menolak nilainya juga kalau ada yang mengetik manual di URL.
     *
     * @return array<string, string>
     */
    private function daftarTab(): array
    {
        $tab = self::TAB;

        if (! $this->bolehKelola()) {
            unset($tab['tak_selesai']);
        }

        return $tab;
    }

    /** @return array<string, mixed> */
    private function saring(): array
    {
        $tab = (string) $this->request->getGet('tab');

        return [
            'tab'         => in_array($tab, array_keys($this->daftarTab()), true) ? $tab : 'belum',
            'juragan_id'  => (int) $this->request->getGet('toko'),
            'cari'        => trim((string) $this->request->getGet('cari')),
            'dari'        => (string) $this->request->getGet('dari'),
            'sampai'      => (string) $this->request->getGet('sampai'),
            'milik_saya'  => $this->request->getGet('saya') === '1' ? (int) session()->get('id') : 0,
            'juragan_ids' => $this->tokoBoleh(),
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

    /** Admin/Superadmin boleh membuka tab jejak lepas tangan dan melihat aturannya. */
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
