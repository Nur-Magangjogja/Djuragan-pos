<?php

/**
 * Daftar Cek Email: catatan hasil melihat kotak masuk, per toko.
 *
 * Semua variabel di bawah disuntik Admin\Email::index() lewat view() (extract),
 * jadi analyzer tidak bisa menelusurinya dari file ini. Blok @var di bawah hanya
 * memberi tahunya ke IntelliJ/Intelephense, bukan kode yang dijalankan.
 *
 * @var string $title      judul halaman
 * @var array<string, mixed> $s   saringan yang sudah dibersihkan controller
 * @var list<array<string, mixed>> $baris
 * @var int    $total      jumlah baris sesuai saringan
 * @var int    $page       halaman aktif (berbasis satu)
 * @var int    $batas      baris per halaman
 * @var array<string, string>    $tab   kunci => label
 * @var array<string, int>       $angka kunci => jumlah baris
 * @var array<int, string>       $toko
 * @var array<int, string>       $email  alamat yang boleh dipakai akun ini
 * @var list<array<string, mixed>> $daftar baris crm_email (baris tabel pertama)
 * @var array<int, list<array>>  $pegang id_email => pemegang
 * @var array<int, string>       $staf   id => nama CS aktif
 * @var array<int, array>        $hitung id_email => terbuka/selesai (0 = tanpa alamat)
 * @var bool                     $kelola akun ini Admin/Superadmin?
 * @var int    $milik      id akun yang sedang login
 * @var string $kabar      pesan sukses flash (string kosong kalau tidak ada)
 * @var string $galat      pesan gagal flash (string kosong kalau tidak ada)
 */

$level    = (string) session()->get('level');
$hari_ini = date('Y-m-d');

