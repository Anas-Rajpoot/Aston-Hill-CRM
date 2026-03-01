<template>
  <router-view />
  <Toast
    :show="showToast"
    :type="toastType"
    :message="toastMessage"
    :duration="4000"
    @dismiss="showToast = false"
  />
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import Toast from '@/components/Toast.vue'

const showToast = ref(false)
const toastType = ref('success')
const toastMessage = ref('')

function onGlobalToast(event) {
  const type = event?.detail?.type === 'error' ? 'error' : 'success'
  const message = String(event?.detail?.message || '').trim()
  if (!message) return
  toastType.value = type
  toastMessage.value = message
  showToast.value = true
}

// Ensure custom favicon is used on every SPA route (avoids wrong icon on e.g. /roles)
onMounted(() => {
  const base = window.location.origin
  const favicon = `${base}/favicon.ico`
  for (const rel of ['icon', 'shortcut icon']) {
    let link = document.querySelector(`link[rel="${rel}"]`)
    if (!link) {
      link = document.createElement('link')
      link.rel = rel
      link.type = 'image/x-icon'
      document.head.appendChild(link)
    }
    if (link.href !== favicon) link.href = favicon
  }

  window.addEventListener('app:toast', onGlobalToast)
})

onUnmounted(() => {
  window.removeEventListener('app:toast', onGlobalToast)
})
</script>