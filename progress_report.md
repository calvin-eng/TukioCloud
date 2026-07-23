# TukioCloud — Progress Report

Generated: 2026-07-21

---

## Phase 1: Laravel + Breeze + Volt (Auth Scaffolding)

**Status: ✅ Built & Verified**

TukioCloud is a fresh Laravel 13 project with Laravel Breeze (Livewire + Volt stack). Auth scaffolding is fully functional:

| Feature | File(s) | Notes |
|---------|---------|-------|
| Login | `routes/auth.php:8`, Volt page `livewire/pages/auth/login.blade.php` | Uses `Volt::route()` |
| Register | `routes/auth.php:9`, Volt page | Uses `Volt::route()` |
| Password Reset | `routes/auth.php:14-17`, Volt page | Uses `Volt::route()` |
| Email Verification | `routes/auth.php:22`, Volt page | Uses `Volt::route()` |
| Profile Management | `routes/web.php:15`, Volt pages under `livewire/profile/` | Standard Breeze profile pages |

Layout files: `resources/views/layouts/app.blade.php` (authenticated), `resources/views/layouts/guest.blade.php` (unauthenticated).

---

## Phase 2: Multi-Tenant with Spatie Permissions

**Status: ✅ Built & Verified**

### Database (Migrations)
| Migration | Tables Created |
|-----------|---------------|
| `2026_07_18_114103_create_permission_tables.php` | `permissions`, `roles`, `model_has_roles`, `role_has_permissions`, `model_has_permissions` |
| `2026_07_18_114200_create_tenants_table.php` | `tenants` |
| `2026_07_18_115000_add_tenant_id_to_users_table.php` | Adds `tenant_id` to `users` |

### Models & Traits
- `app/Models/Tenant.php` — Tenant model
- `app/Models/Traits/BelongsToTenant.php` — Trait that applies `TenantScope` globally and defines `tenant()` relationship
- `app/Models/Scopes/TenantScope.php` — Global scope that filters `$model->getTable().'.tenant_id'` from session
- `app/Models/Checkin.php` — Uses `BelongsToTenant`

### Middleware
- `bootstrap/app.php:16` — Alias `role` → `Spatie\Permission\Middleware\RoleMiddleware`

### Roles (in code)
- `Admin` — Full access (staff, tenants, templates)
- `EventManager` — Events, guests, delivery
- `DoorStaff` — Check-in only

### Routes by Role
| Role | Routes |
|------|--------|
| Admin | `/staff`, `/tenants`, `/templates`, `/templates/calibrate` |
| Admin + EventManager | `/events`, `/guests`, `/delivery` |
| DoorStaff | `/check-in`, `POST /api/checkin` |

### Session-based tenant assignment
- Tenant ID stored in session during login/register (`LoginForm`, Register flows)
- `TenantScope` reads `session('tenant_id')` avoiding User model re-hydration (prevents recursion)

---

## Phase 3: Events, Guests, Templates CRUD

**Status: ⚠️ Built, Not Tested**

### Database
| Migration | Tables |
|-----------|--------|
| `2026_07_18_114300_create_events_table.php` | `events` (tenant_id, name, date, status, etc.) |
| `2026_07_18_114400_create_guests_table.php` | `guests` (event_id, name, qr_token, short_code) |
| `2026_07_18_120000_create_templates_table.php` | `templates` |
| `2026_07_18_121000_add_delivery_channels_to_events_table.php` | Adds `delivery_channels` JSON to events |
| `2026_07_18_121100_create_messages_log_table.php` | `messages_log` |

### Models
- `app/Models/Event.php`
- `app/Models/Guest.php`
- `app/Models/Template.php`
- `app/Models/MessageLog.php`

### Components
- `app/Livewire/Staff/StaffIndex.php` — Full Livewire class component (uses `Route::get()`)
- Volt pages (use `Route::view()`):
  - `resources/views/livewire/pages/events/index.blade.php`
  - `resources/views/livewire/pages/guests/index.blade.php`
  - `resources/views/livewire/pages/templates/index.blade.php`
  - `resources/views/livewire/pages/templates/calibrate.blade.php`

### ⚠️ Inconsistency
Auth pages use `Volt::route()` (proper page component lifecycle). All app pages (events, guests, templates, delivery, checkin) use `Route::view()` — they render as plain Blade views with Volt syntax, not as proper Livewire page components. This means `dehydrate()` (and consequently `SupportAutoInjectedAssets::$hasRenderedAComponentThisRequest`) may not be reliably set by the page component itself.

---

## Phase 4: Message Delivery

**Status: ⚠️ Built, Not Tested**

Route `/delivery` points to `resources/views/livewire/pages/delivery/index.blade.php` — Volt page with `layout('layouts.app')`.

Guest model has `email` and `phone` fields for delivery targeting. Events store `delivery_channels` as JSON (e.g., `["email", "sms"]`).

Message log table captures delivery attempts.

No actual message sending provider integration visible (no Mail/SMS driver configuration specific to this feature).

---

## Phase 5: Offline Door Scanner (Check-In)

**Status: 🔧 Just Fixed (SW cache + @livewireScripts)**

### What's Built

