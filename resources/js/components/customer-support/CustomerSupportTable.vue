<script setup>
/**
 * Customer Support Requests table – sortable headers, inline edit: dropdown on click, input on double-click.
 * Same design as Field/Lead: green header, jet black row borders, 30-char truncation + tooltip.
 */
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { canModuleAction } from '@/lib/accessControl'

const router = useRouter()

function goToView(row) {
  if (row?.id) router.push(`/customer-support/${row.id}`)
}

function goToEdit(row) {
  if (row?.id) router.push(`/customer-support/${row.id}/edit`)
}

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  sort: { type: String, default: 'submitted_at' },
  order: { type: String, default: 'desc' },
  loading: { type: Boolean, default: false },
  currentPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 15 },
  /** Options for dropdowns: issue_categories, statuses, managers, team_leaders, sales_agents. */
  editOptions: { type: Object, default: () => ({}) },
  /** Whether user can bulk assign (shows checkboxes and Assign button). */
  canBulkAssign: { type: Boolean, default: false },
  /** Selected row IDs for bulk assign. */
  selectedIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['sort', 'updateCell', 'viewHistory', 'openAssign', 'update:selectedIds'])

const auth = useAuthStore()
const canViewAction = computed(() => canModuleAction(auth.user, 'support', 'view'))
const canEditAction = computed(() => canModuleAction(auth.user, 'support', 'edit'))
const canHistoryAction = computed(() => canViewAction.value)
const canInlineEdit = computed(() => {
  const roles = auth.user?.roles ?? []
  if (!Array.isArray(roles)) return false
  const hasRole = (name) => roles.some((r) => (typeof r === 'string' ? r : r?.name) === name)
  return canEditAction.value && (hasRole('superadmin') || hasRole('back_office') || hasRole('backoffice'))
})

function rowNumber(index) {
  return (props.currentPage - 1) * props.perPage + index + 1
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

const columnLabels = {
  id: 'SR',
  submitted_at: 'Created',
  ticket_number: 'AH Ticket ID',
  account_number: 'Account Number',
  company_name: 'Company Name',
  issue_category: 'Issue Category',
  contact_number: 'Contact Number',
  issue_description: 'Issue Description',
  creator: 'Submitted By',
  csr: 'CSR Name',
  sla_timer: 'SLA Timer',
  manager: 'Manager',
  team_leader: 'Team Leader',
  sales_agent: 'Sales Agent',
  status: 'Status',
  workflow_status: 'SLA Status',
  completion_date: 'Completion Date',
  updated_at: 'Last Updated',
  created_at: 'Created',
  trouble_ticket: 'Trouble Ticket',
  activity: 'Activity',
  pending: 'Pending With',
  resolution_remarks: 'Resolution Remarks',
  internal_remarks: 'Internal Remarks',
}

const SORTABLE_COLUMNS = [
  'id', 'submitted_at', 'ticket_number', 'account_number', 'company_name', 'issue_category',
  'contact_number', 'csr', 'manager', 'team_leader', 'sales_agent',
  'status', 'workflow_status', 'completion_date', 'updated_at', 'created_at',
  'trouble_ticket', 'pending',
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
  'issue_category', 'company_name', 'account_number', 'contact_number', 'issue_description',
  'manager', 'team_leader', 'sales_agent', 'creator', 'csr', 'status',
  'submitted_at', 'created_at', 'updated_at', 'completion_date',
  'trouble_ticket', 'activity', 'pending', 'resolution_remarks', 'internal_remarks',
  'workflow_status', 'ticket_number',
]
function shouldTruncate(col) {
  return TRUNCATE_COLUMNS.includes(col)
}
function cellTitle(row, col) {
  if (col === 'id' || !shouldTruncate(col)) return undefined
  const full = fullValue(row, col)
  return full || undefined
}

const DROPDOWN_COLUMNS = ['status', 'issue_category', 'manager', 'team_leader', 'sales_agent', 'csr']
const READ_ONLY_COLUMNS = ['id', 'creator', 'submitted_at', 'created_at']

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
  if (col === 'csr') return row.csr_id ?? ''
  return row[col] != null ? String(row[col]) : ''
}

