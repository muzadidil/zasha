<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import api from '../api';
import { createEcho, getEcho } from '../echo';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const orders = ref([]);
const loading = ref(true);
const error = ref('');
const claiming = ref(null);
const claimedNotice = ref('');
let privateChannel = null;
let orderChannels = new Map();

let pingAudio = null;
function playPing() {
  try {
    if (!pingAudio) {
      pingAudio = new Audio('data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=');
    }
    pingAudio.play().catch(() => {});
  } catch (_) { /* ignore */ }
}

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/partner/orders/available');
    orders.value = data.data;
  } catch (e) {
    error.value = e.response?.data?.error?.message ?? 'Gagal memuat order.';
  } finally {
    loading.value = false;
  }
}

function upsertOrder(entry) {
  const idx = orders.value.findIndex((o) => o.id === entry.id);
  if (idx === -1) orders.value.unshift(entry);
  else orders.value[idx] = { ...orders.value[idx], ...entry };
}

function subscribeToOrder(echo, orderId) {
  if (orderChannels.has(orderId)) return;
  const ch = echo.channel(`order.${orderId}`);
  ch.listen('.OrderClaimed', () => {
    orders.value = orders.value.filter((o) => o.id !== orderId);
    orderChannels.delete(orderId);
  });
  ch.listen('.OrderCancelled', () => {
    orders.value = orders.value.filter((o) => o.id !== orderId);
    orderChannels.delete(orderId);
  });
  ch.listen('.OrderExpired', () => {
    orders.value = orders.value.filter((o) => o.id !== orderId);
    orderChannels.delete(orderId);
  });
  ch.listen('.OrderPriceUpdated', (e) => {
    const order = orders.value.find((o) => o.id === e.order_id);
    if (order) order.current_price = e.current_price;
  });
  orderChannels.set(orderId, ch);
}

async function claim(orderId) {
  claiming.value = orderId;
  error.value = '';
  claimedNotice.value = '';
  try {
    await api.post(`/partner/orders/${orderId}/claim`);
    orders.value = orders.value.filter((o) => o.id !== orderId);
    claimedNotice.value = 'Order berhasil diambil. Buka halaman order untuk mulai.';
  } catch (e) {
    error.value = e.response?.data?.error?.message ?? 'Order sudah diambil mitra lain.';
    orders.value = orders.value.filter((o) => o.id !== orderId);
  } finally {
    claiming.value = null;
  }
}

onMounted(async () => {
  await load();
  if (!auth.token || !auth.user?.id) return;

  const echo = getEcho() ?? createEcho(auth.token);
  privateChannel = echo.private(`partner.${auth.user.id}`);
  privateChannel.listen('.OrderAvailableForPartner', (e) => {
    upsertOrder({
      id: e.order_id,
      service_category: { slug: e.category },
      current_price: e.current_price,
      details: e.details,
      pickup: e.pickup,
      distance_km: e.distance_km,
      expires_at: e.expires_at,
    });
    subscribeToOrder(echo, e.order_id);
    playPing();
  });

  orders.value.forEach((o) => subscribeToOrder(echo, o.id));
});

onUnmounted(() => {
  privateChannel = null;
  orderChannels.clear();
});
</script>

<template>
  <div class="space-y-3">
    <h1 class="text-xl font-semibold text-amber-400">Order Tersedia</h1>
    <p v-if="error" class="text-rose-400 text-sm">{{ error }}</p>
    <p v-if="claimedNotice" class="text-emerald-400 text-sm">{{ claimedNotice }}</p>
    <p v-if="loading" class="text-slate-400">Memuat…</p>
    <p v-else-if="!orders.length" class="text-slate-400">Belum ada order di area kamu.</p>
    <TransitionGroup v-else name="order-list" tag="ul" class="space-y-2">
      <li v-for="o in orders" :key="o.id" class="order-card bg-slate-800 rounded p-3">
        <div class="flex justify-between items-start gap-3">
          <div class="flex-1">
            <div class="flex items-center gap-2">
              <span class="text-sm text-slate-400 uppercase">{{ o.service_category?.slug ?? o.service_category?.name }}</span>
              <span v-if="o.distance_km" class="text-xs bg-slate-700 text-amber-300 rounded px-2 py-0.5">
                {{ o.distance_km }} km
              </span>
            </div>
            <div class="font-semibold text-lg mt-1">Rp {{ o.current_price.toLocaleString('id-ID') }}</div>
            <div v-if="o.details?.pickup_address" class="text-xs text-slate-500 mt-1">
              📍 {{ o.details.pickup_address }}
            </div>
          </div>
          <button
            @click="claim(o.id)"
            :disabled="claiming === o.id"
            class="bg-amber-400 text-slate-900 rounded px-4 py-2 font-semibold disabled:opacity-50"
          >{{ claiming === o.id ? '…' : 'Terima' }}</button>
        </div>
      </li>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.order-card {
  transition: opacity 0.3s ease, transform 0.3s ease;
}
.order-list-enter-from {
  opacity: 0;
  transform: translateY(-8px);
}
.order-list-leave-to {
  opacity: 0;
  transform: translateX(20px);
}
.order-list-leave-active {
  position: absolute;
  width: 100%;
}
</style>
