// router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import Dashboard from '@/pages/Dashboard.vue'
import PlaceholderPage from '@/pages/PlaceholderPage.vue'
import UserShow from '@/pages/users/UserShow.vue'
import UserEdit from '@/pages/users/UserEdit.vue'
import LoginPage from '@/pages/auth/Login.vue'
import RegisterPage from '@/pages/auth/Register.vue'
import ForgotPasswordPage from '@/pages/auth/ForgotPassword.vue'
import ResetPasswordPage from '@/pages/auth/ResetPassword.vue'
import TwoFactorVerifyPage from '@/pages/auth/TwoFactorVerify.vue'
import ChangePasswordPage from '@/pages/auth/ChangePassword.vue'
import { useAuthStore } from '@/stores/auth'
import { canAccessRoute } from '@/lib/accessControl'

// Lazy-load heavy pages so initial HTML + JS parse stays fast (DOMContentLoaded < 3s).
const ph = (title) => ({ component: PlaceholderPage, props: { title } })

const authRoutes = [
  { path: '/login', name: 'login', component: LoginPage },
  { path: '/register', name: 'register', component: RegisterPage },
  { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordPage },
  { path: '/reset-password/:token', name: 'reset-password', component: ResetPasswordPage },
  { path: '/2fa/verify', name: '2fa-verify', component: TwoFactorVerifyPage },
  { path: '/change-password', name: 'change-password', component: ChangePasswordPage },
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
      { path: 'customer-support/:id/resubmit', component: () => import('@/pages/customer-support/CustomerSupportResubmitPage.vue'), name: 'cs-resubmit' },
      { path: 'vas-requests', component: () => import('@/pages/vas-requests/VasRequestsListingPage.vue') },
      { path: 'vas-requests/:id/edit', component: () => import('@/pages/vas-requests/VasRequestEditPage.vue'), name: 'vas-request-edit' },
      { path: 'vas-requests/:id', component: () => import('@/pages/vas-requests/VasRequestDetailPage.vue'), name: 'vas-request-detail' },
      { path: 'special-requests', component: () => import('@/pages/special-requests/SpecialRequestsListingPage.vue') },
      { path: 'special-requests/:id/edit', component: () => import('@/pages/special-requests/SpecialRequestEditPage.vue'), name: 'special-request-edit' },
      { path: 'special-requests/:id', component: () => import('@/pages/special-requests/SpecialRequestDetailPage.vue'), name: 'special-request-detail' },
      { path: 'all-clients', name: 'clients.all', component: () => import('@/pages/clients/AllClientsListingPage.vue'), meta: { title: 'All Clients' } },
      { path: 'clients', name: 'clients.index', component: () => import('@/pages/clients/ClientsListingPage.vue') },
      { path: 'clients/create', name: 'clients.create', component: () => import('@/pages/clients/ClientCreatePage.vue'), meta: { title: 'Add New Client' } },
      { path: 'clients/products/:id', component: () => import('@/pages/clients/ClientProductDetailPage.vue'), name: 'client-product-detail' },
      { path: 'clients/products/:id/edit', component: () => import('@/pages/clients/ClientProductEditPage.vue'), name: 'client-product-edit' },
      { path: 'clients/:id', component: () => import('@/pages/clients/ClientProfilePage.vue'), name: 'client-profile' },
      { path: 'clients/:id/edit', component: () => import('@/pages/clients/ClientEditPage.vue'), meta: { title: 'Edit Client' } },
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

function withTimeout(promise, ms = 3000) {
  return Promise.race([
    promise,
    new Promise((resolve) => setTimeout(resolve, ms)),
  ])
}

// Note: In-flight API requests are cancelled by composables (e.g. useUserEditData) via AbortController in onUnmounted when navigating away.

router.beforeEach(async (to, from, next) => {
  const auth = useAuthStore()
  const authPath = isAuthPath(to.path)

  // Allow /2fa/verify without requiring auth.user so login → 2fa redirect always shows the page
  if (to.path === '/2fa/verify') {
    return next()
  }

  // Allow auth pages to render immediately; never block UI on bootstrap APIs.
  // If already authenticated, move away from login/register screens.
  if (authPath && to.path !== '/change-password') {
    if (auth.isAuthenticated) return next('/')
    auth.fetchUser()
      .then(() => {
        if (auth.isAuthenticated && router.currentRoute.value.path === to.path) {
          router.replace('/')
        }
      })
      .catch(() => {})
    return next()
  }

  // Allow /change-password for authenticated users who must change their password
  if (to.path === '/change-password') {
    if (!auth.user && !auth.isAuthenticated) {
      await withTimeout(auth.fetchUser(), 3000)
    }
    // If not authenticated at all, go to login
    if (!auth.isAuthenticated) return next('/login')
    return next()
  }

  if (!auth.user || !auth.user.pending2FA) {
    await withTimeout(auth.fetchUser(), 3000)
  }

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

  // Never apply module access-control to auth pages.
  if (authPath) {
    return next()
  }

  // Default-deny module access for non-superadmin users.
  // Dashboard remains the safe fallback for users with no assigned permissions.
  if (!canAccessRoute(auth.user, to.path)) {
    return next('/')
  }

  next()
})

// Recover from occasional chunk-load/startup race by reloading once.
router.onError((err) => {
  const msg = String(err?.message || '')
  if (/Loading chunk \d+ failed|Failed to fetch dynamically imported module|Importing a module script failed/i.test(msg)) {
    const key = '__router_chunk_retry__'
    const retried = sessionStorage.getItem(key) === '1'
    if (!retried) {
      sessionStorage.setItem(key, '1')
      window.location.reload()
      return
    }
    sessionStorage.removeItem(key)
  }
})

export default router
