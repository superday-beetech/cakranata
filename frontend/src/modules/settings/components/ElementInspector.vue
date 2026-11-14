<script setup lang="ts">
import type { PrintElement } from "../types/printTemplate";
import TableInspector from "./TableInspector.vue";

const props = defineProps<{ element: PrintElement | null }>();
const emit = defineEmits<{
  "update-element": [Partial<PrintElement> & { id: string }];
  "delete-element": [string];
}>();

const bindingOptions: Record<string, { path: string; label: string }[]> = {
  kwitansi: [
    { path: "patient.name", label: "Nama Pasien" },
    { path: "patient.address", label: "Alamat Pasien" },
    { path: "invoice.number", label: "No. Invoice" },
    { path: "invoice.date", label: "Tanggal Invoice" },
    { path: "invoice.total", label: "Total" },
    { path: "invoice.total_in_words", label: "Total (terbilang)" },
    { path: "cashier.name", label: "Nama Kasir" },
  ],
  struk: [
    { path: "invoice.number", label: "No. Invoice" },
    { path: "invoice.date", label: "Tanggal" },
    { path: "invoice.total", label: "Total" },
    { path: "invoice.paid", label: "Dibayar" },
    { path: "invoice.change", label: "Kembalian" },
    { path: "patient.name", label: "Nama Pasien" },
  ],
  penerimaan_barang: [
    { path: "receipt.number", label: "No. Penerimaan" },
    { path: "supplier.name", label: "Nama Supplier" },
    { path: "purchase_order.number", label: "No. PO" },
  ],
};

function updateProp(key: string, value: unknown) {
  if (!props.element) return;
  emit("update-element", {
    id: props.element.id,
    props: { ...props.element.props, [key]: value },
  });
}
function updateField(key: keyof PrintElement, value: unknown) {
  if (!props.element) return;
  emit("update-element", { id: props.element.id, [key]: value } as never);
}
function onUpdateTable(id: string, tableProps: PrintElement["props"]) {
  const el = props.element;
  if (!el) return;
  emit("update-element", { id, props: { ...el.props, ...tableProps } });
}
function onImageUpload(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file || !props.element) return;
  if (file.size > 512_000) {
    alert("Ukuran gambar maksimal 500 KB");
    return;
  }
  const reader = new FileReader();
  reader.onload = () => updateProp("src", reader.result);
  reader.readAsDataURL(file);
}

function onLogoUpload(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  if (file.size > 300_000) {
    alert("Ukuran logo maksimal 300 KB");
    return;
  }
  const reader = new FileReader();
  reader.onload = () => updateProp("src", reader.result);
  reader.readAsDataURL(file);
}
</script>

