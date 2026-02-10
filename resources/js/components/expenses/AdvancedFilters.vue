<script setup>
/**
 * Advanced filters for Expense Tracker. No section headings, 4 filters per row. Dates: dd-mm-yyyy.
 * Apply and Reset buttons at bottom.
 */
import { computed } from 'vue'
import { toDdMmYyyy, fromDdMmYyyy } from '@/lib/dateFormat'

const props = defineProps({
  visible: { type: Boolean, default: false },
  filters: { type: Object, required: true },
  filterOptions: {
    type: Object,
    default: () => ({
      categories: [],
      vat_options: [],
      status_options: [],
    }),
  },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'reset'])

const expenseDateFromDisplay = computed({
  get: () => toDdMmYyyy(props.filters.expense_date_from),
  set: (v) => { props.filters.expense_date_from = fromDdMmYyyy(v) || '' },
})
const expenseDateToDisplay = computed({
  get: () => toDdMmYyyy(props.filters.expense_date_to),
  set: (v) => { props.filters.expense_date_to = fromDdMmYyyy(v) || '' },
})
const createdFromDisplay = computed({
  get: () => toDdMmYyyy(props.filters.created_from),
  set: (v) => { props.filters.created_from = fromDdMmYyyy(v) || '' },
})
const createdToDisplay = computed({
  get: () => toDdMmYyyy(props.filters.created_to),
  set: (v) => { props.filters.created_to = fromDdMmYyyy(v) || '' },
})
</script>

<template>
  <Transition
    enter-active-class="transition ease-out duration-200"
    enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition ease-in duration-150"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 -translate-y-2"
  >
    <div v-show="visible" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
      <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Expense Date From</label>
          <input
            v-model="expenseDateFromDisplay"
            type="text"
            placeholder="dd-mm-yyyy"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Expense Date To</label>
          <input
            v-model="expenseDateToDisplay"
            type="text"
            placeholder="dd-mm-yyyy"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Created Date From</label>
          <input
            v-model="createdFromDisplay"
            type="text"
            placeholder="dd-mm-yyyy"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Created Date To</label>
          <input
            v-model="createdToDisplay"
            type="text"
            placeholder="dd-mm-yyyy"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Product Category</label>
          <select
            v-model="filters.product_category"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          >
            <option value="">All Categories</option>
            <option v-for="c in filterOptions.categories" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Added By</label>
          <input
            v-model="filters.added_by"
            type="text"
            placeholder="Enter name"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Amount Min (AED)</label>
          <input
            v-model.number="filters.amount_min"
            type="number"
            step="0.01"
            min="0"
            placeholder="0.00"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Amount Max (AED)</label>
          <input
            v-model.number="filters.amount_max"
            type="number"
            step="0.01"
            min="0"
            placeholder="0.00"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">VAT Applicable</label>
          <select
            v-model="filters.vat_applicable"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          >
            <option v-for="o in filterOptions.vat_options" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Product Description</label>
          <input
            v-model="filters.product_description"
            type="text"
            placeholder="Search description"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Invoice Number</label>
          <input
            v-model="filters.invoice_number"
            type="text"
            placeholder="Enter invoice number"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Status</label>
          <select
            v-model="filters.status"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          >
            <option value="">All</option>
            <option v-for="o in filterOptions.status_options" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
      </div>

      <div class="flex gap-3 border-t border-gray-200 px-4 py-3">
        <button
          type="button"
          class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
          :disabled="loading"
          @click="$emit('apply')"
        >
          Apply Filters
        </button>
        <button
          type="button"
          class="inline-flex items-center rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
          :disabled="loading"
          @click="$emit('reset')"
        >
          Reset Filters
        </button>
      </div>
    </div>
  </Transition>
</template>
