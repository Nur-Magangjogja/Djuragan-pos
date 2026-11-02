<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Daftar nomor WhatsApp pengirim milik toko, dan siapa yang boleh memakainya.
 *
 * Hanya Admin/Superadmin yang menulis daftar ini. Nomor dengan `user_id` kosong
 * boleh dipakai semua CS di toko itu; kalau diisi, hanya CS itu yang melihatnya
 * di form Reminder WhatsApp.
 */
class WaNomorModel extends Model
{
    /** @return list<array<string, mixed>> semua nomor (khusus layar Admin) */
    public function semua(array $s = []): array
    {
        $b = $this->db->table('crm_wa_nomor n')
            ->select('n.*, j.nama_juragan, u.name AS nama_dipakai', false)
            ->join('juragan j', 'j.id_juragan = n.juragan_id', 'left')
            ->join('user u', 'u.id = n.user_id', 'left');

        if (! empty($s['juragan_id'])) {
            $b->where('n.juragan_id', (int) $s['juragan_id']);
        }

        if (! empty($s['juragan_ids'])) {
            $b->whereIn('n.juragan_id', array_map('intval', $s['juragan_ids']));
        }

        if (isset($s['aktif']) && $s['aktif'] !== '') {
            $b->where('n.aktif', (int) $s['aktif']);
        }

        return $b->orderBy('j.nama_juragan', 'ASC')->orderBy('n.label', 'ASC')->get()->getResultArray();
    }

    /**
     * Nomor yang boleh dipakai satu akun di satu toko: milik toko itu, aktif,
     * dan tidak sedang disematkan ke orang lain.
     *
     * @return list<array<string, mixed>>
     */
    public function siapDipakai(int $juragan_id, int $user_id): array
    {
        return $this->db->table('crm_wa_nomor')
            ->where('juragan_id', $juragan_id)
            ->where('aktif', 1)
            ->groupStart()->where('user_id', null)->orWhere('user_id', $user_id)->groupEnd()
            ->orderBy('label', 'ASC')
            ->get()->getResultArray();
    }

    public function satu(int $id_nomor): ?array
    {
        $baris = $this->db->table('crm_wa_nomor')->where('id_nomor', $id_nomor)->get(1)->getRowArray();

        return $baris ?: null;
    }

    /**
     * Nomor kembar di toko yang sama ditolak sebelum sempat menabrak unique key.
     * Yang dibandingkan selalu nomor hasil perapian, karena kolom `nomor` juga
     * disimpan seragam: input "0896..." dan "62896..." adalah nomor yang sama.
     */
    public function kembar(int $juragan_id, string $nomor, int $selain = 0): bool
    {
        $b = $this->db->table('crm_wa_nomor')
            ->where('juragan_id', $juragan_id)
            ->where('nomor', self::rapikanNomor($nomor));

        if ($selain > 0) {
            $b->where('id_nomor !=', $selain);
        }

        return (int) $b->countAllResults() > 0;
    }

    /** @param array<string, mixed> $data */
    public function simpan(array $data, ?int $oleh, int $id_nomor = 0): int
    {
        $waktu = time();

        $isi = [
            'juragan_id' => (int) $data['juragan_id'],
            'label'      => trim((string) ($data['label'] ?? '')) === '' ? null : mb_substr(trim((string) $data['label']), 0, 80),
            'nomor'      => self::rapikanNomor((string) $data['nomor']),
            'user_id'    => (int) ($data['user_id'] ?? 0) > 0 ? (int) $data['user_id'] : null,
            'aktif'      => ((int) ($data['aktif'] ?? 1)) === 1 ? 1 : 0,
            'catatan'    => trim((string) ($data['catatan'] ?? '')) === '' ? null : mb_substr(trim((string) $data['catatan']), 0, 255),
            'updated_at' => $waktu,
        ];

        if ($id_nomor > 0) {
            $this->db->table('crm_wa_nomor')->where('id_nomor', $id_nomor)->update($isi);

            return $id_nomor;
        }

        $this->db->table('crm_wa_nomor')->insert($isi + [
            'dibuat_oleh' => $oleh,
            'created_at'  => $waktu,
        ]);

        return (int) $this->db->insertID();
    }

    public function setAktif(int $id_nomor, bool $aktif): bool
    {
        $this->db->table('crm_wa_nomor')->where('id_nomor', $id_nomor)
            ->update(['aktif' => $aktif ? 1 : 0, 'updated_at' => time()]);

        return true;
    }

    /** Nomor disimpan seragam: digit saja, leading 0 → 62 (pola internasional). */
    public static function rapikanNomor(string $nomor): string
    {
        $digit = preg_replace('/\D+/', '', $nomor) ?? '';

        if (str_starts_with($digit, '0')) {
            $digit = '62' . ltrim($digit, '0');
        }

        return mb_substr($digit, 0, 32);
    }

    public static function nomorSah(string $nomor): bool
    {
        $digit = preg_replace('/\D+/', '', $nomor) ?? '';

        return strlen($digit) >= 8 && strlen($digit) <= 16;
    }
}
