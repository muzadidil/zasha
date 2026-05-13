<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChatBubbleLeftEllipsisIcon, ChevronUpIcon, NoSymbolIcon, StarIcon } from '@heroicons/vue/24/solid';
import api from '../api';
import OsmMap from '../components/OsmMap.vue';
import AppHeader from '../components/ui/AppHeader.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseModal from '../components/ui/BaseModal.vue';
import CategoryIcon from '../components/ui/CategoryIcon.vue';
import LoadingSpinner from '../components/ui/LoadingSpinner.vue';
import StatusBadge from '../components/ui/StatusBadge.vue';
import { useToast } from '../composables/useToast';
import { useAuthStore } from '../stores/auth';
import { useCategoriesStore } from '../stores/categories';
import { useOrderStore } from '../stores/order';

const RACE_WINDOW_MS = 10_000;

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const orderStore = useOrderStore();
const categories = useCategoriesStore();
const toast = useToast();
const acting = ref(false);
const rating = reactive({ stars: 5, comment: '', submitted: false });
const now = ref(Date.now());
const isExpiredOptimistic = ref(false);
const optimisticExpiredAt = ref(null);
const showLateClaimModal = ref(false);
let tickInterval = null;
let autoReorderTimer = null;

const order = computed(() => orderStore.currentOrder);
const radiusKm = computed(() => orderStore.activeRadiusKm);
const pickup = computed(() => {
  const p = order.value?.pickup;
  return p ? [p.lat, p.lng] : null;
});

const isSearching = computed(() => order.value?.status === 'searching');
const isClaimed = computed(() => order.value?.status === 'claimed' || order.value?.status === 'in_progress');
const isExpired = computed(() => order.value?.status === 'expired');
const isCancelled = computed(() => order.value?.status === 'cancelled');
const canRate = computed(() => order.value?.status === 'completed' && !rating.submitted);
const showExpiredModal = computed(() => (isExpired.value || isExpiredOptimistic.value) && !showLateClaimModal.value);

const secondsLeft = computed(() => {
  if (!order.value?.expires_at) return null;
  const diff = Math.floor((new Date(order.value.expires_at).getTime() - now.value) / 1000);
  return diff > 0 ? diff : 0;
});

const progressPct = computed(() => {
  if (!order.value?.expires_at || !order.value?.created_at) return 0;
  const total = new Date(order.value.expires_at).getTime() - new Date(order.value.created_at).getTime();
  const remain = new Date(order.value.expires_at).getTime() - now.value;
  if (total <= 0) return 100;
  return Math.max(0, Math.min(100, ((total - remain) / total) * 100));
});

watch(secondsLeft, (s) => {
  if (s === 0 && isSearching.value && !isExpiredOptimistic.value) {
    isExpiredOptimistic.value = true;
    optimisticExpiredAt.value = Date.now();
  }
});

watch([isExpired, isExpiredOptimistic], ([backendExpired, optimistic]) => {
  if (!backendExpired && !optimistic) return;
  if (showLateClaimModal.value) return;
  if (autoReorderTimer) return;
  const delay = backendExpired ? 600 : RACE_WINDOW_MS;
  autoReorderTimer = setTimeout(() => {
    if (showLateClaimModal.value) return;
    reorder();
  }, delay);
});

watch(() => orderStore.lastClaimAt, (claimedAt) => {
  if (!claimedAt || !optimisticExpiredAt.value) return;
  if (claimedAt - optimisticExpiredAt.value <= RACE_WINDOW_MS) {
    isExpiredOptimistic.value = false;
    showLateClaimModal.value = true;
  }
});

async function increasePrice() {
  acting.value = true;
  try {
    await orderStore.increasePrice(route.params.id);
    toast.success('Harga dinaikkan');
  } catch (e) {
    toast.error(e.response?.data?.error?.message ?? 'Gagal menaikkan harga');
  } finally {
    acting.value = false;
  }
}

async function cancel() {
  acting.value = true;
  try {
    await orderStore.cancel(route.params.id);
    toast.info('Order dibatalkan');
  } catch (e) {
    toast.error(e.response?.data?.error?.message ?? 'Gagal membatalkan');
  } finally {
    acting.value = false;
  }
}

