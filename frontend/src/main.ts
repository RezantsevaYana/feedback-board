import './scss/app.scss'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import '@/shared/composables/useTheme' // применяет сохранённую тему до первого paint

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')
