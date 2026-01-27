<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InvoiceReceipt from '@/components/InvoiceReceipt.vue';
import { ref, watch, onMounted } from 'vue';
import { debounce } from 'lodash';
import { Calendar, ArrowUpDown, X } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

const props = defineProps({
    filters: Object,
});

const search = ref(props.filters?.search || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const sortBy = ref(props.filters?.sort_by || 'finalized_at');
const sortDirection = ref(props.filters?.sort_direction || 'desc');

const sales = ref({ data: [], links: [] });
const isLoading = ref(true);
const showReceipt = ref(false);
const selectedSale = ref<any>(null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales History', href: '/app/sales' },
];

const fetchSales = (url = '/app/sales') => {
    isLoading.value = true;
    import('axios').then(({ default: axios }) => {
        axios.get(url, {
            params: {
                search: search.value,
                start_date: startDate.value,
                end_date: endDate.value,
                sort_by: sortBy.value,
                sort_direction: sortDirection.value,
            },
            headers: { 'Accept': 'application/json' }
        })
            .then(response => {
                sales.value = response.data.data ? response.data.data : response.data;
            })
            .catch(error => {
                console.error("Failed to fetch sales", error);
            })
            .finally(() => {
                isLoading.value = false;
            });
    });
};

onMounted(() => {
    fetchSales();
});

watch(search, debounce((value) => {
    fetchSales();
}, 300));

watch([startDate, endDate, sortBy, sortDirection], debounce(() => {
    fetchSales();
}, 300));

const viewReceipt = (sale: any) => {
    import('axios').then(({ default: axios }) => {
        axios.get(`/app/sales/${sale.id}`, {
            headers: { 'Accept': 'application/json' }
        })
            .then(response => {
                const saleData = response.data.data || response.data;
                selectedSale.value = {
                    id: saleData.id,
                    items: saleData.items || [],
                    subtotal: saleData.subtotal_amount,
                    taxAmount: saleData.tax_amount,
                    totalAmount: saleData.total_amount,
                    paymentType: saleData.payment_type,
                    cashReceived: saleData.cash_received,
                    change: saleData.change_amount,
                    date: new Date(saleData.finalized_at || saleData.created_at).toLocaleString(),
                    user: saleData.user, // Include user data for "Served By"
                };
                showReceipt.value = true;
            })
            .catch(error => {
                console.error("Failed to fetch sale details", error);
            });
    });
};

const clearFilters = () => {
    search.value = '';
    startDate.value = '';
    endDate.value = '';
    sortBy.value = 'finalized_at';
    sortDirection.value = 'desc';
};

const toggleSort = (field: string) => {
    if (sortBy.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDirection.value = 'desc';
    }
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'GHS' }).format(value);
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString() + ' ' + new Date(dateString).toLocaleTimeString();
};

const hasActiveFilters = () => {
    return search.value || startDate.value || endDate.value || sortBy.value !== 'finalized_at' || sortDirection.value !== 'desc';
};
</script>

