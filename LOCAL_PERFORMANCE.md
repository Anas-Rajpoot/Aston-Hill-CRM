# Faster local response times (GET /submissions, API, etc.)

If requests take 1–2+ seconds on local, use these steps.

## 1. Cache config, routes, and views (biggest win)

```bash
php artisan optimize
```

This caches config, routes, and compiled views. **Run again** when you change `.env` or `routes/*`:

```bash
php artisan optimize:clear
```

## 2. Enable PHP OPcache (recommended)

OPcache caches compiled PHP so the second request and later are much faster.

**XAMPP:** Edit `C:\xampp\php\php.ini` and ensure:

```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
```

Restart Apache (or `php artisan serve`) after changing.

## 3. Cache driver (already set)

`.env` should use `CACHE_STORE=file` for local (faster than `database`). Config defaults to `file` when `APP_ENV=local` if not set.

## 4. /me endpoint: faster response and fewer calls

- **Backend:** `GET /api/me` is cached per user for 60s (server-side). The response also sends `Cache-Control: private, max-age=60` so the **browser** can cache it; repeat loads within 60s can be served from disk cache (0–2 ms).
- **Frontend:** The auth store **skips** calling `/me` if it already has user data and the last fetch was within 55 seconds. So after the first load (~1.3s), navigating between pages does **not** trigger another `/me` request for 55s – no extra 1s+ delay on every route change.
- **First request:** To reduce the initial ~1.3s, use **optimize + OPcache** (steps 1–2). The first hit still runs Laravel + cache miss; subsequent hits (same or other users) benefit from OPcache and server cache.

## 4b. Vue / script load times (dev vs production)

- **Dev:** In development, Vite compiles each `.vue` and JS file **on demand**, so you see many small requests (e.g. 14–45 ms each). That’s normal; you can’t get “microsecond” response times in dev because of compile + network.
- **Production:** For fast script loads (few requests, then cached), build and serve the built assets:
  ```bash
  npm run build
  ```
  Then load the app from the same server (e.g. Laravel). The browser loads one or two JS chunks (and CSS); after the first load they’re cached, so repeat visits are near-instant (0–2 ms from disk cache). Use production build when you care about script load times.

## 5. Skip csrf-cookie when token is already in the page (frontend)

When you load the app from a Laravel route (e.g. GET /submissions), the HTML already includes `<meta name="csrf-token" content="...">`. The auth store now **skips** the `/sanctum/csrf-cookie` request when that meta tag is present, so you avoid the ~2–3s delay on every load. The csrf-cookie call still runs only when needed (e.g. login from a context without the meta tag).

## 6. Optional: less logging

For even faster responses, set in `.env`:

```
LOG_LEVEL=warning
```

Revert to `debug` when you need full logs.

---

## 7. Roles & permissions API (super-admin)

**What was slow**

- **`GET /api/super-admin/permissions/structure`** – Looped over every module and ran `Permission::firstOrCreate()` per permission (100+ queries). Response time could reach 15+ seconds.
- **`GET /api/super-admin/roles`** – One query per role for `users_count` (correlated subquery). No caching.
- **Role permissions page** – Vue called **structure** and **role permissions** in parallel but both endpoints were slow; total wait was dominated by the slower request (~18s).

**What was fixed**

- **RolesPermissionsCacheService** – Centralized logic with **server-side cache**:
  - **Structure:** Built from config with **one** `Permission::whereIn('name', $allNames)->get()`, bulk `Permission::insert()` for missing, then build modules in memory. Result cached **1 hour**.
  - **Roles list:** Single query with `LEFT JOIN model_has_roles` + `GROUP BY` for `users_count` (no N subqueries). Cached **5 minutes**.
- **Single endpoint for permission page:** `GET /api/super-admin/roles/{role}/permissions-page` returns `{ structure, role, permission_names }` in one response. Structure comes from cache; role permissions from one join query.
- **Vue:** RolePermissions page now uses that **single** endpoint instead of two, so one round-trip and one backend path.
- **Cache invalidation:** On role create/update/delete and on permission sync, `RolesPermissionsCacheService::forgetAll()` is called so the next request repopulates cache.
- **Spatie:** `forgetCachedPermissions()` is still called only on write (create/update/delete), not on reads.

**Target:** Roles/structure/permissions-page API responses under 300–500 ms after the first (cache-warming) request. Use `php artisan optimize` and OPcache for best first-request time.

---

## 8. Progressive rendering (incremental data loading)

We implement **Progressive Rendering with Incremental Data Loading** using section-level async rendering and skeleton loaders so the UI feels fast even when the backend is slow.

**Principles**

- **Page shell first:** Layout, header, filters, tabs, and primary buttons render immediately. No blocking on API responses.
- **Section-level loading:** Each heavy section (stats, table, permission cards) has its own loading state and skeleton. Data loads in the background after first paint.
- **No full-page spinner:** Replaced with skeletons that match the final UI shape (tables, lists, cards).
- **Bootstrap API:** `GET /api/bootstrap` returns cached user + roles + permissions (TTL 5 min). Auth store prefers it over `/me` for faster first load. Invalidated when roles/permissions change.

**Backend**

- **BootstrapController:** `GET /api/bootstrap` – cached per user (key includes version; version bumped when roles/permissions change). Returns `{ user, permissions }`. Used by auth store so one call can hydrate user + permissions.
- **Cache invalidation:** When `RolesPermissionsCacheService::forgetAll()` runs, `BootstrapController::invalidate()` is called so all bootstrap caches miss on next request.

**Vue**

- **Skeleton components** (`resources/js/components/skeletons/`): `SkeletonBox`, `SkeletonTable`, `SkeletonList`, `SkeletonStatsCards`, `SkeletonPermissionCards`. Used where heavy data loads asynchronously.
- **RolesPage:** Header and guidelines render immediately; stats and table show skeletons until `/super-admin/roles` responds, then swap to real data.
- **RolePermissions:** Header and actions render immediately; permission module cards show `SkeletonPermissionCards` until `/super-admin/roles/:id/permissions-page` responds.
- **PermissionsPage:** Shell renders immediately; roles list shows `SkeletonList` until API responds.
- **SubmissionsPage:** Tabs and card shell render immediately; tab content is wrapped in `<Suspense>` with a skeleton fallback until the lazy-loaded form component (LeadSubmissionWizard, etc.) resolves.

**Targets:** First paint &lt; 1s, interactive UI &lt; 2s, heavy data loads progressively without blank screen or full-page loader.
