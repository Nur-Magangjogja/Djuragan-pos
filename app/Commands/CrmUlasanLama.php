<?php

namespace App\Commands;

use App\Models\InvoiceModel;
use App\Models\TugasModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Backfill tugas "Minta ulasan" dari nota lama.
 *
 * Sapuan rutin (layar tugas & cron crm:reminder) hanya melirik nota yang terakhir
 * disentuh dalam TugasModel::JENDELA_REMINDER hari. Rem itu sengaja: tanpanya satu
 * sapuan melahirkan ratusan tugas dari nota bertahun-tahun lalu dan badge antrean
 * CS melonjak tanpa diminta. Perintah ini melepas rem itu secara sadar — sekali
 * jalan, untuk semua toko, dan tetap tidak membuat tugas kembar (kunci_unik
 * 'ulasan-<id>' dijaga TugasModel::buatUnik).
 *
 * Pakai: php spark crm:ulasan-lama [--maks=500] [--kering]
 */
class CrmUlasanLama extends BaseCommand
{
    protected $group       = 'CRM';
    protected $name        = 'crm:ulasan-lama';
    protected $description = 'Membuat tugas "Minta ulasan" dari nota lama yang dananya sudah masuk (DP/cicilan/lunas), tanpa batas umur nota.';
    protected $usage       = 'crm:ulasan-lama [--maks=500] [--kering]';
    protected $arguments   = [];
    protected $options     = [
        'maks'   => 'Paling banyak berapa nota diperiksa sekali jalan (default 500).',
        'kering' => 'Hitung kandidatnya saja, tidak menulis apa pun.',
    ];

    public function run(array $params)
    {
        // opsi spark datang sebagai kunci asosiatif: --maks=500 => ['maks' => '500']
        $maks   = array_key_exists('maks', $params) ? max(1, (int) $params['maks']) : 500;
        $kering = array_key_exists('kering', $params);

        $calon = $this->hitungCalon();

        if ($kering) {
            CLI::write(
                'Kering: ' . $calon . ' nota punya dana masuk (DP/cicilan/lunas) dan belum dihapus; '
                    . 'maks ' . $maks . ' terbaru akan diproses kalau perintah ini dijalankan sungguhan.',
                'yellow'
            );

            return 0;
        }

        CLI::write('Memeriksa paling banyak ' . $maks . ' nota dari ' . $calon . ' kandidat...');

        $hasil = (new TugasModel())->backfillUlasan(null, $maks);

        CLI::write(
            $hasil['nota'] . ' nota diperiksa: ' . $hasil['dibuat'] . ' tugas ulasan baru, '
                . $hasil['dilewati'] . ' dilewati (tugasnya sudah ada atau jeda reminder_ulasan_hari belum lewat)',
            $hasil['dibuat'] > 0 ? 'green' : 'yellow'
        );

        return 0;
    }

    /** Banyaknya nota yang boleh jadi kandidat, tanpa menulis apa pun. */
    private function hitungCalon(): int
    {
        return (int) db_connect()->table('invoice')
            ->where('deleted_at', null)
            ->where("status_pesanan <> '3'")
            ->whereIn('status_pembayaran', InvoiceModel::DANA_MASUK)
            ->countAllResults();
    }
}
