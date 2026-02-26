<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()

function goToView(row) {
  if (row?.id) router.push(`/special-requests/${row.id}`)
}

function goToEdit(row) {
  if (row?.id) router.push(`/special-requests/${row.id}/edit`)
}

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  sort: { type: String, default: 'created_at' },
  order: { type: String, default: 'desc' },
  loading: { type: Boolean, default: false },
  currentPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 15 },
  editOptions: { type: Object, default: () => ({}) },
  selectedIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['sort', 'updateCell', 'update:selectedIds', 'viewHistory'])

const auth = useAuthStore()
const canInlineEdit = computed(() => {
  const roles = auth.user?.roles ?? []
  if (!Array.isArray(roles)) return false
  return roles.some((r) => {
    const name = typeof r === 'string' ? r : r?.name
    return name === 'superadmin' || name === 'backoffice' || name === 'back_office'
  })
})

const COLUMN_LABELS = {
  id: 'SR',
  submitted_at: 'Submission Date',
  created_at: 'Created',
  company_name: 'Company Name',
  account_number: 'Account Number',
  request_type: 'Request Type',
  complete_address: 'Address',
  special_instruction: 'Special Instruction',
  sales_agent: 'Sales Agent',
  team_leader: 'Team Leader',
  manager: 'Manager',
  status: 'Status',
  creator: 'Created By',
  updated_at: 'Last Updated',
}

const SORTABLE = new Set([
  'id', 'submitted_at', 'created_at', 'company_name', 'account_number', 'request_type',
  'sales_agent', 'team_leader', 'manager', 'status', 'updated_at',
])

const editingCell = ref(null)
const editingValue = ref('')
const inlineEditError = ref('')

const DROPDOWN_COLUMNS = ['status', 'request_type', 'manager', 'team_leader', 'sales_agent']
const READ_ONLY_COLUMNS = ['id', 'created_at', 'submitted_at', 'updated_at', 'creator']

function isEditing(rowId, col) {
  return editingCell.value && editingCell.value.rowId === rowId && editingCell.value.col === col
}

function isDropdownColumn(col) {
  return DROPDOWN_COLUMNS.includes(col)
}

function isInputColumn(col) {
  return !READ_ONLY_COLUMNS.includes(col) && !DROPDOWN_COLUMNS.includes(col)
}

function toggleSort(col) {
  if (!SORTABLE.has(col)) return
  const newOrder = props.sort === col && props.order === 'asc' ? 'desc' : 'asc'
  emit('sort', { sort: col, order: newOrder })
}

function startEdit(row, col) {
  if (!canInlineEdit.value) return
  editingCell.value = { rowId: row.id, col }
  if (col === 'manager') editingValue.value = row.manager_id ?? ''
  else if (col === 'team_leader') editingValue.value = row.team_leader_id ?? ''
  else if (col === 'sales_agent') editingValue.value = row.sales_agent_id ?? ''
  else editingValue.value = row[col] ?? ''
  inlineEditError.value = ''
}

function saveEdit(rowId, col) {
  const value = editingValue.value

  if (col === 'company_name' && !String(value || '').trim()) {
    inlineEditError.value = 'Company name is required.'
    return
  }
  if (col === 'request_type' && !String(value || '').trim()) {
    inlineEditError.value = 'Request type is required.'
    return
  }
  if (['manager', 'team_leader', 'sales_agent'].includes(col) && !value) {
    inlineEditError.value = `${(COLUMN_LABELS[col] || col)} is required.`
    return
  }

  emit('updateCell', rowId, col, value)
  editingCell.value = null
  editingValue.value = ''
  inlineEditError.value = ''
}

function cancelEdit() {
  editingCell.value = null
  editingValue.value = ''
  inlineEditError.value = ''
}

function getOptionsForColumn(col) {
  const opt = props.editOptions || {}
  if (col === 'status') return [{ value: '', label: 'Select' }, ...(opt.statuses || [])]
  if (col === 'request_type') return [{ value: '', label: 'Select' }, ...(opt.request_types || [])]
  if (col === 'manager') return [{ value: '', label: 'Select' }, ...(opt.managers || []).map((m) => ({ value: m.id, label: m.name }))]
  if (col === 'team_leader') return [{ value: '', label: 'Select' }, ...(opt.team_leaders || []).map((t) => ({ value: t.id, label: t.name }))]
  if (col === 'sales_agent') return [{ value: '', label: 'Select' }, ...(opt.sales_agents || []).map((s) => ({ value: s.id, label: s.name }))]
  return []
}

function statusClass(s) {
  const status = (s || '').toLowerCase()
  if (status === 'approved') return 'bg-green-100 text-green-800'
  if (status === 'rejected') return 'bg-red-100 text-red-800'
  if (status === 'submitted') return 'bg-blue-100 text-blue-800'
  return 'bg-gray-100 text-gray-700'
}

function cellVal(row, col) {
  if (col === 'creator') return row.creator?.name ?? row.creator ?? '—'
  return row[col] ?? '—'
}

function cellTitle(row, col) {
  const val = cellVal(row, col)
  return val == null ? '' : String(val)
}

function truncate(val, len = 40) {
  if (!val || val === '—') return val
  const s = String(val)
  return s.length > len ? s.slice(0, len) + '...' : s
}

const allSelected = computed({
  get: () => props.data.length > 0 && props.data.every((r) => props.selectedIds.includes(r.id)),
  set: (val) => emit('update:selectedIds', val ? props.data.map((r) => r.id) : []),
})

