<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Penanda siapa yang menulis satu baris hasil checklist.
 *
 * Daftar Cek Toko sekarang menampilkan catatan satu-satu, bukan per sesi, dan kolom
 * "siapa yang buat" harus menjawab siapa yang menulis catatan itu — bukan siapa yang
 * membuka sesinya. Butir templat tidak punya penulis tersendiri, jadi kolomnya boleh
 * kosong dan halaman menunjuk penggarap sesinya sebagai fallback.
 *
 * Tambahan murni: tidak ada kolom atau baris yang diubah isinya.
 */
class PenulisCatatanChecklist extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom()) {
            $this->db->query('ALTER TABLE ' . $this->t('crm_checklist_hasil')
                . ' ADD COLUMN dibuat_oleh INT(10) UNSIGNED NULL AFTER item_id');
        }
    }

    public function down()
    {
        if ($this->punyaKolom()) {
            $this->db->query('ALTER TABLE ' . $this->t('crm_checklist_hasil') . ' DROP COLUMN dibuat_oleh');
        }
    }

    private function punyaKolom(): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$this->t('crm_checklist_hasil'), 'dibuat_oleh'])->getNumRows() > 0;
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }
}
