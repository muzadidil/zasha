<script setup>
defineProps({
  title: { type: String, default: 'Belum ada apa-apa di sini' },
  description: { type: String, default: '' },
});
</script>

<template>
  <div class="flex flex-col items-center justify-center text-center py-12 px-6">
    <div class="w-24 h-24 mb-4 rounded-full bg-slate-100 grid place-items-center text-slate-400">
      <slot name="icon">
        <svg viewBox="0 0 64 64" fill="none" class="w-12 h-12">
          <circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="2" stroke-dasharray="4 4" />
          <path d="M22 36c2 3 5 5 10 5s8-2 10-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          <circle cx="24" cy="26" r="2" fill="currentColor" />
          <circle cx="40" cy="26" r="2" fill="currentColor" />
        </svg>
      </slot>
    </div>
    <h3 class="text-base font-semibold text-ink">{{ title }}</h3>
    <p v-if="description" class="text-sm text-ink-soft mt-1 max-w-xs">{{ description }}</p>
    <div class="mt-4">
      <slot />
    </div>
  </div>
</template>
