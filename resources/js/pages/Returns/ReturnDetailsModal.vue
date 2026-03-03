<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { ref } from 'vue';

const props = defineProps<{
  returnItem: any;
  open: boolean;
}>();

const emit = defineEmits(['update:open', 'restock-success']);

const isRestocking = ref<string | null>(null);

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

const restockItem = (item: any) => {
  if (!confirm('Are you sure you want to restock this item back to inventory?')) return;

  isRestocking.value = item.id;

  import('axios').then(({ default: axios }) => {
    axios.post(`/app/returns/${item.id}/restock`)
      .then(() => {
        // Update local state immediately for better UX
        item.is_restocked = true;
        emit('restock-success');
      })
      .catch(error => {
        console.error("Failed to restock item", error);
      })
      .finally(() => {
        isRestocking.value = null;
      });
  });
};
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="sm:max-w-[800px]">
      <DialogHeader>
        <DialogTitle>Return Details</DialogTitle>
        <DialogDescription>
          View details for Return #{{ returnItem?.id?.substring(0, 8) }}
        </DialogDescription>
      </DialogHeader>

      <div v-if="returnItem" class="space-y-6 py-4">
        <!-- Header Info -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 p-4 bg-muted/50 rounded-lg">
          <div>
            <div class="text-xs text-muted-foreground">Returned By</div>
            <div class="font-medium flex items-center gap-2">
              {{ returnItem.user?.name || 'Unknown' }}
            </div>
          </div>
          <div>
            <div class="text-xs text-muted-foreground">Sale ID</div>
            <div class="font-medium font-mono text-xs">
              #{{ returnItem.sale_id?.substring(0, 8) }}
            </div>
          </div>
          <div>
            <div class="text-xs text-muted-foreground">Date</div>
            <div class="font-medium text-xs">
              {{ formatDate(returnItem.returned_at) }}
            </div>
          </div>
          <div>
            <div class="text-xs text-muted-foreground">Total Refund</div>
            <div class="font-medium text-green-600">
              {{ formatCurrency(returnItem.refund_amount) }}
            </div>
          </div>
          <div>
            <div class="text-xs text-muted-foreground">Reason</div>
            <div class="font-medium">{{ returnItem.reason }}</div>
          </div>
          <div>
            <div class="text-xs text-muted-foreground">Method</div>
            <div class="font-medium capitalize">{{ returnItem.refund_method.replace('_', ' ') }}</div>
          </div>
        </div>

        <!-- Items Table -->
        <div>
          <h3 class="font-medium mb-3">Returned Items</h3>
          <div class="w-full overflow-hidden rounded-lg border">
            <table class="w-full text-sm">
              <thead class="bg-muted/50 text-muted-foreground">
                <tr class="border-b">
                  <th class="h-10 px-4 text-left font-medium">Item</th>
                  <th class="h-10 px-4 text-center font-medium">Qty</th>
                  <th class="h-10 px-4 text-right font-medium">Refund Amt</th>
                  <th class="h-10 px-4 text-left font-medium">Condition</th>
                  <th class="h-10 px-4 text-center font-medium">Status</th>
                  <th class="h-10 px-4 text-right font-medium">Action</th>
                </tr>
              </thead>
              <tbody class="bg-background">
                <tr v-for="item in returnItem.items" :key="item.id" class="border-b last:border-0 hover:bg-muted/30">
                  <td class="p-4 align-middle font-medium">
                    {{ item.drug_name }}
                  </td>
                  <td class="p-4 align-middle text-center">
                    {{ item.quantity }}
                  </td>
                  <td class="p-4 align-middle text-right">
                    {{ formatCurrency(item.refund_amount) }}
                  </td>
                  <td class="p-4 align-middle">
                    <Badge variant="outline" :class="{
                      'bg-green-50 text-green-700 border-green-200': item.condition === 'Good',
                      'bg-red-50 text-red-700 border-red-200': item.condition === 'Damaged' || item.condition === 'Expired'
                    }">
                      {{ item.condition }}
                    </Badge>
                  </td>
                  <td class="p-4 align-middle text-center">
                    <Badge v-if="item.is_restocked"
                      class="bg-emerald-100 text-emerald-800 border-emerald-200 hover:bg-emerald-100">
                      Restocked
                    </Badge>
                    <Badge v-else variant="secondary" class="text-muted-foreground bg-gray-100">
                      Not Restocked
                    </Badge>
                  </td>
                  <td class="p-4 align-middle text-right">
                    <Button v-if="!item.is_restocked && $can('returns.restock')" size="sm" variant="outline" class="h-8 gap-1"
                      :disabled="isRestocking === item.id" @click="restockItem(item)">
                      <span v-if="isRestocking === item.id" class="loader mr-1"></span>
                      Restock
                    </Button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <DialogFooter>
        <Button @click="$emit('update:open', false)">Close</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
