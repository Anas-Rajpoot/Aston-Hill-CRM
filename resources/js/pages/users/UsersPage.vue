<script setup>
/**
 * Users listing: Customize columns + Advance filters; two quick filters (User, Status);
 * sortable datatable; editable cells (input/dropdown) with Save/Cancel; permissions (super admin only by self, else users.edit); history/audit.
 */
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import usersApi from '@/services/usersApi'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import Toast from '@/components/Toast.vue'
import { toDdMmYyyy, toDdMonYyyy } from '@/lib/dateFormat'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

const TABLE_MODULE = 'users'
const perPageOptions = ref([10, 20, 25, 50, 100])
const users = ref([])
const roles = ref([])
const stats = ref({ total: 0, active: 0, inactive: 0, pending: 0 })
const pagination = ref({ current_page: 1, last_page: 1, per_page: auth.defaultTablePageSize || 25, total: 0 })
const filterOptions = ref({ statuses: [], roles: [] })
const allColumns = ref([])
const visibleColumns = ref(['id', 'name', 'email', 'phone', 'country', 'roles', 'status', 'last_login_at', 'created_at'])
const sort = ref('name')
const order = ref('asc')
const filters = ref({
  name: '',
  email: '',
  role: '',
  status: '',
  country: '',
  created_from: '',
  created_to: '',
})
const filtersVisible = ref(false)
const columnModalVisible = ref(false)
const loading = ref(true)
const selectedIds = ref([])
const bulkLoading = ref(false)
const actionMenuOpen = ref(null)

// Inline edit state: { userId, field } or null
const editingCell = ref(null)
const editValue = ref('')
const savingCell = ref(false)

// History modal
const historyUserId = ref(null)
const historyLogs = ref([])
const historyLoading = ref(false)

// Deactivate confirmation modal
const userToDeactivate = ref(null)
const deactivating = ref(false)

// Reset password confirmation modal
const userToResetPassword = ref(null)
const resetPasswordLoading = ref(false)

// User detail popup
const detailUser = ref(null)
const detailLoading = ref(false)
const detailError = ref(null)

// Add User modal (popup)
const addUserModalOpen = ref(false)
const addUserForm = ref({
  name: '',
  email: '',
  phone: '',
  country: '',
  department: '',
  status: 'approved',
  roles: [],
  password: '',
  password_confirmation: '',
})
const addUserRolesDropdownOpen = ref(false)
const addUserLoading = ref(false)
const addUserError = ref('')
const addUserFieldErrors = ref({})
const addUserSuccess = ref(false)
const addUserCountries = ref([])
const addUserRolesDropdownRef = ref(null)
const ADD_USER_DEPARTMENTS = [
  { value: 'sales', label: 'Sales' },
  { value: 'backoffice', label: 'Back Office' },
  { value: 'field', label: 'Field' },
  { value: 'csr', label: 'CSR' },
  { value: 'admin', label: 'Admin' },
  { value: 'it', label: 'IT' },
]

const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) ? r.includes('superadmin') : false
})

const canEditUsers = computed(() => {
  const perms = auth.user?.permissions ?? []
  return isSuperAdmin.value || (Array.isArray(perms) && perms.includes('users.edit'))
})

/** Who can add new users: same as edit, or explicit users.create if present. */
const canCreateUsers = computed(() => {
  const perms = auth.user?.permissions ?? []
  return isSuperAdmin.value || (Array.isArray(perms) && (perms.includes('users.edit') || perms.includes('users.create')))
})

function canEditRow(user) {
  if (!user) return false
  if (!canEditUsers.value) return false
  const roleNames = (user.roles || []).map((r) => (typeof r === 'string' ? r : r?.name))
  if (roleNames.includes('superadmin')) return auth.user?.id === user.id
  return true
}

function isDropdownField(field) {
  return field === 'status'
}

function getStatusOptions() {
  return filterOptions.value.statuses || [
    { value: 'approved', label: 'Active' },
    { value: 'rejected', label: 'Inactive' },
    { value: 'pending', label: 'Pending Approval' },
  ]
}

const statusLabel = (status) => {
  const s = status ?? 'pending'
  if (s === 'approved') return 'Active'
  if (s === 'rejected') return 'Inactive'
  return 'Pending Approval'
}
const statusBadgeClass = (status) => {
  const s = status ?? 'pending'
  if (s === 'approved') return 'bg-green-50 text-green-700 border-green-200'
  if (s === 'rejected') return 'bg-red-50 text-red-700 border-red-200'
  return 'bg-gray-100 text-gray-700 border-gray-200'
}
const formatDate = (d) => {
  if (!d) return '-'
  const str = typeof d === 'string' ? d.trim().slice(0, 10) : ''
  return str ? (toDdMmYyyy(str) || '-') : '-'
}
const formatDateTime = (d) => {
  if (!d) return '-'
  const date = new Date(d)
  if (Number.isNaN(date.getTime())) return '-'
  const dateStr = date.toISOString().slice(0, 10)
  const timeStr = date.toTimeString().slice(0, 5)
  return `${toDdMmYyyy(dateStr) || ''} ${timeStr}`.trim() || '-'
}
/** Format for detail modal: "15 Jan 2024, 14:30" */
const formatDetailDateTime = (iso) => {
  if (!iso || typeof iso !== 'string') return '—'
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return '—'
  const ymd = iso.trim().slice(0, 10)
  const datePart = toDdMonYyyy(ymd)
  const timePart = date.toTimeString().slice(0, 5)
  return datePart ? `${datePart}, ${timePart}` : '—'
}
const userIdDisplay = (id) => (id ? `USR${String(id).padStart(3, '0')}` : '—')
const getInitials = (name) => {
  if (!name) return '?'
  return name.split(/\s+/).map((n) => n[0]).slice(0, 2).join('').toUpperCase()
}

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
  if (filters.value.created_from) p.created_from = filters.value.created_from
  if (filters.value.created_to) p.created_to = filters.value.created_to
  return p
}

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
    filterOptions.value = { statuses: data.statuses ?? [], roles: data.roles ?? [] }
  } catch {}
}

