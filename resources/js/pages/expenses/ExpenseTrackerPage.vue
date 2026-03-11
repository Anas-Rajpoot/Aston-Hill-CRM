<script setup>
/**
 * Expense Tracker – Filters + Advanced Filters (DD-MM-YYYY), sortable editable datatable, delete with confirmation.
 */
import { ref, computed, onMounted } from 'vue'
import { toDdMmYyyy, fromDdMmYyyy } from '@/lib/dateFormat'
import { useTablePageSize } from '@/composables/useTablePageSize'
import expensesApi from '@/services/expensesApi'
import { useAuthStore } from '@/stores/auth'
import AdvancedFilters from '@/components/expenses/AdvancedFilters.vue'
import AddExpenseModal from '@/components/expenses/AddExpenseModal.vue'
import EditExpenseModal from '@/components/expenses/EditExpenseModal.vue'
import ExpenseDetailModal from '@/components/expenses/ExpenseDetailModal.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import ExpenseTable from '@/components/expenses/ExpenseTable.vue'
import ExpenseEditHistoryModal from '@/components/expenses/ExpenseEditHistoryModal.vue'
import HorizontalScrollToolbar from '@/components/common/HorizontalScrollToolbar.vue'
import Toast from '@/components/Toast.vue'
import DeleteOtpModal from '@/components/DeleteOtpModal.vue'
import { canModuleAction } from '@/lib/accessControl'

const auth = useAuthStore()
const { perPage, perPageOptions, perPageReady, setPerPage } = useTablePageSize('expenses')
const canView = computed(() =>
  canModuleAction(auth.user, 'expense-tracker', 'view', [
    'expense_tracker.view',
    'expense_tracker.list',
  ])
)
const canCreate = computed(() => canModuleAction(auth.user, 'expense-tracker', 'create', ['expense_tracker.create']))
const canExport = computed(() =>
  canModuleAction(auth.user, 'expense-tracker', 'export', [
    'expense_tracker.export_expenses',
    'expense_tracker.export',
  ])
)
const canEdit = computed(() => canModuleAction(auth.user, 'expense-tracker', 'edit', ['expense_tracker.edit', 'expense_tracker.update']))
const canDelete = computed(() => canModuleAction(auth.user, 'expense-tracker', 'delete', ['expense_tracker.delete']))
const canHistory = computed(() =>
  canModuleAction(auth.user, 'expense-tracker', 'view', [
    'expense_tracker.history',
    'expense_tracker.view-history',
  ])
)

