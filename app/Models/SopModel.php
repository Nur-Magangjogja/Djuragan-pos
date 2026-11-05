<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pustaka SOP: aturan tertulis yang dibaca CS sebelum mengerjakan tugas.
 *
 * Isinya sengaja satu tabel saja (bukan per halaman). Dokumen yang sudah tidak
 * dipakai bisa sekadar dimatikan lewat `aktif` supaya tidak tampil ke CS, dan
 * Admin tetap punya jalan keluar terakhir lewat hapus permanen.
 */
class SopModel extends Model
{
    public const UNTUK = [
        'semua' => 'Semua peran',
        'cs'    => 'Khusus CS',
        'admin' => 'Khusus Admin',
    ];

    /**
     * Kategori dokumen. Hanya dua hal yang dibedakan CS: aturan kerja (SOP) dan
     * panduan baca. Kuncinya sekalian jadi nama panel di daftar dan di dasbor.
     */
    public const KATEGORI = [
        'SOP'     => 'SOP',
        'PANDUAN' => 'PANDUAN',
    ];

    /**
     * Kategori lama isinya bebas diketik, jadi yang bukan 'SOP' ditampung ke
     * PANDUAN — dokumen lama tidak ada yang hilang dari daftar.
     */
    public static function kategori_satu(?string $kategori): string
    {
        return strtoupper(trim((string) $kategori)) === 'SOP' ? 'SOP' : 'PANDUAN';
    }

    /**
     * Bagi dokumen jadi dua panel siap pakai: ['SOP' => [...], 'PANDUAN' => [...]].
     *
     * Tiap baris ikut dibawakan poinnya hasil `poin()`, supaya panel daftar bisa
     * langsung mencetak judul beserta isinya tanpa menyentuh database lagi.
     *
     * @param list<array<string, mixed>> $baris
     * @return array<string, list<array<string, mixed>>>
     */
    public static function grup(array $baris): array
    {
        $grup = ['SOP' => [], 'PANDUAN' => []];

        foreach ($baris as $s) {
            $s['poin'] = self::poin($s['isi'] ?? null);
            $grup[self::kategori_satu($s['kategori'] ?? '')][] = $s;
        }

        return $grup;
    }

