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
})
const docFiles = ref({
  trade_license: [],
  establishment_card: [],
  owner_emirates_id: [],
  vat_certificate: [],
})
/** Add Document slots (same as edit form: multiple cards, each with Upload). */
const addDocumentSlots = ref([{ id: 0 }])
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
    const res = await leadSubmissionsApi.getResubmissionData(id)
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

/** Trade License is required on Save (submit). Can be existing on lead or newly uploaded. */
const hasTradeLicense = computed(() => {
  if (lead.value?.documents?.some((d) => d.doc_key === 'trade_license')) return true
  return (docFiles.value.trade_license?.length ?? 0) > 0
})

async function saveDraft() {
  await submitForm('draft')
}

async function submitResubmission() {
  if (!canSubmit.value) return
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
    }
    const files = {}
    Object.entries(docFiles.value).forEach(([key, list]) => {
      if (list?.length) files[key] = list
    })
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
  addDocumentSlots.value = [...addDocumentSlots.value, { id: Date.now() }]
}

async function uploadFromInput(e) {
  const input = e?.target
  if (!input?.files?.length) return
  const id = leadId.value
  if (!id) return
  uploadingDocs.value = true
  try {
    const formData = new FormData()
    for (let i = 0; i < input.files.length; i++) {
      formData.append('documents[]', input.files[i])
    }
    await leadSubmissionsApi.uploadDocuments(id, formData)
    input.value = ''
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Upload failed.'
    alert(msg)
  } finally {
    uploadingDocs.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] py-3 px-3 sm:px-4">
    <div class="mx-auto max-w-5xl">
      <!-- Heading section: background and border, Back button on right (same as detail/edit) -->
      <div class="mb-4 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <h1 class="text-xl font-semibold text-gray-900">Resubmission Form</h1>
          <button
            type="button"
            class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            @click="cancel"
          >
            Back
          </button>
        </div>
        <div class="mt-3 border-t border-gray-100 pt-3">
          <Breadcrumbs />
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
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
          <!-- Request Type (read-only): saved as Resubmission on submit -->
          <div class="border-b border-gray-100 px-5 py-3">
            <div class="flex flex-wrap items-center gap-4">
              <div>
                <span class="text-xs font-medium text-gray-500">Request Type</span>
                <span class="ml-2 inline-flex rounded-full bg-purple-100 px-2.5 py-0.5 text-sm font-medium text-purple-800">Resubmission</span>
              </div>
              <p class="text-xs text-gray-500">All fields below show your original submission data. You can edit and resubmit. On save, status will be recorded as Resubmission.</p>
            </div>
          </div>

          <!-- Primary Information -->
          <div class="border-b border-gray-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Primary Information</h2>
            <p class="mt-0.5 text-xs text-gray-500">Please provide all basic, contact, and commercial information. Fields marked with * are required.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
              <div>
                <label class="block text-xs font-medium text-gray-700">Account Number</label>
                <input
                  v-model="form.account_number"
                  type="text"
                  class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                  placeholder="Enter account number"
                />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700">Company Name as per Trade License <span class="text-red-500">*</span></label>
                <input
                  v-model="form.company_name"
                  type="text"
                  class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                  placeholder="Enter company name"
                  required
                />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700">Authorized Signatory Name</label>
                <input
                  v-model="form.authorized_signatory_name"
                  type="text"
                  class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                  placeholder="Enter authorized signatory name"
                />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700">Email</label>
                <input
                  v-model="form.email"
                  type="email"
                  class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                  placeholder="Enter email"
                />
              </div>
            </div>
          </div>

          <!-- Team (read-only: original submission) -->
          <div class="border-b border-gray-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Team</h2>
            <p class="mt-0.5 text-xs text-gray-500">Manager, Team Leader and Sales Agent from your original submission (read-only).</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
              <div>
                <label class="block text-xs font-medium text-gray-500">Manager</label>
                <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ lead?.manager_name ?? '—' }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Team Leader</label>
                <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ lead?.team_leader_name ?? '—' }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Sales Agent</label>
                <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ lead?.sales_agent_name ?? '—' }}</div>
              </div>
            </div>
          </div>

          <!-- Service Category -->
          <div class="border-b border-gray-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Service Category</h2>
            <p class="mt-0.5 text-xs text-gray-500">Please select the service category for this resubmission.</p>
            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
              <button
                v-for="cat in categories"
                :key="cat.id"
                type="button"
                class="flex flex-col items-center rounded-lg border-2 p-4 text-left transition"
                :class="form.service_category_id === cat.id ? 'border-blue-600 bg-blue-50' : 'border-gray-200 bg-white hover:border-gray-300'"
                @click="onCategorySelect(cat)"
              >
                <svg class="h-8 w-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="categoryIcon(cat.slug)" />
                </svg>
                <span class="mt-2 text-sm font-medium text-gray-900">{{ cat.name }}</span>
                <span v-if="cat.description" class="mt-0.5 text-xs text-gray-500">{{ cat.description }}</span>
              </button>
            </div>
            <div v-if="types.length" class="mt-2">
              <label class="block text-xs font-medium text-gray-700">Service Type</label>
              <select
                v-model="form.service_type_id"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
              >
                <option :value="null">Select type</option>
                <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
          </div>

          <!-- Contact Information -->
          <div class="border-b border-gray-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Contact Information</h2>
            <div class="mt-3 space-y-3">
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <label class="block text-xs font-medium text-gray-700">Contact Number <span class="text-red-500">*</span></label>
                  <input
                    v-model="form.contact_number_gsm"
                    type="text"
                    class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    placeholder="+971 XX XXX XXXX"
                    required
                  />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-700">Alternate Contact Number</label>
                  <input
                    v-model="form.alternate_contact_number"
                    type="text"
                    class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    placeholder="+971 XX XXX XXXX"
                  />
                </div>
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <label class="block text-xs font-medium text-gray-700">Complete Address as per Ejari</label>
                  <input
                    v-model="form.address"
                    type="text"
                    class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    placeholder="Enter complete address"
                  />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-700">Emirate</label>
                  <input
                    v-model="form.emirate"
                    type="text"
                    class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    placeholder="Enter emirate"
                  />
                </div>
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <label class="block text-xs font-medium text-gray-700">Location Coordinates</label>
                  <input
                    v-model="form.location_coordinates"
                    type="text"
                    class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    placeholder="e.g. 12.2048, 55.2708"
                  />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-700">Previous Activity</label>
                  <input
                    v-model="form.previous_activity"
                    type="text"
                    class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    placeholder="Enter previous activity"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Commercial / Service Details -->
          <div class="border-b border-gray-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Commercial / Service Details</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <div>
                <label class="block text-xs font-medium text-gray-700">Product</label>
                <input
                  v-model="form.product"
                  type="text"
                  class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                  placeholder="Enter product"
                />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700">Offer</label>
                <input
                  v-model="form.offer"
                  type="text"
                  class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                  placeholder="Enter offer"
                />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700">MRC (AED)</label>
                <input
                  v-model="form.mrc_aed"
                  type="text"
                  class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                  placeholder="Enter MRC"
                />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700">Quantity</label>
                <input
                  v-model="form.quantity"
                  type="text"
                  class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                  placeholder="Enter quantity"
                />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700">AE Domain</label>
                <input
                  v-model="form.ae_domain"
                  type="text"
                  class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                  placeholder="Enter AE domain"
                />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700">GAID</label>
                <input
                  v-model="form.gaid"
                  type="text"
                  class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                  placeholder="Enter GAID"
                />
              </div>
            </div>
          </div>

          <!-- Resubmission Reason -->
          <div class="border-b border-gray-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Resubmission Reason</h2>
            <textarea
              v-model="form.resubmission_reason"
              rows="3"
              class="mt-2 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
              placeholder="Enter reason for resubmission"
            />
          </div>

          <!-- Additional Information -->
          <div class="border-b border-gray-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Additional Information</h2>
            <label class="block text-xs font-medium text-gray-700">Comment / Remarks</label>
            <textarea
              v-model="form.remarks"
              rows="3"
              class="mt-2 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
              placeholder="Enter any additional comments or remarks"
            />
          </div>

          <!-- Document Upload (same card design as wizard step3: white card, icon box, sky Upload button) -->
          <div class="px-5 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Documents</h2>
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

            <!-- Add Document (same as edit form: multiple slots, Add Document button) -->
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
                  class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                >
                  <div class="flex items-start gap-3 min-w-0 flex-1">
                    <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                      <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                      <p class="font-semibold text-gray-900 text-sm">Additional Document</p>
                      <p class="mt-0.5 text-xs text-gray-500">PDF, DOC, DOCX, EML. You can select multiple files.</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2 shrink-0">
                    <label class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-sky-400 bg-white text-sky-600 text-sm font-medium cursor-pointer hover:bg-sky-50 shrink-0 disabled:opacity-50" :class="{ 'opacity-50 pointer-events-none': uploadingDocs }">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                      </svg>
                      {{ uploadingDocs ? 'Uploading...' : 'Upload' }}
                      <input type="file" class="hidden" accept=".pdf,.doc,.docx,.eml" multiple @change="uploadFromInput" />
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ error }}
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3">
          <button
            type="button"
            class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            @click="cancel"
          >
            Cancel
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            :disabled="saving"
            @click="saveDraft"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            Save as Draft
          </button>
          <button
            type="submit"
            class="inline-flex items-center gap-2 rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
            :disabled="saving || !canSubmit"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            Resubmit Lead Submission
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
