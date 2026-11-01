<?php

/**
 * Detail satu tugas: siapa yang memegang, apa yang harus dikerjakan, dan seluruh
 * riwayat perpindahan tangan. Aksi yang muncul hanya yang merupakan transisi sah —
 * daftar lengkapnya ada di TugasModel::TRANSISI, jadi layar tidak pernah memberi
 * tombol yang akan ditolak server.
 *
 * @var array $tugas   satu baris hasil TugasModel::satu()
 * @var array $riwayat baris crm_tugas_event + nama aktor
 * @var ?array $ulasan baris crm_ulasan nota terkait, atau null
 * @var array $staf    id => nama untuk dropdown penetapan
 * @var bool  $boleh_bagi akun ini admin/superadmin
 * @var bool  $boleh_kelola akun ini admin/superadmin; yang boleh membatalkan tugas
 * @var array $pilihan label status & prioritas
 * @var string $kabar   pesan sukses flash (string kosong kalau tidak ada)
 * @var string $galat   pesan gagal flash (string kosong kalau tidak ada)
 */

$level    = (string) session()->get('level');
$saya     = (int) session()->get('id');
$status   = (string) $tugas['status'];
$penggarap = (int) ($tugas['dikerjakan_oleh'] ?? 0);
$hari_ini = date('Y-m-d');
$tenggat  = (string) ($tugas['tenggat'] ?? '');

$terbuka   = in_array($status, ['queued', 'assigned', 'in_progress', 'waiting'], true);
$milik_saya = $penggarap === $saya;
$pegang     = $status === 'queued';
$terlambat  = $terbuka && $tenggat !== '' && $tenggat < $hari_ini;

$label = static function (string $kode) use ($pilihan): string {
    return $pilihan['status'][$kode] ?? $kode;
};

