<script setup>
import { computed } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
const router = useRouter();
const userName = computed(() => auth.user?.name ?? null);

async function handleLogout() {
  await auth.logout();
  router.push({ name: 'login' });
}
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <header class="bg-white shadow-sm">
      <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
        <RouterLink to="/" class="font-semibold text-lg text-indigo-700">Zasha Pelanggan</RouterLink>
        <nav class="text-sm space-x-3">
          <template v-if="auth.token">
            <RouterLink to="/orders" class="text-slate-600 hover:text-indigo-700">Order Saya</RouterLink>
            <RouterLink to="/orders/new" class="text-slate-600 hover:text-indigo-700">Pesan Jasa</RouterLink>
            <span class="text-slate-400">|</span>
            <span class="text-slate-500">{{ userName }}</span>
            <button @click="handleLogout" class="text-rose-600 hover:underline">Keluar</button>
          </template>
          <template v-else>
            <RouterLink to="/login" class="text-slate-600 hover:text-indigo-700">Masuk</RouterLink>
            <RouterLink to="/register" class="text-slate-600 hover:text-indigo-700">Daftar</RouterLink>
          </template>
        </nav>
      </div>
    </header>
    <main class="flex-1 max-w-3xl w-full mx-auto px-4 py-6">
      <RouterView />
    </main>
  </div>
</template>
