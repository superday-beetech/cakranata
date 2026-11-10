<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";

import customerService from "../services/customerService";
import type { Customer } from "../services/customerService";

interface Props {
  modelValue: number | string | null;
  disabled?: boolean;
  placeholder?: string;
  label?: string;
  required?: boolean;
  allowClear?: boolean;
  minCharacters?: number;
  limit?: number;
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
  placeholder: "Cari nama, kode, nomor anggota, atau scan barcode...",
  label: "Pelanggan / Anggota",
  required: false,
  allowClear: true,
  minCharacters: 2,
  limit: 15,
});

const emit = defineEmits<{
  (event: "update:modelValue", value: number | null): void;
  (event: "select", customer: Customer | null): void;
}>();

const keyword = ref("");
const selectedCustomer = ref<Customer | null>(null);
const results = ref<Customer[]>([]);

const loading = ref(false);
const searching = ref(false);
const dropdownOpen = ref(false);

const errorMessage = ref("");
const infoMessage = ref("");

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let requestSequence = 0;

const hasSelection = computed(() => selectedCustomer.value !== null);

const canSearch = computed(() => keyword.value.trim().length >= props.minCharacters);

function normalize(value: unknown): string {
  return String(value ?? "")
    .trim()
    .toLowerCase();
}

function isExactMatch(customer: Customer, value: string): boolean {
  const target = normalize(value);

  if (!target) return false;

  return [customer.code, customer.member_number, customer.barcode, customer.phone].some((item) => normalize(item) === target);
}

function getCustomerType(customer: Customer): string {
  return customer.member_number ? "Anggota" : "Umum";
}

function getCustomerIdentifier(customer: Customer): string {
  const identifiers = [customer.code ? `Kode: ${customer.code}` : null, customer.member_number ? `Anggota: ${customer.member_number}` : null, customer.phone ? `Telp: ${customer.phone}` : null].filter(Boolean);

  return identifiers.join(" · ");
}

function clearTimer(): void {
  if (debounceTimer) {
    clearTimeout(debounceTimer);
    debounceTimer = null;
  }
}

function resetMessages(): void {
  errorMessage.value = "";
  infoMessage.value = "";
}

function invalidateRequests(): void {
  requestSequence++;
  clearTimer();
  loading.value = false;
}

async function searchCustomers(searchTerm: string, exactLookup = false): Promise<void> {
  const term = searchTerm.trim();

  if (!term || term.length < props.minCharacters) {
    results.value = [];
    dropdownOpen.value = false;
    loading.value = false;
    return;
  }

  const currentSequence = ++requestSequence;

  loading.value = true;
  searching.value = true;
  errorMessage.value = "";
  infoMessage.value = "";

  try {
    const response = await customerService.getAll({
      page: 1,
      per_page: props.limit,
      search: term,
      is_active: 1,
    });

    if (currentSequence !== requestSequence) return;

    if (response.data.error) {
      throw new Error(Array.isArray(response.data.message) ? response.data.message.join(", ") : response.data.message || "Pencarian pelanggan gagal.");
    }

    const customers = response.data.data?.items ?? [];

    results.value = customers.filter((customer) => Number(customer.is_active) === 1);

    dropdownOpen.value = results.value.length > 0;

    if (exactLookup) {
      const exactCustomer = results.value.find((customer) => isExactMatch(customer, term));

      if (exactCustomer) {
        selectCustomer(exactCustomer);
        return;
      }

      if (results.value.length === 0) {
        infoMessage.value = "Pelanggan tidak ditemukan.";
      } else {
        infoMessage.value = "Tidak ditemukan kecocokan persis. Silakan pilih pelanggan dari hasil pencarian.";
      }
    } else if (results.value.length === 0) {
      infoMessage.value = "Pelanggan tidak ditemukan.";
    }
  } catch (error: unknown) {
    if (currentSequence !== requestSequence) return;

    results.value = [];
    dropdownOpen.value = false;

    errorMessage.value = error instanceof Error ? error.message : "Terjadi kesalahan saat mencari pelanggan.";
  } finally {
    if (currentSequence === requestSequence) {
      loading.value = false;
      searching.value = false;
    }
  }
}

