<script setup>
/**
 * Assign to Back Office Executive – single row or bulk for VAS requests.
 * Fire-and-forget: dispatches to queue, closes immediately, user keeps working.
 */
import { ref, computed, watch } from 'vue'
import vasRequestsApi from '@/services/vasRequestsApi'

const props = defineProps({
  visible: { type: Boolean, default: false },
  vas: { type: Object, default: null },
  bulkVasIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'saved'])

const loading = ref(false)
const saving = ref(false)
const executives = ref([])
const executiveId = ref(null)
const error = ref(null)

const isBulk = computed(() => Array.isArray(props.bulkVasIds) && props.bulkVasIds.length > 0)

watch(
  () => [props.visible, props.vas, props.bulkVasIds],
  async ([visible, vas, bulkIds]) => {
    if (!visible) {
      executiveId.value = null
      error.value = null
      return
    }
    const hasWork = vas?.id || (Array.isArray(bulkIds) && bulkIds.length > 0)
    if (!hasWork) return
    loading.value = true
    error.value = null
    try {
      const res = await vasRequestsApi.getBackOfficeOptions()
      executives.value = res?.executives ?? []
      executiveId.value = null
    } catch (err) {
      executives.value = []
      const status = err?.response?.status
      if (status === 403) {
        error.value = 'You do not have permission to assign requests. Please contact your administrator.'
      } else {
        error.value = 'Failed to load executive options. Please try again.'
      }
    } finally {
      loading.value = false
    }
  },
  { immediate: true }
)

function close() {
  emit('close')
}

async function assign() {
  if (executiveId.value == null || executiveId.value === '') {
    error.value = 'Please select a back office executive.'
    return
  }
  saving.value = true
  error.value = null
  try {
    if (isBulk.value) {
      await vasRequestsApi.bulkAssign(props.bulkVasIds, {
        executive_id: Number(executiveId.value),
      })
    } else {
      const id = props.vas?.id
      if (!id) return
      await vasRequestsApi.assignBackOffice(id, {
        executive_id: Number(executiveId.value),
      })
    }
    emit('saved')
    close()
  } catch (e) {
    error.value = e?.response?.data?.message ?? 'Failed to assign.'
  } finally {
    saving.value = false
  }
}

function displayVal(val) {
  return val != null && val !== '' ? val : '—'
}

function formatDate(d) {
  if (!d) return '—'
  const dt = new Date(d)
  const day = String(dt.getDate()).padStart(2, '0')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  return `${day}-${months[dt.getMonth()]}-${dt.getFullYear()}`
}

