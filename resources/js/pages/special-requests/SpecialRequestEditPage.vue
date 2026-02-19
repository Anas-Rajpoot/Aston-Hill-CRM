<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import specialRequestsApi from '@/services/specialRequestsApi'
import { useFormErrors } from '@/composables/useFormErrors'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Toast from '@/components/Toast.vue'

const route = useRoute()
const router = useRouter()

const id = computed(() => Number(route.params.id))

const loading = ref(true)
const saving = ref(false)
const showToast = ref(false)
const toastType = ref('success')
const toastMsg = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

const REQUEST_TYPES = ['General', 'Support', 'Relocation', 'Renewal', 'Other']
const STATUSES = ['draft', 'submitted', 'approved', 'rejected']

const { errors, generalMessage, setErrors, clearErrors, clearFieldError, getError } = useFormErrors()

const form = ref({
  company_name: '',
  account_number: '',
  request_type: '',
  status: '',
  complete_address: '',
  special_instruction: '',
  manager_id: '',
  team_leader_id: '',
  sales_agent_id: '',
})

const teamOptions = ref({ managers: [], team_leaders: [], sales_agents: [] })
const settingFromChild = ref(false)
const settingFromSalesAgent = ref(false)

const filteredTeamLeaders = computed(() => {
  const mid = form.value.manager_id
  if (!mid) return teamOptions.value.team_leaders
  return teamOptions.value.team_leaders.filter((t) => String(t.manager_id) === String(mid))
})

const filteredSalesAgents = computed(() => {
  const tlId = form.value.team_leader_id
  if (!tlId) return teamOptions.value.sales_agents
  return teamOptions.value.sales_agents.filter((sa) => String(sa.team_leader_id) === String(tlId))
})

watch(() => form.value.manager_id, () => {
  if (settingFromChild.value) { nextTick(() => { settingFromChild.value = false }); return }
  form.value.team_leader_id = ''
  form.value.sales_agent_id = ''
})

watch(() => form.value.team_leader_id, (val) => {
  if (val) {
    const tl = teamOptions.value.team_leaders.find((u) => String(u.id) === String(val))
    if (tl?.manager_id != null) { settingFromChild.value = true; form.value.manager_id = String(tl.manager_id); nextTick(() => { settingFromChild.value = false }) }
    if (!settingFromSalesAgent.value) form.value.sales_agent_id = ''
  } else { form.value.manager_id = '' }
})

watch(() => form.value.sales_agent_id, (val) => {
  if (val) {
    const sa = teamOptions.value.sales_agents.find((u) => String(u.id) === String(val))
    if (sa) {
      settingFromSalesAgent.value = true; settingFromChild.value = true
      if (sa.team_leader_id != null) form.value.team_leader_id = String(sa.team_leader_id)
      if (sa.manager_id != null) form.value.manager_id = String(sa.manager_id)
      nextTick(() => { settingFromSalesAgent.value = false; settingFromChild.value = false })
    }
  } else { form.value.team_leader_id = ''; form.value.manager_id = '' }
})

async function loadData() {
  loading.value = true
  try {
    const [reqData, teamRes] = await Promise.all([
      specialRequestsApi.getRequest(id.value),
      specialRequestsApi.getTeamOptions(),
    ])
    const teamData = teamRes?.data ?? teamRes ?? {}
    teamOptions.value = {
      managers: teamData.managers ?? [],
      team_leaders: teamData.team_leaders ?? [],
      sales_agents: teamData.sales_agents ?? [],
    }

    settingFromChild.value = true
    settingFromSalesAgent.value = true
    form.value = {
      company_name: reqData.company_name || '',
      account_number: reqData.account_number || '',
      request_type: reqData.request_type || '',
      status: reqData.status || '',
      complete_address: reqData.complete_address || '',
      special_instruction: reqData.special_instruction || '',
      manager_id: reqData.manager_id != null ? String(reqData.manager_id) : '',
      team_leader_id: reqData.team_leader_id != null ? String(reqData.team_leader_id) : '',
      sales_agent_id: reqData.sales_agent_id != null ? String(reqData.sales_agent_id) : '',
    }
    nextTick(() => { settingFromChild.value = false; settingFromSalesAgent.value = false })
  } catch (e) {
    setErrors(e)
  } finally {
    loading.value = false
  }
}

function validateForm() {
  const err = {}
  if (!form.value.company_name?.trim()) err.company_name = ['Company name is required.']
  if (!form.value.request_type?.trim()) err.request_type = ['Request type is required.']
  if (!form.value.manager_id) err.manager_id = ['Manager is required.']
  if (!form.value.team_leader_id) err.team_leader_id = ['Team leader is required.']
  if (!form.value.sales_agent_id) err.sales_agent_id = ['Sales agent is required.']
  return Object.keys(err).length ? err : null
}

