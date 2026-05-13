<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { CheckBadgeIcon, MapPinIcon, StarIcon } from '@heroicons/vue/24/solid';
import api from '../api';
import { createEcho, getEcho } from '../echo';
import AppHeader from '../components/ui/AppHeader.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import CategoryIcon from '../components/ui/CategoryIcon.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import LoadingSpinner from '../components/ui/LoadingSpinner.vue';
import { useToast } from '../composables/useToast';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const toast = useToast();
const orders = ref([]);
const loading = ref(true);
const claiming = ref(null);
const isOnline = ref(false);
const updatingOnline = ref(false);
let privateChannel = null;
let orderChannels = new Map();
let pollTimer = null;

let pingAudio = null;
function playPing() {
  try {
    if (!pingAudio) {
      pingAudio = new Audio('data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=');
    }
    pingAudio.play().catch(() => {});
  } catch (_) { /* ignore */ }
}

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/partner/orders/available');
    orders.value = data.data;
  } finally {
    loading.value = false;
  }
}

async function reconcile() {
  try {
    const { data } = await api.get('/partner/orders/available');
    const fresh = data.data ?? [];
    const freshIds = new Set(fresh.map((o) => o.id));
    orders.value = orders.value.filter((o) => freshIds.has(o.id));
    fresh.forEach((entry) => {
      const idx = orders.value.findIndex((o) => o.id === entry.id);
      if (idx === -1) orders.value.push(entry);
      else orders.value[idx] = { ...orders.value[idx], ...entry };
    });
  } catch (_) { /* swallow */ }
}

function upsertOrder(entry) {
  const idx = orders.value.findIndex((o) => o.id === entry.id);
  if (idx === -1) orders.value.unshift(entry);
  else orders.value[idx] = { ...orders.value[idx], ...entry };
}

function addressFor(o) {
  const slug = o.service_category?.slug;
  const d = o.details ?? {};
  switch (slug) {
    case 'titip': return [d.pickup_address, d.dropoff_address].filter(Boolean).join(' → ');
    case 'tenaga': return d.work_address ?? '';
    case 'service': return d.location_type === 'on_site' ? (d.service_address ?? '') : '(diantar ke tempat mitra)';
    case 'wfh': return '(remote)';
    default: return '';
  }
}

function subscribeToOrder(echo, orderId) {
  if (orderChannels.has(orderId)) return;
  const ch = echo.channel(`order.${orderId}`);
  const drop = () => { orders.value = orders.value.filter((o) => o.id !== orderId); orderChannels.delete(orderId); };
  ch.listen('.OrderClaimed', drop);
  ch.listen('.OrderCancelled', drop);
  ch.listen('.OrderExpired', drop);
  ch.listen('.OrderPriceUpdated', (e) => {
    const idx = orders.value.findIndex((o) => o.id === e.order_id);
    if (idx === -1) return;
    orders.value[idx] = { ...orders.value[idx], current_price: e.current_price };
  });
  orderChannels.set(orderId, ch);
}

async function claim(orderId) {
  claiming.value = orderId;
  try {
    await api.post(`/partner/orders/${orderId}/claim`);
    orders.value = orders.value.filter((o) => o.id !== orderId);
    toast.success('Order berhasil diambil!');
  } catch (e) {
    toast.error(e.response?.data?.error?.message ?? 'Order sudah diambil mitra lain');
    orders.value = orders.value.filter((o) => o.id !== orderId);
  } finally {
    claiming.value = null;
  }
}

async function toggleOnline() {
  updatingOnline.value = true;
  try {
    const next = !isOnline.value;
    await api.patch('/partner/online-status', { is_online: next });
    isOnline.value = next;
    toast.success(next ? 'Status: Online — siap terima order' : 'Status: Offline');
  } catch (e) {
    toast.error(e.response?.data?.error?.message ?? 'Gagal ubah status');
  } finally {
    updatingOnline.value = false;
  }
}

onMounted(async () => {
  await load();
  if (!auth.token || !auth.user?.id) return;

  const echo = getEcho() ?? createEcho(auth.token);
  privateChannel = echo.private(`partner.${auth.user.id}`);
  privateChannel.listen('.OrderAvailableForPartner', (e) => {
    upsertOrder({
      id: e.order_id,
      service_category: { slug: e.category },
      current_price: e.current_price,
      details: e.details,
      pickup: e.pickup,
      distance_km: e.distance_km,
      expires_at: e.expires_at,
      customer: e.customer ?? null,
    });
    subscribeToOrder(echo, e.order_id);
    playPing();
  });

  orders.value.forEach((o) => subscribeToOrder(echo, o.id));
  pollTimer = setInterval(reconcile, 5_000);
});

