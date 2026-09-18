<script setup>
import { ref, onMounted, onUnmounted, computed, watch, inject, nextTick } from 'vue'
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog'
import {
    Car,
    MapPin,
    Home,
    RefreshCw,
    Settings,
    Navigation,
    GraduationCap,
    Building2,
    ShoppingCart,
    AlertTriangle,
    Plus,
    Trash2,
    X,
    Clock,
    Maximize2,
} from 'lucide-vue-next'

const homeAddress = inject('homeAddress', ref({ address: '', lat: null, lon: null }))

const destinations = ref([])
const etas = ref([])
const isLoading = ref(true)
const isRefreshing = ref(false)
const lastRefreshed = ref(null)
const map = ref(null)
const mapContainer = ref(null)
const routeLayers = ref([])
const markerLayers = ref([])
let refreshIntervalTimer = null

const isFiniteNumber = (v) => typeof v === 'number' && Number.isFinite(v)

const hasRoutePolylines = computed(() =>
    etas.value.some((eta) => eta.route?.polyline?.length),
)

const REFRESH_INTERVAL_MS = 15 * 60 * 1000

const DEFAULT_ZOOM = 13
const MAP_VIEW_STORAGE_KEY = 'commute_map_view'
const SAVE_VIEW_DEBOUNCE_MS = 500

const ICON_MAP = { GraduationCap, Building2, ShoppingCart, MapPin }

const resolveIcon = (name) => ICON_MAP[name] || MapPin

const hasHome = computed(() => !!(homeAddress.value?.lat && homeAddress.value?.lon))

const lastRefreshedLabel = computed(() => {
    if (!lastRefreshed.value) return 'Never'
    const now = new Date()
    const diffMs = now - lastRefreshed.value
    const mins = Math.floor(diffMs / 60000)
    if (mins < 1) return 'Just now'
    if (mins < 60) return `${mins} min ago`
    const hrs = Math.floor(mins / 60)
    return `${hrs}h ${mins % 60}m ago`
})

const arrivalLabel = (eta) => {
    const seconds = eta.route?.travel_time_seconds
    if (!seconds && seconds !== 0) return ''
    return new Date(Date.now() + seconds * 1000).toLocaleTimeString([], {
        hour: 'numeric',
        minute: '2-digit',
    })
}

const needsRefresh = () => {
    if (!lastRefreshed.value) return true
    return Date.now() - lastRefreshed.value.getTime() > REFRESH_INTERVAL_MS
}

const fetchDestinations = async () => {
    try {
        const res = await axios.get('/api/commute/destinations')
        destinations.value = res.data
    } catch (e) {
        console.error('Failed to fetch destinations:', e)
    }
}

const fetchEtas = async (force = false) => {
    if (!hasHome.value) {
        isLoading.value = false
        return
    }
    if (force) isRefreshing.value = true
    try {
        const params = { home_lat: homeAddress.value.lat, home_lon: homeAddress.value.lon }
        const res = force
            ? await axios.post('/api/commute/refresh', null, { params })
            : await axios.get('/api/commute/etas', { params })
        etas.value = res.data
        lastRefreshed.value = new Date()
        await nextTick()
        drawRoutes()
    } catch (e) {
        console.error('Failed to fetch ETAs:', e)
    } finally {
        isLoading.value = false
        isRefreshing.value = false
    }
}

const handleVisibility = () => {
    if (document.visibilityState === 'visible' && needsRefresh()) {
        fetchEtas(false)
    }
}

/* ----------------------------- Map rendering ----------------------------- */

const homeIcon = L.divIcon({
    html: `
        <div class="relative">
            <div class="absolute -inset-2 bg-white/60 dark:bg-black/40 rounded-full animate-pulse"></div>
            <div class="relative flex items-center justify-center w-10 h-10 rounded-full bg-indigo-600 text-white shadow-xl border-2 border-white dark:border-black">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
        </div>
    `,
    className: 'custom-home-icon',
    iconSize: [40, 40],
    iconAnchor: [20, 20],
})

