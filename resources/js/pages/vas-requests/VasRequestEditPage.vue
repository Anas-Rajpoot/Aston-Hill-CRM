<script setup>
/**
 * VAS Request Edit – form: request type, account, company, description, team; documents: view, download, remove, add new.
 */
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import vasRequestsApi from '@/services/vasRequestsApi'
import { useAuthStore } from '@/stores/auth'
import { useFormErrors } from '@/composables/useFormErrors'
import Toast from '@/components/Toast.vue'
import { formatUserDate } from '@/lib/dateFormat'

const MAX_FILE_MB = 3
const MAX_TOTAL_MB = 10
const ALLOWED_EXT = ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.png', '.jpg', '.jpeg']

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const saving = ref(false)
const request = ref(null)

/* ───── Toast ───── */
const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }
const teamOptions = ref({
  managers: [],
  team_leaders: [],
  sales_agents: [],
})
const executives = ref([])
const requestTypes = ref([])
const docSchema = ref([])
const newDocFiles = ref({})
const newAdditionalDocs = ref([])
const uploadSaving = ref(false)
const docUploadError = ref('')
const removingDocId = ref(null)
const documentToRemove = ref(null)
const showAddDocs = ref(false)
const addDocsSectionRef = ref(null)
const completionDateRef = ref(null)
const settingFromChild = ref(false)
const settingFromSalesAgent = ref(false)
const initialFormLoad = ref(false)

const VAS_STATUSES = [
  { value: 'submitted_under_process', label: 'Submitted Under Process' },
  { value: 'completed', label: 'Completed' },
  { value: 'rejected', label: 'Rejected' },
]

function normalizeStatus(status) {
  const value = String(status || '').toLowerCase()
  if (!value || value === 'draft' || value === 'pending_with_csr' || value === 'pending_with_du' || value === 'pending_with_sales' || value === 'pending_for_approval') return 'unassigned'
  if (value === 'approved') return 'completed'
  return value
}

const form = ref({
  request_type: '',
  account_number: '',
  contact_number: '',
  company_name: '',
  description: '',
  additional_notes: '',
  status: '',
  manager_id: null,
  team_leader_id: null,
  sales_agent_id: null,
  back_office_executive_id: null,
  activity: '',
  completion_date: '',
  remarks: '',
})

const id = computed(() => {
  const p = route.params.id
  return p != null ? Number(p) : null
})

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
  if (reportsTo && (teamOptions.value.managers ?? []).some((m) => String(m.id) === reportsTo)) return reportsTo
  return ''
}

const filteredTeamLeaders = computed(() => {
  const mid = form.value.manager_id
  if (!mid) return teamOptions.value.team_leaders ?? []
  return (teamOptions.value.team_leaders ?? []).filter((t) => resolveTeamLeaderManagerId(t) === String(mid))
})

