<script setup>
/**
 * Users & Employees listing page — Full rewrite with:
 * - Scrollable button row (Import, Export, Template, Bulk Activate/Deactivate, Add User)
 * - 5 dashboard cards (Total, Active, Inactive, Pending, Total Target MRC)
 * - Advanced filters with monthly target range
 * - Sortable/paginated table with inline editing + monthly_target column
 * - OTP-verified delete modal
 * - Import CSV modal with validation/error display
 * - Export CSV (streamed)
 * - Template download (CSV with headers + 2 sample rows)
 * - Monthly target edit modal with history
 * - Column visibility toggle
 * - Responsive design, green theme consistency
 * - Permissions enforcement via canModuleAction
 */
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { canModuleAction } from '@/lib/accessControl'
import usersApi from '@/services/usersApi'
import api from '@/lib/axios'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import Toast from '@/components/Toast.vue'
import { formatUserDate, formatSystemDateTime } from '@/lib/dateFormat'
import TruncatedText from '@/components/TruncatedText.vue'
import DateInputDdMmYyyy from '@/components/DateInputDdMmYyyy.vue'
import { useProgressiveHydration } from '@/composables/useProgressiveHydration'
import { useDeferredQuery } from '@/composables/useDeferredQuery'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

// ── Toast ──
const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

// ── Hydration ──
const statsHydration = useProgressiveHydration({ strategy: 'visible-or-idle', idleTimeout: 900 })
const advancedFiltersHydration = useProgressiveHydration({ strategy: 'visible-or-idle', idleTimeout: 1200 })

// ── Constants ──
const TABLE_MODULE = 'users'
const perPageOptions = ref([10, 20, 25, 50, 100])
const ADD_USER_DEPARTMENTS = [
  { value: 'sales', label: 'Sales' },
  { value: 'backoffice', label: 'Back Office' },
  { value: 'field', label: 'Field' },
  { value: 'csr', label: 'CSR' },
  { value: 'admin', label: 'Admin' },
  { value: 'it', label: 'IT' },
]

// ── State: Table data ──
const users = ref([])
const roles = ref([])
const stats = ref({ total: 0, active: 0, inactive: 0, pending: 0, total_target_mrc: 0 })
const pagination = ref({ current_page: 1, last_page: 1, per_page: auth.defaultTablePageSize || 25, total: 0 })
const filterOptions = ref({ statuses: [], roles: [], departments: [], managers: [], team_leaders: [] })
const allColumns = ref([])
const visibleColumns = ref(['id', 'name', 'email', 'phone', 'country', 'roles', 'status', 'last_login_at', 'created_at'])
const sort = ref('name')
const order = ref('asc')
const filters = ref({
  name: '', email: '', role: '', status: '', country: '', department: '',
  manager_id: '', team_leader_id: '',
  joining_from: '', joining_to: '',
  created_from: '', created_to: '',
  target_min: '', target_max: '',
  target_month_from: '', target_month_to: '',
})
const filtersVisible = ref(false)
const columnModalVisible = ref(false)
const loading = ref(true)
const selectedIds = ref([])
const bulkLoading = ref(false)
const actionMenuOpen = ref(null)

// ── Inline edit state ──
const editingCell = ref(null)
const editValue = ref('')
const savingCell = ref(false)

// ── History modal ──
const historyUserId = ref(null)
const historyLogs = ref([])
const historyLoading = ref(false)

// ── OTP delete modal ──
const userToDelete = ref(null)
const deleteOtp = ref('')
const deleteOtpSent = ref(false)
const deleteOtpValue = ref('')
const deleteLoading = ref(false)
const deleteError = ref('')

// ── Reset password modal ──
const userToResetPassword = ref(null)
const resetPasswordLoading = ref(false)

// ── User detail popup ──
const detailUser = ref(null)
const detailLoading = ref(false)
const detailError = ref(null)

// ── Import modal ──
const importModalOpen = ref(false)
const importFile = ref(null)
const importLoading = ref(false)
const importResult = ref(null)

// ── Monthly target modal ──
const targetModalOpen = ref(false)
const targetUser = ref(null)
const targetValue = ref('')
const targetMonth = ref('')
const targetLoading = ref(false)
const targetHistory = ref([])
const targetHistoryLoading = ref(false)
const bulkTargetModalOpen = ref(false)
const bulkTargetValue = ref('')
const bulkTargetMonth = ref('')
const bulkTargetLoading = ref(false)

// ── Add user modal ──
const addUserModalOpen = ref(false)
const addUserForm = ref({ name: '', email: '', phone: '', country: '', department: '', status: 'approved', roles: [], password: '', password_confirmation: '' })
const addUserRolesDropdownOpen = ref(false)
const addUserLoading = ref(false)
const addUserError = ref('')
const addUserFieldErrors = ref({})
const addUserSuccess = ref(false)
const addUserCountries = ref([])
const addUserRolesDropdownRef = ref(null)

// ── Edit user modal ──
const editUserModalOpen = ref(false)
const editUserId = ref(null)
const editUserLoading = ref(false)
const editUserSaving = ref(false)
const editUserError = ref('')
const editUserFieldErrors = ref({})
const editUserRolesDropdownOpen = ref(false)
const editUserRolesDropdownRef = ref(null)
const editUserCountries = ref([])
const editUserForm = ref({ name: '', email: '', phone: '', country: '', department: '', status: 'approved', roles: [], password: '', password_confirmation: '' })

// ── Password policy ──
const passwordPolicy = ref({ min_length: 8, require_uppercase: true, require_number: true, require_special: true })
const passwordPolicyHint = computed(() => {
  const parts = [`Minimum ${passwordPolicy.value.min_length || 8} characters`]
  if (passwordPolicy.value.require_uppercase) parts.push('1 uppercase letter')
  if (passwordPolicy.value.require_number) parts.push('1 number')
  if (passwordPolicy.value.require_special) parts.push('1 special character')
  return parts.join(', ')
})

// ── Permissions ──
const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) ? r.includes('superadmin') : false
})
const canCreate = computed(() => canModuleAction(auth.user, 'users', 'create'))
const canEdit = computed(() => canModuleAction(auth.user, 'users', 'edit'))
const canDelete = computed(() => canModuleAction(auth.user, 'users', 'delete'))
const canExport = computed(() => canModuleAction(auth.user, 'users', 'export'))
const canImport = computed(() => canModuleAction(auth.user, 'users', 'import'))

function canEditRow(user) {
  if (!user) return false
  if (!canEdit.value) return false
  const roleNames = (user.roles || []).map((r) => (typeof r === 'string' ? r : r?.name))
  if (roleNames.includes('superadmin')) return auth.user?.id === user.id
  return true
}

// ── Helpers ──
const statusLabel = (s) => {
  if (s === 'approved') return 'Active'
  if (s === 'rejected') return 'Inactive'
  return 'Pending Approval'
}
const statusBadgeClass = (s) => {
  if (s === 'approved') return 'bg-brand-primary-light text-brand-primary-hover border-brand-primary-muted'
  if (s === 'rejected') return 'bg-red-50 text-red-700 border-red-200'
  return 'bg-gray-100 text-gray-700 border-gray-200'
}
const formatDate = (d) => formatUserDate(d, '-')
const formatDateTime = (d) => formatSystemDateTime(d, '-')
const formatDetailDateTime = (iso) => formatSystemDateTime(iso, '—')
const userIdDisplay = (id) => id ? `USR${String(id).padStart(3, '0')}` : '—'
const getInitials = (name) => {
  if (!name) return '?'
  return name.split(/\s+/).map(n => n[0]).slice(0, 2).join('').toUpperCase()
}
const formatRoleNameForAdd = (name) => name ? name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : ''
const formatCurrency = (v) => {
  const n = Number(v)
  return Number.isFinite(n) ? n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00'
}

const columnLabels = {
  id: 'SR', name: 'User', email: 'Email', phone: 'Phone', country: 'Country',
  roles: 'Assigned Roles', status: 'Status', last_login_at: 'Last Login',
  created_at: 'Created Date', employee_number: 'Employee ID', department: 'Department',
  extension: 'Extension', joining_date: 'Joining Date', terminate_date: 'Terminate Date',
  manager: 'Manager', team_leader: 'Team Leader', monthly_target: 'Monthly Target',
}

const sortableCols = ['id', 'name', 'email', 'phone', 'country', 'status', 'created_at', 'employee_number', 'department', 'extension', 'joining_date', 'terminate_date', 'manager', 'team_leader', 'last_login_at', 'monthly_target']
const editableFields = ['name', 'email', 'phone', 'country', 'status', 'employee_number', 'department', 'extension', 'joining_date', 'terminate_date', 'monthly_target']

function isDropdownField(field) { return field === 'status' }
function getStatusOptions() {
  return filterOptions.value.statuses || [
    { value: 'approved', label: 'Active' },
    { value: 'rejected', label: 'Inactive' },
    { value: 'pending', label: 'Pending Approval' },
  ]
}

const assignableRolesForAdd = computed(() => {
  const list = (roles.value ?? []).filter(r => (r?.name ?? '').toLowerCase() !== 'superadmin')
  const seen = new Map()
  for (const r of list) {
    if (!r?.id) continue
    const key = (r.name ?? '').toLowerCase().replace(/[\s_-]+/g, '')
    if (!seen.has(key)) seen.set(key, r)
  }
  return Array.from(seen.values())
})

function getCurrentMonth() {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
}

