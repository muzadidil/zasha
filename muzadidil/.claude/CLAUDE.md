# Zasha Order Marketplace — Specification Document

> Spec lengkap untuk pembangunan platform marketplace jasa lokal Zasha.
> Dokumen ini menjadi acuan utama Claude Code for VS Code.

**Status:** MVP Specification v1.0
**Last Updated:** 13 Mei 2026

---

## 1. Project Overview

### Konsep
Marketplace jasa lokal mirip inDrive, tapi untuk 4 kategori jasa non-transportasi:
- **WFH** — Jasa pekerjaan remote (desain, coding, copywriting, dll)
- **Titip** — Jasa titip beli barang / antar
- **Tenaga** — Jasa tenaga manual (angkut, bersih, bangunan, kebun)
- **Service** — Jasa perbaikan (elektronik, kendaraan, perabot, dll)

### Model Bisnis
- **Matching Model: Race (First-Come-First-Served)**
- Pelanggan pasang harga awal → broadcast ke semua mitra dalam radius
- Mitra bisa langsung tekan "Terima" (tidak ada counter-offer)
- Mitra paling cepat tekan "Terima" yang dapat order
- Kalau tidak ada yang ambil → pelanggan tekan tombol (+) untuk naikkan harga manual
- Pelanggan & mitra komunikasi via WhatsApp setelah order claimed (WFH)

### Revenue Model
- Mitra punya saldo wallet (batas atas Rp 100.000, boleh di bawahnya — tidak ada saldo minimum untuk daftar/online)
- Saat claim order: saldo mitra **harus ≥ 5% × `current_price`** order tersebut
- Setiap order yang diterima mitra → potong **5% dari nilai order** dari saldo
- Top-up via **transfer manual** ke admin → admin approve manual (saldo setelah top-up tidak boleh melebihi Rp 100.000)
- Pembayaran order pelanggan ke mitra: **cash langsung** (Titip/Tenaga/Service) atau **transfer langsung** (WFH)
- Zasha tidak nyimpan uang pelanggan (no escrow) → aman dari regulasi PJP

---

## 2. Tech Stack

### Backend
```
PHP 8.2.12
Laravel 12.x (latest)
Composer 2.9.x
MySQL 8.0 (dengan spatial index)
Redis (cache + broadcasting)
Laravel Sanctum (API authentication)
Laravel Reverb (WebSocket native)
```

### Frontend
```
Vue 3 (Composition API)
Vite (build tool)
Pinia (state management)
Vue Router
Axios (HTTP client)
Laravel Echo (WebSocket client)
Tailwind CSS
```

### Coding Standards
Ikuti **Zasha Laravel Standard** (lihat `/mnt/skills/user/zasha-laravel-standard/SKILL.md`):
- Thin Controller — hanya terima request & return response
- Service Class Pattern untuk business logic
- Form Request mandatory untuk validasi
- Custom Exception dengan static factory method
- Format log konsisten untuk integrasi `kamus.zasha.online`

---

## 3. Deployment Structure

### Localhost (Development)
```
~/zasha-project/
├── backend/         ← Laravel 12 API
├── customer-app/    ← Vue 3 (pelanggan)
└── mitra-app/       ← Vue 3 (mitra)
```

### Production (Shared Hosting)
```
zasha.online              → public_html/           (Build Vue pelanggan)
mitra.zasha.online        → public_html/mitra/     (Build Vue mitra)
zasha.online/muzadidi     → public_html/muzadidi/  (Laravel API)
```

**⚠️ Catatan Keamanan Deploy:**
- File sensitif (`.env`, `vendor/`, `database/`) tidak boleh ter-expose ke web
- `.htaccess` di `public_html/muzadidi/` harus block akses ke folder selain `public/`
- Atau symlink `public_html/muzadidi/` → ke `public/` Laravel di luar `public_html`

---

## 4. Database Schema

### Total: 11 Tabel

#### 4.1. `service_categories`
| Field | Type | Notes |
|---|---|---|
| id | bigint | PK |
| slug | string | unique: `wfh`, `titip`, `tenaga`, `service` |
| name | string | label tampilan |
| min_price | integer | harga minimum |
| price_step | integer | nilai tombol (+/-) pelanggan |
| max_partners | tinyint | 1 untuk WFH/Titip/Service, 10 untuk Tenaga |
| requires_geolocation | boolean | true kecuali WFH |
| search_radius_km | integer | nullable untuk WFH |
| search_timeout_minutes | integer | timeout sebelum order expired |
| commission_percent | decimal(5,2) | default 5.00 |
| timestamps | | |

