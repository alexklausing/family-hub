import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { useSleepException } from './useSleepException'

describe('useSleepException', () => {
    let store

    beforeEach(() => {
        store = useSleepException()
        for (const key of [...store.activeExceptions]) {
            store.disableException(key)
        }
    })

    afterEach(() => {
        for (const key of [...store.activeExceptions]) {
            store.disableException(key)
        }
    })

    it('starts with no active exceptions', () => {
        expect(store.hasSleepException.value).toBe(false)
        expect(store.activeExceptions.size).toBe(0)
    })

    it('reports an exception when a feature registers', () => {
        store.enableException('cooking')
        expect(store.hasSleepException.value).toBe(true)
        expect(store.activeExceptions.has('cooking')).toBe(true)
    })

    it('clears the exception when a feature unregisters', () => {
        store.enableException('celebration')
        expect(store.hasSleepException.value).toBe(true)

        store.disableException('celebration')
        expect(store.hasSleepException.value).toBe(false)
        expect(store.activeExceptions.has('celebration')).toBe(false)
    })

    it('tracks multiple simultaneous exceptions', () => {
        store.enableException('cooking')
        store.enableException('celebration')
        expect(store.activeExceptions.size).toBe(2)

        store.disableException('cooking')
        expect(store.hasSleepException.value).toBe(true)

        store.disableException('celebration')
        expect(store.hasSleepException.value).toBe(false)
    })
})
