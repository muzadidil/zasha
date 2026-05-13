# Zasha V2 Refactor + Filament Admin Panel — Combined Spec

> Spec gabungan untuk: (1) refactor backend ke model search bertahap, (2) build Filament admin panel.
> Dibangun di atas project existing yang sudah punya 56 Pest tests passing.

**Status:** Combined Specification v2.0
**Last Updated:** 13 Mei 2026
**Dependencies:** ZASHA_ORDER_SPEC.md (parent — semua keputusan dasar)

---

## 1. Perubahan V2 (Apa yang Berubah dari Existing)

### Tabel Perubahan Konsep

| Aspek | Existing (V1) | Target (V2) |
|---|---|---|
| Order timeout | 10/30/60 menit + 24 jam (WFH) | **1 menit untuk semua** |
| Broadcast strategy | Langsung ke max radius | **Bertahap 1→2→3→4 km** (kecuali WFH nasional) |
| Step interval | N/A | **15 detik per step** |
| UI awal pelanggan | Form input dulu | **Map + kategori dulu, baru input** |
| Map pelanggan saat search | Tidak ada visualisasi | **Animasi radius circle expanding** |
| Posisi mitra di map pelanggan | Tidak ada | **Hanya muncul setelah claimed** |
| Cancel order saat search | Belum ada | **Boleh kapan saja sebelum claimed** |
| Mitra baru online di tengah search | Tidak dapat broadcast | **Dapat broadcast kalau masuk radius aktif** |

### Yang TIDAK Berubah (Tetap Pakai)

- ✅ Atomic claim via `lockForUpdate()`
- ✅ Two-way rating (customer ↔ partner)
- ✅ Wallet system & 5% fee deduction
- ✅ Top-up manual + admin approve
- ✅ KTP & bank account encryption
- ✅ Sanctum authentication
- ✅ Reverb broadcasting
- ✅ Custom exceptions dengan `toKamusPayload()`
- ✅ 49+ existing Pest tests harus tetap passing setelah refactor

---

## 2. Database Migrations (V2)

### 2.1. Update `service_categories`

Buat migration baru: `2026_05_14_100001_add_v2_columns_to_service_categories_table.php`

```php
Schema::table('service_categories', function (Blueprint $table) {
    // Step radius config untuk search bertahap (JSON: [1, 2, 3, 4])
    // null = WFH (nasional, langsung broadcast)
    $table->json('radius_steps')->nullable()->after('search_radius_km');
    
    // Durasi tiap step dalam detik (default 15)
    $table->unsignedSmallInteger('step_duration_seconds')->default(15)
        ->after('radius_steps');
});
```

Lalu update seeder `ServiceCategorySeeder`:

```php
[
    'slug' => 'wfh',
    'search_timeout_minutes' => 1,  // 1 menit
    'search_radius_km' => null,      // nasional
    'radius_steps' => null,           // tidak ada step
    'step_duration_seconds' => 60,    // langsung 60 detik tunggu
    // ... field lain
],
[
    'slug' => 'titip',
    'search_timeout_minutes' => 1,
    'search_radius_km' => 4,           // max
    'radius_steps' => [1, 2, 3, 4],    // step
    'step_duration_seconds' => 15,
],
[
    'slug' => 'tenaga',
    'search_timeout_minutes' => 1,
    'search_radius_km' => 4,
    'radius_steps' => [1, 2, 3, 4],
    'step_duration_seconds' => 15,
],
[
    'slug' => 'service',
    'search_timeout_minutes' => 1,
    'search_radius_km' => 4,
    'radius_steps' => [1, 2, 3, 4],
    'step_duration_seconds' => 15,
],
```

### 2.2. Update `orders`

Buat migration: `2026_05_14_100002_add_v2_columns_to_orders_table.php`

```php
Schema::table('orders', function (Blueprint $table) {
    // Radius aktif sekarang (dalam km)
    // null untuk WFH atau setelah claimed
    $table->unsignedSmallInteger('active_radius_km')->nullable()
        ->after('destination_location');
    
    // Step index sekarang (0 = 1km, 1 = 2km, 2 = 3km, 3 = 4km)
    // null untuk WFH atau setelah claimed
    $table->unsignedTinyInteger('current_step_index')->nullable()
        ->after('active_radius_km');
});
```

### 2.3. Buat Tabel `order_radius_expansions` (Audit Trail)

Migration: `2026_05_14_100003_create_order_radius_expansions_table.php`

