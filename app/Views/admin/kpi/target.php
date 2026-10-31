<?php
/**
 * Target KPI.
 *
 * Yang boleh diubah hanya angka target dan aktif/tidaknya indikator. Kolom
 * "Bobot %" sudah dilepas bersama indikator chat/lead/resolution: satu-satunya
 * target yang tersisa adalah GMV per CS (plus target Tim CS), jadi tidak ada lagi
 * yang perlu ditimbang. Keterangan di sini dibuat satu kalimat biasa; definisi
 * teknis dan rumus hitungnya hanya muncul saat selnya ditahan (tooltip) dan di
 * halaman detail tiap indikator.
 *
 * Variabel di bawah disuntik runtime oleh Admin\Kpi::target() lewat
 * view('admin/kpi/target', $data). CI4 meng-extract array itu saat render,
 * jadi tidak ada assignment di file ini dan Intelephense (P1008)
 * menyangkanya tak terdefinisi. Blok @var ini hanya penanda tipe untuk
 * analyzer, bukan kode yang dijalankan.
 *
 * @var array  $target   daftar indikator: [['indikator','nama','peran','arah','satuan','target','target_tim','aktif','definisi','aturan','updated_at','updated_by'], ...]
 * @var list   $poin     aturan poin: [['slug','nama','sumber','satuan','poin','poin_baku','kustom'], ...]
 * @var array  $batas    batas nilai poin yang diterima server: ['min' => float, 'max' => float]
 * @var bool   $boleh    pengguna boleh mengubah target; khusus Superadmin (admin
 *                       sudah dibelokkan ke Ringkasan oleh Kpi::target(), jadi
 *                       mode baca di bawah ini cuma cadangan kalau rutenya berubah)
 * @var string $kabar    pesan flash hasil simpan, kosong jika tidak ada
 * @var array  $pembaru  [user_id => nama] untuk mencantumkan siapa yang mengubah
 */

/** Bahasa sehari-hari per indikator, tanpa istilah teknis. */
$sederhana = [
    'gmv_cs'                => 'Jumlah pcs produk katalog dari nota yang sudah lunas, dihitung per CS. Punya dua target: per CS (satu orang) dan Tim CS (total seluruh tim).',
];

$isi = static function ($nilai, string $satuan): string {
    if ($nilai === null || $nilai === '') {
        return '&mdash;';
    }

    $n = (float) $nilai;

    if ($satuan === 'rupiah') {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }

    // desimal hanya ditampilkan kalau memang ada: 1250 pcs bukan 1.250,0 pcs
    return number_format($n, fmod($n, 1.0) === 0.0 ? 0 : 1, ',', '.') . ' ' . $satuan;
};

/** Nilai di dalam kotak angka: rupiah selalu bulat penuh, tanpa ".00" di ujung. */
$kotak = static function ($nilai, string $satuan): string {
    if ($nilai === null || $nilai === '') {
        return '';
    }

    return $satuan === 'rupiah' ? (string) (int) $nilai : (string) (float) $nilai;
};

