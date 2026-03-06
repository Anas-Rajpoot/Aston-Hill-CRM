<script setup>
/**
 * SLA Configuration – manage SLA timers, warning thresholds, and notification rules per module.
 *
 * Features:
 * - Progressive rendering with skeleton rows
 * - Double-click any cell to edit it inline
 * - Click pencil icon to edit the entire row
 * - Save button replaces pencil icon when editing
 * - Active toggles with optimistic UI and rollback
 * - Validation (422 errors shown inline)
 * - Super Admin Only banner when user lacks permission
 * - Route leave guard when rows are being edited
 * - Audit-logged on every server update
 */
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import api from '@/lib/axios'
import Toast from '@/components/Toast.vue'
import SkeletonBox from '@/components/skeletons/SkeletonBox.vue'

// ─── State ───────────────────────────────────────────────────────────────────
const loading = ref(true)
const canUpdate = ref(false)
const rules = ref([])           // SlaRuleResource[]
const activeCount = ref(0)

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

// ─── Per-row edit state ──────────────────────────────────────────────────────
//   editingRows: { [id]: { duration, warning, email, saving, errors } }
const editingRows = reactive({})

function isEditing(id) { return !!editingRows[id] }
function hasAnyEditing() { return Object.keys(editingRows).length > 0 }

// ─── Time helpers ────────────────────────────────────────────────────────────
function minutesToHumanShort(total) {
  if (!total || total <= 0) return '0m'
  const h = Math.floor(total / 60)
  const m = total % 60
  const parts = []
  if (h > 0) parts.push(h + 'h')
  if (m > 0) parts.push(m + 'm')
  return parts.join(' ')
}

// ─── Load rules ──────────────────────────────────────────────────────────────
async function loadRules() {
  loading.value = true
  try {
    const { data } = await api.get('/sla-rules')
    rules.value = (data?.data ?? [])
      .filter((rule) => ![
        'lead_resubmissions',
        'back_office_queue',
        'field_head_queue',
      ].includes(rule?.module_key))
      .map((rule) => ({
        ...rule,
        module_name: rule?.module_name === 'Field Submissions (Assignment SLA)'
          ? 'Field Submissions'
          : rule?.module_name,
      }))
    canUpdate.value = data?.meta?.can_update ?? false
    activeCount.value = rules.value.filter((r) => r.is_active).length
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to load SLA rules.')
  } finally {
    loading.value = false
  }
}

// ─── Start editing a row (full row or single cell via double-click) ──────────
function startEdit(rule) {
  if (!canUpdate.value) return
  if (isEditing(rule.id)) return // already editing
  editingRows[rule.id] = {
    duration: rule.sla_duration_minutes,
    warning: rule.warning_threshold_minutes,
    email: rule.notification_email,
    saving: false,
    errors: {},
  }
}

function cancelEdit(id) {
  delete editingRows[id]
}

// Double-click on a specific cell — starts edit for the whole row
function onCellDblClick(rule) {
  if (!canUpdate.value) return
  startEdit(rule)
}

