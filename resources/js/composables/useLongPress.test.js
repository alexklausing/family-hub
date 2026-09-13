import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { useLongPress } from './useLongPress'

const makeTouchEvent = (type, touches, changedTouches, cancelable = true) => ({
    type,
    touches,
    changedTouches,
    clientX: touches?.[0]?.clientX,
    clientY: touches?.[0]?.clientY,
    cancelable,
    preventDefault: vi.fn(),
})

describe('useLongPress', () => {
    beforeEach(() => {
        vi.useFakeTimers()
    })

    afterEach(() => {
        vi.useRealTimers()
    })

    it('calls onClick for a short tap with no movement', () => {
        const onLongPress = vi.fn()
        const onClick = vi.fn()
        const handlers = useLongPress(onLongPress, onClick)

        handlers.touchstart(
            makeTouchEvent('touchstart', [{ clientX: 10, clientY: 10 }], []),
        )
        handlers.touchend(
            makeTouchEvent('touchend', [], [{ clientX: 12, clientY: 11 }]),
        )

        expect(onClick).toHaveBeenCalledTimes(1)
        expect(onLongPress).not.toHaveBeenCalled()
    })

    it('calls onLongPress when the pointer is held past the delay and skips the click', () => {
        const onLongPress = vi.fn()
        const onClick = vi.fn()
        const handlers = useLongPress(onLongPress, onClick)

        handlers.touchstart(
            makeTouchEvent('touchstart', [{ clientX: 10, clientY: 10 }], []),
        )
        vi.advanceTimersByTime(700)

        expect(onLongPress).toHaveBeenCalledTimes(1)
        expect(onClick).not.toHaveBeenCalled()

        handlers.touchend(
            makeTouchEvent('touchend', [], [{ clientX: 10, clientY: 10 }]),
        )
        expect(onClick).not.toHaveBeenCalled()
    })

    it('does not fire onClick for a drag movement beyond the threshold', () => {
        const onLongPress = vi.fn()
        const onClick = vi.fn()
        const handlers = useLongPress(onLongPress, onClick)

        handlers.touchstart(
            makeTouchEvent('touchstart', [{ clientX: 10, clientY: 10 }], []),
        )
        handlers.touchend(
            makeTouchEvent('touchend', [], [{ clientX: 50, clientY: 40 }]),
        )

        expect(onClick).not.toHaveBeenCalled()
        expect(onLongPress).not.toHaveBeenCalled()
    })

    it('prevents default on touchend to suppress the synthetic mouse sequence', () => {
        const handlers = useLongPress(() => {}, () => {})
        const touchEnd = makeTouchEvent('touchend', [], [
            { clientX: 12, clientY: 11 },
        ])

        handlers.touchstart(
            makeTouchEvent('touchstart', [{ clientX: 10, clientY: 10 }], []),
        )
        handlers.touchend(touchEnd)

        expect(touchEnd.preventDefault).toHaveBeenCalled()
    })

    it('does not preventDefault when the touchend is not cancelable', () => {
        const handlers = useLongPress(() => {}, () => {})
        const touchEnd = makeTouchEvent(
            'touchend',
            [],
            [{ clientX: 12, clientY: 11 }],
            false,
        )

        handlers.touchstart(
            makeTouchEvent('touchstart', [{ clientX: 10, clientY: 10 }], []),
        )
        handlers.touchend(touchEnd)

        expect(touchEnd.preventDefault).not.toHaveBeenCalled()
    })

    it('ignores synthetic mouse events that follow a touch gesture', () => {
        const onLongPress = vi.fn()
        const onClick = vi.fn()
        const handlers = useLongPress(onLongPress, onClick)

        handlers.touchstart(
            makeTouchEvent('touchstart', [{ clientX: 10, clientY: 10 }], []),
        )
        handlers.touchend(
            makeTouchEvent('touchend', [], [{ clientX: 10, clientY: 10 }]),
        )
        expect(onClick).toHaveBeenCalledTimes(1)

        // Synthetic mousedown/mouseup landing right after the touch must be ignored
        handlers.mousedown({ type: 'mousedown', button: 0, clientX: 10, clientY: 10 })
        handlers.mouseup({ type: 'mouseup', clientX: 10, clientY: 10 })
        vi.advanceTimersByTime(700)

        expect(onLongPress).not.toHaveBeenCalled()
        expect(onClick).toHaveBeenCalledTimes(1)
    })

    it('still responds to real mouse input', () => {
        const onLongPress = vi.fn()
        const onClick = vi.fn()
        const handlers = useLongPress(onLongPress, onClick)

        handlers.mousedown({ type: 'mousedown', button: 0, clientX: 20, clientY: 20 })
        handlers.mouseup({ type: 'mouseup', clientX: 20, clientY: 20 })

        expect(onClick).toHaveBeenCalledTimes(1)
        expect(onLongPress).not.toHaveBeenCalled()
    })

    it('stops the long-press timer when the gesture is cancelled', () => {
        const onLongPress = vi.fn()
        const onClick = vi.fn()
        const handlers = useLongPress(onLongPress, onClick)

        handlers.touchstart(
            makeTouchEvent('touchstart', [{ clientX: 10, clientY: 10 }], []),
        )
        handlers.touchcancel({ type: 'touchcancel' })
        vi.advanceTimersByTime(700)

        expect(onLongPress).not.toHaveBeenCalled()
        expect(onClick).not.toHaveBeenCalled()
    })
})