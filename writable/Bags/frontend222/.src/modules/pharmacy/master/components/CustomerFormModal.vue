<script setup lang="ts">
import { computed, reactive, watch } from "vue";
import customerService, { type Customer, type CustomerPayload } from "../services/customerService";
import { notify } from "@/utils/notification";

const props = defineProps<{
  customer?: Customer | null;
}>();

const emit = defineEmits<{
  select: [value: Customer];
  close: [];
}>();

const isEdit = computed(() => Boolean(props.customer?.id));
const loading = reactive({ value: false });

const form = reactive<CustomerPayload>({
  code: "",
  name: "",
  customer_type: null,
  phone: null,
  email: null,
  address: null,
  is_active: 1,
});

function fillForm(customer?: Customer | null) {
  form.code = customer?.code ?? "";
  form.name = customer?.name ?? "";
  form.customer_type = customer?.customer_type ?? null;
  form.phone = customer?.phone ?? null;
  form.email = customer?.email ?? null;
  form.address = customer?.address ?? null;
  form.is_active = Number(customer?.is_active ?? 1);
}

watch(
  () => props.customer,
  (customer) => fillForm(customer),
  { immediate: true },
);

async function submitForm() {
  if (loading.value) return;

  if (!(form.code ?? "").trim()) {
    notify.warning("Kode dan nama customer wajib diisi.");
    return;
  }

  loading.value = true;

  try {
    const payload: CustomerPayload = {
      code: (form.code ?? "").trim().toUpperCase(),
      name: form.name.trim(),
      customer_type: form.customer_type?.trim() || null,
      phone: form.phone?.trim() || null,
      email: form.email?.trim() || null,
      address: form.address?.trim() || null,
      is_active: Number(form.is_active),
    };

    const response = isEdit.value ? await customerService.update(props.customer!.id, payload) : await customerService.create(payload);

    const result = response.data;

    if (result.error || !result.data) {
      throw new Error(Array.isArray(result.message) ? result.message.join(", ") : result.message || "Gagal menyimpan customer.");
    }

    notify.success(isEdit.value ? "Customer berhasil diperbarui." : "Customer berhasil ditambahkan.");

    emit("select", result.data);
    emit("close");
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || "Terjadi kesalahan saat menyimpan customer.";

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
          <label class="form-label"> Kode Customer <span class="text-danger">*</span> </label>
          <input v-model="form.code" type="text" class="form-control" maxlength="50" placeholder="Contoh: CUS-001" required />
        </div>

        <div class="col-md-8 mb-3">
          <label class="form-label"> Nama Customer <span class="text-danger">*</span> </label>
          <input v-model="form.name" type="text" class="form-control" maxlength="150" placeholder="Nama customer" required />
        </div>

        <div class="col-md-6 mb-3">
          <label class="form-label">Jenis Customer</label>
          <input v-model="form.customer_type" type="text" class="form-control" maxlength="30" placeholder="Contoh: retail, grosir, instansi" />
        </div>

        <div class="col-md-6 mb-3">
          <label class="form-label">Nomor Telepon</label>
          <input v-model="form.phone" type="text" class="form-control" maxlength="30" placeholder="Nomor telepon" />
        </div>

        <div class="col-12 mb-3">
          <label class="form-label">Email</label>
          <input v-model="form.email" type="email" class="form-control" maxlength="150" placeholder="Alamat email" />
        </div>

        <div class="col-12 mb-3">
          <label class="form-label">Alamat</label>
          <textarea v-model="form.address" class="form-control" rows="3" placeholder="Alamat customer" />
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
