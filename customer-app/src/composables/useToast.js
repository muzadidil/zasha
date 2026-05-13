import { reactive } from 'vue';

const toasts = reactive([]);
let nextId = 1;

function dismiss(id) {
  const idx = toasts.findIndex((t) => t.id === id);
  if (idx !== -1) toasts.splice(idx, 1);
}

function push(message, variant = 'info', timeoutMs = 3000) {
  const id = nextId++;
  toasts.push({ id, message, variant });
  if (timeoutMs > 0) setTimeout(() => dismiss(id), timeoutMs);
  return id;
}

export function useToast() {
  return {
    toasts,
    dismiss,
    success: (msg, ms) => push(msg, 'success', ms),
    error: (msg, ms) => push(msg, 'error', ms),
    warning: (msg, ms) => push(msg, 'warning', ms),
    info: (msg, ms) => push(msg, 'info', ms),
  };
}
