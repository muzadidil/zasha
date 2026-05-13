# Zasha Filament Admin Panel — Specification

> Spec lengkap admin panel untuk Zasha Order Marketplace.
> Dibangun dengan Filament v5 di Laravel 12.

**Status:** Admin Panel Specification v1.0
**Last Updated:** 13 Mei 2026
**Dependencies:** ZASHA_ORDER_SPEC.md (parent spec)

---

## 1. Overview

Admin panel internal Zasha untuk monitoring dan manajemen marketplace. Akses via `/admin` dengan login Filament. Hanya user `role=admin` yang bisa akses.

### Tech Stack
```
Filament v5.6.3
Laravel 12
Livewire 4
Tailwind CSS (built-in Filament)
```

### Panel URL Structure
```
/admin                    → Dashboard (stats widgets)
/admin/pelanggan          → UserResource (filter customer)
/admin/mitra              → PartnerResource (filter partner)
/admin/orders             → OrderResource (monitoring)
/admin/topup-requests     → TopupRequestResource
/admin/service-categories → ServiceCategoryResource
/admin/wallet-transactions → WalletTransactionResource (audit)
/admin/ratings            → RatingResource (audit)
```

---

## 2. Authentication & Authorization

### Login Page
- Default Filament login (`/admin/login`)
- Hanya terima user dengan `role = admin`

### Authorization
Tambahkan ke `app/Models/User.php`:

```php
public function canAccessPanel(\Filament\Panel $panel): bool
{
    return $this->role === 'admin';
}
```

### Logout
Default Filament logout button di top-right.

---

## 3. Dashboard (Default Page)

URL: `/admin`

### Widgets yang Wajib

#### 3.1. Stats Overview (`StatsOverviewWidget`)
4 stat cards di atas:
- **Total Orders Hari Ini** — count `orders` where `created_at` today
- **Mitra Online** — count `partner_locations` where `is_online = true` and `last_seen_at > now() - 5 minutes`
- **Pending Top-up** — count `topup_requests` where `status = pending`
- **Revenue Hari Ini** — sum `wallet_transactions.amount` where `type = fee` and today (absolute value)

#### 3.2. Orders Chart (`ChartWidget`)
Line chart: orders per hari (7 hari terakhir), grouped by status.

#### 3.3. Latest Orders Table (`TableWidget`)
5 order terbaru, kolom: ID, customer, kategori, status, harga, created_at.

#### 3.4. Latest Top-up Requests (`TableWidget`)
5 top-up terbaru yang pending.

---

## 4. Resources Detail

### 4.1. UserResource (Pelanggan)

**Path:** `app/Filament/Resources/UserResource.php`

**Navigation:**
- Label: "Pelanggan"
- Icon: `heroicon-o-users`
- Group: "Pengguna"
- Sort: 1

**Eloquent Query Override:**
```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->where('role', 'customer');
}
```

**Table Columns:**
- `id` (sortable)
- `name` (searchable)
- `email` (searchable, copyable)
- `phone` (searchable)
- `average_rating` (badge color berdasarkan nilai)
- `rating_count`
- `created_at` (since format)

**Filters:**
- Date range: `created_at`
- Rating range: `average_rating`

**Actions per Row:**
- View (default)
- Block/Unblock (custom action, set `blocked_at` — perlu tambah kolom nanti)

**Bulk Actions:**
- Export ke Excel (kalau diperlukan)

**Form (Edit Only — Pelanggan tidak bisa dibuat dari admin):**
- name (required)
- email (email, unique except self)
- phone (unique except self, nullable)
- Hanya edit, tidak ada create

---

### 4.2. PartnerResource (Mitra)

**Path:** `app/Filament/Resources/PartnerResource.php`

**Navigation:**
- Label: "Mitra"
- Icon: `heroicon-o-briefcase`
- Group: "Pengguna"
- Sort: 2

