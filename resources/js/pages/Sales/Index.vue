<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ref, watch } from 'vue';
import { debounce } from 'lodash';
import type { BreadcrumbItem } from '@/types';

const props = defineProps({
    sales: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales History', href: '/app/sales' },
];

watch(search, debounce((value) => {
    router.get('/app/sales', { search: value }, {
        preserveState: true,
        replace: true,
    });
}, 300));

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString() + ' ' + new Date(dateString).toLocaleTimeString();
};
</script>

<template>

    <Head title="Sales History" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight">Sales History</h2>
                    <p class="text-muted-foreground">View and manage past transactions.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Input v-model="search" placeholder="Search sales..." class="w-64" />
                </div>
            </div>

            <div class="rounded-xl border bg-card text-card-foreground shadow">
                <div class="p-0">
                    <div class="relative w-full overflow-auto">
                        <table class="w-full caption-bottom text-sm">
                            <thead class="[&_tr]:border-b">
                                <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Sale
                                        ID</th>
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Date
                                    </th>
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                                        Payment</th>
                                    <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">
                                        Total</th>
                                    <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="[&_tr:last-child]:border-0">
                                <tr v-for="sale in sales.data" :key="sale.sale_id"
                                    class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                    <td class="p-4 align-middle font-medium">{{ sale.sale_id.substring(0, 8) }}...</td>
                                    <td class="p-4 align-middle">{{ formatDate(sale.finalized_at) }}</td>
                                    <td class="p-4 align-middle capitalize">{{ sale.payment_type || 'N/A' }}</td>
                                    <td class="p-4 align-middle text-right">{{ formatCurrency(sale.total_amount) }}</td>
                                    <td class="p-4 align-middle text-right">
                                        <Button variant="outline" size="sm" as-child>
                                            <a :href="`/app/sales/${sale.sale_id}`">View</a>
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="sales.data.length === 0">
                                    <td colspan="5" class="p-4 text-center text-muted-foreground">
                                        No sales found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="flex items-center justify-end p-4 gap-2" v-if="sales.links.length > 3">
                    <template v-for="(link, key) in sales.links" :key="key">
                        <Button v-if="link.url" variant="outline" size="sm" :disabled="link.active" as-child>
                            <a :href="link.url" v-html="link.label"></a>
                        </Button>
                        <span v-else v-html="link.label" class="px-2 text-muted-foreground"></span>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
