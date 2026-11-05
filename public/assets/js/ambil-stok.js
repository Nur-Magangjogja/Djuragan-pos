/*
 * Panel "Ambil dari stok produk" di modal tambah orderan.
 *
 * Ditulis satu kali karena modalnya sama di tiga halaman (Tulis admin, Tulis CS,
 * Sunting admin) dan dipasang lewat stok_panel_form(). Yang dicarinya hanya varian
 * stok milik toko yang sedang dipilih di form, polanya sengaja disamakan dengan
 * cari-pelanggan.js: debounce, token permintaan supaya jawaban yang terlambat
 * dibuang, dan saran dirender lewat .text() supaya nama barang tidak pernah jadi
 * markup.
 *
 * Memilih varian mengisi kode, harga, dan ukuran kolom isian lalu menyimpan
 * id_stok-nya di hidden [name="stok_produk"]; halaman pemanggil baris orderan
 * membacanya lewat window.STOK.ambil() dan mengirimnya sebagai produk[k][stok_id].
 * Kalau setelah itu pengguna mengubah kode/harga/ukuran sendiri, taunya ke stok
 * dilepas lagi - barisnya sudah tidak menggambarkan varian itu.
 */
(function ($) {
  "use strict";

  var $panel = $(".stok-panel");

  if ($panel.length === 0) {
    return;
  }

  var $cari = $panel.find(".stok-cari"),
    $id = $panel.find(".stok-id"),
    $saran = $panel.find(".saran-stok"),
    $catatan = $panel.find(".stok-catatan"),
    $acuan = $panel.find(".foto-acuan"),
    $isi = $panel.find(".stok-foto-isi"),
    $batal = $panel.find(".stok-batal"),
    $pemicu = $(".stok-pemicu"),
    $buka = $(".stok-buka"),
    alamatCari = $panel.data("cari"),
    alamatBaca = $panel.data("baca"),
    token = 0,
    dipilih = null,
    bawaan = {},
    dimintak = {};

  function rupiah(n) {
    return "Rp " + Number(n || 0).toLocaleString("id-ID");
  }

  function saranTampilkan(hasil, pesan) {
    $saran.empty().removeClass("d-none");

    if (pesan) {
      $saran.append(
        $('<div class="list-group-item text-muted small py-2"></div>').text(
          pesan,
        ),
      );

      return;
    }

    $.each(hasil, function (i, v) {
      var $item = $(
          '<button type="button" class="list-group-item list-group-item-action text-start py-2"></button>',
        ).data("stok", v),
        $judul = $('<span class="d-block fw-bold"></span>').text(
          v.kode + " ( " + String(v.ukuran).toUpperCase() + " )",
        ),
        $meta = $('<span class="d-block small"></span>').text(
          rupiah(v.harga) +
            " · stok " +
            (v.kolam === "sewa" ? "sewa " : "") +
            v.stok +
            (v.keterangan ? " · " + v.keterangan : ""),
        );

      if (Number(v.stok) <= 0) {
        $meta
          .append(" ")
          .append(
            $('<span class="badge bg-danger-subtle text-danger"></span>').text(
              "habis",
            ),
          );
      }

      $item.append($judul).append($meta);
      $saran.append($item);
    });
  }

  function kosongkan() {
    dipilih = null;
    $id.val("");
    $catatan.addClass("d-none").text("");
    $acuan.addClass("d-none");
    $isi.empty();
    $batal.addClass("d-none");
  }

  /*
   * Panel tidak tampil bawaan (d-none dari stok_panel_form). Pancingan
   * "Ambil dari stok produk?" membuka panel dan menyembunyikan dirinya lagi;
   * "Tulis manual" dan penutupan modal mengembalikannya ke keadaan lipat.
   */
  function bukaPanel() {
    $panel.removeClass("d-none");
    $pemicu.addClass("d-none");
    $buka.attr("aria-expanded", "true");
  }

  function tutupPanel() {
    $panel.addClass("d-none");
    $pemicu.removeClass("d-none");
    $buka.attr("aria-expanded", "false");
    $saran.addClass("d-none").empty();
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
    var aktif = {};

    $('#iForm input[name^="produk["][name$="[stok_id]"]').each(function () {
      var id = parseInt(this.value, 10);

      if (!id) {
        return;
      }

      var kode = $.trim(
        $(this).closest("tr").find('input[name$="[kode]"]').val() || "",
      );

      if (kode !== "") {
        aktif[kode] = id;
      }
    });

    return aktif;
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

  function isi(v) {
    var $ukuran = $("#ukuran"),
      nilai = String(v.ukuran || ""),
      $opsi = $ukuran.find(
        'option[value="' + nilai.replace(/"/g, '\\"') + '"]',
      );

    dipilih = v;
    $id.val(v.id_stok);

    // kode bisa saja belum ikut tercantum di dropdown katalog produk (kolam
    // ini hanya membawa 40 teratas), jadi opsinya diselipkan dulu supaya
    // val() benar-benar kebaca oleh halaman pemilih item
    var $kode = $("#kode_produk");

    if ($kode.is("select") && $kode.find('option[value="' + window.CSS.escape(v.kode) + '"]').length === 0) {
      $("<option>").val(v.kode).text(v.kode + " \u00B7 (di luar katalog)").appendTo($kode);
    }

    $kode.val(v.kode);
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
          rupiah(v.harga),
      );
    $batal.removeClass("d-none");
    $saran.addClass("d-none").empty();
    $cari.val("");

    fotoMuat(v.id_stok);
  }

  function cari() {
    var q = $.trim($cari.val()),
      juragan = parseInt($('select[name="juragan"]').val(), 10) || 0,
      // tipe pesanan menentukan kolam yang dicari: unit jual atau unit sewa
      tipe = $("#tipe_pesanan").val() || "",
      jalan = ++token;

    if (q.length < 2) {
      $saran.addClass("d-none").empty();

      return;
    }

    if (juragan < 1) {
      saranTampilkan([], "Pilih juragan dulu ya.");

      return;
    }

    $.getJSON(alamatCari, { q: q, juragan_id: juragan, tipe: tipe })
      .done(function (b) {
        if (token !== jalan) {
          return;
        }

        var hasil = (b && b.results) || [];

        saranTampilkan(
          hasil,
          hasil.length === 0
            ? "Barang itu belum dipantau stoknya di toko ini."
            : "",
        );
      })
      .fail(function () {
        if (token !== jalan) {
          return;
        }

        saranTampilkan([], "Pencarian gagal, coba lagi.");
      });
  }

  var saatKetik = (function () {
    var timer;

    return function () {
      clearTimeout(timer);
      timer = setTimeout(cari, 300);
    };
  })();

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
   * Tipe pesanan menentukan kolam stok yang dicari (unit jual atau unit sewa),
   * jadi ganti tipe membuat semuanya basi: harga dan unit yang tadi diambil
   * berasal dari kolam yang lama, dan saran yang masih terbuka menampilkan
   * kolam yang salah.
   */
  $("#tipe_pesanan").on("change", function () {
    $saran.addClass("d-none").empty();

    if (dipilih) {
      kosongkan();
    }
  });

  $cari.on("input", saatKetik).on("focus", function () {
    if ($.trim($cari.val()).length >= 2) {
      cari();
    }
  });

  $saran.on("click", "button", function (e) {
    e.preventDefault();

    var v = $(this).data("stok");

    if (v) {
      isi(v);
    }
  });

  $buka.on("click", bukaPanel);

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

  $(document).on("mousedown", function (e) {
    if ($(e.target).closest(".stok-panel").length === 0) {
      $saran.addClass("d-none");
    }
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
     * Segarkan daftar foto bawaan dari baris yang masih ada. Dipanggil setelah
     * baris dibuang programatik (ganti tipe layanan) supaya foto produk baris
     * itu tidak tertinggal di panel "Foto Orderan".
     */
    sinkronFoto: function () {
      bawaanSinkron();
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
        tutupPanel();

        return;
      }

      ++token;
      bukaPanel();

      dipilih = {
        id_stok: Number(id),
        kode: $.trim($("#kode_produk").val() || ""),
        ukuran: String($("#ukuran").val() || ""),
        kolam: kolam || "",
      };

      $id.val(id);
      $catatan.removeClass("d-none").text("Baris ini memakai stok produk.");
      $batal.removeClass("d-none");

      fotoMuat(id);
    },
  };
})(jQuery);
