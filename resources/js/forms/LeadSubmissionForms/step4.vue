<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/leadSubmissionsApi'
import { useFormErrors } from '@/composables/useFormErrors'
import { toDdMmYyyy } from '@/lib/dateFormat'

const props = defineProps({
  leadId: { type: Number, required: true },
})

const emit = defineEmits(['back', 'submitted'])

const lead = ref(null)
const loading = ref(true)
const submitting = ref(false)

const { errors, generalMessage, setErrors, clearErrors, getError } = useFormErrors()

const documentsByKey = computed(() => {
  const list = lead.value?.documents || []
  const byKey = {}
  list.forEach((doc) => {
    if (!byKey[doc.doc_key]) byKey[doc.doc_key] = []
    byKey[doc.doc_key].push(doc)
  })
  return byKey
})

const totalDocCount = computed(() => lead.value?.documents?.length ?? 0)

/** True when the lead has no primary data (empty/incomplete draft). */
const isEmptyDraft = computed(() => {
  const l = lead.value
  if (!l) return false
  const hasPrimary = (l.company_name && l.company_name.trim()) || (l.contact_number_gsm && l.contact_number_gsm.trim())
  const hasService = (l.category_name && l.category_name.trim()) || (l.type_name && l.type_name.trim()) || l.service_category_id || l.service_type_id
  return !hasPrimary && !hasService
})

function formatSize(bytes) {
  if (bytes == null || bytes === 0) return '—'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  const str = typeof dateStr === 'string' ? dateStr.trim().slice(0, 10) : ''
  if (!str) return '—'
  return toDdMmYyyy(str) || '—'
}

function displayValue(val) {
  if (val == null) return '—'
  const s = String(val).trim()
  return s || '—'
}

function displayNameOrId(nameVal, idVal) {
  const name = displayValue(nameVal)
  if (name !== '—') return name
  if (idVal != null && String(idVal).trim() !== '') return `ID: ${idVal}`
  return '—'
}

/** Truncate string to maxLen characters and append "..." if longer. */
function truncate(val, maxLen = 80) {
  if (val == null || val === '') return ''
  const s = String(val).trim()
  if (s.length <= maxLen) return s
  return s.slice(0, maxLen) + '...'
}

onMounted(async () => {
  loading.value = true
  try {
    const res = await api.getLead(props.leadId)
    lead.value = res?.data ?? null
  } catch (e) {
    setErrors(e)
  } finally {
    loading.value = false
  }
})

async function submit() {
  clearErrors()
  submitting.value = true
  try {
    await api.submit(props.leadId)
    emit('submitted')
  } catch (e) {
    setErrors(e)
  } finally {
    submitting.value = false
  }
}

function goBack() {
  emit('back')
}

function close() {
  window.history.back()
}
</script>

