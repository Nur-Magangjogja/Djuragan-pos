<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\KpiModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Halaman KPI karyawan untuk admin dan superadmin.
 *
 * Yang ditampilkan hanya angka yang benar-benar bisa dihitung dari isi database.
 * Indikator yang pembilangnya belum pernah dicatat tampil dengan keterangan, bukan
 * dengan angka nol: skor palsu lebih berbahaya daripada kolom yang kosong.
 *
 * Saringan (periode, peran, pegawai, toko) diproses di model sebagai bind yang
 * sudah divalidasi di sini, jadi tidak ada satu pun nilai dari URL yang masuk ke
 * SQL apa adanya.
 */
class Kpi extends BaseController
{
    /** Pilihan keranjang waktu grafik. */
    private const PERIODE = ['harian' => 'Harian', 'mingguan' => 'Mingguan', 'bulanan' => 'Bulanan'];

    /** Pilihan peran yang dinilai. KPI khusus Customer Service, admin/superadmin tidak dinilai. */
    private const PERAN = ['semua' => 'Customer Service'];

    /** Baris per halaman rekap dan detail. */
    private const HALAMAN = [10, 25, 50];

    /**
     * Ringkasan: kartu indikator, grafik tren, perbandingan antarpegawai, rekap.
     *
     * @return string
     */
    public function index()
    {
        $s     = $this->saring();
        $model = new KpiModel();

        $batas   = $this->batas((string) $this->request->getGet('batas'));
        $banding = $this->indikator((string) $this->request->getGet('banding'));

        // Target GMV mentah (sebelum disesuaikan cakupan): `target` = pcs per-CS,
        // `target_tim` = pcs total tim. Keduanya dibutuhkan blok Ringkasan Tim dan
        // kolom status per-baris di tabel rekap.
        $targetRaw = $this->petaTarget($model);
        $g         = $targetRaw['gmv_cs'] ?? [];
        $percs     = $g['target'] ?? null;
        $tim       = $g['target_tim'] ?? null;

        // Kartu indikator mengikuti saringan: tanpa pegawai = melihat tim (pakai
        // target_tim), satu pegawai terpilih = per-CS (pakai target).
        $target  = $targetRaw;
        $gmv_tim = $s['user_id'] === null;
        if (isset($target['gmv_cs'])) {
            $target['gmv_cs'] = $this->cakupanTargetGmv($target['gmv_cs'], $gmv_tim);
        }

        // Ringkasan Tim dan tabel rekap SELALU memakai cakupan seluruh CS: saringan
        // pegawai hanya menyorot baris yang bersangkutan, tidak menyembunyikan yang
        // lain, jadi angka tim dan angka per orang tetap tampil berdampingan.
        $sTim  = array_merge($s, ['user_id' => null]);
        $rekap = $model->rekap($sTim, $batas, $this->halaman() * $batas);

        return view('admin/kpi/index', [
            'title'       => 'KPI Karyawan',
            's'           => $s,
            'kartu'       => $model->kartu($s),
            'target'      => $target,
            'gmv_tim'     => $gmv_tim,
            // isi blok ini pcs produk katalog dari nota lunas, bukan nominal rupiah
            'gmv_ringkas' => [
                'tim_nilai'      => $model->kartu($sTim)['gmv_cs']['nilai'],
                'tim_target'     => $tim,
                'percs_target'   => $percs,
                'arah'           => $g['arah'] ?? 'lebih_tinggi',
                'jumlah_pegawai' => (int) $rekap['jumlah'],
            ],
            'tren'        => $model->tren($s),
            'pegawai'     => $model->pegawaiPilihan($s['peran']),
            'juragans'    => $this->juraganPilihan(),
            'periode'     => self::PERIODE,
            'peran'       => self::PERAN,
            'indikator'   => KpiModel::INDIKATOR,
            'rekap'       => $rekap,
            'batas'       => $batas,
            'halaman'     => $this->halaman(),
            'banding'     => $banding,
            'pembanding'  => $model->pembanding($s, $banding),
        ]);
    }

