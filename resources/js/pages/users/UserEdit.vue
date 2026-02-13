<script setup>
/**
 * User edit page: progressive loading with prime + extras (parallel fetch).
 * Shell (title, breadcrumbs, actions) renders immediately; form area shows skeleton until data is ready.
 */
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import usersApi from '@/services/usersApi'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import UserEditSectionSkeleton from '@/components/skeletons/UserEditSectionSkeleton.vue'
import { useUserEditData } from '@/composables/useUserEditData'
import { toDdMmYyyy, fromDdMmYyyy, toDdMonYyyyLower, fromDdMonYyyyLower } from '@/lib/dateFormat'

const DEPARTMENTS = [
  { value: 'sales', label: 'Sales' },
  { value: 'backoffice', label: 'Back Office' },
  { value: 'field', label: 'Field' },
  { value: 'csr', label: 'CSR' },
  { value: 'admin', label: 'Admin' },
  { value: 'it', label: 'IT' },
]

const STATUS_OPTIONS = [
  { value: 'approved', label: 'Active' },
  { value: 'rejected', label: 'Inactive' },
  { value: 'pending', label: 'Pending Approval' },
]

const ROLE_DESCRIPTIONS = {
  superadmin: 'Full system access with all administrative privileges. Can manage users, roles, and system configurations.',
  manager: 'Manages sales team, lead submissions, and client relationships. Can view reports and assign leads.',
  team_leader: 'Leads a team of sales representatives. Can manage team submissions and view team performance.',
  sales_agent: 'Conducts field visits and submits field operation reports. Mobile-first role for on-ground activities.',
  'back office executive': 'Processes and verifies submissions from the back office queue. Handles documentation and compliance.',
  'field agent': 'Conducts field visits and submits field operation reports. Mobile-first role for on-ground activities.',
  'field operations head': 'Oversees all field operations and agents. Reviews field submissions and manages field team performance.',
  'customer support representative': 'Handles customer inquiries, complaints, and support tickets. First point of contact for customers.',
  'support manager': 'Manages customer support team and escalated issues. Oversees support operations and quality.',
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const form = ref({
  name: '',
  email: '',
  phone: '',
  country: '',
  cnic_number: '',
  additional_notes: '',
  password: '',
  password_confirmation: '',
  force_password_reset: false,
  roles: [],
  manager_id: '',
  team_leader_id: '',
  department: '',
  extension: '',
  joining_date: '',
  terminate_date: '',
  status: '',
})
const joiningDateValue = ref('')
const roles = ref([])
const managers = ref([])
const teamLeaders = ref([])
const teamLeaderRoleId = ref(null)
const salesAgentRoleId = ref(null)
const managerLabel = ref('Manager')
const teamLeaderLabel = ref('Team Leader')
const user = ref(null)
const formPopulated = ref(false)
const saving = ref(false)

const {
  user: composableUser,
  extras: composableExtras,
  countries: composableCountries,
  extensionOptions: composableExtensionOptions,
  loadingPrime,
  loadingExtras,
  errorPrime,
  errorExtras,
  retry,
} = useUserEditData({ useCache: true })
const error = ref('')
const showPassword = ref(false)
const rolesDropdownOpen = ref(false)
const rolesDropdownRef = ref(null)

const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) ? r.some((x) => (typeof x === 'string' ? x === 'superadmin' : x?.name === 'superadmin')) : false
})

/** Only super admin or user with users.edit permission can access edit page. */
const canEdit = computed(() => {
  if (isSuperAdmin.value) return true
  const perms = auth.user?.permissions ?? []
  return Array.isArray(perms) && perms.includes('users.edit')
})

const showManagerDropdown = computed(() => {
  const ids = form.value.roles
  const tl = teamLeaderRoleId.value
  const sa = salesAgentRoleId.value
  return (tl != null && ids.includes(tl)) || (sa != null && ids.includes(sa))
})

const showTeamLeaderDropdown = computed(() => {
  if (salesAgentRoleId.value == null) return false
  return form.value.roles.includes(salesAgentRoleId.value)
})