```php
Schema::create('order_radius_expansions', function (Blueprint $table) {
    $table->id();
    $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
    $table->unsignedSmallInteger('from_radius_km')->nullable();
    $table->unsignedSmallInteger('to_radius_km');
    $table->unsignedTinyInteger('step_index');
    $table->unsignedSmallInteger('partners_notified');  // berapa mitra dapat broadcast
    $table->timestamp('expanded_at')->useCurrent();
    
    $table->index('order_id');
});
```

### 2.4. Update `users` (Tambah Field Block)

Optional: untuk admin bisa block pelanggan/mitra.

```php
Schema::table('users', function (Blueprint $table) {
    $table->timestamp('blocked_at')->nullable()->after('phone_verified_at');
    $table->string('blocked_reason', 500)->nullable()->after('blocked_at');
});
```

---

## 3. Backend Refactor (V2)

### 3.1. Service Classes Baru / Refactor

#### `OrderBroadcastService` (REFACTOR)

Lokasi: `app/Services/Order/OrderBroadcastService.php`

```php
class OrderBroadcastService
{
    public function __construct(
        private readonly PartnerLocationService $locationService,
    ) {}

    /**
     * Mulai broadcasting bertahap untuk order baru.
     * Dipanggil saat OrderCreationService selesai create order.
     */
    public function startStagedBroadcast(Order $order): void
    {
        if (!$order->serviceCategory->requires_geolocation) {
            // WFH: langsung broadcast ke semua mitra WFH
            $this->broadcastNational($order);
            return;
        }

        // Geo categories: mulai dari step 0 (radius pertama)
        $this->expandToStep($order, stepIndex: 0);
    }

    /**
     * Expand search ke radius berikutnya.
     * Dipanggil oleh ExpandSearchRadiusJob.
     */
    public function expandToStep(Order $order, int $stepIndex): void
    {
        $steps = $order->serviceCategory->radius_steps;
        
        if (!isset($steps[$stepIndex])) {
            return;  // sudah max radius
        }

        $radiusKm = $steps[$stepIndex];
        $previousRadius = $order->active_radius_km;

        // Query mitra yang masuk radius BARU
        // (mitra yang sudah dapat broadcast di step sebelumnya tidak perlu di-broadcast ulang)
        $partners = $this->locationService->findPartnersInRadius(
            order: $order,
            radiusKm: $radiusKm,
            excludePreviousRadius: $previousRadius,
        );

        // Update order
        $order->update([
            'active_radius_km' => $radiusKm,
            'current_step_index' => $stepIndex,
        ]);

        // Audit trail
        OrderRadiusExpansion::create([
            'order_id' => $order->id,
            'from_radius_km' => $previousRadius,
            'to_radius_km' => $radiusKm,
            'step_index' => $stepIndex,
            'partners_notified' => $partners->count(),
        ]);

        // Broadcast event ke pelanggan (untuk animasi map)
        broadcast(new OrderRadiusExpanded($order));

        // Broadcast event ke mitra baru
        foreach ($partners as $partner) {
            broadcast(new OrderAvailableForPartner($order, $partner));
        }

        // Schedule expansion ke step berikutnya (kalau ada)
        if (isset($steps[$stepIndex + 1])) {
            $delay = $order->serviceCategory->step_duration_seconds;
            ExpandSearchRadiusJob::dispatch($order, $stepIndex + 1)
                ->delay(now()->addSeconds($delay));
        }
    }

    /**
     * Broadcast nasional untuk WFH (langsung ke semua mitra).
     */
    public function broadcastNational(Order $order): void
    {
        $partners = User::where('role', 'partner')
            ->whereHas('partnerProfile', function ($q) {
                $q->where('is_verified', true)
                    ->whereJsonContains('service_categories', 'wfh');
            })
            ->get();

        foreach ($partners as $partner) {
            broadcast(new OrderAvailableForPartner($order, $partner));
        }
    }
}
```

#### `PartnerLocationService::findPartnersInRadius` (UPDATE)

Tambah parameter `excludePreviousRadius` untuk hanya return mitra yang **baru masuk** di radius ini:

