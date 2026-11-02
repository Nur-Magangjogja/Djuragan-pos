<?php
/**
 * Variabel di bawah ini diisi dari Admin\Settings\Bank::index() lewat view('admin/pengaturan/bank', $data).
 * CodeIgniter memanggil extract() di dalam system/View/View.php, jadi analyzers tidak
 * bisa menelusuri asal variabelnya; blok @var ini hanya memberi tahu Intelephense.
 *
 * @var string                                        $title      Judul halaman
 * @var list<object>                                  $banks      Daftar rekening (BankModel::findAll): id_bank, nama_bank, rekening, atas_nama, tipe_bank
 * @var array<string, int>                            $pemakaian  Jumlah pembayaran per rekening: id_bank => jumlah
 * @var \CodeIgniter\Validation\Validation             $validation Hasil validasi dari request sebelumnya
 * @var ?string                                       $sukses     Flash message sukses
 * @var ?string                                       $gagal      Flash message gagal
 */

// daftar tipe akun dipakai dua kali: form tambah dan form sunting
$jenis_akun = [
    ''        => 'Pilih Tipe Akun',
    'bca'     => 'BCA',
    'bni'     => 'BNI',
    'bri'     => 'BRI',
    'edc'     => 'EDC',
    'mandiri' => 'Mandiri',
];

// akun di luar daftar (tunai, C.O.D) tetap perlu disebut namanya
$label_akun = static fn (?string $nama): string => $jenis_akun[$nama] ?? strtoupper((string) $nama);

// logo_bank() masih mengembalikan teks "NOLOGO" untuk akun tanpa logo, jadi
// kotaknya diisi nama akun supaya barisnya tidak terbaca kosong
$logo_akun = static function (object $bank) use ($label_akun): string {
    $logo = logo_bank($bank->nama_bank);

    if ($logo !== 'NOLOGO') {
        return $logo;
    }

    return '<span class="d-inline-flex align-items-center justify-content-center rounded border border-ink-200 bg-white text-muted small text-uppercase" style="width: 100px; height: 50px">'
        . esc($label_akun($bank->nama_bank) ?: '-') . '</span>';
};

// kedua form memakai nama field yang berbeda, jadi error langsung menunjuk
// modal yang harus dibuka lagi
$tambah_err  = $validation->hasError('nama_bank') || $validation->hasError('nomor_rekening') || $validation->hasError('atas_nama');
$sunting_err = $validation->hasError('sunting_nama_bank') || $validation->hasError('sunting_nomor_rekening') || $validation->hasError('sunting_atas_nama');
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
/* Di ponsel tabel rekening ini meluber 37-107px keluar kartu: kolom Logo dikunci
   120px + logonya 100x50, dan padding .table (0,8rem 1rem !important) memakan
   32px per kolom. Akibatnya tombol Sunting/Hapus jatuh ke LUAR kartu dan tidak
   bisa disentuh sama sekali (terukur elementFromPoint gagal di 320 dan 360px).
   Yang dikecilkan hanya logo + jarak dalam sel; tidak ada gulir horizontal baru. */
