<script setup>
import { ref, onMounted, onUnmounted, inject } from 'vue'
import { TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Button } from '@/components/ui/button'
import {
    Calendar,
    CloudSun,
    ChefHat,
    ShoppingBag,
    CheckSquare,
    Sun,
    Cloud,
    CloudRain,
    CloudLightning,
    CloudSnow,
    Settings,
    Telescope,
    Radar,
} from 'lucide-vue-next'
import { useWeather } from '@/composables/useWeather'

const props = defineProps({
    activeTab: {
        type: String,
        default: 'family',
    },
    localTimezone: {
        type: String,
        default: 'America/New_York',
    },
})

const emit = defineEmits(['open-settings'])

const weatherView = inject('weatherView')

const toggleWeatherView = () => {
    if (weatherView) {
        weatherView.value = weatherView.value === 'weather' ? 'astronomy' : 'weather'
    }
}

const { weatherData, fetchWeather } = useWeather()

// Helper to get weather icon
const getWeatherIcon = (description) => {
    if (!description) return Sun
    const desc = description.toLowerCase()
    if (desc.includes('rain') || desc.includes('drizzle')) return CloudRain
    if (desc.includes('thunder') || desc.includes('storm'))
        return CloudLightning
    if (desc.includes('snow') || desc.includes('ice')) return CloudSnow
    if (desc.includes('cloud') || desc.includes('overcast')) return Cloud
    return Sun
}

const currentTime = ref('')
const currentDate = ref('')

const updateDateTime = () => {
    const now = new Date()
    currentTime.value = now.toLocaleTimeString([], {
        timeZone: props.localTimezone,
        hour: '2-digit',
        minute: '2-digit',
    })
    currentDate.value = now.toLocaleDateString([], {
        timeZone: props.localTimezone,
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    })
}

let timer = null

onMounted(() => {
    updateDateTime()
    timer = setInterval(updateDateTime, 1000)

    // Fetch weather once on mount if not already loaded
    if (!weatherData.value) {
        fetchWeather()
    }
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
                class="flex h-16 shrink-0 flex-col justify-center rounded-3xl border border-white/10 bg-white/40 px-6 shadow-none backdrop-blur-2xl dark:bg-white/5"
            >
                <div
                    class="text-center text-2xl leading-none font-black tracking-tighter tabular-nums whitespace-nowrap"
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

        <TabsList
            class="h-16 w-fit rounded-3xl border-none bg-white/40 p-1.5 shadow-none backdrop-blur-2xl dark:bg-white/5"
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
            <TabsTrigger
                value="chores"
                class="h-full flex-1 gap-2 rounded-2xl text-lg font-black data-[state=active]:shadow-none"
            >
                <CheckSquare class="h-5 w-5" />
                <span class="hidden lg:inline">Chores</span>
            </TabsTrigger>
        </TabsList>

        <!-- Right Actions (Weather + Settings) -->
        <div class="flex shrink-0 items-center gap-3">
            <Button
                v-if="props.activeTab === 'weather'"
                variant="ghost"
                @click="toggleWeatherView"
                :class="[
                    'hidden md:flex h-16 px-4 rounded-3xl font-black uppercase tracking-widest transition-all whitespace-nowrap hover:bg-transparent',
                    weatherView === 'weather' 
                        ? 'text-slate-700 dark:text-indigo-400 hover:opacity-70' 
                        : 'text-slate-900 dark:text-green-600 hover:opacity-70'
                ]"
            >
                <Telescope v-if="weatherView === 'weather'" class="w-5 h-5 mr-2 shrink-0" />
                <Radar v-else class="w-5 h-5 mr-2 shrink-0" />
                {{ weatherView === 'weather' ? 'Night Sky' : 'Radar Map' }}
            </Button>

            <div
                v-if="weatherData"
                class="hidden h-16 items-center gap-4 rounded-3xl border border-white/10 bg-white/20 px-6 backdrop-blur-xl transition-all md:flex dark:bg-white/5"
            >
                <component
                    :is="
                        getWeatherIcon(
                            weatherData.current.weather[0].description,
                        )
                    "
                    class="h-6 w-6 text-yellow-500"
                />
                <span class="text-xl leading-none font-black tabular-nums"
                    >{{ Math.round(weatherData.current.temp) }}&deg;</span
                >
            </div>
            <div
                v-else
                class="hidden h-16 items-center gap-4 rounded-3xl border border-white/10 bg-white/20 px-6 opacity-50 backdrop-blur-xl md:flex dark:bg-white/5"
            >
                <Sun class="h-6 w-6 animate-pulse text-yellow-500" />
                <span
                    class="animate-pulse text-xl leading-none font-black tabular-nums"
                    >--&deg;</span
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
