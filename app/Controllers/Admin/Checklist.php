<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Controllers\Juragan as Jrgn;
use App\Models\ChecklistModel;
use App\Models\JuraganModel;
use App\Models\LabelAsal;
use App\Models\TokoOnlineModel;
use App\Models\UserModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Checklist toko harian + pemeriksaan email.
 *
 * CS membuka sesi, menjawab butir satu-satu, lalu menutup sesi. Sesinya selalu
 * punya tugas di antrean, jadi "sudah dicek hari ini atau belum" cukup dilihat
 * di satu tempat — tidak ada daftar pekerjaan kedua yang harus diingat orang.
 *
 * Layar ini juga tempat Admin & Superadmin mengelola tokonya: daftar toko, siapa
 * saja yang boleh membukanya, dan tombol untuk menautkan akun atau menyunting
 * tokonya. Yang diubah hanyalah tautan toko-akun yang sudah dipakai seluruh
 * halaman, jadi tokonya langsung ikut muncul di daftar kerja akun itu di mana-mana
 * tanpa mengubah juragan yang sedang dipegangnya.
 */
class Checklist extends BaseController
{
    private const BATAS = [10, 25, 50];

    /** Pilihan terakhir di dropdown platform: bukan nama platform, tapi niat menambah. */
    private const PLATFORM_BARU = '__baru__';

    public function index()
    {
        helper('fungsi');

        // layar tugas lain mengandalkan sapuan ini juga; di sini ia menjaga supaya
        // sesi yang seharusnya dioper/diingatkan tidak tertahan
        $this->sapuTugasKalauWaktu();

        $model = new ChecklistModel();
        $toko  = $this->tokoCek();
        $s     = $this->saring($toko);
        $batas = $this->batas();

        // tab ketiga dipakai bersama Admin & Superadmin: isinya daftar toko,
        // siapa yang memegangnya, dan tombol untuk menyunting/menautkan akun.
        // Tidak digantungkan pada $toko !== [] supaya toko pertama masih bisa
        // dibuat saat daftarnya masih kosong. Akun yang tidak berhak dikembalikan
        // ke daftar catatan, bukan diberi pesan error
        $kelolaToko = $this->bolehKelola();
        $tabKelola  = (string) $this->request->getGet('tab') === 'kelola' && $kelolaToko;

        if ($tabKelola) {
            $baris = [];
            $total = 0;
            $page  = 1;
        } else {
            $page  = max(1, (int) $this->request->getGet('halaman'));
            $total = $model->jumlahCatatan($s);
            $page  = max(1, min($page, max(1, (int) ceil($total / $batas))));
            $baris = $model->catatan($s, $batas, ($page - 1) * $batas);
        }

        $kelola   = $this->bolehKelola();
        $pemegang = $kelola ? $this->pemegangCek($toko) : [];

        return view('admin/checklist/index', [
            'title'       => 'Cek Toko',
            's'           => $s,
            'baris'       => $baris,
            'total'       => $total,
            'page'        => $page,
            'batas'       => $batas,
            'toko'        => $toko,
            'kelola'      => $kelola,
            'kelolaToko'  => $kelolaToko,
            'tabKelola'   => $tabKelola,
            'pegawai'     => $tabKelola ? $this->calonDelegasi() : [],
            'pemegang'    => $pemegang,
            'namajuragan' => $tabKelola ? $this->petaJuragan() : [],
            'platform'    => $tabKelola ? $this->platform() : [],
            'platformInfo' => $tabKelola ? $this->platformTersimpan() : [],
            'lembarToko'  => $tabKelola ? (new TokoOnlineModel())->lembarPerToko() : [],
            'kabar'       => (string) session()->getFlashdata('sukses'),
            'galat'       => (string) session()->getFlashdata('galat'),
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
        $toko    = $this->tokoCek();

        if (! isset($toko[$toko_id])) {
            return redirect()->back()->with('galat', 'Pilih toko yang benar — toko itu bukan delegasimu.')->withInput();
        }

        $sesi = $model->mulai('toko', $toko_id, null, $tanggal, (int) session()->get('id'), $toko[$toko_id]['juragan_id']);

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
            (int) $this->request->getPost('id_hasil'),
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
            'count'  => (new ChecklistModel())->hitungTerbuka(array_keys($this->tokoCek()), date('Y-m-d')),
        ]);
    }

