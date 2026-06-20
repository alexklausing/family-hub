<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import axios from 'axios'
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import {
    Search,
    RefreshCw,
    ChefHat,
    Utensils,
    ShoppingCart,
    Check,
    ArrowLeft,
    Clock,
    Users,
    Play,
    X,
    BookOpen,
    ScrollText,
    ZoomIn,
    ZoomOut,
    Scale,
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
    lineHeight: '1.5',
}))

const increaseZoom = () => {
    if (zoomLevel.value < 200) zoomLevel.value += 10
}
const decreaseZoom = () => {
    if (zoomLevel.value > 50) zoomLevel.value -= 10
}

// Scaling Helper Logic
const parseQuantity = (val) => {
    if (val.includes(' ')) {
        const parts = val.split(' ')
        if (parts[1].includes('/')) {
            const fraction = parts[1].split('/')
            return (
                parseFloat(parts[0]) +
                parseFloat(fraction[0]) / parseFloat(fraction[1])
            )
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
    const fractions = {
        0.25: '1/4',
        0.5: '1/2',
        0.75: '3/4',
        0.33: '1/3',
        0.66: '2/3',
    }
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
    return selectedRecipe.value.ingredients
        .split('\n')
        .map((line) => {
            let replaced = false
            return line.replace(pattern, (match) => {
                if (replaced) return match // Only scale first number
                replaced = true
                try {
                    const numeric = parseQuantity(match)
                    return formatQuantity(numeric * scaleFactor.value)
                } catch (e) {
                    return match
                }
            })
        })
        .join('\n')
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
                sync: sync ? 1 : undefined,
            },
        })

        if (response.data && response.data.data) {
            recipes.value = response.data.data
            pagination.value = {
                current_page: response.data.current_page,
                last_page: response.data.last_page,
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
            scale: scaleFactor.value,
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

const clearFilters = () => {
    searchQuery.value = ''
    activeCategory.value = ''
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
    <div class="flex h-full min-h-0 flex-col">
        <!-- 1. Recipe Browser (Grid) -->
        <div
            v-if="!selectedRecipe"
            class="flex h-full min-h-0 flex-col gap-8 p-2"
        >
            <!-- Toolbar -->
            <div
                class="flex shrink-0 flex-col items-center justify-between gap-6 md:flex-row"
            >
                <div class="relative w-full max-w-lg">
                    <Search
                        class="text-muted-foreground absolute top-1/2 left-4 h-6 w-6 -translate-y-1/2 opacity-50"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Search your library..."
                        class="focus:ring-primary/20 h-14 rounded-2xl border-white/20 bg-white/40 pl-12 text-xl shadow-lg backdrop-blur-xl transition-all dark:border-white/10 dark:bg-white/5"
                    />
                </div>

                <div
                    class="no-scrollbar flex w-full items-center gap-3 overflow-x-auto pb-2 md:w-auto"
                >
                    <Button
                        v-for="cat in categories"
                        :key="cat"
                        :variant="activeCategory === cat ? 'default' : 'ghost'"
                        class="h-12 rounded-2xl border border-transparent px-6 text-sm font-black tracking-widest whitespace-nowrap uppercase shadow-sm transition-all"
                        :class="
                            activeCategory === cat
                                ? 'bg-primary shadow-primary/20 text-white'
                                : 'border-white/20 bg-white/40 backdrop-blur-xl hover:bg-white/60 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10'
                        "
                        @click="selectCategory(cat)"
                    >
                        {{ cat }}
                    </Button>
                </div>

                <Button
                    variant="ghost"
                    size="icon"
                    class="h-14 w-14 shrink-0 rounded-2xl border-none bg-white/40 shadow-xl backdrop-blur-xl hover:bg-white/60 dark:bg-white/5 dark:hover:bg-white/10"
                    @click="fetchRecipes(1, true)"
                    :disabled="isSyncing"
                >
                    <RefreshCw
                        :class="['h-7 w-7', isSyncing ? 'animate-spin' : '']"
                    />
                </Button>
            </div>

            <!-- Grid -->
            <div
                v-if="isLoading"
                class="flex flex-1 items-center justify-center"
            >
                <div class="flex flex-col items-center gap-6">
                    <RefreshCw class="text-primary h-16 w-16 animate-spin" />
                    <p
                        class="animate-pulse text-2xl font-black tracking-tighter uppercase opacity-40"
                    >
                        Fetching kitchen secrets...
                    </p>
                </div>
            </div>

            <div
                v-else-if="error"
                class="flex flex-1 items-center justify-center"
            >
                <div
                    class="space-y-6 rounded-[3rem] border-none bg-white/40 p-12 text-center shadow-2xl backdrop-blur-xl dark:bg-white/5"
                >
                    <p
                        class="text-destructive text-2xl font-black tracking-tight"
                    >
                        {{ error }}
                    </p>
                    <Button
                        class="h-14 rounded-2xl px-10 text-lg font-black"
                        @click="fetchRecipes(1, true)"
                    >
                        <RefreshCw class="mr-3 h-5 w-5" />
                        Retry Sync
                    </Button>
                </div>
            </div>

            <div
                v-else-if="!recipes || recipes.length === 0"
                class="flex flex-1 items-center justify-center"
            >
                <div class="space-y-6 text-center opacity-40">
                    <ChefHat class="text-muted-foreground mx-auto h-32 w-32" />
                    <p class="text-3xl font-black tracking-tighter uppercase">
                        No recipes found
                    </p>
                    <div class="flex justify-center gap-4">
                        <Button
                            v-if="searchQuery || activeCategory"
                            variant="outline"
                            class="rounded-xl font-bold"
                            @click="clearFilters"
                            >Clear filters</Button
                        >
                        <Button
                            variant="ghost"
                            class="rounded-xl font-bold"
                            @click="fetchRecipes(1, true)"
                            >Try Force Sync</Button
                        >
                    </div>
                </div>
            </div>

            <div
                v-else
                class="custom-scrollbar min-h-0 flex-1 overflow-y-auto pr-2"
            >
                <div
                    class="grid grid-cols-1 gap-8 pb-12 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <Card
                        v-for="recipe in recipes"
                        :key="recipe.id"
                        class="group flex cursor-pointer flex-col overflow-hidden rounded-[2.5rem] border-none bg-white/60 shadow-2xl backdrop-blur-3xl transition-all duration-500 hover:scale-[1.03] hover:shadow-2xl dark:bg-white/5"
                        @click="openDetails(recipe)"
                    >
                        <div
                            class="bg-muted relative aspect-[4/3] overflow-hidden"
                        >
                            <img
                                v-if="recipe.image_url"
                                :src="recipe.image_url"
                                :alt="recipe.title"
                                class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110"
                            />
                            <div
                                v-else
                                class="flex h-full items-center justify-center"
                            >
                                <Utensils
                                    class="text-muted-foreground/20 h-16 w-16"
                                />
                            </div>
                            <div class="absolute top-4 right-4">
                                <div
                                    class="rounded-full border border-white/10 bg-black/60 px-3 py-1.5 text-[10px] font-black tracking-widest text-white uppercase shadow-lg backdrop-blur-xl"
                                >
                                    {{ recipe.category || 'Recipe' }}
                                </div>
                            </div>
                        </div>
                        <CardHeader class="flex-1 p-6">
                            <CardTitle
                                class="group-hover:text-primary line-clamp-2 text-2xl leading-tight font-black tracking-tight transition-colors"
                            >
                                {{ recipe.title }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-6 pt-0">
                            <Button
                                variant="secondary"
                                class="bg-primary/5 hover:bg-primary border-primary/10 h-12 w-full rounded-2xl border-2 font-black tracking-widest uppercase transition-all hover:text-white"
                                @click.stop="addToList(recipe)"
                                :disabled="isAddingToList[recipe.uuid]"
                            >
                                <component
                                    :is="
                                        isAddingToList[recipe.uuid]
                                            ? Check
                                            : ShoppingCart
                                    "
                                    class="mr-2 h-5 w-5"
                                />
                                {{
                                    isAddingToList[recipe.uuid]
                                        ? 'Added!'
                                        : 'Add to List'
                                }}
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <!-- Pagination -->
                <div
                    v-if="pagination.last_page > 1"
                    class="flex items-center justify-center gap-6 py-12"
                >
                    <Button
                        variant="ghost"
                        class="h-14 rounded-2xl border border-white/20 bg-white/40 px-8 font-black tracking-widest uppercase backdrop-blur-xl dark:bg-white/5"
                        :disabled="pagination.current_page === 1"
                        @click="fetchRecipes(pagination.current_page - 1)"
                    >
                        Previous
                    </Button>
                    <div
                        class="flex h-14 items-center rounded-2xl border border-white/20 bg-white/60 px-6 text-xl font-black tabular-nums backdrop-blur-3xl dark:bg-white/5"
                    >
                        {{ pagination.current_page }}
                        <span class="mx-2 opacity-30">/</span>
                        {{ pagination.last_page }}
                    </div>
                    <Button
                        variant="ghost"
                        class="h-14 rounded-2xl border border-white/20 bg-white/40 px-8 font-black tracking-widest uppercase backdrop-blur-xl dark:bg-white/5"
                        :disabled="
                            pagination.current_page === pagination.last_page
                        "
                        @click="fetchRecipes(pagination.current_page + 1)"
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>

        <!-- 2. Full Recipe Detail View -->
        <div
            v-else-if="!isCookingMode"
            class="animate-in fade-in zoom-in-95 slide-in-from-bottom-10 flex min-h-0 flex-1 flex-col overflow-hidden rounded-[3rem] border border-white/40 bg-white/80 shadow-[0_32px_64px_-12px_rgba(0,0,0,0.3)] duration-700 dark:border-white/10 dark:bg-[#000000]"
        >
            <!-- Header (Compact Banner) -->
            <div class="bg-muted group relative h-56 shrink-0 overflow-hidden">
                <img
                    v-if="selectedRecipe.image_url"
                    :src="selectedRecipe.image_url"
                    class="h-full w-full object-cover transition-transform duration-[2000ms] group-hover:scale-105"
                />
                <div
                    class="absolute inset-0 flex items-end bg-gradient-to-t from-black via-black/20 to-transparent p-10"
                >
                    <div
                        class="mx-auto flex w-full max-w-7xl flex-col justify-between gap-4 lg:flex-row lg:items-end"
                    >
                        <div class="space-y-3">
                            <div class="flex items-center gap-4">
                                <Button
                                    variant="ghost"
                                    class="h-10 rounded-xl border border-white/20 bg-white/10 px-6 text-[10px] font-black tracking-widest text-white uppercase shadow-2xl backdrop-blur-3xl transition-all hover:bg-white/30"
                                    @click="closeDetails"
                                >
                                    <ArrowLeft class="mr-2 h-4 w-4" /> Back
                                </Button>
                                <div
                                    class="bg-primary/90 rounded-lg px-3 py-1.5 text-[10px] font-black tracking-[0.2em] text-white uppercase shadow-xl backdrop-blur-xl"
                                >
                                    {{
                                        selectedRecipe.category ||
                                        'Kitchen Secret'
                                    }}
                                </div>
                            </div>
                            <h2
                                class="text-5xl leading-[0.9] font-black tracking-tighter text-white drop-shadow-[0_4px_12px_rgba(0,0,0,0.5)]"
                            >
                                {{ selectedRecipe.title }}
                            </h2>
                        </div>

                        <div
                            class="flex items-center gap-6 text-[10px] font-black tracking-widest text-white/70 uppercase"
                        >
                            <div class="flex items-center gap-2">
                                <Clock class="h-4 w-4" />
                                <span>Prep: 20m</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Utensils class="h-4 w-4" />
                                <span>Cook: 45m</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div
                class="grid min-h-0 flex-1 grid-cols-1 overflow-hidden bg-white lg:grid-cols-12 dark:bg-[#050505]"
            >
                <div
                    class="border-border/40 flex min-h-0 flex-col border-r lg:col-span-4"
                >
                    <div
                        class="flex shrink-0 items-center justify-between p-8 pb-4"
                    >
                        <h4
                            class="text-primary flex items-center gap-3 text-lg font-black tracking-widest uppercase"
                        >
                            <div
                                class="bg-primary shadow-primary/20 h-5 w-2 rounded-full shadow-lg"
                            ></div>
                            Ingredients
                        </h4>
                        <!-- Scaling Control -->
                        <div
                            class="bg-accent/30 border-border/40 flex items-center rounded-full border p-1"
                        >
                            <Button
                                v-for="opt in scaleOptions"
                                :key="opt"
                                variant="ghost"
                                size="sm"
                                class="h-8 rounded-full px-3 text-[10px] font-black"
                                :class="
                                    scaleFactor === opt
                                        ? 'bg-primary text-white'
                                        : ''
                                "
                                @click="scaleFactor = opt"
                            >
                                {{ opt }}x
                            </Button>
                        </div>
                    </div>
                    <div
                        class="custom-scrollbar flex-1 overflow-y-auto p-8 pt-0"
                    >
                        <div
                            class="text-foreground/80 text-xl leading-relaxed font-bold tracking-tight whitespace-pre-wrap"
                        >
                            {{ scaledIngredients || 'No ingredients listed.' }}
                        </div>
                    </div>
                </div>

                <div class="flex min-h-0 flex-col lg:col-span-8">
                    <div class="shrink-0 p-8 pb-4">
                        <h4
                            class="text-primary flex items-center gap-3 text-lg font-black tracking-widest uppercase"
                        >
                            <div
                                class="bg-primary shadow-primary/20 h-5 w-2 rounded-full shadow-lg"
                            ></div>
                            Preparation
                        </h4>
                    </div>
                    <div
                        class="custom-scrollbar flex-1 overflow-y-auto p-8 pt-0"
                    >
                        <div
                            class="text-foreground/90 text-xl leading-relaxed font-medium tracking-tight whitespace-pre-wrap"
                        >
                            {{
                                selectedRecipe.directions ||
                                'No directions listed.'
                            }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Toolbar -->
            <div class="bg-accent/5 flex shrink-0 justify-center border-t p-6">
                <div class="flex w-full max-w-2xl gap-4">
                    <Button
                        variant="ghost"
                        class="bg-muted/20 border-border/50 hover:bg-muted/40 h-14 rounded-2xl border-4 px-8 text-lg font-black transition-all"
                        @click="closeDetails"
                        >Close</Button
                    >
                    <Button
                        class="shadow-primary/20 h-14 flex-1 rounded-2xl px-8 text-lg font-black shadow-lg transition-all hover:scale-[1.02]"
                        @click="addToList(selectedRecipe)"
                        :disabled="isAddingToList[selectedRecipe.uuid]"
                    >
                        <component
                            :is="
                                isAddingToList[selectedRecipe.uuid]
                                    ? Check
                                    : ShoppingCart
                            "
                            class="mr-3 h-6 w-6"
                        />
                        {{
                            isAddingToList[selectedRecipe.uuid]
                                ? 'Added!'
                                : 'Add to Shopping List'
                        }}
                    </Button>
                    <Button
                        variant="outline"
                        class="border-primary bg-primary/10 text-primary hover:bg-primary h-14 rounded-2xl border-4 px-8 text-lg font-black transition-all hover:text-white"
                        @click="isCookingMode = true"
                    >
                        <Play class="mr-3 h-5 w-5 fill-current" /> Cook This
                    </Button>
                </div>
            </div>
        </div>

        <!-- 3. Immersive Cooking Mode (Full Viewport) -->
        <Teleport to="body">
            <div
                v-if="selectedRecipe && isCookingMode"
                class="animate-in fade-in fixed inset-0 z-[100] flex min-h-0 flex-col bg-white duration-500 dark:bg-[#000000]"
            >
                <!-- Full-Screen Header -->
                <div
                    class="bg-primary flex shrink-0 items-center justify-between border-b p-8 text-white shadow-2xl lg:p-12"
                >
                    <div class="flex items-center gap-8">
                        <div
                            class="rounded-3xl bg-white/20 p-5 backdrop-blur-md"
                        >
                            <Utensils class="h-12 w-12" />
                        </div>
                        <div class="max-w-3xl">
                            <p
                                class="text-sm font-black tracking-[0.4em] uppercase opacity-60"
                            >
                                Cooking Mode
                            </p>
                            <h2
                                class="mt-1 truncate text-6xl leading-none font-black tracking-tighter"
                            >
                                {{ selectedRecipe.title }}
                            </h2>
                        </div>
                    </div>

                    <!-- Zoom, Scale & Close Controls -->
                    <div class="flex items-center gap-6">
                        <!-- Scale -->
                        <div
                            class="flex items-center rounded-full border border-white/20 bg-white/10 p-1 backdrop-blur-md"
                        >
                            <div
                                class="flex items-center gap-2 px-4 text-xs font-black tracking-widest uppercase opacity-60"
                            >
                                <Scale class="h-4 w-4" /> Scale
                            </div>
                            <Button
                                v-for="opt in scaleOptions"
                                :key="opt"
                                variant="ghost"
                                class="h-14 rounded-full px-6 text-xl font-black hover:bg-white/20"
                                :class="
                                    scaleFactor === opt
                                        ? 'text-primary bg-white hover:bg-white'
                                        : 'text-white'
                                "
                                @click="scaleFactor = opt"
                            >
                                {{ opt }}x
                            </Button>
                        </div>

                        <!-- Zoom -->
                        <div
                            class="flex items-center rounded-full border border-white/20 bg-white/10 p-1 backdrop-blur-md"
                        >
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-16 w-16 rounded-full text-white hover:bg-white/20"
                                @click="decreaseZoom"
                            >
                                <ZoomOut class="h-8 w-8" />
                            </Button>
                            <div
                                class="min-w-[4rem] px-4 text-center text-xl font-black tabular-nums"
                            >
                                {{ zoomLevel }}%
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-16 w-16 rounded-full text-white hover:bg-white/20"
                                @click="increaseZoom"
                            >
                                <ZoomIn class="h-8 w-8" />
                            </Button>
                        </div>

                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-20 w-20 rounded-full border border-white/20 bg-white/10 text-white shadow-xl hover:bg-white/20"
                            @click="isCookingMode = false"
                        >
                            <X class="h-12 w-12" />
                        </Button>
                    </div>
                </div>

                <!-- Full-Screen Split Pane -->
                <div
                    class="grid min-h-0 flex-1 grid-cols-1 overflow-hidden bg-white lg:grid-cols-12 dark:bg-[#000000]"
                >
                    <!-- Ingredients -->
                    <div
                        class="border-primary/10 bg-accent/5 flex min-h-0 flex-col border-r-8 lg:col-span-4"
                    >
                        <div class="flex shrink-0 items-center gap-6 p-16 pb-8">
                            <ScrollText class="text-primary h-10 w-10" />
                            <h4
                                class="text-4xl font-black tracking-tighter uppercase"
                            >
                                Ingredients
                            </h4>
                        </div>
                        <div
                            class="custom-scrollbar flex-1 overflow-y-auto p-16 pt-0"
                        >
                            <div
                                :style="fontSizeStyle"
                                class="text-foreground/90 font-black tracking-tight whitespace-pre-wrap transition-all duration-300"
                            >
                                {{ scaledIngredients }}
                            </div>
                        </div>
                    </div>

                    <!-- Instructions -->
                    <div class="flex min-h-0 flex-col lg:col-span-8">
                        <div class="flex shrink-0 items-center gap-6 p-16 pb-8">
                            <BookOpen class="text-primary h-10 w-10" />
                            <h4
                                class="text-4xl font-black tracking-tighter uppercase"
                            >
                                Preparation
                            </h4>
                        </div>
                        <div
                            class="custom-scrollbar flex-1 overflow-y-auto p-16 pt-0"
                        >
                            <div
                                :style="fontSizeStyle"
                                class="text-foreground font-bold tracking-tight whitespace-pre-wrap transition-all duration-300"
                            >
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
