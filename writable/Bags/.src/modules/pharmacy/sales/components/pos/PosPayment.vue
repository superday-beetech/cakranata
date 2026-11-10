<script setup lang="ts">
import PosPaymentMethod from "./PosPaymentMethod.vue";
import PosCashDenominations from "./PosCashDenominations.vue";
import PosPaymentInformation from "./PosPaymentInformation.vue";

import type { PaymentMethod, SettlementMode } from "../../types/sales";

const props = defineProps<{
  mode: SettlementMode;
  paymentMethod: PaymentMethod;
  paymentAmount: number;
  total: number;
  dueDate: string;
  referenceNumber: string;
  disabled?: boolean;

  bankName?: string;
  accountNumber?: string;
  accountHolder?: string;
  qrisImageUrl?: string | null;
}>();

const emit = defineEmits<{
  "update:mode": [value: SettlementMode];
  "update:paymentMethod": [value: PaymentMethod];
  "update:paymentAmount": [value: number];
  "update:dueDate": [value: string];
  "update:referenceNumber": [value: string];
  submit: [];
}>();

function currency(value: number): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(value);
}

function updateAmount(event: Event): void {
  const value = Number((event.target as HTMLInputElement).value);

  emit("update:paymentAmount", Number.isFinite(value) ? Math.max(0, value) : 0);
}

function addDenomination(value: number): void {
  emit("update:paymentAmount", Math.max(0, props.paymentAmount) + value);
}

function exactAmount(): void {
  if (props.mode !== "paid") return;

  emit("update:paymentAmount", Math.max(0, props.total));
}

function resetAmount(): void {
  emit("update:paymentAmount", 0);
}
</script>

<template>
  <section class="pos-payment-card">
    <div class="pos-payment-header">
      <div>
        <h5>Pembayaran</h5>
        <p>Tentukan penyelesaian tagihan pelanggan.</p>
      </div>

      <kbd>F6</kbd>
    </div>

    <!-- Settlement mode -->
    <div class="pos-settlement-options">
      <button type="button" class="pos-settlement-option" :class="{ active: mode === 'paid' }" :disabled="disabled" @click="emit('update:mode', 'paid')">
        <i class="ti ti-circle-check"></i>
        <span>
          <strong>Lunas</strong>
          <small>Bayar seluruh tagihan</small>
        </span>
      </button>

      <button type="button" class="pos-settlement-option" :class="{ active: mode === 'partial' }" :disabled="disabled" @click="emit('update:mode', 'partial')">
        <i class="ti ti-wallet"></i>
        <span>
          <strong>Tempo - Sebagian</strong>
          <small>Bayar sebagian, sisanya piutang</small>
        </span>
      </button>

      <button type="button" class="pos-settlement-option" :class="{ active: mode === 'receivable' }" :disabled="disabled" @click="emit('update:mode', 'receivable')">
        <i class="ti ti-calendar-due"></i>
        <span>
          <strong>Tempo - Piutang</strong>
          <small>Seluruh tagihan menjadi piutang</small>
        </span>
      </button>
    </div>

    <!-- Due date -->
    <div v-if="mode !== 'paid'" class="pos-due-date">
      <label for="pos-due-date">Tanggal Jatuh Tempo</label>

      <input id="pos-due-date" type="date" :value="dueDate" :disabled="disabled" @change="emit('update:dueDate', ($event.target as HTMLInputElement).value)" />
    </div>

    <!-- Payment method and amount -->
    <template v-if="mode !== 'receivable'">
      <PosPaymentMethod :model-value="paymentMethod" :disabled="disabled" @update:model-value="emit('update:paymentMethod', $event)" />

      <div class="pos-payment-amount">
        <div class="pos-payment-amount-heading">
          <label for="pos-payment-amount">
            {{ paymentMethod === "cash" ? "Uang Diterima" : "Nominal Dibayar" }}
          </label>

          <kbd>F8</kbd>
        </div>

        <div class="pos-amount-input">
          <span>Rp</span>
          <input id="pos-payment-amount" type="number" min="0" step="1" :value="paymentAmount" :disabled="disabled" placeholder="0" @input="updateAmount" />
        </div>

        <div v-if="mode === 'paid'" class="pos-payment-hint">
          Total yang harus dibayar: <strong>{{ currency(total) }}</strong>
        </div>

        <div v-else class="pos-payment-hint">
          Pembayaran sekarang maksimal sebesar
          <strong>{{ currency(total) }}</strong
          >.
        </div>

        <PosCashDenominations v-if="paymentMethod === 'cash'" :disabled="disabled" @add="addDenomination" @exact="exactAmount" @reset="resetAmount" />
      </div>

      <PosPaymentInformation
        :method="paymentMethod"
        :bank-name="bankName"
        :account-number="accountNumber"
        :account-holder="accountHolder"
        :qris-image-url="qrisImageUrl"
        :reference-number="referenceNumber"
        @update:reference-number="emit('update:referenceNumber', $event)"
      />
    </template>

    <div v-else class="pos-full-receivable">
      <i class="ti ti-info-circle"></i>
      <div>
        <strong>Tidak ada pembayaran sekarang</strong>
        <p>
          Seluruh tagihan sebesar {{ currency(total) }}
          akan dicatat sebagai piutang pelanggan.
        </p>
      </div>
    </div>

    <button type="button" class="pos-process-payment" :disabled="disabled || total <= 0" @click="emit('submit')">
      <i class="ti ti-check"></i>
      Proses Transaksi
    </button>
  </section>
