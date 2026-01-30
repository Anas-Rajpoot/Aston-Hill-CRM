// router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import Dashboard from '@/pages/Dashboard.vue'
import Users from '@/pages/Users.vue'
import LeadSubmissions from '@/pages/LeadSubmissions.vue'
import SubmissionsPage from '@/pages/submissions/SubmissionsPage.vue'
import PlaceholderPage from '@/pages/PlaceholderPage.vue'
import Login from '@/pages/auth/Login.vue'
import { useAuthStore } from '@/stores/auth'

const ph = (title) => ({ component: PlaceholderPage, props: { title } })

const routes = [
  {
    path: '/login',
    component: Login,
  },
  {
    path: '/',
    component: AppLayout,
    children: [
      { path: '', component: Dashboard },
      { path: 'dashboard', redirect: '' },
      { path: 'submissions', component: SubmissionsPage },
      { path: 'lead-submissions', component: LeadSubmissions },
      { path: 'users', component: Users },
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

router.beforeEach(async (to, from, next) => {
  const auth = useAuthStore()
  if (!auth.user) await auth.fetchUser()
  if (to.path !== '/login' && !auth.isAuthenticated) return next('/login')
  if (to.path === '/login' && auth.isAuthenticated) return next('/')
  next()
})

export default router
