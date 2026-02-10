<script setup>
/**
 * Cisco Extensions table – sortable columns, inline editable columns, View / Edit / History / Delete actions.
 */
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  sort: { type: String, default: 'extension' },
  order: { type: String, default: 'asc' },
  loading: { type: Boolean, default: false },
  currentPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 15 },
  filterOptions: { type: Object, default: () => ({ gateways: [], statuses: [] }) },
  assignableEmployees: { type: Array, default: () => [] },
})

const emit = defineEmits(['sort', 'delete', 'updateCell', 'openHistory', 'edit'])

const editingCell = ref(null)
const inlineEditValue = ref('')

const permissions = computed(() => auth.user?.permissions ?? [])
const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) && r.some((x) => (typeof x === 'string' ? x === 'superadmin' : x?.name === 'superadmin'))
})
const canView = computed(() => isSuperAdmin.value || permissions.value.includes('extensions.list'))
const canEdit = computed(() => isSuperAdmin.value || permissions.value.includes('extensions.edit'))
const canDelete = computed(() => isSuperAdmin.value || permissions.value.includes('extensions.edit'))
const canInlineEdit = computed(() => canEdit.value)

const EDITABLE_COLUMNS = ['landline_number', 'gateway', 'username', 'status', 'assigned_to_name']
const DROPDOWN_COLUMNS = ['gateway', 'status', 'assigned_to_name']

function isEditableColumn(col) {
  return canInlineEdit.value && EDITABLE_COLUMNS.includes(col)
}

function isDropdownColumn(col) {
  return DROPDOWN_COLUMNS.includes(col)
}

function getCellValueForEdit(row, col) {
  if (col === 'assigned_to_name') return row.assigned_to != null ? String(row.assigned_to) : ''
  return row[col] != null ? String(row[col]) : ''
}

function openDropdownEdit(row, col) {
  if (!isEditableColumn(col)) return
  editingCell.value = { rowId: row.id, col }
  inlineEditValue.value = getCellValueForEdit(row, col)
}

function openInputEdit(row, col) {
  if (!isEditableColumn(col)) return
  editingCell.value = { rowId: row.id, col }
  inlineEditValue.value = getCellValueForEdit(row, col)
}

function saveInlineEdit() {
  if (!editingCell.value) return
  const { rowId, col } = editingCell.value
  let value = inlineEditValue.value
  if (col === 'assigned_to_name') {
    value = value === '' ? null : (Number(value) || null)
  } else {
    value = value === '' ? null : value
  }
  emit('updateCell', rowId, col, value)
  editingCell.value = null
}

function cancelInlineEdit() {
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

const COLUMN_LABELS = {
  id: 'ID',
  extension: 'Extension',
  landline_number: 'Landline Number',
  gateway: 'Gateway',
  username: 'Username',
  password: 'Password',
  status: 'Status',
  team_leader: 'Team Leader',
  manager: 'Manager',
  usage: 'Usage',
  assigned_to_name: 'Assigned To',
  updated_at: 'Last Updated',
}

const SORTABLE_COLUMNS = ['id', 'extension', 'landline_number', 'gateway', 'username', 'status', 'team_leader', 'manager', 'updated_at']

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
  if (row?.id && canView.value) router.push(`/cisco-extensions/${row.id}`)
}

function goToEdit(row) {
  if (row?.id && canEdit.value) emit('edit', row)
}

function goToHistory(row) {
  if (row?.id) emit('openHistory', row)
}

