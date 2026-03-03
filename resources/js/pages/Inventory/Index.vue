<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { ref, watch, computed, onMounted } from 'vue';
import { debounce } from 'lodash';
import type { BreadcrumbItem } from '@/types';
import AddInventoryDialog from './AddInventoryDialog.vue';
import CreateBatchDialog from './CreateBatchDialog.vue';
import { Search, ChevronLeft, ChevronRight, Plus, AlertCircle, Settings2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';

const props = defineProps({
    filters: Object,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const batchMode = computed(() => page.props.auth.settings?.settings?.inventory_batch_mode || false);

const search = ref(props.filters?.search || '');
const adjustOpen = ref(false);
const selectedItem = ref<any>(null);
const addStockDialog = ref();
const createBatchDialog = ref();

const inventory = ref({ data: [], links: [], meta: {}, current_page: 1, last_page: 1, total: 0 });
const isLoading = ref(true);
const currentPage = ref(1);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventory', href: '/inventory' },
];

const fetchInventory = (page = 1) => {
    isLoading.value = true;
    currentPage.value = page;

    import('axios').then(({ default: axios }) => {
        axios.get('/app/inventory', {
            params: {
                page: page,
                search: search.value,
                per_page: 15
            },
            headers: { 'Accept': 'application/json' }
        })
            .then(response => {
                inventory.value = response.data.data ? response.data.data : response.data;
            })
            .catch(error => {
                console.error("Failed to fetch inventory", error);
            })
            .finally(() => {
                isLoading.value = false;
            });
    });
};

onMounted(() => {
    fetchInventory();
});

watch(search, debounce((value) => {
    fetchInventory(1);
}, 300));

const adjustForm = useForm({
    branch_id: '',
    batch_id: '',
    quantity_change: 0,
    reason: 'Manual Adjustment',
});

const openAdjustModal = (item: { branch_id: string; batch_id: string; }) => {
    selectedItem.value = item;
    adjustForm.branch_id = item.branch_id;
    adjustForm.batch_id = item.batch_id;
    adjustForm.quantity_change = 0;
    adjustOpen.value = true;
};

const submitAdjustment = () => {
    adjustForm.post('/app/inventory/adjust', {
        onSuccess: () => {
            adjustOpen.value = false;
            adjustForm.reset();
            // Re-fetch inventory to show updated stock
            fetchInventory(currentPage.value);
        },
    });
};

const formatDate = (dateString: string | number | Date) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const formatCurrency = (val: string | number) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'GHS' }).format(Number(val));
};

const openAddStock = () => {
    addStockDialog.value.openDialog();
};

const openCreateBatch = () => {
    createBatchDialog.value.openDialog();
};
</script>

