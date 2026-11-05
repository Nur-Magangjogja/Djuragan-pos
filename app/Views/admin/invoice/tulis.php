<?php

use CodeIgniter\I18n\Time;

$sekarang = new Time('now');
$session  = \Config\Services::session();
?>

<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    /* Responsif modal Tambah/Edit Orderan saat ukuran Custom dipilih:
       Tampilannya dibuat setengah tinggi layar (50vh), posisi pas di tengah-tengah
       layar (centered), dan isinya bisa digulir sampai isian terakhir serta tombol simpan.
       Hanya berlaku untuk layar responsif (mobile / tablet kecil). */
    @media (max-width: 767.98px) {
        #tambahProduk.is-custom-modal .modal-dialog {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100% - 1rem);
            min-height: calc(100dvh - 1rem);
            margin: 0.5rem auto;
            padding: 0 0.5rem;
        }

        #tambahProduk.is-custom-modal .modal-content {
            height: 50vh;
            height: 50dvh;
            max-height: 50vh;
            max-height: 50dvh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border-radius: 0.85rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.2);
        }

        #tambahProduk.is-custom-modal .modal-header {
            flex-shrink: 0;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        #tambahProduk.is-custom-modal #nambahProduk {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
        }

        #tambahProduk.is-custom-modal .modal-body {
            flex: 1 1 auto;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            padding: 0.85rem 1rem 1.5rem 1rem;
            scrollbar-width: thin;
        }

        #tambahProduk.is-custom-modal .modal-footer {
            flex-shrink: 0;
            padding: 0.6rem 1rem;
            border-top: 1px solid #e2e8f0;
            background: #fff;
        }
    }

    @media (max-width: 767.98px) and (max-height: 550px) {
        #tambahProduk.is-custom-modal .modal-content {
            height: 85vh;
            height: 85dvh;
            max-height: 85vh;
            max-height: 85dvh;
        }
    }

    /* Strip katalog preview sudah dihapus; penanda stok menipis kini duduk di
       dropdown produk modal: opsi ber-stok 1 dicetak merah (teks merah di bawah
       dropdown menemani, karena warna <option> tidak konsisten antar browser). */
    #kode_produk option.stok-menipis {
        color: #b81c16;
        font-weight: 700;
    }
    #kodeProdukInfo.text-danger {
        color: #b81c16 !important;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">

    <div class="judul-sunting">
        <?= anchor('admin/invoices/lihat/semua/semua', '<i class="fal fa-arrow-left"></i>', [
            'class'      => 'tombol-kembali',
            'title'      => 'Kembali ke daftar transaksi',
            'aria-label' => 'Kembali',
        ]) ?>
        <h1 class="h3 mt-5">Tulis Orderan</h1>
    </div>

    <?= form_open('admin/invoices/save', ['class' => 'row mb-5', 'id' => 'iForm']); ?>

    <div class="col-12 col-lg-6 mb-3">
        <div class="h-full rounded-kartu border border-ink-200 bg-white shadow-kartu">
            <div class="flex items-center gap-2.5 rounded-t-kartu border-b border-ink-100 bg-ink-50/60 px-4 py-2.5 sm:px-5">
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-[0.65rem] border border-brand-100 bg-white text-brand-500"><i class="fal fa-store text-xs"></i></span>
                <h6 class="mb-0 text-[0.78rem] font-bold uppercase tracking-[0.06em] text-ink-700">Informasi Orderan</h6>
            </div>
            <div class="form-kartu px-4 py-4 sm:px-5">
                <div class="mb-3">
                    <div class="row gx-2 mb-3">
                        <div class="col">
                            <?= form_label('Juragan', 'juragan', ['class' => 'form-label']); ?>
                            <?= form_dropdown('juragan', ['' => 'Pilih Juragan'], '', ['class' => 'form-select', 'id' => 'juragan', 'required' => '']); ?>
                        </div>
                        <div class="col">
                            <?= form_label('Admin/CS', 'pengguna', ['class' => 'form-label']); ?>
                            <?= form_dropdown('pengguna', ['' => 'Pilih Admin/CS'], '', ['class' => 'form-select', 'id' => 'pengguna', 'required' => '']); ?>
                        </div>
                    </div>

                    <?php /* strip katalog preview sudah dilepas: pemilihan produk
                     kini lewat dropdown di modal tambah item (lihat kode_produk) */ ?>
                    <div class="row gx-2">
                        <div class="col">
                            <?php
                            // get all active labels
                            $labelModel    = new \App\Models\LabelAsal();
                            $options_label = ['' => 'Pilih asal'];

                            foreach ($labelModel->where('status', '1')->orderBy('label', 'asc')->findAll() as $label) {
                                $options_label[$label->id] = $label->label;
                            }
                            ?>
                            <?= form_label('Asal Orderan', 'asal_orderan', ['class' => 'form-label']); ?>
                            <?= form_dropdown('asal_orderan', $options_label, '', ['class' => 'form-select', 'id' => 'asal_orderan', 'required' => '']); ?>
                        </div>
                        <div class="col">
                            <?= form_label('Penanggung Jawab', 'label', ['class' => 'form-label']); ?>
                            <?= form_input('label', '', ['class' => 'form-control', 'id' => 'label', 'placeholder' => 'nama admin/CS', 'maxlength' => '50']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="col-12 col-lg-6 mb-3">
        <div class="h-full rounded-kartu border border-ink-200 bg-white shadow-kartu">
            <div class="flex items-center gap-2.5 rounded-t-kartu border-b border-ink-100 bg-ink-50/60 px-4 py-2.5 sm:px-5">
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-[0.65rem] border border-brand-100 bg-white text-brand-500"><i class="fal fa-user text-xs"></i></span>
                <h6 class="mb-0 text-[0.78rem] font-bold uppercase tracking-[0.06em] text-ink-700">Tanggal &amp; Pelanggan</h6>
            </div>
            <div class="form-kartu px-4 py-4 sm:px-5">
                <div class="mb-3">
                    <?= form_label('Tanggal Order', 'tanggal_order', ['class' => 'form-label']); ?>
                    <?= form_input('tanggal_order', set_value('tanggal_order', $sekarang->toDateString()), ['class' => 'form-control', 'id' => 'tanggal_order', 'required' => '', 'max' => $sekarang->toDateString()], 'date'); ?>
                </div>
                <div class="mb-3">
                    <div class="hidden_id">
                        <?= form_hidden('id_pemesan', ''); ?>
                        <?= form_hidden('id_kirimKe', ''); ?>
                    </div>

                    <!-- pemesan -->
                    <div class="mb-3">
                        <?= form_label('Pemesan', 'cari_pelanggan', ['class' => 'form-label small text-muted mb-1']); ?>
                        <div class="input-group mycustom tambah-dalam form_pemesan">
                            <?= form_input([
                                'class'       => 'form-control cari_pelanggan',
                                'id'          => 'cari_pelanggan',
                                'placeholder' => 'cari data pemesan',
                                'type'        => 'search',
                            ]); ?>
                            <?= form_button([
                                'class'          => 'btn btn-dark',
                                'content'        => '<i class="fal fa-plus"></i> Tambah',
                                'data-bs-target' => '#modalTambahPelanggan',
                                'data-bs-toggle' => 'modal',
                                'data-mode'      => 'tambah_pemesan',
                                'id'             => 'tambah_pelanggan',
                                'title'          => 'Tambah Data Pemesan',
                            ]); ?>
                        </div>

                        <div class="mt-2 info-data-pemesan" style="display: none;">
                            <?= form_button([
                                'class'   => 'btn btn-link text-danger text-decoration-none btn-sm float-end btn-hapus-alamat pemesan',
                                'content' => '<i class="fal fa-trash"></i> <span class="visually-hidden">Hapus</span>',
                                'title'   => 'Hapus Pemesan',
                                'type'    => 'button',
                            ]); ?>
                            <?= form_button([
                                'class'   => 'btn btn-link text-muted text-decoration-none btn-sm float-end btn-edit-alamat pemesan',
                                'content' => '<i class="fal fa-pen"></i> <span class="visually-hidden">Ubah</span>',
                                'title'   => 'Ubah Data Pemesan',
                                'type'    => 'button',
                            ]); ?>
                            <span id="alamat_pemesan" class="d-block border rounded-[0.7rem] border-ink-200 bg-ink-50 p-3"></span>
                        </div>
                    </div>

                    <!-- penerima -->
                    <div>
                        <div class="d-flex align-items-center justify-content-between">
                            <?= form_label('Penerima', 'cari_kirimKe', ['class' => 'form-label small text-muted mb-1']); ?>
                            <?= form_button([
                                'class'    => 'btn btn-link btn-sm p-0 text-decoration-none',
                                'content'  => '<i class="fal fa-copy"></i> Sama dengan Pemesan',
                                'disabled' => '',
                                'id'       => 'salinPemesan',
                                'title'    => 'Ambil data penerima dari pemesan',
                                'type'     => 'button',
                            ]); ?>
                        </div>
                        <div class="input-group mycustom tambah-dalam form_kirimKe">
                            <?= form_input([
                                'class'       => 'form-control cari_pelanggan kirimKe',
                                'id'          => 'cari_kirimKe',
                                'placeholder' => 'cari data penerima',
                                'type'        => 'search',
                            ]); ?>
                            <?= form_button([
                                'class'          => 'btn btn-dark',
                                'content'        => '<i class="fal fa-plus"></i> Tambah',
                                'data-bs-target' => '#modalTambahPelanggan',
                                'data-bs-toggle' => 'modal',
                                'data-mode'      => 'tambah_kirimKe',
                                'id'             => 'tambah_kirimKe',
                                'title'          => 'Tambah Data Penerima',
                            ]); ?>
                        </div>

                        <div class="mt-2 info-data-kirimKe" style="display: none;">
                            <?= form_button([
                                'class'   => 'btn btn-link text-danger text-decoration-none btn-sm float-end btn-hapus-alamat kirimKe',
                                'content' => '<i class="fal fa-trash"></i> <span class="visually-hidden">Hapus</span>',
                                'title'   => 'Hapus Penerima',
                                'type'    => 'button',
                            ]); ?>
                            <?= form_button([
                                'class'   => 'btn btn-link text-muted text-decoration-none btn-sm float-end btn-edit-alamat kirimKe',
                                'content' => '<i class="fal fa-pen"></i> <span class="visually-hidden">Ubah</span>',
                                'title'   => 'Ubah Data Penerima',
                                'type'    => 'button',
                            ]); ?>
                            <span id="alamat_kirimKe" class="d-block border rounded-[0.7rem] border-ink-200 bg-ink-50 p-3"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6 mb-3">
        <div class="h-full rounded-kartu border border-ink-200 bg-white shadow-kartu">
            <div class="flex items-center gap-2.5 rounded-t-kartu border-b border-ink-100 bg-ink-50/60 px-4 py-2.5 sm:px-5">
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-[0.65rem] border border-brand-100 bg-white text-brand-500"><i class="fal fa-tag text-xs"></i></span>
                <h6 class="mb-0 text-[0.78rem] font-bold uppercase tracking-[0.06em] text-ink-700">Jenis Layanan</h6>
            </div>
            <div class="form-kartu px-4 py-4 sm:px-5">
                <div class="row gx-3 gy-3">
                    <div class="col-12 col-sm-6">
                        <?= form_label('Jenis Layanan', 'tipe_pesanan', ['class' => 'form-label']); ?>
                        <?= form_dropdown('rincian[tipe]', ['' => 'Pilih jenis layanan'] + tipe_pesanan(), '', ['class' => 'form-select', 'id' => 'tipe_pesanan']); ?>
                    </div>
                    <div class="col-12 col-sm-6 d-none tipe-buat">
                        <?= form_label('Deadline', 'rincian_deadline', ['class' => 'form-label']); ?>
                        <?= form_input('rincian[deadline]', '', ['class' => 'form-control', 'id' => 'rincian_deadline'], 'date'); ?>
                    </div>
                    <div class="col-12 col-sm-6 d-none tipe-sewa">
                        <?= form_label('Jaminan', 'rincian_jaminan', ['class' => 'form-label']); ?>
                        <?= form_input('rincian[jaminan]', '', ['class' => 'form-control', 'id' => 'rincian_jaminan', 'placeholder' => 'cth: SIM C / KTP']); ?>
                    </div>
                    <div class="col-12 col-sm-6 d-none tipe-sewa">
                        <?= form_label('Tanggal Diambil', 'rincian_ambil', ['class' => 'form-label']); ?>
                        <?= form_input('rincian[ambil]', '', ['class' => 'form-control', 'id' => 'rincian_ambil'], 'date'); ?>
                    </div>
                    <div class="col-12 col-sm-6 d-none tipe-sewa">
                        <?= form_label('Tanggal Kembali', 'rincian_kembali', ['class' => 'form-label']); ?>
                        <?= form_input('rincian[kembali]', '', ['class' => 'form-control', 'id' => 'rincian_kembali'], 'date'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6 mb-3">
        <div class="h-full rounded-kartu border border-ink-200 bg-white shadow-kartu">
            <div class="flex items-center gap-2.5 rounded-t-kartu border-b border-ink-100 bg-ink-50/60 px-4 py-2.5 sm:px-5">
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-[0.65rem] border border-brand-100 bg-white text-brand-500"><i class="fal fa-users text-xs"></i></span>
                <h6 class="mb-0 text-[0.78rem] font-bold uppercase tracking-[0.06em] text-ink-700">Kategori Pesanan</h6>
            </div>
            <div class="form-kartu px-4 py-4 sm:px-5">
                <div class="row gx-3">
                    <div class="col-12 col-sm-6">
                        <?= form_label('Kategori Pesanan', 'tipe_orderan', ['class' => 'form-label']); ?>
                        <?= form_dropdown('rincian[orderan]', ['satuan' => 'Satuan', 'rombongan' => 'Rombongan'], 'satuan', ['class' => 'form-select', 'id' => 'tipe_orderan']); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 mb-3">
        <div class="card mb-3 overflow-hidden rounded-kartu border-ink-200 shadow-kartu">
            <div class="card-header border-ink-100 bg-ink-50/60 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-[0.65rem] border border-brand-100 bg-white text-brand-500"><i class="fal fa-receipt text-xs"></i></span>
                    <h6 class="mb-0 text-[0.78rem] font-bold uppercase tracking-[0.06em] text-ink-700">Orderan</h6>
                </div>
                <a href="#!" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#tambahProduk">
                    <i class="fal fa-plus"></i> Tambah Orderan
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover tabel-patok">
                        <colgroup>
                            <col class="k-produk"><col class="k-harga"><col class="k-qty">
                            <col class="k-subtotal"><col class="k-aksi">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col">Produk</th>
                                <th scope="col">Harga</th>
                                <th scope="col">QTY</th>
                                <th scope="col" class="text-end">Subtotal</th>
                                <th scope="col" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="list-orderan" data-length="1">
                            <tr class="orderan-kosong">
                                <td class="text-center" colspan="5">
                                    <div class="py-5"><i class="fad text-warning fa-<?= random_element(['shopping-cart', 'shopping-bag', 'shopping-basket', 'bags-shopping', 'dolly-flatbed-empty', 'dolly-empty']) ?> fa-4x"></i>
                                        <p class="mb-0"><?= random_element(['orderan kosong?', 'isi dulu orderannya ya?', 'jangan lupa isi orderannya ya?']) ?></p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="customBiaya d-none listBiaya">
                            <tr>
                                <td colspan="3" class="text-end">Subtotal</td>
                                <td class="text-end" data-totalbiaya="0" data-subtotal="0" id="subTotal"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="border-bottom pb-3 customBiaya d-none">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#biayaOrder" data-biayaID="1" data-judul="Ongkir" data-operasi="1"><i class="fal fa-plus"></i> Ongkir</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-target="#biayaOrder" data-bs-toggle="modal" data-biayaID="2" data-judul="Lain-lain" data-operasi="1"><i class="fal fa-plus"></i> Biaya Lain</button>
                </div>
                <div class="customBiaya d-none">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold">TOTAL</h6>
                        <div class="h2 text-primary" id="grandTotal"></div>
                    </div>
                </div>
            </div>
        </div>

        <?= anggota_panel_form() ?>

        <?= foto_panel_form() ?>

        <div class="form-kartu mb-3">
            <?= form_label('Note / Keterangan', 'keterangan', ['class' => 'form-label']); ?>
            <?= form_textarea(['name' => 'keterangan', 'id' => 'keterangan', 'class' => 'form-control', 'rows' => '3', 'placeholder' => 'opsional']); ?>
        </div>

        <hr class="my-4 border-0 border-t border-ink-200 opacity-100">
        <button type="submit" class="btn btn-primary w-100 rounded-[0.75rem] py-2.5 text-uppercase">
            <i class="fal fa-save"></i> Simpan
        </button>
    </div>
    <?= form_close(); ?>

</div>

<?= $this->endSection() ?>

<?= $this->section('modal') ?>
<!-- Modal tambah pelanggan -->
<div class="modal fade" id="modalTambahPelanggan" data-backdrop="static" tabindex="-1" aria-labelledby="modalTambahPelangganLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <?= form_open('', ['id' => 'tambahPelanggan', 'class' => 'modal-content']); ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalTambahPelangganLabel"></h5>
            <button type="button" data-reset="false" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
		<div class="modal-body">
			<?= form_hidden('id_pelanggan', ''); ?>
			<!-- ===== DATA PEMESAN ===== -->
			<div class="mb-3 blk-pemesan">
				<h6 class="text-muted small fw-bold text-uppercase mb-2">Data Pemesan</h6>
				<?= form_label('Nama Pelanggan', 'nama_pelanggan', ['class' => 'form-label']); ?>
				<?= form_input('nama_pelanggan', '', [
					'class' => 'form-control input',
					'id' => 'nama_pelanggan',
					'required' => '',
					'placeholder' => 'nama pelanggan',
					'autocomplete' => 'off'
				]); ?>
				<div id="auto-results"></div>
			</div>
			<div class="row gx-2 mb-3 blk-pemesan">
				<div class="col-6">
					<?= form_label('HP 1', 'hp1', ['class' => 'form-label']); ?>
					<?= form_input([
						'class' => 'form-control input hp-num',
						'id' => 'hp1',
						'name' => 'hp[0]',
						'placeholder' => 'HP',
						'required' => '',
						'autocomplete' => 'off',
						'inputmode' => 'numeric',
						'maxlength' => '13',
					]) ?>
				</div>
				<div class="col-6">
					<?= form_label('HP 2', 'hp2', ['class' => 'form-label']); ?>
					<?= form_input([
						'class' => 'form-control input hp-num',
						'id' => 'hp2',
						'name' => 'hp[1]',
						'placeholder' => 'HP 2 - opsional',
						'autocomplete' => 'off',
						'inputmode' => 'numeric',
						'maxlength' => '13',
					]) ?>
				</div>
			</div>

			<!-- ===== DATA PENERIMA ===== -->
			<div class="mb-3 blk-penerima border-top pt-3">
				<h6 class="text-muted small fw-bold text-uppercase mb-2">Data Penerima</h6>

				<?= form_label('Nama Penerima', 'nama_penerima', ['class' => 'form-label']); ?>
				<?= form_input('nama_penerima', '', [
					'class' => 'form-control input',
					'id' => 'nama_penerima',
					'required' => '',
					'placeholder' => 'nama penerima',
					'autocomplete' => 'off'
				]); ?>
			</div>
			<div class="row gx-2 mb-3 blk-penerima">
				<div class="col-6">
					<?= form_label('HP 1', 'hp_penerima1', ['class' => 'form-label']); ?>
					<?= form_input([
						'class' => 'form-control input hp-num',
						'id' => 'hp_penerima1',
						'name' => 'hp_penerima[0]',
						'placeholder' => 'HP',
						'required' => '',
						'autocomplete' => 'off',
						'inputmode' => 'numeric',
						'maxlength' => '13',
					]) ?>
				</div>
				<div class="col-6">
					<?= form_label('HP 2', 'hp_penerima2', ['class' => 'form-label']); ?>
					<?= form_input([
						'class' => 'form-control input hp-num',
						'id' => 'hp_penerima2',
						'name' => 'hp_penerima[1]',
						'placeholder' => 'HP 2 - opsional',
						'autocomplete' => 'off',
						'inputmode' => 'numeric',
						'maxlength' => '13',
					]) ?>
				</div>
			</div>

			<!-- ===== COD + ALAMAT: kolomnya selalu ada; COD hanya mematikan isian alamat ===== -->
			<div class="blk-alamat">
			<div class="mb-3">
				<div class="form-check form-switch">
					<?= form_label('COD', 'cod', ['class' => 'form-check-label']) ?>
					<?= form_checkbox('cod', 'ya', true, ['class' => 'form-check-input swictCOD', 'id' => 'cod']) ?>
				</div>
			</div>

			<div id="nonCOD">
				<div class="mb-3">
					<?= form_label('Alamat', 'alamat', ['class' => 'form-label']); ?>
					<?= form_textarea([
						'name' => 'alamat',
						'class' => 'form-control input',
						'id' => 'alamat',
						'required' => '',
						'placeholder' => 'RT/RW, Nama Kampung/Perumahan, No Rumah, Desa/Kelurahan',
						'rows' => '3',
						'disabled' => ''
					]); ?>
				</div>

				<div class="row gx-2 mb-3">
					<div class="col">
						<?= form_label('Provinsi', 'provinsi', ['class' => 'form-label']); ?>
						<?= form_dropdown('provinsi', ['' => 'Pilih Provinsi'], '', [
							'class' => 'form-select input',
							'id' => 'provinsi',
							'required' => '',
							'disabled' => ''
						]); ?>
					</div>
					<div class="col">
						<?= form_label('Kab/Kota', 'kabupaten', ['class' => 'form-label']); ?>
						<?= form_dropdown('kabupaten', ['' => 'Pilih Kab/Kota'], '', [
							'class' => 'form-select input',
							'id' => 'kabupaten',
							'required' => '',
							'disabled' => ''
						]); ?>
					</div>
					<div class="col">
						<?= form_label('Kecamatan', 'kecamatan', ['class' => 'form-label']); ?>
						<?= form_dropdown('kecamatan', ['' => 'Pilih Kecamatan'], '', [
							'class' => 'form-select input',
							'id' => 'kecamatan',
							'required' => '',
							'disabled' => ''
						]); ?>
					</div>
				</div>

				<div class="row gx-2 mb-3">
					<div class="col-4">
						<?= form_label('Kode Pos', 'kodepos', ['class' => 'form-label']); ?>
						<?= form_input('kodepos', '', [
							'class' => 'form-control input',
							'id' => 'kodepos',
							'placeholder' => 'xxxxx',
							'autocomplete' => 'off',
							'inputmode' => 'numeric',
							'maxlength' => '5',
							'required' => '',
							'disabled' => ''
						]); ?>
					</div>
				</div>
			</div>
			</div>
		</div>
        <div class="modal-footer">
            <button type="button" data-reset="false" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>

            <button type="button" disabled class="btn loading btn-primary" style="display:none">
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Simpan
            </button>
            <button type="submit" class="btn submit btn-primary">
                <i class="fal fa-save"></i> Simpan
            </button>
        </div>
        <?= form_close(); ?>
    </div>
</div>

<!-- Modal tambah biaya -->
<div class="modal fade" id="biayaOrder" data-backdrop="static" tabindex="-1" aria-labelledby="biayaOrderLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="biayaOrderLabel">Modal title</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">

                </button>
            </div>
            <?= form_open('', ['id' => 'tambahBiaya'], ['biayaId' => '']); ?>
            <div class="modal-body">
                <div class="mb-3">
                    <?= form_label('Nominal', 'nominalBiaya', ['class' => 'form-label']); ?>
                    <?= form_input(['name' => 'nominal_biaya', 'id' => 'nominalBiaya', 'class' => 'form-control', 'required' => '', 'placeholder' => 'cth: ' . random_element(['10000', '20000', '25000', '30000']), 'type' => 'number']); ?>
                    <div class="form-text" id="biayaHelp">Gunakan tanda ( <b>-</b> ) untuk mengurangi. misal untuk diskon: <b>-2000</b></div>
                </div>

                <div class="mb-3">
                    <?= form_label('Label', 'labelBiaya', ['class' => 'form-label']); ?>
                    <?= form_input(['name' => 'label_biaya', 'id' => 'labelBiaya', 'class' => 'form-control', 'placeholder' => 'label biaya - opsional, max: 20 karakter', 'maxlength' => '20']); ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                <button type="submit" id="submit-form" class="btn btn-primary addBiaya">Tambahkan</button>
            </div>
            <?= form_close(); ?>
        </div>
    </div>
</div>

<!-- Modal tambah produk -->
<div class="modal fade" id="tambahProduk" data-backdrop="static" tabindex="-1" aria-labelledby="tambahProdukLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tambahProdukLabel">Tambah Orderan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">

                </button>
            </div>
            <?= form_open('', ['id' => 'nambahProduk']); ?>
            <div class="modal-body">
                <?= stok_panel_form() ?>
                <div class="row gx-2 gy-2">
                    <div class="col-12">
                        <?= form_label('Produk', 'kode_produk', ['class' => 'form-label']); ?>
                        <?= '<select name="kode_produk" id="kode_produk" class="form-select" required="">';
                        echo '<option value="">Pilih produk</option>';
                        echo '</select>'; ?>
                        <div class="form-text" id="kodeProdukInfo"></div>
                    </div>
                    <div class="col-6 col-sm-6 col-md-3">
                        <?= form_label('Harga Satuan', 'harga_satuan', ['class' => 'form-label']); ?>
                        <?= form_input(['name' => 'harga_satuan', 'min' => '0', 'id' => 'harga_satuan', 'class' => 'form-control', 'required' => '', 'placeholder' => 'cth: ' . random_element(['225000', '280000', '295000', '320000']), 'type' => 'number']); ?>
                    </div>
                    <div class="col-6 col-sm-6 col-md-3">
                        <?= form_label('Ukuran', 'ukuran', ['class' => 'form-label']); ?>
                        <?= '<select name="ukuran" class="form-select" id="ukuran" required="">';
                        echo '<option value="" disabled="" selected="">Pilih ukuran</option>';

                        foreach (config('JuraganConfig')->size as $k => $v) {
                            echo '<optgroup label="' . $k . '">';

                            foreach ($v as $k2 => $v2) {
                                echo '<option value="' . $k2 . '">' . $v2 . '</option>';
                            }
                            echo '</optgroup>';
                        }
                        echo '<option value="custom">Custom</option>';
                        echo '</select>';
                        ?>
                    </div>
                    <div class="col-6 col-sm-6 col-md-3">
                        <?= form_label('QTY', 'QTY', ['class' => 'form-label']); ?>
                        <?= form_input(['name' => 'QTY', 'id' => 'QTY', 'class' => 'form-control', 'required' => '', 'placeholder' => 'cth: ' . mt_rand(1, 20), 'type' => 'number', 'min' => '1']); ?>
                    </div>
                </div>

                <div class="row gx-2 mt-1 d-none rounded-[0.85rem] border border-dashed border-[#dbe3ee] bg-ink-50 px-[0.85rem] pb-[0.6rem] pt-[0.4rem] [&_h6]:text-[0.78rem] [&_h6]:font-bold [&_h6]:uppercase [&_h6]:tracking-[0.06em] [&_h6]:text-ink-600 [&_.fal]:text-brand-500 [&_.form-label]:text-xs [&_.form-label]:text-ink-500" id="customDetail">
                    <div class="col-12">
                        <h6 class="mb-2 mt-3 fw-bold">
                            <i class="fal fa-ruler-combined text-danger"></i> Spesifikasi Custom
                            <span class="fw-normal text-muted small">- opsional, hanya untuk pesanan custom</span>
                        </h6>
                    </div>
                    <?= ukuran_form_panel(['id' => 'ukuranModal']) ?>
                    <div class="col-sm-6 mb-2">
                        <?= form_label('Ukuran Jadi', 'ri_ukuran_jadi', ['class' => 'form-label']); ?>
                        <input type="text" class="form-control ri-field" id="ri_ukuran_jadi" data-key="ukuran_jadi" placeholder="cth: L / PM" autocomplete="off">
                    </div>
                    <div class="col-sm-6 mb-2">
                        <?= form_label('Nama Pemilik Ukuran', 'ri_pemilik', ['class' => 'form-label']); ?>
                        <input type="text" class="form-control ri-field" id="ri_pemilik" data-key="pemilik" placeholder="cth: AFRIZAL" autocomplete="off">
                    </div>
                    <div class="col-sm-6 mb-2">
                        <?= form_label('Bahan', 'ri_bahan', ['class' => 'form-label']); ?>
                        <input type="text" class="form-control ri-field" id="ri_bahan" data-key="bahan" placeholder="cth: JETBLACK" autocomplete="off">
                    </div>
                    <div class="col-sm-6 mb-2">
                        <?= form_label('Model & Jahitan', 'ri_spesifikasi', ['class' => 'form-label']); ?>
                        <input type="text" class="form-control ri-field" id="ri_spesifikasi" data-key="spesifikasi" placeholder="cth: KANCING JAS 2, SAKU BAWAH VARIASI" autocomplete="off">
                    </div>
                    <div class="col-12">
                        <?= form_label('Catatan', 'ri_ukuran_detail', ['class' => 'form-label']); ?>
                        <textarea class="form-control ri-field" id="ri_ukuran_detail" data-key="ukuran_detail" rows="2" placeholder="cth: lengan diperpendek 2 cm, ada kancing cadangan"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary addBiaya">Tambahkan</button>
            </div>
            <?= form_close(); ?>
        </div>
    </div>
</div>

<!-- Modal detail orderan -->
<div class="modal fade" id="detailOrderan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Orderan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl id="detailIsi" class="mb-0"></dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<?php

$current_user_id     = $session->get('id');
$link_api_invoice    = site_url('admin/invoices/save');
$link_api_juragan    = site_url('api/juragan/by_user/');
$link_api_kecamatan  = site_url('rajaongkir/kecamatan');
$link_api_kota       = site_url('rajaongkir/kota');
$link_api_pengguna   = site_url('api/juragan/get_users/');
$link_api_provinsi   = site_url('rajaongkir/provinsi');
$link_cari_pelanggan = site_url('pelanggan/cari');
$link_invoice        = site_url('admin/invoices/lihat/');
$link_post_pelanggan = site_url('pelanggan/baru');
$link_data_pelanggan = rtrim(site_url('pelanggan/data'), '/') . '/';
$link_stok_jumlah    = site_url('api/stok/jumlah');
$link_stok_katalog   = site_url('api/stok/katalog');
// nama field token CSRF: tidak boleh ikut disimpan/dipulihkan draf, sebab tiap
// muat halaman token-nya berganti dan menimpa yang baru bikin simpan ditolak.
$nama_csrf           = csrf_token();

?>
<script>
    window.UKURAN_FORM = <?= ukuran_form_data() ?>;
    window.CARI_PELANGGAN = '<?= $link_cari_pelanggan ?>';
</script>
<script defer src="<?= base_url('assets/js/ukuran-form.js') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/js/ukuran-form.js') ?>"></script>
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script defer src="<?= base_url('assets/js/anggota-form.js') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/js/anggota-form.js') ?>"></script>
<script defer src="<?= base_url('assets/js/cari-pelanggan.js') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/js/cari-pelanggan.js') ?>"></script>
<script defer src="<?= base_url('assets/js/foto-form.js') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/js/foto-form.js') ?>"></script>
<script defer src="<?= base_url('assets/js/ambil-stok.js') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/js/ambil-stok.js') ?>"></script>

<?php

$js = <<< JS
    $(function() {
    	'use strict';
    	// collapse sidebar

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


    	// salin data cuma bisa dipakai setelah ada pemesan
    	function sinkronSalin() {
    		$('#salinPemesan').prop('disabled', $('.hidden_id [name="id_pemesan"]').val() === '');
    	}

    	// hapus kartu pemesan / penerima, kotak pencariannya muncul lagi
    	$('.btn-hapus-alamat').on('click', function(){
    		var nama = $(this).hasClass("kirimKe") ? 'kirimKe' : 'pemesan';

    		$('#alamat_' + nama).empty();
    		$('.hidden_id [name="id_' + nama + '"]').val('');
    		$('.info-data-' + nama).hide();
    		$('.form_' + nama).show().find('.cari_pelanggan').val('');

    		sinkronSalin();
    	});

    	// penerima boleh orang yang sama dengan pemesan
    	$('#salinPemesan').on('click', function(){
    		var kartu = $('#alamat_pemesan').clone();
    		kartu.children('h6').text('Penerima');

    		$('#alamat_kirimKe').html(kartu.contents());
    		$('.hidden_id [name="id_kirimKe"]').val($('.hidden_id [name="id_pemesan"]').val());
    		$('.form_kirimKe').hide();
    		$('.info-data-kirimKe').show();
    	});

    	// satu nama dipilih dari daftar pencarian
    	$(document).on('pelanggan:terisi', sinkronSalin);

    	// Enter pada nama yang belum ada di daftar: modal diisi nama itu, tinggal lengkapi
    	$(document).on('pelanggan:buat', function(e, targetNama, nama){
    		var kirim = targetNama === 'kirimKe';

    		$('#nama_' + (kirim ? 'penerima' : 'pelanggan')).val(nama);
    		$('#tambah_' + (kirim ? 'kirimKe' : 'pelanggan')).trigger('click');
    	});

    	sinkronSalin();


    	// load juragan
    	// embed langsung ke form select - dropdown
    	$.getJSON('{$link_api_juragan}', { id: {$current_user_id} }, function(b){
    		$.each(b[{$current_user_id}].juragan,function(c,x){
    			$('<option />',{value:x.id,text:x.nama}).data('nama', x.nama).appendTo($('select[name="juragan"]'));
    		});
    		// opsi tiba: pasang pilihan dari draf yang tadi ditahan
    		if (window.drafTundaJuragan) { window.drafTundaJuragan(); }
    	});

    	// dropdown produk di modal tambah item: isi katalog toko terpilih, tiap
    	// opsi membawa nama, harga awal, dan stok; stok tinggal 1 ditandai merah
    	function muatDropdownProduk() {
    		var sel = $('#kode_produk');
    		if (sel.length === 0 || sel[0].tagName !== 'SELECT') {
    			return;
    		}

    		var j = parseInt($('select[name="juragan"]').val(), 10) || 0,
    			lama = sel.data('pending') || sel.val();

    		sel.removeData('pending');
    		sel.find('option').slice(1).remove();

    		if (j < 1) {
    			return;
    		}

    		$.getJSON('{$link_stok_katalog}', { juragan_id: j }, function(res) {
    			var data = (res && res.data) || [],
    				seen = {};

    			$.each(data, function(i, p) {
    				if (seen[p.kode]) {
    					return;
    				}
    				seen[p.kode] = true;

    				var stok = Number(p.stok || 0),
    					teks = p.kode + ' \u00B7 Rp ' + Number(p.harga || 0).toLocaleString('id-ID') + ' \u00B7 Stok ' + stok;

    				$('<option/>', { value: p.kode, text: teks })
    					.data('harga', p.harga || 0)
    					.data('stok', stok)
    					.toggleClass('stok-menipis', stok === 1)
    					.appendTo(sel);
    			});

    			// baris manual / edit: kode di luar katalog tetap bisa terpilih lagi
    			if (lama && sel.find('option[value="' + window.CSS.escape(lama) + '"]').length === 0) {
    				$('<option/>', { value: lama, text: lama + ' \u00B7 (di luar katalog)' }).data('harga', '').data('stok', '').appendTo(sel);
    			}

    			if (lama) {
    				sel.val(lama);
    				tataInfoStok();
    			}
    		});
    	}

    	// harga katalog ikut tersalin saat produk dipilih; masih boleh dinegosiasi.
    	// nama variabel js di blok ini tanpa awalan $: seluruh skrip hidup dalam
    	// heredoc php, jadi nama berawalan dollar di sini akan diinterpolasi sbg variabel php
    	$(document).on('change', '#kode_produk', function() {
    		var opsi = $(this).find('option:selected');
    		if (opsi.data('harga') !== undefined && String(opsi.data('harga')) !== '') {
    			$('#harga_satuan').val(opsi.data('harga'));
    		}
    		tataInfoStok();
    	});

    	// penanda stok menipis juga ditulis di bawah dropdown: warna <option>
    	// tidak konsisten antar browser, jadi teks merah inilah yang dijamin tampil
    	function tataInfoStok() {
    		var opsi = $('#kode_produk').find('option:selected'),
    			stok = opsi.data('stok'),
    			info = $('#kodeProdukInfo');

    		if (stok === undefined || String(opsi.val()) === '') {
    			info.empty().removeClass('text-danger fw-semibold');
    			return;
    		}

    		info.text('Stok tersedia: ' + Number(stok))
    			.toggleClass('text-danger fw-semibold', Number(stok) === 1);
    	}

    	$('#tambahProduk').on('show.bs.modal', function() {
    		muatDropdownProduk();
    	});

    	// ganti toko: pilihan produk lama tidak berlaku lagi
    	$('select[name="juragan"]').on('change', function() {
    		var sel = $('#kode_produk');
    		sel.find('option').slice(1).remove();
    		sel.val('');
    		tataInfoStok();
    	});


    	// load admin/cs
    	// embed langsung ke form select - dropdown
    	$.getJSON('{$link_api_pengguna}', { id: {$current_user_id} }, function(a){
    		$.each(a,function(b,a){
    			$('<option />',{value:a.id,text:a.nama}).appendTo($('select[name="pengguna"]'));
    		});
    		// opsi tiba: pasang pilihan dari draf yang tadi ditahan
    		if (window.drafTundaPengguna) { window.drafTundaPengguna(); }
    	});

    	// modal tambah pelanggan

    	var mpel=document.getElementById('modalTambahPelanggan');

    	// Mode modal: tambah_pemesan / tambah_kirimKe. Judul dan blok nama yang datang
    	// dari tombol pemicunya. Blok COD + alamat TIDAK ikut disembunyikan: pemesan dan
    	// penerima sama-sama punya kolom alamat, dan COD hanya mematikan isian alamatnya.
    	// Kolom yang hilang dari layar bikin orang mengira fiturnya memang tidak ada.
    	function terapkanMode(mode, judul) {
    		var penerima = mode === 'tambah_kirimKe';

    		mpel.querySelector('.modal-title').textContent = judul;
    		$('#tambahPelanggan').removeClass('tambah_pemesan tambah_kirimKe').addClass(mode);

    		$('#tambahPelanggan .blk-pemesan').toggle(!penerima);
    		$('#tambahPelanggan .blk-penerima').toggle(penerima);
    		$('#nama_pelanggan, #hp1').prop('required', !penerima);
    		$('#nama_penerima, #hp_penerima1').prop('required', penerima);

    		$('#nonCOD .input').prop('disabled', $('#cod').prop('checked'));
    	}

    	mpel.addEventListener('show.bs.modal',function(g){
    		var a = g.relatedTarget;

    		// dibuka lewat tombol edit: mode dan isian sudah disiapkan handler edit
    		if (! a) {
    			return;
    		}

    		$('#tambahPelanggan [name="id_pelanggan"]').val('');
    		terapkanMode(a.getAttribute('data-mode'), a.getAttribute('title'));
    	});

    	// daftar provinsi cuma perlu satu kali; dipakai juga waktu mengisi form edit
    	var janjiProvinsi = null;

    	function isiProvinsi() {
    		var selProv = mpel.querySelector('[name="provinsi"]');

    		if (selProv.options.length > 1) {
    			return $.Deferred().resolve().promise();
    		}

    		if (janjiProvinsi) {
    			return janjiProvinsi;
    		}

    		janjiProvinsi = $.getJSON('{$link_api_provinsi}').done(function(a){
    			$.each(a, function(c, v){
    				$('<option />',{value:v.province_id, text:v.province_name}).appendTo(selProv);
    			});
    		});

    		return janjiProvinsi;
    	}

    	function isiKabupaten(provinsi) {
    		return $.getJSON('{$link_api_kota}', { prov: provinsi }).done(function(a){
    			$(kab).empty();
    			$('<option />',{value:'',text:'Pilih Kab/Kota'}).appendTo(kab);
    			$(kec).empty();
    			$('<option />',{value:'',text:'Pilih Kecamatan'}).appendTo(kec);
    			$.each(a,function(c,v){
    				var b = v.city_name;
    				v.type == 'Kota' && (b = v.type + ' ' + v.city_name);
    				$('<option />',{value:v.city_id,text:b}).attr('data-kodepos', v.postal_code).appendTo(kab);
    			});
    		});
    	}

    	function isiKecamatan(kota) {
    		return $.getJSON('{$link_api_kecamatan}', { kota: kota }).done(function(a){
    			$(kec).empty();
    			$('<option />',{value:'',text:'Pilih Kecamatan'}).appendTo(kec);
    			$.each(a,function(c,v){
    				$('<option />',{value:v.subdistrict_id,text:v.subdistrict_name}).appendTo(kec);
    			});
    		});
    	}

    	// pensil di kartu: tarik datanya lalu buka modal dalam mode yang sama, terisi
    	$('.btn-edit-alamat').on('click', function(){
    		var tombol = this,
    			kirim  = $(tombol).hasClass('kirimKe'),
    			nama   = kirim ? 'kirimKe' : 'pemesan',
    			id     = $('.hidden_id [name="id_' + nama + '"]').val(),
    			juragan = parseInt($('select[name="juragan"]').val(), 10) || 0;

    		if (id === '' || ! juragan) {
    			return;
    		}

    		tombol.disabled = true;

    		$.getJSON('{$link_data_pelanggan}' + id, { juragan_id: juragan }).done(function (b) {
    			isiModalEdit(nama, b && b.data);
    		}).always(function () {
    			tombol.disabled = false;
    		});
    	});

    	function isiModalEdit(nama, d) {
    		if (! d) {
    			return;
    		}

    		var kirim = nama === 'kirimKe',
    			cod   = Number(d.cod) === 1,
    			hp    = d.hp || [];

    		terapkanMode(kirim ? 'tambah_kirimKe' : 'tambah_pemesan', kirim ? 'Ubah Data Penerima' : 'Ubah Data Pemesan');

    		$('#nama_' + (kirim ? 'penerima' : 'pelanggan')).val(d.nama);
    		$(kirim ? '#hp_penerima1' : '#hp1').val(hp[0] || '');
    		$(kirim ? '#hp_penerima2' : '#hp2').val(hp[1] || '');
    		$('#tambahPelanggan [name="id_pelanggan"]').val(d.id);

    		// switch COD menentukan isian alamat dipakai atau tidak (mati, bukan hilang)
    		$('#cod').prop('checked', cod).trigger('change');

    		$('#alamat').val(cod ? '' : d.alamat);
    		$('#kodepos').val(cod ? '' : d.kodepos);

    		if (! cod) {
    			isiProvinsi().done(function () {
    				$('#provinsi').val(String(d.provinsi));
    				isiKabupaten(d.provinsi).done(function () {
    					$('#kabupaten').val(String(d.kabupaten));
    					isiKecamatan(d.kabupaten).done(function () {
    						$('#kecamatan').val(String(d.kecamatan));
    					});
    				});
    			});
    		}

    		$(mpel).modal('show');
    	}

    	$(document).on("keyup change", '.swictCOD', function(){
    		// COD aktif: isian alamat DIMATIKAN tapi tetap terlihat. Field yang disabled
    		// juga tidak ikut terkirim, jadi tindakan ke server sama persis seperti
    		// waktu bloknya disembunyikan — hanya layarnya yang kini jujur.
    		$('#nonCOD .input').prop('disabled', this.checked);

    		if (! this.checked) {
    			// daftar wilayah baru dibutuhkan ketika alamatnya jadi dipakai
    			isiProvinsi();
    		}
    	});

    	// nama penerima: huruf saja, spasi dan titik (cth: H. AGUS) boleh
    	$(document).on('input', '#nama_penerima', function() {
    		var v = this.value.replace(/[^A-Za-zÀ-ÿ.'\s]/g, '');

    		if (v !== this.value) {
    			this.value = v;
    		}
    	});

    	// nomor HP & kode pos: angka saja; HP maksimal 13 digit, kode pos 5
    	$(document).on('input', '.hp-num, #kodepos', function() {
    		var maks = this.id === 'kodepos' ? 5 : 13,
    			v = this.value.replace(/\D/g, '');

    		if (v !== this.value || v.length > maks) {
    			this.value = v.slice(0, maks);
    		}
    	});

    	// setelah berhasil disimpan, menutup modal membersihkan form; kalau
    	// belum disimpan isian dibiarkan supaya tidak hilang saat kepepet close
    	$(mpel).on('hidden.bs.modal', function() {
    		if (fpel.data('tersimpan')) {
    			fpel[0].reset();
    			fpel.removeData('tersimpan');
    			$('#auto-results').empty();
    			// reset mengembalikan COD ke posisi aktif, jadi isian alamat dimatikan lagi
    			$('#nonCOD .input').prop('disabled', true);
    		}
    	});

    	var kab=mpel.querySelector('#modalTambahPelanggan [name="kabupaten"]'),
    		kec=mpel.querySelector('#modalTambahPelanggan [name="kecamatan"]'),
    		fpel=$('#tambahPelanggan'),
    		request_pelanggan;

    	fpel.on('submit',function(event){
    		// Prevent default posting of form - put here to work in case of errors
    		event.preventDefault();

    		// Abort any pending request
    		if (request_pelanggan) {
    			request_pelanggan.abort();
    		}
    		// setup some local variables
    		var form = $(this);

    		// Let's select and cache all the fields
    		var inputs = form.find("input, select, button, textarea");

    		// disalin sebelum input dinonaktifkan
    		var formFields = form.serializeArray();

    		// dari tombol tambah di baris kirim: data penerima dikirim pakai nama
    		// field pemesan, karena endpoint baru hanya membaca nama_pelanggan + hp[]
    		if (form.hasClass('tambah_kirimKe')) {
    			var ganti = {
    				'nama_pelanggan': form.find('[name="nama_penerima"]').val(),
    				'hp[0]': form.find('[name="hp_penerima[0]"]').val(),
    				'hp[1]': form.find('[name="hp_penerima[1]"]').val()
    			};

    			formFields = $.map(formFields, function (f) {
    				return Object.prototype.hasOwnProperty.call(ganti, f.name)
    					? { name: f.name, value: ganti[f.name] }
    					: f;
    			});
    		}

    		var serializedData = $.param(formFields);

    		// Let's disable the inputs for the duration of the Ajax request.
    		// Note: we disable elements AFTER the form data has been serialized.
    		// Disabled form elements will not be serialized.
    		inputs.prop("disabled", true);

    		// Fire off the request to /form.php
    		request_pelanggan = $.ajax({
    			url: "{$link_post_pelanggan}",
    			type: "post",
    			data: serializedData
    		});

    		// Callback handler that will be called on success
    		request_pelanggan.done(function (response, textStatus, jqXHR){
    			function tulisInfo(r) {
    				var c='';
    				c+='<span class="d-block fw-bold">'+r.nama_pelanggan+'</span>',
    				c+='<span class="d-block">';
    				$.each(r.hp, function (i,v){
    					if(i==1) {
    						c+='<span> / </span>';
    					}
    					c+=v;
    				});
    				c+='</span>';
    				c+='<span class="d-block">';
    				if (r.cod === '0') {
    					c+=r.alamat+', '+ r.nama_kecamatan+', '+r.nama_kabupaten +', '+ r.nama_provinsi+', '+r.kodepos;
    				}
    				else {
    					c+= 'C.O.D';
    				}
    				c+='</span>';
    				return c;
    			}

    			var c = tulisInfo(response),
    				kirim = form.hasClass('tambah_kirimKe'),
    				nama = kirim ? 'kirimKe' : 'pemesan',
    				lain = kirim ? 'pemesan' : 'kirimKe';

    			fpel.data('tersimpan', true);

    			$('#alamat_' + nama).empty().append('<h6 class="text-muted fw-normal">' + (kirim ? 'Penerima' : 'Pemesan') + '</h6>' + c);
    			$('.hidden_id [name="id_' + nama + '"]').val(response.id_pelanggan);
    			$('.form_' + nama).hide();
    			$('.info-data-' + nama).show();

    			// pemesan dan penerima boleh orang yang sama: kartunya ikut diperbarui
    			if ($('.hidden_id [name="id_' + lain + '"]').val() === String(response.id_pelanggan)) {
    				$('#alamat_' + lain).empty().append('<h6 class="text-muted fw-normal">' + (lain === 'kirimKe' ? 'Penerima' : 'Pemesan') + '</h6>' + c);
    			}

    			sinkronSalin();
    			$(mpel).modal('hide');
    		});

    		// Callback handler that will be called on failure
    		request_pelanggan.fail(function (jqXHR, textStatus, errorThrown){
    			// Log the error to the console
    			console.error(
    				"The following error occurred: "+
    				textStatus, errorThrown
    			);
    		});

    		// Callback handler that will be called regardless
    		// if the request failed or succeeded
    		request_pelanggan.always(function () {
    			// Reenable the inputs
    			inputs.prop("disabled", false);
    			// matikan yang perlu dimatikan seperti sebelumnya
    		});
    	}),
    	$('select[name="provinsi"]').on('change',function(){
    		isiKabupaten(this.value);
    	}),
    	$('select[name="kabupaten"]').on('change',function(){
    		isiKecamatan(this.value);
    	});

    	// modal tambah produk

    	function listOrder(){
    		var a=$('.orderan-kosong'),
    			c=$('.customBiaya'),
    			b=$('.list-orderan').data('length');
    		b>1?a.hide():a.show();
    		b>1?c.removeClass('d-none'):c.addClass('d-none');
    	}

    	function newRow(a,b){
    		var c = $('<tr/>');
    		for(var i=0; i<b.length; i++){
    			var d = $('<td/>').append(b[i]);
    			c.append(d);
    		}
    		a.append(c);
    	}

    	function price(n){
    		// untuk menampilkan label harga
    		var a = new Intl.NumberFormat(
    			'id-ID',{
    				style:'currency',
    				currency:'IDR',
    				unitDisplay:'narrow',
    				minimumFractionDigits:0
    				}
    			).format(n);
    		return a;
    	}

    	var pl='';
    	listOrder();

    	// inputan jadwal ikut tipe pesanan: deadline untuk pembuatan, tanggal
    	// ambil/kembali dan jaminan untuk sewa. Data lama bisa punya jadwal tanpa
    	// tipe, jadi saat halaman dibuka field yang tak relevan cuma disembunyikan.
    	function tampilJadwal(hapus) {
    		var t = $('#tipe_pesanan').val(),
    			sewa = $('#rincian_ambil').val() !== '' || $('#rincian_kembali').val() !== '' || $('#rincian_jaminan').val() !== '',
    			buat = t === 'pembuatan' || (!hapus && $('#rincian_deadline').val() !== '');

    		sewa = t === 'sewa' || (!hapus && sewa);

    		$('.tipe-buat').toggleClass('d-none', !buat);
    		$('.tipe-sewa').toggleClass('d-none', !sewa);

    		if (hapus && !buat) {
    			$('#rincian_deadline').val('');
    		}
    		if (hapus && !sewa) {
    			$('#rincian_ambil, #rincian_kembali, #rincian_jaminan').val('');
    		}
    	}

    	tampilJadwal(false);

    	// Tipe layanan menentukan kolam stok yang valid: unit sewa tidak terdaftar
    	// di kolam jual, dan sebaliknya. Baris orderan yang ditandai data-kolam dari
    	// kolam lain ikut dibuang begitu tipe dipindah supaya tidak tertinggal barang
    	// yang stoknya memang tak ada di kolam baru. Baris tulisan manual (data-kolam
    	// kosong) tidak disentuh.
    	function buangBarisKolamLain(tipe) {
    		if (tipe === '') {
    			return;
    		}

    		var kolam = tipe === 'sewa' ? 'sewa' : 'jual',
    			tb = $('.list-orderan'),
    			buang = 0;

    		tb.children('tr[data-kolam]').each(function() {
    			if ($(this).attr('data-kolam') === kolam) {
    				return;
    			}
    			$(this).remove();
    			buang++;
    		});

    		if (buang) {
    			tb.data('length', tb.children('tr[data-kunci]').length + 1);
    			listOrder();
    			subtotal();
    			if (window.STOK && STOK.sinkronFoto) {
    				STOK.sinkronFoto();
    			}
    		}
    	}

    	$('#tipe_pesanan').on('change', function() {
    		tampilJadwal(true);
    		buangBarisKolamLain($(this).val());
    	});

    	// tipe orderan: rombongan membuka tabel Anggota Rombongan, satuan menutupnya
    	function tampilAnggota(buka) {
    		var pnl = $('.anggota-isi').first(),
    			btn = $('.buka-anggota').first();

    		pnl.toggleClass('d-none', !buka);
    		btn.attr('aria-expanded', buka ? 'true' : 'false');
    		btn.find('.ikon-panah-anggota').toggleClass('rotate-180', buka);

    		if (!buka) {
    			pnl.find('.anggota-daftar').empty();
    			$('.jumlah-anggota').text(0);
    		} else {
    			var df = pnl.find('.anggota-daftar').first();
    			if (df.children('.anggota-baris').length === 0) {
    				$('.tambah-anggota').first().trigger('click');
    			}
    		}
    	}

    	// kategori menentukan panel yang tampil, dua arah:
    	// satuan -> tabel rombongan hilang, rombongan -> card orderan hilang
    	function tampilKategori(v) {
    		$('.anggota-isi').first().closest('.card').toggleClass('d-none', v !== 'rombongan');
    		$('.list-orderan').first().closest('.card').toggleClass('d-none', v === 'rombongan');
    	}

    	$('#tipe_orderan').on('change', function() {
    		var v = $(this).val();

    		if (v === 'rombongan') {
    			tampilAnggota(true);
    			tampilKategori(v);
    		}
    		else if (v === 'satuan') {
    			if ($('.anggota-daftar .anggota-baris').length === 0 || confirm('Kosongkan daftar anggota rombongan?')) {
    				tampilAnggota(false);
    				tampilKategori(v);
    			}
    			else {
    				$(this).val('rombongan');
    			}
    		}
    		else {
    			tampilKategori('');
    		}
    	});

    	// default kategori satuan: card rombongan langsung tersembunyi sejak awal
    	tampilKategori($('#tipe_orderan').val());

    	// spesifikasi custom hanya muncul saat ukuran = Custom; ukuran terstruktur
    	// sekarang ikut di dalam panelnya
    	$('#ukuran').on('change', function() {
    		var custom = $(this).val() === 'custom';
    		$('#customDetail').toggleClass('d-none', !custom);
    		$('#tambahProduk').toggleClass('is-custom-modal', custom);
    		if (!custom) {
    			$('#customDetail .ri-field').val('');
    			// panel yang tersembunyi tetap terbaca waktu baris dikirim,
    			// jadi jenis dan angkanya dibuang bersamaan
    			$('#ukuranModal .uk-field').val('');
    			UKURAN.kosongkan('#ukuranModal');
    		}
    	});

    	var fpro=$('#nambahProduk'),
    		rowEdit = null;

    	// panel ukuran disembunyikan di dalam baris: nilainya tetap terkirim dan
    	// hanya bisa diubah lewat tombol Ubah, yang tampil cuma ringkasannya
    	function panelRow(jenis, nilai, k) {
    		var src = $('#ukuranModal').clone(false);

    		src.removeAttr('id')
    			.attr('data-nama', 'produk[' + k + ']')
    			.attr('data-jenis', jenis || '')
    			.attr('data-nilai', JSON.stringify(nilai || {}))
    			.attr('data-lebih', '[]')
    			.addClass('d-none');

    		UKURAN.tempel(src);

    		return src;
    	}

    	// satu baris orderan; dipakai lagi waktu edit supaya kuncinya (k) tetap sama.
    	// `s` id_stok yang sedang terpakai di modal: ikut terkirim supaya nota bisa
    	// mengambil foto produknya, dan tetap tidak ada kalau barangnya ditulis manual.
    	// `kolam` ('jual'/'sewa') menandai baris diambil dari kolam stok mana; disimpan
    	// di data-kolam supaya barisnya bisa dibuang saat tipe layanan dipindah.
    	function bikinBaris(k, d, a, e, b, rincian, jenis, nilai, s, kolam) {
    		var label = d + ' ( ' + String(e).toUpperCase() + ' )',
    			bd = $('<button type="button" class="bg-transparent border-0 detail_row px-1" title="Detail"><i class="fal fa-eye h6 text-secondary"></i></button>'),
    			be = $('<button type="button" class="bg-transparent border-0 edit_row px-1" title="Ubah"><i class="fal fa-pen h6 text-primary"></i></button>'),
    			bt = $('<button type="button" class="bg-transparent border-0 hapus_row orderan px-1" title="Hapus"><i class="fal fa-trash-alt h6 text-danger"></i></button>'),
    			nama = $('<div class="fw-semibold text-truncate"></div>').text(label).attr('title', label),
    			ring = $('<div class="small text-muted text-truncate ukuran-ringkas d-none"></div>'),
    			isi = $('<div class="flex-grow-1"></div>').append(nama).append(ring),
    			t = $('<div class="d-flex align-items-start"></div>').append(isi),
    			f = price(a),
    			aksi = $('<td class="text-end"></td>').append($('<span class="d-inline-flex align-items-center gap-1"></span>').append(bd).append(be).append(bt)),
    			p = $('<div/>', {'class' : 'text-end', 'data-uang1': a * b})
    				.append(price(a * b))
    				.append($('<input/>', {'type':'hidden','name':'produk['+k+'][kode]','value':d}))
    				.append($('<input/>', {'type':'hidden','name':'produk['+k+'][harga]','value':a}))
    				.append($('<input/>', {'type':'hidden','name':'produk['+k+'][ukuran]','value':e}))
    				.append($('<input/>', {'type':'hidden','name':'produk['+k+'][qty]','value':b}))
    				.append(s ? $('<input/>', {'type':'hidden','name':'produk['+k+'][stok_id]','value':s}) : $()),
    			tr = $('<tr/>', {'data-kunci': k, 'data-kolam': kolam || ''})
    				.append($('<td/>').append(t))
    				.append($('<td/>').append(f))
    				.append($('<td/>').append(b))
    				.append($('<td/>').append(p))
    				.append(aksi);

    		if (e === 'custom') {
    			$.each(rincian, function (key, v) {
    				p.append($('<input/>', {'type':'hidden','name':'produk['+k+'][rincian]['+key+']','value':v}));
    			});
    		}

    		if (jenis !== '' || ! $.isEmptyObject(nilai)) {
    			isi.append(panelRow(jenis, nilai, k));
    		}

    		return tr;
    	}

    	// ringkasan ukuran satu baris, dibaca dari panel tersembunyinya
    	function ringkasRow(tr) {
    		var bag = [];

    		tr.find('.uk-field').each(function() {
    			var v = $.trim($(this).val() || ''),
    				s = $(this).data('satuan');

    			if (v !== '') {
    				bag.push($(this).data('label') + ' ' + String(v).replace('.', ',') + (s ? ' ' + s : ''));
    			}
    		});

    		var teks = bag.join(' · ');
    		tr.find('.ukuran-ringkas').first().text(teks).attr('title', teks).toggleClass('d-none', teks === '');
    	}

    	fpro.on('submit',function(c){
    		c.preventDefault(),
    		c.stopPropagation();

    		var d=fpro.find('[name="kode_produk"]').val(),
    			a=fpro.find('[name="harga_satuan"]').val(),
    			e=fpro.find('[name="ukuran"]').val(),
    			b=fpro.find('[name="QTY"]').val(),
    			s=window.STOK ? String(STOK.ambil() || '') : '',
    			kolam=window.STOK && STOK.kolam ? String(STOK.kolam() || '') : '',
    			rincian={},
    			nilai={},
    			jenis=$('#ukuranModal .uk-jenis').val() || '',
    			k = rowEdit ? rowEdit.attr('data-kunci') : uniqId(),
    			tb = $('.list-orderan');

    		fpro.find('#customDetail .ri-field').each(function(){
    			var key = $(this).data('key'),
    				v = $.trim($(this).val() || '');
    			if (key && v !== '') {
    				rincian[key] = v;
    			}
    		});
    		$('#ukuranModal .uk-field').each(function(){
    			var v = $.trim($(this).val() || '');
    			if (v !== '') {
    				nilai[$(this).data('key')] = v;
    			}
    		});

    		var tr = bikinBaris(k, d, a, e, b, rincian, jenis, nilai, s, kolam);

    		if (rowEdit) {
    			rowEdit.replaceWith(tr);
    			rowEdit = null;
    		}
    		else {
    			tb.append(tr);
    			pl=tb.data('length'),
    			tb.data('length',pl+1),
    			listOrder();
    		}

    		// ringkasan ukuran dihitung ulang setelah baris masuk DOM
    		ringkasRow(tr);

    		subtotal();
    		$('#tambahProduk').modal('hide');
    	});

    	$(document).on('click','.hapus_row',function(){
    		this.closest('tr').remove(),
    		$(this).hasClass('orderan')&&(
    			// panjang baru dihitung dari baris yang tersisa, bukan variabel lama:
    			// sebelumnya baris sisa salah, listOrder() ikut menyembunyikan blok
    			// subtotal sehingga total terlihat tidak berkurang
    			$('.list-orderan').data('length', $('.list-orderan').children('tr[data-kunci]').length + 1),
    			listOrder(),
    			subtotal(),
    			window.STOK&&STOK.sinkronFoto&&STOK.sinkronFoto()
    		),
    		totalbiaya();
    	});

    	// klik ubah: isi ulang modal dengan data baris, kunci baris dipertahankan
    	$(document).on('click','.edit_row',function(){
    		var tr = $(this).closest('tr'),
    			d = {},
    			rincian = {},
    			nilai = {},
    			pnl = $('#ukuranModal');

    		tr.find('[name^="produk["]').each(function(){
    			var m = this.name.match(/^produk\[[^\]]+\]\[rincian\]\[([^\]]+)\]$/),
    				m2 = this.name.match(/^produk\[[^\]]+\]\[([^\]]+)\]$/);

    			if (m) {
    				rincian[m[1]] = this.value;
    			}
    			else if (m2) {
    				d[m2[1]] = this.value;
    			}
    		});
    		tr.find('.uk-field').each(function(){
    			var v = $.trim($(this).val() || '');
    			if (v !== '') {
    				nilai[$(this).data('key')] = v;
    			}
    		});

    		(fpro.find('[name="kode_produk"]').data('pending', d.kode).val(d.kode)),
    		fpro.find('[name="harga_satuan"]').val(d.harga),
    		fpro.find('[name="QTY"]').val(d.qty),
    		fpro.find('[name="ukuran"]').val(d.ukuran).trigger('change'),
    		$('#customDetail .ri-field').each(function(){
    			$(this).val(rincian[$(this).data('key')] || '');
    		});

    		pnl.find('.uk-jenis').val(tr.find('.uk-jenis').val() || '').trigger('change');
    		pnl.find('.uk-field').each(function(){
    			var key = $(this).data('key');
    			if (nilai[key] !== undefined) {
    				$(this).val(nilai[key]);
    			}
    		});

    		// baris yang dulu diambil dari stok produk tetap tersambung ke stoknya,
    		// jadi fotonya tidak hilang waktu orderannya disunting
    		if (window.STOK) {
    			STOK.tata(d.stok_id || 0, tr.attr('data-kolam') || '');
    		}

    		rowEdit = tr;
    		$('#tambahProdukLabel').text('Edit Orderan');
    		fpro.find('button[type="submit"]').text('Simpan');
    		$('#tambahProduk').modal('show');
    	});

    	// klik detail: tampilkan isi baris tanpa membuka modal edit
    	function barisDetail(judul, teks) {
    		return $('<div class="d-flex justify-content-between gap-3 border-bottom py-2"></div>')
    			.append($('<dt class="fw-semibold text-muted mb-0"></dt>').text(judul))
    			.append($('<dd class="mb-0 text-end"></dd>').text(teks));
    	}

    	$(document).on('click','.detail_row',function(){
    		var tr = $(this).closest('tr'),
    			d = {},
    			rincian = {},
    			ukuran = [],
    			dl = $('#detailIsi').empty();

    		tr.find('[name^="produk["]').each(function(){
    			var m = this.name.match(/^produk\[[^\]]+\]\[rincian\]\[([^\]]+)\]$/),
    				m2 = this.name.match(/^produk\[[^\]]+\]\[([^\]]+)\]$/);

    			if (m) {
    				rincian[m[1]] = this.value;
    			}
    			else if (m2) {
    				d[m2[1]] = this.value;
    			}
    		});
    		tr.find('.uk-field').each(function(){
    			var v = $.trim($(this).val() || ''),
    				s = $(this).data('satuan');

    			if (v !== '') {
    				ukuran.push($(this).data('label') + ' ' + v + (s ? ' ' + s : ''));
    			}
    		});

    		dl.append(barisDetail('Produk', (d.kode || '') + ' ( ' + String(d.ukuran || '').toUpperCase() + ' )'));
    		dl.append(barisDetail('Harga', price(Number(d.harga) || 0)));
    		dl.append(barisDetail('QTY', d.qty || ''));
    		dl.append(barisDetail('Subtotal', price((Number(d.harga) || 0) * (Number(d.qty) || 0))));

    		var labelRi = {
    			ukuran_jadi: 'Ukuran jadi',
    			pemilik: 'Pemilik ukuran',
    			bahan: 'Bahan',
    			spesifikasi: 'Model & jahitan',
    			ukuran_detail: 'Catatan'
    		};

    		$.each(rincian, function (key, v) {
    			dl.append(barisDetail(labelRi[key] || key, v));
    		});

    		if (ukuran.length) {
    			dl.append(barisDetail('Ukuran', ukuran.join(' · ')));
    		}

    		$('#detailOrderan').modal('show');
    	});

    	var mPro=document.getElementById('tambahProduk');
    	mPro.addEventListener('hidden.bs.modal',function(){
    		var a=$('#nambahProduk');
    		a.removeClass('was-validated')[0].reset();
    		$('#tambahProduk').removeClass('is-custom-modal');
    		$('#customDetail').addClass('d-none');
    		$('#ukuranModal .uk-field').val('');
    		UKURAN.kosongkan('#ukuranModal');
    		$('#tambahProdukLabel').text('Tambah Orderan');
    		a.find('button[type="submit"]').text('Tambahkan');
    		rowEdit = null;
    	});

    	function subtotal(){
    		// hitung subtotal produk
    		var r=$('.list-orderan').find('[data-uang1'),
    			a=0;
    		// console.log(r);
    		r.each(function() {
    			a+=Number($(this).data('uang1'));
    			// console.log($(this).data('uang1'));
    		});
    		$('#subTotal').attr('data-subtotal', a).empty().html(price(a));
    		$(document).trigger('data-attribute-changed');

    		$('.customBiaya').show();
    		// grandtotal();
    	}

    	$(document).on('data-attribute-changed', function() {
    		var a = $('#subTotal')[0]['attributes'],
    			b = a.getNamedItem("data-subtotal")['nodeValue'],
    			c = a.getNamedItem("data-totalbiaya")['nodeValue'],
    			d = parseInt(b) + parseInt(c);

    		$('#grandTotal').empty().html(price(d));
    	});

    	function totalbiaya(){
    		var r=$('.listBiaya').find('[data-biaya]'),
    			a=0;
    		// console.log(r);
    		r.each(function() {
    			a+=Number($(this).data('biaya'));
    			// console.log($(this).data('uang1'));
    		});
    		$('#subTotal').attr('data-totalbiaya', a);
    		$(document).trigger('data-attribute-changed');
    		// grandtotal();
    	}

    	// modal tambah biaya

    	var mBia=document.getElementById('biayaOrder');
    	mBia.addEventListener('show.bs.modal',function(f){
    		var a=f.relatedTarget;
    		var c=a.getAttribute('data-judul');
    		var b=a.getAttribute('data-biayaID');
    		var d=mBia.querySelector('.modal-title');
    		var e=mBia.querySelector('.modal-content [name="biayaId"]');
    		d.textContent='Biaya '+c,b=='1'&&$('#biayaHelp').addClass('d-none'),e.value=b;
    	}),
    	mBia.addEventListener('hidden.bs.modal',function(b){
    		var a=$('#tambahBiaya');
    		a.removeClass('was-validated')[0].reset(),
    		$('#biayaHelp').removeClass('d-none');
    	});

    	var fBia=$('#tambahBiaya');
    	fBia.on('submit',function(f){
    		f.preventDefault(),
    		f.stopPropagation();

    		var a=fBia.find('[name="nominal_biaya"]').val();
    		var c=fBia.find('[name="label_biaya"]').val();
    		var g=fBia.find('[name="biayaId"]').val();
    		var d='';
    		c!=''&&(d='<span class="text-muted ms-1">'+c+'</span>');
    		var b='';
    		switch(parseInt(g)){
    			case 1:b='Ongkir';
    			break;

    			default:b='Lain-lain';
    		}

    		if(parseInt(a)!=0){
    			var e='',
    				u=parseInt(a);
    			u<0&&(e='text-danger');
    			var h=price(u),
    				j = uniqId(),
    				id = $('<input/>',{'type':'hidden','name': 'biaya['+j+'][biaya_id]', 'value': g}),
    				no = $('<input/>',{'type':'hidden','name': 'biaya['+j+'][nominal]', 'value': u}),
    				la = $('<input/>',{'type':'hidden','name': 'biaya['+j+'][label]', 'value': c}),
    				r=$('<tr/>'),
    				c1=$('<td/>', {'colspan': '3', 'class': 'text-end'}).append('<button type="button" class="bg-transparent border-0 hapus_row me-1" aria-label="Close"><span aria-hidden="true"><i class="fal fa-trash-alt h6"></i></span></button>'+b+d),
    				c2=$('<td/>').append('<div data-biaya="'+u+'" class="text-end '+e+'">'+h+'</div>').append(id).append(no).append(la),
    				q = r.append(c1).append(c2).append('<td></td>');
    			$(q).appendTo('.listBiaya');
    			totalbiaya();
    		}
    		$('#biayaOrder').modal('hide');
    	});

    	// main form
    	var emef=$('#iForm');
    	var request;
    	var terkunci = false;

    	emef.on('submit',function(f){
    		f.preventDefault(),
    		f.stopPropagation();

    		// satu klik Simpan = satu orderan. Dulu permintaan yang masih jalan
    		// di-abort lalu dikirim ulang, padahal server tetap menyimpan orderan
    		// pertama — akhirnya satu form melahirkan beberapa orderan sekaligus.
    		if (terkunci) {
    			return false;
    		}

    		// foto ditahan di peramban dan baru naik setelah orderan punya id: kalau
    		// ada yang jelas tidak akan diterima server, orderannya jangan disimpan
    		// dulu — fotonya dihapus atau diganti, baru Simpan lagi
    		if (window.FOTO && window.FOTO.hambatkan() !== '') {
    			return false;
    		}

    		terkunci = true;

    		// setup some local variables
    		var f = $(this);

    		// Let's select and cache all the fields
    		var inputs = f.find("input, select, button, textarea");

    		// Serialize the data in the form
    		var serializedData = f.serialize();

    		// Let's disable the inputs for the duration of the Ajax request.
    		// Note: we disable elements AFTER the form data has been serialized.
    		// Disabled form elements will not be serialized.
    		inputs.prop("disabled", true);

    		// Fire off the request to /form.php
    		request = $.ajax({
    			url: "{$link_api_invoice}",
    			type: "post",
    			data: serializedData
    		});

    		// Callback handler that will be called on success
    		request.done(function (response, textStatus, jqXHR){
    			// orderan tersimpan: draf tidak relevan lagi
    			try { sessionStorage.removeItem('draf_tulis_transaksi'); } catch (e) {}

    			// foto sengaja ditahan peramban sampai orderan ini punya id
    			var id = parseInt(response.id || 0, 10);

    			if (id > 0 && window.FOTO && window.FOTO.ada()) {
    				window.FOTO.kirim(id, response.url);

    				return;
    			}

    			document.location.href = response.url;
    		});

    		// Callback handler that will be called on failure
    		request.fail(function (jqXHR, textStatus, errorThrown){
    			// gagal: kunci dilepas dan kolom dibuka lagi supaya bisa dicoba lagi
    			terkunci = false;
    			inputs.prop("disabled", false);

    			//
    			// console.log( Object.keys(jqXHR['responseJSON']).length);
    			$( Object.entries(jqXHR['responseJSON']) ).each(function( i,v ) {
    				var Tid = 't-'+uniqId();

    				// console.log(v);
    				$('#lTo').append(makeToast(Tid, v[1], 'Galat'));
    				$('#'+Tid).toast('show');
    			});


    			var myToastEl = document.getElementsByClassName('toast')[0];
    			myToastEl.addEventListener('hidden.bs.toast', function () {
    				// do something...
    				$(this).remove();
    			});

    		});

    		// sengaja tidak ada handler "always": setelah sukses kolom memang
    		// dibiarkan terkunci, orderannya sudah jadi dan yang masih menunggu
    		// cuma fotonya — menekan Simpan lagi harus melahirkan orderan baru
    	});

    	function makeToast(id,text,status) {
    		return `<div id="`+id+`" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="5000">
    			<div class="toast-header">
    				<strong class="me-auto">`+status+`</strong>
    				<button type="button" class="ms-2 mb-1 close" data-bs-dismiss="toast" aria-label="Close">

    				</button>
    			</div>
    			<div class="toast-body">`+text+`</div>
    		</div>`;
    	}

    	// disable hit enter
    	// https://stackoverflow.com/a/895231/2094645
    	$(document).on("keydown", ":input:not(textarea)", function(event) {
    		if(event.keyCode == 13) {
    			event.preventDefault();
    			return false;
    		}
    	});

    	function uniqId() {
    		return Math.round(new Date().getTime() + (Math.random() * 100));
    	}

    	// ── Draf isian ─────────────────────────────────────────────────────────
    	// Semua yang diketik/dipilih ditabung ke sessionStorage, jadi menekan F5
    	// (atau tak sengaja menutup lalu membuka lagi di tab yang sama) tidak
    	// menghapus pekerjaan. Yang disimpan: nilai tiap field (statis maupun baris
    	// orderan/biaya/anggota yang punya nama unik), struktur baris dinamis, dan
    	// kartu pemesan/penerima. Foto (file) memang tidak bisa dipulihkan peramban.
    	var DRAF_KUNCI = 'draf_tulis_transaksi';
    	var DRAF_NAMA_CSRF = '{$nama_csrf}';   // field token CSRF: jangan ikut draf
    	var DRAF_umur  = 12 * 60 * 60 * 1000;   // kedaluwarsa 12 jam
    	var drafSiap   = false;                 // tabung hanya setelah pemulihan selesai
    	var drafBuang  = false;                 // sengaja berhenti menulis: jangan ditabung lagi
    	var drafTimer  = null;
    	// juragan & pengguna diisi lewat AJAX; pilihannya ditahan di sini sampai
    	// opsinya tiba, baru dipasang (lihat .done loader masing-masing)
    	var drafTunda  = { juragan: '', pengguna: '' };

    	function bacaDraf() {
    		try {
    			var d = JSON.parse(sessionStorage.getItem(DRAF_KUNCI) || 'null');
    			if (d && d.ts && (Date.now() - d.ts) < DRAF_umur) {
    				return d;
    			}
    		} catch (e) { /* draf rusak: anggap tidak ada */ }
    		return null;
    	}

    	function simpanDraf() {
    		if (!drafSiap || drafBuang) {
    			return;
    		}

    		var v = {};
    		$('#iForm').find('input,select,textarea').each(function () {
    			if (!this.name || this.type === 'file' || this.disabled || this.name === DRAF_NAMA_CSRF) {
    				return;
    			}
    			if (this.type === 'checkbox' || this.type === 'radio') {
    				v[this.name] = this.checked ? '1' : '';
    				return;
    			}
    			v[this.name] = $(this).val();
    		});

    		var draf = {
    			v: v,
    			orderan: $('.list-orderan').html() || '',
    			length: $('.list-orderan').data('length') || 1,
    			biaya: $('.listBiaya').html() || '',
    			anggota: $('.anggota-daftar').html() || '',
    			pemesanHtml: $('#alamat_pemesan').html() || '',
    			kirimKeHtml: $('#alamat_kirimKe').html() || '',
    			pemesanShown: $('.info-data-pemesan').is(':visible'),
    			kirimKeShown: $('.info-data-kirimKe').is(':visible'),
    			ts: Date.now()
    		};

    		try { sessionStorage.setItem(DRAF_KUNCI, JSON.stringify(draf)); } catch (e) { /* kuota penuh: abaikan */ }
    	}

    	function jadwalkanDraf() {
    		clearTimeout(drafTimer);
    		drafTimer = setTimeout(simpanDraf, 250);
    	}

    	function pulihkanDraf() {
    		var d = bacaDraf();
    		if (!d || !d.v) {
    			return;
    		}

    		// struktur baris dinamis dulu, supaya nilainya punya tempat untuk dipasang
    		if (d.orderan) { $('.list-orderan').html(d.orderan); }
    		$('.list-orderan').data('length', d.length || 1);
    		if (d.biaya) { $('.listBiaya').html(d.biaya); }
    		if (d.anggota) { $('.anggota-daftar').html(d.anggota); }

    		// kartu pemesan / penerima
    		if (d.pemesanHtml) { $('#alamat_pemesan').html(d.pemesanHtml); }
    		if (d.kirimKeHtml) { $('#alamat_kirimKe').html(d.kirimKeHtml); }
    		$('.info-data-pemesan').toggle(!!d.pemesanShown);
    		$('.form_pemesan').toggle(!d.pemesanShown);
    		$('.info-data-kirimKe').toggle(!!d.kirimKeShown);
    		$('.form_kirimKe').toggle(!d.kirimKeShown);

    		// pasang nilai tiap field (termasuk uk-field & hidden baris yang baru dirender)
    		$('#iForm').find('input,select,textarea').each(function () {
    			if (!this.name || this.type === 'file' || this.name === DRAF_NAMA_CSRF) {
    				return;
    			}
    			if (!Object.prototype.hasOwnProperty.call(d.v, this.name)) {
    				return;
    			}
    			var val = d.v[this.name];
    			if (this.type === 'checkbox' || this.type === 'radio') {
    				this.checked = val === '1';
    			} else {
    				$(this).val(val);
    			}
    		});

    		// juragan & pengguna opsinya masih dimuat lewat AJAX → tahan dulu, dipasang
    		// oleh .done loader-nya begitu opsinya tiba
    		drafTunda.juragan  = d.v.juragan  || '';
    		drafTunda.pengguna = d.v.pengguna || '';

    		// tipe layanan & kategori menentukan panel yang tampil
    		tampilJadwal(false);
    		var ord = $('#tipe_orderan').val();
    		if (ord === 'rombongan') { tampilAnggota(true); }
    		tampilKategori(ord);

    		$('.list-orderan tr[data-kunci]').each(function () { ringkasRow($(this)); });

    		var adaIsi = ($('.list-orderan tr[data-kunci]').length > 0) || ($('.listBiaya [data-biaya]').length > 0);
    		listOrder();
    		if (adaIsi) { subtotal(); totalbiaya(); }
    		sinkronSalin();
    	}

    	// dipasang oleh loader AJAX begitu opsinya tiba (lihat .done juragan/pengguna)
    	window.drafTundaJuragan = function () { if (drafTunda.juragan) { $('select[name="juragan"]').val(drafTunda.juragan); } };
    	window.drafTundaPengguna = function () { if (drafTunda.pengguna) { $('select[name="pengguna"]').val(drafTunda.pengguna); } };

    	// tiap ketikan/pilihan & perubahan struktur (baris, kartu, opsi) ditabung
    	$('#iForm').on('input change', jadwalkanDraf);
    	if (window.MutationObserver) {
    		new MutationObserver(jadwalkanDraf).observe(
    			document.getElementById('iForm'),
    			{ childList: true, subtree: true, characterData: true }
    		);
    	}

    	// ── Kapan draf boleh dipulihkan ─────────────────────────────────
    	// Aturannya: menyegarkan halaman (F5 / tombol reload) = pekerjaan lama
    	// dikembalikan; pergi dari layar ini = mulai dari nol. Membuka Tulis Orderan
    	// lagi, menekan tautan "kembali", atau back/forward peramban semuanya tercatat
    	// sebagai navigasi selain 'reload', jadi draf lama dibuang, bukan dipasang.
    	var drafCara = '';

    	if (window.performance && performance.getEntriesByType) {
    		var drafNav = performance.getEntriesByType('navigation')[0];
    		drafCara = drafNav && drafNav.type ? drafNav.type : '';
    	}

    	function buangDraf() {
    		try { sessionStorage.removeItem(DRAF_KUNCI); } catch (e) { /* mode privat */ }
    	}

    	// berhenti total: draf dibuang DAN isian baru tidak ditabung lagi, supaya
    	// beforeunload di bawahnya tidak menulis ulang pekerjaan yang baru saja
    	// dibatalkan. Layar berikutnya (setelah reload/navigasi) mulai bersih lagi.
    	function berhentiMenulis() {
    		drafBuang = true;
    		buangDraf();
    	}

    	// peramban yang tidak memberi tahu caranya halaman ini dibuka diperlakukan
    	// seperti refresh: lebih baik draf tertinggal daripada pekerjaan hilang.
    	// Yang dibuang di sini hanya isian lama; penulisan baru tetap didraf.
    	if (drafCara === '' || drafCara === 'reload') {
    		pulihkanDraf();
    	} else {
    		buangDraf();
    	}

    	drafSiap = true;

    	// flush terakhir saat tab ditutup/di-refresh, jangan sampai ketinggal debounce
    	$(window).on('beforeunload', simpanDraf);

    	// tautan "kembali" = sengaja berhenti menulis orderan: drafnya dibuang di sini
    	// juga, dan drafBuang menahan beforeunload di bawahnya menabung ulang
    	$('.tombol-kembali').on('click', berhentiMenulis);

    	// kembali ke layar ini dengan tombol back bisa mengambilnya dari bfcache: skrip
    	// di atas tidak jalan sama sekali, jadi isian lama masih tertinggal di DOM.
    	// Buang drafnya lalu muat ulang supaya benar-benar mulai dari nol.
    	$(window).on('pageshow', function (e) {
    		var asli = e.originalEvent || {};

    		if (asli.persisted) {
    			berhentiMenulis();
    			document.location.reload();
    		}
    	});

    });

    JS;

$packer    = new Tholu\Packer\Packer($js, 'Normal', true, false, true);
$packed_js = $packer->pack();
echo '<script>' . $packed_js . '</script>';
?>
<?= $this->endSection() ?>
