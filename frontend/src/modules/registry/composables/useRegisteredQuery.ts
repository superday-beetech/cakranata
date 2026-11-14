// =============================================================================
// Adaptor query tabel Registered.
// =============================================================================
// Menyatukan debounce pencarian + perPage + page agar view tidak perlu
// mengurus timer sendiri. Semua perubahan memanggil action store.

import { onUnmounted, ref, watch } from "vue";

import { useRouteRegistryStore } from "../stores/routeRegistryStore";

const SEARCH_DEBOUNCE_MS = 300;

export function useRegisteredQuery() {
  const store = useRouteRegistryStore();

  // Sumber kebenaran UI = store.query, tapi kita butuh buffer untuk input
  // agar tidak memicu request tiap ketikan.
  const searchInput = ref(store.query.search);
  let timer: ReturnType<typeof setTimeout> | undefined;

  watch(
    () => store.query.search,
    (v) => {
      if (v !== searchInput.value) searchInput.value = v;
    },
  );

  watch(searchInput, (value) => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
      const trimmed = value.trim();
      if (trimmed === store.query.search) return;
      store.changeSearch(trimmed).catch(() => {
        /* sudah dinotifikasi store */
      });
    }, SEARCH_DEBOUNCE_MS);
  });

  onUnmounted(() => {
    if (timer) clearTimeout(timer);
  });

  async function reload(): Promise<void> {
    try {
      await store.reloadRegistered();
    } catch {
      /* sudah dinotifikasi store */
    }
  }

  function setPerPage(perPage: number): void {
    store.changePerPage(perPage).catch(() => {});
  }

  function goToPage(page: number): void {
    store.changePage(page).catch(() => {});
  }

  return {
    // state
    items: () => store.registeredItems,
    pagination: () => store.pagination,
    perPage: () => store.query.perPage,
    search: searchInput,
    loading: () => store.loading.registered,
    // actions
    reload,
    setPerPage,
    goToPage,
  };
}
