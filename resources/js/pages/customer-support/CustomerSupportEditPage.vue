<script setup>
/**
 * Edit Customer Support Request – full form: Primary Info, Issue Details, Attachments,
 * Submitter Info (read-only), CSR fields, Save Draft / Submit. Update history tracked via API.
 */
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import customerSupportApi from '@/services/customerSupportApi'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { useFormErrors } from '@/composables/useFormErrors'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const saving = ref(false)
const submission = ref(null)
const editOptions = ref({ issue_categories: [], statuses: [], workflow_statuses: [], pending_options: [] })
const teamOptions = ref({ managers: [], team_leaders: [], sales_agents: [], labels: {} })
const csrUsers = ref([])
const accountCsrIds = ref([])
const newFiles = ref([])
const fileInput = ref(null)
const completionDateRef = ref(null)

const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
function formatDateDisplay(val) {
  if (!val) return ''
  const [y, m, d] = val.split('-')
  if (!y || !m || !d) return ''
  return `${d}-${MONTHS[parseInt(m, 10) - 1]}-${y}`
}
function openDatePicker(r) {
  const el = r?.$el ?? r
  if (el?.showPicker) {
    try { el.showPicker() } catch { el.click() }
  } else if (el) { el.click() }
}
const accountCsrs = computed(() => {
  const ids = new Set(accountCsrIds.value)
  return csrUsers.value.filter((c) => ids.has(c.id))
})
const otherCsrs = computed(() => {
  const ids = new Set(accountCsrIds.value)
  return csrUsers.value.filter((c) => !ids.has(c.id))
})

async function loadAccountCsrs(accountNumber) {
  if (!accountNumber) {
    accountCsrIds.value = []
    return
  }
  try {
    const res = await customerSupportApi.getCsrsByAccount(accountNumber)
    accountCsrIds.value = res?.csr_ids ?? []
  } catch {
    accountCsrIds.value = []
  }
}

const settingFromChild = ref(false)
const settingFromSalesAgent = ref(false)
/** When true, watchers skip so initial load does not clear team assignment IDs */
const initialFormLoad = ref(false)

const id = computed(() => {
  const p = route.params.id
  return p != null ? Number(p) : null
})

const { errors, generalMessage, setErrors, clearErrors, getError } = useFormErrors()

const form = ref({
  issue_category: '',
  company_name: '',
  account_number: '',
  contact_number: '',
  issue_description: '',
  manager_id: '',
  team_leader_id: '',
  sales_agent_id: '',
  status: '',
  ticket_number: '',
  csr_id: '',
  workflow_status: '',
  completion_date: '',
  trouble_ticket: '',
  activity: '',
  pending: '',
  resolution_remarks: '',
  internal_remarks: '',
})

/** Format label: first letter capital, underscore → space, next word capital (e.g. team_leader → Team Leader) */
function formatTeamLabel(keyOrLabel) {
  const s = (keyOrLabel ?? '').toString().trim()
  if (!s) return ''
  return s.split('_').map((w) => w.charAt(0).toUpperCase() + (w.slice(1) || '').toLowerCase()).join(' ')
}

const teamLabels = computed(() => ({
  manager: formatTeamLabel(teamOptions.value.labels?.manager ?? 'manager'),
  team_leader: formatTeamLabel(teamOptions.value.labels?.team_leader ?? 'team_leader'),
  sales_agent: formatTeamLabel(teamOptions.value.labels?.sales_agent ?? 'sales_agent'),
}))

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

function displayVal(val) {
  return val != null && val !== '' ? String(val) : '—'
}

function attachmentDisplayName(att) {
  return att?.file_name ?? att?.original_name ?? 'Attachment'
}

async function downloadAttachment(index) {
  if (!submission.value?.id) return
  const att = submission.value.attachments?.[index]
  const name = attachmentDisplayName(att) || 'attachment'
  try {
    const { data } = await api.get(`/customer-support/${submission.value.id}/attachments/${index}/download`, { responseType: 'blob' })
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = name
    a.rel = 'noopener'
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  } catch {
    window.open(`/api/customer-support/${submission.value.id}/attachments/${index}/download`, '_blank', 'noopener')
  }
}

