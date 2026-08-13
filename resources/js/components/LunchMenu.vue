<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import { ChevronLeft, ChevronRight, Utensils } from 'lucide-vue-next'

const weekOffset = ref(0)
const isLoading = ref(true)
const loadError = ref(false)
const menu = ref(null)

const WEEKDAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri']

const formatYMD = (date) => {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
}

// Monday of the currently viewed week (shifted by weekOffset)
const weekStart = computed(() => {
    const now = new Date()
    const day = now.getDay() // 0 = Sunday
    const diff = day === 0 ? 6 : day - 1
    const monday = new Date(
        now.getFullYear(),
        now.getMonth(),
        now.getDate() - diff + weekOffset.value * 7,
    )
    monday.setHours(0, 0, 0, 0)
    return monday
})

const weekLabel = computed(() => {
    const end = new Date(weekStart.value)
    end.setDate(end.getDate() + 4)
    const opts = { month: 'short', day: 'numeric' }
    return `${weekStart.value.toLocaleDateString([], opts)} – ${end.toLocaleDateString([], opts)}`
})

const fetchMenu = async () => {
    isLoading.value = true
    loadError.value = false
    try {
        const res = await axios.get('/api/lunch-menu', {
            params: { date: formatYMD(weekStart.value) },
        })
        menu.value = res.data
    } catch (e) {
        console.error('Failed to fetch lunch menu', e)
        loadError.value = true
    } finally {
        isLoading.value = false
    }
}

onMounted(fetchMenu)
watch(weekOffset, fetchMenu)

const daysByDate = computed(() => {
    const map = {}
    for (const day of menu.value?.days || []) {
        map[day.date] = day
    }
    return map
})

const todayStr = formatYMD(new Date())

const schoolDays = computed(() => {
    const result = []
    const start = new Date(weekStart.value)
    for (let i = 0; i < 5; i++) {
        const date = new Date(start)
        date.setDate(start.getDate() + i)
        const dateStr = formatYMD(date)
        const day = daysByDate.value[dateStr]
        result.push({
            dateStr,
            label: WEEKDAY_LABELS[i],
            monthDay: date.toLocaleDateString([], {
                month: 'short',
                day: 'numeric',
            }),
            isToday: dateStr === todayStr,
            hasSchool: day?.has_school !== false,
            sections: day?.sections || [],
        })
    }
    return result
})
</script>

<template>
    <div class="flex h-full w-full flex-col gap-4 overflow-hidden p-4 sm:p-6">
        <!-- Header -->
        <div class="flex shrink-0 items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-orange-500/15 text-orange-500"
                >
                    <Utensils class="h-6 w-6" />
                </div>
                <div class="min-w-0">
                    <h2
                        class="truncate text-xl font-black tracking-tight text-slate-900 sm:text-2xl dark:text-white"
                    >
                        {{ menu?.school || 'School Lunch' }}
                    </h2>
                    <p
                        class="text-xs font-bold tracking-widest text-slate-500 uppercase sm:text-sm dark:text-slate-400"
                    >
                        {{ weekLabel }}
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button
                    class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/40 bg-white/40 text-slate-700 transition-colors hover:bg-white/70 dark:border-white/5 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                    title="Previous week"
                    @click="weekOffset--"
                >
                    <ChevronLeft class="h-6 w-6" />
                </button>
                <button
                    class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/40 bg-white/40 text-slate-700 transition-colors hover:bg-white/70 dark:border-white/5 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                    title="Next week"
                    @click="weekOffset++"
                >
                    <ChevronRight class="h-6 w-6" />
                </button>
            </div>
        </div>

        <!-- Body -->
        <div class="min-h-0 flex-1">
            <div
                v-if="isLoading"
                class="flex h-full items-center justify-center"
            >
                <div
                    class="h-10 w-10 animate-spin rounded-full border-4 border-orange-500/20 border-t-orange-500"
                ></div>
            </div>

            <div
                v-else-if="loadError"
                class="flex h-full items-center justify-center"
            >
                <p class="text-lg font-bold text-slate-500 dark:text-slate-400">
                    Couldn't load the menu right now.
                </p>
            </div>

            <div
                v-else
                class="custom-scrollbar grid h-full grid-cols-1 gap-4 overflow-y-auto sm:grid-cols-2 xl:grid-cols-5 xl:overflow-hidden"
            >
                <div
                    v-for="day in schoolDays"
                    :key="day.dateStr"
                    class="flex min-h-0 flex-col rounded-3xl border p-4 transition-colors"
                    :class="[
                        day.isToday
                            ? 'border-orange-500/50 bg-orange-500/10 dark:border-orange-500/40 dark:bg-orange-500/10'
                            : 'border-white/40 bg-white/40 dark:border-white/5 dark:bg-white/5',
                    ]"
                >
                    <div class="mb-3 shrink-0 text-center">
                        <div
                            class="text-sm font-black tracking-widest text-slate-900 uppercase dark:text-white"
                        >
                            {{ day.label }}
                        </div>
                        <div
                            class="text-xs font-bold tracking-widest text-slate-500 uppercase dark:text-slate-400"
                        >
                            {{ day.monthDay }}
                        </div>
                        <div
                            v-if="day.isToday"
                            class="mx-auto mt-1 w-fit rounded-full bg-orange-500 px-2 py-0.5 text-[10px] font-black tracking-widest text-white uppercase"
                        >
                            Today
                        </div>
                    </div>

                    <div
                        class="custom-scrollbar min-h-0 flex-1 overflow-y-auto"
                    >
                        <p
                            v-if="!day.hasSchool"
                            class="py-8 text-center text-sm font-black tracking-widest text-slate-400 uppercase dark:text-slate-500"
                        >
                            No School
                        </p>
                        <div
                            v-else-if="day.sections.length === 0"
                            class="py-8 text-center text-sm font-bold text-slate-400 dark:text-slate-500"
                        >
                            Menu not posted yet
                        </div>
                        <div v-else class="flex flex-col gap-4">
                            <div
                                v-for="(section, idx) in day.sections"
                                :key="idx"
                            >
                                <div
                                    class="mb-1.5 text-[11px] font-black tracking-widest text-orange-600 uppercase dark:text-orange-400"
                                >
                                    {{ section.name.replace(/:$/, '') }}
                                </div>
                                <ul class="flex flex-col gap-1.5">
                                    <li
                                        v-for="(item, itemIdx) in section.items"
                                        :key="itemIdx"
                                        class="rounded-xl border border-white/40 bg-white/50 px-3 py-2 text-sm font-bold text-slate-800 dark:border-white/10 dark:bg-white/10 dark:text-white"
                                    >
                                        {{ item }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
