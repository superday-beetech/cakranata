<script setup lang="ts">
import { formatCurrency } from "../../utils/format";
import type { PosAdditionalCharge } from "../../types/sales";

defineProps<{
  charges: PosAdditionalCharge[];
  disabled?: boolean;
}>();

const emit = defineEmits<{
  add: [];
  update: [id: string, field: "type" | "description" | "amount", value: string | number];
  remove: [id: string];
}>();

const chargeTypes = [
  { value: "donation", label: "Donasi" },
  { value: "shipping", label: "Ongkos kirim" },
  { value: "packaging", label: "Kemasan" },
  { value: "service", label: "Biaya layanan" },
  { value: "other", label: "Lainnya" },
] as const;
</script>

<template>
  <div class="pos-charges">
    <div class="pos-charges-bar">
      <small>
        Total:
        <strong>{{ formatCurrency(charges.reduce((sum, charge) => sum + charge.amount, 0)) }}</strong>
      </small>

      <button type="button" class="btn btn-soft-primary btn-sm" :disabled="disabled" @click="emit('add')">
        <i class="ti ti-plus"></i>
        Tambah
      </button>
    </div>

    <small v-if="!charges.length" class="pos-muted-center">Belum ada biaya tambahan.</small>

    <div v-for="charge in charges" :key="charge.id" class="pos-charge-row">
      <select class="form-select" aria-label="Jenis biaya" :value="charge.type" :disabled="disabled" @change="emit('update', charge.id, 'type', ($event.target as HTMLSelectElement).value)">
        <option v-for="type in chargeTypes" :key="type.value" :value="type.value">
          {{ type.label }}
        </option>
      </select>

      <input
        type="text"
        class="form-control"
        placeholder="Keterangan"
        aria-label="Keterangan biaya"
        :value="charge.description"
        :disabled="disabled"
        @input="emit('update', charge.id, 'description', ($event.target as HTMLInputElement).value)"
      />

      <input
        type="number"
        class="form-control text-end"
        min="0"
        step="1"
        placeholder="Nominal"
        aria-label="Nominal biaya"
        :value="charge.amount"
        :disabled="disabled"
        @change="emit('update', charge.id, 'amount', Math.max(0, Number(($event.target as HTMLInputElement).value) || 0))"
      />

      <button type="button" class="btn btn-soft-danger btn-icon btn-sm" title="Hapus biaya" aria-label="Hapus biaya" :disabled="disabled" @click="emit('remove', charge.id)">
        <i class="ti ti-x"></i>
      </button>
    </div>
  </div>
</template>
