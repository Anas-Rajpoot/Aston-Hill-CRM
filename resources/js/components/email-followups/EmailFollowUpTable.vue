<script setup>
/**
 * Email Follow-Up table – sortable, inline edit, status column (two statuses).
 */
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  sort: { type: String, default: 'email_date' },
  order: { type: String, default: 'desc' },
  loading: { type: Boolean, default: false },
  currentPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 15 },
  editOptions: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['sort', 'updateCell'])

const auth = useAuthStore()
const canInlineEdit = computed(() => {
  const perms = auth.user?.permissions ?? []
  if (perms.includes('emails_followup.edit')) return true
  const roles = auth.user?.roles ?? []
  if (!Array.isArray(roles)) return false
  const hasRole = (name) => roles.some((r) => (typeof r === 'string' ? r : r?.name) === name)
  return hasRole('superadmin')
})

function rowNumber(index) {
  return (props.currentPage - 1) * props.perPage + index + 1
}

const columnLabels = {
  id: '#',
  email_date: 'Email Date',
  subject: 'Subject',
  category: 'Category',
  request_from: 'Request From',
  sent_to: 'Sent To',
  creator: 'Added By',
  status: 'Status',
}

const SORTABLE_COLUMNS = ['email_date', 'subject', 'category', 'request_from', 'sent_to', 'creator', 'status']

