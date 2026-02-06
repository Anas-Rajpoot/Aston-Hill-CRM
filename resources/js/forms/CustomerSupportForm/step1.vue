<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import api from '@/services/customerSupportApi'
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

const form = ref({
  issue_category: '',
  company_name: '',
  account_number: '',
  contact_number: '',
  issue_description: '',
  attachment_1: null,
  attachment_2: null,
  manager_id: '',
  team_leader_id: '',
  sales_agent_id: '',
})

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
const submitting = ref(false)
const successMessage = ref('')
const fileInput1 = ref(null)
const fileInput2 = ref(null)
/** Public URL for submit icon (in public/images/) – use bound :src so Vite does not try to import */
const submitRequestIconUrl = '/images/submit-request-icon.png'

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
  } catch (e) {
    setErrors(e)
  } finally {
    loading.value = false
  }
})

function buildPayload() {
  const f = form.value
  return {
    issue_category: f.issue_category?.trim() ?? '',
    company_name: f.company_name?.trim() ?? '',
    account_number: f.account_number?.trim() ?? '',
    contact_number: f.contact_number?.trim() ?? '',
    issue_description: f.issue_description?.trim() ?? '',
    attachment_1: f.attachment_1 instanceof File ? f.attachment_1 : null,
    attachment_2: f.attachment_2 instanceof File ? f.attachment_2 : null,
    manager_id: f.manager_id ? Number(f.manager_id) : null,
    team_leader_id: f.team_leader_id ? Number(f.team_leader_id) : null,
    sales_agent_id: f.sales_agent_id ? Number(f.sales_agent_id) : null,
  }
}

function validateForm() {
  const err = {}
  if (!form.value.issue_category?.trim()) err.issue_category = ['Please select an issue category.']
  if (!form.value.company_name?.trim()) err.company_name = ['Company name is required.']
  if (!form.value.contact_number?.trim()) err.contact_number = ['Contact number is required.']
  if (!form.value.issue_description?.trim()) err.issue_description = ['Issue description is required.']
  if (!form.value.manager_id) err.manager_id = [`${formatTeamLabel(teamLabels.value.manager || 'manager')} is required.`]
  if (!form.value.team_leader_id) err.team_leader_id = [`${formatTeamLabel(teamLabels.value.team_leader || 'team_leader')} is required.`]
  if (!form.value.sales_agent_id) err.sales_agent_id = [`${formatTeamLabel(teamLabels.value.sales_agent || 'sales_agent')} is required.`]
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
    await api.store(payload, true)
    successMessage.value = 'Request submitted successfully.'
    nextTick(() => {
      window.scrollTo(0, 0)
      document.documentElement.scrollTop = 0
      document.body.scrollTop = 0
    })
    form.value = {
      issue_category: '',
      company_name: '',
      account_number: '',
      contact_number: '',
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
  } finally {
    submitting.value = false
  }
}

function cancel() {
  form.value = {
    issue_category: '',
    company_name: '',
    account_number: '',
    contact_number: '',
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

function onFileChange(slot, event) {
  const file = event.target?.files?.[0]
  if (slot === 1) form.value.attachment_1 = file || null
  else form.value.attachment_2 = file || null
}

const inputClass = (field) =>
  `w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500 ${getError(field) ? 'border-red-500' : 'border-gray-300'}`
const selectClass = (field) =>
  `w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 focus:border-green-500 focus:ring-1 focus:ring-green-500 ${getError(field) ? 'border-red-500' : 'border-gray-300'}`
</script>

<template>
  <div class="space-y-6">
    <!-- Loading state (same as Lead Submissions) -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
      </svg>
      <span class="ml-3 text-gray-600">Loading...</span>
    </div>

    <template v-else>
    <!-- Success message at top -->
    <div
      v-if="successMessage"
      class="rounded-lg border border-green-200 bg-green-50 p-6 text-center"
    >
      <svg class="mx-auto mb-3 h-12 w-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <h3 class="text-lg font-semibold text-green-800">Request Submitted</h3>
      <p class="mt-1 text-sm text-green-600">{{ successMessage }}</p>
    </div>

    <div v-if="!successMessage">
      <h2 class="text-2xl font-bold text-gray-800">Primary Information</h2>
    </div>

    <form v-if="!successMessage" @submit.prevent="submit" class="space-y-8">
      <div
        v-if="generalMessage || Object.keys(errors).length"
        class="rounded-lg border border-red-200 bg-red-50 p-4"
      >
        <p class="text-sm font-medium text-red-800">{{ generalMessage }}</p>
        <ul class="mt-2 list-inside list-disc space-y-0.5 text-sm text-red-700">
          <li v-for="(msgs, field) in errors" :key="field">{{ getError(field) }}</li>
        </ul>
      </div>

      <!-- Primary Information section content -->
      <div>
        <div class="mt-0">
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
          <p v-if="getError('issue_category')" class="mt-1 text-sm text-red-600">
            {{ getError('issue_category') }}
          </p>
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
              placeholder="Enter Contact Number"
              :class="inputClass('contact_number')"
              @input="clearFieldError('contact_number')"
            />
            <p v-if="getError('contact_number')" class="mt-1 text-sm text-red-600">{{ getError('contact_number') }}</p>
          </div>
        </div>

        <!-- Issue Description (wider) + Attachments (narrower, smaller) -->
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
            <p v-if="getError('issue_description')" class="mt-1 text-sm text-red-600">
              {{ getError('issue_description') }}
            </p>
          </div>
          <div class="flex max-w-full flex-col gap-2">
            <label class="block text-sm font-semibold text-gray-900">Attachments</label>
            <div class="flex flex-col gap-2">
              <!-- Attachment 1 -->
              <div class="flex min-w-0 items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 shadow-sm">
                <div class="shrink-0 text-gray-500">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    @change="onFileChange(1, $event)"
                  />
                  <span class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-2 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-green-700">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Upload
                  </span>
                </label>
              </div>
              <!-- Attachment 2 -->
              <div class="flex min-w-0 items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 shadow-sm">
                <div class="shrink-0 text-gray-500">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    @change="onFileChange(2, $event)"
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
              {{ formatTeamLabel(teamLabels.manager || 'manager') }} Name <span class="text-red-500">*</span>
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

      <div class="flex justify-end gap-3 border-t border-gray-100 pt-6">
        <button
          type="button"
          @click="cancel"
          class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="submitting"
          class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-black shadow-sm disabled:opacity-50 bg-[#7ED321] hover:bg-[#6ab81e]"
        >
          <img
            :src="submitRequestIconUrl"
            alt=""
            class="h-4 w-4 shrink-0 object-contain"
          />
          <span class="text-black">{{ submitting ? 'Submitting...' : 'Submit Request' }}</span>
        </button>
      </div>
    </form>
    </template>
  </div>
</template>
