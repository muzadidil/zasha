<script setup>
defineProps({
  padded: { type: Boolean, default: true },
  hoverable: { type: Boolean, default: false },
});
</script>

<template>
  <div
    :class="[
      'bg-white rounded-card shadow-card border border-slate-100',
      padded ? 'p-4' : '',
      hoverable ? 'transition-all duration-200 hover:shadow-elevated hover:-translate-y-0.5 cursor-pointer' : '',
    ]"
  >
    <slot />
  </div>
</template>