@media (max-width: 767.98px) {
    .bank-tabel th:first-child {
        width: auto !important;
    }
    .bank-tabel thead th,
    .bank-tabel tbody td {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
    }
    /* logo_bank() menulis width:100px INLINE di <svg>-nya, dan kotak pengganti
       akun tanpa logo juga 100x50 inline — dua-duanya butuh !important */
    .bank-logo svg,
    .bank-logo span {
        width: 64px !important;
        height: 32px !important;
    }
    /* Sisa 23px di 320px: nomor rekening panjang tidak punya spasi untuk patah,
       jadi kolomnya menahan tabel tetap lebih lebar dari kartu. */
    .bank-tabel tbody td:nth-child(2) {
        overflow-wrap: anywhere;
    }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">Pengaturan Bank</h1>

    <div class="row">
        <div class="col-12 mb-3">
            <?php if (($sukses ?? null) !== null) { ?>
                <div class="alert alert-success py-2" data-flash><?= esc($sukses) ?></div>
            <?php } ?>
            <?php if (($gagal ?? null) !== null) { ?>
                <div class="alert alert-danger py-2" data-flash><?= esc($gagal) ?></div>
            <?php } ?>

            <div class="card">
                <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <span class="fw-semibold">
                        <i class="fal fa-building-columns text-primary me-1"></i>Rekening pembayaran
                        <span class="text-muted small">(<?= count($banks) ?>)</span>
                    </span>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahBank">
                        <i class="fal fa-plus"></i> Tambah Bank / EDC
                    </button>
                </div>
                <div class="card-body">
                    <table class="table align-middle bank-tabel">
                        <thead>
                            <tr>
                                <th style="width: 120px">Logo</th>
                                <th>Rekening</th>
                                <th class="text-end" style="width: 1%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($banks === []) { ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">
                                        Belum ada rekening. Klik <span class="fw-semibold">Tambah Bank / EDC</span> untuk menambah.
                                    </td>
                                </tr>
                            <?php } ?>
                            <?php foreach ($banks as $bank) { ?>
                                <tr>
                                    <td class="bank-logo">
                                        <?= $logo_akun($bank) ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= esc($bank->rekening) ?></div>
                                        <div class="text-muted small">
                                            <?= esc($label_akun($bank->nama_bank)) ?>
                                            <?php if ($bank->atas_nama !== null && $bank->atas_nama !== '') { ?>
                                                &middot; <?= esc($bank->atas_nama) ?>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?php
                                        // barisnya tidak benar-benar hilang, jadi pembayaran lama
                                        // tetap terbaca — cuma jumlah catatannya yang perlu disebut
                                        $dipakai       = (int) ($pemakaian[$bank->id_bank] ?? 0);
                                        $nama_rekening = trim($label_akun($bank->nama_bank) . ' ' . $bank->rekening);
                                        $peringatan    = 'Hapus rekening ' . $nama_rekening . '?';

                                        if ($dipakai > 0) {
                                            $peringatan .= ' Rekening ini dipakai ' . $dipakai . ' catatan pembayaran; riwayatnya tetap terbaca, hanya pilihannya yang hilang.';
                                        }
                                        ?>
                                        <button aria-label="Sunting rekening" title="Sunting <?= esc($nama_rekening, 'attr') ?>" data-banks="<?= esc(json_encode($bank)) ?>" data-bs-toggle="modal" data-bs-target="#modalEditBank" class="btn btn-sm btn-outline-secondary">
                                            <i class="fal fa-pencil"></i>
                                        </button>
                                        <?= form_open('admin/settings/bank/hapus', ['class' => 'd-inline']) ?>
                                        <?= form_hidden('id_bank', (string) $bank->id_bank) ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" aria-label="Hapus rekening"
                                            title="Hapus <?= esc($nama_rekening, 'attr') ?>"
                                            data-bs-toggle="modal" data-bs-target="#modalHapusBank"
                                            data-pesan="<?= esc($peringatan, 'attr') ?>">
                                            <i class="fal fa-trash"></i>
                                        </button>
                                        <?= form_close() ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('modal') ?>
<!-- Modal tambah -->
<div class="modal fade" id="modalTambahBank" tabindex="-1" aria-labelledby="modalTambahBankLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <?= form_open('admin/settings/bank/save', ['class' => 'modal-content']) ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalTambahBankLabel"><i class="fal fa-plus-circle text-primary me-1"></i>Tambah Bank / EDC</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <?= form_label('Tipe Akun', 'nama_bank', ['class' => 'form-label']) ?>
                <?= form_dropdown([
                    'class'    => 'form-select' . ($validation->hasError('nama_bank') ? ' is-invalid' : ''),
                    'name'     => 'nama_bank',
                    'options'  => $jenis_akun,
                    'required' => '',
                    'selected' => set_value('nama_bank'),
                ]) ?>
                <?php if ($validation->hasError('nama_bank')) { ?>
                    <div class="invalid-feedback d-block"><?= $validation->getError('nama_bank') ?></div>
                <?php } ?>
            </div>
            <div class="mb-3">
                <?= form_label('Nomor Rekening', 'nomor_rekening', ['class' => 'form-label']) ?>
                <?= form_input([
                    'class'       => 'form-control' . ($validation->hasError('nomor_rekening') ? ' is-invalid' : ''),
                    'id'          => 'nomor_rekening',
                    'name'        => 'nomor_rekening',
                    'placeholder' => 'cth: 1234567890',
                    'maxlength'   => '30',
                    'inputmode'   => 'numeric',
                    'required'    => '',
                    'value'       => set_value('nomor_rekening'),
                ]) ?>
                <?php if ($validation->hasError('nomor_rekening')) { ?>
                    <div class="invalid-feedback d-block"><?= $validation->getError('nomor_rekening') ?></div>
                <?php } ?>
            </div>
            <div class="mb-3">
                <?= form_label('Atas Nama', 'atas_nama', ['class' => 'form-label']) ?>
                <?= form_input([
                    'class'       => 'form-control' . ($validation->hasError('atas_nama') ? ' is-invalid' : ''),
                    'id'          => 'atas_nama',
                    'name'        => 'atas_nama',
                    'placeholder' => 'rekening atas nama',
                    'required'    => '',
                    'value'       => set_value('atas_nama'),
                ]) ?>
                <?php if ($validation->hasError('atas_nama')) { ?>
                    <div class="invalid-feedback d-block"><?= $validation->getError('atas_nama') ?></div>
                <?php } ?>
            </div>
        </div>
        <div class="modal-footer flex-nowrap p-0">
            <button type="button" data-bs-dismiss="modal" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0">Batal</button>
            <button type="submit" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0 border-start"><i class="fal fa-save"></i> Tambahkan</button>
        </div>
        <?= form_close() ?>
    </div>
</div>
<!-- Modal edit -->
<div class="modal fade" id="modalEditBank" tabindex="-1" aria-labelledby="modalEditBankLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <?= form_open('admin/settings/bank/update', ['class' => 'modal-content'], ['id_bank' => set_value('id_bank')]) ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalEditBankLabel"><i class="fal fa-pen text-primary me-1"></i>Sunting Rekening</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <?= form_label('Tipe Akun', 'sunting_nama_bank', ['class' => 'form-label']) ?>
                <?= form_dropdown([
                    'class'    => 'form-select' . ($validation->hasError('sunting_nama_bank') ? ' is-invalid' : ''),
                    'name'     => 'sunting_nama_bank',
                    'options'  => $jenis_akun,
                    'required' => '',
                    'selected' => set_value('sunting_nama_bank'),
                ]) ?>
                <?php if ($validation->hasError('sunting_nama_bank')) { ?>
                    <div class="invalid-feedback d-block"><?= $validation->getError('sunting_nama_bank') ?></div>
                <?php } ?>
            </div>
            <div class="mb-3">
                <?= form_label('Nomor Rekening', 'sunting_nomor_rekening', ['class' => 'form-label']) ?>
                <?= form_input([
                    'class'       => 'form-control' . ($validation->hasError('sunting_nomor_rekening') ? ' is-invalid' : ''),
                    'id'          => 'sunting_nomor_rekening',
                    'name'        => 'sunting_nomor_rekening',
                    'placeholder' => 'cth: 1234567890',
                    'maxlength'   => '30',
                    'inputmode'   => 'numeric',
                    'required'    => '',
                    'value'       => set_value('sunting_nomor_rekening'),
                ]) ?>
                <?php if ($validation->hasError('sunting_nomor_rekening')) { ?>
                    <div class="invalid-feedback d-block"><?= $validation->getError('sunting_nomor_rekening') ?></div>
                <?php } ?>
            </div>
            <div class="mb-3">
                <?= form_label('Atas Nama', 'sunting_atas_nama', ['class' => 'form-label']) ?>
                <?= form_input([
                    'class'       => 'form-control' . ($validation->hasError('sunting_atas_nama') ? ' is-invalid' : ''),
                    'id'          => 'sunting_atas_nama',
                    'name'        => 'sunting_atas_nama',
                    'placeholder' => 'rekening atas nama',
                    'required'    => '',
                    'value'       => set_value('sunting_atas_nama'),
                ]) ?>
                <?php if ($validation->hasError('sunting_atas_nama')) { ?>
                    <div class="invalid-feedback d-block"><?= $validation->getError('sunting_atas_nama') ?></div>
                <?php } ?>
            </div>
        </div>

        <div class="modal-footer flex-nowrap p-0">
            <button type="button" data-bs-dismiss="modal" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0">Batal</button>
            <button type="submit" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0 border-start">Simpan</button>
        </div>
        <?= form_close() ?>
    </div>
</div>
<!-- Konfirmasi hapus satu modal untuk semua baris: isinya diisi lewat JS dari
     data-pesan tombol yang dibuka, jadi tidak perlu satu popus per rekening -->
<div class="modal fade" id="modalHapusBank" tabindex="-1" aria-labelledby="modalHapusBankLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalHapusBankLabel"><i class="fal fa-triangle-exclamation text-danger me-1"></i>Hapus rekening</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body small" id="modalHapusBankPesan"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-sm btn-danger" id="modalHapusBankYa"><i class="fal fa-trash"></i> Hapus</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<?php

$current_user_id      = session()->get('id');
$link_api_juragan     = site_url('api/juragan/by_user/');
$link_invoice         = site_url('admin/invoices/lihat/');
$buka_sunting         = $sunting_err ? 'true' : 'false';
$buka_tambah          = $tambah_err ? 'true' : 'false';

$js = <<< JS
    $(function() {
    	'use strict';

        const suntingErr = {$buka_sunting},
            tambahErr = {$buka_tambah};
    	// sidebar

    	var juragan = document.getElementById('juragan');
        if(juragan) {
            juragan.addEventListener('show.bs.offcanvas', function () {
                var id = {$current_user_id};
                $('#listLi').empty();
                $.getJSON('{$link_api_juragan}', { id: id }, function(b){
                    var a=[];
                    // akun yang cuma pegang satu toko tidak perlu memilih
                    var ada = 0;
                    $.each(b[id].juragan, function() { ada++; });
                    if (ada > 1) {
                        a.push('<a class="list-group-item text-light list-group-item-action" href="{$link_invoice}semua"><i class="fal fa-user-circle"></i> Semua Juragan</a>');
                    }

                    $.each(b[id].juragan,function(c,b){
                        a.push('<a href="{$link_invoice}'+b.slug+'" class="list-group-item text-light list-group-item-action"><i class="fal fa-user-circle"></i> '+b.nama+'</a>');
                    }),

                    $(a.join('')).appendTo('#listLi');
                });
            });
        }


        // nomor rekening: angka saja, jadi spasi, titik dan strip pemisah ikut
        // terbuang saat diketik maupun ditempel
        $(document).on('input', '#nomor_rekening, #sunting_nomor_rekening', function() {
            var v = this.value.replace(/\D/g, '');

            if (v !== this.value || v.length > 30) {
                this.value = v.slice(0, 30);
            }
        });

        // modal sunting: isiannya diambil dari tombol yang membukanya
        const modalEditBank = document.getElementById('modalEditBank'),
            modalTambah = document.getElementById('modalTambahBank');

        modalEditBank.addEventListener('show.bs.modal', event => {
            // dibuka sendiri tanpa tombol (ada error validasi): isian biarkan apa adanya
            if (! event.relatedTarget) {
                return;
            }

            var data = JSON.parse(event.relatedTarget.getAttribute('data-banks'));

            modalEditBank.querySelector('[name="id_bank"]').value = data.id_bank;
            modalEditBank.querySelector('[name="sunting_nama_bank"]').value = data.nama_bank;
            modalEditBank.querySelector('[name="sunting_nomor_rekening"]').value = data.rekening;
            modalEditBank.querySelector('[name="sunting_atas_nama"]').value = data.atas_nama;
        });

        // konfirmasi hapus memakai popus halaman sendiri, bukan dialog bawaan
        // peramban yang judulnya ikut alamat tunnel dan tampilannya tidak bisa diatur
        const modalHapusBank = document.getElementById('modalHapusBank');
        let formHapus = null;

        modalHapusBank.addEventListener('show.bs.modal', event => {
            const pemicu = event.relatedTarget;

            if (! pemicu) {
                return;
            }

            formHapus = pemicu.closest('form');
            // textContent, bukan innerHTML: pesannya datang dari atribul data
            document.getElementById('modalHapusBankPesan').textContent = pemicu.getAttribute('data-pesan') || '';
        });

        document.getElementById('modalHapusBankYa').addEventListener('click', () => {
            if (formHapus) {
                formHapus.submit();
            }
        });

        // gagal simpan: modal yang mengirim dibuka lagi supaya pesannya kelihatan
        if (suntingErr) {
            new bootstrap.Modal(modalEditBank).show();
        }

        if (tambahErr) {
            new bootstrap.Modal(modalTambah).show();
        }

        // pesan error dari server hanya berlaku untuk muat halaman ini, jadi dibuang
        // seluruhnya saat modal ditutup supaya tidak muncul lagi di percobaan berikutnya
        [modalEditBank, modalTambah].forEach(modal => {
            modal.addEventListener('hidden.bs.modal', () => {
                modal.querySelectorAll('.invalid-feedback').forEach(el => el.parentNode.removeChild(el));
                modal.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            });
        });
    });
    JS;

$packer    = new Tholu\Packer\Packer($js, 'Normal', true, false, true);
$packed_js = $packer->pack();
echo '<script>' . $packed_js . '</script>';
?>
<?= $this->endSection() ?>
