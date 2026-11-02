<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Buang setelan gateway WhatsApp yang sudah tidak punya kode pembacanya.
 *
 * Jalur kirim WhatsApp sudah dicabut total (Tahap 2): Library providernya sudah
 * dihapus, layarnya sudah dihapus, dan tidak ada satu pun method yang membaca
 * kunci-kunci ini lagi. Isinya token API asli, jadi membiarkannya menumpuk di
 * database cuma memperbesar area yang bisa bocor kalau basis data ini ikut
 * tersalin ke lingkungan lain.
 *
 * down() sengaja TIDAK mengembalikan nilai apa pun: nilai lama memang tidak kita
 * simpan di mana- mana (migrasi bukan tempat menaruh kredensial). Kalau suatu
 * saat gateway perlu dihidupkan lagi, kuncinya dibuat baru dari dasar penyedia.
 */
class HapusKredensialWa extends Migration
{
    /** Nama kunci saja; nilainya tidak pernah dibaca ke dalam file ini. */
    private const KUNCI = [
        'wa_provider',
        'wa_webhook_event_terakhir',
        'fonnte_token',
        'fonnte_base_url',
        'kapso_api_key',
        'kapso_base_url',
        'kapso_phone_number_id',
        'kapso_sandbox_mode',
        'kapso_sandbox_test_number',
    ];

    public function up()
    {
        $this->db->table('crm_setting')
            ->whereIn('setting_key', self::KUNCI)
            ->delete();
    }

    public function down()
    {
        // Tidak ada yang dipulihkan: kunci lama adalah kredensial yang justru
        // ingin kita singkirkan, dan nilainya tidak disimpan di migrasi ini.
    }
}
