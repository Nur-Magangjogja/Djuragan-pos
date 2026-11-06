<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;

/**
 * Memperbaiki crm_pelanggan / crm_pelanggan_duplikat yang rusak menjadi tabel
 * kosong.
 *
 * Keduanya seharusnya VIEW virtual di atas order_pelanggan + agregat invoice,
 * jadi pelanggan dan orderan baru langsung terlihat di CRM. Pada sebagian
 * lingkungan (pulihkan dari dump), view ini berakhir sebagai BASE TABLE kosong:
 * layar Data Pelanggan jadi tidak pernah menampilkan siapa pun — termasuk
 * pelanggan baru yang barusaja dibuat lewat transaksi.
 *
 * Migrasi ini menjatuhkan objek lama (tabel kosong ATAU view basi, keduanya
 * tidak menyimpan data asli) lalu membangun kembali sebagai view, baik nama
 * tanpa prefix (dipakai kueri mentah dasbor) maupun alias berprefix (dipakai
 * query builder lewat $db->table()). Definisi SQL-nya identik dengan
 * ViewCrmPelanggan agar angkanya tidak pernah berubah makna.
 */
class RepairCrmPelangganViews extends Migration
{
    public function up()
    {
        $prefix = $this->prefix();

        // 1. jatuhkan objek lama apa pun bentuknya (TABLE kosong atau VIEW basi)
        $objek = ['crm_pelanggan', 'crm_pelanggan_duplikat'];
        if ($prefix !== '') {
            $objek[] = $prefix . 'crm_pelanggan';
            $objek[] = $prefix . 'crm_pelanggan_duplikat';
        }
        foreach ($objek as $obj) {
            $this->jatuhkanObjek($obj);
        }

        // 2. view basis tanpa prefix: inilah yang dibaca kueri mentah dasbor
        $this->db->query($this->sqlPelanggan());
        $this->db->query('CREATE OR REPLACE VIEW crm_pelanggan_duplikat AS' . "\n" . $this->sqlDuplikat());

        // 3. alias berprefix: inilah yang dibaca $db->table('crm_pelanggan')
        if ($prefix !== '') {
            $this->db->query("CREATE OR REPLACE VIEW `{$prefix}crm_pelanggan` AS SELECT * FROM `crm_pelanggan`");
            $this->db->query("CREATE OR REPLACE VIEW `{$prefix}crm_pelanggan_duplikat` AS SELECT * FROM `crm_pelanggan_duplikat`");
        }
    }

    public function down()
    {
        // tidak ada tabel basis yang perlu dikembalikan: view ini memang virtual
        $prefix = $this->prefix();

        foreach (['crm_pelanggan', 'crm_pelanggan_duplikat'] as $obj) {
            $this->db->query("DROP VIEW IF EXISTS `{$obj}`");
            if ($prefix !== '') {
                $this->db->query("DROP VIEW IF EXISTS `{$prefix}{$obj}`");
            }
        }
    }

    /** DROP sesuai tipe nyata; MySQL menolak DROP TABLE pada view (dan sebaliknya). */
    private function jatuhkanObjek(string $obj): void
    {
        $row = $this->db->query(
            'SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$obj]
        )->getRowArray();

        if ($row === null) {
            return;
        }

        if (strcasecmp((string) $row['TABLE_TYPE'], 'VIEW') === 0) {
            $this->db->query("DROP VIEW IF EXISTS `{$obj}`");
        } else {
            $this->db->query("DROP TABLE IF EXISTS `{$obj}`");
        }
    }

    // ---- definisi identik dengan ViewCrmPelanggan (2026-09-28) ----