**Seed values:**
| slug | min_price | price_step | max_partners | radius_km | timeout |
|---|---|---|---|---|---|
| wfh | 50.000 | 10.000 | 1 | null | 1440 (24 jam) |
| titip | 10.000 | 5.000 | 1 | 5 | 10 menit |
| tenaga | 100.000 | 25.000 | 10 | 5 | 30 menit |
| service | 50.000 | 10.000 | 1 | 10 | 60 menit |

#### 4.2. `users` (extend default Laravel)
| Field | Type | Notes |
|---|---|---|
| id | bigint | PK |
| name | string | |
| email | string | unique, nullable |
| phone | string | unique, untuk login |
| password | string | |
| role | enum | `customer`, `partner`, `admin` |
| average_rating | decimal(3,2) | nullable, cache |
| rating_count | integer | default 0 |
| phone_verified_at | timestamp | nullable |
| timestamps | | |

#### 4.3. `partner_profiles`
| Field | Type | Notes |
|---|---|---|
| id | bigint | PK |
| user_id | bigint | FK → users |
| ktp_number | string | unique, encrypted |
| ktp_photo_url | string | |
| vehicle_info | json | nullable, untuk Titip/Tenaga |
| skills | json | array string, untuk WFH |
| service_categories | json | array slug yang dilayani |
| bank_name | string | untuk WFH transfer |
| bank_account | string | nullable |
| is_verified | boolean | default false |
| verified_at | timestamp | nullable |
| timestamps | | |

#### 4.4. `partner_locations`
| Field | Type | Notes |
|---|---|---|
| id | bigint | PK |
| user_id | bigint | FK → users, unique |
| coordinates | POINT | MySQL spatial, dengan SPATIAL INDEX |
| accuracy_meters | integer | nullable |
| is_online | boolean | default false |
| last_seen_at | timestamp | |
| timestamps | | |

#### 4.5. `partner_wallets`
| Field | Type | Notes |
|---|---|---|
| id | bigint | PK |
| user_id | bigint | FK → users, unique |
| balance | integer | dalam rupiah, batas atas 100.000 (boleh 0 atau di bawahnya, tidak ada minimum) |
| timestamps | | |

#### 4.6. `wallet_transactions` (audit trail, IMMUTABLE)
| Field | Type | Notes |
|---|---|---|
| id | bigint | PK |
| wallet_id | bigint | FK → partner_wallets |
| type | enum | `topup`, `fee`, `refund`, `adjustment` |
| amount | integer | positif = credit, negatif = debit |
| balance_after | integer | snapshot saldo setelah transaksi |
| reference_type | string | nullable, e.g. `App\Models\Order` |
| reference_id | string | nullable, ID order terkait |
| notes | string | nullable |
| created_at | timestamp | tidak ada updated_at (immutable) |

#### 4.7. `topup_requests`
| Field | Type | Notes |
|---|---|---|
| id | bigint | PK |
| wallet_id | bigint | FK |
| amount | integer | |
| proof_url | string | bukti transfer |
| status | enum | `pending`, `approved`, `rejected` |
| processed_by | bigint | FK admin user_id, nullable |
| processed_at | timestamp | nullable |
| rejection_reason | string | nullable |
| timestamps | | |

#### 4.8. `orders`
| Field | Type | Notes |
|---|---|---|
| id | ULID | PK, ULID lebih baik dari UUID untuk index |
| customer_id | bigint | FK → users |
| service_category_id | bigint | FK |
| partner_id | bigint | FK → users, nullable (assigned setelah claim) |
| details | json | spesifik per kategori (lihat 4.8.1) |
| current_price | integer | harga saat ini (bisa naik) |
| initial_price | integer | harga awal |
| pickup_location | POINT | spatial, nullable untuk WFH |
| destination_location | POINT | spatial, nullable |
| status | enum | `draft`, `searching`, `claimed`, `in_progress`, `completed`, `cancelled`, `expired` |
| expires_at | timestamp | dihitung dari `search_timeout_minutes` |
| claimed_at | timestamp | nullable |
| completed_at | timestamp | nullable |
| timestamps | | |

