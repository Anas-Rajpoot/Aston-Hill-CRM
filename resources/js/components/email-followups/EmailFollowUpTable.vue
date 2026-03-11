<script setup>
/**
 * Email Follow-Up table – sortable, inline edit, status column (two statuses).
 */
import { ref, computed, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { canModuleAction } from '@/lib/accessControl'

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  sort: { type: String, default: 'email_date' },
  order: { type: String, default: 'desc' },
  loading: { type: Boolean, default: false },
  currentPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 15 },
  editOptions: { type: Object, default: () => ({}) },
  canInlineEdit: { type: Boolean, default: undefined },
  selectedIds: { type: Array, default: () => [] },
  allRowsSelected: { type: Boolean, default: false },
  someRowsSelected: { type: Boolean, default: false },
})

const emit = defineEmits(['sort', 'updateCell', 'toggleSelectAll', 'toggleRowSelection', 'delete'])

const auth = useAuthStore()
const canInlineEdit = computed(() => {
  if (props.canInlineEdit !== undefined) return props.canInlineEdit
  if (
    canModuleAction(auth.user, 'email-follow-up', 'edit', [
      'emails_followup.edit',
      'emails_followup.update',
    ])
  ) return true
  const perms = auth.user?.permissions ?? []
  if (perms.includes('emails_followup.edit')) return true
  const roles = auth.user?.roles ?? []
  if (!Array.isArray(roles)) return false
  const hasRole = (name) => roles.some((r) => (typeof r === 'string' ? r : r?.name) === name)
  return hasRole('superadmin')
})
const canDeleteAction = computed(() =>
  canModuleAction(auth.user, 'email-follow-up', 'delete', ['emails_followup.delete'])
)

function rowNumber(index) {
  return (props.currentPage - 1) * props.perPage + index + 1
}

const columnLabels = {
  id: 'SR',
  email_date: 'Email Date',
  subject: 'Subject',
  category: 'Category',
  request_from: 'Request From',
  sent_to: 'Sent To',
  creator: 'Added By',
  status: 'Status',
  status_date: 'Status Date',
}

const SORTABLE_COLUMNS = ['email_date', 'subject', 'category', 'request_from', 'sent_to', 'creator', 'status', 'status_date']

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

const TRUNCATE_COLUMNS = ['subject', 'category', 'request_from', 'sent_to', 'creator', 'status', 'email_date', 'status_date']
function shouldTruncate(col) {
  return TRUNCATE_COLUMNS.includes(col)
}
function cellTitle(row, col) {
  if (col === 'id' || !shouldTruncate(col)) return undefined
  return fullValue(row, col) || undefined
}

const DROPDOWN_COLUMNS = ['status', 'category']
const READ_ONLY_COLUMNS = ['id', 'email_date', 'creator', 'status_date']

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
const selectAllRef = ref(null)

watch(
  () => [props.allRowsSelected, props.someRowsSelected],
  () => {
    if (!selectAllRef.value) return
    selectAllRef.value.indeterminate = !props.allRowsSelected && props.someRowsSelected
  },
  { immediate: true }
)

function isRowSelected(rowId) {
  return props.selectedIds.includes(Number(rowId))
}

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