**Eloquent Query:**
```php
parent::getEloquentQuery()
    ->where('role', 'partner')
    ->with(['partnerProfile', 'partnerWallet', 'partnerLocation'])
```

**Table Columns:**
- `id`
- `name` (searchable)
- `email` (searchable)
- `phone` (searchable)
- `partnerProfile.is_verified` (badge: ✅ Verified / ❌ Belum)
- `partnerWallet.balance` (format rupiah, color jika < 5000)
- `partnerLocation.is_online` (badge: 🟢 Online / ⚫ Offline)
- `average_rating` (badge)
- `created_at`

**Filters:**
- Verification status (verified / not_verified / all)
- Online status (online / offline / all)
- Service category (multi-select dari `partner_profiles.service_categories` JSON)

**Actions per Row:**
- View detail (default)
- **Verify** action — kalau belum verified:
  - Konfirmasi modal
  - Call `AdminPartnerService::verify($partner)` (existing service)
  - Show success notification
- **Reject Verification** action — kalau verified:
  - Konfirmasi modal
  - Set `is_verified = false`
- View masked KTP/Bank (pakai `PartnerProfileResource` masking — JANGAN expose raw)

**View Page (Detail):**
Tampilkan:
- Info Dasar: name, email, phone, registered date
- Profil Mitra:
  - KTP number (MASKED: `*********3456`)
  - KTP photo (clickable to enlarge)
  - Vehicle info (JSON formatted)
  - Skills (badges)
  - Service categories yang dilayani (badges)
  - Bank name + account (MASKED: `*****7890`)
  - Bank holder name
  - Verification status + verified_at + verified_by
- Wallet:
  - Current balance
  - Total top-up
  - Total fee paid
  - Link ke wallet transactions
- Location:
  - Last seen
  - Online status
  - Map preview (kalau coordinates ada)
- Stats:
  - Total orders claimed
  - Total orders completed
  - Average rating
  - Rating count

**Form (Edit Only):**
- name, email, phone (basic info)
- is_verified (toggle, dengan konfirmasi)
- Trigger verify/unverify via toggle yang panggil service

---

### 4.3. OrderResource (Monitoring)

**Path:** `app/Filament/Resources/OrderResource.php`

**Navigation:**
- Label: "Orders"
- Icon: `heroicon-o-shopping-bag`
- Group: "Transaksi"
- Sort: 1

**Read-Only Notes:**
- Admin **tidak boleh** buat/edit/delete order — cuma monitoring
- Disable create button
- Disable edit form (atau gunakan `View` only)

**Table Columns:**
- `id` (ULID, copyable, truncated 8 char)
- `customer.name` (relation, searchable)
- `serviceCategory.name`
- `partner.name` (relation, nullable, searchable)
- `current_price` (format rupiah)
- `status` (badge color berdasarkan status)
- `created_at` (since)
- `expires_at` (nullable, only show if status=searching)

**Filters:**
- Status (multi-select enum)
- Service category (relation select)
- Date range: created_at
- Has partner (yes/no)

**Actions per Row:**
- View detail
- Cancel order (admin force) — kalau status `searching` atau `claimed`

**View Page:**
- Order details lengkap
- Customer info (link ke UserResource)
- Partner info (link ke PartnerResource, kalau ada)
- Price history (initial → current)
- Pickup/destination map (Leaflet/OSM)
- Details JSON (formatted)
- Status logs timeline (dari `order_status_logs`)
- Claims history (dari `order_claims`)
- Ratings (kalau completed)

---

### 4.4. TopupRequestResource

**Path:** `app/Filament/Resources/TopupRequestResource.php`

**Navigation:**
- Label: "Top-up Mitra"
- Icon: `heroicon-o-banknotes`
- Group: "Transaksi"
- Sort: 2
- Badge: count pending (real-time)

