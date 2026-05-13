<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import { useCategoriesStore } from '../stores/categories';

const router = useRouter();
const categories = useCategoriesStore();
const selectedSlug = ref('');
const error = ref('');
const submitting = ref(false);

const location = reactive({ pickup_lat: null, pickup_lng: null, destination_lat: null, destination_lng: null });
const price = ref(0);
const details = reactive({});

const category = computed(() => categories.bySlug(selectedSlug.value));

watch(category, (c) => {
  if (c) price.value = c.min_price;
});

onMounted(async () => {
  await categories.load();
  if (!selectedSlug.value && categories.items.length) selectedSlug.value = categories.items[0].slug;
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

watch(selectedSlug, (slug) => {
  Object.keys(details).forEach((k) => delete details[k]);
  Object.assign(details, defaultDetails(slug));
}, { immediate: false });

async function useMyLocation() {
  if (!navigator.geolocation) return;
  navigator.geolocation.getCurrentPosition((pos) => {
    location.pickup_lat = pos.coords.latitude;
    location.pickup_lng = pos.coords.longitude;
  });
}

async function submit() {
  error.value = '';
  submitting.value = true;
  try {
    const payload = {
      service_category_slug: selectedSlug.value,
      initial_price: price.value,
      details: { ...details },
      ...(category.value?.requires_geolocation ? { pickup_lat: location.pickup_lat, pickup_lng: location.pickup_lng } : {}),
      ...(location.destination_lat ? { destination_lat: location.destination_lat, destination_lng: location.destination_lng } : {}),
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
    <h1 class="text-xl font-semibold">Pesan Jasa</h1>

    <div class="bg-white rounded shadow p-4">
      <label class="text-sm text-slate-600">Kategori</label>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-2">
        <button
          v-for="c in categories.items"
          :key="c.id"
          type="button"
          @click="selectedSlug = c.slug"
          :class="['border rounded py-2 text-sm', selectedSlug === c.slug ? 'border-indigo-600 bg-indigo-50' : 'border-slate-200']"
        >{{ c.name }}</button>
      </div>
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

    <div v-if="category?.requires_geolocation" class="bg-white rounded shadow p-4">
      <label class="text-sm text-slate-600">Lokasi pickup</label>
      <div class="flex gap-2 mt-2 items-center">
        <input v-model.number="location.pickup_lat" type="number" step="any" placeholder="Lat" class="border rounded px-2 py-1 w-32" />
        <input v-model.number="location.pickup_lng" type="number" step="any" placeholder="Lng" class="border rounded px-2 py-1 w-32" />
        <button type="button" @click="useMyLocation" class="text-sm text-indigo-600">Gunakan lokasi saya</button>
      </div>
    </div>

    <details v-if="selectedSlug" class="bg-white rounded shadow p-4">
      <summary class="cursor-pointer text-sm text-slate-600">Detail order</summary>
      <textarea v-model.lazy="details.task_description" v-if="selectedSlug === 'wfh'" placeholder="Deskripsi tugas" class="mt-2 w-full border rounded p-2"></textarea>
      <textarea v-model.lazy="details.description" v-else-if="selectedSlug === 'tenaga'" placeholder="Deskripsi pekerjaan" class="mt-2 w-full border rounded p-2"></textarea>
      <textarea v-model.lazy="details.notes" v-else-if="selectedSlug === 'titip'" placeholder="Catatan" class="mt-2 w-full border rounded p-2"></textarea>
      <textarea v-model.lazy="details.problem_description" v-else-if="selectedSlug === 'service'" placeholder="Deskripsi masalah" class="mt-2 w-full border rounded p-2"></textarea>
    </details>

    <p v-if="error" class="text-rose-600 text-sm">{{ error }}</p>
    <button @click="submit" :disabled="submitting" class="w-full bg-indigo-600 text-white rounded py-3 disabled:opacity-50">
      {{ submitting ? 'Mengirim…' : 'Kirim Order' }}
    </button>
  </div>
</template>
