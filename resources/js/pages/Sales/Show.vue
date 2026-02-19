<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { ref, onMounted } from 'vue';
import type { BreadcrumbItem } from '@/types';
import ReturnModal from './ReturnModal.vue';

// No props needed for data, strictly fetching
const props = defineProps({});

const sale = ref<any>(null);
const isLoading = ref(true);
const returnModalOpen = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales History', href: '/app/sales' },
    { title: 'Sale Details', href: '#' },
];

const fetchSale = () => {
    isLoading.value = true;
    import('axios').then(({ default: axios }) => {
        // Request the current URL but as JSON
        axios.get(window.location.href, {
            headers: { 'Accept': 'application/json' }
        })
            .then(response => {
                // Controller returns { data: sale }
                sale.value = response.data.data ? response.data.data : response.data;
            })
            .catch(error => {
                console.error("Failed to fetch sale details", error);
            })
            .finally(() => {
                isLoading.value = false;
            });
    });
};

onMounted(() => {
    fetchSale();
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString() + ' ' + new Date(dateString).toLocaleTimeString();
};
</script>

<template>

    <Head title="Sale Details" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 items-center justify-center bg-muted/20">
            <div class="w-full max-w-md rounded-xl border bg-card text-card-foreground shadow-lg overflow-hidden">

                <div v-if="isLoading" class="p-10 text-center text-muted-foreground animate-pulse">
                    Loading receipt...
                </div>

                <div v-else-if="sale">
                    <div class="p-6 pb-2 text-center">
                        <h2 class="text-2xl font-bold tracking-tight">Receipt</h2>
                        <div class="text-sm text-muted-foreground font-mono mt-1">#{{ sale.id ?? sale.sale_id }}</div>
                        <!-- Fallback ID check -->
                        <p class="text-sm text-muted-foreground">{{ formatDate(sale.finalized_at) }}</p>
                    </div>

                    <div class="px-6 py-4">
                        <div v-for="item in sale.items" :key="item.id"
                            class="flex justify-between py-2 border-b border-dashed last:border-0">
                            <div>
                                <div class="font-medium">{{ item.drug?.name || 'Item' }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ item.quantity }} x {{ formatCurrency(item.price) }}
                                </div>
                                <div v-if="item.is_returned"
                                    class="text-xs text-red-600 bg-red-50 inline-block px-1 rounded mt-0.5">
                                    Returned: {{ item.return_quantity }} / {{ item.quantity }}
                                    <span v-if="item.return_date">on {{ new Date(item.return_date).toLocaleDateString()
                                        }}</span>
                                </div>
                            </div>
                            <div class="font-mono text-right">
                                <div>{{ formatCurrency(item.quantity * item.price) }}</div>
                                <div v-if="item.is_returned" class="text-xs text-red-600">
                                    -{{ formatCurrency(item.return_quantity * item.price) }}
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-between py-2 border-t mt-4">
                            <span class="font-medium">Payment Type</span>
                            <span class="capitalize">{{ sale.payment_type }}</span>
                        </div>
                    </div>

                    <div class="bg-muted/50 px-6 py-4 space-y-2">
                        <!-- Original Total (Net + Returned) because we updated total_amount to be net -->
                        <div class="flex justify-between text-sm text-muted-foreground"
                            v-if="sale.total_returned_amount > 0">
                            <span>Original Subtotal</span>
                            <span>{{ formatCurrency(Number(sale.subtotal_amount) + Number(sale.total_returned_amount))
                                }}</span>
                        </div>

                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Tax</span>
                            <span>{{ formatCurrency(sale.tax_amount) }}</span>
                        </div>

                        <div class="flex justify-between text-sm text-destructive font-medium"
                            v-if="sale.total_returned_amount > 0">
                            <span>Returned Amount</span>
                            <span>-{{ formatCurrency(sale.total_returned_amount) }}</span>
                        </div>

                        <div class="flex justify-between text-lg font-bold border-t pt-2">
                            <span>Net Total</span>
                            <span>{{ formatCurrency(sale.total_amount) }}</span>
                        </div>
                    </div>

                    <div class="p-6 pt-2 flex flex-col gap-2">
                        <Button variant="outline" class="w-full" @click="returnModalOpen = true">
                            Process Return
                        </Button>
                        <!-- Use Link for navigation -->
                        <Button variant="secondary" class="w-full" as-child>
                            <Link href="/app/sales">Back to Sales</Link>
                        </Button>
                    </div>
                </div>

                <div v-else class="p-10 text-center text-destructive">
                    Failed to load sale.
                </div>

            </div>
        </div>

        <ReturnModal :sale="sale" :open="returnModalOpen" @update:open="returnModalOpen = $event"
            @success="fetchSale" />
    </AppLayout>
</template>