function handleInput(): void {
  resetMessages();
  selectedCustomer.value = null;

  if (props.modelValue !== null) {
    emit("update:modelValue", null);
    emit("select", null);
  }

  clearTimer();

  const term = keyword.value.trim();

  if (term.length < props.minCharacters) {
    invalidateRequests();
    results.value = [];
    dropdownOpen.value = false;
    return;
  }

  debounceTimer = setTimeout(() => {
    searchCustomers(term);
  }, 300);
}

async function handleEnter(): Promise<void> {
  clearTimer();
  resetMessages();

  const term = keyword.value.trim();

  if (!term) return;

  invalidateRequests();

  // Scan barcode/kode pelanggan harus dicari secara langsung.
  await searchCustomers(term, true);
}

function selectCustomer(customer: Customer): void {
  invalidateRequests();

  selectedCustomer.value = customer;

  keyword.value = "";
  results.value = [];
  dropdownOpen.value = false;

  resetMessages();

  emit("update:modelValue", Number(customer.id));
  emit("select", customer);
}

function clearSelection(): void {
  invalidateRequests();

  selectedCustomer.value = null;
  keyword.value = "";
  results.value = [];
  dropdownOpen.value = false;

  resetMessages();

  emit("update:modelValue", null);
  emit("select", null);
}

function closeDropdown(): void {
  dropdownOpen.value = false;
}

function handleFocus(): void {
  if (results.value.length > 0) {
    dropdownOpen.value = true;
  }
}

function handleOutsideClick(event: MouseEvent): void {
  const target = event.target as HTMLElement | null;

  if (!target?.closest(".customer-autocomplete")) {
    closeDropdown();
  }
}

watch(
  () => props.modelValue,
  (value) => {
    if (value === null || value === undefined || value === "") {
      selectedCustomer.value = null;
      return;
    }

    const customerId = Number(value);

    if (!Number.isSafeInteger(customerId) || customerId <= 0) {
      selectedCustomer.value = null;
      return;
    }

    if (selectedCustomer.value?.id !== customerId) {
      void loadCustomerById(customerId);
    }
  },
  { immediate: true },
);

async function loadCustomerById(id: number): Promise<void> {
  const currentSequence = ++requestSequence;

  try {
    const response = await customerService.getById(id);

    if (currentSequence !== requestSequence) return;

    const customer = response.data.data;

    if (customer) {
      selectedCustomer.value = customer;
    }
  } catch {
    if (currentSequence !== requestSequence) return;

    selectedCustomer.value = null;
  }
}

import { onMounted } from "vue";

onMounted(() => {
  document.addEventListener("click", handleOutsideClick);
});

onBeforeUnmount(() => {
  clearTimer();
  requestSequence++;
  document.removeEventListener("click", handleOutsideClick);
});
</script>

