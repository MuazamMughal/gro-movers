<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, ShieldCheck, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { create, destroy, edit, index } from '@/routes/admin/roles';

type RoleRow = {
    id: number;
    name: string;
    description: string | null;
    is_system: boolean;
    users_count: number;
    permissions: string[];
};

const props = defineProps<{
    roles: RoleRow[];
    groups: Record<string, Record<string, string>>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Roles', href: index() }],
    },
});

const total = Object.values(props.groups).reduce((n, g) => n + Object.keys(g).length, 0);
const label = (key: string) => Object.values(props.groups).find((g) => key in g)?.[key] ?? key;

const toDelete = ref<RoleRow | null>(null);
const deleting = ref(false);
const confirmDelete = () => {
    if (!toDelete.value) return;
    router.delete(destroy(toDelete.value.id), {
        preserveScroll: true,
        onStart: () => (deleting.value = true),
        onFinish: () => {
            deleting.value = false;
            toDelete.value = null;
        },
    });
};
</script>

<template>
    <Head title="Roles" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading title="Roles" description="What each role can see and change in the admin. Assign roles on the Users page." />
            <Button as-child>
                <Link :href="create()"><Plus class="size-4" /> Add role</Link>
            </Button>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div v-for="role in roles" :key="role.id" class="flex flex-col gap-3 rounded-xl border p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="flex items-center gap-2 font-semibold">
                            <ShieldCheck v-if="role.is_system" class="size-4 text-primary" />
                            {{ role.name }}
                            <Badge variant="secondary">{{ role.users_count }} {{ role.users_count === 1 ? 'user' : 'users' }}</Badge>
                        </h2>
                        <p v-if="role.description" class="mt-1 text-sm text-muted-foreground">{{ role.description }}</p>
                    </div>
                    <div v-if="!role.is_system" class="flex gap-1">
                        <Button variant="ghost" size="icon" as-child>
                            <Link :href="edit(role.id)" :aria-label="`Edit ${role.name}`"><Pencil class="size-4" /></Link>
                        </Button>
                        <Button v-if="!role.users_count" variant="ghost" size="icon" :aria-label="`Delete ${role.name}`" @click="toDelete = role">
                            <Trash2 class="size-4 text-destructive" />
                        </Button>
                    </div>
                </div>
                <p v-if="role.is_system" class="text-sm text-muted-foreground">Every permission, plus Users and Roles. This role cannot be changed.</p>
                <template v-else>
                    <p class="text-xs text-muted-foreground">{{ role.permissions.length }} of {{ total }} permissions</p>
                    <ul class="flex flex-wrap gap-1.5">
                        <li v-for="p in role.permissions" :key="p"><Badge variant="outline">{{ label(p) }}</Badge></li>
                        <li v-if="!role.permissions.length" class="text-sm text-muted-foreground">No permissions: dashboard and own account only.</li>
                    </ul>
                </template>
            </div>
        </div>
    </div>

    <Dialog :open="toDelete !== null" @update:open="(open) => !open && (toDelete = null)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete the {{ toDelete?.name }} role?</DialogTitle>
                <DialogDescription>No users have this role. This cannot be undone.</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="toDelete = null">Cancel</Button>
                <Button variant="destructive" :disabled="deleting" @click="confirmDelete">Delete role</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
