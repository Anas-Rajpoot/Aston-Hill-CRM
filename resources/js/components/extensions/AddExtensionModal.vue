<script setup>
/**
 * Add New Extension modal – form matches design: Extension, Landline, Gateway, Username, Password, Status, Assign To Employee (optional), Comment.
 */
import { ref, computed, watch } from 'vue'
import extensionsApi from '@/services/extensionsApi'

const props = defineProps({
  visible: { type: Boolean, default: false },
  gateways: { type: Array, default: () => [] },
  statuses: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'created'])

/** Dummy gateway options when none provided from API/parent */
const DEFAULT_GATEWAY_OPTIONS = [
  { value: 'gateway_1', label: 'Gateway 1' },
  { value: 'gateway_2', label: 'Gateway 2' },
  { value: 'gateway_3', label: 'Gateway 3' },
  { value: 'primary_gateway', label: 'Primary Gateway' },
  { value: 'backup_gateway', label: 'Backup Gateway' },
]

const gatewayOptions = computed(() => {
  const list = props.gateways?.length ? props.gateways : []
  return list.length > 0 ? list : DEFAULT_GATEWAY_OPTIONS
})

// Only Active and Inactive in dropdown; exclude "Not Created"
const statusOptions = computed(() => {
  const list = props.statuses?.length ? props.statuses : [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'InActive' },
  ]
  return list.filter((s) => s.value !== 'not_created')
})

const form = ref({
  extension: '',
  landline_number: '',
  gateway: '',
  username: '',
  password: '',
  status: 'active',
  assigned_to: '',
  comment: '',
})
const assignableEmployees = ref([])
const loadingEmployees = ref(false)
const submitting = ref(false)
const error = ref(null)
const fieldErrors = ref({
  extension: '',
  landline_number: '',
  gateway: '',
  username: '',
  password: '',
  status: '',
})

function resetFieldErrors() {
  fieldErrors.value = {
    extension: '',
    landline_number: '',
    gateway: '',
    username: '',
    password: '',
    status: '',
  }
}

function inputClass(field) {
  return [
    'w-full rounded-lg border px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500',
    fieldErrors.value[field] ? 'border-red-500' : 'border-gray-300',
  ]
}

function validateLandlineNumber(value) {
  if (!value) return 'Landline Number is required.'
  if (/\s/.test(value)) return 'Must not contain spaces.'
  if (!/^\d+$/.test(value)) return 'Must contain only digits.'
  if (!value.startsWith('971')) return 'Must start with 971.'
  if (value.length !== 12) return 'Must be exactly 12 digits.'
  return null
}

function onLandlineInput(event) {
  const raw = String(event?.target?.value ?? '').replace(/\D/g, '').slice(0, 12)
  form.value.landline_number = raw
  fieldErrors.value.landline_number = ''
  if (event?.target) event.target.value = raw
}

