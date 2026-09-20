<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { ref, watch, onMounted } from 'vue';
import { debounce } from 'lodash';
import { Plus, Edit, Trash2 } from 'lucide-vue-next';
import TaxModal from './TaxModal.vue';
import type { BreadcrumbItem } from '@/types';

interface TaxRate {
  id: string;
  tax_name: string;
  jurisdiction: string;
  percentage: number | string;
  tax_type: string;
  effective_from: string;
  effective_to?: string | null;
  is_active: boolean;
  description?: string | null;
  account?: { name: string } | null;
  [key: string]: unknown;
}

interface TaxPaginator {
  data: TaxRate[];
  links: unknown[];
  prev_page_url?: string | null;
  next_page_url?: string | null;
}

const props = withDefaults(defineProps<{
  taxes?: TaxPaginator;
  filters?: { search?: string };
}>(), {
  taxes: () => ({ data: [], links: [] }),
  filters: () => ({}),
});

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Taxes', href: '/app/taxes' },
];

const search = ref(props.filters?.search || '');
const showModal = ref(false);
const selectedTax = ref<TaxRate | null>(null);

watch(search, debounce((val) => {
  router.get('/app/taxes', { search: val }, { preserveState: true, preserveScroll: true });
}, 300));

onMounted(() => {
  router.reload({ only: ['taxes'] });
});

const openCreateModal = () => {
  selectedTax.value = null;
  showModal.value = true;
};

const openEditModal = (tax: TaxRate) => {
  selectedTax.value = tax;
  showModal.value = true;
};

const deleteTax = (id: string) => {
  if (confirm('Are you sure you want to delete this tax rate?')) {
    router.delete(`/app/taxes/${id}`);
  }
};

const closeModal = () => {
  showModal.value = false;
  selectedTax.value = null;
};

const goToPage = (url?: string | null) => {
  if (url) router.get(url);
};
</script>

<template>

  <Head title="Tax Rates" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-col gap-4 p-4">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold tracking-tight">Tax Rates</h1>
        <Button @click="openCreateModal">
          <Plus class="mr-2 h-4 w-4" /> Add Tax Rate
        </Button>
      </div>

      <div class="flex items-center gap-2 max-w-sm">
        <Input v-model="search" placeholder="Search taxes..." />
      </div>

      <div class="rounded-md border">
        <div class="relative w-full overflow-auto">
          <table class="w-full caption-bottom text-sm">
            <thead class="[&_tr]:border-b">
              <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Name</th>
                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Jurisdiction</th>
                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Rate (%)</th>
                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Type</th>
                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Effective</th>
                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Status</th>
                <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tax in taxes.data" :key="tax.id" class="border-b transition-colors hover:bg-muted/50">
                <td class="p-4 align-middle">{{ tax.tax_name }} <br> <span class="text-muted-foreground text-xs">{{
                  tax.description }}</span></td>
                <td class="p-4 align-middle">
                  <div>{{ tax.jurisdiction }}</div>
                  <div class="text-xs text-muted-foreground" v-if="tax.account">{{ tax.account.name }}</div>
                </td>
                <td class="p-4 align-middle font-bold">{{ tax.percentage }}%</td>
                <td class="p-4 align-middle capitalize">{{ tax.tax_type }}</td>
                <td class="p-4 align-middle">
                  {{ new Date(tax.effective_from).toLocaleDateString() }}
                  <span v-if="tax.effective_to">- {{ new Date(tax.effective_to).toLocaleDateString() }}</span>
                </td>
                <td class="p-4 align-middle">
                  <Badge :variant="tax.is_active ? 'default' : 'secondary'">{{ tax.is_active ? 'Active' : 'Inactive' }}
                  </Badge>
                </td>
                <td class="p-4 align-middle text-right">
                  <Button variant="ghost" size="icon" @click="openEditModal(tax)">
                    <Edit class="h-4 w-4" />
                  </Button>
                  <Button variant="ghost" size="icon" class="text-destructive" @click="deleteTax(tax.id)">
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </td>
              </tr>
              <tr v-if="taxes.data.length === 0">
                <td colspan="7" class="p-4 text-center text-muted-foreground">No tax rates found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination simple wrapper -->
      <div class="flex items-center justify-end space-x-2 py-4" v-if="taxes.links.length > 3">
        <Button variant="outline" size="sm" :disabled="!taxes.prev_page_url" @click="goToPage(taxes.prev_page_url)">
          Previous
        </Button>
        <Button variant="outline" size="sm" :disabled="!taxes.next_page_url" @click="goToPage(taxes.next_page_url)">
          Next
        </Button>
      </div>
    </div>

    <TaxModal :open="showModal" :tax="selectedTax" @close="closeModal" />
  </AppLayout>
</template>
