/*
 * Panel inputan ukuran per item orderan (form Tulis, form Sunting, modal produk).
 *
 * Kerangkanya dibangun server lewat ukuran_form_panel() di fungsi_helper.php;
 * file ini yang mengisi dropdown jenis produk dan kolom angkanya dari
 * window.UKURAN_FORM. Panel baris lama memakai prefix nama dari data-nama supaya
 * angka yang sudah tercatat ikut terkirim terus saat orderan disunting, panel
 * modal tidak punya nama (kunci barisnya baru ada saat produk ditambah, jadi
 * nilainya diambil lewat UKURAN.nilai()).
 */
(function ($) {
  "use strict";

  var data = window.UKURAN_FORM;

  if (!data) {
    return;
  }

  function lepas(teks) {
    return String(teks === undefined || teks === null ? "" : teks)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function tambah(daftar, lebih) {
    var punya = {};

    $.each(daftar, function (i, k) {
      punya[k.id_komponen] = true;
    });

    $.each(lebih || [], function (i, k) {
      if (!punya[k.id_komponen]) {
        daftar.push(k);
      }
    });

    return daftar;
  }

  function daftar(jenis, lebih) {
    var pakai = (data.komponen[0] || []).slice();

    if (jenis !== "" && jenis !== null && jenis !== undefined) {
      pakai = pakai.concat(data.komponen[jenis] || []);
    }

    return tambah(pakai, lebih);
  }

  function kolom(k, nilai, nama) {
    var nm = nama
      ? ' name="' + nama + "[nilai_ukuran][" + k.id_komponen + ']"'
      : "";
    var sat = k.satuan
      ? '<span class="input-group-text bg-white border-start-0 text-muted px-2 text-xs">' +
        lepas(k.satuan) +
        "</span>"
      : "";

    return (
      '<div class="col-6 col-sm-4 col-md-3 mb-2">' +
      '<label class="form-label text-xs fw-semibold text-secondary mb-1 d-block text-truncate" title="' +
      lepas(k.nama) +
      '">' +
      lepas(k.nama) +
      "</label>" +
      (k.satuan
        ? '<div class="input-group input-group-sm">' +
          '<input type="number" class="form-control form-control-sm uk-field bg-white border-end-0" step="0.1" min="0"' +
          ' data-key="' +
          k.id_komponen +
          '" data-label="' +
          lepas(k.nama) +
          '" data-satuan="' +
          lepas(k.satuan) +
          '"' +
          nm +
          ' value="' +
          lepas(nilai) +
          '" autocomplete="off" placeholder="0">' +
          sat +
          "</div>"
        : '<input type="number" class="form-control form-control-sm uk-field bg-white" step="0.1" min="0"' +
          ' data-key="' +
          k.id_komponen +
          '" data-label="' +
          lepas(k.nama) +
          '" data-satuan="' +
          lepas(k.satuan) +
          '"' +
          nm +
          ' value="' +
          lepas(nilai) +
          '" autocomplete="off" placeholder="0">') +
      "</div>"
    );
  }

  function pilihan(jenis) {
    var html = ['<option value="">-- Pilih Jenis Busana / Pola --</option>'];

    $.each(data.jenis, function (id, nama) {
      html.push(
        '<option value="' +
          id +
          '"' +
          (String(jenis) === String(id) ? " selected" : "") +
          ">" +
          lepas(nama) +
          "</option>",
      );
    });

    return (
      '<div class="col-12 mb-2 pb-2 border-bottom border-ink-200/70">' +
      '<div class="d-flex align-items-center justify-content-between mb-1">' +
      '<button type="button" class="btn btn-link text-decoration-none p-0 text-xs terapkan-semua-jenis d-none" title="Terapkan jenis ini ke semua anggota rombongan"><i class="fal fa-clone me-1"></i>Terapkan ke semua</button>' +
      "</div>" +
      '<select class="form-select form-select-sm bg-white uk-jenis">' +
      html.join("") +
      "</select>" +
      "</div>"
    );
  }

  function angka($panel) {
    var hasil = {};

    $panel.find(".uk-field").each(function () {
      var v = $.trim($(this).val() || "");

      if (v !== "") {
        hasil[$(this).data("key")] = v;
      }
    });

    return hasil;
  }

  /** "Lingkar Dada 102 cm" per kolom yang terisi (koma, bukan titik). */
  function isiRingkas($panel) {
    var bagian = [];

    $panel.find(".uk-field").each(function () {
      var v = $.trim($(this).val() || ""),
        s = $(this).data("satuan");

      if (v !== "") {
        bagian.push(
          $(this).data("label") +
            " " +
            v.replace(".", ",") +
            (s ? " " + s : ""),
        );
      }
    });

    return bagian;
  }

  function ringkas($panel) {
    var $wrapper = $panel.closest(".ukuran-wrapper"),
      $ringkas = $wrapper.find(".uk-ringkas").first(),
      bagian = isiRingkas($panel),
      jenisTeks = $panel.find(".uk-jenis option:selected").text();

    if ($ringkas.length === 0) {
      return;
    }

    var labelJenis =
      $panel.find(".uk-jenis").val() &&
      jenisTeks &&
      jenisTeks.indexOf("--") === -1
        ? jenisTeks + ": "
        : "";

    var teks =
      bagian.length > 0
        ? labelJenis +
          (bagian.length > 3
            ? bagian.slice(0, 3).join(" · ") + " +" + (bagian.length - 3)
            : bagian.join(" · "))
        : "";

    // ringkasan cuma tampil selama panelnya masih ditutup
    $ringkas
      .text(teks)
      .toggleClass("d-none", teks === "" || !$panel.hasClass("d-none"));

    // perbarui status tombol buka-ukuran jika ada
    var $btn = $wrapper.find(".buka-ukuran");
    if ($btn.length > 0 && $panel.hasClass("d-none")) {
      $btn
        .find(".teks-buka")
        .text(bagian.length > 0 ? "Ubah Ukuran" : "Atur Ukuran");
    }
  }

  function isi($panel, jenis) {
    var nilai = $.extend({}, $panel.data("nilai") || {}, angka($panel)),
      nama = $panel.data("nama") || "",
      html = [];

    $.each(daftar(jenis, $panel.data("lebih")), function (i, k) {
      html.push(kolom(k, nilai[k.id_komponen], nama));
    });

    if (html.length === 0) {
      $panel
        .find(".uk-isi")
        .html(
          '<div class="col-12 py-2"><p class="small text-muted mb-0"><i class="fal fa-info-circle me-1"></i> Pilih jenis busana di atas untuk menampilkan kolom ukuran.</p></div>',
        );
    } else {
      var tombolTutup =
        $panel.closest(".ukuran-wrapper").find(".buka-ukuran").length > 0
          ? '<div class="col-12 mt-1 pt-2 border-top border-ink-200/60 d-flex justify-content-between align-items-center">' +
            '<span class="text-xs text-muted"><i class="fal fa-check-circle text-success me-1"></i>Ukuran tersimpan otomatis</span>' +
            '<button type="button" class="btn btn-xs btn-light border py-1 px-2.5 text-xs tutup-ukuran-btn"><i class="fal fa-check me-1"></i>Selesai</button>' +
            "</div>"
          : "";
      $panel.find(".uk-isi").html(html.join("") + tombolTutup);
    }

    // Tampilkan tombol "Terapkan ke semua" jika ada lebih dari 1 anggota di daftar
    var $daftar = $panel.closest(".anggota-daftar");
    if (
      $daftar.length > 0 &&
      $daftar.children(".anggota-baris").length > 1 &&
      jenis
    ) {
      $panel.find(".terapkan-semua-jenis").removeClass("d-none");
    } else {
      $panel.find(".terapkan-semua-jenis").addClass("d-none");
    }

    ringkas($panel);
  }

  /** Isi satu kerangka panel; dipakai waktu halaman dimuat dan waktu baris baru ditambah. */
  function tempel(panel) {
    var $panel = $(panel);

    $panel.html(
      pilihan($panel.data("jenis")) +
        '<div class="uk-isi row gx-2 w-100"></div>',
    );
    isi($panel, $panel.data("jenis") || "");

    return $panel;
  }

  $(function () {
    $(".ukuran-panel").each(function () {
      tempel(this);
    });
  });

  $(document)
    .on("change", ".uk-jenis", function () {
      var $panel = $(this).closest(".ukuran-panel");

      isi($panel, $(this).val());
    })
    .on("input", ".uk-field", function () {
      ringkas($(this).closest(".ukuran-panel"));
    })
    .on("click", ".buka-ukuran", function (e) {
      e.preventDefault();

      var $wrapper = $(this).closest(".ukuran-wrapper");
      var $panel = $wrapper.find(".ukuran-panel").first().toggleClass("d-none");
      var buka = !$panel.hasClass("d-none");

      $(this).attr("aria-expanded", buka ? "true" : "false");
      $(this)
        .find(".teks-buka")
        .text(
          buka
            ? "Tutup Ukuran"
            : $wrapper.find(".uk-field").filter(function () {
                  return $.trim($(this).val()) !== "";
                }).length > 0
              ? "Ubah Ukuran"
              : "Atur Ukuran",
        );
      $(this).find(".ikon-panah").toggleClass("rotate-180", buka);
      ringkas($panel);
    })
    .on("click", ".tutup-ukuran-btn", function (e) {
      e.preventDefault();
      var $wrapper = $(this).closest(".ukuran-wrapper");
      var $panel = $wrapper.find(".ukuran-panel").first().addClass("d-none");
      var $btn = $wrapper.find(".buka-ukuran").first();
      $btn.attr("aria-expanded", "false");
      $btn.find(".teks-buka").text(
        $wrapper.find(".uk-field").filter(function () {
          return $.trim($(this).val()) !== "";
        }).length > 0
          ? "Ubah Ukuran"
          : "Atur Ukuran",
      );
      $btn.find(".ikon-panah").removeClass("rotate-180");
      ringkas($panel);
    })
    .on("click", ".terapkan-semua-jenis", function (e) {
      e.preventDefault();
      var val = $(this).closest(".col-12").find(".uk-jenis").val();
      if (!val) {
        return;
      }
      var $daftar = $(this).closest(".anggota-daftar");
      $daftar.find(".ukuran-panel").each(function () {
        var $p = $(this);
        $p.find(".uk-jenis").val(val);
        isi($p, val);
      });
    });

  // dipakai JS bawaan halaman untuk memindahkan angka ke hidden input baris baru
  window.UKURAN = {
    tempel: tempel,
    isi: isi,
    pilihJenis: function (panel, jenis) {
      var $panel = $(panel);
      $panel.attr("data-jenis", jenis || "");
      $panel.find(".uk-jenis").val(jenis || "");
      isi($panel, jenis || "");
    },
    nilai: function (panel) {
      var hasil = [];

      $(panel)
        .find(".uk-field")
        .each(function () {
          var v = $.trim($(this).val() || "");

          if (v !== "") {
            hasil.push({
              key: $(this).data("key"),
              label: $(this).data("label"),
              satuan: $(this).data("satuan"),
              nilai: v,
            });
          }
        });

      return hasil;
    },
    ringkas: function (panel) {
      return isiRingkas($(panel)).join(" · ");
    },
    kosongkan: function (panel) {
      var $panel = $(panel);

      $panel.find(".uk-jenis").val("");
      $panel.data("nilai", {});
      isi($panel, "");
    },
  };
})(jQuery);