const destIcon = (color = '#0ea5e9') => L.divIcon({
    html: `
        <div class="relative">
            <div class="absolute -inset-1.5 bg-white/50 dark:bg-black/40 rounded-full animate-pulse"></div>
            <div class="relative flex items-center justify-center w-9 h-9 rounded-full text-white shadow-lg border-2 border-white dark:border-black" style="background: ${color}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16.3 3H7.7L1.5 13.2a2 2 0 0 0 1.7 3h17.6a2 2 0 0 0 1.7-3z"/><path d="M12 13v8"/><path d="M8 21h8"/></svg>
            </div>
        </div>
    `,
    className: 'custom-dest-icon',
    iconSize: [36, 36],
    iconAnchor: [18, 18],
})

const initMap = () => {
    if (!mapContainer.value || map.value) return
    if (!hasHome.value) return

    const home = homeAddress.value
    const saved = loadSavedView()
    const useSaved = saved && savedViewMatchesHome(saved)
    const center = useSaved ? [saved.lat, saved.lng] : [home.lat, home.lon]
    const zoom = useSaved ? saved.zoom : DEFAULT_ZOOM

    map.value = L.map(mapContainer.value, {
        zoomControl: false,
        attributionControl: false,
    }).setView(center, zoom)

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        referrerPolicy: 'origin',
    }).addTo(map.value)

    L.marker([homeAddress.value.lat, homeAddress.value.lon], {
        icon: homeIcon,
        zIndexOffset: 2000,
    }).addTo(map.value)

    map.value.on('moveend', schedulePersist)
    map.value.on('zoomend', schedulePersist)
    map.value.on('movestart', () => {
        skipNextSave = false
    })
}

/* ------------------------- Map view persistence --------------------------- */

const loadSavedView = () => {
    try {
        const raw = localStorage.getItem(MAP_VIEW_STORAGE_KEY)
        if (!raw) return null
        const parsed = JSON.parse(raw)
        if (
            isFiniteNumber(parsed?.lat) &&
            isFiniteNumber(parsed?.lng) &&
            isFiniteNumber(parsed?.zoom)
        ) {
            return parsed
        }
    } catch (e) {
        // Corrupt saved view — fall back to the default
    }
    return null
}

const savedViewMatchesHome = (saved) => {
    const home = homeAddress.value
    if (!isFiniteNumber(home?.lat) || !isFiniteNumber(home?.lon)) return false
    if (!isFiniteNumber(saved?.home_lat) || !isFiniteNumber(saved?.home_lon)) return false
    const latDiff = Math.abs(home.lat - saved.home_lat)
    const lonDiff = Math.abs(home.lon - saved.home_lon)
    return latDiff < 0.0001 && lonDiff < 0.0001
}

let saveViewTimer = null
let skipNextSave = false

const schedulePersist = () => {
    if (saveViewTimer) clearTimeout(saveViewTimer)
    saveViewTimer = setTimeout(persistMapView, SAVE_VIEW_DEBOUNCE_MS)
}

const persistMapView = () => {
    // Programmatic fits (auto-fit) shouldn't become the saved view; they're
    // temporary. The next manual gesture clears this and resumes saving.
    if (skipNextSave) {
        skipNextSave = false
        return
    }
    const center = map.value?.getCenter()
    if (!center || !isFiniteNumber(center.lat) || !isFiniteNumber(center.lng)) return
    localStorage.setItem(
        MAP_VIEW_STORAGE_KEY,
        JSON.stringify({
            lat: center.lat,
            lng: center.lng,
            zoom: map.value.getZoom(),
            home_lat: homeAddress.value?.lat ?? null,
            home_lon: homeAddress.value?.lon ?? null,
        }),
    )
}

const focusHome = () => {
    if (!map.value || !hasHome.value) return
    map.value.setView([homeAddress.value.lat, homeAddress.value.lon], DEFAULT_ZOOM)
}

const autoFitRoutes = () => {
    if (!map.value || !hasRoutePolylines.value) return
    let minLat = Infinity
    let minLng = Infinity
    let maxLat = -Infinity
    let maxLng = -Infinity
    const addPoint = (lat, lng) => {
        if (!isFiniteNumber(lat) || !isFiniteNumber(lng)) return
        minLat = Math.min(minLat, lat)
        minLng = Math.min(minLng, lng)
        maxLat = Math.max(maxLat, lat)
        maxLng = Math.max(maxLng, lng)
    }
    if (hasHome.value) addPoint(homeAddress.value.lat, homeAddress.value.lon)
    etas.value.forEach((eta) =>
        eta.route?.polyline?.forEach((p) => addPoint(p.latitude, p.longitude)),
    )
    if (!Number.isFinite(minLat)) return
    skipNextSave = true
    map.value.fitBounds([
        [minLat, minLng],
        [maxLat, maxLng],
    ])
}

