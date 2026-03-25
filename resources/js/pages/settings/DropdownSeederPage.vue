<script setup>
/**
 * Dropdown Seeder – Settings page for managing CRM dropdown options.
 * Groups: statuses, emirates, service categories, product types, etc.
 * Supports: CRUD, toggle active/inactive, reorder, and cascade rename.
 * Superadmin only.
 */
import { ref, computed, onMounted } from 'vue'
import api from '@/lib/axios'
import { useRouter } from 'vue-router'
import DeleteOtpModal from '@/components/DeleteOtpModal.vue'

const router = useRouter()

/* ───── State ───── */
const loading = ref(true)
const saving = ref(false)
const groups = ref([])
const groupedData = ref([])
const selectedGroup = ref('')
const showAddGroup = ref(false)
const newGroupName = ref('')
const error = ref('')
const success = ref('')
const searchQuery = ref('')
const importing = ref(false)
const fileInputRef = ref(null)

/* ───── New option form ───── */
const newOption = ref({ value: '', label: '', sort_order: 0 })

/* ───── Edit state ───── */
const editingId = ref(null)
const editForm = ref({ value: '', label: '', sort_order: 0, is_active: true })

/* ───── Delete state ───── */
const showDeleteModal = ref(false)
const deleteTarget = ref(null)

/* ───── Computed ───── */
const GROUP_KEY_ALIASES = {
  lead_statuses: ['lead_submission_statuses', 'lead_status'],
  field_statuses: ['field_submission_statuses', 'field_status'],
  vas_statuses: ['vas_request_statuses', 'vas_status'],
  client_statuses: ['clients_statuses', 'client_status'],
  expense_statuses: ['expenses_statuses', 'expense_status'],
  team_statuses: ['teams_statuses', 'team_status'],
}

