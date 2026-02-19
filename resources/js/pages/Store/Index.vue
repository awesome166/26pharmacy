<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { ref, computed, watch, onMounted } from 'vue';
import { debounce } from 'lodash';
import { Plus, Trash2, CreditCard, Banknote, Printer, User } from 'lucide-vue-next';
import DosageSelector from './Partials/DosageSelector.vue';
import InvoiceReceipt from '@/components/InvoiceReceipt.vue';
import DosageInstructions from '@/components/DosageInstructions.vue';
import ReturnModal from '@/pages/Sales/ReturnModal.vue';

import type { BreadcrumbItem } from '@/types';

const props = defineProps({
  filters: Object,
  user: Object,
});

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Store (POS)', href: '/app/store' },
];

// --- Left Panel: Product List logic ---
const search = ref(props.filters?.search || '');
const scannerInput = ref('');
const scannerRef = ref(null);
const inventory = ref({ data: [], links: [] });
const isLoading = ref(true);

const fetchInventory = (url = '/app/store') => {
  isLoading.value = true;
  import('axios').then(({ default: axios }) => {
    axios.get(url, {
      params: { search: search.value },
      headers: { 'Accept': 'application/json' }
    })
      .then(response => {
        inventory.value = response.data.data ? response.data.data : response.data; // Handle potential wrapping
      })
      .catch(error => {
        console.error("Failed to fetch inventory", error);
      })
      .finally(() => {
        isLoading.value = false;
      });
  });
};

// --- Tax Logic ---
const activeTaxes = ref([]);

const fetchTaxes = () => {
  import('axios').then(({ default: axios }) => {
    axios.get('/app/taxes', {
      params: { active_only: 1, per_page: 100 },
      headers: { 'Accept': 'application/json' }
    }).then(response => {
      activeTaxes.value = response.data.data ? response.data.data : response.data;
    }).catch(err => console.error("Failed to fetch taxes", err));
  });
};

onMounted(() => {
  fetchInventory();
  fetchTaxes();
});

watch(search, debounce((value) => {
  fetchInventory();
}, 150)); // Reduced from 300ms for better responsiveness

// --- Cart Logic ---
const cart = ref<any[]>([]);
const selectedProduct = ref<any>(null);
const addToCartOpen = ref(false);
const editCartOpen = ref(false);
const editingCartIndex = ref<number | null>(null);
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
    cart.value[existingindex].total = cart.value[existingindex].quantity * cart.value[existingindex].price;
  } else {
    // Use selling_price from backend, fallback to 0 if missing.
    const productPrice = selectedProduct.value.selling_price || 0;

    cart.value.push({
      drug_name: selectedProduct.value.drug_name,
      batch_id: selectedProduct.value.batch_id,
      drug_id: selectedProduct.value.drug_id,
      id: selectedProduct.value.id, // Ensure ID is passed for consistency
      quantity: qtyForm.value.quantity,
      price: productPrice,
      total: productPrice * qtyForm.value.quantity,
      dosage_instructions: qtyForm.value.dosage_instructions,
      drug_class: selectedProduct.value.drug_class, // Important for tax calc
      // inventory_id: selectedProduct.value.id
    });
  }
  addToCartOpen.value = false;
};

const removeFromCart = (index) => {
  cart.value.splice(index, 1);
};

const openEditCart = (index) => {
  editingCartIndex.value = index;
  const item = cart.value[index];
  qtyForm.value = {
    quantity: item.quantity,
    dosage_instructions: item.dosage_instructions || {
      frequency: '',
      full_frequency: '',
      route: 'oral',
      measurement: '',
      special: [],
      structured: { type: '', description: '' },
      duration: ''
    }
  };
  editCartOpen.value = true;
};

const updateCartItem = () => {
  if (editingCartIndex.value === null) return;

  const item = cart.value[editingCartIndex.value];
  item.quantity = qtyForm.value.quantity;
  item.dosage_instructions = qtyForm.value.dosage_instructions;
  item.total = item.quantity * item.price;

  editCartOpen.value = false;
  editingCartIndex.value = null;
};

// --- Invoicing Logic ---
const subtotal = computed(() => {
  return cart.value.reduce((acc, item) => acc + item.total, 0);
});

