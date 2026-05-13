<script setup>
import { computed } from 'vue';
import {
  ComputerDesktopIcon,
  ShoppingBagIcon,
  WrenchScrewdriverIcon,
  BoltIcon,
} from '@heroicons/vue/24/solid';

const props = defineProps({
  slug: { type: String, required: true },
  size: { type: String, default: 'md' }, // sm | md | lg
});

const iconMap = {
  wfh: ComputerDesktopIcon,
  titip: ShoppingBagIcon,
  tenaga: BoltIcon,
  service: WrenchScrewdriverIcon,
};

const colorMap = {
  wfh: 'bg-violet-100 text-violet-600',
  titip: 'bg-amber-100 text-amber-600',
  tenaga: 'bg-emerald-100 text-emerald-600',
  service: 'bg-rose-100 text-rose-600',
};

const sizeMap = {
  sm: { box: 'w-9 h-9', icon: 'w-5 h-5' },
  md: { box: 'w-12 h-12', icon: 'w-6 h-6' },
  lg: { box: 'w-16 h-16', icon: 'w-8 h-8' },
};

const Icon = computed(() => iconMap[props.slug] ?? ShoppingBagIcon);
const color = computed(() => colorMap[props.slug] ?? 'bg-slate-100 text-slate-600');
const size = computed(() => sizeMap[props.size]);
</script>

<template>
  <span :class="['rounded-xl grid place-items-center flex-shrink-0', color, size.box]">
    <component :is="Icon" :class="size.icon" />
  </span>
</template>
