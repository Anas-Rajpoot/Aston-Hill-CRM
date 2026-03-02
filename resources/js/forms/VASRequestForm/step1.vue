<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import api from '@/services/vasRequestsApi'
import { useFormErrors } from '@/composables/useFormErrors'
import { formatTeamLabel } from '@/composables/useTeamLabel'
import { useSessionFormState } from '@/composables/useSessionFormState'

const props = defineProps({
  vasRequestId: { type: Number, default: null },
})

const REQUEST_TYPES = [
  'Establishment Card Update',
  'Trade License Update',
  'POC Details Update',
  'Benefit Activation',
  'CNAP Update',
  'Sim Contract Renewals',
  'Hard Cap',
  'IR Activation',
  'Rate Plan Change',
  'Vas Activation',
  'Migration - Pre to Post',
  'Migration - Post to Pre',
  'Upgrade Rate Plan Change Request',
  'Downgrade Rate Plan Change Request',
  'Flavour Change',
  'Sub Account To Main Account Transfer',
  'Company Name Change',
  'TRN Update',
  'Other Request',
]

const form = ref({
  request_type: '',
  account_number: '',
  contact_number: '',
  company_name: '',
  request_description: '',
  additional_notes: '',
  manager_id: '',
  team_leader_id: '',
  sales_agent_id: '',
})
const { restoreState, clearState } = useSessionFormState('submission.vas.step1', form)

const managers = ref([])
const teamLeaders = ref([])
const salesAgents = ref([])
const settingFromChild = ref(false)
const settingFromSalesAgent = ref(false)
const teamLabels = ref({
  manager: 'Manager Name',
  team_leader: 'Team Leader Name',
  sales_agent: 'Sales Agent Name',
})
const loading = ref(true)
const saving = ref(false)
const savingDraft = ref(false)

const { errors, generalMessage, setErrors, clearErrors, clearFieldError, getError } = useFormErrors()

const filteredTeamLeaders = computed(() => {
  const mid = form.value.manager_id
  if (!mid) return teamLeaders.value
  return teamLeaders.value.filter((t) => String(t.manager_id) === String(mid))
})

