<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import api from '@/services/customerSupportApi'
import { useFormErrors } from '@/composables/useFormErrors'
import { formatTeamLabel } from '@/composables/useTeamLabel'
import { useSessionFormState } from '@/composables/useSessionFormState'
import { useAuthStore } from '@/stores/auth'
import { documentUploadAcceptAttr } from '@/lib/documentUpload'

const docUploadAccept = documentUploadAcceptAttr()

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

const form = ref({
  issue_category: '',
  company_name: '',
  account_number: '',
  contact_number: '',
  alternate_contact_number: '',
  issue_description: '',
  attachment_1: null,
  attachment_2: null,
  manager_id: '',
  team_leader_id: '',
  sales_agent_id: '',
})
const { restoreState, clearState } = useSessionFormState('submission.customer-support.step1', form, {
  omitKeys: ['attachment_1', 'attachment_2'],
})

const managers = ref([])
const teamLeaders = ref([])
const salesAgents = ref([])
const settingFromChild = ref(false)
const settingFromSalesAgent = ref(false)
const auth = useAuthStore()
const teamLabels = ref({
  manager: 'Manager Name',
  team_leader: 'Team Leader Name',
  sales_agent: 'Sales Agent Name',
})
const teamSectionCollapsed = ref(false)
const loading = ref(true)
const submitting = ref(false)
const savingDraft = ref(false)
const successMessage = ref('')
const fileInput1 = ref(null)
const fileInput2 = ref(null)
const errorSummaryRef = ref(null)
/** Public URL for submit icon (in public/images/) – use bound :src so Vite does not try to import */
const submitRequestIconUrl = '/images/submit-request-icon.png'
const currentSubmitterName = computed(() => auth.user?.name || '-')

const { errors, generalMessage, setErrors, clearErrors, clearFieldError, getError } = useFormErrors()

const pickFirstValue = (obj, keys) => {
  for (const key of keys) {
    const value = obj?.[key]
    if (value != null && value !== '') return String(value)
  }
  return ''
}

const resolveTeamLeaderManagerId = (teamLeader) => {
  const direct = pickFirstValue(teamLeader, ['manager_id', 'managerId'])
  if (direct) return direct
  const reportsTo = pickFirstValue(teamLeader, ['reports_to', 'reportsTo'])
  if (reportsTo && managers.value.some((m) => String(m.id) === reportsTo)) return reportsTo
  return ''
}

const filteredTeamLeaders = computed(() => {
  const mid = form.value.manager_id
  if (!mid) return teamLeaders.value
  const filtered = teamLeaders.value.filter((t) => resolveTeamLeaderManagerId(t) === String(mid))
  // Fallback to all role users when hierarchy mapping is missing/incomplete.
  return filtered.length ? filtered : teamLeaders.value
})

const filteredSalesAgents = computed(() => {
  const tlId = form.value.team_leader_id
  const managerId = form.value.manager_id
  const teamLeaderIdsUnderManager = new Set(
    teamLeaders.value
      .filter((t) => managerId && resolveTeamLeaderManagerId(t) === String(managerId))
      .map((t) => String(t.id))
  )

  const resolveSalesAgentTeamLeaderId = (salesAgent) => {
    const direct = pickFirstValue(salesAgent, ['team_leader_id', 'teamLeaderId'])
    const reportsTo = pickFirstValue(salesAgent, ['reports_to', 'reportsTo'])
    if (direct) return direct
    if (reportsTo) {
      const asTeamLeader = teamLeaders.value.some((t) => String(t.id) === String(reportsTo))
      if (asTeamLeader) return String(reportsTo)
    }
    return ''
  }

  const resolveSalesAgentManagerId = (salesAgent) => {
    const direct = pickFirstValue(salesAgent, ['manager_id', 'managerId'])
    const reportsTo = pickFirstValue(salesAgent, ['reports_to', 'reportsTo'])
    if (direct) return direct
    if (reportsTo) {
      const asManager = managers.value.some((m) => String(m.id) === String(reportsTo))
      if (asManager) return String(reportsTo)
    }
    const teamLeaderIdFromAgent = resolveSalesAgentTeamLeaderId(salesAgent)
    if (teamLeaderIdFromAgent) {
      const teamLeader = teamLeaders.value.find((t) => String(t.id) === String(teamLeaderIdFromAgent))
      const managerIdFromLeader = resolveTeamLeaderManagerId(teamLeader)
      if (managerIdFromLeader) return managerIdFromLeader
    }
    return ''
  }

  if (tlId) {
    const filteredByTeamLeader = salesAgents.value.filter((salesAgent) => resolveSalesAgentTeamLeaderId(salesAgent) === String(tlId))
    return filteredByTeamLeader.length ? filteredByTeamLeader : salesAgents.value
  }
  if (managerId) {
    const filteredByManager = salesAgents.value.filter((salesAgent) => {
      const resolvedManagerId = resolveSalesAgentManagerId(salesAgent)
      const resolvedTeamLeaderId = resolveSalesAgentTeamLeaderId(salesAgent)
      return resolvedManagerId === String(managerId) || teamLeaderIdsUnderManager.has(resolvedTeamLeaderId)
    })
    return filteredByManager.length ? filteredByManager : salesAgents.value
  }
  return salesAgents.value
})

