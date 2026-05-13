<script setup>
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';

defineProps({
  open: { type: Boolean, required: true },
  title: { type: String, default: '' },
  closable: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);
function onClose() {
  emit('close');
}
</script>

<template>
  <TransitionRoot appear :show="open" as="template">
    <Dialog as="div" class="relative z-50" @close="closable ? onClose() : null">
      <TransitionChild
        as="template"
        enter="duration-200 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-150 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" />
      </TransitionChild>

      <div class="fixed inset-0 flex items-end sm:items-center justify-center">
        <TransitionChild
          as="template"
          enter="duration-220 ease-out"
          enter-from="opacity-0 translate-y-6 sm:scale-95"
          enter-to="opacity-100 translate-y-0 sm:scale-100"
          leave="duration-150 ease-in"
          leave-from="opacity-100 translate-y-0 sm:scale-100"
          leave-to="opacity-0 translate-y-6 sm:scale-95"
        >
          <DialogPanel
            class="w-full sm:w-auto sm:max-w-md bg-white rounded-t-2xl sm:rounded-2xl shadow-elevated"
            style="padding-bottom: calc(0px + env(safe-area-inset-bottom))"
          >
            <div v-if="title" class="px-5 pt-5 pb-2">
              <DialogTitle class="text-lg font-semibold text-ink">{{ title }}</DialogTitle>
            </div>
            <div class="px-5 pb-5 pt-2">
              <slot />
            </div>
          </DialogPanel>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
