<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api';
import OsmMap from '../components/OsmMap.vue';
import { useAuthStore } from '../stores/auth';
import { useOrderStore } from '../stores/order';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const orderStore = useOrderStore();
const error = ref('');
const acting = ref(false);
const rating = reactive({ stars: 5, comment: '', submitted: false, error: '' });
const now = ref(Date.now());
let tickInterval = null;

const order = computed(() => orderStore.currentOrder);
const radiusKm = computed(() => orderStore.activeRadiusKm);
const pickup = computed(() => {
  const p = order.value?.pickup;
  if (!p) return null;
  return [p.lat, p.lng];
});

const isSearching = computed(() => order.value?.status === 'searching');
const isClaimed = computed(() => order.value?.status === 'claimed' || order.value?.status === 'in_progress');
const canRate = computed(() => order.value?.status === 'completed' && !rating.submitted);

const secondsLeft = computed(() => {
  if (!order.value?.expires_at) return null;
  const diff = Math.floor((new Date(order.value.expires_at).getTime() - now.value) / 1000);
  return diff > 0 ? diff : 0;
});

async function increasePrice() {
  acting.value = true;
  try {
    await orderStore.increasePrice(route.params.id);
  } catch (e) {
    error.value = e.response?.data?.error?.message ?? 'Gagal menaikkan harga.';
  } finally {
    acting.value = false;
  }
}

async function cancel() {
  acting.value = true;
  try {
    await orderStore.cancel(route.params.id);
  } catch (e) {
    error.value = e.response?.data?.error?.message ?? 'Gagal membatalkan order.';
  } finally {
    acting.value = false;
  }
}

async function submitRating() {
  rating.error = '';
  try {
    await api.post(`/customer/orders/${route.params.id}/rate`, {
      stars: rating.stars,
      comment: rating.comment || undefined,
    });
    rating.submitted = true;
  } catch (e) {
    rating.error = e.response?.data?.error?.message ?? 'Gagal mengirim rating.';
  }
}

function waLink(phone) {
  if (!phone) return '#';
  const clean = String(phone).replace(/[^0-9]/g, '');
  return `https://wa.me/${clean}`;
}

onMounted(async () => {
  try {
    await orderStore.load(route.params.id);
  } catch (e) {
    error.value = e.response?.data?.error?.message ?? 'Gagal memuat detail order.';
    return;
  }
  if (auth.token) orderStore.subscribe(route.params.id, auth.token);
  tickInterval = setInterval(() => { now.value = Date.now(); }, 1000);
});

onUnmounted(() => {
  orderStore.unsubscribe();
  if (tickInterval) clearInterval(tickInterval);
});
</script>

<template>
  <div v-if="order" class="space-y-3">
    <button class="text-sm text-slate-500" @click="router.push('/')">← Beranda</button>

    <!-- Searching: map with expanding radius -->
    <div v-if="isSearching && order.service_category?.requires_geolocation">
      <OsmMap :center="pickup ?? [-6.2, 106.8]" :pickup="pickup" :radius-km="radiusKm" height="320px" />
      <div class="bg-white rounded shadow p-4 mt-2 space-y-2">
        <div class="text-center">
          <div class="text-lg font-semibold">Mencari mitra…</div>
          <div v-if="radiusKm" class="text-sm text-slate-500">Radius aktif: {{ radiusKm }} km</div>
          <div v-if="secondsLeft !== null" class="text-sm text-amber-600 mt-1">⏱️ {{ secondsLeft }} detik tersisa</div>
        </div>
      </div>
    </div>

    <div v-else-if="isSearching" class="bg-white rounded shadow p-4 text-center">
      <div class="text-lg font-semibold">Mencari mitra WFH…</div>
      <div v-if="secondsLeft !== null" class="text-sm text-amber-600 mt-1">⏱️ {{ secondsLeft }} detik tersisa</div>
    </div>

    <!-- Claimed/in_progress: tracking -->
    <div v-if="isClaimed">
      <OsmMap
        v-if="pickup"
        :center="pickup"
        :pickup="pickup"
        :partner="orderStore.partnerLocation"
        height="320px"
      />
      <div class="bg-white rounded shadow p-4 mt-2 space-y-2">
        <div class="font-semibold">✅ Mitra: {{ order.partner?.name ?? '—' }}</div>
        <div class="text-sm text-slate-500" v-if="order.partner?.average_rating">⭐ {{ order.partner.average_rating }}</div>
        <a v-if="order.partner?.phone" :href="waLink(order.partner.phone)" target="_blank"
           class="inline-block bg-emerald-500 text-white rounded px-4 py-2 text-sm">Chat WA</a>
      </div>
    </div>

    <div class="bg-white rounded shadow p-4 space-y-1">
      <div><span class="text-slate-500 text-sm">Kategori:</span> {{ order.service_category?.name }}</div>
      <div><span class="text-slate-500 text-sm">Status:</span> {{ order.status }}</div>
      <div><span class="text-slate-500 text-sm">Harga:</span> Rp {{ order.current_price.toLocaleString('id-ID') }}</div>
    </div>

    <div v-if="isSearching" class="flex gap-2">
      <button @click="increasePrice" :disabled="acting" class="flex-1 bg-indigo-600 text-white rounded py-2 disabled:opacity-50">+ Naikkan Harga</button>
      <button @click="cancel" :disabled="acting" class="flex-1 border border-rose-300 text-rose-600 rounded py-2 disabled:opacity-50">Batalkan</button>
    </div>

    <div v-if="canRate" class="bg-white rounded shadow p-4 space-y-3">
      <h2 class="font-semibold">Beri Rating Mitra</h2>
      <div class="flex gap-2">
        <button v-for="s in 5" :key="s" type="button" @click="rating.stars = s"
                :class="['w-10 h-10 rounded-full text-lg font-semibold', rating.stars >= s ? 'bg-amber-400 text-white' : 'bg-slate-100 text-slate-500']">
          {{ s }}
        </button>
      </div>
      <textarea v-model="rating.comment" maxlength="300" placeholder="Komentar (opsional)"
                class="w-full border rounded p-2"></textarea>
      <p v-if="rating.error" class="text-rose-600 text-sm">{{ rating.error }}</p>
      <button @click="submitRating" class="bg-indigo-600 text-white rounded px-4 py-2">Kirim Rating</button>
    </div>

    <p v-if="rating.submitted" class="text-emerald-600 text-sm">Terima kasih, rating sudah dikirim.</p>
    <p v-if="error" class="text-rose-600 text-sm">{{ error }}</p>
  </div>
  <p v-else-if="error" class="text-rose-600">{{ error }}</p>
  <p v-else class="text-slate-500">Memuat…</p>
</template>
