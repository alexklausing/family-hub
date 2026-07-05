<script setup>
import { ref } from 'vue'
import { Calendar, CloudSun, ChefHat, ShoppingBag, CheckSquare, ImageIcon, Pin, MapPin, BadgeCheck, CheckCircle2 } from 'lucide-vue-next'

const props = defineProps({
    workspaces: {
        type: Array,
        default: () => []
    },
    isEditing: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['launch', 'create-workspace', 'remove-workspace'])

const apps = [
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
    }
]

// Helper to find if a dedicated workspace exists for this app
const getDedicatedWorkspace = (appId) => {
    return props.workspaces.find(ws => ws.apps.length === 1 && ws.apps[0] === appId)
}

const isAppPinned = (appId) => {
    return !!getDedicatedWorkspace(appId)
}

const handleAppClick = (appId) => {
    if (props.isEditing) {
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
    <div class="h-full flex flex-col p-6">
        <div class="mb-10 text-center">
            <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">App Library</h1>
            <p class="text-lg font-medium text-slate-500 mt-2" v-if="props.isEditing">Tap an app to pin or unpin it from the main dashboard tabs.</p>
            <p class="text-lg font-medium text-slate-500 mt-2" v-else>Tap an app to launch it in a temporary view.</p>
        </div>

        <!-- Grid of Apps (iPhone style) -->
        <div class="flex flex-wrap justify-center gap-x-12 gap-y-12 max-w-4xl mx-auto">
            <button
                v-for="app in apps"
                :key="app.id"
                @click="handleAppClick(app.id)"
                class="relative flex flex-col items-center justify-center gap-3 outline-none select-none touch-none"
            >
                <div 
                    class="relative w-24 h-24 sm:w-28 sm:h-28 rounded-[2rem] flex items-center justify-center shadow-xl transition-all duration-300 text-white scale-100"
                    :class="[app.color, props.isEditing ? 'animate-jiggle origin-center' : '']"
                    :style="{ animationDelay: props.isEditing ? `${Math.random() * 0.2}s` : '0s' }"
                >
                    <component :is="app.icon" class="w-12 h-12 sm:w-14 sm:h-14 drop-shadow-md" />
                    
                    <!-- Pinned Badge -->
                    <div 
                        v-if="props.isEditing && isAppPinned(app.id)"
                        class="absolute -top-3 -right-3 w-8 h-8 bg-green-500 rounded-full border-4 border-[#f2f2f7] dark:border-black flex items-center justify-center text-white shadow-lg animate-in zoom-in duration-300"
                    >
                        <Pin class="w-3.5 h-3.5" stroke-width="3" />
                    </div>
                </div>
                <span class="text-base font-bold text-slate-800 dark:text-white transition-colors duration-300">
                    {{ app.name }}
                </span>
            </button>
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
