<script setup>
import Sidebar from '@/components/Sidebar.vue'
import Topbar from '@/components/Topbar.vue'
import Footer from '@/components/Footer.vue'
import { useInactivityLogout } from '@/composables/useInactivityLogout'

useInactivityLogout()
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-gray-100">
    <Sidebar />

    <!-- Content area: only this column's main scrolls; header and footer stay fixed -->
    <div class="flex min-w-0 flex-1 flex-col min-h-0 overflow-hidden">
      <header class="flex-shrink-0 z-20 bg-white">
        <Topbar />
      </header>

      <main class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden bg-gray-100 px-4 py-5">
        <router-view />
      </main>

      <footer class="flex-shrink-0 z-10">
        <Footer />
      </footer>
    </div>
  </div>
</template>
