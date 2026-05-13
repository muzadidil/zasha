<script setup>
import 'leaflet/dist/leaflet.css';
import { LMap, LTileLayer, LMarker, LCircle } from '@vue-leaflet/vue-leaflet';

const props = defineProps({
  center: { type: Array, default: () => [-6.2, 106.8] },
  zoom: { type: Number, default: 13 },
  pickup: { type: Array, default: null },
  partner: { type: Array, default: null },
  radiusKm: { type: Number, default: null },
  height: { type: String, default: '320px' },
});

defineEmits(['click']);
</script>

<template>
  <div :style="{ height }" class="rounded overflow-hidden border border-slate-200">
    <l-map :zoom="zoom" :center="center" :use-global-leaflet="false" @click="$emit('click', $event)">
      <l-tile-layer
        url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"
        attribution="&copy; OpenStreetMap"
      />
      <l-marker v-if="pickup" :lat-lng="pickup" />
      <l-marker v-if="partner" :lat-lng="partner" />
      <l-circle
        v-if="pickup && radiusKm"
        :lat-lng="pickup"
        :radius="radiusKm * 1000"
        :color="'#4f46e5'"
        :fill-color="'#6366f1'"
        :fill-opacity="0.12"
      />
    </l-map>
  </div>
</template>
