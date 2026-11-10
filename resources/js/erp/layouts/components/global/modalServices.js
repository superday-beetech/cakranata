import { reactive, shallowRef, markRaw } from "vue";

export const modalState = reactive({
  visible: false,
  title: "",
  size: "lg",
  centered: false,
  scrollable: false,
  component: null,
  props: {},
  loading: false,
  error: null,
});

let requestId = 0;

export function openModal(options = {}) {
  if (!options.component) {
    throw new Error("Komponen modal wajib ditentukan.");
  }

  const id = ++requestId;

  Object.assign(modalState, {
    visible: true,
    title: options.title ?? "Informasi",
    size: options.size ?? "lg",
    centered: options.centered ?? false,
    scrollable: options.centered ?? false,
    component: null,
    props: options.props ?? {},
    loading: true,
    error: null,
  });

  // Component dapat berupa import() agar file baru dimuat saat dibutuhkan.
  Promise.resolve()
    .then(() => {
      if (typeof options.component === "function") {
        return options.component();
      }

      return options.component;
    })
    .then((result) => {
      if (id !== requestId || !modalState.visible) return;

      const component = result?.default ?? result;

      if (!component) {
        throw new Error("Komponen modal tidak ditemukan.");
      }

      modalState.component = markRaw(component);
      modalState.loading = false;
    })
    .catch((error) => {
      if (id !== requestId || !modalState.visible) return;

      console.error("Gagal memuat modal:", error);
      modalState.error = "Konten gagal dimuat. Silakan coba lagi.";
      modalState.loading = false;
    });
}

export function closeModal() {
  requestId++;

  Object.assign(modalState, {
    visible: false,
    component: null,
    props: {},
    loading: false,
    error: null,
    title: "",
  });
}

export function retryModal() {
  // Retry dilakukan dengan memanggil openModal kembali
  // menggunakan konfigurasi komponen yang sama dari pemanggil.
}
