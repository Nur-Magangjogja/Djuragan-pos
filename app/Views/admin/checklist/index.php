<?php

/**
 * Daftar sesi checklist (toko harian + pemeriksaan email).
 *
 * Semua variabel di bawah disuntik Admin\Checklist::index() lewat view() (extract),
 * jadi analyzer tidak bisa menelusurinya dari file ini. Blok @var di bawah hanya
 * memberi tahunya ke Intelephense, bukan kode yang dijalankan.
 *
 * @var string $title      judul halaman
 * @var array  $baris      hasil ChecklistModel::catatan() — satu baris satu catatan
 * @var array  $s          saringan yang sudah dibersihkan controller
 * @var array  $toko       id_toko => baris toko yang boleh dibuka akun ini
 * @var bool   $kelola     akun ini admin/superadmin
 * @var bool   $delegasi   layar delegasi boleh dipakai (khusus superadmin, tokonya ada)
 * @var bool   $tabDelegasi tab "Delegasi toko" yang sedang dibuka
 * @var array  $calon      id akun => "nama (level)" — Admin/CS aktif yang bisa memegang toko
 * @var array  $pemegang   id_toko => daftar akun yang boleh membuka lembar toko itu
 * @var string $kabar      pesan sukses flash (string kosong kalau tidak ada)
 * @var string $galat      pesan gagal flash (string kosong kalau tidak ada)
 * @var int    $total      jumlah sesi sesuai saringan
 * @var int    $page       halaman aktif (berbasis satu)
 * @var int    $batas      baris per halaman
 */

$level = (string) session()->get('level');

