<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Sementara: cocokkan setiap URL yang dibangun kode terhadap rute terdaftar.
 *
 * Dipakai untuk mencari tautan yang berujung 404 (mis. admin/tugas/lihat/1 padahal
 * rutenya admin/tugas/(:num)). Setelah audit selesai file ini dihapus.
 *
 * Pakai: php spark rute:audit [--verb=get] [--lebar]
 */
class RuteAudit extends BaseCommand
{
    protected $group       = 'Helper';
    protected $name        = 'rute:audit';
    protected $description = 'Mencocokkan URL yang dibangun kode dengan rute terdaftar untuk mencari tautan 404.';
    protected $usage       = 'rute:audit [--verb=get] [--lebar]';
    protected $options     = [
        '--verb'  => 'Hanya periksa kandidat untuk satu metode (get|post)',
        '--lebar' => 'Tampilkan juga kandidat yang cocok samar (prefix)',
    ];

    /** Pola yang menghasilkan URL internal. */
    private const POLA = [
        'get'  => [
            '#\bsite_url\s*\(\s*[\'"]([^\'"]+)[\'"]#',
            '#\banchor\s*\(\s*[\'"]([^\'"]+)[\'"]#',
            '#\bredirect\(\)\s*->\s*to\s*\(\s*site_url\s*\(\s*[\'"]([^\'"]+)[\'"]#',
            '#\bredirect\(\)\s*->\s*to\s*\(\s*[\'"]([^\'"]+)[\'"]#',
            '#\b\$\.(?:get|getJSON)\(\s*[\'"]([^\'"]+)[\'"]#',
            '#window\.location\.href\s*=\s*[\'"]([^\'"]+)[\'"]#',
            '#location\.href\s*=\s*[\'"]([^\'"]+)[\'"]#',
        ],
        'post' => [
            '#\bform_open(?:_multipart)?\s*\(\s*[\'"]([^\'"]+)[\'"]#',
            '#\b\$\.(?:post|put|delete)\(\s*[\'"]([^\'"]+)[\'"]#',
            '#\bajax\s*\(\s*[\'"]([^\'"]+)[\'"]#',
        ],
    ];

    /** Bukan rute: tautan luar, jangkar, dan aset statis. */
    private const DIAMKAN = [
        'http://', 'https://', 'mailto:', 'tel:', 'javascript:', '#', '//', 'assets/',
    ];

    public function run(array $params)
    {
        $hanya = strtolower((string) ($this->opsi($params, 'verb') ?? ''));
        $lebar = (bool) $this->opsi($params, 'lebar');
        $rute  = $this->rute();

        CLI::write('Rute terdaftar: ' . $this->jumlahRute($rute) . ' (get+post, autoRoute mati)', 'cyan');

        $rusak = [];
        $samar = [];
        $ok    = 0;

        foreach ($this->kandidat() as $k) {
            if ($hanya !== '' && $k['verb'] !== $hanya) {
                continue;
            }

            $hasil = $this->cocok($k['path'], $k['verb'], $rute);

            if ($hasil === 'ok') {
                $ok++;
            } elseif ($hasil === 'samar') {
                $samar[] = $k;
            } else {
                $rusak[] = $k;
            }
        }

        CLI::write('Cocok pasti   : ' . $ok, 'green');
        CLI::write('Cocok samar    : ' . count($samar) . ' (ujung dinamis, tidak dipastikan)');
        CLI::write('TIDAK ADA RUTE : ' . count($rusak), $rusak === [] ? 'green' : 'red');
        CLI::newLine();

        foreach ($rusak as $r) {
            CLI::write(sprintf('%-6s %-52s %s:%d', strtoupper($r['verb']), $r['path'], $r['file'], $r['line']), 'red');
        }

        if ($lebar && $samar !== []) {
            CLI::newLine();
            CLI::write('Cocok samar (periksa manual kalau perlu):', 'cyan');

            foreach ($samar as $s) {
                CLI::write(sprintf('%-6s %-52s %s:%d', strtoupper($s['verb']), $s['path'], $s['file'], $s['line']));
            }
        }

        return 0;
    }

    private function opsi(array $params, string $nama)
    {
        foreach ($params as $kunci => $nilai) {
            $tekun = is_string($kunci) ? $kunci : $nilai;

            if (! is_string($tekun)) {
                continue;
            }

            $pecah = explode('=', ltrim($tekun, '-'), 2);

            if ($pecah[0] === $nama) {
                return $pecah[1] ?? true;
            }
        }

        return null;
    }

    /** @return array<string, list<array{segm: list<string>, asli: string}>> */
    private function rute(): array
    {
        $kol = service('routes');
        $isi = ['get' => [], 'post' => []];

        // RouteCollection menyimpan verb dengan huruf besar ('GET', 'POST'), jadi
        // kunci kecil akan mengembalikan nol. getRoutes() tanpa argumen juga
        // mengembalikan daftar datar untuk verb sesi, bukan per verb.
        foreach (['get' => 'GET', 'post' => 'POST'] as $kecil => $besar) {
            foreach ($kol->getRoutes($besar, false) as $uri => $handler) {
                $isi[$kecil][] = $this->ubahRute((string) $uri);
            }
        }

        foreach ($kol->getRoutes('*', false) as $uri => $handler) {
            $sama        = $this->ubahRute((string) $uri);
            $isi['get'][]  = $sama;
            $isi['post'][] = $sama;
        }

        return $isi;
    }

    private function jumlahRute(array $rute): int
    {
        return count($rute['get'] ?? []) + count($rute['post'] ?? []);
    }

