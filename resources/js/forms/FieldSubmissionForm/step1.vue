<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import api from '@/services/fieldSubmissionsApi'
import { useFormErrors } from '@/composables/useFormErrors'
import { formatTeamLabel } from '@/composables/useTeamLabel'
import { useSessionFormState } from '@/composables/useSessionFormState'
import { useAuthStore } from '@/stores/auth'

const EMIRATES = [
  'Abu Dhabi',
  'Dubai',
  'Sharjah',
  'Ajman',
  'Umm Al Quwain',
  'Ras Al Khaimah',
  'Fujairah',
]

const form = ref({
  account_number: '',
  company_name: '',
  authorized_signatory_name: '',
  contact_number: '',
  product: '',
  alternate_number: '',
  emirates: '',
  location_coordinates: '',
  complete_address: '',
  additional_notes: '',
  special_instruction: '',
  manager_id: '',
  team_leader_id: '',
  sales_agent_id: '',
})
const { restoreState, clearState } = useSessionFormState('submission.field.step1', form)
const auth = useAuthStore()

const managers = ref([])
const teamLeaders = ref([])
const salesAgents = ref([])
/** When true, manager was set by TL or SA selection – don't clear TL/SA in manager watch */
const settingFromChild = ref(false)
/** When true, TL/SA were set by SA selection – don't clear SA in TL watch */
const settingFromSalesAgent = ref(false)
const teamLabels = ref({
  manager: 'Manager Name',
  team_leader: 'Team Leader Name',
  sales_agent: 'Sales Agent Name',
})
const submitting = ref(false)
const successMessage = ref('')
const teamSectionCollapsed = ref(false)
const savingDraft = ref(false)

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
  return teamLeaders.value.filter((t) => resolveTeamLeaderManagerId(t) === String(mid))
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
    return salesAgents.value.filter((salesAgent) => resolveSalesAgentTeamLeaderId(salesAgent) === String(tlId))
  }
  if (managerId) {
    return salesAgents.value.filter((salesAgent) => {
      const resolvedManagerId = resolveSalesAgentManagerId(salesAgent)
      const resolvedTeamLeaderId = resolveSalesAgentTeamLeaderId(salesAgent)
      return resolvedManagerId === String(managerId) || teamLeaderIdsUnderManager.has(resolvedTeamLeaderId)
    })
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

// When manager changes, clear TL and SA unless we're updating from a child selection
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

// When team leader is selected, auto-fill manager; when cleared, clear manager. When TL changes, clear SA unless we're setting from SA.
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

// When sales agent is selected, auto-fill TL and manager
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
  }
})

function buildPayload() {
  const f = form.value
  return {
    account_number: f.account_number?.trim() || null,
    company_name: f.company_name?.trim() ?? '',
    authorized_signatory_name: f.authorized_signatory_name?.trim() || null,
    contact_number: f.contact_number?.trim() ?? '',
    product: f.product?.trim() ?? '',
    alternate_number: f.alternate_number?.trim() ?? '',
    emirates: f.emirates?.trim() ?? '',
    location_coordinates: f.location_coordinates?.trim() || null,
    complete_address: f.complete_address?.trim() ?? '',
    additional_notes: f.additional_notes?.trim() || null,
    special_instruction: f.special_instruction?.trim() || null,
    manager_id: f.manager_id ? Number(f.manager_id) : null,
    team_leader_id: f.team_leader_id ? Number(f.team_leader_id) : null,
    sales_agent_id: f.sales_agent_id ? Number(f.sales_agent_id) : null,
  }
}

const onPhoneInput = (field, e) => {
  form.value[field] = e.target.value.replace(/\D/g, '').slice(0, 12)
  clearFieldError(field)
}

function validatePhone(value) {
  if (!value) return null
  if (!/^\d{12}$/.test(value)) return 'Must be exactly 12 digits with no spaces (e.g. 971XXXXXXXXX).'
  if (!value.startsWith('971')) return 'Must start with 971.'
  return null
}

