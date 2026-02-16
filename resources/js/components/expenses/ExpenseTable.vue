<script setup>
/**
 * Expense table – sortable, inline editable (with audit), View / Edit / Delete. Delete only for permission/super admin with popup.
 */
import { ref, computed, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  sort: { type: String, default: 'expense_date' },
  order: { type: String, default: 'desc' },
  loading: { type: Boolean, default: false },
  savingRowId: { type: Number, default: null },
  savingCell: { type: Object, default: () => ({ rowId: null, col: null }) },
  currentPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 15 },
  filterOptions: { type: Object, default: () => ({}) },
  canEdit: { type: Boolean, default: undefined },
  canDelete: { type: Boolean, default: undefined },
})

const emit = defineEmits(['sort', 'delete', 'view', 'edit', 'viewHistory', 'openEdit'])

// Column order as per design: Status first, then rest
const ORDERED_COLUMNS = ['status', 'expense_date', 'product_category', 'product_description', 'invoice_number', 'vat_amount', 'amount_without_vat', 'vat_amount_currency', 'full_amount', 'added_by', 'created_at']
const orderedColumns = computed(() => {
  const cols = [...(props.columns || [])]
  return cols.sort((a, b) => {
    const ia = ORDERED_COLUMNS.indexOf(a)
    const ib = ORDERED_COLUMNS.indexOf(b)
    if (ia === -1 && ib === -1) return 0
    if (ia === -1) return 1
    if (ib === -1) return -1
    return ia - ib
  })
})

const permissions = computed(() => auth.user?.permissions ?? [])
const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) && r.some((x) => (typeof x === 'string' ? x === 'superadmin' : x?.name === 'superadmin'))
})
const canView = computed(() => isSuperAdmin.value || permissions.value.includes('expense_tracker.view') || permissions.value.includes('expense_tracker.list'))
const effectiveCanEdit = computed(() => props.canEdit !== undefined ? props.canEdit : (isSuperAdmin.value || permissions.value.includes('expense_tracker.edit') || permissions.value.includes('expense_tracker.update')))
const effectiveCanDelete = computed(() => props.canDelete !== undefined ? props.canDelete : (isSuperAdmin.value || permissions.value.includes('expense_tracker.delete')))

const editingCell = ref({ rowId: null, col: null })
const editValue = ref('')

const EDITABLE_COLUMNS = ['status', 'expense_date', 'product_category', 'product_description', 'invoice_number', 'vat_amount', 'amount_without_vat']
const READONLY_COLUMNS = ['vat_amount_currency', 'full_amount']

function isEditable(col) {
  return EDITABLE_COLUMNS.includes(col)
}

function isReadOnly(col) {
  return READONLY_COLUMNS.includes(col)
}

function isCellEditing(row, col) {
  return editingCell.value.rowId === row.id && editingCell.value.col === col
}

function startCellEdit(row, col) {
  if (!effectiveCanEdit.value || !row?.id || !isEditable(col)) return
  editingCell.value = { rowId: row.id, col }
  if (col === 'expense_date') editValue.value = row.expense_date_raw || ''
  else if (col === 'vat_amount') editValue.value = row.vat_amount_raw != null ? row.vat_amount_raw : 0
  else if (col === 'amount_without_vat') editValue.value = row.amount_without_vat_raw != null ? row.amount_without_vat_raw : ''
  else editValue.value = row[col] ?? ''
}

function cancelCellEdit() {
  editingCell.value = { rowId: null, col: null }
}

function saveCellEdit(row) {
  const col = editingCell.value.col
  let value = editValue.value
  if (col === 'amount_without_vat') value = parseFloat(value)
  if (col === 'vat_amount') value = parseFloat(value)
  if (col === 'invoice_number' || col === 'product_category' || col === 'status' || col === 'product_description') value = value == null ? '' : String(value).trim()
  const payload = col === 'vat_amount' ? { vat_percent: value } : col === 'expense_date' ? { expense_date: value } : { [col]: value }
  emit('edit', { row, payload, isStatusToggle: false, col })
}

