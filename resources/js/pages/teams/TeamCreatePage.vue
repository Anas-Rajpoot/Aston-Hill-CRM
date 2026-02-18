<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import teamsApi from '@/services/teamsApi'
import api from '@/lib/axios'

const router = useRouter()

const form = ref({
  name: '',
  description: '',
  manager_id: '',
  team_leader_id: '',
  department: '',
  status: 'active',
  max_members: '',
})

const allUsers = ref([])
const managerSearch = ref('')
const leaderSearch = ref('')
const managerDropdownOpen = ref(false)
const leaderDropdownOpen = ref(false)
const loading = ref(false)
const error = ref('')
const errors = ref({})
const successMessage = ref('')

const filteredManagers = computed(() => {
  const term = managerSearch.value.toLowerCase()
  if (!term) return allUsers.value
  return allUsers.value.filter((u) => u.name.toLowerCase().includes(term) || u.email?.toLowerCase().includes(term))
})

const filteredLeaders = computed(() => {
  const term = leaderSearch.value.toLowerCase()
  if (!term) return allUsers.value
  return allUsers.value.filter((u) => u.name.toLowerCase().includes(term) || u.email?.toLowerCase().includes(term))
})

const selectedManager = computed(() => allUsers.value.find((u) => u.id === form.value.manager_id))
const selectedLeader = computed(() => allUsers.value.find((u) => u.id === form.value.team_leader_id))

function initials(name) {
  if (!name) return '?'
  return name.split(' ').map((w) => w[0]).join('').toUpperCase().slice(0, 2)
}

function selectManager(user) {
  form.value.manager_id = user.id
  managerDropdownOpen.value = false
  managerSearch.value = ''
}

function selectLeader(user) {
  form.value.team_leader_id = user.id
  leaderDropdownOpen.value = false
  leaderSearch.value = ''
}

function clearManager() { form.value.manager_id = '' }
function clearLeader() { form.value.team_leader_id = '' }

