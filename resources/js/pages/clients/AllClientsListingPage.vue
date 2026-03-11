<script setup>
/**
 * All Clients listing – search by company name / account number, filters, sort, customize columns, export.
 */
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import clientsApi from '@/services/clientsApi'
import ClientsFiltersBar from '@/components/clients/ClientsFiltersBar.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import ClientTable from '@/components/clients/ClientTable.vue'
import RenewalAlertsModal from '@/components/clients/RenewalAlertsModal.vue'
import DateInputDdMmYyyy from '@/components/DateInputDdMmYyyy.vue'
import RecordHistoryModal from '@/components/RecordHistoryModal.vue'
import Toast from '@/components/Toast.vue'
import DeleteOtpModal from '@/components/DeleteOtpModal.vue'
import api from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'
import { canModuleAction } from '@/lib/accessControl'

const historyModalVisible = ref(false)
const historyRecordId = ref(null)
const historyRecordLabel = ref('')
function openHistoryModal(row) {
  if (!row?.id) return
  historyRecordId.value = row.id
  historyRecordLabel.value = row.company_name || `Client #${row.id}`
  historyModalVisible.value = true
}
function closeHistoryModal() {
  historyModalVisible.value = false
  historyRecordId.value = null
  historyRecordLabel.value = ''
}
async function fetchClientAudits(id) {
  return await clientsApi.audits(id)
}

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const canCreate = computed(() => canModuleAction(authStore.user, 'all-clients', 'create'))
const canExport = computed(() => canModuleAction(authStore.user, 'all-clients', 'export'))
const canImport = computed(() => canModuleAction(authStore.user, 'all-clients', 'import'))
const canEdit = computed(() => canModuleAction(authStore.user, 'all-clients', 'edit'))
const canDelete = computed(() => canModuleAction(authStore.user, 'all-clients', 'delete'))
const loading = ref(true)

/* ───── Toast ───── */
const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

/* ───── Row selection & Bulk actions ───── */
const selectedIds = ref([])
const allSelected = computed({
  get: () => selectedIds.value.length > 0 && selectedIds.value.length === clients.value.length,
  set: (v) => { selectedIds.value = v ? clients.value.map((r) => Number(r?._row_id ?? r?.id)).filter((id) => Number.isInteger(id) && id > 0) : [] },
})
const bulkLoading = ref(false)
const bulkCsrName = ref('')
const bulkAccountManagerName = ref('')
const csrOptions = ref([])
const accountManagerOptions = ref([])

async function loadBulkDropdownOptions() {
  try {
    const [csrRes, amRes] = await Promise.all([
      api.get('/customer-support/csr-options').then(r => r.data).catch(() => ({ csrs: [] })),
      api.get('/users', { params: { status: 'active', per_page: 200, columns: ['id', 'name'] } }).then(r => r.data).catch(() => ({ data: [] })),
    ])
    csrOptions.value = Array.isArray(csrRes?.csrs)
      ? csrRes.csrs.map(c => (typeof c?.name === 'string' ? c.name.trim() : '')).filter(Boolean)
      : []
    const users = amRes?.data ?? amRes ?? []
    accountManagerOptions.value = (Array.isArray(users) ? users : []).map(u => u.name).filter(Boolean)
  } catch { /* silent */ }
}

function toggleSelect(id) {
  const normalized = Number(id)
  if (!Number.isInteger(normalized) || normalized <= 0) return
  const idx = selectedIds.value.indexOf(normalized)
  if (idx >= 0) selectedIds.value.splice(idx, 1)
  else selectedIds.value.push(normalized)
}

async function bulkAssignCsr() {
  if (!selectedIds.value.length) return
  if (!bulkCsrName.value || !String(bulkCsrName.value).trim()) {
    toast('error', 'Please select a CSR.')
    return
  }
  bulkLoading.value = true
  try {
    await clientsApi.bulkAssignCsr(selectedIds.value, String(bulkCsrName.value).trim())
    selectedIds.value = []
    bulkCsrName.value = ''
    load()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Bulk CSR assignment failed.')
  } finally {
    bulkLoading.value = false
  }
}

async function bulkAssignAccountManager() {
  if (!selectedIds.value.length) return
  if (!bulkAccountManagerName.value || !String(bulkAccountManagerName.value).trim()) {
    toast('error', 'Please select an Account Manager.')
    return
  }
  bulkLoading.value = true
  try {
    await clientsApi.bulkAssignAccountManager(selectedIds.value, String(bulkAccountManagerName.value).trim())
    selectedIds.value = []
    bulkAccountManagerName.value = ''
    load()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Bulk Account Manager assignment failed.')
  } finally {
    bulkLoading.value = false
  }
}

const showBulkDeleteModal = ref(false)
const deleteTargetIds = ref([])
const deleteTargetLabel = ref('selected client(s)')

