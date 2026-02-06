<script setup>
/**
 * Assign Field Technician modal – shown when clicking "Unassigned" on field submissions listing.
 * Company + Service context, dropdown of users with field_agent role, Assign Technician on save.
 */
import { ref, watch } from 'vue'

const props = defineProps({
  visible: { type: Boolean, default: false },
  submission: { type: Object, default: null },
  fieldTechnicians: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'assign'])

const selectedId = ref(null)
const saving = ref(false)

watch(
  () => [props.visible, props.submission],
  ([vis, sub]) => {
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
  if (!id || !props.submission?.id) return
  saving.value = true
  emit('assign', { submissionId: props.submission.id, fieldExecutiveId: id })
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
          class="w-full max-w-md overflow-hidden rounded-lg bg-white shadow-xl"
          @click.stop
        >
          <div class="flex items-start justify-between border-b border-gray-200 px-5 py-4">
            <h3 class="text-lg font-semibold text-gray-900">Assign Field Technician</h3>
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
          <div class="space-y-4 px-5 py-4">
            <div v-if="submission" class="rounded-lg bg-gray-100 px-4 py-3 text-sm text-gray-700">
              <p><strong>Company:</strong> {{ submission.company_name ?? '—' }}</p>
              <p class="mt-1"><strong>Service:</strong> {{ submission.product ?? '—' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">
                Select Field Technician <span class="text-red-500">*</span>
              </label>
              <select
                v-model="selectedId"
                class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              >
                <option :value="null">-- Select Technician --</option>
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
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              @click="close"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
              :disabled="!selectedId || saving"
              @click="assign"
            >
              Assign Technician
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
