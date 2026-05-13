<script setup>
import { reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import { useToast } from '../composables/useToast';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const toast = useToast();
const form = reactive({
  name: '',
  phone: '',
  email: '',
  password: '',
  password_confirmation: '',
});
const errors = reactive({});
const loading = ref(false);

async function submit() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  loading.value = true;
  try {
    await auth.register(form);
    toast.success('Akun berhasil dibuat');
    router.push({ name: 'home' });
  } catch (e) {
    const errs = e.response?.data?.error?.data?.errors;
    if (errs && typeof errs === 'object') {
      Object.assign(errors, errs);
    }
    toast.error(e.response?.data?.error?.message ?? 'Pendaftaran gagal');
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex flex-col">
    <div class="bg-gradient-to-br from-brand-600 to-violet-600 text-white px-6 pt-12 pb-10 rounded-b-3xl">
      <div class="max-w-md mx-auto">
        <h1 class="text-2xl font-bold">Daftar akun baru</h1>
        <p class="text-sm text-white/80 mt-1">Mulai pakai Zasha dalam 30 detik.</p>
      </div>
    </div>

    <div class="-mt-6 px-5 flex-1 pb-8">
      <div class="max-w-md mx-auto bg-white rounded-card shadow-card p-5 space-y-3">
        <form @submit.prevent="submit" class="space-y-3">
          <BaseInput v-model="form.name" label="Nama lengkap" :error="errors.name?.[0]" required />
          <BaseInput v-model="form.phone" label="Nomor telepon" type="tel" autocomplete="tel" :error="errors.phone?.[0]" required />
          <BaseInput v-model="form.email" label="Email (opsional)" type="email" autocomplete="email" :error="errors.email?.[0]" />
          <BaseInput v-model="form.password" label="Password" type="password" autocomplete="new-password" :error="errors.password?.[0]" required />
          <BaseInput v-model="form.password_confirmation" label="Ulangi password" type="password" autocomplete="new-password" required />

          <BaseButton type="submit" :loading="loading" block size="lg">Daftar Sekarang</BaseButton>
        </form>
      </div>

      <p class="text-center text-sm text-ink-soft mt-6">
        Sudah punya akun?
        <RouterLink to="/login" class="text-brand-600 font-semibold">Masuk</RouterLink>
      </p>
    </div>
  </div>
</template>
