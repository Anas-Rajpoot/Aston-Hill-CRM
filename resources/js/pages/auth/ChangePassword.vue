<script setup>
/**
 * Change Password Page
 *
 * Shown when:
 *  - User must change password on first login (must_change_password flag)
 *  - User voluntarily wants to change their password
 *
 * Displays dynamic password policy requirements from SecuritySettings.
 */
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api, { ensureCsrfCookie } from '@/lib/axios'
import PasswordStrengthMeter from '@/components/PasswordStrengthMeter.vue'

const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const submitting = ref(false)
const success = ref(false)
const successRedirect = ref('/')
const errors = reactive({})

const form = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const policy = reactive({
  min_length: 8,
  require_uppercase: true,
  require_number: true,
  require_special: true,
  reason: null,
})

const strengthMeter = ref(null)

const passwordsMatch = computed(() => form.password.length > 0 && form.password === form.password_confirmation)
const allChecksMet = computed(() => strengthMeter.value?.allMet && passwordsMatch.value && form.current_password.length > 0)

const reasonMessage = computed(() => {
  if (policy.reason === 'must_change_password') {
    return 'You are required to change your password before continuing. This is your first login.'
  }
  return null
})

async function fetchPolicy() {
  loading.value = true
  try {
    const { data } = await api.get('/change-password/policy')
    Object.assign(policy, data.data)
  } catch {
    // Use defaults
  } finally {
    loading.value = false
  }
}

async function submit() {
  if (submitting.value || !allChecksMet.value) return
  submitting.value = true
  Object.keys(errors).forEach(k => delete errors[k])

  try {
    const payload = { ...form }
    const requestConfig = { showToast: false, skipAuthRedirect: true }
    let response = null

    const submitChange = () => api.post('/change-password', payload, requestConfig)

    try {
      response = await submitChange()
    } catch (e) {
      // Retry once on stale CSRF token without pre-emptively touching session cookies.
      if (e?.response?.status === 419) {
        await ensureCsrfCookie(true)
        response = await submitChange()
      } else {
        throw e
      }
    }

    successRedirect.value = response?.data?.redirect || '/'
    success.value = true

    // Clear the password action in the auth store
    auth.passwordAction = null

    // Invalidate bootstrap cache so next fetch picks up cleared must_change_password
    try { localStorage.removeItem('auth_bootstrap') } catch {}

    // Redirect to home after brief delay
    setTimeout(() => {
      router.push(successRedirect.value)
    }, 2000)
  } catch (e) {
    if (e?.response?.status === 401) {
      errors.general = 'Your session expired. Please sign in again.'
      setTimeout(() => router.push('/login'), 900)
      return
    }
    if (e?.response?.status === 422) {
      const fe = e.response.data?.errors ?? {}
      Object.keys(fe).forEach(k => {
        errors[k] = Array.isArray(fe[k]) ? fe[k].join(' ') : fe[k]
      })
    } else {
      errors.general = e?.response?.data?.message || 'Failed to change password. Please try again.'
    }
  } finally {
    submitting.value = false
  }
}

async function handleLogout() {
  try {
    await auth.logout()
  } catch { /* silent */ }
  router.push('/login')
}

onMounted(fetchPolicy)
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
    <div class="w-full max-w-md">

      <!-- Success State -->
      <div v-if="success" class="bg-white rounded-xl shadow-lg p-8 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-primary-light mb-4">
          <svg class="h-7 w-7 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h2 class="text-xl font-semibold text-gray-900 mb-2">Password Changed</h2>
        <p class="text-sm text-gray-600">Your password has been updated successfully. Redirecting...</p>
      </div>

      <!-- Form State -->
      <div v-else class="bg-white rounded-xl shadow-lg p-8">
        <!-- Header -->
        <div class="flex items-center gap-3 mb-2">
          <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-primary-light">
            <svg class="h-5 w-5 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
          </div>
          <div>
            <h1 class="text-xl font-semibold text-gray-800">Change Password</h1>
          </div>
        </div>

        <!-- Reason Banner -->
        <div v-if="reasonMessage" class="mt-3 mb-5 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3">
          <div class="flex gap-2">
            <svg class="h-5 w-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p class="text-sm text-amber-800">{{ reasonMessage }}</p>
          </div>
        </div>
        <p v-else class="text-sm text-gray-600 mb-5 mt-1">Enter your current password and choose a new one.</p>

        <!-- General error -->
        <div v-if="errors.general" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3">
          <p class="text-sm text-red-700">{{ errors.general }}</p>
        </div>

        <!-- Loading skeleton -->
        <div v-if="loading" class="space-y-4">
          <div class="h-10 bg-gray-100 rounded-lg animate-pulse"></div>
          <div class="h-10 bg-gray-100 rounded-lg animate-pulse"></div>
          <div class="h-10 bg-gray-100 rounded-lg animate-pulse"></div>
        </div>

        <!-- Form -->
        <form v-else @submit.prevent="submit" class="space-y-4">
          <!-- Current Password -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
            <input
              v-model="form.current_password"
              type="password"
              required
              autocomplete="current-password"
              class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-primary focus:ring-brand-primary"
              placeholder="Enter current password"
            />
            <p v-if="errors.current_password" class="mt-1 text-xs text-red-600">{{ errors.current_password }}</p>
          </div>

          <!-- New Password -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
            <input
              v-model="form.password"
              type="password"
              required
              autocomplete="new-password"
              class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-primary focus:ring-brand-primary"
              placeholder="Enter new password"
            />
            <p v-if="errors.password" class="mt-1 text-xs text-red-600">{{ errors.password }}</p>
          </div>

          <!-- Confirm New Password -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
            <input
              v-model="form.password_confirmation"
              type="password"
              required
              autocomplete="new-password"
              class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-primary focus:ring-brand-primary"
              placeholder="Confirm new password"
            />
          </div>

          <!-- Password Strength Meter + Checklist -->
          <PasswordStrengthMeter ref="strengthMeter" :password="form.password" :policy="policy" />

          <!-- Passwords match indicator -->
          <div v-if="form.password.length > 0" class="flex items-center gap-2 text-sm">
            <svg v-if="passwordsMatch" class="h-4 w-4 text-brand-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <svg v-else class="h-4 w-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10" stroke-width="2" />
            </svg>
            <span :class="passwordsMatch ? 'text-brand-primary-hover' : 'text-gray-500'">Passwords match</span>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-3 pt-2">
            <button
              type="submit"
              :disabled="submitting || !allChecksMet"
              class="flex-1 py-2.5 px-4 rounded-lg bg-brand-primary text-white font-medium hover:bg-brand-primary-hover focus:ring-brand-primary disabled:opacity-50 disabled:cursor-not-allowed transition"
            >
              {{ submitting ? 'Changing...' : 'Change Password' }}
            </button>
            <button
              type="button"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition"
              @click="handleLogout"
            >
              Log Out
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>
</template>
