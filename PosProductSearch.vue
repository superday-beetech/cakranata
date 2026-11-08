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

function currency(value: number): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(Number(value) || 0);
}
</script>

<template>
  <section class="card pos-search-card">
    <label class="pos-field-label" for="pos-search-input">
      <i class="ti ti-barcode"></i>
      Cari produk
      <kbd>F1</kbd>
    </label>

    <div class="input-group">
      <span class="input-group-text"><i class="ti ti-scan"></i></span>

      <input id="pos-search-input" :value="keyword" type="text" class="form-control" autocomplete="off" placeholder="Scan barcode, ketik SKU atau nama produk" :disabled="disabled" @input="handleInput" @keydown.enter.prevent="emit('enter')" />

      <button type="button" class="btn btn-primary" :disabled="loading || disabled" @click="emit('search')">
        <span v-if="loading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        <i v-else class="ti ti-search"></i>
        Cari
      </button>
    </div>

    <div v-if="results.length" class="pos-search-results">
      <button v-for="product in results" :key="product.id" type="button" class="pos-result" :disabled="disabled" @click="emit('select', product)">
        <span class="pos-result-info">
          <strong>{{ product.name }}</strong>
          <small>
            {{ product.sku || "-" }}
            <template v-if="product.barcode"> · {{ product.barcode }}</template>
            <template v-if="product.unit_name"> · {{ product.unit_name }}</template>
          </small>
        </span>

        <span class="pos-result-price">
          <strong>{{ currency(product.selling_price) }}</strong>
          <small>{{ product.available_stock !== undefined ? `Stok: ${product.available_stock}` : "Stok diperiksa saat posting" }}</small>
        </span>
      </button>
    </div>
  </section>
</template>
