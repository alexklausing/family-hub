<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue'
import Draggable from 'vuedraggable'
import axios from 'axios'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Button } from '@/components/ui/button'
import { 
    Lock, Unlock, RotateCcw, RefreshCw,
    Calendar, UtensilsCrossed, ChefHat, 
    ShoppingBag, ListTodo, ShoppingCart,
    Clock, Sun, ArrowRight,
    Home, User, CloudSun
} from 'lucide-vue-next'
import CalendarTab from './CalendarTab.vue'
import RecipeBrowser from './recipes/RecipeBrowser.vue'
import ShoppingList from './shopping/ShoppingList.vue'
import WeatherTab from './WeatherTab.vue'

// Orientation Tracking
const isVertical = ref(false)
const updateOrientation = () => {
    isVertical.value = window.innerHeight > window.innerWidth
}

// Layout Management
const isEditMode = ref(false)

// Calendars and Filtering
const availableCalendars = ref([])
const filtersByProfile = ref({}) // Map: { 'Family': [1,2], 'Mom': [1,3] }
const isFilterDialogOpen = ref(false)
const filterProfileName = ref('')

const loadFilters = () => {
    const saved = localStorage.getItem('dashboard_filters_map')
    if (saved) {
        filtersByProfile.value = JSON.parse(saved)
    }
}

const saveFilters = () => {
    localStorage.setItem('dashboard_filters_map', JSON.stringify(filtersByProfile.value))
}

const visibleCalendarIds = computed(() => {
    return filtersByProfile.value[activeProfile.value] || []
})

const toggleCalendar = (id) => {
    const profile = filterProfileName.value || activeProfile.value
    if (!filtersByProfile.value[profile]) {
        filtersByProfile.value[profile] = availableCalendars.value.map(c => c.id)
    }
    
    const index = filtersByProfile.value[profile].indexOf(id)
    if (index > -1) {
        filtersByProfile.value[profile].splice(index, 1)
    } else {
        filtersByProfile.value[profile].push(id)
    }
    saveFilters()
}

const openFilters = (profileName) => {
    filterProfileName.value = profileName
    isFilterDialogOpen.value = true
}

const defaultWidgets = [
    { id: 'header', title: 'Status', cols: 3 },
    { id: 'schedule', title: 'Up Next', cols: 4 },
    { id: 'calendar', title: 'Calendar', component: 'CalendarTab', cols: 7 },
]

const widgets = ref([])

const loadLayout = () => {
    const saved = localStorage.getItem('dashboard_layout_v3')
    if (saved) {
        const savedWidgets = JSON.parse(saved)
        // Reconcile: Ensure all default widgets exist in the saved layout
        const reconciled = [...savedWidgets]
        defaultWidgets.forEach(defaultWidget => {
            if (!reconciled.find(w => w.id === defaultWidget.id)) {
                reconciled.push(defaultWidget)
            }
        })
        widgets.value = reconciled
    } else {
        widgets.value = JSON.parse(JSON.stringify(defaultWidgets))
    }
}

const saveLayout = () => {
    localStorage.setItem('dashboard_layout_v3', JSON.stringify(widgets.value))
}

const resetLayout = () => {
    widgets.value = JSON.parse(JSON.stringify(defaultWidgets))
    saveLayout()
    isEditMode.value = false
}

// Watch for changes to save automatically
watch(widgets, () => {
    saveLayout()
}, { deep: true })

onMounted(() => {
    updateOrientation()
    window.addEventListener('resize', updateOrientation)
    loadLayout()
    loadFilters()
    // Initial fetch handled by CalendarTab @range-changed or fallback
    fetchEvents()
})

onUnmounted(() => {
    window.removeEventListener('resize', updateOrientation)
})

// Profiles
const profiles = [
    { name: 'Family', icon: Home },
    { name: 'Alex', icon: User },
    { name: 'Sarah', icon: User },
    { name: 'Emily', icon: User },
    { name: 'Henry', icon: User },
]
const activeProfile = ref('Family')

// Long Press Logic
let longPressTimer = null
const handleTouchStart = (profileName) => {
    longPressTimer = setTimeout(() => {
        openFilters(profileName)
        longPressTimer = null
    }, 600)
}
const handleTouchEnd = (profileName) => {
    if (longPressTimer) {
        clearTimeout(longPressTimer)
        activeProfile.value = profileName
    }
}

