<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/leadSubmissionsApi'
import { useFormErrors } from '@/composables/useFormErrors'

const props = defineProps({
  leadId: { type: Number, required: true },
})

const emit = defineEmits(['back', 'submitted'])

const docDefs = ref([])
const existingDocs = ref([])
const files = ref({}) // { key: File[] }
const loading = ref(true)
const saving = ref(false)
const submitting = ref(false)
const additionalDocs = ref([]) // [{ key, label, files: File[] }]

const { errors, generalMessage, setErrors, clearErrors, clearFieldError, getError } = useFormErrors()

const ALLOWED_TYPES = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'message/rfc822']
const ALLOWED_EXT = ['.pdf', '.doc', '.docx', '.eml']
const MAX_FILE_MB = 3
const MAX_TOTAL_MB = 10

/** Default document types (matches backend LeadSubmissionSchema) – always show 12 cards */
const DEFAULT_DOCUMENTS = [
  { key: 'trade_license', label: 'Trade License', required: true },
  { key: 'establishment_card', label: 'Establishment Card', required: false },
  { key: 'owner_emirates_id', label: 'Owner Emirates ID', required: true },
  { key: 'loa_poa', label: 'LOA / POA', required: false },
  { key: 'ejari', label: 'Ejari', required: true },
  { key: 'proposal_form', label: 'Proposal Form', required: true },
  { key: 'main_application', label: 'Main Application', required: true },
  { key: 'customer_confirmation_email', label: 'Customer Confirmation Email', required: true },
  { key: 'as_person_eid', label: 'AS Person EID', required: false },
  { key: 'rfs_marketing_approvals', label: 'RFS / Marketing / Migration Approvals', required: false },
  { key: 'fnp_binder', label: 'FNP Binder', required: true },
  { key: 'etisatis_bill', label: 'Etisatis Bill', required: false },
]

const requiredCount = computed(() => docDefs.value.filter((d) => d.required).length)
const uploadedRequiredCount = computed(() => {
  const uploaded = new Set()
  docDefs.value.forEach((d) => {
    if (!d.required) return
    const hasExisting = existingDocs.value.some((ed) => ed.doc_key === d.key)
    const hasNew = files.value[d.key]?.length > 0
    if (hasExisting || hasNew) uploaded.add(d.key)
  })
  return uploaded.size
})
const totalSizeBytes = computed(() => {
  let total = existingDocs.value.reduce((s, d) => s + (d.size || 0), 0)
  Object.values(files.value).flat().forEach((f) => {
    if (f?.size) total += f.size
  })
  additionalDocs.value.forEach((ad) => {
    (ad.files || []).forEach((f) => {
      if (f?.size) total += f.size
    })
  })
  return total
})
const totalSizeMB = computed(() => (totalSizeBytes.value / (1024 * 1024)).toFixed(2))

const validateFile = (file, docKey) => {
  const ext = '.' + (file.name?.split('.').pop() || '').toLowerCase()
  if (!ALLOWED_EXT.includes(ext)) {
    return 'File must be PDF, DOC, DOCX, or EML.'
  }
  if (file.size > MAX_FILE_MB * 1024 * 1024) {
    return `File must not exceed ${MAX_FILE_MB}MB.`
  }
  return null
}

onMounted(async () => {
  loading.value = true
  try {
    const leadRes = await api.getLead(props.leadId)
    const lead = leadRes?.data
    existingDocs.value = lead?.documents || []
    const typeId = lead?.service_type_id
    if (typeId) {
      try {
        const schemaRes = await api.getTypeSchema(typeId)
        const schemaDocs = schemaRes?.data?.documents || []
        docDefs.value = schemaDocs.length > 0 ? schemaDocs : DEFAULT_DOCUMENTS
      } catch {
        docDefs.value = DEFAULT_DOCUMENTS
      }
    } else {
      docDefs.value = DEFAULT_DOCUMENTS
    }
    // Initialize files ref for each doc key
    docDefs.value.forEach((d) => {
      if (d.key && !(d.key in files.value)) files.value[d.key] = []
    })
  } catch (e) {
    setErrors(e)
  } finally {
    loading.value = false
  }
})

