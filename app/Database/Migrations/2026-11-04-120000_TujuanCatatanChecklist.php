<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Catatan yang ditulis Admin/Superadmin ditujukan ke orang tertentu.
 *
 * Cek toko sudah bisa mendelegasikan toko ke beberapa akun sekaligus, jadi "tulis
 * catatan untuk toko ini" masih ambigu: siapa yang harus mengerjakan? Kolom ini
 * menyimpan orang yang ditunjuk, diambil dari daftar pemegang toko itu, sehingga
 * barisnya jelas sejak dibuka.
 *
 * Catatan CS tidak memakai kolom ini (dia menulis apa yang sudah IA kerjakan),
 * dan catatan lama tetap NULL — tidak ada data lama yang diubah isinya.
 */
class TujuanCatatanChecklist extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom()) {
            $this->db->query('ALTER TABLE ' . $this->t('crm_checklist_hasil')
                . ' ADD COLUMN ditujuk_ke INT(10) UNSIGNED NULL AFTER dibuat_oleh');
        }
    }

    public function down()
    {
        if ($this->punyaKolom()) {
            $this->db->query('ALTER TABLE ' . $this->t('crm_checklist_hasil') . ' DROP COLUMN ditujuk_ke');
        }
    }

    private function punyaKolom(): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$this->t('crm_checklist_hasil'), 'ditujuk_ke'])->getNumRows() > 0;
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }
}
