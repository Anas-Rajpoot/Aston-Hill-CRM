<script setup>
/**
 * Edit Extension modal – design per image: Extension (read-only), Landline, Gateway, Username, Password, Status, Assign To Employee (optional), Comment. Cancel + Update Extension (teal).
 */
import { ref, computed, watch } from 'vue'
import extensionsApi from '@/services/extensionsApi'

const props = defineProps({
  visible: { type: Boolean, default: false },
  extensionId: { type: [Number, String], default: null },
  gateways: { type: Array, default: () => [] },
  statuses: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'updated'])

const form = ref({
  extension: '',
  landline_number: '',
  gateway: '',
  username: '',
  password: '',
  status: 'active',
  assigned_to: null,
  comment: '',
})
const gatewaysList = ref([])
const statusesList = ref([])
// Only Active and Inactive in dropdown; exclude "Not Created"
const statusOptions = computed(() =>
  (statusesList.value || []).filter((s) => s.value !== 'not_created')
)
const assignableEmployees = ref([])
const loading = ref(false)
const loadingEmployees = ref(false)
const submitting = ref(false)
const error = ref(null)

watch(
  () => [props.visible, props.extensionId],
  async ([visible, id]) => {
    if (visible && id) {
      error.value = null
      gatewaysList.value = props.gateways?.length ? props.gateways : []
      statusesList.value = props.statuses?.length ? props.statuses : []
      if (!props.gateways?.length || !props.statuses?.length) {
        try {
          const { data } = await extensionsApi.filters()
          if (!gatewaysList.value.length) gatewaysList.value = data.gateways ?? []
          if (!statusesList.value.length) statusesList.value = data.statuses ?? []
        } catch {
          //
        }
      }
      loadAssignableEmployees()
      await loadExtension(id)
    }
  }
)

async function loadExtension(id) {
  loading.value = true
  try {
    const { data } = await extensionsApi.show(id)
    const ext = data.data
    if (ext) {
      const assignedTo = ext.assigned_to
      form.value = {
        extension: String(ext.extension ?? ''),
        landline_number: String(ext.landline_number ?? ''),
        gateway: String(ext.gateway ?? ''),
        username: String(ext.username ?? ''),
        password: '', // never pre-fill; user enters to change
        status: ext.status === 'inactive' ? 'inactive' : (ext.status ?? 'active'),
        assigned_to: assignedTo != null && assignedTo !== '' ? Number(assignedTo) : null,
        comment: String(ext.comment ?? ''),
      }
    }
  } catch {
    error.value = 'Failed to load extension.'
  } finally {
    loading.value = false
  }
}

async function loadAssignableEmployees() {
  loadingEmployees.value = true
  try {
    const { data } = await extensionsApi.getAssignableEmployees()
    assignableEmployees.value = data.data ?? []
  } catch {
    assignableEmployees.value = []
  } finally {
    loadingEmployees.value = false
  }
}

function close() {
  emit('close')
}

