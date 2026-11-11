<script setup lang="ts">
import type { PrintElement } from "../types/printTemplate";
import TableInspector from "./TableInspector.vue";
const props = defineProps<{ element: PrintElement | null }>();
const emit = defineEmits<{
  "update-element": [Partial<PrintElement> & { id: string }];
  "delete-element": [string];
}>();
function updateProp(key: string, value: unknown) {
  if (!props.element) return;
  emit("update-element", { id: props.element.id, props: { ...props.element.props, [key]: value } });
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
</script>

<template>
  <div class="inspector">
    <div v-if="!element" class="text-muted text-center py-4">
      <i class="ti ti-click fs-1 d-block mb-2"></i>
      Klik elemen di kanvas untuk mengedit propertinya.
    </div>
    <template v-else>
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center">
          <span class="badge bg-primary me-2">{{ element.type }}</span>
          <small class="text-muted">ID: {{ element.id.slice(0, 8) }}…</small>
        </div>
        <button class="btn btn-sm btn-soft-danger" @click="emit('delete-element', element.id)">
          <i class="ti ti-trash"></i>
        </button>
      </div>

      <div v-if="element.type === 'table'" class="mb-3">
        <TableInspector :element-id="element.id" :table-props="{ columns: element.props.columns ?? [], rows: element.props.rows ?? [], fontSize: element.props.fontSize }" @update="onUpdateTable" />
      </div>

      <template v-else>
        <div class="mb-3">
          <label class="form-label">Teks / Label</label>
          <textarea :value="element.props.label ?? ''" rows="3" class="form-control" placeholder="Nama Pasien" @input="updateProp('label', ($event.target as HTMLTextAreaElement).value)"></textarea>
        </div>
        <div class="mb-3">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" :checked="!!element.props.binding" @change="updateProp('binding', ($event.target as HTMLInputElement).checked ? 'patient.name' : '')" />
            <label class="form-check-label">Teks Dinamis (binding)</label>
          </div>
        </div>
        <div v-if="element.props.binding !== undefined && element.props.binding !== ''" class="mb-3">
          <label class="form-label">Binding Path</label>
          <input :value="element.props.binding as string" class="form-control" placeholder="patient.name" @input="updateProp('binding', ($event.target as HTMLInputElement).value)" />
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Font (px)</label>
            <input :value="element.props.fontSize ?? 12" type="number" min="6" max="72" class="form-control" @input="updateProp('fontSize', Number(($event.target as HTMLInputElement).value))" />
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

      <hr />
      <h6 class="text-muted small text-uppercase mb-2">Posisi & Ukuran (px)</h6>
      <div class="row g-2">
        <div class="col-6"><label class="form-label small">X</label> <input :value="element.x" type="number" class="form-control form-control-sm" @input="updateField('x', Number(($event.target as HTMLInputElement).value))" /></div>
        <div class="col-6"><label class="form-label small">Y</label> <input :value="element.y" type="number" class="form-control form-control-sm" @input="updateField('y', Number(($event.target as HTMLInputElement).value))" /></div>
        <div class="col-6">
          <label class="form-label small">Width</label> <input :value="element.width" type="number" class="form-control form-control-sm" @input="updateField('width', Number(($event.target as HTMLInputElement).value))" />
        </div>
        <div class="col-6">
          <label class="form-label small">Height</label> <input :value="element.height" type="number" class="form-control form-control-sm" @input="updateField('height', Number(($event.target as HTMLInputElement).value))" />
        </div>
      </div>
    </template>
  </div>
</template>
