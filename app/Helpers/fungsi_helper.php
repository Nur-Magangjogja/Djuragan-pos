<?php

use CodeIgniter\I18n\Time;

if (! function_exists('formatRibuan')) {
    /**
     * Format angka pendek
     * misal: 10K, 20M
     *
     * see: https://code.recuweb.com/2018/php-format-numbers-to-nearest-thousands/
     *
     * @param int $num angka yang akan diformat
     *
     * @return string hasil angka pendek berupa 10K, 20M
     */
    function formatRibuan(int $num): string
    {
        if ($num > 1000) {
            $x               = round($num);
            $x_number_format = number_format($x);
            $x_array         = explode(',', $x_number_format);
            $x_parts         = ['k', 'm', 'b', 't'];
            $x_count_parts   = count($x_array) - 1;
            $x_display       = $x;
            $x_display       = $x_array[0] . ((int) $x_array[1][0] !== 0 ? '.' . $x_array[1][0] : '');
            $x_display .= $x_parts[$x_count_parts - 1];

            return strtoupper($x_display);
        }

        return $num;
    }
}

if (! function_exists('base_user')) {
    function base_user()
    {
        $session = \Config\Services::session();

        switch ($session->get('level')) {
            case 'superadmin':
            case 'admin':
                $base = 'admin';
                break;

            default:
                $base = 'user'; //$session->get('level');
                break;
        }

        return $base;
    }
}

if (! function_exists('random_element')) {
    /**
     * Random Element - Takes an array as input and returns a random element
     *
     * @param	array
     * @param mixed $array
     *
     * @return mixed depends on what the array contains
     */
    function random_element($array)
    {
        return is_array($array) ? $array[array_rand($array)] : $array;
    }
}

if (! function_exists('label_asal')) {
    /**
     * Menampilkan label asal order
     */
    function label_asal(?int $id, ?string $label = null): ?string
    {
        $labelModel = new \App\Models\LabelAsal();
        $get        = $labelModel->find($id);

        return strtoupper($get->label) . ($label !== null ? '&nbsp;<span class="d-block text-muted">' . $label . '</span>' : '');
    }
}

if (! function_exists('penanggung_jawab')) {
    /**
     * Nama penanggung jawab untuk ditampilkan di detail orderan. Dulu pengguna
     * mengetik penuh "Dilayani Oleh: <nama>" di kolom bebas, jadi prefiksnya bisa
     * sudah ikut tersimpan; di sini prefiks itu dilepas supaya tampilan bisa
     * memasang labelnya sendiri tanpa berlipat. Tidak ada penanggung jawab =>
     * string kosong, dan pemanggil menyembunyikan barisnya.
     */
    function penanggung_jawab(?string $label): string
    {
        $nama = trim((string) $label);

        if (mb_stripos($nama, 'dilayani oleh') === 0) {
            $nama = ltrim(mb_substr($nama, mb_strlen('dilayani oleh')), " \t\n\r\0\x0B:");
        }

        return trim($nama);
    }
}

if (! function_exists('logo_bank')) {
    function logo_bank($nama_bank)
    {
        switch ($nama_bank) {
            case 'bca':
                return '<svg style="width: 100px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 500"><defs/><defs><clipPath id="a"><path d="M0 0h1000v500H0z"/></clipPath></defs><g clip-path="url(#a)"><rect width="1000" height="500" fill="#0c377f" rx="50"/><path fill="#fff" d="M170 337l-2-2c-14-30-20-60-20-89v-4c0-30 7-60 20-89l2-2c30-15 61-23 94-23h1v8h-1q-46 0-89 22a209 209 0 000 173q45 19 90 19v8c-32 0-63-7-95-21zm95 21v-8h2c31 0 62-6 93-20q20-62 11-125a261 261 0 00-11-48q-49-21-95-21v-8q48 0 100 23l1 1 1 1v1l7 25 5 25a278 278 0 013 33v13a290 290 0 01-15 84l-1 2-1 1c-33 14-65 21-98 21zm-9-17v-17h7v3h-3v5h3v3h-3v6zm22-17l7-1v3h-3v4h2a3 3 0 001 0v3h-3l1 5h2v3h-6zm7 17v-3h1a15 15 0 002 0 2 2 0 002-1 3 3 0 000-2 3 3 0 000-1 2 2 0 00-2-1 18 18 0 00-3 0v-3h2a3 3 0 001-1 1 1 0 001-1 2 2 0 00-1-2 2 2 0 00-1 0 13 13 0 00-2 0v-3a15 15 0 013 0 4 4 0 012 1 4 4 0 012 1 5 5 0 010 2 4 4 0 010 2 4 4 0 01-2 2 5 5 0 014 4 8 8 0 01-1 2 6 6 0 01-1 2 5 5 0 01-2 1l-5 1zm-40 0a10 10 0 01-4-1 8 8 0 01-2-2 6 6 0 010-2 40 40 0 010-5l1-9h3l-1 10v3a3 3 0 001 2 3 3 0 002 1 3 3 0 003-1 3 3 0 001-1v-3l1-10h4l-1 9a15 15 0 01-1 5 5 5 0 01-1 2 5 5 0 01-2 1 6 6 0 01-2 1h-2zm-13-2l-2-5a4 4 0 00-1-1v-2a9 9 0 00-1-1 5 5 0 00-2 0h-1l-1 7-4-1 3-17 6 1h2a12 12 0 014 1 4 4 0 012 3 5 5 0 010 2 5 5 0 01-2 3 6 6 0 01-4 1 8 8 0 012 2 15 15 0 011 4l2 3zm-3-6v-5 5zm2-5a2 2 0 002-1 3 3 0 000-1 2 2 0 000-2h-1l-3-1v-3 3h-3v4h2l1 1a8 8 0 002 0zm67 9a10 10 0 01-3-6 10 10 0 011-7 8 8 0 015-4 8 8 0 016 1 6 6 0 012 3l-3 2a3 3 0 00-2-2 4 4 0 00-2-1 5 5 0 00-3 2 7 7 0 00-1 5l3 5a4 4 0 003 1 4 4 0 002-2 6 6 0 001-3l4 1a9 9 0 01-2 4 8 8 0 01-5 3 10 10 0 01-1 0 7 7 0 01-5-2zm17-19l4-1 1 1v6l-2-3-1 7h3v3h-3v5l-4 1zm-52 14a3 3 0 001-1 2 2 0 002 0 3 3 0 000-2 2 2 0 00-1-1 2 2 0 00-1-1h-1v-3a10 10 0 013 0 4 4 0 013 2 5 5 0 011 3 7 7 0 01-1 3 10 10 0 01-2 2 14 14 0 01-2 1 21 21 0 01-2 0zm-53 2a10 10 0 01-5-3 8 8 0 01-2-4 11 11 0 011-4 11 11 0 012-5 7 7 0 014-2 10 10 0 019 3 6 6 0 011 4h-3a4 4 0 00-1-3 5 5 0 00-3-1 5 5 0 00-3 0 6 6 0 00-3 4 7 7 0 000 5 5 5 0 003 3 7 7 0 003 0 8 8 0 002-1v-2l-4-1 1-3 8 2-2 7a7 7 0 01-4 1h-1a13 13 0 01-3 0zm114-4l-4 1v-3l2-1-2-3v-6l10 14-4 1zm245-51a84 84 0 010-18 99 99 0 016-25c29-71 73-70 99-68a108 108 0 0013 0l3-9 18 10 18 10-20 47c-35-25-86-2-89 24-3 27 18 49 71 32l-18 49a204 204 0 01-23 1c-53 0-74-25-78-53zm227 51v-32h-42l-11 31h-52l17-38 39-84h-8l22-42h77l14 165zm0-64v-58l-15 29-14 29h29zm-380 63l28-122-10 1 23-45 49 1 29 1c20 0 31 11 34 26a38 38 0 010 13 49 49 0 01-25 36c36 35 0 80-38 88v-41c11-7 17-31 0-34v-25h7c16 0 18-32 5-32h-24l-8 32h20v25a19 19 0 00-4 0h-21l-10 37h29a11 11 0 006-3v41a53 53 0 01-10 1zm-187-6zm31-82c-6-19-30-31-30-52-1-17 12-37 36-37 23 0 37 17 38 35 2 21-23 35-30 55-7 21-5 44-7 65-2-22-1-45-7-66zm20 7c6-18 18-34 37-39 13-4 32 12 29 27-3 12-15 27-35 29-9 0-13 8-19 12 26-2 43-13 54-23 2 9 3 24-4 29-8 6-16 6-24 5-6 0-12 0-19 2-9 2-15 8-23 12-1-19-1-37 4-54zm-45 42c-7-2-13-2-19-2-8 1-16 1-24-5-7-5-6-20-5-29 12 10 29 21 55 23-6-4-10-12-19-12-20-2-32-17-35-29-3-15 16-31 29-27 19 5 31 21 37 39 5 17 5 35 4 54-9-4-14-10-23-12z"/></g></svg>';
                break;

            case 'bri':
                return '<svg style="width:100px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 500"><defs><clipPath id="a"><path d="M0 0h1000v500H0z"/></clipPath></defs><g clip-path="url(#a)"><rect width="1000" height="500" fill="#17479e" rx="50"/><path fill="#fff" fill-rule="evenodd" d="M119 182a27 27 0 0127-27h136a27 27 0 0127 27v136a27 27 0 01-27 27H146a28 28 0 01-27-27zm33 144h14c18 0 38-22 21-39l-35-37 41-40c14-14-2-36-24-36h-17a15 15 0 00-14 14v124a15 15 0 0014 14zm53-152c12 3 24 25 7 43l-32 33 28 29c18 19 7 47-10 47h79l-75-76 41-40c14-14-3-36-24-36h-14zm85 138V188a15 15 0 00-14-14h-21c12 3 25 25 7 43l-32 33 60 62zm74-55v19h8c4 0 8-1 10-3a8 8 0 003-7 8 8 0 00-3-7l-9-2h-9zm0-32v18h6c5 0 8-1 10-3 2-1 4-3 4-7a8 8 0 00-4-6c-2-2-5-3-9-3h-7v1zm-21-15h33c9 0 17 2 22 6 5 3 7 8 7 15a18 18 0 01-4 12c-2 3-6 5-11 7a22 22 0 0113 6c3 3 4 8 4 13 0 7-3 13-8 16-5 4-14 6-25 6h-31v-81zm110 18l-10 30h20l-10-30zm-13-18h26l31 81h-23l-6-17h-31l-6 17h-21l30-81zm62 0h27l23 49 3 7a102 102 0 013 10l-1-11a79 79 0 01-1-9v-46h20v81h-27l-24-50a67 67 0 01-3-8l-3-8 2 13v53h-19v-81zm89 0h22v35l25-35h24l-30 37 33 44h-27l-25-37v37h-22v-81zm136 47v19h9c4 0 7-1 10-3a8 8 0 003-7 8 8 0 00-3-7l-9-2h-10zm0-32v18h7c4 0 8-1 10-3 2-1 3-3 3-7a8 8 0 00-3-6c-2-2-5-3-9-3h-8v1zm-21-15h33c10 0 17 2 22 6 5 3 7 8 7 15a18 18 0 01-3 12c-3 3-7 5-12 7a22 22 0 0113 6c3 3 5 8 5 13 0 7-3 13-9 16-5 4-13 6-25 6h-31v-81zm98 14v21h8c5 0 8-1 11-3s3-4 3-7c0-4-1-6-4-8s-7-3-12-3zm-21-14h32c11 0 19 2 24 5s8 9 8 16a21 21 0 01-4 13c-3 4-7 6-12 7q7 2 11 13l9 27h-22l-7-22q-2-6-5-8l-9-2h-4v32h-21v-81zm77 0h21v81h-21v-81z"/></g></svg>';
                break;

            case 'bni':
                return '<svg style="width:100px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 500"><defs><clipPath id="a"><path d="M0 0h1000v500H0z"/></clipPath></defs><g clip-path="url(#a)"><rect width="1000" height="500" fill="#f15a23" rx="50"/><path fill="#fff" d="M277 343l15-13s-8 5-28-8c-11-8-29-32-29-32l21-18s20 27 44 33c30 9 44-12 44-12v50zm-107 0l45-38 31 38zm-24-125l60 75-60 50zm146 46c-10-9-20-21-20-41 0-14 12-33 35-33 17 0 37 17 37 17v74a31 31 0 01-15 5c-15 0-27-11-37-22zm-130-67l-16-19v-32h65s-16 28 7 72c11 23 29 43 29 43l-21 17zm73-19c-5-23 2-32 2-32h107v28s-36-19-67 13c-15 14-17 40-17 40s-20-25-25-49z"/><path fill="#fff" fill-rule="evenodd" d="M572 148h47s42 63 62 90l43 55V162c0-5-15-14-15-14h55s-18 7-18 14v191s-17-9-37-32c-22-25-99-128-99-128v133c0 7 15 17 15 17h-53s14-10 14-17V162c0-6-14-14-14-14zm222 0h61s-17 8-17 14v164c0 7 17 17 17 17h-61s15-10 15-17V162c0-6-15-14-15-14zm-400 0s15 8 15 14v164c0 7-15 17-15 17h89c6 0 69-10 69-59s-49-53-49-53 30-8 30-41c0-36-44-42-50-42h-89zm47 80v-61h37c5 0 25 7 25 31 0 20-20 30-25 30h-37zm0 16h42c6 0 39 9 39 37 0 30-33 40-39 40h-42v-77z"/></g></svg>';
                break;

            case 'mandiri':
                return '<svg style="width:100px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 500"><defs><linearGradient id="b" x2="1" y1="4.7" y2="-3.6" gradientUnits="objectBoundingBox"><stop offset="0" stop-color="#f6ab00"/><stop offset=".2" stop-color="#fff100"/><stop offset=".4" stop-color="#f6ab00"/><stop offset=".7" stop-color="#fff100"/><stop offset=".8" stop-color="#f6ab00"/><stop offset="1" stop-color="#fff100"/></linearGradient><clipPath id="a"><path d="M0 0h1000v500H0z"/></clipPath></defs><g clip-path="url(#a)"><rect width="1000" height="500" fill="#005bac" rx="50"/><path fill="#fff" fill-rule="evenodd" d="M679.7 264.7h25.8v89h-25.9v-89zm-63.7 89v-61.4a377 377 0 00-1-27.6h23.7l1 16a24.4 24.4 0 0110.7-12.5c5-2.8 11-4.2 18.3-4.2h.8v23.4l-3.6-.3a18.5 18.5 0 00-2 0c-6.9 0-12.2 1.6-16 4.9a16.9 16.9 0 00-5.7 13.6v48.1zm-39.2-89h26v89h-26v-89zm-42 89L534 341a33 33 0 01-11.5 10.9 32.2 32.2 0 01-15.3 3.6 32.1 32.1 0 01-26.3-12.7 52.5 52.5 0 01-10-33.5c0-14 3.2-25.2 9.9-33.7A31.8 31.8 0 01507 263a33 33 0 0115.4 3.5 31 31 0 0111.4 10.4v-51h25.7v109.9l.2 11 .6 7h-25.4zm-37.7-44.4c0 9.2 1.6 16.4 4.7 21.4s7.6 7.6 13.4 7.6c5.8 0 10.5-2.6 13.8-7.8a38 38 0 005.1-21.2c0-9-1.7-16.1-5-21.2-3.5-5.2-8.1-7.8-14-7.8-5.7 0-10 2.6-13.3 7.7a40 40 0 00-4.7 21.3zm-122.4 44.4v-72c0-2.5 0-5.2-.2-8l-.6-9h24.6l.6 13.3c3.3-5.4 7.3-9.3 11.9-11.8s10.2-3.7 16.8-3.7c9.8 0 17.1 2.8 21.9 8.4s7.1 14 7.1 25.5v57.3h-25.5V300q0-10-3.4-14.3c-2.3-3-6-4.3-11.1-4.3-5 0-9 1.6-12 4.8-3.1 3.2-4.6 7.4-4.6 12.8v54.8zm-245.4 0v-72l-.2-9-.5-8H153l.7 13.2c3.4-5.4 7.3-9.3 12-11.8s10.2-3.7 16.8-3.7c6.7 0 12.3 1.5 17 4.5a26.4 26.4 0 0110.1 13q4.4-9 11.4-13.3t17.8-4.2c9.8 0 17 2.8 21.8 8.4s7.2 14 7.2 25.5v57.3H242v-53.8q0-10-3.4-14.3c-2.3-2.9-6-4.3-11.1-4.3-5 0-9 1.6-12 4.8-3 3.2-4.4 7.5-4.4 12.8v54.8h-25.7v-53.8c0-6.6-1.2-11.3-3.4-14.3-2.2-2.9-5.9-4.4-10.9-4.4s-9 1.6-11.8 4.8-4.4 7.4-4.4 12.8v54.7h-25.7v.3zm208.1-9.3a61.3 61.3 0 01-22.3 10.9 38.5 38.5 0 01-8.8.9 25.1 25.1 0 01-18.7-7.9 28 28 0 01-7.6-20c0-6.6 1.3-12 3.8-16.1a26.3 26.3 0 0111-9.8 57.9 57.9 0 0117.4-5.1 269 269 0 0121.5-2.3v-.5c0-21.2-30.4-18.6-43.3-9.5l-4.5-16.8a72.9 72.9 0 0132.5-7.1c21.8 0 40.2 10.4 40.2 35.4v57.4h-21.1v-9.5h-.1zm-2-17.4v-17.2c-4 .3-8.2.9-12.8 1.5a50.7 50.7 0 00-10.4 2.3 13.7 13.7 0 00-6.7 4.6 12.2 12.2 0 00-2.2 7.8c-.1 22.6 26.2 15.3 32 1z"/><path fill="url(#b)" fill-rule="evenodd" d="M325.8 64.8c8 8.9 22.8 32.9 40.2 31.4 41-3.5 64.3-47.8 115-44.6 20.8 1.3 30.6 36.2 51.1 39.2 32.5 4.7 63.7-45.3 116.7-42.4 21.8 1.2 30.8 37.8 52.5 40 20.2 1.8 52-26.8 79.7-41.6-7-12-20.3-30.7-34.3-31.6-27.5-2-49.7 33.3-74 27.1-14-3.6-30.2-42.4-53.5-42.3-43.1.2-72.9 42.9-111 46.2-21.6 1.9-32-46.2-61-41.8-39.6 6.1-80.1 38.7-121.4 60.4z" transform="translate(90.2 143.2)"/></g></svg>';
                break;

            default:
                return 'NOLOGO';
                break;
        }
    }
}

