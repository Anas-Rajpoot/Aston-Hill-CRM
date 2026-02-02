// router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import Dashboard from '@/pages/Dashboard.vue'
import UsersPage from '@/pages/users/UsersPage.vue'
import UserShow from '@/pages/users/UserShow.vue'
import UserEdit from '@/pages/users/UserEdit.vue'
import UserCreate from '@/pages/users/UserCreate.vue'
import LeadSubmissions from '@/pages/LeadSubmissions.vue'
import SubmissionsPage from '@/pages/submissions/SubmissionsPage.vue'
import PlaceholderPage from '@/pages/PlaceholderPage.vue'
import TeamHierarchyPage from '@/pages/settings/TeamHierarchyPage.vue'
import Login from '@/pages/auth/Login.vue'
import Register from '@/pages/auth/Register.vue'
import ForgotPassword from '@/pages/auth/ForgotPassword.vue'
import ResetPassword from '@/pages/auth/ResetPassword.vue'
import TwoFactorVerify from '@/pages/auth/TwoFactorVerify.vue'
import { useAuthStore } from '@/stores/auth'

const ph = (title) => ({ component: PlaceholderPage, props: { title } })

// Auth pages – no sidebar, topbar, footer
const authRoutes = [
  { path: '/login', name: 'login', component: Login },
  { path: '/register', name: 'register', component: Register },
  { path: '/forgot-password', name: 'forgot-password', component: ForgotPassword },
  { path: '/reset-password/:token', name: 'reset-password', component: ResetPassword },
  { path: '/2fa/verify', name: '2fa-verify', component: TwoFactorVerify },
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
      { path: 'submissions', component: SubmissionsPage },
      { path: 'lead-submissions', component: LeadSubmissions },
      { path: 'users', component: UsersPage },
      { path: 'users/create', component: UserCreate },
      { path: 'users/:id', component: UserShow },
      { path: 'users/:id/edit', component: UserEdit },
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
      { path: 'settings/team-hierarchy', component: TeamHierarchyPage },
      { path: 'announcements', ...ph('Announcements') },
      { path: 'notifications', ...ph('Notifications') },
      { path: 'accounts', ...ph('Accounts') },
      { path: 'email-followups', ...ph('Email Follow Up') },
      { path: 'login-logs', ...ph('Login Logs') },
      { path: 'expenses', ...ph('Expense Tracker') },
      { path: 'personal-notes', ...ph('Personal Notes') },
      { path: 'roles', ...ph('Roles') },
      { path: 'permissions', ...ph('Permissions') },
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
