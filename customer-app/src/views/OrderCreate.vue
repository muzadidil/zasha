<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { MinusIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import api from '../api';
import OsmMap from '../components/OsmMap.vue';
import AppHeader from '../components/ui/AppHeader.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import CategoryIcon from '../components/ui/CategoryIcon.vue';
import { useToast } from '../composables/useToast';
import { useCategoriesStore } from '../stores/categories';

const route = useRoute();
const router = useRouter();
const categories = useCategoriesStore();
const toast = useToast();

const selectedSlug = ref('');
const fieldErrors = ref({});
const submitting = ref(false);

const pickup = ref(null);
const price = ref(0);
const details = reactive({});
const skillsInput = ref('');

const category = computed(() => categories.bySlug(selectedSlug.value));
// See: counter prevents racing watchers from clobbering reorder prefill.
const prefilledFromReorder = ref(0);

watch(category, (c) => {
  if (!c) return;
  if (prefilledFromReorder.value > 0) { prefilledFromReorder.value--; return; }
  price.value = c.min_price;
});

watch(selectedSlug, (slug) => {
  if (prefilledFromReorder.value > 0) { prefilledFromReorder.value--; return; }
  Object.keys(details).forEach((k) => delete details[k]);
  Object.assign(details, defaultDetails(slug));
}, { immediate: false });

onMounted(async () => {
  await categories.load();
  const slug = route.query.slug;
  if (slug && categories.bySlug(slug)) {
    if (route.query.details || route.query.suggested_price) {
      prefilledFromReorder.value = 2;
      try {
        const parsed = route.query.details ? JSON.parse(route.query.details) : defaultDetails(slug);
        Object.keys(details).forEach((k) => delete details[k]);
        Object.assign(details, parsed);
      } catch (_) {
        Object.assign(details, defaultDetails(slug));
      }
      if (route.query.suggested_price) {
        const sp = Number(route.query.suggested_price);
        if (!Number.isNaN(sp) && sp > 0) price.value = sp;
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
function decrease() {
  if (category.value && price.value - category.value.price_step >= category.value.min_price) {
    price.value -= category.value.price_step;
  }
}

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

const fieldError = (key) => fieldErrors.value[key]?.[0] ?? '';

async function submit() {
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
    toast.success('Order dibuat, mencari mitra…');
    router.push(`/orders/${data.data.id}`);
  } catch (e) {
    const errs = e.response?.data?.error?.data?.errors;
    if (errs && typeof errs === 'object') {
      fieldErrors.value = errs;
      const first = Object.values(errs)[0];
      toast.error(Array.isArray(first) ? first[0] : 'Periksa kembali isian form.');
    } else {
      toast.error(e.response?.data?.error?.message ?? 'Gagal membuat order.');
    }
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <AppHeader :title="category?.name ?? 'Pesan Jasa'" back />

    <div class="max-w-md mx-auto sm:max-w-2xl px-5 py-4 space-y-4">
      <!-- Category picker (when no slug given) -->
      <BaseCard v-if="!selectedSlug">
        <h2 class="text-sm font-semibold text-ink mb-3">Pilih kategori</h2>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="c in categories.items"
            :key="c.id"
            @click="selectedSlug = c.slug"
            class="flex items-center gap-2 p-3 rounded-btn border border-slate-200 hover:border-brand-400 hover:bg-brand-50 transition-colors"
          >
            <CategoryIcon :slug="c.slug" size="sm" />
            <span class="text-sm font-semibold">{{ c.name }}</span>
          </button>
        </div>
      </BaseCard>

      <!-- Selected category card -->
      <BaseCard v-if="category" padded>
        <div class="flex items-center gap-3">
          <CategoryIcon :slug="category.slug" />
          <div class="flex-1">
            <div class="font-semibold text-ink">{{ category.name }}</div>
            <div class="text-xs text-ink-soft">
              Mulai Rp {{ category.min_price.toLocaleString('id-ID') }} · timeout {{ category.search_timeout_minutes }} menit
            </div>
          </div>
        </div>
      </BaseCard>

      <!-- Pickup location map -->
      <BaseCard v-if="category?.requires_geolocation" padded>
        <h3 class="text-sm font-semibold text-ink mb-2">Lokasi pickup</h3>
        <OsmMap :center="pickup ?? [-8.17, 113.70]" :pickup="pickup" :height="'200px'" @click="onMapClick" />
        <p class="text-xs text-ink-soft mt-2">Klik di peta untuk menggeser titik pickup.</p>
      </BaseCard>

      <!-- Price -->
      <BaseCard v-if="category" padded>
        <h3 class="text-sm font-semibold text-ink mb-3">Harga tawaran</h3>
        <div class="flex items-center justify-between bg-slate-50 rounded-card px-3 py-3">
          <button
            type="button"
            @click="decrease"
            class="w-11 h-11 grid place-items-center bg-white shadow-card rounded-full active:scale-95 transition-transform"
            aria-label="Turunkan harga"
          ><MinusIcon class="w-5 h-5 text-ink" /></button>

          <div class="text-center">
            <div class="text-[10px] uppercase font-semibold text-ink-soft tracking-wide">Tawaran</div>
            <div class="text-2xl font-bold text-ink mt-0.5">Rp {{ price.toLocaleString('id-ID') }}</div>
          </div>

          <button
            type="button"
            @click="increase"
            class="w-11 h-11 grid place-items-center bg-brand-600 hover:bg-brand-700 rounded-full active:scale-95 transition-transform"
            aria-label="Naikkan harga"
          ><PlusIcon class="w-5 h-5 text-white" /></button>
        </div>
        <p class="text-xs text-ink-soft mt-2 text-center">
          Minimum Rp {{ category.min_price.toLocaleString('id-ID') }} · step Rp {{ category.price_step.toLocaleString('id-ID') }}
        </p>
      </BaseCard>

      <!-- WFH detail -->
      <BaseCard v-if="selectedSlug === 'wfh'" padded>
        <h3 class="text-sm font-semibold text-ink mb-3">Detail tugas</h3>
        <div class="space-y-3">
          <BaseInput v-model="details.task_title" label="Judul tugas (10-200 karakter)" :error="fieldError('details.task_title')" required />
          <BaseInput v-model="details.task_description" label="Deskripsi tugas (min 50 karakter)" :error="fieldError('details.task_description')" required />
          <BaseInput v-model="details.deadline" type="datetime-local" label="Deadline" :error="fieldError('details.deadline')" required />

          <div>
            <label class="text-xs font-semibold text-ink-soft">Skill yang dibutuhkan</label>
            <div class="flex gap-2 mt-1.5">
              <input
                v-model="skillsInput"
                @keydown.enter.prevent="addSkill"
                placeholder="mis. Laravel"
                class="flex-1 h-11 px-3 rounded-btn border border-slate-300 text-sm focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
              />
              <BaseButton type="button" variant="secondary" size="md" @click="addSkill">Tambah</BaseButton>
            </div>
            <div v-if="details.skills_required?.length" class="flex flex-wrap gap-1.5 mt-2">
              <span v-for="s in details.skills_required" :key="s" class="inline-flex items-center gap-1 bg-brand-50 text-brand-700 text-xs rounded-full px-2.5 py-1">
                {{ s }}
                <button type="button" @click="removeSkill(s)" class="hover:text-brand-900"><XMarkIcon class="w-3 h-3" /></button>
              </span>
            </div>
            <p v-if="fieldError('details.skills_required')" class="text-xs text-rose-600 mt-1">{{ fieldError('details.skills_required') }}</p>
          </div>
        </div>
      </BaseCard>

      <!-- Titip detail -->
      <BaseCard v-if="selectedSlug === 'titip'" padded>
        <h3 class="text-sm font-semibold text-ink mb-3">Detail titip</h3>
        <div class="space-y-3">
          <BaseInput v-model="details.pickup_address" label="Alamat pickup" :error="fieldError('details.pickup_address')" required />
          <BaseInput v-model="details.dropoff_address" label="Alamat antar" :error="fieldError('details.dropoff_address')" required />
          <BaseInput v-model="details.notes" label="Catatan (opsional)" :error="fieldError('details.notes')" />
        </div>
      </BaseCard>

      <!-- Tenaga detail -->
      <BaseCard v-if="selectedSlug === 'tenaga'" padded>
        <h3 class="text-sm font-semibold text-ink mb-3">Detail pekerjaan</h3>
        <div class="space-y-3">
          <div>
            <label class="text-xs font-semibold text-ink-soft">Jenis pekerjaan</label>
            <select v-model="details.job_type" class="mt-1 w-full h-12 px-3 rounded-btn border border-slate-300 text-sm bg-white">
              <option value="angkut">Angkut</option>
              <option value="bersih">Bersih-bersih</option>
              <option value="bangunan">Bangunan</option>
              <option value="kebun">Kebun</option>
              <option value="lainnya">Lainnya</option>
            </select>
          </div>
          <BaseInput v-model="details.work_address" label="Alamat lokasi" :error="fieldError('details.work_address')" required />
          <BaseInput v-model="details.description" label="Deskripsi (min 20 karakter)" :error="fieldError('details.description')" required />
        </div>
      </BaseCard>

      <!-- Service detail -->
      <BaseCard v-if="selectedSlug === 'service'" padded>
        <h3 class="text-sm font-semibold text-ink mb-3">Detail service</h3>
        <div class="space-y-3">
          <div>
            <label class="text-xs font-semibold text-ink-soft">Jenis service</label>
            <select v-model="details.service_type" class="mt-1 w-full h-12 px-3 rounded-btn border border-slate-300 text-sm bg-white">
              <option value="elektronik">Elektronik</option>
              <option value="kendaraan">Kendaraan</option>
              <option value="perabot">Perabot</option>
              <option value="plumbing">Plumbing</option>
              <option value="listrik">Listrik</option>
              <option value="lainnya">Lainnya</option>
            </select>
          </div>
          <BaseInput v-model="details.device_or_item" label="Barang / perangkat" :error="fieldError('details.device_or_item')" required />
          <BaseInput v-model="details.problem_description" label="Deskripsi masalah (min 30 karakter)" :error="fieldError('details.problem_description')" required />
        </div>
      </BaseCard>

      <!-- Submit -->
      <div v-if="selectedSlug" class="sticky bottom-4 z-10 pt-2">
        <BaseButton :loading="submitting" :disabled="submitting" block size="lg" @click="submit">
          {{ submitting ? 'Mengirim…' : 'Cari mitra sekarang' }}
        </BaseButton>
      </div>
    </div>
  </div>
</template>