const onFileChange = (key, e) => {
  const selected = Array.from(e.target?.files || [])
  const validated = []
  const fileErrors = {}
  selected.forEach((f) => {
    const err = validateFile(f, key)
    if (err) fileErrors[f.name] = err
    else validated.push(f)
  })
  files.value[key] = validated
  clearFieldError(`documents.${key}`)
  clearFieldError('documents')
  if (Object.keys(fileErrors).length) {
    const msg = Object.entries(fileErrors)
      .map(([n, m]) => `${n}: ${m}`)
      .join('; ')
    setErrors({ response: { data: { errors: { [`documents.${key}`]: [msg] } } } })
  }
  e.target.value = ''
}

const removeFile = (key, idx) => {
  const arr = [...(files.value[key] || [])]
  arr.splice(idx, 1)
  files.value[key] = arr
  clearFieldError(`documents.${key}`)
  const doc = docDefs.value.find((d) => d.key === key)
  if (doc?.required) {
    const hasExisting = existingDocs.value.some((ed) => ed.doc_key === key)
    if (!hasExisting && arr.length === 0) {
      setErrors({ response: { data: { errors: { [`documents.${key}`]: ['This document is required.'] } } } })
    }
  }
}

const MAX_ADDITIONAL_DOCS = 3
const canAddMoreAdditional = computed(() => additionalDocs.value.length < MAX_ADDITIONAL_DOCS)

const addAdditionalDoc = () => {
  if (additionalDocs.value.length >= MAX_ADDITIONAL_DOCS) return
  additionalDocs.value.push({
    key: `additional_${Date.now()}`,
    label: `Additional Document ${additionalDocs.value.length + 1}`,
    files: [],
  })
}

const onAdditionalFileChange = (idx, e) => {
  const selected = Array.from(e.target?.files || [])
  const validated = []
  selected.forEach((f) => {
    const err = validateFile(f)
    if (!err) validated.push(f)
  })
  additionalDocs.value[idx].files = validated
  e.target.value = ''
}

const removeAdditionalDoc = (idx) => {
  additionalDocs.value.splice(idx, 1)
}

const getDocFiles = (key) => files.value[key] || []
const getExistingForKey = (key) => existingDocs.value.filter((d) => d.doc_key === key)

const buildFormData = () => {
  const fd = new FormData()
  Object.entries(files.value).forEach(([key, arr]) => {
    ;(arr || []).forEach((f) => fd.append(`documents[${key}][]`, f))
  })
  additionalDocs.value.forEach((ad) => {
    ;(ad.files || []).forEach((f) => fd.append(`documents[${ad.key}][]`, f))
    if (ad.key) fd.append(`document_labels[${ad.key}]`, (ad.label || '').trim())
  })
  return fd
}

const validateBeforeSubmit = () => {
  const err = {}
  if (totalSizeBytes.value > MAX_TOTAL_MB * 1024 * 1024) {
    err.documents = [`Total upload size must not exceed ${MAX_TOTAL_MB}MB.`]
  }
  docDefs.value
    .filter((d) => d.required)
    .forEach((d) => {
      const hasExisting = existingDocs.value.some((ed) => ed.doc_key === d.key)
      const hasNew = (files.value[d.key] || []).length > 0
      if (!hasExisting && !hasNew) {
        err[`documents.${d.key}`] = ['This document is required.']
      }
    })
  additionalDocs.value.forEach((ad) => {
    if ((ad.files || []).length > 0 && !(ad.label || '').trim()) {
      err[`additional_docs.${ad.key}`] = ['Title is required when a document is uploaded.']
    }
  })
  if (Object.keys(err).length) return err
  return null
}

