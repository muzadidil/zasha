<script setup>
import { computed, markRaw, onMounted } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import { BoltIcon, ClipboardDocumentCheckIcon, UserIcon, WalletIcon } from '@heroicons/vue/24/outline';
import api from './api';
import BottomTab from './components/ui/BottomTab.vue';
import Toast from './components/ui/Toast.vue';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
const route = useRoute();

const HIDDEN_ON = new Set(['login', 'register']);
const showTabs = computed(() => auth.token && !HIDDEN_ON.has(route.name));

const tabs = [
  { name: 'available', label: 'Order Masuk', route: 'available', icon: markRaw(BoltIcon) },
  { name: 'in-progress', label: 'Dikerjakan', route: 'in-progress', icon: markRaw(ClipboardDocumentCheckIcon) },
  { name: 'wallet', label: 'Wallet', route: 'wallet', icon: markRaw(WalletIcon) },
  { name: 'profile', label: 'Profil', route: 'profile', icon: markRaw(UserIcon) },
];

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
  <div class="min-h-full bg-slate-50">
    <main :class="['min-h-screen', showTabs ? 'pb-20' : '']">
      <RouterView v-slot="{ Component }">
        <transition name="page" mode="out-in">
          <component :is="Component" />
        </transition>
      </RouterView>
    </main>
    <BottomTab v-if="showTabs" :items="tabs" />
    <Toast />
  </div>
</template>

<style>
.page-enter-active, .page-leave-active {
  transition: opacity 180ms ease, transform 200ms ease;
}
.page-enter-from { opacity: 0; transform: translateY(4px); }
.page-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