const loading = ref(true)
const loadError = ref(null)
const filterOptions = ref({
  categories: [],
  vat_options: [{ value: 'all', label: 'All' }, { value: 'yes', label: 'Yes' }, { value: 'no', label: 'No' }],
  vat_percent_options: [],
  added_by_users: [],
  status_options: [],
})
const summary = ref({
  total_expenses: 0,
  total_amount: 0,
  pending_approval: 0,
  approved: 0,
})
const expenses = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: auth.defaultTablePageSize || 25, total: 0 })
const allColumns = ref([])
const visibleColumns = ref([
  'id', 'status', 'created_at', 'expense_date', 'product_category', 'product_description', 'invoice_number', 'vat_amount',
  'amount_without_vat', 'vat_amount_currency', 'full_amount', 'added_by',
])
const sort = ref('expense_date')
const order = ref('desc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)
const exportLoading = ref(false)
const importLoading = ref(false)
const importInputRef = ref(null)
const bulkLoading = ref(false)
const bulkStatus = ref('')
const selectedExpenseIds = ref([])
const bulkDeleteConfirmVisible = ref(false)
const expenseToDelete = ref(null)
const deleting = ref(false)
const addModalVisible = ref(false)
const editModalVisible = ref(false)
const expenseIdForEdit = ref(null)
const detailModalVisible = ref(false)
const selectedExpenseId = ref(null)
const historyModalVisible = ref(false)
const historyExpenseId = ref(null)
const historyExpenseRef = ref('')
const savingRowId = ref(null)
const savingCell = ref({ rowId: null, col: null })

const CANONICAL_COLUMN_ORDER = [
  'id', 'status', 'created_at', 'expense_date', 'product_category', 'product_description', 'invoice_number', 'vat_amount',
  'amount_without_vat', 'vat_amount_currency', 'full_amount', 'added_by',
]

function normalizeVisibleColumns(cols) {
  const unique = [...new Set((Array.isArray(cols) ? cols : []).filter(Boolean))]
  if (!unique.includes('id')) unique.unshift('id')
  const canonical = CANONICAL_COLUMN_ORDER.filter((col) => unique.includes(col))
  const extras = unique.filter((col) => !CANONICAL_COLUMN_ORDER.includes(col))
  return [...canonical, ...extras]
}

const selectedCount = computed(() => selectedExpenseIds.value.length)

const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

const filters = ref({
  expense_date_from: '',
  expense_date_to: '',
  created_from: '',
  created_to: '',
  product_category: '',
  added_by: '',
  added_by_user_id: '',
  amount_min: '',
  amount_max: '',
  vat_applicable: 'all',
  product_description: '',
  invoice_number: '',
  status: '',
})

function buildParams() {
  const f = filters.value
  const p = {
    page: meta.value.current_page,
    per_page: perPage.value,
    sort: sort.value,
    order: order.value,
    columns: visibleColumns.value,
  }
  if (f.expense_date_from) p.expense_date_from = f.expense_date_from
  if (f.expense_date_to) p.expense_date_to = f.expense_date_to
  if (f.created_from) p.created_from = f.created_from
  if (f.created_to) p.created_to = f.created_to
  if (f.product_category) p.product_category = f.product_category
  if (f.added_by) p.added_by = f.added_by
  if (f.added_by_user_id) p.added_by_user_id = f.added_by_user_id
  if (f.amount_min !== '' && f.amount_min != null) p.amount_min = f.amount_min
  if (f.amount_max !== '' && f.amount_max != null) p.amount_max = f.amount_max
  if (f.vat_applicable && f.vat_applicable !== 'all') p.vat_applicable = f.vat_applicable
  if (f.product_description) p.product_description = f.product_description
  if (f.invoice_number) p.invoice_number = f.invoice_number
  if (f.status) p.status = f.status
  return p
}

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const [listRes, summaryRes] = await Promise.all([
      expensesApi.index(buildParams()),
      expensesApi.summary(),
    ])
    expenses.value = listRes.data?.data ?? []
    selectedExpenseIds.value = []
    bulkStatus.value = ''
    meta.value = listRes.data?.meta ?? { current_page: 1, last_page: 1, per_page: auth.defaultTablePageSize || 25, total: 0 }
    summary.value = summaryRes.data ?? summary.value
  } catch (e) {
    loadError.value = e?.response?.data?.message || 'Failed to load expenses.'
    expenses.value = []
  } finally {
    loading.value = false
  }
}

async function loadFilters() {
  try {
    const { data } = await expensesApi.filters()
    filterOptions.value = {
      categories: data.categories ?? [],
      vat_options: data.vat_options ?? filterOptions.value.vat_options,
      vat_percent_options: data.vat_percent_options ?? [],
      added_by_users: data.added_by_users ?? [],
      status_options: data.status_options ?? [],
    }
  } catch {
    //
  }
}

async function loadColumns() {
  try {
    const { data } = await expensesApi.columns()
    allColumns.value = data.all_columns ?? []
    visibleColumns.value = normalizeVisibleColumns(data.visible_columns ?? visibleColumns.value)
  } catch {
    //
  }
}

function toggleSelectRow({ id, checked }) {
  const n = Number(id)
  if (!n) return
  if (checked) {
    if (!selectedExpenseIds.value.includes(n)) selectedExpenseIds.value.push(n)
    return
  }
  selectedExpenseIds.value = selectedExpenseIds.value.filter((x) => x !== n)
}

