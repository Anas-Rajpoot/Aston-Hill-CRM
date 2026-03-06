<script setup>
/**
 * Dynamic table: sortable headers, status/last updated inline edit with Save/Cancel.
 */
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { canModuleAction } from '@/lib/accessControl'
import { formatUserDate, formatSystemDateTime } from '@/lib/dateFormat'

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  sort: { type: String, default: 'submitted_at' },
  order: { type: String, default: 'desc' },
  loading: { type: Boolean, default: false },
  /** Selected row IDs (for bulk assign). */
  selectedIds: { type: Array, default: () => [] },
  /** Current page (1-based) for row number. */
  currentPage: { type: Number, default: 1 },
  /** Per-page size for row number. */
  perPage: { type: Number, default: 15 },
  /** Options for dropdowns: categories, types, executives, managers, teamLeaders, salesAgents, call_verification_options, etc. */
  editOptions: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['sort', 'updateStatus', 'updateStatusChangedAt', 'updateCell', 'openEdit', 'openAssign', 'update:selectedIds', 'viewHistory', 'delete'])
let router = null
try {
  router = useRouter()
} catch {
  // Not inside a router (e.g. test env); navigation will be no-op
}
const auth = useAuthStore()
const canViewAction = computed(() => canModuleAction(auth.user, 'lead', 'view'))
const canEditAction = computed(() => canModuleAction(auth.user, 'lead', 'edit'))
const canHistoryAction = computed(() => canViewAction.value)
const canDeleteAction = computed(() => canModuleAction(auth.user, 'lead', 'delete'))

function goToDetail(leadId) {
  if (router && typeof router.push === 'function') {
    router.push(`/lead-submissions/${leadId}`)
  }
}
const canEdit = computed(() => {
  return canEditAction.value
})

/** Only superadmin or backoffice can open Edit Submission modal (back office form). */
const canEditBackOffice = computed(() => {
  const roles = auth.user?.roles ?? []
  if (!Array.isArray(roles)) return false
  return canEditAction.value && roles.some((r) => {
    const name = typeof r === 'string' ? r : r?.name
    return name === 'superadmin' || name === 'backoffice' || name === 'back_office'
  })
})

/** Resubmit: submitted or rejected; super admin or the user who submitted (creator / created_by). */
function canResubmit(row) {
  if (row.status === 'approved') return false
  const roles = auth.user?.roles ?? []
  const isSuperAdmin = Array.isArray(roles) && roles.some((r) => (typeof r === 'string' ? r : r?.name) === 'superadmin')
  if (isSuperAdmin) return true
  const creatorId = row.creator?.id ?? row.created_by
  return creatorId != null && Number(creatorId) === Number(auth.user?.id)
}

/** { rowId, col } when a cell is in edit mode; inlineEditValue holds current input/select value */
const editingCell = ref(null)
const editStatusValue = ref('')
const editStatusChangedAtValue = ref('')
const inlineEditValue = ref('')
const inlineEditError = ref('')

const PHONE_COLUMNS = ['contact_number_gsm', 'alternate_contact_number']

function validatePhone(value, required = false) {
  if (!value || !value.trim()) return required ? 'Contact number is required.' : null
  if (/\s/.test(value)) return 'Must not contain spaces.'
  if (!/^\d+$/.test(value)) return 'Must contain only digits.'
  if (!value.startsWith('971')) return 'Must start with 971.'
  if (value.length !== 12) return 'Must be exactly 12 digits.'
  return null
}

function validateEmail(value) {
  if (!value || !value.trim()) return null
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())) return 'Please enter a valid email address.'
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

