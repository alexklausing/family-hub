<script setup>
import { computed } from 'vue'
import { Calendar, CloudSun, ChefHat, ShoppingBag, CheckSquare, ImageIcon, Pin, MapPin, BadgeCheck, CheckCircle2, Minus, Plus } from 'lucide-vue-next'

const props = defineProps({
    workspaces: {
        type: Array,
        default: () => []
    },
    isEditing: {
        type: Boolean,
        default: false
    },
    isAddingToSlot: {
        type: Boolean,
        default: false
    },
    unusedApps: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['launch', 'create-workspace', 'remove-workspace', 'toggle-active'])

const allApps = [
    {
        id: 'family',
        name: 'Calendar',
        icon: Calendar,
        color: 'bg-indigo-500',
    },
    {
        id: 'weather',
        name: 'Weather',
        icon: CloudSun,
        color: 'bg-blue-500',
    },
    {
        id: 'recipes',
        name: 'Recipes',
        icon: ChefHat,
        color: 'bg-orange-500',
    },
    {
        id: 'shopping',
        name: 'Shopping',
        icon: ShoppingBag,
        color: 'bg-green-500',
    },
    {
        id: 'chores',
        name: 'Chores',
        icon: CheckSquare,
        color: 'bg-purple-500',
    },
    {
        id: 'aura',
        name: 'Aura Frames',
        icon: ImageIcon,
        color: 'bg-teal-500',
    },
    {
        id: 'fun-facts',
        name: 'Fun Facts',
        icon: BadgeCheck,
        color: 'bg-yellow-500',
    }
]

const activeAppsList = computed(() => allApps.filter(a => !props.unusedApps.includes(a.id)))
const unusedAppsList = computed(() => allApps.filter(a => props.unusedApps.includes(a.id)))

// Helper to find if a dedicated workspace exists for this app
const getDedicatedWorkspace = (appId) => {
    return props.workspaces.find(ws => ws.apps.length === 1 && ws.apps[0] === appId)
}

const isAppPinned = (appId) => {
    return !!getDedicatedWorkspace(appId)
}

const handleAppClick = (appId) => {
    if (props.isEditing && !props.isAddingToSlot) {
        if ('vibrate' in navigator) navigator.vibrate(50) // Haptic feedback if available
        const ws = getDedicatedWorkspace(appId)
        if (ws) {
            emit('remove-workspace', ws.id)
        } else {
            emit('create-workspace', appId)
        }
    } else {
        emit('launch', appId)
    }
}
</script>

<template>
    <div class="h-full flex flex-col p-6 overflow-y-auto custom-scrollbar">
        <div class="mb-10 text-center shrink-0">
            <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">App Library</h1>
            <p class="text-lg font-medium text-slate-500 mt-2" v-if="props.isEditing && !props.isAddingToSlot">Tap an app to pin/unpin, or use the +/- buttons to manage active apps.</p>
            <p class="text-lg font-medium text-slate-500 mt-2" v-else-if="props.isAddingToSlot">Select an app to add to the slot.</p>
            <p class="text-lg font-medium text-slate-500 mt-2" v-else>Tap an app to launch it in a temporary view.</p>
        </div>

        <div class="flex-1 min-h-0 flex flex-col gap-16 pb-12">
            <!-- Active Apps -->
            <div>
                <h2 v-if="props.isEditing && !props.isAddingToSlot" class="text-2xl font-black tracking-tight text-slate-900 dark:text-white mb-6 text-center">Active Apps</h2>
                <div class="flex flex-wrap justify-center gap-x-12 gap-y-12 max-w-4xl mx-auto">
                    <button
                        v-for="app in activeAppsList"
                        :key="app.id"
                        @click="handleAppClick(app.id)"
                        class="relative flex flex-col items-center justify-center gap-3 outline-none select-none touch-none"
                    >
                        <div 
                            class="relative w-24 h-24 sm:w-28 sm:h-28 rounded-[2rem] flex items-center justify-center shadow-xl transition-all duration-300 text-white scale-100 group"
                            :class="[app.color, props.isEditing && !props.isAddingToSlot ? 'animate-jiggle origin-center' : '']"
                            :style="{ animationDelay: props.isEditing && !props.isAddingToSlot ? `${Math.random() * 0.2}s` : '0s' }"
                        >
                            <component :is="app.icon" class="w-12 h-12 sm:w-14 sm:h-14 drop-shadow-md" />
                            
                            <!-- Pinned Badge -->
                            <div 
                                v-if="props.isEditing && !props.isAddingToSlot && isAppPinned(app.id)"
                                class="absolute -bottom-3 -right-3 w-8 h-8 bg-green-500 rounded-full border-4 border-[#f2f2f7] dark:border-black flex items-center justify-center text-white shadow-lg animate-in zoom-in duration-300"
                            >
                                <Pin class="w-3.5 h-3.5" stroke-width="3" />
                            </div>
                            
                            <!-- Remove App from Active -->
                            <button
                                v-if="props.isEditing && !props.isAddingToSlot"
                                @click.stop="emit('toggle-active', app.id)"
                                class="absolute -top-3 -left-3 w-8 h-8 bg-red-500 rounded-full border-4 border-[#f2f2f7] dark:border-black flex items-center justify-center text-white shadow-lg hover:scale-110 active:scale-95 transition-transform z-10"
                            >
                                <Minus class="w-4 h-4" stroke-width="4" />
                            </button>
                        </div>
                        <span class="text-base font-bold text-slate-800 dark:text-white transition-colors duration-300">
                            {{ app.name }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Unused Apps -->
            <div v-if="props.isEditing && !props.isAddingToSlot && unusedAppsList.length > 0">
                <div class="max-w-4xl mx-auto flex items-center justify-center gap-4 mb-8">
                    <div class="h-px bg-slate-300 dark:bg-slate-700 flex-1"></div>
                    <h2 class="text-2xl font-black tracking-tight text-slate-400 dark:text-slate-500">Unused Apps</h2>
                    <div class="h-px bg-slate-300 dark:bg-slate-700 flex-1"></div>
                </div>
                
                <div class="flex flex-wrap justify-center gap-x-12 gap-y-12 max-w-4xl mx-auto opacity-70 hover:opacity-100 transition-opacity">
                    <button
                        v-for="app in unusedAppsList"
                        :key="app.id"
                        class="relative flex flex-col items-center justify-center gap-3 outline-none select-none touch-none cursor-default"
                    >
                        <div 
                            class="relative w-24 h-24 sm:w-28 sm:h-28 rounded-[2rem] flex items-center justify-center shadow-md transition-all duration-300 text-white scale-90 grayscale"
                            :class="[app.color]"
                        >
                            <component :is="app.icon" class="w-12 h-12 sm:w-14 sm:h-14 drop-shadow-md" />
                            
                            <!-- Add App to Active -->
                            <button
                                @click.stop="emit('toggle-active', app.id)"
                                class="absolute -top-3 -right-3 w-8 h-8 bg-green-500 rounded-full border-4 border-[#f2f2f7] dark:border-black flex items-center justify-center text-white shadow-lg hover:scale-110 active:scale-95 transition-transform z-10"
                            >
                                <Plus class="w-4 h-4" stroke-width="4" />
                            </button>
                        </div>
                        <span class="text-base font-bold text-slate-500 dark:text-slate-400">
                            {{ app.name }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Prevent text selection and long-press menu on mobile */
button {
    -webkit-user-select: none;
    -webkit-touch-callout: none;
}
</style>
