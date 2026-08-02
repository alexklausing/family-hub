import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { useConfirm } from './useConfirm'

describe('useConfirm', () => {
    let confirm

    beforeEach(() => {
        confirm = useConfirm()
        confirm.confirmDismissed()
    })

    afterEach(() => {
        confirm.confirmDismissed()
    })

    it('opens a confirm and resolves true on accept', async () => {
        const promise = confirm.confirmAsync({
            title: 'Delete?',
            message: 'This will be removed.',
            confirmLabel: 'Delete',
        })

        expect(confirm.confirmState.value).toMatchObject({
            title: 'Delete?',
            message: 'This will be removed.',
            confirmLabel: 'Delete',
            destructive: true,
        })

        confirm.confirmAccepted()
        expect(await promise).toBe(true)
        expect(confirm.confirmState.value).toBeNull()
    })

    it('resolves false on dismiss', async () => {
        const promise = confirm.confirmAsync({ title: 'Reset?' })

        confirm.confirmDismissed()
        expect(await promise).toBe(false)
        expect(confirm.confirmState.value).toBeNull()
    })

    it('defaults to non-destructive when flagged', () => {
        confirm.confirmAsync({ title: 'OK?', destructive: false })
        expect(confirm.confirmState.value.destructive).toBe(false)
        confirm.confirmDismissed()
    })
})
