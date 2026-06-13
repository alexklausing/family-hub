<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import Draggable from 'vuedraggable'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Lock, Unlock, RotateCcw } from 'lucide-vue-next'
import CalendarTab from './CalendarTab.vue'

// Orientation Tracking
const isVertical = ref(false)
const updateOrientation = () => {
    isVertical.value = window.innerHeight > window.innerWidth
}

// Layout Management
const isEditMode = ref(false)

const defaultWidgets = [
    { id: 'header', title: 'Header', cols: 7 },
    { id: 'calendar', title: 'Calendar', component: 'CalendarTab', cols: 5 },
    { id: 'schedule', title: 'Schedule', component: 'ScheduleList', cols: 2 },
]

const widgets = ref([])

const loadLayout = () => {
    const saved = localStorage.getItem('dashboard_layout')
    if (saved) {
        const savedWidgets = JSON.parse(saved)
        // Reconcile: Ensure all default widgets exist in the saved layout
        const reconciled = [...savedWidgets]
        defaultWidgets.forEach(defaultWidget => {
            if (!reconciled.find(w => w.id === defaultWidget.id)) {
                reconciled.unshift(defaultWidget)
            }
        })
        widgets.value = reconciled
    } else {
        widgets.value = JSON.parse(JSON.stringify(defaultWidgets))
    }
}

const saveLayout = () => {
    localStorage.setItem('dashboard_layout', JSON.stringify(widgets.value))
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
})

onUnmounted(() => {
    window.removeEventListener('resize', updateOrientation)
})

// Profiles (Placeholder)
const profiles = [
    { name: 'Family', icon: '🏠' },
    { name: 'Dad', icon: '👨' },
    { name: 'Mom', icon: '👩' },
    { name: 'Kids', icon: '🧒' },
]
const activeProfile = ref('Family')

// Placeholder events
const mockEvents = [
    {
        title: 'Family Dinner',
        start: new Date().toISOString().split('T')[0] + 'T18:30:00',
    },
    {
        title: 'Scouts Meeting',
        start: new Date(new Date().setDate(new Date().getDate() + 2))
            .toISOString()
            .split('T')[0],
    },
]
</script>

