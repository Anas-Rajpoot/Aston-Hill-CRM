<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import api from '@/services/fieldSubmissionsApi'
import { useFormErrors } from '@/composables/useFormErrors'
import { formatTeamLabel } from '@/composables/useTeamLabel'

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
  company_name: '',
  contact_number: '',
  product: '',
  alternate_number: '',
  emirates: '',
  location_coordinates: '25.2048, 55.2708',
  complete_address: '',
  additional_notes: '',
  special_instruction: '',
  manager_id: '',
  team_leader_id: '',
  sales_agent_id: '',
})

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

// When sales agent is selected, auto-fill TL and manager; when cleared, clear TL and manager
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
  }
})

function buildPayload() {
  const f = form.value
  return {
    company_name: f.company_name?.trim() ?? '',
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

const validateForm = () => {
  const err = {}
  if (!form.value.company_name?.trim()) err.company_name = ['Company name is required.']
  if (!form.value.contact_number?.trim()) err.contact_number = ['Contact number is required.']
  if (!form.value.product?.trim()) err.product = ['Product is required.']
  if (!form.value.alternate_number?.trim()) err.alternate_number = ['Alternate number is required.']
  if (!form.value.emirates?.trim()) err.emirates = ['Emirates is required.']
  if (!form.value.complete_address?.trim()) err.complete_address = ['Complete address is required.']
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
    await api.store(payload, true) // submit = true to mark as submitted in DB
    successMessage.value = 'Field request submitted successfully.'
    nextTick(() => {
      window.scrollTo(0, 0)
      document.documentElement.scrollTop = 0
      document.body.scrollTop = 0
    })
    Object.assign(form.value, {
      company_name: '',
      contact_number: '',
      product: '',
      alternate_number: '',
      emirates: '',
      location_coordinates: '25.2048, 55.2708',
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

function cancel() {
  Object.assign(form.value, {
    company_name: '',
    contact_number: '',
    product: '',
    alternate_number: '',
    emirates: '',
    location_coordinates: '25.2048, 55.2708',
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
    </div>

    <div v-if="!successMessage">
      <h2 class="text-2xl font-bold text-gray-800">Field Submission Form</h2>
      <p class="mt-1 text-sm text-gray-500">
        Submit a request for field visit or meeting. Fields marked with <span class="text-red-500">*</span> are required.
      </p>
    </div>

    <form v-if="!successMessage" @submit.prevent="submit" class="space-y-8">
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
      <div>
        <h3 class="border-b border-gray-200 pb-2 text-base font-semibold text-gray-800">
          Primary Information
        </h3>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
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
            <p v-if="getError('company_name')" class="mt-1 text-sm text-red-600">
              {{ getError('company_name') }}
            </p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">
              Contact Number <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.contact_number"
              type="text"
              placeholder="+971 XX XXX XXXX"
              :class="inputClass('contact_number')"
              @input="clearFieldError('contact_number')"
            />
            <p v-if="getError('contact_number')" class="mt-1 text-sm text-red-600">
              {{ getError('contact_number') }}
            </p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">
              Product <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.product"
              type="text"
              placeholder="Product Name"
              :class="inputClass('product')"
              @input="clearFieldError('product')"
            />
            <p v-if="getError('product')" class="mt-1 text-sm text-red-600">
              {{ getError('product') }}
            </p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">
              Alternate Number <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.alternate_number"
              type="text"
              placeholder="+971 xxx xx xx XXX"
              :class="inputClass('alternate_number')"
              @input="clearFieldError('alternate_number')"
            />
            <p v-if="getError('alternate_number')" class="mt-1 text-sm text-red-600">
              {{ getError('alternate_number') }}
            </p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">
              Emirates <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.emirates"
              :class="selectClass('emirates')"
              @change="clearFieldError('emirates')"
            >
              <option value="">Select Emirates</option>
              <option v-for="e in EMIRATES" :key="e" :value="e">{{ e }}</option>
            </select>
            <p v-if="getError('emirates')" class="mt-1 text-sm text-red-600">
              {{ getError('emirates') }}
            </p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Location Coordinates</label>
            <input
              v-model="form.location_coordinates"
              type="text"
              placeholder="25.2048, 55.2708"
              :class="inputClass('location_coordinates')"
            />
          </div>
        </div>
        <div class="mt-4 space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">
              Complete Address <span class="text-red-500">*</span>
            </label>
            <textarea
              v-model="form.complete_address"
              rows="3"
              placeholder="Enter complete address"
              :class="inputClass('complete_address')"
              @input="clearFieldError('complete_address')"
            />
            <p v-if="getError('complete_address')" class="mt-1 text-sm text-red-600">
              {{ getError('complete_address') }}
            </p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Additional Notes</label>
            <textarea
              v-model="form.additional_notes"
              rows="2"
              placeholder="Enter any additional notes"
              :class="inputClass('additional_notes')"
            />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">
              Any Special Instruction
            </label>
            <textarea
              v-model="form.special_instruction"
              rows="2"
              placeholder="Enter any remarks or comments"
              :class="inputClass('special_instruction')"
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
            <p v-if="getError('manager_id')" class="mt-1 text-sm text-red-600">
              {{ getError('manager_id') }}
            </p>
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
            <p v-if="getError('team_leader_id')" class="mt-1 text-sm text-red-600">
              {{ getError('team_leader_id') }}
            </p>
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
            <p v-if="getError('sales_agent_id')" class="mt-1 text-sm text-red-600">
              {{ getError('sales_agent_id') }}
            </p>
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
          {{ submitting ? 'Submitting...' : 'Submit Field Request' }}
        </button>
      </div>
    </form>
  </div>
</template>
