<script setup>
/**
 * Employee table – sortable columns, checkboxes, View / Edit / Deactivate actions.
 * Super admin: cannot be deactivated; only that super admin can edit their own row; row visible only to super admin.
 */
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { toDdMmYyyy } from '@/lib/dateFormat'

const router = useRouter()
const auth = useAuthStore()

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  sort: { type: String, default: 'name' },
  order: { type: String, default: 'asc' },
  loading: { type: Boolean, default: false },
  currentPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 15 },
  selectedIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['sort', 'update:selectedIds', 'deactivate', 'activate', 'showMessage'])

const COLUMN_LABELS = {
  id: 'ID',
  employee_number: 'Employee ID',
  name: 'Employee Name',
  roles: 'Role(s)',
  team_leader: 'Team Leader',
  manager: 'Manager',
  department: 'Department',
  email: 'Primary Email',
  phone: 'Contact No',
  cnic_number: 'GMIC No',
  extension: 'Extension',
  status: 'Status',
  joining_date: 'Joining Date',
  terminate_date: 'Terminate Date',
}

const SORTABLE_COLUMNS = [
  'id', 'employee_number', 'name', 'team_leader', 'manager', 'department',
  'email', 'phone', 'cnic_number', 'extension', 'status', 'joining_date', 'terminate_date',
]

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
  if (Array.isArray(val)) return val.length ? val.join(', ') : '—'
  if (typeof val === 'object') return val?.name ?? '—'
  return val
}

/** Display date as dd-mm-yyyy. */
function formatDate(val) {
  if (!val) return '—'
  const ymd = typeof val === 'string' ? val : (val instanceof Date ? val.toISOString().slice(0, 10) : '')
  return toDdMmYyyy(ymd) || '—'
}

/** Mask phone for display: +971 XXX XXX XXX */
function maskPhone(phone) {
  if (!phone || typeof phone !== 'string') return '—'
  const digits = phone.replace(/\D/g, '')
  if (digits.length < 4) return '—'
  return `+971 XXX XXX ${digits.slice(-3)}`
}

/** Mask GMIC/CNIC for display: xxxx-xxxxx-x */
function maskGmic(val) {
  if (!val || typeof val !== 'string') return '—'
  const s = val.trim()
  if (!s) return '—'
  return 'xxxx-xxxxx-x'
}

/** Role display: replace _ with space, capitalize first letter of each word. */
function formatRoleName(role) {
  if (!role || typeof role !== 'string') return ''
  return role
    .split('_')
    .filter((word) => word.length > 0)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(' ')
}

const selectedSet = computed(() => new Set((props.selectedIds || []).map(String)))
const allRowIds = computed(() => props.data.map((r) => r.id))
const isAllSelected = computed(() => allRowIds.value.length > 0 && allRowIds.value.every((id) => selectedSet.value.has(String(id))))

function toggleSelectAll() {
  if (isAllSelected.value) {
    emit('update:selectedIds', [])
  } else {
    emit('update:selectedIds', [...allRowIds.value])
  }
}

function toggleRow(id) {
  const idStr = String(id)
  const next = new Set(selectedSet.value)
  if (next.has(idStr)) next.delete(idStr)
  else next.add(idStr)
  emit('update:selectedIds', Array.from(next).map(Number))
}

function rowNumber(index) {
  return (props.currentPage - 1) * props.perPage + index + 1
}

function goToView(row) {
  if (row?.id) router.push(`/employees/${row.id}`)
}

function goToEdit(row) {
  if (row?.id) router.push(`/employees/${row.id}/edit`)
}

function goToHistory(row) {
  if (row?.id) router.push({ path: '/login-logs', query: { user_id: row.id } })
}

function onDeactivate(row) {
  if (row?.id) emit('deactivate', row)
}

function onActivate(row) {
  if (row?.id) emit('activate', row)
}

function isActive(row) {
  return row?.status === 'approved'
}

const STATUS_BADGES = {
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
  pending: 'bg-gray-100 text-gray-700',
}
function statusBadgeClass(status) {
  return STATUS_BADGES[status] ?? 'bg-gray-100 text-gray-700'
}

function statusLabel(status) {
  if (status === 'approved') return 'Active'
  if (status === 'rejected') return 'Inactive'
  if (status === 'pending') return 'Pending'
  return status ?? '—'
}

function isSuperAdmin(row) {
  const roles = row?.roles
  if (!Array.isArray(roles)) return false
  return roles.some((r) => (typeof r === 'string' ? r : r?.name) === 'superadmin')
}

