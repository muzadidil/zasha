<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ArrowRightOnRectangleIcon, CheckBadgeIcon, ClockIcon, StarIcon } from '@heroicons/vue/24/solid';
import api from '../api';
import AppHeader from '../components/ui/AppHeader.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import { useToast } from '../composables/useToast';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const toast = useToast();
const me = ref(null);
const profile = ref(null);
const categories = ref([]);
const saving = ref(false);

const form = reactive({
  ktp_number: '',
  ktp_photo_url: '',
  service_categories: [],
  skills: [],
  bank_name: '',
  bank_account: '',
  vehicle_info: { type: '', plate: '' },
});

const skillsInput = ref('');
const initial = computed(() => (me.value?.name ?? '?').charAt(0).toUpperCase());
const isVerified = computed(() => profile.value?.is_verified ?? false);

async function loadAll() {
  try {
    const [meResp, catsResp] = await Promise.all([
      api.get('/auth/me'),
      api.get('/service-categories'),
    ]);
    me.value = meResp.data.data;
    categories.value = catsResp.data.data;
    try {
      const { data } = await api.get('/partner/profile');
      profile.value = data.data;
      Object.assign(form, {
        ktp_number: '',
        ktp_photo_url: data.data.ktp_photo_url,
        service_categories: data.data.service_categories ?? [],
        skills: data.data.skills ?? [],
        bank_name: data.data.bank_name ?? '',
        bank_account: '',
        vehicle_info: data.data.vehicle_info ?? { type: '', plate: '' },
      });
      skillsInput.value = (data.data.skills ?? []).join(', ');
    } catch (_) { /* no profile yet */ }
  } catch (e) {
    toast.error(e.response?.data?.error?.message ?? 'Gagal memuat profil');
  }
}

function toggleCategory(slug) {
  const i = form.service_categories.indexOf(slug);
  if (i >= 0) form.service_categories.splice(i, 1);
  else form.service_categories.push(slug);
}

async function save() {
  saving.value = true;
  form.skills = skillsInput.value.split(',').map((s) => s.trim()).filter(Boolean);
  try {
    const payload = { ...form };
    if (!payload.bank_account) delete payload.bank_account;
    if (!payload.bank_name) delete payload.bank_name;
    if (!payload.vehicle_info.type && !payload.vehicle_info.plate) delete payload.vehicle_info;
    const { data } = await api.put('/partner/profile', payload);
    profile.value = data.data;
    toast.success('Profil tersimpan. Tunggu admin verifikasi.');
  } catch (e) {
    const msgs = e.response?.data?.error?.data?.errors;
    toast.error(msgs ? Object.values(msgs).flat().join(' ') : (e.response?.data?.error?.message ?? 'Gagal menyimpan'));
  } finally {
    saving.value = false;
  }
}

async function handleLogout() {
  await auth.logout();
  toast.info('Sampai jumpa lagi 👋');
  router.push({ name: 'login' });
}

onMounted(loadAll);
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <AppHeader title="Profil Mitra" />

    <div class="max-w-md mx-auto sm:max-w-2xl px-5 py-4 space-y-4">
      <!-- Identity card -->
      <BaseCard padded>
        <div class="flex items-center gap-4">
          <div class="w-16 h-16 rounded-full bg-gradient-to-br from-brand-500 to-violet-500 grid place-items-center text-white font-bold text-2xl">
            {{ initial }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-bold text-lg text-ink truncate">{{ me?.name ?? '—' }}</div>
            <div class="text-sm text-ink-soft">{{ me?.phone ?? '—' }}</div>
            <div class="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold rounded-full px-2 py-0.5"
                 :class="isVerified ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
              <CheckBadgeIcon v-if="isVerified" class="w-3.5 h-3.5" />
              <ClockIcon v-else class="w-3.5 h-3.5" />
              {{ isVerified ? 'Verified' : 'Belum diverifikasi' }}
            </div>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3">
          <div class="bg-amber-50 border border-amber-100 rounded-card p-3 text-center">
            <div class="flex items-center justify-center gap-1 text-amber-600">
              <StarIcon class="w-4 h-4" />
              <span class="font-bold text-base">{{ me?.average_rating ?? '—' }}</span>
            </div>
            <div class="text-[10px] uppercase text-ink-soft font-semibold mt-0.5">Rating</div>
          </div>
          <div class="bg-brand-50 border border-brand-100 rounded-card p-3 text-center">
            <div class="font-bold text-base text-brand-700">{{ me?.rating_count ?? 0 }}</div>
            <div class="text-[10px] uppercase text-ink-soft font-semibold mt-0.5">Ulasan</div>
          </div>
        </div>
      </BaseCard>

      <!-- Profile form -->
      <BaseCard padded>
        <h2 class="text-sm font-semibold text-ink mb-3">Data Mitra</h2>
        <form @submit.prevent="save" class="space-y-3">
          <BaseInput
            v-model="form.ktp_number"
            label="Nomor KTP (16 digit)"
            :helper="profile ? 'Tersimpan: ' + (profile.ktp_number_masked || '****') + ' — isi ulang setiap update.' : 'Wajib diisi.'"
            required
          />
          <BaseInput v-model="form.ktp_photo_url" label="URL Foto KTP" type="url" required />

          <div>
            <label class="text-xs font-semibold text-ink-soft px-1">Kategori jasa</label>
            <div class="flex flex-wrap gap-2 mt-1.5">
              <button
                v-for="c in categories"
                :key="c.id"
                type="button"
                @click="toggleCategory(c.slug)"
                :class="[
                  'h-9 px-3 rounded-full text-xs font-semibold border transition-colors',
                  form.service_categories.includes(c.slug)
                    ? 'border-brand-500 bg-brand-50 text-brand-700'
                    : 'border-slate-200 text-ink-soft',
                ]"
              >{{ c.name }}</button>
            </div>
          </div>

          <BaseInput v-model="skillsInput" label="Skills (pisah koma)" helper="Untuk kategori WFH." />

          <div class="grid grid-cols-2 gap-3">
            <BaseInput v-model="form.bank_name" label="Bank" placeholder=" " />
            <BaseInput v-model="form.bank_account" label="No. rekening" helper="Isi ulang setiap update." />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <BaseInput v-model="form.vehicle_info.type" label="Kendaraan" placeholder=" " />
            <BaseInput v-model="form.vehicle_info.plate" label="Plat nomor" placeholder=" " />
          </div>

          <BaseButton type="submit" :loading="saving" block size="lg">Simpan Profil</BaseButton>
        </form>
      </BaseCard>

      <!-- Logout -->
      <button
        @click="handleLogout"
        class="w-full flex items-center justify-center gap-2 h-12 rounded-btn border border-rose-200 text-rose-600 font-semibold text-sm hover:bg-rose-50 transition-colors"
      >
        <ArrowRightOnRectangleIcon class="w-5 h-5" />
        Keluar
      </button>

      <p class="text-center text-xs text-ink-soft pt-2">Zasha Mitra · v2.0</p>
    </div>
  </div>
</template>
