<script setup>
/**
 * Field Submission Change History – super admin only. Lists who changed what, when.
 */
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import fieldSubmissionsApi from '@/services/fieldSubmissionsApi'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Pagination from '@/components/Pagination.vue'

const router = useRouter()
const auth = useAuthStore()
const loading = ref(true)
const items = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const filterSubmissionId = ref('')

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

function fieldLabel(name) {
  const labels = {
    company_name: 'Company Name',
    contact_number: 'Contact Number',
    product: 'Product',
    emirates: 'Emirates',
    complete_address: 'Address',
    manager_id: 'Manager',
    team_leader_id: 'Team Leader',
    sales_agent_id: 'Sales Agent',
    field_executive_id: 'Field Agent',
    meeting_date: 'Meeting Date',
    field_status: 'Field Status',
    status: 'Status',
  }
  return labels[name] ?? name
}

async function load() {
  if (!isSuperAdmin()) {
    router.replace('/field-submissions')
    return
  }
  loading.value = true
  try {
    const params = { page: meta.value.current_page, per_page: meta.value.per_page }
    if (filterSubmissionId.value) params.field_submission_id = filterSubmissionId.value
    const res = await fieldSubmissionsApi.getAuditLog(params)
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

function applyFilter() {
  meta.value.current_page = 1
  load()
}

onMounted(() => {
  if (!isSuperAdmin()) {
    router.replace('/field-submissions')
    return
  }
  load()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-7xl space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-xl font-semibold text-gray-900">Field Submission Change History</h1>
        <router-link
          to="/field-submissions"
          class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
        >
          Back to Field Submissions
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
            class="rounded border border-gray-300 px-2 py-1.5 text-sm w-40"
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

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
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
                        :to="`/field-submissions/${row.field_submission_id}`"
                        class="text-green-600 hover:underline"
                      >
                        {{ row.field_submission_id }}
                      </router-link>
                    </td>
                    <td class="max-w-[180px] truncate px-4 py-3 text-sm text-gray-900" :title="row.company_name">
                      {{ row.company_name }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                      {{ fieldLabel(row.field_name) }}
                    </td>
                    <td class="max-w-[200px] truncate px-4 py-3 text-sm text-gray-700" :title="row.old_value ?? '—'">
                      {{ row.old_value ?? '—' }}
                    </td>
                    <td class="max-w-[200px] truncate px-4 py-3 text-sm text-gray-700" :title="row.new_value ?? '—'">
                      {{ row.new_value ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">{{ row.changed_by }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ formatDateTime(row.changed_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div
              class="flex flex-wrap items-center gap-4 border-t border-black bg-white px-4 py-3"
              :class="meta.last_page > 1 ? 'justify-between' : 'justify-start'"
            >
              <p class="text-sm text-gray-600">
                Showing {{ meta.total ? (meta.current_page - 1) * meta.per_page + 1 : 0 }} to
                {{ Math.min(meta.current_page * meta.per_page, meta.total) }} of {{ meta.total }} records
              </p>
              <Pagination
                v-if="meta.last_page > 1"
                :meta="{
                  prev_page_url: meta.current_page > 1 ? '#' : null,
                  next_page_url: meta.current_page < meta.last_page ? '#' : null,
                  current_page: meta.current_page,
                  last_page: meta.last_page,
                }"
                @change="onPageChange"
              />
            </div>
          </template>
        </div>
      </template>
    </div>
  </div>
</template>