/** Teks poin: 2,0 tampil sebagai +2 dan 0,5 sebagai +0,5 tanpa nol di ujung. */
$poin_tampil = static function (float $n): string {
    $teks = rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');

    return ($n <=> 0) > 0 ? '+' . $teks : $teks;
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">Target KPI</h1>

    <?php if ($kabar !== '') { ?>
        <div class="alert alert-success py-2"><?= esc($kabar) ?></div>
    <?php } ?>

    <p class="text-muted small">
        Isi target sesuai keputusan manajemen. Yang masih kosong belum dinilai, jadi kartu di
        halaman Ringkasan menulis "target belum diisi".
    </p>

    <div class="card mb-4">
        <div class="card-body pb-0">
            <?= form_open('admin/kpi/target') ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Indikator</th>
                            <th scope="col" style="min-width: 200px;">Keterangan</th>
                            <th scope="col" class="text-center">Arah</th>
                            <?php if ($boleh) { ?>
                                <th scope="col" style="width: 150px;">Target</th>
                                <th scope="col" class="text-center">Aktif</th>
                            <?php } else { ?>
                                <th scope="col" class="text-end">Target</th>
                            <?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($target as $t) { ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc($t['nama']) ?></div>
                                    <div class="small text-muted">
                                        role <?= esc($t['peran'] === 'cs' ? 'CS' : ucfirst($t['peran'])) ?>
                                        <?php if ($t['updated_at']) { ?>
                                            &middot; diubah <?= date('j M Y', (int) $t['updated_at']) ?>
                                            <?php if (isset($pembaru[(int) $t['updated_by']])) { ?>
                                                oleh <?= esc($pembaru[(int) $t['updated_by']]) ?>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                </td>
                                <td class="small" title="<?= esc(trim($t['definisi'] . ' ' . $t['aturan'])) ?>">
                                    <?= esc($sederhana[$t['indikator']] ?? $t['definisi']) ?>
                                </td>
                                <td class="text-center small">
                                    <?= $t['arah'] === 'lebih_rendah' ? 'makin kecil makin baik' : 'makin besar makin baik' ?>
                                    <div class="text-muted">satuan: <?= esc($t['satuan']) ?></div>
                                </td>
                                <?php if ($boleh) { ?>
                                    <td>
                                        <?php if ($t['indikator'] === 'gmv_cs') : ?>
                                            <div class="small text-muted mb-1">Per CS (<?= esc($t['satuan']) ?>)</div>
                                        <?php endif; ?>
                                        <?= form_input([
                                            'class'       => 'form-control form-control-sm',
                                            'type'        => 'number',
                                            // pcs tidak mengenal setengah unit; rupiah dan
                                            // persen boleh desimal
                                            'step'        => $t['satuan'] === 'pcs' ? '1' : '0.01',
                                            'min'         => '0',
                                            'name'        => 'target[' . $t['indikator'] . ']',
                                            'placeholder' => $t['satuan'],
                                            'value'       => $kotak($t['target'], $t['satuan']),
                                        ]) ?>
                                        <?php if ($t['indikator'] === 'gmv_cs') : ?>
                                            <div class="small text-muted mt-2 mb-1">Tim CS (total <?= esc($t['satuan']) ?>)</div>
                                            <?= form_input([
                                                'class'       => 'form-control form-control-sm',
                                                'type'        => 'number',
                                                'step'        => $t['satuan'] === 'pcs' ? '1' : '0.01',
                                                'min'         => '0',
                                                'name'        => 'target_tim[' . $t['indikator'] . ']',
                                                'placeholder' => $t['satuan'],
                                                'value'       => $kotak($t['target_tim'] ?? null, $t['satuan']),
                                            ]) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?= form_checkbox([
                                            'name'    => 'aktif[' . $t['indikator'] . ']',
                                            'value'   => '1',
                                            'checked' => (int) $t['aktif'] === 1,
                                            'class'   => 'form-check-input',
                                        ]) ?>
                                    </td>
                                <?php } else { ?>
                                    <td class="text-end">
                                        <?= $isi($t['target'], $t['satuan']) ?>
                                        <?php if ($t['indikator'] === 'gmv_cs') : ?>
                                            <div class="small text-muted">
                                                tim: <?= $isi($t['target_tim'] ?? null, $t['satuan']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php } ?>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php if ($boleh) { ?>
                <div class="card-footer bg-white text-end">
                    <?= form_submit(['class' => 'btn btn-sm btn-primary', 'value' => 'Simpan semua target']) ?>
                </div>
            <?php } ?>
            <?= form_close(); ?>
        </div>
    </div>

    <?php if (! $boleh) { ?>
        <p class="alert alert-light border small">
            Hanya Superadmin yang boleh menentukan target. Akun Anda hanya bisa membaca angka yang berlaku.
        </p>
    <?php } ?>

    <div class="card mb-4">
        <div class="card-body pb-0">
            <h2 class="h6 mb-1">Poin aktivitas</h2>
            <p class="text-muted small mb-3">
                Berapa poin yang didapat satu satuan kerja. Ranking karyawan menjumlahnya dari kolom
                yang sama, jadi angka di sini langsung mengubah total poin. Kosongkan kotak, atau isi
                sama persis dengan poin bawaan, untuk kembali ke angka kode.
            </p>
            <?= form_open('admin/kpi/poin') ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Aktivitas</th>
                            <th scope="col">Sumber data</th>
                            <th scope="col" class="text-center">Bawaan</th>
                            <th scope="col" style="width: 150px;">Poin berlaku</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($poin as $a) { ?>
                            <?php
                            $baku = (float) $a['poin_baku'];
                            $kini = (float) $a['poin'];
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc($a['nama']) ?></div>
                                    <div class="small text-muted">satuan: <?= esc($a['satuan']) ?></div>
                                    <?php if (isset($a['catatan'])) { ?>
                                        <div class="small text-muted"><?= esc($a['catatan']) ?></div>
                                    <?php } ?>
                                </td>
                                <td class="small text-muted"><code><?= esc($a['sumber']) ?></code></td>
                                <td class="text-center"><?= $poin_tampil($baku) ?></td>
                                <td>
                                    <?php if ($boleh) { ?>
                                        <?= form_input([
                                            'class'       => 'form-control form-control-sm',
                                            'type'        => 'number',
                                            'step'        => '0.1',
                                            'min'         => (string) $batas['min'],
                                            'max'         => (string) $batas['max'],
                                            'name'        => 'poin[' . $a['slug'] . ']',
                                            'placeholder' => $poin_tampil($baku),
                                            'value'       => $kini === $baku ? '' : (string) $kini,
                                        ]) ?>
                                        <?php if (! empty($a['kustom'])) { ?>
                                            <div class="small text-primary mt-1">
                                                dikustom &middot; bawaan <?= $poin_tampil($baku) ?>
                                            </div>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <div class="text-end">
                                            <?= $poin_tampil($kini) ?>
                                            <?php if (! empty($a['kustom'])) { ?>
                                                <div class="small text-muted">bawaan <?= $poin_tampil($baku) ?></div>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php if ($boleh) { ?>
                <div class="card-footer bg-white text-end">
                    <?= form_submit(['class' => 'btn btn-sm btn-primary', 'value' => 'Simpan semua poin']) ?>
                </div>
            <?php } ?>
            <?= form_close(); ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
