<script setup>
import { ref, onMounted, nextTick } from "vue";
import { closeModal } from "./global/modalServices.js";

const searchInput = ref(null);
const keyword = ref("");

const pages = [
  {
    title: "Modern",
    path: "/dashboards/dashboard1",
  },
];

const filteredPages = ref([...pages]);

function searchPages() {
  const query = keyword.value.trim().toLowerCase();

  filteredPages.value = pages.filter((page) => `${page.title} ${page.path}`.toLowerCase().includes(query));
}

function openPage(path) {
  // Tutup modal sebelum navigasi.
  closeModal();

  // Ganti dengan router.push(path) jika menggunakan Vue Router.
  window.location.href = path;
}

onMounted(async () => {
  await nextTick();
  searchInput.value?.focus();
});
</script>

<template>
  <div class="modal fade show" style="display: block" tabindex="-1" role="dialog" aria-modal="true" @click.self="closeModal">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered modal-lg">
      <div class="modal-content rounded-1">
        <div class="modal-header border-bottom">
          <input ref="searchInput" v-model="keyword" @input="searchPages" type="search" class="form-control fs-3" placeholder="Search here" aria-label="Cari halaman" />

          <button type="button" class="btn-close" aria-label="Tutup" @click="closeModal"></button>
        </div>

        <div class="modal-body message-body">
          <h5 class="mb-0 fs-5 p-1">Quick Page Links</h5>

          <ul class="list mb-0 py-2">
            <li v-for="page in filteredPages" :key="page.path" class="p-1 mb-1 bg-hover-light-black">
              <a href="#" @click.prevent="openPage(page.path)">
                <span class="d-block">{{ page.title }}</span>
                <span class="text-muted d-block">{{ page.path }}</span>
              </a>
            </li>

            <li v-if="filteredPages.length === 0" class="p-2 text-muted">Halaman tidak ditemukan.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>
