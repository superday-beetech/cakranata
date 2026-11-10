<script setup lang="ts">
import "../styles/pos.css";

import { computed, ref } from "vue";

import CustomerAutocomplete from "@/modules/pharmacy/master/components/CustomerAutocomplete.vue";

import PosAdditionalCharges from "../components/pos/PosAdditionalCharges.vue";
import PosCart from "../components/pos/PosCart.vue";
import PosDiscountInput from "../components/pos/PosDiscountInput.vue";
import PosDock from "../components/pos/PosDock.vue";
import PosHeader from "../components/pos/PosHeader.vue";
import PosHeldTransactions from "../components/pos/PosHeldTransactions.vue";
import PosMenuBar from "../components/pos/PosMenuBar.vue";
import PosPanel from "../components/pos/PosPanel.vue";
import PosPayment from "../components/pos/PosPayment.vue";
import PosProductSearch from "../components/pos/PosProductSearch.vue";
import PosShortcutHelp from "../components/pos/PosShortcutHelp.vue";
import PosSummary from "../components/pos/PosSummary.vue";
import PosTaxInput from "../components/pos/PosTaxInput.vue";
import PosTotalPanel from "../components/pos/PosTotalPanel.vue";
import PosTransactionNotes from "../components/pos/PosTransactionNotes.vue";

import { usePos } from "../composables/usePos";
import { usePosAdditionalCharges } from "../composables/usePosAdditionalCharges";
import { usePosCartHandlers } from "../composables/usePosCartHandlers";
import { usePosDelight } from "../composables/usePosDelight";
import { usePosDisplay } from "../composables/usePosDisplay";
import { usePosDock, type DockEdge } from "../composables/usePosDock";
import { usePosFeedback } from "../composables/usePosFeedback";
import { usePosHost } from "../composables/usePosHost";
import { usePosKeyboard } from "../composables/usePosKeyboard";
import { usePosLifecycle } from "../composables/usePosLifecycle";
import { usePosMenuActions } from "../composables/usePosMenuActions";
import { usePosMenus } from "../composables/usePosMenus";
import { usePosNavigation } from "../composables/usePosNavigation";
import { usePosPaymentHandlers } from "../composables/usePosPaymentHandlers";
import { usePosPaymentInfo } from "../composables/usePosPaymentInfo";
import { usePosProductSearch } from "../composables/usePosProductSearch";
import { searchPosProducts } from "../services/posProductSearch";
import { usePosSections } from "../composables/usePosSections";
import { usePosSession } from "../composables/usePosSession";
import { usePosSettingsDialogs } from "../composables/usePosSettingsDialogs";
import { usePosSound } from "../composables/usePosSound";
import { usePosTransactionActions } from "../composables/usePosTransactionActions";
import { usePosTransactionAdjustments } from "../composables/usePosTransactionAdjustments";
import { usePosShortcutHelp } from "../composables/usePosShortcutHelp";
import { usePosTransactionState } from "../composables/usePosTransactionState";

defineOptions({ name: "PosView" });

/* INFRASTRUKTUR UI */

const host = usePosHost();
const { message, errorMessage, clearMessages, showError, showSuccess } = usePosFeedback();
const { soundEnabled, play: playPosSound, toggle: togglePosSound } = usePosSound();
const { dock, toggleDock, moveDock, toggleDockVisibility } = usePosDock();
const { isFullscreen, compactHeader, syncFullscreen, toggleFullscreen, toggleCompact } = usePosDisplay({
  notifyWarning: (text) => host.notify("warning", text),
});
const paymentInfo = usePosPaymentInfo();

const { shortcutHelp, toggleShortcutHelp, closeShortcutHelp, setShortcutHelp } = usePosShortcutHelp();
const dismissMessage = (): void => {
  message.value = "";
};
const dismissError = (): void => {
  errorMessage.value = "";
};

/* SEKSI HALAMAN & FOKUS */

const {
  barcodeSection,
  customerSection,
  cartSection,
  discountSection,
  chargesSection,
  paymentSection,
  paymentInput,
  adjustTab,
  adjustTabItems,
  onTabKeydown,
  focusBarcode,
  focusCustomer,
  focusCart,
  focusPayment,
  focusPaymentAmount,
  openAdjustTab,
} = usePosSections();

/* STATE & LOGIKA TRANSAKSI */

