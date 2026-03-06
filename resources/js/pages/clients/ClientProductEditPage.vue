<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import DateInputDdMmYyyy from '@/components/DateInputDdMmYyyy.vue'
import clientsApi from '@/services/clientsApi'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const fieldErrors = ref({})

const productId = computed(() => Number(route.params.id || 0))

const closeTarget = computed(() => {
  const returnTo = String(route.query.return_to || '').trim()
  if (returnTo.startsWith('/')) return returnTo
  return '/clients'
})

const form = ref({
  company_name: '',
  submitted_at: '',
  manager_id: null,
  team_leader_id: null,
  sales_agent_id: null,
  submission_type: '',
  service_category: '',
  service_type: '',
  product_type: '',
  address: '',
  product_name: '',
  mrc: '',
  quantity: '',
  other: '',
  migration_numbers: '',
  activity: '',
  account_number: '',
  wo_number: '',
  work_order_status: '',
  activation_date: '',
  contract_type: '',
  contract_end_date: '',
  clawback_chum: '',
  remarks: '',
  additional_notes: '',
})

const teamOptions = ref({
  managers: [],
  team_leaders: [],
  sales_agents: [],
})

const dropdowns = ref({
  submission_types: [],
  service_categories: [],
  service_types: [],
  product_types: [],
  product_types_by_category: {},
  work_order_statuses: [],
  contract_types: [],
  clawback_chum_options: [],
})
const productTypeOptions = computed(() => {
  const category = form.value.service_category || ''
  const mapped = dropdowns.value.product_types_by_category?.[category]
  if (!mapped) return dropdowns.value.product_types
  return ensureValueOption(mapped, form.value.product_type)
})

const filteredTeamLeaders = computed(() => {
  const mid = form.value.manager_id
  if (!mid) return teamOptions.value.team_leaders
  return teamOptions.value.team_leaders.filter((t) => String(t.manager_id) === String(mid))
})

const filteredSalesAgents = computed(() => {
  const tid = form.value.team_leader_id
  if (!tid) return teamOptions.value.sales_agents
  return teamOptions.value.sales_agents.filter((s) => String(s.team_leader_id) === String(tid))
})

function mapToYmd(val) {
  if (!val) return ''
  return String(val).slice(0, 10)
}

const REQUIRED_FIELDS = {
  manager_id: 'Manager Name',
  team_leader_id: 'Team Leader',
  sales_agent_id: 'Sales Agent Name',
  submission_type: 'Submission Type',
  service_category: 'Service Category',
  activity: 'Activity',
  work_order_status: 'Work Order Status',
  activation_date: 'Activation Date',
  clawback_chum: 'Clawback / Chum',
  remarks: 'Remarks',
}

function hasValue(val) {
  if (val == null) return false
  if (typeof val === 'string') return val.trim() !== ''
  return true
}

function validateForm() {
  const errs = {}
  for (const [key, label] of Object.entries(REQUIRED_FIELDS)) {
    if (!hasValue(form.value[key])) errs[key] = `${label} is required.`
  }
  fieldErrors.value = errs
  return Object.keys(errs).length === 0
}

function inputClass(field) {
  return fieldErrors.value[field]
    ? 'mt-1 block w-full rounded border border-red-500 px-3 py-2 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500'
    : 'mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary'
}

function selectClass(field) {
  return inputClass(field)
}

function ensureValueOption(list, value) {
  const out = Array.isArray(list) ? [...list] : []
  const v = value == null ? '' : String(value).trim()
  if (!v) return out
  if (!out.some((item) => String(item).trim() === v)) out.unshift(v)
  return out
}

