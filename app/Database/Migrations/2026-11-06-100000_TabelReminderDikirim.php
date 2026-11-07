<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Penanda bahwa sebuah orderan di "Reminder Pesanan" sudah diingatkan.
 *
 * Daftar reminder penjahit dihitung langsung dari tabel invoice (orderan yang
 * tenggatnya mendekat), jadi tidak ada baris khusus yang bisa diberi status.
 * Tabel ini menambahkan penanda itu: satu baris per invoice berarti reminder-nya
 * sudah dikerjakan dan dipindah ke tab "Sudah Diingatkan".
 *
 * Sengaja global (id_invoice unik) — sekali tim menandai sudah direminder,
 * reminder itu berhenti menumpuk di tab "Belum" untuk semua orang, bukan per
 * akun. Kolom user_id & created_at hanya jejak siapa dan kapan menandai.
 */
class TabelReminderDikirim extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_dikirim' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_invoice' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'user_id' => [
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
        ]);
        $this->forge->addPrimaryKey('id_dikirim');
        $this->forge->addUniqueKey('id_invoice');
        $this->forge->createTable('reminder_penjahit_dikirim', true);
    }

    public function down()
    {
        $this->forge->dropTable('reminder_penjahit_dikirim', true);
    }
}
