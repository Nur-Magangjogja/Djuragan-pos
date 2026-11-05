<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Controllers\Juragan as Jrgn;
use App\Models\ChecklistModel;
use App\Models\JuraganModel;
use App\Models\LabelAsal;
use App\Models\RelasiModel;
use App\Models\TokoOnlineModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Checklist toko harian + pemeriksaan email.
 *
 * CS membuka sesi, menjawab butir satu-satu, lalu menutup sesi. Sesinya selalu
 * punya tugas di antrean, jadi "sudah dicek hari ini atau belum" cukup dilihat
 * di satu tempat — tidak ada daftar pekerjaan kedua yang harus diingat orang.
 *
 * Layar ini juga tempat Superadmin mendelegasikan toko ke akun tertentu. Yang
 * diubah hanyalah tautan toko-akun yang sudah dipakai seluruh halaman, jadi
 * tokonya langsung ikut muncul di daftar kerja akun itu di mana-mana.
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
        $toko  = $this->tokoCek();
        $s     = $this->saring($toko);
        $batas = $this->batas();

        // tab ketiga hanya ada untuk Superadmin; siapa pun yang memanggilnya tanpa
        // hak itu dikembalikan ke daftar catatan, bukan diberi pesan error
        $tabDelegasi = (string) $this->request->getGet('tab') === 'delegasi' && $this->bolehDelegasi();

        if ($tabDelegasi) {
            $baris = [];
            $total = 0;
            $page  = 1;
        } else {
            $page  = max(1, (int) $this->request->getGet('halaman'));
            $total = $model->jumlahCatatan($s);
            $page  = max(1, min($page, max(1, (int) ceil($total / $batas))));
            $baris = $model->catatan($s, $batas, ($page - 1) * $batas);
        }

        $delegasi = $this->bolehDelegasi() && $toko !== [];
        $kelola   = $this->bolehKelola();
        $pemegang = $kelola ? $this->pemegangCek($toko) : [];

        return view('admin/checklist/index', [
            'title'    => 'Cek Toko',
            's'        => $s,
            'baris'    => $model->catatan($s, $batas, ($page - 1) * $batas),
            'total'    => $total,
            'page'     => $page,
            'batas'    => $batas,
            'toko'     => $toko,
            'kelola'   => $kelola,
            'delegasi' => $delegasi,
            'tabDelegasi' => $tabDelegasi,
            'calon'    => $delegasi ? $this->calonDelegasi() : [],
            'pemegang' => $pemegang,
            'kabar'    => (string) session()->getFlashdata('sukses'),
            'galat'    => (string) session()->getFlashdata('galat'),
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

    /** Cabut delegasi juragan sebuah toko. Sesi lama di toko itu tetap bisa dibaca. */
    public function copotDelegasi()
    {
        $bahan = $this->bahanDelegasi();

        if ($bahan === null) {
            return redirect()->to(site_url('admin/checklist'))
                ->with('galat', 'Tokonya atau akunnya tidak dikenali.');
        }

        [, , , $juragan_id] = $bahan;

        [$user, $juragan_id] = [$bahan[1], $bahan[3]];

        if ($juragan_id === null) {
            return redirect()->to(site_url('admin/checklist'))
                ->with('galat', 'Toko ini tidak berdiri di atas juragan, jadi gunakan lepas tautan.');
        }

        $kunci  = ['table' => 1, 'juragan_id' => (int) $juragan_id, 'val_id' => $user];
        $relasi = new RelasiModel();

        if ($relasi->where($kunci)->countAllResults() === 0) {
            return redirect()->to(site_url('admin/checklist'))
                ->with('galat', 'Tautan itu sudah tidak ada.');
        }

        $relasi->where($kunci)->delete();
        $this->buangCacheToko([$user]);

        return redirect()->to(site_url('admin/checklist'))
            ->with('sukses', 'Delegasi dilepas. Sesi checklist yang sudah pernah dibuka di toko itu tidak berubah.');
    }

    /**
     * Serahkan satu toko ke akun Admin/CS tertentu (khusus Superadmin).
     *
     * Toko yang berdiri di atas seorang juragan mengikuti delegasi juragan: akun
     * itu otomatis bekerja di tokonya pada semua halaman. Toko yang tidak punya
     * juragan hanya bisa dicapai lewat tautan checklist, jadi dua jalur itu dipilih
     * di sini dan tidak perlu ditanyakan ke orang yang menekan tombol.
     */
    public function delegasi()
    {
        $bahan = $this->bahanDelegasi();

        if ($bahan === null) {
            return redirect()->to(site_url('admin/checklist'))
                ->with('galat', 'Tokonya atau akunnya tidak dikenali — pilih dari daftar, jangan diketik manual.');
        }

        [$id_toko, $user, $nama, $juragan_id] = $bahan;

        if (! array_key_exists($user, $this->calonDelegasi())) {
            return redirect()->to(site_url('admin/checklist'))
                ->with('galat', $nama . ' bukan akun aktif, jadi tidak bisa memegang toko.');
        }

        if ($juragan_id !== null) {
            return $this->tautkanJuragan((int) $juragan_id, $user, $nama);
        }

        $model = new TokoOnlineModel();
        $sudah = $model->aksesPunyaUser($id_toko, $user);

        if (! $sudah) {
            $model->beriAkses($id_toko, $user, (int) session()->get('id'));
        }

        return redirect()->to(site_url('admin/checklist'))
            ->with('sukses', $sudah
                ? $nama . ' memang sudah memegang toko ini.'
                : 'Toko didelegasikan ke ' . $nama . '. Lembar tokonya langsung muncul di daftar kerja dia.');
    }

    /** Lepas tautan checklist sebuah toko ke seorang akun. */
    public function copotTautan()
    {
        $bahan = $this->bahanDelegasi();

        if ($bahan === null) {
            return redirect()->to(site_url('admin/checklist'))
                ->with('galat', 'Tokonya atau akunnya tidak dikenali.');
        }

        [$id_toko, $user] = $bahan;

        $model = new TokoOnlineModel();

        if (! $model->aksesPunyaUser($id_toko, $user)) {
            return redirect()->to(site_url('admin/checklist'))
                ->with('galat', 'Tautan itu sudah tidak ada.');
        }

        $model->copotAkses($id_toko, $user);
        $this->buangCacheToko([$user]);

        return redirect()->to(site_url('admin/checklist'))
            ->with('sukses', 'Tautan dilepas. Sesi checklist yang sudah pernah dibuka di toko itu tidak berubah.');
    }

    /** Kelola templat butir (Admin & Superadmin). */
    public function aturan()
    {
        helper('fungsi');

        $model  = new ChecklistModel();
        $jenis  = (string) $this->request->getGet('jenis') === 'email' ? 'email' : 'toko';
        $toko   = $this->tokoCek();

        return view('admin/checklist/aturan', [
            'title'    => 'Templat Checklist',
            'jenis'    => $jenis,
            'butir'    => $model->semuaButir($jenis),
            'toko'     => $toko,
            'meta'     => ChecklistModel::JENIS,
            'platform' => $this->platform(),
            'pegawai'  => $this->calonDelegasi(),
            'pemegang' => $this->pemegangCek($toko),
            'juragan'  => $this->tokoId(),
            'kabar'    => (string) session()->getFlashdata('sukses'),
            'galat'    => (string) session()->getFlashdata('galat'),
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

        if ($toko > 0 && ! isset($this->tokoCek()[$toko])) {
            return redirect()->back()->with('galat', 'Toko tambahan bukan delegasimu.')->withInput();
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

    /**
     * Simpan satu toko checklist (khusus Admin & Superadmin): nama bebas, juragan
     * boleh dikosongkan, lengkap dengan daftar akun yang boleh membukanya.
     */
    public function simpanToko()
    {
        if (! $this->bolehKelola()) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model   = new TokoOnlineModel();
        $id_toko = (int) $this->request->getPost('id_toko');
        $nama    = trim((string) $this->request->getPost('nama'));
        $juragan = (int) $this->request->getPost('juragan_id');

        if ($nama === '') {
            return redirect()->back()->with('galat', 'Nama tokonya diisi dulu.')->withInput();
        }

        if ($juragan > 0 && ! $this->bolehToko($juragan)) {
            return redirect()->back()->with('galat', 'Juragan itu tidak boleh ditautkan oleh akun ini.')->withInput();
        }

        if ($id_toko > 0 && $model->satu($id_toko) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $id_toko = $model->simpan([
            'juragan_id' => $juragan,
            'platform'   => (string) $this->request->getPost('platform'),
            'nama'       => $nama,
            'url'        => (string) $this->request->getPost('url'),
            'catatan'    => (string) $this->request->getPost('catatan'),
            'aktif'      => (string) $this->request->getPost('aktif') === '0' ? 0 : 1,
        ], (int) session()->get('id'), $id_toko);

        $ubah = $this->tautkanCs($id_toko, (array) $this->request->getPost('cs'));

        return redirect()->to(site_url('admin/checklist/aturan'))
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
     * Memegang toko berarti akun itu bisa bekerja di tokonya pada semua halaman,
     * jadi yang boleh mengubah tautannya hanya Superadmin.
     */
    private function bolehDelegasi(): bool
    {
        return (string) session()->get('level') === 'superadmin';
    }

    /**
     * Bahan satu aksi delegasi, sudah lolos pagar. null menjawab dua hal
     * sekaligus: tokonya tidak ada dan akunnya bukan Admin/CS.
     *
     * Akun nonaktif ikut lolos di sini karena mencabut tautannya justru masih
     * perlu — yang boleh ditambahkan hanya yang aktif, itu dijaga delegasi().
     *
     * @return array{0:int,1:int,2:string,3:?int}|null id_toko, id akun, nama, juragan_id
     */
    private function bahanDelegasi(): ?array
    {
        if (! $this->bolehDelegasi()) {
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

        foreach ((new LabelAsal())->where('status', 1)->orderBy('label', 'ASC')->findAll() as $a) {
            $platform[(string) $a->label] = (string) $a->label;
        }

        return $platform;
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