```php
public function findPartnersInRadius(
    Order $order,
    int $radiusKm,
    ?int $excludePreviousRadius = null,
): Collection {
    if (!$order->pickup_location) {
        return collect();
    }

    $lat = $order->pickup_location->getLat();
    $lng = $order->pickup_location->getLng();

    $query = User::where('role', 'partner')
        ->whereHas('partnerProfile', fn ($q) => $q
            ->where('is_verified', true)
            ->whereJsonContains('service_categories', $order->serviceCategory->slug)
        )
        ->whereHas('partnerLocation', fn ($q) => $q
            ->where('is_online', true)
            ->where('last_seen_at', '>', now()->subMinutes(5))
            ->whereRaw(
                'ST_Distance_Sphere(coordinates, POINT(?, ?)) <= ?',
                [$lng, $lat, $radiusKm * 1000]
            )
        );

    // Exclude mitra yang sudah dapat broadcast di radius sebelumnya
    if ($excludePreviousRadius) {
        $query->whereHas('partnerLocation', fn ($q) => $q
            ->whereRaw(
                'ST_Distance_Sphere(coordinates, POINT(?, ?)) > ?',
                [$lng, $lat, $excludePreviousRadius * 1000]
            )
        );
    }

    return $query->with('partnerLocation')->get();
}
```

### 3.2. Queued Job: `ExpandSearchRadiusJob`

Lokasi: `app/Jobs/ExpandSearchRadiusJob.php`

```php
class ExpandSearchRadiusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly int $stepIndex,
    ) {}

    public function handle(OrderBroadcastService $broadcastService): void
    {
        // Refresh order dari DB (status mungkin sudah berubah)
        $order = $this->order->fresh();

        // Cek apakah order masih dalam status searching
        if ($order->status !== 'searching') {
            return;  // Sudah claimed/cancelled/expired, abort
        }

        $broadcastService->expandToStep($order, $this->stepIndex);
    }
}
```

### 3.3. Update `OrderCreationService`

Setelah create order, panggil `OrderBroadcastService::startStagedBroadcast()` instead of broadcast langsung:

```php
public function create(OrderCreationData $data): Order
{
    return DB::transaction(function () use ($data) {
        $order = Order::create([
            // ... fields
            'status' => 'searching',
            'expires_at' => now()->addMinutes(1),  // V2: 1 menit
        ]);

        // V2: mulai broadcasting bertahap
        $this->broadcastService->startStagedBroadcast($order);

        return $order;
    });
}
```

### 3.4. Update `OrderClaimService`

Saat order claimed, **STOP** queued job expansion yang masih pending:

```php
public function claim(string $orderId, int $partnerId): OrderClaim
{
    return DB::transaction(function () use ($orderId, $partnerId) {
        $order = Order::lockForUpdate()->findOrFail($orderId);

        // ... existing validation & claim logic

        // V2: clear radius info
        $order->update([
            'status' => 'claimed',
            'partner_id' => $partnerId,
            'claimed_at' => now(),
            'active_radius_km' => null,
            'current_step_index' => null,
        ]);

        // V2: broadcast OrderClaimed ke semua channel
        // Frontend pelanggan & mitra otomatis dapat update
        broadcast(new OrderClaimed($order));

        return $claim;
    });
}
```

### 3.5. New Broadcasting Events

#### `OrderRadiusExpanded`

```php
class OrderRadiusExpanded implements ShouldBroadcast
{
    public function __construct(public readonly Order $order) {}

    public function broadcastOn(): array
    {
        return [new Channel("order.{$this->order->id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'active_radius_km' => $this->order->active_radius_km,
            'current_step_index' => $this->order->current_step_index,
        ];
    }
}
```

#### `OrderAvailableForPartner` (replace existing OrderCreated)

```php
class OrderAvailableForPartner implements ShouldBroadcast
{
    public function __construct(
        public readonly Order $order,
        public readonly User $partner,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("partner.{$this->partner->id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'category' => $this->order->serviceCategory->slug,
            'current_price' => $this->order->current_price,
            'details' => $this->order->details,
            'pickup' => $this->order->pickup_location?->toArray(),
            'distance_km' => $this->order->active_radius_km,
        ];
    }
}
```

### 3.6. Scheduler Update

Update command `orders:expire` agar fire setiap 10 detik (bukan 1 menit) karena timeout sekarang cuma 1 menit:

```php
// app/Console/Commands/ExpireOrdersCommand.php
public function handle(): void
{
    Order::where('status', 'searching')
        ->where('expires_at', '<', now())
        ->each(function (Order $order) {
            $order->update([
                'status' => 'expired',
                'active_radius_km' => null,
                'current_step_index' => null,
            ]);
            
            broadcast(new OrderExpired($order));
        });
}

// routes/console.php
Schedule::command('orders:expire')->everyTenSeconds();
```

### 3.7. Cancel Order Endpoint (Customer)

