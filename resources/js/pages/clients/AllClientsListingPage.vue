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
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import RecordHistoryModal from '@/components/RecordHistoryModal.vue'
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
const loading = ref(true)
const clients = ref([])
const TABLE_MODULE = 'all-clients'
const perPageOptions = ref([10, 20, 25, 50])
const meta = ref({ current_page: 1, last_page: 1, per_page: authStore.defaultTablePageSize || 25, total: 0 })
const allColumns = ref([])
const defaultColumns = ref([])
const visibleColumns = ref([
  'company_name', 'account_number', 'submitted_at',
  'trade_license_issuing_authority', 'company_category', 'trade_license_number', 'trade_license_expiry_date',
  'establishment_card_number', 'establishment_card_expiry_date', 'account_taken_from', 'account_mapping_date',
  'account_transfer_given_to', 'account_transfer_given_date', 'full_address',
  'account_manager_name', 'csr_name_1', 'csr_name_2', 'csr_name_3',
  'first_bill', 'second_bill', 'third_bill', 'fourth_bill', 'additional_comment_1', 'additional_comment_2',
  'status', 'creator',
  'submission_type', 'service_category', 'manager', 'team_leader', 'sales_agent',
  'service_type', 'product_type', 'address', 'product_name', 'mrc', 'quantity',
  'other', 'migration_numbers', 'activity', 'wo_number', 'work_order_status',
  'activation_date', 'completion_date', 'payment_connection', 'contract_type', 'contract_end_date',
  'clawback_chum', 'remarks', 'renewal_alert', 'additional_notes',
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
  full_address: 'Full Address',
  account_manager_name: 'Account Manager Name',
  csr_name_1: 'CSR Name 1',
  csr_name_2: 'CSR Name 2',
  csr_name_3: 'CSR Name 3',
  first_bill: 'First Bill',
  second_bill: 'Second Bill',
  third_bill: 'Third Bill',
  fourth_bill: 'Fourth Bill',
  additional_comment_1: 'Additional Note 1',
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
  renewal_alert: 'Renewal Alert',
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
  const headers = [
    'company_name',
    'account_number',
    'status',
    'submitted_at',
    'manager_id',
    'team_leader_id',
    'sales_agent_id',
    'submission_type',
    'service_category',
    'service_type',
    'product_type',
    'address',
    'product_name',
    'mrc',
    'quantity',
    'offer',
    'other',
    'migration_numbers',
    'fiber',
    'order_number',
    'activity',
    'wo_number',
    'work_order_status',
    'activation_date',
    'completion_date',
    'payment_connection',
    'contract_type',
    'contract_end_date',
    'clawback_chum',
    'remarks',
    'renewal_alert',
    'additional_notes',
    'trade_license_issuing_authority',
    'company_category',
    'trade_license_number',
    'trade_license_expiry_date',
    'establishment_card_number',
    'establishment_card_expiry_date',
    'account_taken_from',
    'account_mapping_date',
    'account_transfer_given_to',
    'account_transfer_given_date',
    'account_manager_name',
    'first_bill',
    'second_bill',
    'third_bill',
    'fourth_bill',
    'additional_comment_1',
    'additional_comment_2',
    'csr_name_1',
    'csr_name_2',
    'csr_name_3',
    'contact_1_name',
    'contact_1_designation',
    'contact_1_contact_number',
    'contact_1_alternate_number',
    'contact_1_email',
    'contact_1_as_updated_or_not',
    'contact_1_as_expiry_date',
    'contact_1_additional_note',
    'address_1_full_address',
    'address_1_area',
    'address_1_building',
    'address_1_unit',
    'address_1_emirates',
  ]

  const validationGuideRow = [
    'REQUIRED',
    'REQUIRED',
    'REQUIRED (Normal/Churn/Clawback)',
    'OPTIONAL (YYYY-MM-DD)',
    'OPTIONAL (user id)',
    'OPTIONAL (user id)',
    'OPTIONAL (user id)',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL (integer)',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL (YYYY-MM-DD)',
    'OPTIONAL (YYYY-MM-DD)',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL (YYYY-MM-DD)',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL (integer)',
    'OPTIONAL',
    'OPTIONAL',
    'RECOMMENDED',
    'REQUIRED',
    'REQUIRED',
    'OPTIONAL (YYYY-MM-DD)',
    'OPTIONAL',
    'OPTIONAL (YYYY-MM-DD)',
    'OPTIONAL',
    'OPTIONAL (YYYY-MM-DD)',
    'OPTIONAL',
    'OPTIONAL (YYYY-MM-DD)',
    'REQUIRED',
    'REQUIRED (paid/unpaid)',
    'REQUIRED (paid/unpaid)',
    'REQUIRED (paid/unpaid)',
    'REQUIRED (paid/unpaid)',
    'OPTIONAL',
    'OPTIONAL',
    'REQUIRED (at least one CSR)',
    'OPTIONAL',
    'OPTIONAL',
    'REQUIRED',
    'OPTIONAL',
    'REQUIRED',
    'OPTIONAL',
    'REQUIRED',
    'OPTIONAL (yes/no)',
    'OPTIONAL (YYYY-MM-DD)',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL',
    'OPTIONAL',
  ]

  const sampleRows = [
    [
      'Demo Company LLC',
      'ACCT-10001',
      'Normal',
      '2026-03-01',
      '',
      '',
      '',
      'New',
      'Internet',
      'Fiber',
      'Business',
      'Dubai, UAE',
      'Business Internet Plan',
      '999',
      '1',
      'Promo Offer',
      'N/A',
      'MN-001',
      'No',
      'ORD-1001',
      'Initial activation',
      'WO-12345',
      'Completed',
      '2026-03-01',
      '2026-03-05',
      'Connected',
      '12 Months',
      '2027-03-01',
      'No',
      'Imported from sample CSV',
      '0',
      'Sample note',
      'DED',
      'LLC',
      'TL-12345',
      '2027-03-01',
      'EC-7788',
      '2027-03-01',
      'Referral',
      '2026-03-01',
      'Operations Team',
      '2026-03-03',
      'Imran Siddiqui',
      'paid',
      'paid',
      'paid',
      'paid',
      'Additional comment 1',
      'Additional comment 2',
      'CSR A',
      'CSR B',
      '',
      'John Doe',
      'Admin',
      '971501112233',
      '971509998877',
      'john.doe@example.com',
      'yes',
      '2027-03-01',
      'Primary contact',
      '1205, Example Tower, Business Bay, Dubai',
      'Business Bay',
      'Example Tower',
      '1205',
      'Dubai',
    ],
  ]

  const csvRows = [
    headers.map(escapeCsv).join(','),
    validationGuideRow.map(escapeCsv).join(','),
    ...sampleRows.map((row) => row.map(escapeCsv).join(',')),
  ]

  const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'clients-import-sample.csv'
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
    alert(msg)
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
    clients.value = data.data ?? []
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
  'company_name', 'account_number', 'submitted_at',
  'trade_license_issuing_authority', 'company_category', 'trade_license_number', 'trade_license_expiry_date',
  'establishment_card_number', 'establishment_card_expiry_date', 'account_taken_from', 'account_mapping_date',
  'account_transfer_given_to', 'account_transfer_given_date', 'full_address',
  'account_manager_name', 'csr_name_1', 'csr_name_2', 'csr_name_3',
  'first_bill', 'second_bill', 'third_bill', 'fourth_bill', 'additional_comment_1', 'additional_comment_2',
  'status', 'creator',
  'submission_type', 'service_category', 'manager', 'team_leader', 'sales_agent',
  'service_type', 'product_type', 'address', 'product_name', 'mrc', 'quantity', 'other',
  'migration_numbers', 'activity', 'wo_number', 'work_order_status', 'activation_date', 'completion_date',
  'payment_connection', 'contract_type', 'contract_end_date', 'clawback_chum', 'remarks',
  'renewal_alert', 'additional_notes',
]

function enforceColumnOrder(cols) {
  const set = new Set(cols)
  ;[
    'company_name', 'account_number', 'trade_license_issuing_authority', 'company_category',
    'trade_license_number', 'trade_license_expiry_date', 'establishment_card_number',
    'establishment_card_expiry_date', 'account_taken_from', 'account_mapping_date',
    'account_transfer_given_to', 'account_transfer_given_date', 'full_address',
    'account_manager_name', 'csr_name_1', 'status', 'creator',
    'submission_type', 'service_category', 'service_type', 'product_type', 'address', 'product_name',
    'mrc', 'quantity', 'other', 'migration_numbers', 'activity', 'wo_number', 'work_order_status',
    'activation_date', 'completion_date', 'payment_connection', 'contract_type', 'contract_end_date',
    'clawback_chum', 'remarks', 'renewal_alert', 'additional_notes',
  ].forEach((c) => set.add(c))
  const ordered = COLUMN_ORDER.filter((c) => set.has(c))
  const extra = [...set].filter((c) => !COLUMN_ORDER.includes(c))
  return [...ordered, ...extra]
}

async function loadColumns() {
  try {
    const data = await clientsApi.columns()
    allColumns.value = data.all_columns ?? []
    visibleColumns.value = enforceColumnOrder(data.visible_columns ?? visibleColumns.value)
    defaultColumns.value = data.default_columns ?? []
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
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-xl font-semibold text-gray-900 leading-tight">All Clients</h1>
          <Breadcrumbs />
        </div>
        <div class="flex items-center gap-2">
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
            Sample CSV
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
            v-if="canCreate"
            type="button"
            class="inline-flex items-center rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-70 disabled:cursor-wait"
            :disabled="loading"
            @click="goToAddClient"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add New Client
          </button>
        </div>
      </div>

      <p class="text-sm text-gray-500">Search for a client to view their profile.</p>

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
        </template>
      </ClientsFiltersBar>

      <div v-if="advancedVisible" class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
          <div>
            <label class="mb-1 block text-xs text-gray-600">Status</label>
            <select v-model="filters.status" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Statuses</option>
              <option v-for="s in filterOptions.statuses" :key="s.value || s" :value="s.value || s">{{ s.label || s }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Submission Type</label>
            <select v-model="filters.submission_type" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Submission Types</option>
              <option v-for="v in filterOptions.submission_types" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Service Category</label>
            <select v-model="filters.service_category" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Service Categories</option>
              <option v-for="v in filterOptions.service_categories" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Service Type</label>
            <select v-model="filters.service_type" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Service Types</option>
              <option v-for="v in filterOptions.service_types" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Product Type</label>
            <select v-model="filters.product_type" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Product Types</option>
              <option v-for="v in filterOptions.product_types" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Product Name</label>
            <input v-model="filters.product_name" type="text" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" placeholder="Product name" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Manager</label>
            <select v-model="filters.manager_id" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Managers</option>
              <option v-for="u in filterOptions.managers" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Team Leader</label>
            <select v-model="filters.team_leader_id" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Team Leaders</option>
              <option v-for="u in filterOptions.team_leaders" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Sales Agent</label>
            <select v-model="filters.sales_agent_id" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Sales Agents</option>
              <option v-for="u in filterOptions.sales_agents" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Work Order</label>
            <input v-model="filters.wo_number" type="text" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" placeholder="WO number" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Work Order Status</label>
            <select v-model="filters.work_order_status" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Work Order Statuses</option>
              <option v-for="v in filterOptions.work_order_statuses" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Payment Connection</label>
            <select v-model="filters.payment_connection" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Payment Connection</option>
              <option v-for="v in filterOptions.payment_connections" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Contract Type</label>
            <select v-model="filters.contract_type" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Contract Types</option>
              <option v-for="v in filterOptions.contract_types" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Clawback / Chum</label>
            <select v-model="filters.clawback_chum" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All</option>
              <option v-for="v in filterOptions.clawback_chum_options" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Company Category</label>
            <select v-model="filters.company_category" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Company Categories</option>
              <option v-for="v in filterOptions.company_categories" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Trade License Number</label>
            <input v-model="filters.trade_license_number" type="text" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" placeholder="Trade license number" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Establishment Card Number</label>
            <input v-model="filters.establishment_card_number" type="text" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" placeholder="Establishment card number" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">Account Manager Name</label>
            <select v-model="filters.account_manager_name" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">All Account Managers</option>
              <option v-for="v in filterOptions.account_manager_names" :key="v" :value="v">{{ v }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-600">CSR Name</label>
            <input v-model="filters.csr_name_1" type="text" class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" placeholder="CSR Name" />
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
          <button type="button" class="rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700" @click="applyFilters">Apply</button>
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
          permission-module="all-clients"
          @sort="onSort"
          @update-cell="onUpdateCell"
          @view-history="openHistoryModal"
          @show-renewal-alerts="openRenewalAlertsModal"
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
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm min-w-[80px] text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
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
  </div>
</template>
