<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import specialRequestsApi from '@/services/specialRequestsApi'
import { useFormErrors } from '@/composables/useFormErrors'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Toast from '@/components/Toast.vue'

const route = useRoute()
const router = useRouter()

const id = computed(() => Number(route.params.id))

const loading = ref(true)
const saving = ref(false)
const documents = ref([])
const uploadingDocs = ref(false)
const removingDocId = ref(null)
const addDocumentSlots = ref([{ id: 0 }])
const showToast = ref(false)
const toastType = ref('success')
const toastMsg = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

const REQUEST_TYPES = ['General', 'Support', 'Relocation', 'Renewal', 'Other']
const STATUSES = ['draft', 'submitted', 'approved', 'rejected']

const { errors, generalMessage, setErrors, clearErrors, clearFieldError, getError } = useFormErrors()

const form = ref({
  company_name: '',
  account_number: '',
  request_type: '',
  status: '',
  complete_address: '',
  special_instruction: '',
  manager_id: '',
  team_leader_id: '',
  sales_agent_id: '',
})

const teamOptions = ref({ managers: [], team_leaders: [], sales_agents: [] })
const settingFromChild = ref(false)
const settingFromSalesAgent = ref(false)

const filteredTeamLeaders = computed(() => {
  const mid = form.value.manager_id
  if (!mid) return teamOptions.value.team_leaders
  return teamOptions.value.team_leaders.filter((t) => String(t.manager_id) === String(mid))
})

const filteredSalesAgents = computed(() => {
  const tlId = form.value.team_leader_id
  if (!tlId) return teamOptions.value.sales_agents
  return teamOptions.value.sales_agents.filter((sa) => String(sa.team_leader_id) === String(tlId))
})

watch(() => form.value.manager_id, () => {
  if (settingFromChild.value) { nextTick(() => { settingFromChild.value = false }); return }
  form.value.team_leader_id = ''
  form.value.sales_agent_id = ''
})

watch(() => form.value.team_leader_id, (val) => {
  if (val) {
    const tl = teamOptions.value.team_leaders.find((u) => String(u.id) === String(val))
    if (tl?.manager_id != null) { settingFromChild.value = true; form.value.manager_id = String(tl.manager_id); nextTick(() => { settingFromChild.value = false }) }
    if (!settingFromSalesAgent.value) form.value.sales_agent_id = ''
  } else { form.value.manager_id = '' }
})

watch(() => form.value.sales_agent_id, (val) => {
  if (val) {
    const sa = teamOptions.value.sales_agents.find((u) => String(u.id) === String(val))
    if (sa) {
      settingFromSalesAgent.value = true; settingFromChild.value = true
      if (sa.team_leader_id != null) form.value.team_leader_id = String(sa.team_leader_id)
      if (sa.manager_id != null) form.value.manager_id = String(sa.manager_id)
      nextTick(() => { settingFromSalesAgent.value = false; settingFromChild.value = false })
    }
  } else { form.value.team_leader_id = ''; form.value.manager_id = '' }
})

async function loadData() {
  loading.value = true
  try {
    const [reqData, teamRes] = await Promise.all([
      specialRequestsApi.getRequest(id.value),
      specialRequestsApi.getTeamOptions(),
    ])
    const teamData = teamRes?.data ?? teamRes ?? {}
    teamOptions.value = {
      managers: teamData.managers ?? [],
      team_leaders: teamData.team_leaders ?? [],
      sales_agents: teamData.sales_agents ?? [],
    }

    settingFromChild.value = true
    settingFromSalesAgent.value = true
    form.value = {
      company_name: reqData.company_name || '',
      account_number: reqData.account_number || '',
      request_type: reqData.request_type || '',
      status: reqData.status || '',
      complete_address: reqData.complete_address || '',
      special_instruction: reqData.special_instruction || '',
      manager_id: reqData.manager_id != null ? String(reqData.manager_id) : '',
      team_leader_id: reqData.team_leader_id != null ? String(reqData.team_leader_id) : '',
      sales_agent_id: reqData.sales_agent_id != null ? String(reqData.sales_agent_id) : '',
    }
    documents.value = Array.isArray(reqData?.documents) ? reqData.documents : []
    nextTick(() => { settingFromChild.value = false; settingFromSalesAgent.value = false })
  } catch (e) {
    setErrors(e)
  } finally {
    loading.value = false
  }
}