const {
  cart,
  keyword,
  searchResults,
  selectedCustomerId,
  paymentMethod,
  paymentAmount,
  notes,

  transactionDiscountMode,
  transactionDiscountValue,
  taxMode,
  taxValue,
  additionalCharges,
  settlementMode,
  dueDate,
  paymentReferenceNumber,

  subtotal,
  itemDiscount,
  transactionDiscount,
  dpp,
  taxAmount,
  additionalChargesTotal,
  grandTotal,

  appliedAmount,
  changeAmount,
  remainingAmount,

  loadingSearch,
  processing,
  currentSale,

  isDraft,
  isPosted,
  transactionLocked,
  canCreateDraft,
  canPostSale,

  searchProduct,
  clearSearch,
  addProduct,
  updateQuantity,
  updateUnitPrice,
  updateItemDiscount,
  updateItemNotes,
  removeProduct,
  clearCart,
  setCustomer,
  setPaymentAmount,
  setPaymentMethod,
  setTransactionDiscount,
  setTax,
  addAdditionalCharge,
  updateAdditionalCharge,
  removeAdditionalCharge,
  setSettlementMode,
  setDueDate,
  setPaymentReferenceNumber,
  payExact,
  resetPaymentAmount,
  createDraft,
  postCurrentSale,
  newTransaction,

  heldTransactions,
  loadingHeldTransactions,
  loadHeldTransactions,
  holdCurrentSale,
  resumeHeldTransaction,
  paymentConfirmed,
  isPaymentValid,
  dueDateError,
  setPaymentConfirmed,
} = usePos({ searchProducts: searchPosProducts });

const { hasUnsavedCart, hasUnsupportedDraftValues, saleStatus, settlementLabel } = usePosTransactionState({
  cart,
  isDraft,
  isPosted,
  additionalChargesTotal,
  settlementMode,
});

/* Sentuhan visual (jam, sapaan, kedip keranjang). Harus setelah usePos karena membaca subtotal. */
const { clockTime, greeting, cartFlash } = usePosDelight(subtotal);

/* SESI, NAVIGASI, DIALOG */

const { cashierName, cashierTitle, workspaceLabel, rememberOrganization, syncOrganization, signOut } = usePosSession({
  notifyWarning: (text) => host.notify("warning", text),
});

const { goToDashboard, openPage, openUrl, handleLogout } = usePosNavigation({
  hasUnsavedCart: () => hasUnsavedCart.value,
  signOut,
  notifyError: (text) => host.notify("error", text),
});

const { openPrinterSettings, openPosSettings, openPaymentSettings } = usePosSettingsDialogs(host);

/* HANDLER */

const { handleAddProduct, handleSearch, handleBarcodeEnter } = usePosProductSearch({
  keyword,

  getCart: () => cart.value,
  getSearchResults: () => searchResults.value,
  isTransactionLocked: () => transactionLocked.value,

  searchProduct,
  clearSearch,
  addProduct,

  clearMessages,
  showError,
  setErrorMessage: (text) => {
    errorMessage.value = text;
  },

  playSound: playPosSound,
  focusBarcode,
});

const { handleQuantityChange, handlePriceChange, handleDiscountModeChange, handleDiscountChange, handleItemNoteChange, handleRemoveProduct, handleClearCart } = usePosCartHandlers({
  getCart: () => cart.value,

  updateQuantity,
  updateUnitPrice,
  updateItemDiscount,
  updateItemNotes,
  removeProduct,
  clearCart,

  showError,
});

const { handleTransactionDiscountMode, handleTransactionDiscountValue, handleTaxMode, handleTaxValue } = usePosTransactionAdjustments({
  getTransactionDiscountMode: () => transactionDiscountMode.value,
  getTransactionDiscountValue: () => transactionDiscountValue.value,
  getTaxMode: () => taxMode.value,
  getTaxValue: () => taxValue.value,

  setTransactionDiscount,
  setTax,
});

const { addCharge, updateCharge, removeCharge } = usePosAdditionalCharges({
  addAdditionalCharge,
  updateAdditionalCharge,
  removeAdditionalCharge,
  showError,
});

const { handleSettlementMode, handlePaymentAmount, handlePaymentMethod, handleDueDate, handleReferenceNumber, handleExactPayment, handleResetPayment, handlePaymentConfirmation } = usePosPaymentHandlers({
  getSettlementMode: () => settlementMode.value,

  setSettlementMode,
  setPaymentAmount,
  setPaymentMethod,
  setDueDate,
  setPaymentReferenceNumber,
  payExact,
  resetPaymentAmount,
  setPaymentConfirmed,
});

