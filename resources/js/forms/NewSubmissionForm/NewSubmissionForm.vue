<script setup>
/**
 * New Submission form – Back Office fields. Same design pattern as Field Submission form.
 * Fields: Back Office Name, Call Verification, Documents Verification, Status,
 * Submission date, Activity, Account, Work Order, DU Status, Completed Date.
 */
import { ref, onMounted } from 'vue'
import leadSubmissionsApi from '@/services/leadSubmissionsApi'

const form = ref({
  executive_id: null,
  call_verification: '',
  documents_verification: '',
  status: '',
  submission_date: '',
  activity: '',
  account: '',
  work_order: '',
  du_status: '',
  completion_date: '',
})

const options = ref({
  executives: [],
  call_verification_options: [],
  documents_verification_options: [],
  du_status_options: [],
})

const STATUS_OPTIONS = [
  { value: '', label: 'Select' },
  { value: 'submitted', label: 'Submitted' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'pending_for_ata', label: 'Pending for ATA' },
  { value: 'pending_for_finance', label: 'Pending for Finance' },
  { value: 'pending_from_sales', label: 'Pending from Sales' },
  { value: 'unassigned', label: 'UnAssigned' },
]

const submitting = ref(false)

onMounted(async () => {
  try {
    const res = await leadSubmissionsApi.getBackOfficeOptions()
    const data = res?.data ?? res ?? {}
    options.value = {
      executives: data.executives ?? [],
      call_verification_options: data.call_verification_options ?? [],
      documents_verification_options: data.documents_verification_options ?? [],
      du_status_options: data.du_status_options ?? [],
    }
  } catch {
    // Keep empty options; form still usable
  }
})

function submit() {
  submitting.value = true
  setTimeout(() => {
    submitting.value = false
    alert('Form design only. Submit will be wired later.')
  }, 300)
}

function reset() {
  form.value = {
    executive_id: null,
    call_verification: '',
    documents_verification: '',
    status: '',
    submission_date: '',
    activity: '',
    account: '',
    work_order: '',
    du_status: '',
    completion_date: '',
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Section: same pattern as Field Submission form (Primary Information / Team Member) -->
    <div>
      <h3 class="border-b border-gray-200 pb-2 text-base font-semibold text-gray-800">
        New Submission
      </h3>
      <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <!-- Back Office Name, Call Verification, Documents Verification, Status -->
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Back Office Name</label>
          <select
            v-model="form.executive_id"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          >
            <option :value="null">Select</option>
            <option v-for="e in options.executives" :key="e.id" :value="e.id">{{ e.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Call Verification</label>
          <select
            v-model="form.call_verification"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          >
            <option value="">Select</option>
            <option v-for="o in options.call_verification_options" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Documents Verification</label>
          <select
            v-model="form.documents_verification"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          >
            <option value="">Select</option>
            <option v-for="o in options.documents_verification_options" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
          <select
            v-model="form.status"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          >
            <option v-for="s in STATUS_OPTIONS" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
        <!-- Submission date, Activity, Account, Work Order, DU Status -->
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Submission Date</label>
          <input
            v-model="form.submission_date"
            type="date"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Activity</label>
          <input
            v-model="form.activity"
            type="text"
            placeholder="Describe activity"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Account</label>
          <input
            v-model="form.account"
            type="text"
            placeholder="Enter account"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Work Order</label>
          <input
            v-model="form.work_order"
            type="text"
            placeholder="Work order"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">DU Status</label>
          <select
            v-model="form.du_status"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          >
            <option value="">Select</option>
            <option v-for="o in options.du_status_options" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <!-- Completed Date -->
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Completed Date</label>
          <input
            v-model="form.completion_date"
            type="date"
            class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
          />
        </div>
      </div>

      <div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-gray-200 pt-4">
        <button
          type="button"
          :disabled="submitting"
          class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-70"
          @click="submit"
        >
          <span v-if="submitting" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
          {{ submitting ? 'Saving…' : 'Save' }}
        </button>
        <button
          type="button"
          class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
          @click="reset"
        >
          Reset
        </button>
      </div>
    </div>
  </div>
</template>
