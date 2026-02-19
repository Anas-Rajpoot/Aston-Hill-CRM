<script setup>
/**
 * Lead Resubmission Form – for rejected leads only (super admin or creator).
 * Matches design: Primary Info, Service Category cards, Contact, Resubmission Reason, Documents, Cancel / Save as Draft / Next.
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import leadSubmissionsApi from '@/services/leadSubmissionsApi'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const route = useRoute()
const router = useRouter()
const leadId = computed(() => {
  const id = route.params.id
  return id != null ? Number(id) : null
})

const loading = ref(true)
const saving = ref(false)
const error = ref(null)
const lead = ref(null)
const categories = ref([])
const docDefs = ref([])
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
const docFiles = ref({
  trade_license: [],
  establishment_card: [],
  owner_emirates_id: [],
  vat_certificate: [],
})
const addDocumentSlots = ref([{ id: 0 }])
const additionalFiles = ref({})
const uploadingDocs = ref(false)

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
    docDefs.value = data.resubmission_documents ?? []
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

function onPhoneInput(field, event) {
  form.value[field] = event.target.value.replace(/\D/g, '')
  fieldErrors.value[field] = null
}

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
  if (!form.value.team_leader_id) errs.team_leader_id = 'Team Leader Name is required.'
  if (!form.value.sales_agent_id) errs.sales_agent_id = 'Sales Agent Name is required.'
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
    await leadSubmissionsApi.resubmit(id, payload, Object.keys(files).length ? files : null)
    if (action === 'submit') {
      router.push(`/lead-submissions/${id}`)
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
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] p-0">
    <div class="w-full">
      <!-- Heading section: background and border, Back button on right (same as detail/edit) -->
      <div class="mb-4 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="flex flex-wrap items-baseline gap-2">
            <h1 class="text-xl font-semibold text-gray-900">Lead Resubmission Form</h1>
            <Breadcrumbs />
          </div>
          <router-link
            to="/lead-submissions"
            class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Back to List
          </router-link>
        </div>
      </div>

      <div v-if="loading" class="flex justify-center py-8">
        <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <div v-else-if="error && !lead" class="rounded-lg border border-red-200 bg-red-50 p-6 text-red-700">
        {{ error }}
        <div class="mt-3">
          <button type="button" class="text-sm font-medium underline" @click="loadData">Try again</button>
          <span class="mx-2">|</span>
          <button type="button" class="text-sm font-medium underline" @click="cancel">Back to list</button>
        </div>
      </div>

      <form v-else class="space-y-4" @submit.prevent="submitResubmission">
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm px-6 pb-6 pt-3 sm:px-8 sm:pb-8 sm:pt-4">
          <!-- Primary Information -->
          <div>
            <h3 class="text-base font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Primary Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
                <input v-model="form.account_number" type="text" placeholder="Enter account number" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="fieldErrors.account_number ? 'border-red-500' : 'border-gray-300'" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Company Name as per Trade License <span class="text-red-500">*</span></label>
                <input v-model="form.company_name" type="text" placeholder="Enter company name" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="fieldErrors.company_name ? 'border-red-500' : 'border-gray-300'" @input="fieldErrors.company_name = null" />
                <p v-if="fieldErrors.company_name" class="mt-1 text-sm text-red-600">{{ fieldErrors.company_name }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Authorized Signatory Name</label>
                <input v-model="form.authorized_signatory_name" type="text" placeholder="Enter authorized signatory name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Product <span class="text-red-500">*</span></label>
                <input v-model="form.product" type="text" placeholder="Enter product" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="fieldErrors.product ? 'border-red-500' : 'border-gray-300'" @input="fieldErrors.product = null" />
                <p v-if="fieldErrors.product" class="mt-1 text-sm text-red-600">{{ fieldErrors.product }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number <span class="text-red-500">*</span></label>
                <input v-model="form.contact_number_gsm" type="text" maxlength="12" placeholder="971XXXXXXXXX" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="fieldErrors.contact_number_gsm ? 'border-red-500' : 'border-gray-300'" @input="onPhoneInput('contact_number_gsm', $event)" />
                <p v-if="fieldErrors.contact_number_gsm" class="mt-1 text-sm text-red-600">{{ fieldErrors.contact_number_gsm }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Alternate Contact Number</label>
                <input v-model="form.alternate_contact_number" type="text" maxlength="12" placeholder="971XXXXXXXXX" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="fieldErrors.alternate_contact_number ? 'border-red-500' : 'border-gray-300'" @input="onPhoneInput('alternate_contact_number', $event)" />
                <p v-if="fieldErrors.alternate_contact_number" class="mt-1 text-sm text-red-600">{{ fieldErrors.alternate_contact_number }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email ID</label>
                <input v-model="form.email" type="email" placeholder="Enter email" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="fieldErrors.email ? 'border-red-500' : 'border-gray-300'" @input="fieldErrors.email = null" />
                <p v-if="fieldErrors.email" class="mt-1 text-sm text-red-600">{{ fieldErrors.email }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Complete Address as per Ejari <span class="text-red-500">*</span></label>
                <input v-model="form.address" type="text" placeholder="Enter complete address" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="fieldErrors.address ? 'border-red-500' : 'border-gray-300'" @input="fieldErrors.address = null" />
                <p v-if="fieldErrors.address" class="mt-1 text-sm text-red-600">{{ fieldErrors.address }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Emirates <span class="text-red-500">*</span></label>
                <input v-model="form.emirate" type="text" placeholder="Enter emirates" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="fieldErrors.emirate ? 'border-red-500' : 'border-gray-300'" @input="fieldErrors.emirate = null" />
                <p v-if="fieldErrors.emirate" class="mt-1 text-sm text-red-600">{{ fieldErrors.emirate }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Location Coordinates</label>
                <input v-model="form.location_coordinates" type="text" placeholder="e.g. 25.2048, 55.2708" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="fieldErrors.location_coordinates ? 'border-red-500' : 'border-gray-300'" @input="fieldErrors.location_coordinates = null" />
                <p v-if="fieldErrors.location_coordinates" class="mt-1 text-sm text-red-600">{{ fieldErrors.location_coordinates }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Offer</label>
                <input v-model="form.offer" type="text" placeholder="Enter offer" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">MRC (AED)</label>
                <input v-model="form.mrc_aed" type="text" placeholder="Enter MRC" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                <input v-model="form.quantity" type="text" placeholder="Enter quantity" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">.ae Domain</label>
                <input v-model="form.ae_domain" type="text" placeholder="Enter Domain (e.g. example.ae)" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="fieldErrors.ae_domain ? 'border-red-500' : 'border-gray-300'" @input="fieldErrors.ae_domain = null" />
                <p v-if="fieldErrors.ae_domain" class="mt-1 text-sm text-red-600">{{ fieldErrors.ae_domain }}</p>
                <p v-else-if="form.ae_domain?.trim() && aeDomainValidation.valid && aeDomainValidation.message" class="mt-1 text-sm text-green-600">{{ aeDomainValidation.message }}</p>
                <p v-else-if="form.ae_domain?.trim() && !aeDomainValidation.valid" class="mt-1 text-sm text-red-600">{{ aeDomainValidation.message }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">GAID</label>
                <input v-model="form.gaid" type="text" placeholder="Enter GAID" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
              </div>
            </div>
          </div>

          <!-- Team Information -->
          <div class="mt-4">
            <h3 class="text-base font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Team Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Manager Name <span class="text-red-500">*</span></label>
                <select v-model="form.manager_id" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="fieldErrors.manager_id ? 'border-red-500' : 'border-gray-300'" @change="fieldErrors.manager_id = null">
                  <option :value="null">Select Manager</option>
                  <option v-for="u in managers" :key="u.id" :value="Number(u.id)">{{ u.name }}</option>
                </select>
                <p v-if="fieldErrors.manager_id" class="mt-1 text-sm text-red-600">{{ fieldErrors.manager_id }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Team Leader Name <span class="text-red-500">*</span></label>
                <select v-model="form.team_leader_id" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="fieldErrors.team_leader_id ? 'border-red-500' : 'border-gray-300'" @change="fieldErrors.team_leader_id = null">
                  <option :value="null">Select Team Leader</option>
                  <option v-for="u in teamLeaders" :key="u.id" :value="Number(u.id)">{{ u.name }}</option>
                </select>
                <p v-if="fieldErrors.team_leader_id" class="mt-1 text-sm text-red-600">{{ fieldErrors.team_leader_id }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sales Agent Name <span class="text-red-500">*</span></label>
                <select v-model="form.sales_agent_id" class="w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="fieldErrors.sales_agent_id ? 'border-red-500' : 'border-gray-300'" @change="fieldErrors.sales_agent_id = null">
                  <option :value="null">Select Sales Agent</option>
                  <option v-for="u in salesAgents" :key="u.id" :value="Number(u.id)">{{ u.name }}</option>
                </select>
                <p v-if="fieldErrors.sales_agent_id" class="mt-1 text-sm text-red-600">{{ fieldErrors.sales_agent_id }}</p>
              </div>
            </div>
          </div>

          <!-- Comment / Remarks -->
          <div class="mt-8">
            <label class="block text-sm font-medium text-gray-700 mb-1">Comment / Remarks</label>
            <textarea
              v-model="form.remarks"
              rows="4"
              placeholder="Enter any additional comments or remarks"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            />
          </div>

          <!-- Service Category -->
          <div class="mt-8">
            <h3 class="text-base font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Service Category</h3>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
              <button
                v-for="cat in categories"
                :key="cat.id"
                type="button"
                class="flex flex-col items-center rounded-lg border-2 p-4 text-left transition"
                :class="form.service_category_id === cat.id ? 'border-green-600 bg-green-50' : 'border-gray-200 bg-white hover:border-gray-300'"
                @click="onCategorySelect(cat)"
              >
                <svg class="h-8 w-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="categoryIcon(cat.slug)" />
                </svg>
                <span class="mt-2 text-sm font-medium text-gray-900">{{ cat.name }}</span>
                <span v-if="cat.description" class="mt-0.5 text-xs text-gray-500">{{ cat.description }}</span>
              </button>
            </div>
            <div v-if="types.length" class="mt-3">
              <label class="block text-sm font-medium text-gray-700 mb-1">Service Type</label>
              <select
                v-model="form.service_type_id"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              >
                <option :value="null">Select type</option>
                <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
          </div>

          <!-- Resubmission Reason -->
          <div class="mt-8">
            <h3 class="text-base font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Resubmission Reason</h3>
            <textarea
              v-model="form.resubmission_reason"
              rows="4"
              placeholder="Enter reason for resubmission"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            />
          </div>

          <!-- Document Upload -->
          <div class="mt-8">
            <h3 class="text-base font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Documents</h3>
            <p class="mt-1 text-xs text-gray-500">PDF, DOC, DOCX, EML. Upload required and optional documents below.</p>
            <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
              <div
                v-for="doc in docDefs"
                :key="doc.key"
                class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm flex flex-col gap-3"
              >
                <div class="flex flex-row items-center justify-between gap-3">
                  <div class="flex items-start gap-3 min-w-0 flex-1">
                    <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                      <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                      <p class="font-semibold text-gray-900 text-sm">{{ doc.label }}</p>
                      <div v-if="docFiles[doc.key]?.length || existingDocsFor(doc.key).length" class="mt-1 flex flex-col gap-0.5">
                        <template v-for="(f, i) in (docFiles[doc.key] || [])" :key="'new-' + i">
                          <span class="text-xs text-gray-600 truncate max-w-full" :title="f.name">{{ f.name }}</span>
                        </template>
                        <template v-for="d in existingDocsFor(doc.key)" :key="d.id">
                          <span class="text-xs text-gray-600 truncate max-w-full" :title="d.original_name">{{ d.original_name || 'Document' }}</span>
                        </template>
                      </div>
                    </div>
                  </div>
                  <div class="flex items-center gap-2 shrink-0">
                    <label class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-sky-400 bg-white text-sky-600 text-sm font-medium cursor-pointer hover:bg-sky-50 shrink-0">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                      </svg>
                      Upload
                      <input
                        type="file"
                        class="hidden"
                        accept=".pdf,.doc,.docx,.eml"
                        multiple
                        @change="onFileChange(doc.key, $event)"
                      />
                    </label>
                  </div>
                </div>
                <div v-if="(docFiles[doc.key]?.length || 0) + existingDocsFor(doc.key).length > 0" class="border-t border-gray-100 pt-2 space-y-1">
                  <template v-for="(f, i) in (docFiles[doc.key] || [])" :key="'new-' + i">
                    <div class="flex items-center gap-2 text-xs text-gray-600">
                      <span class="truncate min-w-0 flex-1" :title="f.name">{{ f.name }}</span>
                      <button type="button" class="text-red-600 hover:text-red-700 font-medium shrink-0" @click="removeDocFile(doc.key, i)">Remove</button>
                    </div>
                  </template>
                  <template v-for="d in existingDocsFor(doc.key)" :key="d.id">
                    <div class="flex items-center gap-2 text-xs text-gray-600">
                      <span class="truncate min-w-0 flex-1" :title="d.original_name">{{ d.original_name || 'Document' }}</span>
                      <a href="#" class="text-blue-600 hover:underline shrink-0" @click.prevent="downloadDoc(d)">Download</a>
                    </div>
                  </template>
                </div>
              </div>
            </div>

            <!-- Existing Additional Documents -->
            <div v-if="existingAdditionalDocs.length" class="mt-4">
              <h4 class="text-sm font-semibold text-gray-900 mb-2">Uploaded Additional Documents</h4>
              <div class="space-y-1.5">
                <div
                  v-for="doc in existingAdditionalDocs"
                  :key="doc.id"
                  class="flex items-center gap-2 rounded-md bg-green-50 border border-green-200 px-3 py-2"
                >
                  <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span class="text-sm text-gray-700 truncate min-w-0 flex-1" :title="doc.original_name">{{ doc.original_name || 'Document' }}</span>
                  <a href="#" class="text-sm text-blue-600 hover:underline shrink-0" @click.prevent="downloadDoc(doc)">Download</a>
                </div>
              </div>
            </div>

            <!-- Add Document -->
            <div class="mt-4 border-t border-gray-200 pt-4">
              <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-base font-semibold text-gray-900">Add Document</h3>
                <button
                  type="button"
                  class="inline-flex items-center gap-2 text-sm font-medium text-green-600 hover:text-green-700"
                  @click="addDocumentSlot"
                >
                  <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  Add Document
                </button>
              </div>
              <div class="space-y-3">
                <div
                  v-for="slot in addDocumentSlots"
                  :key="slot.id"
                  class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
                  :class="getSlotFiles(slot.id).length ? 'border-green-300' : ''"
                >
                  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-start gap-3 min-w-0 flex-1">
                      <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" :class="getSlotFiles(slot.id).length ? 'bg-green-100' : 'bg-gray-100'">
                        <svg class="w-5 h-5" :class="getSlotFiles(slot.id).length ? 'text-green-600' : 'text-gray-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                      </div>
                      <div class="min-w-0 flex-1">
                        <p class="font-semibold text-gray-900 text-sm">Additional Document</p>
                        <p class="mt-0.5 text-xs text-gray-500">PDF, DOC, DOCX, EML. You can select multiple files.</p>
                      </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                      <label class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-sky-400 bg-white text-sky-600 text-sm font-medium cursor-pointer hover:bg-sky-50 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        Upload
                        <input type="file" class="hidden" accept=".pdf,.doc,.docx,.eml" multiple @change="onAdditionalFileSelect(slot.id, $event)" />
                      </label>
                    </div>
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

        <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-gray-200 bg-white rounded-lg px-6 pb-4">
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
            class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-6 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-green-700 disabled:opacity-50"
            :disabled="saving || !canSubmit"
          >
            Resubmit Lead Submission
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
