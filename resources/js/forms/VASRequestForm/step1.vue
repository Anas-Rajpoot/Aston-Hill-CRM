<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import api from '@/services/vasRequestsApi'
import { useFormErrors } from '@/composables/useFormErrors'
import { formatTeamLabel } from '@/composables/useTeamLabel'
import { useSessionFormState } from '@/composables/useSessionFormState'
import { useAuthStore } from '@/stores/auth'
import { DOCUMENT_UPLOAD_EXTENSIONS } from '@/lib/documentUpload'

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
const auth = useAuthStore()
const teamLabels = ref({
  manager: 'Manager Name',
  team_leader: 'Team Leader Name',
  sales_agent: 'Sales Agent Name',
})
const teamSectionCollapsed = ref(false)
const documentsSectionCollapsed = ref(false)
const loading = ref(true)
const saving = ref(false)
const savingDraft = ref(false)
const currentSubmitterName = computed(() => auth.user?.name || '-')

const MAX_FILE_MB = 3
const MAX_TOTAL_MB = 10
const ALLOWED_EXT = DOCUMENT_UPLOAD_EXTENSIONS

const docDefs = ref([])
const existingDocs = ref([])
const files = ref({})
const fileErrors = ref({})
const additionalDocs = ref([])

const MAX_ADDITIONAL_DOCS = 3
const canAddMoreAdditional = computed(() => additionalDocs.value.length < MAX_ADDITIONAL_DOCS)

const { errors, generalMessage, setErrors, clearErrors, clearFieldError, getError } = useFormErrors()

const uploadedDocCount = computed(() => {
  const existing = existingDocs.value.length
  const newSchema = Object.values(files.value).filter((f) => f instanceof File).length
  const newAdditional = additionalDocs.value.filter((ad) => ad.file instanceof File).length
  return existing + newSchema + newAdditional
})

const uploadedStatusText = computed(() => {
  const n = uploadedDocCount.value
  return n === 1 ? '1 document is uploaded' : `${n} documents are uploaded`
})

const schemaFilesTotalBytes = computed(() => {
  let total = 0
  Object.values(files.value).forEach((f) => {
    if (f instanceof File && f.size) total += f.size
  })
  return total
})

const additionalDocsTotalSizeBytes = computed(() => {
  let total = 0
  additionalDocs.value.forEach((ad) => {
    if (ad.file instanceof File && ad.file.size) total += ad.file.size
  })
  return total
})

const allFilesTotalSizeBytes = computed(() => schemaFilesTotalBytes.value + additionalDocsTotalSizeBytes.value)
const allFilesTotalSizeMB = computed(() => (allFilesTotalSizeBytes.value / (1024 * 1024)).toFixed(2))

function getExistingForKey(key) {
  return existingDocs.value.filter((ed) => ed.doc_key === key)
}

function isFnpBinderDocument(doc) {
  const key = String(doc?.key || doc?.doc_key || '').toLowerCase()
  const label = String(doc?.label || doc?.file_name || '').toLowerCase()
  return key === 'fnp_binder' || label.includes('fnp binder')
}

function filterOutFnpBinder(docs = []) {
  return (docs || []).filter((doc) => !isFnpBinderDocument(doc))
}

function validateFile(file) {
  const ext = '.' + (file.name?.split('.').pop() || '').toLowerCase()
  if (!ALLOWED_EXT.includes(ext)) {
    return `File must be one of: ${ALLOWED_EXT.join(', ')}`
  }
  if (file.size > MAX_FILE_MB * 1024 * 1024) {
    return `File must not exceed ${MAX_FILE_MB} MB.`
  }
  return null
}

function onFileChange(key, e) {
  const file = e.target?.files?.[0] || null
  fileErrors.value[key] = null
  if (!file) {
    files.value[key] = null
    e.target.value = ''
    return
  }
  const err = validateFile(file)
  if (err) {
    fileErrors.value[key] = err
    files.value[key] = null
    e.target.value = ''
    return
  }
  const otherSize = allFilesTotalSizeBytes.value - (files.value[key] instanceof File ? files.value[key].size : 0) + file.size
  if (otherSize > MAX_TOTAL_MB * 1024 * 1024) {
    fileErrors.value[key] = `Total size of all files must not exceed ${MAX_TOTAL_MB} MB.`
    files.value[key] = null
    e.target.value = ''
    return
  }
  files.value[key] = file
  e.target.value = ''
}

