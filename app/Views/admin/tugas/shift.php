<?php

/**
 * Layar Jam shift CS.
 *
 * @var string $title   judul halaman dari controller
 * @var string $kabar   flashdata sukses, string kosong kalau tidak ada
 * @var string $galat   flashdata gagal, string kosong kalau tidak ada
 * @var string $tanggal hari yang sedang diatur
 * @var array  $baris   satu baris per staf aktif: id, nama, level, mulai, selesai, sudah, lewat
 * @var array  $operan  waktu + jumlah sapuan operan otomatis terakhir
 */

$sudah_diisi = count(array_filter($baris, static fn ($b) => $b['sudah']));
$sudah_lewat = count(array_filter($baris, static fn ($b) => $b['lewat']));
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .shift-halaman { max-width: 880px; }
    .shift-kotak {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        padding: 1.1rem 1.25rem; margin-bottom: 1rem;
    }
    .shift-kotak h2 {
        margin: 0 0 .3rem; font-size: .8rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .06em; color: #334155;
    }
    .shift-hint { font-size: .82rem; color: #64748b; margin: 0 0 .9rem; }
    .shift-baris { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-bottom: .55rem; }
    .shift-orang { flex: 1 1 220px; min-width: 0; }
    .shift-orang strong { display: block; font-size: .88rem; color: #0f172a; }
    .shift-orang span { font-size: .74rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; }
    .shift-jam { display: flex; align-items: center; gap: .3rem; }
    .shift-jam input[type=time] { width: 7.2rem; }
    .shift-setuju { font-size: .74rem; color: #14663a; font-weight: 700; white-space: nowrap; }
    .shift-lewat { font-size: .74rem; color: #b81c16; font-weight: 700; white-space: nowrap; }
    .shift-cek { font-size: .8rem; color: #475569; }
    .shift-cek code { background: #f4f6f9; padding: .1rem .4rem; border-radius: 6px; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl shift-halaman">

    <a class="tugas-kembali mt-4" href="<?= esc(site_url('admin/tugas')) ?>" style="display:inline-flex;align-items:center;gap:.35rem;font-size:.8rem;color:#64748b;text-decoration:none;">
        <i class="fal fa-arrow-alt-left"></i> Kembali ke antrean tugas
    </a>

    <h1 class="h3 mt-2"><i class="fal fa-clock me-1 text-brand-500"></i> <?= esc($title) ?></h1>

    <?php if ($kabar !== '') : ?>
        <div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div>
    <?php endif; ?>
    <?php if ($galat !== '') : ?>
        <div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div>
    <?php endif; ?>

    <div class="shift-kotak">
        <h2><i class="fal fa-calendar-day me-1"></i> Jam kerja</h2>
        <p class="shift-hint">
            Satu jendela per orang per tanggal. Lewat jam <em>selesai</em>, tugas yang masih dipegang
            (status Ditetapkan atau Sedang dikerjakan) otomatis kembali ke antrean dan penggarapnya dilepas —
            catatan operan dari orang itu tetap terbaca di riwayat tugas.
            Kolom yang dibiarkan kosong berarti orang tersebut tidak bertugas hari itu, jadi tidak ada yang dioperkan.
        </p>

        <?= form_open('admin/tugas/shift') ?>
            <div class="mb-3" style="max-width:220px">
                <label class="form-label small fw-bold" for="tanggal">Tanggal</label>
                <input type="date" class="form-control form-control-sm" id="tanggal" name="tanggal" value="<?= esc($tanggal) ?>">
            </div>

            <?php foreach ($baris as $b) : ?>
                <div class="shift-baris">
                    <div class="shift-orang">
                        <strong><?= esc($b['nama']) ?></strong>
                        <span><?= esc($b['level']) ?></span>
                    </div>
                    <div class="shift-jam">
                        <input type="time" class="form-control form-control-sm" name="mulai[<?= (int) $b['id'] ?>]"
                            value="<?= esc($b['mulai']) ?>" step="60">
                        <span class="text-muted small">s/d</span>
                        <input type="time" class="form-control form-control-sm" name="selesai[<?= (int) $b['id'] ?>]"
                            value="<?= esc($b['selesai']) ?>" step="60">
                    </div>
                    <div style="flex:0 0 110px">
                        <?php if ($b['lewat']) : ?>
                            <span class="shift-lewat"><i class="fal fa-exclamation-triangle"></i> berakhir</span>
                        <?php elseif ($b['sudah']) : ?>
                            <span class="shift-setuju"><i class="fal fa-check"></i> tercatat</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fal fa-save me-1"></i> Simpan jam</button>
                <span class="small text-muted"><?= $sudah_diisi ?> dari <?= count($baris) ?> staf sudah punya jendela pada tanggal ini.</span>
            </div>
        <?= form_close() ?>
    </div>

    <div class="shift-kotak">
        <h2><i class="fal fa-broom me-1"></i> Operan otomatis</h2>
        <p class="shift-hint">
            Sistem memeriksa sendiri paling banyak satu kali tiap lima menit, yaitu setiap kali halaman
            layar Tugas CS atau badge angka dibuka. Kalau hosting punya cron, jalankan
            <code>php spark crm:operan</code> setiap menit — hasilnya sama, hanya lebih pasti waktunya.
        </p>
        <p class="shift-cek mb-2">
            Sapuan terakhir:
            <code><?= $operan['waktu'] === null ? 'belum pernah' : esc(date('d M Y H:i', $operan['waktu'])) ?></code>
            &middot; tugas dioperkan: <code><?= (int) $operan['jumlah'] ?></code>
            <?php if ($sudah_lewat > 0) : ?>
                &middot; jendela yang sudah lewat hari ini: <code><?= (int) $sudah_lewat ?></code>
            <?php endif; ?>
        </p>

        <?= form_open('admin/tugas/sapu') ?>
            <button type="submit" class="btn btn-sm btn-outline-dark rounded-pill">
                <i class="fal fa-play me-1"></i> Jalankan operan sekarang
            </button>
        <?= form_close() ?>
    </div>

</div>

<?= $this->endSection() ?>