```php
// app/Http/Controllers/Api/Customer/OrderController.php
public function cancel(string $orderId, OrderCancellationService $service): JsonResponse
{
    $order = Order::where('id', $orderId)
        ->where('customer_id', auth()->id())
        ->whereIn('status', ['searching'])  // V2: hanya boleh saat searching
        ->firstOrFail();

    $service->cancelByCustomer($order);

    return response()->json(['message' => 'Order dibatalkan']);
}
```

```php
// app/Services/Order/OrderCancellationService.php
public function cancelByCustomer(Order $order): void
{
    DB::transaction(function () use ($order) {
        $order->update([
            'status' => 'cancelled',
            'active_radius_km' => null,
            'current_step_index' => null,
        ]);

        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => 'searching',
            'to_status' => 'cancelled',
            'changed_by' => auth()->id(),
            'reason' => 'Dibatalkan pelanggan',
        ]);

        broadcast(new OrderCancelled($order));
    });
}
```

### 3.8. New API Endpoints

```
DELETE  /api/customer/orders/{id}/cancel    Cancel saat searching
```

---

## 4. Frontend Refactor (V2)

### 4.1. Customer App Flow (Vue 3)

**Halaman utama (home):**
```
┌─────────────────────────────────┐
│   Map (OSM tile, full screen)   │
│                                  │
│        [📍 Lokasi saya]          │
│                                  │
│  ┌──────────────────────────┐   │
│  │ Mau order apa?           │   │
│  │ [WFH] [Titip] [Tenaga]   │   │
│  │ [Service]                │   │
│  └──────────────────────────┘   │
└─────────────────────────────────┘
```

**Pilih kategori → Form input:**
- Detail spesifik kategori
- Set harga (slider min-max sesuai kategori)
- Konfirmasi lokasi (kecuali WFH)

**Submit → Searching screen:**
```
┌─────────────────────────────────┐
│   Map dengan animasi circle      │
│   yang melebar (1→2→3→4 km)      │
│                                  │
│        📍 Posisi Anda             │
│         ○                         │
│      ○    ○     <-- radius expand│
│    ○        ○                    │
│                                  │
│   Mencari mitra...               │
│   [Naikkan Harga (+)] [Cancel]   │
│   ⏱️ 45 detik tersisa             │
└─────────────────────────────────┘
```

**Setelah claimed:**
```
┌─────────────────────────────────┐
│   Map: posisi pelanggan +        │
│   posisi mitra (real-time)       │
│                                  │
│   ┌──────────────────────────┐   │
│   │ ✅ Mitra: Budi (⭐ 4.8)  │   │
│   │ ETA: 5 menit              │   │
│   │ [Chat WA] [Cancel]        │   │
│   └──────────────────────────┘   │
└─────────────────────────────────┘
```

**Reactive bindings (Pinia store):**
```javascript
// stores/order.js
state: () => ({
  currentOrder: null,
  activeRadiusKm: null,
  currentStepIndex: null,
  partnerLocation: null,  // hanya setelah claimed
})

actions: {
  subscribeToOrderChannel(orderId) {
    Echo.channel(`order.${orderId}`)
      .listen('OrderRadiusExpanded', (e) => {
        this.activeRadiusKm = e.active_radius_km
        this.currentStepIndex = e.current_step_index
      })
      .listen('OrderClaimed', (e) => {
        this.currentOrder.status = 'claimed'
        this.currentOrder.partner = e.partner
      })
  }
}
```

### 4.2. Mitra App Flow (Vue 3)

**Halaman utama (feed orders):**
- Tidak banyak berubah dari V1
- Listen channel `partner.{partner_id}` untuk `OrderAvailableForPartner`
- Order baru pop-up dengan sound notification
- Distance ditampilkan (dari `distance_km`)

**Saat tekan "Terima":**
- POST `/api/partner/orders/{id}/claim`
- Race ke atomic lock
- Menang → redirect ke halaman tracking order
- Kalah → toast error "Order sudah diambil mitra lain"

---

## 5. Filament Admin Panel

### 5.1. Setup Base

`User::canAccessPanel()`:
```php
public function canAccessPanel(\Filament\Panel $panel): bool
{
    return $this->role === 'admin' && !$this->blocked_at;
}
```

### 5.2. Dashboard

URL: `/admin`

#### Widgets:

**`OrdersStatsWidget` (StatsOverviewWidget):**
- Total Orders Hari Ini
- Mitra Online
- Pending Top-up (badge)
- Revenue Hari Ini (komisi 5%)
- **V2 Extra:** Avg Search Time (rata-rata berapa detik order claimed)

