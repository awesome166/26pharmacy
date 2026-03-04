<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
  reportData: any;
  incomeData: any;
  trialData: any;
  filters: { date: string; from: string; to: string };
}>();

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Accounting', href: '/accounting' },
  { title: 'Reports', href: '/accounting/reports/balance-sheet' },
];

const dateFilter = ref(props.filters?.date || new Date().toISOString().slice(0, 10));
const incomeFrom = ref(props.filters?.from || new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10));
const incomeTo = ref(props.filters?.to || new Date().toISOString().slice(0, 10));

const activeTab = ref<'balance-sheet' | 'income-statement' | 'trial-balance'>('balance-sheet');
const balanceSheetTab = ref<'assets' | 'liabilities' | 'equity'>('assets');

const applyFilters = () => {
  router.get('/accounting/reports/balance-sheet', {
    date: dateFilter.value,
    from: incomeFrom.value,
    to: incomeTo.value,
  }, { preserveState: true, preserveScroll: true });
};

const formatCurrency = (val: number | string) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'GHS' }).format(Number(val || 0));
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">

    <Head title="Accounting Reports" />

    <div class="p-4 space-y-4">
      <Card>
        <CardHeader>
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <CardTitle>Financial Reports</CardTitle>
              <CardDescription>View balance sheet, income statement, and trial balance</CardDescription>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
              <template v-if="activeTab === 'balance-sheet' || activeTab === 'trial-balance'">
                <Label class="text-xs whitespace-nowrap">As of</Label>
                <Input type="date" v-model="dateFilter" class="w-[160px] h-9" />
              </template>
              <template v-if="activeTab === 'income-statement'">
                <Label class="text-xs whitespace-nowrap">From</Label>
                <Input type="date" v-model="incomeFrom" class="w-[160px] h-9" />
                <Label class="text-xs whitespace-nowrap">To</Label>
                <Input type="date" v-model="incomeTo" class="w-[160px] h-9" />
              </template>
              <Button size="sm" @click="applyFilters">Run</Button>
            </div>
          </div>

          <!-- Top-level report tabs -->
          <div class="mt-4 border-b">
            <div class="flex gap-0 -mb-px">
              <button type="button"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap" :class="activeTab === 'balance-sheet'
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground hover:border-muted-foreground/30'"
                @click="activeTab = 'balance-sheet'">
                Balance Sheet
              </button>
              <button type="button"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap" :class="activeTab === 'income-statement'
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground hover:border-muted-foreground/30'"
                @click="activeTab = 'income-statement'">
                Income Statement
              </button>
              <button type="button"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap" :class="activeTab === 'trial-balance'
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground hover:border-muted-foreground/30'"
                @click="activeTab = 'trial-balance'">
                Trial Balance
              </button>
            </div>
          </div>
        </CardHeader>

        <CardContent>
          <!-- ==================== BALANCE SHEET ==================== -->
          <div v-if="activeTab === 'balance-sheet'" class="space-y-4">
            <div class="inline-flex rounded-md border bg-muted/30 p-1">
              <button type="button" class="rounded-sm px-3 py-1.5 text-sm font-medium transition-colors"
                :class="balanceSheetTab === 'assets' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'"
                @click="balanceSheetTab = 'assets'">
                Assets
              </button>
              <button type="button" class="rounded-sm px-3 py-1.5 text-sm font-medium transition-colors"
                :class="balanceSheetTab === 'liabilities' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'"
                @click="balanceSheetTab = 'liabilities'">
                Liabilities
              </button>
              <button type="button" class="rounded-sm px-3 py-1.5 text-sm font-medium transition-colors"
                :class="balanceSheetTab === 'equity' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'"
                @click="balanceSheetTab = 'equity'">
                Equity
              </button>
            </div>

            <div v-if="balanceSheetTab === 'assets'">
              <h3 class="font-semibold mb-2">Assets</h3>
              <div class="space-y-1">
                <div v-for="account in reportData.assets" :key="account.id" class="flex justify-between text-sm">
                  <span>{{ account.code }} - {{ account.name }}</span>
                  <span>{{ formatCurrency(account.current_balance) }}</span>
                </div>
              </div>
              <div class="border-t mt-2 pt-2 font-semibold flex justify-between">
                <span>Total Assets</span>
                <span>{{ formatCurrency(reportData.total_assets) }}</span>
              </div>
            </div>

            <div v-if="balanceSheetTab === 'liabilities'">
              <h3 class="font-semibold mb-2">Liabilities</h3>
              <div class="space-y-1">
                <div v-for="account in reportData.liabilities" :key="account.id" class="flex justify-between text-sm">
                  <span>{{ account.code }} - {{ account.name }}</span>
                  <span>{{ formatCurrency(account.current_balance) }}</span>
                </div>
              </div>
              <div class="border-t mt-2 pt-2 font-semibold flex justify-between">
                <span>Total Liabilities</span>
                <span>{{ formatCurrency(reportData.total_liabilities) }}</span>
              </div>
            </div>

            <div v-if="balanceSheetTab === 'equity'">
              <h3 class="font-semibold mb-2">Equity</h3>
              <div class="space-y-1">
                <div v-for="account in reportData.equity" :key="account.id" class="flex justify-between text-sm">
                  <span>{{ account.code }} - {{ account.name }}</span>
                  <span>{{ formatCurrency(account.current_balance) }}</span>
                </div>
              </div>
              <div class="border-t mt-2 pt-2 font-semibold flex justify-between">
                <span>Total Equity</span>
                <span>{{ formatCurrency(reportData.total_equity) }}</span>
              </div>
            </div>
          </div>

          <!-- ==================== INCOME STATEMENT ==================== -->
          <div v-if="activeTab === 'income-statement'">
            <template v-if="incomeData">
              <div class="grid md:grid-cols-2 gap-6 text-sm">
                <div>
                  <h3 class="font-semibold mb-2">Revenue</h3>
                  <div v-for="row in incomeData.revenue" :key="row.id" class="flex justify-between">
                    <span>{{ row.code }} - {{ row.name }}</span>
                    <span>{{ formatCurrency(row.amount) }}</span>
                  </div>
                  <div class="border-t pt-2 mt-2 font-semibold flex justify-between">
                    <span>Total Revenue</span>
                    <span>{{ formatCurrency(incomeData.total_revenue) }}</span>
                  </div>
                </div>
                <div>
                  <h3 class="font-semibold mb-2">Expenses</h3>
                  <div v-for="row in incomeData.expenses" :key="row.id" class="flex justify-between">
                    <span>{{ row.code }} - {{ row.name }}</span>
                    <span>{{ formatCurrency(row.amount) }}</span>
                  </div>
                  <div class="border-t pt-2 mt-2 font-semibold flex justify-between">
                    <span>Total Expenses</span>
                    <span>{{ formatCurrency(incomeData.total_expenses) }}</span>
                  </div>
                </div>
              </div>
              <div class="border-t pt-3 mt-3 font-bold flex justify-between">
                <span>Net Income</span>
                <span>{{ formatCurrency(incomeData.net_income) }}</span>
              </div>
            </template>
            <div v-else class="text-center text-muted-foreground py-10">
              No income data available. Adjust the date range and click Run.
            </div>
          </div>

          <!-- ==================== TRIAL BALANCE ==================== -->
          <div v-if="activeTab === 'trial-balance'">
            <template v-if="trialData">
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="border-b">
                    <tr>
                      <th class="text-left py-2">Code</th>
                      <th class="text-left py-2">Account</th>
                      <th class="text-right py-2">Debit</th>
                      <th class="text-right py-2">Credit</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in trialData.rows" :key="row.id" class="border-b">
                      <td class="py-2">{{ row.code }}</td>
                      <td class="py-2">{{ row.name }}</td>
                      <td class="py-2 text-right">{{ formatCurrency(row.debit) }}</td>
                      <td class="py-2 text-right">{{ formatCurrency(row.credit) }}</td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr class="font-semibold">
                      <td colspan="2" class="py-2">Totals</td>
                      <td class="py-2 text-right">{{ formatCurrency(trialData.total_debit) }}</td>
                      <td class="py-2 text-right">{{ formatCurrency(trialData.total_credit) }}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </template>
            <div v-else class="text-center text-muted-foreground py-10">
              No trial balance data available. Click Run to generate.
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
