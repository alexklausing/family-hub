<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick, watch } from 'vue'
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import {
    AlertCircle,
    Wind,
    Droplets,
    Thermometer,
    CloudRain,
    Sun,
    Cloud,
    CloudLightning,
    Map as MapIcon,
    RefreshCw,
    AlertTriangle,
    Home,
    Plus,
    Minus,
    Zap,
    Play,
    Pause,
    Maximize2,
    Minimize2,
} from 'lucide-vue-next'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog'

const weatherData = ref(null)
const alerts = ref([])
const location = ref(null)
const apiKey = ref(null)
const isLoading = ref(true)
const isSyncing = ref(false)
const isFullMap = ref(false)

const fetchWeather = async (silent = false) => {
    if (!silent) isLoading.value = true
    else isSyncing.value = true

    try {
        const response = await axios.get('/api/weather')
        weatherData.value = response.data.weather
        alerts.value = response.data.alerts
        location.value = response.data.location
        apiKey.value = response.data.apiKey
    } catch (error) {
        console.error('Failed to fetch weather:', error)
    } finally {
        isLoading.value = false
        isSyncing.value = false
    }
}

// Map & Radar State
const mapContainer = ref(null)
let map = null
let radarLayers = []
let lightningLayer = null
let warningsLayer = null

// Layer Toggles
const showPrecipitation = ref(true)
const showLightning = ref(true)
const showWarnings = ref(true)

// Animation State
const isPlaying = ref(true)
const currentFrameIndex = ref(5) // Latest frame
const frames = ref([]) // Array of timestamps
let animationInterval = null

// Warning Modal State
const selectedWarning = ref(null)
const isWarningModalOpen = ref(false)

const formatFrameTime = (ts) => {
    if (!ts) return ''
    const hour = ts.substring(8, 10)
    const min = ts.substring(10, 12)
    const date = new Date()
    date.setUTCHours(parseInt(hour))
    date.setUTCMinutes(parseInt(min))
    return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
}

const getTimestamps = () => {
    const times = []
    const now = new Date()
    const rounded = new Date(Math.floor(now.getTime() / 300000) * 300000)

    for (let i = 5; i >= 0; i--) {
        const time = new Date(rounded.getTime() - i * 300000)
        const year = time.getUTCFullYear()
        const month = String(time.getUTCMonth() + 1).padStart(2, '0')
        const day = String(time.getUTCDate()).padStart(2, '0')
        const hour = String(time.getUTCHours()).padStart(2, '0')
        const min = String(time.getUTCMinutes()).padStart(2, '0')
        times.push(`${year}${month}${day}${hour}${min}`)
    }
    return times
}

const updateRadarLayers = () => {
    if (!map) return
    radarLayers.forEach((l) => map.removeLayer(l))
    radarLayers = []

    if (!showPrecipitation.value) return

    const timestamps = getTimestamps()
    frames.value = timestamps

    timestamps.forEach((ts, index) => {
        const layer = L.tileLayer(
            `https://mesonet.agron.iastate.edu/cache/tile.py/1.0.0/ridge::USCOMP-N0Q-${ts}/{z}/{x}/{y}.png`,
            {
                maxZoom: 19,
                opacity: index === currentFrameIndex.value ? 0.7 : 0,
                zIndex: 1000,
            },
        )
        radarLayers.push(layer)
        layer.addTo(map)
    })
}

const updateLightningLayer = () => {
    if (!map) return
    if (lightningLayer) map.removeLayer(lightningLayer)
    if (!showLightning.value) return

    lightningLayer = L.tileLayer(
        'https://mesonet.agron.iastate.edu/cache/tile.py/1.0.0/lightning/{z}/{x}/{y}.png',
        {
            maxZoom: 19,
            opacity: 0.8,
            zIndex: 1100,
        },
    ).addTo(map)
}

