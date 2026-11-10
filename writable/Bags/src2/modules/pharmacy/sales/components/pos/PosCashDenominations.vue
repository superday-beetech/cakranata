<script setup lang="ts">
defineProps<{
  disabled?: boolean;
}>();

const emit = defineEmits<{
  add: [amount: number];
  exact: [];
  reset: [];
}>();

const denominations = [1_000, 2_000, 5_000, 10_000, 20_000, 50_000, 100_000];

function format(value: number): string {
  return new Intl.NumberFormat("id-ID").format(value);
}
</script>

<template>
  <div class="pos-cash-denominations">
    <div class="pos-cash-heading">
      <span>Nominal Cepat</span>
      <button type="button" :disabled="disabled" @click="emit('reset')">Reset</button>
    </div>

    <div class="pos-denomination-grid">
      <button v-for="amount in denominations" :key="amount" type="button" :disabled="disabled" @click="emit('add', amount)">+{{ format(amount) }}</button>
    </div>

    <button type="button" class="pos-exact-button" :disabled="disabled" @click="emit('exact')">
      <i class="ti ti-check"></i>
      Uang Pas
    </button>
  </div>
</template>

<style scoped>
.pos-cash-denominations {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pos-cash-heading {
  align-items: center;
  color: #64748b;
  display: flex;
  font-size: 10px;
  font-weight: 700;
  justify-content: space-between;
}

.pos-cash-heading button {
  background: transparent;
  border: 0;
  color: #dc2626;
  font-size: 10px;
  font-weight: 700;
}

.pos-denomination-grid {
  display: grid;
  gap: 6px;
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.pos-denomination-grid button {
  background: #f8fafc;
  border: 1px solid #dbe1e8;
  border-radius: 6px;
  color: #334155;
  font-size: 10px;
  font-weight: 650;
  min-height: 32px;
  padding: 5px 3px;
}

.pos-denomination-grid button:hover:not(:disabled) {
  background: #eff6ff;
  border-color: #93c5fd;
  color: #1d4ed8;
}

.pos-exact-button {
  align-items: center;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  border-radius: 7px;
  color: #047857;
  display: flex;
  font-size: 11px;
  font-weight: 750;
  gap: 6px;
  justify-content: center;
  min-height: 35px;
}

.pos-cash-denominations button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
</style>
