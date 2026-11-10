// Membungkus seluruh kode dalam IIFE (Immediately Invoked Function Expression)
// untuk mencegah polusi scope global.
(function (global) {
  "use strict";

  /**
   * Library Vanilla JavaScript untuk format mata uang Rupiah
   * pada elemen input, span, atau sel tabel.
   */
  /**
   * Class Utilitas untuk memformat dan mengurai angka.
   */
  class NumberFormatter {
    /**
     * Memformat angka menjadi string dengan pemisah ribuan.
     * @param {number|string} number Angka yang akan diformat.
     * @param {string} [prefix=''] Prefix mata uang atau simbol.
     * @param {boolean} [showDecimal=true] Menampilkan desimal atau tidak.
     * @returns {string} String yang sudah diformat.
     */
    static format(number, prefix = "", showDecimal = true) {
      if (typeof number !== "number") {
        number = NumberFormatter.parse(number);
      }
      if (isNaN(number)) {
        return "";
      }

      // --- Perbaikan Utama di Sini ---
      // Cek apakah angka memiliki desimal
      // Gunakan Math.floor untuk menghindari masalah floating point
      const hasDecimal = number !== Math.floor(number) || (showDecimal && number % 1 !== 0);

      const options = {
        minimumFractionDigits: hasDecimal ? 2 : 0,
        maximumFractionDigits: hasDecimal ? 2 : 0,
        useGrouping: true, // Intl.NumberFormat secara default mengaktifkan ini
      };

      if (prefix.trim().toLowerCase() === "rp") {
        options.style = "currency";
        options.currency = "IDR";
        return new Intl.NumberFormat("id-ID", options).format(number);
      }

      // Perbaikan untuk memastikan koma tidak muncul jika tidak ada desimal
      const formatted = new Intl.NumberFormat("id-ID", options).format(number);
      return `${prefix}${formatted}`;
    }

    /**
     * Mengurai string yang diformat kembali menjadi angka murni.
     * @param {string} formattedString String yang akan diurai.
     * @returns {number} Angka murni.
     */
    /* static parse(formattedString) {
      return parseFloat(
        String(formattedString)
          .replace(/[^0-9,-]/g, "")
          .replace(/\./g, "")
          .replace(/,/g, ".")
      );
    } */
    static parse(formattedString) {
      // --- Perbaikan di Sini ---
      return parseFloat(
        String(formattedString)
          // Hapus semua karakter non-angka, non-koma, dan non-titik
          .replace(/[^0-9.,]/g, "")
          // Hapus titik jika diikuti oleh 3 digit (pemisah ribuan)
          .replace(/\.(?=\d{3})/g, "")
          // Ganti koma dengan titik untuk konversi parseFloat
          .replace(/,/g, "."),
      );
    }
  }

  /**
   * Class untuk menerapkan input mask pada elemen input secara real-time,
   * berdasarkan atribut data-*.
   */
  class InputMask {
    /**
     * @param {string} selector - Selector CSS untuk elemen input.
     */
    constructor(selector) {
      this.elements = document.querySelectorAll(selector);
      this.elements.forEach((element) => {
        this.applyMask(element);
      });
    }

    applyMask(element) {
      const type = element.dataset.maskType || "number";
      const prefix = element.dataset.maskPrefix || "";
      const showDecimal = element.dataset.maskShowDecimal === "true" || type === "currency";
      //   console.log(element.dataset);
      element.style.textAlign = element.dataset.maskAlign || "right";

      // Terapkan format awal saat page load
      let initialValue = element.value;
      const parsedValue = NumberFormatter.parse(initialValue);
      if (!isNaN(parsedValue)) {
        element.value = NumberFormatter.format(parsedValue, prefix, showDecimal);
      }

      // Event 'input': Otomatis tambahkan titik, biarkan koma/titik desimal tidak berubah, dan batasi desimal
      element.addEventListener("input", (e) => {
        let value = e.target.value;
        const cursorPosition = e.target.selectionStart;

        // Ambil semua karakter yang valid (angka, titik, koma)
        let cleanedValue = value.replace(/[^\d.,]/g, "");

        // --- Logika perbaikan di sini: ---
        let integerPart;
        let decimalPart;
        const decimalIndex = cleanedValue.indexOf(",");
        if (decimalIndex === -1) {
          // Tidak ada koma, seluruh nilai adalah bagian integer
          integerPart = cleanedValue.replace(/\./g, ""); // Hapus titik ribuan
          decimalPart = "";
        } else {
          // Ada koma, pisahkan integer dan desimal
          integerPart = cleanedValue.substring(0, decimalIndex).replace(/\./g, "");
          decimalPart = cleanedValue.substring(decimalIndex + 1);

          // Batasi bagian desimal hanya 2 digit
          if (decimalPart.length > 2) {
            decimalPart = decimalPart.substring(0, 2);
          }
        }

        // Format bagian integer dengan pemisah ribuan
        const formattedIntegerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ".");

        // Gabungkan kembali
        let finalValue = formattedIntegerPart;
        if (decimalPart) {
          finalValue += "," + decimalPart;
        } else if (decimalIndex !== -1) {
          // Jika user baru mengetik koma
          finalValue += ",";
        }

        e.target.value = finalValue;

        // Atur posisi kursor setelah format
        let newCursorPosition = cursorPosition + (finalValue.length - value.length);
        e.target.setSelectionRange(newCursorPosition, newCursorPosition);
      });

      // Event 'focus': Hapus semua pemformatan (titik dan koma)
      element.addEventListener("focus", (e) => {
        const parsed = NumberFormatter.parse(e.target.value);
        e.target.value = isNaN(parsed) ? "" : parsed;
      });

      // Event 'blur': Terapkan pemformatan penuh
      element.addEventListener("blur", (e) => {
        const parsed = NumberFormatter.parse(e.target.value);
        e.target.value = isNaN(parsed) ? "" : NumberFormatter.format(parsed, prefix, showDecimal);
      });
    }
  }

  /**
   * Library Validasi Form Vanilla JS
   * Menggunakan atribut data-* untuk validasi.
   */
  class FormValidator {
    constructor(formSelector, options = {}) {
      this.form = document.querySelector(formSelector);
      if (!this.form) {
        console.error("Form tidak ditemukan:", formSelector);
        return;
      }

      this.form.setAttribute("novalidate", "");

      this.messages = {
        required: "Bidang ini wajib diisi.",
        email: "Harap masukkan alamat email yang valid.",
        number: "Harap masukkan angka yang valid.",
        minlength: (min) => `Harap masukkan setidaknya ${min} karakter.`,
        maxlength: (max) => `Harap masukkan tidak lebih dari ${max} karakter.`,
        min: (min) => `Harap masukkan nilai lebih dari atau sama dengan ${min}.`,
        max: (max) => `Harap masukkan nilai kurang dari atau sama dengan ${max}.`,
        ...options.messages,
      };

      this.init();
    }

    init() {
      this.form.addEventListener("submit", (e) => this.handleValidation(e));
      this.form.querySelectorAll("input, textarea, select").forEach((input) => {
        input.addEventListener("input", () => this.validateField(input));
        input.addEventListener("change", () => this.validateField(input));
      });
    }

    handleValidation(e) {
      let isFormValid = true;
      this.form.querySelectorAll("input, textarea, select").forEach((input) => {
        if (!this.validateField(input)) {
          isFormValid = false;
        }
      });

      if (!isFormValid) {
        e.preventDefault();
        e.stopPropagation();
        this.form.classList.add("was-validated");
      } else {
        this.form.classList.remove("was-validated");
      }
    }

    validateField(input) {
      const value = input.value.trim();
      let isValid = true;
      let errorMessage = "";

      const validationRules = [
        () => input.hasAttribute("required") && value === "",
        () => input.type === "email" && value !== "" && !/^[\w-]+(\.[\w-]+)*@([\w-]+\.)+[a-zA-Z]{2,7}$/.test(value),
        () => input.type === "number" && value !== "" && isNaN(parseFloat(value)),
        () => input.dataset.minlength && value.length < parseInt(input.dataset.minlength),
        () => input.dataset.maxlength && value.length > parseInt(input.dataset.maxlength),
        () => input.dataset.min && parseFloat(value) < parseFloat(input.dataset.min),
        () => input.dataset.max && parseFloat(value) > parseFloat(input.dataset.max),
      ];

      const failedRule = validationRules.findIndex((rule) => rule());

      if (failedRule !== -1) {
        isValid = false;
        const errorMessages = [
          this.messages.required,
          this.messages.email,
          this.messages.number,
          this.messages.minlength(input.dataset.minlength),
          this.messages.maxlength(input.dataset.maxlength),
          this.messages.min(input.dataset.min),
          this.messages.max(input.dataset.max),
        ];
        errorMessage = errorMessages[failedRule];
      }

      this.setValidationState(input, isValid, errorMessage);
      return isValid;
    }

    setValidationState(input, isValid, message) {
      const feedbackElement = input.nextElementSibling;

      if (isValid) {
        input.classList.remove("is-invalid");
        input.classList.add("is-valid");
      } else {
        input.classList.remove("is-valid");
        input.classList.add("is-invalid");
        if (feedbackElement && feedbackElement.classList.contains("invalid-feedback")) {
          feedbackElement.textContent = message;
        }
      }
    }
  }

  class CurrencyInput {
    constructor(elements, options = {}) {
      // Cek apakah 'elements' adalah string, elemen tunggal, atau NodeList
      if (typeof elements === "string") {
        this.inputs = document.querySelectorAll(elements);
      } else if (elements instanceof NodeList || Array.isArray(elements)) {
        this.inputs = elements;
      } else if (elements instanceof HTMLElement) {
        this.inputs = [elements]; // Bungkus elemen tunggal dalam array
      } else {
        console.error("Invalid selector or element provided to CurrencyInput.");
        return;
      }

      this.prefix = options.prefix || "";
      this.formatter = new Intl.NumberFormat("id-ID", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
      this.intFormatter = new Intl.NumberFormat("id-ID", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      });

      this.init();
    }

    init() {
      this.inputs.forEach((input) => {
        const maskAlign = input.getAttribute("data-mask-align");

        // Atur textAlign. Default ke 'right' kecuali jika data-mask-align="left"
        if (maskAlign === "left") {
          input.style.textAlign = "left";
        } else {
          input.style.textAlign = "right";
        }

        // input.style.textAlign = "right";
        if (!input.hiddenInput) {
          const hidden = document.createElement("input");
          hidden.type = "hidden";
          hidden.name = input.name + "_raw";
          input.parentNode.insertBefore(hidden, input.nextSibling);
          input.hiddenInput = hidden;
        }
        input.addEventListener("input", (e) => this.onInput(e));
        input.addEventListener("focus", (e) => this.onFocus(e));
        input.addEventListener("blur", (e) => this.onBlur(e));
      });
    }

    getRawValue(value) {
      if (!value) return "";
      value = value.replace(this.prefix, "").replace(/\./g, "").replace(/,/g, ".");
      return value || "";
    }
    // Saat mengetik (Perbaikan)
    onInput(e) {
      let value = e.target.value.replace(this.prefix, "").trim();

      // 1. Bersihkan nilai: hanya biarkan angka dan koma (pemisah desimal)
      value = value.replace(/[^\d,]/g, "");

      // 2. Batasi hanya satu koma desimal
      if (value.indexOf(",") !== value.lastIndexOf(",")) {
        // Jika ada lebih dari satu koma, hapus koma kedua dan seterusnya
        const parts = value.split(",");
        value = parts[0] + "," + parts.slice(1).join("");
      }

      let [intPart, decimalPart] = value.split(",");

      // Konversi integer part menjadi angka (mengabaikan titik ribuan yang mungkin ada)
      // Kemudian konversi kembali menjadi string untuk dibersihkan dari titik ribuan
      let cleanIntString = intPart.replace(/\./g, "");
      let intNumber = parseInt(cleanIntString || "0", 10);

      // 3. Format integer part dengan pemisah ribuan
      let formattedInt = this.intFormatter.format(intNumber);

      // 4. Gabungkan kembali
      let newValue;
      if (decimalPart !== undefined) {
        // Jika sedang mengetik desimal, tambahkan koma dan bagian desimal yang belum diformat
        newValue = `${formattedInt},${decimalPart}`;
      } else if (value.endsWith(",")) {
        // Jika input diakhiri hanya dengan koma
        newValue = `${formattedInt},`;
      } else {
        // Hanya bagian integer yang sudah diformat
        newValue = formattedInt;
      }

      e.target.value = this.prefix + newValue;

      // 5. Update hidden input dengan nilai mentah (float-ready)
      if (e.target.hiddenInput) {
        let raw = newValue.replace(/\./g, "").replace(/,/g, ".");
        e.target.hiddenInput.value = raw ? parseFloat(raw).toFixed(2) : "";
      }
    }
    // Saat mengetik
    /* onInput(e) {
      let value = e.target.value.replace(this.prefix, "");
      value = value.replace(/[^\d,]/g, ""); // hanya angka & koma

      let [intPart, decimalPart] = value.split(",");
      let intNumber = parseInt(intPart || "0", 10);

      // Format ulang integer part → ribuan selalu muncul
      let formattedInt = this.intFormatter.format(intNumber);

      // Gabung dengan decimal part apa adanya
      e.target.value = this.prefix + (decimalPart !== undefined ? `${formattedInt},${decimalPart}` : formattedInt);

      // Update hidden
      if (e.target.hiddenInput) {
        let raw = this.getRawValue(e.target.value);
        e.target.hiddenInput.value = raw ? parseFloat(raw).toFixed(2) : "";
      }
    } */
    /* onInput(e) {
        let rawValue = this.getRawValue(e.target.value);

        // Jika ada lebih dari satu koma, perbaiki
        if ((rawValue.match(/\./g) || []).length > 1) {
            const parts = rawValue.split('.');
            rawValue = parts[0] + '.' + parts.slice(1).join('');
        }

        let formattedValue = this.formatNumber(rawValue, false);
        e.target.value = this.prefix + formattedValue;

        // Update hidden input
        if (e.target.hiddenInput) {
            e.target.hiddenInput.value = rawValue ? parseFloat(rawValue).toFixed(2) : "";
        }
    } */
    // Saat fokus → tampilkan mentah
    onFocus(e) {
      let rawValue = this.getRawValue(e.target.value);
      // Periksa jika nilai mentah adalah '0' atau '0.00'
      if (rawValue == 0) {
        e.target.value = ""; // Mengosongkan input
      } else if (rawValue) {
        e.target.value = rawValue.replace(".", ",");
      } else {
        e.target.value = "";
      }
    }

    // Saat blur → format penuh
    onBlur(e) {
      let raw = this.getRawValue(e.target.value);
      if (!raw || raw == 0) {
        e.target.value = this.prefix + "0";
        if (e.target.hiddenInput) {
          e.target.hiddenInput.value = "0.00";
        }
      } else if (raw && !isNaN(raw)) {
        const number = parseFloat(raw);
        let formatted = this.formatter.format(number);

        // kalau desimal ,00 → buang
        if (formatted.endsWith(",00")) {
          formatted = formatted.slice(0, -3);
        }

        e.target.value = this.prefix + formatted;
        if (e.target.hiddenInput) {
          e.target.hiddenInput.value = number.toFixed(2);
        }
      } else {
        e.target.value = "";
        if (e.target.hiddenInput) e.target.hiddenInput.value = "";
      }
    }

    // 🔹 tambahan → format untuk text/td (bukan input)
    formatDisplay(value, prefix = "") {
      if (value == null || value === "") return "";
      const number = parseFloat(value);
      if (isNaN(number)) return "";

      let formatted = this.formatter.format(number);
      if (formatted.endsWith(",00")) {
        formatted = formatted.slice(0, -3);
      }

      // pakai global kalau ada, kalau kosong ambil dari argumen
      const usedPrefix = this.prefix && this.prefix.trim() !== "" ? this.prefix.trim() : prefix;

      return `<div style="display: flex; justify-content: space-between; width: 100%;"><span>${usedPrefix}</span><span>${formatted}</span></div>`;
    }

    rupiah(value, prefix = "") {
      if (value == null || value === "") return "";
      const number = parseFloat(value);
      if (isNaN(number)) return "";
      let formatted = this.formatter.format(number);
      if (formatted.endsWith(",00")) {
        formatted = formatted.slice(0, -3);
      }
      const usedPrefix = this.prefix && this.prefix.trim() !== "" ? this.prefix.trim() : prefix;

      return usedPrefix + formatted;
    }
  }

  class SimpleDataTable {
    constructor(tableEl, options = {}) {
      this.table = tableEl;
      this.tbody = this.table.querySelector("tbody");

      this.options = Object.assign(
        {
          perPage: 10,
          iDisplayLength: null,
          lengthMenu: [
            [10, 25, 50, 100],
            [10, 25, 50, 100],
          ],
          order: null,
          columnDefs: [],
          ajax: null,
          columns: [],
          onEdit: null,
          onDelete: null,
          onBulkDelete: null,
          filterDate: false,
          dateFilterContainer: null,
          exportUrls: {},
          selectors: {
            pageSizeSelect: "#pageSizeSelect",
            searchBox: "#searchBox",
            pagination: "#pagination",
            info: "#info",
            refreshBtn: "#btnRefresh",
            // showGrandTotalCheckbox: "#showGrandTotal",
          },
          onLoaded: null,
          serverSide: false,
          defaultSort: { col: 1, dir: "asc" },
        },
        options,
      );

      this.pageSizeSelect = document.querySelector(this.options.selectors.pageSizeSelect);
      this.searchBox = document.querySelector(this.options.selectors.searchBox);
      this.pagination = document.querySelector(this.options.selectors.pagination);
      this.info = document.querySelector(this.options.selectors.info);
      this.refreshBtn = document.querySelector(this.options.selectors.refreshBtn);
      //   this.showGrandTotalCheckbox = document.querySelector(this.options.selectors.showGrandTotalCheckbox);

      this.currentPage = 1;
      this.totalRows = 0;
      this.rows = [];
      this.loading = false;
      this.currentSortBy = this.options.defaultSort?.col ?? null;
      this.currentSortDir = this.options.defaultSort?.dir ?? null;

      if (this.options.iDisplayLength) {
        this.options.perPage = this.options.iDisplayLength;
      }

      if (this.options.ajax && typeof this.options.ajax === "object") {
        const originalData = this.options.ajax.data;
        this.options.ajax.data = () => {
          const baseData = typeof originalData === "function" ? originalData() : originalData;
          return { ...baseData, ...this._prepareAjaxData() };
        };
      } else {
        this.options.ajax.data = this._prepareAjaxData.bind(this);
      }

      this.initHeader();
      this.applyColumnDefs();
      this.bindEvents();
      this.injectSortStyles();

      if (this.options.filterDate && this.options.dateFilterContainer) {
        this._initDateFilter();
      }
      SimpleDataTable.instances = SimpleDataTable.instances || [];
      SimpleDataTable.instances.push(this);

      // Bind global click handler untuk edit/delete row
      this._bindRowActions();
      this.loadData();
    }
    _bindRowActions() {
      document.addEventListener("click", (e) => {
        const target = e.target.closest("a, button");
        if (!target) return;

        // Pastikan tombol ada di tabel ini
        if (!this.table.contains(target)) return;

        if (target.classList.contains("btn-edit") && target.dataset.id) {
          if (typeof this.options.onEdit === "function") {
            e.preventDefault();
            this.options.onEdit(target.dataset.id);
          }
        } else if (target.classList.contains("btn-delete") && target.dataset.id) {
          if (typeof this.options.onDelete === "function") {
            e.preventDefault();
            this.options.onDelete(target.dataset.id);
          }
        } else if (target.classList.contains("btn-remove-row")) {
          const row = target.closest("tr");
          const selectEl = row.querySelector(".account-select");
          if (selectEl && selectEl.choices) selectEl.choices.destroy();
          row.remove();
        }
      });
    }
    _prepareAjaxData() {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
      const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.getAttribute("content");

      const data = {
        length: this.options.perPage,
        start: (this.currentPage - 1) * this.options.perPage,
        search: this.searchBox?.value ?? "",
        order: this.currentSortBy ? [[this.currentSortBy, this.currentSortDir]] : null,
      };

      if (csrfHeader && csrfToken) {
        data[csrfHeader] = csrfToken;
      }

      if (this.options.filterDate) {
        const isManual = document.getElementById("manualInputs")?.classList.contains("d-flex");
        const startDate = isManual ? document.getElementById("startDateManual").value : document.getElementById("startDateHidden").value;
        const endDate = isManual ? document.getElementById("endDateManual").value : document.getElementById("endDateHidden").value;
        Object.assign(data, {
          start_date: startDate,
          end_date: endDate,
        });
      }
      return data;
    }

    initHeader() {
      if (!this.table.querySelector("thead")) {
        const thead = document.createElement("thead");
        const tr = document.createElement("tr");

        if (!this.options.order || this.options.order.length === 0) {
          this.options.order = this.options.defaultSort ? [[this.options.defaultSort.col, this.options.defaultSort.dir]] : [];
        }

        this.options.columns.forEach((col, colIdx) => {
          const th = document.createElement("th");
          th.textContent = col.label || "";
          if (col.thClass) th.classList.add(...col.thClass.split(" "));
          const globalSortable = this.options.sortable !== false;
          const colOrderable = col.orderable !== false;

          if (globalSortable && colOrderable) {
            th.classList.add("sortableDatatable");
            let defaultOrder = this.options.order?.[0];
            if (defaultOrder && defaultOrder[0] === colIdx) {
              this.currentSortBy = colIdx;
              this.currentSortDir = defaultOrder[1] || "asc";
              th.classList.add(this.currentSortDir === "asc" ? "sortedDatatable-asc" : "sortedDatatable-desc");
            }
            th.addEventListener("click", () => this.handleSort(colIdx));
          }
          tr.appendChild(th);
        });
        thead.appendChild(tr);
        this.table.insertBefore(thead, this.tbody);
      }
    }

    handleSort(colIdx) {
      if (this.currentSortBy === colIdx) {
        this.currentSortDir = this.currentSortDir === "asc" ? "desc" : "asc";
      } else {
        this.currentSortBy = colIdx;
        this.currentSortDir = "asc";
      }

      const ths = this.table.querySelectorAll("thead th");
      ths.forEach((th) => th.classList.remove("sortedDatatable-asc", "sortedDatatable-desc"));
      ths[colIdx].classList.add(this.currentSortDir === "asc" ? "sortedDatatable-asc" : "sortedDatatable-desc");
      this.loadData();
    }

    injectSortStyles() {
      if (document.getElementById("datatable-sort-style")) return;
      const css = `
        .sortableDatatable { cursor: pointer; }
        .sortableDatatable::after {
            content: "⇅";
            font-size: 0.8em;
            margin-right: 4px;
            float:right;
            color: #ccc;
        }
        .sortedDatatable-asc::after {
            content: "↑";
            color: #000;
        }
        .sortedDatatable-desc::after {
            content: "↓";
            color: #000;
        }
        `;
      const style = document.createElement("style");
      style.id = "datatable-sort-style";
      style.textContent = css;
      document.head.appendChild(style);
    }

    applyColumnDefs() {
      if (!this.options.columnDefs.length) return;
      const ths = this.table.querySelectorAll("thead th");

      this.options.columnDefs.forEach((def) => {
        let targets = def.targets;
        if (!Array.isArray(targets)) targets = [targets];

        targets.forEach((t) => {
          const index = t < 0 ? ths.length + t : t;
          const th = ths[index];
          if (!th) return;

          // width
          if (def.width) th.style.width = def.width;

          // className
          if (def.className) th.classList.add(def.className);

          // orderable
          if (typeof def.orderable !== "undefined") {
            if (def.orderable === false) {
              th.dataset.orderable = "false";
              th.classList.remove("sortableDatatable"); // hapus indikator panah
            } else {
              th.dataset.orderable = "true";
              th.classList.add("sortableDatatable");
            }
          }
        });
      });
    }

    bindEvents() {
      if (this.pageSizeSelect) {
        this.pageSizeSelect.addEventListener("change", () => {
          this.options.perPage = parseInt(this.pageSizeSelect.value, 10);
          this.currentPage = 1;
          this.loadData();
        });
      }

      if (this.searchBox) {
        const debouncedLoadData = this.debounce(() => {
          this.currentPage = 1;
          this.loadData();
        }, 300);
        this.searchBox.addEventListener("keyup", debouncedLoadData);
      }

      if (this.refreshBtn) {
        this.refreshBtn.addEventListener("click", () => {
          this.loadData();
        });
      }
    }
    _createSizeSelect() {
      if (!this.pageSizeSelect) return;
      const menuOptions = this.options.lengthMenu[0];
      const menuLabels = this.options.lengthMenu[1];

      this.pageSizeSelect.innerHTML = "";

      for (let i = 0; i < menuOptions.length; i++) {
        const option = document.createElement("option");
        option.value = menuOptions[i] === -1 ? this.totalRows : menuOptions[i];
        option.textContent = menuLabels[i];
        if (Number(this.options.perPage) === Number(menuOptions[i])) {
          option.selected = true;
        }
        this.pageSizeSelect.appendChild(option);
      }
    }
    async loadData(extraParams = {}) {
      if (!this.options.ajax || this.loading) return;
      this.loading = true;
      this.tbody.innerHTML = `<tr><td colspan="${this.options.columns.length}" class="text-center"><i class="bi bi-arrow-clockwise spin me-1"></i> Loading...</td></tr>`;

      const params = {
        page: this.currentPage,
        length: this.options.perPage,
        search: this.searchBox ? this.searchBox.value : "",
        orderBy: this.currentSortBy !== null ? this.options.columns[this.currentSortBy]?.data : "",
        orderDir: this.currentSortDir || "",
        ...extraParams,
      };

      try {
        const ajaxOpts = typeof this.options.ajax === "string" ? { url: this.options.ajax } : this.options.ajax;
        const url = ajaxOpts.url;
        const type = (ajaxOpts.type || "GET").toUpperCase();
        const extraData = ajaxOpts.data ? ajaxOpts.data() : {};

        let fetchOpts = {
          method: type,
          headers: {
            "X-Requested-With": "XMLHttpRequest",
          },
        };

        if (type === "POST") {
          const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.getAttribute("content");
          const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
          if (csrfHeader && csrfToken) {
            fetchOpts.headers[csrfHeader] = csrfToken;
          }

          if (ajaxOpts.contentType === "application/json" || !ajaxOpts.contentType) {
            fetchOpts.headers["Content-Type"] = "application/json";
            fetchOpts.body = JSON.stringify({ ...extraData, ...params });
          } else {
            fetchOpts.headers["Content-Type"] = "application/x-www-form-urlencoded";
            fetchOpts.body = new URLSearchParams({ ...extraData, ...params });
          }
        } else {
          const qs = new URLSearchParams({ ...extraData, ...params }).toString();
          ajaxOpts.url = url + (url.includes("?") ? "&" : "?") + qs;
        }

        const res = await fetch(ajaxOpts.url, fetchOpts);
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);

        let json;
        try {
          let text = await res.text();
          if (typeof ajaxOpts.dataFilter === "function") {
            text = ajaxOpts.dataFilter(text);
          }
          json = JSON.parse(text);
        } catch (err) {
          console.error("⚠️ Error parsing JSON:", err);
          json = { data: [], recordsTotal: 0 };
        }

        this.totalRows = json.recordsTotal ?? json.total ?? 0;
        this.rows = json.data ?? [];
        this.render();

        if (this.options.onLoaded) this.options.onLoaded(this.rows);
      } catch (error) {
        console.error("⚠️ Gagal load data:", error);
        this.tbody.innerHTML = `<tr><td colspan="${this.options.columns.length}" class="text-center text-danger">Gagal memuat data.</td></tr>`;
      } finally {
        this.loading = false;
      }
    }

    render() {
      if (!this.rows.length) {
        this.tbody.innerHTML = `<tr><td colspan="${this.options.columns.length}" class="text-center">Tidak ada data.</td></tr>`;
      } else {
        this.renderRows(this.rows);
      }
      this._createSizeSelect();
      this.updatePagination();
      this.updateInfo();
    }

    renderRows(data) {
      const tbody = this.tbody;
      tbody.innerHTML = "";

      data.forEach((row, rowIndex) => {
        const tr = document.createElement("tr");

        this.options.columns.forEach((col) => {
          const td = document.createElement("td");
          if (col.className) {
            if (typeof col.className === "function") {
              td.className = col.className(row, rowIndex) || "";
            } else {
              td.className = col.className;
            }
          }
          let content = row[col.data];
          if (col.render) {
            content = typeof col.render === "function" ? col.render(row, rowIndex, this) : content;
          }
          td.innerHTML = content ?? "";
          tr.appendChild(td);
        });
        tbody.appendChild(tr);
      });
    }

    updatePagination() {
      const totalPages = Math.ceil(this.totalRows / this.options.perPage) || 1;
      if (!this.pagination) return;
      this.pagination.innerHTML = "";

      const labels = this.options.paginationLabels || {
        first: '<i class="bi bi-skip-start"></i>',
        prev: '<i class="bi bi-skip-backward"></i>',
        next: '<i class="bi bi-skip-forward"></i>',
        last: '<i class="bi bi-skip-end"></i>',
      };

      const createPageItem = (page, label, active = false, disabled = false) => {
        const li = document.createElement("li");
        li.className = `page-item ${active ? "active" : ""} ${disabled ? "disabled" : ""}`;
        li.innerHTML = `<a class="page-link" href="#">${label}</a>`;
        li.addEventListener("click", (e) => {
          e.preventDefault();
          if (!disabled && this.currentPage !== page && page !== null) {
            this.currentPage = page;
            this.loadData();
          }
        });
        return li;
      };

      const maxVisible = 5;
      let start = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
      let end = Math.min(totalPages, start + maxVisible - 1);
      if (end - start < maxVisible - 1) {
        start = Math.max(1, end - maxVisible + 1);
      }

      this.pagination.appendChild(createPageItem(1, labels.first, false, this.currentPage === 1));
      this.pagination.appendChild(createPageItem(this.currentPage - 1, labels.prev, false, this.currentPage === 1));

      if (start > 1) {
        this.pagination.appendChild(createPageItem(1, "1"));
        if (start > 2) {
          this.pagination.appendChild(createPageItem(null, "...", false, true));
        }
      }
      for (let i = start; i <= end; i++) {
        this.pagination.appendChild(createPageItem(i, i, this.currentPage === i));
      }
      if (end < totalPages) {
        if (end < totalPages - 1) {
          this.pagination.appendChild(createPageItem(null, "...", false, true));
        }
        this.pagination.appendChild(createPageItem(totalPages, totalPages));
      }
      this.pagination.appendChild(createPageItem(this.currentPage + 1, labels.next, false, this.currentPage === totalPages));
      this.pagination.appendChild(createPageItem(totalPages, labels.last, false, this.currentPage === totalPages));
    }

    updateInfo() {
      if (!this.info) return;
      const start = (this.currentPage - 1) * this.options.perPage + 1;
      const end = Math.min(this.currentPage * this.options.perPage, this.totalRows);
      this.info.innerText = `Showing ${start} to ${end} of ${this.totalRows} entries`;
    }

    debounce(func, delay) {
      let timeoutId;
      return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
          func.apply(this, args);
        }, delay);
      };
    }

    _createDateFilterElements() {
      const container = document.getElementById(this.options.dateFilterContainer);
      if (!container) {
        console.error(`Container with ID "${this.options.dateFilterContainer}" not found.`);
        return null;
      }

      container.innerHTML = `
                <div id="manualInputs" class="d-none gap-2">
                    <div>
                        <label for="startDateManual" class="form-label">Tanggal Awal</label>
                        <input name="startDateManual" type="date" id="startDateManual" class="form-control">
                    </div>
                    <div>
                        <label for="endDateManual" class="form-label">Tanggal Akhir</label>
                        <input name="endDateManual" type="date" id="endDateManual" class="form-control">
                    </div>
                </div>
                <div class="d-flex align-items-end">
                    <div class="dropdown">
                        <button class="btn btn-light" type="button" id="monthDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-calendar4 me-1"></i>
                            <span id="selectedPeriod"></span>
                            <i class="bi bi-chevron-down ms-1"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="monthDropdown" id="monthMenu"></ul>
                    </div>
                    <div id="manualFilterButton" class="d-flex align-items-end ms-2 d-none">
                        <button id="filterDataBtn" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                    </div>
                </div>
            `;
      return {
        startDateManual: document.getElementById("startDateManual"),
        endDateManual: document.getElementById("endDateManual"),
        monthMenu: document.getElementById("monthMenu"),
        manualInputs: document.getElementById("manualInputs"),
        manualFilterButton: document.getElementById("manualFilterButton"),
        selectedPeriod: document.getElementById("selectedPeriod"),
        filterDataBtn: document.getElementById("filterDataBtn"),
      };
    }

    _initDateFilter() {
      const filterElements = this._createDateFilterElements();
      if (!filterElements) return;

      const startDateHidden = document.createElement("input");
      startDateHidden.type = "hidden";
      startDateHidden.id = "startDateHidden";
      const endDateHidden = document.createElement("input");
      endDateHidden.type = "hidden";
      endDateHidden.id = "endDateHidden";
      document.body.appendChild(startDateHidden);
      document.body.appendChild(endDateHidden);

      let current = new Date();

      const formatPeriod = (date) => date.toLocaleString("id-ID", { month: "long", year: "numeric" });
      const formatDateLocal = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
      };

      const setPeriod = (date) => {
        const firstDay = formatDateLocal(new Date(date.getFullYear(), date.getMonth(), 1));
        const lastDay = formatDateLocal(new Date(date.getFullYear(), date.getMonth() + 1, 0));
        startDateHidden.value = firstDay;
        endDateHidden.value = lastDay;
        filterElements.selectedPeriod.innerText = formatPeriod(date);
        filterElements.manualInputs.classList.remove("d-flex");
        filterElements.manualInputs.classList.add("d-none");
        filterElements.manualFilterButton.classList.remove("d-flex");
        filterElements.manualFilterButton.classList.add("d-none");
      };

      const buildMenu = () => {
        const prev = new Date(current.getFullYear(), current.getMonth() - 1, 1);
        const next = new Date(current.getFullYear(), current.getMonth() + 1, 1);
        const thisMonth = new Date();
        filterElements.monthMenu.innerHTML = `
                    <li><a class="dropdown-item" href="javascript:void(0)" data-action="prev"><i class="bi bi-chevron-left me-1"></i> ${formatPeriod(prev)}</a></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" data-action="this"><i class="bi bi-calendar3 me-1"></i> ${formatPeriod(thisMonth)}</a></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" data-action="next"><i class="bi bi-chevron-right me-1"></i> ${formatPeriod(next)}</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-primary" href="javascript:void(0)" id="manualPeriod"><i class="bi bi-calendar4 me-1"></i> Filter Periode</a></li>
                    <li><a class="dropdown-item text-danger" href="javascript:void(0)" data-action="reset"><i class="bi bi-refresh me-1"></i> Reset ke bulan ini</a></li>
                `;
      };

      filterElements.monthMenu.addEventListener("click", (e) => {
        e.preventDefault();
        const action = e.target.closest("a")?.dataset.action;
        const targetId = e.target.closest("a")?.id;
        if (action) {
          if (action === "prev") current.setMonth(current.getMonth() - 1);
          else if (action === "next") current.setMonth(current.getMonth() + 1);
          else if (action === "this" || action === "reset") current = new Date();
          setPeriod(current);
          buildMenu();
          this.loadData();
        } else if (targetId === "manualPeriod") {
          filterElements.manualInputs.classList.remove("d-none");
          filterElements.manualInputs.classList.add("d-flex");
          filterElements.manualFilterButton.classList.remove("d-none");
          filterElements.manualFilterButton.classList.add("d-flex");
          filterElements.selectedPeriod.innerText = "Manual Periode";
          const now = new Date();
          filterElements.startDateManual.value = formatDateLocal(new Date(now.getFullYear(), now.getMonth(), 1));
          filterElements.endDateManual.value = formatDateLocal(new Date(now.getFullYear(), now.getMonth() + 1, 0));
        }
      });

      filterElements.filterDataBtn.addEventListener("click", () => {
        this.loadData();
      });

      if (this.options.exportUrls.excel) {
        document.getElementById("btnExportExcel")?.addEventListener("click", () => {
          const isManual = filterElements.manualInputs.classList.contains("d-flex");
          const start = isManual ? filterElements.startDateManual.value : startDateHidden.value;
          const end = isManual ? filterElements.endDateManual.value : endDateHidden.value;
          const url = `${this.options.exportUrls.excel}?start_date=${start}&end_date=${end}`;
          window.open(url, "_blank");
        });
      }

      if (this.options.exportUrls.pdf) {
        document.getElementById("btnExportPdf")?.addEventListener("click", () => {
          const isManual = filterElements.manualInputs.classList.contains("d-flex");
          const start = isManual ? filterElements.startDateManual.value : startDateHidden.value;
          const end = isManual ? filterElements.endDateManual.value : endDateHidden.value;
          const url = `${this.options.exportUrls.pdf}?start_date=${start}&end_date=${end}`;
          window.open(url, "_blank");
        });
      }

      setPeriod(current);
      buildMenu();
    }
  }

  class Rupiah {
    constructor(selector, cfg = {}) {
      this.inputs = document.querySelectorAll(selector);
      this.config = {
        prefix: "Rp ",
        fraction: 2,
        thousand: ".",
        decimal: ",",
        liveThousand: false, // Default: live formatting aktif
        ...cfg,
      };
      this.init();
    }

    init() {
      this.inputs.forEach((el) => {
        let cfg = {};
        try {
          cfg = JSON.parse(el.dataset.mask || "{}");
        } catch (e) {
          console.warn("data-mask bukan JSON valid:", e);
        }
        el.rupiahConfig = { ...this.config, ...cfg };

        // Buat hidden input (logika sudah stabil)
        if (!el.dataset.bind) {
          const hidden = document.createElement("input");
          hidden.type = "hidden";
          hidden.name = el.name;
          el.removeAttribute("hidden");
          el.after(hidden);
          el.dataset.bind = hidden.name;
          el.hiddenInput = hidden;
        } else {
          const hiddenEl = el.form.querySelector(`[name="${el.dataset.bind}"]`);
          el.hiddenInput = hiddenEl; // Asumsi hiddenEl adalah HTMLInputElement
        }

        el.style.textAlign = el.getAttribute("data-mask-align") === "left" ? "left" : "right";

        el.addEventListener("input", (e) => this.onInput(e));
        el.addEventListener("blur", (e) => this.onBlur(e));
        el.addEventListener("focus", (e) => this.onFocus(e));

        this.formatInput(el); // Format nilai awal
      });
    }

    // --- UTILITY FUNCTIONS ---

    // Fungsi pembantu untuk mengembalikan posisi kursor (Kritis untuk UX)
    restoreCursorPosition(el, oldVal, newVal) {
      if (!el.setSelectionRange || el.rupiahConfig.liveThousand === false) return;

      let cursor = el.selectionStart;
      const thousandChar = el.rupiahConfig.thousand;

      // Perhitungan panjang string untuk mendapatkan pergeseran netto
      const oldLength = oldVal.length;
      const newLength = newVal.length;

      const lengthDifference = newLength - oldLength;
      let newCursorPos = cursor + lengthDifference;

      // --- LOGIKA KOMPENSASI DELIMITER ---

      // Jika panjang string bertambah (biasanya karena penambahan titik ribuan)
      if (lengthDifference > 0) {
        // Cek apakah karakter yang ditambahkan tepat di posisi kursor lama adalah titik ribuan
        // Jika kursor berada tepat di depan titik yang baru ditambahkan, kita harus melewati titik itu.

        // Cek karakter di string baru pada posisi kursor lama + panjang perubahan.
        // Jika karakter tersebut adalah titik, itu berarti kursor didorong.

        if (newVal[cursor] === thousandChar) {
          newCursorPos++;
        }
      } else if (lengthDifference < 0) {
        // Jika panjang string berkurang (biasanya karena menghapus titik ribuan)

        // Cek apakah titik ribuan dihapus di belakang kursor
        if (oldVal[cursor - 1] === thousandChar) {
          newCursorPos--;
        }
      }

      // Batasi kursor agar tidak melampaui batas
      if (newCursorPos < 0) {
        newCursorPos = 0;
      } else if (newCursorPos > newVal.length) {
        newCursorPos = newVal.length;
      }

      el.setSelectionRange(newCursorPos, newCursorPos);
    }

    getRaw(value, cfg) {
      cfg = cfg || this.config;
      if (!value) return "";
      let v = value.replace(cfg.prefix, "");
      const thousandRegex = new RegExp("\\" + cfg.thousand, "g");
      v = v.replace(thousandRegex, "");
      if (cfg.decimal !== ".") {
        const decimalRegex = new RegExp("\\" + cfg.decimal, "g");
        v = v.replace(decimalRegex, ".");
      }
      return v.replace(/[^\d.-]/g, "");
    }

    // --- EVENT HANDLERS ---

    onInput(e) {
      const el = e.target;
      const cfg = el.rupiahConfig;
      const oldVal = el.value;

      // 1. Pembersihan & Normalisasi
      let raw = el.value.replace(/[^\d,\.-]/g, "");
      const decimalChar = cfg.decimal;
      const thousandChar = cfg.thousand;

      // Normalisasi: Ubah desimal ke titik, hapus ribuan
      raw = raw.replace(new RegExp("\\" + decimalChar, "g"), "#");
      raw = raw.replace(new RegExp("\\" + thousandChar, "g"), "");
      raw = raw.replace(/#/g, ".");

      // Penanganan Minus dan Desimal Ganda (logika tetap sama)
      const parts = raw.split(".");
      if (parts.length > 2) raw = parts[0] + "." + parts.slice(1).join("");
      if (raw.indexOf("-") > 0) raw = raw.replace(/-/g, "");
      if (raw.lastIndexOf("-") > 0) raw = "-" + raw.replace(/-/g, "");

      // Cek Nilai Kosong/Invalid
      if (raw === "" || raw === "-" || isNaN(parseFloat(raw))) {
        el.hiddenInput.value = "";
        el.value = raw === "-" ? "-" : "";
        this.restoreCursorPosition(el, oldVal, el.value);
        return;
      }

      // 2. Update Hidden Input (nilai bersih yang sudah di-fixed)
      let num = parseFloat(raw);
      el.hiddenInput.value = num.toFixed(cfg.fraction);

      // 3. Live Formatting (Perubahan di sini)
      if (cfg.liveThousand) {
        let displayValue;

        // --- PERBAIKAN: Gunakan nilai raw yang belum di-fixed untuk display, agar koma bisa diketik ---

        // Ambil bagian yang akan ditampilkan sebagai integer
        let intPart = raw.split(".")[0];

        // Format integer part dengan ribuan
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, cfg.thousand);

        // Ambil bagian desimal (jika ada)
        let decPart = raw.split(".")[1];

        // Gabungkan
        if (raw.includes(".")) {
          // Jika desimal sudah diketik (ada titik), gunakan koma desimal visual
          if (decPart && Number(decPart) === 0 && decPart.length === cfg.fraction) {
            displayValue = intPart; // HANYA tampilkan integer part
          } else {
            // Jika sedang mengetik, tampilkan lengkap
            displayValue = intPart + decimalChar + (decPart || "");
          }

          // Potong desimal jika sudah melebihi batas (memungkinkan pengetikan, tapi tidak disimpan)
          if (decPart && decPart.length > cfg.fraction) {
            decPart = decPart.substring(0, cfg.fraction);
            displayValue = intPart + decimalChar + decPart;
          }
        } else {
          // Jika belum ada desimal, hanya tampilkan ribuan integer
          displayValue = intPart;
        }

        // Terapkan prefix jika ada
        if (displayValue !== oldVal) {
          el.value = displayValue;
          this.restoreCursorPosition(el, oldVal, el.value);
        }
      } else {
        // Mode mentah: tampilkan mentah saat fokus
        // this.onFocus(e);
        // this.restoreCursorPosition(el, oldVal, el.value);
      }
    }

    onFocus(e) {
      const el = e.target;
      const cfg = el.rupiahConfig;
      let val = el.hiddenInput.value || "";

      // Tampilkan nilai mentah dari hidden input (tanpa ribuan)
      el.value = val ? val.replace(".", cfg.decimal) : "";
    }

    onBlur(e) {
      const el = e.target;
      const cfg = el.rupiahConfig;

      // Ambil nilai dari hidden input (yang sudah pasti bersih)
      const raw = el.hiddenInput.value || this.getRaw(el.value, cfg);
      let num = parseFloat(raw);

      if (isNaN(num)) {
        el.value = "";
        el.hiddenInput.value = "";
        return;
      }

      // Terapkan format Rupiah Penuh dengan prefix
      el.value = Rupiah.format(num, cfg);
      el.hiddenInput.value = num.toFixed(cfg.fraction);
    }

    // --- STATIC FORMATTER (Bisa digunakan di luar class) ---

    static format(value, cfg = { prefix: "Rp ", fraction: 2, thousand: ".", decimal: "," }) {
      if (value === null || value === undefined || value === "") return "";
      let num = parseFloat(value);
      if (isNaN(num)) return "";

      const sign = num < 0 ? "-" : "";
      num = Math.abs(num);

      let parts = num.toFixed(cfg.fraction).split(".");
      // Format ribuan
      parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, cfg.thousand);

      let formattedValue;
      let decimalPart = parts[1];

      // Hilangkan desimal jika nilainya nol (kecuali fraction = 0)
      if (decimalPart && Number(decimalPart) === 0 && cfg.fraction > 0) {
        formattedValue = parts[0];
      } else {
        formattedValue = parts.join(cfg.decimal);
      }

      return sign + cfg.prefix + formattedValue;
    }

    formatInput(el) {
      const cfg = el.rupiahConfig;
      const raw = el.hiddenInput.value || this.getRaw(el.value, cfg);
      let num = parseFloat(raw);

      if (isNaN(num)) {
        el.value = "";
        el.hiddenInput.value = "";
        return;
      }

      el.value = Rupiah.format(num, cfg);
      el.hiddenInput.value = num.toFixed(cfg.fraction);
    }
  }

  class RupiahCleavePro {
    constructor(selector, cfg = {}) {
      this.inputs = document.querySelectorAll(selector);
      this.config = {
        prefix: "Rp ",
        thousand: ".",
        decimal: ",",
        fraction: 2,
        allowNegative: false,
        ...cfg,
      };
      this.init();
    }

    init() {
      this.inputs.forEach((el) => {
        el.rupiahConfig = { ...this.config };

        // Hidden input untuk backend
        if (!el.dataset.bind) {
          const hidden = document.createElement("input");
          hidden.type = "text";
          hidden.name = el.name;
          el.removeAttribute("name");
          el.after(hidden);
          el.dataset.bind = hidden.name;
          el.hiddenInput = hidden;
        } else {
          const hiddenEl = el.form.querySelector(`[name="${el.dataset.bind}"]`);
          if (hiddenEl) el.hiddenInput = hiddenEl;
          else {
            const hidden = document.createElement("input");
            hidden.type = "text";
            hidden.name = el.dataset.bind;
            el.after(hidden);
            el.hiddenInput = hidden;
          }
        }

        el.style.textAlign = "right";

        // Event listeners
        el.addEventListener("input", (e) => this.onInput(e));
        el.addEventListener("blur", (e) => this.onBlur(e));
        el.addEventListener("focus", (e) => this.onFocus(e));
        el.addEventListener("paste", (e) => this.onPaste(e));

        this.formatInput(el);
      });
    }

    getRaw(value, cfg) {
      if (!value) return "";
      let v = value.replace(cfg.prefix, "");
      const thousandRegex = new RegExp("\\" + cfg.thousand, "g");
      v = v.replace(thousandRegex, "");
      if (cfg.decimal !== ".") {
        const decimalRegex = new RegExp("\\" + cfg.decimal, "g");
        v = v.replace(decimalRegex, ".");
      }
      // Hanya angka, minus, dan titik
      return v.replace(/[^\d.-]/g, "");
    }

    onInput(e) {
      const el = e.target;
      const cfg = el.rupiahConfig;

      const selectionStart = el.selectionStart;
      const oldValue = el.value;

      let raw = this.getRaw(el.value, cfg);
      let isNegative = raw.startsWith("-") && cfg.allowNegative;
      if (!cfg.allowNegative) isNegative = false;
      raw = raw.replace("-", "");

      let num = parseFloat(raw) || 0;
      if (isNegative) num = -num;

      el.hiddenInput.value = num.toFixed(cfg.fraction);

      // Format ribuan
      let [integer, decimal] = Math.abs(num).toFixed(cfg.fraction).split(".");
      integer = integer.replace(/\B(?=(\d{3})+(?!\d))/g, cfg.thousand);

      let newValue = cfg.prefix + (isNegative ? "-" : "") + integer;
      if (cfg.fraction > 0) newValue += cfg.decimal + decimal;

      el.value = newValue;

      // Atur cursor supaya tetap di tempat alami
      let diff = el.value.length - oldValue.length;
      let newPos = selectionStart + diff;
      if (newPos < cfg.prefix.length) newPos = cfg.prefix.length;
      el.setSelectionRange(newPos, newPos);
    }

    onFocus(e) {
      const el = e.target;
      const cfg = el.rupiahConfig;
      const raw = el.hiddenInput.value || "";
      if (!raw) {
        el.value = "";
        return;
      }
      const isNegative = raw.startsWith("-");
      const absRaw = raw.replace("-", "");
      let [integer, decimal] = absRaw.split(".");
      el.value = (isNegative ? "-" : "") + integer + (decimal ? cfg.decimal + decimal : "");
    }

    onBlur(e) {
      this.formatInput(e.target);
    }

    onPaste(e) {
      e.preventDefault();
      const paste = (e.clipboardData || window.clipboardData).getData("text");
      const cleaned = this.getRaw(paste, this.config);
      e.target.value = cleaned;
      this.onInput({ target: e.target });
    }
    normalizeServerValue(str, cfg) {
      if (!str) return 0;
      cfg = cfg || this.config;
      // ubah koma desimal jadi titik
      let v = str.replace(cfg.decimal, ".");
      // hapus ribuan jika ada (titik)
      v = v.replace(new RegExp("\\" + cfg.thousand, "g"), "");
      return parseFloat(v) || 0;
    }
    formatInput(el) {
      const cfg = el.rupiahConfig;
      // pakai normalizeServerValue supaya "50000000,00" dari server jadi number valid
      let num = this.normalizeServerValue(el.hiddenInput.value, cfg);

      if (isNaN(num)) {
        el.value = "";
        el.hiddenInput.value = "";
        return;
      }

      const isNegative = num < 0;
      let [integer, decimal] = Math.abs(num).toFixed(cfg.fraction).split(".");
      integer = integer.replace(/\B(?=(\d{3})+(?!\d))/g, cfg.thousand);

      el.value = cfg.prefix + (isNegative ? "-" : "") + integer + (cfg.fraction > 0 ? cfg.decimal + decimal : "");
      el.hiddenInput.value = num.toFixed(cfg.fraction);
    }

    static format(value, cfg = { prefix: "Rp ", fraction: 2, thousand: ".", decimal: ",", allowNegative: false }) {
      if (value === null || value === undefined || value === "") return "";
      let num = parseFloat(value);
      if (isNaN(num)) return "";
      const isNegative = num < 0;
      let [integer, decimal] = Math.abs(num).toFixed(cfg.fraction).split(".");
      integer = integer.replace(/\B(?=(\d{3})+(?!\d))/g, cfg.thousand);
      return cfg.prefix + (isNegative ? "-" : "") + integer + (cfg.fraction > 0 ? cfg.decimal + decimal : "");
    }
  }

  class RupiahGlobal {
    constructor(selector = ".currency", options = {}) {
      this.config = {
        fraction: 2,
        prefix: "Rp ",
        liveThousand: false,
        ...options,
      };

      this.selector = selector;

      // Init semua input yang ada saat ini
      this.initAll();

      // Auto detect input baru di DOM
      this.observeDOM();
    }

    initAll() {
      document.querySelectorAll(this.selector).forEach((input) => this.initInput(input));
    }

    initInput(input) {
      if (input._rupiahInit) return; // sudah di-init, skip
      input._rupiahInit = true;

      // Hidden input untuk server
      if (!input.hiddenInput) {
        const hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = input.name;
        input.after(hidden);
        input.hiddenInput = hidden;
      }

      // Format awal
      let val = parseFloat(input.value) || 0;
      input.value = numeral(val).format("0,0.00");
      input.hiddenInput.value = val.toFixed(this.config.fraction);

      // Focus: tampilkan mentah
      input.addEventListener("focus", () => {
        let raw = parseFloat(input.hiddenInput.value) || 0;
        input.value = raw || "";
      });

      // Blur: format otomatis
      input.addEventListener("blur", () => {
        let raw = this.normalize(input.value);
        input.value = numeral(raw).format("0,0.00");
        input.hiddenInput.value = raw.toFixed(this.config.fraction);
      });

      // Input: update hidden & live format
      input.addEventListener("input", () => {
        let raw = this.normalize(input.value);
        input.hiddenInput.value = raw.toFixed(this.config.fraction);

        if (this.config.liveThousand) {
          const cursorPos = input.selectionStart;
          input.value = numeral(raw).format("0,0.00");
          input.setSelectionRange(cursorPos, cursorPos);
        }
      });
    }

    normalize(str) {
      if (!str) return 0;
      let v = str.toString().replace(/\./g, "").replace(",", ".");
      return parseFloat(v) || 0;
    }

    observeDOM() {
      const observer = new MutationObserver((mutations) => {
        for (let mutation of mutations) {
          if (!mutation.addedNodes) continue;
          mutation.addedNodes.forEach((node) => {
            if (node.nodeType !== 1) return; // pastikan element
            // jika node itu input currency langsung init
            if (node.matches && node.matches(this.selector)) {
              this.initInput(node);
            }
            // jika node punya child input currency, init semua
            node.querySelectorAll && node.querySelectorAll(this.selector).forEach((input) => this.initInput(input));
          });
        }
      });

      observer.observe(document.body, {
        childList: true,
        subtree: true,
      });
    }
  }

  global.CurrencyInput = CurrencyInput;
  global.SimpleDataTable = SimpleDataTable;
  global.InputMask = InputMask;
  global.NumberFormatter = NumberFormatter;
  global.FormValidator = FormValidator;
  global.RupiahGlobal = RupiahGlobal;
})(window);
// Auto-init
document.addEventListener("DOMContentLoaded", () => {
  new RupiahGlobal(".currency, [data-mask-input]");
  numeral.register("locale", "id", {
    delimiters: { thousands: ".", decimal: "," },
    currency: { symbol: "Rp " },
  });
  numeral.locale("id");
});
//<input type="text" data-mask-input name="usd" value="1234.56"
//data-mask='{"prefix":"$ ","fraction":2,"thousand":",","decimal":"."}'>
// Inisialisasi library saat DOM selesai dimuat
// document.addEventListener("DOMContentLoaded", () => {
// Inisialisasi RupiahFormatter pada semua elemen dengan kelas 'currency'
//   new InputMask("[data-mask-type]");
//   new CurrencyInput(".currency");
//   new CurrencyInput("[data-mask-input]", { prefix: "" });
// Inisialisasi FormValidator pada form dengan ID 'myForm'
//   new FormValidator("#myForm", {
//     messages: {
//       minlength: (min) => `Minimal ${min} karakter.`,
//       email: "Alamat email tidak valid. Cth: nama@domain.com",
//       required: "Tolong isi kolom ini, ya!",
//     },
//   });
// });

