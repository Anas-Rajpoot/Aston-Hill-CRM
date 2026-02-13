<script setup>
/**
 * Settings main page: System Status, 8 setting category cards, Role-Based Access panel.
 * Each "Manage" links to a dedicated setting page (to be provided later).
 */
import { RouterLink } from 'vue-router'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const systemStatus = {
  sessionTimeout: '2 Hours',
  activeSlaRules: '7 Modules',
  notificationsEnabled: '5 Types',
}

const settingCards = [
  {
    id: 'system-preferences',
    title: 'System Preferences',
    description: 'Configure language, date format, timezone, and default system behaviors.',
    icon: 'gear',
    label: 'English',
    route: '/settings/system-preferences',
  },
  {
    id: 'sla',
    title: 'SLA Configuration',
    description: 'Manage SLA timers, warning thresholds, and notification rules per module.',
    icon: 'clock',
    label: '7 Active Rules',
    route: '/settings/sla',
  },
  {
    id: 'notifications-email',
    title: 'Notifications & Email Rules',
    description: 'Configure email notifications, SLA breach alerts, and notification preferences.',
    icon: 'bell',
    label: '5 Enabled',
    route: '/settings/notifications-email',
  },
  {
    id: 'announcement-center',
    title: 'Announcement Center',
    description: 'Create and manage system-wide announcements visible to all users.',
    icon: 'megaphone',
    label: null,
    route: '/settings/announcement-center',
  },
  {
    id: 'library',
    title: 'Library (Templates & Forms)',
    description: 'Central repository for application forms, NOC templates, and document formats.',
    icon: 'folder',
    label: null,
    route: '/settings/library',
  },
  {
    id: 'data-import-export',
    title: 'Data & Import/Export',
    description: 'Bulk upload clients, employees, extensions, and export data.',
    icon: 'database',
    label: null,
    route: '/settings/data-import-export',
  },
  {
    id: 'security-session',
    title: 'Security & Session',
    description: 'Configure session timeout, password policies, and security settings.',
    icon: 'shield',
    label: '2 Hours',
    route: '/settings/security-session',
  },
  {
    id: 'audit-logs',
    title: 'Audit Logs',
    description: 'Track all system changes and user actions for accountability.',
    icon: 'document',
    label: null,
    route: '/settings/audit-logs',
  },
]
</script>

<template>
  <div class="space-y-8">
    <div>
      <div class="flex flex-wrap items-baseline gap-2">
        <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
        <Breadcrumbs />
      </div>
      <p class="mt-1 text-sm text-gray-500">Configure system preferences, SLA rules, notifications, and security settings.</p>
    </div>

    <!-- System Status -->
    <div class="rounded-xl border border-blue-200 bg-blue-50 px-5 py-4">
      <div class="flex items-center gap-2 text-blue-900 font-medium">
        <svg class="h-5 w-5 text-blue-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
        </svg>
        System Status
      </div>
      <ul class="mt-3 space-y-1 text-sm text-blue-800">
        <li>Current Session Timeout: <strong>{{ systemStatus.sessionTimeout }}</strong></li>
        <li>Active SLA Rules: <strong>{{ systemStatus.activeSlaRules }}</strong></li>
        <li>Notifications Enabled: <strong>{{ systemStatus.notificationsEnabled }}</strong></li>
      </ul>
    </div>

    <!-- Settings categories grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <article
        v-for="card in settingCards"
        :key="card.id"
        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:shadow-md transition-shadow flex flex-col"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-600">
            <!-- Gear -->
            <svg v-if="card.icon === 'gear'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <!-- Clock -->
            <svg v-else-if="card.icon === 'clock'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <!-- Bell -->
            <svg v-else-if="card.icon === 'bell'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <!-- Megaphone -->
            <svg v-else-if="card.icon === 'megaphone'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
            </svg>
            <!-- Folder -->
            <svg v-else-if="card.icon === 'folder'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
            </svg>
            <!-- Database -->
            <svg v-else-if="card.icon === 'database'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
            </svg>
            <!-- Shield -->
            <svg v-else-if="card.icon === 'shield'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <!-- Document -->
            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <span v-if="card.label" class="text-xs font-medium text-gray-500 whitespace-nowrap">{{ card.label }}</span>
        </div>
        <h3 class="mt-4 text-base font-semibold text-gray-900">{{ card.title }}</h3>
        <p class="mt-2 text-sm text-gray-600 flex-1">{{ card.description }}</p>
        <RouterLink
          :to="card.route"
          class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-sky-600 hover:text-sky-700"
        >
          Manage
          <span aria-hidden="true">→</span>
        </RouterLink>
      </article>
    </div>

    <!-- Role-Based Access -->
    <div class="rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 flex gap-3">
      <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
      </div>
      <div>
        <p class="font-semibold text-blue-900">Role-Based Access</p>
        <p class="mt-1 text-sm text-blue-800">Some settings are restricted to Super Admin only. Other roles will see limited options based on their permissions.</p>
      </div>
    </div>
  </div>
</template>
