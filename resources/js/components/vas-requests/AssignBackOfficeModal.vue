<script setup>
/**
 * Assign to Back Office Executive – single row or bulk. Same pattern as lead submission.
 */
import { ref, computed, watch } from 'vue'
import vasRequestsApi from '@/services/vasRequestsApi'

const props = defineProps({
  visible: { type: Boolean, default: false },
  /** Single row for one assign */
  vas: { type: Object, default: null },
  /** For bulk: array of VAS request IDs. Only unassigned rows get updated. */
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
    } catch {
      executives.value = []
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
</script>

<template>
  <Teleport to="body">
    <div
      v-if="visible"
      class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/50 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="vas-assign-modal-title"
      @click.self="close"
    >
      <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
          <h2 id="vas-assign-modal-title" class="text-lg font-semibold text-gray-900">
            {{ isBulk ? `Assign ${bulkVasIds.length} request(s) to Back Office` : 'Assign to Back Office Executive' }}
          </h2>
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

        <div v-if="loading" class="flex justify-center py-8">
          <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </div>

        <template v-else>
          <div class="space-y-4 px-6 py-4">
            <div v-if="!isBulk" class="rounded-lg border border-gray-200 bg-gray-50/80 p-4">
              <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Request Details</p>
              <dl class="space-y-2">
                <div class="flex flex-wrap items-baseline gap-2">
                  <dt class="text-xs font-medium text-gray-500">Account</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ displayVal(vas?.account_number) }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline gap-2">
                  <dt class="text-xs font-medium text-gray-500">Company</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ displayVal(vas?.company_name) }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline gap-2">
                  <dt class="text-xs font-medium text-gray-500">Request Type</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ displayVal(vas?.request_type) }}</dd>
                </div>
              </dl>
            </div>
            <div v-else class="rounded-lg border border-gray-200 bg-blue-50/80 p-4">
              <p class="text-sm font-medium text-gray-900">{{ bulkVasIds.length }} request(s) selected. Only rows without an assigned back office will be updated.</p>
            </div>

            <div>
              <label for="vas-assign-executive" class="block text-sm font-medium text-gray-700">Select Back Office Executive</label>
              <select
                id="vas-assign-executive"
                v-model="executiveId"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              >
                <option :value="null">Select an executive...</option>
                <option v-for="ex in executives" :key="ex.id" :value="ex.id">{{ ex.name }}</option>
              </select>
            </div>

            <div v-if="error" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ error }}
            </div>
          </div>

          <div class="flex justify-end gap-2 border-t border-gray-200 px-6 py-4">
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              @click="close"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
              :disabled="saving"
              @click="assign"
            >
              {{ saving ? 'Assigning...' : (isBulk ? `Assign ${bulkVasIds.length} request(s)` : 'Assign') }}
            </button>
          </div>
        </template>
      </div>
    </div>
  </Teleport>
</template>
