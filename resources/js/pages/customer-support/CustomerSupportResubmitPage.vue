<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import customerSupportApi from '@/services/customerSupportApi'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { useFormErrors } from '@/composables/useFormErrors'
import { formatTeamLabel } from '@/composables/useTeamLabel'

const ISSUE_CATEGORIES = [
  'Internet / Landline Issues',
  'SIM Cards Not Working',
  'Billing Issues',
  'Plan / Benefits Issue',
  'Documents / Contract Renewal',
  'Upgrade / Downgrade / Cancellation',
  'Hard Cap / Roaming Activation',
  'B2B Portal Issue',
  'Other Request',
]

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const saving = ref(false)
const submission = ref(null)
const newFiles = ref([])
const fileInput = ref(null)
const errorSummaryRef = ref(null)
const settingFromChild = ref(false)
const settingFromSalesAgent = ref(false)
const initialFormLoad = ref(false)

const id = computed(() => {
  const p = route.params.id
  return p != null ? Number(p) : null
})

const { errors, generalMessage, setErrors, clearErrors, clearFieldError, getError } = useFormErrors()

const form = ref({
  issue_category: '',
  company_name: '',
  account_number: '',
  contact_number: '',
  issue_description: '',
  manager_id: '',
  team_leader_id: '',
  sales_agent_id: '',
})

const managers = ref([])
const teamLeaders = ref([])
const salesAgents = ref([])
const teamLabels = ref({
  manager: 'Manager Name',
  team_leader: 'Team Leader Name',
  sales_agent: 'Sales Agent Name',
})

const filteredTeamLeaders = computed(() => {
  const mid = form.value.manager_id
  if (!mid) return teamLeaders.value
  return teamLeaders.value.filter((t) => String(t.manager_id) === String(mid))
})

const filteredSalesAgents = computed(() => {
  const tlId = form.value.team_leader_id
  if (!tlId) return salesAgents.value
  return salesAgents.value.filter((s) => String(s.team_leader_id) === String(tlId))
})

watch(
  () => form.value.manager_id,
  () => {
    if (initialFormLoad.value) return
    if (settingFromChild.value) {
      nextTick(() => { settingFromChild.value = false })
      return
    }
    form.value.team_leader_id = ''
    form.value.sales_agent_id = ''
  }
)

watch(
  () => form.value.team_leader_id,
  (tid) => {
    if (initialFormLoad.value) return
    if (tid) {
      const tl = teamLeaders.value.find((u) => String(u.id) === String(tid))
      if (tl?.manager_id != null) {
        settingFromChild.value = true
        form.value.manager_id = String(tl.manager_id)
        nextTick(() => { settingFromChild.value = false })
      }
      if (!settingFromSalesAgent.value) form.value.sales_agent_id = ''
    } else {
      form.value.manager_id = ''
    }
  }
)

watch(
  () => form.value.sales_agent_id,
  (sid) => {
    if (initialFormLoad.value) return
    if (sid) {
      const sa = salesAgents.value.find((u) => String(u.id) === String(sid))
      if (sa) {
        settingFromSalesAgent.value = true
        settingFromChild.value = true
        if (sa.team_leader_id != null) form.value.team_leader_id = String(sa.team_leader_id)
        if (sa.manager_id != null) form.value.manager_id = String(sa.manager_id)
        nextTick(() => {
          settingFromSalesAgent.value = false
          settingFromChild.value = false
        })
      }
    } else {
      form.value.team_leader_id = ''
      form.value.manager_id = ''
    }
  }
)

function attachmentDisplayName(att) {
  return att?.file_name ?? att?.original_name ?? 'Attachment'
}

async function downloadAttachment(index) {
  if (!submission.value?.id) return
  const att = submission.value.attachments?.[index]
  const name = attachmentDisplayName(att) || 'attachment'
  try {
    const { data } = await api.get(`/customer-support/${submission.value.id}/attachments/${index}/download`, { responseType: 'blob' })
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = name
    a.rel = 'noopener'
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  } catch {
    window.open(`/api/customer-support/${submission.value.id}/attachments/${index}/download`, '_blank', 'noopener')
  }
}

