/**
 * useTablePageSize(module)
 *
 * Composable that provides a reactive `perPage` ref and `setPerPage()` function
 * for any DataTable. On mount it fetches the user's saved preference (or global
 * default) from the API, then persists changes back automatically.
 *
 * Usage:
 *   const { perPage, perPageOptions, perPageReady } = useTablePageSize('leads')
 *   watch(perPage, () => fetchList(1))   // refetch when it changes
 */
import { ref, onMounted } from 'vue'
import api from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'

const PAGE_SIZE_OPTIONS = [10, 20, 25, 50, 100]

// In-memory cache so navigating back to the same page doesn't re-fetch
const cache = {}

export function useTablePageSize(module) {
  const authStore = useAuthStore()
  const systemDefault = authStore.defaultTablePageSize || 25
  const perPage = ref(cache[module] ?? systemDefault)
  const perPageOptions = PAGE_SIZE_OPTIONS
  const perPageReady = ref(!!cache[module])

  async function load() {
    if (cache[module]) {
      perPage.value = cache[module]
      perPageReady.value = true
      return
    }
    try {
      const { data } = await api.get(`/table-preferences/${module}`)
      perPage.value = Number(data.per_page) || systemDefault
      cache[module] = perPage.value
    } catch {
      // silent — use fallback
    } finally {
      perPageReady.value = true
    }
  }

  async function setPerPage(val) {
    const num = Number(val)
    if (!PAGE_SIZE_OPTIONS.includes(num)) return
    perPage.value = num
    cache[module] = num
    try {
      await api.post(`/table-preferences/${module}`, { per_page: num })
    } catch {
      // silent — preference still applies locally this session
    }
  }

  onMounted(load)

  return { perPage, perPageOptions, perPageReady, setPerPage }
}
