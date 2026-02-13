/**
 * Composable for user edit page: parallel fetch prime + extras with granular loading states.
 * - Fetches /users/:id/prime and /users/:id/extras in parallel (Promise.allSettled).
 * - Uses AbortController to cancel in-flight requests on route change or retry.
 * - Optional SWR: show cached data instantly, revalidate in background.
 * - Exposes loadingPrime, loadingExtras, errorPrime, errorExtras, user (prime), extras.
 */

import { ref, watch, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import usersApi from '@/services/usersApi'
import api from '@/lib/axios'
import extensionsApi from '@/services/extensionsApi'
import { useApiCacheStore } from '@/stores/apiCache'

export function useUserEditData(options = {}) {
  const { useCache = true, cacheTtlMs = 5 * 60 * 1000 } = options
  const route = useRoute()
  const apiCache = useApiCacheStore()

  const user = ref(null)
  const extras = ref(null)
  const countries = ref([])
  const extensionOptions = ref([])

  const loadingPrime = ref(false)
  const loadingExtras = ref(false)
  const errorPrime = ref(null)
  const errorExtras = ref(null)

  let abortController = null

  function newAbortController() {
    abortController?.abort()
    abortController = new AbortController()
    return abortController.signal
  }

  async function fetchPrime(id) {
    const key = `GET /users/${id}/prime`
    if (useCache) {
      const cached = apiCache.get(`/users/${id}/prime`, {})
      if (cached?.user) {
        user.value = cached.user
        return
      }
    }
    loadingPrime.value = true
    errorPrime.value = null
    const signal = newAbortController()
    try {
      const res = await usersApi.prime(id, { signal })
      const data = res?.data ?? res
      user.value = data?.user ?? null
      if (useCache && user.value) {
        apiCache.set(`/users/${id}/prime`, {}, { user: user.value }, cacheTtlMs)
      }
    } catch (e) {
      if (e?.name === 'AbortError' || e?.code === 'ERR_CANCELED') return
      errorPrime.value = e?.response?.data?.message || 'Failed to load user.'
    } finally {
      loadingPrime.value = false
    }
  }

  async function fetchExtras(id) {
    if (useCache) {
      const cached = apiCache.get(`/users/${id}/extras`, {})
      if (cached && Object.keys(cached).length) {
        extras.value = cached
        return
      }
    }
    loadingExtras.value = true
    errorExtras.value = null
    if (!abortController) newAbortController()
    const signal = abortController.signal
    try {
      const res = await usersApi.extras(id, { signal })
      const data = res?.data ?? res
      extras.value = data || null
      if (useCache && extras.value) {
        apiCache.set(`/users/${id}/extras`, {}, extras.value, cacheTtlMs)
      }
    } catch (e) {
      if (e?.name === 'AbortError' || e?.code === 'ERR_CANCELED') return
      errorExtras.value = e?.response?.data?.message || 'Failed to load options.'
    } finally {
      loadingExtras.value = false
    }
  }

  async function fetchCountries() {
    if (countries.value.length) return
    try {
      const { data } = await api.get('/countries', { signal: abortController?.signal })
      countries.value = Array.isArray(data) ? data : data?.data ?? []
    } catch {
      countries.value = []
    }
  }

  async function fetchExtensions(id) {
    try {
      const res = await extensionsApi.index({ status: ['active'], usage: ['unassigned', 'assigned'], per_page: 100, signal: abortController?.signal })
      const rows = res?.data?.data ?? res?.data ?? []
      const userId = parseInt(id, 10)
      extensionOptions.value = rows
        .filter((r) => r.assigned_to == null || r.assigned_to === userId)
        .map((r) => ({ value: r.extension ?? String(r.id), label: r.extension ?? String(r.id) }))
    } catch {
      extensionOptions.value = []
    }
  }

  async function load(id) {
    if (!id) return
    newAbortController()
    user.value = null
    extras.value = null
    errorPrime.value = null
    errorExtras.value = null
    countries.value = []
    extensionOptions.value = []

    // Prime and extras in parallel (same AbortController so one cancel stops both)
    const primePromise = fetchPrime(id)
    const extrasPromise = fetchExtras(id)
    await Promise.allSettled([primePromise, extrasPromise])
    await fetchCountries()
    await fetchExtensions(id)
  }

  function retry() {
    const id = route.params?.id
    if (id) load(id)
  }

  watch(
    () => route.params?.id,
    (id) => {
      if (id) load(id)
    },
    { immediate: true }
  )

  onUnmounted(() => {
    abortController?.abort()
  })

  return {
    user,
    extras,
    countries,
    extensionOptions,
    loadingPrime,
    loadingExtras,
    errorPrime,
    errorExtras,
    load,
    retry,
  }
}
