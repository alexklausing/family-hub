<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Plus, Trash2, X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { choreApi } from '@/lib/mobileApi'
import { useRewardForm } from '@/composables/useRewardForm'
import {
    ALL_DAYS,
    DAY_LABELS,
    REWARD_PRESETS,
    blankChore,
    buildChorePayload,
    choreToForm,
} from '@/lib/chorePayload'

const props = defineProps({
    open: { type: Boolean, default: false },
    chore: { type: Object, default: null },
    profiles: { type: Array, default: () => [] },
    labels: { type: Array, default: () => [] },
    initialProfile: { type: String, default: '' },
})

const emit = defineEmits(['update:open', 'saved', 'toast'])

const form = reactive(blankChore(props.initialProfile))
const { rewardMode, moneyAmount, textReward, decodeReward, toggleBonus, toggleBonusDay } =
    useRewardForm(form)
const saving = ref(false)
const error = ref('')

const isEditing = computed(() => !!props.chore)
const bonusEnabled = computed(() => !!form.bonus_reward)

watch(
    () => [props.open, props.chore],
    ([open]) => {
        if (!open) return
        error.value = ''
        const source = props.chore ? choreToForm(props.chore) : blankChore(props.initialProfile)
        Object.assign(form, source)
        if (props.chore) {
            form.profile = props.chore.profile
        }

        decodeReward(form.reward)
    },
    { immediate: true },
)

const dayActive = (day) => (form.days || []).includes(day)
function toggleDay(day) {
    const days = new Set(form.days || [])
    if (days.has(day)) days.delete(day)
    else days.add(day)
    form.days = [...days].sort((a, b) => a - b)
}

function addSubtask() {
    form.subtasks.push({ id: null, title: '' })
}
function removeSubtask(index) {
    form.subtasks.splice(index, 1)
}

async function save() {
    if (!form.title.trim()) {
        error.value = 'Give the chore a title.'
        return
    }
    saving.value = true
    error.value = ''
    try {
        const payload = buildChorePayload(form)
        if (isEditing.value) {
            await choreApi.update(props.chore.id, payload)
            emit('toast', 'Chore updated.')
        } else {
            await choreApi.create(payload)
            emit('toast', 'Chore added.')
        }
        emit('saved')
        emit('update:open', false)
    } catch (e) {
        error.value =
            e?.response?.data?.message || 'Could not save the chore. Try again.'
    } finally {
        saving.value = false
    }
}

const fieldClass =
    'h-12 w-full rounded-2xl border-0 bg-white/5 px-4 text-white focus:ring-2 focus:ring-sky-500'
const labelClass =
    'mb-1.5 block text-xs font-bold uppercase tracking-widest text-slate-400'
