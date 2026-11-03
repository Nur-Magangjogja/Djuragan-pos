<?php

namespace App\Models;

use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Model;

/**
 * Foto orderan: acuan bentuk yang biasanya dikirim pelanggan lewat WA.
 *
 * Aturan main yang dijaga di sini:
 *  - hanya gambar sungguhan yang masuk; tipe dibaca dari isi berkas, bukan dari
 *    nama ekstensi, jadi file .jpg berisi PHP tidak bisa nyelip;
 *  - berkas selalu ditulis ulang lewat GD. EXIF (termasuk koordinat HP) dan
 *    muatan tersembunyi di belakang gambar ikut hilang;
 *  - nama berkas di disk acak, jadi tidak ada jalur yang bisa ditebak dan nama
 *    asli pelanggan tidak bocor ke disk;
 *  - fotonya nempel ke invoice, bukan ke item, sesuai cara foto dipakai.
 *
 * Order lama tidak punya baris di tabel ini dan itu normal; pembacanya hanya
 * menampilkan panel foto kalau isinya tidak kosong.
 *
 * Kelas ini juga jadi dasar ProdukFotoModel. Empat penyetel di bawah
 * (kolomAcuan/folder/api/induk) satu-satunya perbedaan antara foto orderan dan
 * foto produk, jadi seluruh jalur penulisan berkas dipakai bersama dan tidak
 * ada dua salinan pipa unggah yang bisa berbeda sendiri nanti.
 */
