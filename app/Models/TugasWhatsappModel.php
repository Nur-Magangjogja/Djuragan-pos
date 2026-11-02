<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Reminder WhatsApp: percakapan pelanggan yang perlu ditindaklanjuti CS.
 *
 * Status, pengambilan, pelepasan, dan riwayat tetap milik `crm_tugas` (satu mesin
 * antrean untuk semua kategori). Model ini hanya mengurus bagian yang khusus WA —
 * nomor pengirim, nomor pelanggan, dan jenis tindak lanjutnya — dan memastikan
 * keduanya ditulis dalam satu transaksi supaya tidak ada tugas tanpa data WA.
 */
class TugasWhatsappModel extends Model
{
    public const TINDAK = [
        'perlu_dibalas'    => 'Perlu Dibalas',
        'perlu_follow_up'  => 'Perlu Follow Up',
        'menunggu_balasan' => 'Menunggu Balasan Pelanggan',
        'perlu_konfirmasi' => 'Perlu Konfirmasi',
        'lainnya'          => 'Tindak Lanjut Lainnya',
    ];

    /**
     * Tab layar. nilainya dipakai sebagai filter, labelnya dipakai di layar.
     *
     * 'tak_selesai' hanya dibuka Admin/Superadmin: isinya alasan orang melepas
     * tugas yang sudah ada di tangannya, jadi itu bahan pemantauan, bukan bahan
     * yang diungkit antar-CS.
     */
    public const TAB = [
        'belum'       => 'Belum Diambil',
        'proses'      => 'Sedang Diproses',
        'selesai'     => 'Sudah Diselesaikan',
        'tak_selesai' => 'Tidak Jadi Dikerjakan',
    ];

    /** Kejadian yang berarti "sudah di tangan, tapi tidak sampai selesai". */
    public const JEJAK_TAK_SELESAI = ['operan_manual', 'operan_otomatis', 'dibatalkan'];

    /**
     * Simpan tugas WA baru (antrean + baris khusus WA) dalam satu transaksi.
     *
     * @param array<string, mixed> $data
     * @return array{id: int, judul: string}
     */
    public function buat(array $data, int $oleh): array
    {
        $db = \Config\Database::connect();

        $toko     = (int) ($data['juragan_id'] ?? 0);
        $tindak   = array_key_exists((string) ($data['tindak_lanjut'] ?? ''), self::TINDAK)
            ? (string) $data['tindak_lanjut'] : 'perlu_dibalas';
        $nomor_ke = WaNomorModel::rapikanNomor((string) ($data['nomor_ke'] ?? ''));
        $catatan  = trim((string) ($data['catatan'] ?? ''));
        $pelanggan = (int) ($data['pelanggan_id'] ?? 0) > 0 ? (int) $data['pelanggan_id'] : null;

        $nomor = $data['nomor_id'] !== null && (int) $data['nomor_id'] > 0
            ? (new WaNomorModel())->satu((int) $data['nomor_id']) : null;

        // snapshot teks pengirim: id boleh meleset ke lain hari, teks yang dipakai
        // saat itu tidak boleh ikut berubah
        $dari = $nomor !== null ? (string) $nomor['nomor'] : null;

        $nama    = $pelanggan !== null
            ? (string) ($db->table('pelanggan')->select('nama_pelanggan')->where('id_pelanggan', $pelanggan)->get(1)->getRowArray()['nama_pelanggan'] ?? '')
            : '';
        $sasaran = $nama !== '' ? $nama : ($nomor_ke !== '' ? $nomor_ke : 'pelanggan');
        $judul   = mb_substr('WA ' . self::TINDAK[$tindak] . ': ' . $sasaran, 0, 200);

        $db->transBegin();

        $id_tugas = (new TugasModel())->buat([
            'judul'        => $judul,
            'deskripsi'    => $catatan === '' ? null : mb_substr($catatan, 0, 2000),
            'jenis'        => 'catatan_wa',
            'prioritas'    => (string) ($data['prioritas'] ?? 'normal'),
            'sumber'       => 'manual',
            'juragan_id'   => $toko,
            'pelanggan_id' => $pelanggan,
            'tenggat'      => (string) ($data['tenggat'] ?? ''),
        ], $oleh);

        $db->table('crm_tugas_whatsapp')->insert([
            'tugas_id'       => $id_tugas,
            'nomor_id'       => $nomor !== null ? (int) $nomor['id_nomor'] : null,
            'nomor_dari'     => $dari,
            'nomor_ke'       => $nomor_ke === '' ? null : $nomor_ke,
            'tindak_lanjut'  => $tindak,
            'created_at'     => time(),
            'updated_at'     => time(),
        ]);

        // timeline pelanggan tetap terisi: layar ini adalah tempat CS mencatat
        // percakapan, jadi jejaknya tidak boleh berhenti di daftar tugas saja
        if ($pelanggan !== null) {
            (new CrmModel())->tambahAktivitas([
                'pelanggan_id' => $pelanggan,
                'tipe'         => 'wa',
                'judul'        => 'Catatan percakapan: ' . mb_substr($catatan, 0, 80),
                'deskripsi'    => mb_substr($catatan, 0, 2000),
                'user_id'      => $oleh,
            ]);
        }

        if ($db->transStatus() === false) {
            $galat = $db->error();

            $db->transRollback();

            throw new \RuntimeException('Reminder WhatsApp gagal disimpan: ' . ($galat['message'] ?? 'tanpa pesan error'));
        }

        $db->transCommit();

        return ['id' => (int) $id_tugas, 'judul' => $judul];
    }