function validateForm() {
  const err = {}
  if (!form.value.company_name?.trim()) err.company_name = ['Company name is required.']
  if (!form.value.request_type?.trim()) err.request_type = ['Request type is required.']
  if (!form.value.manager_id) err.manager_id = ['Manager is required.']
  if (!form.value.team_leader_id) err.team_leader_id = ['Team leader is required.']
  if (!form.value.sales_agent_id) err.sales_agent_id = ['Sales agent is required.']
  return Object.keys(err).length ? err : null
}

async function save() {
  clearErrors()
  const frontendErrors = validateForm()
  if (frontendErrors) {
    errors.value = frontendErrors
    generalMessage.value = 'Please correct the errors below.'
    return
  }
  saving.value = true
  try {
    await specialRequestsApi.updateRequest(id.value, {
      company_name: form.value.company_name?.trim(),
      account_number: form.value.account_number?.trim() || null,
      request_type: form.value.request_type?.trim(),
      status: form.value.status || undefined,
      complete_address: form.value.complete_address?.trim() || null,
      special_instruction: form.value.special_instruction?.trim() || null,
      manager_id: Number(form.value.manager_id),
      team_leader_id: Number(form.value.team_leader_id),
      sales_agent_id: Number(form.value.sales_agent_id),
    })
    toast('success', 'Special request updated successfully.')
    setTimeout(() => router.push(`/special-requests/${id.value}`), 800)
  } catch (e) {
    setErrors(e)
    toast('error', 'Failed to update.')
  } finally {
    saving.value = false
  }
}

function docDisplayName(doc) {
  return doc?.label || doc?.original_name || doc?.file_name || 'Document'
}

function formatFileSize(bytes) {
  if (bytes == null) return ''
  const n = Number(bytes)
  if (Number.isNaN(n) || n < 0) return ''
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  return `${(n / (1024 * 1024)).toFixed(1)} MB`
}

function addDocumentSlot() {
  addDocumentSlots.value = [...addDocumentSlots.value, { id: Date.now() }]
}

async function downloadDocument(doc) {
  if (!id.value || !doc?.id) return
  try {
    const blob = await specialRequestsApi.downloadDocument(id.value, doc.id)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = docDisplayName(doc)
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  } catch {
    window.open(`/api/special-requests/${id.value}/documents/${doc.id}/download`, '_blank')
  }
}

async function uploadFromInput(e) {
  const input = e?.target
  if (!input?.files?.length || !id.value) return
  uploadingDocs.value = true
  try {
    const formData = new FormData()
    for (let i = 0; i < input.files.length; i++) {
      formData.append('documents[]', input.files[i])
    }
    await specialRequestsApi.uploadDocuments(id.value, formData)
    const reqData = await specialRequestsApi.getRequest(id.value)
    documents.value = Array.isArray(reqData?.documents) ? reqData.documents : []
    input.value = ''
    toast('success', 'Documents uploaded successfully.')
  } catch (e2) {
    setErrors(e2)
    toast('error', 'Failed to upload documents.')
  } finally {
    uploadingDocs.value = false
  }
}

async function removeDocument(doc) {
  if (!id.value || !doc?.id) return
  if (!confirm('Remove this document?')) return
  removingDocId.value = doc.id
  try {
    await specialRequestsApi.deleteDocument(id.value, doc.id)
    documents.value = documents.value.filter((d) => d.id !== doc.id)
    toast('success', 'Document removed.')
  } catch (e2) {
    setErrors(e2)
    toast('error', 'Failed to remove document.')
  } finally {
    removingDocId.value = null
  }
}

