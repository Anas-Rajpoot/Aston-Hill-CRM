<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import teamsApi from '@/services/teamsApi'
import api from '@/lib/axios'

const router = useRouter()

const DEPARTMENTS = [
  { value: 'sales', label: 'Sales' },
  { value: 'backoffice', label: 'Back Office' },
  { value: 'field', label: 'Field' },
  { value: 'csr', label: 'CSR' },
  { value: 'admin', label: 'Admin' },
  { value: 'it', label: 'IT' },
]

const form = ref({
  name: '',
  description: '',
  manager_id: '',
  team_leader_id: '',
  manager_ids: [],
  team_leader_ids: [],
  member_ids: [],
  department: '',
  status: 'active',
  max_members: '',
})

const allUsers = ref([])
const managerSearch = ref('')
const leaderSearch = ref('')
const memberSearch = ref('')
const managerDropdownOpen = ref(false)
const leaderDropdownOpen = ref(false)
const memberDropdownOpen = ref(false)
const loading = ref(false)
const error = ref('')
const errors = ref({})
const successMessage = ref('')

const ROLE_KEYS = {
  salesManager: ['manager'],
  salesLeader: ['teamleader', 'teamlead'],
  salesMember: ['salesagent', 'salesrepresentative', 'salesrep'],
  backoffice: ['backoffice', 'backofficeexecutive', 'backofficeagent'],
  field: ['fieldagent', 'fieldexecutive'],
  csr: ['csr', 'customersupportrepresentative', 'customersupport', 'supportrepresentative'],
  admin: ['executivemanager'],
  it: ['itmanager'],
}

function departmentRoleKeys() {
  const department = String(form.value.department || '').toLowerCase()

  if (department === 'backoffice') {
    return {
      manager: ROLE_KEYS.backoffice,
      leader: ROLE_KEYS.backoffice,
      member: ROLE_KEYS.backoffice,
    }
  }
  if (department === 'field') {
    return {
      manager: ROLE_KEYS.field,
      leader: ROLE_KEYS.field,
      member: ROLE_KEYS.field,
    }
  }
  if (department === 'csr') {
    return {
      manager: ROLE_KEYS.csr,
      leader: ROLE_KEYS.csr,
      member: ROLE_KEYS.csr,
    }
  }
  if (department === 'admin') {
    return {
      manager: ROLE_KEYS.admin,
      leader: ROLE_KEYS.admin,
      member: ROLE_KEYS.admin,
    }
  }
  if (department === 'it') {
    return {
      manager: ROLE_KEYS.it,
      leader: ROLE_KEYS.it,
      member: ROLE_KEYS.it,
    }
  }

  // Sales/default mapping
  return {
    manager: ROLE_KEYS.salesManager,
    leader: ROLE_KEYS.salesLeader,
    member: ROLE_KEYS.salesMember,
  }
}

function normalizeRoleKey(value) {
  return String(value || '').toLowerCase().replace(/[^a-z0-9]/g, '')
}

function userRoleKeys(user) {
  const roles = Array.isArray(user?.roles) ? user.roles : []
  return roles.map((r) => normalizeRoleKey(typeof r === 'string' ? r : (r?.name || r?.key || r?.label || ''))).filter(Boolean)
}

function hasUserRole(user, keys) {
  const allowed = new Set(keys.map((k) => normalizeRoleKey(k)))
  return userRoleKeys(user).some((k) => allowed.has(k))
}

const filteredManagers = computed(() => {
  const term = managerSearch.value.toLowerCase()
  const blocked = new Set([...(form.value.team_leader_ids || []), ...(form.value.member_ids || [])])
  const pool = allUsers.value.filter((u) => !blocked.has(u.id))
  const roleKeys = departmentRoleKeys().manager
  const base = pool.filter((u) => hasUserRole(u, roleKeys))
  if (!term) return base
  return base.filter((u) => u.name.toLowerCase().includes(term) || u.email?.toLowerCase().includes(term))
})

