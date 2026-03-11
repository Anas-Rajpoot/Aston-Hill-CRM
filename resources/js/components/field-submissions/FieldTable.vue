<script setup>
/**
 * Field Submissions table – sortable headers, inline edit: dropdown on click, input on double-click.
 */
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { canModuleAction } from '@/lib/accessControl'
import { formatSystemDateTime } from '@/lib/dateFormat'

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

const emit = defineEmits(['sort', 'updateStatus', 'assignTechnician', 'update:selectedIds', 'updateCell', 'viewHistory', 'delete'])
const router = useRouter()
const auth = useAuthStore()
const canViewAction = computed(() => canModuleAction(auth.user, 'field', 'view'))
const canEditAction = computed(() => canModuleAction(auth.user, 'field', 'edit'))
const canHistoryAction = computed(() => canViewAction.value)
const canDeleteAction = computed(() => canModuleAction(auth.user, 'field', 'delete'))
const perms = computed(() => auth.user?.permissions ?? [])
const canEdit = computed(() => perms.value.includes('field_head.view') || perms.value.includes('field_head.list'))

/** Who can inline-edit rows: superadmin, field_head, field_agent, back_office. */
const canInlineEdit = computed(() => {
  if (canEditAction.value || canEdit.value) return true
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

const hasAnyRowAction = computed(() => {
  if (canViewAction.value || canEditAction.value || canHistoryAction.value || canDeleteAction.value) return true
  return false
})

const columnLabels = {
  id: 'SR',
  created_at: 'Created',
  account_number: 'Account Number',
  company_name: 'Company Name as per Trade License',
  authorized_signatory_name: 'Authorized Signatory Name',
  contact_number: 'Contact Number',
  alternate_number: 'Alternate Contact Number',
  product: 'Product',
  emirates: 'Emirates',
  location_coordinates: 'Location Coordinates',
  complete_address: 'Complete Address',
  additional_notes: 'Additional Notes',
  special_instruction: 'Special Instruction',
  sales_agent: 'Sales Agent',
  team_leader: 'Team Leader',
  manager: 'Manager',
  field_agent: 'Field Agent',
  status: 'Status',
  field_status: 'Field Status',
  target_date: 'Meeting Date',
  remarks_by_field_agent: 'Field Agent Remarks',
  sla_timer: 'SLA Timer',
  sla_status: 'SLA Status',
  last_updated: 'Last Updated',
  creator: 'Submitter Name',
}

const SORTABLE_COLUMNS = [
  'id', 'created_at', 'company_name', 'contact_number',
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
  return formatSystemDateTime(d, '—')
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
  if (col === 'target_date' || col === 'last_updated') return row[col] ?? ''
  if (col === 'created_at') return formatDate(row[col]) || ''
  const val = formatValue(row, col)
  if (val == null || val === '—') return ''
  return String(val)
}

/** Columns that display text and should be truncated to 30 chars with title tooltip. */
const TRUNCATE_COLUMNS = [
  'company_name', 'contact_number', 'product', 'emirates', 'complete_address',
  'sales_agent', 'team_leader', 'manager', 'field_agent', 'creator',
  'status', 'field_status', 'sla_timer', 'sla_status', 'target_date', 'last_updated', 'created_at',
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
  submitted: 'bg-brand-primary-light text-brand-primary-hover',
  unassigned: 'bg-amber-100 text-amber-800',
}

function formatStatusLabel(status) {
  if (!status) return '—'
  if (status === 'unassigned') return 'UnAssigned'
  return status.charAt(0).toUpperCase() + status.slice(1)
}

/** Badges for field workflow status (field_status) */
const FIELD_STATUS_BADGES = {
  'Meeting Scheduled': 'bg-brand-primary-light text-brand-primary-hover',
  'CM Cancelled': 'bg-red-100 text-red-700',
  'Meeting Done - Closed Documents Shared with Sales': 'bg-brand-primary-light text-brand-primary-hover',
  'Meeting Done - Closed CM will Share Documents': 'bg-brand-primary-light text-brand-primary-hover',
  'Meeting Done - Sales In Follow Up': 'bg-amber-100 text-amber-800',
  'Meeting Done - CM Not Interested': 'bg-red-100 text-red-700',
  'Field Executive In Follow Up': 'bg-amber-100 text-amber-800',
  'No Meeting Closed on Call': 'bg-gray-100 text-gray-700',
}

function statusBadgeClass(status) {
  return STATUS_BADGES[status] ?? 'bg-gray-100 text-gray-700'
}

function fieldStatusBadgeClass(fieldStatus) {
  return FIELD_STATUS_BADGES[fieldStatus] ?? 'bg-gray-100 text-gray-700'
}

function slaTimerClass(slaTimer, slaStatus) {
  if (!slaTimer) return 'text-gray-500'
  if (slaTimer === 'Completed' || slaStatus === 'Completed') return 'text-brand-primary font-medium'
  if (slaStatus === 'Breached') return 'text-red-600 font-medium'
  if (slaStatus === 'Approaching') return 'text-amber-600 font-medium'
  return 'text-brand-primary'
}

/** Inline edit: dropdown (click) vs input (double-click). */
const editingCell = ref(null)
const inlineEditValue = ref('')
const inlineEditError = ref('')

const PHONE_COLUMNS = ['contact_number', 'alternate_number']

function validatePhone(value, required = false) {
  if (!value || !value.trim()) return required ? 'Contact number is required.' : null
  if (/\s/.test(value)) return 'Must not contain spaces.'
  if (!/^\d+$/.test(value)) return 'Must contain only digits.'
  if (!value.startsWith('971')) return 'Must start with 971.'
  if (value.length !== 12) return 'Must be exactly 12 digits.'
  return null
}

function validateCoordinates(value) {
  if (!value || !value.trim()) return null
  const coordPattern = /^-?\d{1,3}(\.\d+)?\s*,\s*-?\d{1,3}(\.\d+)?$/
  const coords = value.trim()
  if (!coordPattern.test(coords)) return 'Enter valid coordinates (e.g. 25.2048, 55.2708).'
  const [lat, lng] = coords.split(',').map(s => parseFloat(s.trim()))
  if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return 'Latitude must be -90 to 90, longitude -180 to 180.'
  return null
}

function onPhoneInput(event) {
  inlineEditValue.value = event.target.value.replace(/\D/g, '')
  inlineEditError.value = ''
}

/** Same as field submission form: dropdowns for select fields, input for text/date. */
const DROPDOWN_COLUMNS = ['status', 'field_status', 'emirates', 'manager', 'team_leader', 'sales_agent', 'field_agent']
const READ_ONLY_COLUMNS = ['id', 'sla_timer', 'sla_status', 'creator', 'created_at', 'last_updated']

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
    const yyyy = d.getFullYear()
    const mm = String(d.getMonth() + 1).padStart(2, '0')
    const dd = String(d.getDate()).padStart(2, '0')
    const hh = String(d.getHours()).padStart(2, '0')
    const mi = String(d.getMinutes()).padStart(2, '0')
    const ss = String(d.getSeconds()).padStart(2, '0')
    return `${yyyy}-${mm}-${dd}T${hh}:${mi}:${ss}`
  }
  return row[col] != null ? String(row[col]) : ''
}