// ─── Save a row ──────────────────────────────────────────────────────────────
async function saveRow(rule) {
  const ed = editingRows[rule.id]
  if (!ed || ed.saving) return

  // Client-side pre-validation
  ed.errors = {}
  const durMin = parseInt(ed.duration) || 0
  const warnMin = parseInt(ed.warning) || 0

  if (durMin < 1) ed.errors.sla_duration_minutes = 'Duration must be at least 1 minute.'
  if (durMin > 10080) ed.errors.sla_duration_minutes = 'Duration cannot exceed 7 days.'
  if (warnMin < 0) ed.errors.warning_threshold_minutes = 'Warning must be 0 or more.'
  if (warnMin >= durMin) ed.errors.warning_threshold_minutes = 'Warning must be less than duration.'
  if (!ed.email || !ed.email.includes('@')) ed.errors.notification_email = 'Enter a valid email.'

  if (Object.keys(ed.errors).length) return

  ed.saving = true
  try {
    const { data } = await api.patch(`/sla-rules/${rule.id}`, {
      sla_duration_minutes: durMin,
      warning_threshold_minutes: warnMin,
      notification_email: ed.email,
    })
    // Update the local row with the fresh data
    const idx = rules.value.findIndex((r) => r.id === rule.id)
    if (idx !== -1 && data?.data) rules.value[idx] = data.data
    delete editingRows[rule.id]
    toast('success', `SLA rule "${rule.module_name}" updated.`)
  } catch (e) {
    if (e?.response?.status === 422) {
      const fieldErrors = e.response.data?.errors ?? {}
      Object.keys(fieldErrors).forEach((k) => {
        ed.errors[k] = Array.isArray(fieldErrors[k]) ? fieldErrors[k].join(' ') : fieldErrors[k]
      })
      toast('error', 'Please correct the highlighted errors.')
    } else if (e?.response?.status === 403) {
      toast('error', 'You do not have permission to update SLA rules.')
    } else {
      toast('error', e?.response?.data?.message || 'Failed to save SLA rule.')
    }
  } finally {
    if (editingRows[rule.id]) editingRows[rule.id].saving = false
  }
}

// ─── Toggle active (optimistic) ─────────────────────────────────────────────
const togglingIds = reactive({})

async function updateStatus(rule, nextStatus) {
  if (!canUpdate.value || togglingIds[rule.id]) return
  const newVal = nextStatus === 'active'
  if (rule.is_active === newVal) return

  const oldVal = rule.is_active
  rule.is_active = newVal
  togglingIds[rule.id] = true

  try {
    const { data } = await api.patch(`/sla-rules/${rule.id}/toggle`, { is_active: newVal })
    const idx = rules.value.findIndex((r) => r.id === rule.id)
    if (idx !== -1 && data?.data) rules.value[idx] = data.data
    activeCount.value = rules.value.filter((r) => r.is_active).length
  } catch (e) {
    rule.is_active = oldVal
    toast('error', e?.response?.data?.message || 'Failed to update status.')
  } finally {
    delete togglingIds[rule.id]
  }
}

// ─── Unsaved changes guard ───────────────────────────────────────────────────
onBeforeRouteLeave((to, from, next) => {
  if (hasAnyEditing() && !confirm('You have unsaved SLA edits. Discard and leave?')) {
    next(false)
  } else {
    next()
  }
})
// Only attach beforeunload AFTER user interacts (Chrome intervention fix)
function beforeUnloadHandler(e) {
  if (hasAnyEditing()) { e.preventDefault(); e.returnValue = '' }
}
let beforeUnloadAttached = false
function attachBeforeUnload() {
  if (!beforeUnloadAttached) {
    window.addEventListener('beforeunload', beforeUnloadHandler)
    beforeUnloadAttached = true
  }
}

