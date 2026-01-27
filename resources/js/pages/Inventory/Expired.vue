<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { ref, watch, computed, onMounted } from 'vue';
import { debounce } from 'lodash';
import type { BreadcrumbItem } from '@/types';

const page = usePage();

const daysThreshold = ref('0');
const search = ref('');
const inventory = ref({ data: [], links: [] });
const isLoading = ref(true);
const selectedItems = ref<string[]>([]);
const disposeDialogOpen = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Inventory', href: '/app/inventory' },
  { title: 'Expired Stock', href: '/inventory/expired' },
];

const fetchExpired = (url = '/app/inventory/expired') => {
  isLoading.value = true;
  import('axios').then(({ default: axios }) => {
    axios.get(url, {
      params: {
        search: search.value,
        days_threshold: daysThreshold.value
      },
      headers: { 'Accept': 'application/json' }
    })
      .then(response => {
        inventory.value = response.data.data ? response.data.data : response.data;
      })
      .catch(error => {
        console.error("Failed to fetch expired inventory", error);
      })
      .finally(() => {
        isLoading.value = false;
      });
  });
};

onMounted(() => {
  fetchExpired();
});

watch([search, daysThreshold], debounce(() => {
  fetchExpired();
}, 300));

const toggleSelection = (batchId: string) => {
  if (selectedItems.value.includes(batchId)) {
    selectedItems.value = selectedItems.value.filter(id => id !== batchId);
  } else {
    selectedItems.value.push(batchId);
  }
};

const toggleAll = (event: any) => {
  if (event) {
    selectedItems.value = inventory.value.data.map((item: any) => item.batch_id);
  } else {
    selectedItems.value = [];
  }
};

const disposeForm = useForm({
  items: [] as any[],
});

const openDisposeDialog = () => {
  if (selectedItems.value.length === 0) return;
  disposeDialogOpen.value = true;
};

const submitDispose = () => {
  // Prepare items
  const itemsToDispose = inventory.value.data
    .filter((item: any) => selectedItems.value.includes(item.batch_id))
    .map((item: any) => ({
      batch_id: item.batch_id,
      quantity: item.quantity_on_hand // Default to disposing all
    }));

  disposeForm.items = itemsToDispose;

  disposeForm.post('/app/inventory/expired/process', {
    onSuccess: () => {
      disposeDialogOpen.value = false;
      selectedItems.value = [];
      fetchExpired();
    },
  });
};

const formatDate = (dateString: string) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString();
};

const isExpired = (dateString: string) => {
  if (!dateString) return false;
  return new Date(dateString) < new Date();
};
</script>

<template>

  <Head title="Expired Inventory" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-bold tracking-tight">Expired Stock Management</h2>
          <p class="text-muted-foreground">Identify and write-off expired or expiring inventory.</p>
        </div>
        <div class="flex items-center gap-2">
          <div class="flex items-center gap-2">
            <Label>Threshold (Days)</Label>
            <Input v-model="daysThreshold" type="number" class="w-20" />
          </div>
          <Button variant="destructive" :disabled="selectedItems.length === 0" @click="openDisposeDialog">
            Dispose Selected ({{ selectedItems.length }})
          </Button>
        </div>
      </div>

      <div class="rounded-xl border bg-card text-card-foreground shadow">
        <div class="p-0">
          <div class="relative w-full overflow-auto">
            <div v-if="isLoading" class="p-8 text-center text-muted-foreground animate-pulse">
              Loading data...
            </div>
            <table v-else class="w-full caption-bottom text-sm">
              <thead class="[&_tr]:border-b">
                <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                  <th class="h-12 w-[50px] px-4 align-middle">
                    <Checkbox :checked="selectedItems.length > 0 && selectedItems.length === inventory.data.length"
                      @update:checked="toggleAll" />
                  </th>
                  <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Drug Name</th>
                  <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Batch / Lot</th>
                  <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Expiry Date</th>
                  <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">Qty on Hand</th>
                  <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">Cost Value</th>
                </tr>
              </thead>
              <tbody class="[&_tr:last-child]:border-0">
                <tr v-for="item in inventory?.data" :key="item.inventory_id"
                  class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                  <td class="p-4 align-middle">
                    <Checkbox :checked="selectedItems.includes(item.batch_id)"
                      @update:checked="() => toggleSelection(item.batch_id)" />
                  </td>
                  <td class="p-4 align-middle font-medium">
                    {{ item.drug_name }}
                    <div class="text-xs text-muted-foreground">{{ item.strength }}</div>
                  </td>
                  <td class="p-4 align-middle">{{ item.lot_number || item.batch_id.substring(0, 8) }}</td>
                  <td class="p-4 align-middle">
                    <span :class="{ 'text-red-600 font-bold': isExpired(item.expiry_date) }">
                      {{ formatDate(item.expiry_date) }}
                    </span>
                  </td>
                  <td class="p-4 align-middle text-right">{{ item.quantity_on_hand }}</td>
                  <td class="p-4 align-middle text-right font-mono">
                    {{ (Number(item.cost_price) * Number(item.quantity_on_hand)).toFixed(2) }}
                  </td>
                </tr>
                <tr v-if="!inventory?.data || inventory.data.length === 0">
                  <td colspan="6" class="p-4 text-center text-muted-foreground">
                    No expired stock found within threshold.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-end p-4 gap-2" v-if="inventory?.links && inventory.links.length > 3">
          <template v-for="(link, key) in inventory.links" :key="key">
            <Button v-if="link.url" variant="outline" size="sm" :disabled="link.active || isLoading"
              @click.prevent="fetchExpired(link.url)">
              <span v-html="link.label"></span>
            </Button>
            <span v-else v-html="link.label" class="px-2 text-muted-foreground"></span>
          </template>
        </div>
      </div>
    </div>

    <!-- Dispose Dialog -->
    <Dialog :open="disposeDialogOpen" @update:open="disposeDialogOpen = $event">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Dispose Expired Stock</DialogTitle>
          <DialogDescription>
            You are about to write-off {{ selectedItems.length }} batches. This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <div class="py-4">
          <p>Are you sure you want to proceed?</p>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="disposeDialogOpen = false">Cancel</Button>
          <Button variant="destructive" @click="submitDispose" :disabled="disposeForm.processing">
            Confirm Disposal
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

  </AppLayout>
</template>
