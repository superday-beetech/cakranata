<script setup lang="ts">
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
</script>

<template>
  <section v-if="method === 'transfer'" class="pos-pay-info">
    <div class="pos-pay-info-head">
      <i class="ti ti-building-bank"></i>
      Rekening tujuan
    </div>

    <div v-if="bankName && accountNumber" class="pos-bank">
      <div>
        <small>Bank</small>
        <strong>{{ bankName }}</strong>
      </div>

      <div>
        <small>Nomor rekening</small>
        <strong>{{ accountNumber }}</strong>
      </div>

      <div>
        <small>Atas nama</small>
        <strong>{{ accountHolder || "-" }}</strong>
      </div>
    </div>

    <p v-else class="pos-notice is-warning mb-0">Informasi rekening belum dikonfigurasi. Periksa pengaturan pembayaran.</p>

    <div>
      <label class="form-label" for="pos-reference-transfer">Nomor referensi transfer</label>
      <input id="pos-reference-transfer" type="text" class="form-control form-control-sm" :value="referenceNumber" placeholder="Isi jika tersedia" @input="emit('update:referenceNumber', ($event.target as HTMLInputElement).value)" />
    </div>

    <small>Memilih transfer belum membuktikan dana diterima. Verifikasi dulu sebelum dinyatakan terkonfirmasi.</small>
  </section>

  <section v-else-if="method === 'qris'" class="pos-pay-info">
    <div class="pos-pay-info-head">
      <i class="ti ti-qrcode"></i>
      Pembayaran QRIS
    </div>

    <div v-if="qrisImageUrl" class="pos-qris">
      <img :src="qrisImageUrl" alt="Kode QRIS pembayaran" />
    </div>

    <p v-else class="pos-notice is-warning mb-0">QRIS belum dikonfigurasi. Hubungkan penyedia QRIS atau unggah kode pembayaran di pengaturan.</p>

    <small>QRIS statis tidak otomatis mengonfirmasi pembayaran. Verifikasi lewat penyedia atau petugas.</small>
  </section>

  <section v-else-if="method === 'debit' || method === 'credit'" class="pos-pay-info">
    <div class="pos-pay-info-head">
      <i class="ti ti-credit-card"></i>
      Pembayaran kartu
    </div>

    <div>
      <label class="form-label" for="pos-reference-card">Nomor referensi / approval</label>
      <input id="pos-reference-card" type="text" class="form-control form-control-sm" :value="referenceNumber" placeholder="Nomor referensi mesin EDC" @input="emit('update:referenceNumber', ($event.target as HTMLInputElement).value)" />
    </div>

    <small>Pastikan transaksi berhasil di mesin EDC sebelum mengonfirmasi pembayaran di sistem.</small>
  </section>
</template>
