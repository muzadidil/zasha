<script setup>
import { TransitionRoot } from '@headlessui/vue';
import { CheckCircleIcon, ExclamationTriangleIcon, InformationCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';
import { useToast } from '../../composables/useToast';

const { toasts, dismiss } = useToast();

const iconFor = (variant) => ({
  success: CheckCircleIcon,
  error: XCircleIcon,
  warning: ExclamationTriangleIcon,
  info: InformationCircleIcon,
}[variant] ?? InformationCircleIcon);

const colorFor = computed(() => (variant) => ({
  success: 'text-emerald-600 bg-emerald-50 border-emerald-200',
  error: 'text-rose-600 bg-rose-50 border-rose-200',
  warning: 'text-amber-600 bg-amber-50 border-amber-200',
  info: 'text-brand-600 bg-brand-50 border-brand-200',
}[variant] ?? 'text-ink bg-white border-slate-200'));
</script>

<template>
  <div
    class="fixed left-1/2 -translate-x-1/2 top-4 sm:left-auto sm:right-4 sm:translate-x-0 z-[60] w-[92%] sm:w-auto max-w-sm space-y-2 pointer-events-none"
    style="padding-top: var(--safe-top)"
  >
    <TransitionRoot
      v-for="t in toasts"
      :key="t.id"
      :show="true"
      appear
      enter="transition transform duration-200 ease-out"
      enter-from="-translate-y-2 opacity-0"
      enter-to="translate-y-0 opacity-100"
      leave="transition duration-150 ease-in"
      leave-from="opacity-100"
      leave-to="opacity-0"
      as="template"
    >
      <div
        :class="['flex items-start gap-3 p-3 rounded-card border shadow-card backdrop-blur-md pointer-events-auto', colorFor(t.variant)]"
      >
        <component :is="iconFor(t.variant)" class="w-5 h-5 mt-0.5 flex-shrink-0" />
        <div class="flex-1 text-sm">{{ t.message }}</div>
        <button @click="dismiss(t.id)" class="text-current opacity-60 hover:opacity-100 transition-opacity text-xs">×</button>
      </div>
    </TransitionRoot>
  </div>
</template>
