<script setup>
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import { useToast } from '../composables/useToast';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const toast = useToast();
const phone = ref('');
const password = ref('');
const phoneError = ref('');
const passwordError = ref('');
const loading = ref(false);

async function submit() {
  phoneError.value = '';
  passwordError.value = '';
  if (!phone.value) phoneError.value = 'Nomor telepon wajib diisi';
  if (!password.value) passwordError.value = 'Password wajib diisi';
  if (phoneError.value || passwordError.value) return;

  loading.value = true;
  try {
    await auth.login(phone.value, password.value);
    router.push({ name: 'available' });
  } catch (e) {
    toast.error(e.response?.data?.error?.message ?? 'Login gagal');
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex flex-col">
    <div class="bg-gradient-to-br from-slate-900 to-brand-900 text-white px-6 pt-16 pb-12 rounded-b-3xl">
      <div class="max-w-md mx-auto">
        <div class="inline-block px-2.5 py-0.5 rounded-full bg-amber-400/20 text-amber-300 text-[10px] font-semibold uppercase tracking-wide mb-2">
          Mitra
        </div>
        <h1 class="text-3xl font-bold">Zasha Mitra</h1>
        <p class="text-sm text-white/80 mt-1">Cari order, kelola pekerjaan, dapatkan penghasilan.</p>
      </div>
    </div>

    <div class="-mt-8 px-5 flex-1">
      <div class="max-w-md mx-auto bg-white rounded-card shadow-card p-5 space-y-4">
        <h2 class="font-semibold text-ink">Masuk ke akun mitra</h2>
        <form @submit.prevent="submit" class="space-y-3">
          <BaseInput v-model="phone" label="Nomor telepon" type="tel" autocomplete="tel" :error="phoneError" required />
          <BaseInput v-model="password" label="Password" type="password" autocomplete="current-password" :error="passwordError" required />
          <BaseButton type="submit" :loading="loading" block size="lg">Masuk</BaseButton>
        </form>
      </div>

      <p class="text-center text-sm text-ink-soft mt-6">
        Mau jadi mitra?
        <RouterLink to="/register" class="text-brand-600 font-semibold">Daftar di sini</RouterLink>
      </p>
    </div>
  </div>
</template>
