import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import GuestLayout from '@/layouts/GuestLayout.vue'
import Users from '@/pages/Users.vue'
import Login from '@/pages/auth/Login.vue'

const routes = [
  {
    path: '/login',
    component: GuestLayout,
    children: [{ path: '', component: Login }]
  },
  {
    path: '/',
    component: AppLayout,
    children: [
      { path: 'users', component: Users }
    ]
  }
]

export default createRouter({
  history: createWebHistory(),
  routes
})