const deriveSalesHierarchy = (salesAgent) => {
  let teamLeaderId = pickFirstValue(salesAgent, ['team_leader_id', 'teamLeaderId'])
  let managerId = pickFirstValue(salesAgent, ['manager_id', 'managerId'])
  const reportsTo = pickFirstValue(salesAgent, ['reports_to', 'reportsTo'])

  if (!teamLeaderId && reportsTo && teamLeaders.value.some((u) => String(u.id) === reportsTo)) {
    teamLeaderId = reportsTo
  }
  if (!managerId && reportsTo && managers.value.some((u) => String(u.id) === reportsTo)) {
    managerId = reportsTo
  }
  if (!managerId && teamLeaderId) {
    const teamLeader = teamLeaders.value.find((u) => String(u.id) === String(teamLeaderId))
    managerId = resolveTeamLeaderManagerId(teamLeader)
  }
  if (!teamLeaderId && managerId) {
    const leaders = teamLeaders.value.filter((u) => resolveTeamLeaderManagerId(u) === String(managerId))
    if (leaders.length === 1) teamLeaderId = String(leaders[0].id)
  }
  return { teamLeaderId, managerId }
}

watch(
  () => form.value.manager_id,
  () => {
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
  (id) => {
    if (id) {
      const tl = teamLeaders.value.find((u) => String(u.id) === String(id))
      const managerId = resolveTeamLeaderManagerId(tl)
      if (managerId) {
        settingFromChild.value = true
        form.value.manager_id = String(managerId)
        nextTick(() => { settingFromChild.value = false })
      }
      if (!settingFromSalesAgent.value) form.value.sales_agent_id = ''
    } else if (!settingFromSalesAgent.value) {
      form.value.sales_agent_id = ''
    }
  }
)

watch(
  () => form.value.sales_agent_id,
  (id) => {
    if (id) {
      const sa = salesAgents.value.find((u) => String(u.id) === String(id))
      if (sa) {
        const resolved = deriveSalesHierarchy(sa)
        settingFromSalesAgent.value = true
        settingFromChild.value = true
        if (resolved.teamLeaderId) form.value.team_leader_id = resolved.teamLeaderId
        if (resolved.managerId) form.value.manager_id = resolved.managerId
        nextTick(() => {
          settingFromSalesAgent.value = false
          settingFromChild.value = false
        })
      }
    }
  }
)

onMounted(async () => {
  loading.value = true
  try {
    await auth.fetchUser()
    const { data } = await api.getTeamOptions(true)
    managers.value = data.managers || []
    teamLeaders.value = data.team_leaders || []
    salesAgents.value = data.sales_agents || []
    if (data.labels) {
      teamLabels.value = { ...teamLabels.value, ...data.labels }
    }
    restoreState()
  } catch (e) {
    setErrors(e)
  } finally {
    loading.value = false
  }
})

function buildPayload() {
  const f = form.value
  const mid = f.manager_id && Number(f.manager_id) >= 1 ? Number(f.manager_id) : null
  const tlid = f.team_leader_id && Number(f.team_leader_id) >= 1 ? Number(f.team_leader_id) : null
  const said = f.sales_agent_id && Number(f.sales_agent_id) >= 1 ? Number(f.sales_agent_id) : null
  return {
    issue_category: f.issue_category?.trim() ?? '',
    company_name: f.company_name?.trim() ?? '',
    account_number: f.account_number?.trim() ?? '',
    contact_number: f.contact_number?.trim() ?? '',
    alternate_contact_number: f.alternate_contact_number?.trim() ?? '',
    issue_description: f.issue_description?.trim() ?? '',
    attachment_1: f.attachment_1 instanceof File ? f.attachment_1 : null,
    attachment_2: f.attachment_2 instanceof File ? f.attachment_2 : null,
    manager_id: mid,
    team_leader_id: tlid,
    sales_agent_id: said,
  }
}

/** Returns true if value is missing or invalid for a required user select (empty, "0", or 0). */
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
  return Object.keys(err).length ? err : null
}