<template>

    <Head title="Sales History" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight">Sales History</h2>
                    <p class="text-muted-foreground">View and manage past transactions.</p>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="rounded-lg border bg-card p-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div class="md:col-span-1">
                        <label class="text-sm font-medium mb-2 block">Search</label>
                        <Input v-model="search" placeholder="Search sales..." />
                    </div>

                    <!-- Start Date -->
                    <div>
                        <label class="text-sm font-medium mb-2 block">Start Date</label>
                        <Input v-model="startDate" type="date" />
                    </div>

                    <!-- End Date -->
                    <div>
                        <label class="text-sm font-medium mb-2 block">End Date</label>
                        <Input v-model="endDate" type="date" />
                    </div>

                    <!-- Sort By -->
                    <div>
                        <label class="text-sm font-medium mb-2 block">Sort By</label>
                        <Select v-model="sortBy">
                            <SelectTrigger>
                                <SelectValue placeholder="Sort by..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="finalized_at">Date</SelectItem>
                                <SelectItem value="total_amount">Total Amount</SelectItem>
                                <SelectItem value="payment_type">Payment Type</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Active Filters & Actions -->
                <div class="flex items-center justify-between mt-4 pt-4 border-t" v-if="hasActiveFilters()">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm text-muted-foreground">Active filters:</span>
                        <span v-if="search" class="text-xs bg-primary/10 text-primary px-2 py-1 rounded">
                            Search: {{ search }}
                        </span>
                        <span v-if="startDate" class="text-xs bg-primary/10 text-primary px-2 py-1 rounded">
                            From: {{ startDate }}
                        </span>
                        <span v-if="endDate" class="text-xs bg-primary/10 text-primary px-2 py-1 rounded">
                            To: {{ endDate }}
                        </span>
                        <span v-if="sortBy !== 'finalized_at' || sortDirection !== 'desc'"
                            class="text-xs bg-primary/10 text-primary px-2 py-1 rounded">
                            Sort: {{ sortBy }} ({{ sortDirection }})
                        </span>
                    </div>
                    <Button variant="ghost" size="sm" @click="clearFilters" class="gap-2">
                        <X class="h-4 w-4" />
                        Clear Filters
                    </Button>
                </div>
            </div>

            <!-- Sales Table -->
            <div class="rounded-xl border bg-card text-card-foreground shadow">
                <div class="p-0">
                    <div class="relative w-full overflow-auto">
                        <div v-if="isLoading" class="p-8 text-center text-muted-foreground animate-pulse">
                            Loading sales history...
                        </div>
                        <table v-else class="w-full caption-bottom text-sm">
                            <thead class="[&_tr]:border-b">
                                <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Sale
                                        ID</th>
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground cursor-pointer hover:text-foreground"
                                        @click="toggleSort('finalized_at')">
                                        <div class="flex items-center gap-2">
                                            Date
                                            <ArrowUpDown class="h-4 w-4" v-if="sortBy === 'finalized_at'" />
                                        </div>
                                    </th>
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground cursor-pointer hover:text-foreground"
                                        @click="toggleSort('payment_type')">
                                        <div class="flex items-center gap-2">
                                            Payment
                                            <ArrowUpDown class="h-4 w-4" v-if="sortBy === 'payment_type'" />
                                        </div>
                                    </th>
                                    <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground cursor-pointer hover:text-foreground"
                                        @click="toggleSort('total_amount')">
                                        <div class="flex items-center justify-end gap-2">
                                            Total
                                            <ArrowUpDown class="h-4 w-4" v-if="sortBy === 'total_amount'" />
                                        </div>
                                    </th>
                                    <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="[&_tr:last-child]:border-0">
                                <tr v-for="sale in sales.data" :key="sale.id"
                                    class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                    <td class="p-4 align-middle font-mono text-xs">{{ sale.id.substring(0, 8) }}...</td>
                                    <td class="p-4 align-middle">{{ formatDate(sale.finalized_at) }}</td>
                                    <td class="p-4 align-middle capitalize">{{ sale.payment_type || 'N/A' }}</td>
                                    <td class="p-4 align-middle text-right font-semibold">{{
                                        formatCurrency(sale.total_amount) }}</td>
                                    <td class="p-4 align-middle text-right">
                                        <Button variant="outline" size="sm" @click="viewReceipt(sale)">
                                            View Receipt
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="sales.data.length === 0">
                                    <td colspan="5" class="p-8 text-center text-muted-foreground">
                                        No sales found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="flex items-center justify-end p-4 gap-2" v-if="sales.links && sales.links.length > 3">
                    <template v-for="(link, key) in sales.links" :key="key">
                        <Button v-if="link.url" variant="outline" size="sm" :disabled="link.active || isLoading"
                            @click.prevent="fetchSales(link.url)">
                            <span v-html="link.label"></span>
                        </Button>
                        <span v-else v-html="link.label" class="px-2 text-muted-foreground"></span>
                    </template>
                </div>
            </div>
        </div>

        <!-- Receipt Preview Modal -->
        <InvoiceReceipt :sale="selectedSale" :show="showReceipt" @update:show="showReceipt = $event" />
    </AppLayout>
</template>
