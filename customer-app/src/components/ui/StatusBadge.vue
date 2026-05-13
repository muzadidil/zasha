<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: { type: String, required: true },
});

const labelMap = {
  searching: 'Mencari mitra',
  claimed: 'Diterima',
  in_progress: 'Sedang dikerjakan',
  completed: 'Selesai',
  cancelled: 'Dibatalkan',
  expired: 'Kedaluwarsa',
  draft: 'Draft',
};

const colorMap = {
  searching: 'bg-amber-50 text-amber-700 border-amber-200',
  claimed: 'bg-brand-50 text-brand-700 border-brand-200',
  in_progress: 'bg-brand-50 text-brand-700 border-brand-200',
  completed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  cancelled: 'bg-rose-50 text-rose-700 border-rose-200',
  expired: 'bg-slate-100 text-slate-700 border-slate-200',
  draft: 'bg-slate-100 text-slate-700 border-slate-200',
};

const label = computed(() => labelMap[props.status] ?? props.status);
const color = computed(() => colorMap[props.status] ?? 'bg-slate-100 text-slate-700 border-slate-200');
</script>

<template>
  <span :class="['inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border text-[11px] font-semibold', color]">
    <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70" />
    {{ label }}
  </span>
</template>