    private function sqlPelanggan(): string
    {
        $l = [];
        $l[] = 'CREATE OR REPLACE VIEW crm_pelanggan AS';
        $l[] = 'SELECT p.id_pelanggan, p.nama_pelanggan, p.cod, p.alamat, p.kodepos, p.created_at AS terdaftar_sejak,';
        $l[] = '       REGEXP_REPLACE(TRIM(LOWER(p.nama_pelanggan)), "[[:space:]]+", " ") AS nama_kunci,';
        $l[] = '       pv.province_name AS provinsi, ct.city_name AS kota, sd.subdistrict_name AS kecamatan,';
        $l[] = '       kon.nomor_utama, kon.jumlah_nomor,';
        $l[] = '       COALESCE(agg.jumlah_order, 0) AS jumlah_order, COALESCE(agg.qty, 0) AS qty,';
        $l[] = '       COALESCE(agg.nilai, 0) AS nilai, COALESCE(agg.dibayar, 0) AS dibayar,';
        $l[] = '       COALESCE(agg.nilai, 0) - COALESCE(agg.dibayar, 0) AS sisa,';
        $l[] = '       agg.order_pertama, agg.order_terakhir';
        $l[] = 'FROM `' . $this->t('pelanggan') . '` p';
        $l[] = 'LEFT JOIN `' . $this->t('provinces') . '` pv ON pv.province_id = p.provinsi';
        $l[] = 'LEFT JOIN `' . $this->t('cities') . '` ct ON ct.city_id = p.kabupaten';
        $l[] = 'LEFT JOIN `' . $this->t('subdistricts') . '` sd ON sd.subdistrict_id = p.kecamatan';
        $l[] = 'LEFT JOIN (SELECT pelanggan_id, MIN(nomor_pure) AS nomor_utama,';
        $l[] = '                  COUNT(DISTINCT nomor_pure) AS jumlah_nomor';
        $l[] = '           FROM `' . $this->t('pelanggan_kontak') . '` GROUP BY pelanggan_id) kon';
        $l[] = '       ON kon.pelanggan_id = p.id_pelanggan';
        $l[] = 'LEFT JOIN (SELECT o.pemesan_id AS id_pelanggan, COUNT(*) AS jumlah_order,';
        $l[] = '                  SUM(COALESCE(b.qty, 0)) AS qty,';
        $l[] = '                  SUM(COALESCE(b.barang, 0) + COALESCE(bc.biaya, 0)) AS nilai,';
        $l[] = '                  SUM(COALESCE(pm.dibayar, 0)) AS dibayar,';
        $l[] = '                  MIN(o.tanggal_pesan) AS order_pertama, MAX(o.tanggal_pesan) AS order_terakhir';
        $l[] = '           FROM `' . $this->t('invoice') . '` o';
        $l[] = '           LEFT JOIN (SELECT invoice_id, SUM(qty * harga) AS barang, SUM(qty) AS qty';
        $l[] = '                    FROM `' . $this->t('dibeli') . '` GROUP BY invoice_id) b';
        $l[] = '                ON b.invoice_id = o.id_invoice';
        $l[] = '           LEFT JOIN (SELECT invoice_id, SUM(nominal) AS biaya';
        $l[] = '                    FROM `' . $this->t('biaya') . '` GROUP BY invoice_id) bc';
        $l[] = '                ON bc.invoice_id = o.id_invoice';
        $l[] = '           LEFT JOIN (SELECT invoice_id, SUM(total_pembayaran) AS dibayar';
        $l[] = '                    FROM `' . $this->t('pembayaran') . '` GROUP BY invoice_id) pm';
        $l[] = '                ON pm.invoice_id = o.id_invoice';
        $l[] = '           WHERE o.deleted_at IS NULL GROUP BY o.pemesan_id) agg';
        $l[] = '       ON agg.id_pelanggan = p.id_pelanggan';
        $l[] = 'WHERE p.deleted_at IS NULL';

        return implode("\n", $l);
    }

    private function sqlDuplikat(): string
    {
        $kunciNama = 'REGEXP_REPLACE(TRIM(LOWER(p.nama_pelanggan)), "[[:space:]]+", " ")';
        $l         = [];
        $l[] = 'SELECT "nomor" AS jenis, k.nomor_pure AS kunci,';
        $l[] = '       COUNT(DISTINCT k.pelanggan_id) AS jumlah_akun, MIN(p.nama_pelanggan) AS contoh_nama,';
        $l[] = '       GROUP_CONCAT(DISTINCT k.pelanggan_id ORDER BY k.pelanggan_id SEPARATOR ",") AS id_akun';
        $l[] = 'FROM `' . $this->t('pelanggan_kontak') . '` k';
        $l[] = 'JOIN `' . $this->t('pelanggan') . '` p ON p.id_pelanggan = k.pelanggan_id';
        $l[] = 'WHERE p.deleted_at IS NULL AND CHAR_LENGTH(k.nomor_pure) >= 9';
        $l[] = 'GROUP BY k.nomor_pure';
        $l[] = 'HAVING COUNT(DISTINCT k.pelanggan_id) > 1';
        $l[] = 'UNION ALL';
        $l[] = 'SELECT "nama" AS jenis, ' . $kunciNama . ' AS kunci,';
        $l[] = '       COUNT(*) AS jumlah_akun, MIN(p.nama_pelanggan) AS contoh_nama,';
        $l[] = '       GROUP_CONCAT(p.id_pelanggan ORDER BY p.id_pelanggan SEPARATOR ",") AS id_akun';
        $l[] = 'FROM `' . $this->t('pelanggan') . '` p';
        $l[] = 'WHERE p.deleted_at IS NULL';
        $l[] = 'GROUP BY ' . $kunciNama;
        $l[] = 'HAVING COUNT(*) > 1';

        return implode("\n", $l);
    }

    /**
     * Prefix DB. $this->db hanya dideklarasikan sebagai ConnectionInterface di
     * kelas induk, padahal getPrefix() ada di BaseConnection yang benar-benar
     * dipakai saat runtime, jadi pemeriksa statis butuh penyempitan tipe ini.
     */
    private function prefix(): string
    {
        $db = $this->db;

        return $db instanceof BaseConnection ? $db->getPrefix() : '';
    }

    private function t(string $nama): string
    {
        $db = $this->db;

        return $db instanceof BaseConnection ? $db->prefixTable($nama) : $nama;
    }
}
