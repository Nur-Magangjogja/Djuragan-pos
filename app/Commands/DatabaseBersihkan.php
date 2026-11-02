<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

/**
 * Menyapu isi database, bukan kerangkanya.
 *
 * Yang dihapus: nota beserta semua yang menempel padanya (item, biaya, pembayaran,
 * pengiriman, foto, status, label, COD, anggota rombongan, poin penjualan), catatan
 * kerja CRM (tugas, checklist yang dijalankan, shift, ulasan, follow-up, aktivitas,
 * log pesan, broadcast, chat), lalu notifikasi dan masukan. Semuanya hasil kerja,
 * jadi setelah disapu aplikasi tetap jalan dan hanya tinggal kosong.
 *
 * Yang tetap utuh: migrations (kerangka skema), master data (toko, produk, stok,
 * pelanggan beserta profil dan tag-nya), dan konfigurasi yang sudah dipasang
 * (kpi_target, pencatatan_pilihan, asal_order, bank, jenis_produk, ukuran, template
 * pesan, tag, SOP, item checklist, pengaturan, template tugas). Daftarnya tidak
 * ditulis di sini: apa pun yang tidak masuk kelompok sapuan otomatis dipertahankan,
 * jadi tabel baru nanti tidak ikut tersapu diam-diam.
 *
 * Akun level superadmin selalu ditinggalkan, dan penghapusan akun diblokir kalau
 * superadmin tidak ada sama sekali — tanpa satu akun pun tidak ada lagi yang bisa
 * masuk untuk memulihkan keadaan. Akun lain dihapus, tautan yang menunjuk ke akun
 * itu (relasi toko-akun, akses toko online, kunci API) ikut diputus supaya tidak
 * meninggalkan baris hantu.
 *
 * TRUNCATE adalah DDL: MySQL menyimpannya seketika, tidak bisa di-rollback. Karena
 * itu salinan dibuat lebih dulu, dan penghapusan tidak berjalan kalau salinan itu
 * gagal ditulis.
 *
 * Pakai: php spark db:bersihkan                          # rencana saja (bawaan)
 *        php spark db:bersihkan --ya                     # sungguhan menghapus
 *        php spark db:bersihkan --ya --kelompok=transaksi
 *        php spark db:bersihkan --ya --warisan            # ikut sapu tabel sistem lama
 *        php spark db:bersihkan --ya --stok0              # master stok diturunkan ke nol
 *        php spark db:bersihkan --ya --tanpa-backup       # hanya untuk database uji
 */
