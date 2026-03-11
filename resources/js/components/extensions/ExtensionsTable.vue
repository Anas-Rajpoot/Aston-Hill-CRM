<script setup>
/**
 * Cisco Extensions table – sortable columns, inline editable columns, View / Edit / History / Delete actions.
 */
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { canModuleAction } from '@/lib/accessControl'

const auth = useAuthStore()

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  selectedIds: { type: Array, default: () => [] },
  sort: { type: String, default: 'extension' },
  order: { type: String, default: 'asc' },
  loading: { type: Boolean, default: false },
  currentPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 15 },
  filterOptions: { type: Object, default: () => ({ gateways: [], statuses: [] }) },
  assignableEmployees: { type: Array, default: () => [] },
  managerOptions: { type: Array, default: () => [] },
  teamLeaderOptions: { type: Array, default: () => [] },
})

const emit = defineEmits(['sort', 'delete', 'updateCell', 'openHistory', 'edit', 'view', 'toggleSelectRow', 'toggleSelectAll'])

const editingCell = ref(null)
const inlineEditValue = ref('')
const inlineEditError = ref('')
const showPasswordInline = ref(false)

const canView = computed(() =>
  canModuleAction(auth.user, 'extensions', 'view', [
    'extensions.list',
    'extensions.view',
    'cisco-extensions.list',
    'cisco-extensions.view',
  ])
)
const canEdit = computed(() =>
  canModuleAction(auth.user, 'extensions', 'edit', [
    'extensions.edit',
    'extensions.update',
    'cisco-extensions.edit',
    'cisco-extensions.update',
  ])
)
const canDelete = computed(() =>
  canModuleAction(auth.user, 'extensions', 'delete', [
    'extensions.delete',
    'cisco-extensions.delete',
  ])
)
const canInlineEdit = computed(() => canEdit.value)
const hasAnyRowAction = computed(() => canView.value || canEdit.value || canDelete.value)
const hasSelectionColumn = computed(() => canEdit.value || canDelete.value)
const selectedSet = computed(() => new Set((props.selectedIds ?? []).map((id) => Number(id))))
const allVisibleSelected = computed(() => {
  if (!props.data?.length) return false
  return props.data.every((row) => selectedSet.value.has(Number(row.id)))
})
const someVisibleSelected = computed(() => {
  if (!props.data?.length) return false
  return props.data.some((row) => selectedSet.value.has(Number(row.id))) && !allVisibleSelected.value
})

const EDITABLE_COLUMNS = ['extension', 'landline_number', 'gateway', 'username', 'password', 'status', 'assigned_to_name', 'manager', 'team_leader', 'comment']
const DROPDOWN_COLUMNS = ['gateway', 'status', 'assigned_to_name', 'manager', 'team_leader']

function isEditableColumn(col) {
  return canInlineEdit.value && EDITABLE_COLUMNS.includes(col)
}

function isDropdownColumn(col) {
  return DROPDOWN_COLUMNS.includes(col)
}

function getCellValueForEdit(row, col) {
  if (col === 'assigned_to_name') return row.assigned_to != null ? String(row.assigned_to) : ''
  if (col === 'manager') return row.manager_id != null ? String(row.manager_id) : ''
  if (col === 'team_leader') return row.team_leader_id != null ? String(row.team_leader_id) : ''
  if (col === 'password') return row.password_view != null ? String(row.password_view) : ''
  return row[col] != null ? String(row[col]) : ''
}

function openDropdownEdit(row, col) {
  if (!isEditableColumn(col)) return
  inlineEditError.value = ''
  editingCell.value = { rowId: row.id, col }
  inlineEditValue.value = getCellValueForEdit(row, col)
}

function openInputEdit(row, col) {
  if (!isEditableColumn(col)) return
  inlineEditError.value = ''
  showPasswordInline.value = false
  editingCell.value = { rowId: row.id, col }
  inlineEditValue.value = getCellValueForEdit(row, col)
}

function validateInlineValue(col, value) {
  const raw = value == null ? '' : String(value).trim()
  if (col === 'extension' && !raw) return 'Extension is required.'
  if (col === 'gateway' && !raw) return 'Gateway is required.'
  if (col === 'username' && !raw) return 'Username is required.'
  if (col === 'status' && !raw) return 'Status is required.'
  if (col === 'password' && !raw) return 'Password is required.'
  if (col === 'landline_number') {
    if (!raw) return 'Landline Number is required.'
    if (/\s/.test(raw)) return 'Must not contain spaces.'
    if (!/^\d+$/.test(raw)) return 'Must contain only digits.'
    if (!raw.startsWith('971')) return 'Must start with 971.'
    if (raw.length !== 12) return 'Must be exactly 12 digits.'
  }
  return ''
}

