<?php
/**
 * Variabel di bawah ini diisi dari Notifikasi::index() lewat view('notifikasi', $data).
 * CodeIgniter memanggil extract() di dalam system/View/View.php, jadi analyzers tidak
 * bisa menelusuri asal variabelnya; blok @var ini hanya memberi tahu Intelephense.
 *
 * @var string                                             $title         Judul halaman
 * @var list<array{id: int, pesan: string, juragan: ?string, seri: ?string, dibaca: bool, prioritas: bool, waktu: string, tautan: ?string}> $daftar  Baris notifikasi per halaman
 * @var string                                             $tab           Tab aktif: 'belum' atau 'semua'
 * @var int                                                $jumlah_belum  Jumlah notifikasi belum dibaca
 * @var int                                                $jumlah_semua  Total seluruh notifikasi
 * @var int                                                $halaman       Nomor halaman saat ini
 * @var int                                                $halaman_akhir Total halaman
 * @var bool                                               $boleh_sapu    Akun ini boleh membuka pengaturan kebersihan
 */
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
/* Notifikasi memakai dasar kartu yang sama dengan panel lonceng: garis tipis,
   sudut 12px, bayangan lembut. */
.notif-halaman .card {
    border: 1px solid #eaecf0 !important;
    border-radius: 12px !important;
    /* Tanpa ini, baris kepala & pager di dalamnya menutupi sudut kartu yang bulat. */
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(16, 24, 40, 0.05) !important;
}

.notif-kepala {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    padding: 0.7rem 1rem;
    border-bottom: 1px solid #eaecf0;
    background: #ffffff;
}

.notif-tab {
    display: flex;
    gap: 0.35rem;
}

.notif-tab a {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.8rem;
    border: 1px solid #eaecf0;
    border-radius: 9999px;
    background: #ffffff;
    color: #475467;
    font-size: 0.84rem;
    font-weight: 600;
    text-decoration: none;
}

.notif-tab a:hover {
    border-color: #d0d5dd;
    color: #101828;
}

.notif-tab a.aktif {
    background: #101828;
    border-color: #101828;
    color: #ffffff;
}

.notif-baris {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #f2f4f7;
}

.notif-baris:last-child {
    border-bottom: 0;
}

.notif-baris__isi {
    flex: 1 1 auto;
    min-width: 0;
}

.notif-baris__pesan {
    color: #344054;
    font-size: 0.875rem;
    line-height: 1.45;
}

.notif-baris__pesan a {
    color: #344054;
    text-decoration: none;
}

.notif-baris__pesan a:hover {
    color: #101828;
    text-decoration: underline;
}

.notif-baris__meta {
    margin-top: 0.15rem;
    color: #98a2b3;
    font-size: 0.75rem;
}

.notif-baris--baru {
    background: #ffffff;
}

.notif-baris--baru .notif-baris__pesan {
    color: #101828;
    font-weight: 600;
}

.notif-baris__titik {
    flex: 0 0 auto;
    width: 8px;
    height: 8px;
    margin-top: 0.45rem;
    border-radius: 9999px;
    background: #2563eb;
}

/* baris prioritas ditonjolkan dengan garis kiri merah + badge, selain urutan
   teratas (NotifikasiModel::ambil mengurutkan prioritas lebih dulu) */
.notif-baris--prioritas {
    border-left: 3px solid #d92d20;
    background: #fef8f8;
}

.notif-badge-prioritas {
    display: inline-block;
    margin-right: 0.4rem;
    padding: 0.05rem 0.5rem;
    border-radius: 9999px;
    background: #d92d20;
    color: #ffffff;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    vertical-align: middle;
}

.notif-baris__aksi {
    flex: 0 0 auto;
}

.notif-kosong-halaman {
    padding: 3rem 1rem;
    color: #98a2b3;
    text-align: center;
}

.notif-buntut {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.7rem 1rem;
    border-top: 1px solid #eaecf0;
    background: #f9fafb;
    color: #475467;
    font-size: 0.8rem;
}