const { handleProcessSale, handleHoldSale, handleResumeSale, handleNewTransaction, handleRefreshHeld, handlePostSale, handleCreateDraft } = usePosTransactionActions({
  hasUnsupportedDraftValues: () => hasUnsupportedDraftValues.value,
  isDraft: () => isDraft.value,
  canPostSale: () => canPostSale.value,
  isPaymentValid: () => isPaymentValid.value,
  isPosted: () => isPosted.value,

  createDraft,
  postCurrentSale,
  holdCurrentSale,
  resumeHeldTransaction,
  loadHeldTransactions,
  newTransaction,

  clearMessages,
  setErrorMessage: (text) => {
    errorMessage.value = text;
  },
  showSuccess,
  showError,

  playTransactionSound: () => {
    void playPosSound("transaction");
  },

  focusBarcode,
});

function handleCustomerChange(customerId: number | null): void {
  setCustomer(customerId);
}

/* KEYBOARD, MENU, DOCK */

const { handleKeyboard, handleDenominationShortcut } = usePosKeyboard({
  hostIsOpen: () => host.isOpen(),

  isProcessing: () => processing.value,
  isPosted: () => isPosted.value,
  getSettlementMode: () => settlementMode.value,
  getPaymentMethod: () => paymentMethod.value,

  setShortcutHelp,

  focusBarcode,
  focusCustomer,
  focusCart,
  focusPayment,
  focusPaymentAmount,
  openAdjustTab,

  handleResetPayment,
  handleExactPayment,
  handlePaymentAmount,

  handleHoldSale,
  handleNewTransaction,
  handleProcessSale,
});

const { runAction } = usePosMenuActions({
  handleNewTransaction,
  handleHoldSale,
  handleProcessSale,
  handleRefreshHeld,

  focusBarcode,
  focusCustomer,
  focusCart,
  openAdjustTab,
  focusPaymentAmount,

  handleExactPayment,
  handleResetPayment,

  toggleSound: togglePosSound,
  toggleDock,
  toggleDockVisibility,
  moveDock,

  toggleShortcutHelp,
  showAbout: () => showSuccess("ACN Pharmacy · Point of Sale"),

  openPrinterSettings,
  openPosSettings,
  openPaymentSettings,

  toggleFullscreen,
  toggleCompact,
  handleLogout,

  openPage,
  openUrl,
});

const { menus, dockItems, loadMenu, loadPermission } = usePosMenus({
  dock,
  isSoundEnabled: () => soundEnabled.value,
  isFullscreen: () => isFullscreen.value,
  isCompact: () => compactHeader.value,
  cashierName: () => cashierName.value,

  settlementMode: () => settlementMode.value,
  isDraft: () => isDraft.value,
  isPosted: () => isPosted.value,
  isProcessing: () => processing.value,
});

function onDockMove(payload: { edge: DockEdge; offset: number }): void {
  moveDock(payload.edge, payload.offset);
}

// Setelah satu aksi dipilih, toolbar terlipat lagi agar tidak menghalangi.
function onDockAction(id: string): void {
  runAction(id);
  dock.open = false;
}

/* SIKLUS HIDUP */

usePosLifecycle({
  handleKeyboard,
  handleDenominationShortcut,

  loadHeldTransactions,
  showError,
  focusBarcode,

  loadMenu,
  loadPermission,

  rememberOrganization,
  syncOrganization,
  syncFullscreen,

  hasUnsavedCart: () => hasUnsavedCart.value,
});
</script>

