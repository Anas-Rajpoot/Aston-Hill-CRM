<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '@/services/vasRequestsApi'
import { useFormErrors } from '@/composables/useFormErrors'

const props = defineProps({
  vasRequestId: { type: Number, required: true },
})

const emit = defineEmits(['back', 'draft-saved', 'next'])

const MAX_FILE_MB = 3
const MAX_TOTAL_MB = 10
const ALLOWED_EXT = ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.png', '.jpg', '.jpeg']
const ALLOWED_MIME = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'image/png',
  'image/jpeg',
]

const docDefs = ref([])
const existingDocs = ref([])
const files = ref({}) // { key: File | null }
const fileErrors = ref({}) // { key: string }
const additionalDocs = ref([]) // [{ key, label, file: File | null }]
const loading = ref(true)
const saving = ref(false)
const submitting = ref(false)

const { errors, generalMessage, setErrors, clearErrors, clearFieldError, getError } = useFormErrors()

/** Total number of documents uploaded (existing + new files not yet saved). */
const uploadedDocCount = computed(() => {
  const existing = existingDocs.value.length
  const newSchema = Object.values(files.value).filter((f) => f instanceof File).length
  const newAdditional = additionalDocs.value.filter((ad) => ad.file instanceof File).length
  return existing + newSchema + newAdditional
})

const uploadedStatusText = computed(() => {
  const n = uploadedDocCount.value
  return n === 1 ? '1 document is uploaded' : `${n} documents are uploaded`
})

/** Existing documents for a schema doc key (for display when returning from Step 3). */
function getExistingForKey(key) {
  return existingDocs.value.filter((ed) => ed.doc_key === key)
}

function validateFile(file, key) {
  const ext = '.' + (file.name?.split('.').pop() || '').toLowerCase()
  if (!ALLOWED_EXT.includes(ext)) {
    return `File must be one of: ${ALLOWED_EXT.join(', ')}`
  }
  if (file.size > MAX_FILE_MB * 1024 * 1024) {
    return `File must not exceed ${MAX_FILE_MB} MB.`
  }
  return null
}