// Dynamic Tax Calculation
const taxDetails = computed(() => {
  let totalTax = 0;
  const taxesApplied = {}; // { 'Tax Name (10%)': amount }

  cart.value.forEach(item => {
    activeTaxes.value.forEach(tax => {
      let applies = false;
      // Case 1: No categories defined OR contains 'all' = Applies to everything
      if (!tax.applicable_categories || tax.applicable_categories.length === 0 || tax.applicable_categories.includes('all')) {
        applies = true;
      }
      // Case 2: Categories defined = Check match
      else if (item.drug_class && tax.applicable_categories.includes(item.drug_class)) {
        applies = true;
      }

      if (applies) {
        const taxForLine = item.total * (tax.percentage / 100);
        totalTax += taxForLine;

        const key = `${tax.tax_name} (${Number(tax.percentage)}%)`;
        taxesApplied[key] = (taxesApplied[key] || 0) + taxForLine;
      }
    });
  });

  return {
    total: totalTax,
    breakdown: taxesApplied
  };
});

const taxAmount = computed(() => taxDetails.value.total);
const totalAmount = computed(() => subtotal.value + taxAmount.value);

const paymentType = ref('cash');
const cashReceived = ref(0);
const changeAmount = computed(() => {
  if (paymentType.value === 'cash' && cashReceived.value > 0) {
    return Math.max(0, cashReceived.value - totalAmount.value);
  }
  return 0;
});

const processing = ref(false);
const showReceipt = ref(false);
const lastSaleData = ref<any>(null);
const showDosageDialog = ref(false);
const dosageSaleData = ref<any>(null);

// Return Logic
const returnModalOpen = ref(false);
const selectedSaleForReturn = ref<any>(null);

const openReturnModal = (sale: any) => {
  // Fetch full sale details first to get items
  import('axios').then(({ default: axios }) => {
    axios.get(`/app/sales/${sale.id}`, {
      headers: { 'Accept': 'application/json' }
    })
      .then(response => {
        selectedSaleForReturn.value = response.data.data || response.data;
        returnModalOpen.value = true;
      })
      .catch(error => {
        console.error("Failed to fetch sale details for return", error);
      });
  });
};

// Customer Information
const showCustomerInfo = ref(false);
const customerInfo = ref({
  name: '',
  phone: '',
  email: '',
  dob: ''
});

// Recent sales for empty cart display
const recentSales = ref<any[]>([]);
const loadingRecentSales = ref(false);

const fetchRecentSales = () => {
  loadingRecentSales.value = true;
  import('axios').then(({ default: axios }) => {
    axios.get('/app/sales', {
      params: { per_page: 10 },
      headers: { 'Accept': 'application/json' }
    })
      .then(response => {
        recentSales.value = response.data.data?.data || response.data.data || [];
      })
      .catch(error => {
        console.error("Failed to fetch recent sales", error);
      })
      .finally(() => {
        loadingRecentSales.value = false;
      });
  });
};

onMounted(() => {
  fetchRecentSales();
});

