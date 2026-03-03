<script setup>
/**
 * Lead Resubmission Form – for rejected leads only (super admin or creator).
 * Matches design: Primary Info, Service Category cards, Contact, Resubmission Reason, Documents, Cancel / Save as Draft / Submit.
 */
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import leadSubmissionsApi from '@/services/leadSubmissionsApi'

const route = useRoute()
const router = useRouter()
const EMIRATES_OPTIONS = [
  'Abu Dhabi',
  'Dubai',
  'Sharjah',
  'Ajman',
  'Umm Al Quwain',
  'Ras Al Khaimah',
  'Fujairah',
]

const leadId = computed(() => {
  const id = route.params.id
  return id != null ? Number(id) : null
})

const loading = ref(true)
const saving = ref(false)
const error = ref(null)
const lead = ref(null)
const categories = ref([])
const docDefs = ref([
  { key: 'trade_license', label: 'Trade License', required: true },
  { key: 'establishment_card', label: 'Establishment Card', required: false },
  { key: 'owner_emirates_id', label: 'Owner Emirates ID', required: false },
  { key: 'loa_poa', label: 'LOA / POA', required: false },
  { key: 'ejari', label: 'Ejari', required: false },
  { key: 'proposal_form', label: 'Proposal Form', required: false },
  { key: 'main_application', label: 'Main Application', required: false },
  { key: 'customer_confirmation_email', label: 'Customer Confirmation Email', required: false },
  { key: 'as_person_eid', label: 'AS Person EID', required: false },
  { key: 'rfs_marketing_approvals', label: 'RFS / Marketing / Migration Approvals', required: false },
  { key: 'fnp_binder', label: 'FNP Binder', required: false },
  { key: 'etisatis_bill', label: 'Etisatis Bill', required: false },
])
const types = ref([])
const loadingTypes = ref(false)
const managers = ref([])
const teamLeaders = ref([])
const salesAgents = ref([])

const form = ref({
  account_number: '',
  company_name: '',
  authorized_signatory_name: '',
  email: '',
  contact_number_gsm: '',
  alternate_contact_number: '',
  address: '',
  emirate: '',
  location_coordinates: '',
  service_category_id: null,
  service_type_id: null,
  product: '',
  offer: '',
  mrc_aed: '',
  quantity: '',
  ae_domain: '',
  gaid: '',
  previous_activity: '',
  resubmission_reason: '',
  remarks: '',
  manager_id: null,
  team_leader_id: null,
  sales_agent_id: null,
})

const ALL_DOCUMENT_FIELDS = [
  { key: 'trade_license', label: 'Trade License', required: true },
  { key: 'establishment_card', label: 'Establishment Card', required: false },
  { key: 'owner_emirates_id', label: 'Owner Emirates ID', required: false },
  { key: 'loa_poa', label: 'LOA / POA', required: false },
  { key: 'ejari', label: 'Ejari', required: false },
  { key: 'proposal_form', label: 'Proposal Form', required: false },
  { key: 'main_application', label: 'Main Application', required: false },
  { key: 'customer_confirmation_email', label: 'Customer Confirmation Email', required: false },
  { key: 'as_person_eid', label: 'AS Person EID', required: false },
  { key: 'rfs_marketing_approvals', label: 'RFS / Marketing / Migration Approvals', required: false },
  { key: 'fnp_binder', label: 'FNP Binder', required: false },
  { key: 'etisatis_bill', label: 'Etisatis Bill', required: false },
]

const docFiles = ref({
  trade_license: [],
  establishment_card: [],
  owner_emirates_id: [],
})
const addDocumentSlots = ref([{ id: 0 }])
const additionalFiles = ref({})
const uploadingDocs = ref(false)
const primarySectionCollapsed = ref(false)
const serviceSectionCollapsed = ref(false)
const teamSectionCollapsed = ref(false)
const documentsSectionCollapsed = ref(false)

const categoryIcons = {
  fixed: 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0',
  fms: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
  gsm: 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z',
  other: 'M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z',
}

function categoryIcon(slug) {
  const s = (slug || '').toLowerCase()
  if (s.includes('fixed')) return categoryIcons.fixed
  if (s.includes('fms')) return categoryIcons.fms
  if (s.includes('gsm')) return categoryIcons.gsm
  return categoryIcons.other
}

