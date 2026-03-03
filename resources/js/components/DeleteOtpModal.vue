<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  visible: { type: Boolean, default: false },
  title: { type: String, default: 'Delete Confirmation' },
  itemLabel: { type: String, default: 'this record' },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'confirm'])

const otpCode = ref('')
const otpInput = ref('')

function generateOtpCode() {
  otpCode.value = String(Math.floor(100000 + Math.random() * 900000))
  otpInput.value = ''
}

watch(() => props.visible, (isVisible) => {
  if (isVisible) generateOtpCode()
})

function closeModal() {
  if (props.loading) return
  emit('close')
}

function confirmDelete() {
  if (props.loading) return
  if (otpInput.value.trim() !== otpCode.value) return
  emit('confirm')
}
</script>

<template>
  <div v-if="visible" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" @click="closeModal" />

    <div class="relative w-full max-w-md rounded-lg bg-white shadow-xl">
      <div class="border-b border-gray-200 px-5 py-4">
        <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
      </div>

      <div class="space-y-3 px-5 py-4">
        <p class="text-sm text-gray-700">
          To delete <span class="font-semibold">{{ itemLabel }}</span>, enter the OTP shown below.
        </p>

        <div class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-center">
          <p class="text-xs font-medium uppercase tracking-wide text-amber-700">OTP Code</p>
          <p class="mt-1 text-2xl font-bold tracking-widest text-amber-800">{{ otpCode }}</p>
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Enter OTP</label>
          <input
            v-model="otpInput"
            type="text"
            maxlength="6"
            inputmode="numeric"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500"
            placeholder="Type OTP here"
          >
          <p v-if="otpInput && otpInput.trim() !== otpCode" class="mt-1 text-xs text-red-600">Entered OTP does not match.</p>
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 border-t border-gray-200 px-5 py-4">
        <button
          type="button"
          class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
          :disabled="loading"
          @click="closeModal"
        >
          Cancel
        </button>
        <button
          type="button"
          class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
          :disabled="loading || otpInput.trim() !== otpCode"
          @click="confirmDelete"
        >
          {{ loading ? 'Deleting...' : 'Delete' }}
        </button>
      </div>
    </div>
  </div>
</template>