**Index:** composite `(status, service_category_id, current_price)`, spatial `pickup_location`

##### 4.8.1. Struktur `details` JSON per Kategori

**WFH:**
```json
{
  "task_title": "string, 10-200 char",
  "task_description": "string, 50-5000 char",
  "deadline": "datetime",
  "skills_required": ["array of string"],
  "attachment_urls": ["array of url, max 5"]
}
```

**Titip:**
```json
{
  "pickup_address": "string, max 500",
  "dropoff_address": "string, max 500",
  "estimated_weight": "numeric, 0.1-50 kg",
  "items": [
    {"name": "string", "qty": "integer", "estimated_price": "integer|null"}
  ],
  "notes": "string|null, max 1000"
}
```

**Tenaga:**
```json
{
  "job_type": "enum: angkut|bersih|bangunan|kebun|lainnya",
  "work_address": "string, max 500",
  "duration_hours": "integer, 1-12",
  "worker_count": "integer, 1-10",
  "start_at": "datetime",
  "tools_needed": ["array of string|null"],
  "description": "string, 20-2000 char"
}
```

**Service:**
```json
{
  "service_type": "enum: elektronik|kendaraan|perabot|plumbing|listrik|lainnya",
  "device_or_item": "string, max 200",
  "problem_description": "string, 30-3000 char",
  "location_type": "enum: on_site|dropoff",
  "service_address": "string|null (required if on_site)",
  "photos": ["array of url, max 5"]
}
```

#### 4.9. `order_claims`
| Field | Type | Notes |
|---|---|---|
| id | bigint | PK |
| order_id | ULID | FK |
| partner_id | bigint | FK → users |
| agreed_price | integer | harga saat claim |
| fee_amount | integer | 5% dari agreed_price |
| status | enum | `success`, `failed` (untuk audit race condition) |
| claimed_at | timestamp | |

#### 4.10. `order_status_logs` (audit trail)
| Field | Type | Notes |
|---|---|---|
| id | bigint | PK |
| order_id | ULID | FK |
| from_status | string | nullable (saat draft) |
| to_status | string | |
| changed_by | bigint | FK → users, nullable (kalau system) |
| reason | string | nullable |
| created_at | timestamp | immutable |

#### 4.11. `ratings`
| Field | Type | Notes |
|---|---|---|
| id | bigint | PK |
| order_id | ULID | FK |
| rater_id | bigint | FK → users (yang ngasih rating) |
| ratee_id | bigint | FK → users (yang dirating) |
| rater_role | enum | `customer`, `partner` |
| stars | tinyint | 1-5 |
| comment | string | nullable, max 300 |
| created_at | timestamp | |

**Unique constraint:** `(order_id, rater_id)` — 1 rating per order per pihak

---

## 5. State Machine Order

```
draft → searching → claimed → in_progress → completed
            ↓           ↓
         expired    cancelled
```

**Aturan Transisi:**
- `draft → searching`: saat pelanggan submit order
- `searching → claimed`: saat mitra menang race
- `searching → expired`: timeout tanpa ada yang claim
- `claimed → in_progress`: mitra mulai kerja / OTW
- `claimed → cancelled`: pelanggan atau mitra batal sebelum mulai
- `in_progress → completed`: mitra tekan "selesai"
- `in_progress → cancelled`: ada masalah di tengah jalan

Setiap transisi **wajib** dicatat di `order_status_logs`.

---

## 6. Business Logic & Flow

### 6.1. Order Creation Flow
1. Pelanggan pilih kategori jasa
2. Form input detail sesuai kategori (validasi via Form Request)
3. Pelanggan set harga awal (slider, min ditentukan kategori)
4. Set lokasi (kecuali WFH)
5. Submit → status `searching` → broadcast ke mitra dalam radius

### 6.2. Partner Notification Flow
1. Order baru muncul di feed mitra yang **online** + dalam radius + kategori yang dilayani
2. Push notification via Reverb event `OrderCreated`
3. Mitra lihat: detail lengkap, lokasi, harga, identitas pelanggan
4. Mitra tekan "Terima" → race ke atomic lock

