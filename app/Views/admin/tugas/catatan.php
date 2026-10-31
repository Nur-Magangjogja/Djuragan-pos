<?php

/**
 * Layar Catatan Percakapan.
 *
 * CS memakai aplikasi WhatsApp untuk mengobrol; layar ini tempat menyimpan HASIL
 * obrolan itu supaya CS berikutnya (atau Admin) tidak perlu bertanya "tadi ngobrol
 * apa". Sekali simpan bisa langsung lahir tugas lanjutan.
 *
 * @var array $toko        id toko => nama
 * @var array $label       master tag pelanggan
 * @var array $pilihan     prioritas
 * @var array $belakangan  10 tugas lanjutan terakhir buatan akun ini
 */

$level = (string) session()->get('level');
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .catatan-halaman { max-width: 940px; }
    .catatan-kotak {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        padding: 1.1rem 1.25rem; margin-bottom: 1rem;
    }
    .catatan-kotak h2 {
        margin: 0 0 .3rem; font-size: .8rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .06em; color: #334155;
    }
    .catatan-hint { font-size: .82rem; color: #64748b; margin: 0 0 .9rem; }
    .catatan-kolom { display: flex; flex-wrap: wrap; gap: .6rem; }
    .catatan-kolom > div { flex: 1 1 200px; min-width: 0; }
    .catatan-saran { list-style: none; margin: .25rem 0 0; padding: 0; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
    .catatan-saran:empty { display: none; }
    .catatan-saran button { display: block; width: 100%; text-align: left; padding: .4rem .6rem; font-size: .82rem;
        background: #fff; border: 0; border-bottom: 1px solid #f1f5f9; cursor: pointer; }
    .catatan-saran button:hover { background: #f9fafb; }
    .catatan-saran span { color: #94a3b8; }
    .catatan-belakang { width: 100%; font-size: .84rem; }
    .catatan-belakang td { padding: .4rem .3rem; border-bottom: 1px solid #f1f5f9; }
    .catatan-belakang a { color: #0f172a; font-weight: 600; text-decoration: none; }
    .catatan-belakang a:hover { color: #da251d; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl catatan-halaman">

    <a class="mt-4 d-inline-flex align-items-center gap-2 text-decoration-none small" style="color:#64748b"
        href="<?= esc(site_url('admin/tugas')) ?>">
        <i class="fal fa-arrow-alt-left"></i> Kembali ke antrean tugas
    </a>

    <h1 class="h3 mt-2"><i class="fal fa-comments me-1 text-brand-500"></i> <?= esc($title) ?></h1>

    <?php if ($kabar !== '') : ?>
        <div class="alert alert-success py-2"><?= esc($kabar) ?></div>
    <?php endif; ?>
    <?php if ($galat !== '') : ?>
        <div class="alert alert-danger py-2"><?= esc($galat) ?></div>
    <?php endif; ?>

    <?= form_open('admin/tugas/catatan', ['class' => 'catatan-kotak']) ?>
        <h2><i class="fal fa-plus me-1"></i> Catatan baru</h2>
        <p class="catatan-hint">
            Isi begitu obrolan selesai. Kalau ada yang harus dikerjakan lagi, isi <em>Tindakan lanjutan</em>
            atau <em>Jadwal ulang</em> — catatannya otomatis masuk antrean tugas.
        </p>

        <?php if (count($toko) > 1) : ?>
            <div class="mb-2" style="max-width:260px">
                <label class="form-label small fw-bold" for="juragan">Toko</label>
                <select class="form-select form-select-sm" id="juragan" name="juragan_id" required>
                    <option value="">Pilih toko</option>
                    <?php foreach ($toko as $id_toko => $nama_t) : ?>
                        <option value="<?= (int) $id_toko ?>"><?= esc($nama_t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else : ?>
            <?php $id_toko = (int) array_key_first($toko); ?>
            <input type="hidden" name="juragan_id" value="<?= $id_toko ?>">
        <?php endif; ?>

        <div class="mb-2">
            <label class="form-label small fw-bold" for="cari">Pelanggan</label>
            <input type="search" class="form-control form-control-sm" id="cari" placeholder="ketik nama atau nomor WhatsApp"
                autocomplete="off">
            <input type="hidden" name="pelanggan_id" id="pelanggan_id" value="">
            <ul class="catatan-saran" id="saran"></ul>
            <div class="form-text" id="terpilih"></div>
            <?php if (count($toko) > 1) : ?>
                <div class="form-text">Pilih toko dulu, baru ketik nama atau nomornya.</div>
            <?php endif; ?>
        </div>

        <div class="mb-2">
            <label class="form-label small fw-bold" for="ringkasan">Ringkasan percakapan <span class="text-danger">*</span></label>
            <textarea class="form-control form-control-sm" id="ringkasan" name="ringkasan" rows="3" required
                placeholder="ditanya apa, dijawab apa, kesepakatannya apa"></textarea>
        </div>

        <div class="catatan-kolom mb-2">
            <div>
                <label class="form-label small fw-bold" for="tindakan">Tindakan lanjutan</label>
                <input type="text" class="form-control form-control-sm" id="tindakan" name="tindakan" maxlength="200"
                    placeholder="kosongkan kalau tidak ada pekerjaan">
            </div>
            <div>
                <label class="form-label small fw-bold" for="tenggat">Jadwal ulang</label>
                <input type="date" class="form-control form-control-sm" id="tenggat" name="tenggat">
            </div>
            <div>
                <label class="form-label small fw-bold" for="prioritas">Prioritas</label>
                <select class="form-select form-select-sm" id="prioritas" name="prioritas">
                    <?php foreach ($pilihan['prioritas'] as $kode => $nama) : ?>
                        <option value="<?= esc($kode) ?>" <?= $kode === 'normal' ? 'selected' : '' ?>><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold" for="tag">Label pelanggan</label>
            <div class="catatan-kolom">
                <div>
                    <select class="form-select form-select-sm" id="tag" name="tag_id">
                        <option value="0">Tidak mengubah label</option>
                        <?php foreach ($label as $l) : ?>
                            <option value="<?= (int) $l['id_tag'] ?>"><?= esc($l['nama_tag']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <input type="text" class="form-control form-control-sm" name="nama_tag_baru" maxlength="60"
                        placeholder="atau buat label baru">
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-sm btn-primary"><i class="fal fa-save me-1"></i> Simpan catatan</button>
    <?= form_close() ?>

    <div class="catatan-kotak">
        <h2><i class="fal fa-history me-1"></i> Catatan terakhir darimu</h2>
        <?php if ($belakangan === []) : ?>
            <p class="catatan-hint mb-0">Belum ada catatan dari akun ini.</p>
        <?php else : ?>
            <table class="catatan-belakang">
                <?php foreach ($belakangan as $b) : ?>
                    <tr>
                        <td>
                            <a href="<?= esc(site_url('admin/tugas/' . (int) $b['id_tugas'])) ?>"><?= esc($b['judul']) ?></a>
                            <div class="text-muted small">
                                <?= esc($b['nama_pelanggan'] ?: 'tanpa pelanggan') ?>
                                <?php if ($b['nama_dikerjakan']) : ?> &middot; dipegang <?= esc($b['nama_dikerjakan']) ?><?php endif; ?>
                            </div>
                        </td>
                        <td class="text-end text-muted text-nowrap small"><?= esc(date('d M Y', (int) $b['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
    // Pencari pelanggan untuk layar catatan: memakai endpoint sendiri (id + nama +
    // nomor saja) supaya tidak menarik data ongkir per baris seperti form orderan.
    (function () {
        var cari = document.getElementById('cari');
        var toko = document.getElementById('juragan');
        var hidden = document.getElementById('pelanggan_id');
        var saran = document.getElementById('saran');
        var terpilih = document.getElementById('terpilih');
        if (!cari) return;

        var timer = null;
        var url = '<?= site_url('admin/tugas/cari-pelanggan') ?>';

        function pilih(p) {
            hidden.value = p.id;
            cari.value = p.nama + (p.nomor ? ' · ' + p.nomor : '');
            saran.innerHTML = '';
            terpilih.textContent = 'Pelanggan terpilih: #' + p.id + ' · ' + p.nama;
        }

        function minta() {
            var q = cari.value.trim();
            var j = toko ? toko.value : '<?= (int) ($id_toko ?? 0) ?>';

            if (q.length < 2 || !j) { saran.innerHTML = ''; return; }

            fetch(url + '?q=' + encodeURIComponent(q) + '&juragan_id=' + j, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    saran.innerHTML = '';
                    (res.results || []).slice(0, 8).forEach(function (p) {
                        var li = document.createElement('li');
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.textContent = p.nama + ' ';
                        var n = document.createElement('span');
                        n.textContent = p.nomor || 'tanpa nomor';
                        b.appendChild(n);
                        b.addEventListener('click', function () { pilih(p); });
                        li.appendChild(b);
                        saran.appendChild(li);
                    });
                    if (!saran.children.length) {
                        var li = document.createElement('li');
                        li.innerHTML = '<button type="button" disabled>tidak ada yang cocok — simpan lewat form orderan dulu</button>';
                        saran.appendChild(li);
                    }
                })
                .catch(function () { saran.innerHTML = ''; });
        }

        cari.addEventListener('input', function () {
            hidden.value = '';
            terpilih.textContent = '';
            window.clearTimeout(timer);
            timer = window.setTimeout(minta, 250);
        });

        if (toko) toko.addEventListener('change', function () { hidden.value = ''; terpilih.textContent = ''; saran.innerHTML = ''; });
    })();
</script>
<?= $this->endSection() ?>
