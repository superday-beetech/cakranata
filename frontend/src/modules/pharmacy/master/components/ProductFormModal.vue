<script setup lang="ts">
import { onMounted, ref, watch } from "vue";
import { notify } from "@/utils/notification";

import productService, { type Product, type ProductPayload } from "../services/productService";

import categoryService from "../services/categoryService";
import unitService from "../services/unitService";

interface SelectOption {
  id: number;
  name: string;
  abbreviation?: string;
}

const props = defineProps<{
  product?: Product | null;
}>();

const emit = defineEmits<{
  select: [value: Product];
  close: [];
}>();

const saving = ref(false);
const loadingOptions = ref(false);

const categories = ref<SelectOption[]>([]);
const units = ref<SelectOption[]>([]);
const optionsLoaded = ref(false);

const form = ref<ProductPayload>({
  sku: "",
  barcode: null,
  name: "",
  category_id: 0,
  unit_id: 0,
  purchase_price: 0,
  selling_price: 0,
  description: null,
  is_active: 1,
});

function resetForm(product?: Product | null): void {
  form.value = {
    sku: product?.sku ?? "",
    barcode: product?.barcode ?? null,
    name: product?.name ?? "",
    category_id: product?.category_id ?? 0,
    unit_id: product?.unit_id ?? 0,
    purchase_price: Number(product?.purchase_price ?? 0),
    selling_price: Number(product?.selling_price ?? 0),
    description: product?.description ?? null,
    is_active: Number(product?.is_active ?? 1),
  };
}

watch(
  () => props.product,
  (product) => resetForm(product),
  { immediate: true },
);

function getErrorMessage(error: any): string {
  const message = error?.response?.data?.message;

  if (Array.isArray(message)) return message.join(", ");
  if (typeof message === "string") return message;

  return error?.message || "Terjadi kesalahan.";
}

async function loadOptions(): Promise<void> {
  loadingOptions.value = true;

  try {
    const [categoryResponse, unitResponse] = await Promise.all([categoryService.getAll({ page: 1, per_page: 100 }), unitService.getAll({ page: 1, per_page: 100 })]);

    // Category service mengembalikan CategoryListData langsung.
    const categoryData = categoryResponse.data;

    if (!categoryData) {
      throw new Error("Gagal mengambil data kategori.");
    }

    categories.value = categoryData.items
      .filter((item) => Number(item.is_active) === 1)
      .map((item) => ({
        id: item.id,
        name: item.name,
      }));

    if (props.product?.category_id && !categories.value.some((item) => item.id === props.product!.category_id)) {
      categories.value.push({
        id: props.product.category_id,
        name: `${props.product.category_name ?? "Kategori"} (Nonaktif)`,
      });
    }

    optionsLoaded.value = true;

    // Unit service mengikuti response wrapper ApiResponse.
    const unitResult = unitResponse.data;

    if (unitResult.error || !unitResult.data) {
      throw new Error("Gagal mengambil data satuan.");
    }

    units.value = unitResult.data.items.map((item) => ({
      id: item.id,
      name: item.name,
      abbreviation: item.abbreviation,
    }));

    if (form.value.category_id && !categories.value.some((item) => item.id === form.value.category_id)) {
      notify.warning("Kategori produk ini tidak aktif atau tidak tersedia.");
    }
  } catch (error: any) {
    optionsLoaded.value = false;
    notify.error(getErrorMessage(error));
  } finally {
    loadingOptions.value = false;
  }
}

function validateForm(): boolean {
  if (!form.value.sku.trim()) {
    notify.warning("SKU produk wajib diisi.");
    return false;
  }

  if (form.value.sku.trim().length > 50) {
    notify.warning("SKU maksimal 50 karakter.");
    return false;
  }

  if (form.value.barcode && form.value.barcode.trim().length > 100) {
    notify.warning("Barcode maksimal 100 karakter.");
    return false;
  }

  if (!form.value.name.trim()) {
    notify.warning("Nama produk wajib diisi.");
    return false;
  }

  if (form.value.name.trim().length < 2 || form.value.name.trim().length > 180) {
    notify.warning("Nama produk harus terdiri dari 2 sampai 180 karakter.");
    return false;
  }

  if (!form.value.category_id) {
    notify.warning("Silakan pilih kategori produk.");
    return false;
  }

  if (!form.value.unit_id) {
    notify.warning("Silakan pilih satuan produk.");
    return false;
  }

  if (!Number.isFinite(form.value.purchase_price) || form.value.purchase_price < 0) {
    notify.warning("Harga beli tidak valid.");
    return false;
  }

  if (!Number.isFinite(form.value.selling_price) || form.value.selling_price < 0) {
    notify.warning("Harga jual tidak valid.");
    return false;
  }

  return true;
}

