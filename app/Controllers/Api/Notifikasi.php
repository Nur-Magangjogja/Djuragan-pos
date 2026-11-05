<?php

namespace App\Controllers\Api;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\I18n\Time;

class Notifikasi extends \CodeIgniter\Controller
{
    use ResponseTrait;

    public function get()
    {
        $notifikasi = new \App\Models\NotifikasiModel();
        helper('fungsi');

        // kotak notifikasi selalu milik akun yang login; `id` dari request diabaikan
        $user_id = (int) session()->get('id');
        $page    = $this->request->getGet('page');
        $baca    = $this->request->getGet('dibaca');

        // lonceng ini dipanggil polling, jadi sapuan otomatis harus lewat gerbang
        // waktunya dulu: hampir selalu jawabannya "belum waktunya", tanpa query tulis
        $notifikasi->sapuJadwal();

        $limit = config('Pager')->perPage;
        $page  = (int) $this->request->getGet('page');

        if (! isset($page) || $page === 0 || $page === 1) {
            $page   = 1;
            $offset = 0;
        } else {
            $offset = ($page - 1) * $limit;
            $page   = $page;
        }

        $dibaca = false;
        if (isset($baca) && $baca === '1') {
            $dibaca = true;
        }

        $counter = $notifikasi->ambil($user_id, $dibaca)->countAllResults();

        $res = [
            'page'       => (int) $page,
            'next'       => (ceil($counter / $limit) > $page ? true : false),
            'count'      => $counter,
            'count_text' => formatRibuan($counter),
        ];

        $x = $notifikasi->ambil($user_id, $dibaca, $limit, $offset)->get()->getResult();

        foreach ($x as $notif) {
            // template dari config memang berisi markup ikon, jadi yang di-escape
            // hanyalah nilai yang dimasukkan ke dalamnya; dulu pesan ini menimpa
            // variabel $notifikasi yang sudah dipakai untuk modelnya
            $pesan      = replacer(config('JuraganConfig')->notifikasi[$notif->type], ['invoice' => esc((string) $notif->seri), 'nama' => esc((string) $notif->name)]);
            $tanggal    = Time::createFromTimestamp($notif->created_at)->setTimezone(config('App')->appTimezone);

            $res['results'][] = [
                'id'         => (int) $notif->id_notifikasi,
                'notif'      => $pesan,
                'created_at' => $tanggal->toLocalizedString('EEE, d MMM yyyy (HH:mm:ss)'),
                'invoice'    => $notif->seri,
                'juragan'    => $notif->juragan,
                'prioritas'  => (int) ($notif->prioritas ?? 0) === 1,
                'tautan'     => tautan_invoice_notif($notif->seri, $notif->juragan),
            ];
        }

        return $this->respond($res);
    }

    /**
     * Hapus satu notifikasi milik akun yang login, khusus yang sudah terbaca.
     * Hanya sisi manajemen (Admin/Superadmin): CS tidak lagi membereskan
     * kotaknya sendiri — kotak CS dibersihkan dari Pengaturan Notifikasi
     * (sapu manual) atau lewat penjadwalan otomatis (sapuJadwal, cron
     * `php spark notif:bersihkan`). Pagar ini di server; tombolnya sendiri
     * memang tidak dirender untuk CS di halaman daftar.
     */
    public function hapus()
    {
        if (! in_array((string) session()->get('level'), ['admin', 'superadmin'], true)) {
            return $this->respond([
                'status'   => 'DITOLAK',
                'pesan'    => 'Notifikasi hanya bisa dihapus dari sisi manajemen.',
                'terhapus' => 0,
            ], 403);
        }

        $notifikasi = new \App\Models\NotifikasiModel();
        $id         = (int) $this->request->getPost('id');
        $terhapus   = $id > 0 ? $notifikasi->hapusSatu($id, (int) session()->get('id')) : 0;

        return $this->respond([
            'status'   => $terhapus > 0 ? 'OK' : 'KOSONG',
            'terhapus' => $terhapus,
        ]);
    }

    public function mark_all()
    {
        $notifikasi = new \App\Models\NotifikasiModel();

        // sama seperti get(): yang ditandai terbaca hanyalah milik akun yang login
        $user_id = (int) session()->get('id');
        $notifikasi->whereIn('for', [$user_id])
            ->set(['read_at' => time()])
            ->update();

        return $this->respond(['status' => 'OK', 'message' => 'Semua notifikasi sudah ditandai terbaca']);
    }

    public function mark()
    {
        $notifikasi = new \App\Models\NotifikasiModel();

        $id     = (int) $this->request->getPost('id');
        $action = $this->request->getPost('action');

        $dibaca = time();
        if ($action === 'belumBaca') {
            $dibaca = null;
        }

        $data = [
            'id_notifikasi' => $id,
            'read_at'       => $dibaca,
        ];

        // `save()` dulu mengubah baris mana pun tanpa peduli pemiliknya; sekarang
        // hanya notifikasi milik akun ini yang tersentuh
        $notifikasi
            ->where('id_notifikasi', $id)
            ->where('for', (int) session()->get('id'))
            ->set(['read_at' => $dibaca])
            ->update();

        return $this->respond($data);
    }
}
