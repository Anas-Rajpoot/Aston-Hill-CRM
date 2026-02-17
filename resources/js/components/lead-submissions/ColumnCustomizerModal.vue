<script setup>
/**
 * Column customization modal – checkboxes per column, Check All / Uncheck All.
 * Shows only columns user has permission to view (from API).
 * Requires at least 4 columns to be selected before saving.
 */
import { ref, watch } from 'vue'

const MIN_COLUMNS = 4

const props = defineProps({
  visible: { type: Boolean, default: false },
  allColumns: { type: Array, default: () => [] },
  visibleColumns: { type: Array, default: () => [] },
  /** Accepted but not used (kept for backward compat). */
  defaultColumns: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:visible', 'save'])

const localSelected = ref([...props.visibleColumns])
const errorMessage = ref('')

watch(
  () => [props.visible, props.visibleColumns],
  ([vis, cols]) => {
    if (vis) {
      localSelected.value = [...(cols || [])]
      errorMessage.value = ''
    }
  }
)

function toggle(col) {
  errorMessage.value = ''
  const key = col.key
  if (localSelected.value.includes(key)) {
    localSelected.value = localSelected.value.filter((c) => c !== key)
  } else {
    localSelected.value = [...localSelected.value, key]
  }
}

function checkAll() {
  errorMessage.value = ''
  localSelected.value = props.allColumns.map((c) => c.key)
}

function uncheckAll() {
  errorMessage.value = ''
  localSelected.value = []
}

function save() {
  if (localSelected.value.length < MIN_COLUMNS) {
    errorMessage.value = 'Please select at least 4 columns.'
    return
  }
  errorMessage.value = ''
  emit('save', [...localSelected.value])
  emit('update:visible', false)
}

function close() {
  errorMessage.value = ''
  emit('update:visible', false)
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
          class="flex max-h-[85vh] min-h-0 w-full max-w-sm flex-col overflow-hidden rounded-lg bg-white shadow-xl"
          @click.stop
        >
          <div class="flex flex-shrink-0 items-start justify-between border-b px-4 py-3">
            <div>
              <h3 class="text-base font-semibold">Customize Columns</h3>
              <p class="text-xs text-gray-500">Select columns to display in the table.</p>
            </div>
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
          <div class="min-h-0 flex-1 overflow-y-auto p-4">
            <div class="mb-4 flex flex-wrap gap-2">
              <button
                type="button"
                class="rounded border border-gray-300 px-3 py-1 text-sm hover:bg-gray-50"
                @click="checkAll"
              >
                Check All
              </button>
              <button
                type="button"
                class="rounded border border-gray-300 px-3 py-1 text-sm hover:bg-gray-50"
                @click="uncheckAll"
              >
                Uncheck All
              </button>
            </div>
            <div class="space-y-2">
              <label
                v-for="col in allColumns"
                :key="col.key"
                class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 hover:bg-gray-50"
              >
                <input
                  type="checkbox"
                  :checked="localSelected.includes(col.key)"
                  class="rounded border-gray-300"
                  @change="toggle(col)"
                />
                <span class="text-sm">{{ col.label }}</span>
              </label>
            </div>
            <p v-if="errorMessage" class="mt-3 text-sm text-red-600">{{ errorMessage }}</p>
          </div>
          <div class="flex flex-shrink-0 justify-end gap-2 border-t border-gray-200 bg-gray-50 px-4 py-3">
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50"
              @click="close"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded bg-green-600 px-3 py-1.5 text-sm text-white hover:bg-green-700"
              @click="save"
            >
              Save
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