const sectionClass = 'rounded-3xl border border-white/10 bg-white/[0.03] p-4'
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogScrollContent
            class="max-w-lg border-white/10 bg-slate-950 text-slate-100"
        >
            <DialogHeader>
                <DialogTitle class="text-xl font-black">
                    {{ isEditing ? 'Edit chore' : 'New chore' }}
                </DialogTitle>
            </DialogHeader>

            <div class="space-y-4 pb-2">
                <div :class="sectionClass">
                    <label :class="labelClass" for="chore-title">Title</label>
                    <Input
                        id="chore-title"
                        v-model="form.title"
                        placeholder="e.g. Make your bed"
                        :class="fieldClass"
                    />
                </div>

                <div :class="sectionClass">
                    <label :class="labelClass" for="chore-profile">Assign to</label>
                    <select
                        id="chore-profile"
                        v-model="form.profile"
                        :class="fieldClass"
                    >
                        <option
                            v-for="profile in profiles"
                            :key="profile"
                            :value="profile"
                        >
                            {{ profile }}
                        </option>
                    </select>

                    <label :class="[labelClass, 'mt-4']">Days</label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="day in ALL_DAYS"
                            :key="day"
                            type="button"
                            class="h-10 min-w-[3rem] flex-1 rounded-xl text-sm font-bold transition"
                            :class="
                                dayActive(day)
                                    ? 'bg-sky-500 text-slate-950'
                                    : 'bg-white/5 text-slate-400'
                            "
                            @click="toggleDay(day)"
                        >
                            {{ DAY_LABELS[day] }}
                        </button>
                    </div>
                </div>

                <div :class="sectionClass">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label :class="labelClass" for="chore-time">Time</label>
                            <input
                                id="chore-time"
                                v-model="form.time"
                                type="time"
                                :class="fieldClass"
                            />
                        </div>
                        <div>
                            <label :class="labelClass" for="chore-available">
                                Available from
                            </label>
                            <input
                                id="chore-available"
                                v-model="form.available_from"
                                type="time"
                                :class="fieldClass"
                            />
                        </div>
                    </div>
                </div>

                <div :class="sectionClass">
                    <label :class="labelClass" for="chore-label">Group label</label>
                    <select
                        id="chore-label"
                        v-model="form.label_id"
                        :class="fieldClass"
                    >
                        <option :value="null">No label</option>
                        <option
                            v-for="label in labels"
                            :key="label.id"
                            :value="label.id"
                        >
                            {{ label.name }}
                        </option>
                    </select>
                </div>

                <div :class="sectionClass">
                    <label :class="labelClass">Reward</label>
                    <div class="mb-3 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="h-10 rounded-xl text-sm font-bold"
                            :class="
                                rewardMode === 'money'
                                    ? 'bg-sky-500 text-slate-950'
                                    : 'bg-white/5 text-slate-400'
                            "
                            @click="rewardMode = 'money'"
                        >
                            Money
                        </button>
                        <button
                            type="button"
                            class="h-10 rounded-xl text-sm font-bold"
                            :class="
                                rewardMode === 'text'
                                    ? 'bg-sky-500 text-slate-950'
                                    : 'bg-white/5 text-slate-400'
                            "
                            @click="rewardMode = 'text'"
                        >
                            Privilege
                        </button>
                    </div>

                    <div v-if="rewardMode === 'money'" class="flex items-center gap-2">
                        <span class="text-xl font-black text-slate-400">$</span>
                        <Input
                            v-model="moneyAmount"
                            inputmode="decimal"
                            placeholder="5.00"
                            :class="fieldClass"
                        />
                    </div>
                    <div v-else class="space-y-2">
                        <Input
                            v-model="textReward"
                            placeholder="e.g. 30 min screen time"
                            :class="fieldClass"
                        />
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="preset in REWARD_PRESETS"
                                :key="preset"
                                type="button"
                                class="rounded-full bg-white/5 px-3 py-1 text-xs font-semibold text-slate-300"
                                @click="textReward = preset"
                            >
                                {{ preset }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold">Bankable</p>
                            <p class="text-xs text-slate-400">
                                Keep reward across days until spent
                            </p>
                        </div>
                        <Switch
                            :checked="form.is_bankable"
                            @update:checked="(v) => (form.is_bankable = v)"
                        />
                    </div>
                </div>

                <div :class="sectionClass">
                    <div class="mb-3 flex items-center justify-between">
                        <label :class="labelClass">Bonus streak reward</label>
                        <Switch
                            :checked="bonusEnabled"
                            @update:checked="toggleBonus"
                        />
                    </div>
                    <div v-if="bonusEnabled && form.bonus_reward" class="space-y-3">
                        <div>
                            <label :class="labelClass">Required days</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="day in ALL_DAYS"
                                    :key="day"
                                    type="button"
                                    class="h-9 min-w-[2.5rem] flex-1 rounded-lg text-xs font-bold"
                                    :class="
                                        form.bonus_reward.required_days.includes(day)
                                            ? 'bg-amber-400 text-slate-950'
                                            : 'bg-white/5 text-slate-400'
                                    "
                                    @click="toggleBonusDay(day)"
                                >
                                    {{ DAY_LABELS[day] }}
                                </button>
                            </div>
                        </div>
                        <Input
                            v-model="form.bonus_reward.reward_value"
                            placeholder="Bonus reward e.g. $10"
                            :class="fieldClass"
                        />
                        <div class="flex items-center gap-3">
                            <div class="flex-1">
                                <label :class="labelClass">Expires in (days)</label>
                                <Input
                                    v-model.number="form.bonus_reward.expires_in_days"
                                    inputmode="numeric"
                                    :class="fieldClass"
                                />
                            </div>
                            <div class="flex items-center gap-2 pt-5">
                                <Switch
                                    :checked="form.bonus_reward.requires_approval"
                                    @update:checked="
                                        (v) =>
                                            (form.bonus_reward.requires_approval = v)
                                    "
                                />
                                <span class="text-xs font-semibold text-slate-400">
                                    Needs approval
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div :class="sectionClass">
                    <div class="mb-3 flex items-center justify-between">
                        <label :class="labelClass">Checklist subtasks</label>
                        <Button
                            type="button"
                            variant="ghost"
                            class="h-8 rounded-full px-3 text-xs text-sky-400"
                            @click="addSubtask"
                        >
                            <Plus class="mr-1 h-3.5 w-3.5" /> Add
                        </Button>
                    </div>
                    <div v-if="form.subtasks.length" class="space-y-2">
                        <div
                            v-for="(subtask, index) in form.subtasks"
                            :key="index"
                            class="flex items-center gap-2"
                        >
                            <Input
                                v-model="subtask.title"
                                placeholder="Subtask"
                                :class="fieldClass"
                            />
                            <button
                                type="button"
                                class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/5 text-red-400"
                                @click="removeSubtask(index)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <p v-else class="text-xs text-slate-500">
                        No subtasks. Kids can tick each step.
                    </p>
                </div>

                <p
                    v-if="error"
                    class="rounded-2xl bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-300"
                >
                    {{ error }}
                </p>

                <div class="flex gap-3 pb-[env(safe-area-inset-bottom)]">
                    <Button
                        type="button"
                        variant="ghost"
                        class="h-14 flex-1 rounded-2xl text-slate-300"
                        @click="emit('update:open', false)"
                    >
                        <X class="mr-1 h-4 w-4" /> Cancel
                    </Button>
                    <Button
                        type="button"
                        class="h-14 flex-[2] rounded-2xl bg-sky-500 text-base font-black text-slate-950"
                        :disabled="saving"
                        @click="save"
                    >
                        {{ saving ? 'Saving…' : 'Save chore' }}
                    </Button>
                </div>
            </div>
        </DialogScrollContent>
    </Dialog>
</template>
