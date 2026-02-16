<script setup>
/**
 * Add New Client – full form: Company Information, Contact Details, Address,
 * Submission/Product details, Account Ownership, Status & Notes, System Metadata.
 * Buttons: Cancel, Create Client, Create & Add Another.
 */
import { ref, onMounted, computed, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import clientsApi from '@/services/clientsApi'
import leadSubmissionsApi from '@/services/leadSubmissionsApi'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Toast from '@/components/Toast.vue'
import DateInputDdMmYyyy from '@/components/DateInputDdMmYyyy.vue'
import { toDdMmYyyy, fromDdMmYyyy } from '@/lib/dateFormat'
import { useFormDraft } from '@/composables/useFormDraft'

const router = useRouter()
const auth = useAuthStore()

const STATUS_OPTIONS = [
  { value: 'pending', label: 'Pending' },
  { value: 'in_progress', label: 'In Progress' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
]

const COMPANY_CATEGORY_OPTIONS = [
  { value: '', label: 'Select category' },
  { value: 'LLC', label: 'LLC' },
  { value: 'FZCO', label: 'FZCO' },
  { value: 'FZE', label: 'FZE' },
  { value: 'Branch', label: 'Branch' },
  { value: 'Other', label: 'Other' },
]

const YES_NO_OPTIONS = [
  { value: '', label: 'Select' },
  { value: 'yes', label: 'Yes' },
  { value: 'no', label: 'No' },
]

const PAID_UNPAID_OPTIONS = [
  { value: '', label: 'Paid / Unpaid' },
  { value: 'paid', label: 'Paid' },
  { value: 'unpaid', label: 'Unpaid' },
]

const MAX_CONTACTS = 5
const teamOptions = ref({ managers: [], team_leaders: [], sales_agents: [] })
const loading = ref(false)
const error = ref('')
const successMessage = ref('')
const fieldErrors = ref({})
const errorAlertEl = ref(null)
const successAlertEl = ref(null)
const showResultModal = ref(false)
const resultModalType = ref('success')
const resultModalMessage = ref('')
const redirectCountdown = ref(15)
let redirectTimerId = null
const showToast = ref(false)
const toastType = ref('success')
const toastMessage = ref('')
const toastCountdown = ref(null)
let toastRedirectTimer = null

const form = ref({
  company_name: '',
  account_number: '',
  status: 'pending',
  submitted_at: '',
  manager_id: null,
  team_leader_id: null,
  sales_agent_id: null,
  service_type: '',
  product_type: '',
  address: '',
  product_name: '',
  mrc: '',
  quantity: '',
  other: '',
  migration_numbers: '',
  fiber: '',
  order_number: '',
  wo_number: '',
  completion_date: '',
  submission_type: '',
  service_category: '',
  offer: '',
  activity: '',
  work_order_status: '',
  activation_date: '',
  contract_term: '36 months',
  contract_end_date: '',
  clawback_chum: 'Yes',
  remarks: '',
  payment_connection: '',
  contract_type: '',
  renewal_alert: '',
  additional_notes: '',
  csrs: [{ user_id: null }],
  company_detail: {
    trade_license_issuing_authority: '',
    company_category: '',
    trade_license_number: '',
    trade_license_expiry_date: '',
    establishment_card_number: '',
    establishment_card_expiry_date: '',
    account_taken_from: '',
    account_mapping_date: '',
    account_transfer_given_to: '',
    account_transfer_given_date: '',
    account_manager_name: '',
    first_bill: '',
    second_bill: '',
    third_bill: '',
    fourth_bill: '',
    additional_comment_1: '',
    additional_comment_2: '',
  },
  contacts: [
    { name: '', designation: '', contact_number: '', alternate_number: '', email: '', as_updated_or_not: '', as_expiry_date: '', additional_note: '' },
  ],
  addresses: [
    { full_address: '', area: '', building: '', unit: '', emirates: '' },
  ],
})

const { draftSaving, draftSavedAt, clearDraft } = useFormDraft('client', 'new', form)

const createdByLabel = computed(() => auth.user?.name ? `${auth.user.name} (Auto)` : 'Current User (Auto)')
const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
const createdDateLabel = computed(() => {
  const d = new Date()
  return `${d.getDate()}-${MONTH_NAMES[d.getMonth()]}-${d.getFullYear()}`
})

onMounted(async () => {
  try {
    const res = await leadSubmissionsApi.getTeamOptions()
    const data = res?.data ?? res ?? {}
    teamOptions.value = {
      managers: data.managers ?? [],
      team_leaders: data.team_leaders ?? [],
      sales_agents: data.sales_agents ?? [],
    }
  } catch {
    // keep empty
  }
})

function addContact() {
  if (form.value.contacts.length >= MAX_CONTACTS) return
  form.value.contacts.push({ name: '', designation: '', contact_number: '', alternate_number: '', email: '', as_updated_or_not: '', as_expiry_date: '', additional_note: '' })
  form.value.addresses.push({ full_address: '', area: '', building: '', unit: '', emirates: '' })
}

function removeContact(index) {
  if (form.value.contacts.length <= 1) return
  form.value.contacts.splice(index, 1)
  form.value.addresses.splice(index, 1)
}

function addCsr() {
  form.value.csrs.push({ user_id: null })
}

function removeCsr(index) {
  if (form.value.csrs.length <= 1) return
  form.value.csrs.splice(index, 1)
}

/** CSR options for dropdown at index cidx: exclude users already selected in other CSR slots; always include current selection */
function csrOptionsForIndex(cidx) {
  const agents = teamOptions.value.sales_agents ?? []
  const currentId = form.value.csrs[cidx]?.user_id ?? null
  const selectedInOtherSlots = form.value.csrs
    .map((c, i) => (i !== cidx ? c.user_id : null))
    .filter(Boolean)
  return agents.filter((s) => s.id === currentId || !selectedInOtherSlots.includes(s.id))
}

function buildPayload() {
  const f = form.value
  const csrNames = f.csrs.map((c) => (c.user_id ? (teamOptions.value.sales_agents.find((s) => s.id === c.user_id)?.name ?? null) : null)).filter(Boolean)
  const payload = {
    company_name: f.company_name,
    account_number: f.account_number || null,
    status: f.status,
    submitted_at: fromDdMmYyyy(f.submitted_at) || null,
    manager_id: f.manager_id || null,
    team_leader_id: f.team_leader_id || null,
    sales_agent_id: f.sales_agent_id || null,
    csrs: f.csrs.map((c) => ({ user_id: c.user_id || null })).filter((c) => c.user_id),
    service_type: f.service_type || null,
    product_type: f.product_type || null,
    address: f.address || null,
    product_name: f.product_name || null,
    mrc: f.mrc || null,
    quantity: f.quantity ? parseInt(f.quantity, 10) : null,
    migration_numbers: f.migration_numbers || null,
    fiber: f.fiber || null,
    order_number: f.order_number || null,
    wo_number: f.wo_number || null,
    completion_date: fromDdMmYyyy(f.completion_date) || null,
    other: f.activity || f.other || null,
    additional_notes: [f.remarks, f.additional_notes].filter(Boolean).join('\n') || null,
    contract_end_date: fromDdMmYyyy(f.contract_end_date) || null,
    payment_connection: f.payment_connection || null,
    contract_type: f.contract_type || null,
    renewal_alert: f.renewal_alert ? parseInt(f.renewal_alert, 10) : null,
    csr_name_1: csrNames[0] || null,
    csr_name_2: csrNames[1] || null,
    csr_name_3: csrNames[2] || null,
  }
  const cd = f.company_detail
  const hasCompanyDetail = Object.values(cd).some((v) => v !== '' && v != null)
  if (hasCompanyDetail) {
    payload.company_detail = {
      trade_license_issuing_authority: cd.trade_license_issuing_authority || null,
      company_category: cd.company_category || null,
      trade_license_number: cd.trade_license_number || null,
      trade_license_expiry_date: fromDdMmYyyy(cd.trade_license_expiry_date) || null,
      establishment_card_number: cd.establishment_card_number || null,
      establishment_card_expiry_date: fromDdMmYyyy(cd.establishment_card_expiry_date) || null,
      account_taken_from: cd.account_taken_from || null,
      account_mapping_date: fromDdMmYyyy(cd.account_mapping_date) || null,
      account_transfer_given_to: cd.account_transfer_given_to || null,
      account_transfer_given_date: fromDdMmYyyy(cd.account_transfer_given_date) || null,
      account_manager_name: cd.account_manager_name?.trim() || null,
      csr_name_1: csrNames[0] || null,
      csr_name_2: csrNames[1] || null,
      csr_name_3: csrNames[2] || null,
      first_bill: cd.first_bill || null,
      second_bill: cd.second_bill || null,
      third_bill: cd.third_bill || null,
      fourth_bill: cd.fourth_bill || null,
      additional_comment_1: cd.additional_comment_1 || null,
      additional_comment_2: cd.additional_comment_2 || null,
    }
  }
  payload.contacts = f.contacts
    .map((c) => ({
      name: c.name || null,
      designation: c.designation || null,
      contact_number: c.contact_number || null,
      alternate_number: c.alternate_number || null,
      email: c.email || null,
      as_updated_or_not: c.as_updated_or_not || null,
      as_expiry_date: fromDdMmYyyy(c.as_expiry_date) || null,
      additional_note: c.additional_note || null,
    }))
    .filter((c) => c.name || c.contact_number || c.email)
  payload.addresses = f.addresses
    .map((a) => ({
      full_address: a.full_address || null,
      area: a.area || null,
      building: a.building || null,
      unit: a.unit || null,
      emirates: a.emirates || null,
    }))
    .filter((a) => a.full_address || a.area || a.building || a.unit)
  return payload
}

function resetForm() {
  form.value = {
    company_name: '',
    account_number: '',
    status: 'pending',
    submitted_at: '',
    manager_id: null,
    team_leader_id: null,
    sales_agent_id: null,
    service_type: '',
    product_type: '',
    address: '',
    product_name: '',
    mrc: '',
    quantity: '',
    other: '',
    migration_numbers: '',
    fiber: '',
    order_number: '',
    wo_number: '',
    completion_date: '',
    submission_type: '',
    service_category: '',
    offer: '',
    activity: '',
    work_order_status: '',
    activation_date: '',
    contract_term: '36 months',
    contract_end_date: '',
    clawback_chum: 'Yes',
    remarks: '',
    payment_connection: '',
    contract_type: '',
    renewal_alert: '',
  additional_notes: '',
  csrs: [{ user_id: null }],
  company_detail: {
    trade_license_issuing_authority: '',
    company_category: '',
    trade_license_number: '',
    trade_license_expiry_date: '',
    establishment_card_number: '',
    establishment_card_expiry_date: '',
    account_taken_from: '',
    account_mapping_date: '',
    account_transfer_given_to: '',
    account_transfer_given_date: '',
    account_manager_name: '',
    first_bill: '',
    second_bill: '',
    third_bill: '',
    fourth_bill: '',
    additional_comment_1: '',
    additional_comment_2: '',
  },
  contacts: [
    { name: '', designation: '', contact_number: '', alternate_number: '', email: '', as_updated_or_not: '', as_expiry_date: '', additional_note: '' },
  ],
  addresses: [
    { full_address: '', area: '', building: '', unit: '', emirates: '' },
  ],
}
  error.value = ''
  successMessage.value = ''
  fieldErrors.value = {}
}

function validateForm() {
  const f = form.value
  const cd = f.company_detail
  const errs = {}

  if (!f.company_name?.trim()) errs.company_name = 'Company Name is required.'
  if (!cd.company_category?.trim()) errs.company_category = 'Company Category is required.'
  if (!cd.trade_license_number?.trim()) errs.trade_license_number = 'Trade License Number is required.'
  if (!cd.first_bill?.trim()) errs.first_bill = 'First Bill is required.'
  if (!cd.second_bill?.trim()) errs.second_bill = 'Second Bill is required.'
  if (!cd.third_bill?.trim()) errs.third_bill = 'Third Bill is required.'
  if (!cd.fourth_bill?.trim()) errs.fourth_bill = 'Fourth Bill is required.'
  if (!cd.account_manager_name?.trim()) errs.account_manager_name = 'Account Manager Name is required.'

  const hasCsr = f.csrs.some((c) => c.user_id)
  if (!hasCsr) errs.csr = 'At least one CSR is required.'

  if (!f.status?.trim()) errs.status = 'Account Status is required.'

  const c0 = f.contacts[0]
  if (c0) {
    if (!c0.name?.trim()) errs.contact_0_name = 'Contact Person Name is required.'
    if (!c0.contact_number?.trim()) errs.contact_0_contact_number = 'Contact Number is required.'
    if (!c0.email?.trim()) errs.contact_0_email = 'Email ID is required.'
  }

  fieldErrors.value = errs
  return Object.keys(errs).length === 0
}

const requiredFieldLabels = {
  company_name: 'Company Name',
  company_category: 'Company Category',
  trade_license_number: 'Trade License Number',
  first_bill: 'First Bill',
  second_bill: 'Second Bill',
  third_bill: 'Third Bill',
  fourth_bill: 'Fourth Bill',
  account_manager_name: 'Account Manager Name',
  csr: 'CSR (at least one)',
  status: 'Account Status',
  contact_0_name: 'Contact Person Name',
  contact_0_contact_number: 'Contact Number',
  contact_0_email: 'Email ID',
}

function inputClass(fieldKey) {
  return fieldErrors.value[fieldKey]
    ? 'mt-1 block w-full rounded border border-red-500 px-3 py-2 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500'
    : 'mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500'
}

function selectClass(fieldKey) {
  return fieldErrors.value[fieldKey]
    ? 'mt-1 block w-full rounded border border-red-500 px-3 py-2 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500'
    : 'mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500'
}

async function submit(andAddAnother = false) {
  if (!validateForm()) {
    const keys = Object.keys(fieldErrors.value)
    const labels = keys.map((k) => requiredFieldLabels[k] || k).join(', ')
    error.value = keys.length === 1
      ? `Required: ${labels}.`
      : `Please fix the ${keys.length} errors below: ${labels}.`
    await nextTick()
    errorAlertEl.value?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    return
  }
  error.value = ''
  successMessage.value = ''
  fieldErrors.value = {}
  loading.value = true
  showResultModal.value = false
  showToast.value = false
  if (toastRedirectTimer) clearTimeout(toastRedirectTimer)
  try {
    const payload = buildPayload()
    const raw = await clientsApi.store(payload)
    const inner = raw?.data
    const success =
      raw?.success === true ||
      (inner && (inner.id != null && inner.company_name != null)) ||
      (raw?.id != null && raw?.company_name != null)

    if (success) {
      error.value = ''
      await clearDraft()
      if (andAddAnother) {
        successMessage.value = 'New client is added successfully. You can add another below.'
        resetForm()
        setTimeout(() => { successMessage.value = '' }, 4000)
      } else {
        resultModalType.value = 'success'
        resultModalMessage.value = 'New client is added successfully'
        successMessage.value = 'New client is added successfully'
        showResultModal.value = true
        startRedirectCountdown(3)
        setTimeout(() => {
          showResultModal.value = false
          router.push('/clients')
        }, 3500)
      }
    } else {
      resultModalType.value = 'error'
      resultModalMessage.value = 'Client can not be added'
      showResultModal.value = true
      error.value = 'Client can not be added'
    }
  } catch (e) {
    const msg = e?.response?.data?.message
    const errs = e?.response?.data?.errors
    const detail = msg || (errs ? Object.values(errs).flat().filter(Boolean).join(' ') : '')
    resultModalMessage.value = detail ? `Client can not be added. ${detail}` : 'Client can not be added'
    resultModalType.value = 'error'
    showResultModal.value = true
    error.value = resultModalMessage.value
  } finally {
    loading.value = false
  }
}

function cancel() {
  router.push('/clients')
}

function startRedirectCountdown(seconds = 3) {
  redirectCountdown.value = seconds
  if (redirectTimerId) clearInterval(redirectTimerId)
  redirectTimerId = setInterval(() => {
    redirectCountdown.value -= 1
    if (redirectCountdown.value <= 0) {
      if (redirectTimerId) clearInterval(redirectTimerId)
      redirectTimerId = null
      showResultModal.value = false
      router.push('/clients')
    }
  }, 1000)
}

function goToClients() {
  if (redirectTimerId) {
    clearInterval(redirectTimerId)
    redirectTimerId = null
  }
  showResultModal.value = false
  router.push('/clients')
}

function closeResultModal() {
  showResultModal.value = false
  if (redirectTimerId) {
    clearInterval(redirectTimerId)
    redirectTimerId = null
  }
  if (resultModalType.value === 'success') {
    router.push('/clients')
  }
}

function closeToast() {
  showToast.value = false
  if (toastRedirectTimer) {
    clearTimeout(toastRedirectTimer)
    toastRedirectTimer = null
  }
}
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-4 pl-4 pr-0 relative">
    <!-- Success / Error toast (prominent, accessible, dismissible) -->
    <Toast
      :show="showToast"
      :type="toastType"
      :message="toastMessage"
      :countdown="toastCountdown"
      @dismiss="closeToast"
    />

    <!-- Loader overlay when request is in progress -->
    <div v-if="loading" class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-black/40">
      <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-8 py-6 shadow-lg">
        <svg class="h-10 w-10 animate-spin text-[#21A67B]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
        <p class="text-sm font-medium text-gray-700">Creating client…</p>
      </div>
    </div>

    <!-- Success / Error result modal (teleported to body so it always appears on top) -->
    <Teleport to="body">
      <div v-if="showResultModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" :aria-labelledby="resultModalType === 'success' ? 'result-success-title' : 'result-error-title'">
      <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
        <div v-if="resultModalType === 'success'" class="flex flex-col items-center text-center">
          <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-100">
            <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h2 id="result-success-title" class="text-lg font-semibold text-gray-900">Success</h2>
          <p class="mt-2 text-gray-700">{{ resultModalMessage }}</p>
          <p class="mt-3 text-sm text-gray-500">Redirecting to clients listing in {{ redirectCountdown }} seconds…</p>
          <button type="button" class="mt-4 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700" @click="goToClients">
            Go to clients
          </button>
        </div>
        <div v-else class="flex flex-col items-center text-center">
          <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-red-100">
            <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <h2 id="result-error-title" class="text-lg font-semibold text-gray-900">Error</h2>
          <p class="mt-2 text-gray-700">{{ resultModalMessage }}</p>
          <button type="button" class="mt-4 rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-300" @click="closeResultModal">Close</button>
        </div>
      </div>
    </div>
    </Teleport>

    <div class="max-w-full space-y-4">
      <router-link to="/clients" class="inline-block text-sm text-blue-600 hover:text-blue-700">
        ← Back to Clients
      </router-link>
      <div>
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-2xl font-semibold text-gray-900">Add New Client</h1>
          <span v-if="draftSavedAt" class="text-xs text-gray-400 flex items-center gap-1">
            <svg v-if="draftSaving" class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="4" class="opacity-25" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
            <svg v-else class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            Draft saved
          </span>
          <Breadcrumbs />
        </div>
        <p class="mt-2 text-sm text-gray-500">Create a new client master record.</p>
      </div>

      <div v-if="error" ref="errorAlertEl" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
        {{ error }}
      </div>
      <div v-if="successMessage" ref="successAlertEl" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700" role="status">
        {{ successMessage }}
      </div>

      <form @submit.prevent="submit(false)" class="space-y-6">
        <!-- Company Information -->
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
          <div class="flex items-start gap-3 border-b border-gray-200 pb-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#E0F7F2]">
              <svg class="h-5 w-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <div>
              <h2 class="text-base font-semibold text-gray-900">Company Information</h2>
              <p class="mt-1 text-sm text-gray-500">Basic company details and registration information.</p>
            </div>
          </div>
          <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Company Name <span class="text-red-600">*</span></label>
              <input v-model="form.company_name" type="text" placeholder="Enter company name" :class="inputClass('company_name')" />
              <p v-if="fieldErrors.company_name" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.company_name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Account Number</label>
              <input v-model="form.account_number" type="text" placeholder="Enter unique account number" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
              <p class="mt-0.5 text-xs text-gray-500">Must be unique across all clients.</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Trade License Issuing Authority</label>
              <select v-model="form.company_detail.trade_license_issuing_authority" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                <option value="">Select authority</option>
                <option value="DED">DED</option>
                <option value="JAFZA">JAFZA</option>
                <option value="DMCC">DMCC</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Company Category <span class="text-red-600">*</span></label>
              <select v-model="form.company_detail.company_category" :class="selectClass('company_category')">
                <option v-for="o in COMPANY_CATEGORY_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
              <p v-if="fieldErrors.company_category" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.company_category }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Trade License Number <span class="text-red-600">*</span></label>
              <input v-model="form.company_detail.trade_license_number" type="text" placeholder="Enter trade license number" :class="inputClass('trade_license_number')" />
              <p v-if="fieldErrors.trade_license_number" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.trade_license_number }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Trade License Expiry Date</label>
              <DateInputDdMmYyyy v-model="form.company_detail.trade_license_expiry_date" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Establishment Card Number</label>
              <input v-model="form.company_detail.establishment_card_number" type="text" placeholder="Enter establishment card number" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Establishment Card Expiry Date</label>
              <DateInputDdMmYyyy v-model="form.company_detail.establishment_card_expiry_date" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Account Taken From</label>
              <input v-model="form.company_detail.account_taken_from" type="text" placeholder="Select" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Account Mapping Date</label>
              <DateInputDdMmYyyy v-model="form.company_detail.account_mapping_date" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Account Transfer Given To</label>
              <input v-model="form.company_detail.account_transfer_given_to" type="text" placeholder="Select" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Account Transfer Given Date</label>
              <DateInputDdMmYyyy v-model="form.company_detail.account_transfer_given_date" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">First Bill <span class="text-red-600">*</span></label>
              <select v-model="form.company_detail.first_bill" :class="selectClass('first_bill')">
                <option v-for="o in PAID_UNPAID_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
              <p v-if="fieldErrors.first_bill" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.first_bill }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Second Bill <span class="text-red-600">*</span></label>
              <select v-model="form.company_detail.second_bill" :class="selectClass('second_bill')">
                <option v-for="o in PAID_UNPAID_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
              <p v-if="fieldErrors.second_bill" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.second_bill }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Third Bill <span class="text-red-600">*</span></label>
              <select v-model="form.company_detail.third_bill" :class="selectClass('third_bill')">
                <option v-for="o in PAID_UNPAID_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
              <p v-if="fieldErrors.third_bill" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.third_bill }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Fourth Bill <span class="text-red-600">*</span></label>
              <select v-model="form.company_detail.fourth_bill" :class="selectClass('fourth_bill')">
                <option v-for="o in PAID_UNPAID_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
              <p v-if="fieldErrors.fourth_bill" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.fourth_bill }}</p>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Additional Note</label>
              <textarea v-model="form.company_detail.additional_comment_1" rows="2" placeholder="Additional notes" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Additional Note</label>
              <textarea v-model="form.company_detail.additional_comment_2" rows="2" placeholder="Additional notes" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
          </div>
        </div>

        <!-- Contact Details (design per 1st image: icon left, heading+subtitle, link right parallel to heading) -->
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 pb-3">
            <div class="flex items-start gap-3">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#E8F0FE]">
                <svg class="h-5 w-5 text-[#4285F4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
              <div>
                <h2 class="text-base font-semibold text-[#202124]">Contact Details</h2>
                <p class="mt-1 text-sm text-[#5F6368]">Add up to 5 contact persons and addresses.</p>
              </div>
            </div>
            <span
              v-if="form.contacts.length < MAX_CONTACTS"
              role="button"
              tabindex="0"
              class="shrink-0 cursor-pointer text-sm font-medium text-[#21A67B] hover:underline focus:outline-none focus:ring-0"
              @click="addContact()"
              @keydown.enter.space.prevent="addContact()"
            >
              + Add Another Contact
            </span>
          </div>
          <div v-for="(contact, idx) in form.contacts" :key="idx" class="mt-4 rounded-lg border border-gray-100 bg-gray-50/50 p-4">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-gray-700">Contact Person {{ idx + 1 }}</span>
              <button v-if="form.contacts.length > 1" type="button" class="text-sm text-red-600 hover:text-red-700" @click="removeContact(idx)">Remove</button>
            </div>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <div>
                <label class="block text-sm font-medium text-gray-700">Contact Person Name <span class="text-red-600">*</span></label>
                <input v-model="contact.name" type="text" placeholder="Enter contact person name" :class="idx === 0 ? inputClass('contact_0_name') : 'mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500'" />
                <p v-if="idx === 0 && fieldErrors.contact_0_name" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.contact_0_name }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Designation</label>
                <input v-model="contact.designation" type="text" placeholder="Enter designation" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Contact Number <span class="text-red-600">*</span></label>
                <input v-model="contact.contact_number" type="text" placeholder="Enter contact number" :class="idx === 0 ? inputClass('contact_0_contact_number') : 'mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500'" />
                <p v-if="idx === 0 && fieldErrors.contact_0_contact_number" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.contact_0_contact_number }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Alternate Number</label>
                <input v-model="contact.alternate_number" type="text" placeholder="Enter alternate number" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Email ID <span class="text-red-600">*</span></label>
                <input v-model="contact.email" type="email" placeholder="Enter email address" :class="idx === 0 ? inputClass('contact_0_email') : 'mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500'" />
                <p v-if="idx === 0 && fieldErrors.contact_0_email" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.contact_0_email }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">AS Updated or Not</label>
                <select v-model="contact.as_updated_or_not" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                  <option v-for="o in YES_NO_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
              </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Account Transfer Given Date</label>
              <DateInputDdMmYyyy v-model="contact.as_expiry_date" />
            </div>
              <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Additional Note</label>
                <textarea v-model="contact.additional_note" rows="2" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
              </div>
            </div>
            <!-- Address for this contact -->
            <div v-if="form.addresses[idx]" class="mt-4 rounded border border-gray-100 bg-white p-3">
              <span class="text-sm font-medium text-gray-700">Address {{ idx + 1 }}</span>
              <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2">
                  <label class="block text-sm font-medium text-gray-700">Full Address</label>
                  <p class="mt-1 rounded bg-gray-50 px-3 py-2 text-sm text-gray-600">{{ [form.addresses[idx].unit, form.addresses[idx].building, form.addresses[idx].area, form.addresses[idx].emirates].filter(Boolean).join(', ') || '—' }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">BRN</label>
                  <input v-model="form.addresses[idx].unit" type="text" placeholder="e.g. 1205" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Building</label>
                  <input v-model="form.addresses[idx].building" type="text" placeholder="Enter building" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Area</label>
                  <input v-model="form.addresses[idx].area" type="text" placeholder="Enter area" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Emirates</label>
                  <input v-model="form.addresses[idx].emirates" type="text" placeholder="Enter emirates" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Product or Service (Company Name and Account Number auto-fill from Company Information) -->
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
          <h2 class="border-b border-gray-200 pb-3 text-base font-semibold text-gray-900">Product or Service</h2>
          <p class="mt-1 text-sm text-gray-500">Company Name and Account Number below mirror the values from Company Information.</p>
          <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
              <label class="block text-sm font-medium text-gray-700">Company Name</label>
              <input v-model="form.company_name" type="text" placeholder="Enter company name" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Submission Date</label>
              <DateInputDdMmYyyy v-model="form.submitted_at" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Manager Name</label>
              <select v-model="form.manager_id" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                <option :value="null">Select</option>
                <option v-for="m in teamOptions.managers" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Team Leader</label>
              <select v-model="form.team_leader_id" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                <option :value="null">Select</option>
                <option v-for="t in teamOptions.team_leaders" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Sales Agent Name</label>
              <select v-model="form.sales_agent_id" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                <option :value="null">Select</option>
                <option v-for="s in teamOptions.sales_agents" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
            <!-- Row 2: Submission Type, Service Category, Service Type, Product Type, Address -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Submission Type</label>
              <select v-model="form.submission_type" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                <option value="">Select</option>
                <option value="New | Submit">New | Submit</option>
                <option value="Resubmit">Resubmit</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Service Category</label>
              <select v-model="form.service_category" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                <option value="">Select</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Service Type</label>
              <select v-model="form.service_type" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                <option value="">Select</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Product Type</label>
              <select v-model="form.product_type" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                <option value="">Select</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Address</label>
              <select v-model="form.address" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                <option value="">Select</option>
              </select>
            </div>
            <!-- Row 3: Product Name, MRC, Quantity, Offer, Migration Numbers -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Product Name</label>
              <input v-model="form.product_name" type="text" placeholder="Product Name" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">MRC</label>
              <input v-model="form.mrc" type="text" placeholder="Enter MRC" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Quantity</label>
              <input v-model="form.quantity" type="number" min="0" placeholder="Enter Quantity" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Offer</label>
              <input v-model="form.offer" type="text" placeholder="Enter Offer" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Migration Numbers</label>
              <input v-model="form.migration_numbers" type="text" placeholder="Enter Migration Number" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <!-- Row 4: Activity, Account Number, Work Order, Work Order Status, Activation Date -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Activity</label>
              <input v-model="form.activity" type="text" placeholder="Enter Activity" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Account Number</label>
              <input v-model="form.account_number" type="text" placeholder="Enter Account Number" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Work Order</label>
              <input v-model="form.wo_number" type="text" placeholder="Enter Work Order" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Work Order Status</label>
              <input v-model="form.work_order_status" type="text" placeholder="Enter Work Order Status" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Activation Date</label>
              <DateInputDdMmYyyy v-model="form.activation_date" />
            </div>
            <!-- Row 5: Contract Term, Deactivation Date, Clawback / Chum, Remarks -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Contract Term</label>
              <input v-model="form.contract_term" type="text" placeholder="36 months" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Deactivation Date</label>
              <DateInputDdMmYyyy v-model="form.contract_end_date" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Clawback / Chum</label>
              <input v-model="form.clawback_chum" type="text" placeholder="Yes" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <div class="lg:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Remarks</label>
              <input v-model="form.remarks" type="text" placeholder="Primary connection" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <!-- Row 6: Additional Note full width -->
            <div class="sm:col-span-2 lg:col-span-5">
              <label class="block text-sm font-medium text-gray-700">Additional Note</label>
              <textarea v-model="form.additional_notes" rows="2" placeholder="Additional notes" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
          </div>
        </div>

        <!-- Account Manager & CSR -->
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 pb-3">
            <div class="flex items-start gap-3">
              <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-[#EFE6FF]">
                <svg class="h-5 w-5 text-[#7A5AF8]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" />
                </svg>
              </span>
              <div>
                <h2 class="text-base font-semibold text-gray-900">Account Manager & CSR</h2>
                <p class="mt-0.5 text-sm text-gray-500">Assign account manager and customer service representatives.</p>
              </div>
            </div>
            <button type="button" class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700" @click="addCsr">
              Add CSR
            </button>
          </div>
          <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Account Manager Name <span class="text-red-600">*</span></label>
              <select v-model="form.company_detail.account_manager_name" :class="selectClass('account_manager_name')">
                <option value="">Select account manager</option>
                <option value="Irfan siddque">Irfan siddque</option>
              </select>
              <p v-if="fieldErrors.account_manager_name" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.account_manager_name }}</p>
            </div>
            <div v-for="(csr, cidx) in form.csrs" :key="cidx" class="flex items-end gap-2">
              <div class="min-w-0 flex-1">
                <label class="block text-sm font-medium text-gray-700">CSR Name <span class="text-red-600">*</span></label>
                <select v-model="csr.user_id" :class="cidx === 0 ? selectClass('csr') : 'mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500'">
                  <option :value="null">Select CSR</option>
                  <option v-for="s in csrOptionsForIndex(cidx)" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <p v-if="cidx === 0 && fieldErrors.csr" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.csr }}</p>
              </div>
              <button v-if="form.csrs.length > 1" type="button" class="shrink-0 rounded p-2 text-red-600 hover:bg-red-50" title="Remove CSR" @click="removeCsr(cidx)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Status & Notes -->
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
          <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#D1FAE5]">
              <svg class="h-5 w-5 text-[#34D399]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </span>
            <div>
              <h2 class="text-xl font-bold text-gray-900">Status & Notes</h2>
              <p class="mt-0.5 text-sm text-gray-500">Account status and additional information.</p>
            </div>
          </div>
          <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
              <label class="block text-sm font-medium text-gray-700">Account Status <span class="text-red-600">*</span></label>
              <select v-model="form.status" :class="selectClass('status')">
                <option value="">Select status</option>
                <option v-for="s in STATUS_OPTIONS" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
              <p v-if="fieldErrors.status" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.status }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Account Taken From</label>
              <input v-model="form.company_detail.account_taken_from" type="text" placeholder="Enter source" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Account Mapping Date</label>
              <DateInputDdMmYyyy v-model="form.company_detail.account_mapping_date" />
            </div>
            <div class="sm:col-span-3">
              <label class="block text-sm font-medium text-gray-700">Additional Comment</label>
              <textarea v-model="form.company_detail.additional_comment_2" rows="3" placeholder="Enter any additional notes or comments" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
            </div>
          </div>
        </div>

        <!-- System Metadata (per 1st image: info icon in light gray box, description under heading, then line) -->
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
          <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-200">
              <svg class="h-5 w-5 text-gray-600" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z" />
              </svg>
            </span>
            <div>
              <h2 class="text-xl font-bold text-gray-900">System Metadata</h2>
              <p class="mt-0.5 text-sm text-gray-500">Automatically generated information.</p>
            </div>
          </div>
          <hr class="mt-4 border-gray-200" />
          <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-500">Created By</label>
              <p class="mt-1 rounded bg-gray-100 px-3 py-2 text-sm text-gray-700">{{ createdByLabel }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-500">Created Date</label>
              <p class="mt-1 rounded bg-gray-100 px-3 py-2 text-sm text-gray-700">{{ createdDateLabel }}</p>
            </div>
          </div>
        </div>

        <!-- Footer: audit note + Cancel, Create Client, Create & Add Another -->
        <div class="border-t border-gray-200 bg-white pt-6">
          <p class="mb-4 text-sm text-gray-500">All actions will be logged in Audit Logs.</p>
          <div class="flex flex-wrap items-center justify-end gap-3">
            <button type="button" class="inline-flex items-center rounded-lg border border-blue-500 bg-white px-4 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50 disabled:opacity-50" :disabled="loading" @click="cancel">
              Cancel
            </button>
            <button type="submit" class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50" :disabled="loading">
              {{ loading ? 'Creating…' : 'Create Client' }}
            </button>
            <button type="button" class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50" :disabled="loading" @click="submit(true)">
              <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Create & Add Another
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>
