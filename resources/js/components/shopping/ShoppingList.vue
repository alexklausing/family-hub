<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import {
    ShoppingCart,
    Plus,
    RefreshCw,
    Eraser,
    PackageCheck,
    Trash2,
} from 'lucide-vue-next'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog'

const categories = ref({})
const newItemName = ref('')
const isLoading = ref(false)
const isSyncing = ref(false)
const isClearDialogOpen = ref(false)

const fetchItems = async (sync = false) => {
    isLoading.value = sync ? false : true
    if (sync) isSyncing.value = true

    try {
        const response = await axios.get('/api/shopping-list', {
            params: { sync: sync ? 1 : undefined },
        })
        categories.value = response.data
    } catch (error) {
        console.error('Failed to fetch shopping list:', error)
    } finally {
        isLoading.value = false
        isSyncing.value = false
    }
}

const addItem = async () => {
    if (!newItemName.value) return

    try {
        await axios.post('/api/shopping-list', {
            name: newItemName.value,
        })
        newItemName.value = ''
        fetchItems()
    } catch (error) {
        console.error('Failed to add item:', error)
    }
}

const toggleItem = async (itemGroup) => {
    // If it's grouped, we toggle all items to match the new state
    const newPurchasedState = !itemGroup.purchased
    itemGroup.purchased = newPurchasedState // Optimistic UI update

    try {
        const promises = itemGroup.originalItems.map(item => {
            if (item.purchased !== newPurchasedState) {
                item.purchased = newPurchasedState
                return axios.post(`/api/shopping-list/${item.id}/toggle`, {
                    purchased: newPurchasedState,
                })
            }
            return Promise.resolve()
        })
        await Promise.all(promises)
    } catch (error) {
        console.error('Failed to toggle item:', error)
        fetchItems() // Revert UI
    }
}

const clearList = async () => {
    isClearDialogOpen.value = false
    isLoading.value = true
    try {
        await axios.delete('/api/shopping-list')
        fetchItems()
    } catch (error) {
        console.error('Failed to clear list:', error)
        isLoading.value = false
    }
}

const deleteSingleItem = async (itemGroup) => {
    try {
        const promises = itemGroup.originalItems.map(item => 
            axios.delete(`/api/shopping-list/${item.id}`)
        )
        await Promise.all(promises)
        fetchItems()
    } catch (error) {
        console.error('Failed to delete item:', error)
    }
}

// Gesture State
const swipeState = ref({})

// Computed Grouping
const processedCategories = computed(() => {
    const result = {}
    for (const [category, categoryItems] of Object.entries(categories.value)) {
        const grouped = {}
        for (const item of categoryItems) {
            const key = (item.name || '').toLowerCase().trim()
            if (!grouped[key]) {
                grouped[key] = {
                    id: `group-${item.id}`,
                    name: item.name,
                    quantities: item.quantity ? [item.quantity] : [],
                    originalItems: [item],
                    purchased: item.purchased,
                    recipes: item.recipe ? [item.recipe] : [],
                }
            } else {
                grouped[key].originalItems.push(item)
                if (item.quantity && !grouped[key].quantities.includes(item.quantity)) {
                    grouped[key].quantities.push(item.quantity)
                }
                if (item.recipe && !grouped[key].recipes.find(r => r.id === item.recipe.id)) {
                    grouped[key].recipes.push(item.recipe)
                }
                grouped[key].purchased = grouped[key].purchased && item.purchased
            }
        }
        result[category] = Object.values(grouped).map(group => {
            group.displayQuantity = group.quantities.length > 0 ? group.quantities.join(' + ') : ''
            return group
        })
    }
    return result
})

// Touch Handlers
const handleTouchStart = (e, item) => {
    if (!swipeState.value[item.id]) {
        swipeState.value[item.id] = { startX: 0, currentX: 0, isOpen: false, isSwiping: false, wasDragged: false }
    }
    const state = swipeState.value[item.id]
    const clientX = e.touches ? e.touches[0].clientX : e.clientX
    state.startX = clientX
    state.isSwiping = true
    state.wasDragged = false
}