### 6.3. Atomic Claim Flow
1. Validasi: mitra punya saldo ≥ 5% dari `current_price`
2. `DB::transaction` dengan `lockForUpdate()` pada `orders`:
   - Cek `status === 'searching'`
   - Cek `current_claims < max_partners`
   - Insert `order_claims`
   - Update `orders.partner_id` & `status`
   - Deduct saldo mitra & insert `wallet_transactions`
3. Broadcast `OrderClaimed` ke semua mitra (kecuali pemenang)
4. Frontend mitra lain: hapus order dari list, tampilkan banner

### 6.4. Price Update Flow
1. Pelanggan tekan tombol (+) → naik sesuai `price_step` kategori
2. Update `orders.current_price`
3. Broadcast event `OrderPriceUpdated` ke channel `order.{order_id}`
4. Frontend mitra yang sedang lihat order: update harga real-time

### 6.5. Timeout / Expiration Flow
- Scheduler (`php artisan schedule:run`) jalan tiap menit
- Cek orders dengan `status === 'searching'` dan `expires_at < now()`
- Update ke `status === 'expired'`
- Notifikasi pelanggan: "Tidak ada mitra yang ambil, silakan coba lagi dengan harga lebih tinggi"

### 6.6. Top-up Flow (Manual)
1. Mitra request top-up via form (input amount + upload bukti transfer)
2. Insert `topup_requests` dengan status `pending`
3. Admin lihat list pending di dashboard
4. Admin verifikasi bukti → approve / reject
5. Kalau approve:
   - Update `topup_requests.status` & `processed_at`
   - Update `partner_wallets.balance` (jangan melebihi 100.000)
   - Insert `wallet_transactions` (type: `topup`)
6. Notifikasi mitra

### 6.7. Rating Flow
1. Setelah `order.status === 'completed'`
2. Pelanggan & mitra masing-masing bisa kasih 1 rating
3. Validasi: hanya peserta order yang bisa rating, 1x per order
4. Setelah insert: update cache `users.average_rating` & `rating_count` (via Observer)

---

## 7. API Endpoint Plan

### Auth
```
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
POST   /api/auth/verify-phone
```

### Customer
```
POST   /api/customer/orders                    Create order
GET    /api/customer/orders                    List orders
GET    /api/customer/orders/{id}               Detail
PATCH  /api/customer/orders/{id}/increase-price
DELETE /api/customer/orders/{id}               Cancel
POST   /api/customer/orders/{id}/rate          Beri rating ke mitra
```

### Partner
```
GET    /api/partner/orders/available           List order di radius
POST   /api/partner/orders/{id}/claim          Tekan "Terima" (atomic)
PATCH  /api/partner/orders/{id}/start          Mulai kerja
PATCH  /api/partner/orders/{id}/complete       Selesai
POST   /api/partner/orders/{id}/rate           Beri rating ke pelanggan

GET    /api/partner/wallet                     Lihat saldo
POST   /api/partner/wallet/topup-request       Submit top-up
GET    /api/partner/wallet/transactions        Riwayat

PATCH  /api/partner/location                   Update GPS (frequent)
PATCH  /api/partner/online-status              Toggle online
```

### Admin
```
GET    /api/admin/topup-requests               List pending
POST   /api/admin/topup-requests/{id}/approve
POST   /api/admin/topup-requests/{id}/reject
GET    /api/admin/orders                       Monitoring semua order
GET    /api/admin/partners                     List mitra & verifikasi
POST   /api/admin/partners/{id}/verify
```

### Public
```
GET    /api/service-categories                 List kategori + config
```

---

## 8. Broadcasting Events (Laravel Reverb)

| Event | Channel | Listener |
|---|---|---|
| `OrderCreated` | `partners.{category}.{geo_hash}` | Mitra online di radius |
| `OrderClaimed` | `order.{order_id}` | Pelanggan & mitra lain |
| `OrderPriceUpdated` | `order.{order_id}` | Mitra yang sedang lihat |
| `OrderStatusChanged` | `order.{order_id}` | Pelanggan & mitra terpilih |
| `WalletBalanceUpdated` | `private-user.{user_id}` | Mitra |

---

## 9. Custom Exceptions

Buat custom exception per domain dengan static factory + `toKamusPayload()`:

