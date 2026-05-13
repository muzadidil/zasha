<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../api';

const wallet = ref({ balance: 0, max_balance: 100000 });
const transactions = ref([]);
const topup = reactive({ amount: 10000, proof_url: '' });
const message = ref('');
const error = ref('');

async function load() {
  const [walletResp, txResp] = await Promise.all([
    api.get('/partner/wallet'),
    api.get('/partner/wallet/transactions'),
  ]);
  wallet.value = walletResp.data.data;
  transactions.value = txResp.data.data;
}

async function submit() {
  message.value = ''; error.value = '';
  try {
    await api.post('/partner/wallet/topup-request', { amount: topup.amount, proof_url: topup.proof_url });
    message.value = 'Permintaan top-up dikirim. Tunggu admin verifikasi.';
    topup.proof_url = '';
  } catch (e) {
    error.value = e.response?.data?.error?.message ?? 'Gagal mengirim top-up.';
  }
}

onMounted(load);
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-xl font-semibold text-amber-400">Saldo</h1>
    <div class="bg-slate-800 rounded p-4">
      <div class="text-sm text-slate-400">Saldo saat ini</div>
      <div class="text-3xl font-bold text-amber-400">Rp {{ wallet.balance.toLocaleString('id-ID') }}</div>
      <div class="text-xs text-slate-500 mt-1">Batas atas Rp {{ wallet.max_balance.toLocaleString('id-ID') }}</div>
    </div>

    <div class="bg-slate-800 rounded p-4">
      <h2 class="font-semibold mb-2">Top-up Manual</h2>
      <form @submit.prevent="submit" class="space-y-2">
        <input v-model.number="topup.amount" type="number" min="1000" :max="wallet.max_balance" required class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2" />
        <input v-model="topup.proof_url" type="url" required placeholder="URL bukti transfer" class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2" />
        <button class="bg-amber-400 text-slate-900 rounded py-2 px-4 font-semibold">Kirim Permintaan</button>
      </form>
      <p v-if="message" class="text-emerald-400 text-sm mt-2">{{ message }}</p>
      <p v-if="error" class="text-rose-400 text-sm mt-2">{{ error }}</p>
    </div>

    <div class="bg-slate-800 rounded p-4">
      <h2 class="font-semibold mb-2">Riwayat Transaksi</h2>
      <p v-if="!transactions.length" class="text-slate-500 text-sm">Belum ada transaksi.</p>
      <ul v-else class="divide-y divide-slate-700">
        <li v-for="t in transactions" :key="t.id" class="py-2 flex justify-between text-sm">
          <span>{{ t.type }} <span class="text-slate-500 text-xs">{{ t.notes }}</span></span>
          <span :class="t.amount < 0 ? 'text-rose-400' : 'text-emerald-400'">
            {{ t.amount > 0 ? '+' : '' }}Rp {{ t.amount.toLocaleString('id-ID') }}
          </span>
        </li>
      </ul>
    </div>
  </div>
</template>