**Table Columns:**
- `id`
- `wallet.user.name` (mitra, searchable)
- `amount` (format rupiah)
- `proof_url` (button "Lihat Bukti" → modal image)
- `status` (badge)
- `created_at`
- `processed_at`
- `processed_by.name`

**Filters:**
- Status (pending/approved/rejected)
- Date range

**Actions per Row (untuk status `pending`):**
- **Approve** action:
  - Modal konfirmasi
  - Tampilkan: nama mitra, amount, current balance, balance after approve
  - Call `TopupService::approve($request)` (existing)
  - Cek: balance + amount tidak boleh > 100.000
  - Notif success
- **Reject** action:
  - Modal dengan field "Alasan penolakan" (textarea required)
  - Call `TopupService::reject($request, $reason)`

**Actions untuk status `approved` / `rejected`:**
- View only

---

### 4.5. ServiceCategoryResource

**Path:** `app/Filament/Resources/ServiceCategoryResource.php`

**Navigation:**
- Label: "Kategori Jasa"
- Icon: `heroicon-o-tag`
- Group: "Pengaturan"
- Sort: 1

**Full CRUD:**
- List, Create, Edit, Delete (soft, atau pakai is_active flag)

**Table Columns:**
- `slug` (badge)
- `name`
- `min_price` (rupiah)
- `price_step` (rupiah)
- `max_partners`
- `requires_geolocation` (icon)
- `search_radius_km`
- `search_timeout_minutes`
- `commission_percent` (%)
- `is_active` (toggle)

**Form Fields:**
- slug (string, unique, readonly after create)
- name (string, required)
- min_price (number, required, in rupiah)
- price_step (number, required)
- max_partners (number, 1-50)
- requires_geolocation (toggle)
- search_radius_km (number, required if requires_geolocation)
- search_timeout_minutes (number, required)
- commission_percent (number, 0-100, decimal)
- is_active (toggle)

**Delete Protection:**
- Tidak boleh delete kategori yang masih punya orders aktif.
- Pakai soft delete atau toggle `is_active = false`.

---

### 4.6. WalletTransactionResource (Audit Trail)

**Path:** `app/Filament/Resources/WalletTransactionResource.php`

**Navigation:**
- Label: "Riwayat Wallet"
- Icon: `heroicon-o-clipboard-document-list`
- Group: "Transaksi"
- Sort: 3

**Read-Only — IMMUTABLE:**
- Disable create, edit, delete
- Cuma view & filter

**Table Columns:**
- `id`
- `wallet.user.name` (mitra, searchable)
- `type` (badge: topup/fee/refund/adjustment)
- `amount` (format rupiah, color: green positive, red negative)
- `balance_after` (rupiah)
- `reference_type` + `reference_id` (link kalau bisa)
- `notes`
- `created_at`

**Filters:**
- Type (multi-select)
- Date range
- Mitra (relation select)
- Amount range

**Export:**
- Excel export untuk audit eksternal

---

### 4.7. RatingResource (Audit)

**Path:** `app/Filament/Resources/RatingResource.php`

**Navigation:**
- Label: "Rating"
- Icon: `heroicon-o-star`
- Group: "Transaksi"
- Sort: 4

**Read-Only.**

**Table Columns:**
- `id`
- `order_id` (truncated, link ke order)
- `rater.name` (yang ngasih rating)
- `ratee.name` (yang dirating)
- `rater_role` (badge: customer/partner)
- `stars` (visual stars 1-5)
- `comment` (truncated 50 char, tooltip full)
- `created_at`

**Filters:**
- Stars range
- Rater role
- Date range
- Has comment (yes/no)

---

## 5. Navigation Groups

Filament panel sidebar:

```
📊 Dashboard

👥 Pengguna
   ├─ Pelanggan
   └─ Mitra

🛒 Transaksi
   ├─ Orders
   ├─ Top-up Mitra
   ├─ Riwayat Wallet
   └─ Rating

⚙️ Pengaturan
   └─ Kategori Jasa
```

---

## 6. Security Requirements