async function loadColumns() {
  try {
    const data = await usersApi.columns()
    allColumns.value = data.all_columns ?? []
    visibleColumns.value = data.visible_columns ?? visibleColumns.value
  } catch {}
}

function applyFilters() {
  pagination.value.current_page = 1
  load()
}

function resetFilters() {
  filters.value = { name: '', email: '', role: '', status: '', country: '', created_from: '', created_to: '' }
  pagination.value.current_page = 1
  load()
}

function toggleSort(col) {
  const sortableCols = ['id', 'name', 'email', 'phone', 'country', 'status', 'created_at', 'employee_number', 'department', 'extension', 'joining_date', 'terminate_date', 'manager', 'team_leader', 'last_login_at']
  if (!sortableCols.includes(col)) return
  if (sort.value === col) order.value = order.value === 'asc' ? 'desc' : 'asc'
  else { sort.value = col; order.value = 'asc' }
  pagination.value.current_page = 1
  load()
}

async function onSaveColumns(cols) {
  try {
    await usersApi.saveColumns(cols)
    visibleColumns.value = cols
    pagination.value.current_page = 1
    load()
  } catch {}
}

function startEdit(user, field) {
  if (!canEditRow(user)) return
  const val = user[field]
  editingCell.value = { userId: user.id, field }
  editValue.value = Array.isArray(val) ? (val.join(', ') || '') : (val ?? '')
}

function cancelEdit() {
  editingCell.value = null
  editValue.value = ''
}

async function saveEdit() {
  const { userId, field } = editingCell.value || {}
  if (!userId || !field) return
  savingCell.value = true
  try {
    await usersApi.patch(userId, field, editValue.value === '' ? null : editValue.value)
    const u = users.value.find((r) => r.id === userId)
    if (u) {
      if (field === 'status') u.status = editValue.value
      else u[field] = editValue.value === '' ? null : editValue.value
    }
    cancelEdit()
    toast('success', 'Field updated successfully.')
  } catch (e) {
    cancelEdit()
    toast('error', e?.response?.data?.message || 'Failed to update field.')
  } finally {
    savingCell.value = false
  }
}

function isEditing(userId, field) {
  const e = editingCell.value
  return e && e.userId === userId && e.field === field
}

function goToPage(page) {
  if (page < 1 || page > pagination.value.last_page) return
  pagination.value.current_page = page
  load()
}

async function onPerPageChange(event) {
  const newPerPage = Number(event.target.value)
  pagination.value.per_page = newPerPage
  pagination.value.current_page = 1
  try {
    await api.post(`/table-preferences/${TABLE_MODULE}`, { per_page: newPerPage })
  } catch { /* silent */ }
  load()
}

async function loadTablePreference() {
  try {
    const { data } = await api.get(`/table-preferences/${TABLE_MODULE}`)
    if (data.per_page) pagination.value.per_page = Number(data.per_page)
    if (Array.isArray(data.options) && data.options.length) perPageOptions.value = data.options
  } catch { /* use system default */ }
}

function toggleSelectAll() {
  if (selectedIds.value.length === users.value.length) selectedIds.value = []
  else selectedIds.value = users.value.map((u) => u.id)
}

function toggleSelect(id) {
  const idx = selectedIds.value.indexOf(id)
  if (idx >= 0) selectedIds.value.splice(idx, 1)
  else selectedIds.value.push(id)
}

async function bulkActivate() {
  if (!selectedIds.value.length) return
  bulkLoading.value = true
  try {
    await usersApi.bulkActivate(selectedIds.value)
    await load()
  } finally {
    bulkLoading.value = false
  }
}

async function bulkDeactivate() {
  if (!selectedIds.value.length) return
  bulkLoading.value = true
  try {
    await usersApi.bulkDeactivate(selectedIds.value)
    await load()
  } finally {
    bulkLoading.value = false
  }
}

function openDeactivateModal(user) {
  if (!user?.id || (user.roles || []).includes('superadmin')) return
  userToDeactivate.value = user
  closeActionMenu()
}

function closeDeactivateModal() {
  if (!deactivating.value) userToDeactivate.value = null
}

async function confirmDeactivate() {
  const user = userToDeactivate.value
  if (!user?.id) return
  deactivating.value = true
  try {
    await usersApi.bulkDeactivate([user.id])
    userToDeactivate.value = null
    toast('success', 'User deactivated successfully.')
    await load()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to deactivate user.')
  } finally {
    deactivating.value = false
  }
}

function openResetPasswordModal(user) {
  if (!canEditRow(user)) return
  userToResetPassword.value = user
  closeActionMenu()
}

function closeResetPasswordModal() {
  if (!resetPasswordLoading.value) userToResetPassword.value = null
}

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
  } finally {
    resetPasswordLoading.value = false
  }
}

async function openUserDetail(user) {
  if (!user?.id) return
  closeActionMenu()
  detailUser.value = null
  detailError.value = null
  detailLoading.value = true
  try {
    const response = await usersApi.show(user.id)
    const userData = response?.data?.user
    if (userData) {
      detailUser.value = userData
    } else {
      detailError.value = 'Could not load user details.'
    }
  } catch {
    detailError.value = 'Could not load user details.'
  } finally {
    detailLoading.value = false
  }
}

