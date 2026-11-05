<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cek toko berdiri di atas daftar tokonya sendiri.
 *
 * Sampai sekarang "toko" pada checklist selalu berarti satu baris juragan, sehingga
 * lembar pemeriksaan hanya bisa dibuat untuk nama yang juga pemilik orderan. User
 * meminta dua hal yang lebih longgar: toko cek boleh bernama bebas dan boleh dibuat
 * tanpa menyentuh daftar juragan. Tempatnya sudah ada — crm_toko_online (nama,
 * platform, url, catatan) — tinggal dilepaskan dari keharusan punya juragan dan
 * dijadikan sasaran sesi checklist.
 *
 * Semua di sini menambah kelonggaran, tidak ada yang dihapus:
 * 1. crm_checklist_hasil.item_id boleh NULL. Butir yang ditulis CS sendiri di satu
 *    lembar tidak punya baris templat, dan kunci unik (run_id, item_id) tetap aman
 *    karena MySQL tidak pernah menganggap dua NULL sama.
 * 2. crm_toko_online.juragan_id boleh NULL untuk toko yang memang tidak ber-juragan.
 * 3. tiap juragan aktif disemai jadi satu baris toko, supaya 21 toko yang hari ini
 *    bisa dicek tidak hilang dari halaman saat rujukannya dipindah.
 * 4. rujukan sesi dan templat dipindah dari id_juragan ke id_toko. Sesi dikenali dari
 *    kuncinya ('toko-<id>-<tanggal>'), yang menyimpan id lama dan therefore satu-
 *    satunya bukti yang tidak ambigu setelah id_toko dan id_juragan bisa berbentuk sama.
 */
class TokoCekTerpisahJuragan extends Migration
{
    public function up()
    {
        $this->bolehKosong('crm_checklist_hasil', 'item_id');
        $this->bolehKosong('crm_toko_online', 'juragan_id');

        $this->semaikanToko();
        $this->pindahkanRujukan();
    }

    /**
     * Jalan balik seadanya: rujukan dikembalikan ke juragan, kolom tidak dibuat NOT
     * NULL lagi kalau masih ada isinya. Data seminannya dibiarkan — menghapusnya
     * ikut melepas sesi checklist yang sudah dibuat darinya.
     */
    public function down()
    {
        $this->kembalikanRujukan();
    }

    private function semaikanToko(): void
    {
        $sudah = [];

        foreach ($this->db->table('crm_toko_online')->select('juragan_id')->get()->getResultArray() as $t) {
            $sudah[(int) $t['juragan_id']] = true;
        }

        $waktu = time();

        foreach ($this->db->table('juragan')->select('id_juragan, nama_juragan')
            ->where('deleted_at', null)->orderBy('nama_juragan', 'ASC')
            ->get()->getResultArray() as $j) {

            if (isset($sudah[(int) $j['id_juragan']])) {
                continue;
            }

            $this->db->table('crm_toko_online')->insert([
                'juragan_id' => (int) $j['id_juragan'],
                'platform'   => '',
                'nama'       => mb_substr((string) $j['nama_juragan'], 0, 120),
                'aktif'      => 1,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ]);
        }
    }

    /** id_juragan => id_toko, untuk toko hasil semai (satu baris per juragan, platform kosong). */
    private function peta(): array
    {
        $peta = [];

        foreach ($this->db->table('crm_toko_online')->select('id_toko, juragan_id')
            ->where('platform', '')->get()->getResultArray() as $t) {
            $peta[(int) $t['juragan_id']] = (int) $t['id_toko'];
        }

        return $peta;
    }

    private function pindahkanRujukan(): void
    {
        $peta = $this->peta();

        foreach ($this->db->table('crm_checklist_run')->where('jenis', 'toko')
            ->where('toko_id IS NOT NULL', null, false)->get()->getResultArray() as $r) {

            $lama = (int) $r['toko_id'];

            // kunci menyimpan id yang dipakai saat sesi dibuka; kalau isinya masih
            // sama dengan toko_id, baris ini belum pernah dipindah
            if ((int) $this->idDariKunci((string) $r['kunci']) !== $lama || ! isset($peta[$lama])) {
                continue;
            }

            $baru = $peta[$lama];

            $this->db->table('crm_checklist_run')->where('id_run', (int) $r['id_run'])->update([
                'toko_id'    => $baru,
                'kunci'      => 'toko-' . $baru . '-' . $r['tanggal'],
                'updated_at' => time(),
            ]);
        }

        foreach ($this->db->table('crm_checklist_item')->select('id_item, toko_id')
            ->where('jenis', 'toko')->where('toko_id IS NOT NULL', null, false)
            ->get()->getResultArray() as $i) {

            $lama = (int) $i['toko_id'];

            if (! isset($peta[$lama]) || (int) $peta[$lama] === $lama) {
                continue;
            }

            $this->db->table('crm_checklist_item')->where('id_item', (int) $i['id_item'])
                ->update(['toko_id' => $peta[$lama], 'updated_at' => time()]);
        }
    }

    private function kembalikanRujukan(): void
    {
        $peta = $this->peta();
        $balik = array_flip($peta);

        foreach ($this->db->table('crm_checklist_run')->where('jenis', 'toko')
            ->where('toko_id IS NOT NULL', null, false)->get()->getResultArray() as $r) {

            $id_toko = (int) $r['toko_id'];

            if (! isset($balik[$id_toko])) {
                continue;
            }

            $juragan = $balik[$id_toko];

            $this->db->table('crm_checklist_run')->where('id_run', (int) $r['id_run'])->update([
                'toko_id'    => $juragan,
                'kunci'      => 'toko-' . $juragan . '-' . $r['tanggal'],
                'updated_at' => time(),
            ]);
        }

        foreach ($this->db->table('crm_checklist_item')->select('id_item, toko_id')
            ->where('jenis', 'toko')->where('toko_id IS NOT NULL', null, false)
            ->get()->getResultArray() as $i) {

            if (isset($balik[(int) $i['toko_id']])) {
                $this->db->table('crm_checklist_item')->where('id_item', (int) $i['id_item'])
                    ->update(['toko_id' => $balik[(int) $i['toko_id']], 'updated_at' => time()]);
            }
        }
    }

    /** @return int id yang terikat di kunci sesi, 0 kalau bentuknya bukan toko */
    private function idDariKunci(string $kunci): int
    {
        return preg_match('/\Atoko-(\d+)-\d{4}-\d{2}-\d{2}\z/', $kunci, $m) === 1 ? (int) $m[1] : -1;
    }

    /** Melonggarkan satu kolom jadi boleh NULL tanpa mengubah tipenya. */
    private function bolehKosong(string $tabel, string $kolom): void
    {
        $nama   = $this->t($tabel);
        $kolomInfo = $this->db->query(
            'SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$nama, $kolom]
        )->getRowArray();

        if ($kolomInfo === null || strtoupper((string) $kolomInfo['IS_NULLABLE']) === 'YES') {
            return;
        }

        $this->db->query('ALTER TABLE ' . $nama . ' MODIFY ' . $kolom . ' '
            . $kolomInfo['COLUMN_TYPE'] . ' NULL');
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }
}