async function save() {
  clearErrors()
  const frontendErrors = validateForm()
  if (frontendErrors) {
    errors.value = frontendErrors
    generalMessage.value = 'Please correct the errors below.'
    return
  }
  saving.value = true
  try {
    await specialRequestsApi.updateRequest(id.value, {
      company_name: form.value.company_name?.trim(),
      account_number: form.value.account_number?.trim() || null,
      request_type: form.value.request_type?.trim(),
      status: form.value.status || undefined,
      complete_address: form.value.complete_address?.trim() || null,
      special_instruction: form.value.special_instruction?.trim() || null,
      manager_id: Number(form.value.manager_id),
      team_leader_id: Number(form.value.team_leader_id),
      sales_agent_id: Number(form.value.sales_agent_id),
    })
    toast('success', 'Special request updated successfully.')
    setTimeout(() => router.push(`/special-requests/${id.value}`), 800)
  } catch (e) {
    setErrors(e)
    toast('error', 'Failed to update.')
  } finally {
    saving.value = false
  }
}

function goBack() { router.push(`/special-requests/${id.value}`) }

const inputClass = (field) =>
  `w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500 ${getError(field) ? 'border-red-500' : 'border-gray-300'}`
const selectClass = (field) =>
  `w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 focus:border-green-500 focus:ring-1 focus:ring-green-500 ${getError(field) ? 'border-red-500' : 'border-gray-300'}`

onMounted(() => loadData())
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] p-0">
    <div class="w-full">
      <div class="border border-black">
        <div class="px-4 py-4 sm:px-5">
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-xl font-semibold text-gray-900">Edit Special Request</h1>
            <Breadcrumbs />
          </div>
        </div>

        <div class="border-t border-black" />

        <div v-if="loading" class="flex justify-center px-4 py-16 sm:px-5">
          <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
        </div>

        <div v-else class="px-4 py-5 sm:px-5 space-y-6">
          <div v-if="generalMessage || Object.keys(errors).length" class="rounded-lg border border-red-200 bg-red-50 p-4">
            <p class="text-sm font-medium text-red-800">{{ generalMessage }}</p>
            <ul class="mt-2 list-inside list-disc space-y-0.5 text-sm text-red-700">
              <li v-for="(msgs, field) in errors" :key="field">{{ getError(field) }}</li>
            </ul>
          </div>

          <!-- Primary Information -->
          <section>
            <h2 class="mb-4 border-b border-gray-200 pb-2 text-base font-semibold text-gray-900">Primary Information</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
                  <option value="">Select</option>
                  <option v-for="t in REQUEST_TYPES" :key="t" :value="t">{{ t }}</option>
                </select>
                <p v-if="getError('request_type')" class="mt-1 text-sm text-red-600">{{ getError('request_type') }}</p>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                <select v-model="form.status" :class="selectClass('status')">
                  <option value="">Select</option>
                  <option v-for="s in STATUSES" :key="s" :value="s">{{ s.charAt(0).toUpperCase() + s.slice(1) }}</option>
                </select>
              </div>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Complete Address</label>
                <textarea v-model="form.complete_address" rows="3" placeholder="Enter complete address" :class="inputClass('complete_address')" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Any Special Instruction</label>
                <textarea v-model="form.special_instruction" rows="3" placeholder="Enter Special Instruction" :class="inputClass('special_instruction')" />
              </div>
            </div>
          </section>

          <!-- Team Information -->
          <section>
            <h2 class="mb-4 border-b border-gray-200 pb-2 text-base font-semibold text-gray-900">Team Information</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Manager <span class="text-red-500">*</span></label>
                <select v-model="form.manager_id" :class="selectClass('manager_id')" @change="clearFieldError('manager_id')">
                  <option value="">Select Manager</option>
                  <option v-for="u in teamOptions.managers" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
                <p v-if="getError('manager_id')" class="mt-1 text-sm text-red-600">{{ getError('manager_id') }}</p>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Team Leader <span class="text-red-500">*</span></label>
                <select v-model="form.team_leader_id" :class="selectClass('team_leader_id')" @change="clearFieldError('team_leader_id')">
                  <option value="">Select Team Leader</option>
                  <option v-for="u in filteredTeamLeaders" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
                <p v-if="getError('team_leader_id')" class="mt-1 text-sm text-red-600">{{ getError('team_leader_id') }}</p>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Sales Agent <span class="text-red-500">*</span></label>
                <select v-model="form.sales_agent_id" :class="selectClass('sales_agent_id')" @change="clearFieldError('sales_agent_id')">
                  <option value="">Select Sales Agent</option>
                  <option v-for="u in filteredSalesAgents" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
                <p v-if="getError('sales_agent_id')" class="mt-1 text-sm text-red-600">{{ getError('sales_agent_id') }}</p>
              </div>
            </div>
          </section>
        </div>

        <div v-if="!loading" class="border-t border-black px-4 py-4 sm:px-5">
          <div class="flex justify-end gap-3">
            <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50" @click="goBack">Cancel</button>
            <button type="button" :disabled="saving" class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-green-700 disabled:opacity-50" @click="save">
              {{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
