import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { can, canAll, canAny, canModuleAction, isSuperAdmin } from '@/lib/accessControl'

export function usePermissions() {
  const auth = useAuthStore()
  const user = computed(() => auth.user)

  const canPermission = (permission) => computed(() => can(user.value, permission))
  const canAnyPermission = (permissions = []) => computed(() => canAny(user.value, permissions))
  const canAllPermissions = (permissions = []) => computed(() => canAll(user.value, permissions))
  const canAction = (moduleKey, actionKey, extraKeys = []) =>
    computed(() => canModuleAction(user.value, moduleKey, actionKey, extraKeys))
  const superadmin = computed(() => isSuperAdmin(user.value))

  return {
    user,
    superadmin,
    can: canPermission,
    canAny: canAnyPermission,
    canAll: canAllPermissions,
    canAction,
  }
}
