<script setup>
import { defineAsyncComponent, defineProps } from 'vue';
import LoadingSpinner from './ui/LoadingSpinner.vue';

const props = defineProps({
  center: { type: Array, default: () => [-6.2, 106.8] },
  zoom: { type: Number, default: 13 },
  pickup: { type: Array, default: null },
  partner: { type: Array, default: null },
  radiusKm: { type: Number, default: null },
  height: { type: String, default: '320px' },
  showPulse: { type: Boolean, default: false },
});

defineEmits(['click']);

// Lazy-load the heavy Leaflet bundle (~150KB) so Home + simple screens don't
// pull it in. Loaded the moment the user reaches a screen that mounts OsmMap.
const OsmMapInner = defineAsyncComponent({
  loader: () => import('./OsmMapInner.vue'),
  loadingComponent: LoadingSpinner,
  delay: 100,
  timeout: 8000,
});
</script>

<template>
  <div :style="{ height }" class="rounded-card overflow-hidden border border-slate-200 bg-slate-100 relative">
    <OsmMapInner
      :center="center"
      :zoom="zoom"
      :pickup="pickup"
      :partner="partner"
      :radius-km="radiusKm"
      :show-pulse="showPulse"
      @click="$emit('click', $event)"
    />
  </div>
</template>
