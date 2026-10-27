import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * What the signed-in user's role allows (T19, config/permissions.php).
 * The server enforces every permission; this only hides what cannot be used.
 */
export function useAccess() {
    const access = computed(() => usePage().props.access);

    return {
        role: computed(() => access.value?.role ?? null),
        isAdministrator: computed(() => access.value?.administrator ?? false),
        can: (permission: string) => access.value?.administrator || (access.value?.permissions ?? []).includes(permission),
    };
}