const filteredTeamLeaders = computed(() => {
  const mid = form.value.manager_id
  const list = teamLeadersFiltered.value
  if (!mid) return list
  return list.filter((t) => String(t.manager_id) === String(mid))
})

/** Roles shown in Role Assignment: no superadmin, deduplicated by name so e.g. "Back Office" appears once. */
const assignableRoles = computed(() => {
  const list = (roles.value ?? []).filter((r) => (r?.name ?? '').toLowerCase() !== 'superadmin')
  const seen = new Map()
  for (const r of list) {
    if (!r?.id) continue
    const nameKey = (r.name ?? '').toLowerCase().replace(/\s+/g, '_')
    if (seen.has(nameKey)) continue
    seen.set(nameKey, r)
  }
  return Array.from(seen.values())
})

const roleDescription = (role) => ROLE_DESCRIPTIONS[role?.name?.toLowerCase()] || ''

const selectedRoleIdsSet = computed(() => new Set((form.value.roles || []).map((id) => Number(id))))

const selectedRolesLabel = computed(() => {
  const idSet = selectedRoleIdsSet.value
  if (idSet.size === 0) return 'Select roles...'
  return assignableRoles.value
    .filter((r) => idSet.has(Number(r.id)))
    .map((r) => formatRoleForDisplay(r.name))
    .join(', ')
})

watch(
  () => form.value.manager_id,
  () => {
    if (showTeamLeaderDropdown.value) form.value.team_leader_id = ''
  }
)

watch(
  () => form.value.roles,
  (ids) => {
    const tl = teamLeaderRoleId.value
    const sa = salesAgentRoleId.value
    if ((tl == null || !ids.includes(tl)) && (sa == null || !ids.includes(sa))) {
      form.value.manager_id = ''
      form.value.team_leader_id = ''
    }
    if (sa == null || !ids.includes(sa)) form.value.team_leader_id = ''
  }
)

watch(
  () => form.value.team_leader_id,
  (id) => {
    if (!id) return
    const tl = teamLeaders.value.find((u) => String(u.id) === String(id))
    if (tl?.manager_id) form.value.manager_id = String(tl.manager_id)
  }
)

// Sync composable user ref to local user for display
watch(composableUser, (v) => { user.value = v }, { immediate: true })

// Populate form when prime + extras are ready (one-time per load)
watch(
  [composableUser, composableExtras],
  ([u, ext]) => {
    if (!u || !ext || formPopulated.value) return
    const d = ext
    roles.value = d.roles ?? []
    const allRoles = d.roles ?? []
    const assignableList = (() => {
      const list = allRoles.filter((r) => (r?.name ?? '').toLowerCase() !== 'superadmin')
      const seen = new Map()
      for (const r of list) {
        if (!r?.id) continue
        const nameKey = (r.name ?? '').toLowerCase().replace(/\s+/g, '_')
        if (seen.has(nameKey)) continue
        seen.set(nameKey, r)
      }
      return Array.from(seen.values())
    })()
    const rawUserRoles = Array.isArray(u.roles) ? u.roles : []
    const roleIdsFromApi = Array.isArray(u.role_ids) ? u.role_ids : []
    const userRoleIds = rawUserRoles.length > 0
      ? rawUserRoles.map((r) => {
          const roleId = r?.id ?? r?.role_id
          const byId = assignableList.find((x) => Number(x.id) === Number(roleId))
          if (byId) return Number(byId.id)
          const byName = assignableList.find((x) => String(x.name || '').toLowerCase() === String(r?.name || '').toLowerCase())
          return byName != null ? Number(byName.id) : null
        }).filter((id) => id != null)
      : roleIdsFromApi.map((id) => {
          const byId = assignableList.find((x) => Number(x.id) === Number(id))
          return byId ? Number(byId.id) : null
        }).filter((id) => id != null)
    const joiningRaw = u.joining_date ? String(u.joining_date).substring(0, 10) : (u.created_at ? String(u.created_at).substring(0, 10) : '')
    joiningDateValue.value = joiningRaw
    // Set extras first so the form.roles watch has correct teamLeaderRoleId/salesAgentRoleId and does not clear team_leader_id
    managers.value = d.managers ?? []
    teamLeaders.value = (d.team_leaders ?? []).map((t) => ({ ...t, manager_id: t.manager_id ?? null }))
    teamLeaderRoleId.value = d.team_leader_role_id ?? null
    salesAgentRoleId.value = d.sales_agent_role_id ?? null
    managerLabel.value = d.manager_label ?? 'Manager'
    teamLeaderLabel.value = d.team_leader_label ?? 'Team Leader'
    form.value = {
      name: u.name ?? '',
      email: u.email ?? '',
      phone: u.phone ?? '',
      country: u.country ?? '',
      cnic_number: u.cnic_number ?? '',
      additional_notes: u.additional_notes ?? '',
      password: '',
      password_confirmation: '',
      force_password_reset: false,
      roles: userRoleIds,
      manager_id: u.manager_id ? String(u.manager_id) : '',
      team_leader_id: u.team_leader_id ? String(u.team_leader_id) : '',
      department: (() => { const dept = (u.department ?? '').toLowerCase().replace(/\s+/g, ''); return DEPARTMENTS.some(x => x.value === dept) ? dept : (u.department ?? ''); })(),
      extension: u.extension ?? '',
      joining_date: joiningRaw,
      terminate_date: u.terminate_date ? String(u.terminate_date).substring(0, 10) : '',
      status: u.status ?? 'pending',
    }
    formPopulated.value = true
  },
  { immediate: true }
)

