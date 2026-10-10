<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { labelApi } from '@/lib/mobileApi'
import { useRewardForm } from '@/composables/useRewardForm'
import {
    ALL_DAYS,
    DAY_LABELS,
    REWARD_PRESETS,
    blankLabel,
    buildLabelPayload,
    labelToForm,
} from '@/lib/chorePayload'

const props = defineProps({
    open: { type: Boolean, default: false },
    label: { type: Object, default: null },
})

const emit = defineEmits(['update:open', 'saved', 'toast'])

const form = reactive(blankLabel())
const { rewardMode, moneyAmount, textReward, decodeReward, toggleBonus, toggleBonusDay } =
    useRewardForm(form)
const saving = ref(false)
const error = ref('')

const isEditing = computed(() => !!props.label)
const bonusEnabled = computed(() => !!form.bonus_reward)

watch(
    () => [props.open, props.label],
    ([open]) => {
        if (!open) return
        error.value = ''
        Object.assign(form, props.label ? labelToForm(props.label) : blankLabel())
        decodeReward(form.reward)
    },
    { immediate: true },
)

async function save() {
    if (!form.name.trim()) {
        error.value = 'Give the label a name.'
        return
    }
    saving.value = true
    error.value = ''
    try {
        const payload = buildLabelPayload(form)
        if (isEditing.value) {
            await labelApi.update(props.label.id, payload)
            emit('toast', 'Label updated.')
        } else {
            await labelApi.create(payload)
            emit('toast', 'Label added.')
        }
        emit('saved')
        emit('update:open', false)
    } catch (e) {
        error.value =
            e?.response?.data?.message || 'Could not save the label. Try again.'
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
                    {{ isEditing ? 'Edit label' : 'New label' }}
                </DialogTitle>
            </DialogHeader>

            <div class="space-y-4 pb-2">
                <div :class="sectionClass">
                    <label :class="labelClass" for="label-name">Name</label>
                    <Input
                        id="label-name"
                        v-model="form.name"
                        placeholder="e.g. Morning Routine"
                        :class="fieldClass"
                    />
                    <label :class="[labelClass, 'mt-4']" for="label-available">
                        Available from
                    </label>
                    <input
                        id="label-available"
                        v-model="form.available_from"
                        type="time"
                        :class="fieldClass"
                    />
                </div>

                <div :class="sectionClass">
                    <label :class="labelClass">Group reward</label>
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
                            placeholder="e.g. Movie night"
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
                        <Switch :checked="bonusEnabled" @update:checked="toggleBonus" />
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
                        {{ saving ? 'Saving…' : 'Save label' }}
                    </Button>
                </div>
            </div>
        </DialogScrollContent>
    </Dialog>
</template>
