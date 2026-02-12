<script setup>
/**
 * Edit History modal – timeline of changes (like image 1), "View All" opens Change Details (like image 2).
 * Data from expense audit log API; field labels for Expense Tracker module.
 */
import { ref, watch, computed } from 'vue'
import expensesApi from '@/services/expensesApi'

const props = defineProps({
  visible: { type: Boolean, default: false },
  expenseId: { type: [Number, String], default: null },
  expenseRef: { type: String, default: '' },
})

const emit = defineEmits(['close'])

const FIELD_LABELS = {
  expense_date: 'Expense Date',
  product_category: 'Product Category',
  product_description: 'Product Description',
  invoice_number: 'Invoice Number',
  comment: 'Comment / Remarks',
  vat_amount: 'VAT %',
  amount_without_vat: 'Amount (Without VAT)',
  full_amount: 'Total Amount',
  user_id: 'Added By',
  status: 'Status',
}

function formatAuditVal(v) {
  if (v == null || v === '') return '(empty)'
  if (typeof v === 'object') return JSON.stringify(v)
  return String(v)
}

function getChangesFromAudit(a) {
  const changes = []
  const skipKeys = ['updated_at', 'created_at']
  if (a.action === 'created' && a.new_values) {
    for (const [key, val] of Object.entries(a.new_values)) {
      if (skipKeys.includes(key)) continue
      changes.push({ field: FIELD_LABELS[key] ?? key, oldVal: null, newVal: val })
    }
  } else if (a.action === 'deleted' && a.old_values) {
    for (const [key, val] of Object.entries(a.old_values)) {
      if (skipKeys.includes(key)) continue
      changes.push({ field: FIELD_LABELS[key] ?? key, oldVal: val, newVal: null })
    }
  } else if (a.action === 'updated' && (a.old_values || a.new_values)) {
    const keys = new Set([...(Object.keys(a.old_values || {})), ...(Object.keys(a.new_values || {}))])
    for (const key of keys) {
      if (skipKeys.includes(key)) continue
      const oldVal = a.old_values?.[key]
      const newVal = a.new_values?.[key]
      if (oldVal !== newVal) {
        changes.push({ field: FIELD_LABELS[key] ?? key, oldVal, newVal })
      }
    }
  }
  return changes
}

function formatDateTime(iso) {
  if (!iso) return '—'
  const d = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  const day = pad(d.getDate())
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const mon = months[d.getMonth()]
  const year = d.getFullYear()
  const time = `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
  return `${day}-${mon}-${year} ${time}`
}

const auditLog = ref([])
const loading = ref(false)
const selectedEntry = ref(null)

const timelineEntries = computed(() => {
  return auditLog.value.map((a) => {
    const changes = getChangesFromAudit(a)
    const preview = changes.slice(0, 2)
    const moreCount = Math.max(0, changes.length - 2)
    return {
      id: a.id,
      user_name: a.user_name ?? 'System',
      user_role: a.user_role ?? '',
      created_at: a.created_at,
      formatted_at: formatDateTime(a.created_at),
      changes,
      preview,
      moreCount,
    }
  })
})

async function loadAudit() {
  if (!props.expenseId || !props.visible) return
  loading.value = true
  try {
    const { data } = await expensesApi.getAuditLog(props.expenseId)
    auditLog.value = data?.data ?? []
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
  () => [props.visible, props.expenseId],
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
      aria-labelledby="edit-history-title"
      @click.self="$emit('close')"
    >
      <div class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-lg bg-white shadow-xl">
        <!-- Edit History header -->
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
          <h2 id="edit-history-title" class="text-lg font-semibold text-gray-900">Edit History</h2>
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
            <p class="font-medium text-gray-900">{{ expenseRef || `Expense #${expenseId}` }}</p>
            <p class="text-sm text-gray-600">Record ID: {{ expenseId }} • Module: Expense Tracker</p>
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
                v-for="(entry, idx) in timelineEntries"
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
                    class="text-sm font-medium text-green-600 hover:underline"
                    @click="openChangeDetails(entry)"
                  >
                    View All
                  </button>
                </div>
                <div class="mt-3 space-y-1.5">
                  <div
                    v-for="c in entry.preview"
                    :key="c.field"
                    class="flex flex-wrap items-center gap-1 text-sm"
                  >
                    <span class="font-medium text-gray-700">{{ c.field }}:</span>
                    <span v-if="c.oldVal != null" class="text-red-600 line-through">{{ formatAuditVal(c.oldVal) }}</span>
                    <span v-if="c.oldVal != null && c.newVal != null" class="text-gray-400">→</span>
                    <span v-if="c.newVal != null" class="text-green-600">{{ formatAuditVal(c.newVal) }}</span>
                    <span v-if="c.oldVal == null && c.newVal != null" class="text-green-600">{{ formatAuditVal(c.newVal) }}</span>
                  </div>
                  <p v-if="entry.moreCount > 0" class="text-sm text-gray-500">+{{ entry.moreCount }} more field(s) changed</p>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Change Details modal (overlay on top of Edit History) -->
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
          <div class="px-6 py-4 space-y-4">
            <dl class="grid grid-cols-1 gap-2 text-sm">
              <div><dt class="font-medium text-gray-500">Modified By</dt><dd class="text-gray-900">{{ selectedEntry.user_name }}</dd></div>
              <div v-if="selectedEntry.user_role"><dt class="font-medium text-gray-500">Role</dt><dd class="text-gray-900">{{ selectedEntry.user_role }}</dd></div>
              <div><dt class="font-medium text-gray-500">Date & Time</dt><dd class="text-gray-900">{{ selectedEntry.formatted_at }}</dd></div>
              <div><dt class="font-medium text-gray-500">IP Address</dt><dd class="text-gray-900">—</dd></div>
            </dl>
            <div>
              <h4 class="mb-2 font-medium text-gray-900">Fields Changed</h4>
              <div class="space-y-4">
                <div v-for="c in selectedEntry.changes" :key="c.field" class="text-sm">
                  <p class="mb-1.5 font-medium text-gray-700">{{ c.field }}</p>
                  <div class="grid grid-cols-2 gap-2">
                    <div class="rounded bg-red-50 px-3 py-2">
                      <p class="text-xs font-medium text-gray-600">Before</p>
                      <p class="text-gray-900">{{ formatAuditVal(c.oldVal) }}</p>
                    </div>
                    <div class="rounded bg-green-50 px-3 py-2">
                      <p class="text-xs font-medium text-gray-600">After</p>
                      <p class="text-gray-900">{{ formatAuditVal(c.newVal) }}</p>
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