// Reset formPopulated when route id changes so we can populate again
watch(() => route.params?.id, () => { formPopulated.value = false })

onMounted(() => {
  if (!canEdit.value) {
    router.push(isEmployeeRoute.value ? '/employees' : '/users')
  }
})

const statusLabel = (status) => {
  if (status === 'approved') return 'Active'
  if (status === 'rejected') return 'Inactive'
  return 'Pending Approval'
}
const displayStatus = computed(() => statusLabel(user.value?.status))

const isEmployeeRoute = computed(() => (typeof route.path === 'string' && route.path.startsWith('/employees')))

const employeeIdDisplay = computed(() => user.value?.employee_number ?? user.value?.id ?? '')

const joiningDateDisplay = computed(() => {
  const v = joiningDateValue.value
  return v ? toDdMmYyyy(v) : ''
})

const terminateDateDisplay = computed({
  get: () => (form.value.terminate_date ? toDdMonYyyyLower(form.value.terminate_date) : ''),
  set: (val) => {
    form.value.terminate_date = fromDdMonYyyyLower(val) || (val === '' ? '' : form.value.terminate_date)
  },
})

/** Managers excluding the user being edited (backend already excludes; this is a safeguard). */
const managersFiltered = computed(() => {
  const id = route.params.id
  return (managers.value ?? []).filter((m) => String(m.id) !== String(id))
})

/** Team leaders excluding the user being edited. */
const teamLeadersFiltered = computed(() => {
  const id = route.params.id
  return (teamLeaders.value ?? []).filter((t) => String(t.id) !== String(id))
})
const formatRoleForDisplay = (name) => {
  if (!name || typeof name !== 'string') return ''
  return name
    .split('_')
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
    .join(' ')
}
const toggleRole = (roleId) => {
  const ids = form.value.roles
  const idx = ids.indexOf(roleId)
  if (idx >= 0) ids.splice(idx, 1)
  else ids.push(roleId)
}

const save = async (closeAfter = false) => {
  error.value = ''
  saving.value = true
  try {
    const payload = { ...form.value }
    if (!payload.password) {
      delete payload.password
      delete payload.password_confirmation
    }
    delete payload.force_password_reset
    if (payload.manager_id) payload.manager_id = parseInt(payload.manager_id, 10)
    else payload.manager_id = null
    if (payload.team_leader_id) payload.team_leader_id = parseInt(payload.team_leader_id, 10)
    else payload.team_leader_id = null
    payload.joining_date = joiningDateValue.value || null
    payload.terminate_date = (payload.terminate_date && String(payload.terminate_date).trim()) ? payload.terminate_date : null
    await usersApi.update(route.params.id, payload)
    if (closeAfter) {
      router.push(isEmployeeRoute.value ? { path: '/employees', query: { updated: form.value.name } } : { path: '/users', query: { updated: form.value.name } })
    }
  } catch (e) {
    const msg = e?.response?.data?.message
    const errs = e?.response?.data?.errors
    error.value = msg || (errs ? Object.values(errs).flat().join(' ') : 'Failed to update user.')
  } finally {
    saving.value = false
  }
}

