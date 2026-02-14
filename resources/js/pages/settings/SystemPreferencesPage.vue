<script setup>
/**
 * System Preferences – configure global system-level behaviour and defaults.
 * Progressive rendering: page shell immediately, skeleton per section until data loads.
 * Super Admin Only badge + disabled inputs for non-authorized users.
 * Dirty-state tracking, unsaved-changes guard, Reset to Default with confirmation.
 */
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, onBeforeRouteLeave } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Toast from '@/components/Toast.vue'
import SkeletonBox from '@/components/skeletons/SkeletonBox.vue'

const router = useRouter()
const auth = useAuthStore()

// ─── State ───────────────────────────────────────────────────────────────────
const loading = ref(true)
const saving = ref(false)
const resetting = ref(false)
const canUpdate = ref(false)
const updatedAt = ref(null)
const updatedByName = ref(null)
const defaults = ref({})
const showResetConfirm = ref(false)

// Toast
const showToast = ref(false)
const toastType = ref('success')
const toastMessage = ref('')
function dismissToast() { showToast.value = false }
function toast(type, msg) {
  toastType.value = type
  toastMessage.value = msg
  showToast.value = true
}

// Meta options
const timezoneOptions = ref([])
const landingPageOptions = ref([])
const pageSizeOptions = [
  { value: 10, label: '10 rows' },
  { value: 25, label: '25 rows' },
  { value: 50, label: '50 rows' },
  { value: 100, label: '100 rows' },
]

// Form model
const form = reactive({
  timezone: 'Asia/Dubai',
  default_dashboard_landing_page: 'dashboard',
  default_table_page_size: 25,
  auto_refresh_dashboard: false,
  auto_refresh_interval_minutes: 5,
  auto_save_draft_forms: true,
  session_warning_before_logout: true,
  session_warning_minutes: 5,
})

// Snapshot for dirty detection
const snapshot = ref('')
function takeSnapshot() { snapshot.value = JSON.stringify(form) }
const isDirty = computed(() => !loading.value && JSON.stringify(form) !== snapshot.value)

// Validation errors (422)
const errors = reactive({})
function clearErrors() { Object.keys(errors).forEach((k) => delete errors[k]) }

// ─── Load ────────────────────────────────────────────────────────────────────
async function loadAll() {
  loading.value = true
  try {
    const [prefsRes, tzRes, lpRes] = await Promise.all([
      api.get('/system-preferences'),
      api.get('/meta/timezones'),
      api.get('/meta/landing-pages'),
    ])

    const prefs = prefsRes.data?.data ?? {}
    canUpdate.value = prefsRes.data?.meta?.can_update ?? false
    updatedAt.value = prefsRes.data?.meta?.updated_at ?? null
    updatedByName.value = prefsRes.data?.meta?.updated_by_name ?? null
    defaults.value = prefsRes.data?.meta?.defaults ?? {}

    Object.keys(form).forEach((k) => {
      if (prefs[k] !== undefined) form[k] = prefs[k]
    })
    takeSnapshot()

    timezoneOptions.value = tzRes.data ?? []
    landingPageOptions.value = lpRes.data ?? []
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to load system preferences.')
  } finally {
    loading.value = false
  }
}

// ─── Save ────────────────────────────────────────────────────────────────────
/** Push session-related prefs into the auth store so they take effect immediately. */
function syncSessionToAuthStore(d) {
  if (!d) return
  auth.session = {
    ...auth.session,
    warning_enabled: d.session_warning_before_logout ?? auth.session.warning_enabled,
    warning_minutes_before: d.session_warning_minutes ?? auth.session.warning_minutes_before,
  }
  if (d.timezone) auth.timezone = d.timezone
}

async function save() {
  if (!canUpdate.value || saving.value || !isDirty.value) return
  saving.value = true
  clearErrors()
  try {
    const { data } = await api.put('/system-preferences', { ...form })
    if (data?.data) {
      Object.keys(form).forEach((k) => {
        if (data.data[k] !== undefined) form[k] = data.data[k]
      })
      syncSessionToAuthStore(data.data)
    }
    takeSnapshot()
    toast('success', 'System preferences saved successfully!')
  } catch (e) {
    if (e?.response?.status === 422) {
      const fieldErrors = e.response.data?.errors ?? {}
      Object.keys(fieldErrors).forEach((k) => {
        errors[k] = Array.isArray(fieldErrors[k]) ? fieldErrors[k].join(' ') : fieldErrors[k]
      })
      toast('error', 'Please correct the highlighted fields.')
    } else if (e?.response?.status === 403) {
      toast('error', 'You do not have permission to update system preferences.')
    } else {
      toast('error', e?.response?.data?.message || 'Failed to save preferences.')
    }
  } finally {
    saving.value = false
  }
}

