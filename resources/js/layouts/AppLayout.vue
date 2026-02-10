<script setup>
import Sidebar from '@/components/Sidebar.vue'
import Topbar from '@/components/Topbar.vue'
</script>

<template>
  <div class="flex min-h-screen bg-gray-100">
    <Sidebar />

    <div class="flex min-h-0 flex-1 flex-col bg-gray-100">
      <Topbar />

      <main class="min-h-0 flex-1 overflow-auto bg-gray-100 px-4 py-5">
        <router-view />
      </main>
    </div>
  </div>
</template>
