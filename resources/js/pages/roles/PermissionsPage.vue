<script setup>
/**
 * Progressive rendering: shell (title, description, "Go to Roles") renders immediately.
 * Roles list loads in background with section-level skeleton.
 */
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import SkeletonList from '@/components/skeletons/SkeletonList.vue'

const router = useRouter()
const roles = ref([])
const loading = ref(true)

onMounted(() => {
  api.get('/super-admin/roles')
    .then(({ data }) => {
      roles.value = (data.data ?? []).filter((r) => r.name !== 'superadmin')
    })
    .catch(() => { roles.value = [] })
    .finally(() => { loading.value = false })
})

const goToRolePermissions = (role) => {
  router.push(`/roles/${role.id}/permissions`)
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Permissions</h1>
      <p class="mt-1 text-sm text-gray-500">
        Permissions are set per role. Choose a role below to view or edit its permissions, or go to Roles to create and manage roles.
      </p>
    </div>
    <Breadcrumbs />

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-6">
      <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <h2 class="text-base font-semibold text-gray-900">Manage permissions by role</h2>
        <router-link
          to="/roles"
          class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        >
          Go to Roles
        </router-link>
      </div>

      <SkeletonList v-if="loading" :items="5" />
      <div v-else-if="!roles.length" class="py-4 text-gray-500 text-sm">
        No roles yet. <router-link to="/roles/create" class="text-indigo-600 hover:underline">Create a role</router-link> first, then set its permissions.
      </div>
      <ul v-else class="space-y-2">
        <li
          v-for="role in roles"
          :key="role.id"
          class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 hover:bg-gray-50"
        >
          <span class="font-medium text-gray-900">{{ role.name }}</span>
          <button
            type="button"
            @click="goToRolePermissions(role)"
            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Set permissions
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>
