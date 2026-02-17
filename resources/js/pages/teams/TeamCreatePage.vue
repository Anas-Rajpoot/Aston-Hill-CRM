<script setup>
/**
 * Create Team – enhanced form with real-time validation, searchable dropdowns, toggle switch.
 */
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import teamsApi from '@/services/teamsApi'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
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

const nameError = computed(() => {
  if (!form.value.name) return null
  if (form.value.name.length > 255) return 'Name must be under 255 characters.'
  return errors.value.name?.[0] ?? null
})

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
    setTimeout(() => router.push('/teams?created=1'), 1200)
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
  <div class="space-y-6">
    <router-link to="/teams" class="inline-flex items-center gap-1 text-sm text-green-600 hover:text-green-700 font-medium">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
      Back to Teams
    </router-link>

    <div>
      <h1 class="text-2xl font-bold text-gray-900">Create New Team</h1>
      <p class="mt-1 text-sm text-gray-500">Set up a new team with leadership and department assignment.</p>
    </div>
    <Breadcrumbs />

    <form @submit.prevent="save" class="rounded-xl border border-gray-200 bg-white shadow-sm max-w-3xl">
      <div class="p-6 space-y-6">
        <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">{{ error }}</div>
        <div v-if="successMessage" class="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700">{{ successMessage }}</div>

        <!-- Section: Team Details -->
        <section>
          <h2 class="text-sm font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            Team Details
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Team Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" required class="w-full rounded-lg border-gray-300 focus:border-green-500 focus:ring-green-500" :class="{ 'border-red-300': nameError }" placeholder="e.g. Sales Team Alpha" />
              <p v-if="nameError" class="mt-1 text-xs text-red-600">{{ nameError }}</p>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="form.description" rows="3" class="w-full rounded-lg border-gray-300 focus:border-green-500 focus:ring-green-500" placeholder="Brief team description…" />
              <p class="mt-1 text-xs text-gray-400">{{ (form.description || '').length }}/1000</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
              <input v-model="form.department" type="text" class="w-full rounded-lg border-gray-300 focus:border-green-500 focus:ring-green-500" placeholder="e.g. Sales, Support, IT" />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Max Members</label>
              <input v-model="form.max_members" type="number" min="1" max="100" class="w-full rounded-lg border-gray-300 focus:border-green-500 focus:ring-green-500" placeholder="Leave blank for unlimited" />
            </div>
          </div>
        </section>

        <!-- Section: Leadership -->
        <section>
          <h2 class="text-sm font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
            Leadership
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Manager (searchable) -->
            <div class="relative">
              <label class="block text-sm font-medium text-gray-700 mb-1">Manager</label>
              <div v-if="selectedManager" class="flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-green-100 text-[10px] font-bold text-green-700">{{ initials(selectedManager.name) }}</span>
                <span class="text-sm text-gray-900 flex-1">{{ selectedManager.name }}</span>
                <button type="button" class="text-gray-400 hover:text-gray-600" @click="clearManager">&times;</button>
              </div>
              <div v-else>
                <input v-model="managerSearch" type="text" class="w-full rounded-lg border-gray-300 focus:border-green-500 focus:ring-green-500" placeholder="Search manager…" @focus="managerDropdownOpen = true" @blur="setTimeout(() => managerDropdownOpen = false, 200)" />
                <div v-if="managerDropdownOpen && filteredManagers.length" class="absolute z-20 top-full left-0 right-0 mt-1 max-h-48 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                  <button v-for="u in filteredManagers.slice(0, 20)" :key="u.id" type="button" class="flex items-center gap-2 w-full px-3 py-2 hover:bg-gray-50 text-left" @mousedown.prevent="selectManager(u)">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-[9px] font-bold text-gray-600">{{ initials(u.name) }}</span>
                    <div class="min-w-0">
                      <p class="text-sm text-gray-900 truncate">{{ u.name }}</p>
                      <p class="text-xs text-gray-500 truncate">{{ u.email }}</p>
                    </div>
                  </button>
                </div>
              </div>
            </div>

            <!-- Team Leader (searchable) -->
            <div class="relative">
              <label class="block text-sm font-medium text-gray-700 mb-1">Team Leader</label>
              <div v-if="selectedLeader" class="flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-blue-700">{{ initials(selectedLeader.name) }}</span>
                <span class="text-sm text-gray-900 flex-1">{{ selectedLeader.name }}</span>
                <button type="button" class="text-gray-400 hover:text-gray-600" @click="clearLeader">&times;</button>
              </div>
              <div v-else>
                <input v-model="leaderSearch" type="text" class="w-full rounded-lg border-gray-300 focus:border-green-500 focus:ring-green-500" placeholder="Search team leader…" @focus="leaderDropdownOpen = true" @blur="setTimeout(() => leaderDropdownOpen = false, 200)" />
                <div v-if="leaderDropdownOpen && filteredLeaders.length" class="absolute z-20 top-full left-0 right-0 mt-1 max-h-48 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                  <button v-for="u in filteredLeaders.slice(0, 20)" :key="u.id" type="button" class="flex items-center gap-2 w-full px-3 py-2 hover:bg-gray-50 text-left" @mousedown.prevent="selectLeader(u)">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-[9px] font-bold text-gray-600">{{ initials(u.name) }}</span>
                    <div class="min-w-0">
                      <p class="text-sm text-gray-900 truncate">{{ u.name }}</p>
                      <p class="text-xs text-gray-500 truncate">{{ u.email }}</p>
                    </div>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Section: Settings -->
        <section>
          <h2 class="text-sm font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            Settings
          </h2>
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
        </section>
      </div>

      <!-- Footer -->
      <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 flex items-center justify-end gap-3 rounded-b-xl">
        <router-link to="/teams" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Cancel</router-link>
        <button type="submit" :disabled="loading" class="px-5 py-2.5 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700 disabled:opacity-50 shadow-sm">
          {{ loading ? 'Creating…' : 'Create Team' }}
        </button>
      </div>
    </form>
  </div>
</template>