const editingCell = ref(null)
const inlineEditValue = ref('')
const inlineEditError = ref('')

function validatePhone(value) {
  if (!value || !value.trim()) return 'Contact number is required.'
  if (/\s/.test(value)) return 'Must not contain spaces.'
  if (!/^\d+$/.test(value)) return 'Must contain only digits.'
  if (!value.startsWith('971')) return 'Must start with 971.'
  if (value.length !== 12) return 'Must be exactly 12 digits.'
  return null
}

function onPhoneInput(event) {
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
  if (col === 'contact_number') {
    const err = validatePhone(value)
    if (err) {
      inlineEditError.value = err
      return
    }
  }
  if (col === 'company_name') {
    if (!value || !value.trim()) { inlineEditError.value = 'Company name is required.'; return }
    if (value.length > 255) { inlineEditError.value = 'Company name must not exceed 255 characters.'; return }
  }
  if (col === 'issue_description') {
    if (!value || !value.trim()) { inlineEditError.value = 'Issue description is required.'; return }
    if (value.length > 5000) { inlineEditError.value = 'Issue description must not exceed 5000 characters.'; return }
  }
  if (col === 'issue_category' && (!value || !value.trim())) {
    inlineEditError.value = 'Issue category is required.'
    return
  }
  if (col === 'account_number' && value && value.length > 100) {
    inlineEditError.value = 'Account number must not exceed 100 characters.'
    return
  }
  if (col === 'trouble_ticket' && value && value.length > 255) {
    inlineEditError.value = 'Trouble ticket must not exceed 255 characters.'
    return
  }
  if (col === 'activity' && value && value.length > 255) {
    inlineEditError.value = 'Activity must not exceed 255 characters.'
    return
  }
  if (col === 'pending' && value && value.length > 255) {
    inlineEditError.value = 'Pending must not exceed 255 characters.'
    return
  }
  const REQUIRED_DROPDOWNS = { manager: 'Manager', team_leader: 'Team Leader', sales_agent: 'Sales Agent' }
  if (col in REQUIRED_DROPDOWNS && (value === '' || value == null)) {
    inlineEditError.value = `${REQUIRED_DROPDOWNS[col]} is required.`
    return
  }
  if (['manager', 'team_leader', 'sales_agent', 'csr'].includes(col)) {
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
    case 'issue_category':
      return (opt.issue_categories || []).map((c) => ({ value: typeof c === 'string' ? c : c.value, label: typeof c === 'string' ? c : (c.label || c.value) }))
    case 'status':
      return (opt.statuses || []).map((s) => ({ value: typeof s === 'string' ? s : s.value, label: typeof s === 'string' ? s : (s.label || s.value) }))
    case 'manager':
      return (opt.managers || []).map((m) => ({ value: m.id, label: m.name }))
    case 'team_leader':
      return (opt.team_leaders || []).map((t) => ({ value: t.id, label: t.name }))
    case 'sales_agent':
      return (opt.sales_agents || []).map((s) => ({ value: s.id, label: s.name }))
    case 'csr':
      return [
        { value: '', label: 'Unassign' },
        ...(opt.csrs || []).map((c) => ({ value: c.id, label: c.name })),
      ]
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
  completed: 'bg-emerald-100 text-emerald-700',
  'Pending with CM': 'bg-orange-100 text-orange-700',
  'Pending with DU': 'bg-yellow-100 text-yellow-700',
  'Pending with Sales': 'bg-purple-100 text-purple-700',
  'Pending with CSR': 'bg-pink-100 text-pink-700',
}
function statusBadgeClass(status) {
  return STATUS_BADGES[status] ?? 'bg-gray-100 text-gray-700'
}

function canResubmit(row) {
  if (row.status === 'approved') return false
  const roles = auth.user?.roles ?? []
  const isSuperAdmin = Array.isArray(roles) && roles.some((r) => (typeof r === 'string' ? r : r?.name) === 'superadmin')
  if (isSuperAdmin) return true
  const creatorId = row.creator_id ?? row.created_by
  return creatorId != null && Number(creatorId) === Number(auth.user?.id)
}

const WORKFLOW_BADGES = {
  'On Time': 'bg-green-100 text-green-700',
  'on_time': 'bg-green-100 text-green-700',
  'Breached': 'bg-red-100 text-red-700',
  'breached': 'bg-red-100 text-red-700',
  'Approaching Breach': 'bg-orange-100 text-orange-700',
  'approaching_breach': 'bg-orange-100 text-orange-700',
  'open': 'bg-blue-100 text-blue-700',
  'in_progress': 'bg-yellow-100 text-yellow-700',
  'pending': 'bg-orange-100 text-orange-700',
  'resolved': 'bg-green-100 text-green-700',
  'closed': 'bg-gray-100 text-gray-700',
}
function workflowBadgeClass(val) {
  return WORKFLOW_BADGES[val] ?? 'bg-gray-100 text-gray-700'
}

function isUnassignedCsr(row) {
  return row.csr_id == null && (!row.csr || row.csr === '' || row.csr === '—')
}

const hasAnyRowAction = computed(() => {
  if (canViewAction.value || canEditAction.value || canHistoryAction.value) return true
  return canEditAction.value && (props.data || []).some((row) => canResubmit(row))
})
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
          <th v-if="canBulkAssign" class="w-10 px-3 py-3 text-left">
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
          <th v-if="hasAnyRowAction" scope="col" class="whitespace-nowrap px-4 py-3 text-center text-sm font-bold capitalize text-white">
            Actions
          </th>
        </tr>
      </thead>
      <tbody class="bg-white">
        <tr v-if="!loading && !data.length" class="border-b border-black bg-white">
          <td :colspan="columns.length + (canBulkAssign ? 1 : 0) + (hasAnyRowAction ? 1 : 0)" class="px-4 py-12 text-center text-gray-500">
            No customer support requests found.
          </td>
        </tr>
        <tr
          v-for="(row, rowIndex) in data"
          :key="row.id"
          class="border-b border-black bg-white hover:bg-gray-50/50"
        >
          <td v-if="canBulkAssign" class="w-10 px-3 py-3">
            <input
              type="checkbox"
              class="rounded border-gray-300"
              :checked="selectedSet.has(String(row.id))"
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
            <!-- Dropdown edit -->
            <template v-if="col === 'id'">
              {{ rowNumber(rowIndex) }}
            </template>
            <template v-else-if="canInlineEdit && isEditing(row.id, col) && isDropdownColumn(col)">
              <div class="flex flex-col gap-1.5">
                <select
                  v-model="inlineEditValue"
                  class="w-full min-w-[200px] rounded border bg-white px-3 py-1.5 pr-8 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  :class="inlineEditError ? 'border-red-500' : 'border-gray-300'"
                  @change="inlineEditError = ''"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                >
                  <option v-for="o in getOptionsForColumn(col)" :key="String(o.value)" :value="o.value">{{ o.label }}</option>
                </select>
                <p v-if="inlineEditError" class="text-xs text-red-600">{{ inlineEditError }}</p>
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700" @click="saveInlineEdit">Save</button>
                </div>
              </div>
            </template>
            <!-- Input edit -->
            <template v-else-if="canInlineEdit && isEditing(row.id, col) && isInputColumn(col)">
              <div class="flex flex-col gap-1.5">
                <textarea
                  v-if="col === 'issue_description'"
                  v-model="inlineEditValue"
                  rows="3"
                  class="w-full min-w-[180px] max-w-[280px] rounded border bg-white px-2 py-1 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  :class="inlineEditError ? 'border-red-500' : 'border-gray-300'"
                  @input="inlineEditError = ''"
                  @keydown.esc="cancelInlineEdit"
                />
                <input
                  v-else-if="col === 'contact_number'"
                  :value="inlineEditValue"
                  type="text"
                  maxlength="12"
                  placeholder="971XXXXXXXXX"
                  class="w-full min-w-[100px] max-w-[220px] rounded border bg-white px-2 py-1 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  :class="inlineEditError ? 'border-red-500' : 'border-gray-300'"
                  @input="onPhoneInput($event)"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                />
                <input
                  v-else
                  v-model="inlineEditValue"
                  type="text"
                  class="w-full min-w-[100px] max-w-[220px] rounded border bg-white px-2 py-1 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
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
            <!-- CSR Name: dropdown editable on double-click -->
            <template v-else-if="col === 'csr' && canInlineEdit && isEditing(row.id, 'csr')">
              <div class="flex flex-col gap-1.5">
                <select
                  v-model="inlineEditValue"
                  class="w-full min-w-[160px] max-w-[220px] rounded border bg-white px-3 py-1.5 pr-8 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  :class="inlineEditError ? 'border-red-500' : 'border-gray-300'"
                  @change="inlineEditError = ''"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                >
                  <option v-for="o in getOptionsForColumn('csr')" :key="String(o.value)" :value="o.value">{{ o.label }}</option>
                </select>
                <p v-if="inlineEditError" class="text-xs text-red-600">{{ inlineEditError }}</p>
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700" @click="saveInlineEdit">Save</button>
                </div>
              </div>
            </template>
            <template v-else-if="col === 'csr' && canInlineEdit">
              <span
                v-if="isUnassignedCsr(row)"
                class="cursor-pointer text-red-600 hover:bg-gray-100 rounded px-0.5"
                @dblclick="openDropdownEdit(row, 'csr')"
              >Unassigned</span>
              <span
                v-else
                class="cursor-pointer hover:bg-gray-100 rounded px-0.5"
                @dblclick="openDropdownEdit(row, 'csr')"
              >{{ truncate(formatValue(row, 'csr')) }}</span>
            </template>
            <template v-else-if="col === 'csr' && isUnassignedCsr(row)">
              <span class="text-red-600">Unassigned</span>
            </template>
            <!-- Workflow / SLA Status badge -->
            <template v-else-if="col === 'workflow_status'">
              <span
                v-if="row.workflow_status"
                :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', workflowBadgeClass(row.workflow_status)]"
              >
                {{ row.workflow_status }}
              </span>
              <span v-else class="text-gray-400">—</span>
            </template>
            <template v-else-if="col === 'sla_timer'">
              <span
                v-if="row.sla_timer"
                :class="[
                  'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                  String(row.sla_timer).startsWith('Overdue') ? 'bg-red-100 text-red-700'
                    : String(row.sla_timer) === 'Assigned' ? 'bg-green-100 text-green-700'
                      : String(row.sla_timer).startsWith('Due in') ? 'bg-orange-100 text-orange-700'
                        : 'bg-blue-100 text-blue-700',
                ]"
              >
                {{ row.sla_timer }}
              </span>
              <span v-else class="text-gray-400">—</span>
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
            <template v-else-if="canInlineEdit && isDropdownColumn(col)">
              <span
                class="cursor-pointer hover:bg-gray-100 rounded px-0.5 min-w-0 inline-block max-w-full truncate"
                @click="openDropdownEdit(row, col)"
                @dblclick="openDropdownEdit(row, col)"
              >{{ truncate(formatValue(row, col)) }}</span>
            </template>
            <template v-else-if="canInlineEdit && isInputColumn(col)">
              <span
                class="cursor-text hover:bg-gray-50 rounded px-0.5"
                @dblclick="openInputEdit(row, col)"
              >{{ col === 'issue_description' ? truncate(formatValue(row, col)) : truncate(formatValue(row, col)) }}</span>
            </template>
            <template v-else>
              {{ truncate(formatValue(row, col)) }}
            </template>
          </td>
          <td v-if="hasAnyRowAction" class="whitespace-nowrap px-4 py-3">
            <div class="flex items-center justify-between gap-3">
              <div class="inline-flex items-center gap-1">
                <button
                  v-if="canViewAction"
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
                  v-if="canEditAction"
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
              </div>
              <router-link
                v-if="canEditAction && canResubmit(row)"
                :to="`/customer-support/${row.id}/resubmit`"
                class="rounded bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-700"
              >
                Resubmit
              </router-link>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
