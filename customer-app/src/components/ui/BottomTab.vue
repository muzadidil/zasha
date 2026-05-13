<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const props = defineProps({
  items: { type: Array, required: true }, // [{ name, label, icon, route, badge? }]
});

const route = useRoute();
const router = useRouter();

const activeName = computed(() => {
  // Match by route name; falls back to the first item whose route is a prefix
  // of the current path so detail pages stay highlighted under their parent.
  const exact = props.items.find((it) => it.route === route.name || it.name === route.name);
  if (exact) return exact.name;
  const prefix = props.items.find((it) => route.path.startsWith(it.path ?? ''));
  return prefix?.name;
});

function go(item) {
  if (route.name === item.route) return;
  router.push({ name: item.route });
}
</script>

<template>
  <nav
    class="fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200"
    style="padding-bottom: var(--safe-bottom)"
  >
    <ul class="max-w-md mx-auto sm:max-w-2xl flex justify-around items-stretch h-16">
      <li v-for="item in items" :key="item.name" class="flex-1">
        <button
          @click="go(item)"
          :class="[
            'group w-full h-full flex flex-col items-center justify-center gap-0.5 transition-all duration-200',
            activeName === item.name ? 'text-brand-600' : 'text-ink-soft hover:text-ink',
          ]"
        >
          <span class="relative">
            <component :is="item.icon" class="w-6 h-6" />
            <span
              v-if="item.badge"
              class="absolute -top-1 -right-2 min-w-[18px] h-[18px] px-1 text-[10px] font-semibold rounded-full bg-rose-500 text-white grid place-items-center"
            >{{ item.badge > 9 ? '9+' : item.badge }}</span>
          </span>
          <span class="text-[11px] font-medium">{{ item.label }}</span>
          <span
            :class="[
              'absolute top-0 h-[3px] rounded-b-full bg-brand-600 transition-all duration-200',
              activeName === item.name ? 'w-10 opacity-100' : 'w-0 opacity-0',
            ]"
          />
        </button>
      </li>
    </ul>
  </nav>
</template>
