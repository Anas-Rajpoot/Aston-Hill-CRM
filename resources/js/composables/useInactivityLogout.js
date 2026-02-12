/**
 * Auto-logout after a period of user inactivity (no mouse, keyboard, touch, or scroll).
 * Use inside the main app layout so it applies to all authenticated pages.
 */
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const IDLE_TIMEOUT_MS = 30 * 60 * 1000 // 30 minutes
const CHECK_INTERVAL_MS = 60 * 1000    // check every 1 minute

export function useInactivityLogout(options = {}) {
  const timeoutMs = options.timeoutMs ?? IDLE_TIMEOUT_MS
  const checkIntervalMs = options.checkIntervalMs ?? CHECK_INTERVAL_MS

  const router = useRouter()
  const auth = useAuthStore()
  const lastActivityAt = ref(Date.now())
  let checkTimer = null
  let boundOnActivity = null

  function onActivity() {
    lastActivityAt.value = Date.now()
  }

  function checkIdle() {
    if (!auth.isAuthenticated) return
    const idle = Date.now() - lastActivityAt.value
    if (idle >= timeoutMs) {
      stop()
      auth.logout()
      router.push('/login')
    }
  }

  function start() {
    if (!auth.isAuthenticated) return
    lastActivityAt.value = Date.now()
    boundOnActivity = onActivity
    const events = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click']
    events.forEach((e) => window.addEventListener(e, boundOnActivity))
    checkTimer = setInterval(checkIdle, checkIntervalMs)
  }

  function stop() {
    if (boundOnActivity) {
      const events = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click']
      events.forEach((e) => window.removeEventListener(e, boundOnActivity))
      boundOnActivity = null
    }
    if (checkTimer) {
      clearInterval(checkTimer)
      checkTimer = null
    }
  }

  onMounted(() => {
    start()
  })

  onUnmounted(() => {
    stop()
  })

  return { lastActivityAt, start, stop }
}
