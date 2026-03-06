import './bootstrap'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'

import App from './App.vue'

const app = createApp(App)
app.use(createPinia())
app.use(router)

const mountApp = () => {
  const el = document.getElementById('app')
  if (el) {
    app.mount('#app')
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mountApp)
} else {
  mountApp()
}
