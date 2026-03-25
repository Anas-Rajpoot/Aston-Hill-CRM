function normalizeKey(value) {
  return String(value ?? '')
    .trim()
    .toLowerCase()
    .replace(/[\s_]+/g, '-')
}

function toPermissionSet(user) {
  const permissions = Array.isArray(user?.permissions) ? user.permissions : []
  return new Set(permissions.map((permission) => normalizeKey(permission)))
}

export function isSuperAdmin(user) {
  const roles = Array.isArray(user?.roles) ? user.roles : []
  return roles.some((role) => {
    const roleName = typeof role === 'string'
      ? role
      : role?.name ?? role?.slug ?? role?.key ?? ''
    return normalizeKey(roleName) === 'superadmin'
  })
}

export function canAny(user, permissionKeys = []) {
  if (isSuperAdmin(user)) return true
  const permissionSet = toPermissionSet(user)
  if (!permissionSet.size) return false

  const normalizedKeys = permissionKeys.map((key) => normalizeKey(key)).filter(Boolean)
  return normalizedKeys.some((key) => permissionSet.has(key))
}

export function canAll(user, permissionKeys = []) {
  if (isSuperAdmin(user)) return true
  const permissionSet = toPermissionSet(user)
  if (!permissionSet.size) return false

  const normalizedKeys = permissionKeys.map((key) => normalizeKey(key)).filter(Boolean)
  return normalizedKeys.every((key) => permissionSet.has(key))
}

export function can(user, permissionKey) {
  return canAny(user, [permissionKey])
}

export function hasModulePermission(user, modulePrefixes = []) {
  if (isSuperAdmin(user)) return true
  const permissionSet = toPermissionSet(user)
  if (!permissionSet.size) return false

  const normalizedPrefixes = modulePrefixes.map((prefix) => normalizeKey(prefix)).filter(Boolean)
  if (!normalizedPrefixes.length) return false

  for (const permission of permissionSet) {
    if (normalizedPrefixes.some((prefix) => permission === prefix || permission.startsWith(`${prefix}.`))) {
      return true
    }
  }

  return false
}

const MODULE_ALIASES = {
  lead: ['lead-submissions', 'lead'],
  field: ['field-submissions', 'field'],
  support: ['customer-support-requests', 'customer-support', 'customer-support-requests', 'customer-support'],
  vas: ['vas-requests', 'vas'],
  special: ['special-requests', 'special'],
  'order-status': ['order-status', 'order_status'],
  'dsp-tracker': ['dsp-tracker', 'dsp_tracker'],
  'verifiers-detail': ['verifiers-detail', 'verifiers_detail', 'verifier_detail', 'verifiers'],
  extensions: ['extensions', 'cisco-extensions', 'cisco_extensions'],
  'attendance-log': ['attendance-log', 'attendance_log', 'attendance'],
  'expense-tracker': ['expense-tracker', 'expense_tracker', 'expenses'],
  'personal-notes': ['personal-notes', 'personal_notes'],
  'email-follow-up': ['email-follow-up', 'email-followups', 'email_follow_up', 'emails_followup'],
  reports: ['reports'],
  clients: ['clients'],
  'all-clients': ['all-clients', 'all_clients', 'accounts'],
}

const ACTION_ALIASES = {
  view: ['view', 'list', 'read'],
  read: ['read', 'list', 'view'],
  edit: ['edit', 'update'],
  update: ['update', 'edit'],
  delete: ['delete'],
  export: ['export', 'bulk-download', 'download'],
  import: ['import', 'upload', 'bulk-upload', 'upload-csv'],
  sample: ['download-sample', 'sample', 'import', 'upload'],
  create: ['create', 'add'],
  bulk_assign: ['bulk-assign', 'bulk_assign', 'assign'],
  template: ['template', 'download-template', 'download_template'],
  apply_filters: ['apply-filters', 'apply_filters', 'apply'],
  reset_filters: ['reset-filters', 'reset_filters', 'reset'],
  advanced_filters: ['advanced-filters', 'advanced_filters'],
  customize_columns: ['customize-columns', 'customize_columns', 'columns'],
}

