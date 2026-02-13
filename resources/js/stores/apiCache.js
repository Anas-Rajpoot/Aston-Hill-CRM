/**
 * Pinia store: in-memory API response cache with TTL and request deduplication.
 * Stale-while-revalidate (SWR): return cached data immediately, then revalidate in background and update.
 * Dedup: only one in-flight request per cache key within the TTL window.
 */

import { defineStore } from 'pinia'

const DEFAULT_TTL_MS = 5 * 60 * 1000 // 5 min

function cacheKey(url, params = {}) {
  const sorted = Object.keys(params)
    .sort()
    .map((k) => `${k}=${JSON.stringify(params[k])}`)
    .join('&')
  return sorted ? `${url}?${sorted}` : url
}

export const useApiCacheStore = defineStore('apiCache', {
  state: () => ({
    _entries: {}, // { key: { data, at } }
    _inFlight: {}, // { key: Promise }
    _defaultTtlMs: DEFAULT_TTL_MS,
  }),

  getters: {
    /** Get cached data if present and not expired. */
    get: (state) => (url, params = {}) => {
      const key = cacheKey(url, params)
      const entry = state._entries[key]
      if (!entry) return null
      if (Date.now() - entry.at > (entry.ttlMs ?? state._defaultTtlMs)) return null
      return entry.data
    },
  },

  actions: {
    set(url, params, data, ttlMs = null) {
      const key = cacheKey(url, params)
      this._entries[key] = {
        data,
        at: Date.now(),
        ttlMs: ttlMs ?? this._defaultTtlMs,
      }
    },

    invalidate(url, params = null) {
      if (params === null) {
        const prefix = url.includes('?') ? url.split('?')[0] : url
        Object.keys(this._entries).forEach((k) => {
          if (k.startsWith(prefix)) delete this._entries[k]
        })
        return
      }
      const key = cacheKey(url, params)
      delete this._entries[key]
      delete this._inFlight[key]
    },

    /**
     * Deduplicate: if a request for this key is already in flight, return that promise.
     * Otherwise run fetcher(), cache the result, and return.
     */
    async dedup(key, fetcher, ttlMs = null) {
      if (this._inFlight[key]) {
        return this._inFlight[key]
      }
      const promise = fetcher()
      this._inFlight[key] = promise
      try {
        const result = await promise
        return result
      } finally {
        delete this._inFlight[key]
      }
    },
  },
})
