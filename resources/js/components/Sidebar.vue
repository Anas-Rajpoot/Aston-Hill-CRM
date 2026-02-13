<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import SidebarLink from './SidebarLink.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const settingsOpen = ref(false)

const logout = async () => {
  await auth.logout()
  router.push('/login')
}

const userHasRole = (role) => auth.user?.roles?.includes(role) ?? false

const primaryRole = computed(() => {
  const roles = auth.user?.roles ?? []
  if (roles.includes('superadmin')) return 'SA'
  if (roles.length) return String(roles[0]).slice(0, 2).toUpperCase()
  return 'U'
})

const navItems = [
  { to: '/', label: 'Dashboard' },
  { to: '/submissions', label: 'Submissions' },
  { to: '/lead-submissions', label: 'Lead Submissions' },
  { to: '/field-submissions', label: 'Field Submissions' },
  { to: '/customer-support', label: 'Customer Support' },
  { to: '/vas-requests', label: 'VAS Requests' },
  { to: '/clients', label: 'Clients' },
  { to: '/order-status', label: 'Order Status' },
  { to: '/dsp-tracker', label: 'DSP Tracker' },
  { to: '/employees', label: 'Employees' },
  { to: '/cisco-extensions', label: 'Cisco Extensions' },
  { to: '/attendance-log', label: 'Attendance Log' },
  { to: '/expenses', label: 'Expense Tracker' },
  { to: '/personal-notes', label: 'Personal Notes' },
  { to: '/email-followups', label: 'Email Follow Up' },
  { to: '/reports', label: 'Reports' },
]

const settingsItems = [
  { to: '/settings', label: 'Settings' },
  { to: '/settings/team-hierarchy', label: 'Team Hierarchy', show: () => userHasRole('superadmin') },
  { to: '/announcements', label: 'Announcements' },
  { to: '/notifications', label: 'Notifications' },
  { to: '/accounts', label: 'Accounts' },
  { to: '/login-logs', label: 'Login Logs' },
]

const superAdminOnly = [
  { to: '/users', label: 'Users', show: () => userHasRole('superadmin') },
  { to: '/roles', label: 'Roles', show: () => userHasRole('superadmin') },
  { to: '/permissions', label: 'Permissions', show: () => userHasRole('superadmin') },
  { to: '/lead-submissions/audit-log', label: 'Lead Submission Changes', show: () => userHasRole('superadmin') },
  { to: '/field-submissions/audit-log', label: 'Field Submission Changes', show: () => userHasRole('superadmin') },
]

const isActive = (to) => {
  if (to === '/') return route.path === '/' || route.path === '/dashboard'
  return route.path.startsWith(to)
}
</script>

<template>
  <aside class="w-64 min-h-screen flex flex-col bg-gray-900 text-gray-200">
    <!-- Logo / Title -->
    <div class="h-16 flex items-center px-4 border-b border-gray-700">
      <span class="text-lg font-semibold text-white truncate">CRM Pro Operations Hub</span>
    </div>

    <!-- Main nav -->
    <nav class="flex-1 p-3 space-y-0.5 overflow-y-auto">
      <SidebarLink
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        :label="item.label"
        dark
        :active="isActive(item.to)"
      />
      <SidebarLink
        v-for="item in superAdminOnly"
        :key="item.to"
        v-show="item.show?.()"
        :to="item.to"
        :label="item.label"
        dark
        :active="isActive(item.to)"
      />

      <!-- Settings with dropdown -->
      <div class="pt-2">
        <button
          type="button"
          class="w-full flex items-center justify-between px-3 py-2 rounded-md text-sm font-medium text-gray-300 hover:bg-gray-800 hover:text-white"
          @click="settingsOpen = !settingsOpen"
        >
          <span>Settings</span>
          <svg
            class="w-4 h-4 transition-transform"
            :class="settingsOpen && 'rotate-180'"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <div v-show="settingsOpen" class="mt-0.5 ml-3 pl-3 border-l border-gray-700 space-y-0.5">
          <SidebarLink
            v-for="item in settingsItems"
            :key="item.to"
            v-show="item.show?.() !== false"
            :to="item.to"
            :label="item.label"
            dark
            :active="isActive(item.to)"
            sub
          />
        </div>
      </div>
    </nav>

    <!-- Bottom user block -->
    <div class="p-3 border-t border-gray-700">
      <div class="flex items-center gap-3 px-2 py-2 rounded-md bg-gray-800/50">
        <div class="flex-shrink-0 w-9 h-9 rounded-full bg-gray-700 flex items-center justify-center text-xs font-medium text-white">
          {{ primaryRole }}
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-medium text-white truncate">{{ auth.user?.name ?? 'User' }}</p>
          <p class="text-xs text-gray-400 truncate">{{ auth.user?.email ?? '' }}</p>
        </div>
        <button
          type="button"
          class="flex-shrink-0 p-1.5 rounded text-gray-400 hover:text-white hover:bg-gray-700"
          title="Logout"
          @click="logout"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
        </button>
      </div>
    </div>
  </aside>
</template>