async function load() {
  if (!id.value) return
  loading.value = true
  submission.value = null
  clearErrors()
  initialFormLoad.value = true
  try {
    const [subData, optionsData, teamData, csrData] = await Promise.all([
      customerSupportApi.getSubmission(id.value),
      customerSupportApi.getEditOptions().catch(() => ({})),
      customerSupportApi.getTeamOptions().then((r) => r?.data ?? {}).catch(() => ({})),
      customerSupportApi.getCsrOptions().catch(() => ({})),
    ])
    csrUsers.value = csrData?.csrs ?? []
    submission.value = subData
    editOptions.value = {
      issue_categories: optionsData.issue_categories ?? [],
      statuses: optionsData.statuses ?? [],
      workflow_statuses: optionsData.workflow_statuses ?? [],
      pending_options: optionsData.pending_options ?? [],
    }
    teamOptions.value = {
      managers: teamData.managers ?? [],
      team_leaders: teamData.team_leaders ?? [],
      sales_agents: teamData.sales_agents ?? [],
      labels: teamData.labels ?? {},
    }
    const managerId = subData.manager_id ?? subData.manager?.id
    const teamLeaderId = subData.team_leader_id ?? subData.team_leader?.id
    const salesAgentId = subData.sales_agent_id ?? subData.sales_agent?.id
    form.value = {
      issue_category: subData.issue_category ?? '',
      company_name: subData.company_name ?? '',
      account_number: subData.account_number ?? '',
      contact_number: subData.contact_number ?? '',
      issue_description: subData.issue_description ?? '',
      manager_id: managerId != null ? String(managerId) : '',
      team_leader_id: teamLeaderId != null ? String(teamLeaderId) : '',
      sales_agent_id: salesAgentId != null ? String(salesAgentId) : '',
      status: '',
      ticket_number: subData.ticket_number ?? '',
      csr_id: subData.csr_id != null ? String(subData.csr_id) : '',
      workflow_status: subData.workflow_status ?? '',
      completion_date: subData.completion_date ? String(subData.completion_date).slice(0, 10) : '',
      trouble_ticket: subData.trouble_ticket ?? '',
      activity: subData.activity ?? '',
      pending: subData.pending ?? '',
      resolution_remarks: subData.resolution_remarks ?? '',
      internal_remarks: subData.internal_remarks ?? '',
    }
    newFiles.value = []
    if (subData.account_number) {
      await loadAccountCsrs(subData.account_number)
      if (!form.value.csr_id && accountCsrIds.value.length > 0) {
        form.value.csr_id = String(accountCsrIds.value[0])
      }
    }
    nextTick(() => {
      initialFormLoad.value = false
    })
  } catch {
    submission.value = null
    initialFormLoad.value = false
  } finally {
    loading.value = false
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
    form.value.team_leader_id = ''
    form.value.sales_agent_id = ''
  }
)

