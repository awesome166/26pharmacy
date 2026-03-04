<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface AccountingAccount {
  id: string;
  code: string;
  name: string;
  type: string;
}

const props = defineProps<{
  balanceSheet: {
    as_of: string;
    total_assets: number;
    total_liabilities: number;
    total_equity: number;
  };
  operations: {
    date: string;
    sales_total: number;
    sales_count: number;
    returns_total: number;
    returns_count: number;
    cash_in_total: number;
    cash_out_total: number;
  };
  recentEntries: Array<any>;
  closures: Array<any>;
  accounts: AccountingAccount[];
  filters: { date: string };
}>();

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Accounting', href: '/accounting' },
];

const reportDate = ref(props.filters?.date || new Date().toISOString().slice(0, 10));
const closeDate = ref(new Date().toISOString().slice(0, 10));
const closeNotes = ref('');

const cashForm = ref({
  movement: 'in',
  amount: '',
  date: new Date().toISOString().slice(0, 10),
  cash_account_id: props.accounts.find((a) => a.code === '1000')?.id || '',
  counterpart_account_id: '',
  memo: '',
});

const netPosition = computed(() =>
  Number(props.balanceSheet.total_assets || 0) - Number(props.balanceSheet.total_liabilities || 0),
);

const cashAccountChoices = computed(() =>
  props.accounts.filter((a) => a.type === 'Asset'),
);

const counterpartChoices = computed(() =>
  props.accounts.filter((a) => a.id !== cashForm.value.cash_account_id),
);

const updateDate = () => {
  router.get('/accounting', { date: reportDate.value }, { preserveState: true, preserveScroll: true });
};

const closeBooks = () => {
  router.post('/accounting/close-books', {
    business_date: closeDate.value,
    notes: closeNotes.value,
  });
};

const submitCashMovement = () => {
  router.post('/accounting/cash-movements', {
    movement: cashForm.value.movement,
    amount: Number(cashForm.value.amount),
    date: cashForm.value.date,
    cash_account_id: cashForm.value.cash_account_id || null,
    counterpart_account_id: cashForm.value.counterpart_account_id,
    memo: cashForm.value.memo,
  }, {
    onSuccess: () => {
      cashForm.value.amount = '';
      cashForm.value.counterpart_account_id = '';
      cashForm.value.memo = '';
    },
  });
};

