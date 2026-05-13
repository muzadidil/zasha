<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import OsmMap from '../components/OsmMap.vue';
import { useCategoriesStore } from '../stores/categories';

const router = useRouter();
const categories = useCategoriesStore();
const center = ref([-6.2, 106.8]);
const pickup = ref(null);

onMounted(async () => {
  await categories.load();
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition((pos) => {
      const ll = [pos.coords.latitude, pos.coords.longitude];
      center.value = ll;
      pickup.value = ll;
    });
  }
});

function onMapClick(e) {
  if (!e?.latlng) return;
  pickup.value = [e.latlng.lat, e.latlng.lng];
}

function pickCategory(slug) {
  const params = { slug };
  if (pickup.value) {
    params.lat = pickup.value[0];
    params.lng = pickup.value[1];
  }
  router.push({ name: 'orders.create', query: params });
}
</script>

<template>
  <div class="space-y-3">
    <h1 class="text-xl font-semibold">Mau order apa?</h1>
    <OsmMap :center="center" :pickup="pickup" @click="onMapClick" height="320px" />
    <p class="text-xs text-slate-500">Klik di peta untuk pilih lokasi pickup, atau lanjut tanpa lokasi untuk WFH.</p>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
      <button
        v-for="c in categories.items"
        :key="c.id"
        type="button"
        @click="pickCategory(c.slug)"
        class="border border-slate-200 rounded py-4 text-sm hover:border-indigo-400 hover:bg-indigo-50"
      >
        <div class="font-semibold">{{ c.name }}</div>
        <div class="text-xs text-slate-500 mt-1">
          Rp {{ c.min_price.toLocaleString('id-ID') }}+
        </div>
      </button>
    </div>
  </div>
</template>
