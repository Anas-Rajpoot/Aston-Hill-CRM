/**
 * useFilterCache(module)
 *
 * Composable for caching filter/column metadata in Pinia-backed memory.
 * Avoids re-fetching stable data on every page visit.
 *
 * - Caches filters + columns for 10 minutes
 * - Supports the new /bootstrap endpoint (single request for filters + columns + first page)
 * - Falls back to individual requests if bootstrap is unavailable
 *
 * Usage:
 *   const { filters, columns, bootstrapData, loadBootstrap, loadFilters, loadColumns, invalidate } = useFilterCache('lead-submissions')
 */
import { ref } from 'vue'
import api from '@/lib/axios'

const CACHE_TTL_MS = 10 * 60 * 1000 // 10 minutes

// Module-level in-memory cache (persists across component mounts)
const _cache = {}

function getCached(module, key) {
  const entry = _cache[`${module}:${key}`]
  if (!entry) return null
  if (Date.now() - entry.ts > CACHE_TTL_MS) {
    delete _cache[`${module}:${key}`]
    return null
  }
  return entry.data
}

function setCache(module, key, data) {
  _cache[`${module}:${key}`] = { data, ts: Date.now() }
}

export function useFilterCache(module) {
  const filters = ref(null)
  const columns = ref(null)
  const bootstrapData = ref(null)
  const loading = ref(false)

  /**
   * Load everything via the aggregated bootstrap endpoint.
   * Returns { filters, columns, page } in one request.
   */
  async function loadBootstrap(params = {}, signal = undefined) {
    // Check cache for filters + columns
    const cachedFilters = getCached(module, 'filters')
    const cachedColumns = getCached(module, 'columns')

    if (cachedFilters && cachedColumns) {
      filters.value = cachedFilters
      columns.value = cachedColumns
    }

    loading.value = true
    try {
      const { data } = await api.get(`/${module}/bootstrap`, { params, signal })
      filters.value = data.filters
      columns.value = data.columns
      bootstrapData.value = data

      setCache(module, 'filters', data.filters)
      setCache(module, 'columns', data.columns)

      return data
    } catch (err) {
      if (err?.name === 'CanceledError' || err?.code === 'ERR_CANCELED') return null
      // Fall back to individual requests
      console.warn(`Bootstrap endpoint failed for ${module}, falling back:`, err.message)
      await Promise.all([loadFilters(signal), loadColumns(signal)])
      return null
    } finally {
      loading.value = false
    }
  }

  /**
   * Load filter metadata (cached for 10 min).
   */
  async function loadFilters(signal = undefined) {
    const cached = getCached(module, 'filters')
    if (cached) {
      filters.value = cached
      return cached
    }

    try {
      const { data } = await api.get(`/${module}/filters`, { signal })
      filters.value = data
      setCache(module, 'filters', data)
      return data
    } catch (err) {
      if (err?.name !== 'CanceledError' && err?.code !== 'ERR_CANCELED') {
        console.warn(`Failed to load filters for ${module}:`, err.message)
      }
      return null
    }
  }

  /**
   * Load column configuration (cached for 10 min).
   */
  async function loadColumns(signal = undefined) {
    const cached = getCached(module, 'columns')
    if (cached) {
      columns.value = cached
      return cached
    }

    try {
      const { data } = await api.get(`/${module}/columns`, { signal })
      columns.value = data
      setCache(module, 'columns', data)
      return data
    } catch (err) {
      if (err?.name !== 'CanceledError' && err?.code !== 'ERR_CANCELED') {
        console.warn(`Failed to load columns for ${module}:`, err.message)
      }
      return null
    }
  }

  /**
   * Invalidate cached data (call after column preference save).
   */
  function invalidate(key = null) {
    if (key) {
      delete _cache[`${module}:${key}`]
    } else {
      delete _cache[`${module}:filters`]
      delete _cache[`${module}:columns`]
    }
  }

  return { filters, columns, bootstrapData, loading, loadBootstrap, loadFilters, loadColumns, invalidate }
}
