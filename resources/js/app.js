import './bootstrap'
import Alpine from 'alpinejs'
import $ from 'jquery'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'

import App from './App.vue'

window.$ = window.jQuery = $
window.Alpine = Alpine
Alpine.start()

import 'datatables.net'
import 'datatables.net-dt/css/dataTables.dataTables.css'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')
