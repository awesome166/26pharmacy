<template>
  <AppLayout :breadcrumbs="breadcrumbs">

    <Head title="Journal Entries" />

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <!-- Header / Actions -->
        <div class="flex justify-between items-center mb-6">
          <div class="space-y-1">
            <h2 class="text-2xl font-bold tracking-tight">Journal Entries</h2>
            <p class="text-muted-foreground">Manage and review financial transactions.</p>
          </div>
          <div class="flex items-center gap-2">
            <div class="flex items-center gap-2 bg-background border px-3 py-1.5 rounded-md text-sm">
              <span class="text-muted-foreground">Show:</span>
              <select v-model="filterStatus" @change="refresh"
                class="bg-transparent border-none outline-none focus:ring-0 cursor-pointer">
                <option value="all">All Status</option>
                <option value="draft">Draft</option>
                <option value="posted">Posted</option>
                <option value="reversed">Reversed</option>
                <option value="voided">Voided</option>
              </select>
            </div>
            <Button @click="showCreateModal = true">
              <i class="fas fa-plus mr-2"></i> New Entry
            </Button>
          </div>
        </div>

        <Card>
          <CardContent class="p-0">
            <div class="rounded-md border">
              <table class="w-full text-sm text-left">
                <thead class="bg-muted/50 font-medium">
                  <tr class="border-b">
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground w-[120px]">Date</th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground w-[150px]">Entry #</th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Description</th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground w-[120px]">Includes</th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground w-[100px]">Status</th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground text-right w-[150px]">Total
                      Amount</th>
                    <th class="h-12 px-4 align-middle font-medium text-muted-foreground text-right w-[100px]">Actions
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="entry in filteredEntries" :key="entry.id">
                    <tr class="border-b transition-colors hover:bg-muted/50 cursor-pointer group"
                      :class="{ 'bg-muted/30': expandedEntry === entry.id }" @click="toggleDetails(entry.id)">
                      <td class="p-4 align-middle font-mono text-muted-foreground">{{ formatDate(entry.date) }}</td>
                      <td class="p-4 align-middle font-medium">
                        {{ entry.entry_number }}
                        <div v-if="entry.reference" class="text-xs text-muted-foreground">{{ entry.reference }}</div>
                      </td>
                      <td class="p-4 align-middle">
                        <div class="truncate max-w-[300px]">{{ entry.description }}</div>
                      </td>
                      <td class="p-4 align-middle">
                        <div class="flex -space-x-1 overflow-hidden">
                          <!-- Show bubbles of account types involved -->
                          <div v-for="(type, i) in getUniqueTypes(entry.details)" :key="i"
                            class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-primary rounded-full border-2 border-background"
                            :title="type">
                            {{ type[0] }}
                          </div>
                        </div>
                      </td>
                      <td class="p-4 align-middle">
                        <Badge :variant="getStatusVariant(entry.status)" class="capitalize">
                          {{ entry.status }}
                        </Badge>
                      </td>
                      <td class="p-4 align-middle text-right font-medium">
                        {{ formatCurrency(entry.total_amount || calculateTotal(entry.details)) }}
                      </td>
                      <td class="p-4 align-middle text-right" @click.stop>
                        <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                          <Button v-if="entry.status === 'draft'" variant="ghost" size="icon"
                            class="h-8 w-8 text-green-600 hover:text-green-700 hover:bg-green-100" title="Post Entry"
                            @click="postEntry(entry.id)">
                            <i class="fas fa-check"></i>
                          </Button>
                          <Button v-if="entry.status === 'posted'" variant="ghost" size="icon"
                            class="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10"
                            title="Void Entry" @click="voidEntry(entry.id)">
                            <i class="fas fa-ban"></i>
                          </Button>
                        </div>
                      </td>
                    </tr>
                    <!-- Expanded Details Row -->
                    <tr v-if="expandedEntry === entry.id" class="bg-muted/20 border-b">
                      <td colspan="7" class="p-0">
                        <div class="p-4 border-t border-b">
                          <h4 class="text-sm font-semibold mb-2 ml-1">Transaction Details</h4>
                          <div class="rounded-md border bg-background">
                            <table class="w-full text-sm">
                              <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                                <tr>
                                  <th class="py-2 px-4 text-left font-medium">Account</th>
                                  <th class="py-2 px-4 text-left font-medium">Reference</th>
                                  <th class="py-2 px-4 text-right font-medium">Debit</th>
                                  <th class="py-2 px-4 text-right font-medium">Credit</th>
                                </tr>
                              </thead>
                              <tbody>
                                <tr v-for="detail in entry.details" :key="detail.id"
                                  class="border-b last:border-0 hover:bg-muted/10">
                                  <td class="py-2 px-4">
                                    <div class="font-medium">{{ detail.chart_of_account?.name }}</div>
                                    <div class="text-xs text-muted-foreground font-mono">{{
                                      detail.chart_of_account?.code }}</div>
                                  </td>
                                  <td class="py-2 px-4 text-muted-foreground text-xs">
                                    <!-- Polymorphic ref placeholder -->
                                    {{ detail.reference_type ? detail.reference_type.split('\\').pop() + ' #' +
                                    detail.reference_id : '-' }}
                                  </td>
                                  <td class="py-2 px-4 text-right font-mono">
                                    {{ Number(detail.debit) > 0 ? formatCurrency(detail.debit) : '-' }}
                                  </td>
                                  <td class="py-2 px-4 text-right font-mono">
                                    {{ Number(detail.credit) > 0 ? formatCurrency(detail.credit) : '-' }}
                                  </td>
                                </tr>
                              </tbody>
                            </table>
                          </div>
                          <div class="mt-3 rounded-md border bg-muted/30 px-3 py-2 flex items-center justify-between">
                            <div class="text-xs">
                              <div class="font-medium text-foreground">Posted By</div>
                              <div class="text-muted-foreground">{{ getPostedByName(entry) }}</div>
                              <div v-if="getPostedByEmail(entry)" class="text-muted-foreground/80">{{ getPostedByEmail(entry) }}</div>
                            </div>
                            <div class="text-xs text-right">
                              <div class="font-medium text-foreground">Posted At</div>
                              <div class="text-muted-foreground">{{ entry.posted_at ? formatDateTime(entry.posted_at) : 'Not Posted' }}</div>
                            </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </template>
                  <tr v-if="filteredEntries.length === 0">
                    <td colspan="7" class="p-8 text-center text-muted-foreground">
                      No entries found.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>

    <JournalEntryCreateModal v-if="showCreateModal" :open="showCreateModal" :accounts="accounts"
      @close="showCreateModal = false" @success="refresh" />

  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import JournalEntryCreateModal from './Components/JournalEntryCreateModal.vue';

