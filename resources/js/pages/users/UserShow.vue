<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import usersApi from '@/services/usersApi'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { toDdMmYyyy } from '@/lib/dateFormat'

const route = useRoute()
const router = useRouter()

const isEmployeeRoute = computed(() => typeof route.path === 'string' && route.path.startsWith('/employees'))
const auth = useAuthStore()
const user = ref(null)
const managers = ref([])
const teamLeaders = ref([])
const loading = ref(true)

const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r)
    ? r.some((x) => (typeof x === 'string' ? x === 'superadmin' : x?.name === 'superadmin'))
    : false
})

const permissions = computed(() => auth.user?.permissions ?? [])

/** Edit allowed: super admin can only edit own; others need super admin or users.edit permission */
const canEdit = computed(() => {
  if (!user.value) return false
  const isViewedSuperAdmin = (user.value.roles ?? []).some(
    (r) => (typeof r === 'object' ? r?.name === 'superadmin' : r === 'superadmin')
  )
  if (isViewedSuperAdmin) return auth.user?.id === user.value.id
  return isSuperAdmin.value || permissions.value.includes('users.edit')
})

const managerName = computed(() => {
  if (!user.value?.manager_id) return 'N/A'
  const m = managers.value.find((x) => String(x.id) === String(user.value.manager_id))
  return m?.name ?? 'N/A'
})

const teamLeaderName = computed(() => {
  if (!user.value?.team_leader_id) return 'N/A'
  const t = teamLeaders.value.find((x) => String(x.id) === String(user.value.team_leader_id))
  return t?.name ?? 'N/A'
})

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

const formatRoleForDisplay = (name) => {
  if (!name || typeof name !== 'string') return ''
  return name
    .split('_')
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
    .join(' ')
}

// Date display: dd-mm-yyyy
function formatDateDdMmYyyy(d) {
  if (!d) return '—'
  const str = typeof d === 'string' ? d.trim().slice(0, 10) : (d instanceof Date ? d.toISOString().slice(0, 10) : '')
  if (!str) return '—'
  return toDdMmYyyy(str) || '—'
}

// Joining date: use joining_date if set, else created_at (date when user was added to system)
const joiningDateDisplay = computed(() => {
  const d = user.value?.joining_date || user.value?.created_at
  return formatDateDdMmYyyy(d)
})

const terminateDateDisplay = computed(() => {
  return formatDateDdMmYyyy(user.value?.terminate_date)
})

const lastActivityDisplay = computed(() => {
  const d = user.value?.last_login_at
  if (!d) return '—'
  try {
    const date = new Date(d)
    if (Number.isNaN(date.getTime())) return '—'
    return formatDateDdMmYyyy(date.toISOString().slice(0, 10))
  } catch {
    return '—'
  }
})

// System Access: from user record — status (approved = access), extension (Cisco), phone (WhatsApp)
const hasSystemAccess = computed(() => (user.value?.status === 'approved' ? 'Yes' : 'No'))
const hasCiscoExtension = computed(() => (user.value?.extension ? 'Yes' : 'No'))
const whatsAppAssigned = computed(() => (user.value?.phone ? 'Yes' : 'No'))

onMounted(async () => {
  try {
    const { data } = await usersApi.show(route.params.id)
    user.value = data.user
    managers.value = data.managers ?? []
    teamLeaders.value = data.team_leaders ?? []
  } catch {
    router.push(isEmployeeRoute.value ? '/employees' : '/users')
  } finally {
    loading.value = false
  }
})

const onClose = () => router.push(isEmployeeRoute.value ? '/employees' : '/users')
const onEdit = () => router.push(isEmployeeRoute.value ? `/employees/${route.params.id}/edit` : `/users/${route.params.id}/edit`)
</script>