**`LiveOrdersTableWidget`:** 10 order paling baru dengan status

**`PartnerOnlineMapWidget`:** Map dengan dot posisi mitra online (mirip Gojek dashboard) — optional, post-MVP

---

### 5.3. Resources Detail

Sama persis seperti **FILAMENT_ADMIN_SPEC.md**, dengan tambahan V2:

#### Tambahan untuk `ServiceCategoryResource`:
Form fields baru:
- `radius_steps` (TagsInput atau Repeater untuk array) — nullable
- `step_duration_seconds` (Number) — default 15

#### Tambahan untuk `OrderResource` view page:
- **Section "Search Progress":**
  - Active radius sekarang (kalau status `searching`)
  - Current step index
  - Timeline radius expansion (dari `order_radius_expansions`)
  - Total mitra yang ter-broadcast di tiap step
- **Section "Status Logs":**
  - Timeline status transition
  - Reason kalau ada

#### Tambahan: `OrderRadiusExpansionResource`
Optional read-only resource untuk audit trail expansion.

Bisa diakses dari OrderResource detail saja, tidak perlu di sidebar.

---

## 6. Implementation Order (Step-by-Step)

### Phase A: Backend V2 Refactor

1. **Migration**: tambah kolom `radius_steps`, `step_duration_seconds` di `service_categories`
2. **Migration**: tambah kolom `active_radius_km`, `current_step_index` di `orders`
3. **Migration**: buat tabel `order_radius_expansions`
4. **Migration**: tambah `blocked_at`, `blocked_reason` di `users`
5. **Update seeder** `ServiceCategorySeeder` dengan timeout 1 menit + radius_steps
6. **Refactor** `OrderCreationService` (timeout, expires_at)
7. **Buat** `OrderBroadcastService` dengan `startStagedBroadcast` & `expandToStep`
8. **Buat** `ExpandSearchRadiusJob`
9. **Buat events**: `OrderRadiusExpanded`, `OrderAvailableForPartner`, `OrderCancelled`
10. **Refactor** `OrderClaimService` untuk clear radius & broadcast `OrderClaimed`
11. **Buat** `OrderCancellationService` & endpoint `DELETE /api/customer/orders/{id}/cancel`
12. **Update** scheduler `orders:expire` jadi `everyTenSeconds()`
13. **Update existing tests** + buat test baru untuk V2 features

### Phase B: Frontend Customer

14. Refactor halaman home: map + kategori picker (bukan form)
15. Pisahkan halaman input detail per kategori (4 form components)
16. Buat halaman search dengan animasi radius circle expanding
17. Subscribe `OrderRadiusExpanded`, `OrderClaimed`, `OrderCancelled` events
18. Tombol cancel saat searching
19. Halaman tracking setelah claimed (map dengan mitra)

### Phase C: Frontend Mitra

20. Update event listener jadi `OrderAvailableForPartner`
21. Pastikan setelah claim, semua app mitra lain dapat update via `OrderClaimed`
22. Update UI untuk tampilkan distance dari `distance_km`

### Phase D: Filament Admin Panel

23. `User::canAccessPanel()` + check `!blocked_at`
24. `ServiceCategoryResource` (full CRUD dengan V2 fields)
25. `UserResource` (Pelanggan, filter role=customer, action Block)
26. `PartnerResource` (Mitra, dengan masking & Verify action)
27. `OrderResource` (monitoring, dengan section Search Progress + Status Logs)
28. `TopupRequestResource` (Approve/Reject)
29. `WalletTransactionResource` (read-only audit)
30. `RatingResource` (read-only audit)
31. Dashboard widgets

### Phase E: Testing & Polish

32. Run all Pest tests, fix regression
33. Manual end-to-end test:
    - Customer buat order → search bertahap → mitra terima
    - Customer cancel saat searching
    - Order expired (tidak ada mitra)
    - Admin verify mitra, approve top-up
34. `php artisan filament:cache-components`
35. `php artisan optimize`

---

## 7. Testing Checklist

