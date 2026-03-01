/**
 * useInactivityLogout
 *
 * Tracks user inactivity and handles:
 *  - Auto-logout after configurable timeout (from auth store → server config)
 *  - Session warning banner N minutes before logout (when enabled)
 *  - Heartbeat to extend session on "Stay Signed In"
 *  - Pauses when tab hidden; resumes on focus
 *
 * Warning flow:
 *   0:00 ─── idle starts ───► (timeout - warning) min ─── show warning ───► timeout min ─── logout
 */
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/lib/axios'

const ACTIVITY_EVENTS = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click', 'focus']

export function useInactivityLogout() {
  const router = useRouter()
  const auth   = useAuthStore()

  // ── Reactive state exposed to UI ─────────────────────
  const showWarning     = ref(false)
  const countdownSecs   = ref(0)
  const extending       = ref(false)

  // ── Timers ───────────────────────────────────────────
  let warningTimer      = null  // fires when warning should appear
  let logoutTimer       = null  // fires when logout should happen
  let countdownInterval = null  // 1s tick for countdown display
  let lastActivity      = Date.now()

  // ── Config (from auth store, populated by bootstrap) ──
  const timeoutMs = computed(() => (auth.session?.timeout_minutes ?? 120) * 60_000)
  const warningEnabled = computed(() => auth.session?.warning_enabled ?? false)
  const warningMs = computed(() => (auth.session?.warning_minutes_before ?? 5) * 60_000)

  // ── Core logic ───────────────────────────────────────
  function resetTimers() {
    lastActivity = Date.now()
    showWarning.value = false
    countdownSecs.value = 0
    clearAllTimers()

    if (!auth.isAuthenticated) return

    if (warningEnabled.value && warningMs.value < timeoutMs.value) {
      // Set timer to show warning
      const warningDelay = timeoutMs.value - warningMs.value
      warningTimer = setTimeout(triggerWarning, warningDelay)
    } else {
      // No warning — just auto-logout at timeout
      logoutTimer = setTimeout(doLogout, timeoutMs.value)
    }
  }

  function triggerWarning() {
    showWarning.value = true
    const warningSecs = Math.round(warningMs.value / 1000)
    countdownSecs.value = warningSecs

    // Start countdown display
    countdownInterval = setInterval(() => {
      countdownSecs.value--
      if (countdownSecs.value <= 0) {
        clearInterval(countdownInterval)
        countdownInterval = null
      }
    }, 1000)

    // Set final logout timer
    logoutTimer = setTimeout(doLogout, warningMs.value)
  }

  async function doLogout() {
    clearAllTimers()
    showWarning.value = false
    try {
      await auth.logout()
    } catch { /* silent */ }
    router.push('/login')
  }

  // ── Stay Signed In (heartbeat) ───────────────────────
  async function staySignedIn() {
    if (extending.value) return
    extending.value = true
    try {
      await api.post('/session/heartbeat')
      resetTimers()
    } catch {
      // If heartbeat fails, still reset local timers to avoid immediate logout
      resetTimers()
    } finally {
      extending.value = false
    }
  }

  function logoutNow() {
    doLogout()
  }

  // ── Activity tracking ────────────────────────────────
  function onActivity() {
    // Any user activity means the session is not inactive.
    // Keep logout strictly inactivity-based, even during warning countdown.
    if (!auth.isAuthenticated) return
    resetTimers()
  }

  // ── Tab visibility ───────────────────────────────────
  function onVisibilityChange() {
    if (document.hidden) return
    // Tab became visible — check if we should have logged out while hidden
    const elapsed = Date.now() - lastActivity
    if (elapsed >= timeoutMs.value) {
      doLogout()
    } else if (warningEnabled.value && elapsed >= (timeoutMs.value - warningMs.value)) {
      if (!showWarning.value) triggerWarning()
    }
  }

  // ── Cleanup ──────────────────────────────────────────
  function clearAllTimers() {
    if (warningTimer)      { clearTimeout(warningTimer); warningTimer = null }
    if (logoutTimer)       { clearTimeout(logoutTimer); logoutTimer = null }
    if (countdownInterval) { clearInterval(countdownInterval); countdownInterval = null }
  }

  // ── Lifecycle ────────────────────────────────────────
  onMounted(() => {
    ACTIVITY_EVENTS.forEach(e => window.addEventListener(e, onActivity, { passive: true }))
    document.addEventListener('visibilitychange', onVisibilityChange)
    resetTimers()
  })

  onUnmounted(() => {
    ACTIVITY_EVENTS.forEach(e => window.removeEventListener(e, onActivity))
    document.removeEventListener('visibilitychange', onVisibilityChange)
    clearAllTimers()
  })

  const totalWarningSecs = computed(() => Math.round(warningMs.value / 1000))

  return {
    showWarning,
    countdownSecs,
    totalWarningSecs,
    extending,
    staySignedIn,
    logoutNow,
  }
}