function normalizeDocumentDefinitions(definitions = []) {
  const incoming = (Array.isArray(definitions) ? definitions : []).filter((d) => d?.key !== 'vat_certificate')
  const byKey = new Map(incoming.filter((d) => d?.key).map((d) => [d.key, d]))
  const merged = ALL_DOCUMENT_FIELDS.map((base) => {
    const fromApi = byKey.get(base.key)
    return {
      ...base,
      ...(fromApi || {}),
      key: base.key,
      label: fromApi?.label || base.label,
      required: base.key === 'trade_license',
    }
  })

  incoming.forEach((d) => {
    if (!d?.key) return
    if (!merged.some((m) => m.key === d.key)) {
      merged.push({ ...d, required: d.key === 'trade_license' })
    }
  })

  return merged
}

async function loadData() {
  const id = leadId.value
  if (!id) return
  loading.value = true
  error.value = null
  try {
    const [res, teamRes] = await Promise.all([
      leadSubmissionsApi.getResubmissionData(id),
      leadSubmissionsApi.getTeamOptions().catch(() => ({ data: {} })),
    ])
    const teamData = teamRes?.data ?? teamRes ?? {}
    managers.value = teamData.managers ?? []
    teamLeaders.value = teamData.team_leaders ?? []
    salesAgents.value = teamData.sales_agents ?? []
    const data = res?.data ?? res
    lead.value = data.lead
    categories.value = data.categories ?? []
    docDefs.value = normalizeDocumentDefinitions(data.resubmission_documents ?? [])
    if (lead.value) {
      form.value = {
        account_number: lead.value.account_number ?? '',
        company_name: lead.value.company_name ?? '',
        authorized_signatory_name: lead.value.authorized_signatory_name ?? '',
        email: lead.value.email ?? '',
        contact_number_gsm: lead.value.contact_number_gsm ?? '',
        alternate_contact_number: lead.value.alternate_contact_number ?? '',
        address: lead.value.address ?? '',
        emirate: lead.value.emirate ?? '',
        location_coordinates: lead.value.location_coordinates ?? '',
        service_category_id: lead.value.service_category_id ?? null,
        service_type_id: lead.value.service_type_id ?? null,
        product: lead.value.product ?? '',
        offer: lead.value.offer ?? '',
        mrc_aed: lead.value.mrc_aed ?? '',
        quantity: lead.value.quantity ?? '',
        ae_domain: lead.value.ae_domain ?? '',
        gaid: lead.value.gaid ?? '',
        previous_activity: lead.value.previous_activity ?? '',
        resubmission_reason: lead.value.resubmission_reason ?? '',
        remarks: lead.value.remarks ?? '',
        manager_id: lead.value.manager_id ?? null,
        team_leader_id: lead.value.team_leader_id ?? null,
        sales_agent_id: lead.value.sales_agent_id ?? null,
      }
    }
    if (form.value.service_category_id) {
      await loadTypes(form.value.service_category_id)
    }
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load resubmission data.'
    lead.value = null
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
}

async function loadTypes(categoryId) {
  if (!categoryId) {
    types.value = []
    form.value.service_type_id = null
    return
  }
  loadingTypes.value = true
  try {
    const res = await leadSubmissionsApi.getServiceTypesByCategory(categoryId)
    types.value = Array.isArray(res?.data) ? res.data : (res ?? [])
    if (!types.value.some((t) => Number(t.id) === Number(form.value.service_type_id))) {
      form.value.service_type_id = null
    }
  } catch {
    types.value = []
  } finally {
    loadingTypes.value = false
  }
}

function onCategorySelect(cat) {
  const id = cat?.id ?? cat
  form.value.service_category_id = id
  form.value.service_type_id = null
  loadTypes(id)
}

function onFileChange(docKey, event) {
  const files = event.target.files
  if (!files?.length) return
  docFiles.value[docKey] = Array.from(files)
  if (docKey === 'trade_license') error.value = null
}

function removeDocFile(docKey, index) {
  docFiles.value[docKey] = docFiles.value[docKey].filter((_, i) => i !== index)
}

function existingDocsFor(docKey) {
  if (!lead.value?.documents?.length) return []
  return lead.value.documents.filter((d) => d.doc_key === docKey)
}

const knownDocKeys = computed(() => {
  return new Set((docDefs.value || []).map(d => d.key))
})

const existingAdditionalDocs = computed(() => {
  if (!lead.value?.documents?.length) return []
  return lead.value.documents.filter((d) => !d.doc_key || !knownDocKeys.value.has(d.doc_key))
})

async function downloadDoc(doc) {
  const id = leadId.value
  if (!id || !doc?.id) return
  try {
    const blob = await leadSubmissionsApi.downloadDocument(id, doc.id)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = doc.original_name || 'document'
    a.click()
    URL.revokeObjectURL(url)
  } catch {}
}

const canSubmit = computed(() => {
  const f = form.value
  return f.company_name?.trim() && f.contact_number_gsm?.trim()
})

const fieldErrors = ref({})

function validatePhone(value) {
  if (!value) return null
  if (/\s/.test(value)) return 'Must not contain spaces.'
  if (!/^\d+$/.test(value)) return 'Must contain only digits.'
  if (!value.startsWith('971')) return 'Must start with 971.'
  if (value.length !== 12) return 'Must be exactly 12 digits.'
  return null
}

function validateCoordinates(value) {
  if (!value) return null
  const pattern = /^-?\d{1,3}(\.\d+)?\s*,\s*-?\d{1,3}(\.\d+)?$/
  if (!pattern.test(value)) return 'Invalid format. Use: lat, long (e.g. 25.2048, 55.2708)'
  const [latStr, lonStr] = value.split(',').map(s => s.trim())
  const lat = parseFloat(latStr)
  const lon = parseFloat(lonStr)
  if (lat < -90 || lat > 90) return 'Latitude must be between -90 and 90.'
  if (lon < -180 || lon > 180) return 'Longitude must be between -180 and 180.'
  return null
}

function validateEmail(value) {
  if (!value) return null
  const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!pattern.test(value)) return 'Enter a valid email address.'
  return null
}