if (! function_exists('first_letter')) {
    /**
     * First Letter - Get the first letter of string
     *
     * @param	string
     * @param mixed $words
     * @param mixed $length
     *
     * @return string
     */
    function first_letter($words, $length = 2)
    {
        $r = array_slice(explode('-', url_title($words . ' ' . random_string('alpha', 6))), 0, $length);

        if (count($r) > 1) {
            $c = '';

            foreach ($r as $f) {
                $c .= mb_substr($f, 0, 1);
            }

            return $c;
        }

        return mb_substr($words, 0, $length);
    }
}

if (! function_exists('timeline_langkah')) {
    /**
     * Satu langkah di timeline .timeliner. $label pendek biar terbaca tanpa
     * hover, $title lengkap tetap jadi tooltip.
     */
    function timeline_langkah($class, $ikon, $label, $tanggal, $title)
    {
        $tgl = $tanggal instanceof Time
            ? '<abbr title="' . esc($tanggal->humanize()) . '">' . esc($tanggal->day . '/' . $tanggal->month) . '</abbr>'
            : '&mdash;';

        $html = '<li class="list-inline-item me-0 position-relative ' . $class . '" data-bs-toggle="tooltip" data-bs-placement="top" title="' . esc($title) . '">';
        $html .= '<div class="flex flex-col items-center text-center">';
        $html .= '<i class="fal fa-' . $ikon . ' icon"></i>';
        $html .= '<span class="tl-label">' . esc($label) . '</span>';
        $html .= '<span class="tl-date">' . $tgl . '</span>';
        $html .= '</div></li>';

        return $html;
    }
}

if (! function_exists('tahap_produksi')) {
    /**
     * Peta tahap produksi: nomor => [ikon, label pendek, kata kerja].
     * Satu-satunya sumber nama tahap; kartu transaksi dan nota cetak pakainya ini.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    function tahap_produksi(): array
    {
        return [
            1 => ['file-alt', 'Data', 'orderan'],
            2 => ['layer-group', 'Bahan', 'bahan'],
            3 => ['print', 'Sablon', 'sablon'],
            4 => ['waveform-path fa-rotate-90', 'Bordir', 'bordir'],
            5 => ['cut fa-rotate-270', 'Jahit', 'penjahit'],
            6 => ['tasks', 'QC', 'QC'],
            7 => ['box-alt', 'Packing', 'packing'],
        ];
    }
}

if (! function_exists('label_status_orderan')) {
    /**
     * Nama tab orderan untuk invoice.status_pesanan.
     */
    function label_status_orderan($status): string
    {
        $label = [
            '1' => 'Belum diproses',
            '2' => 'Sedang/sudah diproses',
            '3' => 'Dibatalkan',
        ];

        return $label[(string) $status] ?? '-';
    }
}

if (! function_exists('tahap_terakhir')) {
    /**
     * Tahap produksi tertinggi yang sudah dicatat, baris ganda per tahap digabung
     * seperti timeline kartu (masuk paling awal, selesai paling akhir).
     *
     * @param iterable|null $status baris invoice_status
     * @return array{no: int, nama: string, tanggal: string, selesai: bool}|null
     */
    function tahap_terakhir($status): ?array
    {
        $gabung = [];

        foreach ((array) $status as $baris) {
            $b = (array) $baris;
            $n = (int) ($b['status'] ?? 0);

            if ($n <= 0 || ($b['tanggal_masuk'] ?? null) === null) {
                continue;
            }

            $masuk   = (int) $b['tanggal_masuk'];
            $selesai = isset($b['tanggal_selesai']) ? $b['tanggal_selesai'] : null;

            if (! isset($gabung[$n])) {
                $gabung[$n] = ['masuk' => $masuk, 'selesai' => null];
            } else {
                $gabung[$n]['masuk'] = min($gabung[$n]['masuk'], $masuk);
            }

            if ($selesai !== null) {
                $gabung[$n]['selesai'] = $selesai === null
                    ? (int) $selesai
                    : max($gabung[$n]['selesai'], (int) $selesai);
            }
        }

        if ($gabung === []) {
            return null;
        }

        $n = max(array_keys($gabung));

        return [
            'no'      => $n,
            'nama'    => tahap_produksi()[$n][1] ?? 'Tahap',
            'tanggal' => tanggal_rincian(date('Y-m-d', $gabung[$n]['selesai'] ?? $gabung[$n]['masuk'])),
            'selesai' => $gabung[$n]['selesai'] !== null,
        ];
    }
}

if (! function_exists('status_orderan')) {
    function status_orderan($status, $mulai, $selesai, $keterangan_mulai, $keterangan_selesai)
    {
        $tahap = tahap_produksi();

        [$ico, $label, $apa] = $tahap[(int) $status] ?? ['circle', 'Tahap', 'orderan'];

        $class = '';
        $time  = null;

        if ($mulai !== null && $selesai !== null) {
            $class = 'full';
            $title = 'Sudah selesai ' . $apa;
            $time  = Time::createFromTimestamp($selesai);

            if ($keterangan_selesai !== 'null' && $keterangan_selesai !== null) {
                $title .= ': ' . $keterangan_selesai;
            }
        } elseif ($mulai !== null) {
            $class = 'half';
            $title = 'Masih berjalan ' . $apa;
            $time  = Time::createFromTimestamp($mulai);

            if ($keterangan_mulai !== 'null' && $keterangan_mulai !== null) {
                $title .= ': ' . $keterangan_mulai;
            }
        } else {
            $title = 'Belum ada catatan ' . $apa;
        }

        return timeline_langkah($class, $ico, $label, $time, $title);
    }
}

if (! function_exists('alasan_batal')) {
    /**
     * Alasan sebuah orderan dihapus/VOID. Kunci array ini yang disimpan di
     * order_invoice.alasan_batal, jadi nilainya tidak boleh berubah seenaknya.
     *
     * 'jenis' membedakan kesalahan operasional dari pembatalan yang sah: KPI
     * Error/Void Rate hanya menghitung 'kesalahan'. Membatalkan karena pelanggan
     * tidak lanjut itu kerja yang benar, bukan error, jadi tidak boleh membuat
     * angka karyawan jelek tanpa bukti.
     */
    function alasan_batal(): array
    {
        return [
            'salah_input' => [
                'label' => 'Salah input barang / ukuran',
                'jenis' => 'kesalahan',
            ],
            'salah_harga' => [
                'label' => 'Salah harga atau jumlah',
                'jenis' => 'kesalahan',
            ],
            'duplikat' => [
                'label' => 'Nota duplikat',
                'jenis' => 'kesalahan',
            ],
            'salah_lainnya' => [
                'label' => 'Lainnya',
                'jenis' => 'kesalahan',
            ],
            'pelanggan_batal' => [
                'label' => 'Pelanggan membatalkan',
                'jenis' => 'sah',
            ],
            'tidak_lanjut' => [
                'label' => 'Tidak jadi order / pembayaran tidak masuk',
                'jenis' => 'sah',
            ],
            'lainnya' => [
                'label' => 'Lainnya',
                'jenis' => 'sah',
            ],
        ];
    }
}

