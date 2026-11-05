<?php

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Controllers\Juragan as Jrgn;
use App\Models\DasborModel;
use App\Models\SopModel;
use App\Models\TugasModel;

/**
 * Dasbor kerja CS: angka transaksi toko yang dipegang, panduan SOP, dan tugas
 * yang menunggu dikerjakan.
 *
 * Kartunya sengaja memakai model yang sama dengan Dasbor admin (DasborModel,
 * disaring ke toko akun ini) supaya CS dan admin membaca angka yang sama untuk
 * toko yang sama. Yang beda cuma tujuan tautannya: daftar transaksi CS ada di
 * user/invoices, dan rute itu menuntut slug toko — bukan 'semua'.
 */
class Dasbor extends BaseController
{
    /**
     * Dokumen yang tetap terlihat di kartu SOP: dua per kelompok, isinya dipotong
     * satu baris, supaya dasbor masih muat satu layar tanpa digulir.
     */
    private const SOP_TAMPIL = 2;

    /** Tugas per kelompok yang terlihat di kartu; sisanya lewat "semua tugas". */
    private const TUGAS_TAMPIL = 10;

    /** @return string */
    public function index()
    {
        $user   = (int) session()->get('id');
        $ids    = $this->juraganIds();
        $dasbor = new DasborModel();
        $tugas  = new TugasModel();
        $sop    = new SopModel();

        $juragans = Jrgn::by_user($user)[$user]['juragan'] ?? [];
        $sasaran  = $this->juraganBy($user)->juragan ?? '';

        // TugasModel hanya membatasi toko kalau daftar tokonya terisi; akun tanpa
        // relasi akan mendapat seluruh antrean toko orang lain. 0 tidak pernah
        // menjadi id_juragan, jadi saringannya menyisakan tugas tanpa toko saja
        // (mis. pemeriksaan email bersama) — sama seperti yang boleh ia lihat
        $toko = $ids === [] ? [0] : $ids;

        return view('user/dasbor', [
            'title'     => 'Dasbor',
            'hari'      => $dasbor->satuHari($ids, date('Y-m-d')),
            'saringan'  => $dasbor->saringan($ids),
            'sisa'      => $dasbor->sisaTagihan($ids),
            // slug toko tujuan kartu; akun tanpa relasi dapat '' dan kartunya
            // tidak dijadikan tautan
            'sasaran'   => is_string($sasaran) ? $sasaran : '',
            'nama_toko' => array_values(array_map('strval', array_column($juragans, 'nama'))),
            // kelompoknya dikirim utuh: panel cuma menampilkan SOP_TAMPIL pertama,
            // sisanya muncul begitu panelnya dibuka sebagai dialog di tengah
            'sop_grup'  => SopModel::grup($sop->daftar(['aktif' => 1, 'untuk' => 'cs'])),
            'sop_kelola' => false,
            'sop_modal' => true,
            'sop_tampil' => self::SOP_TAMPIL,
            'pegangan'  => $tugas->daftar(['terbuka' => 1, 'dikerjakan_oleh' => $user, 'juragan_ids' => $toko], self::TUGAS_TAMPIL, 0),
            'antrean'   => $tugas->daftar(['antrean' => 1, 'juragan_ids' => $toko], self::TUGAS_TAMPIL, 0),
            'meta'      => [
                'status'     => TugasModel::STATUS,
                'jenis'      => TugasModel::JENIS,
                'prioritas'  => TugasModel::PRIORITAS,
                'untuk_sop'  => SopModel::UNTUK,
            ],
        ]);
    }
}
