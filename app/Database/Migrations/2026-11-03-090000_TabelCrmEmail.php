<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Daftar alamat email yang diperiksa CS, dan siapa yang memegangnya.
 *
 * Yang sudah ada di aplikasi ini cuma catatan "sudah lihat kotak masuk" (jenis
 * tugas `pemeriksaan_email`) — tidak ada benda bernama "email" yang bisa
 * didelegasikan. Tabel ini menambahkan benda itu: `crm_email` adalah daftar
 * alamat yang pernah ditambahkan Admin/Superadmin, dan `crm_email_pegang`
 * menjawab "alamat ini dipegang siapa saja".
 *
 * Sengaja TIDAK ada kolom sandi, token, atau apa pun yang menyerupai kredensial:
 * layar ini hanya untuk menugaskan dan mengingat, bukan untuk membuka mailbox.
 *
 * `crm_tugas.email_id` (migration berikutnya di file yang sama) membuat sebuah
 * catatan cek email bisa menunjuk satu alamat. Kolomnya nullable supaya catatan
 * lama — yang lahir sebelum ada daftar email — tetap terbaca apa adanya.
 */
class TabelCrmEmail extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_email' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'default'    => '',
            ],
            'catatan' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'aktif' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
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
        $this->forge->addPrimaryKey('id_email');
        $this->forge->addUniqueKey('email');
        $this->forge->addKey('aktif');
        $this->forge->createTable('crm_email', true);

        $this->forge->addField([
            'id_pegang' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'email_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
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
        $this->forge->addPrimaryKey('id_pegang');
        $this->forge->addUniqueKey(['email_id', 'user_id']);
        $this->forge->addKey('user_id');
        $this->forge->createTable('crm_email_pegang', true);

        // catatan cek email yang sudah ada tidak ikut berubah: kolomnya boleh NULL
        // dan ditambahkan lewat ALTER mentah (pola yang sama dengan KolomDeadline)
        if (! $this->punyaKolom('email_id')) {
            $this->db->query('ALTER TABLE ' . $this->t('crm_tugas') . ' ADD COLUMN email_id INT UNSIGNED NULL AFTER invoice_id');
            $this->db->query('ALTER TABLE ' . $this->t('crm_tugas') . ' ADD INDEX idx_crm_tugas_email (email_id)');
        }
    }

    public function down()
    {
        if ($this->punyaKolom('email_id')) {
            $this->db->query('ALTER TABLE ' . $this->t('crm_tugas') . ' DROP INDEX idx_crm_tugas_email');
            $this->db->query('ALTER TABLE ' . $this->t('crm_tugas') . ' DROP COLUMN email_id');
        }

        $this->forge->dropTable('crm_email_pegang', true);
        $this->forge->dropTable('crm_email', true);
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }

    private function punyaKolom(string $kolom): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$this->t('crm_tugas'), $kolom])->getNumRows() > 0;
    }
}
