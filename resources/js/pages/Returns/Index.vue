<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, onMounted, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Search, RotateCcw, Eye, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import ReturnDetailsModal from './ReturnDetailsModal.vue';
import { debounce } from 'lodash';

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Returns Management', href: '/app/returns' },
];

const returns = ref({
  data: [],
  links: [],
  meta: {},
  current_page: 1,
  last_page: 1
});
const isLoading = ref(true);
const search = ref('');
const currentPage = ref(1);

// Modal state
const selectedReturn = ref(null);
const detailsModalOpen = ref(false);

const fetchReturns = (page = 1) => {
  isLoading.value = true;
  currentPage.value = page;

  import('axios').then(({ default: axios }) => {
    axios.get('/app/returns', {
      params: {
        page: page,
        search: search.value,
        per_page: 15
      },
      headers: {
        'Accept': 'application/json'
      }
    })
      .then(response => {
        returns.value = response.data;
      })
      .catch(error => {
        console.error("Failed to fetch returns", error);
      })
      .finally(() => {
        isLoading.value = false;
      });
  });
};

// Debounce search
watch(search, debounce(() => {
  fetchReturns(1); // Reset to page 1 on search
}, 300));

onMounted(() => {
  fetchReturns();
});

const openDetails = (ret: any) => {
  selectedReturn.value = ret;
  detailsModalOpen.value = true;
};

const handleRestockSuccess = () => {
  fetchReturns(currentPage.value);
};

const formatDate = (dateString: string) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const formatCurrency = (val: string | number) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'GHS' }).format(Number(val));
};
</script>

<template>

  <Head title="Returns Management" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-6 space-y-6 max-w-7xl mx-auto w-full">
      <!-- Header -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">Returns Management</h1>
          <p class="text-muted-foreground mt-1">
            Review processed returns, track restocked items, and audit activities.
          </p>
        </div>
        <div class="flex gap-2">
          <Button variant="outline" @click="fetchReturns(currentPage)">
            <RotateCcw class="h-4 w-4 mr-2" />
            Refresh
          </Button>
        </div>
      </div>

      <!-- Main Content -->
      <Card>
        <CardHeader class="pb-3">
          <div class="flex justify-between items-center">
            <CardTitle>Returned Orders</CardTitle>
            <div class="relative w-64">
              <Search class="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input v-model="search" placeholder="Search returns..." class="pl-8" />
            </div>
          </div>
          <CardDescription>
            A comprehensive list of all product returns processed in the system.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead class="w-[100px]">Return ID</TableHead>
                  <TableHead>Sale Reference</TableHead>
                  <TableHead>Processed By</TableHead>
                  <TableHead>Date</TableHead>
                  <TableHead>Reason</TableHead>
                  <TableHead class="text-right">Amount</TableHead>
                  <TableHead class="text-center">Items</TableHead>
                  <TableHead class="text-right">Action</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-if="isLoading">
                  <TableCell colspan="8" class="text-center h-24">
                    Loading returns...
                  </TableCell>
                </TableRow>
                <TableRow v-else-if="!returns.data || returns.data.length === 0">
                  <TableCell colspan="8" class="text-center h-24 text-muted-foreground">
                    No returns found matching your criteria.
                  </TableCell>
                </TableRow>
                <TableRow v-for="ret in returns.data" :key="ret.id" class="cursor-pointer hover:bg-muted/50"
                  @click="openDetails(ret)">
                  <TableCell class="font-mono text-xs">{{ ret.id.substring(0, 8) }}</TableCell>
                  <TableCell class="font-mono text-xs text-muted-foreground">#{{ ret.sale_id.substring(0, 8) }}
                  </TableCell>
                  <TableCell>
                    <div class="flex items-center gap-2">
                      <span class="font-medium text-sm">{{ ret.user?.name || 'Unknown' }}</span>
                    </div>
                  </TableCell>
                  <TableCell class="text-xs">{{ formatDate(ret.returned_at) }}</TableCell>
                  <TableCell>
                    <Badge variant="outline" class="truncate max-w-[150px] inline-block">
                      {{ ret.reason }}
                    </Badge>
                  </TableCell>
                  <TableCell class="text-right font-medium">
                    {{ formatCurrency(ret.refund_amount) }}
                  </TableCell>
                  <TableCell class="text-center">
                    <div class="flex flex-col items-center gap-1">
                      <Badge variant="secondary">{{ ret.items.length }} Items</Badge>
                      <span class="text-[10px] text-muted-foreground">
                        Qty: {{ret.items.reduce((sum: number, item: any) => sum + item.quantity, 0)}}
                      </span>
                    </div>
                  </TableCell>
                  <TableCell class="text-right">
                    <Button variant="ghost" size="sm" @click.stop="openDetails(ret)">
                      <Eye class="h-4 w-4 text-muted-foreground hover:text-primary" />
                    </Button>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>

          <!-- Pagination -->
          <div class="flex justify-end mt-4 items-center gap-2" v-if="returns.last_page > 1">
            <Button variant="outline" size="sm" :disabled="returns.current_page === 1"
              @click="fetchReturns(returns.current_page - 1)">
              <ChevronLeft class="h-4 w-4 mr-2" />
              Previous
            </Button>

            <div class="text-sm text-muted-foreground">
              Page {{ returns.current_page }} of {{ returns.last_page }}
            </div>

            <Button variant="outline" size="sm" :disabled="returns.current_page === returns.last_page"
              @click="fetchReturns(returns.current_page + 1)">
              Next
              <ChevronRight class="h-4 w-4 ml-2" />
            </Button>
          </div>

        </CardContent>
      </Card>
    </div>

    <ReturnDetailsModal v-if="selectedReturn" :return-item="selectedReturn" :open="detailsModalOpen"
      @update:open="detailsModalOpen = $event" @restock-success="handleRestockSuccess" />
  </AppLayout>
</template>
