import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import LunchMenu from './LunchMenu.vue'
import axios from 'axios'

vi.mock('axios')

const formatYMD = (date) => {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
}

// Build the sample week relative to today's Monday so the test
// stays valid regardless of when it runs.
const start = new Date()
start.setHours(0, 0, 0, 0)
start.setDate(start.getDate() - ((start.getDay() + 6) % 7))
const weekStart = formatYMD(start)
const dayAt = (offset) => {
    const d = new Date(start)
    d.setDate(start.getDate() + offset)
    return formatYMD(d)
}

const sampleWeek = {
    school: 'Lakeland Montessori',
    start_date: weekStart,
    days: [
        { date: dayAt(0), has_school: false, sections: [] },
        {
            date: dayAt(1),
            sections: [
                { name: 'Hot Meal:', items: ['Chicken and Waffles E'] },
                { name: 'Fruit of the Day:', items: ['Red Apple Slices'] },
            ],
        },
        { date: dayAt(2), sections: [] },
        { date: dayAt(3), sections: [] },
        { date: dayAt(4), sections: [] },
    ],
}

describe('LunchMenu', () => {
    let wrapper

    beforeEach(() => {
        document.body.innerHTML = ''
        axios.get.mockReset()
        axios.get.mockResolvedValue({ data: sampleWeek })
    })

    afterEach(() => {
        if (wrapper) wrapper.unmount()
    })

    it('fetches the lunch menu for the current week on mount', async () => {
        wrapper = mount(LunchMenu)
        await new Promise((r) => setTimeout(r, 0))

        expect(axios.get).toHaveBeenCalledWith('/api/lunch-menu', {
            params: { date: expect.any(String) },
        })
        expect(wrapper.text()).toContain('Lakeland Montessori')
    })

    it('renders each school day in the week', async () => {
        wrapper = mount(LunchMenu)
        await new Promise((r) => setTimeout(r, 0))

        // 5 columns rendered
        expect(
            wrapper.findAll('button, [class*="rounded-3xl"]').length,
        ).toBeGreaterThan(0)
        expect(wrapper.text()).toContain('Mon')
        expect(wrapper.text()).toContain('Fri')
        expect(wrapper.text()).toContain('No School')
        expect(wrapper.text()).toContain('Hot Meal')
        expect(wrapper.text()).toContain('Chicken and Waffles E')
    })

    it('navigates weeks with the prev/next buttons', async () => {
        wrapper = mount(LunchMenu)
        await new Promise((r) => setTimeout(r, 0))

        await wrapper.setData({ weekOffset: 0 })
        axios.get.mockClear()

        const buttons = wrapper.findAll('button')
        expect(buttons.length).toBe(2)

        // Click "next week" (right chevron button)
        await buttons[1].trigger('click')
        expect(wrapper.vm.weekOffset).toBe(1)
        expect(axios.get).toHaveBeenCalledTimes(1)

        // Click "previous week" (left chevron button)
        await buttons[0].trigger('click')
        expect(wrapper.vm.weekOffset).toBe(0)
        expect(axios.get).toHaveBeenCalledTimes(2)
    })

    it('shows a loading spinner while fetching', async () => {
        let resolveFn
        axios.get.mockImplementation(
            () => new Promise((res) => (resolveFn = res)),
        )
        wrapper = mount(LunchMenu)
        await wrapper.vm.$nextTick()

        expect(wrapper.find('[class*="animate-spin"]').exists()).toBe(true)

        resolveFn({ data: sampleWeek })
        await new Promise((r) => setTimeout(r, 0))
        expect(wrapper.find('[class*="animate-spin"]').exists()).toBe(false)
    })

    it('shows an error message when the fetch fails', async () => {
        axios.get.mockRejectedValue(new Error('boom'))
        wrapper = mount(LunchMenu)
        await new Promise((r) => setTimeout(r, 0))

        expect(wrapper.text()).toContain("Couldn't load the menu right now.")
    })
})