function toggleSelectAll(checked) {
  const pageIds = (expenses.value || []).map((r) => Number(r?.id)).filter(Boolean)
  if (!checked) {
    selectedExpenseIds.value = selectedExpenseIds.value.filter((id) => !pageIds.includes(id))
    return
  }
  selectedExpenseIds.value = [...new Set([...selectedExpenseIds.value, ...pageIds])]
}

async function onBulkStatusChange() {
  if (!canEdit.value || !selectedExpenseIds.value.length || !bulkStatus.value) return
  bulkLoading.value = true
  try {
    const targetStatus = bulkStatus.value === 'on' ? 'approved' : 'pending'
    const selectedSet = new Set(selectedExpenseIds.value)
    const targets = (expenses.value || []).filter((row) => selectedSet.has(Number(row?.id)))
    const results = await Promise.allSettled(
      targets.map((row) => expensesApi.update(row.id, { status: targetStatus }))
    )
    const ok = results.filter((r) => r.status === 'fulfilled').length
    const failed = results.length - ok
    if (ok > 0) toast('success', `Status updated for ${ok} expense${ok > 1 ? 's' : ''}.`)
    if (failed > 0) toast('error', `Failed to update ${failed} expense${failed > 1 ? 's' : ''}.`)
    await load()
  } catch {
    toast('error', 'Failed to update selected expenses.')
  } finally {
    bulkLoading.value = false
  }
}

async function onBulkDelete() {
  if (!canDelete.value || !selectedExpenseIds.value.length) return
  bulkDeleteConfirmVisible.value = true
}

function closeBulkDeleteConfirm() {
  bulkDeleteConfirmVisible.value = false
}

async function confirmBulkDelete() {
  if (!canDelete.value || !selectedExpenseIds.value.length) {
    closeBulkDeleteConfirm()
    return
  }
  bulkLoading.value = true
  try {
    const ids = [...selectedExpenseIds.value]
    const results = await Promise.allSettled(ids.map((id) => expensesApi.destroy(id)))
    const ok = results.filter((r) => r.status === 'fulfilled').length
    const failed = results.length - ok
    if (ok === 0) {
      toast('error', `Could not delete selected expense${ids.length > 1 ? 's' : ''}.`)
    } else if (failed === 0) {
      toast('success', `${ok} expense${ok > 1 ? 's' : ''} deleted successfully.`)
    } else {
      toast('error', `Deleted ${ok} expense${ok > 1 ? 's' : ''}, but failed to delete ${failed}.`)
    }
    await load()
  } catch {
    toast('error', 'Failed to delete selected expenses.')
  } finally {
    bulkLoading.value = false
    closeBulkDeleteConfirm()
  }
}

function applyFilters() {
  meta.value.current_page = 1
  load()
}

function resetFilters() {
  filters.value = {
    expense_date_from: '',
    expense_date_to: '',
    created_from: '',
    created_to: '',
    product_category: '',
    added_by: '',
    added_by_user_id: '',
    amount_min: '',
    amount_max: '',
    vat_applicable: 'all',
    product_description: '',
    invoice_number: '',
    status: '',
  }
  meta.value.current_page = 1
  load()
}

function clearFiltersOnly() {
  filters.value = {
    expense_date_from: '',
    expense_date_to: '',
    created_from: '',
    created_to: '',
    product_category: '',
    added_by: '',
    added_by_user_id: '',
    amount_min: '',
    amount_max: '',
    vat_applicable: 'all',
    product_description: '',
    invoice_number: '',
    status: '',
  }
}

function onAddCreated() {
  // Keep list state deterministic after create (same result as hard refresh).
  clearFiltersOnly()
  meta.value.current_page = 1
  loadError.value = null
  toast('success', 'Expense created successfully.')
  loadFilters()
  load()
}

function onSort({ sort: s, order: o }) {
  sort.value = s
  order.value = o
  load()
}

const quickDateFromDisplay = computed({
  get: () => toDdMmYyyy(filters.value.expense_date_from),
  set: (v) => { filters.value.expense_date_from = fromDdMmYyyy(v) || '' },
})
const quickDateToDisplay = computed({
  get: () => toDdMmYyyy(filters.value.expense_date_to),
  set: (v) => { filters.value.expense_date_to = fromDdMmYyyy(v) || '' },
})