class FotoModel extends Model
{
    protected $table         = 'invoice_foto';
    protected $primaryKey    = 'id_foto';
    protected $returnType    = 'array';
    protected $allowedFields = ['invoice_id', 'berkas', 'nama_asli', 'tipe', 'byte', 'lebar', 'tinggi', 'user_id'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $dateFormat    = 'int';

    /** Sebanyak ini pun jarang dipakai; batasnya cuma supaya disk tidak kewatiran. */
    public const MAKS_FOTO = 12;

    /**
     * 3 MB cukup untuk foto 1600 px; browser sudah memcompress sebelum kirim.
     * Public karena panel foto perlu menyerahkannya ke peramban lewat data-*,
     * supaya foto yang pasti ditolak server tertahan sebelum orderan dikirim.
     */
    public const MAKS_BYTE = 3 * 1024 * 1024;

    /** Sisi terpanjang yang disimpan. Lebih dari ini hanya memberatkan halaman. */
    private const MAKS_SISI = 1600;

    /** Sumber di atas ini bisa bikin proses GD kehabisan memori di server. */
    private const MAKS_SISI_ASAM = 5000;

    /** Isi berkas => [tipe disimpan, ekstensi]. Hanya tiga ini yang diterima. */
    private const TIPE = [
        'image/jpeg' => ['jpeg', 'jpg'],
        'image/pjpeg' => ['jpeg', 'jpg'],
        'image/png'  => ['png', 'png'],
        'image/webp' => ['webp', 'webp'],
    ];

    /**
     * Sisi thumbnail yang boleh diminta lewat ?s=. Selain dua angka ini foto
     * disaji utuh, jadi tidak ada yang bisa minta ukuran bebas ke server.
     */
    public const SISI_THUMB = [240, 480];

    /** Kolom yang menautkan baris foto ke pemiliknya. */
    protected static function kolomAcuan(): string
    {
        return 'invoice_id';
    }

    /** Folder di writable/uploads tempat berkas ditulis. */
    protected static function folder(): string
    {
        return 'invoice';
    }

    /** Path API yang menyajikan berkasnya; cuma satu jalan membaca foto. */
    protected static function api(): string
    {
        return 'api/foto/saji';
    }

    /** Sebutan untuk pemilik foto, dipakai supaya pesan galat tidak menyesatkan. */
    protected static function induk(): string
    {
        return 'orderan';
    }

    /** Foto siap tampil untuk satu orderan, urut dari yang paling lama. */
    public function untukInvoice(int $invoice_id): array
    {
        return $this->where('invoice_id', $invoice_id)->orderBy('id_foto', 'ASC')->findAll();
    }

    /**
     * Foto siap tampil untuk banyak pemilik sekali query:
     * [id_pemilik => [baris, ...]]. Dipakai daftar transaksi dan daftar barang
     * supaya tidak menambah satu query per kartu.
     *
     * @param int[] $pemilik_ids
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function banyak(array $pemilik_ids): array
    {
        $id = array_values(array_filter(array_map('intval', $pemilik_ids)));

        if ($id === []) {
            return [];
        }

        $ak    = static::kolomAcuan();
        $tabel = $this->db->prefixTable($this->table);
        $hasil = [];

        $baris = $this->db->query('SELECT id_foto, ' . $ak . ' AS pemilik, nama_asli, tipe, byte, lebar, tinggi, created_at'
            . ' FROM `' . $tabel . '` WHERE ' . $ak . ' IN (' . implode(',', $id) . ') ORDER BY id_foto')->getResultArray();

        foreach ($baris as $b) {
            $hasil[(int) $b['pemilik']][] = $b;
        }

        return $hasil;
    }

    public function jumlah(int $pemilik): int
    {
        return $this->where(static::kolomAcuan(), $pemilik)->countAllResults();
    }

    /** Tipe tersimpan => isi header Content-Type waktu foto dibuka. */
    public static function mime(string $tipe): string
    {
        return match ($tipe) {
            'png'  => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /** Alamat satu foto di API. $sisi diisi untuk minta versi kecilnya. */
    public static function alamat(int $id_foto, int $sisi = 0): string
    {
        $alamat = site_url(static::api() . '/' . $id_foto);

        return $sisi > 0 ? $alamat . '?s=' . $sisi : $alamat;
    }

    /**
     * Berkas thumbnail ukuran $sisi: dibuat sekali lalu dipakai terus.
     *
     * Daftar transaksi bisa memuat puluhan foto sekaligus dan kotaknya cuma
     * sebesar kuku; menyaji berkas 1600 px di situ memaksa peramban menarik
     * ratusan kilobyta per kartu. Hasilnya selalu ditulis di samping berkas
     * aslinya dengan nama yang bisa dihitung, jadi tidak ada tabel tambahan
     * dan tidak ada berkas yatim kalau aslinya dihapus.
     *
     * Null berarti pemanggil harus menyaji berkas asli (gambar kecil, ukuran
     * tidak dikenal, atau thumbnail-nya gagal dibuat).
     *
     * @param array<string, mixed> $baris
     */
    public static function thumb(array $baris, int $sisi): ?string
    {
        if (! in_array($sisi, self::SISI_THUMB, true)) {
            return null;
        }

        $asal = self::jalur((string) ($baris['berkas'] ?? ''));

        if ($asal === null) {
            return null;
        }

        // foto yang sudah kecil dikecilkan lagi cuma bikin buram
        if (max((int) ($baris['lebar'] ?? 0), (int) ($baris['tinggi'] ?? 0)) <= $sisi) {
            return $asal;
        }

        $nama  = self::nama_thumb(basename($asal), $sisi);
        $jalan = dirname($asal) . DIRECTORY_SEPARATOR . $nama;

        if (is_file($jalan)) {
            return $jalan;
        }

        $isi = @file_get_contents($asal);

        if ($isi === false || $isi === '') {
            return null;
        }

        $gambar = @imagecreatefromstring($isi);

        if (! $gambar instanceof \GdImage) {
            return null;
        }

        $tipe   = (string) ($baris['tipe'] ?? 'jpeg');
        $kanvas = self::perkecil($gambar, imagesx($gambar), imagesy($gambar), $tipe, $sisi);

        if ($kanvas === null) {
            return null;
        }

        $ditulis = match ($tipe) {
            'png'  => @imagepng($kanvas, $jalan, 6),
            'webp' => @imagewebp($kanvas, $jalan, 78),
            default => @imagejpeg($kanvas, $jalan, 78),
        };

        imagedestroy($kanvas);

        if (! $ditulis || ! is_file($jalan)) {
            @unlink($jalan);

            return null;
        }

        return $jalan;
    }

    /** Nama berkas thumbnail selalu dihitung dari nama aslinya. */
    private static function nama_thumb(string $nama, int $sisi): string
    {
        return '.thumb-' . $sisi . '-' . $nama;
    }

    /**
     * Satu berkas jadi satu baris + satu berkas di disk.
     *
     * @return array{0:?array, 1:?string} [baris baru, null] atau [null, pesan galat]
     */
    public function terima(UploadedFile $berkas, int $pemilik, int $user_id): array
    {
        if ($this->jumlah($pemilik) >= self::MAKS_FOTO) {
            return [null, 'Foto di ' . static::induk() . ' ini sudah ' . self::MAKS_FOTO . ' buah, cukup segitu ya.'];
        }

        $galat_upload = $berkas->getError();

        if ($galat_upload !== UPLOAD_ERR_OK) {
            // upload_max_filesize/post_max_size server yang menolak, jadi pesannya
            // tidak boleh menyuruh memperkecil — browser-nya sudah memcompress
            return [null, match ($galat_upload) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fotonya terlalu besar untuk server, coba kirim foto yang lebih ringan.',
                UPLOAD_ERR_NO_FILE => 'Tidak ada foto yang ikut terkirim.',
                default => 'Foto gagal diunggah, coba lagi ya.',
            }];
        }

        if ($berkas->getSize() > self::MAKS_BYTE) {
            return [null, 'Fotonya terlalu besar (maksimum ' . round(self::MAKS_BYTE / 1024 / 1024, 1) . ' MB), coba kirim ulang.'];
        }

        $isi = file_get_contents($berkas->getRealPath());

        if ($isi === false || $isi === '') {
            return [null, 'Foto gagal dibaca, coba lagi ya.'];
        }

        $kepala = @getimagesizefromstring($isi);

        if ($kepala === false || ! isset(self::TIPE[$kepala['mime']])) {
            return [null, 'Yang diunggah bukan foto JPEG, PNG, atau WebP.'];
        }

        $lebar  = (int) $kepala[0];
        $tinggi = (int) $kepala[1];

        if ($lebar < 40 || $tinggi < 40) {
            return [null, 'Fotonya terlalu kecil untuk dilihat, kirim yang lebih jelas ya.'];
        }

        if ($lebar > self::MAKS_SISI_ASAM || $tinggi > self::MAKS_SISI_ASAM) {
            return [null, 'Resolusi fotonya kebesaran (maksimum ' . self::MAKS_SISI_ASAM . ' px), coba perkecil dulu.'];
        }

        [$tipe, $ekstensi] = self::TIPE[$kepala['mime']];

        $gambar = @imagecreatefromstring($isi);

        if ($gambar === false) {
            return [null, 'Fotonya rusak, tidak bisa dibuka.'];
        }

        $gambar = self::perbaikiOrientasi($gambar, $berkas->getRealPath(), $tipe);

        // orientasi bisa memutar gambar, jadi ukuran sumber dibaca ulang
        $kanvas = self::perkecil($gambar, imagesx($gambar), imagesy($gambar), $tipe);

        if ($kanvas === null) {
            return [null, 'Fotonya gagal diolah, coba lagi ya.'];
        }

        $lebar = imagesx($kanvas);
        $tinggi = imagesy($kanvas);

        $folder = static::arah($pemilik);

        if ($folder === null) {
            imagedestroy($kanvas);

            return [null, 'Folder penyimpanan foto tidak bisa dibuat, laporkan ke juragan ya.'];
        }

        $nama  = bin2hex(random_bytes(8)) . '.' . $ekstensi;
        $jalan = $folder . DIRECTORY_SEPARATOR . $nama;

        $ditulis = match ($tipe) {
            'png'  => @imagepng($kanvas, $jalan, 6),
            'webp' => @imagewebp($kanvas, $jalan, 82),
            default => @imagejpeg($kanvas, $jalan, 82),
        };

        imagedestroy($kanvas);

        if (! $ditulis || ! is_file($jalan)) {
            @unlink($jalan);

            return [null, 'Foto gagal disimpan di server, coba lagi ya.'];
        }

        $id = $this->insert([
            static::kolomAcuan() => $pemilik,
            'berkas'     => static::relatif($nama, $pemilik),
            'nama_asli'  => self::namaTampil($berkas->getClientName()),
            'tipe'       => $tipe,
            'byte'       => (int) filesize($jalan),
            'lebar'      => $lebar,
            'tinggi'     => $tinggi,
            'user_id'    => $user_id,
        ]);

        if ($id === false) {
            @unlink($jalan);

            return [null, 'Foto gagal dicatat, coba lagi ya.'];
        }

        $baris = $this->find((int) $id);

        return [$baris, null];
    }

    /**
     * Hapus satu foto beserta berkasnya. Baris dan berkas selalu pergi berdua,
     * tidak ada berkas yatim yang masih bisa dibuka.
     */
    public function buang(int $id_foto): bool
    {
        $baris = $this->find($id_foto);

        if ($baris === null) {
            return false;
        }

        $jalan = self::jalur($baris['berkas']);

        if ($jalan !== null) {
            @unlink($jalan);

            // thumbnail ikut pergi bersama aslinya, jangan sampai jadi berkas yatim
            foreach (self::SISI_THUMB as $sisi) {
                @unlink(dirname($jalan) . DIRECTORY_SEPARATOR . self::nama_thumb(basename($jalan), $sisi));
            }

            // folder orderan yang sudah kosong jangan ditinggalkan di disk
            @rmdir(dirname($jalan));
        }

        return $this->delete($id_foto);
    }

    /**
     * Salin foto produk ke orderan yang baru ditulis dari stok.
     *
     * Barang yang dicatat dari katalog sudah punya contoh bentuk di kartu
     * produknya, jadi tidak perlu difoto ulang. Yang masuk ke orderan adalah
     * salinan berkas baru dengan nama acak sendiri, bukan tautan ke foto produk:
     * orderan harus tetap punya fotonya sendiri walaupun produknya diganti atau
     * dihapus nanti.
     *
     * Hanya baris yang bertaut stok yang jadi sumber; baris manual memang tidak
     * punya foto. Dipanggil setelah transaksi save commit, jadi orderan yang
     * gagal validasi tidak meninggalkan berkas di disk.
     *
     * @return int banyak foto yang berhasil disalin
     */
    public function salin_dari_produk(int $invoice_id, int $user_id): int
    {
        if ($invoice_id < 1) {
            return 0;
        }

        // orderan yang fotonya sudah penuh tidak diberi salinan lagi
        $sisa = self::MAKS_FOTO - $this->jumlah($invoice_id);

        if ($sisa < 1) {
            return 0;
        }

        $tautan = $this->db->table('dibeli')
            ->select('stok_id')
            ->where('invoice_id', $invoice_id)
            ->where('stok_id IS NOT NULL')
            ->get()
            ->getResultArray();

        $stok_ids = array_values(array_unique(array_filter(array_map('intval', array_column($tautan, 'stok_id')))));

        if ($stok_ids === []) {
            return 0;
        }

        // foto memang menempel di satu varian, tapi kartu katalog menampilkannya
        // untuk sekartu, jadi ikut cara kartu itu dibaca supaya ukurannya tidak
        // harus sama dengan yang ditulis di orderan
        $stok   = new StokModel();
        $varian = [];

        foreach ($this->db->table('stok')->select('juragan_id, kode')->whereIn('id_stok', $stok_ids)->get()->getResultArray() as $kartu) {
            foreach ($stok->sekartu((int) $kartu['juragan_id'], (string) $kartu['kode']) as $id) {
                $varian[$id] = $id;
            }
        }

        if ($varian === []) {
            return 0;
        }

        // banyak() sengaja tidak membawa kolom berkas, jadi barisnya dibaca lagi
        $sumber = (new ProdukFotoModel())
            ->select('berkas, nama_asli')
            ->whereIn('stok_id', array_values($varian))
            ->orderBy('id_foto', 'ASC')
            ->limit($sisa)
            ->get()
            ->getResultArray();

        if ($sumber === []) {
            return 0;
        }

        $folder = static::arah($invoice_id);

        if ($folder === null) {
            return 0;
        }

        $disalin = 0;

        foreach ($sumber as $b) {
            $asal = self::jalur((string) ($b['berkas'] ?? ''));

            if ($asal === null) {
                continue;
            }

            $isi = @file_get_contents($asal);

            if ($isi === false || $isi === '') {
                continue;
            }

            // berkas produk sudah bersih dari muatan tersembunyi, tapi tipe dan
            // ukuran tetap dibaca ulang dari isinya, bukan dari kolom tabel
            $kepala = @getimagesizefromstring($isi);

            if ($kepala === false || ! isset(self::TIPE[$kepala['mime']])) {
                continue;
            }

            [$tipe, $ekstensi] = self::TIPE[$kepala['mime']];
            $nama               = bin2hex(random_bytes(8)) . '.' . $ekstensi;
            $jalan              = $folder . DIRECTORY_SEPARATOR . $nama;

            if (@file_put_contents($jalan, $isi) === false || ! is_file($jalan)) {
                @unlink($jalan);

                continue;
            }

            $id = $this->insert([
                static::kolomAcuan() => $invoice_id,
                'berkas'     => static::relatif($nama, $invoice_id),
                'nama_asli'  => self::namaTampil((string) ($b['nama_asli'] ?? '')),
                'tipe'       => $tipe,
                'byte'       => (int) filesize($jalan),
                'lebar'      => (int) $kepala[0],
                'tinggi'     => (int) $kepala[1],
                'user_id'    => $user_id,
            ]);

            if ($id === false) {
                @unlink($jalan);

                continue;
            }

            $disalin++;
        }

        return $disalin;
    }

    /**
     * Lokasi asli berkas di disk, atau null kalau jalur di tabel tidak bisa
     * dipercaya (berkas hilang / jalurnya mencoba keluar dari folder uploads).
     */
    public static function jalur(string $berkas): ?string
    {
        $akar = realpath(WRITEPATH . 'uploads');

        if ($akar === false) {
            return null;
        }

        $nyata = realpath($akar . DIRECTORY_SEPARATOR . str_replace('\\', '/', $berkas));

        if ($nyata === false || ! str_starts_with($nyata, $akar . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $nyata;
    }

    /** Folder penyimpanan satu pemilik foto, dibuat kalau belum ada. */
    public static function arah(int $pemilik): ?string
    {
        $dasar = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . static::folder();

        if (! is_dir($dasar) && ! @mkdir($dasar, 0775, true) && ! is_dir($dasar)) {
            return null;
        }

        $baru = $dasar . DIRECTORY_SEPARATOR . $pemilik;

        if (! is_dir($baru) && ! @mkdir($baru, 0775) && ! is_dir($baru)) {
            return null;
        }

        return $baru;
    }

    /**
     * Foto HP sering tersimpan miring dan arah aslinya hanya ada di EXIF.
     * Karena EXIF dibuang saat berkas ditulis ulang, arahnya dibereskan lebih
     * dulu. Angka arah mengikuti standar EXIF (1 = sudah benar).
     */
    private static function perbaikiOrientasi(\GdImage $gambar, string $jalan, string $tipe): \GdImage
    {
        if ($tipe !== 'jpeg' || ! function_exists('exif_read_data')) {
            return $gambar;
        }

        $exif = @exif_read_data($jalan);
        $arah = (int) ($exif['Orientation'] ?? 1);

        if ($arah < 2 || $arah > 8) {
            return $gambar;
        }

        // imagerotate memutar ke kiri, jadi 270 = memutar 90 derajat ke kanan.
        // Sisi panjang ikut tertukar, jadi gambar lama dibuang setelah jadi.
        if (in_array($arah, [5, 6, 7, 8], true)) {
            $hasil = imagerotate($gambar, in_array($arah, [5, 6], true) ? 270 : 90, 0);

            if (! $hasil instanceof \GdImage) {
                return $gambar;
            }

            if ($arah === 5 || $arah === 7) {
                imageflip($hasil, IMG_FLIP_HORIZONTAL);
            }

            imagedestroy($gambar);

            return $hasil;
        }

        if ($arah === 3) {
            $hasil = imagerotate($gambar, 180, 0);

            if ($hasil instanceof \GdImage) {
                imagedestroy($gambar);

                return $hasil;
            }

            return $gambar;
        }

        imageflip($gambar, $arah === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);

        return $gambar;
    }

    /** Nama berkas di disk selalu relatif terhadap writable/uploads/. */
    protected static function relatif(string $nama, int $pemilik): string
    {
        return static::folder() . '/' . $pemilik . '/' . $nama;
    }

    /** Nama asli cuma untuk label, jadi dipangkas dan dibuang karakter aneh. */
    private static function namaTampil(string $nama): string
    {
        $nama = trim(preg_replace('/[^\P{C}\n]+/u', '', $nama) ?? '');

        return $nama === '' ? 'foto' : mb_substr($nama, 0, 150);
    }

    /**
     * Kanvas baru berukuran paling panjang $sisi. Semua format dilewatkan sini
     * supaya hasil akhirnya selalu bersih dari muatan lama.
     */
    private static function perkecil($gambar, int $lebar, int $tinggi, string $tipe, int $sisi = self::MAKS_SISI): ?\GdImage
    {
        $kali = min(1, $sisi / max($lebar, $tinggi));
        $baru_lebar  = max(1, (int) round($lebar * $kali));
        $baru_tinggi = max(1, (int) round($tinggi * $kali));

        $kanvas = imagecreatetruecolor($baru_lebar, $baru_tinggi);

        if ($kanvas === false) {
            return null;
        }

        if ($tipe !== 'jpeg') {
            // PNG/WebP bisa tembus pandang; latar transparan itu dipertahankan
            imagealphablending($kanvas, false);
            imagesavealpha($kanvas, true);
            imagefill($kanvas, 0, 0, imagecolorallocatealpha($kanvas, 0, 0, 0, 127));
        }

        if (! imagecopyresampled($kanvas, $gambar, 0, 0, 0, 0, $baru_lebar, $baru_tinggi, $lebar, $tinggi)) {
            imagedestroy($kanvas);

            return null;
        }

        imagedestroy($gambar);

        return $kanvas;
    }
}
