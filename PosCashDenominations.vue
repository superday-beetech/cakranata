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
  <div>
    <div class="pos-denoms-head">
      <span>Nominal cepat</span>
      <button type="button" class="pos-link-danger" :disabled="disabled" @click="emit('reset')">Reset</button>
    </div>

    <div class="pos-denoms">
      <button v-for="amount in denominations" :key="amount" type="button" :disabled="disabled" @click="emit('add', amount)">+{{ format(amount) }}</button>

      <button type="button" class="is-exact" :disabled="disabled" @click="emit('exact')">
        <i class="ti ti-check"></i>
        Uang pas
      </button>
    </div>
  </div>
</template>
