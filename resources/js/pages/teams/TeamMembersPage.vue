<script setup>
/**
 * Team Members Management – dual-panel layout.
 * Left: Current members with roles, search, remove.
 * Right: Available users to add, searchable/filterable.
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import teamsApi from '@/services/teamsApi'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Toast from '@/components/Toast.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const isSuperAdmin = computed(() => auth.user?.roles?.includes('superadmin') ?? false)
const canManage = computed(() => isSuperAdmin.value || (auth.user?.permissions ?? []).includes('teams.manage_members'))

const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

const loading = ref(true)
const team = ref(null)
const members = ref([])
const availableUsers = ref([])

const memberSearch = ref('')
const availableSearch = ref('')
const availableFilter = ref('')
const selectedToAdd = ref([])
const addLoading = ref(false)
const removeLoading = ref(null)

const filteredMembers = computed(() => {
  const term = memberSearch.value.toLowerCase()
  if (!term) return members.value
  return members.value.filter((m) => m.name.toLowerCase().includes(term) || m.email?.toLowerCase().includes(term))
})

const filteredAvailable = computed(() => {
  let list = availableUsers.value
  const term = availableSearch.value.toLowerCase()
  if (term) {
    list = list.filter((u) => u.name.toLowerCase().includes(term) || u.email?.toLowerCase().includes(term))
  }
  if (availableFilter.value) {
    list = list.filter((u) => u.department === availableFilter.value)
  }
  return list
})

const departments = computed(() => {
  const depts = new Set(availableUsers.value.map((u) => u.department).filter(Boolean))
  return [...depts].sort()
})

function initials(name) {
  if (!name) return '?'
  return name.split(' ').map((w) => w[0]).join('').toUpperCase().slice(0, 2)
}

function roleNames(user) {
  return (user.roles ?? []).map((r) => typeof r === 'string' ? r : r?.name).join(', ') || '—'
}

async function loadData() {
  try {
    const [teamData, available] = await Promise.all([
      teamsApi.show(route.params.id),
      teamsApi.availableMembers(route.params.id),
    ])
    team.value = teamData.team
    members.value = teamData.members ?? []
    availableUsers.value = (available.users ?? []).filter((u) => {
      return !members.value.some((m) => m.id === u.id)
    })
  } catch {
    router.push('/teams')
  }
}

async function addSelected() {
  if (!selectedToAdd.value.length) return
  addLoading.value = true
  try {
    await teamsApi.addMembers(team.value.id, selectedToAdd.value)
    toast('success', `${selectedToAdd.value.length} member(s) added.`)
    selectedToAdd.value = []
    await loadData()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to add members.')
  } finally {
    addLoading.value = false
  }
}

async function removeMember(member) {
  removeLoading.value = member.id
  try {
    await teamsApi.removeMember(team.value.id, member.id)
    toast('success', `${member.name} removed from team.`)
    await loadData()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to remove member.')
  } finally {
    removeLoading.value = null
  }
}

function toggleSelect(userId) {
  const idx = selectedToAdd.value.indexOf(userId)
  if (idx >= 0) selectedToAdd.value.splice(idx, 1)
  else selectedToAdd.value.push(userId)
}

onMounted(async () => {
  loading.value = true
  await loadData()
  loading.value = false
})
</script>

<template>
  <div class="space-y-6">
    <Toast :show="showToast" :type="toastType" :message="toastMsg" @dismiss="showToast = false" />

    <router-link :to="`/teams/${route.params.id}`" class="inline-flex items-center gap-1 text-sm text-green-600 hover:text-green-700 font-medium">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
      Back to Team Details
    </router-link>

    <div>
      <h1 class="text-2xl font-bold text-gray-900">Manage Members{{ team ? `: ${team.name}` : '' }}</h1>
      <p class="mt-1 text-sm text-gray-500">Add or remove team members. {{ team?.max_members ? `Team limit: ${team.max_members} members.` : 'No member limit set.' }}</p>
    </div>
    <Breadcrumbs />

    <div v-if="loading" class="flex justify-center py-16">
      <svg class="animate-spin h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Left: Current Members -->
      <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden flex flex-col" style="max-height: 70vh">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
          <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-semibold text-gray-900">Current Members ({{ members.length }}{{ team?.max_members ? ` / ${team.max_members}` : '' }})</h2>
          </div>
          <input v-model="memberSearch" type="text" class="w-full rounded-lg border-gray-300 px-3 py-1.5 text-sm focus:border-green-500 focus:ring-green-500" placeholder="Search members…" />
        </div>
        <div class="flex-1 overflow-y-auto divide-y divide-gray-100">
          <div v-if="!filteredMembers.length" class="px-4 py-8 text-center text-sm text-gray-400">No members found</div>
          <div v-for="m in filteredMembers" :key="m.id" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-green-100 text-xs font-bold text-green-700 flex-shrink-0">{{ initials(m.name) }}</span>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-gray-900 truncate">{{ m.name }}</p>
              <p class="text-xs text-gray-500 truncate">{{ m.email }} &middot; {{ roleNames(m) }}</p>
            </div>
            <span :class="['inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium', m.status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600']">{{ m.status === 'approved' ? 'Active' : m.status }}</span>
            <button v-if="canManage" type="button" class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-50" :disabled="removeLoading === m.id" @click="removeMember(m)">
              <svg v-if="removeLoading !== m.id" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              <svg v-else class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Right: Available Users -->
      <div v-if="canManage" class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden flex flex-col" style="max-height: 70vh">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
          <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-semibold text-gray-900">Available Users ({{ filteredAvailable.length }})</h2>
            <button
              v-if="selectedToAdd.length"
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-50"
              :disabled="addLoading"
              @click="addSelected"
            >
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
              {{ addLoading ? 'Adding…' : `Add ${selectedToAdd.length}` }}
            </button>
          </div>
          <div class="flex gap-2">
            <input v-model="availableSearch" type="text" class="flex-1 rounded-lg border-gray-300 px-3 py-1.5 text-sm focus:border-green-500 focus:ring-green-500" placeholder="Search users…" />
            <select v-if="departments.length" v-model="availableFilter" class="rounded-lg border-gray-300 px-2 py-1.5 text-sm focus:border-green-500 focus:ring-green-500">
              <option value="">All Depts</option>
              <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
            </select>
          </div>
        </div>
        <div class="flex-1 overflow-y-auto divide-y divide-gray-100">
          <div v-if="!filteredAvailable.length" class="px-4 py-8 text-center text-sm text-gray-400">No available users</div>
          <label
            v-for="u in filteredAvailable"
            :key="u.id"
            class="flex items-center gap-3 px-4 py-3 hover:bg-green-50 cursor-pointer"
            :class="selectedToAdd.includes(u.id) ? 'bg-green-50' : ''"
          >
            <input type="checkbox" :checked="selectedToAdd.includes(u.id)" class="rounded border-gray-300 text-green-600 focus:ring-green-500" @change="toggleSelect(u.id)" />
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-[10px] font-bold text-gray-600 flex-shrink-0">{{ initials(u.name) }}</span>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-gray-900 truncate">{{ u.name }}</p>
              <p class="text-xs text-gray-500 truncate">{{ u.email }}{{ u.department ? ` · ${u.department}` : '' }}</p>
            </div>
          </label>
        </div>
      </div>
    </div>
  </div>
</template>
