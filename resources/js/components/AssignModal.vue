<script setup>
/**
 * Generic Assign Modal – reusable across all 4 submission types.
 *
 * Props:
 *   - visible: Boolean
 *   - row: Object (single submission to assign, or null for bulk)
 *   - bulkIds: Array of IDs for bulk assignment
 *   - title: String (e.g. "Assign to Back Office Executive")
 *   - bulkTitle: String (e.g. "Assign {count} submission(s) to Back Office")
 *   - selectLabel: String (e.g. "Select Back Office Executive")
 *   - loadOptions: async Function → returns array of { id, name }
 *   - onAssignSingle: async Function(row, selectedId, notes)
 *   - onAssignBulk: async Function(ids, selectedId)
 */
import { ref, computed, watch } from 'vue'

const props = defineProps({
  visible: { type: Boolean, default: false },
  row: { type: Object, default: null },
  bulkIds: { type: Array, default: () => [] },
  title: { type: String, default: 'Assign Submission' },
  bulkTitle: { type: String, default: '' },
  selectLabel: { type: String, default: 'Select Assignee' },
  loadOptions: { type: Function, required: true },
  onAssignSingle: { type: Function, default: null },
  onAssignBulk: { type: Function, default: null },
})

const emit = defineEmits(['close', 'saved'])

const loading = ref(false)
const saving = ref(false)
const options = ref([])
const selectedId = ref(null)
const notes = ref('')
const error = ref(null)

const isBulk = computed(() => Array.isArray(props.bulkIds) && props.bulkIds.length > 0)

const modalTitle = computed(() => {
  if (isBulk.value) {
    return props.bulkTitle
      ? props.bulkTitle.replace('{count}', props.bulkIds.length)
      : `Assign ${props.bulkIds.length} submission(s)`
  }
  return props.title
})

watch(
  () => [props.visible, props.row, props.bulkIds],
  async ([visible]) => {
    if (!visible) {
      selectedId.value = null
      notes.value = ''
      error.value = null
      return
    }
    loading.value = true
    error.value = null
    try {
      options.value = await props.loadOptions()
      selectedId.value = null
      notes.value = ''
    } catch {
      options.value = []
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
  if (selectedId.value == null || selectedId.value === '') {
    error.value = `Please select an assignee.`
    return
  }
  saving.value = true
  error.value = null
  try {
    if (isBulk.value && props.onAssignBulk) {
      await props.onAssignBulk(props.bulkIds, Number(selectedId.value))
    } else if (props.onAssignSingle) {
      await props.onAssignSingle(props.row, Number(selectedId.value), notes.value?.trim() || '')
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
      class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-500/50 p-4"
      role="dialog"
      aria-modal="true"
      @click.self="close"
    >
      <div class="w-full max-w-md max-h-[90vh] flex flex-col rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
          <h2 class="text-lg font-semibold text-gray-900">{{ modalTitle }}</h2>
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
          <div class="space-y-4 px-6 py-4 overflow-y-auto flex-1 min-h-0">
            <!-- Single submission details -->
            <div v-if="!isBulk && row" class="rounded-lg border border-gray-200 bg-gray-50/80 p-4">
              <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Submission Details</p>
              <dl class="grid grid-cols-1 gap-3">
                <div v-if="row.account_number">
                  <dt class="text-xs font-medium text-gray-500">Account</dt>
                  <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ displayVal(row.account_number) }}</dd>
                </div>
                <div v-if="row.company_name">
                  <dt class="text-xs font-medium text-gray-500">Company</dt>
                  <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ displayVal(row.company_name) }}</dd>
                </div>
                <div v-if="row.product">
                  <dt class="text-xs font-medium text-gray-500">Product</dt>
                  <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ displayVal(row.product) }}</dd>
                </div>
                <div v-if="row.issue_category">
                  <dt class="text-xs font-medium text-gray-500">Issue Category</dt>
                  <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ displayVal(row.issue_category) }}</dd>
                </div>
                <div v-if="row.request_type">
                  <dt class="text-xs font-medium text-gray-500">Request Type</dt>
                  <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ displayVal(row.request_type) }}</dd>
                </div>
              </dl>
            </div>

            <!-- Bulk info -->
            <div v-if="isBulk" class="rounded-lg border border-gray-200 bg-blue-50/80 p-4">
              <p class="text-sm font-medium text-gray-900">{{ bulkIds.length }} submission(s) selected for assignment.</p>
            </div>

            <!-- Select assignee -->
            <div>
              <label class="block text-sm font-medium text-gray-700">{{ selectLabel }}</label>
              <select
                v-model="selectedId"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
              >
                <option :value="null">Select...</option>
                <option v-for="opt in options" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
              </select>
            </div>

            <!-- Notes (single assign only) -->
            <div v-if="!isBulk">
              <label class="block text-sm font-medium text-gray-700">Assignment Notes (Optional)</label>
              <textarea
                v-model="notes"
                rows="3"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                placeholder="Add any notes..."
              />
            </div>

            <!-- Error -->
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
              {{ saving ? 'Assigning...' : (isBulk ? `Assign ${bulkIds.length} submission(s)` : 'Assign') }}
            </button>
          </div>
        </template>
      </div>
    </div>
  </Teleport>
</template>