if (! function_exists('kategori_pembayaran')) {
    /**
     * Kelompok orderan untuk tab Pembayaran, kunci = slug kategori.
     *
     * Urutan array ini juga dipakai sebagai urutan chip, jadi yang mendesak
     * (butuh dicek pembayarannya) ditaruh paling depan. 'status' berisi nilai
     * invoice.status_pembayaran yang termasuk, sesuai InvoiceModel::perbaruiStatusPembayaran():
     *   1 belum ada pembayaran, 2 transfer belum dicek (belum ada dana masuk),
     *   3 transfer belum dicek (sudah ada dana masuk), 4 dicicil, 5 lunas lebih, 6 lunas.
     */
    function kategori_pembayaran(): array
    {
        return [
            'perlu-cek' => [
                'label'    => 'Perlu Dicek',
                'ikon'     => 'fa-clock',
                'mendesak' => true,
                'status'   => ['2', '3'],
                'catatan'  => 'Orderan yang perlu diperiksa dulu: ada transfer yang belum dikonfirmasi.',
            ],
            'dp' => [
                'label'    => 'DP Menunggu Konfirmasi',
                'ikon'     => 'fa-money-check-edit',
                'mendesak' => true,
                'status'   => ['3'],
                'catatan'  => 'Sudah ada dana yang masuk dan terkonfirmasi, tapi masih ada transfer lain yang belum dicek.',
            ],
            'menunggu' => [
                'label'    => 'Menunggu Pembayaran',
                'ikon'     => 'fa-hourglass-half',
                'mendesak' => true,
                'status'   => ['2'],
                'catatan'  => 'Katanya sudah transfer, tapi belum ada satu pun dana yang berhasil dicek.',
            ],
            'belum' => [
                'label'    => 'Belum Bayar',
                'ikon'     => 'fa-wallet',
                'mendesak' => false,
                'status'   => ['0', '1'],
                'catatan'  => 'Belum ada pembayaran sama sekali dan tidak ada yang perlu dicek.',
            ],
            'dicicil' => [
                'label'    => 'Bayar Dicicil',
                'ikon'     => 'fa-coins',
                'mendesak' => false,
                'status'   => ['4'],
                'catatan'  => 'Sebagian sudah dibayar dan sudah dicek, sisanya masih kurang.',
            ],
            'lunas' => [
                'label'    => 'Lunas',
                'ikon'     => 'fa-check-circle',
                'mendesak' => false,
                'status'   => ['5', '6'],
                'catatan'  => 'Sudah terbayar penuh (termasuk yang ada kelebihan pembayaran).',
            ],
        ];
    }
}

if (! function_exists('kategori_untuk_status_bayar')) {
    /**
     * Chip tab Pembayaran yang memuat nota dengan status_pembayaran ini.
     *
     * Dipakai setelah pembayaran dicatat: arahnya harus mengikuti status hasil
     * hitungan, karena pembayaran terakhir membuat nota jadi lunas dan tidak lagi
     * ikut chip "Perlu Dicek" — mendarat di chip yang salah berarti daftar kosong.
     */
    function kategori_untuk_status_bayar(string $status): string
    {
        foreach (kategori_pembayaran() as $slug => $kategori) {
            if (in_array($status, $kategori['status'], true)) {
                return $slug;
            }
        }

        return 'perlu-cek';
    }
}

if (! function_exists('saring_transaksi')) {
    /**
     * Saringan orderan untuk dasbor.
     *
     * Satu-satunya tempat definisi "aktif", "siap diambil", "deadline terlewat"
     * dan kawan-kawannya. Dasbor mengambil angkanya lewat InvoiceModel dengan
     * slug ini, jadi angka di kartu selalu sama dengan isi halaman yang dibuka
     * saat kartunya diklik.
     *
     * 'tautan' = [segmen tab, segmen kategori] tujuan klik.
     */
    function saring_transaksi(): array
    {
        return [
            'pesan-hari-ini' => [
                'label'    => 'Order hari ini',
                'ikon'     => 'fa-file-alt',
                'mendesak' => false,
                'catatan'  => 'Orderan yang ditulis pada tanggal hari ini.',
                'tautan'   => ['saring', 'pesan-hari-ini'],
            ],
            'aktif' => [
                'label'    => 'Order aktif',
                'ikon'     => 'fa-box-alt',
                'mendesak' => false,
                'catatan'  => 'Belum selesai dikerjakan atau belum diambil pelanggan.',
                'tautan'   => ['saring', 'aktif'],
            ],
            'belum-lunas' => [
                'label'    => 'Belum lunas',
                'ikon'     => 'fa-wallet',
                'mendesak' => true,
                'catatan'  => 'Tagihan yang belum terbayar penuh.',
                'tautan'   => ['saring', 'belum-lunas'],
            ],
            'produksi' => [
                'label'    => 'Sedang produksi',
                'ikon'     => 'fa-tasks',
                'mendesak' => false,
                'catatan'  => 'Sudah masuk antrean kerja tapi belum diambil.',
                'tautan'   => ['dalam-proses', ''],
            ],
            'belum-diproses' => [
                'label'    => 'Belum diproses',
                'ikon'     => 'fa-inbox-in',
                'mendesak' => true,
                'catatan'  => 'Orderan yang belum masuk antrean kerja sama sekali.',
                'tautan'   => ['belum-proses', 'semua'],
            ],
            'siap-diambil' => [
                'label'    => 'Siap diambil',
                'ikon'     => 'fa-shipping-fast',
                'mendesak' => false,
                'catatan'  => 'Packing sudah selesai, menunggu pelanggan mengambil.',
                'tautan'   => ['saring', 'siap-diambil'],
            ],
            'ukuran-belum-lengkap' => [
                'label'    => 'Ukuran belum lengkap',
                'ikon'     => 'fa-ruler-combined',
                'mendesak' => true,
                'catatan'  => 'Orderan rombongan yang belum ada satu pun ukuran terisi.',
                'tautan'   => ['saring', 'ukuran-belum-lengkap'],
            ],
            'deadline-hari-ini' => [
                'label'    => 'Deadline hari ini',
                'ikon'     => 'fa-ballot-check',
                'mendesak' => true,
                'catatan'  => 'Jatuh tempo hari ini dan belum diambil.',
                'tautan'   => ['saring', 'deadline-hari-ini'],
            ],
            'deadline-besok' => [
                'label'    => 'Deadline besok',
                'ikon'     => 'fa-ballot-check',
                'mendesak' => true,
                'catatan'  => 'Jatuh tempo besok dan belum diambil.',
                'tautan'   => ['saring', 'deadline-besok'],
            ],
            'deadline-3-hari' => [
                'label'    => 'Deadline dekat',
                'ikon'     => 'fa-ballot-check',
                'mendesak' => true,
                'catatan'  => 'Jatuh tempo dalam tiga hari ke depan, hari ini termasuk.',
                'tautan'   => ['saring', 'deadline-3-hari'],
            ],
            'deadline-terlewat' => [
                'label'    => 'Deadline terlewat',
                'ikon'     => 'fa-print',
                'mendesak' => true,
                'catatan'  => 'Lewat dari tanggal janji dan belum diambil.',
                'tautan'   => ['saring', 'deadline-terlewat'],
            ],
        ];
    }
}

if (! function_exists('kategori_tab')) {
    /**
     * Chip filter untuk satu tab: kategori pembayaran, atau daftar saringan
     * orderan pada tab 'saring' yang dipakai dasbor.
     *
     * Tab pembayaran membuka 'perlu-cek' lebih dulu, sedangkan tab belum-proses
     * memakai alur lengkap dari belum bayar sampai lunas (plus 'semua' sebagai
     * pilihan awal, statusnya dikosongkan artinya "tanpa filter").
     */
    function kategori_tab(string $hal): array
    {
        $daftar = kategori_pembayaran();

        if ($hal === 'saring') {
            return saring_transaksi();
        }

        if ($hal !== 'belum-proses') {
            return $daftar;
        }

        $pilihan = [
            'semua' => [
                'label'    => 'Semua',
                'ikon'     => 'fa-inbox',
                'mendesak' => false,
                'status'   => [],
                'catatan'  => 'Semua orderan yang belum masuk proses, dari yang belum bayar sampai yang sudah lunas.',
            ],
        ];

        foreach (['belum', 'menunggu', 'dp', 'dicicil', 'lunas'] as $slug) {
            $pilihan[$slug] = $daftar[$slug];
        }

        return $pilihan;
    }
}

if (! function_exists('laporan_jenis')) {
    /**
     * Jenis laporan di halaman Laporan. Satu daftar ini yang dibaca controller
     * (whitelist segmen), view (tab) dan model (cara menghitung), jadi nama
     * laporan tidak bisa bercabang.
     *
     * 'uang' menandai laporan yang menampilkan angka uang; view pakai penanda itu
     * untuk memutuskan perlu menulis dua kolom uang atau tidak. 'sumbu' menandai
     * laporan yang barisnya dikelompokkan menurut waktu, jadi pilihan periode hanya
     * muncul di laporan yang memang memakainya.
     */
    function laporan_jenis(): array
    {
        return [
            'pesanan' => [
                'label'   => 'Laporan Pesanan',
                'ikon'    => 'fa-file-alt',
                'catatan' => 'Orderan masuk dan nilainya; barisnya dikelompokkan sendiri mengikuti panjang rentang tanggal.',
                'uang'    => true,
                'sumbu'   => true,
            ],
            'pendapatan' => [
                'label'   => 'Laporan Pendapatan',
                'ikon'    => 'fa-wallet',
                'catatan' => 'Nilai orderan dibanding dana yang benar-benar sudah masuk; barisnya ikut panjang rentang tanggal.',
                'uang'    => true,
                'sumbu'   => true,
            ],
            'pembayaran' => [
                'label'   => 'Laporan Pembayaran',
                'ikon'    => 'fa-money-check-edit',
                'catatan' => 'Semua catatan pembayaran pada tanggal transfer, berikut status diceknya.',
                'uang'    => true,
            ],
            'piutang' => [
                'label'   => 'Laporan Piutang',
                'ikon'    => 'fa-inbox-in',
                'catatan' => 'Orderan yang belum lunas, paling besar lebih dulu, lengkap dengan umur tunggakan.',
                'uang'    => false,
            ],
            'produksi' => [
                'label'   => 'Laporan Produksi',
                'ikon'    => 'fa-tasks',
                'catatan' => 'Orderan berhenti di tahap mana, plus lama kerja dari pesan sampai tahap terakhir.',
                'uang'    => false,
            ],
            'produk' => [
                'label'   => 'Laporan Produk',
                'ikon'    => 'fa-box-alt',
                'catatan' => 'Produk dengan nilai dan jumlah penjualan terbesar pada rentang ini.',
                'uang'    => false,
            ],
            'pelanggan' => [
                'label'   => 'Laporan Customer',
                'ikon'    => 'fa-user',
                'catatan' => 'Pelanggan dengan orderan dan nilai terbesar; sisa bayarnya ikut terlihat.',
                'uang'    => false,
            ],
        ];
    }
}

if (! function_exists('label_bayar_cek')) {
    /**
     * Status pengecekan satu baris pembayaran (order_pembayaran.status):
     * 1 belum dicek, 2 dana tidak ada, 3 dana ada.
     */
    function label_bayar_cek($status): array
    {
        return match ((int) $status) {
            2       => ['teks' => 'Dana tidak ada', 'kelas' => 'danger'],
            3       => ['teks' => 'Sudah dicek', 'kelas' => 'success'],
            default => ['teks' => 'Belum dicek', 'kelas' => 'warning'],
        };
    }
}

if (! function_exists('tagihan_lunas')) {
    // patokan tunggal "lunas": status 5 (lunas, ada kelebihan) dan 6 (lunas).
    // dipakai view untuk menyembunyikan tombol Tambah Pembayaran dan controller untuk menolaknya.
    function tagihan_lunas($status_pembayaran)
    {
        return in_array((int) $status_pembayaran, [5, 6], true);
    }
}

