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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps<{
  sale: any;
  open: boolean;
}>();

const emit = defineEmits(['update:open', 'success']);

const form = useForm({
  sale_id: '',
  refund_amount: 0,
  refund_method: 'cash',
  reason: '',
  other_reason: '',
  items: [] as any[],
});

const localItems = ref<any[]>([]);

// Initialize state when modal opens
// Initialize state when modal opens or sale data loads
watch(
  [() => props.open, () => props.sale],
  (newValues, oldValues) => {
    const [isOpen, newSale] = newValues;
    const [oldOpen, oldSale] = oldValues || [];

    if (isOpen && newSale) {
      // Initialize if just opened OR if sale data changed (e.g. loaded async)
      if (!oldOpen || newSale.id !== oldSale?.id) {
        form.sale_id = newSale.id;
        form.refund_method = newSale.payment_type || 'cash';
        form.reason = '';
        form.other_reason = '';

        // Initialize local items with UI state merged
        localItems.value = newSale.items.map((item: any) => {
          const returned = Number(item.return_quantity || 0);
          const available = Number(item.quantity) - returned;

          return {
            ...item,
            ui_checked: false,                    // Selection state
            ui_quantity: available > 0 ? available : 0,   // Default to max available
            ui_restock: true,                     // Restock checkbox
            ui_condition: 'Good',                  // Condition select
            available_quantity: available,
            returned_quantity: returned
          };
        }).filter(item => item.available_quantity > 0); // Only show returnable items
      }
    }
  },
  { immediate: true }
);

const calculateRefund = computed(() => {
  let total = 0;
  localItems.value.forEach((item) => {
    if (item.ui_checked) {
      const price = Number(item.price || 0);
      const quantity = Number(item.ui_quantity || 0);
      total += price * quantity;
    }
  });
  return total;
});

const submit = () => {
  // Combine reason if "Other"
  if (form.reason === 'Other' && form.other_reason) {
    form.reason = `Other: ${form.other_reason}`;
  }

  form.items = localItems.value
    .filter((item) => item.ui_checked)
    .map((item) => ({
      sale_item_id: item.id,
      quantity: item.ui_quantity,
      restock: item.ui_restock,
      condition: item.ui_condition
    }));

  form.refund_amount = calculateRefund.value;

  form.post('/app/returns', {
    onSuccess: () => {
      emit('update:open', false);
      emit('success');
      form.reset();
    }
  });
};
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="sm:max-w-[700px]">
      <DialogHeader>
        <DialogTitle>Process Return</DialogTitle>
        <DialogDescription>
          Select items to return from Sale #{{ sale?.id?.substring(0, 8) }}
        </DialogDescription>
      </DialogHeader>

      <div v-if="sale" class="py-4">
        <div class="rounded-md border">
          <table class="w-full text-sm">
            <thead class="bg-muted/50 border-b">
              <tr>
                <th class="h-10 px-4 text-left font-medium">Select</th>
                <th class="h-10 px-4 text-left font-medium">Item</th>
                <th class="h-10 px-4 text-left font-medium">Sold</th>
                <th class="h-10 px-4 text-left font-medium w-[100px]">Return Qty</th>
                <th class="h-10 px-4 text-left font-medium">Details</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="localItems.length === 0">
                <td colspan="5" class="text-center p-4 text-muted-foreground">No items available to return</td>
              </tr>
              <tr v-for="(item, index) in localItems" :key="item.id" class="border-b last:border-0 hover:bg-muted/50">
                <td class="p-4 align-top">
                  <input type="checkbox" v-model="localItems[index].ui_checked"
                    class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" />
                </td>
                <td class="p-4 align-top">
                  <div class="font-medium">{{ item.drug?.name || 'Unknown Item' }}</div>
                  <div class="text-xs text-muted-foreground">{{ item.price }} / unit</div>
                  <div v-if="item.returned_quantity > 0" class="text-xs text-red-500">
                    Already Returned: {{ item.returned_quantity }}
                  </div>
                </td>
                <td class="p-4 align-top text-center">
                  {{ item.quantity }}
                  <span v-if="item.returned_quantity > 0" class="block text-xs text-muted-foreground">
                    (Avail: {{ item.available_quantity }})
                  </span>
                </td>
                <td class="p-4 align-top">
                  <Input type="number" v-model.number="localItems[index].ui_quantity" :max="item.available_quantity"
                    min="1" class="h-8 w-16" />
                </td>
                <td class="p-4 align-top space-y-2">
                  <div class="flex items-center gap-2">
                    <input type="checkbox" :id="`restock-${item.id}`" v-model="localItems[index].ui_restock"
                      class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" />
                    <Label :for="`restock-${item.id}`" class="text-xs">Restock Inventory?</Label>
                  </div>
                  <Select v-model="localItems[index].ui_condition">
                    <SelectTrigger class="h-8 w-24 text-xs">
                      <SelectValue placeholder="Condition" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="Good">Good</SelectItem>
                      <SelectItem value="Damaged">Damaged</SelectItem>
                      <SelectItem value="Expired">Expired</SelectItem>
                    </SelectContent>
                  </Select>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="grid grid-cols-2 gap-4 mt-6">
          <div class="space-y-2">
            <Label>Reason for Return</Label>
            <Select v-model="form.reason">
              <SelectTrigger>
                <SelectValue placeholder="Select Reason" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Defective">Defective</SelectItem>
                <SelectItem value="Expired">Expired</SelectItem>
                <SelectItem value="Wrong Item">Wrong Item</SelectItem>
                <SelectItem value="Customer Preference">Customer Preference</SelectItem>
                <SelectItem value="Other">Other</SelectItem>
              </SelectContent>
            </Select>
            <Input v-if="form.reason === 'Other'" v-model="form.other_reason" placeholder="Specify other reason..."
              class="mt-2" />
          </div>
          <div class="space-y-2">
            <Label>Refund Method</Label>
            <Select v-model="form.refund_method">
              <SelectTrigger>
                <SelectValue placeholder="Select Method" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="cash">Cash</SelectItem>
                <SelectItem value="card">Card</SelectItem>
                <SelectItem value="store_credit">Store Credit</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <div class="mt-4 p-4 bg-muted rounded-lg flex justify-between items-center">
          <span class="font-medium">Total Refund Amount:</span>
          <span class="text-xl font-bold">{{ calculateRefund.toFixed(2) }}</span>
        </div>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="$emit('update:open', false)">Cancel</Button>
        <Button @click="submit" :disabled="form.processing || calculateRefund <= 0">Process Return</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
