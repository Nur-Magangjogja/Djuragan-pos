/*
 * Popup kabar (galat / sukses / info) untuk form yang tetap berada di halaman
 * yang sama, sekarang dipakai alur tulis & sunting orderan.
 *
 * Dulu kegagalan simpan di tulis orderan ditampilkan lewat API toast
 * Bootstrap 4 ($(...).toast('show') dan data-delay) sementara aplikasinya sudah
 * Bootstrap 5: panggilan itu tidak pernah ada, jadi pesan validasi dari server
 * hilang tanpa jejak. Modul ini sengaja tidak bergantung pada plugin apa pun -
 * cuma jQuery untuk menyentuh DOM - supaya tidak ada lagi beda versi yang
 * membuat pesan lenyap diam-diam.
 *
 * Masa hidupnya disamakan dengan banner flash di template/main.php: satu pesan
 * hidup 5 detik lalu pudar dan dibuang. Yang lebih lama dari itu hanya boleh
 * lewat tombol tutupnya sendiri.
 */
(function ($) {
  "use strict";

  var UMUR = 5000; // sama dengan [data-flash] di template/main.php
  var PUDAR = 400; // lama fadeOut
  var DEDUP = 1200; // pesan kembar dalam hitungan detik cukup ditampilkan sekali
  var MAKS = 3; // popup sekaligus; sisanya diringkas satu baris
  var wadah = null;
  var terakhir = {};

  function pastikan() {
    if (wadah && document.body.contains(wadah)) {
      return wadah;
    }

    wadah = $('<div class="kabar-wadah" aria-live="polite"></div>').appendTo("body")[0];

    return wadah;
  }

  function rupa(jenis) {
    return jenis === "sukses"
      ? { kelas: "kabar-sukses", ikon: "fa-check-circle", peran: "status" }
      : jenis === "info"
        ? { kelas: "kabar-info", ikon: "fa-info-circle", peran: "status" }
        : { kelas: "kabar-galat", ikon: "fa-exclamation-circle", peran: "alert" };
  }

  function tampil(jenis, teks) {
    teks = String(teks === null || teks === undefined ? "" : teks).trim();

    if (teks === "") {
      return null;
    }

    var kunci = jenis + "|" + teks;
    var sekarang = Date.now();

    // tombol Simpan yang ditekan dua kali, atau satu field yang dilaporkan dua
    // jalur berbeda, tidak boleh menumpuk kartu yang sama di layar
    if (terakhir[kunci] && sekarang - terakhir[kunci] < DEDUP) {
      return null;
    }

    terakhir[kunci] = sekarang;

    var w = rupa(jenis);
    var $kartu = $('<div class="kabar"></div>')
      .addClass(w.kelas)
      .attr("role", w.peran);

    $('<i class="fal kabar-ikon"></i>').addClass(w.ikon).appendTo($kartu);
    $('<div class="kabar-teks"></div>').text(teks).appendTo($kartu);

    var $tutup = $('<button type="button" class="kabar-tutup" aria-label="Tutup pesan"></button>')
      .html("&times;")
      .on("click", function () {
        buang();
      });

    $kartu.append($tutup);
    pastikan().append($kartu);

    var jeda = window.setTimeout(buang, UMUR);

    function buang() {
      window.clearTimeout(jeda);
      $kartu.addClass("kabar-pudar");
      window.setTimeout(function () {
        $kartu.remove();
      }, PUDAR);
    }

    // jangan biarkan tumpukan tumbuh kalau orang mengetik cepat
    var $isi = $(wadah).children(".kabar");

    if ($isi.length > MAKS) {
      $isi.slice(0, $isi.length - MAKS).each(function () {
        $(this).remove();
      });
    }

    return $kartu;
  }

  function nama(el) {
    var $el = $(el);
    var label = "";

    if (el.id) {
      label = $("label[for='" + el.id.replace(/'/g, "\\'") + "']").first().text();
    }

    if ($.trim(label) === "") {
      label = $el.closest("label").text() || $el.closest(".form-group, .mb-3, .col").find("label").first().text();
    }

    if ($.trim(label) === "") {
      label = el.name || el.getAttribute("placeholder") || "";
    }

    label = $.trim(label).replace(/[:*\u2022]+$/g, "");

    return label === "" ? "Isian ini" : label;
  }

  /*
   * Pesan dibangun sendiri, bukan dari el.validationMessage: teks bawaan
   * peramban berbahasa Inggris ("Please fill out this field") dan tampil
   * sebagai gelembung kecil yang menempel di kolom, bukan popup yang diminta.
   */
  function pesanField(el) {
    var v = el.validity || {};
    var apa = nama(el);

    if (v.valueMissing) {
      return apa + " belum diisi.";
    }

    if (el.type === "number" && v.rangeUnderflow) {
      return apa + " tidak boleh kurang dari " + (el.min || 0) + ".";
    }

    if (el.type === "number" && v.rangeOverflow) {
      return apa + " melebihi batas " + (el.max || "-") + ".";
    }

    if (v.stepMismatch) {
      return apa + " harus angka bulat.";
    }

    if (v.typeMismatch || v.badInput) {
      return apa + " belum sesuai format yang diminta.";
    }

    if (v.tooLong) {
      return apa + " terlalu panjang.";
    }

    if (v.patternMismatch) {
      return apa + " belum sesuai format yang diminta.";
    }

    return apa + " belum benar.";
  }

  function layak(el) {
    // isian tersembunyi, mati, atau hanya-baca bukan sasaran validasi peramban;
    // yang tidak terlihat (panel lipat, kolom COD yang sedang mati) juga tidak
    // boleh menuntut isi
    return (
      !el.disabled &&
      !el.readOnly &&
      el.type !== "hidden" &&
      el.type !== "button" &&
      el.type !== "submit" &&
      el.offsetWidth + el.offsetHeight > 0
    );
  }

  /*
   * Periksa satu form sebelum dikirim. true = bersih; false = sudah menampilkan
   * popupnya dan fokus di isian pertama yang bermasalah.
   *
   * Dipakai bersama novalidate di form-nya: tanpa itu, peramban menghentikan
   * submit lebih dulu dan memunculkan gelembungnya sendiri, sehingga popup di
   * sini tidak pernah sempat tampil.
   */
  function saringForm(form) {
    var $form = form instanceof $ ? form : $(form);

    if ($form.length === 0) {
      return true;
    }

    var buruk = [];

    $form.find(":input").each(function () {
      var el = this;

      if (!layak(el)) {
        return;
      }

      var wajib = el.required || $(el).attr("required") !== undefined;

      if (wajib && $.trim(String(el.value || "")) === "") {
        buruk.push({ el: el, pesan: nama(el) + " belum diisi." });

        return;
      }

      if (el.willValidate && el.validity && !el.validity.valid) {
        buruk.push({ el: el, pesan: pesanField(el) });
      }
    });

    if (buruk.length === 0) {
      return true;
    }

    var tercatat = {};

    $.each(buruk.slice(0, MAKS), function (i, b) {
      if (!tercatat[b.pesan]) {
        tercatat[b.pesan] = true;
        tampil("galat", b.pesan);
      }
    });

    if (buruk.length > MAKS) {
      tampil("galat", "Masih ada " + (buruk.length - MAKS) + " isian lain yang perlu dibereskan.");
    }

    try {
      buruk[0].el.focus();
    } catch (e) {
      // fokus boleh gagal (mis. kolom baru saja dilipat); pesannya sudah tampil
    }

    return false;
  }

  /*
   * Nama orang: huruf, spasi, dan tanda baca nama yang wajar (titik, apostrof,
   * hubung). Angka di kolom ini hampir selalu jari yang nyasar ke HP, dan
   * namanya ikut terbaca di seluruh laporan, jadi angkanya dibuang begitu
   * masuk - lewat ketikan maupun lewat tempelan.
   */
  var BOLEH_NAMA = /[^\p{L}\p{M}\s'.\-’—–]+/gu;

  function cukilNama(el) {
    var lama  = el.value;
    var caret = typeof el.selectionStart === "number" ? el.selectionStart : lama.length;
    var baru  = lama.replace(BOLEH_NAMA, "");

    if (baru === lama) {
      return false;
    }

    el.value = baru;

    // kursor digeser sejauh huruf yang dibuang di depannya, supaya mengetik di
    // tengah nama tidak melompat ke akhir kata
    var buang = (lama.slice(0, caret).match(BOLEH_NAMA) || []).length;

    try {
      el.setSelectionRange(Math.max(0, caret - buang), Math.max(0, caret - buang));
    } catch (e) {
      // sebagian jenis isian tidak mendukung seleksi; isinya sudah dibersihkan
    }

    return true;
  }

  function jagaNama(pemilih) {
    $(pemilih).each(function () {
      var el = this;

      if (el.dataset.kabarNama) {
        return;
      }

      el.dataset.kabarNama = "1";

      function periksa() {
        if (cukilNama(el)) {
          tampil("galat", "Nama hanya boleh berisi huruf — angka dan simbol lain dibuang.");
        }
      }

      $(el).on("input", periksa);

      // tempelan tidak selalu memicu input di peramban lama: dijaga sekali lagi
      // setelah isian sempat terisi
      $(el).on("paste", function () {
        window.setTimeout(periksa, 0);
      });
    });
  }

  window.KABAR = {
    galat: function (teks) {
      return tampil("galat", teks);
    },

    sukses: function (teks) {
      return tampil("sukses", teks);
    },

    info: function (teks) {
      return tampil("info", teks);
    },

    saringForm: saringForm,

    /** Jaga satu/sekumpulan kolom nama dari angka dan simbol asing. */
    jagaNama: jagaNama,

    /** Nama satu isian, untuk pesan yang dirakit di halaman pemanggil. */
    nama: nama,

    tutupSemua: function () {
      $(pastikan()).empty();
    },
  };
})(jQuery);