async function onInlineEdit({ row, payload, field, value, isStatusToggle, col }) {
  if (!row?.id) return
  if (isStatusToggle) savingRowId.value = row.id
  else if (col != null) savingCell.value = { rowId: row.id, col }
  const data = payload ?? (() => {
    const p = {}
    if (field === 'vat_amount') p.vat_percent = value
    else if (field === 'expense_date') p.expense_date = value
    else p[field] = value
    return p
  })()
  try {
    await expensesApi.update(row.id, data)
    load()
  } catch {
    load()
  } finally {
    savingRowId.value = null
    savingCell.value = { rowId: null, col: null }
  }
}

function onPageChange(page) {
  meta.value.current_page = page
  load()
}

function onPerPageChange(e) {
  setPerPage(e.target.value)
  meta.value.current_page = 1
  load()
}
async function onSaveColumns(cols) {
  try {
    const normalized = normalizeVisibleColumns(cols)
    await expensesApi.saveColumns(normalized)
    visibleColumns.value = normalized
    load()
  } catch {
    //
  }
}

function escapeCsv(val) {
  if (val == null) return ''
  const s = String(val)
  if (s.includes(',') || s.includes('"') || s.includes('\n')) return '"' + s.replace(/"/g, '""') + '"'
  return s
}

async function onExport() {
  if (!canExport.value) return
  const params = { ...buildParams(), page: 1, per_page: 5000 }
  exportLoading.value = true
  try {
    const { data } = await expensesApi.index(params)
    const rows = data.data ?? []
    const cols = visibleColumns.value
    const headers = cols.map((c) => (COLUMN_HEADERS[c] ?? c))
    const csvRows = [headers.map(escapeCsv).join(',')]
    for (const row of rows) {
      csvRows.push(cols.map((col) => escapeCsv(row[col])).join(','))
    }
    const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `expenses-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    //
  } finally {
    exportLoading.value = false
  }
}

function onDownloadTemplate() {
  const preferredOrder = [
    'status', 'created_at', 'expense_date', 'product_category', 'product_description', 'invoice_number',
    'vat_amount', 'amount_without_vat', 'vat_amount_currency', 'full_amount',
    'added_by',
  ]
  const fromColumns = (allColumns.value || []).map((c) => c?.key).filter(Boolean)
  const selected = fromColumns.length ? preferredOrder.filter((k) => fromColumns.includes(k)) : [...visibleColumns.value]
  const cols = selected.length ? selected : preferredOrder
  const headers = cols.map((c) => (COLUMN_HEADERS[c] ?? c))
  const finalHeaders = [...headers, 'Other 1', 'Other 2']
  const sampleRows = [
    ['11-Mar-2026', 'water', 'Office water supply', 'INV-EXP-001', '5', '100.00', '5.00', '105.00', (auth.user?.name || 'Admin User'), '11-Mar-2026', 'pending', 'Note A', 'Ref A'],
    ['12-Mar-2026', 'telecom', 'Internet renewal', 'INV-EXP-002', '5', '250.00', '12.50', '262.50', (auth.user?.name || 'Admin User'), '12-Mar-2026', 'approved', 'Note B', 'Ref B'],
    ['13-Mar-2026', 'others', 'Office miscellaneous', 'INV-EXP-003', '0', '80.00', '0.00', '80.00', (auth.user?.name || 'Admin User'), '13-Mar-2026', 'pending', 'Note C', 'Ref C'],
  ]

  const csvRows = [finalHeaders.map(escapeCsv).join(',')]
  for (const row of sampleRows) {
    csvRows.push(row.map(escapeCsv).join(','))
  }

  const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'expenses-template.csv'
  a.click()
  URL.revokeObjectURL(url)
}

function openImportCsv() {
  importInputRef.value?.click()
}

async function onImportCsvChange(event) {
  const file = event?.target?.files?.[0]
  if (!file) return
  importLoading.value = true
  try {
    const fd = new FormData()
    fd.append('file', file)
    const { data } = await expensesApi.importCsv(fd)
    toast('success', data?.message || 'CSV imported successfully.')
    meta.value.current_page = 1
    await load()
  } catch (e) {
    const msg = e?.response?.data?.message || 'CSV import failed.'
    toast('error', msg)
  } finally {
    importLoading.value = false
    if (event?.target) event.target.value = ''
  }
}

const COLUMN_HEADERS = {
  expense_date: 'Expense Date',
  product_category: 'Product Category',
  product_description: 'Product Description',
  invoice_number: 'Invoice Number',
  vat_amount: 'VAT %',
  amount_without_vat: 'Amount Without VAT (AED)',
  vat_amount_currency: 'VAT Amount (AED)',
  full_amount: 'Total Amount (AED)',
  added_by: 'Added By',
  created_at: 'Created',
  status: 'Status',
}

function openDetailModal(row) {
  if (!canView.value) return
  if (row?.id) {
    selectedExpenseId.value = row.id
    detailModalVisible.value = true
  }
}

function closeDetailModal() {
  detailModalVisible.value = false
  selectedExpenseId.value = null
}

function openEditModal(row) {
  if (!canEdit.value) return
  if (row?.id) {
    expenseIdForEdit.value = row.id
    editModalVisible.value = true
  }
}

function onOpenEditFromDetail(row) {
  closeDetailModal()
  openEditModal(row)
}

function closeEditModal() {
  editModalVisible.value = false
  expenseIdForEdit.value = null
}

const openHistoryModal = (row) => {
  if (!canHistory.value) return
  if (row?.id) {
    historyExpenseId.value = row.id
    historyExpenseRef.value = row.expense_id || ''
    historyModalVisible.value = true
  }
}

const closeHistoryModal = () => {
  historyModalVisible.value = false
  historyExpenseId.value = null
  historyExpenseRef.value = ''
}

function openDeleteConfirm(row) {
  if (!canDelete.value) return
  expenseToDelete.value = row
}

function closeDeleteConfirm() {
  expenseToDelete.value = null
}

async function confirmDelete() {
  if (!canDelete.value) return
  const row = expenseToDelete.value
  if (!row?.id) {
    closeDeleteConfirm()
    return
  }
  deleting.value = true
  try {
    await expensesApi.destroy(row.id)
    toast('success', 'Expense deleted successfully.')
    closeDeleteConfirm()
    load()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to delete expense.')
    load()
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  if (!canView.value) return
  loadFilters()
  loadColumns()
  load()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-7xl space-y-4">
      <div v-if="loadError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ loadError }}
      </div>
      <div v-if="!canView" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        You do not have permission to view expense tracker.
      </div>

      <!-- Summary cards (above advanced filters) -->
      <div v-if="canView" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-600">Total Expenses</span>
            <span class="rounded bg-brand-primary-light p-1.5 text-brand-primary">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
              </svg>
            </span>
          </div>
          <p class="mt-2 text-2xl font-semibold text-gray-900">{{ summary.total_expenses }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-600">Total Amount</span>
            <span class="rounded bg-brand-primary-light p-1.5 text-brand-primary">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
              </svg>
            </span>
          </div>
          <p class="mt-2 text-2xl font-semibold text-gray-900">AED {{ summary.total_amount?.toLocaleString('en-US', { minimumFractionDigits: 2 }) ?? '0.00' }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-600">Pending Approval</span>
            <span class="rounded bg-amber-100 p-1.5 text-amber-600">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </span>
          </div>
          <p class="mt-2 text-2xl font-semibold text-gray-900">{{ summary.pending_approval }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-600">Approved</span>
            <span class="rounded bg-brand-primary-light p-1.5 text-brand-primary">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </span>
          </div>
          <p class="mt-2 text-2xl font-semibold text-gray-900">{{ summary.approved }}</p>
        </div>
      </div>

      <!-- Filters: Status, Product Category, Apply/Reset, Advanced Filters, Customize Columns -->
      <div v-if="canView" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <input
          ref="importInputRef"
          type="file"
          accept=".csv,text/csv"
          class="hidden"
          @change="onImportCsvChange"
        />
        <HorizontalScrollToolbar :step="340">
          <div class="w-[180px] shrink-0">
            <label class="mb-1 block text-xs font-medium text-gray-600">Status</label>
            <select
              v-model="filters.status"
              class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
              :disabled="loading"
            >
              <option value="">All</option>
              <option v-for="o in filterOptions.status_options" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div class="w-[220px] shrink-0">
            <label class="mb-1 block text-xs font-medium text-gray-600">Product Category</label>
            <select
              v-model="filters.product_category"
              class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
              :disabled="loading"
            >
              <option value="">All Categories</option>
              <option v-for="c in filterOptions.categories" :key="c.value" :value="c.value">{{ c.label }}</option>
            </select>
          </div>
          <div class="flex shrink-0 gap-2">
            <button
              type="button"
              class="rounded bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-50"
              :disabled="loading"
              @click="applyFilters"
            >
              Apply Filters
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
              :disabled="loading"
              @click="resetFilters"
            >
              Reset
            </button>
          </div>
          <div class="flex shrink-0 gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
              @click="advancedVisible = !advancedVisible"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
              Advanced Filters
            </button>
            <span
              v-if="selectedCount > 0"
              class="inline-flex items-center rounded border border-brand-primary/30 bg-brand-primary-light px-2.5 py-2 text-xs font-medium text-brand-primary"
            >
              {{ selectedCount }} selected
            </span>
            <select
              v-if="canEdit && selectedCount > 0"
              v-model="bulkStatus"
              class="rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
              :disabled="loading || bulkLoading || selectedCount === 0"
            >
              <option value="">Bulk Status</option>
              <option value="approved">On</option>
              <option value="pending">Off</option>
            </select>
            <button
              v-if="canEdit && selectedCount > 0"
              type="button"
              class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
              :disabled="loading || bulkLoading || selectedCount === 0 || !bulkStatus"
              @click="onBulkStatusChange"
            >
              Change Status
            </button>
            <button
              v-if="canDelete && selectedCount > 0"
              type="button"
              class="inline-flex items-center rounded border border-red-300 bg-white px-3 py-2 text-sm text-red-700 hover:bg-red-50 disabled:opacity-50"
              :disabled="loading || bulkLoading || selectedCount === 0"
              @click="onBulkDelete"
            >
              Delete Selected
            </button>
            <button
              v-if="canCreate"
              type="button"
              class="inline-flex items-center rounded-lg bg-brand-primary px-3 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover"
              @click="onDownloadTemplate"
            >
              <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
              Template
            </button>
            <button
              v-if="canCreate"
              type="button"
              class="inline-flex items-center rounded-lg bg-brand-primary px-3 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-50"
              :disabled="loading || importLoading"
              @click="openImportCsv"
            >
              <svg v-if="importLoading" class="mr-1.5 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <svg v-else class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              {{ importLoading ? 'Importing...' : 'Import' }}
            </button>
            <button
              v-if="canExport"
              type="button"
              class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
              :disabled="loading || exportLoading"
              @click="onExport"
            >
              <svg v-if="exportLoading" class="mr-1.5 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <svg v-else class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
              {{ exportLoading ? 'Exporting...' : 'Export Expenses' }}
            </button>
            <button
              v-if="canCreate"
              type="button"
              class="inline-flex items-center rounded-lg bg-brand-primary px-3 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover"
              @click="addModalVisible = true"
            >
              <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Add Expense
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
              @click="columnModalVisible = true"
            >
              Customize Columns
              <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
          </div>
        </HorizontalScrollToolbar>
      </div>

      <AdvancedFilters
        v-if="canView"
        :visible="advancedVisible"
        :filters="filters"
        :filter-options="filterOptions"
        :loading="loading"
        @apply="applyFilters"
        @reset="resetFilters"
      />

      <div v-if="canView" class="overflow-hidden rounded-xl border-2 border-black bg-white shadow-sm">
        <ExpenseTable
          :columns="visibleColumns"
          :data="expenses"
          :sort="sort"
          :order="order"
          :loading="loading"
          :saving-row-id="savingRowId"
          :saving-cell="savingCell"
          :current-page="meta.current_page"
          :per-page="perPage"
          :filter-options="filterOptions"
          :can-edit="canEdit"
          :can-delete="canDelete"
          :can-view="canView"
          :can-history="canHistory"
          :selectable="canEdit || canDelete"
          :selected-ids="selectedExpenseIds"
          @sort="onSort"
          @view="openDetailModal"
          @open-edit="openEditModal"
          @edit="onInlineEdit"
          @view-history="openHistoryModal"
          @delete="openDeleteConfirm"
          @toggle-select-row="toggleSelectRow"
          @toggle-select-all="toggleSelectAll"
        />
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-black bg-white px-4 py-3">
          <!-- Left: entries info -->
          <p class="text-sm text-gray-600">
            Showing {{ meta.total ? (meta.current_page - 1) * perPage + 1 : 0 }} to {{ Math.min(meta.current_page * perPage, meta.total) }} of {{ meta.total }} entries
          </p>

          <!-- Right: Number of rows + Previous / Page X of Y / Next -->
          <div class="flex items-center gap-4">
            <div class="flex items-center gap-2 text-sm text-gray-600">
              <span class="whitespace-nowrap font-medium">Number of rows</span>
              <select
                id="expense-per-page"
                :value="perPage"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm min-w-[80px] text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                @change="onPerPageChange"
              >
                <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </div>

            <div class="flex items-center gap-1.5">
              <button
                type="button"
                :disabled="meta.current_page <= 1"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                @click="onPageChange(meta.current_page - 1)"
              >Previous</button>
              <span class="rounded-md border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm text-gray-700">
                Page {{ meta.current_page }} of {{ meta.last_page }}
              </span>
              <button
                type="button"
                :disabled="meta.current_page >= meta.last_page"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                @click="onPageChange(meta.current_page + 1)"
              >Next
            </button>
          </div>
        </div>
      </div>
    </div>

    <AddExpenseModal
      :visible="addModalVisible"
      :categories="filterOptions.categories"
      :vat-percent-options="filterOptions.vat_percent_options"
      :added-by-users="filterOptions.added_by_users"
      :current-user-id="auth.user?.id"
      @close="addModalVisible = false"
      @created="onAddCreated"
    />

    <EditExpenseModal
      :visible="editModalVisible"
      :expense-id="expenseIdForEdit"
      :categories="filterOptions.categories"
      :vat-percent-options="filterOptions.vat_percent_options"
      :added-by-users="filterOptions.added_by_users"
      @close="closeEditModal"
      @updated="toast('success', 'Expense updated successfully.'); load(); closeEditModal()"
    />

    <ExpenseDetailModal
      :visible="detailModalVisible"
      :expense-id="selectedExpenseId"
      @close="closeDetailModal"
      @open-edit="onOpenEditFromDetail"
    />

    <ExpenseEditHistoryModal
      :visible="historyModalVisible"
      :expense-id="historyExpenseId"
      :expense-ref="historyExpenseRef"
      @close="closeHistoryModal"
    />

    <ColumnCustomizerModal
      :visible="columnModalVisible"
      :all-columns="allColumns"
      :visible-columns="visibleColumns"
      @update:visible="columnModalVisible = $event"
      @save="onSaveColumns"
    />

    <DeleteOtpModal
      :visible="!!expenseToDelete"
      title="Delete Expense"
      :item-label="expenseToDelete ? `Expense #${expenseToDelete.id}` : 'this expense'"
      :loading="deleting"
      @confirm="confirmDelete"
      @close="closeDeleteConfirm"
    />

    <DeleteOtpModal
      :visible="bulkDeleteConfirmVisible"
      title="Delete Expenses"
      :item-label="selectedCount ? `${selectedCount} selected expense${selectedCount > 1 ? 's' : ''}` : 'selected expenses'"
      :loading="bulkLoading"
      @confirm="confirmBulkDelete"
      @close="closeBulkDeleteConfirm"
    />

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</div>
</template>