function goBack() { router.push(`/special-requests/${id.value}`) }

const inputClass = (field) =>
  `w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500 ${getError(field) ? 'border-red-500' : 'border-gray-300'}`
const selectClass = (field) =>
  `w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 focus:border-green-500 focus:ring-1 focus:ring-green-500 ${getError(field) ? 'border-red-500' : 'border-gray-300'}`

onMounted(() => loadData())
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] p-0">
    <div class="w-full">
      <div class="mb-4 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="flex flex-wrap items-baseline gap-2">
            <h1 class="text-xl font-semibold text-gray-900">Edit Special Request</h1>
            <Breadcrumbs />
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              @click="goBack"
            >
              Close
            </button>
            <router-link
              to="/special-requests"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              Back to List
            </router-link>
          </div>
          </div>
        </div>

      <div v-if="loading" class="flex justify-center py-16">
          <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
        </div>

      <div v-else class="space-y-6">
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
          <div class="px-6 py-4 space-y-6">
          <div v-if="generalMessage || Object.keys(errors).length" class="rounded-lg border border-red-200 bg-red-50 p-4">
            <p class="text-sm font-medium text-red-800">{{ generalMessage }}</p>
            <ul class="mt-2 list-inside list-disc space-y-0.5 text-sm text-red-700">
              <li v-for="(msgs, field) in errors" :key="field">{{ getError(field) }}</li>
            </ul>
          </div>

          <!-- Primary Information -->
          <section>
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Primary Information</h2>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <div>
                  <label class="block text-xs font-medium text-gray-500">Company Name <span class="text-red-500">*</span></label>
                  <input v-model="form.company_name" type="text" placeholder="Enter company name" :class="`${inputClass('company_name')} mt-0.5`" @input="clearFieldError('company_name')" />
                  <p v-if="getError('company_name')" class="mt-1 text-xs text-red-600">{{ getError('company_name') }}</p>
              </div>
              <div>
                  <label class="block text-xs font-medium text-gray-500">Account Number</label>
                  <input v-model="form.account_number" type="text" placeholder="Enter account number" :class="`${inputClass('account_number')} mt-0.5`" />
              </div>
              <div>
                  <label class="block text-xs font-medium text-gray-500">Request Type <span class="text-red-500">*</span></label>
                  <select v-model="form.request_type" :class="`${selectClass('request_type')} mt-0.5`" @change="clearFieldError('request_type')">
                  <option value="">Select</option>
                  <option v-for="t in REQUEST_TYPES" :key="t" :value="t">{{ t }}</option>
                </select>
                  <p v-if="getError('request_type')" class="mt-1 text-xs text-red-600">{{ getError('request_type') }}</p>
              </div>
              <div>
                  <label class="block text-xs font-medium text-gray-500">Status</label>
                  <select v-model="form.status" :class="`${selectClass('status')} mt-0.5`">
                  <option value="">Select</option>
                  <option v-for="s in STATUSES" :key="s" :value="s">{{ s.charAt(0).toUpperCase() + s.slice(1) }}</option>
                </select>
              </div>
            </div>
              <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                  <label class="block text-xs font-medium text-gray-500">Complete Address</label>
                  <textarea v-model="form.complete_address" rows="3" placeholder="Enter complete address" :class="`${inputClass('complete_address')} mt-0.5`" />
              </div>
              <div>
                  <label class="block text-xs font-medium text-gray-500">Any Special Instruction</label>
                  <textarea v-model="form.special_instruction" rows="3" placeholder="Enter Special Instruction" :class="`${inputClass('special_instruction')} mt-0.5`" />
                </div>
            </div>
          </section>

          <!-- Team Information -->
          <section>
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Team Information</h2>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <div>
                  <label class="block text-xs font-medium text-gray-500">Manager <span class="text-red-500">*</span></label>
                  <select v-model="form.manager_id" :class="`${selectClass('manager_id')} mt-0.5`" @change="clearFieldError('manager_id')">
                  <option value="">Select Manager</option>
                  <option v-for="u in teamOptions.managers" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
                  <p v-if="getError('manager_id')" class="mt-1 text-xs text-red-600">{{ getError('manager_id') }}</p>
              </div>
              <div>
                  <label class="block text-xs font-medium text-gray-500">Team Leader <span class="text-red-500">*</span></label>
                  <select v-model="form.team_leader_id" :class="`${selectClass('team_leader_id')} mt-0.5`" @change="clearFieldError('team_leader_id')">
                  <option value="">Select Team Leader</option>
                  <option v-for="u in filteredTeamLeaders" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
                  <p v-if="getError('team_leader_id')" class="mt-1 text-xs text-red-600">{{ getError('team_leader_id') }}</p>
              </div>
              <div>
                  <label class="block text-xs font-medium text-gray-500">Sales Agent <span class="text-red-500">*</span></label>
                  <select v-model="form.sales_agent_id" :class="`${selectClass('sales_agent_id')} mt-0.5`" @change="clearFieldError('sales_agent_id')">
                  <option value="">Select Sales Agent</option>
                  <option v-for="u in filteredSalesAgents" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
                  <p v-if="getError('sales_agent_id')" class="mt-1 text-xs text-red-600">{{ getError('sales_agent_id') }}</p>
                </div>
              </div>
            </section>

            <section>
              <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900">Documents</h2>
              </div>
              <div v-if="documents.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                  v-for="doc in documents"
                  :key="doc.id"
                  class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-100 p-3"
                >
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-red-600 text-sm font-bold text-white">D</div>
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900" :title="docDisplayName(doc)">{{ docDisplayName(doc) }}</p>
                    <p class="text-xs text-gray-500">{{ formatFileSize(doc.size) }}</p>
                  </div>
                  <div class="flex shrink-0 items-center gap-1">
                    <button
                      type="button"
                      class="rounded p-1.5 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                      title="Download"
                      @click="downloadDocument(doc)"
                    >
                      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                      </svg>
                    </button>
                    <button
                      type="button"
                      class="rounded p-1.5 text-red-600 hover:bg-red-50 hover:text-red-700"
                      title="Remove"
                      :disabled="removingDocId === doc.id"
                      @click="removeDocument(doc)"
                    >
                      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
              <div v-else class="rounded-lg border border-gray-200 bg-gray-50 py-8 text-center text-sm text-gray-500">No documents uploaded.</div>

              <div class="mt-4 border-t border-gray-200 pt-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                  <h3 class="text-base font-semibold text-gray-900">Add Document</h3>
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 text-sm font-medium text-green-600 hover:text-green-700"
                    @click="addDocumentSlot"
                  >
                    <svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Document
                  </button>
                </div>
                <div class="space-y-3">
                  <div
                    v-for="slot in addDocumentSlots"
                    :key="slot.id"
                    class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                  >
                    <div class="min-w-0 flex-1">
                      <p class="text-sm font-semibold text-gray-900">Additional Document</p>
                      <p class="mt-0.5 text-xs text-gray-500">PDF, DOC, DOCX, EML. You can select multiple files.</p>
                    </div>
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-sky-400 bg-white px-4 py-2 text-sm font-medium text-sky-600 hover:bg-sky-50" :class="{ 'opacity-50 pointer-events-none': uploadingDocs }">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                      </svg>
                      {{ uploadingDocs ? 'Uploading...' : 'Upload' }}
                      <input type="file" class="hidden" accept=".pdf,.doc,.docx,.eml" multiple @change="uploadFromInput" />
                    </label>
                  </div>
              </div>
            </div>
          </section>

            <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4">
              <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="goBack">Cancel</button>
              <button
                type="button"
                :disabled="saving"
                class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                @click="save"
              >
              {{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
    </div>
  </div>
</template>