/** Approved = approved (legacy followed_up treated as approved). */
const isApproved = (status) => status === 'approved' || status === 'followed_up'
function statusLabel(status) {
  return isApproved(status) ? 'Closed' : 'Open'
}
function onStatusToggle(row) {
  if (!canInlineEdit.value || !row?.id) return
  const next = isApproved(row.status) ? 'pending' : 'approved'
  emit('updateCell', row.id, 'status', next)
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
        <span class="text-sm font-medium text-gray-600">Updating...</span>
      </div>
    </div>

    <table class="min-w-full border-2 border-black border-collapse bg-white">
      <thead>
        <tr class="bg-brand-primary border-b-2 border-green-700">
          <th class="w-10 px-2 py-3 text-center">
            <input
              ref="selectAllRef"
              type="checkbox"
              class="h-4 w-4 cursor-pointer rounded border-gray-300 text-brand-primary focus:ring-brand-primary"
              :checked="allRowsSelected"
              @change="emit('toggleSelectAll')"
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
              class="inline-flex items-center gap-1 font-semibold text-white hover:text-white/80"
              @click="toggleSort(col)"
            >
              {{ label(col) }}
              <svg v-if="sort === col" class="h-4 w-4" :class="order === 'asc' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
              </svg>
            </button>
            <span v-else class="font-semibold text-white">{{ label(col) }}</span>
          </th>
          <th
            v-if="canDeleteAction"
            scope="col"
            class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-white"
          >
            Actions
          </th>
        </tr>
      </thead>
      <tbody class="bg-white">
        <tr v-if="!loading && !data.length" class="border-b border-black bg-white">
          <td :colspan="columns.length + (canDeleteAction ? 2 : 1)" class="px-4 py-12 text-center text-sm text-gray-500">No email follow-up entries found.</td>
        </tr>
        <tr
          v-for="(row, rowIndex) in data"
          :key="row.id"
          class="border-b border-black bg-white hover:bg-gray-50/30"
        >
          <td class="px-2 py-3 text-center">
            <input
              type="checkbox"
              class="h-4 w-4 cursor-pointer rounded border-gray-300 text-brand-primary focus:ring-brand-primary"
              :checked="isRowSelected(row.id)"
              @change="emit('toggleRowSelection', row.id)"
            />
          </td>
          <td
            v-for="col in columns"
            :key="col"
            class="px-4 py-3 text-sm text-gray-900"
            :class="[
              { 'whitespace-nowrap': col !== 'subject' && col !== 'sent_to' },
              { 'cursor-pointer': canInlineEdit && isDropdownColumn(col) && !isEditing(row.id, col) && col !== 'status' },
              { 'cursor-text': canInlineEdit && isInputColumn(col) && !isEditing(row.id, col) },
            ]"
            :title="cellTitle(row, col)"
          >
            <template v-if="col === 'id'">
              {{ rowNumber(rowIndex) }}
            </template>
            <template v-else-if="col === 'email_date' || col === 'status_date'">
              {{ formatValue(row, col) }}
            </template>
            <template v-else-if="col === 'sent_to'">
              <a
                v-if="row.sent_to"
                :href="'mailto:' + row.sent_to"
                class="text-brand-primary underline hover:text-brand-primary-hover"
              >{{ truncate(row.sent_to) }}</a>
              <span v-else>—</span>
            </template>
            <template v-else-if="col === 'creator'">
              <div class="flex flex-col">
                <span class="font-semibold text-gray-900">{{ row.creator || '—' }}</span>
                <span class="text-xs text-gray-500">{{ row.creator_role || '—' }}</span>
              </div>
            </template>
            <template v-else-if="col === 'status'">
              <div class="flex flex-col items-start gap-0.5">
                <button
                  type="button"
                  :class="[
                    'inline-flex h-6 w-11 shrink-0 rounded-full border-2 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1',
                    canInlineEdit ? 'cursor-pointer' : 'cursor-default',
                    isApproved(row.status)
                      ? 'border-brand-primary bg-brand-primary focus:ring-brand-primary'
                      : 'border-red-500 bg-red-500 focus:ring-red-400',
                  ]"
                  :disabled="!canInlineEdit"
                  :aria-pressed="isApproved(row.status)"
                  @click="onStatusToggle(row)"
                >
                  <span
                    :class="[
                      'inline-block h-5 w-5 rounded-full bg-white shadow transition-transform',
                      isApproved(row.status) ? 'translate-x-5' : 'translate-x-0.5',
                    ]"
                  />
                </button>
                <span class="text-xs font-medium text-gray-900">{{ statusLabel(row.status) }}</span>
              </div>
            </template>
            <template v-else-if="canInlineEdit && isEditing(row.id, col) && isDropdownColumn(col)">
              <div class="flex flex-col gap-1.5">
                <select
                  v-model="inlineEditValue"
                  class="w-full min-w-[160px] max-w-[220px] rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                >
                  <option v-for="o in getOptionsForColumn(col)" :key="String(o.value)" :value="o.value">{{ o.label }}</option>
                </select>
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-brand-primary px-2 py-0.5 text-xs text-white hover:bg-brand-primary-hover" @click="saveInlineEdit">Save</button>
                </div>
              </div>
            </template>
            <template v-else-if="canInlineEdit && isEditing(row.id, col) && isInputColumn(col)">
              <div class="flex flex-col gap-1.5">
                <input
                  v-model="inlineEditValue"
                  type="text"
                  class="w-full min-w-[100px] max-w-[220px] rounded border border-gray-300 bg-white px-2 py-1 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                  @keydown.enter="saveInlineEdit"
                  @keydown.esc="cancelInlineEdit"
                />
                <div class="flex gap-1">
                  <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50" @click="cancelInlineEdit">Cancel</button>
                  <button type="button" class="rounded bg-brand-primary px-2 py-0.5 text-xs text-white hover:bg-brand-primary-hover" @click="saveInlineEdit">Save</button>
                </div>
              </div>
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
          <td v-if="canDeleteAction" class="whitespace-nowrap px-4 py-3 text-right">
            <button
              type="button"
              class="rounded-full p-1.5 text-red-600 hover:bg-red-50"
              title="Delete"
              @click="emit('delete', row)"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" />
              </svg>
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
