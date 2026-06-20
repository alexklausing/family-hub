<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Button } from '@/components/ui/button'
import {
    Calendar,
    CloudSun,
    ChefHat,
    ShoppingBag,
    Sun,
    Settings,
} from 'lucide-vue-next'

const emit = defineEmits(['open-settings'])

const currentTime = ref('')
const currentDate = ref('')

const updateDateTime = () => {
    const now = new Date()
    currentTime.value = now.toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    })
    currentDate.value = now.toLocaleDateString([], {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    })
}

let timer = null

onMounted(() => {
    updateDateTime()
    timer = setInterval(updateDateTime, 1000)
})

onUnmounted(() => {
    if (timer) clearInterval(timer)
})
</script>

<template>
    <!-- Compact Unified Header Island -->
    <div
        class="mx-auto flex w-full max-w-7xl shrink-0 items-center justify-between gap-4 px-2"
    >
        <!-- Left Section: Date/Time Island -->
        <div class="flex items-center gap-4">
            <div
                class="flex h-16 flex-col justify-center rounded-3xl border border-white/10 bg-white/40 px-8 shadow-none backdrop-blur-2xl dark:bg-white/5"
            >
                <div
                    class="text-center text-2xl leading-none font-black tracking-tighter tabular-nums"
                >
                    {{ currentTime }}
                </div>
                <div
                    class="mt-1 text-center text-[10px] font-bold tracking-widest uppercase opacity-40"
                >
                    {{ currentDate }}
                </div>
            </div>
        </div>

        <!-- Tab Navigation (Center) -->
        <TabsList
            class="h-16 max-w-2xl flex-1 rounded-3xl border-none bg-white/40 p-1.5 shadow-none backdrop-blur-2xl dark:bg-white/5"
        >
            <TabsTrigger
                value="family"
                class="h-full flex-1 gap-2 rounded-2xl text-lg font-black data-[state=active]:shadow-none"
            >
                <Calendar class="h-5 w-5" />
                <span class="hidden lg:inline">Calendar</span>
            </TabsTrigger>
            <TabsTrigger
                value="weather"
                class="h-full flex-1 gap-2 rounded-2xl text-lg font-black data-[state=active]:shadow-none"
            >
                <CloudSun class="h-5 w-5" />
                <span class="hidden lg:inline">Weather</span>
            </TabsTrigger>
            <TabsTrigger
                value="recipes"
                class="h-full flex-1 gap-2 rounded-2xl text-lg font-black data-[state=active]:shadow-none"
            >
                <ChefHat class="h-5 w-5" />
                <span class="hidden lg:inline">Recipes</span>
            </TabsTrigger>
            <TabsTrigger
                value="shopping"
                class="h-full flex-1 gap-2 rounded-2xl text-lg font-black data-[state=active]:shadow-none"
            >
                <ShoppingBag class="h-5 w-5" />
                <span class="hidden lg:inline">Shopping</span>
            </TabsTrigger>
        </TabsList>

        <!-- Right Actions (Weather + Settings) -->
        <div class="flex items-center gap-3">
            <div
                class="hidden h-16 items-center gap-4 rounded-3xl border border-white/10 bg-white/20 px-6 backdrop-blur-xl md:flex dark:bg-white/5"
            >
                <Sun class="h-6 w-6 text-yellow-500" />
                <span class="text-xl leading-none font-black tabular-nums"
                    >72°</span
                >
            </div>

            <Button
                variant="ghost"
                size="icon"
                @click="emit('open-settings')"
                class="h-16 w-16 rounded-3xl bg-white/40 shadow-none backdrop-blur-2xl transition-all hover:bg-white/60 dark:bg-white/5"
            >
                <Settings class="h-7 w-7" />
            </Button>
        </div>
    </div>
</template>
