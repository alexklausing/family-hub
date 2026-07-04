<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { XIcon, ImageIcon } from '@lucide/vue'
import axios from 'axios'

const apps = [
    {
        id: 'aura',
        name: 'Aura Frames',
        icon: ImageIcon,
        color: 'bg-blue-500',
    }
]

const activeApp = ref(null)

// Aura Frames App State
const auraPhotos = ref([])
const auraCurrentIndex = ref(0)
const auraLoading = ref(false)
const auraError = ref(false)
let auraInterval = null

const launchApp = (appId) => {
    activeApp.value = appId
    if (appId === 'aura') {
        startAura()
    }
}

const closeApp = () => {
    if (activeApp.value === 'aura') {
        stopAura()
    }
    activeApp.value = null
}

const startAura = async () => {
    auraLoading.value = true
    auraError.value = false
    try {
        const response = await axios.get('/api/aura')
        if (response.data.photos && response.data.photos.length > 0) {
            auraPhotos.value = response.data.photos.sort(() => Math.random() - 0.5) // shuffle
            auraCurrentIndex.value = 0
            
            // Advance slide every 10 seconds
            if (auraInterval) clearInterval(auraInterval)
            auraInterval = setInterval(() => {
                auraCurrentIndex.value = (auraCurrentIndex.value + 1) % auraPhotos.value.length
            }, 10000)
        } else {
            auraError.value = true
        }
    } catch (e) {
        console.error(e)
        auraError.value = true
    } finally {
        auraLoading.value = false
    }
}

const stopAura = () => {
    if (auraInterval) {
        clearInterval(auraInterval)
        auraInterval = null
    }
}

onUnmounted(() => {
    stopAura()
})
</script>

<template>
    <div class="h-full flex flex-col p-6">
        <h1 class="text-4xl font-bold mb-8 text-black dark:text-white">Other Apps</h1>

        <!-- Grid of Apps (iPhone style) -->
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-6 place-content-start" v-if="!activeApp">
            <button
                v-for="app in apps"
                :key="app.id"
                @click="launchApp(app.id)"
                class="flex flex-col items-center justify-center gap-2 group outline-none"
            >
                <div 
                    :class="[
                        'w-20 h-20 rounded-2xl flex items-center justify-center text-white shadow-lg transition-transform duration-200 group-hover:scale-105 group-active:scale-95',
                        app.color
                    ]"
                >
                    <component :is="app.icon" class="w-10 h-10" />
                </div>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ app.name }}</span>
            </button>
        </div>

        <!-- Fullscreen App Container -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-transform duration-500 ease-[cubic-bezier(0.32,0.72,0,1)]"
                enter-from-class="translate-y-full"
                enter-to-class="translate-y-0"
                leave-active-class="transition-transform duration-300 ease-in"
                leave-from-class="translate-y-0"
                leave-to-class="translate-y-full"
            >
                <div v-if="activeApp" class="fixed inset-0 z-[100] bg-black">
                    <!-- Close Button -->
                    <button 
                        @click="closeApp"
                        class="absolute top-6 right-6 z-50 w-12 h-12 bg-black/40 hover:bg-black/60 rounded-full flex items-center justify-center text-white backdrop-blur-md transition-colors"
                    >
                        <XIcon class="w-6 h-6" />
                    </button>

                    <!-- Aura App -->
                    <div v-if="activeApp === 'aura'" class="w-full h-full flex items-center justify-center">
                        <div v-if="auraLoading" class="text-white text-xl animate-pulse">
                            Loading Photos...
                        </div>
                        <div v-else-if="auraError" class="text-red-400 text-xl text-center px-4">
                            Unable to load photos. Please check your Aura credentials in .env.
                        </div>
                        <div v-else-if="auraPhotos.length === 0" class="text-gray-400 text-xl">
                            No photos found in your Aura frame.
                        </div>
                        <template v-else>
                            <div 
                                v-for="(photo, index) in auraPhotos"
                                :key="photo.id"
                                class="absolute inset-0 transition-opacity duration-1000 ease-in-out"
                                :class="index === auraCurrentIndex ? 'opacity-100' : 'opacity-0 pointer-events-none'"
                            >
                                <img 
                                    :src="photo.url" 
                                    class="w-full h-full object-contain"
                                    alt="Aura Frame Photo"
                                />
                            </div>
                        </template>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
