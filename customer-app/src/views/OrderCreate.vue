<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api';
import OsmMap from '../components/OsmMap.vue';
import { useCategoriesStore } from '../stores/categories';

const route = useRoute();
const router = useRouter();
const categories = useCategoriesStore();

const selectedSlug = ref('');
const error = ref('');
const fieldErrors = ref({});
const submitting = ref(false);

const pickup = ref(null);
const price = ref(0);
const details = reactive({});
const skillsInput = ref('');

const category = computed(() => categories.bySlug(selectedSlug.value));
const prefilledFromReorder = ref(false);

watch(category, (c) => {
  if (!c) return;
  if (prefilledFromReorder.value) {
    prefilledFromReorder.value = false;
    return;
  }
  price.value = c.min_price;
});

watch(selectedSlug, (slug) => {
  if (prefilledFromReorder.value) return;
  Object.keys(details).forEach((k) => delete details[k]);
  Object.assign(details, defaultDetails(slug));
}, { immediate: false });

onMounted(async () => {
  await categories.load();
  const slug = route.query.slug;

  if (slug && categories.bySlug(slug)) {
    // Reorder pre-fill: seed details + price BEFORE assigning selectedSlug so the
    // watcher above does not wipe them with defaults / min_price.
    if (route.query.details || route.query.suggested_price) {
      prefilledFromReorder.value = true;
      try {
        const parsed = route.query.details ? JSON.parse(route.query.details) : defaultDetails(slug);
        Object.keys(details).forEach((k) => delete details[k]);
        Object.assign(details, parsed);
      } catch (_) {
        Object.assign(details, defaultDetails(slug));
      }
      if (route.query.suggested_price) {
        const suggested = Number(route.query.suggested_price);
        if (!Number.isNaN(suggested) && suggested > 0) price.value = suggested;
      }
    }
    selectedSlug.value = slug;
  }

  if (route.query.lat && route.query.lng) {
    pickup.value = [Number(route.query.lat), Number(route.query.lng)];
  } else if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition((pos) => {
      pickup.value = [pos.coords.latitude, pos.coords.longitude];
    });
  }
});

function increase() { if (category.value) price.value += category.value.price_step; }
function decrease() { if (category.value && price.value - category.value.price_step >= category.value.min_price) price.value -= category.value.price_step; }

function defaultDetails(slug) {
  switch (slug) {
    case 'wfh':
      return { task_title: '', task_description: '', deadline: '', skills_required: [], attachment_urls: [] };
    case 'titip':
      return { pickup_address: '', dropoff_address: '', estimated_weight: 1, items: [{ name: '', qty: 1, estimated_price: null }], notes: '' };
    case 'tenaga':
      return { job_type: 'angkut', work_address: '', duration_hours: 2, worker_count: 1, start_at: '', tools_needed: [], description: '' };
    case 'service':
      return { service_type: 'elektronik', device_or_item: '', problem_description: '', location_type: 'on_site', service_address: '', photos: [] };
    default:
      return {};
  }
}

function onMapClick(e) {
  if (!e?.latlng) return;
  pickup.value = [e.latlng.lat, e.latlng.lng];
}

function addSkill() {
  const v = skillsInput.value.trim();
  if (!v) return;
  if (!Array.isArray(details.skills_required)) details.skills_required = [];
  if (!details.skills_required.includes(v)) details.skills_required.push(v);
  skillsInput.value = '';
}

function removeSkill(s) {
  details.skills_required = (details.skills_required ?? []).filter((x) => x !== s);
}

