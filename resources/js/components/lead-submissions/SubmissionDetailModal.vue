<script setup>
/**
 * Submission Details modal – read-only view matching design.
 * Opens from eye icon on lead submissions listing.
 */
import { ref, watch } from 'vue'
import leadSubmissionsApi from '@/services/leadSubmissionsApi'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  visible: { type: Boolean, default: false },
  leadId: { type: Number, default: null },
})

const emit = defineEmits(['close', 'openEdit'])

const auth = useAuthStore()
const loading = ref(false)
const lead = ref(null)

/** Only superadmin or back_office can open Edit Submission from this view. */
const canEditBackOffice = ref(false)
function updateCanEdit() {
  const roles = auth.user?.roles ?? []
  if (!Array.isArray(roles)) {
    canEditBackOffice.value = false
    return
  }
  canEditBackOffice.value = roles.some((r) => {
    const name = typeof r === 'string' ? r : r?.name
    return name === 'superadmin' || name === 'backoffice' || name === 'back_office'
  })
}

watch(
  () => [props.visible, props.leadId],
  async ([visible, leadId]) => {
    updateCanEdit()
    const id = leadId != null ? Number(leadId) : null
    if (!visible || id == null) {
      lead.value = null
      return
    }
    loading.value = true
    lead.value = null
    try {
      const res = await leadSubmissionsApi.getLead(id)
      const data = res?.data ?? res
      lead.value = data
    } catch {
      lead.value = null
    } finally {
      loading.value = false
    }
  }
)

function formatSubmissionDate(d) {
  if (!d) return '—'
  const date = new Date(d)
  if (Number.isNaN(date.getTime())) return '—'
  const day = String(date.getDate()).padStart(2, '0')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  return `${day}/${months[date.getMonth()]}/${date.getFullYear()}`
}

/** Submission date: prefer submitted_at, fallback to created_at so drafts still show a date. */
function submissionDateDisplay(lead) {
  const d = lead?.submitted_at ?? lead?.created_at
  return formatSubmissionDate(d)
}

/** Type = status (Draft, Submitted, Approved, Rejected). */
function formatStatus(status) {
  if (status == null || status === '') return '—'
  const s = String(status)
  return s.charAt(0).toUpperCase() + s.slice(1).toLowerCase()
}

function formatMrc(val) {
  if (val == null || val === '') return '—'
  const num = Number(val)
  if (Number.isNaN(num)) return val
  return 'AED ' + num.toLocaleString()
}

function displayVal(val) {
  return val != null && val !== '' ? val : '—'
}

/** Service category: from category_name or category.name (listing-style). */
function categoryDisplay(lead) {
  return displayVal(lead?.category_name ?? lead?.category?.name)
}

/** Service type: from type_name or type.name (listing-style). */
function typeNameDisplay(lead) {
  return displayVal(lead?.type_name ?? lead?.type?.name)
}

function close() {
  emit('close')
}

function onEditSubmission() {
  if (canEditBackOffice.value && props.leadId) {
    emit('openEdit', props.leadId)
    close()
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="visible"
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 py-6"
        @click.self="close"
      >
        <div
          class="relative w-full max-w-3xl rounded-lg bg-white shadow-xl"
          @click.stop
        >
          <!-- Header -->
          <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">Submission Details</h2>
            <button
              type="button"
              class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
              aria-label="Close"
              @click="close"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="max-h-[calc(100vh-8rem)] overflow-y-auto px-6 py-4">
            <div v-if="loading" class="flex justify-center py-12">
              <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </div>

            <div v-else-if="!lead" class="py-8 text-center text-sm text-gray-500">
              Unable to load submission. You may not have permission to view it.
            </div>
            <template v-else>
              <!-- Basic Information -->
              <div class="mb-5">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">Basic Information</h3>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Account Number</label>
                    <div class="mt-0.5 rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-800">
                      {{ displayVal(lead.account_number) }}
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Company Name</label>
                    <div class="mt-0.5 rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-800">
                      {{ displayVal(lead.company_name) }}
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Submission Date</label>
                    <div class="mt-0.5 rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-800">
                      {{ submissionDateDisplay(lead) }}
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Type</label>
                    <div class="mt-0.5 rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-800">
                      {{ formatStatus(lead.status) }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Service Details -->
              <div class="mb-5">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">Service Details</h3>
                <div class="grid grid-cols-3 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Service Category</label>
                    <div class="mt-0.5 rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-800">
                      {{ categoryDisplay(lead) }}
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Service Type</label>
                    <div class="mt-0.5 rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-800">
                      {{ typeNameDisplay(lead) }}
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Product</label>
                    <div class="mt-0.5 rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-800">
                      {{ displayVal(lead.product) }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Commercial -->
              <div class="mb-5">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">Commercial</h3>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-gray-500">MRC</label>
                    <div class="mt-0.5 rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-800">
                      {{ formatMrc(lead.mrc_aed) }}
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Quantity</label>
                    <div class="mt-0.5 rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-800">
                      {{ displayVal(lead.quantity) }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Team & Status -->
              <div class="mb-5">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">Team & Status</h3>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Sales Agent</label>
                    <div class="mt-0.5 rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-800">
                      {{ displayVal(lead.sales_agent_name) }}
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Team Leader</label>
                    <div class="mt-0.5 rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-800">
                      {{ displayVal(lead.team_leader_name) }}
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Manager</label>
                    <div class="mt-0.5 rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-800">
                      {{ displayVal(lead.manager_name) }}
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500">Back Office</label>
                    <div class="mt-0.5 rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-800">
                      {{ displayVal(lead.executive_name) }}
                    </div>
                  </div>
                  <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-500">SLA Status</label>
                    <div class="mt-0.5 rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-800">
                      —
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </div>

          <!-- Footer -->
          <div class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              @click="close"
            >
              Close
            </button>
            <button
              v-if="canEditBackOffice && lead"
              type="button"
              class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
              @click="onEditSubmission"
            >
              Edit Submission
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
