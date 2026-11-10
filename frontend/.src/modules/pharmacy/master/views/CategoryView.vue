<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import categoryService, { type Category, type CategoryPayload, type CategoryPagination } from "@/modules/pharmacy/master/services/categoryService";
import { notify } from "@/utils/notification";

const categories = ref<Category[]>([]);
const loading = ref(false);
const saving = ref(false);
const errorMessage = ref("");
const successMessage = ref("");

const searchInput = ref("");
const search = ref("");
const statusFilter = ref("all");
const perPage = ref(10);

const pagination = ref<CategoryPagination>({
  current_page: 1,
  per_page: 10,
  total: 0,
  total_pages: 0,
});

const showForm = ref(false);
const showDetail = ref(false);
const isEditing = ref(false);

const selectedCategory = ref<Category | null>(null);

const form = reactive<CategoryPayload>({
  name: "",
  description: "",
  is_active: 1,
});

const formErrors = reactive<Record<string, string>>({});

const pageStart = computed(() => {
  if (pagination.value.total === 0) return 0;

  return (pagination.value.current_page - 1) * pagination.value.per_page + 1;
});

const pageEnd = computed(() => {
  return Math.min(pagination.value.current_page * pagination.value.per_page, pagination.value.total);
});

const hasPrevious = computed(() => pagination.value.current_page > 1);

const hasNext = computed(() => pagination.value.current_page < pagination.value.total_pages);

function getErrorMessage(error: any): string {
  const message = error?.response?.data?.message;

  if (Array.isArray(message)) return message.join(", ");
  if (typeof message === "string") return message;

  return error?.message || "Terjadi kesalahan pada server.";
}

async function loadCategories(page = 1) {
  loading.value = true;
  errorMessage.value = "";

  try {
    const response = await categoryService.getAll({
      page,
      per_page: perPage.value,
      search: search.value,
      is_active: statusFilter.value === "all" ? undefined : statusFilter.value,
    });

    if (response.error || !response.data) {
      throw new Error(typeof response.message === "string" ? response.message : "Gagal mengambil data kategori.");
    }

    categories.value = response.data.items;
    pagination.value = response.data.pagination;
  } catch (error) {
    errorMessage.value = getErrorMessage(error);
    categories.value = [];
  } finally {
    loading.value = false;
  }
}

function applySearch() {
  search.value = searchInput.value.trim();
  loadCategories(1);
}

function changePerPage() {
  loadCategories(1);
}

function changeStatus() {
  loadCategories(1);
}

function previousPage() {
  if (hasPrevious.value) {
    loadCategories(pagination.value.current_page - 1);
  }
}

function nextPage() {
  if (hasNext.value) {
    loadCategories(pagination.value.current_page + 1);
  }
}

function clearMessages() {
  errorMessage.value = "";
  successMessage.value = "";
}

function clearForm() {
  form.name = "";
  form.description = "";
  form.is_active = 1;

  Object.keys(formErrors).forEach((key) => {
    delete formErrors[key];
  });
}

function openCreate() {
  clearMessages();
  clearForm();

  selectedCategory.value = null;
  isEditing.value = false;
  showForm.value = true;
}

async function openEdit(id: number) {
  clearMessages();

  try {
    const response = await categoryService.getById(id);

    if (response.error || !response.data) {
      throw new Error("Gagal mengambil detail kategori.");
    }

    selectedCategory.value = response.data;

    form.name = response.data.name;
    form.description = response.data.description ?? "";
    form.is_active = Number(response.data.is_active);

    isEditing.value = true;
    showForm.value = true;
  } catch (error) {
    errorMessage.value = getErrorMessage(error);
  }
}

async function openDetail(id: number) {
  clearMessages();

  try {
    const response = await categoryService.getById(id);

    if (response.error || !response.data) {
      throw new Error("Gagal mengambil detail kategori.");
    }

    selectedCategory.value = response.data;
    showDetail.value = true;
  } catch (error) {
    errorMessage.value = getErrorMessage(error);
  }
}

function closeForm() {
  showForm.value = false;
  selectedCategory.value = null;
}

function validateForm(): boolean {
  Object.keys(formErrors).forEach((key) => {
    delete formErrors[key];
  });

  const name = form.name.trim();

  if (!name) {
    formErrors.name = "Nama kategori wajib diisi.";
  } else if (name.length < 2 || name.length > 100) {
    formErrors.name = "Nama kategori harus 2–100 karakter.";
  }

  if ((form.description?.length ?? 0) > 1000) {
    formErrors.description = "Deskripsi maksimal 1000 karakter.";
  }

  return Object.keys(formErrors).length === 0;
}

