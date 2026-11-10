<script setup lang="ts">
import { ref } from "vue";
import type { PaymentMethod } from "../../types/sales";

defineProps<{
  method: PaymentMethod;
  bankName?: string;
  accountNumber?: string;
  accountHolder?: string;
  qrisImageUrl?: string | null;
  referenceNumber?: string;
}>();

const emit = defineEmits<{
  "update:referenceNumber": [value: string];
}>();

// Kode QRIS tidak ditampilkan permanen agar panel pembayaran tetap ringkas.
const qrisOpen = ref(false);
</script>

<template>
  <section v-if="method === 'transfer'" class="pos-pay-info">
    <div v-if="bankName && accountNumber" class="pos-bank-line">
      <i class="ti ti-building-bank"></i>
      <div>
        <strong>{{ bankName }} · {{ accountNumber }}</strong>
        <small>a.n. {{ accountHolder || "-" }}</small>
      </div>
    </div>

    <p v-else class="pos-notice is-warning mb-0">Rekening tujuan belum dikonfigurasi di pengaturan pembayaran.</p>

    <input
      type="text"
      class="form-control form-control-sm"
      aria-label="Nomor referensi transfer"
      :value="referenceNumber"
      placeholder="No. referensi transfer (jika ada)"
      @input="emit('update:referenceNumber', ($event.target as HTMLInputElement).value)"
    />
  </section>

  <section v-else-if="method === 'qris'" class="pos-pay-info">
    <template v-if="qrisImageUrl">
      <button type="button" class="btn btn-soft-primary btn-sm" @click="qrisOpen = true">
        <i class="ti ti-qrcode"></i>
        Tampilkan kode QRIS
      </button>

      <div v-if="qrisOpen" class="pos-qr-overlay" role="dialog" aria-modal="true" aria-label="Kode QRIS pembayaran" @click.self="qrisOpen = false">
        <div class="pos-qr-card">
          <img :src="qrisImageUrl" alt="Kode QRIS pembayaran" />
          <button type="button" class="btn btn-primary btn-sm" @click="qrisOpen = false">Tutup</button>
        </div>
      </div>
    </template>

    <p v-else class="pos-notice is-warning mb-0">QRIS belum dikonfigurasi di pengaturan pembayaran.</p>
  </section>

  <section v-else-if="method === 'debit' || method === 'credit'" class="pos-pay-info">
    <input
      type="text"
      class="form-control form-control-sm"
      aria-label="Nomor referensi atau approval mesin EDC"
      :value="referenceNumber"
      placeholder="No. referensi / approval EDC"
      @input="emit('update:referenceNumber', ($event.target as HTMLInputElement).value)"
    />
  </section>
</template>
