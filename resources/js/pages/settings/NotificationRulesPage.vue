<script setup>
/**
 * Notifications & Email Rules – comprehensive settings page.
 *
 * Sections:
 *  1. Global Email Settings (form, explicit save)
 *  2. Notification Channels (immediate toggle saves)
 *  3. Notification Triggers (table, immediate toggle saves)
 *  4. SLA Escalation Emails (form, explicit save)
 *  5. Email Template Management (table + modal)
 *  6. Notification Log (paginated table)
 *  7. Test Notification (send test)
 *  8. Audit & Safety notes
 */
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import api from '@/lib/axios'
import draggable from 'vuedraggable'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Toast from '@/components/Toast.vue'
import SkeletonBox from '@/components/skeletons/SkeletonBox.vue'
import EmailTemplateModal from '@/components/notifications/EmailTemplateModal.vue'

const router = useRouter()

// ═══════════════════════════════════════════════════════════
//  Toast
// ═══════════════════════════════════════════════════════════
const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function dismissToast() { showToast.value = false }
function toast(type, msg) { toastType.value = type; toastMsg.value = msg; showToast.value = true }

// ═══════════════════════════════════════════════════════════
//  Loading & auth (granular permissions from backend)
// ═══════════════════════════════════════════════════════════
const configLoading     = ref(true)
const templatesLoading  = ref(true)
const logsLoading       = ref(true)
const canUpdate         = ref(false)    // legacy: general manage permission
const canEditSettings   = ref(false)    // notification_rules.edit_settings
const canManageChannels = ref(false)    // notification_rules.manage_channels
const canManageTriggers = ref(false)    // notification_rules.manage_triggers
const canManageEscalations = ref(false) // notification_rules.manage_escalations
const canManageTemplates = ref(false)   // notification_rules.manage_templates
const canViewLogs       = ref(false)    // notification_rules.view_logs
const canSendTest       = ref(false)    // notification_rules.send_test
const canDelete         = ref(false)    // notification_rules.delete

// ═══════════════════════════════════════════════════════════
//  § 1 – Global Email Settings
// ═══════════════════════════════════════════════════════════
const emailForm = reactive({
  default_sender_email: '',
  cc_emails: '',
  bcc_emails: '',
})
const emailSnapshot     = ref('')
const emailSaving       = ref(false)
const emailErrors       = reactive({})
const emailDirty        = computed(() => JSON.stringify(emailForm) !== emailSnapshot.value)

function takeEmailSnapshot() { emailSnapshot.value = JSON.stringify(emailForm) }

// ═══════════════════════════════════════════════════════════
//  § 2 – Channels
// ═══════════════════════════════════════════════════════════
const channels = reactive({
  enable_email: true,
  enable_web: true,
  enable_sms: false,
  enable_sla_alerts: true,
})
const channelSaving = reactive({})

const channelMeta = [
  { key: 'enable_email',      label: 'Email Notifications',  desc: 'Send emails for all system notifications' },
  { key: 'enable_web',        label: 'In-App Notifications', desc: 'Show notifications in the notification bell' },
  { key: 'enable_sla_alerts', label: 'SLA Alerts',           desc: 'Receive SLA warning and breach notifications' },
]

async function toggleChannel(key) {
  if (!canManageChannels.value || channelSaving[key]) return
  const old = channels[key]
  channels[key] = !old
  channelSaving[key] = true
  try {
    await api.put('/notification-settings', { [key]: channels[key] })
    // Refetch triggers to get updated resolved state & lock flags
    await refetchTriggers()
    const label = channelMeta.find(c => c.key === key)?.label ?? key
    toast('success', `${label} ${channels[key] ? 'enabled' : 'disabled'}.`)
  } catch {
    channels[key] = old
    toast('error', 'Failed to toggle channel.')
  } finally { delete channelSaving[key] }
}

async function refetchTriggers() {
  try {
    const { data } = await api.get('/notification-config')
    triggers.value = data?.data?.triggers ?? []
    // Sync channel state from response in case it changed
    const ch = data?.data?.channels
    if (ch) {
      channels.enable_email      = ch.enable_email ?? channels.enable_email
      channels.enable_web        = ch.enable_web ?? channels.enable_web
      channels.enable_sla_alerts = ch.enable_sla_alerts ?? channels.enable_sla_alerts
    }
  } catch { /* ignore */ }
}

// ═══════════════════════════════════════════════════════════
//  § 3 – Triggers
// ═══════════════════════════════════════════════════════════
const triggers       = ref([])
const triggerSaving  = reactive({}) // { `${id}_${field}`: true }

const triggerCols = [
  { key: 'email_enabled',       label: 'Email',       masterKey: 'enable_email',      channelKey: 'email' },
  { key: 'in_app_enabled',      label: 'In-App',      masterKey: 'enable_web',        channelKey: 'in_app' },
  { key: 'email_alert_enabled', label: 'SLA Alerts',  masterKey: 'enable_sla_alerts', channelKey: 'sla_alerts' },
  { key: 'is_active',           label: 'Active',      masterKey: null,                channelKey: null },
]

/**
 * Check if a trigger column is locked by its master channel switch.
 * Uses backend-provided lock flags when available, falls back to client-side check.
 */
function isColumnLocked(col, tr = null) {
  if (!col.masterKey) return false
  // If a trigger row is provided, use its backend lock flag
  if (tr) {
    const channelKey = col.channelKey // e.g. 'email', 'in_app', 'sla_alerts'
    if (channelKey && tr[channelKey + '_locked'] !== undefined) {
      return tr[channelKey + '_locked']
    }
  }
  return !channels[col.masterKey]
}

/**
 * Check if ANY trigger in the column is locked (for header icon).
 */
function isColumnLockedGlobal(col) {
  if (!col.masterKey) return false
  return !channels[col.masterKey]
}

/**
 * Map trigger column keys → channel API identifiers.
 */
const colToChannel = {
  email_enabled: 'email',
  in_app_enabled: 'in_app',
  email_alert_enabled: 'sla_alerts',
}

