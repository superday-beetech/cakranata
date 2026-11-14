<script setup lang="ts">
import { computed, ref } from "vue";

import type { ShieldPreviewItem } from "../types/route-registry";

const props = defineProps<{
  items: readonly ShieldPreviewItem[];
}>();

const emit = defineEmits<{
  (e: "select", payload: { items: Array<{ permission: string; description: string }> }): void;
  (e: "close"): void;
}>();

const drafts = ref<string[]>(props.items.map((item) => item.description));

const stats = computed(() => ({
  total: props.items.length,
  fresh: props.items.filter((item) => !item.exists).length,
  existing: props.items.filter((item) => item.exists).length,
}));

const invalid = computed(() => drafts.value.some((d) => d.trim() === "" || d.length > 255));

function confirm(): void {
  if (invalid.value) return;

  emit("select", {
    items: props.items.map((item, index) => ({
      permission: item.permission,
      description: drafts.value[index].trim(),
    })),
  });
}
</script>

<template>
  <div>
    <p class="text-muted mb-3">Periksa dan edit deskripsi sebelum menyimpan ke Shield.</p>

    <div class="d-flex gap-3 mb-3 flex-wrap small">
      <span
        >Jumlah: <strong>{{ stats.total }}</strong></span
      >
      <span
        >Baru: <strong class="text-primary">{{ stats.fresh }}</strong></span
      >
      <span
        >Sudah ada: <strong class="text-secondary">{{ stats.existing }}</strong></span
      >
    </div>

    <div class="table-responsive border rounded-2">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 28%">Permission</th>
            <th>Deskripsi</th>
            <th style="width: 110px" class="text-center">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, index) in items" :key="item.permission">
            <td>
              <code>{{ item.permission }}</code>
            </td>
            <td>
              <textarea v-model="drafts[index]" class="form-control form-control-sm" rows="2" maxlength="255" :aria-label="`Deskripsi permission ${item.permission}`"></textarea>
            </td>
            <td class="text-center">
              <span class="badge" :class="item.exists ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary'">
                {{ item.exists ? "Sudah ada" : "Baru" }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="invalid" class="small text-danger mt-2 mb-0">Setiap permission harus memiliki deskripsi 1–255 karakter.</p>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <button type="button" class="btn btn-light" @click="emit('close')">Batal</button>
      <button type="button" class="btn btn-primary" :disabled="invalid" @click="confirm">Konfirmasi Sinkronisasi</button>
    </div>
  </div>
</template>
