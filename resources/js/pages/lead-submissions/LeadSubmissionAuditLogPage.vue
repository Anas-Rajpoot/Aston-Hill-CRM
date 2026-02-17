<script setup>
/**
 * Lead Submission Change History – super admin only. Lists who changed what, when.
 */
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import leadSubmissionsApi from '@/services/leadSubmissionsApi'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const router = useRouter()
const auth = useAuthStore()
const loading = ref(true)
const items = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: auth.defaultTablePageSize || 25, total: 0 })
const filterSubmissionId = ref('')
const TABLE_MODULE = 'lead-audit-log'
const perPageOptions = [10, 20, 25, 50, 100]

const isSuperAdmin = () => (auth.user?.roles ?? []).includes('superadmin')

function formatDateTime(iso) {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function fieldLabel(name, row) {
  if (row?.field_label) return row.field_label
  const labels = {
    company_name: 'Company Name',
    account_number: 'Account Number',
    authorized_signatory_name: 'Authorized Signatory',
    email: 'Email',
    contact_number_gsm: 'Contact Number (GSM)',
    alternate_contact_number: 'Alternate Contact',
    address: 'Address',
    emirates: 'Emirates',
    emirate: 'Emirate',
    product: 'Product',
    manager_id: 'Manager',
    team_leader_id: 'Team Leader',
    sales_agent_id: 'Sales Agent',
    executive_id: 'Back Office Executive',
    back_office_executive_id: 'Back Office Executive',
    field_executive_id: 'Field Agent',
    csr_id: 'Customer Support Representative',
    status: 'Status',
    step: 'Submission Step',
    submission_date_from: 'Submission Date',
    submitted_at: 'Submitted At',
    status_changed_at: 'Status Changed At',
    submission_type: 'Submission Type',
    call_verification: 'Call Verification',
    pending_from_sales: 'Pending From Sales',
    documents_verification: 'Documents Verification',
    back_office_notes: 'Back Office Notes',
    activity: 'Activity',
    back_office_account: 'Back Office Account',
    work_order: 'Work Order',
    du_status: 'DU Status',
    completion_date: 'Completion Date',
    du_remarks: 'DU Remarks',
    additional_note: 'Additional Note',
    service_category_id: 'Service Category',
    service_type_id: 'Service Type',
    created_by: 'Created By',
    team_id: 'Team',
    department_id: 'Department',
  }
  return labels[name] ?? name.replace(/_id$/, '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

async function load() {
  if (!isSuperAdmin()) {
    router.replace('/lead-submissions')
    return
  }
  loading.value = true
  try {
    const params = { page: meta.value.current_page, per_page: meta.value.per_page }
    if (filterSubmissionId.value) params.lead_submission_id = filterSubmissionId.value
    const res = await leadSubmissionsApi.getAuditLog(params)
    items.value = res.data ?? []
    meta.value = res.meta ?? meta.value
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }
}

function onPageChange(page) {
  meta.value.current_page = page
  load()
}

function onPerPageChange(e) {
  const newPerPage = Number(e.target.value)
  if (!perPageOptions.includes(newPerPage)) return
  meta.value.per_page = newPerPage
  meta.value.current_page = 1
  load()
  // Save preference
  api.post(`/table-preferences/${TABLE_MODULE}`, { per_page: newPerPage }).catch(() => {})
}

async function loadTablePreference() {
  try {
    const { data } = await api.get(`/table-preferences/${TABLE_MODULE}`)
    const pp = Number(data.per_page)
    if (pp && perPageOptions.includes(pp)) {
      meta.value.per_page = pp
    }
  } catch {
    // silent — use default
  }
}

function applyFilter() {
  meta.value.current_page = 1
  load()
}

onMounted(() => {
  if (!isSuperAdmin()) {
    router.replace('/lead-submissions')
    return
  }
  loadTablePreference().then(() => load())
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-7xl space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-xl font-semibold text-gray-900">Lead Submission Change History</h1>
        <router-link
          to="/lead-submissions"
          class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
        >
          Back to Lead Submissions
        </router-link>
      </div>

      <p v-if="!isSuperAdmin()" class="text-sm text-amber-700">
        Only super admin can view this page.
      </p>

      <template v-else>
        <Breadcrumbs />

        <div class="flex flex-wrap items-center gap-2">
          <label class="text-sm font-medium text-gray-700">Submission ID</label>
          <input
            v-model="filterSubmissionId"
            type="text"
            placeholder="Filter by ID (optional)"
            class="w-40 rounded border border-gray-300 px-2 py-1.5 text-sm"
            @keydown.enter="applyFilter"
          />
          <button
            type="button"
            class="rounded bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"
            @click="applyFilter"
          >
            Apply
          </button>
        </div>

        <div class="overflow-hidden rounded-lg border-2 border-black bg-white shadow-sm">
          <div
            v-if="loading"
            class="flex justify-center py-16"
          >
            <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
          </div>

          <template v-else>
            <div class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-black bg-green-600">
                    <th class="px-4 py-3 text-left text-sm font-bold uppercase tracking-wider text-white">Submission ID</th>
                    <th class="px-4 py-3 text-left text-sm font-bold uppercase tracking-wider text-white">Company</th>
                    <th class="px-4 py-3 text-left text-sm font-bold uppercase tracking-wider text-white">Field</th>
                    <th class="px-4 py-3 text-left text-sm font-bold uppercase tracking-wider text-white">Old Value</th>
                    <th class="px-4 py-3 text-left text-sm font-bold uppercase tracking-wider text-white">New Value</th>
                    <th class="px-4 py-3 text-left text-sm font-bold uppercase tracking-wider text-white">Changed By</th>
                    <th class="px-4 py-3 text-left text-sm font-bold uppercase tracking-wider text-white">Date & Time</th>
                  </tr>
                </thead>
                <tbody class="bg-white">
                  <tr v-if="!items.length" class="border-b border-black">
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">No change records found.</td>
                  </tr>
                  <tr
                    v-for="row in items"
                    :key="row.id"
                    class="border-b border-black hover:bg-gray-50/50"
                  >
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                      <router-link
                        :to="`/lead-submissions/${row.lead_submission_id}`"
                        class="text-green-600 hover:underline"
                      >
                        {{ row.lead_submission_id }}
                      </router-link>
                    </td>
                    <td class="max-w-[180px] truncate px-4 py-3 text-sm text-gray-900" :title="row.company_name">
                      {{ row.company_name }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                      {{ fieldLabel(row.field_name, row) }}
                    </td>
                    <td class="max-w-[200px] truncate px-4 py-3 text-sm text-gray-700" :title="row.old_value ?? '(empty)'">
                      {{ row.old_value ?? '(empty)' }}
                    </td>
                    <td class="max-w-[200px] truncate px-4 py-3 text-sm text-gray-700" :title="row.new_value ?? '(empty)'">
                      {{ row.new_value ?? '(empty)' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">{{ row.changed_by_name || row.changed_by || '—' }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ formatDateTime(row.changed_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-black bg-white px-4 py-3">
              <p class="text-sm text-gray-600">
                Showing {{ meta.total ? (meta.current_page - 1) * meta.per_page + 1 : 0 }} to
                {{ Math.min(meta.current_page * meta.per_page, meta.total) }} of {{ meta.total }} entries
              </p>
              <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 text-sm text-gray-600">
                  <span class="whitespace-nowrap font-medium">Number of rows</span>
                  <select
                    :value="meta.per_page"
                    class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm min-w-[80px] text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
                    @change="onPerPageChange"
                  >
                    <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
                  </select>
                </div>
                <div class="flex items-center gap-1.5">
                  <button type="button" :disabled="meta.current_page <= 1" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" @click="onPageChange(meta.current_page - 1)">Previous</button>
                  <span class="rounded-md border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm text-gray-700">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
                  <button type="button" :disabled="meta.current_page >= meta.last_page" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" @click="onPageChange(meta.current_page + 1)">Next</button>
                </div>
              </div>
            </div>
          </template>
        </div>
      </template>
    </div>
  </div>
</template>
