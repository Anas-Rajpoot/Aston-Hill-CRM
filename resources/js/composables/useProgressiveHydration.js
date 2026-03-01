import { onMounted, onUnmounted, ref } from 'vue'

function runWhenIdle(callback, timeout = 1200) {
  if (typeof window === 'undefined') return () => {}
  if (typeof window.requestIdleCallback === 'function') {
    const id = window.requestIdleCallback(callback, { timeout })
    return () => window.cancelIdleCallback?.(id)
  }
  const id = window.setTimeout(callback, Math.min(timeout, 250))
  return () => window.clearTimeout(id)
}

export function useProgressiveHydration({
  strategy = 'visible-or-idle',
  rootMargin = '240px',
  idleTimeout = 1500,
} = {}) {
  const isHydrated = ref(false)
  const targetRef = ref(null)

  let stopIdle = null
  let observer = null

  function hydrateNow() {
    if (isHydrated.value) return
    isHydrated.value = true
    if (observer) {
      observer.disconnect()
      observer = null
    }
    if (stopIdle) {
      stopIdle()
      stopIdle = null
    }
  }

  function setupVisibilityHydration() {
    if (typeof window === 'undefined') return
    if (typeof window.IntersectionObserver !== 'function') {
      return
    }
    observer = new window.IntersectionObserver((entries) => {
      const entry = entries?.[0]
      if (entry?.isIntersecting) hydrateNow()
    }, { rootMargin })
    if (targetRef.value) observer.observe(targetRef.value)
  }

  function setupIdleHydration() {
    stopIdle = runWhenIdle(() => hydrateNow(), idleTimeout)
  }

  function bindInteractionHydration() {
    // Bind to container-level handlers: @focusin, @click, @pointerenter, etc.
    hydrateNow()
  }

  onMounted(() => {
    if (strategy === 'visible' || strategy === 'visible-or-idle') {
      setupVisibilityHydration()
    }
    if (strategy === 'idle' || strategy === 'visible-or-idle') {
      setupIdleHydration()
    }
    if (strategy === 'interaction') {
      // Pure interaction mode: caller triggers bindInteractionHydration.
      // Intentionally no observer/idle setup.
    }
  })

  onUnmounted(() => {
    if (observer) observer.disconnect()
    if (stopIdle) stopIdle()
    observer = null
    stopIdle = null
  })

  return {
    isHydrated,
    hydrateNow,
    targetRef,
    bindInteractionHydration,
  }
}