async function save(): Promise<void> {
  if (!optionsLoaded.value) {
    notify.warning("Data kategori dan satuan belum berhasil dimuat.");
    return;
  }
  if (!validateForm()) return;

  saving.value = true;

  const payload: ProductPayload = {
    sku: form.value.sku.trim(),
    barcode: form.value.barcode?.trim() || null,
    name: form.value.name.trim(),
    category_id: Number(form.value.category_id),
    unit_id: Number(form.value.unit_id),
    purchase_price: Number(form.value.purchase_price),
    selling_price: Number(form.value.selling_price),
    description: form.value.description?.trim() || null,
    is_active: Number(form.value.is_active),
  };

  try {
    const response = props.product ? await productService.update(props.product.id, payload) : await productService.create(payload);

    const result = response.data;

    if (result.error || !result.data) {
      throw new Error(typeof result.message === "string" ? result.message : "Gagal menyimpan produk.");
    }

    notify.success(props.product ? "Produk berhasil diperbarui." : "Produk berhasil ditambahkan.");

    emit("select", result.data);
    emit("close");
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  } finally {
    saving.value = false;
  }
}

onMounted(loadOptions);
</script>

<template>
  <form @submit.prevent="save">
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="product-sku" class="form-label"> SKU <span class="text-danger">*</span> </label>

        <input id="product-sku" v-model="form.sku" type="text" class="form-control" maxlength="50" placeholder="Kode produk" :disabled="saving" required />
      </div>

      <div class="col-md-6 mb-3">
        <label for="product-barcode" class="form-label"> Barcode </label>
        <input id="product-name" v-model="form.name" type="text" class="form-control" maxlength="180" placeholder="Nama produk" :disabled="saving" required />
      </div>

      <div class="col-12 mb-3">
        <label for="product-name" class="form-label"> Nama Produk <span class="text-danger">*</span> </label>
        <input id="product-name" v-model="form.name" type="text" class="form-control" maxlength="150" placeholder="Nama produk" :disabled="saving" required />
      </div>

      <div class="col-md-6 mb-3">
        <label for="product-category" class="form-label"> Kategori <span class="text-danger">*</span> </label>
        <select id="product-category" v-model.number="form.category_id" class="form-select" :disabled="saving || loadingOptions" required>
          <option :value="0" disabled>Pilih kategori</option>
          <option v-for="category in categories" :key="category.id" :value="category.id">
            {{ category.name }}
          </option>
        </select>
      </div>

      <div class="col-md-6 mb-3">
        <label for="product-unit" class="form-label"> Satuan <span class="text-danger">*</span> </label>
        <select id="product-unit" v-model.number="form.unit_id" class="form-select" :disabled="saving || loadingOptions" required>
          <option :value="0" disabled>Pilih satuan</option>
          <option v-for="unit in units" :key="unit.id" :value="unit.id">
            {{ unit.name }}
            <template v-if="unit.abbreviation"> ({{ unit.abbreviation }}) </template>
          </option>
        </select>
      </div>

      <div class="col-md-6 mb-3">
        <label for="product-purchase-price" class="form-label"> Harga Beli <span class="text-danger">*</span> </label>
        <input id="product-purchase-price" v-model.number="form.purchase_price" type="number" min="0" step="0.01" class="form-control" :disabled="saving" required />
      </div>

      <div class="col-md-6 mb-3">
        <label for="product-selling-price" class="form-label"> Harga Jual <span class="text-danger">*</span> </label>
        <input id="product-selling-price" v-model.number="form.selling_price" type="number" min="0" step="0.01" class="form-control" :disabled="saving" required />
      </div>

      <div class="col-12 mb-3">
        <label for="product-description" class="form-label"> Deskripsi </label>
        <textarea id="product-description" v-model="form.description" class="form-control" rows="3" placeholder="Keterangan produk" :disabled="saving"></textarea>
      </div>

      <div class="col-12 mb-3">
        <label for="product-status" class="form-label"> Status </label>
        <select id="product-status" v-model.number="form.is_active" class="form-select" :disabled="saving">
          <option :value="1">Aktif</option>
          <option :value="0">Tidak Aktif</option>
        </select>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
      <button type="button" class="btn btn-light" :disabled="saving || loadingOptions || !optionsLoaded" @click="emit('close')">Batal</button>

      <button type="submit" class="btn btn-primary" :disabled="saving || loadingOptions">
        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
        {{ saving ? "Menyimpan..." : "Simpan" }}
      </button>
    </div>
  </form>
</template>