function normalizeKey(v) {
  return String(v || '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '')
}

function resolveGroupKey(key) {
  const target = normalizeKey(key)
  const existing = groups.value || []
  if (existing.includes(target)) return target

  const aliases = (GROUP_KEY_ALIASES[target] || []).map(normalizeKey)
  for (const a of aliases) {
    if (existing.includes(a)) return a
  }

  // Fuzzy fallback: same important tokens (e.g., lead + statuses)
  const tokens = target.split('_').filter((t) => t && t !== 'new')
  const match = existing.find((g) => tokens.every((t) => normalizeKey(g).includes(t)))
  return match || target
}

const resolvedSelectedGroup = computed(() => resolveGroupKey(selectedGroup.value))
const currentGroup = computed(() =>
  groupedData.value.find((g) => g.group === resolvedSelectedGroup.value)
)
const currentOptions = computed(() => currentGroup.value?.options ?? [])
const groupCountMap = computed(() => {
  const out = {}
  for (const g of groupedData.value) out[g.group] = g.options?.length ?? 0
  return out
})

/* ───── Admin-first sequence catalog (module -> groups) ───── */
const GROUP_SECTIONS = [
  {
    key: 'lead',
    title: 'Lead Submissions',
    groups: [
      { value: 'lead_statuses', label: 'Lead Statuses' },
      { value: 'submission_types', label: 'Submission Types' },
      { value: 'service_categories', label: 'Service Categories' },
      { value: 'service_types', label: 'Service Types' },
      { value: 'product_types', label: 'Product Types' },
      { value: 'contract_types', label: 'Contract Types' },
      { value: 'work_order_statuses', label: 'Work Order Statuses' },
      { value: 'call_verification_statuses', label: 'Call Verification Statuses' },
      { value: 'documents_verification_statuses', label: 'Documents Verification Statuses' },
      { value: 'du_statuses', label: 'DU Statuses' },
      { value: 'company_categories', label: 'Company Categories' },
      { value: 'emirates', label: 'Emirates' },
    ],
  },
  {
    key: 'field',
    title: 'Field Submissions',
    groups: [
      { value: 'field_statuses', label: 'Field Submission Statuses' },
      { value: 'field_meeting_statuses', label: 'Field Meeting Statuses' },
      { value: 'field_appointment_statuses', label: 'Field Appointment Statuses' },
    ],
  },
  {
    key: 'support',
    title: 'Customer Support',
    groups: [
      { value: 'customer_support_statuses', label: 'Customer Support Statuses' },
      { value: 'ticket_priorities', label: 'Ticket Priorities' },
      { value: 'ticket_categories', label: 'Ticket Categories' },
    ],
  },
  {
    key: 'vas',
    title: 'VAS & Special Requests',
    groups: [
      { value: 'vas_statuses', label: 'VAS Request Statuses' },
      { value: 'special_request_statuses', label: 'Special Request Statuses' },
    ],
  },
  {
    key: 'clients',
    title: 'Clients',
    groups: [
      { value: 'client_statuses', label: 'Client Statuses' },
      { value: 'client_categories', label: 'Client Categories' },
      { value: 'company_categories', label: 'Company Categories' },
    ],
  },
  {
    key: 'ops',
    title: 'Operations',
    groups: [
      { value: 'dsp_statuses', label: 'DSP Statuses' },
      { value: 'order_statuses', label: 'Order Statuses' },
      { value: 'verification_statuses', label: 'Verifier Statuses' },
    ],
  },
  {
    key: 'extensions',
    title: 'Cisco Extensions',
    groups: [
      { value: 'extension_statuses', label: 'Extension Statuses' },
      { value: 'extension_gateways', label: 'Extension Gateways' },
    ],
  },
  {
    key: 'expenses',
    title: 'Expense Tracker',
    groups: [
      { value: 'expense_statuses', label: 'Expense Statuses' },
      { value: 'expense_categories', label: 'Expense Categories' },
    ],
  },
  {
    key: 'org',
    title: 'Teams & Users',
    groups: [
      { value: 'team_statuses', label: 'Team Statuses' },
      { value: 'user_statuses', label: 'User Statuses' },
    ],
  },
]
const GROUP_TEMPLATES = GROUP_SECTIONS.flatMap((s) => s.groups)
const allGroupSequence = computed(() => {
  const ordered = GROUP_TEMPLATES.map((t) => t.value)
  const extras = groups.value.filter((g) => !ordered.includes(g))
  return [...ordered, ...extras]
})
const selectedGroupIndex = computed(() => allGroupSequence.value.indexOf(selectedGroup.value))
const canGoPrevGroup = computed(() => selectedGroupIndex.value > 0)
const canGoNextGroup = computed(() => {
  const idx = selectedGroupIndex.value
  return idx >= 0 && idx < allGroupSequence.value.length - 1
})
const filteredSections = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return GROUP_SECTIONS
  const optionMap = {}
  for (const g of groupedData.value) optionMap[g.group] = g.options ?? []
  return GROUP_SECTIONS
    .map((section) => {
      const groupsFiltered = section.groups.filter((grp) => {
        const groupText = `${grp.label} ${grp.value}`.toLowerCase()
        if (groupText.includes(q)) return true
        const opts = optionMap[grp.value] ?? []
        return opts.some((o) =>
          String(o?.value ?? '').toLowerCase().includes(q) ||
          String(o?.label ?? '').toLowerCase().includes(q)
        )
      })
      return { ...section, groups: groupsFiltered }
    })
    .filter((section) => section.groups.length > 0)
})

