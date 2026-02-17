<script setup>
/**
 * Field Submissions table – sortable headers, inline edit: dropdown on click, input on double-click.
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
  /** Selected row IDs (for bulk assign). */
  selectedIds: { type: Array, default: () => [] },
  currentPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 10 },
  /** Options for dropdowns: managers, teamLeaders, salesAgents, field_executives, field_statuses. */
  editOptions: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['sort', 'updateStatus', 'assignTechnician', 'update:selectedIds', 'updateCell', 'viewHistory'])
const router = useRouter()
const auth = useAuthStore()
const perms = computed(() => auth.user?.permissions ?? [])
const canEdit = computed(() => perms.value.includes('field_head.view') || perms.value.includes('field_head.list'))

/** Who can inline-edit rows: superadmin, field_head, field_agent, back_office. */
const canInlineEdit = computed(() => {
  if (canEdit.value) return true
  const roles = auth.user?.roles ?? []
  if (!Array.isArray(roles)) return false
  const hasRole = (name) => roles.some((r) => (typeof r === 'string' ? r : r?.name) === name)
  return hasRole('superadmin') || hasRole('field_agent') || hasRole('back_office') || hasRole('backoffice')
})

/** Superadmin or field_agent role can click "Unassigned" to open assign field technician pop-up. */
const canOpenAssignFieldAgent = computed(() => {
  const roles = auth.user?.roles ?? []
  if (!Array.isArray(roles)) return canEdit.value
  const hasRole = (name) => roles.some((r) => (typeof r === 'string' ? r : r?.name) === name)
  return hasRole('superadmin') || hasRole('field_agent') || canEdit.value
})

function goToEdit(row) {
  if (row?.id) router.push(`/field-submissions/${row.id}/edit`)
}

function goToView(row) {
  if (row?.id) router.push(`/field-submissions/${row.id}`)
}

function rowNumber(index) {
  return (props.currentPage - 1) * props.perPage + index + 1
}

const columnLabels = {
  id: '#',
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
  'target_date', 'sla_timer', 'sla_status', 'last_updated', 'creator',
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

/** Single row per cell: 30 chars + "..." and full value on hover. */
const TRUNCATE_LENGTH = 30
function truncate(str, max = TRUNCATE_LENGTH) {
  if (!str || typeof str !== 'string') return '—'
  const s = String(str)
  return s.length > max ? s.slice(0, max) + '...' : s
}

/** Full value for tooltip (title); use with truncate() for display. */
function fullValue(row, col) {
  if (col === 'target_date' || col === 'last_updated' || col === 'submitted_at') return row[col] ?? ''
  if (col === 'created_at') return formatDate(row[col]) || ''
  const val = formatValue(row, col)
  if (val == null || val === '—') return ''
  return String(val)
}

/** Columns that display text and should be truncated to 30 chars with title tooltip. */
const TRUNCATE_COLUMNS = [
  'company_name', 'contact_number', 'product', 'emirates', 'complete_address',
  'sales_agent', 'team_leader', 'manager', 'field_agent', 'creator',
  'status', 'field_status', 'sla_timer', 'sla_status', 'target_date', 'last_updated', 'submitted_at', 'created_at',
]
function shouldTruncate(col) {
  return TRUNCATE_COLUMNS.includes(col)
}
function cellTitle(row, col) {
  if (col === 'id' || !shouldTruncate(col)) return undefined
  const full = fullValue(row, col)
  return full || undefined
}

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

/** Inline edit: dropdown (click) vs input (double-click). */
const editingCell = ref(null)
const inlineEditValue = ref('')

/** Same as field submission form: dropdowns for select fields, input for text/date. */
const DROPDOWN_COLUMNS = ['status', 'field_status', 'emirates', 'manager', 'team_leader', 'sales_agent', 'field_agent']
const READ_ONLY_COLUMNS = ['id', 'sla_timer', 'sla_status', 'creator', 'submitted_at', 'created_at', 'last_updated']

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
  if (col === 'field_agent') return row.field_executive_id ?? ''
  if (col === 'field_status') return row.field_status ?? ''
  if (col === 'target_date') {
    const v = row.meeting_date ?? row.target_date
    if (!v) return ''
    const d = new Date(v)
    if (Number.isNaN(d.getTime())) return ''
    return d.toISOString().slice(0, 10)
  }
  return row[col] != null ? String(row[col]) : ''
}

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
  if (col === 'status') {
    emit('updateStatus', rowId, value)
    editingCell.value = null
    return
  }
  if (col === 'target_date') value = value || null
  if (['manager', 'team_leader', 'sales_agent', 'field_agent'].includes(col)) {
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
    case 'emirates':
      return (opt.emirates || []).map((e) => ({ value: typeof e === 'string' ? e : e.value, label: typeof e === 'string' ? e : (e.label || e.value) }))
    case 'manager':
      return (opt.managers || []).map((m) => ({ value: m.id, label: m.name }))
    case 'team_leader':
      return (opt.teamLeaders || []).map((t) => ({ value: t.id, label: t.name }))
    case 'sales_agent':
      return (opt.salesAgents || []).map((s) => ({ value: s.id, label: s.name }))
    case 'field_agent':
      return [{ value: null, label: 'Unassigned' }, ...(opt.field_executives || []).map((e) => ({ value: e.id, label: e.name }))]
    case 'field_status':
      return (opt.field_statuses || []).map((s) => ({ value: s.value, label: s.label || s.value }))
    default:
      return []
  }
}

