<script setup>
/**
 * Edit Team – enhanced form with searchable dropdowns, avatars, toggle switch, real-time validation.
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import teamsApi from '@/services/teamsApi'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import api from '@/lib/axios'

const route = useRoute()
const router = useRouter()

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
const pageLoading = ref(true)
const loading = ref(false)
const error = ref('')
const errors = ref({})
const successMessage = ref('')
const teamName = ref('')

const filteredManagers = computed(() => {
  const term = managerSearch.value.toLowerCase()
  const blocked = new Set([...(form.value.team_leader_ids || []), ...(form.value.member_ids || [])])
  const base = allUsers.value.filter((u) => !blocked.has(u.id))
  if (!term) return base
  return base.filter((u) => u.name.toLowerCase().includes(term) || u.email?.toLowerCase().includes(term))
})

const filteredLeaders = computed(() => {
  const term = leaderSearch.value.toLowerCase()
  const blocked = new Set([...(form.value.manager_ids || []), ...(form.value.member_ids || [])])
  const base = allUsers.value.filter((u) => !blocked.has(u.id))
  if (!term) return base
  return base.filter((u) => u.name.toLowerCase().includes(term) || u.email?.toLowerCase().includes(term))
})

const filteredMembers = computed(() => {
  const term = memberSearch.value.toLowerCase()
  const blocked = new Set([...(form.value.manager_ids || []), ...(form.value.team_leader_ids || [])])
  const base = allUsers.value.filter((u) => !blocked.has(u.id))
  if (!term) return base
  return base.filter((u) => u.name.toLowerCase().includes(term) || u.email?.toLowerCase().includes(term))
})

const selectedManagers = computed(() => allUsers.value.filter((u) => (form.value.manager_ids || []).includes(u.id)))
const selectedLeaders = computed(() => allUsers.value.filter((u) => (form.value.team_leader_ids || []).includes(u.id)))
const selectedMembers = computed(() => allUsers.value.filter((u) => (form.value.member_ids || []).includes(u.id)))

function initials(name) {
  if (!name) return '?'
  return name.split(' ').map((w) => w[0]).join('').toUpperCase().slice(0, 2)
}

function toggleManager(u) {
  const ids = new Set(form.value.manager_ids || [])
  if (ids.has(u.id)) ids.delete(u.id)
  else ids.add(u.id)
  form.value.manager_ids = [...ids]
  form.value.team_leader_ids = (form.value.team_leader_ids || []).filter((id) => id !== u.id)
  form.value.member_ids = (form.value.member_ids || []).filter((id) => id !== u.id)
}
function toggleLeader(u) {
  const ids = new Set(form.value.team_leader_ids || [])
  if (ids.has(u.id)) ids.delete(u.id)
  else ids.add(u.id)
  form.value.team_leader_ids = [...ids]
  form.value.manager_ids = (form.value.manager_ids || []).filter((id) => id !== u.id)
  form.value.member_ids = (form.value.member_ids || []).filter((id) => id !== u.id)
}
function toggleMember(u) {
  if ((form.value.manager_ids || []).includes(u.id) || (form.value.team_leader_ids || []).includes(u.id)) return
  const ids = new Set(form.value.member_ids || [])
  if (ids.has(u.id)) ids.delete(u.id)
  else ids.add(u.id)
  form.value.member_ids = [...ids]
}
function removeManager(id) { form.value.manager_ids = (form.value.manager_ids || []).filter((x) => x !== id) }
function removeLeader(id) { form.value.team_leader_ids = (form.value.team_leader_ids || []).filter((x) => x !== id) }
function removeMember(id) { form.value.member_ids = (form.value.member_ids || []).filter((x) => x !== id) }

onMounted(async () => {
  try {
    const [teamData, usersRes] = await Promise.all([
      teamsApi.show(route.params.id),
      api.get('/users', { params: { per_page: 200, status: 'approved' } }),
    ])
    const team = teamData.team
    teamName.value = team.name
    form.value = {
      name: team.name ?? '',
      description: team.description ?? '',
      manager_id: team.manager_id ?? '',
      team_leader_id: team.team_leader_id ?? '',
      manager_ids: (team.manager_ids ?? (team.manager_id ? [team.manager_id] : [])).map((id) => Number(id)).filter(Boolean),
      team_leader_ids: (team.team_leader_ids ?? (team.team_leader_id ? [team.team_leader_id] : [])).map((id) => Number(id)).filter(Boolean),
      member_ids: (team.member_ids ?? []).map((id) => Number(id)).filter(Boolean),
      department: team.department ?? '',
      status: team.status ?? 'active',
      max_members: team.max_members ?? '',
    }
    allUsers.value = (usersRes.data.users ?? []).map((u) => ({ id: u.id, name: u.name, email: u.email }))
  } catch {
    router.push('/teams')
  } finally {
    pageLoading.value = false
  }
})

const save = async () => {
  error.value = ''
  errors.value = {}
  successMessage.value = ''
  if (!form.value.name.trim()) { errors.value.name = ['Team name is required.']; return }
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
    await teamsApi.update(route.params.id, payload)
    successMessage.value = 'Team updated successfully.'
    setTimeout(() => router.push(`/teams/${route.params.id}`), 1200)
  } catch (e) {
    const msg = e?.response?.data?.message
    const errs = e?.response?.data?.errors
    errors.value = errs || {}
    error.value = msg || (errs ? Object.values(errs).flat().join(' ') : 'Failed to update team.')
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
      <h1 class="text-2xl font-bold text-gray-900">Edit Team{{ teamName ? `: ${teamName}` : '' }}</h1>
      <p class="mt-1 text-sm text-gray-500">Update team details and leadership assignments.</p>
    </div>
    <Breadcrumbs />

    <div v-if="pageLoading" class="flex justify-center py-16">
      <svg class="animate-spin h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
    </div>

    <form v-else @submit.prevent="save" class="rounded-xl border border-gray-200 bg-white shadow-sm max-w-3xl">
      <div class="p-6 space-y-6">
        <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">{{ error }}</div>
        <div v-if="successMessage" class="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700">{{ successMessage }}</div>

        <!-- Team Details -->
        <section>
          <h2 class="text-sm font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            Team Details
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Team Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" required class="w-full rounded-lg border-gray-300 focus:border-green-500 focus:ring-green-500" :class="{ 'border-red-300': errors.name }" />
              <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name[0] }}</p>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="form.description" rows="3" class="w-full rounded-lg border-gray-300 focus:border-green-500 focus:ring-green-500" />
              <p class="mt-1 text-xs text-gray-400">{{ (form.description || '').length }}/1000</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
              <input v-model="form.department" type="text" class="w-full rounded-lg border-gray-300 focus:border-green-500 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Max Members</label>
              <input v-model="form.max_members" type="number" min="1" max="100" class="w-full rounded-lg border-gray-300 focus:border-green-500 focus:ring-green-500" placeholder="Unlimited" />
            </div>
          </div>
        </section>

        <!-- Leadership -->
        <section>
          <h2 class="text-sm font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
            Leadership
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="relative">
              <label class="block text-sm font-medium text-gray-700 mb-1">Manager</label>
              <div class="rounded-lg border border-gray-300 px-3 py-2" @click="managerDropdownOpen = true">
                <div v-if="selectedManagers.length" class="mb-2 flex flex-wrap gap-1.5">
                  <span v-for="u in selectedManagers" :key="u.id" class="inline-flex items-center gap-1 rounded bg-green-100 px-2 py-1 text-xs text-green-800">
                    {{ u.name }}
                    <button type="button" class="text-green-700 hover:text-green-900" @click.stop="removeManager(u.id)">&times;</button>
                  </span>
                </div>
                <input v-model="managerSearch" type="text" class="w-full border-0 p-0 text-sm focus:ring-0" placeholder="Select team manager" @focus="managerDropdownOpen = true" @blur="setTimeout(() => managerDropdownOpen = false, 200)" />
                <div v-if="managerDropdownOpen && filteredManagers.length" class="absolute z-20 top-full left-0 right-0 mt-1 max-h-48 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                  <button v-for="u in filteredManagers.slice(0, 30)" :key="u.id" type="button" class="flex items-center gap-2 w-full px-3 py-2 hover:bg-gray-50 text-left" @mousedown.prevent="toggleManager(u)">
                    <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-green-600" :checked="(form.manager_ids || []).includes(u.id)" />
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-[9px] font-bold text-gray-600">{{ initials(u.name) }}</span>
                    <div class="min-w-0"><p class="text-sm text-gray-900 truncate">{{ u.name }}</p><p class="text-xs text-gray-500 truncate">{{ u.email }}</p></div>
                  </button>
                </div>
              </div>
              <p v-if="errors.manager_ids" class="mt-1 text-xs text-red-600">{{ errors.manager_ids[0] }}</p>
            </div>
            <div class="relative">
              <label class="block text-sm font-medium text-gray-700 mb-1">Team Leader</label>
              <div class="rounded-lg border border-gray-300 px-3 py-2" @click="leaderDropdownOpen = true">
                <div v-if="selectedLeaders.length" class="mb-2 flex flex-wrap gap-1.5">
                  <span v-for="u in selectedLeaders" :key="u.id" class="inline-flex items-center gap-1 rounded bg-blue-100 px-2 py-1 text-xs text-blue-800">
                    {{ u.name }}
                    <button type="button" class="text-blue-700 hover:text-blue-900" @click.stop="removeLeader(u.id)">&times;</button>
                  </span>
                </div>
                <input v-model="leaderSearch" type="text" class="w-full border-0 p-0 text-sm focus:ring-0" placeholder="Select team leader" @focus="leaderDropdownOpen = true" @blur="setTimeout(() => leaderDropdownOpen = false, 200)" />
                <div v-if="leaderDropdownOpen && filteredLeaders.length" class="absolute z-20 top-full left-0 right-0 mt-1 max-h-48 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                  <button v-for="u in filteredLeaders.slice(0, 30)" :key="u.id" type="button" class="flex items-center gap-2 w-full px-3 py-2 hover:bg-gray-50 text-left" @mousedown.prevent="toggleLeader(u)">
                    <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" :checked="(form.team_leader_ids || []).includes(u.id)" />
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-[9px] font-bold text-gray-600">{{ initials(u.name) }}</span>
                    <div class="min-w-0"><p class="text-sm text-gray-900 truncate">{{ u.name }}</p><p class="text-xs text-gray-500 truncate">{{ u.email }}</p></div>
                  </button>
                </div>
              </div>
              <p v-if="errors.team_leader_ids" class="mt-1 text-xs text-red-600">{{ errors.team_leader_ids[0] }}</p>
            </div>
            <div class="relative md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Team Members</label>
              <div class="rounded-lg border border-gray-300 px-3 py-2" @click="memberDropdownOpen = true">
                <div v-if="selectedMembers.length" class="mb-2 flex flex-wrap gap-1.5">
                  <span v-for="u in selectedMembers" :key="u.id" class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-1 text-xs text-gray-800">
                    {{ u.name }}
                    <button type="button" class="text-gray-700 hover:text-gray-900" @click.stop="removeMember(u.id)">&times;</button>
                  </span>
                </div>
                <input v-model="memberSearch" type="text" class="w-full border-0 p-0 text-sm focus:ring-0" placeholder="Select team members" @focus="memberDropdownOpen = true" @blur="setTimeout(() => memberDropdownOpen = false, 200)" />
                <div v-if="memberDropdownOpen && filteredMembers.length" class="absolute z-20 top-full left-0 right-0 mt-1 max-h-52 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                  <button v-for="u in filteredMembers.slice(0, 50)" :key="u.id" type="button" class="flex items-center gap-2 w-full px-3 py-2 hover:bg-gray-50 text-left" @mousedown.prevent="toggleMember(u)">
                    <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-gray-700" :checked="(form.member_ids || []).includes(u.id)" />
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-[9px] font-bold text-gray-600">{{ initials(u.name) }}</span>
                    <div class="min-w-0"><p class="text-sm text-gray-900 truncate">{{ u.name }}</p><p class="text-xs text-gray-500 truncate">{{ u.email }}</p></div>
                  </button>
                </div>
              </div>
              <p v-if="errors.member_ids" class="mt-1 text-xs text-red-600">{{ errors.member_ids[0] }}</p>
            </div>
          </div>
        </section>

        <!-- Settings -->
        <section>
          <h2 class="text-sm font-semibold text-gray-900 mb-4">Settings</h2>
          <div class="flex items-center gap-4">
            <label class="text-sm font-medium text-gray-700">Status</label>
            <button type="button" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors" :class="form.status === 'active' ? 'bg-green-600' : 'bg-gray-300'" @click="form.status = form.status === 'active' ? 'inactive' : 'active'">
              <span class="inline-block h-4 w-4 rounded-full bg-white transition-transform" :class="form.status === 'active' ? 'translate-x-6' : 'translate-x-1'" />
            </button>
            <span class="text-sm" :class="form.status === 'active' ? 'text-green-700 font-medium' : 'text-gray-500'">{{ form.status === 'active' ? 'Active' : 'Inactive' }}</span>
          </div>
        </section>
      </div>

      <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 flex items-center justify-end gap-3 rounded-b-xl">
        <router-link :to="`/teams/${route.params.id}`" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Cancel</router-link>
        <button type="submit" :disabled="loading" class="px-5 py-2.5 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700 disabled:opacity-50 shadow-sm">
          {{ loading ? 'Saving…' : 'Save Changes' }}
        </button>
      </div>
    </form>
  </div>
</template>