if (! function_exists('status_pembayaran')) {
    function status_pembayaran($pembayaran, $status, $return = 'html')
    {
        switch ($status) {
            case 2: // tunggu konfirmasi (belum bayar)
                $class   = '';
                $l_class = 'danger';
                $title   = 'Belum ada pembayaran (cek)';
                $pendek  = 'Menunggu pembayaran';
                break;

            case 3: // tunggu konfirmasi (kredit)
                $class   = 'half';
                $l_class = 'warning';
                $title   = 'Sebagian sudah dibayar (cek)';
                $pendek  = 'DP menunggu konfirmasi';
                break;

            case 4: // kredit
                $class   = 'half';
                $l_class = 'warning';
                $title   = 'Sebagian sudah dibayar';
                $pendek  = 'Pembayaran dicicil';
                break;

            case 5: // kelebihan
                $class   = 'full';
                $l_class = 'primary';
                $title   = 'Pembayaran lunas, ada kelebihan';
                $pendek  = 'Lunas, ada kelebihan';
                break;

            case 6: // lunas
                $class   = 'full';
                $l_class = 'success';
                $title   = 'Pembayaran sudah Lunas';
                $pendek  = 'Lunas';
                break;

            default: // belum bayar
                $class   = '';
                $l_class = 'danger';
                $title   = 'Belum ada pembayaran';
                $pendek  = 'Belum dibayar';
                break;
        }

        $sudah_bayar   = 0;
        $menunggu      = 0;
        $tanggal_bayar = '';

        foreach ($pembayaran as $pay) {
            if ($pay->status === '3') {
                $sudah_bayar += $pay->nominal;
                $tanggal_bayar = Time::createFromTimestamp($pay->tanggal_bayar);
            }
            if ($pay->status === '1') {
                $menunggu += $pay->nominal;
            }
        }

        $html = timeline_langkah($class, 'wallet', 'Bayar', $tanggal_bayar, $title);

        if ($return === 'html') {
            return $html;
        }
        if ($return === 'sudah_bayar') {
            return $sudah_bayar;
        }
        if ($return === 'menunggu') {
            return $menunggu;
        }
        if ($return === 'l_class') {
            return $l_class;
        }
        if ($return === 'label') {
            return $pendek;
        }
    }
}

if (! function_exists('status_pengiriman')) {
    function status_pengiriman($pengiriman, $status)
    {
        switch ($status) {
            case '2': // dikirim sebagian
                $class = 'half';
                $title = 'Sebagian sudah dikirim';
                break;

            case '3': // dikirim semua
                $class = 'full';
                $title = 'Semua pesanan sudah dikirim';
                break;

            default: // belum kirim
                $class = '';
                $title = 'Belum dikirim';
                break;
        }

        $tanggal_kirim = '';

        foreach ($pengiriman as $kirim) {
            $tanggal_kirim = Time::createFromTimestamp($kirim->tanggal_kirim);
        }

        return timeline_langkah($class . ' end', 'shipping-fast', 'Kirim', $tanggal_kirim, $title);
    }
}

if (! function_exists('replacer')) {
    // source : https://stackoverflow.com/a/48981341/2094645
    function replacer($template, $data)
    {
        if (preg_match_all('/{(.*?)}/', $template, $m)) {
            foreach ($m[1] as $i => $varname) {
                $template = str_replace($m[0][$i], sprintf('%s', $data[$varname]), $template);
            }
        }

        return $template;
    }
}

if (! function_exists('crm_isi_token')) {
    /**
     * Isi token {nama}, {invoice}, {total}, ... pada pesan WhatsApp CRM.
     *
     * Beda dengan replacer(): token yang tidak punya data dibiarkan utuh,
     * supaya penulis pesan melihat bagian mana yang belum terisi alih-alih
     * mengirim pesan berhiaskan spasi kosong ke pelanggan.
     */
    function crm_isi_token($pesan, array $data)
    {
        return preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($m) use ($data) {
            $kunci = strtolower($m[1]);

            if (! array_key_exists($kunci, $data) || $data[$kunci] === null || $data[$kunci] === '') {
                return $m[0];
            }

            return (string) $data[$kunci];
        }, (string) $pesan);
    }
}

if (! function_exists('pengguna_sesi')) {
    /**
     * ID akun yang sedang login, null kalau tidak ada.
     *
     * Kunci session yang diisi Auth.php saat login itu 'id'. Beberapa tempat lama
     * membaca 'user_id' yang tidak pernah ada lalu jatuh ke nilai cadangan 1,
     * sehingga aksi orang tercatat atas nama akun pertama. Modul KPI mengukur
     * orang, jadi pembacaan session dikumpulkan di sini supaya tidakulangan
     * kesalahan yang sama tidak muncul lagi.
     */
    function pengguna_sesi(): ?int
    {
        $id = (int) (\Config\Services::session()->get('id') ?? 0);

        return $id > 0 ? $id : null;
    }
}

if (! function_exists('simpan_notif')) {
    /**
     * Siapa yang diberi tahu sebuah aksi. Bukan semua orang yang tertaut ke
     * tokonya, tapi yang memang berwenang menindaklanjuti:
     *
     *   admin mengubah      -> CS + superadmin
     *   CS mengubah         -> admin + superadmin
     *   superadmin mengubah -> admin + CS
     *
     * Level di luar itu (viewer) dan aksi tanpa sesi (mis. webhook) jatuh ke
     * aturan lama: semua yang tertaut, kecuali pembuatnya sendiri.
     */
    function simpan_notif($type, $juragan_id, $invoice_id)
    {
        $juragan    = new \App\Models\JuraganModel();
        $notifikasi = new \App\Models\NotifikasiModel();
        $session    = \Config\Services::session();

        $user_id = $session->get('id');
        $level   = (string) $session->get('level');

        $petaPenerima = [
            'admin'      => ['cs', 'superadmin'],
            'cs'         => ['admin', 'superadmin'],
            'superadmin' => ['admin', 'cs'],
        ];
        $boleh = $petaPenerima[$level] ?? null;

        $r_users = $juragan->getUsersByJuragan($juragan_id)->getResult();

        foreach ($r_users as $v) {
            // Pembuat aksi tidak dikirimi notifikasi atas aksinya sendiri. Kedua
            // sisi dibandingkan sebagai int karena kolom id dari MySQLi datang
            // sebagai string sedangkan session()->get('id') bisa int.
            if ((int) $v->id === (int) $user_id) {
                continue;
            }

            if ($boleh !== null && ! in_array((string) $v->level, $boleh, true)) {
                continue;
            }

            $notifikasi->save([
                'juragan_id' => $juragan_id,
                'from'       => $user_id,
                'for'        => $v->id,
                'invoice_id' => $invoice_id,
                'type'       => $type,
                'created_at' => Time::now(config('App')->appTimezone)->timestamp,
            ]);
        }
    }
}

if (! function_exists('tanggalDefault')) {
    function tanggalDefault($akhir_mulai)
    {
        $time = new Time('now');

        $default_tanggal_mulai = 26;
        $default_tanggal_akhir = 25;
        $setting               = '1';

        $hari_ini      = $time->toLocalizedString('yyyy-MM-dd');
        $bulan_ini     = $time->toLocalizedString('yyyy-MM');
        $datestring    = $hari_ini . ' first day of last month';
        $dt            = date_create($datestring);
        $datestring2   = $hari_ini . ' first day of next month';
        $dt2           = date_create($datestring2);
        $bulan_kemarin = $dt->format('Y-m');
        $bulan_besok   = $dt2->format('Y-m');

        $sekarang        = strtotime($hari_ini);
        $mulai_bulan_ini = strtotime($bulan_ini . '-' . $default_tanggal_mulai);
        // $mulai_bulan_kemarin = strtotime($bulan_kemarin . '-' . $default_tanggal_mulai);

        $akhir_bulan_ini = strtotime($bulan_ini . '-' . $default_tanggal_akhir);
        // $akhir_bulan_besok = strtotime($bulan_besok . '-' . $default_tanggal_akhir);
        if ($setting === '1') {
            if ($sekarang >= $mulai_bulan_ini) {
                $tanggal_mulai = $bulan_ini . '-' . $default_tanggal_mulai;
            } elseif ($sekarang <= $mulai_bulan_ini) {
                $tanggal_mulai = $bulan_kemarin . '-' . $default_tanggal_mulai;
            }

            if ($sekarang <= $akhir_bulan_ini) {
                $tanggal_akhir = $bulan_ini . '-' . $default_tanggal_akhir;
            } elseif ($sekarang >= $akhir_bulan_ini) {
                $tanggal_akhir = $bulan_besok . '-' . $default_tanggal_akhir;
            }
        } else {
            $tanggal_mulai = $time->toLocalizedString('yyyy-MM-01');
            $tanggal_akhir = date('Y-m-t', now());
        }
        if ($akhir_mulai === 'mulai') {
            return $tanggal_mulai;
        }
        if ($akhir_mulai === 'akhir') {
            return $tanggal_akhir;
        }
    }
}

if (! function_exists('satuBulanSebelumnya')) {
    function satuBulanSebelumnya($tanggal)
    {
        return date('Y-m-d', strtotime('-1 month', strtotime($tanggal)));
    }
}

if (! function_exists('satuBulanSelanjutnya')) {
    function satuBulanSelanjutnya($tanggal)
    {
        return date('Y-m-d', strtotime('+1 month', strtotime($tanggal)));
    }
}

if (! function_exists('base64_encode_url')) {
    /**
     * Membuat string agar binary data dapat ditampilkan di url.
     *
     * @param string $string
     *
     * @see https://www.php.net/manual/en/function.base64-encode.php#123098
     */
    function base64_encode_url($string): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($string));
    }
}

if (! function_exists('base64_decode_url')) {
    /**
     * Mengembalikan binary data dari string yang telah dibuat oleh base64_encode_url.
     *
     * @param string $string
     *
     * @see https://www.php.net/manual/en/function.base64-encode.php#123098
     */
    function base64_decode_url($string): string
    {
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $string), true);
    }
}

