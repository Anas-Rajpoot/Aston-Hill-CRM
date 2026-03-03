<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/vasRequestsApi'
import { useFormErrors } from '@/composables/useFormErrors'

const props = defineProps({
  vasRequestId: { type: Number, required: true },
})

const emit = defineEmits(['back', 'draft-saved', 'submitted'])

const request = ref(null)
const docSchema = ref([])
const loading = ref(true)
const saving = ref(false)
const submitting = ref(false)

const { errors, generalMessage, setErrors, clearErrors, getError } = useFormErrors()

const docKeyToLabel = computed(() => {
  const map = {}
  docSchema.value.forEach((d) => { map[d.key] = d.label })
  return map
})

const documentsByKey = computed(() => {
  const list = request.value?.documents || []
  const byKey = {}
  list.forEach((doc) => {
    if (!byKey[doc.doc_key]) byKey[doc.doc_key] = []
    byKey[doc.doc_key].push(doc)
  })
  return byKey
})

const totalDocCount = computed(() => request.value?.documents?.length ?? 0)

function formatSize(bytes) {
  if (bytes == null || bytes === 0) return '—'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

onMounted(async () => {
  loading.value = true
  try {
    const [schemaRes, requestRes] = await Promise.all([
      api.getDocumentSchema(),
      api.getRequest(props.vasRequestId),
    ])
    docSchema.value = schemaRes?.data?.documents || []
    request.value = requestRes?.data
  } catch (e) {
    setErrors(e)
  } finally {
    loading.value = false
  }
})

async function saveDraft() {
  clearErrors()
  saving.value = true
  try {
    await api.getRequest(props.vasRequestId)
    emit('draft-saved')
  } catch (e) {
    setErrors(e)
  } finally {
    saving.value = false
  }
}

async function submit() {
  clearErrors()
  submitting.value = true
  try {
    await api.submit(props.vasRequestId)
    emit('submitted')
  } catch (e) {
    setErrors(e)
  } finally {
    submitting.value = false
  }
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

    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
      </svg>
      <span class="ml-3 text-gray-600">Loading...</span>
    </div>

    <template v-else-if="request">
      <div>
        <h3 class="text-base font-semibold text-gray-800">Review & Submit</h3>
        <p class="mt-1 text-sm text-gray-600">Please review all information before submitting.</p>
      </div>

      <!-- Primary Information -->
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h4 class="mb-3 text-sm font-semibold text-gray-800">Primary Information</h4>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <p class="text-xs text-gray-500">Request Type</p>
            <p class="text-sm font-medium text-gray-900">{{ request.request_type || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Account Number</p>
            <p class="text-sm font-medium text-gray-900">{{ request.account_number || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Company Name</p>
            <p class="text-sm font-medium text-gray-900">{{ request.company_name || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Contact Number</p>
            <p class="text-sm font-medium text-gray-900">{{ request.contact_number || '—' }}</p>
          </div>
        </div>
      </div>

      <!-- Request Description -->
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h4 class="mb-2 text-sm font-semibold text-gray-800">Request Description</h4>
        <p class="whitespace-pre-wrap text-sm text-gray-700">{{ request.request_description || request.description || '—' }}</p>
      </div>

      <!-- Comment / Remarks -->
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h4 class="mb-2 text-sm font-semibold text-gray-800">Comment / Remarks</h4>
        <p class="whitespace-pre-wrap text-sm text-gray-700">{{ request.additional_notes || '—' }}</p>
      </div>

      <!-- Uploaded Documents -->
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h4 class="mb-3 text-sm font-semibold text-gray-800">
          Uploaded Documents ({{ totalDocCount }} {{ totalDocCount === 1 ? 'file' : 'files' }})
        </h4>
        <div v-if="totalDocCount === 0" class="text-sm text-gray-500">No documents uploaded.</div>
        <div v-else class="space-y-3">
          <div
            v-for="(docs, key) in documentsByKey"
            :key="key"
            class="rounded border border-gray-200 bg-gray-50 p-3"
          >
            <p class="text-sm font-medium text-gray-800">
              {{ docKeyToLabel[key] || (request.documents?.find(d => d.doc_key === key)?.label) || key }} ({{ docs.length }} {{ docs.length === 1 ? 'file' : 'files' }})
            </p>
            <ul class="mt-2 space-y-1 pl-4 text-sm text-gray-600">
              <li v-for="doc in docs" :key="doc.id">
                {{ doc.file_name || 'File' }}
                <span v-if="doc.size != null" class="text-gray-500">({{ formatSize(doc.size) }})</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Team Information -->
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h4 class="mb-3 text-sm font-semibold text-gray-800">Team Information</h4>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <p class="text-xs text-gray-500">Manager</p>
            <p class="text-sm font-medium text-gray-900">{{ request.manager_name || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Team Leader</p>
            <p class="text-sm font-medium text-gray-900">{{ request.team_leader_name || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Sales Agent</p>
            <p class="text-sm font-medium text-gray-900">{{ request.sales_agent_name || '—' }}</p>
          </div>
        </div>
      </div>

      <!-- Submitter Information -->
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h4 class="mb-3 text-sm font-semibold text-gray-800">Submitter Information</h4>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <p class="text-xs text-gray-500">Submitted By</p>
            <p class="text-sm font-medium text-gray-900">{{ request.creator_name || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Submitter Role</p>
            <p class="text-sm font-medium text-gray-900">{{ request.creator_role || '—' }}</p>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-6">
        <div class="flex items-center gap-3">
          <button
            type="button"
            @click="emit('back')"
            class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-green-700"
          >
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back
          </button>
          <button
            type="button"
            @click="emit('back')"
            class="rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-green-700"
          >
            Cancel
          </button>
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
            @click="submit"
            :disabled="saving || submitting"
            class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-green-700 disabled:opacity-50"
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