async function saveCategory() {
  clearMessages();

  if (!validateForm()) return;

  saving.value = true;

  try {
    const payload: CategoryPayload = {
      name: form.name.trim(),
      description: form.description?.trim() || null,
      is_active: Number(form.is_active),
    };

    const response = isEditing.value && selectedCategory.value ? await categoryService.update(selectedCategory.value.id, payload) : await categoryService.create(payload);

    if (response.error) {
      throw new Error(typeof response.message === "string" ? response.message : "Gagal menyimpan kategori.");
    }

    showForm.value = false;
    notify.success(isEditing.value ? "Kategori berhasil diperbarui." : "Kategori berhasil ditambahkan.");
    // successMessage.value = isEditing.value ? "Kategori berhasil diperbarui." : "Kategori berhasil ditambahkan.";

    await loadCategories(isEditing.value ? pagination.value.current_page : 1);
  } catch (error) {
    errorMessage.value = getErrorMessage(error);
  } finally {
    saving.value = false;
  }
}

async function deleteCategory(category: Category) {
  const confirmed = window.confirm(`Hapus kategori "${category.name}"?`);

  if (!confirmed) return;

  clearMessages();

  try {
    const response = await categoryService.delete(category.id);

    if (response.error) {
      throw new Error(typeof response.message === "string" ? response.message : "Gagal menghapus kategori.");
    }

    // successMessage.value = "Kategori berhasil dihapus.";
    notify.success("Kategori berhasil dihapus.");

    const currentPage = pagination.value.current_page;
    const nextPageNumber = categories.value.length === 1 && currentPage > 1 ? currentPage - 1 : currentPage;

    await loadCategories(nextPageNumber);
  } catch (error) {
    errorMessage.value = getErrorMessage(error);
  }
}

function closeDetail() {
  showDetail.value = false;
  selectedCategory.value = null;
}

onMounted(() => {
  loadCategories();
});
</script>