if (! function_exists('str_starts_with')) {
    /**
     * Hanya untuk menyesuaikan fungsi pada PHP8
     */
    function str_starts_with(string $haystack, string $needle): bool
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

if (! function_exists('vite')) {
    /**
     * Get the path to a versioned vite file.
     *
     * @param string $path
     * @param string $manifestDirectory
     *
     * @throws \Exception
     *
     * @return string
     */
    function vite($path, $manifestDirectory = 'assets')
    {
        static $manifest;
        $publicFolder = '';
        $rootPath     = FCPATH;
        $publicPath   = $rootPath . $publicFolder;
        if ($manifestDirectory && ! str_starts_with($manifestDirectory, '/')) {
            $manifestDirectory = "/{$manifestDirectory}";
        }

        if (! $manifest) {
            if (! file_exists($manifestPath = ($rootPath . $manifestDirectory . '/manifest.json'))) {
                throw new Exception('The Mix manifest does not exist.');
            }
            $manifest = json_decode(file_get_contents($manifestPath), true);
        }

        $path = $publicFolder . $path;

        if (! array_key_exists($path, $manifest)) {
            throw new Exception(
                "Unable to locate Mix file: {$path}. Please check your " .
                'webpack.mix.js output paths and try again.'
            );
        }

        return file_exists($publicPath . $manifestDirectory)
                   ? base_url($manifestDirectory . '/' . $manifest[$path]['file'])
                   : $manifestDirectory . $manifest[$path]['file'];
    }
}

if (! function_exists('rincian_json')) {
    /**
     * Rangkum inputan rincian dari form Tulis Orderan menjadi JSON untuk kolom
     * `rincian`. Hanya key yang dikenal dan terisi yang disimpan, NULL bila
     * semuanya kosong supaya orderan lama dan orderan tanpa detail tetap polos.
     *
     * @param mixed $rincian
     * @param array $pilihan key => daftar nilai yang boleh, dipakai untuk input
     *                       berpilihan baku seperti tipe pesanan
     */
    function rincian_json($rincian, array $keys, array $pilihan = []): ?string
    {
        if (! is_array($rincian)) {
            return null;
        }

        $isi = [];

        foreach ($keys as $key) {
            $nilai = trim((string) ($rincian[$key] ?? ''));

            if ($nilai === '') {
                continue;
            }

            if (isset($pilihan[$key]) && ! in_array($nilai, $pilihan[$key], true)) {
                continue;
            }

            $isi[$key] = mb_substr($nilai, 0, 1000);
        }

        return $isi === [] ? null : json_encode($isi, JSON_UNESCAPED_UNICODE);
    }
}

if (! function_exists('baca_rincian')) {
    /**
     * Balik isi kolom `rincian` jadi array [key => teks]; data lama (NULL)
     * atau JSON rusak menghasilkan array kosong.
     *
     * @param string|null $json
     */
    function baca_rincian($json): array
    {
        if (trim((string) $json) === '') {
            return [];
        }

        $data = json_decode((string) $json, true);

        if (! is_array($data)) {
            return [];
        }

        return array_filter($data, static fn ($nilai) => trim((string) $nilai) !== '');
    }
}

if (! function_exists('tipe_pesanan')) {
    /**
     * Jenis pesanan. Menentukan inputan jadwal yang muncul di form: deadline
     * untuk pembuatan, tanggal ambil/kembali dan jaminan untuk sewa. Sekalian
     * menentukan kolam stok yang dipakai, jadi label jual ikut disebut.
     */
    function tipe_pesanan(): array
    {
        return ['pembuatan' => 'Pembuatan/Jual', 'sewa' => 'Sewa'];
    }
}

if (! function_exists('meta_rincian')) {
    /**
     * Label dan ikon untuk tiap key rincian, urut sesuai tampilan.
     * Tipe 'pesanan' untuk detail level invoice, 'produk' untuk per item custom.
     */
    function meta_rincian(string $tipe = 'pesanan'): array
    {
        $meta = [
            'pesanan' => [
                'tipe'     => ['Jenis Layanan', 'fa-tag'],
                'orderan'  => ['Kategori Pesanan', 'fa-users'],
                'deadline' => ['Deadline', 'fa-calendar-check'],
                'ambil'    => ['Diambil', 'fa-arrow-down'],
                'kembali'  => ['Kembali', 'fa-arrow-up'],
                'jaminan'  => ['Jaminan', 'fa-shield-halved'],
            ],
            'produk' => [
                'ukuran_jadi'   => ['Ukuran jadi', 'fa-ruler'],
                'pemilik'       => ['Atas nama', 'fa-user'],
                'bahan'         => ['Bahan', 'fa-tshirt'],
                'spesifikasi'   => ['Model & jahitan', 'fa-scissors'],
                'ukuran_detail' => ['Catatan', 'fa-sticky-note'],
            ],
        ];

        return $meta[$tipe] ?? [];
    }
}

if (! function_exists('tanggal_rincian')) {
    /**
     * Tampilin tanggal rincian; kalau formatnya bukan tanggal, teks apa adanya.
     */
    function tanggal_rincian(string $tanggal): string
    {
        try {
            return \CodeIgniter\I18n\Time::parse($tanggal)->toLocalizedString('d MMMM yyyy');
        } catch (Throwable $e) {
            return $tanggal;
        }
    }
}

if (! function_exists('daftar_rincian')) {
    /**
     * Rincian (JSON/string/array) jadi daftar [label, ikon, nilai] yang siap tampil.
     * Baris lama tanpa rincian mengembalikan array kosong, kecuali $dengan_kosong:
     * key yang dikenal tapi belum terisi ikut dibawa dengan nilai null supaya
     * pembedanya bisa menampilkan "-", bukan menghilangkan barisnya.
     *
     * @param string|array|null $rincian
     */
    function daftar_rincian($rincian, string $tipe = 'pesanan', bool $dengan_kosong = false): array
    {
        $data    = is_array($rincian) ? $rincian : baca_rincian($rincian);
        $tanggal = ['deadline', 'ambil', 'kembali'];
        $hasil   = [];

        foreach (meta_rincian($tipe) as $key => $meta) {
            $nilai = isset($data[$key]) ? trim((string) $data[$key]) : '';

            if ($nilai === '') {
                if ($dengan_kosong) {
                    $hasil[] = ['label' => $meta[0], 'ikon' => $meta[1], 'nilai' => null];
                }

                continue;
            }

            if ($key === 'tipe') {
                $nilai = tipe_pesanan()[$nilai] ?? $nilai;
            } elseif (in_array($key, $tanggal, true)) {
                $nilai = tanggal_rincian($nilai);
            }

            $hasil[] = ['label' => $meta[0], 'ikon' => $meta[1], 'nilai' => $nilai];
        }

        return $hasil;
    }
}

if (! function_exists('kunci_produk')) {
    /** Kunci master produk: huruf kecil, spasi dirapatkan. */
    function kunci_produk(string $kode): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower(trim($kode))));
    }
}

if (! function_exists('jamin_produk')) {
    /**
     * Kembalikan id master produk untuk ejaan kode ini, membuat barunya bila
     * belum ada. Kolom `kode` lama di order_dibeli tidak diubah sedikitpun.
     */
    function jamin_produk(string $kode): ?int
    {
        $kunci = kunci_produk($kode);

        if ($kunci === '') {
            return null;
        }

        $db    = \Config\Database::connect();
        $tabel = $db->prefixTable('produk');
        $row   = $db->query('SELECT id_produk FROM `' . $tabel . '` WHERE kunci = ? LIMIT 1', [$kunci])->getFirstRow();

        if ($row !== null) {
            return (int) $row->id_produk;
        }

        $waktu = time();
        $db->query('INSERT INTO `' . $tabel . '` (kode, kunci, created_at, updated_at) VALUES (?, ?, ?, ?)', [
            trim($kode), $kunci, $waktu, $waktu,
        ]);

        return (int) $db->insertID();
    }
}

if (! function_exists('lengkapi_produk')) {
    /** Tambah produk_id pada baris order_dibeli sebelum insertBatch. */
    function lengkapi_produk(array $baris): array
    {
        foreach ($baris as $i => $item) {
            $baris[$i]['produk_id'] = jamin_produk((string) ($item['kode'] ?? ''));
        }

        return $baris;
    }
}

if (! function_exists('ukuran_form_panel')) {
    /**
     * Kerangka panel inputan ukuran untuk satu baris orderan. Isinya (dropdown
     * jenis + kolom angkanya) dibangun assets/js/ukuran-form.js dari atribut
     * data di bawah sini, jadi bentuknya identik di form Tulis, form Sunting,
     * dan modal tambah produk.
     *
     * Panel baris lama dapat $nama (prefix nama input) sehingga angka yang sudah
     * tercatat ikut terkirim terus setiap kali orderan disunting; panel modal
     * tanpa $nama karena kunci barisnya baru ada saat produk ditambahkan.
     *
     * @param array $isi nama, jenis, nilai ([id_komponen => angka]), id
     */
    function ukuran_form_panel(array $isi = []): string
    {
        static $peta = null;

        if ($peta === null) {
            $model  = new \App\Models\UkuranKomponenModel();
            $peta   = [
                'semua'    => array_column($model->findAll(), null, 'id_komponen'),
                'template' => $model->semuaTemplate(),
            ];
        }

        $isi    += ['nama' => '', 'jenis' => null, 'nilai' => [], 'id' => '', 'kelas' => ''];
        $jenis   = (int) $isi['jenis'];
        $template = $jenis > 0 ? $peta['template'][$jenis] ?? [] : [];
        $daftar  = array_merge($peta['template'][0] ?? [], $template);
        $punya   = array_column($daftar, null, 'id_komponen');

        // angka yang tersimpan tapi komponennya sudah tidak masuk template
        // (jenisnya ganti atau komponennya dinonaktifkan) tetap ditampilkan,
        // kalau tidak barisnya ikut terhapus waktu orderan disimpan
        $lebih = [];

        foreach ($isi['nilai'] as $id_komponen => $nilai) {
            if (isset($punya[(int) $id_komponen])) {
                continue;
            }

            $k = $peta['semua'][(int) $id_komponen] ?? null;

            if ($k === null) {
                continue;
            }

            $lebih[] = [
                'id_komponen' => (int) $k['id_komponen'],
                'nama'        => $k['nama'],
                'satuan'      => $k['satuan'],
            ];
        }

        $data = [
            'data-nama'  => $isi['nama'],
            'data-jenis' => $jenis > 0 ? (string) $jenis : '',
            'data-nilai' => json_encode((object) $isi['nilai']),
            'data-lebih' => json_encode($lebih),
        ];

        $attr = [];

        foreach ($data as $k => $v) {
            $attr[] = $k . '="' . esc($v, 'attr') . '"';
        }

        $id = $isi['id'] === '' ? '' : ' id="' . esc($isi['id'], 'attr') . '"';

        // panel baris lama dibuka lewat tombol "ukuran", panel modal selalu tampil
        $sembunyi = $isi['nama'] === '' ? '' : ' d-none';
        $kelas    = trim('ukuran-panel row gx-2 ' . ($isi['kelas'] ?? '') . $sembunyi);

        return '<div class="col-12"><div class="' . esc($kelas, 'attr') . '"' . $id
            . ' ' . implode(' ', $attr) . '></div></div>';
    }
}

if (! function_exists('ukuran_form_data')) {
    /**
     * Peta jenis + komponen untuk JavaScript ukuran-form.js, satu kali ambil
     * per request. Dipakai form Tulis dan form Sunting.
     */
    function ukuran_form_data(): string
    {
        static $json = null;

        if ($json === null) {
            $model = new \App\Models\UkuranKomponenModel();
            $json  = json_encode([
                'jenis'    => (object) $model->jenis(),
                'komponen' => (object) $model->semuaTemplate(),
            ], JSON_UNESCAPED_UNICODE);
        }

        return $json;
    }
}

if (! function_exists('anggota_baris_form')) {
    /**
     * Satu baris anggota rombongan: nama, nomor/tinggi, catatan, plus panel
     * ukuran orang itu (memakai ukuran_form_panel yang sama dengan baris produk).
     *
     * Kunci baris dipakai apa adanya di nama input sehingga form Tulis dan form
     * Sununting mengirim bentuk payload yang identik: anggota[k][nama],
     * anggota[k][nilai_ukuran][id]. Baris cadangan pakai kunci `__K__` yang
     * nanti ditukar JavaScript waktu barisnya ditambah.
     *
     * @param array $isi nama, nomor, catatan, jenis, nilai ([id_komponen => angka])
     */
    function anggota_baris_form(array $isi = [], string $key = ''): string
    {
        $isi += ['nama' => '', 'nomor' => '', 'catatan' => '', 'jenis' => null, 'nilai' => []];

        $teks = static function (string $kolom, string $ulasan, int $maks, string $kelas = '') use ($isi, $key): string {
            return '<input type="text" class="form-control form-control-sm ' . $kelas . '"'
                . ' name="anggota[' . $key . '][' . $kolom . ']"'
                . ' value="' . esc((string) $isi[$kolom], 'attr') . '"'
                . ' placeholder="' . esc($ulasan, 'attr') . '" maxlength="' . $maks . '" autocomplete="off">';
        };

        return '<div class="anggota-baris rounded-kartu mb-2.5 p-2.5 p-sm-3 border border-ink-200 bg-white shadow-sm transition">'
            . '<div class="d-flex align-items-start gap-2.5">'
            . '<span class="anggota-nomor badge rounded-pill bg-ink-900 text-white flex-shrink-0 mt-1 px-2 py-1 text-xs">1</span>'
            . '<div class="flex-grow-1 min-w-0">'
            . '<div class="row gx-2 gy-2 align-items-center">'
            . '<div class="col-12 col-md-5">' . $teks('nama', 'Nama anggota (wajib)', 60, 'anggota-nama fw-semibold') . '</div>'
            . '<div class="col-6 col-md-3">' . $teks('nomor', 'No. / Tinggi (cth: 01 / 170cm)', 30) . '</div>'
            . '<div class="col-6 col-md-4">' . $teks('catatan', 'Catatan khusus...', 255) . '</div>'
            . '</div>'
            . '<div class="ukuran-wrapper mt-2 pt-1 border-top border-ink-100/80">'
            . '<div class="d-flex flex-wrap align-items-center gap-2">'
            . '<button type="button" class="btn btn-xs btn-outline-secondary buka-ukuran rounded-pill px-2.5 py-0.5 text-xs fw-semibold" aria-expanded="false">'
            . '<i class="fal fa-ruler-combined text-primary me-1"></i><span class="teks-buka">Atur Ukuran</span> <i class="fal fa-chevron-down ms-1 ikon-panah transition-transform text-[0.7rem]"></i>'
            . '</button>'
            . '<span class="uk-ringkas badge bg-ink-100 text-ink-700 border border-ink-200 px-2 py-1 text-xs fw-normal d-none"></span>'
            . '</div>'
            . ukuran_form_panel([
                'nama'  => 'anggota[' . $key . ']',
                'jenis' => $isi['jenis'],
                'nilai' => $isi['nilai'],
                'kelas' => 'ukuran-subcard w-100',
            ])
            . '</div>'
            . '</div>'
            . '<button type="button" class="bg-transparent border-0 hapus-anggota p-1 text-danger flex-shrink-0 mt-0.5" aria-label="Hapus anggota" title="Hapus anggota">'
            . '<i class="fal fa-trash-alt"></i>'
            . '</button>'
            . '</div>'
            . '</div>';
    }
}

