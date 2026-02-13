<template>
  <router-view />
</template>

<script setup>
import { onMounted } from 'vue'

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
})
</script>