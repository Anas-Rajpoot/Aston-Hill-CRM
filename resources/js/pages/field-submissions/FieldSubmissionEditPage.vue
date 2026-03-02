<script setup>
/**
 * Field Head Edit – full page form matching the design (Primary Info, Team Member, Field Executive, Photographic Proof, Remarks).
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useFormDraft } from '@/composables/useFormDraft'
import fieldSubmissionsApi from '@/services/fieldSubmissionsApi'
import { useAuthStore } from '@/stores/auth'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Toast from '@/components/Toast.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const saving = ref(false)
const submission = ref(null)
const teamOptions = ref({
  managers: [],
  team_leaders: [],
  sales_agents: [],
  field_executives: [],
})
const editOptions = ref({ emirates: [], field_statuses: [] })

const EMIRATES_FALLBACK = [
  'Abu Dhabi', 'Dubai', 'Sharjah', 'Ajman', 'Umm Al Quwain', 'Ras Al Khaimah', 'Fujairah',
]
const emiratesList = computed(() => {
  const list = editOptions.value.emirates && editOptions.value.emirates.length
    ? editOptions.value.emirates
    : EMIRATES_FALLBACK
  return Array.isArray(list) ? list : EMIRATES_FALLBACK
})

const form = ref({
  company_name: '',
  product: '',
  contact_number: '',
  alternate_number: '',
  emirates: '',
  location_coordinates: '',
  complete_address: '',
  additional_notes: '',
  special_instruction: '',
  sales_agent_id: null,
  team_leader_id: null,
  manager_id: null,
  field_executive_id: null,
  field_status: '',
  meeting_date: '',
  remarks_by_field_agent: '',
})

const { draftSaving, draftSavedAt, clearDraft } = useFormDraft('field-submission', route.params.id || 'new', form)

const newFiles = ref([])
const fileInput = ref(null)
const meetingDateRef = ref(null)

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

const id = computed(() => {
  const p = route.params.id
  return p != null ? Number(p) : null
})

const canEdit = computed(() => (auth.user?.permissions ?? []).includes('field_head.view'))

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

const existingDocuments = computed(() => submission.value?.documents ?? [])

async function load() {
  if (!id.value) return
  loading.value = true
  submission.value = null
  try {
    const [subRes, teamRes, optionsRes] = await Promise.all([
      fieldSubmissionsApi.getSubmission(id.value),
      fieldSubmissionsApi.getTeamOptions().then((r) => r?.data ?? r).catch(() => ({})),
      fieldSubmissionsApi.getEditOptions().catch(() => ({})),
    ])
    const data = subRes?.data ?? subRes
    submission.value = data
    teamOptions.value = {
      managers: teamRes.managers ?? [],
      team_leaders: teamRes.team_leaders ?? [],
      sales_agents: teamRes.sales_agents ?? [],
      field_executives: teamRes.field_executives ?? [],
    }
    editOptions.value = {
      emirates: optionsRes.emirates ?? [],
      field_statuses: optionsRes.field_statuses ?? [],
    }
    form.value = {
      company_name: data.company_name ?? '',
      product: data.product ?? '',
      contact_number: data.contact_number ?? '',
      alternate_number: data.alternate_number ?? '',
      emirates: data.emirates ?? '',
      location_coordinates: data.location_coordinates ?? '',
      complete_address: data.complete_address ?? '',
      additional_notes: data.additional_notes ?? '',
      special_instruction: data.special_instruction ?? '',
      sales_agent_id: data.sales_agent_id ?? null,
      team_leader_id: data.team_leader_id ?? null,
      manager_id: data.manager_id ?? null,
      field_executive_id: data.field_executive_id ?? null,
      field_status: data.field_status ?? '',
      meeting_date: data.meeting_date ? data.meeting_date.slice(0, 10) : '',
      remarks_by_field_agent: data.remarks_by_field_agent ?? '',
    }
    newFiles.value = []
  } catch {
    submission.value = null
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
}

function goBack() {
  router.push('/field-submissions')
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

function formatFileSize(bytes) {
  if (bytes == null) return ''
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function docDisplayName(doc) {
  return doc?.original_name || doc?.label || doc?.doc_key || 'Document'
}

async function downloadDoc(doc) {
  if (!submission.value?.id || !doc?.id) return
  try {
    const blob = await fieldSubmissionsApi.downloadDocument(submission.value.id, doc.id)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = docDisplayName(doc)
    a.click()
    URL.revokeObjectURL(url)
  } catch {}
}

const serverErrors = ref({})
const showToast = ref(false)
const toastType = ref('success')
const toastMsg = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }
const fieldErrors = ref({})

function validatePhone(value) {
  if (!value) return null
  if (!/^\d{12}$/.test(value)) return 'Must be exactly 12 digits with no spaces (e.g. 971XXXXXXXXX).'
  if (!value.startsWith('971')) return 'Must start with 971.'
  return null
}

function validateCoordinates(value) {
  if (!value) return null
  const coordPattern = /^-?\d{1,3}(\.\d+)?\s*,\s*-?\d{1,3}(\.\d+)?$/
  const coords = value.trim()
  if (!coordPattern.test(coords)) return 'Enter valid coordinates (e.g. 25.2048, 55.2708).'
  const [lat, lng] = coords.split(',').map(s => parseFloat(s.trim()))
  if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return 'Latitude must be -90 to 90, longitude -180 to 180.'
  return null
}

function onPhoneInput(field, e) {
  form.value[field] = e.target.value.replace(/\D/g, '').slice(0, 12)
  fieldErrors.value[field] = null
}

function validateFields() {
  const errs = {}
  if (form.value.contact_number) {
    const phoneErr = validatePhone(form.value.contact_number.trim())
    if (phoneErr) errs.contact_number = phoneErr
  }
  if (form.value.alternate_number) {
    const altErr = validatePhone(form.value.alternate_number.trim())
    if (altErr) errs.alternate_number = altErr
  }
  if (form.value.location_coordinates) {
    const coordErr = validateCoordinates(form.value.location_coordinates)
    if (coordErr) errs.location_coordinates = coordErr
  }
  fieldErrors.value = errs
  return Object.keys(errs).length === 0
}

async function submitForm() {
  if (!id.value) return
  if (!validateFields()) return
  saving.value = true
  serverErrors.value = {}
  try {
    const payload = {
      company_name: form.value.company_name || null,
      product: form.value.product || null,
      contact_number: form.value.contact_number || null,
      alternate_number: form.value.alternate_number || null,
      emirates: form.value.emirates || null,
      location_coordinates: form.value.location_coordinates || null,
      complete_address: form.value.complete_address || null,
      additional_notes: form.value.additional_notes || null,
      special_instruction: form.value.special_instruction || null,
      manager_id: form.value.manager_id ? Number(form.value.manager_id) : null,
      team_leader_id: form.value.team_leader_id ? Number(form.value.team_leader_id) : null,
      sales_agent_id: form.value.sales_agent_id ? Number(form.value.sales_agent_id) : null,
      field_executive_id: form.value.field_executive_id ? Number(form.value.field_executive_id) : null,
      meeting_date: form.value.meeting_date || null,
      field_status: form.value.field_status || null,
      remarks_by_field_agent: form.value.remarks_by_field_agent || null,
    }
    await fieldSubmissionsApi.updateSubmission(id.value, payload, newFiles.value.length ? newFiles.value : null)
    await clearDraft()
    newFiles.value = []
    toast('success', 'Field submission updated successfully.')
    setTimeout(() => {
      router.push(`/field-submissions/${id.value}`)
    }, 1200)
  } catch (err) {
    if (err.response?.status === 422 && err.response?.data?.errors) {
      serverErrors.value = err.response.data.errors
    }
    const msg = err.response?.data?.message || err.message || 'Failed to save.'
    toast('error', msg)
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
      <!-- Single white card: heading + border + form (same background, thin border between) -->
      <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <!-- Heading: same background as form -->
        <div class="px-4 py-4 sm:px-5">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-baseline gap-2">
              <h1 class="text-xl font-semibold text-gray-900">Edit Field Submission</h1>
              <Breadcrumbs />
            </div>
            <router-link
              to="/field-submissions"
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
          Unable to load submission. You may not have permission to view it.
        </div>

        <form v-else class="px-4 py-5 sm:px-5" @submit.prevent="submitForm">
        <!-- Section 1: Primary Information -->
        <h2 class="mb-4 text-sm font-semibold text-gray-900">Primary Information</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
              <label class="block text-sm font-medium text-gray-700">Company Name <span class="text-red-500">*</span></label>
              <input
                v-model="form.company_name"
                type="text"
                required
                :class="['mt-1 block w-full rounded border px-3 py-2 shadow-sm focus:ring-1', serverErrors.company_name ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-green-500 focus:ring-green-500']"
              />
              <p v-if="serverErrors.company_name" class="mt-1 text-xs text-red-600">{{ serverErrors.company_name[0] }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Product <span class="text-red-500">*</span></label>
              <input
                v-model="form.product"
                type="text"
                required
                :class="['mt-1 block w-full rounded border px-3 py-2 shadow-sm focus:ring-1', serverErrors.product ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-green-500 focus:ring-green-500']"
              />
              <p v-if="serverErrors.product" class="mt-1 text-xs text-red-600">{{ serverErrors.product[0] }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Contact Number <span class="text-red-500">*</span></label>
              <input
                v-model="form.contact_number"
                type="text"
                required
                maxlength="12"
                placeholder="971XXXXXXXXX"
                autocomplete="off"
                :class="['contact-number-input mt-1 block w-full rounded border px-3 py-2 shadow-sm focus:ring-1', fieldErrors.contact_number || serverErrors.contact_number ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-green-500 focus:ring-green-500']"
                @input="onPhoneInput('contact_number', $event)"
              />
              <p v-if="fieldErrors.contact_number" class="mt-1 text-xs text-red-600">{{ fieldErrors.contact_number }}</p>
              <p v-else-if="serverErrors.contact_number" class="mt-1 text-xs text-red-600">{{ serverErrors.contact_number[0] }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Alternate Contact Number</label>
              <input
                v-model="form.alternate_number"
                type="text"
                maxlength="12"
                placeholder="971XXXXXXXXX"
                autocomplete="off"
                :class="['mt-1 block w-full rounded border px-3 py-2 shadow-sm focus:ring-1', fieldErrors.alternate_number || serverErrors.alternate_number ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-green-500 focus:ring-green-500']"
                @input="onPhoneInput('alternate_number', $event)"
              />
              <p v-if="fieldErrors.alternate_number" class="mt-1 text-xs text-red-600">{{ fieldErrors.alternate_number }}</p>
              <p v-else-if="serverErrors.alternate_number" class="mt-1 text-xs text-red-600">{{ serverErrors.alternate_number[0] }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Emirates <span class="text-red-500">*</span></label>
              <select
                v-model="form.emirates"
                required
                :class="['mt-1 block w-full rounded border px-3 py-2 shadow-sm focus:ring-1', serverErrors.emirates ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-green-500 focus:ring-green-500']"
              >
                <option value="">Select Emirates</option>
                <option v-for="e in emiratesList" :key="e" :value="e">{{ e }}</option>
              </select>
              <p v-if="serverErrors.emirates" class="mt-1 text-xs text-red-600">{{ serverErrors.emirates[0] }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Location Coordinates</label>
              <input
                v-model="form.location_coordinates"
                type="text"
                placeholder="e.g. 25.2048, 55.2708"
                :class="['mt-1 block w-full rounded border px-3 py-2 shadow-sm focus:ring-1', fieldErrors.location_coordinates ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-green-500 focus:ring-green-500']"
                @input="fieldErrors.location_coordinates = null"
              />
              <p v-if="fieldErrors.location_coordinates" class="mt-1 text-xs text-red-600">{{ fieldErrors.location_coordinates }}</p>
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700">Complete Address <span class="text-red-500">*</span></label>
            <input
              v-model="form.complete_address"
              type="text"
              required
              :class="['mt-1 block w-full rounded border px-3 py-2 shadow-sm focus:ring-1', serverErrors.complete_address ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-green-500 focus:ring-green-500']"
            />
            <p v-if="serverErrors.complete_address" class="mt-1 text-xs text-red-600">{{ serverErrors.complete_address[0] }}</p>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700">Additional Notes</label>
              <textarea
                v-model="form.additional_notes"
                rows="3"
                placeholder="Enter Additional Notes"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Any Special Instruction</label>
              <textarea
                v-model="form.special_instruction"
                rows="3"
                placeholder="Enter Special Instruction"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              />
            </div>
        </div>

        <!-- Section 2: Team Member -->
        <h2 class="mb-4 mt-8 text-sm font-semibold text-gray-900">Team Member</h2>
        <div class="grid gap-4 sm:grid-cols-3">
          <div>
            <label class="block text-sm font-medium text-gray-700">Sales Agent Name</label>
            <select
              v-model="form.sales_agent_id"
              :class="['mt-1 block w-full rounded border px-3 py-2 shadow-sm focus:ring-1', serverErrors.sales_agent_id ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-green-500 focus:ring-green-500']"
            >
              <option :value="null">Select</option>
              <option v-for="u in filteredSalesAgents" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
            <p v-if="serverErrors.sales_agent_id" class="mt-1 text-xs text-red-600">{{ serverErrors.sales_agent_id[0] }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Team Leader Name</label>
            <select
              v-model="form.team_leader_id"
              :class="['mt-1 block w-full rounded border px-3 py-2 shadow-sm focus:ring-1', serverErrors.team_leader_id ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-green-500 focus:ring-green-500']"
            >
              <option :value="null">Select</option>
              <option v-for="u in filteredTeamLeaders" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
            <p v-if="serverErrors.team_leader_id" class="mt-1 text-xs text-red-600">{{ serverErrors.team_leader_id[0] }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Manager Name <span class="text-red-500">*</span></label>
            <select
              v-model="form.manager_id"
              required
              :class="['mt-1 block w-full rounded border px-3 py-2 shadow-sm focus:ring-1', serverErrors.manager_id ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-green-500 focus:ring-green-500']"
            >
              <option :value="null">Select</option>
              <option v-for="u in teamOptions.managers" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
            <p v-if="serverErrors.manager_id" class="mt-1 text-xs text-red-600">{{ serverErrors.manager_id[0] }}</p>
          </div>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
          <div>
            <label class="block text-sm font-medium text-gray-700">Field Executive Name <span class="text-red-500">*</span></label>
            <select
              v-model="form.field_executive_id"
              class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            >
              <option :value="null">Select</option>
              <option v-for="u in teamOptions.field_executives" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Status <span class="text-red-500">*</span></label>
            <select
              v-model="form.field_status"
              class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            >
              <option value="">Select</option>
              <option v-for="s in editOptions.field_statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Meeting Date <span class="text-red-500">*</span></label>
            <div class="relative mt-1" @click="openDatePicker(meetingDateRef)">
              <input
                type="text"
                readonly
                :value="formatDateDisplay(form.meeting_date)"
                placeholder="DD-MMM-YYYY"
                class="block w-full cursor-pointer rounded border border-gray-300 bg-white px-3 py-2 pr-9 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              />
              <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
              <input ref="meetingDateRef" v-model="form.meeting_date" type="date" class="sr-only" tabindex="-1" />
            </div>
          </div>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <div class="flex flex-col">
            <label class="block text-sm font-medium text-gray-700">Photographic Proof <span class="text-red-500">*</span></label>
            <input ref="fileInput" type="file" multiple accept="image/*,.pdf" class="hidden" @change="onFileChange" />
            <div
              class="mt-1 flex min-h-[120px] flex-1 items-center gap-3 rounded-lg border border-gray-300 bg-white px-3 py-2"
              @click="triggerFileSelect"
            >
              <svg class="h-8 w-8 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span class="min-w-0 flex-1 text-sm text-gray-600">Upload Required Document</span>
              <button
                type="button"
                class="flex-shrink-0 rounded bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-600 hover:bg-blue-100"
                @click.stop="triggerFileSelect"
              >
                <span class="inline-flex items-center gap-1">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                  </svg>
                  Upload
                </span>
              </button>
            </div>
            <div v-if="existingDocuments.length || newFiles.length" class="mt-2 flex flex-wrap gap-2">
              <template v-for="doc in existingDocuments" :key="doc.id">
                <div class="flex items-center gap-2 rounded border border-gray-200 bg-gray-50 px-2 py-1 text-xs">
                  <span class="max-w-[140px] truncate">{{ docDisplayName(doc) }}</span>
                  <button type="button" class="text-blue-600 hover:underline" @click="downloadDoc(doc)">Download</button>
                </div>
              </template>
              <template v-for="(f, idx) in newFiles" :key="'new-' + idx">
                <div class="flex items-center gap-2 rounded border border-green-200 bg-green-50 px-2 py-1 text-xs">
                  <span class="max-w-[140px] truncate">{{ f.name }}</span>
                  <button type="button" class="text-red-600 hover:underline" @click="removeNewFile(idx)">Remove</button>
                </div>
              </template>
            </div>
          </div>
          <div class="flex flex-col">
            <label class="block text-sm font-medium text-gray-700">Remarks</label>
            <textarea
              v-model="form.remarks_by_field_agent"
              rows="5"
              placeholder="Remarks by Field Agent"
              class="mt-1 min-h-[120px] w-full flex-1 resize-y rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            />
          </div>
        </div>

        <!-- Actions (footer inside same card) -->
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
            class="inline-flex items-center gap-2 rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-70"
          >
            <svg v-if="!saving" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
            <span v-if="saving">Saving...</span>
            <span v-else>Update Field Submission</span>
          </button>
        </div>
        </form>
      </div>
    </div>
    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>

<style scoped>
/* Remove browser's clear (X) button from Contact Number field */
.contact-number-input::-ms-clear {
  display: none;
}
.contact-number-input::-webkit-search-cancel-button {
  display: none;
}
</style>
