<script setup>
/**
 * VAS Request Edit – form: request type, account, company, description, team; documents: view, download, remove, add new.
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import vasRequestsApi from '@/services/vasRequestsApi'
import { useAuthStore } from '@/stores/auth'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const MAX_FILE_MB = 3
const MAX_TOTAL_MB = 10
const ALLOWED_EXT = ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.png', '.jpg', '.jpeg']

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const saving = ref(false)
const request = ref(null)
const teamOptions = ref({
  managers: [],
  team_leaders: [],
  sales_agents: [],
})
const requestTypes = ref([])
const docSchema = ref([])
const newDocFiles = ref({})
const newAdditionalDocs = ref([])
const uploadSaving = ref(false)
const docUploadError = ref('')
const removingDocId = ref(null)
const documentToRemove = ref(null)

const form = ref({
  request_type: '',
  account_number: '',
  company_name: '',
  description: '',
  manager_id: null,
  team_leader_id: null,
  sales_agent_id: null,
  back_office_executive_id: null,
})

const id = computed(() => {
  const p = route.params.id
  return p != null ? Number(p) : null
})

const filteredTeamLeaders = computed(() => {
  const mid = form.value.manager_id
  if (!mid) return teamOptions.value.team_leaders ?? []
  return (teamOptions.value.team_leaders ?? []).filter((t) => String(t.manager_id) === String(mid))
})

const filteredSalesAgents = computed(() => {
  const tlId = form.value.team_leader_id
  if (!tlId) return teamOptions.value.sales_agents ?? []
  return (teamOptions.value.sales_agents ?? []).filter((s) => String(s.team_leader_id) === String(tlId))
})

async function load() {
  if (!id.value) return
  loading.value = true
  request.value = null
  try {
    const [reqRes, teamRes, filtersRes, schemaRes] = await Promise.all([
      vasRequestsApi.getRequest(id.value),
      vasRequestsApi.getTeamOptions().then((r) => r?.data ?? r).catch(() => ({})),
      vasRequestsApi.filters().catch(() => ({})),
      vasRequestsApi.getDocumentSchema().then((r) => r?.data ?? r).catch(() => ({ documents: [] })),
    ])
    const data = reqRes?.data ?? reqRes
    request.value = data
    teamOptions.value = {
      managers: teamRes.managers ?? [],
      team_leaders: teamRes.team_leaders ?? [],
      sales_agents: teamRes.sales_agents ?? [],
    }
    requestTypes.value = (filtersRes.request_types ?? []).map((t) => (typeof t === 'string' ? t : t?.value ?? t))
    docSchema.value = schemaRes.documents ?? []
    docSchema.value.forEach((d) => {
      if (d.key && newDocFiles.value[d.key] === undefined) newDocFiles.value[d.key] = null
    })
    form.value = {
      request_type: data.request_type ?? '',
      account_number: data.account_number ?? '',
      company_name: data.company_name ?? '',
      description: data.description ?? '',
      manager_id: data.manager_id ?? null,
      team_leader_id: data.team_leader_id ?? null,
      sales_agent_id: data.sales_agent_id ?? null,
      back_office_executive_id: data.back_office_executive_id ?? null,
    }
  } catch {
    request.value = null
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
}

const documents = computed(() => request.value?.documents ?? [])

function docDisplayName(doc) {
  return doc?.file_name || doc?.label || doc?.doc_key || 'Document'
}

async function downloadDoc(doc) {
  if (!id.value || !doc?.id) return
  try {
    const blob = await vasRequestsApi.downloadDocument(id.value, doc.id)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = docDisplayName(doc)
    a.click()
    URL.revokeObjectURL(url)
  } catch {}
}

function openRemoveConfirm(doc) {
  if (!doc?.id) return
  documentToRemove.value = doc
}

function closeRemoveConfirm() {
  documentToRemove.value = null
}

async function confirmRemoveDoc() {
  const doc = documentToRemove.value
  if (!id.value || !doc?.id) return
  removingDocId.value = doc.id
  try {
    await vasRequestsApi.deleteDocument(id.value, doc.id)
    if (request.value?.documents) {
      request.value.documents = request.value.documents.filter((d) => d.id !== doc.id)
    }
    closeRemoveConfirm()
  } catch (e) {
    alert(e?.response?.data?.message || 'Failed to remove document.')
  } finally {
    removingDocId.value = null
  }
}

function validateNewFile(file) {
  const ext = '.' + (file.name?.split('.').pop() || '').toLowerCase()
  if (!ALLOWED_EXT.includes(ext)) return `Allowed: ${ALLOWED_EXT.join(', ')}`
  if (file.size > MAX_FILE_MB * 1024 * 1024) return `Max ${MAX_FILE_MB} MB per file.`
  return null
}

function onNewSchemaFileChange(key, e) {
  const file = e.target?.files?.[0] || null
  if (!file) {
    newDocFiles.value[key] = null
    e.target.value = ''
    return
  }
  const err = validateNewFile(file)
  if (err) {
    docUploadError.value = err
    e.target.value = ''
    return
  }
  docUploadError.value = ''
  newDocFiles.value[key] = file
  e.target.value = ''
}

function addAdditionalDocSlot() {
  if (newAdditionalDocs.value.length >= 3) return
  newAdditionalDocs.value.push({ key: 'additional_' + Date.now(), label: '', file: null })
}

function onAdditionalFileChange(index, e) {
  const file = e.target?.files?.[0] || null
  if (!newAdditionalDocs.value[index]) return
  if (!file) {
    newAdditionalDocs.value[index].file = null
    e.target.value = ''
    return
  }
  const err = validateNewFile(file)
  if (err) {
    docUploadError.value = err
    e.target.value = ''
    return
  }
  docUploadError.value = ''
  newAdditionalDocs.value[index].file = file
  if (!(newAdditionalDocs.value[index].label || '').trim()) newAdditionalDocs.value[index].label = file.name
  e.target.value = ''
}

function removeAdditionalSlot(index) {
  newAdditionalDocs.value.splice(index, 1)
}

const hasNewFilesToUpload = computed(() => {
  const schemaFiles = Object.values(newDocFiles.value).filter((f) => f instanceof File)
  const additionalFiles = newAdditionalDocs.value.filter((ad) => ad.file instanceof File)
  return schemaFiles.length > 0 || additionalFiles.length > 0
})

function buildUploadFormData() {
  const fd = new FormData()
  Object.entries(newDocFiles.value).forEach(([key, file]) => {
    if (file instanceof File) fd.append(key, file)
  })
  newAdditionalDocs.value.forEach((ad, i) => {
    if (ad.file instanceof File) {
      fd.append('additional_documents[]', ad.file)
      fd.append('additional_document_label[]', ad.label || 'Additional ' + (i + 1))
    }
  })
  return fd
}

async function uploadNewDocuments() {
  if (!id.value || !hasNewFilesToUpload.value) return
  uploadSaving.value = true
  docUploadError.value = ''
  try {
    const fd = buildUploadFormData()
    await vasRequestsApi.storeStep2(id.value, fd)
    Object.keys(newDocFiles.value).forEach((k) => { newDocFiles.value[k] = null })
    newAdditionalDocs.value = []
    // Refetch only documents so the list updates without refreshing the whole page
    const res = await vasRequestsApi.getRequest(id.value)
    const data = res?.data ?? res
    if (request.value && data?.documents) {
      request.value.documents = data.documents
    }
  } catch (e) {
    docUploadError.value = e?.response?.data?.message || e?.response?.data?.errors?.documents?.[0] || 'Upload failed.'
  } finally {
    uploadSaving.value = false
  }
}

function goBack() {
  router.push('/vas-requests')
}

async function submitForm() {
  if (!id.value) return
  saving.value = true
  try {
    await vasRequestsApi.updateRequest(id.value, {
      request_type: form.value.request_type,
      account_number: form.value.account_number || null,
      company_name: form.value.company_name,
      description: form.value.description || null,
      manager_id: form.value.manager_id,
      team_leader_id: form.value.team_leader_id,
      sales_agent_id: form.value.sales_agent_id,
      back_office_executive_id: form.value.back_office_executive_id || null,
    })
    router.push(`/vas-requests/${id.value}`)
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Failed to save.'
    alert(msg)
  } finally {
    saving.value = false
  }
}

onMounted(() => load())
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] p-0">
    <div class="mx-auto max-w-7xl px-1 sm:px-2">
      <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="px-4 py-4 sm:px-5">
          <div class="flex flex-wrap items-baseline gap-2">
            <h1 class="text-xl font-semibold text-gray-900">Edit VAS Request</h1>
            <Breadcrumbs />
          </div>
        </div>
        <div class="border-t border-gray-200" />

        <div v-if="loading" class="flex justify-center px-4 py-16 sm:px-5">
          <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </div>

        <div v-else-if="!request" class="px-4 py-8 text-center text-gray-500 sm:px-5">
          Unable to load request. You may not have permission to view it.
        </div>

        <form v-else class="px-4 py-5 sm:px-5" @submit.prevent="submitForm">
          <h2 class="mb-4 text-sm font-semibold text-gray-900">Request Information</h2>
          <div class="grid gap-4 sm:grid-cols-3">
            <div>
              <label class="block text-sm font-medium text-gray-700">Company Name <span class="text-red-500">*</span></label>
              <input
                v-model="form.company_name"
                type="text"
                required
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Account Number</label>
              <input
                v-model="form.account_number"
                type="text"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Request Type <span class="text-red-500">*</span></label>
              <select
                v-model="form.request_type"
                required
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              >
                <option value="">Select</option>
                <option v-for="t in requestTypes" :key="t" :value="t">{{ t }}</option>
              </select>
            </div>
            <div class="sm:col-span-3">
              <label class="block text-sm font-medium text-gray-700">Description</label>
              <textarea
                v-model="form.description"
                rows="4"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              />
            </div>
          </div>

          <h2 class="mb-4 mt-8 text-sm font-semibold text-gray-900">Team</h2>
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Manager <span class="text-red-500">*</span></label>
              <select
                v-model="form.manager_id"
                required
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              >
                <option :value="null">Select</option>
                <option v-for="u in teamOptions.managers" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Team Leader <span class="text-red-500">*</span></label>
              <select
                v-model="form.team_leader_id"
                required
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              >
                <option :value="null">Select</option>
                <option v-for="u in filteredTeamLeaders" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Sales Agent <span class="text-red-500">*</span></label>
              <select
                v-model="form.sales_agent_id"
                required
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              >
                <option :value="null">Select</option>
                <option v-for="u in filteredSalesAgents" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Back Office Executive</label>
              <select
                v-model="form.back_office_executive_id"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              >
                <option :value="null">Select</option>
                <option v-for="u in teamOptions.sales_agents" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
          </div>

          <!-- Documents: existing (view, download, remove) + add new -->
          <h2 class="mb-3 mt-8 text-sm font-semibold text-gray-900">Documents</h2>
          <div class="space-y-4 rounded-lg border border-gray-200 bg-gray-50/50 p-4">
            <div v-if="documents.length > 0">
              <p class="mb-2 text-xs font-medium text-gray-600">Uploaded documents</p>
              <ul class="space-y-2">
                <li
                  v-for="doc in documents"
                  :key="doc.id"
                  class="flex flex-wrap items-center justify-between gap-2 rounded border border-gray-200 bg-white px-3 py-2 text-sm"
                >
                  <span class="min-w-0 truncate font-medium text-gray-800" :title="docDisplayName(doc)">{{ docDisplayName(doc) }}</span>
                  <div class="flex shrink-0 items-center gap-2">
                    <button
                      type="button"
                      class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                      @click="downloadDoc(doc)"
                    >
                      <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                      </svg>
                      Download
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1 rounded border border-red-200 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50"
                      :disabled="removingDocId === doc.id"
                      @click="openRemoveConfirm(doc)"
                    >
                      <span v-if="removingDocId === doc.id">Removing...</span>
                      <span v-else>Remove</span>
                    </button>
                  </div>
                </li>
              </ul>
            </div>
            <p v-else class="text-sm text-gray-500">No documents uploaded yet.</p>

            <div class="border-t border-gray-200 pt-4">
              <p class="mb-3 text-xs font-medium text-gray-600">Add new documents</p>
              <p class="mb-2 text-xs text-gray-500">Max {{ MAX_FILE_MB }} MB per file, {{ MAX_TOTAL_MB }} MB total. Allowed: {{ ALLOWED_EXT.join(', ') }}</p>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div
                  v-for="doc in docSchema"
                  :key="doc.key"
                  class="flex min-w-0 items-center gap-2 rounded border border-gray-300 bg-white px-3 py-2"
                >
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900">{{ doc.label }}</p>
                    <p v-if="newDocFiles[doc.key]" class="truncate text-xs text-gray-600">{{ newDocFiles[doc.key].name }}</p>
                  </div>
                  <label class="shrink-0 cursor-pointer">
                    <input type="file" class="hidden" :accept="ALLOWED_EXT.join(',')" @change="onNewSchemaFileChange(doc.key, $event)" />
                    <span class="inline-flex items-center gap-1 rounded bg-green-600 px-2 py-1 text-xs font-medium text-white hover:bg-green-700">Upload</span>
                  </label>
                </div>
              </div>
              <div class="mt-3">
                <button
                  type="button"
                  class="text-sm font-medium text-green-600 hover:text-green-700 disabled:opacity-50"
                  :disabled="newAdditionalDocs.length >= 3"
                  @click="addAdditionalDocSlot"
                >
                  + Add additional document
                </button>
                <div v-if="newAdditionalDocs.length > 0" class="mt-2 space-y-2">
                  <div
                    v-for="(ad, index) in newAdditionalDocs"
                    :key="ad.key"
                    class="flex flex-wrap items-center gap-2 rounded border border-gray-200 bg-white px-3 py-2"
                  >
                    <input
                      v-model="ad.label"
                      type="text"
                      placeholder="Label"
                      class="min-w-0 flex-1 rounded border border-gray-300 px-2 py-1 text-sm"
                    />
                    <label class="shrink-0 cursor-pointer">
                      <input type="file" class="hidden" :accept="ALLOWED_EXT.join(',')" @change="onAdditionalFileChange(index, $event)" />
                      <span class="inline-flex rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">Choose file</span>
                    </label>
                    <span v-if="ad.file" class="truncate text-xs text-gray-600">{{ ad.file.name }}</span>
                    <button type="button" class="text-xs text-red-600 hover:underline" @click="removeAdditionalSlot(index)">Remove</button>
                  </div>
                </div>
              </div>
              <div v-if="docUploadError" class="mt-2 text-sm text-red-600">{{ docUploadError }}</div>
              <button
                type="button"
                class="mt-3 inline-flex items-center gap-1 rounded bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                :disabled="!hasNewFilesToUpload || uploadSaving"
                @click.prevent="uploadNewDocuments"
              >
                <span v-if="uploadSaving">Uploading...</span>
                <span v-else>Upload selected files</span>
              </button>
            </div>
          </div>

          <div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4">
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              @click="goBack"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="inline-flex items-center gap-2 rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-70"
            >
              <span v-if="saving">Saving...</span>
              <span v-else>Update VAS Request</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Remove document confirmation modal -->
  <Teleport to="body">
    <div
      v-if="documentToRemove"
      class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/50 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="remove-doc-modal-title"
      @click.self="closeRemoveConfirm"
    >
      <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
          <h2 id="remove-doc-modal-title" class="text-lg font-semibold text-gray-900">Remove document</h2>
          <button
            type="button"
            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
            aria-label="Close"
            @click="closeRemoveConfirm"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="px-6 py-4">
          <p class="text-sm text-gray-600">
            The document
            <span class="font-medium text-gray-900">"{{ documentToRemove ? docDisplayName(documentToRemove) : '' }}"</span>
            will be permanently removed from this request. You cannot undo this action.
          </p>
          <p class="mt-2 text-sm text-gray-600">
            Do you want to continue?
          </p>
        </div>
        <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
          <button
            type="button"
            class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            @click="closeRemoveConfirm"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
            :disabled="removingDocId === documentToRemove?.id"
            @click="confirmRemoveDoc"
          >
            <span v-if="removingDocId === documentToRemove?.id">Removing...</span>
            <span v-else>Remove</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
