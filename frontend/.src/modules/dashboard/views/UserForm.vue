<script setup lang="ts">
defineProps<{
  userId?: number;
}>();

const emit = defineEmits<{
  close: [];
}>();

function save() {
  // Proses penyimpanan API di sini

  emit("close");
}
</script>

<template>
  <form @submit.prevent="save">
    <div class="mb-3">
      <label class="form-label">Nama Pengguna</label>
      <input type="text" class="form-control" placeholder="Masukkan nama" />
    </div>

    <div class="d-flex justify-content-end gap-2">
      <button type="button" class="btn btn-light" @click="emit('close')">Batal</button>

      <button type="submit" class="btn btn-primary">Simpan</button>
    </div>
  </form>
</template>
