<script setup>
import { ref } from 'vue'
import { ChevronDown } from 'lucide-vue-next'
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover'
import { Button } from '@/components/ui/button'

const props = defineProps({
    modelValue: { type: String, default: '🌟' },
})

const emit = defineEmits(['update:modelValue', 'select'])

const isOpen = ref(false)

const emojis = [
    '😀',
    '😄',
    '😁',
    '😊',
    '😍',
    '🤩',
    '🥳',
    '😎',
    '🤗',
    '😅',
    '😇',
    '🙃',
    '😋',
    '😴',
    '😢',
    '🎉',
    '🎊',
    '🎂',
    '🎁',
    '🎈',
    '🎀',
    '🎯',
    '🏆',
    '🥇',
    '🎮',
    '🎨',
    '🎵',
    '🎬',
    '📚',
    '🎢',
    '🎪',
    '🐶',
    '🐱',
    '🐰',
    '🦊',
    '🐼',
    '🐨',
    '🐢',
    '🦄',
    '🌈',
    '🌻',
    '🌸',
    '🍀',
    '🌊',
    '🌙',
    '⭐',
    '☀️',
    '❄️',
    '🔥',
    '🍃',
    '🌲',
    '🍂',
    '🌵',
    '🦋',
    '🐝',
    '🍕',
    '🍔',
    '🌮',
    '🍦',
    '🍩',
    '🧁',
    '🍪',
    '🍓',
    '🍎',
    '🥑',
    '☕',
    '🍿',
    '🍉',
    '🥕',
    '🍗',
    '🍜',
    '✈️',
    '🚗',
    '🚲',
    '🏖️',
    '🏔️',
    '🏰',
    '🗽',
    '⛺',
    '🚀',
    '🚂',
    '🏠',
    '🏡',
    '⚽',
    '🏀',
    '⚾',
    '🏈',
    '🎾',
    '🏊',
    '⛷️',
    '🛝',
    '❤️',
    '💙',
    '💚',
    '💛',
    '💜',
    '💯',
    '✨',
    '🌟',
    '💝',
    '🕯️',
    '🎄',
    '🎃',
    '🥚',
    '🎆',
    '🎇',
    '🧸',
    '🪁',
    '📷',
    '🎸',
    '🩷',
]

const selectEmoji = (emoji) => {
    emit('update:modelValue', emoji)
    emit('select', { i: emoji })
    isOpen.value = false
}
</script>

<template>
    <Popover v-model:open="isOpen">
        <PopoverTrigger as-child>
            <Button
                variant="outline"
                type="button"
                class="h-10 w-full justify-start rounded-xl border-slate-200 bg-white text-left font-normal dark:border-white/10 dark:bg-white/5"
            >
                <span class="mr-2 text-xl">{{ props.modelValue || '🌟' }}</span>
                <span class="flex-1">Select an emoji</span>
                <ChevronDown class="h-4 w-4 opacity-40" />
            </Button>
        </PopoverTrigger>
        <PopoverContent
            align="start"
            side="bottom"
            :side-offset="8"
            class="w-auto rounded-2xl border-none bg-white/95 p-3 shadow-xl backdrop-blur-3xl dark:bg-black/95"
        >
            <div class="grid grid-cols-8 gap-1">
                <button
                    v-for="emoji in emojis"
                    :key="emoji"
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-lg text-xl transition-colors hover:bg-slate-100 dark:hover:bg-white/10"
                    @click="selectEmoji(emoji)"
                >
                    {{ emoji }}
                </button>
            </div>
        </PopoverContent>
    </Popover>
</template>