// ─── Reset to Default ────────────────────────────────────────────────────────
function openResetConfirm() { showResetConfirm.value = true }
function closeResetConfirm() { if (!resetting.value) showResetConfirm.value = false }

async function confirmReset() {
  if (!canUpdate.value || resetting.value) return
  resetting.value = true
  try {
    const { data } = await api.post('/system-preferences/reset')
    const d = data?.data ?? defaults.value
    Object.keys(form).forEach((k) => { if (d[k] !== undefined) form[k] = d[k] })
    takeSnapshot()
    closeResetConfirm()
    toast('success', 'System preferences reset to defaults.')
  } catch (e) {
    closeResetConfirm()
    toast('error', e?.response?.data?.message || 'Failed to reset preferences.')
  } finally {
    resetting.value = false
  }
}

// ─── Cancel ──────────────────────────────────────────────────────────────────
function cancel() {
  router.push('/settings')
}

// ─── Unsaved changes guard ───────────────────────────────────────────────────
onBeforeRouteLeave((to, from, next) => {
  if (isDirty.value && !confirm('You have unsaved changes. Are you sure you want to leave?')) {
    next(false)
  } else {
    next()
  }
})

function beforeUnloadHandler(e) {
  if (isDirty.value) {
    e.preventDefault()
    e.returnValue = ''
  }
}

onMounted(() => {
  loadAll()
  window.addEventListener('beforeunload', beforeUnloadHandler)
})
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', beforeUnloadHandler)
})

