import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import OtherTab from './OtherTab.vue'

const findAppButton = (wrapper, name) => {
    return wrapper
        .findAll('button')
        .find((b) => b.findAll('span').some((s) => s.text() === name))
}

describe('OtherTab', () => {
    const mountTab = (props = {}) =>
        mount(OtherTab, {
            props: {
                workspaces: [],
                isEditing: false,
                isAddingToSlot: false,
                unusedApps: [],
                ...props,
            },
        })

    it('launches commute when tapped in the library', async () => {
        const wrapper = mountTab()

        await findAppButton(wrapper, 'Commute').trigger('click')

        expect(wrapper.emitted('launch')).toEqual([['commute']])
        expect(wrapper.emitted('create-workspace')).toBeUndefined()
    })

    it('never pins commute while editing', async () => {
        const wrapper = mountTab({ isEditing: true })

        await findAppButton(wrapper, 'Commute').trigger('click')

        expect(wrapper.emitted('launch')).toBeUndefined()
        expect(wrapper.emitted('create-workspace')).toBeUndefined()
        expect(wrapper.emitted('remove-workspace')).toBeUndefined()
    })

    it('never drops commute into a workspace slot while adding to a slot', async () => {
        const wrapper = mountTab({ isEditing: true, isAddingToSlot: true })

        await findAppButton(wrapper, 'Commute').trigger('click')

        expect(wrapper.emitted('launch')).toBeUndefined()
        expect(wrapper.emitted('create-workspace')).toBeUndefined()
    })

    it('creates a workspace for a pinnable app while editing', async () => {
        const wrapper = mountTab({ isEditing: true })

        await findAppButton(wrapper, 'Calendar').trigger('click')

        expect(wrapper.emitted('create-workspace')).toEqual([['family']])
    })
})