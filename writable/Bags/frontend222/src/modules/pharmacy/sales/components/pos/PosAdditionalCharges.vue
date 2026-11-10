<script setup lang="ts">
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
  { value: "shipping", label: "Ongkos Kirim" },
  { value: "packaging", label: "Kemasan" },
  { value: "service", label: "Biaya Layanan" },
  { value: "other", label: "Lainnya" },
] as const;

function currency(value: number): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(value);
}
</script>

<template>
  <section class="pos-charge-section">
    <div class="pos-section-heading">
      <div>
        <h6>Biaya Tambahan</h6>
        <small>Biaya di luar harga produk.</small>
      </div>

      <button type="button" class="pos-add-button" :disabled="disabled" @click="emit('add')">
        <i class="ti ti-plus"></i>
        Tambah
      </button>
    </div>

    <div v-if="!charges.length" class="pos-charge-empty">Belum ada biaya tambahan.</div>

    <div v-for="charge in charges" :key="charge.id" class="pos-charge-row">
      <select :value="charge.type" :disabled="disabled" @change="emit('update', charge.id, 'type', ($event.target as HTMLSelectElement).value)">
        <option v-for="type in chargeTypes" :key="type.value" :value="type.value">
          {{ type.label }}
        </option>
      </select>

      <input type="text" :value="charge.description" placeholder="Keterangan" :disabled="disabled" @input="emit('update', charge.id, 'description', ($event.target as HTMLInputElement).value)" />

      <input
        type="number"
        min="0"
        step="1"
        :value="charge.amount"
        placeholder="Nominal"
        class="pos-charge-amount"
        :disabled="disabled"
        @change="emit('update', charge.id, 'amount', Math.max(0, Number(($event.target as HTMLInputElement).value) || 0))"
      />

      <button type="button" class="pos-charge-remove" title="Hapus biaya" :disabled="disabled" @click="emit('remove', charge.id)">
        <i class="ti ti-x"></i>
      </button>
    </div>

    <div v-if="charges.length" class="pos-charge-total">
      <span>Total biaya tambahan</span>
      <strong>
        {{ currency(charges.reduce((sum, charge) => sum + charge.amount, 0)) }}
      </strong>
    </div>
  </section>
</template>

<style>
.pos-charge-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pos-section-heading {
  align-items: center;
  display: flex;
  justify-content: space-between;
}

.pos-section-heading h6 {
  font-size: 12px;
  font-weight: 750;
  margin: 0 0 3px;
}

.pos-section-heading small,
.pos-charge-empty {
  color: #94a3b8;
  font-size: 10px;
}

.pos-add-button {
  align-items: center;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 6px;
  color: #2563eb;
  display: inline-flex;
  font-size: 11px;
  font-weight: 700;
  gap: 5px;
  padding: 6px 9px;
}

.pos-charge-row {
  align-items: center;
  display: grid;
  gap: 7px;
  grid-template-columns: 115px minmax(90px, 1fr) 120px 28px;
}

.pos-charge-row input,
.pos-charge-row select {
  background: white;
  border: 1px solid #dbe1e8;
  border-radius: 6px;
  font-size: 11px;
  min-height: 34px;
  min-width: 0;
  padding: 6px 8px;
  width: 100%;
}

.pos-charge-amount {
  text-align: right;
}

.pos-charge-remove {
  background: transparent;
  border: 0;
  color: #dc2626;
  font-size: 15px;
}

.pos-charge-total {
  align-items: center;
  border-top: 1px dashed #e2e8f0;
  color: #64748b;
  display: flex;
  font-size: 11px;
  justify-content: space-between;
  padding-top: 9px;
}

.pos-charge-total strong {
  color: #111827;
}

@media (max-width: 600px) {
  .pos-charge-row {
    grid-template-columns: 1fr 1fr 28px;
  }

  .pos-charge-row select {
    grid-column: 1 / 2;
  }

  .pos-charge-row input:nth-of-type(1) {
    grid-column: 2 / 3;
  }

  .pos-charge-row input:nth-of-type(2) {
    grid-column: 1 / 3;
  }

  .pos-charge-remove {
    grid-column: 3;
    grid-row: 1 / 3;
  }
}
</style>
