<script setup>
import { ref } from 'vue'
import { Pencil, Plus, Trash2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { labelApi } from '@/lib/mobileApi'
import { DAY_SHORT } from '@/lib/chorePayload'
import LabelForm from './LabelForm.vue'

const props = defineProps({
    labels: { type: Array, default: () => [] },
})

const emit = defineEmits(['changed', 'toast'])

const showForm = ref(false)
const editing = ref(null)

function openAdd() {
    editing.value = null
    showForm.value = true
}

function openEdit(label) {
    editing.value = label
    showForm.value = true
}

function bonusSummary(label) {
    const days = label.bonus_reward?.required_days || []
    if (!days.length) return ''
    return days.map((day) => DAY_SHORT[day]).join(' ')
}

async function remove(label) {
    if (!window.confirm(`Delete the "${label.name}" group?`)) return
    try {
        await labelApi.remove(label.id)
        emit('toast', 'Label deleted.')
        emit('changed')
    } catch {
        emit('toast', 'Could not delete the label.')
    }
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-black tracking-tight">Labels</h2>
                <p class="text-sm text-slate-400">Group chores &amp; combo rewards</p>
            </div>
            <Button
                class="h-11 rounded-2xl bg-sky-500 px-4 font-bold text-slate-950"
                @click="openAdd"
            >
                <Plus class="mr-1 h-4 w-4" /> Add
            </Button>
        </div>

        <div
            v-if="!labels.length"
            class="rounded-3xl border border-dashed border-white/15 py-14 text-center text-sm text-slate-500"
        >
            No labels yet. Create one to group chores.
        </div>

        <article
            v-for="label in labels"
            :key="label.id"
            class="rounded-3xl border border-white/10 bg-white/[0.04] p-4"
        >
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <h3 class="text-base font-bold">{{ label.name }}</h3>
                    <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs">
                        <span
                            v-if="label.reward"
                            class="rounded-full bg-amber-400/15 px-2.5 py-1 font-semibold text-amber-300"
                        >
                            {{ label.reward }}
                        </span>
                        <span
                            v-if="label.bonus_reward"
                            class="rounded-full bg-fuchsia-400/15 px-2.5 py-1 font-semibold text-fuchsia-300"
                        >
                            Bonus {{ bonusSummary(label) }}
                        </span>
                        <span
                            v-if="label.available_from"
                            class="rounded-full bg-white/5 px-2.5 py-1 font-semibold text-slate-300"
                        >
                            from {{ label.available_from }}
                        </span>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <button
                        class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 active:bg-white/10"
                        aria-label="Edit label"
                        @click="openEdit(label)"
                    >
                        <Pencil class="h-4 w-4" />
                    </button>
                    <button
                        class="grid h-8 w-8 place-items-center rounded-lg text-red-400 active:bg-white/10"
                        aria-label="Delete label"
                        @click="remove(label)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </article>

        <LabelForm
            v-model:open="showForm"
            :label="editing"
            @saved="emit('changed')"
            @toast="(m) => emit('toast', m)"
        />
    </div>
</template>
