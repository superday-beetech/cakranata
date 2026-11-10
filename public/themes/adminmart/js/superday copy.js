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
          .replace(/,/g, ".")
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

      element.style.textAlign = "right";

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
        input.style.textAlign = "right";
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

    // Saat mengetik
    onInput(e) {
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
    }
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
          order: null, // [[colIndex, 'asc']]
          columnDefs: [], // ex: [{ targets: 0, className: "text-center", width: "5%", orderable: false }]
          ajax: null, // bisa string URL atau function
          ajaxMode: "get",
          columns: [],
          onEdit: null,
          onDelete: null,
          onBulkDelete: null,
          filterDate: false,
          selectors: {
            pageSizeSelect: "#pageSizeSelect",
            searchBox: "#searchBox",
            pagination: "#pagination",
            info: "#info",
            refreshBtn: "#btnRefresh",
            showGrandTotalCheckbox: "#showGrandTotal",
          },
          onLoaded: null,
          serverSide: false,
          defaultSort: { col: 1, dir: "asc" },
        },
        options
      );

      // Inisialisasi elemen-elemen berdasarkan selector
      this.pageSizeSelect = document.querySelector(this.options.selectors.pageSizeSelect);
      this.searchBox = document.querySelector(this.options.selectors.searchBox);
      this.pagination = document.querySelector(this.options.selectors.pagination);
      this.info = document.querySelector(this.options.selectors.info);
      this.refreshBtn = document.querySelector(this.options.selectors.refreshBtn);

      this.currentPage = 1;
      this.totalRows = 0;
      this.rows = [];
      this.loading = false;
      this.currentSortBy = null;
      this.currentSortDir = null;
      this.options.ajax.data = this._prepareAjaxData.bind(this);
      // perPage mengikuti iDisplayLength kalau ada
      if (this.options.filterDate && this.options.dateFilterContainer) {
        this._initDateFilter();
      }

      this.initHeader();
      this.applyColumnDefs();
      this.bindEvents();
      this.injectSortStyles();
      if (this.options.filterDate) {
        this.initDateFilter();
      }
      //   this.applyDefaultSort();
      //   this.loadData();
    }
    injectSortStyles() {
      if (document.getElementById("datatable-sort-style")) return; // biar nggak dobel inject

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
    _prepareAjaxData() {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
      const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.getAttribute("content");

      const data = {
        length: this.options.perPage,
        start: (this.currentPage - 1) * this.options.perPage,
        search: this.searchBox?.value ?? "",
        order: this.currentSortBy ? [[this.currentSortBy, this.currentSortDir]] : null,
      };

      if (csrfTokenName && csrfHash) {
        data[csrfTokenName] = csrfHash;
      }
      // Tambahkan data filter tanggal jika opsi diaktifkan
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
        // fallback order
        if (!this.options.order || this.options.order.length === 0) {
          this.options.order = [[1, "asc"]];
        }

        this.options.columns.forEach((col, colIdx) => {
          const th = document.createElement("th");
          th.textContent = col.label || "";

          if (col.thClass) th.classList.add(...col.thClass.split(" "));

          const globalSortable = this.options.sortable !== false; // default true
          const colOrderable = col.orderable !== false; // default true

          if (globalSortable && colOrderable) {
            th.classList.add("sortableDatatable");

            let defaultOrder = this.options.order?.[0];
            if (defaultOrder && defaultOrder[0] === colIdx) {
              this.sortColumn = colIdx;
              this.sortDirection = defaultOrder[1] || "asc";
              th.classList.add(this.sortDirection === "asc" ? "sortedDatatable-asc" : "sortedDatatable-desc");
            }

            th.addEventListener("click", () => this.handleSort(colIdx));
          }

          tr.appendChild(th);
        });

        thead.appendChild(tr);
        this.table.insertBefore(thead, this.tbody);
      }

      if (this.sortColumn !== null) {
        if (this.options.serverSide) {
          this.loadData(); // biar request awal langsung sorted
        } else {
          this.sortData();
          this.render();
        }
      }

      if (this.pageSizeSelect) {
        const [values, labels] = this.options.lengthMenu;
        this.pageSizeSelect.innerHTML = values
          .map((size, i) => {
            const label = labels[i] ?? size;
            const selected = size === this.options.perPage ? "selected" : "";
            return `<option value="${size}" ${selected}>${label}</option>`;
          })
          .join("");
      }
    }

    handleSort(colIdx) {
      if (this.sortColumn === colIdx) {
        this.sortDirection = this.sortDirection === "asc" ? "desc" : "asc";
      } else {
        this.sortColumn = colIdx;
        this.sortDirection = "asc";
      }

      // update kelas di header
      const ths = this.table.querySelectorAll("thead th");
      ths.forEach((th) => th.classList.remove("sortedDatatable-asc", "sortedDatatable-desc"));
      ths[colIdx].classList.add(this.sortDirection === "asc" ? "sortedDatatable-asc" : "sortedDatatable-desc");

      if (this.options.serverSide) {
        this.loadData(); // request ke server
      } else {
        this.sortData();
        this.render();
      }
    }

    sortData() {
      if (this.sortColumn == null) return;

      const col = this.options.columns[this.sortColumn];
      const key = col.data;

      const isIsoDate = (val) => /^\d{4}-\d{2}-\d{2}$/.test(val);

      this.rows.sort((a, b) => {
        let va = a[key];
        let vb = b[key];

        // 1. deteksi ISO date (YYYY-MM-DD)
        if (typeof va === "string" && isIsoDate(va)) va = new Date(va);
        if (typeof vb === "string" && isIsoDate(vb)) vb = new Date(vb);

        // 2. kalau masih string dan pattern "angka - teks"
        if (typeof va === "string" && /^\d+\s*-\s*/.test(va)) {
          va = va.split("-")[0].trim();
        }
        if (typeof vb === "string" && /^\d+\s*-\s*/.test(vb)) {
          vb = vb.split("-")[0].trim();
        }

        // 3. numeric string → parse number
        if (!isNaN(parseFloat(va)) && !isNaN(parseFloat(vb))) {
          va = parseFloat(va);
          vb = parseFloat(vb);
        }

        // 4. terakhir: kalau masih string biasa → lowercase
        if (typeof va === "string") va = va.toLowerCase();
        if (typeof vb === "string") vb = vb.toLowerCase();

        if (va < vb) return this.sortDirection === "asc" ? -1 : 1;
        if (va > vb) return this.sortDirection === "asc" ? 1 : -1;
        return 0;
      });
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

    async loadData(extraParams = {}) {
      if (!this.options.ajax || this.loading) return;
      this.loading = true;
      this.tbody.innerHTML = `<tr><td colspan="${this.options.columns.length}" class="text-center"><i class="bi bi-arrow-clockwise spin me-1"></i> Loading...</td></tr>`;

      const params = {
        page: this.currentPage,
        length: this.options.perPage,
        search: this.searchBox ? this.searchBox.value : "",
        orderBy: this.sortColumn !== null ? this.options.columns[this.sortColumn].data : "",
        orderDir: this.sortDirection || "",
        ...extraParams,
      };

      try {
        let json;

        // --- CASE 1: ajax function ---
        if (typeof this.options.ajax === "function") {
          json = await this.options.ajax(params);

          // --- CASE 2: ajax string (url langsung) ---
        } else if (typeof this.options.ajax === "string") {
          const qs = new URLSearchParams(params).toString();
          const res = await fetch(this.options.ajax + "?" + qs);
          json = await res.json();

          // --- CASE 3: ajax object ala DataTables ---
        } else if (typeof this.options.ajax === "object") {
          const ajaxOpts = this.options.ajax;
          const url = ajaxOpts.url;
          const type = (ajaxOpts.type || "GET").toUpperCase();
          let extraData = {};
          if (ajaxOpts.data) {
            extraData = typeof ajaxOpts.data === "function" ? ajaxOpts.data() : ajaxOpts.data;
          }

          let fetchOpts = {
            method: type,
            headers: {
              "X-Requested-With": "XMLHttpRequest",
            },
          };

          if (type === "POST") {
            if (ajaxOpts.contentType === "application/json" || !ajaxOpts.contentType) {
              fetchOpts.headers["Content-Type"] = "application/json";
              fetchOpts.body = JSON.stringify({ ...extraData, ...params });
            } else {
              fetchOpts.headers["Content-Type"] = "application/x-www-form-urlencoded";
              fetchOpts.body = new URLSearchParams({ ...extraData, ...params });
            }
          }
          try {
            const res = await fetch(url, fetchOpts);
            let text = await res.text();

            // pakai dataFilter kalau ada
            if (typeof ajaxOpts.dataFilter === "function") {
              text = ajaxOpts.dataFilter(text);
            }

            json = JSON.parse(text);
          } catch (err) {
            if (typeof ajaxOpts.error === "function") {
              // bikin pseudo-jqXHR
              const jqXHR = {
                status: err?.status || 0,
                responseText: err?.responseText || err?.message || "",
              };

              let exception = "error";
              if (err.name === "AbortError") exception = "abort";
              else if (err.name === "TimeoutError") exception = "timeout";
              else if (err instanceof SyntaxError) exception = "parsererror";

              ajaxOpts.error(jqXHR, exception);
            } else {
              console.error("⚠️ Ajax error:", err);
            }
            throw err;
          }
        }

        // --- Normalisasi response ---
        this.totalRows = json.recordsTotal ?? json.total ?? 0;
        this.rows = json.data ?? [];

        // sorting manual kalau ada default order
        if (this.options.order) {
          const [colIndex, dir] = this.options.order[0];
          const colKey = this.options.columns[colIndex].data;
          this.rows.sort((a, b) => {
            const valA = a[colKey] ?? "";
            const valB = b[colKey] ?? "";
            if (valA < valB) return dir === "asc" ? -1 : 1;
            if (valA > valB) return dir === "asc" ? 1 : -1;
            return 0;
          });
        }

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

          // apply className dari kolom (string / function)
          if (col.className) {
            if (typeof col.className === "function") {
              td.className = col.className(row, rowIndex) || "";
            } else {
              td.className = col.className;
            }
          }

          // isi konten
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
      this.pagination.innerHTML = "";

      // ambil label dari options (fallback ke default)
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

  global.CurrencyInput = CurrencyInput;
  global.SimpleDataTable = SimpleDataTable;
  global.InputMask = InputMask;
  global.NumberFormatter = NumberFormatter;
  global.FormValidator = FormValidator;
})(window);

// Inisialisasi library saat DOM selesai dimuat
document.addEventListener("DOMContentLoaded", () => {
  // Inisialisasi RupiahFormatter pada semua elemen dengan kelas 'currency'
  new InputMask("[data-mask-type]");
  new CurrencyInput(".currency");

  // Inisialisasi FormValidator pada form dengan ID 'myForm'
  //   new FormValidator("#myForm", {
  //     messages: {
  //       minlength: (min) => `Minimal ${min} karakter.`,
  //       email: "Alamat email tidak valid. Cth: nama@domain.com",
  //       required: "Tolong isi kolom ini, ya!",
  //     },
  //   });
});

function showAlert(message, type = "success", timeout = 5000, title = null, icon = null) {
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
