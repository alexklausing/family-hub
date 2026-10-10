<script setup>
import { computed, ref, watch } from 'vue'
import { Check, CheckCircle2, Clock, Pencil, Plus, Trash2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { choreApi } from '@/lib/mobileApi'
import { todayIso } from '@/lib/chorePayload'
import ChoreForm from './ChoreForm.vue'

const props = defineProps({
    activeProfile: { type: String, default: '' },
    profiles: { type: Array, default: () => [] },
    labels: { type: Array, default: () => [] },
})

const emit = defineEmits(['toast'])

const chores = ref([])
const loading = ref(false)
const showForm = ref(false)
const editing = ref(null)

async function load() {
    if (!props.activeProfile) {
        chores.value = []
        return
    }
    loading.value = true
    try {
        chores.value = await choreApi.list(props.activeProfile, todayIso())
    } catch {
        emit('toast', 'Could not load chores.')
    } finally {
        loading.value = false
    }
}

watch(() => props.activeProfile, load, { immediate: true })

function openAdd() {
    editing.value = null
    showForm.value = true
}

function openEdit(chore) {
    editing.value = chore
    showForm.value = true
}

const subtaskDone = (chore, subtask) =>
    (chore.completed_subtasks || []).includes(subtask.id)

const progressText = (chore) => {
    const total = chore.subtasks?.length || 0
    const done = (chore.completed_subtasks || []).filter((id) =>
        (chore.subtasks || []).some((subtask) => subtask.id === id),
    ).length
    return `${done}/${total}`
}

async function toggleChore(chore) {
    try {
        if (chore.subtasks?.length) {
            const allIds = chore.subtasks.map((subtask) => subtask.id)
            const done = new Set(chore.completed_subtasks || [])
            const allDone = allIds.every((id) => done.has(id))
            const next = allDone ? [] : allIds
            const result = await choreApi.toggle(chore.id, {
                date: todayIso(),
                subtaskIds: next,
            })
            chore.completed_subtasks = next
            chore.completed = result.completed
        } else {
            const result = await choreApi.toggle(chore.id, { date: todayIso() })
            chore.completed = result.completed
        }
    } catch {
        emit('toast', 'Could not update the chore.')
    }
}

async function toggleSubtask(chore, subtask) {
    const done = new Set(chore.completed_subtasks || [])
    if (done.has(subtask.id)) done.delete(subtask.id)
    else done.add(subtask.id)
    const next = [...done]
    try {
        const result = await choreApi.toggle(chore.id, {
            date: todayIso(),
            subtaskIds: next,
        })
        chore.completed_subtasks = next
        chore.completed = result.completed
    } catch {
        emit('toast', 'Could not update the subtask.')
    }
}

async function remove(chore) {
    if (!window.confirm(`Delete "${chore.title}"?`)) return
    try {
        await choreApi.remove(chore.id)
        chores.value = chores.value.filter((item) => item.id !== chore.id)
        emit('toast', 'Chore deleted.')
    } catch {
        emit('toast', 'Could not delete the chore.')
    }
}

const emptyMessage = computed(() =>
    loading.value
        ? 'Loading chores…'
        : `No chores for ${props.activeProfile} today.`,
)
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-black tracking-tight">Chores</h2>
                <p class="text-sm text-slate-400">
                    {{ activeProfile }} · {{ new Date().toLocaleDateString() }}
                </p>
            </div>
            <Button
                class="h-11 rounded-2xl bg-sky-500 px-4 font-bold text-slate-950"
                @click="openAdd"
            >
                <Plus class="mr-1 h-4 w-4" /> Add
            </Button>
        </div>

        <div
            v-if="!chores.length"
            class="rounded-3xl border border-dashed border-white/15 py-14 text-center text-sm text-slate-500"
        >
            {{ emptyMessage }}
        </div>

        <article
            v-for="chore in chores"
            :key="chore.id"
            class="rounded-3xl border border-white/10 bg-white/[0.04] p-4"
        >
            <div class="flex items-start gap-3">
                <button
                    class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-full border-2 transition"
                    :class="
                        chore.completed
                            ? 'border-emerald-400 bg-emerald-400 text-slate-950'
                            : 'border-white/25 text-transparent'
                    "
                    :aria-label="chore.completed ? 'Mark incomplete' : 'Mark complete'"
                    @click="toggleChore(chore)"
                >
                    <Check class="h-5 w-5" />
                </button>

                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <h3
                            class="min-w-0 flex-1 break-words text-base font-bold leading-tight"
                            :class="{ 'text-slate-500 line-through': chore.completed }"
                        >
                            {{ chore.title }}
                        </h3>
                        <div class="flex shrink-0 items-center gap-1">
                            <button
                                class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 active:bg-white/10"
                                aria-label="Edit chore"
                                @click="openEdit(chore)"
                            >
                                <Pencil class="h-4 w-4" />
                            </button>
                            <button
                                class="grid h-8 w-8 place-items-center rounded-lg text-red-400 active:bg-white/10"
                                aria-label="Delete chore"
                                @click="remove(chore)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs">
                        <span
                            v-if="chore.time"
                            class="inline-flex items-center gap-1 rounded-full bg-white/5 px-2.5 py-1 font-semibold text-slate-300"
                        >
                            <Clock class="h-3 w-3" /> {{ chore.time }}
                        </span>
                        <span
                            v-if="chore.label"
                            class="rounded-full bg-sky-500/15 px-2.5 py-1 font-semibold text-sky-300"
                        >
                            {{ chore.label.name }}
                        </span>
                        <span
                            v-if="chore.reward"
                            class="rounded-full bg-amber-400/15 px-2.5 py-1 font-semibold text-amber-300"
                        >
                            {{ chore.reward }}
                        </span>
                        <span
                            v-if="chore.bonus_reward"
                            class="rounded-full bg-fuchsia-400/15 px-2.5 py-1 font-semibold text-fuchsia-300"
                        >
                            Streak bonus
                        </span>
                    </div>

                    <ul v-if="chore.subtasks?.length" class="mt-3 space-y-1.5">
                        <li
                            v-for="subtask in chore.subtasks"
                            :key="subtask.id"
                            class="flex items-center gap-2.5"
                        >
                            <button
                                class="grid h-6 w-6 shrink-0 place-items-center rounded-md border-2 transition"
                                :class="
                                    subtaskDone(chore, subtask)
                                        ? 'border-emerald-400 bg-emerald-400 text-slate-950'
                                        : 'border-white/25 text-transparent'
                                "
                                :aria-label="subtask.title"
                                @click="toggleSubtask(chore, subtask)"
                            >
                                <CheckCircle2 class="h-4 w-4" />
                            </button>
                            <span
                                class="min-w-0 break-words text-sm"
                                :class="{
                                    'text-slate-500 line-through': subtaskDone(
                                        chore,
                                        subtask,
                                    ),
                                }"
                            >
                                {{ subtask.title }}
                            </span>
                        </li>
                        <li class="pt-1 text-[11px] font-semibold text-slate-500">
                            {{ progressText(chore) }} steps complete
                        </li>
                    </ul>
                </div>
            </div>
        </article>

        <ChoreForm
            v-model:open="showForm"
            :chore="editing"
            :profiles="profiles"
            :labels="labels"
            :initial-profile="activeProfile"
            @saved="load"
            @toast="(m) => emit('toast', m)"
        />
    </div>
</template>
