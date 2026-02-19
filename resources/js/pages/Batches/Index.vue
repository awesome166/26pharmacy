<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import axios from 'axios';
import CreateBatchDialog from '@/pages/Inventory/CreateBatchDialog.vue';
import AddInventoryDialog from '@/pages/Inventory/AddInventoryDialog.vue';
import { Edit, Trash2, ArrowRight, MoreHorizontal, Search, ChevronLeft, ChevronRight, Plus } from 'lucide-vue-next';

const page = usePage();
const batchMode = computed(() => page.props.auth.settings?.settings?.inventory_batch_mode || false);

const batches = ref({ data: [], links: [], meta: {}, current_page: 1, last_page: 1 });
const loading = ref(false);
const search = ref('');
const currentPage = ref(1);

const createBatchDialog = ref<any>(null);
const addInventoryDialog = ref<any>(null);

// Delete Modal State
const deleteDialogOpen = ref(false);
const batchToDelete = ref<any>(null);
const isDeleting = ref(false);

const fetchBatches = async (page = 1) => {
  loading.value = true;
  currentPage.value = page;
  try {
    const res = await axios.get('/app/batches', {
      params: {
        page: page,
        search: search.value,
        per_page: 15
      }
    });
    batches.value = res.data.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

// Debounce search
watch(search, debounce(() => {
  fetchBatches(1);
}, 300));

const handleCreate = () => {
  createBatchDialog.value?.openDialog();
};

const handleEdit = (batch: any) => {
  createBatchDialog.value?.openDialog(batch.drug_id, batch);
};

const openDeleteConfirm = (batch: any) => {
  batchToDelete.value = batch;
  deleteDialogOpen.value = true;
};

const confirmDelete = async () => {
  if (!batchToDelete.value) return;
  isDeleting.value = true;

  try {
    await axios.delete(`/app/batches/${batchToDelete.value.id}`);
    (window as any).addToast?.({ title: 'Success', message: 'Batch deleted successfully.', type: 'success' });
    fetchBatches(currentPage.value);
    deleteDialogOpen.value = false;
  } catch (e) {
    console.error(e);
    (window as any).addToast?.({ title: 'Error', message: 'Failed to delete batch.', type: 'error' });
  } finally {
    isDeleting.value = false;
    batchToDelete.value = null;
  }
};

const handleIssueToInventory = (batch: any) => {
  addInventoryDialog.value?.openDialog(batch);
};

const formatDate = (dateString: string) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

onMounted(() => {
  fetchBatches();
});
</script>

<template>

  <Head title="Batches" />

  <AppLayout>
    <div class="p-6 space-y-6   w-full">
      <!-- Header -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">Batches Management</h1>
          <p class="text-muted-foreground mt-1">
            Manage product batches, expiration dates, and inventory issuance.
          </p>
        </div>
        <Button @click="handleCreate">
          <Plus class="h-4 w-4 mr-2" />
          Create Batch
        </Button>
      </div>

      <Card>
        <CardHeader class="pb-3">
          <div class="flex justify-between items-center">
            <CardTitle>All Batches</CardTitle>
            <div class="relative w-64">
              <Search class="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input v-model="search" placeholder="Search batches..." class="pl-8" />
            </div>
          </div>
          <CardDescription>
            View all received batches. {{ batchMode ? 'Use "Issue to Inventory" to add stock.' : '' }}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Lot Number</TableHead>
                  <TableHead>Drug</TableHead>
                  <TableHead>Expiry</TableHead>
                  <TableHead>Qty</TableHead>
                  <TableHead class="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-if="loading">
                  <TableCell colspan="5" class="text-center h-24">Loading batches...</TableCell>
                </TableRow>
                <TableRow v-else-if="!batches.data || batches.data.length === 0">
                  <TableCell colspan="5" class="text-center h-24 text-muted-foreground">No batches found.</TableCell>
                </TableRow>
                <TableRow v-for="batch in batches.data" :key="batch.id">
                  <TableCell class="font-mono font-medium">{{ batch.lot_number }}</TableCell>
                  <TableCell>
                    <div class="flex flex-col">
                      <span class="font-medium">{{ batch.drug?.name }}</span>
                      <span class="text-xs text-muted-foreground">{{ batch.manufacturer }}</span>
                    </div>
                  </TableCell>
                  <TableCell>
                    <div class="flex items-center gap-2">
                      {{ formatDate(batch.expiry_date) }}
                      <Badge variant="outline" v-if="new Date(batch.expiry_date) < new Date()"
                        class="text-red-500 border-red-200 bg-red-50">Expired</Badge>
                    </div>
                  </TableCell>
                  <TableCell>{{ batch.quantity }}</TableCell>
                  <TableCell class="text-right">
                    <DropdownMenu>
                      <DropdownMenuTrigger as-child>
                        <Button variant="ghost" class="h-8 w-8 p-0">
                          <span class="sr-only">Open menu</span>
                          <MoreHorizontal class="h-4 w-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <!-- <DropdownMenuLabel>Actions</DropdownMenuLabel> -->
                        <DropdownMenuItem @click="handleEdit(batch)">
                          <Edit class="mr-2 h-4 w-4" />
                          Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="batchMode" @click="handleIssueToInventory(batch)">
                          <ArrowRight class="mr-2 h-4 w-4" />
                          Issue to Inventory
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="openDeleteConfirm(batch)" class="text-red-600 focus:text-red-600">
                          <Trash2 class="mr-2 h-4 w-4" />
                          Delete
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>

          <!-- Pagination -->
          <div class="flex justify-end mt-4 items-center gap-2" v-if="batches.last_page > 1">
            <Button variant="outline" size="sm" :disabled="batches.current_page === 1"
              @click="fetchBatches(batches.current_page - 1)">
              <ChevronLeft class="h-4 w-4 mr-2" />
              Previous
            </Button>

            <div class="text-sm text-muted-foreground">
              Page {{ batches.current_page }} of {{ batches.last_page }}
            </div>

            <Button variant="outline" size="sm" :disabled="batches.current_page === batches.last_page"
              @click="fetchBatches(batches.current_page + 1)">
              Next
              <ChevronRight class="h-4 w-4 ml-2" />
            </Button>
          </div>
        </CardContent>
      </Card>

      <CreateBatchDialog ref="createBatchDialog" @success="fetchBatches(currentPage)" />
      <AddInventoryDialog ref="addInventoryDialog" />

      <!-- Delete Confirmation Modal -->
      <Dialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delete Batch</DialogTitle>
            <DialogDescription>
              Are you sure you want to delete only batch <strong>{{ batchToDelete?.lot_number }}</strong>?
              This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" @click="deleteDialogOpen = false" :disabled="isDeleting">Cancel</Button>
            <Button variant="destructive" @click="confirmDelete" :disabled="isDeleting">
              {{ isDeleting ? 'Deleting...' : 'Delete' }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

    </div>
  </AppLayout>
</template>