function toggleRow(id) {
  const ids = [...props.selectedIds]
  const idx = ids.indexOf(id)
  if (idx >= 0) ids.splice(idx, 1)
  else ids.push(id)
  emit('update:selectedIds', ids)
}
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full border-collapse text-left text-sm">
      <thead class="bg-green-600">
        <tr>
          <th class="w-10 border-b border-black px-3 py-2.5 text-center">
            <input type="checkbox" v-model="allSelected" class="rounded border-gray-300 text-green-600 focus:ring-green-500" />
          </th>
          <th class="w-10 border-b border-black px-3 py-2.5 text-center text-xs font-semibold text-white uppercase">SR</th>
          <th
            v-for="col in columns"
            :key="col"
            class="whitespace-nowrap border-b border-black px-3 py-2.5 text-xs font-semibold uppercase text-white"
            :class="{ 'cursor-pointer select-none hover:text-white/90': SORTABLE.has(col) }"
            @click="toggleSort(col)"
          >
            <span class="inline-flex items-center gap-1">
              {{ COLUMN_LABELS[col] ?? col }}
              <template v-if="SORTABLE.has(col)">
                <svg v-if="sort === col && order === 'asc'" class="h-3.5 w-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                <svg v-else-if="sort === col && order === 'desc'" class="h-3.5 w-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                <svg v-else class="h-3.5 w-3.5 text-white/70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
              </template>
            </span>
          </th>
          <th class="whitespace-nowrap border-b border-black px-3 py-2.5 text-xs font-semibold uppercase text-white">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading">
          <td :colspan="columns.length + 3" class="px-4 py-12 text-center">
            <svg class="mx-auto h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
          </td>
        </tr>
        <tr v-else-if="!data.length">
          <td :colspan="columns.length + 3" class="px-4 py-10 text-center text-sm text-gray-500">No special requests found.</td>
        </tr>
        <tr
          v-else
          v-for="(row, idx) in data"
          :key="row.id"
          class="group transition-colors hover:bg-gray-50"
          :class="{ 'bg-green-50/40': selectedIds.includes(row.id) }"
        >
          <td class="border-b border-gray-200 px-3 py-2 text-center">
            <input type="checkbox" :checked="selectedIds.includes(row.id)" @change="toggleRow(row.id)" class="rounded border-gray-300 text-green-600 focus:ring-green-500" />
          </td>
          <td class="border-b border-gray-200 px-3 py-2 text-xs text-gray-500">{{ (currentPage - 1) * perPage + idx + 1 }}</td>
          <td
            v-for="col in columns"
            :key="col"
            class="whitespace-nowrap border-b border-gray-200 px-3 py-2 text-sm text-gray-700"
            :class="{ 'cursor-pointer': canInlineEdit && isDropdownColumn(col) && !isEditing(row.id, col), 'cursor-text': canInlineEdit && isInputColumn(col) && !isEditing(row.id, col) }"
          >
            <template v-if="canInlineEdit && isEditing(row.id, col) && isDropdownColumn(col)">
              <div class="flex flex-col gap-1.5">
                <select
                  v-model="editingValue"
                  class="w-full min-w-[160px] max-w-[220px] rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  @keydown.enter="saveEdit(row.id, col)"
                  @keydown.esc="cancelEdit"
                >
                  <option v-for="o in getOptionsForColumn(col)" :key="String(o.value)" :value="o.value">{{ o.label }}</option>
                </select>
                <p v-if="inlineEditError" class="text-xs text-red-600">{{ inlineEditError }}</p>
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelEdit">Cancel</button>
                  <button type="button" class="rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700" @click="saveEdit(row.id, col)">Save</button>
                </div>
              </div>
            </template>
            <template v-else-if="canInlineEdit && isEditing(row.id, col) && isInputColumn(col)">
              <div class="flex flex-col gap-1.5">
                <input
                  v-model="editingValue"
                  class="w-full min-w-[160px] max-w-[220px] rounded border bg-white px-3 py-1.5 pr-8 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  :class="inlineEditError ? 'border-red-500' : 'border-gray-300'"
                  @input="inlineEditError = ''"
                  @keydown.enter="saveEdit(row.id, col)"
                  @keydown.esc="cancelEdit"
                />
                <p v-if="inlineEditError" class="text-xs text-red-600">{{ inlineEditError }}</p>
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelEdit">Cancel</button>
                  <button type="button" class="rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700" @click="saveEdit(row.id, col)">Save</button>
                </div>
              </div>
            </template>
            <template v-else-if="col === 'status'">
              <span
                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                :class="[statusClass(row.status), canInlineEdit ? 'cursor-pointer hover:ring-2 hover:ring-green-400' : '']"
                @click="canInlineEdit && startEdit(row, col)"
              >
                {{ row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : '—' }}
              </span>
            </template>
            <template v-else>
              <span
                class="inline-block max-w-[220px] truncate align-middle"
                :class="{ 'cursor-pointer hover:bg-gray-100 rounded px-0.5': canInlineEdit && isDropdownColumn(col), 'cursor-text hover:bg-gray-50 rounded px-0.5': canInlineEdit && isInputColumn(col) }"
                :title="cellTitle(row, col)"
                @click="canInlineEdit && isDropdownColumn(col) && startEdit(row, col)"
                @dblclick="canInlineEdit && isInputColumn(col) && startEdit(row, col)"
              >
                {{ truncate(cellVal(row, col)) }}
              </span>
            </template>
          </td>
          <td class="border-b border-gray-200 px-3 py-2">
            <div class="flex items-center gap-1">
              <button type="button" class="rounded p-1 text-blue-600 hover:bg-blue-50" title="View" @click="goToView(row)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
              </button>
              <button type="button" class="rounded p-1 text-green-600 hover:bg-green-50" title="Edit" @click="goToEdit(row)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
              </button>
              <button type="button" class="rounded p-1 text-purple-600 hover:bg-purple-50" title="History" @click="$emit('viewHistory', row)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