watch(() => props.visible, (visible) => {
  if (visible) {
    form.value = {
      extension: '',
      landline_number: '',
      gateway: '',
      username: '',
      password: '',
      status: 'active',
      assigned_to: '',
      comment: '',
    }
    error.value = null
    resetFieldErrors()
    loadAssignableEmployees()
  }
})

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
  resetFieldErrors()
  const errs = {}
  const extension = (form.value.extension || '').trim()
  const landline = (form.value.landline_number || '').trim()
  const gateway = (form.value.gateway || '').trim()
  const username = (form.value.username || '').trim()
  const password = form.value.password || ''
  const status = (form.value.status || '').trim()

  if (!extension) errs.extension = 'Extension is required.'
  const landlineErr = validateLandlineNumber(landline)
  if (landlineErr) errs.landline_number = landlineErr
  if (!gateway) errs.gateway = 'Gateway is required.'
  if (!username) errs.username = 'Username is required.'
  if (!password.trim()) errs.password = 'Password is required.'
  if (!status) errs.status = 'Status is required.'

  if (Object.keys(errs).length > 0) {
    fieldErrors.value = { ...fieldErrors.value, ...errs }
    error.value = 'Please fix the highlighted required fields.'
    return
  }

  submitting.value = true
  try {
    const payload = {
      extension,
      landline_number: landline,
      gateway: gateway || null,
      username,
      password: form.value.password,
      status,
      assigned_to: form.value.assigned_to || null,
      comment: form.value.comment?.trim() || null,
    }
    await extensionsApi.create(payload)
    emit('created')
    close()
  } catch (e) {
    const serverErrors = e?.response?.data?.errors
    if (serverErrors && typeof serverErrors === 'object') {
      fieldErrors.value = {
        ...fieldErrors.value,
        extension: Array.isArray(serverErrors.extension) ? serverErrors.extension[0] : (fieldErrors.value.extension || ''),
        landline_number: Array.isArray(serverErrors.landline_number) ? serverErrors.landline_number[0] : (fieldErrors.value.landline_number || ''),
        gateway: Array.isArray(serverErrors.gateway) ? serverErrors.gateway[0] : (fieldErrors.value.gateway || ''),
        username: Array.isArray(serverErrors.username) ? serverErrors.username[0] : (fieldErrors.value.username || ''),
        password: Array.isArray(serverErrors.password) ? serverErrors.password[0] : (fieldErrors.value.password || ''),
        status: Array.isArray(serverErrors.status) ? serverErrors.status[0] : (fieldErrors.value.status || ''),
      }
    }
    const msg = e?.response?.data?.message || serverErrors
    error.value = typeof msg === 'string' ? msg : (msg && Object.values(msg).flat().length ? Object.values(msg).flat().join(' ') : 'Failed to create extension.')
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
        aria-labelledby="add-extension-title"
        @click.self="close"
      >
        <div class="my-8 w-full max-w-lg max-h-[90vh] flex flex-col rounded-xl bg-white shadow-xl overflow-hidden">
          <div class="flex flex-shrink-0 items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 id="add-extension-title" class="text-lg font-semibold text-gray-900">Add New Extension</h2>
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

          <form class="flex-1 min-h-0 overflow-y-auto px-6 py-5 space-y-4" @submit.prevent="submit">
            <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ error }}
            </p>
            <p class="text-xs text-gray-500">Fields marked with <span class="text-red-500">*</span> are required.</p>

            <div>
              <label for="add-ext-extension" class="mb-1 block text-sm font-medium text-gray-700">
                Extension <span class="text-red-500">*</span>
              </label>
              <input
                id="add-ext-extension"
                v-model="form.extension"
                type="text"
                :class="inputClass('extension')"
                placeholder="e.g., 1001"
                @input="fieldErrors.extension = ''"
              />
              <p v-if="fieldErrors.extension" class="mt-1 text-xs text-red-600">{{ fieldErrors.extension }}</p>
            </div>

            <div>
              <label for="add-ext-landline" class="mb-1 block text-sm font-medium text-gray-700">
                Landline Number <span class="text-red-500">*</span>
              </label>
              <input
                id="add-ext-landline"
                v-model="form.landline_number"
                type="text"
                maxlength="12"
                :class="inputClass('landline_number')"
                placeholder="971XXXXXXXXX"
                @input="onLandlineInput"
              />
              <p v-if="fieldErrors.landline_number" class="mt-1 text-xs text-red-600">{{ fieldErrors.landline_number }}</p>
            </div>

            <div>
              <label for="add-ext-gateway" class="mb-1 block text-sm font-medium text-gray-700">
                Gateway <span class="text-red-500">*</span>
              </label>
              <select
                id="add-ext-gateway"
                v-model="form.gateway"
                :class="inputClass('gateway')"
                @change="fieldErrors.gateway = ''"
              >
                <option value="">Select Gateway</option>
                <option v-for="opt in gatewayOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
              <p v-if="fieldErrors.gateway" class="mt-1 text-xs text-red-600">{{ fieldErrors.gateway }}</p>
            </div>

            <div>
              <label for="add-ext-username" class="mb-1 block text-sm font-medium text-gray-700">
                Username <span class="text-red-500">*</span>
              </label>
              <input
                id="add-ext-username"
                v-model="form.username"
                type="text"
                autocomplete="off"
                :class="inputClass('username')"
                placeholder="e.g., ext1001"
                @input="fieldErrors.username = ''"
              />
              <p v-if="fieldErrors.username" class="mt-1 text-xs text-red-600">{{ fieldErrors.username }}</p>
            </div>

            <div>
              <label for="add-ext-password" class="mb-1 block text-sm font-medium text-gray-700">
                Password <span class="text-red-500">*</span>
              </label>
              <input
                id="add-ext-password"
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                :class="inputClass('password')"
                placeholder="Enter password"
                @input="fieldErrors.password = ''"
              />
              <p v-if="fieldErrors.password" class="mt-1 text-xs text-red-600">{{ fieldErrors.password }}</p>
            </div>

            <div>
              <label for="add-ext-status" class="mb-1 block text-sm font-medium text-gray-700">
                Status <span class="text-red-500">*</span>
              </label>
              <select
                id="add-ext-status"
                v-model="form.status"
                :class="inputClass('status')"
                @change="fieldErrors.status = ''"
              >
                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
              <p v-if="fieldErrors.status" class="mt-1 text-xs text-red-600">{{ fieldErrors.status }}</p>
            </div>

            <div>
              <label for="add-ext-assigned" class="mb-1 block text-sm font-medium text-gray-700">
                Assign To Employee <span class="text-gray-400 font-normal">(Optional)</span>
              </label>
              <select
                id="add-ext-assigned"
                v-model="form.assigned_to"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              >
                <option value="">Not Assigned</option>
                <option v-for="emp in assignableEmployees" :key="emp.id" :value="emp.id">
                  {{ emp.name || emp.label }}
                </option>
              </select>
              <p class="mt-1 text-xs text-gray-500">Only active employees are shown</p>
            </div>

            <div>
              <label for="add-ext-comment" class="mb-1 block text-sm font-medium text-gray-700">
                Comment
              </label>
              <textarea
                id="add-ext-comment"
                v-model="form.comment"
                rows="3"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
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
                class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                :disabled="submitting"
              >
                {{ submitting ? 'Creating...' : 'Add Extension' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
