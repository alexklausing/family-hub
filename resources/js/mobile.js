import './bootstrap'
import { createApp } from 'vue'
import MobileApp from './components/mobile/MobileApp.vue'

createApp(MobileApp).mount('#app')

if ('serviceWorker' in navigator && (window.isSecureContext || location.hostname === 'localhost')) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            /* service workers require a secure context (https or localhost) */
        })
    })
}