<template>
  <div class="pos-page">
    <div class="pos-page">
      <PosMenuBar :menus="menus" :clock="clockTime" :greeting="greeting" :cashier="cashierName" :workspace="workspaceLabel" :user-title="cashierTitle" :fullscreen="isFullscreen" :compact="compactHeader" @action="runAction" />

      <!-- HEADER -->
      <PosHeader
        v-if="!compactHeader"
        :sale-number="currentSale?.sale_number"
        :status="saleStatus"
        :processing="processing"
        :loading-held="loadingHeldTransactions"
        :can-hold="!isPosted"
        :can-new="isPosted"
        :sound-enabled="soundEnabled"
        @back="goToDashboard"
        @new-transaction="handleNewTransaction"
        @hold-transaction="handleHoldSale"
        @refresh="handleRefreshHeld"
        @toggle-sound="togglePosSound"
        @toggle-shortcuts="shortcutHelp = !shortcutHelp"
      />

      <!-- BANTUAN SHORTCUT & NOTIFIKASI (melayang, tidak menggeser layout) -->
      <PosShortcutHelp :visible="shortcutHelp" @close="shortcutHelp = false" />

      <div v-if="message || errorMessage" class="pos-toasts">
        <div v-if="message" class="alert alert-success" role="status">
          <i class="ti ti-circle-check fs-5"></i>
          <span>{{ message }}</span>
          <button type="button" class="btn btn-icon btn-sm btn-soft-success" aria-label="Tutup pesan" @click="message = ''">
            <i class="ti ti-x"></i>
          </button>
        </div>

        <div v-if="errorMessage" class="alert alert-danger" role="alert">
          <i class="ti ti-alert-circle fs-5"></i>
          <span>{{ errorMessage }}</span>
          <button type="button" class="btn btn-icon btn-sm btn-soft-danger" aria-label="Tutup pesan" @click="errorMessage = ''">
            <i class="ti ti-x"></i>
          </button>
        </div>
      </div>

      <main class="pos-main">
        <!-- KIRI: INPUT & KERANJANG -->
        <section class="pos-left">
          <div v-if="isPosted" class="card pos-posted">
            <i class="ti ti-circle-check text-success"></i>

            <div>
              <strong>Transaksi berhasil diselesaikan</strong>
              <small>{{ currentSale?.sale_number }}</small>
            </div>

            <button type="button" class="btn btn-primary btn-sm" @click="handleNewTransaction">
              <i class="ti ti-plus"></i>
              Transaksi baru
            </button>
          </div>

          <div class="pos-entry">
            <PosProductSearch
              ref="barcodeSection"
              v-model:keyword="keyword"
              :results="searchResults"
              :loading="loadingSearch"
              :disabled="transactionLocked || processing"
              @search="handleSearch"
              @enter="handleBarcodeEnter"
              @select="handleAddProduct"
            />

            <div ref="customerSection" class="card pos-customer">
              <label class="pos-field-label">
                <i class="ti ti-user"></i>
                Pelanggan
                <kbd>F2</kbd>
              </label>

              <CustomerAutocomplete :model-value="selectedCustomerId" :disabled="transactionLocked || processing" @update:model-value="handleCustomerChange" />
            </div>
          </div>

          <PosCart
            ref="cartSection"
            class="pos-cart"
            :class="{ 'is-flash': cartFlash }"
            :items="cart"
            :subtotal="subtotal"
            :disabled="transactionLocked || processing"
            shortcut="F3"
            @quantity-change="handleQuantityChange"
            @price-change="handlePriceChange"
            @discount-mode-change="handleDiscountModeChange"
            @discount-change="handleDiscountChange"
            @note-change="handleItemNoteChange"
            @remove="handleRemoveProduct"
            @clear="handleClearCart"
          />
        </section>

        <!-- TENGAH: PENYESUAIAN & RINGKASAN -->
        <section class="pos-middle">
          <!-- PENYESUAIAN TRANSAKSI (tab agar hemat ruang) -->
          <section class="card pos-adjust">
            <div class="pos-tabs" role="tablist" aria-label="Penyesuaian transaksi">
              <button
                v-for="(tab, index) in adjustTabItems"
                :id="`pos-tab-${tab.id}`"
                :key="tab.id"
                type="button"
                class="pos-tab"
                :class="{ 'is-active': adjustTab === tab.id }"
                role="tab"
                :aria-selected="adjustTab === tab.id"
                :aria-controls="`pos-tabpanel-${tab.id}`"
                :aria-label="tab.fullLabel"
                :title="tab.fullLabel"
                :tabindex="adjustTab === tab.id ? 0 : -1"
                @click="adjustTab = tab.id"
                @keydown="onTabKeydown($event, index)"
              >
                <i :class="tab.icon"></i>
                <span class="pos-tab-label">{{ tab.label }}</span>
                <span v-if="tab.id === 'charges' && additionalCharges.length" class="pos-tab-badge">{{ additionalCharges.length }}</span>
                <span v-else-if="tab.id === 'notes' && notes" class="pos-tab-badge">1</span>
                <span v-else-if="tab.id === 'held' && heldTransactions.length" class="pos-tab-badge">{{ heldTransactions.length }}</span>
                <kbd v-if="tab.shortcut">{{ tab.shortcut }}</kbd>
              </button>
            </div>

            <div class="pos-adjust-body">
              <div v-show="adjustTab === 'discount'" id="pos-tabpanel-discount" ref="discountSection" role="tabpanel" aria-labelledby="pos-tab-discount">
                <PosDiscountInput
                  :mode="transactionDiscountMode"
                  :value="transactionDiscountValue"
                  :base-amount="Math.max(0, subtotal - itemDiscount)"
                  :disabled="transactionLocked || processing"
                  @update:mode="handleTransactionDiscountMode"
                  @update:value="handleTransactionDiscountValue"
                />
              </div>

              <div v-show="adjustTab === 'charges'" id="pos-tabpanel-charges" ref="chargesSection" role="tabpanel" aria-labelledby="pos-tab-charges">
                <PosAdditionalCharges :charges="additionalCharges" :disabled="transactionLocked || processing" @add="addCharge" @update="updateCharge" @remove="removeCharge" />
              </div>

              <div v-show="adjustTab === 'notes'" id="pos-tabpanel-notes" class="h-100" role="tabpanel" aria-labelledby="pos-tab-notes">
                <PosTransactionNotes v-model="notes" :disabled="transactionLocked || processing" />
              </div>

              <div v-show="adjustTab === 'held'" id="pos-tabpanel-held" role="tabpanel" aria-labelledby="pos-tab-held">
                <PosHeldTransactions :transactions="heldTransactions" :loading="loadingHeldTransactions" :disabled="processing" @refresh="handleRefreshHeld" @resume="handleResumeSale" />
              </div>
            </div>
          </section>

          <PosPanel title="Ringkasan" icon="ti ti-receipt" tone="success">
            <PosSummary :subtotal="subtotal" :item-discount="itemDiscount" :transaction-discount="transactionDiscount" :dpp="dpp" :tax="taxAmount" :additional-charges="additionalChargesTotal" :total="grandTotal" :show-total="false" />

            <div class="pos-tax-row">
              <label><i class="ti ti-percentage"></i>Atur pajak</label>

              <PosTaxInput :mode="taxMode" :value="taxValue" :base-amount="dpp" :disabled="transactionLocked || processing" :show-preview="false" @update:mode="handleTaxMode" @update:value="handleTaxValue" />
            </div>
          </PosPanel>
        </section>

        <!-- KANAN: PEMBAYARAN & AKSI -->
        <aside class="pos-right">
          <div class="pos-right-scroll">
            <PosPanel ref="paymentSection" title="Pembayaran" icon="ti ti-wallet" tone="primary" shortcut="F6">
              <div ref="paymentInput">
                <PosPayment
                  :mode="settlementMode"
                  :payment-method="paymentMethod"
                  :payment-amount="paymentAmount"
                  :total="grandTotal"
                  :due-date="dueDate"
                  :due-date-error="dueDateError"
                  :reference-number="paymentReferenceNumber"
                  :payment-confirmed="paymentConfirmed"
                  :disabled="processing || isPosted"
                  :bank-name="paymentInfo.bankName"
                  :account-number="paymentInfo.accountNumber"
                  :account-holder="paymentInfo.accountHolder"
                  :qris-image-url="paymentInfo.qrisImageUrl"
                  @update:mode="handleSettlementMode"
                  @update:payment-method="handlePaymentMethod"
                  @update:payment-amount="handlePaymentAmount"
                  @update:due-date="handleDueDate"
                  @update:reference-number="handleReferenceNumber"
                  @update:payment-confirmed="handlePaymentConfirmation"
                  @exact="handleExactPayment"
                  @reset="handleResetPayment"
                />
              </div>
            </PosPanel>
          </div>

          <!-- TOTAL & AKSI: selalu terlihat di bawah -->
          <section class="card pos-final">
            <PosTotalPanel :total="grandTotal" :paid="appliedAmount" :change="changeAmount" :remaining="remainingAmount" :settlement-label="settlementLabel" />

            <div class="pos-final-actions">
              <button v-if="!isDraft && !isPosted" type="button" class="btn btn-primary btn-lg" :disabled="!canCreateDraft || processing" @click="handleCreateDraft">
                <span v-if="processing" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <i v-else class="ti ti-device-floppy"></i>
                Buat draft penjualan
                <kbd>F12</kbd>
              </button>

              <button v-else-if="isDraft" type="button" class="btn btn-success btn-lg" :disabled="!canPostSale || !isPaymentValid || processing" @click="handlePostSale">
                <span v-if="processing" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <i v-else class="ti ti-check"></i>
                Posting penjualan
                <kbd>F12</kbd>
              </button>

              <button v-else type="button" class="btn btn-primary btn-lg" :disabled="processing" @click="handleNewTransaction">
                <i class="ti ti-plus"></i>
                Transaksi baru
                <kbd>F11</kbd>
              </button>

              <small v-if="isDraft">
                <i class="ti ti-info-circle"></i>
                Draft tersimpan. Periksa transaksi sebelum posting.
              </small>
            </div>
          </section>
        </aside>
      </main>
      <PosDock v-if="dock.visible" :open="dock.open" :edge="dock.edge" :offset="dock.offset" :items="dockItems" @action="onDockAction" @toggle="toggleDock" @move="onDockMove" />
    </div>
  </div>
</template>