```
App\Exceptions\Order\OrderException
  - invalidCategory()
  - priceBelowMinimum()
  - geolocationRequired()
  - alreadyClaimed()
  - notInSearchingState()

App\Exceptions\Wallet\WalletException
  - insufficientBalance()
  - exceedsMaxBalance()
  - topupAlreadyProcessed()

App\Exceptions\Partner\PartnerException
  - notVerified()
  - notInRadius()
  - notOnline()
  - categoryNotSupported()
```

Semua exception harus implement `toKamusPayload()` untuk dikirim ke `kamus.zasha.online`.

---

## 10. Security Checklist

### Wajib di MVP
- [ ] Form Request validation di semua endpoint mutating
- [ ] Sanitize input string (strip_tags, Str::squish)
- [ ] Rate limiting di route:
  - Auth: 5/minute
  - Order creation: 10/hour per user
  - Price increase: 30/hour per order
  - Partner claim: 60/minute per partner
- [ ] Mass assignment protection (`$fillable` di semua model)
- [ ] Eloquent / Query Builder (no raw SQL tanpa binding)
- [ ] CSRF protection (default Laravel)
- [ ] Sanctum token expiration (e.g., 7 hari)
- [ ] `lockForUpdate()` untuk semua operasi atomic (claim, top-up approve)
- [ ] Validasi geolocation: lat -90~90, lng -180~180
- [ ] KTP & nomor rekening: encrypted di DB

### Post-MVP
- [ ] Anti-fraud: tracking acceptance rate mitra
- [ ] Captcha untuk register
- [ ] 2FA untuk admin
- [ ] IP-based suspicious activity detection

---

## 11. Development Roadmap

### Phase 1: Foundation (Week 1-2)
- [ ] Setup Laravel 12 project + Sanctum + Reverb
- [ ] Setup 2 Vue 3 projects (customer + partner)
- [ ] Migration 11 tabel
- [ ] Seed `service_categories`
- [ ] Auth (register, login, logout, verify phone)
- [ ] Form Request validation lengkap

### Phase 2: Core Matching (Week 3-4)
- [ ] Order creation flow (semua kategori)
- [ ] Geolocation update mitra (frequent endpoint)
- [ ] Broadcast event `OrderCreated` ke radius
- [ ] Atomic claim service dengan `lockForUpdate`
- [ ] State machine + `order_status_logs`
- [ ] Wallet & fee deduction

### Phase 3: Real-time UX (Week 5)
- [ ] Frontend customer: slider harga + tombol (+) real-time
- [ ] Frontend mitra: feed order + button claim
- [ ] Broadcast `OrderClaimed`, `OrderPriceUpdated`, `OrderStatusChanged`
- [ ] Notification (toast / push)

### Phase 4: Top-up & Admin (Week 6)
- [ ] Top-up request flow
- [ ] Admin dashboard (approve top-up, verifikasi mitra)
- [ ] Wallet transaction history

### Phase 5: Rating & Polish (Week 7)
- [ ] Rating two-way
- [ ] Cache average rating via Observer
- [ ] UI polish, error handling, loading states

### Phase 6: Pre-launch (Week 8)
- [ ] Rate limiting
- [ ] Exception → kamus.zasha.online integration
- [ ] Testing manual end-to-end
- [ ] Deploy ke staging hosting

---

## 12. Backlog (Post-MVP)

- Mobile app (Flutter atau React Native)
- Push notification (FCM / OneSignal)
- In-app chat (replace WhatsApp untuk WFH)
- Anti-fraud system (acceptance rate tracking)
- Payment gateway integration (Midtrans/Xendit)
- Subscription model untuk mitra
- Multi-language (ID/EN)
- Search & filter mitra (untuk WFH)
- Portfolio mitra (untuk WFH)
- Dispute resolution flow
- Refund mechanism
- Analytics dashboard
- Promo code / bonus system
- Referral system

---

## 13. Catatan untuk Claude Code

- Selalu ikuti **Zasha Laravel Standard** (di SKILL file)
- **Thin Controller** — business logic di Service
- **Form Request mandatory** — jangan validasi di Controller/Service
- **Custom Exception** dengan static factory + `toKamusPayload()`
- **Format log konsisten** untuk `kamus.zasha.online`
- Penjelasan & komentar code: **Bahasa Inggris**
- Pesan user-facing (error message, label): **Bahasa Indonesia**
- Test cases pakai **Pest** (default Laravel 12)
- Database query: **Eloquent / Query Builder**, NO raw SQL tanpa binding