function onTerminateDatePickerChange(e) {
  const val = e.target?.value
  if (val) form.value.terminate_date = val
}

const cancel = () => router.push(isEmployeeRoute.value ? '/employees' : `/users/${route.params.id}`)

function closeRolesDropdownOnOutside(e) {
  if (rolesDropdownRef.value && !rolesDropdownRef.value.contains(e.target)) {
    rolesDropdownOpen.value = false
  }
}
onMounted(() => {
  document.addEventListener('click', closeRolesDropdownOnOutside)
})
onUnmounted(() => {
  document.removeEventListener('click', closeRolesDropdownOnOutside)
})
</script>

<template>
  <div class="space-y-6">
    <!-- Shell: always visible -->
    <div class="flex flex-wrap items-baseline gap-2">
      <Breadcrumbs />
      <h1 class="text-2xl font-bold text-gray-900 leading-tight">Edit Employee</h1>
    </div>
    <p class="text-sm text-gray-500">Update employee record in the system.</p>

    <div v-if="errorPrime || errorExtras" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 flex items-center justify-between">
      <span class="text-sm text-red-700">{{ errorPrime || errorExtras }}</span>
      <button type="button" class="text-red-600 hover:text-red-800 font-medium" @click="retry">Retry</button>
    </div>
    <div v-if="error" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Section-level loading: show skeletons until prime + extras are ready -->
    <template v-if="loadingPrime || loadingExtras">
      <UserEditSectionSkeleton :lines="6" />
      <UserEditSectionSkeleton :lines="4" />
      <UserEditSectionSkeleton :lines="6" />
    </template>

    <form v-else-if="formPopulated" @submit.prevent="save(true)" class="space-y-6">
      <!-- Basic Information -->
      <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
          <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
          <h2 class="text-base font-semibold text-gray-900">Basic Information</h2>
        </div>
        <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Employee Name <span class="text-red-500">*</span></label>
            <input v-model="form.name" type="text" required class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Enter full name" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Employee ID <span class="text-red-500">*</span></label>
            <input :value="employeeIdDisplay" type="text" readonly class="w-full rounded-lg border border-gray-300 bg-gray-50 text-gray-600 cursor-not-allowed" placeholder="ID" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-500">*</span></label>
            <select v-model="form.department" class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
              <option value="">Select department</option>
              <option v-for="d in DEPARTMENTS" :key="d.value" :value="d.value">{{ d.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Employee Status <span class="text-red-500">*</span></label>
            <select v-model="form.status" class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
              <option v-for="s in STATUS_OPTIONS" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Joining Date <span class="text-red-500">*</span></label>
            <input :value="joiningDateDisplay" type="text" readonly class="w-full rounded-lg border border-gray-300 bg-gray-50 text-gray-600 cursor-not-allowed" placeholder="DD-MM-YYYY" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Terminate Date</label>
            <div class="relative">
              <input
                v-model="terminateDateDisplay"
                type="text"
                class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 pr-10"
                placeholder="e.g. 11-feb-2026"
              />
              <span class="absolute right-0 top-0 flex h-full w-10 items-center justify-center">
                <input
                  type="date"
                  :value="form.terminate_date"
                  class="absolute inset-0 cursor-pointer opacity-0"
                  @change="onTerminateDatePickerChange"
                />
                <svg class="pointer-events-none w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </span>
            </div>
            <p class="mt-1 text-xs text-gray-500">Format: dd-mmm-yyyy (e.g. 11-feb-2026). Click calendar icon to pick a date.</p>
          </div>
        </div>
      </div>

      <!-- Role & Hierarchy (overflow-visible so roles dropdown panel is not clipped) -->
      <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-visible">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
          <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          <h2 class="text-base font-semibold text-gray-900">Role & Hierarchy</h2>
        </div>
        <div class="px-6 py-5">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div ref="rolesDropdownRef" class="relative">
              <label class="block text-sm font-medium text-gray-700 mb-1">Role(s) <span class="text-red-500">*</span></label>
              <button
                type="button"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-left text-sm shadow-sm focus:ring-blue-500 focus:border-blue-500 flex items-center justify-between gap-2"
                @click.stop="rolesDropdownOpen = !rolesDropdownOpen"
              >
                <span class="text-gray-700 truncate min-w-0">
                  {{ selectedRolesLabel }}
                </span>
                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <div
                v-if="rolesDropdownOpen"
                class="absolute z-50 mt-1 w-full min-w-[12rem] rounded-lg border border-gray-200 bg-white py-1 shadow-lg max-h-60 overflow-auto"
              >
                <label
                  v-for="r in assignableRoles"
                  :key="r.id"
                  class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer"
                >
                  <input
                    type="checkbox"
                    :checked="selectedRoleIdsSet.has(Number(r.id))"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    @change="toggleRole(r.id)"
                  />
                  <span class="text-sm text-gray-700">{{ formatRoleForDisplay(r.name) }}</span>
                </label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Manager Name</label>
              <select v-model="form.manager_id" class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select manager</option>
                <option v-for="m in managersFiltered" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Team Leader Name</label>
              <select v-model="form.team_leader_id" class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select team leader</option>
                <option v-for="t in filteredTeamLeaders" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Extension</label>
              <select v-model="form.extension" class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select extension</option>
                <option v-for="opt in composableExtensionOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Contact Details -->
      <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
          <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <h2 class="text-base font-semibold text-gray-900">Contact Details</h2>
        </div>
        <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email ID</label>
            <input v-model="form.email" type="email" required class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="employee@company.com" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
            <input v-model="form.phone" type="text" class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="+971-50-123-4567" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
            <select v-model="form.country" class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
              <option value="">Select country</option>
              <option v-for="c in composableCountries" :key="c.id" :value="c.code || c.name">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">CNIC Number</label>
            <input v-model="form.cnic_number" type="text" class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="---------" />
          </div>
        </div>
      </div>

      <!-- System Access -->
      <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
          <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
          </svg>
          <h2 class="text-base font-semibold text-gray-900">System Access</h2>
        </div>
        <div class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">System Login Email <span class="text-red-500">*</span></label>
            <input v-model="form.email" type="email" required class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="login@company.com" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">System Password</label>
            <div class="relative">
              <input v-model="form.password" :type="showPassword ? 'text' : 'password'" class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 pr-10" placeholder="Enter secure password (leave blank to keep current)" />
              <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
              </button>
            </div>
            <p class="mt-1 text-xs text-gray-500">Leave blank to keep current password. Min 8 characters if changing.</p>
          </div>
          <div v-if="form.password">
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input v-model="form.password_confirmation" type="password" class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Confirm new password" />
          </div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="form.force_password_reset" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
            <span class="text-sm text-gray-700">Force password reset on first login</span>
          </label>
        </div>
      </div>

      <!-- Internal Comment -->
      <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
          <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
          </svg>
          <h2 class="text-base font-semibold text-gray-900">Internal Comment</h2>
        </div>
        <div class="px-6 py-5">
          <label class="block text-sm font-medium text-gray-700 mb-1">Internal Comment</label>
          <textarea v-model="form.additional_notes" rows="4" class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Add any internal notes or comments about this employee..." />
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-gray-200">
        <button
          type="button"
          @click="cancel"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          Cancel
        </button>
        <button
          type="button"
          :disabled="saving"
          @click="save(false)"
          class="inline-flex items-center gap-2 rounded-lg border border-sky-400 bg-sky-50 px-5 py-2.5 text-sm font-medium text-sky-600 hover:bg-sky-100 disabled:opacity-50"
        >
          {{ saving ? 'Saving...' : 'Save & Close' }}
        </button>
        <button
          type="submit"
          :disabled="saving"
          class="inline-flex items-center gap-2 rounded-lg border border-green-600 bg-green-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
          </svg>
          {{ saving ? 'Updating...' : 'Update' }}
        </button>
      </div>
    </form>
  </div>
</template>
