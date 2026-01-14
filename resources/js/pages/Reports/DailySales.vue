<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';

const props = defineProps({
    report: Object,
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Daily Sales', href: '#' },
];

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);
};
</script>

<template>
    <Head title="Daily Sales Report" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Daily Sales Overview</h2>
                <p class="text-muted-foreground">Financial summary for today.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Gross Sales</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatCurrency(report?.gross_sales) }}</div>
                        <p class="text-xs text-muted-foreground">For current business day</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Tax Collected</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatCurrency(report?.tax_collected) }}</div>
                        <p class="text-xs text-muted-foreground">Sales tax total</p>
                    </CardContent>
                </Card>
            </div>

             <div class="rounded-xl border bg-card text-card-foreground shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Transactions</h3>
                <p class="text-muted-foreground" v-if="!report?.transactions || report.transactions.length === 0">
                    No transactions recorded for today yet.
                </p>
                 <!-- We could list transactions here if the report object included them -->
            </div>
        </div>
    </AppLayout>
</template>
