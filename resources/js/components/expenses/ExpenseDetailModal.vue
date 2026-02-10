<script setup>
/**
 * Expense Details modal – compact layout, Expense Information (dense grid),
 * Financial Summary (one row), Attachments section. Permissions: view required; Edit if canEdit.
 */
import { ref, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import expensesApi from '@/services/expensesApi'

const props = defineProps({
  visible: { type: Boolean, default: false },
  expenseId: { type: [Number, String], default: null },
})

const emit = defineEmits(['close'])

const router = useRouter()
const auth = useAuthStore()

const expense = ref(null)
const loading = ref(false)
const loadError = ref(null)

const permissions = computed(() => auth.user?.permissions ?? [])
const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) && r.some((x) => (typeof x === 'string' ? x === 'superadmin' : x?.name === 'superadmin'))
})
const canEdit = computed(() => isSuperAdmin.value || permissions.value.includes('expense_tracker.edit') || permissions.value.includes('expense_tracker.update'))

function statusLabel(status) {
  if (status === 'approved') return 'Approved'
  if (status === 'pending') return 'Pending Approval'
  return status ?? '—'
}

function close() {
  emit('close')
}

function goToEdit() {
  if (expense.value?.id && canEdit.value) {
    close()
    router.push(`/expenses/${expense.value.id}/edit`)
  }
}

watch(
  () => [props.visible, props.expenseId],
  async ([visible, id]) => {
    if (!visible || !id) {
      expense.value = null
      loadError.value = null
      return
    }
    loading.value = true
    loadError.value = null
    try {
      const { data } = await expensesApi.show(id)
      expense.value = data.data ?? null
    } catch (e) {
      loadError.value = e?.response?.data?.message || 'Failed to load expense.'
      expense.value = null
    } finally {
      loading.value = false
    }
  }
)

const infoRows = computed(() => {
  const e = expense.value
  if (!e) return []
  return [
    { label: 'Expense ID', value: e.expense_id ?? `EXP-${e.id}`, highlight: true },
    { label: 'Product Category', value: e.product_category ?? '—' },
    { label: 'Added By', value: e.added_by ?? '—' },
    { label: 'Status', value: e.status, isStatus: true },
    { label: 'Invoice Number', value: e.invoice_number ?? '—' },
    { label: 'Expense Date', value: e.expense_date ?? '—' },
    { label: 'Created Date', value: e.created_at ?? '—' },
    { label: 'Product Description', value: e.product_description ?? '—', span: true },
    ...(e.comment ? [{ label: 'Comment / Remarks', value: e.comment, span: true }] : []),
  ]
})

const attachments = computed(() => expense.value?.attachments ?? [])
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="visible"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-500/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="expense-detail-title"
        @click.self="close"
      >
        <div class="my-8 w-full max-w-3xl max-h-[90vh] flex flex-col rounded-xl bg-white shadow-xl overflow-hidden">
          <!-- Header -->
          <div class="flex flex-shrink-0 items-center justify-between border-b border-gray-200 bg-white px-6 py-4">
            <h2 id="expense-detail-title" class="text-lg font-semibold text-gray-900">Expense Details</h2>
            <button
              type="button"
              class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
              aria-label="Close"
              @click="close"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="flex-1 min-h-0 overflow-y-auto px-6 py-5 space-y-5">
            <div v-if="loading" class="flex items-center justify-center py-12">
              <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </div>

            <div v-else-if="loadError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
              {{ loadError }}
              <button type="button" class="ml-2 font-medium underline hover:no-underline" @click="close">Close</button>
            </div>

            <template v-else-if="expense">
              <!-- Expense Information – dense 3-column grid -->
              <section>
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Expense Information</h3>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                  <template v-for="(row, i) in infoRows" :key="i">
                    <div
                      v-if="!row.span"
                      class="flex flex-col gap-0.5"
                    >
                      <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ row.label }}</dt>
                      <dd class="text-sm text-gray-900">
                        <span
                          v-if="row.isStatus"
                          class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                          :class="row.value === 'approved' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                        >
                          {{ statusLabel(row.value) }}
                        </span>
                        <span v-else :class="row.highlight ? 'font-semibold text-gray-900' : ''">{{ row.value || '—' }}</span>
                      </dd>
                    </div>
                    <div
                      v-else
                      class="col-span-2 flex flex-col gap-0.5 sm:col-span-3"
                    >
                      <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ row.label }}</dt>
                      <dd class="text-sm text-gray-900 leading-snug">{{ row.value || '—' }}</dd>
                    </div>
                  </template>
                </dl>
              </section>

              <!-- Financial Summary – one row -->
              <section class="rounded-lg border border-gray-200 bg-gray-50/80 px-4 py-4">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Financial Summary</h3>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                  <div class="flex flex-col gap-0.5">
                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">VAT %</span>
                    <span class="text-sm font-medium text-gray-900">
                      {{ expense.vat_percent != null ? expense.vat_percent + '%' : '—' }}
                    </span>
                  </div>
                  <div class="flex flex-col gap-0.5">
                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Amount (Without VAT)</span>
                    <span class="text-sm font-medium text-gray-900">
                      AED {{ expense.amount_without_vat != null ? Number(expense.amount_without_vat).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '—' }}
                    </span>
                  </div>
                  <div class="flex flex-col gap-0.5">
                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">VAT Amount</span>
                    <span class="text-sm font-medium text-gray-900">
                      AED {{ expense.vat_amount_currency != null ? Number(expense.vat_amount_currency).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '—' }}
                    </span>
                  </div>
                  <div class="flex flex-col gap-0.5">
                    <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Total Amount</span>
                    <span class="text-base font-semibold text-green-700">
                      AED {{ expense.full_amount != null ? Number(expense.full_amount).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '—' }}
                    </span>
                  </div>
                </div>
              </section>

              <!-- Attachments -->
              <section>
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Attachments</h3>
                <div v-if="attachments.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <div
                    v-for="(att, idx) in attachments"
                    :key="idx"
                    class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 shadow-sm"
                  >
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-600">
                      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-sm font-medium text-gray-900">{{ att.name || att.filename || 'Document' }}</p>
                      <p class="text-xs text-gray-500">{{ att.type || 'Document' }}</p>
                    </div>
                    <a
                      v-if="att.url"
                      :href="att.url"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="flex-shrink-0 rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                      title="Download"
                    >
                      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                      </svg>
                    </a>
                  </div>
                </div>
                <div v-else class="rounded-lg border border-dashed border-gray-200 bg-gray-50/50 px-4 py-6 text-center text-sm text-gray-500">
                  No attachments
                </div>
              </section>
            </template>
          </div>

          <!-- Footer -->
          <div class="flex flex-shrink-0 justify-end gap-3 border-t border-gray-200 bg-gray-50/50 px-6 py-4">
            <button
              v-if="expense && canEdit"
              type="button"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
              @click="goToEdit"
            >
              Edit Expense
            </button>
            <button
              type="button"
              class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition-colors"
              @click="close"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
