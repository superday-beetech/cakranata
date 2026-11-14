<script setup lang="ts">
import { computed, ref } from "vue";

import { HTTP_VERBS, type HttpVerb } from "../types/route-registry";

const props = defineProps<{
  endpoint: string;
  verbs: readonly HttpVerb[];
}>();

const emit = defineEmits<{
  (e: "select", payload: { endpoint: string; verbs: HttpVerb[] }): void;
  (e: "close"): void;
}>();

const selected = ref<ReadonlySet<HttpVerb>>(new Set(props.verbs));

const ordered = computed<HttpVerb[]>(() => HTTP_VERBS.filter((verb) => selected.value.has(verb)));

function toggle(verb: HttpVerb): void {
  const next = new Set(selected.value);
  if (next.has(verb)) next.delete(verb);
  else next.add(verb);
  selected.value = next;
}

function submit(): void {
  emit("select", { endpoint: props.endpoint, verbs: [...ordered.value] });
}
</script>

<template>
  <div>
    <p class="text-muted small mb-3">
      <code class="text-body">{{ endpoint }}</code>
    </p>

    <div class="d-flex flex-wrap gap-3">
      <label v-for="verb in HTTP_VERBS" :key="verb" class="form-check">
        <input class="form-check-input" type="checkbox" :value="verb" :checked="selected.has(verb)" @change="toggle(verb)" />
        <span class="form-check-label">{{ verb }}</span>
      </label>
    </div>

    <p class="small text-muted mt-3 mb-0">
      Pilih metode HTTP yang diizinkan untuk endpoint ini. Konfigurasi ini
      <strong>bukan</strong> pengganti otorisasi permission pengguna.
    </p>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <button type="button" class="btn btn-light" @click="emit('close')">Batal</button>
      <button type="button" class="btn btn-primary" @click="submit">Simpan</button>
    </div>
  </div>
</template>
