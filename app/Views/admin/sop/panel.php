<?php

/**
 * Dua panel daftar dokumen: SOP dan PANDUAN.
 *
 * Satu kartu = satu dokumen: nomor, judul (teks biasa, bukan tautan), lalu
 * poin-poin isinya. Ikon pensil membuka popus sunting di tempat; ikon tempat
 * sampah menghapus dokumennya.
 *
 * Kartunya bisa diseret untuk mengubah urutan: dengan mouse cukup tekan dan geser,
 * di layar sentuh ditahan sebentar dulu supaya menggulir halaman dengan jari tetap
 * biasa. Angkanya sendiri dihitung CSS, jadi ia ikut berubah begitu DOM ditata
 * ulang; urutannya dikirim ke admin/sop/urut dan tersimpan di kolom urut.
 *
 * Dua bentuk, satu sumber:
 *  - halaman Daftar SOP  -> semua dokumen tampil penuh, kartu bisa diseret;
 *  - kartu dasbor CS     -> panel menampilkan dua dokumen dengan isinya dipotong
 *    (satu baris per poin + "..."), dan klik panelnya membuka dialog di tengah
 *    berisi seluruh dokumen kelompok itu.
 *
 * include() tidak membawa data, jadi semua kuncinya disiapkan controller pemanggil.
 *
 * @var array<string, list<array<string, mixed>>> $sop_grup   ['SOP' => [...], 'PANDUAN' => [...]]
 * @var bool                                      $sop_kelola Admin/Superadmin: boleh seret, sunting, hapus
 * @var bool                                      $sop_modal  panel jadi pemicu dialog isi lengkap
 * @var int                                       $sop_tampil jumlah kartu yang tetap terlihat di panel
 * @var array<int, array<string, string>>         $sop_bahan  nilai form sunting per dokumen (hanya saat kelola)
 * @var array<int, string>                        $kategori_sop dua pilihan kategori popus sunting
 */

