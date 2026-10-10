import { describe, it, expect } from 'vitest'
import {
    buildChorePayload,
    buildLabelPayload,
    choreToForm,
    blankChore,
    isMonetaryReward,
    ALL_DAYS,
} from './chorePayload'

describe('buildChorePayload', () => {
    it('normalizes a blank chore with defaults', () => {
        const payload = buildChorePayload(blankChore('Alex'))

        expect(payload.title).toBe('')
        expect(payload.profile).toBe('Alex')
        expect(payload.time).toBeNull()
        expect(payload.label_id).toBeNull()
        expect(payload.days).toEqual(ALL_DAYS)
        expect(payload.subtasks).toEqual([])
        expect(payload.bonus_reward).toBeNull()
    })

    it('trims subtask titles and drops empty ones', () => {
        const payload = buildChorePayload({
            ...blankChore('Emily'),
            title: '  Do homework  ',
            subtasks: [
                { title: ' Math ' },
                { title: '   ' },
                { id: 7, title: 'Reading' },
            ],
        })

        expect(payload.title).toBe('Do homework')
        expect(payload.subtasks).toEqual([
            { id: null, title: 'Math', order: 0 },
            { id: 7, title: 'Reading', order: 2 },
        ])
    })

    it('includes a bonus reward only when bankable with a value', () => {
        const withBonus = buildChorePayload({
            ...blankChore('Alex'),
            is_bankable: true,
            bonus_reward: {
                required_days: [0, 1, 2],
                reward_value: '$10',
                expires_in_days: 5,
                requires_approval: false,
            },
        })
        expect(withBonus.bonus_reward).toEqual({
            required_days: [0, 1, 2],
            reward_value: '$10',
            expires_in_days: 5,
            requires_approval: false,
        })

        const nonBankable = buildChorePayload({
            ...blankChore('Alex'),
            is_bankable: false,
            bonus_reward: { reward_value: '$10' },
        })
        expect(nonBankable.bonus_reward).toBeNull()
    })
})

describe('buildLabelPayload', () => {
    it('builds a label payload with a bonus', () => {
        const payload = buildLabelPayload({
            name: '  Morning  ',
            reward: 'Movie night',
            is_bankable: true,
            bonus_reward: {
                required_days: [1, 2, 3],
                reward_value: 'Ice cream',
                expires_in_days: null,
                requires_approval: true,
            },
        })

        expect(payload.name).toBe('Morning')
        expect(payload.reward).toBe('Movie night')
        expect(payload.bonus_reward).toEqual({
            required_days: [1, 2, 3],
            reward_value: 'Ice cream',
            expires_in_days: null,
            requires_approval: true,
        })
    })
})

describe('choreToForm', () => {
    it('maps an existing chore into editable form state', () => {
        const form = choreToForm({
            title: 'Make bed',
            profile: 'Henry',
            time: '07:30',
            days: [1, 2, 3, 4, 5],
            reward: '$2.00',
            is_bankable: true,
            label_id: 4,
            subtasks: [{ id: 9, title: 'Pull up covers' }],
            bonus_reward: {
                required_days: [0, 1],
                reward_value: '$5',
                expires_in_days: 3,
                requires_approval: true,
            },
        })

        expect(form).toMatchObject({
            title: 'Make bed',
            profile: 'Henry',
            time: '07:30',
            days: [1, 2, 3, 4, 5],
            reward: '$2.00',
            label_id: 4,
            subtasks: [{ id: 9, title: 'Pull up covers' }],
        })
        expect(form.bonus_reward.reward_value).toBe('$5')
    })
})

describe('isMonetaryReward', () => {
    it('detects monetary rewards', () => {
        expect(isMonetaryReward('$5.00')).toBe(true)
        expect(isMonetaryReward('30 min screen time')).toBe(false)
        expect(isMonetaryReward('')).toBe(false)
    })
})