// ── API params builder ──
function buildParams() {
  const p = {
    page: pagination.value.current_page,
    per_page: pagination.value.per_page,
    sort: sort.value,
    order: order.value,
    columns: visibleColumns.value,
  }
  if (filters.value.name) p.name = filters.value.name
  if (filters.value.email) p.email = filters.value.email
  if (filters.value.role) p.role = filters.value.role
  if (filters.value.status) p.status = filters.value.status
  if (filters.value.country) p.country = filters.value.country
  if (filters.value.department) p.department = filters.value.department
  if (filters.value.manager_id) p.manager_id = filters.value.manager_id
  if (filters.value.team_leader_id) p.team_leader_id = filters.value.team_leader_id
  if (filters.value.joining_from) p.joining_from = filters.value.joining_from
  if (filters.value.joining_to) p.joining_to = filters.value.joining_to
  if (filters.value.created_from) p.created_from = filters.value.created_from
  if (filters.value.created_to) p.created_to = filters.value.created_to
  if (filters.value.target_min) p.target_min = filters.value.target_min
  if (filters.value.target_max) p.target_max = filters.value.target_max
  if (filters.value.target_month_from) p.target_month_from = filters.value.target_month_from
  if (filters.value.target_month_to) p.target_month_to = filters.value.target_month_to
  return p
}

// ── Data loading ──
async function load() {
  loading.value = true
  try {
    const { data } = await usersApi.index(buildParams())
    users.value = data.users ?? []
    pagination.value = data.pagination ?? pagination.value
    stats.value = data.stats ?? stats.value
    if (data.roles) roles.value = data.roles
    selectedIds.value = []
  } finally {
    loading.value = false
  }
}

async function loadFilters() {
  try {
    const data = await usersApi.filters()
    filterOptions.value = { statuses: data.statuses ?? [], roles: data.roles ?? [], departments: data.departments ?? [], managers: data.managers ?? [], team_leaders: data.team_leaders ?? [] }
  } catch {}
}

async function loadColumns() {
  try {
    const data = await usersApi.columns()
    allColumns.value = data.all_columns ?? []
    visibleColumns.value = data.visible_columns ?? visibleColumns.value
  } catch {}
}

async function loadPasswordPolicy() {
  try {
    const { data } = await api.get('/security-settings')
    const policy = data?.data ?? {}
    const min = Number(policy.min_length)
    passwordPolicy.value = {
      min_length: Number.isFinite(min) && min > 0 ? min : 8,
      require_uppercase: Boolean(policy.require_uppercase ?? true),
      require_number: Boolean(policy.require_number ?? true),
      require_special: Boolean(policy.require_special ?? true),
    }
  } catch {
    passwordPolicy.value = { min_length: 8, require_uppercase: true, require_number: true, require_special: true }
  }
}

const deferredSecondaryBootstrap = useDeferredQuery(async () => {
  await Promise.all([loadFilters(), loadPasswordPolicy()])
})
function hydrateSecondaryData() { deferredSecondaryBootstrap.run().catch(() => {}) }

async function loadTablePreference() {
  try {
    const { data } = await api.get(`/table-preferences/${TABLE_MODULE}`)
    if (data.per_page) pagination.value.per_page = Number(data.per_page)
    if (Array.isArray(data.options) && data.options.length) perPageOptions.value = data.options
  } catch {}
}

// ── Filters ──
function applyFilters() { pagination.value.current_page = 1; load() }
function resetFilters() {
  filters.value = { name: '', email: '', role: '', status: '', country: '', department: '', manager_id: '', team_leader_id: '', joining_from: '', joining_to: '', created_from: '', created_to: '', target_min: '', target_max: '', target_month_from: '', target_month_to: '' }
  pagination.value.current_page = 1
  load()
}

// ── Sorting ──
function toggleSort(col) {
  if (!sortableCols.includes(col)) return
  if (sort.value === col) order.value = order.value === 'asc' ? 'desc' : 'asc'
  else { sort.value = col; order.value = 'asc' }
  pagination.value.current_page = 1
  load()
}

// ── Column customizer ──
async function onSaveColumns(cols) {
  try {
    await usersApi.saveColumns(cols)
    visibleColumns.value = cols
    pagination.value.current_page = 1
    load()
  } catch {}
}

// ── Inline edit ──
function startEdit(user, field) {
  if (!canEditRow(user)) return
  const val = user[field]
  editingCell.value = { userId: user.id, field }
  editValue.value = Array.isArray(val) ? (val.join(', ') || '') : (val ?? '')
}
function cancelEdit() { editingCell.value = null; editValue.value = '' }

async function saveEdit() {
  const { userId, field } = editingCell.value || {}
  if (!userId || !field) return
  savingCell.value = true
  try {
    await usersApi.patch(userId, field, editValue.value === '' ? null : editValue.value)
    const u = users.value.find(r => r.id === userId)
    if (u) {
      if (field === 'status') u.status = editValue.value
      else u[field] = editValue.value === '' ? null : editValue.value
    }
    cancelEdit()
    toast('success', 'Field updated successfully.')
  } catch (e) {
    cancelEdit()
    toast('error', e?.response?.data?.message || 'Failed to update field.')
  } finally { savingCell.value = false }
}

function isEditing(userId, field) {
  const e = editingCell.value
  return e && e.userId === userId && e.field === field
}

// ── Pagination ──
function goToPage(page) {
  if (page < 1 || page > pagination.value.last_page) return
  pagination.value.current_page = page
  load()
}

async function onPerPageChange(event) {
  const newPerPage = Number(event.target.value)
  pagination.value.per_page = newPerPage
  pagination.value.current_page = 1
  try { await api.post(`/table-preferences/${TABLE_MODULE}`, { per_page: newPerPage }) } catch {}
  load()
}

// ── Selection ──
function toggleSelectAll() {
  if (selectedIds.value.length === users.value.length) selectedIds.value = []
  else selectedIds.value = users.value.map(u => u.id)
}
function toggleSelect(id) {
  const idx = selectedIds.value.indexOf(id)
  if (idx >= 0) selectedIds.value.splice(idx, 1)
  else selectedIds.value.push(id)
}

// ── Bulk activate/deactivate ──
async function bulkActivate() {
  if (!selectedIds.value.length) return
  bulkLoading.value = true
  try {
    const { data } = await usersApi.bulkActivate(selectedIds.value)
    toast('success', data?.message || 'Users activated.')
    await load()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to activate users.')
  } finally { bulkLoading.value = false }
}

async function bulkDeactivate() {
  if (!selectedIds.value.length) return
  bulkLoading.value = true
  try {
    const { data } = await usersApi.bulkDeactivate(selectedIds.value)
    toast('success', data?.message || 'Users deactivated.')
    await load()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to deactivate users.')
  } finally { bulkLoading.value = false }
}

function openBulkTargetModal() {
  if (!selectedIds.value.length || !canEdit.value) return
  bulkTargetMonth.value = getCurrentMonth()
  bulkTargetValue.value = ''
  bulkTargetModalOpen.value = true
}

function closeBulkTargetModal() {
  if (bulkTargetLoading.value) return
  bulkTargetModalOpen.value = false
}

async function submitBulkTarget() {
  if (!selectedIds.value.length || bulkTargetValue.value === '' || !bulkTargetMonth.value) return
  const selectedCount = selectedIds.value.length
  const appliedMonth = bulkTargetMonth.value
  const appliedTarget = Number(bulkTargetValue.value)
  bulkTargetLoading.value = true
  try {
    const { data } = await usersApi.bulkAssignMonthlyTarget({
      ids: selectedIds.value,
      monthly_target: appliedTarget,
      month: appliedMonth,
    })
    closeBulkTargetModal()
    const updatedCount = Number(data?.count ?? selectedCount)
    toast('success', `Assigned AED ${formatCurrency(appliedTarget)} target for ${appliedMonth} to ${updatedCount} user(s).`)
    await load()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to assign monthly target.')
  } finally {
    bulkTargetLoading.value = false
  }
}

// ── OTP Delete ──
function openDeleteModal(user) {
  if (!user?.id || (user.roles || []).includes('superadmin')) return
  closeActionMenu()
  userToDelete.value = user
  deleteOtp.value = ''
  deleteOtpSent.value = false
  deleteOtpValue.value = ''
  deleteError.value = ''
}
function closeDeleteModal() {
  if (!deleteLoading.value) {
    userToDelete.value = null
    deleteOtp.value = ''
    deleteOtpSent.value = false
    deleteOtpValue.value = ''
    deleteError.value = ''
  }
}

async function requestDeleteOtp() {
  if (!userToDelete.value?.id) return
  deleteLoading.value = true
  deleteError.value = ''
  try {
    const { data } = await usersApi.requestDeleteOtp(userToDelete.value.id)
    deleteOtpSent.value = true
    // In dev mode the OTP is returned in the response
    if (data?.otp) deleteOtpValue.value = data.otp
    toast('info', data?.message || 'OTP sent. Please enter it to confirm deletion.')
  } catch (e) {
    deleteError.value = e?.response?.data?.message || 'Failed to generate OTP.'
  } finally { deleteLoading.value = false }
}

async function confirmOtpDelete() {
  if (!userToDelete.value?.id || !deleteOtp.value) return
  deleteLoading.value = true
  deleteError.value = ''
  try {
    await usersApi.otpDelete(userToDelete.value.id, deleteOtp.value)
    closeDeleteModal()
    toast('success', 'User deleted successfully.')
    await load()
  } catch (e) {
    deleteError.value = e?.response?.data?.message || 'Invalid or expired OTP.'
  } finally { deleteLoading.value = false }
}

// ── Reset password ──
function openResetPasswordModal(user) {
  if (!canEditRow(user)) return
  userToResetPassword.value = user
  closeActionMenu()
}
function closeResetPasswordModal() { if (!resetPasswordLoading.value) userToResetPassword.value = null }

