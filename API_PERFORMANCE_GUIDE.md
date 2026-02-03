# API Performance Guide – Laravel 12 + Vue 3

Goals:

- **Initial HTML document** (e.g. `GET /submissions`): **&lt; 300 ms**
- **API responses** (e.g. `/login`, `/bootstrap`): **&lt; 300 ms**

---

## 1. Backend analysis summary

### SPA shell (initial HTML document)

All SPA document routes (`/`, `/dashboard`, `/submissions`, `/users`, etc.) are in **`routes/spa.php`** and use the **`spa_shell`** middleware group only. No `web` group (no CSRF, no ShareErrorsFromSession, no BreadcrumbTrail) and no auth middleware on the document request.

| Middleware stack (SPA document) | Purpose |
|---------------------------------|--------|
| `spa_shell`                     | EncryptCookies, AddQueuedCookiesToResponse, StartSession, SubstituteBindings |

- **Auth** – Not run on the initial GET. Vue router + API enforce auth; unauthenticated users get the same HTML shell and are redirected to `/login` by the frontend.
- **Session** – Still started so subsequent API calls (session-based Sanctum) work; no session write from BreadcrumbTrail (those routes are not in the `web` group).
- **Result** – Minimal work per request: cookies, session start, view render. Target: **initial HTML &lt; 300 ms**.

### Middleware stack (API)

| Route        | Middleware |
|-------------|------------|
| `POST /auth/login` | `web` only (session, cookie, CSRF). No auth. |
| `GET /bootstrap`   | `web`, `auth:sanctum`, `verified`, `approved`, `2fa_or_superadmin` |

- **auth:sanctum** – Resolves user from session or Bearer token (1 DB hit for session or token lookup).
- **2fa_or_superadmin** – Uses session-cached roles; no Spatie `hasRole()` on every request after the first.

### Auth guards

- **web** – Session driver (`config('session.driver')`). Prefer `redis` or `cookie` in production; `database` adds a DB read/write per request.
- **sanctum** – Tries session first, then Bearer token. No extra heavy work if session is already loaded.

### Session

- **Driver** – In `.env` use `SESSION_DRIVER=cookie` (no DB) or `SESSION_DRIVER=redis` for sub‑ms reads and larger payloads. Avoid `SESSION_DRIVER=database` on hot paths (adds a DB read/write per request).
- **Lifetime** – Set `SESSION_LIFETIME`; avoid very long values if you need quick invalidation.
- **SPA document** – `spa_shell` still runs `StartSession` so the first HTML response can set the session cookie and later API calls (e.g. `/bootstrap`) use the same session.

### Database

- **Bootstrap / Me / Login** – Use `UserPermissionResolver` (2 queries: roles + permissions). No N+1, no Spatie `getAllPermissions()` or `load('roles')` in the hot path.
- **Indexes** – `role_has_permissions.role_id` indexed; `model_has_roles` / `model_has_permissions` have composite indexes from Spatie migrations.

### Service providers

- **AppServiceProvider::boot()** – `Gate::before` with `hasRole('superadmin')` runs only when a Gate check is made (e.g. `@can`). Not used in the SPA shell or API JSON responses for bootstrap/login.
- No view composers or boot-time DB/permission work for the API.

### Events / listeners

- **Login** – `LogUserLogin` no longer blocks the response: it dispatches `ProcessLoginLog` to the queue. With `QUEUE_CONNECTION=sync` (e.g. local) the job runs inline; with `redis` or `database` the login response returns immediately.

---

## 2. Bootstrap endpoint

### Implemented optimizations

- **Payload** – Only `user: { id, name, email, roles }` and `permissions: string[]`. No extra relations or keys.
- **Queries** – Exactly 2 (roles, permissions via UNION) via `UserPermissionResolver`. No Spatie models in the hot path.
- **Cache** – `Cache::remember('bootstrap_{version}_{user_id}', 300, ...)`. Version bumped when roles/permissions change so caches miss and repopulate.
- **Cache driver** – Use `redis` or `file` in production; avoid `database` for cache if possible.