</template>

<style scoped>
.pos-payment-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 11px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 16px;
}

.pos-payment-header {
  align-items: center;
  display: flex;
  justify-content: space-between;
}

.pos-payment-header h5 {
  font-size: 14px;
  font-weight: 750;
  margin: 0 0 3px;
}

.pos-payment-header p {
  color: #64748b;
  font-size: 11px;
  margin: 0;
}

kbd {
  background: #f1f5f9;
  border: 1px solid #dbe1e8;
  border-radius: 4px;
  color: #475569;
  font-family: inherit;
  font-size: 9px;
  font-weight: 750;
  padding: 3px 5px;
}

.pos-settlement-options {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pos-settlement-option {
  align-items: center;
  background: white;
  border: 1px solid #dbe1e8;
  border-radius: 8px;
  color: #64748b;
  display: flex;
  gap: 11px;
  padding: 11px;
  text-align: left;
  width: 100%;
}

.pos-settlement-option > i {
  font-size: 21px;
}

.pos-settlement-option span {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.pos-settlement-option strong {
  color: #334155;
  font-size: 11px;
}

.pos-settlement-option small {
  color: #94a3b8;
  font-size: 10px;
}

.pos-settlement-option.active {
  background: #eff6ff;
  border-color: #2563eb;
  color: #2563eb;
}

.pos-settlement-option.active strong {
  color: #1d4ed8;
}

.pos-due-date,
.pos-payment-amount {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pos-due-date label,
.pos-payment-amount label {
  color: #475569;
  font-size: 11px;
  font-weight: 700;
}

.pos-due-date input {
  background: white;
  border: 1px solid #dbe1e8;
  border-radius: 7px;
  font-size: 12px;
  min-height: 36px;
  padding: 7px 9px;
  width: 100%;
}

.pos-payment-amount-heading {
  align-items: center;
  display: flex;
  justify-content: space-between;
}

.pos-amount-input {
  align-items: center;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  display: flex;
  gap: 9px;
  padding: 5px 11px;
}

.pos-amount-input span {
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}

.pos-amount-input input {
  background: transparent;
  border: 0;
  color: #1e293b;
  flex: 1;
  font-size: 20px;
  font-weight: 800;
  min-width: 0;
  outline: 0;
  padding: 7px 0;
  text-align: right;
}

.pos-payment-hint {
  color: #64748b;
  font-size: 10px;
}

.pos-payment-hint strong {
  color: #334155;
}

.pos-full-receivable {
  align-items: flex-start;
  background: #fff7ed;
  border: 1px solid #fed7aa;
  border-radius: 8px;
  color: #9a3412;
  display: flex;
  gap: 10px;
  padding: 12px;
}

.pos-full-receivable > i {
  font-size: 20px;
}

.pos-full-receivable strong {
  font-size: 11px;
}

.pos-full-receivable p {
  font-size: 10px;
  line-height: 1.5;
  margin: 4px 0 0;
}

.pos-process-payment {
  align-items: center;
  background: #2563eb;
  border: 0;
  border-radius: 8px;
  color: white;
  display: flex;
  font-size: 13px;
  font-weight: 750;
  gap: 8px;
  justify-content: center;
  min-height: 44px;
  padding: 10px;
  width: 100%;
}

.pos-process-payment:hover:not(:disabled) {
  background: #1d4ed8;
}

.pos-process-payment:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
</style>
