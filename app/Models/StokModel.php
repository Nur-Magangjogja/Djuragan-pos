<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Master stok per juragan: satu baris untuk tiap kombinasi juragan + kode +
 * ukuran + kolam. Kolam jual dan sewa punya barisnya sendiri-sendiri, jadi
 * ukuran yang hanya disewakan tidak pernah muncul sebagai varian di katalog
 * barang jual.
 */
class StokModel extends Model
{
    /** Stok sebanyak ini atau kurang dianggap menipis (masih ada, tapi hampir habis). */
    public const AMBANG_MENIPIS = 1;

    /** Dua kolam stok: unit yang boleh dijual dan unit yang boleh disewa. */
    public const KOLAM = ['jual' => 'Untuk Dijual', 'sewa' => 'Untuk Disewa'];

    protected $table          = 'stok';
    protected $primaryKey     = 'id_stok';
    protected $returnType     = 'object';
    protected $useSoftDeletes = true;
    protected $allowedFields  = ['juragan_id', 'produk_id', 'kode', 'ukuran', 'kolam', 'harga', 'stok', 'keterangan'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';
    protected $dateFormat     = 'int';

    /** Nilai kolam yang aman; apa pun di luar daftar dihitung sebagai kolam jual. */
    public static function kolam(string $kolam): string
    {
        return isset(self::KOLAM[$kolam]) ? $kolam : 'jual';
    }

    /**
     * Tipe pesanan dari form orderan menentukan kolam mana yang dipakai: Sewa
     * mengambil unit sewa, Pembuatan/Jual (dan nota lama tanpa tipe) mengambil
     * unit jual.
     */
    public static function kolamDariTipe($tipe): string
    {
        return (string) $tipe === 'sewa' ? 'sewa' : 'jual';
    }

    /**
     * Katalog untuk kartu produk: satu kartu merangkum semua varian yang nama
     * barangnya sama di satu juragan dan satu kolam. Halaman dihitung per kartu,
     * bukan per varian, karena yang dicari pengguna adalah "barang apa", bukan
     * "ukuran apa".
     *
     * @param array  $juragan_ids ID juragan yang boleh dilihat pengguna
     * @param int    $juragan_id  0 = semua dari $juragan_ids
     * @param string $cari        Dicocokkan dengan kode atau keterangan
     * @param string $keadaan     '', 'ada', 'menipis', 'habis'
     * @param string $kolam       'jual' atau 'sewa': kartu hanya memuat varian kolam ini
     *
     * @return list<array<string, mixed>>
     */
    public function katalog(array $juragan_ids, int $juragan_id = 0, string $cari = '', string $keadaan = '', int $limit = 20, int $offset = 0, string $kolam = 'jual'): array
    {
        $kolam  = self::kolam($kolam);
        $kunci  = $this->kunci($juragan_ids, $juragan_id, $cari, $keadaan, $limit, $offset, $kolam);

        if ($kunci === []) {
            return [];
        }

        $kartu = [];

        foreach ($this->varian($kunci, $juragan_ids, $juragan_id, $cari, $keadaan, $kolam) as $b) {
            $t = &$kartu[$this->tanda($b['juragan_id'], $b['produk_id'], $b['kode'])];

            $t ??= [
                'juragan_id'  => (int) $b['juragan_id'],
                'id_juragan'  => (int) $b['juragan_id'],
                'produk_id'   => (int) $b['produk_id'],
                'kode'        => (string) $b['kode'],
                'nama_juragan' => (string) $b['nama_juragan'],
                'harga_mulai' => (int) $b['harga'],
                'harga_akhir' => (int) $b['harga'],
                'poin'        => (int) ($b['poin_produk'] ?? 0),
                'total_stok'  => 0,
                'terjual'     => 0,
                'varian'      => [],
            ];

            $t['harga_mulai'] = min($t['harga_mulai'], (int) $b['harga']);
            $t['harga_akhir'] = max($t['harga_akhir'], (int) $b['harga']);
            // poin satu produk: sama untuk semua ukuran, ambil apa adanya
            $t['poin']        = (int) ($b['poin_produk'] ?? 0);
            $t['total_stok'] += (int) $b['tersedia'];
            $t['terjual']    += (int) $b['terjual'];
            $t['varian'][]    = $b;

            unset($t);
        }

        // urutan kartu mengikuti $kunci (varian terbaru di atas)
        $urut = [];

        foreach ($kunci as $i => $k) {
            $urut[$this->tanda($k['juragan_id'], $k['produk_id'], $k['kode'])] = $i;
        }

        uksort($kartu, static fn (string $a, string $b) => $urut[$a] <=> $urut[$b]);

        return array_values($kartu);
    }

    /** Banyak kartu katalog dengan penyaringan yang sama. */
    public function kartu(array $juragan_ids, int $juragan_id = 0, string $cari = '', string $keadaan = '', string $kolam = 'jual'): int
    {
        return count($this->kunci($juragan_ids, $juragan_id, $cari, $keadaan, null, 0, $kolam));
    }

    /**
     * Jumlah produk yang BENAR-BENAR ADA stoknya untuk tiap toko — dihitung sebagai
     * kartu katalog (kode unik) yang total unit tersedia-nya lebih dari nol, bukan
     * sekadar jumlah kode. Produk yang seluruh unitnya sedang diikat nota (mis. lagi
     * disewa, atau stoknya sudah 0) tidak ikut dihitung, jadi angka indikator di
     * dropdown juragan berarti "barang yang masih bisa diambil".
     *
     * Sekali query; tiap baris stok dinilai pada kolam-nya sendiri (jual/sewa) lewat
     * klausaTersedia(), persis sama seperti angka "Tersedia" di halaman Produk.
     *
     * @param list<int> $juragan_ids
     *
     * @return array<int, int> juragan_id => jumlah produk tersedia
     */
    public function jumlahPerJuragan(array $juragan_ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $juragan_ids)));

        if ($ids === []) {
            return [];
        }

        $stok     = $this->db->prefixTable('stok');
        $tersedia = "CASE WHEN `" . $stok . "`.kolam = 'sewa' THEN " . $this->klausaTersedia('sewa')
            . ' ELSE ' . $this->klausaTersedia('jual') . ' END';

        // satu kartu = (juragan, kode); hanya kartu yang total tersedia-nya > 0 dihitung
        $sql = 'SELECT t.juragan_id, COUNT(*) AS n FROM ('
            . ' SELECT `' . $stok . '`.juragan_id, `' . $stok . '`.kode'
            . ' FROM `' . $stok . '`'
            . ' WHERE `' . $stok . '`.deleted_at IS NULL'
            . ' AND `' . $stok . '`.juragan_id IN (' . implode(',', $ids) . ')'
            . ' GROUP BY `' . $stok . '`.juragan_id, `' . $stok . '`.kode'
            . ' HAVING SUM(' . $tersedia . ') > 0'
            . ') AS t GROUP BY t.juragan_id';

        $baris = $this->db->query($sql)->getResultArray();

        $hasil = [];

        foreach ($baris as $b) {
            $hasil[(int) $b['juragan_id']] = (int) $b['n'];
        }

        return $hasil;
    }

    /**
     * Angka KEDUA kolam untuk sekelompok barang (juragan + nama barang), sekali
     * query. Katalog hanya menampilkan kolam yang dibuka, tapi pihak luar (API)
     * perlu melihat bahwa unit jual dan unit sewa itu dua tumpukan yang berbeda:
     * stok gudang, unit yang sedang keluar, dan sisanya, per kolam.
     *
     * 'keluar' di sini = unit yang sudah diikat nota: semua nota tercatat yang
     * menaut baris kolam itu menghitung, termasuk nota yang belum diserahkan;
     * hanya nota sewa yang pengembaliannya sudah dicatat yang lepas. Jadi angkanya
     * sama persis dengan pengurang "tersedia" di halaman Produk.
     *
     * @param array<array{juragan_id:int, kode:string}> $pasangan
     *
     * @return array<string, array{jual:?array, sewa:?array}> kunci "juragan|kode kecil"
     */
    public function duaKolam(array $pasangan, array $juragan_ids): array
    {
        if ($pasangan === [] || $juragan_ids === []) {
            return [];
        }

        $tabel    = '`' . $this->db->prefixTable('stok') . '`';
        $kondisi  = [];
        $bind     = [];

        foreach ($pasangan as $p) {
            $kondisi[] = '(?,' . $this->db->escape((string) $p['kode']) . ')';
            $bind[]    = (int) $p['juragan_id'];
        }

        $tersedia = 'CASE WHEN ' . $tabel . ".kolam = 'sewa' THEN " . $this->klausaTersedia('sewa')
            . ' ELSE ' . $this->klausaTersedia('jual') . ' END';

        $baris = $this->db->query('SELECT ' . $tabel . '.juragan_id, ' . $tabel . '.kode, ' . $tabel . ".kolam,"
            . ' COUNT(*) AS varian, SUM(' . $tabel . '.stok) AS stok,'
            . ' SUM(' . $tabel . '.stok) - SUM(' . $tersedia . ') AS keluar,'
            . ' MIN(' . $tabel . '.harga) AS harga_mulai, MAX(' . $tabel . '.harga) AS harga_akhir'
            . ' FROM ' . $tabel
            . ' WHERE ' . $tabel . '.deleted_at IS NULL'
            . ' AND ' . $tabel . '.juragan_id IN (' . implode(',', array_map('intval', $juragan_ids)) . ')'
            . ' AND (' . $tabel . '.juragan_id, ' . $tabel . '.kode) IN (' . implode(',', $kondisi) . ')'
            . ' GROUP BY ' . $tabel . '.juragan_id, ' . $tabel . '.kode, ' . $tabel . '.kolam', $bind)
            ->getResultArray();

        $hasil = [];

        foreach ($baris as $b) {
            $kolam = self::kolam((string) $b['kolam']);
            $kunci = (int) $b['juragan_id'] . '|' . mb_strtolower((string) $b['kode']);

            $hasil[$kunci][$kolam] = [
                'varian'      => (int) $b['varian'],
                'stok'        => (int) $b['stok'],
                'keluar'      => (int) $b['keluar'],
                'tersedia'    => (int) $b['stok'] - (int) $b['keluar'],
                'harga_mulai' => (int) $b['harga_mulai'],
                'harga_akhir' => (int) $b['harga_akhir'],
            ];
        }

        return $hasil;
    }

    /**
     * Kondisi "sewa yang barangnya sudah kembali": nota sewa yang pengembaliannya
     * sudah dicatat orang lewat tombol "Sudah kembali" di nota. Baris orderan
     * seperti ini tidak lagi mengurangi stok, jadi unitnya pulang ke rak pada detik
     * yang sama dengan notanya boleh masuk tab Selesai. Tanggal `rincian.kembali`
     * sendiri hanya jadual: belum diklik berarti masih di tangan penyewa, walau
     * tanggalnya sudah lewat.
     */
    private static function sudahKembali(): string
    {
        return 'o.sewa_kembali_at IS NOT NULL';
    }

    /**
     * Nota mana yang memakai satu kolam. Kolam sewa hanya diisi nota bertipe sewa
     * yang pengembaliannya belum dicatat; kolam jual menampung semuanya yang bukan
     * sewa — termasuk 15 ribu nota lama yang tidak punya tipe, jadi angka mereka
     * tidak berubah saat pemisahan ini dipasang.
     *
     * Di sini sengaja TIDAK ada syarat pembayaran: bagian Masih Disewa di tab
     * Disewa memang menandai fase pekerjaan (barang sudah di tangan penyewa dan
     * dananya sudah masuk), tapi stok tidak boleh menunggu sejauh itu — lihat
     * `klausaTersedia()`.
     */
    private static function tipeKolam(string $kolam): string
    {
        $tipe = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.rincian, '\$.tipe')), 'pembuatan')";

        return $kolam === 'sewa'
            ? $tipe . " = 'sewa' AND NOT (" . self::sudahKembali() . ')'
            : $tipe . " <> 'sewa'";
    }

    /**
     * Ungkapan SQL: stok satu kolam dikurangi unit yang sudah diikat nota, yaitu qty
     * dari setiap nota yang MASIH TERCATAT — tidak dihapus dan tidak dibatalkan —
     * sejak nota itu ditulis, bukan sejak barangnya diserahkan. Kolam sewa lepas
     * lebih awal: unitnya dihitung pulang begitu tombol "Sudah kembali" ditekan.
     *
     * Aturan ini ada supaya tidak ada stok gaib: begitu sebuah orderan tercatat,
     * unitnya tidak bisa lagi dijualkan atau disewakan ke orang lain walau notasnya
     * masih berstatus belum diproses dan pembayarannya baru DP.
     *
     * Dipakai katalog, saringan keadaan, saran form order, penengah qty saat menulis
     * (`keluar()`), dan kartu ringkasan supaya angka stok berarti "masih bisa diambil"
     * untuk kolam yang sedang dibuka, di semua tempat sekaligus.
     *
     * Satu baris stok hanya milik satu kolam, jadi kolomnya selalu `stok`;
     * saringan nota bertipe sekadar menjaga nota lama yang sempat menaut
     * baris ini sebelum kolamnya dipisah.
     *
     * Ekspresi ini mentah (tidak di-escape builder), jadi tabel stok harus
     * ditulis dengan prefix aslinya seperti pada klausa FROM-nya.
     */
    private function klausaTersedia(string $kolam = 'jual'): string
    {
        $kolam   = self::kolam($kolam);
        $stok    = '`' . $this->db->prefixTable('stok') . '`';
        $dibeli  = $this->db->prefixTable('dibeli');
        $invoice = $this->db->prefixTable('invoice');

        return '(CAST(' . $stok . '.stok AS SIGNED) - (SELECT IFNULL(SUM(d.qty), 0)'
            . ' FROM `' . $dibeli . '` d'
            . ' JOIN `' . $invoice . '` o ON o.id_invoice = d.invoice_id'
            . ' WHERE d.stok_id = ' . $stok . '.id_stok'
            . ' AND o.deleted_at IS NULL'
            . " AND o.status_pesanan <> '3'"
            . ' AND ' . self::tipeKolam($kolam) . '))';
    }

    /**
     * Unit yang sudah diikat nota per varian untuk satu kolam. Sama aturannya dengan
     * `klausaTersedia()`, hanya saja bentuknya peta PHP supaya jalur tulis bisa
     * memeriksa dulu sebelum menulis apa pun — termasuk nota yang sedang dibuka
     * itu sendiri, yang dikecualikan lewat `$kecuali_invoice` supaya tidak
     * dihitung dua kali saat menyunting.
     *
     * @return array<int, int> id_stok => qty yang keluar
     */
    public function keluar(array $stok_ids, string $kolam = 'jual', int $kecuali_invoice = 0): array
    {
        $kolam = self::kolam($kolam);
        $ids   = array_values(array_unique(array_filter(array_map('intval', $stok_ids))));

        if ($ids === []) {
            return [];
        }

        $dibeli  = $this->db->prefixTable('dibeli');
        $invoice = $this->db->prefixTable('invoice');

        $sql = 'SELECT d.stok_id, IFNULL(SUM(d.qty), 0) AS keluar'
            . ' FROM `' . $dibeli . '` d'
            . ' JOIN `' . $invoice . '` o ON o.id_invoice = d.invoice_id'
            . ' WHERE d.stok_id IN (' . implode(',', $ids) . ')'
            . ' AND o.deleted_at IS NULL'
            . " AND o.status_pesanan <> '3'"
            . ' AND ' . self::tipeKolam($kolam);

        if ($kecuali_invoice > 0) {
            $sql .= ' AND o.id_invoice <> ' . $kecuali_invoice;
        }

        $sql .= ' GROUP BY d.stok_id';

        $hasil = [];

        foreach ($this->db->query($sql)->getResultArray() as $b) {
            $hasil[(int) $b['stok_id']] = (int) $b['keluar'];
        }

        return $hasil;
    }

    /**
     * Berapa unit sewa yang sudah diikat nota dan belum kembali untuk sekumpulan
     * toko. Sumbernya sama persis dengan pengurang stok kolam sewa di
     * `klausaTersedia('sewa')` — nota bertipe sewa yang belum dicatat kembali,
     * berapapun status pembayarannya — jadi angka ini adalah unit yang hilang dari
     * kolom "Tersedia" di halaman Produk, bukan hitungan lain.
     *
     * @param array<int> $juragan_ids
     */
    public function sedangDisewa(array $juragan_ids): int
    {
        $stok    = $this->db->prefixTable('stok');
        $dibeli  = $this->db->prefixTable('dibeli');
        $invoice = $this->db->prefixTable('invoice');
        $ids     = array_values(array_filter(array_map('intval', $juragan_ids)));
        $ids     = $ids === [] ? '0' : implode(',', $ids);

        $baris = $this->db->query(
            'SELECT IFNULL(SUM(d.qty), 0) AS n'
            . ' FROM `' . $dibeli . '` d'
            . ' JOIN `' . $invoice . '` o ON o.id_invoice = d.invoice_id'
            . ' JOIN `' . $stok . '` s ON s.id_stok = d.stok_id'
            . ' WHERE s.deleted_at IS NULL'
            . " AND s.kolam = 'sewa'"
            . ' AND s.juragan_id IN (' . $ids . ')'
            . ' AND o.deleted_at IS NULL'
            . " AND o.status_pesanan <> '3'"
            . ' AND ' . self::tipeKolam('sewa')
        )->getRowArray();

        return (int) ($baris['n'] ?? 0);
    }

    /**
     * Baris orderan yang mengambil lebih banyak unit daripada stok kolamnya.
     * Pemakaian nota yang sedang disimpan tidak ikut dihitung, jadi menyunting
     * nota yang sudah keluar tetap boleh selama qty-nya tidak bertambah melewati
     * sisa rak.
     *
     * @param array<string|int, array> $baris hasil `tautkan_stok()`
     *
     * @return array<string, string> kunci form => pesan galat
     */
    public function lebih(array $baris, string $kolam = 'jual', int $kecuali_invoice = 0): array
    {
        $kolam = self::kolam($kolam);
        $perlu = [];

        foreach ($baris as $k => $v) {
            $id = (int) ($v['stok_id'] ?? 0);

            if ($id > 0) {
                $perlu[$k] = [
                    'id'     => $id,
                    'qty'    => max(0, (int) ($v['qty'] ?? 0)),
                    'label'  => trim((string) ($v['kode'] ?? '') . ' ' . (string) ($v['ukuran'] ?? '')),
                ];
            }
        }

        if ($perlu === []) {
            return [];
        }

        $punya = [];

        foreach ($this->select('id_stok, stok, kolam')
            ->whereIn('id_stok', array_column($perlu, 'id'))->get()->getResultArray() as $b) {
            $punya[(int) $b['id_stok']] = ['stok' => (int) $b['stok'], 'kolam' => (string) $b['kolam']];
        }

        $keluar = $this->keluar(array_keys($punya), $kolam, $kecuali_invoice);
        $dipaki = [];
        $galat  = [];

        foreach ($perlu as $k => $v) {
            $ada = $punya[$v['id']] ?? null;

            // nota hanya boleh mengambil varian kolamnya sendiri: tipe jual
            // tidak boleh meminjam baris sewa, begitu juga sebaliknya
            if ($ada === null || $ada['kolam'] !== $kolam) {
                $galat['produk[' . $k . '][stok]'] = 'Varian ' . ($v['label'] === '' ? 'ini' : $v['label'])
                    . ' bukan barang ' . mb_strtolower(self::KOLAM[$kolam]) . '.';

                continue;
            }

            $sisa = $ada['stok'] - ($keluar[$v['id']] ?? 0);
            $dipaki[$v['id']] = ($dipaki[$v['id']] ?? 0) + $v['qty'];

            if ($dipaki[$v['id']] > $sisa) {
                $galat['produk[' . $k . '][stok]'] = 'Stok ' . ($v['label'] === '' ? 'barang ini' : $v['label'])
                    . ' tinggal ' . $sisa . ', tidak bisa mengambil ' . $dipaki[$v['id']] . ' unit.';
            }
        }

        return $galat;
    }

    /**
     * Angka untuk kartu ringkasan satu kolam: varian, total unit, nilai stok,
     * menipis, habis, dan berapa barang yang belum diberi poin gaji. Semua
     * angkanya hanya membaca baris kolam yang dibuka.
     *
     * Poin menempel di master produk (order_produk), bukan di baris stok, jadi
     * angka 'belum_poin' dihitung per barang — satu nama barang dengan tiga
     * ukuran tetap dihitung sekali kalau poinnya belum diisi.
     *
     * @return array{varian:int,unit:int,nilai:int,menipis:int,habis:int,belum_poin:int}
     */
    public function ringkas(array $juragan_ids, string $kolam = 'jual'): array
    {
        $kolam    = self::kolam($kolam);
        $tabel    = $this->db->prefixTable('stok');
        $produk   = $this->db->prefixTable('produk');
        $tersedia = $this->klausaTersedia($kolam);

        $baris = $this->db->query('SELECT ' . $tersedia . ' AS tersedia, `' . $tabel . '`.harga AS harga,'
            . ' `' . $tabel . '`.juragan_id AS juragan, `' . $tabel . '`.kode AS nama,'
            . ' `' . $tabel . '`.produk_id AS master, IFNULL(`' . $produk . '`.poin, 0) AS poin'
            . ' FROM `' . $tabel . '`'
            . ' LEFT JOIN `' . $produk . '` ON `' . $produk . '`.id_produk = `' . $tabel . '`.produk_id'
            . ' WHERE `' . $tabel . '`.deleted_at IS NULL AND `' . $tabel . '`.kolam = ' . $this->db->escape($kolam)
            . ' AND ' . $this->klausulJuragan($juragan_ids))
            ->getResultArray();

        $hasil  = ['varian' => 0, 'unit' => 0, 'nilai' => 0, 'menipis' => 0, 'habis' => 0, 'belum_poin' => 0];
        $kosong = [];

        foreach ($baris as $b) {
            $unit = (int) $b['tersedia'];

            $hasil['varian']++;
            $hasil['unit']   += $unit;
            $hasil['nilai']  += $unit * (int) $b['harga'];

            if ($unit <= 0) {
                $hasil['habis']++;
            } elseif ($unit <= self::AMBANG_MENIPIS) {
                $hasil['menipis']++;
            }

            if ((int) $b['poin'] <= 0) {
                $kosong[$this->tanda((int) $b['juragan'], $b['master'], (string) $b['nama'])] = true;
            }
        }

        $hasil['belum_poin'] = count($kosong);

        return $hasil;
    }

    /**
     * Sudah ada baris stok dengan juragan + kode + ukuran + kolam yang sama?
     * Nama yang sama boleh dipakai sekali di tiap kolam, jadi ukuran khusus
     * sewa tidak menghalangi ukuran yang sama didaftarkan untuk dijual. Tanpa
     * unique index, karena baris yang dihapus lunak tidak boleh memblokir nama
     * yang sama dibuat ulang.
     */
    public function kembar(int $id, int $juragan_id, string $kode, ?string $ukuran, string $kolam = 'jual'): bool
    {
        $builder = $this->where('juragan_id', $juragan_id)
            ->where('kode', $kode)
            ->where('kolam', self::kolam($kolam));

        if ($ukuran === null || $ukuran === '') {
            $builder->groupStart()->where('ukuran', null)->orWhere('ukuran', '')->groupEnd();
        } else {
            $builder->where('ukuran', $ukuran);
        }

        if ($id > 0) {
            $builder->where('id_stok !=', $id);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Varian sekartu produk yang masih hidup: juragan dan nama barang yang sama.
     * Foto produk memang menempel pada satu varian, tapi kartu katalog dan form
     * orderan menampilkan gabungan foto sekartu supaya satu foto cukup diunggah
     * sekali — untuk itu kolamnya tidak dipilah (null). Pemanggil yang perlu
     * saudara satu kolam, seperti warisan harga "+ Varian" dan hapus kartu,
     * mengirim kolamnya. Varian yang dihapus lunak dibuang di sini karena
     * `get()` tidak menyaringnya, sementara `find()` iya — campuran keduanya
     * bikin harga warisan menunjuk baris yang sudah mati.
     *
     * @return int[]
     */
    public function sekartu(int $juragan_id, string $kode, ?string $kolam = null): array
    {
        if ($juragan_id <= 0 || $kode === '') {
            return [];
        }

        $baris = $this->select('id_stok')
            ->where('juragan_id', $juragan_id)
            ->where('kode', $kode)
            ->where('deleted_at', null);

        if ($kolam !== null) {
            $baris->where('kolam', self::kolam($kolam));
        }

        $baris = $baris->orderBy('id_stok', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $b): int => (int) $b['id_stok'], $baris);
    }

    /**
     * Saran varian untuk form orderan: varian yang nama barangnya cocok dengan
     * kata yang diketik, atau seluruh varian kolam itu kalau katanya kosong --
     * itu yang dipakai dropdown "Ambil dari stok produk" untuk memperlihatkan
     * isi toko. Saringannya sengaja memakai `saring()` yang dipakai katalog
     * juga, jadi kode, ukuran, harga, dan stok yang dilihat CS di form sama
     * persis dengan kartu katalog.
     *
     * @return list<array<string, mixed>>
     */
    public function saran(array $juragan_ids, int $juragan_id, string $cari, int $limit = 12, string $kolam = 'jual'): array
    {
        if ($juragan_id <= 0) {
            return [];
        }

        $kolam = self::kolam($kolam);

        $baris = $this->saring($juragan_ids, $juragan_id, $cari, '', $kolam)
            ->orderBy('stok.kode')
            ->orderBy('urutan')
            ->orderBy('stok.id_stok')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return $baris;
    }

    /** Nama produk dari master, untuk saran di kolom isian. */
    public function daftarKode(): array
    {
        $produk = $this->db->prefixTable('produk');

        return $this->db->query('SELECT kode FROM `' . $produk . '` ORDER BY kode')->getResultArray();
    }

    /** Ukuran dari master, dikelompokkan supaya dropdown tidak acak-acakan. */
    public function daftarUkuran(): array
    {
        $ukuran = $this->db->prefixTable('ukuran');

        return $this->db->query('SELECT kode, label, kelompok FROM `' . $ukuran . '` ORDER BY kelompok, urutan')->getResultArray();
    }

    private function saring(array $juragan_ids, int $juragan_id, string $cari, string $keadaan, string $kolam = 'jual')
    {
        $kolam = self::kolam($kolam);

        // `ukuran` diberi alias: ekspresi di dalam COALESCE tidak ikut diberi
        // prefix tabel oleh query builder, jadi nama aslinya (order_ukuran) tidak dikenal.
        $builder = $this->select('stok.*, juragan.nama_juragan, COALESCE(u.urutan, 255) AS urutan, IFNULL(pr.poin, 0) AS poin_produk')
            ->select($this->klausaTersedia($kolam) . ' AS tersedia', false)
            ->join('juragan', 'juragan.id_juragan = stok.juragan_id')
            ->join('ukuran u', 'u.kode = stok.ukuran', 'left')
            ->join('produk pr', 'pr.id_produk = stok.produk_id', 'left');

        return $this->pagar($builder, $juragan_ids, $juragan_id, $cari, $keadaan, $kolam);
    }

    /**
     * Saringan yang berlaku atas baris stok: pagar toko, pencarian, dan keadaan
     * stok pada satu kolam. Dipakai bersama oleh query katalog dan query variannya
     * supaya jumlah kartu di pager tidak pernah berbeda dari kartu yang tampil.
     */
    private function pagar($builder, array $juragan_ids, int $juragan_id, string $cari, string $keadaan, string $kolam = 'jual')
    {
        // hasilnya diambil lewat get(), yang menembus query builder tanpa
        // scope soft delete yang biasa dipakai find()/findAll()
        $builder->where('stok.deleted_at', null);

        // tab hanya memuat baris kolamnya sendiri: varian jual dan sewa adalah
        // dua baris yang berbeda meski nama barang dan ukurannya sama
        $builder->where('stok.kolam', $kolam);

        if ($juragan_id > 0) {
            $builder->where('stok.juragan_id', $juragan_id);
        } else {
            // IN () kosong adalah SQL invalid, gunakan [0] saat tidak ada id yang cocok
            $builder->whereIn('stok.juragan_id', $juragan_ids ?: [0]);
        }

        if ($cari !== '') {
            $builder->groupStart()
                ->like('stok.kode', $cari)
                ->orLike('stok.keterangan', $cari)
                ->groupEnd();
        }

        // keadaan dibaca dari stok kolam yang masih tersedia, bukan angka gudang:
        // barang yang sedang keluar di nota Selesai tidak bisa diambil lagi
        $tersedia = $this->klausaTersedia($kolam);

        switch ($keadaan) {
            case 'menipis':
                $builder->where($tersedia . ' > 0', null, false)
                    ->where($tersedia . ' <= ' . self::AMBANG_MENIPIS, null, false);
                break;

            case 'habis':
                $builder->where($tersedia . ' <= 0', null, false);
                break;

            case 'ada':
                $builder->where($tersedia . ' > ' . self::AMBANG_MENIPIS, null, false);
                break;

            default:
                // Katalog jual tampil apa adanya seperti sebelumnya: barang habis
                // masih perlu terlihat supaya bisa ditambah stoknya. Kolam sewa
                // baru dan masih kosong di hampir semua barang, jadi tab halaman
                // Produk hanya memuat unit sewa yang benar-benar ada; yang 0 tetap
                // bisa dibuka lewat saringan "Habis". 'semua' mematikan aturan itu
                // dan dipakai umpan data eksternal, yang justru perlu melihat unit
                // sedang dipinjam.
                if ($kolam === 'sewa' && $keadaan !== 'semua') {
                    $builder->where($tersedia . ' > 0', null, false);
                }
        }

        return $builder;
    }

    /**
     * Kunci tiap kartu katalog: juragan + produk + nama barang. Produk NULL
     * ikut dikelompokkan (baris lama sebelum kode dipetakan ke master), dan
     * `MAX(id_stok)` dipakai sebagai penanda kemudaan karena kartu boleh berisi
     * beberapa varian.
     */
    private function kunci(array $juragan_ids, int $juragan_id, string $cari, string $keadaan, ?int $limit = null, int $offset = 0, string $kolam = 'jual'): array
    {
        // agregat ditulis mentah, jadi nama tabelnya harus berprefix seperti
        // hasil kerja builder (FROM `order_stok` tanpa alias)
        $tabel = $this->db->prefixTable('stok');

        $builder = $this->pagar(
            $this->builder()
                // nama kolom biasa dibiarkan di-escape builder supaya ikut terprefix
                ->select('stok.juragan_id, stok.produk_id, stok.kode')
                ->select('MAX(`' . $tabel . '`.id_stok) AS terbaru', false)
                ->groupBy(['stok.juragan_id', 'stok.produk_id', 'stok.kode']),
            $juragan_ids,
            $juragan_id,
            $cari,
            $keadaan,
            self::kolam($kolam)
        )->orderBy('terbaru', 'DESC');

        if ($limit !== null) {
            $builder->limit($limit, $offset);
        }

        return $builder->get()->getResultArray();
    }

    /** Semua varian dari kunci kartu yang sedang diminta, lengkap dengan terjualnya. */
    private function varian(array $kunci, array $juragan_ids, int $juragan_id, string $cari, string $keadaan, string $kolam = 'jual'): array
    {
        $kolam   = self::kolam($kolam);
        $builder = $this->saring($juragan_ids, $juragan_id, $cari, $keadaan, $kolam)->groupStart();

        foreach (array_values($kunci) as $i => $k) {
            $i === 0 ? $builder->groupStart() : $builder->orGroupStart();

            $builder->where('stok.juragan_id', (int) $k['juragan_id'])
                ->where('stok.kode', $k['kode']);

            // NULL dan '' harus cocok dengan kelompoknya sendiri, bukan dengan varian ber-produk
            if ($k['produk_id'] === null || $k['produk_id'] === '') {
                $builder->groupStart()->where('stok.produk_id', null)->orWhere('stok.produk_id', '')->groupEnd();
            } else {
                $builder->where('stok.produk_id', (int) $k['produk_id']);
            }

            $builder->groupEnd();
        }

        $baris = $builder->groupEnd()
            ->orderBy('stok.juragan_id')
            ->orderBy('stok.kode')
            ->orderBy('urutan')
            ->orderBy('stok.id_stok')
            ->get()
            ->getResultArray();

        return $this->pasangTerjual($baris);
    }

    /** Penanda kartu di PHP; harus sama cara membulatnya dengan kunci() dan varian(). */
    private function tanda(int|string $juragan_id, int|string|null $produk_id, string $kode): string
    {
        $produk = ($produk_id === null || $produk_id === '') ? '-' : (string) (int) $produk_id;

        // kode dibandingkan tanpa peduli huruf besar/kecil, sama seperti collation
        // MySQL yang dipakai GROUP BY di kunci()
        return $juragan_id . '|' . $produk . '|' . mb_strtolower($kode);
    }

    private function klausulJuragan(array $juragan_ids): string
    {
        $ids = array_values(array_filter(array_map('intval', $juragan_ids)));

        // IN () kosong adalah SQL invalid, gunakan (0) saat tidak ada id yang cocok
        return 'juragan_id IN (' . ($ids === [] ? '0' : implode(',', $ids)) . ')';
    }

    /**
     * Barang terjual per varian, dihitung dari transaksi yang belum dihapus.
     * Hanya untuk baris di halaman ini supaya agregasinya tetap murah.
     */
    private function pasangTerjual(array $baris): array
    {
        if ($baris === []) {
            return [];
        }

        $produkIds  = array_values(array_unique(array_filter(array_column($baris, 'produk_id'))));
        $juraganIds = array_values(array_unique(array_map('intval', array_column($baris, 'juragan_id'))));

        $petakan = [];

        if ($produkIds !== []) {
            $dibeli  = $this->db->prefixTable('dibeli');
            $invoice = $this->db->prefixTable('invoice');

            $sql = 'SELECT o.juragan_id, d.produk_id, d.ukuran, SUM(d.qty) AS jumlah'
                . ' FROM `' . $dibeli . '` d'
                . ' JOIN `' . $invoice . '` o ON o.id_invoice = d.invoice_id AND o.deleted_at IS NULL'
                . ' WHERE o.juragan_id IN (' . implode(',', $juraganIds) . ')'
                . ' AND d.produk_id IN (' . implode(',', $produkIds) . ')'
                . ' GROUP BY o.juragan_id, d.produk_id, d.ukuran';

            foreach ($this->db->query($sql)->getResultArray() as $h) {
                $petakan[$h['juragan_id'] . '|' . $h['produk_id'] . '|' . strtolower((string) $h['ukuran'])] = (int) $h['jumlah'];
            }
        }

        foreach ($baris as $i => $b) {
            $kunci = $b['juragan_id'] . '|' . $b['produk_id'] . '|' . strtolower((string) $b['ukuran']);

            $baris[$i]['terjual'] = ! empty($b['produk_id']) ? ($petakan[$kunci] ?? 0) : 0;
        }

        return $baris;
    }
}
