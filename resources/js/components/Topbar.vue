<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()
const userMenuOpen = ref(false)

const primaryRole = computed(() => {
  const roles = auth.user?.roles ?? []
  if (roles.includes('superadmin')) return 'Super Admin'
  if (roles.length) return String(roles[0]).replace(/_/g, ' ')
  return 'User'
})

const logout = async () => {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <header class="h-16 flex items-center justify-between px-6 bg-white border-b border-gray-200 shadow-sm">
    <h1 class="text-lg font-semibold text-gray-800 truncate">CRM Pro Operations Hub</h1>

    <div class="flex items-center gap-3">
      <button
        type="button"
        class="relative p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700"
        title="Notifications"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" />
      </button>

      <div class="relative">
        <button
          type="button"
          class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100"
          @click="userMenuOpen = !userMenuOpen"
        >
          <span class="text-sm font-medium">{{ auth.user?.name ?? 'User' }}</span>
          <span class="text-xs text-gray-500">{{ primaryRole }}</span>
          <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <div
          v-show="userMenuOpen"
          class="absolute right-0 mt-1 w-48 py-1 bg-white rounded-lg shadow-lg border border-gray-200 z-10"
        >
          <button
            type="button"
            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
            @click="logout"
          >
            Logout
          </button>
        </div>
      </div>
    </div>
  </header>
</template>