const finalizeSale = () => {
  // Validate cash payment
  if (paymentType.value === 'cash' && cashReceived.value < totalAmount.value) {
    alert('Cash received is less than the total amount!');
    return;
  }

  processing.value = true;

  // Prepare payload matching FinalizeSaleRequest
  const payload = {
    account_id: props.user?.accounts[0]?.id, // Use safe navigation
    user_id: props.user?.id,
    subtotal: subtotal.value, // Used computed subtotal not totalAmount
    tax_amount: taxAmount.value,
    total_amount: totalAmount.value,
    payment_type: paymentType.value,
    cash_received: paymentType.value === 'cash' ? cashReceived.value : null,
    change_amount: paymentType.value === 'cash' ? changeAmount.value : null,
    // Include customer information if provided
    customer_name: showCustomerInfo.value && customerInfo.value.name ? customerInfo.value.name : null,
    customer_phone: showCustomerInfo.value && customerInfo.value.phone ? customerInfo.value.phone : null,
    customer_email: showCustomerInfo.value && customerInfo.value.email ? customerInfo.value.email : null,
    customer_dob: showCustomerInfo.value && customerInfo.value.dob ? customerInfo.value.dob : null,
    items: cart.value.map(item => ({
      batch_id: item.batch_id,
      quantity: item.quantity,
      price: item.price,
      inventory_id: item.id,
      drug_id: item.drug_id,
      dosage_instructions: item.dosage_instructions
    }))
  };
  // console.log(payload);
  // return;
  import('axios').then(({ default: axios }) => {
    axios.post('/app/sales', payload, {
      headers: { 'Accept': 'application/json' }
    })
      .then((response) => {
        // Store sale data for receipt
        lastSaleData.value = {
          items: [...cart.value],
          subtotal: subtotal.value,
          taxAmount: taxAmount.value,
          taxDetails: { ...taxDetails.value.breakdown }, // Snapshot tax details
          totalAmount: totalAmount.value,
          totalReturnedAmount: 0,
          paymentType: paymentType.value,
          cashReceived: cashReceived.value,
          change: changeAmount.value,
          date: new Date().toLocaleString(),
          saleId: response.data?.id || response.data?.event_id || 'N/A',
          servedBy: props.user?.name || 'Staff'
        };

        // Clear cart and reset
        cart.value = [];
        cashReceived.value = 0;

        // Reset customer info
        customerInfo.value = {
          name: '',
          phone: '',
          email: '',
          dob: ''
        };
        showCustomerInfo.value = false;

        // Show receipt dialog
        showReceipt.value = true;

        // Refresh recent sales and inventory
        fetchRecentSales();
        fetchInventory();
      })
      .catch((error) => {
        console.error("Sale finalized error", error);
      })
      .finally(() => {
        processing.value = false;
      });
  });
};

const viewSaleReceipt = (sale: any) => {
  // Fetch full sale details with items
  import('axios').then(({ default: axios }) => {
    axios.get(`/app/sales/${sale.id}`, {
      headers: { 'Accept': 'application/json' }
    })
      .then(response => {
        const saleData = response.data.data || response.data;
        // Reconstruct tax details if not stored (simplified for now, ideally backend stores snapshot)
        // For historic sales, we might verify stored tax_amount vs assumed.
        // For now, just show total tax as "Tax" if breakdown missing.

        lastSaleData.value = {
          id: saleData.id,
          items: saleData.items || [],
          subtotal: saleData.subtotal_amount,
          taxAmount: saleData.tax_amount,
          taxDetails: { 'Tax': saleData.tax_amount }, // Fallback
          totalAmount: saleData.total_amount,
          totalReturnedAmount: Number(saleData.total_returned_amount ?? 0),
          paymentType: saleData.payment_type,
          cashReceived: saleData.cash_received,
          change: saleData.change_amount,
          date: new Date(saleData.finalized_at || saleData.created_at).toLocaleString(),
        };
        showReceipt.value = true;
      })
      .catch(error => {
        console.error("Failed to fetch sale details", error);
      });
  });
};

const printDosageInstructions = (sale: any) => {
  // Fetch full sale details with items
  import('axios').then(({ default: axios }) => {
    axios.get(`/app/sales/${sale.id}`, {
      headers: { 'Accept': 'application/json' }
    })
      .then(response => {
        dosageSaleData.value = response.data.data || response.data;
        showDosageDialog.value = true;
      })
      .catch(error => {
        console.error("Failed to fetch sale details", error);
      });
  });
};

