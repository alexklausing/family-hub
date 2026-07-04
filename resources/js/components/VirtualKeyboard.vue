<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import Keyboard from 'simple-keyboard'
import 'simple-keyboard/build/css/index.css'

const keyboardRef = ref(null)
const keyboard = ref(null)
const isVisible = ref(false)
const currentInput = ref(null)

const onChange = (input) => {
    if (currentInput.value) {
        currentInput.value.value = input
        // trigger input event so Vue v-model picks it up
        currentInput.value.dispatchEvent(new Event('input', { bubbles: true }))
    }
}

const onKeyPress = (button) => {
    if (button === '{shift}' || button === '{lock}') {
        handleShift()
    }
    if (button === '{enter}') {
        // Optional: trigger enter key
        if (currentInput.value) {
            currentInput.value.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }))
        }
    }
    if (button === '{close}') {
        isVisible.value = false
        if (currentInput.value) {
            currentInput.value.blur()
            currentInput.value = null
        }
    }
}

const handleShift = () => {
    let currentLayout = keyboard.value.options.layoutName
    let shiftToggle = currentLayout === 'default' ? 'shift' : 'default'
    keyboard.value.setOptions({ layoutName: shiftToggle })
}

const handleFocus = (e) => {
    const target = e.target
    if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA') {
        const type = target.type
        // Don't show for hidden, checkbox, radio, range, etc.
        if (['text', 'password', 'email', 'number', 'search', 'tel', 'url', 'textarea'].includes(type) || target.tagName === 'TEXTAREA') {
            currentInput.value = target
            isVisible.value = true
            nextTick(() => {
                if (keyboard.value) {
                    keyboard.value.setInput(target.value)
                }
            })
        }
    }
}

// Global click outside to close
const handleGlobalClick = (e) => {
    if (!isVisible.value) return
    
    // Check if clicked element is an input or the keyboard itself
    const isInput = e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA'
    const isKeyboard = e.target.closest('.virtual-keyboard-wrapper')
    
    // Check if clicked inside a Radix UI or shadcn popover/dialog
    // Often when you click a select dropdown, it might blur the input.
    // We only want to close if they clearly clicked somewhere else on the page.
    
    if (!isInput && !isKeyboard) {
        // Only hide if we actually click a button or empty space
        isVisible.value = false
        currentInput.value = null
    }
}

// Global input listener to sync external changes
const handleGlobalInput = (e) => {
    if (currentInput.value === e.target && keyboard.value) {
        keyboard.value.setInput(e.target.value)
    }
}

onMounted(() => {
    keyboard.value = new Keyboard(keyboardRef.value, {
        onChange: onChange,
        onKeyPress: onKeyPress,
        layout: {
            default: [
                "` 1 2 3 4 5 6 7 8 9 0 - = {bksp}",
                "q w e r t y u i o p [ ] \\",
                "a s d f g h j k l ; ' {enter}",
                "{shift} z x c v b n m , . / {shift}",
                "{close} @ {space} {close}"
            ],
            shift: [
                "~ ! @ # $ % ^ & * ( ) _ + {bksp}",
                "Q W E R T Y U I O P { } |",
                "A S D F G H J K L : \" {enter}",
                "{shift} Z X C V B N M < > ? {shift}",
                "{close} @ {space} {close}"
            ]
        },
        display: {
            '{bksp}': 'delete',
            '{enter}': 'return',
            '{shift}': 'shift',
            '{space}': 'space',
            '{close}': 'hide keyboard',
        },
        theme: "hg-theme-default hg-layout-default my-theme"
    })
    
    document.addEventListener('focusin', handleFocus, true)
    document.addEventListener('input', handleGlobalInput, true)
    document.addEventListener('click', handleGlobalClick, true)
})

onUnmounted(() => {
    document.removeEventListener('focusin', handleFocus, true)
    document.removeEventListener('input', handleGlobalInput, true)
    document.removeEventListener('click', handleGlobalClick, true)
    if (keyboard.value) keyboard.value.destroy()
})
</script>

<template>
    <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-y-full"
        enter-to-class="translate-y-0"
        leave-active-class="transition-transform duration-300 ease-in"
        leave-from-class="translate-y-0"
        leave-to-class="translate-y-full"
    >
        <div v-show="isVisible" class="virtual-keyboard-wrapper pointer-events-auto fixed bottom-0 left-0 right-0 z-[9999] bg-[#e2e2e4] dark:bg-[#1c1c1e] p-4 pt-5 shadow-[0_-20px_60px_rgba(0,0,0,0.5)] border-t border-white/20 backdrop-blur-3xl">
            <div ref="keyboardRef" class="max-w-[1400px] mx-auto"></div>
        </div>
    </Transition>
</template>

<style>
/* Custom theme for the keyboard to match our premium aesthetic */
.my-theme {
    background-color: transparent !important;
}

.my-theme .hg-button {
    height: 70px !important;
    border-radius: 12px !important;
    font-size: 26px !important;
    font-weight: 500 !important;
    background: #ffffff !important;
    color: #000000 !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2), 0 2px 2px rgba(0,0,0,0.1) !important;
    border-bottom: 2px solid #b5b5b5 !important;
    margin: 4px !important;
}

.dark .my-theme .hg-button {
    background: #4a4a4b !important;
    color: #ffffff !important;
    border-bottom: 2px solid #1c1c1e !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.4), 0 2px 2px rgba(0,0,0,0.3) !important;
}

.my-theme .hg-button:active {
    background: #d4d4d4 !important;
    transform: translateY(2px) !important;
    border-bottom: 0px solid transparent !important;
}

.dark .my-theme .hg-button:active {
    background: #3a3a3b !important;
}

/* Special buttons */
.my-theme .hg-button-shift,
.my-theme .hg-button-bksp,
.my-theme .hg-button-enter,
.my-theme .hg-button-close {
    background: #b4b4b9 !important;
    font-weight: 700 !important;
}

.dark .my-theme .hg-button-shift,
.dark .my-theme .hg-button-bksp,
.dark .my-theme .hg-button-enter,
.dark .my-theme .hg-button-close {
    background: #2c2c2e !important;
}

.my-theme .hg-button-space {
    width: 40% !important;
}
</style>
