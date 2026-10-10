export const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
export const DAY_SHORT = ['S', 'M', 'T', 'W', 'T', 'F', 'S']
export const ALL_DAYS = [0, 1, 2, 3, 4, 5, 6]

export const REWARD_PRESETS = [
    '30 min screen time',
    '1 hr screen time',
    "Pick tonight's movie",
    'Stay up 30 min late',
    'Pick dessert',
    'Skip a chore tomorrow',
    'Choose family dinner',
    'Extra game time',
]

function normalizeBonus(form) {
    const bonus = form?.bonus_reward
    if (form?.is_bankable === false || !bonus || !bonus.reward_value) {
        return null
    }

    return {
        required_days: bonus.required_days ?? [],
        reward_value: bonus.reward_value,
        expires_in_days: bonus.expires_in_days ?? null,
        requires_approval: bonus.requires_approval ?? true,
    }
}

function normalizeSubtasks(subtasks) {
    return (subtasks || [])
        .map((subtask, index) => ({
            id: subtask.id || null,
            title: (subtask.title || '').trim(),
            order: index,
        }))
        .filter((subtask) => subtask.title)
}

export function buildChorePayload(form) {
    return {
        title: (form.title || '').trim(),
        profile: form.profile,
        time: form.time || null,
        available_from: form.available_from || null,
        days: form.days ?? [...ALL_DAYS],
        reward: form.reward || null,
        is_bankable: form.is_bankable ?? true,
        label_id: form.label_id || null,
        subtasks: normalizeSubtasks(form.subtasks),
        bonus_reward: normalizeBonus(form),
    }
}

export function buildLabelPayload(form) {
    return {
        name: (form.name || '').trim(),
        available_from: form.available_from || null,
        reward: form.reward || null,
        is_bankable: form.is_bankable ?? true,
        bonus_reward: normalizeBonus(form),
    }
}

export function blankChore(profile) {
    return {
        title: '',
        profile,
        time: '',
        available_from: '',
        days: [...ALL_DAYS],
        reward: '',
        is_bankable: true,
        label_id: null,
        subtasks: [],
        bonus_reward: null,
    }
}

export function choreToForm(chore) {
    return {
        title: chore.title || '',
        profile: chore.profile,
        time: chore.time || '',
        available_from: chore.available_from || '',
        days: chore.days ? [...chore.days] : [...ALL_DAYS],
        reward: chore.reward || '',
        is_bankable: chore.is_bankable ?? true,
        label_id: chore.label_id || null,
        subtasks: (chore.subtasks || []).map((subtask) => ({
            id: subtask.id,
            title: subtask.title,
        })),
        bonus_reward: chore.bonus_reward
            ? {
                  required_days: chore.bonus_reward.required_days || [],
                  reward_value: chore.bonus_reward.reward_value || '',
                  expires_in_days: chore.bonus_reward.expires_in_days ?? 3,
                  requires_approval: chore.bonus_reward.requires_approval ?? true,
              }
            : null,
    }
}

export function blankLabel() {
    return {
        name: '',
        available_from: '',
        reward: '',
        is_bankable: true,
        bonus_reward: null,
    }
}

export function labelToForm(label) {
    return {
        name: label.name || '',
        available_from: label.available_from || '',
        reward: label.reward || '',
        is_bankable: label.is_bankable ?? true,
        bonus_reward: label.bonus_reward
            ? {
                  required_days: label.bonus_reward.required_days || [],
                  reward_value: label.bonus_reward.reward_value || '',
                  expires_in_days: label.bonus_reward.expires_in_days ?? 3,
                  requires_approval: label.bonus_reward.requires_approval ?? true,
              }
            : null,
    }
}

export function isMonetaryReward(reward) {
    return /^\$[\d.]+$/.test((reward || '').trim())
}

export function todayIso() {
    return new Date().toLocaleDateString('en-CA')
}
