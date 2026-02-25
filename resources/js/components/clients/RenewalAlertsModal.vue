<script setup>
const props = defineProps({
  visible: { type: Boolean, default: false },
  companyName: { type: String, default: '' },
  alerts: { type: Array, default: () => [] },
})

const emit = defineEmits(['close'])

function displayDate(item) {
  if (!item) return '—'
  return item.display_date || item.expiry_date || '—'
}
</script>

<template>
  <Teleport to="body">
    <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
      <div
        v-if="visible"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4"
        role="dialog"
        aria-modal="true"
        @click.self="emit('close')"
      >
        <div class="w-full max-w-xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
            <div>
              <h3 class="text-base font-semibold text-gray-900">Renewal Alerts</h3>
              <p class="text-xs text-gray-500">{{ companyName || 'Client' }}</p>
            </div>
            <button type="button" class="rounded p-1.5 text-gray-500 hover:bg-gray-100" @click="emit('close')">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="max-h-[65vh] overflow-y-auto p-5">
            <div v-if="!alerts.length" class="rounded border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
              No alerts found for this client.
            </div>
            <table v-else class="min-w-full border-collapse">
              <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                  <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Alert Type</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Date</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in alerts" :key="`${item.alert_type || 'Alert'}-${item.expiry_date || idx}`" class="border-b border-gray-100">
                  <td class="px-3 py-2 text-sm text-gray-800">{{ item.alert_type || '—' }}</td>
                  <td class="px-3 py-2 text-sm text-gray-700">{{ displayDate(item) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex justify-end border-t border-gray-200 px-5 py-3">
            <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="emit('close')">
              Close
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