    /** @return list<array<string, mixed>> */
    public function daftar(array $s = []): array
    {
        $b = $this->db->table('crm_sop s')
            ->select('s.*, u.name AS nama_diubah')
            ->join('user u', 'u.id = s.diubah_oleh', 'left');

        if (! empty($s['aktif'])) {
            $b->where('s.aktif', 1);
        }

        if (! empty($s['untuk'])) {
            $b->groupStart()->where('s.untuk', $s['untuk'])->orWhere('s.untuk', 'semua')->groupEnd();
        }

        if (! empty($s['kategori'])) {
            $b->where('s.kategori', $s['kategori']);
        }

        if (! empty($s['cari'])) {
            $c = (string) $s['cari'];
            $b->groupStart()->like('s.judul', $c, 'both')->orLike('s.isi', $c, 'both')->groupEnd();
        }

        return $b->orderBy('s.urut', 'ASC')
            ->orderBy('s.id_sop', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function jumlah(array $s = []): int
    {
        $b = $this->db->table('crm_sop');

        if (! empty($s['aktif'])) {
            $b->where('aktif', 1);
        }

        if (! empty($s['untuk'])) {
            $b->groupStart()->where('untuk', $s['untuk'])->orWhere('untuk', 'semua')->groupEnd();
        }

        return (int) $b->countAllResults();
    }

    public function satu(int $id_sop): ?array
    {
        $baris = $this->db->table('crm_sop s')
            ->select('s.*, u.name AS nama_diubah')
            ->join('user u', 'u.id = s.diubah_oleh', 'left')
            ->where('s.id_sop', $id_sop)
            ->get(1)->getRowArray();

        return $baris ?: null;
    }

    /** @param array<string, mixed> $data */
    public function simpan(array $data, ?int $oleh, int $id_sop = 0): int
    {
        $waktu = time();

        $isi = [
            'judul'      => mb_substr(trim((string) $data['judul']), 0, 200),
            'isi'        => trim((string) ($data['isi'] ?? '')) === '' ? null : mb_substr((string) $data['isi'], 0, 20000),
            'kategori'   => self::kategori_satu((string) ($data['kategori'] ?? 'SOP')),
            'aktif'      => ((int) ($data['aktif'] ?? 1)) === 1 ? 1 : 0,
            'diubah_oleh' => $oleh,
            'updated_at' => $waktu,
        ];

        // 'untuk' dan 'urut' tidak lagi dikirim form. Kalau kuncinya memang tidak
        // ada, nilai lama dibiarkan — dokumen yang tadinya khusus CS/Admin tidak
        // boleh tiba-tiba terbuka untuk semua peran hanya karena isinya disunting
        if (array_key_exists('untuk', $data)) {
            $isi['untuk'] = array_key_exists((string) $data['untuk'], self::UNTUK) ? (string) $data['untuk'] : 'semua';
        }

        if (array_key_exists('urut', $data)) {
            $isi['urut'] = max(0, min(9999, (int) $data['urut']));
        }

        if ($id_sop > 0) {
            $this->db->table('crm_sop')->where('id_sop', $id_sop)->update($isi);

            return $id_sop;
        }

        $this->db->table('crm_sop')->insert($isi + [
            'dibuat_oleh' => $oleh,
            'created_at'  => $waktu,
        ]);

        return (int) $this->db->insertID();
    }

    public function setAktif(int $id_sop, bool $aktif): bool
    {
        $this->db->table('crm_sop')->where('id_sop', $id_sop)->update([
            'aktif' => $aktif ? 1 : 0,
            'updated_at' => time(),
        ]);

        return true;
    }

    /**
     * Hapus permanen satu dokumen. Tidak ada tabel lain yang menyimpan id_sop,
     * jadi riwayat tugas tidak ikut kehilangan apa pun.
     */
    public function hapus(int $id_sop): bool
    {
        $this->db->table('crm_sop')->where('id_sop', $id_sop)->delete();

        return true;
    }

    /**
     * Simpan urutan baru hasil seret-lepas: baris pertama dapat 1, kedua 2, dst.
     *
     * 'diubah_oleh' dan updated_at sengaja tidak disentuh — mengubah urutan bukan
     * mengubah isi, dan halaman dokumen masih harus menunjukkan siapa terakhir
     * menulis. Id yang tidak ada di tabel hanya kena update nol baris.
     *
     * @param list<int|string> $ids
     */
    public function simpanUrut(array $ids): int
    {
        $nomor = 0;

        foreach (array_slice($ids, 0, 200) as $id) {
            $id = (int) $id;

            if ($id <= 0) {
                continue;
            }

            $nomor++;

            $this->db->table('crm_sop')->where('id_sop', $id)->update(['urut' => $nomor]);
        }

        return $nomor;
    }

    /**
     * Isi dokumen apa adanya: satu baris = satu poin.
     *
     * Bentuk lama (kata pembuka + bagian bernomor yang disimpan sebagai JSON di
     * kolom yang sama) diratakan jadi baris biasa, jadi dokumen yang terlanjur
     * ditulis dengan bentuk itu tetap terbaca dan tinggal simpan ulang.
     */
    public static function teks(?string $isi): string
    {
        $teks = trim((string) ($isi ?? ''));

        if ($teks === '' || $teks[0] !== '{') {
            return $teks;
        }

        $d = json_decode($teks, true);

        if (! is_array($d)) {
            return $teks;
        }

        $baris = [];

        if (trim((string) ($d['mukadimah'] ?? '')) !== '') {
            $baris[] = trim((string) $d['mukadimah']);
        }

        foreach ((array) ($d['bagian'] ?? []) as $b) {
            if (! is_array($b)) {
                continue;
            }

            if (trim((string) ($b['judul'] ?? '')) !== '') {
                $baris[] = trim((string) $b['judul']);
            }

            foreach ((array) ($b['poin'] ?? []) as $p) {
                if (trim((string) $p) !== '') {
                    $baris[] = trim((string) $p);
                }
            }
        }

        return implode("\n", $baris);
    }

    /**
     * Poin yang siap dicetak jadi bullet.
     *
     * @return list<string>
     */
    public static function poin(?string $isi): array
    {
        $teks = self::teks($isi);

        if ($teks === '') {
            return [];
        }

        $baris = preg_split('/\r\n|\r|\n/', $teks) ?: [];

        return array_values(array_filter(array_map('trim', $baris), static fn (string $b): bool => $b !== ''));
    }
}