async function loadData() {
  loading.value = true
  try {
    const [schemaRes, requestRes] = await Promise.all([
      api.getDocumentSchema(),
      api.getRequest(props.vasRequestId),
    ])
    docDefs.value = schemaRes?.data?.documents || []
    existingDocs.value = requestRes?.data?.documents || []
    // Only Trade License is required; normalize in case API returned different flags
    docDefs.value = docDefs.value.map((d) => ({ ...d, required: d.key === 'trade_license' }))
    docDefs.value.forEach((d) => {
      if (d.key && files.value[d.key] === undefined) files.value[d.key] = null
    })
  } catch (e) {
    setErrors(e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
})

// Refetch documents when returning from Step 3 (same vasRequestId, component re-mounted so onMounted runs; keep for clarity if Vue reuses)
watch(
  () => props.vasRequestId,
  (newId, oldId) => {
    if (newId && newId !== oldId) loadData()
  }
)

function onFileChange(key, e) {
  const file = e.target?.files?.[0] || null
  fileErrors.value[key] = null
  if (!file) {
    files.value[key] = null
    e.target.value = ''
    return
  }
  const err = validateFile(file, key)
  if (err) {
    fileErrors.value[key] = err
    files.value[key] = null
    e.target.value = ''
    return
  }
  const otherSize = allFilesTotalSizeBytes.value - (files.value[key] instanceof File ? files.value[key].size : 0) + file.size
  if (otherSize > MAX_TOTAL_MB * 1024 * 1024) {
    fileErrors.value[key] = `Total size of all files must not exceed ${MAX_TOTAL_MB} MB.`
    files.value[key] = null
    e.target.value = ''
    return
  }
  files.value[key] = file
  e.target.value = ''
}

const MAX_ADDITIONAL_DOCS = 3
const canAddMoreAdditional = computed(() => additionalDocs.value.length < MAX_ADDITIONAL_DOCS)

const schemaFilesTotalBytes = computed(() => {
  let total = 0
  Object.values(files.value).forEach((f) => {
    if (f instanceof File && f.size) total += f.size
  })
  return total
})
const additionalDocsTotalSizeBytes = computed(() => {
  let total = 0
  additionalDocs.value.forEach((ad) => {
    if (ad.file instanceof File && ad.file.size) total += ad.file.size
  })
  return total
})
const allFilesTotalSizeBytes = computed(() => schemaFilesTotalBytes.value + additionalDocsTotalSizeBytes.value)
const allFilesTotalSizeMB = computed(() => (allFilesTotalSizeBytes.value / (1024 * 1024)).toFixed(2))

function addAdditionalDoc() {
  if (additionalDocs.value.length >= MAX_ADDITIONAL_DOCS) return
  additionalDocs.value.push({
    key: 'additional_' + Date.now(),
    label: '',
    file: null,
  })
}

function onAdditionalFileChange(index, e) {
  const file = e.target?.files?.[0] || null
  if (!additionalDocs.value[index]) return
  if (!file) {
    additionalDocs.value[index].file = null
    e.target.value = ''
    return
  }
  const err = validateFile(file, 'additional')
  if (err) {
    setErrors({ response: { data: { errors: { additional_documents: [err] } } } })
    e.target.value = ''
    return
  }
  const otherSize = schemaFilesTotalBytes.value + additionalDocs.value.reduce((s, ad, i) => {
    if (i === index) return s
    return s + (ad.file instanceof File ? ad.file.size : 0)
  }, 0)
  if (otherSize + file.size > MAX_TOTAL_MB * 1024 * 1024) {
    setErrors({
      response: {
        data: {
          errors: {
            additional_documents: [
              `Total size of all files must not exceed ${MAX_TOTAL_MB} MB.`,
            ],
          },
        },
      },
    })
    e.target.value = ''
    return
  }
  additionalDocs.value[index].file = file
  if (!(additionalDocs.value[index].label || '').trim()) {
    additionalDocs.value[index].label = file.name
  }
  clearFieldError('additional_documents')
  e.target.value = ''
}

function removeAdditionalDoc(index) {
  additionalDocs.value.splice(index, 1)
}

function buildFormData() {
  const fd = new FormData()
  Object.entries(files.value).forEach(([key, file]) => {
    if (file instanceof File) fd.append(key, file)
  })
  additionalDocs.value.forEach((ad, i) => {
    if (ad.file instanceof File) {
      fd.append('additional_documents[]', ad.file)
      fd.append('additional_document_label[]', ad.label || 'Additional document ' + (i + 1))
    }
  })
  return fd
}

function validateBeforeSubmit() {
  const msgs = []
  docDefs.value.forEach((d) => {
    if (!d.required) return
    const hasExisting = existingDocs.value.some((ed) => ed.doc_key === d.key)
    const hasNew = files.value[d.key] instanceof File
    if (!hasExisting && !hasNew) {
      msgs.push(`${d.label} is required.`)
    }
  })
  additionalDocs.value.forEach((ad, i) => {
    if (ad.file instanceof File && !(ad.label || '').trim()) {
      msgs.push(`Additional document ${i + 1}: label is required when a file is attached.`)
    }
  })
  const totalMB = allFilesTotalSizeBytes.value / (1024 * 1024)
  if (totalMB > MAX_TOTAL_MB) {
    msgs.push(`Total size of all files must not exceed ${MAX_TOTAL_MB} MB.`)
  }
  if (msgs.length) {
    setErrors({ response: { data: { errors: { documents: [msgs.join(' ')] }, message: 'Please fix the errors below.' } } })
    return false
  }
  return true
}

async function saveDraft() {
  clearErrors()
  saving.value = true
  try {
    const fd = buildFormData()
    await api.storeStep2(props.vasRequestId, fd)
    const { data } = await api.getRequest(props.vasRequestId)
    existingDocs.value = data?.documents || []
    docDefs.value.forEach((d) => {
      if (files.value[d.key] instanceof File) files.value[d.key] = null
    })
    additionalDocs.value.forEach((ad) => { ad.file = null })
    emit('draft-saved')
  } catch (e) {
    setErrors(e)
  } finally {
    saving.value = false
  }
}

async function goNext() {
  clearErrors()
  if (!validateBeforeSubmit()) return
  submitting.value = true
  try {
    const fd = buildFormData()
    await api.storeStep2(props.vasRequestId, fd)
    emit('next')
  } catch (e) {
    setErrors(e)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="space-y-8">
    <div
      v-if="generalMessage || Object.keys(errors).length"
      class="rounded-lg border border-red-200 bg-red-50 p-4"
    >
      <p class="text-sm font-medium text-red-800">{{ generalMessage }}</p>
      <ul class="mt-2 list-inside list-disc space-y-0.5 text-sm text-red-700">
        <li v-for="(msgs, field) in errors" :key="field">{{ getError(field) }}</li>
      </ul>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
      </svg>
      <span class="ml-3 text-gray-600">Loading...</span>
    </div>

    <template v-else>
      <!-- VAS Submission Form (Document uploads) -->
      <div>
        <h3 class="border-b border-gray-200 pb-2 text-base font-semibold text-gray-800">VAS Submission Form</h3>
        <p class="mt-2 text-sm text-gray-600">
          {{ uploadedStatusText }}
        </p>
        <p class="mt-1 text-xs text-gray-500">
          Max {{ MAX_FILE_MB }} MB per file, {{ MAX_TOTAL_MB }} MB total. Allowed: {{ ALLOWED_EXT.join(', ') }}
        </p>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div
            v-for="doc in docDefs"
            :key="doc.key"
            class="flex min-w-0 items-center gap-3 rounded-lg border border-gray-300 bg-white px-3 py-2 shadow-sm"
            :class="{ 'border-red-300': fileErrors[doc.key] }"
          >
            <div class="shrink-0 text-gray-500">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-gray-900">{{ doc.label }}</p>
              <p v-if="fileErrors[doc.key]" class="mt-0.5 text-xs text-red-600">{{ fileErrors[doc.key] }}</p>
              <p v-else-if="files[doc.key]" class="mt-0.5 truncate text-xs text-gray-600" :title="files[doc.key].name">
                {{ files[doc.key].name }}
              </p>
              <template v-else v-for="ed in getExistingForKey(doc.key)" :key="ed.id">
                <p class="mt-0.5 truncate text-xs text-gray-600" :title="ed.file_name || ed.label">
                  {{ ed.file_name || ed.label || 'Uploaded' }}
                </p>
              </template>
            </div>
            <label class="shrink-0 cursor-pointer">
              <input
                type="file"
                class="hidden"
                :accept="ALLOWED_EXT.join(',')"
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
      </div>

      <!-- Additional Documents (match images 3 & 4: title + Add Document, total size, card rows) -->
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h4 class="text-sm font-semibold text-gray-900">Additional Documents</h4>
          <button
            type="button"
            @click="addAdditionalDoc"
            :disabled="!canAddMoreAdditional"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-green-600 hover:text-green-700 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Document
          </button>
        </div>
        <p class="mt-2 text-xs text-gray-500">
          Total size: {{ allFilesTotalSizeMB }} MB / {{ MAX_TOTAL_MB }} MB
        </p>
        <div v-if="additionalDocs.length" class="mt-4 space-y-3">
          <div
            v-for="(ad, index) in additionalDocs"
            :key="ad.key"
            class="flex min-w-0 flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2.5 shadow-sm"
          >
            <div class="shrink-0 text-gray-400">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <input
              v-model="ad.label"
              type="text"
              :placeholder="'Additional Document ' + (index + 1)"
              class="min-w-0 flex-1 rounded border border-gray-300 px-3 py-1.5 text-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            <label class="shrink-0 cursor-pointer">
              <input
                type="file"
                class="hidden"
                :accept="ALLOWED_EXT.join(',')"
                @change="onAdditionalFileChange(index, $event)"
              />
              <span class="inline-flex items-center gap-1.5 rounded-lg border border-blue-500 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-600 hover:bg-blue-100">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                Upload
              </span>
            </label>
            <button
              type="button"
              @click="removeAdditionalDoc(index)"
              class="shrink-0 text-sm text-red-600 hover:underline"
            >
              Remove
            </button>
          </div>
        </div>
      </div>

      <!-- Actions: left = Back + Step 2, right = Save as Draft, Cancel, Next -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-6">
        <div class="flex items-center gap-3">
          <button
            type="button"
            @click="emit('back')"
            class="inline-flex items-center gap-2 rounded-lg bg-teal-500 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-teal-600"
          >
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back
          </button>
          <span class="rounded-lg bg-[#121d2c] px-4 py-2.5 text-sm font-medium text-white shadow-sm">Step 2</span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <button
            type="button"
            @click="saveDraft"
            :disabled="saving || submitting"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-200 disabled:opacity-50"
          >
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            Save as Draft
          </button>
          <button
            type="button"
            class="rounded-lg bg-teal-500 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-teal-600"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="goNext"
            :disabled="saving || submitting"
            class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white shadow-sm disabled:opacity-50 bg-green-600 hover:bg-green-700"
          >
            {{ submitting ? 'Saving...' : 'Next' }}
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
