<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Catatan alasan pembatalan invoice saat memilih opsi 'Lainnya'.
 */
class CatatanBatalInvoice extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom('catatan_batal')) {
            $this->db->query('ALTER TABLE ' . $this->t('invoice')
                . ' ADD COLUMN catatan_batal VARCHAR(255) NULL AFTER alasan_batal');
        }
    }

    public function down()
    {
        if ($this->punyaKolom('catatan_batal')) {
            $this->db->query('ALTER TABLE ' . $this->t('invoice') . ' DROP COLUMN catatan_batal');
        }
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }

    private function punyaKolom(string $kolom): bool
    {
        return $this->db->fieldExists($kolom, $this->t('invoice'));
    }
}