function saveInlineEdit() {
  if (!editingCell.value) return
  const { rowId, col } = editingCell.value
  const err = validateInlineValue(col, inlineEditValue.value)
  if (err) {
    inlineEditError.value = err
    return
  }
  inlineEditError.value = ''
  let value = inlineEditValue.value
  if (col === 'assigned_to_name') {
    value = value === '' ? null : (Number(value) || null)
  } else if (col === 'manager' || col === 'team_leader') {
    value = value === '' ? null : (Number(value) || null)
  } else {
    value = value === '' ? null : value
  }
  emit('updateCell', rowId, col, value)
  showPasswordInline.value = false
  editingCell.value = null
}

function cancelInlineEdit() {
  inlineEditError.value = ''
  showPasswordInline.value = false
  editingCell.value = null
}

function isEditing(rowId, col) {
  return editingCell.value && editingCell.value.rowId === rowId && editingCell.value.col === col
}

function getGatewayOptions() {
  return (props.filterOptions?.gateways ?? []).map((g) => ({ value: g.value, label: g.label }))
}

function getStatusOptions() {
  return (props.filterOptions?.statuses ?? []).map((s) => ({ value: s.value, label: s.label }))
}

function getAssignedOptions() {
  const list = props.assignableEmployees ?? []
  return [{ value: '', label: 'Not Assigned' }, ...list.map((e) => ({ value: String(e.id), label: e.name || e.label }))]
}

function getManagerOptions() {
  const list = props.managerOptions ?? []
  return [{ value: '', label: 'Not Assigned' }, ...list.map((e) => ({ value: String(e.id), label: e.name || e.label }))]
}

function getTeamLeaderOptions() {
  const list = props.teamLeaderOptions ?? []
  return [{ value: '', label: 'Not Assigned' }, ...list.map((e) => ({ value: String(e.id), label: e.name || e.label }))]
}

const COLUMN_LABELS = {
  id: 'SR',
  extension: 'Extension',
  landline_number: 'Landline Number',
  gateway: 'Gateway',
  username: 'User Name',
  password: 'Password',
  status: 'Status',
  team_leader: 'Team Leader',
  manager: 'Manager',
  usage: 'Usage',
  assigned_to_name: 'Assigned To',
  comment: 'Comment',
  updated_at: 'Last Updated',
}

const SORTABLE_COLUMNS = ['id', 'extension', 'landline_number', 'gateway', 'username', 'status', 'team_leader', 'manager', 'usage', 'assigned_to_name', 'comment', 'updated_at']

function label(col) {
  return COLUMN_LABELS[col] ?? col
}

function sortable(col) {
  return SORTABLE_COLUMNS.includes(col)
}

function toggleSort(col) {
  if (!sortable(col)) return
  const nextOrder = props.sort === col && props.order === 'asc' ? 'desc' : 'asc'
  emit('sort', { sort: col, order: nextOrder })
}

function formatValue(row, col) {
  const val = row[col]
  if (val == null || val === '') return '—'
  return val
}

function statusLabel(status) {
  if (status === 'active') return 'Active'
  if (status === 'inactive') return 'InActive'
  if (status === 'not_created') return 'Not Created'
  return status ?? '—'
}

function usageLabel(usage) {
  if (usage === 'assigned') return 'Assigned'
  if (usage === 'unassigned') return 'UnAssigned'
  return usage ?? '—'
}

function goToView(row) {
  if (row?.id && canView.value) emit('view', row)
}

function goToEdit(row) {
  if (row?.id && canEdit.value) emit('edit', row)
}

function goToHistory(row) {
  if (row?.id && canView.value) emit('openHistory', row)
}

function onDelete(row) {
  if (row?.id && canDelete.value) emit('delete', row)
}

function isSelected(id) {
  return selectedSet.value.has(Number(id))
}

function toggleRow(row, checked) {
  emit('toggleSelectRow', { id: Number(row.id), checked: Boolean(checked) })
}

function toggleAll(checked) {
  emit('toggleSelectAll', { checked: Boolean(checked) })
}
</script>

