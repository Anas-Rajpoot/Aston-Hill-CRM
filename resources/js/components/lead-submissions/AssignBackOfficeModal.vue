<script setup>
/**
 * Assign to Back Office Executive – modal when clicking "Unassigned" on listing.
 * Only for super admin or back office role. Submission details + select executive + optional notes.
 */
import { ref, computed, watch } from 'vue'
import leadSubmissionsApi from '@/services/leadSubmissionsApi'

const props = defineProps({
  visible: { type: Boolean, default: false },
  /** Row from table: { id, account_number, company_name, product, ... } – single assign */
  lead: { type: Object, default: null },
  /** For bulk assign: array of lead IDs. When set, modal assigns one executive to all. */
  bulkLeadIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'saved'])

const loading = ref(false)
const saving = ref(false)
const executives = ref([])
const executiveId = ref(null)
const assignmentNotes = ref('')
const error = ref(null)

const isBulk = computed(() => Array.isArray(props.bulkLeadIds) && props.bulkLeadIds.length > 0)

watch(
  () => [props.visible, props.lead, props.bulkLeadIds],
  async ([visible, lead, bulkIds]) => {
    if (!visible) {
      executiveId.value = null
      assignmentNotes.value = ''
      error.value = null
      return
    }
    const hasWork = lead?.id || (Array.isArray(bulkIds) && bulkIds.length > 0)
    if (!hasWork) return
    loading.value = true
    error.value = null
    try {
      const res = await leadSubmissionsApi.getBackOfficeOptions()
      executives.value = res?.executives ?? []
      executiveId.value = null
      assignmentNotes.value = ''
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
      await leadSubmissionsApi.bulkAssign(props.bulkLeadIds, {
        executive_id: Number(executiveId.value),
      })
    } else {
      const id = props.lead?.id
      if (!id) return
      await leadSubmissionsApi.updateBackOffice(id, {
        executive_id: executiveId.value ? Number(executiveId.value) : null,
        back_office_notes: assignmentNotes.value?.trim() || undefined,
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
      aria-labelledby="assign-modal-title"
      @click.self="close"
    >
      <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
          <h2 id="assign-modal-title" class="text-lg font-semibold text-gray-900">
            {{ isBulk ? `Assign ${bulkLeadIds.length} submission(s) to Back Office` : 'Assign to Back Office Executive' }}
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
              <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Submission Details</p>
              <dl class="grid grid-cols-1 gap-3 sm:grid-cols-1">
                <div>
                  <dt class="text-xs font-medium text-gray-500">Account</dt>
                  <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ displayVal(lead?.account_number) }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Company</dt>
                  <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ displayVal(lead?.company_name) }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Product</dt>
                  <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ displayVal(lead?.product) }}</dd>
                </div>
              </dl>
            </div>
            <div v-else class="rounded-lg border border-gray-200 bg-blue-50/80 p-4">
              <p class="text-sm font-medium text-gray-900">{{ bulkLeadIds.length }} submission(s) selected for assignment.</p>
            </div>

            <div>
              <label for="assign-executive" class="block text-sm font-medium text-gray-700">Select Back Office Executive</label>
              <select
                id="assign-executive"
                v-model="executiveId"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
              >
                <option :value="null">Select an executive...</option>
                <option v-for="ex in executives" :key="ex.id" :value="ex.id">{{ ex.name }}</option>
              </select>
            </div>

            <div>
              <label for="assign-notes" class="block text-sm font-medium text-gray-700">Assignment Notes (Optional)</label>
              <textarea
                id="assign-notes"
                v-model="assignmentNotes"
                rows="3"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                placeholder="Add any notes for the assigned executive..."
              />
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
              {{ saving ? 'Assigning...' : (isBulk ? `Assign ${bulkLeadIds.length} submission(s)` : 'Assign Submission') }}
            </button>
          </div>
        </template>
      </div>
    </div>
  </Teleport>
</template>