const handleTouchMove = (e, item) => {
    const state = swipeState.value[item.id]
    if (!state?.isSwiping) return
    const clientX = e.touches ? e.touches[0].clientX : e.clientX
    const diff = clientX - state.startX
    
    if (Math.abs(diff) > 10) {
        state.wasDragged = true
    }
    
    let newX = state.isOpen ? diff - 80 : diff
    if (newX > 0) newX = 0
    if (newX < -100) newX = -100
    
    state.currentX = newX
}

const handleTouchEnd = (e, item) => {
    const state = swipeState.value[item.id]
    if (!state?.isSwiping) return
    state.isSwiping = false
    
    if (state.currentX < -40) {
        state.isOpen = true
        state.currentX = -80
    } else {
        state.isOpen = false
        state.currentX = 0
    }
}

const handleItemClick = (item) => {
    const state = swipeState.value[item.id]
    if (state?.wasDragged) return
    if (state?.isOpen) {
        state.isOpen = false
        state.currentX = 0
        return
    }
    toggleItem(item)
}

onMounted(() => {
    fetchItems()
})
</script>

<template>
    <div class="flex h-full flex-col gap-8 p-2">
        <!-- Add Item Bar (iOS Style) -->
        <div class="mx-auto flex w-full max-w-3xl items-center gap-4">
            <div class="group relative flex-1">
                <ShoppingCart
                    class="text-muted-foreground absolute top-1/2 left-4 h-6 w-6 -translate-y-1/2 opacity-40 transition-opacity group-focus-within:opacity-100"
                />
                <Input
                    v-model="newItemName"
                    placeholder="Add something to your list..."
                    class="focus:ring-primary/20 h-16 rounded-3xl border-white/20 bg-white/40 pl-12 text-2xl font-bold tracking-tight shadow-xl backdrop-blur-xl transition-all dark:border-white/10 dark:bg-white/5"
                    @keyup.enter="addItem"
                />
            </div>
            <Button
                class="shadow-primary/20 h-16 rounded-[2rem] px-10 text-xl font-black shadow-lg transition-all hover:scale-105 active:scale-95"
                @click="addItem"
            >
                <Plus class="mr-2 h-7 w-7" /> Add
            </Button>

            <div class="flex items-center gap-2">
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-16 w-16 rounded-[2rem] border border-white/20 bg-white/40 shadow-xl backdrop-blur-xl dark:border-white/10 dark:bg-white/5"
                    @click="fetchItems(true)"
                    :disabled="isSyncing"
                >
                    <RefreshCw
                        :class="['h-7 w-7', isSyncing ? 'animate-spin' : '']"
                    />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="bg-destructive/10 dark:bg-destructive/20 border-destructive/20 text-destructive hover:bg-destructive h-16 w-16 rounded-[2rem] border shadow-xl transition-all hover:text-white"
                    @click="isClearDialogOpen = true"
                >
                    <Eraser class="h-7 w-7" />
                </Button>
            </div>
        </div>

        <div v-if="isLoading" class="flex flex-1 items-center justify-center">
            <RefreshCw class="text-primary h-16 w-16 animate-spin" />
        </div>

        <div
            v-else-if="Object.keys(categories).length === 0"
            class="flex flex-1 flex-col items-center justify-center opacity-30"
        >
            <ShoppingCart class="mb-6 h-40 w-40" />
            <p class="text-4xl font-black tracking-tighter uppercase italic">
                Shopping List Empty
            </p>
        </div>

        <div v-else class="custom-scrollbar flex-1 overflow-y-auto pr-6 pb-10">
            <div class="grid grid-cols-1 gap-10 md:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="(items, category) in processedCategories"
                    :key="category"
                    class="space-y-6"
                >
                    <h3
                        class="text-primary flex items-center gap-4 pl-2 text-2xl font-black tracking-widest uppercase"
                    >
                        <div
                            class="bg-primary shadow-primary/20 h-8 w-3 rounded-full shadow-lg"
                        ></div>
                        {{ category || 'General' }}
                        <span
                            class="ml-auto text-sm font-bold tabular-nums opacity-40"
                            >{{ items.length }} Items</span
                        >
                    </h3>

                    <div class="space-y-3">
                        <div
                            v-for="item in items"
                            :key="item.id"
                            class="group relative overflow-hidden rounded-3xl border border-black/10 border-t-black/20 dark:border-white/10 dark:border-t-white/20 bg-destructive/90 shadow-sm"
                            :class="{
                                'opacity-50 grayscale': item.purchased,
                            }"
                        >
                            <!-- Background Delete Button -->
                            <div class="absolute inset-y-0 right-0 w-[80px] flex items-center justify-center text-white cursor-pointer hover:bg-destructive transition-colors z-0" @click.stop="deleteSingleItem(item)">
                                <Trash2 class="h-6 w-6" />
                            </div>

                            <!-- Swipeable Foreground -->
                            <div 
                                class="relative z-10 flex items-center space-x-4 bg-[#f8f9fa] dark:bg-[#1a1a1a] p-4 cursor-pointer select-none [@media(hover:hover)]:group-hover:-translate-x-[80px]"
                                :class="!swipeState[item.id]?.isSwiping ? 'duration-300 ease-out transition-transform' : ''"
                                :style="swipeState[item.id]?.currentX ? { transform: `translateX(${swipeState[item.id].currentX}px)` } : {}"
                                @touchstart="handleTouchStart($event, item)"
                                @touchmove="handleTouchMove($event, item)"
                                @touchend="handleTouchEnd($event, item)"
                                @mousedown="handleTouchStart($event, item)"
                                @mousemove="handleTouchMove($event, item)"
                                @mouseup="handleTouchEnd($event, item)"
                                @mouseleave="handleTouchEnd($event, item)"
                                @dragstart.prevent
                                @click="handleItemClick(item)"
                            >
                                <Checkbox
                                    :id="`item-${item.id}`"
                                    :checked="item.purchased"
                                    @update:checked="handleItemClick(item)"
                                    class="h-7 w-7 rounded-full border-2 transition-colors data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                                />
                                <div class="flex-1 flex flex-col justify-center min-w-0">
                                    <Label
                                        :for="`item-${item.id}`"
                                        class="cursor-pointer text-lg leading-tight font-semibold tracking-tight select-none flex items-center flex-wrap gap-2 truncate w-full"
                                        :class="{
                                            'text-muted-foreground line-through':
                                                item.purchased,
                                        }"
                                    >
                                        {{ item.name }}
                                        <Badge v-for="recipe in item.recipes" :key="recipe.id" variant="secondary" class="text-[10px] uppercase font-bold py-0 h-5 px-2 bg-primary/10 text-primary border-none">
                                            {{ recipe.title }}
                                        </Badge>
                                    </Label>
                                    <span v-if="item.displayQuantity" class="text-sm font-medium text-muted-foreground truncate w-full block mt-0.5">
                                        {{ item.displayQuantity }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Dialog (iOS Style) -->
        <Dialog v-model:open="isClearDialogOpen">
            <DialogContent
                class="animate-in fade-in zoom-in-95 rounded-[3rem] border-none bg-white/95 p-10 shadow-2xl backdrop-blur-3xl duration-300 sm:max-w-[500px] dark:bg-black/95"
            >
                <DialogHeader class="text-center">
                    <div
                        class="bg-destructive/10 text-destructive mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full"
                    >
                        <Eraser class="h-10 w-10" />
                    </div>
                    <DialogTitle class="text-4xl font-black tracking-tight"
                        >Clear Entire List?</DialogTitle
                    >
                    <DialogDescription
                        class="mt-2 text-xl font-bold opacity-60"
                    >
                        Every single item will be permanently removed from
                        Paprika.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-8 flex flex-col gap-4 sm:flex-col">
                    <Button
                        class="bg-destructive hover:bg-destructive/90 shadow-destructive/20 h-18 w-full rounded-[2rem] py-6 text-2xl font-black shadow-xl transition-all active:scale-95"
                        @click="clearList"
                        >Yes, Wipe it Clean</Button
                    >
                    <Button
                        variant="ghost"
                        class="h-18 w-full rounded-[2rem] py-6 text-xl font-bold opacity-60 transition-all hover:opacity-100"
                        @click="isClearDialogOpen = false"
                        >Cancel</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
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
