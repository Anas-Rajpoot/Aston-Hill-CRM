<script setup>
/**
 * Dynamic table: sortable headers, status/last updated inline edit with Save/Cancel.
 */
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  sort: { type: String, default: 'created_at' },
  order: { type: String, default: 'desc' },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['sort', 'updateStatus', 'updateStatusChangedAt', 'openEdit', 'openAssign'])
let router = null
try {
  router = useRouter()
} catch {
  // Not inside a router (e.g. test env); navigation will be no-op
}
const auth = useAuthStore()

function goToSubmission(leadId) {
  if (router && typeof router.push === 'function') {
    router.push({ path: '/submissions', query: { lead_id: leadId } })
  }
}

function goToDetail(leadId) {
  if (router && typeof router.push === 'function') {
    router.push(`/lead-submissions/${leadId}`)
  }
}
const canEdit = computed(() => {
  const roles = auth.user?.roles ?? []
  const permissions = auth.user?.permissions ?? []
  const isSuperAdmin = Array.isArray(roles) && roles.some((r) => (typeof r === 'string' ? r : r?.name) === 'superadmin')
  if (isSuperAdmin) return true
  return permissions.includes('lead.edit')
})

/** Only superadmin or backoffice can open Edit Submission modal (back office form). */
const canEditBackOffice = computed(() => {
  const roles = auth.user?.roles ?? []
  if (!Array.isArray(roles)) return false
  return roles.some((r) => {
    const name = typeof r === 'string' ? r : r?.name
    return name === 'superadmin' || name === 'backoffice' || name === 'back_office'
  })
})

/** Resubmit: rejected only; super admin or the user who submitted (creator / created_by). */
function canResubmit(row) {
  if (row.status !== 'rejected') return false
  const roles = auth.user?.roles ?? []
  const isSuperAdmin = Array.isArray(roles) && roles.some((r) => (typeof r === 'string' ? r : r?.name) === 'superadmin')
  if (isSuperAdmin) return true
  const creatorId = row.creator?.id ?? row.created_by
  return creatorId != null && Number(creatorId) === Number(auth.user?.id)
}

/** { rowId, col: 'status' | 'status_changed_at' } when a cell is in edit mode */
const editingCell = ref(null)
const editStatusValue = ref('')
const editStatusChangedAtValue = ref('')

