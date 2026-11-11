<script setup lang="ts">
import { reactive } from "vue";
import { notify } from "@/utils/notification";

const emit = defineEmits<{ close: [] }>();

const KEY = "pos.printer";

function load(): Record<string, unknown> {
  try {
    return JSON.parse(localStorage.getItem(KEY) ?? "{}");
  } catch {
    return {};
  }
}

const form = reactive({ paperWidth: "80", autoPrint: true, copies: 1, ...load() });

function save(): void {
  try {
    localStorage.setItem(KEY, JSON.stringify(form));
    notify.success("Pengaturan printer disimpan.");
    emit("close");
  } catch {
    notify.error("Gagal menyimpan pengaturan printer.");
  }
}
</script>

<template>
  <form class="d-flex flex-column gap-3" @submit.prevent="save">
    <div>
      <label class="form-label" for="pos-paper">Lebar kertas</label>
      <select id="pos-paper" v-model="form.paperWidth" class="form-select">
        <option value="58">58 mm</option>
        <option value="80">80 mm</option>
      </select>
    </div>

    <div class="form-check form-switch">
      <input id="pos-autoprint" v-model="form.autoPrint" type="checkbox" class="form-check-input" />
      <label class="form-check-label" for="pos-autoprint">Cetak struk otomatis setelah transaksi selesai</label>
    </div>

    <div>
      <label class="form-label" for="pos-copies">Jumlah salinan</label>
      <input id="pos-copies" v-model.number="form.copies" type="number" min="1" max="5" class="form-control" />
    </div>

    <button type="submit" class="btn btn-primary">Simpan</button>
  </form>
</template>