const filteredLeaders = computed(() => {
  const term = leaderSearch.value.toLowerCase()
  const blocked = new Set([...(form.value.manager_ids || []), ...(form.value.member_ids || [])])
  const pool = allUsers.value.filter((u) => !blocked.has(u.id) && !isSuperAdminUser(u))
  const roleKeys = departmentRoleKeys().leader
  const base = pool.filter((u) => hasUserRole(u, roleKeys))
  if (!term) return base
  return base.filter((u) => u.name.toLowerCase().includes(term) || u.email?.toLowerCase().includes(term))
})

const filteredMembers = computed(() => {
  const term = memberSearch.value.toLowerCase()
  const blocked = new Set([...(form.value.manager_ids || []), ...(form.value.team_leader_ids || [])])
  const pool = allUsers.value.filter((u) => !blocked.has(u.id) && !isSuperAdminUser(u))
  const roleKeys = departmentRoleKeys().member
  const base = pool.filter((u) => hasUserRole(u, roleKeys))
  if (!term) return base
  return base.filter((u) => u.name.toLowerCase().includes(term) || u.email?.toLowerCase().includes(term))
})

const selectedManagers = computed(() => allUsers.value.filter((u) => (form.value.manager_ids || []).includes(u.id)))
const selectedLeaders = computed(() => allUsers.value.filter((u) => (form.value.team_leader_ids || []).includes(u.id)))
const selectedMembers = computed(() => allUsers.value.filter((u) => (form.value.member_ids || []).includes(u.id)))
const selectedUsersCount = computed(() => {
  return (form.value.manager_ids || []).length + (form.value.team_leader_ids || []).length + (form.value.member_ids || []).length
})
const maxMembersLimit = computed(() => {
  const parsed = Number.parseInt(String(form.value.max_members ?? ''), 10)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null
})

function isSuperAdminUser(user) {
  return hasUserRole(user, ['superadmin'])
}

function setMaxMembersError() {
  errors.value = {
    ...errors.value,
    max_members: ['Total selected users (Manager + Team Leader + Team Members) cannot exceed Max Members.'],
  }
}

function clearMaxMembersError() {
  if (!errors.value.max_members) return
  const next = { ...errors.value }
  delete next.max_members
  errors.value = next
}

function validateMaxMembersConstraint() {
  if (maxMembersLimit.value !== null && selectedUsersCount.value > maxMembersLimit.value) {
    setMaxMembersError()
    return false
  }
  clearMaxMembersError()
  return true
}

function closeManagerDropdownSoon() {
  window.setTimeout(() => { managerDropdownOpen.value = false }, 200)
}
function closeLeaderDropdownSoon() {
  window.setTimeout(() => { leaderDropdownOpen.value = false }, 200)
}
function closeMemberDropdownSoon() {
  window.setTimeout(() => { memberDropdownOpen.value = false }, 200)
}
function doneManagerDropdown() {
  managerDropdownOpen.value = false
}
function doneLeaderDropdown() {
  leaderDropdownOpen.value = false
}
function doneMemberDropdown() {
  memberDropdownOpen.value = false
}
function closeManagerDropdown() {
  managerSearch.value = ''
  managerDropdownOpen.value = false
}
function closeLeaderDropdown() {
  leaderSearch.value = ''
  leaderDropdownOpen.value = false
}
function closeMemberDropdown() {
  memberSearch.value = ''
  memberDropdownOpen.value = false
}

function initials(name) {
  if (!name) return '?'
  return name.split(' ').map((w) => w[0]).join('').toUpperCase().slice(0, 2)
}

function normalizeSelections() {
  // Manager is single-select; Team Leader and Members are multi-select.
  const managerIds = (form.value.manager_ids || []).map((id) => Number(id)).filter(Boolean)
  const leaderIds = (form.value.team_leader_ids || []).map((id) => Number(id)).filter(Boolean)
  const memberIds = (form.value.member_ids || []).map((id) => Number(id)).filter(Boolean)
  const userById = new Map(allUsers.value.map((u) => [u.id, u]))

  const managerId = managerIds[0] ?? null
  const leaderList = [...new Set(
    leaderIds.filter((id) => id !== managerId && !isSuperAdminUser(userById.get(id)))
  )]

  form.value.manager_ids = managerId ? [managerId] : []
  form.value.team_leader_ids = leaderList
  form.value.member_ids = [...new Set(
    memberIds.filter((id) => id !== managerId && !leaderList.includes(id) && !isSuperAdminUser(userById.get(id)))
  )]
  form.value.manager_id = managerId ?? ''
  // Keep legacy single-value field for backward compatibility on APIs that still read it.
  form.value.team_leader_id = leaderList[0] ?? ''
}