$kartu = static function (array $s, ?int $poin_maks = null) use ($sop_kelola): string {
    $mati   = (int) $s['aktif'] !== 1;
    $wadah  = '"';
    $aksi   = '';
    $isi    = [];

    // kartu di dasbor ikut memunculkan isinya tapi dipotong: dua poin paling depan,
    // tiap poin satu baris dengan titik-titik dari pemotongan baris itu sendiri
    $poin_depan = array_slice($s['poin'] ?? [], 0, $poin_maks);

    foreach ($poin_depan as $p) {
        $isi[] = '<li>' . esc($p) . '</li>';
    }

    $bacaan = '';

    if ($poin_maks !== 0) {
        $bacaan = $isi === []
            ? '<p class="sop-panel-kosong isian">Belum ada isi.</p>'
            : '<ul class="sop-poin">' . implode('', $isi) . '</ul>';
    }

    if ($sop_kelola) {
        // seluruh badan kartu jadi gagang seret; JS yang membedakan mouse dan jari
        $wadah = ' sop-panel-seret" data-sop-gagang="1" title="Seret untuk mengubah urutan"';
        $aksi  = '<span class="sop-panel-aksi">'
            . '<button type="button" class="btn btn-sm btn-link text-decoration-none p-0" data-sop-sunting="' . (int) $s['id_sop'] . '"'
            . ' title="Sunting dokumen ini" aria-label="Sunting dokumen ini"><i class="fal fa-pen"></i></button>'
            . '<form action="' . esc(site_url('admin/sop/hapus')) . '" method="post" accept-charset="utf-8" class="d-inline-flex"'
            . ' onsubmit="return confirm(\'Hapus dokumen ini permanen? Isinya tidak bisa dikembalikan.\');">'
            . csrf_field()
            . '<input type="hidden" name="id_sop" value="' . (int) $s['id_sop'] . '">'
            . '<button type="submit" class="btn btn-sm btn-link text-decoration-none p-0 text-danger"'
            . ' title="Hapus dokumen ini" aria-label="Hapus dokumen ini"><i class="fal fa-trash-alt"></i></button>'
            . '</form></span>';
    }

    return '<li class="sop-panel-baris' . ($mati ? ' sop-panel-mati' : '') . ($poin_maks !== null ? ' sop-panel-ringkas' : '') . $wadah
        . ' data-id="' . (int) $s['id_sop'] . '">'
        . '<span class="sop-panel-nomor" aria-hidden="true"></span>'
        . '<span class="sop-panel-teks">' . esc($s['judul']) . '</span>'
        . $bacaan
        . $aksi
        . '</li>';
};
?>
<div class="sop-panel-luar">
    <?php foreach ($sop_grup as $nama => $daftar) : ?>
        <?php $depan = $sop_modal ? array_slice($daftar, 0, $sop_tampil) : $daftar; ?>
        <section class="sop-panel<?= $sop_modal ? ' sop-panel-klik' : '' ?>"<?= $sop_modal
            ? ' role="button" tabindex="0" data-bs-toggle="modal" data-bs-target="#sopLapis' . $nama . '"'
            . ' aria-label="Buka semua dokumen ' . esc($nama) . '"' : '' ?>>
            <h2 class="sop-panel-judul">
                <?= esc($nama) ?>
                <?php if ($sop_modal) : ?>
                    <span class="sop-panel-lebih" aria-hidden="true"><i class="fal fa-expand"></i></span>
                <?php endif; ?>
            </h2>
            <?php if ($daftar === []) : ?>
                <p class="sop-panel-kosong">Belum ada dokumen.</p>
            <?php else : ?>
                <ol class="sop-panel-daftar">
                    <?php foreach ($depan as $s) : ?>
                        <?= $kartu($s, $sop_modal ? 2 : null) ?>
                    <?php endforeach; ?>
                </ol>
                <?php if ($sop_modal && count($daftar) > count($depan)) : ?>
                    <p class="sop-panel-sisa">+<?= count($daftar) - count($depan) ?> dokumen lagi — klik panel ini</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<?php if ($sop_modal) : ?>
    <?php foreach ($sop_grup as $nama => $daftar) : ?>
        <div class="modal fade" id="sopLapis<?= $nama ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable sop-lapis-dialog">
                <div class="modal-content sop-lapis">
                    <div class="sop-lapis-kepala">
                        <span class="sop-lapis-judul"><?= esc($nama) ?></span>
                        <span class="sop-lapis-jumlah"><?= count($daftar) ?> dokumen</span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <?php if ($daftar === []) : ?>
                            <p class="sop-panel-kosong">Belum ada dokumen.</p>
                        <?php else : ?>
                            <ol class="sop-panel-daftar">
                                <?php foreach ($daftar as $s) : ?>
                                    <?= $kartu($s) ?>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($sop_kelola) : ?>
    <?php /* nilai tiap dokumen untuk popus sunting, sekali saja di halaman ini */ ?>
    <script id="sopBahan" type="application/json"><?= json_encode($sop_bahan ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <div class="modal fade" id="sopSunting" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable sop-lapis-dialog">
            <div class="modal-content sop-lapis">
                <div class="sop-lapis-kepala">
                    <span class="sop-lapis-judul">Sunting dokumen</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <?= form_open('admin/sop/simpan', ['class' => 'd-flex flex-column flex-grow-1']) ?>
                    <input type="hidden" name="id_sop" value="0">
                    <input type="hidden" name="aktif" value="1">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12 col-sm-8">
                                <label class="form-label" for="sop-judul">Judul <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="sop-judul" name="judul" maxlength="200" required>
                            </div>
                            <div class="col-12 col-sm-4">
                                <label class="form-label" for="sop-kategori">Kategori</label>
                                <select class="form-select form-select-sm" id="sop-kategori" name="kategori">
                                    <?php foreach ($kategori_sop as $kode) : ?>
                                        <option value="<?= esc($kode) ?>"><?= esc($kode) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="sop-isi">Isi</label>
                                <textarea class="form-control form-control-sm" id="sop-isi" name="isi" rows="12"
                                    placeholder="Tulis satu aturan per baris. Setiap baris jadi satu poin."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fal fa-save me-1"></i> Simpan</button>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
    /* dua panel berdampingan begitu muat 190px tiap kolom: di dalam kartu dasbor
       yang sempit itu tetap terbaca karena isinya dipotong satu baris */
    .sop-panel-luar { display: grid; gap: .9rem; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
    .sop-panel { background: #f8fafc; border: 1px solid #eef1f6; border-radius: .9rem; padding: .8rem .85rem .85rem; }
    .sop-panel-klik { cursor: pointer; }
    .sop-panel-klik:hover { border-color: #d7dee9; }
    .sop-panel-judul { margin: 0 0 .55rem; font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #667085; display: flex; align-items: baseline; gap: .5rem; }
    .sop-panel-lebih { margin-left: auto; font-size: .68rem; font-weight: 600; letter-spacing: 0; text-transform: none; color: #94a3b8; }
    .sop-panel-daftar { list-style: none; margin: 0; padding: 0; display: grid; gap: .7rem; counter-reset: sop-panel; }
    .sop-panel-baris {
        display: flex; flex-wrap: wrap; align-items: baseline; gap: .35rem;
        background: #fff; border: 1px solid #e2e8f0; border-radius: .9rem; padding: .75rem .85rem .8rem;
    }
    .sop-panel-ringkas { padding: .5rem .7rem; }
    .sop-panel-ringkas .sop-panel-teks { font-size: .88rem; }
    .sop-panel-nomor { counter-increment: sop-panel; flex: 0 0 auto; font-size: .9rem; font-weight: 700; color: #0f172a; }
    .sop-panel-nomor::before { content: counter(sop-panel) "."; }
    /* kartu yang boleh diseret: kursor tangan dan teks tidak ikut terseleksi;
       touch-action sengaja dibiarkan supaya menggulir halaman dengan jari tetap
       jalan — seret di layar sentuh baru aktif setelah ditahan (lihat JS) */
    .sop-panel-seret { cursor: grab; user-select: none; -webkit-touch-callout: none; }
    .sop-panel-seret:active { cursor: grabbing; }
    .sop-seret { opacity: .55; border-style: dashed; }
    .sop-panel-teks { font-size: .93rem; font-weight: 700; color: #0f172a; min-width: 0; }
    .sop-panel-mati .sop-panel-teks { color: #94a3b8; text-decoration: line-through; }
    /* ikon sunting/hapus duduk di baris paling bawah kartunya, rata ke tepi kanan;
       sama seperti poin, baris baru di flex box butuh flex-basis penuh */
    .sop-panel-aksi { display: flex; flex-basis: 100%; justify-content: flex-end; align-items: center; gap: .85rem; margin-top: .1rem; }
    .sop-panel-aksi .btn { font-size: .82rem; }
    /* poinnya satu baris sendiri di bawah judul: pemecah baris di flex box cuma
       bisa lewat flex-basis penuh */
    .sop-panel-baris > .sop-poin { flex-basis: 100%; min-width: 0; margin-top: .15rem; }
    .sop-panel-kosong { margin: 0; font-size: .86rem; color: #94a3b8; }
    .sop-panel-kosong.isian { flex-basis: 100%; margin-top: .15rem; }
    .sop-panel-sisa { margin: .6rem 0 0; font-size: .76rem; color: #94a3b8; }
    /* kartu ringkas di dasbor: tiap poin satu baris saja dan yang kepanjangan
       dipotong dengan titik-titik dari browser, sisanya dibaca di lapisan */
    .sop-panel-ringkas .sop-poin li {
        display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 1;
        overflow: hidden; line-height: 1.5;
    }
    .sop-panel-ringkas .sop-poin { margin-top: .1rem; }
    /* lapisan isinya: dialog tengah yang tingginya dibatasi, kepala ramping yang
       menempel waktu isinya digulir */
    .sop-lapis-dialog { max-width: 620px; }
    .sop-lapis { border: 0; border-radius: 1.15rem; background: #fff; max-height: 78vh; }
    .sop-lapis-kepala {
        position: sticky; top: 0; z-index: 2; display: flex; align-items: baseline; gap: .5rem;
        padding: .8rem .95rem .55rem; background: #fff; border-bottom: 1px solid #f1f5f9;
    }
    .sop-lapis-judul { font-size: .74rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #667085; }
    .sop-lapis-jumlah { font-size: .74rem; color: #94a3b8; }
    .sop-lapis-kepala .btn-close { margin: 0 0 0 auto; }
    .sop-lapis .modal-body { padding: .9rem .95rem 1.1rem; }
    .sop-lapis .sop-panel-baris { border-color: #eef1f6; }
</style>

<?php if ($sop_kelola) : ?>
<script>
(function () {
    'use strict';

    var yang = null, daftar = null, bergerak = false;
    var antre = null, pegangan = null, awal = null;

    function bersihkan() {
        if (antre) {
            window.clearTimeout(antre);
            antre = null;
        }

        pegangan = null;
        awal = null;
    }

    function pegang(gagang, id) {
        yang = gagang.closest('.sop-panel-baris');
        daftar = yang.closest('.sop-panel-daftar');
        bergerak = false;
        yang.classList.add('sop-seret');
        gagang.setPointerCapture(id);
    }

    function lepas() {
        if (! yang) {
            return;
        }

        var kartu = yang, isinya = daftar;
        yang = null;
        daftar = null;
        kartu.classList.remove('sop-seret');

        if (! bergerak) {
            return;
        }

        var urutan = Array.prototype.map.call(
            isinya.querySelectorAll('.sop-panel-baris'),
            function (b) { return b.getAttribute('data-id'); }
        );

        // token CSRF sudah dipasang global oleh $.ajaxSetup di template/main.php
        window.jQuery.post('<?= esc(site_url('admin/sop/urut'), 'js') ?>', { id: urutan });
    }

    document.addEventListener('pointerdown', function (e) {
        var gagang = e.target.closest('[data-sop-gagang]');
        var id = e.pointerId;
        bersihkan();

        if (! gagang || (e.pointerType === 'mouse' && e.button !== 0)) {
            return;
        }

        // jangan mulai menyeret dari tautan sunting atau tombol hapus
        if (e.target.closest('a, button')) {
            return;
        }

        if (e.pointerType === 'mouse') {
            pegang(gagang, id);
            e.preventDefault();

            return;
        }

        // jari: ditahan 320ms dulu baru jadi seret — gerakan sebelum itu adalah
        // gulir halaman biasa, jadi seluruh kartu tetap bisa dipegang tanpa
        // membuat halaman tidak bisa digulir lagi
        pegangan = gagang;
        awal = { x: e.clientX, y: e.clientY };
        antre = window.setTimeout(function () {
            antre = null;

            if (pegangan) {
                pegang(pegangan, id);
            }

            pegangan = null;
            awal = null;
        }, 320);
    });

    document.addEventListener('pointermove', function (e) {
        if (antre && awal) {
            var dx = e.clientX - awal.x, dy = e.clientY - awal.y;

            if (dx * dx + dy * dy > 100) {
                bersihkan();
            }

            return;
        }

        if (! yang) {
            return;
        }

        var kena = document.elementFromPoint(e.clientX, e.clientY);
        var sasaran = kena ? kena.closest('.sop-panel-baris') : null;

        if (! sasaran || sasaran === yang || sasaran.parentNode !== daftar) {
            return;
        }

        var kotak = sasaran.getBoundingClientRect();
        var bawah = e.clientY > kotak.top + kotak.height / 2;

        daftar.insertBefore(yang, bawah ? sasaran.nextSibling : sasaran);
        bergerak = true;
    });

    // kartu yang sedang dipegang tidak boleh ditinggal gulir halaman
    document.addEventListener('touchmove', function (e) {
        if (yang) {
            e.preventDefault();
        }
    }, { passive: false });

    function selesai() {
        bersihkan();
        lepas();
    }

    document.addEventListener('pointerup', selesai);
    document.addEventListener('pointercancel', selesai);

    // popus sunting: satu form untuk semua kartu, isinya diambil dari blok JSON
    // di atas supaya tidak ada atribut panjang di tiap kartu
    var sunting = document.getElementById('sopSunting');
    var bahan = JSON.parse(document.getElementById('sopBahan').textContent) || {};

    if (sunting) {
        document.addEventListener('click', function (e) {
            var tombol = e.target.closest('[data-sop-sunting]');

            if (! tombol) {
                return;
            }

            var id = tombol.getAttribute('data-sop-sunting');

            if (! bahan[id]) {
                return;
            }

            sunting.querySelector('[name="id_sop"]').value = id;
            sunting.querySelector('[name="aktif"]').value = bahan[id].aktif;
            sunting.querySelector('[name="judul"]').value = bahan[id].judul;
            sunting.querySelector('[name="kategori"]').value = bahan[id].kategori;
            sunting.querySelector('[name="isi"]').value = bahan[id].isi;

            window.bootstrap.Modal.getOrCreateInstance(sunting).show();
        });
    }
})();
</script>
<?php endif; ?>
