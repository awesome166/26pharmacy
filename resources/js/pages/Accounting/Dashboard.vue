<template>
  <AppLayout :breadcrumbs="breadcrumbs">

    <Head title="Accounting Dashboard" />

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

        <!-- Welcome Section -->
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-3xl font-bold tracking-tight">Financial Overview</h2>
            <p class="text-muted-foreground">Welcome back, here's what's happening with your finances today.</p>
          </div>
          <div class="flex items-center space-x-2">
            <Button variant="outline">Download Report</Button>
            <Link href="/accounting/journal-entries">
              <Button>
                <i class="fas fa-plus mr-2"></i> New Entry
              </Button>
            </Link>
          </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Card class="hover:shadow-md transition-shadow">
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Total Assets</CardTitle>
              <i class="fas fa-wallet text-muted-foreground h-4 w-4"></i>
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">$145,231.89</div>
              <p class="text-xs text-muted-foreground text-green-600">
                <i class="fas fa-arrow-up mr-1"></i> +20.1% from last month
              </p>
            </CardContent>
          </Card>
          <Card class="hover:shadow-md transition-shadow">
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Liabilities</CardTitle>
              <i class="fas fa-file-invoice-dollar text-muted-foreground h-4 w-4"></i>
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">$23,450.00</div>
              <p class="text-xs text-muted-foreground text-red-600">
                <i class="fas fa-arrow-up mr-1"></i> +4.5% from last month
              </p>
            </CardContent>
          </Card>
          <Card class="hover:shadow-md transition-shadow">
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Net Income (YTD)</CardTitle>
              <i class="fas fa-chart-line text-muted-foreground h-4 w-4"></i>
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">$82,100.50</div>
              <p class="text-xs text-muted-foreground text-green-600">
                <i class="fas fa-arrow-up mr-1"></i> +12.3% from last year
              </p>
            </CardContent>
          </Card>
          <Card class="hover:shadow-md transition-shadow">
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Pending Approvals</CardTitle>
              <i class="fas fa-bell text-muted-foreground h-4 w-4"></i>
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">12</div>
              <p class="text-xs text-muted-foreground">3 high priority</p>
            </CardContent>
          </Card>
        </div>

        <!-- Main Content Grid -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-7">

          <!-- Recent Activity / Chart Placeholder -->
          <Card class="col-span-4">
            <CardHeader>
              <CardTitle>Cash Flow</CardTitle>
              <CardDescription>Income vs Expenses over the last 6 months.</CardDescription>
            </CardHeader>
            <CardContent class="pl-2">
              <div class="h-[300px] flex items-center justify-center bg-muted/10 rounded-md border border-dashed">
                <p class="text-muted-foreground">Chart Visualization Placeholder</p>
                <!-- Integrate Chart.js or Recharts here later -->
              </div>
            </CardContent>
          </Card>

          <!-- Quick Actions & Recent Transactions -->
          <Card class="col-span-3">
            <CardHeader>
              <CardTitle>Quick Access</CardTitle>
              <CardDescription>Commonly used accounting tools.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4">
              <Link href="/accounting/accounts"
                class="group flex items-center justify-between rounded-lg border p-4 hover:bg-muted/50 transition-colors">
                <div class="flex items-center gap-4">
                  <div class="rounded-full bg-blue-100 p-2 text-blue-600 group-hover:bg-blue-200 transition-colors">
                    <i class="fas fa-list h-5 w-5"></i>
                  </div>
                  <div>
                    <div class="font-medium">Chart of Accounts</div>
                    <div class="text-xs text-muted-foreground">Manage hierarchy</div>
                  </div>
                </div>
                <i class="fas fa-chevron-right text-muted-foreground"></i>
              </Link>

              <Link href="/accounting/journal-entries"
                class="group flex items-center justify-between rounded-lg border p-4 hover:bg-muted/50 transition-colors">
                <div class="flex items-center gap-4">
                  <div
                    class="rounded-full bg-purple-100 p-2 text-purple-600 group-hover:bg-purple-200 transition-colors">
                    <i class="fas fa-book h-5 w-5"></i>
                  </div>
                  <div>
                    <div class="font-medium">Journal Entries</div>
                    <div class="text-xs text-muted-foreground">Post transactions</div>
                  </div>
                </div>
                <i class="fas fa-chevron-right text-muted-foreground"></i>
              </Link>

              <Link href="/accounting/reports/balance-sheet"
                class="group flex items-center justify-between rounded-lg border p-4 hover:bg-muted/50 transition-colors">
                <div class="flex items-center gap-4">
                  <div class="rounded-full bg-green-100 p-2 text-green-600 group-hover:bg-green-200 transition-colors">
                    <i class="fas fa-file-invoice h-5 w-5"></i>
                  </div>
                  <div>
                    <div class="font-medium">Financial Reports</div>
                    <div class="text-xs text-muted-foreground">View Balance Sheet</div>
                  </div>
                </div>
                <i class="fas fa-chevron-right text-muted-foreground"></i>
              </Link>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Accounting', href: '/accounting' },
];
</script>
