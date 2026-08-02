import { ref } from 'vue'

const confirmState = ref(null)
let pendingResolver = null

export function useConfirm() {
    const confirmAsync = (options = {}) => {
        confirmState.value = {
            title: options.title ?? 'Are you sure?',
            message: options.message ?? '',
            confirmLabel: options.confirmLabel ?? 'Confirm',
            cancelLabel: options.cancelLabel ?? 'Cancel',
            destructive: options.destructive !== false,
        }
        return new Promise((resolve) => {
            pendingResolver = resolve
        })
    }

    const settle = (accepted) => {
        if (pendingResolver) {
            pendingResolver(accepted)
            pendingResolver = null
        }
        confirmState.value = null
    }

    const confirmAccepted = () => settle(true)
    const confirmDismissed = () => settle(false)

    return {
        confirmAsync,
        confirmState,
        confirmAccepted,
        confirmDismissed,
    }
}
