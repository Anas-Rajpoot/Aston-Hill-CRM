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
import Pagination from '@/components/Pagination.vue'

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
const auditModalVisible = ref(false)
const audits = ref([])
const auditsMeta = ref({})
const auditLoading = ref(false)

// Contact Details
const contactDraft = ref([])
const addressDraft = ref([])
const contactSaveLoading = ref(false)

// Products & Services
const products = ref([])
const productsMeta = ref({})
const productsLoading = ref(false)
const productsSort = ref('submitted_at')
const productsOrder = ref('desc')
const allColumns = ref([])
const visibleColumns = ref(['company_name', 'submitted_at', 'manager', 'team_leader', 'sales_agent', 'status', 'service_type', 'product_type', 'address', 'product_name', 'mrc', 'quantity', 'other', 'migration_numbers', 'order_number', 'wo_number', 'completion_date', 'additional_notes'])
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

function displayVal(val) {
  return val != null && val !== '' ? String(val) : '—'
}

function formatDate(d) {
  if (!d) return '—'
  const str = typeof d === 'string' ? d.trim().slice(0, 10) : ''
  if (!str) return '—'
  const out = toDdMmYyyy(str)
  return out || '—'
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
      per_page: 10,
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
    const res = await clientsApi.alerts(id.value, { per_page: 10 })
    alertsList.value = res.data ?? []
    alertsMeta.value = res.meta ?? {}
  } catch {
    alertsList.value = []
  } finally {
    alertsLoading.value = false
  }
}

watch(activeTab, (tab) => {
  if (tab === 'products-services') {
    loadProducts()
    loadColumns()
  } else if (tab === 'vas-requests') loadVasRequests()
  else if (tab === 'customer-support') loadCustomerSupport()
  else if (tab === 'alerts') loadAlerts()
})

