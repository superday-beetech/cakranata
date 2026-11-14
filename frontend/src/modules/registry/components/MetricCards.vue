<script setup lang="ts">
defineProps<{
  discovered: number | string;
  registered: number | string;
  routable: number | string;
}>();
</script>

<template>
  <div class="row g-2">
    <div class="col-4">
      <div class="card border-0 shadow-sm bg-body-tertiary h-100">
        <div class="card-body p-3">
          <div class="text-muted small mb-1"><i class="ti ti-search text-primary me-1"></i> Ditemukan</div>
          <h4 class="mb-0 fw-semibold">{{ discovered }}</h4>
        </div>
      </div>
    </div>

    <div class="col-4">
      <div class="card border-0 shadow-sm bg-body-tertiary h-100">
        <div class="card-body p-3">
          <div class="text-muted small mb-1"><i class="ti ti-database text-success me-1"></i> Terdaftar</div>
          <h4 class="mb-0 fw-semibold">{{ registered }}</h4>
        </div>
      </div>
    </div>

    <div class="col-4">
      <div class="card border-0 shadow-sm bg-body-tertiary h-100">
        <div class="card-body p-3">
          <div class="text-muted small mb-1"><i class="ti ti-route text-info me-1"></i> Routable</div>
          <h4 class="mb-0 fw-semibold">{{ routable }}</h4>
        </div>
      </div>
    </div>
  </div>
</template>
