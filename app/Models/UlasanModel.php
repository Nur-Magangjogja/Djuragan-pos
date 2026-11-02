<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Catatan ulasan pelanggan — bukan bagian dari mesin tugas.
 *
 * Tugas 'minta ulasan' boleh selesai tanpa ulasan apa pun; yang dicatat di sini
 * hanyalah fakta dari sisi pelanggan: sudah diminta, dan apakah ulasannya benar-benar
 * masuk. Bintang boleh kosong supaya "diminta tapi belum dibalas" tetap tercatat
 * sebagai keadaan normal, bukan sebagai data rusak.
 */
class UlasanModel extends Model
{
    public const STATUS = [
        'diminta'  => 'Sudah diminta',
        'diterima' => 'Ulasan diterima',
    ];

    public function satu(int $invoice_id, string $platform = ''): ?array
    {
        $baris = $this->db->table('crm_ulasan')
            ->where('invoice_id', $invoice_id)
            ->where('platform', $platform)
            ->get(1)
            ->getRowArray();

        return $baris ?: null;
    }

    public function untukTugas(int $tugas_id): ?array
    {
        $baris = $this->db->table('crm_ulasan')->where('tugas_id', $tugas_id)->get(1)->getRowArray();

        return $baris ?: null;
    }

    /**
     * Status ulasan per nota untuk daftar: satu query, bukan satu per baris.
     * Ulasan yang sudah diterima menang atas baris 'diminta' — catatan diterima
     * bisa ber-platform berbeda, sedangkan 'diminta' selalu tanpa platform.
     *
     * @return array<int, array<string, mixed>> invoice_id => baris ulasan
     */
    public function untukInvoiceList(array $invoice_ids): array
    {
        if ($invoice_ids === []) {
            return [];
        }

        $peta = [];

        foreach ($this->db->table('crm_ulasan')->whereIn('invoice_id', array_map('intval', $invoice_ids))
            ->get()->getResultArray() as $u) {
            $id = (int) $u['invoice_id'];

            if (! isset($peta[$id]) || (string) $u['status'] === 'diterima') {
                $peta[$id] = $u;
            }
        }

        return $peta;
    }

    /** Catat bahwa CS sudah meminta ulasan. Barisnya diperbarui kalau sudah pernah ada. */
    public function minta(int $invoice_id, ?int $tugas_id, ?int $oleh, string $platform = ''): int
    {
        $waktu = time();
        $lama  = $this->satu($invoice_id, $platform);

        if ($lama !== null) {
            $ubah = ['updated_at' => $waktu];

            if ($tugas_id !== null) {
                $ubah['tugas_id'] = $tugas_id;
            }

            // ulasan yang sudah diterima tidak pernah dibalik jadi 'diminta'
            if ((string) $lama['status'] === 'diminta' && $oleh !== null) {
                $ubah['diminta_oleh'] = $oleh;
            }

            $this->db->table('crm_ulasan')->where('id_ulasan', (int) $lama['id_ulasan'])->update($ubah);

            return (int) $lama['id_ulasan'];
        }

        $this->db->table('crm_ulasan')->insert([
            'invoice_id'   => $invoice_id,
            'tugas_id'     => $tugas_id,
            'platform'     => mb_substr($platform, 0, 40),
            'status'       => 'diminta',
            'diminta_oleh' => $oleh,
            'created_at'   => $waktu,
            'updated_at'   => $waktu,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * Isi ulasan yang benar-benar diberikan pelanggan. Ini satu-satunya jalur yang
     * boleh mengubah status jadi 'diterima', dan bintangnya wajib 1..5 — tidak
     * pernah diisi otomatis saat tugas CS selesai.
     */
    public function terima(int $invoice_id, int $bintang, string $komentar, ?int $oleh, string $platform = ''): int
    {
        $waktu = time();
        $lama  = $this->satu($invoice_id, $platform);

        $isi = [
            'status'        => 'diterima',
            'bintang'       => $bintang,
            'komentar'      => trim($komentar) === '' ? null : $komentar,
            'diterima_oleh' => $oleh,
            'updated_at'    => $waktu,
        ];

        if ($lama !== null) {
            $this->db->table('crm_ulasan')->where('id_ulasan', (int) $lama['id_ulasan'])->update($isi);

            return (int) $lama['id_ulasan'];
        }

        $this->db->table('crm_ulasan')->insert($isi + [
            'invoice_id' => $invoice_id,
            'platform'   => mb_substr($platform, 0, 40),
            'created_at' => $waktu,
        ]);

        return (int) $this->db->insertID();
    }

    /** Bintang yang sah: 1..5. Selain itu dianggap belum ada ulasan. */
    public static function bintangSah(mixed $nilai): ?int
    {
        $angka = (int) $nilai;

        return $angka >= 1 && $angka <= 5 ? $angka : null;
    }
}