### 6.1. Data Masking (WAJIB)

KTP dan rekening bank **TIDAK BOLEH** ditampilkan raw di mana pun di admin panel.

Gunakan resource/method existing yang sudah masking:
- KTP: `*********3456` (12 digit awal masked)
- Rekening: `*****7890` (digit awal masked)

### 6.2. Action Authorization

Tambahkan policy:
```php
// app/Policies/PartnerPolicy.php
public function verify(User $user, User $partner): bool
{
    return $user->role === 'admin' && $partner->role === 'partner';
}
```

Lalu di Filament action:
```php
->authorize('verify', $record)
```

### 6.3. Audit Trail

Setiap admin action (verify mitra, approve top-up, cancel order, ubah kategori) **wajib** di-log via:
- `order_status_logs` untuk perubahan status order
- `wallet_transactions` untuk perubahan saldo
- Tambah `admin_audit_logs` table kalau mau lengkap (post-MVP)

---

## 7. Performance Notes

### 7.1. Eager Loading
Selalu eager load relasi yang ditampilkan di table:
```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->with(['partnerProfile', 'partnerWallet', 'partnerLocation']);
}
```

### 7.2. Cache Heavy Widgets
Dashboard stats widget di-cache 30 detik:
```php
protected static ?string $pollingInterval = '30s';
```

### 7.3. Pagination
Default 25 per page. Naikkan ke 50 untuk tabel yang banyak diakses.

---

## 8. Implementation Order

Urutan generate untuk Claude Code:

1. **Setup base** — pastikan `User::canAccessPanel()` return `role === 'admin'`
2. **ServiceCategoryResource** — paling sederhana, no relations
3. **UserResource** (Pelanggan) — simple filter
4. **PartnerResource** (Mitra) — kompleks, dengan view detail + actions
5. **OrderResource** — monitoring, read-only
6. **TopupRequestResource** — dengan actions
7. **WalletTransactionResource** — audit trail
8. **RatingResource** — audit
9. **Dashboard Widgets** — paling akhir, butuh data dari resources

---

## 9. Testing Checklist

Setelah generate, test manual:

- [ ] Login admin → masuk dashboard
- [ ] Non-admin user mencoba akses `/admin` → ditolak (403)
- [ ] Dashboard widgets tampil dengan data benar
- [ ] List pelanggan filter `role=customer` only
- [ ] List mitra filter `role=partner` only, KTP & rekening masked
- [ ] Detail mitra tampilkan info lengkap dengan masking
- [ ] Verify mitra → trigger AdminPartnerService::verify
- [ ] Approve top-up → balance bertambah, audit trail tercatat
- [ ] Cap saldo 100.000 dihormati
- [ ] Reject top-up → status = rejected, reason tersimpan
- [ ] Create/edit kategori jasa → tersimpan
- [ ] Disable kategori → orders baru tidak bisa pakai kategori itu
- [ ] Monitoring order → tampil semua status, filter berfungsi
- [ ] Wallet transactions tidak bisa di-edit/delete (immutable)
- [ ] Cancel order admin → status = cancelled, log tersimpan
- [ ] Rate limit Filament login bekerja

---

## 10. Catatan untuk Claude Code

- **Pakai resource & service existing** — jangan duplikasi logic
  - `AdminPartnerService` untuk verify
  - `TopupService` untuk approve/reject
  - `PartnerProfileResource` masking
- **Thin Resource** — bisnis logic tetap di Service class
- **Custom Action** harus panggil Service, bukan langsung manipulate model
- **Resource icon** pakai Heroicons (`heroicon-o-*` untuk outline, `heroicon-s-*` untuk solid)
- **Notification** pakai Filament built-in: `Notification::make()->title('...')->success()->send()`
- **Test setiap resource** dengan login admin sebelum lanjut ke resource berikutnya
- **Cache Filament components** setelah selesai: `php artisan filament:cache-components`
