<script setup>
/**
 * Field Submissions table – sortable headers, status badges, inline status edit, view/edit actions.
 */
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  sort: { type: String, default: 'created_at' },
  order: { type: String, default: 'desc' },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['sort', 'updateStatus'])
const router = useRouter()
const auth = useAuthStore()
const perms = computed(() => auth.user?.permissions ?? [])
const canEdit = computed(() => perms.value.includes('field_head.view') || perms.value.includes('field_head.list'))

function goToEdit(row) {
  if (row?.id) router.push(`/field-submissions/${row.id}/edit`)
}

function goToView(row) {
  if (row?.id) router.push(`/field-submissions/${row.id}`)
}

const columnLabels = {
  id: 'ID',
  submitted_at: 'Submission Date',
  created_at: 'Created',
  company_name: 'Company Name',
  contact_number: 'Contact Number',
  product: 'Product',
  emirates: 'Emirates',
  complete_address: 'Address',
  sales_agent: 'Sales Agent',
  team_leader: 'Team Leader',
  manager: 'Manager',
  field_agent: 'Field Agent',
  status: 'Status',
  field_status: 'Status',
  target_date: 'Target Date',
  sla_timer: 'SLA Timer',
  sla_status: 'SLA Status',
  last_updated: 'Last Updated',
  creator: 'Created By',
}

const SORTABLE_COLUMNS = [
  'id', 'submitted_at', 'created_at', 'company_name', 'contact_number',
  'product', 'emirates', 'status', 'field_status', 'sales_agent', 'team_leader', 'manager', 'field_agent',
  'target_date', 'sla_timer', 'sla_status', 'last_updated',
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

function formatDate(d) {
  if (!d) return '—'
  const date = new Date(d)
  const day = String(date.getDate()).padStart(2, '0')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  return `${day}-${months[date.getMonth()]}-${date.getFullYear()}`
}

function truncate(str, max = 20) {
  if (!str || typeof str !== 'string') return '—'
  return str.length > max ? str.slice(0, max) + '...' : str
}

const STATUS_BADGES = {
  draft: 'bg-gray-100 text-gray-700',
  submitted: 'bg-blue-100 text-blue-700',
}

/** Badges for field workflow status (field_status) */
const FIELD_STATUS_BADGES = {
  'Pending Assignment': 'bg-gray-100 text-gray-700',
  'Site Survey Scheduled': 'bg-blue-100 text-blue-700',
  'Survey Completed': 'bg-green-100 text-green-700',
  'In Progress': 'bg-amber-100 text-amber-800',
  'Installation Scheduled': 'bg-blue-100 text-blue-700',
  'Completed': 'bg-green-100 text-green-700',
  'Meeting Scheduled': 'bg-blue-100 text-blue-700',
  'Visited': 'bg-green-100 text-green-700',
  'Cancelled': 'bg-red-100 text-red-700',
  'Rescheduled': 'bg-amber-100 text-amber-800',
  'No Show': 'bg-gray-100 text-gray-600',
}

function statusBadgeClass(status) {
  return STATUS_BADGES[status] ?? 'bg-gray-100 text-gray-700'
}

function fieldStatusBadgeClass(fieldStatus) {
  return FIELD_STATUS_BADGES[fieldStatus] ?? 'bg-gray-100 text-gray-700'
}

function slaTimerClass(slaTimer, slaStatus) {
  if (!slaTimer) return 'text-gray-500'
  if (slaTimer === 'Completed' || slaStatus === 'Completed') return 'text-green-600 font-medium'
  if (slaStatus === 'Breached') return 'text-red-600 font-medium'
  if (slaStatus === 'Approaching') return 'text-amber-600 font-medium'
  return 'text-green-600'
}
</script>

<template>
  <div class="relative overflow-x-auto">
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
            ACTIONS
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 bg-white">
        <tr v-if="!loading && !data.length">
          <td :colspan="columns.length + 2" class="px-4 py-12 text-center text-gray-500">
            No field submissions found.
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
            <template v-if="col === 'status' && canEdit">
              <select
                :value="row.status"
                class="rounded border border-gray-300 bg-white px-2 py-1 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                @change="$emit('updateStatus', row.id, $event.target.value)"
              >
                <option value="draft">Draft</option>
                <option value="submitted">Submitted</option>
              </select>
            </template>
            <template v-else-if="col === 'status'">
              <span
                :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(row.status)]"
              >
                {{ row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : '—' }}
              </span>
            </template>
            <template v-else-if="col === 'field_agent'">
              <span
                :class="row[col] === 'Unassigned' ? 'text-red-600' : 'font-semibold text-gray-900'"
              >
                {{ formatValue(row, col) }}
              </span>
            </template>
            <template v-else-if="col === 'field_status'">
              <span
                v-if="row[col]"
                :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', fieldStatusBadgeClass(row[col])]"
              >
                {{ row[col] }}
              </span>
              <span v-else>—</span>
            </template>
            <template v-else-if="col === 'sla_timer'">
              <span :class="slaTimerClass(row.sla_timer, row.sla_status)">
                {{ row.sla_timer ?? '—' }}
              </span>
            </template>
            <template v-else-if="['target_date', 'last_updated', 'submitted_at'].includes(col)">
              {{ row[col] ?? '—' }}
            </template>
            <template v-else-if="col === 'sla_status'">
              {{ row[col] ?? '—' }}
            </template>
            <template v-else-if="['created_at'].includes(col)">
              {{ formatDate(row[col]) }}
            </template>
            <template v-else-if="['company_name', 'product', 'emirates'].includes(col)">
              {{ truncate(formatValue(row, col), 18) }}
            </template>
            <template v-else-if="col === 'complete_address'">
              {{ truncate(formatValue(row, col), 25) }}
            </template>
            <template v-else>
              {{ formatValue(row, col) }}
            </template>
          </td>
          <td class="px-4 py-3 text-right">
            <div class="inline-flex items-center gap-2">
              <button
                type="button"
                class="rounded-full p-1.5 text-blue-600 hover:bg-blue-50"
                title="View"
                @click="goToView(row)"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </button>
              <button
                type="button"
                class="rounded-full p-1.5 text-green-600 hover:bg-green-50"
                title="Edit"
                @click="goToEdit(row)"
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