const fetchWarnings = async () => {
    if (!map) return
    try {
        const response = await fetch(
            'https://api.weather.gov/alerts/active?severity=Extreme,Severe',
            {
                headers: {
                    Accept: 'application/geo+json',
                    'User-Agent': '(FamilyHub, contact@familyhub.local)',
                },
            },
        )
        const data = await response.json()

        if (warningsLayer) map.removeLayer(warningsLayer)
        if (!showWarnings.value) return

        warningsLayer = L.geoJSON(data, {
            style: (feature) => {
                const event = feature.properties.event.toLowerCase()
                if (event.includes('tornado'))
                    return {
                        color: '#ef4444',
                        weight: 3,
                        fillOpacity: 0.2,
                        fillColor: '#ef4444',
                    }
                if (event.includes('thunderstorm'))
                    return {
                        color: '#eab308',
                        weight: 2,
                        fillOpacity: 0.15,
                        fillColor: '#eab308',
                    }
                if (event.includes('flood'))
                    return {
                        color: '#22c55e',
                        weight: 2,
                        fillOpacity: 0.15,
                        fillColor: '#22c55e',
                    }
                return { color: '#ffffff', weight: 1, fillOpacity: 0.1 }
            },
            onEachFeature: (feature, layer) => {
                layer.on('click', (e) => {
                    L.DomEvent.stopPropagation(e)
                    selectedWarning.value = feature.properties
                    isWarningModalOpen.value = true
                })
            },
        }).addTo(map)
    } catch (error) {
        console.error('Failed to fetch NWS warnings:', error)
    }
}

const startAnimation = () => {
    if (animationInterval) clearInterval(animationInterval)
    animationInterval = setInterval(() => {
        if (!isPlaying.value || radarLayers.length === 0) return
        if (radarLayers[currentFrameIndex.value]) {
            radarLayers[currentFrameIndex.value].setOpacity(0)
        }
        currentFrameIndex.value =
            (currentFrameIndex.value + 1) % radarLayers.length
        if (radarLayers[currentFrameIndex.value]) {
            radarLayers[currentFrameIndex.value].setOpacity(0.7)
        }
    }, 600)
}

const initMap = () => {
    if (!location.value || !mapContainer.value || map) return

    map = L.map(mapContainer.value, {
        zoomControl: false,
        attributionControl: false,
    }).setView([location.value.lat, location.value.lon], 9)

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
    }).addTo(map)

    updateRadarLayers()
    updateLightningLayer()
    fetchWarnings()

    const homeIcon = L.divIcon({
        html: `
            <div class="relative">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="relative text-primary drop-shadow-[0_0_8px_rgba(255,255,255,0.8)]"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
        `,
        className: 'custom-home-icon',
        iconSize: [32, 32],
        iconAnchor: [16, 16],
    })

    L.marker([location.value.lat, location.value.lon], {
        icon: homeIcon,
        zIndexOffset: 2000,
    }).addTo(map)
    startAnimation()
}

watch(location, () => {
    if (location.value) {
        nextTick(() => initMap())
    }
})

watch(isFullMap, () => {
    nextTick(() => {
        if (map) {
            // Force Leaflet to recalculate its container size after the expansion
            map.invalidateSize()
        }
    })
})

watch([showPrecipitation, showLightning, showWarnings], () => {
    updateRadarLayers()
    updateLightningLayer()
    fetchWarnings()
})

const zoomIn = () => map?.zoomIn()
const zoomOut = () => map?.zoomOut()
const togglePlayback = () => (isPlaying.value = !isPlaying.value)

const weatherIcon = (condition) => {
    const code = condition?.toLowerCase() || ''
    if (code.includes('rain')) return CloudRain
    if (code.includes('cloud')) return Cloud
    if (code.includes('clear')) return Sun
    if (code.includes('storm')) return CloudLightning
    return Sun
}

// Formatting helpers
const formatTemp = (temp) => {
    if (temp === null || temp === undefined || isNaN(temp)) return '--'
    return Math.round(temp) + '°'
}
const formatTime = (timestamp) => {
    return new Date(timestamp * 1000).toLocaleTimeString([], {
        hour: 'numeric',
    })
}
const formatDate = (timestamp) => {
    return new Date(timestamp * 1000).toLocaleDateString([], {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    })
}

onMounted(() => {
    fetchWeather()
    const weatherInterval = setInterval(() => fetchWeather(true), 600000)
    onUnmounted(() => {
        clearInterval(weatherInterval)
        if (animationInterval) clearInterval(animationInterval)
    })
})
</script>

