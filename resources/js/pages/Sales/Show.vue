<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import type { BreadcrumbItem } from '@/types';

const props = defineProps({
    sale: Object,
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales History', href: '/app/sales' },
    { title: 'Sale Details', href: '#' },
];

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
                <div class="p-6 pb-2 text-center">
                    <h2 class="text-2xl font-bold tracking-tight">Receipt</h2>
                    <p class="text-sm text-muted-foreground font-mono mt-1">#{{ sale.sale_id }}</p>
                    <p class="text-sm text-muted-foreground">{{ formatDate(sale.finalized_at) }}</p>
                </div>

                <div class="px-6 py-4">
                    <!-- Items would go here if we were syncing line items to the read model.
                         Assuming line items are part of the sale object or loaded.
                         For now displaying totals as per current Read Model schema which focuses on totals. -->

                    <div class="flex justify-between py-2 border-b border-dashed">
                        <span class="font-medium">Payment Type</span>
                        <span class="capitalize">{{ sale.payment_type }}</span>
                    </div>
                </div>

                <div class="bg-muted/50 px-6 py-4 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">Tax</span>
                        <span>{{ formatCurrency(sale.tax_amount) }}</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold">
                        <span>Total</span>
                        <span>{{ formatCurrency(sale.total_amount) }}</span>
                    </div>
                </div>

                <div class="p-6 pt-2 flex flex-col gap-2">
                    <Button class="w-full" as-child>
                         <Link href="/app/sales">Back to Sales</Link>
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