function statusBadgeClass(status) {
  const s = (status ?? '').toLowerCase()
  if (s === 'approved' || s === 'completed') return 'bg-green-100 text-green-800'
  if (s === 'pending' || s === 'in_progress' || s === 'in progress') return 'bg-amber-100 text-amber-800'
  if (s === 'rejected' || s === 'cancelled') return 'bg-red-100 text-red-800'
  if (s === 'draft') return 'bg-gray-100 text-gray-600'
  return 'bg-blue-100 text-blue-800'
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="visible"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/30 backdrop-blur-sm p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="vas-assign-modal-title"
        @click.self="close"
      >
        <div class="w-full max-w-lg rounded-xl bg-white shadow-xl border border-gray-200 max-h-[90vh] flex flex-col overflow-hidden">
          <!-- Header -->
          <div class="flex items-center gap-3 border-b border-gray-200 px-6 py-4 bg-gray-50">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100">
              <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>
            <div class="flex-1">
              <h2 id="vas-assign-modal-title" class="text-lg font-semibold text-gray-900">
                {{ isBulk ? 'Bulk Assign to Back Office' : 'Assign to Back Office Executive' }}
              </h2>
              <p class="text-xs text-gray-500 mt-0.5">{{ isBulk ? `${bulkVasIds.length} request(s) selected` : 'Assign an executive to process this VAS request' }}</p>
            </div>
            <button
              type="button"
              class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
              aria-label="Close"
              @click="close"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Loading -->
          <div v-if="loading" class="flex flex-col items-center justify-center py-12">
            <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p class="mt-3 text-sm text-gray-500">Loading options...</p>
          </div>

          <template v-else>
            <div class="space-y-5 px-6 py-5 overflow-y-auto flex-1 min-h-0">
              <!-- Request Details (single assign) -->
              <div v-if="!isBulk && vas" class="rounded-lg border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-4 py-2.5 border-b border-gray-200">
                  <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    Request Details
                  </p>
                </div>
                <div class="p-4">
                  <dl class="grid grid-cols-2 gap-x-6 gap-y-3">
                    <div>
                      <dt class="text-xs font-medium text-gray-500">Account Number</dt>
                      <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ displayVal(vas?.account_number) }}</dd>
                    </div>
                    <div>
                      <dt class="text-xs font-medium text-gray-500">Company Name</dt>
                      <dd class="mt-0.5 text-sm font-semibold text-gray-900" :title="vas?.company_name">{{ displayVal(vas?.company_name) }}</dd>
                    </div>
                    <div>
                      <dt class="text-xs font-medium text-gray-500">Request Type</dt>
                      <dd class="mt-0.5 text-sm text-gray-900">{{ displayVal(vas?.request_type) }}</dd>
                    </div>
                    <div>
                      <dt class="text-xs font-medium text-gray-500">Service Category</dt>
                      <dd class="mt-0.5 text-sm text-gray-900">{{ displayVal(vas?.service_category) }}</dd>
                    </div>
                    <div>
                      <dt class="text-xs font-medium text-gray-500">Status</dt>
                      <dd class="mt-1">
                        <span v-if="vas?.status" class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass(vas?.status)">{{ vas?.status }}</span>
                        <span v-else class="text-sm text-gray-400">—</span>
                      </dd>
                    </div>
                    <div>
                      <dt class="text-xs font-medium text-gray-500">Submitted Date</dt>
                      <dd class="mt-0.5 text-sm text-gray-900">{{ formatDate(vas?.submitted_at) }}</dd>
                    </div>
                    <div v-if="vas?.sales_agent">
                      <dt class="text-xs font-medium text-gray-500">Sales Agent</dt>
                      <dd class="mt-0.5 text-sm text-gray-900">{{ vas.sales_agent }}</dd>
                    </div>
                    <div v-if="vas?.executive">
                      <dt class="text-xs font-medium text-gray-500">Current Executive</dt>
                      <dd class="mt-0.5 text-sm text-gray-900">{{ vas.executive }}</dd>
                    </div>
                  </dl>
                </div>
              </div>

              <!-- Bulk assign info -->
              <div v-else-if="isBulk" class="rounded-lg border border-blue-200 bg-blue-50 p-4 flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 shrink-0">
                  <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                </div>
                <div>
                  <p class="text-sm font-semibold text-blue-900">{{ bulkVasIds.length }} request(s) selected</p>
                  <p class="text-xs text-blue-700 mt-0.5">All selected requests will be assigned to the same executive.</p>
                </div>
              </div>

              <!-- Select Executive -->
              <div>
                <label for="vas-assign-executive" class="block text-sm font-medium text-gray-700 mb-1.5">
                  Select Back Office Executive <span class="text-red-500">*</span>
                </label>
                <select
                  id="vas-assign-executive"
                  v-model="executiveId"
                  class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                >
                  <option :value="null">Select an executive...</option>
                  <option v-for="ex in executives" :key="ex.id" :value="ex.id">{{ ex.name }}</option>
                </select>
                <p v-if="!executives.length && !loading" class="mt-1.5 text-xs text-amber-600 flex items-center gap-1">
                  <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                  No executives found. Ensure users with the "Back Office" role exist and are active.
                </p>
              </div>

              <!-- Error -->
              <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 flex items-center gap-2">
                <svg class="h-4 w-4 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                {{ error }}
              </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
              <button
                type="button"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300"
                @click="close"
              >
                Cancel
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-60"
                :disabled="saving || executiveId == null"
                @click="assign"
              >
                <svg v-if="saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ saving ? 'Assigning...' : (isBulk ? `Assign ${bulkVasIds.length} Request(s)` : 'Assign Request') }}
              </button>
            </div>
          </template>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