async function load() {
  if (!id.value) return
  loading.value = true
  submission.value = null
  clearErrors()
  initialFormLoad.value = true
  try {
    const [subData, teamData] = await Promise.all([
      customerSupportApi.getSubmission(id.value),
      customerSupportApi.getTeamOptions().then((r) => r?.data ?? {}).catch(() => ({})),
    ])

    if (subData.status === 'approved') {
      router.replace(`/customer-support/${id.value}`)
      return
    }

    submission.value = subData
    managers.value = teamData.managers ?? []
    teamLeaders.value = teamData.team_leaders ?? []
    salesAgents.value = teamData.sales_agents ?? []
    if (teamData.labels) {
      teamLabels.value = { ...teamLabels.value, ...teamData.labels }
    }

    const managerId = subData.manager_id ?? subData.manager?.id
    const teamLeaderId = subData.team_leader_id ?? subData.team_leader?.id
    const salesAgentId = subData.sales_agent_id ?? subData.sales_agent?.id
    form.value = {
      issue_category: subData.issue_category ?? '',
      company_name: subData.company_name ?? '',
      account_number: subData.account_number ?? '',
      contact_number: subData.contact_number ?? '',
      issue_description: subData.issue_description ?? '',
      manager_id: managerId != null ? String(managerId) : '',
      team_leader_id: teamLeaderId != null ? String(teamLeaderId) : '',
      sales_agent_id: salesAgentId != null ? String(salesAgentId) : '',
    }
    newFiles.value = []
    nextTick(() => {
      initialFormLoad.value = false
    })
  } catch {
    submission.value = null
    initialFormLoad.value = false
  } finally {
    loading.value = false
  }
}

function isEmptyUserSelect(val) {
  if (val == null || val === '') return true
  const n = Number(val)
  return Number.isNaN(n) || n < 1
}

function validatePhone(value) {
  if (!value) return 'Contact number is required.'
  if (/\s/.test(value)) return 'Contact number must not contain spaces.'
  if (!/^\d+$/.test(value)) return 'Contact number must contain only digits.'
  if (!value.startsWith('971')) return 'Contact number must start with 971.'
  if (value.length !== 12) return 'Contact number must be exactly 12 digits.'
  return null
}

function onPhoneInput(field, event) {
  const raw = event.target.value.replace(/\D/g, '')
  form.value[field] = raw
  clearFieldError(field)
}

function validateForm() {
  const err = {}
  if (!form.value.issue_category?.trim()) err.issue_category = ['Please select an issue category.']
  if (!form.value.company_name?.trim()) err.company_name = ['Company name is required.']
  const phoneErr = validatePhone(form.value.contact_number?.trim())
  if (phoneErr) err.contact_number = [phoneErr]
  if (!form.value.issue_description?.trim()) err.issue_description = ['Issue description is required.']
  if (isEmptyUserSelect(form.value.manager_id)) err.manager_id = [`${formatTeamLabel(teamLabels.value.manager || 'manager')} is required.`]
  if (isEmptyUserSelect(form.value.team_leader_id)) err.team_leader_id = [`${formatTeamLabel(teamLabels.value.team_leader || 'team_leader')} is required.`]
  if (isEmptyUserSelect(form.value.sales_agent_id)) err.sales_agent_id = [`${formatTeamLabel(teamLabels.value.sales_agent || 'sales_agent')} is required.`]
  return Object.keys(err).length ? err : null
}

function buildPayload() {
  const f = form.value
  return {
    issue_category: f.issue_category?.trim() || null,
    company_name: f.company_name?.trim() || null,
    account_number: f.account_number?.trim() || null,
    contact_number: f.contact_number?.trim() || null,
    issue_description: f.issue_description?.trim() || null,
    manager_id: f.manager_id ? Number(f.manager_id) : null,
    team_leader_id: f.team_leader_id ? Number(f.team_leader_id) : null,
    sales_agent_id: f.sales_agent_id ? Number(f.sales_agent_id) : null,
  }
}

function triggerFileSelect() {
  fileInput.value?.click()
}

function onFileChange(e) {
  const files = e.target?.files
  if (!files?.length) return
  newFiles.value = [...newFiles.value, ...Array.from(files)]
  e.target.value = ''
}

function removeNewFile(index) {
  newFiles.value = newFiles.value.filter((_, i) => i !== index)
}

function goBack() {
  router.push('/customer-support')
}

