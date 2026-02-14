/**
 * useDashboardAutoRefresh
 *
 * Smart auto-refresh composable with:
 *  - ETag / 304 support (skip DOM update when data unchanged)
 *  - Idle detection (pause when no activity for 30s)
 *  - Tab visibility (pause when tab hidden)
 *  - Exponential backoff on errors (30s → 1m → 2m)
 *  - AbortController to cancel stale requests
 *  - Debounced toggle
 */
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import api from '@/lib/axios'

const IDLE_TIMEOUT_MS = 30_000  // 30 seconds of inactivity
const BACKOFF_STEPS   = [30_000, 60_000, 120_000]  // 30s, 1m, 2m

export function useDashboardAutoRefresh(fetchCallback) {
  const enabled        = ref(false)
  const intervalMs     = ref(5 * 60_000)  // default 5 min, overridden from API
  const refreshing     = ref(false)
  const lastRefreshed  = ref(null)
  const lastEtag       = ref(null)

  let timerId          = null
  let abortController  = null
  let backoffIndex     = 0
  let idleTimer        = null
  let isIdle           = false
  let isHidden         = false

  // ── Fetch with ETag ───────────────────────────────────
  async function doRefresh() {
    if (refreshing.value) return
    refreshing.value = true

    // Cancel any in-flight request
    if (abortController) abortController.abort()
    abortController = new AbortController()

    const start = performance.now()
    const headers = {}
    if (lastEtag.value) headers['If-None-Match'] = lastEtag.value

    try {
      const res = await api.get('/dashboard/stats', {
        signal: abortController.signal,
        headers,
        validateStatus: s => s === 200 || s === 304,
      })

      if (res.status === 304) {
        // Data unchanged — skip callback
        console.info(`[AutoRefresh] 304 Not Modified – saved bandwidth (${Math.round(performance.now() - start)}ms)`)
      } else {
        lastEtag.value = res.headers?.etag || null
        const data = res.data

        // Apply auto-refresh settings from server
        if (data.auto_refresh) {
          intervalMs.value = (data.auto_refresh.interval_minutes || 5) * 60_000
        }

        // Call the page's data handler
        if (fetchCallback) fetchCallback(data.data)

        const dur = Math.round(performance.now() - start)
        console.info(`[AutoRefresh] Updated in ${dur}ms`)
      }

      lastRefreshed.value = new Date()
      backoffIndex = 0  // reset backoff on success
      scheduleNext()
    } catch (err) {
      if (err?.name === 'AbortError' || err?.name === 'CanceledError') return

      // Backoff on error
      const delay = BACKOFF_STEPS[Math.min(backoffIndex, BACKOFF_STEPS.length - 1)]
      backoffIndex++
      console.warn(`[AutoRefresh] Error, retrying in ${delay / 1000}s`, err?.message)
      timerId = setTimeout(doRefresh, delay)
    } finally {
      refreshing.value = false
    }
  }

  // ── Scheduling ────────────────────────────────────────
  function scheduleNext() {
    clearTimer()
    if (!enabled.value || isIdle || isHidden) return
    timerId = setTimeout(doRefresh, intervalMs.value)
  }

  function clearTimer() {
    if (timerId) { clearTimeout(timerId); timerId = null }
  }

  // ── Idle detection ────────────────────────────────────
  function resetIdleTimer() {
    if (isIdle) {
      isIdle = false
      if (enabled.value && !isHidden) scheduleNext()
    }
    clearTimeout(idleTimer)
    idleTimer = setTimeout(() => {
      isIdle = true
      clearTimer()
    }, IDLE_TIMEOUT_MS)
  }

  const activityEvents = ['mousemove', 'keydown', 'mousedown', 'touchstart', 'scroll']

  // ── Tab visibility ────────────────────────────────────
  function onVisibilityChange() {
    isHidden = document.hidden
    if (isHidden) {
      clearTimer()
    } else if (enabled.value && !isIdle) {
      // Immediate refresh when tab comes back
      doRefresh()
    }
  }

  // ── Toggle watcher ────────────────────────────────────
  let toggleDebounce = null
  watch(enabled, (on) => {
    clearTimeout(toggleDebounce)
    toggleDebounce = setTimeout(() => {
      if (on) {
        scheduleNext()
      } else {
        clearTimer()
      }
    }, 200) // debounce rapid toggles
  })

  // ── Lifecycle ─────────────────────────────────────────
  onMounted(() => {
    activityEvents.forEach(e => window.addEventListener(e, resetIdleTimer, { passive: true }))
    document.addEventListener('visibilitychange', onVisibilityChange)
    resetIdleTimer()
  })

  onBeforeUnmount(() => {
    clearTimer()
    clearTimeout(idleTimer)
    clearTimeout(toggleDebounce)
    if (abortController) abortController.abort()
    activityEvents.forEach(e => window.removeEventListener(e, resetIdleTimer))
    document.removeEventListener('visibilitychange', onVisibilityChange)
  })

  return {
    enabled,
    refreshing,
    lastRefreshed,
    intervalMs,
    doRefresh,   // for manual trigger / initial load
  }
}