class DatabaseBersihkan extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:bersihkan';
    protected $description = 'Mengosongkan tabel transaksi dan catatan kerja CRM; akun selain superadmin dihapus; master data dan konfigurasi tetap utuh.';
    protected $usage       = 'db:bersihkan [--ya] [--kelompok=transaksi|crm|semua] [--warisan] [--tanpa-akun] [--stok0] [--tanpa-backup] [--produksi]';
    protected $arguments   = [];
    protected $options     = [
        '--ya'           => 'Benar-benar menghapus. Tanpa opsi ini perintah berhenti setelah menampilkan rencana',
        '--kelompok'     => 'Hanya satu kelompok: transaksi, crm, atau semua (bawaan)',
        '--warisan'      => 'Ikutkan tabel warisan (tanpa prefiks order_), sisa sistem lama yang tidak lagi dibaca aplikasi',
        '--tanpa-akun'   => 'Biarkan akun selain superadmin; hanya data kerja yang disapu',
        '--stok0'        => 'Turunkan jumlah stok pada master stok menjadi nol; barisnya tetap ada',
        '--tanpa-backup' => 'Lewati mysqldump. Hanya untuk database uji yang isinya memang tidak diperlukan lagi',
        '--produksi'     => 'Izin tambahan yang wajib bila ENVIRONMENT sedang production',
    ];

    /** Hasil kerja transaksi: satu nota beserta turunannya, induk disebut terakhir. */
    private const TRANSAKSI = [
        'dibeli', 'biaya', 'pembayaran', 'pengiriman', 'invoice_foto', 'invoice_status',
        'label_invoice', 'pencatatan_cod', 'anggota', 'poin_penjualan', 'notifikasi',
        'feedback', 'status_pembayaran_log', 'deadline_backfill_log', 'invoice',
    ];

    /** Catatan kerja harian CRM yang ikut dibersihkan. */
    private const CRM = [
        'crm_tugas_event', 'crm_tugas', 'crm_checklist_hasil', 'crm_checklist_run',
        'crm_shift', 'crm_ulasan', 'crm_followup', 'crm_aktivitas', 'crm_pesan_log',
        'crm_broadcast', 'crm_chat_pesan', 'crm_chat_percakapan', 'pencatatan_saran_abai',
    ];

    /** Kolom penanda akun pada tabel yang dipertahankan; barisnya putus kalau akunnya pergi. */
    private const TAUTAN_AKUN = [
        'crm_toko_akses' => 'user_id',
        'kunci_api'      => 'user_id',
    ];

    /** Kolom yang dipindai tapi tidak diubah: hanya dilaporkan kalau masih menunjuk akun lama. */
    private const KOLOM_AKUN = ['user_id', 'id_user'];

    /** Nama tabel asli (sudah berprefiks) => jumlah baris sebelum apa pun diubah. */
    private array $ada = [];

    private array $sapuan = [];

    /** Tabel tanpa prefiks milik sistem lama; hanya ikut kalau --warisan. */
    private array $warisan = [];

    private string $dump = '';

    public function run(array $params)
    {
        $opt = $this->opsi($params);
        $db  = Database::connect();

        if (! str_contains(strtolower((string) $db->DBDriver), 'mysql')) {
            CLI::error('Perintah ini memakai SQL MySQL (TRUNCATE, FOREIGN_KEY_CHECKS). Driver aktif: ' . $db->DBDriver);

            return 1;
        }

        if (ENVIRONMENT === 'production' && ! $opt['produksi']) {
            CLI::error('ENVIRONMENT production. Tambahkan --produksi kalau memang sadar apa yang dihapus.');

            return 1;
        }

        $this->ada     = $this->isiTabel($db);
        $this->sapuan  = $this->pilih($db, $opt['kelompok']);
        $this->warisan = $this->deteksiWarisan($db, $this->sapuan['baris']);

        if ($opt['warisan']) {
            $this->sapuan = $this->gabung($this->sapuan, $this->warisan);
        }

        $akun = $this->petaAkun($db);

        CLI::write('Database : ' . $db->database . ' di ' . $db->hostname . ' (prefiks: ' . $db->DBPrefix . ')', 'cyan');
        CLI::write('Kelompok : ' . $opt['kelompok'] . ($opt['warisan'] ? ' + warisan' : '') . ' (' . $this->sapuan['jumlah']
            . ' tabel, ' . number_format(array_sum($this->sapuan['baris'])) . ' baris)', 'cyan');
        $this->rencana();
        $this->rencanaAkun($akun, $opt['akun']);

        if (! $opt['ya']) {
            CLI::newLine();
            CLI::write('Ini baru rencana, belum ada yang berubah. Jalankan dengan: php spark ' . $this->name . ' --ya', 'yellow');

            return 0;
        }

        if ($opt['akun'] && $akun['superadmin'] === 0 && $akun['lain'] > 0) {
            CLI::error('Dibatalkan: tidak ada akun superadmin di sini, dan ' . $akun['lain']
                . ' akun lain akan ikut terhapus. Setelah itu tidak ada yang bisa masuk lagi.');
            CLI::error('Simpan dulu satu akun superadmin, atau jalankan dengan --tanpa-akun bila akun memang tidak disentuh.');

            return 1;
        }

        if ($opt['backup']) {
            if ($this->cadangkan($db) === null) {
                return 1;
            }
        } else {
            CLI::newLine();
            CLI::write('Backup dilewati atas permintaan (--tanpa-backup).', 'yellow');
        }

        $kerja = $this->sapu($db);
        $taut  = $opt['akun'] ? $this->putusTautan($db, $akun['id']) : 0;
        $hapus = $opt['akun'] ? $this->hapusAkun($db) : 0;
        $stok  = $opt['stok0'] ? $this->turunkanStok($db) : 0;

        if (! $opt['akun']) {
            CLI::write('  akun: dibiarkan apa adanya (--tanpa-akun)');
        }

        $this->ringkas($db, $kerja, $taut, $hapus, $stok);

        return 0;
    }

    // ------------------------------------------------------------------
    // persiapan
    // ------------------------------------------------------------------

    /**
     * Baca pilihan.
     *
     * Spark menaruh opsi sebagai kunci asosiatif dan SUDAH melepas tanda '--',
     * sementara parser CLI-nya tidak memecah tanda sama dengan: `--kelompok=crm`
     * datang utuh sebagai kunci 'kelompok=crm', dan `--ya` sebagai kunci 'ya'
     * bernilai null. Bentuk lama (`--ya` masih memakai tanda, atau nilai menyusul
     * di argumen berikutnya) ikut diterima supaya perintah ini tidak berubah
     * perilaku kalau versi framework atau cara memanggilnya bergeser.
     *
     * @return array<string, mixed>
     */
    private function opsi(array $params): array
    {
        $isi = [];

        foreach ($params as $kunci => $nilai) {
            $tekun = is_string($kunci) ? $kunci : $nilai;

            if (! is_string($tekun)) {
                continue;
            }

            $tekun = ltrim($tekun, '-');

            if ($tekun === '') {
                continue;
            }

            $pecah = explode('=', $tekun, 2);
            $nama  = $pecah[0];

            if ($nama === '') {
                continue;
            }

            // nilai tertulis di kunci (a=b) menang, lalu nilai bawaan spark
            $isi[$nama] = $pecah[1] ?? (is_string($nilai) && ! is_string($kunci) ? $nilai : '');
        }

        $kelompok = strtolower((string) ($isi['kelompok'] ?? ''));

        return [
            'ya'       => array_key_exists('ya', $isi),
            'backup'   => ! array_key_exists('tanpa-backup', $isi),
            'akun'     => ! array_key_exists('tanpa-akun', $isi),
            'stok0'    => array_key_exists('stok0', $isi),
            'produksi' => array_key_exists('produksi', $isi),
            'warisan'  => array_key_exists('warisan', $isi),
            'kelompok' => in_array($kelompok, ['transaksi', 'crm', 'semua'], true) ? $kelompok : 'semua',
        ];
    }

    /** Nama tabel berprefiks => jumlah baris, hanya tabel nyata (view tidak ikut). */
    private function isiTabel($db): array
    {
        $isi  = [];
        $daftar = $db->query('SELECT TABLE_NAME AS nama FROM information_schema.TABLES'
            . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->getResultArray();

        foreach ($daftar as $t) {
            $nama   = (string) $t['nama'];
            $isi[$nama] = (int) $db->query('SELECT COUNT(*) AS j FROM `' . str_replace('`', '', $nama) . '`')->getRowArray()['j'];
        }

        return $isi;
    }

    /**
     * Nama asli dari kelompok terpilih, plus tabel yang disebut tapi tidak ada.
     *
     * @return array{baris: array<string, int>, jumlah: int, hilang: list<string>}
     */
    private function pilih($db, string $kelompok): array
    {
        $logis = match ($kelompok) {
            'transaksi' => self::TRANSAKSI,
            'crm'       => self::CRM,
            default     => array_merge(self::TRANSAKSI, self::CRM),
        };

        $baris  = [];
        $hilang = [];

        foreach ($logis as $nama) {
            $asli = (string) $db->prefixTable($nama);

            if (! array_key_exists($asli, $this->ada)) {
                $hilang[] = $nama;

                continue;
            }

            $baris[$asli] = $this->ada[$asli];
        }

        return ['baris' => $baris, 'jumlah' => count($baris), 'hilang' => $hilang];
    }

    /**
     * Tabel yang tidak memakai prefiks aplikasi: sisa sistem lama.
     *
     * Dikenali dari prefiks koneksi, bukan dari daftar nama yang ditulis tangan,
     * jadi tabel hantu baru ikut terlihat. Isinya tidak dibaca model mana pun
     * (kolom carian "faktur" di controller hanyalah label, bukan nama tabel), tapi
     * tetap tidak disentuh kecuali --warisan diminta: ini keputusan, bukan default.
     *
     * @param array<string, int> $sudah
     * @return array<string, int>
     */
    private function deteksiWarisan($db, array $sudah): array
    {
        $prefiks = (string) $db->DBPrefix;
        $kandidat = [];

        foreach ($this->ada as $nama => $jumlah) {
            if (isset($sudah[$nama]) || ($prefiks !== '' && str_starts_with($nama, $prefiks))) {
                continue;
            }

            $kandidat[$nama] = $jumlah;
        }

        return $kandidat;
    }

    /** @param array<string, int> $warisan */
    private function gabung(array $sapuan, array $warisan): array
    {
        $sapuan['baris']  = $sapuan['baris'] + $warisan;
        $sapuan['jumlah'] = count($sapuan['baris']);

        return $sapuan;
    }

    /** @return array{superadmin: int, lain: int, id: list<int>} */
    private function petaAkun($db): array
    {
        $user = (string) $db->prefixTable('user');

        if (! array_key_exists($user, $this->ada)) {
            return ['superadmin' => 0, 'lain' => 0, 'id' => []];
        }

        $superadmin = (int) $db->query('SELECT COUNT(*) AS j FROM `' . $user . '`'
            . " WHERE level = 'superadmin'")->getRowArray()['j'];

        $id = array_map(static fn ($r) => (int) $r['id'], $db->query('SELECT id FROM `' . $user . '`'
            . " WHERE level <> 'superadmin' ORDER BY id")->getResultArray());

        return ['superadmin' => $superadmin, 'lain' => count($id), 'id' => $id];
    }

    private function rencana()
    {
        CLI::newLine();

        $tabel = [];

        foreach ($this->sapuan['baris'] as $nama => $jumlah) {
            $tabel[] = [$nama, number_format($jumlah), 'disapu'];
        }

        foreach ($this->ada as $nama => $jumlah) {
            if (isset($this->sapuan['baris'][$nama])) {
                continue;
            }

            $tabel[] = [$nama, number_format($jumlah), isset($this->warisan[$nama]) ? 'warisan' : 'tetap'];
        }

        CLI::table($tabel, ['Tabel', 'Baris', 'Nasib']);

        if ($this->warisan !== []) {
            CLI::write('Warisan = ' . count($this->warisan) . ' tabel tanpa prefiks ' . $this->prefixNama()
                . ' (' . number_format(array_sum($this->warisan)) . ' baris) dari sistem lama; tidak dibaca aplikasi mana pun.'
                . ' Ikutkan dengan: --warisan', 'yellow');
        }

        if ($this->sapuan['hilang'] !== []) {
            CLI::write('Dilewati, tabelnya tidak ada: ' . implode(', ', $this->sapuan['hilang']), 'yellow');
        }
    }

    private function prefixNama(): string
    {
        return (string) Database::connect()->DBPrefix;
    }

    /** @param array{superadmin: int, lain: int, id: list<int>} $akun */
    private function rencanaAkun(array $akun, bool $boleh)
    {
        CLI::newLine();

        if (! $boleh) {
            CLI::write('Akun: tidak disentuh (--tanpa-akun). ' . $akun['superadmin'] . ' superadmin dan '
                . $akun['lain'] . ' akun lain tetap ada.', 'cyan');

            return;
        }

        CLI::write('Akun: ' . $akun['superadmin'] . ' superadmin dipertahankan, ' . $akun['lain']
            . ' akun lain dihapus beserta tautannya.', $akun['superadmin'] > 0 ? 'cyan' : 'red');

        if ($akun['superadmin'] === 0) {
            CLI::write('Tidak ada superadmin sama sekali, jadi --ya akan ditolak sampai minimal satu dibuat.', 'red');
        }
    }

    // ------------------------------------------------------------------
    // eksekusi
    // ------------------------------------------------------------------

    /** Salinan penuh lewat mysqldump; null berarti gagal dan penghapusan tidak boleh jalan. */
    private function cadangkan($db): ?string
    {
        $folder = WRITEPATH . 'backups';

        if (! is_dir($folder) && ! mkdir($folder, 0775, true)) {
            CLI::error('Tidak bisa membuat folder ' . $folder);

            return null;
        }

        $berkas = $folder . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $db->database)
            . '-' . date('Ymd-His') . '.sql';

        CLI::newLine();
        CLI::write('Membuat salinan: ' . $berkas, 'cyan');

        // password dibaca dari .env dan kadang masih membawa kutip bawaan
        $sandi = trim((string) $db->password, "'\"");

        $perintah = 'mysqldump'
            . ' --host=' . escapeshellarg((string) ($db->hostname !== '' ? $db->hostname : 'localhost'))
            . ' --port=' . escapeshellarg((string) ($db->port !== '' ? $db->port : '3306'))
            . ' --user=' . escapeshellarg((string) $db->username)
            . ($sandi === '' ? '' : ' --password=' . escapeshellarg($sandi))
            . ' --single-transaction --routines --triggers'
            . ' --result-file=' . escapeshellarg($berkas)
            . ' ' . escapeshellarg((string) $db->database)
            . ' 2>&1';

        $keluar = [];
        $kode   = 0;

        exec($perintah, $keluar, $kode);

        if ($kode !== 0 || ! is_file($berkas) || filesize($berkas) < 1024) {
            CLI::error('mysqldump gagal (kode ' . $kode . '): ' . implode(' ', array_slice($keluar, 0, 3)));
            CLI::error('Pastikan mysqldump ada di PATH, atau jalankan dengan --tanpa-backup bila ini database uji.');

            return null;
        }

        $this->dump = $berkas;
        CLI::write('Salinan tersimpan: ' . number_format((int) (filesize($berkas) / 1024)) . ' KB', 'green');

        return $berkas;
    }

    /**
     * TRUNCATE semua tabel terpilih.
     *
     * FOREIGN_KEY_CHECKS dimatikan sebentar karena tabel saling merujuk (item dan
     * pembayaran menunjuk invoice, stok menunjuk toko) dan TRUNCATE induk tidak bisa
     * menunggu anak selesai lebih dulu. Yang ditangguhkan hanya pengecekan MySQL,
     * datanya tidak berubah.
     *
     * @return int jumlah baris kerja yang hilang
     */
    private function sapu($db): int
    {
        CLI::newLine();

        $total = 0;

        $db->query('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($this->sapuan['baris'] as $nama => $jumlah) {
                $db->query('TRUNCATE TABLE `' . str_replace('`', '', $nama) . '`');
                $total += $jumlah;

                CLI::write('  disapu: ' . $nama . ' (' . number_format($jumlah) . ' baris)');
            }
        } finally {
            $db->query('SET FOREIGN_KEY_CHECKS = 1');
        }

        return $total;
    }

    /**
     * Putuskan baris yang menunjuk akun yang dihapus.
     *
     * Hanya tabel tautan yang diubah: relasi (baris `table` = 1 adalah tautan
     * toko-akun, id akunnya disimpan di val_id) dan yang terdaftar di TAUTAN_AKUN.
     * Kolomnya tidak pernah diasumsikan — yang dipakai adalah kolom yang benar-benar
     * ada di struktur database, jadi tabel yang ejaannya berbeda (kunci_api menyimpan
     * id_token, bukan user_id) hanya dilewati dengan kabar, bukan menjatuhkan seluruh
     * penghapusan di tengah jalan.
     *
     * Tabel master seperti produk atau toko sengaja TIDAK ikut dibersihkan walau
     * menyimpan kolom pelaku: di sana yang hilang bukan tautannya tapi datanya.
     * Kolom ber-akun lainnya hanya dihitung dan dilaporkan supaya jejak yang
     * menggantung terlihat.
     */
    private function putusTautan($db, array $id): int
    {
        if ($id === []) {
            return 0;
        }

        $daftar  = implode(',', $id);
        $relasi  = (string) $db->prefixTable('relasi');
        $kolom   = $this->kolomAkun($db);
        $taut    = 0;
        $sentuh  = [(string) $db->prefixTable('user'), $relasi];

        if (array_key_exists($relasi, $this->ada)) {
            $taut += $this->buangTautan($db, $relasi, 'val_id', $daftar, '`table` = 1 AND');
        }

        foreach (self::TAUTAN_AKUN as $logis => $diharapkan) {
            $nama = (string) $db->prefixTable($logis);

            $sentuh[] = $nama;

            // yang sudah di-TRUNCATE tidak perlu dibersihkan lagi
            if (! array_key_exists($nama, $this->ada) || isset($this->sapuan['baris'][$nama])) {
                continue;
            }

            $punya = $kolom[$nama] ?? null;

            if ($punya === null) {
                CLI::write('  dilewati, tidak punya kolom akun: ' . $nama . ' (diharapkan ' . $diharapkan . ')', 'yellow');

                continue;
            }

            $taut += $this->buangTautan($db, $nama, $punya, $daftar);
        }

        foreach ($kolom as $nama => $punya) {
            if (in_array($nama, $sentuh, true) || isset($this->sapuan['baris'][$nama]) || ! array_key_exists($nama, $this->ada)) {
                continue;
            }

            $gantung = (int) $db->query('SELECT COUNT(*) AS j FROM `' . str_replace('`', '', $nama) . '`'
                . ' WHERE `' . $punya . '` IN (' . $daftar . ')')->getRowArray()['j'];

            if ($gantung > 0) {
                CLI::write('  dibiarkan (bukan tabel tautan): ' . $nama . '.' . $punya . ' masih menunjuk '
                    . number_format($gantung) . ' akun yang dihapus', 'yellow');
            }
        }

        return $taut;
    }

    /** Nama tabel asli => kolom penanda akun yang sungguh ada di struktur database. */
    private function kolomAkun($db): array
    {
        $kolom = [];

        $pindaian = $db->query("SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS"
            . ' WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME IN (\'' . implode("','", self::KOLOM_AKUN) . '\')'
            . ' ORDER BY TABLE_NAME')->getResultArray();

        foreach ($pindaian as $k) {
            $kolom[(string) $k['t']] = (string) $k['c'];
        }

        return $kolom;
    }

    /** Satu penghapusan tautan; gagal di satu tabel tidak boleh membatalkan sisanya. */
    private function buangTautan($db, string $nama, string $kolom, string $daftar, string $syarat = ''): int
    {
        $sql = 'DELETE FROM `' . str_replace('`', '', $nama) . '` WHERE ' . $syarat
            . '`' . str_replace('`', '', $kolom) . '` IN (' . $daftar . ')';

        try {
            $db->query($sql);
        } catch (Throwable $e) {
            CLI::write('  gagal membersihkan ' . $nama . ': ' . $e->getMessage(), 'red');

            return 0;
        }

        $hapus = $db->affectedRows();

        if ($hapus > 0) {
            CLI::write('  tautan diputus: ' . $nama . '.' . $kolom . ' (' . number_format($hapus) . ' baris)');
        }

        return $hapus;
    }

    /** Akun selain superadmin dihapus keras; riwayat login ikut, karena tidak ada lagi yang membacanya. */
    private function hapusAkun($db): int
    {
        $user = (string) $db->prefixTable('user');

        if (! array_key_exists($user, $this->ada) || $this->petaAkun($db)['lain'] === 0) {
            CLI::write('  akun: tidak ada yang perlu dihapus');

            return 0;
        }

        $db->query('DELETE FROM `' . $user . "` WHERE level <> 'superadmin'");
        $hapus = $db->affectedRows();

        CLI::write('  akun dihapus: ' . number_format($hapus) . ' (non-superadmin)');

        return $hapus;
    }

    /** Master stok tetap ada, hanya jumlahnya yang diturunkan ke nol. */
    private function turunkanStok($db): int
    {
        $stok = (string) $db->prefixTable('stok');

        if (! array_key_exists($stok, $this->ada)) {
            return 0;
        }

        $kolom = array_map(static fn ($r) => (string) $r['c'], $db->query('SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS'
            . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . str_replace("'", '', $stok) . "'")->getResultArray());

        $diisi = [];

        foreach (['stok', 'stok_sewa', 'stok_jual', 'stok_tersedia', 'jumlah'] as $k) {
            if (in_array($k, $kolom, true)) {
                $diisi[] = '`' . $k . '` = 0';
            }
        }

        if ($diisi === []) {
            CLI::write('  stok: kolom jumlah tidak dikenali, dilewati', 'yellow');

            return 0;
        }

        $db->query('UPDATE `' . $stok . '` SET ' . implode(', ', $diisi));
        $ubah = $db->affectedRows();

        CLI::write('  stok: ' . number_format($ubah) . ' baris master diturunkan ke nol ('
            . implode(', ', $diisi) . ')');

        return $ubah;
    }

    /** Hitung ulang apa yang tersisa, lalu bersihkan hafalan yang menunjuk data lama. */
    private function ringkas($db, int $kerja, int $taut, int $akun, int $stok)
    {
        $sisa   = 0;
        $paling = [];

        foreach ($this->isiTabel($db) as $nama => $jumlah) {
            $sisa += $jumlah;

            if ($jumlah > 0) {
                $paling[$nama] = $jumlah;
            }
        }

        arsort($paling);

        CLI::newLine();
        CLI::write('Baris kerja dihapus      : ' . number_format($kerja), 'green');
        CLI::write('Tautan akun diputus     : ' . number_format($taut));
        CLI::write('Akun dihapus            : ' . number_format($akun));
        CLI::write('Baris master diubah stok: ' . number_format($stok));
        CLI::write('Isi database sekarang   : ' . number_format($sisa) . ' baris (master + konfigurasi)');

        if ($paling !== []) {
            CLI::newLine();
            CLI::write('Yang masih berisi data:', 'cyan');

            foreach (array_slice($paling, 0, 12, true) as $nama => $jumlah) {
                CLI::write('  ' . str_pad($nama, 32, ' ') . number_format($jumlah) . ' baris');
            }

            if (count($paling) > 12) {
                CLI::write('  ... dan ' . (count($paling) - 12) . ' tabel lainnya');
            }
        }

        try {
            $penyimpan = service('cache');

            // CacheInterface tidak mengenal clear(); deleteMatching('**') menyapu
            // seluruh key pada penyimpanan file, termasuk hafalan daftar toko
            // dan akun yang sekarang sudah tidak ada isinya
            if (method_exists($penyimpan, 'deleteMatching')) {
                $penyimpan->deleteMatching('*');
                CLI::newLine();
                CLI::write('Cache hafalan dibersihkan supaya daftar toko dan akun tidak lagi memperlihatkan data lama.');
            }
        } catch (Throwable $e) {
            CLI::newLine();
            CLI::write('Cache tidak bisa dibersihkan otomatis (' . $e->getMessage() . '); hapus isi writable/cache bila perlu.', 'yellow');
        }

        if ($this->dump !== '') {
            CLI::newLine();
            CLI::write('Salinan sebelum menghapus: ' . $this->dump, 'cyan');
            CLI::write('Pulihkan dengan: mysql -u ' . $db->username . ' -p ' . $db->database . ' < "' . $this->dump . '"');
        }
    }
}