function onDelete(row) {
  if (row?.id && canDelete.value) emit('delete', row)
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
        <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <span class="text-sm font-medium text-gray-600">Loading...</span>
      </div>
    </div>

    <table class="min-w-full border border-gray-200 border-collapse bg-white">
      <thead>
        <tr class="border-b border-gray-200 bg-gray-50">
          <th
            v-for="col in columns"
            :key="col"
            scope="col"
            class="whitespace-nowrap px-4 py-3 text-left text-sm font-semibold text-gray-900"
          >
            <button
              v-if="sortable(col)"
              type="button"
              class="inline-flex items-center gap-1 font-semibold text-gray-900 hover:text-gray-700"
              @click="toggleSort(col)"
            >
              {{ label(col) }}
              <svg v-if="sort === col" class="h-4 w-4" :class="order === 'asc' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
              </svg>
            </button>
            <span v-else class="font-semibold text-gray-900">{{ label(col) }}</span>
          </th>
          <th scope="col" class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-gray-900">Actions</th>
        </tr>
      </thead>
      <tbody class="bg-white">
        <tr v-if="!loading && !data.length" class="border-b border-gray-200">
          <td :colspan="columns.length + 1" class="px-4 py-12 text-center text-sm text-gray-500">No extensions found.</td>
        </tr>
        <tr
          v-for="row in data"
          :key="row.id"
          class="border-b border-gray-200 bg-white hover:bg-gray-50/30"
        >
          <td
            v-for="col in columns"
            :key="col"
            class="whitespace-nowrap px-4 py-3 text-sm text-gray-900"
            :class="{
              'cursor-pointer': isEditableColumn(col) && !isEditing(row.id, col),
            }"
          >
            <template v-if="col === 'id'">
              {{ row.id }}
            </template>
            <template v-else-if="col === 'extension'">
              <router-link
                v-if="canView && row.extension"
                :to="`/cisco-extensions/${row.id}`"
                class="text-blue-600 hover:text-blue-800 hover:underline"
              >
                {{ row.extension }}
              </router-link>
              <span v-else>{{ row.extension || '—' }}</span>
            </template>
            <template v-else-if="col === 'password'">
              <span v-if="row.password" class="text-gray-500">••••••••</span>
              <span v-else>—</span>
            </template>
            <template v-else-if="isEditing(row.id, col)">
              <template v-if="isDropdownColumn(col)">
                <select
                  v-if="col === 'gateway'"
                  v-model="inlineEditValue"
                  class="w-full min-w-[120px] rounded border border-gray-300 px-2 py-1 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  @change="saveInlineEdit"
                >
                  <option value="">—</option>
                  <option v-for="opt in getGatewayOptions()" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
                <select
                  v-else-if="col === 'status'"
                  v-model="inlineEditValue"
                  class="w-full min-w-[120px] rounded border border-gray-300 px-2 py-1 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  @change="saveInlineEdit"
                >
                  <option v-for="opt in getStatusOptions()" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
                <select
                  v-else-if="col === 'assigned_to_name'"
                  v-model="inlineEditValue"
                  class="w-full min-w-[140px] rounded border border-gray-300 px-2 py-1 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  @change="saveInlineEdit"
                >
                  <option v-for="opt in getAssignedOptions()" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </template>
              <span v-else class="inline-flex items-center gap-1">
                <input
                  v-model="inlineEditValue"
                  type="text"
                  class="min-w-[100px] rounded border border-gray-300 px-2 py-1 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  @keydown.enter="saveInlineEdit"
                  @keydown.escape="cancelInlineEdit"
                />
                <button type="button" class="rounded p-0.5 text-green-600 hover:bg-green-50" title="Save" @click="saveInlineEdit">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </button>
                <button type="button" class="rounded p-0.5 text-gray-500 hover:bg-gray-100" title="Cancel" @click="cancelInlineEdit">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </span>
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
            <template v-else-if="col === 'gateway' && isEditableColumn(col)">
              <span @click="openDropdownEdit(row, col)">{{ formatValue(row, col) }}</span>
            </template>
            <template v-else-if="(col === 'landline_number' || col === 'username') && isEditableColumn(col)">
              <span @click="openInputEdit(row, col)">{{ formatValue(row, col) }}</span>
            </template>
            <template v-else>
              {{ formatValue(row, col) }}
            </template>
          </td>
          <td class="whitespace-nowrap px-4 py-3 text-right">
            <div class="inline-flex items-center gap-2">
              <button
                v-if="canView"
                type="button"
                class="rounded-full p-1.5 text-blue-600 hover:bg-blue-50"
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
                class="rounded-full p-1.5 text-green-600 hover:bg-green-50"
                title="Edit"
                @click="goToEdit(row)"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                </svg>
              </button>
              <button
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