const filteredSalesAgents = computed(() => {
  const tlId = form.value.team_leader_id
  const managerId = form.value.manager_id
  const teamLeaderIdsUnderManager = new Set(
    (teamOptions.value.team_leaders ?? [])
      .filter((t) => managerId && resolveTeamLeaderManagerId(t) === String(managerId))
      .map((t) => String(t.id))
  )

  const resolveSalesAgentTeamLeaderId = (salesAgent) => {
    const direct = pickFirstValue(salesAgent, ['team_leader_id', 'teamLeaderId'])
    const reportsTo = pickFirstValue(salesAgent, ['reports_to', 'reportsTo'])
    if (direct) return direct
    if (reportsTo) {
      const asTeamLeader = (teamOptions.value.team_leaders ?? []).some((t) => String(t.id) === String(reportsTo))
      if (asTeamLeader) return String(reportsTo)
    }
    return ''
  }

  const resolveSalesAgentManagerId = (salesAgent) => {
    const direct = pickFirstValue(salesAgent, ['manager_id', 'managerId'])
    const reportsTo = pickFirstValue(salesAgent, ['reports_to', 'reportsTo'])
    if (direct) return direct
    if (reportsTo) {
      const asManager = (teamOptions.value.managers ?? []).some((m) => String(m.id) === String(reportsTo))
      if (asManager) return String(reportsTo)
    }
    const teamLeaderIdFromAgent = resolveSalesAgentTeamLeaderId(salesAgent)
    if (teamLeaderIdFromAgent) {
      const teamLeader = (teamOptions.value.team_leaders ?? []).find((t) => String(t.id) === String(teamLeaderIdFromAgent))
      const managerIdFromLeader = resolveTeamLeaderManagerId(teamLeader)
      if (managerIdFromLeader) return managerIdFromLeader
    }
    return ''
  }

  if (tlId) {
    return (teamOptions.value.sales_agents ?? []).filter((salesAgent) => resolveSalesAgentTeamLeaderId(salesAgent) === String(tlId))
  }
  if (managerId) {
    return (teamOptions.value.sales_agents ?? []).filter((salesAgent) => {
      const resolvedManagerId = resolveSalesAgentManagerId(salesAgent)
      const resolvedTeamLeaderId = resolveSalesAgentTeamLeaderId(salesAgent)
      return resolvedManagerId === String(managerId) || teamLeaderIdsUnderManager.has(resolvedTeamLeaderId)
    })
  }
  return teamOptions.value.sales_agents ?? []
})

const deriveSalesHierarchy = (salesAgent) => {
  let teamLeaderId = pickFirstValue(salesAgent, ['team_leader_id', 'teamLeaderId'])
  let managerId = pickFirstValue(salesAgent, ['manager_id', 'managerId'])
  const reportsTo = pickFirstValue(salesAgent, ['reports_to', 'reportsTo'])

  if (!teamLeaderId && reportsTo && (teamOptions.value.team_leaders ?? []).some((u) => String(u.id) === reportsTo)) {
    teamLeaderId = reportsTo
  }
  if (!managerId && reportsTo && (teamOptions.value.managers ?? []).some((u) => String(u.id) === reportsTo)) {
    managerId = reportsTo
  }
  if (!managerId && teamLeaderId) {
    const teamLeader = (teamOptions.value.team_leaders ?? []).find((u) => String(u.id) === String(teamLeaderId))
    managerId = resolveTeamLeaderManagerId(teamLeader)
  }
  if (!teamLeaderId && managerId) {
    const leaders = (teamOptions.value.team_leaders ?? []).filter((u) => resolveTeamLeaderManagerId(u) === String(managerId))
    if (leaders.length === 1) teamLeaderId = String(leaders[0].id)
  }
  return { teamLeaderId, managerId }
}

async function load() {
  if (!id.value) return
  loading.value = true
  request.value = null
  initialFormLoad.value = true
  try {
    const [reqRes, teamRes, filtersRes, schemaRes, boRes] = await Promise.all([
      vasRequestsApi.getRequest(id.value),
      vasRequestsApi.getTeamOptions().then((r) => r?.data ?? r).catch(() => ({})),
      vasRequestsApi.filters().catch(() => ({})),
      vasRequestsApi.getDocumentSchema().then((r) => r?.data ?? r).catch(() => ({ documents: [] })),
      vasRequestsApi.getBackOfficeOptions().catch(() => ({ executives: [] })),
    ])
    const data = reqRes?.data ?? reqRes
    request.value = data
    teamOptions.value = {
      managers: teamRes.managers ?? [],
      team_leaders: teamRes.team_leaders ?? [],
      sales_agents: teamRes.sales_agents ?? [],
    }
    executives.value = boRes?.executives ?? []
    requestTypes.value = (filtersRes.request_types ?? []).map((t) => (typeof t === 'string' ? t : t?.value ?? t))
    docSchema.value = schemaRes.documents ?? []
    docSchema.value.forEach((d) => {
      if (d.key && newDocFiles.value[d.key] === undefined) newDocFiles.value[d.key] = null
    })
    form.value = {
      request_type: data.request_type ?? '',
      account_number: data.account_number ?? '',
      contact_number: data.contact_number ?? '',
      company_name: data.company_name ?? '',
      description: data.description ?? '',
      additional_notes: data.additional_notes ?? '',
      status: normalizeStatus(data.status),
      manager_id: data.manager_id != null ? data.manager_id : null,
      team_leader_id: data.team_leader_id != null ? data.team_leader_id : null,
      sales_agent_id: data.sales_agent_id != null ? data.sales_agent_id : null,
      back_office_executive_id: data.back_office_executive_id != null ? data.back_office_executive_id : null,
      activity: data.activity ?? '',
      completion_date: data.completion_date ? String(data.completion_date).slice(0, 10) : (data.approved_at ? String(data.approved_at).slice(0, 10) : ''),
      remarks: data.remarks ?? '',
    }
  } catch {
    request.value = null
  } finally {
    nextTick(() => {
      initialFormLoad.value = false
    })
    loading.value = false
    window.scrollTo(0, 0)
  }
}

