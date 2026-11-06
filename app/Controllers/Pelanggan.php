<?php

namespace App\Controllers;

use App\Libraries\Ongkir;
use App\Models\PelangganModel;

class Pelanggan extends BaseController
{
    public function baru()
    {
        if ($this->request->isAJAX()) {
            /*
             * Nama orang adalah satu-satunya identitas yang dibaca manusia di
             * seluruh laporan, jadi angka yang nyasar ke kolom nama (jari yang
             * sebenarnya hendak mengisi HP) harus berhenti di server juga, bukan
             * cuma di peramban. Penerima dikirim dengan nama field pemesan, jadi
             * satu aturan ini mengawal keduanya.
             */
            $validation = \Config\Services::validation();
            $validation->setRuleGroup('tambahPelanggan');

            if (! $validation->withRequest($this->request)->run()) {
                $this->response->setStatusCode(406);

                return $this->response->setJSON($validation->getErrors());
            }

            $ongkir    = new Ongkir();
            $pelanggan = new PelangganModel();

            $d['nama_pelanggan'] = strtoupper(trim($this->request->getPost('nama_pelanggan')));
            $d['hp']             = json_encode(array_filter($this->request->getPost('hp')));

            $cod = $this->request->getVar('cod');
            $cod = ($cod === null ? '0' : '1');

            $d['cod'] = $cod;
            if ($cod === '1') {
                $d['kecamatan'] = null;
                $d['kabupaten'] = null;
                $d['provinsi']  = null;
                $d['alamat']    = null;
                $d['kodepos']   = null;
            } else {
                $d['kecamatan'] = $this->request->getPost('kecamatan');
                $d['kabupaten'] = $this->request->getPost('kabupaten');
                $d['provinsi']  = $this->request->getPost('provinsi');
                $d['alamat']    = htmlentities(strtoupper(trim(preg_replace('/\s+/', ' ', $this->request->getPost('alamat')))), ENT_QUOTES);
                $d['kodepos']   = $this->request->getPost('kodepos');
            }

            if ($this->request->getVar('id_pelanggan')) {
                $d['id_pelanggan'] = (int) $this->request->getPost('id_pelanggan');
            }

            $pelanggan->save($d);

            if ($this->request->getVar('id_pelanggan')) {
                $id = (int) $this->request->getPost('id_pelanggan');
            } else {
                $db = \Config\Database::connect();
                $id = $db->insertID();
            }

            $result = $pelanggan->ambil($id)->getFirstRow();

            sinkron_kontak($id, $result->hp);

            $r['id_pelanggan']   = (int) $result->id_pelanggan;
            $r['hp']             = json_decode($result->hp);
            $r['nama_pelanggan'] = $result->nama_pelanggan;
            $r['cod']            = $result->cod;

            if ($result->cod === '0') {
                $r['nama_kecamatan'] = strtoupper(esc($ongkir->kecamatan($result->kabupaten, $result->kecamatan)['subdistrict_name']));
                $r['kecamatan']      = (int) $result->kecamatan;
                $r['nama_kabupaten'] = strtoupper(esc($ongkir->kota($result->provinsi, $result->kabupaten)['city_name']));
                $r['kabupaten']      = (int) $result->kabupaten;
                $r['nama_provinsi']  = strtoupper(esc($ongkir->provinsi($result->provinsi)['province_name']));
                $r['provinsi']       = (int) $result->provinsi;
                $r['alamat']         = $result->alamat;
                $r['kodepos']        = $result->kodepos;
            }

            return $this->response->setJSON($r);
        }

        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }

    public function cari()
    {
        // if ($this->request->isAJAX()) {
        // } else {
        //     throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        // }

        $cari       = $this->request->getGet('q');
        $juragan_id = (int) $this->request->getGet('juragan_id');

        if ($juragan_id < 1 || ! $this->bolehToko($juragan_id)) {
            return $this->response->setJSON(['query' => $cari, 'results' => []]);
        }

        $pelanggan = new PelangganModel();

        $builder = $pelanggan->cari($juragan_id, $cari);

        $s = [];
        $i = 0;

        foreach ($builder->getResult() as $u) {
            $s[$i] = $this->bentuk($u);
            $i++;
        }

        $return = [
            'query'   => $cari,
            'results' => $s,
        ];

        return $this->response->setJSON($return);
    }

    /**
     * Satu pelanggan untuk mengisi form edit. Bentuk hasilnya sama dengan
     * salah satu item hasil cari().
     */
    public function data($id_pelanggan)
    {
        if (! $this->request->isAJAX()) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $juragan_id = (int) $this->request->getGet('juragan_id');

        if ($juragan_id < 1 || ! $this->bolehToko($juragan_id)) {
            return $this->response->setStatusCode(403)->setJSON(['data' => null]);
        }

        $pelanggan  = new PelangganModel();
        $u          = $pelanggan->satu($juragan_id, (int) $id_pelanggan);

        if (! $u) {
            return $this->response->setStatusCode(404)->setJSON(['data' => null]);
        }

        return $this->response->setJSON(['data' => $this->bentuk($u)]);
    }

    /**
     * Baris tabel pelanggan jadi bentuk yang dipakai pencarian dan form edit.
     */
    private function bentuk(object $u): array
    {
        $ongkir = new Ongkir();

        $s = [
            'id'   => (int) $u->id_pelanggan,
            'nama' => $u->nama_pelanggan,
            'hp'   => json_decode($u->hp),
            'cod'  => (int) $u->cod,
            'full' => 'C.O.D',
        ];

        if ($u->cod === '0') {
            $id_kecamatan = (int) $u->kecamatan;
            $id_kabupaten = (int) $u->kabupaten;
            $id_provinsi  = (int) $u->provinsi;

            $kota = $ongkir->kota($id_provinsi, $id_kabupaten);

            $nama_kecamatan = strtoupper($ongkir->kecamatan($id_kabupaten, $id_kecamatan)['subdistrict_name'] ?? '');
            $nama_kabupaten = strtoupper(trim((($kota['type'] ?? '') === 'Kabupaten' ? '' : '(Kota) ') . ($kota['city_name'] ?? '')));
            $nama_provinsi  = strtoupper($ongkir->provinsi($id_provinsi)['province_name'] ?? '');

            $s['nama_kecamatan'] = $nama_kecamatan;
            $s['kecamatan']      = $id_kecamatan;
            $s['nama_kabupaten'] = $nama_kabupaten;
            $s['kabupaten']      = $id_kabupaten;
            $s['nama_provinsi']  = $nama_provinsi;
            $s['provinsi']       = $id_provinsi;
            $s['alamat']         = $u->alamat;
            $s['kodepos']        = $u->kodepos;
            $s['full']           = $u->alamat . ', ' . $nama_kecamatan . ', ' . $nama_kabupaten . ', ' . $nama_provinsi . ' - ' . $u->kodepos;
        }

        return $s;
    }
}
