<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const phone = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

async function submit() {
  error.value = '';
  loading.value = true;
  try {
    await auth.login(phone.value, password.value);
    router.push({ name: 'available' });
  } catch (e) {
    error.value = e.response?.data?.error?.message ?? 'Login gagal.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="bg-slate-800 rounded p-6 max-w-md mx-auto">
    <h1 class="text-xl font-semibold mb-4 text-amber-400">Masuk Mitra</h1>
    <form @submit.prevent="submit" class="space-y-3">
      <input v-model="phone" type="tel" required placeholder="Nomor telepon" class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2" />
      <input v-model="password" type="password" required placeholder="Password" class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2" />
      <p v-if="error" class="text-rose-400 text-sm">{{ error }}</p>
      <button :disabled="loading" class="w-full bg-amber-400 text-slate-900 rounded py-2 font-semibold disabled:opacity-50">
        {{ loading ? 'Memproses…' : 'Masuk' }}
      </button>
    </form>
  </div>
</template>
