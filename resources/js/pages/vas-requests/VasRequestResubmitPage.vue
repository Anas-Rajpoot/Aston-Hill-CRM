<script setup>
/**
 * VAS Request Resubmit – mirrors the Edit form, allows user to modify data and resubmit a request (all statuses except approved).
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import vasRequestsApi from '@/services/vasRequestsApi'
import { useAuthStore } from '@/stores/auth'
import { useFormErrors } from '@/composables/useFormErrors'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { errors: formErrors, generalMessage, setErrors, clearErrors, clearFieldError, getError } = useFormErrors()

const loading = ref(true)
const saving = ref(false)
const request = ref(null)
const teamOptions = ref({
  managers: [],
  team_leaders: [],
  sales_agents: [],
})
const requestTypes = ref([])
const serverErrors = ref({})
const successMessage = ref('')

const form = ref({
  request_type: '',
  account_number: '',
  contact_number: '',
  company_name: '',
  description: '',
  additional_notes: '',
  manager_id: null,
  team_leader_id: null,
  sales_agent_id: null,
})

const id = computed(() => {
  const p = route.params.id
  return p != null ? Number(p) : null
})

const filteredTeamLeaders = computed(() => {
  const mid = form.value.manager_id
  if (!mid) return teamOptions.value.team_leaders ?? []
  return (teamOptions.value.team_leaders ?? []).filter((t) => String(t.manager_id) === String(mid))
})

const filteredSalesAgents = computed(() => {
  const tlId = form.value.team_leader_id
  if (!tlId) return teamOptions.value.sales_agents ?? []
  return (teamOptions.value.sales_agents ?? []).filter((s) => String(s.team_leader_id) === String(tlId))
})

async function load() {
  if (!id.value) return
  loading.value = true
  request.value = null
  try {
    const [reqRes, teamRes, filtersRes] = await Promise.all([
      vasRequestsApi.getRequest(id.value),
      vasRequestsApi.getTeamOptions().then((r) => r?.data ?? r).catch(() => ({})),
      vasRequestsApi.filters().catch(() => ({})),
    ])
    const data = reqRes?.data ?? reqRes
    request.value = data

    if (data.status === 'approved') {
      alert('Approved requests cannot be resubmitted.')
      router.push('/vas-requests')
      return
    }

    teamOptions.value = {
      managers: teamRes.managers ?? [],
      team_leaders: teamRes.team_leaders ?? [],
      sales_agents: teamRes.sales_agents ?? [],
    }
    requestTypes.value = (filtersRes.request_types ?? []).map((t) => (typeof t === 'string' ? t : t?.value ?? t))
    form.value = {
      request_type: data.request_type ?? '',
      account_number: data.account_number ?? '',
      contact_number: data.contact_number ?? '',
      company_name: data.company_name ?? '',
      description: data.description ?? data.request_description ?? '',
      additional_notes: data.additional_notes ?? '',
      manager_id: data.manager_id != null ? data.manager_id : null,
      team_leader_id: data.team_leader_id != null ? data.team_leader_id : null,
      sales_agent_id: data.sales_agent_id != null ? data.sales_agent_id : null,
    }
  } catch {
    request.value = null
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
}

const inputClass = (field) =>
  `mt-1 block w-full rounded border px-3 py-2 shadow-sm focus:ring-1 ${getError(field) ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-green-500 focus:ring-green-500'}`

const selectClass = (field) =>
  `mt-1 block w-full rounded border bg-white px-3 py-2 shadow-sm focus:ring-1 ${getError(field) ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-green-500 focus:ring-green-500'}`

function validatePhone(value) {
  if (!value) return 'Contact number is required.'
  if (/\s/.test(value)) return 'Contact number must not contain spaces.'
  if (!/^\d+$/.test(value)) return 'Contact number must contain only digits.'
  if (!value.startsWith('971')) return 'Contact number must start with 971.'
  if (value.length !== 12) return 'Contact number must be exactly 12 digits.'
  return null
}

function onPhoneInput(e) {
  form.value.contact_number = e.target.value.replace(/[^0-9]/g, '')
  clearFieldError('contact_number')
}

function validateForm() {
  const err = {}
  if (!form.value.request_type?.trim()) err.request_type = ['Please select a request type.']
  if (!form.value.account_number?.trim()) err.account_number = ['Account number is required.']
  const phoneErr = validatePhone(form.value.contact_number?.trim())
  if (phoneErr) err.contact_number = [phoneErr]
  if (!form.value.company_name?.trim()) err.company_name = ['Company name is required.']
  if (!form.value.description?.trim()) err.description = ['Request description is required.']
  if (!form.value.manager_id) err.manager_id = ['Please select a manager.']
  if (!form.value.team_leader_id) err.team_leader_id = ['Please select a team leader.']
  if (!form.value.sales_agent_id) err.sales_agent_id = ['Please select a sales agent.']
  return Object.keys(err).length > 0 ? err : null
}

function goBack() {
  router.push('/vas-requests')
}

async function submitForm() {
  if (!id.value) return
  clearErrors()
  serverErrors.value = {}
  successMessage.value = ''
  const frontendErrors = validateForm()
  if (frontendErrors) {
    formErrors.value = frontendErrors
    generalMessage.value = 'Please correct the errors below.'
    return
  }
  saving.value = true
  try {
    await vasRequestsApi.resubmit(id.value, {
      request_type: form.value.request_type,
      account_number: form.value.account_number || null,
      contact_number: form.value.contact_number || null,
      company_name: form.value.company_name,
      description: form.value.description || null,
      additional_notes: form.value.additional_notes || null,
      manager_id: form.value.manager_id ? Number(form.value.manager_id) : null,
      team_leader_id: form.value.team_leader_id ? Number(form.value.team_leader_id) : null,
      sales_agent_id: form.value.sales_agent_id ? Number(form.value.sales_agent_id) : null,
    })
    successMessage.value = 'VAS request resubmitted successfully!'
    setTimeout(() => {
      router.push('/vas-requests')
    }, 1500)
  } catch (err) {
    if (err.response?.status === 422 && err.response?.data?.errors) {
      serverErrors.value = err.response.data.errors
    }
    setErrors(err)
  } finally {
    saving.value = false
  }
}

onMounted(() => load())
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white p-0">
    <div class="w-full bg-white">
      <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="px-4 py-4 sm:px-5">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-baseline gap-2">
              <h1 class="text-xl font-semibold text-gray-900">Resubmit VAS Request</h1>
              <Breadcrumbs />
            </div>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
              @click="goBack"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              Back to List
            </button>
          </div>
        </div>
        <div class="border-t border-gray-200" />

        <div v-if="loading" class="flex justify-center px-4 py-16 sm:px-5">
          <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </div>

        <div v-else-if="!request" class="px-4 py-8 text-center text-gray-500 sm:px-5">
          Unable to load request. You may not have permission to view it.
        </div>

        <form v-else class="px-4 py-4 sm:px-5" @submit.prevent="submitForm">
          <!-- Resubmit banner -->
          <div class="mb-4 rounded-lg border border-orange-200 bg-orange-50 px-4 py-3">
            <div class="flex items-start gap-3">
              <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
              <div>
                <h3 class="text-sm font-semibold text-orange-900">Resubmit VAS Request</h3>
                <p class="mt-1 text-sm text-orange-800">Review and update the information below, then click "Resubmit" to send it for review again.</p>
              </div>
            </div>
          </div>

          <!-- Success message -->
          <div v-if="successMessage" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
            {{ successMessage }}
          </div>

          <!-- Primary Information -->
          <h2 class="mb-3 border-b border-gray-200 pb-2 text-base font-semibold text-gray-900">Primary Information</h2>
          <div class="grid gap-4 sm:grid-cols-2 lg:gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700">Request Type <span class="text-red-500">*</span></label>
              <select
                v-model="form.request_type"
                required
                :class="selectClass('request_type')"
                @change="clearFieldError('request_type')"
              >
                <option value="">Select</option>
                <option v-for="t in requestTypes" :key="t" :value="t">{{ t }}</option>
              </select>
              <p v-if="getError('request_type')" class="mt-1 text-xs text-red-600">{{ getError('request_type') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Account Number <span class="text-red-500">*</span></label>
              <input
                v-model="form.account_number"
                type="text"
                required
                :class="inputClass('account_number')"
                @input="clearFieldError('account_number')"
              />
              <p v-if="getError('account_number')" class="mt-1 text-xs text-red-600">{{ getError('account_number') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Contact Number <span class="text-red-500">*</span></label>
              <input
                :value="form.contact_number"
                type="text"
                maxlength="12"
                placeholder="971XXXXXXXXX"
                required
                :class="inputClass('contact_number')"
                @input="onPhoneInput"
              />
              <p v-if="getError('contact_number')" class="mt-1 text-xs text-red-600">{{ getError('contact_number') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Company Name <span class="text-red-500">*</span></label>
              <input
                v-model="form.company_name"
                type="text"
                required
                :class="inputClass('company_name')"
                @input="clearFieldError('company_name')"
              />
              <p v-if="getError('company_name')" class="mt-1 text-xs text-red-600">{{ getError('company_name') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Request Description <span class="text-red-500">*</span></label>
              <textarea
                v-model="form.description"
                rows="3"
                required
                :class="inputClass('description')"
                placeholder="Enter request description"
                @input="clearFieldError('description')"
              />
              <p v-if="getError('description')" class="mt-1 text-xs text-red-600">{{ getError('description') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Additional Notes</label>
              <textarea
                v-model="form.additional_notes"
                rows="3"
                :class="inputClass('additional_notes')"
                placeholder="Enter additional notes"
              />
              <p v-if="getError('additional_notes')" class="mt-1 text-xs text-red-600">{{ getError('additional_notes') }}</p>
            </div>
          </div>

          <!-- Team Information -->
          <h2 class="mb-3 mt-8 border-b border-gray-200 pb-2 text-base font-semibold text-gray-900">Team Information</h2>
          <div class="grid gap-4 sm:grid-cols-3 lg:gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700">Manager <span class="text-red-500">*</span></label>
              <select
                v-model="form.manager_id"
                required
                :class="selectClass('manager_id')"
                @change="clearFieldError('manager_id')"
              >
                <option :value="null">Select</option>
                <option v-for="u in teamOptions.managers" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <p v-if="getError('manager_id')" class="mt-1 text-xs text-red-600">{{ getError('manager_id') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Team Leader <span class="text-red-500">*</span></label>
              <select
                v-model="form.team_leader_id"
                required
                :class="selectClass('team_leader_id')"
                @change="clearFieldError('team_leader_id')"
              >
                <option :value="null">Select</option>
                <option v-for="u in filteredTeamLeaders" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <p v-if="getError('team_leader_id')" class="mt-1 text-xs text-red-600">{{ getError('team_leader_id') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Sales Agent <span class="text-red-500">*</span></label>
              <select
                v-model="form.sales_agent_id"
                required
                :class="selectClass('sales_agent_id')"
                @change="clearFieldError('sales_agent_id')"
              >
                <option :value="null">Select</option>
                <option v-for="u in filteredSalesAgents" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <p v-if="getError('sales_agent_id')" class="mt-1 text-xs text-red-600">{{ getError('sales_agent_id') }}</p>
            </div>
          </div>

          <!-- Actions -->
          <div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4">
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              @click="goBack"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="inline-flex items-center gap-2 rounded bg-orange-600 px-5 py-2 text-sm font-medium text-white hover:bg-orange-700 disabled:opacity-70"
            >
              <svg v-if="!saving" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <span v-if="saving">Resubmitting...</span>
              <span v-else>Resubmit VAS Request</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

</template>
