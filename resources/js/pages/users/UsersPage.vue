<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import usersApi from '@/services/usersApi'

const router = useRouter()
const route = useRoute()
const successMessage = ref('')
const auth = useAuthStore()
const users = ref([])
const roles = ref([])
const stats = ref({ total: 0, active: 0, inactive: 0, pending: 0 })
const pagination = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 })
const filters = ref({
  name: '',
  email: '',
  role: '',
  created_from: '',
  created_to: '',
  status: [], // active=approved, inactive=rejected, pending=pending
})
const filtersVisible = ref(true)
const selectedIds = ref([])
const loading = ref(true)
const bulkLoading = ref(false)
const actionMenuOpen = ref(null)

const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) ? r.includes('superadmin') : false
})

const userIdFormat = (id) => (id ? `USR${String(id).padStart(3, '0')}` : '')

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
  return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

const formatDateTime = (d) => {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const getInitials = (name) => {
  if (!name) return '?'
  return name
    .split(/\s+/)
    .map((n) => n[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
}

const load = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.value.current_page,
      per_page: pagination.value.per_page,
      name: filters.value.name || undefined,
      email: filters.value.email || undefined,
      role: filters.value.role || undefined,
      created_from: filters.value.created_from || undefined,
      created_to: filters.value.created_to || undefined,
    }
    if (filters.value.status?.length) {
      params.status = filters.value.status.join(',')
    }
    const { data } = await usersApi.index(params)
    users.value = data.users ?? []
    pagination.value = data.pagination ?? pagination.value
    stats.value = data.stats ?? stats.value
    if (data.roles) roles.value = data.roles
    selectedIds.value = []
  } finally {
    loading.value = false
  }
}

const applyFilters = () => {
  pagination.value.current_page = 1
  load()
}

const resetFilters = () => {
  filters.value = {
    name: '',
    email: '',
    role: '',
    created_from: '',
    created_to: '',
    status: [],
  }
  pagination.value.current_page = 1
  load()
}

const toggleStatusFilter = (s) => {
  const idx = filters.value.status.indexOf(s)
  if (idx >= 0) filters.value.status.splice(idx, 1)
  else filters.value.status.push(s)
}

const goToPage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return
  pagination.value.current_page = page
  load()
}

const paginationPages = computed(() => {
  const cur = pagination.value.current_page
  const last = pagination.value.last_page
  if (last <= 1) return []
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)
  const pages = []
  if (cur <= 4) {
    for (let i = 1; i <= 5; i++) pages.push(i)
    pages.push('...')
    pages.push(last)
  } else if (cur >= last - 3) {
    pages.push(1)
    pages.push('...')
    for (let i = last - 4; i <= last; i++) pages.push(i)
  } else {
    pages.push(1)
    pages.push('...')
    for (let i = cur - 1; i <= cur + 1; i++) pages.push(i)
    pages.push('...')
    pages.push(last)
  }
  return pages
})

const toggleSelectAll = () => {
  if (selectedIds.value.length === users.value.length) {
    selectedIds.value = []
  } else {
    selectedIds.value = users.value.map((u) => u.id)
  }
}

const toggleSelect = (id) => {
  const idx = selectedIds.value.indexOf(id)
  if (idx >= 0) selectedIds.value.splice(idx, 1)
  else selectedIds.value.push(id)
}

const bulkActivate = async () => {
  if (!selectedIds.value.length) return
  bulkLoading.value = true
  try {
    await usersApi.bulkActivate(selectedIds.value)
    await load()
  } finally {
    bulkLoading.value = false
  }
}

const bulkDeactivate = async () => {
  if (!selectedIds.value.length) return
  bulkLoading.value = true
  try {
    await usersApi.bulkDeactivate(selectedIds.value)
    await load()
  } finally {
    bulkLoading.value = false
  }
}

const toggleActionMenu = (id) => {
  actionMenuOpen.value = actionMenuOpen.value === id ? null : id
}

const closeActionMenu = () => {
  actionMenuOpen.value = null
}

const handleClickOutside = () => {
  if (actionMenuOpen.value) actionMenuOpen.value = null
}

