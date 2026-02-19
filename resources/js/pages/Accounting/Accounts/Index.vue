<template>
  <AppLayout :breadcrumbs="breadcrumbs">

    <Head title="Chart of Accounts" />

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <!-- Stats / Header Area -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 mb-6">
          <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Total Assets</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">{{ formatCurrency(totalAssets) }}</div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Total Liabilities</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">{{ formatCurrency(totalLiabilities) }}</div>
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader class="flex flex-row items-center justify-between">
            <div class="space-y-1">
              <CardTitle>Chart of Accounts</CardTitle>
              <CardDescription>Manage your financial accounts hierarchy.</CardDescription>
            </div>
            <div class="flex items-center gap-2">
              <div class="relative w-64">
                <i class="fas fa-search absolute left-2 top-2.5 h-4 w-4 text-muted-foreground"></i>
                <Input placeholder="Search accounts..." v-model="searchQuery" class="pl-8" />
              </div>
              <Button @click="openCreateModal()">
                <i class="fas fa-plus mr-2"></i> Add Account
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <div class="rounded-md border">
              <table class="w-full text-sm text-left">
                <thead class="bg-muted/50 font-medium">
                  <tr class="border-b">
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground w-[100px]">Code</th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Account Name</th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground w-[100px]">Type</th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground w-[100px]">Status</th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground text-right w-[150px]">Balance
                    </th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground text-right w-[100px]">Actions
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="account in filteredAccounts" :key="account.id">
                    <tr class="border-b transition-colors hover:bg-muted/50 group">
                      <td class="p-4 align-middle font-mono text-muted-foreground">{{ account.code }}</td>
                      <td class="p-4 align-middle">
                        <div class="flex items-center">
                          <span v-if="account.level > 0" class="text-muted-foreground mr-2"
                            :style="{ marginLeft: (account.level * 16) + 'px' }">
                            ∟
                          </span>
                          <span :class="{ 'font-semibold': account.is_group }">{{ account.name }}</span>
                        </div>
                      </td>
                      <td class="p-4 align-middle">
                        <Badge variant="outline" class="capitalize">{{ account.type }}</Badge>
                      </td>
                      <td class="p-4 align-middle">
                        <Badge :variant="account.is_active ? 'default' : 'secondary'" class="text-xs">
                          {{ account.is_active ? 'Active' : 'Inactive' }}
                        </Badge>
                      </td>
                      <td class="p-4 align-middle font-mono text-right">
                        <span :class="{ 'text-muted-foreground': account.current_balance == 0 }">
                          {{ formatCurrency(account.current_balance) }}
                        </span>
                      </td>
                      <td class="p-4 align-middle text-right">
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                          <Button variant="ghost" size="icon" class="h-8 w-8" @click="openEditModal(account)">
                            <i class="fas fa-pencil-alt h-4 w-4"></i>
                          </Button>
                        </div>
                      </td>
                    </tr>
                    <!-- Render children recursively? For flat list with indent, we need to flatten the tree in computed -->
                  </template>
                  <tr v-if="filteredAccounts.length === 0">
                    <td colspan="6" class="p-8 text-center text-muted-foreground">
                      No accounts found matching your search.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>

    <AccountFormModal v-if="showModal" :open="showModal" :account="selectedAccount" :accountsList="flatAccounts"
      @close="closeModal" @success="refresh" />
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import AccountFormModal from './Components/AccountFormModal.vue';

const props = defineProps({
  accounts: Array,
});

const breadcrumbs = [
  { title: 'Dashboard', href: '/accounting' },
  { title: 'Chart of Accounts', href: '/accounting/accounts' },
];

const searchQuery = ref('');
const showModal = ref(false);
const selectedAccount = ref(null);

// Flatten the tree for easier display/filtering if the backend returns a tree
// But the controller returns `ChartOfAccount::with('children')->whereNull('parent_id')->get()` which IS a tree.
// We need to flatten it for the table but keep hierarchy info.

const flattenAccounts = (accounts, level = 0) => {
  let result = [];
  accounts.forEach(acc => {
    result.push({ ...acc, level });
    if (acc.children && acc.children.length > 0) {
      result = result.concat(flattenAccounts(acc.children, level + 1));
    }
  });
  return result;
};

const flatAccounts = computed(() => {
  return flattenAccounts(props.accounts);
});

const filteredAccounts = computed(() => {
  if (!searchQuery.value) return flatAccounts.value;
  const query = searchQuery.value.toLowerCase();
  return flatAccounts.value.filter(acc =>
    acc.name.toLowerCase().includes(query) ||
    acc.code.includes(query) ||
    acc.type.toLowerCase().includes(query)
  );
});

const totalAssets = computed(() => {
  return flatAccounts.value
    .filter(a => a.type === 'Asset' && !a.parent_id) // Only top level to avoid double counting
    .reduce((sum, a) => sum + Number(a.current_balance), 0);
});

const totalLiabilities = computed(() => {
  return flatAccounts.value
    .filter(a => a.type === 'Liability' && !a.parent_id)
    .reduce((sum, a) => sum + Number(a.current_balance), 0);
});

const openCreateModal = () => {
  selectedAccount.value = null;
  showModal.value = true;
};

const openEditModal = (account) => {
  selectedAccount.value = account;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  selectedAccount.value = null;
};

const refresh = () => {
  router.reload();
};

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
};
</script>
