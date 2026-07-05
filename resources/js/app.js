import './bootstrap'
import { createApp } from 'vue'
import { polyfill } from 'mobile-drag-drop'
import { scrollBehaviourDragImageTranslateOverride } from 'mobile-drag-drop/scroll-behaviour'
import 'mobile-drag-drop/default.css'

polyfill({
    dragImageTranslateOverride: scrollBehaviourDragImageTranslateOverride
})

window.addEventListener('touchmove', function() {}, { passive: false })
import Dashboard from './components/Dashboard.vue'

const app = createApp(Dashboard)
app.mount('#app')