watch(
  () => form.value.team_leader_id,
  (tid) => {
    if (initialFormLoad.value) return
    if (tid) {
      const tl = (teamOptions.value.team_leaders ?? []).find((u) => String(u.id) === String(tid))
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
  (sid) => {
    if (initialFormLoad.value) return
    if (sid) {
      const sa = (teamOptions.value.sales_agents ?? []).find((u) => String(u.id) === String(sid))
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

watch(
  () => form.value.account_number,
  async (newVal, oldVal) => {
    if (initialFormLoad.value) return
    if (newVal === oldVal) return
    await loadAccountCsrs(newVal)
    if (accountCsrIds.value.length > 0) {
      form.value.csr_id = String(accountCsrIds.value[0])
    }
  }
)

function goBack() {
  router.push(`/customer-support/${id.value}`)
}

/** Validate required (starred) fields; set errors and return false if invalid */
function validateRequired() {
  const f = form.value
  const errs = {}
  if (!(f.issue_category ?? '').toString().trim()) errs.issue_category = ['Please select an issue category.']
  if (!(f.company_name ?? '').toString().trim()) errs.company_name = ['Company name is required.']
  if (!(f.contact_number ?? '').toString().trim()) errs.contact_number = ['Contact number is required.']
  if (!(f.issue_description ?? '').toString().trim()) errs.issue_description = ['Issue description is required.']
  if (!f.manager_id || Number(f.manager_id) < 1) errs.manager_id = ['Please select a manager.']
  if (!f.team_leader_id || Number(f.team_leader_id) < 1) errs.team_leader_id = ['Please select a team leader.']
  if (!f.sales_agent_id || Number(f.sales_agent_id) < 1) errs.sales_agent_id = ['Please select a sales agent.']
  if (Object.keys(errs).length > 0) {
    setErrors({ response: { data: { errors: errs, message: 'Please correct the errors below.' } } })
    return false
  }
  return true
}

function buildPayload(submitAction = false) {
  const f = form.value
  const payload = {
    issue_category: f.issue_category || null,
    company_name: f.company_name || null,
    account_number: f.account_number || null,
    contact_number: f.contact_number || null,
    issue_description: f.issue_description || null,
    manager_id: f.manager_id ? Number(f.manager_id) : null,
    team_leader_id: f.team_leader_id ? Number(f.team_leader_id) : null,
    sales_agent_id: f.sales_agent_id ? Number(f.sales_agent_id) : null,
    status: f.status || null,
    ticket_number: f.ticket_number || null,
    csr_id: f.csr_id ? Number(f.csr_id) : null,
    workflow_status: f.workflow_status || null,
    completion_date: f.completion_date || null,
    trouble_ticket: f.trouble_ticket || null,
    activity: f.activity || null,
    pending: f.pending || null,
    resolution_remarks: f.resolution_remarks || null,
    internal_remarks: f.internal_remarks || null,
  }
  if (submitAction && !f.status) payload.status = 'submitted'
  return payload
}

function triggerFileSelect() {
  fileInput.value?.click()
}

function onFileChange(e) {
  const files = e.target?.files
  if (!files?.length) return
  newFiles.value = [...newFiles.value, ...Array.from(files)]
  e.target.value = ''
}

function removeNewFile(index) {
  newFiles.value = newFiles.value.filter((_, i) => i !== index)
}

async function saveDraft() {
  if (!id.value) return
  clearErrors()
  if (!validateRequired()) return
  saving.value = true
  try {
    await customerSupportApi.updateSubmission(id.value, buildPayload(false))
    if (newFiles.value.length > 0) {
      await customerSupportApi.uploadAttachments(id.value, newFiles.value)
    }
    router.push(`/customer-support/${id.value}`)
  } catch (e) {
    setErrors(e)
  } finally {
    saving.value = false
  }
}

async function submit() {
  if (!id.value) return
  clearErrors()
  if (!validateRequired()) return
  saving.value = true
  try {
    await customerSupportApi.updateSubmission(id.value, buildPayload(true))
    if (newFiles.value.length > 0) {
      await customerSupportApi.uploadAttachments(id.value, newFiles.value)
    }
    router.push(`/customer-support/${id.value}`)
  } catch (e) {
    setErrors(e)
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  load()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white p-0">
    <div class="w-full">
      <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="px-4 py-4 sm:px-5">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-baseline gap-2">
              <h1 class="text-xl font-semibold text-gray-900">Edit Customer Support Request</h1>
              <Breadcrumbs />
            </div>
            <router-link
              to="/customer-support"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            >
              Back to List
            </router-link>
          </div>
        </div>
        <div class="border-t border-gray-200" />

        <div v-if="loading" class="flex justify-center px-4 py-16 sm:px-5">
          <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </div>

        <div v-else-if="!submission" class="px-4 py-8 text-center text-gray-500 sm:px-5">
          Unable to load request. You may not have permission to view it.
        </div>

        <form v-else class="px-4 py-5 sm:px-5" @submit.prevent="saveDraft">
          <!-- Validation summary -->
          <div
            v-if="generalMessage || Object.keys(errors).length"
            class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4"
          >
            <p class="text-sm font-medium text-red-800">{{ generalMessage }}</p>
            <ul class="mt-2 list-inside list-disc space-y-0.5 text-sm text-red-700">
              <li v-for="(msgs, field) in errors" :key="field">{{ getError(field) }}</li>
            </ul>
          </div>

          <!-- Primary Information -->
          <section class="mb-8">
            <h2 class="mb-3 border-b border-gray-200 pb-2 text-base font-semibold text-gray-900">Primary Information</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div class="sm:col-span-3">
                <label class="block text-sm font-medium text-gray-700">Issue Category <span class="text-red-500">*</span></label>
                <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
                  <label
                    v-for="cat in editOptions.issue_categories"
                    :key="cat"
                    class="flex cursor-pointer items-center rounded-lg border px-3 py-2.5 shadow-sm transition hover:border-gray-400"
                    :class="form.issue_category === cat
                      ? 'border-green-500 bg-green-50 ring-1 ring-green-500'
                      : 'border-gray-300 bg-white'"
                  >
                    <input
                      v-model="form.issue_category"
                      type="radio"
                      :value="cat"
                      class="h-4 w-4 border-gray-300 text-green-600 focus:ring-green-500"
                    />
                    <span
                      class="ml-2 text-sm"
                      :class="form.issue_category === cat ? 'font-medium text-gray-900' : 'text-gray-600'"
                    >{{ cat }}</span>
                  </label>
                </div>
                <p v-if="getError('issue_category')" class="mt-1 text-sm text-red-600">{{ getError('issue_category') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Company Name <span class="text-red-500">*</span></label>
                <input v-model="form.company_name" type="text" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="{ 'border-red-500': getError('company_name') }" />
                <p v-if="getError('company_name')" class="mt-1 text-sm text-red-600">{{ getError('company_name') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Account Number</label>
                <input v-model="form.account_number" type="text" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="{ 'border-red-500': getError('account_number') }" />
                <p v-if="getError('account_number')" class="mt-1 text-sm text-red-600">{{ getError('account_number') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Contact Number <span class="text-red-500">*</span></label>
                <input v-model="form.contact_number" type="text" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="{ 'border-red-500': getError('contact_number') }" />
                <p v-if="getError('contact_number')" class="mt-1 text-sm text-red-600">{{ getError('contact_number') }}</p>
              </div>
            </div>
          </section>

          <!-- Team Assignment -->
          <section class="mb-8">
            <h2 class="mb-3 border-b border-gray-200 pb-2 text-base font-semibold text-gray-900">Team Assignment</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div>
                <label class="block text-sm font-medium text-gray-700">{{ teamLabels.manager || 'Manager' }} <span class="text-red-500">*</span></label>
                <select v-model="form.manager_id" class="mt-1 block w-full rounded border px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="getError('manager_id') ? 'border-red-500' : 'border-gray-300'">
                  <option value="">Select {{ teamLabels.manager || 'Manager' }}</option>
                  <option v-for="u in teamOptions.managers" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
                <p v-if="getError('manager_id')" class="mt-1 text-sm text-red-600">{{ getError('manager_id') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">{{ teamLabels.team_leader }} <span class="text-red-500">*</span></label>
                <select v-model="form.team_leader_id" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                  <option value="">Select {{ teamLabels.team_leader }}</option>
                  <option v-for="u in filteredTeamLeaders" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
                <p v-if="getError('team_leader_id')" class="mt-1 text-sm text-red-600">{{ getError('team_leader_id') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">{{ teamLabels.sales_agent || 'Sales Agent' }} <span class="text-red-500">*</span></label>
                <select v-model="form.sales_agent_id" class="mt-1 block w-full rounded border px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="getError('sales_agent_id') ? 'border-red-500' : 'border-gray-300'">
                  <option value="">Select {{ teamLabels.sales_agent || 'Sales Agent' }}</option>
                  <option v-for="u in filteredSalesAgents" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
                <p v-if="getError('sales_agent_id')" class="mt-1 text-sm text-red-600">{{ getError('sales_agent_id') }}</p>
              </div>
            </div>
          </section>

          <!-- Issue Details -->
          <section class="mb-8">
            <h2 class="mb-3 border-b border-gray-200 pb-2 text-base font-semibold text-gray-900">Issue Details</h2>
            <div>
              <label class="block text-sm font-medium text-gray-700">Issue Description <span class="text-red-500">*</span></label>
              <textarea v-model="form.issue_description" rows="4" placeholder="Provide detailed description of the issue..." class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" :class="{ 'border-red-500': getError('issue_description') }" />
              <p v-if="getError('issue_description')" class="mt-1 text-sm text-red-600">{{ getError('issue_description') }}</p>
            </div>
          </section>

          <!-- Attachments -->
          <section class="mb-8">
            <h2 class="mb-2 text-base font-semibold text-gray-900">Attachments</h2>
            <div class="border-b border-gray-200 pb-3" />
            <!-- Uploaded documents (cards in a row) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div
                v-for="(att, idx) in submission.attachments"
                :key="'ex-' + idx"
                class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
              >
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-red-50 text-red-600">
                  <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium text-gray-900">{{ attachmentDisplayName(att) }}</p>
                  <p v-if="att.file_size" class="mt-0.5 text-xs text-gray-500">{{ att.file_size }}</p>
                </div>
                <button type="button" class="shrink-0 rounded p-2 text-blue-600 hover:bg-blue-50" :title="'Download ' + attachmentDisplayName(att)" @click="downloadAttachment(idx)">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                </button>
              </div>
            </div>
            <!-- + Add Another Document – on the next line after uploaded documents -->
            <div class="mt-6 block border-t border-gray-100 pt-4">
              <input ref="fileInput" type="file" multiple accept="image/*,.pdf,.doc,.docx,.csv" class="hidden" @change="onFileChange" />
              <button
                type="button"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-green-600 hover:text-green-700 hover:underline focus:outline-none"
                @click="triggerFileSelect"
              >
                <span class="text-base leading-none">+</span>
                Add Another Document
              </button>
              <div v-if="newFiles.length" class="mt-3 flex flex-wrap gap-2">
                <div v-for="(f, idx) in newFiles" :key="'new-' + idx" class="flex items-center gap-2 rounded border border-green-200 bg-green-50 px-2 py-1 text-xs">
                  <span class="max-w-[160px] truncate">{{ f.name }}</span>
                  <button type="button" class="text-red-600 hover:underline" @click="removeNewFile(idx)">Remove</button>
                </div>
              </div>
            </div>
          </section>

          <!-- Submitter Information (read-only) -->
          <section class="mb-8">
            <h2 class="mb-3 border-b border-gray-200 pb-2 text-base font-semibold text-gray-900">Submitter Information</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label class="block text-sm font-medium text-gray-500">Submitter Name</label>
                <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.creator_name) }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-500">Submitter Role</label>
                <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.creator_role) }}</div>
              </div>
            </div>
          </section>

          <!-- Additional CSR fields -->
          <section class="mb-8">
            <h2 class="mb-3 border-b border-gray-200 pb-2 text-base font-semibold text-gray-900">Additional Information (CSR)</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <select v-model="form.status" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                  <option value="">Select Status</option>
                  <option v-for="opt in editOptions.statuses" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Ticket Number</label>
                <input :value="form.ticket_number" type="text" readonly class="mt-1 block w-full cursor-not-allowed rounded border border-gray-200 bg-gray-50 px-3 py-2 text-gray-600 shadow-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">CSR Name</label>
                <select v-model="form.csr_id" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                  <option value="">Select CSR</option>
                  <optgroup v-if="accountCsrs.length" label="Assigned to this Account">
                    <option v-for="csr in accountCsrs" :key="'acc-'+csr.id" :value="String(csr.id)">{{ csr.name }}</option>
                  </optgroup>
                  <optgroup v-if="otherCsrs.length" :label="accountCsrs.length ? 'Other CSRs' : 'All CSRs'">
                    <option v-for="csr in otherCsrs" :key="'oth-'+csr.id" :value="String(csr.id)">{{ csr.name }}</option>
                  </optgroup>
                  <template v-if="!accountCsrs.length && !otherCsrs.length">
                    <option v-for="csr in csrUsers" :key="csr.id" :value="String(csr.id)">{{ csr.name }}</option>
                  </template>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Completion Date</label>
                <div class="relative mt-1" @click="openDatePicker(completionDateRef)">
                  <input
                    type="text"
                    readonly
                    :value="formatDateDisplay(form.completion_date)"
                    placeholder="DD-MMM-YYYY"
                    class="block w-full cursor-pointer rounded border border-gray-300 bg-white px-3 py-2 pr-9 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  />
                  <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                  <input ref="completionDateRef" v-model="form.completion_date" type="date" class="sr-only" tabindex="-1" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Trouble Ticket</label>
                <input v-model="form.trouble_ticket" type="text" placeholder="Enter the Ticket" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Activity</label>
                <input v-model="form.activity" type="text" placeholder="Enter Activity" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
              </div>
              <div class="sm:col-span-2 lg:col-span-3">
                <label class="block text-sm font-medium text-gray-700">Pending</label>
                <select v-model="form.pending" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                  <option value="">Select Pending</option>
                  <option v-for="opt in editOptions.pending_options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
              <div class="sm:col-span-2 lg:col-span-3">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Resolution Remarks</label>
                    <textarea v-model="form.resolution_remarks" rows="3" placeholder="Provide detailed description of the issue..." class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Internal Remarks</label>
                    <textarea v-model="form.internal_remarks" rows="3" placeholder="Provide detailed description of the issue..." class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500" />
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- Actions -->
          <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4">
            <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50" @click="goBack">
              Cancel
            </button>
            <button type="submit" class="rounded border border-green-600 bg-green-50 px-4 py-2 text-sm font-medium text-green-700 shadow-sm hover:bg-green-100" :disabled="saving">
              {{ saving ? 'Saving...' : 'Save Draft' }}
            </button>
            <button type="button" class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700" :disabled="saving" @click="submit">
              {{ saving ? 'Submitting...' : 'Submit' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