<template>
  <div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Kategori Produk</h4>
        <p class="text-muted mb-0">Pengelolaan kategori produk apotek.</p>
      </div>

      <button type="button" class="btn btn-primary" @click="openCreate">
        <i class="ti ti-plus me-1"></i>
        Tambah Kategori
      </button>
    </div>

    <!-- Notifications -->
    <div v-if="successMessage" class="alert alert-success alert-dismissible" role="alert">
      {{ successMessage }}
      <button type="button" class="btn-close" @click="successMessage = ''"></button>
    </div>

    <div v-if="errorMessage" class="alert alert-danger alert-dismissible" role="alert">
      {{ errorMessage }}
      <button type="button" class="btn-close" @click="errorMessage = ''"></button>
    </div>

    <!-- Main Table -->
    <div class="card">
      <div class="card-body">
        <!-- Filters -->
        <div class="row g-3 mb-4">
          <div class="col-md-2">
            <label class="form-label"> Show entries </label>

            <select v-model.number="perPage" class="form-select" @change="changePerPage">
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label"> Status </label>

            <select v-model="statusFilter" class="form-select" @change="changeStatus">
              <option value="all">Semua Status</option>
              <option value="1">Aktif</option>
              <option value="0">Nonaktif</option>
            </select>
          </div>

          <div class="col-md-7">
            <label class="form-label"> Pencarian </label>

            <div class="input-group">
              <input v-model="searchInput" type="search" class="form-control" placeholder="Cari nama atau deskripsi kategori..." @keyup.enter="applySearch" />

              <button type="button" class="btn btn-outline-primary" @click="applySearch">
                <i class="ti ti-search me-1"></i>
                Cari
              </button>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th width="70">No.</th>
                <th>Nama Kategori</th>
                <th>Deskripsi</th>
                <th width="120">Status</th>
                <th width="180" class="text-center">Aksi</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="loading">
                <td colspan="5" class="text-center py-5">
                  <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                  Memuat data kategori...
                </td>
              </tr>

              <tr v-else-if="categories.length === 0">
                <td colspan="5" class="text-center text-muted py-5">Tidak ada data kategori.</td>
              </tr>

              <tr v-for="(category, index) in categories" :key="category.id">
                <td>
                  {{ pageStart + index }}
                </td>

                <td class="fw-semibold">
                  {{ category.name }}
                </td>

                <td>
                  {{ category.description || "-" }}
                </td>

                <td>
                  <span :class="Number(category.is_active) === 1 ? 'badge bg-success-subtle text-success' : 'badge bg-danger-subtle text-danger'">
                    {{ Number(category.is_active) === 1 ? "Aktif" : "Nonaktif" }}
                  </span>
                </td>

                <td class="text-center">
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-info" title="Detail" @click="openDetail(category.id)">
                      <i class="ti ti-eye"></i>
                    </button>

                    <button class="btn btn-outline-primary" title="Edit" @click="openEdit(category.id)">
                      <i class="ti ti-edit"></i>
                    </button>

                    <button class="btn btn-outline-danger" title="Hapus" @click="deleteCategory(category)">
                      <i class="ti ti-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
          <div class="text-muted small">Menampilkan {{ pageStart }} sampai {{ pageEnd }} dari {{ pagination.total }} data</div>

          <div class="d-flex align-items-center gap-2">
            <span class="text-muted small"> Halaman {{ pagination.current_page }} dari {{ pagination.total_pages }} </span>

            <button class="btn btn-sm btn-outline-secondary" :disabled="!hasPrevious || loading" @click="previousPage">
              <i class="ti ti-chevron-left"></i>
              Sebelumnya
            </button>

            <button class="btn btn-sm btn-outline-secondary" :disabled="!hasNext || loading" @click="nextPage">
              Berikutnya
              <i class="ti ti-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <div v-if="showForm" class="modal fade show d-block" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              {{ isEditing ? "Edit Kategori" : "Tambah Kategori" }}
            </h5>

            <button type="button" class="btn-close" :disabled="saving" @click="closeForm"></button>
          </div>

          <form @submit.prevent="saveCategory">
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">
                  Nama Kategori
                  <span class="text-danger">*</span>
                </label>

                <input v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': formErrors.name }" maxlength="100" placeholder="Masukkan nama kategori" />

                <div v-if="formErrors.name" class="invalid-feedback">
                  {{ formErrors.name }}
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label"> Deskripsi </label>

                <textarea
                  v-model="form.description"
                  class="form-control"
                  :class="{
                    'is-invalid': formErrors.description,
                  }"
                  rows="3"
                  maxlength="1000"
                  placeholder="Deskripsi kategori"
                ></textarea>

                <div v-if="formErrors.description" class="invalid-feedback">
                  {{ formErrors.description }}
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label d-block"> Status </label>

                <div class="form-check form-switch">
                  <input id="categoryActive" v-model="form.is_active" class="form-check-input" type="checkbox" :true-value="1" :false-value="0" />

                  <label class="form-check-label" for="categoryActive">
                    {{ Number(form.is_active) === 1 ? "Aktif" : "Nonaktif" }}
                  </label>
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-light" :disabled="saving" @click="closeForm">Batal</button>

              <button type="submit" class="btn btn-primary" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>

                {{ saving ? "Menyimpan..." : "Simpan" }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <div v-if="showDetail && selectedCategory" class="modal fade show d-block" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Detail Kategori</h5>

            <button type="button" class="btn-close" @click="closeDetail"></button>
          </div>

          <div class="modal-body">
            <dl class="row mb-0">
              <dt class="col-4">ID</dt>
              <dd class="col-8">
                {{ selectedCategory.id }}
              </dd>

              <dt class="col-4">Nama</dt>
              <dd class="col-8">
                {{ selectedCategory.name }}
              </dd>

              <dt class="col-4">Deskripsi</dt>
              <dd class="col-8">
                {{ selectedCategory.description || "-" }}
              </dd>

              <dt class="col-4">Status</dt>
              <dd class="col-8">
                {{ Number(selectedCategory.is_active) === 1 ? "Aktif" : "Nonaktif" }}
              </dd>

              <dt class="col-4">Dibuat</dt>
              <dd class="col-8">
                {{ selectedCategory.created_at || "-" }}
              </dd>

              <dt class="col-4">Diperbarui</dt>
              <dd class="col-8">
                {{ selectedCategory.updated_at || "-" }}
              </dd>
            </dl>
          </div>

          <div class="modal-footer">
            <button class="btn btn-light" @click="closeDetail">Tutup</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Backdrop -->
    <div v-if="showForm || showDetail" class="modal-backdrop fade show"></div>
  </div>
</template>
