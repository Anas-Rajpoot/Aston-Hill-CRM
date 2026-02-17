// router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import Dashboard from '@/pages/Dashboard.vue'
import PlaceholderPage from '@/pages/PlaceholderPage.vue'
import UserShow from '@/pages/users/UserShow.vue'
import UserEdit from '@/pages/users/UserEdit.vue'
import { useAuthStore } from '@/stores/auth'

// Lazy-load heavy pages so initial HTML + JS parse stays fast (DOMContentLoaded < 3s).
const ph = (title) => ({ component: PlaceholderPage, props: { title } })

const authRoutes = [
  { path: '/login', name: 'login', component: () => import('@/pages/auth/Login.vue') },
  { path: '/register', name: 'register', component: () => import('@/pages/auth/Register.vue') },
  { path: '/forgot-password', name: 'forgot-password', component: () => import('@/pages/auth/ForgotPassword.vue') },
  { path: '/reset-password/:token', name: 'reset-password', component: () => import('@/pages/auth/ResetPassword.vue') },
  { path: '/2fa/verify', name: '2fa-verify', component: () => import('@/pages/auth/TwoFactorVerify.vue') },
  { path: '/change-password', name: 'change-password', component: () => import('@/pages/auth/ChangePassword.vue') },
]

const routes = [
  ...authRoutes,
  // Explicit /dashboard so Laravel's redirect (guest→dashboard) matches Vue Router
  { path: '/dashboard', redirect: '/' },
  {
    path: '/',
    component: AppLayout,
    children: [
      { path: '', component: Dashboard, name: 'home' },
      { path: 'submissions', component: () => import('@/pages/submissions/SubmissionsPage.vue') },
      { path: 'lead-submissions', component: () => import('@/pages/lead-submissions/LeadSubmissionsListingPage.vue') },
      { path: 'lead-submissions/audit-log', component: () => import('@/pages/lead-submissions/LeadSubmissionAuditLogPage.vue'), name: 'lead-submission-audit-log' },
      { path: 'lead-submissions/:id/resubmit', component: () => import('@/pages/lead-submissions/LeadResubmissionPage.vue'), name: 'lead-resubmission' },
      { path: 'lead-submissions/:id/edit', component: () => import('@/pages/lead-submissions/LeadSubmissionEditPage.vue'), name: 'lead-submission-edit' },
      { path: 'lead-submissions/:id', component: () => import('@/pages/lead-submissions/LeadSubmissionDetailPage.vue'), name: 'lead-submission-detail' },
      { path: 'users', component: () => import('@/pages/users/UsersPage.vue') },
      { path: 'users/create', component: () => import('@/pages/users/UserCreate.vue') },
      { path: 'users/:id', component: UserShow },
      { path: 'users/:id/edit', component: UserEdit },
      { path: 'back-office', ...ph('Back Office') },
      { path: 'field-submissions', component: () => import('@/pages/field-submissions/FieldSubmissionsListingPage.vue') },
      { path: 'field-submissions/audit-log', component: () => import('@/pages/field-submissions/FieldSubmissionAuditLogPage.vue'), name: 'field-submission-audit-log' },
      { path: 'field-submissions/:id/edit', component: () => import('@/pages/field-submissions/FieldSubmissionEditPage.vue'), name: 'field-submission-edit' },
      { path: 'field-submissions/:id', component: () => import('@/pages/field-submissions/FieldSubmissionDetailPage.vue'), name: 'field-submission-detail' },
      { path: 'customer-support', component: () => import('@/pages/customer-support/CustomerSupportListingPage.vue') },
      { path: 'customer-support/:id', component: () => import('@/pages/customer-support/CustomerSupportDetailPage.vue') },
      { path: 'customer-support/:id/edit', component: () => import('@/pages/customer-support/CustomerSupportEditPage.vue') },
      { path: 'vas-requests', component: () => import('@/pages/vas-requests/VasRequestsListingPage.vue') },
      { path: 'vas-requests/:id/edit', component: () => import('@/pages/vas-requests/VasRequestEditPage.vue'), name: 'vas-request-edit' },
      { path: 'vas-requests/:id', component: () => import('@/pages/vas-requests/VasRequestDetailPage.vue'), name: 'vas-request-detail' },
      { path: 'clients', component: () => import('@/pages/clients/ClientsListingPage.vue') },
      { path: 'clients/create', component: () => import('@/pages/clients/ClientCreatePage.vue'), meta: { title: 'Add New Client' } },
      { path: 'clients/:id', component: () => import('@/pages/clients/ClientProfilePage.vue'), name: 'client-profile' },
      { path: 'clients/:id/edit', ...ph('Edit Client') },
      { path: 'order-status', component: () => import('@/pages/order-status/OrderStatusListingPage.vue'), meta: { title: 'Order Status' } },
      { path: 'dsp-tracker', component: () => import('@/pages/dsp-tracker/DSPTrackerListingPage.vue') },
      { path: 'gsm-tracker', redirect: '/dsp-tracker' },
      { path: 'verifiers-detail', component: () => import('@/pages/verifiers/VerifiersDetailPage.vue'), meta: { title: 'Verifiers Detail' } },
      { path: 'employees', component: () => import('@/pages/employees/EmployeesListingPage.vue') },
      { path: 'employees/:id', component: UserShow },
      { path: 'employees/:id/edit', component: UserEdit },
      { path: 'cisco-extensions', component: () => import('@/pages/extensions/ExtensionsListingPage.vue') },
      { path: 'cisco-extensions/create', ...ph('Add Extension') },
      { path: 'cisco-extensions/:id', component: () => import('@/pages/extensions/ExtensionDetailPage.vue') },
      { path: 'cisco-extensions/:id/edit', ...ph('Edit Extension') },
      { path: 'attendance-log', component: () => import('@/pages/attendance/AttendanceLogPage.vue') },
      { path: 'reports', component: () => import('@/pages/reports/ReportsMainPage.vue'), meta: { title: 'Reports' } },
      { path: 'reports/lead', component: () => import('@/pages/reports/LeadReportsPage.vue'), meta: { title: 'Lead Reports' } },
      { path: 'reports/field-operations', component: () => import('@/pages/reports/FieldOperationsReportsPage.vue'), meta: { title: 'Field Operations Reports' } },
      { path: 'reports/vas', component: () => import('@/pages/reports/VasReportsPage.vue'), meta: { title: 'VAS Reports' } },
      { path: 'reports/sla', component: () => import('@/pages/reports/SlaPerformanceReportsPage.vue'), meta: { title: 'SLA Performance Report' } },
      { path: 'settings', component: () => import('@/pages/settings/SettingsPage.vue'), meta: { title: 'Settings' } },
      { path: 'settings/team-hierarchy', component: () => import('@/pages/settings/TeamHierarchyPage.vue') },
      { path: 'settings/system-preferences', component: () => import('@/pages/settings/SystemPreferencesPage.vue'), meta: { title: 'System Preferences' } },
      { path: 'settings/sla', component: () => import('@/pages/settings/SlaConfigurationPage.vue'), meta: { title: 'SLA Configuration' } },
      { path: 'settings/notifications-email', component: () => import('@/pages/settings/NotificationRulesPage.vue'), meta: { title: 'Notifications & Email Rules' } },
      { path: 'settings/announcement-center', component: () => import('@/pages/settings/AnnouncementCenterPage.vue'), meta: { title: 'Announcement Center' } },
      { path: 'settings/library', component: () => import('@/pages/settings/LibraryPage.vue'), meta: { title: 'Library — Templates & Forms' } },
      { path: 'settings/data-import-export', component: () => import('@/pages/PlaceholderPage.vue'), props: { title: 'Data & Import/Export' }, meta: { title: 'Data & Import/Export' } },
      { path: 'settings/security-session', component: () => import('@/pages/settings/SecuritySessionPage.vue'), meta: { title: 'Security, Session & Access Control' } },
      { path: 'settings/audit-logs', component: () => import('@/pages/settings/AuditLogsPage.vue'), meta: { title: 'Audit Logs' } },
      { path: 'announcements', ...ph('Announcements') },
      { path: 'notifications', ...ph('Notifications') },
      { path: 'accounts', ...ph('Accounts') },
      { path: 'email-followups', component: () => import('@/pages/email-followups/EmailFollowUpPage.vue') },
      { path: 'login-logs', ...ph('Login Logs') },
      { path: 'expenses', component: () => import('@/pages/expenses/ExpenseTrackerPage.vue') },
      { path: 'expenses/create', ...ph('Add Expense') },
      { path: 'expenses/:id', ...ph('Expense Detail') },
      { path: 'expenses/:id/edit', component: () => import('@/pages/expenses/ExpenseEditPage.vue'), meta: { title: 'Edit Expense Tracker' } },
      { path: 'personal-notes/create', component: () => import('@/pages/personal-notes/PersonalNoteFormPage.vue'), meta: { title: 'New Note' } },
      { path: 'personal-notes/:id/edit', component: () => import('@/pages/personal-notes/PersonalNoteFormPage.vue'), meta: { title: 'Edit Note' } },
      { path: 'personal-notes/:id?', component: () => import('@/pages/personal-notes/PersonalNotesPage.vue'), meta: { title: 'Personal Notes' } },
      { path: 'teams', component: () => import('@/pages/teams/TeamsListingPage.vue'), meta: { title: 'Teams' } },
      { path: 'teams/create', component: () => import('@/pages/teams/TeamCreatePage.vue'), meta: { title: 'Create Team' } },
      { path: 'teams/:id', component: () => import('@/pages/teams/TeamDetailPage.vue'), meta: { title: 'Team Details' } },
      { path: 'teams/:id/edit', component: () => import('@/pages/teams/TeamEditPage.vue'), meta: { title: 'Edit Team' } },
      { path: 'teams/:id/members', component: () => import('@/pages/teams/TeamMembersPage.vue'), meta: { title: 'Manage Team Members' } },
      { path: 'roles', component: () => import('@/pages/roles/RolesPage.vue') },
      { path: 'roles/create', component: () => import('@/pages/roles/RoleCreate.vue') },
      { path: 'roles/:role', redirect: (to) => ({ path: `/roles/${to.params.role}/permissions` }) },
      { path: 'roles/:role/edit', component: () => import('@/pages/roles/RoleEdit.vue') },
      { path: 'roles/:role/permissions', component: () => import('@/pages/roles/RolePermissions.vue') },
      { path: 'permissions', component: () => import('@/pages/roles/PermissionsPage.vue') },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})

const authPaths = ['/login', '/register', '/forgot-password', '/2fa/verify', '/change-password']
const isAuthPath = (path) => authPaths.includes(path) || path.startsWith('/reset-password')

// Note: In-flight API requests are cancelled by composables (e.g. useUserEditData) via AbortController in onUnmounted when navigating away.

router.beforeEach(async (to, from, next) => {
  const auth = useAuthStore()
  const authPath = isAuthPath(to.path)

  // Allow /2fa/verify without requiring auth.user so login → 2fa redirect always shows the page
  if (to.path === '/2fa/verify') {
    return next()
  }

  // Allow /change-password for authenticated users who must change their password
  if (to.path === '/change-password') {
    if (!auth.user && !auth.isAuthenticated) {
      await auth.fetchUser()
    }
    // If not authenticated at all, go to login
    if (!auth.isAuthenticated) return next('/login')
    return next()
  }

  if (!auth.user || !auth.user.pending2FA) await auth.fetchUser()

  if (authPath && auth.isAuthenticated) {
    return next('/')
  }
  if (!authPath && !auth.isAuthenticated) {
    return next('/login')
  }

  // If authenticated user has a pending password action, redirect to change-password
  // (except for API/logout routes)
  if (auth.isAuthenticated && auth.passwordAction && to.path !== '/change-password') {
    return next('/change-password')
  }

  next()
})

export default router