const saveDraft = async () => {
  clearErrors()
  const draftErr = {}
  additionalDocs.value.forEach((ad) => {
    if ((ad.files || []).length > 0 && !(ad.label || '').trim()) {
      draftErr[`additional_docs.${ad.key}`] = ['Title is required when a document is uploaded.']
    }
  })
  if (Object.keys(draftErr).length) {
    errors.value = draftErr
    generalMessage.value = 'Please correct the errors below.'
    return
  }
  const fd = buildFormData()
  fd.append('action', 'save')
  saving.value = true
  try {
    await api.storeStep3(props.leadId, fd)
    const { data: lead } = await api.getLead(props.leadId)
    existingDocs.value = lead?.documents || []
    Object.keys(files.value).forEach((k) => (files.value[k] = []))
  } catch (e) {
    setErrors(e)
  } finally {
    saving.value = false
  }
}

const submit = async () => {
  clearErrors()
  const frontendErr = validateBeforeSubmit()
  if (frontendErr) {
    errors.value = frontendErr
    generalMessage.value = 'Please correct the errors below.'
    return
  }
  const fd = buildFormData()
  fd.append('action', 'submit')
  submitting.value = true
  try {
    await api.storeStep3(props.leadId, fd)
    emit('submitted')
  } catch (e) {
    setErrors(e)
  } finally {
    submitting.value = false
  }
}

const goBack = () => emit('back')
const cancel = () => window.history.back()
</script>

