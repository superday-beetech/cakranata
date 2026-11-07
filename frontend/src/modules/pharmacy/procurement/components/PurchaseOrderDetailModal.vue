<script setup lang="ts">
import type { PurchaseOrder } from "../types/purchaseOrder";

defineProps<{
  order: PurchaseOrder;
}>();

const emit = defineEmits<{
  close: [];
}>();

function currency(value: number | string | null | undefined) {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(Number(value ?? 0));
}

function date(value?: string | null) {
  if (!value) return "-";

  return new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "long",
    year: "numeric",
  });
}
</script>

<template>
  <div>
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <small class="text-muted">Nomor PO</small>
        <div class="fw-semibold">{{ order.order_number }}</div>
      </div>

      <div class="col-md-6">
        <small class="text-muted">Status</small>
        <div>
          <span class="badge bg-light-primary text-primary">
            {{ order.status }}
          </span>
        </div>
      </div>

      <div class="col-md-6">
        <small class="text-muted">Supplier</small>
        <div class="fw-semibold">
          {{ order.supplier_name || "-" }}
        </div>
        <small class="text-muted">
          {{ order.supplier_phone || "" }}
        </small>
      </div>

      <div class="col-md-6">
        <small class="text-muted">Tanggal Order</small>
        <div>{{ date(order.order_date) }}</div>

        <small class="text-muted">Estimasi Kedatangan</small>
        <div>{{ date(order.expected_date) }}</div>
      </div>

      <div class="col-12">
        <small class="text-muted">Alamat Supplier</small>
        <div>{{ order.supplier_address || "-" }}</div>
      </div>
    </div>

    <h6 class="fw-semibold mb-3">Daftar Produk</h6>

    <div class="table-responsive">
      <table class="table table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th>Produk</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Harga</th>
            <th class="text-end">Diskon</th>
            <th class="text-end">Subtotal</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="item in order.items || []" :key="item.id">
            <td>
              <div class="fw-semibold">
                {{ item.product_name || `Produk #${item.product_id}` }}
              </div>
              <small class="text-muted">
                {{ item.sku || "" }}
              </small>
            </td>

            <td class="text-end">
              {{ Number(item.quantity).toLocaleString("id-ID") }}
            </td>

            <td class="text-end">
              {{ currency(item.unit_cost) }}
            </td>

            <td class="text-end">
              {{ currency(item.discount_amount) }}
            </td>

            <td class="text-end">
              {{ currency(item.subtotal) }}
            </td>
          </tr>

          <tr v-if="!order.items?.length">
            <td colspan="5" class="text-center text-muted py-4">Tidak ada item Purchase Order.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="row justify-content-end">
      <div class="col-md-5">
        <div class="d-flex justify-content-between mb-2">
          <span>Subtotal</span>
          <strong>{{ currency(order.subtotal) }}</strong>
        </div>

        <div class="d-flex justify-content-between mb-2">
          <span>Diskon</span>
          <strong>{{ currency(order.discount_amount) }}</strong>
        </div>

        <div class="d-flex justify-content-between mb-2">
          <span>Pajak</span>
          <strong>{{ currency(order.tax_amount) }}</strong>
        </div>

        <div class="d-flex justify-content-between border-top pt-3">
          <strong>Total</strong>
          <strong class="text-primary">
            {{ currency(order.total_amount) }}
          </strong>
        </div>
      </div>
    </div>

    <div v-if="order.notes" class="mt-3">
      <small class="text-muted">Catatan</small>
      <p class="mb-0">{{ order.notes }}</p>
    </div>

    <div class="text-end mt-4">
      <button type="button" class="btn btn-light" @click="emit('close')">Tutup</button>
    </div>
  </div>
</template>
