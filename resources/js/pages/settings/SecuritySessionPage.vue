<script setup>
/**
 * Security, Session & Access Control
 *
 * Sections: Session Management, Login & Account Security, Password Policies,
 * Audit & Safety (static), Footer with Reset to Default + Save.
 * Progressive rendering with skeletons. Super Admin Only badge + disabled controls.
 */
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Toast from '@/components/Toast.vue'
import SkeletonBox from '@/components/skeletons/SkeletonBox.vue'

// ─── State ────────────────────────────────────────────────
const loading   = ref(true)
const saving    = ref(false)
const resetting = ref(false)
const canUpdate = ref(false)
const showResetConfirm = ref(false)
const errors = reactive({})

// Toast
const showToast    = ref(false)
const toastType    = ref('success')
const toastMessage = ref('')
function dismissToast() { showToast.value = false }
function toast(type, msg) { toastType.value = type; toastMessage.value = msg; showToast.value = true }

// ─── Form fields ──────────────────────────────────────────
const form = reactive({
  // Session Management
  auto_logout_after_minutes: 120,
  session_warning_minutes: 5,
  force_logout_on_close: false,
  prevent_multiple_sessions: false,
  // Login & Account Security
  max_login_attempts: 5,
  lock_after_failed_attempts: true,
  lock_duration_minutes: 30,
  force_password_reset_on_first_login: true,
  // Password Policies
  min_length: 8,
  require_uppercase: true,
  require_number: true,
  require_special: true,
  password_expiry_days: 90,
})
const original = ref({})

// ─── Dirty tracking ──────────────────────────────────────
const isDirty = computed(() => {
  const orig = original.value
  return Object.keys(form).some(k => {
    return JSON.stringify(form[k]) !== JSON.stringify(orig[k])
  })
})

// ─── Unsaved changes guard ───────────────────────────────
// Only attach beforeunload AFTER user interacts (Chrome intervention fix)
function beforeUnload(e) { if (isDirty.value) { e.preventDefault(); e.returnValue = '' } }
let beforeUnloadAttached = false
function attachBeforeUnload() {
  if (!beforeUnloadAttached) {
    window.addEventListener('beforeunload', beforeUnload)
    beforeUnloadAttached = true
  }
}
onMounted(() => {
  window.addEventListener('click', attachBeforeUnload, { once: true })
  window.addEventListener('keydown', attachBeforeUnload, { once: true })
})
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', beforeUnload)
  window.removeEventListener('click', attachBeforeUnload)
  window.removeEventListener('keydown', attachBeforeUnload)
  beforeUnloadAttached = false
})
onBeforeRouteLeave((to, from, next) => {
  if (isDirty.value && !confirm('You have unsaved changes. Leave anyway?')) return next(false)
  next()
})

// ─── Fetch ───────────────────────────────────────────────
async function fetchSettings() {
  loading.value = true
  try {
    const { data } = await api.get('/security-settings')
    const d = data.data
    Object.keys(form).forEach(k => { if (d[k] !== undefined) form[k] = d[k] })
    original.value = { ...form }
    canUpdate.value = data.meta?.can_update ?? false
  } catch {
    toast('error', 'Failed to load security settings.')
  } finally {
    loading.value = false
  }
}
onMounted(fetchSettings)

// ─── Save ────────────────────────────────────────────────
async function save() {
  if (saving.value || !isDirty.value) return
  saving.value = true
  Object.keys(errors).forEach(k => delete errors[k])

  try {
    const { data } = await api.put('/security-settings', { ...form })
    const d = data.data
    Object.keys(form).forEach(k => { if (d[k] !== undefined) form[k] = d[k] })
    original.value = { ...form }
    toast('success', 'Security settings saved successfully.')
  } catch (e) {
    if (e?.response?.status === 422) {
      const fe = e.response.data?.errors ?? {}
      Object.keys(fe).forEach(k => { errors[k] = Array.isArray(fe[k]) ? fe[k].join(' ') : fe[k] })
      toast('error', 'Please fix the validation errors.')
    } else {
      toast('error', e?.response?.data?.message || 'Failed to save settings.')
    }
  } finally { saving.value = false }
}

