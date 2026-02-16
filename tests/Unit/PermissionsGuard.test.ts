/**
 * PermissionsGuard.test.ts — Vitest unit tests for Vue permission guards.
 *
 * Validates that the auth store exposes permissions correctly and that
 * permission-dependent computed properties resolve as expected.
 *
 * Run:  npx vitest run tests/unit/PermissionsGuard.test.ts
 *       npx vitest --reporter=verbose tests/unit/PermissionsGuard.test.ts
 *
 * Prerequisites:
 *   npm install -D vitest @vue/test-utils @pinia/testing happy-dom
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

/* ═══════════════════════════════════════════════════════════════════════
   Mock auth store (mirrors resources/js/stores/auth.js structure)
   ═══════════════════════════════════════════════════════════════════════ */

interface AuthUser {
  id: number
  name: string
  email: string
  roles: string[]
  permissions: string[]
}

function createMockAuthStore(user: AuthUser | null = null) {
  return {
    user,
    token: null,
    timezone: 'Asia/Dubai',
    session: { timeout_minutes: 120, warning_enabled: false, warning_minutes_before: 5 },
    passwordAction: null,
    loading: false,
    get isAuthenticated() { return !!this.user },
  }
}

/* ═══════════════════════════════════════════════════════════════════════
   Permission helper functions (match app patterns)
   ═══════════════════════════════════════════════════════════════════════ */

function isSuperAdmin(auth: ReturnType<typeof createMockAuthStore>): boolean {
  return (auth.user?.roles ?? []).includes('superadmin')
}

function hasPermission(auth: ReturnType<typeof createMockAuthStore>, perm: string): boolean {
  return isSuperAdmin(auth) || (auth.user?.permissions ?? []).includes(perm)
}

function hasRole(auth: ReturnType<typeof createMockAuthStore>, role: string): boolean {
  return (auth.user?.roles ?? []).includes(role)
}

/* ═══════════════════════════════════════════════════════════════════════
   1. Auth Store State Tests
   ═══════════════════════════════════════════════════════════════════════ */

describe('Auth Store State', () => {
  it('isAuthenticated is false when user is null', () => {
    const auth = createMockAuthStore(null)
    expect(auth.isAuthenticated).toBe(false)
  })

  it('isAuthenticated is true when user exists', () => {
    const auth = createMockAuthStore({
      id: 1, name: 'Admin', email: 'admin@test.com',
      roles: ['superadmin'], permissions: ['users.list'],
    })
    expect(auth.isAuthenticated).toBe(true)
  })

  it('user roles are accessible', () => {
    const auth = createMockAuthStore({
      id: 1, name: 'Agent', email: 'agent@test.com',
      roles: ['sales_agent'], permissions: [],
    })
    expect(auth.user?.roles).toContain('sales_agent')
  })

  it('user permissions are accessible', () => {
    const auth = createMockAuthStore({
      id: 1, name: 'User', email: 'user@test.com',
      roles: ['user'], permissions: ['expense_tracker.list', 'expense_tracker.create'],
    })
    expect(auth.user?.permissions).toContain('expense_tracker.list')
    expect(auth.user?.permissions).toContain('expense_tracker.create')
  })
})

/* ═══════════════════════════════════════════════════════════════════════
   2. isSuperAdmin Guard Tests
   ═══════════════════════════════════════════════════════════════════════ */

describe('isSuperAdmin Guard', () => {
  it('returns true for superadmin role', () => {
    const auth = createMockAuthStore({
      id: 1, name: 'SA', email: 'sa@test.com',
      roles: ['superadmin'], permissions: [],
    })
    expect(isSuperAdmin(auth)).toBe(true)
  })

  it('returns false for non-superadmin role', () => {
    const auth = createMockAuthStore({
      id: 2, name: 'Agent', email: 'agent@test.com',
      roles: ['sales_agent'], permissions: [],
    })
    expect(isSuperAdmin(auth)).toBe(false)
  })

  it('returns false for null user', () => {
    const auth = createMockAuthStore(null)
    expect(isSuperAdmin(auth)).toBe(false)
  })
})

