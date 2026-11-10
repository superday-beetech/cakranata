<script setup lang="ts">
import { computed, reactive, watch } from "vue";

import { notify } from "@/utils/notification";
import organizationUnitService from "../services/organizationUnitService";

import type { OrganizationUnit, CreateOrganizationUnitPayload, UpdateOrganizationUnitPayload } from "../types/organizationUnit";

interface Props {
  organizationId: number;
  unit?: OrganizationUnit | null;
  units?: OrganizationUnit[];
}

const props = withDefaults(defineProps<Props>(), {
  unit: null,
  units: () => [],
});

const emit = defineEmits<{
  close: [];
  select: [value: OrganizationUnit];
}>();

const isEdit = computed(() => props.unit !== null);

const form = reactive({
  parent_id: null as number | null,
  code: "",
  name: "",
  unit_type: "",
});

const errors = reactive({
  parent_id: "",
  code: "",
  name: "",
  unit_type: "",
});

const unitTypes = [
  { value: "head_office", label: "Kantor Pusat" },
  { value: "branch", label: "Cabang" },
  { value: "division", label: "Divisi" },
  { value: "department", label: "Departemen" },
  { value: "warehouse", label: "Gudang" },
  { value: "operational_location", label: "Lokasi Operasional" },
  { value: "other", label: "Lainnya" },
];

const parentOptions = computed(() =>
  props.units.filter((item) => {
    if (!props.unit) return true;

    return item.id !== props.unit.id;
  }),
);

watch(
  () => props.unit,
  (unit) => {
    form.parent_id = unit?.parent_id ?? null;
    form.code = unit?.code ?? "";
    form.name = unit?.name ?? "";
    form.unit_type = unit?.unit_type ?? "";

    clearErrors();
  },
  { immediate: true },
);

function clearErrors(): void {
  errors.parent_id = "";
  errors.code = "";
  errors.name = "";
  errors.unit_type = "";
}

function validate(): boolean {
  clearErrors();

  if (!form.code.trim()) {
    errors.code = "Kode unit wajib diisi.";
  }

  if (!form.name.trim()) {
    errors.name = "Nama unit wajib diisi.";
  }

  if (!form.unit_type) {
    errors.unit_type = "Jenis unit wajib dipilih.";
  }

  if (props.unit && form.parent_id !== null && Number(form.parent_id) === props.unit.id) {
    errors.parent_id = "Unit tidak dapat menjadi induk bagi dirinya sendiri.";
  }

  return !Object.values(errors).some(Boolean);
}

function getErrorMessage(error: any): string {
  const message = error?.response?.data?.message;

  if (Array.isArray(message)) return message.join(", ");
  if (typeof message === "string") return message;

  return error?.message || "Terjadi kesalahan.";
}

async function submit(): Promise<void> {
  if (!validate()) return;

  const payload = {
    parent_id: form.parent_id ? Number(form.parent_id) : null,
    code: form.code.trim(),
    name: form.name.trim(),
    unit_type: form.unit_type,
  };

  try {
    let response;

    if (isEdit.value && props.unit) {
      const updatePayload: UpdateOrganizationUnitPayload = payload;

      response = await organizationUnitService.updateUnit(props.organizationId, props.unit.id, updatePayload);
    } else {
      const createPayload: CreateOrganizationUnitPayload = payload;

      response = await organizationUnitService.createUnit(props.organizationId, createPayload);
    }

    const result = response.data;

    if (result.error || !result.data) {
      throw new Error(typeof result.message === "string" ? result.message : "Gagal menyimpan unit organisasi.");
    }

    notify.success(isEdit.value ? "Unit organisasi berhasil diperbarui." : "Unit organisasi berhasil ditambahkan.");

    emit("close");
    emit("select", result.data);
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  }
}
</script>

<template>
  <form @submit.prevent="submit">
    <div class="modal-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="unit-code" class="form-label"> Kode Unit <span class="text-danger">*</span> </label>

          <input id="unit-code" v-model="form.code" type="text" class="form-control" :class="{ 'is-invalid': errors.code }" maxlength="50" placeholder="Contoh: HO, CAB-BDG, WH-01" />

          <div v-if="errors.code" class="invalid-feedback">
            {{ errors.code }}
          </div>
        </div>

        <div class="col-md-6 mb-3">
          <label for="unit-name" class="form-label"> Nama Unit <span class="text-danger">*</span> </label>

          <input id="unit-name" v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': errors.name }" maxlength="150" placeholder="Nama unit organisasi" />

          <div v-if="errors.name" class="invalid-feedback">
            {{ errors.name }}
          </div>
        </div>

        <div class="col-md-6 mb-3">
          <label for="unit-type" class="form-label"> Jenis Unit <span class="text-danger">*</span> </label>

          <select id="unit-type" v-model="form.unit_type" class="form-select" :class="{ 'is-invalid': errors.unit_type }">
            <option value="" disabled>Pilih jenis unit</option>

            <option v-for="type in unitTypes" :key="type.value" :value="type.value">
              {{ type.label }}
            </option>
          </select>

          <div v-if="errors.unit_type" class="invalid-feedback">
            {{ errors.unit_type }}
          </div>
        </div>

        <div class="col-md-6 mb-3">
          <label for="unit-parent" class="form-label"> Unit Induk </label>

          <select id="unit-parent" v-model="form.parent_id" class="form-select" :class="{ 'is-invalid': errors.parent_id }">
            <option :value="null">Tidak memiliki induk</option>

            <option v-for="item in parentOptions" :key="item.id" :value="item.id">{{ item.code }} - {{ item.name }}</option>
          </select>

          <div v-if="errors.parent_id" class="invalid-feedback">
            {{ errors.parent_id }}
          </div>

          <small class="text-muted"> Kosongkan jika unit merupakan unit tertinggi. </small>
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-light" @click="emit('close')">Batal</button>

      <button type="submit" class="btn btn-primary">
        <i class="ti ti-device-floppy me-1"></i>
        {{ isEdit ? "Simpan Perubahan" : "Simpan Unit" }}
      </button>
    </div>
  </form>
</template>
