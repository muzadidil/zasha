<script setup>
import { computed, onMounted, ref } from 'vue';
import { BanknotesIcon, PlusIcon, ArrowDownTrayIcon, ArrowUpTrayIcon, MinusCircleIcon } from '@heroicons/vue/24/outline';
import api from '../api';
import AppHeader from '../components/ui/AppHeader.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import BaseModal from '../components/ui/BaseModal.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import LoadingSpinner from '../components/ui/LoadingSpinner.vue';
import { useToast } from '../composables/useToast';

const toast = useToast();
const wallet = ref({ balance: 0, max_balance: 100000 });
const transactions = ref([]);
const loading = ref(true);
const showTopup = ref(false);
const topupAmount = ref(10000);
const topupProof = ref('');
const submitting = ref(false);

const balancePct = computed(() => Math.min(100, (wallet.value.balance / wallet.value.max_balance) * 100));

async function load() {
  loading.value = true;
  try {
    const [walletResp, txResp] = await Promise.all([
      api.get('/partner/wallet'),
      api.get('/partner/wallet/transactions'),
    ]);
    wallet.value = walletResp.data.data;
    transactions.value = txResp.data.data;
  } finally {
    loading.value = false;
  }
}

async function submitTopup() {
  submitting.value = true;
  try {
    await api.post('/partner/wallet/topup-request', { amount: topupAmount.value, proof_url: topupProof.value });
    toast.success('Top-up dikirim. Tunggu admin memverifikasi.');
    showTopup.value = false;
    topupProof.value = '';
    await load();
  } catch (e) {
    toast.error(e.response?.data?.error?.message ?? 'Gagal mengirim top-up');
  } finally {
    submitting.value = false;
  }
}

function txMeta(t) {
  switch (t.type) {
    case 'topup': return { label: 'Top-up', icon: ArrowDownTrayIcon, color: 'text-emerald-600 bg-emerald-50' };
    case 'fee': return { label: 'Fee 5%', icon: MinusCircleIcon, color: 'text-rose-600 bg-rose-50' };
    case 'refund': return { label: 'Refund', icon: ArrowUpTrayIcon, color: 'text-brand-600 bg-brand-50' };
    default: return { label: t.type, icon: BanknotesIcon, color: 'text-slate-600 bg-slate-100' };
  }
}

function timeAgo(iso) {
  if (!iso) return '';
  const diff = (Date.now() - new Date(iso).getTime()) / 1000;
  if (diff < 60) return 'baru saja';
  if (diff < 3600) return `${Math.floor(diff / 60)}m`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}j`;
  return `${Math.floor(diff / 86400)}h`;
}

onMounted(load);
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <AppHeader title="Wallet" />

    <div class="max-w-md mx-auto sm:max-w-2xl px-5 py-4 space-y-4">
      <!-- Balance card -->
      <div class="rounded-card overflow-hidden bg-gradient-to-br from-slate-900 to-brand-900 text-white p-5 shadow-card relative">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -translate-y-16 translate-x-16" />
        <div class="absolute bottom-0 right-0 w-24 h-24 bg-white/5 rounded-full translate-y-12 translate-x-12" />
        <div class="relative">
          <div class="flex items-center gap-2 text-xs text-white/70 font-medium">
            <BanknotesIcon class="w-4 h-4" /> Saldo wallet kamu
          </div>
          <div class="text-3xl font-bold mt-2">Rp {{ wallet.balance.toLocaleString('id-ID') }}</div>

          <div class="mt-3 h-1.5 bg-white/15 rounded-full overflow-hidden">
            <div class="h-full bg-amber-400 transition-all duration-500" :style="{ width: balancePct + '%' }" />
          </div>
          <div class="flex justify-between text-[10px] text-white/60 mt-1">
            <span>Min: Rp 0</span>
            <span>Max: Rp {{ wallet.max_balance.toLocaleString('id-ID') }}</span>
          </div>

          <button
            @click="showTopup = true"
            class="mt-4 w-full h-11 bg-amber-400 hover:bg-amber-500 text-slate-900 rounded-btn font-semibold flex items-center justify-center gap-1.5 transition-colors"
          >
            <PlusIcon class="w-4 h-4" /> Top-up Saldo
          </button>
        </div>
      </div>

      <!-- Tips -->
      <div class="bg-amber-50 border border-amber-100 rounded-card p-3 text-xs text-amber-800">
        💡 Saldo digunakan untuk fee 5% dari setiap order. Pastikan saldo cukup sebelum menerima order.
      </div>

      <!-- Transactions -->
      <div>
        <h2 class="text-sm font-semibold text-ink mb-2 px-1">Riwayat transaksi</h2>
        <LoadingSpinner v-if="loading" />
        <EmptyState v-else-if="!transactions.length" title="Belum ada transaksi" description="Top-up atau ambil order untuk melihat riwayat." />
        <BaseCard v-else :padded="false">
          <ul class="divide-y divide-slate-100">
            <li v-for="t in transactions" :key="t.id" class="px-4 py-3 flex items-center gap-3">
              <div :class="['w-9 h-9 rounded-full grid place-items-center', txMeta(t).color]">
                <component :is="txMeta(t).icon" class="w-4 h-4" />
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-ink">{{ txMeta(t).label }}</div>
                <div class="text-xs text-ink-soft truncate">{{ t.notes || timeAgo(t.created_at) }}</div>
              </div>
              <div :class="['text-sm font-semibold', t.amount < 0 ? 'text-rose-600' : 'text-emerald-600']">
                {{ t.amount > 0 ? '+' : '' }}Rp {{ Math.abs(t.amount).toLocaleString('id-ID') }}
              </div>
            </li>
          </ul>
        </BaseCard>
      </div>
    </div>

    <BaseModal :open="showTopup" @close="showTopup = false" title="Top-up Saldo">
      <div class="space-y-3">
        <BaseInput
          v-model.number="topupAmount"
          label="Jumlah (Rp)"
          type="number"
          helper="Min 1.000 — max Rp 100.000"
        />
        <BaseInput
          v-model="topupProof"
          label="URL bukti transfer"
          type="url"
          required
          helper="Upload bukti ke imgur/Google Drive, lalu paste link-nya."
        />
        <BaseButton block size="lg" :loading="submitting" @click="submitTopup">
          Kirim Permintaan
        </BaseButton>
      </div>
    </BaseModal>
  </div>
</template>
