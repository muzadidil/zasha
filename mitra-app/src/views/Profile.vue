<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../api';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const me = ref(null);
const profile = ref(null);
const categories = ref([]);
const error = ref('');
const message = ref('');
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
const isVerified = computed(() => profile.value?.is_verified ?? false);

async function loadAll() {
  error.value = '';
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
    } catch (e) {
      if (e.response?.status !== 422 && e.response?.status !== 404) {
        // 404/422 = no profile yet — leave form blank.
      }
    }
  } catch (e) {
    error.value = e.response?.data?.error?.message ?? 'Gagal memuat profil.';
  }
}

function toggleCategory(slug) {
  const i = form.service_categories.indexOf(slug);
  if (i >= 0) form.service_categories.splice(i, 1);
  else form.service_categories.push(slug);
}

async function save() {
  error.value = ''; message.value = ''; saving.value = true;
  form.skills = skillsInput.value.split(',').map((s) => s.trim()).filter(Boolean);
  try {
    const payload = { ...form };
    if (!payload.bank_account) delete payload.bank_account;
    if (!payload.bank_name) delete payload.bank_name;
    if (!payload.vehicle_info.type && !payload.vehicle_info.plate) delete payload.vehicle_info;
    const { data } = await api.put('/partner/profile', payload);
    profile.value = data.data;
    message.value = 'Profil tersimpan. Tunggu admin verifikasi.';
  } catch (e) {
    const msgs = e.response?.data?.error?.data?.errors;
    error.value = msgs ? Object.values(msgs).flat().join(' ') : (e.response?.data?.error?.message ?? 'Gagal menyimpan profil.');
  } finally {
    saving.value = false;
  }
}

onMounted(loadAll);
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-xl font-semibold text-amber-400">Profil Mitra</h1>

    <div v-if="me" class="bg-slate-800 rounded p-4 space-y-1 text-sm">
      <div><span class="text-slate-400">Nama:</span> {{ me.name }}</div>
      <div><span class="text-slate-400">Telepon:</span> {{ me.phone }}</div>
      <div><span class="text-slate-400">Rating:</span> {{ me.average_rating ?? '-' }} ({{ me.rating_count }} ulasan)</div>
      <div>
        <span class="text-slate-400">Verifikasi:</span>
        <span :class="isVerified ? 'text-emerald-400' : 'text-amber-400'">
          {{ isVerified ? 'Sudah diverifikasi admin' : 'Menunggu / belum diverifikasi' }}
        </span>
      </div>
    </div>

    <form @submit.prevent="save" class="bg-slate-800 rounded p-4 space-y-3">
      <h2 class="font-semibold">Data Mitra</h2>

      <div>
        <label class="text-sm text-slate-400">Nomor KTP (16 digit)</label>
        <input v-model="form.ktp_number" required maxlength="16" placeholder="Masukkan ulang setiap update"
               class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 mt-1" />
        <p v-if="profile" class="text-xs text-slate-500 mt-1">Tersimpan: {{ profile.ktp_number_masked }}</p>
      </div>

      <div>
        <label class="text-sm text-slate-400">URL Foto KTP</label>
        <input v-model="form.ktp_photo_url" required type="url"
               class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 mt-1" />
      </div>

      <div>
        <label class="text-sm text-slate-400">Kategori jasa yang dilayani</label>
        <div class="flex flex-wrap gap-2 mt-2">
          <button v-for="c in categories" :key="c.id" type="button" @click="toggleCategory(c.slug)"
                  :class="['px-3 py-1 rounded text-sm border',
                    form.service_categories.includes(c.slug)
                      ? 'border-amber-400 bg-amber-400/20 text-amber-300'
                      : 'border-slate-600 text-slate-300']">
            {{ c.name }}
          </button>
        </div>
      </div>

      <div>
        <label class="text-sm text-slate-400">Skills (pisah koma — untuk WFH)</label>
        <input v-model="skillsInput" placeholder="desain grafis, web development"
               class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 mt-1" />
      </div>

      <div class="grid grid-cols-2 gap-2">
        <div>
          <label class="text-sm text-slate-400">Bank</label>
          <input v-model="form.bank_name" placeholder="BCA"
                 class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 mt-1" />
        </div>
        <div>
          <label class="text-sm text-slate-400">No. Rekening</label>
          <input v-model="form.bank_account" placeholder="Masukkan ulang setiap update"
                 class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 mt-1" />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-2">
        <div>
          <label class="text-sm text-slate-400">Kendaraan (opsional)</label>
          <input v-model="form.vehicle_info.type" placeholder="motor / mobil"
                 class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 mt-1" />
        </div>
        <div>
          <label class="text-sm text-slate-400">Plat Nomor</label>
          <input v-model="form.vehicle_info.plate" placeholder="B 1234 ABC"
                 class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 mt-1" />
        </div>
      </div>

      <p v-if="error" class="text-rose-400 text-sm">{{ error }}</p>
      <p v-if="message" class="text-emerald-400 text-sm">{{ message }}</p>

      <button :disabled="saving" class="bg-amber-400 text-slate-900 rounded py-2 px-4 font-semibold disabled:opacity-50">
        {{ saving ? 'Menyimpan…' : 'Simpan Profil' }}
      </button>
    </form>
  </div>
</template>