    public function side(int $tugas_id): ?array
    {
        $baris = $this->db->table('crm_tugas_whatsapp')->where('tugas_id', $tugas_id)->get(1)->getRowArray();

        return $baris ?: null;
    }

    /** Satu tugas WA lengkap (tugas + bagian WA + nama semua orang yang terlibat). */
    public function satu(int $tugas_id): ?array
    {
        $baris = $this->bangun([])->where('t.id_tugas', $tugas_id)->get(1)->getRowArray();

        return $baris ?: null;
    }

    /** Ubah data WA milik tugas yang sudah ada (Admin/pembuat, selama belum selesai). */
    public function ubah(int $tugas_id, array $data): void
    {
        $ada = $this->side($tugas_id);

        if ($ada === null) {
            return;
        }

        $nomor = (int) ($data['nomor_id'] ?? 0) > 0 ? (new WaNomorModel())->satu((int) $data['nomor_id']) : null;

        $this->db->table('crm_tugas_whatsapp')->where('tugas_id', $tugas_id)->update([
            'nomor_id'      => $nomor !== null ? (int) $nomor['id_nomor'] : null,
            'nomor_dari'    => $nomor !== null ? (string) $nomor['nomor'] : $ada['nomor_dari'],
            'nomor_ke'      => WaNomorModel::rapikanNomor((string) ($data['nomor_ke'] ?? '')) ?: null,
            'tindak_lanjut' => array_key_exists((string) ($data['tindak_lanjut'] ?? ''), self::TINDAK)
                ? (string) $data['tindak_lanjut'] : $ada['tindak_lanjut'],
            'updated_at'    => time(),
        ]);
    }

    /** @param array<string, mixed> $s */
    public function daftar(array $s, int $limit = 25, int $offset = 0): array
    {
        return $this->bangun($s)->orderBy('w.id_whatsapp', 'DESC')->get($limit, $offset)->getResultArray();
    }

    /** @param array<string, mixed> $s */
    public function jumlah(array $s): int
    {
        return (int) $this->bangun($s)->countAllResults();
    }

    /**
     * Tab "Tidak Jadi Dikerjakan": satu baris per momen seorang CS melepas tugas WA
     * yang sudah ada di tangannya, atau Admin membatalkannya.
     *
     * Yang dibaca adalah riwayat append-only (`crm_tugas_event`), bukan kondisi tugas
     * hari ini. Alasannya: satu reminder bisa berpindah tangan tiga kali, dan Admin
     * yang ingin tahu "kenapa tidak selesai" harus membaca ketiga alasan itu beserta
     * orangnya — sementara baris `crm_tugas` hanya pernah menyimpan penggarap
     * terakhir, itu pun dikosongkan lagi saat tugas dioperkan.
     *
     * @param array<string, mixed> $s
     * @return list<array<string, mixed>>
     */
    public function takSelesai(array $s, int $limit = 25, int $offset = 0): array
    {
        // daftar kolom ditulis mentah (select false): sama seperti di bangun(),
        // kolomnya berkualifikasi alias dan CI4 akan menempelkan DBPrefix ke
        // alias tersebut kalau ikut di-escape
        return $this->bangunJejak($s)
            ->select('e.id_event, e.tugas_id, e.jenis, e.status_lama, e.status_baru, e.alasan,'
                . ' e.catatan AS catatan_lepas, e.created_at AS dilepas_at,'
                . ' t.judul, t.deskripsi, t.status, t.prioritas, t.tenggat, t.juragan_id, t.pelanggan_id,'
                . ' t.dibuat_oleh, t.dikerjakan_oleh, t.selesai_oleh, t.selesai_at, t.alasan_batal,'
                . ' w.tindak_lanjut, w.nomor_ke, w.nomor_dari,'
                . ' p.nama_pelanggan, j.nama_juragan, du.name AS nama_dibuat, su.name AS nama_selesai, '
                . self::lepas('t.dikerjakan_oleh', 'COALESCE(e.user_id, e.dari_user_id, t.dikerjakan_oleh)') . ' AS id_lepas, '
                . self::lepas('ku.name', 'COALESCE(au.name, ad.name, ku.name)') . ' AS nama_lepas, '
                . self::lepas('ku.level', 'COALESCE(au.level, ad.level, ku.level)') . ' AS level_lepas', false)
            ->orderBy('e.id_event', 'DESC')
            ->get($limit, $offset)
            ->getResultArray();
    }

