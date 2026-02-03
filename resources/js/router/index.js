// router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import Dashboard from '@/pages/Dashboard.vue'
import PlaceholderPage from '@/pages/PlaceholderPage.vue'
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
      { path: 'lead-submissions', component: () => import('@/pages/LeadSubmissions.vue') },
      { path: 'users', component: () => import('@/pages/users/UsersPage.vue') },
      { path: 'users/create', component: () => import('@/pages/users/UserCreate.vue') },
      { path: 'users/:id', component: () => import('@/pages/users/UserShow.vue') },
      { path: 'users/:id/edit', component: () => import('@/pages/users/UserEdit.vue') },
      { path: 'back-office', ...ph('Back Office') },
      { path: 'field-head', ...ph('Field Head') },
      { path: 'customer-support', ...ph('Customer Support') },
      { path: 'vas-requests', ...ph('VAS Requests') },
      { path: 'clients', ...ph('Clients') },
      { path: 'gsm-tracker', ...ph('GSM Tracker') },
      { path: 'employees', ...ph('Employees') },
      { path: 'attendance-log', ...ph('Attendance Log') },
      { path: 'reports', ...ph('Reports') },
      { path: 'settings', ...ph('Settings') },
      { path: 'settings/team-hierarchy', component: () => import('@/pages/settings/TeamHierarchyPage.vue') },
      { path: 'announcements', ...ph('Announcements') },
      { path: 'notifications', ...ph('Notifications') },
      { path: 'accounts', ...ph('Accounts') },
      { path: 'email-followups', ...ph('Email Follow Up') },
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
})

const authPaths = ['/login', '/register', '/forgot-password', '/2fa/verify']
const isAuthPath = (path) => authPaths.includes(path) || path.startsWith('/reset-password')

router.beforeEach(async (to, from, next) => {
  const auth = useAuthStore()
  const authPath = isAuthPath(to.path)

  if (!auth.user) await auth.fetchUser()

  if (authPath && auth.isAuthenticated && to.path !== '/2fa/verify') {
    return next('/')
  }
  if (to.path === '/2fa/verify' && !auth.isAuthenticated) {
    return next('/login')
  }
  if (!authPath && !auth.isAuthenticated) {
    return next('/login')
  }

  next()
})

export default router