async function toggleTriggerField(tr, field) {
  // Block if the master channel for this column is disabled
  const col = triggerCols.find(c => c.key === field)
  if (col && isColumnLocked(col, tr)) return

  const sk = `${tr.id}_${field}`
  if (triggerSaving[sk]) return
  const old = tr[field]
  tr[field] = !old
  triggerSaving[sk] = true

  try {
    const channel = colToChannel[field]
    if (channel) {
      // Use per-user preference endpoint
      await api.put(`/notification-triggers/${tr.id}/${channel}`, { enabled: tr[field] })
    } else {
      // For non-channel fields (like is_active), use the admin PATCH
      await api.patch(`/notification-triggers/${tr.id}`, { [field]: tr[field] })
    }
    toast('success', 'Trigger preference saved.')
  } catch (e) {
    tr[field] = old
    const msg = e?.response?.data?.message || 'Failed to update trigger.'
    toast('error', msg)
  } finally { delete triggerSaving[sk] }
}

/**
 * Reset all user preferences for a channel back to system defaults.
 */
const channelResetting = reactive({})
async function resetChannelDefaults(channelKey) {
  if (channelResetting[channelKey]) return
  if (!confirm(`Reset all your ${channelKey.replace('_', ' ')} trigger preferences to system defaults?`)) return
  channelResetting[channelKey] = true
  try {
    await api.post(`/notification-triggers/${channelKey}/reset`)
    await refetchTriggers()
    toast('success', `Preferences reset to defaults for ${channelKey.replace('_', ' ')} channel.`)
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to reset preferences.')
  } finally { delete channelResetting[channelKey] }
}

// ═══════════════════════════════════════════════════════════
//  § 4 – Escalation Levels (role-based system)
// ═══════════════════════════════════════════════════════════
const escalationLevels    = ref([])
const escalationLoading   = ref(true)
const escalationSaving    = ref(false)
const escalationCanUpdate = ref(false)
// System roles fetched from backend for dropdown
const systemRoles         = ref([])

async function loadEscalationLevels() {
  escalationLoading.value = true
  try {
    const { data } = await api.get('/escalation-levels')
    escalationLevels.value = data?.data ?? []
    systemRoles.value      = data?.meta?.roles ?? []
    escalationCanUpdate.value = data?.meta?.can_update ?? false
  } catch { /* ignore */ }
  finally { escalationLoading.value = false }
}

async function addEscalationLevel() {
  if (escalationSaving.value) return
  escalationSaving.value = true
  try {
    // Default to first available role or 'superadmin'
    const defaultRole = systemRoles.value.length ? systemRoles.value[0].value : 'superadmin'
    const { data } = await api.post('/escalation-levels', {
      recipient_type: defaultRole,
      is_active: true,
    })
    escalationLevels.value.push(data.data)
    toast('success', 'Escalation level added.')
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to add level.')
  } finally { escalationSaving.value = false }
}

async function updateEscalationLevel(level) {
  if (escalationSaving.value) return
  escalationSaving.value = true
  try {
    const { data } = await api.put(`/escalation-levels/${level.id}`, {
      recipient_type: level.recipient_type,
      custom_email:   level.custom_email || null,
      is_active:      level.is_active,
    })
    const idx = escalationLevels.value.findIndex(l => l.id === level.id)
    if (idx !== -1) escalationLevels.value[idx] = data.data
    toast('success', 'Escalation level updated.')
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to update level.')
  } finally { escalationSaving.value = false }
}

// Delete confirmation modal
const showDeleteLevelModal = ref(false)
const deleteLevelTarget    = ref(null)

function confirmDeleteLevel(level) {
  deleteLevelTarget.value = level
  showDeleteLevelModal.value = true
}

function cancelDeleteLevel() {
  showDeleteLevelModal.value = false
  deleteLevelTarget.value = null
}

async function executeDeleteLevel() {
  const level = deleteLevelTarget.value
  if (!level) return
  showDeleteLevelModal.value = false
  try {
    await api.delete(`/escalation-levels/${level.id}`)
    escalationLevels.value = escalationLevels.value.filter(l => l.id !== level.id)
    escalationLevels.value.forEach((l, i) => { l.level = i + 1 })
    toast('success', 'Escalation level removed successfully.')
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to remove level.')
  }
  deleteLevelTarget.value = null
}

async function toggleEscalationActive(level) {
  const old = level.is_active
  level.is_active = !old
  try {
    await api.put(`/escalation-levels/${level.id}`, {
      recipient_type: level.recipient_type,
      custom_email:   level.custom_email || null,
      is_active:      level.is_active,
    })
  } catch {
    level.is_active = old
    toast('error', 'Failed to toggle status.')
  }
}

async function onEscalationDragEnd() {
  const order = escalationLevels.value.map(l => l.id)
  escalationLevels.value.forEach((l, i) => { l.level = i + 1 })
  try {
    const { data } = await api.put('/escalation-levels/reorder', { order })
    escalationLevels.value = data?.data ?? escalationLevels.value
    toast('success', 'Escalation levels reordered.')
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to reorder.')
    loadEscalationLevels()
  }
}

// Editing state per-row — clicking Edit makes all fields in that row editable
const editingLevelId    = ref(null)
const editingLevelDraft = reactive({})

function startEditLevel(level) {
  editingLevelId.value = level.id
  Object.assign(editingLevelDraft, {
    recipient_type: level.recipient_type,
    custom_email:   level.custom_email || '',
    is_active:      level.is_active,
  })
}

function cancelEditLevel() {
  editingLevelId.value = null
  Object.keys(editingLevelDraft).forEach(k => delete editingLevelDraft[k])
}

async function saveEditLevel(level) {
  // Validate email if provided
  if (editingLevelDraft.custom_email && !isValidEmail(editingLevelDraft.custom_email)) {
    toast('error', 'Please enter a valid email address.')
    return
  }
  Object.assign(level, {
    recipient_type: editingLevelDraft.recipient_type,
    custom_email:   editingLevelDraft.custom_email || null,
    is_active:      editingLevelDraft.is_active,
  })
  await updateEscalationLevel(level)
  editingLevelId.value = null
}

// Email validation helper
function isValidEmail(email) {
  if (!email) return true
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
}

// Get human-readable label for a role slug
function getRoleLabel(slug) {
  const found = systemRoles.value.find(r => r.value === slug)
  return found ? found.label : (slug ? slug.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : '—')
}

// ═══════════════════════════════════════════════════════════
//  § 5 – Templates
// ═══════════════════════════════════════════════════════════
const templates          = ref([])
const showTplModal       = ref(false)
const selectedTemplateId = ref(null)
const tplCanUpdate       = ref(false)
const availableTriggers  = ref([])

