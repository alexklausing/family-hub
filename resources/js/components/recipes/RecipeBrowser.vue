<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import axios from 'axios'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { 
    Search, RefreshCw, ChefHat, Utensils, 
    ShoppingCart, Check, ArrowLeft, Clock, 
    Users, Play, X, BookOpen, ScrollText,
    ZoomIn, ZoomOut, Scale
} from 'lucide-vue-next'

const recipes = ref([])
const categories = ref([])
const activeCategory = ref('')
const searchQuery = ref('')
const isLoading = ref(false)
const isSyncing = ref(false)
const error = ref(null)
const pagination = ref({ current_page: 1, last_page: 1 })

// View States
const selectedRecipe = ref(null)
const isCookingMode = ref(false)
const isAddingToList = ref({}) // Map: { uuid: true/false }

// Scaling State
const scaleFactor = ref(1.0)
const scaleOptions = [0.5, 1.0, 2.0, 3.0]

// Zoom Logic
const zoomLevel = ref(100) // Percentage
const fontSizeStyle = computed(() => ({
    fontSize: `${(zoomLevel.value / 100) * 2.25}rem`,
    lineHeight: '1.5'
}))

const increaseZoom = () => { if (zoomLevel.value < 200) zoomLevel.value += 10 }
const decreaseZoom = () => { if (zoomLevel.value > 50) zoomLevel.value -= 10 }

// Scaling Helper Logic
const parseQuantity = (val) => {
    if (val.includes(' ')) {
        const parts = val.split(' ')
        if (parts[1].includes('/')) {
            const fraction = parts[1].split('/')
            return parseFloat(parts[0]) + (parseFloat(fraction[0]) / parseFloat(fraction[1]))
        }
    }
    if (val.includes('/')) {
        const parts = val.split('/')
        return parseFloat(parts[0]) / parseFloat(parts[1])
    }
    return parseFloat(val)
}

const formatQuantity = (num) => {
    if (num === (num | 0)) return num.toString()
    const fractions = { 0.25: '1/4', 0.5: '1/2', 0.75: '3/4', 0.33: '1/3', 0.66: '2/3' }
    const whole = Math.floor(num)
    const decimal = Math.round((num - whole) * 100) / 100
    for (const [val, text] of Object.entries(fractions)) {
        if (Math.abs(decimal - parseFloat(val)) < 0.05) {
            return (whole > 0 ? `${whole} ` : '') + text
        }
    }
    return num.toFixed(2).replace(/\.?0+$/, '')
}

const scaledIngredients = computed(() => {
    if (!selectedRecipe.value || !selectedRecipe.value.ingredients) return ''
    if (scaleFactor.value === 1.0) return selectedRecipe.value.ingredients

    const pattern = /(\d+\s+\d+\/\d+|\d+\/\d+|\d+\.\d+|\d+)/g
    return selectedRecipe.value.ingredients.split('\n').map(line => {
        let replaced = false
        return line.replace(pattern, (match) => {
            if (replaced) return match // Only scale first number
            replaced = true
            try {
                const numeric = parseQuantity(match)
                return formatQuantity(numeric * scaleFactor.value)
            } catch (e) { return match }
        })
    }).join('\n')
})

const fetchRecipes = async (page = 1, sync = false) => {
    isLoading.value = sync ? false : true
    if (sync) isSyncing.value = true
    error.value = null
    
    try {
        const response = await axios.get('/api/recipes', {
            params: {
                page,
                category: activeCategory.value,
                search: searchQuery.value,
                sync: sync ? 1 : undefined
            }
        })
        
        if (response.data && response.data.data) {
            recipes.value = response.data.data
            pagination.value = {
                current_page: response.data.current_page,
                last_page: response.data.last_page
            }
        } else {
            recipes.value = []
        }
    } catch (err) {
        console.error('Failed to fetch recipes:', err)
        error.value = 'Failed to load recipes. Please try syncing again.'
    } finally {
        isLoading.value = false
        isSyncing.value = false
    }
}

const fetchCategories = async () => {
    try {
        const response = await axios.get('/api/recipes/categories')
        categories.value = response.data
    } catch (error) {
        console.error('Failed to fetch categories:', error)
    }
}

const addToList = async (recipe) => {
    isAddingToList.value[recipe.uuid] = true
    try {
        await axios.post('/api/shopping-list/add-recipe', {
            recipe_uuid: recipe.uuid,
            scale: scaleFactor.value
        })
        setTimeout(() => {
            isAddingToList.value[recipe.uuid] = false
        }, 2000)
    } catch (error) {
        console.error('Failed to add recipe to list:', error)
        isAddingToList.value[recipe.uuid] = false
    }
}

