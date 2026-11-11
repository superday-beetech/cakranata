<script setup lang="ts">
import { computed, reactive } from "vue";
import type { SupplierPaymentPayload } from "../types/finance";

interface SupplierOption {
  id: number;
  name: string;
}

const props = defineProps<{
  suppliers: SupplierOption[];
  paymentNumber: string;
}>();

const emit = defineEmits<{
  select: [payload: SupplierPaymentPayload];
  close: [];
}>();

const today = () => {
  const date = new Date();
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
};

const form = reactive({
  supplier_id: "",
  purchase_order_id: "",
  payment_date: today(),
  amount: "",
  payment_method: "cash",
  custom_payment_method: "",
  reference_number: "",
  notes: "",
});

const selectedSupplier = computed(() => props.suppliers.find((supplier) => supplier.id === Number(form.supplier_id)));

const submit = () => {
  const supplierId = Number(form.supplier_id);
  const purchaseOrderId = form.purchase_order_id.trim() ? Number(form.purchase_order_id) : null;
  const amount = Number(form.amount);

  if (!Number.isInteger(supplierId) || supplierId < 1) {
    return;
  }

  if (purchaseOrderId !== null && (!Number.isInteger(purchaseOrderId) || purchaseOrderId < 1)) {
    return;
  }

  if (!form.payment_date || !Number.isFinite(amount) || amount <= 0) {
    return;
  }

  const paymentMethod = form.payment_method === "other" ? form.custom_payment_method.trim() : form.payment_method;

  if (!paymentMethod) {
    return;
  }

  emit("select", {
    payment_number: props.paymentNumber,
    supplier_id: supplierId,
    purchase_order_id: purchaseOrderId,
    payment_date: form.payment_date,
    amount,
    payment_method: paymentMethod,
    reference_number: form.reference_number.trim() || null,
    notes: form.notes.trim() || null,
  });
};
</script>

<template>
  <form @submit.prevent="submit">
    <div class="modal-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label"> Nomor Pembayaran </label>

          <input :value="paymentNumber" type="text" class="form-control" readonly />
        </div>

        <div class="col-md-6">
          <label class="form-label">
            Tanggal Pembayaran
            <span class="text-danger">*</span>
          </label>

          <input v-model="form.payment_date" type="date" class="form-control" required />
        </div>

        <div class="col-md-6">
          <label class="form-label">
            Supplier
            <span class="text-danger">*</span>
          </label>

          <select v-model="form.supplier_id" class="form-select" required>
            <option value="" disabled>Pilih supplier</option>

            <option v-for="supplier in suppliers" :key="supplier.id" :value="String(supplier.id)">
              {{ supplier.name }}
            </option>
          </select>

          <div v-if="selectedSupplier" class="form-text">Supplier dipilih: {{ selectedSupplier.name }}</div>
        </div>

        <div class="col-md-6">
          <label class="form-label"> Purchase Order ID </label>

          <input v-model="form.purchase_order_id" type="number" min="1" class="form-control" placeholder="Kosongkan untuk pembayaran umum" />

          <div class="form-text">Isi jika pembayaran dialokasikan ke PO tertentu.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">
            Nominal Pembayaran
            <span class="text-danger">*</span>
          </label>

          <input v-model="form.amount" type="number" min="0.01" step="0.01" class="form-control" placeholder="0" required />
        </div>

        <div class="col-md-6">
          <label class="form-label">
            Metode Pembayaran
            <span class="text-danger">*</span>
          </label>

          <select v-model="form.payment_method" class="form-select" required>
            <option value="cash">Tunai</option>
            <option value="transfer">Transfer Bank</option>
            <option value="debit">Debit</option>
            <option value="credit">Kartu Kredit</option>
            <option value="other">Lainnya</option>
          </select>
        </div>

        <div v-if="form.payment_method === 'other'" class="col-12">
          <label class="form-label">
            Metode Pembayaran Lainnya
            <span class="text-danger">*</span>
          </label>

          <input v-model="form.custom_payment_method" type="text" class="form-control" placeholder="Masukkan metode pembayaran" required />
        </div>

        <div class="col-md-6">
          <label class="form-label"> Nomor Referensi </label>

          <input v-model="form.reference_number" type="text" class="form-control" placeholder="Nomor bukti transfer / referensi" />
        </div>

        <div class="col-md-6">
          <label class="form-label"> Catatan </label>

          <input v-model="form.notes" type="text" class="form-control" placeholder="Catatan pembayaran" />
        </div>
      </div>

      <div class="alert alert-info mt-3 mb-0">Pembayaran otomatis dicatat sebagai kas keluar. Jika dikaitkan dengan Purchase Order, nominal akan divalidasi terhadap sisa tagihan.</div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-light" @click="emit('close')">Batal</button>

      <button type="submit" class="btn btn-primary">
        <i class="ti ti-device-floppy me-1"></i>
        Simpan Pembayaran
      </button>
    </div>
  </form>
</template>
