<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/users';

const props = defineProps<{
    user: { id: number; name: string; email: string; role_id: number | null } | null;
    roles: { id: number; name: string; description: string | null; is_system: boolean }[];
    isSelf?: boolean;
    passwordRules: string;
}>();

const isEdit = computed(() => props.user !== null);
const title = computed(() => (isEdit.value ? 'Edit user' : 'Add user'));
const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:opacity-60 dark:bg-input/30';

const action = computed(() =>
    props.user
        ? UserController.update.form(props.user.id)
        : UserController.store.form(),
);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: index() }],
    },
});
</script>

<template>
    <Head :title="title" />

    <div class="flex max-w-xl flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="title"
            :description="
                isEdit
                    ? 'Leave the password empty to keep the current one.'
                    : 'The new user can sign in at /admin/login with these details.'
            "
        />

        <Form
            v-bind="action"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="user?.name"
                    required
                    autocomplete="off"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    :default-value="user?.email"
                    required
                    autocomplete="off"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="role">Role</Label>
                <!-- Your own role is fixed, so you cannot lock yourself out. -->
                <input v-if="isSelf" type="hidden" name="role_id" :value="user?.role_id ?? ''" />
                <select id="role" :name="isSelf ? undefined : 'role_id'" :class="selectClass" :disabled="isSelf" required>
                    <option value="" disabled :selected="!user?.role_id">Choose a role…</option>
                    <option v-for="role in roles" :key="role.id" :value="role.id" :selected="user?.role_id === role.id">
                        {{ role.name }}{{ role.description ? ` — ${role.description}` : '' }}
                    </option>
                </select>
                <p v-if="isSelf" class="text-sm text-muted-foreground">You cannot change your own role.</p>
                <InputError :message="errors.role_id" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{
                    isEdit ? 'New password' : 'Password'
                }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    :required="!isEdit"
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    :required="!isEdit"
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-3">
                <Button :disabled="processing">
                    {{ isEdit ? 'Save changes' : 'Create user' }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="index()">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
