<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const MONTHS_3 = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
function formatDateTime(val) {
  if (!val || typeof val !== 'string') return '—'
  const date = new Date(val)
  if (isNaN(date.getTime())) return val
  const dd = String(date.getDate()).padStart(2, '0')
  const mon = MONTHS_3[date.getMonth()]
  const yyyy = date.getFullYear()
  const hh = String(date.getHours()).padStart(2, '0')
  const mm = String(date.getMinutes()).padStart(2, '0')
  return `${dd}-${mon}-${yyyy} ${hh}:${mm}`
}

function formatDateOnly(val) {
  if (!val || typeof val !== 'string') return '—'
  const date = new Date(val)
  if (isNaN(date.getTime())) return val
  const dd = String(date.getDate()).padStart(2, '0')
  const mon = MONTHS_3[date.getMonth()]
  const yyyy = date.getFullYear()
  return `${dd}-${mon}-${yyyy}`
}

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  sort: { type: String, default: 'submitted_at' },
  order: { type: String, default: 'desc' },
  loading: { type: Boolean, default: false },
  currentPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 15 },
  editOptions: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['sort', 'updateCell', 'viewHistory'])

const router = useRouter()
const auth = useAuthStore()

const columnLabels = {
  id: 'ID',
  company_name: 'Company Name',
  account_number: 'Account Number',
  submitted_at: 'Created',
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
  other: 'Offer',
  migration_numbers: 'Migration Numbers',
  activity: 'Activity',
  wo_number: 'Work Order',
  completion_date: 'Completion Date',
  payment_connection: 'Payment Connection',
  contract_type: 'Contract Type Term',
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

const READ_ONLY_COLUMNS = ['id', 'submitted_at', 'creator']

const DROPDOWN_COLUMNS = ['status', 'manager', 'team_leader', 'sales_agent', 'service_type', 'product_type', 'payment_connection', 'contract_type']

const DATE_COLUMNS = ['completion_date', 'contract_end_date']

const STATUS_OPTIONS = [
  { value: 'Normal', label: 'Normal' },
  { value: 'Churn', label: 'Churn' },
  { value: 'Clawback', label: 'Clawback' },
]

const SERVICE_TYPE_OPTIONS = [
  { value: 'Voice', label: 'Voice' },
  { value: 'Internet', label: 'Internet' },
  { value: 'IPTV', label: 'IPTV' },
  { value: 'Managed Services', label: 'Managed Services' },
  { value: 'Cloud', label: 'Cloud' },
  { value: 'Security', label: 'Security' },
]

const PRODUCT_TYPE_OPTIONS = [
  { value: 'SIP Trunk', label: 'SIP Trunk' },
  { value: 'PRI', label: 'PRI' },
  { value: 'Broadband', label: 'Broadband' },
  { value: 'Dedicated Internet', label: 'Dedicated Internet' },
  { value: 'MPLS', label: 'MPLS' },
  { value: 'Hosted PBX', label: 'Hosted PBX' },
  { value: 'SD-WAN', label: 'SD-WAN' },
  { value: 'Firewall', label: 'Firewall' },
]

const PAYMENT_CONNECTION_OPTIONS = [
  { value: 'Paid', label: 'Paid' },
  { value: 'Unpaid', label: 'Unpaid' },
  { value: 'Partial', label: 'Partial' },
]

const CONTRACT_TYPE_OPTIONS = [
  { value: '12 months', label: '12 months' },
  { value: '24 months', label: '24 months' },
]

function calcContractEndDate(months) {
  const now = new Date()
  const end = new Date(now.getFullYear(), now.getMonth() + months, now.getDate())
  return end.toISOString().slice(0, 10)
}

const editingCell = ref(null)
const inlineEditValue = ref('')
const inlineEditError = ref('')

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
  if (col === 'submitted_at') return formatDateTime(val)
  if (col === 'contract_end_date' || col === 'completion_date') return formatDateOnly(val)
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

function isReadOnly(col) {
  return READ_ONLY_COLUMNS.includes(col)
}

function isDropdownColumn(col) {
  return DROPDOWN_COLUMNS.includes(col)
}

function isDateColumn(col) {
  return DATE_COLUMNS.includes(col)
}

function isInputColumn(col) {
  return !isReadOnly(col) && !isDropdownColumn(col) && !isDateColumn(col)
}

function isEditing(rowId, col) {
  return editingCell.value?.rowId === rowId && editingCell.value?.col === col
}

function getCellValueForEdit(row, col) {
  if (col === 'manager') return row.manager_id != null ? String(row.manager_id) : ''
  if (col === 'team_leader') return row.team_leader_id != null ? String(row.team_leader_id) : ''
  if (col === 'sales_agent') return row.sales_agent_id != null ? String(row.sales_agent_id) : ''
  if (col === 'status') return row.status ?? ''
  if (col === 'service_type') return row.service_type ?? ''
  if (col === 'product_type') return row.product_type ?? ''
  if (col === 'payment_connection') return row.payment_connection ?? ''
  if (col === 'contract_type') return row.contract_type ?? ''
  if (col === 'completion_date') return row._raw_completion_date ?? row.completion_date ?? ''
  if (col === 'contract_end_date') return row._raw_contract_end_date ?? row.contract_end_date ?? ''
  const val = row[col]
  if (val == null) return ''
  if (typeof val === 'object') return val?.name ?? ''
  return String(val)
}

function getOptionsForColumn(col) {
  if (col === 'status') return STATUS_OPTIONS
  if (col === 'service_type') return SERVICE_TYPE_OPTIONS
  if (col === 'product_type') return PRODUCT_TYPE_OPTIONS
  if (col === 'payment_connection') return PAYMENT_CONNECTION_OPTIONS
  if (col === 'contract_type') return CONTRACT_TYPE_OPTIONS
  if (col === 'manager') return (props.editOptions.managers ?? []).map(u => ({ value: String(u.id), label: u.name }))
  if (col === 'team_leader') return (props.editOptions.team_leaders ?? []).map(u => ({ value: String(u.id), label: u.name }))
  if (col === 'sales_agent') return (props.editOptions.sales_agents ?? []).map(u => ({ value: String(u.id), label: u.name }))
  return []
}

function openDropdownEdit(row, col) {
  editingCell.value = { rowId: row.id, col }
  inlineEditValue.value = getCellValueForEdit(row, col)
  inlineEditError.value = ''
}

function openInputEdit(row, col) {
  editingCell.value = { rowId: row.id, col }
  inlineEditValue.value = getCellValueForEdit(row, col)
  inlineEditError.value = ''
}

function saveInlineEdit() {
  if (!editingCell.value) return
  const { rowId, col } = editingCell.value
  let value = inlineEditValue.value

  let err = null
  if (col === 'company_name') {
    if (!value || !String(value).trim()) err = 'Company name is required.'
  } else if (col === 'status') {
    if (!value || !String(value).trim()) err = 'Status is required.'
  } else if (col === 'account_number') {
    if (!value || !String(value).trim()) err = 'Account number is required.'
  } else if (col === 'quantity' || col === 'renewal_alert') {
    if (value !== '' && value !== null) {
      const n = Number(value)
      if (isNaN(n) || n < 0) err = 'Must be a non-negative number.'
      if (!Number.isInteger(n)) err = 'Must be a whole number.'
      else value = n
    } else {
      value = null
    }
  } else if (col === 'mrc') {
    if (value !== '' && value !== null) {
      const n = Number(value)
      if (isNaN(n) || n < 0) err = 'MRC must be a valid non-negative number.'
    }
  } else if (col === 'manager') {
    if (!value) err = 'Manager is required.'
  } else if (col === 'team_leader') {
    if (!value) err = 'Team Leader is required.'
  } else if (col === 'sales_agent') {
    if (!value) err = 'Sales Agent is required.'
  } else if (col === 'service_type') {
    if (!value) err = 'Service Type is required.'
  } else if (col === 'product_type') {
    if (!value) err = 'Product Type is required.'
  }

  if (err) {
    inlineEditError.value = err
    return
  }

  if (DATE_COLUMNS.includes(col)) value = value || null

  let apiField = col
  if (col === 'manager') { apiField = 'manager_id'; value = value ? Number(value) : null }
  else if (col === 'team_leader') { apiField = 'team_leader_id'; value = value ? Number(value) : null }
  else if (col === 'sales_agent') { apiField = 'sales_agent_id'; value = value ? Number(value) : null }

  if (col === 'contract_type' && value) {
    const months = parseInt(value)
    if (months) {
      const endDate = calcContractEndDate(months)
      emit('updateCell', rowId, 'contract_type', value)
      emit('updateCell', rowId, 'contract_end_date', endDate)
      editingCell.value = null
      inlineEditError.value = ''
      return
    }
  }

  emit('updateCell', rowId, apiField, value)
  editingCell.value = null
  inlineEditError.value = ''
}

function cancelInlineEdit() {
  editingCell.value = null
  inlineEditError.value = ''
}

function onCellInteract(e, row, col) {
  if (isReadOnly(col)) return
  if (isDropdownColumn(col)) {
    e.stopPropagation()
    openDropdownEdit(row, col)
  }
}

function onCellDblClick(e, row, col) {
  if (isReadOnly(col)) return
  if (!isDropdownColumn(col)) {
    e.stopPropagation()
    openInputEdit(row, col)
  }
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
        <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
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
          class="border-b border-black bg-white hover:bg-gray-50/50"
        >
          <td
            v-for="col in columns"
            :key="col"
            class="whitespace-nowrap px-4 py-3 text-sm text-gray-900"
            :title="cellTitle(row, col)"
            @click="onCellInteract($event, row, col)"
            @dblclick="onCellDblClick($event, row, col)"
          >
            <!-- Dropdown edit mode -->
            <template v-if="isEditing(row.id, col) && isDropdownColumn(col)">
              <div class="flex flex-col gap-1.5" @click.stop>
                <select
                  v-model="inlineEditValue"
                  class="w-full min-w-[160px] max-w-[220px] rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                >
                  <option value="">Select</option>
                  <option v-for="o in getOptionsForColumn(col)" :key="String(o.value)" :value="o.value">{{ o.label }}</option>
                </select>
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700" @click="saveInlineEdit">Save</button>
                </div>
              </div>
            </template>

            <!-- Date input edit mode -->
            <template v-else-if="isEditing(row.id, col) && isDateColumn(col)">
              <div class="flex flex-col gap-1.5" @click.stop>
                <input
                  v-model="inlineEditValue"
                  type="date"
                  class="w-full min-w-[160px] max-w-[220px] rounded border bg-white px-3 py-1.5 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  :class="inlineEditError ? 'border-red-500' : 'border-gray-300'"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                />
                <p v-if="inlineEditError" class="text-xs text-red-600">{{ inlineEditError }}</p>
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700" @click="saveInlineEdit">Save</button>
                </div>
              </div>
            </template>

            <!-- Text/number input edit mode -->
            <template v-else-if="isEditing(row.id, col) && isInputColumn(col)">
              <div class="flex flex-col gap-1.5" @click.stop>
                <input
                  v-model="inlineEditValue"
                  :type="col === 'quantity' || col === 'renewal_alert' ? 'number' : 'text'"
                  class="w-full min-w-[160px] max-w-[220px] rounded border bg-white px-3 py-1.5 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  :class="inlineEditError ? 'border-red-500' : 'border-gray-300'"
                  @input="inlineEditError = ''"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                />
                <p v-if="inlineEditError" class="text-xs text-red-600">{{ inlineEditError }}</p>
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700" @click="saveInlineEdit">Save</button>
                </div>
              </div>
            </template>

            <!-- Display mode -->
            <template v-else>
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
            </template>
          </td>
          <td class="whitespace-nowrap border-r border-gray-200 px-4 py-3 text-right text-sm last:border-r-0" @click.stop>
            <div class="inline-flex items-center gap-2">
              <router-link
                :to="`/clients/${row.id}?tab=products-services`"
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
