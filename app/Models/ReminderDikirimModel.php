<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Penanda "sudah diingatkan" untuk orderan di Reminder Pesanan.
 *
 * Satu baris per invoice (id_invoice unik). Ada barisnya = reminder orderan itu
 * sudah dikerjakan dan pindah ke tab "Sudah Diingatkan"; tidak ada = masih di
 * tab "Belum Diingatkan". Tandai/copot dipakai berpasangan supaya aksi bisa
 * diurungkan.
 */
class ReminderDikirimModel extends Model
{
    protected $table         = 'reminder_penjahit_dikirim';
    protected $primaryKey    = 'id_dikirim';
    protected $returnType    = 'array';
    protected $allowedFields = ['id_invoice', 'user_id'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $dateFormat    = 'int';

    /**
     * Tandai satu orderan sudah diingatkan. Menimpa (replace) supaya menandai
     * ulang tidak melempar error kunci unik.
     */
    public function tandai(int $id_invoice, int $user_id): void
    {
        if ($id_invoice <= 0) {
            return;
        }

        $this->replace([
            'id_invoice' => $id_invoice,
            'user_id'    => $user_id > 0 ? $user_id : null,
        ]);
    }

    /** Copot penanda, kembalikan orderan ke tab "Belum Diingatkan". */
    public function copot(int $id_invoice): void
    {
        if ($id_invoice <= 0) {
            return;
        }

        $this->where('id_invoice', $id_invoice)->delete();
    }

    /** Semua id invoice yang sudah ditandai — untuk menapis daftar di layar. */
    public function semuaId(): array
    {
        return array_map('intval', array_column($this->select('id_invoice')->findAll(), 'id_invoice'));
    }
}
