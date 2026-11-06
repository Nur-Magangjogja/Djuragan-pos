/*
 * Panel "Ambil dari stok produk" di modal tambah orderan.
 *
 * Ditulis satu kali karena modalnya sama di tiga halaman (Tulis admin, Tulis CS,
 * Sunting admin) dan dipasang lewat stok_panel_form(). Pemilihnya satu combobox:
 * kolom yang bisa diketik untuk menyaring sekaligus membuka daftar varian milik
 * toko + kolam yang sedang dipilih di form. Seluruh isi kolam diambil sekali per
 * kombinasi itu, jadi mengetik hanya menyaring data yang sudah ada di halaman -
 * tidak ada permintaan baru untuk tiap huruf.
 *
 * Daftar yang kosong dijawab apa adanya ("Tidak ada produk"), bukan dengan
 * kotak kosong; toko yang belum dipilih dan kegagalan memuat juga punya
 * kabarnya sendiri.
 *
 * Panel dibuka dari pancingan "Ambil dari stok produk?" dan ditutup dari tombol
 * "Tutup" di kanan kepalanya. "Tutup" menyimpan pilihan yang sudah diambil,
 * sedangkan "Tulis manual" melepas taunya ke stok.
 *
 * Memilih varian mengisi kode, harga, dan ukuran kolom isian lalu menyimpan
 * id_stok-nya di hidden [name="stok_produk"]; halaman pemanggil baris orderan
 * membacanya lewat window.STOK.ambil() dan mengirimnya sebagai produk[k][stok_id].
 * Kalau setelah itu pengguna mengubah kode/harga/ukuran sendiri, taunya ke stok
 * dilepas lagi - barisnya sudah tidak menggambarkan varian itu.
 *
 * Semua teks yang datang dari basis data dirender lewat .text(), tidak pernah
 * lewat HTML, karena kode produk boleh berisi karakter apa pun.
 */