function toggleManager(user) {
  const current = form.value.manager_ids?.[0]
  const willAddNew = current !== user.id && !current
  if (willAddNew && maxMembersLimit.value !== null && selectedUsersCount.value + 1 > maxMembersLimit.value) {
    setMaxMembersError()
    return
  }
  form.value.manager_ids = current === user.id ? [] : [user.id]
  // If selected as Manager, remove from Team Leader and Members.
  form.value.team_leader_ids = (form.value.team_leader_ids || []).filter((id) => id !== user.id)
  form.value.member_ids = (form.value.member_ids || []).filter((id) => id !== user.id)
  normalizeSelections()
  validateMaxMembersConstraint()
}

function toggleLeader(user) {
  if (isSuperAdminUser(user)) return
  const ids = new Set(form.value.team_leader_ids || [])
  const isAlreadySelected = ids.has(user.id)
  if (!isAlreadySelected && maxMembersLimit.value !== null && selectedUsersCount.value + 1 > maxMembersLimit.value) {
    setMaxMembersError()
    return
  }
  if (ids.has(user.id)) ids.delete(user.id)
  else ids.add(user.id)
  form.value.team_leader_ids = [...ids]
  // If selected as Team Leader, remove from Manager and Members.
  form.value.manager_ids = (form.value.manager_ids || []).filter((id) => id !== user.id)
  form.value.member_ids = (form.value.member_ids || []).filter((id) => id !== user.id)
  normalizeSelections()
  validateMaxMembersConstraint()
}

function toggleMember(user) {
  if (isSuperAdminUser(user)) return
  if ((form.value.manager_ids || []).includes(user.id) || (form.value.team_leader_ids || []).includes(user.id)) return
  const ids = new Set(form.value.member_ids || [])
  const isAlreadySelected = ids.has(user.id)
  if (!isAlreadySelected && maxMembersLimit.value !== null && selectedUsersCount.value + 1 > maxMembersLimit.value) {
    setMaxMembersError()
    return
  }
  if (ids.has(user.id)) ids.delete(user.id)
  else ids.add(user.id)
  form.value.member_ids = [...ids]
  normalizeSelections()
  validateMaxMembersConstraint()
}

function removeManager(id) { form.value.manager_ids = (form.value.manager_ids || []).filter((x) => x !== id); normalizeSelections(); validateMaxMembersConstraint() }
function removeLeader(id) { form.value.team_leader_ids = (form.value.team_leader_ids || []).filter((x) => x !== id); normalizeSelections(); validateMaxMembersConstraint() }
function removeMember(id) { form.value.member_ids = (form.value.member_ids || []).filter((x) => x !== id); normalizeSelections(); validateMaxMembersConstraint() }

onMounted(async () => {
  try {
    const { data } = await api.get('/users', {
      params: { per_page: 200, columns: ['name', 'email', 'roles'] },
    })
    const list = data?.users ?? data?.data?.users ?? data?.data ?? []
    allUsers.value = (Array.isArray(list) ? list : [])
      .map((u) => ({
        id: Number(u.id),
        name: u.name,
        email: u.email,
        roles: u.roles || [],
      }))
      .filter((u) => Number.isFinite(u.id))
  } catch { /* silent */ }
  normalizeSelections()
  validateMaxMembersConstraint()
})

function fieldError(field) {
  return errors.value[field]?.[0] ?? null
}

