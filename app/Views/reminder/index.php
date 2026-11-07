<?php

/**
 * Halaman Reminder Penjahit. Data dikirim Reminder::index() lewat view() lalu
 * di-extract CI4 saat render, jadi tidak ada assignment yang terlihat analis
 * statis; blok @var ini memberi jenisnya.
 *
 * @var string $title
 * @var int    $hari        ambang H hari sebelum deadline (pengaturan Superadmin)
 * @var array  $reminder    daftar orderan dari InvoiceModel::pengingatPenjahit(),
 *                          tiap item sudah ditambah kunci 'tautan' ke satu invoice
 * @var bool   $superadmin  boleh mengubah ambang hari?
 */

// Tiap reminder sudah membawa 'tautan' dari controller: layar transaksi yang
// tersaring ke satu nomor nota, jadi isi invoice tampil penuh tanpa membuka
// form sunting dan tanpa daftar invoice lain.
$hari_ini = date('Y-m-d');
$lunas    = ['5', '6'];
$sukses   = session()->getFlashdata('sukses');
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .reminder-halaman { max-width: 940px; }
    /* lonceng notifikasi desktop melayang di pojok kanan atas; sisihkan ruang di
       kanan supaya tombol "Selesai" / pil di ujung kanan kartu tidak tertutupnya */
    @media (min-width: 992px) {
        .reminder-halaman { padding-right: 4.5rem; }
    }
    .reminder-head {
        margin-bottom: 1.25rem;
    }
    /* Pil "muncul mulai H-…" duduk di SAMPING judul, bukan di ujung kanan:
       di kanan atas ia tertutup lonceng notifikasi yang melayang. Baris ini
       boleh membungkus, jadi di layar sempit pilnya turun sendiri di bawah
       judul tanpa menutupi apa pun. */
    .reminder-judul {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .6rem;
    }
    /* Judul tidak lagi diberi ukuran sendiri: kelas h3 mt-5 membuatnya ikut
       normalisasi style.css (body.has-app-sidebar h1.h3.mt-5) persis seperti
       judul halaman lain — 1,5rem, margin atas 0. Yang dilucuti cuma margin
       bawahnya, supaya baris "Orderan pembuatan/jual ..." tetap nempel di
       bawah judul, bukan terpisah 1,35rem. Selector harus menyalip bobot aturan
       global itu (4 kelas + 3 elemen), karena dua !importantadu spesifik. */
    body.has-app-sidebar .reminder-halaman .reminder-head h1.h3.mt-5 {
        margin-bottom: 0 !important;
    }
    .reminder-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }
    .reminder-periode {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        padding: .26rem .85rem;
        font-size: .78rem;
        font-weight: 600;
        color: #475569;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        white-space: nowrap;
    }
    .reminder-periode i { color: #da251d; }

    .reminder-kartu-kecil {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
    }
    .reminder-setelan {
        padding: 1.1rem 1.25rem;
        margin-bottom: 1.1rem;
    }
    .reminder-setelan h2 {
        margin: 0 0 .2rem;
        font-size: .82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #334155;
    }
    .reminder-setelan .hint { margin: 0 0 .85rem; font-size: .82rem; color: #64748b; }
    .reminder-setelan-baris { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; }
    .reminder-setelan-baris .input-group { max-width: 220px; }
    .reminder-setelan-baris input { text-align: center; }

    /* Angka dan satuannya SATU kotak, di semua lebar. .input-group-text dan
       .form-control sama-sama mengunci border + radius sendiri di style.css,
       jadi yang dijadikan kotak adalah wadahnya (box-shadow dalam, bukan border,
       supaya tingginya tidak bertambah 2px) dan garis kedua isinya dilucuti —
       resep yang sama dengan kolom bersatuan di panel ukuran. */
    .reminder-setelan-baris .input-group {
        display: flex;
        align-items: stretch;
        flex: 0 1 auto;
        max-width: 268px;
        min-width: 0;
        border-radius: 9px;
        background-color: #ffffff;
        box-shadow: inset 0 0 0 1px #d0d5dd;
        transition: box-shadow .15s ease;
    }

    .reminder-setelan-baris .input-group > .form-control,
    .reminder-setelan-baris .input-group > .input-group-text {
        border: 0 !important;
        border-radius: 0 !important;
        background-color: transparent !important;
        box-shadow: none !important;
    }

    .reminder-setelan-baris .input-group > .form-control {
        flex: 0 0 3.5rem;
        padding: .42rem .5rem !important;
        font-size: .85rem !important;
    }

    .reminder-setelan-baris .input-group > .input-group-text {
        display: flex;
        align-items: center;
        flex: 1 1 auto;
        min-width: 0;
        padding: .42rem .7rem .42rem .55rem !important;
        font-size: .85rem !important;
        color: #667085 !important;
        /* pemisah tipis tanpa menambah tinggi: garis dalam, bukan border */
        box-shadow: inset 1px 0 0 #eaecf0 !important;
    }

    .reminder-setelan-baris .input-group:focus-within {
        box-shadow: inset 0 0 0 1px #101828, 0 0 0 3px rgba(16, 24, 40, .08);
    }

    /* Tombol Simpan tetap di sebelah kolom, bukan melompat ke baris baru. */
    .reminder-setelan-baris > button {
        flex: 0 0 auto;
    }

    /* Layar sempit: ikon gunting dibuang, pil H- dirampingkan supaya masih
       sejajar di samping judul, dan kolom + Simpan dipaksa muat satu baris. */
    @media (max-width: 767.98px) {

        .reminder-ikon {
            display: none;
        }

        .reminder-judul {
            gap: .2rem;
        }

        .reminder-periode {
            gap: .25rem;
            padding: .2rem .42rem;
            font-size: .62rem;
        }

        .reminder-setelan {
            padding: 1rem;
        }

        .reminder-setelan-baris {
            flex-wrap: nowrap;
            gap: .5rem;
        }

        .reminder-setelan-baris .input-group {
            max-width: none;
            flex: 1 1 auto;
        }

        .reminder-setelan-baris .input-group > .form-control {
            flex: 0 0 2.9rem;
            padding: .42rem .4rem !important;
        }

        .reminder-setelan-baris .input-group > .input-group-text {
            padding: .42rem .5rem .42rem .45rem !important;
            font-size: .78rem !important;
        }

        .reminder-setelan-baris > button {
            padding: .42rem .7rem;
            font-size: .78rem;
        }
    }

    .reminder-list { display: grid; gap: .8rem; }
    .reminder-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: .95rem 1.15rem;
        text-decoration: none;
        transition: box-shadow .15s ease, border-color .15s ease;
    }
    .reminder-item:hover { border-color: #cbd5e1; box-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 12px 28px -14px rgba(15, 23, 42, .2); }
    .reminder-item.lewat { border-left: 3px solid #da251d; }
    .reminder-item.dekat { border-left: 3px solid #f59e0b; }
    .reminder-item.normal { border-left: 3px solid #0ea5e9; }
    .reminder-kiri { min-width: 0; }
    .reminder-seri { font-weight: 700; color: #0f172a; font-size: .98rem; }
    .reminder-meta { margin-top: .15rem; font-size: .82rem; color: #64748b; display: flex; flex-wrap: wrap; gap: .35rem .75rem; }
    .reminder-meta span { display: inline-flex; align-items: center; gap: .3rem; }
    .reminder-meta i { color: #94a3b8; }
    .reminder-kanan { text-align: right; flex-shrink: 0; }
    .reminder-tanggal { font-size: .82rem; color: #475569; font-weight: 600; }
    .reminder-pill {
        display: inline-block;
        margin-top: .35rem;
        padding: .2rem .6rem;
        border-radius: 999px;
        font-size: .74rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .reminder-pill.lewat { background: #fdf1f0; color: #b81c16; }
    .reminder-pill.dekat { background: #fef3e2; color: #b45309; }
    .reminder-pill.normal { background: #e6f4fb; color: #0369a1; }
    .reminder-kosong { padding: 3rem 1rem; text-align: center; color: #94a3b8; }
    .reminder-kosong i { font-size: 2.2rem; display: block; margin-bottom: .6rem; }

    /* dua pil status di atas daftar; angka di dalam pil ikut berubah */
    .reminder-tab-bar { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1rem; }
    .reminder-tab {
        display: inline-flex; align-items: center; gap: .45rem;
        padding: .4rem .9rem; border-radius: 999px; text-decoration: none;
        font-size: .82rem; font-weight: 600; color: #475569;
        background: #fff; border: 1px solid #e2e8f0;
    }
    .reminder-tab:hover { border-color: #cbd5e1; }
    .reminder-tab.aktif { background: #101828; color: #fff; border-color: #101828; }
    .reminder-tab span {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 1.35rem; height: 1.2rem; padding: 0 .35rem; border-radius: 999px;
        font-size: .72rem; font-weight: 700; background: #eef1f6; color: #475569;
    }
    .reminder-tab.aktif span { background: rgba(255, 255, 255, .2); color: #fff; }

    /* nomor nota kini tautan: tetap tegak & sewarna teks, hanya garis saat hover */
    .reminder-seri { text-decoration: none; }
    .reminder-seri:hover { color: #0f172a; text-decoration: underline; }

    /* sisi kanan ditumpuk supaya pil + tombol aksi duduk rapi */
    .reminder-kanan { display: flex; flex-direction: column; align-items: flex-end; gap: .35rem; }
    /* tanggal + pil status sejajar satu baris; pil tidak lagi turun di bawah */
    .reminder-kanan-atas { display: flex; align-items: center; gap: .5rem; }
    .reminder-kanan-atas .reminder-pill { margin-top: 0; }
    .reminder-selesai { font-size: .74rem; }
    .reminder-item.sudah { opacity: .94; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include((string) session()->get('level') === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl reminder-halaman">

    <div class="reminder-head">
        <div class="reminder-judul">
            <h1 class="h3 mt-5"><i class="fal fa-cut me-1 text-brand-500 reminder-ikon"></i> <?= esc($title) ?></h1>
            <span class="reminder-periode">
                <i class="fal fa-hourglass-half reminder-periode-ikon"></i> muncul mulai H-<?= (int) $hari ?> deadline
            </span>
        </div>
        <p>Orderan pembuatan/jual yang tenggatnya sudah dekat — hubungi penjahit sebelum lewat.</p>
    </div>

    <?php if ($sukses !== null) : ?>
        <div class="alert alert-success py-2" data-flash><?= esc($sukses) ?></div>
    <?php endif; ?>

    <?php if ($superadmin) : ?>
        <div class="reminder-kartu-kecil reminder-setelan">
            <h2><i class="fal fa-sliders-h me-1 text-brand-500"></i> Pengaturan Reminder</h2>
            <p class="hint">Tentukan berapa hari sebelum deadline sebuah orderan mulai muncul di sini. Makin besar angkanya, makin awal reminder muncul.</p>
            <?= form_open('admin/reminder/pengaturan', ['class' => 'reminder-setelan-baris']) ?>
                <div class="input-group">
                    <input type="number" name="hari" class="form-control form-control-sm" min="0" max="90" value="<?= (int) $hari ?>" required>
                    <span class="input-group-text">hari sebelum deadline</span>
                </div>
                <button type="submit" class="btn btn-sm btn-primary"><i class="fal fa-save me-1"></i> Simpan</button>
            <?= form_close() ?>
        </div>
    <?php endif; ?>

    <div class="reminder-tab-bar">
        <a class="reminder-tab <?= $tab === 'belum' ? 'aktif' : '' ?>" href="<?= esc(site_url('admin/reminder?tab=belum')) ?>">
            <i class="fal fa-bell"></i> Belum Diingatkan
            <span><?= (int) ($angka['belum'] ?? 0) ?></span>
        </a>
        <a class="reminder-tab <?= $tab === 'sudah' ? 'aktif' : '' ?>" href="<?= esc(site_url('admin/reminder?tab=sudah')) ?>">
            <i class="fal fa-check"></i> Sudah Diingatkan
            <span><?= (int) ($angka['sudah'] ?? 0) ?></span>
        </a>
    </div>
    
    <?php if ($reminder === []) : ?>
        <div class="reminder-kartu-kecil">
            <div class="reminder-kosong">
                <i class="fal fa-circle-check text-success"></i>
                <?= $tab === 'sudah'
                    ? 'Belum ada reminder yang ditandai selesai.'
                    : 'Tidak ada orderan yang mendekati deadline. Aman!' ?>
            </div>
        </div>
    <?php else : ?>
        <div class="reminder-list">
            <?php foreach ($reminder as $r) : ?>
                <?php
                $deadline = (string) ($r['deadline'] ?? '');
                $selisih  = (int) round((strtotime($deadline) - strtotime($hari_ini)) / 86400); // hitung hari kalender
    
                if ($selisih < 0) {
                    $kelas = 'lewat';
                    $label = 'Lewat ' . abs($selisih) . ' hari';
                } elseif ($selisih === 0) {
                    $kelas = 'lewat';
                    $label = 'Jatuh tempo hari ini';
                } elseif ($selisih <= 2) {
                    $kelas = 'dekat';
                    $label = $selisih . ' hari lagi';
                } else {
                    $kelas = 'normal';
                    $label = $selisih . ' hari lagi';
                }
    
                $sudah_lunas = in_array((string) ($r['status_pembayaran'] ?? ''), $lunas, true);
                $id_inv      = (int) ($r['id_invoice'] ?? 0);
                ?>
                <div class="reminder-kartu-kecil reminder-item <?= $kelas ?><?= $tab === 'sudah' ? ' sudah' : '' ?>">
                    <div class="reminder-kiri">
                        <a class="reminder-seri" href="<?= esc((string) ($r['tautan'] ?? '')) ?>"><?= esc($r['seri']) ?></a>
                        <div class="reminder-meta">
                            <span><i class="fal fa-user"></i> <?= esc($r['nama_pelanggan'] ?: 'Tanpa nama') ?></span>
                            <span><i class="fal fa-store"></i> <?= esc($r['nama_juragan'] ?: '-') ?></span>
                            <span>
                                <i class="fal fa-<?= $sudah_lunas ? 'check-circle text-success' : 'clock text-warning' ?>"></i>
                                <?= $sudah_lunas ? 'Lunas' : 'Belum lunas' ?>
                            </span>
                        </div>
                    </div>
                    <div class="reminder-kanan">
                        <div class="reminder-kanan-atas">
                            <span class="reminder-pill <?= $kelas ?>"><?= esc($label) ?></span>
                            <div class="reminder-tanggal"><i class="fal fa-calendar-day me-1"></i><?= esc(tanggal_rincian($deadline)) ?></div>
                        </div>
                        <?php if ($tab === 'belum') : ?>
                            <button type="button" class="btn btn-sm btn-success reminder-selesai"
                                data-invoice="<?= $id_inv ?>" data-seri="<?= esc($r['seri'], 'attr') ?>">
                                <i class="fal fa-check me-1"></i> Selesai
                            </button>
                        <?php else : ?>
                            <?= form_open('admin/reminder/batal', ['class' => 'd-inline reminder-aksi']) ?>
                                <input type="hidden" name="id_invoice" value="<?= $id_inv ?>">
                                <button type="submit" class="btn btn-sm btn-link text-muted text-decoration-none" title="Kembalikan ke Belum Diingatkan">
                                    <i class="fal fa-undo me-1"></i> Urungkan
                                </button>
                            <?= form_close() ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($tab === 'belum' && $reminder !== []) : ?>
        <!-- satu modal konfirmasi untuk semua tombol Selesai: nomor nota diisi dari
             tombol yang diklik, jadi tidak ada satu popup per baris -->
        <div class="modal fade" id="modalReminderSelesai" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <?= form_open('admin/reminder/selesai') ?>
                        <input type="hidden" name="id_invoice" value="">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fal fa-bell me-1 text-brand-500"></i> Tandai sudah diingatkan</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-1">Sudah direminder untuk orderan <strong class="reminder-selesai-seri">&mdash;</strong>?</p>
                            <p class="text-muted small mb-0">Setelah ditandai, reminder ini pindah ke tab "Sudah Diingatkan" dan bisa diurungkan kapan pun.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-sm btn-success"><i class="fal fa-check me-1"></i> Ya, sudah direminder</button>
                        </div>
                    <?= form_close() ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // tombol "Selesai" mengisi satu modal konfirmasi: id invoice + nomor nota
        // diambil dari tombol yang diklik, jadi popup tidak dibuat per baris
        document.addEventListener('click', function (e) {
            var tombol = e.target.closest ? e.target.closest('.reminder-selesai') : null;

            if (! tombol) {
                return;
            }

            var modal = document.getElementById('modalReminderSelesai');

            if (! modal) {
                return;
            }

            modal.querySelector('[name="id_invoice"]').value = tombol.getAttribute('data-invoice') || '';
            modal.querySelector('.reminder-selesai-seri').textContent = tombol.getAttribute('data-seri') || '';

            if (window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        });
    });
</script>
<?= $this->endSection() ?>
