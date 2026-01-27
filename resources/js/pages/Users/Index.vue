<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Users Management" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Users Management</CardTitle>
                            <CardDescription>Manage application users, roles, and access status.</CardDescription>
                        </div>
                        <Button @click="openModal()">
                            <i class="fas fa-plus mr-2"></i> Create User
                        </Button>
                    </CardHeader>

                    <CardContent>
                        <div class="rounded-md border">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-muted/50 font-medium h-12">
                                    <tr
                                        class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                        <th class="px-4 align-middle font-medium text-muted-foreground">Name</th>
                                        <th class="px-4 align-middle font-medium text-muted-foreground">Email</th>
                                        <th class="px-4 align-middle font-medium text-muted-foreground">Role</th>
                                        <th class="px-4 align-middle font-medium text-muted-foreground">Direct
                                            Permissions</th>
                                        <th class="px-4 align-middle font-medium text-muted-foreground">Status</th>
                                        <th class="px-4 align-middle font-medium text-muted-foreground text-right">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="user in users.data" :key="user.id"
                                        class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                        <td class="p-4 align-middle font-medium">{{ user.name }}</td>
                                        <td class="p-4 align-middle text-muted-foreground">{{ user.email }}</td>
                                        <td class="p-4 align-middle">
                                            <Badge v-if="user.roles && user.roles.length > 0" variant="secondary"
                                                class="font-normal">
                                                {{ user.roles[0].name }}
                                            </Badge>
                                            <span v-else class="text-muted-foreground text-xs italic">No Role</span>
                                        </td>
                                        <td class="p-4 align-middle">
                                            <div class="flex flex-wrap gap-1">
                                                <Badge v-for="perm in user.permissions.slice(0, 3)" :key="perm.id"
                                                    variant="outline" class="text-xs border-dashed">
                                                    {{ perm.name }}
                                                </Badge>
                                                <span v-if="user.permissions.length > 3"
                                                    class="text-xs text-muted-foreground self-center">+{{
                                                        user.permissions.length - 3 }} more</span>
                                                <span v-if="!user.permissions || user.permissions.length === 0"
                                                    class="text-muted-foreground text-xs">-</span>
                                            </div>
                                        </td>
                                        <td class="p-4 align-middle">
                                            <Badge :variant="user.is_active ? 'default' : 'destructive'"
                                                class="capitalize">
                                                {{ user.is_active ? 'Active' : 'Suspended' }}
                                            </Badge>
                                        </td>
                                        <td class="p-4 align-middle text-right">
                                            <Button variant="ghost" size="sm" class="mr-2"
                                                @click="openModal(user)">Edit</Button>
                                            <Button variant="ghost" size="sm"
                                                :class="user.is_active ? 'text-destructive hover:text-destructive hover:bg-destructive/10' : 'text-green-600 hover:text-green-600 hover:bg-green-50'"
                                                @click="toggleStatus(user)">
                                                {{ user.is_active ? 'Suspend' : 'Activate' }}
                                            </Button>
                                        </td>
                                    </tr>
                                    <tr v-if="!users.data || users.data.length === 0">
                                        <td colspan="6" class="p-4 text-center text-muted-foreground">No users found.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Placeholder -->
                        <div v-if="users.meta && users.meta.last_page > 1" class="mt-4 flex justify-end">
                            <!-- Implement Pagination Component if available -->
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Dialog :open="showModal" @update:open="val => !val && closeModal()">
            <DialogContent class="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Edit User' : 'Create New User' }}</DialogTitle>
                    <DialogDescription>
                        {{ isEditing ? 'Update user details and permissions.' : 'Add a new user to the system.' }}
                    </DialogDescription>
                </DialogHeader>

                <div class="py-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Name -->
                    <div class="space-y-2">
                        <Label>Name</Label>
                        <Input v-model="form.name" />
                        <p v-if="errors.name" class="text-destructive text-xs">{{ errors.name }}</p>
                    </div>

                    <!-- Email -->
                    <div class="space-y-2">
                        <Label>Email</Label>
                        <Input v-model="form.email" type="email" />
                        <p v-if="errors.email" class="text-destructive text-xs">{{ errors.email }}</p>
                    </div>

                    <!-- Password -->
                    <div class="space-y-2 md:col-span-2">
                        <Label>Password</Label>
                        <Input v-model="form.password" type="password"
                            :placeholder="isEditing ? 'Leave empty to keep unchanged' : ''" />
                        <p v-if="errors.password" class="text-destructive text-xs">{{ errors.password }}</p>
                    </div>

                    <!-- Role -->
                    <div class="space-y-2">
                        <Label>Role</Label>
                        <Select v-model="form.role_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select Role..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="role in availableRoles" :key="role.id" :value="String(role.id)">
                                    {{ role.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.role_id" class="text-destructive text-xs">{{ errors.role_id }}</p>
                    </div>

                    <!-- Status -->
                    <div class="space-y-2 flex items-center pt-8">
                        <div class="flex items-center space-x-2">
                            <input type="checkbox" id="is_active" v-model="form.is_active"
                                class="rounded border-input text-primary focus:ring-ring h-4 w-4">
                                <Label for="is_active" class="cursor-pointer mb-0">Active Account</Label>
                        </div>
                    </div>

                    <!-- Direct Permissions -->
                    <div class="md:col-span-2 space-y-2 pt-2 border-t mt-2">
                        <div @click="showPermissions = !showPermissions"
                            class="flex items-center justify-between cursor-pointer p-2 hover:bg-muted/50 rounded-md">
                            <Label class="cursor-pointer font-semibold">Additional Direct Permissions</Label>
                            <i :class="showPermissions ? 'fa-chevron-up' : 'fa-chevron-down'"
                                class="fas text-muted-foreground text-xs"></i>
                        </div>

                        <div v-show="showPermissions"
                            class="border rounded-md p-4 max-h-48 overflow-y-auto bg-muted/20">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div v-for="perm in availablePermissions" :key="perm.id"
                                    class="flex items-center space-x-2">
                                    <input :id="'u-perm-' + perm.id" v-model="form.permissions" :value="perm.id"
                                        type="checkbox"
                                        class="rounded border-input text-primary focus:ring-ring h-4 w-4">
                                        <label :for="'u-perm-' + perm.id"
                                            class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer">
                                            {{ perm.name }}
                                        </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="closeModal">Cancel</Button>
                    <Button @click="saveUser">
                        {{ isEditing ? 'Update' : 'Create' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';

// Shadcn Components
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/app/users' },
];

const users = ref({ data: [] });
const availableRoles = ref([]);
const availablePermissions = ref([]);
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const showPermissions = ref(false);
const errors = ref({});

const form = ref({
    name: '',
    email: '',
    password: '',
    role_id: '',
    is_active: true,
    permissions: []
});

const fetchUsers = async () => {
    try {
        const res = await axios.get('/app/users');
        users.value = res.data;
    } catch (e) {
        console.error("Error fetching users", e);
    }
};

const fetchRoles = async () => {
    try {
        const res = await axios.get('/app/roles'); // Reusing the roles endpoint
        availableRoles.value = res.data.data || res.data;
    } catch (e) {
        console.error("Error fetching roles", e);
    }
};

const fetchPermissions = async () => {
    try {
        const res = await axios.get('/app/permissions');
        availablePermissions.value = res.data;
    } catch (e) {
        console.error("Error fetching permissions", e);
    }
};

onMounted(() => {
    fetchUsers();
    fetchRoles();
    fetchPermissions();
});

const openModal = (user = null) => {
    errors.value = {};
    showPermissions.value = false;
    if (user) {
        isEditing.value = true;
        editingId.value = user.id;
        form.value.name = user.name;
        form.value.email = user.email;
        form.value.password = '';
        form.value.is_active = !!user.is_active;
        form.value.role_id = (user.roles && user.roles.length > 0) ? String(user.roles[0].id) : '';
        form.value.permissions = user.permissions ? user.permissions.map(p => p.id) : [];
    } else {
        isEditing.value = false;
        editingId.value = null;
        form.value = {
            name: '',
            email: '',
            password: '',
            role_id: '',
            is_active: true,
            permissions: []
        };
    }
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const saveUser = async () => {
    try {
        if (isEditing.value) {
            await axios.put(`/app/users/${editingId.value}`, form.value);
        } else {
            await axios.post('/app/users', form.value);
        }
        closeModal();
        fetchUsers();
    } catch (e) {
        if (e.response && e.response.data.errors) {
            errors.value = e.response.data.errors;
        }
        console.error("Error saving user", e);
    }
};

const toggleStatus = async (user) => {
    try {
        await axios.put(`/app/users/${user.id}`, {
            is_active: !user.is_active
        });
        fetchUsers();
    } catch (e) {
        console.error("Error toggling status", e);
    }
};
</script>
