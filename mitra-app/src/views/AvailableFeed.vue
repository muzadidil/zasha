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
let channels = [];

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

async function claim(orderId) {
  claiming.value = orderId;
  error.value = '';
  try {
    await api.post(`/partner/orders/${orderId}/claim`);
    orders.value = orders.value.filter((o) => o.id !== orderId);
  } catch (e) {
    error.value = e.response?.data?.error?.message ?? 'Gagal mengambil order.';
  } finally {
    claiming.value = null;
  }
}

onMounted(async () => {
  await load();
  if (!auth.token) return;
  const echo = getEcho() ?? createEcho(auth.token);
  ['wfh', 'titip', 'tenaga', 'service'].forEach((slug) => {
    const ch = echo.channel(`partners.${slug}`);
    ch.listen('.OrderCreated', () => load());
    ch.listen('.OrderClaimed', (e) => {
      orders.value = orders.value.filter((o) => o.id !== e.order_id);
    });
    ch.listen('.OrderPriceUpdated', (e) => {
      const order = orders.value.find((o) => o.id === e.order_id);
      if (order) order.current_price = e.current_price;
    });
    channels.push(ch);
  });
});

onUnmounted(() => channels = []);
</script>

<template>
  <div class="space-y-3">
    <h1 class="text-xl font-semibold text-amber-400">Order Tersedia</h1>
    <p v-if="error" class="text-rose-400 text-sm">{{ error }}</p>
    <p v-if="loading" class="text-slate-400">Memuat…</p>
    <p v-else-if="!orders.length" class="text-slate-400">Belum ada order di area kamu.</p>
    <ul v-else class="space-y-2">
      <li v-for="o in orders" :key="o.id" class="bg-slate-800 rounded p-3">
        <div class="flex justify-between">
          <div>
            <div class="text-sm text-slate-400">{{ o.service_category?.name }}</div>
            <div class="font-semibold">Rp {{ o.current_price.toLocaleString('id-ID') }}</div>
          </div>
          <button
            @click="claim(o.id)"
            :disabled="claiming === o.id"
            class="bg-amber-400 text-slate-900 rounded px-4 py-2 font-semibold disabled:opacity-50"
          >{{ claiming === o.id ? '…' : 'Terima' }}</button>
        </div>
      </li>
    </ul>
  </div>
</template>
