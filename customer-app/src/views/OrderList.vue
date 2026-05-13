<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../api';

const orders = ref([]);
const loading = ref(true);
const error = ref('');

onMounted(async () => {
  try {
    const { data } = await api.get('/customer/orders');
    orders.value = data.data;
  } catch (e) {
    error.value = e.response?.data?.error?.message ?? 'Gagal memuat order.';
  } finally {
    loading.value = false;
  }
});

const labels = {
  draft: 'Draft', searching: 'Mencari mitra', claimed: 'Diterima mitra',
  in_progress: 'Sedang dikerjakan', completed: 'Selesai',
  cancelled: 'Dibatalkan', expired: 'Kedaluwarsa',
};
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-semibold">Order Saya</h1>
      <RouterLink to="/orders/new" class="bg-indigo-600 text-white rounded px-3 py-2 text-sm">+ Pesan Jasa</RouterLink>
    </div>
    <p v-if="loading" class="text-slate-500">Memuat…</p>
    <p v-else-if="error" class="text-rose-600">{{ error }}</p>
    <p v-else-if="!orders.length" class="text-slate-500">Belum ada order.</p>
    <ul v-else class="space-y-2">
      <li v-for="o in orders" :key="o.id" class="bg-white rounded shadow-sm p-3">
        <RouterLink :to="`/orders/${o.id}`" class="block">
          <div class="flex items-center justify-between">
            <span class="font-medium">{{ o.service_category?.name ?? '-' }}</span>
            <span class="text-xs bg-slate-100 rounded px-2 py-0.5">{{ labels[o.status] ?? o.status }}</span>
          </div>
          <div class="text-sm text-slate-500 mt-1">Rp {{ o.current_price.toLocaleString('id-ID') }}</div>
        </RouterLink>
      </li>
    </ul>
  </div>
</template>