    /**
     * Peringkat karyawan dari poin aktivitas.
     *
     * Hanya menghitung kerja yang meninggalkan jejak beserta pelakunya di
     * database; aturan poinnya ditampilkan lengkap di halaman yang sama.
     *
     * @return string
     */
    public function ranking()
    {
        $s     = $this->saring();
        $model = new KpiModel();

        $batas     = $this->batas((string) $this->request->getGet('batas'));
        $halaman   = $this->halaman();
        $peringkat = $model->peringkat($s);

        // nomor halaman di luar daftar dijepit ke halaman terakhir supaya judul
        // "X sampai Y dari Z orang" tidak pernah terbalik
        $halaman = min($halaman, max(0, (int) ceil(count($peringkat) / $batas) - 1));

        return view('admin/kpi/ranking', [
            'title'     => 'Ranking Karyawan',
            's'         => $s,
            'peringkat' => $peringkat,
            'baris'     => array_slice($peringkat, $halaman * $batas, $batas),
            'jumlah'    => count($peringkat),
            'batas'     => $batas,
            'halaman'   => $halaman,
            'aturan'    => $model->aturanPoin($s),
            'luar'      => KpiModel::TIDAK_TERNILAI,
            'pegawai'   => $model->pegawaiPilihan($s['peran']),
            'juragans'  => $this->juraganPilihan(),
            'periode'   => self::PERIODE,
            'peran'     => self::PERAN,
        ]);
    }

