<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSidebar } from '@/composables/useSidebar'
import { canAccessRoute, isSuperAdmin } from '@/lib/accessControl'
import {
  IconDashboard, IconSubmissions, IconLeadSubmissions, IconFieldSubmissions,
  IconCustomerSupport, IconVasRequests, IconSpecialRequests, IconEmailFollowUp,
  IconClients, IconOrderStatus, IconDspTracker, IconVerifiers, IconExtensions,
  IconAttendance, IconExpenses, IconPersonalNotes, IconEmployees, IconReports,
  IconSettings, IconUsers, IconTeams, IconRoles, IconPermissions, IconChevronDown,
  IconNotifications,
} from './icons/SidebarIcons.vue'

const props = defineProps({
  forceExpanded: { type: Boolean, default: false },
})

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { collapsed, toggle } = useSidebar()

/** When forceExpanded (mobile drawer), always show expanded state */
const effectiveCollapsed = computed(() => props.forceExpanded ? false : collapsed.value)

const logout = async () => {
  await auth.logout()
  router.push('/login')
}

const primaryRole = computed(() => {
  const roles = auth.user?.roles ?? []
  if (roles.includes('superadmin')) return 'SA'
  if (roles.length) return String(roles[0]).slice(0, 2).toUpperCase()
  return 'U'
})

const isActive = (to) => {
  if (to === '/') return route.path === '/' || route.path === '/dashboard'
  return route.path === to || route.path.startsWith(to + '/')
}

const isGroupActive = (children) => children.some(c => isActive(c.to))

/* ── Navigation structure ── */
const navGroups = computed(() => {
  const groups = []

  // Dashboard (top-level)
  groups.push({
    type: 'link',
    to: '/',
    label: 'Dashboard',
    icon: IconDashboard,
    visible: canAccessRoute(auth.user, '/'),
  })

  // All Forms (top-level, above CRM)
  if (canAccessRoute(auth.user, '/submissions')) {
    groups.push({ type: 'link', to: '/submissions', label: 'All Forms', icon: IconSubmissions, visible: true })
  }

  // CRM group (without All Forms and Email Follow-Up, which are moved up)
  const crmChildren = [
    { to: '/lead-submissions', label: 'Lead Submissions', icon: IconLeadSubmissions },
    { to: '/field-submissions', label: 'Field Submissions', icon: IconFieldSubmissions },
    { to: '/customer-support', label: 'Customer Support', icon: IconCustomerSupport },
    { to: '/vas-requests', label: 'VAS Requests', icon: IconVasRequests },
    { to: '/special-requests', label: 'Special Requests', icon: IconSpecialRequests },
  ].filter(c => canAccessRoute(auth.user, c.to))

  if (crmChildren.length) {
    groups.push({ type: 'group', label: 'CRM', icon: IconSubmissions, children: crmChildren, key: 'crm' })
  }

  // Email Follow-Up (top-level, above Notifications)
  if (canAccessRoute(auth.user, '/email-followups')) {
    groups.push({ type: 'link', to: '/email-followups', label: 'Email Follow-Up', icon: IconEmailFollowUp, visible: true })
  }

  // Notifications (top-level)
  if (canAccessRoute(auth.user, '/notifications')) {
    groups.push({ type: 'link', to: '/notifications', label: 'Notifications', icon: IconNotifications, visible: true })
  }

  // Clients group
  const clientsChildren = [
    { to: '/clients', label: 'Clients', icon: IconClients },
    { to: '/all-clients', label: 'All Clients', icon: IconClients },
  ].filter(c => canAccessRoute(auth.user, c.to))

  if (clientsChildren.length) {
    groups.push({ type: 'group', label: 'Clients', icon: IconClients, children: clientsChildren, key: 'clients' })
  }

  // Operations group
  const opsChildren = [
    { to: '/order-status', label: 'Order Status', icon: IconOrderStatus },
    { to: '/dsp-tracker', label: 'DSP Tracker', icon: IconDspTracker },
    { to: '/verifiers-detail', label: 'Verifiers Detail', icon: IconVerifiers },
    { to: '/cisco-extensions', label: 'Cisco Extensions', icon: IconExtensions },
    { to: '/attendance-log', label: 'Attendance Log', icon: IconAttendance },
    { to: '/expenses', label: 'Expense Tracker', icon: IconExpenses },
    { to: '/personal-notes', label: 'Personal Notes', icon: IconPersonalNotes },
  ].filter(c => canAccessRoute(auth.user, c.to))

  if (opsChildren.length) {
    groups.push({ type: 'group', label: 'Operations', icon: IconOrderStatus, children: opsChildren, key: 'operations' })
  }

  // Reports (top-level)
  if (canAccessRoute(auth.user, '/reports')) {
    groups.push({ type: 'link', to: '/reports', label: 'Reports', icon: IconReports, visible: true })
  }

  // Users & Access group
  const accessChildren = []
  if (canAccessRoute(auth.user, '/users')) accessChildren.push({ to: '/users', label: 'Users & Employees', icon: IconUsers })
  if (canAccessRoute(auth.user, '/teams')) accessChildren.push({ to: '/teams', label: 'Teams', icon: IconTeams })
  if (isSuperAdmin(auth.user)) {
    accessChildren.push({ to: '/roles', label: 'Roles', icon: IconRoles })
    accessChildren.push({ to: '/permissions', label: 'Permissions', icon: IconPermissions })
  }

  if (accessChildren.length) {
    groups.push({ type: 'group', label: 'Users & Access', icon: IconUsers, children: accessChildren, key: 'access' })
  }

  // Settings (top-level, superadmin only)
  if (isSuperAdmin(auth.user)) {
    groups.push({ type: 'link', to: '/settings', label: 'Settings', icon: IconSettings, visible: true })
  }

  return groups
})

