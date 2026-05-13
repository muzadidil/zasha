<script setup>
import 'leaflet/dist/leaflet.css';
import { LMap, LTileLayer, LMarker, LCircle } from '@vue-leaflet/vue-leaflet';

defineProps({
  center: { type: Array, default: () => [-6.2, 106.8] },
  zoom: { type: Number, default: 13 },
  pickup: { type: Array, default: null },
  partner: { type: Array, default: null },
  radiusKm: { type: Number, default: null },
  showPulse: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
  <l-map :zoom="zoom" :center="center" :use-global-leaflet="false" @click="$emit('click', $event)" style="z-index: 0">
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
      color="#4f46e5"
      fill-color="#6366f1"
      :fill-opacity="0.14"
      :weight="2"
    />
    <l-circle
      v-if="pickup && radiusKm && showPulse"
      :lat-lng="pickup"
      :radius="radiusKm * 1000"
      color="#6366f1"
      :fill-opacity="0.05"
      :weight="1"
      class="map-pulse"
    />
  </l-map>
</template>

<style>
.leaflet-interactive.map-pulse {
  animation: mapPulse 2.4s ease-out infinite;
  transform-origin: center;
  transform-box: fill-box;
}
@keyframes mapPulse {
  0%   { stroke-opacity: 0.5; fill-opacity: 0.06; transform: scale(0.6); }
  100% { stroke-opacity: 0;   fill-opacity: 0;    transform: scale(1.4); }
}
</style>