function showAlert(message, type = "success", timeout = 5000, title = null, icon = null, position = "top-right") {
  // 1. Definisikan ID unik berdasarkan posisi container
  const containerId = `alert-container-${position}`;
  let container = document.getElementById(containerId);

  if (!container) {
    container = document.createElement("div");
    container.id = containerId;
    container.style.position = "fixed";
    container.style.zIndex = 9999;
    container.style.display = "flex";
    container.style.flexDirection = "column";
    container.style.gap = "0.75rem";
    container.style.maxWidth = "380px";
    container.style.width = "100%";
    container.style.padding = "1rem";
    container.style.pointerEvents = "none";

    switch (position) {
      case "top-left":
        container.style.top = "0";
        container.style.left = "0";
        break;
      case "bottom-right":
        container.style.bottom = "0";
        container.style.right = "0";
        container.style.flexDirection = "column-reverse";
        break;
      case "bottom-left":
        container.style.bottom = "0";
        container.style.left = "0";
        container.style.flexDirection = "column-reverse";
        break;
      case "top-center":
        container.style.top = "0";
        container.style.left = "50%";
        container.style.transform = "translateX(-50%)";
        break;
      case "bottom-center":
        container.style.bottom = "0";
        container.style.left = "50%";
        container.style.transform = "translateX(-50%)";
        container.style.flexDirection = "column-reverse";
        break;
      case "top-right":
      default:
        container.style.top = "0";
        container.style.right = "0";
        break;
    }

    document.body.appendChild(container);
  }

  // 2. Pemetaan ikon default
  const iconMap = {
    success: "ti ti-circle-check",
    danger: "ti ti-circle-x",
    error: "ti ti-circle-x",
    warning: "ti ti-alert-triangle",
    info: "ti ti-info-circle",
  };
  const alertIcon = icon || iconMap[type] || iconMap.info;

  // 3. Warna aksen tema
  const themeColors = {
    success: "#198754",
    danger: "#dc3545",
    error: "#dc3545",
    warning: "#ffc107",
    info: "#0dcaf0",
  };
  const accentColor = themeColors[type] || themeColors.info;

  // 4. Buat elemen alert
  const alert = document.createElement("div");
  alert.className = `alert alert-${type} alert-dismissible fade show shadow-sm border-0 bg-white m-0 overflow-hidden`;
  alert.style.borderRadius = "0.5rem";
  alert.style.borderLeft = `4px solid ${accentColor}`;
  alert.style.transition = "all 0.3s ease-in-out";
  alert.style.pointerEvents = "auto";
  alert.setAttribute("role", "alert");

  // Tampilkan tombol close HANYA JIKA timeout TIDAK bernilai false
  const closeButtonHtml = timeout == false ? `<button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close" style="font-size: 0.75rem;"></button>` : "";

  alert.innerHTML = `
        <div class="d-flex align-items-start p-2">
            <i class="${alertIcon} fs-4 me-3 flex-shrink-0 text-${type}"></i>
            <div class="flex-grow-1 pe-2">
                ${title ? `<h6 class="alert-heading mb-1 fw-bold text-dark fs-6">${title}</h6>` : ""}
                <div class="alert-body text-secondary small lh-sm">${message}</div>
            </div>
            ${closeButtonHtml}
        </div>
        <div class="alert-timer bg-${type}" style="
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            width: 100%;
            opacity: 0.75;
        "></div>
    `;

  container.appendChild(alert);

  const timerBar = alert.querySelector(".alert-timer");

  // 5. Logika Timeout & Animasi Progress Bar
  if (timeout !== false) {
    let startTime = Date.now();
    let remainingTime = timeout;
    let paused = false;
    let animationFrameId;

    function animate() {
      if (paused) return;
      const elapsed = Date.now() - startTime;
      const widthPercent = Math.max(0, 100 - (elapsed / remainingTime) * 100);

      if (timerBar) {
        timerBar.style.width = widthPercent + "%";
      }

      if (elapsed < remainingTime) {
        animationFrameId = requestAnimationFrame(animate);
      } else {
        closeAlert();
      }
    }

    function closeAlert() {
      cancelAnimationFrame(animationFrameId);
      alert.classList.remove("show");
      alert.style.opacity = "0";
      alert.style.transform = "scale(0.95)";
      setTimeout(() => alert.remove(), 300);
    }

    alert.addEventListener("mouseenter", () => {
      paused = true;
      cancelAnimationFrame(animationFrameId);
      remainingTime -= Date.now() - startTime;
    });

    alert.addEventListener("mouseleave", () => {
      if (!paused) return;
      paused = false;
      startTime = Date.now();
      animationFrameId = requestAnimationFrame(animate);
    });

    alert.querySelector(".btn-close")?.addEventListener("click", (e) => {
      e.preventDefault();
      closeAlert();
    });

    animationFrameId = requestAnimationFrame(animate);
  } else {
    if (timerBar) timerBar.style.display = "none";
  }
}

