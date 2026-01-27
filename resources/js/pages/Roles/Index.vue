<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Roles & Permissions" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Roles Management</CardTitle>
                            <CardDescription>Manage user roles and their associated permissions.</CardDescription>
                        </div>
                        <Button @click="openModal()">
                            <i class="fas fa-plus mr-2"></i> Create Role
                        </Button>
                    </CardHeader>

                    <CardContent>
                        <div class="rounded-md border">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-muted/50 font-medium">
                                    <tr
                                        class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                        <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Role Name
                                        </th>
                                        <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Guard</th>
                                        <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Permissions
                                        </th>
                                        <th class="h-12 px-4 align-middle font-medium text-muted-foreground text-right">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="role in roles.data" :key="role.id"
                                        class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                        <td class="p-4 align-middle font-medium">{{ role.name }}</td>
                                        <td class="p-4 align-middle text-muted-foreground">{{ role.guard_name }}</td>
                                        <td class="p-4 align-middle">
                                            <div class="flex flex-wrap gap-1">
                                                <Badge v-for="perm in role.permissions.slice(0, 5)" :key="perm.id"
                                                    variant="secondary" class="text-xs">
                                                    {{ perm.name }}
                                                </Badge>
                                                <Badge v-if="role.permissions.length > 5" variant="outline"
                                                    class="text-xs">
                                                    +{{ role.permissions.length - 5 }} more
                                                </Badge>
                                            </div>
                                        </td>
                                        <td class="p-4 align-middle text-right">
                                            <Button variant="ghost" size="sm" class="mr-2"
                                                @click="openModal(role)">Edit</Button>
                                            <Button variant="ghost" size="sm"
                                                class="text-destructive hover:text-destructive"
                                                @click="deleteRole(role.id)">Delete</Button>
                                        </td>
                                    </tr>
                                    <tr v-if="!roles.data || roles.data.length === 0">
                                        <td colspan="4" class="p-4 text-center text-muted-foreground">No roles found.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Dialog :open="showModal" @update:open="val => !val && closeModal()">
            <DialogContent class="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Edit Role' : 'Create New Role' }}</DialogTitle>
                    <DialogDescription>
                        Configure the role details and assign permissions.
                    </DialogDescription>
                </DialogHeader>

                <div class="py-4 space-y-4">
                    <div class="space-y-2">
                        <Label>Role Name</Label>
                        <Input v-model="form.name" placeholder="e.g. Manager" />
                        <p v-if="errors.name" class="text-destructive text-xs">{{ errors.name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Permissions</Label>
                        <div class="border rounded-md p-4 max-h-60 overflow-y-auto bg-muted/20">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div v-for="permission in allPermissions" :key="permission.id"
                                    class="flex items-center space-x-2">
                                    <input :id="'perm-' + permission.id" v-model="form.permissions"
                                        :value="permission.id" type="checkbox"
                                        class="rounded border-input text-primary focus:ring-ring h-4 w-4">
                                        <label :for="'perm-' + permission.id"
                                            class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer">
                                            {{ permission.name }}
                                        </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="closeModal">Cancel</Button>
                    <Button @click="saveRole">
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

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Roles', href: '/app/roles' },
];

const roles = ref({ data: [] });
const allPermissions = ref([]);
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const errors = ref({});

const form = ref({
    name: '',
    permissions: []
});

const fetchRoles = async () => {
    try {
        const res = await axios.get('/app/roles');
        roles.value = res.data;
    } catch (e) {
        console.error("Error fetching roles", e);
    }
};

const fetchPermissions = async () => {
    try {
        const res = await axios.get('/app/permissions'); // Defined in web routes as /app/permissions
        allPermissions.value = res.data;
    } catch (e) {
        console.warn("Error fetching permissions", e);
    }
};

onMounted(() => {
    fetchRoles();
    fetchPermissions();
});

const openModal = (role = null) => {
    errors.value = {};
    if (role) {
        isEditing.value = true;
        editingId.value = role.id;
        form.value.name = role.name;
        form.value.permissions = role.permissions ? role.permissions.map(p => p.id) : [];
    } else {
        isEditing.value = false;
        editingId.value = null;
        form.value = { name: '', permissions: [] };
    }
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const saveRole = async () => {
    try {
        if (isEditing.value) {
            await axios.put(`/app/roles/${editingId.value}`, form.value);
        } else {
            await axios.post('/app/roles', form.value);
        }
        closeModal();
        fetchRoles();
    } catch (e) {
        if (e.response && e.response.data.errors) {
            errors.value = e.response.data.errors;
        }
        console.error("Error saving role", e);
    }
};

const deleteRole = async (id) => {
    if (!confirm('Are you sure you want to delete this role?')) return;
    try {
        await axios.delete(`/app/roles/${id}`);
        fetchRoles();
    } catch (e) {
        console.error("Error deleting role", e);
    }
};
</script>
