import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import ChoresTab from './ChoresTab.vue'
import axios from 'axios'
import confetti from 'canvas-confetti'

vi.mock('axios')
vi.mock('canvas-confetti', () => ({
    default: vi.fn(),
}))

describe('ChoresTab', () => {
    let mockChores
    let mockLabels
    let wrapper

    beforeEach(() => {
        vi.useFakeTimers()
        global.requestAnimationFrame = vi.fn((cb) => setTimeout(cb, 16))

        // Clean up document body from teleports
        document.body.innerHTML = ''

        // Mock data
        mockLabels = [
            { id: 1, name: 'Kitchen', reward: 'Extra game time' },
            { id: 2, name: 'Bedroom', reward: '$5' },
        ]

        mockChores = [
            {
                id: 1,
                title: 'Wash dishes',
                profile: 'Alex',
                completed: false,
                label_id: 1,
                label: mockLabels[0],
                days: [0, 1, 2, 3, 4, 5, 6],
            },
            {
                id: 2,
                title: 'Sweep floor',
                profile: 'Alex',
                completed: false,
                label_id: 1,
                label: mockLabels[0],
                days: [0, 1, 2, 3, 4, 5, 6],
            },
            {
                id: 3,
                title: 'Make bed',
                profile: 'Alex',
                completed: false,
                label_id: 2,
                label: mockLabels[1],
                days: [0, 1, 2, 3, 4, 5, 6],
            },
        ]

        axios.get.mockImplementation((url) => {
            if (url === '/api/chores') {
                return Promise.resolve({ data: mockChores })
            }
            if (url === '/api/labels') {
                return Promise.resolve({ data: mockLabels })
            }
            return Promise.resolve({ data: [] })
        })

        axios.post.mockResolvedValue({ data: { completed: true } })
    })

    afterEach(() => {
        vi.useRealTimers()
        vi.clearAllMocks()
    })

    it('renders the tab and groups chores by label', async () => {
        wrapper = mount(ChoresTab, {
            props: {
                profiles: [{ name: 'Alex', icon: 'User' }],
                activeProfile: 'Alex',
            },
        })

        await vi.runAllTimersAsync()

        // Should render groups/columns
        expect(wrapper.text()).toContain('Kitchen')
        expect(wrapper.text()).toContain('Bedroom')
        expect(wrapper.text()).toContain('Wash dishes')
    })

    it('fires Level 1 celebration (single chore pop) when a single chore is completed but column/board are not', async () => {
        wrapper = mount(ChoresTab, {
            props: {
                profiles: [{ name: 'Alex', icon: 'User' }],
                activeProfile: 'Alex',
            },
        })

        await vi.runAllTimersAsync()

        // Complete chore 1 (Wash dishes) reactive proxy
        await wrapper.vm.toggleChore(wrapper.vm.chores[0])

        expect(axios.post).toHaveBeenCalledWith(
            '/api/chores/1/toggle',
            expect.any(Object),
        )

        // Fast forward timers for confetti
        await vi.advanceTimersByTimeAsync(100)

        // Confetti should have been fired once for Level 1 (25 particles pop)
        expect(confetti).toHaveBeenCalledTimes(1)
        expect(confetti).toHaveBeenCalledWith(
            expect.objectContaining({
                particleCount: 25,
                spread: 40,
            }),
        )
    })

    it('fires Level 2 celebration (dual side cannons) when completing all tasks in a column (card)', async () => {
        // To complete a column, we can start with Sweep floor already completed.
        mockChores[1].completed = true // Sweep floor completed.

        wrapper = mount(ChoresTab, {
            props: {
                profiles: [{ name: 'Alex', icon: 'User' }],
                activeProfile: 'Alex',
            },
        })

        await vi.runAllTimersAsync()

        // Complete Wash dishes
        await wrapper.vm.toggleChore(wrapper.vm.chores[0])

        // Fast-forward timers for Level 2 confetti (100ms delay in code)
        await vi.advanceTimersByTimeAsync(200)

        // Level 2 fires two confetti shots from sides (origin x: 0.15 and 0.85)
        expect(confetti).toHaveBeenCalledTimes(2)
        expect(confetti).toHaveBeenNthCalledWith(
            1,
            expect.objectContaining({
                origin: { x: 0.15, y: 0.8 },
            }),
        )
        expect(confetti).toHaveBeenNthCalledWith(
            2,
            expect.objectContaining({
                origin: { x: 0.85, y: 0.8 },
            }),
        )
    })

    it('fires Level 3 celebration (full board continuous) and shows overlay when completing all tasks on the board', async () => {
        // Complete sweep floor and make bed first
        mockChores[1].completed = true
        mockChores[2].completed = true

        wrapper = mount(ChoresTab, {
            props: {
                profiles: [{ name: 'Alex', icon: 'User' }],
                activeProfile: 'Alex',
            },
        })

        await vi.runAllTimersAsync()

        // Complete Wash dishes
        await wrapper.vm.toggleChore(wrapper.vm.chores[0])

        // Fast-forward timers for Level 3 confetti (150ms delay in code)
        await vi.advanceTimersByTimeAsync(200)

        // It should call confetti several times
        expect(confetti).toHaveBeenCalled()

        // The overlay element should be shown in document body (due to Teleport)
        expect(wrapper.vm.showBoardCelebration).toBe(true)
        expect(document.body.textContent).toContain('Great job, Alex!')
    })
})