<template>
  <div class="inspector">
    <div v-if="!element" class="text-muted text-center py-4">
      <i class="ti ti-click fs-1 d-block mb-2"></i>
      Klik elemen di kanvas untuk mengedit propertinya.
    </div>
    <template v-else>
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-1">
          <span class="badge bg-primary">{{ element.type }}</span>
          <small class="text-muted">ID: {{ element.id.slice(0, 8) }}…</small>
        </div>
        <div class="d-flex gap-1">
          <button class="btn btn-sm" :class="element.props.locked ? 'btn-warning' : 'btn-soft-secondary'" :title="element.props.locked ? 'Buka kunci' : 'Kunci elemen'" @click="updateProp('locked', !element.props.locked)">
            <i :class="element.props.locked ? 'ti ti-lock' : 'ti ti-lock-open'"></i>
          </button>
          <button class="btn btn-sm btn-soft-danger" @click="emit('delete-element', element.id)">
            <i class="ti ti-trash"></i>
          </button>
        </div>
      </div>

      <div v-if="element.type === 'table'" class="mb-3">
        <TableInspector
          :element-id="element.id"
          :table-props="{
            columns: element.props.columns ?? [],
            rows: element.props.rows ?? [],
            fontSize: element.props.fontSize,
            binding: element.props.binding,
          }"
          @update="onUpdateTable"
        />
      </div>

      <template v-else>
        <div v-if="element.type === 'image'" class="mb-3">
          <label class="form-label">Gambar</label>
          <input type="file" accept="image/*" class="form-control" @change="onImageUpload" />
        </div>

        <div v-if="element.type === 'qr' || element.type === 'barcode'" class="mb-3">
          <label class="form-label">Binding Data</label>
          <input :value="(element.props.binding as string) ?? ''" class="form-control" placeholder="invoice.number" @input="updateProp('binding', ($event.target as HTMLInputElement).value)" />
        </div>

        <template v-else>
          <div class="mb-3">
            <label class="form-label">Teks / Label</label>
            <textarea :value="element.props.label ?? ''" rows="3" class="form-control" placeholder="Nama Pasien" @input="updateProp('label', ($event.target as HTMLTextAreaElement).value)"></textarea>
          </div>
          <div class="mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" :checked="!!element.props.binding" @change="updateProp('binding', ($event.target as HTMLInputElement).checked ? bindingOptions.kwitansi[0].path : '')" />
              <label class="form-check-label">Teks Dinamis (binding)</label>
            </div>
          </div>
          <div v-if="element.props.binding" class="mb-3">
            <label class="form-label">Binding Path</label>
            <input :value="element.props.binding as string" class="form-control" placeholder="patient.name" list="binding-list" @input="updateProp('binding', ($event.target as HTMLInputElement).value)" />
            <datalist id="binding-list">
              <option v-for="b in bindingOptions.kwitansi" :key="b.path" :value="b.path">
                {{ b.label }}
              </option>
            </datalist>
          </div>

          <div v-if="element.type === 'logo'" class="mb-3">
            <label class="form-label">Gambar Logo</label>
            <input type="file" accept="image/*" class="form-control" @change="onLogoUpload" />
            <img v-if="element.props.src" :src="element.props.src as string" class="mt-2 border rounded" style="max-width: 100px; max-height: 100px; object-fit: contain" />
          </div>
        </template>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Font (pt)</label>
            <input :value="element.props.fontSize ?? 11" type="number" min="6" max="72" class="form-control" @input="updateProp('fontSize', Number(($event.target as HTMLInputElement).value))" />
          </div>
          <div class="col-6">
            <label class="form-label">Warna</label>
            <input :value="element.props.color ?? '#000000'" type="color" class="form-control form-control-color w-100" @input="updateProp('color', ($event.target as HTMLInputElement).value)" />
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Perataan</label>
          <div class="btn-group w-100">
            <button v-for="a in ['left', 'center', 'right']" :key="a" type="button" class="btn btn-outline-secondary" :class="{ active: (element.props.align ?? 'left') === a }" @click="updateProp('align', a)">
              <i :class="`ti ti-align-${a}`"></i>
            </button>
          </div>
        </div>
      </template>

      <div class="mb-3">
        <label class="form-label">Tampilkan jika (opsional)</label>
        <input :value="(element.props.visibleIf as string) ?? ''" class="form-control form-control-sm" placeholder="discount > 0" @input="updateProp('visibleIf', ($event.target as HTMLInputElement).value)" />
      </div>

      <hr />
      <h6 class="text-muted small text-uppercase mb-2">Posisi &amp; Ukuran (mm)</h6>
      <div class="row g-2">
        <div class="col-6">
          <label class="form-label small">X</label>
          <input :value="element.x" type="number" class="form-control form-control-sm" @input="updateField('x', Number(($event.target as HTMLInputElement).value))" />
        </div>
        <div class="col-6">
          <label class="form-label small">Y</label>
          <input :value="element.y" type="number" class="form-control form-control-sm" @input="updateField('y', Number(($event.target as HTMLInputElement).value))" />
        </div>
        <div class="col-6">
          <label class="form-label small">Width</label>
          <input :value="element.width" type="number" class="form-control form-control-sm" @input="updateField('width', Number(($event.target as HTMLInputElement).value))" />
        </div>
        <div class="col-6">
          <label class="form-label small">Height</label>
          <input :value="element.height" type="number" class="form-control form-control-sm" @input="updateField('height', Number(($event.target as HTMLInputElement).value))" />
        </div>
      </div>
    </template>
  </div>
</template>
