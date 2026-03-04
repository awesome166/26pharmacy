<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';
import axios from 'axios';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Daily Sales', href: '#' },
];

const printReport = () => {
    window.print();
};

const summary = ref<any>(null);
const loading = ref(false);
const selectedDate = ref(new Date().toISOString().split('T')[0]);

const fetchDailySales = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/app/reports/daily-sales', {
            params: { date: selectedDate.value },
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        summary.value = response.data;
    } catch (error) {
        console.error("Failed to fetch daily sales:", error);
    } finally {
        loading.value = false;
    }
};

const formatCurrency = (value: any) => {
    const amount = Number(value) || 0;
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'GHS' }).format(amount);
};

onMounted(() => {
    fetchDailySales();
});

watch(selectedDate, () => {
    fetchDailySales();
});
</script>

<template>

    <Head title="Daily Sales Report" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight">Daily Sales Overview</h2>
                    <p class="text-muted-foreground">Financial summary for {{ selectedDate }}.</p>
                </div>
                <div class="flex items-center gap-2 no-print">
                    <Button variant="outline" @click="printReport">
                        Download / Print
                    </Button>
                    <div class="flex items-center gap-2">
                        <Label for="report-date">Date</Label>
                        <Input id="report-date" type="date" v-model="selectedDate" class="w-40" />
                    </div>
                </div>
            </div>

            <div v-if="loading" class="text-center py-10 text-muted-foreground">
                Loading data...
            </div>

            <div v-else class="space-y-6">
                <!-- Financial Cards -->
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Gross Sales</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ formatCurrency(summary?.gross_sales) }}</div>
                            <p class="text-xs text-muted-foreground">For selected day</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Gross Transactions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ summary?.total_transactions || 0 }}</div>
                            <p class="text-xs text-muted-foreground">Total sales count</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Net Sales</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ formatCurrency(summary?.net_sales) }}</div>
                            <p class="text-xs text-muted-foreground">Excluding tax</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Gross Profit</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ formatCurrency(summary?.gross_profit) }}</div>
                            <p class="text-xs text-muted-foreground">Net Sales - Cost</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Total Cost</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ formatCurrency(summary?.total_cost) }}</div>
                            <p class="text-xs text-muted-foreground">Cost of items sold</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Payment, User Breakdown & Tax -->
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <!-- Tax Collected -->
                    <Card class="col-span-1">
                        <CardHeader>
                            <CardTitle>Tax Collected</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ formatCurrency(summary?.tax_collected) }}</div>
                            <p class="text-xs text-muted-foreground">Total tax amount</p>
                        </CardContent>
                    </Card>

                    <!-- User Breakdown -->
                    <Card class="col-span-1">
                        <CardHeader>
                            <CardTitle>Sales by User</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="summary?.user_breakdown && summary.user_breakdown.length > 0" class="space-y-4">
                                <div v-for="(user, index) in summary.user_breakdown" :key="index"
                                    class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-2 rounded-full bg-slate-500"></div>
                                        <span class="text-sm font-medium">{{ user.name }}</span>
                                    </div>
                                    <span class="font-bold">{{ formatCurrency(user.total) }}</span>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">No user data available.</p>
                        </CardContent>
                    </Card>

                    <!-- Payment Distribution -->
                    <Card class="col-span-1">
                        <CardHeader>
                            <CardTitle>Payment Distribution</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-2 rounded-full bg-green-500"></div>
                                        <span class="text-sm font-medium">Cash</span>
                                    </div>
                                    <span class="font-bold">{{ formatCurrency(summary?.cash_collected) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-2 rounded-full bg-blue-500"></div>
                                        <span class="text-sm font-medium">Card</span>
                                    </div>
                                    <span class="font-bold">{{ formatCurrency(summary?.card_collected) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-2 rounded-full bg-yellow-500"></div>
                                        <span class="text-sm font-medium">Mobile Money</span>
                                    </div>
                                    <span class="font-bold">{{ formatCurrency(summary?.momo_collected) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-2 rounded-full bg-purple-500"></div>
                                        <span class="text-sm font-medium">Insurance</span>
                                    </div>
                                    <span class="font-bold">{{ formatCurrency(summary?.insurance_billed) }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Transactions Table -->
                <div class="rounded-xl border bg-card text-card-foreground shadow p-6 break-inside-avoid">
                    <h3 class="text-lg font-semibold mb-4">Transactions List</h3>

                    <div v-if="summary?.transactions && summary.transactions.length > 0" class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-muted-foreground border-b">
                                <tr>
                                    <th class="py-2">Time</th>
                                    <th class="py-2">Receipt #</th>
                                    <th class="py-2">Customer</th>
                                    <th class="py-2">Payment</th>
                                    <th class="py-2 text-right">Total</th>
                                    <th class="py-2 text-right">Cost</th>
                                    <th class="py-2 text-right">Profit</th>
                                    <th class="py-2 text-right">Margin %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="sale in summary.transactions" :key="sale.id"
                                    class="border-b last:border-0 hover:bg-muted/50">
                                    <td class="py-2">{{ new Date(sale.finalized_at).toLocaleTimeString() }}</td>
                                    <td class="py-2 font-mono text-xs">{{ sale.id.substring(0, 8) }}</td>
                                    <td class="py-2">{{ sale.customer_name || 'Walk-in' }}</td>
                                    <td class="py-2 capitalize">{{ sale.payment_type }}</td>
                                    <td class="py-2 text-right font-medium">{{ formatCurrency(sale.total_amount) }}</td>
                                    <td class="py-2 text-right">{{ formatCurrency(sale.total_cost) }}</td>
                                    <td class="py-2 text-right" :class="Number(sale.gross_profit) < 0 ? 'text-red-600' : 'text-emerald-600'">
                                        {{ formatCurrency(sale.gross_profit) }}
                                    </td>
                                    <td class="py-2 text-right">{{ Number(sale.gross_margin || 0).toFixed(2) }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="text-muted-foreground py-4 text-center" v-else>
                        No transactions recorded for this date.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }

    /* Hide Layout Elements handled by AppLayout (This might need a global print css or more specific targeting if AppLayout renders sidebar etc) */
    /* Assuming standard layout structure, we might need to target typically global classes if they aren't scoped */

    /* Usually easier to just target the specific report container and hide everything else, but Vue scoped styles make that hard. */
    /* Better approach: Add a global print style block or rely on helper classes provided by the layout if any. */
    /* For now, we'll try to be specific to what we can control */
}
</style>