if (! function_exists('anggota_panel_form')) {
    /**
     * Panel "Anggota Rombongan" di bawah tabel produk, form Tulis dan Sunting.
     *
     * Defaultnya tertutup supaya order satuan hari ini tidak berubah bentuk.
     * Penanda `anggota_dikirim` ditulis PHP, bukan JavaScript: kalau JS gagal
     * dimuat, penandanya tetap ada untuk baris yang sudah tersimpan, sedangkan
     * order yang belum punya anggota sama sekali tidak akan pernah tersentuh.
     *
     * @param array $daftar baris anggota: id_anggota, nama, nomor, catatan, jenis, nilai
     */
    function anggota_panel_form(array $daftar = []): string
    {
        $ada   = $daftar !== [];
        $baris = '';

        foreach ($daftar as $a) {
            $baris .= anggota_baris_form($a, (string) $a['id_anggota']);
        }

        return '<div class="card mb-3 overflow-hidden rounded-kartu border-ink-200 shadow-kartu">'
            . '<div class="card-header border-ink-100 bg-ink-50/60 py-2.5 px-3 px-sm-4">'
            . '<button type="button" class="buka-anggota flex w-full cursor-pointer items-center gap-2 border-0 bg-transparent p-0 text-[0.78rem] font-bold uppercase tracking-[0.08em] text-ink-700" aria-expanded="' . ($ada ? 'true' : 'false') . '">'
            . '<span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-[0.65rem] border border-brand-100 bg-white text-brand-500"><i class="fal fa-users text-xs"></i></span>'
            . '<span>Anggota Rombongan</span>'
            . '<span class="inline-block rounded-full bg-ink-900 px-2 py-0.5 text-xs font-semibold text-white jumlah-anggota">' . count($daftar) . '</span>'
            . '<i class="fal fa-chevron-down ms-auto text-ink-400 ikon-panah-anggota transition-transform' . ($ada ? ' rotate-180' : '') . '"></i>'
            . '</button>'
            . '</div>'
            . '<div class="card-body anggota-isi' . ($ada ? '' : ' d-none') . ' p-3 p-sm-4">'
            . '<input type="hidden" name="anggota_dikirim" value="1">'
            . '<div class="row gx-2">'
            . '<div class="col-12 mb-3">'
            . '<p class="small text-muted mb-0"><i class="fal fa-info-circle me-1 text-primary"></i>Isi nama dan ukuran per orang untuk pesanan rombongan. Order satuan boleh dibiarkan kosong.</p>'
            . '</div>'
            . '<div class="col-12 anggota-daftar">' . $baris . '</div>'
            . '<div class="col-12 mt-2">'
            . '<button type="button" class="tambah-anggota w-100 rounded-kartu bg-white border border-dashed border-ink-200 py-2.5 small fw-semibold text-primary hover:bg-ink-50 transition">'
            . '<i class="fal fa-plus-circle me-1"></i> Tambah Anggota'
            . '</button>'
            . '</div>'
            . '<div class="col-12 mt-3">'
            . '<div class="rounded-[0.95rem] border border-dashed border-[#dbe3ee] bg-ink-50/80 p-3">'
            . '<div class="d-flex align-items-center justify-content-between mb-1.5">'
            . '<label class="form-label text-xs fw-bold text-ink-600 mb-0" for="anggota_tempel">'
            . '<i class="fab fa-whatsapp text-success me-1"></i> Tempel Daftar Nama dari WhatsApp'
            . '</label>'
            . '<span class="text-[0.7rem] text-ink-400">1 baris = 1 nama</span>'
            . '</div>'
            . '<textarea class="form-control form-control-sm bg-white" id="anggota_tempel" rows="2" placeholder="Contoh:&#10;1. A.N DEVI&#10;2. BUDI SANTOSO"></textarea>'
            . '<div class="mt-2 text-end">'
            . '<button type="button" class="btn btn-sm btn-outline-primary tambah-tempelan px-3 py-1 text-xs fw-semibold">'
            . '<i class="fal fa-list-ol me-1"></i> Jadikan Baris Anggota'
            . '</button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="col-12 mt-3">'
            . '<div class="rounded-[0.95rem] border border-dashed border-[#dbe3ee] bg-ink-50/80 p-3">'
            . '<div class="d-flex align-items-center justify-content-between mb-1.5">'
            . '<span class="form-label text-xs fw-bold text-ink-600 mb-0"><i class="fal fa-file-excel text-success me-1"></i> Impor dari Excel / CSV</span>'
            . '<button type="button" class="btn btn-link btn-sm p-0 text-xs fw-semibold unduh-template-anggota"><i class="fal fa-download me-1"></i> Unduh template</button>'
            . '</div>'
            . '<p class="text-[0.7rem] text-ink-400 mb-2">Kolom yang dibaca: <b>Nama</b>, <b>No./Tinggi</b>, <b>Catatan</b>. Baris pertama boleh judul kolom; tiap baris jadi satu anggota.</p>'
            . '<input type="file" id="anggota_file" class="anggota-file d-none" accept=".xlsx,.xls,.csv">'
            . '<label for="anggota_file" class="btn btn-sm btn-outline-primary w-100 text-xs fw-semibold mb-0"><i class="fal fa-upload me-1"></i> Pilih file Excel / CSV</label>'
            . '<div class="anggota-import-info small mt-2 d-none"></div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<template class="anggota-cadang">' . anggota_baris_form([], '__K__') . '</template>'
            . '</div>'
            . '</div>';
    }
}

if (! function_exists('nilai_php_ini')) {
    /**
     * Nilai php.ini yang ditulis shorthand ("8M", "1G", "204800") dalam byte.
     * 0 berarti tidak dibatasi — sama seperti arti "0" di upload_max_filesize.
     */
    function nilai_php_ini(string $kunci): int
    {
        $nilai = trim((string) ini_get($kunci));

        if ($nilai === '') {
            return 0;
        }

        $angka = (int) $nilai;

        return match (strtoupper(substr($nilai, -1))) {
            'G'     => $angka * 1024 * 1024 * 1024,
            'M'     => $angka * 1024 * 1024,
            'K'     => $angka * 1024,
            default => $angka,
        };
    }
}

if (! function_exists('batas_upload')) {
    /**
     * Batas yang benar-benar berlaku di server ini: batas model dipotong
     * `upload_max_filesize` (per berkas) dan `post_max_size` (satu permintaan
     * berisi semua foto sekaligus).
     *
     * Angkanya diserahkan ke peramban lewat data-* panel foto supaya berkas yang
     * pasti ditolak server sudah tertahan sejak awal: orderannya tidak jadi
     * tersimpan, pengguna tinggal menghapus atau mengganti fotonya.
     *
     * @return array{berkas: int, rombongan: int}
     */
    function batas_upload(int $batas_model, int $maks_foto): array
    {
        $per   = nilai_php_ini('upload_max_filesize');
        $total = nilai_php_ini('post_max_size');

        $berkas = $per > 0 ? min($batas_model, $per) : $batas_model;

        // dipakai 95% saja: nama field dan boundary multipart ikut dihitung
        // post_max_size, bukan cuma isi berkasnya
        $rombongan = $total > 0 ? (int) floor($total * 0.95) : $berkas * $maks_foto;

        return ['berkas' => $berkas, 'rombongan' => max($berkas, $rombongan)];
    }
}

if (! function_exists('foto_panel_form')) {
    /**
     * Panel "Foto Orderan" di bawah panel anggota, form Tulis dan form Sunting,
     * dan panel "Foto Produk" di modal katalog barang.
     *
     * Foto sengaja tidak dikirim lewat formulir ini. Berkasnya naik sendiri ke
     * api/foto/unggah setelah orderan tersimpan (lihat public/assets/js/foto-form.js),
     * jadi tidak ada penanda tersembunyi seperti anggota_dikirim: kalau JavaScript
     * gagal dimuat, orderan tetap tersimpan apa adanya dan fotonya memang tidak ikut.
     *
     * Panel produk memakai pipa yang sama tetapi kolom acuannya beda (stok, bukan
     * invoice), jadi alamat API dan kelas fotonya ikut dipilih lewat $sumber.
     *
     * @param array  $sudah      baris foto tersimpan: id_foto, nama_asli
     * @param int    $pemilik    0 = sumbernya belum tersimpan; untuk produk ini id_stok
     * @param string $sumber     'invoice' atau 'produk'
     */
    function foto_panel_form(array $sudah = [], int $pemilik = 0, string $sumber = 'invoice'): string
    {
        $produk  = $sumber === 'produk';
        $model   = $produk ? \App\Models\ProdukFotoModel::class : \App\Models\FotoModel::class;
        $api     = $produk ? 'api/produk-foto' : 'api/foto';
        $judul   = $produk ? 'Foto Produk' : 'Foto Orderan';
        $acuan   = $produk
            ? 'Contoh bentuk barang ini; satu foto terlihat di semua ukuran sekartu.'
            : 'Acuan bentuk dari pelanggan.';
        $petunjuk  = $produk
            ? 'Tekan Simpan setelah semua foto dipilih.'
            : ($pemilik > 0
                ? 'Foto langsung tersimpan begitu dipilih.'
                : 'Fotonya ikut dikirim setelah orderan disimpan.');
        $kartu     = '';
        $anggaran  = batas_upload($model::MAKS_BYTE, $model::MAKS_FOTO);
        $mb_foto   = round($anggaran['berkas'] / 1024 / 1024, 1);

        foreach ($sudah as $f) {
            $alamat = $model::alamat((int) $f['id_foto']);
            $kecil  = $model::alamat((int) $f['id_foto'], 480);
            $nama   = (string) ($f['nama_asli'] ?? '');

            $kartu .= '<div class="col-4 col-sm-3 col-md-2 foto-kartu" data-id="' . (int) $f['id_foto'] . '">'
                . '<div class="foto-kotak">'
                . '<a class="foto-buka" href="' . esc($alamat, 'attr') . '" target="_blank" rel="noopener">'
                . '<img class="foto-gambar" src="' . esc($kecil, 'attr') . '" alt="' . esc($nama, 'attr') . '" loading="lazy">'
                . '</a>'
                . '<button type="button" class="foto-buang" aria-label="Hapus foto" title="Hapus foto"><i class="fal fa-trash-alt"></i></button>'
                . '<span class="foto-tunggu" aria-hidden="true"><i class="fal fa-spinner"></i></span>'
                . '</div>'
                . '<div class="foto-nama small" title="' . esc($nama, 'attr') . '">' . esc($nama) . '</div>'
                . '</div>';
        }

        /* Panelnya selalu terbuka. Dulu isian fotonya tersembunyi di balik kepala
           kartu dan baru muncul setelah panah diklik, padahal memilih foto
           memang langkah utama panel ini; kepala kartunya jadi judul saja. */
        return '<div class="card mb-3 overflow-hidden rounded-kartu border-ink-200 shadow-kartu foto-panel"'
            . ' data-sumber="' . esc($sumber, 'attr') . '"'
            . ' data-pemilik="' . (int) $pemilik . '"'
            . ' data-maks="' . (int) $model::MAKS_FOTO . '"'
            . ' data-batas-byte="' . (int) $anggaran['berkas'] . '"'
            . ' data-batas-rombongan="' . (int) $anggaran['rombongan'] . '"'
            . ' data-unggah="' . esc(site_url($api . '/unggah'), 'attr') . '"'
            . ' data-hapus="' . esc(site_url($api . '/hapus'), 'attr') . '">'
            . '<div class="card-header border-ink-100 bg-ink-50/60 py-2.5 px-3 px-sm-4">'
            . '<div class="flex w-full items-center gap-2 text-[0.78rem] font-bold uppercase tracking-[0.08em] text-ink-700">'
            . '<span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-[0.65rem] border border-brand-100 bg-white text-brand-500"><i class="fal fa-camera text-xs"></i></span>'
            . '<span>' . $judul . '</span>'
            . '<span class="inline-block rounded-full bg-ink-900 px-2 py-0.5 text-xs font-semibold text-white jumlah-foto">' . count($sudah) . '</span>'
            . '</div>'
            . '</div>'
            . '<div class="card-body foto-isi p-3 p-sm-4">'
            . '<p class="small text-muted mb-2"><i class="fal fa-info-circle me-1 text-primary"></i>' . $acuan . ' ' . $petunjuk . ' Maksimum ' . (int) $model::MAKS_FOTO . ' foto JPG, PNG, atau WebP &middot; ' . $mb_foto . ' MB per foto.</p>'
            . '<div class="row g-2 foto-daftar">' . $kartu . '</div>'
            . '<label class="foto-pilih mt-2">'
            . '<input type="file" class="foto-input d-none" multiple accept="image/jpeg,image/png,image/webp">'
            . '<span class="foto-pilih-ikon"><i class="fal fa-image"></i></span>'
            . '<span class="d-block small fw-semibold">Pilih foto atau taruh di sini</span>'
            . '<span class="d-block" style="font-size:0.72rem">Yang besar diperkecil dulu oleh peramban sebelum dikirim.</span>'
            . '</label>'
            . '<div class="foto-galat small text-danger mt-2 d-none"></div>'
            . '</div>'
            . '</div>';
    }
}