watch(homeAddress, (val) => {
    if (!map.value) {
        initMap()
    } else if (val?.lat && val?.lon) {
        // Home moved — the saved view is anchored to the old home, so clear it
        // and re-center on the new home rather than restoring a stale view.
        const saved = loadSavedView()
        if (saved && !savedViewMatchesHome(saved)) {
            localStorage.removeItem(MAP_VIEW_STORAGE_KEY)
            map.value.setView([val.lat, val.lon], DEFAULT_ZOOM)
        }
    }
    if (val?.lat && val?.lon) fetchEtas(false)
}, { deep: true })

const drawRoutes = () => {
    if (!map.value) return

    routeLayers.value.forEach((l) => map.value.removeLayer(l))
    routeLayers.value = []
    markerLayers.value.forEach((l) => map.value.removeLayer(l))
    markerLayers.value = []

    etas.value.forEach((eta, index) => {
        const route = eta.route
        if (!route?.polyline?.length) return

        const points = route.polyline.map((p) => [p.latitude, p.longitude])
        const delayMin = route.traffic_delay_minutes || 0
        const color = delayMin >= 5 ? '#ef4444' : delayMin >= 2 ? '#f59e0b' : '#10b981'

        const line = L.polyline(points, {
            color,
            weight: 6,
            opacity: 0.85,
            lineCap: 'round',
            lineJoin: 'round',
        }).addTo(map.value)
        routeLayers.value.push(line)

        markerLayers.value.push(
            L.marker([eta.destination.lat, eta.destination.lon], {
                icon: destIcon(COLORS[index % COLORS.length]),
                zIndexOffset: 1000 + index,
            }).addTo(map.value)
        )
    })
}

const COLORS = ['#0ea5e9', '#8b5cf6', '#f59e0b', '#10b981', '#ef4444', '#ec4899']

/* ----------------------- Destination management -------------------------- */

const isDestDialogOpen = ref(false)
const editingDest = ref(null)
const destForm = ref({ name: '', address: '' })
const isSavingDest = ref(false)
const destError = ref('')

const openAddDest = () => {
    editingDest.value = null
    destForm.value = { name: '', address: '' }
    destError.value = ''
    isDestDialogOpen.value = true
}

const openEditDest = (dest) => {
    editingDest.value = dest
    destForm.value = { name: dest.name, address: dest.address }
    destError.value = ''
    isDestDialogOpen.value = true
}

const saveDest = async () => {
    if (!destForm.value.name.trim() || !destForm.value.address.trim()) {
        destError.value = 'Name and address are required'
        return
    }
    isSavingDest.value = true
    destError.value = ''
    try {
        if (editingDest.value?.id) {
            await axios.put(`/api/commute/destinations/${editingDest.value.id}`, destForm.value)
        } else {
            await axios.post('/api/commute/destinations', destForm.value)
        }
        isDestDialogOpen.value = false
        await fetchDestinations()
        await fetchEtas(true)
    } catch (e) {
        destError.value = e.response?.data?.message || 'Failed to save destination'
    } finally {
        isSavingDest.value = false
    }
}

const deleteDest = async (dest) => {
    try {
        await axios.delete(`/api/commute/destinations/${dest.id}`)
        if (editingDest.value?.id === dest.id) {
            isDestDialogOpen.value = false
        }
        await fetchDestinations()
        await fetchEtas(true)
    } catch (e) {
        console.error('Failed to delete destination:', e)
    }
}

/* ----------------------------- Lifecycle --------------------------------- */

onMounted(async () => {
    initMap()
    await fetchDestinations()
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
    if (map.value) {
        map.value.remove()
        map.value = null
    }
})
</script>

