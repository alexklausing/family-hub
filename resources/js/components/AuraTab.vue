<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const auraPhotos = ref([])
const auraCurrentIndex = ref(0)
const auraLoading = ref(false)
const auraError = ref(false)
let auraInterval = null

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

onMounted(() => {
    startAura()
})

onUnmounted(() => {
    stopAura()
})
</script>

<template>
    <div class="w-full h-full flex items-center justify-center bg-black">
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
</template>
