<script setup>
import { useRouter } from 'vue-router';
import { ChevronLeftIcon } from '@heroicons/vue/24/outline';

defineProps({
  title: { type: String, default: '' },
  back: { type: Boolean, default: false },
});

const router = useRouter();
function goBack() {
  if (window.history.length > 1) router.back();
  else router.push('/');
}
</script>

<template>
  <header
    class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-slate-200"
    style="padding-top: var(--safe-top)"
  >
    <div class="h-14 flex items-center px-4 max-w-md mx-auto sm:max-w-2xl">
      <button
        v-if="back"
        @click="goBack"
        class="w-11 h-11 -ml-2 flex items-center justify-center rounded-full hover:bg-slate-100 active:bg-slate-200 transition-colors"
        aria-label="Kembali"
      >
        <ChevronLeftIcon class="w-6 h-6 text-ink" />
      </button>
      <h1 class="flex-1 text-center text-base font-semibold text-ink truncate px-2">
        <slot name="title">{{ title }}</slot>
      </h1>
      <div class="w-11 h-11 flex items-center justify-end">
        <slot name="action" />
      </div>
    </div>
  </header>
</template>
