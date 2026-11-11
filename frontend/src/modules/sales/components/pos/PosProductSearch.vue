<script setup lang="ts">
import { nextTick, ref, watch } from "vue";
import { formatCurrency } from "../../utils/format";
import type { ProductSearchResult } from "../../types/sales";

const props = defineProps<{
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

const listRef = ref<HTMLElement | null>(null);
const activeIndex = ref(-1);

watch(
  () => props.results,
  () => {
    activeIndex.value = -1;
  },
);

watch(activeIndex, async () => {
  await nextTick();
  listRef.value?.querySelector(".is-active")?.scrollIntoView({ block: "nearest" });
});

function handleInput(event: Event): void {
  emit("update:keyword", (event.target as HTMLInputElement).value);
}

function move(step: 1 | -1): void {
  const total = props.results.length;
  if (!total) return;

  activeIndex.value = activeIndex.value < 0 ? (step === 1 ? 0 : total - 1) : (activeIndex.value + step + total) % total;
}

// Enter memilih hasil hanya jika kasir sudah menekan panah; selain itu alur barcode seperti biasa.
function handleEnter(): void {
  const picked = props.results[activeIndex.value];

  if (picked) emit("select", picked);
  else emit("enter");
}

// Esc menutup daftar hasil.
function handleEscape(): void {
  if (props.results.length) emit("update:keyword", "");
}
</script>

<template>
  <section class="card pos-search-card">
    <label class="pos-field-label" for="pos-search-input">
      <i class="ti ti-barcode"></i>
      Cari produk
      <kbd>F1</kbd>
    </label>

    <div class="pos-search-box">
      <div class="input-group">
        <span class="input-group-text"><i class="ti ti-scan"></i></span>

        <input
          id="pos-search-input"
          :value="keyword"
          type="text"
          class="form-control"
          autocomplete="off"
          placeholder="Scan barcode, ketik SKU atau nama produk"
          :disabled="disabled"
          @input="handleInput"
          @keydown.down.prevent="move(1)"
          @keydown.up.prevent="move(-1)"
          @keydown.enter.prevent="handleEnter"
          @keydown.esc="handleEscape"
        />

        <button type="button" class="btn btn-primary" :disabled="loading || disabled" @click="emit('search')">
          <span v-if="loading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
          <i v-else class="ti ti-search"></i>
          Cari
        </button>
      </div>

      <div v-if="results.length" ref="listRef" class="pos-search-results" role="listbox">
        <button
          v-for="(product, index) in results"
          :key="product.id"
          type="button"
          class="pos-result"
          :class="{ 'is-active': index === activeIndex }"
          role="option"
          :aria-selected="index === activeIndex"
          :disabled="disabled"
          @click="emit('select', product)"
        >
          <span class="pos-result-info">
            <strong>{{ product.name }}</strong>
            <small>
              {{ product.sku || "-" }}
              <template v-if="product.barcode"> · {{ product.barcode }}</template>
              <template v-if="product.unit_name"> · {{ product.unit_name }}</template>
            </small>
          </span>

          <span class="pos-result-price">
            <strong>{{ formatCurrency(product.selling_price) }}</strong>
            <small>{{ product.available_stock !== undefined ? `Stok: ${product.available_stock}` : "Stok diperiksa saat posting" }}</small>
          </span>
        </button>
      </div>
    </div>

    <small class="pos-field-hint">Ketik nama atau SKU, tekan ↓ lalu Enter. Esc untuk menutup.</small>
  </section>
</template>