watch(
  () => form.value.manager_id,
  () => {
    if (initialFormLoad.value) return
    if (settingFromChild.value) {
      nextTick(() => { settingFromChild.value = false })
      return
    }
    form.value.team_leader_id = null
    form.value.sales_agent_id = null
  }
)

watch(
  () => form.value.team_leader_id,
  (id) => {
    if (initialFormLoad.value) return
    if (id) {
      const tl = (teamOptions.value.team_leaders ?? []).find((u) => String(u.id) === String(id))
      const managerId = resolveTeamLeaderManagerId(tl)
      if (managerId) {
        settingFromChild.value = true
        form.value.manager_id = Number(managerId)
        nextTick(() => { settingFromChild.value = false })
      }
      if (!settingFromSalesAgent.value) form.value.sales_agent_id = null
    } else if (!settingFromSalesAgent.value) {
      form.value.sales_agent_id = null
    }
  }
)

watch(
  () => form.value.sales_agent_id,
  (id) => {
    if (initialFormLoad.value) return
    if (id) {
      const sa = (teamOptions.value.sales_agents ?? []).find((u) => String(u.id) === String(id))
      if (sa) {
        const resolved = deriveSalesHierarchy(sa)
        settingFromSalesAgent.value = true
        settingFromChild.value = true
        if (resolved.teamLeaderId) form.value.team_leader_id = Number(resolved.teamLeaderId)
        if (resolved.managerId) form.value.manager_id = Number(resolved.managerId)
        nextTick(() => {
          settingFromSalesAgent.value = false
          settingFromChild.value = false
        })
      }
    }
  }
)

const documents = computed(() => request.value?.documents ?? [])

function docDisplayName(doc) {
  return doc?.file_name || doc?.label || doc?.doc_key || 'Document'
}

function formatFileSize(bytes) {
  if (bytes == null || bytes === '') return ''
  const n = Number(bytes)
  if (Number.isNaN(n) || n < 0) return ''
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  return `${(n / (1024 * 1024)).toFixed(1)} MB`
}

function formatSubmissionDate(d) {
  return formatUserDate(d, '—')
}

function openDatePicker(r) {
  const el = r?.$el ?? r
  if (el?.showPicker) {
    try { el.showPicker() } catch { el.click() }
  } else if (el) {
    el.click()
  }
}

async function downloadDoc(doc) {
  if (!id.value || !doc?.id) return
  try {
    const blob = await vasRequestsApi.downloadDocument(id.value, doc.id)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = docDisplayName(doc)
    a.click()
    URL.revokeObjectURL(url)
  } catch {}
}

async function bulkDownloadDocs() {
  for (const doc of documents.value) {
    await downloadDoc(doc)
  }
}

function openRemoveConfirm(doc) {
  if (!doc?.id) return
  documentToRemove.value = doc
}

function closeRemoveConfirm() {
  documentToRemove.value = null
}

