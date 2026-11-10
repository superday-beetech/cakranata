<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";

import { notify } from "@/utils/notification";
import organizationService from "../services/organizationService";

import type { Organization, CreateOrganizationPayload, UpdateOrganizationPayload } from "../types/organization";

const props = defineProps<{
  organization: Organization | null;
}>();

const emit = defineEmits<{
  close: [];
  select: [value: Organization];
}>();

const isEdit = computed(() => props.organization !== null);
const loading = ref(false);

const form = reactive({
  code: "",
  slug: "",
  name: "",
  legal_name: "",
  organization_type: "",
  timezone: "Asia/Jakarta",
  currency: "IDR",
});

const errors = ref<Record<string, string>>({});

function slugify(value: string): string {
  return value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}

watch(
  () => props.organization,
  (organization) => {
    errors.value = {};

    if (organization) {
      form.code = organization.code;
      form.slug = organization.slug;
      form.name = organization.name;
      form.legal_name = organization.legal_name ?? "";
      form.organization_type = organization.organization_type;
      form.timezone = organization.timezone;
      form.currency = organization.currency;
    } else {
      form.code = "";
      form.slug = "";
      form.name = "";
      form.legal_name = "";
      form.organization_type = "";
      form.timezone = "Asia/Jakarta";
      form.currency = "IDR";
    }
  },
  { immediate: true },
);

watch(
  () => form.name,
  (name) => {
    if (!isEdit.value) {
      form.slug = slugify(name);
    }
  },
);

function getErrorMessage(error: any): string {
  const message = error?.response?.data?.message;

  if (Array.isArray(message)) return message.join(", ");
  if (typeof message === "string") return message;

  return error?.message || "Terjadi kesalahan.";
}

function validate(): boolean {
  errors.value = {};

  if (!isEdit.value && !form.code.trim()) {
    errors.value.code = "Kode organisasi wajib diisi.";
  }

  if (!isEdit.value && !form.slug.trim()) {
    errors.value.slug = "Slug organisasi wajib diisi.";
  }

  if (!form.name.trim()) {
    errors.value.name = "Nama organisasi wajib diisi.";
  }

  if (!form.organization_type.trim() && !isEdit.value) {
    errors.value.organization_type = "Jenis organisasi wajib diisi.";
  }

  if (!form.timezone.trim()) {
    errors.value.timezone = "Timezone wajib diisi.";
  }

  if (!/^[A-Z]{3}$/.test(form.currency.trim().toUpperCase())) {
    errors.value.currency = "Mata uang harus terdiri dari 3 huruf, misalnya IDR.";
  }

  return Object.keys(errors.value).length === 0;
}