<template>
  <div class="customer-autocomplete">
    <label class="form-label fw-semibold">
      {{ label }}
      <span v-if="required" class="text-danger">*</span>
    </label>

    <!-- Selected customer -->
    <div v-if="hasSelection && selectedCustomer" class="customer-selected border rounded p-3">
      <div class="d-flex align-items-start justify-content-between gap-2">
        <div class="d-flex align-items-start gap-2">
          <div class="customer-avatar">
            <i class="ti ti-user"></i>
          </div>

          <div>
            <div class="fw-semibold">
              {{ selectedCustomer.name }}
            </div>

            <div class="small text-muted mt-1">
              {{ getCustomerIdentifier(selectedCustomer) }}
            </div>

            <span class="badge mt-2" :class="selectedCustomer.member_number ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'">
              {{ getCustomerType(selectedCustomer) }}
            </span>
          </div>
        </div>

        <div class="d-flex gap-1">
          <button type="button" class="btn btn-sm btn-light" :disabled="disabled" title="Ganti pelanggan" @click="clearSelection">
            <i class="ti ti-refresh"></i>
          </button>

          <button v-if="allowClear" type="button" class="btn btn-sm btn-light text-danger" :disabled="disabled" title="Hapus pelanggan" @click="clearSelection">
            <i class="ti ti-x"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Search input -->
    <div v-else class="position-relative">
      <div class="input-group">
        <span class="input-group-text">
          <i class="ti ti-user-search"></i>
        </span>

        <input
          v-model="keyword"
          type="text"
          class="form-control"
          :placeholder="placeholder"
          :disabled="disabled"
          autocomplete="off"
          @input="handleInput"
          @keydown.enter.prevent="handleEnter"
          @focus="handleFocus"
          @keydown.esc="closeDropdown"
        />

        <button
          v-if="keyword"
          type="button"
          class="btn btn-outline-secondary"
          :disabled="disabled"
          @click="
            keyword = '';
            handleInput();
          "
        >
          <i class="ti ti-x"></i>
        </button>

        <span v-if="loading" class="input-group-text">
          <span class="spinner-border spinner-border-sm" role="status"></span>
        </span>
      </div>

      <!-- Search results -->
      <div v-if="dropdownOpen && results.length > 0" class="customer-results shadow">
        <button v-for="customer in results" :key="customer.id" type="button" class="customer-result-item" @click="selectCustomer(customer)">
          <div class="d-flex align-items-center gap-2">
            <div class="customer-avatar">
              <i class="ti ti-user"></i>
            </div>

            <div class="flex-grow-1 text-start">
              <div class="fw-semibold">
                {{ customer.name }}
              </div>

              <div class="small text-muted">
                {{ getCustomerIdentifier(customer) }}
              </div>
            </div>

            <span class="badge" :class="customer.member_number ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'">
              {{ getCustomerType(customer) }}
            </span>
          </div>
        </button>
      </div>
    </div>

    <div v-if="errorMessage" class="small text-danger mt-2" role="alert">
      <i class="ti ti-alert-circle me-1"></i>
      {{ errorMessage }}
    </div>

    <div v-else-if="infoMessage" class="small text-muted mt-2" role="status">
      <i class="ti ti-info-circle me-1"></i>
      {{ infoMessage }}
    </div>

    <div v-else-if="!hasSelection" class="form-text">Ketik minimal {{ minCharacters }} karakter atau scan barcode pelanggan.</div>
  </div>
</template>

<style scoped>
.customer-autocomplete {
  position: relative;
  width: 100%;
}

.customer-avatar {
  width: 38px;
  height: 38px;
  min-width: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--bs-tertiary-bg, #f1f3f5);
  color: var(--bs-secondary-color, #6c757d);
  font-size: 18px;
}

.customer-selected {
  background: var(--bs-body-bg);
}

.customer-results {
  position: absolute;
  z-index: 1055;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  max-height: 320px;
  overflow-y: auto;
  background: var(--bs-body-bg, #fff);
  border: 1px solid var(--bs-border-color, #dee2e6);
  border-radius: 8px;
}

.customer-result-item {
  display: block;
  width: 100%;
  padding: 12px;
  border: 0;
  border-bottom: 1px solid var(--bs-border-color, #dee2e6);
  background: transparent;
  color: var(--bs-body-color);
}

.customer-result-item:last-child {
  border-bottom: 0;
}

.customer-result-item:hover,
.customer-result-item:focus {
  background: var(--bs-tertiary-bg, #f8f9fa);
  outline: none;
}

.customer-result-item:focus-visible {
  box-shadow: inset 0 0 0 2px var(--bs-primary);
}
</style>
