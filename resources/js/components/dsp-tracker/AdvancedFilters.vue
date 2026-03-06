<script setup>
/**
 * Advanced filters for DSP Tracker: Activity Number, Account Number, Request Type,
 * Appointment Date From/To, Product, SO Number, Rejection Reason, Verifier Name.
 * (Request Status and Company Name are in general filters and should not be duplicated here.)
 * Dates: dd-Mon-yyyy with calendar picker.
 */
import DateInputDdMmYyyy from '@/components/DateInputDdMmYyyy.vue'

const props = defineProps({
  visible: { type: Boolean, default: false },
  filters: { type: Object, required: true },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'reset'])
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
      <div class="border-b border-gray-100 bg-gray-50 px-4 py-2">
        <p class="text-xs font-medium text-gray-600">All filters</p>
        <p class="text-xs text-gray-500">Activity Number, Account Number, Request Type, Appointment Date, Product, SO Number, Rejection Reason, Verifier Name</p>
      </div>
      <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Activity Number</label>
          <input
            v-model="filters.activity_number"
            type="text"
            placeholder="Search activity number..."
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Account Number</label>
          <input
            v-model="filters.account_number"
            type="text"
            placeholder="Search account..."
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Request Type</label>
          <input
            v-model="filters.request_type"
            type="text"
            placeholder="Search request type..."
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Appointment Date From</label>
          <DateInputDdMmYyyy v-model="filters.appointment_date_from" placeholder="DD-MMM-YYYY" :disabled="loading" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Appointment Date To</label>
          <DateInputDdMmYyyy v-model="filters.appointment_date_to" placeholder="DD-MMM-YYYY" :disabled="loading" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Product</label>
          <input
            v-model="filters.product"
            type="text"
            placeholder="Search product..."
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">SO Number</label>
          <input
            v-model="filters.so_number"
            type="text"
            placeholder="Search SO number..."
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Rejection Reason</label>
          <input
            v-model="filters.rejection_reason"
            type="text"
            placeholder="Search rejection reason..."
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Verifier Name</label>
          <input
            v-model="filters.verifier_name"
            type="text"
            placeholder="Search verifier..."
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          />
        </div>
        <div class="sm:col-span-2 lg:col-span-2">
          <label class="mb-1 block text-xs font-medium text-transparent select-none">Actions</label>
          <div class="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap">
            <button
              type="button"
              class="inline-flex w-full items-center justify-center rounded bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-50 sm:w-auto sm:flex-1"
              :disabled="loading"
              @click="$emit('apply')"
            >
              Apply Filters
            </button>
            <button
              type="button"
              class="inline-flex w-full items-center justify-center rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 sm:w-auto sm:flex-1"
              :disabled="loading"
              @click="$emit('reset')"
            >
              Clear Filters
            </button>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>
