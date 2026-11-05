<?php

/**
 * Satu sesi checklist: jawab butir satu per satu, lalu akhiri sesinya.
 *
 * @var array  $run    hasil ChecklistModel::run()
 * @var array  $hasil  hasil ChecklistModel::hasil() (judul ter-snapshot)
 * @var array  $status ChecklistModel::STATUS
 * @var string $kabar  flashdata sukses dari controller
 * @var string $galat  flashdata galat dari controller
 */

$level    = (string) session()->get('level');
$dibaca   = static function ($detik): string {
    return $detik !== null && (int) $detik > 0 ? date('d M Y H:i', (int) $detik) : '-';
};

$tutup    = (string) $run['status'] === 'selesai';
$sasaran  = $run['jenis'] === 'email'
    ? 'Email ' . ($run['akun_email'] ?: '(akun tidak tercatat)')
    : ($run['nama_toko'] ?: 'Toko #' . (int) $run['toko_id']);

$butir_beres = 0;

foreach ($hasil as $h) {
    $butir_beres += (string) $h['status'] === 'belum' ? 0 : 1;
}
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .ckl-halaman { max-width: 900px; }
    .ckl-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .ckl-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }

    .ckl-kartu {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        overflow: hidden; margin-bottom: 1rem;
    }
    .ckl-info { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; }
    .ckl-tile {
        background: #fff; border: 1px solid #e2e8f0; border-radius: .9rem;
        padding: .6rem .9rem; min-width: 128px;
    }
    .ckl-tile b { display: block; font-size: 1.15rem; color: #0f172a; }
    .ckl-tile span { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; }

    .ckl-baris { padding: .9rem 1rem; border-bottom: 1px solid #f1f5f9; }
    .ckl-baris:last-child { border-bottom: 0; }
    .ckl-tanya { font-weight: 700; color: #0f172a; font-size: .92rem; }
    .ckl-hint { font-size: .78rem; color: #64748b; margin-top: .15rem; }
    .ckl-diisi { font-size: .74rem; color: #94a3b8; margin-top: .3rem; }
    .ckl-jawab { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .45rem; margin-top: .6rem; }
    .ckl-jawab .lapangan { display: flex; flex-direction: column; gap: .18rem; }
    .ckl-jawab label { font-size: .68rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .ckl-jawab .form-select { min-width: 132px; }
    .ckl-jawab .form-control { min-width: 150px; }
    .ckl-cetak { display: inline-flex; align-items: center; gap: .3rem; font-size: .78rem; color: #475569; }

    /* catatan tulisan sendiri: satu baris paling bawah kartu + tempat buang di kanan barisnya */
    .ckl-hapus { display: inline-flex; align-items: flex-start; padding-top: .1rem; }
    .ckl-tambah { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; padding: .9rem 1rem; border-top: 1px solid #f1f5f9; }
    .ckl-tambah .form-control { flex: 1 1 240px; min-width: 0; }

    @media (max-width: 991.98px) {
        .ckl-head h1 > i { display: none; }
        .ckl-tile { flex: 1 1 calc(50% - .25rem); min-width: 0; }
        .ckl-jawab .form-select, .ckl-jawab .form-control { min-width: 0; width: 100%; max-width: none; }
        .ckl-jawab > .lapangan { flex: 1 1 calc(50% - .25rem); min-width: 0; }
        .ckl-jawab > .lapangan:last-child { flex: 0 0 auto; }
    }

    .pil-ckl { display: inline-block; padding: .18rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
    .c-berjalan { background: #fef3e2; color: #b45309; }
    .c-selesai { background: #e7f6ec; color: #14663a; }
    .h-selesai { background: #e7f6ec; color: #14663a; }
    .h-tidak_relevan { background: #eef1f6; color: #475569; }
    .h-belum { background: #fdf1f0; color: #b81c16; }

    .ckl-kosong { padding: 2.4rem 1rem; text-align: center; color: #94a3b8; }
    .ckl-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl ckl-halaman">

    <div class="ckl-head">
        <div class="me-auto">
            <h1 class="h3 mt-5">
                <i class="fal fa-ballot-check me-1 text-brand-500"></i> <?= esc($sasaran) ?>
            </h1>
            <p>
                <?= esc(\App\Models\ChecklistModel::JENIS[$run['jenis']] ?? $run['jenis']) ?>
                &middot; <?= esc(tanggal_rincian((string) $run['tanggal'])) ?>
                &middot; sesi #<?= (int) $run['id_run'] ?>
            </p>
        </div>
        <span class="pil-ckl c-<?= esc($run['status']) ?>"><?= $tutup ? 'Selesai' : 'Berjalan' ?></span>
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/checklist')) ?>">
            <i class="fal fa-arrow-left me-1"></i> Daftar sesi
        </a>
    </div>

    <?php if ($kabar !== '') : ?>
        <div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div>
    <?php endif; ?>
    <?php if ($galat !== '') : ?>
        <div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div>
    <?php endif; ?>

    <div class="ckl-info">
        <div class="ckl-tile"><b><?= $butir_beres ?>/<?= count($hasil) ?></b><span>Butir terjawab</span></div>
        <div class="ckl-tile"><b><?= count($hasil) - $butir_beres ?></b><span>Masih belum</span></div>
        <div class="ckl-tile">
            <b><?= $run['nama_dikerjakan'] ? esc($run['nama_dikerjakan']) : '-' ?></b>
            <span>Dikerjakan oleh</span>
        </div>
        <?php if ($tutup) : ?>
            <div class="ckl-tile"><b><?= $dibaca($run['selesai_at']) ?></b><span>Ditutup</span></div>
        <?php endif; ?>
    </div>

    <?php if ($run['judul_tugas']) : ?>
        <div class="alert alert-light border py-2 small d-flex align-items-center gap-2">
            <i class="fal fa-tasks text-brand-500"></i>
            <span>Tugas antrean:
                <a class="text-decoration-none fw-bold" href="<?= esc(site_url('admin/tugas/' . (int) $run['tugas_id'])) ?>">
                    <?= esc($run['judul_tugas']) ?>
                </a>
            </span>
            <span class="ms-auto text-muted"><?= esc((string) $run['status_tugas']) ?></span>
        </div>
    <?php endif; ?>

    <div class="ckl-kartu">
        <?php if ($hasil === []) : ?>
            <div class="ckl-kosong">
                <i class="fal fa-clipboard-list"></i>
                Templat checklist ini masih kosong. Minta Admin menambah butirnya di menu Templat Butir.
            </div>
        <?php else : ?>
            <?php foreach ($hasil as $h) : ?>
                <?php $status_butir = (string) $h['status']; ?>
                <div class="ckl-baris">
                    <div class="d-flex align-items-start gap-2">
                        <div class="me-auto">
                            <div class="ckl-tanya"><?= esc($h['judul']) ?></div>
                            <div class="ckl-diisi">
                                <span class="pil-ckl h-<?= esc($status_butir) ?>"><?= esc($status[$status_butir] ?? $status_butir) ?></span>
                                <?php if ((int) $h['perlu_tindakan'] === 1) : ?>
                                    <span class="pil-ckl h-belum ms-1"><i class="fal fa-triangle-exclamation"></i> perlu tindakan</span>
                                <?php endif; ?>
                                <?php if ($h['nama_diisi']) : ?>
                                    <span class="ms-1">diisi oleh <?= esc($h['nama_diisi']) ?> &middot; <?= $dibaca($h['updated_at']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($h['alasan']) : ?>
                                <div class="ckl-hint"><i class="fal fa-info-circle"></i> Alasan: <?= esc($h['alasan']) ?></div>
                            <?php endif; ?>
                            <?php if ($h['catatan']) : ?>
                                <div class="ckl-hint"><i class="fal fa-note-sticky"></i> <?= esc($h['catatan']) ?></div>
                            <?php endif; ?>
                        </div>

                        <?php if (! $tutup && $h['item_id'] === null) : ?>
                            <!-- butir tulisan sendiri boleh dibuang lagi; butir templat
                               tidak, supaya orang berikutnya tetap melihat apa yang
                               memang ditanyakan di toko ini -->
                            <?= form_open('admin/checklist/butir-hapus/' . (int) $run['id_run'], ['class' => 'ckl-hapus']) ?>
                                <input type="hidden" name="id_hasil" value="<?= (int) $h['id_hasil'] ?>">
                                <button type="submit" class="btn btn-sm btn-link text-decoration-none p-0"
                                    title="Hapus butir ini" aria-label="Hapus butir ini">
                                    <i class="fal fa-trash text-danger"></i>
                                </button>
                            <?= form_close() ?>
                        <?php endif; ?>
                    </div>

                    <?php if (! $tutup) : ?>
                        <?= form_open('admin/checklist/isi/' . (int) $run['id_run'], ['class' => 'ckl-jawab']) ?>
                            <input type="hidden" name="id_hasil" value="<?= (int) $h['id_hasil'] ?>">
                            <div class="lapangan">
                                <label for="st-<?= (int) $h['id_hasil'] ?>">Status</label>
                                <select class="form-select form-select-sm" id="st-<?= (int) $h['id_hasil'] ?>" name="status">
                                    <?php foreach ($status as $kode => $nama) : ?>
                                        <option value="<?= esc($kode) ?>" <?= $status_butir === $kode ? 'selected' : '' ?>><?= esc($nama) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="lapangan">
                                <label for="al-<?= (int) $h['id_hasil'] ?>">Alasan (wajib bila tidak relevan)</label>
                                <input type="text" class="form-control form-control-sm" id="al-<?= (int) $h['id_hasil'] ?>"
                                    name="alasan" maxlength="255" value="<?= esc($h['alasan'] ?? '') ?>"
                                    placeholder="mis. toko tidak punya akun ini">
                            </div>
                            <div class="lapangan">
                                <label for="ct-<?= (int) $h['id_hasil'] ?>">Catatan</label>
                                <input type="text" class="form-control form-control-sm" id="ct-<?= (int) $h['id_hasil'] ?>"
                                    name="catatan" maxlength="500" value="<?= esc($h['catatan'] ?? '') ?>"
                                    placeholder="opsional">
                            </div>
                            <label class="ckl-cetak" for="pt-<?= (int) $h['id_hasil'] ?>">
                                <input class="form-check-input mt-0" type="checkbox" id="pt-<?= (int) $h['id_hasil'] ?>"
                                    name="perlu_tindakan" value="1" <?= (int) $h['perlu_tindakan'] === 1 ? 'checked' : '' ?>>
                                Perlu tindakan lanjut
                            </label>
                            <div class="lapangan">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-save me-1"></i> Simpan</button>
                            </div>
                        <?= form_close() ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (! $tutup) : ?>
            <!-- lembar kerja toko tidak selalu sama tiap hari: apa yang sudah
               dikerjakan hari itu ditulis di sini, hanya hidup di sesi ini -->
            <?= form_open('admin/checklist/butir-tambah/' . (int) $run['id_run'], ['class' => 'ckl-tambah']) ?>
                <input type="text" class="form-control form-control-sm" name="judul"
                    maxlength="200" placeholder="mis. sudah balas 12 chat pembeli jam 10.00" required>
                <button type="submit" class="btn btn-sm btn-dark">
                    <i class="fal fa-plus me-1"></i> Tambah catatan
                </button>
            <?= form_close() ?>
        <?php endif; ?>
    </div>

    <?php if (! $tutup) : ?>
        <div class="ckl-kartu">
            <div class="ckl-baris">
                <div class="ckl-tanya">Akhiri sesi</div>
                <div class="ckl-hint">
                    Semua butir harus ditandai dulu. Sesi yang ditutup ikut menyelesaikan tugasnya di antrean CS.
                </div>
                <?= form_open('admin/checklist/selesai/' . (int) $run['id_run']) ?>
                    <div class="ckl-jawab">
                        <div class="lapangan flex-grow-1" style="min-width: 240px">
                            <label for="catatan-sesi">Catatan penutup</label>
                            <input type="text" class="form-control form-control-sm" id="catatan-sesi" name="catatan"
                                maxlength="500" placeholder="mis. semua aman, tidak ada temuan">
                        </div>
                        <div class="lapangan">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-sm btn-primary"
                                <?= $butir_beres < count($hasil) ? 'disabled' : '' ?>>
                                <i class="fal fa-circle-check me-1"></i> Akhiri sesi
                            </button>
                        </div>
                    </div>
                <?= form_close() ?>
                <?php if ($butir_beres < count($hasil)) : ?>
                    <div class="ckl-hint text-danger">
                        <i class="fal fa-triangle-exclamation"></i>
                        <?= count($hasil) - $butir_beres ?> butir masih belum ditandai.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($tutup && $run['catatan']) : ?>
        <div class="ckl-kartu">
            <div class="ckl-baris">
                <div class="ckl-tanya">Catatan penutup</div>
                <div class="ckl-hint"><?= esc($run['catatan']) ?></div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?= $this->endSection() ?>
