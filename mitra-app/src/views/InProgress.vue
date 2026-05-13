<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ChatBubbleLeftEllipsisIcon, PlayIcon, CheckCircleIcon, MapPinIcon } from '@heroicons/vue/24/outline';
import api from '../api';
import AppHeader from '../components/ui/AppHeader.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import CategoryIcon from '../components/ui/CategoryIcon.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import LoadingSpinner from '../components/ui/LoadingSpinner.vue';
import StatusBadge from '../components/ui/StatusBadge.vue';
import { useToast } from '../composables/useToast';

const router = useRouter();
const toast = useToast();
const orders = ref([]);
const loading = ref(true);
const acting = ref(null);

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/partner/orders/history');
    orders.value = data.data;
  } finally {
    loading.value = false;
  }
}

const activeOrders = computed(() =>
  orders.value.filter((o) => ['claimed', 'in_progress'].includes(o.status))
);

async function startOrder(id) {
  acting.value = id;
  try {
    await api.patch(`/partner/orders/${id}/start`);
    toast.success('Pekerjaan dimulai');
    await load();
  } catch (e) {
    toast.error(e.response?.data?.error?.message ?? 'Gagal mulai');
  } finally {
    acting.value = null;
  }
}

async function completeOrder(id) {
  acting.value = id;
  try {
    await api.patch(`/partner/orders/${id}/complete`);
    toast.success('Order selesai');
    await load();
  } catch (e) {
    toast.error(e.response?.data?.error?.message ?? 'Gagal selesaikan');
  } finally {
    acting.value = null;
  }
}

function waLink(phone) {
  if (!phone) return '#';
  return `https://wa.me/${String(phone).replace(/[^0-9]/g, '')}`;
}

onMounted(load);
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <AppHeader title="Sedang Dikerjakan" />

    <div class="max-w-md mx-auto sm:max-w-2xl px-5 py-4">
      <LoadingSpinner v-if="loading" />
      <EmptyState
        v-else-if="activeOrders.length === 0"
        title="Tidak ada order aktif"
        description="Setelah kamu terima order, akan muncul di sini sampai selesai."
      >
        <BaseButton size="md" @click="router.push({ name: 'available' })">Cari Order</BaseButton>
      </EmptyState>

      <ul v-else class="space-y-3">
        <li v-for="o in activeOrders" :key="o.id">
          <BaseCard padded>
            <div class="flex items-center gap-3 mb-3">
              <CategoryIcon :slug="o.service_category?.slug ?? 'titip'" />
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <span class="font-semibold text-ink">{{ o.service_category?.name }}</span>
                  <StatusBadge :status="o.status" />
                </div>
                <div class="text-xs text-ink-soft mt-0.5">#{{ o.id.slice(-6) }}</div>
              </div>
              <div class="text-right">
                <div class="text-xs text-ink-soft">Bayaran</div>
                <div class="font-bold text-ink">Rp {{ o.current_price.toLocaleString('id-ID') }}</div>
              </div>
            </div>

            <div v-if="o.customer" class="flex items-center gap-2 text-sm">
              <div class="w-8 h-8 rounded-full bg-slate-200 grid place-items-center text-xs font-bold text-slate-700">
                {{ (o.customer.name ?? '?').charAt(0).toUpperCase() }}
              </div>
              <span class="font-medium">{{ o.customer.name }}</span>
            </div>

            <div v-if="o.details" class="mt-2 text-xs text-ink-muted flex items-start gap-1">
              <MapPinIcon class="w-3.5 h-3.5 mt-0.5 flex-shrink-0 text-rose-400" />
              <span class="line-clamp-2">{{ o.details.pickup_address || o.details.work_address || o.details.service_address || o.details.task_title || '—' }}</span>
            </div>

            <div class="mt-3 pt-3 border-t border-slate-100 grid grid-cols-2 gap-2">
              <BaseButton
                v-if="o.status === 'claimed'"
                size="md"
                :loading="acting === o.id"
                @click="startOrder(o.id)"
              >
                <PlayIcon class="w-4 h-4" /> Mulai
              </BaseButton>
              <BaseButton
                v-if="o.status === 'in_progress'"
                variant="primary"
                size="md"
                :loading="acting === o.id"
                @click="completeOrder(o.id)"
              >
                <CheckCircleIcon class="w-4 h-4" /> Selesai
              </BaseButton>
              <a
                v-if="o.customer_phone"
                :href="waLink(o.customer_phone)"
                target="_blank"
                class="h-12 inline-flex items-center justify-center gap-1 bg-emerald-500 hover:bg-emerald-600 text-white rounded-btn font-semibold text-sm transition-colors"
              >
                <ChatBubbleLeftEllipsisIcon class="w-4 h-4" /> Chat
              </a>
            </div>
          </BaseCard>
        </li>
      </ul>
    </div>
  </div>
</template>