async function submitRating() {
  try {
    await api.post(`/customer/orders/${route.params.id}/rate`, {
      stars: rating.stars,
      comment: rating.comment || undefined,
    });
    rating.submitted = true;
    toast.success('Terima kasih atas ratingnya!');
  } catch (e) {
    toast.error(e.response?.data?.error?.message ?? 'Gagal mengirim rating');
  }
}

function waLink(phone) {
  if (!phone) return '#';
  return `https://wa.me/${String(phone).replace(/[^0-9]/g, '')}`;
}

function reorder() {
  if (!order.value) return;
  const slug = order.value.service_category?.slug;
  const cat = categories.bySlug(slug);
  const step = cat?.price_step ?? 0;
  const query = {
    slug,
    details: JSON.stringify(order.value.details ?? {}),
    suggested_price: String((order.value.current_price ?? 0) + step),
  };
  if (order.value.pickup) {
    query.lat = String(order.value.pickup.lat);
    query.lng = String(order.value.pickup.lng);
  }
  router.push({ name: 'orders.create', query });
}

onMounted(async () => {
  try {
    await orderStore.load(route.params.id);
    await categories.load();
  } catch (e) {
    toast.error(e.response?.data?.error?.message ?? 'Gagal memuat detail order');
    return;
  }
  if (auth.token) orderStore.subscribe(route.params.id, auth.token);
  tickInterval = setInterval(() => { now.value = Date.now(); }, 1000);
});

