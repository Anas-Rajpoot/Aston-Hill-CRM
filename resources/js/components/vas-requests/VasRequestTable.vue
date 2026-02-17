<script setup>
/**
 * VAS Request table – sortable headers, inline edit, same design as Field/Lead/Customer Support.
 */
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()

function goToView(row) {
  if (row?.id) router.push(`/vas-requests/${row.id}`)
}

function goToEdit(row) {
  if (row?.id) router.push(`/vas-requests/${row.id}/edit`)
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
  selectedIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['sort', 'updateCell', 'openAssign', 'update:selectedIds', 'viewHistory', 'resubmit'])

const auth = useAuthStore()
const canInlineEdit = computed(() => {
  const perms = auth.user?.permissions ?? []
  if (perms.includes('vas.edit')) return true
  const roles = auth.user?.roles ?? []
  if (!Array.isArray(roles)) return false
  const hasRole = (name) => roles.some((r) => (typeof r === 'string' ? r : r?.name) === name)
  return hasRole('superadmin') || hasRole('back_office') || hasRole('backoffice')
})

const canOpenAssign = computed(() => {
  const roles = auth.user?.roles ?? []
  if (!Array.isArray(roles)) return false
  return roles.some((r) => {
    const name = typeof r === 'string' ? r : r?.name
    return name === 'superadmin' || name === 'back_office' || name === 'backoffice'
  })
})

function rowNumber(index) {
  return (props.currentPage - 1) * props.perPage + index + 1
}

const columnLabels = {
  id: '#',
  submitted_at: 'Submission Date',
  created_at: 'Created',
  request_type: 'Request Type',
  account_number: 'Account Number',
  company_name: 'Company Name',
  description: 'Description',
  manager: 'Manager',
  team_leader: 'Team Leader',
  sales_agent: 'Sales Agent',
  executive: 'Back Office Executive',
  status: 'Status',
  creator: 'Created By',
}

const SORTABLE_COLUMNS = [
  'id', 'submitted_at', 'created_at', 'request_type', 'account_number', 'company_name',
  'description', 'manager', 'team_leader', 'sales_agent', 'executive', 'status', 'creator',
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
  if (!str || typeof str !== 'string') return '—'
  const s = String(str)
  return s.length > max ? s.slice(0, max) + '...' : s
}

function fullValue(row, col) {
  const val = formatValue(row, col)
  if (val == null || val === '—') return ''
  return String(val)
}

const TRUNCATE_COLUMNS = [
  'request_type', 'company_name', 'account_number', 'description',
  'manager', 'team_leader', 'sales_agent', 'executive', 'creator', 'status',
  'submitted_at', 'created_at',
]
function shouldTruncate(col) {
  return TRUNCATE_COLUMNS.includes(col)
}
function cellTitle(row, col) {
  if (col === 'id' || !shouldTruncate(col)) return undefined
  const full = fullValue(row, col)
  return full || undefined
}

const DROPDOWN_COLUMNS = ['status', 'request_type', 'manager', 'team_leader', 'sales_agent', 'executive']
const READ_ONLY_COLUMNS = ['id', 'creator', 'submitted_at', 'created_at']

const selectedSet = computed(() => new Set((props.selectedIds || []).map(String)))
const allRowIds = computed(() => props.data.map((r) => r.id))
const isAllSelected = computed(() => allRowIds.value.length > 0 && allRowIds.value.every((id) => selectedSet.value.has(String(id))))

function toggleSelectAll() {
  if (isAllSelected.value) {
    emit('update:selectedIds', [])
  } else {
    emit('update:selectedIds', [...allRowIds.value])
  }
}

function toggleRow(id) {
  const idStr = String(id)
  const next = new Set(selectedSet.value)
  if (next.has(idStr)) next.delete(idStr)
  else next.add(idStr)
  emit('update:selectedIds', Array.from(next).map(Number))
}

function isUnassigned(row) {
  return row.back_office_executive_id == null && (row.executive == null || row.executive === '')
}

function isDropdownColumn(col) {
  return DROPDOWN_COLUMNS.includes(col)
}
function isInputColumn(col) {
  return !READ_ONLY_COLUMNS.includes(col) && !DROPDOWN_COLUMNS.includes(col)
}

function getCellValueForEdit(row, col) {
  if (col === 'manager') return row.manager_id ?? ''
  if (col === 'team_leader') return row.team_leader_id ?? ''
  if (col === 'sales_agent') return row.sales_agent_id ?? ''
  if (col === 'executive') return row.back_office_executive_id ?? ''
  if (col === 'status') return row.status ?? ''
  return row[col] != null ? String(row[col]) : ''
}