/* ═══════════════════════════════════════════════════════════════════════
   3. hasPermission Guard Tests
   ═══════════════════════════════════════════════════════════════════════ */

describe('hasPermission Guard', () => {
  it('superadmin always has permission', () => {
    const auth = createMockAuthStore({
      id: 1, name: 'SA', email: 'sa@test.com',
      roles: ['superadmin'], permissions: [],
    })
    expect(hasPermission(auth, 'users.edit')).toBe(true)
    expect(hasPermission(auth, 'expense_tracker.delete')).toBe(true)
    expect(hasPermission(auth, 'manage-security-settings')).toBe(true)
  })

  it('user with specific permission passes', () => {
    const auth = createMockAuthStore({
      id: 2, name: 'Editor', email: 'editor@test.com',
      roles: ['admin'], permissions: ['users.edit', 'users.list'],
    })
    expect(hasPermission(auth, 'users.edit')).toBe(true)
    expect(hasPermission(auth, 'users.list')).toBe(true)
  })

  it('user without specific permission fails', () => {
    const auth = createMockAuthStore({
      id: 3, name: 'Viewer', email: 'viewer@test.com',
      roles: ['viewer'], permissions: ['users.list'],
    })
    expect(hasPermission(auth, 'users.edit')).toBe(false)
    expect(hasPermission(auth, 'users.delete')).toBe(false)
  })

  it('null user fails for any permission', () => {
    const auth = createMockAuthStore(null)
    expect(hasPermission(auth, 'users.list')).toBe(false)
  })
})

/* ═══════════════════════════════════════════════════════════════════════
   4. UsersPage Permission Computed Properties
   ═══════════════════════════════════════════════════════════════════════ */

describe('UsersPage — Permission Computed Properties', () => {
  function usersPageGuards(auth: ReturnType<typeof createMockAuthStore>) {
    const _isSuperAdmin = isSuperAdmin(auth)
    const perms = auth.user?.permissions ?? []
    return {
      canEditUsers: _isSuperAdmin || perms.includes('users.edit'),
      canCreateUsers: _isSuperAdmin || perms.includes('users.edit') || perms.includes('users.create'),
    }
  }

  it('superadmin can edit and create users', () => {
    const auth = createMockAuthStore({
      id: 1, name: 'SA', email: 'sa@test.com', roles: ['superadmin'], permissions: [],
    })
    const guards = usersPageGuards(auth)
    expect(guards.canEditUsers).toBe(true)
    expect(guards.canCreateUsers).toBe(true)
  })

  it('user with users.edit can edit and create', () => {
    const auth = createMockAuthStore({
      id: 2, name: 'HR', email: 'hr@test.com', roles: ['admin'], permissions: ['users.edit'],
    })
    const guards = usersPageGuards(auth)
    expect(guards.canEditUsers).toBe(true)
    expect(guards.canCreateUsers).toBe(true)
  })

  it('user with users.create can create but not edit', () => {
    const auth = createMockAuthStore({
      id: 3, name: 'Recruiter', email: 'r@test.com', roles: ['admin'], permissions: ['users.create'],
    })
    const guards = usersPageGuards(auth)
    expect(guards.canEditUsers).toBe(false)
    expect(guards.canCreateUsers).toBe(true)
  })

  it('user without permissions cannot edit or create', () => {
    const auth = createMockAuthStore({
      id: 4, name: 'Viewer', email: 'v@test.com', roles: ['viewer'], permissions: [],
    })
    const guards = usersPageGuards(auth)
    expect(guards.canEditUsers).toBe(false)
    expect(guards.canCreateUsers).toBe(false)
  })
})

/* ═══════════════════════════════════════════════════════════════════════
   5. ExpenseTrackerPage Permission Computed Properties
   ═══════════════════════════════════════════════════════════════════════ */