if (! function_exists('stok_panel_form')) {
    /**
     * Panel "Ambil dari stok produk" di modal tambah orderan.
     *
     * Dipakai modal yang sama di halaman Tulis admin, Tulis CS, dan Sunting,
     * jadi markupnya satu tempat saja seperti panel foto. Isinya cuma kolom
     * cari + daftar saran + contoh foto; pengisian kolomnya yang dikerjakan
     * public/assets/js/ambil-stok.js.
     *
     * Daftar saran ditampilkan sebagai blok di bawah kolom (bukan layer mengambang)
     * karena ia hidup di dalam modal, yang punya scroll-nya sendiri.
     *
     * tautan API-nya dibacakan JS dari data-cari/data-baca, jadi halaman yang
     * tidak memasang panelnya (mis. halaman lama) tidak memanggil apa pun.
     */
    function stok_panel_form(): string
    {
        return '<div class="stok-panel rounded-[0.85rem] border border-dashed border-[#dbe3ee] bg-ink-50 px-3 pb-3 pt-2 mb-3"'
            . ' data-cari="' . esc(site_url('api/stok/cari'), 'attr') . '"'
            . ' data-baca="' . esc(site_url('api/produk-foto/baca'), 'attr') . '">'
            . '<div class="d-flex flex-wrap align-items-end gap-2">'
            . '<div class="flex-grow-1">'
            . '<label class="form-label text-xs text-ink-500 mb-1" for="stok_cari">Ambil dari stok produk</label>'
            . '<input type="text" class="form-control stok-cari" id="stok_cari" autocomplete="off"'
            . ' placeholder="Ketik nama barang yang dipantau, cth: JAS HITAM">'
            . '<input type="hidden" name="stok_produk" class="stok-id" value="">'
            . '</div>'
            . '<button type="button" class="btn btn-link text-decoration-none stok-batal d-none p-0 small">Tulis manual</button>'
            . '</div>'
            . '<p class="small text-muted mb-0 mt-2">'
            . 'Opsional. Pilih barang yang sudah dipantau stoknya</p>'
            . '<div class="list-group list-group-flush saran-stok mt-2 d-none" style="max-height:13rem;overflow:auto"></div>'
            . '<div class="stok-catatan small mt-2 d-none"></div>'
            . '<div class="foto-acuan mt-2 d-none">'
            . '<div class="small text-muted mb-1"><i class="fal fa-images me-1 text-primary"></i>Contoh bentuk barang ini ikut tersalin ke orderan setelah disimpan.</div>'
            . '<div class="d-flex flex-wrap gap-2 stok-foto-isi"></div>'
            . '</div>'
            . '</div>';
    }
}

if (! function_exists('foto_kisi')) {
    /**
     * Kisi thumbnail foto orderan untuk kartu daftar transaksi.
     *
     * Bloknya mengikuti pola lipatan Produk/Anggota Rombongan: tertutup dulu,
     * dibuka lewat tombolnya, supaya kartu daftar tetap ringkas walau fotonya
     * banyak. Kotaknya minta versi kecil ke server (240 px, 480 px untuk layar
     * rapat) sementara kliknya membuka foto utuh di tab baru. Orderan tanpa
     * foto mengembalikan string kosong, jadi kartunya persis seperti sebelum
     * fitur ini ada.
     *
     * @param array<int, mixed> $foto baris foto dari FotoModel::banyak()
     */
    function foto_kisi(array $foto, int $invoice_id = 0): string
    {
        if ($foto === []) {
            return '';
        }

        // id invoice dipakai sebagai target lipatan; kalau pemanggilnya tidak
        // mengirim id, nomor urut cukup untuk menjaga targetnya tetap unik
        static $urut = 0;
        $target = $invoice_id > 0 ? 'foto-' . $invoice_id : 'foto-x' . (++$urut);

        $kotak  = '';
        $jumlah = 0;

        foreach ($foto as $f) {
            $satu = (object) $f;
            $id   = (int) $satu->id_foto;

            if ($id <= 0) {
                continue;
            }

            $nama = (string) ($satu->nama_asli ?? '');
            $jumlah++;

            // di HP tiga foto sebaris masih lega untuk disentuh, di layar besar
            // enam sebaris supaya kartunya tidak memanjang
            $kotak .= '<div class="col-4 col-sm-3 col-md-3 col-lg-2">'
                . '<a class="d-block text-decoration-none" href="' . esc(\App\Models\FotoModel::alamat($id), 'attr') . '" target="_blank" rel="noopener" title="' . esc($nama, 'attr') . '">'
                . '<span class="foto-kotak">'
                . '<img class="foto-gambar" src="' . esc(\App\Models\FotoModel::alamat($id, 240), 'attr') . '"'
                . ' srcset="' . esc(\App\Models\FotoModel::alamat($id, 240), 'attr') . ' 1x,'
                . ' ' . esc(\App\Models\FotoModel::alamat($id, 480), 'attr') . ' 2x"'
                . ' alt="' . esc($nama, 'attr') . '" loading="lazy">'
                . '</span>'
                . '</a>'
                . '</div>';
        }

        if ($jumlah === 0) {
            return '';
        }

        return '<div class="blok blok-lipat mt-4 overflow-hidden rounded-2xl border border-ink-200 bg-[#fcfdfe]">'
            . '<h6 class="mb-0">'
            . '<button class="flex w-full cursor-pointer items-center gap-1.5 border-0 bg-transparent px-[1.15rem] py-4 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400" type="button"'
            . ' data-bs-toggle="collapse" data-bs-target="#' . $target . '" aria-expanded="false" aria-controls="' . $target . '">'
            . '<i class="fal fa-camera"></i> Foto Orderan'
            . '<span class="inline-block rounded-full bg-ink-900 px-2 py-0.5 text-xs font-semibold text-white">' . $jumlah . '</span>'
            . '<span class="text-[0.8rem] font-semibold normal-case tracking-normal">foto</span>'
            . '<i class="fal fa-chevron-down ms-auto transition rotate-[-90deg] aria-expanded:rotate-0"></i>'
            . '</button>'
            . '</h6>'
            . '<div class="collapse" id="' . $target . '">'
            . '<div class="border-t border-ink-200 px-[1.15rem] py-3">'
            . '<div class="row g-2">' . $kotak . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }
}

if (! function_exists('sinkron_kontak')) {
    /**
     * Pecah kolom JSON order_pelanggan.hp menjadi satu baris per nomor di
     * order_pelanggan_kontak. Sumber aslinya (hp) tetap dipakai aplikasi.
     */
    function sinkron_kontak(int $pelanggan_id, ?string $hp): void
    {
        if ($pelanggan_id <= 0) {
            return;
        }

        $db = \Config\Database::connect();
        $db->query('DELETE FROM `' . $db->prefixTable('pelanggan_kontak') . '` WHERE pelanggan_id = ?', [$pelanggan_id]);

        $nomor = json_decode((string) $hp, true);

        if (! is_array($nomor)) {
            return;
        }

        $waktu = time();
        $baru  = [];
        $urut  = 0;

        foreach ($nomor as $n) {
            $n = trim((string) $n);

            if ($n === '' || isset($baru[$n])) {
                continue;
            }

            $urut++;
            $baru[$n] = ['pelanggan_id' => $pelanggan_id, 'nomor' => $n, 'urutan' => $urut, 'created_at' => $waktu];
        }

        if ($baru !== []) {
            $db->table('pelanggan_kontak')->insertBatch(array_values($baru));
        }
    }
}

if (! function_exists('deadline_iso')) {
    /**
     * Deadline jadi tanggal Y-m-d untuk kolom order_invoice.deadline, supaya
     * bisa diindeks dan dipakai laporan. Inputan lama masih tersimpan di rincian
     * dan tetap dibaca dari sana; teks yang bukan tanggal hanya jadi NULL.
     *
     * @param string|array|null $rincian
     */
    function deadline_iso($rincian): ?string
    {
        $data    = is_array($rincian) ? $rincian : baca_rincian($rincian);
        $tanggal = trim((string) ($data['deadline'] ?? ''));

        if ($tanggal === '') {
            return null;
        }

        $urai = date_parse($tanggal);

        if ($urai === false || $urai['error_count'] > 0 || ! checkdate((int) $urai['month'], (int) $urai['day'], (int) $urai['year'])) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int) $urai['year'], (int) $urai['month'], (int) $urai['day']);
    }
}

if (! function_exists('label_deadline')) {
    /**
     * Deadline jadi [teks, kelas badge]: tanggal yang sudah lewat merah, sisanya
     * hijau, tanpa kata "terlewat" karena warnanya yang bicara. 'kelas' kosong
     * hanya untuk tanggal yang gagal dibaca, itu ditulis polos.
     */
    function label_deadline(?string $deadline): array
    {
        if ($deadline === null || trim($deadline) === '') {
            return ['teks' => '-', 'kelas' => ''];
        }

        try {
            $hari_ini = new DateTimeImmutable('today');
            $tanggal  = new DateTimeImmutable(substr($deadline, 0, 10) . ' 00:00:00');
        } catch (Throwable $e) {
            return ['teks' => tanggal_rincian($deadline), 'kelas' => ''];
        }

        $selisih = (int) $hari_ini->diff($tanggal)->format('%r%a');
        $tertulis = tanggal_rincian($deadline);

        if ($selisih < 0) {
            return ['teks' => $tertulis, 'kelas' => 'danger'];
        }

        if ($selisih === 0) {
            return ['teks' => 'Hari ini', 'kelas' => 'success'];
        }

        if ($selisih === 1) {
            return ['teks' => 'Besok', 'kelas' => 'success'];
        }

        if ($selisih <= 3) {
            return ['teks' => $selisih . ' hari lagi', 'kelas' => 'success'];
        }

        return ['teks' => $tertulis, 'kelas' => 'success'];
    }
}

if (! function_exists('produk_dibeli')) {
    /**
     * Belah payload `produk[...]` dari form orderan jadi dua bagian: baris tabel
     * order_dibeli dan nilai ukuran terstruktur per item.
     *
     * Hanya kolom yang benar-benar ada di order_dibeli yang diambil (kecuali
     * primary key-nya), jadi form tidak bisa menulis kolom karangan maupun
     * memindahkan baris milik orderan lain. `rincian` dan `nilai_ukuran`
     * dikirim sebagai array di dalam tiap item, bukan kolom order_dibeli,
     * jadi keduanya dipisah di sini.
     *
     * @param mixed $produks
     * @return array{baris: array, ukuran: array}
     */
    function produk_dibeli($produks, int $invoice_id): array
    {
        if (! is_array($produks)) {
            return ['baris' => [], 'ukuran' => []];
        }

        $kolom = array_diff(
            \Config\Database::connect()->getFieldNames('dibeli'),
            ['id_beli', 'rincian']
        );

        $baris  = [];
        $ukuran = [];

        foreach ($produks as $k => $v) {
            if (! is_array($v)) {
                continue;
            }

            if (isset($v['nilai_ukuran']) && is_array($v['nilai_ukuran'])) {
                $ukuran[$k] = $v['nilai_ukuran'];
            }

            $siap = [];

            foreach ($v as $p => $d) {
                if (in_array($p, $kolom, true)) {
                    $siap[$p] = $d;
                }
            }

            $siap['invoice_id'] = $invoice_id;

            // rincian item lama jangan ikut hilang kalau kiriman tidak membawa
            // key-nya sama sekali (klien lama / request sebagian), sama seperti
            // aturan rincian invoice di controller. Form cuma mengirim rincian
            // untuk item custom, jadi item yang dipindah ke ukuran standar
            // memang sudah tidak punya rincian.
            if (array_key_exists('rincian', $v)) {
                $siap['rincian'] = rincian_json($v['rincian'], array_keys(meta_rincian('produk')));
            } elseif (($siap['ukuran'] ?? '') !== 'custom') {
                $siap['rincian'] = null;
            }

            $baris[$k] = $siap;
        }

        return ['baris' => $baris, 'ukuran' => $ukuran];
    }
}

