import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Warns before leaving a page with unsaved form changes: Inertia link clicks
 * (confirm dialog) and closing or reloading the tab (browser prompt).
 *
 *   const form = useForm({...});
 *   useUnsavedGuard(() => form.isDirty && !form.processing);
 */
export function useUnsavedGuard(isDirty: () => boolean, message = 'You have unsaved changes. Leave this page without saving?') {
    let removeListener: (() => void) | undefined;

    const onBeforeUnload = (e: BeforeUnloadEvent) => {
        if (isDirty()) {
            e.preventDefault();
            e.returnValue = '';
        }
    };

    onMounted(() => {
        removeListener = router.on('before', (event) => {
            const visit = (event as CustomEvent).detail?.visit;

            // Only guard navigations away (GET), not the form's own save requests.
            if (visit?.method === 'get' && isDirty() && !window.confirm(message)) {
                event.preventDefault();
            }
        });

        window.addEventListener('beforeunload', onBeforeUnload);
    });

    onBeforeUnmount(() => {
        removeListener?.();
        window.removeEventListener('beforeunload', onBeforeUnload);
    });
}