async function submit(): Promise<void> {
  if (loading.value || !validate()) return;

  loading.value = true;

  try {
    let response;

    if (isEdit.value && props.organization) {
      const payload: UpdateOrganizationPayload = {
        name: form.name.trim(),
        legal_name: form.legal_name.trim() || null,
        organization_type: form.organization_type,
        timezone: form.timezone.trim(),
        currency: form.currency.trim().toUpperCase(),
      };

      response = await organizationService.update(props.organization.id, payload);
    } else {
      const payload: CreateOrganizationPayload = {
        code: form.code.trim().toUpperCase(),
        slug: form.slug.trim().toLowerCase(),
        name: form.name.trim(),
        legal_name: form.legal_name.trim() || null,
        organization_type: form.organization_type.trim(),
        timezone: form.timezone.trim(),
        currency: form.currency.trim().toUpperCase(),
      };

      response = await organizationService.create(payload);
    }

    const result = response.data;

    if (result.error || !result.data) {
      throw new Error(typeof result.message === "string" ? result.message : "Gagal menyimpan organisasi.");
    }

    notify.success(isEdit.value ? "Organisasi berhasil diperbarui." : "Organisasi berhasil ditambahkan.");

    emit("close");
    emit("select", result.data);
  } catch (error: any) {
    const responseErrors = error?.response?.data?.errors;

    if (responseErrors && typeof responseErrors === "object") {
      errors.value = Object.fromEntries(Object.entries(responseErrors).map(([key, value]) => [key, Array.isArray(value) ? value.join(", ") : String(value)]));
    }

    notify.error(getErrorMessage(error));
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <form @submit.prevent="submit">
    <div class="row g-3">
      <template v-if="!isEdit">
        <div class="col-md-6">
          <label class="form-label"> Kode Organisasi <span class="text-danger">*</span> </label>

          <input v-model="form.code" type="text" class="form-control" :class="{ 'is-invalid': errors.code }" placeholder="Contoh: ACN" maxlength="50" />

          <div v-if="errors.code" class="invalid-feedback">
            {{ errors.code }}
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label"> Slug <span class="text-danger">*</span> </label>

          <input v-model="form.slug" type="text" class="form-control" :class="{ 'is-invalid': errors.slug }" placeholder="contoh: adi-cakra-nata" maxlength="100" />

          <div v-if="errors.slug" class="invalid-feedback">
            {{ errors.slug }}
          </div>
        </div>
      </template>

      <div class="col-12">
        <label class="form-label"> Nama Organisasi <span class="text-danger">*</span> </label>

        <input v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': errors.name }" placeholder="Nama organisasi" maxlength="150" />

        <div v-if="errors.name" class="invalid-feedback">
          {{ errors.name }}
        </div>
      </div>

      <div class="col-12">
        <label class="form-label">Nama Legal</label>

        <input v-model="form.legal_name" type="text" class="form-control" placeholder="Nama legal organisasi" maxlength="200" />
      </div>
      <div class="mb-3 col-md-6">
        <label for="organization_type" class="form-label"> Jenis Organisasi <span class="text-danger">*</span> </label>

        <select id="organization_type" v-model="form.organization_type" class="form-select" required>
          <option value="" disabled>Pilih jenis organisasi</option>
          <option value="business">Perusahaan / Badan Usaha</option>
          <option value="cooperative">Koperasi</option>
          <option value="foundation">Yayasan</option>
          <option value="government">Instansi Pemerintah</option>
          <option value="community">Komunitas / Kelompok</option>
          <option value="non_profit">Organisasi Nirlaba</option>
          <option value="other">Lainnya</option>
        </select>
      </div>
      <!-- <div v-if="!isEdit" class="col-md-6">
        <label class="form-label"> Jenis Organisasi <span class="text-danger">*</span> </label>

        <input v-model="form.organization_type" type="text" class="form-control" :class="{ 'is-invalid': errors.organization_type }" placeholder="business" maxlength="50" />

        <div v-if="errors.organization_type" class="invalid-feedback">
          {{ errors.organization_type }}
        </div>
      </div> -->

      <div class="col-md-6">
        <label class="form-label"> Timezone <span class="text-danger">*</span> </label>

        <input v-model="form.timezone" type="text" class="form-control" :class="{ 'is-invalid': errors.timezone }" placeholder="Asia/Jakarta" maxlength="64" />

        <div v-if="errors.timezone" class="invalid-feedback">
          {{ errors.timezone }}
        </div>
      </div>

      <div class="col-md-6">
        <label class="form-label"> Mata Uang <span class="text-danger">*</span> </label>

        <input v-model="form.currency" type="text" class="form-control text-uppercase" :class="{ 'is-invalid': errors.currency }" placeholder="IDR" maxlength="3" />

        <div v-if="errors.currency" class="invalid-feedback">
          {{ errors.currency }}
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <button type="button" class="btn btn-light" :disabled="loading" @click="emit('close')">Batal</button>

      <button type="submit" class="btn btn-primary" :disabled="loading">
        <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>

        {{ isEdit ? "Simpan Perubahan" : "Simpan Organisasi" }}
      </button>
    </div>
  </form>
</template>