    /**
     * Lepas tautan langsung sebuah toko dari satu akun (Admin & Superadmin).
     *
     * Menambah pemegang tidak lagi punya jalur sendiri di sini: akun dicentang
     * dari popus "Sunting toko", jadi tautan dan juragan tokonya diurus di satu
     * tempat. Yang tersisa hanya jalan melepasnya, karena tanda silang di daftar
     * toko harus tetap bekerja.
     *
     * Tanda silang memanggil ini dari latar belakang (chipnya sudah hilang dari
     * layar lebih dulu, menunggu "Urungkan" habis waktunya), jadi jawabannya JSON
     * dan bukan halaman penuh.
     */
    public function tautanCopot()
    {
        $json  = $this->request->isAJAX();
        $bahan = $this->bahanTautan();

        if ($bahan === null) {
            return $this->balas($json, false, 'Tokonya atau akunnya tidak dikenali.');
        }

        [$id_toko, $user, $nama, $juragan_id] = $bahan;

        $model = new TokoOnlineModel();

        if (! $model->aksesPunyaUser($id_toko, $user)) {
            return $this->balas($json, false, 'Tautan itu sudah tidak ada.');
        }

        $model->copotAkses($id_toko, $user);
        $this->buangCacheToko([$user]);

        // mencabut tautan tidak selalu mencabut aksesnya: kalau juragan toko ini
        // masih dipegang akun itu, tokonya tetap ikut lewat jalur juragan
        $lewat = $this->pegangLewatJuragan($user, $juragan_id);

        return $this->balas($json, true, 'Tautan ' . $nama . ' dilepas dari toko ini.'
            . ($lewat ? ' Dia tetap bisa membukanya lewat juragan yang dipegangnya.' : ''),
            ['lewat_juragan' => $lewat]);
    }

    /**
     * Satu aksi, dua bentuk balasan: JSON untuk tombol yang berjalan di latar,
     * redirect + flash untuk form biasa (halaman tanpa JavaScript).
     *
     * @param array<string, mixed> $tambahan lapangan ekstra untuk balasan JSON
     *
     * @return \CodeIgniter\HTTP\RedirectResponse|\CodeIgniter\HTTP\ResponseInterface
     */
    private function balas(bool $json, bool $ok, string $pesan, array $tambahan = [])
    {
        if ($json) {
            return $this->response->setJSON(['ok' => $ok, 'pesan' => $pesan] + $tambahan);
        }

        return redirect()->back()->with($ok ? 'sukses' : 'galat', $pesan);
    }

    /**
     * Menghapus satu toko beserta seluruh riwayat checklistnya (Admin & Superadmin).
     *
     * Ini jalan satu-satunya yang benar-benar membuang baris toko, jadi semuanya
     * ikut dibersihkan dalam satu transaksi dan cache orang yang memegangnya
     * dibuang. Layar menawarkan enam detik untuk mengurungkan sebelum permintaan
     * ini dikirim — sesudah itu tidak ada yang bisa dikembalikan.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse|\CodeIgniter\HTTP\ResponseInterface
     */
    public function hapusToko()
    {
        if (! $this->bolehKelola()) {
            throw PageNotFoundException::forPageNotFound();
        }

        $json    = $this->request->isAJAX();
        $id_toko = (int) $this->request->getPost('id_toko');
        $model   = new TokoOnlineModel();

        // pemegang lewat juragan harus diketahui SEBELUM tokonya hilang: setelah
        // barisnya tidak ada, jalurnya tidak bisa ditelusuri lagi
        $lama    = $id_toko > 0 ? $model->satu($id_toko) : null;
        $warisan = [];

        if ($lama !== null && $lama['juragan_id'] !== null) {
            foreach ((new JuraganModel())->pengelola([(int) $lama['juragan_id']]) as $per_level) {
                foreach ($per_level as $daftar) {
                    foreach ($daftar as $o) {
                        $warisan[] = (int) $o['id'];
                    }
                }
            }
        }

        $hasil = $model->hapus($id_toko);

        if ($hasil === null) {
            return $this->balas($json, false, 'Tokonya sudah tidak ada di daftar.');
        }

        $terkena = array_values(array_unique(array_merge($hasil['user'], $warisan)));
        $this->buangCacheToko($terkena);

        return $this->balas($json, true, 'Toko ' . $hasil['nama'] . ' dihapus.'
            . ($hasil['lembar'] > 0 ? ' ' . $hasil['lembar'] . ' lembar checklist ikut terhapus.' : '')
            . ($hasil['tautan'] > 0 ? ' ' . $hasil['tautan'] . ' tautan CS dilepas.' : ''),
            ['id_toko' => $id_toko, 'lembar' => $hasil['lembar'], 'tautan' => $hasil['tautan']]);
    }