const editingCell = ref(null)
const inlineEditValue = ref('')

function openDropdownEdit(row, col) {
  if (!canInlineEdit.value) return
  editingCell.value = { rowId: row.id, col }
  inlineEditValue.value = getCellValueForEdit(row, col)
}

function openInputEdit(row, col) {
  if (!canInlineEdit.value) return
  editingCell.value = { rowId: row.id, col }
  inlineEditValue.value = getCellValueForEdit(row, col)
}

function saveInlineEdit() {
  if (!editingCell.value) return
  const { rowId, col } = editingCell.value
  let value = inlineEditValue.value
  if (['manager', 'team_leader', 'sales_agent', 'executive'].includes(col)) {
    value = value === '' || value == null ? null : Number(value)
  }
  emit('updateCell', rowId, col, value)
  editingCell.value = null
}

function cancelInlineEdit() {
  editingCell.value = null
}

function getOptionsForColumn(col) {
  const opt = props.editOptions || {}
  switch (col) {
    case 'request_type':
      return (opt.request_types || []).map((t) => ({ value: typeof t === 'string' ? t : t.value, label: typeof t === 'string' ? t : (t.label || t.value) }))
    case 'status':
      return (opt.statuses || []).map((s) => ({ value: typeof s === 'string' ? s : s.value, label: typeof s === 'string' ? s : (s.label || s.value) }))
    case 'manager':
      return (opt.managers || []).map((m) => ({ value: m.id, label: m.name }))
    case 'team_leader':
      return (opt.team_leaders || []).map((t) => ({ value: t.id, label: t.name }))
    case 'sales_agent':
      return (opt.sales_agents || []).map((s) => ({ value: s.id, label: s.name }))
    case 'executive':
      return [{ value: null, label: '—' }, ...(opt.executives || []).map((s) => ({ value: s.id, label: s.name }))]
    default:
      return []
  }
}

