<template>
  <AppLayout :breadcrumbs="breadcrumbs">

    <Head title="Pharmacy Accounts" />

    <div class="py-12">
      <div class=" sm:px-6 lg:px-8">
        <Card>
          <CardHeader class="flex flex-row items-center justify-between">
            <div>
              <CardTitle>Pharmacy Accounts</CardTitle>
              <CardDescription>Manage your pharmacy branches and their settings.</CardDescription>
            </div>
            <Button @click="openModal()">
              <i class="fas fa-plus mr-2"></i> Create Pharmacy
            </Button>
          </CardHeader>
          <CardContent>
            <!-- Accounts List -->
            <div class="rounded-md border">
              <table class="w-full text-sm text-left">
                <thead class="bg-muted/50 font-medium">
                  <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Pharmacy Name</th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Plan</th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Phone</th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Location</th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Accounting</th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Users</th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground text-right">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="account in accounts.data" :key="account.id"
                    class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                    <td class="p-4 align-middle">
                      <div class="font-medium">{{ account.name }}</div>
                      <div v-if="account.metadata?.license" class="text-xs text-muted-foreground">License: {{
                        account.metadata.license }}</div>
                    </td>
                    <td class="p-4 align-middle">
                      <Badge :variant="getPlanBadgeVariant(account.plan)">
                        {{ account.plan || 'No Plan' }}
                      </Badge>
                    </td>
                    <td class="p-4 align-middle text-muted-foreground">
                      {{ account.metadata?.phone || '-' }}
                    </td>
                    <td class="p-4 align-middle text-muted-foreground">
                      <div class="max-w-xs truncate">{{ account.metadata?.address || '-' }}</div>
                    </td>
                    <td class="p-4 align-middle">
                      <Badge :variant="account.metadata?.accounting_enabled ? 'default' : 'secondary'">
                        {{ account.metadata?.accounting_enabled ? 'Enabled' : 'Disabled' }}
                      </Badge>
                    </td>
                    <td class="p-4 align-middle">
                      <Button variant="outline" size="sm" class="h-8 gap-1" @click="openUserModal(account)">
                        <i class="fas fa-users w-3.5 h-3.5"></i>
                        <span>{{ account.users_count || 0 }}</span>
                      </Button>
                    </td>
                    <td class="p-4 align-middle text-right">
                      <Button variant="ghost" size="sm" class="mr-2" @click="openModal(account)">Edit</Button>
                      <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive"
                        @click="deleteAccount(account.id)">Delete</Button>
                    </td>
                  </tr>
                  <tr v-if="!accounts.data || accounts.data.length === 0">
                    <td colspan="7" class="p-4 text-center text-muted-foreground">No pharmacy accounts found.</td>
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
      <DialogContent class="sm:max-w-3xl overflow-hidden flex flex-col max-h-[92vh]">
        <DialogHeader>
          <DialogTitle>{{ isEditing ? 'Edit Pharmacy' : 'Create New Pharmacy' }}</DialogTitle>
          <DialogDescription>
            {{ isEditing ? 'Update the pharmacy details below.' : 'Fill in the details to create a new pharmacy branch.'
            }}
          </DialogDescription>
        </DialogHeader>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 py-4 overflow-y-auto flex-1 pr-1">
          <!-- Pharmacy Name -->
          <div class="md:col-span-2 space-y-2">
            <Label>Pharmacy Name *</Label>
            <Input v-model="form.name" placeholder="e.g., Main Street Pharmacy" />
            <p v-if="errors.name" class="text-destructive text-xs">{{ errors.name }}</p>
          </div>

          <!-- Plan -->
          <div class="space-y-2">
            <Label>Plan</Label>
            <Select v-model="form.plan">
              <SelectTrigger>
                <SelectValue placeholder="Select Plan..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="basic">Basic</SelectItem>
                <SelectItem value="premium">Premium</SelectItem>
                <SelectItem value="enterprise">Enterprise</SelectItem>
              </SelectContent>
            </Select>
            <p v-if="errors.plan" class="text-destructive text-xs">{{ errors.plan }}</p>
          </div>

          <!-- License Number -->
          <div class="space-y-2">
            <Label>License Number</Label>
            <Input v-model="form.metadata.license" placeholder="e.g., PH-12345" />
            <p v-if="errors['metadata.license']" class="text-destructive text-xs">{{ errors['metadata.license'] }}</p>
          </div>

          <!-- Phone -->
          <div class="space-y-2">
            <Label>Phone Number</Label>
            <Input v-model="form.metadata.phone" type="tel" placeholder="e.g., (555) 123-4567" />
            <p v-if="errors['metadata.phone']" class="text-destructive text-xs">{{ errors['metadata.phone'] }}</p>
          </div>

          <!-- Business Email -->
          <div class="space-y-2">
            <Label>Business Email</Label>
            <Input v-model="form.metadata.email" type="email" placeholder="e.g., info@pharmacy.com" />
            <p v-if="errors['metadata.email']" class="text-destructive text-xs">{{ errors['metadata.email'] }}</p>
          </div>

          <!-- Contact Email -->
          <div class="space-y-2">
            <Label>Contact Person Email</Label>
            <Input v-model="form.metadata.contact_email" type="email" placeholder="e.g., manager@pharmacy.com" />
            <p v-if="errors['metadata.contact_email']" class="text-destructive text-xs">{{
              errors['metadata.contact_email'] }}</p>
          </div>

          <!-- Logo URL -->
          <div class="space-y-2">
            <Label>Logo URL</Label>
            <Input v-model="form.metadata.logo" placeholder="https://..." />
            <p v-if="errors['metadata.logo']" class="text-destructive text-xs">{{ errors['metadata.logo'] }}</p>
          </div>

          <!-- Business Hours -->
          <div class="md:col-span-2 space-y-2">
            <Label>Business Hours</Label>
            <Input v-model="form.metadata.business_hours" placeholder="e.g., Mon-Fri: 9AM-6PM, Sat: 9AM-2PM" />
            <p v-if="errors['metadata.business_hours']" class="text-destructive text-xs">{{
              errors['metadata.business_hours'] }}</p>
          </div>

          <!-- Address -->
          <div class="md:col-span-2 space-y-2">
            <Label>Physical Address</Label>
            <textarea v-model="form.metadata.address" rows="2"
              class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
              placeholder="Street address, City, State, ZIP"></textarea>
            <p v-if="errors['metadata.address']" class="text-destructive text-xs">{{ errors['metadata.address'] }}</p>
          </div>

          <div class="md:col-span-2 flex items-center justify-between rounded-md border p-3">
            <div>
              <Label class="text-sm font-medium">Enable Accounting Module</Label>
              <p class="text-xs text-muted-foreground mt-1">
                Enable before accounting pages become available for this account.
              </p>
            </div>
            <input v-model="form.metadata.accounting_enabled" type="checkbox" class="h-4 w-4" />
          </div>

          <!-- Permissions -->
          <div class="md:col-span-2 space-y-2 pt-2 border-t">
            <Label>Default Permissions
              <span class="text-xs font-normal text-muted-foreground ml-1">(assigned to this pharmacy account)</span>
            </Label>
            <PermissionSelector v-model="form.permissions" :allPermissions="allPermissions" />
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" @click="closeModal">Cancel</Button>
          <Button @click="saveAccount">
            {{ isEditing ? 'Update' : 'Create' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- User Management Modal -->
    <Dialog :open="showUserModal" @update:open="val => !val && closeUserModal()">
      <DialogContent class="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>Manage Users</DialogTitle>
          <DialogDescription>
            Users attached to <strong>{{ selectedAccount?.name }}</strong>
          </DialogDescription>
        </DialogHeader>

        <div class="py-4 space-y-6">
          <!-- Attach User Section -->
          <div class="space-y-3">
            <Label>Attach User to Pharmacy</Label>
            <div class="flex gap-2">
              <Select v-model="selectedUserId">
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="Select a user..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="user in availableUsers" :key="user.id" :value="user.id">
                    {{ user.name }} ({{ user.email }})
                  </SelectItem>
                </SelectContent>
              </Select>
              <Button @click="attachUser" :disabled="!selectedUserId">
                <i class="fas fa-plus mr-1"></i> Attach
              </Button>
            </div>
          </div>

          <!-- Attached Users List -->
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <Label>Attached Users ({{ attachedUsers.length }})</Label>
            </div>

            <div v-if="attachedUsers.length > 0" class="max-h-[300px] overflow-y-auto border rounded-md divide-y">
              <div v-for="user in attachedUsers" :key="user.id" class="flex items-center justify-between p-3">
                <div>
                  <div class="text-sm font-medium">{{ user.name }}</div>
                  <div class="text-xs text-muted-foreground">{{ user.email }}</div>
                </div>
                <Button variant="ghost" size="sm"
                  class="text-destructive hover:text-destructive hover:bg-destructive/10" @click="detachUser(user.id)">
                  <i class="fas fa-times mr-1"></i> Remove
                </Button>
              </div>
            </div>
            <div v-else class="text-center py-8 text-muted-foreground border rounded-md border-dashed">
              No users attached to this pharmacy yet.
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" @click="closeUserModal">Close</Button>
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
  CardFooter,
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
  DialogTrigger,
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
  { title: 'Accounts', href: '/app/accounts' },
];

const accounts = ref({ data: [] });
const allPermissions = ref([]);
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const errors = ref({});

const showUserModal = ref(false);
const selectedAccount = ref(null);
const attachedUsers = ref([]);
const availableUsers = ref([]);
const selectedUserId = ref('');

const form = ref({
  name: '',
  plan: '',
  permissions: [],
  metadata: {
    phone: '',
    email: '',
    logo: '',
    business_hours: '',
    contact_email: '',
    address: '',
    license: '',
    accounting_enabled: false,
  }
});

const fetchAccounts = async () => {
  try {
    const res = await axios.get('/app/accounts');
    accounts.value = res.data;
  } catch (e) {
    console.error("Error fetching accounts", e);
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

const fetchAllUsers = async () => {
  try {
    const res = await axios.get('/app/users');
    availableUsers.value = res.data.data || res.data;
  } catch (e) {
    console.error("Error fetching users", e);
  }
};

onMounted(() => {
  fetchAccounts();
  fetchAllUsers();
  fetchPermissions();
});

const openModal = (account = null) => {
  errors.value = {};

  if (account) {
    isEditing.value = true;
    editingId.value = account.id;
    form.value.name = account.name;
    form.value.plan = account.plan || '';
    form.value.metadata = {
      phone: account.metadata?.phone || '',
      email: account.metadata?.email || '',
      logo: account.metadata?.logo || '',
      business_hours: account.metadata?.business_hours || '',
      contact_email: account.metadata?.contact_email || '',
      address: account.metadata?.address || '',
      license: account.metadata?.license || '',
      accounting_enabled: Boolean(account.metadata?.accounting_enabled),
    };
    // Pre-populate permissions from eager-loaded assignedPermissions relation.
    // Shape from API: [{ permission_id, access, permission: { id, name, type } }]
    const rawPerms = account.assigned_permissions ?? [];
    form.value.permissions = rawPerms.map(p => {
      const permId = p.permission?.id ?? p.permission_id ?? p.id;
      const rawAccess = p.access;
      let access = [];
      if (rawAccess) {
        if (Array.isArray(rawAccess)) {
          access = rawAccess;
        } else {
          try { access = JSON.parse(rawAccess) ?? []; } catch { access = []; }
        }
      }
      return { id: permId, access };
    });
  } else {
    isEditing.value = false;
    editingId.value = null;
    form.value = {
      name: '',
      plan: '',
      permissions: [],
      metadata: {
        phone: '',
        email: '',
        logo: '',
        business_hours: '',
        contact_email: '',
        address: '',
        license: '',
        accounting_enabled: false,
      }
    };
  }
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

const saveAccount = async () => {
  try {
    const payload = {
      name: form.value.name,
      plan: form.value.plan || null,
      metadata: form.value.metadata,
      permissions: form.value.permissions,
    };

    if (isEditing.value) {
      await axios.put(`/app/accounts/${editingId.value}`, payload);
    } else {
      await axios.post('/app/accounts', payload);
    }

    closeModal();
    fetchAccounts();
  } catch (e) {
    if (e.response && e.response.data.errors) {
      errors.value = e.response.data.errors;
    }
    console.error("Error saving account", e);
  }
};

const deleteAccount = async (id) => {
  if (!confirm('Are you sure you want to delete this pharmacy? This action cannot be undone.')) return;

  try {
    await axios.delete(`/app/accounts/${id}`);
    fetchAccounts();
  } catch (e) {
    if (e.response && e.response.data.message) {
      alert(e.response.data.message);
    } else {
      console.error("Error deleting account", e);
    }
  }
};

const openUserModal = async (account) => {
  selectedAccount.value = account;
  selectedUserId.value = '';

  try {
    const res = await axios.get(`/app/accounts/${account.id}/users`);
    attachedUsers.value = res.data;
  } catch (e) {
    console.error("Error fetching attached users", e);
  }

  showUserModal.value = true;
};

const closeUserModal = () => {
  showUserModal.value = false;
  selectedAccount.value = null;
  attachedUsers.value = [];
};

const attachUser = async () => {
  if (!selectedUserId.value) return;

  try {
    await axios.post(`/app/accounts/${selectedAccount.value.id}/users`, {
      user_id: selectedUserId.value
    });

    // Refresh attached users
    const res = await axios.get(`/app/accounts/${selectedAccount.value.id}/users`);
    attachedUsers.value = res.data;
    selectedUserId.value = '';

    // Refresh accounts to update count
    fetchAccounts();
  } catch (e) {
    if (e.response && e.response.data.message) {
      alert(e.response.data.message);
    }
    console.error("Error attaching user", e);
  }
};

const detachUser = async (userId) => {
  if (!confirm('Are you sure you want to remove this user from the pharmacy?')) return;

  try {
    await axios.delete(`/app/accounts/${selectedAccount.value.id}/users/${userId}`);

    // Refresh attached users
    const res = await axios.get(`/app/accounts/${selectedAccount.value.id}/users`);
    attachedUsers.value = res.data;

    // Refresh accounts to update count
    fetchAccounts();
  } catch (e) {
    console.error("Error detaching user", e);
  }
};

const getPlanBadgeVariant = (plan) => {
  const variants = {
    'basic': 'secondary',
    'premium': 'default', // blue-ish usually
    'enterprise': 'destructive' // purple not available, using distinctive variant or could use outline
  };
  // Adjusting mapping based on badge variants: default, secondary, destructive, outline
  if (plan === 'enterprise') return 'secondary'; // Using secondary for enterprise too or we can just use default
  return variants[plan] || 'outline';
};
</script>