function label(col) {
  return columnLabels[col] ?? col
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

const TRUNCATE_LENGTH = 30
function truncate(str, max = TRUNCATE_LENGTH) {
  if (!str || typeof str !== 'string') return '—'
  const s = String(str)
  return s.length > max ? s.slice(0, max) + '...' : s
}

function fullValue(row, col) {
  const val = formatValue(row, col)
  if (val == null || val === '—') return ''
  return String(val)
}

const TRUNCATE_COLUMNS = ['subject', 'category', 'request_from', 'sent_to', 'creator', 'status', 'email_date']
function shouldTruncate(col) {
  return TRUNCATE_COLUMNS.includes(col)
}
function cellTitle(row, col) {
  if (col === 'id' || !shouldTruncate(col)) return undefined
  return fullValue(row, col) || undefined
}

const DROPDOWN_COLUMNS = ['status', 'category']
const READ_ONLY_COLUMNS = ['id', 'email_date', 'creator']

function isDropdownColumn(col) {
  return DROPDOWN_COLUMNS.includes(col)
}
function isInputColumn(col) {
  return !READ_ONLY_COLUMNS.includes(col) && !DROPDOWN_COLUMNS.includes(col)
}

function getCellValueForEdit(row, col) {
  return row[col] != null ? String(row[col]) : ''
}

const editingCell = ref(null)
const inlineEditValue = ref('')

function openDropdownEdit(row, col) {
  if (!canInlineEdit.value) return
  editingCell.value = { rowId: row.id, col }
  inlineEditValue.value = getCellValueForEdit(row, col)
}

function openInputEdit(row, col) {
  if (!canInlineEdit.value) return
  editingCell.value = { rowId: row.id, col }
  inlineEditValue.value = getCellValueForEdit(row, col)
}

function saveInlineEdit() {
  if (!editingCell.value) return
  const { rowId, col } = editingCell.value
  let value = inlineEditValue.value
  if (col === 'status') value = value || null
  emit('updateCell', rowId, col, value === '' ? null : value)
  editingCell.value = null
}

function cancelInlineEdit() {
  editingCell.value = null
}

function getOptionsForColumn(col) {
  const opt = props.editOptions || {}
  if (col === 'status') {
    return (opt.statuses || []).map((s) => ({ value: s.value, label: s.label }))
  }
  if (col === 'category') {
    const cats = opt.categories || []
    return [{ value: '', label: '—' }, ...cats.map((c) => ({ value: c, label: c }))]
  }
  return []
}

function isEditing(rowId, col) {
  return editingCell.value && editingCell.value.rowId === rowId && editingCell.value.col === col
}

const STATUS_BADGES = {
  pending: 'bg-amber-100 text-amber-800',
  followed_up: 'bg-green-100 text-green-700',
}
function statusBadgeClass(status) {
  return STATUS_BADGES[status] ?? 'bg-gray-100 text-gray-700'
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
        <span class="text-sm font-medium text-gray-600">Updating...</span>
      </div>
    </div>

    <table class="min-w-full border border-gray-400 border-collapse">
      <thead>
        <tr class="border-b border-gray-400 bg-green-600">
          <th
            v-for="col in columns"
            :key="col"
            scope="col"
            class="whitespace-nowrap px-4 py-3 text-left text-sm font-bold uppercase tracking-wider text-white"
          >
            <button
              v-if="sortable(col)"
              type="button"
              class="inline-flex items-center gap-1 font-bold text-white hover:text-white/90"
              @click="toggleSort(col)"
            >
              {{ label(col) }}
              <svg v-if="sort === col" class="h-4 w-4" :class="order === 'asc' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
              </svg>
            </button>
            <span v-else class="font-bold text-white">{{ label(col) }}</span>
          </th>
        </tr>
      </thead>
      <tbody class="bg-white">
        <tr v-if="!loading && !data.length" class="border-b border-gray-400 bg-white">
          <td :colspan="columns.length" class="px-4 py-12 text-center text-gray-500">No email follow-up entries found.</td>
        </tr>
        <tr
          v-for="(row, rowIndex) in data"
          :key="row.id"
          class="border-b border-gray-400 bg-white hover:bg-gray-50/50"
        >
          <td
            v-for="col in columns"
            :key="col"
            class="whitespace-nowrap px-4 py-3 text-sm text-gray-900"
            :class="{ 'cursor-pointer': canInlineEdit && isDropdownColumn(col) && !isEditing(row.id, col), 'cursor-text': canInlineEdit && isInputColumn(col) && !isEditing(row.id, col) }"
            :title="cellTitle(row, col)"
          >
            <template v-if="col === 'id'">
              {{ rowNumber(rowIndex) }}
            </template>
            <template v-else-if="canInlineEdit && isEditing(row.id, col) && isDropdownColumn(col)">
              <div class="flex flex-col gap-1.5">
                <select
                  v-model="inlineEditValue"
                  class="w-full min-w-[160px] max-w-[220px] rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                >
                  <option v-for="o in getOptionsForColumn(col)" :key="String(o.value)" :value="o.value">{{ o.label }}</option>
                </select>
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700" @click="saveInlineEdit">Save</button>
                </div>
              </div>
            </template>
            <template v-else-if="canInlineEdit && isEditing(row.id, col) && isInputColumn(col)">
              <div class="flex flex-col gap-1.5">
                <input
                  v-model="inlineEditValue"
                  type="text"
                  class="w-full min-w-[100px] max-w-[220px] rounded border border-gray-300 bg-white px-2 py-1 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                />
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700" @click="saveInlineEdit">Save</button>
                </div>
              </div>
            </template>
            <template v-else-if="col === 'status' && canInlineEdit && isEditing(row.id, 'status')">
              <div class="flex flex-col gap-1.5">
                <select
                  v-model="inlineEditValue"
                  class="min-w-[160px] rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                >
                  <option v-for="s in (editOptions.statuses || [])" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700" @click="saveInlineEdit">Save</button>
                </div>
              </div>
            </template>
            <template v-else-if="col === 'status' && canInlineEdit">
              <span
                :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium cursor-pointer hover:ring-2 hover:ring-green-400', statusBadgeClass(row.status)]"
                @dblclick="openDropdownEdit(row, 'status')"
              >
                {{ row.status === 'followed_up' ? 'Followed Up' : (row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : '—') }}
              </span>
            </template>
            <template v-else-if="col === 'status'">
              <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(row.status)]">
                {{ row.status === 'followed_up' ? 'Followed Up' : (row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : '—') }}
              </span>
            </template>
            <template v-else-if="canInlineEdit && isDropdownColumn(col)">
              <span
                class="cursor-pointer hover:bg-gray-100 rounded px-0.5 min-w-0 inline-block max-w-full truncate"
                @click="openDropdownEdit(row, col)"
                @dblclick="openDropdownEdit(row, col)"
              >{{ truncate(formatValue(row, col)) }}</span>
            </template>
            <template v-else-if="canInlineEdit && isInputColumn(col)">
              <span class="cursor-text hover:bg-gray-50 rounded px-0.5" @dblclick="openInputEdit(row, col)">{{ truncate(formatValue(row, col)) }}</span>
            </template>
            <template v-else>
              {{ truncate(formatValue(row, col)) }}
            </template>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