function candidatePermissionKeys(moduleKey, actionKey) {
  const modules = MODULE_ALIASES[moduleKey] ?? [moduleKey]
  const actions = ACTION_ALIASES[actionKey] ?? [actionKey]
  const keys = []

  modules.forEach((moduleAlias) => {
    actions.forEach((actionAlias) => {
      keys.push(`${moduleAlias}.${actionAlias}`)
    })
  })

  return keys
}

export function canModuleAction(user, moduleKey, actionKey, extraKeys = []) {
  return canAny(user, [...candidatePermissionKeys(moduleKey, actionKey), ...extraKeys])
}

export function canAccessSubmissionHub(user) {
  if (isSuperAdmin(user)) return true

  // Hub contains create forms only. Listing permission alone must not grant hub access.
  return canAny(user, [
    'lead-submissions.create',
    'lead.create',
    'field-submissions.create',
    'field.create',
    'customer_support_requests.create',
    'customer-support-requests.create',
    'customer_support.create',
    'customer-support.create',
    'vas_requests.create',
    'vas-requests.create',
    'vas.create',
    'special_requests.create',
    'special-requests.create',
    'special.create',
    'submissions.create',
  ])
}

const ROUTE_ACCESS_RULES = [
  { prefix: '/lead-submissions', modules: ['lead-submissions', 'lead'] },
  { prefix: '/field-submissions', modules: ['field-submissions', 'field'] },
  { prefix: '/customer-support', modules: ['customer_support_requests', 'customer-support', 'customer-support-requests', 'customer_support'] },
  { prefix: '/vas-requests', modules: ['vas_requests', 'vas-requests', 'vas'] },
  { prefix: '/special-requests', modules: ['special_requests', 'special-requests', 'special'] },
  { prefix: '/all-clients', modules: ['all-clients', 'all_clients', 'accounts'] },
  { prefix: '/clients', modules: ['clients'] },
  { prefix: '/order-status', modules: ['order-status', 'order_status'] },
  { prefix: '/dsp-tracker', modules: ['dsp_tracker', 'dsp-tracker'] },
  { prefix: '/verifiers-detail', modules: ['verifier_detail', 'verifiers_detail', 'verifiers-detail', 'gsm_verifiers'] },
  { prefix: '/cisco-extensions', modules: ['cisco_extensions', 'cisco-extensions', 'extensions'] },
  { prefix: '/attendance-log', modules: ['attendance_log', 'attendance-log', 'attendance'] },
  { prefix: '/expenses', modules: ['expenses', 'expense_tracker', 'expense-tracker'] },
  { prefix: '/personal-notes', modules: ['personal-notes', 'personal_notes'] },
  { prefix: '/email-followups', modules: ['email_follow_up', 'email-followups', 'email-follow-up'] },
  { prefix: '/reports', modules: ['reports'] },
  { prefix: '/users', modules: ['users'] },
  { prefix: '/teams', modules: ['teams'] },
  { prefix: '/notifications', modules: ['notifications'] },
]

export function canAccessRoute(user, path) {
  if (path === '/' || path === '/dashboard') return true
  if (path === '/notifications') return true // all authenticated users can view their notifications
  if (isSuperAdmin(user)) return true

  if (path.startsWith('/submissions')) {
    return canAccessSubmissionHub(user)
  }

  // Settings and role administration are superadmin-only.
  if (
    path.startsWith('/settings') ||
    path.startsWith('/roles') ||
    path.startsWith('/permissions')
  ) {
    return false
  }

  const matchedRule = ROUTE_ACCESS_RULES.find((rule) => path.startsWith(rule.prefix))
  if (!matchedRule) return false

  return hasModulePermission(user, matchedRule.modules)
}