// Inline editing state for template rows
const editingTplId    = ref(null)
const editingTplDraft = reactive({})

function openCreateTemplate() {
  selectedTemplateId.value = null
  showTplModal.value = true
}
function openTemplate(id) { selectedTemplateId.value = id; showTplModal.value = true }

function onTemplateSaved(saved) {
  showTplModal.value = false
  const idx = templates.value.findIndex(t => t.id === saved.id)
  if (idx !== -1) templates.value[idx] = { ...templates.value[idx], ...saved }
  toast('success', 'Template updated.')
}

function onTemplateCreated(created) {
  showTplModal.value = false
  templates.value.push(created)
  toast('success', 'Email template created successfully.')
}

// Start inline editing on double-click
function startEditTemplate(tpl) {
  if (!tplCanUpdate.value) return
  editingTplId.value = tpl.id
  Object.assign(editingTplDraft, {
    name:    tpl.name || '',
    subject: tpl.subject || '',
  })
}

function cancelEditTemplate() {
  editingTplId.value = null
  Object.keys(editingTplDraft).forEach(k => delete editingTplDraft[k])
}

async function saveEditTemplate(tpl) {
  if (!editingTplDraft.name?.trim()) {
    toast('error', 'Template name is required.')
    return
  }
  if (!editingTplDraft.subject?.trim()) {
    toast('error', 'Subject is required.')
    return
  }
  try {
    const { data } = await api.put(`/email-templates/${tpl.id}`, {
      name:    editingTplDraft.name,
      subject: editingTplDraft.subject,
    })
    const idx = templates.value.findIndex(t => t.id === tpl.id)
    if (idx !== -1) {
      templates.value[idx] = { ...templates.value[idx], ...data.data }
    }
    toast('success', 'Template updated.')
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to update template.')
  }
  editingTplId.value = null
}

// Delete template
const showDeleteTplModal = ref(false)
const deleteTplTarget    = ref(null)

function confirmDeleteTemplate(tpl) {
  deleteTplTarget.value = tpl
  showDeleteTplModal.value = true
}

function cancelDeleteTemplate() {
  showDeleteTplModal.value = false
  deleteTplTarget.value = null
}

async function executeDeleteTemplate() {
  const tpl = deleteTplTarget.value
  if (!tpl) return
  showDeleteTplModal.value = false
  try {
    await api.delete(`/email-templates/${tpl.id}`)
    templates.value = templates.value.filter(t => t.id !== tpl.id)
    toast('success', 'Email template deleted.')
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to delete template.')
  }
  deleteTplTarget.value = null
}

// ═══════════════════════════════════════════════════════════
//  § 6 – Logs
// ═══════════════════════════════════════════════════════════
const logs       = ref([])
const logsMeta   = ref({ last_page: 1, total: 0 })
const logsPage   = ref(1)
const logsPerPage = ref(10)

async function fetchLogs(page = 1) {
  logsLoading.value = true
  try {
    const { data } = await api.get('/notification-logs', { params: { page, per_page: logsPerPage.value } })
    logs.value     = data.data ?? []
    logsMeta.value = { last_page: data.last_page, total: data.total, current_page: data.current_page }
    logsPage.value = data.current_page
  } catch { /* ignore */ }
  finally { logsLoading.value = false }
}

// ═══════════════════════════════════════════════════════════
//  § 7 – Test notification
// ═══════════════════════════════════════════════════════════
const testEmail   = ref('')
const testTrigger = ref('')
const testSending = ref(false)

async function sendTest() {
  if (!canSendTest.value || testSending.value || !testEmail.value || !testTrigger.value) return
  testSending.value = true
  try {
    const { data } = await api.post('/notification-test', { trigger_key: testTrigger.value, email: testEmail.value })
    toast('success', data?.message || 'Test sent!')
    fetchLogs(1) // refresh log
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to send test.')
  } finally { testSending.value = false }
}

// ═══════════════════════════════════════════════════════════
//  Load all
// ═══════════════════════════════════════════════════════════
async function loadConfig() {
  configLoading.value = true
  try {
    const { data } = await api.get('/notification-config')

    // Granular permissions from backend
    const meta = data?.meta ?? {}
    canUpdate.value           = meta.can_update ?? false
    canEditSettings.value     = meta.can_edit_settings ?? false
    canManageChannels.value   = meta.can_manage_channels ?? false
    canManageTriggers.value   = meta.can_manage_triggers ?? false
    canManageEscalations.value = meta.can_manage_escalations ?? false
    canManageTemplates.value  = meta.can_manage_templates ?? false
    canViewLogs.value         = meta.can_view_logs ?? false
    canSendTest.value         = meta.can_send_test ?? false
    canDelete.value           = meta.can_delete ?? false

    const s = data?.data?.settings ?? {}
    emailForm.default_sender_email = s.default_sender_email ?? ''
    emailForm.cc_emails  = (s.cc_emails ?? []).join(', ')
    emailForm.bcc_emails = (s.bcc_emails ?? []).join(', ')
    takeEmailSnapshot()

    // Sync channel state from dedicated channels object (or fallback to settings)
    const ch = data?.data?.channels ?? {}
    channels.enable_email      = ch.enable_email ?? s.enable_email ?? true
    channels.enable_web        = ch.enable_web ?? s.enable_web ?? true
    channels.enable_sms        = s.enable_sms ?? false
    channels.enable_sla_alerts = ch.enable_sla_alerts ?? s.enable_sla_alerts ?? true

    // Triggers come pre-resolved per-user with lock flags from backend
    triggers.value = data?.data?.triggers ?? []

    // Escalation levels are now loaded separately via loadEscalationLevels()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to load config.')
  } finally { configLoading.value = false }
}

async function loadTemplates() {
  templatesLoading.value = true
  try {
    const { data } = await api.get('/email-templates')
    templates.value      = data?.data ?? []
    tplCanUpdate.value   = data?.meta?.can_update ?? false
    availableTriggers.value = data?.meta?.triggers ?? []
  } catch { /* ignore */ }
  finally { templatesLoading.value = false }
}

// ═══════════════════════════════════════════════════════════
//  Save (bottom button) → settings + escalations
// ═══════════════════════════════════════════════════════════
const anySaving = computed(() => emailSaving.value)
const anyDirty  = computed(() => emailDirty.value)