function toggleStatus(row) {
  if (!effectiveCanEdit.value || !row?.id) return
  const nextStatus = row.status === 'approved' ? 'pending' : 'approved'
  emit('edit', { row, payload: { status: nextStatus }, isStatusToggle: true })
}

const isStatusSaving = (row) => props.savingRowId === row.id
const isCellSaving = (row, col) => props.savingCell?.rowId === row.id && props.savingCell?.col === col

watch(
  () => props.savingCell,
  (next) => {
    if (next?.rowId == null && editingCell.value.rowId != null) {
      editingCell.value = { rowId: null, col: null }
    }
  },
  { deep: true }
)

const COLUMN_LABELS = {
  id: 'ID',
  expense_date: 'Expense Date',
  product_category: 'Product Category',
  product_description: 'Product Description',
  invoice_number: 'Invoice Number',
  vat_amount: 'VAT %',
  amount_without_vat: 'Amount (Without VAT)',
  vat_amount_currency: 'VAT Amount',
  full_amount: 'Total Amount',
  added_by: 'Added By',
  created_at: 'Created Date',
  status: 'Status',
}

const SORTABLE_COLUMNS = ['id', 'expense_date', 'product_category', 'product_description', 'invoice_number', 'vat_amount', 'amount_without_vat', 'vat_amount_currency', 'full_amount', 'added_by', 'created_at', 'status']

function label(col) {
  return COLUMN_LABELS[col] ?? col
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
  return val
}

function statusLabel(status) {
  if (status === 'approved') return 'Approved'
  if (status === 'pending') return 'Pending Approval'
  return status ?? '—'
}

function goToView(row) {
  if (row?.id && canView.value) emit('view', row)
}

function openEditModal(row) {
  if (row?.id && effectiveCanEdit.value) emit('openEdit', row)
}

function goToViewHistory(row) {
  if (row?.id) emit('viewHistory', row)
}

