<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import usersApi from '@/services/usersApi'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const user = ref(null)
const loading = ref(true)

const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) ? r.includes('superadmin') || r.some((x) => x?.name === 'superadmin') : false
})

const userIdFormat = (id) => (id ? `USR${String(id).padStart(3, '0')}` : '')
const getInitials = (name) => {
  if (!name) return '?'
  return name
    .split(/\s+/)
    .map((n) => n[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
}
const statusLabel = (status) => {
  const s = status ?? 'pending'
  if (s === 'approved') return 'Active'
  if (s === 'rejected') return 'Inactive'
  return 'Pending Approval'
}
const statusBadgeClass = (status) => {
  const s = status ?? 'pending'
  if (s === 'approved') return 'bg-green-100 text-green-800'
  if (s === 'rejected') return 'bg-red-100 text-red-800'
  return 'bg-gray-100 text-gray-800'
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
const formatDate = (d) => {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

onMounted(async () => {
  try {
    const { data } = await usersApi.show(route.params.id)
    user.value = data.user
  } catch {
    router.push('/users')
  } finally {
    loading.value = false
  }
})

const onClose = () => router.push('/users')
const onEdit = () => router.push(`/users/${route.params.id}/edit`)
const onResetPassword = () => {
  // TODO: wire to reset-password flow if you have one
  router.push(`/users/${route.params.id}/edit`)
}
</script>

<template>
  <div class="space-y-0">
    <div v-if="loading" class="flex justify-center py-16">
      <svg class="animate-spin h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
    </div>

    <div v-else-if="user" class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <!-- Header: Title, Close, Status -->
      <div class="flex items-center justify-between px-6 py-5 border-b border-gray-200">
        <h1 class="text-xl font-semibold text-gray-900">User Details</h1>
        <div class="flex items-center gap-3">
          <span
            :class="[
              'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium',
              statusBadgeClass(user.status),
            ]"
          >
            {{ statusLabel(user.status) }}
          </span>
          <button
            type="button"
            @click="onClose"
            class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100"
            aria-label="Close"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Profile summary: Avatar, Name, User ID -->
      <div class="px-6 py-6 flex items-center gap-4 border-b border-gray-100">
        <div
          class="w-16 h-16 rounded-full bg-indigo-600 flex items-center justify-center text-xl font-semibold text-white shrink-0"
        >
          {{ getInitials(user.name) }}
        </div>
        <div>
          <h2 class="text-lg font-semibold text-gray-900">{{ user.name }}</h2>
          <p class="text-sm text-gray-500">User ID: {{ userIdFormat(user.id) }}</p>
        </div>
      </div>

      <!-- Sections -->
      <div class="px-6 py-5 space-y-6">
        <!-- Basic Information -->
        <section>
          <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            Basic Information
          </h3>
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
              <dt class="text-gray-500 font-medium">Full Name</dt>
              <dd class="mt-0.5 text-gray-900">{{ user.name }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 font-medium">Phone Number</dt>
              <dd class="mt-0.5 text-gray-900">{{ user.phone || '-' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 font-medium">Email</dt>
              <dd class="mt-0.5 text-gray-900 break-all">{{ user.email }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 font-medium">Country</dt>
              <dd class="mt-0.5 text-gray-900">{{ user.country || '-' }}</dd>
            </div>
          </dl>
        </section>

        <!-- Assigned Roles -->
        <section>
          <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Assigned Roles
          </h3>
          <div class="flex flex-wrap gap-2">
            <span
              v-for="r in (user.roles || [])"
              :key="r.id"
              class="inline-flex rounded-full bg-indigo-100 px-3 py-1 text-xs font-medium text-indigo-800"
            >
              {{ r.name }}
            </span>
            <span v-if="!(user.roles || []).length" class="text-sm text-gray-500">No roles assigned</span>
          </div>
        </section>

        <!-- Account Activity -->
        <section>
          <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Account Activity
          </h3>
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
              <dt class="text-gray-500 font-medium">Last Login</dt>
              <dd class="mt-0.5 text-gray-900">{{ formatDateTime(user.last_login_at) }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 font-medium">Account Created</dt>
              <dd class="mt-0.5 text-gray-900">{{ formatDate(user.created_at) }}</dd>
            </div>
          </dl>
        </section>
      </div>

      <!-- Footer: Edit User (Super Admin only), Reset Password, Close -->
      <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center gap-3">
        <button
          v-if="isSuperAdmin"
          type="button"
          @click="onEdit"
          class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
          </svg>
          Edit User
        </button>
        <button
          type="button"
          @click="onResetPassword"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          Reset Password
        </button>
        <button
          type="button"
          @click="onClose"
          class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</template>
