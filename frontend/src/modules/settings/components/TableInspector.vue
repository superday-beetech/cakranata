<script setup lang="ts">
import type { TableColumn, PrintElement } from "../types/printTemplate";
const props = defineProps<{
  elementId: string;
  tableProps: { columns: TableColumn[]; rows: Record<string, string>[]; fontSize?: number };
}>();
const emit = defineEmits<{ update: [id: string, props: PrintElement["props"]] }>();
function addColumn() {
  emit("update", props.elementId, {
    ...props.tableProps,
    columns: [
      ...props.tableProps.columns,
      {
        key: `col_${Date.now()}`,
        label: "Kolom Baru",
        width: 100,
        align: "left" as const,
      },
    ],
  });
}
function removeColumn(i: number) {
  emit("update", props.elementId, {
    ...props.tableProps,
    columns: props.tableProps.columns.filter((_, idx) => idx !== i),
  });
}
function updateColumn(i: number, patch: Partial<TableColumn>) {
  emit("update", props.elementId, {
    ...props.tableProps,
    columns: props.tableProps.columns.map((c, idx) => (idx === i ? { ...c, ...patch } : c)),
  });
}
function addRow() {
  const row: Record<string, string> = {};
  props.tableProps.columns.forEach((c) => (row[c.key] = ""));
  emit("update", props.elementId, { ...props.tableProps, rows: [...props.tableProps.rows, row] });
}
function removeRow(i: number) {
  emit("update", props.elementId, {
    ...props.tableProps,
    rows: props.tableProps.rows.filter((_, idx) => idx !== i),
  });
}
function updateCell(ri: number, key: string, value: string) {
  emit("update", props.elementId, {
    ...props.tableProps,
    rows: props.tableProps.rows.map((r, i) => (i === ri ? { ...r, [key]: value } : r)),
  });
}
</script>

<template>
  <div>
    <h6 class="text-muted small text-uppercase mb-2">Kolom</h6>
    <div v-for="(col, i) in tableProps.columns" :key="i" class="border rounded p-2 mb-2">
      <div class="row g-1 align-items-center">
        <div class="col-5"><input :value="col.label" class="form-control form-control-sm" placeholder="Label" @input="updateColumn(i, { label: ($event.target as HTMLInputElement).value })" /></div>
        <div class="col-4"><input :value="col.key" class="form-control form-control-sm" placeholder="key" @input="updateColumn(i, { key: ($event.target as HTMLInputElement).value })" /></div>
        <div class="col-2"><input :value="col.width" type="number" class="form-control form-control-sm" @input="updateColumn(i, { width: Number(($event.target as HTMLInputElement).value) })" /></div>
        <div class="col-1"><button class="btn btn-sm btn-soft-danger w-100" @click="removeColumn(i)">×</button></div>
      </div>
    </div>
    <button class="btn btn-sm btn-outline-primary w-100 mb-3" @click="addColumn"><i class="ti ti-plus me-1"></i> Tambah Kolom</button>

    <h6 class="text-muted small text-uppercase mb-2">Data Contoh (Preview)</h6>
    <div v-for="(row, ri) in tableProps.rows" :key="ri" class="border rounded p-2 mb-2">
      <div class="d-flex justify-content-end mb-1">
        <button class="btn btn-sm btn-soft-danger" @click="removeRow(ri)">×</button>
      </div>
      <div class="row g-1">
        <div v-for="col in tableProps.columns" :key="col.key" class="col-6">
          <input :value="row[col.key]" class="form-control form-control-sm" :placeholder="col.label" @input="updateCell(ri, col.key, ($event.target as HTMLInputElement).value)" />
        </div>
      </div>
    </div>
    <button class="btn btn-sm btn-outline-secondary w-100" @click="addRow"><i class="ti ti-plus me-1"></i> Tambah Baris</button>
  </div>
</template>
