<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import api, { invalidateCurrentDraftCache } from '@/services/leadSubmissionsApi'
import { useFormErrors } from '@/composables/useFormErrors'
import { formatTeamLabel } from '@/composables/useTeamLabel'

const EMIRATES_OPTIONS = [
  'Abu Dhabi',
  'Dubai',
  'Sharjah',
  'Ajman',
  'Umm Al Quwain',
  'Ras Al Khaimah',
  'Fujairah',
]

const props = defineProps({
  leadId: { type: Number, default: null },
  /** When true and leadId is null, do not load current draft (e.g. after "New lead submission"). */
  skipLoadDraft: { type: Boolean, default: false },
})
const router = useRouter()
const emit = defineEmits(['next', 'draft-saved'])

// Draft state
const draftId = ref(null)
const draftDate = ref(null)
const loadingDraft = ref(true)
const discarding = ref(false)

const form = ref({
  account_number: '',
  company_name: '',
  authorized_signatory_name: '',
  contact_number_gsm: '',
  alternate_contact_number: '',
  email: '',
  address: '',
  emirates: '',
  location_coordinates: '25.2048, 55.2708',
  product: '',
  offer: '',
  mrc_aed: '0',
  quantity: '1',
  ae_domain: '',
  gaid: '',
  manager_id: '',
  team_leader_id: '',
  sales_agent_id: '',
  remarks: '',
})

const managers = ref([])
const teamLeaders = ref([])
const salesAgents = ref([])
const settingFromSalesAgent = ref(false)
/** When true, manager_id was set by TL or SA selection – don't clear TL/SA in manager watch */
const settingFromChild = ref(false)
const teamLabels = ref({
  manager: 'Manager Name',
  team_leader: 'Team Leader Name',
  sales_agent: 'Sales Agent Name',
})
const saving = ref(false)
const savingDraft = ref(false)

const { errors, generalMessage, setErrors, clearErrors, clearFieldError, getError } = useFormErrors()

const isResumingDraft = computed(() => !!draftId.value)

// Format date for display
const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Populate form from draft/lead data. Set skipTeamWatchers=true when loading from API so watchers don't clear team dropdowns.
const populateForm = (draft, skipTeamWatchers = false) => {
  if (skipTeamWatchers) {
    settingFromChild.value = true
    settingFromSalesAgent.value = true
  }
  form.value = {
    account_number: draft.account_number || '',
    company_name: draft.company_name || '',
    authorized_signatory_name: draft.authorized_signatory_name || '',
    contact_number_gsm: draft.contact_number_gsm || '',
    alternate_contact_number: draft.alternate_contact_number || '',
    email: draft.email || '',
    address: draft.address || '',
    emirates: draft.emirate || '',
    location_coordinates: draft.location_coordinates || '25.2048, 55.2708',
    product: draft.product || '',
    offer: draft.offer || '',
    mrc_aed: draft.mrc_aed != null ? String(Math.max(0, parseInt(draft.mrc_aed, 10) || 0)) : '0',
    quantity: draft.quantity ?? '1',
    ae_domain: draft.ae_domain || '',
    gaid: draft.gaid || '',
    manager_id: draft.manager_id != null ? String(draft.manager_id) : '',
    team_leader_id: draft.team_leader_id != null ? String(draft.team_leader_id) : '',
    sales_agent_id: draft.sales_agent_id != null ? String(draft.sales_agent_id) : '',
    remarks: draft.remarks || '',
  }
  if (skipTeamWatchers) {
    nextTick(() => {
      settingFromChild.value = false
      settingFromSalesAgent.value = false
    })
  }
}

// Reset form to empty state
const resetForm = () => {
  form.value = {
    account_number: '',
    company_name: '',
    authorized_signatory_name: '',
    contact_number_gsm: '',
    alternate_contact_number: '',
    email: '',
    address: '',
    emirates: '',
    location_coordinates: '25.2048, 55.2708',
    product: '',
    offer: '',
    mrc_aed: '0.00',
    quantity: '1',
    ae_domain: '',
    gaid: '',
    manager_id: '',
    team_leader_id: '',
    sales_agent_id: '',
    remarks: '',
  }
  draftId.value = null
  draftDate.value = null
  clearErrors()
}

// Filter team leaders by manager; clear team_leader and sales_agent when manager changes
const filteredTeamLeaders = computed(() => {
  const mid = form.value.manager_id
  if (!mid) return teamLeaders.value
  return teamLeaders.value.filter((t) => String(t.manager_id) === String(mid))
})

