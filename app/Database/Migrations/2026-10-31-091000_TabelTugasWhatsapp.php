<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Data pendukung menu Reminder WhatsApp.
 *
 * Tugasnya sendiri tetap berada di `crm_tugas` (satu mesin antrean: status,
 * pengambil, riwayat append-only). Yang ditambah di sini hanya kebutuhan yang
 * khusus untuk percakapan WhatsApp:
 *
 * 1. `crm_wa_nomor` — daftar nomor pengirim milik toko yang boleh dipakai CS.
 *    Admin/Superadmin yang menentukannya; satu nomor boleh disematkan ke satu CS
 *    supaya jelas siapa yang berhak memakainya.
 * 2. `crm_tugas_whatsapp` — bagian 1:1 dari tugas yang menyimpan nomor pengirim,
 *    nomor pelanggan, dan jenis tindak lanjutnya.
 *
 * Nomor disimpan sebagai TEKSSnapshot, bukan hanya id: kalau besok nomor toko
 * diganti, tugas lama harus tetap menunjukkan nomor yang dulu dipakai CS.
 */
class TabelTugasWhatsapp extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_nomor' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'juragan_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
            ],
            'nomor' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'aktif' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'catatan' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'dibuat_oleh' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'updated_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id_nomor');
        $this->forge->addUniqueKey(['juragan_id', 'nomor']);
        $this->forge->addKey(['user_id', 'aktif']);
        $this->forge->createTable('crm_wa_nomor', true);

        $this->forge->addField([
            'id_whatsapp' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'tugas_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'nomor_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'nomor_dari' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => true,
            ],
            'nomor_ke' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => true,
            ],
            'tindak_lanjut' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'default'    => 'perlu_dibalas',
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'updated_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id_whatsapp');
        $this->forge->addUniqueKey('tugas_id');
        $this->forge->addKey('tindak_lanjut');
        $this->forge->createTable('crm_tugas_whatsapp', true);
    }

    public function down()
    {
        $this->forge->dropTable('crm_tugas_whatsapp', true);
        $this->forge->dropTable('crm_wa_nomor', true);
    }
}