// ─── Reset to Default ────────────────────────────────────
async function resetDefaults() {
  if (resetting.value) return
  resetting.value = true
  try {
    const { data } = await api.post('/security-settings/reset')
    const d = data.data
    Object.keys(form).forEach(k => { if (d[k] !== undefined) form[k] = d[k] })
    original.value = { ...form }
    showResetConfirm.value = false
    toast('success', 'Settings reset to defaults.')
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to reset.')
  } finally { resetting.value = false }
}

// ─── Select options ──────────────────────────────────────
const autoLogoutOptions = [
  { value: 10, label: '10 Minutes' },
  { value: 15, label: '15 Minutes' },
  { value: 30, label: '30 Minutes' },
  { value: 45, label: '45 Minutes' },
  { value: 60, label: '1 Hour' },
  { value: 120, label: '2 Hours (Default)' },
  { value: 240, label: '4 Hours' },
  { value: 480, label: '8 Hours' },
  { value: 720, label: '12 Hours' },
  { value: 1440, label: '24 Hours' },
]
const warningOptions = [
  { value: 1, label: '1 Minute' },
  { value: 3, label: '3 Minutes' },
  { value: 5, label: '5 Minutes' },
  { value: 10, label: '10 Minutes' },
  { value: 15, label: '15 Minutes' },
]

