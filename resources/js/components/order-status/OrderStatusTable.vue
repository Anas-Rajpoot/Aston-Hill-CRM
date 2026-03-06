<script setup>
/**
 * Order Status table – Activity (green link), Account Number, Work Order, Work Order Status (badge), Activation Date, Remarks. Sortable.
 */
import { useRouter } from 'vue-router'
import { toDdMonYyyy } from '@/lib/dateFormat'

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  sort: { type: String, default: 'submitted_at' },
  order: { type: String, default: 'desc' },
  loading: { type: Boolean, default: false },
  canViewAction: { type: Boolean, default: true },
})

const emit = defineEmits(['sort'])

const router = useRouter()

const columnLabels = {
  activity: 'Activity',
  id: 'ID',
  company_name: 'Company Name',
  account_number: 'Account Number',
  submitted_at: 'Activation Date',
  manager: 'Manager Name',
  team_leader: 'Team Leader',
  sales_agent: 'Sales Agent Name',
  status: 'Work Order Status',
  service_type: 'Service Type',
  product_type: 'Product Type',
  address: 'Address',
  product_name: 'Product Name',
  mrc: 'MRC',
  quantity: 'Quantity',
  other: 'Activity Notes',
  migration_numbers: 'Migration Numbers',
  wo_number: 'Work Order',
  completion_date: 'Completion Date',
  payment_connection: 'Payment Connection',
  contract_type: 'Contract Type',
  contract_end_date: 'Contract End Date',
  renewal_alert: 'Renewal Alert',
  additional_notes: 'Remarks',
  creator: 'Created By',
}

const SORTABLE_COLUMNS = [
  'company_name', 'account_number', 'submitted_at',
  'manager', 'team_leader', 'sales_agent', 'status',
  'service_type', 'product_type', 'product_name', 'mrc', 'quantity',
  'wo_number', 'completion_date', 'contract_end_date', 'renewal_alert', 'creator',
]

function label(col) {
  return columnLabels[col] ?? col
}

function sortable(col) {
  if (col === 'activity') return false
  return SORTABLE_COLUMNS.includes(col)
}

function toggleSort(col) {
  if (!sortable(col)) return
  const nextOrder = props.sort === col && props.order === 'asc' ? 'desc' : 'asc'
  emit('sort', { sort: col, order: nextOrder })
}

function activityDisplay(row) {
  const year = row.submitted_at
    ? new Date(row.submitted_at).getFullYear()
    : new Date().getFullYear()
  return `ACT-${year}-${String(row.id ?? 0).padStart(3, '0')}`
}

function formatValue(row, col) {
  if (col === 'activity') return activityDisplay(row)
  const val = row[col]
  if (val == null || val === '') return '—'
  if (typeof val === 'object') return val?.name ?? '—'
  return val
}

function activationDateDisplay(row) {
  const d = row.submitted_at
  if (!d) return '—'
  const str = typeof d === 'string' ? d.trim().slice(0, 10) : ''
  if (!str) return '—'
  return toDdMonYyyy(str) || str
}

const TRUNCATE_LENGTH = 40

function truncate(str, max = TRUNCATE_LENGTH) {
  if (str == null || str === '') return '—'
  const s = String(str)
  return s.length > max ? s.slice(0, max) + '...' : s
}

const STATUS_BADGES = {
  pending: 'bg-amber-100 text-amber-800',
  on_hold: 'bg-gray-100 text-gray-700',
  in_progress: 'bg-brand-primary-light text-brand-primary-hover',
  completed: 'bg-brand-primary-light text-brand-primary-hover',
  cancelled: 'bg-red-100 text-red-700',
  failed: 'bg-red-100 text-red-700',
}

function statusBadgeClass(status) {
  if (!status) return 'bg-gray-100 text-gray-700'
  const s = String(status).toLowerCase().replace(/\s/g, '_')
  return STATUS_BADGES[s] ?? 'bg-gray-100 text-gray-700'
}

function statusLabel(status) {
  if (!status) return '—'
  return String(status).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function goToClient(row) {
  if (row?.id) router.push(`/clients/${row.id}`)
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
        <svg class="h-8 w-8 animate-spin text-brand-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
        <span class="text-sm font-medium text-gray-600">Loading...</span>
      </div>
    </div>

    <table class="min-w-full border-2 border-black border-collapse">
      <thead>
        <tr class="bg-brand-primary border-b-2 border-green-700">
          <th
            v-for="col in columns"
            :key="col"
            scope="col"
            class="whitespace-nowrap px-4 py-3 text-left text-sm font-bold text-white cursor-pointer select-none"
            @click="sortable(col) ? toggleSort(col) : null"
          >
            <button
              v-if="sortable(col)"
              type="button"
              class="inline-flex items-center gap-1 font-bold text-white hover:text-white/70"
              @click.stop="toggleSort(col)"
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
            <span v-else class="font-bold text-white">{{ label(col) }}</span>
          </th>
        </tr>
      </thead>
      <tbody class="bg-white">
        <tr v-if="!loading && !data.length" class="border-b border-black bg-white">
          <td :colspan="columns.length" class="px-4 py-12 text-center text-gray-500">
            No orders found.
          </td>
        </tr>
        <tr
          v-for="(row, rowIndex) in data"
          :key="row.id"
          class="border-b border-black bg-white hover:bg-gray-50/50"
        >
          <td
            v-for="col in columns"
            :key="col"
            class="whitespace-nowrap px-4 py-3 text-sm text-gray-900"
          >
            <template v-if="col === 'activity'">
              <router-link
                v-if="canViewAction"
                :to="`/clients/${row.id}`"
                class="font-medium text-brand-primary hover:text-brand-primary-hover hover:underline"
                @click.stop
              >
                {{ activityDisplay(row) }}
              </router-link>
              <span v-else class="text-gray-500">{{ activityDisplay(row) }}</span>
            </template>
            <template v-else-if="col === 'status'">
              <span
                :class="['inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap', statusBadgeClass(row.status)]"
              >
                {{ statusLabel(row.status) }}
              </span>
            </template>
            <template v-else-if="col === 'submitted_at'">
              {{ activationDateDisplay(row) }}
            </template>
            <template v-else>
              {{ truncate(formatValue(row, col)) }}
            </template>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
