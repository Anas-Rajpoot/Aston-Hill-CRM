<script setup>
/**
 * PasswordStrengthMeter — Reusable, color-coded password strength indicator.
 *
 * Props:
 *  - password (string): The current password value
 *  - policy (object): { min_length, require_uppercase, require_number, require_special }
 *
 * Displays:
 *  - A color-coded progress bar (red → yellow → green)
 *  - A checklist of missing/met requirements
 */
import { computed } from 'vue'

const props = defineProps({
  password: { type: String, default: '' },
  policy: {
    type: Object,
    default: () => ({
      min_length: 8,
      require_uppercase: true,
      require_number: true,
      require_special: true,
    }),
  },
})

const checks = computed(() => {
  const p = props.password
  const items = [
    { key: 'length', label: `At least ${props.policy.min_length} characters`, met: p.length >= (props.policy.min_length || 8) },
  ]
  if (props.policy.require_uppercase) {
    items.push({ key: 'uppercase', label: 'At least one uppercase letter (A-Z)', met: /[A-Z]/.test(p) })
  }
  if (props.policy.require_number) {
    items.push({ key: 'number', label: 'At least one number (0-9)', met: /[0-9]/.test(p) })
  }
  if (props.policy.require_special) {
    items.push({ key: 'special', label: 'At least one special character (!@#$%^&*)', met: /[^A-Za-z0-9]/.test(p) })
  }
  return items
})

const metCount = computed(() => checks.value.filter(c => c.met).length)
const totalCount = computed(() => checks.value.length)
const percentage = computed(() => (totalCount.value === 0 ? 0 : Math.round((metCount.value / totalCount.value) * 100)))

const strength = computed(() => {
  const pct = percentage.value
  if (pct === 0) return { level: 'none', label: '', color: 'bg-gray-200', text: 'text-gray-400' }
  if (pct < 50) return { level: 'weak', label: 'Weak', color: 'bg-red-500', text: 'text-red-600' }
  if (pct < 100) return { level: 'medium', label: 'Medium', color: 'bg-yellow-500', text: 'text-yellow-600' }
  return { level: 'strong', label: 'Strong', color: 'bg-brand-primary', text: 'text-brand-primary' }
})

const allMet = computed(() => metCount.value === totalCount.value)

defineExpose({ allMet, checks })
</script>

<template>
  <div class="space-y-3">
    <!-- Strength bar -->
    <div v-if="password.length > 0">
      <div class="flex items-center justify-between mb-1.5">
        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Password Strength</span>
        <span class="text-xs font-semibold" :class="strength.text">{{ strength.label }}</span>
      </div>
      <div class="h-2 w-full rounded-full bg-gray-200 overflow-hidden">
        <div
          class="h-full rounded-full transition-all duration-300 ease-out"
          :class="strength.color"
          :style="{ width: percentage + '%' }"
        />
      </div>
    </div>

    <!-- Requirements checklist -->
    <div class="rounded-lg bg-gray-50 border border-gray-200 p-3">
      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Password Requirements</p>
      <ul class="space-y-1.5">
        <li v-for="check in checks" :key="check.key" class="flex items-center gap-2 text-sm">
          <svg v-if="check.met" class="h-4 w-4 text-brand-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <svg v-else class="h-4 w-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" stroke-width="2" />
          </svg>
          <span :class="check.met ? 'text-brand-primary-hover' : 'text-gray-500'">{{ check.label }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>