const validateForm = () => {
  const err = {}
  if (!form.value.company_name?.trim()) err.company_name = ['Company name is required.']
  if (!form.value.contact_number?.trim()) {
    err.contact_number = ['Contact number is required.']
  } else {
    const phoneErr = validatePhone(form.value.contact_number.trim())
    if (phoneErr) err.contact_number = [phoneErr]
  }
  if (!form.value.product?.trim()) err.product = ['Product is required.']
  if (form.value.alternate_number?.trim()) {
    const altPhoneErr = validatePhone(form.value.alternate_number.trim())
    if (altPhoneErr) err.alternate_number = [altPhoneErr]
  }
  if (form.value.location_coordinates?.trim()) {
    const coordPattern = /^-?\d{1,3}(\.\d+)?\s*,\s*-?\d{1,3}(\.\d+)?$/
    const coords = form.value.location_coordinates.trim()
    if (!coordPattern.test(coords)) {
      err.location_coordinates = ['Enter valid coordinates (e.g. 25.2048, 55.2708).']
    } else {
      const [lat, lng] = coords.split(',').map(s => parseFloat(s.trim()))
      if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        err.location_coordinates = ['Latitude must be -90 to 90, longitude -180 to 180.']
      }
    }
  }
  if (!form.value.emirates?.trim()) err.emirates = ['Emirates is required.']
  if (!form.value.complete_address?.trim()) err.complete_address = ['Complete address is required.']
  if (!form.value.manager_id) err.manager_id = [`${formatTeamLabel(teamLabels.value.manager || 'manager')} is required.`]
  return Object.keys(err).length ? err : null
}

async function submit() {
  clearErrors()
  const frontendErrors = validateForm()
  if (frontendErrors) {
    errors.value = frontendErrors
    generalMessage.value = 'Please correct the errors below.'
    return
  }
  successMessage.value = ''
  submitting.value = true
  try {
    const payload = buildPayload()
    await api.store(payload, true) // submit = true to mark as submitted in DB
    successMessage.value = 'Your field submission has been submitted successfully. Back Office will review and process.'
    nextTick(() => {
      window.scrollTo(0, 0)
      document.documentElement.scrollTop = 0
      document.body.scrollTop = 0
    })
    clearState()
    Object.assign(form.value, {
      account_number: '',
      company_name: '',
      authorized_signatory_name: '',
      contact_number: '',
      product: '',
      alternate_number: '',
      emirates: '',
      location_coordinates: '',
      complete_address: '',
      additional_notes: '',
      special_instruction: '',
      manager_id: '',
      team_leader_id: '',
      sales_agent_id: '',
    })
  } catch (e) {
    setErrors(e)
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
    return
  }
  savingDraft.value = true
  try {
    const payload = buildPayload()
    await api.store(payload, false)
  } catch (e) {
    setErrors(e)
  } finally {
    savingDraft.value = false
  }
}

function cancel() {
  clearState()
  Object.assign(form.value, {
    account_number: '',
    company_name: '',
    authorized_signatory_name: '',
    contact_number: '',
    product: '',
    alternate_number: '',
    emirates: '',
    location_coordinates: '',
    complete_address: '',
    additional_notes: '',
    special_instruction: '',
    manager_id: '',
    team_leader_id: '',
    sales_agent_id: '',
  })
  clearErrors()
  successMessage.value = ''
}

function startNewSubmission() {
  cancel()
  window.scrollTo(0, 0)
}

const inputClass = (field) =>
  `w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500 ${getError(field) ? 'border-red-500' : 'border-gray-300'}`
const selectClass = (field) =>
  `w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 focus:border-green-500 focus:ring-1 focus:ring-green-500 ${getError(field) ? 'border-red-500' : 'border-gray-300'}`
</script>

