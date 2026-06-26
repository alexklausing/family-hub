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
            if (url === '/api/rewards/bank') {
                return Promise.resolve({
                    data: {
                        monetary_balance: 0,
                        textual_rewards: [],
                        history: [],
                    },
                })
            }
            if (url === '/api/chores/approvals') {
                return Promise.resolve({ data: [] })
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

    it('handles creating a new label with streak/bonus options', async () => {
        axios.post.mockResolvedValue({
            data: {
                id: 3,
                name: 'Bathroom',
                reward: '$2.50',
                is_bankable: true,
                bonus_reward: {
                    required_days: [0, 1],
                    reward_value: 'Free movie',
                    expires_in_days: 3,
                    requires_approval: true,
                },
            },
        })

        wrapper = mount(ChoresTab, {
            props: {
                profiles: [{ name: 'Alex', icon: 'User' }],
                activeProfile: 'Alex',
            },
        })

        await vi.runAllTimersAsync()

        // Trigger create
        wrapper.vm.startCreateLabel()
        expect(wrapper.vm.isLabelFormOpen).toBe(true)

        // Set inputs
        wrapper.vm.newLabelName = 'Bathroom'
        wrapper.vm.labelRewardType = 'monetary'
        wrapper.vm.labelMonetaryAmount = '2.50'
        wrapper.vm.labelHasBonus = true
        wrapper.vm.labelBonusValue = 'Free movie'
        wrapper.vm.labelBonusDays = [0, 1]

        // Allow watch on reward to tick
        await wrapper.vm.$nextTick()

        // Save
        await wrapper.vm.saveLabel()

        // Check axios call payload
        expect(axios.post).toHaveBeenCalledWith(
            '/api/labels',
            expect.objectContaining({
                name: 'Bathroom',
                reward: '$2.50',
                is_bankable: true,
                bonus_reward: {
                    required_days: [0, 1],
                    reward_value: 'Free movie',
                    expires_in_days: 3,
                    requires_approval: true,
                },
            }),
        )

        // Verifying label list updated and form closed
        expect(wrapper.vm.labels.length).toBe(3)
        expect(wrapper.vm.labels[2].name).toBe('Bathroom')
        expect(wrapper.vm.isLabelFormOpen).toBe(false)
    })

    it('handles editing an existing label and updating streak/bonus', async () => {
        axios.put.mockResolvedValue({
            data: {
                id: 1,
                name: 'Kitchen Remodeled',
                reward: 'Extra game time',
                is_bankable: true,
                bonus_reward: {
                    required_days: [0, 1, 2, 3, 4],
                    reward_value: 'Extra screen time',
                    expires_in_days: 3,
                    requires_approval: true,
                },
            },
        })

        wrapper = mount(ChoresTab, {
            props: {
                profiles: [{ name: 'Alex', icon: 'User' }],
                activeProfile: 'Alex',
            },
        })

        await vi.runAllTimersAsync()

        // Trigger edit on label 1 (Kitchen)
        wrapper.vm.startEditLabel(wrapper.vm.labels[0])
        expect(wrapper.vm.isLabelFormOpen).toBe(true)
        expect(wrapper.vm.newLabelName).toBe('Kitchen')

        // Set edits
        wrapper.vm.newLabelName = 'Kitchen Remodeled'
        wrapper.vm.labelHasBonus = true
        wrapper.vm.labelBonusValue = 'Extra screen time'

        await wrapper.vm.$nextTick()

        // Save
        await wrapper.vm.saveLabel()

        // Check axios put call payload
        expect(axios.put).toHaveBeenCalledWith(
            '/api/labels/1',
            expect.objectContaining({
                name: 'Kitchen Remodeled',
                reward: 'Extra game time',
                bonus_reward: {
                    required_days: [0, 1, 2, 3, 4],
                    reward_value: 'Extra screen time',
                    expires_in_days: 3,
                    requires_approval: true,
                },
            }),
        )

        // Check label list updated in place
        expect(wrapper.vm.labels[0].name).toBe('Kitchen Remodeled')
        expect(wrapper.vm.isLabelFormOpen).toBe(false)
    })

    it('auto-saves form content on modal close when dirty and valid', async () => {
        axios.post.mockResolvedValue({
            data: {
                id: 4,
                name: 'Garden',
                reward: null,
                is_bankable: true,
                bonus_reward: null,
            },
        })

        wrapper = mount(ChoresTab, {
            props: {
                profiles: [{ name: 'Alex', icon: 'User' }],
                activeProfile: 'Alex',
            },
        })

        await vi.runAllTimersAsync()

        // Start creating
        wrapper.vm.startCreateLabel()
        wrapper.vm.newLabelName = 'Garden'

        await wrapper.vm.$nextTick()

        // Simulate closing the modal (e.g. user clicked outside or Done without clicking Save)
        wrapper.vm.isLabelFormOpen = false

        // Wait for watcher to trigger saveLabel
        await wrapper.vm.$nextTick()
        await vi.runAllTimersAsync()

        // Check that post was triggered automatically
        expect(axios.post).toHaveBeenCalledWith(
            '/api/labels',
            expect.objectContaining({
                name: 'Garden',
            }),
        )
    })

    it('controls the custom reward accordion and clear button in chore modal', async () => {
        wrapper = mount(ChoresTab, {
            props: {
                profiles: [{ name: 'Alex', icon: 'User' }],
                activeProfile: 'Alex',
            },
        })

        await vi.runAllTimersAsync()

        // 1. Open add modal (no reward) -> accordion should not be expanded
        wrapper.vm.openAddModal()
        expect(wrapper.vm.isRewardExpanded).toBe(false)

        // 2. Open edit modal with a chore having no reward -> accordion should not be expanded
        const choreNoReward = { ...mockChores[0], reward: '' }
        wrapper.vm.openEditModal(choreNoReward)
        expect(wrapper.vm.isRewardExpanded).toBe(false)

        // 3. Open edit modal with a chore having a custom reward -> accordion should be expanded
        const choreWithReward = { ...mockChores[0], reward: '$2.00' }
        wrapper.vm.openEditModal(choreWithReward)
        expect(wrapper.vm.isRewardExpanded).toBe(true)

        // 4. Test clearReward method
        wrapper.vm.monetaryAmount = '2.00'
        wrapper.vm.textRewardValue = 'Free movie'
        wrapper.vm.clearReward()
        expect(wrapper.vm.monetaryAmount).toBe('')
        expect(wrapper.vm.textRewardValue).toBe('')
    })

    it('only shows streak/bonus reward options if bankable is enabled, and clears streak options on disable', async () => {
        wrapper = mount(ChoresTab, {
            props: {
                profiles: [{ name: 'Alex', icon: 'User' }],
                activeProfile: 'Alex',
            },
        })

        await vi.runAllTimersAsync()

        // Test Chore Modal Bankable watch
        wrapper.vm.openAddModal()
        wrapper.vm.newChore.is_bankable = true
        wrapper.vm.choreHasBonus = true
        wrapper.vm.choreBonusValue = 'Movie ticket'

        await wrapper.vm.$nextTick()
        expect(wrapper.vm.choreHasBonus).toBe(true)

        // Toggle bankable off -> should reset chore streak fields
        wrapper.vm.newChore.is_bankable = false
        await wrapper.vm.$nextTick()

        expect(wrapper.vm.choreHasBonus).toBe(false)
        expect(wrapper.vm.choreBonusValue).toBe('')

        // Test Label Modal Bankable watch
        wrapper.vm.startCreateLabel()
        wrapper.vm.newLabelIsBankable = true
        wrapper.vm.labelHasBonus = true
        wrapper.vm.labelBonusValue = 'Popcorn'

        await wrapper.vm.$nextTick()
        expect(wrapper.vm.labelHasBonus).toBe(true)

        // Toggle bankable off -> should reset label streak fields
        wrapper.vm.newLabelIsBankable = false
        await wrapper.vm.$nextTick()

        expect(wrapper.vm.labelHasBonus).toBe(false)
        expect(wrapper.vm.labelBonusValue).toBe('')
    })

    it('hides custom rewards and auto-clears them if a label with a reward is selected', async () => {
        wrapper = mount(ChoresTab, {
            props: {
                profiles: [{ name: 'Alex', icon: 'User' }],
                activeProfile: 'Alex',
            },
        })

        await vi.runAllTimersAsync()

        // 1. Open Add Modal, set some custom rewards
        wrapper.vm.openAddModal()
        wrapper.vm.monetaryAmount = '5.00'
        wrapper.vm.newChore.is_bankable = true
        wrapper.vm.choreHasBonus = true
        wrapper.vm.choreBonusValue = 'Free pizza'

        await wrapper.vm.$nextTick()
        expect(wrapper.vm.newChore.reward).toBe('$5.00')

        // 2. Select a label that has a reward attached (label 1: Kitchen has 'Extra game time')
        wrapper.vm.newChore.label_id = 1
        await wrapper.vm.$nextTick()

        // The computed property selectedLabelHasReward should be true
        expect(wrapper.vm.selectedLabelHasReward).toBe(true)

        // Custom rewards and streak configuration should be auto-cleared/reset
        expect(wrapper.vm.newChore.reward).toBe('')
        expect(wrapper.vm.monetaryAmount).toBe('')
        expect(wrapper.vm.choreHasBonus).toBe(false)
        expect(wrapper.vm.choreBonusValue).toBe('')

        // 3. Select No Label -> selectedLabelHasReward becomes false
        wrapper.vm.newChore.label_id = null
        await wrapper.vm.$nextTick()
        expect(wrapper.vm.selectedLabelHasReward).toBe(false)
    })

    it('automatically banks monetary rewards in chore and label forms', async () => {
        wrapper = mount(ChoresTab, {
            props: {
                profiles: [{ name: 'Alex', icon: 'User' }],
                activeProfile: 'Alex',
            },
        })

        await vi.runAllTimersAsync()

        // 1. Chore Modal: Set type to text, is_bankable to false
        wrapper.vm.openAddModal()
        wrapper.vm.rewardType = 'text'
        wrapper.vm.textRewardValue = 'Stay up late'
        wrapper.vm.newChore.is_bankable = false
        await wrapper.vm.$nextTick()
        expect(wrapper.vm.newChore.is_bankable).toBe(false)

        // Switch to monetary -> should automatically force is_bankable to true
        wrapper.vm.rewardType = 'monetary'
        wrapper.vm.monetaryAmount = '1.50'
        await wrapper.vm.$nextTick()
        expect(wrapper.vm.newChore.is_bankable).toBe(true)

        // 2. Label Modal: Set type to text, is_bankable to false
        wrapper.vm.startCreateLabel()
        wrapper.vm.labelRewardType = 'text'
        wrapper.vm.labelTextReward = 'Extra screen time'
        wrapper.vm.newLabelIsBankable = false
        await wrapper.vm.$nextTick()
        expect(wrapper.vm.newLabelIsBankable).toBe(false)

        // Switch to monetary -> should automatically force is_bankable to true
        wrapper.vm.labelRewardType = 'monetary'
        wrapper.vm.labelMonetaryAmount = '2.00'
        await wrapper.vm.$nextTick()
        expect(wrapper.vm.newLabelIsBankable).toBe(true)
    })

    it('correctly decodes 24h string values to 12h select elements, and updates the chore time payload', async () => {
        wrapper = mount(ChoresTab, {
            props: {
                profiles: [{ name: 'Alex', icon: 'User' }],
                activeProfile: 'Alex',
            },
        })

        await vi.runAllTimersAsync()

        // 1. Add modal starts with empty/null time
        wrapper.vm.openAddModal()
        expect(wrapper.vm.choreTimeHour).toBeNull()
        expect(wrapper.vm.newChore.time).toBe('')

        // Update elements to 8:30 AM
        wrapper.vm.choreTimeHour = 8
        wrapper.vm.choreTimeMinute = '30'
        wrapper.vm.choreTimePeriod = 'AM'
        await wrapper.vm.$nextTick()
        expect(wrapper.vm.newChore.time).toBe('08:30')

        // Update elements to 4:45 PM
        wrapper.vm.choreTimeHour = 4
        wrapper.vm.choreTimeMinute = '45'
        wrapper.vm.choreTimePeriod = 'PM'
        await wrapper.vm.$nextTick()
        expect(wrapper.vm.newChore.time).toBe('16:45')

        // Update elements to 12:00 PM (noon)
        wrapper.vm.choreTimeHour = 12
        wrapper.vm.choreTimeMinute = '00'
        wrapper.vm.choreTimePeriod = 'PM'
        await wrapper.vm.$nextTick()
        expect(wrapper.vm.newChore.time).toBe('12:00')

        // Update elements to 12:15 AM (midnight period)
        wrapper.vm.choreTimeHour = 12
        wrapper.vm.choreTimeMinute = '15'
        wrapper.vm.choreTimePeriod = 'AM'
        await wrapper.vm.$nextTick()
        expect(wrapper.vm.newChore.time).toBe('00:15')

        // Clear time limit
        wrapper.vm.choreTimeHour = null
        await wrapper.vm.$nextTick()
        expect(wrapper.vm.newChore.time).toBe('')

        // 2. Edit modal decodes 24h string to 12h elements (14:35 -> 2:35 PM)
        const mockChore = {
            title: 'Clean room',
            profile: 'Alex',
            time: '14:35',
            days: [0, 1],
            reward: '',
            is_bankable: true,
            label_id: null,
        }
        wrapper.vm.openEditModal(mockChore)
        expect(wrapper.vm.choreTimeHour).toBe(2)
        expect(wrapper.vm.choreTimeMinute).toBe('35')
        expect(wrapper.vm.choreTimePeriod).toBe('PM')
    })
})
