<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Templat tugas: resep pekerjaan berulang yang bisa dituang ke antrean sekali klik.
 *
 * Satu templat punya beberapa item. Setiap item yang dituang menyimpan kunci
 * `tpl-<templat>-<item>-<toko>-<tanggal>`, jadi menekan tombol "Pakai" dua kali
 * pada hari yang sama tidak menghasilkan tugas ganda — yang kedua hanya
 * melaporkan berapa tugas yang sudah ada.
 */
class TugasTemplateModel extends Model
{
    /** @return list<array<string, mixed>> */
    public function daftar(array $s = []): array
    {
        $b = $this->db->table('crm_tugas_template t')
            ->select('t.*, (SELECT COUNT(*) FROM `' . $this->p('crm_tugas_template_item') . '` i WHERE i.template_id = t.id_template) AS jumlah_item');

        if (! empty($s['aktif'])) {
            $b->where('t.aktif', 1);
        }

        if (! empty($s['cari'])) {
            $b->like('t.nama', (string) $s['cari']);
        }

        return $b->orderBy('t.aktif', 'DESC')->orderBy('t.id_template', 'DESC')->get()->getResultArray();
    }

    public function satu(int $id_template): ?array
    {
        $baris = $this->db->table('crm_tugas_template')->where('id_template', $id_template)->get(1)->getRowArray();

        return $baris ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function item(int $id_template): array
    {
        return $this->db->table('crm_tugas_template_item')
            ->where('template_id', $id_template)
            ->orderBy('urut', 'ASC')
            ->orderBy('id_item', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Simpan header + daftarkan ulang itemnya.
     *
     * Item lama dibuang lalu ditulis lagi: templat memang hanya dipakai membuat
     * tugas baru, dan tugas yang sudah terlanjur dibuat menyimpan judulnya sendiri
     * di `crm_tugas`, jadi riwayat tidak ikut berubah.
     *
     * @param array<string, mixed>  $data
     * @param list<array<string, mixed>> $items
     */
    public function simpan(array $data, array $items, ?int $oleh, int $id_template = 0): int
    {
        $waktu = time();

        $header = [
            'nama'        => mb_substr(trim((string) $data['nama']), 0, 120),
            'keterangan'  => trim((string) ($data['keterangan'] ?? '')) === '' ? null : mb_substr(trim((string) $data['keterangan']), 0, 255),
            'aktif'       => ((int) ($data['aktif'] ?? 1)) === 1 ? 1 : 0,
            'updated_at'  => $waktu,
        ];

        if ($id_template > 0) {
            $this->db->table('crm_tugas_template')->where('id_template', $id_template)->update($header);
        } else {
            $this->db->table('crm_tugas_template')->insert($header + [
                'dibuat_oleh' => $oleh,
                'created_at'  => $waktu,
            ]);

            $id_template = (int) $this->db->insertID();
        }

        $this->db->table('crm_tugas_template_item')->where('template_id', $id_template)->delete();

        foreach ($this->bersihkanItem($items) as $i => $item) {
            $this->db->table('crm_tugas_template_item')->insert($item + [
                'template_id' => $id_template,
                'urut'        => $i,
                'created_at'  => $waktu,
                'updated_at'  => $waktu,
            ]);
        }

        return $id_template;
    }

    public function setAktif(int $id_template, bool $aktif): bool
    {
        $this->db->table('crm_tugas_template')->where('id_template', $id_template)
            ->update(['aktif' => $aktif ? 1 : 0, 'updated_at' => time()]);

        return true;
    }

    /**
     * Tuangkan templat ke antrean untuk satu toko pada satu tanggal dasar.
     *
     * @return array{dibuat: int, sudah: int, id: list<int>}
     */
    public function pakai(int $id_template, ?int $toko_id, string $tanggal_dasar, int $oleh): array
    {
        $templat = $this->satu($id_template);
        $hasil   = ['dibuat' => 0, 'sudah' => 0, 'id' => []];

        if ($templat === null || (int) $templat['aktif'] !== 1) {
            return $hasil;
        }

        $model = new TugasModel();

        foreach ($this->item($id_template) as $item) {
            $tenggat = date('Y-m-d', strtotime($tanggal_dasar . ' +' . (int) $item['tenggat_hari'] . ' days'));

            $tugas = $model->buatUnik([
                'judul'       => (string) $item['judul'],
                'deskripsi'   => (string) ($item['deskripsi'] ?? ''),
                'jenis'       => $this->jenisSah((string) $item['jenis']),
                'prioritas'   => (string) $item['prioritas'],
                'sumber'      => 'sistem',
                'kunci_unik'  => 'tpl-' . $id_template . '-' . (int) $item['id_item'] . '-' . ($toko_id ?: 0) . '-' . $tanggal_dasar,
                'juragan_id'  => $toko_id,
                'tenggat'     => $tenggat,
            ], $oleh);

            $hasil['id'][] = (int) $tugas['id'];
            $tugas['baru'] ? $hasil['dibuat']++ : $hasil['sudah']++;
        }

        return $hasil;
    }

    /** Buang item kosong dan rapikan nilainya sebelum disimpan. */
    private function bersihkanItem(array $items): array
    {
        $bersih = [];

        foreach ($items as $item) {
            $judul = trim((string) ($item['judul'] ?? ''));

            if ($judul === '') {
                continue;
            }

            $bersih[] = [
                'judul'        => mb_substr($judul, 0, 200),
                'deskripsi'    => trim((string) ($item['deskripsi'] ?? '')) === '' ? null : mb_substr((string) $item['deskripsi'], 0, 2000),
                'jenis'        => $this->jenisSah((string) ($item['jenis'] ?? 'lain')),
                'prioritas'    => array_key_exists((string) ($item['prioritas'] ?? 'normal'), TugasModel::PRIORITAS) ? (string) $item['prioritas'] : 'normal',
                'tenggat_hari' => max(0, min(365, (int) ($item['tenggat_hari'] ?? 1))),
            ];
        }

        return $bersih;
    }

    /**
     * Jenis yang boleh dituangkan templat: hanya yang punya layar daftar umum.
     * Jenis milik submenu (catatan WhatsApp, reminder transaksi, checklist toko,
     * pemeriksaan email) lahir dari layar masing-masing beserta data sampingannya,
     * jadi kalau templat membuatnya tugas itu tidak tampil di layar mana pun.
     */
    private function jenisSah(string $jenis): string
    {
        return array_key_exists($jenis, TugasModel::jenisLain()) ? $jenis : 'lain';
    }

    private function p(string $tabel): string
    {
        return $this->db->prefixTable($tabel);
    }
}
