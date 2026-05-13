<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api';
import OsmMap from '../components/OsmMap.vue';
import { useAuthStore } from '../stores/auth';
import { useCategoriesStore } from '../stores/categories';
import { useOrderStore } from '../stores/order';

const RACE_WINDOW_MS = 10_000;

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const orderStore = useOrderStore();
const categories = useCategoriesStore();
const error = ref('');
const acting = ref(false);
const rating = reactive({ stars: 5, comment: '', submitted: false, error: '' });
const now = ref(Date.now());
const isExpiredOptimistic = ref(false);
const optimisticExpiredAt = ref(null);
const showLateClaimModal = ref(false);
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
const isExpired = computed(() => order.value?.status === 'expired');
const isCancelled = computed(() => order.value?.status === 'cancelled');
const canRate = computed(() => order.value?.status === 'completed' && !rating.submitted);

// Show the expired modal as soon as the local countdown hits 0, even before the
// backend `orders:expire` scheduler fires (it runs every 10s). The lateClaim
// watcher below rolls this back if an OrderClaimed event sneaks in during the
// race window — partner can legitimately win the race at second 59.
const showExpiredModal = computed(() => (isExpired.value || isExpiredOptimistic.value) && !showLateClaimModal.value);

const secondsLeft = computed(() => {
  if (!order.value?.expires_at) return null;
  const diff = Math.floor((new Date(order.value.expires_at).getTime() - now.value) / 1000);
  return diff > 0 ? diff : 0;
});

// Flip optimistic expire when the local countdown drops to 0 while still
// searching. We only set it once — the OrderClaimed watcher decides whether to
// roll it back.
watch(secondsLeft, (s) => {
  if (s === 0 && isSearching.value && !isExpiredOptimistic.value) {
    isExpiredOptimistic.value = true;
    optimisticExpiredAt.value = Date.now();
  }
});

// Auto-reorder once we are confident the order really expired (status flipped
// OR optimistic flip has not been rolled back within the race window). Saves
// the user one modal click — they wanted the same order with a higher price
// anyway. Hold for the race window so a late OrderClaimed can still cancel it.
let autoReorderTimer = null;
watch([isExpired, isExpiredOptimistic], ([backendExpired, optimistic]) => {
  if (!backendExpired && !optimistic) return;
  if (showLateClaimModal.value) return;
  if (autoReorderTimer) return;
  const delay = backendExpired ? 600 : RACE_WINDOW_MS;
  autoReorderTimer = setTimeout(() => {
    if (showLateClaimModal.value) return;
    reorder();
  }, delay);
});

// Roll back the optimistic expired modal if a late OrderClaimed event arrives
// within the race window. We rely on the store recording the event time and
// then re-fetching the order (so partner info is populated).
watch(() => orderStore.lastClaimAt, (claimedAt) => {
  if (!claimedAt || !optimisticExpiredAt.value) return;
  if (claimedAt - optimisticExpiredAt.value <= RACE_WINDOW_MS) {
    isExpiredOptimistic.value = false;
    showLateClaimModal.value = true;
  }
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

function reorder() {
  if (!order.value) return;
  const slug = order.value.service_category?.slug;
  const cat = categories.bySlug(slug);
  const step = cat?.price_step ?? 0;
  const suggested = (order.value.current_price ?? 0) + step;
  const query = {
    slug,
    details: JSON.stringify(order.value.details ?? {}),
    suggested_price: String(suggested),
  };
  if (order.value.pickup) {
    query.lat = String(order.value.pickup.lat);
    query.lng = String(order.value.pickup.lng);
  }
  router.push({ name: 'orders.create', query });
}

onMounted(async () => {
  try {
    await orderStore.load(route.params.id);
    await categories.load();
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
  if (autoReorderTimer) clearTimeout(autoReorderTimer);
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

    <!-- Expired modal (optimistic or backend-confirmed) -->
    <Transition name="fade">
      <div v-if="showExpiredModal" class="fixed inset-0 bg-slate-900/60 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-sm w-full p-6 space-y-3">
          <div class="text-center">
            <div class="text-4xl">😕</div>
            <h2 class="text-lg font-semibold mt-2">Yah, tidak ada mitra yang mengambil</h2>
            <p class="text-sm text-slate-500 mt-1">
              Form order kamu akan otomatis terbuka lagi dengan harga lebih tinggi…
            </p>
          </div>
          <button @click="reorder" class="w-full bg-indigo-600 text-white rounded py-2.5 font-semibold">
            Order Ulang Sekarang
          </button>
          <button @click="router.push('/')" class="w-full border border-slate-300 text-slate-700 rounded py-2.5">
            Batal, kembali ke Home
          </button>
        </div>
      </div>
    </Transition>

    <!-- Late-claim modal: shown when a partner won the race within the race window
         after we already flipped to optimistic expired. -->
    <Transition name="fade">
      <div v-if="showLateClaimModal" class="fixed inset-0 bg-slate-900/60 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-sm w-full p-6 space-y-3">
          <div class="text-center">
            <div class="text-4xl">🎉</div>
            <h2 class="text-lg font-semibold mt-2">
              Order diambil oleh {{ order?.partner?.name ?? 'mitra' }}!
            </h2>
            <p class="text-sm text-slate-500 mt-1">Mitra menerima order di detik terakhir.</p>
          </div>
          <button @click="showLateClaimModal = false"
                  class="w-full bg-indigo-600 text-white rounded py-2.5 font-semibold">
            Lihat detail mitra
          </button>
        </div>
      </div>
    </Transition>

    <!-- Cancelled banner (lighter, no modal) -->
    <div v-if="isCancelled" class="bg-rose-50 border border-rose-200 text-rose-700 rounded p-3 text-sm">
      Order ini dibatalkan.
    </div>
  </div>
  <p v-else-if="error" class="text-rose-600">{{ error }}</p>
  <p v-else class="text-slate-500">Memuat…</p>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
