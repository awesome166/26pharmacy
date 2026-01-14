<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { ref, computed, watch, onMounted } from 'vue';
import { debounce } from 'lodash';
import { Plus, Trash2, CreditCard, Banknote } from 'lucide-vue-next';
import DosageSelector from './Partials/DosageSelector.vue';

import type { BreadcrumbItem } from '@/types';

const props = defineProps({
  inventory: Object,
  filters: Object,
  user: Object,
  branch_id: String,
});

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Store (POS)', href: '/app/store' },
];

// --- Left Panel: Product List logic ---
const search = ref(props.filters?.search || '');
const scannerInput = ref(''); // separate input for scanner focus if needed? or shared
const scannerRef = ref(null);

watch(search, debounce((value) => {
  router.get('/app/store', { search: value }, {
    preserveState: true,
    replace: true,
    preserveScroll: true
  });
}, 300));

// --- Cart Logic ---
const cart = ref<any[]>([]);
const selectedProduct = ref<any>(null);
const addToCartOpen = ref(false);
const qtyForm = ref({
  quantity: 1,
  dosage_instructions: {
    frequency: '',
    full_frequency: '',
    route: 'oral',
    measurement: '',
    special: [],
    structured: { type: '', description: '' },
    duration: ''
  }
});

const openAddToCart = (product) => {
  selectedProduct.value = product;
  qtyForm.value = {
    quantity: 1,
    dosage_instructions: {
      frequency: '',
      full_frequency: '',
      route: 'oral',
      measurement: '',
      special: [],
      structured: { type: '', description: '' },
      duration: ''
    }
  };
  addToCartOpen.value = true;
};

const addToCart = () => {
  if (!selectedProduct.value) return;

  const existingindex = cart.value.findIndex(item => item.batch_id === selectedProduct.value.batch_id);

  if (existingindex >= 0) {
    cart.value[existingindex].quantity += qtyForm.value.quantity;
    // Update price if dynamic pricing existed, here it's static/mocked
    // Assuming price comes from inventory? Wait, inventory doesn't have price in schema yet?
    // Schema has sales table with amount, but inventory table is just qty.
    // Seed data generates total randomly.
    // Let's Mock Price for now based on drug name length or random hash until schema update
    cart.value[existingindex].total = cart.value[existingindex].quantity * cart.value[existingindex].price;
  } else {
    // Mocking price because Schema doesn't have `price` on `inventory` or `drugs` yet.
    // In real app, price should be in batches or a price list.
    const mockPrice = 10.00;

    cart.value.push({
      drug_name: selectedProduct.value.drug_name,
      batch_id: selectedProduct.value.batch_id,
      drug_id: selectedProduct.value.drug_id,
      quantity: qtyForm.value.quantity,
      price: mockPrice,
      total: mockPrice * qtyForm.value.quantity,
      dosage_instructions: qtyForm.value.dosage_instructions
    });
  }
  addToCartOpen.value = false;
};

const removeFromCart = (index) => {
  cart.value.splice(index, 1);
};

// --- Invoicing Logic ---
const subtotal = computed(() => {
  return cart.value.reduce((acc, item) => acc + item.total, 0);
});

const taxRate = 0.08; // 8% Mock
const taxAmount = computed(() => subtotal.value * taxRate);
const totalAmount = computed(() => subtotal.value + taxAmount.value);

const paymentType = ref('cash');

const finalizeForm = useForm({
  tenant_id: props.user?.tenant_id, // Fallback if user doesn't have it on model but should
  branch_id: props.branch_id,
  device_id: 'browser-device', // Mock
  user_id: props.user?.id,
  subtotal: 0,
  tax_amount: 0,
  total_amount: 0, // Request expects subtotal but logic might want total
  jurisdiction: 'default',
  payment_type: 'cash',
  items: []
});

const processing = ref(false);

