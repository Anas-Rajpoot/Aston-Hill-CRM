/**
 * useApiRequest()
 *
 * Composable providing debounced, cancellable API requests.
 *
 * Features:
 * - Automatic cancellation of in-flight requests when new ones are made
 * - Configurable debounce delay
 * - Loading / error state tracking
 * - AbortController-based cancellation (no deprecated cancel tokens)
 *
 * Usage:
 *   const { data, loading, error, execute, cancel } = useApiRequest()
 *   await execute(() => api.get('/lead-submissions', { params, signal }))
 */
import { ref, onUnmounted } from 'vue'

export function useApiRequest() {
  const data = ref(null)
  const loading = ref(false)
  const error = ref(null)
  let abortController = null
  let debounceTimer = null

  /**
   * Execute an API call. Cancels any previous in-flight request.
   * @param {Function} apiFn - receives { signal } and should return a promise
   * @param {number} debounceMs - debounce delay (0 = immediate)
   */
  function execute(apiFn, debounceMs = 0) {
    return new Promise((resolve, reject) => {
      // Clear any pending debounce
      if (debounceTimer) {
        clearTimeout(debounceTimer)
        debounceTimer = null
      }

      const run = async () => {
        // Cancel previous in-flight request
        if (abortController) {
          abortController.abort()
        }
        abortController = new AbortController()
        const { signal } = abortController

        loading.value = true
        error.value = null

        try {
          const result = await apiFn({ signal })
          data.value = result.data ?? result
          loading.value = false
          resolve(data.value)
        } catch (err) {
          if (err?.name === 'CanceledError' || err?.code === 'ERR_CANCELED' || signal.aborted) {
            // Silently ignore cancelled requests
            return
          }
          error.value = err
          loading.value = false
          reject(err)
        }
      }

      if (debounceMs > 0) {
        debounceTimer = setTimeout(run, debounceMs)
      } else {
        run()
      }
    })
  }

  function cancel() {
    if (debounceTimer) {
      clearTimeout(debounceTimer)
      debounceTimer = null
    }
    if (abortController) {
      abortController.abort()
      abortController = null
    }
    loading.value = false
  }

  // Auto-cleanup on component unmount
  onUnmounted(() => {
    cancel()
  })

  return { data, loading, error, execute, cancel }
}

/**
 * Creates a debounced version of a function.
 * @param {Function} fn - function to debounce
 * @param {number} ms - delay in milliseconds
 * @returns {Function} debounced function with .cancel() method
 */
export function debounce(fn, ms = 400) {
  let timer = null

  const debounced = (...args) => {
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => {
      timer = null
      fn(...args)
    }, ms)
  }

  debounced.cancel = () => {
    if (timer) {
      clearTimeout(timer)
      timer = null
    }
  }

  return debounced
}
