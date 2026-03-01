import { ref } from 'vue'

export function useDeferredQuery(queryFn) {
  const started = ref(false)
  const loading = ref(false)
  const loaded = ref(false)
  const error = ref(null)

  async function run(...args) {
    if (loading.value || loaded.value) return null
    started.value = true
    loading.value = true
    error.value = null
    try {
      const result = await queryFn(...args)
      loaded.value = true
      return result
    } catch (e) {
      error.value = e
      throw e
    } finally {
      loading.value = false
    }
  }

  async function runForced(...args) {
    if (loading.value) return null
    started.value = true
    loading.value = true
    error.value = null
    try {
      const result = await queryFn(...args)
      loaded.value = true
      return result
    } catch (e) {
      error.value = e
      throw e
    } finally {
      loading.value = false
    }
  }

  function reset() {
    started.value = false
    loading.value = false
    loaded.value = false
    error.value = null
  }

  return {
    started,
    loading,
    loaded,
    error,
    run,
    runForced,
    reset,
  }
}

