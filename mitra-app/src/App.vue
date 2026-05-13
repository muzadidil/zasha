<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';
import api from './api';

const auth = useAuthStore();
const router = useRouter();
const userName = computed(() => auth.user?.name ?? null);
const isOnline = ref(false);
const updatingOnline = ref(false);

async function handleLogout() {
  await auth.logout();
  router.push({ name: 'login' });
}

async function toggleOnline() {
  updatingOnline.value = true;
  try {
    const next = !isOnline.value;
    await api.patch('/partner/online-status', { is_online: next });
    isOnline.value = next;
  } catch (_) {
    // surfaced by the location view when no coords yet
  } finally {
    updatingOnline.value = false;
  }
}

async function pushLocation() {
  if (!navigator.geolocation) return;
  navigator.geolocation.getCurrentPosition(async (pos) => {
    try {
      await api.patch('/partner/location', {
        lat: pos.coords.latitude,
        lng: pos.coords.longitude,
        accuracy_meters: Math.round(pos.coords.accuracy ?? 0),
      });
    } catch (_) {}
  });
}

onMounted(() => {
  if (auth.token) {
    auth.fetchMe();
    pushLocation();
    setInterval(pushLocation, 30000);
  }
});
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <header class="bg-slate-800 border-b border-slate-700">
      <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
        <RouterLink to="/" class="font-semibold text-lg text-amber-400">Zasha Mitra</RouterLink>
        <nav class="text-sm space-x-3 flex items-center">
          <template v-if="auth.token">
            <RouterLink to="/available" class="text-slate-300 hover:text-amber-400">Order</RouterLink>
            <RouterLink to="/wallet" class="text-slate-300 hover:text-amber-400">Saldo</RouterLink>
            <RouterLink to="/profile" class="text-slate-300 hover:text-amber-400">Profil</RouterLink>
            <button
              @click="toggleOnline"
              :disabled="updatingOnline"
              :class="['ml-2 rounded px-2 py-1 text-xs', isOnline ? 'bg-emerald-500 text-slate-900' : 'bg-slate-600 text-slate-200']"
            >{{ isOnline ? 'Online' : 'Offline' }}</button>
            <span class="text-slate-500 text-xs ml-2">{{ userName }}</span>
            <button @click="handleLogout" class="text-rose-400 hover:underline ml-2">Keluar</button>
          </template>
          <template v-else>
            <RouterLink to="/login" class="text-slate-300 hover:text-amber-400">Masuk</RouterLink>
            <RouterLink to="/register" class="text-slate-300 hover:text-amber-400">Daftar</RouterLink>
          </template>
        </nav>
      </div>
    </header>
    <main class="flex-1 max-w-3xl w-full mx-auto px-4 py-6">
      <RouterView />
    </main>
  </div>
</template>
