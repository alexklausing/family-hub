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
    Moon,
    Telescope,
    Radar,
    Satellite,
    Rocket,
    Leaf,
    X,
    Flame,
    Tornado,
    Sunrise,
    Sunset,
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
const airQuality = ref(null)
const location = ref(null)
const apiKey = ref(null)
const isLoading = ref(true)
const isSyncing = ref(false)
const isFullMap = ref(false)

import { inject } from 'vue'

// View Toggle
const activeView = inject('weatherView')
const developerSettings = inject('developerSettings', ref({}))

watch(developerSettings, () => {
    fetchWeather(true)
    fetchWarnings()
}, { deep: true })

// Astronomy State
const issData = ref(null)
const launchData = ref(null)
let issInterval = null

// Sidebar & Modals
const isSidebarOpen = ref(false)
const isAqiModalOpen = ref(false)

const fetchWeather = async (silent = false) => {
    if (!silent) isLoading.value = true
    else isSyncing.value = true

    try {
        const devSettings = JSON.parse(localStorage.getItem('developerSettings') || 'null') || {}
        const isTestAlerts = devSettings.masterToggle && devSettings.testWeatherAlerts
        const endpoint = isTestAlerts ? '/api/weather?test_alerts=1' : '/api/weather'
        const response = await axios.get(endpoint)
        weatherData.value = response.data.weather
        alerts.value = response.data.alerts
        airQuality.value = response.data.air_quality
        issData.value = response.data.iss
        launchData.value = response.data.launch
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
let warningsLayer = null
let wildfiresLayer = null
let hurricanesLayers = []

const defaultLayers = JSON.parse(localStorage.getItem('defaultRadarLayers') || 'null') || {
    precipitation: true,
    warnings: false,
    wildfires: false,
    hurricanes: false
}

// Layer Toggles
const showPrecipitation = ref(defaultLayers.precipitation)
const showWarnings = ref(defaultLayers.warnings)
const showWildfires = ref(defaultLayers.wildfires)
const showHurricanes = ref(defaultLayers.hurricanes)

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

const fetchWildfires = async () => {
    if (!map) return
    if (wildfiresLayer) map.removeLayer(wildfiresLayer)
    if (!showWildfires.value) return

    try {
        const response = await fetch(
            'https://services3.arcgis.com/T4QMspbfLg3qTGWY/ArcGIS/rest/services/WFIGS_Incident_Locations_Current/FeatureServer/0/query?where=IncidentTypeCategory=%27WF%27&f=geojson&outFields=*'
        )
        const data = await response.json()
        
        // Custom fire icon
        const fireIcon = L.divIcon({
            html: `
                <div class="relative w-6 h-6 flex items-center justify-center bg-orange-500/20 rounded-full border border-orange-500 shadow-[0_0_10px_rgba(249,115,22,0.5)]">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-orange-500"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
                </div>
            `,
            className: 'custom-fire-marker',
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        })

        wildfiresLayer = L.geoJSON(data, {
            pointToLayer: (feature, latlng) => L.marker(latlng, { icon: fireIcon }),
            onEachFeature: (feature, layer) => {
                const props = feature.properties
                layer.bindPopup(`
                    <div class="p-2 min-w-[200px]">
                        <div class="flex items-center gap-2 text-orange-500 font-black uppercase tracking-widest text-[10px] mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
                            Active Wildfire
                        </div>
                        <div class="text-lg font-bold leading-tight mb-1">${props.IncidentName || 'Unknown Fire'}</div>
                        <div class="text-xs font-bold opacity-60 mb-3">${props.IncidentTypeCategory || 'WF'} &bull; ${props.DiscoveryAcres ? Math.round(props.DiscoveryAcres) + ' Acres' : 'Size Unknown'}</div>
                    </div>
                `, { className: 'custom-weather-popup' })
            }
        }).addTo(map)
    } catch (error) {
        console.error('Failed to fetch Wildfires:', error)
    }
}

const fetchHurricanes = async () => {
    if (!map) return
    hurricanesLayers.forEach(l => map.removeLayer(l))
    hurricanesLayers = []
    if (!showHurricanes.value) return

    try {
        const fetchLayer = async (layerId, isCone = false) => {
            const res = await fetch(`https://services9.arcgis.com/RHVPKKiFTONKtxq3/ArcGIS/rest/services/Active_Hurricanes_v1/FeatureServer/${layerId}/query?where=1=1&f=geojson&outFields=*`)
            const data = await res.json()
            if (!showHurricanes.value) return // Check again in case toggle changed during fetch

            const layer = L.geoJSON(data, {
                style: (feature) => {
                    if (isCone) return { color: '#ffffff', weight: 1, fillOpacity: 0.15, fillColor: '#ffffff', dashArray: '4, 6' }
                    return { color: '#ef4444', weight: 2, fillOpacity: 0.1 }
                },
                pointToLayer: (feature, latlng) => {
                    if (isCone) return L.circleMarker(latlng, { radius: 3 })
                    const hurricaneIcon = L.divIcon({
                        html: `
                            <div class="relative w-8 h-8 flex items-center justify-center bg-red-500/20 rounded-full border border-red-500 shadow-[0_0_15px_rgba(239,68,68,0.5)]">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-red-500"><path d="M21 10.5g0 0-4.04a3.15 3.15 0 0 0-4.88-4.06L7.3 2.39a11.41 11.41 0 0 1 15.1 7.62 11.4 11.4 0 0 1 .11 1.05l-1.51-.56z"/><path d="M10.5 3h0-4.04a3.15 3.15 0 0 0-4.88 4.06L2.39 16.7a11.41 11.41 0 0 1 7.62-15.1 11.4 11.4 0 0 1 1.05-.11L10.5 3z"/><path d="M3 13.5h0 4.04a3.15 3.15 0 0 0 4.88 4.06l4.78 4.05a11.41 11.41 0 0 1-15.1-7.62 11.4 11.4 0 0 1-.11-1.05l1.51.56z"/><path d="M13.5 21h0 4.04a3.15 3.15 0 0 0 4.88-4.06l-4.05-4.78a11.41 11.41 0 0 1-7.62 15.1 11.4 11.4 0 0 1-1.05.11L13.5 21z"/></svg>
                            </div>
                        `,
                        className: 'custom-hurricane-marker',
                        iconSize: [32, 32],
                        iconAnchor: [16, 16]
                    })
                    return L.marker(latlng, { icon: hurricaneIcon })
                },
                onEachFeature: (feature, layer) => {
                    const props = feature.properties
                    const name = props.STORMNAME || props.STORM_NAME || 'Tropical Cyclone'
                    const cat = props.TCDV || props.SS_NUM || 'Active'
                    
                    if (!isCone) {
                        layer.bindPopup(`
                            <div class="p-2 min-w-[200px]">
                                <div class="flex items-center gap-2 text-red-500 font-black uppercase tracking-widest text-[10px] mb-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10.5g0 0-4.04a3.15 3.15 0 0 0-4.88-4.06L7.3 2.39a11.41 11.41 0 0 1 15.1 7.62 11.4 11.4 0 0 1 .11 1.05l-1.51-.56z"/><path d="M10.5 3h0-4.04a3.15 3.15 0 0 0-4.88 4.06L2.39 16.7a11.41 11.41 0 0 1 7.62-15.1 11.4 11.4 0 0 1 1.05-.11L10.5 3z"/><path d="M3 13.5h0 4.04a3.15 3.15 0 0 0 4.88 4.06l4.78 4.05a11.41 11.41 0 0 1-15.1-7.62 11.4 11.4 0 0 1-.11-1.05l1.51.56z"/><path d="M13.5 21h0 4.04a3.15 3.15 0 0 0 4.88-4.06l-4.05-4.78a11.41 11.41 0 0 1-7.62 15.1 11.4 11.4 0 0 1-1.05.11L13.5 21z"/></svg>
                                    NHC Advisory
                                </div>
                                <div class="text-lg font-bold leading-tight mb-1">${name}</div>
                                <div class="text-xs font-bold opacity-60 mb-3">Status: ${cat}</div>
                            </div>
                        `, { className: 'custom-weather-popup' })
                    }
                }
            })
            hurricanesLayers.push(layer)
            layer.addTo(map)
        }
        
        await Promise.all([fetchLayer(0, false), fetchLayer(1, true)])
    } catch (error) {
        console.error('Failed to fetch NHC Hurricane layers:', error)
    }
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

watch(alerts, (newAlerts) => {
    if (newAlerts && newAlerts.length > 0 && !isSidebarOpen.value) {
        isSidebarOpen.value = true
    }
})

// --- Astronomy Methods ---
const fetchIssLocation = async () => {
    try {
        const response = await fetch('https://api.wheretheiss.at/v1/satellites/25544')
        const data = await response.json()
        issData.value = {
            latitude: data.latitude.toFixed(4),
            longitude: data.longitude.toFixed(4),
            altitude: Math.round(data.altitude * 0.621371), // km to miles
            velocity: Math.round(data.velocity * 0.621371).toLocaleString(), // kph to mph
            visibility: data.visibility
        }
    } catch (e) {
        console.error('Failed to fetch ISS data', e)
    }
}

watch(activeView, (newView) => {
    if (newView === 'astronomy') {
        fetchIssLocation()
        if (!issInterval) issInterval = setInterval(fetchIssLocation, 10000)
    } else {
        if (issInterval) {
            clearInterval(issInterval)
            issInterval = null
        }
    }
})

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
    fetchWildfires()
    fetchHurricanes()
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

watch(showWildfires, () => {
    fetchWildfires()
})

watch(showHurricanes, () => {
    fetchHurricanes()
})

watch([showPrecipitation, showWarnings], () => {
    updateRadarLayers()
    fetchWarnings()
})

const zoomIn = () => map?.zoomIn()
const zoomOut = () => map?.zoomOut()
const togglePlayback = () => (isPlaying.value = !isPlaying.value)

const weatherIcon = (condition) => {
    switch (condition?.toLowerCase()) {
        case 'clear':
            return Sun
        case 'clouds':
            return Cloud
        case 'rain':
        case 'drizzle':
            return CloudRain
        case 'thunderstorm':
            return CloudLightning
        case 'snow':
            return CloudRain // Could add Snowflake icon
        default:
            return Sun
    }
}

const getMoonPhase = (dayOrTimestamp) => {
    if (!dayOrTimestamp) return { emoji: '🌑', label: 'New Moon' }

    let phase
    const dt =
        typeof dayOrTimestamp === 'object' ? dayOrTimestamp.dt : dayOrTimestamp

    if (
        typeof dayOrTimestamp === 'object' &&
        dayOrTimestamp.moon_phase !== undefined
    ) {
        phase = dayOrTimestamp.moon_phase
    } else {
        const newMoon = new Date('2000-01-06T18:14:00Z').getTime()
        const diff = dt * 1000 - newMoon
        const days = diff / (1000 * 60 * 60 * 24)
        phase =
            (((days % 29.53058868) + 29.53058868) % 29.53058868) / 29.53058868 // Handle negative diffs safely
    }

    if (phase < 0.0625) return { emoji: '🌑', label: 'New Moon' }
    if (phase < 0.1875) return { emoji: '🌒', label: 'Waxing Crescent' }
    if (phase < 0.3125) return { emoji: '🌓', label: 'First Quarter' }
    if (phase < 0.4375) return { emoji: '🌔', label: 'Waxing Gibbous' }
    if (phase < 0.5625) return { emoji: '🌕', label: 'Full Moon' }
    if (phase < 0.6875) return { emoji: '🌖', label: 'Waning Gibbous' }
    if (phase < 0.8125) return { emoji: '🌗', label: 'Last Quarter' }
    if (phase < 0.9375) return { emoji: '🌘', label: 'Waning Crescent' }
    return { emoji: '🌑', label: 'New Moon' }
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
const formatTimeMinutes = (timestamp) => {
    if (!timestamp) return '--'
    return new Date(timestamp * 1000).toLocaleTimeString([], {
        hour: 'numeric',
        minute: '2-digit'
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
        if (issInterval) clearInterval(issInterval)
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

                    <div class="mt-8 grid grid-cols-7 gap-4">
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
                        <div @click="isAqiModalOpen = true" class="flex flex-col items-center gap-1 cursor-pointer transition-colors hover:text-green-500">
                            <Leaf class="h-5 w-5 opacity-40 hover:opacity-100" />
                            <span class="text-sm font-black">{{ airQuality !== null ? airQuality : '--' }}</span>
                            <span class="text-[10px] font-bold uppercase opacity-30">AQI</span>
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
                        <div class="flex flex-col items-center gap-1">
                            <Moon class="h-5 w-5 opacity-40" />
                            <span
                                class="text-lg leading-none"
                                :title="
                                    getMoonPhase(weatherData?.current?.dt).label
                                "
                            >
                                {{
                                    getMoonPhase(weatherData?.current?.dt).emoji
                                }}
                            </span>
                            <span
                                class="text-[10px] font-bold uppercase opacity-30"
                                >Moon</span
                            >
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <Sunrise class="h-5 w-5 text-orange-400 opacity-60" />
                            <span class="text-sm font-black">{{ formatTimeMinutes(weatherData?.current?.sunrise) }}</span>
                            <span class="text-[10px] font-bold uppercase opacity-30">Sunrise</span>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <Sunset class="h-5 w-5 text-orange-500 opacity-60" />
                            <span class="text-sm font-black">{{ formatTimeMinutes(weatherData?.current?.sunset) }}</span>
                            <span class="text-[10px] font-bold uppercase opacity-30">Sunset</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Map and Sidebar Container -->
            <div :class="['flex h-full min-h-[400px] max-h-[80vh] gap-4', isFullMap ? 'col-span-full' : 'lg:col-span-8']">
                <!-- Map/Astronomy Container -->
                <Card
                    class="group flex-1 relative overflow-hidden rounded-[2.5rem] border-none bg-black/20 shadow-none backdrop-blur-3xl"
                >
                <!-- WEATHER VIEW -->
                <div 
                    v-show="activeView === 'weather'"
                    class="absolute inset-0 w-full h-full"
                >
                    <div v-if="location" class="absolute inset-0 z-0">
                        <div
                            ref="mapContainer"
                            class="h-full w-full bg-[#111]"
                        ></div>
                    </div>
                </div>

                <!-- ASTRONOMY VIEW -->
                <div v-show="activeView === 'astronomy'" class="absolute inset-0 w-full h-full bg-[#030712] overflow-hidden">
                    <!-- Virtual Sky iFrame -->
                    <iframe 
                        class="absolute inset-0 w-full h-full border-0 pointer-events-auto"
                        src="https://virtualsky.lco.global/embed/index.html?longitude=-81.9498&latitude=28.0395&projection=stereo&constellations=true&constellationlabels=true&meteorshowers=true&showstarlabels=true&live=true&az=180&keyboard=false&showdate=false&showposition=false"
                        scrolling="no"
                    ></iframe>


                </div>

                <!-- Floating Layer Controls (Bottom Left) -->
                <div
                    v-show="activeView === 'weather'"
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
                            @click="showWarnings = !showWarnings"
                            :class="[
                                'h-12 w-12 rounded-xl transition-all',
                                showWarnings
                                    ? 'bg-red-500 text-white'
                                    : 'text-white/40 hover:bg-white/10',
                            ]"
                            ><AlertTriangle class="h-5 w-5"
                        /></Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="showWildfires = !showWildfires"
                            :class="[
                                'h-12 w-12 rounded-xl transition-all',
                                showWildfires
                                    ? 'bg-orange-500 text-white'
                                    : 'text-white/40 hover:bg-white/10',
                            ]"
                            ><Flame class="h-5 w-5"
                        /></Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="showHurricanes = !showHurricanes"
                            :class="[
                                'h-12 w-12 rounded-xl transition-all',
                                showHurricanes
                                    ? 'bg-red-500/20 text-red-500'
                                    : 'text-white/40 hover:bg-white/10',
                            ]"
                            ><Tornado class="h-5 w-5"
                        /></Button>
                    </div>
                </div>

                <!-- Right Side Controls Container -->
                <div class="absolute inset-y-6 right-6 z-[1000] flex flex-col items-end pointer-events-none">
                    
                    <!-- Top Controls -->
                    <div class="flex flex-col gap-2 pointer-events-auto items-end">
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="isFullMap = !isFullMap"
                            class="h-12 w-12 rounded-xl border border-white/10 bg-black/20 text-white backdrop-blur-md hover:bg-black/40 shrink-0"
                        >
                            <Minimize2 v-if="isFullMap" class="h-5 w-5" />
                            <Maximize2 v-else class="h-5 w-5" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="fetchWeather(true)"
                            class="h-12 w-12 rounded-xl border border-white/10 bg-black/20 text-white backdrop-blur-md hover:bg-black/40 shrink-0"
                        >
                            <RefreshCw
                                :class="[
                                    'h-5 w-5',
                                    isSyncing ? 'animate-spin' : '',
                                ]"
                            />
                        </Button>
                        <div
                            class="mt-4 flex flex-col gap-1 overflow-hidden rounded-xl border border-white/10 bg-black/20 backdrop-blur-md shrink-0"
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

                    <!-- Center Space for Warnings -->
                    <div class="flex-1 flex flex-col justify-center py-4 pointer-events-none min-h-0">
                        <Button 
                            v-if="!isSidebarOpen && alerts.length > 0" 
                            @click="isSidebarOpen = true"
                            class="h-12 shrink-0 rounded-full bg-red-500 hover:bg-red-600 text-white shadow-2xl flex items-center gap-3 px-6 font-black tracking-widest uppercase transition-transform hover:scale-105 active:scale-95 pointer-events-auto"
                        >
                            <AlertTriangle class="h-5 w-5 animate-pulse shrink-0" />
                            {{ alerts.length }}
                        </Button>
                    </div>

                    <!-- Bottom Animation Controls -->
                    <div
                        v-show="activeView === 'weather'"
                        class="flex items-center gap-3 rounded-full border border-white/10 bg-black/40 p-1.5 shadow-2xl backdrop-blur-xl pointer-events-auto shrink-0"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="togglePlayback"
                            class="h-10 w-10 rounded-full bg-white/10 text-white hover:bg-white/20 shrink-0"
                        >
                            <Pause v-if="isPlaying" class="h-4 w-4 fill-current shrink-0" />
                            <Play v-else class="ml-0.5 h-4 w-4 fill-current shrink-0" />
                        </Button>
                        <div
                            v-if="frames[currentFrameIndex]"
                            class="flex items-center gap-2 pr-4 shrink-0"
                        >
                            <div
                                class="bg-primary h-1.5 w-1.5 animate-pulse rounded-full shrink-0"
                            ></div>
                            <span
                                class="text-[10px] font-black tracking-[0.2em] text-white/90 uppercase tabular-nums whitespace-nowrap"
                                >{{
                                    formatFrameTime(frames[currentFrameIndex])
                                }}</span
                            >
                        </div>
                    </div>
                </div>
            </Card>
            
            <!-- Collapsible Warnings Sidebar -->
            <div 
                :class="[
                    'transition-all duration-500 ease-in-out border-white/10 bg-black/5 dark:bg-white/5 backdrop-blur-xl z-50 rounded-[2.5rem] shrink-0',
                    isSidebarOpen ? 'w-80 md:w-96 p-6 opacity-100 overflow-y-auto custom-scrollbar border' : 'w-0 opacity-0 overflow-hidden p-0 border-0'
                ]"
            >
                <div class="w-full min-w-[280px]">
                    <div class="flex items-center justify-between mb-8">
                        <h3 class="text-xl font-black uppercase tracking-widest text-red-500 flex items-center gap-2">
                            <AlertTriangle class="h-5 w-5" /> Warnings
                        </h3>
                        <Button variant="ghost" size="icon" @click="isSidebarOpen = false" class="rounded-full hover:bg-black/10 dark:hover:bg-white/10">
                            <X class="h-5 w-5" />
                        </Button>
                    </div>
                    
                    <div class="space-y-4">
                        <div
                            v-for="alert in alerts"
                            :key="alert.id"
                            @click="selectedWarning = alert.properties; isWarningModalOpen = true"
                            class="cursor-pointer rounded-2xl bg-red-500/10 border border-red-500/20 p-5 transition-transform hover:scale-105 active:scale-95 shadow-lg"
                        >
                            <h4 class="font-black uppercase text-red-500 tracking-tight leading-tight mb-2">{{ alert.properties.event }}</h4>
                            <p class="text-sm font-bold opacity-80 line-clamp-4">{{ alert.properties.headline }}</p>
                        </div>
                    </div>
                </div>
            </div>

            </div>
        </div>

        <!-- Hidden when full map -->
        <template v-if="!isFullMap">
            <div class="grid min-h-0 flex-1 grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Weather Cards -->
                <template v-if="activeView === 'weather'">
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
                                            class="w-32 text-left text-sm font-bold capitalize opacity-60"
                                            >{{ day.weather[0]?.description }}</span
                                        >
                                        <span
                                            class="text-xl opacity-80"
                                            :title="getMoonPhase(day).label"
                                            >{{ getMoonPhase(day).emoji }}</span
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
                </template>

                <!-- Astronomy Cards -->
                <template v-if="activeView === 'astronomy'">
                    <!-- ISS Live Tracker -->
                    <Card class="flex flex-col min-h-[400px] rounded-[2.5rem] border-none bg-[#030712]/80 backdrop-blur-3xl text-white shadow-none p-8 dark:bg-black/60">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="p-3 bg-blue-500/20 rounded-2xl border border-blue-500/50 text-blue-400">
                                <Satellite class="w-8 h-8" />
                            </div>
                            <div>
                                <h3 class="font-black text-2xl leading-tight text-white">ISS Tracker</h3>
                                <p class="text-white/50 text-sm font-bold uppercase tracking-wider">Live Position</p>
                            </div>
                        </div>

                        <div v-if="issData" class="space-y-4 flex-1 flex flex-col justify-center">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-white/5 rounded-2xl p-4 border border-white/5">
                                    <div class="text-white/40 text-xs font-bold uppercase tracking-wider mb-2">Latitude</div>
                                    <div class="font-mono text-xl text-white">{{ issData.latitude }}&deg;</div>
                                </div>
                                <div class="bg-white/5 rounded-2xl p-4 border border-white/5">
                                    <div class="text-white/40 text-xs font-bold uppercase tracking-wider mb-2">Longitude</div>
                                    <div class="font-mono text-xl text-white">{{ issData.longitude }}&deg;</div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-white/5 rounded-2xl p-4 border border-white/5">
                                    <div class="text-white/40 text-xs font-bold uppercase tracking-wider mb-2">Altitude</div>
                                    <div class="font-mono text-xl text-white">{{ issData.altitude }} mi</div>
                                </div>
                                <div class="bg-white/5 rounded-2xl p-4 border border-white/5">
                                    <div class="text-white/40 text-xs font-bold uppercase tracking-wider mb-2">Velocity</div>
                                    <div class="font-mono text-xl text-white">{{ issData.velocity }} mph</div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between bg-white/5 rounded-2xl p-4 border border-white/5 mt-auto">
                                <div class="text-white/40 text-xs font-bold uppercase tracking-wider">Visibility</div>
                                <div class="flex items-center gap-3">
                                    <span class="relative flex h-3 w-3">
                                        <span :class="['animate-ping absolute inline-flex h-full w-full rounded-full opacity-75', issData.visibility === 'daylight' ? 'bg-yellow-400' : 'bg-indigo-400']"></span>
                                        <span :class="['relative inline-flex rounded-full h-3 w-3', issData.visibility === 'daylight' ? 'bg-yellow-500' : 'bg-indigo-500']"></span>
                                    </span>
                                    <span class="text-lg font-bold capitalize text-white">{{ issData.visibility }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="animate-pulse space-y-4 py-8 flex-1 flex flex-col justify-center">
                            <div class="h-12 bg-white/10 rounded-xl"></div>
                            <div class="h-12 bg-white/10 rounded-xl"></div>
                            <div class="h-12 bg-white/10 rounded-xl"></div>
                        </div>
                    </Card>

                    <!-- Next Space Coast Launch -->
                    <Card class="flex flex-col min-h-[400px] relative overflow-hidden rounded-[2.5rem] border-none bg-[#030712]/80 backdrop-blur-3xl text-white shadow-none p-8 dark:bg-black/60">
                        <div v-if="launchData?.image" class="absolute inset-0 opacity-20 bg-cover bg-center mix-blend-screen" :style="{ backgroundImage: `url(${launchData.image})` }"></div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/80 to-transparent"></div>
                        
                        <div class="relative z-10 flex-1 flex flex-col">
                            <div class="flex items-center gap-4 mb-6">
                                <div class="p-3 bg-orange-500/20 rounded-2xl border border-orange-500/50 text-orange-400">
                                    <Rocket class="w-8 h-8" />
                                </div>
                                <div>
                                    <h3 class="font-black text-2xl leading-tight text-white">Space Coast</h3>
                                    <p class="text-white/50 text-sm font-bold uppercase tracking-wider">Next Launch</p>
                                </div>
                            </div>

                            <div v-if="launchData" class="flex-1 flex flex-col justify-end">
                                <div class="inline-block px-3 py-1.5 bg-white/10 border border-white/20 rounded-lg text-xs font-bold uppercase tracking-widest text-white/80 w-max mb-3">
                                    {{ launchData.provider }}
                                </div>
                                <h4 class="text-3xl font-black leading-tight mb-6 text-white">{{ launchData.name }}</h4>
                                
                                <div class="space-y-3 bg-black/40 p-6 rounded-[2rem] border border-white/5">
                                    <div class="flex items-center justify-between border-b border-white/5 pb-3">
                                        <span class="text-sm text-white/50 font-bold uppercase tracking-wider">Date</span>
                                        <span class="text-lg font-bold text-white">{{ launchData.date }}</span>
                                    </div>
                                    <div class="flex items-center justify-between border-b border-white/5 pb-3">
                                        <span class="text-sm text-white/50 font-bold uppercase tracking-wider">Time</span>
                                        <span class="text-lg font-bold text-orange-400">{{ launchData.time }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-white/50 font-bold uppercase tracking-wider">Pad</span>
                                        <span class="text-sm font-bold text-white/80 text-right truncate max-w-[200px]" :title="launchData.pad">{{ launchData.pad }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="animate-pulse space-y-4 mt-auto">
                                <div class="h-6 bg-white/10 rounded-xl w-1/3"></div>
                                <div class="h-10 bg-white/10 rounded-xl w-full"></div>
                                <div class="h-32 bg-white/10 rounded-2xl w-full mt-6"></div>
                            </div>
                        </div>
                    </Card>
                </template>
            </div>
        </template>

        <!-- Warning Details Modal -->
        <Dialog v-model:open="isWarningModalOpen">
            <DialogContent
                class="max-w-2xl max-h-[90vh] flex flex-col overflow-hidden rounded-[2.5rem] border-none bg-white/90 p-8 shadow-2xl backdrop-blur-2xl dark:bg-[#111]/90"
            >
                <DialogHeader v-if="selectedWarning" class="shrink-0">
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
                <div v-if="selectedWarning" class="mt-6 space-y-4 overflow-y-auto custom-scrollbar pr-4 flex-1">
                    <div class="bg-red-500/10 rounded-2xl p-6 border border-red-500/20 shrink-0">
                        <h4 class="mb-3 text-xs font-black tracking-widest text-red-500 uppercase opacity-80 flex items-center gap-2">
                            <AlertTriangle class="h-4 w-4" /> Raw Bulletin Text
                        </h4>
                        <div class="custom-scrollbar max-h-[40vh] overflow-y-auto">
                            <p class="text-xs leading-relaxed font-mono whitespace-pre-wrap text-foreground/90">
                                {{ selectedWarning.description }}
                            </p>
                        </div>
                    </div>
                    <div v-if="selectedWarning.instruction" class="bg-muted/30 rounded-2xl p-6">
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
                <div class="mt-6 flex justify-end shrink-0">
                    <Button
                        @click="isWarningModalOpen = false"
                        class="h-12 rounded-xl px-8 font-bold"
                        >Dismiss</Button
                    >
                </div>
            </DialogContent>
        </Dialog>

        <!-- AQI Details Modal -->
        <Dialog v-model:open="isAqiModalOpen">
            <DialogContent
                class="max-w-2xl rounded-[2.5rem] border-none bg-white/90 p-8 shadow-2xl backdrop-blur-2xl dark:bg-[#111]/90"
            >
                <DialogHeader>
                    <div
                        class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                    >
                        <Leaf class="h-10 w-10" />
                    </div>
                    <DialogTitle
                        class="text-3xl font-black tracking-tight uppercase"
                        >Air Quality Index</DialogTitle
                    >
                    <DialogDescription
                        class="text-foreground/80 mt-2 text-lg leading-relaxed font-bold"
                    >
                        The AQI is an index for reporting daily air quality. It tells you how clean or polluted your air is, and what associated health effects might be a concern for you.
                    </DialogDescription>
                </DialogHeader>

                <div class="mt-6 space-y-3">
                    <div class="flex items-center gap-4 rounded-2xl p-4 bg-green-500/10 border border-green-500/20">
                        <div class="w-16 text-center font-black text-green-500">0-50</div>
                        <div class="flex-1">
                            <div class="font-bold text-green-600 dark:text-green-400">Good</div>
                            <div class="text-sm opacity-80">Air quality is satisfactory, and air pollution poses little or no risk.</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 rounded-2xl p-4 bg-yellow-500/10 border border-yellow-500/20">
                        <div class="w-16 text-center font-black text-yellow-600 dark:text-yellow-500">51-100</div>
                        <div class="flex-1">
                            <div class="font-bold text-yellow-700 dark:text-yellow-500">Moderate</div>
                            <div class="text-sm opacity-80">Acceptable quality. There may be a risk for some people, particularly those who are unusually sensitive to air pollution.</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 rounded-2xl p-4 bg-orange-500/10 border border-orange-500/20">
                        <div class="w-16 text-center font-black text-orange-500">101-150</div>
                        <div class="flex-1">
                            <div class="font-bold text-orange-600 dark:text-orange-400">Unhealthy for Sensitive Groups</div>
                            <div class="text-sm opacity-80">Members of sensitive groups may experience health effects. The general public is less likely to be affected.</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 rounded-2xl p-4 bg-red-500/10 border border-red-500/20">
                        <div class="w-16 text-center font-black text-red-500">151-200</div>
                        <div class="flex-1">
                            <div class="font-bold text-red-600 dark:text-red-400">Unhealthy</div>
                            <div class="text-sm opacity-80">Some members of the general public may experience health effects; members of sensitive groups may experience more serious health effects.</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 rounded-2xl p-4 bg-purple-500/10 border border-purple-500/20">
                        <div class="w-16 text-center font-black text-purple-500">201-300</div>
                        <div class="flex-1">
                            <div class="font-bold text-purple-600 dark:text-purple-400">Very Unhealthy</div>
                            <div class="text-sm opacity-80">Health alert: The risk of health effects is increased for everyone.</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 rounded-2xl p-4 bg-[#800000]/10 border border-[#800000]/20">
                        <div class="w-16 text-center font-black text-[#800000] dark:text-[#ff6666]">301+</div>
                        <div class="flex-1">
                            <div class="font-bold text-[#800000] dark:text-[#ff6666]">Hazardous</div>
                            <div class="text-sm opacity-80">Health warning of emergency conditions: everyone is more likely to be affected.</div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end">
                    <Button
                        @click="isAqiModalOpen = false"
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
