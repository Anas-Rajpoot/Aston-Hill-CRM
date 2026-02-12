<script setup>
/**
 * Breadcrumbs for all app pages. Shown in layout; builds from current route path + meta.
 */
import { computed, inject } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
/** Optional override for the current page label (e.g. client name on profile page). */
const breadcrumbLabelOverride = inject('breadcrumbLabel', null)

/** Human-readable labels for path segments (first segment of path). */
const SEGMENT_LABELS = {
  '': 'Home',
  submissions: 'Submissions',
  'lead-submissions': 'Lead Submissions',
  users: 'Users',
  'field-submissions': 'Field Submissions',
  'back-office': 'Back Office',
  'customer-support': 'Customer Support',
  'vas-requests': 'VAS Requests',
  clients: 'Clients',
  'dsp-tracker': 'DSP Tracker',
  'gsm-tracker': 'DSP Tracker',
  employees: 'Employees',
  'cisco-extensions': 'Cisco Extensions',
  'attendance-log': 'Attendance Log',
  reports: 'Reports',
  settings: 'Settings',
  'team-hierarchy': 'Team Hierarchy',
  announcements: 'Announcements',
  notifications: 'Notifications',
  accounts: 'Accounts',
  'email-followups': 'Email Follow Up',
  'login-logs': 'Login Logs',
  expenses: 'Expenses',
  'personal-notes': 'Personal Notes',
  roles: 'Roles',
  permissions: 'Permissions',
  create: 'Create',
  edit: 'Edit',
  resubmit: 'Resubmit',
}

/** Action segments after which we skip the preceding numeric ID (e.g. /lead-submissions/4/resubmit -> skip Lead #4). */
const ACTION_SEGMENTS = ['resubmit', 'edit', 'permissions']

const breadcrumbs = computed(() => {
  const path = route.path
  if (!path || path === '/') {
    return [{ label: 'Home', to: '/', current: true }]
  }
  const segments = path.replace(/^\/|\/$/g, '').split('/')
  const items = []
  let acc = ''
  for (let i = 0; i < segments.length; i++) {
    const seg = segments[i]
    const isNumber = /^\d+$/.test(seg)
    const nextSeg = segments[i + 1]
    if (isNumber && i > 0 && nextSeg && ACTION_SEGMENTS.includes(nextSeg)) {
      acc += (acc ? '/' : '') + seg
      continue
    }
    acc += (acc ? '/' : '') + seg
    const fullPath = '/' + acc
    let label = SEGMENT_LABELS[seg]
    if (label === undefined) {
      if (isNumber && i > 0) {
        const parent = segments[i - 1]
        if (parent === 'lead-submissions') label = 'Lead #' + seg
        else if (parent === 'field-submissions') label = 'Submission #' + seg
        else if (parent === 'vas-requests') label = 'Request #' + seg
        else if (parent === 'customer-support') label = 'Request #' + seg
        else if (parent === 'users') label = 'User #' + seg
        else if (parent === 'employees') label = 'Employee #' + seg
        else if (parent === 'roles') label = 'Role'
        else label = '#' + seg
      } else {
        label = seg.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
      }
    }
    const isLast = i === segments.length - 1
    const overrideVal = breadcrumbLabelOverride != null && typeof breadcrumbLabelOverride === 'object' && 'value' in breadcrumbLabelOverride
      ? breadcrumbLabelOverride.value
      : breadcrumbLabelOverride
    const resolvedLabel = isLast && overrideVal != null && typeof overrideVal === 'string'
      ? overrideVal
      : label
    items.push({
      label: resolvedLabel,
      to: isLast ? null : fullPath,
      current: isLast,
    })
  }
  return [{ label: 'Home', to: '/', current: false }, ...items]
})
</script>

<template>
  <nav aria-label="Breadcrumb" class="breadcrumb-nav">
    <ol class="flex flex-wrap items-center gap-1.5 text-xs text-gray-600 leading-tight">
      <li
        v-for="(crumb, index) in breadcrumbs"
        :key="index"
        class="inline-flex items-center gap-1.5"
      >
        <template v-if="index > 0">
          <span class="text-gray-400" aria-hidden="true">/</span>
        </template>
        <router-link
          v-if="crumb.to"
          :to="crumb.to"
          class="hover:text-green-600 hover:underline"
        >
          {{ crumb.label }}
        </router-link>
        <span
          v-else
          class="font-medium text-gray-900"
          :aria-current="crumb.current ? 'page' : undefined"
        >
          {{ crumb.label }}
        </span>
      </li>
    </ol>
  </nav>
</template>

<style scoped>
/* When inside a flex row with heading, no bottom margin; otherwise keep spacing */
.breadcrumb-nav {
  margin-bottom: 0.25rem;
}
</style>
