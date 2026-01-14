<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { ref, watch } from 'vue';
import { debounce } from 'lodash';
import type { BreadcrumbItem } from '@/types';

const props = defineProps({
    inventory: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const adjustOpen = ref(false);
const selectedItem = ref<any>(null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventory', href: '/inventory' },
];

watch(search, debounce((value) => {
    router.get('/inventory', { search: value }, {
        preserveState: true,
        replace: true,
    });
}, 300));

const adjustForm = useForm({
    branch_id: '',
    batch_id: '',
    quantity_change: 0,
    reason: 'Manual Adjustment',
});

const openAdjustModal = (item) => {
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
        },
    });
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString();
};
</script>

<template>

    <Head title="Inventory" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight">Inventory Management</h2>
                    <p class="text-muted-foreground">Track stock levels and manage batches.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Input v-model="search" placeholder="Search drugs or batches..." class="w-64" />
                </div>
            </div>

            <div class="rounded-xl border bg-card text-card-foreground shadow">
                <div class="p-0">
                    <div class="relative w-full overflow-auto">
                        <table class="w-full caption-bottom text-sm">
                            <thead class="[&_tr]:border-b">
                                <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Drug
                                        Name</th>
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Batch
                                        ID</th>
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                                        Expiry</th>
                                    <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">
                                        Quantity</th>
                                    <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="[&_tr:last-child]:border-0">
                                <tr v-for="item in inventory.data" :key="item.inventory_id"
                                    class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                    <td class="p-4 align-middle font-medium">{{ item.drug_name }}</td>
                                    <td class="p-4 align-middle">{{ item.batch_id.substring(0, 8) }}...</td>
                                    <td class="p-4 align-middle">{{ formatDate(item.expiry_date) }}</td>
                                    <td class="p-4 align-middle text-right">
                                        <span :class="{ 'text-red-500 font-bold': item.quantity_on_hand < 10 }">
                                            {{ item.quantity_on_hand }}
                                        </span>
                                    </td>
                                    <td class="p-4 align-middle text-right">
                                        <Button variant="outline" size="sm" @click="openAdjustModal(item)">
                                            Adjust
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="inventory.data.length === 0">
                                    <td colspan="5" class="p-4 text-center text-muted-foreground">
                                        No inventory found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="flex items-center justify-end p-4 gap-2" v-if="inventory.links.length > 3">
                    <template v-for="(link, key) in inventory.links" :key="key">
                        <Button v-if="link.url" variant="outline" size="sm" :disabled="link.active" as-child>
                            <a :href="link.url" v-html="link.label"></a>
                        </Button>
                        <span v-else v-html="link.label" class="px-2 text-muted-foreground"></span>
                    </template>
                </div>
            </div>
        </div>

        <!-- Adjust Stock Modal -->
        <Dialog :open="adjustOpen" @update:open="adjustOpen = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Adjust Stock</DialogTitle>
                    <DialogDescription>
                        Update stock quantity for {{ selectedItem?.drug_name }}.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="grid grid-cols-4 items-center gap-4">
                        <Label class="text-right">Adjustment</Label>
                        <Input type="number" v-model="adjustForm.quantity_change" class="col-span-3"
                            placeholder="+10 or -5" />
                    </div>
                    <div class="grid grid-cols-4 items-center gap-4">
                        <Label class="text-right">Reason</Label>
                        <Input v-model="adjustForm.reason" class="col-span-3" placeholder="Restock / Damage" />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="submit" @click="submitAdjustment" :disabled="adjustForm.processing">Save
                        changes</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>
