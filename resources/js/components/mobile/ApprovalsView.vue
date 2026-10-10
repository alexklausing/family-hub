<script setup>
import { onMounted, ref } from 'vue'
import { Check, Inbox, X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { choreApi } from '@/lib/mobileApi'

const emit = defineEmits(['toast'])

const approvals = ref([])
const loading = ref(false)
const busyId = ref(null)

async function load() {
    loading.value = true
    try {
        approvals.value = await choreApi.approvals()
    } catch {
        emit('toast', 'Could not load approvals.')
    } finally {
        loading.value = false
    }
}

onMounted(load)

function typeLabel(item) {
    if (item.is_bonus) return 'Bonus streak'
    if (item.is_label) return 'Label reward'
    return 'Chore'
}

async function process(item, action) {
    busyId.value = item.id
    try {
        const value =
            item.is_bonus || item.is_label
                ? null
                : item.chore?.reward || item.awarded_value || null
        await choreApi.processApproval(item.id, action, value)
        approvals.value = approvals.value.filter((a) => a.id !== item.id)
        emit('toast', action === 'approve' ? 'Approved.' : 'Rejected.')
    } catch {
        emit('toast', 'Could not process that item.')
    } finally {
        busyId.value = null
    }
}
</script>

<template>
    <div class="space-y-3">
        <div>
            <h2 class="text-2xl font-black tracking-tight">Approvals</h2>
            <p class="text-sm text-slate-400">Approve completed chores &amp; rewards</p>
        </div>

        <div
            v-if="!approvals.length"
            class="flex flex-col items-center gap-2 rounded-3xl border border-dashed border-white/15 py-14 text-center text-sm text-slate-500"
        >
            <Inbox class="h-6 w-6" />
            {{ loading ? 'Loading…' : 'Nothing waiting for approval.' }}
        </div>

        <article
            v-for="item in approvals"
            :key="item.id"
            class="rounded-3xl border border-white/10 bg-white/[0.04] p-4"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span
                        class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-400"
                    >
                        {{ typeLabel(item) }}
                    </span>
                    <h3 class="mt-2 text-base font-bold leading-tight">
                        {{ item.chore?.title || item.chore?.name || 'Chore' }}
                    </h3>
                    <p class="mt-1 text-xs text-slate-400">
                        {{ item.chore?.profile }} · {{ item.date }}
                    </p>
                    <p
                        v-if="item.awarded_value"
                        class="mt-1 text-sm font-semibold text-amber-300"
                    >
                        {{ item.awarded_value }}
                    </p>
                </div>
            </div>

            <div class="mt-3 flex gap-2">
                <Button
                    class="h-11 flex-1 rounded-2xl bg-emerald-500 font-bold text-slate-950"
                    :disabled="busyId === item.id"
                    @click="process(item, 'approve')"
                >
                    <Check class="mr-1 h-4 w-4" /> Approve
                </Button>
                <Button
                    variant="ghost"
                    class="h-11 flex-1 rounded-2xl bg-red-500/10 font-bold text-red-300"
                    :disabled="busyId === item.id"
                    @click="process(item, 'reject')"
                >
                    <X class="mr-1 h-4 w-4" /> Reject
                </Button>
            </div>
        </article>
    </div>
</template>