    /** @param array<string, mixed> $s */
    public function takSelesaiJumlah(array $s): int
    {
        return (int) $this->bangunJejak($s)->countAllResults();
    }

    /**
     * Ekspresi SQL "orang yang mengambil tugas ini tapi tidak menyelesaikannya".
     * Penggarap pada baris tugas tidak bisa dipercaya: kolomnya dikosongkan saat
     * tugas dioper dan sudah berganti orang kalau tugasnya diambil lagi. Jadi
     * dibaca dari kejadiannya — pelepasan manual menulis user_id, operan otomatis
     * hanya dari_user_id, sedangkan pembatalan meninggalkan penggarap lamanya di
     * baris tugas (karena itu kolomnya dipilih per jenis kejadian).
     *
     * @param string $saatBatal kolom penggarap lama, dipakai saat tugas dibatalkan
     * @param string $saatOper  kandidat saat tugas dioperkan
     */
    private static function lepas(string $saatBatal, string $saatOper): string
    {
        return "CASE WHEN e.jenis = 'dibatalkan' AND t.dikerjakan_oleh IS NOT NULL"
            . " THEN {$saatBatal} ELSE {$saatOper} END";
    }

    /**
     * Rekap per orang pada saringan yang sama, supaya Admin melihat polanya dulu
     * sebelum membaca satu-satu. Nama dibungkus MAX() karena hanya kolom yang
     * dikelompokkan yang boleh muncul telanjang di SELECT.
     *
     * @param array<string, mixed> $s
     * @return list<array<string, mixed>>
     */
    public function takSelesaiPerOrang(array $s): array
    {
        $siapa   = self::lepas('t.dikerjakan_oleh', 'COALESCE(e.user_id, e.dari_user_id, t.dikerjakan_oleh)');
        $namanya = self::lepas('ku.name', 'COALESCE(au.name, ad.name, ku.name)');

        return $this->bangunJejak($s)
            ->select($siapa . ' AS pemegang, MAX(' . $namanya . ') AS nama, COUNT(*) AS n', false)
            ->groupBy($siapa, false)
            ->orderBy('n', 'DESC')
            ->get(12)
            ->getResultArray();
    }

    /**
     * Dasar kedua metode di atas: riwayat pelepasan/pembatalan yang sudah dibatasi
     * ke tugas WhatsApp dan ke orang yang benar-benar pernah memegangnya.
     *
     * `status_lama` ikut disaring karena ketiga jenis kejadian itu bisa saja lahir
     * dari tugas yang masih di antrean (Admin membatalkan reminder yang belum
     * diambil siapa pun) — dan itu bukan cerita tab ini.
     *
     * @param array<string, mixed> $s
     */
    private function bangunJejak(array $s)
    {
        $b = $this->db->table('crm_tugas_event e')
            ->join('crm_tugas t', 't.id_tugas = e.tugas_id')
            ->join('crm_tugas_whatsapp w', 'w.tugas_id = t.id_tugas')
            ->join('pelanggan p', 'p.id_pelanggan = t.pelanggan_id', 'left')
            ->join('juragan j', 'j.id_juragan = t.juragan_id', 'left')
            ->join('user du', 'du.id = t.dibuat_oleh', 'left')
            ->join('user ku', 'ku.id = t.dikerjakan_oleh', 'left')
            ->join('user su', 'su.id = t.selesai_oleh', 'left')
            // dua tabel user untuk pelaku: pelepasan manual menulis user_id,
            // operan otomatis hanya menulis dari_user_id (tidak ada yang klik apa-apa)
            ->join('user au', 'au.id = e.user_id', 'left')
            ->join('user ad', 'ad.id = e.dari_user_id', 'left')
            ->whereIn('e.jenis', self::JEJAK_TAK_SELESAI)
            ->whereIn('e.status_lama', ['assigned', 'in_progress', 'waiting']);

        if (! empty($s['tindak_lanjut'])) {
            $b->where('w.tindak_lanjut', (string) $s['tindak_lanjut']);
        }

        if (! empty($s['juragan_id'])) {
            $b->where('t.juragan_id', (int) $s['juragan_id']);
        }

        // "hanya punyaku" di tab ini berarti akulah yang menulis alasannya
        if (! empty($s['milik_saya'])) {
            $b->groupStart()->where('e.user_id', (int) $s['milik_saya'])
                ->orWhere('e.dari_user_id', (int) $s['milik_saya'])->groupEnd();
        }

        if (! empty($s['nomor'])) {
            $c = WaNomorModel::rapikanNomor((string) $s['nomor']);
            $b->groupStart()->like('w.nomor_ke', $c, 'both')->orLike('w.nomor_dari', $c, 'both')->groupEnd();
        }

        if (! empty($s['cari'])) {
            $cari = (string) $s['cari'];
            $b->groupStart()->like('t.judul', $cari)->orLike('e.alasan', $cari)
                ->orLike('e.catatan', $cari)->groupEnd();
        }

        if (! empty($s['dari'])) {
            $b->where('e.created_at >=', (int) strtotime((string) $s['dari'] . ' 00:00:00'));
        }

        if (! empty($s['sampai'])) {
            $b->where('e.created_at <=', (int) strtotime((string) $s['sampai'] . ' 23:59:59'));
        }

        if (! empty($s['juragan_ids'])) {
            $b->groupStart()->where('t.juragan_id IS NULL')->orWhereIn('t.juragan_id', array_map('intval', $s['juragan_ids']))->groupEnd();
        }

        return $b;
    }

