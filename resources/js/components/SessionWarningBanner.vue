<script setup>
/**
 * SessionWarningBanner
 *
 * Floating top-right banner that warns user their session is about to expire.
 * Shows countdown, "Stay Signed In", and "Log Out Now" buttons.
 * Accessible: ARIA live region, keyboard navigable, focus management.
 */
defineProps({
  show:              { type: Boolean, default: false },
  countdownSecs:     { type: Number, default: 0 },
  totalWarningSecs:  { type: Number, default: 300 },
  extending:         { type: Boolean, default: false },
})

const emit = defineEmits(['stay', 'logout'])

function formatCountdown(secs) {
  const m = Math.floor(secs / 60)
  const s = secs % 60
  return `${m}:${s.toString().padStart(2, '0')}`
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="opacity-0 translate-y-[-20px]"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-[-20px]"
    >
      <div
        v-if="show"
        role="alertdialog"
        aria-live="assertive"
        aria-atomic="true"
        aria-label="Session expiry warning"
        class="fixed top-4 right-4 z-[9999] w-96 max-w-[calc(100vw-2rem)]"
      >
        <div class="bg-white rounded-xl shadow-2xl border border-amber-200 overflow-hidden">
          <!-- Header -->
          <div class="bg-amber-50 px-4 py-3 flex items-center gap-3 border-b border-amber-100">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100">
              <svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-amber-900">Session Expiring Soon</p>
              <p class="text-xs text-amber-700">You'll be logged out due to inactivity</p>
            </div>
            <div class="text-right">
              <span class="inline-flex items-center justify-center rounded-lg bg-amber-600 text-white px-2.5 py-1 text-sm font-bold tabular-nums min-w-[52px]">
                {{ formatCountdown(countdownSecs) }}
              </span>
            </div>
          </div>

          <!-- Body -->
          <div class="px-4 py-3">
            <p class="text-sm text-gray-600">
              Your session will expire in <strong class="text-amber-700">{{ formatCountdown(countdownSecs) }}</strong>.
              Click below to continue working.
            </p>
          </div>

          <!-- Actions -->
          <div class="px-4 pb-4 flex items-center gap-3">
            <button
              type="button"
              :disabled="extending"
              class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 transition focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
              @click="emit('stay')"
            >
              <svg v-if="extending" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
              {{ extending ? 'Extending…' : 'Stay Signed In' }}
            </button>
            <button
              type="button"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
              @click="emit('logout')"
            >
              Log Out
            </button>
          </div>

          <!-- Progress bar -->
          <div class="h-1 bg-amber-100">
            <div
              class="h-full bg-amber-500 transition-all duration-1000 ease-linear"
              :style="{ width: (countdownSecs > 0 && totalWarningSecs > 0 ? (countdownSecs / totalWarningSecs) * 100 : 0) + '%' }"
            />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
