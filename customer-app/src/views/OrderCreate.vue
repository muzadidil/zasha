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
const submitting = ref(false);

const pickup = ref(null);
const price = ref(0);
const details = reactive({});

const category = computed(() => categories.bySlug(selectedSlug.value));

watch(category, (c) => {
  if (c) price.value = c.min_price;
});

watch(selectedSlug, (slug) => {
  Object.keys(details).forEach((k) => delete details[k]);
  Object.assign(details, defaultDetails(slug));
}, { immediate: false });

onMounted(async () => {
  await categories.load();
  const slug = route.query.slug;
  if (slug && categories.bySlug(slug)) selectedSlug.value = slug;
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

async function submit() {
  error.value = '';
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
    error.value = e.response?.data?.error?.message ?? 'Gagal membuat order.';
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
      <input v-if="selectedSlug === 'wfh'" v-model="details.task_title" placeholder="Judul tugas"
             class="w-full border rounded p-2" />
      <textarea v-if="selectedSlug === 'wfh'" v-model.lazy="details.task_description" placeholder="Deskripsi tugas (min 50 char)"
                class="w-full border rounded p-2"></textarea>
      <input v-if="selectedSlug === 'wfh'" v-model="details.deadline" type="datetime-local"
             class="w-full border rounded p-2" />

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