    /** Angka badge menu: tugas WA yang belum selesai dalam cakupan akun ini. */
    public function hitungTerbuka(array $juragan_ids): int
    {
        $b = $this->db->table('crm_tugas t')
            ->where('t.jenis', 'catatan_wa')
            ->whereIn('t.status', TugasModel::BELUM_SELESAI);

        if ($juragan_ids !== []) {
            $b->groupStart()->where('t.juragan_id IS NULL')->orWhereIn('t.juragan_id', array_map('intval', $juragan_ids))->groupEnd();
        }

        return (int) $b->countAllResults();
    }

    /**
     * Query dasar daftar WA: tugas + bagian khusus WA + nama semua orang yang
     * terlibat, supaya layar bisa menampilkan label pembuat/pengambil/penyelesai
     * tanpa query tambahan per baris.
     *
     * @param array<string, mixed> $s
     */
    private function bangun(array $s)
    {
        $b = $this->db->table('crm_tugas_whatsapp w')
            // daftar kolom ditulis mentah: kolomnya selalu berkualifikasi alias
            // (w., t., p., ...) dan kalau di-escape CI4 ikut menempelkan DBPrefix
            // ke alias tersebut sehingga query-nya tidak pernah cocok
            ->select('w.*, t.judul, t.deskripsi, t.status, t.prioritas, t.tenggat, t.juragan_id, t.pelanggan_id,'
                . ' t.dibuat_oleh, t.dikerjakan_oleh, t.dikerjakan_at, t.selesai_oleh, t.selesai_at, t.cara_penugasan,'
                . ' p.nama_pelanggan, j.nama_juragan, du.name AS nama_dibuat, ku.name AS nama_dikerjakan,'
                . ' su.name AS nama_selesai, n.label AS label_nomor, n.nomor AS nomor_master', false)
            ->join('crm_tugas t', 't.id_tugas = w.tugas_id')
            ->join('pelanggan p', 'p.id_pelanggan = t.pelanggan_id', 'left')
            ->join('juragan j', 'j.id_juragan = t.juragan_id', 'left')
            ->join('crm_wa_nomor n', 'n.id_nomor = w.nomor_id', 'left')
            ->join('user du', 'du.id = t.dibuat_oleh', 'left')
            ->join('user ku', 'ku.id = t.dikerjakan_oleh', 'left')
            ->join('user su', 'su.id = t.selesai_oleh', 'left');

        if (! empty($s['tab'])) {
            match ((string) $s['tab']) {
                'belum'   => $b->where('t.status', 'queued'),
                'proses'  => $b->whereIn('t.status', ['assigned', 'in_progress', 'waiting']),
                'selesai' => $b->where('t.status', 'completed'),
                default   => null,
            };
        }

        if (! empty($s['tindak_lanjut'])) {
            $b->where('w.tindak_lanjut', (string) $s['tindak_lanjut']);
        }

        if (! empty($s['juragan_id'])) {
            $b->where('t.juragan_id', (int) $s['juragan_id']);
        }

        if (! empty($s['milik_saya'])) {
            $b->where('t.dikerjakan_oleh', (int) $s['milik_saya']);
        }

        if (! empty($s['nomor'])) {
            $c = WaNomorModel::rapikanNomor((string) $s['nomor']);
            $b->groupStart()->like('w.nomor_ke', $c, 'both')->orLike('w.nomor_dari', $c, 'both')->groupEnd();
        }

        if (! empty($s['cari'])) {
            $b->like('t.judul', (string) $s['cari']);
        }

        if (! empty($s['juragan_ids'])) {
            $b->groupStart()->where('t.juragan_id IS NULL')->orWhereIn('t.juragan_id', array_map('intval', $s['juragan_ids']))->groupEnd();
        }

        return $b;
    }
}