onUnmounted(() => {
  privateChannel = null;
  orderChannels.clear();
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
});

const onlineLabel = computed(() => (isOnline.value ? 'Online' : 'Offline'));
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <AppHeader title="Order Masuk">
      <template #action>
        <button
          @click="toggleOnline"
          :disabled="updatingOnline"
          :class="[
            'inline-flex items-center gap-1.5 h-9 px-3 rounded-full text-xs font-semibold transition-colors',
            isOnline ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600',
          ]"
        >
          <span :class="['w-2 h-2 rounded-full', isOnline ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400']" />
          {{ onlineLabel }}
        </button>
      </template>
    </AppHeader>

    <div class="max-w-md mx-auto sm:max-w-2xl px-5 py-4">
      <!-- Counter -->
      <div class="text-xs text-ink-soft mb-3">
        <span class="font-semibold text-ink">{{ orders.length }}</span> order tersedia di area kamu
      </div>

      <LoadingSpinner v-if="loading" />

      <EmptyState
        v-else-if="!orders.length"
        title="Belum ada order masuk"
        description="Tetap online dan dekati area ramai. Order baru akan muncul di sini secara otomatis."
      />

      <TransitionGroup v-else name="order-list" tag="ul" class="space-y-3 relative">
        <li v-for="o in orders" :key="o.id" class="order-card">
          <BaseCard padded>
            <div class="flex items-start gap-3">
              <CategoryIcon :slug="o.service_category?.slug ?? 'titip'" size="md" />

              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-semibold text-ink-soft uppercase">{{ o.service_category?.slug ?? o.service_category?.name }}</span>
                  <span v-if="o.distance_km" class="inline-flex items-center gap-1 text-[10px] font-semibold bg-brand-50 text-brand-700 rounded-full px-2 py-0.5">
                    <MapPinIcon class="w-3 h-3" />
                    {{ o.distance_km }} km
                  </span>
                </div>

                <div class="text-xl font-bold text-ink mt-1">Rp {{ o.current_price.toLocaleString('id-ID') }}</div>

                <div v-if="o.customer" class="mt-2 flex items-center gap-1.5 text-xs">
                  <div class="w-6 h-6 rounded-full bg-slate-200 grid place-items-center text-[10px] font-bold text-slate-600">
                    {{ (o.customer.name ?? '?').charAt(0).toUpperCase() }}
                  </div>
                  <span class="font-medium text-ink truncate">{{ o.customer.name }}</span>
                  <span v-if="o.customer.average_rating !== null" class="flex items-center gap-0.5 text-amber-600 font-semibold">
                    <StarIcon class="w-3 h-3" />
                    {{ o.customer.average_rating.toFixed(1) }}
                    <span class="text-ink-soft font-normal">({{ o.customer.rating_count }})</span>
                  </span>
                  <span v-else class="text-ink-soft">baru</span>
                </div>

                <div v-if="addressFor(o)" class="mt-2 text-xs text-ink-muted flex items-start gap-1">
                  <MapPinIcon class="w-3.5 h-3.5 mt-0.5 flex-shrink-0 text-rose-400" />
                  <span class="line-clamp-2">{{ addressFor(o) }}</span>
                </div>
              </div>
            </div>

            <div class="mt-3 pt-3 border-t border-slate-100">
              <BaseButton
                block
                size="md"
                :loading="claiming === o.id"
                @click="claim(o.id)"
              >
                <CheckBadgeIcon class="w-5 h-5" />
                Terima Order
              </BaseButton>
            </div>
          </BaseCard>
        </li>
      </TransitionGroup>
    </div>
  </div>
</template>

<style scoped>
.order-card { transition: opacity 0.3s ease, transform 0.3s ease; }
.order-list-enter-from { opacity: 0; transform: translateY(-8px); }
.order-list-leave-to { opacity: 0; transform: translateX(40px); }
.order-list-leave-active { position: absolute; left: 0; right: 0; }
</style>
