<script setup lang="ts">
import { computed } from "vue";
import PosPaymentMethod from "./PosPaymentMethod.vue";
import PosCashDenominations from "./PosCashDenominations.vue";
import PosPaymentInformation from "./PosPaymentInformation.vue";
import type { PaymentMethod, SettlementMode } from "../../types/sales";

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
}>();

const settlementOptions: Array<{ value: SettlementMode; label: string; icon: string; hint: string }> = [
  { value: "paid", label: "Lunas", icon: "ti ti-circle-check", hint: "Bayar seluruh tagihan" },
  { value: "partial", label: "Tempo sebagian", icon: "ti ti-wallet", hint: "Bayar sebagian, sisanya piutang" },
  { value: "receivable", label: "Piutang", icon: "ti ti-calendar-due", hint: "Seluruh tagihan menjadi piutang" },
];

const isCash = computed(() => props.paymentMethod === "cash");

const requiresConfirmation = computed(() => props.paymentMethod === "transfer" || props.paymentMethod === "qris");

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
  <div class="pos-payment">
    <!-- Mode penyelesaian -->
    <div class="pos-segment" role="radiogroup" aria-label="Mode pembayaran">
      <button
        v-for="option in settlementOptions"
        :key="option.value"
        type="button"
        class="pos-segment-btn"
        :class="{ 'is-active': mode === option.value }"
        role="radio"
        :aria-checked="mode === option.value"
        :title="option.hint"
        :disabled="disabled"
        @click="emit('update:mode', option.value)"
      >
        <i :class="option.icon"></i>
        {{ option.label }}
      </button>
    </div>

    <!-- Jatuh tempo -->
    <div v-if="mode !== 'paid'">
      <label class="form-label" for="pos-due-date">Tanggal jatuh tempo</label>

      <input id="pos-due-date" type="date" class="form-control" :class="{ 'is-invalid': !!dueDateError }" :value="dueDate" :disabled="disabled" :aria-invalid="!!dueDateError" @change="emit('update:dueDate', ($event.target as HTMLInputElement).value)" />

      <small v-if="dueDateError" class="pos-invalid">{{ dueDateError }}</small>
    </div>

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
          <input id="pos-payment-amount" type="number" class="form-control" min="0" step="1" placeholder="0" :value="paymentAmount" :disabled="disabled" @input="updateAmount" />
        </div>

        <p v-if="mode === 'paid'" class="pos-amount-hint">
          Total yang harus dibayar:
          <strong>{{ currency(total) }}</strong>
        </p>

        <p v-else class="pos-amount-hint">
          Pembayaran sekarang harus lebih kecil dari
          <strong>{{ currency(total) }}</strong
          >.
        </p>
      </div>

      <PosCashDenominations v-if="isCash" :disabled="disabled" @add="addDenomination" @exact="exactAmount" @reset="resetAmount" />

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

        <span>Saya sudah memastikan pembayaran {{ paymentMethod === "qris" ? "QRIS" : "transfer" }} diterima.</span>
      </label>
    </template>

    <div v-else class="pos-notice is-info">
      <i class="ti ti-info-circle"></i>
      <div>
        <strong>Tidak ada pembayaran sekarang</strong>
        Seluruh tagihan {{ currency(total) }} dicatat sebagai piutang pelanggan.
      </div>
    </div>
  </div>
</template>