async function confirmRemoveDoc() {
  const doc = documentToRemove.value
  if (!id.value || !doc?.id) return
  removingDocId.value = doc.id
  try {
    await vasRequestsApi.deleteDocument(id.value, doc.id)
    if (request.value?.documents) {
      request.value.documents = request.value.documents.filter((d) => d.id !== doc.id)
    }
    closeRemoveConfirm()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to remove document.')
  } finally {
    removingDocId.value = null
  }
}

function validateNewFile(file) {
  const ext = '.' + (file.name?.split('.').pop() || '').toLowerCase()
  if (!ALLOWED_EXT.includes(ext)) return `Allowed: ${ALLOWED_EXT.join(', ')}`
  if (file.size > MAX_FILE_MB * 1024 * 1024) return `Max ${MAX_FILE_MB} MB per file.`
  return null
}

function onNewSchemaFileChange(key, e) {
  const file = e.target?.files?.[0] || null
  if (!file) {
    newDocFiles.value[key] = null
    e.target.value = ''
    return
  }
  const err = validateNewFile(file)
  if (err) {
    docUploadError.value = err
    e.target.value = ''
    return
  }
  docUploadError.value = ''
  newDocFiles.value[key] = file
  e.target.value = ''
}

function addAdditionalDocSlot() {
  if (newAdditionalDocs.value.length >= 3) return
  newAdditionalDocs.value.push({ key: 'additional_' + Date.now(), label: '', file: null })
}

function onAdditionalFileChange(index, e) {
  const file = e.target?.files?.[0] || null
  if (!newAdditionalDocs.value[index]) return
  if (!file) {
    newAdditionalDocs.value[index].file = null
    e.target.value = ''
    return
  }
  const err = validateNewFile(file)
  if (err) {
    docUploadError.value = err
    e.target.value = ''
    return
  }
  docUploadError.value = ''
  newAdditionalDocs.value[index].file = file
  if (!(newAdditionalDocs.value[index].label || '').trim()) newAdditionalDocs.value[index].label = file.name
  e.target.value = ''
}

function removeAdditionalSlot(index) {
  newAdditionalDocs.value.splice(index, 1)
}

const hasNewFilesToUpload = computed(() => {
  const schemaFiles = Object.values(newDocFiles.value).filter((f) => f instanceof File)
  const additionalFiles = newAdditionalDocs.value.filter((ad) => ad.file instanceof File)
  return schemaFiles.length > 0 || additionalFiles.length > 0
})

function buildUploadFormData() {
  const fd = new FormData()
  Object.entries(newDocFiles.value).forEach(([key, file]) => {
    if (file instanceof File) fd.append(key, file)
  })
  newAdditionalDocs.value.forEach((ad, i) => {
    if (ad.file instanceof File) {
      fd.append('additional_documents[]', ad.file)
      fd.append('additional_document_label[]', ad.label || 'Additional ' + (i + 1))
    }
  })
  return fd
}

async function uploadNewDocuments() {
  if (!id.value || !hasNewFilesToUpload.value) return
  uploadSaving.value = true
  docUploadError.value = ''
  try {
    const fd = buildUploadFormData()
    await vasRequestsApi.storeStep2(id.value, fd)
    Object.keys(newDocFiles.value).forEach((k) => { newDocFiles.value[k] = null })
    newAdditionalDocs.value = []
    // Refetch only documents so the list updates without refreshing the whole page
    const res = await vasRequestsApi.getRequest(id.value)
    const data = res?.data ?? res
    if (request.value && data?.documents) {
      request.value.documents = data.documents
    }
  } catch (e) {
    docUploadError.value = e?.response?.data?.message || e?.response?.data?.errors?.documents?.[0] || 'Upload failed.'
  } finally {
    uploadSaving.value = false
  }
}

const { errors, generalMessage, setErrors, clearErrors, clearFieldError, getError } = useFormErrors()

const inputClass = (field) =>
  `mt-1 block w-full rounded border bg-white px-3 py-2 shadow-sm focus:ring-1 ${getError(field) ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-brand-primary focus:ring-brand-primary'}`
