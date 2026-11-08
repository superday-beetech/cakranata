<script setup lang="ts">
import type { PaymentMethod } from "../../types/sales";

defineProps<{
  modelValue: PaymentMethod;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  "update:modelValue": [value: PaymentMethod];
}>();

const methods: Array<{
  value: PaymentMethod;
  label: string;
  icon: string;
}> = [
  { value: "cash", label: "Tunai", icon: "ti ti-cash" },
  { value: "transfer", label: "Transfer", icon: "ti ti-building-bank" },
  { value: "qris", label: "QRIS", icon: "ti ti-qrcode" },
  { value: "debit", label: "Debit", icon: "ti ti-credit-card" },
  { value: "credit", label: "Kredit", icon: "ti ti-credit-card-pay" },
  { value: "other", label: "Lainnya", icon: "ti ti-wallet" },
];
</script>

<template>
  <div>
    <span class="form-label" id="pos-method-label">Metode pembayaran</span>

    <div class="pos-method-grid" role="radiogroup" aria-labelledby="pos-method-label">
      <button
        v-for="method in methods"
        :key="method.value"
        type="button"
        class="pos-method"
        :class="{ 'is-active': modelValue === method.value }"
        role="radio"
        :aria-checked="modelValue === method.value"
        :disabled="disabled"
        @click="emit('update:modelValue', method.value)"
      >
        <i :class="method.icon"></i>
        <span>{{ method.label }}</span>
      </button>
    </div>
  </div>
</template>
