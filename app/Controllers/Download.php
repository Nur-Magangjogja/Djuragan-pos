<?php

namespace App\Controllers;

use App\Models\InvoiceModel;
use Dompdf\Dompdf;
use Dompdf\Options;

class Download extends BaseController
{
    /**
     * Nota untuk pelanggan: berisi uang (harga, total, sisa bayar).
     */
    public function invoice($invoice)
    {
        $get = $this->ambil($invoice);

        if ($get === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->sajikan('template/invoice', $get, 'invoice');
    }

    /**
     * Lembar kerja untuk penjahit: spesifikasi jahit + ukuran rombongan, tanpa uang.
     */
    public function penjahit($invoice)
    {
        $get = $this->ambil($invoice);

        if ($get === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->sajikan('template/penjahit', $get, 'penjahit');
    }

    /**
     * Tautan invoice publik: dibuka tanpa login lewat tombol "Salin tautan
     * invoice". Nota dicari hanya bermodal nomor, tapi aksesnya dikunci token
     * HMAC (lihat token_invoice_publik) yang mengikat nomor + id nota, sehingga
     * menebak nota orang lain tidak mungkin. Yang dirender persis satu nota ini,
     * tidak ada daftar atau data lain yang ikut terbawa.
     */
    public function nota($seri = '', $token = '')
    {
        helper('fungsi');

        $seri = trim((string) $seri);

        if ($seri === '' || $token === '') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $get   = (new InvoiceModel())->getAll('semua', 0, ['kolom' => 'faktur', 'q' => $seri]);
        $nota  = null;

        // q bersifat LIKE, jadi bisa menyinggung beberapa nota; ambil yang nomornya
        // persis sama supaya token tidak tertukar dengan nota berawalan mirip
        foreach ($get['data'] ?? [] as $baris) {
            if ((string) $baris->seri === $seri) {
                $nota = $baris;

                break;
            }
        }

        // verifikasi token HMAC yang mengikat id + nomor nota ini saja
        if ($nota === null
            || ! hash_equals(token_invoice_publik((int) $nota->id_invoice, (string) $nota->seri), (string) $token)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('template/invoice', ['invoice' => $nota]);
    }

    /**
     * Satu baris invoice lewat nomor nota; null kalau nomornya tidak dikenal
     * atau tokonya tidak dipegang akun yang sedang mencetak.
     */
    private function ambil($invoice)
    {
        $get_invoice = (new InvoiceModel())->getAll('semua', 0, [
            'kolom' => 'faktur',
            'q'     => $invoice,
        ]);

        $nota = $get_invoice['data'][0] ?? null;

        if ($nota !== null && ! $this->bolehToko((int) $nota->juragan_id)) {
            return null;
        }

        return $nota;
    }

    /**
     * Render view jadi PDF A4 dan kirim ke browser. Dinamakan dari nomor notasinya
     * supaya berkas yang tersimpan orang masih bisa dibedakan.
     */
    private function sajikan(string $view, object $invoice, string $awalan)
    {
        $opsi = new Options();
        $opsi->set('chroot', [FCPATH]);

        $dompdf = new Dompdf($opsi);
        $dompdf->loadHtml(view($view, ['invoice' => $invoice]));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // hanya karakter aman: nomor nota masuk ke header Content-Disposition
        $nama = $awalan . '-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $invoice->seri);
        $dompdf->stream($nama, ['Attachment' => false]);

        exit(0);
    }
}
