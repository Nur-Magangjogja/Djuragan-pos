/*
 * Panel "Anggota Rombongan" di form Tulis dan form Sunting.
 *
 * Barisnya diambil dari <template class="anggota-cadang"> yang ditulis PHP lewat
 * anggota_baris_form(), jadi markup-nya tidak ditulis dua kali di dua tempat.
 * Setiap baris sudah berisi input bernama anggota[k][...] dan panel ukuran
 * dengan data-nama yang sama, sehingga form.serialize() bawaan halaman ikut
 * mengirimnya tanpa perlu hook tambahan di script bawaan halaman.
 */
(function ($) {
  "use strict";

  var GANTIAN = "__K__";

  function kunci() {
    return Math.round(new Date().getTime() + Math.random() * 100);
  }

  function hitung($daftar) {
    var $baris = $daftar.children(".anggota-baris");

    // nomor kartu selalu ulang 1..n tiap ada tambah/hapus baris
    $baris.each(function (i) {
      $(this)
        .find(".anggota-nomor")
        .first()
        .text(i + 1);
    });

    $daftar.closest(".card").find(".jumlah-anggota").text($baris.length);
  }

  /**
   * Satu baris anggota baru. $isi boleh string (nama saja, untuk tempelan WA)
   * atau object {nama, nomor, catatan} untuk hasil impor spreadsheet.
   */
  function baris($daftar, isi) {
    var $cadang = $daftar.closest(".card").find(".anggota-cadang").first();

    if ($cadang.length === 0) {
      // dulu mengembalikan null tanpa kabar, jadi tombol "+ Tambah anggota"
      // terlihat rusak tanpa penjelasan apa pun
      if (window.KABAR) {
        KABAR.galat(
          "Baris anggota tidak bisa dibuat: cetakan barisnya tidak ada di halaman. Muat ulang lalu coba lagi.",
        );
      }

      return null;
    }

    var $row = $($cadang.html().split(GANTIAN).join(kunci()));

    // Wariskan jenis busana dari orderan (rombongan) atau anggota pertama jika sudah pernah dipilih
    var jenisRombongan =
      window.JENIS_BUSANA_ROMBONGAN ||
      $daftar.closest(".anggota-isi").attr("data-jenis-rombongan") ||
      $("#ukuranModal .uk-jenis").val() ||
      $(".list-orderan .ukuran-panel").first().attr("data-jenis");

    var jenisDipakai =
      jenisRombongan || $daftar.find(".uk-jenis").first().val();
    if (jenisDipakai) {
      $row.find(".ukuran-panel").attr("data-jenis", jenisDipakai);
    }

    $daftar.append($row);

    // Bersihkan filter cari agar baris baru tidak tersembunyi oleh search aktif
    $daftar.closest(".anggota-isi").find(".anggota-cari").val("");
    $daftar.children(".anggota-baris").removeClass("d-none");

    if (window.UKURAN) {
      window.UKURAN.tempel($row.find(".ukuran-panel"));
    }

    if (isi) {
      var data = typeof isi === "string" ? { nama: isi } : isi;

      if (data.nama) {
        $row.find(".anggota-nama").val(String(data.nama).slice(0, 60));
      }
      if (data.nomor) {
        $row
          .find('input[name$="[nomor]"]')
          .val(String(data.nomor).slice(0, 30));
      }
      if (data.catatan) {
        $row
          .find('input[name$="[catatan]"]')
          .val(String(data.catatan).slice(0, 255));
      }
    }

    hitung($daftar);

    return $row;
  }

  /** "1. A.N DEVI" -> "A.N DEVI"; nomor urut dari WA jangan ikut jadi nama. */
  function bersihkan(teks) {
    var n = String(teks === undefined ? "" : teks)
      .replace(/^\s*[-*•]\s*/, "")
      .replace(/^\s*\d+[.)]\s*/, "")
      .trim();

    return n.slice(0, 60);
  }

  /**
   * Petakan baris hasil baca spreadsheet menjadi baris anggota. Kolom dikenali
   * dari judul (Nama / No|Tinggi / Catatan) bila baris pertama memang judul;
   * kalau tidak, urutan kolom dianggap [nama, nomor, catatan]. Mengembalikan
   * jumlah anggota yang berhasil dibuat.
   *
   * @param {Array<Array>} rows hasil sheet_to_json berformat header:1
   */
  function imporAnggota($daftar, rows) {
    if (!rows || rows.length === 0) {
      return 0;
    }

    var header = (rows[0] || []).map(function (h) {
      return String(h).trim().toLowerCase();
    });
    var punyaJudul = header.some(function (h) {
      return h.indexOf("nama") !== -1;
    });
    var mulai = punyaJudul ? 1 : 0;
    var iNama = 0,
      iNomor = -1,
      iCatatan = -1;

    if (punyaJudul) {
      var fN = -1,
        fNo = -1,
        fC = -1;

      header.forEach(function (h, idx) {
        if (fN === -1 && h.indexOf("nama") !== -1) {
          fN = idx;
        } else if (
          fNo === -1 &&
          (h === "no" ||
            h === "nomor" ||
            h.indexOf("no.") !== -1 ||
            h.indexOf("tinggi") !== -1)
        ) {
          fNo = idx;
        } else if (
          fC === -1 &&
          (h.indexOf("catatan") !== -1 ||
            h.indexOf("ket") !== -1 ||
            h.indexOf("note") !== -1)
        ) {
          fC = idx;
        }
      });

      iNama = fN !== -1 ? fN : 0;
      iNomor = fNo;
      iCatatan = fC;
    }

    var count = 0;

    for (var r = mulai; r < rows.length; r++) {
      var row = rows[r] || [];
      var nama = bersihkan(row[iNama]);

      if (nama === "") {
        continue;
      }

      var isi = { nama: nama };

      if (iNomor >= 0) {
        isi.nomor = String(
          row[iNomor] === undefined || row[iNomor] === null ? "" : row[iNomor],
        ).trim();
      }
      if (iCatatan >= 0) {
        isi.catatan = String(
          row[iCatatan] === undefined || row[iCatatan] === null
            ? ""
            : row[iCatatan],
        ).trim();
      }

      baris($daftar, isi);
      count++;
    }

    return count;
  }

  $(document)
    .on("click", ".buka-anggota", function (e) {
      e.preventDefault();

      var $card = $(this).closest(".card"),
        $isi = $card.find(".anggota-isi").first(),
        $panah = $(this).find(".ikon-panah-anggota"),
        buka = $isi.hasClass("d-none");

      $isi.toggleClass("d-none", !buka);
      $(this).attr("aria-expanded", buka ? "true" : "false");
      $panah.toggleClass("rotate-180", buka);

      // Jika dibuka dan belum ada baris sama sekali, otomatis munculkan 1 anggota
      if (buka) {
        var $daftar = $isi.find(".anggota-daftar").first();
        if ($daftar.children(".anggota-baris").length === 0) {
          baris($daftar);
        }
      }
    })
    .on("click", ".tambah-anggota", function () {
      baris($(this).closest(".anggota-isi").find(".anggota-daftar").first());
    })
    .on("click", ".hapus-anggota", function () {
      var $daftar = $(this).closest(".anggota-daftar");

      $(this).closest(".anggota-baris").remove();
      hitung($daftar);
    })
    .on("click", ".tambah-tempelan", function () {
      var $daftar = $(".anggota-daftar").first(),
        $kotak = $("#anggota_tempel"),
        nama = $kotak
          .val()
          .split(/\r?\n/)
          .map(bersihkan)
          .filter(function (n) {
            return n !== "";
          });

      if (nama.length === 0) {
        $kotak.focus();

        if (window.KABAR) {
          KABAR.galat("Tempel dulu daftar namanya — satu nama per baris.");
        }

        return;
      }

      $.each(nama, function (i, n) {
        baris($daftar, n);
      });

      $kotak.val("");

      var modalEl = document.getElementById("modalTempelWA");
      if (modalEl && window.bootstrap && bootstrap.Modal) {
        var bsModal = bootstrap.Modal.getInstance(modalEl);
        if (bsModal) {
          bsModal.hide();
        } else {
          $("#modalTempelWA").modal("hide");
        }
      } else {
        $("#modalTempelWA").modal("hide");
      }

      if (window.KABAR) {
        KABAR.sukses(nama.length + " anggota berhasil ditambahkan.");
      }
    })
    .on("change", ".anggota-file", function () {
      var input = this;
      var file = input.files && input.files[0];

      if (!file) {
        return;
      }

      var $daftar = $(".anggota-daftar").first();
      var $info = $("#modalImporExcel .anggota-import-info")
        .removeClass("d-none");

      if (typeof XLSX === "undefined") {
        $info
          .removeClass("text-muted text-success")
          .addClass("text-danger")
          .text(
            "Pustaka Excel belum termuat; muat ulang halaman lalu coba lagi.",
          );
        input.value = "";

        return;
      }

      $info
        .removeClass("text-danger text-success")
        .addClass("text-muted")
        .text("Membaca " + file.name + "…");

      var reader = new FileReader();

      reader.onload = function (e) {
        var n;

        try {
          var wb = XLSX.read(new Uint8Array(e.target.result), {
            type: "array",
          });
          var ws = wb.Sheets[wb.SheetNames[0]];
          var rows = XLSX.utils.sheet_to_json(ws, {
            header: 1,
            blankrows: false,
            defval: "",
          });
          n = imporAnggota($daftar, rows);
        } catch (err) {
          $info
            .removeClass("text-muted text-success")
            .addClass("text-danger")
            .text(
              "Gagal membaca file: " + (err && err.message ? err.message : err),
            );
          input.value = "";

          return;
        }

        if (n === 0) {
          $info
            .removeClass("text-muted text-success")
            .addClass("text-danger")
            .text("Tidak ada baris nama yang terbaca dari file itu.");
        } else {
          $info
            .removeClass("text-muted text-danger")
            .addClass("text-success")
            .text(n + " anggota ditambahkan dari " + file.name + ".");

          setTimeout(function () {
            var modalEl = document.getElementById("modalImporExcel");
            if (modalEl && window.bootstrap && bootstrap.Modal) {
              var bsModal = bootstrap.Modal.getInstance(modalEl);
              if (bsModal) {
                bsModal.hide();
              } else {
                $("#modalImporExcel").modal("hide");
              }
            } else {
              $("#modalImporExcel").modal("hide");
            }
            $info.addClass("d-none").text("");
          }, 1200);

          if (window.KABAR) {
            KABAR.sukses(n + " anggota ditambahkan dari " + file.name + ".");
          }
        }

        input.value = "";
      };

      reader.onerror = function () {
        $info
          .removeClass("text-muted text-success")
          .addClass("text-danger")
          .text("Gagal membaca file.");
        input.value = "";
      };

      reader.readAsArrayBuffer(file);
    })
    .on("click", ".unduh-template-anggota", function () {
      if (typeof XLSX === "undefined") {
        if (window.KABAR) {
          KABAR.galat(
            "Pustaka Excel belum termuat; muat ulang halaman lalu coba lagi.",
          );
        }

        return;
      }

      var ws = XLSX.utils.aoa_to_sheet([
        ["Nama", "No./Tinggi", "Catatan"],
        ["A.N DEVI", "01 / 170cm", "lengan diperpendek"],
        ["BUDI SANTOSO", "02", ""],
      ]);
      var wb = XLSX.utils.book_new();

      XLSX.utils.book_append_sheet(wb, ws, "Anggota");
      XLSX.writeFile(wb, "template-anggota-rombongan.xlsx");
    });

  // baris warisan server di form Sunting: nomornya baru bisa diisi di sini
  $(function () {
    // Pindahkan modal tempel WA & impor Excel ke body agar tidak terpotong kontainer
    $("#modalTempelWA, #modalImporExcel").appendTo("body");

    $(".anggota-daftar").each(function () {
      hitung($(this));
    });
  });

  // ---- Search anggota ----
  $(document).on("input", ".anggota-cari", function () {
    var kata = $(this).val().toLowerCase().trim();
    var $daftar = $(this).closest(".anggota-isi").find(".anggota-daftar");
    $daftar.children(".anggota-baris").each(function () {
      var nama = $(this).find(".anggota-nama").val().toLowerCase();
      $(this).toggleClass("d-none", kata !== "" && nama.indexOf(kata) === -1);
    });
  });

})(jQuery);
