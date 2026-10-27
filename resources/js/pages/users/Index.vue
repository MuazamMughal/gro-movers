<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, destroy, edit, index } from '@/routes/admin/users';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: { id: number; name: string; is_system: boolean } | null;
    last_login_at: string | null;
    created_at: string;
};

const props = defineProps<{
    users: AdminUser[];
    currentUserId: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: index() }],
    },
});

const toDelete = ref<AdminUser | null>(null);
const deleting = ref(false);

const confirmDelete = () => {
    if (!toDelete.value) {
        return;
    }

    router.delete(destroy(toDelete.value.id), {
        preserveScroll: true,
        onStart: () => (deleting.value = true),
        onFinish: () => {
            deleting.value = false;
            toDelete.value = null;
        },
    });
};

// The server also refuses deleting yourself or the last Administrator.
const administrators = computed(() => props.users.filter((u) => u.role?.is_system).length);
const canDelete = (user: AdminUser) =>
    user.id !== props.currentUserId && !(user.role?.is_system && administrators.value <= 1);

const formatDate = (iso: string | null) =>
    iso
        ? new Intl.DateTimeFormat('en-US', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(iso))
        : 'Never';
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Users"
                description="Admin accounts. Every admin has full access to the panel."
            />
            <Button as-child>
                <Link :href="create()"><Plus class="size-4" /> Add admin</Link>
            </Button>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Role</th>
                        <th class="px-4 py-3 font-medium">Last login</th>
                        <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="user in users" :key="user.id">
                        <td class="px-4 py-3 font-medium">
                            {{ user.name }}
                            <Badge
                                v-if="user.id === currentUserId"
                                variant="secondary"
                                class="ml-1"
                                >You</Badge
                            >
                        </td>
                        <td class="px-4 py-3">{{ user.email }}</td>
                        <td class="px-4 py-3">
                            <Badge :variant="user.role?.is_system ? 'default' : 'outline'">{{ user.role?.name ?? 'No role' }}</Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ formatDate(user.last_login_at) }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <Button variant="ghost" size="icon" as-child>
                                    <Link
                                        :href="edit(user.id)"
                                        :aria-label="`Edit ${user.name}`"
                                        ><Pencil class="size-4"
                                    /></Link>
                                </Button>
                                <Button
                                    v-if="canDelete(user)"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Delete ${user.name}`"
                                    @click="toDelete = user"
                                >
                                    <Trash2 class="size-4 text-destructive" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <ConfirmDialog
        :open="toDelete !== null"
        :title="`Delete ${toDelete?.name}?`"
        description="This admin will no longer be able to sign in. This cannot be undone."
        confirm-label="Delete admin"
        destructive
        :processing="deleting"
        @update:open="(open) => !open && (toDelete = null)"
        @confirm="confirmDelete"
    />
</template>
