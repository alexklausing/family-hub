import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import axios from 'axios'
import CelebrationTab from './CelebrationTab.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}))

const createCelebration = (overrides = {}) => ({
    id: 1,
    message: 'Happy Birthday Henry!',
    background: 'confetti',
    font: 'display',
    font_color: '#ffffff',
    is_active: true,
    ...overrides,
})

const bodyText = () => document.body.textContent || ''

const findButtonByText = (text) => {
    return [...document.querySelectorAll('button')].find((b) => b.textContent.includes(text))
}

describe('CelebrationTab', () => {
    let wrapper

    beforeEach(() => {
        document.body.innerHTML = ''
        axios.get.mockReset()
    })

    afterEach(() => {
        vi.restoreAllMocks()
        if (wrapper) wrapper.unmount()
        document.body.innerHTML = ''
    })

    it('renders the active celebration message fullscreen', async () => {
        axios.get.mockResolvedValue({ data: [createCelebration()] })

        wrapper = mount(CelebrationTab)
        await new Promise((r) => setTimeout(r, 0))

        expect(wrapper.text()).toContain('Happy Birthday Henry!')
    })

    it('opens the create dialog from the empty state', async () => {
        axios.get.mockResolvedValue({ data: [] })

        wrapper = mount(CelebrationTab)
        await new Promise((r) => setTimeout(r, 0))

        const createButton = wrapper.findAll('button').find((b) => b.text().includes('Create a Celebration'))
        expect(createButton).toBeTruthy()
        await createButton.trigger('click')

        expect(bodyText()).toContain('New Celebration')
    })

    it('saves a new celebration via POST', async () => {
        axios.get.mockResolvedValue({ data: [] })
        axios.post.mockResolvedValue({ data: createCelebration() })

        wrapper = mount(CelebrationTab)
        await new Promise((r) => setTimeout(r, 0))

        const createButton = wrapper.findAll('button').find((b) => b.text().includes('Create a Celebration'))
        await createButton.trigger('click')

        const messageInput = document.querySelector('input[id="message"]')
        messageInput.value = 'Test Message'
        messageInput.dispatchEvent(new Event('input'))
        await new Promise((r) => setTimeout(r, 0))

        const saveButton = findButtonByText('Save')
        await saveButton.click()

        expect(axios.post).toHaveBeenCalledWith('/api/celebrations', expect.objectContaining({ message: 'Test Message' }))
    })

    it('opens the edit dialog from the manage panel', async () => {
        axios.get.mockResolvedValue({ data: [createCelebration()] })

        wrapper = mount(CelebrationTab)
        await new Promise((r) => setTimeout(r, 0))

        const gear = wrapper.findAll('button').find((b) => b.classes().includes('absolute'))
        await gear.trigger('click')
        await new Promise((r) => setTimeout(r, 0))

        const row = wrapper.find('.group')
        expect(row.exists()).toBe(true)

        const editBtn = row.findAll('button')[1]
        await editBtn.trigger('click')
        await new Promise((r) => setTimeout(r, 0))

        expect(bodyText()).toContain('Edit Celebration')
    })
})
