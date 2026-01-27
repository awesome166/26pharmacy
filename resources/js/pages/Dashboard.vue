<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { TrendingUp, DollarSign, AlertTriangle, ShoppingCart, RefreshCw } from 'lucide-vue-next';
import LineChart from '@/components/charts/LineChart.vue';
import BarChart from '@/components/charts/BarChart.vue';
import DoughnutChart from '@/components/charts/DoughnutChart.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

// Stats data
const stats = ref({
    today_sales_count: 0,
    today_revenue: 0,
    low_stock_count: 0,
    month_sales_count: 0,
});

// Chart data
const salesTrendData = ref({
    labels: [],
    sales: [],
    revenue: [],
});

const topProductsData = ref({
    labels: [],
    data: [],
});

const paymentBreakdownData = ref({
    labels: [],
    data: [],
});

const isLoading = ref(true);
const isRefreshing = ref(false);

const fetchDashboardData = async () => {
    isRefreshing.value = true;

    try {
        const [statsRes, trendRes, productsRes, paymentsRes] = await Promise.all([
            import('axios').then(({ default: axios }) =>
                axios.get('/app/dashboard/stats', { headers: { 'Accept': 'application/json' } })
            ),
            import('axios').then(({ default: axios }) =>
                axios.get('/app/dashboard/sales-trend?days=7', { headers: { 'Accept': 'application/json' } })
            ),
            import('axios').then(({ default: axios }) =>
                axios.get('/app/dashboard/top-products?limit=10', { headers: { 'Accept': 'application/json' } })
            ),
            import('axios').then(({ default: axios }) =>
                axios.get('/app/dashboard/payment-breakdown', { headers: { 'Accept': 'application/json' } })
            ),
        ]);

        stats.value = statsRes.data;
        salesTrendData.value = trendRes.data;
        topProductsData.value = productsRes.data;
        paymentBreakdownData.value = paymentsRes.data;
    } catch (error) {
        console.error('Failed to fetch dashboard data:', error);
    } finally {
        isLoading.value = false;
        isRefreshing.value = false;
    }
};

onMounted(() => {
    fetchDashboardData();
});

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'GHS' }).format(value);
};
</script>

<template>

    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header with Refresh Button -->
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight">Dashboard</h2>
                    <p class="text-muted-foreground">Overview of your pharmacy operations</p>
                </div>
                <Button @click="fetchDashboardData" :disabled="isRefreshing" variant="outline" class="gap-2">
                    <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isRefreshing }" />
                    Refresh
                </Button>
            </div>

            <!-- Statistics Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <!-- Today's Sales -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Today's Sales</CardTitle>
                        <ShoppingCart class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ isLoading ? '...' : stats.today_sales_count }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Transactions completed today
                        </p>
                    </CardContent>
                </Card>

                <!-- Today's Revenue -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Today's Revenue</CardTitle>
                        <DollarSign class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ isLoading ? '...' : formatCurrency(stats.today_revenue) }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Total revenue for today
                        </p>
                    </CardContent>
                </Card>

                <!-- Low Stock Alert -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Low Stock Alert</CardTitle>
                        <AlertTriangle class="h-4 w-4 text-amber-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold" :class="stats.low_stock_count > 0 ? 'text-amber-600' : ''">
                            {{ isLoading ? '...' : stats.low_stock_count }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Items below reorder level
                        </p>
                    </CardContent>
                </Card>

                <!-- This Month's Sales -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">This Month</CardTitle>
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ isLoading ? '...' : stats.month_sales_count }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Total sales this month
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Charts Section -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Sales & Revenue Trend -->
                <Card class="col-span-2 lg:col-span-1">
                    <CardHeader>
                        <CardTitle>Sales & Revenue Trend</CardTitle>
                        <CardDescription>Last 7 days performance</CardDescription>
                    </CardHeader>
                    <CardContent class="h-[300px]">
                        <LineChart v-if="!isLoading && salesTrendData.labels.length > 0" :labels="salesTrendData.labels"
                            :datasets="[
                                {
                                    label: 'Sales Count',
                                    data: salesTrendData.sales,
                                    borderColor: 'rgb(59, 130, 246)',
                                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                    fill: true,
                                },
                                {
                                    label: 'Revenue (GHS)',
                                    data: salesTrendData.revenue,
                                    borderColor: 'rgb(16, 185, 129)',
                                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                    fill: true,
                                }
                            ]" />
                        <div v-else class="flex items-center justify-center h-full text-muted-foreground">
                            {{ isLoading ? 'Loading...' : 'No data available' }}
                        </div>
                    </CardContent>
                </Card>

                <!-- Payment Methods Breakdown -->
                <Card>
                    <CardHeader>
                        <CardTitle>Payment Methods</CardTitle>
                        <CardDescription>Distribution of payment types</CardDescription>
                    </CardHeader>
                    <CardContent class="h-[300px]">
                        <DoughnutChart v-if="!isLoading && paymentBreakdownData.labels.length > 0"
                            :labels="paymentBreakdownData.labels" :data="paymentBreakdownData.data" />
                        <div v-else class="flex items-center justify-center h-full text-muted-foreground">
                            {{ isLoading ? 'Loading...' : 'No data available' }}
                        </div>
                    </CardContent>
                </Card>

                <!-- Top Selling Products -->
                <Card class="col-span-2">
                    <CardHeader>
                        <CardTitle>Top Selling Products</CardTitle>
                        <CardDescription>Best performing items</CardDescription>
                    </CardHeader>
                    <CardContent class="h-[300px]">
                        <BarChart v-if="!isLoading && topProductsData.labels.length > 0"
                            :labels="topProductsData.labels" :data="topProductsData.data" />
                        <div v-else class="flex items-center justify-center h-full text-muted-foreground">
                            {{ isLoading ? 'Loading...' : 'No data available' }}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
