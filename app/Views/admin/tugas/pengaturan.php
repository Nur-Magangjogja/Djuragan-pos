<?php

/**
 * Aturan reminder transaksi.
 *
 * Semua variabel di bawah disuntik Admin\Tugas::pengaturan() lewat view(); blok
 * @var ini hanya memberi tahunya ke analyzer, bukan kode yang dijalankan.
 *
 * @var string $title judul halaman
 * @var string $kabar pesan sukses flash (string kosong kalau tidak ada)
 * @var array<string, int>                                     $aturan nilai aktif (kunci => angka)
 * @var array<string, array{label: string, bawaan: int, satuan: string}> $meta   label + bawaan tiap kunci
 * @var array<string, int>                                     $hitung jumlah nota yang sedang masuk tiap aturan
 */
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .aturan-halaman { max-width: 880px; }
    .aturan-kotak {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        padding: 1.1rem 1.25rem; margin-bottom: 1rem;
    }
    .aturan-kotak h2 { margin: 0 0 .3rem; font-size: .8rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .06em; color: #334155; }
    .aturan-hint { font-size: .82rem; color: #64748b; margin: 0 0 .9rem; }
    .aturan-baris { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem;
        padding: .7rem 0; border-top: 1px solid #f1f5f9; }
    .aturan-baris:first-of-type { border-top: 0; }
    .aturan-teks { flex: 1 1 260px; }
    .aturan-teks strong { display: block; font-size: .86rem; color: #0f172a; }
    .aturan-teks span { font-size: .76rem; color: #94a3b8; }
    .aturan-angka { width: 5.5rem; }
    .aturan-kotak table { width: 100%; font-size: .84rem; }
    .aturan-kotak td { padding: .35rem .3rem; border-bottom: 1px solid #f1f5f9; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl aturan-halaman">

    <a class="mt-4 d-inline-flex align-items-center gap-2 text-decoration-none small" style="color:#64748b"
        href="<?= esc(site_url('admin/tugas')) ?>">
        <i class="fal fa-arrow-alt-left"></i> Kembali ke antrean tugas
    </a>

    <h1 class="h3 mt-2"><i class="fal fa-bell me-1 text-brand-500"></i> <?= esc($title) ?></h1>

    <?php if ($kabar !== '') : ?>
        <div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div>
    <?php endif; ?>

    <?= form_open('admin/tugas/pengaturan', ['class' => 'aturan-kotak']) ?>
        <h2><i class="fal fa-sliders-h me-1"></i> Kapan tugas lahir sendiri</h2>
        <p class="aturan-hint">
            Sistem memeriksa nota yang sedang dalam masa DP, dicicil, atau baru selesai. Aturan di bawah
            menentukan batas waktunya. Satu nota hanya pernah menghasilkan satu tugas per jenis — dijaga
            kunci unik di database, jadi diperiksa ulang tidak pernah menduplikasi.
        </p>

        <?php
        // satu baris aturan = satu angka ambang + satu sakelar; hitungannya ikut
        // dipetakan supaya "ada berapa nota sekarang" tidak perlu ditebak
        $daftar = [
            ['angka' => 'reminder_dp_jam', 'aktif' => 'reminder_dp_aktif', 'hitung' => 'dp'],
            ['angka' => 'reminder_pelunasan_hari', 'aktif' => 'reminder_pelunasan_aktif', 'hitung' => 'pelunasan'],
            ['angka' => 'reminder_ulasan_hari', 'aktif' => 'reminder_ulasan_aktif', 'hitung' => 'ulasan'],
        ];
        ?>
        <?php foreach ($daftar as $l) : ?>
            <div class="aturan-baris">
                <div class="aturan-teks">
                    <strong><?= esc($meta[$l['angka']]['label']) ?></strong>
                    <span>
                        <?php if ($aturan[$l['aktif']] === 1) : ?>
                            sekarang ada <?= (int) $hitung[$l['hitung']] ?> nota yang masuk kriteria
                        <?php else : ?>
                            sedang dimatikan
                        <?php endif; ?>
                    </span>
                </div>
                <input type="number" class="form-control form-control-sm aturan-angka" name="<?= esc($l['angka']) ?>"
                    value="<?= (int) $aturan[$l['angka']] ?>" min="0" max="90" step="1">
                <span class="small text-muted"><?= esc($meta[$l['angka']]['satuan']) ?></span>
                <div class="form-check form-switch ms-auto">
                    <input class="form-check-input" type="checkbox" role="switch" id="sw-<?= esc($l['aktif']) ?>"
                        name="<?= esc($l['aktif']) ?>" value="1" <?= $aturan[$l['aktif']] === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="sw-<?= esc($l['aktif']) ?>">Nyalakan</label>
                </div>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-sm btn-primary mt-3"><i class="fal fa-save me-1"></i> Simpan aturan</button>
    <?= form_close() ?>

    <div class="aturan-kotak">
        <h2><i class="fal fa-list-check me-1"></i> Yang sedang menunggu sekarang</h2>
        <table>
            <tr>
                <td>Konfirmasi DP</td>
                <td class="text-end fw-bold"><?= (int) $hitung['dp'] ?></td>
                <td class="text-end">
                    <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/tugas?tab=semua&cari=Konfirmasi DP')) ?>">buka tugasnya</a>
                </td>
            </tr>
            <tr>
                <td>Pelunasan dekat tenggat</td>
                <td class="text-end fw-bold"><?= (int) $hitung['pelunasan'] ?></td>
                <td class="text-end">
                    <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/tugas?tab=semua&cari=pelunasan')) ?>">buka tugasnya</a>
                </td>
            </tr>
            <tr>
                <td>Permintaan ulasan</td>
                <td class="text-end fw-bold"><?= (int) $hitung['ulasan'] ?></td>
                <td class="text-end">
                    <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/tugas?tab=semua&cari=ulasan')) ?>">buka tugasnya</a>
                </td>
            </tr>
        </table>
        <p class="aturan-hint mb-2 mt-2">
            Hitungannya dari kondisi nota saat ini, bukan dari tugas yang sudah terlanjur dibuat.
            Cek ulang kondisi pembayaran tidak mengubah tugas lama — yang pemicunya hilang ditutup
            otomatis dengan alasan tercatat.
        </p>

        <?= form_open('admin/tugas/sinkron') ?>
            <button type="submit" class="btn btn-sm btn-outline-dark rounded-pill">
                <i class="fal fa-rotate me-1"></i> Periksa nota sekarang
            </button>
        <?= form_close() ?>
    </div>

</div>

<?= $this->endSection() ?>
