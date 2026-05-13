<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { MapPinIcon } from '@heroicons/vue/24/outline';
import OsmMap from '../components/OsmMap.vue';
import CategoryIcon from '../components/ui/CategoryIcon.vue';
import LoadingSpinner from '../components/ui/LoadingSpinner.vue';
import { useAuthStore } from '../stores/auth';
import { useCategoriesStore } from '../stores/categories';

const router = useRouter();
const categories = useCategoriesStore();
const auth = useAuthStore();
const center = ref([-8.1727, 113.7000]);
const pickup = ref(null);
const locating = ref(false);

const greeting = computed(() => {
  const h = new Date().getHours();
  if (h < 11) return 'Selamat pagi';
  if (h < 15) return 'Selamat siang';
  if (h < 19) return 'Selamat sore';
  return 'Selamat malam';
});

const firstName = computed(() => auth.user?.name?.split(' ')[0] ?? 'di Zasha');

onMounted(async () => {
  await categories.load();
  if (!navigator.geolocation) return;
  locating.value = true;
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      const ll = [pos.coords.latitude, pos.coords.longitude];
      center.value = ll;
      pickup.value = ll;
      locating.value = false;
    },
    () => { locating.value = false; },
    { timeout: 8000 },
  );
});

function onMapClick(e) {
  if (!e?.latlng) return;
  pickup.value = [e.latlng.lat, e.latlng.lng];
}

function pickCategory(slug) {
  const query = { slug };
  if (pickup.value) {
    query.lat = pickup.value[0];
    query.lng = pickup.value[1];
  }
  router.push({ name: 'orders.create', query });
}
</script>

<template>
  <div class="max-w-md mx-auto sm:max-w-2xl">
    <!-- Hero greeting -->
    <section class="px-5 pt-8 pb-4" :style="{ paddingTop: 'calc(2rem + var(--safe-top))' }">
      <p class="text-sm text-ink-soft">{{ greeting }} 👋</p>
      <h1 class="text-2xl font-bold text-ink mt-0.5 truncate">{{ firstName }}</h1>
      <p class="text-sm text-ink-soft mt-1">Mau order apa hari ini?</p>
    </section>

    <!-- Map preview -->
    <section class="px-5">
      <div class="relative">
        <OsmMap :center="center" :pickup="pickup" :height="'220px'" @click="onMapClick" />
        <div class="absolute top-2 left-2 right-2 flex items-center gap-1.5 bg-white/95 backdrop-blur rounded-full shadow-card px-3 py-1.5 text-xs text-ink-muted">
          <MapPinIcon class="w-4 h-4 text-brand-600" />
          <span v-if="locating">Mengambil lokasi…</span>
          <span v-else-if="pickup" class="truncate">Pickup: {{ pickup[0].toFixed(4) }}, {{ pickup[1].toFixed(4) }}</span>
          <span v-else>Klik di peta untuk pilih lokasi pickup</span>
        </div>
      </div>
    </section>

    <!-- Category grid -->
    <section class="px-5 pt-6">
      <h2 class="text-xs uppercase tracking-wide font-semibold text-ink-soft mb-3">Pilih layanan</h2>
      <LoadingSpinner v-if="categories.items.length === 0" />
      <div v-else class="grid grid-cols-2 gap-3">
        <button
          v-for="c in categories.items"
          :key="c.id"
          @click="pickCategory(c.slug)"
          class="group bg-white rounded-card shadow-card border border-slate-100 p-4 text-left transition-all duration-200 hover:-translate-y-0.5 hover:shadow-elevated active:scale-[0.98]"
        >
          <CategoryIcon :slug="c.slug" size="md" />
          <div class="mt-3 font-semibold text-ink">{{ c.name }}</div>
          <div class="mt-0.5 text-xs text-ink-soft">
            Mulai Rp {{ c.min_price.toLocaleString('id-ID') }}
          </div>
        </button>
      </div>
    </section>

    <!-- Tips card -->
    <section class="px-5 pt-6 pb-6">
      <div class="rounded-card bg-gradient-to-br from-brand-50 to-violet-50 border border-brand-100 p-4">
        <h3 class="text-sm font-semibold text-brand-700">💡 Tips cepat dapat mitra</h3>
        <p class="text-xs text-ink-muted mt-1 leading-relaxed">
          Pastikan lokasi pickup sudah benar di peta dan harga yang ditawarkan kompetitif.
          Pelanggan bisa naikkan harga kapan saja saat masih dalam pencarian.
        </p>
      </div>
    </section>
  </div>
</template>
