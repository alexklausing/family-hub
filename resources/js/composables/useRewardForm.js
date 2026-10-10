import { ref, watch } from 'vue'
import { isMonetaryReward } from '@/lib/chorePayload'

/**
 * Shared reward + bonus-streak form logic used by both the chore and label
 * editors. Keeps the two forms from drifting apart.
 */
export function useRewardForm(form) {
    const rewardMode = ref('money')
    const moneyAmount = ref('')
    const textReward = ref('')

    function decodeReward(reward) {
        if (isMonetaryReward(reward)) {
            rewardMode.value = 'money'
            moneyAmount.value = reward.replace('$', '')
            textReward.value = ''
        } else {
            rewardMode.value = reward ? 'text' : 'money'
            textReward.value = reward || ''
            moneyAmount.value = ''
        }
    }

    watch([rewardMode, moneyAmount, textReward], () => {
        if (rewardMode.value === 'money') {
            const amount = parseFloat(moneyAmount.value)
            form.reward =
                moneyAmount.value && !isNaN(amount) ? `$${amount.toFixed(2)}` : ''
        } else {
            form.reward = textReward.value.trim()
        }
    })

    function toggleBonus(enabled) {
        form.bonus_reward = enabled
            ? {
                  required_days: [0, 1, 2, 3, 4],
                  reward_value: '',
                  expires_in_days: 3,
                  requires_approval: true,
              }
            : null
    }

    function toggleBonusDay(day) {
        if (!form.bonus_reward) return
        const days = new Set(form.bonus_reward.required_days || [])
        if (days.has(day)) days.delete(day)
        else days.add(day)
        form.bonus_reward.required_days = [...days].sort((a, b) => a - b)
    }

    return {
        rewardMode,
        moneyAmount,
        textReward,
        decodeReward,
        toggleBonus,
        toggleBonusDay,
    }
}
