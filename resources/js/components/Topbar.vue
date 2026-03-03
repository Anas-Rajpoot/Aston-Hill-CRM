<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { useSidebar } from '@/composables/useSidebar'
import api from '@/lib/axios'

const auth = useAuthStore()
const router = useRouter()
const { collapsed, toggle: toggleSidebar } = useSidebar()
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

// ─── Notification bell ──────────────────────────────────
const unreadCount  = ref(0)
const badge        = ref('0')
const webEnabled   = ref(true)
const notifications = ref([])
const bellOpen     = ref(false)
let pollTimer      = null

async function pollNotifications() {
  try {
    const { data } = await api.get('/notifications/poll')
    webEnabled.value   = data.web_enabled !== false
    unreadCount.value  = data.unreadCount ?? 0
    badge.value        = data.badge ?? '0'
    notifications.value = data.top ?? []
  } catch { /* silent */ }
}

function toggleBell() {
  bellOpen.value = !bellOpen.value
}

async function markRead(id) {
  try {
    await api.post(`/notifications/${id}/read`)
    const n = notifications.value.find(x => x.id === id)
    if (n) n.is_unread = false
    if (unreadCount.value > 0) unreadCount.value--
    badge.value = unreadCount.value > 5 ? '5+' : String(unreadCount.value)
  } catch { /* silent */ }
}

function schedulePoll() {
  if (pollTimer) clearTimeout(pollTimer)
  pollTimer = setTimeout(async () => {
    if (!document.hidden) await pollNotifications()
    schedulePoll()
  }, 60_000)
}

onMounted(() => {
  // Defer initial poll so it doesn't block page-load API calls on single-threaded server
  pollTimer = setTimeout(async () => {
    await pollNotifications()
    schedulePoll()
  }, 5_000)
})
onUnmounted(() => {
  if (pollTimer) clearTimeout(pollTimer)
})
</script>

<template>
  <header class="h-16 flex items-center justify-between px-6 bg-white border-b border-gray-200 shadow-sm">
    <div class="flex items-center gap-3">
      <button
        type="button"
        class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors"
        :title="collapsed ? 'Open sidebar' : 'Close sidebar'"
        @click="toggleSidebar"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
    </div>

    <div class="flex items-center gap-3">
      <!-- Notification bell (only visible when web notifications enabled) -->
      <div v-if="webEnabled" class="relative">
        <button
          type="button"
          class="relative p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700"
          title="Notifications"
          @click="toggleBell"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
          <span
            v-if="unreadCount > 0"
            class="absolute -top-0.5 -right-0.5 flex items-center justify-center min-w-[18px] h-[18px] rounded-full bg-red-500 text-white text-[10px] font-bold px-1"
          >{{ badge }}</span>
        </button>

        <!-- Dropdown panel -->
        <div
          v-if="bellOpen"
          class="absolute right-0 mt-1 w-80 bg-white rounded-xl shadow-xl border border-gray-200 z-50 overflow-hidden"
        >
          <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <p class="text-sm font-semibold text-gray-900">Notifications</p>
            <span v-if="unreadCount > 0" class="text-xs text-gray-500">{{ unreadCount }} unread</span>
          </div>
          <div class="max-h-72 overflow-y-auto divide-y divide-gray-50">
            <div
              v-for="n in notifications"
              :key="n.id"
              class="px-4 py-3 hover:bg-gray-50 cursor-pointer transition-colors"
              :class="n.is_unread ? 'bg-blue-50/40' : ''"
              @click="markRead(n.id)"
            >
              <div class="flex items-start gap-2">
                <span
                  v-if="n.is_sla"
                  class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600"
                >
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                </span>
                <span
                  v-else
                  class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600"
                >
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6z" /></svg>
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-900 truncate">{{ n.title }}</p>
                  <p class="text-xs text-gray-500 truncate">{{ n.message }}</p>
                  <p class="text-[10px] text-gray-400 mt-0.5">{{ n.created_at }}</p>
                </div>
                <span v-if="n.is_unread" class="mt-1.5 h-2 w-2 rounded-full bg-blue-500 shrink-0" />
              </div>
            </div>
            <div v-if="!notifications.length" class="px-4 py-6 text-center text-sm text-gray-400">
              No notifications
            </div>
          </div>
          <div class="px-4 py-2 border-t border-gray-100 text-center">
            <button
              type="button"
              class="text-xs font-medium text-blue-600 hover:text-blue-700"
              @click="bellOpen = false; router.push('/notifications')"
            >View all notifications</button>
          </div>
        </div>
      </div>

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
