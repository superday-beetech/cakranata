<script setup lang="ts">
import { ref, watch } from "vue";
import { notify } from "@/utils/notification";

import unitService, { type Unit, type UnitPayload } from "../services/unitService";

const props = defineProps<{
  unit?: Unit | null;
}>();

const emit = defineEmits<{
  select: [value: Unit];
  close: [];
}>();

const saving = ref(false);

const form = ref<UnitPayload>({
  name: "",
  abbreviation: "",
});

watch(
  () => props.unit,
  (unit) => {
    form.value = {
      name: unit?.name ?? "",
      abbreviation: unit?.abbreviation ?? "",
    };
  },
  { immediate: true },
);

function validateForm(): boolean {
  if (form.value.name.trim().length < 2) {
    notify.warning("Nama satuan minimal 2 karakter.");
    return false;
  }

  if (form.value.name.trim().length > 60) {
    notify.warning("Nama satuan maksimal 60 karakter.");
    return false;
  }

  if (!form.value.abbreviation.trim()) {
    notify.warning("Singkatan satuan wajib diisi.");
    return false;
  }

  if (form.value.abbreviation.trim().length > 20) {
    notify.warning("Singkatan maksimal 20 karakter.");
    return false;
  }

  return true;
}

function getErrorMessage(error: any): string {
  const message = error?.response?.data?.message;

  if (Array.isArray(message)) {
    return message.join(", ");
  }

  if (typeof message === "string") {
    return message;
  }

  return error?.message || "Terjadi kesalahan saat menyimpan satuan.";
}

async function save(): Promise<void> {
  if (!validateForm()) return;

  saving.value = true;

  const payload: UnitPayload = {
    name: form.value.name.trim(),
    abbreviation: form.value.abbreviation.trim(),
  };

  try {
    const response = props.unit ? await unitService.update(props.unit.id, payload) : await unitService.create(payload);

    const result = response.data;

    if (result.error || !result.data) {
      throw new Error(typeof result.message === "string" ? result.message : "Gagal menyimpan satuan.");
    }

    notify.success(props.unit ? "Satuan berhasil diperbarui." : "Satuan berhasil ditambahkan.");

    emit("select", result.data);
    emit("close");
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <form @submit.prevent="save">
    <div class="mb-3">
      <label for="unit-name" class="form-label"> Nama Satuan <span class="text-danger">*</span> </label>

      <input id="unit-name" v-model="form.name" type="text" class="form-control" maxlength="60" placeholder="Contoh: Botol" :disabled="saving" required />
    </div>

    <div class="mb-3">
      <label for="unit-abbreviation" class="form-label"> Singkatan <span class="text-danger">*</span> </label>

      <input id="unit-abbreviation" v-model="form.abbreviation" type="text" class="form-control" maxlength="20" placeholder="Contoh: btl" :disabled="saving" required />
    </div>

    <div class="d-flex justify-content-end gap-2">
      <button type="button" class="btn btn-light" :disabled="saving" @click="emit('close')">Batal</button>

      <button type="submit" class="btn btn-primary" :disabled="saving">
        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>

        {{ saving ? "Menyimpan..." : "Simpan" }}
      </button>
    </div>
  </form>
</template>
