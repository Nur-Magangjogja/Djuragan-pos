<?php
/**
 * Variabel di bawah ini diisi dari Admin\Settings\Juragan::index() lewat view('admin/pengaturan/juragan', $data).
 * CodeIgniter memanggil extract() di dalam system/View/View.php, jadi analyzers tidak
 * bisa menelusuri asal variabelnya; blok @var ini hanya memberi tahu Intelephense.
 *
 * @var string       $title     Judul halaman
 * @var ?string      $sukses    Flash message sukses
 * @var ?string      $gagal     Flash message gagal
 * @var list<object> $juragan   Daftar toko (JuraganModel::findAll): id_juragan, juragan, nama_juragan
 * @var list<object> $banks     Daftar rekening (BankModel::findAll): id_bank, nama_bank, rekening, atas_nama, tipe_bank
 * @var array<int, array<string, list<array{id: int, nama: string}>>> $pengelola Penanggung jawab per toko: id_juragan => ['admin' => [...], 'cs' => [...]]
 * @var array<int, int> $orderan Jumlah nota per toko: id_juragan => jumlah
 * @var list<array{id: int, nama: string, username: string, level: string}> $admins  Akun level admin
 * @var list<array{id: int, nama: string, username: string, level: string}> $cs_list Akun level cs
 */

$pager   = \Config\Services::pager();
$session = \Config\Services::session();

// daftar rekening dipakai bersama oleh modal tambah dan modal sunting
$opsi_bank = [];

foreach ($banks as $b) {
    $opsi_bank[(string) $b->id_bank] = strtoupper($b->nama_bank) . ' - ' . $b->atas_nama;
}

// save_juragan yang gagal kembali dengan withInput(); id hanya ikut terkirim
// pada form sunting, jadi isian lama tanpa id berarti percobaan menambah.
$tambah_err   = old('nama_juragan') !== null && old('id') === null;
$pilihan_bank = array_map('strval', (array) old('bank', []));

// pilihan akun untuk pemilih penanggung jawab
$isi_opsi = static function (array $daftar): array {
    $opsi = ['' => '— tidak ada —'];

    foreach ($daftar as $u) {
        $opsi[(string) $u['id']] = $u['nama'];
    }

    return $opsi;
};
$opsi_admin = $isi_opsi($admins);
$opsi_cs    = $isi_opsi($cs_list);

// rekening dipilih dengan kotak centang: satu klik langsung menambah atau
// membatalkan satu rekening, tanpa daftar yang harus ditahan CTRL. Kotaknya
// sengaja tidak memakai atribut required per baris: itu memaksa orang mencentang
// SEMUA rekening, padahal aturannya minimal satu. Yang menahan "minimal satu"
// sekarang skrip form di bawah dan server (grup addJuragan/editJuragan).
$kotak_rekening = static function (array $opsi, array $terpilih, string $awalan): void {
    foreach ($opsi as $id => $label) {
        $kunci = $awalan . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $id); ?>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="bank[]" value="<?= esc((string) $id) ?>"
                id="<?= esc($kunci) ?>" <?= in_array((string) $id, $terpilih, true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="<?= esc($kunci) ?>"><?= esc($label) ?></label>
        </div>
<?php }
};

// satu baris daftar pemegang: label + pil nama, atau keterangan kosong
$chip = static function (string $label, array $daftar): void { ?>
    <div class="d-flex align-items-center flex-wrap gap-2">
        <span class="text-muted"><?= esc($label) ?></span>
        <?php if ($daftar === []) { ?>
            <span class="badge rounded-pill fw-light bg-secondary">belum ada</span>
        <?php } ?>
        <?php foreach ($daftar as $u) { ?>
            <span class="badge rounded-pill fw-light bg-dark"><?= esc($u['nama']) ?></span>
        <?php } ?>
    </div>
<?php };

