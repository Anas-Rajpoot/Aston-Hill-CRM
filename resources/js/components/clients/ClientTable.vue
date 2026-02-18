<script setup>
/**
 * Clients table – sortable headers, green header, white body. No inline edit (add/edit/detail later).
 */
import { useRouter } from 'vue-router'

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  sort: { type: String, default: 'submitted_at' },
  order: { type: String, default: 'desc' },
  loading: { type: Boolean, default: false },
  currentPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 15 },
})

const emit = defineEmits(['sort', 'viewHistory'])

const router = useRouter()

const columnLabels = {
  id: 'ID',
  company_name: 'Company Name',
  account_number: 'Account Number',
  submitted_at: 'Submission Date',
  manager: 'Manager Name',
  team_leader: 'Team Leader',
  sales_agent: 'Sales Agent Name',
  status: 'Status',
  service_type: 'Service Type',
  product_type: 'Product Type',
  address: 'Address',
  product_name: 'Product Name',
  mrc: 'MRC',
  quantity: 'Quantity',
  other: 'Other',
  migration_numbers: 'Migration Numbers',
  wo_number: 'Work Order',
  completion_date: 'Completion Date',
  payment_connection: 'Payment Connection',
  contract_type: 'Contract Type',
  contract_end_date: 'Contract End Date',
  renewal_alert: 'Renewal Alert',
  additional_notes: 'Additional Notes',
  creator: 'Created By',
}

const SORTABLE_COLUMNS = [
  'company_name', 'account_number', 'submitted_at',
  'manager', 'team_leader', 'sales_agent', 'status',
  'service_type', 'product_type', 'product_name', 'mrc', 'quantity',
  'wo_number', 'completion_date',
  'contract_type', 'contract_end_date', 'renewal_alert', 'creator',
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
  if (typeof val === 'object') return val?.name ?? '—'
  return val
}

const TRUNCATE_LENGTH = 30

function truncate(str, max = TRUNCATE_LENGTH) {
  if (str == null || str === '') return '—'
  const s = String(str)
  return s.length > max ? s.slice(0, max) + '...' : s
}

function cellTitle(row, col) {
  const val = formatValue(row, col)
  return val == null || val === '—' ? '' : String(val)
}

const STATUS_BADGES = {
  pending: 'bg-gray-100 text-gray-700',
  in_progress: 'bg-blue-100 text-blue-700',
  completed: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-700',
}

function statusBadgeClass(status) {
  return STATUS_BADGES[status] ?? 'bg-gray-100 text-gray-700'
}

function goToDetail(row) {
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
        <span class="text-sm font-medium text-gray-600">Loading...</span>
      </div>
    </div>

    <table class="min-w-full border-2 border-black border-collapse">
      <thead>
        <tr class="border-b-2 border-black bg-green-600">
          <th
            v-for="col in columns"
            :key="col"
            scope="col"
            class="whitespace-nowrap px-4 py-3 text-left text-sm font-bold capitalize text-white"
          >
            <button
              v-if="sortable(col)"
              type="button"
              class="inline-flex items-center gap-1 font-bold text-white hover:text-white/90"
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
            <span v-else class="font-bold text-white">{{ label(col) }}</span>
          </th>
          <th scope="col" class="whitespace-nowrap px-4 py-3 text-center text-sm font-bold capitalize text-white">
            Actions
          </th>
        </tr>
      </thead>
      <tbody class="bg-white">
        <tr v-if="!loading && !data.length" class="border-b border-black bg-white">
          <td :colspan="columns.length + 1" class="px-4 py-12 text-center text-gray-500">
            No clients found.
          </td>
        </tr>
        <tr
          v-for="(row, rowIndex) in data"
          :key="row.id"
          class="border-b border-black bg-white hover:bg-gray-50/50 cursor-pointer"
          @click="goToDetail(row)"
        >
          <td
            v-for="col in columns"
            :key="col"
            class="whitespace-nowrap px-4 py-3 text-sm text-gray-900"
            :title="cellTitle(row, col)"
          >
            <template v-if="col === 'status'">
              <span
                :class="['inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap', statusBadgeClass(row.status)]"
              >
                {{ row.status ? (row.status.charAt(0).toUpperCase() + row.status.slice(1).replace('_', ' ')) : '—' }}
              </span>
            </template>
            <template v-else>
              {{ truncate(formatValue(row, col)) }}
            </template>
          </td>
          <td class="whitespace-nowrap border-r border-gray-200 px-4 py-3 text-right text-sm last:border-r-0" @click.stop>
            <div class="inline-flex items-center gap-2">
              <router-link
                :to="`/clients/${row.id}`"
                class="text-green-600 hover:text-green-800 font-medium"
              >
                View
              </router-link>
              <button
                type="button"
                class="rounded-full p-1.5 text-amber-600 hover:bg-amber-50"
                title="View History"
                @click="$emit('viewHistory', row)"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