async function submit() {
  error.value = null
  if (!form.value.extension?.trim()) {
    error.value = 'Extension is required.'
    return
  }
  if (!form.value.landline_number?.trim()) {
    error.value = 'Landline Number is required.'
    return
  }
  if (!form.value.gateway?.trim()) {
    error.value = 'Gateway is required.'
    return
  }
  if (!form.value.username?.trim()) {
    error.value = 'Username is required.'
    return
  }
  if (!form.value.status?.trim()) {
    error.value = 'Status is required.'
    return
  }
  // Password optional on edit (leave blank to keep existing)
  if (!props.extensionId) return

  submitting.value = true
  try {
    const payload = {
      extension: form.value.extension.trim(),
      landline_number: form.value.landline_number.trim(),
      gateway: form.value.gateway.trim() || null,
      username: form.value.username.trim(),
      status: form.value.status,
      assigned_to: form.value.assigned_to || null,
      comment: form.value.comment?.trim() || null,
    }
    if (form.value.password?.trim()) payload.password = form.value.password
    await extensionsApi.update(props.extensionId, payload)
    emit('updated')
    close()
  } catch (e) {
    const msg = e?.response?.data?.message || e?.response?.data?.errors
    error.value = typeof msg === 'string' ? msg : (msg && Object.values(msg).flat().length ? Object.values(msg).flat().join(' ') : 'Failed to update extension.')
  } finally {
    submitting.value = false
  }
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
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-500/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="edit-extension-title"
        @click.self="close"
      >
        <div class="my-8 w-full max-w-lg max-h-[90vh] flex flex-col rounded-xl bg-white shadow-xl overflow-hidden">
          <div class="flex flex-shrink-0 items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 id="edit-extension-title" class="text-lg font-semibold text-gray-900">Edit Extension</h2>
            <button
              type="button"
              class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
              aria-label="Close"
              @click="close"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <form v-if="!loading" class="flex-1 min-h-0 overflow-y-auto px-6 py-5 space-y-4" @submit.prevent="submit">
            <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ error }}
            </p>

            <div>
              <label for="edit-ext-extension" class="mb-1 block text-sm font-medium text-gray-700">
                Extension <span class="text-red-500">*</span>
              </label>
              <input
                id="edit-ext-extension"
                v-model="form.extension"
                type="text"
                readonly
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700"
              />
            </div>

            <div>
              <label for="edit-ext-landline" class="mb-1 block text-sm font-medium text-gray-700">
                Landline Number <span class="text-red-500">*</span>
              </label>
              <input
                id="edit-ext-landline"
                v-model="form.landline_number"
                type="text"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                placeholder="e.g., +971-4-123-1001"
              />
            </div>

            <div>
              <label for="edit-ext-gateway" class="mb-1 block text-sm font-medium text-gray-700">
                Gateway <span class="text-red-500">*</span>
              </label>
              <select
                id="edit-ext-gateway"
                v-model="form.gateway"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
              >
                <option value="">Select Gateway</option>
                <option v-for="opt in gatewaysList" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <div>
              <label for="edit-ext-username" class="mb-1 block text-sm font-medium text-gray-700">
                Username <span class="text-red-500">*</span>
              </label>
              <input
                id="edit-ext-username"
                v-model="form.username"
                type="text"
                autocomplete="off"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                placeholder="e.g., ext1001"
              />
            </div>

            <div>
              <label for="edit-ext-password" class="mb-1 block text-sm font-medium text-gray-700">
                Password <span class="text-red-500">*</span>
              </label>
              <input
                id="edit-ext-password"
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                placeholder="Leave blank to keep current"
              />
            </div>

            <div>
              <label for="edit-ext-status" class="mb-1 block text-sm font-medium text-gray-700">
                Status <span class="text-red-500">*</span>
              </label>
              <select
                id="edit-ext-status"
                v-model="form.status"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
              >
                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <div>
              <label for="edit-ext-assigned" class="mb-1 block text-sm font-medium text-gray-700">
                Assign To Employee <span class="text-gray-400 font-normal">(Optional)</span>
              </label>
              <select
                id="edit-ext-assigned"
                v-model="form.assigned_to"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
              >
                <option :value="null">Not Assigned</option>
                <option v-for="emp in assignableEmployees" :key="emp.id" :value="emp.id">
                  {{ emp.name || emp.label }}
                </option>
              </select>
              <p class="mt-1 text-xs text-gray-500">Only active employees are shown</p>
            </div>

            <div>
              <label for="edit-ext-comment" class="mb-1 block text-sm font-medium text-gray-700">
                Comment
              </label>
              <textarea
                id="edit-ext-comment"
                v-model="form.comment"
                rows="3"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                placeholder="Add any notes or comments..."
              />
            </div>

            <div class="flex justify-end gap-3 border-t border-gray-200 pt-4">
              <button
                type="button"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                @click="close"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-50"
                :disabled="submitting"
              >
                {{ submitting ? 'Updating...' : 'Update Extension' }}
              </button>
            </div>
          </form>

          <div v-else class="flex items-center justify-center px-6 py-12">
            <svg class="h-8 w-8 animate-spin text-teal-600" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
