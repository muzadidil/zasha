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
    router.push({ name: 'orders.index' });
  } catch (e) {
    error.value = e.response?.data?.error?.message ?? 'Login gagal.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="bg-white shadow rounded-lg p-6 max-w-md mx-auto">
    <h1 class="text-xl font-semibold mb-4">Masuk</h1>
    <form @submit.prevent="submit" class="space-y-3">
      <div>
        <label class="block text-sm text-slate-600 mb-1">Nomor Telepon</label>
        <input v-model="phone" type="tel" required class="w-full border rounded px-3 py-2" placeholder="+6281234567890" />
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Password</label>
        <input v-model="password" type="password" required class="w-full border rounded px-3 py-2" />
      </div>
      <p v-if="error" class="text-rose-600 text-sm">{{ error }}</p>
      <button type="submit" :disabled="loading" class="w-full bg-indigo-600 text-white rounded py-2 disabled:opacity-50">
        {{ loading ? 'Memproses…' : 'Masuk' }}
      </button>
    </form>
  </div>
</template>