<template>
    <div
        class="custom-scrollbar flex h-full w-full flex-col gap-6 overflow-x-hidden overflow-y-auto p-2 lg:p-4"
    >
        <!-- Dashboard Content -->
        <div
            :class="[
                'grid shrink-0 gap-6 lg:grid-cols-12',
                isFullMap ? 'flex-1' : '',
            ]"
        >
            <!-- Current Weather Card (Hidden in Full Map) -->
            <Card
                v-if="!isFullMap"
                class="rounded-[2.5rem] border-none bg-white/60 shadow-none backdrop-blur-3xl lg:col-span-4 dark:bg-white/5"
            >
                <CardContent
                    class="flex h-full min-h-[300px] flex-col justify-between p-8"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <h2
                                class="text-xl font-black tracking-widest uppercase opacity-40"
                            >
                                Right Now
                            </h2>
                            <div
                                class="mt-2 text-7xl font-black tracking-tighter lg:text-8xl"
                            >
                                {{
                                    weatherData?.current
                                        ? formatTemp(weatherData.current.temp)
                                        : '--'
                                }}
                            </div>
                            <p
                                class="mt-1 text-2xl font-bold capitalize opacity-80"
                            >
                                {{
                                    weatherData?.current?.weather[0]
                                        ?.description || 'Loading...'
                                }}
                            </p>
                        </div>
                        <component
                            :is="
                                weatherIcon(
                                    weatherData?.current?.weather[0]?.main,
                                )
                            "
                            class="text-primary h-20 w-20 drop-shadow-2xl"
                        />
                    </div>

                    <div class="mt-8 grid grid-cols-3 gap-4">
                        <div class="flex flex-col items-center gap-1">
                            <Wind class="h-5 w-5 opacity-40" /><span
                                class="text-sm font-black"
                                >{{
                                    Math.round(
                                        weatherData?.current?.wind_speed || 0,
                                    )
                                }}
                                mph</span
                            >
                            <span
                                class="text-[10px] font-bold uppercase opacity-30"
                                >Wind</span
                            >
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <Droplets class="h-5 w-5 opacity-40" /><span
                                class="text-sm font-black"
                                >{{
                                    weatherData?.current?.humidity || 0
                                }}%</span
                            >
                            <span
                                class="text-[10px] font-bold uppercase opacity-30"
                                >Humid</span
                            >
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <Thermometer class="h-5 w-5 opacity-40" /><span
                                class="text-sm font-black"
                                >{{
                                    formatTemp(
                                        weatherData?.current?.feels_like || 0,
                                    )
                                }}</span
                            >
                            <span
                                class="text-[10px] font-bold uppercase opacity-30"
                                >Feels</span
                            >
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Radar Card -->
            <Card
                :class="[
                    'group relative overflow-hidden rounded-[2.5rem] border-none bg-black/20 shadow-none backdrop-blur-3xl',
                    isFullMap ? 'col-span-full' : 'lg:col-span-8',
                ]"
            >
                <div v-if="location" class="absolute inset-0 z-0">
                    <div
                        ref="mapContainer"
                        class="h-full w-full bg-[#111]"
                    ></div>
                </div>

                <!-- Floating Layer Controls (Bottom Left) -->
                <div
                    class="absolute bottom-6 left-6 z-[1000] flex flex-col gap-3"
                >
                    <div
                        class="flex flex-col gap-1 overflow-hidden rounded-2xl border border-white/10 bg-black/40 p-1 shadow-2xl backdrop-blur-xl"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="showPrecipitation = !showPrecipitation"
                            :class="[
                                'h-12 w-12 rounded-xl transition-all',
                                showPrecipitation
                                    ? 'bg-primary text-white'
                                    : 'text-white/40 hover:bg-white/10',
                            ]"
                            ><CloudRain class="h-5 w-5"
                        /></Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="showLightning = !showLightning"
                            :class="[
                                'h-12 w-12 rounded-xl transition-all',
                                showLightning
                                    ? 'bg-yellow-500 text-black'
                                    : 'text-white/40 hover:bg-white/10',
                            ]"
                            ><Zap class="h-5 w-5"
                        /></Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="showWarnings = !showWarnings"
                            :class="[
                                'h-12 w-12 rounded-xl transition-all',
                                showWarnings
                                    ? 'bg-red-500 text-white'
                                    : 'text-white/40 hover:bg-white/10',
                            ]"
                            ><AlertTriangle class="h-5 w-5"
                        /></Button>
                    </div>
                </div>

                <!-- Animation Controls (Bottom Right) -->
                <div
                    class="absolute right-6 bottom-6 z-[1000] flex items-center gap-3 rounded-full border border-white/10 bg-black/40 p-1.5 shadow-2xl backdrop-blur-xl"
                >
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="togglePlayback"
                        class="h-10 w-10 rounded-full bg-white/10 text-white hover:bg-white/20"
                    >
                        <Pause v-if="isPlaying" class="h-4 w-4 fill-current" />
                        <Play v-else class="ml-0.5 h-4 w-4 fill-current" />
                    </Button>
                    <div
                        v-if="frames[currentFrameIndex]"
                        class="flex items-center gap-2 pr-4"
                    >
                        <div
                            class="bg-primary h-1.5 w-1.5 animate-pulse rounded-full"
                        ></div>
                        <span
                            class="text-[10px] font-black tracking-[0.2em] text-white/90 uppercase tabular-nums"
                            >{{
                                formatFrameTime(frames[currentFrameIndex])
                            }}</span
                        >
                    </div>
                </div>

                <!-- Top Status Bar (Top Left) -->
                <div
                    class="absolute top-6 left-6 z-[1000] flex items-center gap-3 rounded-2xl border border-white/10 bg-black/40 px-4 py-2 shadow-xl backdrop-blur-md"
                >
                    <div
                        class="h-2 w-2 animate-pulse rounded-full bg-red-500"
                    ></div>
                    <span
                        class="text-[10px] font-black tracking-widest text-white/80 uppercase"
                        >NEXRAD N0Q</span
                    >
                    <div class="mx-1 h-3 w-[1px] bg-white/20"></div>
                    <span
                        class="text-[10px] font-black tracking-widest text-white uppercase"
                        >Live Feed</span
                    >
                </div>

                <!-- System Controls (Top Right) -->
                <div
                    class="absolute top-6 right-6 z-[1000] flex flex-col gap-2"
                >
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="isFullMap = !isFullMap"
                        class="h-12 w-12 rounded-xl border border-white/10 bg-black/20 text-white backdrop-blur-md hover:bg-black/40"
                    >
                        <Minimize2 v-if="isFullMap" class="h-5 w-5" />
                        <Maximize2 v-else class="h-5 w-5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="fetchWeather(true)"
                        class="h-12 w-12 rounded-xl border border-white/10 bg-black/20 text-white backdrop-blur-md hover:bg-black/40"
                    >
                        <RefreshCw
                            :class="[
                                'h-5 w-5',
                                isSyncing ? 'animate-spin' : '',
                            ]"
                        />
                    </Button>
                    <div
                        class="mt-4 flex flex-col gap-1 overflow-hidden rounded-xl border border-white/10 bg-black/20 backdrop-blur-md"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="zoomIn"
                            class="h-12 w-12 rounded-none text-white hover:bg-black/40"
                            ><Plus class="h-5 w-5"
                        /></Button>
                        <div class="h-[1px] w-full bg-white/10"></div>
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="zoomOut"
                            class="h-12 w-12 rounded-none text-white hover:bg-black/40"
                            ><Minus class="h-5 w-5"
                        /></Button>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Hidden when full map -->
        <template v-if="!isFullMap">
            <div v-if="alerts.length > 0" class="space-y-3">
                <div
                    v-for="alert in alerts"
                    :key="alert.id"
                    class="flex animate-pulse items-center gap-4 rounded-[2rem] border-2 border-red-500/20 bg-red-500/10 p-6 text-red-500"
                >
                    <AlertTriangle class="h-8 w-8 shrink-0" />
                    <div class="flex-1">
                        <h3 class="text-lg font-black tracking-tight uppercase">
                            {{ alert.properties.event }}
                        </h3>
                        <p class="text-sm font-bold opacity-80">
                            {{ alert.properties.headline }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid min-h-0 flex-1 grid-cols-1 gap-6 lg:grid-cols-2">
                <Card
                    class="flex min-h-[400px] flex-col rounded-[2.5rem] border-none bg-white/40 shadow-none backdrop-blur-3xl dark:bg-white/5"
                >
                    <CardHeader class="shrink-0 px-8 pt-8 pb-4"
                        ><CardTitle
                            class="flex items-center gap-3 text-2xl font-black tracking-tight"
                            ><div class="bg-primary h-6 w-2 rounded-full"></div>
                            Next 24 Hours</CardTitle
                        ></CardHeader
                    >
                    <CardContent
                        class="custom-scrollbar flex-1 overflow-x-auto overflow-y-hidden p-8 pt-2"
                        ><div class="flex h-full gap-8 pb-4">
                            <div
                                v-for="hour in weatherData?.hourly?.slice(
                                    0,
                                    24,
                                )"
                                :key="hour.dt"
                                class="flex min-w-[100px] flex-col items-center justify-between rounded-[2rem] border border-white/10 bg-white/20 px-6 py-4 dark:bg-white/5"
                            >
                                <span
                                    class="text-xs font-black uppercase opacity-40"
                                    >{{ formatTime(hour.dt) }}</span
                                ><component
                                    :is="weatherIcon(hour.weather[0]?.main)"
                                    class="text-primary h-10 w-10"
                                /><span class="text-2xl font-black">{{
                                    formatTemp(hour.temp)
                                }}</span>
                            </div>
                        </div></CardContent
                    >
                </Card>
                <Card
                    class="flex min-h-[400px] flex-col rounded-[2.5rem] border-none bg-white/40 shadow-none backdrop-blur-3xl dark:bg-white/5"
                >
                    <CardHeader class="shrink-0 px-8 pt-8 pb-4"
                        ><CardTitle
                            class="flex items-center gap-3 text-2xl font-black tracking-tight"
                            ><div class="bg-primary h-6 w-2 rounded-full"></div>
                            10-Day Outlook</CardTitle
                        ></CardHeader
                    >
                    <CardContent
                        class="custom-scrollbar flex-1 overflow-y-auto p-8 pt-2"
                        ><div class="space-y-3">
                            <div
                                v-for="day in weatherData?.daily"
                                :key="day.dt"
                                class="flex items-center justify-between rounded-[1.5rem] border border-white/5 bg-white/20 p-5 transition-all hover:bg-white/30 dark:bg-white/5 dark:hover:bg-white/10"
                            >
                                <span
                                    class="w-32 text-sm font-black uppercase opacity-60"
                                    >{{ formatDate(day.dt) }}</span
                                >
                                <div
                                    class="flex flex-1 items-center justify-center gap-4"
                                >
                                    <component
                                        :is="weatherIcon(day.weather[0]?.main)"
                                        class="text-primary h-8 w-8"
                                    /><span
                                        class="text-sm font-bold capitalize opacity-60"
                                        >{{ day.weather[0]?.description }}</span
                                    >
                                </div>
                                <div class="flex w-32 justify-end gap-4">
                                    <span class="text-lg font-black">{{
                                        formatTemp(day.temp.max)
                                    }}</span
                                    ><span
                                        class="text-lg font-black opacity-30"
                                        >{{ formatTemp(day.temp.min) }}</span
                                    >
                                </div>
                            </div>
                        </div></CardContent
                    >
                </Card>
            </div>
        </template>

        <!-- Warning Details Modal -->
        <Dialog v-model:open="isWarningModalOpen">
            <DialogContent
                class="max-w-2xl rounded-[2.5rem] border-none bg-white/90 p-8 shadow-2xl backdrop-blur-2xl dark:bg-[#111]/90"
            >
                <DialogHeader v-if="selectedWarning">
                    <div
                        class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-red-500/10 text-red-500"
                    >
                        <AlertTriangle class="h-10 w-10" />
                    </div>
                    <DialogTitle
                        class="text-3xl font-black tracking-tight uppercase"
                        >{{ selectedWarning.event }}</DialogTitle
                    >
                    <DialogDescription
                        class="text-foreground/80 mt-4 text-xl leading-relaxed font-bold"
                        >{{ selectedWarning.headline }}</DialogDescription
                    >
                </DialogHeader>
                <div v-if="selectedWarning" class="mt-6 space-y-4">
                    <div class="bg-muted/30 rounded-2xl p-6">
                        <h4
                            class="mb-2 text-xs font-black tracking-widest uppercase opacity-40"
                        >
                            Instructions
                        </h4>
                        <p
                            class="text-sm leading-relaxed font-medium whitespace-pre-wrap opacity-80"
                        >
                            {{ selectedWarning.instruction }}
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-muted/30 rounded-2xl p-4">
                            <h4
                                class="mb-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                            >
                                Severity
                            </h4>
                            <span class="text-sm font-black">{{
                                selectedWarning.severity
                            }}</span>
                        </div>
                        <div class="bg-muted/30 rounded-2xl p-4">
                            <h4
                                class="mb-1 text-[10px] font-black tracking-widest uppercase opacity-40"
                            >
                                Urgency
                            </h4>
                            <span class="text-sm font-black">{{
                                selectedWarning.urgency
                            }}</span>
                        </div>
                    </div>
                </div>
                <div class="mt-8 flex justify-end">
                    <Button
                        @click="isWarningModalOpen = false"
                        class="h-12 rounded-xl px-8 font-bold"
                        >Dismiss</Button
                    >
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 10px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
}
:deep(.custom-home-icon) {
    background: none !important;
    border: none !important;
}
:deep(.leaflet-tile) {
    filter: brightness(0.8) contrast(1.2);
}
.dark :deep(.leaflet-tile) {
    filter: brightness(0.6) invert(1) contrast(3) hue-rotate(200deg)
        saturate(0.3) brightness(0.7);
}
</style>