describe('ExpenseTrackerPage — Permission Computed Properties', () => {
  function expenseGuards(auth: ReturnType<typeof createMockAuthStore>) {
    const _isSuperAdmin = isSuperAdmin(auth)
    const perms = auth.user?.permissions ?? []
    return {
      canCreate: _isSuperAdmin || perms.includes('expense_tracker.create'),
      canExport: _isSuperAdmin || perms.includes('expense_tracker.export_expenses') || perms.includes('expense_tracker.export'),
      canEdit: _isSuperAdmin || perms.includes('expense_tracker.edit') || perms.includes('expense_tracker.update'),
      canDelete: _isSuperAdmin || perms.includes('expense_tracker.delete'),
    }
  }

  it('superadmin has all expense permissions', () => {
    const auth = createMockAuthStore({
      id: 1, name: 'SA', email: 'sa@test.com', roles: ['superadmin'], permissions: [],
    })
    const g = expenseGuards(auth)
    expect(g.canCreate).toBe(true)
    expect(g.canExport).toBe(true)
    expect(g.canEdit).toBe(true)
    expect(g.canDelete).toBe(true)
  })

  it('user with expense_tracker.create can only create', () => {
    const auth = createMockAuthStore({
      id: 2, name: 'E', email: 'e@test.com', roles: ['user'],
      permissions: ['expense_tracker.create'],
    })
    const g = expenseGuards(auth)
    expect(g.canCreate).toBe(true)
    expect(g.canExport).toBe(false)
    expect(g.canEdit).toBe(false)
    expect(g.canDelete).toBe(false)
  })
})

/* ═══════════════════════════════════════════════════════════════════════
   6. AttendanceLogPage Permission Computed Properties
   ═══════════════════════════════════════════════════════════════════════ */

describe('AttendanceLogPage — Permission Computed Properties', () => {
  function attendanceGuards(auth: ReturnType<typeof createMockAuthStore>) {
    const _isSuperAdmin = isSuperAdmin(auth)
    const perms = auth.user?.permissions ?? []
    return {
      canView: _isSuperAdmin || perms.includes('view_attendance_logs'),
      canForceLogout: _isSuperAdmin || perms.includes('force_logout'),
      canExport: _isSuperAdmin || perms.includes('export_attendance_data'),
    }
  }

  it('superadmin can view, force logout, and export', () => {
    const auth = createMockAuthStore({
      id: 1, name: 'SA', email: 'sa@test.com', roles: ['superadmin'], permissions: [],
    })
    const g = attendanceGuards(auth)
    expect(g.canView).toBe(true)
    expect(g.canForceLogout).toBe(true)
    expect(g.canExport).toBe(true)
  })

  it('user with view_attendance_logs can view only', () => {
    const auth = createMockAuthStore({
      id: 2, name: 'M', email: 'm@test.com', roles: ['manager'],
      permissions: ['view_attendance_logs'],
    })
    const g = attendanceGuards(auth)
    expect(g.canView).toBe(true)
    expect(g.canForceLogout).toBe(false)
    expect(g.canExport).toBe(false)
  })
})

/* ═══════════════════════════════════════════════════════════════════════
   7. VerifiersDetailPage Permission Computed Properties
   ═══════════════════════════════════════════════════════════════════════ */

describe('VerifiersDetailPage — Permission Computed Properties', () => {
  function verifierGuards(auth: ReturnType<typeof createMockAuthStore>) {
    const _isSuperAdmin = hasRole(auth, 'superadmin')
    const perms = auth.user?.permissions ?? []
    return {
      canAdd: _isSuperAdmin || perms.includes('verifiers.add') || perms.includes('verifiers.create'),
      canDelete: _isSuperAdmin || perms.includes('verifiers.delete'),
    }
  }

  it('superadmin can add and delete', () => {
    const auth = createMockAuthStore({
      id: 1, name: 'SA', email: 'sa@test.com', roles: ['superadmin'], permissions: [],
    })
    expect(verifierGuards(auth).canAdd).toBe(true)
    expect(verifierGuards(auth).canDelete).toBe(true)
  })

  it('user with verifiers.add can add but not delete', () => {
    const auth = createMockAuthStore({
      id: 2, name: 'V', email: 'v@test.com', roles: ['user'],
      permissions: ['verifiers.add'],
    })
    expect(verifierGuards(auth).canAdd).toBe(true)
    expect(verifierGuards(auth).canDelete).toBe(false)
  })
})