const filteredSalesAgents = computed(() => {
  const tlId = form.value.team_leader_id
  if (!tlId) return salesAgents.value
  return salesAgents.value.filter((sa) => String(sa.team_leader_id) === String(tlId))
})

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
  (id) => {
    if (id) {
      const sa = salesAgents.value.find((u) => String(u.id) === String(id))
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

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await api.getTeamOptions()
    managers.value = data.managers || []
    teamLeaders.value = data.team_leaders || []
    salesAgents.value = data.sales_agents || []
    if (data.labels) {
      teamLabels.value = { ...teamLabels.value, ...data.labels }
    }

    if (props.vasRequestId) {
      try {
        const res = await api.getRequest(props.vasRequestId)
        const d = res.data
        settingFromChild.value = true
        settingFromSalesAgent.value = true
        form.value = {
          request_type: d.request_type || '',
          account_number: d.account_number || '',
          contact_number: d.contact_number || '',
          company_name: d.company_name || '',
          request_description: d.description || '',
          additional_notes: d.additional_notes || '',
          manager_id: d.manager_id != null ? String(d.manager_id) : '',
          team_leader_id: d.team_leader_id != null ? String(d.team_leader_id) : '',
          sales_agent_id: d.sales_agent_id != null ? String(d.sales_agent_id) : '',
        }
        nextTick(() => {
          settingFromChild.value = false
          settingFromSalesAgent.value = false
        })
      } catch (_) {}
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
  return {
    request_type: f.request_type?.trim() ?? '',
    account_number: f.account_number?.trim() ?? '',
    contact_number: f.contact_number?.trim() ?? '',
    company_name: f.company_name?.trim() ?? '',
    request_description: f.request_description?.trim() ?? '',
    additional_notes: f.additional_notes?.trim() || null,
    manager_id: f.manager_id ? Number(f.manager_id) : null,
    team_leader_id: f.team_leader_id ? Number(f.team_leader_id) : null,
    sales_agent_id: f.sales_agent_id ? Number(f.sales_agent_id) : null,
  }
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
  if (!form.value.request_type?.trim()) err.request_type = ['Please select a request type.']
  if (!form.value.account_number?.trim()) err.account_number = ['Account number is required.']
  const phoneErr = validatePhone(form.value.contact_number?.trim())
  if (phoneErr) err.contact_number = [phoneErr]
  if (!form.value.company_name?.trim()) err.company_name = ['Company name is required.']
  if (!form.value.request_description?.trim()) err.request_description = ['Request description is required.']
  if (!form.value.manager_id) err.manager_id = [`${formatTeamLabel(teamLabels.value.manager || 'manager')} is required.`]
  if (!form.value.team_leader_id) err.team_leader_id = [`${formatTeamLabel(teamLabels.value.team_leader || 'team_leader')} is required.`]
  if (!form.value.sales_agent_id) err.sales_agent_id = [`${formatTeamLabel(teamLabels.value.sales_agent || 'sales_agent')} is required.`]
  return Object.keys(err).length ? err : null
}

async function saveDraft() {
  clearErrors()
  const frontendErrors = validateForm()
  if (frontendErrors) {
    errors.value = frontendErrors
    generalMessage.value = 'Please correct the errors below.'
    return
  }
  savingDraft.value = true
  try {
    const payload = buildPayload()
    if (props.vasRequestId) {
      await api.updateStep1(props.vasRequestId, payload)
      clearState()
      emit('next', props.vasRequestId)
    } else {
      const { data } = await api.storeStep1(payload)
      clearState()
      emit('next', data.id)
    }
  } catch (e) {
    setErrors(e)
  } finally {
    savingDraft.value = false
  }
}

async function nextStep() {
  clearErrors()
  const frontendErrors = validateForm()
  if (frontendErrors) {
    errors.value = frontendErrors
    generalMessage.value = 'Please correct the errors below.'
    return
  }
  saving.value = true
  try {
    const payload = buildPayload()
    if (props.vasRequestId) {
      await api.updateStep1(props.vasRequestId, payload)
      clearState()
      emit('next', props.vasRequestId)
    } else {
      const { data } = await api.storeStep1(payload)
      clearState()
      emit('next', data.id)
    }
  } catch (e) {
    setErrors(e)
  } finally {
    saving.value = false
  }
}

const emit = defineEmits(['next'])

const inputClass = (field) =>
  `w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500 ${getError(field) ? 'border-red-500' : 'border-gray-300'}`
const selectClass = (field) =>
  `w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 focus:border-green-500 focus:ring-1 focus:ring-green-500 ${getError(field) ? 'border-red-500' : 'border-gray-300'}`
</script>

<template>
  <div class="space-y-8">
    <!-- Loading state (same as Lead Submissions) -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
      </svg>
      <span class="ml-3 text-gray-600">Loading...</span>
    </div>

    <template v-else>
    <div
      v-if="generalMessage || Object.keys(errors).length"
      class="rounded-lg border border-red-200 bg-red-50 p-4"
    >
      <p class="text-sm font-medium text-red-800">{{ generalMessage }}</p>
      <ul class="mt-2 list-inside list-disc space-y-0.5 text-sm text-red-700">
        <li v-for="(msgs, field) in errors" :key="field">{{ getError(field) }}</li>
      </ul>
    </div>

    <!-- Primary Information (2 columns) -->
    <div class="!mt-0">
      <h3 class="text-base font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">
        Primary Information
      </h3>
      <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">
            Request Type <span class="text-red-500">*</span>
          </label>
          <select
            v-model="form.request_type"
            :class="selectClass('request_type')"
            @change="clearFieldError('request_type')"
          >
            <option value="">Select Request Type</option>
            <option v-for="t in REQUEST_TYPES" :key="t" :value="t">{{ t }}</option>
          </select>
          <p v-if="getError('request_type')" class="mt-1 text-sm text-red-600">{{ getError('request_type') }}</p>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">
            Account Number <span class="text-red-500">*</span>
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
            Request Description <span class="text-red-500">*</span>
          </label>
          <textarea
            v-model="form.request_description"
            rows="4"
            placeholder="Provide detailed description of your VAS request..."
            :class="inputClass('request_description')"
            @input="clearFieldError('request_description')"
          />
          <p v-if="getError('request_description')" class="mt-1 text-sm text-red-600">{{ getError('request_description') }}</p>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Additional Notes</label>
          <textarea
            v-model="form.additional_notes"
            rows="4"
            placeholder="Add any additional comments or remarks..."
            :class="inputClass('additional_notes')"
          />
        </div>
      </div>
    </div>

    <!-- Team Information -->
    <div>
      <h3 class="border-b border-gray-200 pb-2 text-base font-semibold text-gray-800">
        Team Information
      </h3>
      <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">
            {{ formatTeamLabel(teamLabels.manager || 'manager') }} <span class="text-red-500">*</span>
          </label>
          <select
            v-model="form.manager_id"
            :class="selectClass('manager_id')"
            @change="clearFieldError('manager_id')"
          >
            <option value="">Select {{ formatTeamLabel(teamLabels.manager || 'manager') }}</option>
            <option v-for="u in managers" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
          </select>
          <p v-if="getError('manager_id')" class="mt-1 text-sm text-red-600">{{ getError('manager_id') }}</p>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">
            {{ formatTeamLabel(teamLabels.team_leader || 'team_leader') }} <span class="text-red-500">*</span>
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
            {{ formatTeamLabel(teamLabels.sales_agent || 'sales_agent') }} <span class="text-red-500">*</span>
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

    <!-- Actions: left = Step 1, right = Save as Draft, Cancel, Next (same icons as image) -->
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-6">
      <div class="flex items-center gap-3">
        <span class="rounded-lg bg-[#121d2c] px-4 py-2.5 text-sm font-medium text-white shadow-sm">Step 1</span>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <button
          type="button"
          @click="saveDraft"
          :disabled="saving || savingDraft"
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
          @click="nextStep"
          :disabled="saving || savingDraft"
          class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white shadow-sm disabled:opacity-50 bg-green-600 hover:bg-green-700"
        >
          {{ saving ? 'Saving...' : 'Next' }}
          <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </div>
    </template>
  </div>
</template>