const groupLabel = (key) => {
  const tpl = GROUP_TEMPLATES.find((t) => t.value === key)
  return tpl ? tpl.label : key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function goPrevGroup() {
  if (!canGoPrevGroup.value) return
  selectedGroup.value = allGroupSequence.value[selectedGroupIndex.value - 1] || selectedGroup.value
}

function goNextGroup() {
  if (!canGoNextGroup.value) return
  selectedGroup.value = allGroupSequence.value[selectedGroupIndex.value + 1] || selectedGroup.value
}

function triggerImport() {
  fileInputRef.value?.click()
}

function downloadExportCsv() {
  const rows = []
  for (const groupItem of groupedData.value) {
    for (const opt of groupItem.options ?? []) {
      rows.push({
        group: groupItem.group,
        value: opt.value ?? '',
        label: opt.label ?? '',
        sort_order: Number(opt.sort_order ?? 0),
        is_active: opt.is_active ? 1 : 0,
      })
    }
  }
  const headers = ['group', 'value', 'label', 'sort_order', 'is_active']
  const escape = (v) => {
    const s = String(v ?? '')
    if (s.includes(',') || s.includes('"') || s.includes('\n')) return `"${s.replace(/"/g, '""')}"`
    return s
  }
  const lines = [headers.join(',')]
  for (const r of rows) lines.push(headers.map((h) => escape(r[h])).join(','))
  const blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `dropdown-seeder-${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

async function onImportCsv(event) {
  const file = event?.target?.files?.[0]
  if (!file) return
  importing.value = true
  error.value = ''
  success.value = ''
  try {
    const text = await file.text()
    const lines = text.split(/\r?\n/).filter((l) => l.trim() !== '')
    if (lines.length < 2) throw new Error('CSV is empty.')
    const parseLine = (line) => {
      const out = []
      let cur = ''
      let inQuotes = false
      for (let i = 0; i < line.length; i += 1) {
        const ch = line[i]
        if (ch === '"' && line[i + 1] === '"') {
          cur += '"'
          i += 1
          continue
        }
        if (ch === '"') { inQuotes = !inQuotes; continue }
        if (ch === ',' && !inQuotes) { out.push(cur); cur = ''; continue }
        cur += ch
      }
      out.push(cur)
      return out.map((c) => c.trim())
    }
    const headers = parseLine(lines[0]).map((h) => h.toLowerCase())
    const idx = {
      group: headers.indexOf('group'),
      value: headers.indexOf('value'),
      label: headers.indexOf('label'),
      sort_order: headers.indexOf('sort_order'),
      is_active: headers.indexOf('is_active'),
    }
    if (idx.group < 0 || idx.value < 0) throw new Error('CSV must include group and value columns.')

    const existing = new Map()
    for (const groupItem of groupedData.value) {
      for (const opt of groupItem.options ?? []) {
        existing.set(`${groupItem.group}::${opt.value}`, opt.id)
      }
    }
    let created = 0
    let updated = 0
    for (let i = 1; i < lines.length; i += 1) {
      const cols = parseLine(lines[i])
      const group = String(cols[idx.group] ?? '').trim().toLowerCase()
      const value = String(cols[idx.value] ?? '').trim()
      if (!group || !value) continue
      const label = idx.label >= 0 ? String(cols[idx.label] ?? '').trim() : ''
      const sortOrder = idx.sort_order >= 0 ? Number(cols[idx.sort_order] ?? 0) || 0 : 0
      const active = idx.is_active >= 0 ? ['1', 'true', 'yes', 'active'].includes(String(cols[idx.is_active] ?? '').toLowerCase()) : true
      const key = `${group}::${value}`
      const existingId = existing.get(key)
      if (existingId) {
        await api.put(`/settings/dropdown-seeder/${existingId}`, { value, label: label || null, sort_order: sortOrder, is_active: active })
        updated += 1
      } else {
        await api.post('/settings/dropdown-seeder', { group, value, label: label || null, sort_order: sortOrder, is_active: active })
        created += 1
      }
    }
    await loadData()
    success.value = `Import complete. Created ${created}, updated ${updated}.`
  } catch (e) {
    error.value = e?.message || 'Failed to import CSV.'
  } finally {
    importing.value = false
    if (event?.target) event.target.value = ''
  }
}

/* ───── Load data ───── */
async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/settings/dropdown-seeder')
    groups.value = data.groups ?? []
    groupedData.value = data.data ?? []

    const allKnown = Array.from(new Set([...GROUP_TEMPLATES.map((t) => t.value), ...groups.value]))
    const firstWithData = groupedData.value[0]?.group || groups.value[0] || ''
    if (!selectedGroup.value && allKnown.length > 0) {
      selectedGroup.value = firstWithData || allKnown[0]
    } else if (selectedGroup.value && !allKnown.includes(selectedGroup.value)) {
      selectedGroup.value = firstWithData || allKnown[0] || ''
    }
  } catch {
    error.value = 'Failed to load dropdown options.'
  } finally {
    loading.value = false
  }
}

/* ───── Add option ───── */
async function addOption() {
  if (!newOption.value.value.trim()) return
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    await api.post('/settings/dropdown-seeder', {
      group: resolvedSelectedGroup.value,
      value: newOption.value.value.trim(),
      label: newOption.value.label.trim() || null,
      sort_order: newOption.value.sort_order || 0,
    })
    newOption.value = { value: '', label: '', sort_order: 0 }
    success.value = 'Option added.'
    await loadData()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to add option.'
  } finally {
    saving.value = false
  }
}

/* ───── Create new group ───── */
async function createGroup() {
  const name = newGroupName.value.trim().toLowerCase().replace(/\s+/g, '_')
  if (!name) return
  selectedGroup.value = name
  showAddGroup.value = false
  newGroupName.value = ''
  // Group will be created when the first option is added
}

/* ───── Edit option ───── */
function startEdit(opt) {
  editingId.value = opt.id
  editForm.value = { value: opt.value, label: opt.label || '', sort_order: opt.sort_order, is_active: opt.is_active }
}

function cancelEdit() {
  editingId.value = null
}

async function saveEdit(id) {
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    await api.put(`/settings/dropdown-seeder/${id}`, editForm.value)
    editingId.value = null
    success.value = 'Option updated.'
    await loadData()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to update option.'
  } finally {
    saving.value = false
  }
}

/* ───── Toggle active ───── */
async function toggleActive(opt) {
  try {
    await api.put(`/settings/dropdown-seeder/${opt.id}`, { is_active: !opt.is_active })
    await loadData()
  } catch {
    error.value = 'Failed to toggle option.'
  }
}

/* ───── Delete ───── */
function confirmDelete(opt) {
  deleteTarget.value = opt
  showDeleteModal.value = true
}

async function handleDelete() {
  if (!deleteTarget.value) return
  saving.value = true
  try {
    await api.delete(`/settings/dropdown-seeder/${deleteTarget.value.id}`)
    showDeleteModal.value = false
    deleteTarget.value = null
    success.value = 'Option deleted.'
    await loadData()
  } catch {
    error.value = 'Failed to delete option.'
  } finally {
    saving.value = false
  }
}

/* ───── Lifecycle ───── */
onMounted(() => loadData())
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <button
          type="button"
          class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-brand-primary"
          @click="router.push('/settings')"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Back to Settings
        </button>
        <h1 class="text-2xl font-bold text-gray-900">Dropdown Seeder</h1>
        <p class="mt-1 text-sm text-gray-500">
          Manage dropdown values used across the CRM. Renaming a value will cascade-update all existing records.
        </p>
      </div>
    </div>

    <!-- Alerts -->
    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
      {{ error }}
      <button class="ml-2 font-medium underline" @click="error = ''">Dismiss</button>
    </div>
    <div v-if="success" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
      {{ success }}
      <button class="ml-2 font-medium underline" @click="success = ''">Dismiss</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16">
      <svg class="h-8 w-8 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
      </svg>
    </div>

    <template v-else>
      <!-- Legacy quick selector -->
      <div class="flex flex-wrap items-center gap-3">
        <label class="text-sm font-medium text-gray-700">Group:</label>
        <select
          v-model="selectedGroup"
          class="rounded-lg border border-gray-300 px-3 py-2 text-sm min-w-[260px]"
        >
          <option value="" disabled>Select a group</option>
          <option v-for="tpl in GROUP_TEMPLATES" :key="tpl.value" :value="tpl.value">
            {{ tpl.label }}
          </option>
          <option v-for="g in groups.filter((x) => !GROUP_TEMPLATES.some((t) => t.value === x))" :key="g" :value="g">
            {{ groupLabel(g) }} (existing)
          </option>
        </select>
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          @click="showAddGroup = !showAddGroup"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Custom Group
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          @click="downloadExportCsv"
        >
          Export CSV
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
          :disabled="importing"
          @click="triggerImport"
        >
          {{ importing ? 'Importing...' : 'Import CSV' }}
        </button>
        <input ref="fileInputRef" type="file" accept=".csv,text/csv" class="hidden" @change="onImportCsv" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search groups/options..."
          class="rounded-lg border border-gray-300 px-3 py-2 text-sm min-w-[220px]"
        />
      </div>

      <!-- Module-wise sequence: all groups in one screen -->
      <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div
          v-for="section in filteredSections"
          :key="section.key"
          class="rounded-xl border border-gray-200 bg-white p-4"
        >
          <h3 class="text-sm font-semibold text-gray-900">{{ section.title }}</h3>
          <p class="mt-1 text-xs text-gray-500">Select a dropdown group to manage values.</p>
          <div class="mt-3 space-y-2">
            <button
              v-for="g in section.groups"
              :key="g.value"
              type="button"
              class="flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left text-sm transition-colors"
              :class="resolvedSelectedGroup === resolveGroupKey(g.value)
                ? 'border-brand-primary bg-brand-primary-light text-brand-primary'
                : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
              @click="selectedGroup = g.value"
            >
              <span>{{ g.label }}</span>
              <span class="text-xs"
                :class="(groupCountMap[resolveGroupKey(g.value)] ?? 0) > 0 ? 'text-gray-500' : 'text-amber-600'">
                {{ (groupCountMap[resolveGroupKey(g.value)] ?? 0) > 0 ? `${groupCountMap[resolveGroupKey(g.value)]} options` : 'empty' }}
              </span>
            </button>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
        <div class="text-sm text-gray-600">
          Sequence: <span class="font-medium text-gray-900">{{ selectedGroupIndex + 1 }}</span>
          / {{ allGroupSequence.length }}
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            :disabled="!canGoPrevGroup"
            @click="goPrevGroup"
          >
            Previous Group
          </button>
          <button
            type="button"
            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            :disabled="!canGoNextGroup"
            @click="goNextGroup"
          >
            Next Group
          </button>
        </div>
      </div>

      <!-- Custom group input -->
      <div v-if="showAddGroup" class="flex items-end gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Group Key (snake_case)</label>
          <input
            v-model="newGroupName"
            type="text"
            placeholder="e.g. payment_methods"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm"
          />
        </div>
        <button
          type="button"
          class="rounded-lg bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-green-600"
          @click="createGroup"
        >
          Use Group
        </button>
      </div>

      <!-- Options table & add form -->
      <div v-if="selectedGroup" class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
          <h3 class="text-sm font-semibold text-gray-700 mb-3">
            Add Option to "{{ groupLabel(selectedGroup) }}"
          </h3>
          <div class="flex flex-wrap items-end gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Value *</label>
              <input
                v-model="newOption.value"
                type="text"
                placeholder="e.g. submitted"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-48"
                @keydown.enter="addOption"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Label (optional)</label>
              <input
                v-model="newOption.label"
                type="text"
                placeholder="Display label"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-48"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Sort Order</label>
              <input
                v-model.number="newOption.sort_order"
                type="number"
                min="0"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-20"
              />
            </div>
            <button
              type="button"
              class="rounded-lg bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-green-600 disabled:opacity-50"
              :disabled="saving || !newOption.value.trim()"
              @click="addOption"
            >
              {{ saving ? 'Saving...' : 'Add' }}
            </button>
          </div>
        </div>

        <!-- Options listing -->
        <div class="overflow-x-auto rounded-xl border border-gray-200">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-brand-primary border-b-2 border-green-700">
                <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase">Order</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase">Value</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase">Label</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase">Active</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-white uppercase">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="currentOptions.length === 0">
                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                  No options in this group yet. Add one above.
                </td>
              </tr>
              <template v-for="opt in currentOptions" :key="opt.id">
                <!-- View row -->
                <tr v-if="editingId !== opt.id" class="border-b border-gray-100 hover:bg-gray-50">
                  <td class="px-4 py-3 text-gray-500">{{ opt.sort_order }}</td>
                  <td class="px-4 py-3 font-medium text-gray-900">{{ opt.value }}</td>
                  <td class="px-4 py-3 text-gray-600">{{ opt.label || '—' }}</td>
                  <td class="px-4 py-3 text-center">
                    <button
                      type="button"
                      :class="[
                        'inline-flex h-6 w-11 items-center rounded-full transition-colors',
                        opt.is_active ? 'bg-brand-primary' : 'bg-gray-300',
                      ]"
                      @click="toggleActive(opt)"
                    >
                      <span
                        :class="[
                          'inline-block h-4 w-4 rounded-full bg-white shadow transition-transform',
                          opt.is_active ? 'translate-x-6' : 'translate-x-1',
                        ]"
                      />
                    </button>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <button
                      type="button"
                      class="mr-2 text-brand-primary hover:text-green-700 text-xs font-medium"
                      @click="startEdit(opt)"
                    >
                      Edit
                    </button>
                    <button
                      type="button"
                      class="text-red-600 hover:text-red-800 text-xs font-medium"
                      @click="confirmDelete(opt)"
                    >
                      Delete
                    </button>
                  </td>
                </tr>
                <!-- Edit row -->
                <tr v-else class="border-b border-gray-100 bg-amber-50">
                  <td class="px-4 py-2">
                    <input v-model.number="editForm.sort_order" type="number" min="0" class="w-16 rounded border border-gray-300 px-2 py-1 text-sm" />
                  </td>
                  <td class="px-4 py-2">
                    <input v-model="editForm.value" type="text" class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                  </td>
                  <td class="px-4 py-2">
                    <input v-model="editForm.label" type="text" class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                  </td>
                  <td class="px-4 py-2 text-center">
                    <input v-model="editForm.is_active" type="checkbox" class="rounded text-brand-primary" />
                  </td>
                  <td class="px-4 py-2 text-right">
                    <button
                      type="button"
                      class="mr-2 rounded bg-brand-primary px-3 py-1 text-xs font-medium text-white hover:bg-green-600 disabled:opacity-50"
                      :disabled="saving"
                      @click="saveEdit(opt.id)"
                    >
                      Save
                    </button>
                    <button
                      type="button"
                      class="rounded bg-gray-200 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-300"
                      @click="cancelEdit"
                    >
                      Cancel
                    </button>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Cascade info -->
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
          <div class="flex gap-2">
            <svg class="h-5 w-5 shrink-0 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="text-sm text-blue-800">
              <strong>Cascade Rename:</strong> When you edit a value, all existing records across the CRM that
              use the old value will be automatically updated to the new value. This ensures data consistency.
            </div>
          </div>
        </div>
      </div>

      <!-- No group selected -->
      <div v-else class="rounded-xl border border-gray-200 bg-gray-50 px-6 py-12 text-center">
        <p class="text-gray-500">Select a dropdown group above to manage its options.</p>
      </div>
    </template>

    <!-- Delete confirmation modal -->
    <DeleteOtpModal
      :visible="showDeleteModal"
      title="Delete Dropdown Option"
      :item-label="deleteTarget?.value || ''"
      :loading="saving"
      @confirm="handleDelete"
      @close="showDeleteModal = false; deleteTarget = null"
    />
  </div>
</template>
