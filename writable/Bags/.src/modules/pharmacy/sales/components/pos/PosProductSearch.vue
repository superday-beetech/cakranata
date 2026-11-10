<script setup lang="ts">
import type { ProductSearchResult } from "../../types/sales";

defineProps<{
  keyword: string;
  results: ProductSearchResult[];
  loading?: boolean;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  "update:keyword": [value: string];
  search: [];
  enter: [];
  select: [product: ProductSearchResult];
}>();

function handleInput(event: Event): void {
  emit("update:keyword", (event.target as HTMLInputElement).value);
}
</script>

<template>
  <section class="pos-card">
    <div class="pos-card-header">
      <div>
        <h5>Cari Produk</h5>
        <p>Scan barcode atau cari berdasarkan SKU dan nama barang.</p>
      </div>
      <kbd>F1</kbd>
    </div>

    <div class="pos-search">
      <i class="ti ti-barcode"></i>

      <input :value="keyword" type="text" autocomplete="off" placeholder="Scan barcode / SKU / nama produk..." :disabled="disabled" @input="handleInput" @keydown.enter.prevent="emit('enter')" />

      <button type="button" class="pos-button pos-button-primary" :disabled="loading || disabled" @click="emit('search')">
        <span v-if="loading" class="pos-spinner"></span>
        <i v-else class="ti ti-search"></i>
        Cari
      </button>
    </div>

    <div v-if="results.length" class="pos-search-results">
      <button v-for="product in results" :key="product.id" type="button" class="pos-product-result" :disabled="disabled" @click="emit('select', product)">
        <div class="pos-product-info">
          <strong>{{ product.name }}</strong>
          <small>
            {{ product.sku || "-" }}
            <span v-if="product.barcode"> · {{ product.barcode }}</span>
            <span v-if="product.unit_name"> · {{ product.unit_name }}</span>
          </small>
        </div>

        <div class="pos-product-price">
          <strong>
            {{
              new Intl.NumberFormat("id-ID", {
                style: "currency",
                currency: "IDR",
                maximumFractionDigits: 0,
              }).format(Number(product.selling_price))
            }}
          </strong>
          <small>
            {{ product.available_stock !== undefined ? `Stok: ${product.available_stock}` : "Stok diperiksa saat posting" }}
          </small>
        </div>
      </button>
    </div>
  </section>
</template>

<style>
.pos-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 11px;
  margin-bottom: 14px;
  padding: 16px;
}

.pos-card-header {
  align-items: center;
  display: flex;
  justify-content: space-between;
  margin-bottom: 14px;
}

.pos-card-header h5 {
  font-size: 14px;
  font-weight: 750;
  margin: 0 0 3px;
}

.pos-card-header p {
  color: #6b7280;
  font-size: 11px;
  margin: 0;
}

.pos-search {
  align-items: center;
  background: #f9fafb;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  display: flex;
  gap: 10px;
  padding: 5px 6px 5px 12px;
}

.pos-search > i {
  color: #2563eb;
  font-size: 20px;
}

.pos-search input {
  background: transparent;
  border: 0;
  flex: 1;
  font-size: 13px;
  min-width: 0;
  outline: 0;
  padding: 8px 0;
}

.pos-button {
  align-items: center;
  border: 1px solid transparent;
  border-radius: 7px;
  display: inline-flex;
  font-size: 12px;
  font-weight: 650;
  gap: 7px;
  justify-content: center;
  min-height: 34px;
  padding: 7px 11px;
}

.pos-button-primary {
  background: #2563eb;
  color: white;
}

.pos-search-results {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  margin-top: 10px;
  max-height: 240px;
  overflow-y: auto;
}

.pos-product-result {
  align-items: center;
  background: white;
  border: 0;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 11px 12px;
  text-align: left;
  width: 100%;
}

.pos-product-result:hover:not(:disabled) {
  background: #f8fafc;
}

.pos-product-info,
.pos-product-price {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}

.pos-product-info strong {
  font-size: 12px;
}

.pos-product-info small,
.pos-product-price small {
  color: #6b7280;
  font-size: 10px;
}

.pos-product-price {
  flex-shrink: 0;
  text-align: right;
}

.pos-product-price strong {
  color: #2563eb;
  font-size: 12px;
}

kbd {
  background: #f1f5f9;
  border: 1px solid #dbe1e8;
  border-radius: 4px;
  color: #475569;
  font-family: inherit;
  font-size: 9px;
  font-weight: 750;
  padding: 3px 5px;
}
</style>