async function resubmit() {
  if (!id.value) return
  clearErrors()
  const frontendErrors = validateForm()
  if (frontendErrors) {
    errors.value = frontendErrors
    generalMessage.value = 'Please correct the errors below.'
    nextTick(() => {
      errorSummaryRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
    })
    return
  }
  saving.value = true
  try {
    await customerSupportApi.resubmit(id.value, buildPayload())
    if (newFiles.value.length > 0) {
      await customerSupportApi.uploadAttachments(id.value, newFiles.value)
    }
    router.push(`/customer-support/${id.value}`)
  } catch (e) {
    setErrors(e)
    nextTick(() => {
      errorSummaryRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
    })
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  load()
})

const inputClass = (field) =>
  `w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500 ${getError(field) ? 'border-red-500' : 'border-gray-300'}`
const selectClass = (field) =>
  `w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 focus:border-green-500 focus:ring-1 focus:ring-green-500 ${getError(field) ? 'border-red-500' : 'border-gray-300'}`
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white p-0">
    <div class="w-full">
      <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="px-4 py-4 sm:px-5">
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-xl font-semibold text-gray-900">Resubmit Customer Support Request</h1>
            <Breadcrumbs />
            <div class="ml-auto">
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                @click="goBack"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back to List
              </button>
            </div>
          </div>
        </div>
        <div class="border-t border-gray-200" />

        <div v-if="loading" class="flex items-center justify-center px-4 py-16 sm:px-5">
          <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          <span class="ml-3 text-gray-600">Loading...</span>
        </div>

        <div v-else-if="!submission" class="px-4 py-8 text-center text-gray-500 sm:px-5">
          Unable to load request. It may be in an approved state or you may not have permission.
        </div>

        <form v-else class="space-y-8 px-4 py-5 sm:px-5" @submit.prevent="resubmit">
          <!-- Validation summary -->
          <div
            ref="errorSummaryRef"
            v-if="generalMessage || Object.keys(errors).length"
            class="rounded-lg border border-red-200 bg-red-50 p-4"
          >
            <p class="text-sm font-medium text-red-800">{{ generalMessage }}</p>
            <ul class="mt-2 list-inside list-disc space-y-0.5 text-sm text-red-700">
              <li v-for="(msgs, field) in errors" :key="field">{{ getError(field) }}</li>
            </ul>
          </div>

          <!-- Primary Information -->
          <div class="!mt-0">
            <h3 class="border-b border-gray-200 pb-2 mb-4 text-base font-semibold text-gray-900">Primary Information</h3>
            <div>
              <label class="mb-2 block text-sm font-medium text-gray-700">
                Issue Category <span class="text-red-500">*</span>
              </label>
              <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-3">
                <label
                  v-for="cat in ISSUE_CATEGORIES"
                  :key="cat"
                  class="flex cursor-pointer items-center rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 transition hover:border-green-400 hover:bg-gray-50 has-[:checked]:border-green-500 has-[:checked]:bg-green-50 has-[:checked]:ring-1 has-[:checked]:ring-green-500"
                  :class="{ 'border-red-500': getError('issue_category') }"
                >
                  <input
                    v-model="form.issue_category"
                    type="radio"
                    :value="cat"
                    class="h-4 w-4 border-gray-300 text-green-600 focus:ring-green-500"
                    @change="clearFieldError('issue_category')"
                  />
                  <span class="ml-2">{{ cat }}</span>
                </label>
              </div>
              <p v-if="getError('issue_category')" class="mt-1 text-sm text-red-600">{{ getError('issue_category') }}</p>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                  Company Name <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="form.company_name"
                  type="text"
                  placeholder="Enter company name"
                  :class="inputClass('company_name')"
                  @input="clearFieldError('company_name')"
                />
                <p v-if="getError('company_name')" class="mt-1 text-sm text-red-600">{{ getError('company_name') }}</p>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Account Number</label>
                <input
                  v-model="form.account_number"
                  type="text"
                  placeholder="Enter account number"
                  :class="inputClass('account_number')"
                  @input="clearFieldError('account_number')"
                />
                <p v-if="getError('account_number')" class="mt-1 text-sm text-red-600">{{ getError('account_number') }}</p>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                  Contact Number <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="form.contact_number"
                  type="text"
                  maxlength="12"
                  placeholder="971XXXXXXXXX"
                  :class="inputClass('contact_number')"
                  @input="onPhoneInput('contact_number', $event)"
                />
                <p v-if="getError('contact_number')" class="mt-1 text-sm text-red-600">{{ getError('contact_number') }}</p>
              </div>
            </div>

            <!-- Issue Description + Attachments -->
            <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px] xl:grid-cols-[1fr_260px] lg:items-start">
              <div class="min-w-0">
                <label class="mb-1 block text-sm font-semibold text-gray-900">
                  Issue Description <span class="text-red-500">*</span>
                </label>
                <textarea
                  v-model="form.issue_description"
                  rows="5"
                  placeholder="Provide detailed description of the issue..."
                  :class="inputClass('issue_description')"
                  @input="clearFieldError('issue_description')"
                />
                <p v-if="getError('issue_description')" class="mt-1 text-sm text-red-600">{{ getError('issue_description') }}</p>
              </div>
              <div class="flex max-w-full flex-col gap-2">
                <label class="block text-sm font-semibold text-gray-900">Attachments</label>
                <!-- Existing attachments -->
                <div
                  v-for="(att, idx) in submission.attachments"
                  :key="'ex-' + idx"
                  class="flex min-w-0 items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 shadow-sm"
                >
                  <div class="shrink-0 text-gray-500">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-medium text-gray-900">{{ attachmentDisplayName(att) }}</p>
                    <p v-if="att.file_size" class="mt-0.5 text-xs text-gray-500">{{ att.file_size }}</p>
                  </div>
                  <button type="button" class="shrink-0 rounded p-1 text-blue-600 hover:bg-blue-50" @click="downloadAttachment(idx)">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                  </button>
                </div>
                <!-- New files -->
                <div v-for="(f, idx) in newFiles" :key="'new-' + idx" class="flex min-w-0 items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2">
                  <span class="min-w-0 flex-1 truncate text-xs text-gray-900">{{ f.name }}</span>
                  <button type="button" class="shrink-0 text-xs text-red-600 hover:underline" @click="removeNewFile(idx)">Remove</button>
                </div>
                <!-- Add more -->
                <input ref="fileInput" type="file" multiple accept="image/*,.pdf,.doc,.docx,.csv" class="hidden" @change="onFileChange" />
                <button
                  type="button"
                  class="inline-flex items-center gap-1.5 text-sm font-medium text-green-600 hover:text-green-700 hover:underline"
                  @click="triggerFileSelect"
                >
                  <span class="text-base leading-none">+</span>
                  Add Document
                </button>
              </div>
            </div>
          </div>

          <!-- Team Information -->
          <div>
            <h3 class="border-b border-gray-200 pb-2 text-base font-semibold text-gray-800">Team Information</h3>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                  {{ formatTeamLabel(teamLabels.manager || 'manager') || 'Manager' }} Name <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="form.manager_id"
                  :class="selectClass('manager_id')"
                  @change="clearFieldError('manager_id')"
                >
                  <option value="">Select {{ formatTeamLabel(teamLabels.manager || 'manager') || 'Manager' }}</option>
                  <option v-for="u in managers" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
                <p v-if="getError('manager_id')" class="mt-1 text-sm text-red-600">{{ getError('manager_id') }}</p>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                  {{ formatTeamLabel(teamLabels.team_leader || 'team_leader') }} Name <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="form.team_leader_id"
                  :class="selectClass('team_leader_id')"
                  @change="clearFieldError('team_leader_id')"
                >
                  <option value="">Select {{ formatTeamLabel(teamLabels.team_leader || 'team_leader') }}</option>
                  <option v-for="u in filteredTeamLeaders" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
                <p v-if="getError('team_leader_id')" class="mt-1 text-sm text-red-600">{{ getError('team_leader_id') }}</p>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                  {{ formatTeamLabel(teamLabels.sales_agent || 'sales_agent') }} Name <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="form.sales_agent_id"
                  :class="selectClass('sales_agent_id')"
                  @change="clearFieldError('sales_agent_id')"
                >
                  <option value="">Select {{ formatTeamLabel(teamLabels.sales_agent || 'sales_agent') }}</option>
                  <option v-for="u in filteredSalesAgents" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
                <p v-if="getError('sales_agent_id')" class="mt-1 text-sm text-red-600">{{ getError('sales_agent_id') }}</p>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4">
            <button
              type="button"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
              @click="goBack"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-green-700 disabled:opacity-50"
              :disabled="saving"
            >
              {{ saving ? 'Resubmitting...' : 'Resubmit' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
