<template>
  <Teleport to="body">
    <Transition name="toast">
      <div
        v-if="visible"
        role="alert"
        :aria-live="type === 'error' ? 'assertive' : 'polite'"
        :class="[
          'fixed left-1/2 top-4 z-[200] w-full max-w-md -translate-x-1/2 rounded-xl px-4 py-4 shadow-lg',
          type === 'success' ? 'bg-brand-primary text-white' : 'bg-red-600 text-white',
        ]"
      >
        <div class="flex items-start gap-3">
          <span v-if="type === 'success'" class="shrink-0 rounded-full bg-white/20 p-1" aria-hidden="true">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </span>
          <span v-else class="shrink-0 rounded-full bg-white/20 p-1" aria-hidden="true">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </span>
          <div class="min-w-0 flex-1">
            <p class="font-semibold">{{ type === 'success' ? 'Success' : 'Error' }}</p>
            <p class="mt-0.5 text-sm opacity-95">{{ message }}</p>
            <p v-if="type === 'success' && countdown != null" class="mt-2 text-xs opacity-90">
              Redirecting to clients listing in {{ countdown }} seconds…
            </p>
          </div>
          <button
            type="button"
            class="shrink-0 rounded p-1 opacity-80 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-white/50"
            aria-label="Dismiss"
            @click="dismiss"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, onUnmounted } from 'vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  type: { type: String, default: 'success' },
  message: { type: String, default: '' },
  duration: { type: Number, default: null },
  countdown: { type: Number, default: null },
})

const emit = defineEmits(['dismiss', 'countdown-done'])

const visible = ref(false)
let durationTimer = null
let countdownInterval = null

watch(
  () => props.show,
  (val) => {
    visible.value = val
    if (val && props.duration != null && props.duration > 0) {
      clearTimeout(durationTimer)
      durationTimer = setTimeout(() => {
        emit('countdown-done')
        emit('dismiss')
      }, props.duration)
    }
  },
  { immediate: true }
)

watch(
  () => props.countdown,
  (val) => {
    if (val != null && val <= 0 && props.show) emit('countdown-done')
  }
)

function dismiss() {
  visible.value = false
  if (durationTimer) clearTimeout(durationTimer)
  if (countdownInterval) clearInterval(countdownInterval)
  emit('dismiss')
}

onUnmounted(() => {
  if (durationTimer) clearTimeout(durationTimer)
  if (countdownInterval) clearInterval(countdownInterval)
})
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: transform 0.25s ease, opacity 0.25s ease;
}
.toast-enter-from,
.toast-leave-to {
  transform: translate(-50%, -100%);
  opacity: 0;
}
</style>