<template>
  <div class="space-y-6">
    <div
      v-if="generalMessage || Object.keys(errors).length"
      class="rounded-lg border border-red-200 bg-red-50 p-4"
    >
      <p class="text-sm font-medium text-red-800">{{ generalMessage }}</p>
      <ul class="mt-2 list-inside list-disc space-y-0.5 text-sm text-red-700">
        <li v-for="(msgs, field) in errors" :key="field">{{ getError(field) }}</li>
      </ul>
    </div>

    <div v-if="loading" class="flex justify-center items-center py-12">
      <svg class="animate-spin h-8 w-8 text-brand-primary" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
      </svg>
      <span class="ml-3 text-gray-600">Loading...</span>
    </div>

    <template v-else-if="lead">
      <!-- Empty draft: no step 1/2 data -->
      <div
        v-if="isEmptyDraft"
        class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800"
      >
        <p class="text-sm font-medium">This draft has no details yet.</p>
        <p class="mt-1 text-sm">Please go back to Step 1 to complete the form, then continue through the steps.</p>
      </div>

      <!-- Page Title -->
      <div class="border-b border-gray-200 pb-2">
        <h2 class="text-xl font-bold text-gray-900">Review Lead Submission</h2>
        <p class="mt-1 text-sm text-gray-500">Review details and documents before final submission.</p>
      </div>

      <!-- Part 1: Primary Information -->
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h3 class="mb-3 text-base font-semibold text-gray-800">Primary Information</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <p class="text-xs text-gray-500">Account Number</p>
            <p class="text-sm font-medium text-gray-900">{{ displayValue(lead.account_number) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Company Name as per Trade License</p>
            <p class="text-sm font-medium text-gray-900">{{ displayValue(lead.company_name) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Authorized Signatory Name</p>
            <p class="text-sm font-medium text-gray-900">{{ displayValue(lead.authorized_signatory_name) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Contact Number</p>
            <p class="text-sm font-medium text-gray-900">{{ displayValue(lead.contact_number_gsm) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Alternate Contact Number</p>
            <p class="text-sm font-medium text-gray-900">{{ displayValue(lead.alternate_contact_number) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Email ID</p>
            <p class="text-sm font-medium text-gray-900">{{ displayValue(lead.email) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Complete Address as per Ejari</p>
            <p class="text-sm font-medium text-gray-900">{{ displayValue(lead.address) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Emirates</p>
            <p class="text-sm font-medium text-gray-900">{{ displayValue(lead.emirate) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Location Coordinates</p>
            <p class="text-sm font-medium text-gray-900">{{ displayValue(lead.location_coordinates) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Product</p>
            <p class="text-sm font-medium text-gray-900">{{ displayValue(lead.product) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Offer</p>
            <p class="text-sm font-medium text-gray-900">{{ displayValue(lead.offer) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">MRC (AED)</p>
            <p class="text-sm font-medium text-gray-900">{{ displayValue(lead.mrc_aed) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Quantity</p>
            <p class="text-sm font-medium text-gray-900">{{ displayValue(lead.quantity) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">.ae Domain</p>
            <p class="text-sm font-medium text-gray-900">{{ displayValue(lead.ae_domain) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">GAID</p>
            <p class="text-sm font-medium text-gray-900">{{ displayValue(lead.gaid) }}</p>
          </div>
        </div>
      </div>

      <!-- Part 2: Team Information -->
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h3 class="mb-3 text-base font-semibold text-gray-800">Team Information</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <p class="text-xs text-gray-500">Manager Name</p>
            <p class="text-sm font-medium text-gray-900">{{ displayNameOrId(lead.manager_name, lead.manager_id) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Team Leader Name</p>
            <p class="text-sm font-medium text-gray-900">{{ displayNameOrId(lead.team_leader_name, lead.team_leader_id) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Sales Agent Name</p>
            <p class="text-sm font-medium text-gray-900">{{ displayNameOrId(lead.sales_agent_name, lead.sales_agent_id) }}</p>
          </div>
          <div class="sm:col-span-2 lg:col-span-3">
            <p class="text-xs text-gray-500">Comment / Remarks</p>
            <p class="mt-0.5 whitespace-pre-wrap text-sm font-medium text-gray-900">{{ displayValue(lead.remarks) }}</p>
          </div>
        </div>
      </div>

      <!-- Part 3: Service Category & Service Type -->
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h3 class="mb-3 text-base font-semibold text-gray-800">Service Category & Service Type</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <p class="text-xs text-gray-500">Service Category</p>
            <p class="text-sm font-medium text-gray-900">{{ displayNameOrId(lead.category_name || lead.category?.name, lead.service_category_id) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Service Type</p>
            <p class="text-sm font-medium text-gray-900">{{ displayNameOrId(lead.type_name || lead.type?.name, lead.service_type_id) }}</p>
          </div>
        </div>
      </div>

      <!-- Part 4: Documents -->
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h3 class="mb-3 text-base font-semibold text-gray-800">
          Documents ({{ totalDocCount }} {{ totalDocCount === 1 ? 'file' : 'files' }})
        </h3>
        <div v-if="totalDocCount === 0" class="text-sm text-gray-500">No documents uploaded.</div>
        <div v-else class="space-y-3">
          <div
            v-for="(docs, key) in documentsByKey"
            :key="key"
            class="rounded border border-gray-200 bg-gray-50 p-3"
          >
            <p class="text-sm font-medium text-gray-800">
              {{ truncate(docs[0]?.label || key) || key }} ({{ docs.length }} {{ docs.length === 1 ? 'file' : 'files' }})
            </p>
            <ul class="mt-2 space-y-1 pl-4 text-sm text-gray-600">
              <li v-for="doc in docs" :key="doc.id">
                {{ truncate(doc.original_name) || 'File' }}
                <span v-if="doc.size != null" class="text-gray-500">({{ formatSize(doc.size) }})</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex flex-wrap items-center justify-between gap-3 border border-gray-200 rounded-lg bg-white p-4">
        <div class="flex items-center gap-3">
          <button
            type="button"
            @click="goBack"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-brand-primary-hover"
          >
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back
          </button>
        </div>
        <div class="flex items-center gap-3">
          <button
            type="button"
            @click="close"
            class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="submit"
            :disabled="submitting"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-brand-primary-hover disabled:opacity-50"
          >
            <span class="text-white">{{ submitting ? 'Submitting...' : 'Submit' }}</span>
            <svg v-if="!submitting" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M13 6l6 6-6 6" />
            </svg>
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