const printReceipt = () => {
  const printWindow = window.open('', '_blank');
  if (!printWindow) return;

  // Format Tax Rows
  const taxRows = Object.entries(lastSaleData.value?.taxDetails || {}).map(([name, amount]) => `
     <div class="row">
        <span class="tax-name">${name}:</span>
        <span>${formatCurrency(amount)}</span>
    </div>
  `).join('');

  const receiptHtml = `
    <!DOCTYPE html>
    <html>
    <head>
      <title>Receipt - ${lastSaleData.value?.saleId}</title>
      <style>
        body {
          font-family: 'Courier New', monospace;
          max-width: 300px;
          margin: 20px auto;
          padding: 20px;
        }
        .header {
          text-align: center;
          border-bottom: 2px dashed #000;
          padding-bottom: 10px;
          margin-bottom: 10px;
        }
        .item {
          margin: 10px 0;
          padding: 5px 0;
          border-bottom: 1px dotted #ccc;
        }
        .item-name {
          font-weight: bold;
        }
        .dosage {
          font-size: 0.85em;
          font-style: italic;
          margin-left: 10px;
          color: #555;
        }
        .row {
          display: flex;
          justify-content: space-between;
          margin: 5px 0;
        }
        .tax-name {
            font-size: 0.9em;
            color: #444;
        }
        .totals {
          border-top: 2px solid #000;
          margin-top: 10px;
          padding-top: 10px;
        }
        .total {
          font-weight: bold;
          font-size: 1.2em;
        }
        .footer {
          text-align: center;
          margin-top: 20px;
          padding-top: 10px;
          border-top: 2px dashed #000;
          font-size: 0.9em;
        }
        @media print {
          body { margin: 0; }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <h2>26 PHARMACY</h2>
        <p>Receipt #${lastSaleData.value?.saleId}</p>
        <p>${lastSaleData.value?.date}</p>
      </div>

      <div class="items">
        ${lastSaleData.value?.items.map(item => `
          <div class="item">
            <div class="item-name">${item.drug_name}</div>
            <div class="row">
              <span>${item.quantity} x ${formatCurrency(item.price)}</span>
              <span>${formatCurrency(item.total)}</span>
            </div>
            ${item.dosage_instructions?.full_frequency ? `
              <div class="dosage">
                <strong>Dosage:</strong> ${item.dosage_instructions.measurement} ${item.dosage_instructions.full_frequency}
                ${item.dosage_instructions.special?.length ? `(${item.dosage_instructions.special.join(', ')})` : ''}
                ${item.dosage_instructions.duration ? `for ${item.dosage_instructions.duration}` : ''}
              </div>
            ` : ''}
          </div>
        `).join('')}
      </div>

      <div class="totals">
        <div class="row">
          <span>Subtotal:</span>
          <span>${formatCurrency(lastSaleData.value?.subtotal)}</span>
        </div>

        ${taxRows}

        <div class="row total">
          <span>TOTAL:</span>
          <span>
             ${lastSaleData.value.totalReturnedAmount > 0
      ? `<s style="font-size: 0.8em; color: #999; margin-right: 5px;">${formatCurrency(lastSaleData.value.totalAmount + lastSaleData.value.totalReturnedAmount)}</s>`
      : ''}
             ${formatCurrency(lastSaleData.value.totalAmount)}
          </span>
        </div>

         ${lastSaleData.value.totalReturnedAmount > 0 ? `
            <div class="row" style="color: #666; font-size: 0.9em;">
                <span>Returned Amount:</span>
                <span>-${formatCurrency(lastSaleData.value.totalReturnedAmount)}</span>
            </div>
        ` : ''}

        ${lastSaleData.value?.paymentType === 'cash' ? `
          <div class="row">
            <span>Cash Received:</span>
            <span>${formatCurrency(lastSaleData.value?.cashReceived)}</span>
          </div>
          <div class="row">
            <span>Change:</span>
            <span>
                ${lastSaleData.value.totalReturnedAmount > 0
        ? `<s style="font-size: 0.8em; color: #999; margin-right: 5px;">${formatCurrency(Math.max(0, lastSaleData.value.cashReceived - (lastSaleData.value.totalAmount + lastSaleData.value.totalReturnedAmount)))}</s>`
        : ''}
                ${formatCurrency(Math.max(0, lastSaleData.value.cashReceived - lastSaleData.value.totalAmount))}
            </span>
          </div>
        ` : ''}
        <div class="row">
          <span>Payment:</span>
          <span>${lastSaleData.value?.paymentType.toUpperCase()}</span>
        </div>
      </div>

      <div class="footer">
        <p>Thank you for your business!</p>
        <p>Please keep this receipt for your records</p>
      </div>

      <script>
        window.onload = () => {
          window.print();
        };
      <` + `/script>
    <` + `/body>

    <` + `/html>
`;

  printWindow.document.write(receiptHtml);
  printWindow.document.close();
};

const formatCurrency = (val: string | number | bigint) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'GHS' }).format(val);
const formatDate = (dateString: string | number | Date) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};
</script>

