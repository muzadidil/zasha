<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import AppHeader from '../components/ui/AppHeader.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import CategoryIcon from '../components/ui/CategoryIcon.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import LoadingSpinner from '../components/ui/LoadingSpinner.vue';
import StatusBadge from '../components/ui/StatusBadge.vue';

const router = useRouter();
const orders = ref([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const { data } = await api.get('/customer/orders');
    orders.value = data.data;
  } finally {
    loading.value = false;
  }
});

const activeOrders = computed(() =>
  orders.value.filter((o) => ['searching', 'claimed', 'in_progress'].includes(o.status))
);
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <AppHeader title="Order Aktif" />

    <div class="max-w-md mx-auto sm:max-w-2xl px-5 py-4">
      <LoadingSpinner v-if="loading" />
      <EmptyState
        v-else-if="activeOrders.length === 0"
        title="Tidak ada order aktif"
        description="Order yang sedang dicari mitra atau sedang dikerjakan akan tampil di sini."
      >
        <BaseButton size="md" @click="router.push({ name: 'home' })">Pesan Sekarang</BaseButton>
      </EmptyState>

      <ul v-else class="space-y-2.5">
        <li v-for="o in activeOrders" :key="o.id">
          <BaseCard hoverable @click="router.push(`/orders/${o.id}`)">
            <div class="flex items-center gap-3">
              <CategoryIcon :slug="o.service_category?.slug ?? 'titip'" size="sm" />
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <span class="font-semibold text-ink">{{ o.service_category?.name ?? '—' }}</span>
                  <StatusBadge :status="o.status" />
                </div>
                <div class="text-sm font-medium text-ink">Rp {{ o.current_price.toLocaleString('id-ID') }}</div>
                <div v-if="o.partner?.name" class="text-xs text-ink-soft mt-0.5">👤 {{ o.partner.name }}</div>
              </div>
            </div>
          </BaseCard>
        </li>
      </ul>
    </div>
  </div>
</template>
