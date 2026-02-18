<script setup>
/**
 * Client Profile – overview card, tabs: Company Details, Contact Details, Products & Services, VAS Requests, Customer Support, Alerts.
 * Breadcrumbs, back link, permission-based edit, change history (audits).
 */
import { ref, computed, onMounted, watch, provide } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import clientsApi from '@/services/clientsApi'
import { useAuthStore } from '@/stores/auth'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { toDdMmYyyy } from '@/lib/dateFormat'
import ClientTable from '@/components/clients/ClientTable.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import Toast from '@/components/Toast.vue'
import api from '@/lib/axios'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const client = ref(null)
const loading = ref(true)
const activeTab = ref('company-details')

const canEdit = computed(() => {
  const perms = auth.user?.permissions ?? []
  const roles = auth.user?.roles ?? []
  return roles.includes('superadmin') || perms.includes('clients.edit')
})

provide('breadcrumbLabel', computed(() => (client.value?.company_name ?? null)))

const id = computed(() => {
  const p = route.params.id
  return p != null ? Number(p) : null
})

const tabs = [
  { key: 'company-details', label: 'Company Details', icon: 'building' },
  { key: 'contact-details', label: 'Contact Details', icon: 'person' },
  { key: 'products-services', label: 'Products & Services', icon: 'box' },
  { key: 'vas-requests', label: 'VAS Requests', icon: 'list' },
  { key: 'customer-support', label: 'Customer Support', icon: 'headset' },
  { key: 'alerts', label: 'Alerts', icon: 'bell' },
]

// Company Details
const companyDetail = computed(() => client.value?.company_detail ?? null)
const csrNamesList = computed(() => {
  const c = client.value
  const cd = companyDetail.value
  let list = []
  if (c?.csrs?.length) {
    list = c.csrs.map((csr) => csr?.user ?? csr?.user_id ?? '—')
  } else {
    list = [cd?.csr_name_1, cd?.csr_name_2, cd?.csr_name_3].filter((n) => n != null && n !== '')
  }
  return list.filter((n) => n != null && n !== '' && n !== '—')
})
const auditModalVisible = ref(false)
const audits = ref([])
const auditsMeta = ref({})
const auditLoading = ref(false)

// Contact Details
const contactDraft = ref([])
const addressDraft = ref([])
const contactSaveLoading = ref(false)

const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

// Products & Services
const TABLE_MODULE = 'client-profile-products'
const perPageOptions = ref([10, 20, 25, 50, 100])
const products = ref([])
const productsMeta = ref({ per_page: auth.defaultTablePageSize || 25 })
const productsLoading = ref(false)
const productsSort = ref('submitted_at')
const productsOrder = ref('desc')
const allColumns = ref([])
const defaultColumns = ref([])
const visibleColumns = ref([
  'company_name', 'account_number', 'submitted_at', 'manager', 'team_leader', 'sales_agent', 'status',
  'service_type', 'product_type', 'address', 'product_name', 'mrc', 'quantity', 'other', 'migration_numbers',
  'wo_number', 'completion_date', 'payment_connection', 'contract_type', 'contract_end_date',
  'renewal_alert', 'additional_notes', 'creator',
])
const columnModalVisible = ref(false)

// VAS / Customer Support / Alerts
const vasRequests = ref([])
const vasMeta = ref({})
const vasLoading = ref(false)
const customerSupport = ref([])
const csMeta = ref({})
const csLoading = ref(false)
const alertsList = ref([])
const alertsMeta = ref({})
const alertsLoading = ref(false)
const alertFilterType = ref('')
const alertFilterStatus = ref('')
const alertSort = ref('expiry_date')
const alertOrder = ref('desc')
const showAlertModal = ref(false)
const alertSaving = ref(false)
const alertForm = ref({ alert_type: '', expiry_date: '', days_remaining: '', manager_id: '', status: '' })
const alertFormErrors = ref({})
const managerOptions = ref([])
const showAlertAdvanced = ref(false)
const alertAdvFilters = ref({ resolved: '', manager_id: '', expiry_from: '', expiry_to: '', created_from: '', created_to: '' })
const alertColumnModal = ref(false)
const alertEditingCell = ref(null)
const alertEditValue = ref('')

const ALERT_TYPE_OPTIONS = [
  'Trade License Expiry',
  'Establishment Card Expiry',
  'Custom',
]
const ALERT_STATUS_OPTIONS = ['Active', 'Valid / Invalid', 'Resolved', 'Expired']

const ALL_ALERT_COLUMNS = [
  { key: 'alert_type', label: 'Alert Type' },
  { key: 'company_name', label: 'Company Name' },
  { key: 'account_number', label: 'Account Number' },
  { key: 'expiry_date', label: 'Expiry Date' },
  { key: 'days_remaining', label: 'Days Remaining' },
  { key: 'manager', label: 'Manager Name' },
  { key: 'status', label: 'Status' },
  { key: 'created_date', label: 'Created Date' },
  { key: 'resolved', label: 'Resolved' },
]
const DEFAULT_ALERT_COLS = ['alert_type', 'company_name', 'account_number', 'expiry_date', 'days_remaining', 'manager', 'status', 'created_date']
const alertVisibleCols = ref([...DEFAULT_ALERT_COLS])
const ALERT_SORTABLE = ['alert_type', 'company_name', 'account_number', 'expiry_date', 'days_remaining', 'status', 'created_date', 'resolved']
const ALERT_DROPDOWN_COLS = ['alert_type', 'status']
const ALERT_INPUT_COLS = ['days_remaining', 'expiry_date']
const ALERT_READONLY_COLS = ['company_name', 'account_number', 'created_date', 'resolved', 'manager']

function alertColLabel(key) {
  return ALL_ALERT_COLUMNS.find(c => c.key === key)?.label || key
}

