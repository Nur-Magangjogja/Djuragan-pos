<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Sekali-pakai: mendiagnosis kenapa pelanggan baru tidak muncul di Data
 * Pelanggan CRM. Hapus setelah dipakai.
 */
class CekCrmPelangganTmp extends BaseCommand
{
    protected $group       = 'Uji';
    protected $name        = 'cek:crm-pelanggan-tmp';
    protected $description = 'Diagnosis view crm_pelanggan vs pelanggan terbaru.';

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        CLI::write('prefix: ' . var_export($db->getPrefix(), true));

        $tables = $db->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->getResultArray();
        foreach ($tables as $t) {
            $v = array_values($t)[0];
            if (str_contains((string) $v, 'crm_pelanggan')) {
                CLI::write('VIEW: ' . $v);
            }
        }

        CLI::write('--- 5 pelanggan terbaru di order_pelanggan ---');
        $baru = $db->query('SELECT id_pelanggan, nama_pelanggan, created_at, deleted_at FROM order_pelanggan ORDER BY id_pelanggan DESC LIMIT 5')->getResultArray();
        foreach ($baru as $p) {
            CLI::write(sprintf('#%s %s created=%s deleted=%s', $p['id_pelanggan'], $p['nama_pelanggan'], date('Y-m-d H:i', (int) $p['created_at']), var_export($p['deleted_at'], true)));
        }

        CLI::write('--- apakah ada di view order_crm_pelanggan? ---');
        foreach ($baru as $p) {
            $ada = $db->table('order_crm_pelanggan')->where('id_pelanggan', $p['id_pelanggan'])->countAllResults();
            CLI::write('#' . $p['id_pelanggan'] . ' di view: ' . ($ada ? 'ADA' : 'TIDAK'));
        }

        CLI::write('--- 5 invoice terbaru ---');
        $inv = $db->query('SELECT id_invoice, pemesan_id, tanggal_pesan, deleted_at FROM order_invoice ORDER BY id_invoice DESC LIMIT 5')->getResultArray();
        foreach ($inv as $i) {
            $diView = $db->table('order_crm_pelanggan')->where('id_pelanggan', $i['pemesan_id'])->get()->getRowArray();
            CLI::write(sprintf('inv #%s pemesan #%s tgl=%s | di view: %s | jumlah_order view: %s',
                $i['id_invoice'], $i['pemesan_id'], $i['tanggal_pesan'],
                $diView ? 'ADA' : 'TIDAK', $diView['jumlah_order'] ?? '-'));
        }

        CLI::write('--- posisi pelanggan baru di urutan daftar (sort model) ---');
        if ($baru !== []) {
            $idBaru = (int) $baru[0]['id_pelanggan'];
            $posisi = $db->table('order_crm_pelanggan')->where('jumlah_order >', 1)->countAllResults();
            $total  = $db->table('order_crm_pelanggan')->countAllResults();
            CLI::write("total pelanggan di view: $total; yang jumlah_order>1 (di depan si baru): $posisi");
        }

        CLI::write('--- definisi view aktif di DB ---');
        $create = $db->query("SHOW CREATE VIEW order_crm_pelanggan")->getRowArray();
        CLI::write(wordwrap((string) ($create['Create View'] ?? '?'), 110));
    }
}