const openDetails = (recipe) => {
    selectedRecipe.value = recipe
    isCookingMode.value = false
    scaleFactor.value = 1.0
}

const closeDetails = () => {
    selectedRecipe.value = null
    isCookingMode.value = false
    scaleFactor.value = 1.0
}

const handleSearch = () => {
    fetchRecipes(1)
}

const selectCategory = (cat) => {
    activeCategory.value = activeCategory.value === cat ? '' : cat
    fetchRecipes(1)
}

onMounted(() => {
    fetchRecipes()
    fetchCategories()
})

// Debounce search
let searchTimeout = null
watch(searchQuery, () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        handleSearch()
    }, 500)
})
</script>

<template>
    <div class="h-full flex flex-col min-h-0">
        <!-- 1. Recipe Browser (Grid) -->
        <div v-if="!selectedRecipe" class="flex flex-col gap-8 h-full p-2 min-h-0">
            <!-- Toolbar -->
            <div class="flex flex-col md:flex-row gap-6 items-center justify-between shrink-0">
                <div class="relative w-full max-w-lg">
                    <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-6 w-6 text-muted-foreground opacity-50" />
                    <Input 
                        v-model="searchQuery" 
                        placeholder="Search your library..." 
                        class="pl-12 h-14 text-xl rounded-2xl bg-white/40 dark:bg-white/5 backdrop-blur-xl border-white/20 dark:border-white/10 shadow-lg focus:ring-primary/20 transition-all"
                    />
                </div>
                
                <div class="flex items-center gap-3 overflow-x-auto pb-2 w-full md:w-auto no-scrollbar">
                    <Button 
                        v-for="cat in categories" 
                        :key="cat"
                        :variant="activeCategory === cat ? 'default' : 'ghost'"
                        class="whitespace-nowrap rounded-2xl font-black text-sm uppercase tracking-widest px-6 h-12 transition-all border border-transparent shadow-sm"
                        :class="activeCategory === cat ? 'bg-primary text-white shadow-primary/20' : 'bg-white/40 dark:bg-white/5 backdrop-blur-xl border-white/20 dark:border-white/10 hover:bg-white/60 dark:hover:bg-white/10'"
                        @click="selectCategory(cat)"
                    >
                        {{ cat }}
                    </Button>
                </div>

                <Button 
                    variant="ghost" 
                    size="icon" 
                    class="h-14 w-14 shrink-0 rounded-2xl bg-white/40 dark:bg-white/5 backdrop-blur-xl border-none shadow-xl hover:bg-white/60 dark:hover:bg-white/10" 
                    @click="fetchRecipes(1, true)"
                    :disabled="isSyncing"
                >
                    <RefreshCw :class="['h-7 w-7', isSyncing ? 'animate-spin' : '']" />
                </Button>
            </div>

            <!-- Grid -->
            <div v-if="isLoading" class="flex-1 flex items-center justify-center">
                <div class="flex flex-col items-center gap-6">
                    <RefreshCw class="h-16 w-16 animate-spin text-primary" />
                    <p class="text-2xl font-black uppercase tracking-tighter animate-pulse opacity-40">Fetching kitchen secrets...</p>
                </div>
            </div>

            <div v-else-if="error" class="flex-1 flex items-center justify-center">
                <div class="text-center space-y-6 bg-white/40 dark:bg-white/5 backdrop-blur-xl p-12 rounded-[3rem] border-none shadow-2xl">
                    <p class="text-2xl font-black text-destructive tracking-tight">{{ error }}</p>
                    <Button class="h-14 px-10 rounded-2xl font-black text-lg" @click="fetchRecipes(1, true)">
                        <RefreshCw class="w-5 h-5 mr-3" />
                        Retry Sync
                    </Button>
                </div>
            </div>

            <div v-else-if="!recipes || recipes.length === 0" class="flex-1 flex items-center justify-center">
                <div class="text-center space-y-6 opacity-40">
                    <ChefHat class="h-32 w-32 mx-auto text-muted-foreground" />
                    <p class="text-3xl font-black uppercase tracking-tighter">No recipes found</p>
                    <div class="flex gap-4 justify-center">
                        <Button v-if="searchQuery || activeCategory" variant="outline" class="rounded-xl font-bold" @click="searchQuery = ''; activeCategory = ''; fetchRecipes(1);">Clear filters</Button>
                        <Button variant="ghost" class="rounded-xl font-bold" @click="fetchRecipes(1, true)">Try Force Sync</Button>
                    </div>
                </div>
            </div>

            <div v-else class="flex-1 overflow-y-auto pr-2 custom-scrollbar min-h-0">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8 pb-12">
                    <Card 
                        v-for="recipe in recipes" 
                        :key="recipe.id" 
                        class="group overflow-hidden rounded-[2.5rem] bg-white/60 dark:bg-white/5 backdrop-blur-3xl border-none shadow-2xl hover:shadow-2xl hover:scale-[1.03] transition-all duration-500 cursor-pointer flex flex-col"
                        @click="openDetails(recipe)"
                    >
                        <div class="aspect-[4/3] relative overflow-hidden bg-muted">
                            <img 
                                v-if="recipe.image_url" 
                                :src="recipe.image_url" 
                                :alt="recipe.title"
                                class="object-cover w-full h-full group-hover:scale-110 transition-transform duration-700"
                            />
                            <div v-else class="flex items-center justify-center h-full">
                                <Utensils class="h-16 w-16 text-muted-foreground/20" />
                            </div>
                            <div class="absolute top-4 right-4">
                                <div class="bg-black/60 backdrop-blur-xl text-white text-[10px] font-black px-3 py-1.5 rounded-full uppercase tracking-widest border border-white/10 shadow-lg">
                                    {{ recipe.category || 'Recipe' }}
                                </div>
                            </div>
                        </div>
                        <CardHeader class="p-6 flex-1">
                            <CardTitle class="text-2xl font-black leading-tight tracking-tight line-clamp-2 group-hover:text-primary transition-colors">
                                {{ recipe.title }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-6 pt-0">
                            <Button 
                                variant="secondary" 
                                class="w-full font-black uppercase tracking-widest h-12 rounded-2xl bg-primary/5 hover:bg-primary hover:text-white border-2 border-primary/10 transition-all" 
                                @click.stop="addToList(recipe)"
                                :disabled="isAddingToList[recipe.uuid]"
                            >
                                <component :is="isAddingToList[recipe.uuid] ? Check : ShoppingCart" class="mr-2 h-5 w-5" />
                                {{ isAddingToList[recipe.uuid] ? 'Added!' : 'Add to List' }}
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <!-- Pagination -->
                <div v-if="pagination.last_page > 1" class="flex justify-center items-center gap-6 py-12">
                    <Button 
                        variant="ghost" 
                        class="h-14 px-8 rounded-2xl bg-white/40 dark:bg-white/5 backdrop-blur-xl border border-white/20 font-black uppercase tracking-widest"
                        :disabled="pagination.current_page === 1"
                        @click="fetchRecipes(pagination.current_page - 1)"
                    >
                        Previous
                    </Button>
                    <div class="flex items-center px-6 h-14 bg-white/60 dark:bg-white/5 backdrop-blur-3xl rounded-2xl border border-white/20 font-black text-xl tabular-nums">
                        {{ pagination.current_page }} <span class="mx-2 opacity-30">/</span> {{ pagination.last_page }}
                    </div>
                    <Button 
                        variant="ghost" 
                        class="h-14 px-8 rounded-2xl bg-white/40 dark:bg-white/5 backdrop-blur-xl border border-white/20 font-black uppercase tracking-widest"
                        :disabled="pagination.current_page === pagination.last_page"
                        @click="fetchRecipes(pagination.current_page + 1)"
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>

        <!-- 2. Full Recipe Detail View -->
        <div v-else-if="!isCookingMode" class="flex-1 flex flex-col bg-white/80 dark:bg-[#000000] rounded-[3rem] overflow-hidden border border-white/40 dark:border-white/10 shadow-[0_32px_64px_-12px_rgba(0,0,0,0.3)] animate-in fade-in zoom-in-95 slide-in-from-bottom-10 duration-700 min-h-0">
            <!-- Header (Compact Banner) -->
            <div class="h-56 shrink-0 relative bg-muted overflow-hidden group">
                <img 
                    v-if="selectedRecipe.image_url" 
                    :src="selectedRecipe.image_url" 
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-[2000ms]"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent flex items-end p-10">
                    <div class="flex flex-col lg:flex-row lg:items-end justify-between w-full max-w-7xl mx-auto gap-4">
                        <div class="space-y-3">
                            <div class="flex items-center gap-4">
                                <Button variant="ghost" class="bg-white/10 backdrop-blur-3xl border border-white/20 text-white hover:bg-white/30 h-10 px-6 rounded-xl font-black uppercase tracking-widest text-[10px] shadow-2xl transition-all" @click="closeDetails">
                                    <ArrowLeft class="mr-2 h-4 w-4" /> Back
                                </Button>
                                <div class="bg-primary/90 backdrop-blur-xl px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-[0.2em] text-white shadow-xl">
                                    {{ selectedRecipe.category || 'Kitchen Secret' }}
                                </div>
                            </div>
                            <h2 class="text-5xl font-black text-white drop-shadow-[0_4px_12px_rgba(0,0,0,0.5)] tracking-tighter leading-[0.9]">
                                {{ selectedRecipe.title }}
                            </h2>
                        </div>
                        
                        <div class="flex items-center gap-6 text-white/70 font-black uppercase tracking-widest text-[10px]">
                            <div class="flex items-center gap-2">
                                <Clock class="w-4 h-4" />
                                <span>Prep: 20m</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Utensils class="w-4 h-4" />
                                <span>Cook: 45m</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="flex-1 overflow-hidden grid grid-cols-1 lg:grid-cols-12 min-h-0 bg-white dark:bg-[#050505]">
                <div class="lg:col-span-4 flex flex-col border-r border-border/40 min-h-0">
                    <div class="p-8 pb-4 shrink-0 flex items-center justify-between">
                        <h4 class="text-lg font-black uppercase tracking-widest text-primary flex items-center gap-3">
                            <div class="w-2 h-5 bg-primary rounded-full shadow-lg shadow-primary/20"></div>
                            Ingredients
                        </h4>
                        <!-- Scaling Control -->
                        <div class="flex items-center bg-accent/30 rounded-full p-1 border border-border/40">
                            <Button 
                                v-for="opt in scaleOptions" :key="opt"
                                variant="ghost" 
                                size="sm" 
                                class="h-8 px-3 rounded-full font-black text-[10px]"
                                :class="scaleFactor === opt ? 'bg-primary text-white' : ''"
                                @click="scaleFactor = opt"
                            >
                                {{ opt }}x
                            </Button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto p-8 pt-0 custom-scrollbar">
                        <div class="text-xl leading-relaxed whitespace-pre-wrap font-bold text-foreground/80 tracking-tight">
                            {{ scaledIngredients || 'No ingredients listed.' }}
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-8 flex flex-col min-h-0">
                    <div class="p-8 pb-4 shrink-0">
                        <h4 class="text-lg font-black uppercase tracking-widest text-primary flex items-center gap-3">
                            <div class="w-2 h-5 bg-primary rounded-full shadow-lg shadow-primary/20"></div>
                            Preparation
                        </h4>
                    </div>
                    <div class="flex-1 overflow-y-auto p-8 pt-0 custom-scrollbar">
                        <div class="text-xl leading-relaxed whitespace-pre-wrap text-foreground/90 font-medium tracking-tight">
                            {{ selectedRecipe.directions || 'No directions listed.' }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Toolbar -->
            <div class="p-6 border-t bg-accent/5 flex justify-center shrink-0">
                <div class="flex gap-4 w-full max-w-2xl">
                    <Button variant="ghost" class="h-14 px-8 font-black text-lg rounded-2xl border-4 bg-muted/20 border-border/50 hover:bg-muted/40 transition-all" @click="closeDetails">Close</Button>
                    <Button class="h-14 px-8 font-black text-lg flex-1 rounded-2xl shadow-lg shadow-primary/20 hover:scale-[1.02] transition-all" @click="addToList(selectedRecipe)" :disabled="isAddingToList[selectedRecipe.uuid]">
                        <component :is="isAddingToList[selectedRecipe.uuid] ? Check : ShoppingCart" class="mr-3 h-6 w-6" />
                        {{ isAddingToList[selectedRecipe.uuid] ? 'Added!' : 'Add to Shopping List' }}
                    </Button>
                    <Button variant="outline" class="h-14 px-8 font-black text-lg rounded-2xl border-4 border-primary bg-primary/10 text-primary hover:bg-primary hover:text-white transition-all" @click="isCookingMode = true">
                        <Play class="mr-3 h-5 w-5 fill-current" /> Cook This
                    </Button>
                </div>
            </div>
        </div>

        <!-- 3. Immersive Cooking Mode (Full Viewport) -->
        <Teleport to="body">
            <div v-if="selectedRecipe && isCookingMode" 
                 class="fixed inset-0 z-[100] bg-white dark:bg-[#000000] flex flex-col min-h-0 animate-in fade-in duration-500"
            >
                <!-- Full-Screen Header -->
                <div class="p-8 lg:p-12 border-b bg-primary text-white flex items-center justify-between shrink-0 shadow-2xl">
                    <div class="flex items-center gap-8">
                        <div class="p-5 bg-white/20 rounded-3xl backdrop-blur-md">
                            <Utensils class="w-12 h-12" />
                        </div>
                        <div class="max-w-3xl">
                            <p class="text-sm font-black uppercase tracking-[0.4em] opacity-60">Cooking Mode</p>
                            <h2 class="text-6xl font-black tracking-tighter leading-none mt-1 truncate">{{ selectedRecipe.title }}</h2>
                        </div>
                    </div>
                    
                    <!-- Zoom, Scale & Close Controls -->
                    <div class="flex items-center gap-6">
                        <!-- Scale -->
                        <div class="flex items-center bg-white/10 rounded-full p-1 border border-white/20 backdrop-blur-md">
                            <div class="px-4 font-black text-xs uppercase tracking-widest opacity-60 flex items-center gap-2">
                                <Scale class="w-4 h-4" /> Scale
                            </div>
                            <Button 
                                v-for="opt in scaleOptions" :key="opt"
                                variant="ghost" 
                                class="h-14 px-6 rounded-full font-black text-xl hover:bg-white/20"
                                :class="scaleFactor === opt ? 'bg-white text-primary hover:bg-white' : 'text-white'"
                                @click="scaleFactor = opt"
                            >
                                {{ opt }}x
                            </Button>
                        </div>

                        <!-- Zoom -->
                        <div class="flex items-center bg-white/10 rounded-full p-1 border border-white/20 backdrop-blur-md">
                            <Button variant="ghost" size="icon" class="h-16 w-16 rounded-full hover:bg-white/20 text-white" @click="decreaseZoom">
                                <ZoomOut class="w-8 h-8" />
                            </Button>
                            <div class="px-4 font-black text-xl tabular-nums min-w-[4rem] text-center">{{ zoomLevel }}%</div>
                            <Button variant="ghost" size="icon" class="h-16 w-16 rounded-full hover:bg-white/20 text-white" @click="increaseZoom">
                                <ZoomIn class="w-8 h-8" />
                            </Button>
                        </div>
                        
                        <Button variant="ghost" size="icon" class="h-20 w-20 rounded-full bg-white/10 hover:bg-white/20 text-white border border-white/20 shadow-xl" @click="isCookingMode = false">
                            <X class="w-12 h-12" />
                        </Button>
                    </div>
                </div>

                <!-- Full-Screen Split Pane -->
                <div class="flex-1 overflow-hidden grid grid-cols-1 lg:grid-cols-12 min-h-0 bg-white dark:bg-[#000000]">
                    <!-- Ingredients -->
                    <div class="lg:col-span-4 flex flex-col border-r-8 border-primary/10 min-h-0 bg-accent/5">
                        <div class="p-16 pb-8 shrink-0 flex items-center gap-6">
                            <ScrollText class="w-10 h-10 text-primary" />
                            <h4 class="text-4xl font-black uppercase tracking-tighter">Ingredients</h4>
                        </div>
                        <div class="flex-1 overflow-y-auto p-16 pt-0 custom-scrollbar">
                            <div :style="fontSizeStyle" class="font-black text-foreground/90 tracking-tight whitespace-pre-wrap transition-all duration-300">
                                {{ scaledIngredients }}
                            </div>
                        </div>
                    </div>

                    <!-- Instructions -->
                    <div class="lg:col-span-8 flex flex-col min-h-0">
                        <div class="p-16 pb-8 shrink-0 flex items-center gap-6">
                            <BookOpen class="w-10 h-10 text-primary" />
                            <h4 class="text-4xl font-black uppercase tracking-tighter">Preparation</h4>
                        </div>
                        <div class="flex-1 overflow-y-auto p-16 pt-0 custom-scrollbar">
                            <div :style="fontSizeStyle" class="font-bold text-foreground tracking-tight whitespace-pre-wrap transition-all duration-300">
                                {{ selectedRecipe.directions }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 12px;
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
