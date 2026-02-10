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
      { path: 'customer-support/:id', component: () => import('@/pages/PlaceholderPage.vue'), props: { title: 'Customer Support Request Details' } },
      { path: 'customer-support/:id/edit', component: () => import('@/pages/PlaceholderPage.vue'), props: { title: 'Edit Customer Support Request' } },
      { path: 'vas-requests', component: () => import('@/pages/vas-requests/VasRequestsListingPage.vue') },
      { path: 'vas-requests/:id/edit', component: () => import('@/pages/vas-requests/VasRequestEditPage.vue'), name: 'vas-request-edit' },
      { path: 'vas-requests/:id', component: () => import('@/pages/vas-requests/VasRequestDetailPage.vue'), name: 'vas-request-detail' },
      { path: 'clients', ...ph('Clients') },
      { path: 'gsm-tracker', ...ph('GSM Tracker') },
      { path: 'employees', component: () => import('@/pages/employees/EmployeesListingPage.vue') },
      { path: 'attendance-log', ...ph('Attendance Log') },
      { path: 'reports', ...ph('Reports') },
      { path: 'settings', ...ph('Settings') },
      { path: 'settings/team-hierarchy', component: () => import('@/pages/settings/TeamHierarchyPage.vue') },
      { path: 'announcements', ...ph('Announcements') },
      { path: 'notifications', ...ph('Notifications') },
      { path: 'accounts', ...ph('Accounts') },
      { path: 'email-followups', component: () => import('@/pages/email-followups/EmailFollowUpPage.vue') },
      { path: 'login-logs', ...ph('Login Logs') },
      { path: 'expenses', ...ph('Expense Tracker') },
      { path: 'personal-notes', ...ph('Personal Notes') },
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

const authPaths = ['/login', '/register', '/forgot-password', '/2fa/verify']
const isAuthPath = (path) => authPaths.includes(path) || path.startsWith('/reset-password')

router.beforeEach(async (to, from, next) => {
  const auth = useAuthStore()
  const authPath = isAuthPath(to.path)

  // Allow /2fa/verify without requiring auth.user so login → 2fa redirect always shows the page
  if (to.path === '/2fa/verify') {
    return next()
  }

  if (!auth.user || !auth.user.pending2FA) await auth.fetchUser()

  if (authPath && auth.isAuthenticated) {
    return next('/')
  }
  if (!authPath && !auth.isAuthenticated) {
    return next('/login')
  }

  next()
})

export default router