onUnmounted(() => {
  orderStore.unsubscribe();
  if (tickInterval) clearInterval(tickInterval);
  if (autoReorderTimer) clearTimeout(autoReorderTimer);
});
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <AppHeader back :title="'Order ' + (order?.id?.slice(-6) ?? '')">
      <template #action>
        <StatusBadge v-if="order" :status="order.status" />
      </template>
    </AppHeader>

    <LoadingSpinner v-if="!order" fullscreen label="Memuat order…" />

    <div v-else class="max-w-md mx-auto sm:max-w-2xl space-y-4">
      <!-- Hero: searching with radius -->
      <section v-if="isSearching && order.service_category?.requires_geolocation" class="px-5 pt-4">
        <BaseCard padded>
          <OsmMap
            :center="pickup ?? [-8.17, 113.70]"
            :pickup="pickup"
            :radius-km="radiusKm"
            :height="'260px'"
            :show-pulse="true"
          />
          <div class="text-center mt-4">
            <div class="text-lg font-semibold text-ink">Mencari mitra…</div>
            <div v-if="radiusKm" class="text-sm text-brand-600 font-medium mt-1">Radius aktif: {{ radiusKm }} km</div>
          </div>
          <!-- Countdown progress bar -->
          <div class="mt-4">
            <div class="flex justify-between text-xs text-ink-soft">
              <span>{{ secondsLeft ?? '—' }} detik tersisa</span>
              <span>{{ Math.round(progressPct) }}%</span>
            </div>
            <div class="mt-1 h-2 rounded-full bg-slate-200 overflow-hidden">
              <div
                class="h-full bg-gradient-to-r from-brand-500 to-violet-500 transition-all duration-1000"
                :style="{ width: progressPct + '%' }"
              />
            </div>
          </div>
        </BaseCard>
      </section>

      <!-- WFH searching -->
      <section v-else-if="isSearching" class="px-5 pt-4">
        <BaseCard padded>
          <div class="grid place-items-center py-8">
            <div class="w-16 h-16 rounded-full bg-brand-100 grid place-items-center mb-3">
              <span class="inline-block w-7 h-7 border-[3px] border-brand-300 border-t-brand-600 rounded-full animate-spin" />
            </div>
            <div class="text-lg font-semibold text-ink">Mencari mitra WFH…</div>
            <div v-if="secondsLeft !== null" class="text-sm text-ink-soft mt-1">⏱️ {{ secondsLeft }} detik tersisa</div>
          </div>
        </BaseCard>
      </section>

      <!-- Claimed: partner info + map -->
      <section v-if="isClaimed" class="px-5 pt-4 space-y-3">
        <BaseCard v-if="pickup" :padded="false">
          <OsmMap :center="pickup" :pickup="pickup" :partner="orderStore.partnerLocation" :height="'220px'" />
        </BaseCard>
        <BaseCard padded>
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-emerald-100 grid place-items-center text-emerald-700 font-bold">
              {{ (order.partner?.name ?? '?').charAt(0).toUpperCase() }}
            </div>
            <div class="flex-1">
              <div class="font-semibold text-ink">{{ order.partner?.name ?? 'Mitra' }}</div>
              <div v-if="order.partner?.average_rating !== undefined" class="text-xs text-ink-soft flex items-center gap-1">
                <StarIcon class="w-3.5 h-3.5 text-amber-400" />
                {{ order.partner?.average_rating ?? '—' }}
              </div>
            </div>
            <a
              v-if="order.partner?.phone"
              :href="waLink(order.partner.phone)"
              target="_blank"
              class="h-11 px-4 inline-flex items-center gap-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-btn font-semibold text-sm transition-colors"
            >
              <ChatBubbleLeftEllipsisIcon class="w-4 h-4" /> WA
            </a>
          </div>
        </BaseCard>
      </section>

      <!-- Order summary card -->
      <section class="px-5">
        <BaseCard padded>
          <div class="flex items-center gap-3">
            <CategoryIcon :slug="order.service_category?.slug" />
            <div class="flex-1">
              <div class="font-semibold text-ink">{{ order.service_category?.name }}</div>
              <div class="text-xs text-ink-soft">#{{ order.id.slice(-6) }}</div>
            </div>
            <div class="text-right">
              <div class="text-[10px] text-ink-soft font-medium uppercase tracking-wide">Harga</div>
              <div class="text-lg font-bold text-ink">Rp {{ order.current_price.toLocaleString('id-ID') }}</div>
            </div>
          </div>
        </BaseCard>
      </section>

      <!-- Action buttons -->
      <section v-if="isSearching" class="px-5">
        <div class="grid grid-cols-2 gap-3">
          <BaseButton variant="primary" size="md" :loading="acting" @click="increasePrice">
            <ChevronUpIcon class="w-4 h-4" /> Naikkan harga
          </BaseButton>
          <BaseButton variant="secondary" size="md" :loading="acting" @click="cancel">
            <NoSymbolIcon class="w-4 h-4" /> Batalkan
          </BaseButton>
        </div>
      </section>

      <!-- Rating panel -->
      <section v-if="canRate" class="px-5">
        <BaseCard padded>
          <h3 class="text-sm font-semibold text-ink mb-3">Beri rating mitra</h3>
          <div class="flex gap-2 justify-center mb-3">
            <button
              v-for="s in 5" :key="s"
              type="button"
              @click="rating.stars = s"
              class="w-11 h-11 rounded-full grid place-items-center transition-transform active:scale-90"
              :class="rating.stars >= s ? 'bg-amber-400 text-white' : 'bg-slate-100 text-slate-400'"
            >
              <StarIcon class="w-5 h-5" />
            </button>
          </div>
          <textarea
            v-model="rating.comment"
            maxlength="300"
            placeholder="Komentar (opsional)"
            class="w-full rounded-btn border border-slate-300 p-3 text-sm focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
            rows="3"
          />
          <BaseButton block size="md" @click="submitRating" class="mt-3">Kirim Rating</BaseButton>
        </BaseCard>
      </section>

      <!-- Cancelled state -->
      <section v-if="isCancelled" class="px-5">
        <BaseCard padded>
          <p class="text-sm text-rose-600 text-center">Order ini sudah dibatalkan.</p>
        </BaseCard>
      </section>

      <div class="h-4" />
    </div>

    <!-- Expired modal -->
    <BaseModal :open="showExpiredModal" :closable="false" title="Yah, belum ada mitra yang menerima">
      <p class="text-sm text-ink-soft mb-4">
        Form order kamu akan otomatis dibuka kembali dengan harga lebih tinggi…
      </p>
      <div class="space-y-2">
        <BaseButton block size="md" @click="reorder">Order Ulang Sekarang</BaseButton>
        <BaseButton block size="md" variant="secondary" @click="router.push('/')">Batal, kembali ke Beranda</BaseButton>
      </div>
    </BaseModal>

    <!-- Late-claim modal -->
    <BaseModal :open="showLateClaimModal" @close="showLateClaimModal = false" title="Diterima di detik terakhir!">
      <p class="text-sm text-ink-soft mb-4">
        🎉 Order diterima oleh {{ order?.partner?.name ?? 'mitra' }}.
      </p>
      <BaseButton block size="md" @click="showLateClaimModal = false">Lihat detail mitra</BaseButton>
    </BaseModal>
  </div>
</template>
