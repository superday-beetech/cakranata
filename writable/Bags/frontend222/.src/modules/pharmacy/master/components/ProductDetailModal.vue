<script setup lang="ts">
import type { Product } from "../services/productService";

defineProps<{
  product: Product;
}>();

const emit = defineEmits<{
  select: [value: Product];
  close: [];
}>();

function formatCurrency(value: number | string): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 2,
  }).format(Number(value || 0));
}
</script>

<template>
  <div v-if="product">
    <div class="mb-3">
      <label class="form-label text-muted">Nama Produk</label>
      <div class="fw-semibold">{{ product.name }}</div>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label text-muted">SKU</label>
        <div>{{ product.sku || "-" }}</div>
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label text-muted">Barcode</label>
        <div>{{ product.barcode || "-" }}</div>
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label text-muted">Kategori</label>
        <div>{{ product.category_name || product.category_id }}</div>
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label text-muted">Satuan</label>
        <div>{{ product.unit_name || product.unit_id }}</div>
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label text-muted">Harga Beli</label>
        <div>{{ formatCurrency(product.purchase_price) }}</div>
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label text-muted">Harga Jual</label>
        <div>{{ formatCurrency(product.selling_price) }}</div>
      </div>

      <div class="col-12 mb-3">
        <label class="form-label text-muted">Deskripsi</label>
        <div>{{ product.description || "-" }}</div>
      </div>

      <div class="col-12 mb-3">
        <label class="form-label text-muted">Status</label>
        <div>
          <span class="badge" :class="Number(product.is_active) === 1 ? 'bg-success' : 'bg-secondary'">
            {{ Number(product.is_active) === 1 ? "Aktif" : "Tidak Aktif" }}
          </span>
        </div>
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label text-muted">Dibuat</label>
        <div>{{ product.created_at || "-" }}</div>
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label text-muted">Diperbarui</label>
        <div>{{ product.updated_at || "-" }}</div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
      <button type="button" class="btn btn-light" @click="emit('close')">Tutup</button>

      <button type="button" class="btn btn-primary" @click="emit('select', product)">
        <i class="ti ti-edit me-1"></i>
        Ubah
      </button>
    </div>
  </div>
</template>