function addAdditionalDoc() {
  if (additionalDocs.value.length >= MAX_ADDITIONAL_DOCS) return
  additionalDocs.value.push({
    key: 'additional_' + Date.now(),
    label: '',
    file: null,
  })
}

function onAdditionalFileChange(index, e) {
  const file = e.target?.files?.[0] || null
  if (!additionalDocs.value[index]) return
  if (!file) {
    additionalDocs.value[index].file = null
    e.target.value = ''
    return
  }
  const err = validateFile(file)
  if (err) {
    setErrors({ response: { data: { errors: { additional_documents: [err] } } } })
    e.target.value = ''
    return
  }
  const otherSize = schemaFilesTotalBytes.value + additionalDocs.value.reduce((sum, ad, i) => {
    if (i === index) return sum
    return sum + (ad.file instanceof File ? ad.file.size : 0)
  }, 0)
  if (otherSize + file.size > MAX_TOTAL_MB * 1024 * 1024) {
    setErrors({ response: { data: { errors: { additional_documents: [`Total size of all files must not exceed ${MAX_TOTAL_MB} MB.`] } } } })
    e.target.value = ''
    return
  }
  additionalDocs.value[index].file = file
  if (!(additionalDocs.value[index].label || '').trim()) {
    additionalDocs.value[index].label = file.name
  }
  clearFieldError('additional_documents')
  e.target.value = ''
}

function removeAdditionalDoc(index) {
  additionalDocs.value.splice(index, 1)
}

function buildDocumentsFormData() {
  const fd = new FormData()
  Object.entries(files.value).forEach(([key, file]) => {
    if (file instanceof File) fd.append(key, file)
  })
  additionalDocs.value.forEach((ad, i) => {
    if (ad.file instanceof File) {
      fd.append('additional_documents[]', ad.file)
      fd.append('additional_document_label[]', ad.label || 'Additional document ' + (i + 1))
    }
  })
  return fd
}

function hasNewDocumentUploads() {
  const hasSchema = Object.values(files.value).some((f) => f instanceof File)
  const hasAdditional = additionalDocs.value.some((ad) => ad.file instanceof File)
  return hasSchema || hasAdditional
}

function validateDocumentsBeforeNext() {
  const messages = []
  docDefs.value.forEach((d) => {
    if (!d.required) return
    const hasExisting = existingDocs.value.some((ed) => ed.doc_key === d.key)
    const hasNew = files.value[d.key] instanceof File
    if (!hasExisting && !hasNew) {
      messages.push(`${d.label} is required.`)
    }
  })
  const totalMB = allFilesTotalSizeBytes.value / (1024 * 1024)
  if (totalMB > MAX_TOTAL_MB) {
    messages.push(`Total size of all files must not exceed ${MAX_TOTAL_MB} MB.`)
  }
  additionalDocs.value.forEach((ad, i) => {
    if (ad.file instanceof File && !(ad.label || '').trim()) {
      messages.push(`Additional document ${i + 1}: label is required when a file is attached.`)
    }
  })
  if (!messages.length) return true
  setErrors({ response: { data: { errors: { documents: [messages.join(' ')] }, message: 'Please fix the errors below.' } } })
  return false
}

