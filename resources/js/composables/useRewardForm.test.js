import { describe, it, expect } from 'vitest'
import { nextTick, reactive } from 'vue'
import { useRewardForm } from './useRewardForm'

describe('useRewardForm', () => {
    it('decodes a monetary reward into money mode', () => {
        const form = reactive({ reward: '' })
        const { rewardMode, moneyAmount, textReward, decodeReward } =
            useRewardForm(form)

        decodeReward('$5.50')

        expect(rewardMode.value).toBe('money')
        expect(moneyAmount.value).toBe('5.50')
        expect(textReward.value).toBe('')
    })

    it('decodes a text reward into privilege mode', () => {
        const form = reactive({ reward: '' })
        const { rewardMode, moneyAmount, textReward, decodeReward } =
            useRewardForm(form)

        decodeReward('30 min screen time')

        expect(rewardMode.value).toBe('text')
        expect(textReward.value).toBe('30 min screen time')
        expect(moneyAmount.value).toBe('')
    })

    it('defaults to money mode when there is no reward', () => {
        const form = reactive({ reward: '' })
        const { rewardMode, decodeReward } = useRewardForm(form)

        decodeReward('')

        expect(rewardMode.value).toBe('money')
    })

    it('formats money mode edits back into the reward field', async () => {
        const form = reactive({ reward: '' })
        const { moneyAmount } = useRewardForm(form)

        moneyAmount.value = '10'
        await nextTick()

        expect(form.reward).toBe('$10.00')
    })

    it('toggles a bonus streak on and off', () => {
        const form = reactive({ reward: '', bonus_reward: null })
        const { toggleBonus } = useRewardForm(form)

        toggleBonus(true)
        expect(form.bonus_reward).toMatchObject({
            required_days: [0, 1, 2, 3, 4],
            expires_in_days: 3,
            requires_approval: true,
        })

        toggleBonus(false)
        expect(form.bonus_reward).toBeNull()
    })

    it('toggles individual bonus days and keeps them sorted', () => {
        const form = reactive({ reward: '', bonus_reward: null })
        const { toggleBonus, toggleBonusDay } = useRewardForm(form)

        toggleBonus(true)
        toggleBonusDay(6)
        expect(form.bonus_reward.required_days).toEqual([0, 1, 2, 3, 4, 6])

        toggleBonusDay(2)
        expect(form.bonus_reward.required_days).toEqual([0, 1, 3, 4, 6])
    })

    it('ignores bonus day toggles when no bonus is enabled', () => {
        const form = reactive({ reward: '', bonus_reward: null })
        const { toggleBonusDay } = useRewardForm(form)

        toggleBonusDay(3)

        expect(form.bonus_reward).toBeNull()
    })
})
