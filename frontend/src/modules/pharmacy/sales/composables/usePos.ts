import { computed, ref } from "vue";

import { salesService } from "../services/salesService";

import {
    usePosCalculation,
} from "./usePosCalculation";

import {
    usePosPayment,
} from "./usePosPayment";

import type {
    DiscountMode,
    TaxMode,
    SettlementMode,
    PosAdditionalCharge,
    PaymentMethod,
    PosCartItem,
    PosSummary,
    ProductSearchResult,
    Sale,
    SaleItem,
    CreateSalePayload,
} from "../types/sales";

// =============================================================
// OPTIONS
// =============================================================

interface ProductSearch {
    (keyword: string): Promise<ProductSearchResult[]>;
}

interface UsePosOptions {
    searchProducts: ProductSearch;
    taxRate?: number;
}

// =============================================================
// HELPERS
// =============================================================

function toNumber(value: unknown): number {
    const result = Number(value);
    return Number.isFinite(result) ? result : 0;
}

function roundMoney(value: number): number {
    return Math.round((value + Number.EPSILON) * 100) / 100;
}

function getToday(): string {
    const date = new Date();

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
}

// =============================================================
// COMPOSABLE
// =============================================================

export function usePos(options: UsePosOptions) {
    // =========================================================
    // STATE
    // =========================================================

    const cart = ref<PosCartItem[]>([]);

    const keyword = ref("");
    const searchResults = ref<ProductSearchResult[]>([]);

    const selectedCustomerId = ref<number | null>(null);

    const paymentMethod = ref<PaymentMethod | null>("cash");
    const paymentAmount = ref(0);

    const notes = ref("");

    const loadingSearch = ref(false);
    const processing = ref(false);

    const currentSale = ref<Sale | null>(null);

    const heldTransactions = ref<Sale[]>([]);
    const loadingHeldTransactions = ref(false);

    let searchSequence = 0;

    // =========================================================
    // DISCOUNT STATE
    // =========================================================

    const transactionDiscountMode = ref<DiscountMode>("amount");
    const transactionDiscountValue = ref(0);

    // =========================================================
    // TAX STATE
    // =========================================================

    const taxMode = ref<TaxMode>("percent");
    const taxValue = ref(Math.max(0, toNumber(options.taxRate)));

    // =========================================================
    // ADDITIONAL CHARGES
    // =========================================================

    const additionalCharges = ref<PosAdditionalCharge[]>([]);

    // =========================================================
    // SETTLEMENT STATE
    // =========================================================

    const settlementMode = ref<SettlementMode>("paid");

    const dueDate = ref("");
    const paymentReferenceNumber = ref("");

    // =========================================================
    // TRANSACTION STATE
    // =========================================================

    const isDraft = computed(
        () => currentSale.value?.status === "draft",
    );

    const isPosted = computed(
        () => currentSale.value?.status === "posted",
    );

    const isCancelled = computed(
        () => currentSale.value?.status === "cancelled",
    );

    const transactionLocked = computed(
        () =>
            processing.value ||
            isDraft.value ||
            isPosted.value,
    );

    const canCreateDraft = computed(
        () =>
            cart.value.length > 0 &&
            !processing.value &&
            !currentSale.value,
    );

    // =========================================================
    // CALCULATION COMPOSABLE
    // =========================================================

    const calculation = usePosCalculation({
        items: cart,

        transactionDiscountMode,
        transactionDiscountValue,

        taxMode,
        taxValue,

        additionalCharges,
    });

    const subtotal = computed(() =>
        roundMoney(calculation.subtotal.value),
    );

    const itemDiscount = computed(() =>
        roundMoney(calculation.itemDiscount.value),
    );

    const afterItemDiscount = computed(() =>
        roundMoney(calculation.afterItemDiscount.value),
    );

    const transactionDiscount = computed(() =>
        roundMoney(calculation.transactionDiscount.value),
    );

    const discountTotal = computed(() =>
        roundMoney(calculation.discountTotal.value),
    );

    const dpp = computed(() =>
        roundMoney(calculation.dpp.value),
    );

    const taxAmount = computed(() =>
        roundMoney(calculation.taxAmount.value),
    );

    const additionalChargesTotal = computed(() =>
        roundMoney(calculation.additionalChargesTotal.value),
    );

    const grandTotal = computed(() =>
        roundMoney(calculation.grandTotal.value),
    );

    // =========================================================
    // PAYMENT COMPOSABLE
    // =========================================================

    const payment = usePosPayment({
        total: grandTotal,

        mode: settlementMode,
        paymentMethod,
        paymentAmount,

        dueDate,
        customerId: selectedCustomerId,
    });

    const {
        amountReceived,
        appliedAmount,
        changeAmount,
        remainingAmount,
        receivableAmount,

        isPaid,
        isPartial,
        isReceivable,
        requiresConfirmation,

        validationErrors: paymentValidationErrors,
        isValid: isPaymentValid,
        summary: paymentSummary,

        buildPaymentData,
        payExactAmount,
        resetAmount,
        setDefaultDueDate,
    } = payment;

    // =========================================================
    // POS SUMMARY
    // =========================================================

    const summary = computed<PosSummary>(() => ({
        subtotal: subtotal.value,
        itemDiscount: itemDiscount.value,
        transactionDiscount: transactionDiscount.value,
        discount: discountTotal.value,
        dpp: dpp.value,
        tax: taxAmount.value,
        additionalCharges: additionalChargesTotal.value,
        total: grandTotal.value,

        paid: appliedAmount.value,
        change: changeAmount.value,
        receivable: receivableAmount.value,
    }));

    // =========================================================
    // PAYMENT VALIDATION / PROCESS CONTROL
    // =========================================================

    /**
     * API backend saat ini belum mendukung:
     * - settlement_mode receivable;
     * - due_date;
     * - konfirmasi transfer / QRIS;
     * - pencatatan amount_received dan change_amount terpisah.
     *
     * Karena itu posting hanya diaktifkan untuk pembayaran
     * yang sudah dapat diproses secara aman oleh API lama.
     */
    const canPostSale = computed(() => {
        return (
            isDraft.value &&
            !processing.value &&
            isPaymentValid.value &&
            settlementMode.value === "paid" &&
            !requiresConfirmation.value
        );
    });

    const canProcessPayment = computed(() => {
        return (
            isPaymentValid.value &&
            !processing.value &&
            !transactionLocked.value
        );
    });

    // =========================================================
    // VALIDATION
    // =========================================================

    function ensureEditable(): void {
        if (processing.value) {
            throw new Error("Transaksi sedang diproses.");
        }

        if (currentSale.value) {
            throw new Error(
                "Transaksi sudah disimpan. Buat transaksi baru untuk melanjutkan.",
            );
        }
    }

    function validateQuantity(
        quantity: number,
        productName: string,
    ): void {
        if (!Number.isFinite(quantity) || quantity <= 0) {
            throw new Error(
                `Kuantitas ${productName} tidak valid.`,
            );
        }
    }

    function validateStock(
        item: PosCartItem,
        quantity: number,
    ): void {
        if (item.available_stock === undefined) {
            return;
        }

        const availableStock = toNumber(item.available_stock);

        if (quantity > availableStock) {
            throw new Error(
                `Stok ${item.product_name} tidak mencukupi. Tersedia: ${availableStock}.`,
            );
        }
    }

    // =========================================================
    // PRODUCT SEARCH
    // =========================================================

    async function searchProduct(value?: string): Promise<void> {
        const searchKeyword = (value ?? keyword.value).trim();
        const sequence = ++searchSequence;

        keyword.value = searchKeyword;

        if (!searchKeyword) {
            searchResults.value = [];
            loadingSearch.value = false;
            return;
        }

        loadingSearch.value = true;

        try {
            const results =
                await options.searchProducts(searchKeyword);

            if (sequence !== searchSequence) {
                return;
            }

            searchResults.value = results;
        } finally {
            if (sequence === searchSequence) {
                loadingSearch.value = false;
            }
        }
    }

    function clearSearch(): void {
        searchSequence++;

        keyword.value = "";
        searchResults.value = [];
        loadingSearch.value = false;
    }

    // =========================================================
    // CART OPERATIONS
    // =========================================================

    function addProduct(product: ProductSearchResult): void {
        ensureEditable();

        if (!product.is_active) {
            throw new Error("Produk tidak aktif.");
        }

        const existing = cart.value.find(
            item => item.product_id === product.id,
        );

        if (existing) {
            const nextQuantity = existing.quantity + 1;

            validateStock(existing, nextQuantity);

            existing.quantity = nextQuantity;
        } else {
            const item: PosCartItem = {
                product_id: product.id,
                sku: product.sku,
                barcode: product.barcode ?? null,
                product_name: product.name,
                unit_name: product.unit_name,

                quantity: 1,
                unit_price: toNumber(product.selling_price),

                discount_amount: 0,
                discount_mode: "amount",
                discount_value: 0,

                available_stock:
                    product.available_stock === undefined
                        ? undefined
                        : toNumber(product.available_stock),
            };

            validateStock(item, item.quantity);

            cart.value.push(item);
        }

        clearSearch();
    }

    function updateQuantity(
        productId: number,
        quantity: number,
    ): void {
        ensureEditable();

        const item = cart.value.find(
            row => row.product_id === productId,
        );

        if (!item) return;

        if (!Number.isFinite(quantity) || quantity <= 0) {
            removeProduct(productId);
            return;
        }

        validateStock(item, quantity);

        item.quantity = quantity;

        const maxDiscount = roundMoney(
            item.quantity * item.unit_price,
        );

        if (item.discount_amount > maxDiscount) {
            item.discount_amount = maxDiscount;
            item.discount_value = maxDiscount;
        }
    }

    function updateUnitPrice(
        productId: number,
        price: number,
    ): void {
        ensureEditable();

        const item = cart.value.find(
            row => row.product_id === productId,
        );

        if (!item) return;

        if (!Number.isFinite(price) || price < 0) {
            throw new Error("Harga jual tidak valid.");
        }

        item.unit_price = roundMoney(price);

        const maxDiscount = roundMoney(
            item.quantity * item.unit_price,
        );

        if (item.discount_amount > maxDiscount) {
            item.discount_amount = maxDiscount;
            item.discount_value = maxDiscount;
        }
    }

    /**
     * Update diskon item dalam nominal rupiah.
     * Dipertahankan agar kompatibel dengan CartItem yang sudah ada.
     */
    function updateDiscount(
        productId: number,
        discount: number,
    ): void {
        ensureEditable();

        const item = cart.value.find(
            row => row.product_id === productId,
        );

        if (!item) return;

        if (!Number.isFinite(discount) || discount < 0) {
            throw new Error("Nominal diskon tidak valid.");
        }

        const maxDiscount = roundMoney(
            item.quantity * item.unit_price,
        );

        const amount = roundMoney(
            Math.min(maxDiscount, discount),
        );

        item.discount_mode = "amount";
        item.discount_value = amount;
        item.discount_amount = amount;
    }

    /**
     * Mendukung diskon item nominal atau persentase.
     */
    function updateItemDiscount(
        productId: number,
        mode: DiscountMode,
        value: number,
    ): void {
        ensureEditable();

        const item = cart.value.find(
            row => row.product_id === productId,
        );

        if (!item) return;

        if (!Number.isFinite(value) || value < 0) {
            throw new Error("Nilai diskon tidak valid.");
        }

        const gross = roundMoney(
            item.quantity * item.unit_price,
        );

        const normalizedValue =
            mode === "percent"
                ? Math.min(100, value)
                : Math.min(gross, value);

        const amount =
            mode === "percent"
                ? roundMoney(gross * normalizedValue / 100)
                : roundMoney(normalizedValue);

        item.discount_mode = mode;
        item.discount_value = normalizedValue;
        item.discount_amount = amount;
    }

    function updateItemNotes(
        productId: number,
        value: string,
    ): void {
        ensureEditable();

        const item = cart.value.find(
            row => row.product_id === productId,
        );

        if (!item) return;

        item.notes = value.trim() || null;
    }

    function removeProduct(productId: number): void {
        ensureEditable();

        cart.value = cart.value.filter(
            item => item.product_id !== productId,
        );
    }

    function clearCart(): void {
        ensureEditable();

        cart.value = [];

        paymentAmount.value = 0;
        selectedCustomerId.value = null;
        notes.value = "";

        paymentMethod.value = "cash";
        settlementMode.value = "paid";
        dueDate.value = "";
        paymentReferenceNumber.value = "";

        transactionDiscountMode.value = "amount";
        transactionDiscountValue.value = 0;

        additionalCharges.value = [];

        clearSearch();
    }

    // =========================================================
    // CUSTOMER
    // =========================================================

    function setCustomer(
        customerId: number | string | null,
    ): void {
        ensureEditable();

        if (customerId === null || customerId === "") {
            selectedCustomerId.value = null;
            return;
        }

        const id = Number(customerId);

        if (!Number.isSafeInteger(id) || id <= 0) {
            throw new Error("Pelanggan tidak valid.");
        }

        selectedCustomerId.value = id;
    }

    // =========================================================
    // TRANSACTION DISCOUNT
    // =========================================================

    function setTransactionDiscount(
        mode: DiscountMode,
        value: number,
    ): void {
        ensureEditable();

        if (!Number.isFinite(value) || value < 0) {
            throw new Error("Nilai diskon transaksi tidak valid.");
        }

        transactionDiscountMode.value = mode;
        transactionDiscountValue.value =
            mode === "percent"
                ? Math.min(100, value)
                : value;
    }

    // =========================================================
    // TAX
    // =========================================================

    function setTax(
        mode: TaxMode,
        value: number,
    ): void {
        ensureEditable();

        if (!Number.isFinite(value) || value < 0) {
            throw new Error("Nilai pajak tidak valid.");
        }

        taxMode.value = mode;
        taxValue.value = value;
    }

    // =========================================================
    // ADDITIONAL CHARGES
    // =========================================================

    function addAdditionalCharge(
        charge: PosAdditionalCharge,
    ): void {
        ensureEditable();

        if (!charge.label.trim()) {
            throw new Error("Nama biaya tambahan wajib diisi.");
        }

        if (
            !Number.isFinite(charge.amount) ||
            charge.amount < 0
        ) {
            throw new Error("Nominal biaya tambahan tidak valid.");
        }

        if (additionalCharges.value.some(
            item => item.id === charge.id,
        )) {
            throw new Error("ID biaya tambahan sudah digunakan.");
        }

        additionalCharges.value.push({
            ...charge,
            label: charge.label.trim(),
            amount: roundMoney(charge.amount),
        });
    }

    function updateAdditionalCharge(
        id: string,
        changes: Partial<PosAdditionalCharge>,
    ): void {
        ensureEditable();

        const index = additionalCharges.value.findIndex(
            item => item.id === id,
        );

        if (index === -1) return;

        const current = additionalCharges.value[index];

        const amount =
            changes.amount === undefined
                ? current.amount
                : Number(changes.amount);

        if (!Number.isFinite(amount) || amount < 0) {
            throw new Error("Nominal biaya tambahan tidak valid.");
        }

        additionalCharges.value[index] = {
            ...current,
            ...changes,
            label:
                changes.label !== undefined
                    ? changes.label.trim()
                    : current.label,
            amount: roundMoney(amount),
        };
    }

    function removeAdditionalCharge(id: string): void {
        ensureEditable();

        additionalCharges.value =
            additionalCharges.value.filter(
                item => item.id !== id,
            );
    }

    function clearAdditionalCharges(): void {
        ensureEditable();

        additionalCharges.value = [];
    }

    // =========================================================
    // PAYMENT INPUT
    // =========================================================

    function setPaymentAmount(amount: number): void {
        if (processing.value) return;

        if (!Number.isFinite(amount) || amount < 0) {
            throw new Error("Nominal pembayaran tidak valid.");
        }

        paymentAmount.value = roundMoney(amount);
    }

    function setPaymentMethod(
        method: PaymentMethod | null,
    ): void {
        if (processing.value) return;

        paymentMethod.value = method;
    }

    function setSettlementMode(mode: SettlementMode): void {
        if (processing.value) return;

        settlementMode.value = mode;

        if (mode === "paid") {
            dueDate.value = "";
            paymentMethod.value ??= "cash";

            payExactAmount();

            return;
        }

        if (!dueDate.value) {
            dueDate.value = getToday();
        }

        if (mode === "partial") {
            paymentMethod.value ??= "cash";
            return;
        }

        // Seluruh tagihan menjadi piutang.
        paymentAmount.value = 0;
        paymentMethod.value = null;
    }

    function setDueDate(value: string): void {
        if (processing.value) return;

        dueDate.value = value;
    }

    function setPaymentReferenceNumber(value: string): void {
        if (processing.value) return;

        paymentReferenceNumber.value = value;
    }

    function payExact(): void {
        if (processing.value) return;

        payExactAmount();
    }

    function resetPaymentAmount(): void {
        if (processing.value) return;

        resetAmount();
    }

    // =========================================================
    // RESET TRANSACTION STATE
    // =========================================================

    function resetTransactionState(): void {
        cart.value = [];

        selectedCustomerId.value = null;

        paymentMethod.value = "cash";
        paymentAmount.value = 0;

        settlementMode.value = "paid";
        dueDate.value = "";
        paymentReferenceNumber.value = "";

        notes.value = "";

        transactionDiscountMode.value = "amount";
        transactionDiscountValue.value = 0;

        taxMode.value = "percent";
        taxValue.value = Math.max(0, toNumber(options.taxRate));

        additionalCharges.value = [];

        currentSale.value = null;

        clearSearch();
    }

    // =========================================================
    // BUILD SALE PAYLOAD
    // =========================================================

    function buildSalePayload(): CreateSalePayload {
        if (cart.value.length === 0) {
            throw new Error("Keranjang masih kosong.");
        }

        /**
         * Biaya tambahan belum memiliki tabel/payload backend.
         * Jangan sampai total di layar berbeda dengan total tersimpan.
         */
        if (additionalChargesTotal.value > 0) {
            throw new Error(
                "Biaya tambahan belum dapat disimpan oleh API penjualan. Integrasikan tabel biaya transaksi terlebih dahulu.",
            );
        }

        const items = cart.value.map(item => {
            validateQuantity(
                item.quantity,
                item.product_name,
            );

            validateStock(item, item.quantity);

            const gross = roundMoney(
                item.quantity * item.unit_price,
            );

            const discount = roundMoney(
                item.discount_amount,
            );

            if (
                discount < 0 ||
                discount > gross
            ) {
                throw new Error(
                    `Diskon ${item.product_name} tidak valid.`,
                );
            }

            return {
                product_id: item.product_id,
                quantity: item.quantity,
                unit_price: item.unit_price,
                discount_amount: discount,
            };
        });

        /**
         * Backend SalesService saat ini sudah menerima
         * discount_amount pada header dan tax_amount.
         */
        const payload = {
            customer_id: selectedCustomerId.value,
            discount_amount: transactionDiscount.value,
            tax_amount: taxAmount.value,
            notes: notes.value.trim() || null,
            items,
        };

        return payload as CreateSalePayload;
    }

    // =========================================================
    // CREATE DRAFT
    // =========================================================

    async function createDraft(): Promise<Sale | null> {
        if (processing.value) return null;

        if (currentSale.value) {
            throw new Error(
                "Transaksi sebelumnya belum selesai.",
            );
        }

        processing.value = true;

        try {
            const payload = buildSalePayload();

            currentSale.value =
                await salesService.createSale(payload);

            return currentSale.value;
        } finally {
            processing.value = false;
        }
    }

    // =========================================================
    // POST SALE
    // =========================================================

    async function postCurrentSale(): Promise<Sale | null> {
        if (processing.value) return null;

        if (!currentSale.value) {
            throw new Error("Draft penjualan belum dibuat.");
        }

        if (currentSale.value.status !== "draft") {
            throw new Error(
                "Transaksi bukan berstatus draft.",
            );
        }

        if (!isPaymentValid.value) {
            throw new Error(
                paymentValidationErrors.value[0] ??
                    "Data pembayaran belum valid.",
            );
        }

        /**
         * API lama belum menerima settlement_mode dan due_date.
         * Jangan memposting piutang tanpa menyimpan jatuh temponya.
         */
        if (settlementMode.value !== "paid") {
            throw new Error(
                "Pembayaran tempo belum dapat diposting karena API backend belum mendukung settlement dan tanggal jatuh tempo.",
            );
        }

        /**
         * Transfer dan QRIS membutuhkan konfirmasi.
         * API lama belum menyediakan status pending/confirmed.
         */
        if (requiresConfirmation.value) {
            throw new Error(
                "Pembayaran transfer/QRIS harus dikonfirmasi melalui mekanisme backend sebelum transaksi diposting.",
            );
        }

        if (!paymentMethod.value) {
            throw new Error("Metode pembayaran wajib dipilih.");
        }

        processing.value = true;

        try {
            const paymentData = buildPaymentData();

            currentSale.value = await salesService.postSale(
                currentSale.value.id,
                {
                    // API lama menerima amount yang dialokasikan ke tagihan.
                    amount: paymentData.applied_amount,
                    payment_method: paymentMethod.value,
                    reference_number:
                        paymentReferenceNumber.value.trim() || null,
                },
            );

            return currentSale.value;
        } finally {
            processing.value = false;
        }
    }

    // =========================================================
    // NEW TRANSACTION
    // =========================================================

    function newTransaction(): void {
        if (processing.value) {
            throw new Error("Transaksi sedang diproses.");
        }

        if (
            currentSale.value &&
            currentSale.value.status === "draft"
        ) {
            throw new Error(
                "Draft belum diselesaikan. Jangan tinggalkan transaksi ini.",
            );
        }

        resetTransactionState();
    }

    // =========================================================
    // HOLD / RESUME TRANSACTIONS
    // =========================================================

    async function loadHeldTransactions(): Promise<void> {
        if (loadingHeldTransactions.value) return;

        loadingHeldTransactions.value = true;

        try {
            const result = await salesService.getSales({
                page: 1,
                per_page: 100,
                status: "held",
            });

            heldTransactions.value = result.data;
        } finally {
            loadingHeldTransactions.value = false;
        }
    }

    async function holdCurrentSale(): Promise<Sale | null> {
        if (processing.value) return null;

        if (
            !currentSale.value ||
            currentSale.value.status !== "draft"
        ) {
            throw new Error(
                "Hanya draft aktif yang dapat ditahan.",
            );
        }

        processing.value = true;

        try {
            const heldSale = await salesService.holdSale(
                currentSale.value.id,
            );

            heldTransactions.value = [
                heldSale,
                ...heldTransactions.value.filter(
                    item => item.id !== heldSale.id,
                ),
            ];

            resetTransactionState();

            return heldSale;
        } finally {
            processing.value = false;
        }
    }

    async function resumeHeldTransaction(
        saleId: number,
    ): Promise<Sale | null> {
        if (processing.value) return null;

        if (currentSale.value || cart.value.length > 0) {
            throw new Error(
                "Selesaikan atau tahan transaksi aktif sebelum melanjutkan transaksi lain.",
            );
        }

        if (!Number.isSafeInteger(saleId) || saleId <= 0) {
            throw new Error("ID transaksi tidak valid.");
        }

        processing.value = true;

        try {
            const detail = await salesService.getSale(saleId);

            if (String(detail.status) !== "held") {
                throw new Error(
                    "Transaksi tidak ditemukan dalam daftar transaksi tertahan.",
                );
            }

            const resumedSale =
                await salesService.resumeSale(saleId);

            if (resumedSale.status !== "draft") {
                throw new Error("Transaksi gagal dilanjutkan.");
            }

            cart.value = (detail.items ?? []).map(
                (item: SaleItem): PosCartItem => ({
                    product_id: item.product_id,
                    sku: item.sku ?? "",
                    barcode: item.barcode ?? null,
                    product_name: item.product_name ?? "Produk",
                    unit_name: undefined,

                    quantity: toNumber(item.quantity),
                    unit_price: toNumber(item.unit_price),

                    discount_amount: toNumber(item.discount_amount),
                    discount_mode: "amount",
                    discount_value: toNumber(item.discount_amount),

                    available_stock: undefined,
                    notes: null,
                }),
            );

            selectedCustomerId.value =
                detail.customer_id ?? null;

            paymentMethod.value = "cash";
            paymentAmount.value = 0;

            settlementMode.value = "paid";
            dueDate.value = "";
            paymentReferenceNumber.value = "";

            notes.value = detail.notes ?? "";

            /**
             * Draft yang dilanjutkan tetap berstatus draft.
             * Sesuai alur backend saat ini, keranjang tidak diedit
             * setelah draft tersimpan; transaksi dapat diposting
             * atau ditahan kembali.
             */
            currentSale.value = {
                ...detail,
                ...resumedSale,
                items: detail.items,
            };

            heldTransactions.value =
                heldTransactions.value.filter(
                    item => item.id !== saleId,
                );

            clearSearch();

            return currentSale.value;
        } finally {
            processing.value = false;
        }
    }

    // =========================================================
    // PUBLIC API
    // =========================================================

    return {
        // State
        cart,
        keyword,
        searchResults,
        selectedCustomerId,
        paymentMethod,
        paymentAmount,
        notes,
        loadingSearch,
        processing,
        currentSale,

        // Transaction state
        isDraft,
        isPosted,
        isCancelled,
        transactionLocked,
        canCreateDraft,
        canPostSale,

        // Calculation state
        transactionDiscountMode,
        transactionDiscountValue,

        taxMode,
        taxValue,

        additionalCharges,

        subtotal,
        itemDiscount,
        afterItemDiscount,
        transactionDiscount,
        discountTotal,
        dpp,
        taxAmount,
        additionalChargesTotal,
        grandTotal,
        summary,

        // Payment state
        settlementMode,
        dueDate,
        paymentReferenceNumber,

        amountReceived,
        appliedAmount,
        changeAmount,
        remainingAmount,
        receivableAmount,

        isPaid,
        isPartial,
        isReceivable,
        requiresConfirmation,

        paymentSummary,
        paymentValidationErrors,
        isPaymentValid,
        canProcessPayment,

        // Search
        searchProduct,
        clearSearch,

        // Cart
        addProduct,
        updateQuantity,
        updateUnitPrice,
        updateDiscount,
        updateItemDiscount,
        updateItemNotes,
        removeProduct,
        clearCart,

        // Customer
        setCustomer,

        // Discount / tax
        setTransactionDiscount,
        setTax,

        // Additional charges
        addAdditionalCharge,
        updateAdditionalCharge,
        removeAdditionalCharge,
        clearAdditionalCharges,

        // Payment actions
        setPaymentAmount,
        setPaymentMethod,
        setSettlementMode,
        setDueDate,
        setPaymentReferenceNumber,
        payExact,
        resetPaymentAmount,
        buildPaymentData,

        // Transaction actions
        buildSalePayload,
        createDraft,
        postCurrentSale,
        newTransaction,

        // Held transactions
        heldTransactions,
        loadingHeldTransactions,
        loadHeldTransactions,
        holdCurrentSale,
        resumeHeldTransaction,
    };
}