    /**
     * Pola rute jadi bahan yang aman dibandingkan.
     *
     * Placeholder CI diganti regex, lalu tiap kelompok regex diringkas jadi token
     * '@' supaya jumlah segmen bisa dihitung tanpa memecah "([^/]+)" di tengah
     * karakter kelas — memecah pola mentah dengan explode('/') menghasilkan regex
     * rusak seperti "#^([^$".
     *
     * @return array{regex: string, segm: list<string>, asli: string}
     */
    private function ubahRute(string $pola): array
    {
        $regex = str_replace(
            ['(:num)', '(:any)', '(:segment)', '(:alpha)', '(:uri)', '(:hash)'],
            ['[0-9]+', '[^/]+', '[^/]+', '[a-z]+', '.+', '[^/]+'],
            $pola
        );

        $token = (string) preg_replace('#\([^()]*\)#', '@', $regex);

        return ['regex' => $regex, 'segm' => $this->pecah($token), 'asli' => $pola];
    }

    /** @return list<string> */
    private function pecah(string $uri): array
    {
        $bersih = trim((string) preg_replace('~[?#].*$~', '', $uri), '/');

        return $bersih === '' ? [] : explode('/', strtolower($bersih));
    }

    /** Satu segmen rute (regex atau token '@') lawan satu segmen URL. */
    private function segmenCocok(string $rute, string $url): bool
    {
        if ($rute === '@') {
            return true;
        }

        if (str_contains($rute, '(') || str_contains($rute, '[')) {
            // pola tidak valid: anggap teks biasa, jangan jatuhkan seluruh audit
            if (@preg_match('#^' . $rute . '$#i', $url) === false) {
                return strtolower($rute) === $url;
            }

            return (bool) preg_match('#^' . $rute . '$#i', $url);
        }

        return strtolower($rute) === $url;
    }

    /**
     * @return 'ok'|'samar'|'rusak'
     *
     * 'samar' menampung dua hal: URL kandidat berhenti sebelum polanya selesai
     * (ujungnya dinamis, mis. site_url('admin/tugas') lalu '/' . $id) dan rute yang
     * ternyata hanya ada di metode lain. Yang kedua tetap perlu dilihat, karena
     * CI membalasnya sebagai 404 juga.
     */
    private function cocok(string $path, string $verb, array $rute): string
    {
        $calon = trim((string) preg_replace('~[?#].*$~', '', $path), '/');
        $segm  = $this->pecah($calon);
        $samar = false;

        foreach (['get', 'post'] as $uji) {
            foreach ($rute[$uji] ?? [] as $r) {
                $pan = $r['segm'];

                if (count($pan) === count($segm)) {
                    if (@preg_match('#^' . $r['regex'] . '/?$#i', $calon) !== 1) {
                        continue;
                    }

                    if ($uji === $verb) {
                        return 'ok';
                    }

                    $samar = true;

                    continue;
                }

                if (count($pan) <= count($segm)) {
                    continue;
                }

                $pasang = true;

                foreach ($segm as $i => $s) {
                    if (! $this->segmenCocok($pan[$i], $s)) {
                        $pasang = false;

                        break;
                    }
                }

                if ($pasang) {
                    $samar = true;
                }
            }
        }

        return $samar ? 'samar' : 'rusak';
    }

    /** @return list<array{file: string, line: int, verb: string, path: string}> */
    private function kandidat(): array
    {
        $berkas = [];

        foreach (['app/Views', 'app/Controllers', 'app/Helpers', 'app/Models', 'app/Libraries', 'app/Filters'] as $folder) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(ROOTPATH . $folder, \FilesystemIterator::SKIP_DOTS));

            foreach ($it as $f) {
                if ($f->isFile() && $f->getExtension() === 'php') {
                    $berkas[] = str_replace('\\', '/', $f->getPathname());
                }
            }
        }

        foreach (glob(ROOTPATH . 'public/assets/js/*.js') ?: [] as $f) {
            $berkas[] = str_replace('\\', '/', $f);
        }

        $hasil = [];

        foreach ($berkas as $b) {
            $baris = file($b) ?: [];

            foreach ($baris as $i => $teks) {
                foreach (self::POLA as $verb => $pola) {
                    foreach ($pola as $p) {
                        if (! preg_match_all($p, $teks, $m, PREG_OFFSET_CAPTURE)) {
                            continue;
                        }

                        foreach ($m[1] as $c) {
                            $item = $this->bersihkan((string) $c[0]);

                            if ($item === null) {
                                continue;
                            }

                            // beberapa pola bisa mengenai teks yang sama (site_url di
                            // dalam redirect), jadi kunci unik yang disimpan
                            $kunci = $b . '|' . ($i + 1) . '|' . $verb . '|' . $item;

                            $hasil[$kunci] = [
                                'file' => str_replace(ROOTPATH, '', $b),
                                'line' => $i + 1,
                                'verb' => $verb,
                                'path' => $item,
                            ];
                        }
                    }
                }
            }
        }

        return array_values($hasil);
    }

    private function bersihkan(string $raw): ?string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        // berhenti di bagian dinamis: tag buka PHP, kurung kurawal, atau variabel
        $potong = preg_split('#[<][$?][=]?|[$]|[{|[]#', $raw);
        $raw    = rtrim((string) $potong[0], '/?&= ');

        if ($raw === '') {
            return null;
        }

        foreach (self::DIAMKAN as $lalu) {
            if (str_starts_with(strtolower($raw), $lalu)) {
                return null;
            }
        }

        foreach (['.css', '.js', '.png', '.jpg', '.ico', '.json', '.csv', '.xlsx', '.woff'] as $buntut) {
            if (str_ends_with(strtolower($raw), $buntut)) {
                return null;
            }
        }

        return $raw;
    }
}