onMounted(async () => {
  try {
    const { data } = await api.get('/users', { params: { per_page: 200, status: 'approved' } })
    allUsers.value = (data.users ?? []).map((u) => ({ id: u.id, name: u.name, email: u.email }))
  } catch { /* silent */ }
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

  loading.value = true
  try {
    const payload = { ...form.value }
    if (!payload.manager_id) payload.manager_id = null
    if (!payload.team_leader_id) payload.team_leader_id = null
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
        <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <span class="text-sm font-medium text-gray-700">Creating team…</span>
      </div>
    </div>

    <!-- Back link -->
    <router-link to="/teams" class="inline-flex items-center gap-1 text-sm text-green-600 hover:text-green-700 font-medium mb-4">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
      Back to Teams
    </router-link>

    <!-- Page header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Create New Team</h1>
      <p class="mt-1 text-sm text-gray-500">Set up a new team with leadership and department assignment.</p>
    </div>

    <!-- Alerts -->
    <div v-if="error" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ error }}</div>
    <div v-if="successMessage" class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ successMessage }}</div>

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
              <input v-model="form.name" type="text" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="{ 'border-red-400': fieldError('name') }" placeholder="e.g. Sales Team Alpha" />
              <p v-if="fieldError('name')" class="mt-1 text-xs text-red-600">{{ fieldError('name') }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
              <input v-model="form.department" type="text" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="{ 'border-red-400': fieldError('department') }" placeholder="e.g. Sales, Support, IT" />
              <p v-if="fieldError('department')" class="mt-1 text-xs text-red-600">{{ fieldError('department') }}</p>
            </div>

            <div class="lg:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="form.description" rows="3" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" placeholder="Brief team description…" />
              <p class="mt-1 text-xs text-gray-400">{{ (form.description || '').length }}/1000</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Max Members</label>
              <input v-model="form.max_members" type="number" min="1" max="100" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" placeholder="Unlimited if blank" />
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
              <label class="block text-sm font-medium text-gray-700 mb-1">Manager</label>
              <div v-if="selectedManager" class="flex items-center gap-2 rounded border border-gray-300 px-3 py-2 text-sm">
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-green-100 text-[10px] font-bold text-green-700">{{ initials(selectedManager.name) }}</span>
                <span class="text-gray-900 flex-1 truncate">{{ selectedManager.name }}</span>
                <button type="button" class="text-gray-400 hover:text-gray-600 text-lg leading-none" @click="clearManager">&times;</button>
              </div>
              <div v-else>
                <input v-model="managerSearch" type="text" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" placeholder="Search manager…" @focus="managerDropdownOpen = true" @blur="setTimeout(() => managerDropdownOpen = false, 200)" />
                <div v-if="managerDropdownOpen && filteredManagers.length" class="absolute z-20 top-full left-0 right-0 mt-1 max-h-52 overflow-y-auto rounded border border-gray-200 bg-white shadow-lg">
                  <button v-for="u in filteredManagers.slice(0, 30)" :key="u.id" type="button" class="flex items-center gap-2 w-full px-3 py-2 hover:bg-gray-50 text-left" @mousedown.prevent="selectManager(u)">
                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-200 text-[9px] font-bold text-gray-600">{{ initials(u.name) }}</span>
                    <div class="min-w-0">
                      <p class="text-sm text-gray-900 truncate">{{ u.name }}</p>
                      <p class="text-xs text-gray-500 truncate">{{ u.email }}</p>
                    </div>
                  </button>
                </div>
              </div>
              <p v-if="fieldError('manager_id')" class="mt-1 text-xs text-red-600">{{ fieldError('manager_id') }}</p>
            </div>

            <!-- Team Leader -->
            <div class="relative">
              <label class="block text-sm font-medium text-gray-700 mb-1">Team Leader</label>
              <div v-if="selectedLeader" class="flex items-center gap-2 rounded border border-gray-300 px-3 py-2 text-sm">
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-blue-700">{{ initials(selectedLeader.name) }}</span>
                <span class="text-gray-900 flex-1 truncate">{{ selectedLeader.name }}</span>
                <button type="button" class="text-gray-400 hover:text-gray-600 text-lg leading-none" @click="clearLeader">&times;</button>
              </div>
              <div v-else>
                <input v-model="leaderSearch" type="text" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" placeholder="Search team leader…" @focus="leaderDropdownOpen = true" @blur="setTimeout(() => leaderDropdownOpen = false, 200)" />
                <div v-if="leaderDropdownOpen && filteredLeaders.length" class="absolute z-20 top-full left-0 right-0 mt-1 max-h-52 overflow-y-auto rounded border border-gray-200 bg-white shadow-lg">
                  <button v-for="u in filteredLeaders.slice(0, 30)" :key="u.id" type="button" class="flex items-center gap-2 w-full px-3 py-2 hover:bg-gray-50 text-left" @mousedown.prevent="selectLeader(u)">
                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-200 text-[9px] font-bold text-gray-600">{{ initials(u.name) }}</span>
                    <div class="min-w-0">
                      <p class="text-sm text-gray-900 truncate">{{ u.name }}</p>
                      <p class="text-xs text-gray-500 truncate">{{ u.email }}</p>
                    </div>
                  </button>
                </div>
              </div>
              <p v-if="fieldError('team_leader_id')" class="mt-1 text-xs text-red-600">{{ fieldError('team_leader_id') }}</p>
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
              :class="form.status === 'active' ? 'bg-green-600' : 'bg-gray-300'"
              @click="form.status = form.status === 'active' ? 'inactive' : 'active'"
            >
              <span
                class="inline-block h-4 w-4 rounded-full bg-white transition-transform"
                :class="form.status === 'active' ? 'translate-x-6' : 'translate-x-1'"
              />
            </button>
            <span class="text-sm" :class="form.status === 'active' ? 'text-green-700 font-medium' : 'text-gray-500'">{{ form.status === 'active' ? 'Active' : 'Inactive' }}</span>
          </div>
        </div>
      </div>

      <!-- Footer actions -->
      <div class="flex items-center justify-end gap-3 pt-2">
        <router-link to="/teams" class="rounded border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</router-link>
        <button type="submit" :disabled="loading" class="rounded bg-green-600 px-6 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 shadow-sm">
          {{ loading ? 'Creating…' : 'Create Team' }}
        </button>
      </div>
    </form>
  </div>
</template>