/* function showAlert(message, type = "success", timeout = 5000, title = null, icon = null) {
  // stackable container
  let container = document.getElementById("alert-container");
  if (!container) {
    container = document.createElement("div");
    container.id = "alert-container";
    container.style.position = "fixed";
    container.style.top = "1rem";
    container.style.right = "1rem";
    container.style.zIndex = 9999;
    container.style.display = "flex";
    container.style.flexDirection = "column";
    container.style.gap = "0.5rem";
    document.body.appendChild(container);
  }

  // default icon mapping
  const iconMap = {
    success: "ti ti-checks",
    danger: "ti ti-alert-circle",
    error: "ti ti-alert-circle",
    warning: "ti ti-alert-triangle",
    info: "ti ti-info-circle",
  };
  const alertIcon = icon || iconMap[type] || iconMap.info;

  const alert = document.createElement("div");
  alert.className = `alert alert-${type} alert-dismissible fade show position-relative shadow  z-3 bg-white mb-3`;
  alert.style.minWidth = "300px";
  alert.style.margin = "0";
  alert.style.position = "relative";
  alert.role = "alert";

  alert.innerHTML = `
        <div class="d-flex align-items-start">
            <i class="${alertIcon} fs-5 me-2 flex-shrink-0 text-${type}"></i>
            <div class="flex-grow-1">
                ${title ? `<h6 class="alert-heading mb-1">${title}</h6>` : ""}
                <div class="alert-body">${message}</div>
            </div>
            <button type="button" class="btn-close ms-2" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <div class="alert-timer bg-${type}" style="
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            width: 100%;
        "></div>
    `;

  container.appendChild(alert);

  const timerBar = alert.querySelector(".alert-timer");

  if (timeout !== false) {
    let startTime = Date.now();
    let paused = false;

    function animate() {
      if (paused) return;
      const elapsed = Date.now() - startTime;
      const widthPercent = Math.max(0, 100 - (elapsed / timeout) * 100);
      if (timerBar) timerBar.style.width = widthPercent + "%";

      if (elapsed < timeout) {
        requestAnimationFrame(animate);
      } else {
        closeAlert();
      }
    }

    function closeAlert() {
      alert.classList.remove("show");
      alert.classList.add("hide");
      setTimeout(() => alert.remove(), 500);
    }

    // hover pause/resume
    alert.addEventListener("mouseenter", () => {
      paused = true;
      timeout = timeout - (Date.now() - startTime);
    });
    alert.addEventListener("mouseleave", () => {
      if (!paused) return;
      paused = false;
      startTime = Date.now();
      requestAnimationFrame(animate);
    });

    // manual close
    alert.querySelector(".btn-close")?.addEventListener("click", closeAlert);

    requestAnimationFrame(animate);
  } else {
    // sembunyikan timer jika timeout false
    if (timerBar) timerBar.style.display = "none";
  }
}
 */
