<script setup>
import { ref, onMounted, onUnmounted, inject, computed } from 'vue'
import axios from 'axios'
import { Car, MapPin, GraduationCap, Building2, ShoppingCart, RefreshCw, AlertTriangle } from 'lucide-vue-next'

const homeAddress = inject('homeAddress', ref({ address: '', lat: null, lon: null }))

const etas = ref([])
const isLoading = ref(true)
const isRefreshing = ref(false)
const lastRefreshed = ref(null)
let refreshIntervalTimer = null

const REFRESH_INTERVAL_MS = 15 * 60 * 1000

const ICON_MAP = { GraduationCap, Building2, ShoppingCart, MapPin }
const resolveIcon = (name) => ICON_MAP[name] || MapPin
const COLORS = ['#0ea5e9', '#8b5cf6', '#f59e0b', '#10b981', '#ef4444', '#ec4899']

const arrivalLabel = (eta) => {
    const seconds = eta.route?.travel_time_seconds
    if (!seconds && seconds !== 0) return ''
    return new Date(Date.now() + seconds * 1000).toLocaleTimeString([], {
        hour: 'numeric',
        minute: '2-digit',
    })
}

const hasHome = computed(() => !!(homeAddress.value?.lat && homeAddress.value?.lon))

const needsRefresh = () => {
    if (!lastRefreshed.value) return true
    return Date.now() - lastRefreshed.value.getTime() > REFRESH_INTERVAL_MS
}

const fetchEtas = async (force = false) => {
    if (!hasHome.value) return
    isRefreshing.value = force
    try {
        const params = { home_lat: homeAddress.value.lat, home_lon: homeAddress.value.lon }
        const res = force
            ? await axios.post('/api/commute/refresh', null, { params })
            : await axios.get('/api/commute/etas', { params })
        etas.value = res.data
        lastRefreshed.value = new Date()
    } catch (e) {
        console.error('Failed to fetch ETAs:', e)
    } finally {
        isRefreshing.value = false
        isLoading.value = false
    }
}

const handleVisibility = () => {
    if (document.visibilityState === 'visible' && needsRefresh()) {
        fetchEtas(false)
    }
}

onMounted(async () => {
    await fetchEtas(false)
    document.addEventListener('visibilitychange', handleVisibility)
    refreshIntervalTimer = setInterval(() => {
        if (document.visibilityState === 'visible' && needsRefresh()) {
            fetchEtas(false)
        }
    }, 60000)
})

onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibility)
    if (refreshIntervalTimer) clearInterval(refreshIntervalTimer)
})
</script>

<template>
    <div class="w-full h-full flex flex-col overflow-hidden rounded-3xl bg-white dark:bg-[#111] p-4">
        <div class="flex items-center justify-between mb-3 shrink-0">
            <div class="flex items-center gap-2">
                <Car class="w-5 h-5 text-sky-500" />
                <h3 class="text-base font-black tracking-tight">Commute</h3>
            </div>
            <button
                @click="fetchEtas(true)"
                :disabled="isRefreshing"
                class="h-8 w-8 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
            >
                <RefreshCw :class="['w-4 h-4', isRefreshing ? 'animate-spin' : '']" />
            </button>
        </div>

        <div v-if="!hasHome" class="flex-1 flex flex-col items-center justify-center gap-2 text-center px-3">
            <AlertTriangle class="w-8 h-8 text-amber-500" />
            <p class="text-xs font-bold opacity-60">Set your home address in Settings</p>
        </div>

        <div v-else-if="isLoading" class="flex-1 flex items-center justify-center">
            <RefreshCw class="w-6 h-6 text-sky-500 animate-spin" />
        </div>

        <div v-else-if="etas.length === 0" class="flex-1 flex flex-col items-center justify-center gap-2 text-center px-3">
            <MapPin class="w-8 h-8 text-sky-500 opacity-50" />
            <p class="text-xs font-bold opacity-60">No destinations</p>
        </div>

        <div v-else class="flex-1 overflow-y-auto custom-scrollbar flex flex-col gap-2">
            <div
                v-for="(eta, index) in etas"
                :key="eta.destination.id"
                class="flex items-center gap-3 p-3 rounded-2xl border border-black/5 dark:border-white/10"
            >
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl shrink-0"
                    :style="{ backgroundColor: COLORS[index % COLORS.length] + '22', color: COLORS[index % COLORS.length] }"
                >
                    <component :is="resolveIcon(eta.destination.icon)" class="w-5 h-5" />
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="text-sm font-black tracking-tight truncate">{{ eta.destination.name }}</h4>
                    <span v-if="eta.route" class="text-lg font-black leading-none">
                        {{ eta.route.travel_time_minutes }}
                        <span class="text-xs font-bold opacity-60">min</span>
                    </span>
                    <span v-if="arrivalLabel(eta)" class="text-xs font-black text-sky-500 leading-none mt-0.5">
                        Arrive {{ arrivalLabel(eta) }}
                    </span>
                    <span v-else class="text-xs font-bold opacity-50">—</span>
                </div>
                <span
                    v-if="eta.route"
                    class="w-2.5 h-2.5 rounded-full shrink-0"
                    :class="{
                        'bg-green-500': (eta.route.traffic_delay_minutes || 0) < 2,
                        'bg-amber-400': (eta.route.traffic_delay_minutes || 0) >= 2 && (eta.route.traffic_delay_minutes || 0) < 5,
                        'bg-red-500': (eta.route.traffic_delay_minutes || 0) >= 5,
                    }"
                ></span>
            </div>
        </div>
    </div>
</template>