async function bulkDelete() {
  if (!selectedIds.value.length) return
  deleteTargetIds.value = [...selectedIds.value]
  deleteTargetLabel.value = `${selectedIds.value.length} client(s)`
  showBulkDeleteModal.value = true
}

function openDeleteModalForRow(row) {
  const id = Number(row?._row_id ?? row?.id ?? row?.client_id)
  if (!Number.isInteger(id) || id <= 0) {
    toast('error', 'Could not determine row ID for delete.')
    return
  }
  deleteTargetIds.value = [id]
  deleteTargetLabel.value = row?.company_name || `Client #${id}`
  showBulkDeleteModal.value = true
}

async function confirmBulkDelete() {
  if (!deleteTargetIds.value.length) return
  bulkLoading.value = true
  try {
    await clientsApi.bulkDelete(deleteTargetIds.value)
    showBulkDeleteModal.value = false
    selectedIds.value = selectedIds.value.filter((id) => !deleteTargetIds.value.includes(Number(id)))
    deleteTargetIds.value = []
    toast('success', 'Client(s) deleted successfully.')
    load()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Bulk delete failed.')
  } finally {
    bulkLoading.value = false
  }
}

function closeBulkDeleteModal() {
  if (bulkLoading.value) return
  showBulkDeleteModal.value = false
  deleteTargetIds.value = []
}