function isEditing(rowId, col) {
  return editingCell.value && editingCell.value.rowId === rowId && editingCell.value.col === col
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
          <th scope="col" class="whitespace-nowrap px-4 py-3 text-right text-sm font-bold uppercase tracking-wider text-white">
            Actions
          </th>
        </tr>
      </thead>
      <tbody class="bg-white">
        <tr v-if="!loading && !data.length" class="border-b border-black bg-white">
          <td :colspan="columns.length + 2" class="px-4 py-12 text-center text-gray-500">
            No field submissions found.
          </td>
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
            <!-- Dropdown edit (click or double-click to open) -->
            <template v-if="canInlineEdit && isEditing(row.id, col) && isDropdownColumn(col)">
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
            <!-- Input edit (double-click to open) – save on Save button only -->
            <template v-else-if="canInlineEdit && isEditing(row.id, col) && isInputColumn(col)">
              <div class="flex flex-col gap-1.5">
                <input
                  v-model="inlineEditValue"
                  :type="col === 'target_date' ? 'date' : 'text'"
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
            <template v-else-if="col === 'id'">
              {{ rowNumber(rowIndex) }}
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
              <span
                :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(row.status)]"
              >
                {{ row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : '—' }}
              </span>
            </template>
            <template v-else-if="col === 'field_agent' && canOpenAssignFieldAgent && row[col] === 'Unassigned'">
              <button
                type="button"
                class="cursor-pointer text-red-600 underline hover:text-red-700"
                @click="$emit('assignTechnician', row)"
              >
                Unassigned
              </button>
            </template>
            <template v-else-if="col === 'field_agent' && canInlineEdit">
              <span
                class="cursor-pointer hover:bg-gray-100 rounded px-0.5 font-semibold text-gray-900"
                @click="openDropdownEdit(row, col)"
                @dblclick="openDropdownEdit(row, col)"
              >{{ truncate(formatValue(row, col)) }}</span>
            </template>
            <template v-else-if="col === 'field_agent' && canOpenAssignFieldAgent">
              <span
                class="cursor-pointer hover:bg-gray-100 rounded px-0.5 font-semibold text-gray-900"
                @click="$emit('assignTechnician', row)"
              >{{ truncate(formatValue(row, col)) }}</span>
            </template>
            <template v-else-if="col === 'field_agent'">
              <span :class="row[col] === 'Unassigned' ? 'text-red-600' : 'font-semibold text-gray-900'">
                {{ truncate(formatValue(row, col)) }}
              </span>
            </template>
            <template v-else-if="canInlineEdit && isDropdownColumn(col)">
              <span
                class="cursor-pointer hover:bg-gray-100 rounded px-0.5 min-w-0 inline-block max-w-full truncate"
                @click="openDropdownEdit(row, col)"
                @dblclick="openDropdownEdit(row, col)"
              >{{ truncate(formatValue(row, col)) }}</span>
            </template>
            <template v-else-if="col === 'field_status'">
              <span
                v-if="row[col]"
                :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap', fieldStatusBadgeClass(row[col])]"
              >
                {{ truncate(row[col]) }}
              </span>
              <span v-else>—</span>
            </template>
            <template v-else-if="col === 'sla_timer'">
              <span :class="slaTimerClass(row.sla_timer, row.sla_status)">
                {{ row.sla_timer ?? '—' }}
              </span>
            </template>
            <template v-else-if="['last_updated', 'submitted_at'].includes(col)">
              {{ truncate(row[col] ?? '—') }}
            </template>
            <template v-else-if="col === 'sla_status'">
              {{ truncate(row[col] ?? '—') }}
            </template>
            <template v-else-if="col === 'created_at'">
              {{ formatDate(row[col]) }}
            </template>
            <template v-else-if="col === 'target_date'">
              <span
                v-if="canInlineEdit"
                class="cursor-text hover:bg-gray-50 rounded px-0.5"
                @dblclick="openInputEdit(row, col)"
              >{{ truncate(row[col] ?? '—') }}</span>
              <span v-else>{{ truncate(row[col] ?? '—') }}</span>
            </template>
            <template v-else-if="['company_name', 'product', 'contact_number', 'complete_address', 'sales_agent', 'team_leader', 'manager', 'creator'].includes(col)">
              <span
                v-if="canInlineEdit && isInputColumn(col)"
                class="cursor-text hover:bg-gray-50 rounded px-0.5"
                @dblclick="openInputEdit(row, col)"
              >{{ truncate(formatValue(row, col)) }}</span>
              <span
                v-else-if="canInlineEdit && isDropdownColumn(col)"
                class="cursor-pointer hover:bg-gray-100 rounded px-0.5 min-w-0 inline-block max-w-full truncate"
                @click="openDropdownEdit(row, col)"
                @dblclick="openDropdownEdit(row, col)"
              >{{ truncate(formatValue(row, col)) }}</span>
              <span v-else>{{ truncate(formatValue(row, col)) }}</span>
            </template>
            <template v-else>
              <span
                v-if="canInlineEdit && isInputColumn(col)"
                class="cursor-text hover:bg-gray-50 rounded px-0.5"
                @dblclick="openInputEdit(row, col)"
              >{{ truncate(formatValue(row, col)) }}</span>
              <span v-else>{{ truncate(formatValue(row, col)) }}</span>
            </template>
          </td>
          <td class="whitespace-nowrap px-4 py-3 text-right">
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
