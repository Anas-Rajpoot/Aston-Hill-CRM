<script setup>
import Sidebar from '@/components/Sidebar.vue'
import Topbar from '@/components/Topbar.vue'
import Footer from '@/components/Footer.vue'
</script>

<template>
  <div class="flex min-h-screen bg-gray-100">
    <Sidebar />

    <div class="flex-1 flex flex-col">
      <Topbar />

      <main class="flex-1 p-6">
        <router-view />
      </main>

      <Footer />
    </div>
  </div>
</template>