// Events state
const allEvents = ref([])
const isLoading = ref(false)

const filteredEvents = computed(() => {
    if (availableCalendars.value.length === 0) return []
    const visibleIds = visibleCalendarIds.value
    if (visibleIds.length === 0) return []
    return allEvents.value.filter(event => visibleIds.includes(event.calendar_id))
})

const scheduleEvents = computed(() => {
    const now = new Date()
    // Show events that haven't ended yet
    return filteredEvents.value
        .filter(event => {
            const eventDate = new Date(event.end || event.start)
            return eventDate >= now
        })
        .slice(0, 30)
})

const fetchEvents = async (start = null, end = null) => {
    isLoading.value = true
    try {
        const response = await axios.get('/api/events', {
            params: { 
                profile: activeProfile.value,
                start,
                end
            }
        })
        allEvents.value = response.data.events
        availableCalendars.value = response.data.calendars
        
        // Initialize visible filters for this profile if missing
        if (!filtersByProfile.value[activeProfile.value] || filtersByProfile.value[activeProfile.value].length === 0) {
            filtersByProfile.value[activeProfile.value] = availableCalendars.value.map(c => c.id)
            saveFilters()
        }
    } catch (error) {
        console.error('Failed to fetch events:', error)
    } finally {
        isLoading.value = false
    }
}

const isSyncing = ref(false)
const syncCalendars = async () => {
    isSyncing.value = true
    try {
        await axios.post('/api/sync/calendars')
        await fetchEvents()
    } catch (error) {
        console.error('Sync failed:', error)
    } finally {
        isSyncing.value = false
    }
}

// Watch profile change to refetch
watch(activeProfile, () => {
    fetchEvents()
})
</script>

