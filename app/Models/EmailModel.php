<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Daftar alamat email yang diperiksa CS + siapa yang memegangnya.
 *
 * Isinya sengaja tipis: alamat, label pendek, catatan, dan tautan ke pemegang.
 * Tidak ada sandi, token, atau kredensial apa pun di tabel ini — layar ini
 * menugaskan dan mengingat, bukan membuka mailbox.
 *
 * Seperti model CRM lain di repo ini, tidak ada FOREIGN KEY: integritas dijaga
 * di sini (keberadaan baris dicek sebelum menaut, kunci unik dipakai untuk
 * mencegah dobel) dan penulisan dilakukan lewat query builder eksplisit, bukan
 * save()/insert() model.
 */
class EmailModel extends Model
{
    /** Batas panjang kolom supaya teks panjang tidak menembus kolom database. */
    public const PANJANG_EMAIL = 120;
    public const PANJANG_LABEL = 60;

    /**
     * Alamat sudah pernah didaftarkan? Di-bandingkan dalam bentuk rapi supaya
     * "Admin@Toko.com" dan "admin@toko.com" tidak jadi dua baris.
     *
     * @return array<string, mixed>|null
     */
    public function kembar(string $email, int $kecuali = 0): ?array
    {
        $b = $this->db->table('crm_email')->where('email', self::rapikan($email));

        if ($kecuali > 0) {
            $b->where('id_email !=', $kecuali);
        }

        $baris = $b->get(1)->getRowArray();

        return $baris ?: null;
    }

    /**
     * Daftar email + berapa orang yang memegangnya.
     *
     * @param array<string, mixed> $s cari, aktif ('1'|'0'|''), milik_user (int)
     * @return list<array<string, mixed>>
     */
    public function daftar(array $s, int $limit = 50, int $offset = 0): array
    {
        return $this->bangun($s)
            ->orderBy('e.aktif', 'DESC')
            ->orderBy('e.email', 'ASC')
            ->get($limit, $offset)
            ->getResultArray();
    }

    /** @param array<string, mixed> $s */
    public function jumlah(array $s): int
    {
        return (int) $this->bangun($s)->countAllResults();
    }

    /** @return array<string, mixed>|null */
    public function satu(int $id_email): ?array
    {
        $baris = $this->bangun([])->where('e.id_email', $id_email)->get(1)->getRowArray();

        return $baris ?: null;
    }

    /**
     * Tambah atau ubah satu alamat. Email dinormalkan lebih dulu; kalau hasilnya
     * bukan alamat yang sah, 0 dikembalikan tanpa menulis apa pun.
     *
     * @param array<string, mixed> $data
     */
    public function simpan(array $data, ?int $oleh, int $id_email = 0): int
    {
        $email = self::rapikan((string) ($data['email'] ?? ''));

        if (! self::sah($email)) {
            return 0;
        }

        $waktu = time();
        $isi   = [
            'email'      => mb_substr($email, 0, self::PANJANG_EMAIL),
            'label'      => mb_substr(trim((string) ($data['label'] ?? '')), 0, self::PANJANG_LABEL),
            'catatan'    => $this->isiAtauNull($data['catatan'] ?? null),
            'aktif'      => ((int) ($data['aktif'] ?? 1)) === 1 ? 1 : 0,
            'updated_at' => $waktu,
        ];

        if ($id_email > 0) {
            $this->db->table('crm_email')->where('id_email', $id_email)->update($isi);

            return $id_email;
        }

        $this->db->table('crm_email')->insert($isi + [
            'dibuat_oleh' => $oleh,
            'created_at'  => $waktu,
        ]);

        return (int) $this->db->insertID();
    }

    /** Alamat dinonaktifkan, tidak dihapus: catatan lama tetap menunjuk ke sini. */
    public function setAktif(int $id_email, bool $aktif): bool
    {
        return $this->db->table('crm_email')->where('id_email', $id_email)
            ->update(['aktif' => $aktif ? 1 : 0, 'updated_at' => time()]) !== false;
    }

