import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import WordOfTheDayWidget from './WordOfTheDayWidget.vue'

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

describe('WordOfTheDayWidget', () => {
    let audioMock
    let wrapper

    beforeEach(() => {
        document.body.innerHTML = ''
        Object.defineProperty(window, 'localStorage', {
            configurable: true,
            value: createStorageMock(),
        })

        audioMock = {
            play: vi.fn().mockResolvedValue(undefined),
            referrerPolicy: '',
        }

        global.Audio = vi.fn().mockImplementation(function () {
            return audioMock
        })

        // Simulate the production environment (Chromium Snap) where
        // speech-dispatcher is blocked so speechSynthesis is unavailable.
        Object.defineProperty(window, 'speechSynthesis', {
            configurable: true,
            value: undefined,
        })

        wrapper = mount(WordOfTheDayWidget)
    })

    afterEach(() => {
        vi.restoreAllMocks()
        wrapper.unmount()
    })

    it('sets no-referrer on the cloud TTS audio so Google does not return 404', async () => {
        const englishWord = wrapper.find('h2')
        expect(englishWord.exists()).toBe(true)

        await englishWord.trigger('click')

        expect(global.Audio).toHaveBeenCalledTimes(1)
        const calledUrl = global.Audio.mock.calls[0][0]
        expect(calledUrl).toContain('https://translate.google.com/translate_tts')
        expect(calledUrl).toContain('client=tw-ob')

        expect(audioMock.referrerPolicy).toBe('no-referrer')
        expect(audioMock.play).toHaveBeenCalledTimes(1)
    })

    it('reorders languages in the settings dialog', async () => {
        await wrapper.find('button').trigger('click')
        await new Promise((r) => setTimeout(r, 0))

        expect(document.body.textContent).toContain('Language Settings')

        const rows = document.body.querySelectorAll('.language-row')
        expect(rows.length).toBe(5)

        const stored = JSON.parse(localStorage.getItem('word-of-day-language-order'))
        expect(stored[0]).toBe('fr')

        const reordered = [...stored]
        reordered.splice(0, 0, reordered.pop())
        localStorage.setItem('word-of-day-language-order', JSON.stringify(reordered))
        await wrapper.vm.$nextTick()

        const updated = JSON.parse(localStorage.getItem('word-of-day-language-order'))
        expect(updated[0]).toBe('lb')
    })
})