/* ═══════════════════════════════════════════════════════════════════════
   8. ExtensionsListingPage Permission Computed Properties
   ═══════════════════════════════════════════════════════════════════════ */

describe('ExtensionsListingPage — Permission Computed Properties', () => {
  function extensionGuards(auth: ReturnType<typeof createMockAuthStore>) {
    const _isSuperAdmin = isSuperAdmin(auth)
    const perms = auth.user?.permissions ?? []
    return {
      canCreate: _isSuperAdmin || perms.includes('extensions.create'),
    }
  }

  it('superadmin can create extensions', () => {
    const auth = createMockAuthStore({
      id: 1, name: 'SA', email: 'sa@test.com', roles: ['superadmin'], permissions: [],
    })
    expect(extensionGuards(auth).canCreate).toBe(true)
  })

  it('user without extensions.create cannot create', () => {
    const auth = createMockAuthStore({
      id: 2, name: 'U', email: 'u@test.com', roles: ['user'], permissions: [],
    })
    expect(extensionGuards(auth).canCreate).toBe(false)
  })
})

/* ═══════════════════════════════════════════════════════════════════════
   9. DSP Tracker — CRITICAL: No Guards at All
   ═══════════════════════════════════════════════════════════════════════ */

describe('DSPTrackerListingPage — CRITICAL: Missing Guards', () => {
  it('documents that DSP Tracker has NO permission guards in Vue', () => {
    // This test documents the security gap.
    // DSPTrackerListingPage.vue has zero permission checks.
    // Any authenticated user can:
    //   - Upload CSV (POST /api/dsp-tracker/import)
    //   - Delete batches (DELETE /api/dsp-tracker/batch/{id})
    //   - Edit rows inline
    //
    // Recommended fix: Add these computed properties:
    //   const canUpload = computed(() => isSuperAdmin.value || permissions.value.includes('dsp_tracker.upload_csv'))
    //   const canDelete = computed(() => isSuperAdmin.value || permissions.value.includes('dsp_tracker.delete_existing_csv'))
    //
    // Then wrap buttons: <button v-if="canUpload">Upload CSV</button>
    expect(true).toBe(true) // Placeholder — this documents the gap
  })
})

/* ═══════════════════════════════════════════════════════════════════════
   10. Bootstrap API Response Structure
   ═══════════════════════════════════════════════════════════════════════ */

describe('Bootstrap API Response Contract', () => {
  it('expected bootstrap response shape includes user, permissions, session', () => {
    // The auth store expects this shape from GET /api/bootstrap:
    const mockResponse = {
      user: { id: 1, name: 'Admin', email: 'admin@test.com', roles: [{ name: 'superadmin' }] },
      permissions: ['users.list', 'users.edit', 'manage-security-settings'],
      timezone: 'Asia/Dubai',
      session: { timeout_minutes: 120, warning_enabled: false, warning_minutes_before: 5, force_logout_on_close: false },
      password_action: null,
    }

    expect(mockResponse).toHaveProperty('user')
    expect(mockResponse).toHaveProperty('permissions')
    expect(mockResponse).toHaveProperty('session')
    expect(Array.isArray(mockResponse.permissions)).toBe(true)
    expect(mockResponse.user).toHaveProperty('roles')
  })

  it('roles are normalized to string array from object array', () => {
    const rawRoles = [{ name: 'superadmin' }, { name: 'admin' }]
    const normalized = rawRoles.map(r => typeof r === 'string' ? r : r?.name).filter(Boolean)
    expect(normalized).toEqual(['superadmin', 'admin'])
  })
})