function openDropdownEdit(row, col) {
  if (!canInlineEdit.value) return
  editingCell.value = { rowId: row.id, col }
  inlineEditValue.value = getCellValueForEdit(row, col)
  inlineEditError.value = ''
}

function openInputEdit(row, col) {
  if (!canInlineEdit.value) return
  editingCell.value = { rowId: row.id, col }
  inlineEditValue.value = getCellValueForEdit(row, col)
  inlineEditError.value = ''
}

function saveInlineEdit() {
  if (!editingCell.value) return
  const { rowId, col } = editingCell.value
  let value = inlineEditValue.value
  if (PHONE_COLUMNS.includes(col)) {
    const isRequired = col === 'contact_number'
    const err = validatePhone(value, isRequired)
    if (err) {
      inlineEditError.value = err
      return
    }
  }
  if (col === 'location_coordinates') {
    const err = validateCoordinates(value)
    if (err) {
      inlineEditError.value = err
      return
    }
  }
  if (col === 'status') {
    emit('updateCell', rowId, 'field_status', value || null)
    editingCell.value = null
    inlineEditError.value = ''
    return
  }
  if (col === 'target_date') value = value || null
  if (['manager', 'team_leader', 'sales_agent', 'field_agent'].includes(col)) {
    value = value === '' || value == null ? null : Number(value)
  }
  emit('updateCell', rowId, col, value)
  editingCell.value = null
  inlineEditError.value = ''
}

function cancelInlineEdit() {
  editingCell.value = null
  inlineEditError.value = ''
}

function getOptionsForColumn(col) {
  const opt = props.editOptions || {}
  switch (col) {
    case 'status':
      return [
        { value: '', label: 'UnAssigned' },
        ...(opt.field_statuses || []).map((s) => ({ value: s.value, label: s.label || s.value })),
      ]
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
      return [
        { value: '', label: 'UnAssigned' },
        ...(opt.field_statuses || []).map((s) => ({ value: s.value, label: s.label || s.value })),
      ]
    default:
      return []
  }
}

function isEditing(rowId, col) {
  return editingCell.value && editingCell.value.rowId === rowId && editingCell.value.col === col
}