function alertAdvCount() {
  const f = alertAdvFilters.value
  let n = 0
  if (f.resolved !== '') n++
  if (f.manager_id) n++
  if (f.expiry_from || f.expiry_to) n++
  if (f.created_from || f.created_to) n++
  return n
}

function displayVal(val) {
  return val != null && val !== '' ? String(val) : '—'
}

function formatDate(d) {
  if (d == null || d === '') return '—'
  const str = typeof d === 'string' ? d.trim().slice(0, 10) : ''
  if (!str || str.length < 10) return '—'
  const out = toDdMmYyyy(str)
  return out || str
}

function statusBadgeClass(status) {
  const s = (status || '').toLowerCase()
  if (s === 'active' || s === 'completed') return 'bg-green-100 text-green-700'
  if (s === 'pending' || s.includes('pending')) return 'bg-amber-100 text-amber-800'
  if (s === 'cancelled' || s === 'rejected') return 'bg-red-100 text-red-700'
  return 'bg-gray-100 text-gray-700'
}

async function loadClient() {
  if (!id.value) return
  loading.value = true
  client.value = null
  try {
    const data = await clientsApi.show(id.value)
    client.value = data
    contactDraft.value = (data.contacts ?? []).map((c) => ({ ...c }))
    addressDraft.value = (data.addresses ?? []).map((a) => ({ ...a }))
  } catch {
    client.value = null
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push('/clients')
}

async function loadAudits() {
  if (!id.value) return
  auditLoading.value = true
  try {
    const res = await clientsApi.audits(id.value, { per_page: 50 })
    audits.value = res.data ?? []
    auditsMeta.value = res.meta ?? {}
  } catch {
    audits.value = []
  } finally {
    auditLoading.value = false
  }
}

function openAuditModal() {
  auditModalVisible.value = true
  loadAudits()
}

async function loadProducts() {
  if (!id.value) return
  productsLoading.value = true
  try {
    const res = await clientsApi.products(id.value, {
      page: productsMeta.value.current_page || 1,
      per_page: productsMeta.value.per_page || auth.defaultTablePageSize || 25,
      sort: productsSort.value,
      order: productsOrder.value,
    })
    products.value = res.data ?? []
    productsMeta.value = res.meta ?? {}
  } catch {
    products.value = []
  } finally {
    productsLoading.value = false
  }
}

function onProductsSort({ sort, order }) {
  productsSort.value = sort
  productsOrder.value = order
  loadProducts()
}

function onProductsPageChange(page) {
  productsMeta.value.current_page = page
  loadProducts()
}

async function onProductsPerPageChange(event) {
  const newPerPage = Number(event.target.value)
  productsMeta.value.per_page = newPerPage
  productsMeta.value.current_page = 1
  try {
    await api.post(`/table-preferences/${TABLE_MODULE}`, { per_page: newPerPage })
  } catch { /* silent */ }
  loadProducts()
}

async function loadTablePreference() {
  try {
    const { data } = await api.get(`/table-preferences/${TABLE_MODULE}`)
    if (data.per_page) productsMeta.value.per_page = Number(data.per_page)
    if (Array.isArray(data.options) && data.options.length) perPageOptions.value = data.options
  } catch { /* use system default */ }
}

async function loadColumns() {
  try {
    const data = await clientsApi.columns()
    allColumns.value = data.all_columns ?? []
    visibleColumns.value = data.visible_columns ?? visibleColumns.value
    defaultColumns.value = data.default_columns ?? []
  } catch {}
}

async function onSaveColumns(cols) {
  try {
    await clientsApi.saveColumns(cols)
    visibleColumns.value = cols
    loadProducts()
  } catch {}
}

async function loadVasRequests() {
  if (!id.value) return
  vasLoading.value = true
  try {
    const res = await clientsApi.vasRequests(id.value, { per_page: 10 })
    vasRequests.value = res.data ?? []
    vasMeta.value = res.meta ?? {}
  } catch {
    vasRequests.value = []
  } finally {
    vasLoading.value = false
  }
}

async function loadCustomerSupport() {
  if (!id.value) return
  csLoading.value = true
  try {
    const res = await clientsApi.customerSupport(id.value, { per_page: 10 })
    customerSupport.value = res.data ?? []
    csMeta.value = res.meta ?? {}
  } catch {
    customerSupport.value = []
  } finally {
    csLoading.value = false
  }
}

async function loadAlerts() {
  if (!id.value) return
  alertsLoading.value = true
  try {
    const params = { per_page: 25, sort: alertSort.value, order: alertOrder.value }
    if (alertFilterType.value) params.alert_type = alertFilterType.value
    if (alertFilterStatus.value) params.status = alertFilterStatus.value
    const adv = alertAdvFilters.value
    if (adv.resolved !== '') params.resolved = adv.resolved
    if (adv.manager_id) params.manager_id = adv.manager_id
    if (adv.expiry_from) params.expiry_from = adv.expiry_from
    if (adv.expiry_to) params.expiry_to = adv.expiry_to
    if (adv.created_from) params.created_from = adv.created_from
    if (adv.created_to) params.created_to = adv.created_to
    const res = await clientsApi.alerts(id.value, params)
    alertsList.value = res.data ?? []
    alertsMeta.value = res.meta ?? {}
  } catch {
    alertsList.value = []
  } finally {
    alertsLoading.value = false
  }
}

function applyAlertFilters() { loadAlerts() }

function clearAlertFilters() {
  alertFilterType.value = ''
  alertFilterStatus.value = ''
  loadAlerts()
}

function clearAlertAdvFilters() {
  alertAdvFilters.value = { resolved: '', manager_id: '', expiry_from: '', expiry_to: '', created_from: '', created_to: '' }
  loadAlerts()
}

function toggleAlertSort(col) {
  if (!ALERT_SORTABLE.includes(col)) return
  if (alertSort.value === col) {
    alertOrder.value = alertOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    alertSort.value = col
    alertOrder.value = 'asc'
  }
  loadAlerts()
}

function openAlertModal() {
  alertForm.value = { alert_type: '', expiry_date: '', days_remaining: '', manager_id: '', status: '' }
  alertFormErrors.value = {}
  showAlertModal.value = true
}

function onSaveAlertColumns(cols) {
  alertVisibleCols.value = cols
  alertColumnModal.value = false
}

function toggleAlertColumn(key) {
  const idx = alertVisibleCols.value.indexOf(key)
  if (idx !== -1) {
    if (alertVisibleCols.value.length <= 1) return
    alertVisibleCols.value.splice(idx, 1)
  } else {
    alertVisibleCols.value.push(key)
  }
}

async function loadManagerOptions() {
  try {
    const res = await api.get('/lead-submissions/team-options')
    const data = res?.data?.data ?? res?.data ?? {}
    managerOptions.value = data.managers ?? []
  } catch { /* ignore */ }
}

async function submitAlert() {
  alertFormErrors.value = {}
  if (!alertForm.value.alert_type) { alertFormErrors.value.alert_type = 'Alert type is required.'; return }
  if (!alertForm.value.expiry_date) { alertFormErrors.value.expiry_date = 'Expiry date is required.'; return }
  if (!alertForm.value.status) { alertFormErrors.value.status = 'Status is required.'; return }

  alertSaving.value = true
  try {
    const payload = {
      alert_type: alertForm.value.alert_type,
      expiry_date: alertForm.value.expiry_date,
      days_remaining: alertForm.value.days_remaining ? Number(alertForm.value.days_remaining) : null,
      manager_id: alertForm.value.manager_id || null,
      status: alertForm.value.status,
    }
    const res = await clientsApi.storeAlert(id.value, payload)
    if (res.alert) {
      alertsList.value.unshift(res.alert)
    }
    showAlertModal.value = false
    toast('success', 'Alert created successfully.')
  } catch (err) {
    if (err.response?.status === 422) {
      const e = err.response.data?.errors ?? {}
      alertFormErrors.value = Object.fromEntries(Object.entries(e).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]))
    } else {
      toast('error', 'Failed to create alert.')
    }
  } finally {
    alertSaving.value = false
  }
}