### Example cached bootstrap response (< 2 KB typical)

```json
{
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin@example.com",
    "roles": ["superadmin"]
  },
  "permissions": [
    "dashboard.view_dashboard",
    "dashboard.view_pending_tasks",
    "users.list",
    "users.create"
  ]
}
```

### Invalidation

- `BootstrapController::invalidate()` is called when roles/permissions are updated (e.g. from `RolesPermissionsCacheService::forgetAll()`). All bootstrap caches miss and are rebuilt on next request.

---

## 3. Authentication flow

### Login

- **Validation** – Email + password only.
- **Auth::attempt()** – Single query (or two with “remember”).
- **Status check** – `$user->status` (already loaded).
- **Roles/permissions** – One call to `UserPermissionResolver::getRolesAndPermissions()` (2 queries). Used for:
  - Superadmin 2FA bypass (no `hasRole()`).
  - Response payload: `user` + `permissions` so the frontend can skip an immediate `/bootstrap` call.
- **UserLoginLog** – Single `update()` for `session_id`. Full login log (geoip, suspicious check, insert) is done in queued `ProcessLoginLog` job.

### After login (session)

- Response includes `redirect`, `user`, and `permissions`. Vue store sets `user` and `_lastFetchedAt` and does not call `fetchUser()` (and thus not `/bootstrap`) on that navigation.

### /me

- Uses `UserPermissionResolver` and `Cache::remember('api_me_{id}', 60, ...)`. Same 2-query resolution; no Spatie `load('roles')`.

---

## 4. Laravel best practices

### Config / route / view cache (production)

| Command | Effect |
|--------|--------|
| `php artisan config:cache` | Writes a single config file; avoids reading many PHP files and `.env` on every request. |
| `php artisan route:cache` | Precompiles route list; avoids parsing `routes/*.php` on every request. |
| `php artisan view:cache` | Precompiles Blade templates; avoids compiling views on first load. |

Run after deploy or after changing config/routes/views. Clear with `php artisan optimize:clear` when developing.

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Production .env

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Session: redis is faster than database for server-side sessions
SESSION_DRIVER=redis
# Or SESSION_DRIVER=cookie if you keep session data small

# Cache: redis strongly recommended for bootstrap/me and permission caches
CACHE_STORE=redis

# Queue: run worker so login log and other jobs don't block requests
QUEUE_CONNECTION=redis
# Then: php artisan queue:work

# Logging: avoid writing huge logs in production
LOG_LEVEL=warning
```

### Local development (why responses can still be 5–8 s)

If **GET /submissions** or **GET /api/bootstrap** are still slow (e.g. 5–8 s) locally:

1. **Layout no longer runs auth** – `layouts/app.blade.php` no longer calls `auth()->check()` or `auth()->id()` so the SPA document request does not load the user. Real-time notifications (Echo) can be subscribed in Vue after `/bootstrap` returns.
2. **Clear route cache** – If you had run `php artisan route:cache` before the SPA shell change, run `php artisan route:clear` (or `php artisan optimize:clear`) so `/submissions` uses the `spa_shell` route.
3. **Session and cache drivers** – With `SESSION_DRIVER=database` or `CACHE_STORE=database`, every request hits the DB. For local dev use `SESSION_DRIVER=file` and `CACHE_STORE=file` so session/cache are on disk and avoid DB round-trips.
4. **First request is cold** – The first `/api/bootstrap` request fills the cache; the second should be much faster. Reload the page and check the second request time.
5. **Favicon** – A fast `favicon.ico` route is registered in `routes/spa.php` so if the request hits Laravel it returns the file without rendering a view. With `php artisan serve`, `/favicon.ico` is often served from `public/` before Laravel.

### Redis

If using Redis for cache/session/queue:

```env
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Configure `config/database.php` Redis connections and, if needed, `config/cache.php` / `config/session.php` to use the desired store.

### Heavy tasks