### Backend
- [ ] Migration jalan tanpa error, rollback bersih
- [ ] Seeder isi 4 kategori dengan timeout 1 menit
- [ ] Order create dengan `expires_at = now() + 1 menit`
- [ ] WFH order: langsung broadcast nasional, tanpa step
- [ ] Geo order: step 0 (1km) broadcast, lalu setelah 15 detik step 1 (2km), dst
- [ ] Mitra di radius lebih kecil tidak dapat broadcast 2x (excludePreviousRadius works)
- [ ] Mitra baru online di tengah search dapat broadcast (kalau masuk radius aktif)
- [ ] Customer cancel saat searching → status `cancelled`, queued job ke step berikutnya tidak fire
- [ ] Order expired → status `expired`, broadcast `OrderExpired`
- [ ] Atomic claim tetap bekerja (race condition)
- [ ] Wallet fee deduction 5% tetap jalan
- [ ] Semua 49+ Pest tests passing (V1) + tests baru V2 passing

### Frontend Customer
- [ ] Home: map + kategori picker tampil
- [ ] Pilih kategori → form input sesuai kategori
- [ ] Submit → masuk halaman search
- [ ] Animasi radius circle melebar tiap 15 detik
- [ ] Timer countdown 1 menit
- [ ] Tombol cancel berfungsi
- [ ] Tombol naikkan harga berfungsi (rebroadcast)
- [ ] Setelah claimed: posisi mitra muncul di map
- [ ] Setelah claimed: tombol Chat WA muncul

### Frontend Mitra
- [ ] Notif order pop-up dengan sound
- [ ] Distance ditampilkan
- [ ] Tekan terima → menang/kalah race handled
- [ ] Order claimed mitra lain → notif "Order sudah diambil"

### Admin Panel
- [ ] Login admin → masuk dashboard
- [ ] Non-admin akses `/admin` → 403
- [ ] Dashboard widgets tampil data benar
- [ ] Filter pelanggan (role=customer) jalan
- [ ] Filter mitra (role=partner) jalan
- [ ] KTP & rekening masked di list & detail
- [ ] Verify mitra → trigger service
- [ ] Approve top-up → balance bertambah, cap 100k dihormati
- [ ] Order monitoring tampilkan radius expansion timeline
- [ ] Wallet transactions immutable (tidak bisa edit/delete)
- [ ] Rate limit Filament login jalan

---

## 8. Catatan untuk Claude Code

### Wajib Diikuti

1. **Backward compatibility**: V1 tests harus tetap passing. Kalau ada test yang break karena V2, fix test-nya, jangan skip.

2. **Jangan duplikasi logic**: 
   - `OrderBroadcastService` adalah satu-satunya tempat broadcast logic
   - `OrderClaimService` untuk atomic claim
   - `OrderCancellationService` untuk cancel logic

3. **Job cancellation handling**: 
   - `ExpandSearchRadiusJob` HARUS cek `$order->fresh()->status === 'searching'` sebelum eksekusi
   - Kalau sudah claimed/cancelled/expired, return tanpa do anything

4. **Atomic claim tetap pakai `lockForUpdate()`** — JANGAN ubah jadi optimistic locking.

5. **Filament resource pakai service existing**:
   - Verify mitra → `AdminPartnerService::verify`
   - Approve top-up → `TopupService::approve`
   - Cancel order admin → `OrderCancellationService::cancelByAdmin`

6. **Frontend route**:
   - Customer subscribe `order.{order_id}` channel (public)
   - Mitra subscribe `partner.{partner_id}` channel (private, butuh auth)

7. **Map di Frontend**: pakai `flutter_map` style (OSM tile) untuk Vue juga via `vue3-leaflet` atau `@vue-leaflet/vue-leaflet`. Jangan pakai Google Maps di Vue (mahal).

8. **WhatsApp share link**: gunakan format `https://wa.me/{phone}?text={message}` — tidak perlu API.

### Optional / Backlog

- App version check endpoint (untuk Flutter nanti)
- File upload endpoint untuk KTP/foto (kalau belum ada)
- FCM push notification (untuk Flutter)
- Refresh token mechanism (untuk Flutter)
- API versioning `/api/v1/*` (kalau belum ada)

---

## 9. File Output Setelah Selesai

Setelah Phase E selesai, hasilnya:

```
✅ Backend Laravel:
   - 49 V1 tests passing
   - 15-20 V2 tests passing (estimated)
   - 28+ API endpoints (tambahan: cancel order)
   - Filament admin panel di /admin

✅ Customer App Vue:
   - Map-first home page
   - Search animation
   - Cancel during search
   - Tracking after claimed

✅ Mitra App Vue:
   - Update event listener
   - Distance display

✅ Database:
   - 13 tabel (11 V1 + 2 V2: order_radius_expansions, plus column updates)

✅ Documentation:
   - ZASHA_ORDER_SPEC.md (V1 parent)
   - ZASHA_V2_COMBINED_SPEC.md (this file)
```
