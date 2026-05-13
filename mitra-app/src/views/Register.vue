<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const form = reactive({ name: '', phone: '', email: '', password: '', password_confirmation: '' });
const error = ref('');
const loading = ref(false);

async function submit() {
  error.value = '';
  loading.value = true;
  try {
    await auth.register(form);
    router.push({ name: 'profile' });
  } catch (e) {
    error.value = e.response?.data?.error?.message ?? 'Pendaftaran gagal.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="bg-slate-800 rounded p-6 max-w-md mx-auto">
    <h1 class="text-xl font-semibold mb-4 text-amber-400">Daftar Akun Mitra</h1>
    <form @submit.prevent="submit" class="space-y-3">
      <input v-model="form.name" required placeholder="Nama lengkap" class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2" />
      <input v-model="form.phone" required placeholder="Nomor telepon" class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2" />
      <input v-model="form.email" type="email" placeholder="Email (opsional)" class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2" />
      <input v-model="form.password" type="password" required placeholder="Password" class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2" />
      <input v-model="form.password_confirmation" type="password" required placeholder="Ulangi password" class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2" />
      <p v-if="error" class="text-rose-400 text-sm">{{ error }}</p>
      <button :disabled="loading" class="w-full bg-amber-400 text-slate-900 rounded py-2 font-semibold disabled:opacity-50">
        {{ loading ? 'Memproses…' : 'Daftar' }}
      </button>
    </form>
    <p class="text-xs text-slate-400 mt-3">Setelah daftar, lengkapi profil &amp; tunggu verifikasi admin sebelum bisa ambil order.</p>
  </div>
</template>
