<template>
  <AppLayout :breadcrumbs="breadcrumbs">

    <Head title="Balance Sheet" />

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <Card>
          <CardHeader>
            <CardTitle>Balance Sheet</CardTitle>
            <CardDescription>Financial position as of {{ formatDate(filters.date || new Date()) }}</CardDescription>
            <div class="flex items-center gap-4 mt-4">
              <div class="flex items-center gap-2">
                <Label>As of Date:</Label>
                <Input type="date" v-model="filterForm.date" class="w-auto" />
              </div>
              <Button @click="applyFilters">Update Report</Button>
            </div>
          </CardHeader>
          <CardContent>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <!-- Assets -->
              <div>
                <h3 class="font-bold text-lg mb-4 border-b pb-2">Assets</h3>
                <div class="space-y-4">
                  <div v-for="account in reportData.assets" :key="account.id">
                    <div class="flex justify-between font-medium">
                      <span>{{ account.name }}</span>
                      <span>{{ formatCurrency(account.current_balance) }}</span>
                    </div>
                    <!-- Children -->
                    <div v-if="account.children" class="pl-4 text-sm text-muted-foreground space-y-1 mt-1">
                      <div v-for="child in account.children" :key="child.id" class="flex justify-between">
                        <span>{{ child.name }}</span>
                        <span>{{ formatCurrency(child.current_balance) }}</span>
                      </div>
                    </div>
                  </div>
                  <div class="flex justify-between font-bold text-lg border-t pt-2 mt-4">
                    <span>Total Assets</span>
                    <span>{{ formatCurrency(reportData.total_assets) }}</span>
                  </div>
                </div>
              </div>

              <!-- Liabilities & Equity -->
              <div>
                <h3 class="font-bold text-lg mb-4 border-b pb-2">Liabilities</h3>
                <div class="space-y-4">
                  <div v-for="account in reportData.liabilities" :key="account.id">
                    <div class="flex justify-between font-medium">
                      <span>{{ account.name }}</span>
                      <span>{{ formatCurrency(account.current_balance) }}</span>
                    </div>
                    <div v-if="account.children" class="pl-4 text-sm text-muted-foreground space-y-1 mt-1">
                      <div v-for="child in account.children" :key="child.id" class="flex justify-between">
                        <span>{{ child.name }}</span>
                        <span>{{ formatCurrency(child.current_balance) }}</span>
                      </div>
                    </div>
                  </div>
                  <div class="flex justify-between font-bold border-t pt-2 mt-4">
                    <span>Total Liabilities</span>
                    <span>{{ formatCurrency(reportData.total_liabilities) }}</span>
                  </div>
                </div>

                <h3 class="font-bold text-lg mb-4 border-b pb-2 mt-8">Equity</h3>
                <div class="space-y-4">
                  <div v-for="account in reportData.equity" :key="account.id">
                    <div class="flex justify-between font-medium">
                      <span>{{ account.name }}</span>
                      <span>{{ formatCurrency(account.current_balance) }}</span>
                    </div>
                    <div v-if="account.children" class="pl-4 text-sm text-muted-foreground space-y-1 mt-1">
                      <div v-for="child in account.children" :key="child.id" class="flex justify-between">
                        <span>{{ child.name }}</span>
                        <span>{{ formatCurrency(child.current_balance) }}</span>
                      </div>
                    </div>
                  </div>
                  <div class="flex justify-between font-bold border-t pt-2 mt-4">
                    <span>Total Equity</span>
                    <span>{{ formatCurrency(reportData.total_equity) }}</span>
                  </div>
                </div>

                <div
                  class="flex justify-between font-bold text-lg border-t-2 border-black pt-2 mt-8 bg-muted/20 p-2 rounded">
                  <span>Total Liabilities & Equity</span>
                  <span>{{ formatCurrency(Number(reportData.total_liabilities) + Number(reportData.total_equity))
                    }}</span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps({
  reportData: Object,
  filters: Object
});

const breadcrumbs = [
  { title: 'Dashboard', href: '/accounting' },
  { title: 'Balance Sheet', href: '/accounting/reports/balance-sheet' },
];

const filterForm = useForm({
  date: props.filters.date || new Date().toISOString().substr(0, 10),
});

const applyFilters = () => {
  filterForm.get(route('accounting.reports.balance-sheet'), {
    preserveState: true,
    preserveScroll: true,
  });
};

const formatDate = (date) => new Date(date).toLocaleDateString();
const formatCurrency = (val) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
</script>