<template>
  <div class="relative overflow-x-auto">
    <div
      v-if="loading"
      class="absolute inset-0 z-10 flex items-center justify-center bg-white/80"
      aria-live="polite"
      aria-busy="true"
    >
      <div class="flex flex-col items-center gap-2">
        <svg class="h-8 w-8 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <span class="text-sm font-medium text-gray-600">Loading...</span>
      </div>
    </div>

    <table class="min-w-full border-2 border-black border-collapse bg-white">
      <thead>
        <tr class="bg-brand-primary border-b-2 border-green-700">
          <th
            v-if="hasSelectionColumn"
            scope="col"
            class="w-10 whitespace-nowrap px-2 py-3 text-center text-sm font-semibold text-white"
          >
            <input
              type="checkbox"
              class="h-4 w-4 rounded border-gray-300 text-brand-primary focus:ring-brand-primary"
              :checked="allVisibleSelected"
              :indeterminate.prop="someVisibleSelected"
              @change="toggleAll($event.target.checked)"
            />
          </th>
          <th
            v-for="col in columns"
            :key="col"
            scope="col"
            class="whitespace-nowrap px-4 py-3 text-left text-sm font-semibold text-white"
          >
            <button
              v-if="sortable(col)"
              type="button"
              class="inline-flex items-center gap-1 font-semibold text-white hover:text-white/70"
              @click="toggleSort(col)"
            >
              {{ label(col) }}
              <svg v-if="sort === col" class="h-4 w-4" :class="order === 'asc' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
              </svg>
            </button>
            <span v-else class="font-semibold text-white">{{ label(col) }}</span>
          </th>
          <th v-if="hasAnyRowAction" scope="col" class="whitespace-nowrap px-4 py-3 text-center text-sm font-semibold text-white">Actions</th>
        </tr>
      </thead>
      <tbody class="bg-white">
        <tr v-if="!loading && !data.length" class="border-b border-black">
          <td :colspan="columns.length + (hasAnyRowAction ? 1 : 0) + (hasSelectionColumn ? 1 : 0)" class="px-4 py-12 text-center text-sm text-gray-500">No extensions found.</td>
        </tr>
        <tr
          v-for="(row, rowIndex) in data"
          :key="row.id"
          class="border-b border-black bg-white hover:bg-gray-50/30"
        >
          <td v-if="hasSelectionColumn" class="whitespace-nowrap px-2 py-3 text-center">
            <input
              type="checkbox"
              class="h-4 w-4 rounded border-gray-300 text-brand-primary focus:ring-brand-primary"
              :checked="isSelected(row.id)"
              @change="toggleRow(row, $event.target.checked)"
            />
          </td>
          <td
            v-for="col in columns"
            :key="col"
            class="whitespace-nowrap px-4 py-3 text-sm text-gray-900"
            :class="{
              'cursor-pointer': isEditableColumn(col) && !isEditing(row.id, col),
            }"
          >
            <template v-if="col === 'id'">
              {{ ((currentPage - 1) * perPage) + rowIndex + 1 }}
            </template>
            <template v-else-if="col === 'extension' && !isEditing(row.id, col)">
              <span
                v-if="isEditableColumn(col)"
                class="cursor-pointer text-brand-primary hover:text-brand-primary-hover hover:underline"
                title="Click to edit"
                @click="openInputEdit(row, col)"
              >{{ row.extension || '—' }}</span>
              <button
                v-else-if="canView && row.extension"
                type="button"
                class="text-left text-brand-primary hover:text-brand-primary-hover hover:underline bg-transparent border-none cursor-pointer p-0"
                @click="goToView(row)"
              >
                {{ row.extension }}
              </button>
              <span v-else>{{ row.extension || '—' }}</span>
            </template>
            <template v-else-if="col === 'password' && !isEditing(row.id, col)">
              <span
                v-if="isEditableColumn(col)"
                class="cursor-pointer text-brand-primary hover:text-brand-primary-hover hover:underline"
                title="Double-click to edit password"
                @dblclick="openInputEdit(row, col)"
              >{{ row.password ? '••••••••' : '—' }}</span>
              <span v-else-if="row.password" class="text-gray-500">••••••••</span>
              <span v-else>—</span>
            </template>
            <template v-else-if="isEditing(row.id, col)">
              <template v-if="isDropdownColumn(col)">
                <span class="inline-flex flex-wrap items-center gap-2">
                  <select
                    v-if="col === 'gateway'"
                    v-model="inlineEditValue"
                    class="min-w-[120px] rounded border border-gray-300 px-2 py-1 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                  >
                    <option value="">—</option>
                    <option v-for="opt in getGatewayOptions()" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                  <select
                    v-else-if="col === 'status'"
                    v-model="inlineEditValue"
                    class="min-w-[120px] rounded border border-gray-300 px-2 py-1 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                  >
                    <option v-for="opt in getStatusOptions()" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                  <select
                    v-else-if="col === 'assigned_to_name'"
                    v-model="inlineEditValue"
                    class="min-w-[140px] rounded border border-gray-300 px-2 py-1 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                  >
                    <option v-for="opt in getAssignedOptions()" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                  <select
                    v-else-if="col === 'manager'"
                    v-model="inlineEditValue"
                    class="min-w-[140px] rounded border border-gray-300 px-2 py-1 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                  >
                    <option v-for="opt in getManagerOptions()" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                  <select
                    v-else-if="col === 'team_leader'"
                    v-model="inlineEditValue"
                    class="min-w-[140px] rounded border border-gray-300 px-2 py-1 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                  >
                    <option v-for="opt in getTeamLeaderOptions()" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                  <span class="inline-flex items-center gap-1 shrink-0">
                    <button type="button" class="rounded bg-brand-primary px-2 py-1 text-xs font-medium text-white hover:bg-brand-primary-hover" @click="saveInlineEdit">Save</button>
                    <button type="button" class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  </span>
                </span>
                <p v-if="inlineEditError && isEditing(row.id, col)" class="mt-1 text-xs text-red-600">{{ inlineEditError }}</p>
              </template>
              <span v-else class="inline-flex flex-wrap items-center gap-2">
                <input
                  v-model="inlineEditValue"
                  type="text"
                  :maxlength="editingCell?.col === 'landline_number' ? 12 : undefined"
                  :placeholder="editingCell?.col === 'password' ? 'Enter password' : undefined"
                  :style="editingCell?.col === 'password' && !showPasswordInline ? { WebkitTextSecurity: 'disc' } : undefined"
                  :name="editingCell?.col === 'password' ? `ext_secret_${editingCell?.rowId || ''}` : undefined"
                  :autocomplete="editingCell?.col === 'password' ? 'new-password' : 'off'"
                  autocapitalize="off"
                  autocorrect="off"
                  spellcheck="false"
                  data-lpignore="true"
                  data-1p-ignore="true"
                  data-bwignore="true"
                  class="min-w-[100px] rounded border border-gray-300 px-2 py-1 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                  @input="editingCell?.col === 'landline_number' && (inlineEditValue = String(inlineEditValue ?? '').replace(/\D/g, '').slice(0, 12))"
                  @keydown.enter="saveInlineEdit"
                  @keydown.escape="cancelInlineEdit"
                />
                <button
                  v-if="editingCell?.col === 'password'"
                  type="button"
                  class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                  @click="showPasswordInline = !showPasswordInline"
                >
                  {{ showPasswordInline ? 'Hide' : 'View' }}
                </button>
                <span class="inline-flex items-center gap-1 shrink-0">
                  <button type="button" class="rounded bg-brand-primary px-2 py-1 text-xs font-medium text-white hover:bg-brand-primary-hover" @click="saveInlineEdit">Save</button>
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                </span>
              </span>
              <p v-if="inlineEditError && isEditing(row.id, col)" class="mt-1 text-xs text-red-600">{{ inlineEditError }}</p>
            </template>
            <template v-else-if="col === 'status'">
              <span
                class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800"
                @click="openDropdownEdit(row, col)"
              >
                {{ statusLabel(row.status) }}
              </span>
            </template>
            <template v-else-if="col === 'usage'">
              <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                {{ usageLabel(row.usage) }}
              </span>
            </template>
            <template v-else-if="col === 'assigned_to_name'">
              <span
                v-if="row.assigned_to_name"
                class="text-gray-900"
                @click="openDropdownEdit(row, col)"
              >{{ row.assigned_to_name }}</span>
              <span
                v-else
                class="italic text-gray-500"
                @click="openDropdownEdit(row, col)"
              >Not assigned</span>
            </template>
            <template v-else-if="(col === 'manager' || col === 'team_leader') && isEditableColumn(col)">
              <span class="cursor-pointer" @click="openDropdownEdit(row, col)">{{ formatValue(row, col) }}</span>
            </template>
            <template v-else-if="col === 'gateway' && isEditableColumn(col)">
              <span @click="openDropdownEdit(row, col)">{{ formatValue(row, col) }}</span>
            </template>
            <template v-else-if="(col === 'landline_number' || col === 'username' || col === 'comment') && isEditableColumn(col)">
              <span class="cursor-pointer" @click="openInputEdit(row, col)">{{ formatValue(row, col) }}</span>
            </template>
            <template v-else>
              {{ formatValue(row, col) }}
            </template>
          </td>
          <td v-if="hasAnyRowAction" class="whitespace-nowrap px-4 py-3 text-center">
            <div class="inline-flex items-center justify-center gap-2">
              <button
                v-if="canView"
                type="button"
                class="rounded-full p-1.5 text-brand-primary hover:bg-brand-primary-light"
                title="View"
                @click="goToView(row)"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </button>
              <button
                v-if="canEdit"
                type="button"
                class="rounded-full p-1.5 text-brand-primary hover:bg-brand-primary-light"
                title="Edit"
                @click="goToEdit(row)"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                </svg>
              </button>
              <button
                v-if="canView"
                type="button"
                class="rounded-full p-1.5 text-amber-500 hover:bg-amber-50"
                title="History"
                @click="goToHistory(row)"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </button>
              <button
                v-if="canDelete"
                type="button"
                class="rounded-full p-1.5 text-red-600 hover:bg-red-50"
                title="Delete"
                @click="onDelete(row)"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
