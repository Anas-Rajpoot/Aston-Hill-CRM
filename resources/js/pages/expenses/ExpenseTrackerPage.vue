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
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Toast from '@/components/Toast.vue'

const auth = useAuthStore()
const { perPage, perPageOptions, perPageReady, setPerPage } = useTablePageSize('expenses')
const permissions = computed(() => auth.user?.permissions ?? [])
const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) && r.some((x) => (typeof x === 'string' ? x === 'superadmin' : x?.name === 'superadmin'))
})
const canCreate = computed(() => isSuperAdmin.value || permissions.value.includes('expense_tracker.create'))
const canExport = computed(() => isSuperAdmin.value || permissions.value.includes('expense_tracker.export_expenses') || permissions.value.includes('expense_tracker.export'))
const canEdit = computed(() => isSuperAdmin.value || permissions.value.includes('expense_tracker.edit') || permissions.value.includes('expense_tracker.update'))
const canDelete = computed(() => isSuperAdmin.value || permissions.value.includes('expense_tracker.delete'))

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
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const allColumns = ref([])
const visibleColumns = ref([
  'status', 'expense_date', 'product_category', 'product_description', 'invoice_number', 'vat_amount',
  'amount_without_vat', 'vat_amount_currency', 'full_amount', 'added_by', 'created_at',
])
const sort = ref('expense_date')
const order = ref('desc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)
const exportLoading = ref(false)
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
    meta.value = listRes.data?.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: 0 }
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
    visibleColumns.value = data.visible_columns ?? visibleColumns.value
  } catch {
    //
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
const pageNumbers = computed(() => {
  const n = meta.value.last_page || 1
  return Array.from({ length: n }, (_, i) => i + 1)
})

async function onSaveColumns(cols) {
  try {
    await expensesApi.saveColumns(cols)
    visibleColumns.value = cols
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

const COLUMN_HEADERS = {
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

function openDetailModal(row) {
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
  if (row?.id) {
    expenseIdForEdit.value = row.id
    editModalVisible.value = true
  }
}

function closeEditModal() {
  editModalVisible.value = false
  expenseIdForEdit.value = null
}

const openHistoryModal = (row) => {
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
  expenseToDelete.value = row
}

function closeDeleteConfirm() {
  expenseToDelete.value = null
}

async function confirmDelete() {
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
  loadFilters()
  loadColumns()
  load()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-7xl space-y-4">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-semibold text-gray-900 leading-tight">Expense Tracker</h1>
            <Breadcrumbs />
          </div>
          <p class="mt-0.5 text-sm text-gray-500">Track and manage operational expenses with detailed financial records.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
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
            class="inline-flex items-center rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700"
            @click="addModalVisible = true"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Expense
          </button>
        </div>
      </div>

      <div v-if="loadError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ loadError }}
      </div>

      <!-- Summary cards (above advanced filters) -->
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-600">Total Expenses</span>
            <span class="rounded bg-green-100 p-1.5 text-green-600">
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
            <span class="rounded bg-green-100 p-1.5 text-green-600">
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
            <span class="rounded bg-green-100 p-1.5 text-green-600">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </span>
          </div>
          <p class="mt-2 text-2xl font-semibold text-gray-900">{{ summary.approved }}</p>
        </div>
      </div>

      <!-- Filters: Status, Product Category, Apply/Reset, Advanced Filters, Customize Columns -->
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-end gap-4">
          <div class="min-w-[140px] max-w-[180px]">
            <label class="mb-1 block text-xs font-medium text-gray-600">Status</label>
            <select
              v-model="filters.status"
              class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              :disabled="loading"
            >
              <option value="">All</option>
              <option v-for="o in filterOptions.status_options" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div class="min-w-[140px] max-w-[200px]">
            <label class="mb-1 block text-xs font-medium text-gray-600">Product Category</label>
            <select
              v-model="filters.product_category"
              class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              :disabled="loading"
            >
              <option value="">All Categories</option>
              <option v-for="c in filterOptions.categories" :key="c.value" :value="c.value">{{ c.label }}</option>
            </select>
          </div>
          <div class="flex gap-2">
            <button
              type="button"
              class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
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
          <div class="flex gap-2 ml-auto">
            <button
              type="button"
              class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
              @click="advancedVisible = !advancedVisible"
            >
              Advanced Filters
              <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <button
              type="button"
              class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
              @click="columnModalVisible = true"
            >
              Customize Columns
              <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <AdvancedFilters
        :visible="advancedVisible"
        :filters="filters"
        :filter-options="filterOptions"
        :loading="loading"
        @apply="applyFilters"
        @reset="resetFilters"
      />

      <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
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
          @sort="onSort"
          @view="openDetailModal"
          @open-edit="openEditModal"
          @edit="onInlineEdit"
          @view-history="openHistoryModal"
          @delete="openDeleteConfirm"
        />
        <div class="flex flex-wrap items-center gap-4 border-t border-gray-200 bg-white px-4 py-3">
          <p class="text-sm text-gray-600">
            Showing {{ meta.total ? (meta.current_page - 1) * perPage + 1 : 0 }} to {{ Math.min(meta.current_page * perPage, meta.total) }} of {{ meta.total }} entries
          </p>
          <div class="flex items-center gap-2">
            <label for="expense-per-page" class="text-sm text-gray-600">Number of rows</label>
            <select
              id="expense-per-page"
              :value="perPage"
              class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm min-w-[85px] text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
              @change="onPerPageChange"
            >
              <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
            </select>
          </div>
          <div v-if="meta.last_page > 1" class="ml-auto flex items-center gap-1">
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="meta.current_page <= 1"
              @click="onPageChange(meta.current_page - 1)"
            >
              Previous
            </button>
            <button
              v-for="p in pageNumbers"
              :key="p"
              type="button"
              class="min-w-[2rem] rounded border px-3 py-1.5 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50"
              :class="p === meta.current_page ? 'border-green-600 bg-green-600 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
              :disabled="p === meta.current_page"
              @click="onPageChange(p)"
            >
              {{ p }}
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="meta.current_page >= meta.last_page"
              @click="onPageChange(meta.current_page + 1)"
            >
              Next
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
      @created="toast('success', 'Expense created successfully.'); load()"
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

    <Teleport to="body">
      <div
        v-if="expenseToDelete"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/50 p-4"
        role="dialog"
        aria-modal="true"
        @click.self="closeDeleteConfirm"
      >
        <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">Confirm Delete</h2>
            <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100" aria-label="Close" @click="closeDeleteConfirm">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <div class="px-6 py-4">
            <p class="text-sm text-gray-600">Are you sure you want to delete this expense? This action cannot be undone.</p>
          </div>
          <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
            <button type="button" class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50" :disabled="deleting" @click="confirmDelete">
              {{ deleting ? 'Deleting...' : 'Delete' }}
            </button>
            <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="closeDeleteConfirm">Cancel</button>
          </div>
        </div>
      </div>
    </Teleport>

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