async function bulkExport() {
  if (!selectedIds.value.length) return
  exportLoading.value = true
  try {
    const rows = clients.value.filter((r) => selectedIds.value.includes(r.id))
    const cols = visibleColumns.value
    const headers = cols.map((c) => COLUMN_LABELS[c] ?? c)
    const csvRows = [headers.map(escapeCsv).join(',')]
    for (const row of rows) {
      csvRows.push(cols.map((col) => escapeCsv(row[col] ?? '')).join(','))
    }
    const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `all-clients-export-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } finally {
    exportLoading.value = false
  }
}
const clients = ref([])
const TABLE_MODULE = 'all-clients'
const perPageOptions = ref([10, 20, 25, 50])
const meta = ref({ current_page: 1, last_page: 1, per_page: authStore.defaultTablePageSize || 25, total: 0 })
const allColumns = ref([])
const defaultColumns = ref([])
const visibleColumns = ref([
  'submitted_at', 'company_name', 'account_number',
  'trade_license_issuing_authority', 'company_category', 'trade_license_number', 'trade_license_expiry_date',
  'establishment_card_number', 'establishment_card_expiry_date', 'account_taken_from', 'account_mapping_date',
  'account_transfer_given_to', 'account_transfer_given_date',
  'first_bill', 'second_bill', 'third_bill', 'fourth_bill', 'additional_comment_1', 'primary_contact_detail',
  'full_address',
  'csr_name_2', 'csr_name_3',
  'additional_comment_2',
  'renewal_alert',
])
const sort = ref('submitted_at')
const order = ref('desc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)
const exportLoading = ref(false)
const importLoading = ref(false)
const importFileInputRef = ref(null)
const renewalAlertsModalVisible = ref(false)
const renewalAlertsModalCompany = ref('')
const renewalAlertsModalItems = ref([])

const accountNumbers = ref([])
const alertTypes = ref([])
const filterOptions = ref({
  statuses: [],
  managers: [],
  team_leaders: [],
  sales_agents: [],
  submission_types: [],
  service_categories: [],
  service_types: [],
  product_types: [],
  work_order_statuses: [],
  payment_connections: [],
  contract_types: [],
  clawback_chum_options: [],
  company_categories: [],
  account_manager_names: [],
})

const filters = ref({
  company_name: '',
  account_number: '',
  alert_type: '',
  status: '',
  manager_id: '',
  team_leader_id: '',
  sales_agent_id: '',
  submitted_from: '',
  submitted_to: '',
  submission_type: '',
  service_category: '',
  service_type: '',
  product_type: '',
  product_name: '',
  wo_number: '',
  work_order_status: '',
  payment_connection: '',
  contract_type: '',
  clawback_chum: '',
  company_category: '',
  trade_license_number: '',
  establishment_card_number: '',
  account_manager_name: '',
  csr_name_1: '',
  activation_from: '',
  activation_to: '',
  completion_from: '',
  completion_to: '',
  contract_end_from: '',
  contract_end_to: '',
  trade_license_expiry_from: '',
  trade_license_expiry_to: '',
  establishment_card_expiry_from: '',
  establishment_card_expiry_to: '',
})

const toPositiveInt = (value, fallback = 1) => {
  const n = Number(value)
  return Number.isFinite(n) && n > 0 ? Math.floor(n) : fallback
}

function normalizeMeta(incoming) {
  const current = meta.value || {}
  const next = incoming || {}
  return {
    current_page: toPositiveInt(next.current_page, toPositiveInt(current.current_page, 1)),
    last_page: toPositiveInt(next.last_page, toPositiveInt(current.last_page, 1)),
    per_page: toPositiveInt(next.per_page, toPositiveInt(current.per_page, 25)),
    total: toPositiveInt(next.total, toPositiveInt(current.total, 0)),
  }
}

function buildParams() {
  const f = filters.value
  const currentPage = toPositiveInt(meta.value.current_page, 1)
  const perPage = Math.min(toPositiveInt(meta.value.per_page, 25), 50)
  const p = {
    page: currentPage,
    per_page: perPage,
    sort: sort.value,
    order: order.value,
    columns: visibleColumns.value,
  }
  if (f.company_name) p.company_name = f.company_name
  if (f.account_number) p.account_number = f.account_number
  if (f.alert_type) p.alert_type = f.alert_type
  if (f.status) p.status = f.status
  if (f.manager_id) p.manager_id = f.manager_id
  if (f.team_leader_id) p.team_leader_id = f.team_leader_id
  if (f.sales_agent_id) p.sales_agent_id = f.sales_agent_id
  if (f.submitted_from) p.submitted_from = f.submitted_from
  if (f.submitted_to) p.submitted_to = f.submitted_to
  if (f.submission_type) p.submission_type = f.submission_type
  if (f.service_category) p.service_category = f.service_category
  if (f.service_type) p.service_type = f.service_type
  if (f.product_type) p.product_type = f.product_type
  if (f.product_name) p.product_name = f.product_name
  if (f.wo_number) p.wo_number = f.wo_number
  if (f.work_order_status) p.work_order_status = f.work_order_status
  if (f.payment_connection) p.payment_connection = f.payment_connection
  if (f.contract_type) p.contract_type = f.contract_type
  if (f.clawback_chum) p.clawback_chum = f.clawback_chum
  if (f.company_category) p.company_category = f.company_category
  if (f.trade_license_number) p.trade_license_number = f.trade_license_number
  if (f.establishment_card_number) p.establishment_card_number = f.establishment_card_number
  if (f.account_manager_name) p.account_manager_name = f.account_manager_name
  if (f.csr_name_1) p.csr_name_1 = f.csr_name_1
  if (f.activation_from) p.activation_from = f.activation_from
  if (f.activation_to) p.activation_to = f.activation_to
  if (f.completion_from) p.completion_from = f.completion_from
  if (f.completion_to) p.completion_to = f.completion_to
  if (f.contract_end_from) p.contract_end_from = f.contract_end_from
  if (f.contract_end_to) p.contract_end_to = f.contract_end_to
  if (f.trade_license_expiry_from) p.trade_license_expiry_from = f.trade_license_expiry_from
  if (f.trade_license_expiry_to) p.trade_license_expiry_to = f.trade_license_expiry_to
  if (f.establishment_card_expiry_from) p.establishment_card_expiry_from = f.establishment_card_expiry_from
  if (f.establishment_card_expiry_to) p.establishment_card_expiry_to = f.establishment_card_expiry_to
  return p
}

const COLUMN_LABELS = {
  company_name: 'Company Name',
  account_number: 'Account Number',
  submitted_at: 'Submission Date',
  trade_license_issuing_authority: 'Trade License Issuing Authority',
  company_category: 'Company Category',
  trade_license_number: 'Trade License Number',
  trade_license_expiry_date: 'Trade License Expiry Date',
  establishment_card_number: 'Establishment Card Number',
  establishment_card_expiry_date: 'Establishment Card Expiry Date',
  account_taken_from: 'Account Taken From',
  account_mapping_date: 'Account Mapping Date',
  account_transfer_given_to: 'Account Transfer Given To',
  account_transfer_given_date: 'Account Transfer Given Date',
  primary_contact_detail: 'Primary Contact Detail',
  full_address: 'Full Address',
  account_manager_name: 'Account Manager Name',
  csr_name_1: 'CSR Name 1',
  csr_name_2: 'CSR Name 2',
  csr_name_3: 'CSR Name 3',
  first_bill: 'First Bill',
  second_bill: 'Second Bill',
  third_bill: 'Third Bill',
  fourth_bill: 'Fourth Bill',
  additional_comment_1: 'Additional Note',
  additional_comment_2: 'Additional Note 2',
  submission_type: 'Submission Type',
  service_category: 'Service Category',
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
  other: 'Other',
  migration_numbers: 'Migration Numbers',
  wo_number: 'Work Order',
  work_order_status: 'Work Order Status',
  activation_date: 'Activation Date',
  completion_date: 'Completion Date',
  payment_connection: 'Payment Connection',
  contract_type: 'Contract Type',
  contract_end_date: 'Contract End Date',
  clawback_chum: 'Clawback / Chum',
  remarks: 'Remarks',
  renewal_alert: 'Alert',
  additional_notes: 'Additional Notes',
  creator: 'Created By',
}

function escapeCsv(val) {
  if (val == null) return ''
  const s = String(val)
  if (s.includes(',') || s.includes('"') || s.includes('\n')) return '"' + s.replace(/"/g, '""') + '"'
  return s
}

function downloadSampleCsv() {
  const datatableHeaders = [...visibleColumns.value]
  const additionalHeaders = ['contact_1_name', 'contact_1_contact_number']
  const headers = [...new Set([...datatableHeaders, ...additionalHeaders])]

  const sampleRows = [
    {
      company_name: 'Demo Company LLC',
      account_number: 'ACCT-10001',
      status: 'Normal',
      submitted_at: '2026-03-01',
      contact_1_name: 'John Doe',
      contact_1_contact_number: '971501112233',
    },
    {
      company_name: 'Al Noor Enterprises',
      account_number: 'ACCT-10002',
      status: 'Normal',
      submitted_at: '2026-03-05',
      contact_1_name: 'Ali Hassan',
      contact_1_contact_number: '971502223344',
    },
    {
      company_name: 'Blue Wave Trading',
      account_number: 'ACCT-10003',
      status: 'Normal',
      submitted_at: '2026-03-10',
      contact_1_name: 'Sara Khan',
      contact_1_contact_number: '971503334455',
    },
  ]

  const csvRows = [headers.map(escapeCsv).join(',')]
  for (const row of sampleRows) {
    csvRows.push(headers.map((col) => escapeCsv(row[col] ?? '')).join(','))
  }

  const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'all-clients-template.csv'
  a.click()
  URL.revokeObjectURL(url)
}

async function onExport() {
  const params = { ...buildParams(), page: 1, per_page: 50 }
  exportLoading.value = true
  try {
    const data = await clientsApi.index(params)
    const rows = data.data ?? []
    const cols = visibleColumns.value
    const headers = cols.map((c) => COLUMN_LABELS[c] ?? c)
    const csvRows = [headers.map(escapeCsv).join(',')]
    for (const row of rows) {
      csvRows.push(cols.map((col) => escapeCsv(row[col] ?? '')).join(','))
    }
    const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `products-services-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    //
  } finally {
    exportLoading.value = false
  }
}