function onDelete(row) {
  if (row?.id && effectiveCanDelete.value) emit('delete', row)
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
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <span class="text-sm font-medium text-gray-600">Loading...</span>
      </div>
    </div>

    <table class="min-w-full border border-gray-200 border-collapse bg-white">
      <thead>
        <tr class="border-b border-gray-200 bg-sky-50">
          <th
            v-for="col in orderedColumns"
            :key="col"
            scope="col"
            class="whitespace-nowrap px-4 py-3 text-left text-sm font-semibold text-gray-800"
          >
            <button
              v-if="sortable(col)"
              type="button"
              class="inline-flex items-center gap-1 font-semibold text-gray-800 hover:text-gray-600"
              @click="toggleSort(col)"
            >
              {{ label(col) }}
              <svg v-if="sort === col" class="h-4 w-4" :class="order === 'asc' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
              </svg>
            </button>
            <span v-else class="font-semibold text-gray-800">{{ label(col) }}</span>
          </th>
          <th scope="col" class="whitespace-nowrap px-4 py-3 text-center text-sm font-semibold text-gray-800 bg-sky-50">Actions</th>
        </tr>
      </thead>
      <tbody class="bg-white">
        <tr v-if="!loading && !data.length" class="border-b border-gray-200">
          <td :colspan="orderedColumns.length + 1" class="px-4 py-12 text-center text-sm text-gray-500">No expenses found.</td>
        </tr>
        <tr
          v-for="row in data"
          :key="row.id"
          class="border-b border-gray-200 bg-white hover:bg-gray-50/30"
        >
          <td
            v-for="col in orderedColumns"
            :key="col"
            class="px-4 py-3 text-sm text-gray-900"
            :class="[
              col === 'product_description' ? '' : 'whitespace-nowrap',
              effectiveCanEdit && isEditable(col) ? 'cursor-pointer hover:bg-green-50/50' : '',
            ]"
            @dblclick="effectiveCanEdit && isEditable(col) && startCellEdit(row, col)"
          >
            <!-- Single-cell edit: input/dropdown + Save/Cancel + loader -->
            <template v-if="isCellEditing(row, col)">
              <div class="flex flex-col gap-2">
                <div class="flex flex-wrap items-center gap-2">
                  <input
                    v-if="col === 'expense_date'"
                    v-model="editValue"
                    type="date"
                    class="w-full min-w-[120px] rounded border border-green-500 px-2 py-1 text-sm"
                    :disabled="isCellSaving(row, col)"
                    @keydown.enter.prevent="saveCellEdit(row)"
                    @keydown.escape="cancelCellEdit"
                  />
                  <select
                    v-else-if="col === 'product_category'"
                    v-model="editValue"
                    class="w-full min-w-[140px] rounded border border-green-500 px-2 py-1 text-sm"
                    :disabled="isCellSaving(row, col)"
                    @keydown.escape="cancelCellEdit"
                  >
                    <option value="">Select</option>
                    <option v-for="c in (filterOptions.categories || [])" :key="c.value" :value="c.value">{{ c.label }}</option>
                  </select>
                  <select
                    v-else-if="col === 'vat_amount'"
                    v-model.number="editValue"
                    class="w-full min-w-[100px] rounded border border-green-500 px-2 py-1 text-sm"
                    :disabled="isCellSaving(row, col)"
                    @keydown.escape="cancelCellEdit"
                  >
                    <option v-for="o in (filterOptions.vat_percent_options || [])" :key="o.value" :value="o.value">{{ o.label }}</option>
                  </select>
                  <select
                    v-else-if="col === 'status'"
                    v-model="editValue"
                    class="w-full min-w-[120px] rounded border border-green-500 px-2 py-1 text-sm"
                    :disabled="isCellSaving(row, col)"
                    @keydown.escape="cancelCellEdit"
                  >
                    <option v-for="o in (filterOptions.status_options || [])" :key="o.value" :value="o.value">{{ o.label }}</option>
                  </select>
                  <input
                    v-else-if="col === 'amount_without_vat'"
                    v-model.number="editValue"
                    type="number"
                    step="0.01"
                    min="0"
                    class="w-full min-w-[90px] rounded border border-green-500 px-2 py-1 text-sm"
                    :disabled="isCellSaving(row, col)"
                    @keydown.enter.prevent="saveCellEdit(row)"
                    @keydown.escape="cancelCellEdit"
                  />
                  <textarea
                    v-else-if="col === 'product_description'"
                    v-model="editValue"
                    rows="2"
                    class="w-full min-w-[180px] max-w-[220px] rounded border border-green-500 px-2 py-1 text-sm"
                    :disabled="isCellSaving(row, col)"
                    @keydown.escape="cancelCellEdit"
                  />
                  <input
                    v-else
                    v-model="editValue"
                    type="text"
                    class="w-full min-w-[100px] rounded border border-green-500 px-2 py-1 text-sm"
                    :disabled="isCellSaving(row, col)"
                    @keydown.enter.prevent="saveCellEdit(row)"
                    @keydown.escape="cancelCellEdit"
                  />
                  <span v-if="isCellSaving(row, col)" class="inline-flex items-center gap-1 text-green-600" aria-busy="true">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    <span class="text-xs font-medium">Saving...</span>
                  </span>
                </div>
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    class="rounded bg-green-600 px-2 py-1 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-50"
                    :disabled="isCellSaving(row, col)"
                    @click="saveCellEdit(row)"
                  >
                    Save
                  </button>
                  <button
                    type="button"
                    class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                    :disabled="isCellSaving(row, col)"
                    @click="cancelCellEdit"
                  >
                    Cancel
                  </button>
                </div>
              </div>
            </template>
            <!-- Display: status toggle (click to toggle, saves immediately) + loader -->
            <template v-else-if="col === 'status'">
              <div
                v-if="effectiveCanEdit"
                class="relative flex cursor-pointer flex-col items-center gap-0.5"
                :class="{ 'pointer-events-none opacity-80': isStatusSaving(row) }"
                title="Click to toggle status"
                @click.stop="toggleStatus(row)"
              >
                <span
                  class="relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors"
                  :class="row.status === 'approved' ? 'bg-green-500' : 'bg-red-400'"
                  aria-hidden="true"
                >
                  <span
                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition-transform translate-x-0.5 mt-0.5"
                    :class="row.status === 'approved' ? 'translate-x-5' : 'translate-x-0.5'"
                  />
                </span>
                <span class="text-xs font-medium" :class="row.status === 'approved' ? 'text-green-700' : 'text-red-600'">
                  {{ row.status === 'approved' ? 'On' : 'off' }}
                </span>
                <span v-if="isStatusSaving(row)" class="absolute -right-1 -top-0.5 flex items-center gap-0.5 text-green-600" aria-busy="true">
                  <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                  <span class="text-[10px] font-medium">Saving...</span>
                </span>
              </div>
              <div v-else class="flex flex-col items-center gap-0.5">
                <span
                  class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-default rounded-full border-2 border-transparent transition-colors"
                  :class="row.status === 'approved' ? 'bg-green-500' : 'bg-red-400'"
                  aria-hidden="true"
                >
                  <span
                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition-transform translate-x-0.5 mt-0.5"
                    :class="row.status === 'approved' ? 'translate-x-5' : 'translate-x-0.5'"
                  />
                </span>
                <span class="text-xs font-medium" :class="row.status === 'approved' ? 'text-green-700' : 'text-red-600'">
                  {{ row.status === 'approved' ? 'On' : 'off' }}
                </span>
              </div>
            </template>
            <template v-else-if="col === 'product_description'">
              <span class="block max-w-[220px] text-gray-700 whitespace-normal line-clamp-2" title="Double-click to edit">{{ formatValue(row, col) }}</span>
            </template>
            <template v-else-if="col === 'vat_amount'">
              <span title="Double-click to edit">{{ row.vat_amount_raw != null ? row.vat_amount_raw + '%' : '—' }}</span>
            </template>
            <template v-else-if="col === 'full_amount'">
              <span class="font-bold text-gray-900">{{ row[col] !== '—' ? 'AED ' : '' }}{{ formatValue(row, col) }}</span>
            </template>
            <template v-else-if="col === 'amount_without_vat' || col === 'vat_amount_currency'">
              <span :title="col === 'amount_without_vat' ? 'Double-click to edit' : undefined">{{ row[col] !== '—' ? 'AED ' : '' }}{{ formatValue(row, col) }}</span>
            </template>
            <template v-else>
              <span :title="isEditable(col) ? 'Double-click to edit' : undefined">{{ formatValue(row, col) }}</span>
            </template>
          </td>
          <td class="whitespace-nowrap px-4 py-3 text-center">
            <div class="inline-flex items-center gap-1">
              <button
                v-if="canView"
                type="button"
                class="rounded p-1.5 text-blue-600 hover:bg-blue-50"
                title="View"
                @click.stop="goToView(row)"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </button>
              <button
                v-if="effectiveCanEdit"
                type="button"
                class="rounded p-1.5 text-green-600 hover:bg-green-50"
                title="Edit (open edit form)"
                @click.stop="openEditModal(row)"
              >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                </button>
                <button
                  type="button"
                  class="rounded p-1.5 text-amber-600 hover:bg-amber-50"
                  title="View History"
                  @click.stop="goToViewHistory(row)"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </button>
                <button
                  v-if="effectiveCanDelete"
                  type="button"
                  class="rounded p-1.5 text-red-600 hover:bg-red-50"
                  title="Delete"
                  @click.stop="onDelete(row)"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