// ─── Timezone filter ─────────────────────────────────────────────────────────
const tzSearch = ref('')
const filteredTimezones = computed(() => {
  const q = tzSearch.value.toLowerCase().trim()
  if (!q) return timezoneOptions.value
  return timezoneOptions.value.filter((t) => t.label.toLowerCase().includes(q) || t.value.toLowerCase().includes(q))
})
const tzDropdownOpen = ref(false)
const selectedTimezoneLabel = computed(() => {
  const found = timezoneOptions.value.find((t) => t.value === form.timezone)
  return found?.label || form.timezone
})
function selectTimezone(tz) {
  form.timezone = tz.value
  tzDropdownOpen.value = false
  tzSearch.value = ''
}
function closeTzDropdown() {
  setTimeout(() => { tzDropdownOpen.value = false }, 200)
}
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <Toast
      :show="showToast"
      :type="toastType"
      :message="toastMessage"
      :duration="toastType === 'error' ? 5000 : 3000"
      @dismiss="dismissToast"
    />

    <!-- Header -->
    <div>
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-gray-900">System Preferences</h1>
        <Breadcrumbs />
        <span
          v-if="!loading && !canUpdate"
          class="inline-flex items-center gap-1.5 rounded-lg bg-green-50 border border-green-200 px-3 py-1.5 text-xs font-semibold text-green-700"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          Super Admin Only
        </span>
      </div>
      <p class="mt-1 text-sm text-gray-500">Configure global system-level behavior and defaults for all users.</p>
    </div>

    <!-- ═══════ General Settings ═══════ -->
    <section class="rounded-xl border border-gray-200 bg-gray-50/60 shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="text-base font-semibold text-gray-900">General Settings</h2>
        <p class="text-sm text-gray-500 mt-0.5">Configure language, date format, and timezone preferences.</p>
      </div>

      <!-- Skeleton -->
      <div v-if="loading" class="p-6 space-y-6">
        <div v-for="i in 3" :key="i" class="flex items-center justify-between">
          <div class="space-y-1"><SkeletonBox width="140px" height="14px" /><SkeletonBox width="220px" height="12px" class="mt-1" /></div>
          <SkeletonBox width="300px" height="40px" />
        </div>
      </div>

      <!-- Loaded -->
      <div v-else class="divide-y divide-gray-100">
        <!-- Timezone (searchable select) -->
        <div class="px-6 py-5 flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-[160px]">
            <label class="text-sm font-medium text-gray-900">Timezone</label>
            <p class="text-xs text-gray-500 mt-0.5">Default timezone for timestamps and scheduling.</p>
            <p v-if="errors.timezone" class="text-xs text-red-600 mt-1">{{ errors.timezone }}</p>
          </div>
          <div class="relative w-full max-w-xl">
            <button
              type="button"
              class="w-full flex items-center justify-between rounded-lg border px-3 py-2.5 text-sm text-left"
              :class="canUpdate ? 'border-gray-300 bg-white hover:bg-gray-50 cursor-pointer' : 'border-gray-200 bg-gray-100 cursor-not-allowed opacity-70'"
              :disabled="!canUpdate"
              @click="canUpdate && (tzDropdownOpen = !tzDropdownOpen)"
            >
              <span class="truncate">{{ selectedTimezoneLabel }}</span>
              <svg class="w-4 h-4 text-gray-400 shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </button>
            <div v-if="tzDropdownOpen" class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-hidden">
              <div class="p-2 border-b border-gray-100">
                <input
                  v-model="tzSearch"
                  type="text"
                  placeholder="Search timezone..."
                  class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                  @blur="closeTzDropdown"
                />
              </div>
              <ul class="overflow-y-auto max-h-48">
                <li
                  v-for="tz in filteredTimezones"
                  :key="tz.value"
                  class="px-3 py-2 text-sm cursor-pointer hover:bg-blue-50"
                  :class="tz.value === form.timezone ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                  @mousedown.prevent="selectTimezone(tz)"
                >
                  {{ tz.label }}
                </li>
                <li v-if="!filteredTimezones.length" class="px-3 py-2 text-sm text-gray-400">No timezones found.</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Default Dashboard Landing Page -->
        <div class="px-6 py-5 flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-[160px]">
            <label for="landing_page" class="text-sm font-medium text-gray-900">Default Dashboard Landing Page</label>
            <p class="text-xs text-gray-500 mt-0.5">Page to display after login.</p>
            <p v-if="errors.default_dashboard_landing_page" class="text-xs text-red-600 mt-1">{{ errors.default_dashboard_landing_page }}</p>
          </div>
          <select
            id="landing_page"
            v-model="form.default_dashboard_landing_page"
            :disabled="!canUpdate"
            class="w-full max-w-xl rounded-lg border border-gray-300 px-3 py-2.5 text-sm disabled:bg-gray-100 disabled:opacity-70 disabled:cursor-not-allowed"
          >
            <option v-for="lp in landingPageOptions" :key="lp.value" :value="lp.value">{{ lp.label }}</option>
          </select>
        </div>

        <!-- Default Table Page Size -->
        <div class="px-6 py-5 flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-[160px]">
            <label for="page_size" class="text-sm font-medium text-gray-900">Default Table Page Size</label>
            <p class="text-xs text-gray-500 mt-0.5">Default number of rows per page in tables.</p>
            <p v-if="errors.default_table_page_size" class="text-xs text-red-600 mt-1">{{ errors.default_table_page_size }}</p>
          </div>
          <select
            id="page_size"
            v-model.number="form.default_table_page_size"
            :disabled="!canUpdate"
            class="w-full max-w-xl rounded-lg border border-gray-300 px-3 py-2.5 text-sm disabled:bg-gray-100 disabled:opacity-70 disabled:cursor-not-allowed"
          >
            <option v-for="ps in pageSizeOptions" :key="ps.value" :value="ps.value">{{ ps.label }}</option>
          </select>
        </div>
      </div>
    </section>

    <!-- ═══════ System Behavior ═══════ -->
    <section class="rounded-xl border border-gray-200 bg-gray-50/60 shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="text-base font-semibold text-gray-900">System Behavior</h2>
        <p class="text-sm text-gray-500 mt-0.5">Configure automatic system behaviors and user session settings.</p>
      </div>

      <!-- Skeleton -->
      <div v-if="loading" class="p-6 space-y-6">
        <div v-for="i in 3" :key="i" class="flex items-center justify-between">
          <div class="space-y-1"><SkeletonBox width="180px" height="14px" /><SkeletonBox width="260px" height="12px" class="mt-1" /></div>
          <SkeletonBox width="52px" height="28px" class="rounded-full" />
        </div>
      </div>

      <!-- Loaded -->
      <div v-else class="divide-y divide-gray-100">
        <!-- Auto Refresh Dashboard -->
        <div class="px-6 py-5 flex flex-wrap items-center justify-between gap-4">
          <div class="min-w-[200px]">
            <p class="text-sm font-medium text-gray-900">Auto Refresh Dashboard</p>
            <p class="text-xs text-gray-500 mt-0.5">Automatically refresh dashboard data every {{ form.auto_refresh_interval_minutes }} minutes.</p>
          </div>
          <div class="flex items-center gap-3">
            <button
              type="button"
              role="switch"
              :aria-checked="form.auto_refresh_dashboard"
              :aria-label="'Auto Refresh Dashboard'"
              :disabled="!canUpdate"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
              :class="form.auto_refresh_dashboard ? 'bg-blue-600' : 'bg-gray-300'"
              @click="canUpdate && (form.auto_refresh_dashboard = !form.auto_refresh_dashboard)"
            >
              <span
                class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform"
                :class="form.auto_refresh_dashboard ? 'translate-x-6' : 'translate-x-1'"
              />
            </button>
            <span class="text-sm" :class="form.auto_refresh_dashboard ? 'text-blue-700 font-medium' : 'text-gray-500'">
              {{ form.auto_refresh_dashboard ? 'Enabled' : 'Disabled' }}
            </span>
          </div>
        </div>

        <!-- Auto Save Draft Forms -->
        <div class="px-6 py-5 flex flex-wrap items-center justify-between gap-4">
          <div class="min-w-[200px]">
            <p class="text-sm font-medium text-gray-900">Auto Save Draft Forms</p>
            <p class="text-xs text-gray-500 mt-0.5">Automatically save form progress as draft.</p>
          </div>
          <div class="flex items-center gap-3">
            <button
              type="button"
              role="switch"
              :aria-checked="form.auto_save_draft_forms"
              :aria-label="'Auto Save Draft Forms'"
              :disabled="!canUpdate"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
              :class="form.auto_save_draft_forms ? 'bg-blue-600' : 'bg-gray-300'"
              @click="canUpdate && (form.auto_save_draft_forms = !form.auto_save_draft_forms)"
            >
              <span
                class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform"
                :class="form.auto_save_draft_forms ? 'translate-x-6' : 'translate-x-1'"
              />
            </button>
            <span class="text-sm" :class="form.auto_save_draft_forms ? 'text-blue-700 font-medium' : 'text-gray-500'">
              {{ form.auto_save_draft_forms ? 'Enabled' : 'Disabled' }}
            </span>
          </div>
        </div>

        <!-- Session Warning Before Logout -->
        <div class="px-6 py-5 flex flex-wrap items-center justify-between gap-4">
          <div class="min-w-[200px]">
            <p class="text-sm font-medium text-gray-900">Session Warning Before Logout</p>
            <p class="text-xs text-gray-500 mt-0.5">Warn users {{ form.session_warning_minutes }} minutes before auto logout.</p>
          </div>
          <div class="flex items-center gap-3">
            <button
              type="button"
              role="switch"
              :aria-checked="form.session_warning_before_logout"
              :aria-label="'Session Warning Before Logout'"
              :disabled="!canUpdate"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
              :class="form.session_warning_before_logout ? 'bg-blue-600' : 'bg-gray-300'"
              @click="canUpdate && (form.session_warning_before_logout = !form.session_warning_before_logout)"
            >
              <span
                class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform"
                :class="form.session_warning_before_logout ? 'translate-x-6' : 'translate-x-1'"
              />
            </button>
            <span class="text-sm" :class="form.session_warning_before_logout ? 'text-blue-700 font-medium' : 'text-gray-500'">
              {{ form.session_warning_before_logout ? 'Enabled' : 'Disabled' }}
            </span>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════ Action Buttons ═══════ -->
    <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-gray-200">
      <button
        v-if="canUpdate"
        type="button"
        :disabled="resetting || saving"
        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition-colors"
        @click="openResetConfirm"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
        Reset to Default
      </button>
      <div v-else></div>
      <div class="flex items-center gap-3">
        <button
          type="button"
          class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
          @click="cancel"
        >
          Cancel
        </button>
        <button
          v-if="canUpdate"
          type="button"
          :disabled="!isDirty || saving"
          class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          @click="save"
        >
          <svg v-if="saving" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
          {{ saving ? 'Saving…' : 'Save Changes' }}
        </button>
      </div>
    </div>

    <!-- ═══════ System Notes ═══════ -->
    <div class="rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 flex gap-3">
      <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
      </svg>
      <div>
        <p class="font-semibold text-blue-900">System Notes</p>
        <ul class="mt-2 space-y-1 text-sm text-blue-800 list-disc list-inside">
          <li>Changes are logged in Audit Logs for accountability.</li>
          <li>Only Super Admin can modify these settings.</li>
          <li>Date format will be applied consistently across the entire UI.</li>
          <li>Changes take effect immediately for all users.</li>
        </ul>
      </div>
    </div>

    <!-- ═══════ Reset to Default Confirmation Modal ═══════ -->
    <Teleport to="body">
      <div v-if="showResetConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="closeResetConfirm">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden" @click.stop>
          <div class="px-6 pt-6 pb-4">
            <div class="flex gap-3">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-900">Reset to Default</h3>
                <p class="mt-2 text-sm text-gray-600">Are you sure you want to reset all system preferences to their default values? This action cannot be undone.</p>
              </div>
            </div>
          </div>
          <div class="px-6 pb-6 pt-2 flex justify-end gap-3">
            <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" :disabled="resetting" @click="closeResetConfirm">Cancel</button>
            <button type="button" class="rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50" :disabled="resetting" @click="confirmReset">
              {{ resetting ? 'Resetting…' : 'Reset to Default' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