/* ── Collapsible group state ── */
const openGroups = ref(new Set())

// Auto-open group containing current route
watch(() => route.path, () => {
  navGroups.value.forEach(g => {
    if (g.type === 'group' && isGroupActive(g.children)) {
      openGroups.value.add(g.key)
    }
  })
}, { immediate: true })

const toggleGroup = (key) => {
  if (openGroups.value.has(key)) {
    openGroups.value.delete(key)
  } else {
    openGroups.value.add(key)
  }
}

const isGroupOpen = (g) => openGroups.value.has(g.key) || isGroupActive(g.children)

/* ── Page name & breadcrumbs for sidebar header ── */
const currentPageName = computed(() => {
  if (route.meta?.title) return route.meta.title
  for (const group of navGroups.value) {
    if (group.type === 'link' && isActive(group.to)) return group.label
    if (group.type === 'group') {
      for (const child of group.children) {
        if (isActive(child.to)) return child.label
      }
    }
  }
  const segments = route.path.split('/').filter(Boolean)
  if (segments.length) {
    return segments[segments.length - 1].replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
  }
  return 'Dashboard'
})

const breadcrumbs = computed(() => {
  for (const group of navGroups.value) {
    if (group.type === 'link' && isActive(group.to)) return [group.label]
    if (group.type === 'group') {
      for (const child of group.children) {
        if (isActive(child.to)) return [group.label, child.label]
      }
    }
  }
  // Fallback for settings sub-pages, detail pages, etc.
  const segments = route.path.split('/').filter(Boolean)
  return segments.length
    ? segments.map(s => s.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase()))
    : ['Dashboard']
})
</script>

