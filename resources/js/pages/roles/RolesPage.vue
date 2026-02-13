<script setup>
/**
 * Progressive rendering: page shell (header, guidelines, table header) renders immediately.
 * Stats and table body load in background with section-level skeleton loaders.
 */
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/axios'
import SkeletonStatsCards from '@/components/skeletons/SkeletonStatsCards.vue'
import SkeletonTable from '@/components/skeletons/SkeletonTable.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { toDdMonYyyyLower } from '@/lib/dateFormat'

const router = useRouter()
const roles = ref([])
const stats = ref({ total_roles: 0, active_roles: 0, total_users_assigned: 0 })
const loading = ref(true)
const successMessage = ref('')
const errorMessage = ref('')
const actionMenuOpen = ref(null)

const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/super-admin/roles')
    roles.value = data.data ?? []
    stats.value = data.stats ?? { total_roles: 0, active_roles: 0, total_users_assigned: 0 }
  } catch (e) {
    errorMessage.value = e?.response?.data?.message || 'Failed to load roles.'
  } finally {
    loading.value = false
  }
}

const formatDate = (d) => {
  if (!d) return '—'
  const str = typeof d === 'string' ? d.trim().slice(0, 10) : ''
  if (!str) return '—'
  return toDdMonYyyyLower(str) || '—'
}

const DESCRIPTION_MAX = 50
const truncateDescription = (text) => {
  if (!text || typeof text !== 'string') return { short: '—', full: '' }
  const t = text.trim()
  if (!t) return { short: '—', full: '' }
  if (t.length <= DESCRIPTION_MAX) return { short: t, full: t }
  return { short: t.slice(0, DESCRIPTION_MAX) + '...', full: t }
}

const goToCreate = () => router.push('/roles/create')
const goToEdit = (role) => {
  actionMenuOpen.value = null
  router.push(`/roles/${role.id}/edit`)
}
const goToPermissions = (role) => {
  actionMenuOpen.value = null
  router.push(`/roles/${role.id}/permissions`)
}

const deleteRole = async (role) => {
  actionMenuOpen.value = null
  if (role.name === 'superadmin') return
  if (!confirm(`Delete role "${role.name}"?`)) return
  try {
    await api.delete(`/super-admin/roles/${role.id}`)
    await load()
    successMessage.value = 'Role deleted.'
    setTimeout(() => { successMessage.value = '' }, 3000)
  } catch (e) {
    errorMessage.value = e?.response?.data?.message || 'Failed to delete role.'
  }
}

const toggleActionMenu = (id) => {
  actionMenuOpen.value = actionMenuOpen.value === id ? null : id
}

const closeActionMenu = () => {
  actionMenuOpen.value = null
}

onMounted(() => {
  load()
  document.addEventListener('click', closeActionMenu)
  if (router.currentRoute.value.query?.created) {
    successMessage.value = 'Role created successfully.'
    router.replace({ path: '/roles', query: {} })
    setTimeout(() => { successMessage.value = '' }, 4000)
  }
  if (router.currentRoute.value.query?.updated) {
    successMessage.value = 'Role updated successfully.'
    router.replace({ path: '/roles', query: {} })
    setTimeout(() => { successMessage.value = '' }, 4000)
  }
})

onUnmounted(() => {
  document.removeEventListener('click', closeActionMenu)
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <div class="flex flex-wrap items-baseline gap-2">
          <Breadcrumbs />
          <h1 class="text-2xl font-bold text-gray-900 leading-tight">Roles</h1>
        </div>
        <p class="mt-1 text-sm text-gray-500">Define and manage system roles and permissions.</p>
      </div>
      <button
        type="button"
        @click="goToCreate"
        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Add New Role
      </button>
    </div>

    <div v-if="successMessage" class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700 flex items-center justify-between">
      <span>{{ successMessage }}</span>
      <button type="button" @click="successMessage = ''" class="text-green-600 hover:text-green-800">×</button>
    </div>
    <div v-if="errorMessage" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 flex items-center justify-between">
      <span>{{ errorMessage }}</span>
      <button type="button" @click="errorMessage = ''" class="text-red-600 hover:text-red-800">×</button>
    </div>

    <!-- Stats: section-level async – skeleton until loaded -->
    <SkeletonStatsCards v-if="loading" :count="3" />
    <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-gray-500">Total Roles</p>
        <p class="mt-1 text-2xl font-bold text-blue-600">{{ stats.total_roles }}</p>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-gray-500">Active Roles</p>
        <p class="mt-1 text-2xl font-bold text-green-600">{{ stats.active_roles }}</p>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-gray-500">Total Users Assigned</p>
        <p class="mt-1 text-2xl font-bold text-rose-600">{{ stats.total_users_assigned }}</p>
      </div>
    </div>

    <!-- Guidelines -->
    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
      <div class="flex gap-3">
        <svg class="h-5 w-5 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
        </svg>
        <div class="text-sm text-blue-800">
          <p class="font-medium text-blue-900 mb-2">Role Management Guidelines</p>
          <ul class="list-disc list-inside space-y-1 text-blue-800">
            <li>Super Admin role is locked and cannot be edited or removed</li>
            <li>Multiple roles can be assigned to a single user</li>
            <li>Inactive roles cannot be assigned to users</li>
            <li>Role changes apply immediately to all assigned users</li>
            <li>All role modifications are logged in Audit Logs</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- System Roles Table: shell (title) always; body skeleton or data -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
        <h2 class="text-base font-semibold text-gray-900">System Roles</h2>
      </div>
      <SkeletonTable v-if="loading" :rows="6" :cols="5" />
      <div v-else-if="!roles.length" class="p-8 text-center text-gray-500">No roles yet. Create one to get started.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role Name</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users Assigned</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created Date</th>
              <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="role in roles" :key="role.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-medium text-gray-900">{{ role.name }}</span>
                  <svg v-if="role.name === 'superadmin'" class="h-4 w-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20" title="Locked">
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                  </svg>
                </div>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 max-w-xs">
                <span
                  :title="(role.description || '').trim().length > DESCRIPTION_MAX ? (role.description || '').trim() : null"
                  class="cursor-default"
                >
                  {{ truncateDescription(role.description).short }}
                </span>
              </td>
              <td class="px-4 py-3 whitespace-nowrap">
                <span class="text-sm text-indigo-600 underline cursor-pointer hover:text-indigo-800">{{ role.users_count ?? 0 }}</span>
              </td>
              <td class="px-4 py-3 whitespace-nowrap">
                <span
                  :class="(role.status || 'active') === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
                  class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                >
                  {{ (role.status || 'active') === 'active' ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                {{ formatDate(role.created_at) }}
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-right relative">
                <button
                  type="button"
                  @click.stop="toggleActionMenu(role.id)"
                  class="p-1 rounded hover:bg-gray-200 text-gray-500"
                  aria-label="Actions"
                >
                  <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                  </svg>
                </button>
                <div
                  v-if="actionMenuOpen === role.id"
                  class="absolute right-0 mt-1 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg z-10"
                  @click.stop
                >
                  <button
                    type="button"
                    @click="goToPermissions(role)"
                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                  >
                    Set permissions
                  </button>
                  <button
                    type="button"
                    @click="goToEdit(role)"
                    :disabled="role.name === 'superadmin'"
                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    Edit Role
                  </button>
                  <button
                    v-if="role.name !== 'superadmin'"
                    type="button"
                    @click="deleteRole(role)"
                    class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                  >
                    Delete Role
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
