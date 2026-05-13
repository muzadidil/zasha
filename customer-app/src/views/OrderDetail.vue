<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api';
import { createEcho, getEcho } from '../echo';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const order = ref(null);
const error = ref('');
const acting = ref(false);
const rating = reactive({ stars: 5, comment: '', submitted: false, error: '' });
let channel = null;

const canRate = computed(() => order.value?.status === 'completed' && !rating.submitted);
const showActions = computed(() => order.value?.status === 'searching');

async function load() {
  try {
    const { data } = await api.get(`/customer/orders/${route.params.id}`);
    order.value = data.data;
  } catch (e) {
    error.value = e.response?.data?.error?.message ?? 'Gagal memuat detail order.';
  }
}

async function increasePrice() {
  acting.value = true;
  try {
    const { data } = await api.patch(`/customer/orders/${route.params.id}/increase-price`);
    order.value = data.data;
  } catch (e) {
    error.value = e.response?.data?.error?.message ?? 'Gagal menaikkan harga.';
  } finally {
    acting.value = false;
  }
}

async function cancel() {
  acting.value = true;
  try {
    const { data } = await api.delete(`/customer/orders/${route.params.id}`);
    order.value = data.data;
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

onMounted(async () => {
  await load();
  if (!auth.token || !order.value) return;
  const echo = getEcho() ?? createEcho(auth.token);
  channel = echo.channel(`order.${route.params.id}`);
  channel.listen('.OrderClaimed', () => load());
  channel.listen('.OrderPriceUpdated', (e) => { if (order.value) order.value.current_price = e.current_price; });
  channel.listen('.OrderStatusChanged', () => load());
});

onUnmounted(() => {
  if (channel) channel.stopListening('.OrderClaimed');
});
</script>

<template>
  <div v-if="order" class="space-y-3">
    <button class="text-sm text-slate-500" @click="router.back()">← Kembali</button>
    <h1 class="text-xl font-semibold">Order {{ order.id.slice(-6) }}</h1>
    <div class="bg-white rounded shadow p-4 space-y-2">
      <div><span class="text-slate-500 text-sm">Kategori:</span> {{ order.service_category?.name }}</div>
      <div><span class="text-slate-500 text-sm">Status:</span> {{ order.status }}</div>
      <div><span class="text-slate-500 text-sm">Harga sekarang:</span> Rp {{ order.current_price.toLocaleString('id-ID') }}</div>
    </div>

    <div v-if="showActions" class="flex gap-2">
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