const props = defineProps({
  entries: Object,
  accounts: Array,
  filters: Object,
});

const accounts = ref(props.accounts || []);
const breadcrumbs = [
  { title: 'Dashboard', href: '/accounting' },
  { title: 'Journal Entries', href: '/accounting/journal-entries' },
];

const expandedEntry = ref(null);
const showCreateModal = ref(false);
const filterStatus = ref(props.filters?.status || 'all');

const filteredEntries = computed(() => {
  if (filterStatus.value === 'all') return props.entries.data;
  return props.entries.data.filter(e => e.status === filterStatus.value);
});

const toggleDetails = (id) => {
  expandedEntry.value = expandedEntry.value === id ? null : id;
};

const getUniqueTypes = (details) => {
  if (!details) return [];
  const types = details.map(d => d.chart_of_account?.type).filter(Boolean);
  return [...new Set(types)];
};

const calculateTotal = (details) => {
  return details.reduce((sum, d) => sum + Number(d.debit), 0);
};

const refresh = () => {
  router.get('/accounting/journal-entries', { status: filterStatus.value }, { preserveState: true, preserveScroll: true });
};

const postEntry = (id) => {
  if (!confirm("Are you sure you want to post this entry? Balance updates are permanent.")) return;
  router.post(route('accounting.journal-entries.post', id), {}, { preserveScroll: true });
};

const voidEntry = (id) => {
  if (!confirm("Confirm VOID? This will reverse all balances.")) return;
  router.post(route('accounting.journal-entries.void', id), {}, { preserveScroll: true });
};

const formatDate = (date) => new Date(date).toLocaleDateString();
const formatDateTime = (date) => new Date(date).toLocaleString();
const formatCurrency = (val) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'GHS' }).format(val);

const getPostedByName = (entry) => {
  const posted = entry?.posted_by;
  if (!posted) return 'System';
  if (typeof posted === 'string') return posted;
  if (typeof posted === 'object') return posted.name || 'System';
  return 'System';
};

const getPostedByEmail = (entry) => {
  const posted = entry?.posted_by;
  if (posted && typeof posted === 'object') return posted.email || '';
  return '';
};

  const getStatusVariant = (status) => {
  switch (status) {
    case 'posted': return 'default';
    case 'reversed': return 'secondary';
    case 'draft': return 'outline'; // changed to outline to be less aggressive
    case 'voided': return 'destructive';
    default: return 'secondary';
  }
};
</script>