onMounted(() => {
  load()
  document.addEventListener('click', handleClickOutside)
  if (route.query.updated) {
    successMessage.value = `${route.query.updated} updated successfully.`
    router.replace({ path: '/users', query: {} })
    setTimeout(() => {
      successMessage.value = ''
    }, 5000)
  }
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

watch(() => pagination.value.current_page, load)

watch(
  () => route.path,
  (path) => {
    if (path === '/users') load()
  }
)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Users</h1>
      <p class="mt-1 text-sm text-gray-500">Manage system users, assign roles, and control access.</p>
    </div>

    <!-- Success message -->
    <div
      v-if="successMessage"
      class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 flex items-center justify-between"
    >
      <div class="flex items-center gap-2 text-sm text-green-700">
        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        {{ successMessage }}
      </div>
      <button type="button" @click="successMessage = ''" class="text-green-500 hover:text-green-700">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Stats cards -->
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

    <!-- Bulk actions & Add User (all on right) -->
    <div class="flex flex-wrap items-center justify-end gap-2">
      <button
        type="button"
        :disabled="!selectedIds.length || bulkLoading"
        @click="bulkActivate"
        class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        Bulk Activate
      </button>
      <button
        type="button"
        :disabled="!selectedIds.length || bulkLoading"
        @click="bulkDeactivate"
        class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
        Bulk Deactivate
      </button>
      <router-link
        to="/users/create"
        class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Add New User
      </router-link>
    </div>

    <!-- Filters -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <button
        type="button"
        @click="filtersVisible = !filtersVisible"
        class="w-full flex items-center justify-between px-4 py-3 text-left text-sm font-medium text-gray-700 hover:bg-gray-50"
      >
        <span class="flex items-center gap-2">
          <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
          </svg>
          {{ filtersVisible ? 'Hide Filters' : 'Show Filters' }}
        </span>
        <svg class="w-5 h-5 transition-transform" :class="{ 'rotate-180': filtersVisible }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
      <div v-show="filtersVisible" class="border-t border-gray-200 p-4">
        <!-- Row 1: User Name, Email, Role -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">User Name</label>
            <input v-model="filters.name" type="text" placeholder="Search by name" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Email</label>
            <input v-model="filters.email" type="text" placeholder="Search by email" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Role</label>
            <select v-model="filters.role" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
              <option value="">Search by role</option>
              <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</option>
            </select>
          </div>
        </div>
        <!-- Row 2: Created Date From, Created Date To -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Created Date From</label>
            <input v-model="filters.created_from" type="date" placeholder="DD-MMM-YYYY" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Created Date To</label>
            <input v-model="filters.created_to" type="date" placeholder="DD-MMM-YYYY" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-4">
          <span class="text-xs font-medium text-gray-600">Account Status:</span>
          <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="checkbox" :checked="filters.status.includes('approved')" @change="toggleStatusFilter('approved')" class="rounded border-gray-300" />
            Active
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="checkbox" :checked="filters.status.includes('rejected')" @change="toggleStatusFilter('rejected')" class="rounded border-gray-300" />
            Inactive
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="checkbox" :checked="filters.status.includes('pending')" @change="toggleStatusFilter('pending')" class="rounded border-gray-300" />
            Pending Approval
          </label>
        </div>
        <!-- Apply/Reset on left -->
        <div class="mt-4 flex items-center justify-start gap-2">
          <button type="button" @click="applyFilters" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            Apply Filters
          </button>
          <button type="button" @click="resetFilters" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Reset
          </button>
        </div>
      </div>
    </div>

    <!-- Table (no overflow - page scroll only) -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
      <div v-if="loading" class="flex justify-center items-center py-16">
        <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
      </div>
      <div v-else>
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="w-10 px-4 py-3">
                <input
                  type="checkbox"
                  :checked="users.length > 0 && selectedIds.length === users.length"
                  @change="toggleSelectAll"
                  class="rounded border-gray-300"
                />
              </th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">User</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Email</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Phone</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Country</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Assigned Roles</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Status</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Last Login</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Created Date</th>
              <th class="w-12 px-4 py-3 text-right text-xs font-semibold text-gray-600">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 bg-white">
            <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
              <td class="px-4 py-3">
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(user.id)"
                  @change="toggleSelect(user.id)"
                  class="rounded border-gray-300"
                />
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-sm font-semibold text-gray-600 shrink-0">
                    {{ getInitials(user.name) }}
                  </div>
                  <div>
                    <div class="font-medium text-gray-900">{{ user.name }}</div>
                    <div class="text-xs text-gray-500">{{ userIdFormat(user.id) }}</div>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ user.email }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ user.phone || '-' }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ user.country || '-' }}</td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="r in (user.roles || [])"
                    :key="r.id"
                    class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700 border border-blue-200"
                  >
                    {{ r.name }}
                  </span>
                  <span v-if="!(user.roles || []).length" class="text-sm text-gray-400">-</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <span
                  :class="[
                    'inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold',
                    statusBadgeClass(user.status),
                  ]"
                >
                  {{ statusLabel(user.status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ formatDateTime(user.last_login_at) }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(user.created_at) }}</td>
              <td class="px-4 py-3 text-right align-top">
                <div class="relative inline-block text-left">
                  <button
                    type="button"
                    @click.stop="toggleActionMenu(user.id)"
                    class="p-1 rounded hover:bg-gray-100 text-gray-600"
                  >
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z" />
                    </svg>
                  </button>
                  <div
                    v-if="actionMenuOpen === user.id"
                    class="absolute right-0 bottom-full mb-1 z-50 min-w-[10rem] rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                    @click.stop
                  >
                    <router-link
                      :to="`/users/${user.id}`"
                      class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 whitespace-nowrap"
                      @click="closeActionMenu"
                    >
                      View
                    </router-link>
                    <router-link
                      v-if="isSuperAdmin"
                      :to="`/users/${user.id}/edit`"
                      class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 whitespace-nowrap"
                      @click="closeActionMenu"
                    >
                      Edit
                    </router-link>
                  </div>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && users.length === 0">
              <td colspan="10" class="px-4 py-12 text-center text-sm text-gray-500">No users found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="border-t border-gray-200 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600">
          Showing {{ pagination.total === 0 ? 0 : (pagination.current_page - 1) * pagination.per_page + 1 }} to
          {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of
          {{ pagination.total }}
        </p>
        <div v-if="pagination.last_page > 1" class="flex items-center gap-1">
          <button
            type="button"
            class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="pagination.current_page <= 1"
            @click="goToPage(1)"
          >
            First
          </button>
          <button
            type="button"
            class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="pagination.current_page <= 1"
            @click="goToPage(pagination.current_page - 1)"
          >
            Previous
          </button>
          <template v-for="p in paginationPages" :key="p">
            <span v-if="p === '...'" class="px-2 text-gray-400">...</span>
            <button
              v-else
              type="button"
              class="rounded border px-3 py-1.5 text-sm min-w-[2.25rem]"
              :class="p === pagination.current_page ? 'border-blue-600 bg-blue-50 text-blue-700 font-medium' : 'border-gray-300 hover:bg-gray-50'"
              @click="goToPage(p)"
            >
              {{ p }}
            </button>
          </template>
          <button
            type="button"
            class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="goToPage(pagination.current_page + 1)"
          >
            Next
          </button>
          <button
            type="button"
            class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="goToPage(pagination.last_page)"
          >
            Last
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