async function resolveAlert(alert) {
  if (!confirm('Mark this alert as resolved?')) return
  try {
    await clientsApi.resolveAlert(id.value, alert.id)
    alert.resolved = true
    toast('success', 'Alert resolved successfully.')
  } catch {
    toast('error', 'Failed to resolve alert.')
  }
}

function openAlertCellEdit(row, col) {
  if (ALERT_READONLY_COLS.includes(col)) return
  alertEditingCell.value = { rowId: row.id, col }
  if (col === 'expiry_date') {
    const raw = row._raw_expiry || ''
    alertEditValue.value = raw
  } else if (col === 'manager') {
    alertEditValue.value = row.manager_id ?? ''
  } else {
    alertEditValue.value = row[col] != null ? String(row[col]) : ''
  }
}

function isAlertEditing(rowId, col) {
  return alertEditingCell.value?.rowId === rowId && alertEditingCell.value?.col === col
}

function cancelAlertEdit() { alertEditingCell.value = null }

async function saveAlertCellEdit() {
  if (!alertEditingCell.value) return
  const { rowId, col } = alertEditingCell.value
  let payload = {}
  if (col === 'days_remaining') {
    payload.days_remaining = alertEditValue.value === '' ? null : Number(alertEditValue.value)
  } else if (col === 'expiry_date') {
    payload.expiry_date = alertEditValue.value || null
  } else if (col === 'manager') {
    payload.manager_id = alertEditValue.value || null
  } else {
    payload[col] = alertEditValue.value
  }
  try {
    const res = await clientsApi.updateAlert(id.value, rowId, payload)
    if (res.alert) {
      const idx = alertsList.value.findIndex(a => a.id === rowId)
      if (idx !== -1) alertsList.value[idx] = res.alert
    }
    toast('success', 'Alert updated.')
  } catch {
    toast('error', 'Failed to update alert.')
  }
  alertEditingCell.value = null
}

watch(activeTab, (tab) => {
  if (tab === 'products-services') {
    loadProducts()
    loadColumns()
  } else if (tab === 'vas-requests') loadVasRequests()
  else if (tab === 'customer-support') loadCustomerSupport()
  else if (tab === 'alerts') { loadAlerts(); loadManagerOptions() }
})

async function saveContactDetails() {
  if (!id.value) return
  contactSaveLoading.value = true
  try {
    const addresses = addressDraft.value.map((a) => ({
      ...a,
      full_address: [a.unit, a.building, a.area, a.emirates].filter(Boolean).join(', '),
    }))
    await clientsApi.updateContacts(id.value, { contacts: contactDraft.value, addresses })
    toast('success', 'Contact details saved successfully.')
    await loadClient()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to save contact details.')
  } finally {
    contactSaveLoading.value = false
  }
}

function addContact() {
  contactDraft.value.push({
    id: null,
    sort_order: contactDraft.value.length,
    name: '',
    designation: '',
    contact_number: '',
    alternate_number: '',
    email: '',
    as_updated_or_not: '',
    as_expiry_date: null,
    additional_note: '',
  })
}

function removeContact(index) {
  contactDraft.value.splice(index, 1)
}

function addAddress() {
  addressDraft.value.push({
    id: null,
    sort_order: addressDraft.value.length,
    full_address: '',
    unit: '',
    building: '',
    area: '',
    emirates: '',
  })
}