async function loadInitial() {
  loading.value = true
  error.value = ''
  try {
    const [productRes, filtersRes] = await Promise.all([
      clientsApi.show(productId.value),
      clientsApi.filters(),
    ])

    const data = productRes?.data ?? productRes ?? {}
    const filters = filtersRes?.data ?? filtersRes ?? {}

    const currentSubmissionType = data.submission_type ?? data.request_type ?? ''
    const currentServiceCategory = data.service_category ?? ''
    const currentServiceType = data.service_type ?? ''
    const currentProductType = data.product_type ?? ''
    const currentWorkOrderStatus = data.work_order_status ?? ''
    const currentContractType = data.contract_type ?? ''
    const currentClawbackChum = data.clawback_chum ?? ''

    teamOptions.value = {
      managers: filters.managers ?? [],
      team_leaders: filters.team_leaders ?? [],
      sales_agents: filters.sales_agents ?? [],
    }
    dropdowns.value = {
      submission_types: ensureValueOption(filters.submission_types, currentSubmissionType),
      service_categories: ensureValueOption(filters.service_categories, currentServiceCategory),
      service_types: ensureValueOption(filters.service_types, currentServiceType),
      product_types: ensureValueOption(filters.product_types, currentProductType),
      product_types_by_category: filters.product_types_by_category ?? {},
      work_order_statuses: ensureValueOption(filters.work_order_statuses, currentWorkOrderStatus),
      contract_types: ensureValueOption(filters.contract_types, currentContractType),
      clawback_chum_options: ensureValueOption(filters.clawback_chum_options, currentClawbackChum),
    }

    form.value = {
      company_name: data.company_name ?? '',
      submitted_at: mapToYmd(data.submitted_at),
      manager_id: data.manager_id ?? null,
      team_leader_id: data.team_leader_id ?? null,
      sales_agent_id: data.sales_agent_id ?? null,
      submission_type: currentSubmissionType,
      service_category: currentServiceCategory,
      service_type: data.service_type ?? '',
      product_type: data.product_type ?? '',
      address: data.address ?? '',
      product_name: data.product_name ?? '',
      mrc: data.mrc ?? '',
      quantity: data.quantity ?? '',
      other: data.other ?? '',
      migration_numbers: data.migration_numbers ?? '',
      activity: data.activity ?? data.product_activity ?? '',
      account_number: data.account_number ?? '',
      wo_number: data.wo_number ?? '',
      work_order_status: currentWorkOrderStatus,
      activation_date: mapToYmd(data.activation_date),
      contract_type: currentContractType,
      contract_end_date: mapToYmd(data.contract_end_date),
      clawback_chum: currentClawbackChum,
      remarks: data.remarks ?? data.note ?? '',
      additional_notes: data.additional_notes ?? '',
    }
  } catch (e) {
    error.value = e?.response?.data?.message || 'Unable to load product.'
  } finally {
    loading.value = false
  }
}

function goClose() {
  router.push(closeTarget.value)
}

watch(
  () => form.value.service_category,
  () => {
    if (!productTypeOptions.value.includes(form.value.product_type)) {
      form.value.product_type = ''
    }
  }
)

async function save() {
  if (!validateForm()) {
    error.value = 'Please fill all required fields.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    const payload = {
      company_name: form.value.company_name || null,
      submitted_at: form.value.submitted_at || null,
      manager_id: form.value.manager_id || null,
      team_leader_id: form.value.team_leader_id || null,
      sales_agent_id: form.value.sales_agent_id || null,
      submission_type: form.value.submission_type || null,
      service_category: form.value.service_category || null,
      service_type: form.value.service_type || null,
      product_type: form.value.product_type || null,
      address: form.value.address || null,
      product_name: form.value.product_name || null,
      mrc: form.value.mrc || null,
      quantity: form.value.quantity === '' ? null : Number(form.value.quantity),
      other: form.value.other || null,
      migration_numbers: form.value.migration_numbers || null,
      activity: form.value.activity || null,
      account_number: form.value.account_number || null,
      wo_number: form.value.wo_number || null,
      work_order_status: form.value.work_order_status || null,
      activation_date: form.value.activation_date || null,
      contract_type: form.value.contract_type || null,
      contract_end_date: form.value.contract_end_date || null,
      clawback_chum: form.value.clawback_chum || null,
      remarks: form.value.remarks || null,
      additional_notes: form.value.additional_notes || null,
      create_renewal_record: false,
    }
    await clientsApi.inlineUpdate(productId.value, payload)
    router.push({
      path: `/clients/products/${productId.value}`,
      query: { return_to: closeTarget.value },
    })
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to update Product & Service.'
  } finally {
    saving.value = false
  }
}

