<script setup>
import Sidebar from '@/components/Sidebar.vue'
import Footer from '@/components/Footer.vue'
import SessionWarningBanner from '@/components/SessionWarningBanner.vue'
import { useInactivityLogout } from '@/composables/useInactivityLogout'

const { showWarning, countdownSecs, totalWarningSecs, extending, staySignedIn, logoutNow } = useInactivityLogout()
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-gray-100">
    <Sidebar />

    <div class="flex min-w-0 flex-1 flex-col min-h-0 overflow-hidden">
      <main class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden bg-gray-100 p-2">
        <router-view />
      </main>

      <footer class="flex-shrink-0 z-10">
        <Footer />
      </footer>
    </div>

    <!-- Session expiry warning banner -->
    <SessionWarningBanner
      :show="showWarning"
      :countdown-secs="countdownSecs"
      :total-warning-secs="totalWarningSecs"
      :extending="extending"
      @stay="staySignedIn"
      @logout="logoutNow"
    />
  </div>
</template>
