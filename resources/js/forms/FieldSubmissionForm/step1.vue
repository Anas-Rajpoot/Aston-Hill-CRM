<script setup>
import { ref, onMounted } from 'vue'
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
const submitting = ref(false)
const successMessage = ref('')

const { errors, setErrors, clearErrors } = useFormErrors()

onMounted(async () => {
  try {
    const { data } = await api.getTeamOptions()
    managers.value = data.managers || []
    teamLeaders.value = data.team_leaders || []
    salesAgents.value = data.sales_agents || []
  } catch (e) {
    setErrors(e)
  }
})

const submit = async () => {
  clearErrors()
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
              :class="{ 'border-red-500': errors.company_name }"
            />
            <p v-if="errors.company_name" class="mt-1 text-sm text-red-600">{{ errors.company_name[0] }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number *</label>
            <input
              v-model="form.contact_number"
              type="text"
              placeholder="+971 XX XXX XXXX"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
              :class="{ 'border-red-500': errors.contact_number }"
            />
            <p v-if="errors.contact_number" class="mt-1 text-sm text-red-600">{{ errors.contact_number[0] }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Product *</label>
            <input
              v-model="form.product"
              type="text"
              placeholder="Product Name"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
              :class="{ 'border-red-500': errors.product }"
            />
            <p v-if="errors.product" class="mt-1 text-sm text-red-600">{{ errors.product[0] }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Alternate Number *</label>
            <input
              v-model="form.alternate_number"
              type="text"
              placeholder="+971 xxx xx xx XXX"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
              :class="{ 'border-red-500': errors.alternate_number }"
            />
            <p v-if="errors.alternate_number" class="mt-1 text-sm text-red-600">{{ errors.alternate_number[0] }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Emirates *</label>
            <select
              v-model="form.emirates"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
              :class="{ 'border-red-500': errors.emirates }"
            >
              <option value="">Select Emirates</option>
              <option v-for="e in EMIRATES" :key="e" :value="e">{{ e }}</option>
            </select>
            <p v-if="errors.emirates" class="mt-1 text-sm text-red-600">{{ errors.emirates[0] }}</p>
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
              :class="{ 'border-red-500': errors.complete_address }"
            />
            <p v-if="errors.complete_address" class="mt-1 text-sm text-red-600">{{ errors.complete_address[0] }}</p>
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Manager Name *</label>
            <select
              v-model="form.manager_id"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
              :class="{ 'border-red-500': errors.manager_id }"
            >
              <option value="">Select Manager</option>
              <option v-for="u in managers" :key="u.id" :value="u.id">{{ u.label }}</option>
            </select>
            <p v-if="errors.manager_id" class="mt-1 text-sm text-red-600">{{ errors.manager_id[0] }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Team Leader Name *</label>
            <select
              v-model="form.team_leader_id"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
              :class="{ 'border-red-500': errors.team_leader_id }"
            >
              <option value="">Select Team Leader</option>
              <option v-for="u in teamLeaders" :key="u.id" :value="u.id">{{ u.label }}</option>
            </select>
            <p v-if="errors.team_leader_id" class="mt-1 text-sm text-red-600">{{ errors.team_leader_id[0] }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Sales Agent Name *</label>
            <select
              v-model="form.sales_agent_id"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
              :class="{ 'border-red-500': errors.sales_agent_id }"
            >
              <option value="">Select Sales Agent</option>
              <option v-for="u in salesAgents" :key="u.id" :value="u.id">{{ u.label }}</option>
            </select>
            <p v-if="errors.sales_agent_id" class="mt-1 text-sm text-red-600">{{ errors.sales_agent_id[0] }}</p>
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