async function saveAll() {
  if (!canEditSettings.value || anySaving.value) return

  let ok = true

  // Save email settings if dirty
  if (emailDirty.value) {
    emailSaving.value = true
    Object.keys(emailErrors).forEach(k => delete emailErrors[k])
    try {
      await api.put('/notification-settings', { ...emailForm })
      takeEmailSnapshot()
    } catch (e) {
      ok = false
      if (e?.response?.status === 422) {
        const fe = e.response.data?.errors ?? {}
        Object.keys(fe).forEach(k => { emailErrors[k] = Array.isArray(fe[k]) ? fe[k].join(' ') : fe[k] })
      }
      toast('error', e?.response?.data?.message || 'Failed to save email settings.')
    } finally { emailSaving.value = false }
  }

  if (ok) toast('success', 'All changes saved successfully!')
}

// ═══════════════════════════════════════════════════════════
//  Unsaved changes guard
// ═══════════════════════════════════════════════════════════
onBeforeRouteLeave((to, from, next) => {
  if (anyDirty.value && !confirm('You have unsaved changes. Leave anyway?')) next(false)
  else next()
})
// Only attach beforeunload AFTER user interacts — Chrome blocks the
// confirmation dialog for frames that never received a user gesture.
function beforeUnloadHandler(e) {
  if (anyDirty.value) { e.preventDefault(); e.returnValue = '' }
}
let beforeUnloadAttached = false
function attachBeforeUnload() {
  if (!beforeUnloadAttached) {
    window.addEventListener('beforeunload', beforeUnloadHandler)
    beforeUnloadAttached = true
  }
}

onMounted(() => {
  loadConfig()
  loadEscalationLevels()
  loadTemplates()
  fetchLogs(1)
  // Defer beforeunload registration until first user gesture
  window.addEventListener('click', attachBeforeUnload, { once: true })
  window.addEventListener('keydown', attachBeforeUnload, { once: true })
})
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', beforeUnloadHandler)
  window.removeEventListener('click', attachBeforeUnload)
  window.removeEventListener('keydown', attachBeforeUnload)
  beforeUnloadAttached = false
})

