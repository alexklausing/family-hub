import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import EmojiPicker from './EmojiPicker.vue'

describe('EmojiPicker', () => {
    let wrapper

    beforeEach(() => {
        document.body.innerHTML = ''
    })

    afterEach(() => {
        wrapper?.unmount()
        document.body.innerHTML = ''
    })

    it('does not offer the poop emoji', async () => {
        wrapper = mount(EmojiPicker)
        await wrapper.find('button').trigger('click')
        await new Promise((r) => setTimeout(r, 0))

        const pickerText = document.body.textContent || ''
        expect(pickerText).not.toContain('💩')
        expect(pickerText).toContain('🌟')
    })

    it('emits the selected emoji', async () => {
        wrapper = mount(EmojiPicker, { props: { modelValue: '🌙' } })
        await wrapper.find('button').trigger('click')
        await new Promise((r) => setTimeout(r, 0))

        const emojiBtn = [...document.querySelectorAll('button')].find(
            (b) => b.textContent === '🎉',
        )
        expect(emojiBtn).toBeTruthy()
        emojiBtn.click()
        await new Promise((r) => setTimeout(r, 0))

        expect(wrapper.emitted('select')[0]).toEqual([{ i: '🎉' }])
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['🎉'])
    })

    it('shows the current value on the trigger', () => {
        wrapper = mount(EmojiPicker, { props: { modelValue: '🎂' } })

        expect(wrapper.find('button').text()).toContain('🎂')
    })
})
