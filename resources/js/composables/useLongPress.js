import { ref, onMounted, onUnmounted } from 'vue'

export function useLongPress(onLongPress, onClick, delay = 600) {
    let timer = null
    let isLongPress = false
    let startX = 0
    let startY = 0

    const start = (e) => {
        if (e.type === 'click' && e.button !== 0) return
        
        isLongPress = false
        if (e.touches && e.touches.length > 0) {
            startX = e.touches[0].clientX
            startY = e.touches[0].clientY
        } else {
            startX = e.clientX
            startY = e.clientY
        }

        timer = setTimeout(() => {
            isLongPress = true
            onLongPress(e)
        }, delay)
    }

    const clear = (e) => {
        if (timer) {
            clearTimeout(timer)
            timer = null
        }
    }

    const end = (e) => {
        if (timer) {
            clearTimeout(timer)
            timer = null
        }

        if (!isLongPress) {
            // Check if it was a drag, if so, don't click
            let endX = 0
            let endY = 0
            if (e.changedTouches && e.changedTouches.length > 0) {
                endX = e.changedTouches[0].clientX
                endY = e.changedTouches[0].clientY
            } else if (e.clientX !== undefined) {
                endX = e.clientX
                endY = e.clientY
            }

            // Allow small threshold of movement for inaccurate touches
            if (Math.abs(endX - startX) < 15 && Math.abs(endY - startY) < 15) {
                onClick(e)
            }
        }
    }

    return {
        mousedown: start,
        touchstart: start,
        mouseup: end,
        touchend: end,
        touchcancel: clear,
        mouseleave: clear,
        // Prevent context menu on long press
        contextmenu: (e) => {
            e.preventDefault()
            return false
        }
    }
}