function removeAddress(index) {
  addressDraft.value.splice(index, 1)
}

onMounted(() => {
  loadTablePreference().then(() => {
    loadClient().then(() => {
      if (activeTab.value === 'products-services') {
        loadProducts()
        loadColumns()
      } else if (activeTab.value === 'vas-requests') loadVasRequests()
      else if (activeTab.value === 'customer-support') loadCustomerSupport()
      else if (activeTab.value === 'alerts') loadAlerts()
    })
  })
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white p-3">
    <div class="w-full space-y-3">
      <!-- Back + Title + Breadcrumbs -->
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <router-link
            to="/clients"
            class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-green-600"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Clients
          </router-link>
          <div class="mt-1 flex flex-wrap items-baseline gap-2">
            <h1 class="text-xl font-semibold text-gray-900">
              Client Profile – {{ client?.company_name ?? '…' }}
            </h1>
            <Breadcrumbs />
          </div>
        </div>
      </div>

      <div v-if="loading" class="flex justify-center py-16">
        <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <template v-else-if="client">
        <!-- Overview card -->
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-6">
            <div>
              <p class="text-xs font-medium text-gray-500">Company Name</p>
              <p class="text-sm font-medium text-gray-900">{{ displayVal(client.company_name) }}</p>
            </div>
            <div>
              <p class="text-xs font-medium text-gray-500">Account Number</p>
              <p class="text-sm font-medium text-gray-900">{{ displayVal(client.account_number) }}</p>
            </div>
            <div>
              <p class="text-xs font-medium text-gray-500">Status</p>
              <span
                :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(client.status)]"
              >
                {{ client.status ? (client.status.replace('_', ' ')) : '—' }}
              </span>
            </div>
            <div v-for="(csrName, csrIdx) in csrNamesList" :key="'csr-' + csrIdx">
              <p class="text-xs font-medium text-gray-500">CSR Name{{ csrNamesList.length > 1 ? ' ' + (csrIdx + 1) : '' }}</p>
              <p class="text-sm text-gray-900">{{ displayVal(csrName) }}</p>
            </div>
            <div>
              <p class="text-xs font-medium text-gray-500">Revenue</p>
              <p class="text-sm font-medium text-gray-900">{{ client.revenue != null ? client.revenue : '—' }}</p>
            </div>
          </div>
        </div>

        <!-- Tabs -->
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
          <div class="flex border-b border-gray-200">
            <button
              v-for="tab in tabs"
              :key="tab.key"
              type="button"
              class="inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition-colors"
              :class="activeTab === tab.key
                ? 'border-green-600 text-green-600'
                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
              @click="activeTab = tab.key"
            >
              <span>{{ tab.label }}</span>
            </button>
          </div>

          <div class="p-4">
            <!-- Company Details -->
            <div v-show="activeTab === 'company-details'" class="space-y-4">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-gray-900">Company Details</h2>
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    class="rounded border border-gray-300 bg-white p-2 text-orange-500 hover:bg-orange-50"
                    title="View History"
                    @click="openAuditModal"
                  >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </button>
                  <router-link
                    v-if="canEdit"
                    :to="`/clients/${id}/edit`"
                    class="inline-flex items-center gap-1 rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                    Edit Details
                  </router-link>
                </div>
              </div>
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                  <p class="text-xs font-medium text-gray-500">Company Name</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ displayVal(client.company_name) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Account Number</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ displayVal(client.account_number) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Trade License Issuing Authority</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ displayVal(companyDetail?.trade_license_issuing_authority) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Company Category</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ displayVal(companyDetail?.company_category) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Trade License Number</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ displayVal(companyDetail?.trade_license_number) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Trade License Expiry Date</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ formatDate(companyDetail?.trade_license_expiry_date) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Establishment Card Number</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ displayVal(companyDetail?.establishment_card_number) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Establishment Card Expiry Date</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ formatDate(companyDetail?.establishment_card_expiry_date) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Account Taken From</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ displayVal(companyDetail?.account_taken_from) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Account Mapping Date</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ formatDate(companyDetail?.account_mapping_date) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Account Transfer Given To</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ displayVal(companyDetail?.account_transfer_given_to) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Account Transfer Given Date</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ formatDate(companyDetail?.account_transfer_given_date) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Account Manager Name</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ displayVal(companyDetail?.account_manager_name) }}</p>
                </div>
                <div v-for="(csrName, csrIdx) in csrNamesList" :key="csrIdx">
                  <p class="text-xs font-medium text-gray-500">CSR Name {{ csrIdx + 1 }}</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ displayVal(csrName) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">First Bill</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ displayVal(companyDetail?.first_bill) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Second Bill</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ displayVal(companyDetail?.second_bill) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Third Bill</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ displayVal(companyDetail?.third_bill) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Fourth Bill</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ displayVal(companyDetail?.fourth_bill) }}</p>
                </div>
                <div class="sm:col-span-2 lg:col-span-4">
                  <p class="text-xs font-medium text-gray-500">Additional Comment 1</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ displayVal(companyDetail?.additional_comment_1) }}</p>
                </div>
                <div class="sm:col-span-2 lg:col-span-4">
                  <p class="text-xs font-medium text-gray-500">Additional Comment 2</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">{{ displayVal(companyDetail?.additional_comment_2) }}</p>
                </div>
              </div>
            </div>

            <!-- Contact Details -->
            <div v-show="activeTab === 'contact-details'" class="space-y-6">
              <h2 class="text-lg font-semibold text-gray-900">Contact Details</h2>
              <div>
                <div class="mb-2 flex items-center justify-between">
                  <h3 class="text-sm font-medium text-gray-700">A) Contact Persons</h3>
                  <button
                    v-if="canEdit"
                    type="button"
                    class="inline-flex items-center rounded bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                    @click="addContact"
                  >
                    + Add Contact
                  </button>
                </div>
                <div
                  v-for="(c, idx) in contactDraft"
                  :key="idx"
                  class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4"
                >
                  <div class="mb-3 font-medium text-gray-700">Contact Person {{ idx + 1 }}</div>
                  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                      <label class="block text-xs text-gray-500">Name</label>
                      <input
                        v-model="c.name"
                        type="text"
                        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        :readonly="!canEdit"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500">Designation</label>
                      <input
                        v-model="c.designation"
                        type="text"
                        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        :readonly="!canEdit"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500">Contact Number</label>
                      <input
                        v-model="c.contact_number"
                        type="text"
                        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        :readonly="!canEdit"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500">Alternate Number</label>
                      <input
                        v-model="c.alternate_number"
                        type="text"
                        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        :readonly="!canEdit"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500">Email</label>
                      <input
                        v-model="c.email"
                        type="email"
                        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        :readonly="!canEdit"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500">AS Updated or Not</label>
                      <input
                        v-model="c.as_updated_or_not"
                        type="text"
                        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        :readonly="!canEdit"
                      />
                    </div>
                    <div class="sm:col-span-2">
                      <label class="block text-xs text-gray-500">Additional Note</label>
                      <textarea
                        v-model="c.additional_note"
                        rows="2"
                        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        :readonly="!canEdit"
                      />
                    </div>
                  </div>
                  <div v-if="canEdit" class="mt-2 flex justify-end">
                    <button
                      type="button"
                      class="text-red-600 hover:text-red-800"
                      @click="removeContact(idx)"
                    >
                      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
              <div>
                <div class="mb-2 flex items-center justify-between">
                  <h3 class="text-sm font-medium text-gray-700">B) Addresses</h3>
                  <button
                    v-if="canEdit"
                    type="button"
                    class="inline-flex items-center rounded bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                    @click="addAddress"
                  >
                    + Add Address
                  </button>
                </div>
                <div
                  v-for="(a, idx) in addressDraft"
                  :key="idx"
                  class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4"
                >
                  <div class="mb-3 font-medium text-gray-700">Address {{ idx + 1 }}</div>
                  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                      <label class="block text-xs text-gray-500">Full Address</label>
                      <input
                        :value="[a.unit, a.building, a.area, a.emirates].filter(Boolean).join(', ')"
                        type="text"
                        readonly
                        class="mt-1 w-full rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-700 cursor-default"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500">Unit</label>
                      <input
                        v-model="a.unit"
                        type="text"
                        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        :readonly="!canEdit"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500">Building</label>
                      <input
                        v-model="a.building"
                        type="text"
                        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        :readonly="!canEdit"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500">Area</label>
                      <input
                        v-model="a.area"
                        type="text"
                        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        :readonly="!canEdit"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500">Emirates</label>
                      <input
                        v-model="a.emirates"
                        type="text"
                        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        :readonly="!canEdit"
                      />
                    </div>
                  </div>
                  <div v-if="canEdit" class="mt-2 flex justify-end">
                    <button
                      type="button"
                      class="text-red-600 hover:text-red-800"
                      @click="removeAddress(idx)"
                    >
                      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
              <div v-if="canEdit" class="flex justify-end gap-2">
                <button
                  type="button"
                  class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                  @click="loadClient"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                  :disabled="contactSaveLoading"
                  @click="saveContactDetails"
                >
                  {{ contactSaveLoading ? 'Saving…' : 'Save Changes' }}
                </button>
              </div>
            </div>

            <!-- Products & Services -->
            <div v-show="activeTab === 'products-services'" class="space-y-4">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-gray-900">Products & Services</h2>
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                    @click="columnModalVisible = true"
                  >
                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                    Customize Columns
                    <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                  <router-link
                    v-if="canEdit"
                    to="/clients/create"
                    class="inline-flex items-center rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700"
                  >
                    + Add Product
                  </router-link>
                </div>
              </div>
              <div class="overflow-hidden rounded-lg border border-gray-200">
                <ClientTable
                  :columns="visibleColumns.filter((c) => c !== 'id' && c !== 'fiber' && c !== 'order_number')"
                  :data="products"
                  :sort="productsSort"
                  :order="productsOrder"
                  :loading="productsLoading"
                  :current-page="productsMeta.current_page || 1"
                  :per-page="productsMeta.per_page || 10"
                  @sort="onProductsSort"
                />
              </div>
              <div v-if="productsMeta.total > 0" class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-white px-4 py-3">
                <p class="text-sm text-gray-600">
                  Showing {{ productsMeta.total ? ((productsMeta.current_page || 1) - 1) * (productsMeta.per_page || auth.defaultTablePageSize || 25) + 1 : 0 }}
                  to {{ Math.min((productsMeta.current_page || 1) * (productsMeta.per_page || auth.defaultTablePageSize || 25), productsMeta.total) }}
                  of {{ productsMeta.total }} entries
                </p>
                <div class="flex items-center gap-4">
                  <div class="flex items-center gap-2 text-sm text-gray-600">
                    <span class="whitespace-nowrap font-medium">Number of rows</span>
                    <select
                      :value="productsMeta.per_page || auth.defaultTablePageSize || 25"
                      class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm min-w-[80px] text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
                      @change="onProductsPerPageChange"
                    >
                      <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
                    </select>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <button type="button" :disabled="(productsMeta.current_page || 1) <= 1" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" @click="onProductsPageChange((productsMeta.current_page || 1) - 1)">Previous</button>
                    <span class="rounded-md border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm text-gray-700">Page {{ productsMeta.current_page || 1 }} of {{ productsMeta.last_page || 1 }}</span>
                    <button type="button" :disabled="(productsMeta.current_page || 1) >= (productsMeta.last_page || 1)" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" @click="onProductsPageChange((productsMeta.current_page || 1) + 1)">Next</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- VAS Requests -->
            <div v-show="activeTab === 'vas-requests'" class="space-y-4">
              <h2 class="text-lg font-semibold text-gray-900">VAS Requests</h2>
              <p v-if="client?.account_number" class="text-sm text-gray-600">
                Requests pulled for Account Number: {{ client.account_number }}
              </p>
              <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full border-collapse">
                  <thead class="bg-gray-100">
                    <tr>
                      <th class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Request ID</th>
                      <th class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Submission Date</th>
                      <th class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Request Type</th>
                      <th class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Status</th>
                      <th class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Activity</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="vasLoading" class="bg-white">
                      <td colspan="5" class="px-4 py-8 text-center text-gray-500">Loading…</td>
                    </tr>
                    <tr v-else-if="!vasRequests.length" class="bg-white">
                      <td colspan="5" class="px-4 py-8 text-center text-gray-500">No VAS requests found.</td>
                    </tr>
                    <tr
                      v-for="row in vasRequests"
                      :key="row.id"
                      class="border-b border-gray-200 bg-white hover:bg-gray-50"
                    >
                      <td class="px-4 py-2 text-sm text-gray-900">{{ row.request_id }}</td>
                      <td class="px-4 py-2 text-sm text-gray-900">{{ row.submitted_at }}</td>
                      <td class="px-4 py-2 text-sm text-gray-900">{{ row.request_type }}</td>
                      <td class="px-4 py-2">
                        <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(row.status)]">
                          {{ row.status }}
                        </span>
                      </td>
                      <td class="px-4 py-2 text-sm text-gray-900">{{ row.description || '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Customer Support -->
            <div v-show="activeTab === 'customer-support'" class="space-y-4">
              <h2 class="text-lg font-semibold text-gray-900">Customer Support</h2>
              <p v-if="client?.account_number" class="text-sm text-gray-600">
                Tickets pulled for Account Number: {{ client.account_number }}
              </p>
              <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full border-collapse">
                  <thead class="bg-gray-100">
                    <tr>
                      <th class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Ticket ID</th>
                      <th class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Submission Date</th>
                      <th class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Issue Category</th>
                      <th class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Status</th>
                      <th class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Activity</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="csLoading" class="bg-white">
                      <td colspan="5" class="px-4 py-8 text-center text-gray-500">Loading…</td>
                    </tr>
                    <tr v-else-if="!customerSupport.length" class="bg-white">
                      <td colspan="5" class="px-4 py-8 text-center text-gray-500">No tickets found.</td>
                    </tr>
                    <tr
                      v-for="row in customerSupport"
                      :key="row.id"
                      class="border-b border-gray-200 bg-white hover:bg-gray-50"
                    >
                      <td class="px-4 py-2 text-sm text-gray-900">{{ row.ticket_id }}</td>
                      <td class="px-4 py-2 text-sm text-gray-900">{{ row.submitted_at }}</td>
                      <td class="px-4 py-2 text-sm text-gray-900">{{ row.issue_category }}</td>
                      <td class="px-4 py-2">
                        <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(row.status)]">
                          {{ row.status }}
                        </span>
                      </td>
                      <td class="px-4 py-2 text-sm text-gray-900">{{ row.issue_description || '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Alerts -->
            <div v-show="activeTab === 'alerts'" class="space-y-4">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <h2 class="text-lg font-semibold text-gray-900">Alerts</h2>
                  <p class="text-sm text-gray-600">Alerts for: {{ client?.company_name }}</p>
                </div>
                <div class="flex items-center gap-2">
                  <button type="button" class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="alertColumnModal = true">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" /></svg>
                    Columns
                  </button>
                  <button
                    v-if="canEdit"
                    type="button"
                    class="inline-flex items-center gap-1 rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                    @click="openAlertModal"
                  >
                    + Add Manual Alert
                  </button>
                </div>
              </div>

              <!-- General Filters -->
              <div class="flex flex-wrap items-end gap-3 rounded border border-gray-200 bg-gray-50 p-3">
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Alert Type</label>
                  <select v-model="alertFilterType" class="rounded border border-gray-300 px-3 py-1.5 text-sm min-w-[180px]">
                    <option value="">All Types</option>
                    <option v-for="t in ALERT_TYPE_OPTIONS" :key="t" :value="t">{{ t }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                  <select v-model="alertFilterStatus" class="rounded border border-gray-300 px-3 py-1.5 text-sm min-w-[200px]">
                    <option value="">All Status</option>
                    <option v-for="s in ALERT_STATUS_OPTIONS" :key="s" :value="s">{{ s }}</option>
                  </select>
                </div>
                <button type="button" class="rounded bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700" @click="applyAlertFilters">Apply</button>
                <button type="button" class="rounded border border-gray-300 bg-white px-4 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100" @click="clearAlertFilters">Clear Filters</button>
                <button type="button" class="ml-auto inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100" @click="showAlertAdvanced = !showAlertAdvanced">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                  Advanced Filters
                  <svg :class="['h-3 w-3 transition-transform', showAlertAdvanced ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
              </div>

              <!-- Advanced Filters -->
              <div v-show="showAlertAdvanced" class="rounded border border-gray-200 bg-gray-50 p-3">
                <div class="flex flex-wrap items-end gap-3">
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Resolved</label>
                    <select v-model="alertAdvFilters.resolved" class="rounded border border-gray-300 px-3 py-1.5 text-sm min-w-[140px]">
                      <option value="">All</option>
                      <option value="1">Yes</option>
                      <option value="0">No</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Manager</label>
                    <select v-model="alertAdvFilters.manager_id" class="rounded border border-gray-300 px-3 py-1.5 text-sm min-w-[180px]">
                      <option value="">All Managers</option>
                      <option v-for="m in managerOptions" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Expiry From</label>
                    <input v-model="alertAdvFilters.expiry_from" type="date" class="rounded border border-gray-300 px-3 py-1.5 text-sm" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Expiry To</label>
                    <input v-model="alertAdvFilters.expiry_to" type="date" class="rounded border border-gray-300 px-3 py-1.5 text-sm" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Created From</label>
                    <input v-model="alertAdvFilters.created_from" type="date" class="rounded border border-gray-300 px-3 py-1.5 text-sm" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Created To</label>
                    <input v-model="alertAdvFilters.created_to" type="date" class="rounded border border-gray-300 px-3 py-1.5 text-sm" />
                  </div>
                  <button type="button" class="rounded bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700" @click="applyAlertFilters">Apply</button>
                  <button type="button" class="rounded border border-gray-300 bg-white px-4 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100" @click="clearAlertAdvFilters">Clear Advanced</button>
                </div>
              </div>

              <!-- Alerts table -->
              <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full border-collapse">
                  <thead class="bg-gray-100">
                    <tr>
                      <th
                        v-for="col in alertVisibleCols"
                        :key="col"
                        class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700 select-none"
                        :class="ALERT_SORTABLE.includes(col) ? 'cursor-pointer hover:bg-gray-200' : ''"
                        @click="toggleAlertSort(col)"
                      >
                        <span class="inline-flex items-center gap-1">
                          {{ alertColLabel(col) }}
                          <template v-if="ALERT_SORTABLE.includes(col)">
                            <svg v-if="alertSort === col && alertOrder === 'asc'" class="h-3.5 w-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                            <svg v-else-if="alertSort === col && alertOrder === 'desc'" class="h-3.5 w-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            <svg v-else class="h-3 w-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                          </template>
                        </span>
                      </th>
                      <th class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="alertsLoading" class="bg-white">
                      <td :colspan="alertVisibleCols.length + 1" class="px-4 py-8 text-center text-gray-500">Loading…</td>
                    </tr>
                    <tr v-else-if="!alertsList.length" class="bg-white">
                      <td :colspan="alertVisibleCols.length + 1" class="px-4 py-8 text-center text-gray-500">No alerts found.</td>
                    </tr>
                    <tr
                      v-for="row in alertsList"
                      :key="row.id"
                      class="border-b border-gray-200 bg-white hover:bg-gray-50"
                    >
                      <td
                        v-for="col in alertVisibleCols"
                        :key="col"
                        class="px-4 py-2 text-sm text-gray-900"
                        :class="!ALERT_READONLY_COLS.includes(col) ? 'cursor-pointer' : ''"
                        @dblclick="openAlertCellEdit(row, col)"
                      >
                        <!-- Inline editing -->
                        <template v-if="isAlertEditing(row.id, col)">
                          <select
                            v-if="ALERT_DROPDOWN_COLS.includes(col)"
                            v-model="alertEditValue"
                            class="w-full rounded border border-green-400 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-green-500"
                            @blur="saveAlertCellEdit"
                            @keydown.enter="saveAlertCellEdit"
                            @keydown.escape="cancelAlertEdit"
                          >
                            <option v-if="col === 'alert_type'" v-for="o in ALERT_TYPE_OPTIONS" :key="o" :value="o">{{ o }}</option>
                            <option v-if="col === 'status'" v-for="o in ALERT_STATUS_OPTIONS" :key="o" :value="o">{{ o }}</option>
                          </select>
                          <input
                            v-else-if="col === 'expiry_date'"
                            v-model="alertEditValue"
                            type="date"
                            class="w-full rounded border border-green-400 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-green-500"
                            @blur="saveAlertCellEdit"
                            @keydown.enter="saveAlertCellEdit"
                            @keydown.escape="cancelAlertEdit"
                          />
                          <input
                            v-else-if="col === 'days_remaining'"
                            v-model="alertEditValue"
                            type="number"
                            min="0"
                            class="w-full rounded border border-green-400 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-green-500"
                            @blur="saveAlertCellEdit"
                            @keydown.enter="saveAlertCellEdit"
                            @keydown.escape="cancelAlertEdit"
                          />
                          <input
                            v-else
                            v-model="alertEditValue"
                            type="text"
                            class="w-full rounded border border-green-400 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-green-500"
                            @blur="saveAlertCellEdit"
                            @keydown.enter="saveAlertCellEdit"
                            @keydown.escape="cancelAlertEdit"
                          />
                        </template>
                        <!-- Display mode -->
                        <template v-else>
                          <template v-if="col === 'days_remaining'">
                            <span
                              v-if="row.days_remaining != null"
                              :class="[
                                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                row.days_remaining <= 14 ? 'bg-red-100 text-red-700' : row.days_remaining <= 30 ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-700',
                              ]"
                            >{{ row.days_remaining }} days</span>
                            <span v-else>—</span>
                          </template>
                          <template v-else-if="col === 'status'">
                            <span
                              :class="[
                                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                row.status === 'Active' ? 'bg-green-100 text-green-700' :
                                row.status === 'Expired' ? 'bg-red-100 text-red-700' :
                                row.status === 'Resolved' ? 'bg-blue-100 text-blue-700' :
                                'bg-yellow-100 text-yellow-700',
                              ]"
                            >{{ row.status || '—' }}</span>
                          </template>
                          <template v-else-if="col === 'resolved'">
                            <span :class="row.resolved ? 'text-green-600 font-medium' : 'text-gray-500'">{{ row.resolved ? 'Yes' : 'No' }}</span>
                          </template>
                          <template v-else>{{ row[col] != null && row[col] !== '' ? row[col] : '—' }}</template>
                        </template>
                      </td>
                      <td class="px-4 py-2">
                        <button
                          v-if="!row.resolved"
                          type="button"
                          class="inline-flex items-center gap-1 text-sm font-medium text-green-600 hover:text-green-800"
                          @click="resolveAlert(row)"
                        >
                          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                          Resolve
                        </button>
                        <span v-else class="inline-flex items-center gap-1 text-xs text-gray-400">
                          <svg class="h-3.5 w-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                          Resolved
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Add Alert Modal -->
            <div v-if="showAlertModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showAlertModal = false">
              <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-5 py-3">
                  <h3 class="text-lg font-semibold text-gray-900">Add Manual Alert</h3>
                  <button type="button" class="text-gray-400 hover:text-gray-600 text-xl leading-none" @click="showAlertModal = false">&times;</button>
                </div>
                <form @submit.prevent="submitAlert" class="p-5 space-y-4">
                  <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                      <label class="block text-sm font-medium text-gray-700 mb-1">Alert Type <span class="text-red-500">*</span></label>
                      <select v-model="alertForm.alert_type" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                        <option value="">Select type</option>
                        <option v-for="t in ALERT_TYPE_OPTIONS" :key="t" :value="t">{{ t }}</option>
                      </select>
                      <p v-if="alertFormErrors.alert_type" class="mt-1 text-xs text-red-600">{{ alertFormErrors.alert_type }}</p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                      <input type="text" :value="client?.company_name" readonly class="w-full rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-700 cursor-default" />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
                      <input type="text" :value="client?.account_number" readonly class="w-full rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-700 cursor-default" />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date <span class="text-red-500">*</span></label>
                      <input v-model="alertForm.expiry_date" type="date" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
                      <p v-if="alertFormErrors.expiry_date" class="mt-1 text-xs text-red-600">{{ alertFormErrors.expiry_date }}</p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Days Remaining</label>
                      <input v-model="alertForm.days_remaining" type="number" min="0" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" placeholder="Auto or manual" />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Manager</label>
                      <select v-model="alertForm.manager_id" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                        <option value="">Select manager</option>
                        <option v-for="m in managerOptions" :key="m.id" :value="m.id">{{ m.name }}</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                      <select v-model="alertForm.status" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                        <option value="">Select status</option>
                        <option v-for="s in ALERT_STATUS_OPTIONS" :key="s" :value="s">{{ s }}</option>
                      </select>
                      <p v-if="alertFormErrors.status" class="mt-1 text-xs text-red-600">{{ alertFormErrors.status }}</p>
                    </div>
                  </div>
                  <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="showAlertModal = false">Cancel</button>
                    <button type="submit" :disabled="alertSaving" class="rounded bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                      {{ alertSaving ? 'Saving…' : 'Save Alert' }}
                    </button>
                  </div>
                </form>
              </div>
            </div>

            <!-- Alert Column Customizer Modal -->
            <div v-if="alertColumnModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="alertColumnModal = false">
              <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-5 py-3">
                  <h3 class="text-lg font-semibold text-gray-900">Customize Columns</h3>
                  <button type="button" class="text-gray-400 hover:text-gray-600 text-xl leading-none" @click="alertColumnModal = false">&times;</button>
                </div>
                <div class="p-5 space-y-3 max-h-[60vh] overflow-y-auto">
                  <label
                    v-for="c in ALL_ALERT_COLUMNS"
                    :key="c.key"
                    class="flex items-center gap-2 rounded px-2 py-1.5 hover:bg-gray-50 cursor-pointer"
                  >
                    <input
                      type="checkbox"
                      :checked="alertVisibleCols.includes(c.key)"
                      class="h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500"
                      @change="toggleAlertColumn(c.key)"
                    />
                    <span class="text-sm text-gray-700">{{ c.label }}</span>
                  </label>
                </div>
                <div class="flex items-center justify-between border-t px-5 py-3">
                  <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="alertVisibleCols = [...DEFAULT_ALERT_COLS]">Reset to Default</button>
                  <button type="button" class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700" @click="alertColumnModal = false">Done</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Audit history modal -->
        <div
          v-if="auditModalVisible"
          class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4"
          @click.self="auditModalVisible = false"
        >
          <div class="max-h-[80vh] w-full max-w-2xl flex flex-col overflow-hidden rounded-lg bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
              <h3 class="text-lg font-semibold text-gray-900">Change History</h3>
              <button
                type="button"
                class="rounded p-1 text-gray-500 hover:bg-gray-100"
                @click="auditModalVisible = false"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <div class="max-h-[60vh] overflow-y-auto p-4">
              <p v-if="auditLoading" class="text-center text-gray-500">Loading…</p>
              <p v-else-if="!audits.length" class="text-center text-gray-500">No changes recorded.</p>
              <div v-else class="space-y-3">
                <div
                  v-for="a in audits"
                  :key="a.id"
                  class="rounded border border-gray-200 bg-gray-50 p-3 text-sm"
                >
                  <div class="flex flex-wrap items-center gap-1.5">
                    <span class="font-medium text-gray-700">{{ a.field_label || a.field_name }}:</span>
                    <span class="text-red-500 line-through break-all">{{ a.old_value ?? '(empty)' }}</span>
                    <span class="text-gray-400">&rarr;</span>
                    <span class="text-green-600 break-all">{{ a.new_value ?? '(empty)' }}</span>
                  </div>
                  <p class="mt-1.5 text-xs text-gray-500">
                    {{ new Date(a.changed_at).toLocaleString() }} by {{ a.changed_by_name || a.changed_by || '—' }}
                  </p>
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
      </template>

      <div v-else-if="!loading" class="rounded-lg border border-gray-200 bg-white p-8 text-center text-gray-500">
        Client not found or you don't have permission to view it.
      </div>
    </div>

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