<template>
  <div class="space-y-0">
    <div v-if="loading" class="flex justify-center py-16">
      <svg class="animate-spin h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
    </div>

    <div v-else-if="user" class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <!-- Header: Breadcrumbs + heading first, then Close -->
      <div class="flex flex-wrap items-baseline justify-between gap-2 px-6 py-5 border-b border-gray-200">
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-xl font-semibold text-gray-900">Employee Details</h1>
          <Breadcrumbs />
        </div>
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

      <!-- Two-column sections -->
      <div class="px-6 py-5 space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Basic Information -->
          <section>
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Basic Information</h2>
            <dl class="space-y-0 text-sm">
              <div class="flex items-baseline gap-x-4 border-b border-gray-100 py-2.5">
                <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">Employee Name:</dt>
                <dd class="min-w-0 font-semibold text-gray-900">{{ user.name || '—' }}</dd>
              </div>
              <div class="flex items-baseline gap-x-4 border-b border-gray-100 py-2.5">
                <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">Employee ID:</dt>
                <dd class="min-w-0 font-semibold text-gray-900">{{ user.id != null ? user.id : '—' }}</dd>
              </div>
              <div class="flex items-baseline gap-x-4 border-b border-gray-100 py-2.5">
                <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">Department:</dt>
                <dd class="min-w-0 font-semibold text-gray-900">{{ user.department || '—' }}</dd>
              </div>
              <div class="flex items-baseline gap-x-4 border-b border-gray-100 py-2.5">
                <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">Status:</dt>
                <dd class="mt-0.5 min-w-0">
                  <span
                    :class="[
                      'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium',
                      statusBadgeClass(user.status),
                    ]"
                  >
                    {{ statusLabel(user.status) }}
                  </span>
                </dd>
              </div>
            </dl>
          </section>

          <!-- Contact Information -->
          <section>
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Contact Information</h2>
            <dl class="space-y-0 text-sm">
              <div class="flex items-baseline gap-x-4 border-b border-gray-100 py-2.5">
                <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">Primary Email:</dt>
                <dd class="min-w-0 font-medium text-green-600 break-all">{{ user.email || '—' }}</dd>
              </div>
              <div class="flex items-baseline gap-x-4 border-b border-gray-100 py-2.5">
                <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">WhatsApp Number:</dt>
                <dd class="min-w-0 font-medium text-green-600 flex items-center gap-1.5"><svg class="w-4 h-4 text-green-600 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg> {{ user.phone || '—' }}</dd>
              </div>
              <div class="flex items-baseline gap-x-4 border-b border-gray-100 py-2.5">
                <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">Cisco Extension:</dt>
                <dd class="min-w-0 font-semibold text-gray-900 flex items-center gap-1.5"><svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg> {{ user.extension || '—' }}</dd>
              </div>
              <div class="flex items-baseline gap-x-4 border-b border-gray-100 py-2.5">
                <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">Country:</dt>
                <dd class="min-w-0 font-semibold text-gray-900">{{ user.country || '—' }}</dd>
              </div>
              <div class="flex items-baseline gap-x-4 border-b border-gray-100 py-2.5">
                <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">GMIC No:</dt>
                <dd class="min-w-0 font-semibold text-gray-900">{{ user.cnic_number || '—' }}</dd>
              </div>
            </dl>
          </section>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Role(s) & Hierarchy -->
          <section>
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Role(s) & Hierarchy</h2>
            <dl class="space-y-0 text-sm">
              <div class="flex items-baseline gap-x-4 border-b border-gray-100 py-2.5">
                <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">Roles:</dt>
                <dd class="min-w-0 flex flex-wrap gap-2">
                  <span
                    v-for="r in (user.roles || [])"
                    :key="r.id"
                    class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800"
                  >
                    {{ formatRoleForDisplay(r.name) }}
                  </span>
                  <span v-if="!(user.roles || []).length" class="text-gray-500">No roles assigned</span>
                </dd>
              </div>
              <div class="flex items-baseline gap-x-4 border-b border-gray-100 py-2.5">
                <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">Team Leader:</dt>
                <dd class="min-w-0 font-semibold text-gray-900">{{ teamLeaderName }}</dd>
              </div>
              <div class="flex items-baseline gap-x-4 border-b border-gray-100 py-2.5">
                <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">Manager:</dt>
                <dd class="min-w-0 font-semibold text-gray-900">{{ managerName }}</dd>
              </div>
            </dl>
          </section>

          <!-- System Access (from user: status=approved, extension, phone) -->
          <section>
            <h2 class="text-sm font-semibold text-gray-900 mb-3">System Access</h2>
            <dl class="space-y-0 text-sm">
              <div class="flex items-baseline gap-x-4 border-b border-gray-100 py-2.5">
                <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">Has System Access:</dt>
                <dd class="min-w-0 flex items-center gap-1.5 font-medium" :class="hasSystemAccess === 'Yes' ? 'text-green-600' : 'text-gray-600'"><svg v-if="hasSystemAccess === 'Yes'" class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg> {{ hasSystemAccess }}</dd>
              </div>
              <div class="flex items-baseline gap-x-4 border-b border-gray-100 py-2.5">
                <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">Has Cisco Extension:</dt>
                <dd class="min-w-0 flex items-center gap-1.5 font-medium" :class="hasCiscoExtension === 'Yes' ? 'text-green-600' : 'text-gray-600'"><svg v-if="hasCiscoExtension === 'Yes'" class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg> {{ hasCiscoExtension }}</dd>
              </div>
              <div class="flex items-baseline gap-x-4 border-b border-gray-100 py-2.5">
                <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">WhatsApp Assigned:</dt>
                <dd class="min-w-0 flex items-center gap-1.5 font-medium" :class="whatsAppAssigned === 'Yes' ? 'text-green-600' : 'text-gray-600'"><svg v-if="whatsAppAssigned === 'Yes'" class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg> {{ whatsAppAssigned }}</dd>
              </div>
            </dl>
          </section>
        </div>

        <!-- Timeline: all dates in one row; label then space then value; values align -->
        <section>
          <h2 class="text-sm font-semibold text-gray-900 mb-3">Timeline</h2>
          <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-0 border-b border-gray-100 py-2.5 text-sm">
            <div class="flex items-baseline gap-x-4">
              <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">Joining Date:</dt>
              <dd class="min-w-0 font-semibold text-gray-900">{{ joiningDateDisplay }}</dd>
            </div>
            <div class="flex items-baseline gap-x-4">
              <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">Last Activity Date:</dt>
              <dd class="min-w-0 font-semibold text-gray-900">{{ lastActivityDisplay }}</dd>
            </div>
            <div class="flex items-baseline gap-x-4">
              <dt class="w-[13rem] shrink-0 text-gray-500 font-medium">Terminate Date:</dt>
              <dd class="min-w-0 font-semibold text-gray-900">{{ terminateDateDisplay }}</dd>
            </div>
          </dl>
        </section>

        <!-- Internal Comment -->
        <section v-if="user.additional_notes">
          <h2 class="text-sm font-semibold text-gray-900 mb-3">Internal Comment</h2>
          <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ user.additional_notes }}</p>
        </section>
      </div>

      <!-- Footer: Close, Edit Employee (permission-based) -->
      <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-end gap-3">
        <button
          type="button"
          @click="onClose"
          class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          Close
        </button>
        <button
          v-if="canEdit"
          type="button"
          @click="onEdit"
          class="inline-flex items-center gap-2 rounded-lg border border-green-600 bg-green-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-700"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
          </svg>
          Edit Employee
        </button>
      </div>
    </div>
  </div>
</template>
