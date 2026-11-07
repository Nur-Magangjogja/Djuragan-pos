<?php

/**
 * JS bersama untuk halaman admin: pemanggil notifikasi. Dulu disalin mentah-mentah
 * di setiap view, lalu di-include tiga halaman saja sehingga lonceng mati di
 * sisanya; sekarang dimuat dari template/main.php supaya notifikasi bisa dibuka
 * dari halaman mana pun.
 */
helper('fungsi'); // base_user() dipakai di bawah, dan helper tidak di-autoload

$current_user_id = (int) session()->get('id');

// halaman tanpa sesi login tidak punya kotak notifikasi
if ($current_user_id === 0) {
    return;
}

$link_api_notif   = site_url('api/notifikasi/');

$js = <<< JS
    $(function() {
    	'use strict';

        // notifikasi

    	function getNotif(page = 1, dibaca = 0){
    		var id = {$current_user_id};
    		$.getJSON('{$link_api_notif}' + 'get', { id: id, page: page, dibaca: dibaca }, function(b){
    			var a=[],
    				data = b.results;

    			if (b.count > 0) {
    				$('.btnSettingNotif').show();
    				a.push('');

    				$.each(data,function(c,b){
    					var badge = b.prioritas
    						? '<span class="badge rounded-pill" style="background:#d92d20;color:#fff;font-size:.68rem;font-weight:700;margin-right:.4rem">Prioritas</span>'
    						: '';
    					a.push(`<div class="list-group-item list-group-item-action">
    						<div class="d-flex">
    							<div class="me-auto">
    								<div class="text-muted small">`+b.created_at +`</div>
    								<a class="d-block text-decoration-none" href="`+(b.tautan || '#')+`">`+ badge + b.notif +`</a>
    							</div>
    							<div class="d-flex justify-content-right flex-column actionNotif">
    								<button data-id="`+b.id+`" class="markAs border-0 bg-transparent text-primary small" type="button"><i class="fal fa-circle"></i></button>
    							</div>
    						</div>
    					</div>`);
    				}),
    				$(a.join('')).appendTo('#notifDisini');
    				/* tidak ada tombol "lanjut ..." lagi: panel lonceng memang hanya
    					rambu, halaman penuh notifikasi sudah menunggu di tautan
    					"Lihat semua notifikasi" di kaki panel */
    			}
    			else {
    				$('.btnSettingNotif').hide();

    				$('<div class="notif-kosong"><i class="fal fa-bell-slash fa-2x"></i></div>').appendTo('#notifDisini');
    			}
    		});
    	}

        var badgeMenu = document.querySelectorAll('[data-badge-url]');
        if (badgeMenu.length) {
            function renderBadge(anchor, jumlah) {
                var badge = anchor.querySelector('.badge-live');

                if (jumlah === 0) {
                    if (badge) badge.remove();
                    return;
                }

                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'badge rounded-pill bg-danger ms-auto badge-live';
                    anchor.appendChild(badge);
                }

                badge.textContent = jumlah > 99 ? '99+' : String(jumlah);
                badge.title = jumlah + ' ' + (anchor.getAttribute('data-badge-unit') || '');
            }

            // satu poller untuk semua badge angka di sidebar (Reminder, Tugas CS):
            // yang membedakan hanya data-badge-url di tiap item, jadi menambah menu
            // berbadge tidak pernah menambah skrip baru lagi
            function pollBadges() {
                Array.prototype.forEach.call(badgeMenu, function(anchor) {
                    fetch(anchor.getAttribute('data-badge-url'), { credentials: 'same-origin' })
                        .then(function(r) {
                            if (!r.ok) throw new Error('HTTP ' + r.status);
                            return r.json();
                        })
                        .then(function(res) {
                            if (res && res.status === 'success') renderBadge(anchor, Number(res.count || 0));
                        })
                        .catch(function() { /* jaringan sesaat: biarkan angka terakhir */ });
                });
            }

            pollBadges();
            setInterval(pollBadges, 5000);
        }

        // Tombol "kosongkan saringan": field filter dikosongkan, lalu daftar
        // hasil diambil ulang lewat fetch dan ditukar di tempat — halaman TIDAK
        // dimuat ulang, jadi popus, guliran, dan posisi tetap utuh. URL ikut
        // diperbarui (history.replaceState) sehingga kalau di-refresh saringan
        // lama tidak muncul lagi. Halaman yang tidak punya wadah hasil
        // (data-saring-wadah) atau saat fetch gagal jatuh ke kirim form (muat
        // penuh) supaya datanya tetap benar. Satu pendengar untuk semua halaman;
        // yang dipertahankan (mis. tab, batas) diambil dari data-saring-reset.

        document.addEventListener('click', function (e) {
            var tombol = e.target.closest ? e.target.closest('[data-saring-reset]') : null;

            if (! tombol) {
                return;
            }

            e.preventDefault();

            var form = tombol.closest('form');

            if (! form) {
                return;
            }

            var simpan = (tombol.getAttribute('data-saring-reset') || '').split(',').map(function (n) {
                return n.trim();
            });

            form.querySelectorAll('input, select, textarea').forEach(function (isian) {
                // field yang tidak dikirim form memang tidak boleh disentuh
                if (isian.disabled || simpan.indexOf(isian.name) > -1) {
                    return;
                }

                if (isian.type === 'checkbox' || isian.type === 'radio') {
                    isian.checked = isian.defaultChecked;

                    return;
                }

                if (isian.tagName === 'SELECT') {
                    // pilihan kosong ("Semua toko") yang dipilih; kalau tidak ada,
                    // kembali ke pilihan pertama
                    var adaKosong = Array.prototype.some.call(isian.options, function (o) {
                        return o.value === '';
                    });

                    if (adaKosong) {
                        isian.value = '';
                    } else if (isian.options.length) {
                        isian.selectedIndex = 0;
                    }

                    return;
                }

                isian.value = '';
            });

            // angka "berapa saringan yang aktif" ikut hilang karena saringannya
            // sudah dikosongkan
            document.querySelectorAll('.em-saring-angka, .wa-saring-jumlah, .tugas-saring-jumlah').forEach(function (angka) {
                angka.remove();
            });

            var wadah = document.querySelector('[data-saring-wadah]');

            if (! wadah) {
                // halaman tanpa wadah hasil: muat penuh supaya datanya ikut pulih
                form.submit();

                return;
            }

            // URL bersih: hanya nama yang dipertahankan (tab/batas/saya/kerja) yang
            // ikut terbawa; nilainya diambil dari field form, atau dari URL sekarang
            // kalau field itu tidak ada di form (mis. batas). Semua saringan lain
            // sudah dikosongkan, jadi hasilnya daftar penuh seperti awal.
            var sekarang = new URLSearchParams(window.location.search);
            var q = new URLSearchParams();

            simpan.forEach(function (nama) {
                if (nama === '') {
                    return;
                }

                var field = form.querySelector('[name="' + nama + '"]');
                var nilai = field ? field.value : sekarang.get(nama);

                if (nilai !== null && nilai !== undefined && nilai !== '') {
                    q.set(nama, nilai);
                }
            });

            var asal = form.getAttribute('action') || window.location.pathname;
            var tujuan = q.toString() ? asal + '?' + q.toString() : asal;

            fetch(tujuan, { credentials: 'same-origin' })
                .then(function (r) {
                    if (! r.ok) {
                        throw new Error('HTTP ' + r.status);
                    }

                    return r.text();
                })
                .then(function (html) {
                    var baru = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-saring-wadah]');

                    if (! baru) {
                        throw new Error('wadah hilang');
                    }

                    wadah.replaceWith(document.importNode(baru, true));
                    window.history.replaceState({}, '', tujuan);

                    // beri tahu halaman supaya aksi per-baris diikat ulang bila perlu
                    document.dispatchEvent(new CustomEvent('saring:segar'));
                })
                .catch(function () {
                    // gagal menukar di tempat: muat penuh supaya data tetap benar
                    window.location.assign(tujuan);
                });
        });

    	var notifikasi = document.getElementById('notifikasi');
        if (! notifikasi) {
            return; // halaman ini tidak punya lonceng, tidak ada yang perlu diikat
        }

        // lonceng tidak lagi menarik offcanvas dari samping: panelnya melayang
        function tampilNotif() {
            if (notifikasi.classList.contains('tampil')) {
                return;
            }

            notifikasi.classList.add('tampil');
            notifikasi.setAttribute('aria-hidden', 'false');
            $('#notifDisini').empty();
            getNotif();
        }

        function sembunyiNotif() {
            notifikasi.classList.remove('tampil');
            notifikasi.setAttribute('aria-hidden', 'true');
        }

        $(document).on('click', '[data-notif-toggle]', function (e) {
            e.preventDefault();

            if (notifikasi.classList.contains('tampil')) {
                sembunyiNotif();
            } else {
                tampilNotif();
            }
        });

        // klik di luar panel atau Esc menutupnya, seperti dropdown biasa
        $(document).on('click', function (e) {
            if ($(e.target).closest('#notifikasi, [data-notif-toggle]').length === 0) {
                sembunyiNotif();
            }
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                sembunyiNotif();
            }
        });

    	counter_notif();

    	setInterval(function(){
    		counter_notif();
    	}, 10000);

    	function counter_notif() {
    		var id = {$current_user_id};
    		$.getJSON('{$link_api_notif}' + 'get', { id: id }, function(b){
    			$('.counter').text(b.count_text);
    		});
    	}

    	$(document).on('click', '.actionNotif .markAs', function() { // markAs
    		var action = '';

    		if ($(this).hasClass( "text-primary" )) {
    			$(this).removeClass('text-primary').addClass('text-muted');
    			action = 'sudahBaca';
    		}
    		else {
    			$(this).removeClass('text-muted').addClass('text-primary');
    			action = 'belumBaca';
    		}

    		$.post( '{$link_api_notif}' + 'mark', { action: action, id: $(this).data('id') });
    	});

    	$('.tandaiSemuaTerbaca').on('click',function(){
    		var id = {$current_user_id};

    		$.post( '{$link_api_notif}' + 'mark_all', { id: id }, function() {
    			//
    			$('.actionNotif .markAs').removeClass('text-primary').addClass('text-muted');
    		});
    	});

    });
    JS;

echo '<script>' . (new Tholu\Packer\Packer($js, 'Normal', true, false, true))->pack() . '</script>';