    /**
     * Siapa yang memegang alamat ini, lengkap dengan nama dan levelnya.
     *
     * @return list<array<string, mixed>>
     */
    public function pegang(int $id_email): array
    {
        return $this->db->table('crm_email_pegang p')
            ->select('p.*, u.name AS nama_staf, u.level, k.name AS nama_dibuat')
            ->join('user u', 'u.id = p.user_id')
            ->join('user k', 'k.id = p.dibuat_oleh', 'left')
            ->where('p.email_id', $id_email)
            ->orderBy('u.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** Tautkan orang ke alamat. Sudah tertaut berarti tidak mengubah apa pun. */
    public function beri(int $id_email, int $user_id, ?int $oleh): int
    {
        $ada = $this->db->table('crm_email_pegang')
            ->where('email_id', $id_email)->where('user_id', $user_id)->get(1)->getRowArray();

        if ($ada !== null) {
            return (int) $ada['id_pegang'];
        }

        $waktu = time();
        $this->db->table('crm_email_pegang')->insert([
            'email_id'    => $id_email,
            'user_id'     => $user_id,
            'dibuat_oleh' => $oleh,
            'created_at'  => $waktu,
            'updated_at'  => $waktu,
        ]);

        return (int) $this->db->insertID();
    }

    public function copot(int $id_email, int $user_id): bool
    {
        $this->db->table('crm_email_pegang')
            ->where('email_id', $id_email)->where('user_id', $user_id)->delete();

        return true;
    }

    /** Alamat aktif yang dipegang satu akun — dipakai layar Cek Email. */
    public function milikUser(int $user_id): array
    {
        return $this->db->table('crm_email_pegang p')
            ->select('e.id_email, e.email, e.label')
            ->join('crm_email e', 'e.id_email = p.email_id')
            ->where('p.user_id', $user_id)
            ->where('e.aktif', 1)
            ->orderBy('e.email', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function pegangPunya(int $id_email, int $user_id): bool
    {
        return (int) $this->db->table('crm_email_pegang')
            ->where('email_id', $id_email)->where('user_id', $user_id)->countAllResults() > 0;
    }

    /** Rapi: buang spasi di ujung dan kecilkan huruf — perbandingan kunci pakai ini. */
    public static function rapikan(string $email): string
    {
        return strtolower(trim(preg_replace('/\s+/', '', $email) ?? ''));
    }

    public static function sah(string $email): bool
    {
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && strlen($email) <= self::PANJANG_EMAIL;
    }

    /**
     * @param array<string, mixed> $s
     */
    private function bangun(array $s)
    {
        $pegang = $this->db->prefixTable('crm_email_pegang');

        $b = $this->db->table('crm_email e')
            ->select('e.*, u.name AS nama_dibuat,'
                . ' (SELECT COUNT(*) FROM `' . $pegang . '` p WHERE p.email_id = e.id_email) AS jumlah_pegang', false)
            ->join('user u', 'u.id = e.dibuat_oleh', 'left');

        if (! empty($s['cari'])) {
            $cari = (string) $s['cari'];
            $b->groupStart()->like('e.email', $cari)->orLike('e.label', $cari)->orLike('e.catatan', $cari)->groupEnd();
        }

        if (isset($s['aktif']) && $s['aktif'] !== '') {
            $b->where('e.aktif', (int) $s['aktif']);
        }

        if (! empty($s['milik_user'])) {
            $b->where('e.aktif', 1)->where('EXISTS (SELECT 1 FROM `' . $pegang . '` p2 WHERE p2.email_id = e.id_email AND p2.user_id = ' . (int) $s['milik_user'] . ')', null, false);
        }

        return $b;
    }

    /** @return string|null */
    private function isiAtauNull($nilai)
    {
        $teks = trim((string) ($nilai ?? ''));

        return $teks === '' ? null : mb_substr($teks, 0, 2000);
    }
}
