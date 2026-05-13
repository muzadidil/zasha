<script setup>
import { computed, markRaw } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import { HomeIcon, ListBulletIcon, UserIcon, BoltIcon } from '@heroicons/vue/24/outline';
import BottomTab from './components/ui/BottomTab.vue';
import Toast from './components/ui/Toast.vue';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
const route = useRoute();

// Bottom-tab is hidden on auth pages + full-screen detail/searching screens.
const HIDDEN_ON = new Set(['login', 'register', 'orders.show', 'orders.create']);
const showTabs = computed(() => auth.token && !HIDDEN_ON.has(route.name));

const tabs = [
  { name: 'home', label: 'Beranda', route: 'home', icon: markRaw(HomeIcon) },
  { name: 'active', label: 'Order Aktif', route: 'orders.active', icon: markRaw(BoltIcon) },
  { name: 'history', label: 'Riwayat', route: 'orders.index', icon: markRaw(ListBulletIcon) },
  { name: 'profile', label: 'Profil', route: 'profile', icon: markRaw(UserIcon) },
];
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
.page-enter-from {
  opacity: 0;
  transform: translateY(4px);
}
.page-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