// Breadcrumbs are auto-generated by the Breadcrumbs component from the route
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-4 py-6">
    <Toast :show="showToast" :type="toastType" :message="toastMessage" :duration="4000" @dismiss="dismissToast" />

    <!-- ═══ Header ═══ -->
    <div>
      <div class="flex items-center justify-between gap-4">
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-2xl font-bold text-gray-900 leading-tight">Security, Session & Access Control</h1>
          <Breadcrumbs />
        </div>
        <span class="inline-flex items-center gap-1.5 shrink-0 rounded-lg bg-amber-50 border border-amber-200 px-3 py-1.5 text-xs font-semibold text-amber-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
          Super Admin Only
        </span>
      </div>
      <p class="mt-1 text-sm text-gray-500">Manage session security, access restrictions, and system-level permissions.</p>
    </div>

    <div class="space-y-6">

      <!-- ═══ Info Banner ═══ -->
      <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 flex items-start gap-3">
        <div class="flex-shrink-0 mt-0.5">
          <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <div>
          <p class="text-sm font-semibold text-amber-800">Security & Access Settings</p>
          <p class="text-sm text-amber-700 mt-0.5">Changes to security and access settings apply system-wide immediately and affect all users. All modifications are logged in Audit Logs.</p>
        </div>
      </div>

      <!-- ═══ Loading Skeletons ═══ -->
      <template v-if="loading">
        <div v-for="i in 3" :key="i" class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
          <SkeletonBox class="h-6 w-48" />
          <SkeletonBox class="h-4 w-72" />
          <div class="grid grid-cols-4 gap-4 mt-4">
            <div v-for="j in 4" :key="j" class="space-y-2">
              <SkeletonBox class="h-4 w-32" />
              <SkeletonBox class="h-10 w-full" />
              <SkeletonBox class="h-3 w-40" />
            </div>
          </div>
        </div>
      </template>

      <template v-else>

        <!-- ═══ Section 1: Session Management ═══ -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
          <div class="flex items-center gap-3 mb-5">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-blue-600">
              <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <div>
              <h2 class="text-base font-bold text-gray-900">Session Management</h2>
              <p class="text-sm text-gray-500">Control user session timeout and behavior</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Auto Logout After Inactivity -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Auto Logout After Inactivity</label>
              <div class="relative">
                <select
                  v-model.number="form.auto_logout_after_minutes"
                  :disabled="!canUpdate"
                  class="w-full appearance-none rounded-lg border border-gray-300 bg-white px-3 py-2.5 pr-10 text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-500 transition"
                >
                  <option v-for="o in autoLogoutOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
                <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
              </div>
              <p class="text-xs text-gray-400 mt-1">Users will be logged out after this period of inactivity</p>
              <p v-if="errors.auto_logout_after_minutes" class="mt-1 text-xs text-red-600">{{ errors.auto_logout_after_minutes }}</p>
            </div>

            <!-- Session Warning Before Logout -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Session Warning Before Logout</label>
              <div class="relative">
                <select
                  v-model.number="form.session_warning_minutes"
                  :disabled="!canUpdate"
                  class="w-full appearance-none rounded-lg border border-gray-300 bg-white px-3 py-2.5 pr-10 text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-500 transition"
                >
                  <option v-for="o in warningOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
                <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
              </div>
              <p class="text-xs text-gray-400 mt-1">Warning notification before auto logout</p>
              <p v-if="errors.session_warning_minutes" class="mt-1 text-xs text-red-600">{{ errors.session_warning_minutes }}</p>
            </div>

            <!-- Force Logout on Browser Close -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Force Logout on Browser Close</label>
              <div class="flex items-center gap-2.5 mt-2">
                <button
                  type="button"
                  role="switch"
                  :aria-checked="form.force_logout_on_close"
                  :disabled="!canUpdate"
                  class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                  :class="form.force_logout_on_close ? 'bg-blue-600' : 'bg-gray-200'"
                  @click="canUpdate && (form.force_logout_on_close = !form.force_logout_on_close)"
                >
                  <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out" :class="form.force_logout_on_close ? 'translate-x-5' : 'translate-x-0'" />
                </button>
                <span class="text-sm text-gray-600">{{ form.force_logout_on_close ? 'Enabled' : 'Disabled' }}</span>
              </div>
              <p class="text-xs text-gray-400 mt-1.5">End session when browser is closed</p>
            </div>

            <!-- Prevent Multiple Active Sessions -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Prevent Multiple Active Sessions</label>
              <div class="flex items-center gap-2.5 mt-2">
                <button
                  type="button"
                  role="switch"
                  :aria-checked="form.prevent_multiple_sessions"
                  :disabled="!canUpdate"
                  class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                  :class="form.prevent_multiple_sessions ? 'bg-blue-600' : 'bg-gray-200'"
                  @click="canUpdate && (form.prevent_multiple_sessions = !form.prevent_multiple_sessions)"
                >
                  <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out" :class="form.prevent_multiple_sessions ? 'translate-x-5' : 'translate-x-0'" />
                </button>
                <span class="text-sm" :class="form.prevent_multiple_sessions ? 'text-green-600' : 'text-red-600'">{{ form.prevent_multiple_sessions ? '\u2705 Users can login on multiple devices' : '\u274C Users can only login on one device' }}</span>
              </div>
              <p class="text-xs text-gray-400 mt-1.5">When enabled: users can login on multiple devices simultaneously. When disabled: new login terminates all other active sessions.</p>
            </div>
          </div>
        </div>

        <!-- ═══ Section 2: Login & Account Security ═══ -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
          <div class="flex items-center gap-3 mb-5">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-red-100 text-red-600">
              <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
            </div>
            <div>
              <h2 class="text-base font-bold text-gray-900">Login & Account Security</h2>
              <p class="text-sm text-gray-500">Protect against unauthorized access attempts</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Max Login Attempts -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Max Login Attempts</label>
              <input
                v-model.number="form.max_login_attempts"
                type="number"
                min="1"
                max="20"
                :disabled="!canUpdate"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-500 transition"
              />
              <p class="text-xs text-gray-400 mt-1">Maximum failed login attempts before action</p>
              <p v-if="errors.max_login_attempts" class="mt-1 text-xs text-red-600">{{ errors.max_login_attempts }}</p>
            </div>

            <!-- Lock Account After Failed Attempts -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Lock Account After Failed Attempts</label>
              <div class="flex items-center gap-2.5 mt-2">
                <button
                  type="button"
                  role="switch"
                  :aria-checked="form.lock_after_failed_attempts"
                  :disabled="!canUpdate"
                  class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                  :class="form.lock_after_failed_attempts ? 'bg-blue-600' : 'bg-gray-200'"
                  @click="canUpdate && (form.lock_after_failed_attempts = !form.lock_after_failed_attempts)"
                >
                  <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out" :class="form.lock_after_failed_attempts ? 'translate-x-5' : 'translate-x-0'" />
                </button>
                <span class="text-sm text-gray-600">{{ form.lock_after_failed_attempts ? 'Enabled' : 'Disabled' }}</span>
              </div>
              <p class="text-xs text-gray-400 mt-1.5">Temporarily lock account after max attempts</p>
            </div>

            <!-- Lock Duration (Minutes) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Lock Duration (Minutes)</label>
              <input
                v-model.number="form.lock_duration_minutes"
                type="number"
                min="1"
                max="1440"
                :disabled="!canUpdate || !form.lock_after_failed_attempts"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-500 transition"
              />
              <p class="text-xs text-gray-400 mt-1">How long to lock the account</p>
              <p v-if="errors.lock_duration_minutes" class="mt-1 text-xs text-red-600">{{ errors.lock_duration_minutes }}</p>
            </div>

            <!-- Force Password Reset on First Login -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Force Password Reset on First Login</label>
              <div class="flex items-center gap-2.5 mt-2">
                <button
                  type="button"
                  role="switch"
                  :aria-checked="form.force_password_reset_on_first_login"
                  :disabled="!canUpdate"
                  class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                  :class="form.force_password_reset_on_first_login ? 'bg-blue-600' : 'bg-gray-200'"
                  @click="canUpdate && (form.force_password_reset_on_first_login = !form.force_password_reset_on_first_login)"
                >
                  <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out" :class="form.force_password_reset_on_first_login ? 'translate-x-5' : 'translate-x-0'" />
                </button>
                <span class="text-sm text-gray-600">{{ form.force_password_reset_on_first_login ? 'Enabled' : 'Disabled' }}</span>
              </div>
              <p class="text-xs text-gray-400 mt-1.5">New users must change default password</p>
            </div>
          </div>
        </div>

        <!-- ═══ Section 3: Password Policies ═══ -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
          <div class="flex items-center gap-3 mb-5">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-orange-100 text-orange-600">
              <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
            </div>
            <div>
              <h2 class="text-base font-bold text-gray-900">Password Policies</h2>
              <p class="text-sm text-gray-500">Define password strength requirements</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5 items-start">
            <!-- Minimum Password Length -->
            <div class="flex h-full flex-col">
              <label class="block min-h-[3rem] text-sm font-medium text-gray-700 mb-1.5">Minimum Password Length</label>
              <input
                v-model.number="form.min_length"
                type="number"
                min="6"
                max="128"
                :disabled="!canUpdate"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-500 transition"
              />
              <p class="text-xs text-gray-400 mt-1">Minimum characters required</p>
              <p v-if="errors.min_length" class="mt-1 text-xs text-red-600">{{ errors.min_length }}</p>
            </div>

            <!-- Require Uppercase Letter -->
            <div class="flex h-full flex-col">
              <label class="block min-h-[3rem] text-sm font-medium text-gray-700 mb-1.5">Require Uppercase Letter</label>
              <div class="flex h-[42px] items-center gap-2.5">
                <button
                  type="button"
                  role="switch"
                  :aria-checked="form.require_uppercase"
                  :disabled="!canUpdate"
                  class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                  :class="form.require_uppercase ? 'bg-blue-600' : 'bg-gray-200'"
                  @click="canUpdate && (form.require_uppercase = !form.require_uppercase)"
                >
                  <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out" :class="form.require_uppercase ? 'translate-x-5' : 'translate-x-0'" />
                </button>
                <span class="text-sm text-gray-600">{{ form.require_uppercase ? 'Required' : 'Optional' }}</span>
              </div>
              <p class="text-xs text-gray-400 mt-1.5">At least one uppercase letter (A-Z)</p>
            </div>

            <!-- Require Number -->
            <div class="flex h-full flex-col">
              <label class="block min-h-[3rem] text-sm font-medium text-gray-700 mb-1.5">Require Number</label>
              <div class="flex h-[42px] items-center gap-2.5">
                <button
                  type="button"
                  role="switch"
                  :aria-checked="form.require_number"
                  :disabled="!canUpdate"
                  class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                  :class="form.require_number ? 'bg-blue-600' : 'bg-gray-200'"
                  @click="canUpdate && (form.require_number = !form.require_number)"
                >
                  <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out" :class="form.require_number ? 'translate-x-5' : 'translate-x-0'" />
                </button>
                <span class="text-sm text-gray-600">{{ form.require_number ? 'Required' : 'Optional' }}</span>
              </div>
              <p class="text-xs text-gray-400 mt-1.5">At least one number (0-9)</p>
            </div>

            <!-- Require Special Character -->
            <div class="flex h-full flex-col">
              <label class="block min-h-[3rem] text-sm font-medium text-gray-700 mb-1.5">Require Special Character</label>
              <div class="flex h-[42px] items-center gap-2.5">
                <button
                  type="button"
                  role="switch"
                  :aria-checked="form.require_special"
                  :disabled="!canUpdate"
                  class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                  :class="form.require_special ? 'bg-blue-600' : 'bg-gray-200'"
                  @click="canUpdate && (form.require_special = !form.require_special)"
                >
                  <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out" :class="form.require_special ? 'translate-x-5' : 'translate-x-0'" />
                </button>
                <span class="text-sm text-gray-600">{{ form.require_special ? 'Required' : 'Optional' }}</span>
              </div>
              <p class="text-xs text-gray-400 mt-1.5">At least one special character (!@#$%)</p>
            </div>

            <!-- Password Expiry (Days) -->
            <div class="flex h-full flex-col">
              <label class="block min-h-[3rem] text-sm font-medium text-gray-700 mb-1.5">Password Expiry (Days)</label>
              <input
                v-model.number="form.password_expiry_days"
                type="number"
                min="0"
                max="3650"
                :disabled="!canUpdate"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-500 transition"
              />
              <p class="text-xs text-gray-400 mt-1">Force password change after this period (0 = never expires)</p>
              <p class="text-xs text-gray-400 mt-0.5 italic">Note: Super admin accounts are exempt from password expiry</p>
              <p v-if="errors.password_expiry_days" class="mt-1 text-xs text-red-600">{{ errors.password_expiry_days }}</p>
            </div>
          </div>
        </div>

        <!-- ═══ Section 4: Audit & Safety (static) ═══ -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500">
              <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h2 class="text-base font-bold text-gray-900">Audit & Safety</h2>
          </div>
          <ul class="space-y-1.5 text-sm text-gray-600 list-disc list-inside ml-1">
            <li>All security and access changes are logged in Audit Logs for accountability</li>
            <li>Changes apply system-wide immediately and affect all users</li>
            <li>Only Super Admin can modify security and access control settings</li>
            <li>Log includes who changed access, what module, and before/after state</li>
            <li>Recommended: Review security settings quarterly</li>
          </ul>
        </div>

        <!-- ═══ Footer Actions ═══ -->
        <div class="flex items-center justify-between pt-2 pb-8">
          <button
            v-if="canUpdate"
            type="button"
            :disabled="resetting"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition-colors"
            @click="showResetConfirm = true"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
            Reset to Default
          </button>
          <div v-else></div>

          <button
            v-if="canUpdate"
            type="button"
            :disabled="saving || !isDirty"
            class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            @click="save"
          >
            <svg v-if="saving" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
            {{ saving ? 'Saving…' : 'Save Security & Access Rules' }}
          </button>
        </div>

      </template>
    </div>

    <!-- ═══ Reset Confirmation Modal ═══ -->
    <Teleport to="body">
      <div v-if="showResetConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="showResetConfirm = false">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full overflow-hidden" @click.stop>
          <div class="px-6 pt-6 pb-4">
            <div class="flex items-start gap-3">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-500">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <div>
                <h3 class="text-base font-semibold text-gray-900">Reset to Default</h3>
                <p class="text-sm text-gray-500 mt-0.5">This will restore all settings to their original defaults</p>
              </div>
            </div>
            <p class="mt-4 text-sm text-gray-600">Are you sure you want to reset all security settings? This action will apply system-wide immediately.</p>
          </div>
          <div class="px-6 pb-6 pt-2 flex justify-end gap-3">
            <button
              type="button"
              :disabled="resetting"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
              @click="showResetConfirm = false"
            >Cancel</button>
            <button
              type="button"
              :disabled="resetting"
              class="rounded-lg bg-amber-500 px-5 py-2 text-sm font-medium text-white hover:bg-amber-600 disabled:opacity-50 transition-colors"
              @click="resetDefaults"
            >{{ resetting ? 'Resetting…' : 'Confirm Reset' }}</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
