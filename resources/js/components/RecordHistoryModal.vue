<script setup>
/**
 * Reusable Edit-History modal for field-level audit data.
 * Groups individual field changes by (changed_at + changed_by) into timeline entries.
 * When a field value is a JSON object (e.g. "payload"), it diffs old vs new and shows
 * only the individual sub-fields that actually changed.
 */
import { ref, watch, computed } from 'vue'

const props = defineProps({
  visible: { type: Boolean, default: false },
  recordId: { type: [Number, String], default: null },
  recordLabel: { type: String, default: '' },
  moduleName: { type: String, default: '' },
  fetchFn: { type: Function, required: true },
  fieldLabels: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close'])

const SKIP_FIELDS = ['updated_at', 'created_at', 'deleted_at', 'id', 'remember_token', '_method', '_token', 'payload']

const COMMON_LABELS = {
  step: 'Wizard Step',
  submitted_at: 'Submitted At',
  status: 'Status',
  status_changed_at: 'Status Changed At',
  submission_type: 'Submission Type',
  company_name: 'Company Name',
  account_number: 'Account Number',
  authorized_signatory_name: 'Authorized Signatory',
  email: 'Email',
  contact_number_gsm: 'Contact Number (GSM)',
  alternate_contact_number: 'Alternate Contact Number',
  address: 'Address',
  emirate: 'Emirate',
  location_coordinates: 'Location Coordinates',
  category: 'Service Category',
  category_name: 'Service Category',
  type: 'Service Type',
  type_name: 'Service Type',
  product: 'Product',
  offer: 'Offer',
  mrc_aed: 'MRC (AED)',
  quantity: 'Quantity',
  ae_domain: 'AE Domain',
  gaid: 'GAID',
  remarks: 'Remarks',
  sales_agent: 'Sales Agent',
  sales_agent_id: 'Sales Agent',
  team_leader: 'Team Leader',
  team_leader_id: 'Team Leader',
  manager: 'Manager',
  manager_id: 'Manager',
  back_office_executive_id: 'Back Office Executive',
  executive: 'Back Office Executive',
  call_verification: 'Call Verification',
  pending_from_sales: 'Pending From Sales',
  documents_verification: 'Documents Verification',
  back_office_notes: 'Back Office Notes',
  back_office_account: 'Back Office Account',
  work_order: 'Work Order',
  du_status: 'DU Status',
  completion_date: 'Completion Date',
  du_remarks: 'DU Remarks',
  additional_note: 'Additional Note',
  issue_description: 'Issue Description',
  issue_category: 'Issue Category',
  workflow_status: 'Workflow Status',
  pending_with: 'Pending With',
  request_type: 'Request Type',
  sim_type: 'SIM Type',
  plan_name: 'Plan Name',
  monthly_charges: 'Monthly Charges',
  contract_period: 'Contract Period',
  activation_date: 'Activation Date',
  field_status: 'Field Status',
  field_executive_id: 'Field Executive',
  service_type: 'Service Type',
  creator: 'Created By',
  activity: 'Activity',
}

function formatDateTime(iso) {
  if (!iso) return '—'
  const d = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  return `${pad(d.getDate())}-${months[d.getMonth()]}-${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
}

function prettyField(name) {
  if (!name) return '—'
  if (props.fieldLabels[name]) return props.fieldLabels[name]
  if (COMMON_LABELS[name]) return COMMON_LABELS[name]
  return name
    .replace(/_id$/, '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
}

function formatVal(v) {
  if (v == null || v === '') return '(empty)'
  if (typeof v === 'object') return JSON.stringify(v)
  const s = String(v)
  if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(s)) {
    return formatDateTime(s)
  }
  return s
}

function tryParseJson(val) {
  if (val == null || val === '') return null
  if (typeof val === 'object' && !Array.isArray(val)) return val
  if (typeof val === 'string') {
    const trimmed = val.trim()
    if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
      try { return JSON.parse(trimmed) } catch { return null }
    }
  }
  return null
}

function normalise(v) {
  if (v == null) return ''
  if (typeof v === 'object') return JSON.stringify(v)
  return String(v)
}

function expandAuditRow(a) {
  const oldObj = tryParseJson(a.old_value)
  const newObj = tryParseJson(a.new_value)

  if (oldObj && newObj && typeof oldObj === 'object' && typeof newObj === 'object'
      && !Array.isArray(oldObj) && !Array.isArray(newObj)) {
    const allKeys = new Set([...Object.keys(oldObj), ...Object.keys(newObj)])
    const changes = []
    for (const key of allKeys) {
      if (SKIP_FIELDS.includes(key)) continue
      const ov = normalise(oldObj[key])
      const nv = normalise(newObj[key])
      if (ov !== nv) {
        changes.push({ field: prettyField(key), oldVal: oldObj[key], newVal: newObj[key] })
      }
    }
    return changes.length ? changes : [{ field: prettyField(a.field_name), oldVal: '(no visible changes)', newVal: '(no visible changes)' }]
  }

  if (newObj && typeof newObj === 'object' && !Array.isArray(newObj) && !oldObj) {
    const changes = []
    for (const [key, val] of Object.entries(newObj)) {
      if (SKIP_FIELDS.includes(key)) continue
      if (val != null && val !== '') {
        changes.push({ field: prettyField(key), oldVal: null, newVal: val })
      }
    }
    return changes.length ? changes : [{ field: prettyField(a.field_name), oldVal: null, newVal: '(set)' }]
  }

  if (oldObj && typeof oldObj === 'object' && !Array.isArray(oldObj) && !newObj) {
    const changes = []
    for (const [key, val] of Object.entries(oldObj)) {
      if (SKIP_FIELDS.includes(key)) continue
      if (val != null && val !== '') {
        changes.push({ field: prettyField(key), oldVal: val, newVal: null })
      }
    }
    return changes.length ? changes : [{ field: prettyField(a.field_name), oldVal: '(removed)', newVal: null }]
  }

  if (SKIP_FIELDS.includes(a.field_name)) return []

  return [{ field: prettyField(a.field_name), oldVal: a.old_value, newVal: a.new_value }]
}

const auditLog = ref([])
const loading = ref(false)
const selectedEntry = ref(null)

const timelineEntries = computed(() => {
  const groups = {}
  for (const a of auditLog.value) {
    const key = `${a.changed_at || a.created_at}__${a.changed_by_name || a.changed_by || a.user_name || 'System'}`
    if (!groups[key]) {
      groups[key] = {
        id: a.id,
        user_name: a.changed_by_name || a.changed_by || a.user_name || 'System',
        user_role: a.user_role || '',
        changed_at: a.changed_at || a.created_at,
        formatted_at: formatDateTime(a.changed_at || a.created_at),
        changes: [],
      }
    }
    const expanded = expandAuditRow(a)
    groups[key].changes.push(...expanded)
  }

  return Object.values(groups).map((entry) => ({
    ...entry,
    preview: entry.changes.slice(0, 3),
    moreCount: Math.max(0, entry.changes.length - 3),
  }))
})

async function loadAudit() {
  if (!props.recordId || !props.visible) return
  loading.value = true
  try {
    const result = await props.fetchFn(props.recordId)
    auditLog.value = result?.data ?? result ?? []
  } catch {
    auditLog.value = []
  } finally {
    loading.value = false
  }
}

function openChangeDetails(entry) {
  selectedEntry.value = entry
}

function closeChangeDetails() {
  selectedEntry.value = null
}

watch(
  () => [props.visible, props.recordId],
  ([v, id]) => {
    if (v && id) loadAudit()
    if (!v) selectedEntry.value = null
  },
  { immediate: true }
)
</script>

<template>
  <Teleport to="body">
    <div
      v-if="visible"
      class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/50 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="record-history-title"
      @click.self="$emit('close')"
    >
      <div class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-lg bg-white shadow-xl">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
          <h2 id="record-history-title" class="text-lg font-semibold text-gray-900">Edit History</h2>
          <button
            type="button"
            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
            aria-label="Close"
            @click="$emit('close')"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Record summary -->
        <div class="mx-6 mt-4 flex items-center gap-3 rounded-lg bg-gray-100 px-4 py-3">
          <span class="flex h-10 w-10 items-center justify-center rounded-full bg-green-500 text-white">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </span>
          <div>
            <p class="font-medium text-gray-900">{{ recordLabel || `Record #${recordId}` }}</p>
            <p class="text-sm text-gray-600">Record ID: {{ recordId }}<template v-if="moduleName"> &bull; Module: {{ moduleName }}</template></p>
          </div>
        </div>

        <!-- Timeline -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
          <div v-if="loading" class="flex justify-center py-8">
            <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
          </div>
          <template v-else>
            <div v-if="!timelineEntries.length" class="py-8 text-center text-sm text-gray-500">No change history yet.</div>
            <div v-else class="relative space-y-6 pl-6">
              <div class="absolute left-[11px] top-2 bottom-2 w-0.5 bg-gray-200" aria-hidden="true" />
              <div
                v-for="entry in timelineEntries"
                :key="entry.id"
                class="relative rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
              >
                <span class="absolute -left-[19px] top-5 h-4 w-4 rounded-full bg-green-500 ring-4 ring-white" aria-hidden="true" />
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <p class="font-medium text-gray-900">{{ entry.user_name }}{{ entry.user_role ? ` (${entry.user_role})` : '' }}</p>
                    <p class="text-sm text-gray-500">{{ entry.formatted_at }}</p>
                  </div>
                  <button
                    v-if="entry.changes.length > 0"
                    type="button"
                    class="shrink-0 text-sm font-medium text-green-600 hover:underline"
                    @click="openChangeDetails(entry)"
                  >
                    View All
                  </button>
                </div>
                <div class="mt-3 space-y-1.5">
                  <div
                    v-for="(c, ci) in entry.preview"
                    :key="ci"
                    class="flex flex-wrap items-center gap-1 text-sm"
                  >
                    <span class="font-medium text-gray-700">{{ c.field }}:</span>
                    <template v-if="c.oldVal != null && c.oldVal !== ''">
                      <span class="text-red-600 line-through break-all">{{ formatVal(c.oldVal) }}</span>
                      <span v-if="c.newVal != null && c.newVal !== ''" class="text-gray-400">&rarr;</span>
                    </template>
                    <span v-if="c.newVal != null && c.newVal !== ''" class="text-green-600 break-all">{{ formatVal(c.newVal) }}</span>
                  </div>
                  <p v-if="entry.moreCount > 0" class="text-sm text-gray-500">+{{ entry.moreCount }} more field(s) changed</p>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Change Details overlay -->
      <div
        v-if="selectedEntry"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-500/50 p-4"
        role="dialog"
        aria-modal="true"
        @click.self="closeChangeDetails"
      >
        <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-gray-900">Change Details</h3>
            <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100" aria-label="Close" @click="closeChangeDetails">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <div class="px-6 py-4 space-y-4 max-h-[60vh] overflow-y-auto">
            <dl class="grid grid-cols-1 gap-2 text-sm">
              <div><dt class="font-medium text-gray-500">Modified By</dt><dd class="text-gray-900">{{ selectedEntry.user_name }}</dd></div>
              <div v-if="selectedEntry.user_role"><dt class="font-medium text-gray-500">Role</dt><dd class="text-gray-900">{{ selectedEntry.user_role }}</dd></div>
              <div><dt class="font-medium text-gray-500">Date &amp; Time</dt><dd class="text-gray-900">{{ selectedEntry.formatted_at }}</dd></div>
            </dl>
            <div>
              <h4 class="mb-2 font-medium text-gray-900">Fields Changed ({{ selectedEntry.changes.length }})</h4>
              <div class="space-y-4">
                <div v-for="(c, ci) in selectedEntry.changes" :key="ci" class="text-sm">
                  <p class="mb-1.5 font-medium text-gray-700">{{ c.field }}</p>
                  <div class="grid grid-cols-2 gap-2">
                    <div class="rounded bg-red-50 px-3 py-2 min-w-0">
                      <p class="text-xs font-medium text-gray-600">Before</p>
                      <p class="text-gray-900 break-all">{{ formatVal(c.oldVal) }}</p>
                    </div>
                    <div class="rounded bg-green-50 px-3 py-2 min-w-0">
                      <p class="text-xs font-medium text-gray-600">After</p>
                      <p class="text-gray-900 break-all">{{ formatVal(c.newVal) }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="flex justify-end border-t border-gray-200 px-6 py-4">
            <button
              type="button"
              class="rounded bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
              @click="closeChangeDetails"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