<template>
    <div class="bg-background min-h-screen p-4 lg:p-8 flex flex-col gap-6">
        <Tabs default-value="family" class="flex-1 flex flex-col gap-4">
            <div class="flex items-center justify-between gap-4 max-w-2xl mx-auto w-full mb-2">
                <TabsList class="grid h-16 w-full grid-cols-4">
                    <TabsTrigger value="family" class="text-xl">Calendar</TabsTrigger>
                    <TabsTrigger value="meal-plan" class="text-xl">Meal Plan</TabsTrigger>
                    <TabsTrigger value="recipes" class="text-xl">Recipes</TabsTrigger>
                    <TabsTrigger value="tasks" class="text-xl">Tasks</TabsTrigger>
                </TabsList>
                
                <div class="flex items-center gap-2">
                    <Button 
                        variant="outline" 
                        size="sm" 
                        @click="isEditMode = !isEditMode"
                        :class="['h-16 px-4', isEditMode ? 'bg-orange-100 border-orange-300 hover:bg-orange-200' : '']"
                    >
                        <component :is="isEditMode ? Unlock : Lock" class="w-5 h-5" />
                        <span class="ml-2 hidden lg:inline">{{ isEditMode ? 'Finish' : 'Edit' }}</span>
                    </Button>
                    <Button v-if="isEditMode" variant="ghost" size="sm" @click="resetLayout" class="h-16 px-4">
                        <RotateCcw class="w-5 h-5" />
                    </Button>
                </div>
            </div>

            <TabsContent value="family" class="flex-1">
                <Draggable 
                    v-model="widgets" 
                    item-key="id" 
                    handle=".drag-handle"
                    :disabled="!isEditMode"
                    :class="['grid gap-4 h-full', isVertical ? 'grid-cols-1' : 'grid-cols-7']"
                >
                    <template #item="{ element }">
                        <div :class="[
                            isVertical ? '' : 
                            element.id === 'header' ? 'col-span-7' : 
                            element.id === 'calendar' ? 'col-span-5' : 
                            'col-span-2', 
                            'relative group'
                        ]">
                            <!-- Drag Handle overlay for Edit Mode -->
                            <div v-if="isEditMode" class="drag-handle absolute inset-0 z-50 bg-primary/5 border-2 border-dashed border-primary rounded-xl cursor-move flex items-center justify-center animate-pulse">
                                <Unlock class="w-12 h-12 text-primary opacity-50" />
                            </div>

                            <!-- Header Widget -->
                            <header v-if="element.id === 'header'" class="flex flex-col md:flex-row items-center justify-between gap-4 bg-card p-4 rounded-xl border shadow-sm relative h-full" :class="{ 'scale-[0.98] blur-[1px]': isEditMode }">
                                <div class="flex items-center gap-4">
                                    <div v-for="profile in profiles" :key="profile.name" 
                                         @click="activeProfile = profile.name"
                                         :class="[
                                             'flex flex-col items-center justify-center p-2 rounded-lg cursor-pointer transition-all w-20 h-20 border-2',
                                             activeProfile === profile.name ? 'border-primary bg-primary/10 scale-105' : 'border-transparent hover:bg-accent'
                                         ]">
                                        <span class="text-3xl">{{ profile.icon }}</span>
                                        <span class="text-xs font-medium mt-1">{{ profile.name }}</span>
                                    </div>
                                </div>

                                <div class="flex flex-col items-end gap-2">
                                    <div class="text-4xl font-bold tracking-tighter">
                                        {{ new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                    </div>
                                    <div class="flex items-center gap-2 text-muted-foreground">
                                        <span class="text-2xl font-semibold text-foreground">72°F</span>
                                        <span>Sunny • {{ new Date().toLocaleDateString([], { weekday: 'long', month: 'short', day: 'numeric' }) }}</span>
                                    </div>
                                </div>
                            </header>

                            <!-- Calendar Widget -->
                            <Card v-else-if="element.id === 'calendar'" class="flex flex-col shadow-lg h-full transition-transform" :class="{ 'scale-[0.98] blur-[1px]': isEditMode }">
                                <CardHeader class="pb-2">
                                    <CardTitle class="text-2xl flex items-center justify-between">
                                        {{ activeProfile }} Calendar
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="flex-1 min-h-[600px] p-2">
                                    <CalendarTab :events="mockEvents" />
                                </CardContent>
                            </Card>

                            <!-- Schedule Widget -->
                            <Card v-else-if="element.id === 'schedule'" class="shadow-md h-full transition-transform" :class="{ 'scale-[0.98] blur-[1px]': isEditMode }">
                                <CardHeader>
                                    <CardTitle>Schedule</CardTitle>
                                    <CardDescription>What's happening today</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div class="space-y-4">
                                        <div v-if="mockEvents.length > 0" class="space-y-3">
                                            <div v-for="event in mockEvents" :key="event.title" 
                                                 class="group relative flex items-start gap-4 p-4 rounded-xl border bg-accent/5 hover:bg-accent/10 transition-colors">
                                                <div class="w-1 h-10 bg-primary rounded-full mt-1"></div>
                                                <div>
                                                    <p class="font-bold text-lg leading-tight">{{ event.title }}</p>
                                                    <p class="text-muted-foreground text-sm font-medium">6:30 PM - 8:00 PM</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-center py-10 text-muted-foreground italic">
                                            No more events today.
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </template>
                </Draggable>
            </TabsContent>

            <TabsContent value="meal-plan">
                <Card><CardContent class="h-[600px] flex items-center justify-center">Meal Plan View</CardContent></Card>
            </TabsContent>
            <TabsContent value="recipes">
                <Card><CardContent class="h-[600px] flex items-center justify-center">Recipe View</CardContent></Card>
            </TabsContent>
            <TabsContent value="tasks">
                <Card><CardContent class="h-[600px] flex items-center justify-center">Task View</CardContent></Card>
            </TabsContent>
        </Tabs>
    </div>
</template>

<style scoped>
:deep(.fc) {
    --fc-page-bg-color: transparent;
}

/* Wiggle animation for Edit Mode */
@keyframes wiggle {
    0% { transform: rotate(0deg); }
    25% { transform: rotate(-0.5deg); }
    75% { transform: rotate(0.5deg); }
    100% { transform: rotate(0deg); }
}

.animate-wiggle {
    animation: wiggle 0.3s ease-in-out infinite;
}

/* Ensure the dashboard fills the viewport without scrollbars unless necessary */
</style>
