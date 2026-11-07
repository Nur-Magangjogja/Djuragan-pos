<?php

namespace App\Controllers;

use App\Models\InvoiceModel;
use App\Models\JuraganModel;
use App\Models\PengaturanModel;
use App\Models\ReminderDikirimModel;

/**
 * Reminder Penjahit — daftar orderan pembuatan/jual yang deadline-nya sudah dekat,
 * supaya tim sempat menghubungi penjahit sebelum tenggat lewat. Sengaja berdiri
 * sendiri (bukan bagian lonceng notifikasi) dan jadi menu prioritas di sidebar.
 *
 * Ambang "berapa hari sebelum deadline" hanya boleh diubah Superadmin; admin & CS
 * cukup melihat daftarnya.
 */
class Reminder extends BaseController
{
    public function index()
    {
        helper('fungsi');

        $hari  = pengingat_penjahit_hari();
        $level = (string) session()->get('level');
        $tab   = $this->request->getGet('tab') === 'sudah' ? 'sudah' : 'belum';

        $model = new InvoiceModel();

        // dua tab diambil sekaligus supaya angkanya bisa tampil di pil tab tanpa
        // query tambahan; yang ditampilkan hanya tab aktif
        $belum = $model->pengingatPenjahit($this->tokoBoleh(), $hari, 0, 0, false);
        $sudah = $model->pengingatPenjahit($this->tokoBoleh(), $hari, 0, 0, true);

        $reminder = $tab === 'sudah' ? $sudah : $belum;

        // tiap kartu mengarah ke layar transaksi yang sudah tersaring ke satu
        // nomor nota (cari[kolom]=faktur) lewat helper yang sama dengan lonceng
        // notifikasi, jadi mengklik reminder benar-benar berfokus ke invoice itu
        // saja — bukan daftar transaksi yang belum tersaring
        $slug_toko = [];

        foreach ((new JuraganModel())->select('id_juragan, juragan')->findAll() as $toko) {
            $slug_toko[(int) $toko->id_juragan] = (string) $toko->juragan;
        }

        foreach (array_keys($reminder) as $i) {
            $reminder[$i]['tautan'] = tautan_invoice_notif(
                (string) $reminder[$i]['seri'],
                $slug_toko[(int) $reminder[$i]['juragan_id']] ?? ''
            );
        }

        $data = [
            'title'      => 'Reminder Pesanan',
            'hari'       => $hari,
            'reminder'   => $reminder,
            'tab'        => $tab,
            'angka'      => ['belum' => count($belum), 'sudah' => count($sudah)],
            'superadmin' => $level === 'superadmin',
        ];

        return view('reminder/index', $data);
    }

    /**
     * Tandai satu reminder sudah diingatkan: pindah dari tab "Belum" ke "Sudah".
     * Aksinya bisa diurungkan (lihat batal), jadi tidak perlu guard berat.
     */
    public function selesai()
    {
        $id = (int) $this->request->getPost('id_invoice');

        if ($id > 0) {
            (new ReminderDikirimModel())->tandai($id, (int) session()->get('id'));
        }

        return redirect()->to(site_url('admin/reminder?tab=belum'))
            ->with('sukses', 'Reminder ditandai sudah diingatkan.');
    }

    /** Urungkan penanda: kembalikan satu reminder ke tab "Belum Diingatkan". */
    public function batal()
    {
        $id = (int) $this->request->getPost('id_invoice');

        if ($id > 0) {
            (new ReminderDikirimModel())->copot($id);
        }

        return redirect()->to(site_url('admin/reminder?tab=sudah'))
            ->with('sukses', 'Penanda dilepas; reminder kembali ke Belum Diingatkan.');
    }

    public function jumlah()
    {
        helper('fungsi');

        return $this->response->setJSON([
            'status' => 'success',
            'count'  => pengingat_penjahit_jumlah(),
        ]);
    }

    /**
     * Simpan ambang hari reminder. Rutenya dipagari auth:superadmin, jadi hanya
     * Superadmin yang bisa sampai ke sini.
     */
    public function simpan()
    {
        helper('fungsi');

        $hari = max(0, min(90, (int) $this->request->getPost('hari')));

        (new PengaturanModel())->tulis('reminder_penjahit_hari', (string) $hari);

        return redirect()->to(site_url('admin/reminder'))->with('sukses', 'Pengaturan reminder tersimpan.');
    }
}
