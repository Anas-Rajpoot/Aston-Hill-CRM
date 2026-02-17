# Aston Hill CRM — Performance Optimization Guide

> Target: p95 < 400ms, p50 < 120ms for all list/report API endpoints

## Architecture Overview

```
Browser (Vue 3)
  ├── Single bootstrap request per page (filters + columns + data)
  ├── Debounced search (400ms)
  ├── AbortController cancellation on param change
  └── In-memory filter cache (10 min TTL)
       │
       ▼
Laravel API (Sanctum auth)
  ├── ApiCacheHeaders middleware (ETag + Cache-Control)
  ├── SubmissionCacheService (tag-based Redis / file fallback)
  ├── Selective column loading (no SELECT *)
  ├── Conditional eager loading (N+1 protection)
  └── Composite database indexes
       │
       ▼
MySQL + Redis
  ├── Composite indexes on all 4 submission tables
  ├── Redis cache store (recommended for production)
  └── Redis queue driver (recommended for exports)
```

---

## 1. Database Indexes

Migration: `2026_02_17_200000_add_performance_indexes_to_submission_tables.php`

Indexes are designed around the most common WHERE + ORDER BY patterns:

| Table | Index | Purpose |
|-------|-------|---------|
| `lead_submissions` | `(status, submitted_at)` | Listing page default sort |
| `lead_submissions` | `(submitted_at, status)` | Reports date-range filters |
| `lead_submissions` | `created_by`, `sales_agent_id`, `team_leader_id`, `manager_id`, `team_id` | RBAC `visibleTo()` scope |
| `lead_submissions` | `company_name`, `account_number` | Text search filters |
| `field_submissions` | Same pattern as leads | — |
| `customer_support_submissions` | Same + `issue_category` | Category filter |
| `vas_request_submissions` | Same + `request_type`, `back_office_executive_id` | VAS-specific filters |

### Validating with EXPLAIN

```sql
EXPLAIN SELECT id, company_name, status, submitted_at
FROM lead_submissions
WHERE status = 'submitted'
ORDER BY submitted_at DESC
LIMIT 25;
```

The `idx_leads_status_submitted` index should appear in the `key` column.

---

## 2. Caching Strategy

### Server-side: `SubmissionCacheService`

Located at `app/Services/SubmissionCacheService.php`

| Method | TTL | Use Case |
|--------|-----|----------|
| `rememberList()` | 2 min | Paginated list results |
| `rememberMeta()` | 10 min | Filter options, column config |
| `rememberStats()` | 5 min | Report statistics |

**Tag-based invalidation**: When any submission model is saved/deleted, the `SubmissionCacheObserver` flushes only that module's tag:

```php
// Registered in AppServiceProvider
LeadSubmission::observe(new SubmissionCacheObserver('leads'));
```

**Driver support**: Tags require Redis or Memcached. With file cache, entries expire naturally via TTL.

### Client-side: `useFilterCache` composable

Located at `resources/js/composables/useFilterCache.js`

- Filters and columns are cached in-memory for 10 minutes
- Survives component remount (e.g., navigating away and back)
- Invalidated when user saves column preferences

### HTTP Caching: `ApiCacheHeaders` middleware

Located at `app/Http/Middleware/ApiCacheHeaders.php`

Applied to GET listing and report endpoints:
- **List endpoints**: `max-age=10, s-maxage=20`
- **Filter endpoints**: `max-age=60, s-maxage=120`
- **Report stats**: `max-age=15, s-maxage=30`
- **SLA performance**: `max-age=30, s-maxage=60`

Returns `304 Not Modified` with ETag when content hasn't changed.

---

## 3. Aggregated Bootstrap Endpoints

Each submission type has a `/bootstrap` endpoint that returns filters + columns + first page data in a single request:

```
GET /api/lead-submissions/bootstrap
GET /api/field-submissions/bootstrap
GET /api/customer-support/bootstrap
GET /api/vas-requests/bootstrap
```

Response:
```json
{
  "filters": { "categories": [...], "statuses": [...] },
  "columns": { "all_columns": [...], "visible_columns": [...] },
  "page": { "data": [...], "meta": { "current_page": 1, ... } }
}
```

This reduces initial page load from 3-4 sequential API calls to 1.

---

## 4. Frontend Optimizations

### Debounced Search
All listing pages debounce the search input by 400ms:
```js
const debouncedSearch = debounce(() => { meta.value.current_page = 1; load() }, 400)
watch(() => filters.value.q, (n, o) => { if (n !== o) debouncedSearch() })
```

### Request Cancellation
In-flight list requests are cancelled when new ones are made:
```js
if (listAbortController) listAbortController.abort()
listAbortController = new AbortController()
const data = await api.index(params, { signal: listAbortController.signal })
```

### Auto-cleanup
Components abort pending requests and cancel debounce timers on unmount.

---

## 5. Switching to Redis (Recommended for Production)

In `.env`:
```env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

Ensure Redis is running and configured in `config/database.php` → `redis` section.

Benefits:
- Tag-based cache invalidation (instant flush per module)
- Faster than file-based cache
- Shared cache across multiple app instances
- Required for Horizon queue dashboard

---

## 6. Profiling & EXPLAIN

### Check slow queries
```sql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 0.5;
SHOW VARIABLES LIKE '%slow%';
```

### Validate index usage
```sql
-- Lead submissions listing query
EXPLAIN SELECT id, company_name, status, submitted_at, created_by
FROM lead_submissions
WHERE status IN ('submitted', 'approved')
  AND (created_by = 1 OR sales_agent_id = 1 OR team_leader_id = 1)
ORDER BY submitted_at DESC
LIMIT 25;
```

### MySQL Tuning (production)
```ini
innodb_buffer_pool_size = 1G   # 60-70% of RAM
tmp_table_size = 64M
max_heap_table_size = 64M
innodb_log_file_size = 256M
```

---

## 7. OPcache (PHP)

Enable in `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0  # set to 1 during development
```

---

## 8. Monitoring

### Per-endpoint latency
The `AuditApiActivity` middleware already logs API requests. For deeper profiling:

```bash
# Install Telescope for staging
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

### Query count assertions in tests
```php
// In feature tests
DB::enableQueryLog();
$this->getJson('/api/lead-submissions');
$this->assertLessThanOrEqual(5, count(DB::getQueryLog()));
```

---

## 9. Quick Reference: Files Changed

| File | Purpose |
|------|---------|
| `database/migrations/2026_02_17_200000_*` | Composite indexes for all 4 submission tables |
| `app/Services/SubmissionCacheService.php` | Tag-based caching layer |
| `app/Observers/SubmissionCacheObserver.php` | Auto-flush cache on model events |
| `app/Http/Middleware/ApiCacheHeaders.php` | ETag + Cache-Control headers |
| `app/Http/Resources/{Field,CustomerSupport,VasRequest}Resource.php` | API Resources for response slimming |
| `app/Http/Controllers/Api/*ApiController.php` | Bootstrap endpoints + cached filters |
| `app/Providers/AppServiceProvider.php` | Observer registration |
| `bootstrap/app.php` | Middleware alias registration |
| `routes/api.php` | Bootstrap routes + api.cache middleware |
| `resources/js/composables/useApiRequest.js` | Debounce + AbortController |
| `resources/js/composables/useFilterCache.js` | Client-side filter/column caching |
| `resources/js/pages/*/ListingPage.vue` | Debounced search, cancellation, bootstrap |
| `resources/js/services/*Api.js` | Signal support for cancellation |