?>
<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">

    <h1 class="h3 mt-5">Pengaturan Juragan</h1>

    <?php if (($sukses ?? null) !== null) { ?>
        <div class="alert alert-success py-2" data-flash><?= esc($sukses) ?></div>
    <?php } ?>
    <?php if (($gagal ?? null) !== null) { ?>
        <div class="alert alert-danger py-2" data-flash><?= esc($gagal) ?></div>
    <?php } ?>

    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h6 mb-0">Daftar Juragan</h2>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahJuragan">
                    <i class="fal fa-plus"></i> Tambah Juragan
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Juragan</th>
                            <th scope="col">Rekening</th>
                            <th scope="col">Penanggung jawab</th>
                            <th scope="col" class="text-end">&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($juragan as $j) { ?>
                        <?php
                        $bank = model('JuraganModel')->getBank($j->id_juragan);
                        // kunci yang belum ada diisi daftar kosong, baris ini tidak perlu lagi menebak
                        $pg = ($pengelola[$j->id_juragan] ?? []) + ['admin' => [], 'cs' => []];
                        ?>
                        <tr data-arr="<?= esc(json_encode(compact('bank', 'j', 'pg'))) ?>">
                            <td class="fw-semibold"><?= esc($j->nama_juragan) ?></td>
                            <td class="small text-muted">
                                <?php if ($bank === []) { ?>
                                    belum ada rekening
                                <?php } else { ?>
                                    <?= count($bank) ?> akun<br>
                                    <?= esc(implode(', ', array_unique(array_map(static fn (object $b): string => strtoupper((string) $b->nama), $bank)))) ?>
                                <?php } ?>
                            </td>
                            <td class="small d-flex flex-column gap-1">
                                <?php $chip('Admin', $pg['admin']); ?>
                                <?php $chip('CS', $pg['cs']); ?>
                                <?php if (count($pg['admin']) > 1 || count($pg['cs']) > 1) { ?>
                                    <div style="color:#b42318">lebih dari satu — tunjuk satu Admin dan satu CS</div>
                                <?php } ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?= anchor('admin/invoices/lihat/' . $j->juragan, '<i class="fal fa-shopping-cart"></i>', ['class' => 'btn btn-sm btn-outline-secondary', 'aria-label' => 'Orderan', 'title' => 'Lihat orderan ' . $j->nama_juragan]) ?>
                                <?= form_button([
                                    'class'          => 'btn btn-sm btn-outline-secondary',
                                    'content'        => '<i class="fal fa-pencil"></i>',
                                    'aria-label'     => 'Sunting',
                                    'data-bs-target' => '#modalSuntingJuragan',
                                    'data-bs-toggle' => 'modal',
                                    'title'          => 'Sunting ' . $j->nama_juragan,
                                    'type'           => 'button',
                                ]) ?>
                                <?= form_button([
                                    'class'          => 'btn btn-sm btn-outline-secondary',
                                    'content'        => '<i class="fal fa-user-plus"></i>',
                                    'aria-label'     => 'Penanggung jawab',
                                    'data-bs-target' => '#modalPengelola',
                                    'data-bs-toggle' => 'modal',
                                    'title'          => 'Tunjuk penanggung jawab ' . $j->nama_juragan,
                                    'type'           => 'button',
                                ]) ?>
                                <?php
                                // jumlah nota dipakai sebagai peringatan: slug toko yang dihapus
                                // tidak lagi membuka nota lamanya
                                $nota    = $orderan[$j->id_juragan] ?? 0;
                                $peringatan = 'Hapus ' . $j->nama_juragan . '?';

                                if ($nota > 0) {
                                    $peringatan .= ' ' . $nota . ' nota lama tidak akan bisa dibuka lagi lewat link tokonya.';
                                }
                                ?>
                                <?= form_open('admin/settings/juragan/hapus', ['class' => 'd-inline']) ?>
                                <?= form_hidden('id_juragan', (string) $j->id_juragan) ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Hapus"
                                    title="Hapus <?= esc($j->nama_juragan, 'attr') ?>"
                                    onclick="return confirm('<?= esc($peringatan, 'js') ?>')">
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

<?= $this->endSection() ?>

<?= $this->section('modal') ?>
<div class="modal fade" id="modalTambahJuragan" tabindex="-1" aria-labelledby="modalTambahJuraganLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <?= form_open('admin/settings/save_juragan', ['id' => 'mt']); ?>
            <div class="modal-header">
                <h5 class="modal-title" id="modalTambahJuraganLabel">Tambah Juragan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <?= form_label('Nama Juragan', 'tambah_nama_juragan', ['class' => 'form-label']); ?>
                    <?= form_input('nama_juragan', set_value('nama_juragan'), ['class' => 'form-control', 'id' => 'tambah_nama_juragan', 'required' => '', 'placeholder' => 'nama juragan']); ?>
                </div>
                <div class="mb-0">
                    <span class="form-label d-block">Rekening Bank / EDC</span>
                    <div class="border rounded px-2 py-1" style="max-height: 180px; overflow-y: auto;">
                        <?php $kotak_rekening($opsi_bank, $pilihan_bank, 'tambah_rek'); ?>
                    </div>
                    <div class="text-danger small d-none" data-rek-galat>Pilih minimal 1 bank.</div>
                    <div class="form-text">centang rekening yang dipakai toko ini — minimal satu, tidak perlu semuanya</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fal fa-save"></i> Tambahkan</button>
            </div>
            <?= form_close(); ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalSuntingJuragan" data-backdrop="static" tabindex="-1" aria-labelledby="modalSuntingJuraganLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <?= form_open('admin/settings/juragan/update', ['id' => 'mf'], ['id' => '']); ?>
            <div class="modal-header">
                <h5 class="modal-title" id="modalSuntingJuraganLabel">Update Juragan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">

                </button>
            </div>
            <div class="modal-body">

                <div class="mb-3">
                    <?= form_label('Nama Juragan', 'nama_juragan', ['class' => 'form-label']); ?>
                    <?= form_input('nama_juragan', '', ['class' => 'form-control', 'id' => 'nama_juragan', 'required' => '', 'placeholder' => 'nama juragan']); ?>
                </div>
                <div class="mb-3">
                    <span class="form-label d-block">Rekening Bank</span>
                    <div class="border rounded px-2 py-1" style="max-height: 180px; overflow-y: auto;">
                        <?php $kotak_rekening($opsi_bank, [], 'sunting_rek'); ?>
                    </div>
                    <div class="text-danger small d-none" data-rek-galat>Pilih minimal 1 bank.</div>
                    <div class="form-text">centang rekening yang dipakai toko ini — minimal satu, tidak perlu semuanya</div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fal fa-save"></i> Simpan</button>
            </div>
            <?= form_close(); ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPengelola" data-backdrop="static" tabindex="-1" aria-labelledby="modalPengelolaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <?= form_open('admin/settings/juragan/pengelola', ['id' => 'mg']); ?>
            <div class="modal-header">
                <h5 class="modal-title" id="modalPengelolaLabel">Penanggung jawab toko</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">

                </button>
            </div>
            <div class="modal-body">
                <p class="small text-muted" id="peng_sekarang"></p>
                <?= form_hidden('juragan', ''); ?>
                <div class="mb-3">
                    <?= form_label('Admin', 'admin', ['class' => 'form-label']); ?>
                    <?= form_dropdown('admin', $opsi_admin, '', ['class' => 'form-select', 'id' => 'peng_admin']); ?>
                    <div class="form-text">satu toko dipegang satu Admin</div>
                </div>
                <div class="mb-3">
                    <?= form_label('CS', 'cs', ['class' => 'form-label']); ?>
                    <?= form_dropdown('cs', $opsi_cs, '', ['class' => 'form-select', 'id' => 'peng_cs']); ?>
                    <div class="form-text">pilih “— tidak ada —” untuk melepas penanggung jawab</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fal fa-save"></i> Simpan</button>
            </div>
            <?= form_close(); ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<?php

$current_user_id  = $session->get('id');
$link_api_juragan = site_url('api/juragan/by_user/');
$link_invoice     = site_url('admin/invoices/lihat/');
$showModalTambah  = $tambah_err ? 'true' : 'false';

$js = <<< JS
    $(function() {
    	'use strict';
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


    	var modalSuntingJuragan=document.getElementById('modalSuntingJuragan');
    	modalSuntingJuragan.addEventListener('show.bs.modal',function(event){
    		const button = event.relatedTarget;
            const parent = button.closest('tr');
            const data = parent.dataset.arr;
            const dataParse = JSON.parse(data);
            const bank = dataParse.bank;
            const juragan = dataParse.j;

            var bankId = [];

            bank.forEach(function(el, index, array){
                bankId.push(String(el.id));
            });

            // rekening toko ini disetel ulang setiap modal dibuka, supaya sisa
            // centangan toko sebelumnya tidak ikut tersimpan
            var kotak = modalSuntingJuragan.querySelectorAll('input[name="bank[]"]');

            kotak.forEach(function (el) {
                el.checked = bankId.indexOf(el.value) !== -1;
            });

    		var modalTitle = modalSuntingJuragan.querySelector('.modal-title');
    		var namaJuragan = modalSuntingJuragan.querySelector('#modalSuntingJuragan input#nama_juragan');
    		var juraganId = modalSuntingJuragan.querySelector('#modalSuntingJuragan input[name="id"]');

    		modalTitle.textContent='Perbarui ' + juragan.nama_juragan,
    		namaJuragan.value=juragan.nama_juragan,
            juraganId.value=juragan.id_juragan;
    	}),
    	modalSuntingJuragan.addEventListener('hide.bs.modal',function(a){
    		document.getElementById('mf').reset();
    	});

    	var modalPengelola=document.getElementById('modalPengelola');
    	modalPengelola.addEventListener('show.bs.modal',function(event){
    		var parent = event.relatedTarget.closest('tr');
    		var data   = JSON.parse(parent.dataset.arr);
    		var pg     = data.pg || {admin: [], cs: []};

    		var nama = function(daftar) {
    			var x = [];
    			daftar.forEach(function (u) { x.push(u.nama); });

    			return x.length === 0 ? 'belum ada' : x.join(', ');
    		};

    		modalPengelola.querySelector('input[name="juragan"]').value = data.j.id_juragan;
    		modalPengelola.querySelector('#peng_admin').value = pg.admin.length === 1 ? pg.admin[0].id : '';
    		modalPengelola.querySelector('#peng_cs').value    = pg.cs.length === 1 ? pg.cs[0].id : '';
    		modalPengelola.querySelector('#peng_sekarang').textContent =
    			data.j.nama_juragan + ' — Admin: ' + nama(pg.admin) + ' | CS: ' + nama(pg.cs);
    	}),
    	modalPengelola.addEventListener('hide.bs.modal',function(a){
    		document.getElementById('mg').reset();
    	});

    	var modalTambahJuragan = document.getElementById('modalTambahJuragan');

    	modalTambahJuragan.addEventListener('hide.bs.modal', function() {
    		document.getElementById('mt').reset();
    	});

    	// minimal satu rekening: ditahan di sini supaya orang tidak perlu mengirim
    	// form dulu untuk tahu daftar centangnya masih kosong. Nama variabel tanpa
    	// awalan dollar: skrip ini hidup di dalam heredoc php.
    	function kawalRekening(formId) {
    		var form = document.getElementById(formId);
    		if (! form) { return; }

    		var pesan = form.querySelector('[data-rek-galat]');

    		function ada() {
    			return form.querySelector('input[name="bank[]"]:checked') !== null;
    		}

    		function tutup() {
    			if (pesan) { pesan.classList.add('d-none'); }
    		}

    		form.addEventListener('submit', function(kejadian) {
    			if (ada()) { tutup(); return; }

    			kejadian.preventDefault();

    			if (pesan) { pesan.classList.remove('d-none'); }

    			var pertama = form.querySelector('input[name="bank[]"]');
    			if (pertama) { pertama.focus(); }
    		});

    		form.addEventListener('change', function() {
    			if (ada()) { tutup(); }
    		});
    	}

    	kawalRekening('mt');
    	kawalRekening('mf');

        // gagal menambah: modal dibuka lagi supaya nama yang tadi diketik tidak hilang
        if ({$showModalTambah}) {
            bootstrap.Modal.getOrCreateInstance(modalTambahJuragan).show();
        }
    });
    JS;

$packer    = new Tholu\Packer\Packer($js, 'Normal', true, false, true);
$packed_js = $packer->pack();
echo '<script>' . $packed_js . '</script>';
?>
<?= $this->endSection() ?>