$tanya = static function (array $ubah): string {
    $q = array_merge($_GET, $ubah);

    if (! array_key_exists('halaman', $ubah)) {
        unset($q['halaman']);
    }

    foreach (['tab', 'toko', 'email', 'cari', 'saya', 'batas', 'halaman', 'bagian'] as $k) {
        if (isset($q[$k]) && $q[$k] === '') {
            unset($q[$k]);
        }
    }

    return site_url('admin/tugas/email') . '?' . http_build_query($q);
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .em-halaman { max-width: 1080px; }
    .em-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .em-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }

    .em-buat { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .55rem; padding: .9rem 1rem; margin-bottom: 1rem;
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem; }
    .em-buat .lapangan { display: flex; flex-direction: column; gap: .2rem; }
    .em-buat .lebar { flex: 1; min-width: 260px; }
    .em-buat label, .em-saring label { font-size: .7rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }

    .em-tab { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1rem; }
    .em-tab a {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .34rem .85rem; border-radius: 999px; font-size: .8rem; font-weight: 600;
        background: #f4f6f9; color: #475569; text-decoration: none; border: 1px solid #e2e8f0;
    }
    .em-tab a.aktif { background: #101828; border-color: #101828; color: #fff; }
    .em-tab a span { font-weight: 700; }

    .em-saring { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .55rem; margin-bottom: 1rem; }
    .em-saring .lapangan { display: flex; flex-direction: column; gap: .2rem; }
    .em-saring .form-control, .em-saring .form-select { max-width: 190px; }

    .em-kartu { background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12); overflow: hidden; }
    .table.em-daftar { margin: 0; }
    .em-daftar th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; background: #f9fafb; }
    .em-daftar td { vertical-align: middle; font-size: .86rem; }
    .em-daftar thead th:first-child { width: 46%; }
    .em-catatan { color: #0f172a; font-size: .88rem; }
    .em-sub { font-size: .76rem; color: #64748b; display: flex; flex-wrap: wrap; gap: .2rem .6rem; margin-top: .12rem; }
    .em-kosong { padding: 2.6rem 1rem; text-align: center; color: #94a3b8; }
    .em-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }

    .pil-status { display: inline-block; padding: .18rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
    .s-queued { background: #eef1f6; color: #475569; }
    .s-assigned { background: #e6f4fb; color: #0369a1; }
    .s-in_progress { background: #fef3e2; color: #b45309; }
    .s-waiting { background: #f3e8ff; color: #6b21a8; }
    .s-completed { background: #e7f6ec; color: #14663a; }
    .s-cancelled { background: #fdf1f0; color: #b81c16; }
    .pil-orang { display: inline-block; padding: .16rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: #e6f4fb; color: #0369a1; }
    .pil-buat { background: #f4f6f9; color: #475569; }

    .em-teenggat { font-size: .76rem; white-space: nowrap; }
    .lewat { color: #b81c16; font-weight: 700; }
    .em-aksi { display: inline-flex; gap: .3rem; align-items: center; justify-content: flex-end; }
    .em-form-lepas { margin-top: .4rem; }
    .em-pager { padding: .8rem 1rem; background: #fff; border-top: 1px solid #eaecf0; }

    .em-email-kartu { margin-bottom: 1rem; }
    .em-email-kartu .em-daftar thead th:first-child { width: 28%; }
    .em-email-kartu .em-daftar thead th:nth-child(2) { width: 26%; }
    .em-email { font-weight: 700; color: #0f172a; }
    .em-mati .em-email { color: #94a3b8; text-decoration: line-through; }
    .em-pegang { display: flex; flex-wrap: wrap; gap: .3rem; align-items: center; }
    .em-angka { white-space: nowrap; }
    .em-angka a { text-decoration: none; }
    .em-kosong-baris { padding: 1.4rem 1rem; color: #94a3b8; font-size: .86rem; }
    .em-pil-aktif { font-size: .84rem; color: #475569; margin: 0 0 .5rem; }
    .em-tab-layar { margin-bottom: .9rem; }
    .em-tab-layar a { padding: .42rem 1.05rem; font-size: .84rem; }
    .em-hint { padding: .85rem 1rem; margin-bottom: 1rem; background: #fff; border: 1px dashed #cbd5e1;
        border-radius: 1.15rem; color: #64748b; font-size: .86rem; }
    .pil-orang { display: inline-flex; align-items: center; gap: .3rem; padding: .16rem .3rem .16rem .55rem;
        border-radius: 999px; font-size: .72rem; font-weight: 700; background: #e6f4fb; color: #0369a1; }
    .pil-orang form { display: inline; }
    .pil-orang button { border: 0; background: none; padding: 0 .2rem; color: inherit; font-size: .85rem; line-height: 1; cursor: pointer; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl em-halaman">

    <div class="em-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><i class="fal fa-envelope me-1 text-brand-500"></i> <?= esc($title) ?></h1>
            <p><?= $bagian === 'email'
                ? 'Satu baris = satu alamat email dan orang yang memegangnya. Siapa yang memegang alamatnya, dia yang dapat tugas cek email di alamat itu.'
                : 'Catatan ditugaskan ke salah satu alamat di tab Daftar Email — siapa yang memegang alamatnya, dia yang dapat tugasnya.' ?></p>
        </div>
        <?php if ($kelola && $bagian === 'email') : ?>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalEmailBaru"
                data-id="" data-email="" data-label="" data-catatan="">
                <i class="fal fa-plus"></i> Tambah Email
            </button>
        <?php endif; ?>
    </div>

    <?php if ($kabar !== '') : ?><div class="alert alert-success py-2" data-flash><?= esc($kabar) ?></div><?php endif; ?>
    <?php if ($galat !== '') : ?><div class="alert alert-danger py-2" data-flash><?= esc($galat) ?></div><?php endif; ?>

    <div class="em-tab em-tab-layar">
        <a class="<?= $bagian === 'catatan' ? 'aktif' : '' ?>" href="<?= esc($tanya(['bagian' => ''])) ?>">
            <i class="fal fa-inbox"></i> Catatan
        </a>
        <a class="<?= $bagian === 'email' ? 'aktif' : '' ?>" href="<?= esc($tanya(['bagian' => 'email'])) ?>">
            <i class="fal fa-at"></i> Daftar Email
        </a>
    </div>

    <?php if ($bagian === 'catatan' && $toko !== [] && $email !== []) : ?>
        <?= form_open('admin/tugas/email/simpan', ['class' => 'em-buat']) ?>
            <div class="lapangan">
                <label for="email-pilih">Tugaskan ke email</label>
                <select class="form-select form-select-sm" id="email-pilih" name="email_id" required>
                    <option value="">Pilih email</option>
                    <?php foreach ($email as $id_email => $nama_e) : ?>
                        <option value="<?= (int) $id_email ?>"><?= esc($nama_e) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lebar">
                <label class="form-label small fw-bold mb-1" for="catatan">Catatan</label>
                <textarea class="form-control form-control-sm" id="catatan" name="catatan" rows="2" required
                    maxlength="2000" placeholder="mis. belum ada balasan ukuran dari pelanggan 0812..., nota sudah dikirim kemarin"></textarea>
            </div>
            <div class="lapangan">
                <label for="email-toko">Toko</label>
                <select class="form-select form-select-sm" id="email-toko" name="juragan_id" required>
                    <?php foreach ($toko as $id_toko => $nama_t) : ?>
                        <option value="<?= (int) $id_toko ?>"><?= esc($nama_t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lapangan">
                <label for="email-tenggat">Tenggat</label>
                <input type="date" class="form-control form-control-sm" id="email-tenggat" name="tenggat">
            </div>
            <div class="lapangan">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-sm btn-primary"><i class="fal fa-plus me-1"></i> Simpan catatan</button>
            </div>
        <?= form_close() ?>
    <?php elseif ($bagian === 'catatan' && $toko !== []) : ?>
        <p class="em-hint">
            <?= $kelola
                ? 'Belum ada email di daftar. Tambahkan dulu lewat tab Daftar Email supaya catatan bisa ditugaskan ke satu alamat.'
                : 'Admin belum mendelegasikan alamat email apa pun kepadamu, jadi catatan belum bisa dibuat.' ?>
        </p>
    <?php endif; ?>

    <?php if ($bagian === 'catatan') : ?>
    <div class="em-tab">
        <?php foreach ($tab as $kode => $nama) : ?>
            <a class="<?= $s['tab'] === $kode ? 'aktif' : '' ?>" href="<?= esc($tanya(['tab' => $kode])) ?>">
                <?= esc($nama) ?>
                <?php if (($angka[$kode] ?? 0) > 0) : ?><span><?= (int) $angka[$kode] ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <a class="<?= (int) $s['milik_saya'] === $milik ? 'aktif' : '' ?>"
            href="<?= esc($tanya(['saya' => (int) $s['milik_saya'] === $milik ? '' : '1'])) ?>">
            <i class="fal fa-user"></i> Hanya punyaku
        </a>
    </div>

    <form class="em-saring" method="get" action="<?= esc(site_url('admin/tugas/email')) ?>">
        <input type="hidden" name="tab" value="<?= esc($s['tab']) ?>">
        <input type="hidden" name="saya" value="<?= (int) $s['milik_saya'] === $milik ? '1' : '' ?>">
        <div class="lapangan">
            <label for="cari">Cari</label>
            <input type="search" class="form-control form-control-sm" id="cari" name="cari" value="<?= esc($s['cari']) ?>"
                placeholder="isi catatan">
        </div>
        <?php if ($s['email_get'] !== '') : ?>
            <input type="hidden" name="email" value="<?= esc($s['email_get'], 'attr') ?>">
        <?php endif; ?>
        <?php if (count($toko) > 1) : ?>
            <div class="lapangan">
                <label for="toko">Toko</label>
                <select class="form-select form-select-sm" id="toko" name="toko">
                    <option value="">Semua toko</option>
                    <?php foreach ($toko as $id_toko => $nama_t) : ?>
                        <option value="<?= (int) $id_toko ?>" <?= (int) $s['juragan_id'] === (int) $id_toko ? 'selected' : '' ?>><?= esc($nama_t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
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
            <div class="em-aksi">
                <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-search"></i> Cari</button>
                <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/tugas/email')) ?>">Ulangi</a>
            </div>
        </div>
    </form>

    <?php if ((int) $s['email_id'] > 0) : ?>
        <?php
        $pil_saring = $email[(int) $s['email_id']] ?? '';

        if ($pil_saring === '') {
            foreach ($daftar as $e) {
                if ((int) $e['id_email'] === (int) $s['email_id']) {
                    $pil_saring = (string) $e['email'];
                    break;
                }
            }
        }
        ?>
        <p class="em-pil-aktif">Menampilkan catatan untuk: <strong><?= esc($pil_saring) ?></strong>
            <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/tugas/email')) ?>">lihat semua</a>
        </p>
    <?php endif; ?>
    <div class="em-kartu">
        <?php if ($baris === []) : ?>
            <div class="em-kosong">
                <i class="fal fa-circle-check text-success"></i>
                <?= $s['tab'] === 'belum' ? 'Belum ada catatan cek email yang menunggu.' : 'Kosong di tab ini.' ?>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle em-daftar tabel-rapi">
                    <thead>
                        <tr>
                            <th>Catatan</th>
                            <th>Tenggat</th>
                            <th>Penanggung jawab</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baris as $w) : ?>
                            <?php
                            $id      = (int) $w['id_tugas'];
                            $st      = (string) $w['status'];
                            $punya   = (int) $w['dikerjakan_oleh'] === $milik;
                            $buka    = in_array($st, \App\Models\TugasModel::BELUM_SELESAI, true);
                            $tenggat = (string) ($w['tenggat'] ?? '');
                            $terlambat = $buka && $tenggat !== '' && $tenggat < $hari_ini;
                            ?>
                            <tr>
                                <td>
                                    <div class="em-catatan"><?= nl2br(esc($w['deskripsi'] ?: $w['judul'])) ?></div>
                                    <div class="em-sub">
                                        <span>#<?= $id ?></span>
                                        <?php if (! empty($w['email_alamat'])) : ?>
                                            <span><i class="fal fa-inbox"></i> <?= esc($w['email_label'] ?: $w['email_alamat']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($w['nama_juragan']) : ?><span><i class="fal fa-store"></i> <?= esc($w['nama_juragan']) ?></span><?php endif; ?>
                                        <span class="pil-status s-<?= esc($st) ?>"><?= esc(\App\Models\TugasModel::STATUS[$st] ?? $st) ?></span>
                                        <span class="pil-orang pil-buat">dibuat: <?= esc($w['nama_dibuat'] ?: '?') ?></span>
                                    </div>
                                </td>
                                <td class="em-teenggat <?= $terlambat ? 'lewat' : '' ?>">
                                    <?= $tenggat === '' ? '-' : esc(tanggal_rincian($tenggat)) ?>
                                    <?php if ($terlambat) : ?><i class="fal fa-exclamation-triangle"></i><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($w['nama_dikerjakan']) : ?>
                                        <span class="pil-orang"><i class="fal fa-user-check me-1"></i><?= esc($w['nama_dikerjakan']) ?></span>
                                        <?php if ($w['nama_selesai']) : ?>
                                            <div class="em-sub"><span>selesai: <?= esc($w['nama_selesai']) ?></span></div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="text-muted">belum diambil</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="em-aksi">
                                        <?php if ($st === 'queued') : ?>
                                            <form method="post" action="<?= esc(site_url('admin/tugas/email/ambil/' . $id)) ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">Ambil Catatan</button>
                                            </form>
                                        <?php elseif ($punya && $buka) : ?>
                                            <form method="post" action="<?= esc(site_url('admin/tugas/email/selesai/' . $id)) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="catatan" value="sudah dibaca">
                                                <button type="submit" class="btn btn-sm btn-success rounded-pill">Selesai</button>
                                            </form>
                                            <a class="btn btn-sm btn-link text-decoration-none" data-bs-toggle="collapse"
                                                href="#lepas-<?= $id ?>" role="button">Lepas</a>
                                        <?php else : ?>
                                            <a class="btn btn-sm btn-link text-decoration-none"
                                                href="<?= esc(site_url('admin/tugas/' . $id)) ?>">Riwayat</a>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($punya && $buka) : ?>
                                        <div class="collapse" id="lepas-<?= $id ?>">
                                            <?= form_open('admin/tugas/email/lepas/' . $id, ['class' => 'em-form-lepas']) ?>
                                                <input type="text" class="form-control form-control-sm" name="alasan" maxlength="255"
                                                    placeholder="alasan dilepas (wajib)" required>
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill mt-1">Kembalikan ke daftar</button>
                                            <?= form_close() ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($total > $batas) : ?>
            <div class="em-pager d-flex flex-wrap align-items-center gap-2">
                <span class="small text-muted"><?= (int) $total ?> catatan &middot; halaman <?= (int) $page ?></span>
                <div class="ms-auto">
                    <?= \Config\Services::pager()->makeLinks($page, $batas, $total, 'front_full') ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php else : ?>
    <div class="em-kartu em-email-kartu">
        <div class="table-responsive">
            <table class="table table-hover align-middle em-daftar tabel-rapi">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Dipegang oleh</th>
                        <th>Catatan</th>
                        <?php if ($kelola) : ?>
                            <th>Delegasikan</th>
                            <th class="text-center">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar as $e) : ?>
                        <?php
                        $id_e    = (int) $e['id_email'];
                        $isi_e   = $pegang[$id_e] ?? [];
                        $sudah_e = array_map(static fn (array $p): int => (int) $p['user_id'], $isi_e);
                        $pilih_e = $staf;

                        foreach ($sudah_e as $y) {
                            unset($pilih_e[$y]);
                        }

                        $terbuka = (int) ($hitung[$id_e]['terbuka'] ?? 0);
                        $selesai = (int) ($hitung[$id_e]['selesai'] ?? 0);
                        ?>
                        <tr class="<?= (int) $e['aktif'] === 1 ? '' : 'em-mati' ?>">
                            <td>
                                <div class="em-email"><?= esc($e['email']) ?></div>
                                <div class="em-sub">
                                    <?php if ($e['label'] !== '') : ?><span><i class="fal fa-tag"></i> <?= esc($e['label']) ?></span><?php endif; ?>
                                    <?php if ($e['catatan']) : ?><span><?= esc($e['catatan']) ?></span><?php endif; ?>
                                    <?php if ((int) $e['aktif'] === 0) : ?><span class="pil-status s-cancelled">dimatikan</span><?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($isi_e === []) : ?>
                                    <span class="text-muted small">belum ada yang memegang</span>
                                <?php else : ?>
                                    <div class="em-pegang">
                                        <?php foreach ($isi_e as $p) : ?>
                                            <span class="pil-orang">
                                                <i class="fal fa-user"></i> <?= esc($p['nama_staf']) ?>
                                                <?php if ($kelola) : ?>
                                                    <?= form_open('admin/tugas/email/pegang-copot') ?>
                                                        <input type="hidden" name="id_email" value="<?= $id_e ?>">
                                                        <input type="hidden" name="user_id" value="<?= (int) $p['user_id'] ?>">
                                                        <button type="submit" title="lepas dari <?= esc($p['nama_staf']) ?>" aria-label="lepas">&times;</button>
                                                    <?= form_close() ?>
                                                <?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="em-angka">
                                <?php if ($terbuka === 0 && $selesai === 0) : ?>
                                    <span class="text-muted small">belum ada catatan</span>
                                <?php else : ?>
                                    <a class="pil-status s-queued" href="<?= esc($tanya(['email' => (string) $id_e, 'tab' => 'belum', 'bagian' => ''])) ?>"><?= $terbuka ?> menunggu</a>
                                    <a class="pil-status s-completed" href="<?= esc($tanya(['email' => (string) $id_e, 'tab' => 'sudah', 'bagian' => ''])) ?>"><?= $selesai ?> beres</a>
                                <?php endif; ?>
                            </td>
                            <?php if ($kelola) : ?>
                                <td>
                                    <?php if ($pilih_e !== [] && (int) $e['aktif'] === 1) : ?>
                                        <?= form_open('admin/tugas/email/pegang', ['class' => 'em-pegang']) ?>
                                            <input type="hidden" name="id_email" value="<?= $id_e ?>">
                                            <select class="form-select form-select-sm" name="user_id" style="max-width:160px" required>
                                                <option value="">Pilih CS</option>
                                                <?php foreach ($pilih_e as $uid => $nama) : ?>
                                                    <option value="<?= (int) $uid ?>"><?= esc($nama) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">Tautkan</button>
                                        <?= form_close() ?>
                                    <?php else : ?>
                                        <span class="text-muted small"><?= (int) $e['aktif'] === 1 ? 'semua CS sudah tertaut' : 'email dimatikan' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="em-aksi">
                                        <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-toggle="modal"
                                            data-bs-target="#modalEmailBaru" data-id="<?= $id_e ?>"
                                            data-email="<?= esc($e['email'], 'attr') ?>"
                                            data-label="<?= esc($e['label'], 'attr') ?>"
                                            data-catatan="<?= esc((string) $e['catatan'], 'attr') ?>">Ubah</button>
                                        <?= form_open('admin/tugas/email/daftar-aktif') ?>
                                            <input type="hidden" name="id_email" value="<?= $id_e ?>">
                                            <input type="hidden" name="aktif" value="<?= (int) $e['aktif'] === 1 ? '0' : '1' ?>">
                                            <button type="submit" class="btn btn-sm btn-link text-decoration-none <?= (int) $e['aktif'] === 1 ? 'text-danger' : '' ?>">
                                                <?= (int) $e['aktif'] === 1 ? 'Matikan' : 'Aktifkan' ?>
                                            </button>
                                        <?= form_close() ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($hitung[0]['terbuka'] ?? 0) > 0 || ($hitung[0]['selesai'] ?? 0) > 0) : ?>
                        <tr>
                            <td>
                                <div class="em-email text-muted">catatan tanpa alamat</div>
                                <div class="em-sub"><span>catatan lama sebelum daftar email dipakai</span></div>
                            </td>
                            <td><span class="text-muted small">tidak ditugaskan</span></td>
                            <td class="em-angka">
                                <a class="pil-status s-queued" href="<?= esc($tanya(['email' => '0', 'tab' => 'belum', 'bagian' => ''])) ?>"><?= (int) ($hitung[0]['terbuka'] ?? 0) ?> menunggu</a>
                                <a class="pil-status s-completed" href="<?= esc($tanya(['email' => '0', 'tab' => 'sudah', 'bagian' => ''])) ?>"><?= (int) ($hitung[0]['selesai'] ?? 0) ?> beres</a>
                            </td>
                            <?php if ($kelola) : ?>
                                <td></td>
                                <td class="text-center"><span class="text-muted small">-</span></td>
                            <?php endif; ?>
                        </tr>
                    <?php endif; ?>
                    <?php if ($daftar === []) : ?>
                        <tr>
                            <td colspan="5" class="em-kosong-baris">
                                Belum ada email di daftar. <?= $kelola ? 'Tambahkan lewat tombol di atas, lalu tautkan CS-nya.' : 'Admin belum mendelegasikan alamat apa pun.' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- satu popup untuk tambah dan ubah alamat: isian dan judulnya diambil dari
     tombol yang diklik (lihat bagian js), jadi tidak ada popup per baris -->
<?php if ($kelola && $bagian === 'email') : ?>
<div class="modal fade" id="modalEmailBaru" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <?= form_open('admin/tugas/email/daftar') ?>
                <input type="hidden" name="id_email" id="em-modal-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="em-modal-judul">Tambah Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label small fw-bold" for="em-modal-email">Alamat email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control form-control-sm" id="em-modal-email" name="email" required
                        maxlength="120" placeholder="mis. jahit.toko@gmail.com">
                    <label class="form-label small fw-bold mt-2" for="em-modal-label">Label (opsional)</label>
                    <input type="text" class="form-control form-control-sm" id="em-modal-label" name="label"
                        maxlength="60" placeholder="mis. Email Penjahit">
                    <label class="form-label small fw-bold mt-2" for="em-modal-catatan">Catatan (opsional)</label>
                    <input type="text" class="form-control form-control-sm" id="em-modal-catatan" name="catatan"
                        maxlength="255" placeholder="dipakai untuk apa">
                    <div class="form-text small mt-2">
                        Alamat tidak pernah dihapus — yang sudah tidak dipakai cukup dimatikan supaya catatan lama tetap terbaca.
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

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    var modal = document.getElementById('modalEmailBaru');

    if (! modal) {
        return;
    }

    modal.addEventListener('show.bs.modal', function (event) {
        var pemicu = event.relatedTarget;

        if (! pemicu) {
            return;
        }

        var data = pemicu.dataset;

        document.getElementById('em-modal-id').value = data.id || '';
        document.getElementById('em-modal-email').value = data.email || '';
        document.getElementById('em-modal-label').value = data.label || '';
        document.getElementById('em-modal-catatan').value = data.catatan || '';
        document.getElementById('em-modal-judul').textContent = (data.id ? 'Ubah email' : 'Tambah Email');
    });
});
</script>
<?= $this->endSection() ?>
