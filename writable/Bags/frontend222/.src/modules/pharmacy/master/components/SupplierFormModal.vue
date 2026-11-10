<script setup lang="ts">
import { computed, reactive, watch } from "vue";
import supplierService, { type Supplier, type SupplierPayload } from "../services/supplierService";
import { notify } from "@/utils/notification";

const props = defineProps<{
  supplier?: Supplier | null;
}>();

const emit = defineEmits<{
  select: [value: Supplier];
  close: [];
}>();

const isEdit = computed(() => Boolean(props.supplier?.id));
const loading = reactive({ value: false });

const form = reactive<SupplierPayload>({
  code: "",
  name: "",
  phone: null,
  email: null,
  address: null,
  is_active: 1,
});

function fillForm(supplier?: Supplier | null) {
  form.code = supplier?.code ?? "";
  form.name = supplier?.name ?? "";
  form.phone = supplier?.phone ?? null;
  form.email = supplier?.email ?? null;
  form.address = supplier?.address ?? null;
  form.is_active = Number(supplier?.is_active ?? 1);
}

watch(
  () => props.supplier,
  (supplier) => fillForm(supplier),
  { immediate: true },
);

async function submitForm() {
  if (loading.value) return;

  if (!form.code.trim() || !form.name.trim()) {
    notify.warning("Kode dan nama supplier wajib diisi.");
    return;
  }

  loading.value = true;

  try {
    const payload: SupplierPayload = {
      code: form.code.trim().toUpperCase(),
      name: form.name.trim(),
      phone: form.phone?.trim() || null,
      email: form.email?.trim() || null,
      address: form.address?.trim() || null,
      is_active: Number(form.is_active),
    };

    const response = isEdit.value ? await supplierService.update(props.supplier!.id, payload) : await supplierService.create(payload);

    const result = response.data;

    if (result.error || !result.data) {
      throw new Error(Array.isArray(result.message) ? result.message.join(", ") : result.message || "Gagal menyimpan supplier.");
    }

    notify.success(isEdit.value ? "Supplier berhasil diperbarui." : "Supplier berhasil ditambahkan.");

    emit("select", result.data);
    emit("close");
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || "Terjadi kesalahan saat menyimpan supplier.";

    notify.error(Array.isArray(message) ? message.join(", ") : message);
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <form @submit.prevent="submitForm">
    <div class="modal-body">
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label"> Kode Supplier <span class="text-danger">*</span> </label>
          <input v-model="form.code" type="text" class="form-control" maxlength="30" placeholder="Contoh: SUP-001" required />
        </div>

        <div class="col-md-8 mb-3">
          <label class="form-label"> Nama Supplier <span class="text-danger">*</span> </label>
          <input v-model="form.name" type="text" class="form-control" maxlength="150" placeholder="Nama supplier" required />
        </div>

        <div class="col-md-6 mb-3">
          <label class="form-label">Nomor Telepon</label>
          <input v-model="form.phone" type="text" class="form-control" maxlength="30" placeholder="Nomor telepon" />
        </div>

        <div class="col-md-6 mb-3">
          <label class="form-label">Email</label>
          <input v-model="form.email" type="email" class="form-control" maxlength="150" placeholder="Alamat email" />
        </div>

        <div class="col-12 mb-3">
          <label class="form-label">Alamat</label>
          <textarea v-model="form.address" class="form-control" rows="3" placeholder="Alamat supplier" />
        </div>

        <div class="col-md-6 mb-3">
          <label class="form-label">Status</label>
          <select v-model.number="form.is_active" class="form-select">
            <option :value="1">Aktif</option>
            <option :value="0">Nonaktif</option>
          </select>
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-light" :disabled="loading.value" @click="emit('close')">Batal</button>

      <button type="submit" class="btn btn-primary" :disabled="loading.value">
        <span v-if="loading.value" class="spinner-border spinner-border-sm me-1" />
        {{ loading.value ? "Menyimpan..." : "Simpan" }}
      </button>
    </div>
  </form>
</template>