<template>
  <aside
    class="sidebar h-screen flex flex-col flex-shrink-0 overflow-hidden bg-sidebar-bg text-gray-200 transition-all duration-300 ease-in-out"
    :class="effectiveCollapsed ? 'w-[68px]' : 'w-64'"
  >
    <!-- Logo / Brand -->
    <div
      class="flex items-center border-b border-sidebar-border/50 h-14"
      :class="effectiveCollapsed ? 'justify-center px-2' : 'px-4'"
    >
      <template v-if="!effectiveCollapsed">
        <div class="flex items-center gap-2.5 min-w-0">
          <div class="w-8 h-8 rounded-lg bg-brand-primary/20 flex items-center justify-center flex-shrink-0">
            <span class="text-brand-primary font-bold text-sm">AH</span>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-white font-semibold text-sm leading-tight truncate">{{ currentPageName }}</p>
            <p class="text-gray-400 text-[11px] leading-tight truncate mt-0.5">{{ breadcrumbs.join(' › ') }}</p>
          </div>
        </div>
      </template>
      <template v-else>
        <div class="w-8 h-8 rounded-lg bg-brand-primary/20 flex items-center justify-center">
          <span class="text-brand-primary font-bold text-sm">AH</span>
        </div>
      </template>
    </div>

    <!-- Toggle -->
    <!-- Toggle (hidden in mobile drawer) -->
    <div v-if="!forceExpanded" class="px-2 py-1.5 border-b border-sidebar-border/30">
      <button
        type="button"
        class="w-full flex items-center justify-center p-2.5 rounded-md text-sidebar-text hover:text-white hover:bg-sidebar-hover transition-colors"
        :title="effectiveCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        @click="toggle"
      >
        <svg v-if="effectiveCollapsed" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
        </svg>
        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
        </svg>
      </button>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav flex-1 min-h-0 px-2 py-2 space-y-0.5 overflow-y-auto overflow-x-hidden">
      <template v-for="item in navGroups" :key="item.key || item.to">

        <!-- Top-level link -->
        <router-link
          v-if="item.type === 'link' && item.visible"
          :to="item.to"
          class="nav-item group relative flex items-center rounded-lg text-[13px] font-medium transition-colors"
          :class="[
            effectiveCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2',
            isActive(item.to)
              ? 'bg-brand-primary/15 text-brand-primary'
              : 'text-sidebar-text hover:bg-sidebar-hover/70 hover:text-gray-100',
          ]"
          :title="effectiveCollapsed ? item.label : undefined"
        >
          <component :is="item.icon" />
          <span v-if="!effectiveCollapsed">{{ item.label }}</span>
          <!-- Collapsed tooltip -->
          <span
            v-if="effectiveCollapsed"
            class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-gray-800 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100"
          >{{ item.label }}</span>
        </router-link>

        <!-- Collapsible group -->
        <div v-else-if="item.type === 'group'" class="space-y-0.5">
          <button
            type="button"
            class="nav-group-btn group relative w-full flex items-center rounded-lg text-[13px] font-medium transition-colors"
            :class="[
              effectiveCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2',
              isGroupActive(item.children)
                ? 'bg-brand-primary/10 text-brand-primary'
                : 'text-sidebar-text hover:bg-sidebar-hover/70 hover:text-gray-100',
            ]"
            :title="effectiveCollapsed ? item.label : undefined"
            @click="toggleGroup(item.key)"
          >
            <component :is="item.icon" />
            <span v-if="!effectiveCollapsed" class="flex-1 text-left">{{ item.label }}</span>
            <component
              v-if="!effectiveCollapsed"
              :is="IconChevronDown"
              class="transition-transform duration-200"
              :class="isGroupOpen(item) ? 'rotate-180' : ''"
            />
            <!-- Collapsed tooltip -->
            <span
              v-if="effectiveCollapsed"
              class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-gray-800 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100"
            >{{ item.label }}</span>
          </button>

          <!-- Group children -->
          <div
            v-show="!effectiveCollapsed && isGroupOpen(item)"
            class="ml-4 mt-0.5 space-y-0.5 border-l border-sidebar-border/40 pl-3"
          >
            <router-link
              v-for="child in item.children"
              :key="child.to"
              :to="child.to"
              class="group relative flex items-center gap-2.5 rounded-md px-2.5 py-2 text-[13px] font-medium transition-colors"
              :class="isActive(child.to)
                ? 'bg-brand-primary/15 text-brand-primary'
                : 'text-sidebar-text hover:bg-sidebar-hover/50 hover:text-gray-200'"
            >
              <component :is="child.icon" class="!w-4 !h-4" />
              <span>{{ child.label }}</span>
            </router-link>
          </div>

          <!-- Collapsed flyout -->
          <div v-if="effectiveCollapsed" class="hidden group-hover:block" />
        </div>
      </template>
    </nav>

    <!-- Bottom user block -->
    <div class="border-t border-sidebar-border/50" :class="effectiveCollapsed ? 'p-2' : 'p-3'">
      <div
        class="flex items-center rounded-lg bg-sidebar-hover/40"
        :class="effectiveCollapsed ? 'justify-center px-1 py-2' : 'gap-3 px-3 py-2'"
      >
        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-brand-primary/20 flex items-center justify-center text-xs font-semibold text-brand-primary">
          {{ primaryRole }}
        </div>
        <template v-if="!effectiveCollapsed">
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-gray-100 truncate">{{ auth.user?.name ?? 'User' }}</p>
            <p class="text-[11px] text-gray-500 truncate">{{ auth.user?.email ?? '' }}</p>
          </div>
          <button
            type="button"
            class="flex-shrink-0 p-1.5 rounded-md text-gray-500 hover:text-red-400 hover:bg-gray-700/50 transition-colors"
            title="Logout"
            @click="logout"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
          </button>
        </template>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.sidebar-nav {
  scrollbar-width: thin;
  scrollbar-color: rgb(55 65 81 / 0.5) transparent;
}
.sidebar-nav::-webkit-scrollbar { width: 4px; }
.sidebar-nav::-webkit-scrollbar-track { background: transparent; }
.sidebar-nav::-webkit-scrollbar-thumb { background: rgb(55 65 81 / 0.5); border-radius: 2px; }
.sidebar-nav::-webkit-scrollbar-thumb:hover { background: rgb(75 85 99 / 0.7); }
</style>