async function submit() {
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
  successMessage.value = ''
  submitting.value = true
  try {
    const payload = buildPayload()
    if (isEmptyUserSelect(payload.manager_id)) {
      errors.value = {
        ...(isEmptyUserSelect(payload.manager_id) && { manager_id: [`${formatTeamLabel(teamLabels.value.manager || 'manager')} is required.`] }),
      }
      generalMessage.value = 'Please correct the errors below.'
      nextTick(() => errorSummaryRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' }))
      return
    }
    await api.store(payload, true)
    successMessage.value = 'Your customer support request has been submitted successfully. Back Office will review and process accordingly.'
    nextTick(() => {
      window.scrollTo(0, 0)
      document.documentElement.scrollTop = 0
      document.body.scrollTop = 0
    })
    clearState()
    form.value = {
      issue_category: '',
      company_name: '',
      account_number: '',
      contact_number: '',
      alternate_contact_number: '',
      issue_description: '',
      attachment_1: null,
      attachment_2: null,
      manager_id: '',
      team_leader_id: '',
      sales_agent_id: '',
    }
    if (fileInput1.value) fileInput1.value.value = ''
    if (fileInput2.value) fileInput2.value.value = ''
  } catch (e) {
    setErrors(e)
    nextTick(() => {
      errorSummaryRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
    })
  } finally {
    submitting.value = false
  }
}

async function saveDraft() {
  clearErrors()
  const frontendErrors = validateForm()
  if (frontendErrors) {
    errors.value = frontendErrors
    generalMessage.value = 'Please correct the errors below.'
    nextTick(() => errorSummaryRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' }))
    return
  }
  savingDraft.value = true
  try {
    const payload = buildPayload()
    await api.store(payload, false)
  } catch (e) {
    setErrors(e)
    nextTick(() => {
      errorSummaryRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
    })
  } finally {
    savingDraft.value = false
  }
}

function cancel() {
  clearState()
  form.value = {
    issue_category: '',
    company_name: '',
    account_number: '',
    contact_number: '',
    alternate_contact_number: '',
    issue_description: '',
    attachment_1: null,
    attachment_2: null,
    manager_id: '',
    team_leader_id: '',
    sales_agent_id: '',
  }
  clearErrors()
  successMessage.value = ''
  if (fileInput1.value) fileInput1.value.value = ''
  if (fileInput2.value) fileInput2.value.value = ''
}

function startNewSubmission() {
  cancel()
  window.scrollTo(0, 0)
}

function onFileChange(slot, event) {
  const file = event.target?.files?.[0]
  if (slot === 1) form.value.attachment_1 = file || null
  else form.value.attachment_2 = file || null
}

const inputClass = (field) =>
  `w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary ${getError(field) ? 'border-red-500' : 'border-gray-300'}`
const selectClass = (field) =>
  `w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary ${getError(field) ? 'border-red-500' : 'border-gray-300'}`
</script>

<template>
  <div class="space-y-6">
    <!-- Loading state (same as Lead Submissions) -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="h-8 w-8 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
      </svg>
      <span class="ml-3 text-gray-600">Loading...</span>
    </div>

    <template v-else>
    <!-- Success message at top -->
    <div
      v-if="successMessage"
      class="rounded-lg border border-brand-primary-muted bg-brand-primary-light p-6 text-center"
    >
      <svg class="mx-auto mb-3 h-12 w-12 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <h3 class="text-lg font-semibold text-brand-primary-hover">Customer support submission completed</h3>
      <p class="mt-1 text-sm text-brand-primary">{{ successMessage }}</p>
      <p class="mt-3 text-sm text-gray-600">Click the button below to start a new customer support request.</p>
      <button
        type="button"
        @click="startNewSubmission"
        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-brand-primary-hover"
      >
        New Customer Support
      </button>
    </div>

    <form v-if="!successMessage" @submit.prevent="submit" class="space-y-6">
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
        <h3 class="text-base font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">
          Primary Information
        </h3>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">
              Company Name as per Trade License <span class="text-red-500">*</span>
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
            <label class="mb-1 block text-sm font-medium text-gray-700">
              Account Number
            </label>
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
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">
              Alternate Contact Number
            </label>
            <input
              v-model="form.alternate_contact_number"
              type="text"
              maxlength="12"
              placeholder="971XXXXXXXXX"
              :class="inputClass('alternate_contact_number')"
              @input="onPhoneInput('alternate_contact_number', $event)"
            />
            <p v-if="getError('alternate_contact_number')" class="mt-1 text-sm text-red-600">{{ getError('alternate_contact_number') }}</p>
          </div>
        </div>

        <div class="mt-4">
          <label class="mb-2 block text-sm font-medium text-gray-700">
            Issue Category <span class="text-red-500">*</span>
          </label>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-3">
            <label
              v-for="cat in ISSUE_CATEGORIES"
              :key="cat"
              class="flex cursor-pointer items-center rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 transition hover:border-brand-primary hover:bg-gray-50 has-[:checked]:border-brand-primary has-[:checked]:bg-brand-primary-light has-[:checked]:ring-1 has-[:checked]:ring-brand-primary"
              :class="{ 'border-red-500': getError('issue_category') }"
            >
              <input
                v-model="form.issue_category"
                type="radio"
                :value="cat"
                class="h-4 w-4 border-gray-300 text-brand-primary focus:ring-brand-primary"
                @change="clearFieldError('issue_category')"
              />
              <span class="ml-2">{{ cat }}</span>
            </label>
          </div>
          <p v-if="getError('issue_category')" class="mt-1 text-sm text-red-600">
            {{ getError('issue_category') }}
          </p>
        </div>

      </div>

      <!-- Team & Remarks -->
      <div>
        <button
          type="button"
          class="w-full flex items-center justify-between text-left text-base font-semibold text-gray-900 border-b border-gray-200 pb-2"
          :aria-expanded="(!teamSectionCollapsed).toString()"
          @click.prevent="teamSectionCollapsed = !teamSectionCollapsed"
        >
          <span>Team & Remarks</span>
          <div class="flex items-center gap-2 text-sm text-gray-500">
            <span>{{ teamSectionCollapsed ? 'Show' : 'Minimize' }}</span>
            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': teamSectionCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>
        </button>

        <div v-show="!teamSectionCollapsed" class="mt-4 space-y-4">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Submitter Name</label>
            <input :value="currentSubmitterName" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-gray-100 text-gray-700" readonly />
            </div>
            <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ formatTeamLabel(teamLabels.manager || 'manager') }} Name <span class="text-red-500">*</span></label>
            <select v-model="form.manager_id" :class="inputClass('manager_id')" @change="clearFieldError('manager_id')">
              <option value="">Select {{ formatTeamLabel(teamLabels.manager || 'manager') }}</option>
              <option v-for="u in managers" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
            </select>
            <p v-if="getError('manager_id')" class="mt-1 text-sm text-red-600">{{ getError('manager_id') }}</p>
            </div>
            <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ formatTeamLabel(teamLabels.team_leader || 'team_leader') }} Name</label>
            <select v-model="form.team_leader_id" :class="inputClass('team_leader_id')" @change="clearFieldError('team_leader_id')">
              <option value="">Select {{ formatTeamLabel(teamLabels.team_leader || 'team_leader') }}</option>
              <option v-for="u in filteredTeamLeaders" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
            </select>
            <p v-if="getError('team_leader_id')" class="mt-1 text-sm text-red-600">{{ getError('team_leader_id') }}</p>
            </div>
            <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ formatTeamLabel(teamLabels.sales_agent || 'sales_agent') }} Name</label>
            <select v-model="form.sales_agent_id" :class="inputClass('sales_agent_id')" @change="clearFieldError('sales_agent_id')">
              <option value="">Select {{ formatTeamLabel(teamLabels.sales_agent || 'sales_agent') }}</option>
              <option v-for="u in filteredSalesAgents" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
            </select>
            <p v-if="getError('sales_agent_id')" class="mt-1 text-sm text-red-600">{{ getError('sales_agent_id') }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_260px] lg:items-stretch">
            <div class="min-w-0">
              <label class="mb-1 block text-sm font-semibold text-gray-900">
                Issue Description <span class="text-red-500">*</span>
              </label>
              <textarea
                v-model="form.issue_description"
                rows="3"
                placeholder="Provide detailed description of the issue..."
                :class="`${inputClass('issue_description')} h-24 resize-none`"
                @input="clearFieldError('issue_description')"
              />
              <p v-if="getError('issue_description')" class="mt-1 text-sm text-red-600">
                {{ getError('issue_description') }}
              </p>
            </div>
            <div class="flex max-w-full flex-col gap-2">
              <label class="block text-sm font-semibold text-gray-900">Attachments</label>
              <div class="h-24 flex flex-col justify-between gap-2">
                <div class="flex min-w-0 items-center gap-2 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 shadow-sm">
                  <div class="shrink-0 text-gray-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-medium text-gray-900">Attachment 1</p>
                    <p v-if="form.attachment_1" class="truncate text-xs text-gray-500">{{ form.attachment_1.name }}</p>
                  </div>
                  <label class="shrink-0 cursor-pointer">
                    <input
                      ref="fileInput1"
                      type="file"
                      class="hidden"
                      :accept="docUploadAccept"
                      @change="onFileChange(1, $event)"
                    />
                    <span class="inline-flex items-center gap-1 rounded-lg bg-brand-primary px-2 py-1 text-xs font-medium text-white shadow-sm hover:bg-brand-primary-hover">
                      <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                      </svg>
                      Upload
                    </span>
                  </label>
                </div>
                <div class="flex min-w-0 items-center gap-2 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 shadow-sm">
                  <div class="shrink-0 text-gray-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-medium text-gray-900">Attachment 2</p>
                    <p v-if="form.attachment_2" class="truncate text-xs text-gray-500">{{ form.attachment_2.name }}</p>
                  </div>
                  <label class="shrink-0 cursor-pointer">
                    <input
                      ref="fileInput2"
                      type="file"
                      class="hidden"
                      :accept="docUploadAccept"
                      @change="onFileChange(2, $event)"
                    />
                    <span class="inline-flex items-center gap-1 rounded-lg bg-brand-primary px-2 py-1 text-xs font-medium text-white shadow-sm hover:bg-brand-primary-hover">
                      <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                      </svg>
                      Upload
                    </span>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-6">
        <div class="flex items-center gap-3">
          <button
            type="button"
            @click="cancel"
            class="rounded-lg bg-brand-primary px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-brand-primary-hover"
          >
            Cancel
          </button>
        </div>
        <div class="flex items-center gap-3">
          <button
            type="button"
            :disabled="savingDraft"
            @click="saveDraft"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-200 disabled:opacity-50"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            {{ savingDraft ? 'Saving...' : 'Save as Draft' }}
          </button>
          <button
            type="submit"
            :disabled="submitting"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-brand-primary-hover disabled:opacity-50"
          >
            <span class="text-white">{{ submitting ? 'Submitting...' : 'Submit' }}</span>
            <svg v-if="!submitting" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M13 6l6 6-6 6" />
            </svg>
          </button>
        </div>
      </div>
    </form>
    </template>
  </div>
</template>
