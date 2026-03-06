import { ref, watch } from 'vue'

const STORAGE_KEY = 'sidebar_collapsed'

const collapsed = ref(localStorage.getItem(STORAGE_KEY) === '1')
const mobileOpen = ref(false)

watch(collapsed, (val) => {
  localStorage.setItem(STORAGE_KEY, val ? '1' : '0')
})

export function useSidebar() {
  /** Desktop: toggle collapse. Mobile (<lg): toggle drawer. */
  function toggle() {
    if (window.innerWidth < 1024) {
      mobileOpen.value = !mobileOpen.value
    } else {
      collapsed.value = !collapsed.value
    }
  }

  function closeMobile() {
    mobileOpen.value = false
  }

  return { collapsed, mobileOpen, toggle, closeMobile }
}