<template>

    <Head title="Inventory" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">Inventory Management</h2>
                    <p class="text-muted-foreground mt-1">Track stock levels, monitor expiry, and manage batches.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button v-if="$can('batches.manage.create')" variant="outline" @click="openCreateBatch">
                        <Plus class="h-4 w-4 mr-2" />
                        New Batch
                    </Button>
                    <Button v-if="$can('inventory.expired')" variant="destructive" @click="router.visit('/app/inventory/expired')">
                        <AlertCircle class="h-4 w-4 mr-2" />
                        Expired Stock
                    </Button>
                    <Button v-if="batchMode && $can('inventory.manage.create')" @click="openAddStock">
                        <Plus class="h-4 w-4 mr-2" />
                        Add Stock
                    </Button>
                    <p v-else class="text-xs text-muted-foreground italic px-2 border-l">
                        Batch mode disabled
                    </p>
                </div>
            </div>

            <div class="rounded-xl border bg-card text-card-foreground shadow">
                <!-- Toolbar -->
                <div class="p-4 border-b flex justify-between items-center">
                    <div class="bg-muted/50 rounded-lg p-1 flex items-center">
                        <div class="relative w-64">
                            <Search class="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input v-model="search" placeholder="Search drugs, batches, etc."
                                class="pl-8 border-none shadow-none focus-visible:ring-0 bg-transparent" />
                        </div>
                    </div>
                </div>

                <div class="p-0">
                    <div class="relative w-full overflow-auto">
                        <table class="w-full caption-bottom text-sm">
                            <thead class="[&_tr]:border-b">
                                <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Drug
                                        Details</th>
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Batch
                                        Info</th>
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                                        Expiry</th>
                                    <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">
                                        Price</th>
                                    <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">
                                        Stock Level</th>
                                    <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="[&_tr:last-child]:border-0">
                                <tr v-if="isLoading">
                                    <td colspan="6" class="p-8 text-center text-muted-foreground">
                                        Loading inventory...
                                    </td>
                                </tr>
                                <tr v-else-if="!inventory.data || inventory.data.length === 0">
                                    <td colspan="6" class="p-8 text-center text-muted-foreground">
                                        No inventory found.
                                    </td>
                                </tr>
                                <tr v-for="item in inventory.data" :key="item.inventory_id"
                                    class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                    <td class="p-4 align-middle">
                                        <div class="font-bold">{{ item.drug_name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ item.strength }}</div>
                                    </td>
                                    <td class="p-4 align-middle">
                                        <div class="font-mono text-xs bg-muted px-2 py-1 rounded inline-block">
                                            {{ item.lot_number || item.batch_id.substring(0, 8) }}
                                        </div>
                                    </td>
                                    <td class="p-4 align-middle">
                                        {{ formatDate(item.expiry_date) }}
                                        <Badge v-if="new Date(item.expiry_date) < new Date()" variant="destructive"
                                            class="ml-2 py-0 h-5 text-[10px]">
                                            Exp
                                        </Badge>
                                    </td>
                                    <td class="p-4 align-middle text-right font-medium">
                                        {{ formatCurrency(item.selling_price) }}
                                    </td>
                                    <td class="p-4 align-middle text-right">
                                        <div class="flex flex-col items-end">
                                            <span
                                                :class="{ 'text-red-500 font-bold': item.quantity_on_hand < 10, 'text-green-600 font-bold': item.quantity_on_hand >= 10 }">
                                                {{ item.quantity_on_hand }}
                                            </span>
                                            <span v-if="item.quantity_on_hand < 10" class="text-[10px] text-red-500">Low
                                                Stock</span>
                                        </div>
                                    </td>
                                    <td class="p-4 align-middle text-right">
                                        <Button v-if="$can('inventory.adjust')" variant="outline" size="sm" @click="openAdjustModal(item)">
                                            <Settings2 class="h-3 w-3 mr-1" />
                                            Adjust
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="flex items-center justify-end p-4 gap-2 border-t" v-if="inventory.last_page > 1">
                    <Button variant="outline" size="sm" :disabled="inventory.current_page === 1"
                        @click="fetchInventory(inventory.current_page - 1)">
                        <ChevronLeft class="h-4 w-4 mr-2" />
                        Previous
                    </Button>

                    <div class="text-sm text-muted-foreground w-24 text-center">
                        Page {{ inventory.current_page }} of {{ inventory.last_page }}
                    </div>

                    <Button variant="outline" size="sm" :disabled="inventory.current_page === inventory.last_page"
                        @click="fetchInventory(inventory.current_page + 1)">
                        Next
                        <ChevronRight class="h-4 w-4 ml-2" />
                    </Button>
                </div>
            </div>
        </div>

        <AddInventoryDialog ref="addStockDialog" />
        <CreateBatchDialog ref="createBatchDialog" />

        <!-- Adjust Stock Modal -->
        <Dialog :open="adjustOpen" @update:open="adjustOpen = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Adjust Stock</DialogTitle>
                    <DialogDescription>
                        Update stock quantity for <strong>{{ selectedItem?.drug_name }}</strong>.
                        This action will be logged in the audit trail.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="grid grid-cols-4 items-center gap-4">
                        <Label class="text-right">Adjustment</Label>
                        <Input type="number" v-model="adjustForm.quantity_change" class="col-span-3 font-mono text-lg"
                            placeholder="+10 or -5" autofocus />
                    </div>
                    <div class="grid grid-cols-4 items-center gap-4">
                        <Label class="text-right">Reason</Label>
                        <Input v-model="adjustForm.reason" class="col-span-3"
                            placeholder="e.g. Broken bottle, Audit correction" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="ghost" @click="adjustOpen = false">Cancel</Button>
                    <Button type="submit" @click="submitAdjustment"
                        :disabled="adjustForm.processing || adjustForm.quantity_change === 0">
                        Confirm Adjustment
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>
