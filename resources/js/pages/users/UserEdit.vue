<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import usersApi from '@/services/usersApi'
import api from '@/lib/axios'

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
})
const roles = ref([])
const countries = ref([])
const managers = ref([])
const teamLeaders = ref([])
const teamLeaderRoleId = ref(null)
const salesAgentRoleId = ref(null)
const managerLabel = ref('Manager')
const teamLeaderLabel = ref('Team Leader')
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const showPassword = ref(false)

const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) ? r.some((x) => (typeof x === 'string' ? x === 'superadmin' : x?.name === 'superadmin')) : false
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
  if (!mid) return teamLeaders.value
  return teamLeaders.value.filter((t) => String(t.manager_id) === String(mid))
})

const roleDescription = (role) => ROLE_DESCRIPTIONS[role?.name?.toLowerCase()] || ''

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

onMounted(async () => {
  if (!isSuperAdmin.value) {
    router.push('/users')
    return
  }
  try {
    const [userRes, countriesRes] = await Promise.all([
      usersApi.show(route.params.id),
      api.get('/countries').catch(() => ({ data: [] })),
    ])
    const d = userRes.data
    const user = d.user
    form.value = {
      name: user.name ?? '',
      email: user.email ?? '',
      phone: user.phone ?? '',
      country: user.country ?? '',
      cnic_number: user.cnic_number ?? '',
      additional_notes: user.additional_notes ?? '',
      password: '',
      password_confirmation: '',
      force_password_reset: false,
      roles: (user.roles ?? []).map((r) => r.id),
      manager_id: user.manager_id ? String(user.manager_id) : '',
      team_leader_id: user.team_leader_id ? String(user.team_leader_id) : '',
    }
    roles.value = d.roles ?? []
    managers.value = d.managers ?? []
    teamLeaders.value = (d.team_leaders ?? []).map((t) => ({ ...t, manager_id: t.manager_id ?? null }))
    teamLeaderRoleId.value = d.team_leader_role_id ?? null
    salesAgentRoleId.value = d.sales_agent_role_id ?? null
    managerLabel.value = d.manager_label ?? 'Manager'
    teamLeaderLabel.value = d.team_leader_label ?? 'Team Leader'
    countries.value = Array.isArray(countriesRes.data) ? countriesRes.data : countriesRes.data?.data ?? []
  } catch {
    router.push('/users')
  } finally {
    loading.value = false
  }
})

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
    await usersApi.update(route.params.id, payload)
    if (closeAfter) {
      router.push({ path: '/users', query: { updated: form.value.name } })
    }
  } catch (e) {
    const msg = e?.response?.data?.message
    const errs = e?.response?.data?.errors
    error.value = msg || (errs ? Object.values(errs).flat().join(' ') : 'Failed to update user.')
  } finally {
    saving.value = false
  }
}

const cancel = () => router.push(`/users/${route.params.id}`)
</script>