const STATUS_OPTIONS = [
  { value: 'draft', label: 'Draft' },
  { value: 'submitted', label: 'Submitted' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
]

function isEditing(rowId, col) {
  return editingCell.value && editingCell.value.rowId === rowId && editingCell.value.col === col
}

function openStatusEdit(row) {
  editingCell.value = { rowId: row.id, col: 'status' }
  editStatusValue.value = row.status || 'draft'
}

function openStatusChangedAtEdit(row) {
  editingCell.value = { rowId: row.id, col: 'status_changed_at' }
  const iso = row.status_changed_at
  if (iso) {
    const d = new Date(iso)
    editStatusChangedAtValue.value = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
  } else {
    editStatusChangedAtValue.value = new Date().toISOString().slice(0, 16)
  }
}

function closeEdit() {
  editingCell.value = null
}

function saveStatus(rowId) {
  const status = editStatusValue.value
  if (status) emit('updateStatus', rowId, status)
  closeEdit()
}

function saveStatusChangedAt(rowId) {
  const local = editStatusChangedAtValue.value
  if (local) {
    const iso = new Date(local).toISOString()
    emit('updateStatusChangedAt', rowId, iso)
  }
  closeEdit()
}

const columnLabels = {
  id: 'ID',
  submitted_at: 'Submission Date',
  created_at: 'Created',
  submission_type: 'Type',
  account_number: 'Account Number',
  company_name: 'Company Name',
  category: 'Service Category',
  type: 'Service Type',
  product: 'Product',
  mrc_aed: 'MRC (AED)',
  quantity: 'Qty',
  sales_agent: 'Sales Agent',
  team_leader: 'Team Leader',
  manager: 'Manager',
  status: 'Status',
  sla_timer: 'SLA Timer',
  status_changed_at: 'Last Updated',
  creator: 'Created By',
  executive: 'Back Office Executive',
  email: 'Email',
  contact_number_gsm: 'Contact',
}

const SORTABLE_COLUMNS = [
  'id', 'submitted_at', 'created_at', 'account_number', 'company_name',
  'category', 'type', 'product', 'mrc_aed', 'quantity', 'status',
  'status_changed_at', 'sales_agent', 'team_leader', 'manager', 'creator',
  'executive', 'email', 'contact_number_gsm', 'submission_type', 'sla_timer',
]

function label(col) {
  return columnLabels[col] ?? col
}

function sortable(col) {
  return SORTABLE_COLUMNS.includes(col)
}

function toggleSort(col) {
  if (!sortable(col)) return
  const nextOrder = props.sort === col && props.order === 'asc' ? 'desc' : 'asc'
  emit('sort', { sort: col, order: nextOrder })
}

function formatValue(row, col) {
  const val = row[col]
  if (val == null || val === '') return '—'
  if (col === 'creator' && val && typeof val === 'object') return val.name ?? '—'
  if (typeof val === 'object') return val.name ?? '—'
  return val
}

/** Date format: 12-Feb-2026 */
function formatDate(d) {
  if (!d) return '—'
  const date = new Date(d)
  const day = String(date.getDate()).padStart(2, '0')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const month = months[date.getMonth()]
  return `${day}-${month}-${date.getFullYear()}`
}

function truncate(str, max = 20) {
  if (!str || typeof str !== 'string') return '—'
  return str.length > max ? str.slice(0, max) + '...' : str
}

const STATUS_BADGES = {
  draft: 'bg-gray-100 text-gray-700',
  submitted: 'bg-blue-100 text-blue-700',
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
}

function statusBadgeClass(status) {
  return STATUS_BADGES[status] ?? 'bg-gray-100 text-gray-700'
}
</script>

<template>
  <div class="relative overflow-x-auto">
    <!-- Loading overlay: does not replace table content -->
    <div
      v-if="loading"
      class="absolute inset-0 z-10 flex items-center justify-center bg-white/80"
      aria-live="polite"
      aria-busy="true"
    >
      <div class="flex flex-col items-center gap-2">
        <svg
          class="h-8 w-8 animate-spin text-green-600"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
        <span class="text-sm font-medium text-gray-600">Updating...</span>
      </div>
    </div>

    <table class="min-w-full">
      <thead>
        <tr class="border-b border-gray-200 bg-gray-100">
          <th class="w-10 px-3 py-3 text-left">
            <input type="checkbox" class="rounded border-gray-300" aria-label="Select all" />
          </th>
          <th
            v-for="col in columns"
            :key="col"
            scope="col"
            class="whitespace-nowrap px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-600"
          >
            <button
              v-if="sortable(col)"
              type="button"
              class="inline-flex items-center gap-1 hover:text-gray-900"
              @click="toggleSort(col)"
            >
              {{ label(col) }}
              <svg
                v-if="sort === col"
                class="h-4 w-4"
                :class="order === 'asc' ? 'rotate-180' : ''"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
              </svg>
            </button>
            <span v-else>{{ label(col) }}</span>
          </th>
          <th scope="col" class="whitespace-nowrap px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-600">
            Actions
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 bg-white">
        <tr v-if="!loading && !data.length">
          <td :colspan="columns.length + 2" class="px-4 py-12 text-center text-gray-500">
            No leads found.
          </td>
        </tr>
        <tr
          v-for="row in data"
          :key="row.id"
          class="hover:bg-gray-50/50"
        >
          <td class="w-10 px-3 py-3">
            <input type="checkbox" class="rounded border-gray-300" :value="row.id" />
          </td>
          <td
            v-for="col in columns"
            :key="col"
            class="px-4 py-3 text-sm text-gray-900"
          >
            <template v-if="col === 'status' && canEdit && isEditing(row.id, 'status')">
              <div class="flex flex-col gap-1.5">
                <select
                  v-model="editStatusValue"
                  class="w-full min-w-[100px] rounded border border-gray-300 bg-white px-2 py-1 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                >
                  <option v-for="opt in STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
                <div class="flex gap-1">
                  <button
                    type="button"
                    class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50"
                    @click="closeEdit"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    class="rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700"
                    @click="saveStatus(row.id)"
                  >
                    Save
                  </button>
                </div>
              </div>
            </template>
            <template v-else-if="col === 'status' && canEdit">
              <button
                type="button"
                class="text-left"
                @click="openStatusEdit(row)"
              >
                <span
                  :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium cursor-pointer hover:ring-2 hover:ring-green-400', statusBadgeClass(row.status)]"
                >
                  {{ row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : '—' }}
                </span>
              </button>
            </template>
            <template v-else-if="col === 'status'">
              <span
                :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(row.status)]"
              >
                {{ row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : '—' }}
              </span>
            </template>
            <template v-else-if="col === 'status_changed_at' && canEdit && isEditing(row.id, 'status_changed_at')">
              <div class="flex flex-col gap-1.5">
                <input
                  v-model="editStatusChangedAtValue"
                  type="datetime-local"
                  class="w-full min-w-0 max-w-[180px] rounded border border-gray-300 bg-white px-2 py-1 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                />
                <div class="flex gap-1">
                  <button
                    type="button"
                    class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50"
                    @click="closeEdit"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    class="rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700"
                    @click="saveStatusChangedAt(row.id)"
                  >
                    Save
                  </button>
                </div>
              </div>
            </template>
            <template v-else-if="col === 'status_changed_at' && canEdit">
              <button
                type="button"
                class="text-left text-sm text-gray-900 hover:text-green-600 hover:underline"
                @click="openStatusChangedAtEdit(row)"
              >
                {{ formatDate(row[col]) }}
              </button>
            </template>
            <template v-else-if="['created_at', 'submitted_at', 'status_changed_at'].includes(col)">
              {{ formatDate(row[col]) }}
            </template>
            <template v-else-if="col === 'mrc_aed'">
              {{ row.mrc_aed != null ? Number(row.mrc_aed).toLocaleString() : '—' }}
            </template>
            <template v-else-if="col === 'executive'">
              <button
                v-if="row[col] === 'Unassigned' && canEditBackOffice"
                type="button"
                class="text-left text-sm text-blue-600 hover:underline"
                @click="$emit('openAssign', row)"
              >
                Unassigned
              </button>
              <span v-else>{{ row[col] ?? '—' }}</span>
            </template>
            <template v-else-if="col === 'submission_type'">
              <span
                :class="[
                  'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                  row[col] === 'Resubmission' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800',
                ]"
              >
                {{ row[col] === 'Resubmission' ? 'Resubmission' : 'New Submission' }}
              </span>
            </template>
            <template v-else-if="col === 'sla_timer'">
              <span
                v-if="row[col]"
                :class="[
                  'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                  String(row[col]).startsWith('Overdue') ? 'bg-red-100 text-red-800' : String(row[col]).includes('h left') ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800',
                ]"
              >
                {{ row[col] }}
              </span>
              <span v-else>—</span>
            </template>
            <template v-else-if="['company_name', 'account_number', 'product', 'type', 'email'].includes(col)">
              {{ truncate(formatValue(row, col), 18) }}
            </template>
            <template v-else-if="col === 'contact_number_gsm'">
              {{ formatValue(row, col) }}
            </template>
            <template v-else>
              {{ formatValue(row, col) }}
            </template>
          </td>
          <td class="px-4 py-3 text-right">
            <div class="inline-flex items-center gap-1">
              <button
                type="button"
                class="rounded-full p-1.5 text-blue-600 hover:bg-blue-50"
                title="View details"
                @click="goToDetail(row.id)"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </button>
              <button
                v-if="canEditBackOffice && !canResubmit(row)"
                type="button"
                class="rounded-full p-1.5 text-green-600 hover:bg-green-50"
                title="Edit Submission"
                @click="$emit('openEdit', row.id)"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                </svg>
              </button>
              <template v-else-if="canResubmit(row)">
                <router-link
                  :to="{ path: `/lead-submissions/${row.id}/resubmit` }"
                  class="rounded bg-blue-800 px-2 py-1 text-xs font-medium text-white hover:bg-blue-900"
                >
                  Resubmit
                </router-link>
              </template>
              <button
                v-else-if="!canResubmit(row) && row.status !== 'rejected'"
                type="button"
                class="rounded-full p-1.5 text-green-600 hover:bg-green-50"
                title="Edit"
                @click="goToSubmission(row.id)"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                </svg>
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