/** Only super admin can edit a super admin row (and only their own). */
function canEditRow(row) {
  if (!isSuperAdmin(row)) return true
  return auth.user?.id === row.id
}

/** Can show Activate/Deactivate (not super admin). */
function canToggleActiveRow(row) {
  return !isSuperAdmin(row)
}

function onCheckboxCellClick(row) {
  if (isSuperAdmin(row)) {
    emit('showMessage', 'Super admin cannot be selected.')
  }
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
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
        <span class="text-sm font-medium text-gray-600">Loading...</span>
      </div>
    </div>

    <table class="min-w-full border border-gray-400 border-collapse">
      <thead>
        <tr class="border-b border-gray-400 bg-green-600">
          <th class="w-10 px-3 py-3 text-left">
            <input
              type="checkbox"
              class="rounded border-gray-300"
              aria-label="Select all"
              :checked="isAllSelected"
              :indeterminate="selectedSet.size > 0 && !isAllSelected"
              @change="toggleSelectAll"
            />
          </th>
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
          <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-sm font-bold uppercase tracking-wider text-white">Actions</th>
        </tr>
      </thead>
      <tbody class="bg-white">
        <tr v-if="!loading && !data.length" class="border-b border-gray-400 bg-white">
          <td :colspan="columns.length + 2" class="px-4 py-12 text-center text-gray-500">No employees found.</td>
        </tr>
        <tr
          v-for="(row, rowIndex) in data"
          :key="row.id"
          class="border-b border-gray-400 bg-white hover:bg-gray-50/50"
        >
          <td
            class="w-10 px-3 py-3"
            :class="{ 'cursor-not-allowed': isSuperAdmin(row) }"
            :title="isSuperAdmin(row) ? 'Super admin cannot be selected.' : undefined"
            @click="onCheckboxCellClick(row)"
          >
            <input
              type="checkbox"
              class="rounded border-gray-300 disabled:cursor-not-allowed disabled:opacity-60"
              :checked="selectedSet.has(String(row.id))"
              :disabled="isSuperAdmin(row)"
              aria-label="Select row"
              title="Super admin cannot be selected."
              @change="toggleRow(row.id)"
              @click.stop
            />
          </td>
          <td
            v-for="col in columns"
            :key="col"
            class="whitespace-nowrap px-4 py-3 text-sm text-gray-900"
          >
            <template v-if="col === 'roles'">
              <div class="flex flex-wrap gap-1">
                <span
                  v-for="r in (row.roles || [])"
                  :key="r"
                  class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800"
                >
                  {{ formatRoleName(r) }}
                </span>
                <span v-if="!row.roles?.length">—</span>
              </div>
            </template>
            <template v-else-if="col === 'status'">
              <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(row.status)]">
                {{ statusLabel(row.status) }}
              </span>
            </template>
            <template v-else-if="col === 'phone'">
              {{ maskPhone(row.phone) }}
            </template>
            <template v-else-if="col === 'cnic_number'">
              {{ maskGmic(row.cnic_number) }}
            </template>
            <template v-else-if="col === 'extension'">
              <span v-if="row.extension" class="inline-flex items-center gap-1">
                <svg class="h-3.5 w-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                {{ row.extension }}
              </span>
              <span v-else>—</span>
            </template>
            <template v-else-if="col === 'joining_date' || col === 'terminate_date'">
              {{ formatDate(row[col]) }}
            </template>
            <template v-else>
              {{ formatValue(row, col) }}
            </template>
          </td>
          <td class="whitespace-nowrap px-4 py-3 text-left">
            <div class="inline-flex items-center gap-2">
              <!-- View / Edit / History icons always at left; Activate/Deactivate or space to the right -->
              <button
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
                v-if="canEditRow(row)"
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
                title="History / Activity"
                @click="goToHistory(row)"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </button>
              <button
                v-if="canToggleActiveRow(row) && isActive(row)"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full border-0 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 shadow-none hover:bg-red-100"
                title="Deactivate"
                @click="onDeactivate(row)"
              >
                <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-red-700 text-white">
                  <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </span>
                Deactivate
              </button>
              <button
                v-if="canToggleActiveRow(row) && !isActive(row)"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full border border-green-600 bg-green-50 px-2.5 py-1.5 text-xs font-semibold text-green-700 hover:bg-green-100"
                title="Activate"
                @click="onActivate(row)"
              >
                <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-green-600 text-white">
                  <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </span>
                Activate
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
