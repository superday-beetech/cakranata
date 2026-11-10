<script setup lang="ts">
import { computed, reactive } from "vue";
import type { ExpensePayload } from "../services/financeService";

const props = defineProps<{
  expenseNumber: string;
}>();

const emit = defineEmits<{
  select: [value: ExpensePayload];
  close: [];
}>();

const categories = [
  { label: "Transportasi", value: "transportation" },
  { label: "Listrik dan Air", value: "utilities" },
  { label: "Sewa", value: "rent" },
  { label: "Gaji dan Upah", value: "salary" },
  { label: "Perlengkapan", value: "supplies" },
  { label: "Administrasi", value: "administration" },
  { label: "Operasional", value: "operational" },
  { label: "Lainnya", value: "other" },
];

const form = reactive({
  expense_date: new Date().toLocaleDateString("en-CA"),
  category: "operational",
  custom_category: "",
  description: "",
  amount: "",
  payment_method: "cash",
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

const selectedCategory = computed(() => (form.category === "other" ? form.custom_category.trim() : form.category));

const selectedPaymentMethod = computed(() => (form.payment_method === "other" ? form.custom_payment_method.trim() : form.payment_method));

const isValid = computed(() => form.expense_date !== "" && selectedCategory.value !== "" && form.description.trim() !== "" && Number(form.amount) > 0 && selectedPaymentMethod.value !== "");

function submit(): void {
  if (!isValid.value) return;

  emit("select", {
    expense_number: props.expenseNumber,
    expense_date: form.expense_date,
    category: selectedCategory.value,
    description: form.description.trim(),
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
        <label class="form-label">Nomor Pengeluaran</label>
        <input type="text" class="form-control" :value="expenseNumber" readonly />
      </div>

      <div class="col-md-6">
        <label class="form-label"> Tanggal Pengeluaran <span class="text-danger">*</span> </label>
        <input v-model="form.expense_date" type="date" class="form-control" required />
      </div>

      <div class="col-md-6">
        <label class="form-label"> Kategori <span class="text-danger">*</span> </label>
        <select v-model="form.category" class="form-select" required>
          <option v-for="category in categories" :key="category.value" :value="category.value">
            {{ category.label }}
          </option>
        </select>
      </div>

      <div v-if="form.category === 'other'" class="col-12">
        <label class="form-label"> Nama Kategori <span class="text-danger">*</span> </label>
        <input v-model="form.custom_category" type="text" class="form-control" placeholder="Masukkan kategori pengeluaran" required />
      </div>

      <div class="col-12">
        <label class="form-label"> Deskripsi <span class="text-danger">*</span> </label>
        <input v-model="form.description" type="text" class="form-control" placeholder="Contoh: Pembelian perlengkapan kantor" required />
      </div>

      <div class="col-md-6">
        <label class="form-label"> Jumlah Pengeluaran <span class="text-danger">*</span> </label>
        <input v-model="form.amount" type="number" min="1" step="0.01" class="form-control" placeholder="Masukkan jumlah" required />
      </div>

      <div class="col-md-6">
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
        <input v-model="form.reference_number" type="text" class="form-control" placeholder="Nomor bukti transaksi atau referensi" />
      </div>

      <div class="col-12">
        <label class="form-label">Catatan</label>
        <textarea v-model="form.notes" class="form-control" rows="3" placeholder="Catatan tambahan"></textarea>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <button type="button" class="btn btn-light" @click="emit('close')">Batal</button>

      <button type="submit" class="btn btn-primary" :disabled="!isValid">Simpan Pengeluaran</button>
    </div>
  </form>
</template>