// ═══════════════════════════════════════════════════════════
//  Helpers
// ═══════════════════════════════════════════════════════════
function fmtDate(iso) {
  if (!iso) return '—'
  const d = new Date(iso)
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
    ', ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}
function triggerLabel(key) {
  return (key ?? '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}
function statusClass(s) {
  if (s === 'sent')   return 'bg-green-100 text-green-800'
  if (s === 'failed') return 'bg-red-100 text-red-800'
  return 'bg-yellow-100 text-yellow-800'
}
function channelIcon(ch) {
  if (ch === 'email') return '📧'
  if (ch === 'web')   return '🔔'
  if (ch === 'sms')   return '💬'
  return '📧'
}
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="toastType === 'error' ? 5000 : 3000" @dismiss="dismissToast" />

    <!-- ═══ Header ═══ -->
    <div>
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-gray-900">Notifications & Email Rules</h1>
        <Breadcrumbs />
        <span v-if="!configLoading && !canEditSettings && !canManageChannels" class="inline-flex items-center gap-1.5 rounded-lg bg-green-50 border border-green-200 px-3 py-1.5 text-xs font-semibold text-green-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
          Super Admin Only
        </span>
      </div>
      <p class="mt-1 text-sm text-gray-500">Configure email notifications, SLA breach alerts, and notification preferences.</p>
    </div>

    <!-- ═══ § 1 Global Email Settings ═══ -->
    <section class="rounded-xl border border-gray-400 bg-white shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-400">
        <h2 class="text-base font-semibold text-gray-900">Global Email Settings</h2>
        <p class="text-sm text-gray-500 mt-0.5">Configure default email addresses for system notifications.</p>
      </div>
      <div v-if="configLoading" class="p-6 space-y-5">
        <div v-for="i in 3" :key="i" class="flex items-center justify-between"><SkeletonBox width="120px" height="14px" /><SkeletonBox width="55%" height="40px" /></div>
      </div>
      <div v-else class="divide-y divide-gray-300">
        <!-- Sender -->
        <div class="px-6 py-5 flex flex-wrap items-start gap-4">
          <div class="min-w-[160px] max-w-[220px]">
            <label for="sender" class="text-sm font-medium text-gray-900">Default Notification Email</label>
            <p class="text-xs text-gray-500 mt-0.5">Primary email for all system notifications.</p>
            <p v-if="emailErrors.default_sender_email" class="text-xs text-red-600 mt-1">{{ emailErrors.default_sender_email }}</p>
          </div>
          <input id="sender" v-model="emailForm.default_sender_email" type="email" :disabled="!canEditSettings" class="flex-1 min-w-[200px] rounded-lg border border-gray-300 px-3 py-2.5 text-sm disabled:bg-gray-100 disabled:opacity-70" placeholder="order@astonhill.ae" />
        </div>
        <!-- CC -->
        <div class="px-6 py-5 flex flex-wrap items-start gap-4">
          <div class="min-w-[160px] max-w-[220px]">
            <label for="cc" class="text-sm font-medium text-gray-900">CC Emails</label>
            <p class="text-xs text-gray-500 mt-0.5">Carbon copy recipients, separated by commas.</p>
            <p v-if="emailErrors.cc_emails" class="text-xs text-red-600 mt-1">{{ emailErrors.cc_emails }}</p>
          </div>
          <input id="cc" v-model="emailForm.cc_emails" type="text" :disabled="!canEditSettings" class="flex-1 min-w-[200px] rounded-lg border border-gray-300 px-3 py-2.5 text-sm disabled:bg-gray-100 disabled:opacity-70" placeholder="operations@astonhill.ae, management@astonhill.ae" />
        </div>
        <!-- BCC -->
        <div class="px-6 py-5 flex flex-wrap items-start gap-4">
          <div class="min-w-[160px] max-w-[220px]">
            <label for="bcc" class="text-sm font-medium text-gray-900">BCC Emails</label>
            <p class="text-xs text-gray-500 mt-0.5">Blind carbon copy recipients, separated by commas.</p>
            <p v-if="emailErrors.bcc_emails" class="text-xs text-red-600 mt-1">{{ emailErrors.bcc_emails }}</p>
          </div>
          <input id="bcc" v-model="emailForm.bcc_emails" type="text" :disabled="!canEditSettings" class="flex-1 min-w-[200px] rounded-lg border border-gray-300 px-3 py-2.5 text-sm disabled:bg-gray-100 disabled:opacity-70" placeholder="email1@example.com, email2@example.com" />
        </div>
      </div>
    </section>

    <!-- ═══ § 2 Notification Channels ═══ -->
    <section class="rounded-xl border border-gray-400 bg-white shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-400">
        <h2 class="text-base font-semibold text-gray-900">Notification Channels</h2>
        <p class="text-sm text-gray-500 mt-0.5">Enable or disable notification channels globally.</p>
      </div>
      <div v-if="configLoading" class="p-6 space-y-5">
        <div v-for="i in 3" :key="i" class="flex items-center justify-between"><SkeletonBox width="180px" height="14px" /><SkeletonBox width="52px" height="28px" class="rounded-full" /></div>
      </div>
      <div v-else class="divide-y divide-gray-300">
        <div v-for="ch in channelMeta" :key="ch.key" class="px-6 py-5 flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-sm font-medium text-gray-900">{{ ch.label }}</p>
            <p class="text-xs text-gray-500 mt-0.5">{{ ch.desc }}</p>
          </div>
          <div class="flex items-center gap-3">
            <button
              type="button" role="switch" :aria-checked="channels[ch.key]" :aria-label="ch.label"
              :disabled="!canManageChannels || !!channelSaving[ch.key]"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
              :class="channels[ch.key] ? 'bg-blue-600' : 'bg-gray-300'"
              @click="toggleChannel(ch.key)"
            >
              <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform" :class="channels[ch.key] ? 'translate-x-6' : 'translate-x-1'" />
            </button>
            <span class="text-sm" :class="channels[ch.key] ? 'text-blue-700 font-medium' : 'text-gray-500'">{{ channels[ch.key] ? 'Enabled' : 'Disabled' }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══ § 3 Notification Triggers ═══ -->
    <section class="rounded-xl border border-gray-400 bg-white shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-400">
        <h2 class="text-base font-semibold text-gray-900">Event-Based Notifications</h2>
        <p class="text-sm text-gray-500 mt-0.5">Choose which events send notifications. Disabled channels lock their toggles.</p>
      </div>
      <div v-if="configLoading" class="p-6 space-y-4">
        <div v-for="i in 8" :key="i" class="flex items-center gap-6"><SkeletonBox width="200px" height="14px" /><SkeletonBox v-for="j in 5" :key="j" width="44px" height="24px" class="rounded-full" /></div>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-gray-400 bg-gray-50 text-left">
              <th class="px-6 py-3 font-semibold text-gray-700 whitespace-nowrap">Event / Trigger</th>
              <th class="px-4 py-3 font-semibold text-gray-700 whitespace-nowrap">Module</th>
              <th v-for="col in triggerCols" :key="col.key" class="px-4 py-3 font-semibold text-gray-700 text-center whitespace-nowrap">
                <span class="inline-flex items-center gap-1">
                  {{ col.label }}
                  <svg v-if="isColumnLockedGlobal(col)" class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" :title="col.label + ' channel is disabled globally'">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </span>
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-300">
            <tr v-for="tr in triggers" :key="tr.id" class="hover:bg-gray-50/50 transition-colors">
              <td class="px-6 py-3.5 font-medium text-gray-900 whitespace-nowrap">{{ tr.name }}</td>
              <td class="px-4 py-3.5 text-gray-600 whitespace-nowrap">{{ tr.module ?? '—' }}</td>
              <td v-for="col in triggerCols" :key="col.key" class="px-4 py-3.5 text-center">
                <!-- When master channel is OFF: show forced-off toggle with lock tooltip -->
                <div v-if="isColumnLocked(col, tr)" class="inline-flex items-center gap-1" :title="col.label + ' disabled at channel level — contact your administrator'">
                  <button
                    type="button" role="switch" aria-checked="false"
                    disabled
                    class="relative inline-flex h-5 w-9 items-center rounded-full bg-gray-200 opacity-40 cursor-not-allowed"
                  >
                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm translate-x-[3px]" />
                  </button>
                  <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </div>
                <!-- When master channel is ON: normal toggle behavior -->
                <button
                  v-else
                  type="button" role="switch" :aria-checked="tr[col.key]"
                  :disabled="!!triggerSaving[`${tr.id}_${col.key}`]"
                  class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-40 disabled:cursor-not-allowed"
                  :class="tr[col.key]
                    ? (col.key === 'email_alert_enabled' ? 'bg-amber-500 focus:ring-amber-400' : 'bg-green-500 focus:ring-green-400')
                    : 'bg-gray-300 focus:ring-gray-400'"
                  @click="toggleTriggerField(tr, col.key)"
                >
                  <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform" :class="tr[col.key] ? 'translate-x-[18px]' : 'translate-x-[3px]'" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <!-- Reset to Defaults per channel -->
        <div class="px-6 py-3 border-t border-gray-200 bg-gray-50/50 flex flex-wrap items-center gap-3 text-xs">
          <span class="text-gray-500 font-medium">Reset preferences to defaults:</span>
          <button
            v-for="col in triggerCols.filter(c => c.channelKey)"
            :key="col.channelKey"
            type="button"
            :disabled="!!channelResetting[col.channelKey]"
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            @click="resetChannelDefaults(col.channelKey)"
          >
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
            {{ col.label }}
          </button>
        </div>
      </div>
    </section>

    <!-- ═══ § 4 Escalation Levels ═══ -->
    <section class="rounded-xl border border-gray-400 bg-white shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-400 flex items-center justify-between">
        <div>
          <h2 class="text-base font-semibold text-gray-900">Escalation Levels</h2>
          <p class="text-sm text-gray-500 mt-0.5">Configure SLA breach escalation recipients in order. Drag rows to reorder.</p>
        </div>
        <div v-if="escalationCanUpdate" class="flex items-center gap-2">
          <button type="button" class="text-sm font-medium text-blue-600 hover:text-blue-700" :disabled="escalationSaving" @click="addEscalationLevel">+ Add Level</button>
        </div>
      </div>

      <!-- Loading skeleton -->
      <div v-if="escalationLoading" class="p-6 space-y-3">
        <div v-for="i in 3" :key="i" class="flex items-center gap-4">
          <SkeletonBox width="40px" height="20px" />
          <SkeletonBox width="160px" height="34px" />
          <SkeletonBox width="200px" height="34px" />
          <SkeletonBox width="44px" height="24px" class="rounded-full" />
        </div>
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-gray-300 bg-gray-50 text-left">
              <th class="w-10 px-3 py-3"></th>
              <th class="px-4 py-3 font-semibold text-gray-700 whitespace-nowrap">Level</th>
              <th class="px-4 py-3 font-semibold text-gray-700 whitespace-nowrap">Recipient Type</th>
              <th class="px-4 py-3 font-semibold text-gray-700 whitespace-nowrap">Custom User / Email</th>
              <th class="px-4 py-3 font-semibold text-gray-700 text-center whitespace-nowrap">Status</th>
              <th class="px-4 py-3 font-semibold text-gray-700 text-center whitespace-nowrap">Actions</th>
            </tr>
          </thead>
          <draggable
            v-model="escalationLevels"
            tag="tbody"
            item-key="id"
            handle=".drag-handle"
            ghost-class="bg-blue-50"
            :disabled="!escalationCanUpdate"
            class="divide-y divide-gray-200"
            @end="onEscalationDragEnd"
          >
            <template #item="{ element: lvl }">
              <tr class="hover:bg-gray-50/50 transition-colors group">
                <!-- Drag handle -->
                <td class="px-3 py-3">
                  <span v-if="escalationCanUpdate" class="drag-handle cursor-grab text-gray-300 hover:text-gray-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                  </span>
                </td>

                <!-- Level number -->
                <td class="px-4 py-3">
                  <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-blue-100 text-blue-700 text-xs font-bold">{{ lvl.level }}</span>
                </td>

                <!-- Recipient Type (shows roles from the system) -->
                <td class="px-4 py-3">
                  <template v-if="editingLevelId === lvl.id">
                    <select
                      v-model="editingLevelDraft.recipient_type"
                      class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm w-full max-w-[200px]"
                    >
                      <option v-for="role in systemRoles" :key="role.value" :value="role.value">{{ role.label }}</option>
                    </select>
                  </template>
                  <template v-else>
                    <span class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-800">
                      <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                      {{ getRoleLabel(lvl.recipient_type) }}
                    </span>
                  </template>
                </td>

                <!-- Custom User / Email (editable on double-click, email-only) -->
                <td class="px-4 py-3" @dblclick="escalationCanUpdate && editingLevelId !== lvl.id && startEditLevel(lvl)">
                  <template v-if="editingLevelId === lvl.id">
                    <input
                      v-model="editingLevelDraft.custom_email"
                      type="email"
                      placeholder="Enter email address..."
                      class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm w-full max-w-[240px]"
                      :class="{ 'border-red-400': editingLevelDraft.custom_email && !isValidEmail(editingLevelDraft.custom_email) }"
                    />
                    <p v-if="editingLevelDraft.custom_email && !isValidEmail(editingLevelDraft.custom_email)" class="text-xs text-red-500 mt-0.5">Please enter a valid email</p>
                  </template>
                  <template v-else>
                    <span v-if="lvl.custom_email" class="text-sm text-gray-700 font-mono cursor-pointer" :title="'Double-click to edit'">{{ lvl.custom_email }}</span>
                    <span v-else class="text-xs text-gray-400 italic cursor-pointer" :title="'Double-click to add email'">—</span>
                  </template>
                </td>

                <!-- Status toggle -->
                <td class="px-4 py-3 text-center">
                  <template v-if="editingLevelId === lvl.id">
                    <button
                      type="button" role="switch" :aria-checked="editingLevelDraft.is_active"
                      class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1"
                      :class="editingLevelDraft.is_active ? 'bg-green-500 focus:ring-green-400' : 'bg-gray-300 focus:ring-gray-400'"
                      @click="editingLevelDraft.is_active = !editingLevelDraft.is_active"
                    >
                      <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform" :class="editingLevelDraft.is_active ? 'translate-x-[18px]' : 'translate-x-[3px]'" />
                    </button>
                  </template>
                  <template v-else>
                    <button
                      type="button" role="switch" :aria-checked="lvl.is_active"
                      :disabled="!escalationCanUpdate"
                      class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-40 disabled:cursor-not-allowed"
                      :class="lvl.is_active ? 'bg-green-500 focus:ring-green-400' : 'bg-gray-300 focus:ring-gray-400'"
                      @click="toggleEscalationActive(lvl)"
                    >
                      <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform" :class="lvl.is_active ? 'translate-x-[18px]' : 'translate-x-[3px]'" />
                    </button>
                  </template>
                </td>

                <!-- Actions -->
                <td class="px-4 py-3 text-center">
                  <div v-if="escalationCanUpdate" class="flex items-center justify-center gap-1">
                    <template v-if="editingLevelId === lvl.id">
                      <!-- Save -->
                      <button type="button" class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-green-600 text-white text-xs font-medium hover:bg-green-700 transition-colors" title="Save" :disabled="escalationSaving" @click="saveEditLevel(lvl)">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        Save
                      </button>
                      <!-- Cancel -->
                      <button type="button" class="inline-flex items-center gap-1 px-2.5 py-1 rounded border border-gray-300 text-gray-600 text-xs font-medium hover:bg-gray-50 transition-colors" title="Cancel" @click="cancelEditLevel">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        Cancel
                      </button>
                    </template>
                    <template v-else>
                      <!-- Edit -->
                      <button type="button" class="p-1.5 text-teal-500 hover:bg-teal-50 rounded transition-colors" title="Edit" @click="startEditLevel(lvl)">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z" /><path d="M4 24h16" /></svg>
                      </button>
                      <!-- Delete -->
                      <button type="button" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Delete" @click="confirmDeleteLevel(lvl)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                      </button>
                    </template>
                  </div>
                </td>
              </tr>
            </template>
          </draggable>
        </table>
        <div v-if="!escalationLevels.length" class="px-6 py-6 text-center text-sm text-gray-400">
          No escalation levels configured. Click "+ Add Level" to create one.
        </div>
      </div>

      <!-- Info footer -->
      <div class="px-6 py-3 border-t border-gray-200 bg-gray-50/60 text-xs text-gray-500 space-y-1">
        <p><strong>How it works:</strong> When an SLA breach is detected, the system sends notifications to each active level in order.</p>
        <p><strong>Custom Email:</strong> If set, escalation email goes to this address instead of all users with the selected role.</p>
        <p><strong>Status:</strong> Toggle to enable or disable email notifications for each escalation level.</p>
        <p><strong>Disabled when:</strong> Escalation levels are ignored when the SLA Alerts channel is disabled in Notification Channels above.</p>
      </div>
    </section>

    <!-- ═══ Delete Escalation Level Confirmation Modal ═══ -->
    <Teleport to="body">
      <div v-if="showDeleteLevelModal" class="fixed inset-0 z-[70] flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="cancelDeleteLevel"></div>
        <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
          <!-- Header -->
          <div class="px-6 py-4 border-b border-gray-200 bg-red-50">
            <div class="flex items-center gap-3">
              <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-900">Delete Escalation Level</h3>
                <p class="text-sm text-gray-500">This action cannot be undone</p>
              </div>
            </div>
          </div>
          <!-- Body -->
          <div class="px-6 py-5">
            <p class="text-sm text-gray-700">
              Are you sure you want to delete <strong>Escalation Level {{ deleteLevelTarget?.level }}</strong>
              <span v-if="deleteLevelTarget?.recipient_type">({{ getRoleLabel(deleteLevelTarget?.recipient_type) }})</span>?
            </p>
            <p class="text-sm text-gray-500 mt-2">
              This will permanently remove this escalation level and any associated notification routing. Remaining levels will be re-sequenced automatically.
            </p>
          </div>
          <!-- Footer -->
          <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex items-center justify-end gap-3">
            <button type="button" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors" @click="cancelDeleteLevel">Cancel</button>
            <button type="button" class="px-4 py-2 rounded-lg bg-red-600 text-sm font-medium text-white hover:bg-red-700 transition-colors" @click="executeDeleteLevel">Delete Level</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ═══ § 5 Email Template Management ═══ -->
    <section class="rounded-xl border border-gray-400 bg-white shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-400 flex items-center justify-between">
        <div>
          <h2 class="text-base font-semibold text-gray-900">Email Template Management</h2>
          <p class="text-sm text-gray-500 mt-0.5">View and customize email templates used by notifications. Double-click a column to edit inline.</p>
        </div>
        <button
          v-if="tplCanUpdate"
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
          @click="openCreateTemplate"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
          Add Template
        </button>
      </div>
      <div v-if="templatesLoading" class="p-6 space-y-4">
        <div v-for="i in 5" :key="i" class="flex items-center gap-6"><SkeletonBox width="200px" height="14px" /><SkeletonBox width="140px" height="14px" /><SkeletonBox width="220px" height="14px" /><SkeletonBox width="100px" height="14px" /><SkeletonBox width="50px" height="28px" /></div>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-gray-300 bg-gray-50 text-left">
              <th class="px-6 py-3 font-semibold text-gray-700">Template Name</th>
              <th class="px-6 py-3 font-semibold text-gray-700">Trigger</th>
              <th class="px-6 py-3 font-semibold text-gray-700">Subject</th>
              <th class="px-6 py-3 font-semibold text-gray-700">Last Updated</th>
              <th class="px-6 py-3 font-semibold text-gray-700 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="tpl in templates" :key="tpl.id" class="hover:bg-gray-50/50 transition-colors group">
              <!-- Template Name (editable on double-click) -->
              <td class="px-6 py-3.5" @dblclick="startEditTemplate(tpl)">
                <template v-if="editingTplId === tpl.id">
                  <input
                    v-model="editingTplDraft.name"
                    type="text"
                    class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm w-full max-w-[220px]"
                    @keyup.enter="saveEditTemplate(tpl)"
                    @keyup.escape="cancelEditTemplate"
                  />
                </template>
                <template v-else>
                  <span class="font-medium text-gray-900 cursor-pointer" :title="tplCanUpdate ? 'Double-click to edit' : ''">{{ tpl.name }}</span>
                </template>
              </td>

              <!-- Trigger (read-only) -->
              <td class="px-6 py-3.5 text-gray-600 whitespace-nowrap">{{ triggerLabel(tpl.trigger_key) }}</td>

              <!-- Subject (editable on double-click) -->
              <td class="px-6 py-3.5" @dblclick="startEditTemplate(tpl)">
                <template v-if="editingTplId === tpl.id">
                  <input
                    v-model="editingTplDraft.subject"
                    type="text"
                    class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm w-full max-w-[280px]"
                    @keyup.enter="saveEditTemplate(tpl)"
                    @keyup.escape="cancelEditTemplate"
                  />
                </template>
                <template v-else>
                  <span class="text-gray-600 max-w-[260px] truncate block cursor-pointer" :title="tplCanUpdate ? tpl.subject + ' — Double-click to edit' : tpl.subject">{{ tpl.subject }}</span>
                </template>
              </td>

              <!-- Last Updated (read-only) -->
              <td class="px-6 py-3.5 text-gray-500 whitespace-nowrap text-xs">{{ fmtDate(tpl.updated_at) }}</td>

              <!-- Actions -->
              <td class="px-6 py-3.5 text-center">
                <div class="flex items-center justify-center gap-1">
                  <template v-if="editingTplId === tpl.id">
                    <!-- Save -->
                    <button type="button" class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-green-600 text-white text-xs font-medium hover:bg-green-700 transition-colors" @click="saveEditTemplate(tpl)">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                      Save
                    </button>
                    <!-- Cancel -->
                    <button type="button" class="inline-flex items-center gap-1 px-2.5 py-1 rounded border border-gray-300 text-gray-600 text-xs font-medium hover:bg-gray-50 transition-colors" @click="cancelEditTemplate">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                      Cancel
                    </button>
                  </template>
                  <template v-else>
                    <!-- View/Edit in modal (for full body editing) -->
                    <button type="button" class="p-1.5 text-blue-500 hover:bg-blue-50 rounded transition-colors" title="View / Edit Body" @click="openTemplate(tpl.id)">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    </button>
                    <!-- Edit inline -->
                    <button v-if="tplCanUpdate" type="button" class="p-1.5 text-teal-500 hover:bg-teal-50 rounded transition-colors" title="Edit" @click="startEditTemplate(tpl)">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z" /><path d="M4 24h16" /></svg>
                    </button>
                    <!-- Delete -->
                    <button v-if="tplCanUpdate" type="button" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Delete" @click="confirmDeleteTemplate(tpl)">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                  </template>
                </div>
              </td>
            </tr>
            <tr v-if="!templates.length"><td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400">No email templates found. Click "Add Template" to create one.</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ═══ Delete Template Confirmation Modal ═══ -->
    <Teleport to="body">
      <div v-if="showDeleteTplModal" class="fixed inset-0 z-[70] flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="cancelDeleteTemplate"></div>
        <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200 bg-red-50">
            <div class="flex items-center gap-3">
              <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-900">Delete Email Template</h3>
                <p class="text-sm text-gray-500">This action cannot be undone</p>
              </div>
            </div>
          </div>
          <div class="px-6 py-5">
            <p class="text-sm text-gray-700">
              Are you sure you want to delete the email template <strong>"{{ deleteTplTarget?.name }}"</strong>?
            </p>
            <p class="text-sm text-gray-500 mt-2">
              This will permanently remove this template. The associated trigger event will no longer have a custom email template.
            </p>
          </div>
          <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex items-center justify-end gap-3">
            <button type="button" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors" @click="cancelDeleteTemplate">Cancel</button>
            <button type="button" class="px-4 py-2 rounded-lg bg-red-600 text-sm font-medium text-white hover:bg-red-700 transition-colors" @click="executeDeleteTemplate">Delete Template</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ═══ § 6 Notification Log (latest 10) ═══ -->
    <section class="rounded-xl border border-gray-400 bg-white shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-400">
        <h2 class="text-base font-semibold text-gray-900">Notification Log</h2>
        <p class="text-sm text-gray-500 mt-0.5">
          {{ canViewLogs ? 'Latest 10 system-wide notification delivery records.' : 'Latest 10 notifications related to you.' }}
        </p>
      </div>
      <div v-if="logsLoading && !logs.length" class="p-6 space-y-3">
        <div v-for="i in 6" :key="i" class="flex items-center gap-6"><SkeletonBox width="140px" height="14px" /><SkeletonBox width="140px" height="14px" /><SkeletonBox width="100px" height="14px" /><SkeletonBox width="160px" height="14px" /><SkeletonBox width="60px" height="14px" /><SkeletonBox width="50px" height="22px" /></div>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-gray-300 bg-gray-50 text-left">
              <th class="px-6 py-3 font-semibold text-gray-700 whitespace-nowrap">Date &amp; Time</th>
              <th class="px-6 py-3 font-semibold text-gray-700 whitespace-nowrap">Trigger</th>
              <th class="px-6 py-3 font-semibold text-gray-700 whitespace-nowrap">Channel</th>
              <th class="px-6 py-3 font-semibold text-gray-700 whitespace-nowrap">Module</th>
              <th class="px-6 py-3 font-semibold text-gray-700 whitespace-nowrap">Sent To</th>
              <th class="px-6 py-3 font-semibold text-gray-700 whitespace-nowrap">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50/50 transition-colors">
              <td class="px-6 py-3 text-gray-600 whitespace-nowrap text-xs">{{ fmtDate(log.created_at) }}</td>
              <td class="px-6 py-3 text-gray-900 whitespace-nowrap">{{ triggerLabel(log.trigger_key) }}</td>
              <td class="px-6 py-3 text-gray-600 whitespace-nowrap">{{ channelIcon(log.channel) }} {{ log.channel?.charAt(0).toUpperCase() + log.channel?.slice(1) }}</td>
              <td class="px-6 py-3 text-gray-600 whitespace-nowrap">{{ log.module ?? '—' }}</td>
              <td class="px-6 py-3 text-gray-600 whitespace-nowrap">{{ log.sent_to }}</td>
              <td class="px-6 py-3">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClass(log.status)">{{ log.status }}</span>
              </td>
            </tr>
            <tr v-if="!logs.length"><td colspan="6" class="px-6 py-8 text-center text-sm text-gray-400">No notification logs found.</td></tr>
          </tbody>
        </table>
        <!-- Footer: total count info -->
        <div v-if="logsMeta.total > 0" class="px-6 py-3 border-t border-gray-100 text-xs text-gray-500">
          Showing {{ logs.length }} of {{ logsMeta.total }} total records
        </div>
      </div>
    </section>

    <!-- ═══ § 7 Test Notification + Save ═══ -->
    <div class="flex flex-wrap items-end justify-between gap-4 pt-2 border-t border-gray-200">
      <!-- Test -->
      <div v-if="canSendTest" class="flex flex-wrap items-end gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Send test email to</label>
          <input v-model="testEmail" type="email" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-52" placeholder="test@example.com" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Trigger</label>
          <select v-model="testTrigger" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-48">
            <option value="" disabled>Select trigger…</option>
            <option v-for="tr in triggers" :key="tr.key" :value="tr.key">{{ tr.name }}</option>
          </select>
        </div>
        <button type="button" :disabled="testSending || !testEmail || !testTrigger" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors" @click="sendTest">
          <svg v-if="testSending" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
          {{ testSending ? 'Sending…' : 'Send Test' }}
        </button>
      </div>
      <div v-else></div>

      <!-- Save / Cancel -->
      <div class="flex items-center gap-3">
        <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="router.push('/settings')">Cancel</button>
        <button
          v-if="canUpdate"
          type="button"
          :disabled="!anyDirty || anySaving"
          class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          @click="saveAll"
        >
          <svg v-if="anySaving" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
          {{ anySaving ? 'Saving…' : 'Save Changes' }}
        </button>
      </div>
    </div>

    <!-- ═══ § 8 Audit & Safety Notes ═══ -->
    <div class="rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 flex gap-3">
      <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
      <div>
        <p class="font-semibold text-blue-900">Audit &amp; Safety</p>
        <ul class="mt-2 space-y-1 text-sm text-blue-800">
          <li>• Only Super Admin can modify notification rules.</li>
          <li>• Email templates follow SLA configuration and system behaviour.</li>
          <li>• Email templates are managed by system administrator.</li>
        </ul>
      </div>
    </div>

    <!-- ═══ Template Modal (Create + Edit) ═══ -->
    <EmailTemplateModal
      :show="showTplModal"
      :template-id="selectedTemplateId"
      :can-update="tplCanUpdate"
      :triggers="availableTriggers"
      @close="showTplModal = false"
      @saved="onTemplateSaved"
      @created="onTemplateCreated"
    />
  </div>
</template>