<template>
  <div class="space-y-6">
    <!-- Success message at top so it's visible after scroll -->
    <div
      v-if="successMessage"
      class="rounded-lg border border-green-200 bg-green-50 p-6 text-center"
    >
      <svg class="mx-auto mb-3 h-12 w-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <h3 class="text-lg font-semibold text-green-800">Field Request Submitted</h3>
      <p class="mt-1 text-sm text-green-600">{{ successMessage }}</p>
      <p class="mt-3 text-sm text-gray-600">Click the button below to start a new field submission.</p>
      <button
        type="button"
        @click="startNewSubmission"
        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-green-700"
      >
        New Field Submission
      </button>
    </div>

    <form v-if="!successMessage" @submit.prevent="submit" class="space-y-6">
      <!-- Validation errors summary -->
      <div
        v-if="generalMessage || Object.keys(errors).length"
        class="rounded-lg border border-red-200 bg-red-50 p-4"
      >
        <p class="text-sm font-medium text-red-800">{{ generalMessage }}</p>
        <ul
          v-if="Object.keys(errors).length > 0"
          class="mt-2 list-inside list-disc space-y-0.5 text-sm text-red-700"
        >
          <li v-for="(msgs, field) in errors" :key="field">{{ getError(field) }}</li>
        </ul>
      </div>

      <!-- Primary Information -->
      <div class="!mt-0">
        <h3 class="border-b border-gray-200 pb-2 text-base font-semibold text-gray-800">
          Primary Information
        </h3>

        <div class="mt-4 space-y-4">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
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
              <label class="mb-1 block text-sm font-medium text-gray-700">Product <span class="text-red-500">*</span></label>
              <input
                v-model="form.product"
                type="text"
                placeholder="Enter product"
                :class="inputClass('product')"
                @input="clearFieldError('product')"
              />
              <p v-if="getError('product')" class="mt-1 text-sm text-red-600">{{ getError('product') }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Authorized Signatory Name</label>
              <input
                v-model="form.authorized_signatory_name"
                type="text"
                placeholder="Enter authorized signatory name"
                :class="inputClass('authorized_signatory_name')"
                @input="clearFieldError('authorized_signatory_name')"
              />
              <p v-if="getError('authorized_signatory_name')" class="mt-1 text-sm text-red-600">{{ getError('authorized_signatory_name') }}</p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Contact Number <span class="text-red-500">*</span></label>
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
              <label class="mb-1 block text-sm font-medium text-gray-700">Alternate Contact Number</label>
              <input
                v-model="form.alternate_number"
                type="text"
                maxlength="12"
                placeholder="971XXXXXXXXX"
                :class="inputClass('alternate_number')"
                @input="onPhoneInput('alternate_number', $event)"
              />
              <p v-if="getError('alternate_number')" class="mt-1 text-sm text-red-600">{{ getError('alternate_number') }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
            <div class="lg:col-span-3">
              <label class="mb-1 block text-sm font-medium text-gray-700">Complete Address <span class="text-red-500">*</span></label>
              <textarea
                v-model="form.complete_address"
                rows="1"
                placeholder="Enter complete address"
                class="h-10 resize-none w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500"
                :class="getError('complete_address') ? 'border-red-500' : 'border-gray-300'"
                @input="clearFieldError('complete_address')"
              />
              <p v-if="getError('complete_address')" class="mt-1 text-sm text-red-600">{{ getError('complete_address') }}</p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Emirates <span class="text-red-500">*</span></label>
              <select
                v-model="form.emirates"
                :class="selectClass('emirates')"
                @change="clearFieldError('emirates')"
              >
                <option value="">Select Emirates</option>
                <option v-for="e in EMIRATES" :key="e" :value="e">{{ e }}</option>
              </select>
              <p v-if="getError('emirates')" class="mt-1 text-sm text-red-600">{{ getError('emirates') }}</p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Location Coordinates</label>
              <input
                v-model="form.location_coordinates"
                type="text"
                placeholder="e.g. 25.2048, 55.2708"
                :class="inputClass('location_coordinates')"
                @input="clearFieldError('location_coordinates')"
              />
              <p v-if="getError('location_coordinates')" class="mt-1 text-sm text-red-600">{{ getError('location_coordinates') }}</p>
            </div>
          </div>
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
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Additional Notes</label>
              <textarea
                v-model="form.additional_notes"
                rows="2"
                placeholder="Enter Additional Notes"
                :class="inputClass('additional_notes')"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Any Special Instruction</label>
              <textarea
                v-model="form.special_instruction"
                rows="2"
                placeholder="Enter Special Instruction"
                :class="inputClass('special_instruction')"
              />
            </div>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-6">
        <div class="flex items-center gap-3">
          <button
            type="button"
            @click="cancel"
            class="rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-green-700"
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
            class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-green-700 disabled:opacity-50"
          >
            {{ submitting ? 'Submitting...' : 'Submit' }}
            <svg v-if="!submitting" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M13 6l6 6-6 6" />
            </svg>
          </button>
        </div>
      </div>
    </form>
  </div>
</template>
