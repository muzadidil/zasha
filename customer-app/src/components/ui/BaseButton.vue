<script setup>
import { computed } from 'vue';

const props = defineProps({
  variant: { type: String, default: 'primary' }, // primary | secondary | ghost | danger
  size: { type: String, default: 'md' }, // sm | md | lg
  type: { type: String, default: 'button' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  block: { type: Boolean, default: false },
});

const variantClass = computed(() => ({
  primary: 'bg-brand-600 text-white hover:bg-brand-700 active:bg-brand-700 disabled:bg-slate-200 disabled:text-slate-400',
  secondary: 'border border-slate-300 text-ink hover:bg-slate-50 active:bg-slate-100 disabled:opacity-60',
  ghost: 'text-brand-600 hover:bg-brand-50 active:bg-brand-100 disabled:opacity-50',
  danger: 'bg-rose-600 text-white hover:bg-rose-700 active:bg-rose-700 disabled:bg-slate-200 disabled:text-slate-400',
}[props.variant]));

const sizeClass = computed(() => ({
  sm: 'h-10 px-4 text-sm',
  md: 'h-12 px-5 text-sm',
  lg: 'h-14 px-6 text-base',
}[props.size]));
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[
      'inline-flex items-center justify-center gap-2 rounded-btn font-semibold transition-all duration-200',
      'focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2',
      variantClass,
      sizeClass,
      block ? 'w-full' : '',
    ]"
  >
    <span v-if="loading" class="inline-block w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin" />
    <slot v-else />
  </button>
</template>
