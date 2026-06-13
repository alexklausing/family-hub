import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Button from '@/components/ui/button/Button.vue'

describe('Button', () => {
    it('renders correctly', () => {
        const wrapper = mount(Button, {
            slots: {
                default: 'Click me',
            },
        })
        expect(wrapper.text()).toContain('Click me')
    })

    it('applies variant classes', () => {
        const wrapper = mount(Button, {
            props: {
                variant: 'destructive',
            },
        })
        expect(wrapper.attributes('class')).toContain('bg-destructive')
    })
})