const formatCurrency = (val: number | string) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'GHS' }).format(Number(val || 0));
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Accounting Dashboard" />

    <div class="p-4 space-y-5">
      <div class="rounded-xl border bg-gradient-to-r from-slate-900 via-slate-800 to-cyan-900 text-white p-5">
        <div class="flex items-center justify-between gap-4 flex-wrap">
          <div>
            <h2 class="text-2xl font-bold tracking-tight">Accounting Command Center</h2>
            <p class="text-slate-200 text-sm">Daily close, cash control, journals, and operational accounting in one view.</p>
          </div>
          <div class="flex items-center gap-2">
            <Label for="asOf" class="text-slate-100">As of</Label>
            <Input id="asOf" type="date" v-model="reportDate" class="w-[170px] bg-white text-slate-900" />
            <Button variant="secondary" @click="updateDate">Refresh</Button>
          </div>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader class="pb-2"><CardTitle class="text-sm font-medium">Total Assets</CardTitle></CardHeader>
          <CardContent><div class="text-2xl font-bold">{{ formatCurrency(balanceSheet.total_assets) }}</div></CardContent>
        </Card>
        <Card>
          <CardHeader class="pb-2"><CardTitle class="text-sm font-medium">Total Liabilities</CardTitle></CardHeader>
          <CardContent><div class="text-2xl font-bold">{{ formatCurrency(balanceSheet.total_liabilities) }}</div></CardContent>
        </Card>
        <Card>
          <CardHeader class="pb-2"><CardTitle class="text-sm font-medium">Total Equity</CardTitle></CardHeader>
          <CardContent><div class="text-2xl font-bold">{{ formatCurrency(balanceSheet.total_equity) }}</div></CardContent>
        </Card>
        <Card>
          <CardHeader class="pb-2"><CardTitle class="text-sm font-medium">Net Position</CardTitle></CardHeader>
          <CardContent><div class="text-2xl font-bold">{{ formatCurrency(netPosition) }}</div></CardContent>
        </Card>
      </div>

      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader class="pb-2"><CardTitle class="text-sm font-medium">Daily Sales</CardTitle></CardHeader>
          <CardContent>
            <div class="text-xl font-semibold">{{ formatCurrency(operations.sales_total) }}</div>
            <div class="text-xs text-muted-foreground mt-1">{{ operations.sales_count }} transactions</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader class="pb-2"><CardTitle class="text-sm font-medium">Daily Returns</CardTitle></CardHeader>
          <CardContent>
            <div class="text-xl font-semibold">{{ formatCurrency(operations.returns_total) }}</div>
            <div class="text-xs text-muted-foreground mt-1">{{ operations.returns_count }} return records</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader class="pb-2"><CardTitle class="text-sm font-medium">Cash In</CardTitle></CardHeader>
          <CardContent><div class="text-xl font-semibold">{{ formatCurrency(operations.cash_in_total) }}</div></CardContent>
        </Card>
        <Card>
          <CardHeader class="pb-2"><CardTitle class="text-sm font-medium">Cash Out</CardTitle></CardHeader>
          <CardContent><div class="text-xl font-semibold">{{ formatCurrency(operations.cash_out_total) }}</div></CardContent>
        </Card>
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <Card class="lg:col-span-2">
          <CardHeader>
            <CardTitle>Recent Journal Entries</CardTitle>
            <CardDescription>Latest posted and draft accounting movements.</CardDescription>
          </CardHeader>
          <CardContent>
            <div v-if="recentEntries.length" class="space-y-2">
              <div v-for="entry in recentEntries" :key="entry.id" class="border rounded px-3 py-2 flex justify-between items-center">
                <div>
                  <div class="font-medium">{{ entry.entry_number }} - {{ entry.description }}</div>
                  <div class="text-xs text-muted-foreground">{{ entry.date }} | {{ entry.status }} | {{ entry.reference || 'No Ref' }}</div>
                </div>
                <div class="font-semibold">{{ formatCurrency(entry.total_amount) }}</div>
              </div>
            </div>
            <div v-else class="text-sm text-muted-foreground">No journal entries yet.</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Daily Book Closing</CardTitle>
            <CardDescription>Lock prior business dates after reconciliation.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <div>
              <Label for="closeDate">Business Date</Label>
              <Input id="closeDate" type="date" v-model="closeDate" class="mt-1" />
            </div>
            <div>
              <Label for="closeNotes">Notes</Label>
              <Input id="closeNotes" v-model="closeNotes" class="mt-1" placeholder="Optional closing note" />
            </div>
            <Button class="w-full" @click="closeBooks">Close Books</Button>
            <div class="pt-2 border-t">
              <div class="text-xs font-medium text-muted-foreground mb-1">Recent Closures</div>
              <div v-if="closures.length" class="space-y-1">
                <div v-for="item in closures" :key="item.id" class="text-xs flex justify-between">
                  <span>{{ item.business_date }}</span>
                  <span class="uppercase">{{ item.status }}</span>
                </div>
              </div>
              <div v-else class="text-xs text-muted-foreground">No closure history.</div>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Cash Input / Output</CardTitle>
          <CardDescription>Post controlled cash movements as balanced journal entries.</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid gap-3 md:grid-cols-6">
            <div class="space-y-1">
              <Label>Movement</Label>
              <select v-model="cashForm.movement" class="w-full rounded-md border px-3 py-2 text-sm">
                <option value="in">Cash In</option>
                <option value="out">Cash Out</option>
              </select>
            </div>
            <div class="space-y-1">
              <Label>Amount</Label>
              <Input type="number" step="0.01" min="0" v-model="cashForm.amount" placeholder="0.00" />
            </div>
            <div class="space-y-1">
              <Label>Date</Label>
              <Input type="date" v-model="cashForm.date" />
            </div>
            <div class="space-y-1">
              <Label>Cash Account</Label>
              <select v-model="cashForm.cash_account_id" class="w-full rounded-md border px-3 py-2 text-sm">
                <option v-for="account in cashAccountChoices" :key="account.id" :value="account.id">
                  {{ account.code }} - {{ account.name }}
                </option>
              </select>
            </div>
            <div class="space-y-1">
              <Label>Counterpart</Label>
              <select v-model="cashForm.counterpart_account_id" class="w-full rounded-md border px-3 py-2 text-sm">
                <option value="">Select account</option>
                <option v-for="account in counterpartChoices" :key="account.id" :value="account.id">
                  {{ account.code }} - {{ account.name }}
                </option>
              </select>
            </div>
            <div class="space-y-1">
              <Label>Memo</Label>
              <Input v-model="cashForm.memo" placeholder="Reason" />
            </div>
          </div>
          <div class="mt-3 flex justify-end">
            <Button @click="submitCashMovement" :disabled="!cashForm.amount || !cashForm.counterpart_account_id">
              Post Cash Movement
            </Button>
          </div>
        </CardContent>
      </Card>

      <div class="flex gap-2 flex-wrap">
        <Link href="/accounting/accounts"><Button variant="outline">Chart of Accounts</Button></Link>
        <Link href="/accounting/journal-entries"><Button variant="outline">Journal Entries</Button></Link>
        <Link href="/accounting/reports/balance-sheet"><Button variant="outline">All Reports</Button></Link>
      </div>
    </div>
  </AppLayout>
</template>
