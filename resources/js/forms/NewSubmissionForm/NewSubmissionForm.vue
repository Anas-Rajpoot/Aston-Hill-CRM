<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import specialRequestsApi from '@/services/specialRequestsApi'
import { useFormErrors } from '@/composables/useFormErrors'
import { useSessionFormState } from '@/composables/useSessionFormState'

const form = ref({
  company_name: '',
  account_number: '',
  request_type: '',
  status: '',
  manager_id: '',
  team_leader_id: '',
  sales_agent_id: '',
  complete_address: '',
  special_instruction: '',
})
const { restoreState, clearState } = useSessionFormState('submission.special-request.step1', form)

const options = ref({
  managers: [],
  team_leaders: [],
  sales_agents: [],
})

const REQUEST_TYPE_OPTIONS = [
  { value: '', label: 'Select' },
  { value: 'General', label: 'General' },
  { value: 'Support', label: 'Support' },
  { value: 'Relocation', label: 'Relocation' },
  { value: 'Renewal', label: 'Renewal' },
  { value: 'Other', label: 'Other' },
]

const STATUS_OPTIONS = [
  { value: '', label: 'Select' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
]

const documentFiles = ref([null])
const submitting = ref(false)
const successMessage = ref('')
const loading = ref(true)
const settingFromChild = ref(false)
const settingFromSalesAgent = ref(false)
const errorSummaryRef = ref(null)

const { errors, generalMessage, setErrors, clearErrors, clearFieldError, getError } = useFormErrors()

const filteredTeamLeaders = computed(() => {
  const mid = form.value.manager_id
  if (!mid) return options.value.team_leaders
  return options.value.team_leaders.filter((t) => String(t.manager_id) === String(mid))
})

const filteredSalesAgents = computed(() => {
  const tlId = form.value.team_leader_id
  if (!tlId) return options.value.sales_agents
  return options.value.sales_agents.filter((sa) => String(sa.team_leader_id) === String(tlId))
})

watch(() => form.value.manager_id, () => {
  if (settingFromChild.value) { nextTick(() => { settingFromChild.value = false }); return }
  form.value.team_leader_id = ''
  form.value.sales_agent_id = ''
})

watch(() => form.value.team_leader_id, (id) => {
  if (id) {
    const tl = options.value.team_leaders.find((u) => String(u.id) === String(id))
    if (tl?.manager_id != null) { settingFromChild.value = true; form.value.manager_id = String(tl.manager_id); nextTick(() => { settingFromChild.value = false }) }
    if (!settingFromSalesAgent.value) form.value.sales_agent_id = ''
  } else { form.value.manager_id = '' }
})

watch(() => form.value.sales_agent_id, (id) => {
  if (id) {
    const sa = options.value.sales_agents.find((u) => String(u.id) === String(id))
    if (sa) {
      settingFromSalesAgent.value = true; settingFromChild.value = true
      if (sa.team_leader_id != null) form.value.team_leader_id = String(sa.team_leader_id)
      if (sa.manager_id != null) form.value.manager_id = String(sa.manager_id)
      nextTick(() => { settingFromSalesAgent.value = false; settingFromChild.value = false })
    }
  } else { form.value.team_leader_id = ''; form.value.manager_id = '' }
})

onMounted(async () => {
  loading.value = true
  try {
    const res = await specialRequestsApi.getTeamOptions()
    const data = res?.data ?? res ?? {}
    options.value = {
      managers: data.managers ?? [],
      team_leaders: data.team_leaders ?? [],
      sales_agents: data.sales_agents ?? [],
    }
    restoreState()
  } catch { /* silent */ }
  finally { loading.value = false }
})

function addDocument() { documentFiles.value.push(null) }

function removeDocument(index) {
  if (documentFiles.value.length <= 1) return
  documentFiles.value.splice(index, 1)
}

function onFileChange(index, event) {
  const file = event.target?.files?.[0]
  if (file) documentFiles.value[index] = file
}

function validateForm() {
  const err = {}
  if (!form.value.company_name?.trim()) err.company_name = ['Company name is required.']
  if (!form.value.request_type?.trim()) err.request_type = ['Request type is required.']
  if (!form.value.manager_id) err.manager_id = ['Manager is required.']
  return Object.keys(err).length ? err : null
}

async function submit() {
  clearErrors()
  const frontendErrors = validateForm()
  if (frontendErrors) {
    errors.value = frontendErrors
    generalMessage.value = 'Please correct the errors below.'
    nextTick(() => errorSummaryRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' }))
    return
  }
  submitting.value = true
  try {
    const fd = new FormData()
    fd.append('company_name', form.value.company_name.trim())
    fd.append('account_number', form.value.account_number?.trim() || '')
    fd.append('request_type', form.value.request_type.trim())
    if (form.value.status) fd.append('status', form.value.status)
    fd.append('complete_address', form.value.complete_address?.trim() || '')
    fd.append('special_instruction', form.value.special_instruction?.trim() || '')
    fd.append('manager_id', form.value.manager_id)
    if (form.value.team_leader_id) fd.append('team_leader_id', form.value.team_leader_id)
    if (form.value.sales_agent_id) fd.append('sales_agent_id', form.value.sales_agent_id)
    documentFiles.value.forEach((file) => { if (file) fd.append('documents[]', file) })
    await specialRequestsApi.store(fd)
    successMessage.value = 'Special request submitted successfully.'
    clearState()
    nextTick(() => { window.scrollTo(0, 0) })
  } catch (e) {
    setErrors(e)
    nextTick(() => errorSummaryRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' }))
  } finally {
    submitting.value = false
  }
}

function reset() {
  clearState()
  form.value = {
    company_name: '',
    account_number: '',
    request_type: '',
    status: '',
    manager_id: '',
    team_leader_id: '',
    sales_agent_id: '',
    complete_address: '',
    special_instruction: '',
  }
  documentFiles.value = [null]
  clearErrors()
  successMessage.value = ''
}

function startNewSubmission() {
  reset()
  window.scrollTo(0, 0)
}

const inputClass = (field) =>
  `mt-1 block w-full rounded border bg-white px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500 ${getError(field) ? 'border-red-500' : 'border-gray-300'}`
const selectClass = (field) =>
  `mt-1 block w-full rounded border bg-white px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500 ${getError(field) ? 'border-red-500' : 'border-gray-300'}`
</script>

<template>
  <div class="space-y-6">
    <!-- Success message -->
    <div v-if="successMessage" class="rounded-lg border border-green-200 bg-green-50 p-6 text-center">
      <svg class="mx-auto mb-3 h-12 w-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
      <p class="text-lg font-semibold text-green-800">{{ successMessage }}</p>
      <p class="mt-3 text-sm text-gray-600">Click the button below to start a new special request.</p>
      <button type="button" @click="startNewSubmission" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-green-700">New Special Request</button>
    </div>

    <div v-else class="!mt-0">
      <!-- Error summary -->
      <div v-if="generalMessage || Object.keys(errors).length" ref="errorSummaryRef" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4">
        <p class="text-sm font-medium text-red-800">{{ generalMessage }}</p>
      </div>

      <h3 class="text-base font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Primary Information</h3>
      <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Company Name <span class="text-red-500">*</span></label>
          <input v-model="form.company_name" type="text" placeholder="Enter company name" :class="inputClass('company_name')" @input="clearFieldError('company_name')" />
          <p v-if="getError('company_name')" class="mt-1 text-sm text-red-600">{{ getError('company_name') }}</p>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Account Number</label>
          <input v-model="form.account_number" type="text" placeholder="Enter account number" :class="inputClass('account_number')" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Request Type <span class="text-red-500">*</span></label>
          <select v-model="form.request_type" :class="selectClass('request_type')" @change="clearFieldError('request_type')">
            <option v-for="o in REQUEST_TYPE_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
          <p v-if="getError('request_type')" class="mt-1 text-sm text-red-600">{{ getError('request_type') }}</p>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
          <select v-model="form.status" :class="selectClass('status')">
            <option v-for="s in STATUS_OPTIONS" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
      </div>
      <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Manager <span class="text-red-500">*</span></label>
          <select v-model="form.manager_id" :class="selectClass('manager_id')" @change="clearFieldError('manager_id')">
            <option value="">Select</option>
            <option v-for="m in options.managers" :key="m.id" :value="String(m.id)">{{ m.name }}</option>
          </select>
          <p v-if="getError('manager_id')" class="mt-1 text-sm text-red-600">{{ getError('manager_id') }}</p>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Team Leader</label>
          <select v-model="form.team_leader_id" :class="selectClass('team_leader_id')" @change="clearFieldError('team_leader_id')">
            <option value="">Select</option>
            <option v-for="t in filteredTeamLeaders" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
          </select>
          <p v-if="getError('team_leader_id')" class="mt-1 text-sm text-red-600">{{ getError('team_leader_id') }}</p>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Sales Agent</label>
          <select v-model="form.sales_agent_id" :class="selectClass('sales_agent_id')" @change="clearFieldError('sales_agent_id')">
            <option value="">Select</option>
            <option v-for="s in filteredSalesAgents" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
          </select>
          <p v-if="getError('sales_agent_id')" class="mt-1 text-sm text-red-600">{{ getError('sales_agent_id') }}</p>
        </div>
      </div>

      <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Complete Address</label>
          <textarea
            v-model="form.complete_address"
            rows="3"
            placeholder="Enter complete address"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Any Special Instruction</label>
          <textarea
            v-model="form.special_instruction"
            rows="3"
            placeholder="Enter Special Instruction"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          />
        </div>
      </div>

      <div class="mt-6">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold text-gray-900">Add Document</h3>
          <button
            type="button"
            class="inline-flex items-center gap-1 text-sm font-medium text-green-600 hover:text-green-700"
            @click="addDocument"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Document
          </button>
        </div>
        <div class="space-y-3">
          <div
            v-for="(file, index) in documentFiles"
            :key="index"
            class="flex min-w-0 items-center gap-3 rounded-lg border px-4 py-3 shadow-sm"
            :class="file ? 'border-green-300 bg-green-50' : 'border-gray-300 bg-white'"
          >
            <div class="shrink-0" :class="file ? 'text-green-600' : 'text-gray-400'">
              <svg v-if="file" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <svg v-else class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium truncate" :class="file ? 'text-green-800' : 'text-gray-900'">
                {{ file ? file.name : 'Additional Document' }}
              </p>
              <p v-if="file" class="text-xs text-green-600 mt-0.5">{{ (file.size / 1024).toFixed(1) }} KB — File selected</p>
              <p v-else class="text-xs text-gray-500">PDF, DOC, DOCX, EML. You can select multiple files.</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <label class="cursor-pointer">
                <input
                  type="file"
                  class="hidden"
                  accept=".pdf,.doc,.docx,.eml"
                  @change="onFileChange(index, $event)"
                />
                <span class="inline-flex items-center gap-1 rounded-lg border border-green-600 px-4 py-1.5 text-sm font-medium text-green-600 hover:bg-green-50">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                  </svg>
                  Upload
                </span>
              </label>
              <button
                v-if="documentFiles.length > 1"
                type="button"
                class="rounded-lg border border-red-200 bg-red-50 p-1.5 text-red-600 hover:bg-red-100"
                @click="removeDocument(index)"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-gray-200 pt-4">
        <button
          type="button"
          :disabled="submitting"
          class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-70"
          @click="submit"
        >
          <span v-if="submitting" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
          {{ submitting ? 'Saving…' : 'Save' }}
        </button>
        <button
          type="button"
          class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
          @click="reset"
        >
          Reset
        </button>
      </div>
    </div><!-- end v-else -->
  </div>
</template>
