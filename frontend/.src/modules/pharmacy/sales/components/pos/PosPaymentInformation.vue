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
  <section v-if="method === 'transfer'" class="pos-payment-information">
    <div class="pos-information-heading">
      <i class="ti ti-building-bank"></i>
      <strong>Informasi Rekening Tujuan</strong>
    </div>

    <div v-if="bankName && accountNumber" class="pos-bank-details">
      <div>
        <small>Bank</small>
        <strong>{{ bankName }}</strong>
      </div>

      <div>
        <small>Nomor Rekening</small>
        <strong>{{ accountNumber }}</strong>
      </div>

      <div>
        <small>Atas Nama</small>
        <strong>{{ accountHolder || "-" }}</strong>
      </div>
    </div>

    <p v-else class="pos-information-warning">Informasi rekening belum dikonfigurasi. Silakan periksa pengaturan pembayaran.</p>

    <label>Nomor Referensi Transfer</label>
    <input type="text" :value="referenceNumber" placeholder="Masukkan nomor referensi jika tersedia" @input="emit('update:referenceNumber', ($event.target as HTMLInputElement).value)" />

    <small class="pos-information-note"> Pemilihan metode transfer belum membuktikan dana telah diterima. Pembayaran perlu diverifikasi sebelum dinyatakan terkonfirmasi. </small>
  </section>

  <section v-else-if="method === 'qris'" class="pos-payment-information">
    <div class="pos-information-heading">
      <i class="ti ti-qrcode"></i>
      <strong>Pembayaran QRIS</strong>
    </div>

    <div v-if="qrisImageUrl" class="pos-qris-image">
      <img :src="qrisImageUrl" alt="QRIS pembayaran" />
    </div>

    <p v-else class="pos-information-warning">QRIS belum dikonfigurasi. Hubungkan penyedia QRIS atau unggah kode pembayaran melalui pengaturan.</p>

    <small class="pos-information-note"> Gambar QRIS statis tidak otomatis mengonfirmasi pembayaran. Status pembayaran harus diverifikasi melalui penyedia atau petugas. </small>
  </section>

  <section v-else-if="method === 'debit' || method === 'credit'" class="pos-payment-information">
    <div class="pos-information-heading">
      <i class="ti ti-credit-card"></i>
      <strong>Informasi Pembayaran Kartu</strong>
    </div>

    <label>Nomor Referensi / Approval</label>
    <input type="text" :value="referenceNumber" placeholder="Nomor referensi mesin EDC" @input="emit('update:referenceNumber', ($event.target as HTMLInputElement).value)" />

    <small class="pos-information-note"> Pastikan transaksi kartu berhasil pada mesin EDC sebelum mengonfirmasi pembayaran di sistem. </small>
  </section>
</template>

<style scoped>
.pos-payment-information {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  gap: 9px;
  padding: 12px;
}

.pos-information-heading {
  align-items: center;
  color: #334155;
  display: flex;
  font-size: 11px;
  gap: 7px;
}

.pos-information-heading i {
  color: #2563eb;
  font-size: 17px;
}

.pos-bank-details {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.pos-bank-details div {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}

.pos-bank-details small,
.pos-payment-information label {
  color: #64748b;
  font-size: 10px;
}

.pos-bank-details strong {
  color: #1e293b;
  font-size: 11px;
  overflow-wrap: anywhere;
}

.pos-payment-information input {
  border: 1px solid #dbe1e8;
  border-radius: 6px;
  font-size: 11px;
  min-height: 34px;
  padding: 7px 9px;
  width: 100%;
}

.pos-information-note,
.pos-information-warning {
  font-size: 10px;
  line-height: 1.5;
  margin: 0;
}

.pos-information-note {
  color: #64748b;
}

.pos-information-warning {
  background: #fff7ed;
  border-radius: 6px;
  color: #9a3412;
  padding: 9px;
}

.pos-qris-image {
  align-items: center;
  display: flex;
  justify-content: center;
  padding: 8px;
}

.pos-qris-image img {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 7px;
  max-height: 220px;
  max-width: 220px;
  object-fit: contain;
  padding: 8px;
  width: 100%;
}

@media (max-width: 480px) {
  .pos-bank-details {
    grid-template-columns: 1fr;
  }
}
</style>
