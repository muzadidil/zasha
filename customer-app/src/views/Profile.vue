<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import {
  ArrowRightOnRectangleIcon,
  ChevronRightIcon,
  ClipboardDocumentListIcon,
  CogIcon,
  ShieldCheckIcon,
  StarIcon,
} from '@heroicons/vue/24/outline';
import AppHeader from '../components/ui/AppHeader.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import { useToast } from '../composables/useToast';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const toast = useToast();

const initial = computed(() => (auth.user?.name ?? '?').charAt(0).toUpperCase());

async function handleLogout() {
  await auth.logout();
  toast.info('Sampai jumpa lagi 👋');
  router.push({ name: 'login' });
}

const menus = [
  { icon: ClipboardDocumentListIcon, label: 'Riwayat order', to: 'orders.index' },
  { icon: ShieldCheckIcon, label: 'Privasi & keamanan', to: null },
  { icon: CogIcon, label: 'Pengaturan', to: null },
];
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <AppHeader title="Profil" />

    <div class="max-w-md mx-auto sm:max-w-2xl px-5 py-4 space-y-4">
      <!-- User card -->
      <BaseCard padded>
        <div class="flex items-center gap-4">
          <div class="w-16 h-16 rounded-full bg-gradient-to-br from-brand-500 to-violet-500 grid place-items-center text-white font-bold text-2xl">
            {{ initial }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-bold text-lg text-ink truncate">{{ auth.user?.name ?? '—' }}</div>
            <div class="text-sm text-ink-soft truncate">{{ auth.user?.phone ?? '—' }}</div>
            <div v-if="auth.user?.email" class="text-xs text-ink-soft truncate">{{ auth.user.email }}</div>
          </div>
        </div>

        <div v-if="auth.user?.average_rating !== null" class="mt-4 grid grid-cols-2 gap-3">
          <div class="bg-amber-50 border border-amber-100 rounded-card p-3 text-center">
            <div class="flex items-center justify-center gap-1 text-amber-600">
              <StarIcon class="w-4 h-4" />
              <span class="font-bold text-base">{{ auth.user?.average_rating ?? '—' }}</span>
            </div>
            <div class="text-[10px] uppercase text-ink-soft font-semibold mt-0.5">Rating Anda</div>
          </div>
          <div class="bg-brand-50 border border-brand-100 rounded-card p-3 text-center">
            <div class="font-bold text-base text-brand-700">{{ auth.user?.rating_count ?? 0 }}</div>
            <div class="text-[10px] uppercase text-ink-soft font-semibold mt-0.5">Total ulasan</div>
          </div>
        </div>
      </BaseCard>

      <!-- Menu list -->
      <BaseCard :padded="false">
        <ul class="divide-y divide-slate-100">
          <li v-for="m in menus" :key="m.label">
            <button
              @click="m.to ? router.push({ name: m.to }) : toast.info('Segera tersedia')"
              class="w-full flex items-center gap-3 px-4 py-3.5 text-left hover:bg-slate-50 transition-colors"
            >
              <component :is="m.icon" class="w-5 h-5 text-ink-soft" />
              <span class="flex-1 text-sm text-ink">{{ m.label }}</span>
              <ChevronRightIcon class="w-4 h-4 text-ink-soft" />
            </button>
          </li>
        </ul>
      </BaseCard>

      <!-- Logout -->
      <button
        @click="handleLogout"
        class="w-full flex items-center justify-center gap-2 h-12 rounded-btn border border-rose-200 text-rose-600 font-semibold text-sm hover:bg-rose-50 transition-colors"
      >
        <ArrowRightOnRectangleIcon class="w-5 h-5" />
        Keluar
      </button>

      <p class="text-center text-xs text-ink-soft pt-2">Zasha · v2.0</p>
    </div>
  </div>
</template>