onMounted(() => {
  loadRules()
  window.addEventListener('click', attachBeforeUnload, { once: true })
  window.addEventListener('keydown', attachBeforeUnload, { once: true })
})
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', beforeUnloadHandler)
  window.removeEventListener('click', attachBeforeUnload)
  window.removeEventListener('keydown', attachBeforeUnload)
  beforeUnloadAttached = false
})
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

    <!-- ═══ Header ═══ -->
    <div>
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-gray-900">SLA Configuration</h1>      </div>
      <p class="mt-1 text-sm text-gray-500">Manage SLA timers, warning thresholds, and notification rules per module.</p>
    </div>

    <!-- ═══ Super Admin Only Banner (always visible) ═══ -->
    <div class="rounded-lg border border-yellow-200 bg-yellow-50/60 px-5 py-3.5">
      <p class="flex items-center gap-1.5 font-semibold text-amber-800 text-sm">
        <svg class="h-4 w-4 text-amber-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
        Super Admin Only
      </p>
      <p class="mt-1 text-sm text-amber-700 pl-[22px]">SLA configuration is restricted to Super Admin users only. Changes affect system-wide SLA tracking and breach notifications.</p>
    </div>

    <!-- ═══ Table ═══ -->
    <section class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <!-- Skeleton while loading -->
      <div v-if="loading" class="divide-y divide-gray-100">
        <!-- Header skeleton -->
        <div class="grid grid-cols-6 gap-4 px-6 py-3 bg-gray-50">
          <SkeletonBox v-for="i in 6" :key="i" width="80%" height="14px" />
        </div>
        <!-- Row skeletons -->
        <div v-for="i in 7" :key="i" class="grid grid-cols-6 gap-4 px-6 py-4 items-center">
          <SkeletonBox width="70%" height="16px" />
          <SkeletonBox width="60%" height="16px" />
          <SkeletonBox width="60%" height="16px" />
          <SkeletonBox width="80%" height="16px" />
          <SkeletonBox width="44px" height="24px" class="rounded-full" />
          <SkeletonBox width="50px" height="32px" />
        </div>
      </div>

      <!-- Loaded table -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-brand-primary border-b-2 border-green-700 text-left">
              <th class="px-6 py-3 font-semibold text-white whitespace-nowrap">Page name</th>
              <th class="px-6 py-3 font-semibold text-white whitespace-nowrap">SLA Duration</th>
              <th class="px-6 py-3 font-semibold text-white whitespace-nowrap">Warning Threshold</th>
              <th class="px-6 py-3 font-semibold text-white whitespace-nowrap">Notification Email</th>
              <th class="px-6 py-3 font-semibold text-white whitespace-nowrap">Status</th>
              <th class="px-6 py-3 font-semibold text-white whitespace-nowrap text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr
              v-for="rule in rules"
              :key="rule.id"
              class="transition-colors"
              :class="isEditing(rule.id) ? 'bg-gray-50/80' : 'hover:bg-gray-50/50'"
            >
              <!-- Page name (always static) -->
              <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">{{ rule.module_name }}</td>

              <!-- SLA Duration -->
              <td
                class="px-6 py-4"
                :class="{ 'cursor-pointer': canUpdate && !isEditing(rule.id) }"
                @dblclick="onCellDblClick(rule)"
              >
                <template v-if="isEditing(rule.id)">
                  <input
                    v-model.number="editingRows[rule.id].duration"
                    type="number"
                    min="1"
                    max="10080"
                    class="w-24 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                    :disabled="editingRows[rule.id].saving"
                    placeholder="Minutes"
                  />
                  <p v-if="editingRows[rule.id].errors.sla_duration_minutes" class="mt-1 text-xs text-red-600">{{ editingRows[rule.id].errors.sla_duration_minutes }}</p>
                </template>
                <template v-else>
                  <div class="font-medium text-gray-900">{{ minutesToHumanShort(rule.sla_duration_minutes) }}</div>
                  <div class="text-xs text-gray-500">({{ rule.sla_duration_minutes }} min)</div>
                </template>
              </td>

              <!-- Warning Threshold -->
              <td
                class="px-6 py-4"
                :class="{ 'cursor-pointer': canUpdate && !isEditing(rule.id) }"
                @dblclick="onCellDblClick(rule)"
              >
                <template v-if="isEditing(rule.id)">
                  <input
                    v-model.number="editingRows[rule.id].warning"
                    type="number"
                    min="0"
                    max="10080"
                    class="w-24 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                    :disabled="editingRows[rule.id].saving"
                    placeholder="Minutes"
                  />
                  <p v-if="editingRows[rule.id].errors.warning_threshold_minutes" class="mt-1 text-xs text-red-600">{{ editingRows[rule.id].errors.warning_threshold_minutes }}</p>
                </template>
                <template v-else>
                  <div class="font-medium text-gray-900">{{ minutesToHumanShort(rule.warning_threshold_minutes) }}</div>
                  <div class="text-xs text-gray-500">before breach</div>
                </template>
              </td>

              <!-- Notification Email -->
              <td
                class="px-6 py-4"
                :class="{ 'cursor-pointer': canUpdate && !isEditing(rule.id) }"
                @dblclick="onCellDblClick(rule)"
              >
                <template v-if="isEditing(rule.id)">
                  <input
                    v-model="editingRows[rule.id].email"
                    type="email"
                    class="w-full min-w-[180px] rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                    :disabled="editingRows[rule.id].saving"
                    placeholder="email@example.com"
                  />
                  <p v-if="editingRows[rule.id].errors.notification_email" class="mt-1 text-xs text-red-600">{{ editingRows[rule.id].errors.notification_email }}</p>
                </template>
                <template v-else>
                  <span class="text-gray-700">{{ rule.notification_email }}</span>
                </template>
              </td>

              <!-- Status Dropdown -->
              <td class="px-6 py-4">
                <select
                  :value="rule.is_active ? 'active' : 'inactive'"
                  :disabled="!canUpdate || !!togglingIds[rule.id] || isEditing(rule.id)"
                  class="w-32 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary disabled:cursor-not-allowed disabled:opacity-50"
                  @change="updateStatus(rule, $event.target.value)"
                >
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </td>

              <!-- Actions -->
              <td class="px-6 py-4 text-right">
                <template v-if="isEditing(rule.id)">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      type="button"
                      :disabled="editingRows[rule.id].saving"
                      class="inline-flex items-center gap-1.5 rounded-lg bg-brand-primary px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-brand-primary-hover disabled:opacity-50 transition-colors"
                      @click="saveRow(rule)"
                    >
                      <svg v-if="editingRows[rule.id].saving" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                      <svg v-else class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                      Save
                    </button>
                    <button
                      type="button"
                      :disabled="editingRows[rule.id].saving"
                      class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-gray-300 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 disabled:opacity-50 transition-colors"
                      title="Cancel"
                      @click="cancelEdit(rule.id)"
                    >
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                  </div>
                </template>
                <template v-else-if="canUpdate">
                  <button
                    type="button"
                    class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-brand-primary hover:bg-brand-primary-light hover:text-brand-primary-hover transition-colors"
                    title="Edit row"
                    @click="startEdit(rule)"
                  >
                    <!-- Pencil with underline icon (teal) -->
                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z" />
                      <path d="M4 24h16" />
                    </svg>
                  </button>
                </template>
                <template v-else>
                  <span class="text-xs text-gray-400">—</span>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ═══ SLA Configuration Guidelines ═══ -->
    <div class="rounded-xl border border-brand-primary-muted bg-brand-primary-light px-5 py-4 flex gap-3">
      <svg class="w-5 h-5 text-brand-primary flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
      </svg>
      <div>
        <p class="font-semibold text-brand-primary-dark">SLA Configuration Guidelines</p>
        <ul class="mt-2 space-y-1.5 text-sm text-brand-primary-hover">
          <li class="flex gap-1.5">
            <span class="text-brand-primary/60 mt-0.5">•</span>
            <span><strong>SLA Duration:</strong> Total time allowed before breach occurs.</span>
          </li>
          <li class="flex gap-1.5">
            <span class="text-brand-primary/60 mt-0.5">•</span>
            <span><strong>Warning Threshold:</strong> Time before breach when warning notification is sent.</span>
          </li>
          <li class="flex gap-1.5">
            <span class="text-brand-primary/60 mt-0.5">•</span>
            <span><strong>Notification Email:</strong> Recipient for SLA breach alerts (default: order@astonhill.ae).</span>
          </li>
          <li class="flex gap-1.5">
            <span class="text-brand-primary/60 mt-0.5">•</span>
            <span><strong>Active toggle:</strong> Enable/disable SLA tracking for specific modules.</span>
          </li>
          <li class="flex gap-1.5">
            <span class="text-brand-primary/60 mt-0.5">•</span>
            <span>All times are in minutes (e.g., 480 minutes = 8 hours).</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
