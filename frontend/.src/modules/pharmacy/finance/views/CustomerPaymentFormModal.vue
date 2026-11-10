<script setup lang="ts">
import { computed, reactive } from "vue";
import type { CustomerPaymentPayload } from "../services/financeService";

export interface CustomerOption {
  id: number;
  name: string;
}

const props = defineProps<{
  customers: CustomerOption[];
  paymentNumber: string;
}>();

const emit = defineEmits<{
  select: [value: CustomerPaymentPayload];
  close: [];
}>();

const form = reactive({
  customer_id: "",
  distribution_order_id: "",
  payment_date: new Date().toLocaleDateString("id-ID"),
  amount: "",
  payment_method: "transfer",
  custom_payment_method: "",
  reference_number: "",
  notes: "",
});

const paymentMethods = [
  { label: "Tunai", value: "cash" },
  { label: "Transfer", value: "transfer" },
  { label: "Debit", value: "debit" },
  { label: "Kredit", value: "credit" },
  { label: "Lainnya", value: "other" },
];

const selectedPaymentMethod = computed(() => (form.payment_method === "other" ? form.custom_payment_method.trim() : form.payment_method));

const isValid = computed(() => {
  return Number(form.customer_id) > 0 && form.payment_date !== "" && Number(form.amount) > 0 && selectedPaymentMethod.value !== "";
});

function submit(): void {
  if (!isValid.value) return;

  emit("select", {
    payment_number: props.paymentNumber,
    customer_id: Number(form.customer_id),
    distribution_order_id: form.distribution_order_id ? Number(form.distribution_order_id) : null,
    payment_date: form.payment_date,
    amount: Number(form.amount),
    payment_method: selectedPaymentMethod.value,
    reference_number: form.reference_number.trim() || null,
    notes: form.notes.trim() || null,
  });
}
</script>

<template>
  <form @submit.prevent="submit">
    <div class="row g-3">
      <div class="col-12">
        <label class="form-label">Nomor Pembayaran</label>
        <input type="text" class="form-control" :value="paymentNumber" readonly />
      </div>

      <div class="col-12">
        <label class="form-label"> Pelanggan <span class="text-danger">*</span> </label>
        <select v-model="form.customer_id" class="form-select" required>
          <option value="">Pilih pelanggan</option>
          <option v-for="customer in customers" :key="customer.id" :value="String(customer.id)">
            {{ customer.name }}
          </option>
        </select>
        <small v-if="customers.length === 0" class="text-muted"> Data pelanggan belum tersedia. </small>
      </div>

      <div class="col-12">
        <label class="form-label">Distribution Order ID</label>
        <input v-model="form.distribution_order_id" type="number" min="1" class="form-control" placeholder="Opsional" />
        <small class="text-muted"> Kosongkan jika pembayaran tidak dikaitkan dengan pesanan tertentu. </small>
      </div>

      <div class="col-md-6">
        <label class="form-label"> Tanggal Pembayaran <span class="text-danger">*</span> </label>
        <input v-model="form.payment_date" type="date" class="form-control" required />
      </div>

      <div class="col-md-6">
        <label class="form-label"> Jumlah Pembayaran <span class="text-danger">*</span> </label>
        <input v-model="form.amount" type="number" min="1" step="0.01" class="form-control" placeholder="Masukkan jumlah" required />
      </div>

      <div class="col-12">
        <label class="form-label"> Metode Pembayaran <span class="text-danger">*</span> </label>
        <select v-model="form.payment_method" class="form-select" required>
          <option v-for="method in paymentMethods" :key="method.value" :value="method.value">
            {{ method.label }}
          </option>
        </select>
      </div>

      <div v-if="form.payment_method === 'other'" class="col-12">
        <label class="form-label"> Nama Metode Pembayaran <span class="text-danger">*</span> </label>
        <input v-model="form.custom_payment_method" type="text" class="form-control" placeholder="Masukkan metode pembayaran" required />
      </div>

      <div class="col-12">
        <label class="form-label">Nomor Referensi</label>
        <input v-model="form.reference_number" type="text" class="form-control" placeholder="Nomor transfer atau referensi" />
      </div>

      <div class="col-12">
        <label class="form-label">Catatan</label>
        <textarea v-model="form.notes" class="form-control" rows="3" placeholder="Catatan pembayaran"></textarea>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <button type="button" class="btn btn-light" @click="emit('close')">Batal</button>

      <button type="submit" class="btn btn-primary" :disabled="!isValid">Simpan Pembayaran</button>
    </div>
  </form>
</template>