async function initializeDocumentSchema() {
  const schemaRes = await api.getDocumentSchema()
  docDefs.value = filterOutFnpBinder(schemaRes?.data?.documents || []).map((d) => ({ ...d, required: d.key === 'trade_license' }))
  docDefs.value.forEach((d) => {
    if (d.key && files.value[d.key] === undefined) files.value[d.key] = null
  })
}

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
  const filtered = teamLeaders.value.filter((t) => resolveTeamLeaderManagerId(t) === String(mid))
  // Fallback to all role users when hierarchy mapping is missing/incomplete.
  return filtered.length ? filtered : teamLeaders.value
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
    const filteredByTeamLeader = salesAgents.value.filter((salesAgent) => resolveSalesAgentTeamLeaderId(salesAgent) === String(tlId))
    // Fallback to all role users when hierarchy mapping is missing/incomplete.
    return filteredByTeamLeader.length ? filteredByTeamLeader : salesAgents.value
  }
  if (managerId) {
    const filteredByManager = salesAgents.value.filter((salesAgent) => {
      const resolvedManagerId = resolveSalesAgentManagerId(salesAgent)
      const resolvedTeamLeaderId = resolveSalesAgentTeamLeaderId(salesAgent)
      return resolvedManagerId === String(managerId) || teamLeaderIdsUnderManager.has(resolvedTeamLeaderId)
    })
    // Fallback to all role users when hierarchy mapping is missing/incomplete.
    return filteredByManager.length ? filteredByManager : salesAgents.value
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
  loading.value = true
  try {
    await auth.fetchUser()
    const [{ data }, _schemaRes] = await Promise.all([
      api.getTeamOptions(true),
      initializeDocumentSchema(),
    ])
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
        existingDocs.value = filterOutFnpBinder(d.documents || [])
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
    let vasId = props.vasRequestId
    const payload = buildPayload()
    if (vasId) {
      await api.updateStep1(vasId, payload)
    } else {
      const { data } = await api.storeStep1(payload)
      vasId = data.id
    }

    if (vasId && hasNewDocumentUploads()) {
      const fd = buildDocumentsFormData()
      await api.storeStep2(vasId, fd)
      const res = await api.getRequest(vasId)
      existingDocs.value = filterOutFnpBinder(res?.data?.documents || [])
      docDefs.value.forEach((d) => {
        if (files.value[d.key] instanceof File) files.value[d.key] = null
      })
      additionalDocs.value.forEach((ad) => { ad.file = null })
    }
    clearState()
    emit('draft-saved', vasId)
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
    let vasId = props.vasRequestId
    const payload = buildPayload()
    if (vasId) {
      await api.updateStep1(vasId, payload)
    } else {
      const { data } = await api.storeStep1(payload)
      vasId = data.id
    }

    if (!validateDocumentsBeforeNext()) return
    if (vasId && hasNewDocumentUploads()) {
      const fd = buildDocumentsFormData()
      await api.storeStep2(vasId, fd)
    }
    clearState()
    emit('submitted', vasId)
  } catch (e) {
    setErrors(e)
  } finally {
    saving.value = false
  }
}

function cancel() {
  clearState()
  form.value = {
    request_type: '',
    account_number: '',
    contact_number: '',
    company_name: '',
    request_description: '',
    additional_notes: '',
    manager_id: '',
    team_leader_id: '',
    sales_agent_id: '',
  }
  clearErrors()
}

const emit = defineEmits(['submitted', 'draft-saved'])

const inputClass = (field) =>
  `w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary ${getError(field) ? 'border-red-500' : 'border-gray-300'}`
const selectClass = (field) =>
  `w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary ${getError(field) ? 'border-red-500' : 'border-gray-300'}`
</script>