async function confirmResetPassword() {
  const user = userToResetPassword.value
  if (!user?.id) return
  resetPasswordLoading.value = true
  try {
    await usersApi.sendPasswordReset(user.id)
    userToResetPassword.value = null
    toast('success', 'Password reset email sent successfully.')
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to send password reset.')
  } finally { resetPasswordLoading.value = false }
}

// ── User detail popup ──
async function openUserDetail(user) {
  if (!user?.id) return
  closeActionMenu()
  detailUser.value = null; detailError.value = null; detailLoading.value = true
  try {
    const response = await usersApi.show(user.id)
    const userData = response?.data?.user
    if (userData) {
      detailUser.value = { ...user, ...userData, last_login_at: userData.last_login_at ?? user.last_login_at ?? null }
    } else { detailError.value = 'Could not load user details.' }
  } catch { detailError.value = 'Could not load user details.' }
  finally { detailLoading.value = false }
}
function closeUserDetail() {
  detailUser.value = null; detailError.value = null
  if (route.path !== '/users') router.push('/users')
}
function fromDetailEditUser() { const u = detailUser.value; if (u?.id) { closeUserDetail(); openEditUserModal(u) } }
function fromDetailResetPassword() { const u = detailUser.value; if (u) { closeUserDetail(); openResetPasswordModal(u) } }

// ── Import modal ──
function openImportModal() { importModalOpen.value = true; importFile.value = null; importResult.value = null }
function closeImportModal() { if (!importLoading.value) { importModalOpen.value = false; importFile.value = null; importResult.value = null } }
function onImportFileChange(e) { importFile.value = e.target.files?.[0] || null }

async function submitImport() {
  if (!importFile.value) return
  importLoading.value = true
  importResult.value = null
  try {
    const { data } = await usersApi.bulkImport(importFile.value)
    importResult.value = data
    if (data.imported > 0) {
      toast('success', `${data.imported} user(s) imported successfully.`)
      await load()
    }
  } catch (e) {
    importResult.value = { message: e?.response?.data?.message || 'Import failed.', imported: 0, errors: e?.response?.data?.errors ? [{ row: 0, error: e.response.data.message }] : [] }
  } finally { importLoading.value = false }
}