const finalizeSale = () => {
  processing.value = true;

  // Prepare payload matching FinalizeSaleRequest
  const payload = {
    tenant_id: props.user.tenant_id || '00000000-0000-0000-0000-000000000000',
    branch_id: props.branch_id,
    device_id: 'browser-device',
    user_id: props.user.user_id || props.user.id,
    subtotal: totalAmount.value,
    tax_amount: taxAmount.value,
    payment_type: paymentType.value,
    items: cart.value.map(item => ({
      batch_id: item.batch_id,
      quantity: item.quantity,
      price: item.price,
      dosage_instructions: item.dosage_instructions
    }))
  };

  import('axios').then(({ default: axios }) => {
    axios.post('/app/sales', payload, {
      headers: { 'Accept': 'application/json' }
    })
      .then(() => {
        cart.value = [];
      })
      .catch((error) => {
        // Optionally handle error
        // e.g., show notification
      })
      .finally(() => {
        processing.value = false;
      });
  });
};

const formatCurrency = (val) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'GHS' }).format(val);
</script>

<template>

  <Head title="Point of Sale" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-[calc(100vh-6rem)] w-full gap-4 p-4 overflow-hidden">

      <!-- LEFT PANEL: CATALOG (7/12) -->
      <div class="flex flex-col w-7/12 gap-4 h-full">
        <!-- Search & Scanner Bar -->
        <div class="flex gap-2">
          <Input v-model="search" placeholder="Search items or scan barcode..." class="flex-1 h-12 text-lg"
            ref="scannerRef" autofocus />
        </div>

        <!-- Product Grid/List -->
        <div class="flex-1 rounded-xl border bg-card text-card-foreground shadow overflow-hidden flex flex-col">
          <div class="overflow-y-auto flex-1 p-0">
            <table class="w-full caption-bottom text-sm">
              <thead class="sticky top-0 bg-muted/90 backdrop-blur z-10 [&_tr]:border-b">
                <tr class="border-b transition-colors">
                  <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Product</th>
                  <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Batch / Expiry</th>
                  <th class="h-10 px-4 text-right align-middle font-medium text-muted-foreground">Stock</th>
                  <th class="h-10 px-4 text-right align-middle font-medium text-muted-foreground">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in inventory.data" :key="item.inventory_id"
                  class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted cursor-pointer"
                  @click="openAddToCart(item)">
                  <td class="p-4 align-middle font-medium">
                    <div class="font-bold">{{ item.drug_name }}</div>
                    <div class="text-xs text-muted-foreground">{{ item.strength }}</div>
                  </td>
                  <td class="p-4 align-middle">
                    <div class="font-mono text-xs">{{ item.batch_id.substring(0, 8) }}</div>
                    <div class="text-xs">{{ item.expiry_date }}</div>
                  </td>
                  <td class="p-4 align-middle text-right text-lg font-semibold">
                    {{ item.quantity_on_hand }}
                  </td>
                  <td class="p-4 align-middle text-right">
                    <Button size="icon" variant="ghost">
                      <Plus class="h-4 w-4" />
                    </Button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Paginator -->
          <div class="p-2 border-t flex justify-end gap-2">
            <template v-for="(link, key) in inventory.links" :key="key">
              <Button v-if="link.url" variant="outline" size="sm" as-child :disabled="link.active">
                <a :href="link.url" v-html="link.label"></a>
              </Button>
              <span v-else v-html="link.label" class="px-2 text-muted-foreground flex items-center"></span>
            </template>
          </div>
        </div>
      </div>

      <!-- RIGHT PANEL: INVOICE (5/12) -->
      <div class="flex flex-col w-5/12 gap-4 h-full">
        <div class="flex-1 rounded-xl border bg-card text-card-foreground shadow flex flex-col h-full">
          <div class="p-4 border-b bg-muted/20 flex justify-between items-center">
            <div>
              <h2 class="text-xl font-bold tracking-tight">Current Sale</h2>
              <p class="text-xs text-muted-foreground">Invoice Preview</p>
            </div>
            <Button v-if="cart.length > 0" variant="destructive" size="sm" @click="cart = []">
              Clear Sale
            </Button>
          </div>

          <!-- Cart Items -->
          <div class="flex-1 overflow-y-auto p-4 space-y-2">
            <div v-if="cart.length === 0" class="text-center text-muted-foreground py-10">
              Cart is empty. Select items to start.
            </div>
            <div v-for="(item, index) in cart" :key="index"
              class="flex justify-between items-start p-3 border rounded-lg bg-background shadow-sm">
              <div class="flex-1">
                <div class="font-bold">{{ item.drug_name }}</div>
                <div class="text-xs text-muted-foreground" v-if="item.dosage_instructions?.full_frequency">
                  {{ item.dosage_instructions.measurement }} {{ item.dosage_instructions.full_frequency }}
                  <span v-if="item.dosage_instructions.special.length">({{ item.dosage_instructions.special.join(', ')
                  }})</span>
                </div>
                <div class="text-sm mt-1">
                  {{ item.quantity }} x {{ formatCurrency(item.price) }}
                </div>
              </div>
              <div class="flex flex-col items-end gap-2">
                <div class="font-bold border-b border-dashed">
                  {{ formatCurrency(item.total) }}
                </div>
                <Button variant="ghost" size="icon" class="h-6 w-6 text-destructive" @click="removeFromCart(index)">
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
            </div>
          </div>

          <!-- Totals & Actions -->
          <div class="p-6 border-t bg-muted/20">
            <div class="space-y-2 mb-4">
              <div class="flex justify-between text-sm">
                <span class="text-muted-foreground">Subtotal</span>
                <span>{{ formatCurrency(subtotal) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-muted-foreground">Tax (8%)</span>
                <span>{{ formatCurrency(taxAmount) }}</span>
              </div>
              <div class="flex justify-between text-2xl font-bold pt-2 border-t">
                <span>Total</span>
                <span>{{ formatCurrency(totalAmount) }}</span>
              </div>
            </div>

            <div class="grid grid-cols-3 gap-2 mb-4">
              <Button variant="outline" :class="{ 'border-primary bg-primary/10': paymentType === 'cash' }"
                @click="paymentType = 'cash'">
                <Banknote class="mr-2 h-4 w-4" /> Cash
              </Button>

              <!-- <Button variant="outline" :class="{ 'border-primary bg-primary/10': paymentType === 'momo' }"
                @click="paymentType = 'momo'">
                <CreditCard class="mr-2 h-4 w-4" /> Momo
              </Button>
              <Button variant="outline" :class="{ 'border-primary bg-primary/10': paymentType === 'card' }"
                @click="paymentType = 'card'">
                <CreditCard class="mr-2 h-4 w-4" /> Card
              </Button> -->
            </div>

            <Button class="w-full h-12 text-lg" :disabled="cart.length === 0 || processing" @click="finalizeSale">
              {{ processing ? 'Processing...' : 'Finalize Sale' }}
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add to Cart Modal -->
    <Dialog :open="addToCartOpen" @update:open="addToCartOpen = $event">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Add to Cart</DialogTitle>
          <DialogDescription>
            {{ selectedProduct?.drug_name }} ({{ selectedProduct?.strength }})
          </DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class=" items-center gap-4">
            <Label class=" pb-2 text-right">Quantity</Label>
            <Input type="number" v-model="qtyForm.quantity" class="col-span-3" min="1" />
          </div>
          <div class=" items-start gap-4">
            <Label class=" pb-2 text-right pt-2">Dosage (optional)</Label>
            <div class="col-span-3">
              <DosageSelector v-model="qtyForm.dosage_instructions" />
            </div>
          </div>
          <div class="text-center text-sm font-bold mt-2">
            Estimated: {{ formatCurrency((10.00 * qtyForm.quantity)) }} <!-- Mock Calculation Display -->
          </div>
        </div>
        <DialogFooter>
          <Button type="submit" @click="addToCart">Add to Cart</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

  </AppLayout>
</template>
