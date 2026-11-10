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
  { value: "transfer", label: "Transfer Bank", icon: "ti ti-building-bank" },
  { value: "qris", label: "QRIS", icon: "ti ti-qrcode" },
  { value: "debit", label: "Kartu Debit", icon: "ti ti-credit-card" },
  { value: "credit", label: "Kartu Kredit", icon: "ti ti-credit-card-pay" },
  { value: "other", label: "Lainnya", icon: "ti ti-wallet" },
];
</script>

<template>
  <div class="pos-payment-method">
    <label class="pos-payment-label">Metode Pembayaran</label>

    <div class="pos-method-grid">
      <button v-for="method in methods" :key="method.value" type="button" class="pos-method-option" :class="{ active: modelValue === method.value }" :disabled="disabled" @click="emit('update:modelValue', method.value)">
        <i :class="method.icon"></i>
        <span>{{ method.label }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.pos-payment-label {
  color: #475569;
  display: block;
  font-size: 11px;
  font-weight: 700;
  margin-bottom: 8px;
}

.pos-method-grid {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.pos-method-option {
  align-items: center;
  background: white;
  border: 1px solid #dbe1e8;
  border-radius: 8px;
  color: #64748b;
  display: flex;
  flex-direction: column;
  gap: 6px;
  justify-content: center;
  min-height: 67px;
  padding: 9px 5px;
}

.pos-method-option i {
  font-size: 20px;
}

.pos-method-option span {
  font-size: 10px;
  font-weight: 650;
  text-align: center;
}

.pos-method-option.active {
  background: #eff6ff;
  border-color: #2563eb;
  color: #1d4ed8;
}

.pos-method-option:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

@media (max-width: 480px) {
  .pos-method-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
