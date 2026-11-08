<script setup lang="ts">
/**
 * Pembungkus panel bersama: kartu + judul + ikon + shortcut.
 * Menggantikan markup heading yang sebelumnya diulang di PosView dan tiap komponen.
 */
withDefaults(
  defineProps<{
    title: string;
    subtitle?: string;
    icon?: string;
    tone?: "primary" | "info" | "success" | "warning" | "danger";
    shortcut?: string;
    /** Hilangkan padding isi (untuk tabel penuh). */
    flush?: boolean;
    /** Isi bisa di-scroll di dalam panel. */
    scroll?: boolean;
  }>(),
  { tone: "primary" },
);
</script>

<template>
  <section class="card pos-panel">
    <header class="pos-panel-head">
      <span v-if="icon" class="pos-icon-tile" :class="`tone-${tone}`" aria-hidden="true">
        <i :class="icon"></i>
      </span>

      <div class="pos-panel-title">
        <h6>{{ title }}</h6>
        <small v-if="subtitle">{{ subtitle }}</small>
      </div>

      <div class="pos-panel-extra">
        <slot name="actions" />
        <kbd v-if="shortcut">{{ shortcut }}</kbd>
      </div>
    </header>

    <div class="pos-panel-body" :class="{ 'is-flush': flush, 'is-scroll': scroll }">
      <slot />
    </div>

    <footer v-if="$slots.footer" class="pos-panel-foot">
      <slot name="footer" />
    </footer>
  </section>
</template>
