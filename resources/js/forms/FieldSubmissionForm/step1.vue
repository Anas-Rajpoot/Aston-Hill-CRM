<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '@/services/fieldSubmissionsApi'
import { useFormErrors } from '@/composables/useFormErrors'

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
const teamLabels = ref({
  manager: 'Manager Name',
  team_leader: 'Team Leader Name',
  sales_agent: 'Sales Agent Name',
})
const submitting = ref(false)
const successMessage = ref('')

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
    form.value.team_leader_id = ''
    form.value.sales_agent_id = ''
  }
)

watch(
  () => form.value.team_leader_id,
  (id) => {
    if (!id) return
    const tl = teamLeaders.value.find((u) => String(u.id) === String(id))
    if (tl?.manager_id) form.value.manager_id = String(tl.manager_id)
    form.value.sales_agent_id = ''
  }
)

watch(
  () => form.value.sales_agent_id,
  (id) => {
    if (!id) return
    const sa = salesAgents.value.find((u) => String(u.id) === String(id))
    if (sa?.team_leader_id) form.value.team_leader_id = sa.team_leader_id
    if (sa?.manager_id) form.value.manager_id = sa.manager_id
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

// Frontend validation
const validateForm = () => {
  const err = {}
  if (!form.value.company_name?.trim()) err.company_name = ['Company name is required.']
  if (!form.value.contact_number?.trim()) err.contact_number = ['Contact number is required.']
  if (!form.value.product?.trim()) err.product = ['Product is required.']
  if (!form.value.alternate_number?.trim()) err.alternate_number = ['Alternate number is required.']
  if (!form.value.emirates?.trim()) err.emirates = ['Emirates is required.']
  if (!form.value.complete_address?.trim()) err.complete_address = ['Complete address is required.']
  if (!form.value.manager_id) err.manager_id = [`${teamLabels.value.manager || 'Manager'} is required.`]
  if (!form.value.team_leader_id) err.team_leader_id = [`${teamLabels.value.team_leader || 'Team Leader'} is required.`]
  if (!form.value.sales_agent_id) err.sales_agent_id = [`${teamLabels.value.sales_agent || 'Sales Agent'} is required.`]
  return Object.keys(err).length ? err : null
}

const submit = async () => {
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
    await api.store(form.value, true)
    successMessage.value = 'Field request submitted successfully.'
    form.value = {
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
    }
  } catch (e) {
    setErrors(e)
  } finally {
    submitting.value = false
  }
}

const cancel = () => {
  form.value = {
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
  }
  clearErrors()
  successMessage.value = ''
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-xl font-semibold text-gray-800">Field Submission Form</h2>
      <p class="mt-1 text-sm text-gray-500">
        Submit a request for field visit or meeting. Fields marked with * are required.
      </p>
    </div>

    <form v-if="!successMessage" @submit.prevent="submit" class="space-y-8">
      <!-- Validation errors summary -->
      <div v-if="generalMessage || Object.keys(errors).length" class="rounded-lg bg-red-50 border border-red-200 p-4">
        <p class="text-sm font-medium text-red-800">{{ generalMessage }}</p>
        <ul v-if="Object.keys(errors).length > 0" class="mt-2 text-sm text-red-700 list-disc list-inside space-y-0.5">
          <li v-for="(msgs, field) in errors" :key="field">{{ getError(field) }}</li>
        </ul>
      </div>

      <!-- Primary Information -->
      <div>
        <h3 class="text-sm font-medium text-gray-700 border-b pb-2 mb-4">Primary Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Company Name *</label>
            <input
              v-model="form.company_name"
              type="text"
              placeholder="Enter company name"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
              :class="{ 'border-red-500': getError('company_name') }"
              @input="clearFieldError('company_name')"
            />
            <p v-if="getError('company_name')" class="mt-1 text-sm text-red-600">{{ getError('company_name') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number *</label>
            <input
              v-model="form.contact_number"
              type="text"
              placeholder="+971 XX XXX XXXX"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
              :class="{ 'border-red-500': getError('contact_number') }"
              @input="clearFieldError('contact_number')"
            />
            <p v-if="getError('contact_number')" class="mt-1 text-sm text-red-600">{{ getError('contact_number') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Product *</label>
            <input
              v-model="form.product"
              type="text"
              placeholder="Product Name"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
              :class="{ 'border-red-500': getError('product') }"
              @input="clearFieldError('product')"
            />
            <p v-if="getError('product')" class="mt-1 text-sm text-red-600">{{ getError('product') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Alternate Number *</label>
            <input
              v-model="form.alternate_number"
              type="text"
              placeholder="+971 xxx xx xx XXX"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
              :class="{ 'border-red-500': getError('alternate_number') }"
              @input="clearFieldError('alternate_number')"
            />
            <p v-if="getError('alternate_number')" class="mt-1 text-sm text-red-600">{{ getError('alternate_number') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Emirates *</label>
            <select
              v-model="form.emirates"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
              :class="{ 'border-red-500': getError('emirates') }"
            >
              <option value="">Select Emirates</option>
              <option v-for="e in EMIRATES" :key="e" :value="e">{{ e }}</option>
            </select>
            <p v-if="getError('emirates')" class="mt-1 text-sm text-red-600">{{ getError('emirates') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Location Coordinates</label>
            <input
              v-model="form.location_coordinates"
              type="text"
              placeholder="25.2048, 55.2708"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
            />
          </div>
        </div>
        <div class="mt-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Complete Address *</label>
            <textarea
              v-model="form.complete_address"
              rows="3"
              placeholder="Enter complete address"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
              :class="{ 'border-red-500': getError('complete_address') }"
              @input="clearFieldError('complete_address')"
            />
            <p v-if="getError('complete_address')" class="mt-1 text-sm text-red-600">{{ getError('complete_address') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
            <textarea
              v-model="form.additional_notes"
              rows="2"
              placeholder="Enter any additional notes"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Any Special Instruction</label>
            <textarea
              v-model="form.special_instruction"
              rows="2"
              placeholder="Enter any remarks or comments"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
            />
          </div>
        </div>
      </div>

      <!-- Team Information -->
      <div>
        <h3 class="text-sm font-medium text-gray-700 border-b pb-2 mb-4">Team Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ teamLabels.manager || 'Manager Name' }} *</label>
            <select
              v-model="form.manager_id"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
              :class="{ 'border-red-500': getError('manager_id') }"
              @change="clearFieldError('manager_id')"
            >
              <option value="">Select {{ teamLabels.manager || 'Manager' }}</option>
              <option v-for="u in managers" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
            <p v-if="getError('manager_id')" class="mt-1 text-sm text-red-600">{{ getError('manager_id') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ teamLabels.team_leader || 'Team Leader Name' }} *</label>
            <select
              v-model="form.team_leader_id"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
              :class="{ 'border-red-500': getError('team_leader_id') }"
              @change="clearFieldError('team_leader_id')"
            >
              <option value="">Select {{ teamLabels.team_leader || 'Team Leader' }}</option>
              <option v-for="u in filteredTeamLeaders" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
            <p v-if="getError('team_leader_id')" class="mt-1 text-sm text-red-600">{{ getError('team_leader_id') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Sales Agent Name *</label>
            <select
              v-model="form.sales_agent_id"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
              :class="{ 'border-red-500': getError('sales_agent_id') }"
              @change="clearFieldError('sales_agent_id')"
            >
              <option value="">Select {{ teamLabels.sales_agent || 'Sales Agent' }}</option>
              <option v-for="u in filteredSalesAgents" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
            <p v-if="getError('sales_agent_id')" class="mt-1 text-sm text-red-600">{{ getError('sales_agent_id') }}</p>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-3 pt-4">
        <button type="button" @click="cancel" class="px-4 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
          Cancel
        </button>
        <button type="submit" :disabled="submitting" class="px-4 py-2 rounded-md bg-green-600 text-white hover:bg-green-700 disabled:opacity-50">
          {{ submitting ? 'Submitting...' : 'Submit Field Request' }}
        </button>
      </div>
    </form>

    <div v-else class="rounded-lg bg-green-50 p-4 text-green-800">
      {{ successMessage }}
    </div>
  </div>
</template>
