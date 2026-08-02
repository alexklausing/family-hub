import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import RecipeBrowser from './RecipeBrowser.vue'
import axios from 'axios'
import { useSleepException } from '@/composables/useSleepException'

vi.mock('axios')

const createStorageMock = () => {
    const store = new Map()
    return {
        getItem: vi.fn((k) => (store.has(k) ? store.get(k) : null)),
        setItem: vi.fn((k, v) => store.set(k, String(v))),
        removeItem: vi.fn((k) => store.delete(k)),
        clear: vi.fn(() => store.clear()),
        key: vi.fn((i) => [...store.keys()][i] ?? null),
        get length() {
            return store.size
        },
    }
}

describe('RecipeBrowser cooking mode', () => {
    let store
    let wrapper

    beforeEach(() => {
        document.body.innerHTML = ''
        Object.defineProperty(window, 'localStorage', {
            configurable: true,
            value: createStorageMock(),
        })
        axios.get.mockReset()
        axios.get.mockResolvedValue({ data: { data: [], current_page: 1, last_page: 1 } })

        store = useSleepException()
        for (const key of [...store.activeExceptions]) {
            store.disableException(key)
        }
    })

    afterEach(() => {
        for (const key of [...store.activeExceptions]) {
            store.disableException(key)
        }
        if (wrapper) wrapper.unmount()
    })

    it('registers the cooking sleep exception while cooking', async () => {
        wrapper = mount(RecipeBrowser)
        await new Promise((r) => setTimeout(r, 0))

        expect(store.activeExceptions.has('cooking')).toBe(false)

        wrapper.vm.isCookingMode = true
        await wrapper.vm.$nextTick()
        expect(store.activeExceptions.has('cooking')).toBe(true)

        wrapper.vm.isCookingMode = false
        await wrapper.vm.$nextTick()
        expect(store.activeExceptions.has('cooking')).toBe(false)
    })

    it('cleans up the cooking exception on unmount', async () => {
        wrapper = mount(RecipeBrowser)
        await new Promise((r) => setTimeout(r, 0))

        wrapper.vm.isCookingMode = true
        await wrapper.vm.$nextTick()
        expect(store.activeExceptions.has('cooking')).toBe(true)

        wrapper.unmount()
        expect(store.activeExceptions.has('cooking')).toBe(false)
    })
})