    /**
     * Simpan satu toko checklist (khusus Admin & Superadmin): nama bebas, juragan
     * boleh dikosongkan, lengkap dengan daftar akun yang boleh membukanya.
     */
    public function simpanToko()
    {
        if (! $this->bolehKelola()) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model    = new TokoOnlineModel();
        $id_toko  = (int) $this->request->getPost('id_toko');
        $nama     = trim((string) $this->request->getPost('nama'));

        if ($nama === '') {
            return redirect()->back()->with('galat', 'Nama tokonya diisi dulu.')->withInput();
        }

        // Platform datang dari dua tempat: pilihan di dropdown, atau isian
        // "Tambah platform" yang hanya dirender untuk Superadmin. Yang diketik
        // disimpan ke asal_order, jadi pilihannya ikut bertambah untuk toko lain
        $superadmin = (string) session()->get('level') === 'superadmin';
        $pilih      = trim((string) $this->request->getPost('platform'));
        $ketik      = $superadmin ? trim((string) $this->request->getPost('platform_baru')) : '';

        if ($pilih === self::PLATFORM_BARU) {
            if ($ketik === '') {
                return redirect()->back()
                    ->with('galat', 'Nama platform barunya diisi dulu, atau pilih "Tanpa platform".')
                    ->withInput();
            }

            $platform = $this->platformTetap($ketik, true);
        } else {
            $platform = $this->platformTetap($pilih, false);
        }

        if ($platform === null) {
            return redirect()->back()->with('galat',
                'Platform "' . ($pilih === self::PLATFORM_BARU ? $ketik : $pilih)
                . '" belum terdaftar. Hanya Superadmin yang bisa menambah platform baru.')->withInput();
        }

        $lama = $id_toko > 0 ? $model->satu($id_toko) : null;

        if ($id_toko > 0 && $lama === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        // juragan tidak lagi dipilih dari popus toko: toko baru berdiri sendiri,
        // sedangkan toko lama tetap memakai juragan yang sudah menempel supaya
        // akses warisan dan labelnya di daftar tidak hilang saat disunting
        $juragan = $lama !== null && $lama['juragan_id'] !== null ? (int) $lama['juragan_id'] : 0;

        // satu juragan hanya boleh punya satu toko per platform (kunci unik
        // juragan_id_platform). Ditolak di sini dengan nama tokonya, bukan
        // dibiarkan meledak sebagai galat basis data di tengah penyimpanan
        $kembar = $model->kembar($juragan, $platform, $id_toko);

        if ($kembar !== null) {
            return redirect()->back()->with('galat',
                'Toko ' . (string) $kembar['nama'] . ' sudah memakai '
                . ($platform === '' ? 'pilihan tanpa platform' : 'platform ' . $platform)
                . ' untuk juragan itu. Sunting toko itu, atau pilih platform lain.')->withInput();
        }

        try {
            $id_toko = $model->simpan([
                'juragan_id' => $juragan,
                'platform'   => $platform,
                'nama'       => $nama,
                'url'        => (string) $this->request->getPost('url'),
                'catatan'    => (string) $this->request->getPost('catatan'),
                'aktif'      => (string) $this->request->getPost('aktif') === '0' ? 0 : 1,
            ], (int) session()->get('id'), $id_toko);
        } catch (DatabaseException $e) {
            // dua penyunting bisa menyimpan di saat yang sama; pemeriksaan di atas
            // tidak melihat baris yang belum tersimpan, kunci uniknya yang melihat
            return redirect()->back()->with('galat',
                'Kombinasi juragan dan platform itu baru saja dipakai toko lain. Muat ulang halamannya, lalu simpan lagi.')->withInput();
        }

        $ubah = $this->tautkanCs($id_toko, (array) $this->request->getPost('cs'));

        return redirect()->to(site_url('admin/checklist?tab=kelola'))
            ->with('sukses', 'Toko disimpan.' . ($ubah > 0 ? ' ' . $ubah . ' tautan CS diperbarui.' : ''));
    }

    /** Tambah butir tulisan sendiri di satu lembar. */
    public function butirSendiri(int $id)
    {
        $this->sesi($id);

        $hasil = (new ChecklistModel())->tambahButir(
            $id,
            (string) $this->request->getPost('judul'),
            (int) session()->get('id')
        );

        return redirect()->to(site_url('admin/checklist/' . $id))
            ->with($hasil['ok'] ? 'sukses' : 'galat', $hasil['pesan']);
    }

    /** Hapus butir tulisan sendiri; butir templat ditolak oleh model. */
    public function hapusSendiri(int $id)
    {
        $this->sesi($id);

        $hasil = (new ChecklistModel())->hapusButir($id, (int) $this->request->getPost('id_hasil'));

        return redirect()->to(site_url('admin/checklist/' . $id))
            ->with($hasil['ok'] ? 'sukses' : 'galat', $hasil['pesan']);
    }

    /**
     * Tulis satu catatan untuk sebuah toko pada hari ini.
     *
     * Yang membedakan siapa yang menulis, bukan apa yang ditulis: catatan dari CS
     * adalah laporan kerja mandiri (langsung terhitung selesai), catatan dari
     * Admin/Superadmin adalah permintaan kerja yang menunggu dikerjakan.
     */
    public function catatan()
    {
        $toko_id = (int) $this->request->getPost('toko_id');
        $catatan = trim((string) $this->request->getPost('catatan'));
        $toko    = $this->tokoCek();

        if (! isset($toko[$toko_id])) {
            return redirect()->back()->with('galat', 'Pilih toko yang benar — toko itu bukan delegasimu.')->withInput();
        }

        if ($catatan === '') {
            return redirect()->back()->with('galat', 'Catatannya ditulis dulu.')->withInput();
        }

        $level  = (string) session()->get('level');
        $beres  = $level === 'cs';
        $kepada = null;

        // Admin/Superadmin menulis untuk orang lain, jadi orangnya wajib dipilih
        // dan harus memang pemegang toko itu — kalau tidak, catatan itu menggantung
        // tanpa ada yang merasa ditugasi
        if (! $beres) {
            $boleh = [];

            foreach ($this->pemegangCek([$toko_id => $toko[$toko_id]])[$toko_id] ?? [] as $o) {
                $boleh[(int) $o['id']] = (string) $o['nama'];
            }

            $kepada = (int) $this->request->getPost('user_id');

            if ($boleh !== [] && $kepada < 1) {
                return redirect()->back()->with('galat', 'Pilih dulu siapa yang harus mengerjakan catatan ini.')->withInput();
            }

            if ($boleh !== [] && ! isset($boleh[$kepada])) {
                return redirect()->back()->with('galat', 'Orang itu tidak memegang toko pilihanmu — pilih dari daftarnya.')->withInput();
            }

            if ($boleh === []) {
                $kepada = null;
            }
        }

        $hasil = (new ChecklistModel())->buatCatatan(
            ['id_toko' => $toko_id] + $toko[$toko_id],
            $catatan,
            (int) session()->get('id'),
            $beres,
            $kepada
        );

        return redirect()->to(site_url('admin/checklist'))
            ->with($hasil['ok'] ? 'sukses' : 'galat', $hasil['pesan']);
    }

    /** Selesaikan satu catatan: yang ditanya hanya apa yang sudah dikerjakan. */
    public function catatanSelesai(int $id)
    {
        $this->sesi($id);

        $hasil = (new ChecklistModel())->selesaikanCatatan(
            $id,
            (int) $this->request->getPost('id_hasil'),
            (string) $this->request->getPost('catatan'),
            (int) session()->get('id')
        );

        return redirect()->to(site_url('admin/checklist'))
            ->with($hasil['ok'] ? 'sukses' : 'galat', $hasil['pesan']);
    }

    // ------------------------------------------------------------------ dalam

    /** @return array<string, mixed> */
    private function sesi(int $id): array
    {
        $run = (new ChecklistModel())->run($id);

        if ($run === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($run['toko_id'] !== null && ! isset($this->tokoCek()[(int) $run['toko_id']])) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $run;
    }

    private function bolehKelola(): bool
    {
        return in_array((string) session()->get('level'), ['admin', 'superadmin'], true);
    }

    /**
     * Bahan satu tautan langsung toko-akun, sudah lolos pagar. null menjawab dua
     * hal sekaligus: tokonya tidak ada dan akunnya bukan Admin/CS.
     *
     * Akun nonaktif ikut lolos di sini karena mencabut tautannya justru masih
     * perlu; menambah pemegang hanya lewat popus sunting toko, yang daftarnya
     * memang cuma berisi akun aktif.
     *
     * @return array{0:int,1:int,2:string,3:?int}|null id_toko, id akun, nama, juragan_id
     */
    private function bahanTautan(): ?array
    {
        if (! $this->bolehKelola()) {
            throw PageNotFoundException::forPageNotFound();
        }

        $id_toko = (int) $this->request->getPost('id_toko');
        $user    = (int) $this->request->getPost('user_id');
        $toko    = $this->tokoCek()[$id_toko] ?? null;
        $akun    = $user > 0 ? (new UserModel())->select('id, name, level')->find($user) : null;

        if ($toko === null || $akun === null || ! in_array((string) $akun->level, ['admin', 'cs'], true)) {
            return null;
        }

        return [$id_toko, $user, $akun->name . ' (' . $akun->level . ')', $toko['juragan_id']];
    }

    /**
     * Apakah akun ini memegang juragan toko tersebut — jalur warisan, bukan
     * tautan langsung. Dipakai untuk menjelaskan ke orang kenapa sebuah akun
     * tetap bisa membuka toko walau tautannya dicabut.
     */
    private function pegangLewatJuragan(int $user, ?int $juragan_id): bool
    {
        if ($juragan_id === null) {
            return false;
        }

        foreach ((new JuraganModel())->pengelola([(int) $juragan_id])[(int) $juragan_id] ?? [] as $daftar) {
            foreach ($daftar as $a) {
                if ((int) $a['id'] === $user) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Semua juragan untuk label di daftar toko: label harus tetap menyebut nama
     * juragan toko milik orang lain yang ikut tampil di layar.
     *
     * @return array<int, string>
     */
    private function petaJuragan(): array
    {
        $peta = [];

        foreach ((new JuraganModel())->orderBy('nama_juragan', 'ASC')->findAll() as $j) {
            $peta[(int) $j->id_juragan] = (string) $j->nama_juragan;
        }

        return $peta;
    }

    /** @return array<int, string> id akun => "nama (level)", khusus Admin/CS yang aktif */
    private function calonDelegasi(): array
    {
        $calon = [];

        foreach (Jrgn::get_users() as $u) {
            $calon[(int) $u['id']] = $u['nama'] . ' (' . $u['level'] . ')';
        }

        return $calon;
    }

    /**
     * Daftar juragan tiap akun disimpan 30 menit di writable/cache, jadi setiap
     * tautan yang berubah harus membuang kunci akun yang tersentuh — kalau tidak,
     * toko barunya baru muncul setengah jam kemudian.
     *
     * @param array<int, int> $user_ids
     */
    private function buangCacheToko(array $user_ids): void
    {
        $cache = \Config\Services::cache();

        foreach ($user_ids as $id) {
            $cache->delete('juragan_by_user_' . (int) $id);
            $cache->delete('juragan_by_user_' . (int) $id . '_bank');
        }
    }

    /**
     * Sinkronkan daftar akun pemegang satu toko. Nilai lama dibandingkan dengan yang
     * dikirim form, bukan dibuang lalu ditulis ulang: tautan yang masih sama harus
     * tetap utuh supaya status pengenalannya tidak hilang begitu saja.
     *
     * @return int berapa tautan yang berubah
     */
    private function tautkanCs(int $id_toko, array $dikirim): int
    {
        $model = new TokoOnlineModel();
        $calon = $this->calonDelegasi();
        $mau   = [];

        foreach ($dikirim as $id) {
            $id = (int) $id;

            if ($id > 0 && isset($calon[$id])) {
                $mau[$id] = true;
            }
        }

        $ada = [];

        foreach ($model->akses($id_toko) as $a) {
            $ada[(int) $a['user_id']] = true;
        }

        $berubah = 0;

        foreach (array_diff_key($mau, $ada) as $user => $_) {
            $model->beriAkses($id_toko, (int) $user, (int) session()->get('id'));
            $berubah++;
        }

        foreach (array_diff_key($ada, $mau) as $user => $_) {
            $model->copotAkses($id_toko, (int) $user);
            $berubah++;
        }

        if ($berubah > 0) {
            $this->buangCacheToko(array_keys($mau + $ada));
        }

        return $berubah;
    }

    /** Daftar marketplace dari asal_order — platform toko tidak diketik bebas. */
    private function platform(): array
    {
        $platform = [];

        // status asal_order adalah ENUM('0','1'): angka 1 di sini dibandingkan
        // MySQL dengan indeks enum, bukan isinya, jadi harus benar-benar '1'
        // — kalau tidak, daftar platformnya kosong dan hanya "Tanpa platform"
        // yang tersisa di popus
        foreach ((new LabelAsal())->where('status', '1')->orderBy('label', 'ASC')->findAll() as $a) {
            $platform[(string) $a->label] = (string) $a->label;
        }

        return $platform;
    }

    /**
     * Rincian semua platform di asal_order, termasuk yang sudah disembunyikan.
     *
     * Yang dimatikan tetap ditampilkan (redup, bertanda "nonaktif") supaya
     * Superadmin bisa memakainya lagi tanpa menebak nama lamanya. Jumlah toko
     * ikut ditulis: itulah alasan sebuah platform tidak boleh hilang diam-diam.
     *
     * @return list<array{id: int, label: string, aktif: bool, toko: int}>
     */
    private function platformTersimpan(): array
    {
        $pakai = (new TokoOnlineModel())->jumlahPerPlatform();
        $baris = [];

        foreach ((new LabelAsal())->orderBy('label', 'ASC')->findAll() as $a) {
            $label   = (string) $a->label;
            $baris[] = [
                'id'    => (int) $a->id,
                'label' => $label,
                'aktif' => (string) $a->status === '1',
                'toko'  => $pakai[$label] ?? 0,
            ];
        }

        return $baris;
    }

    /**
     * Sembunyikan / pakai lagi satu platform (khusus Superadmin).
     *
     * Barisnya tidak dihapus: label_invoice.source_id menunjuk ke asal_order.id,
     * jadi nota lama harus tetap menemukan asalnya. "Hapus" di layar berarti
     * pilihan itu hilang dari dropdown platform, bukan datanya lenyap.
     */
    public function platformUbah()
    {
        if ((string) session()->get('level') !== 'superadmin') {
            throw PageNotFoundException::forPageNotFound();
        }

        $id    = (int) $this->request->getPost('id');
        $hidup = (string) $this->request->getPost('aktif') === '1';

        $model = new LabelAsal();
        $label = $id > 0 ? $model->find($id) : null;

        if ($label === null) {
            return $this->response->setJSON(['ok' => false, 'pesan' => 'Platform itu sudah tidak ada.']);
        }

        $model->update($id, ['status' => $hidup ? '1' : '0']);

        return $this->response->setJSON([
            'ok'    => true,
            'id'    => $id,
            'label' => (string) $label->label,
            'aktif' => $hidup,
            'pesan' => $hidup
                ? 'Platform ' . (string) $label->label . ' dipakai lagi di daftar pilihan.'
                : 'Platform ' . (string) $label->label . ' disembunyikan. Toko yang memakainya tidak berubah.',
        ]);
    }

    /**
     * Nama platform yang benar-benar dipakai sebuah toko.
     *
     * Yang sudah terdaftar dikembalikan apa adanya (label lama menang, supaya
     * "shopee" tidak melahirkan kembaran "Shopee"). Yang belum ada hanya
     * diterima dari Superadmin, dan langsung disimpan ke asal_order — itulah
     * arti "tambah platform": pilihannya ikut bertambah untuk toko berikutnya.
     * Label yang sempat dimatikan dihidupkan lagi, bukan digandakan.
     *
     * @return string|null null kalau nama itu tidak dikenal dan tidak boleh dibuat
     */
    private function platformTetap(string $diketik, bool $bolehTambah): ?string
    {
        $nama = mb_substr(trim(preg_replace('/\s+/u', ' ', $diketik) ?? ''), 0, 25);

        if ($nama === '') {
            return '';
        }

        $model = new LabelAsal();
        $kecil = mb_strtolower($nama);

        foreach ($model->findAll() as $a) {
            if (mb_strtolower((string) $a->label) !== $kecil) {
                continue;
            }

            if ((string) $a->status !== '1') {
                // label lama yang sempat dimatikan dihidupkan lagi oleh Superadmin;
                // akun lain cukup memakainya untuk toko ini tanpa mengubah pilihan
                // platform yang dilihat semua orang
                if (! $bolehTambah) {
                    return (string) $a->label;
                }

                $model->update($a->id, ['status' => '1']);
            }

            return (string) $a->label;
        }

        if (! $bolehTambah) {
            return null;
        }

        $model->insert(['label' => $nama, 'status' => '1']);

        return $nama;
    }

    /**
     * Toko yang boleh dibuka lembarannya oleh akun ini. Admin/Superadmin mengurus
     * semuanya (termasuk toko yang belum punya pegangan), CS hanya tokonya sendiri.
     *
     * @return array<int, array<string, mixed>> id_toko => nama, juragan_id, platform
     */
    private function tokoCek(): array
    {
        $model = new TokoOnlineModel();

        return $this->bolehKelola()
            ? $model->petaSemua()
            : $model->petaCek($this->tokoBoleh(), (int) session()->get('id'));
    }

    /**
     * Siapa saja yang boleh membuka lembar tiap toko: tautan langsung di toko itu
     * ditambah akun-akun yang memegang juragannya. Sumber ditulis per nama karena
     * hanya tautan langsung yang bisa dilepas dari kartu ini — yang lewat juragan
     * diatur di layar Penanggung Jawab, bersama sisa hak akses toko itu.
     *
     * @param array<int, array<string, mixed>> $toko
     *
     * @return array<int, list<array{id:int, nama:string, level:string, sumber:string}>>
     */
    private function pemegangCek(array $toko): array
    {
        $model  = new TokoOnlineModel();
        $tautan = $model->pemegang(array_keys($toko));

        $juragan_ids = array_values(array_filter(array_column($toko, 'juragan_id')));
        $sistem      = $juragan_ids !== [] ? (new JuraganModel())->pengelola($juragan_ids) : [];

        $peta = [];

        foreach ($toko as $id_toko => $t) {
            $kenal = [];

            foreach ($tautan[(int) $id_toko] ?? [] as $a) {
                $kenal[(int) $a['id']] = ['id' => (int) $a['id'], 'nama' => (string) $a['nama'], 'level' => (string) $a['level'], 'sumber' => 'tautan'];
            }

            if ($t['juragan_id'] !== null) {
                foreach (['admin', 'cs'] as $lvl) {
                    foreach ($sistem[(int) $t['juragan_id']][$lvl] ?? [] as $a) {
                        $kenal[(int) $a['id']] ??= [
                            'id'     => (int) $a['id'],
                            'nama'   => (string) $a['nama'],
                            'level'  => $lvl,
                            'sumber' => 'juragan',
                        ];
                    }
                }
            }

            $peta[(int) $id_toko] = $kenal;
        }

        return $peta;
    }

    /** Toko online milik akun ini yang boleh dituju oleh butir templat. */
    private function tokoNama(int $id_toko): ?array
    {
        return $this->tokoCek()[$id_toko] ?? null;
    }

    /** @param array<int, array<string, mixed>> $toko @return array<string, mixed> */
    private function saring(array $toko): array
    {
        $tanggal = $this->tanggal((string) $this->request->getGet('tanggal'));
        $kerja   = (string) $this->request->getGet('kerja');

        return [
            // layar ini daftar catatan: tanpa filter apa pun yang terbuka dulu yang
            // ditunjukkan, persis seperti orang membuka pekerjaan hariannya
            'tanggal'  => $tanggal,
            'toko_id'  => (int) $this->request->getGet('toko'),
            'kerja'    => in_array($kerja, ['belum', 'sudah'], true) ? $kerja : 'belum',
            'toko_ids' => array_keys($toko),
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