| Component | File | Purpose |
|-----------|------|---------|
| Migration | `2026_07_20_000000_create_checkins_table.php` | `checkins` table (guest_id, event_id, checked_in_at, client_timestamp) with unique constraint on `(guest_id, event_id)` |
| Model | `app/Models/Checkin.php` | Uses `BelongsToTenant` |
| API Controller | `app/Http/Controllers/Api/CheckinController.php` | Idempotent POST `/api/checkin` — race-condition-safe via try/catch on unique constraint |
| Client DB | `resources/js/checkin.js` | Dexie IndexedDB schema: `guests` (guest_token PK, indexes on guest_id, event_id) |
| Checkin Page | `resources/views/livewire/pages/checkin/index.blade.php` | Volt page, Alpine-driven (`x-data="checkinApp()"`), QR scanner (html5-qrcode CDN), manual code entry, result banner, recent checkins, offline indicator |
| Alpine Component | `resources/views/layouts/app.blade.php:62-177` | `window.checkinApp` global function (primary path) + `Alpine.data()` registration (secondary path via `alpine:init` listener) |
| Vite Config | `vite.config.js` | Entry points: `app.css`, `app.js`, `checkin.js` |

### ❌ Recently Fixed: window.Alpine undefined on /check-in

**Root cause:** The service worker (`public/service-worker.js`) used a **cache-first** strategy for all same-origin requests, including HTML navigation. When the user first visited `/check-in` before `@livewireScripts` was added to the layout (previous fix), the SW cached the HTML page **without** the Livewire script tag. On subsequent visits, the SW served the stale cached HTML — no Livewire script, no `window.Alpine`.

**Secondary factor:** `resources/views/layouts/app.blade.php` relied entirely on Livewire's auto-injection (`config/livewire.php: inject_assets => true`) to load `<script src="/livewire/livewire.js">`. Auto-injection can be unreliable when components don't consistently trigger `dehydrate()` (see Phase 3 inconsistency: `Route::view()` vs `Volt::route()`).

**No double-Alpine conflict:** `resources/js/app.js` is `//` (empty comment). No Alpine import exists anywhere in the JS build. Alpine only exists within Livewire's bundled `livewire.js` at `vendor/livewire/livewire/dist/livewire.js:11154` (`window.Alpine = module_default`).

### Files Touched This Session

| File | Change |
|------|--------|
| `public/service-worker.js` | Bumped cache version `tukio-cloud-v1` → `tukio-cloud-v2`; changed navigation from cache-first to network-first; added `self.clients.claim()` in activate |
| `resources/views/layouts/app.blade.php` | Already had `@livewireScripts` + `@livewireStyles` from previous session; confirmed `@vite(['resources/css/app.css', 'resources/js/app.js'])` present at line 23 |

### How to clear the stale SW cache in browser
1. Open DevTools → Application → Service Workers
2. Click "Unregister" for the old service worker
3. Or visit the page with DevTools open and check "Update on reload" / "Bypass for network"
4. The version bump (v2) should auto-invalidate old caches on next visit

### Guest data model notes
- `guests.qr_token` = 32-char random hex string (for QR code scanning)
- `guests.short_code` = 8-char random string (for manual entry)
- Both stored as `guest_token` in IndexedDB for dual-path lookup

---

## Phase 6: Testing

**Status: ❌ Not Started**

No tests exist for any feature built in Phases 3-5:

- `tests/` contains only the default Breeze auth tests (Authentication, Email Verification, Password Reset, Registration, Profile)
- `CheckinController` is completely untested
- No test for offline Dexie operations
- No test for the idempotent unique-constraint logic
- No Pest test files for any app-specific feature

---

## Summary of What's Currently Broken

### 🔴 window.Alpine undefined (FIXED this session)

**Verdict:** The fix from last session (adding `@livewireScripts` to the layout) was correct but ineffective because the service worker was serving a stale cached HTML page from before the fix. Bumping the SW cache to `v2` and switching navigation to network-first ensures fresh HTML is always fetched.

### 🟡 Inconsistent route registration

All app pages use `Route::view()` instead of `Volt::route()`. This means Volt page components don't go through Livewire's page component lifecycle. While the pages render fine, this bypasses `dehydrate()` hooks (like `SupportAutoInjectedAssets`) and may cause future issues with Livewire-specific features.

### 🟡 No CSRF handling for service worker background sync

The SW's background sync (`sync-checkins`) triggers `retryUnsynced()` via `postMessage`, but the retried fetch to `/api/checkin` may fail if the CSRF token has expired. No token refresh mechanism is in place.

### 🟡 Offline-first without full fallback UX

The checkin page works offline for lookups and writes, but the QR scanner (`html5-qrcode` CDN) requires network (loaded from CDN). A bundled fallback or error state could improve offline UX.

---

## Architecture Notes

```
Request flow for /check-in:

Browser → SW (network-first for nav) → Laravel middleware stack
  → Route::view('livewire.pages.checkin.index')
  → Layout: resources/views/layouts/app.blade.php
    → @vite(['resources/css/app.css', 'resources/js/app.js'])
    → @livewireStyles
    → <livewire:layout.navigation /> (Livewire component)
    → {{ $slot }} → checkin/index.blade.php
      → @vite(['...', 'resources/js/checkin.js'])
      → x-data="checkinApp()" (Alpine component)
    → html5-qrcode CDN
    → window.checkinApp definition
    → SW registration
    → @livewireScripts (loads livewire.js → Alpine boots)
```

Check-in data flow:
```
User scans QR / enters code
  → processToken(token) [checkin.js]
    → Dexie IndexedDB lookup by guest_token
    → If not checked in: mark checked_in in DB (optimistic), POST /api/checkin
    → If offline: schedule retry (30s timer + Background Sync)
  → Alpine component (checkinApp) re-renders result banner + recent checkins
```