<template>
    <div class="w-full h-full flex flex-col overflow-hidden bg-white dark:bg-black">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 pt-5 pb-4 shrink-0 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <div class="bg-sky-500/15 text-sky-500 flex h-12 w-12 items-center justify-center rounded-2xl">
                    <Car class="h-6 w-6" />
                </div>
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">Commute</h2>
                    <p class="text-xs font-bold uppercase tracking-widest opacity-40 flex items-center gap-1.5">
                        <Clock class="w-3 h-3" />
                        Updated {{ lastRefreshedLabel }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    @click="fetchEtas(true)"
                    :disabled="isRefreshing || !hasHome"
                    class="h-12 rounded-2xl px-5 font-bold gap-2"
                >
                    <RefreshCw :class="['w-4 h-4', isRefreshing ? 'animate-spin' : '']" />
                    {{ isRefreshing ? 'Refreshing…' : 'Refresh' }}
                </Button>
                <Button
                    @click="openAddDest"
                    variant="outline"
                    class="h-12 rounded-2xl px-5 font-bold gap-2"
                >
                    <Settings class="w-4 h-4" />
                    Destinations
                </Button>
            </div>
        </div>

        <!-- Body -->
        <div class="flex-1 min-h-0 flex flex-col lg:flex-row gap-4 px-6 pb-6">
            <!-- Map -->
            <div class="flex-1 min-h-0 min-w-[300px] rounded-3xl overflow-hidden relative bg-slate-100 dark:bg-slate-900 border border-black/5 dark:border-white/10">
                <div ref="mapContainer" class="w-full h-full"></div>
                <div
                    v-if="hasHome"
                    class="absolute top-3 left-3 z-[600] flex gap-2"
                >
                    <Button
                        @click="focusHome"
                        variant="secondary"
                        size="icon-lg"
                        title="Center on home"
                        class="w-12 h-12 rounded-2xl shadow-lg backdrop-blur"
                    >
                        <Home class="w-5 h-5" />
                    </Button>
                    <Button
                        @click="autoFitRoutes"
                        :disabled="!hasRoutePolylines"
                        variant="secondary"
                        size="icon-lg"
                        title="Fit all routes"
                        class="w-12 h-12 rounded-2xl shadow-lg backdrop-blur"
                    >
                        <Maximize2 class="w-5 h-5" />
                    </Button>
                </div>
                <div
                    v-if="!hasHome"
                    class="absolute inset-0 flex items-center justify-center bg-slate-100 dark:bg-slate-900 z-[500]"
                >
                    <div class="text-center px-8 max-w-sm">
                        <Home class="w-14 h-14 mx-auto mb-4 text-sky-500 opacity-60" />
                        <h3 class="text-xl font-black tracking-tight mb-2">Set your home address</h3>
                        <p class="text-sm font-bold opacity-60 mb-6">
                            Open the Settings gear in the header (or the main Settings dialog) and enter your home address to start calculating routes.
                        </p>
                    </div>
                </div>
                <div
                    v-else-if="isLoading"
                    class="absolute inset-0 flex items-center justify-center bg-slate-100/80 dark:bg-slate-900/80 z-[500]"
                >
                    <RefreshCw class="w-10 h-10 text-sky-500 animate-spin" />
                </div>
            </div>

            <!-- ETA Cards -->
            <div class="w-full lg:w-[420px] shrink-0 overflow-y-auto custom-scrollbar flex flex-col gap-3 pr-1">
                <div
                    v-if="!hasHome"
                    class="flex-1 flex flex-col items-center justify-center gap-3 text-center px-8 min-h-[200px]"
                >
                    <AlertTriangle class="w-10 h-10 text-amber-500" />
                    <p class="font-bold text-slate-600 dark:text-slate-400">
                        No home address set. Configure it in the main dashboard Settings → Home Address.
                    </p>
                </div>

                <div
                    v-else-if="isLoading"
                    class="flex-1 flex items-center justify-center gap-3 text-slate-400"
                >
                    <RefreshCw class="w-6 h-6 animate-spin" />
                    <span class="font-bold">Calculating routes…</span>
                </div>

                <template v-else>
                    <div
                        v-for="(eta, index) in etas"
                        :key="eta.destination.id"
                        class="flex items-center gap-4 rounded-3xl border border-black/5 dark:border-white/10 bg-white dark:bg-[#111] p-5 shadow-sm"
                    >
                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-2xl shrink-0"
                            :style="{ backgroundColor: COLORS[index % COLORS.length] + '22', color: COLORS[index % COLORS.length] }"
                        >
                            <component :is="resolveIcon(eta.destination.icon)" class="h-7 w-7" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <h3 class="text-lg font-black tracking-tight truncate">{{ eta.destination.name }}</h3>
                                <span
                                    v-if="eta.route && (eta.route.traffic_delay_minutes || 0) >= 2"
                                    class="text-xs font-black px-2.5 py-1 rounded-full uppercase tracking-wider"
                                    :class="(eta.route.traffic_delay_minutes || 0) < 5
                                        ? 'bg-amber-500/10 text-amber-500'
                                        : 'bg-red-500/10 text-red-500'"
                                >
                                    {{ (eta.route.traffic_delay_minutes || 0) < 5 ? 'Slight delay' : 'Major Delay' }}
                                </span>
                            </div>

                            <div v-if="eta.route" class="mt-1 flex items-end gap-2 flex-wrap">
                                <span class="text-4xl font-black tracking-tight leading-none">
                                    {{ eta.route.travel_time_minutes }}
                                    <span class="text-lg font-black opacity-60">min</span>
                                </span>
                                <span class="text-xs font-bold opacity-50 mb-1">
                                    normally {{ eta.route.no_traffic_time_minutes }} min · {{ eta.route.distance_miles }} mi
                                </span>
                            </div>
                            <div v-if="eta.route" class="mt-1 flex items-center gap-1.5">
                                <Clock class="w-4 h-4 text-sky-500" />
                                <span class="text-sm font-black text-sky-600 dark:text-sky-400">
                                    Arrive {{ arrivalLabel(eta) }}
                                </span>
                                <span class="text-xs font-bold opacity-50">if you leave now</span>
                            </div>
                            <div v-else class="mt-1 text-sm font-bold opacity-50">
                                Route unavailable
                            </div>
                        </div>
                    </div>

                    <div v-if="etas.length === 0" class="flex-1 flex flex-col items-center justify-center gap-3 text-center px-8 min-h-[200px]">
                        <Navigation class="w-10 h-10 text-sky-500" />
                        <p class="font-bold text-slate-600 dark:text-slate-400">
                            No destinations yet. Tap "Destinations" to add one — try Lakeland Montessori!
                        </p>
                    </div>
                </template>
            </div>
        </div>

        <!-- Destination Management Dialog -->
        <Dialog v-model:open="isDestDialogOpen">
            <DialogContent class="max-w-lg rounded-[2.5rem] border-none bg-white/95 p-8 shadow-2xl backdrop-blur-2xl dark:bg-[#111]/95">
                <DialogHeader>
                    <div class="flex items-center gap-4">
                        <div class="bg-sky-500/10 text-sky-500 flex h-14 w-14 items-center justify-center rounded-2xl">
                            <MapPin class="h-7 w-7" />
                        </div>
                        <div>
                            <DialogTitle class="text-2xl font-black tracking-tight">
                                {{ editingDest ? 'Edit Destination' : 'Add Destination' }}
                            </DialogTitle>
                            <DialogDescription class="font-bold opacity-60 mt-1">
                                Coordinates are auto-guessed from the address.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div class="grid gap-4 mt-6">
                    <div>
                        <label class="text-xs font-black uppercase tracking-widest opacity-50 mb-2 block">Name</label>
                        <Input
                            v-model="destForm.name"
                            placeholder="e.g. Lakeland Montessori"
                            class="h-12 rounded-xl text-base font-bold"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-black uppercase tracking-widest opacity-50 mb-2 block">Address</label>
                        <Input
                            v-model="destForm.address"
                            placeholder="e.g. 1124 N. Lake Parker Ave, Lakeland, FL 33805"
                            class="h-12 rounded-xl text-base font-bold"
                        />
                    </div>

                    <p v-if="destError" class="text-red-500 text-sm font-bold">{{ destError }}</p>

                    <div class="flex gap-3 mt-2">
                        <Button
                            @click="saveDest"
                            :disabled="isSavingDest"
                            class="flex-1 h-12 rounded-xl font-bold"
                        >
                            <RefreshCw v-if="isSavingDest" class="w-4 h-4 mr-2 animate-spin" />
                            <Plus v-else class="w-4 h-4 mr-2" />
                            {{ editingDest ? 'Save Changes' : 'Add Destination' }}
                        </Button>
                        <Button
                            v-if="editingDest"
                            @click="deleteDest(editingDest)"
                            variant="destructive"
                            class="h-12 rounded-xl font-bold"
                        >
                            <Trash2 class="w-4 h-4" />
                        </Button>
                        <Button
                            @click="isDestDialogOpen = false"
                            variant="ghost"
                            class="h-12 rounded-xl font-bold px-5"
                        >
                            <X class="w-4 h-4" />
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.map-container-host :deep(.leaflet-tile) {
    filter: saturate(0.9);
}
</style>