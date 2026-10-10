<script setup>
import { ref, watch } from 'vue'
import { Gift, Wallet } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { rewardApi } from '@/lib/mobileApi'

const props = defineProps({
    activeProfile: { type: String, default: '' },
})

const emit = defineEmits(['toast'])

const bank = ref(null)
const loading = ref(false)
const redeemAmount = ref('')
const busy = ref(false)

async function load() {
    if (!props.activeProfile) return
    loading.value = true
    try {
        bank.value = await rewardApi.bank(props.activeProfile)
    } catch {
        emit('toast', 'Could not load rewards.')
    } finally {
        loading.value = false
    }
}

watch(() => props.activeProfile, load, { immediate: true })

async function redeemMonetary() {
    const amount = parseFloat(redeemAmount.value)
    if (!amount || amount <= 0) {
        emit('toast', 'Enter an amount to redeem.')
        return
    }
    busy.value = true
    try {
        await rewardApi.redeem({
            profile: props.activeProfile,
            type: 'monetary',
            amount,
        })
        redeemAmount.value = ''
        emit('toast', 'Reward redeemed.')
        await load()
    } catch {
        emit('toast', 'Could not redeem.')
    } finally {
        busy.value = false
    }
}

async function redeemTextual(reward, isDaily = false) {
    busy.value = true
    try {
        await rewardApi.redeem({
            profile: props.activeProfile,
            type: 'textual',
            amount: 1,
            reward_text: reward.text,
            is_daily: isDaily,
        })
        emit('toast', 'Reward redeemed.')
        await load()
    } catch {
        emit('toast', 'Could not redeem.')
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <div class="space-y-3">
        <div>
            <h2 class="text-2xl font-black tracking-tight">Rewards</h2>
            <p class="text-sm text-slate-400">{{ activeProfile }}'s bank</p>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/[0.04] p-5">
            <div class="flex items-center gap-2 text-slate-400">
                <Wallet class="h-4 w-4" />
                <span class="text-xs font-bold uppercase tracking-widest">
                    Money balance
                </span>
            </div>
            <p class="mt-2 text-4xl font-black text-emerald-400">
                ${{ (bank?.monetary_balance ?? 0).toFixed(2) }}
            </p>
            <p
                v-if="bank?.pending_monetary_balance"
                class="mt-1 text-xs font-semibold text-amber-300"
            >
                ${{ Number(bank.pending_monetary_balance).toFixed(2) }} pending
                approval
            </p>
            <div class="mt-4 flex gap-2">
                <Input
                    v-model="redeemAmount"
                    inputmode="decimal"
                    placeholder="Amount"
                    class="h-12 rounded-2xl border-0 bg-white/5 text-white"
                />
                <Button
                    class="h-12 rounded-2xl bg-emerald-500 px-5 font-bold text-slate-950"
                    :disabled="busy"
                    @click="redeemMonetary"
                >
                    Redeem
                </Button>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/[0.04] p-5">
            <div class="flex items-center gap-2 text-slate-400">
                <Gift class="h-4 w-4" />
                <span class="text-xs font-bold uppercase tracking-widest">
                    Privileges
                </span>
            </div>

            <p
                v-if="!(bank?.textual_rewards || []).length"
                class="mt-3 text-sm text-slate-500"
            >
                No privileges banked right now.
            </p>

            <ul class="mt-3 space-y-2">
                <li
                    v-for="reward in bank?.textual_rewards || []"
                    :key="reward.text"
                    class="flex items-center justify-between gap-2 rounded-2xl bg-white/5 px-3 py-2"
                >
                    <span class="text-sm font-semibold">
                        {{ reward.text }}
                        <span class="text-slate-500">×{{ reward.count }}</span>
                    </span>
                    <Button
                        class="h-9 rounded-xl bg-sky-500 px-3 text-xs font-bold text-slate-950"
                        :disabled="busy"
                        @click="redeemTextual(reward)"
                    >
                        Redeem
                    </Button>
                </li>
            </ul>

            <template v-if="(bank?.daily_rewards || []).length">
                <p
                    class="mt-5 text-xs font-bold uppercase tracking-widest text-slate-500"
                >
                    Today only
                </p>
                <ul class="mt-2 space-y-2">
                    <li
                        v-for="reward in bank.daily_rewards"
                        :key="`daily-${reward.text}`"
                        class="flex items-center justify-between gap-2 rounded-2xl bg-white/5 px-3 py-2"
                    >
                        <span class="text-sm font-semibold">
                            {{ reward.text }}
                            <span class="text-slate-500">×{{ reward.count }}</span>
                        </span>
                        <Button
                            class="h-9 rounded-xl bg-sky-500 px-3 text-xs font-bold text-slate-950"
                            :disabled="busy"
                            @click="redeemTextual(reward, true)"
                        >
                            Redeem
                        </Button>
                    </li>
                </ul>
            </template>
        </div>
    </div>
</template>
