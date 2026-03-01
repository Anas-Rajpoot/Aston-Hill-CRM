<script setup>
/**
 * Team Detail – enhanced show page with team info card, members grid with avatars and roles,
 * quick stats, and action buttons.
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
const canEdit = computed(() => isSuperAdmin.value || (auth.user?.permissions ?? []).includes('teams.edit'))
const canDelete = computed(() => isSuperAdmin.value || (auth.user?.permissions ?? []).includes('teams.delete'))

const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

const team = ref(null)
const members = ref([])
const loading = ref(true)
const deleteModal = ref({ visible: false, loading: false })

function statusBadgeClass(s) { return s === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }
function formatDate(d) {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) } catch { return '—' }
}
function initials(name) {
  if (!name) return '?'
  return name.split(' ').map((w) => w[0]).join('').toUpperCase().slice(0, 2)
}
function roleNames(user) {
  return (user.roles ?? []).map((r) => typeof r === 'string' ? r : r?.name).filter(Boolean)
}

const membersByRole = computed(() => {
  const groups = {}
  for (const m of members.value) {
    const roles = roleNames(m)
    const key = roles.length ? roles.join(', ') : 'No Role'
    if (!groups[key]) groups[key] = []
    groups[key].push(m)
  }
  return groups
})

const capacityPercent = computed(() => {
  if (!team.value?.max_members) return null
  return Math.round((members.value.length / team.value.max_members) * 100)
})

onMounted(async () => {
  try {
    const data = await teamsApi.show(route.params.id)
    team.value = data.team
    members.value = data.members ?? []
  } catch {
    router.push('/teams')
  } finally {
    loading.value = false
  }
})

async function executeDelete() {
  deleteModal.value.loading = true
  try {
    await teamsApi.destroy(team.value.id)
    router.push('/teams')
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to delete team.')
  } finally {
    deleteModal.value.loading = false
    deleteModal.value.visible = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <Toast :show="showToast" :type="toastType" :message="toastMsg" @dismiss="showToast = false" />

    <div v-if="loading" class="flex justify-center py-16">
      <svg class="animate-spin h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
    </div>

    <div v-else-if="team" class="space-y-6">
      <!-- Header -->
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <router-link to="/teams" class="inline-flex items-center gap-1 text-sm text-green-600 hover:text-green-700 font-medium mb-2">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            Back to Teams
          </router-link>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900">{{ team.name }}</h1>
            <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-medium', statusBadgeClass(team.status)]">{{ team.status === 'active' ? 'Active' : 'Inactive' }}</span>
          </div>
          <Breadcrumbs class="mt-1" />
        </div>
        <div class="flex items-center gap-2">
          <button v-if="canEdit" type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700" @click="router.push(`/teams/${team.id}/edit`)">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
            Edit Team
          </button>
          <button v-if="canDelete" type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50" @click="deleteModal.visible = true">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            Delete
          </button>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
          <p class="text-xs text-gray-500 font-medium uppercase">Members</p>
          <p class="text-xl font-bold text-gray-900 mt-1">{{ members.length }}{{ team.max_members ? ` / ${team.max_members}` : '' }}</p>
          <div v-if="capacityPercent !== null" class="mt-2">
            <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all" :class="capacityPercent > 90 ? 'bg-red-500' : capacityPercent > 70 ? 'bg-yellow-500' : 'bg-green-500'" :style="{ width: `${Math.min(capacityPercent, 100)}%` }" />
            </div>
            <p class="text-[10px] text-gray-400 mt-0.5">{{ capacityPercent }}% capacity</p>
          </div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
          <p class="text-xs text-gray-500 font-medium uppercase">Department</p>
          <p class="text-lg font-semibold text-gray-900 mt-1">{{ team.department || '—' }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
          <p class="text-xs text-gray-500 font-medium uppercase">Manager</p>
          <div v-if="team.manager" class="flex items-center gap-2 mt-1">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-green-100 text-[10px] font-bold text-green-700">{{ initials(team.manager.name) }}</span>
            <p class="text-sm font-semibold text-gray-900 truncate">{{ team.manager.name }}</p>
          </div>
          <p v-else class="text-lg text-gray-400 mt-1">—</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
          <p class="text-xs text-gray-500 font-medium uppercase">Team Leader</p>
          <div v-if="team.team_leader" class="flex items-center gap-2 mt-1">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-blue-700">{{ initials(team.team_leader.name) }}</span>
            <p class="text-sm font-semibold text-gray-900 truncate">{{ team.team_leader.name }}</p>
          </div>
          <p v-else class="text-lg text-gray-400 mt-1">—</p>
        </div>
      </div>

      <!-- Team Info Card -->
      <div v-if="team.description" class="rounded-xl border border-gray-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-gray-900 mb-2">Description</h2>
        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ team.description }}</p>
      </div>

      <!-- Members Grid -->
      <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-900">Team Members ({{ members.length }})</h2>
        </div>

        <div v-if="!members.length" class="px-5 py-10 text-center text-sm text-gray-400">
          <svg class="mx-auto h-10 w-10 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
          No members in this team yet.
        </div>

        <!-- Group members by role -->
        <div v-else class="p-5 space-y-6">
          <div v-for="(group, role) in membersByRole" :key="role">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">{{ role }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              <div v-for="m in group" :key="m.id" class="flex items-center gap-3 rounded-lg border border-gray-100 bg-gray-50/50 p-3 hover:border-gray-200 transition-colors">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold flex-shrink-0" :class="m.status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500'">{{ initials(m.name) }}</span>
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-medium text-gray-900 truncate">{{ m.name }}</p>
                  <p class="text-xs text-gray-500 truncate">{{ m.email }}</p>
                </div>
                <span :class="['inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium', m.status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600']">{{ m.status === 'approved' ? 'Active' : m.status }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Timeline -->
      <div class="rounded-xl border border-gray-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">Timeline</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div class="flex items-baseline gap-3">
            <dt class="text-gray-500 font-medium">Created:</dt>
            <dd class="font-semibold text-gray-900">{{ formatDate(team.created_at) }}</dd>
          </div>
          <div class="flex items-baseline gap-3">
            <dt class="text-gray-500 font-medium">Last Updated:</dt>
            <dd class="font-semibold text-gray-900">{{ formatDate(team.updated_at) }}</dd>
          </div>
        </dl>
      </div>
    </div>

    <!-- Delete Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="deleteModal.visible" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50" @click.self="deleteModal.visible = false">
          <div class="rounded-xl bg-white shadow-xl max-w-sm w-full p-6" @click.stop>
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 mb-4">
              <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 text-center">Delete Team</h3>
            <p class="mt-2 text-sm text-gray-600 text-center">This will permanently delete <strong>{{ team?.name }}</strong> and cannot be undone.</p>
            <div class="flex justify-end gap-3 mt-6">
              <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="deleteModal.visible = false">Cancel</button>
              <button type="button" class="rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700 disabled:opacity-50" :disabled="deleteModal.loading" @click="executeDelete">
                {{ deleteModal.loading ? 'Deleting…' : 'Delete Team' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