function closeUserDetail() {
  detailUser.value = null
  detailError.value = null
  if (route.path !== '/users') {
    router.push('/users')
  }
}

function fromDetailEditUser() {
  const u = detailUser.value
  if (u?.id) router.push(`/users/${u.id}/edit`)
  closeUserDetail()
}

function fromDetailResetPassword() {
  const u = detailUser.value
  if (u) {
    closeUserDetail()
    openResetPasswordModal(u)
  }
}

function formatRoleNameForAdd(name) {
  if (!name) return ''
  return name.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

const assignableRolesForAdd = computed(() => {
  const list = (roles.value ?? []).filter((r) => (r?.name ?? '').toLowerCase() !== 'superadmin')
  const seen = new Map()
  for (const r of list) {
    if (!r?.id) continue
    const key = (r.name ?? '').toLowerCase().replace(/[\s_-]+/g, '')
    if (!seen.has(key)) seen.set(key, r)
  }
  return Array.from(seen.values())
})

async function openAddUserModal() {
  addUserError.value = ''
  addUserFieldErrors.value = {}
  addUserSuccess.value = false
  addUserForm.value = {
    name: '',
    email: '',
    phone: '',
    country: '',
    department: '',
    status: 'approved',
    roles: [],
    password: '',
    password_confirmation: '',
  }
  addUserModalOpen.value = true
  if (addUserCountries.value.length === 0) {
    try {
      const { data } = await api.get('/countries')
      addUserCountries.value = Array.isArray(data) ? data : (data?.data ?? [])
    } catch {
      addUserCountries.value = []
    }
  }
}

function closeAddUserModal() {
  addUserModalOpen.value = false
  addUserError.value = ''
  addUserFieldErrors.value = {}
  addUserSuccess.value = false
  addUserRolesDropdownOpen.value = false
}

function toggleAddUserRole(role) {
  const id = role.id
  const idx = addUserForm.value.roles.indexOf(id)
  if (idx === -1) addUserForm.value.roles = [...addUserForm.value.roles, id]
  else addUserForm.value.roles = addUserForm.value.roles.filter((r) => r !== id)
}

function hasAddUserRole(roleId) {
  return addUserForm.value.roles.includes(roleId)
}

async function submitAddUser() {
  addUserError.value = ''
  addUserFieldErrors.value = {}
  addUserSuccess.value = false

  const f = addUserForm.value
  const errs = {}

  if (!f.name?.trim()) errs.name = 'Full name is required.'
  if (!f.email?.trim()) errs.email = 'Email address is required.'
  if (!f.phone?.trim()) {
    errs.phone = 'Phone number is required.'
  } else if (!/^[+]?[\d\s()-]+$/.test(f.phone.trim())) {
    errs.phone = 'Phone number must contain only digits, +, spaces, ( ) or -.'
  }
  if (!f.country) errs.country = 'Please select a country.'
  if (!f.department) errs.department = 'Please select a department.'
  if (!(f.roles?.length)) errs.roles = 'Please assign at least one role.'
  if (!f.password || f.password.length < 8) errs.password = 'Password must be at least 8 characters.'
  if (f.password && f.password !== f.password_confirmation) errs.password_confirmation = 'Password and confirmation do not match.'

  if (Object.keys(errs).length) {
    addUserFieldErrors.value = errs
    addUserError.value = 'Please fix the highlighted errors below.'
    return
  }

  addUserLoading.value = true
  try {
    await usersApi.store({
      name: f.name.trim(),
      email: f.email.trim(),
      phone: f.phone.trim(),
      country: f.country,
      department: f.department,
      status: 'approved',
      roles: f.roles,
      password: f.password,
      password_confirmation: f.password_confirmation,
    })
    addUserSuccess.value = true
    addUserError.value = ''
    addUserFieldErrors.value = {}
    setTimeout(() => {
      closeAddUserModal()
      toast('success', 'User created successfully.')
      load()
    }, 1500)
  } catch (e) {
    const msg = e?.response?.data?.message
    const serverErrs = e?.response?.data?.errors
    if (serverErrs) {
      const mapped = {}
      for (const [key, msgs] of Object.entries(serverErrs)) {
        mapped[key] = Array.isArray(msgs) ? msgs[0] : msgs
      }
      addUserFieldErrors.value = mapped
      addUserError.value = msg || 'Please fix the highlighted errors below.'
    } else {
      addUserError.value = msg || 'Failed to create user. Please try again.'
    }
  } finally {
    addUserLoading.value = false
  }
}

function toggleActionMenu(id) {
  actionMenuOpen.value = actionMenuOpen.value === id ? null : id
}

function closeActionMenu() {
  actionMenuOpen.value = null
}

function handleClickOutside() {
  if (actionMenuOpen.value) actionMenuOpen.value = null
}

async function openHistory(userId) {
  historyUserId.value = userId
  historyLoading.value = true
  historyLogs.value = []
  try {
    const data = await usersApi.auditLog(userId)
    historyLogs.value = data.data ?? []
  } catch {
    historyLogs.value = []
  } finally {
    historyLoading.value = false
  }
}

function closeHistory() {
  historyUserId.value = null
  historyLogs.value = []
}

function displayCellValue(user, col) {
  const val = user[col]
  if (col === 'status') return statusLabel(val)
  if (col === 'roles') return Array.isArray(val) && val.length ? val.join(', ') : '-'
  if (col === 'last_login_at' || col === 'created_at') return col === 'created_at' ? formatDate(val) : formatDateTime(val)
  if (col === 'joining_date' || col === 'terminate_date') return formatDate(val)
  if (val == null || val === '') return '-'
  return String(val)
}

const columnLabels = {
  id: 'ID',
  name: 'User',
  email: 'Email',
  phone: 'Phone',
  country: 'Country',
  roles: 'Assigned Roles',
  status: 'Status',
  last_login_at: 'Last Login',
  created_at: 'Created Date',
  employee_number: 'Employee ID',
  department: 'Department',
  extension: 'Extension',
  joining_date: 'Joining Date',
  terminate_date: 'Terminate Date',
  manager: 'Manager',
  team_leader: 'Team Leader',
}

const editableFields = ['name', 'email', 'phone', 'country', 'status', 'employee_number', 'department', 'extension', 'joining_date', 'terminate_date']

onMounted(() => {
  loadTablePreference().then(() => {
    loadFilters()
    loadColumns().then(() => load())
  })
  document.addEventListener('click', handleClickOutside)
  if (route.query.updated) {
    toast('success', `${route.query.updated} updated successfully.`)
    router.replace({ path: '/users', query: {} })
  }
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

watch(() => pagination.value.current_page, load)
watch(() => route.path, (path) => { if (path === '/users') load() })

function closeAddUserRolesDropdownOnOutsideClick() {
  if (!addUserRolesDropdownOpen.value) return
  const el = addUserRolesDropdownRef.value
  const handler = (e) => {
    if (el && !el.contains(e.target)) {
      addUserRolesDropdownOpen.value = false
      document.removeEventListener('click', handler)
    }
  }
  setTimeout(() => document.addEventListener('click', handler), 0)
}
watch(addUserRolesDropdownOpen, (open) => {
  if (open) closeAddUserRolesDropdownOnOutsideClick()
})
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-2xl font-bold text-gray-900 leading-tight">Users</h1>
          <Breadcrumbs />
        </div>
        <p class="mt-1 text-sm text-gray-500">Manage system users, assign roles, and control access.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          :disabled="!selectedIds.length || bulkLoading"
          @click="bulkActivate"
          class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Bulk Activate
        </button>
        <button
          type="button"
          :disabled="!selectedIds.length || bulkLoading"
          @click="bulkDeactivate"
          class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Bulk Deactivate
        </button>
        <button
          v-if="canCreateUsers"
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
          @click="openAddUserModal"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add New User
        </button>
      </div>
    </div>

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm border-t-4 border-t-blue-500">
        <p class="text-xs font-medium text-gray-500">Total Users</p>
        <p class="mt-1 text-2xl font-bold text-blue-600">{{ stats.total }}</p>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm border-t-4 border-t-green-500">
        <p class="text-xs font-medium text-gray-500">Active Users</p>
        <p class="mt-1 text-2xl font-bold text-green-600">{{ stats.active }}</p>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm border-t-4 border-t-red-500">
        <p class="text-xs font-medium text-gray-500">Inactive Users</p>
        <p class="mt-1 text-2xl font-bold text-red-600">{{ stats.inactive }}</p>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm border-t-4 border-t-gray-400">
        <p class="text-xs font-medium text-gray-500">Pending Approval</p>
        <p class="mt-1 text-2xl font-bold text-gray-600">{{ stats.pending }}</p>
      </div>
    </div>

    <!-- Two quick filters: User, Status + Advance Filters + Customize columns -->
    <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
      <label class="sr-only">User</label>
      <input
        v-model="filters.name"
        type="text"
        placeholder="User name..."
        class="min-w-[160px] rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
        :disabled="loading"
      />
      <label class="sr-only">Status</label>
      <select
        v-model="filters.status"
        class="min-w-[140px] rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
        :disabled="loading"
      >
        <option value="">Status</option>
        <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>
      <button
        type="button"
        class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
        :disabled="loading"
        @click="applyFilters"
      >
        Apply
      </button>
      <button
        type="button"
        class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        :disabled="loading"
        @click="resetFilters"
      >
        Reset
      </button>
      <div class="ml-auto flex flex-wrap items-center gap-2 pl-6">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
          @click="filtersVisible = !filtersVisible"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
          Advanced Filters
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
          @click="columnModalVisible = true"
        >
          Customize columns
        </button>
      </div>
    </div>

    <!-- Advance filters panel: only filters NOT in the top bar (no User name, no Status). All other possible filters. -->
    <div v-show="filtersVisible" class="rounded-lg border border-gray-200 bg-white p-4">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
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
          <label class="block text-xs font-medium text-gray-600 mb-1">Created From</label>
          <input v-model="filters.created_from" type="date" class="w-full rounded-lg border-gray-300 text-sm" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Created To</label>
          <input v-model="filters.created_to" type="date" class="w-full rounded-lg border-gray-300 text-sm" />
        </div>
      </div>
      <div class="mt-3 flex gap-2">
        <button type="button" @click="applyFilters" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Apply Filters</button>
        <button type="button" @click="resetFilters" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</button>
      </div>
    </div>

    <!-- Table: sortable + editable -->
    <div class="rounded-xl border-2 border-black bg-white shadow-sm overflow-x-auto">
      <div v-if="loading" class="flex justify-center items-center py-16">
        <svg class="animate-spin h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
      </div>
      <div v-else>
        <table class="min-w-full">
          <thead class="bg-gray-50 border-b-2 border-black">
            <tr>
              <th class="w-10 px-4 py-3">
                <input
                  type="checkbox"
                  :checked="users.length > 0 && selectedIds.length === users.length"
                  @change="toggleSelectAll"
                  class="rounded border-gray-300"
                />
              </th>
              <th
                v-for="col in visibleColumns"
                :key="col"
                class="px-4 py-3 text-left text-xs font-semibold text-gray-600 cursor-pointer hover:bg-gray-100"
                :class="{ 'bg-green-50': sort === col }"
                @click="toggleSort(col)"
              >
                <span class="inline-flex items-center gap-1">
                  {{ columnLabels[col] ?? col }}
                  <span v-if="sort === col" class="text-green-600">{{ order === 'asc' ? '↑' : '↓' }}</span>
                </span>
              </th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white">
            <tr v-for="user in users" :key="user.id" class="border-b border-black hover:bg-gray-50">
              <td class="px-4 py-3">
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(user.id)"
                  @change="toggleSelect(user.id)"
                  class="rounded border-gray-300"
                />
              </td>
              <td
                v-for="col in visibleColumns"
                :key="col"
                class="px-4 py-2 align-top"
                :class="{ 'cursor-pointer hover:bg-gray-50': editableFields.includes(col) && canEditRow(user) }"
                :title="editableFields.includes(col) && canEditRow(user) ? 'Double-click to edit' : undefined"
                @dblclick="editableFields.includes(col) && canEditRow(user) && startEdit(user, col)"
              >
                <template v-if="isEditing(user.id, col)">
                  <div class="flex flex-wrap items-center gap-1">
                    <select
                      v-if="isDropdownField(col)"
                      v-model="editValue"
                      class="rounded border border-gray-300 text-sm py-1 px-2"
                    >
                      <option v-for="opt in getStatusOptions()" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <input
                      v-else
                      v-model="editValue"
                      :type="col === 'joining_date' || col === 'terminate_date' ? 'date' : 'text'"
                      class="rounded border border-gray-300 text-sm py-1 px-2 min-w-[100px]"
                    />
                    <button
                      type="button"
                      class="rounded bg-green-600 px-2 py-1 text-xs text-white hover:bg-green-700 disabled:opacity-50"
                      :disabled="savingCell"
                      @click="saveEdit"
                    >
                      Save
                    </button>
                    <button
                      type="button"
                      class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50"
                      :disabled="savingCell"
                      @click="cancelEdit"
                    >
                      Cancel
                    </button>
                  </div>
                </template>
                <template v-else>
                  <span v-if="col === 'name'" class="font-medium text-gray-900">{{ displayCellValue(user, col) }}</span>
                  <span v-else-if="col === 'roles'" class="flex flex-wrap gap-1">
                    <span
                      v-for="r in (user.roles || [])"
                      :key="r"
                      class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 border border-blue-200"
                    >
                      {{ r }}
                    </span>
                    <span v-if="!(user.roles || []).length" class="text-gray-400">-</span>
                  </span>
                  <span v-else-if="col === 'status'">
                    <span :class="['inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold', statusBadgeClass(user.status)]">
                      {{ statusLabel(user.status) }}
                    </span>
                  </span>
                  <span v-else class="text-sm text-gray-700">{{ displayCellValue(user, col) }}</span>
                </template>
              </td>
              <td class="px-4 py-3 text-right align-top">
                <div class="relative inline-block text-left">
                  <button
                    type="button"
                    @click.stop="toggleActionMenu(user.id)"
                    class="p-2 rounded-lg hover:bg-gray-100 text-gray-600 transition-colors"
                    aria-haspopup="true"
                    :aria-expanded="actionMenuOpen === user.id"
                  >
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z" />
                    </svg>
                  </button>
                  <div
                    v-if="actionMenuOpen === user.id"
                    class="absolute right-0 top-full z-[100] mt-1 min-w-[11rem] rounded-lg border border-gray-200 bg-white py-1.5 shadow-xl"
                    @click.stop
                  >
                    <button
                      type="button"
                      class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                      @click="openUserDetail(user)"
                    >
                      <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                      View Details
                    </button>
                    <router-link
                      v-if="canEditRow(user)"
                      :to="`/users/${user.id}/edit`"
                      class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                      @click="closeActionMenu"
                    >
                      <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                      </svg>
                      Edit User
                    </router-link>
                    <button
                      v-if="canEditRow(user) && !(user.roles || []).includes('superadmin')"
                      type="button"
                      class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-medium text-red-600 hover:bg-red-50 transition-colors"
                      @click="openDeactivateModal(user)"
                    >
                      <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-500 text-white">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </span>
                      Deactivate
                    </button>
                    <button
                      v-if="canEditRow(user)"
                      type="button"
                      class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                      @click="openResetPasswordModal(user)"
                    >
                      <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                      </svg>
                      Reset Password
                    </button>
                  </div>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && users.length === 0" class="border-b border-black">
              <td :colspan="visibleColumns.length + 2" class="px-4 py-12 text-center text-sm text-gray-500">No users found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-black bg-white px-4 py-3">
        <p class="text-sm text-gray-600">
          Showing {{ pagination.total ? ((pagination.current_page - 1) * pagination.per_page) + 1 : 0 }}
          to {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}
          of {{ pagination.total }} entries
        </p>
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2 text-sm text-gray-600">
            <span class="whitespace-nowrap font-medium">Number of rows</span>
            <select
              :value="pagination.per_page"
              class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm min-w-[80px] text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
              @change="onPerPageChange"
            >
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

    <ColumnCustomizerModal
      v-model:visible="columnModalVisible"
      :all-columns="allColumns"
      :visible-columns="visibleColumns"
      :default-columns="['id', 'name', 'email', 'phone', 'country', 'roles', 'status', 'last_login_at', 'created_at']"
      @save="onSaveColumns"
    />

    <!-- Add New User modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div
          v-if="addUserModalOpen"
          class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/30 backdrop-blur-sm p-4 overflow-y-auto"
          role="dialog"
          aria-modal="true"
          aria-labelledby="add-user-modal-title"
          @click.self="closeAddUserModal"
        >
          <div class="w-full max-w-2xl my-8 max-h-[90vh] flex flex-col rounded-xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center gap-3 border-b border-gray-200 px-6 py-4">
              <button
                type="button"
                class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors"
                aria-label="Close"
                @click="closeAddUserModal"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
              </button>
              <div>
                <h2 id="add-user-modal-title" class="text-xl font-bold text-gray-900">Add New User</h2>
                <p class="text-sm text-gray-500 mt-0.5">Create a new user account and assign roles</p>
              </div>
            </div>
            <form @submit.prevent="submitAddUser" class="flex-1 min-h-0 flex flex-col overflow-hidden">
              <div class="overflow-y-auto flex-1 min-h-0 p-6 space-y-6 relative">
              <!-- Loading overlay -->
              <div v-if="addUserLoading" class="absolute inset-0 z-20 flex items-center justify-center bg-white/80 rounded-b-xl">
                <div class="flex flex-col items-center gap-3">
                  <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                  </svg>
                  <span class="text-sm font-medium text-gray-600">Creating user...</span>
                </div>
              </div>

              <!-- Success message -->
              <div v-if="addUserSuccess" class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700 flex items-center gap-2">
                <svg class="h-5 w-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                User created successfully! Redirecting...
              </div>

              <!-- Error message -->
              <p v-if="addUserError && !addUserSuccess" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ addUserError }}</p>

              <section>
                <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 mb-4">
                  <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                  Basic Information
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label for="add-user-name" class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                    <input
                      id="add-user-name"
                      v-model="addUserForm.name"
                      type="text"
                      class="w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                      :class="addUserFieldErrors.name ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                      placeholder="Enter full name"
                    />
                    <p v-if="addUserFieldErrors.name" class="mt-1 text-xs text-red-600">{{ addUserFieldErrors.name }}</p>
                  </div>
                  <div>
                    <label for="add-user-email" class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                    <input
                      id="add-user-email"
                      v-model="addUserForm.email"
                      type="email"
                      class="w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                      :class="addUserFieldErrors.email ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                      placeholder="Enter email address"
                    />
                    <p v-if="addUserFieldErrors.email" class="mt-1 text-xs text-red-600">{{ addUserFieldErrors.email }}</p>
                  </div>
                  <div>
                    <label for="add-user-phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number <span class="text-red-500">*</span></label>
                    <input
                      id="add-user-phone"
                      v-model="addUserForm.phone"
                      type="text"
                      class="w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                      :class="addUserFieldErrors.phone ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                      placeholder="Enter phone number (digits only)"
                    />
                    <p v-if="addUserFieldErrors.phone" class="mt-1 text-xs text-red-600">{{ addUserFieldErrors.phone }}</p>
                  </div>
                  <div>
                    <label for="add-user-country" class="block text-sm font-medium text-gray-700 mb-1">Country <span class="text-red-500">*</span></label>
                    <select
                      id="add-user-country"
                      v-model="addUserForm.country"
                      class="w-full rounded-lg border bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                      :class="addUserFieldErrors.country ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                    >
                      <option value="">Select country</option>
                      <option v-for="c in addUserCountries" :key="c.id" :value="c.code || c.name">{{ c.name }}</option>
                    </select>
                    <p v-if="addUserFieldErrors.country" class="mt-1 text-xs text-red-600">{{ addUserFieldErrors.country }}</p>
                  </div>
                  <div>
                    <label for="add-user-department" class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-500">*</span></label>
                    <select
                      id="add-user-department"
                      v-model="addUserForm.department"
                      class="w-full rounded-lg border bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                      :class="addUserFieldErrors.department ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                    >
                      <option value="">Select department</option>
                      <option v-for="d in ADD_USER_DEPARTMENTS" :key="d.value" :value="d.value">{{ d.label }}</option>
                    </select>
                    <p v-if="addUserFieldErrors.department" class="mt-1 text-xs text-red-600">{{ addUserFieldErrors.department }}</p>
                  </div>
                  <div>
                    <label for="add-user-status" class="block text-sm font-medium text-gray-700 mb-1">Account Status</label>
                    <select
                      id="add-user-status"
                      v-model="addUserForm.status"
                      class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                      disabled
                    >
                      <option value="approved">Active</option>
                    </select>
                  </div>
                </div>
              </section>

              <section>
                <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 mb-4">
                  <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                  Role Assignment
                </h3>
                <div class="relative" ref="addUserRolesDropdownRef">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Roles <span class="text-red-500">*</span></label>
                  <button
                    type="button"
                    class="w-full rounded-lg border bg-white px-3 py-2 text-left text-sm flex items-center justify-between focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    :class="addUserFieldErrors.roles ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                    @click="addUserRolesDropdownOpen = !addUserRolesDropdownOpen"
                  >
                    <span :class="addUserForm.roles.length ? 'text-gray-900' : 'text-gray-500'" class="truncate">
                      {{ addUserForm.roles.length ? assignableRolesForAdd.filter(r => addUserForm.roles.includes(r.id)).map(r => formatRoleNameForAdd(r.name)).join(', ') : 'Select roles to assign' }}
                    </span>
                    <svg class="h-4 w-4 text-gray-400 shrink-0 transition-transform" :class="addUserRolesDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                  <p v-if="addUserFieldErrors.roles" class="mt-1 text-xs text-red-600">{{ addUserFieldErrors.roles }}</p>
                  <div
                    v-show="addUserRolesDropdownOpen"
                    class="absolute z-10 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg"
                  >
                    <div class="max-h-48 overflow-y-auto">
                      <button
                        v-for="r in assignableRolesForAdd"
                        :key="r.id"
                        type="button"
                        class="w-full px-3 py-2.5 text-left text-sm hover:bg-gray-50 flex items-center gap-2"
                        :class="hasAddUserRole(r.id) ? 'bg-blue-50 text-blue-800' : 'text-gray-700'"
                        @click="toggleAddUserRole(r)"
                      >
                        <span class="w-4 h-4 flex items-center justify-center shrink-0 rounded border" :class="hasAddUserRole(r.id) ? 'bg-blue-600 border-blue-600 text-white' : 'border-gray-300'">
                          <svg v-if="hasAddUserRole(r.id)" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                        </span>
                        {{ formatRoleNameForAdd(r.name) }}
                      </button>
                      <p v-if="!assignableRolesForAdd.length" class="px-3 py-2 text-sm text-gray-500">No roles available.</p>
                    </div>
                    <div class="border-t border-gray-200 px-3 py-2 flex justify-end">
                      <button
                        type="button"
                        class="rounded-lg bg-blue-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @click="addUserRolesDropdownOpen = false"
                      >
                        Done
                      </button>
                    </div>
                  </div>
                </div>
              </section>

              <section>
                <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 mb-4">
                  <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                  Set Password
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label for="add-user-password" class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                    <input
                      id="add-user-password"
                      v-model="addUserForm.password"
                      type="password"
                      class="w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                      :class="addUserFieldErrors.password ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                      placeholder="Enter password"
                    />
                    <p v-if="addUserFieldErrors.password" class="mt-1 text-xs text-red-600">{{ addUserFieldErrors.password }}</p>
                    <p v-else class="mt-1 text-xs text-gray-500">Minimum 8 characters</p>
                  </div>
                  <div>
                    <label for="add-user-password-confirm" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
                    <input
                      id="add-user-password-confirm"
                      v-model="addUserForm.password_confirmation"
                      type="password"
                      class="w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                      :class="addUserFieldErrors.password_confirmation ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                      placeholder="Confirm password"
                    />
                    <p v-if="addUserFieldErrors.password_confirmation" class="mt-1 text-xs text-red-600">{{ addUserFieldErrors.password_confirmation }}</p>
                  </div>
                </div>
              </section>
              </div>

              <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                <button
                  type="button"
                  class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300"
                  :disabled="addUserLoading || addUserSuccess"
                  @click="closeAddUserModal"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="addUserLoading || addUserSuccess"
                  class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-70"
                >
                  <svg v-if="addUserLoading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                  </svg>
                  <svg v-else-if="addUserSuccess" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                  {{ addUserLoading ? 'Creating...' : addUserSuccess ? 'Created!' : 'Create User' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Confirm Deactivation modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div
          v-if="userToDeactivate"
          class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/30 backdrop-blur-sm p-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="deactivate-modal-title"
          @click.self="closeDeactivateModal"
        >
          <div class="w-full max-w-md rounded-xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
              <h2 id="deactivate-modal-title" class="text-lg font-bold text-gray-900">Confirm Deactivation</h2>
              <button
                type="button"
                class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                aria-label="Close"
                :disabled="deactivating"
                @click="closeDeactivateModal"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <p class="px-6 py-5 text-gray-700">
              Are you sure you want to deactivate {{ userToDeactivate?.name }}'s account?
            </p>
            <div class="flex items-center gap-3 px-6 pb-6">
              <button
                type="button"
                class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-70"
                :disabled="deactivating"
                @click="confirmDeactivate"
              >
                {{ deactivating ? 'Deactivating…' : 'Confirm' }}
              </button>
              <button
                type="button"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 disabled:opacity-70"
                :disabled="deactivating"
                @click="closeDeactivateModal"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Confirm Password Reset modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div
          v-if="userToResetPassword"
          class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/30 backdrop-blur-sm p-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="reset-password-modal-title"
          @click.self="closeResetPasswordModal"
        >
          <div class="w-full max-w-md rounded-xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
              <h2 id="reset-password-modal-title" class="text-lg font-bold text-gray-900">Confirm Password Reset</h2>
              <button
                type="button"
                class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                aria-label="Close"
                :disabled="resetPasswordLoading"
                @click="closeResetPasswordModal"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <p class="px-6 py-5 text-gray-700">
              Are you sure you want to reset the password for <strong>{{ userToResetPassword?.name }}</strong>? They will receive an email with instructions to set a new password.
            </p>
            <div class="flex items-center gap-3 px-6 pb-6">
              <button
                type="button"
                class="rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-70"
                :disabled="resetPasswordLoading"
                @click="confirmResetPassword"
              >
                {{ resetPasswordLoading ? 'Sending…' : 'Confirm' }}
              </button>
              <button
                type="button"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 disabled:opacity-70"
                :disabled="resetPasswordLoading"
                @click="closeResetPasswordModal"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- User Details popup -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div
          v-if="detailUser !== null || detailLoading"
          class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/30 backdrop-blur-sm p-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="user-detail-modal-title"
          @click.self="closeUserDetail"
        >
          <div class="w-full max-w-lg max-h-[90vh] flex flex-col rounded-xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
              <h2 id="user-detail-modal-title" class="text-lg font-bold text-gray-900">User Details</h2>
              <button
                type="button"
                class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                aria-label="Close"
                :disabled="detailLoading"
                @click="closeUserDetail"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <div v-if="detailLoading" class="flex justify-center py-12">
              <svg class="h-8 w-8 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </div>
            <p v-else-if="detailError" class="px-6 py-6 text-sm text-red-600">{{ detailError }}</p>
            <template v-else-if="detailUser">
              <div class="overflow-y-auto flex-1 min-h-0">
                <div class="px-6 py-5">
                  <div class="flex items-start gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-blue-600 text-lg font-semibold text-white">
                      {{ getInitials(detailUser.name) }}
                    </div>
                    <div class="min-w-0 flex-1">
                      <p class="font-semibold text-gray-900">{{ detailUser.name }}</p>
                      <p class="text-sm text-gray-500">User ID: {{ userIdDisplay(detailUser.id) }}</p>
                    </div>
                    <span
                      class="shrink-0 rounded-full px-3 py-1 text-xs font-medium"
                      :class="statusBadgeClass(detailUser.status)"
                    >
                      {{ statusLabel(detailUser.status) }}
                    </span>
                  </div>
                </div>
                <div class="border-t border-gray-100 px-6 py-4">
                  <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                    <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Basic Information
                  </h3>
                  <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <div>
                      <dt class="text-gray-500">Full Name</dt>
                      <dd class="font-medium text-gray-900">{{ detailUser.name ?? '—' }}</dd>
                    </div>
                    <div>
                      <dt class="text-gray-500">Status</dt>
                      <dd class="font-medium text-gray-900">{{ statusLabel(detailUser.status) }}</dd>
                    </div>
                    <div>
                      <dt class="text-gray-500">Last Login</dt>
                      <dd class="text-gray-700">{{ formatDetailDateTime(detailUser.last_login_at) }}</dd>
                    </div>
                    <div>
                      <dt class="text-gray-500">Created Date</dt>
                      <dd class="text-gray-700">{{ formatDetailDateTime(detailUser.created_at) }}</dd>
                    </div>
                    <div class="col-span-2">
                      <dt class="text-gray-500">Email</dt>
                      <dd class="text-gray-700">{{ detailUser.email ?? '—' }}</dd>
                    </div>
                    <div>
                      <dt class="text-gray-500">Phone Number</dt>
                      <dd class="text-gray-700">{{ detailUser.phone ?? '—' }}</dd>
                    </div>
                    <div>
                      <dt class="text-gray-500">Country</dt>
                      <dd class="text-gray-700">{{ detailUser.country ?? '—' }}</dd>
                    </div>
                  </dl>
                </div>
                <div class="border-t border-gray-100 px-6 py-4">
                  <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                    <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    Assigned Roles
                  </h3>
                  <div class="flex flex-wrap gap-2">
                    <span
                      v-for="role in (detailUser.roles || []).map(r => typeof r === 'string' ? r : r.name)"
                      :key="role"
                      class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800"
                    >
                      {{ role }}
                    </span>
                    <span v-if="!(detailUser.roles || []).length" class="text-sm text-gray-500">No roles assigned</span>
                  </div>
                </div>
              </div>
              <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 px-6 py-4">
                <button
                  v-if="canEditRow(detailUser)"
                  type="button"
                  class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                  @click="fromDetailEditUser"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                  Edit User
                </button>
                <button
                  v-if="canEditRow(detailUser)"
                  type="button"
                  class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300"
                  @click="fromDetailResetPassword"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                  Reset Password
                </button>
                <button
                  type="button"
                  class="ml-auto rounded-lg border border-gray-300 bg-gray-50 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-300"
                  @click="closeUserDetail"
                >
                  Close
                </button>
              </div>
            </template>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- History modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div
          v-if="historyUserId"
          class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/50 p-4"
          role="dialog"
          aria-modal="true"
          @click.self="closeHistory"
        >
          <div class="w-full max-w-2xl max-h-[80vh] flex flex-col rounded-xl bg-white shadow-xl overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
              <h2 class="text-lg font-semibold text-gray-900">Change History</h2>
              <button type="button" class="rounded p-1.5 text-gray-400 hover:bg-gray-100" @click="closeHistory">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>
            <div class="overflow-y-auto p-6">
              <div v-if="historyLoading" class="flex justify-center py-8">
                <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
              </div>
              <table v-else class="min-w-full text-sm">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="text-left py-2 font-medium text-gray-600">Field</th>
                    <th class="text-left py-2 font-medium text-gray-600">Old value</th>
                    <th class="text-left py-2 font-medium text-gray-600">New value</th>
                    <th class="text-left py-2 font-medium text-gray-600">Date & time</th>
                    <th class="text-left py-2 font-medium text-gray-600">Changed by</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="log in historyLogs" :key="log.id" class="border-b border-gray-100">
                    <td class="py-2 font-medium text-gray-700">{{ log.field_label || log.field_name }}</td>
                    <td class="py-2 text-gray-600">{{ log.old_value ?? '(empty)' }}</td>
                    <td class="py-2 text-gray-600">{{ log.new_value ?? '(empty)' }}</td>
                    <td class="py-2 text-gray-600">{{ log.changed_at ? formatDateTime(log.changed_at) : '—' }}</td>
                    <td class="py-2 text-gray-600">{{ log.changed_by_name || log.changed_by || '—' }}</td>
                  </tr>
                  <tr v-if="!historyLoading && historyLogs.length === 0">
                    <td colspan="5" class="py-8 text-center text-gray-500">No history recorded.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