const AE_DOMAIN_FORBIDDEN_KEYWORDS = ['lac', 'rac', 'rat', 'sgns']
const AE_DOMAIN_SPECIAL = /[@#$%^&*()\-+={}\[\]:;'"\\<>,_?/!_`|\s]/
function validateAeDomain(value) {
  const v = (value || '').trim()
  if (!v) return { valid: true, message: '' }
  if (/\s/.test(v)) return { valid: false, message: 'The domain must not contain spaces.' }
  if (/[0-9]/.test(v)) return { valid: false, message: 'The domain must not contain numbers (0–9).' }
  if (AE_DOMAIN_SPECIAL.test(v)) return { valid: false, message: 'The domain must not contain special characters such as: @ # $ % ^ & * ( ) - + = { } [ ] : ; \' " \\ <> , ? / ! _ ` |' }
  const lower = v.toLowerCase()
  for (const kw of AE_DOMAIN_FORBIDDEN_KEYWORDS) {
    if (lower.includes(kw)) return { valid: false, message: 'The domain must not contain these keywords (case-insensitive): LAC, RAC, RAT, SGNS.' }
  }
  if ((v.match(/\./g) || []).length !== 1) return { valid: false, message: 'The domain must contain only one dot (.).' }
  if (!lower.endsWith('.ae')) return { valid: false, message: 'The domain must end with .ae (example: example.ae).' }
  return { valid: true, message: 'You can use this Domain.' }
}

const aeDomainValidation = computed(() => validateAeDomain(form.value.ae_domain))
const currentSubmitterName = computed(() => lead.value?.creator_name || lead.value?.created_by_name || 'Current User')

const filteredTeamLeaders = computed(() => {
  const managerId = form.value.manager_id
  if (!managerId) return teamLeaders.value
  return teamLeaders.value.filter((teamLeader) => String(teamLeader.manager_id) === String(managerId))
})

const filteredSalesAgents = computed(() => {
  const teamLeaderId = form.value.team_leader_id
  if (teamLeaderId) {
    return salesAgents.value.filter((salesAgent) => String(salesAgent.team_leader_id) === String(teamLeaderId))
  }
  const managerId = form.value.manager_id
  if (managerId) {
    return salesAgents.value.filter((salesAgent) => String(salesAgent.manager_id) === String(managerId))
  }
  return salesAgents.value
})

const inputClass = (field) =>
  `w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500 ${fieldErrors.value[field] ? 'border-red-500' : 'border-gray-300'}`

function onPhoneInput(field, event) {
  form.value[field] = event.target.value.replace(/\D/g, '')
  fieldErrors.value[field] = null
}

watch(
  () => form.value.manager_id,
  () => {
    form.value.team_leader_id = null
    form.value.sales_agent_id = null
  }
)

watch(
  () => form.value.team_leader_id,
  (teamLeaderId) => {
    if (!teamLeaderId) {
      form.value.sales_agent_id = null
    }
  }
)

function validateFields() {
  const errs = {}
  if (!form.value.company_name?.trim()) errs.company_name = 'Company Name as per Trade License is required.'
  if (!form.value.product?.trim()) errs.product = 'Product is required.'
  if (!form.value.contact_number_gsm?.trim()) {
    errs.contact_number_gsm = 'Contact Number is required.'
  } else {
    const phoneErr = validatePhone(form.value.contact_number_gsm.trim())
    if (phoneErr) errs.contact_number_gsm = phoneErr
  }
  if (form.value.alternate_contact_number?.trim()) {
    const altPhoneErr = validatePhone(form.value.alternate_contact_number.trim())
    if (altPhoneErr) errs.alternate_contact_number = altPhoneErr
  }
  if (form.value.email?.trim()) {
    const emailErr = validateEmail(form.value.email.trim())
    if (emailErr) errs.email = emailErr
  }
  if (!form.value.address?.trim()) errs.address = 'Complete Address as per Ejari is required.'
  if (!form.value.emirate?.trim()) errs.emirate = 'Emirates is required.'
  if (form.value.location_coordinates?.trim()) {
    const coordErr = validateCoordinates(form.value.location_coordinates.trim())
    if (coordErr) errs.location_coordinates = coordErr
  }
  if (form.value.ae_domain?.trim()) {
    const aeResult = validateAeDomain(form.value.ae_domain)
    if (!aeResult.valid) errs.ae_domain = aeResult.message
  }
  if (!form.value.manager_id) errs.manager_id = 'Manager Name is required.'
  if (!form.value.previous_activity?.trim()) errs.previous_activity = 'Old Activity is required.'
  if (!form.value.resubmission_reason?.trim()) errs.resubmission_reason = 'Resubmission Reason is required.'
  fieldErrors.value = errs
  return Object.keys(errs).length === 0
}

/** Trade License is required on Save (submit). Can be existing on lead or newly uploaded. */
const hasTradeLicense = computed(() => {
  if (lead.value?.documents?.some((d) => d.doc_key === 'trade_license')) return true
  return (docFiles.value.trade_license?.length ?? 0) > 0
})

async function saveDraft() {
  if (!validateFields()) return
  await submitForm('draft')
}

async function submitResubmission() {
  if (!canSubmit.value) return
  if (!validateFields()) return
  if (!hasTradeLicense.value) {
    error.value = 'Trade License is required.'
    return
  }
  error.value = null
  await submitForm('submit')
}

async function submitForm(action) {
  const id = leadId.value
  if (!id) return
  saving.value = true
  error.value = null
  try {
    const payload = {
      action,
      account_number: form.value.account_number || null,
      company_name: form.value.company_name || null,
      authorized_signatory_name: form.value.authorized_signatory_name || null,
      email: form.value.email || null,
      contact_number_gsm: form.value.contact_number_gsm || null,
      alternate_contact_number: form.value.alternate_contact_number || null,
      address: form.value.address || null,
      emirate: form.value.emirate || null,
      location_coordinates: form.value.location_coordinates || null,
      service_category_id: form.value.service_category_id || null,
      service_type_id: form.value.service_type_id || null,
      product: form.value.product || null,
      offer: form.value.offer || null,
      mrc_aed: form.value.mrc_aed || null,
      quantity: form.value.quantity || null,
      ae_domain: form.value.ae_domain || null,
      gaid: form.value.gaid || null,
      previous_activity: form.value.previous_activity || null,
      resubmission_reason: form.value.resubmission_reason || null,
      remarks: form.value.remarks || null,
      manager_id: form.value.manager_id || null,
      team_leader_id: form.value.team_leader_id || null,
      sales_agent_id: form.value.sales_agent_id || null,
    }
    const files = {}
    Object.entries(docFiles.value).forEach(([key, list]) => {
      if (list?.length) files[key] = list
    })
    const extras = getAllAdditionalFiles()
    if (extras.length) files.additional = extras
    const { data } = await leadSubmissionsApi.resubmit(id, payload, Object.keys(files).length ? files : null)
    if (action === 'submit') {
      router.push('/lead-submissions')
    }
  } catch (e) {
    error.value = e?.response?.data?.message || (action === 'draft' ? 'Failed to save draft.' : 'Failed to resubmit.')
  } finally {
    saving.value = false
  }
}

function cancel() {
  router.push(leadId.value ? `/lead-submissions/${leadId.value}` : '/lead-submissions')
}

function addDocumentSlot() {
  const newId = Date.now()
  addDocumentSlots.value = [...addDocumentSlots.value, { id: newId }]
  additionalFiles.value[newId] = []
}

function onAdditionalFileSelect(slotId, e) {
  const input = e?.target
  if (!input?.files?.length) return
  const existing = additionalFiles.value[slotId] || []
  additionalFiles.value[slotId] = [...existing, ...Array.from(input.files)]
  input.value = ''
}

function removeAdditionalFile(slotId, fileIndex) {
  const files = additionalFiles.value[slotId] || []
  additionalFiles.value[slotId] = files.filter((_, i) => i !== fileIndex)
}

function getSlotFiles(slotId) {
  return additionalFiles.value[slotId] || []
}

function getAllAdditionalFiles() {
  const all = []
  for (const files of Object.values(additionalFiles.value)) {
    all.push(...files)
  }
  return all
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-6">
    <div class="w-full space-y-6 px-4">
      <div v-if="loading" class="flex items-center justify-center py-12">
        <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <span class="ml-3 text-gray-600">Loading...</span>
      </div>

      <div v-else-if="error && !lead" class="rounded-lg border border-red-200 bg-red-50 p-6 text-red-700">
        {{ error }}
        <div class="mt-3">
          <button type="button" class="text-sm font-medium underline" @click="loadData">Try again</button>
          <span class="mx-2">|</span>
          <button type="button" class="text-sm font-medium underline" @click="cancel">Back to list</button>
        </div>
      </div>

      <form v-else class="space-y-6" @submit.prevent="submitResubmission">
        <div>
          <button
            type="button"
            class="w-full flex items-center justify-between text-left text-base font-semibold text-gray-900 border-b border-gray-200 pb-2"
            :aria-expanded="(!primarySectionCollapsed).toString()"
            @click.prevent="primarySectionCollapsed = !primarySectionCollapsed"
          >
            <span>Primary Information</span>
            <div class="flex items-center gap-2 text-sm text-gray-500">
              <span>{{ primarySectionCollapsed ? 'Show' : 'Minimize' }}</span>
              <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': primarySectionCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>
          </button>

          <div v-show="!primarySectionCollapsed" class="mt-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
                <input v-model="form.account_number" type="text" placeholder="Enter account number" :class="inputClass('account_number')" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Company Name as per Trade License <span class="text-red-500">*</span></label>
                <input v-model="form.company_name" type="text" placeholder="Enter company name" :class="inputClass('company_name')" @input="fieldErrors.company_name = null" />
                <p v-if="fieldErrors.company_name" class="mt-1 text-sm text-red-600">{{ fieldErrors.company_name }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Authorized Signatory Name</label>
                <input v-model="form.authorized_signatory_name" type="text" placeholder="Enter signatory name" :class="inputClass('authorized_signatory_name')" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number <span class="text-red-500">*</span></label>
                <input v-model="form.contact_number_gsm" type="text" maxlength="12" placeholder="971XXXXXXXXX" :class="inputClass('contact_number_gsm')" @input="onPhoneInput('contact_number_gsm', $event)" />
                <p v-if="fieldErrors.contact_number_gsm" class="mt-1 text-sm text-red-600">{{ fieldErrors.contact_number_gsm }}</p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Alternate Contact Number</label>
                <input v-model="form.alternate_contact_number" type="text" maxlength="12" placeholder="971XXXXXXXXX" :class="inputClass('alternate_contact_number')" @input="onPhoneInput('alternate_contact_number', $event)" />
                <p v-if="fieldErrors.alternate_contact_number" class="mt-1 text-sm text-red-600">{{ fieldErrors.alternate_contact_number }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email ID</label>
                <input v-model="form.email" type="email" placeholder="email@example.com" :class="inputClass('email')" @input="fieldErrors.email = null" />
                <p v-if="fieldErrors.email" class="mt-1 text-sm text-red-600">{{ fieldErrors.email }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">.ae Domain</label>
                <input v-model="form.ae_domain" type="text" placeholder="Enter Domain (e.g. example.ae)" :class="inputClass('ae_domain')" @input="fieldErrors.ae_domain = null" />
                <p v-if="fieldErrors.ae_domain" class="mt-1 text-sm text-red-600">{{ fieldErrors.ae_domain }}</p>
                <p v-else-if="form.ae_domain?.trim() && aeDomainValidation.valid" class="mt-1 text-sm text-green-600">{{ aeDomainValidation.message }}</p>
                <p v-else-if="form.ae_domain?.trim() && !aeDomainValidation.valid" class="mt-1 text-sm text-red-600">{{ aeDomainValidation.message }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">GAID</label>
                <input v-model="form.gaid" type="text" placeholder="Enter GAID" :class="inputClass('gaid')" />
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Product <span class="text-red-500">*</span></label>
                <input v-model="form.product" type="text" placeholder="Search Product" :class="inputClass('product')" @input="fieldErrors.product = null" />
                <p v-if="fieldErrors.product" class="mt-1 text-sm text-red-600">{{ fieldErrors.product }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">MRC (AED)</label>
                <input v-model="form.mrc_aed" type="text" placeholder="0" :class="inputClass('mrc_aed')" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                <input v-model="form.quantity" type="text" placeholder="1" :class="inputClass('quantity')" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Offer</label>
                <input v-model="form.offer" type="text" placeholder="Enter offer details" :class="inputClass('offer')" />
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Complete Address as per Ejari <span class="text-red-500">*</span></label>
                <input v-model="form.address" type="text" placeholder="Enter complete address" :class="inputClass('address')" @input="fieldErrors.address = null" />
                <p v-if="fieldErrors.address" class="mt-1 text-sm text-red-600">{{ fieldErrors.address }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Emirates <span class="text-red-500">*</span></label>
                <select v-model="form.emirate" :class="inputClass('emirate')" @change="fieldErrors.emirate = null">
                  <option value="">Select Emirates</option>
                  <option v-for="e in EMIRATES_OPTIONS" :key="e" :value="e">{{ e }}</option>
                </select>
                <p v-if="fieldErrors.emirate" class="mt-1 text-sm text-red-600">{{ fieldErrors.emirate }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Location Coordinates</label>
                <input v-model="form.location_coordinates" type="text" placeholder="e.g. 25.2048, 55.2708" :class="inputClass('location_coordinates')" @input="fieldErrors.location_coordinates = null" />
                <p v-if="fieldErrors.location_coordinates" class="mt-1 text-sm text-red-600">{{ fieldErrors.location_coordinates }}</p>
              </div>
            </div>
          </div>
        </div>

        <div>
          <button
            type="button"
            class="w-full flex items-center justify-between text-left text-base font-semibold text-gray-900 border-b border-gray-200 pb-2"
            :aria-expanded="(!serviceSectionCollapsed).toString()"
            @click.prevent="serviceSectionCollapsed = !serviceSectionCollapsed"
          >
            <span>Service Details</span>
            <div class="flex items-center gap-2 text-sm text-gray-500">
              <span>{{ serviceSectionCollapsed ? 'Show' : 'Minimize' }}</span>
              <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': serviceSectionCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>
          </button>

          <div v-show="!serviceSectionCollapsed" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Service Categories <span class="text-red-500">*</span></label>
              <select
                v-model="form.service_category_id"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                @change="onCategorySelect(form.service_category_id)"
              >
                <option :value="null">Select Service Category</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Service Types <span class="text-red-500">*</span></label>
              <select
                v-model="form.service_type_id"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                :disabled="!form.service_category_id || loadingTypes"
              >
                <option :value="null">{{ loadingTypes ? 'Loading Service Types...' : 'Select Service Type' }}</option>
                <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Old Activity <span class="text-red-500">*</span></label>
              <input
                v-model="form.previous_activity"
                type="text"
                placeholder="Enter Old Activity"
                :class="inputClass('previous_activity')"
                @input="fieldErrors.previous_activity = null"
              />
              <p v-if="fieldErrors.previous_activity" class="mt-1 text-sm text-red-600">{{ fieldErrors.previous_activity }}</p>
            </div>
          </div>
        </div>

        <div>
          <button
            type="button"
            class="w-full flex items-center justify-between text-left text-base font-semibold text-gray-900 border-b border-gray-200 pb-2"
            :aria-expanded="(!teamSectionCollapsed).toString()"
            @click.prevent="teamSectionCollapsed = !teamSectionCollapsed"
          >
            <span>Team & Remarks</span>
            <div class="flex items-center gap-2 text-sm text-gray-500">
              <span>{{ teamSectionCollapsed ? 'Show' : 'Minimize' }}</span>
              <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': teamSectionCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>
          </button>

          <div v-show="!teamSectionCollapsed" class="mt-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Submitter Name</label>
                <input :value="currentSubmitterName" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-gray-100 text-gray-700" readonly />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Manager Name <span class="text-red-500">*</span></label>
                <select v-model="form.manager_id" :class="inputClass('manager_id')" @change="fieldErrors.manager_id = null">
                  <option :value="null">Select Manager</option>
                  <option v-for="u in managers" :key="u.id" :value="Number(u.id)">{{ u.name }}</option>
                </select>
                <p v-if="fieldErrors.manager_id" class="mt-1 text-sm text-red-600">{{ fieldErrors.manager_id }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Team Leader Name</label>
                <select v-model="form.team_leader_id" :class="inputClass('team_leader_id')" @change="fieldErrors.team_leader_id = null">
                  <option :value="null">Select Team Leader</option>
                  <option v-for="u in filteredTeamLeaders" :key="u.id" :value="Number(u.id)">{{ u.name }}</option>
                </select>
                <p v-if="fieldErrors.team_leader_id" class="mt-1 text-sm text-red-600">{{ fieldErrors.team_leader_id }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sales Agent Name</label>
                <select v-model="form.sales_agent_id" :class="inputClass('sales_agent_id')" @change="fieldErrors.sales_agent_id = null">
                  <option :value="null">Select Sales Agent</option>
                  <option v-for="u in filteredSalesAgents" :key="u.id" :value="Number(u.id)">{{ u.name }}</option>
                </select>
                <p v-if="fieldErrors.sales_agent_id" class="mt-1 text-sm text-red-600">{{ fieldErrors.sales_agent_id }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Resubmission Reason <span class="text-red-500">*</span></label>
            <textarea
              v-model="form.resubmission_reason"
              rows="2"
              placeholder="Enter reason for resubmission"
              :class="inputClass('resubmission_reason')"
              @input="fieldErrors.resubmission_reason = null"
            />
            <p v-if="fieldErrors.resubmission_reason" class="mt-1 text-sm text-red-600">{{ fieldErrors.resubmission_reason }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Comment / Remarks</label>
            <textarea
              v-model="form.remarks"
              rows="2"
              placeholder="Enter any additional comments or remarks"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            />
          </div>
        </div>

        <div>
          <button
            type="button"
            class="w-full flex items-center justify-between text-left text-base font-semibold text-gray-900 border-b border-gray-200 pb-2"
            :aria-expanded="(!documentsSectionCollapsed).toString()"
            @click.prevent="documentsSectionCollapsed = !documentsSectionCollapsed"
          >
            <span>Documents</span>
            <div class="flex items-center gap-2 text-sm text-gray-500">
              <span>{{ documentsSectionCollapsed ? 'Show' : 'Minimize' }}</span>
              <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': documentsSectionCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>
          </button>

          <div v-show="!documentsSectionCollapsed" class="mt-4 space-y-4">
            <p class="text-sm text-gray-600">Upload required and optional documents below.</p>
            <p class="text-xs text-gray-500">Allowed: .pdf, .doc, .docx, .eml</p>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
              <div
                v-for="doc in docDefs"
                :key="doc.key"
                class="flex min-w-0 items-center gap-3 rounded-lg border border-gray-300 bg-white px-3 py-2 shadow-sm"
              >
                <div class="shrink-0 text-gray-500">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium text-gray-900">{{ doc.label }} <span v-if="doc.key === 'trade_license'" class="text-red-500">*</span></p>
                  <template v-for="(f, i) in (docFiles[doc.key] || [])" :key="'new-' + i">
                    <p class="mt-0.5 truncate text-xs text-gray-600" :title="f.name">{{ f.name }}</p>
                  </template>
                  <template v-for="d in existingDocsFor(doc.key)" :key="d.id">
                    <p class="mt-0.5 truncate text-xs text-gray-600" :title="d.original_name">{{ d.original_name || 'Document' }}</p>
                  </template>
                </div>
                <label class="shrink-0 cursor-pointer">
                  <input
                    type="file"
                    class="hidden"
                    accept=".pdf,.doc,.docx,.eml"
                    multiple
                    @change="onFileChange(doc.key, $event)"
                  />
                  <span class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-2 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-green-700">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Upload
                  </span>
                </label>
              </div>
            </div>

            <div v-if="existingAdditionalDocs.length" class="space-y-1.5">
              <h4 class="text-sm font-semibold text-gray-900">Uploaded Additional Documents</h4>
              <div
                v-for="doc in existingAdditionalDocs"
                :key="doc.id"
                class="flex items-center gap-2 rounded-md bg-green-50 border border-green-200 px-3 py-2"
              >
                <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm text-gray-700 truncate min-w-0 flex-1" :title="doc.original_name">{{ doc.original_name || 'Document' }}</span>
                <a href="#" class="text-sm text-green-700 hover:underline shrink-0" @click.prevent="downloadDoc(doc)">Download</a>
              </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <h4 class="text-sm font-semibold text-gray-900">Additional Documents</h4>
                <button
                  type="button"
                  class="inline-flex items-center gap-1.5 text-sm font-medium text-green-600 hover:text-green-700"
                  @click="addDocumentSlot"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  Add Document
                </button>
              </div>

              <div class="mt-4 space-y-3">
                <div
                  v-for="slot in addDocumentSlots"
                  :key="slot.id"
                  class="rounded-lg border border-gray-200 bg-white p-4"
                >
                  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="min-w-0 flex-1">
                      <p class="text-sm font-medium text-gray-900">Additional Document</p>
                      <p class="text-xs text-gray-500">PDF, DOC, DOCX, EML</p>
                    </div>
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-green-500 bg-white px-3 py-2 text-sm font-medium text-green-700 hover:bg-green-50">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                      </svg>
                      Upload
                      <input type="file" class="hidden" accept=".pdf,.doc,.docx,.eml" multiple @change="onAdditionalFileSelect(slot.id, $event)" />
                    </label>
                  </div>
                  <div v-if="getSlotFiles(slot.id).length" class="mt-3 space-y-1.5">
                    <div
                      v-for="(file, fi) in getSlotFiles(slot.id)"
                      :key="fi"
                      class="flex items-center gap-2 rounded-md bg-gray-50 px-3 py-1.5"
                    >
                      <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      <span class="text-sm text-gray-700 truncate min-w-0 flex-1" :title="file.name">{{ file.name }}</span>
                      <button
                        type="button"
                        class="shrink-0 rounded p-0.5 text-red-500 hover:bg-red-50 hover:text-red-700"
                        title="Remove"
                        @click="removeAdditionalFile(slot.id, fi)"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          </div>

        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ error }}
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4">
          <div class="flex items-center gap-3">
            <button
              type="button"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              @click="cancel"
            >
              Cancel
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              :disabled="saving"
              @click="saveDraft"
            >
              Save as Draft
            </button>
          </div>
          <button
            type="submit"
            class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-green-700 disabled:opacity-50"
            :disabled="saving || !canSubmit"
          >
            <span>{{ saving ? 'Submitting...' : 'Submit' }}</span>
            <svg v-if="!saving" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M13 6l6 6-6 6" />
            </svg>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