async function saveContactDetails() {
  if (!id.value) return
  contactSaveLoading.value = true
  try {
    await clientsApi.updateContacts(id.value, { contacts: contactDraft.value })
    await loadClient()
  } catch {}
  finally {
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
  loadClient().then(() => {
    if (activeTab.value === 'products-services') {
      loadProducts()
      loadColumns()
    } else if (activeTab.value === 'vas-requests') loadVasRequests()
    else if (activeTab.value === 'customer-support') loadCustomerSupport()
    else if (activeTab.value === 'alerts') loadAlerts()
  })
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] py-4 px-4 sm:px-6">
    <div class="mx-auto max-w-7xl space-y-4">
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
          <h1 class="mt-1 text-xl font-semibold text-gray-900">
            Client Profile – {{ client?.company_name ?? '…' }}
          </h1>
          <Breadcrumbs />
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
            <div>
              <p class="text-xs font-medium text-gray-500">CSR Name</p>
              <p class="text-sm text-gray-900">{{ displayVal(client.csr_name_1) }}</p>
            </div>
            <div>
              <p class="text-xs font-medium text-gray-500">CSR Name</p>
              <p class="text-sm text-gray-900">{{ displayVal(client.csr_name_2) }}</p>
            </div>
            <div>
              <p class="text-xs font-medium text-gray-500">CSR Name</p>
              <p class="text-sm text-gray-900">{{ displayVal(client.csr_name_3) }}</p>
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
                    class="rounded border border-gray-300 bg-white p-2 text-gray-600 hover:bg-gray-50"
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
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                <div>
                  <p class="text-xs font-medium text-gray-500">Company Name</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ displayVal(client.company_name) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Account Number</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ displayVal(client.account_number) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Trade License Issuing Authority</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ displayVal(companyDetail?.trade_license_issuing_authority) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Company Category</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ displayVal(companyDetail?.company_category) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Trade License Number</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ displayVal(companyDetail?.trade_license_number) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Trade License Expiry Date</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ formatDate(companyDetail?.trade_license_expiry_date) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Establishment Card Number</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ displayVal(companyDetail?.establishment_card_number) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Establishment Card Expiry Date</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ formatDate(companyDetail?.establishment_card_expiry_date) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Account Taken From</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ displayVal(companyDetail?.account_taken_from) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Account Mapping Date</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ formatDate(companyDetail?.account_mapping_date) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Account Transfer Given To</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ displayVal(companyDetail?.account_transfer_given_to) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Account Transfer Given Date</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ formatDate(companyDetail?.account_transfer_given_date) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Account Manager Name</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ displayVal(companyDetail?.account_manager_name) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">CSR Name</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ displayVal(companyDetail?.csr_name_1) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">First Bill</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ displayVal(companyDetail?.first_bill) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Additional Comment 1</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ displayVal(companyDetail?.additional_comment_1) }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-gray-500">Additional Comment 2</p>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ displayVal(companyDetail?.additional_comment_2) }}</p>
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
                        v-model="a.full_address"
                        type="text"
                        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        :readonly="!canEdit"
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
                  :columns="visibleColumns"
                  :data="products"
                  :sort="productsSort"
                  :order="productsOrder"
                  :loading="productsLoading"
                  :current-page="productsMeta.current_page || 1"
                  :per-page="productsMeta.per_page || 10"
                  @sort="onProductsSort"
                />
              </div>
              <div v-if="productsMeta.total > 0" class="flex justify-between border-t border-gray-200 pt-2">
                <p class="text-sm text-gray-600">
                  Showing {{ ((productsMeta.current_page || 1) - 1) * (productsMeta.per_page || 10) + 1 }} to
                  {{ Math.min((productsMeta.current_page || 1) * (productsMeta.per_page || 10), productsMeta.total) }} of
                  {{ productsMeta.total }} entries
                </p>
                <Pagination
                  v-if="(productsMeta.last_page || 1) > 1"
                  :meta="{
                    prev_page_url: (productsMeta.current_page || 1) > 1 ? '#' : null,
                    next_page_url: (productsMeta.current_page || 1) < (productsMeta.last_page || 1) ? '#' : null,
                    current_page: productsMeta.current_page || 1,
                    last_page: productsMeta.last_page || 1,
                  }"
                  @change="(p) => { productsMeta.current_page = p; loadProducts() }"
                />
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
                <h2 class="text-lg font-semibold text-gray-900">Alerts</h2>
                <button
                  v-if="canEdit"
                  type="button"
                  class="inline-flex items-center rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700"
                >
                  + Add Manual Alert
                </button>
              </div>
              <p class="text-sm text-gray-600">Alerts For: {{ client.company_name }}</p>
              <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full border-collapse">
                  <thead class="bg-gray-100">
                    <tr>
                      <th class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Alert Type</th>
                      <th class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Expiry Date</th>
                      <th class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Days Remaining</th>
                      <th class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Manager</th>
                      <th class="border-b border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="alertsLoading" class="bg-white">
                      <td colspan="5" class="px-4 py-8 text-center text-gray-500">Loading…</td>
                    </tr>
                    <tr v-else-if="!alertsList.length" class="bg-white">
                      <td colspan="5" class="px-4 py-8 text-center text-gray-500">No alerts.</td>
                    </tr>
                    <tr
                      v-for="row in alertsList"
                      :key="row.id"
                      class="border-b border-gray-200 bg-white hover:bg-gray-50"
                    >
                      <td class="px-4 py-2 text-sm text-gray-900">{{ row.alert_type }}</td>
                      <td class="px-4 py-2 text-sm text-gray-900">{{ row.expiry_date }}</td>
                      <td class="px-4 py-2">
                        <span
                          v-if="row.days_remaining != null"
                          :class="[
                            'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                            row.days_remaining <= 14 ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-700',
                          ]"
                        >
                          {{ row.days_remaining }} days
                        </span>
                        <span v-else>—</span>
                      </td>
                      <td class="px-4 py-2 text-sm text-gray-900">{{ row.manager || '—' }}</td>
                      <td class="px-4 py-2 text-sm text-gray-900">{{ row.status || '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Audit history modal -->
        <div
          v-if="auditModalVisible"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
          @click.self="auditModalVisible = false"
        >
          <div class="max-h-[80vh] w-full max-w-2xl overflow-hidden rounded-lg bg-white shadow-xl">
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
                  <p class="font-medium text-gray-700">{{ a.field_name }}</p>
                  <p class="text-gray-600">Old: {{ a.old_value ?? '—' }}</p>
                  <p class="text-gray-600">New: {{ a.new_value ?? '—' }}</p>
                  <p class="mt-1 text-xs text-gray-500">
                    {{ new Date(a.changed_at).toLocaleString() }} by {{ a.changed_by ?? '—' }}
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
  </div>
</template>