// ── Export ──
async function handleExport() {
  try {
    const response = await usersApi.exportCsv({ status: filters.value.status, role: filters.value.role })
    const blob = new Blob([response.data], { type: 'text/csv' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `users_export_${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
    toast('success', 'Export downloaded.')
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Export failed.')
  }
}

// ── Template download ──
async function handleTemplateDownload() {
  try {
    const response = await usersApi.importTemplate()
    const blob = new Blob([response.data], { type: 'text/csv' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'users_import_template.csv'
    a.click()
    URL.revokeObjectURL(url)
    toast('success', 'Template downloaded.')
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Template download failed.')
  }
}

// ── Monthly target modal ──
async function openTargetModal(user) {
  if (!canEditRow(user)) return
  closeActionMenu()
  targetUser.value = user
  targetValue.value = user.monthly_target ?? ''
  targetMonth.value = getCurrentMonth()
  targetModalOpen.value = true
  targetHistory.value = []
  targetHistoryLoading.value = true
  try {
    const data = await usersApi.monthlyTargetHistory(user.id)
    targetHistory.value = data.data ?? []
  } catch { targetHistory.value = [] }
  finally { targetHistoryLoading.value = false }
}
function closeTargetModal() { if (!targetLoading.value) { targetModalOpen.value = false; targetUser.value = null } }

async function submitTarget() {
  if (!targetUser.value?.id) return
  targetLoading.value = true
  try {
    await usersApi.updateMonthlyTarget(targetUser.value.id, { monthly_target: Number(targetValue.value), month: targetMonth.value })
    const u = users.value.find(r => r.id === targetUser.value.id)
    if (u) u.monthly_target = Number(targetValue.value)
    closeTargetModal()
    toast('success', 'Monthly target updated.')
    // Refresh stats for total_target_mrc
    load()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to update target.')
  } finally { targetLoading.value = false }
}

// ── Add user ──
function getRoleIdsFromUserRoles(userRoles) {
  if (!Array.isArray(userRoles)) return []
  const roleLookup = new Map((roles.value ?? []).map(r => [String(r?.name ?? '').toLowerCase(), r?.id]))
  return userRoles.map(role => {
    if (role && typeof role === 'object') return Number(role.id) || null
    const key = String(role ?? '').toLowerCase()
    return roleLookup.get(key) || null
  }).filter(id => Number.isFinite(id))
}

function validatePhoneForUser(value) {
  if (!value) return null
  if (!/^\d{12}$/.test(value)) return 'Must be exactly 12 digits with no spaces (e.g. 971XXXXXXXXX).'
  if (!value.startsWith('971')) return 'Must start with 971.'
  return null
}
function onAddUserPhoneInput(event) {
  addUserForm.value.phone = String(event?.target?.value ?? '').replace(/\D/g, '').slice(0, 12)
  if (addUserFieldErrors.value.phone) addUserFieldErrors.value = { ...addUserFieldErrors.value, phone: '' }
}
function onEditUserPhoneInput(event) {
  editUserForm.value.phone = String(event?.target?.value ?? '').replace(/\D/g, '').slice(0, 12)
  if (editUserFieldErrors.value.phone) editUserFieldErrors.value = { ...editUserFieldErrors.value, phone: '' }
}

async function openAddUserModal() {
  hydrateSecondaryData()
  addUserError.value = ''; addUserFieldErrors.value = {}; addUserSuccess.value = false
  addUserForm.value = { name: '', email: '', phone: '', country: '', department: '', status: 'approved', roles: [], password: '', password_confirmation: '' }
  addUserModalOpen.value = true
  await nextTick()
  addUserForm.value.email = ''; addUserForm.value.password = ''; addUserForm.value.password_confirmation = ''
  if (!addUserCountries.value.length) {
    try { const { data } = await api.get('/countries'); addUserCountries.value = Array.isArray(data) ? data : (data?.data ?? []) } catch { addUserCountries.value = [] }
  }
}
function closeAddUserModal() { addUserModalOpen.value = false; addUserError.value = ''; addUserFieldErrors.value = {}; addUserSuccess.value = false; addUserRolesDropdownOpen.value = false }
function toggleAddUserRole(role) {
  const id = role.id
  const idx = addUserForm.value.roles.indexOf(id)
  if (idx === -1) addUserForm.value.roles = [...addUserForm.value.roles, id]
  else addUserForm.value.roles = addUserForm.value.roles.filter(r => r !== id)
}
function hasAddUserRole(roleId) { return addUserForm.value.roles.includes(roleId) }

function validatePassword(password, errs, prefix = '') {
  const minLength = Number(passwordPolicy.value.min_length) || 8
  if (!password || password.length < minLength) { errs[prefix + 'password'] = `Password must be at least ${minLength} characters.`; return }
  if (passwordPolicy.value.require_uppercase && !/[A-Z]/.test(password)) { errs[prefix + 'password'] = 'Must contain at least one uppercase letter.'; return }
  if (passwordPolicy.value.require_number && !/[0-9]/.test(password)) { errs[prefix + 'password'] = 'Must contain at least one number.'; return }
  if (passwordPolicy.value.require_special && !/[^A-Za-z0-9]/.test(password)) { errs[prefix + 'password'] = 'Must contain at least one special character.' }
}

async function submitAddUser() {
  addUserError.value = ''; addUserFieldErrors.value = {}; addUserSuccess.value = false
  const f = addUserForm.value
  const errs = {}
  if (!f.name?.trim()) errs.name = 'Full name is required.'
  if (!f.email?.trim()) errs.email = 'Email address is required.'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.trim())) errs.email = 'Please enter a valid email address.'
  if (!f.phone?.trim()) errs.phone = 'Phone number is required.'
  else { const phoneErr = validatePhoneForUser(f.phone.trim()); if (phoneErr) errs.phone = phoneErr }
  if (!f.country) errs.country = 'Please select a country.'
  if (!f.department) errs.department = 'Please select a department.'
  if (!(f.roles?.length)) errs.roles = 'Please assign at least one role.'
  validatePassword(f.password, errs)
  if (f.password && f.password !== f.password_confirmation) errs.password_confirmation = 'Password and confirmation do not match.'
  if (Object.keys(errs).length) { addUserFieldErrors.value = errs; addUserError.value = 'Please fix the highlighted errors below.'; return }

  addUserLoading.value = true
  try {
    await usersApi.store({ name: f.name.trim(), email: f.email.trim(), phone: f.phone.trim(), country: f.country, department: f.department, status: f.status || 'approved', roles: f.roles, password: f.password, password_confirmation: f.password_confirmation })
    addUserSuccess.value = true; addUserError.value = ''; addUserFieldErrors.value = {}
    setTimeout(() => { closeAddUserModal(); toast('success', 'User created successfully.'); load() }, 1500)
  } catch (e) {
    const msg = e?.response?.data?.message; const serverErrs = e?.response?.data?.errors
    if (serverErrs) { const mapped = {}; for (const [key, msgs] of Object.entries(serverErrs)) mapped[key] = Array.isArray(msgs) ? msgs[0] : msgs; addUserFieldErrors.value = mapped; addUserError.value = msg || 'Please fix the highlighted errors below.' }
    else addUserError.value = msg || 'Failed to create user. Please try again.'
  } finally { addUserLoading.value = false }
}

// ── Edit user ──
async function openEditUserModal(user) {
  hydrateSecondaryData()
  if (!user?.id || !canEditRow(user)) return
  closeActionMenu()
  editUserId.value = user.id; editUserLoading.value = true; editUserError.value = ''; editUserFieldErrors.value = {}
  editUserRolesDropdownOpen.value = false; editUserModalOpen.value = true
  try {
    const [{ data }, countriesResponse] = await Promise.all([
      usersApi.show(user.id),
      editUserCountries.value.length ? Promise.resolve(null) : api.get('/countries'),
    ])
    if (countriesResponse) { const cd = countriesResponse?.data; editUserCountries.value = Array.isArray(cd) ? cd : (cd?.data ?? []) }
    const userData = data?.user
    if (!userData) throw new Error('missing user data')
    editUserForm.value = { name: userData.name ?? '', email: userData.email ?? '', phone: userData.phone ?? '', country: userData.country ?? '', department: userData.department ?? '', status: userData.status ?? 'approved', roles: getRoleIdsFromUserRoles(userData.roles), password: '', password_confirmation: '' }
  } catch (e) { editUserError.value = e?.response?.data?.message || 'Failed to load user details.' }
  finally { editUserLoading.value = false }
}
function closeEditUserModal() { if (editUserSaving.value) return; editUserModalOpen.value = false; editUserId.value = null; editUserError.value = ''; editUserFieldErrors.value = {}; editUserRolesDropdownOpen.value = false }
function toggleEditUserRole(role) {
  const id = role.id
  const idx = editUserForm.value.roles.indexOf(id)
  if (idx === -1) editUserForm.value.roles = [...editUserForm.value.roles, id]
  else editUserForm.value.roles = editUserForm.value.roles.filter(r => r !== id)
}
function hasEditUserRole(roleId) { return editUserForm.value.roles.includes(roleId) }

async function submitEditUser() {
  if (!editUserId.value) return
  editUserError.value = ''; editUserFieldErrors.value = {}
  const f = editUserForm.value; const errs = {}
  if (!f.name?.trim()) errs.name = 'Full name is required.'
  if (!f.email?.trim()) errs.email = 'Email address is required.'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.trim())) errs.email = 'Please enter a valid email address.'
  if (f.phone?.trim()) { const phoneErr = validatePhoneForUser(f.phone.trim()); if (phoneErr) errs.phone = phoneErr }
  if (f.password?.length) { validatePassword(f.password, errs); if (f.password !== f.password_confirmation) errs.password_confirmation = 'Password and confirmation do not match.' }
  if (Object.keys(errs).length) { editUserFieldErrors.value = errs; editUserError.value = 'Please fix the highlighted errors below.'; return }

  editUserSaving.value = true
  try {
    const payload = { name: f.name.trim(), email: f.email.trim(), phone: f.phone?.trim() || null, country: f.country || null, department: f.department || null, status: f.status || 'approved', roles: f.roles }
    if (f.password?.length) { payload.password = f.password; payload.password_confirmation = f.password_confirmation }
    await usersApi.update(editUserId.value, payload)
    closeEditUserModal(); toast('success', 'User updated successfully.'); await load()
  } catch (e) {
    const msg = e?.response?.data?.message || 'Failed to update user.'; const serverErrs = e?.response?.data?.errors
    if (serverErrs) { const mapped = {}; for (const [key, msgs] of Object.entries(serverErrs)) mapped[key] = Array.isArray(msgs) ? msgs[0] : msgs; editUserFieldErrors.value = mapped; editUserError.value = msg }
    else editUserError.value = msg
  } finally { editUserSaving.value = false }
}

// ── Action menu ──
function toggleActionMenu(id) { actionMenuOpen.value = actionMenuOpen.value === id ? null : id }
function closeActionMenu() { actionMenuOpen.value = null }
function handleClickOutside() { if (actionMenuOpen.value) actionMenuOpen.value = null }

// ── History modal ──
async function openHistory(userId) {
  historyUserId.value = userId; historyLoading.value = true; historyLogs.value = []
  try { const data = await usersApi.auditLog(userId); historyLogs.value = data.data ?? [] }
  catch { historyLogs.value = [] }
  finally { historyLoading.value = false }
}
function closeHistory() { historyUserId.value = null; historyLogs.value = [] }

// ── Display helpers ──
function displayCellValue(user, col) {
  const val = user[col]
  if (col === 'status') return statusLabel(val)
  if (col === 'roles') return Array.isArray(val) && val.length ? val.join(', ') : '-'
  if (col === 'last_login_at' || col === 'created_at') return col === 'created_at' ? formatDate(val) : formatDateTime(val)
  if (col === 'joining_date' || col === 'terminate_date') return formatDate(val)
  if (col === 'monthly_target') return formatCurrency(val)
  if (val == null || val === '') return '-'
  return String(val)
}
function rowNumber(index) { return (pagination.value.current_page - 1) * pagination.value.per_page + index + 1 }

// ── Lifecycle ──
onMounted(() => {
  loadTablePreference().then(() => {
    hydrateSecondaryData()
    loadColumns().then(() => load())
  })
  document.addEventListener('click', handleClickOutside)
  if (route.query.updated) { toast('success', `${route.query.updated} updated successfully.`); router.replace({ path: '/users', query: {} }) }
  if (route.query.from === 'employees') { toast('info', 'Employees have been merged into Users & Employees.'); router.replace({ path: '/users', query: {} }) }
})
onUnmounted(() => { document.removeEventListener('click', handleClickOutside) })

watch(() => pagination.value.current_page, load)
watch(() => route.path, (path) => { if (path === '/users') load() })
watch(filtersVisible, (visible) => { if (visible) { advancedFiltersHydration.hydrateNow(); hydrateSecondaryData() } })

// Roles dropdown outside-click handlers
function setupDropdownOutsideClick(openRef, elRef) {
  if (!openRef.value) return
  const el = elRef.value
  const handler = (e) => { if (el && !el.contains(e.target)) { openRef.value = false; document.removeEventListener('click', handler) } }
  setTimeout(() => document.addEventListener('click', handler), 0)
}
watch(addUserRolesDropdownOpen, (open) => { if (open) setupDropdownOutsideClick(addUserRolesDropdownOpen, addUserRolesDropdownRef) })
watch(editUserRolesDropdownOpen, (open) => { if (open) setupDropdownOutsideClick(editUserRolesDropdownOpen, editUserRolesDropdownRef) })
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-4 sm:px-6 py-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 leading-tight">Users &amp; Employees</h1>
        <p class="mt-1 text-sm text-gray-500">Manage system users, employees, assign roles, and control access.</p>
      </div>
    </div>

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />

    <!-- 5 Dashboard Stat Cards -->
    <div ref="statsHydration.targetRef" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
      <template v-if="statsHydration.isHydrated">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm border-t-4 border-t-brand-primary">
          <p class="text-xs font-medium text-gray-500">Total Users</p>
          <p class="mt-1 text-2xl font-bold text-brand-primary">{{ stats.total }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm border-t-4 border-t-brand-primary">
          <p class="text-xs font-medium text-gray-500">Active Users</p>
          <p class="mt-1 text-2xl font-bold text-brand-primary">{{ stats.active }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm border-t-4 border-t-red-500">
          <p class="text-xs font-medium text-gray-500">Inactive Users</p>
          <p class="mt-1 text-2xl font-bold text-red-600">{{ stats.inactive }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm border-t-4 border-t-gray-400">
          <p class="text-xs font-medium text-gray-500">Pending Approval</p>
          <p class="mt-1 text-2xl font-bold text-gray-600">{{ stats.pending }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm border-t-4 border-t-brand-primary col-span-2 sm:col-span-1">
          <p class="text-xs font-medium text-gray-500">Total Target MRC</p>
          <p class="mt-1 text-2xl font-bold text-brand-primary">{{ formatCurrency(stats.total_target_mrc) }}</p>
        </div>
      </template>
      <template v-else>
        <div v-for="n in 5" :key="`stats-skeleton-${n}`" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
          <div class="h-3 w-20 animate-pulse rounded bg-gray-200" />
          <div class="mt-2 h-7 w-12 animate-pulse rounded bg-gray-100" />
        </div>
      </template>
    </div>

    <!-- Toolbar: Single-row scrollable actions -->
    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
      <div class="max-w-full overflow-x-auto pb-1 -mb-1 scrollbar-thin">
        <div class="flex flex-nowrap items-center gap-2 min-w-max whitespace-nowrap">
          <button type="button" class="inline-flex items-center rounded bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-50 shrink-0" :disabled="loading" @click="applyFilters">Apply</button>
          <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 shrink-0" :disabled="loading" @click="resetFilters">Reset</button>
          <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 shrink-0" @click="filtersVisible = !filtersVisible; advancedFiltersHydration.hydrateNow(); hydrateSecondaryData()">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            Advanced Filters
          </button>
          <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 shrink-0" @click="columnModalVisible = true">
            Customize columns
          </button>
          <button
            v-if="canImport"
            type="button"
            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg bg-brand-primary px-3 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover shrink-0"
            @click="openImportModal"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/></svg>
            Bulk Import
          </button>
          <button
            v-if="canExport"
            type="button"
            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border border-brand-primary text-brand-primary px-3 py-2 text-sm font-medium hover:bg-brand-primary-light shrink-0"
            @click="handleExport"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21V9m0 0l-4 4m4-4l4 4M5 3h14"/></svg>
            Export CSV
          </button>
          <button
            v-if="canImport"
            type="button"
            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border border-gray-300 text-gray-700 px-3 py-2 text-sm font-medium hover:bg-gray-50 shrink-0"
            @click="handleTemplateDownload"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 3h7l5 5v13a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z"/></svg>
            Template
          </button>
          <button
            v-if="canEdit"
            type="button"
            :disabled="!selectedIds.length || bulkLoading"
            @click="bulkActivate"
            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg bg-brand-primary px-3 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Bulk Activate
          </button>
          <button
            v-if="canEdit"
            type="button"
            :disabled="!selectedIds.length || bulkLoading"
            @click="bulkDeactivate"
            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            Bulk Deactivate
          </button>
          <button
            v-if="canEdit"
            type="button"
            :disabled="!selectedIds.length || bulkTargetLoading"
            @click="openBulkTargetModal"
            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border border-brand-primary text-brand-primary px-3 py-2 text-sm font-medium hover:bg-brand-primary-light disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-3.314 0-6 1.343-6 3v2c0 1.657 2.686 3 6 3s6-1.343 6-3v-2c0-1.657-2.686-3-6-3zm0 0V5m0 14v-3"/></svg>
            Assign Target (Bulk)
          </button>
          <button
            v-if="canCreate"
            type="button"
            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg bg-brand-primary px-3 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover shrink-0"
            @click="openAddUserModal"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add User
          </button>
        </div>
      </div>
    </div>

    <!-- Advanced Filters Panel -->
    <div v-show="filtersVisible" ref="advancedFiltersHydration.targetRef" class="rounded-lg border border-gray-200 bg-white p-4">
      <template v-if="advancedFiltersHydration.isHydrated">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">User Name</label>
            <input v-model="filters.name" type="text" placeholder="Search by user name" class="w-full rounded-lg border-gray-300 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Email</label>
            <input v-model="filters.email" type="text" placeholder="Search by email" class="w-full rounded-lg border-gray-300 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Role</label>
            <select v-model="filters.role" class="w-full rounded-lg border-gray-300 text-sm">
              <option value="">All roles</option>
              <option v-for="r in filterOptions.roles" :key="r.value" :value="r.value">{{ r.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Country</label>
            <input v-model="filters.country" type="text" placeholder="Search by country" class="w-full rounded-lg border-gray-300 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
            <select v-model="filters.status" class="w-full rounded-lg border-gray-300 bg-white text-sm">
              <option value="">All status</option>
              <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Department</label>
            <select v-model="filters.department" class="w-full rounded-lg border-gray-300 text-sm">
              <option value="">All departments</option>
              <option v-for="d in filterOptions.departments" :key="d.value" :value="d.value">{{ d.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Manager</label>
            <select v-model="filters.manager_id" class="w-full rounded-lg border-gray-300 text-sm">
              <option value="">All managers</option>
              <option v-for="m in filterOptions.managers" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Team Leader</label>
            <select v-model="filters.team_leader_id" class="w-full rounded-lg border-gray-300 text-sm">
              <option value="">All team leaders</option>
              <option v-for="t in filterOptions.team_leaders" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Created From</label>
            <DateInputDdMmYyyy v-model="filters.created_from" placeholder="DD-MMM-YYYY" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Created To</label>
            <DateInputDdMmYyyy v-model="filters.created_to" placeholder="DD-MMM-YYYY" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Joining From</label>
            <DateInputDdMmYyyy v-model="filters.joining_from" placeholder="DD-MMM-YYYY" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Joining To</label>
            <DateInputDdMmYyyy v-model="filters.joining_to" placeholder="DD-MMM-YYYY" />
          </div>
          <!-- Monthly target range filters -->
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Target Min (AED)</label>
            <input v-model="filters.target_min" type="number" step="0.01" min="0" placeholder="0.00" class="w-full rounded-lg border-gray-300 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Target Max (AED)</label>
            <input v-model="filters.target_max" type="number" step="0.01" min="0" placeholder="0.00" class="w-full rounded-lg border-gray-300 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Target Month From</label>
            <input v-model="filters.target_month_from" type="month" class="w-full rounded-lg border-gray-300 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Target Month To</label>
            <input v-model="filters.target_month_to" type="month" class="w-full rounded-lg border-gray-300 text-sm" />
          </div>
        </div>
        <div class="mt-3 flex gap-2">
          <button type="button" @click="applyFilters" class="rounded-lg bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover">Apply Filters</button>
          <button type="button" @click="resetFilters" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</button>
        </div>
      </template>
      <template v-else>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div v-for="n in 6" :key="`user-advanced-skeleton-${n}`" class="space-y-2">
            <div class="h-3 w-20 animate-pulse rounded bg-gray-200" />
            <div class="h-9 w-full animate-pulse rounded bg-gray-100" />
          </div>
        </div>
      </template>
    </div>

    <!-- Data Table -->
    <div class="relative overflow-x-auto overflow-hidden rounded-lg border-2 border-black bg-white shadow-sm">
      <div v-if="loading" class="absolute inset-0 z-10 flex items-center justify-center bg-white/80" aria-live="polite" aria-busy="true">
        <div class="flex flex-col items-center gap-2">
          <svg class="h-8 w-8 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          <span class="text-sm font-medium text-gray-600">Updating...</span>
        </div>
      </div>
      <table class="min-w-full border-collapse">
        <thead>
          <tr class="bg-brand-primary border-b-2 border-green-700">
            <th class="w-10 px-3 py-3">
              <input type="checkbox" @change="toggleSelectAll()" :checked="selectedIds.length === users.length && users.length > 0" class="rounded border-gray-300 text-brand-primary focus:ring-brand-primary" />
            </th>
            <th
              v-for="col in visibleColumns" :key="col"
              class="whitespace-nowrap px-4 py-3 text-left text-sm font-semibold text-white cursor-pointer select-none"
              @click="toggleSort(col)"
            >
              <span class="inline-flex items-center gap-1">
                {{ columnLabels[col] ?? col }}
                <span v-if="sort === col" class="text-white">{{ order === 'asc' ? '↑' : '↓' }}</span>
              </span>
            </th>
            <th class="whitespace-nowrap px-4 py-3 text-center text-sm font-semibold text-white">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white">
          <tr v-if="!loading && users.length === 0" class="border-b border-black">
            <td :colspan="visibleColumns.length + 2" class="px-4 py-12 text-center text-sm text-gray-500">No users found.</td>
          </tr>
          <tr v-for="(user, rowIndex) in users" :key="user.id" class="border-b border-black bg-white hover:bg-gray-50/50">
            <td class="px-4 py-3">
              <input type="checkbox" :checked="selectedIds.includes(user.id)" @change="toggleSelect(user.id)" class="rounded border-gray-300" />
            </td>
            <td
              v-for="col in visibleColumns" :key="col" class="px-4 py-2 align-top"
              :class="{ 'cursor-pointer hover:bg-gray-50': editableFields.includes(col) && canEditRow(user) }"
              :title="editableFields.includes(col) && canEditRow(user) ? 'Double-click to edit' : undefined"
              @dblclick="editableFields.includes(col) && canEditRow(user) && startEdit(user, col)"
            >
              <!-- Inline editing -->
              <template v-if="isEditing(user.id, col)">
                <div class="flex flex-wrap items-center gap-1">
                  <select v-if="isDropdownField(col)" v-model="editValue" class="rounded border border-gray-300 text-sm py-1 px-2">
                    <option v-for="opt in getStatusOptions()" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                  <input v-else v-model="editValue" :type="col === 'joining_date' || col === 'terminate_date' ? 'date' : col === 'monthly_target' ? 'number' : 'text'" :step="col === 'monthly_target' ? '0.01' : undefined" class="rounded border border-gray-300 text-sm py-1 px-2 min-w-[100px]" />
                  <button type="button" class="rounded bg-brand-primary px-2 py-1 text-xs text-white hover:bg-brand-primary-hover disabled:opacity-50" :disabled="savingCell" @click="saveEdit">Save</button>
                  <button type="button" class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" :disabled="savingCell" @click="cancelEdit">Cancel</button>
                </div>
              </template>
              <!-- Display -->
              <template v-else>
                <span v-if="col === 'id'" class="text-sm text-gray-900">{{ rowNumber(rowIndex) }}</span>
                <span v-else-if="col === 'name'" class="font-medium text-gray-900">{{ displayCellValue(user, col) }}</span>
                <span v-else-if="col === 'roles'" class="flex flex-wrap gap-1">
                  <span v-for="r in (user.roles || [])" :key="r" class="inline-flex rounded-full bg-brand-primary-light px-2 py-0.5 text-xs font-medium text-brand-primary-hover border border-brand-primary-muted">{{ r }}</span>
                  <span v-if="!(user.roles || []).length" class="text-gray-400">-</span>
                </span>
                <span v-else-if="col === 'status'">
                  <span :class="['inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold', statusBadgeClass(user.status)]">{{ statusLabel(user.status) }}</span>
                </span>
                <span v-else-if="col === 'monthly_target'" class="text-sm text-gray-700 font-medium whitespace-nowrap">
                  {{ formatCurrency(user.monthly_target) }}
                  <button v-if="canEditRow(user)" type="button" class="ml-1 inline-flex text-brand-primary hover:text-brand-primary-hover" title="Edit monthly target" @click.stop="openTargetModal(user)">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                  </button>
                </span>
                <span v-else class="text-sm text-gray-700" :class="{ 'whitespace-nowrap': col === 'last_login_at' }">{{ displayCellValue(user, col) }}</span>
              </template>
            </td>
            <!-- Actions column -->
            <td class="whitespace-nowrap px-4 py-3 text-center">
              <div class="inline-flex items-center justify-center gap-1">
                <button type="button" class="rounded-full p-1.5 text-brand-primary hover:bg-brand-primary-light" title="View details" @click="openUserDetail(user)">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
                <button v-if="canEditRow(user)" type="button" class="rounded-full p-1.5 text-brand-primary hover:bg-brand-primary-light" title="Edit User" @click="openEditUserModal(user)">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
                <button v-if="canEditRow(user)" type="button" class="rounded-full p-1.5 text-blue-600 hover:bg-blue-50" title="Set Monthly Target" @click="openTargetModal(user)">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </button>
                <button type="button" class="rounded-full p-1.5 text-amber-600 hover:bg-amber-50" title="View History" @click="openHistory(user.id)">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
                <button v-if="canDelete && canEditRow(user) && !(user.roles || []).includes('superadmin')" type="button" class="rounded-full p-1.5 text-red-600 hover:bg-red-50" title="Delete (OTP)" @click="openDeleteModal(user)">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <!-- Pagination -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-black bg-white px-4 py-3">
        <p class="text-sm text-gray-600">
          Showing {{ pagination.total ? ((pagination.current_page - 1) * pagination.per_page) + 1 : 0 }}
          to {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}
          of {{ pagination.total }} entries
        </p>
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2 text-sm text-gray-600">
            <span class="whitespace-nowrap font-medium">Number of rows</span>
            <select :value="pagination.per_page" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm min-w-[80px] text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" @change="onPerPageChange">
              <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
            </select>
          </div>
          <div class="flex items-center gap-1.5">
            <button type="button" :disabled="pagination.current_page <= 1" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" @click="goToPage(pagination.current_page - 1)">Previous</button>
            <span class="rounded-md border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm text-gray-700">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
            <button type="button" :disabled="pagination.current_page >= pagination.last_page" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" @click="goToPage(pagination.current_page + 1)">Next</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Column Customizer Modal -->
    <ColumnCustomizerModal
      v-model:visible="columnModalVisible"
      :all-columns="allColumns"
      :visible-columns="visibleColumns"
      :default-columns="['id', 'name', 'email', 'phone', 'country', 'roles', 'status', 'last_login_at', 'created_at']"
      @save="onSaveColumns"
    />

    <!-- ═══════════════════ MODALS ═══════════════════ -->

    <!-- Add User Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="addUserModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/30 backdrop-blur-sm p-4 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="add-user-modal-title" @click.self="closeAddUserModal">
          <div class="w-full max-w-2xl my-8 max-h-[90vh] flex flex-col rounded-xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center gap-3 border-b border-gray-200 px-6 py-4">
              <button type="button" class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700" aria-label="Close" @click="closeAddUserModal">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
              </button>
              <div>
                <h2 id="add-user-modal-title" class="text-xl font-bold text-gray-900">Add New User</h2>
                <p class="text-sm text-gray-500 mt-0.5">Create a new user account and assign roles</p>
              </div>
            </div>
            <form autocomplete="off" @submit.prevent="submitAddUser" class="flex-1 min-h-0 flex flex-col overflow-hidden">
              <input type="text" name="fake_username" autocomplete="username" class="hidden" tabindex="-1" aria-hidden="true" />
              <input type="password" name="fake_password" autocomplete="current-password" class="hidden" tabindex="-1" aria-hidden="true" />
              <div class="overflow-y-auto flex-1 min-h-0 p-6 space-y-6 relative">
                <div v-if="addUserLoading" class="absolute inset-0 z-20 flex items-center justify-center bg-white/80 rounded-b-xl">
                  <div class="flex flex-col items-center gap-3">
                    <svg class="h-8 w-8 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                    <span class="text-sm font-medium text-gray-600">Creating user...</span>
                  </div>
                </div>
                <div v-if="addUserSuccess" class="rounded-lg bg-brand-primary-light border border-brand-primary-muted px-4 py-3 text-sm text-brand-primary-hover flex items-center gap-2">
                  <svg class="h-5 w-5 text-brand-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  User created successfully! Redirecting...
                </div>
                <p v-if="addUserError && !addUserSuccess" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ addUserError }}</p>
                <section>
                  <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 mb-4">
                    <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Basic Information
                  </h3>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                      <input v-model="addUserForm.name" type="text" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="addUserFieldErrors.name ? 'border-red-400 bg-red-50' : 'border-gray-300'" placeholder="Enter full name" />
                      <p v-if="addUserFieldErrors.name" class="mt-1 text-xs text-red-600">{{ addUserFieldErrors.name }}</p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                      <input v-model="addUserForm.email" type="email" name="add_user_email" autocomplete="off" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="addUserFieldErrors.email ? 'border-red-400 bg-red-50' : 'border-gray-300'" placeholder="Enter email address" />
                      <p v-if="addUserFieldErrors.email" class="mt-1 text-xs text-red-600">{{ addUserFieldErrors.email }}</p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number <span class="text-red-500">*</span></label>
                      <input v-model="addUserForm.phone" type="text" maxlength="12" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="addUserFieldErrors.phone ? 'border-red-400 bg-red-50' : 'border-gray-300'" placeholder="971XXXXXXXXX" @input="onAddUserPhoneInput" />
                      <p v-if="addUserFieldErrors.phone" class="mt-1 text-xs text-red-600">{{ addUserFieldErrors.phone }}</p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Country <span class="text-red-500">*</span></label>
                      <select v-model="addUserForm.country" class="w-full rounded-lg border bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="addUserFieldErrors.country ? 'border-red-400 bg-red-50' : 'border-gray-300'">
                        <option value="">Select country</option>
                        <option v-for="c in addUserCountries" :key="c.id" :value="c.code || c.name">{{ c.name }}</option>
                      </select>
                      <p v-if="addUserFieldErrors.country" class="mt-1 text-xs text-red-600">{{ addUserFieldErrors.country }}</p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-500">*</span></label>
                      <select v-model="addUserForm.department" class="w-full rounded-lg border bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="addUserFieldErrors.department ? 'border-red-400 bg-red-50' : 'border-gray-300'">
                        <option value="">Select department</option>
                        <option v-for="d in ADD_USER_DEPARTMENTS" :key="d.value" :value="d.value">{{ d.label }}</option>
                      </select>
                      <p v-if="addUserFieldErrors.department" class="mt-1 text-xs text-red-600">{{ addUserFieldErrors.department }}</p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Account Status</label>
                      <select v-model="addUserForm.status" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
                        <option value="approved">Active</option>
                        <option value="rejected">Inactive</option>
                        <option value="pending">Pending Approval</option>
                      </select>
                    </div>
                  </div>
                </section>
                <section>
                  <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 mb-4">
                    <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Role Assignment
                  </h3>
                  <div class="relative" ref="addUserRolesDropdownRef">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Roles <span class="text-red-500">*</span></label>
                    <button type="button" class="w-full rounded-lg border bg-white px-3 py-2 text-left text-sm flex items-center justify-between focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="addUserFieldErrors.roles ? 'border-red-400 bg-red-50' : 'border-gray-300'" @click="addUserRolesDropdownOpen = !addUserRolesDropdownOpen">
                      <span :class="addUserForm.roles.length ? 'text-gray-900' : 'text-gray-500'" class="truncate">
                        {{ addUserForm.roles.length ? assignableRolesForAdd.filter(r => addUserForm.roles.includes(r.id)).map(r => formatRoleNameForAdd(r.name)).join(', ') : 'Select roles to assign' }}
                      </span>
                      <svg class="h-4 w-4 text-gray-400 shrink-0 transition-transform" :class="addUserRolesDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <p v-if="addUserFieldErrors.roles" class="mt-1 text-xs text-red-600">{{ addUserFieldErrors.roles }}</p>
                    <div v-show="addUserRolesDropdownOpen" class="absolute z-10 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg">
                      <div class="max-h-48 overflow-y-auto">
                        <button v-for="r in assignableRolesForAdd" :key="r.id" type="button" class="w-full px-3 py-2.5 text-left text-sm hover:bg-gray-50 flex items-center gap-2" :class="hasAddUserRole(r.id) ? 'bg-brand-primary-light text-brand-primary-hover' : 'text-gray-700'" @click="toggleAddUserRole(r)">
                          <span class="w-4 h-4 flex items-center justify-center shrink-0 rounded border" :class="hasAddUserRole(r.id) ? 'bg-brand-primary border-brand-primary text-white' : 'border-gray-300'">
                            <svg v-if="hasAddUserRole(r.id)" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                          </span>
                          {{ formatRoleNameForAdd(r.name) }}
                        </button>
                        <p v-if="!assignableRolesForAdd.length" class="px-3 py-2 text-sm text-gray-500">No roles available.</p>
                      </div>
                      <div class="border-t border-gray-200 px-3 py-2 flex justify-end">
                        <button type="button" class="rounded-lg bg-brand-primary px-4 py-1.5 text-xs font-medium text-white hover:bg-brand-primary-hover" @click="addUserRolesDropdownOpen = false">Done</button>
                      </div>
                    </div>
                  </div>
                </section>
                <section>
                  <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 mb-4">
                    <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Set Password
                  </h3>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                      <input v-model="addUserForm.password" type="password" name="add_user_password" autocomplete="new-password" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="addUserFieldErrors.password ? 'border-red-400 bg-red-50' : 'border-gray-300'" placeholder="Enter password" />
                      <p v-if="addUserFieldErrors.password" class="mt-1 text-xs text-red-600">{{ addUserFieldErrors.password }}</p>
                      <p v-else class="mt-1 text-xs text-gray-500">{{ passwordPolicyHint }}</p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
                      <input v-model="addUserForm.password_confirmation" type="password" name="add_user_password_confirmation" autocomplete="new-password" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="addUserFieldErrors.password_confirmation ? 'border-red-400 bg-red-50' : 'border-gray-300'" placeholder="Confirm password" />
                      <p v-if="addUserFieldErrors.password_confirmation" class="mt-1 text-xs text-red-600">{{ addUserFieldErrors.password_confirmation }}</p>
                    </div>
                  </div>
                </section>
              </div>
              <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" :disabled="addUserLoading || addUserSuccess" @click="closeAddUserModal">Cancel</button>
                <button type="submit" :disabled="addUserLoading || addUserSuccess" class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-70">
                  <svg v-if="addUserLoading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                  {{ addUserLoading ? 'Creating...' : addUserSuccess ? 'Created!' : 'Create User' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Edit User Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="editUserModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/30 backdrop-blur-sm p-4 overflow-y-auto" role="dialog" aria-modal="true" @click.self="closeEditUserModal">
          <div class="w-full max-w-2xl my-8 max-h-[90vh] flex flex-col rounded-xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
              <h2 class="text-xl font-bold text-gray-900">Edit Employee</h2>
              <button type="button" class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700" :disabled="editUserSaving" @click="closeEditUserModal">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <form autocomplete="off" class="flex-1 min-h-0 flex flex-col overflow-hidden" @submit.prevent="submitEditUser">
              <div class="overflow-y-auto flex-1 min-h-0 p-6 space-y-5">
                <div v-if="editUserLoading" class="flex justify-center py-10">
                  <svg class="h-8 w-8 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </div>
                <template v-else>
                  <p v-if="editUserError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ editUserError }}</p>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                      <input v-model="editUserForm.name" type="text" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="editUserFieldErrors.name ? 'border-red-400 bg-red-50' : 'border-gray-300'" />
                      <p v-if="editUserFieldErrors.name" class="mt-1 text-xs text-red-600">{{ editUserFieldErrors.name }}</p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                      <input v-model="editUserForm.email" type="email" autocomplete="off" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="editUserFieldErrors.email ? 'border-red-400 bg-red-50' : 'border-gray-300'" />
                      <p v-if="editUserFieldErrors.email" class="mt-1 text-xs text-red-600">{{ editUserFieldErrors.email }}</p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                      <input v-model="editUserForm.phone" type="text" maxlength="12" placeholder="971XXXXXXXXX" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="editUserFieldErrors.phone ? 'border-red-400 bg-red-50' : 'border-gray-300'" @input="onEditUserPhoneInput" />
                      <p v-if="editUserFieldErrors.phone" class="mt-1 text-xs text-red-600">{{ editUserFieldErrors.phone }}</p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                      <select v-model="editUserForm.country" class="w-full rounded-lg border bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="editUserFieldErrors.country ? 'border-red-400 bg-red-50' : 'border-gray-300'">
                        <option value="">Select country</option>
                        <option v-for="c in editUserCountries" :key="c.id" :value="c.code || c.name">{{ c.name }}</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                      <select v-model="editUserForm.department" class="w-full rounded-lg border bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="editUserFieldErrors.department ? 'border-red-400 bg-red-50' : 'border-gray-300'">
                        <option value="">Select department</option>
                        <option v-for="d in ADD_USER_DEPARTMENTS" :key="d.value" :value="d.value">{{ d.label }}</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Account Status</label>
                      <select v-model="editUserForm.status" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
                        <option value="approved">Active</option>
                        <option value="rejected">Inactive</option>
                        <option value="pending">Pending Approval</option>
                      </select>
                    </div>
                  </div>
                  <div ref="editUserRolesDropdownRef" class="relative">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Roles</label>
                    <button type="button" class="w-full rounded-lg border bg-white px-3 py-2 text-left text-sm flex items-center justify-between focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="editUserFieldErrors.roles ? 'border-red-400 bg-red-50' : 'border-gray-300'" @click="editUserRolesDropdownOpen = !editUserRolesDropdownOpen">
                      <span :class="editUserForm.roles.length ? 'text-gray-900' : 'text-gray-500'" class="truncate">{{ editUserForm.roles.length ? assignableRolesForAdd.filter(r => editUserForm.roles.includes(r.id)).map(r => formatRoleNameForAdd(r.name)).join(', ') : 'Select roles to assign' }}</span>
                      <svg class="h-4 w-4 text-gray-400 shrink-0 transition-transform" :class="editUserRolesDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div v-show="editUserRolesDropdownOpen" class="absolute z-10 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg">
                      <div class="max-h-48 overflow-y-auto">
                        <button v-for="r in assignableRolesForAdd" :key="r.id" type="button" class="w-full px-3 py-2.5 text-left text-sm hover:bg-gray-50 flex items-center gap-2" :class="hasEditUserRole(r.id) ? 'bg-brand-primary-light text-brand-primary-hover' : 'text-gray-700'" @click="toggleEditUserRole(r)">
                          <span class="w-4 h-4 flex items-center justify-center shrink-0 rounded border" :class="hasEditUserRole(r.id) ? 'bg-brand-primary border-brand-primary text-white' : 'border-gray-300'">
                            <svg v-if="hasEditUserRole(r.id)" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                          </span>
                          {{ formatRoleNameForAdd(r.name) }}
                        </button>
                      </div>
                      <div class="border-t border-gray-200 px-3 py-2 flex justify-end">
                        <button type="button" class="rounded-lg bg-brand-primary px-4 py-1.5 text-xs font-medium text-white hover:bg-brand-primary-hover" @click="editUserRolesDropdownOpen = false">Done</button>
                      </div>
                    </div>
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                      <input v-model="editUserForm.password" type="password" autocomplete="new-password" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="editUserFieldErrors.password ? 'border-red-400 bg-red-50' : 'border-gray-300'" placeholder="Leave blank to keep current" />
                      <p v-if="editUserFieldErrors.password" class="mt-1 text-xs text-red-600">{{ editUserFieldErrors.password }}</p>
                      <p v-else class="mt-1 text-xs text-gray-500">{{ passwordPolicyHint }}</p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                      <input v-model="editUserForm.password_confirmation" type="password" autocomplete="new-password" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="editUserFieldErrors.password_confirmation ? 'border-red-400 bg-red-50' : 'border-gray-300'" />
                      <p v-if="editUserFieldErrors.password_confirmation" class="mt-1 text-xs text-red-600">{{ editUserFieldErrors.password_confirmation }}</p>
                    </div>
                  </div>
                </template>
              </div>
              <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" :disabled="editUserSaving" @click="closeEditUserModal">Cancel</button>
                <button type="submit" :disabled="editUserSaving || editUserLoading" class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-70">
                  <svg v-if="editUserSaving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                  {{ editUserSaving ? 'Saving...' : 'Update User' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- OTP Delete Confirmation Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="userToDelete" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/30 backdrop-blur-sm p-4" role="dialog" aria-modal="true" @click.self="closeDeleteModal">
          <div class="w-full max-w-md rounded-xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
              <h2 class="text-lg font-bold text-gray-900">Delete User (OTP Verification)</h2>
              <button type="button" class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" :disabled="deleteLoading" @click="closeDeleteModal">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <div class="px-6 py-5 space-y-4">
              <p class="text-gray-700">Are you sure you want to permanently delete <strong>{{ userToDelete?.name }}</strong>? This action cannot be undone.</p>
              <p v-if="deleteError" class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">{{ deleteError }}</p>
              <!-- Step 1: Request OTP -->
              <div v-if="!deleteOtpSent">
                <button type="button" class="w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-70" :disabled="deleteLoading" @click="requestDeleteOtp">
                  {{ deleteLoading ? 'Generating OTP...' : 'Send OTP to Confirm' }}
                </button>
              </div>
              <!-- Step 2: Enter OTP -->
              <div v-else class="space-y-3">
                <p class="text-sm text-gray-600">An OTP has been generated. Enter it below to confirm deletion.</p>
                <p v-if="deleteOtpValue" class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
                  <strong>Dev OTP:</strong> {{ deleteOtpValue }}
                </p>
                <input v-model="deleteOtp" type="text" maxlength="6" placeholder="Enter 6-digit OTP" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-center tracking-widest font-mono focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                <button type="button" class="w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-70" :disabled="deleteLoading || deleteOtp.length < 6" @click="confirmOtpDelete">
                  {{ deleteLoading ? 'Verifying...' : 'Confirm Delete' }}
                </button>
              </div>
            </div>
            <div class="flex items-center gap-3 px-6 pb-5">
              <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" :disabled="deleteLoading" @click="closeDeleteModal">Cancel</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Reset Password Confirmation Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="userToResetPassword" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/30 backdrop-blur-sm p-4" role="dialog" aria-modal="true" @click.self="closeResetPasswordModal">
          <div class="w-full max-w-md rounded-xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
              <h2 class="text-lg font-bold text-gray-900">Confirm Password Reset</h2>
              <button type="button" class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" :disabled="resetPasswordLoading" @click="closeResetPasswordModal">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <p class="px-6 py-5 text-gray-700">Are you sure you want to reset the password for <strong>{{ userToResetPassword?.name }}</strong>? They will receive an email with instructions.</p>
            <div class="flex items-center gap-3 px-6 pb-6">
              <button type="button" class="rounded-lg bg-brand-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-70" :disabled="resetPasswordLoading" @click="confirmResetPassword">{{ resetPasswordLoading ? 'Sending…' : 'Confirm' }}</button>
              <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-70" :disabled="resetPasswordLoading" @click="closeResetPasswordModal">Cancel</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Import CSV Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="importModalOpen" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/30 backdrop-blur-sm p-4" role="dialog" aria-modal="true" @click.self="closeImportModal">
          <div class="w-full max-w-lg rounded-xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
              <h2 class="text-lg font-bold text-gray-900">Bulk Import Users</h2>
              <button type="button" class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" :disabled="importLoading" @click="closeImportModal">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <div class="px-6 py-5 space-y-4">
              <p class="text-sm text-gray-600">Upload a CSV file to import users in bulk. <button type="button" class="text-brand-primary hover:underline" @click="handleTemplateDownload">Download template</button></p>
              <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-brand-primary transition-colors">
                <input type="file" accept=".csv,.txt" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-primary-light file:text-brand-primary-hover hover:file:bg-brand-primary/20" @change="onImportFileChange" />
                <p v-if="importFile" class="mt-2 text-sm text-brand-primary font-medium">{{ importFile.name }}</p>
              </div>
              <!-- Import result -->
              <div v-if="importResult" class="space-y-2">
                <p class="text-sm font-medium" :class="importResult.imported > 0 ? 'text-brand-primary' : 'text-red-600'">{{ importResult.message }}</p>
                <p v-if="importResult.imported > 0" class="text-sm text-gray-600">{{ importResult.imported }} user(s) imported successfully.</p>
                <div v-if="importResult.errors?.length" class="max-h-40 overflow-y-auto rounded-lg border border-red-200 bg-red-50 p-3 space-y-1">
                  <p class="text-xs font-semibold text-red-700">Errors ({{ importResult.errors.length }}):</p>
                  <p v-for="(err, i) in importResult.errors" :key="i" class="text-xs text-red-600">Row {{ err.row }}: {{ err.error }}</p>
                </div>
              </div>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 pb-5">
              <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" :disabled="importLoading" @click="closeImportModal">Close</button>
              <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-70" :disabled="!importFile || importLoading" @click="submitImport">
                <svg v-if="importLoading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                {{ importLoading ? 'Importing...' : 'Upload & Import' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Monthly Target Edit Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="targetModalOpen" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/30 backdrop-blur-sm p-4" role="dialog" aria-modal="true" @click.self="closeTargetModal">
          <div class="w-full max-w-lg max-h-[85vh] flex flex-col rounded-xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
              <div>
                <h2 class="text-lg font-bold text-gray-900">Monthly Target</h2>
                <p class="text-sm text-gray-500">{{ targetUser?.name }}</p>
              </div>
              <button type="button" class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" :disabled="targetLoading" @click="closeTargetModal">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <div class="overflow-y-auto flex-1 min-h-0 px-6 py-5 space-y-5">
              <!-- Set target form -->
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Target Month</label>
                  <input v-model="targetMonth" type="month" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Target Amount (AED)</label>
                  <input v-model="targetValue" type="number" step="0.01" min="0" placeholder="0.00" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" />
                </div>
              </div>
              <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-70" :disabled="targetLoading || !targetValue" @click="submitTarget">
                <svg v-if="targetLoading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                {{ targetLoading ? 'Saving...' : 'Update Target' }}
              </button>
              <!-- Target history -->
              <div>
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Target History</h3>
                <div v-if="targetHistoryLoading" class="flex justify-center py-4">
                  <svg class="h-6 w-6 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </div>
                <div v-else-if="!targetHistory.length" class="text-center text-sm text-gray-500 py-4">No target history recorded.</div>
                <div v-else class="space-y-2 max-h-48 overflow-y-auto">
                  <div v-for="h in targetHistory" :key="h.id" class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                    <div>
                      <span class="font-medium text-gray-900">{{ h.month }}</span>
                      <span class="ml-2 text-brand-primary font-semibold">AED {{ formatCurrency(h.target_amount) }}</span>
                    </div>
                    <div class="text-xs text-gray-500">
                      by {{ h.set_by_name }} · {{ formatDateTime(h.created_at) }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Bulk Monthly Target Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="bulkTargetModalOpen" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/30 backdrop-blur-sm p-4" role="dialog" aria-modal="true" @click.self="closeBulkTargetModal">
          <div class="w-full max-w-md rounded-xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
              <h2 class="text-lg font-bold text-gray-900">Assign Monthly Target (Bulk)</h2>
              <button type="button" class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" :disabled="bulkTargetLoading" @click="closeBulkTargetModal">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <div class="px-6 py-5 space-y-4">
              <p class="text-sm text-gray-600">Assign the same monthly target to <strong>{{ selectedIds.length }}</strong> selected user(s).</p>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Target Month</label>
                <input v-model="bulkTargetMonth" type="month" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Target Amount (AED)</label>
                <input v-model="bulkTargetValue" type="number" min="0" step="0.01" placeholder="0.00" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" />
              </div>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 pb-5">
              <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" :disabled="bulkTargetLoading" @click="closeBulkTargetModal">Cancel</button>
              <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-70" :disabled="bulkTargetLoading || !bulkTargetMonth || bulkTargetValue === ''" @click="submitBulkTarget">
                <svg v-if="bulkTargetLoading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                {{ bulkTargetLoading ? 'Assigning...' : 'Assign Target' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- User Details Popup -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="detailUser !== null || detailLoading" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/30 backdrop-blur-sm p-4" role="dialog" aria-modal="true" @click.self="closeUserDetail">
          <div class="w-full max-w-lg max-h-[90vh] flex flex-col rounded-xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
              <h2 class="text-lg font-bold text-gray-900">User Details</h2>
              <button type="button" class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" :disabled="detailLoading" @click="closeUserDetail">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <div v-if="detailLoading" class="flex justify-center py-12">
              <svg class="h-8 w-8 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </div>
            <p v-else-if="detailError" class="px-6 py-6 text-sm text-red-600">{{ detailError }}</p>
            <template v-else-if="detailUser">
              <div class="overflow-y-auto flex-1 min-h-0">
                <div class="px-6 py-5">
                  <div class="flex items-start gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-brand-primary text-lg font-semibold text-white">{{ getInitials(detailUser.name) }}</div>
                    <div class="min-w-0 flex-1">
                      <p class="font-semibold text-gray-900">{{ detailUser.name }}</p>
                      <p class="text-sm text-gray-500">User ID: {{ userIdDisplay(detailUser.id) }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium" :class="statusBadgeClass(detailUser.status)">{{ statusLabel(detailUser.status) }}</span>
                  </div>
                </div>
                <div class="border-t border-gray-100 px-6 py-4">
                  <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                    <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Basic Information
                  </h3>
                  <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <div><dt class="text-gray-500">Full Name</dt><dd class="font-medium text-gray-900">{{ detailUser.name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Email Address</dt><dd class="text-gray-700">{{ detailUser.email ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Phone Number</dt><dd class="text-gray-700">{{ detailUser.phone ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Country</dt><dd class="text-gray-700">{{ detailUser.country ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Department</dt><dd class="text-gray-700">{{ detailUser.department ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Monthly Target</dt><dd class="font-medium text-brand-primary">AED {{ formatCurrency(detailUser.monthly_target) }}</dd></div>
                    <div class="col-span-2">
                      <dt class="text-gray-500">Assigned Roles</dt>
                      <dd class="mt-1 flex flex-wrap gap-2">
                        <span v-for="role in (detailUser.roles || []).map(r => typeof r === 'string' ? r : r.name)" :key="role" class="rounded-full bg-brand-primary-light px-3 py-1 text-xs font-medium text-brand-primary-hover">{{ role }}</span>
                        <span v-if="!(detailUser.roles || []).length" class="text-sm text-gray-500">No roles assigned</span>
                      </dd>
                    </div>
                    <div><dt class="text-gray-500">Last Login</dt><dd class="text-gray-700">{{ formatDetailDateTime(detailUser.last_login_at) }}</dd></div>
                    <div><dt class="text-gray-500">Created Date</dt><dd class="text-gray-700">{{ formatDetailDateTime(detailUser.created_at) }}</dd></div>
                  </dl>
                </div>
              </div>
              <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 px-6 py-4">
                <button v-if="canEditRow(detailUser)" type="button" class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-primary-hover" @click="fromDetailEditUser">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                  Edit Employee
                </button>
                <button v-if="canEditRow(detailUser)" type="button" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="fromDetailResetPassword">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                  Reset Password
                </button>
                <button type="button" class="ml-auto rounded-lg border border-gray-300 bg-gray-50 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100" @click="closeUserDetail">Close</button>
              </div>
            </template>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- History Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="historyUserId" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/50 p-4" role="dialog" aria-modal="true" @click.self="closeHistory">
          <div class="w-full max-w-2xl max-h-[80vh] flex flex-col rounded-xl bg-white shadow-xl overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
              <h2 class="text-lg font-semibold text-gray-900">Change History</h2>
              <button type="button" class="rounded p-1.5 text-gray-400 hover:bg-gray-100" @click="closeHistory">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <div class="overflow-y-auto p-6">
              <div v-if="historyLoading" class="flex justify-center py-8">
                <svg class="h-8 w-8 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
              </div>
              <div v-else-if="!historyLogs.length" class="py-8 text-center text-sm text-gray-500">No history recorded.</div>
              <div v-else class="space-y-3">
                <div v-for="log in historyLogs" :key="log.id" class="rounded border border-gray-200 bg-gray-50 p-3 text-sm">
                  <div class="flex flex-wrap items-center gap-1.5">
                    <span class="font-medium text-gray-700">{{ log.field_label || log.field_name }}:</span>
                    <span class="text-red-500 line-through break-all"><TruncatedText :text="log.old_value ?? ''" empty-label="(empty)" /></span>
                    <span class="text-gray-400">&rarr;</span>
                    <span class="text-brand-primary break-all"><TruncatedText :text="log.new_value ?? ''" empty-label="(empty)" /></span>
                  </div>
                  <p class="mt-1.5 text-xs text-gray-500">{{ log.changed_at ? formatDateTime(log.changed_at) : '—' }} by {{ log.changed_by_name || log.changed_by || '—' }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