(function ($) {
  "use strict";

  var $panel = $(".stok-panel");

  if ($panel.length === 0) {
    return;
  }

  var $katalog = $panel.find(".stok-katalog"),
    $daftar = $panel.find(".stok-daftar"),
    $wadah = $panel.find(".stok-pilih"),
    $panah = $panel.find(".stok-panah"),
    $id = $panel.find(".stok-id"),
    $catatan = $panel.find(".stok-catatan"),
    $galat = $panel.find(".stok-galat"),
    $acuan = $panel.find(".foto-acuan"),
    $isi = $panel.find(".stok-foto-isi"),
    $batal = $panel.find(".stok-batal"),
    $tutup = $panel.find(".stok-tutup"),
    $pemicu = $(".stok-pemicu"),
    $buka = $(".stok-buka"),
    alamatCari = $panel.data("cari"),
    alamatBaca = $panel.data("baca"),
    token = 0,
    penanda = 0,
    // seluruh varian kolam toko ini, hasil saringannya, dan baris yang sedang
    // disorot keyboard
    daftar = [],
    tampil = [],
    sorot = -1,
    // kunci "juragan|kolam" yang daftarnya sudah dimuat; nama `siap` sengaja
    // tidak dipakai karena bawaanSinkron() sudah punya variabel lokal itu
    dimuat = "",
    kabar = "",
    // tanda dari server: daftar dipotong di batasnya, jadi isinya tidak tampak
    // seperti daftar lengkap padahal masih panjang
    batas = false,
    dipilih = null,
    bawaan = {},
    dimintak = {};

  function rupiah(n) {
    return "Rp " + Number(n || 0).toLocaleString("id-ID");
  }

  /* Teks yang menempel di kolom setelah barang dipilih. */
  function labelPilihan(v) {
    return (
      String(v.kode || "") +
      (v.ukuran ? " ( " + String(v.ukuran).toUpperCase() + " )" : "")
    );
  }

  /* Baris daftar: nama barang sudah dipakai sebagai judul kelompok, jadi di
     sini yang ditulis hanya ukuran, harga, stok kolam, dan keterangannya. */
  function labelBaris(v) {
    return (
      (String(v.ukuran || "").toUpperCase() || "tanpa ukuran") +
      " · " +
      rupiah(v.harga) +
      " · stok " +
      (v.kolam === "sewa" ? "sewa " : "") +
      v.stok +
      (v.keterangan ? " · " + v.keterangan : "")
    );
  }

  function kataSaring() {
    return $.trim($katalog.val()).toLowerCase();
  }

  function saring() {
    var kata = kataSaring();

    tampil =
      kata === ""
        ? daftar.slice()
        : $.grep(daftar, function (v) {
            return (
              String(v.kode || "")
                .toLowerCase()
                .indexOf(kata) !== -1 ||
              String(v.ukuran || "")
                .toLowerCase()
                .indexOf(kata) !== -1 ||
              String(v.keterangan || "")
                .toLowerCase()
                .indexOf(kata) !== -1
            );
          });
  }

  function gambar() {
    $daftar.empty();
    sorot = -1;

    // tiap kali isi berubah, daftar mulai dari atas lagi: kalau tidak, daftar
    // panjang bisa terbuka di tengah-tengah guliran sebelumnya
    $daftar.scrollTop(0);

    if (kabar !== "") {
      $daftar.append(
        $(
          '<div class="stok-kosong" role="option" aria-disabled="true"></div>',
        ).text(kabar),
      );
    } else if (tampil.length === 0) {
      $daftar.append(
        $(
          '<div class="stok-kosong" role="option" aria-disabled="true"></div>',
        ).text("Tidak ada produk yang cocok"),
      );
    } else {
      var grup = {};

      $.each(tampil, function (i, v) {
        var nama = String(v.kode || "").toUpperCase();

        if (!grup[nama]) {
          grup[nama] = $('<div class="stok-grup"></div>')
            .text(nama)
            .appendTo($daftar);
        }

        $('<button type="button" class="stok-item" role="option"></button>')
          .data("stok", v)
          .toggleClass("habis", Number(v.stok) <= 0)
          .append($('<span class="stok-meta"></span>').text(labelBaris(v)))
          .appendTo(grup[nama]);
      });
    }

    // tawaran barang tanpa katalog ditaruh di sini, bukan di atas: ia harus
    // tetap muncul di toko yang kolamnya kosong maupun di saringan yang nol
    tawarkanManual();

    // kabar pemotongan hanya jujur selama saringan tidak mempersempit daftar
    if (batas && tampil.length === daftar.length) {
      $daftar.append(
        $(
          '<div class="stok-kosong" role="option" aria-disabled="true"></div>',
        ).text(
          "Daftar dipotong di " +
            daftar.length +
            " varian pertama — ketik nama barang untuk menjangkau sisanya.",
        ),
      );
    }
  }

  /*
   * Jahitan custom dan barang sementara tidak punya entri katalog, tapi
   * barisnya tetap harus bisa masuk daftar. Jadi nama yang diketik ditawarkan
   * apa adanya sebagai barang tanpa katalog: tidak ada tautan stok, tidak ada
   * pengurangan stok, harga dan jumlahnya diisi manual. Penawarannya juga muncul
   * di toko yang kolamnya masih kosong - di situlah ia justru dibutuhkan.
   */
  function tawarkanManual() {
    var teks = $.trim($katalog.val());

    if (teks === "" || (kabar !== "" && kabar !== "Tidak ada produk")) {
      return;
    }

    var sudah =
      $.grep(daftar, function (v) {
        return String(v.kode || "").toUpperCase() === teks.toUpperCase();
      }).length > 0;

    if (sudah) {
      return;
    }

    $(
      '<button type="button" class="stok-item stok-tiru" role="option"></button>',
    )
      .data("manual", teks.toUpperCase())
      .text('Pakai "' + teks.toUpperCase() + '" sebagai barang tanpa katalog')
      .appendTo($daftar);
  }

  /*
   * Memakai nama bebas: tautan stoknya sengaja dilepas, jadi barisnya murni
   * ketikan orang. Harga tidak ditimpa - memang itu angka yang sedang
   * dinegosiasi - hanya jumlahnya diberi nilai awal supaya subtotal tidak
   * menggantung kosong.
   */
  function isiManual(teks) {
    tutupDaftar();
    kosongkan();

    $("#kode_produk").val(teks);
    $katalog.val(teks);

    var qty = $("#QTY");

    if (qty.val() === "" || Number(qty.val()) < 1) {
      qty.val(1);
    }

    $catatan
      .removeClass("d-none")
      .text(
        "Baris ini tidak ditautkan ke stok produk: " +
          teks +
          ". Harga dan jumlahnya diisi manual, stok tidak berkurang.",
      );
  }

  function bukaDaftar() {
    muat(false);
    saring();
    gambar();
    $daftar.removeClass("d-none");
    $wadah.addClass("terbuka");
    $katalog.attr("aria-expanded", "true");
  }

  function tutupDaftar() {
    $daftar.addClass("d-none").empty();
    $wadah.removeClass("terbuka");
    $katalog.attr("aria-expanded", "false");
    sorot = -1;
  }

  /* Daftar dibuka-tutup dari satu tempat: klik pada kolom atau pada panahnya.
     Kolomnya tidak lagi membuka daftar tiap kali mendapat fokus, karena klik
     pertama selalu memicu focus lalu click berurutan - tanpa penanda itu daftar
     yang baru dibuka akan langsung ditutup lagi oleh klik yang sama. */
  function gantiDaftar() {
    if ($daftar.hasClass("d-none")) {
      bukaDaftar();

      return;
    }

    tutupDaftar();
  }

  /* Sorotan keyboard dijaga tetap terlihat di daftar yang bisa digulir. */
  function geserSorot(jarak) {
    var $baris = $daftar.find(".stok-item");

    if ($baris.length === 0) {
      return;
    }

    sorot = (sorot + jarak + $baris.length) % $baris.length;
    $baris.removeClass("aktif").eq(sorot).addClass("aktif");

    var kotak = $daftar[0],
      baris = $baris.get(sorot);

    if (!baris) {
      return;
    }

    if (baris.offsetTop < kotak.scrollTop) {
      kotak.scrollTop = baris.offsetTop;
    } else if (
      baris.offsetTop + baris.offsetHeight >
      kotak.scrollTop + kotak.clientHeight
    ) {
      kotak.scrollTop =
        baris.offsetTop + baris.offsetHeight - kotak.clientHeight;
    }
  }

  /*
   * Pilihan yang sedang terpakai dilepas: kolom kembali kosong (placeholder
   * "Pilih Barang" yang muncul) dan isian form yang tadi diisi stok dibersihkan.
   */
  function kosongkan() {
    dipilih = null;
    $id.val("");
    $katalog.val("");
    $catatan.addClass("d-none").text("");
    $galat.addClass("d-none").text("");
    $acuan.addClass("d-none");
    $isi.empty();
    $batal.addClass("d-none");
  }

  /*
   * Panel tidak tampil bawaan (d-none dari stok_panel_form). Pancingan
   * "Ambil dari stok produk?" membuka panel, memuat daftarnya kalau belum,
   * dan menyembunyikan dirinya lagi; "Tutup" mengembalikannya ke keadaan lipat
   * tanpa mengubah pilihan yang sudah diambil.
   */
  function bukaPanel() {
    // panel selalu dibuka dengan daftar TERTEKUP: kalau ada sisa keadaan lama
    // (mis. daftar yang masih terbuka dari baris sebelumnya) ditutup dulu, jadi
    // combobox tampil biasa saja sampai kolom/panahnya diklik
    tutupDaftar();
    $panel.removeClass("d-none");
    $pemicu.addClass("d-none");
    $buka.attr("aria-expanded", "true");
    muat(false);
  }

  function tutupPanel() {
    tutupDaftar();
    $panel.addClass("d-none");
    $pemicu.removeClass("d-none");
    $buka.attr("aria-expanded", "false");
  }

  function fotoMuat(id) {
    $acuan.addClass("d-none");
    $isi.empty();

    if (!id) {
      return;
    }

    var jalan = token;

    $.getJSON(alamatBaca + "/" + id).done(function (b) {
      // permintaan lain sudah menyusul: gambar lama jangan ditimpa telat
      if (token !== jalan || Number($id.val()) !== Number(id)) {
        return;
      }

      var foto = (b && b.foto) || [];

      if (foto.length === 0) {
        return;
      }

      $.each(foto, function (i, f) {
        var $kotak = $('<span class="d-inline-flex"></span>'),
          $link = $('<a target="_blank" rel="noopener"></a>').attr(
            "href",
            f.alamat,
          ),
          $img = $('<img alt="" loading="lazy" class="stok-foto-kecil">').attr(
            "src",
            f.alamat_kecil,
          );

        $kotak.append($link.append($img));
        $isi.append($kotak);
      });

      $acuan.removeClass("d-none");
    });
  }

  /*
   * Foto produk ikut muncul di panel "Foto Orderan" begitu barisnya diambil
   * dari stok. Kuncinya kode barang, bukan id_stok: fotonya menempel sekartu,
   * jadi dua ukuran dari kartu yang sama tidak boleh tampil dobel.
   */
  function stokTerpakai() {
    var pakai = {};

    $('#iForm input[name^="produk["][name$="[stok_id]"]').each(function () {
      var id = parseInt(this.value, 10);

      if (!id) {
        return;
      }

      var kode = $.trim(
        $(this).closest("tr").find('input[name$="[kode]"]').val() || "",
      );

      if (kode !== "") {
        pakai[kode] = id;
      }
    });

    return pakai;
  }

  function bawaanSinkron() {
    if (!window.FOTO || typeof window.FOTO.bawaan_sedia !== "function") {
      return;
    }

    var siap = {};

    $.each(stokTerpakai(), function (kode, id) {
      if (bawaan[kode]) {
        siap[kode] = bawaan[kode];

        return;
      }

      if (dimintak[id]) {
        return;
      }

      dimintak[id] = true;

      $.getJSON(alamatBaca + "/" + id)
        .done(function (b) {
          bawaan[String(b.kode || kode)] = (b && b.foto) || [];
          bawaanSinkron();
        })
        .fail(function () {
          // jangan mengunci kode ini selamanya; percobaan berikutnya boleh lewat
          delete dimintak[id];
        });
    });

    window.FOTO.bawaan_sedia(siap);
  }

  /*
   * Menempelkan satu varian ke form. Kode, harga, dan ukuran kolom isian
   * diisi; harga masih boleh dinegosiasi setelah ini tanpa memutus taunya ke
   * stok, tapi mengubah kode atau ukuran memang berarti barisnya tidak lagi
   * menggambarkan varian itu (lihat pemantau di bawah).
   */
  function isi(v) {
    var $ukuran = $("#ukuran"),
      nilai = String(v.ukuran || ""),
      $opsi = $ukuran.find(
        'option[value="' + nilai.replace(/"/g, '\\"') + '"]',
      );

    dipilih = v;
    $id.val(v.id_stok);
    $katalog.val(labelPilihan(v));
    $galat.addClass("d-none").text("");

    $("#kode_produk").val(v.kode);
    $("#harga_satuan").val(v.harga);

    // ukuran stok bisa saja belum terdaftar di dropdown (barang baru), jadi
    // pilihannya ditambahkan dulu supaya nilainya benar-benar kebaca
    if (nilai !== "" && $opsi.length === 0) {
      $("<option>")
        .val(nilai)
        .text(String(v.ukuran).toUpperCase())
        .appendTo($ukuran);
    }

    $ukuran.val(nilai).trigger("change");

    var qty = $("#QTY");

    if (qty.val() === "" || Number(qty.val()) < 1) {
      qty.val(1);
    }

    $catatan
      .removeClass("d-none")
      .text(
        "Terisi dari stok: " +
          v.kode +
          " ( " +
          String(v.ukuran).toUpperCase() +
          " ) · " +
          rupiah(v.harga) +
          " · stok tersedia " +
          v.stok,
      );
    $batal.removeClass("d-none");

    fotoMuat(v.id_stok);
  }

  function pilih(v) {
    if (!v) {
      return;
    }

    tutupDaftar();
    isi(v);
  }

  /*
   * Daftar varian satu toko + satu kolam dimuat sekali per kombinasi; ganti
   * toko atau ganti jenis layanan membuat kunci ini basi, jadi pemanggil cukup
   * mengosongkan `dimuat`. `q` sengaja tidak dikirim: tanpanya api/stok/cari
   * menjawab seluruh isi kolam, dan penyaringannya dikerjakan di sini.
   */
  function muat(paksa) {
    var juragan = parseInt($('select[name="juragan"]').val(), 10) || 0,
      tipe = $("#tipe_pesanan").val() || "",
      kunci = juragan + "|" + tipe;

    if (juragan < 1) {
      dimuat = "";
      daftar = [];
      batas = false;
      kabar = "Pilih juragan dulu ya.";

      return;
    }

    if (!paksa && dimuat === kunci) {
      // daftar sudah terpasang: kabar lama dihitung ulang dari isinya, bukan
      // dikosongkan buta -- toko tanpa produk memang "Tidak ada produk"
      kabar = daftar.length === 0 ? "Tidak ada produk" : "";

      return;
    }

    dimuat = kunci;
    daftar = [];
    kabar = "Memuat daftar produk…";
    saring();
    gambar();

    var jalan = ++penanda;

    $.getJSON(alamatCari, { juragan_id: juragan, tipe: tipe })
      .done(function (b) {
        // permintaan yang lebih baru sudah menyusul: jawaban lama dibuang
        if (penanda !== jalan) {
          return;
        }

        daftar = (b && b.results) || [];
        batas = !!(b && b.terbatas);
        // toko yang belum punya produk dipantau dijawab apa adanya, di dalam
        // daftarnya langsung
        kabar = daftar.length === 0 ? "Tidak ada produk" : "";
        saring();
        gambar();
      })
      .fail(function () {
        if (penanda !== jalan) {
          return;
        }

        // jangan mengunci kunci ini: percobaan berikutnya boleh mengulang
        dimuat = "";
        daftar = [];
        batas = false;
        kabar = "Daftar produk gagal dimuat, coba buka lagi.";
        saring();
        gambar();
      });
  }

  // mengubah identitas baris (kode atau ukuran) berarti taunya ke stok sudah
  // tidak cocok lagi, jadi taunya dilepas; harga boleh dinegosiasikan per
  // orderan tanpa memutuskan hubungan ke barang yang dipantau
  $("#kode_produk").on("input", function () {
    if (dipilih && $.trim($(this).val()) !== String(dipilih.kode)) {
      kosongkan();
    }
  });

  $("#ukuran").on("change", function () {
    if (dipilih && $(this).val() !== String(dipilih.ukuran)) {
      kosongkan();
    }
  });

  /*
   * Tipe pesanan menentukan kolam stok yang ditawarkan (unit jual atau unit
   * sewa), jadi ganti tipe membuat semuanya basi: harga dan unit yang tadi
   * diambil berasal dari kolam yang lama, dan daftar yang terbuka menampilkan
   * kolam yang salah.
   */
  $("#tipe_pesanan").on("change", function () {
    dimuat = "";

    if (dipilih) {
      kosongkan();
    }

    if (!$panel.hasClass("d-none")) {
      muat(true);
    }
  });

  /*
   * Toko menentukan daftar varian yang ditawarkan, dan varian dari toko lain
   * tidak boleh tertinggal di baris ini, jadi ganti toko juga memaksa daftar
   * dimuat ulang.
   */
  $('select[name="juragan"]').on("change", function () {
    dimuat = "";

    if (dipilih) {
      kosongkan();
    }

    if (!$panel.hasClass("d-none")) {
      muat(true);
    }
  });

  /*
   * Kolomnya dua wajah: kosong = sedang mencari, berisi label = sudah terpilih.
   * Saat fokus masuk ke kolom yang masih menampilkan label, label itu dibersihkan
   * dulu supaya seluruh daftar terlihat lagi; kalau tidak jadi memilih, label
   * dipasang kembali saat kolom ditinggalkan. Membuka daftarnya sendiri dikerjakan
   * oleh klik (lihat gantiDaftar), bukan oleh fokus.
   */
  $katalog.on("focus", function () {
    if (dipilih && $katalog.val() === labelPilihan(dipilih)) {
      $katalog.val("");
    }
  });

  $katalog.on("click", function () {
    gantiDaftar();
  });

  /*
   * Panah ikut membuka-tutup. mousedown-nya ditahan supaya kolom tidak kehilangan
   * fokus dulu: tanpa itu blur menutup daftar, lalu click membukanya lagi, dan
   * klik kedua tidak pernah bisa menutup.
   */
  $panah.on("mousedown", function (e) {
    e.preventDefault();
  });

  $panah.on("click", function (e) {
    e.preventDefault();
    gantiDaftar();
  });

  $katalog.on("input", function () {
    $galat.addClass("d-none").text("");

    if ($daftar.hasClass("d-none")) {
      bukaDaftar();

      return;
    }

    saring();
    gambar();
  });

  $katalog.on("keydown", function (e) {
    switch (e.key) {
      case "ArrowDown":
        e.preventDefault();

        if ($daftar.hasClass("d-none")) {
          bukaDaftar();
        } else {
          geserSorot(1);
        }

        break;

      case "ArrowUp":
        e.preventDefault();
        geserSorot(-1);

        break;

      case "Enter": {
        if ($daftar.hasClass("d-none")) {
          return;
        }

        e.preventDefault();

        var $aktif = $daftar.find(".stok-item.aktif");

        if ($aktif.length > 0) {
          pilih($aktif.first().data("stok"));
        }

        break;
      }

      case "Escape":
        // daftar yang masih terbuka duluan yang ditutup, bukan modalnya
        if (!$daftar.hasClass("d-none")) {
          e.stopPropagation();
          tutupDaftar();
        }

        break;

      case "Tab":
        tutupDaftar();

        break;
    }
  });

  // blur dipakai untuk menutup daftar sekaligus membereskan isian kolom:
  // klik baris daftar tidak sampai ke sini karena mousedown-nya ditahan
  $katalog.on("blur", function () {
    tutupDaftar();

    $katalog.val(dipilih ? labelPilihan(dipilih) : "");
  });

  // mousedown (bukan click) + preventDefault: pilihan terbaca sebelum kolom
  // sempat kehilangan fokus
  $daftar.on("mousedown", ".stok-item", function (e) {
    e.preventDefault();

    var manual = $(this).data("manual");

    if (manual) {
      isiManual(String(manual));

      return;
    }

    pilih($(this).data("stok"));
  });

  $(document).on("mousedown", function (e) {
    if ($(e.target).closest(".stok-pilih").length === 0) {
      // lewat tutupDaftar(): kalau kelas .terbuka ditinggal, panahnya tetap
      // menghadap atas seolah daftarnya masih terbuka
      tutupDaftar();
    }
  });

  $buka.on("click", function () {
    bukaPanel();

    // panel dibuka dengan combobox yang masih TERTEKUP: daftar varian baru
    // muncul begitu kolom atau panahnya diklik, tidak sekaligus saat panelnya
    // dibuka, supaya layar awal tetap tenang (bukan langsung menumpuk daftar)
    $katalog.trigger("focus");
  });

  // "Tutup" melipat panel apa adanya: pilihan yang sudah diambil tetap terpakai
  $tutup.on("click", function () {
    tutupPanel();
  });

  // "Tulis manual" melepas tautan stok sekaligus melipat panelnya kembali
  $batal.on("click", function () {
    kosongkan();
    tutupPanel();
  });

  // baris baru selalu mulai bersih; edit memanggil STOK.tata() setelahnya
  $("#tambahProduk").on("hidden.bs.modal", function () {
    kosongkan();
    tutupPanel();
    bawaanSinkron();
  });

  // baris yang dibuang membawa serta janji fotonya
  $(document).on("click", ".hapus_row.orderan", function () {
    setTimeout(bawaanSinkron, 0);
  });

  window.STOK = {
    /** id_stok yang sedang terpakai di modal, '' kalau ditulis manual. */
    ambil: function () {
      return $id.val() || "";
    },

    /**
     * Kolam varian yang sedang terpakai ('jual' / 'sewa'), '' kalau ditulis
     * manual. Dipakai halaman orderan menandai barisnya: baris kolam sewa harus
     * hilang saat tipe diubah ke Pembuatan/Jual, dan sebaliknya.
     */
    kolam: function () {
      return dipilih ? dipilih.kolam || "" : "";
    },

    /**
     * Kabar merah di dalam panel, dipakai halaman form ketika baris hendak
     * disimpan tanpa produk. Panel dibuka dulu supaya pesannya terlihat.
     */
    kabari: function (teks) {
      bukaPanel();
      $galat.removeClass("d-none").text(teks || "");
      $katalog.trigger("focus");
      bukaDaftar();
    },

    /**
     * Segarkan daftar foto bawaan dari baris yang masih ada. Dipanggil setelah
     * baris dibuang programatik (ganti tipe layanan) supaya foto produk baris
     * itu tidak tertinggal di panel "Foto Orderan".
     */
    sinkronFoto: function () {
      bawaanSinkron();
    },

    /**
     * Muat ulang daftar (mis. saat modal dibuka, karena toko bisa saja baru
     * dipilih lewat draf).
     */
    segar: function () {
      muat(true);
    },

    /**
     * Isi ulang panel untuk baris yang sedang disunting (0 = manual).
     * Dipanggil sesudah halaman pengisi kolom, jadi keadaan form sekarang
     * yang jadi patokan kalau-kalau pengguna mengubah barisnya. `kolam`
     * dibawa lagi supaya tanda kolam baris tidak hilang saat disimpan ulang.
     */
    tata: function (id, kolam) {
      if (!id) {
        kosongkan();
        bukaPanel();

        // baris manual tetap butuh pemilihnya terbuka, dan kode yang sedang
        // terpakai di baris itu disebutkan supaya orang tidak menebak-nebak
        var kode = $.trim($("#kode_produk").val() || "");

        if (kode !== "") {
          $catatan
            .removeClass("d-none")
            .text(
              "Baris ini ditulis manual: " +
                kode +
                ". Pilih barang di daftar untuk menautkannya ke stok.",
            );
        }

        return;
      }

      ++token;

      dipilih = {
        id_stok: Number(id),
        kode: $.trim($("#kode_produk").val() || ""),
        ukuran: String($("#ukuran").val() || ""),
        kolam: kolam || "",
      };

      $id.val(id);
      $katalog.val(labelPilihan(dipilih));
      $catatan.removeClass("d-none").text("Baris ini memakai stok produk.");
      $batal.removeClass("d-none");

      // dibuka setelah `dipilih` terisi supaya labelnya ikut terpasang
      bukaPanel();

      fotoMuat(id);
    },
  };
})(jQuery);