const selectClass = (field) =>
  `mt-1 block w-full rounded border bg-white px-3 py-2 shadow-sm focus:ring-1 ${getError(field) ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-brand-primary focus:ring-brand-primary'}`

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
  if (!form.value.description?.trim()) err.description = ['Request description is required.']
  if (!form.value.manager_id) err.manager_id = ['Manager is required.']
  if (!form.value.back_office_executive_id) err.back_office_executive_id = ['Executive Name is required.']
  if (!form.value.status?.trim()) err.status = ['Status is required.']
  return Object.keys(err).length ? err : null
}

function goBack() {
  router.push('/vas-requests')
}

function addDocTrigger() {
  showAddDocs.value = true
  setTimeout(() => addDocsSectionRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' }), 100)
}

async function submitForm() {
  if (!id.value) return
  clearErrors()
  const frontendErrors = validateForm()
  if (frontendErrors) {
    errors.value = frontendErrors
    generalMessage.value = 'Please correct the errors below.'
    window.scrollTo({ top: 0, behavior: 'smooth' })
    return
  }
  saving.value = true
  try {
    await vasRequestsApi.updateRequest(id.value, {
      request_type: form.value.request_type?.trim(),
      account_number: form.value.account_number?.trim() || null,
      contact_number: form.value.contact_number?.trim() || null,
      company_name: form.value.company_name?.trim(),
      description: form.value.description?.trim() || null,
      additional_notes: form.value.additional_notes?.trim() || null,
      status: form.value.status || null,
      activity: form.value.activity?.trim() || null,
      completion_date: form.value.completion_date || null,
      remarks: form.value.remarks?.trim() || null,
      manager_id: form.value.manager_id,
      team_leader_id: form.value.team_leader_id,
      sales_agent_id: form.value.sales_agent_id,
      back_office_executive_id: form.value.back_office_executive_id || null,
    })
    router.push(`/vas-requests/${id.value}`)
  } catch (err) {
    if (err.response?.status === 422 && err.response?.data?.errors) {
      setErrors(err)
      generalMessage.value = err.response?.data?.message || 'Please correct the errors below.'
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } else {
      const msg = err.response?.data?.message || err.message || 'Failed to save.'
      toast('error', msg)
    }
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
              <h1 class="text-xl font-semibold text-gray-900">Edit VAS Request</h1>            </div>
            <router-link
              to="/vas-requests"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              Back to List
            </router-link>
          </div>
        </div>
        <div class="border-t border-gray-200" />

        <div v-if="loading" class="flex justify-center px-4 py-16 sm:px-5">
          <svg class="h-10 w-10 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </div>

        <div v-else-if="!request" class="px-4 py-8 text-center text-gray-500 sm:px-5">
          Unable to load request. You may not have permission to view it.
        </div>

        <form v-else class="px-4 py-4 sm:px-5" @submit.prevent="submitForm">
          <!-- General error banner -->
          <div v-if="generalMessage" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
            <p class="text-sm font-medium text-red-800">{{ generalMessage }}</p>
          </div>

          <!-- Back Office Verification banner -->
          <div class="mb-4 rounded-lg border border-brand-primary-muted bg-brand-primary-light px-4 py-3">
            <h3 class="text-sm font-semibold text-brand-primary-dark">Back Office Verification</h3>
            <p class="mt-1 text-sm text-brand-primary-hover">Review all information and documents carefully. Make necessary corrections before updating the status.</p>
          </div>

          <!-- Primary Information: 2 columns, labels above inputs -->
          <h2 class="mb-3 border-b border-gray-200 pb-2 text-base font-semibold text-gray-900">Primary Information</h2>
          <div class="grid gap-4 sm:grid-cols-2 lg:gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700">Company Name as per Trade License <span class="text-red-500">*</span></label>
              <input
                v-model="form.company_name"
                type="text"
                :class="inputClass('company_name')"
                @input="clearFieldError('company_name')"
              />
              <p v-if="getError('company_name')" class="mt-1 text-sm text-red-600">{{ getError('company_name') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Account Number <span class="text-red-500">*</span></label>
              <input
                v-model="form.account_number"
                type="text"
                :class="inputClass('account_number')"
                @input="clearFieldError('account_number')"
              />
              <p v-if="getError('account_number')" class="mt-1 text-sm text-red-600">{{ getError('account_number') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Request Type <span class="text-red-500">*</span></label>
              <select
                v-model="form.request_type"
                :class="selectClass('request_type')"
                @change="clearFieldError('request_type')"
              >
                <option value="">Select</option>
                <option v-for="t in requestTypes" :key="t" :value="t">{{ t }}</option>
              </select>
              <p v-if="getError('request_type')" class="mt-1 text-sm text-red-600">{{ getError('request_type') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Contact Number <span class="text-red-500">*</span></label>
              <input
                :value="form.contact_number"
                type="text"
                maxlength="12"
                placeholder="971XXXXXXXXX"
                :class="inputClass('contact_number')"
                @input="onPhoneInput('contact_number', $event)"
              />
              <p v-if="getError('contact_number')" class="mt-1 text-sm text-red-600">{{ getError('contact_number') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Request Description <span class="text-red-500">*</span></label>
              <textarea
                v-model="form.description"
                rows="3"
                :class="inputClass('description')"
                placeholder="Enter request description"
                @input="clearFieldError('description')"
              />
              <p v-if="getError('description')" class="mt-1 text-sm text-red-600">{{ getError('description') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Additional Notes</label>
              <textarea
                v-model="form.additional_notes"
                rows="3"
                :class="inputClass('additional_notes')"
                placeholder="Enter additional notes"
              />
            </div>
          </div>

          <h2 class="mb-3 mt-8 border-b border-gray-200 pb-2 text-base font-semibold text-gray-900">Team Information</h2>
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700">Submitter</label>
              <input
                :value="request?.creator_name || '—'"
                type="text"
                readonly
                class="mt-1 block w-full cursor-not-allowed rounded border border-gray-200 bg-gray-50 px-3 py-2 text-gray-600 shadow-sm"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Manager <span class="text-red-500">*</span></label>
              <select
                v-model="form.manager_id"
                :class="selectClass('manager_id')"
                @change="clearFieldError('manager_id')"
              >
                <option :value="null">Select</option>
                <option v-for="u in teamOptions.managers" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <p v-if="getError('manager_id')" class="mt-1 text-sm text-red-600">{{ getError('manager_id') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Team Leader</label>
              <select
                v-model="form.team_leader_id"
                :class="selectClass('team_leader_id')"
                @change="clearFieldError('team_leader_id')"
              >
                <option :value="null">Select</option>
                <option v-for="u in filteredTeamLeaders" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <p v-if="getError('team_leader_id')" class="mt-1 text-sm text-red-600">{{ getError('team_leader_id') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Sales Agent</label>
              <select
                v-model="form.sales_agent_id"
                :class="selectClass('sales_agent_id')"
                @change="clearFieldError('sales_agent_id')"
              >
                <option :value="null">Select</option>
                <option v-for="u in filteredSalesAgents" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <p v-if="getError('sales_agent_id')" class="mt-1 text-sm text-red-600">{{ getError('sales_agent_id') }}</p>
            </div>
          </div>

          <!-- Back Office Working Section -->
          <h2 class="mb-3 mt-8 border-b border-gray-200 pb-2 text-base font-semibold text-gray-900">Back Office Working Section</h2>
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700">Back Office Executive <span class="text-red-500">*</span></label>
              <select
                v-model="form.back_office_executive_id"
                :class="selectClass('back_office_executive_id')"
                @change="clearFieldError('back_office_executive_id')"
              >
                <option :value="null">Select</option>
                <option v-for="ex in executives" :key="ex.id" :value="ex.id">{{ ex.name }}</option>
              </select>
              <p v-if="getError('back_office_executive_id')" class="mt-1 text-sm text-red-600">{{ getError('back_office_executive_id') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Submission Date</label>
              <input
                :value="formatSubmissionDate(request?.submitted_at || request?.created_at)"
                type="text"
                readonly
                class="mt-1 block w-full cursor-not-allowed rounded border border-gray-200 bg-gray-50 px-3 py-2 text-gray-600 shadow-sm"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Status <span class="text-red-500">*</span></label>
              <select
                v-model="form.status"
                :class="selectClass('status')"
                @change="clearFieldError('status')"
              >
                <option value="unassigned" disabled>UnAssigned</option>
                <option v-for="s in VAS_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
              <p v-if="getError('status')" class="mt-1 text-sm text-red-600">{{ getError('status') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Activity</label>
              <input
                v-model="form.activity"
                type="text"
                :class="inputClass('activity')"
                placeholder="Enter activity"
                @input="clearFieldError('activity')"
              />
              <p v-if="getError('activity')" class="mt-1 text-sm text-red-600">{{ getError('activity') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Completion Date</label>
              <div class="relative mt-1" @click="openDatePicker(completionDateRef)">
                <input
                  :value="formatUserDate(form.completion_date, '')"
                  type="text"
                  readonly
                  placeholder="DD-MMM-YYYY"
                  :class="inputClass('completion_date')"
                />
                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                <input ref="completionDateRef" v-model="form.completion_date" type="date" class="sr-only" tabindex="-1" @input="clearFieldError('completion_date')" />
              </div>
              <p v-if="getError('completion_date')" class="mt-1 text-sm text-red-600">{{ getError('completion_date') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Remarks</label>
              <textarea
                v-model="form.remarks"
                rows="2"
                :class="inputClass('remarks')"
                placeholder="Enter remarks"
                @input="clearFieldError('remarks')"
              />
              <p v-if="getError('remarks')" class="mt-1 text-sm text-red-600">{{ getError('remarks') }}</p>
            </div>
          </div>

          <!-- Documents -->
          <div class="mt-8">
            <div class="mb-3 flex items-center justify-between border-b border-gray-200 pb-2">
              <h2 class="text-base font-semibold text-gray-900">Documents</h2>
              <button
                type="button"
                class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                :disabled="!documents.length"
                @click="bulkDownloadDocs"
              >
                Bulk Download
              </button>
            </div>
            <div v-if="documents.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
              <div
                v-for="doc in documents"
                :key="doc.id"
                class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white p-3"
              >
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-red-600 text-sm font-bold text-white">D</div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium text-gray-900" :title="docDisplayName(doc)">{{ docDisplayName(doc) }}</p>
                  <p v-if="doc.size != null" class="text-xs text-gray-500">{{ formatFileSize(doc.size) }}</p>
                </div>
                <button
                  type="button"
                  class="shrink-0 rounded p-1.5 text-brand-primary hover:bg-brand-primary-light"
                  :title="'Download ' + docDisplayName(doc)"
                  @click="downloadDoc(doc)"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                </button>
                <button
                  type="button"
                  class="shrink-0 rounded p-1.5 text-red-600 hover:bg-red-50"
                  :title="'Remove ' + docDisplayName(doc)"
                  :disabled="removingDocId === doc.id"
                  @click="openRemoveConfirm(doc)"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
            <div v-else class="rounded-lg border border-gray-200 bg-gray-50 py-6 text-center text-sm text-gray-500">No documents uploaded.</div>
          </div>

          <!-- Add Document -->
          <div class="mt-6">
            <div class="mb-3 flex items-center justify-between border-b border-gray-200 pb-2">
              <h2 class="text-base font-semibold text-gray-900">Add Document</h2>
              <button
                type="button"
                class="text-sm font-medium text-brand-primary hover:text-brand-primary-hover"
                @click="addDocTrigger"
              >
                + Add Document
              </button>
            </div>
            <div ref="addDocsSectionRef" v-if="showAddDocs">
              <p class="mb-2 text-xs text-gray-500">PDF, DOC, DOCX, EML. You can select multiple files. Max {{ MAX_FILE_MB }} MB per file.</p>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div
                  v-for="doc in docSchema"
                  :key="doc.key"
                  class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3"
                >
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-gray-100 text-gray-500">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-900">{{ doc.label }}</p>
                    <p v-if="newDocFiles[doc.key]" class="mt-0.5 truncate text-xs text-brand-primary">{{ newDocFiles[doc.key].name }}</p>
                  </div>
                  <label class="shrink-0 cursor-pointer">
                    <input type="file" class="hidden" :accept="ALLOWED_EXT.join(',')" @change="onNewSchemaFileChange(doc.key, $event)" />
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-brand-primary bg-white px-3 py-1.5 text-xs font-medium text-brand-primary hover:bg-brand-primary-light">
                      <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                      Upload
                    </span>
                  </label>
                </div>
              </div>
              <div class="mt-3">
                <button
                  type="button"
                  class="text-sm font-medium text-brand-primary hover:text-brand-primary-hover disabled:opacity-50"
                  :disabled="newAdditionalDocs.length >= 3"
                  @click="addAdditionalDocSlot"
                >
                  + Add additional document
                </button>
                <div v-if="newAdditionalDocs.length > 0" class="mt-2 space-y-2">
                  <div
                    v-for="(ad, index) in newAdditionalDocs"
                    :key="ad.key"
                    class="flex flex-wrap items-center gap-2 rounded border border-gray-200 bg-white px-3 py-2"
                  >
                    <input
                      v-model="ad.label"
                      type="text"
                      placeholder="Label"
                      class="min-w-0 flex-1 rounded border border-gray-300 px-2 py-1 text-sm"
                    />
                    <label class="shrink-0 cursor-pointer">
                      <input type="file" class="hidden" :accept="ALLOWED_EXT.join(',')" @change="onAdditionalFileChange(index, $event)" />
                      <span class="inline-flex rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">Choose file</span>
                    </label>
                    <span v-if="ad.file" class="truncate text-xs text-gray-600">{{ ad.file.name }}</span>
                    <button type="button" class="text-xs text-red-600 hover:underline" @click="removeAdditionalSlot(index)">Remove</button>
                  </div>
                </div>
              </div>
              <div v-if="docUploadError" class="mt-2 text-sm text-red-600">{{ docUploadError }}</div>
              <button
                type="button"
                class="mt-3 inline-flex items-center gap-1 rounded bg-brand-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-50"
                :disabled="!hasNewFilesToUpload || uploadSaving"
                @click.prevent="uploadNewDocuments"
              >
                <span v-if="uploadSaving">Uploading...</span>
                <span v-else>Upload selected files</span>
              </button>
            </div>
          </div>

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
              class="rounded bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-70"
            >
              <span v-if="saving">Saving...</span>
              <span v-else>Save Changes</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Remove document confirmation popup (opened by Remove button in Documents section above) -->
  <Teleport to="body">
    <div
      v-if="documentToRemove"
      class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/50 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="remove-doc-modal-title"
      @click.self="closeRemoveConfirm"
    >
      <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
          <h2 id="remove-doc-modal-title" class="text-lg font-semibold text-gray-900">Remove document</h2>
          <button
            type="button"
            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
            aria-label="Close"
            @click="closeRemoveConfirm"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="px-6 py-4">
          <p class="text-sm text-gray-600">
            The document
            <span class="font-medium text-gray-900">"{{ documentToRemove ? docDisplayName(documentToRemove) : '' }}"</span>
            will be permanently removed from this request. You cannot undo this action.
          </p>
          <p class="mt-2 text-sm text-gray-600">
            Do you want to continue?
          </p>
        </div>
        <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
          <button
            type="button"
            class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            @click="closeRemoveConfirm"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
            :disabled="removingDocId === documentToRemove?.id"
            @click="confirmRemoveDoc"
          >
            <span v-if="removingDocId === documentToRemove?.id">Removing...</span>
            <span v-else>Remove</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
</template>
