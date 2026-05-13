<script setup>
defineProps({
  label: { type: String, default: '' },
  fullscreen: { type: Boolean, default: false },
});
</script>

<template>
  <div
    :class="[
      'flex flex-col items-center justify-center gap-3 text-ink-soft',
      fullscreen ? 'min-h-[60vh]' : 'py-8',
    ]"
  >
    <span class="inline-block w-7 h-7 border-[3px] border-brand-100 border-t-brand-600 rounded-full animate-spin" />
    <span v-if="label" class="text-sm">{{ label }}</span>
  </div>
</template>