// Filter sales agents by team leader
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

// Auto-fill manager and team leader when sales agent is selected; clear parents when none selected
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
    const teamRes = await api.getTeamOptions()
    managers.value = teamRes.data.managers || []
    teamLeaders.value = teamRes.data.team_leaders || []
    salesAgents.value = teamRes.data.sales_agents || []
    if (teamRes.data.labels) {
      teamLabels.value = { ...teamLabels.value, ...teamRes.data.labels }
    }

    // When wizard passes leadId, always use that lead so we never mix two different drafts (e.g. getCurrentDraft returning another id).
    if (props.leadId) {
      draftId.value = props.leadId
      try {
        const leadRes = await api.getLead(props.leadId)
        const lead = leadRes?.data
        if (lead) {
          draftDate.value = lead.updated_at
          populateForm(lead, true)
        }
      } catch (_) {
        // Keep draftId as props.leadId; form stays empty so user can fill and save
      }
    } else if (!props.skipLoadDraft) {
      const draftRes = await api.getCurrentDraft()
      if (draftRes.data.draft) {
        const draft = draftRes.data.draft
        draftId.value = draft.id
        draftDate.value = draft.updated_at
        populateForm(draft, true)
      }
    }
  } catch (_) {
    // Silent fail for team options
  } finally {
    loadingDraft.value = false
  }
})

const inputClass = (field) => {
  const hasError = errors.value && errors.value[field]
  return `w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500 ${hasError ? 'border-red-500' : 'border-gray-300'}`
}

// Discard draft and start fresh
const discardAndStartFresh = async () => {
  if (!draftId.value) return
  
  discarding.value = true
  try {
    await api.discardDraft(draftId.value)
    invalidateCurrentDraftCache()
    resetForm()
  } catch (e) {
    setErrors(e)
  } finally {
    discarding.value = false
  }
}

// Save draft (create or update) – minimal validation
const saveDraft = async () => {
  clearErrors()
  if (!form.value.company_name?.trim()) {
    errors.value = { company_name: ['Company name is required.'] }
    generalMessage.value = 'Please correct the errors below.'
    return
  }
  if (form.value.ae_domain?.trim()) {
    const aeResult = validateAeDomain(form.value.ae_domain)
    if (!aeResult.valid) {
      errors.value = { ae_domain: [aeResult.message] }
      generalMessage.value = 'Please correct the errors below.'
      return
    }
  }
  savingDraft.value = true
  try {
    let response
    if (draftId.value) {
      response = await api.updateStep1(draftId.value, form.value, true)
    } else {
      response = await api.storeStep1(form.value, true)
    }
    const data = response.data
    if (data?.id) {
      draftId.value = data.id
      draftDate.value = new Date().toISOString()
      invalidateCurrentDraftCache()
      emit('draft-saved', data.id)
    }
  } catch (e) {
    setErrors(e)
  } finally {
    savingDraft.value = false
  }
}