<template>
  <div class="space-y-6">
    <!-- Loading state (same as Lead Submissions) -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="h-8 w-8 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
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
      <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Submitter Name</label>
            <input :value="currentSubmitterName" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-gray-100 text-gray-700" readonly />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Manager Name <span class="text-red-500">*</span></label>
            <select v-model="form.manager_id" :class="inputClass('manager_id')" @change="clearFieldError('manager_id')">
              <option value="">Select Manager Name</option>
              <option v-for="u in managers" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
            </select>
            <p v-if="getError('manager_id')" class="mt-1 text-sm text-red-600">{{ getError('manager_id') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Team Leader Name</label>
            <select v-model="form.team_leader_id" :class="inputClass('team_leader_id')" @change="clearFieldError('team_leader_id')">
              <option value="">Select Team Leader Name</option>
              <option v-for="u in filteredTeamLeaders" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
            </select>
            <p v-if="getError('team_leader_id')" class="mt-1 text-sm text-red-600">{{ getError('team_leader_id') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Sales Agent Name</label>
            <select v-model="form.sales_agent_id" :class="inputClass('sales_agent_id')" @change="clearFieldError('sales_agent_id')">
              <option value="">Select Sales Agent Name</option>
              <option v-for="u in filteredSalesAgents" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
            </select>
            <p v-if="getError('sales_agent_id')" class="mt-1 text-sm text-red-600">{{ getError('sales_agent_id') }}</p>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">
              Request Description <span class="text-red-500">*</span>
            </label>
            <textarea
              v-model="form.request_description"
              rows="3"
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
              rows="3"
              placeholder="Add any additional comments or remarks..."
              :class="inputClass('additional_notes')"
            />
          </div>
        </div>

        <div>
          <button
            type="button"
            class="w-full flex items-center justify-between text-left text-base font-semibold text-gray-900 border-b border-gray-200 pb-2"
            :aria-expanded="(!documentsSectionCollapsed).toString()"
            @click.prevent="documentsSectionCollapsed = !documentsSectionCollapsed"
          >
            <span>Documents</span>
            <div class="flex items-center gap-2 text-sm text-gray-500">
              <span>{{ documentsSectionCollapsed ? 'Show' : 'Minimize' }}</span>
              <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': documentsSectionCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>
          </button>

          <div v-show="!documentsSectionCollapsed" class="mt-4 space-y-4">
            <div>
              <p class="text-sm text-gray-600">{{ uploadedStatusText }}</p>
              <p class="mt-1 text-xs text-gray-500">
                Max {{ MAX_FILE_MB }} MB per file, {{ MAX_TOTAL_MB }} MB total. Allowed: {{ ALLOWED_EXT.join(', ') }}
              </p>

              <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div
                  v-for="doc in docDefs"
                  :key="doc.key"
                  class="flex min-w-0 items-center gap-3 rounded-lg border border-gray-300 bg-white px-3 py-2 shadow-sm"
                  :class="{ 'border-red-300': fileErrors[doc.key] }"
                >
                  <div class="shrink-0 text-gray-500">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900">{{ doc.label }} <span v-if="doc.required" class="text-red-500">*</span></p>
                    <p v-if="fileErrors[doc.key]" class="mt-0.5 text-xs text-red-600">{{ fileErrors[doc.key] }}</p>
                    <p v-else-if="files[doc.key]" class="mt-0.5 truncate text-xs text-gray-600" :title="files[doc.key].name">
                      {{ files[doc.key].name }}
                    </p>
                    <template v-else v-for="ed in getExistingForKey(doc.key)" :key="ed.id">
                      <p class="mt-0.5 truncate text-xs text-gray-600" :title="ed.file_name || ed.label">
                        {{ ed.file_name || ed.label || 'Uploaded' }}
                      </p>
                    </template>
                  </div>
                  <label class="shrink-0 cursor-pointer">
                    <input
                      type="file"
                      class="hidden"
                      :accept="ALLOWED_EXT.join(',')"
                      @change="onFileChange(doc.key, $event)"
                    />
                    <span class="inline-flex items-center gap-1 rounded-lg bg-brand-primary px-2 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-brand-primary-hover">
                      <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                      </svg>
                      Upload
                    </span>
                  </label>
                </div>
              </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <h4 class="text-sm font-semibold text-gray-900">Additional Documents</h4>
                <button
                  type="button"
                  @click="addAdditionalDoc"
                  :disabled="!canAddMoreAdditional"
                  class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-primary hover:text-brand-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  Add Document
                </button>
              </div>
              <p class="mt-2 text-xs text-gray-500">Total size: {{ allFilesTotalSizeMB }} MB / {{ MAX_TOTAL_MB }} MB</p>

              <div v-if="additionalDocs.length" class="mt-4 space-y-3">
                <div
                  v-for="(ad, index) in additionalDocs"
                  :key="ad.key"
                  class="flex min-w-0 flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2.5 shadow-sm"
                >
                  <div class="shrink-0 text-gray-400">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                  <input
                    v-model="ad.label"
                    type="text"
                    :placeholder="'Additional Document ' + (index + 1)"
                    class="min-w-0 flex-1 rounded border border-gray-300 px-3 py-1.5 text-sm placeholder:text-gray-400 focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"
                  />
                  <label class="shrink-0 cursor-pointer">
                    <input
                      type="file"
                      class="hidden"
                      :accept="ALLOWED_EXT.join(',')"
                      @change="onAdditionalFileChange(index, $event)"
                    />
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-brand-primary bg-brand-primary-light px-3 py-1.5 text-sm font-medium text-brand-primary hover:bg-brand-primary-light">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                      </svg>
                      Upload
                    </span>
                  </label>
                  <button
                    type="button"
                    @click="removeAdditionalDoc(index)"
                    class="shrink-0 text-sm text-red-600 hover:underline"
                  >
                    Remove
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-6">
      <div class="flex items-center gap-3">
        <button
          type="button"
          @click="cancel"
          class="rounded-lg bg-brand-primary px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-brand-primary-hover"
        >
          Cancel
        </button>
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
          @click="nextStep"
          :disabled="saving || savingDraft"
          class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white shadow-sm disabled:opacity-50 bg-brand-primary hover:bg-brand-primary-hover"
        >
          {{ saving ? 'Submitting...' : 'Next' }}
          <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
          </svg>
        </button>
      </div>
    </div>
    </template>
  </div>
</template>
