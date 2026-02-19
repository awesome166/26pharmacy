<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Roles & Permissions" />

        <div class="py-12">
            <div class=" sm:px-6 lg:px-8">
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
                                        <!-- <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Permissions
                                        </th>-->
                                        <th class="h-12 px-4 align-middle font-medium text-muted-foreground text-right">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="role in roles.data" :key="role.id"
                                        class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                        <td class="p-4 align-middle font-medium">{{ role.name }} <br> <span
                                                class="text-xs text-muted-foreground">{{ role.description }}</span></td>
                                        <td class="p-4 align-middle text-muted-foreground">{{ role.zeus_level }}</td>
                                        <!-- <td class="p-4 align-middle">
                                            <div class="flex flex-wrap gap-1">
                                                <Badge v-for="perm in role.assigned_permissions.slice(0, 5)"
                                                    :key="perm.id" variant="secondary" class="text-xs">
                                                    {{ perm.name }}
                                                </Badge>
                                                <Badge v-if="role.assigned_permissions.length > 5" variant="outline"
                                                    class="text-xs">
                                                    +{{ role.permission.length - 5 }} more
                                                </Badge>
                                            </div>
                                        </td> -->
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
            <DialogContent class="sm:max-w-2xl overflow-hidden flex flex-col max-h-[92vh]">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Edit Role' : 'Create New Role' }}</DialogTitle>
                    <DialogDescription>
                        Configure the role details and assign permissions.
                    </DialogDescription>
                </DialogHeader>

                <div class="py-4 space-y-4 overflow-y-auto flex-1 pr-1">
                    <div class="space-y-2">
                        <Label>Role Name</Label>
                        <Input v-model="form.name" placeholder="e.g. Manager" />
                        <p v-if="errors.name" class="text-destructive text-xs">{{ errors.name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Description</Label>
                        <Input v-model="form.description" placeholder="e.g. Manager" />
                        <p v-if="errors.description" class="text-destructive text-xs">{{ errors.description }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Permissions</Label>
                        <PermissionSelector v-model="form.permissions" :allPermissions="allPermissions" />
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
import PermissionSelector from '@/components/Permissions/PermissionSelector.vue';

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
    description: '',
    permissions: [], // Array of { id, access: [] }
    assignee_type: 'role'
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
        const res = await axios.get('/app/permissions');
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
        form.value.description = role.description || '';
        form.value.assignee_type = 'role';

        // Populate form.permissions for the component
        const perms = [];
        const sourcePermissions = role.assigned_permissions || role.permissions;

        console.log('Index.vue: Role data received:', role);
        console.log('Index.vue: Source permissions:', sourcePermissions);

        if (sourcePermissions && sourcePermissions.length > 0) {
            sourcePermissions.forEach(p => {
                // Determine ID: permission_id if coming from assigned_permissions, or id if from permissions
                const permId = p.permission_id || (p.permission && p.permission.id) || p.id;

                let access = [];
                // Check direct access field (assigned_permissions) or pivot access (old way)
                let rawAccess = p.access || (p.pivot ? p.pivot.access : null);

                if (rawAccess) {
                    if (Array.isArray(rawAccess)) {
                        access = rawAccess;
                    } else if (typeof rawAccess === 'string') {
                        try {
                            // Handle potential double encoding or simple string
                            const parsed = JSON.parse(rawAccess);
                            access = Array.isArray(parsed) ? parsed : [];
                        } catch (e) {
                            console.warn('Failed to parse access for permission', p.id, rawAccess);
                            access = [];
                        }
                    }
                }

                // Only add if we have a valid permission ID
                if (permId) {
                    perms.push({ id: permId, access: access });
                }
            });
        }
        console.log('Index.vue: Setting form.permissions to', JSON.parse(JSON.stringify(perms)));
        form.value.permissions = perms;
    } else {
        isEditing.value = false;
        editingId.value = null;
        form.value = { name: '', description: '', assignee_type: 'role', permissions: [] };
    }
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const saveRole = async () => {
    try {
        console.log('saveRole: form.value =', JSON.stringify(form.value, null, 2));
        // Form permissions are already in correct format thanks to PermissionSelector
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