async function onImportFileSelect(event) {
  const file = event.target?.files?.[0]
  if (!file) return
  importLoading.value = true
  try {
    await clientsApi.importCsv(file)
    event.target.value = ''
    load()
  } catch (err) {
    const msg = err?.response?.data?.message ?? err?.message ?? 'Import failed.'
    toast('error', msg)
  } finally {
    importLoading.value = false
  }
}

function openRenewalAlertsModal(row) {
  renewalAlertsModalCompany.value = row?.company_name || 'Client'
  renewalAlertsModalItems.value = Array.isArray(row?.renewal_alert_details) ? row.renewal_alert_details : []
  renewalAlertsModalVisible.value = true
}

function closeRenewalAlertsModal() {
  renewalAlertsModalVisible.value = false
  renewalAlertsModalCompany.value = ''
  renewalAlertsModalItems.value = []
}

async function load() {
  window.scrollTo(0, 0)
  loading.value = true
  try {
    const data = await clientsApi.index(buildParams())
    const incomingRows = data.data ?? []
    clients.value = incomingRows.map((row) => {
      const resolvedId = Number(row?._row_id ?? row?.id ?? row?.client_id)
      if (Number.isInteger(resolvedId) && resolvedId > 0) {
        return { ...row, _row_id: resolvedId, id: resolvedId }
      }
      return row
    })
    const visibleIds = new Set(
      clients.value
        .map((row) => Number(row?._row_id ?? row?.id))
        .filter((id) => Number.isInteger(id) && id > 0)
    )
    selectedIds.value = selectedIds.value.filter((id) => visibleIds.has(Number(id)))
    meta.value = normalizeMeta(data.meta)
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
}

async function loadFilters() {
  try {
    const data = await clientsApi.filters()
    accountNumbers.value = data.account_numbers ?? []
    alertTypes.value = data.alert_types ?? []
    filterOptions.value = {
      statuses: data.statuses ?? [],
      managers: data.managers ?? [],
      team_leaders: data.team_leaders ?? [],
      sales_agents: data.sales_agents ?? [],
      submission_types: data.submission_types ?? [],
      service_categories: data.service_categories ?? [],
      service_types: data.service_types ?? [],
      product_types: data.product_types ?? [],
      work_order_statuses: data.work_order_statuses ?? [],
      payment_connections: data.payment_connections ?? [],
      contract_types: data.contract_types ?? [],
      clawback_chum_options: data.clawback_chum_options ?? [],
      company_categories: data.company_categories ?? [],
      account_manager_names: data.account_manager_names ?? [],
    }
  } catch { /* silent */ }
}

const COLUMN_ORDER = [
  'submitted_at', 'company_name', 'account_number',
  'trade_license_issuing_authority', 'company_category', 'trade_license_number', 'trade_license_expiry_date',
  'establishment_card_number', 'establishment_card_expiry_date', 'account_taken_from', 'account_mapping_date',
  'account_transfer_given_to', 'account_transfer_given_date',
  'first_bill', 'second_bill', 'third_bill', 'fourth_bill', 'additional_comment_1', 'primary_contact_detail',
  'full_address',
  'csr_name_2', 'csr_name_3',
  'additional_comment_2',
  'renewal_alert',
]

const ALL_CLIENTS_HIDDEN_COLUMNS = new Set([
  'completion_date',
  'payment_connection',
  'account_manager_name',
  'csr_name_1',
  'status',
  'creator',
  'submission_type',
  'service_category',
  'manager',
  'team_leader',
  'sales_agent',
  'service_type',
  'product_type',
  'address',
  'product_name',
  'mrc',
  'quantity',
  'other',
  'migration_numbers',
  'activity',
  'wo_number',
  'work_order_status',
  'activation_date',
  'contract_type',
  'contract_end_date',
  'clawback_chum',
  'remarks',
  'additional_notes',
])

function enforceColumnOrder(cols) {
  const set = new Set((cols || []).filter((c) => !ALL_CLIENTS_HIDDEN_COLUMNS.has(c)))
  ;[
    'submitted_at', 'company_name', 'account_number', 'trade_license_issuing_authority', 'company_category',
    'trade_license_number', 'trade_license_expiry_date', 'establishment_card_number',
    'establishment_card_expiry_date', 'account_taken_from', 'account_mapping_date',
    'account_transfer_given_to', 'account_transfer_given_date',
    'first_bill', 'second_bill', 'third_bill', 'fourth_bill', 'additional_comment_1', 'additional_comment_2',
    'primary_contact_detail',
    'full_address', 'csr_name_2', 'csr_name_3',
    'renewal_alert',
  ].forEach((c) => set.add(c))
  const ordered = COLUMN_ORDER.filter((c) => set.has(c))
  const extra = [...set].filter((c) => !COLUMN_ORDER.includes(c))
  return [...ordered, ...extra]
}

async function loadColumns() {
  try {
    const data = await clientsApi.columns()
    allColumns.value = (data.all_columns ?? []).filter((c) => !ALL_CLIENTS_HIDDEN_COLUMNS.has(c?.key))
    visibleColumns.value = enforceColumnOrder(data.visible_columns ?? visibleColumns.value)
    defaultColumns.value = (data.default_columns ?? []).filter((c) => !ALL_CLIENTS_HIDDEN_COLUMNS.has(c))
    updateTableColumns()
  } catch {
    //
  }
}

function applyFilters() {
  meta.value.current_page = 1
  load()
}

function clearSearch() {
  filters.value.company_name = ''
  filters.value.account_number = ''
  filters.value.alert_type = ''
  filters.value.status = ''
  filters.value.manager_id = ''
  filters.value.team_leader_id = ''
  filters.value.sales_agent_id = ''
  filters.value.submitted_from = ''
  filters.value.submitted_to = ''
  filters.value.submission_type = ''
  filters.value.service_category = ''
  filters.value.service_type = ''
  filters.value.product_type = ''
  filters.value.product_name = ''
  filters.value.wo_number = ''
  filters.value.work_order_status = ''
  filters.value.payment_connection = ''
  filters.value.contract_type = ''
  filters.value.clawback_chum = ''
  filters.value.company_category = ''
  filters.value.trade_license_number = ''
  filters.value.establishment_card_number = ''
  filters.value.account_manager_name = ''
  filters.value.csr_name_1 = ''
  filters.value.activation_from = ''
  filters.value.activation_to = ''
  filters.value.completion_from = ''
  filters.value.completion_to = ''
  filters.value.contract_end_from = ''
  filters.value.contract_end_to = ''
  filters.value.trade_license_expiry_from = ''
  filters.value.trade_license_expiry_to = ''
  filters.value.establishment_card_expiry_from = ''
  filters.value.establishment_card_expiry_to = ''
  meta.value.current_page = 1
  load()
}

function resetFilters() {
  filters.value = {
    company_name: '',
    account_number: '',
    alert_type: '',
    status: '',
    manager_id: '',
    team_leader_id: '',
    sales_agent_id: '',
    submitted_from: '',
    submitted_to: '',
    submission_type: '',
    service_category: '',
    service_type: '',
    product_type: '',
    product_name: '',
    wo_number: '',
    work_order_status: '',
    payment_connection: '',
    contract_type: '',
    clawback_chum: '',
    company_category: '',
    trade_license_number: '',
    establishment_card_number: '',
    account_manager_name: '',
    csr_name_1: '',
    activation_from: '',
    activation_to: '',
    completion_from: '',
    completion_to: '',
    contract_end_from: '',
    contract_end_to: '',
    trade_license_expiry_from: '',
    trade_license_expiry_to: '',
    establishment_card_expiry_from: '',
    establishment_card_expiry_to: '',
  }
  meta.value.current_page = 1
  load()
}

function onSort({ sort: s, order: o }) {
  sort.value = s
  order.value = o
  meta.value.current_page = 1
  load()
}

async function onSaveColumns(cols) {
  try {
    await clientsApi.saveColumns(cols)
    visibleColumns.value = enforceColumnOrder(cols)
    updateTableColumns()
    meta.value.current_page = 1
    load()
  } catch {
    //
  }
}

function onPageChange(page) {
  meta.value.current_page = toPositiveInt(page, 1)
  load()
}

async function onPerPageChange(event) {
  const newPerPage = Math.min(toPositiveInt(event.target.value, 25), 50)
  meta.value.per_page = newPerPage
  meta.value.current_page = 1
  try {
    await api.post(`/table-preferences/${TABLE_MODULE}`, { per_page: newPerPage })
  } catch { /* silent */ }
  load()
}

async function loadTablePreference() {
  try {
    const { data } = await api.get(`/table-preferences/${TABLE_MODULE}`)
    if (data.per_page) meta.value.per_page = Math.min(toPositiveInt(data.per_page, 25), 50)
    if (Array.isArray(data.options) && data.options.length) {
      perPageOptions.value = [...new Set(data.options.map((opt) => Math.min(toPositiveInt(opt, 25), 50)))]
        .filter((opt) => opt <= 50)
    }
  } catch { /* use system default */ }
}

function goToAddClient() {
  try {
    sessionStorage.setItem('clients.create.return_to', route.fullPath)
  } catch {
    // ignore storage errors
  }
  router.push({
    name: 'clients.create',
    query: {
      from: 'all-clients',
      return_to: route.fullPath,
    },
  })
}

async function onUpdateCell(clientId, field, value) {
  const isRenewal = field === 'service_category' && String(value ?? '').trim().toLowerCase() === 'renewal'
  if (isRenewal) {
    try {
      await clientsApi.inlineUpdate(clientId, { [field]: value, create_renewal_record: true })
      await load()
    } catch {
      load()
    }
    return
  }

  const row = clients.value.find((r) => r.id === clientId)
  const prev = row ? { ...row } : null
  if (row) {
    if (field === 'manager_id' && value != null) {
      row.manager_id = value
      row.manager = filterOptions.value.managers.find((u) => u.id === Number(value))?.name ?? row.manager
    } else if (field === 'team_leader_id' && value != null) {
      row.team_leader_id = value
      row.team_leader = filterOptions.value.team_leaders.find((u) => u.id === Number(value))?.name ?? row.team_leader
    } else if (field === 'sales_agent_id' && value != null) {
      row.sales_agent_id = value
      row.sales_agent = filterOptions.value.sales_agents.find((u) => u.id === Number(value))?.name ?? row.sales_agent
    } else {
      row[field] = value
    }
  }
  try {
    await clientsApi.inlineUpdate(clientId, { [field]: value })
  } catch {
    if (prev) Object.assign(row, prev)
    load()
  }
}

const tableColumns = ref([])
function updateTableColumns() {
  tableColumns.value = visibleColumns.value.filter((c) => c !== 'id' && c !== 'fiber' && c !== 'order_number')
}

onMounted(() => {
  loadFilters()
  loadBulkDropdownOptions()
  loadTablePreference().then(() => {
    loadColumns().then(() => {
      updateTableColumns()
      load()
    })
  })
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white p-4">
    <div class="w-full space-y-4">
      <ClientsFiltersBar
        :filters="filters"
        :loading="loading"
        :account-numbers="accountNumbers"
        :alert-types="alertTypes"
        title="Search Clients"
        :compact-actions="true"
        @search="applyFilters"
        @clear="clearSearch"
      >
        <template #customize-columns>
          <input
            ref="importFileInputRef"
            type="file"
            accept=".csv"
            class="hidden"
            @change="onImportFileSelect"
          />
          <button
            v-if="canImport"
            type="button"
            class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            :disabled="loading || importLoading"
            @click="importFileInputRef?.click()"
          >
            <svg v-if="importLoading" class="mr-1.5 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            <svg v-else class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4 4m0 0l-4-4m4 4V8" />
            </svg>
            {{ importLoading ? 'Importing...' : 'Import CSV' }}
          </button>
          <button
            v-if="canImport"
            type="button"
            class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            :disabled="loading || importLoading"
            @click="downloadSampleCsv"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14" />
            </svg>
            Template
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
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            <svg v-else class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            {{ exportLoading ? 'Exporting...' : 'Export' }}
          </button>
          <button
            type="button"
            class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="advancedVisible = !advancedVisible"
          >
            Advanced Filters
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
          <button
            v-if="canCreate"
            type="button"
            class="inline-flex items-center rounded bg-brand-primary px-3 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-70 disabled:cursor-wait"
            :disabled="loading"
            @click="goToAddClient"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add New Client
          </button>
          <button
            v-if="canDelete && selectedIds.length > 0"
            type="button"
            class="inline-flex items-center rounded border border-red-300 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100"
            :disabled="bulkLoading"
            @click="bulkDelete"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" />
            </svg>
            Delete Selected ({{ selectedIds.length }})
          </button>
        </template>
      </ClientsFiltersBar>

      <!-- Bulk actions (visible when items selected) -->
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="selectedIds.length" class="rounded-lg border border-gray-200 bg-white p-3">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm text-gray-600 font-medium">{{ selectedIds.length }} selected</span>
            <select
              v-if="canEdit"
              v-model="bulkCsrName"
              class="rounded border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            >
              <option value="">Select CSR</option>
              <option v-for="name in csrOptions" :key="name" :value="name">{{ name }}</option>
            </select>
            <button v-if="canEdit" type="button" class="rounded bg-brand-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-primary-hover" :disabled="bulkLoading" @click="bulkAssignCsr">Assign CSR</button>

            <select
              v-if="canEdit"
              v-model="bulkAccountManagerName"
              class="rounded border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            >
              <option value="">Select Account Manager</option>
              <option v-for="name in accountManagerOptions" :key="name" :value="name">{{ name }}</option>
            </select>
            <button v-if="canEdit" type="button" class="rounded bg-brand-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-primary-hover" :disabled="bulkLoading" @click="bulkAssignAccountManager">Assign Account Manager</button>

            <button v-if="canExport" type="button" class="rounded bg-brand-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-primary-hover" :disabled="bulkLoading || exportLoading" @click="bulkExport">Export Selected</button>
          </div>
        </div>
      </Transition>

      <div v-if="advancedVisible" class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
          <div>
            <label class="mb-1 block text-xs text-gray-600">Status</label>
            <select v-model="filters.status" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
              <option value="">All Statuses</option>
              <option v-for="s in filterOptions.statuses" :key="s.value || s" :value="s.value || s">{{ s.label || s }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Submission Type</label>
            <select v-model="filters.submission_type" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
              <option value="">All Submission Types</option>
              <option v-for="v in filterOptions.submission_types" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Service Category</label>
            <select v-model="filters.service_category" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
              <option value="">All Service Categories</option>
              <option v-for="v in filterOptions.service_categories" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Service Type</label>
            <select v-model="filters.service_type" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
              <option value="">All Service Types</option>
              <option v-for="v in filterOptions.service_types" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Product Type</label>
            <select v-model="filters.product_type" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
              <option value="">All Product Types</option>
              <option v-for="v in filterOptions.product_types" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Product Name</label>
            <input v-model="filters.product_name" type="text" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" placeholder="Product name" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Manager</label>
            <select v-model="filters.manager_id" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
              <option value="">All Managers</option>
              <option v-for="u in filterOptions.managers" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Team Leader</label>
            <select v-model="filters.team_leader_id" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
              <option value="">All Team Leaders</option>
              <option v-for="u in filterOptions.team_leaders" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Sales Agent</label>
            <select v-model="filters.sales_agent_id" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
              <option value="">All Sales Agents</option>
              <option v-for="u in filterOptions.sales_agents" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Work Order</label>
            <input v-model="filters.wo_number" type="text" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" placeholder="WO number" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Work Order Status</label>
            <select v-model="filters.work_order_status" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
              <option value="">All Work Order Statuses</option>
              <option v-for="v in filterOptions.work_order_statuses" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Payment Connection</label>
            <select v-model="filters.payment_connection" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
              <option value="">All Payment Connection</option>
              <option v-for="v in filterOptions.payment_connections" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Contract Type</label>
            <select v-model="filters.contract_type" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
              <option value="">All Contract Types</option>
              <option v-for="v in filterOptions.contract_types" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Clawback / Chum</label>
            <select v-model="filters.clawback_chum" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
              <option value="">All</option>
              <option v-for="v in filterOptions.clawback_chum_options" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Company Category</label>
            <select v-model="filters.company_category" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
              <option value="">All Company Categories</option>
              <option v-for="v in filterOptions.company_categories" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Trade License Number</label>
            <input v-model="filters.trade_license_number" type="text" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" placeholder="Trade license number" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Establishment Card Number</label>
            <input v-model="filters.establishment_card_number" type="text" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" placeholder="Establishment card number" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Account Manager Name</label>
            <select v-model="filters.account_manager_name" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
              <option value="">All Account Managers</option>
              <option v-for="v in filterOptions.account_manager_names" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">CSR Name</label>
            <input v-model="filters.csr_name_1" type="text" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" placeholder="CSR Name" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Submitted From</label>
            <DateInputDdMmYyyy v-model="filters.submitted_from" placeholder="DD-MMM-YYYY" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Submitted To</label>
            <DateInputDdMmYyyy v-model="filters.submitted_to" placeholder="DD-MMM-YYYY" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Activation From</label>
            <DateInputDdMmYyyy v-model="filters.activation_from" placeholder="DD-MMM-YYYY" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Activation To</label>
            <DateInputDdMmYyyy v-model="filters.activation_to" placeholder="DD-MMM-YYYY" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Completion From</label>
            <DateInputDdMmYyyy v-model="filters.completion_from" placeholder="DD-MMM-YYYY" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Completion To</label>
            <DateInputDdMmYyyy v-model="filters.completion_to" placeholder="DD-MMM-YYYY" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Contract End From</label>
            <DateInputDdMmYyyy v-model="filters.contract_end_from" placeholder="DD-MMM-YYYY" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Contract End To</label>
            <DateInputDdMmYyyy v-model="filters.contract_end_to" placeholder="DD-MMM-YYYY" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Trade License Expiry From</label>
            <DateInputDdMmYyyy v-model="filters.trade_license_expiry_from" placeholder="DD-MMM-YYYY" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Trade License Expiry To</label>
            <DateInputDdMmYyyy v-model="filters.trade_license_expiry_to" placeholder="DD-MMM-YYYY" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Establishment Card Expiry From</label>
            <DateInputDdMmYyyy v-model="filters.establishment_card_expiry_from" placeholder="DD-MMM-YYYY" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Establishment Card Expiry To</label>
            <DateInputDdMmYyyy v-model="filters.establishment_card_expiry_to" placeholder="DD-MMM-YYYY" />
          </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
          <button type="button" class="rounded bg-brand-primary px-3 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover" @click="applyFilters">Apply</button>
          <button type="button" class="rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="resetFilters">Reset</button>
        </div>
      </div>

      <div class="overflow-hidden rounded-lg border-2 border-black bg-white shadow-sm">
        <ClientTable
          :columns="tableColumns"
          :data="clients"
          :sort="sort"
          :order="order"
          :loading="loading"
          :current-page="meta.current_page"
          :per-page="meta.per_page"
          :edit-options="filterOptions"
          :selected-ids="selectedIds"
          :selectable="true"
          permission-module="all-clients"
          @sort="onSort"
          @update-cell="onUpdateCell"
          @view-history="openHistoryModal"
          @show-renewal-alerts="openRenewalAlertsModal"
          @toggle-select="toggleSelect"
          @toggle-select-all="allSelected = !allSelected"
          @delete="openDeleteModalForRow"
        />
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-black bg-white px-4 py-3">
          <p class="text-sm text-gray-600">
            Showing {{ meta.total ? ((meta.current_page - 1) * meta.per_page) + 1 : 0 }}
            to {{ Math.min(meta.current_page * meta.per_page, meta.total) }}
            of {{ meta.total }} entries
          </p>
          <div class="flex items-center gap-4">
            <div class="flex items-center gap-2 text-sm text-gray-600">
              <span class="whitespace-nowrap font-medium">Number of rows</span>
              <select
                :value="meta.per_page"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm min-w-[80px] text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                @change="onPerPageChange"
              >
                <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </div>
            <div class="flex items-center gap-1.5">
              <button type="button" :disabled="meta.current_page <= 1" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" @click="onPageChange(meta.current_page - 1)">Previous</button>
              <span class="rounded-md border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm text-gray-700">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
              <button type="button" :disabled="meta.current_page >= meta.last_page" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" @click="onPageChange(meta.current_page + 1)">Next</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ColumnCustomizerModal
      :visible="columnModalVisible"
      :all-columns="allColumns"
      :visible-columns="visibleColumns"
      :default-columns="defaultColumns"
      @update:visible="columnModalVisible = $event"
      @save="onSaveColumns"
    />

    <RecordHistoryModal
      :visible="historyModalVisible"
      :record-id="historyRecordId"
      :record-label="historyRecordLabel"
      module-name="Clients"
      :fetch-fn="fetchClientAudits"
      @close="closeHistoryModal"
    />

    <RenewalAlertsModal
      :visible="renewalAlertsModalVisible"
      :company-name="renewalAlertsModalCompany"
      :alerts="renewalAlertsModalItems"
      @close="closeRenewalAlertsModal"
    />

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />

    <!-- Bulk Delete OTP Confirmation -->
    <DeleteOtpModal
      :visible="showBulkDeleteModal"
      title="Bulk Delete Clients"
      :item-label="deleteTargetLabel"
      :loading="bulkLoading"
      @confirm="confirmBulkDelete"
      @close="closeBulkDeleteModal"
    />
  </div>
</template>