/** Waktu int → teks; 0/NULL tampil setipis mungkin. */
$jam = static function (?int $detik): string {
    return $detik !== null && $detik > 0 ? date('d M Y H:i', $detik) : '-';
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .tugas-rinci { max-width: 940px; }
    .tugas-kembali { display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; color: #64748b; text-decoration: none; }
    .tugas-kembali:hover { color: #101828; }
    .tugas-judul { display: flex; flex-wrap: wrap; align-items: flex-start; gap: .6rem; margin: .6rem 0 1.1rem; }
    .tugas-judul h1 { margin: 0; font-size: 1.4rem; font-weight: 800; letter-spacing: -.01em; color: #0f172a; }

    .tugas-kotak {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        padding: 1.1rem 1.25rem; margin-bottom: 1rem;
    }
    .tugas-kotak h2 {
        margin: 0 0 .8rem; font-size: .8rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .06em; color: #334155;
    }
    .tugas-isi { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .8rem 1rem; }
    .tugas-isi dt { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; margin-bottom: .15rem; }
    .tugas-isi dd { margin: 0; font-size: .86rem; color: #0f172a; font-weight: 600; }
    .tugas-teks { font-size: .88rem; color: #334155; white-space: pre-line; }
    .tugas-teks + .tugas-teks { margin-top: .6rem; }

    .pil-status { display: inline-block; padding: .2rem .7rem; border-radius: 999px; font-size: .74rem; font-weight: 700; }
    .s-queued { background: #eef1f6; color: #475569; }
    .s-assigned { background: #e6f4fb; color: #0369a1; }
    .s-in_progress { background: #fef3e2; color: #b45309; }
    .s-waiting { background: #f3e8ff; color: #6b21a8; }
    .s-completed { background: #e7f6ec; color: #14663a; }
    .s-cancelled { background: #fdf1f0; color: #b81c16; }
    .lewat { color: #b81c16; }

    .tugas-aksi { display: flex; flex-wrap: wrap; gap: .5rem; }
    .tugas-aksi form { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem;
        background: #f9fafb; border: 1px solid #eaecf0; border-radius: 12px; padding: .5rem .6rem; }
    .tugas-aksi label { font-size: .7rem; font-weight: 700; text-transform: uppercase; color: #667085; }
    .tugas-aksi .form-control, .tugas-aksi .form-select { min-width: 140px; }

    .tugas-riwayat { width: 100%; font-size: .82rem; }
    .tugas-riwayat th { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; }
    .tugas-riwayat td, .tugas-riwayat th { padding: .5rem .35rem; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .tugas-riwayat .panah { color: #94a3b8; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl tugas-rinci">

    <a class="tugas-kembali mt-4" href="<?= esc($kembali ?? site_url('admin/tugas')) ?>">
        <i class="fal fa-arrow-alt-left"></i> Kembali ke antrean
    </a>

    <div class="tugas-judul">
        <h1><?= esc($tugas['judul']) ?></h1>
        <span class="pil-status s-<?= esc($status) ?> ms-auto"><?= esc($label($status)) ?></span>
    </div>

    <?php if ($kabar !== '') : ?>
        <div class="alert alert-success py-2"><?= esc($kabar) ?></div>
    <?php endif; ?>
    <?php if ($galat !== '') : ?>
        <div class="alert alert-danger py-2"><?= esc($galat) ?></div>
    <?php endif; ?>

    <div class="tugas-kotak">
        <h2><i class="fal fa-circle-info me-1"></i> Informasi tugas</h2>
        <dl class="tugas-isi">
            <div><dt>Jenis</dt><dd><?= esc($pilihan['jenis'][$tugas['jenis']] ?? $tugas['jenis']) ?></dd></div>
            <div><dt>Prioritas</dt><dd><?= esc($pilihan['prioritas'][$tugas['prioritas']] ?? $tugas['prioritas']) ?></dd></div>
            <div><dt>Toko</dt><dd><?= $tugas['nama_juragan'] ? esc($tugas['nama_juragan']) : 'Lintas toko' ?></dd></div>
            <div><dt>Pelanggan</dt><dd><?= $tugas['nama_pelanggan'] ? esc($tugas['nama_pelanggan']) : '-' ?></dd></div>
            <div>
                <dt>Nomor nota</dt>
                <dd>
                    <?php if ($tugas['invoice_seri']) : ?>
                        <?php if ($level !== 'cs') : ?>
                            <a href="<?= esc(site_url('admin/invoices/lihat/semua?cari=' . rawurlencode((string) $tugas['invoice_seri']))) ?>">
                                <?= esc($tugas['invoice_seri']) ?>
                            </a>
                        <?php else : ?>
                            <?= esc($tugas['invoice_seri']) ?>
                        <?php endif; ?>
                    <?php else : ?>
                        -
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt>Tenggat</dt>
                <dd class="<?= $terlambat ? 'lewat' : '' ?>">
                    <?= $tenggat === '' ? 'tidak ada' : esc(tanggal_rincian($tenggat)) ?>
                    <?= $terlambat ? ' (lewat)' : '' ?>
                </dd>
            </div>
            <div><dt>Susul lagi</dt><dd><?= $jam((int) ($tugas['susul_at'] ?? 0)) ?></dd></div>
            <div><dt>Dibuat</dt><dd><?= esc($tugas['nama_dibuat'] ?? 'sistem') ?> &middot; <?= esc($jam((int) ($tugas['created_at'] ?? 0))) ?></dd></div>
            <div>
                <dt>Penggarap</dt>
                <dd>
                    <?= $tugas['nama_dikerjakan'] ? esc($tugas['nama_dikerjakan']) : 'belum ada' ?>
                    <?php if ($tugas['cara_penugasan']) : ?>
                        <span class="text-muted fw-normal">(<?= esc($tugas['cara_penugasan']) ?>)</span>
                    <?php endif; ?>
                </dd>
            </div>
            <div><dt>Selesai</dt><dd><?= $tugas['nama_selesai'] ? esc($tugas['nama_selesai']) . ' &middot; ' . esc($jam((int) $tugas['selesai_at'])) : '-' ?></dd></div>
            <?php if ($tugas['alasan_batal']) : ?>
                <div style="grid-column: 1 / -1;"><dt>Alasan dibatalkan</dt><dd><?= esc($tugas['alasan_batal']) ?></dd></div>
            <?php endif; ?>
        </dl>

        <?php if ($tugas['deskripsi'] || $tugas['catatan']) : ?>
            <hr class="my-3">
            <?php if ($tugas['deskripsi']) : ?>
                <p class="tugas-teks mb-0"><?= esc($tugas['deskripsi']) ?></p>
            <?php endif; ?>
            <?php if ($tugas['catatan']) : ?>
                <p class="tugas-teks mt-2 mb-0 text-muted"><?= esc($tugas['catatan']) ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ((int) $tugas['invoice_id'] > 0) : ?>
        <div class="tugas-kotak">
            <h2><i class="fal fa-star me-1"></i> Ulasan pelanggan</h2>
            <p class="shift-hint" style="font-size:.82rem;color:#64748b;margin:0 0 .8rem">
                <?php if ($ulasan !== null) : ?>
                    Status permintaan:
                    <strong><?= esc($ulasan['status'] === 'diterima' ? 'ulasan diterima' : 'sudah diminta, belum dibalas') ?></strong>
                    <?php if ($ulasan['bintang'] !== null) : ?>
                        &middot; <?= (int) $ulasan['bintang'] ?> bintang
                        <?php if ($ulasan['platform'] !== '') : ?> &middot; <?= esc($ulasan['platform']) ?><?php endif; ?>
                    <?php endif; ?>
                    <?php if ($ulasan['komentar']) : ?>
                        <span class="d-block mt-1 text-muted">"&hellip; <?= esc($ulasan['komentar']) ?>"</span>
                    <?php endif; ?>
                <?php else : ?>
                    Belum ada permintaan ulasan untuk nota ini.
                <?php endif; ?>
            </p>

            <?= form_open('admin/tugas/ulasan/' . (int) $tugas['id_tugas']) ?>
                <div class="d-flex flex-wrap align-items-end gap-2">
                    <div style="min-width:120px">
                        <label class="form-label small fw-bold" for="bintang">Bintang dari pelanggan</label>
                        <select class="form-select form-select-sm" id="bintang" name="bintang" required>
                            <option value="">-</option>
                            <?php for ($b = 5; $b >= 1; $b--) : ?>
                                <option value="<?= $b ?>"><?= $b ?> &starf;</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div style="flex:1 1 160px">
                        <label class="form-label small fw-bold" for="platform">Platform</label>
                        <input type="text" class="form-control form-control-sm" id="platform" name="platform"
                            maxlength="40" value="<?= esc($ulasan['platform'] ?? '') ?>" placeholder="Shopee, Tokopedia, ...">
                    </div>
                    <div style="flex:2 1 220px">
                        <label class="form-label small fw-bold" for="komentar">Kutipan ulasan</label>
                        <input type="text" class="form-control form-control-sm" id="komentar" name="komentar"
                            maxlength="500" value="<?= esc($ulasan['komentar'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-dark rounded-pill">Catat ulasan</button>
                </div>
            <?= form_close() ?>
            <p class="small text-muted mb-0 mt-2">
                Mengisi ulasan tidak menyelesaikan tugas ini, dan menyelesaikan tugas tidak mencatat ulasan.
            </p>
        </div>
    <?php endif; ?>

    <div class="tugas-kotak">
        <h2><i class="fal fa-bolt me-1"></i> Aksi</h2>
        <div class="tugas-aksi">

            <?php if ($pegang) : ?>
                <form method="post" action="<?= esc(site_url('admin/tugas/ambil/' . (int) $tugas['id_tugas'])) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill"><i class="fal fa-hand me-1"></i> Ambil tugas</button>
                </form>
            <?php endif; ?>

            <?php if ($milik_saya && in_array($status, ['assigned', 'waiting'], true)) : ?>
                <form method="post" action="<?= esc(site_url('admin/tugas/status/' . (int) $tugas['id_tugas'])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="in_progress">
                    <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">Kerjakan</button>
                </form>
            <?php endif; ?>

            <?php if ($milik_saya && in_array($status, ['assigned', 'in_progress', 'waiting'], true)) : ?>
                <form method="post" action="<?= esc(site_url('admin/tugas/status/' . (int) $tugas['id_tugas'])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="completed">
                    <label for="selesai-catatan">Hasil</label>
                    <input type="text" class="form-control form-control-sm" id="selesai-catatan" name="catatan"
                        placeholder="apa yang sudah dilakukan" maxlength="500">
                    <button type="submit" class="btn btn-sm btn-success rounded-pill">Selesai</button>
                </form>

                <form method="post" action="<?= esc(site_url('admin/tugas/status/' . (int) $tugas['id_tugas'])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="waiting">
                    <label for="tahan">Susulkan</label>
                    <input type="date" class="form-control form-control-sm" id="tahan" name="susul" required>
                    <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill">Menunggu respons</button>
                </form>

                <form method="post" action="<?= esc(site_url('admin/tugas/operan/' . (int) $tugas['id_tugas'])) ?>">
                    <?= csrf_field() ?>
                    <label for="operan-alasan">Alasan</label>
                    <input type="text" class="form-control form-control-sm" id="operan-alasan" name="alasan" required
                        placeholder="shift habis / operan ke CS lain" maxlength="255">
                    <label for="operan-catatan">Catatan penerus</label>
                    <input type="text" class="form-control form-control-sm" id="operan-catatan" name="catatan"
                        placeholder="sudah sampai mana" maxlength="500">
                    <button type="submit" class="btn btn-sm btn-outline-dark rounded-pill"><i class="fal fa-people-arrows me-1"></i> Operan</button>
                </form>
            <?php endif; ?>

            <?php if ($terbuka && $milik_saya && $boleh_kelola) : ?>
                <form method="post" action="<?= esc(site_url('admin/tugas/status/' . (int) $tugas['id_tugas'])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="cancelled">
                    <label for="batal-alasan">Alasan batal</label>
                    <input type="text" class="form-control form-control-sm" id="batal-alasan" name="alasan" required maxlength="255">
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">Batalkan</button>
                </form>
            <?php endif; ?>

            <?php if ($boleh_bagi) : ?>
                <form method="post" action="<?= esc(site_url('admin/tugas/tetapkan/' . (int) $tugas['id_tugas'])) ?>">
                    <?= csrf_field() ?>
                    <label for="bagi">Tetapkan ke</label>
                    <select class="form-select form-select-sm" id="bagi" name="dikerjakan_oleh" required>
                        <?php foreach ($staf as $id_staf => $nama_staf) : ?>
                            <option value="<?= (int) $id_staf ?>" <?= $penggarap === (int) $id_staf ? 'selected' : '' ?>>
                                <?= esc($nama_staf) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" class="form-control form-control-sm" name="alasan" placeholder="alasan" maxlength="255">
                    <button type="submit" class="btn btn-sm btn-dark rounded-pill">Tetapkan</button>
                </form>
            <?php endif; ?>

            <?php if (! $terbuka && ($status !== 'cancelled' || $boleh_kelola)) : ?>
                <form method="post" action="<?= esc(site_url('admin/tugas/status/' . (int) $tugas['id_tugas'])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="queued">
                    <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="fal fa-redo me-1"></i> Buka kembali</button>
                </form>
            <?php endif; ?>

        </div>
        <p class="small text-muted mb-0 mt-2">
            Tombol hanya muncul untuk transisi yang diizinkan; penolakan terjadi di server, bukan sekadar penyembunyian.
            <?php if (! $boleh_kelola) : ?>
                CS tidak membatalkan tugas: kalau peganganmu harus lepas, pakai <strong>Operan</strong> dan tugasnya
                kembali ke antrean untuk diambil orang lain. Yang membatalkan (pekerjaannya berhenti) hanya Admin.
            <?php endif; ?>
        </p>
    </div>

    <div class="tugas-kotak">
        <h2><i class="fal fa-history me-1"></i> Riwayat</h2>
        <?php if ($riwayat === []) : ?>
            <p class="text-muted mb-0">Belum ada aktivitas tercatat.</p>
        <?php else : ?>
            <table class="tugas-riwayat">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Aktivitas</th>
                        <th>Perubahan</th>
                        <th>Oleh</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayat as $e) : ?>
                        <tr>
                            <td class="text-nowrap"><?= esc($jam((int) $e['created_at'])) ?></td>
                            <td class="fw-bold"><?= esc($e['jenis']) ?></td>
                            <td>
                                <?php if ($e['status_lama'] || $e['status_baru']) : ?>
                                    <?= esc($e['status_lama'] ?: '-') ?>
                                    <span class="panah"><i class="fal fa-arrow-alt-right"></i></span>
                                    <?= esc($e['status_baru'] ?: '-') ?>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= esc($e['nama_aktor'] ?? '-') ?></td>
                            <td class="text-muted">
                                <?= esc($e['alasan'] ?? '') ?>
                                <?= ! empty($e['alasan']) && ! empty($e['catatan']) ? ' &middot; ' : '' ?>
                                <?= esc($e['catatan'] ?? '') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<?= $this->endSection() ?>
