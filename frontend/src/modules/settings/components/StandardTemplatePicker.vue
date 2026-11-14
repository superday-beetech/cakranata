<script setup lang="ts">
import type { StandardTemplatePreset } from "../types/printTemplate";
import { standardTemplates } from "../constants/standardTemplates";

const emit = defineEmits<{
  pick: [StandardTemplatePreset];
}>();
</script>

<template>
  <div class="standard-picker">
    <div class="row g-3">
      <div v-for="tpl in standardTemplates" :key="tpl.id" class="col-md-6 col-xl-4">
        <div class="card template-card h-100" @click="emit('pick', tpl)">
          <div class="card-body">
            <div class="d-flex align-items-start gap-2 mb-2">
              <div class="template-icon">
                <i :class="tpl.icon"></i>
              </div>
              <div>
                <h6 class="mb-0">{{ tpl.name }}</h6>
                <small class="text-muted"> {{ tpl.paper.width }} × {{ tpl.paper.height }} mm · {{ tpl.type }} </small>
              </div>
            </div>
            <p class="text-muted small mb-0">{{ tpl.description }}</p>
          </div>
          <div class="card-footer bg-transparent border-top-0 pt-0">
            <button class="btn btn-sm btn-primary w-100"><i class="ti ti-wand me-1"></i> Gunakan Template</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.template-card {
  cursor: pointer;
  transition:
    transform 0.15s,
    box-shadow 0.15s;
}
.template-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(93, 135, 255, 0.15);
  border-color: #5d87ff;
}
.template-icon {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: #e7efff;
  color: #5d87ff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
}
</style>