@media (max-width: 575.98px) {
    .notif-baris {
        padding: 0.75rem 0.8rem;
        gap: 0.5rem;
    }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php if (in_array(session()->get('level'), ['admin', 'superadmin'], true)) : ?>
    <?= $this->include('admin/navbar') ?>
<?php else : ?>
    <?= $this->include('user/navbar') ?>
<?php endif; ?>

<div class="container-xxl notif-halaman">
    <h1 class="h3 mb-3">Notifikasi</h1>

    <div class="card">
        <div class="notif-kepala">
            <div class="notif-tab">
                <a class="<?= $tab === 'belum' ? 'aktif' : '' ?>" href="<?= site_url(base_user() . '/notifikasi/belum') ?>">
                    Belum dibaca
                    <span class="badge rounded-pill bg-light text-dark border-0"><?= (int) $jumlah_belum ?></span>
                </a>
                <a class="<?= $tab === 'semua' ? 'aktif' : '' ?>" href="<?= site_url(base_user() . '/notifikasi/semua') ?>">
                    Semua
                    <span class="badge rounded-pill bg-light text-dark border-0"><?= (int) $jumlah_semua ?></span>
                </a>
            </div>

            <?php if ($jumlah_belum > 0) : ?>
                <button type="button" class="btn btn-sm btn-outline-secondary notif-tandai-semua" title="Tandai semua dibaca" aria-label="Tandai semua dibaca">
                    <i class="fal fa-check-double"></i> <span class="d-none d-lg-inline">Tandai semua dibaca</span>
                </button>
            <?php endif; ?>

            <?php if ($boleh_sapu) : ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/settings/notifikasi') ?>"
                    title="Atur hapus manual dan hapus otomatis">
                    <i class="fal fa-broom"></i> Pengaturan hapus
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($daftar)) : ?>
            <div class="notif-kosong-halaman">
                <i class="fal fa-bell-slash fa-3x mb-2 d-block"></i>
                <?= $tab === 'belum' ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi.' ?>
            </div>
        <?php else : ?>
            <?php foreach ($daftar as $item) : ?>
                <div class="notif-baris<?= $item['dibaca'] ? '' : ' notif-baris--baru' ?><?= ! empty($item['prioritas']) ? ' notif-baris--prioritas' : '' ?>">
                    <?php if (! $item['dibaca']) : ?>
                        <span class="notif-baris__titik" aria-hidden="true"></span>
                    <?php endif; ?>

                    <div class="notif-baris__isi">
                        <div class="notif-baris__pesan">
                            <?php if (! empty($item['prioritas'])) : ?>
                                <span class="notif-badge-prioritas">Prioritas</span>
                            <?php endif; ?>
                            <?php /* teks dari config/JuraganConfig, nomor nota ikut ditautkan */ ?>
                            <?php if ($item['tautan'] !== '') : ?>
                                <a href="<?= esc($item['tautan']) ?>"><?= $item['pesan'] ?></a>
                            <?php else : ?>
                                <?= $item['pesan'] ?>
                            <?php endif; ?>
                        </div>
                        <div class="notif-baris__meta">
                            <?= esc($item['waktu']) ?>
                            <?php if (! empty($item['juragan'])) : ?>
                                &middot; <?= esc($item['juragan']) ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="notif-baris__aksi">
                        <?php if (! $item['dibaca']) : ?>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none notif-tandai" data-id="<?= (int) $item['id'] ?>" title="Tandai dibaca" aria-label="Tandai dibaca">
                                <i class="fal fa-check"></i> <span class="d-none d-lg-inline">Tandai dibaca</span>
                            </button>
                        <?php else : ?>
                            <?php /* membuang notifikasi terbaca bukan hak CS lagi: hanya sisi
                               manajemen yang boleh satu-satu; kotak CS dibersihkan lewat
                               Pengaturan Notifikasi atau sapuan terjadwal */ ?>
                            <?php if ($boleh_sapu) : ?>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none text-muted notif-hapus" data-id="<?= (int) $item['id'] ?>" title="Hapus notifikasi ini">
                                    <i class="fal fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($halaman_akhir > 1) : ?>
            <?php
            $akhir = (int) $halaman_akhir;

            // tab yang sedang dibuka harus ikut terbawa, kalau tidak nomor
            // halaman diam-diam kembali ke daftar "belum dibaca"
            $tautanHalaman = static function (int $n) use ($tab): string {
                return site_url(base_user() . '/notifikasi/' . $tab) . ($n > 1 ? '?page=' . $n : '');
            };

            // Jendela 5 nomor di sekitar halaman aktif, plus halaman pertama/terakhir
            // dengan penanda "..." supaya halaman terjauh tetap bisa diklik.
            $dari   = max(1, min($halaman - 2, $akhir - 4));
            $sampai = min($akhir, $dari + 4);

            $nomorHalaman = [];
            if ($dari > 1) {
                $nomorHalaman[] = 1;
                if ($dari > 2) {
                    $nomorHalaman[] = null;
                }
            }
            for ($n = $dari; $n <= $sampai; $n++) {
                $nomorHalaman[] = $n;
            }
            if ($sampai < $akhir) {
                if ($sampai < $akhir - 1) {
                    $nomorHalaman[] = null;
                }
                $nomorHalaman[] = $akhir;
            }
            ?>
            <div class="notif-buntut crm-pager">
                <div class="small">Halaman <?= (int) $halaman ?> dari <?= $akhir ?></div>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($halaman > 1) : ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $tautanHalaman($halaman - 1) ?>">&laquo; Sebelumnya</a>
                        </li>
                    <?php else : ?>
                        <li class="page-item disabled">
                            <span class="page-link">&laquo; Sebelumnya</span>
                        </li>
                    <?php endif; ?>

                    <?php foreach ($nomorHalaman as $n) : ?>
                        <?php if ($n === null) : ?>
                            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                        <?php elseif ($n === $halaman) : ?>
                            <li class="page-item active" aria-current="page"><span class="page-link"><?= $n ?></span></li>
                        <?php else : ?>
                            <li class="page-item"><a class="page-link" href="<?= $tautanHalaman($n) ?>"><?= $n ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($halaman < $akhir) : ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $tautanHalaman($halaman + 1) ?>">Berikutnya &raquo;</a>
                        </li>
                    <?php else : ?>
                        <li class="page-item disabled">
                            <span class="page-link">Berikutnya &raquo;</span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
    $(function() {
        var markUrl = '<?= site_url('api/notifikasi/mark') ?>';
        var markAllUrl = '<?= site_url('api/notifikasi/mark_all') ?>';
        var hapusUrl = '<?= site_url('api/notifikasi/hapus') ?>';

        $(document).on('click', '.notif-tandai', function() {
            var tombol = $(this);
            tombol.prop('disabled', true);

            $.post(markUrl, { action: 'sudahBaca', id: tombol.data('id') }, function() {
                window.location.reload();
            });
        });

        $('.notif-tandai-semua').on('click', function() {
            var tombol = $(this);
            tombol.prop('disabled', true);

            $.post(markAllUrl, {}, function() {
                window.location.reload();
            });
        });

        $(document).on('click', '.notif-hapus', function() {
            if (! window.confirm('Hapus notifikasi ini dari kotak Anda?')) {
                return;
            }

            $.post(hapusUrl, { id: $(this).data('id') }, function() {
                window.location.reload();
            });
        });
    });
</script>
<?= $this->endSection() ?>