const AE_DOMAIN_FORBIDDEN = ['lac', 'rac', 'rat', 'sgns']
const AE_DOMAIN_SPECIAL = /[@#$%^&*()\-+={}\[\]:;'"\\<>,_?/!_`|\s]/
function validateAeDomain(value) {
  if (!value || !value.trim()) return null
  const v = value.trim()
  if (/\s/.test(v)) return 'Domain must not contain spaces.'
  if (/[0-9]/.test(v)) return 'Domain must not contain numbers.'
  if (AE_DOMAIN_SPECIAL.test(v)) return 'Domain must not contain special characters.'
  const lower = v.toLowerCase()
  for (const kw of AE_DOMAIN_FORBIDDEN) {
    if (lower.includes(kw)) return 'Domain must not contain: LAC, RAC, RAT, SGNS.'
  }
  if ((v.match(/\./g) || []).length !== 1) return 'Domain must contain only one dot.'
  if (!lower.endsWith('.ae')) return 'Domain must end with .ae'
  return null
}

function validateMrc(value) {
  if (!value || value === '') return null
  if (isNaN(parseInt(value, 10)) || parseInt(value, 10) < 0) return 'MRC must be a valid whole number (0 or more).'
  return null
}

function validateQuantity(value) {
  if (!value || value === '') return null
  if (parseInt(value, 10) < 0 || !Number.isInteger(Number(value))) return 'Quantity must be a whole number.'
  return null
}

function onPhoneInput(event) {
  inlineEditValue.value = event.target.value.replace(/\D/g, '')
  inlineEditError.value = ''
}

/** Columns that use dropdown (click to edit). */
const DROPDOWN_COLUMNS = [
  'status', 'submission_type', 'executive', 'category', 'type', 'manager', 'team_leader', 'sales_agent',
  'call_verification', 'pending_from_sales', 'documents_verification', 'du_status',
]
/** Columns that are read-only (no inline edit). */
const READ_ONLY_COLUMNS = ['id', 'sla_timer', 'creator', 'submitted_at', 'updated_at']
/** Columns that use text/number/date input (double-click to edit). */
function isInputColumn(col) {
  return !READ_ONLY_COLUMNS.includes(col) && !DROPDOWN_COLUMNS.includes(col) && col !== 'status_changed_at'
}
function isDropdownColumn(col) {
  return DROPDOWN_COLUMNS.includes(col)
}

function getCellValueForEdit(row, col) {
  if (col === 'submission_type') return row.submission_type === 'Resubmission' ? 'resubmission' : 'new'
  if (col === 'executive') return row.executive_id ?? ''
  if (col === 'category') return row.service_category_id ?? ''
  if (col === 'type') return row.service_type_id ?? ''
  if (col === 'manager') return row.manager_id ?? ''
  if (col === 'team_leader') return row.team_leader_id ?? ''
  if (col === 'sales_agent') return row.sales_agent_id ?? ''
  if (col === 'mrc_aed') return row.mrc_aed != null ? String(row.mrc_aed) : ''
  if (col === 'quantity') return row.quantity != null && row.quantity !== '' ? String(row.quantity) : ''
  if (col === 'submission_date_from' || col === 'completion_date') {
    const v = row[col]
    if (!v) return ''
    const d = new Date(v)
    if (Number.isNaN(d.getTime())) return ''
    return d.toISOString().slice(0, 10)
  }
  return row[col] != null ? String(row[col]) : ''
}

function openDropdownEdit(row, col) {
  if (!canEditBackOffice.value) return
  editingCell.value = { rowId: row.id, col }
  inlineEditValue.value = getCellValueForEdit(row, col)
  inlineEditError.value = ''
}

function openInputEdit(row, col) {
  if (!canEditBackOffice.value) return
  editingCell.value = { rowId: row.id, col }
  inlineEditValue.value = getCellValueForEdit(row, col)
  inlineEditError.value = ''
}

function saveInlineEdit() {
  if (!editingCell.value) return
  const { rowId, col } = editingCell.value
  let value = inlineEditValue.value

  let err = null
  if (PHONE_COLUMNS.includes(col)) {
    err = validatePhone(value, col === 'contact_number_gsm')
  } else if (col === 'email') {
    err = validateEmail(value)
  } else if (col === 'location_coordinates') {
    err = validateCoordinates(value)
  } else if (col === 'ae_domain') {
    err = validateAeDomain(value)
  } else if (col === 'mrc_aed') {
    err = validateMrc(value)
  } else if (col === 'quantity') {
    err = validateQuantity(value)
  } else if (col === 'company_name') {
    if (!value || !value.trim()) err = 'Company name is required.'
  } else if (col === 'product') {
    if (!value || !value.trim()) err = 'Product is required.'
  } else if (col === 'address') {
    if (!value || !value.trim()) err = 'Complete address is required.'
  }

  if (err) {
    inlineEditError.value = err
    return
  }

  if (col === 'mrc_aed' || col === 'quantity') value = value === '' ? null : (col === 'quantity' ? parseInt(value, 10) : parseFloat(value))
  if (col === 'submission_date_from' || col === 'completion_date') value = value || null
  emit('updateCell', rowId, col, value)
  editingCell.value = null
  inlineEditError.value = ''
}

function cancelInlineEdit() {
  editingCell.value = null
  inlineEditError.value = ''
}

const SUBMISSION_TYPE_OPTIONS = [
  { value: 'new', label: 'New Submission' },
  { value: 'resubmission', label: 'Resubmission' },
]

function getOptionsForColumn(col) {
  const opt = props.editOptions || {}
  switch (col) {
    case 'submission_type':
      return SUBMISSION_TYPE_OPTIONS
    case 'executive':
      return [{ value: null, label: 'Unassigned' }, ...(opt.executives || []).map((e) => ({ value: e.id, label: e.name }))]
    case 'category':
      return (opt.categories || []).map((c) => ({ value: c.id, label: c.name }))
    case 'type':
      return (opt.types || []).map((t) => ({ value: t.id, label: t.name }))
    case 'manager':
      return (opt.managers || []).map((m) => ({ value: m.id, label: m.name }))
    case 'team_leader':
      return (opt.teamLeaders || []).map((t) => ({ value: t.id, label: t.name }))
    case 'sales_agent':
      return (opt.salesAgents || []).map((s) => ({ value: s.id, label: s.name }))
    case 'call_verification':
      return opt.call_verification_options || []
    case 'pending_from_sales':
      return opt.pending_from_sales_options || []
    case 'documents_verification':
      return opt.documents_verification_options || []
    case 'du_status':
      return opt.du_status_options || []
    default:
      return []
  }
}

const STATUS_OPTIONS = [
  { value: 'submitted', label: 'Submitted' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'pending_for_ata', label: 'Pending for ATA' },
  { value: 'pending_for_finance', label: 'Pending for Finance' },
  { value: 'pending_from_sales', label: 'Pending from Sales' },
  { value: 'unassigned', label: 'Unassigned' },
]

function statusLabel(status) {
  if (!status) return '—'
  const found = STATUS_OPTIONS.find((opt) => opt.value === status)
  if (found) return found.label
  return String(status)
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

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
  submitted_at: 'Submitted At',
  updated_at: 'Updated At',
  submission_type: 'Request Type',
  account_number: 'Account Number',
  company_name: 'Company Name',
  authorized_signatory_name: 'Authorized Signatory Name',
  email: 'Email ID',
  contact_number_gsm: 'Contact Number',
  alternate_contact_number: 'Alternate Contact Number',
  address: 'Complete Address',
  emirate: 'Emirate',
  location_coordinates: 'Location Coordinates',
  category: 'Service Category',
  type: 'Service Type',
  product: 'Product',
  offer: 'Offer',
  mrc_aed: 'MRC (AED)',
  quantity: 'Quantity',
  ae_domain: '.ae Domain',
  gaid: 'GAID',
  previous_activity: 'Old Activity',
  resubmission_reason: 'Resubmission Reason',
  remarks: 'Remarks',
  sales_agent: 'Sales Agent',
  team_leader: 'Team Leader',
  manager: 'Manager',
  status: 'Status',
  sla_timer: 'SLA Timer',
  executive: 'Back Office Executive',
  status_changed_at: 'Status Updated At',
  creator: 'Submitter Name',
  call_verification: 'Call Verification',
  pending_from_sales: 'Pending From Sales',
  documents_verification: 'Documents Verification',
  submission_date_from: 'Submission Date',
  back_office_notes: 'Back Office Notes',
  activity: 'Activity',
  back_office_account: 'Back Office Account',
  work_order: 'Work Order',
  du_status: 'DU Status',
  completion_date: 'Completion Date',
  du_remarks: 'DU Remarks',
  additional_note: 'Additional Note',
}

const SORTABLE_COLUMNS = [
  'id', 'submitted_at', 'updated_at', 'account_number', 'company_name',
  'category', 'type', 'product', 'mrc_aed', 'quantity', 'status',
  'status_changed_at', 'sales_agent', 'team_leader', 'manager', 'creator',
  'executive', 'email', 'contact_number_gsm', 'submission_type', 'sla_timer',
]

function label(col) {
  return columnLabels[col] ?? col
}

/** Columns to display: never show created_at. */
const effectiveColumns = computed(() => (props.columns || []).filter((c) => c !== 'created_at'))

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

function rowNumber(index) {
  return (props.currentPage - 1) * props.perPage + index + 1
}

const hasAnyRowAction = computed(() => {
  if (canViewAction.value || canEditBackOffice.value || canHistoryAction.value || canDeleteAction.value) return true
  return canEditAction.value && (props.data || []).some((row) => canResubmit(row))
})

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
  return formatUserDate(d, '—')
}

