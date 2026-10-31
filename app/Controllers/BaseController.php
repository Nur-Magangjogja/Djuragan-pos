<?php

namespace App\Controllers;

use App\Controllers\Juragan as Jrgn;
use App\Models\InvoiceModel;
use App\Models\JuraganModel;
use App\Models\UserModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var array
     */
    protected $helpers = ['date', 'form', 'fungsi', 'number', 'text', 'url'];

    /**
     * Constructor.
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.

        // migration here
        // $migrate = \Config\Services::migrations();
        // $migrate->latest();
    }

    public function juraganBy($user_id)
    {
        $juragan      = Jrgn::by_user($user_id);
        $juraganModel = new JuraganModel();

        // akun lama ada yang belum punya baris order_relasi, jadi peta juragannya bisa
        // tidak ada sama sekali; tanpa pagar ini PHP 8 melempar "foreach on null"
        $punya = $juragan[$user_id]['juragan'] ?? [];

        $ids = [];

        foreach ($punya as $r) {
            $ids = array_merge($ids, [$r['id']]);
        }

        $return = $juraganModel->terakhir_update($ids);

        if ($return === null) {
            //  $juragan[$user_id]['juragan'];
            $arr = [];

            foreach ($punya as $r => $v) {
                $arr['id_juragan']   = $v['id'];
                $arr['juragan']      = $v['slug'];
                $arr['nama_juragan'] = $v['nama'];
                break;
            }

            $return = (object) $arr;
        }

        return $return;
    }

    /**
     * ID juragan milik pengguna yang sedang login. Dasar pembatas semua halaman
     * baru (Produk, Dasbor) supaya data juragan lain tidak ikut terbaca.
     *
     * @return list<int>
     */
    public function juraganIds(): array
    {
        $user_id  = (int) session()->get('id');
        $juragans = Jrgn::by_user($user_id)[$user_id]['juragan'] ?? [];

        return array_map('intval', array_column($juragans, 'id'));
    }

    /**
     * Pilihan juragan untuk dropdown filter, sudah terbatas pada milik pengguna.
     *
     * @return array<int, string> id_juragan => nama
     */
    public function juraganPilihan(): array
    {
        $user_id  = (int) session()->get('id');
        $pilihan  = [];

        foreach (Jrgn::by_user($user_id)[$user_id]['juragan'] ?? [] as $j) {
            $pilihan[(int) $j['id']] = $j['nama'];
        }

        asort($pilihan);

        return $pilihan;
    }

    /**
     * Apakah akun ini boleh melihat katalog produk dari SEMUA juragan, bukan hanya
     * toko yang disematkan padanya. Superadmin selalu boleh; CS boleh hanya kalau
     * Superadmin menyalakan penanda `lihat_semua_produk` di akunnya. Dibaca dari
     * basis data (bukan sesi) supaya perubahan Superadmin langsung berlaku tanpa
     * perlu login ulang.
     */
    public function bolehLihatSemuaProduk(): bool
    {
        if ((string) session()->get('level') === 'superadmin') {
            return true;
        }

        $user_id = (int) session()->get('id');

        if ($user_id <= 0) {
            return false;
        }

        // find() diberi id skalar: kembali satu baris (object) atau null
        $akun = (new UserModel())->find($user_id);

        return $akun !== null && (int) $akun->lihat_semua_produk === 1;
    }

    /**
     * ID juragan yang boleh dibaca di katalog produk. Sama seperti juraganIds()
     * (hanya toko yang disematkan), kecuali akun ini boleh melihat semuanya: maka
     * seluruh juragan aktif ikut terbaca. Hanya dipakai halaman Produk, jadi tidak
     * menyentuh pembatasan transaksi/nota.
     *
     * @return list<int>
     */
    public function produkJuraganIds(): array
    {
        if (! $this->bolehLihatSemuaProduk()) {
            return $this->juraganIds();
        }

        $semua = (new JuraganModel())->select('id_juragan')->findAll();

        return array_map('intval', array_column($semua, 'id_juragan'));
    }

    /**
     * Pilihan toko untuk filter katalog produk, mengikuti produkJuraganIds():
     * semua juragan bila akun ini boleh, kalau tidak hanya yang disematkan.
     *
     * @return array<int, string> id_juragan => nama
     */
    public function produkJuraganPilihan(): array
    {
        if (! $this->bolehLihatSemuaProduk()) {
            return $this->juraganPilihan();
        }

        $pilihan = [];

        foreach ((new JuraganModel())->orderBy('nama_juragan', 'ASC')->findAll() as $t) {
            $pilihan[(int) $t->id_juragan] = $t->nama_juragan;
        }

        return $pilihan;
    }

    /**
     * Pilihan toko untuk pemilih djuragan di halaman Transaksi. Kuncinya slug
     * karena tautan daftar transaksi memakai slug, dan superadmin ikut melihat
     * semua toko (sama seperti $hanya yang tidak dibatasi untuk akun itu).
     *
     * @return array<string, string> slug => nama
     */
    public function tokoPilihan(): array
    {
        $pilihan = [];

        if (session()->get('level') === 'superadmin') {
            foreach ((new JuraganModel())->orderBy('nama_juragan', 'ASC')->findAll() as $t) {
                $pilihan[$t->juragan] = $t->nama_juragan;
            }

            return $pilihan;
        }

        $user_id = (int) session()->get('id');

        foreach (Jrgn::by_user($user_id)[$user_id]['juragan'] ?? [] as $j) {
            $pilihan[$j['slug']] = $j['nama'];
        }

        asort($pilihan);

        return $pilihan;
    }

    /**
     * Daftar toko yang boleh dibuka akun ini. superadmin memegang semuanya, jadi
     * daftar lengkap dikembalikan apa adanya; admin/CS/viewer hanya toko yang
     * tertaut di order_relasi (table=1).
     *
     * @return list<int>
     */
    public function tokoBoleh(): array
    {
        if (session()->get('level') === 'superadmin') {
            $semua = (new JuraganModel())->select('id_juragan')->findAll();

            return array_map('intval', array_column($semua, 'id_juragan'));
        }

        return $this->juraganIds();
    }

    public function bolehToko($juragan_id): bool
    {
        return in_array((int) $juragan_id, $this->tokoBoleh(), true);
    }

    /**
     * Sapuan berjeda yang menjaga angka antrean tugas: shift yang lewat dioper
     * otomatis dan reminder yang jatuh tempo dilahirkan. Dipanggil tiap layar
     * tugas supaya tidak ada submenu yang menunjukkan angka lama sekarang layar
     * antrean umum tidak lagi jadi tujuan utama.
     */
    protected function sapuTugasKalauWaktu(): void
    {
        $model = new \App\Models\TugasModel();

        $model->operanShiftKalauWaktu();
        $model->reminderKalauWaktu($this->tokoBoleh());
    }

    /**
     * Toko satu-satunya milik akun ini, atau null kalau pegang nol / lebih dari satu.
     * Dipakai untuk membuang pilihan "Semua Juragan": kalau yang dipegang cuma satu
     * toko, orang tidak perlu memilih dan tidak bisa nyasar ke toko lain.
     *
     * @return array{id:int,slug:string,nama:string}|null
     */
    public function tokoTunggal(): ?array
    {
        $ids = $this->juraganIds();

        if (count($ids) !== 1 || session()->get('level') === 'superadmin') {
            return null;
        }

        $toko = (new JuraganModel())->where('id_juragan', $ids[0])->first();

        if ($toko === null) {
            return null;
        }

        return ['id' => (int) $toko->id_juragan, 'slug' => $toko->juragan, 'nama' => $toko->nama_juragan];
    }

    /**
     * Satu nota, diambil hanya kalau tokonya memang dipegang akun ini.
     * null menjawab dua hal sekaligus: notanya tidak ada, atau itu nota toko lain.
     * Halaman admin dan halaman CS memakai pagar yang sama.
     */
    public function notaMilikToko(int $invoice_id): ?object
    {
        $nota = (new InvoiceModel())->find($invoice_id);

        if ($nota === null || ! $this->bolehToko((int) $nota->juragan_id)) {
            return null;
        }

        return $nota;
    }

    /**
     * Jawaban baku untuk permintaan ke nota toko lain: tidak ada yang diubah.
     */
    public function tolakToko()
    {
        $this->response->setStatusCode(403);

        return $this->response->setJSON(['status' => 'Orderan ini bukan milik tokomu.']);
    }

    public function isJuragan($juragan)
    {
        $juraganModel = new JuraganModel();
        $juraganCount = $juraganModel->where('juragan', $juragan)->countAllResults();

        $return = false;
        if ($juraganCount > 0) {
            $return = true;
        }

        return $return;
    }

    public function allowedJuragan($user_id, $juragan)
    {
        $juraganModel = new JuraganModel();
        $juragans     = Jrgn::by_user($user_id);
        $return       = false;

        if ($this->isJuragan($juragan)) {
            $get = $juraganModel->where('juragan', $juragan)->first();

            // slug boleh berbentuk benar tapi juragannya sudah tidak ada, dan akun lama
            // bisa tidak punya peta juragan sama sekali; keduanya harus cukup dijawab
            // "tidak diijinkan" supaya halaman memberi 404, bukan galat PHP
            if ($get !== null) {
                $punya = $juragans[$user_id]['juragan'] ?? [];

                $result = array_search($get->id_juragan, array_column($punya, 'id'), false);  // false if not found, key off array if exist

                $return = false;
                if ($result !== false) {
                    $return = true;
                }
            }
        }

        return $return;
    }
}