function isEditing(rowId, col) {
  return editingCell.value && editingCell.value.rowId === rowId && editingCell.value.col === col
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
    <div
      v-if="loading"
      class="absolute inset-0 z-10 flex items-center justify-center bg-white/80"
      aria-live="polite"
      aria-busy="true"
    >
      <div class="flex flex-col items-center gap-2">
        <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
        <span class="text-sm font-medium text-gray-600">Updating...</span>
      </div>
    </div>

    <table class="min-w-full border-2 border-black border-collapse">
      <thead>
        <tr class="border-b-2 border-black bg-green-600">
          <th class="w-10 px-3 py-3 text-left">
            <input
              type="checkbox"
              class="rounded border-gray-300"
              aria-label="Select all"
              :checked="isAllSelected"
              :indeterminate="selectedSet.size > 0 && !isAllSelected"
              @change="toggleSelectAll"
            />
          </th>
          <th
            v-for="col in columns"
            :key="col"
            scope="col"
            class="whitespace-nowrap px-4 py-3 text-left text-sm font-bold uppercase tracking-wider text-white"
          >
            <button
              v-if="sortable(col)"
              type="button"
              class="inline-flex items-center gap-1 font-bold text-white hover:text-white/90"
              @click="toggleSort(col)"
            >
              {{ label(col) }}
              <svg v-if="sort === col" class="h-4 w-4" :class="order === 'asc' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
              </svg>
            </button>
            <span v-else class="font-bold text-white">{{ label(col) }}</span>
          </th>
          <th scope="col" class="whitespace-nowrap px-4 py-3 text-right text-sm font-bold uppercase tracking-wider text-white">Actions</th>
        </tr>
      </thead>
      <tbody class="bg-white">
        <tr v-if="!loading && !data.length" class="border-b border-black bg-white">
          <td :colspan="columns.length + 2" class="px-4 py-12 text-center text-gray-500">No VAS requests found.</td>
        </tr>
        <tr
          v-for="(row, rowIndex) in data"
          :key="row.id"
          class="border-b border-black bg-white hover:bg-gray-50/50"
        >
          <td class="w-10 px-3 py-3">
            <input
              type="checkbox"
              class="rounded border-gray-300"
              :checked="selectedSet.has(String(row.id))"
              aria-label="Select row"
              @change="toggleRow(row.id)"
            />
          </td>
          <td
            v-for="col in columns"
            :key="col"
            class="whitespace-nowrap px-4 py-3 text-sm text-gray-900"
            :class="{ 'cursor-pointer': canInlineEdit && isDropdownColumn(col) && !isEditing(row.id, col), 'cursor-text': canInlineEdit && isInputColumn(col) && !isEditing(row.id, col) }"
            :title="cellTitle(row, col)"
          >
            <template v-if="col === 'id'">
              {{ rowNumber(rowIndex) }}
            </template>
            <template v-else-if="canInlineEdit && isEditing(row.id, col) && isDropdownColumn(col)">
              <div class="flex flex-col gap-1.5">
                <select
                  v-model="inlineEditValue"
                  class="w-full min-w-[160px] max-w-[220px] rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                >
                  <option v-for="o in getOptionsForColumn(col)" :key="String(o.value)" :value="o.value">{{ o.label }}</option>
                </select>
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700" @click="saveInlineEdit">Save</button>
                </div>
              </div>
            </template>
            <template v-else-if="canInlineEdit && isEditing(row.id, col) && isInputColumn(col)">
              <div class="flex flex-col gap-1.5">
                <textarea
                  v-if="col === 'description'"
                  v-model="inlineEditValue"
                  rows="3"
                  class="w-full min-w-[180px] max-w-[280px] rounded border border-gray-300 bg-white px-2 py-1 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  @keydown.esc="cancelInlineEdit"
                />
                <input
                  v-else
                  v-model="inlineEditValue"
                  type="text"
                  class="w-full min-w-[100px] max-w-[220px] rounded border border-gray-300 bg-white px-2 py-1 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                />
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700" @click="saveInlineEdit">Save</button>
                </div>
              </div>
            </template>
            <template v-else-if="col === 'executive' && isUnassigned(row)">
              <button
                v-if="canOpenAssign"
                type="button"
                class="cursor-pointer text-red-600 underline hover:text-red-700"
                @click="$emit('openAssign', row)"
              >
                Unassigned
              </button>
              <span v-else class="text-red-600">Unassigned</span>
            </template>
            <template v-else-if="col === 'status' && canInlineEdit && isEditing(row.id, 'status')">
              <div class="flex flex-col gap-1.5">
                <select
                  v-model="inlineEditValue"
                  class="min-w-[160px] rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                >
                  <option value="draft">Draft</option>
                  <option value="submitted">Submitted</option>
                  <option value="approved">Approved</option>
                  <option value="rejected">Rejected</option>
                </select>
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700" @click="saveInlineEdit">Save</button>
                </div>
              </div>
            </template>
            <template v-else-if="col === 'status' && canInlineEdit">
              <span
                :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium cursor-pointer hover:ring-2 hover:ring-green-400', statusBadgeClass(row.status)]"
                @dblclick="openDropdownEdit(row, 'status')"
              >
                {{ row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : '—' }}
              </span>
            </template>
            <template v-else-if="col === 'status'">
              <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(row.status)]">
                {{ row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : '—' }}
              </span>
            </template>
            <template v-else-if="canInlineEdit && isDropdownColumn(col)">
              <span
                class="cursor-pointer hover:bg-gray-100 rounded px-0.5 min-w-0 inline-block max-w-full truncate"
                @click="openDropdownEdit(row, col)"
                @dblclick="openDropdownEdit(row, col)"
              >{{ truncate(formatValue(row, col)) }}</span>
            </template>
            <template v-else-if="canInlineEdit && isInputColumn(col)">
              <span class="cursor-text hover:bg-gray-50 rounded px-0.5" @dblclick="openInputEdit(row, col)">{{ truncate(formatValue(row, col)) }}</span>
            </template>
            <template v-else>
              {{ truncate(formatValue(row, col)) }}
            </template>
          </td>
          <td class="whitespace-nowrap px-4 py-3 text-right">
            <div class="inline-flex items-center gap-2">
              <button type="button" class="rounded-full p-1.5 text-blue-600 hover:bg-blue-50" title="View" @click="goToView(row)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </button>
              <button type="button" class="rounded-full p-1.5 text-green-600 hover:bg-green-50" title="Edit" @click="goToEdit(row)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                </svg>
              </button>
              <button type="button" class="rounded-full p-1.5 text-amber-600 hover:bg-amber-50" title="View History" @click="$emit('viewHistory', row)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </button>
              <button
                v-if="row.status === 'rejected'"
                type="button"
                class="rounded-full p-1.5 text-orange-600 hover:bg-orange-50"
                title="Resubmit"
                @click="$emit('resubmit', row)"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
