<script setup>
/**
 * VAS Request table – sortable headers, inline edit, same design as Field/Lead/Customer Support.
 */
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { canModuleAction } from '@/lib/accessControl'

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
  sort: { type: String, default: 'created_at' },
  order: { type: String, default: 'desc' },
  loading: { type: Boolean, default: false },
  currentPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 15 },
  editOptions: { type: Object, default: () => ({}) },
  selectedIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['sort', 'updateCell', 'openAssign', 'update:selectedIds', 'viewHistory', 'delete'])

const auth = useAuthStore()
const canViewAction = computed(() => canModuleAction(auth.user, 'vas', 'view'))
const canEditAction = computed(() => canModuleAction(auth.user, 'vas', 'edit'))
const canHistoryAction = computed(() => canViewAction.value)
const canDeleteAction = computed(() => canModuleAction(auth.user, 'vas', 'delete'))
const canInlineEdit = computed(() => {
  if (canEditAction.value) return true
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
  id: 'SR',
  created_at: 'Created',
  updated_at: 'Last Updated',
  request_type: 'Request Type',
  account_number: 'Account Number',
  company_name: 'Company Name as per Trade License',
  description: 'Request Description',
  request_description: 'Request Description',
  additional_notes: 'Additional Notes',
  contact_number: 'Contact Number',
  manager: 'Manager Name',
  team_leader: 'Team Leader Name',
  sales_agent: 'Sales Agent Name',
  executive: 'Back Office Executive',
  submitted_at: 'Submission Date',
  sla_timer: 'SLA Timer',
  status: 'Status',
  activity: 'Activity',
  completion_date: 'Completion Date',
  remarks: 'Remarks',
  approved_at: 'Completion Date',
  rejected_at: 'Rejected Date',
  creator: 'Submitter Name',
}

const SORTABLE_COLUMNS = [
  'id', 'created_at', 'request_type', 'account_number', 'company_name',
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
  if (str == null || str === '') return '—'
  const s = String(str)
  return s.length > max ? s.slice(0, max) + '...' : s
}

function fullValue(row, col) {
  const val = formatValue(row, col)
  if (val == null || val === '—') return ''
  return String(val)
}

const TRUNCATE_COLUMNS = [
  'request_type', 'company_name', 'account_number', 'description', 'additional_notes', 'contact_number',
  'manager', 'team_leader', 'sales_agent', 'executive', 'creator', 'status',
  'created_at', 'updated_at', 'approved_at', 'rejected_at',
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
const READ_ONLY_COLUMNS = ['id', 'creator', 'created_at']

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
const inlineEditError = ref('')

function validatePhone(value) {
  if (!value) return 'Contact number is required.'
  if (/\s/.test(value)) return 'Contact number must not contain spaces.'
  if (!/^\d+$/.test(value)) return 'Contact number must contain only digits.'
  if (!value.startsWith('971')) return 'Contact number must start with 971.'
  if (value.length !== 12) return 'Contact number must be exactly 12 digits.'
  return null
}

function validateInlineCell(col, value) {
  if (col === 'contact_number') return validatePhone(value?.trim())
  if (col === 'request_type') return !value?.trim() ? 'Please select a request type.' : null
  if (col === 'account_number') return !value?.trim() ? 'Account number is required.' : null
  if (col === 'company_name') return !value?.trim() ? 'Company name is required.' : null
  if (col === 'description') return !value?.trim() ? 'Request description is required.' : null
  if (col === 'manager') return !value ? 'Manager is required.' : null
  if (col === 'team_leader') return !value ? 'Team Leader is required.' : null
  if (col === 'sales_agent') return !value ? 'Sales Agent is required.' : null
  return null
}

function onPhoneInlineInput(event) {
  inlineEditValue.value = event.target.value.replace(/\D/g, '')
  inlineEditError.value = ''
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

  const err = validateInlineCell(col, value)
  if (err) {
    inlineEditError.value = err
    return
  }

  if (['manager', 'team_leader', 'sales_agent', 'executive'].includes(col)) {
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

const hasAnyRowAction = computed(() => canViewAction.value || canEditAction.value || canHistoryAction.value || canDeleteAction.value)

const STATUS_BADGES = {
  draft: 'bg-gray-100 text-gray-700',
  submitted_under_process: 'bg-brand-primary-light text-brand-primary-hover',
  completed: 'bg-brand-primary-light text-brand-primary-hover',
  approved: 'bg-brand-primary-light text-brand-primary-hover',
  rejected: 'bg-red-100 text-red-700',
  unassigned: 'bg-rose-100 text-rose-700',
}
function statusBadgeClass(status) {
  return STATUS_BADGES[status] ?? 'bg-gray-100 text-gray-700'
}
function formatStatus(status) {
  if (!status) return '—'
  if (status === 'approved') return 'Completed'
  if (['draft', 'pending_with_csr', 'pending_with_du', 'pending_with_sales', 'pending_for_approval'].includes(status)) return 'UnAssigned'
  return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
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
        <svg class="h-8 w-8 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
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
              <svg v-if="sort === col" class="h-4 w-4" :class="order === 'asc' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
              </svg>
            </button>
            <span v-else class="font-bold text-white">{{ label(col) }}</span>
          </th>
          <th v-if="hasAnyRowAction" scope="col" class="whitespace-nowrap px-4 py-3 text-center text-sm font-bold capitalize text-white">Actions</th>
        </tr>
      </thead>
      <tbody class="bg-white">
        <tr v-if="!loading && !data.length" class="border-b border-black bg-white">
          <td :colspan="columns.length + (hasAnyRowAction ? 2 : 1)" class="px-4 py-12 text-center text-gray-500">No VAS requests found.</td>
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
                  :class="['w-full min-w-[160px] max-w-[220px] rounded border bg-white px-3 py-1.5 pr-8 text-sm focus:ring-1', inlineEditError ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-brand-primary focus:ring-brand-primary']"
                  @change="inlineEditError = ''"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                >
                  <option v-for="o in getOptionsForColumn(col)" :key="String(o.value)" :value="o.value">{{ o.label }}</option>
                </select>
                <p v-if="inlineEditError" class="text-xs text-red-600">{{ inlineEditError }}</p>
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-brand-primary px-2 py-0.5 text-xs text-white hover:bg-brand-primary-hover" @click="saveInlineEdit">Save</button>
                </div>
              </div>
            </template>
            <template v-else-if="canInlineEdit && isEditing(row.id, col) && isInputColumn(col)">
              <div class="flex flex-col gap-1.5">
                <textarea
                  v-if="col === 'description'"
                  v-model="inlineEditValue"
                  rows="3"
                  :class="['w-full min-w-[180px] max-w-[280px] rounded border bg-white px-2 py-1 text-sm focus:ring-1', inlineEditError ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-brand-primary focus:ring-brand-primary']"
                  @input="inlineEditError = ''"
                  @keydown.esc="cancelInlineEdit"
                />
                <input
                  v-else-if="col === 'contact_number'"
                  :value="inlineEditValue"
                  type="text"
                  maxlength="12"
                  placeholder="971XXXXXXXXX"
                  :class="['w-full min-w-[130px] max-w-[220px] rounded border bg-white px-2 py-1 text-sm focus:ring-1', inlineEditError ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-brand-primary focus:ring-brand-primary']"
                  @input="onPhoneInlineInput"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                />
                <input
                  v-else
                  v-model="inlineEditValue"
                  type="text"
                  :class="['w-full min-w-[100px] max-w-[220px] rounded border bg-white px-2 py-1 text-sm focus:ring-1', inlineEditError ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-brand-primary focus:ring-brand-primary']"
                  @input="inlineEditError = ''"
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
                  class="min-w-[160px] rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                >
                  <option value="unassigned" disabled>UnAssigned</option>
                  <option value="submitted_under_process">Submitted Under Process</option>
                  <option value="completed">Completed</option>
                  <option value="rejected">Rejected</option>
                </select>
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-brand-primary px-2 py-0.5 text-xs text-white hover:bg-brand-primary-hover" @click="saveInlineEdit">Save</button>
                </div>
              </div>
            </template>
            <template v-else-if="col === 'status' && canInlineEdit">
              <span
                :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium cursor-pointer hover:ring-2 hover:ring-brand-primary', statusBadgeClass(row.status)]"
                @dblclick="openDropdownEdit(row, 'status')"
              >
                {{ formatStatus(row.status) }}
              </span>
            </template>
            <template v-else-if="col === 'status'">
              <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(row.status)]">
                {{ formatStatus(row.status) }}
              </span>
            </template>
            <template v-else-if="col === 'sla_timer'">
              <span
                v-if="row.sla_timer"
                :class="[
                  'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                  String(row.sla_timer).startsWith('Overdue') ? 'bg-red-100 text-red-700'
                    : String(row.sla_timer) === 'Assigned' ? 'bg-brand-primary-light text-brand-primary-hover'
                      : String(row.sla_timer).startsWith('Due in') ? 'bg-orange-100 text-orange-700'
                        : 'bg-brand-primary-light text-brand-primary-hover',
                ]"
              >
                {{ row.sla_timer }}
              </span>
              <span v-else class="text-gray-400">—</span>
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
          <td v-if="hasAnyRowAction" class="whitespace-nowrap px-4 py-3">
            <div class="flex items-center justify-between gap-3">
              <div class="inline-flex items-center gap-1">
                <button v-if="canViewAction" type="button" class="rounded-full p-1.5 text-brand-primary hover:bg-brand-primary-light" title="View" @click="goToView(row)">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>
                <button v-if="canEditAction" type="button" class="rounded-full p-1.5 text-brand-primary hover:bg-brand-primary-light" title="Edit" @click="goToEdit(row)">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                </button>
                <button v-if="canHistoryAction" type="button" class="rounded-full p-1.5 text-amber-600 hover:bg-amber-50" title="View History" @click="$emit('viewHistory', row)">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </button>
                <button v-if="canDeleteAction" type="button" class="rounded-full p-1.5 text-red-600 hover:bg-red-50" title="Delete" @click="$emit('delete', row)">
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