if (! function_exists('tautkan_stok')) {
    /**
     * Hanya boleh menautkan baris orderan ke varian stok milik toko sendiri.
     *
     * id_stok dikirim peramban lewat produk[k][stok_id], jadi isinya bisa saja
     * angka toko lain, angka yang tidak ada, atau kosong karena pemanggilnya
     * menulis manual. Tiga aturan yang dijaga di sini:
     *  - tautan yang bukan varian toko ini ditulis NULL, bukan dibiarkan: kalau
     *    tidak, nota yang sedang disunting bisa tetap membawa tautan lama
     *    sementara peramban mengklaim barang lain;
     *  - baris yang tidak mengirim key sama sekali (klien lama, JS form gagal)
     *    tidak disentuh, jadi taunya tetap seperti sebelumnya;
     *  - nilainya selalu angka bulat atau null, jangan sampai string kosong
     *    masuk ke kolom INT dan diam-diam jadi 0.
     *
     * Kolom stok_id memang boleh null: orderan lama dan barang yang ditulis
     * manual tidak punya varian stok.
     *
     * @param array<string|int, array> $baris      hasil produk_dibeli()['baris']
     * @param int                      $juragan_id toko tempat orderan ini ditulis
     *
     * @return array<string|int, array>
     */
    function tautkan_stok(array $baris, int $juragan_id): array
    {
        $dipakai = array_values(array_unique(array_filter(array_map(
            static fn (array $v): int => (int) ($v['stok_id'] ?? 0),
            $baris
        ))));

        $punya = [];

        if ($dipakai !== [] && $juragan_id > 0) {
            $punya = array_map('intval', array_column(
                (new \App\Models\StokModel())
                    ->select('id_stok')
                    ->whereIn('id_stok', $dipakai)
                    ->where('juragan_id', $juragan_id)
                    ->findAll(),
                'id_stok'
            ));
        }

        foreach ($baris as $k => $v) {
            if (! array_key_exists('stok_id', $v)) {
                continue;
            }

            $taut = (int) $v['stok_id'];

            $baris[$k]['stok_id'] = in_array($taut, $punya, true) ? $taut : null;
        }

        return $baris;
    }
}

if (! function_exists('sinkron_dibeli')) {
    /**
     * Samakan isi order_dibeli satu invoice dengan kiriman form.
     *
     * Key payload dari halaman Sunting adalah id_beli aslinya, jadi baris yang
     * nomornya masih dikenal di-update, bukan dihapus lalu disisip ulang. Itu
     * yang membuat nilai ukuran bisa menempel terus pada itemnya. Key buatan
     * (uniqId di form Tulis) disisip sebagai baris baru, dan baris yang hilang
     * dari payload dibuang — ukurannya ikut terhapus lewat foreign key CASCADE.
     *
     * Dipanggil di dalam transaksi; pemanggil yang memutuskan rollback.
     *
     * @param array $baris hasil produk_dibeli()['baris']
     * @return array<string|int, int> key form => id_beli
     */
    function sinkron_dibeli(int $invoice_id, array $baris): array
    {
        $db = \Config\Database::connect();

        $punya = array_map('intval', array_column(
            $db->table('dibeli')
                ->select('id_beli')
                ->where('invoice_id', $invoice_id)
                ->get()
                ->getResultArray(),
            'id_beli'
        ));

        $siap = lengkapi_produk(array_values($baris));
        $peta = [];
        $sisa = $punya;

        foreach (array_keys($baris) as $i => $key) {
            $lama = is_numeric($key) && in_array((int) $key, $sisa, true) ? (int) $key : null;

            if ($lama === null) {
                $db->table('dibeli')->insert($siap[$i]);
                $id = (int) $db->insertID();
            } else {
                $db->table('dibeli')->where('id_beli', $lama)->update($siap[$i]);
                unset($sisa[array_search($lama, $sisa, true)]);
                $id = $lama;
            }

            $peta[$key] = $id;
        }

        foreach ($sisa as $lama) {
            $db->table('dibeli')->where('id_beli', $lama)->where('invoice_id', $invoice_id)->delete();
        }

        return $peta;
    }
}

if (! function_exists('simpan_ukuran_produk')) {
    /**
     * Tulis ukuran terstruktur tiap item orderan.
     *
     * Mengembalikan pesan per kolom supaya pemanggil bisa me-rollback
     * seluruh transaksi dan mengirimnya sebagai galat 406, sama seperti
     * galat validasi yang sudah ditonjolkan JS form. Nama komponen ikut
     * ditulis karena JS hanya menampilkan isinya.
     *
     * @param array<string|int, int>             $peta   key form => id_beli
     * @param array<string|int, array>           $ukuran key form => [id_komponen => teks]
     * @return array<string, string>
     */
    function simpan_ukuran_produk(int $invoice_id, array $peta, array $ukuran): array
    {
        if ($ukuran === []) {
            return [];
        }

        $komponen = new \App\Models\UkuranKomponenModel();
        $model    = new \App\Models\NilaiUkuranModel();
        $sah      = $komponen->idSah();
        $galat    = [];

        foreach ($ukuran as $key => $kiriman) {
            if (! isset($peta[$key]) || ! is_array($kiriman)) {
                continue;
            }

            $ditolak = $model->sinkron($invoice_id, (int) $peta[$key], null, $kiriman, $sah);

            if ($ditolak === []) {
                continue;
            }

            $nama = $komponen->label(array_keys($ditolak));

            foreach ($ditolak as $id_komponen => $pesan) {
                $galat['produk[' . $key . '][nilai_ukuran][' . $id_komponen . ']']
                    = ($nama[$id_komponen]['nama'] ?? 'Ukuran') . ': ' . $pesan;
            }
        }

        return $galat;
    }
}

if (! function_exists('daftar_anggota')) {
    /**
     * Pecah kiriman form `anggota[k]` jadi baris anggota dan angka ukurannya,
     * sama seperti produk_dibeli() untuk baris produk.
     *
     * @return array{baris: array<string|int, array>, ukuran: array<string|int, array>}
     */
    function daftar_anggota($anggotas): array
    {
        if (! is_array($anggotas)) {
            return ['baris' => [], 'ukuran' => []];
        }

        $baris  = [];
        $ukuran = [];

        foreach ($anggotas as $k => $v) {
            if (! is_array($v)) {
                continue;
            }

            if (isset($v['nilai_ukuran']) && is_array($v['nilai_ukuran'])) {
                $ukuran[$k] = $v['nilai_ukuran'];
            }

            $siap = [];

            foreach (['nama', 'nomor', 'catatan'] as $kolom) {
                $siap[$kolom] = (string) ($v[$kolom] ?? '');
            }

            $baris[$k] = $siap;
        }

        return ['baris' => $baris, 'ukuran' => $ukuran];
    }
}

if (! function_exists('simpan_ukuran_anggota')) {
    /**
     * Tulis ukuran terstruktur tiap anggota rombongan. Sasarannya anggota_id,
     * jadi kolom id_beli dibiarkan NULL — satu orang bisa diukur untuk seluruh
     * orderan tanpa harus dikaitkan ke satu baris produk.
     *
     * @param array<string|int, int>  $peta   key form => id_anggota
     * @param array<string|int, array> $ukuran key form => [id_komponen => teks]
     * @return array<string, string>
     */
    function simpan_ukuran_anggota(int $invoice_id, array $peta, array $ukuran): array
    {
        if ($ukuran === []) {
            return [];
        }

        $komponen = new \App\Models\UkuranKomponenModel();
        $model    = new \App\Models\NilaiUkuranModel();
        $sah      = $komponen->idSah();
        $galat    = [];

        foreach ($ukuran as $key => $kiriman) {
            if (! isset($peta[$key]) || ! is_array($kiriman)) {
                continue;
            }

            $ditolak = $model->sinkron($invoice_id, null, (int) $peta[$key], $kiriman, $sah);

            if ($ditolak === []) {
                continue;
            }

            $nama = $komponen->label(array_keys($ditolak));

            foreach ($ditolak as $id_komponen => $pesan) {
                $galat['anggota[' . $key . '][nilai_ukuran][' . $id_komponen . ']']
                    = ($nama[$id_komponen]['nama'] ?? 'Ukuran') . ': ' . $pesan;
            }
        }

        return $galat;
    }
}

if (! function_exists('simpan_anggota')) {
    /**
     * Simpan daftar anggota rombongan beserta ukuran tiap orang, dipakai
     * jalur simpan admin dan CS.
     *
     * Penanda `anggota_dikirim` ditulis PHP di dalam panel, bukan JavaScript.
     * Tanpa penanda itu daftar yang sudah ada tidak disentuh sama sekali, jadi
     * script yang gagal dimuat tidak bisa menghapus orang-orang dari orderan.
     *
     * @return array<string, string> pesan per kolom, kosong kalau lolos
     */
    function simpan_anggota(int $invoice_id, $penanda, $kiriman): array
    {
        if ($penanda === null) {
            return [];
        }

        $anggota = daftar_anggota($kiriman);
        $hasil   = (new \App\Models\AnggotaModel())->sinkron($invoice_id, $anggota['baris']);

        if ($hasil['galat'] !== []) {
            return $hasil['galat'];
        }

        return simpan_ukuran_anggota($invoice_id, $hasil['peta'], $anggota['ukuran']);
    }
}

if (! function_exists('tautan_invoice_notif')) {
    /**
     * Tujuan klik satu notifikasi: tab Semua tempat nota itu berada. Admin bebas
     * lintas juragan, CS hanya lewat slug juragan milik notanya sendiri supaya
     * tautannya tidak menembus toko lain (dipakai panel lonceng dan halaman daftar).
     */
    function tautan_invoice_notif($seri, $slug_juragan): string
    {
        if (empty($seri)) {
            return '';
        }

        $kueri = http_build_query(['cari' => ['kolom' => 'faktur', 'q' => $seri]]);

        if (in_array(session()->get('level'), ['admin', 'superadmin'], true)) {
            return site_url('admin/invoices/lihat/semua/semua?' . $kueri);
        }

        if (empty($slug_juragan)) {
            return site_url('user/invoices');
        }

        return site_url('user/invoices/lihat/' . rawurlencode($slug_juragan) . '/semua?' . $kueri);
    }
}

if (! function_exists('wewenang_sunting_transaksi')) {
    /**
     * Apakah akun ini boleh membuka layar sunting transaksi (Tracking Transaksi
     * punyanya Admin). Admin dan superadmin selalu boleh; CS hanya kalau
     * Superadmin menyalakan penanda `sunting_transaksi` di akunnya.
     *
     * Penanda dibaca dari basis data, bukan dari sesi, supaya perubahan Superadmin
     * langsung berlaku tanpa perlu login ulang — sama seperti `lihat_semua_produk`.
     * Hasilnya dihafal per permintaan karena filter, navbar, dan daftar transaksi
     * menanyakannya dalam satu kali render.
     */
    function wewenang_sunting_transaksi(): bool
    {
        static $sudah = [];

        $level = (string) session()->get('level');

        if (in_array($level, ['admin', 'superadmin'], true)) {
            return true;
        }

        $id = (int) session()->get('id');

        if ($level !== 'cs' || $id <= 0) {
            return false;
        }

        if (! array_key_exists($id, $sudah)) {
            // find() diberi id skalar: mengembalikan satu baris (object) atau null
            $akun             = (new \App\Models\UserModel())->find($id);
            $sudah[$id]       = $akun !== null && (int) $akun->sunting_transaksi === 1;
        }

        return $sudah[$id];
    }
}

if (! function_exists('pengingat_penjahit_hari')) {
    /**
     * Berapa hari sebelum deadline sebuah orderan pembuatan/jual mulai muncul di
     * Reminder Penjahit. Diatur Superadmin lewat satu kunci di crm_setting; belum
     * pernah diisi = bawaan 3 hari.
     */
    function pengingat_penjahit_hari(): int
    {
        $tersimpan = (new \App\Models\PengaturanModel())
            ->banyak(['reminder_penjahit_hari'])['reminder_penjahit_hari'] ?? null;

        if ($tersimpan === null || $tersimpan === '') {
            return 3;
        }

        return max(0, (int) $tersimpan);
    }
}

if (! function_exists('juragan_dipakai_sesi')) {
    /**
     * Daftar id juragan yang boleh dibaca akun pada sesi ini. Superadmin memegang
     * semuanya; admin/CS hanya toko yang tertaut padanya. Dipakai navbar (view) yang
     * tidak punya akses ke helper controller, jadi logikanya ditaruh di sini.
     *
     * @return list<int>
     */
    function juragan_dipakai_sesi(): array
    {
        if ((string) session()->get('level') === 'superadmin') {
            $semua = (new \App\Models\JuraganModel())->select('id_juragan')->findAll();

            return array_map('intval', array_column($semua, 'id_juragan'));
        }

        $uid      = (int) session()->get('id');
        $juragans = \App\Controllers\Juragan::by_user($uid)[$uid]['juragan'] ?? [];

        return array_map('intval', array_column($juragans, 'id'));
    }
}

if (! function_exists('pengingat_penjahit_jumlah')) {
    /**
     * Jumlah orderan yang sedang menjadi reminder — untuk badge di menu sidebar.
     */
    function pengingat_penjahit_jumlah(): int
    {
        return (new \App\Models\InvoiceModel())
            ->hitungPengingatPenjahit(juragan_dipakai_sesi(), pengingat_penjahit_hari());
    }
}