async function submit() {
  error.value = '';
  fieldErrors.value = {};
  submitting.value = true;
  try {
    const payload = {
      service_category_slug: selectedSlug.value,
      initial_price: price.value,
      details: { ...details },
      ...(category.value?.requires_geolocation && pickup.value
        ? { pickup_lat: pickup.value[0], pickup_lng: pickup.value[1] }
        : {}),
    };
    const { data } = await api.post('/customer/orders', payload);
    router.push(`/orders/${data.data.id}`);
  } catch (e) {
    const errs = e.response?.data?.error?.data?.errors;
    if (errs && typeof errs === 'object') {
      fieldErrors.value = errs;
      console.warn('[CreateOrder] validation errors:', errs);
      const first = Object.values(errs)[0];
      error.value = Array.isArray(first) ? first[0] : (e.response?.data?.error?.message ?? 'Data yang dikirim tidak valid.');
    } else {
      console.warn('[CreateOrder] request failed:', e.response?.data ?? e);
      error.value = e.response?.data?.error?.message ?? 'Gagal membuat order.';
    }
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div class="space-y-4">
    <button class="text-sm text-slate-500" @click="router.back()">← Kembali</button>
    <h1 class="text-xl font-semibold">{{ category?.name ?? 'Pesan Jasa' }}</h1>

    <div v-if="!selectedSlug" class="bg-white rounded shadow p-4">
      <label class="text-sm text-slate-600">Kategori</label>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-2">
        <button
          v-for="c in categories.items"
          :key="c.id"
          type="button"
          @click="selectedSlug = c.slug"
          class="border rounded py-2 text-sm border-slate-200"
        >{{ c.name }}</button>
      </div>
    </div>

    <div v-if="category?.requires_geolocation" class="bg-white rounded shadow p-4 space-y-2">
      <label class="text-sm text-slate-600">Konfirmasi lokasi pickup</label>
      <OsmMap :center="pickup ?? [-6.2, 106.8]" :pickup="pickup" @click="onMapClick" height="240px" />
      <p class="text-xs text-slate-500">Klik di peta untuk geser titik.</p>
    </div>

    <div v-if="category" class="bg-white rounded shadow p-4">
      <label class="text-sm text-slate-600">Harga</label>
      <div class="flex items-center justify-between mt-2">
        <button type="button" @click="decrease" class="bg-slate-100 rounded-full w-10 h-10 text-xl">−</button>
        <div class="text-2xl font-semibold">Rp {{ price.toLocaleString('id-ID') }}</div>
        <button type="button" @click="increase" class="bg-slate-100 rounded-full w-10 h-10 text-xl">+</button>
      </div>
      <p class="text-xs text-slate-500 mt-2">
        Minimum Rp {{ category.min_price.toLocaleString('id-ID') }} · step Rp {{ category.price_step.toLocaleString('id-ID') }}
      </p>
    </div>

    <div v-if="selectedSlug" class="bg-white rounded shadow p-4 space-y-2">
      <label class="text-sm text-slate-600">Detail</label>
      <template v-if="selectedSlug === 'wfh'">
        <input v-model="details.task_title" placeholder="Judul tugas (10-200 karakter)"
               minlength="10" maxlength="200" class="w-full border rounded p-2" />
        <p v-if="fieldErrors['details.task_title']" class="text-xs text-rose-600">{{ fieldErrors['details.task_title'][0] }}</p>

        <textarea v-model.lazy="details.task_description" placeholder="Deskripsi tugas (min 50 karakter)"
                  minlength="50" maxlength="5000" rows="4" class="w-full border rounded p-2"></textarea>
        <p v-if="fieldErrors['details.task_description']" class="text-xs text-rose-600">{{ fieldErrors['details.task_description'][0] }}</p>

        <input v-model="details.deadline" type="datetime-local"
               class="w-full border rounded p-2" />
        <p v-if="fieldErrors['details.deadline']" class="text-xs text-rose-600">{{ fieldErrors['details.deadline'][0] }}</p>

        <div>
          <label class="text-xs text-slate-500">Skill yang dibutuhkan (min 1)</label>
          <div class="flex gap-2 mt-1">
            <input v-model="skillsInput" @keydown.enter.prevent="addSkill" placeholder="mis. Laravel"
                   class="flex-1 border rounded p-2" />
            <button type="button" @click="addSkill" class="bg-slate-200 rounded px-3">+ Tambah</button>
          </div>
          <div v-if="details.skills_required?.length" class="flex flex-wrap gap-1 mt-2">
            <span v-for="s in details.skills_required" :key="s"
                  class="bg-indigo-50 text-indigo-700 text-xs rounded px-2 py-1">
              {{ s }} <button type="button" @click="removeSkill(s)" class="ml-1">×</button>
            </span>
          </div>
          <p v-if="fieldErrors['details.skills_required']" class="text-xs text-rose-600 mt-1">
            {{ fieldErrors['details.skills_required'][0] }}
          </p>
        </div>
      </template>

      <input v-if="selectedSlug === 'titip'" v-model="details.pickup_address" placeholder="Alamat pickup"
             class="w-full border rounded p-2" />
      <input v-if="selectedSlug === 'titip'" v-model="details.dropoff_address" placeholder="Alamat antar"
             class="w-full border rounded p-2" />
      <textarea v-if="selectedSlug === 'titip'" v-model.lazy="details.notes" placeholder="Catatan (opsional)"
                class="w-full border rounded p-2"></textarea>

      <select v-if="selectedSlug === 'tenaga'" v-model="details.job_type" class="w-full border rounded p-2">
        <option value="angkut">Angkut</option>
        <option value="bersih">Bersih-bersih</option>
        <option value="bangunan">Bangunan</option>
        <option value="kebun">Kebun</option>
        <option value="lainnya">Lainnya</option>
      </select>
      <input v-if="selectedSlug === 'tenaga'" v-model="details.work_address" placeholder="Alamat lokasi kerja"
             class="w-full border rounded p-2" />
      <textarea v-if="selectedSlug === 'tenaga'" v-model.lazy="details.description" placeholder="Deskripsi (min 20 char)"
                class="w-full border rounded p-2"></textarea>

      <select v-if="selectedSlug === 'service'" v-model="details.service_type" class="w-full border rounded p-2">
        <option value="elektronik">Elektronik</option>
        <option value="kendaraan">Kendaraan</option>
        <option value="perabot">Perabot</option>
        <option value="plumbing">Plumbing</option>
        <option value="listrik">Listrik</option>
        <option value="lainnya">Lainnya</option>
      </select>
      <input v-if="selectedSlug === 'service'" v-model="details.device_or_item" placeholder="Barang/perangkat"
             class="w-full border rounded p-2" />
      <textarea v-if="selectedSlug === 'service'" v-model.lazy="details.problem_description" placeholder="Deskripsi masalah (min 30 char)"
                class="w-full border rounded p-2"></textarea>
    </div>

    <p v-if="error" class="text-rose-600 text-sm">{{ error }}</p>
    <button @click="submit" :disabled="submitting || !selectedSlug" class="w-full bg-indigo-600 text-white rounded py-3 disabled:opacity-50">
      {{ submitting ? 'Mengirim…' : 'Cari Mitra' }}
    </button>
  </div>
</template>