/** Date + time format: 12-Feb-2026 14:30 */
function formatDateTime(d) {
  return formatSystemDateTime(d, '—')
}

/** Max characters per column; longer values show "..." and full value on hover. */
const TRUNCATE_LENGTH = 30

function truncate(str, max = TRUNCATE_LENGTH) {
  if (!str || typeof str !== 'string') return '—'
  const s = String(str)
  return s.length > max ? s.slice(0, max) + '...' : s
}

/** Full value for tooltip (hover); use with truncate() for display. */
function fullValue(row, col) {
  const val = formatValue(row, col)
  return val == null || val === '—' ? '' : String(val)
}

/** Tooltip for truncated cells: show full value only. */
function cellTitle(row, col) {
  return fullValue(row, col)
}

const STATUS_BADGES = {
  draft: 'bg-gray-100 text-gray-700',
  submitted: 'bg-brand-primary-light text-brand-primary-hover',
  approved: 'bg-brand-primary-light text-brand-primary-hover',
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
            v-for="col in effectiveColumns"
            :key="col"
            scope="col"
            class="whitespace-nowrap px-4 py-3 text-left text-sm font-semibold text-black cursor-pointer select-none"
            @click="sortable(col) ? toggleSort(col) : null"
          >
            <button
              v-if="sortable(col)"
              type="button"
              class="inline-flex items-center gap-1 font-semibold text-white hover:text-white/70"
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
            <span v-else class="font-semibold text-black">{{ label(col) }}</span>
          </th>
          <th v-if="hasAnyRowAction" scope="col" class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-white">
            Actions
          </th>
        </tr>
      </thead>
      <tbody class="bg-white">
        <tr v-if="!loading && !data.length" class="border-b border-black bg-white">
          <td :colspan="effectiveColumns.length + (hasAnyRowAction ? 2 : 1)" class="px-4 py-12 text-center text-gray-500">
            No leads found.
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
              @change="toggleRow(row.id)"
            />
          </td>
          <td
            v-for="col in effectiveColumns"
            :key="col"
            class="whitespace-nowrap px-4 py-3 text-sm text-gray-900"
            :class="{ 'cursor-pointer': canEditBackOffice && isDropdownColumn(col) && !isEditing(row.id, col), 'cursor-text': canEditBackOffice && isInputColumn(col) && !isEditing(row.id, col) }"
          >
            <!-- Generic dropdown edit (click to open) -->
            <template v-if="canEditBackOffice && isEditing(row.id, col) && isDropdownColumn(col) && col !== 'status'">
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
            <!-- Generic input edit (double-click to open) -->
            <template v-else-if="canEditBackOffice && isEditing(row.id, col) && isInputColumn(col)">
              <div class="flex flex-col gap-1.5">
                <input
                  v-if="PHONE_COLUMNS.includes(col)"
                  :value="inlineEditValue"
                  type="text"
                  maxlength="12"
                  placeholder="971XXXXXXXXX"
                  class="w-full min-w-[160px] max-w-[220px] rounded border bg-white px-3 py-1.5 pr-8 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                  :class="inlineEditError ? 'border-red-500' : 'border-gray-300'"
                  @input="onPhoneInput($event)"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                />
                <input
                  v-else
                  v-model="inlineEditValue"
                  :type="col === 'submission_date_from' || col === 'completion_date' ? 'date' : (col === 'email' ? 'email' : 'text')"
                  class="w-full min-w-[160px] max-w-[220px] rounded border bg-white px-3 py-1.5 pr-8 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                  :class="inlineEditError ? 'border-red-500' : 'border-gray-300'"
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
            <template v-else-if="col === 'id'">
              {{ rowNumber(rowIndex) }}
            </template>
            <template v-else-if="col === 'status' && canEdit && isEditing(row.id, 'status')">
              <div class="flex flex-col gap-1.5">
                <select
                  v-model="editStatusValue"
                  class="w-full min-w-[160px] rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
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
                    class="rounded bg-brand-primary px-2 py-0.5 text-xs text-white hover:bg-brand-primary-hover"
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
                :class="['inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap cursor-pointer hover:ring-2 hover:ring-brand-primary', statusBadgeClass(row.status)]"
              >
                {{ statusLabel(row.status) }}
              </span>
            </button>
            </template>
            <template v-else-if="col === 'status'">
              <span
                :class="['inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap', statusBadgeClass(row.status)]"
              >
                {{ statusLabel(row.status) }}
              </span>
            </template>
            <template v-else-if="col === 'status_changed_at' && canEdit && isEditing(row.id, 'status_changed_at')">
              <div class="flex flex-col gap-1.5">
                <input
                  v-model="editStatusChangedAtValue"
                  type="datetime-local"
                  class="w-full min-w-0 max-w-[180px] rounded border border-gray-300 bg-white px-2 py-1 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
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
                    class="rounded bg-brand-primary px-2 py-0.5 text-xs text-white hover:bg-brand-primary-hover"
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
                class="text-left text-sm text-gray-900 hover:text-brand-primary hover:underline"
                @click="openStatusChangedAtEdit(row)"
              >
                {{ formatDateTime(row[col]) }}
              </button>
            </template>
            <template v-else-if="['submitted_at', 'status_changed_at', 'updated_at', 'submission_date_from', 'completion_date'].includes(col)">
              <span
                v-if="canEditBackOffice && (col === 'submission_date_from' || col === 'completion_date')"
                class="cursor-text hover:bg-gray-50 rounded px-0.5"
                :title="cellTitle(row, col)"
                @dblclick="openInputEdit(row, col)"
              >{{ formatDateTime(row[col]) }}</span>
              <span v-else :title="cellTitle(row, col)">{{ formatDateTime(row[col]) }}</span>
            </template>
            <template v-else-if="col === 'mrc_aed'">
              <span
                :class="{ 'cursor-text hover:bg-gray-50 rounded px-0.5': canEditBackOffice }"
                :title="cellTitle(row, col)"
                @dblclick="canEditBackOffice && openInputEdit(row, col)"
              >{{ row.mrc_aed != null ? Number(row.mrc_aed).toLocaleString() : '—' }}</span>
            </template>
            <template v-else-if="col === 'quantity'">
              <span
                :class="{ 'cursor-text hover:bg-gray-50 rounded px-0.5': canEditBackOffice }"
                :title="cellTitle(row, col)"
                @dblclick="canEditBackOffice && openInputEdit(row, col)"
              >{{ row.quantity != null && row.quantity !== '' ? Number(row.quantity) : '—' }}</span>
            </template>
            <template v-else-if="col === 'executive'">
              <button
                v-if="row[col] === 'Unassigned' && canEditBackOffice"
                type="button"
                class="text-left text-sm text-brand-primary hover:underline"
                @click="$emit('openAssign', row)"
              >
                Unassigned
              </button>
              <span
                v-else
                :class="{ 'cursor-pointer hover:bg-gray-100 rounded px-0.5': canEditBackOffice }"
                :title="cellTitle(row, col)"
                @click="canEditBackOffice && openDropdownEdit(row, col)"
              >{{ truncate(formatValue(row, col), TRUNCATE_LENGTH) }}</span>
            </template>
            <template v-else-if="col === 'submission_type'">
              <span
                :class="[
                  'inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
                  row[col] === 'Resubmission' ? 'bg-purple-100 text-purple-800' : 'bg-brand-primary-light text-brand-primary-hover',
                  canEditBackOffice ? 'cursor-pointer hover:ring-2 hover:ring-brand-primary' : '',
                ]"
                :title="cellTitle(row, col)"
                @click="canEditBackOffice && openDropdownEdit(row, col)"
              >
                {{ truncate(row[col] === 'Resubmission' ? 'Resubmission' : 'New Submission', TRUNCATE_LENGTH) }}
              </span>
            </template>
            <template v-else-if="col === 'sla_timer'">
              <span
                v-if="row[col]"
                :class="[
                  'inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
                  String(row[col]).startsWith('Overdue')
                    ? 'bg-red-100 text-red-800'
                    : String(row[col]).includes('passed of')
                      ? 'bg-brand-primary-light text-brand-primary-hover'
                    : String(row[col]).startsWith('Due in')
                      ? 'bg-orange-100 text-orange-800'
                      : String(row[col]).includes('h left')
                        ? 'bg-orange-100 text-orange-800'
                        : 'bg-brand-primary-light text-brand-primary-hover',
                ]"
                :title="cellTitle(row, col)"
              >
                {{ truncate(String(row[col]), TRUNCATE_LENGTH) }}
              </span>
              <span v-else>—</span>
            </template>
            <template v-else-if="canEditBackOffice && isDropdownColumn(col)">
              <span
                class="cursor-pointer hover:bg-gray-100 rounded px-0.5 min-w-0 inline-block max-w-full truncate"
                :title="cellTitle(row, col)"
                @click="openDropdownEdit(row, col)"
              >{{ truncate(formatValue(row, col), TRUNCATE_LENGTH) }}</span>
            </template>
            <template v-else-if="['company_name', 'account_number', 'product', 'type', 'email', 'address', 'back_office_notes', 'remarks', 'du_remarks', 'additional_note'].includes(col)">
              <span
                :class="{ 'cursor-text hover:bg-gray-50 rounded px-0.5': canEditBackOffice && isInputColumn(col) }"
                :title="cellTitle(row, col)"
                @dblclick="canEditBackOffice && isInputColumn(col) && openInputEdit(row, col)"
              >{{ truncate(formatValue(row, col), TRUNCATE_LENGTH) }}</span>
            </template>
            <template v-else-if="col === 'contact_number_gsm'">
              <span
                :class="{ 'cursor-text hover:bg-gray-50 rounded px-0.5': canEditBackOffice && isInputColumn(col) }"
                :title="cellTitle(row, col)"
                @dblclick="canEditBackOffice && isInputColumn(col) && openInputEdit(row, col)"
              >{{ truncate(formatValue(row, col), TRUNCATE_LENGTH) }}</span>
            </template>
            <template v-else>
              <span
                :class="{ 'cursor-text hover:bg-gray-50 rounded px-0.5': canEditBackOffice && isInputColumn(col) }"
                :title="cellTitle(row, col)"
                @dblclick="canEditBackOffice && isInputColumn(col) && openInputEdit(row, col)"
              >{{ truncate(formatValue(row, col), TRUNCATE_LENGTH) }}</span>
            </template>
          </td>
          <td v-if="hasAnyRowAction" class="whitespace-nowrap px-4 py-3">
            <div class="flex w-full min-w-[140px] items-center justify-between gap-2">
              <div class="inline-flex items-center gap-1 shrink-0">
                <button
                  v-if="canViewAction"
                  type="button"
                  class="rounded-full p-1.5 text-brand-primary hover:bg-brand-primary-light"
                  title="View details"
                  @click="goToDetail(row.id)"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>
                <button
                  v-if="canEditBackOffice"
                  type="button"
                  class="rounded-full p-1.5 text-brand-primary hover:bg-brand-primary-light"
                  title="Edit Submission"
                  @click="$emit('openEdit', row.id)"
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
              <div class="inline-flex min-w-[72px] shrink-0 justify-end">
                <router-link
                  v-if="canEditAction && canResubmit(row)"
                  :to="{ path: `/lead-submissions/${row.id}/resubmit` }"
                  class="rounded bg-brand-primary-hover px-2 py-1 text-xs font-medium text-white hover:bg-brand-primary-dark"
                >
                  Resubmit
                </router-link>
              </div>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
