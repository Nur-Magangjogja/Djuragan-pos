<?php

namespace App\Controllers\Admin\Settings;

use App\Controllers\BaseController;
use App\Models\NotifikasiModel;
use CodeIgniter\I18n\Time;

/**
 * Kebersihan kotak notifikasi (Admin + Superadmin).
 *
 * Isinya cuma jejak perubahan nota, dan yang sudah terbaca tidak pernah dibaca
 * dua kali. Tanpa pembersihan tabel notifikasi hanya tumbuh: satu baris per
 * perubahan per nota, untuk setiap orang di toko yang sama. Layar ini memberi
 * dua jalan — sapu manual sekali klik, dan batas umur yang membuat aplikasi
 * menyapu sendiri (NotifikasiModel::sapuJadwal(), dipanggil dari halaman
 * notifikasi dan endpoint lonceng, atau dari cron lewat `php spark notif:bersihkan`).
 *
 * Yang boleh masuk hanyalah admin dan superadmin; rutenya yang memagari. CS
 * tidak menghapus notifikasinya sendiri sama sekali — tidak satu-satu, tidak
 * pula manual massal; kotak CS dibersihkan dari sisi manajemen lewat sapuan
 * manual di layar ini atau penjadwalan otomatis (umur_hari + sapuJadwal).
 */
class Notifikasi extends BaseController
{
    public function index()
    {
        $notifikasi = new NotifikasiModel();
        $setelan    = $notifikasi->setelan();
        $user_id    = (int) session()->get('id');

        $data = [
            'title'         => 'Pengaturan Notifikasi',
            'terbaca'       => $notifikasi->hitungTerbaca(),
            'belum'         => $notifikasi->hitungBelum(),
            'sendiri'       => $notifikasi->hitungTerbaca($user_id),
            'usang'         => $notifikasi->usang($setelan['umur_hari']),
            'setelan'       => $setelan,
            'sapu_terakhir' => $this->waktu($setelan['terakhir']),
            'sukses'        => session('sukses'),
            'gagal'         => session('gagal'),
            'errors'        => session('errors') ?: [],
        ];

        return view('admin/pengaturan/notifikasi', $data);
    }

    /**
     * Sapuan manual. `cakupan=sendiri` mempersempit ke kotak akun yang login;
     * selain itu seluruh kotak semua akun disapu — itu memang yang dimaksud
     * bersih-bersih tingkat pengaturan.
     */
    public function hapus()
    {
        $notifikasi = new NotifikasiModel();
        $sendiri    = $this->request->getPost('cakupan') === 'sendiri';
        $user_id    = $sendiri ? (int) session()->get('id') : null;

        $jumlah = $notifikasi->hapusTerbaca($user_id);

        if ($jumlah === 0) {
            return redirect()->to('/admin/settings/notifikasi')
                ->with('gagal', 'Tidak ada notifikasi yang sudah terbaca untuk dihapus.');
        }

        return redirect()->to('/admin/settings/notifikasi')->with(
            'sukses',
            $jumlah . ' notifikasi yang sudah terbaca dihapus' . ($sendiri ? ' dari kotak akun ini.' : ' dari seluruh kotak.')
        );
    }

    /**
     * Simpan batas umur sapuan otomatis. Kalau otomatisnya baru saja dinyalakan,
     * batasnya langsung dijalankan: menunggu sampai jendela lonceng dibuka lagi
     * cuma membuat angkanya terlihat tidak berubah.
     */
    public function simpan()
    {
        $validation = \Config\Services::validation();
        $validation->setRuleGroup('notifSetelan');

        if (! $validation->withRequest($this->request)->run()) {
            return redirect()->to('/admin/settings/notifikasi')
                ->withInput()->with('errors', $validation->getErrors());
        }

        $notifikasi = new NotifikasiModel();
        $aktif      = (int) $this->request->getPost('auto_aktif') === 1;
        $umur       = $notifikasi->simpanSetelan($aktif, (int) $this->request->getPost('umur_hari'));

        if (! $aktif) {
            return redirect()->to('/admin/settings/notifikasi')->with(
                'sukses',
                'Pengaturan disimpan. Hapus otomatis dimatikan; notifikasi terbaca dibiarkan sampai disapu manual.'
            );
        }

        $sapu  = $notifikasi->sapuUsang($umur);
        $pesan = 'Pengaturan disimpan. Notifikasi terbaca yang berumur lebih dari ' . $umur . ' hari akan ikut tersapu.';
        $pesan .= $sapu > 0
            ? ' Pembersihan pertama baru saja dijalankan: ' . $sapu . ' baris terhapus.'
            : ' Pembersihan pertama dijalankan dan belum ada yang melewati batas umur itu.';

        return redirect()->to('/admin/settings/notifikasi')->with('sukses', $pesan);
    }

    /** Waktu dalam zona aplikasi; null berarti belum pernah menyapu apa pun. */
    private function waktu(?int $detik): ?string
    {
        if ($detik === null) {
            return null;
        }

        return Time::createFromTimestamp($detik)
            ->setTimezone(config('App')->appTimezone)
            ->toLocalizedString('d MMM yyyy HH:mm');
    }
}
