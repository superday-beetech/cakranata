<script setup lang="ts">
import { computed } from "vue";
import PosPaymentMethod from "./PosPaymentMethod.vue";
import PosCashDenominations from "./PosCashDenominations.vue";
import PosPaymentInformation from "./PosPaymentInformation.vue";
import { formatCurrency } from "../../utils/format";
import type { PaymentMethod, SettlementMode } from "../../types/sales";
import CurrencyInput from "./PosCurrencyInput.vue";

const props = defineProps<{
  mode: SettlementMode;
  paymentMethod: PaymentMethod | null;
  paymentAmount: number;
  total: number;
  dueDate: string;
  dueDateError?: string | null;
  referenceNumber: string;
  paymentConfirmed: boolean;
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
  "update:paymentConfirmed": [value: boolean];
  exact: [];
  reset: [];
}>();

const settlementOptions: Array<{ value: SettlementMode; label: string; icon: string; hint: string }> = [
  { value: "paid", label: "Lunas", icon: "ti ti-circle-check", hint: "Bayar seluruh tagihan" },
  { value: "partial", label: "Sebagian", icon: "ti ti-wallet", hint: "Tempo sebagian: bayar sebagian, sisanya menjadi piutang" },
  { value: "receivable", label: "Piutang", icon: "ti ti-calendar", hint: "Seluruh tagihan menjadi piutang" },
];

const isCash = computed(() => props.paymentMethod === "cash");

const requiresConfirmation = computed(() => props.paymentMethod === "transfer" || props.paymentMethod === "qris");

// Mode sebagian: nominal harus lebih kecil dari total. Pesan hanya muncul saat salah.
const partialOverLimit = computed(() => props.mode === "partial" && props.total > 0 && props.paymentAmount >= props.total);

/* function updateAmount(event: Event): void {
  const value = Number((event.target as HTMLInputElement).value);

  emit("update:paymentAmount", Number.isFinite(value) ? Math.max(0, value) : 0);
}
 */

function setDenomination(value: number): void {
  emit("update:paymentAmount", Math.max(0, value));
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
  <div class="pos-payment">
    <!-- Mode penyelesaian -->
    <div class="pos-segment" role="radiogroup" aria-label="Mode pembayaran">
      <label v-for="option in settlementOptions" :key="option.value" class="pos-choice" :title="option.hint">
        <input type="radio" class="visually-hidden" name="pos-settlement-mode" :value="option.value" :checked="mode === option.value" :disabled="disabled" @change="emit('update:mode', option.value)" />
        <span class="pos-segment-btn"><i :class="option.icon"></i>{{ option.label }}</span>
      </label>
    </div>

    <!-- Jatuh tempo: satu baris -->
    <template v-if="mode !== 'paid'">
      <div class="pos-inline-row">
        <label class="form-label mb-0" for="pos-due-date">Jatuh tempo</label>
        <input
          id="pos-due-date"
          type="date"
          class="form-control form-control-sm"
          :class="{ 'is-invalid': !!dueDateError }"
          :value="dueDate"
          :disabled="disabled"
          :aria-invalid="!!dueDateError"
          @change="emit('update:dueDate', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <small v-if="dueDateError" class="pos-invalid">{{ dueDateError }}</small>
    </template>

    <!-- Metode & nominal -->
    <template v-if="mode !== 'receivable'">
      <PosPaymentMethod :model-value="paymentMethod ?? 'cash'" :disabled="disabled" @update:model-value="emit('update:paymentMethod', $event)" />

      <div>
        <label class="form-label" for="pos-payment-amount">
          {{ isCash ? "Uang diterima" : "Nominal dibayar" }}
          <kbd>F8</kbd>
        </label>

        <div class="input-group pos-amount">
          <span class="input-group-text">Rp</span>
          <CurrencyInput id="pos-payment-amount" :model-value="paymentAmount" :disabled="disabled" @update:model-value="emit('update:paymentAmount', $event)" />
        </div>

        <p v-if="partialOverLimit" class="pos-amount-hint is-error">
          Harus kurang dari
          <strong>{{ formatCurrency(total) }}</strong>
        </p>
      </div>

      <!-- <PosCashDenominations v-if="isCash" :disabled="disabled" @add="addDenomination" @exact="exactAmount" @reset="resetAmount" /> -->
      <PosCashDenominations v-if="isCash" :disabled="disabled" @set="setDenomination" @exact="emit('exact')" @reset="emit('reset')" />
      <PosPaymentInformation
        :method="paymentMethod ?? 'cash'"
        :bank-name="bankName"
        :account-number="accountNumber"
        :account-holder="accountHolder"
        :qris-image-url="qrisImageUrl"
        :reference-number="referenceNumber"
        @update:reference-number="emit('update:referenceNumber', $event)"
      />

      <!-- Konfirmasi transfer / QRIS -->
      <label v-if="requiresConfirmation && paymentAmount > 0" class="pos-confirm">
        <input type="checkbox" class="form-check-input" :checked="paymentConfirmed" :disabled="disabled" @change="emit('update:paymentConfirmed', ($event.target as HTMLInputElement).checked)" />

        <span>Pembayaran {{ paymentMethod === "qris" ? "QRIS" : "transfer" }} sudah diterima</span>
      </label>
    </template>

    <div v-else class="pos-notice is-info">
      <i class="ti ti-info-circle"></i>
      <div>
        <strong>Tidak ada pembayaran sekarang</strong>
        Seluruh tagihan {{ formatCurrency(total) }} dicatat sebagai piutang pelanggan.
      </div>
    </div>
  </div>
</template>