const save = async () => {
  error.value = ''
  errors.value = {}
  successMessage.value = ''

  if (!form.value.name.trim()) {
    errors.value.name = ['Team name is required.']
    return
  }
  if (!form.value.department) {
    errors.value.department = ['Department is required.']
    return
  }
  if (!(form.value.manager_ids || []).length) {
    errors.value.manager_ids = ['Manager is required.']
    return
  }
  if (!(form.value.team_leader_ids || []).length) {
    errors.value.team_leader_ids = ['Team Leader is required.']
    return
  }
  if (!validateMaxMembersConstraint()) return

  loading.value = true
  try {
    const payload = { ...form.value }
    payload.manager_ids = (payload.manager_ids || []).map((id) => Number(id)).filter(Boolean)
    payload.team_leader_ids = (payload.team_leader_ids || []).map((id) => Number(id)).filter(Boolean)
    payload.member_ids = (payload.member_ids || []).map((id) => Number(id)).filter(Boolean)
    payload.manager_id = payload.manager_ids[0] ?? null
    payload.team_leader_id = payload.team_leader_ids[0] ?? null
    if (!payload.max_members) payload.max_members = null
    else payload.max_members = parseInt(payload.max_members, 10)

    await teamsApi.store(payload)
    successMessage.value = 'Team created successfully.'
    setTimeout(() => router.push('/teams'), 1200)
  } catch (e) {
    const msg = e?.response?.data?.message
    const errs = e?.response?.data?.errors
    errors.value = errs || {}
    error.value = msg || (errs ? Object.values(errs).flat().join(' ') : 'Failed to create team.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white p-6">
    <!-- Loading overlay -->
    <div v-if="loading" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-8 py-6 shadow-lg">
        <svg class="h-10 w-10 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <span class="text-sm font-medium text-gray-700">Creating team…</span>
      </div>
    </div>

    <!-- Page header -->
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Create New Team</h1>
        <p class="mt-1 text-sm text-gray-500">Set up a new team with leadership and department assignment.</p>
      </div>
      <router-link to="/teams" class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        Back to Teams
      </router-link>
    </div>

    <!-- Alerts -->
    <div v-if="error" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ error }}</div>
    <div v-if="successMessage" class="mb-4 rounded-lg border border-brand-primary-muted bg-brand-primary-light p-4 text-sm text-brand-primary-hover">{{ successMessage }}</div>

    <form @submit.prevent="save" class="w-full">
      <!-- Section: Team Details -->
      <div class="border border-black rounded mb-6">
        <div class="border-b border-black bg-gray-50 px-5 py-3">
          <h2 class="text-sm font-semibold text-gray-900">Team Details</h2>
        </div>
        <div class="p-5">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
            <div class="lg:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Team Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="{ 'border-red-400': fieldError('name') }" placeholder="e.g. Sales Team Alpha" />
              <p v-if="fieldError('name')" class="mt-1 text-xs text-red-600">{{ fieldError('name') }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-500">*</span></label>
              <select
                v-model="form.department"
                required
                class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                :class="{ 'border-red-400': fieldError('department') }"
              >
                <option value="">Select Department</option>
                <option v-for="d in DEPARTMENTS" :key="d.value" :value="d.value">{{ d.label }}</option>
              </select>
              <p v-if="fieldError('department')" class="mt-1 text-xs text-red-600">{{ fieldError('department') }}</p>
            </div>

            <div class="lg:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="form.description" rows="3" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" placeholder="Brief team description…" />
              <p class="mt-1 text-xs text-gray-400">{{ (form.description || '').length }}/1000</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Max Members</label>
              <input v-model="form.max_members" type="number" min="1" max="100" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" :class="{ 'border-red-400': fieldError('max_members') }" placeholder="Unlimited if blank" @input="validateMaxMembersConstraint" />
              <p v-if="fieldError('max_members')" class="mt-1 text-xs text-red-600">{{ fieldError('max_members') }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Section: Leadership -->
      <div class="border border-black rounded mb-6">
        <div class="border-b border-black bg-gray-50 px-5 py-3">
          <h2 class="text-sm font-semibold text-gray-900">Leadership</h2>
        </div>
        <div class="p-5">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
            <!-- Manager -->
            <div class="relative">
              <label class="block text-sm font-medium text-gray-700 mb-1">Manager <span class="text-red-500">*</span></label>
              <div class="rounded border border-gray-300 px-3 py-2 text-sm" @click="managerDropdownOpen = true">
                <div v-if="selectedManagers.length" class="mb-2 flex flex-wrap gap-1.5">
                  <span v-for="u in selectedManagers" :key="u.id" class="inline-flex items-center gap-1 rounded bg-brand-primary-light px-2 py-1 text-xs text-brand-primary-hover">
                    {{ u.name }}
                    <button type="button" class="text-brand-primary-hover hover:text-brand-primary-dark" @click.stop="removeManager(u.id)">&times;</button>
                  </span>
                </div>
                <input v-model="managerSearch" type="text" class="w-full border-0 p-0 text-sm focus:ring-0" placeholder="Select team manager" @focus="managerDropdownOpen = true" @blur="closeManagerDropdownSoon" />
                <div v-if="managerDropdownOpen" class="absolute z-20 top-full left-0 right-0 mt-1 max-h-52 overflow-y-auto rounded border border-gray-200 bg-white shadow-lg">
                  <button v-for="u in filteredManagers.slice(0, 40)" :key="u.id" type="button" class="flex items-center gap-2 w-full px-3 py-2 hover:bg-gray-50 text-left" @mousedown.prevent="toggleManager(u)">
                    <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-primary" :checked="(form.manager_ids || []).includes(u.id)" />
                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-200 text-[9px] font-bold text-gray-600">{{ initials(u.name) }}</span>
                    <div class="min-w-0">
                      <p class="text-sm text-gray-900 truncate">{{ u.name }}</p>
                      <p class="text-xs text-gray-500 truncate">{{ u.email }}</p>
                    </div>
                  </button>
                  <p v-if="!filteredManagers.length" class="px-3 py-2 text-xs text-gray-500">No users found.</p>
                  <div class="sticky bottom-0 flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2 border-t border-gray-200 bg-white px-3 py-2">
                    <button type="button" class="w-full sm:w-auto rounded border border-gray-300 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50" @mousedown.prevent="closeManagerDropdown">Close</button>
                    <button type="button" class="w-full sm:w-auto rounded bg-brand-primary px-3 py-2 text-xs text-white hover:bg-brand-primary-hover" @mousedown.prevent="doneManagerDropdown">Done</button>
                  </div>
                </div>
              </div>
              <p v-if="fieldError('manager_ids')" class="mt-1 text-xs text-red-600">{{ fieldError('manager_ids') }}</p>
            </div>

            <!-- Team Leader -->
            <div class="relative">
              <label class="block text-sm font-medium text-gray-700 mb-1">Team Leader <span class="text-red-500">*</span></label>
              <div class="rounded border border-gray-300 px-3 py-2 text-sm" @click="leaderDropdownOpen = true">
                <div v-if="selectedLeaders.length" class="mb-2 flex flex-wrap gap-1.5">
                  <span v-for="u in selectedLeaders" :key="u.id" class="inline-flex items-center gap-1 rounded bg-brand-primary-light px-2 py-1 text-xs text-brand-primary-hover">
                    {{ u.name }}
                    <button type="button" class="text-brand-primary-hover hover:text-brand-primary-dark" @click.stop="removeLeader(u.id)">&times;</button>
                  </span>
                </div>
                <input v-model="leaderSearch" type="text" class="w-full border-0 p-0 text-sm focus:ring-0" placeholder="Select team leader" @focus="leaderDropdownOpen = true" @blur="closeLeaderDropdownSoon" />
                <div v-if="leaderDropdownOpen" class="absolute z-20 top-full left-0 right-0 mt-1 max-h-52 overflow-y-auto rounded border border-gray-200 bg-white shadow-lg">
                  <button v-for="u in filteredLeaders.slice(0, 40)" :key="u.id" type="button" class="flex items-center gap-2 w-full px-3 py-2 hover:bg-gray-50 text-left" @mousedown.prevent="toggleLeader(u)">
                    <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-primary" :checked="(form.team_leader_ids || []).includes(u.id)" />
                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-200 text-[9px] font-bold text-gray-600">{{ initials(u.name) }}</span>
                    <div class="min-w-0">
                      <p class="text-sm text-gray-900 truncate">{{ u.name }}</p>
                      <p class="text-xs text-gray-500 truncate">{{ u.email }}</p>
                    </div>
                  </button>
                  <p v-if="!filteredLeaders.length" class="px-3 py-2 text-xs text-gray-500">No users found.</p>
                  <div class="sticky bottom-0 flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2 border-t border-gray-200 bg-white px-3 py-2">
                    <button type="button" class="w-full sm:w-auto rounded border border-gray-300 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50" @mousedown.prevent="closeLeaderDropdown">Close</button>
                    <button type="button" class="w-full sm:w-auto rounded bg-brand-primary px-3 py-2 text-xs text-white hover:bg-brand-primary-hover" @mousedown.prevent="doneLeaderDropdown">Done</button>
                  </div>
                </div>
              </div>
              <p v-if="fieldError('team_leader_ids')" class="mt-1 text-xs text-red-600">{{ fieldError('team_leader_ids') }}</p>
            </div>

            <!-- Team Members -->
            <div class="relative md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Team Members</label>
              <div class="rounded border border-gray-300 px-3 py-2 text-sm" @click="memberDropdownOpen = true">
                <div v-if="selectedMembers.length" class="mb-2 flex flex-wrap gap-1.5">
                  <span v-for="u in selectedMembers" :key="u.id" class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-1 text-xs text-gray-800">
                    {{ u.name }}
                    <button type="button" class="text-gray-700 hover:text-gray-900" @click.stop="removeMember(u.id)">&times;</button>
                  </span>
                </div>
                <input v-model="memberSearch" type="text" class="w-full border-0 p-0 text-sm focus:ring-0" placeholder="Select team members" @focus="memberDropdownOpen = true" @blur="closeMemberDropdownSoon" />
                <div v-if="memberDropdownOpen" class="absolute z-20 top-full left-0 right-0 mt-1 max-h-56 overflow-y-auto rounded border border-gray-200 bg-white shadow-lg">
                  <button v-for="u in filteredMembers.slice(0, 60)" :key="u.id" type="button" class="flex items-center gap-2 w-full px-3 py-2 hover:bg-gray-50 text-left" @mousedown.prevent="toggleMember(u)">
                    <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-gray-700" :checked="(form.member_ids || []).includes(u.id)" />
                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-200 text-[9px] font-bold text-gray-600">{{ initials(u.name) }}</span>
                    <div class="min-w-0">
                      <p class="text-sm text-gray-900 truncate">{{ u.name }}</p>
                      <p class="text-xs text-gray-500 truncate">{{ u.email }}</p>
                    </div>
                  </button>
                  <p v-if="!filteredMembers.length" class="px-3 py-2 text-xs text-gray-500">No users found.</p>
                  <div class="sticky bottom-0 flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2 border-t border-gray-200 bg-white px-3 py-2">
                    <button type="button" class="w-full sm:w-auto rounded border border-gray-300 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50" @mousedown.prevent="closeMemberDropdown">Close</button>
                    <button type="button" class="w-full sm:w-auto rounded bg-brand-primary px-3 py-2 text-xs text-white hover:bg-brand-primary-hover" @mousedown.prevent="doneMemberDropdown">Done</button>
                  </div>
                </div>
              </div>
              <p v-if="fieldError('member_ids')" class="mt-1 text-xs text-red-600">{{ fieldError('member_ids') }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Section: Settings -->
      <div class="border border-black rounded mb-6">
        <div class="border-b border-black bg-gray-50 px-5 py-3">
          <h2 class="text-sm font-semibold text-gray-900">Settings</h2>
        </div>
        <div class="p-5">
          <div class="flex items-center gap-4">
            <label class="text-sm font-medium text-gray-700">Status</label>
            <button
              type="button"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
              :class="form.status === 'active' ? 'bg-brand-primary' : 'bg-gray-300'"
              @click="form.status = form.status === 'active' ? 'inactive' : 'active'"
            >
              <span
                class="inline-block h-4 w-4 rounded-full bg-white transition-transform"
                :class="form.status === 'active' ? 'translate-x-6' : 'translate-x-1'"
              />
            </button>
            <span class="text-sm" :class="form.status === 'active' ? 'text-brand-primary-hover font-medium' : 'text-gray-500'">{{ form.status === 'active' ? 'Active' : 'Inactive' }}</span>
          </div>
        </div>
      </div>

      <!-- Footer actions -->
      <div class="flex items-center justify-end gap-3 pt-2">
        <router-link to="/teams" class="rounded border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</router-link>
        <button type="submit" :disabled="loading" class="rounded bg-brand-primary px-6 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-50 shadow-sm">
          {{ loading ? 'Creating…' : 'Create Team' }}
        </button>
      </div>
    </form>
  </div>
</template>
