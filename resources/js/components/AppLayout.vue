<template>
  <div class="flex min-h-screen">
    <Sidebar />
    <div class="flex-1 flex flex-col">
      <Topbar />
      <main class="flex-1 overflow-y-auto p-4 sm:p-6">
        <router-view />
      </main>
      <Footer />
    </div>
  </div>
</template>

<script setup>
import Sidebar from '@/components/Sidebar.vue'
import Topbar from '@/components/Topbar.vue'
import Footer from '@/components/Footer.vue'
</script>