<template>
  <div v-if="loading" class="flex justify-center items-center py-12">
    <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
    </svg>
  </div>

  <div v-else class="space-y-6">
    <!-- Title & Instructions -->
    <div>
      <h2 class="text-xl font-bold text-gray-900">Document Upload</h2>
      <p class="mt-1 text-sm text-gray-500">
        Please upload all required documents. You can upload multiple files for each document type.
        PDF, DOC, EML (Max 3MB per file and all is under 10MB)
      </p>
    </div>

    <!-- Required documents status bar (light blue) -->
    <div class="rounded-lg bg-sky-50 border border-sky-200 p-4 flex items-center justify-center gap-2">
      <svg class="w-5 h-5 text-sky-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
      </svg>
      <p class="text-sm font-medium text-sky-800">
        {{ uploadedRequiredCount }} of {{ requiredCount }} required documents uploaded
      </p>
    </div>

    <!-- Validation errors summary -->
    <div v-if="generalMessage || Object.keys(errors).length" class="rounded-lg bg-red-50 border border-red-200 p-4">
      <p class="text-sm font-medium text-red-800">{{ generalMessage }}</p>
      <ul v-if="Object.keys(errors).length > 0" class="mt-2 text-sm text-red-700 list-disc list-inside space-y-0.5">
        <li v-for="(msgs, field) in errors" :key="field">{{ getError(field) }}</li>
      </ul>
    </div>

    <!-- Document cards (two-column grid) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div
        v-for="doc in docDefs"
        :key="doc.key"
        class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm flex items-center justify-between gap-3"
      >
        <div class="flex items-center gap-3 min-w-0 flex-1">
          <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <div class="min-w-0">
            <p class="font-semibold text-gray-900 text-sm">{{ doc.label }}</p>
            <p :class="doc.required ? 'text-red-600 text-xs font-medium' : 'text-gray-500 text-xs'">
              {{ doc.required ? 'Required' : 'Optional' }}
            </p>
            <p v-if="getError(`documents.${doc.key}`)" class="text-red-600 text-xs mt-1">{{ getError(`documents.${doc.key}`) }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
          <div v-if="getDocFiles(doc.key).length || getExistingForKey(doc.key).length" class="flex flex-col items-end gap-0.5 max-w-[180px] min-w-0">
            <template v-for="(f, idx) in getDocFiles(doc.key)" :key="'new-' + idx">
              <span class="text-xs text-gray-700 truncate max-w-full" :title="f.name">{{ f.name }}</span>
            </template>
            <template v-for="ed in getExistingForKey(doc.key)" :key="'ex-' + ed.id">
              <span class="text-xs text-gray-700 truncate max-w-full" :title="ed.original_name">{{ ed.original_name }}</span>
            </template>
          </div>
          <label class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-sky-400 bg-white text-sky-600 text-sm font-medium cursor-pointer hover:bg-sky-50 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            Upload
            <input type="file" class="hidden" accept=".pdf,.doc,.docx,.eml" multiple @change="(e) => onFileChange(doc.key, e)" />
          </label>
        </div>
      </div>
    </div>

    <!-- Additional Documents (up to 3; one new field per click) -->
    <div class="border-t border-gray-200 pt-6 mt-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-base font-semibold text-gray-900">Additional Documents</h3>
        <button
          v-if="canAddMoreAdditional"
          type="button"
          @click="addAdditionalDoc"
          class="inline-flex items-center gap-2 text-sm font-medium text-green-600 hover:text-green-700"
        >
          <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add Document
        </button>
        <span v-else class="text-sm text-gray-500">Maximum {{ MAX_ADDITIONAL_DOCS }} additional documents</span>
      </div>
      <div v-if="additionalDocs.length" class="space-y-3">
        <div
          v-for="(ad, idx) in additionalDocs"
          :key="ad.key"
          class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm flex flex-nowrap items-center gap-4"
        >
          <!-- Document icon -->
          <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <!-- Label input (required when file uploaded) -->
          <div class="min-w-0 flex-1 flex flex-col gap-0.5">
            <input v-model="ad.label" type="text" :placeholder="`Additional Document ${idx + 1}`" class="w-full rounded-lg border px-3 py-2 text-sm font-semibold text-gray-900" :class="getError(`additional_docs.${ad.key}`) ? 'border-red-500' : 'border-gray-300'" @input="clearFieldError(`additional_docs.${ad.key}`)" />
            <p v-if="getError(`additional_docs.${ad.key}`)" class="text-red-600 text-xs">{{ getError(`additional_docs.${ad.key}`) }}</p>
          </div>
          <!-- Upload + Remove on the right, same row -->
          <div class="flex items-center gap-2 shrink-0 flex-nowrap">
            <div v-if="ad.files?.length" class="w-6 h-6 rounded-full bg-blue-500 text-white text-xs font-bold flex items-center justify-center">
              {{ ad.files.length }}
            </div>
            <label class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-sky-400 bg-white text-sky-600 text-sm font-medium cursor-pointer hover:bg-sky-50 whitespace-nowrap">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              Upload
              <input type="file" class="hidden" accept=".pdf,.doc,.docx,.eml" multiple @change="(e) => onAdditionalFileChange(idx, e)" />
            </label>
            <button type="button" @click="removeAdditionalDoc(idx)" class="text-red-600 hover:text-red-700 text-sm font-medium whitespace-nowrap">Remove</button>
          </div>
        </div>
        <div v-if="canAddMoreAdditional" class="mt-3">
          <button
            type="button"
            @click="addAdditionalDoc"
            class="inline-flex items-center gap-2 text-sm font-medium text-green-600 hover:text-green-700"
          >
            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Document
          </button>
        </div>
      </div>
    </div>

    <!-- Size info -->
    <p class="text-sm text-gray-500">Total size: {{ totalSizeMB }} MB / {{ MAX_TOTAL_MB }} MB</p>

    <!-- Actions: 2 left (Back, Cancel), 2 right (Save as Draft, Submit Lead) -->
    <div class="flex flex-wrap items-center justify-between gap-3 pt-6 border-t border-gray-200">
      <div class="flex items-center gap-3">
        <button
          type="button"
          @click="goBack"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Back
        </button>
        <button
          type="button"
          @click="cancel"
          class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50"
        >
          Cancel
        </button>
      </div>
      <div class="flex items-center gap-3">
        <button
          type="button"
          :disabled="saving"
          @click="saveDraft"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50 disabled:opacity-50"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
          </svg>
          {{ saving ? 'Saving...' : 'Save as Draft' }}
        </button>
        <button
          type="button"
          :disabled="submitting"
          @click="submit"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-lime-500 text-white text-sm font-medium hover:bg-lime-600 disabled:opacity-50"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
          </svg>
          {{ submitting ? 'Submitting...' : 'Submit Lead' }}
        </button>
      </div>
    </div>
  </div>
</template>