<template>
  <div class="space-y-6">
    <nav class="flex text-sm text-gray-500">
      <router-link to="/users" class="hover:text-gray-700">Users</router-link>
      <span class="mx-2">/</span>
      <span class="text-gray-900 font-medium">Edit User</span>
    </nav>

    <div v-if="loading" class="flex justify-center py-16">
      <svg class="animate-spin h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
    </div>

    <form v-else @submit.prevent="save(true)" class="space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Edit User</h1>
        <p class="mt-1 text-sm text-gray-500">Update user account and roles.</p>
      </div>

      <div v-if="error" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
        {{ error }}
      </div>

      <!-- Basic Information -->
      <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
          <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
          <h2 class="text-base font-semibold text-gray-900">Basic Information</h2>
        </div>
        <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
            <input v-model="form.name" type="text" required class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Enter full name" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number <span class="text-red-500">*</span></label>
            <input v-model="form.phone" type="text" required class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="+1 234 567 8900" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
            <input v-model="form.email" type="email" required class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="user@example.com" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Country <span class="text-red-500">*</span></label>
            <select v-model="form.country" required class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
              <option value="">Select country</option>
              <option v-for="c in countries" :key="c.id" :value="c.code || c.name">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">CNIC Number</label>
            <input v-model="form.cnic_number" type="text" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="12345-1234567-1" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
            <textarea v-model="form.additional_notes" rows="4" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Any additional information about this user..." />
          </div>
        </div>
      </div>

      <!-- Role Assignment -->
      <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
          <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          <h2 class="text-base font-semibold text-gray-900">Role Assignment</h2>
        </div>
        <div class="px-6 py-5">
          <label class="block text-sm font-medium text-gray-700 mb-3">Assigned Role(s) <span class="text-red-500">*</span></label>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <label
              v-for="r in roles"
              :key="r.id"
              class="flex items-start gap-3 p-4 rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer"
              :class="{ 'opacity-60': r.name === 'superadmin' }"
            >
              <input
                v-model="form.roles"
                type="checkbox"
                :value="r.id"
                :disabled="r.name === 'superadmin' && !isSuperAdmin"
                class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
              />
              <div class="min-w-0 flex-1">
                <span class="font-medium text-gray-900 block">{{ r.name.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase()) }}</span>
                <p v-if="roleDescription(r)" class="mt-1 text-xs text-gray-500">{{ roleDescription(r) }}</p>
              </div>
            </label>
          </div>
          <p class="mt-4 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            Note: Multiple roles can be assigned. Super Admin role is locked and only assignable by Super Admin. Role changes apply immediately after save.
          </p>
          <div v-show="showTeamLeaderDropdown" class="mt-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Select {{ managerLabel }} <span class="text-red-500">*</span></label>
              <select v-model="form.manager_id" :required="showManagerDropdown" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Select {{ managerLabel }}</option>
                <option v-for="m in managers" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
              <p class="mt-1 text-xs text-gray-500">Select manager first to filter team leaders</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Select {{ teamLeaderLabel }} <span class="text-red-500">*</span></label>
              <select v-model="form.team_leader_id" :required="showTeamLeaderDropdown" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Select {{ teamLeaderLabel }}</option>
                <option v-for="t in filteredTeamLeaders" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
              <p class="mt-1 text-xs text-gray-500">Manager is auto-filled when team leader is selected</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Login Credentials -->
      <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
          <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
          </svg>
          <h2 class="text-base font-semibold text-gray-900">Login Credentials</h2>
        </div>
        <div class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Temporary Password</label>
            <div class="relative">
              <input v-model="form.password" :type="showPassword ? 'text' : 'password'" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 pr-10" placeholder="Enter temporary password (leave blank to keep current)" />
              <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
              </button>
            </div>
            <p class="mt-1 text-xs text-gray-500">Password must be at least 8 characters long.</p>
          </div>
          <div v-if="form.password">
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input v-model="form.password_confirmation" type="password" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Confirm new password" />
          </div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="form.force_password_reset" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
            <span class="text-sm text-gray-700">Force password reset on first login</span>
          </label>
        </div>
      </div>

      <!-- Access Notes -->
      <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
          <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <h2 class="text-base font-semibold text-gray-900">Access Notes</h2>
        </div>
        <div class="px-6 py-5">
          <ul class="space-y-2 text-sm text-gray-600">
            <li class="flex items-start gap-2">
              <svg class="w-5 h-5 text-green-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
              User access and permissions depend on assigned roles and system restrictions.
            </li>
            <li class="flex items-start gap-2">
              <svg class="w-5 h-5 text-green-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
              All changes are automatically logged in Audit Logs for accountability.
            </li>
            <li class="flex items-start gap-2">
              <svg class="w-5 h-5 text-green-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
              Role changes take effect immediately upon saving.
            </li>
            <li class="flex items-start gap-2">
              <svg class="w-5 h-5 text-green-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
              Users with Inactive status cannot log in to the system.
            </li>
          </ul>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-gray-200">
        <button
          type="button"
          @click="cancel"
          class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-900 hover:bg-gray-50"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          Cancel
        </button>
        <div class="flex items-center gap-3">
          <button
            type="button"
            :disabled="saving"
            @click="save(false)"
            class="inline-flex items-center gap-2 rounded-xl border border-sky-400 bg-white px-5 py-2.5 text-sm font-medium text-sky-500 hover:bg-sky-50 disabled:opacity-50"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            {{ saving ? 'Saving...' : 'Save User' }}
          </button>
          <button
            type="submit"
            :disabled="saving"
            class="inline-flex items-center gap-2 rounded-xl bg-lime-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-lime-600 disabled:opacity-50"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            {{ saving ? 'Saving...' : 'Save & Close' }}
          </button>
        </div>
      </div>
    </form>
  </div>
</template>
