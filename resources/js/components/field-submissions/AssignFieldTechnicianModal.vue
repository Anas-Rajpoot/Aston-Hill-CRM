<script setup>
/**
 * Assign Field Agent modal – single row or bulk. Dropdown shows users with field_agent role only.
 */
import { ref, computed, watch } from 'vue'

const props = defineProps({
  visible: { type: Boolean, default: false },
  submission: { type: Object, default: null },
  /** For bulk assign: array of submission IDs. When set, modal assigns one agent to all. */
  bulkSubmissionIds: { type: Array, default: () => [] },
  /** Users with field_agent role only (from team options). */
  fieldTechnicians: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'assign'])

const selectedId = ref(null)
const saving = ref(false)

const isBulk = computed(() => Array.isArray(props.bulkSubmissionIds) && props.bulkSubmissionIds.length > 0)

watch(
  () => [props.visible, props.submission, props.bulkSubmissionIds],
  ([vis]) => {
    if (vis) {
      selectedId.value = null
    }
  }
)

function close() {
  emit('close')
}

function assign() {
  const id = selectedId.value != null ? Number(selectedId.value) : null
  if (!id) return
  if (isBulk.value) {
    if (props.bulkSubmissionIds.length === 0) return
    saving.value = true
    emit('assign', { submissionIds: [...props.bulkSubmissionIds], fieldExecutiveId: id })
  } else {
    if (!props.submission?.id) return
    saving.value = true
    emit('assign', { submissionId: props.submission.id, fieldExecutiveId: id })
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="visible"
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 py-8"
        @click.self="close"
      >
        <div
          class="w-full max-w-md max-h-[90vh] flex flex-col overflow-hidden rounded-lg bg-white shadow-xl"
          @click.stop
        >
          <div class="flex items-start justify-between border-b border-gray-200 px-5 py-4">
            <h3 class="text-lg font-semibold text-gray-900">
              {{ isBulk ? `Assign Field Agent (${bulkSubmissionIds.length} selected)` : 'Assign Field Agent' }}
            </h3>
            <button
              type="button"
              class="-m-1 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
              aria-label="Close"
              @click="close"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="space-y-4 px-5 py-4 overflow-y-auto flex-1 min-h-0">
            <!-- Company & Service: attractive info card (single assign) -->
            <div
              v-if="submission && !isBulk"
              class="assign-info-card rounded-xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white px-4 py-4 shadow-sm"
            >
              <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                  </svg>
                </div>
                <div class="min-w-0 flex-1 space-y-2">
                  <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700/80">Company</span>
                    <p class="mt-0.5 text-sm font-medium text-gray-900">{{ submission.company_name ?? '—' }}</p>
                  </div>
                  <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700/80">Service</span>
                    <p class="mt-0.5 text-sm font-medium text-gray-900">{{ submission.product ?? '—' }}</p>
                  </div>
                </div>
              </div>
            </div>
            <div v-if="isBulk" class="rounded-xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white px-4 py-3 text-sm text-gray-700 shadow-sm">
              <p class="font-medium">Assign one field agent to <span class="text-emerald-700 font-semibold">{{ bulkSubmissionIds.length }}</span> submission(s).</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">
                Select Field Agent <span class="text-red-500">*</span>
              </label>
              <select
                v-model="selectedId"
                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20"
              >
                <option :value="null">-- Select Field Agent --</option>
                <option
                  v-for="tech in fieldTechnicians"
                  :key="tech.id"
                  :value="tech.id"
                >
                  {{ tech.name }}
                </option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3">
            <button
              type="button"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              @click="close"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
              :disabled="!selectedId || saving"
              @click="assign"
            >
              Assign Field Agent
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
