<script setup>
import { computed, ref, useId } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  label: { type: String, default: '' },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: ' ' }, // space keeps :placeholder-shown working for floating label
  helper: { type: String, default: '' },
  error: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  autocomplete: { type: String, default: 'off' },
});

const emit = defineEmits(['update:modelValue']);
const id = useId();
const focused = ref(false);

const hasError = computed(() => !!props.error);

function onInput(e) {
  emit('update:modelValue', e.target.value);
}
</script>

<template>
  <div class="space-y-1">
    <div class="relative">
      <input
        :id="id"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :autocomplete="autocomplete"
        @input="onInput"
        @focus="focused = true"
        @blur="focused = false"
        :class="[
          'peer block w-full h-12 px-3.5 pt-4 pb-1 rounded-btn border bg-white text-ink text-sm placeholder-transparent',
          'transition-all duration-200',
          'focus:outline-none focus:ring-2 focus:ring-offset-0',
          hasError
            ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200'
            : 'border-slate-300 focus:border-brand-500 focus:ring-brand-100',
          disabled ? 'bg-slate-50 text-slate-500 cursor-not-allowed' : '',
        ]"
      />
      <label
        v-if="label"
        :for="id"
        :class="[
          'absolute left-3.5 top-3.5 text-sm pointer-events-none transition-all duration-200',
          'peer-focus:top-1.5 peer-focus:text-[10px] peer-focus:font-semibold',
          'peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[10px] peer-[:not(:placeholder-shown)]:font-semibold',
          hasError ? 'text-rose-500 peer-focus:text-rose-600' : 'text-ink-soft peer-focus:text-brand-600',
        ]"
      >{{ label }}<span v-if="required" class="text-rose-500">*</span></label>
    </div>
    <p v-if="error" class="text-xs text-rose-600 px-1">{{ error }}</p>
    <p v-else-if="helper" class="text-xs text-ink-soft px-1">{{ helper }}</p>
  </div>
</template>
