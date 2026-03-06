<script setup>
import Sidebar from '@/components/SidebarNew.vue'
import Topbar from '@/components/Topbar.vue'
import Footer from '@/components/Footer.vue'
import SessionWarningBanner from '@/components/SessionWarningBanner.vue'
import { useInactivityLogout } from '@/composables/useInactivityLogout'
import { useSidebar } from '@/composables/useSidebar'
import { watch } from 'vue'
import { useRoute } from 'vue-router'

const { showWarning, countdownSecs, totalWarningSecs, extending, staySignedIn, logoutNow } = useInactivityLogout()
const { mobileOpen, closeMobile } = useSidebar()
const route = useRoute()

// Close mobile sidebar on route change
watch(() => route.path, () => closeMobile())
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-brand-bg">
    <!-- Desktop sidebar (hidden below lg) -->
    <div class="hidden lg:flex flex-shrink-0">
      <Sidebar />
    </div>

    <!-- Mobile overlay backdrop -->
    <Transition name="fade">
      <div
        v-if="mobileOpen"
        class="fixed inset-0 bg-black/50 z-40 lg:hidden"
        @click="closeMobile"
      />
    </Transition>

    <!-- Mobile sidebar drawer -->
    <Transition name="slide-left">
      <aside
        v-if="mobileOpen"
        class="fixed inset-y-0 left-0 z-50 w-64 lg:hidden shadow-2xl"
      >
        <Sidebar :force-expanded="true" />
      </aside>
    </Transition>

    <div class="flex min-w-0 flex-1 flex-col min-h-0 overflow-hidden">
      <Topbar />

      <main class="main-content min-h-0 flex-1 overflow-y-auto overflow-x-hidden bg-brand-bg">
        <div class="min-w-0">
          <router-view />
        </div>
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

<style scoped>
/* Datatables: keep empty/loading rows compact and consistent. */
main :deep(.overflow-x-auto table tbody td[colspan]) {
  padding-top: 2rem !important;
  padding-bottom: 2rem !important;
}

/* Mobile sidebar slide animation */
.slide-left-enter-active,
.slide-left-leave-active {
  transition: transform 0.3s ease;
}
.slide-left-enter-from,
.slide-left-leave-to {
  transform: translateX(-100%);
}

/* Fade for overlay backdrop */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