function displayStatusText(row) {
  return row?.field_status || 'UnAssigned'
}

function statusDisplayClass(row) {
  if (!row?.field_status) return 'bg-amber-100 text-amber-800'
  return fieldStatusBadgeClass(row.field_status)
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
          class="h-8 w-8 animate-spin text-brand-primary"
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
        <tr class="bg-brand-primary border-b-2 border-green-700">
          <th class="w-10 px-3 py-3">
            <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll()" class="rounded border-gray-300 text-brand-primary focus:ring-brand-primary" />
          </th>
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
          <th v-if="hasAnyRowAction" scope="col" class="whitespace-nowrap px-4 py-3 text-right text-sm font-bold text-white">
            Actions
          </th>
        </tr>
      </thead>
      <tbody class="bg-white">
        <tr v-if="!loading && !data.length" class="border-b border-black bg-white">
          <td :colspan="columns.length + (hasAnyRowAction ? 2 : 1)" class="px-4 py-12 text-center text-gray-500">
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
                  class="w-full min-w-[160px] max-w-[220px] rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                >
                  <option v-for="o in getOptionsForColumn(col)" :key="String(o.value)" :value="o.value">{{ o.label }}</option>
                </select>
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-brand-primary px-2 py-0.5 text-xs text-white hover:bg-brand-primary-hover" @click="saveInlineEdit">Save</button>
                </div>
              </div>
            </template>
            <!-- Input edit (double-click to open) – save on Save button only -->
            <template v-else-if="canInlineEdit && isEditing(row.id, col) && isInputColumn(col)">
              <div class="flex flex-col gap-1.5">
                <input
                  v-if="PHONE_COLUMNS.includes(col)"
                  :value="inlineEditValue"
                  type="text"
                  maxlength="12"
                  placeholder="971XXXXXXXXX"
                  class="w-full min-w-[100px] max-w-[220px] rounded border bg-white px-2 py-1 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                  :class="inlineEditError ? 'border-red-500' : 'border-gray-300'"
                  @input="onPhoneInput($event)"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                />
                <input
                  v-else-if="col === 'location_coordinates'"
                  v-model="inlineEditValue"
                  type="text"
                  placeholder="25.2048, 55.2708"
                  class="w-full min-w-[100px] max-w-[220px] rounded border bg-white px-2 py-1 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                  :class="inlineEditError ? 'border-red-500' : 'border-gray-300'"
                  @input="inlineEditError = ''"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                />
                <input
                  v-else
                  v-model="inlineEditValue"
                  :type="col === 'target_date' ? 'datetime-local' : 'text'"
                  :step="col === 'target_date' ? 1 : undefined"
                  class="w-full min-w-[100px] max-w-[220px] rounded border border-gray-300 bg-white px-2 py-1 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                />
                <p v-if="inlineEditError" class="text-xs text-red-600">{{ inlineEditError }}</p>
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-brand-primary px-2 py-0.5 text-xs text-white hover:bg-brand-primary-hover" @click="saveInlineEdit">Save</button>
                </div>
              </div>
            </template>
            <template v-else-if="col === 'id'">
              {{ rowNumber(rowIndex) }}
            </template>
            <template v-else-if="col === 'status' && canInlineEdit">
              <span
                :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium cursor-pointer hover:ring-2 hover:ring-brand-primary', statusDisplayClass(row)]"
                @dblclick="openDropdownEdit(row, 'status')"
              >
                {{ displayStatusText(row) }}
              </span>
            </template>
            <template v-else-if="col === 'status'">
              <span
                :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusDisplayClass(row)]"
              >
                {{ displayStatusText(row) }}
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
            <template v-else-if="col === 'last_updated'">
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
          <td v-if="hasAnyRowAction" class="whitespace-nowrap px-4 py-3 text-right">
            <div class="flex items-center justify-end gap-2">
              <div class="inline-flex items-center gap-1">
                <button
                  v-if="canViewAction"
                  type="button"
                  class="rounded-full p-1.5 text-brand-primary hover:bg-brand-primary-light"
                  title="View"
                  @click="goToView(row)"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>
                <button
                  v-if="canEditAction"
                  type="button"
                  class="rounded-full p-1.5 text-brand-primary hover:bg-brand-primary-light"
                  title="Edit"
                  @click="goToEdit(row)"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                </button>
                <button
                  v-if="canHistoryAction"
                  type="button"
                  class="rounded-full p-1.5 text-amber-600 hover:bg-amber-50"
                  title="View History"
                  @click="$emit('viewHistory', row)"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </button>
                <button
                  v-if="canDeleteAction"
                  type="button"
                  class="rounded-full p-1.5 text-red-600 hover:bg-red-50"
                  title="Delete"
                  @click="$emit('delete', row)"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