- Login log (geoip, suspicious check, insert, notify) – **ProcessLoginLog** job.
- Other non-request work (emails, reports, etc.) – Prefer jobs and `queue:work` (or Horizon) so HTTP responses stay fast.

---

## 5. Vue (frontend) improvements

### Duplicate API calls

- **fetchUser()** – Single in-flight request via `_fetchPromise`; skips if user is present and `_lastFetchedAt` is within 55s.
- **After login** – If the server returns `user` and `permissions`, the store sets them and does not call `fetchUser()` (no extra `/bootstrap` on first load).

### Bootstrap cache (Pinia + localStorage)

- **localStorage** – Key `auth_bootstrap` with `{ at, data }`. TTL 4 minutes. Used to show cached user immediately while a fresh `/bootstrap` runs.
- **Flow** – On `_doFetchUser()`: if cache exists and not expired, set `user` from cache and `_lastFetchedAt`; then still call `/bootstrap`, update store and cache. If the request fails but cache exists, UI keeps showing cached user.
- **Logout** – Cache key is removed.

### Lazy-load after login

- Router already lazy-loads heavy components (roles, permissions, submissions, etc.). Bootstrap (or login response) only loads user + permissions; page-specific data loads when the user navigates to the route.

---

## 6. Before / after expectations

| Metric                    | Before (typical)   | After (target)        |
|---------------------------|--------------------|------------------------|
| **Initial HTML** (e.g. GET /submissions) | 4–6 s              | **&lt; 300 ms**         |
| POST /auth/login          | 2–4 s              | &lt; 300 ms (with queue worker) |
| GET /bootstrap            | 2–4 s              | &lt; 100 ms (cache hit) |
| GET /bootstrap            | (cold)             | &lt; 300 ms (cache miss) |
| GET /me                   | ~1 s               | &lt; 100 ms (cache hit)   |

Assumptions:

- Session driver: `cookie` or `redis` (not `database`).
- Cache driver: `redis` or `file` (not `database`).
- Queue: `ProcessLoginLog` run via worker (so login is not blocked by geoip/DB).
- Production: `APP_DEBUG=false`, `config`/`route`/`view` cached, OPcache enabled.

---

## 7. Files changed (reference)

| Area            | File(s) |
|-----------------|---------|
| **SPA shell**   | `bootstrap/app.php`: `spa_shell` middleware group; `then` loads `routes/spa.php` with `spa_shell`. `routes/spa.php`: all SPA document routes (no auth). `routes/web.php`: SPA closures removed; auth-required controller/resource routes kept. |
| Login           | `AuthController`: UserPermissionResolver, return `user` + `permissions` in session login response. |
| Login log       | `LogUserLogin`: dispatch `ProcessLoginLog`; `ProcessLoginLog` job does geoip + DB + notify. |
| Me              | `MeController`: UserPermissionResolver + Cache::remember, no `load('roles')`. |
| Bootstrap       | UserPermissionResolver, cache per user, version invalidation. |
| Vue auth store  | Use login response when present; localStorage bootstrap cache (4 min); clear cache on logout. |
| BreadcrumbTrail | Skip when route has no name (SPA closures in `web` used to run it; now SPA routes use `spa_shell` so BreadcrumbTrail is not in the stack). |

---

## 8. Quick checklist

- [ ] Production `.env`: `APP_DEBUG=false`, `CACHE_STORE=redis` (or `file`), `SESSION_DRIVER=redis` or `cookie`, `QUEUE_CONNECTION=redis` (or `database`).
- [ ] Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache` in production.
- [ ] Run at least one queue worker (e.g. `php artisan queue:work`) so `ProcessLoginLog` does not block.
- [ ] Ensure Redis is running if using it for cache/session/queue.
- [ ] After changing roles/permissions, bootstrap caches are invalidated via `BootstrapController::invalidate()`.
- [ ] SPA document routes live in `routes/spa.php` with `spa_shell` only; no auth on initial HTML for fast first byte.
