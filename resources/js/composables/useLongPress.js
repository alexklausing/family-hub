import { ref, onMounted, onUnmounted } from 'vue'

const SYNTHETIC_CLICK_GUARD_MS = 500

export function useLongPress(onLongPress, onClick, delay = 600) {
    let timer = null
    let isLongPress = false
    let startX = 0
    let startY = 0
    let lastTouchEndAt = 0

    const start = (e) => {
        if (e.type === 'click' && e.button !== 0) return

        // The browser fires synthetic mouse events ~300ms after a touch
        // gesture. Those events land on whatever is under the finger at the
        // time (e.g. a drawer backdrop a tap just opened), so ignore them to
        // avoid double-handling / instantly closing a just-opened panel.
        if (
            e.type === 'mousedown' &&
            Date.now() - lastTouchEndAt < SYNTHETIC_CLICK_GUARD_MS
        ) {
            return
        }

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
        if (e.type === 'mouseup' && Date.now() - lastTouchEndAt < SYNTHETIC_CLICK_GUARD_MS) {
            return
        }

        if (e.type === 'touchend') {
            lastTouchEndAt = Date.now()
            // Cancel the delayed synthetic mouse down/up/click that the
            // browser would otherwise fire after the touch. Without this the
            // synthetic click can retarget to whatever is now under the finger
            // (e.g. a just-opened drawer backdrop) and close it immediately.
            if (e.cancelable) {
                e.preventDefault()
            }
        }

        clear(e)

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