$tanya = static function (array $ubah): string {
    $q = array_merge($_GET, $ubah);

    if (! array_key_exists('halaman', $ubah)) {
        unset($q['halaman']);
    }

    foreach (['kerja', 'tab', 'jenis', 'status', 'tanggal', 'toko', 'batas', 'halaman'] as $k) {
        if (isset($q[$k]) && $q[$k] === '') {
            unset($q[$k]);
        }
    }

    return site_url('admin/checklist') . '?' . http_build_query($q);
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .ckl-halaman { max-width: 1180px; }
    /* tombol di kanan kepala halaman digeser kiri 72px: lonceng notifikasi desktop
       melayang di pojok kanan atas dan menutupinya begitu jendela tidak terlalu lebar */
    .ckl-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; padding-right: 4.5rem; }
    /* blok judul boleh memendek (keterangan turun satu baris) supaya tombol tetap
       satu baris di kanan, tidak jatuh ke baris sendiri */
    .ckl-head > .me-auto { flex: 1 1 0; min-width: 0; }
    .ckl-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }

    .ckl-kartu {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        overflow: hidden; margin-bottom: 1rem;
    }
    .ckl-kartu > .ckl-judul {
        padding: .7rem 1rem; border-bottom: 1px solid #eaecf0;
        font-size: .78rem; font-weight: 700; color: #344054;
        text-transform: uppercase; letter-spacing: .05em;
    }

    .ckl-saring { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .55rem; padding: 1rem; border-bottom: 1px solid #eaecf0; }
    .ckl-saring .lapangan { display: flex; flex-direction: column; gap: .2rem; }
    .ckl-saring label { font-size: .7rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .ckl-saring .form-control, .ckl-saring .form-select { max-width: 180px; }
    /* tombol ikon saja selalu lebih pendek dari tombol bertulisan di sampingnya;
       stretch + tinggi minimum 2rem membuat keduanya setinggi sama */
    .ckl-saring-aksi .btn { min-height: 2rem; }
    .ckl-saring-aksi a.btn { align-items: center; justify-content: center; min-width: 2.4rem; }

    .table.ckl-daftar { margin: 0; }
    .ckl-daftar th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; background: #f9fafb; }
    .ckl-daftar td { vertical-align: middle; font-size: .86rem; }
    .ckl-daftar thead th:first-child { width: 34%; }
    .ckl-nama { font-weight: 700; color: #0f172a; text-decoration: none; display: block; }
    .ckl-nama:hover { color: #da251d; }
    .ckl-sub { font-size: .76rem; color: #64748b; display: flex; flex-wrap: wrap; gap: .2rem .6rem; margin-top: .1rem; }
    .ckl-kosong { padding: 2.6rem 1rem; text-align: center; color: #94a3b8; }
    .ckl-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }

    .pil-ckl { display: inline-block; padding: .18rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
    .c-berjalan { background: #fef3e2; color: #b45309; }
    .c-selesai { background: #e7f6ec; color: #14663a; }

    /* judul catatan yang sedang diselesaikan, ditampilkan di dalam popus supaya
       orang tidak perlu menutup daftar untuk ingat yang mana yang dipilih */
    .ckl-selesai-judul {
        font-size: .86rem; font-weight: 700; color: #0f172a;
        padding: .55rem .75rem; border-left: 3px solid #101828; background: #f6f8fb; border-radius: .4rem;
    }

    .ckl-pager { padding: .8rem 1rem; background: #fff; border-top: 1px solid #eaecf0; }

    /* dua keadaan kerja, bukan filter bertumpuk: garis bawah tipis seperti tab
       rumah, bukan kotak Bootstrap. Tombol "Buat catatan" duduk sejajar dengan
       tab di ujung kanan baris yang sama */
    .ckl-tabs { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .5rem; padding: .9rem 1rem .2rem; }
    .ckl-tab {
        padding: .3rem .85rem; border-radius: 999px; font-size: .78rem; font-weight: 700;
        color: #475569; text-decoration: none;
    }
    .ckl-tab:hover { color: #0f172a; background: #f4f6f9; }
    .ckl-tab.aktif { color: #fff; background: #101828; }

    /* kartu delegasi: toko + siapa yang memegangnya sekarang. Daftar tokonya bisa
       panjang, jadi bagian dalam kartunya yang bergulir sementara tinggi kartu
       tetap — halaman tidak memanjang cuma untuk mencar satu toko */
    /* tombol aksi utama ikut baris tab (ms-auto di ujung kanan), bukan baris
       sendiri di atasnya: "Buat catatan" saat daftar catatan dibuka,
       "Delegasikan toko" saat tab delegasi dibuka — tab-nya dan tombolnya selalu
       sejajar, jadi mata tidak mencari dua posisi berbeda */
    .ckl-tabs > .btn { text-transform: none; letter-spacing: 0; }
    .ckl-deleg-aksi {
        display: flex; flex-wrap: wrap; align-items: center; gap: .6rem;
        padding: .15rem 1rem .6rem; border-bottom: 1px solid #eef1f6;
    }
    .ckl-deleg-catat { flex: 1 1 16rem; min-width: 0; font-size: .74rem; color: #94a3b8; }
    .ckl-deleg { max-height: 30rem; overflow-y: auto; }
    .ckl-deleg-baris {
        display: flex; flex-wrap: wrap; align-items: center; gap: .5rem;
        padding: .55rem 1rem; border-bottom: 1px solid #eef1f6;
    }
    .ckl-deleg-baris:last-child { border-bottom: 0; }
    .ckl-deleg-nama { flex: 0 1 12rem; min-width: 0; font-size: .84rem; font-weight: 700; color: #0f172a; }
    .ckl-deleg-nama i { margin-right: .35rem; color: #64748b; }
    .ckl-deleg-orang { display: flex; flex-wrap: wrap; gap: .35rem; flex: 1 1 12rem; min-width: 0; }
    .ckl-deleg-baris > .btn { margin-left: auto; }
    .pil-deleg {
        display: inline-flex; align-items: center; gap: .35rem; padding: .14rem .2rem .14rem .55rem;
        border-radius: 999px; background: #eef1f6; color: #344054; font-size: .74rem; font-weight: 600;
    }
    .pil-deleg em {
        font-style: normal; font-size: .6rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .05em; color: #64748b;
    }
    /* chip abu-abu = akses yang ikut delegasi juragan; yang warna = tautan toko
       ini saja. Dua jalur, dua warna, supaya orang tahu apa yang baru dicabut */
    .pil-warisan { background: #f6f8fb; color: #64748b; }
    .pil-deleg form { display: inline-flex; }
    .pil-deleg-x {
        display: inline-flex; align-items: center; justify-content: center;
        width: 1.2rem; height: 1.2rem; padding: 0; border: 0; border-radius: 50%;
        background: #fff; color: #b81c16; font-size: .62rem; line-height: 1; cursor: pointer;
    }
    .pil-deleg-x:hover, .pil-deleg-x:focus-visible { background: #b81c16; color: #fff; }
    .ckl-tar { font-size: .74rem; color: #94a3b8; }

    @media (max-width: 991.98px) {
        /* judul + keterangan harus punya barisnya sendiri: selama ia flex:1 1 0
           ia berebut ruang dengan tombol dan menyusut sampai teksnya turun
           dua puluh baris (terukur: kepala 740px tinggi di lebar 390px) */
        .ckl-head { padding-right: 0; }
        .ckl-head > .me-auto { flex: 1 1 100%; }
        .ckl-head > .btn { flex: 1 1 auto; }
        .ckl-tabs { padding-left: .75rem; padding-right: .75rem; }
        .ckl-tabs > .btn { flex: 1 1 100%; }
        .ckl-tab { flex: 1 1 auto; text-align: center; padding-left: .4rem; padding-right: .4rem; }
        .ckl-saring > .lapangan { flex: 1 1 auto; min-width: 0; }
        .ckl-saring .form-control, .ckl-saring .form-select { max-width: none; }

        /* tab delegasi adalah seluruh isi kartu saat itu dibuka, jadi tidak perlu
           dipotong lagi — yang dulu bikin 5.100px adalah kartu delegasi yang
           berdiri sendiri di atas daftar catatan */
        .ckl-deleg { max-height: none; }
        .ckl-deleg-aksi { padding-left: .75rem; padding-right: .75rem; }
        .ckl-deleg-nama { flex: 1 1 100%; }
        .ckl-deleg-baris > .btn { margin-left: 0; }
    }

    @media (min-width: 992px) {
        #modalDelegasi .modal-content { max-height: 80vh; }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl ckl-halaman">

    <div class="ckl-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><i class="fal fa-ballot-check me-1 text-brand-500"></i> <?= esc($title) ?></h1>
            <p>Catatan kerja tiap toko yang jadi delegasimu. Yang ditulis CS langsung terhitung selesai; yang ditulis Admin atau Superadmin masuk daftar yang masih harus dikerjakan.</p>
        </div>
        <?php if ($kelola) : ?>
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/checklist/aturan')) ?>">
                <i class="fal fa-list-check me-1"></i> Templat Butir
            </a>
        <?php endif; ?>
    </div>

    <?php if ($kabar !== '') : ?>
        <div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div>
    <?php endif; ?>
    <?php if ($galat !== '') : ?>
        <div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div>
    <?php endif; ?>

    <div class="ckl-kartu">
        <div class="ckl-tabs">
            <a class="ckl-tab <?= ! $tabDelegasi && $s['kerja'] === 'belum' ? 'aktif' : '' ?>"
                href="<?= esc($tanya(['kerja' => 'belum', 'tab' => ''])) ?>">Belum dikerjakan</a>
            <a class="ckl-tab <?= ! $tabDelegasi && $s['kerja'] === 'sudah' ? 'aktif' : '' ?>"
                href="<?= esc($tanya(['kerja' => 'sudah', 'tab' => ''])) ?>">Sudah dikerjakan</a>
            <?php if ($delegasi) : ?>
                <a class="ckl-tab <?= $tabDelegasi ? 'aktif' : '' ?>"
                    href="<?= esc($tanya(['tab' => 'delegasi'])) ?>">Delegasi toko</a>
            <?php endif; ?>
            <?php if ($toko !== [] && ! $tabDelegasi) : ?>
                <button type="button" class="btn btn-sm btn-dark ms-auto" id="tombolCatatan"
                    data-bs-toggle="modal" data-bs-target="#modalCatatan">
                    <i class="fal fa-plus me-1"></i> Buat catatan
                </button>
            <?php elseif ($tabDelegasi) : ?>
                <button type="button" class="btn btn-sm btn-dark ms-auto"
                    data-bs-toggle="modal" data-bs-target="#modalDelegasi" data-toko="">
                    <i class="fal fa-user-plus me-1"></i> Delegasikan toko
                </button>
            <?php endif; ?>
        </div>
        <?php if ($tabDelegasi) : ?>
            <!-- isi tab delegasi hanya dirender saat tabnya dibuka: daftar tokonya
               panjang dan bukan sesuatu yang perlu dilihat orang yang sedang
               mengerjakan catatan -->
            <div class="ckl-deleg-aksi">
                <span class="ckl-deleg-catat">Toko yang boleh membuka lembarannya sendiri. Yang abu-abu ikut delegasi juragan dan berlaku di semua halaman.</span>
            </div>
            <div class="ckl-deleg">
                <?php foreach ($toko as $id_toko => $t) : ?>
                    <?php $pegang = $pemegang[(int) $id_toko] ?? []; ?>
                    <div class="ckl-deleg-baris">
                        <div class="ckl-deleg-nama"><i class="fal fa-store"></i><?= esc($t['nama']) ?></div>
                        <div class="ckl-deleg-orang">
                            <?php if ($pegang === []) : ?>
                                <span class="ckl-tar">belum didelegasikan</span>
                            <?php endif; ?>
                            <?php foreach ($pegang as $o) : ?>
                                <?php $tautan = $o['sumber'] === 'tautan'; ?>
                                <div class="pil-deleg<?= $tautan ? '' : ' pil-warisan' ?>"
                                    title="<?= $tautan ? 'Tautan toko ini saja' : 'Ikut delegasi juragan, berlaku di semua halaman' ?>">
                                    <?= esc($o['nama']) ?> <em><?= esc($o['level']) ?></em>
                                    <?= form_open($tautan ? 'admin/checklist/copot-tautan' : 'admin/checklist/copot-delegasi') ?>
                                        <input type="hidden" name="id_toko" value="<?= (int) $id_toko ?>">
                                        <input type="hidden" name="user_id" value="<?= (int) $o['id'] ?>">
                                        <button type="submit" class="pil-deleg-x"
                                            title="Lepas <?= esc($o['nama']) ?> dari toko ini"
                                            aria-label="Lepas <?= esc($o['nama']) ?> dari toko <?= esc($t['nama']) ?>">
                                            <i class="fal fa-xmark"></i>
                                        </button>
                                    <?= form_close() ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill"
                            data-bs-toggle="modal" data-bs-target="#modalDelegasi" data-toko="<?= (int) $id_toko ?>"
                            title="Delegasikan toko ini ke akun lain"
                            aria-label="Delegasikan <?= esc($t['nama']) ?>">
                            <i class="fal fa-user-plus"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
        <form class="ckl-saring" method="get" action="<?= esc(site_url('admin/checklist')) ?>">
            <input type="hidden" name="kerja" value="<?= esc($s['kerja']) ?>">
            <?php if (count($toko) > 1) : ?>
                <div class="lapangan">
                    <label for="toko">Toko</label>
                    <select class="form-select form-select-sm" id="toko" name="toko">
                        <option value="">Semua toko</option>
                        <?php foreach ($toko as $id_toko => $t) : ?>
                            <option value="<?= (int) $id_toko ?>" <?= (int) $s['toko_id'] === (int) $id_toko ? 'selected' : '' ?>>
                                <?= esc($t['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="lapangan">
                <label for="tanggal">Tanggal</label>
                <input type="date" class="form-control form-control-sm" id="tanggal" name="tanggal" value="<?= esc($s['tanggal']) ?>">
            </div>
            <div class="lapangan">
                <label for="batas">Baris</label>
                <select class="form-select form-select-sm" id="batas" name="batas">
                    <?php foreach ([10, 25, 50] as $n) : ?>
                        <option value="<?= $n ?>" <?= $batas === $n ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lapangan">
                <label>&nbsp;</label>
                <div class="d-inline-flex gap-2 align-items-stretch ckl-saring-aksi">
                    <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-search"></i> Cari</button>
                    <a class="btn btn-sm btn-dark text-decoration-none d-inline-flex"
                        href="<?= esc(site_url('admin/checklist')) ?>" title="Ulangi" aria-label="Ulangi">
                        <i class="fal fa-sync"></i>
                    </a>
                </div>
            </div>
        </form>

        <?php if ($baris === []) : ?>
            <div class="ckl-kosong">
                <i class="fal fa-circle-check text-success"></i>
                <?= $s['kerja'] === 'sudah'
                    ? 'Belum ada catatan yang selesai di sini.'
                    : 'Tidak ada yang menunggu. Tulis catatan baru lewat tombol "Buat catatan" di atas.' ?>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle ckl-daftar tabel-rapi">
                    <thead>
                        <tr>
                            <th>Catatan</th>
                            <th>Toko</th>
                            <th>Tanggal</th>
                            <th>Siapa yang buat</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baris as $r) : ?>
                            <?php $beres = (string) $r['status'] !== 'belum'; ?>
                            <tr>
                                <td>
                                    <span class="ckl-nama"><?= esc($r['judul']) ?></span>
                                    <div class="ckl-sub">
                                        <span><?= $r['item_id'] === null ? 'catatan sendiri' : 'butir templat' ?></span>
                                        <?php if (! empty($r['nama_tujuan'])) : ?>
                                            <span><i class="fal fa-user-check"></i> untuk <?= esc($r['nama_tujuan']) ?></span>
                                        <?php endif; ?>
                                        <?php if ((int) $r['perlu_tindakan'] === 1) : ?>
                                            <span class="text-danger"><i class="fal fa-triangle-exclamation"></i> perlu tindakan</span>
                                        <?php endif; ?>
                                        <?php if ($r['alasan']) : ?>
                                            <span><i class="fal fa-info-circle"></i> <?= esc($r['alasan']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($r['catatan_butir']) : ?>
                                            <span><i class="fal fa-note-sticky"></i> <?= esc($r['catatan_butir']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-nowrap"><?= esc($r['nama_toko'] ?: 'Toko #' . (int) $r['toko_id']) ?></td>
                                <td class="text-nowrap"><?= esc(tanggal_rincian((string) $r['tanggal'])) ?></td>
                                <td class="text-muted">
                                    <?php
                                    $pembuat = $r['nama_dibuat'] ?: $r['nama_dikerjakan'];
                                    echo $pembuat ? esc($pembuat) : '<span class="text-muted">belum ada</span>';
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if (! $beres) : ?>
                                        <button type="button" class="btn btn-sm btn-link text-decoration-none"
                                            data-bs-toggle="modal" data-bs-target="#modalSelesai"
                                            data-run="<?= (int) $r['run_id'] ?>" data-hasil="<?= (int) $r['id_hasil'] ?>"
                                            data-judul="<?= esc($r['judul'], 'attr') ?>">Selesaikan</button>
                                    <?php else : ?>
                                        <a class="btn btn-sm btn-link text-decoration-none"
                                            href="<?= esc(site_url('admin/checklist/' . (int) $r['run_id'])) ?>">Lihat</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($total > $batas) : ?>
            <div class="ckl-pager d-flex flex-wrap align-items-center gap-2">
                <span class="small text-muted"><?= (int) $total ?> catatan &middot; halaman <?= (int) $page ?></span>
                <div class="ms-auto">
                    <?= \Config\Services::pager()->makeLinks($page, $batas, $total, 'front_full') ?>
                </div>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($delegasi) : ?>
        <!-- satu popus untuk seluruh toko: tombol + di baris mana pun hanya mengisi
             tokonya, jadi tidak ada dua set nama field di halaman yang sama -->
        <div class="modal fade" id="modalDelegasi" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <?= form_open('admin/checklist/delegasi') ?>
                        <div class="modal-header">
                            <h5 class="modal-title">Delegasikan toko</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label small fw-bold" for="dl-toko">Toko <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="dl-toko" name="id_toko" required>
                                <?php foreach ($toko as $id_toko => $t) : ?>
                                    <option value="<?= (int) $id_toko ?>"><?= esc($t['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <label class="form-label small fw-bold mt-2" for="dl-user">Ke akun <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="dl-user" name="user_id" required>
                                <option value="">Pilih akun</option>
                                <?php foreach ($calon as $id_u => $nama_u) : ?>
                                    <option value="<?= (int) $id_u ?>"><?= esc($nama_u) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-user-plus me-1"></i> Delegasikan</button>
                        </div>
                    <?= form_close() ?>
                </div>
            </div>
        </div>

        <script>
    (function () {
        var modal = document.getElementById('modalDelegasi');

        if (! modal) {
            return;
        }

        // "+" di baris toko mana pun membuka popus yang sama, hanya tokonya yang
        // ikut terisi; tombol kepala tidak membawa data-toko jadi biarkan pilihan
        // terakhir apa adanya
        modal.addEventListener('show.bs.modal', function (event) {
            var pemicu = event.relatedTarget;

            if (! pemicu) {
                return;
            }

            var toko = pemicu.getAttribute('data-toko');

            if (toko) {
                modal.querySelector('#dl-toko').value = toko;
            }

            modal.querySelector('#dl-user').value = '';
        });
    }());
        </script>
    <?php endif; ?>

    <?php if ($toko !== []) : ?>
        <!-- popus catatan baru: toko + catatan, dua-duanya wajib. Tidak ada pilihan
             tanggal karena catatan selalu untuk hari ini -->
        <div class="modal fade" id="modalCatatan" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <?= form_open('admin/checklist/catatan') ?>
                        <div class="modal-header">
                            <h5 class="modal-title">Buat catatan</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label small fw-bold" for="ct-toko">Toko <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="ct-toko" name="toko_id" required>
                                <?php $pertama = true; ?>
                                <?php foreach ($toko as $id_toko => $t) : ?>
                                    <option value="<?= (int) $id_toko ?>" <?= $pertama ? 'selected' : '' ?>><?= esc($t['nama']) ?></option>
                                    <?php $pertama = false; ?>
                                <?php endforeach; ?>
                            </select>

                            <?php if ($kelola) : ?>
                                <?php
                                // daftar pemegang tiap toko ikut ditulis sekali di atribut
                                // select ini; berpindah toko tinggal mengisi ulang dropdown,
                                // tanpa request tambahan ke server
                                $isi = [];

                                foreach ($pemegang as $id_toko => $daftar) {
                                    foreach ($daftar as $o) {
                                        $isi[(int) $id_toko][] = [(int) $o['id'], (string) $o['nama'], (string) $o['level']];
                                    }
                                }
                                ?>
                                <label class="form-label small fw-bold mt-2" for="ct-user">Dikerjakan oleh <span class="text-danger" id="ct-user-wajib">*</span></label>
                                <select class="form-select form-select-sm" id="ct-user" name="user_id"
                                    data-pemegang="<?= esc(json_encode($isi), 'attr') ?>">
                                    <option value="">Memuat…</option>
                                </select>
                                <div class="form-text small mt-1" id="ct-user-kosong" hidden>
                                    Toko ini belum punya pemegang. Delegasikan orangnya dulu di tab Delegasi toko.
                                </div>
                            <?php endif; ?>

                            <label class="form-label small fw-bold mt-2" for="ct-catatan">Catatan <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-sm" id="ct-catatan" name="catatan" rows="3"
                                maxlength="200" required placeholder="mis. sudah balas 12 chat pembeli dan rekap pesanan jam 10.00"></textarea>

                            <?php if ($level === 'cs') : ?>
                                <div class="form-text small mt-1">
                                    Catatan dari kamu terhitung tugas mandiri: langsung masuk daftar &quot;Sudah dikerjakan&quot;.
                                </div>
                            <?php else : ?>
                                <div class="form-text small mt-1">
                                    Catatan dari Admin/Superadmin masuk daftar &quot;Belum dikerjakan&quot; sampai CS menuliskan hasilnya.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-plus me-1"></i> Simpan</button>
                        </div>
                    <?= form_close() ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="modalSelesai" tabindex="-1" aria-hidden="true"
        data-base="<?= esc(site_url('admin/checklist/catatan-selesai')) ?>">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="" id="formSelesai">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_hasil" id="sl-hasil" value="0">
                    <div class="modal-header">
                        <h5 class="modal-title">Selesaikan catatan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="ckl-selesai-judul" id="sl-judul"></div>

                        <label class="form-label small fw-bold mt-2" for="sl-catatan">Catatan kerja <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="sl-catatan" name="catatan" rows="3"
                            maxlength="500" required placeholder="mis. sudah dicek satu-satu, stok aman, tidak ada komplain"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-circle-check me-1"></i> Tandai selesai</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var form = document.querySelector('#modalCatatan form');

        if (! form) {
            return;
        }

        var toko = document.getElementById('ct-toko');
        var user = document.getElementById('ct-user');

        // tombol simpan dimatikan begitu form dikirim: dua klik beruntun pernah
        // melahirkan dua catatan yang sama persis dalam enam detik
        form.addEventListener('submit', function () {
            var tombol = form.querySelector('button[type="submit"]');

            if (tombol) {
                tombol.disabled = true;
            }
        });

        if (! user) {
            return;
        }

        // daftar pemegang tiap toko sudah ikut dibawa di atribut select, jadi
        // berpindah toko cukup mengisi ulang dropdownnya tanpa request tambahan
        var peta = {};

        try {
            peta = JSON.parse(user.getAttribute('data-pemegang') || '{}');
        } catch (e) {
            peta = {};
        }

        var kosong = document.getElementById('ct-user-kosong');
        var wajib = document.getElementById('ct-user-wajib');

        var isiUser = function () {
            var punya = peta[toko ? toko.value : ''] || [];

            user.innerHTML = '';

            if (punya.length === 0) {
                user.appendChild(new Option('Belum ada pemegang di toko ini', ''));
                user.disabled = true;
                user.removeAttribute('required');
                wajib.hidden = true;
                kosong.hidden = false;

                return;
            }

            user.disabled = false;
            user.setAttribute('required', 'required');
            user.appendChild(new Option('Pilih orang', ''));

            punya.forEach(function (o) {
                user.appendChild(new Option(o[1] + ' (' + o[2] + ')', o[0]));
            });

            wajib.hidden = false;
            kosong.hidden = true;
        };

        if (toko) {
            toko.addEventListener('change', isiUser);
        }

        // popus tidak pernah di-reset saat ditutup, jadi daftarnya diisi ulang
        // tiap kali dibuka — kalau tidak, pilihan toko terakhir tertinggal di situ
        document.getElementById('modalCatatan').addEventListener('show.bs.modal', isiUser);

        isiUser();
    }());

    (function () {
        var modal = document.getElementById('modalSelesai');

        if (! modal) {
            return;
        }

        // tokonya tidak ditanya lagi di sini: catatannya sudah tahu milik toko mana,
        // yang dibutuhkan tinggal apa yang sudah dikerjakan
        modal.addEventListener('show.bs.modal', function (event) {
            var pemicu = event.relatedTarget;

            if (! pemicu) {
                return;
            }

            var run = pemicu.getAttribute('data-run') || '0';

            modal.querySelector('#formSelesai').action = modal.getAttribute('data-base') + '/' + run;
            modal.querySelector('#sl-hasil').value     = pemicu.getAttribute('data-hasil') || '0';
            modal.querySelector('#sl-judul').textContent = pemicu.getAttribute('data-judul') || '';
            modal.querySelector('#sl-catatan').value   = '';
        });
    }());
    </script>

</div>

<?= $this->endSection() ?>
