import { ref, watch } from 'vue'

const STORAGE_KEY = 'sidebar_collapsed'

const collapsed = ref(localStorage.getItem(STORAGE_KEY) === '1')

watch(collapsed, (val) => {
  localStorage.setItem(STORAGE_KEY, val ? '1' : '0')
})

export function useSidebar() {
  function toggle() {
    collapsed.value = !collapsed.value
  }

  return { collapsed, toggle }
}
