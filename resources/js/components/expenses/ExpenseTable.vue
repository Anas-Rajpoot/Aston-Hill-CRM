<script setup>
/**
 * Expense table – sortable columns, View / Edit / Delete actions (icons).
 */
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  sort: { type: String, default: 'expense_date' },
  order: { type: String, default: 'desc' },
  loading: { type: Boolean, default: false },
  currentPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 15 },
})

const emit = defineEmits(['sort', 'delete', 'view'])

const permissions = computed(() => auth.user?.permissions ?? [])
const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) && r.some((x) => (typeof x === 'string' ? x === 'superadmin' : x?.name === 'superadmin'))
})
const canView = computed(() => isSuperAdmin.value || permissions.value.includes('expense_tracker.view') || permissions.value.includes('expense_tracker.list'))
const canEdit = computed(() => isSuperAdmin.value || permissions.value.includes('expense_tracker.edit') || permissions.value.includes('expense_tracker.update'))
const canDelete = computed(() => isSuperAdmin.value || permissions.value.includes('expense_tracker.delete'))

const COLUMN_LABELS = {
  id: 'ID',
  expense_date: 'Expense Date',
  product_category: 'Product Category',
  product_description: 'Product Description',
  invoice_number: 'Invoice Number',
  vat_amount: 'VAT %',
  amount_without_vat: 'Amount (Without VAT)',
  vat_amount_currency: 'VAT Amount',
  full_amount: 'Total Amount',
  added_by: 'Added By',
  created_at: 'Created Date',
  status: 'Status',
}

const SORTABLE_COLUMNS = ['id', 'expense_date', 'product_category', 'product_description', 'invoice_number', 'vat_amount', 'amount_without_vat', 'full_amount', 'added_by', 'created_at', 'status']

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
  if (status === 'approved') return 'Approved'
  if (status === 'pending') return 'Pending Approval'
  return status ?? '—'
}

function goToView(row) {
  if (row?.id && canView.value) emit('view', row)
}

function goToEdit(row) {
  if (row?.id && canEdit.value) router.push(`/expenses/${row.id}/edit`)
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
          <td :colspan="columns.length + 1" class="px-4 py-12 text-center text-sm text-gray-500">No expenses found.</td>
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
          >
            <template v-if="col === 'status'">
              <span
                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="row.status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
              >
                {{ statusLabel(row.status) }}
              </span>
            </template>
            <template v-else-if="col === 'full_amount' || col === 'amount_without_vat' || col === 'vat_amount_currency'">
              {{ formatValue(row, col) }}{{ row[col] !== '—' && (col === 'full_amount' || col === 'amount_without_vat' || col === 'vat_amount_currency') ? ' AED' : '' }}
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
