<script setup>
/**
 * DSP Tracker Details modal – design per image: two-column key-value layout,
 * Request Status as grey badge, Close + Edit Record footer.
 * Shows all fields from CSV import: Activity Number, Company Name, Account Number, Request Type,
 * Appointment Date (e.g. 15 Jan 2024), Appointment Time, DSP OM ID, SO Number, Request Status,
 * Verifier Name, Verifier Number, Rejection Reason, Product, Uploaded By, Uploaded At.
 */
import { computed } from 'vue'
import { toDdMonYyyy, fromDdMmYyyy } from '@/lib/dateFormat'

const props = defineProps({
  visible: { type: Boolean, default: false },
  record: { type: Object, default: null },
})

const emit = defineEmits(['close', 'edit'])

function displayValue(val) {
  if (val == null || String(val).trim() === '') return '—'
  return String(val).trim()
}

/** Format appointment_date: if yyyy-mm-dd use "15 Jan 2024", else show raw. */
function formatAppointmentDate(val) {
  if (val == null || val === '') return '—'
  const s = String(val).trim()
  const ymd = s.match(/^\d{4}-\d{2}-\d{2}/) ? s.slice(0, 10) : (fromDdMmYyyy(s) || '')
  if (ymd) return toDdMonYyyy(ymd) || s
  return s
}

/** Format time for display (e.g. 10:30 AM); pass-through if already readable. */
function formatAppointmentTime(val) {
  if (val == null || val === '') return '—'
  const s = String(val).trim()
  return s
}

const appointmentDateDisplay = computed(() => formatAppointmentDate(props.record?.appointment_date))
const appointmentTimeDisplay = computed(() => formatAppointmentTime(props.record?.appointment_time))

function close() {
  emit('close')
}

function onEdit() {
  if (props.record) emit('edit', props.record)
}
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
        aria-labelledby="dsp-detail-title"
        @click.self="close"
      >
        <div class="my-8 w-full max-w-3xl max-h-[90vh] flex flex-col rounded-xl bg-white shadow-xl overflow-hidden border border-gray-200">
          <!-- Header -->
          <div class="flex flex-shrink-0 items-center justify-between border-b border-gray-200 bg-white px-6 py-4">
            <h2 id="dsp-detail-title" class="text-lg font-bold text-gray-900">DSP Tracker Details</h2>
            <button
              type="button"
              class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
              aria-label="Close"
              @click="close"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body: two-column key-value -->
          <div class="flex-1 min-h-0 overflow-y-auto px-6 py-5">
            <template v-if="record">
              <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                <div>
                  <dt class="text-xs font-medium text-gray-500">Activity Number</dt>
                  <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ displayValue(record.activity_number) }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Company Name</dt>
                  <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ displayValue(record.company_name) }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Account Number</dt>
                  <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ displayValue(record.account_number) }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Request Type</dt>
                  <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ displayValue(record.request_type) }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Appointment Date</dt>
                  <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ appointmentDateDisplay }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Appointment Time</dt>
                  <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ appointmentTimeDisplay }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">DSP OM ID</dt>
                  <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ displayValue(record.dsp_om_id) }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">SO Number</dt>
                  <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ displayValue(record.so_number) }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Request Status</dt>
                  <dd class="mt-0.5">
                    <span
                      v-if="record.request_status"
                      class="inline-flex rounded-md px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800"
                    >
                      {{ record.request_status }}
                    </span>
                    <span v-else class="text-sm font-semibold text-gray-900">—</span>
                  </dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Verifier Name</dt>
                  <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ displayValue(record.verifier_name) }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Verifier Number</dt>
                  <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ displayValue(record.verifier_number) }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Rejection Reason</dt>
                  <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ displayValue(record.rejection_reason) }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Product</dt>
                  <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ displayValue(record.product) }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Uploaded By</dt>
                  <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ displayValue(record.uploaded_by) }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Uploaded At</dt>
                  <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ displayValue(record.uploaded_at) }}</dd>
                </div>
              </dl>
            </template>
          </div>

          <!-- Footer: Close + Edit Record -->
          <div class="flex flex-shrink-0 justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              @click="close"
            >
              Close
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded bg-[#6BC100] px-4 py-2 text-sm font-medium text-white hover:bg-[#5da800]"
              @click="onEdit"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
              </svg>
              Edit Record
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
