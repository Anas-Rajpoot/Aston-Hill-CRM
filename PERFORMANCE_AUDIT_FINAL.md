# Final performance audit – last-mile hardening

## Goals

- Initial HTML response < 300ms  
- DOMContentLoaded < 3s  
- First paint < 1s  
- No permission/role logic during Blade render  
- No regressions in security or permission semantics  

---

## 1. Blade & HTML route audit

### SPA entry points

- `/permissions`, `/roles`, `/roles/*`, `/submissions`, and other SPA routes all serve **`view('layouts.app')`** only.
- **`resources/views/layouts/app.blade.php`** contains:
  - No `@can`, `@role`, or `@permission`.
  - No `auth()->user()->can()`, `getAllPermissions()`, or `hasRole()`.
  - Only `auth()->check()` and `auth()->id()` for the Echo script (session-only, no permission queries).

### Other Blade files

- **`resources/views/layouts/sidebar.blade.php`** – The only `@role('superadmin')` usage is inside **HTML comments** (legacy Blade nav). The active content is a Vue template (`<Sidebar />`). So **no permission logic runs during HTML render** for the SPA.
- Other Blade views (e.g. `lead-submission/*`, `users/*`, `expenses/*`) that use `@can` / `hasRole` are used by **non-SPA** resource routes (e.g. datatable, export). They are **not** part of the initial SPA document.

**Conclusion:** No permission or role checks run during the rendering of the initial HTML for the SPA. No change required in Blade for the SPA shell.

---

## 2. Middleware audit

### EnsureTwoFactorVerified (`2fa_or_superadmin`)

- **Before:** Called `$user->hasRole('superadmin')` on **every** request (Spatie/DB or cache).
- **After:** Roles are resolved **once per session** and stored in session under `_user_roles`. The middleware uses `UserPermissionResolver::getRolesAndPermissions()` only on the first request (or after login); later requests read from session. Session key is cleared on login (API and web) so the new user’s roles are resolved on the next request.
- **Security:** Same semantics: superadmin still bypasses 2FA; only the storage of the result is session-cached.

### Other middleware

- **CheckStatus** – Uses `auth()->user()->status` only (no roles/permissions).
- **BreadcrumbTrail** – Session and route name only; no DB or permission logic.
- **Spatie `role` / `permission`** – Used only on super-admin routes; not on the main SPA entry routes that serve `layouts.app`.

**Conclusion:** No repeated role/permission queries in middleware for the SPA path; superadmin bypass is session-cached.

---

## 3. View composers & service providers

- **View composers:** None registered (no `View::composer` / `view()->composer`).
- **AppServiceProvider::boot():** Registers `Gate::before` with `$user->hasRole('superadmin')`. This runs only when a Gate check is performed (e.g. `@can` or `Gate::allows()`). The SPA shell does **not** use `@can` or Gate, so this does **not** run during the initial HTML render for the SPA.
- **Boot logic:** No DB or permission work in boot for the request path that serves the SPA.

**Conclusion:** No view composers or boot-time permission work affecting the initial SPA HTML.

---

## 4. Vue bundle & entry optimization

- **Router:** Heavy route components are now **lazy-loaded** with `() => import('...')`:
  - Auth: Login, Register, ForgotPassword, ResetPassword, TwoFactorVerify.
  - App: SubmissionsPage, LeadSubmissions, UsersPage, UserShow, UserEdit, UserCreate, TeamHierarchyPage, RolesPage, RoleCreate, RoleEdit, RolePermissions, PermissionsPage.
- **Eager:** AppLayout, Dashboard, PlaceholderPage (small or needed for first paint).
- **app.js:** No change to global Pinia or router; stores remain created once. No permission-heavy logic before first render; bootstrap is called from the router guard after mount.

**Conclusion:** Initial JS parse is reduced; large pages load on demand. No permission logic before first render.

---

## 5. Duplicate bootstrap calls

- **Auth store:** `_fetchPromise` prevents concurrent `fetchUser()` calls; `_lastFetchedAt` and a 55s window prevent refetch on route change.
- **Router:** `beforeEach` calls `fetchUser()` only when `!auth.user`; after the first successful load, the 55s guard avoids extra `/bootstrap` or `/me` calls.

**Conclusion:** `/api/bootstrap` is effectively called once per “session” of navigation (and at most once per 55s). No change required.

---

## 6. Font & asset optimization

- **Fonts:** `layouts/app.blade.php` already has:
  - `preconnect` to `https://fonts.bunny.net`
  - `display=swap` for the Figtree font.
- **Scripts:** Vite entry for `app.js` and `app.css`; no blocking permission or role logic in the initial HTML.

**Conclusion:** Font and asset setup are already in line with the targets; no change required.

---

## Summary of code changes

| Area | Change |
|------|--------|
| **EnsureTwoFactorVerified** | Cache user roles in session (`_user_roles`); resolve via `UserPermissionResolver` only when missing; clear key on login (API + web). |
| **AuthController::login** | `session()->forget(EnsureTwoFactorVerified::SESSION_ROLES_KEY)` after `regenerate()`. |
| **AuthenticatedSessionController::store** | Same `session()->forget(...)` after `regenerate()`. |
| **Router** | Lazy-load auth pages and heavy app pages (roles, permissions, submissions, users, etc.); keep AppLayout, Dashboard, PlaceholderPage eager. |

---

## Security and semantics

- Authorization rules are unchanged: superadmin still bypasses 2FA; role/permission checks elsewhere are unchanged.
- Session-cached roles are cleared on login so a new user never reuses the previous user’s roles.
- No new data exposed; no removal of checks.

---

## Remaining recommendations

1. **Measure again** after deploy: initial HTML time, DOMContentLoaded, and Time to Interactive with cache warm and cold.
2. **OPcache:** Ensure PHP OPcache is enabled in production so Blade and PHP are compiled once.
3. **Vite build:** Run `npm run build` and test with production assets to confirm chunk splitting and lazy load behavior.
