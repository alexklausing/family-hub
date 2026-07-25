<script setup>
import { computed } from 'vue'
import { Clock, History } from 'lucide-vue-next'

const props = defineProps({
    scheduleEvents: {
        type: Array,
        default: () => []
    }
})

const getEventDate = (event, isEnd = false) => {
    const val = isEnd ? (event.end || event.start) : event.start
    if (typeof val === 'string' && val.length <= 10) {
        const date = new Date(`${val}T00:00:00`)
        if (isEnd && event.end) {
            return new Date(date.getTime() - 1)
        }
        return date
    }
    return new Date(val)
}

const sortedSchedule = computed(() => {
    const now = new Date()
    return [...props.scheduleEvents]
        .filter((e) => {
            const start = getEventDate(e)
            const end = getEventDate(e, true)
            return (
                end >= now ||
                (e.all_day && start.toDateString() === now.toDateString())
            )
        })
        .sort((a, b) => getEventDate(a) - getEventDate(b))
        .slice(0, 50)
})
</script>

<template>
    <div class="h-full w-full flex flex-col gap-3 mt-8">
        <div class="flex items-center justify-between px-2">
            <h3 class="text-sm font-black tracking-widest uppercase text-black/40 dark:text-white/40 flex items-center gap-2">
                <Clock class="h-4 w-4" />
                Up Next
            </h3>
        </div>
        <div class="custom-scrollbar flex-1 overflow-y-auto px-2 pb-4">
                <div class="space-y-3">
                    <div v-if="sortedSchedule.length > 0" class="space-y-2">
                        <div
                            v-for="event in sortedSchedule"
                            :key="event.id"
                            class="flex items-center gap-4 rounded-[1.5rem] border border-white/40 bg-white/40 p-4 transition-colors hover:bg-white/60 dark:border-white/5 dark:bg-white/5 dark:hover:bg-white/10"
                        >
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-[1rem] text-2xl"
                                :style="{
                                    backgroundColor: event.extendedProps?.calendarColor ? `${event.extendedProps.calendarColor}20` : '#ccc2',
                                    color: event.extendedProps?.calendarColor || '#ccc'
                                }"
                            >
                                <span v-if="event.extendedProps?.icon">{{ event.extendedProps.icon }}</span>
                                <span v-else class="text-lg font-bold">📅</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h4 class="truncate font-bold tracking-tight text-slate-800 dark:text-white">
                                    {{ event.title }}
                                </h4>
                                <p class="truncate text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">
                                    <span v-if="getEventDate(event).toDateString() === new Date().toDateString()">Today</span>
                                    <span v-else>{{ getEventDate(event).toLocaleDateString([], { month: 'short', day: 'numeric' }) }}</span>
                                    <span v-if="!event.all_day">
                                        • {{ getEventDate(event).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="py-12 text-center opacity-20">
                        <History class="mx-auto mb-4 h-12 w-12" />
                        <p class="text-sm font-bold uppercase">Clear Schedule</p>
                    </div>
            </div>
        </div>
    </div>
</template>