    /**
     * Baris pembentuk satu angka indikator, supaya angkanya bisa diaudit.
     *
     * @return string
     */
    public function detail(string $indikator)
    {
        $s     = $this->saring();
        $model = new KpiModel();

        if (! in_array($indikator, KpiModel::INDIKATOR, true)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $batas   = $this->batas((string) $this->request->getGet('batas'));
        $halaman = $this->halaman();
        $jumlah  = $model->detailJumlah($s, $indikator);

        // indikator tanpa baris target (order_diproses, resolution_time) tetap
        // punya nama dan penjelasan: yang dibaca pembaca adalah rumus angkanya
        $aturan = $model->targetSatu($indikator) ?? (KpiModel::LABEL[$indikator] ?? null);

        // GMV: target yang tampil di halaman ini harus sama dengan kartu Ringkasan
        // (Tim CS saat melihat semua orang, per-CS saat satu pegawai dipilih); dua-
        // duanya sama-sama dalam pcs, bukan rupiah
        if ($indikator === 'gmv_cs') {
            $aturan = $this->cakupanTargetGmv($aturan, $s['user_id'] === null);
        }

        return view('admin/kpi/detail', [
            'title'     => 'Detail ' . ($aturan['nama'] ?? $indikator),
            's'         => $s,
            'indikator' => $indikator,
            'aturan'    => $aturan,
            'jumlah'    => $jumlah,
            'baris'     => $model->detail($s, $indikator, $batas, $halaman * $batas),
            'batas'     => $batas,
            'halaman'   => $halaman,
            'pegawai'   => $model->pegawaiPilihan($s['peran']),
            'juragans'  => $this->juraganPilihan(),
            'periode'   => self::PERIODE,
            'peran'     => self::PERAN,
        ]);
    }

    /**
     * Daftar target KPI, sekaligus tempat mengubahnya.
     *
     * Khusus Superadmin. Admin hanya menilai hasilnya (Ringkasan, Ranking, Detail),
     * karena angka target adalah keputusan manajemen, bukan kerja harian toko.
     *
     * @return string|\CodeIgniter\HTTP\RedirectResponse
     */
    public function target()
    {
        // rutenya sudah memagari; ini pagar kedua sekaligus jawaban yang lebih
        // sopan daripada halaman login: admin dikembalikan ke Ringkasan KPI
        if (! $this->bolehUbah()) {
            return redirect()->to('admin/kpi');
        }

        $model = new KpiModel();

        return view('admin/kpi/target', [
            'title'    => 'Target KPI',
            'target'   => $model->targetSemua(),
            'poin'     => $model->poinSemua(),
            // kotak angka di layar boleh mengetik sejauh yang server terima
            'batas'    => ['min' => KpiModel::POIN_MIN, 'max' => KpiModel::POIN_MAX],
            'boleh'    => $this->bolehUbah(),
            'kabar'    => (string) session()->getFlashdata('kabar'),
            'pembaru'  => $model->pegawaiPilihan('semua'),
        ]);
    }

    /**
     * Simpan poin per aktivitas.
     *
     * Sama seperti target: keputusannya manajemen, jadi rutenya khusus Superadmin
     * dan ini pagar kedua. Slug yang tidak dikenal dibuang, jadi nilai yang
     * dikirim dari form lama (aturan yang sudah dicabut) tidak pernah masuk setelan.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function simpanPoin()
    {
        if (! $this->bolehUbah()) {
            return redirect()->to('admin/kpi');
        }

        $hasil = (new KpiModel())->simpanPoin(
            (array) $this->request->getPost('poin'),
            pengguna_sesi()
        );

        $kabar = $hasil['diubah'] . ' aturan poin disimpan';

        if ($hasil['lewat'] > 0) {
            $kabar .= '; ' . $hasil['lewat'] . ' dilewati karena di luar '
                . KpiModel::POIN_MIN . ' sampai ' . KpiModel::POIN_MAX;
        }

        if ($hasil['di luar'] > 0) {
            $kabar .= '; ' . $hasil['di luar'] . ' slug tidak dikenal';
        }

        return redirect()->to('admin/kpi/target')->with('kabar', $kabar . '.');
    }

    /**
     * Simpan seluruh target sekaligus.
     *
     * Form-nya satu tabel, jadi namanya array per indikator. Kolom yang dipakai
     * orang untuk mengenali indikator (nama, definisi, aturan) tidak ikut ditulis:
     * kalau rumus bisa diubah dari halaman ini, angka bulan lalu tidak bisa
     * dibandingkan lagi.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function simpanTarget()
    {
        if (! $this->bolehUbah()) {
            return redirect()->to('admin/kpi');
        }

        $model   = new KpiModel();
        $target  = (array) $this->request->getPost('target');
        $aktif   = (array) $this->request->getPost('aktif');
        $tim     = (array) $this->request->getPost('target_tim');
        $known   = array_column($model->targetSemua(), 'satuan', 'indikator');

        $diubah = 0;
        $lewat  = 0;

        foreach ($target as $indikator => $nilai) {
            $satuan = $known[(string) $indikator] ?? null;

            if ($satuan === null) {
                $lewat++;

                continue;
            }

            $angka  = $this->angka($nilai, $satuan);

            $data = [
                'target'  => $angka,
                'aktif'   => isset($aktif[$indikator]) ? 1 : 0,
            ];

            // target_tim hanya punya isian di form untuk gmv_cs; indikator lain tidak
            // mengirimnya, jadi kolomnya dibiarkan apa adanya (tidak ditimpa jadi NULL)
            if (array_key_exists($indikator, $tim)) {
                $data['target_tim'] = $this->angka($tim[$indikator], $satuan);
            }

            $model->simpanTarget((string) $indikator, $data, pengguna_sesi());

            $diubah++;
        }

        session()->setFlashdata('kabar', $diubah . ' indikator disimpan'
            . ($lewat > 0 ? ', ' . $lewat . ' baris dilewati karena nilainya tidak dikenal di luar daftar.' : '.'));

        return redirect()->to('admin/kpi/target');
    }

    /**
     * Saringan dari query string, sudah dibersihkan.
     *
     * Account dengan level cs tidak punya halaman ini (rutenya auth:admin,superadmin),
     * jadi peran dan pegawai di sini selalu di bawah kendali admin.
     *
     * @return array<string, mixed>
     */
    private function saring(): array
    {
        $awal  = $this->tanggal((string) $this->request->getGet('awal'));
        $akhir = $this->tanggal((string) $this->request->getGet('akhir'));

        if ($awal === '') {
            $awal = date('Y-m-01', strtotime('-5 month'));
        }

        if ($akhir === '') {
            $akhir = date('Y-m-d');
        }

        if ($awal > $akhir) {
            [$awal, $akhir] = [$akhir, $awal];
        }

        $periode = (string) $this->request->getGet('periode');
        $peran   = (string) $this->request->getGet('peran');

        $ids     = $this->juraganIds();
        $juragan = (int) $this->request->getGet('juragan');

        // toko yang tidak dimiliki akun ini dianggap tidak dipilih
        if ($juragan > 0 && in_array($juragan, $ids, true)) {
            $ids = [$juragan];
        } else {
            $juragan = 0;
        }

        $pegawai   = (int) $this->request->getGet('pegawai');
        $dikenal   = array_keys((new KpiModel())->pegawaiPilihan(
            array_key_exists($peran, self::PERAN) ? $peran : 'semua'
        ));

        if ($pegawai > 0 && ! in_array($pegawai, $dikenal, true)) {
            $pegawai = 0;
        }

        return [
            'awal'        => $awal,
            'akhir'       => $akhir,
            'periode'     => array_key_exists($periode, self::PERIODE) ? $periode : 'bulanan',
            'peran'       => array_key_exists($peran, self::PERAN) ? $peran : 'semua',
            'user_id'     => $pegawai > 0 ? $pegawai : null,
            'juragan_id'  => $juragan,
            'juragan_ids' => $ids,
        ];
    }

    /** @return array<string, array<string, mixed>> indikator => baris target */
    private function petaTarget(KpiModel $model): array
    {
        $peta = [];

        foreach ($model->targetSemua() as $t) {
            $peta[$t['indikator']] = $t;
        }

        return $peta;
    }

    /**
     * GMV punya dua target dalam pcs: total Tim CS (target_tim) vs kontribusi satu
     * orang (target). Sesuaikan kolom `target` baris ini dengan cakupan yang sedang
     * dilihat supaya kartu Ringkasan dan halaman Detail memakai angka yang sama.
     * Penanda `scope` ikut dipasang agar view bisa memberi label yang jelas.
     *
     * @param array<string, mixed>|null $aturan baris kpi_target indikator ini
     * @param bool                      $tim    true = melihat seluruh Tim CS
     *
     * @return array<string, mixed>|null
     */
    private function cakupanTargetGmv(?array $aturan, bool $tim): ?array
    {
        if ($aturan === null) {
            return null;
        }

        if ($tim) {
            $aturan['target'] = $aturan['target_tim'] ?? null;
        }

        $aturan['scope'] = $tim ? 'tim' : 'per_cs';

        return $aturan;
    }

    /** Indikator yang sedang dipilih untuk grafik perbandingan. */
    private function indikator(string $pilih): string
    {
        return in_array($pilih, KpiModel::INDIKATOR, true) ? $pilih : 'gmv_cs';
    }

    /** Nomor halaman berbasis nol; query string memakai nomor berbasis satu. */
    private function halaman(): int
    {
        return max(1, (int) $this->request->getGet('halaman')) - 1;
    }

    private function batas(string $batas): int
    {
        return in_array((int) $batas, self::HALAMAN, true) ? (int) $batas : self::HALAMAN[0];
    }

    /**
     * Menentukan target hanya boleh Superadmin. Rutenya sudah membatasi,
     * ini pagar kedua: admin cukup melihat ringkasan dan ranking.
     */
    private function bolehUbah(): bool
    {
        return (string) session()->get('level') === 'superadmin';
    }

    private function tanggal(string $nilai): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $nilai, $m)) {
            return '';
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $nilai : '';
    }

    /**
     * Angka desimal dari input orang; selain itu dianggap kosong.
     *
     * Rupiah dan pcs dibulatkan sampai unit: target GMV adalah angka utuh — baik
     * nominal maupun jumlah barang katalog — tidak pernah memakai pecahan, dan
     * kotak isinya tidak menerima desimal.
     */
    private function angka(mixed $nilai, ?string $satuan = null): ?float
    {
        $bersih = trim((string) $nilai);

        if ($bersih === '' || ! is_numeric($bersih)) {
            return null;
        }

        return round((float) $bersih, in_array($satuan, ['rupiah', 'pcs'], true) ? 0 : 2);
    }
}
