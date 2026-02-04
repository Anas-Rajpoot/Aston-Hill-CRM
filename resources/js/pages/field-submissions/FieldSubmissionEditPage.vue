<script setup>
/**
 * Field Head Edit – full page form matching the design (Primary Info, Team Member, Field Executive, Photographic Proof, Remarks).
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import fieldSubmissionsApi from '@/services/fieldSubmissionsApi'
import { useAuthStore } from '@/stores/auth'

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
  contact_number: '',
  complete_address: '',
  product: '',
  emirates: '',
  location_coordinates: '',
  special_instruction: '',
  additional_notes: '',
  sales_agent_id: null,
  team_leader_id: null,
  manager_id: null,
  field_executive_id: null,
  field_status: '',
  meeting_date: '',
  remarks_by_field_agent: '',
})

const newFiles = ref([])
const fileInput = ref(null)

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
      field_executives: teamRes.field_executives ?? teamRes.sales_agents ?? [],
    }
    editOptions.value = {
      emirates: optionsRes.emirates ?? [],
      field_statuses: optionsRes.field_statuses ?? [],
    }
    form.value = {
      company_name: data.company_name ?? '',
      contact_number: data.contact_number ?? '',
      complete_address: data.complete_address ?? '',
      product: data.product ?? '',
      emirates: data.emirates ?? '',
      location_coordinates: data.location_coordinates ?? '',
      special_instruction: data.special_instruction ?? '',
      additional_notes: data.additional_notes ?? '',
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

async function submitForm() {
  if (!id.value) return
  saving.value = true
  try {
    const payload = {
      company_name: form.value.company_name,
      contact_number: form.value.contact_number,
      complete_address: form.value.complete_address,
      product: form.value.product,
      emirates: form.value.emirates,
      location_coordinates: form.value.location_coordinates || null,
      additional_notes: form.value.additional_notes || null,
      special_instruction: form.value.special_instruction || null,
      manager_id: form.value.manager_id,
      team_leader_id: form.value.team_leader_id,
      sales_agent_id: form.value.sales_agent_id,
      field_executive_id: form.value.field_executive_id || null,
      meeting_date: form.value.meeting_date || null,
      field_status: form.value.field_status || null,
      remarks_by_field_agent: form.value.remarks_by_field_agent || null,
    }
    await fieldSubmissionsApi.updateSubmission(id.value, payload, newFiles.value.length ? newFiles.value : null)
    await load()
    newFiles.value = []
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Failed to save.'
    alert(msg)
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  load()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-4xl">
      <!-- Header -->
      <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="rounded p-2 text-gray-600 hover:bg-gray-200 hover:text-gray-900"
            aria-label="Back"
            @click="goBack"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
          </button>
          <h1 class="text-xl font-semibold text-gray-900">Field Head Edit</h1>
          <span
            v-if="auth.user?.name"
            class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-green-600 text-sm font-medium text-white"
          >
            {{ auth.user.name.charAt(0).toUpperCase() }}
          </span>
        </div>
      </div>

      <div v-if="loading" class="flex justify-center py-16">
        <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <div v-else-if="!submission" class="rounded-lg border border-gray-200 bg-white p-8 text-center text-gray-500">
        Unable to load submission. You may not have permission to view it.
      </div>

      <form v-else class="space-y-6" @submit.prevent="submitForm">
        <!-- Primary Information -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
          <h2 class="mb-4 text-sm font-semibold text-gray-900">Primary Information</h2>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Company Name <span class="text-red-500">*</span></label>
              <input
                v-model="form.company_name"
                type="text"
                required
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Contact Number <span class="text-red-500">*</span></label>
              <input
                v-model="form.contact_number"
                type="text"
                required
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Complete Address <span class="text-red-500">*</span></label>
              <input
                v-model="form.complete_address"
                type="text"
                required
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Product <span class="text-red-500">*</span></label>
              <input
                v-model="form.product"
                type="text"
                required
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Emirates <span class="text-red-500">*</span></label>
              <select
                v-model="form.emirates"
                required
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              >
                <option value="">Select Emirates</option>
                <option v-for="e in emiratesList" :key="e" :value="e">{{ e }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Location Coordinates</label>
              <input
                v-model="form.location_coordinates"
                type="text"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Remarks / Comment</label>
              <textarea
                v-model="form.special_instruction"
                rows="2"
                placeholder="Enter any remarks or comments"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Additional Notes</label>
              <textarea
                v-model="form.additional_notes"
                rows="2"
                placeholder="Enter any additional notes"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              />
            </div>
          </div>
        </div>

        <!-- Team Member -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
          <h2 class="mb-4 text-sm font-semibold text-gray-900">Team Member</h2>
          <div class="grid gap-4 sm:grid-cols-3">
            <div>
              <label class="block text-sm font-medium text-gray-700">Sales Agent Name <span class="text-red-500">*</span></label>
              <select
                v-model="form.sales_agent_id"
                required
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              >
                <option :value="null">Select</option>
                <option v-for="u in filteredSalesAgents" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Team Leader Name <span class="text-red-500">*</span></label>
              <select
                v-model="form.team_leader_id"
                required
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              >
                <option :value="null">Select</option>
                <option v-for="u in filteredTeamLeaders" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Manager Name <span class="text-red-500">*</span></label>
              <select
                v-model="form.manager_id"
                required
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              >
                <option :value="null">Select</option>
                <option v-for="u in teamOptions.managers" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Additional Notes (Field Executive section) -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
          <h2 class="mb-4 text-sm font-semibold text-gray-900">Additional Notes</h2>
          <div class="grid gap-4 sm:grid-cols-3">
            <div>
              <label class="block text-sm font-medium text-gray-700">Field Executive Name</label>
              <select
                v-model="form.field_executive_id"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              >
                <option :value="null">Select</option>
                <option v-for="u in teamOptions.field_executives" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Status</label>
              <select
                v-model="form.field_status"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              >
                <option value="">Select</option>
                <option v-for="s in editOptions.field_statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Meeting Date</label>
              <input
                v-model="form.meeting_date"
                type="date"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              />
            </div>
          </div>
        </div>

        <!-- Photographic Proof -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
          <h2 class="mb-4 text-sm font-semibold text-gray-900">Photographic Proof</h2>
          <input ref="fileInput" type="file" multiple accept="image/*,.pdf" class="hidden" @change="onFileChange" />
          <div class="flex flex-wrap items-center gap-4">
            <div
              class="flex flex-1 cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 py-8 px-4 hover:border-green-400 hover:bg-green-50/50"
              @click="triggerFileSelect"
            >
              <svg class="h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span class="mt-2 text-sm text-gray-600">Upload Required Document</span>
            </div>
            <button
              type="button"
              class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
              @click="triggerFileSelect"
            >
              Upload
            </button>
          </div>
          <!-- Existing documents -->
          <div v-if="existingDocuments.length" class="mt-4 flex flex-wrap gap-2">
            <div
              v-for="doc in existingDocuments"
              :key="doc.id"
              class="flex items-center gap-2 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm"
            >
              <span class="truncate max-w-[200px]">{{ docDisplayName(doc) }}</span>
              <button type="button" class="text-blue-600 hover:underline" @click="downloadDoc(doc)">Download</button>
            </div>
          </div>
          <!-- New files to upload -->
          <div v-if="newFiles.length" class="mt-4 flex flex-wrap gap-2">
            <div
              v-for="(f, idx) in newFiles"
              :key="idx"
              class="flex items-center gap-2 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm"
            >
              <span class="truncate max-w-[200px]">{{ f.name }}</span>
              <span class="text-gray-500">({{ formatFileSize(f.size) }})</span>
              <button type="button" class="text-red-600 hover:underline" @click="removeNewFile(idx)">Remove</button>
            </div>
          </div>
        </div>

        <!-- Remarks by Field Agent -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
          <h2 class="mb-4 text-sm font-semibold text-gray-900">Remarks</h2>
          <textarea
            v-model="form.remarks_by_field_agent"
            rows="3"
            placeholder="Remarks by Field Agent"
            class="block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          />
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4">
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
            class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-70"
          >
            <span v-if="saving">Saving...</span>
            <span v-else>Submit Field Head</span>
            <svg v-if="!saving" class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