onMounted(loadInitial)
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-gray-100 p-0">
    <div class="w-full">
      <div class="mb-4 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-baseline gap-2">
            <h1 class="text-xl font-semibold text-gray-900">Edit Product & Service</h1>          </div>
          <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="goClose">
            Back
          </button>
        </div>
      </div>

      <div v-if="loading" class="flex justify-center py-16">
        <svg class="h-10 w-10 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <div v-else class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="px-6 py-5">
          <p v-if="error" class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

          <h2 class="mb-4 text-sm font-semibold text-gray-900">Product or Service</h2>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
              <label class="block text-sm font-medium text-gray-700">Company Name</label>
              <input
                v-model="form.company_name"
                type="text"
                readonly
                class="mt-1 block w-full rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-700"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Submission Date</label>
              <DateInputDdMmYyyy v-model="form.submitted_at" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Manager Name <span class="text-red-600">*</span></label>
              <select v-model="form.manager_id" :class="selectClass('manager_id')">
                <option :value="null">Select</option>
                <option v-for="m in teamOptions.managers" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
              <p v-if="fieldErrors.manager_id" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.manager_id }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Team Leader <span class="text-red-600">*</span></label>
              <select v-model="form.team_leader_id" :class="selectClass('team_leader_id')">
                <option :value="null">Select</option>
                <option v-for="t in filteredTeamLeaders" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
              <p v-if="fieldErrors.team_leader_id" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.team_leader_id }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Sales Agent Name <span class="text-red-600">*</span></label>
              <select v-model="form.sales_agent_id" :class="selectClass('sales_agent_id')">
                <option :value="null">Select</option>
                <option v-for="s in filteredSalesAgents" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
              <p v-if="fieldErrors.sales_agent_id" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.sales_agent_id }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Submission Type <span class="text-red-600">*</span></label>
              <select v-model="form.submission_type" :class="selectClass('submission_type')">
                <option value="">Select</option>
                <option v-for="v in dropdowns.submission_types" :key="v" :value="v">{{ v }}</option>
              </select>
              <p v-if="fieldErrors.submission_type" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.submission_type }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Service Category <span class="text-red-600">*</span></label>
              <select v-model="form.service_category" :class="selectClass('service_category')">
                <option value="">Select</option>
                <option v-for="v in dropdowns.service_categories" :key="v" :value="v">{{ v }}</option>
              </select>
              <p v-if="fieldErrors.service_category" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.service_category }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Service Type</label>
              <select v-model="form.service_type" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
                <option value="">Select</option>
                <option v-for="v in dropdowns.service_types" :key="v" :value="v">{{ v }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Product Type</label>
              <select v-model="form.product_type" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
                <option value="">Select</option>
                <option v-for="v in productTypeOptions" :key="v" :value="v">{{ v }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Address</label>
              <input v-model="form.address" type="text" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Product Name</label>
              <input v-model="form.product_name" type="text" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">MRC</label>
              <input v-model="form.mrc" type="text" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Quantity</label>
              <input v-model="form.quantity" type="number" min="0" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Offer</label>
              <input v-model="form.other" type="text" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Migration Numbers</label>
              <input v-model="form.migration_numbers" type="text" placeholder="Enter Migration / FNP Number" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Activity <span class="text-red-600">*</span></label>
              <input v-model="form.activity" type="text" :class="inputClass('activity')">
              <p v-if="fieldErrors.activity" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.activity }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Account Number</label>
              <input
                v-model="form.account_number"
                type="text"
                readonly
                class="mt-1 block w-full rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-700"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Work Order</label>
              <input v-model="form.wo_number" type="text" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Work Order Status <span class="text-red-600">*</span></label>
              <select v-model="form.work_order_status" :class="selectClass('work_order_status')">
                <option value="">Select</option>
                <option v-for="v in dropdowns.work_order_statuses" :key="v" :value="v">{{ v }}</option>
              </select>
              <p v-if="fieldErrors.work_order_status" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.work_order_status }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Activation Date <span class="text-red-600">*</span></label>
              <DateInputDdMmYyyy v-model="form.activation_date" placeholder="DD-MMM-YYYY" :input-class="fieldErrors.activation_date ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''" />
              <p v-if="fieldErrors.activation_date" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.activation_date }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Contract Type Term</label>
              <select v-model="form.contract_type" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary">
                <option value="">Select</option>
                <option v-for="v in dropdowns.contract_types" :key="v" :value="v">{{ v }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Contract End Date</label>
              <DateInputDdMmYyyy v-model="form.contract_end_date" placeholder="DD-MMM-YYYY" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Clawback / Chum <span class="text-red-600">*</span></label>
              <select v-model="form.clawback_chum" :class="selectClass('clawback_chum')">
                <option value="">Select</option>
                <option value="Clawback">Clawback</option>
                <option value="Churn">Churn</option>
              </select>
              <p v-if="fieldErrors.clawback_chum" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.clawback_chum }}</p>
            </div>
            <div class="lg:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Remarks <span class="text-red-600">*</span></label>
              <input v-model="form.remarks" type="text" placeholder="Enter remarks if clawback / Churn...... " :class="inputClass('remarks')">
              <p v-if="fieldErrors.remarks" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.remarks }}</p>
            </div>
          </div>

          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700">Additional Note</label>
            <textarea v-model="form.additional_notes" rows="3" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"></textarea>
          </div>

          <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4">
            <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="goClose">
              Back
            </button>
            <button type="button" :disabled="saving" class="rounded bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-70" @click="save">
              {{ saving ? 'Updating...' : 'Update Product & Service' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