<template>
    <div class="bg-[#f2f2f7] dark:bg-[#000000] h-screen max-h-screen overflow-hidden p-4 lg:p-10 flex flex-col gap-8 font-sans transition-colors duration-500">
        <Tabs default-value="family" class="flex-1 flex flex-col gap-8 min-h-0">
            <!-- iOS Style Unified Header Bar -->
            <div class="flex items-center justify-center gap-6 w-full max-w-6xl mx-auto shrink-0">
                <TabsList class="flex-1 max-w-4xl bg-white/40 dark:bg-white/5 backdrop-blur-2xl border-none shadow-none rounded-3xl h-16 p-1.5">
                    <TabsTrigger value="family" class="flex-1 h-full gap-2 rounded-2xl font-black text-lg data-[state=active]:shadow-none">
                        <Calendar class="w-5 h-5" />
                        <span>Calendar</span>
                    </TabsTrigger>
                    <TabsTrigger value="weather" class="flex-1 h-full gap-2 rounded-2xl font-black text-lg data-[state=active]:shadow-none">
                        <CloudSun class="w-5 h-5" />
                        <span>Weather</span>
                    </TabsTrigger>
                    <TabsTrigger value="meal-plan" class="flex-1 h-full gap-2 rounded-2xl font-black text-lg data-[state=active]:shadow-none">
                        <UtensilsCrossed class="w-5 h-5" />
                        <span>Menu</span>
                    </TabsTrigger>
                    <TabsTrigger value="recipes" class="flex-1 h-full gap-2 rounded-2xl font-black text-lg data-[state=active]:shadow-none">
                        <ChefHat class="w-5 h-5" />
                        <span>Recipes</span>
                    </TabsTrigger>
                    <TabsTrigger value="shopping" class="flex-1 h-full gap-2 rounded-2xl font-black text-lg data-[state=active]:shadow-none">
                        <ShoppingBag class="w-5 h-5" />
                        <span>Shopping</span>
                    </TabsTrigger>
                    <TabsTrigger value="tasks" class="flex-1 h-full gap-2 rounded-2xl font-black text-lg data-[state=active]:shadow-none">
                        <ListTodo class="w-5 h-5" />
                        <span>Tasks</span>
                    </TabsTrigger>
                </TabsList>
                
                <div class="flex items-center gap-3">
                    <Button 
                        variant="ghost" 
                        size="icon" 
                        @click="isEditMode = !isEditMode"
                        :class="['h-16 w-16 rounded-3xl transition-all', isEditMode ? 'bg-orange-500 text-white shadow-none scale-110' : 'bg-white/40 dark:bg-white/5 backdrop-blur-2xl shadow-none']"
                    >
                        <component :is="isEditMode ? Unlock : Lock" class="w-7 h-7" />
                    </Button>
                    <Button v-if="isEditMode" variant="ghost" size="icon" @click="resetLayout" class="h-16 w-16 rounded-3xl bg-white/40 dark:bg-white/5 backdrop-blur-2xl shadow-none">
                        <RotateCcw class="w-7 h-7" />
                    </Button>
                </div>
            </div>

            <TabsContent value="family" class="flex-1 min-h-0 overflow-hidden">
                <Draggable 
                    v-model="widgets" 
                    item-key="id" 
                    handle=".drag-handle"
                    :disabled="!isEditMode"
                    :class="['grid gap-8 h-full min-h-0', isVertical ? 'grid-cols-1' : 'grid-cols-7 grid-rows-[minmax(0,1.2fr)_minmax(0,2fr)]']"
                >
                    <template #item="{ element }">
                        <div :class="[
                            isVertical ? '' : 
                            element.id === 'header' ? 'col-span-3' : 
                            element.id === 'schedule' ? 'col-span-4' : 
                            'col-span-7', 
                            'relative group transition-all duration-300 min-h-0 h-full'
                        ]">
                            <!-- Drag Handle overlay for Edit Mode -->
                            <div v-if="isEditMode" class="drag-handle absolute inset-0 z-50 bg-primary/5 border-4 border-dashed border-white/40 rounded-[2.5rem] cursor-move flex items-center justify-center animate-pulse">
                                <div class="bg-primary/10 p-6 rounded-full backdrop-blur-md">
                                    <Unlock class="w-16 h-16 text-primary opacity-50" />
                                </div>
                            </div>

                            <!-- Status Widget (Square-ish Header) -->
                            <Card v-if="element.id === 'header'" class="rounded-[2.5rem] bg-white/60 dark:bg-white/5 backdrop-blur-3xl border-none shadow-none h-full transition-all overflow-hidden flex flex-col" :class="{ 'scale-[0.98] blur-[2px] grayscale-[0.5]': isEditMode }">
                                <CardContent class="p-6 lg:p-10 pt-4 lg:pt-6 flex flex-col justify-between h-full">
                                    <!-- Clock/Weather Row -->
                                    <div class="flex items-start justify-between">
                                        <div class="flex flex-col gap-1">
                                            <div class="text-7xl lg:text-8xl font-black tracking-tighter tabular-nums leading-none">
                                                {{ new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                            </div>
                                            <div class="text-xl font-bold opacity-40 uppercase tracking-widest mt-1">
                                                {{ new Date().toLocaleDateString([], { weekday: 'long', month: 'short', day: 'numeric' }) }}
                                            </div>
                                        </div>
                                        <div class="flex flex-col items-end text-right">
                                            <Sun class="w-14 h-14 text-yellow-500 drop-shadow-none mb-2" />
                                            <span class="text-5xl font-black leading-none">72°F</span>
                                            <span class="text-sm font-bold opacity-40 uppercase tracking-widest mt-1">Sunny</span>
                                        </div>
                                    </div>

                                    <!-- Profiles Grid -->
                                    <div class="grid grid-cols-5 gap-3 w-full mt-auto">
                                        <div v-for="profile in profiles" :key="profile.name" 
                                             @mousedown="handleTouchStart(profile.name)"
                                             @mouseup="handleTouchEnd(profile.name)"
                                             @touchstart.passive="handleTouchStart(profile.name)"
                                             @touchend.passive="handleTouchEnd(profile.name)"
                                             :class="[
                                                 'flex flex-col items-center justify-center gap-2 p-3 rounded-3xl cursor-pointer transition-all border-none select-none h-24 lg:h-32',
                                                 activeProfile === profile.name ? 'bg-primary/10 scale-105 shadow-none' : 'bg-white/20 dark:bg-white/5 hover:bg-accent/50'
                                             ]">
                                            <component :is="profile.icon" class="w-10 h-10 lg:w-12 lg:h-12 opacity-80" />
                                            <span class="text-[10px] lg:text-xs font-black uppercase tracking-widest opacity-80 text-center">{{ profile.name }}</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Up Next Widget (Square-ish Schedule) -->
                            <Card v-else-if="element.id === 'schedule'" class="flex flex-col shadow-none rounded-[2.5rem] bg-white/60 dark:bg-white/5 backdrop-blur-3xl border-none h-full transition-all overflow-hidden flex flex-col" :class="{ 'scale-[0.98] blur-[2px] grayscale-[0.5]': isEditMode, 'animate-wiggle': isEditMode }">
                                <CardHeader class="p-8 pb-4 shrink-0">
                                    <CardTitle class="text-3xl font-black tracking-tight flex items-center gap-3">
                                        <div class="w-3 h-8 bg-primary rounded-full shadow-none shadow-primary/20"></div>
                                        Up Next
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="flex-1 overflow-y-auto p-8 pt-2 custom-scrollbar">
                                    <div class="space-y-3">
                                        <div v-if="scheduleEvents.length > 0" class="space-y-3">
                                            <div v-for="event in scheduleEvents" :key="event.id" 
                                                 class="group relative flex items-center gap-5 p-5 rounded-[1.75rem] bg-white/40 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 shadow-none hover:shadow-none transition-all cursor-pointer">
                                                <div class="w-1.5 h-10 rounded-full shrink-0 shadow-none" :style="{ backgroundColor: event.calendar?.color || '#3b82f6' }"></div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="font-black text-lg leading-tight tracking-tight truncate">{{ event.title }}</p>
                                                    <p class="text-muted-foreground text-[10px] font-black uppercase tracking-widest mt-0.5 opacity-60">
                                                        <span v-if="new Date(event.start).toDateString() === new Date().toDateString()">Today</span>
                                                        <span v-else>{{ new Date(event.start).toLocaleDateString([], { month: 'short', day: 'numeric' }) }}</span>
                                                        <span v-if="!event.all_day"> • {{ new Date(event.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
                                                    </p>
                                                </div>
                                                <ArrowRight class="w-4 h-4 opacity-20 group-hover:opacity-100 transition-opacity" />
                                            </div>
                                        </div>
                                        <div v-else class="text-center py-12 text-muted-foreground italic bg-accent/10 rounded-[2rem]">
                                            <ShoppingCart class="w-12 h-12 mx-auto opacity-10 mb-4" />
                                            <p class="text-lg font-bold opacity-40 uppercase tracking-tighter">Everything Done</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Full Width Calendar Widget -->
                            <Card v-else-if="element.id === 'calendar'" class="flex flex-col shadow-none rounded-[2.5rem] bg-white/60 dark:bg-white/5 backdrop-blur-3xl border-none h-full transition-all overflow-hidden flex flex-col" :class="{ 'scale-[0.98] blur-[2px] grayscale-[0.5]': isEditMode, 'animate-wiggle': isEditMode }">
                                <CardHeader class="p-8 pb-4 shrink-0">
                                    <CardTitle class="text-3xl lg:text-4xl font-black tracking-tight flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-3 h-10 bg-primary rounded-full shadow-none shadow-primary/20"></div>
                                            Family Hub Calendar
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <div v-if="isLoading || isSyncing" class="text-[10px] font-black uppercase tracking-widest animate-pulse text-muted-foreground bg-accent/50 px-5 py-2 rounded-full border border-white/10">Syncing...</div>
                                            <Button 
                                                variant="outline" 
                                                size="sm" 
                                                @click="syncCalendars" 
                                                :disabled="isSyncing || isLoading"
                                                class="rounded-2xl gap-2 font-black uppercase tracking-widest text-[10px] h-10 px-4"
                                            >
                                                <RefreshCw :class="['w-3 h-3', (isSyncing || isLoading) ? 'animate-spin' : '']" />
                                                Sync Sources
                                            </Button>
                                        </div>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="flex-1 min-h-0 overflow-y-auto p-4 custom-scrollbar">
                                    <CalendarTab 
                                        :events="filteredEvents" 
                                        @range-changed="(r) => fetchEvents(r.start, r.end)"
                                    />
                                </CardContent>
                            </Card>
                        </div>
                    </template>
                </Draggable>
            </TabsContent>

            <TabsContent value="weather" class="flex-1 min-h-0 overflow-hidden">
                <WeatherTab />
            </TabsContent>

            <TabsContent value="meal-plan" class="flex-1 min-h-0">
                <Card class="rounded-[3rem] bg-white/60 dark:bg-white/5 backdrop-blur-3xl border-none h-full flex items-center justify-center text-4xl font-black opacity-20 uppercase italic tracking-tighter">Meal Plan View</Card>
            </TabsContent>
            <TabsContent value="recipes" class="flex-1 min-h-0 overflow-hidden">
                <RecipeBrowser />
            </TabsContent>
            <TabsContent value="shopping" class="flex-1 min-h-0 overflow-hidden">
                <ShoppingList />
            </TabsContent>
            <TabsContent value="tasks" class="flex-1 min-h-0">
                <Card class="rounded-[3rem] bg-white/60 dark:bg-white/5 backdrop-blur-3xl border-none h-full flex items-center justify-center text-4xl font-black opacity-20 uppercase italic tracking-tighter">Task View</Card>
            </TabsContent>
        </Tabs>

        <!-- Calendar Filter Dialog -->
        <Dialog v-model:open="isFilterDialogOpen">
            <DialogContent class="sm:max-w-[500px] rounded-[3rem] p-8 border-none bg-white/95 dark:bg-black/95 backdrop-blur-3xl shadow-none shadow-black/40 animate-in fade-in zoom-in-95 duration-300">
                <DialogHeader class="mb-6">
                    <DialogTitle class="text-4xl font-black tracking-tight">{{ filterProfileName }} Filters</DialogTitle>
                    <DialogDescription class="text-lg font-bold opacity-60">
                        Choose which calendars show up for {{ filterProfileName }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <div v-for="calendar in availableCalendars" :key="calendar.id" 
                         class="flex items-center justify-between p-6 rounded-3xl transition-all cursor-pointer group"
                         :class="filtersByProfile[filterProfileName]?.includes(calendar.id) ? 'bg-primary/5 shadow-none' : 'bg-transparent opacity-40 hover:opacity-100'"
                         @click="toggleCalendar(calendar.id)"
                    >
                        <div class="flex items-center gap-5">
                            <div class="w-5 h-5 rounded-full shadow-none" :style="{ backgroundColor: calendar.color }"></div>
                            <div>
                                <Label :for="'cal-' + calendar.id" class="text-xl font-black cursor-pointer tracking-tight">
                                    {{ calendar.name }}
                                </Label>
                                <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground opacity-70">{{ calendar.provider }} Account</p>
                            </div>
                        </div>
                        
                        <Switch 
                            :id="'cal-' + calendar.id" 
                            :checked="filtersByProfile[filterProfileName]?.includes(calendar.id)"
                            @update:checked="toggleCalendar(calendar.id)"
                            @click.stop
                            class="scale-125"
                        />
                    </div>
                </div>

                <div class="mt-8">
                    <Button class="w-full h-16 rounded-[2rem] text-xl font-black shadow-none hover:scale-[1.02] transition-transform active:scale-95" @click="isFilterDialogOpen = false">Done</Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
:deep(.fc) {
    --fc-page-bg-color: transparent;
}

/* Wiggle animation for Edit Mode */
@keyframes wiggle {
    0% { transform: rotate(0deg); }
    25% { transform: rotate(-0.3deg); }
    75% { transform: rotate(0.3deg); }
    100% { transform: rotate(0deg); }
}

.animate-wiggle {
    animation: wiggle 0.4s ease-in-out infinite;
}

/* Smooth Tabs Content Transitions */
.tabs-content-enter-active,
.tabs-content-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}

.tabs-content-enter-from {
  opacity: 0;
  transform: translateY(10px);
}

.tabs-content-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

.custom-scrollbar::-webkit-scrollbar {
    width: 10px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: hsl(var(--muted-foreground) / 0.1);
    border-radius: 20px;
    border: 4px solid transparent;
    background-clip: content-box;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: hsl(var(--muted-foreground) / 0.3);
    background-clip: content-box;
}
</style>
