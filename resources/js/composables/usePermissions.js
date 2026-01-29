import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

export function usePermissions() {
  const auth = useAuthStore()

  const can = (permission) =>
    computed(() => auth.permissions.includes(permission))

  return { can }
}