<template>

  <Head title="Point of Sale" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-[calc(100vh-6rem)] w-full gap-4 p-4 overflow-hidden">

      <!-- LEFT PANEL: CATALOG (7/12) -->
      <div class="flex flex-col w-7/12 gap-4 h-full">
        <!-- Search & Scanner Bar -->
        <div class="flex gap-2">
          <Input v-model="search" placeholder="Search items or scan barcode..." class="flex-1 h-18 text-xl"
            ref="scannerRef" autofocus />
        </div>

        <!-- Product Grid/List -->
        <div class="flex-1 rounded-xl border bg-card text-card-foreground shadow overflow-hidden flex flex-col">
          <div v-if="isLoading" class="flex-1 flex items-center justify-center">
            <span class="text-muted-foreground animate-pulse">Loading inventory...</span>
          </div>
          <div v-else class="overflow-y-auto flex-1 p-0">
            <table class="w-full caption-bottom text-sm">
              <thead class="sticky top-0 bg-muted/90 backdrop-blur z-10 [&_tr]:border-b">
                <tr class="border-b transition-colors">
                  <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground w-[40%]">Product</th>
                  <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Batch / Expiry</th>
                  <th class="h-10 px-4 text-right align-middle font-medium text-muted-foreground">Price</th>
                  <th class="h-10 px-4 text-right align-middle font-medium text-muted-foreground">Stock/shelf</th>
                  <th class="h-10 px-4 text-right align-middle font-medium text-muted-foreground">Action</th>
                </tr>
              </thead>
              <tbody v-if="inventory.data.length">
                <tr v-for="item in inventory.data" :key="item.id"
                  class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted cursor-pointer"
                  @click="openAddToCart(item)">
                  <td class="p-4 align-middle font-medium">
                    <div class="font-bold">{{ item.drug_name }}</div>
                    <div class="text-xs text-muted-foreground">{{ item.strength }}</div>
                  </td>
                  <td class="p-4 align-middle">
                    <!-- <div class="font-mono text-xs">{{ item.batch_id.substring(0, 8) }}</div> -->
                    <div class="text-xs">{{ formatDate(item.expiry_date) }}</div>
                  </td>
                  <td class="p-4 align-middle text-right font-medium">
                    {{ formatCurrency(item.selling_price) }}
                  </td>
                  <td class="p-4 align-middle text-right text-lg font-semibold">
                    <div class="font-bold">{{ item.quantity_on_hand }}</div>
                    <div class="text-xs text-muted-foreground">{{ item.location }}</div>
                  </td>
                  <td class="p-4 align-middle text-right">
                    <Button size="icon" class="bg-slate-900 hover:bg-slate-800">
                      <Plus class="h-4 w-4" />
                    </Button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Paginator -->
          <div class="p-2 border-t flex justify-end gap-2" v-if="inventory.links && inventory.links.length > 3">
            <template v-for="(link, key) in inventory.links" :key="key">
              <Button v-if="link.url" variant="outline" size="sm" :disabled="link.active || isLoading"
                @click.prevent="fetchInventory(link.url)">
                <span v-html="link.label"></span>
              </Button>
              <span v-else v-html="link.label" class="px-2 text-muted-foreground flex items-center"></span>
            </template>
          </div>
        </div>

        <!-- {{ inventory }} -->
      </div>

      <!-- RIGHT PANEL: INVOICE (5/12) -->
      <div class="flex flex-col w-5/12 gap-4 h-full">
        <div class="flex-1 rounded-xl border bg-card text-card-foreground shadow flex flex-col h-full  ">
          <div class="p-4 border-b bg-muted/20 flex justify-between items-center bg-slate-900 text-white ">
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
            <!-- Empty Cart - Show Recent Sales -->

            <!-- DEBUG AREA -->
            <!-- <div class="text-xs font-mono p-2 bg-yellow-100 dark:text-black mb-2 rounded">
              <p>Active Taxes: {{ activeTaxes.length }}</p>
              <div v-for="tax in activeTaxes" :key="tax.id">
                {{ tax.tax_name }} ({{ tax.percentage }}%) - Cats: {{ tax.applicable_categories?.length ? tax.applicable_categories.join(', ') : 'ALL' }}
              </div>
            </div> -->

            <div v-if="cart.length === 0">
              <div class="text-center text-muted-foreground py-4 border-b">
                Cart is empty
              </div>

              <!-- Recent Sales -->
              <div class="mt-4">
                <h3 class="text-sm font-semibold mb-3 px-2">Recent Sales</h3>

                <div v-if="loadingRecentSales" class="text-center py-10 text-muted-foreground">
                  <span class="animate-pulse">Loading recent sales...</span>
                </div>

                <div v-else-if="recentSales.length === 0" class="text-center py-10 text-muted-foreground text-sm">
                  No recent sales
                </div>

                <div v-else class="space-y-2">
                  <div v-for="sale in recentSales" :key="sale.id"
                    class="p-3 border rounded-lg bg-background hover:bg-muted/50 transition-colors">
                    <div class="flex justify-between items-start mb-2">
                      <div class="flex-1">
                        <div class="text-xs text-muted-foreground">
                          {{ new Date(sale.finalized_at || sale.created_at).toLocaleString() }}
                        </div>
                        <div class="font-bold text-lg mt-1">
                          {{ formatCurrency(sale.total_amount) }}
                        </div>
                        <div class="text-xs text-muted-foreground capitalize">
                          {{ sale.payment_type }}
                          <span v-if="sale.returns_exists"
                            class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                            Returned
                          </span>
                        </div>
                      </div>
                    </div>

                    <div class="flex gap-2 mt-3">
                      <Button size="sm" variant="outline" class="flex-1 text-xs" @click="viewSaleReceipt(sale)">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" viewBox="0 0 24 24" fill="none"
                          stroke="currentColor" stroke-width="2">
                          <path
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002 2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        Receipt
                      </Button>
                      <Button size="sm" variant="default" class="flex-1 text-xs" @click="printDosageInstructions(sale)">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" viewBox="0 0 24 24" fill="none"
                          stroke="currentColor" stroke-width="2">
                          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                          <polyline points="14 2 14 8 20 8" />
                          <line x1="16" y1="13" x2="8" y2="13" />
                          <line x1="16" y1="17" x2="8" y2="17" />
                          <polyline points="10 9 9 9 8 9" />
                        </svg>
                        Dosage
                      </Button>
                      <Button size="sm" variant="outline"
                        class="flex-1 text-xs text-orange-600 hover:text-orange-700 hover:bg-orange-50 border-orange-200"
                        @click="openReturnModal(sale)">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" viewBox="0 0 24 24" fill="none"
                          stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74-2.74L3 12" />
                          <path d="M3 3v9h9" />
                        </svg>
                        Return
                      </Button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Cart Items (when not empty) -->
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
                <div class="flex gap-1">
                  <Button variant="ghost" size="icon" class="h-6 w-6" @click="openEditCart(index)">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                      stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                      <path d="m15 5 4 4" />
                    </svg>
                  </Button>
                  <Button variant="ghost" size="icon" class="h-6 w-6 text-destructive" @click="removeFromCart(index)">
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
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
              <template v-if="Object.keys(taxDetails.breakdown).length > 0">
                <template v-for="(amount, name) in taxDetails.breakdown" :key="name">
                  <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">{{ name }}</span>
                    <span>{{ formatCurrency(amount) }}</span>
                  </div>
                </template>
              </template>
              <div v-else class="flex justify-between text-sm">
                <span class="text-muted-foreground">Tax</span>
                <span>{{ formatCurrency(0) }}</span>
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

              <Button variant="outline" :class="{ 'border-primary bg-primary/10': paymentType === 'momo' }"
                @click="paymentType = 'momo'">
                <CreditCard class="mr-2 h-4 w-4" /> Momo
              </Button>
              <Button variant="outline" :class="{ 'border-primary bg-primary/10': paymentType === 'card' }"
                @click="paymentType = 'card'">
                <CreditCard class="mr-2 h-4 w-4" /> Card
              </Button>
            </div>

            <!-- Cash Payment Section -->
            <div v-if="paymentType === 'cash' && cart.length > 0"
              class="mb-4 space-y-3 p-4 border rounded-lg bg-muted/50">
              <div>
                <Label for="cashReceived" class="text-sm font-medium">Cash Received</Label>
                <Input id="cashReceived" type="number" v-model.number="cashReceived" placeholder="Enter amount received"
                  class="mt-1 text-lg font-semibold" step="0.01" min="0" />
              </div>
              <div v-if="cashReceived > 0" class="flex justify-between items-center p-3 bg-background rounded border-2"
                :class="cashReceived >= totalAmount ? 'border-green-500' : 'border-red-500'">
                <span class="font-medium">Change:</span>
                <span class="text-xl font-bold"
                  :class="cashReceived >= totalAmount ? 'text-green-600' : 'text-red-600'">
                  {{ cashReceived >= totalAmount ? formatCurrency(changeAmount) : 'Insufficient' }}
                </span>
              </div>
            </div>

            <!-- Customer Information Toggle & Form -->
            <div v-if="cart.length > 0" class="mb-4">
              <Button variant="outline" class="w-full gap-2" @click="showCustomerInfo = !showCustomerInfo"
                type="button">
                <User class="h-4 w-4" />
                {{ showCustomerInfo ? 'Hide' : 'Add' }} Customer Information (Optional)
              </Button>

              <!-- Customer Info Form -->
              <div v-if="showCustomerInfo" class="mt-3 space-y-3 p-4 border rounded-lg bg-muted/50">
                <div>
                  <Label for="customerName" class="text-sm font-medium">Customer Name</Label>
                  <Input id="customerName" v-model="customerInfo.name" placeholder="Enter customer name" class="mt-1" />
                </div>
                <div>
                  <Label for="customerPhone" class="text-sm font-medium">Phone Number</Label>
                  <Input id="customerPhone" v-model="customerInfo.phone" type="tel" placeholder="Enter phone number"
                    class="mt-1" />
                </div>
                <div>
                  <Label for="customerEmail" class="text-sm font-medium">Email (Optional)</Label>
                  <Input id="customerEmail" v-model="customerInfo.email" type="email" placeholder="Enter email address"
                    class="mt-1" />
                </div>
                <div>
                  <Label for="customerDob" class="text-sm font-medium">Date of Birth (Optional)</Label>
                  <Input id="customerDob" v-model="customerInfo.dob" type="date" class="mt-1" />
                </div>
              </div>
            </div>

            <Button class="w-full h-12 text-lg  bg-slate-900 text-white   hover:bg-slate-800"
              :disabled="cart.length === 0 || processing" @click="finalizeSale">
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
            <Input type="number" v-model="qtyForm.quantity" class="col-span-3" min="1" required />
          </div>
          <div class=" items-start gap-4">
            <Label class=" pb-2 text-right pt-2">Dosage (optional)</Label>
            <div class="col-span-3">
              <DosageSelector v-model="qtyForm.dosage_instructions" />
            </div>
          </div>
          <div class="text-center text-sm font-bold mt-2">
            Estimated: {{ formatCurrency((selectedProduct?.selling_price * qtyForm.quantity)) }}
            <!-- Mock Calculation Display -->
          </div>
        </div>
        <DialogFooter>
          <Button type="submit" @click="addToCart">Add to Cart</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Edit Cart Item Modal -->
    <Dialog :open="editCartOpen" @update:open="editCartOpen = $event">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Edit Cart Item</DialogTitle>
          <DialogDescription v-if="editingCartIndex !== null">
            {{ cart[editingCartIndex]?.drug_name }}
          </DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class=" items-center gap-4">
            <Label class=" pb-2 text-right">Quantity</Label>
            <Input type="number" v-model="qtyForm.quantity" class="col-span-3" min="1" required />
          </div>
          <div class=" items-start gap-4">
            <Label class=" pb-2 text-right pt-2">Dosage (optional)</Label>
            <div class="col-span-3">
              <DosageSelector v-model="qtyForm.dosage_instructions" />
            </div>
          </div>
          <div class="text-center text-sm font-bold mt-2" v-if="editingCartIndex !== null">
            Estimated: {{ formatCurrency((cart[editingCartIndex]?.price * qtyForm.quantity)) }}
          </div>
        </div>
        <DialogFooter>
          <Button type="submit" @click="updateCartItem">Update Item</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Receipt Preview using Reusable Component -->
    <InvoiceReceipt :sale="lastSaleData" :show="showReceipt" @update:show="showReceipt = $event" />

    <!-- Dosage Instructions Dialog -->
    <DosageInstructions :sale="dosageSaleData" :show="showDosageDialog" @update:show="showDosageDialog = $event" />

    <!-- Return Modal -->
    <ReturnModal :sale="selectedSaleForReturn" :open="returnModalOpen" @update:open="returnModalOpen = $event"
      @success="fetchRecentSales(); fetchInventory();" />

  </AppLayout>
</template>