// .ae Domain validation (same rules as backend)
const AE_DOMAIN_FORBIDDEN_KEYWORDS = ['lac', 'rac', 'rat', 'sgns']
const AE_DOMAIN_SPECIAL = /[@#$%^&*()\-+={}\[\]:;'"\\<>,_?/!_`|\s]/
function validateAeDomain(value) {
  const v = (value || '').trim()
  if (!v) return { valid: false, message: 'Enter Domain Name' }
  if (/\s/.test(v)) return { valid: false, message: 'The domain must not contain spaces.' }
  if (/[0-9]/.test(v)) return { valid: false, message: 'The domain must not contain numbers (0–9).' }
  if (AE_DOMAIN_SPECIAL.test(v)) return { valid: false, message: 'The domain must not contain special characters such as: @ # $ % ^ & * ( ) - + = { } [ ] : ; \' " \\ <> , ? / ! _ ` |' }
  const lower = v.toLowerCase()
  for (const kw of AE_DOMAIN_FORBIDDEN_KEYWORDS) {
    if (lower.includes(kw)) return { valid: false, message: 'The domain must not contain these keywords (case-insensitive): LAC, RAC, RAT, SGNS.' }
  }
  if ((v.match(/\./g) || []).length !== 1) return { valid: false, message: 'The domain must contain only one dot (.).' }
  if (!lower.endsWith('.ae')) return { valid: false, message: 'The domain must end with .ae (example: example.ae).' }
  return { valid: true, message: 'You can use this Domain.' }
}

const aeDomainValidation = computed(() => validateAeDomain(form.value.ae_domain))

// Frontend validation for required fields (Step 1)
const validateStep1 = () => {
  const err = {}
  if (!form.value.company_name?.trim()) err.company_name = ['Company name is required.']
  if (!form.value.contact_number_gsm?.trim()) err.contact_number_gsm = ['Contact number (GSM) is required.']
  if (!form.value.address?.trim()) err.address = ['Complete address is required.']
  if (!form.value.emirates?.trim()) err.emirates = ['Emirates is required.']
  if (!form.value.product?.trim()) err.product = ['Product is required.']
  const aeResult = validateAeDomain(form.value.ae_domain)
  if (!aeResult.valid) err.ae_domain = [aeResult.message]
  if (!form.value.manager_id) err.manager_id = [`${formatTeamLabel(teamLabels.value.manager || 'manager')} is required.`]
  if (!form.value.team_leader_id) err.team_leader_id = [`${formatTeamLabel(teamLabels.value.team_leader || 'team_leader')} is required.`]
  if (!form.value.sales_agent_id) err.sales_agent_id = [`${formatTeamLabel(teamLabels.value.sales_agent || 'sales_agent')} is required.`]
  if (form.value.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) err.email = ['Please enter a valid email address.']
  if (form.value.mrc_aed && (isNaN(parseInt(form.value.mrc_aed, 10)) || parseInt(form.value.mrc_aed, 10) < 0)) err.mrc_aed = ['MRC must be a valid whole number (0 or more).']
  if (form.value.quantity && (parseInt(form.value.quantity, 10) < 0 || !Number.isInteger(Number(form.value.quantity)))) err.quantity = ['Quantity must be a whole number.']
  return Object.keys(err).length ? err : null
}

// MRC (AED) increment/decrement – start 0, step 1, up/down inside field
const mrcUp = () => {
  const v = Math.max(0, parseInt(form.value.mrc_aed, 10) || 0)
  form.value.mrc_aed = String(v + 1)
  clearFieldError('mrc_aed')
}
const mrcDown = () => {
  const v = Math.max(0, parseInt(form.value.mrc_aed, 10) || 0)
  form.value.mrc_aed = String(Math.max(0, v - 1))
  clearFieldError('mrc_aed')
}
const onMrcInput = (e) => {
  const v = e.target.value.replace(/\D/g, '')
  form.value.mrc_aed = v === '' ? '0' : String(parseInt(v, 10) || 0)
  clearFieldError('mrc_aed')
}
const onGaidInput = (e) => {
  form.value.gaid = e.target.value
  clearFieldError('gaid')
}

// Submit and go to next step
const submit = async () => {
  clearErrors()
  const frontendErrors = validateStep1()
  if (frontendErrors) {
    errors.value = frontendErrors
    generalMessage.value = 'Please correct the errors below.'
    return
  }
  saving.value = true
  try {
    let response
    if (draftId.value) {
      response = await api.updateStep1(draftId.value, form.value)
    } else {
      response = await api.storeStep1(form.value)
    }
    const data = response.data
    if (data?.id) {
      invalidateCurrentDraftCache()
      emit('next', data.id)
    }
  } catch (e) {
    setErrors(e)
  } finally {
    saving.value = false
  }
}

const cancel = () => {
  router.push('/submissions')
}
</script>

<template>
  <!-- Loading state -->
  <div v-if="loadingDraft" class="flex items-center justify-center py-12">
    <svg class="animate-spin h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <span class="ml-3 text-gray-600">Loading...</span>
  </div>

  <div v-else class="space-y-6">
    <!-- Resume Draft Banner -->
    <div v-if="isResumingDraft" class="rounded-lg bg-blue-50 border border-blue-200 p-4 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <p class="text-sm font-medium text-blue-800">Resuming your draft</p>
          <p class="text-xs text-blue-600">Last saved: {{ formatDate(draftDate) }}</p>
        </div>
      </div>
      <button
        type="button"
        @click="discardAndStartFresh"
        :disabled="discarding"
        class="text-sm text-blue-600 hover:text-blue-800 underline disabled:opacity-50"
      >
        {{ discarding ? 'Discarding...' : 'Start Fresh' }}
      </button>
    </div>

    <form @submit.prevent="submit" class="space-y-8">
      <!-- Validation errors summary -->
      <div v-if="generalMessage || Object.keys(errors).length" class="rounded-lg bg-red-50 border border-red-200 p-4">
        <p class="text-sm font-medium text-red-800">{{ generalMessage }}</p>
        <ul v-if="Object.keys(errors).length > 0" class="mt-2 text-sm text-red-700 list-disc list-inside space-y-0.5">
          <li v-for="(msgs, field) in errors" :key="field">{{ getError(field) }}</li>
        </ul>
      </div>

      <!-- Primary Information -->
      <div>
        <h3 class="text-base font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">
          Primary Information
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Company Name as per Trade License <span class="text-red-500">*</span></label>
            <input
              v-model="form.company_name"
              type="text"
              placeholder="Enter company name"
              :class="inputClass('company_name')"
            />
            <p v-if="getError('company_name')" class="mt-1 text-sm text-red-600">{{ getError('company_name') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Authorized Signatory Name</label>
            <input
              v-model="form.authorized_signatory_name"
              type="text"
              placeholder="Enter signatory name"
              :class="inputClass('authorized_signatory_name')"
              @input="clearFieldError('authorized_signatory_name')"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number (GSM) <span class="text-red-500">*</span></label>
            <input
              v-model="form.contact_number_gsm"
              type="text"
              placeholder="+971 XX XXX XXXX"
              :class="inputClass('contact_number_gsm')"
            />
            <p v-if="getError('contact_number_gsm')" class="mt-1 text-sm text-red-600">{{ getError('contact_number_gsm') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Alternate Contact Number</label>
            <input
              v-model="form.alternate_contact_number"
              type="text"
              placeholder="+971 XX XXX XXXX"
              :class="inputClass('alternate_contact_number')"
              @input="clearFieldError('alternate_contact_number')"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email ID</label>
            <input
              v-model="form.email"
              type="email"
              placeholder="email@example.com"
              :class="inputClass('email')"
            />
            <p v-if="getError('email')" class="mt-1 text-sm text-red-600">{{ getError('email') }}</p>
          </div>
        </div>
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div class="md:col-span-2 lg:col-span-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Complete Address as per Ejari <span class="text-red-500">*</span></label>
            <textarea
              v-model="form.address"
              rows="2"
              placeholder="Enter complete address"
              :class="inputClass('address')"
              @input="clearFieldError('address')"
            />
            <p v-if="getError('address')" class="mt-1 text-sm text-red-600">{{ getError('address') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Emirates <span class="text-red-500">*</span></label>
            <select
              v-model="form.emirates"
              :class="inputClass('emirates')"
            >
              <option value="">Select Emirates</option>
              <option v-for="e in EMIRATES_OPTIONS" :key="e" :value="e">{{ e }}</option>
            </select>
            <p v-if="getError('emirates')" class="mt-1 text-sm text-red-600">{{ getError('emirates') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Location Coordinates</label>
            <input
              v-model="form.location_coordinates"
              type="text"
              readonly
              class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-600"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Product <span class="text-red-500">*</span></label>
            <input
              v-model="form.product"
              type="text"
              placeholder="Search Product"
              :class="inputClass('product')"
              @input="clearFieldError('product')"
            />
            <p v-if="getError('product')" class="mt-1 text-sm text-red-600">{{ getError('product') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Offer</label>
            <input
              v-model="form.offer"
              type="text"
              placeholder="Enter offer details"
              :class="inputClass('offer')"
              @input="clearFieldError('offer')"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">MRC (AED)</label>
            <div class="flex w-full rounded-lg border overflow-hidden bg-white items-stretch" :class="getError('mrc_aed') ? 'border-red-500' : 'border-gray-300'">
              <input
                :value="form.mrc_aed"
                type="text"
                inputmode="numeric"
                placeholder="0"
                :class="['min-w-0 flex-1 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-green-500 border-0', getError('mrc_aed') ? 'border-red-500' : '']"
                @input="onMrcInput"
              />
              <div class="flex border-l border-gray-300 shrink-0 bg-gray-50">
                <button type="button" @click="mrcUp" class="px-2 py-1 text-gray-600 hover:bg-gray-100 border-r border-gray-200 focus:outline-none flex items-center justify-center" aria-label="Increase MRC">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                </button>
                <button type="button" @click="mrcDown" class="px-2 py-1 text-gray-600 hover:bg-gray-100 focus:outline-none flex items-center justify-center" aria-label="Decrease MRC">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
              </div>
            </div>
            <p v-if="getError('mrc_aed')" class="mt-1 text-sm text-red-600">{{ getError('mrc_aed') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
            <input
              :value="form.quantity"
              type="text"
              inputmode="numeric"
              placeholder="1"
              :class="inputClass('quantity')"
              @input="onQuantityInput"
            />
            <p v-if="getError('quantity')" class="mt-1 text-sm text-red-600">{{ getError('quantity') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">.ae Domain</label>
            <input
              v-model="form.ae_domain"
              type="text"
              placeholder="Enter Domain (e.g. example.ae)"
              :class="inputClass('ae_domain')"
              @input="clearFieldError('ae_domain')"
            />
            <p v-if="getError('ae_domain')" class="mt-1 text-sm text-red-600">{{ getError('ae_domain') }}</p>
            <p v-else-if="form.ae_domain?.trim() && aeDomainValidation.valid" class="mt-1 text-sm text-green-600">
              {{ aeDomainValidation.message }}
            </p>
            <p v-else-if="form.ae_domain?.trim() && !aeDomainValidation.valid" class="mt-1 text-sm text-red-600">
              {{ aeDomainValidation.message }}
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">GAID</label>
            <input
              :value="form.gaid"
              type="text"
              placeholder="Enter GAID"
              :class="inputClass('gaid')"
              @input="onGaidInput"
            />
            <p v-if="getError('gaid')" class="mt-1 text-sm text-red-600">{{ getError('gaid') }}</p>
          </div>
        </div>
      </div>

      <!-- Team Information -->
      <div>
        <h3 class="text-base font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">
          Team Information
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ formatTeamLabel(teamLabels.manager || 'manager') }} Name <span class="text-red-500">*</span></label>
            <select
              v-model="form.manager_id"
              :class="inputClass('manager_id')"
              @change="clearFieldError('manager_id')"
            >
              <option value="">Select {{ formatTeamLabel(teamLabels.manager || 'manager') }}</option>
              <option v-for="u in managers" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
            </select>
            <p v-if="getError('manager_id')" class="mt-1 text-sm text-red-600">{{ getError('manager_id') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ formatTeamLabel(teamLabels.team_leader || 'team_leader') }} Name <span class="text-red-500">*</span></label>
            <select
              v-model="form.team_leader_id"
              :class="inputClass('team_leader_id')"
              @change="clearFieldError('team_leader_id')"
            >
              <option value="">Select {{ formatTeamLabel(teamLabels.team_leader || 'team_leader') }}</option>
              <option v-for="u in filteredTeamLeaders" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
            </select>
            <p v-if="getError('team_leader_id')" class="mt-1 text-sm text-red-600">{{ getError('team_leader_id') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ formatTeamLabel(teamLabels.sales_agent || 'sales_agent') }} Name <span class="text-red-500">*</span></label>
            <select
              v-model="form.sales_agent_id"
              :class="inputClass('sales_agent_id')"
              @change="clearFieldError('sales_agent_id')"
            >
              <option value="">Select {{ formatTeamLabel(teamLabels.sales_agent || 'sales_agent') }}</option>
              <option v-for="u in filteredSalesAgents" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
            </select>
            <p v-if="getError('sales_agent_id')" class="mt-1 text-sm text-red-600">{{ getError('sales_agent_id') }}</p>
          </div>
        </div>
        <div class="mt-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Comment / Remarks</label>
          <textarea
            v-model="form.remarks"
            rows="4"
            placeholder="Enter any additional comments or remarks"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            @input="clearFieldError('remarks')"
          />
          <p v-if="getError('remarks')" class="mt-1 text-sm text-red-600">{{ getError('remarks') }}</p>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-gray-200">
        <div class="flex items-center gap-3">
          <button
            type="button"
            @click="cancel"
            class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50"
          >
            Cancel
          </button>
          <span class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium">
            Step 1
          </span>
        </div>
        <div class="flex items-center gap-3">
          <button
            type="button"
            :disabled="savingDraft"
            @click="saveDraft"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200 disabled:opacity-50"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            {{ savingDraft ? 'Saving...' : 'Save as Draft' }}
          </button>
          <button
            type="submit"
            :disabled="saving"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white text-sm font-medium disabled:opacity-50 bg-[#7ED321] hover:bg-[#6ab81e]"
          >
            Next
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
          </button>
        </div>
      </div>
    </form>
  </div>
</template>
