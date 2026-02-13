# Production Performance Checklist

## Laravel (Backend)

### Cache & config
- **Route cache:** `php artisan route:cache` (clear before deployment: `route:clear`, then cache again).
- **Config cache:** `php artisan config:cache` (use `config:clear` locally when changing .env).
- **View cache:** `php artisan view:cache` (if using server-rendered Blade).
- **Event cache:** `php artisan event:cache` (Laravel 11+).

### OPcache (php.ini)
- `opcache.enable=1`
- `opcache.memory_consumption=128`
- `opcache.interned_strings_buffer=8`
- `opcache.max_accelerated_files=10000`
- `opcache.validate_timestamps=0` in production (or 1 with revalidate every N seconds).

### Redis
- Set `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis` in production.
- Use Redis for cache tags (user edit cache invalidation).
- Optional: Redis for rate limiting and broadcasting.

### Queues
- Run `php artisan queue:work` (or Horizon) for emails, exports, and heavy jobs.
- Use `dispatch()` for non-blocking jobs; avoid long-running work in request lifecycle.

### HTTP
- Enable **gzip/brotli** compression (Nginx: `gzip on`; Apache: mod_deflate).
- Use **HTTP/2** and **keep-alive**.
- Optional: ETag middleware on read-heavy GET routes (`etag.cache` alias).

### Database
- Run migrations that add indexes: `php artisan migrate` (e.g. `users_status_created_at_index`).
- Use `EXPLAIN` on slow queries to confirm index usage.
- Prefer eager loading `->with([...])` and `->select([...])` to avoid N+1.

---

## Frontend (Vue 3 + Vite)

### Build
- Production build: `npm run build` (minification, tree-shaking).
- Lazy-load heavy routes: `component: () => import('@/pages/...')`.
- Heavy components: `defineAsyncComponent(() => import('./Heavy.vue'))`.

### API
- Use **prime + extras** split for edit-style pages (critical data first, then dropdowns/lists).
- **Parallel fetch** with `Promise.allSettled`; show section skeletons until each part is ready.
- **AbortController** on route change to cancel in-flight requests.
- **Pinia apiCache** for SWR (show cached data, revalidate in background).

### Lists
- **Paginate** large tables; virtualize very long lists (e.g. vue-virtual-scroller) if needed.
- Debounce search/filter inputs to limit request rate.

---

## Ops summary

| Task | Command / action |
|------|-------------------|
| Laravel route cache | `php artisan route:cache` |
| Laravel config cache | `php artisan config:cache` |
| Laravel view cache | `php artisan view:cache` |
| Redis (cache/session/queue) | Set in `.env`; run Redis server |
| Queue worker | `php artisan queue:work` or Horizon |
| OPcache | Enable and tune in php.ini |
| Compression | Nginx/Apache gzip or brotli |
| Indexes | `php artisan migrate` (index migrations) |
