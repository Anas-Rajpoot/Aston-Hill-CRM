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
  service_category_id: null,
  service_type_id: null,
  contact_number_gsm: '',
  alternate_contact_number: '',
  address: '',
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
        service_category_id: lead.value.service_category_id ?? null,
        service_type_id: lead.value.service_type_id ?? null,
        contact_number_gsm: lead.value.contact_number_gsm ?? '',
        alternate_contact_number: lead.value.alternate_contact_number ?? '',
        address: lead.value.address ?? '',
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
      service_category_id: form.value.service_category_id || null,
      service_type_id: form.value.service_type_id || null,
      contact_number_gsm: form.value.contact_number_gsm || null,
      alternate_contact_number: form.value.alternate_contact_number || null,
      address: form.value.address || null,
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

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] py-3 px-3 sm:px-4">
    <div class="mx-auto max-w-5xl">
      <div class="mb-4 flex items-center gap-3">
        <button
          type="button"
          class="rounded p-2 text-gray-600 hover:bg-gray-200 hover:text-gray-900"
          aria-label="Back"
          @click="cancel"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
        </button>
        <h1 class="text-xl font-semibold text-gray-900">Resubmission Form</h1>
      </div>
      <Breadcrumbs />

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
                  <label class="block text-xs font-medium text-gray-700">Contact Number (GSM) <span class="text-red-500">*</span></label>
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

          <!-- Document Upload (4 cards: label + Upload in one row, matching image) -->
          <div class="px-5 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Documents</h2>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div
                v-for="doc in docDefs"
                :key="doc.key"
                class="flex flex-col rounded-lg border border-gray-200 bg-gray-50 p-4"
              >
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="text-sm font-medium text-gray-900">{{ doc.label }}</span>
                  </div>
                  <label class="inline-flex cursor-pointer items-center gap-1 rounded bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm border border-gray-300 hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                <ul v-if="docFiles[doc.key]?.length" class="mt-2 space-y-1 text-xs text-gray-600">
                  <li v-for="(f, i) in docFiles[doc.key]" :key="'new-' + i" class="flex items-center gap-2">
                    {{ f.name }}
                    <button type="button" class="text-red-600 hover:underline" @click="removeDocFile(doc.key, i)">Remove</button>
                  </li>
                </ul>
                <ul v-if="existingDocsFor(doc.key).length" class="mt-2 space-y-1 text-xs text-gray-600">
                  <li v-for="d in existingDocsFor(doc.key)" :key="d.id" class="flex items-center gap-2">
                    {{ d.original_name || 'Document' }}
                    <a
                      href="#"
                      class="text-blue-600 hover:underline"
                      @click.prevent="downloadDoc(d)"
                    >Download</a>
                  </li>
                </ul>
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
