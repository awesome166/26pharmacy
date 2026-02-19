<script setup lang="ts">
import { computed } from 'vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Printer } from 'lucide-vue-next';

interface DosageInstructions {
  frequency?: string;
  full_frequency?: string;
  route?: string;
  measurement?: string;
  special?: string[];
  duration?: string;
}

interface SaleItem {
  drug_name?: string;
  quantity: number;
  price: number;
  total?: number;
  line_total?: number;
  return_quantity?: number;
  dosage_instructions?: DosageInstructions;
  drug?: {
    name: string;
    [key: string]: any;
  };
  [key: string]: any;
}

interface Sale {
  id?: string;
  items: SaleItem[];
  subtotal?: number;
  subtotal_amount?: number;
  taxDetails?: Record<string, number>;
  total_amount?: number;
  totalAmount?: number;
  payment_type?: string;
  paymentType?: string;
  cash_received?: number;
  cashReceived?: number;
  change_amount?: number;
  change?: number;
  total_returned_amount?: number;
  totalReturnedAmount?: number;
  finalized_at?: string;
  date?: string;
  served_by?: string;
  user_name?: string;
  user?: {
    name: string;
    [key: string]: any;
  };
  [key: string]: any;
}

const props = defineProps<{
  sale: Sale | null;
  show: boolean;
  showPrintButton?: boolean;
}>();

const emit = defineEmits<{
  'update:show': [value: boolean];
}>();

// Normalize sale data to handle both snake_case (from DB) and camelCase (from frontend)
const normalizedSale = computed(() => {
  if (!props.sale) return null;

  // Normalize items to ensure all have calculated totals and drug names
  const normalizedItems = (props.sale.items || []).map(item => {
    const price = Number(item.price) || 0;
    const quantity = Number(item.quantity) || 0;
    const total = item.total ?? item.line_total ?? (price * quantity);

    // Extract drug_name from either direct field or nested drug relationship
    const drug_name = item.drug_name || item.drug?.name || 'Unknown Drug';

    return {
      ...item,
      drug_name,
      price,
      quantity,
      total
    };
  });

  // Extract served by user name
  const servedBy = props.sale.served_by || props.sale.user?.name || props.sale.user_name || null;

  return {
    id: props.sale.id || 'N/A',
    items: normalizedItems,
    subtotal: props.sale.subtotal ?? props.sale.subtotal_amount ?? 0,
    taxAmount: props.sale.taxAmount ?? props.sale.tax_amount ?? 0,
    taxDetails: props.sale.taxDetails || (props.sale.taxAmount ? { 'Tax': props.sale.taxAmount } : (props.sale.tax_amount ? { 'Tax': props.sale.tax_amount } : {})),
    totalAmount: Number(props.sale.totalAmount ?? props.sale.total_amount ?? 0),
    paymentType: props.sale.paymentType ?? props.sale.payment_type ?? 'cash',
    cashReceived: Number(props.sale.cashReceived ?? props.sale.cash_received ?? 0),
    change: Number(props.sale.change ?? props.sale.change_amount ?? 0),
    totalReturnedAmount: Number(props.sale.totalReturnedAmount ?? props.sale.total_returned_amount ?? 0),
    date: props.sale.date ?? (props.sale.finalized_at ? new Date(props.sale.finalized_at).toLocaleString() : new Date().toLocaleString()),
    servedBy,
  };
});

const formatCurrency = (val: number | string | undefined | null) => {
  const numVal = Number(val);
  if (isNaN(numVal)) return 'GHS 0.00';
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'GHS' }).format(numVal);
};

const printReceipt = () => {
  if (!normalizedSale.value) return;

  const printWindow = window.open('', '_blank');
  if (!printWindow) return;

  // Format Tax Rows
  const taxRows = Object.entries(normalizedSale.value.taxDetails || {}).map(([name, amount]) => `
     <div class="row">
        <span class="tax-name">${name}:</span>
        <span>${formatCurrency(amount as number)}</span>
    </div>
  `).join('');

  const receiptHtml = `
    <!DOCTYPE html>
    <html>
    <head>
      <title>Receipt - ${normalizedSale.value.id}</title>
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
        <p>Receipt #${normalizedSale.value.id}</p>
        <p>${normalizedSale.value.date}</p>
        ${normalizedSale.value.servedBy ? `<p style="font-size: 0.85em; margin-top: 5px;">Served by: ${normalizedSale.value.servedBy}</p>` : ''}
      </div>

      <div class="items">
        ${normalizedSale.value.items.map(item => `
          <div class="item">
            <div class="item-name">${item.drug_name}</div>
            <div class="row">
              <span>${item.quantity} x ${formatCurrency(item.price)}</span>
              <span>${formatCurrency(item.total)}</span>
            </div>
            ${item.return_quantity > 0 ? `
                <div class="row" style="color: #ef4444; font-size: 0.85em; margin-top: -2px;">
                    <span>Returned:</span>
                    <span>-${item.return_quantity}</span>
                </div>
            ` : ''}
            ${item.return_quantity > 0 ? `
                <div class="row" style="color: #ef4444; font-size: 0.85em; margin-top: -2px;">
                    <span>Returned:</span>
                    <span>-${item.return_quantity}</span>
                </div>
            ` : ''}
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
          <span>${formatCurrency(normalizedSale.value.subtotal)}</span>
        </div>

        ${taxRows}

        <div class="row total">
          <span>TOTAL:</span>
          <span>
             ${normalizedSale.value.totalReturnedAmount > 0
      ? `<s style="font-size: 0.8em; color: #999; margin-right: 5px;">${formatCurrency(normalizedSale.value.totalAmount + normalizedSale.value.totalReturnedAmount)}</s>`
      : ''}
             ${formatCurrency(normalizedSale.value.totalAmount)}
          </span>
        </div>

        ${normalizedSale.value.totalReturnedAmount > 0 ? `
            <div class="row" style="color: #666; font-size: 0.9em;">
                <span>Returned Amount:</span>
                <span>-${formatCurrency(normalizedSale.value.totalReturnedAmount)}</span>
            </div>
        ` : ''}

        ${normalizedSale.value.paymentType === 'cash' && normalizedSale.value.cashReceived ? `
          <div class="row">
            <span>Cash Received:</span>
            <span>${formatCurrency(normalizedSale.value.cashReceived)}</span>
          </div>
          <div class="row">
            <span>Change:</span>
            <span>
                ${normalizedSale.value.totalReturnedAmount > 0
        ? `<s style="font-size: 0.8em; color: #999; margin-right: 5px;">${formatCurrency(Math.max(0, normalizedSale.value.cashReceived - (normalizedSale.value.totalAmount + normalizedSale.value.totalReturnedAmount)))}</s>`
        : ''}
                ${formatCurrency(Math.max(0, normalizedSale.value.cashReceived - normalizedSale.value.totalAmount))}
            </span>
          </div>
        ` : ''}
        <div class="row">
          <span>Payment:</span>
          <span>${normalizedSale.value.paymentType.toUpperCase()}</span>
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
</script>

<template>
  <Dialog :open="show" @update:open="emit('update:show', $event)">
    <DialogContent class="sm:max-w-[500px]">
      <DialogHeader>
        <DialogTitle>Sale Completed!</DialogTitle>
        <DialogDescription>
          Receipt #{{ normalizedSale?.id }}
        </DialogDescription>
      </DialogHeader>

      <div class="max-h-[60vh] overflow-y-auto p-4 border rounded-lg bg-muted/20">
        <!-- Receipt Header -->
        <div class="text-center mb-4 pb-4 border-b-2 border-dashed">
          <h3 class="text-xl font-bold">26 PHARMACY</h3>
          <p class="text-sm text-muted-foreground">{{ normalizedSale?.date }}</p>
          <p v-if="normalizedSale?.servedBy" class="text-xs text-muted-foreground mt-1">
            Served by: {{ normalizedSale.servedBy }}
          </p>
        </div>

        <!-- Items -->
        <div class="space-y-3 mb-4">
          <div v-for="(item, index) in normalizedSale?.items" :key="index" class="pb-3 border-b">
            <div class="font-bold">{{ item.drug_name }}</div>
            <div class="flex justify-between text-sm mt-1">
              <span>{{ item.quantity }} x {{ formatCurrency(item.price) }}</span>
              <span class="font-semibold">{{ formatCurrency(item.total) }}</span>
            </div>
            <div v-if="(item.return_quantity || 0) > 0" class="text-xs text-red-600 font-medium mt-0.5">
              Returned: -{{ item.return_quantity }}
            </div>
            <div v-if="item.dosage_instructions?.full_frequency"
              class="text-xs text-muted-foreground mt-2 p-2 bg-background rounded">
              <strong>Dosage Instructions:</strong><br />
              {{ item.dosage_instructions.measurement }} {{ item.dosage_instructions.full_frequency }}
              <span v-if="item.dosage_instructions.special?.length">
                ({{ item.dosage_instructions.special.join(', ') }})
              </span>
              <span v-if="item.dosage_instructions.duration">
                for {{ item.dosage_instructions.duration }}
              </span>
            </div>
          </div>
        </div>

        <!-- Totals -->
        <div class="space-y-2 pt-4 border-t-2">
          <div class="flex justify-between text-sm">
            <span>Subtotal:</span>
            <span>{{ formatCurrency(normalizedSale?.subtotal ?? 0) }}</span>
          </div>
          <template v-for="(amount, name) in normalizedSale?.taxDetails" :key="name">
            <div class="flex justify-between text-sm">
              <span class="text-muted-foreground">{{ name }}</span>
              <span>{{ formatCurrency(amount) }}</span>
            </div>
          </template>

          <div class="flex justify-between text-lg font-bold pt-2 border-t">
            <span>TOTAL:</span>
            <span>
              <s v-if="(normalizedSale?.totalReturnedAmount || 0) > 0"
                class="text-sm text-muted-foreground mr-2 font-normal">
                {{ formatCurrency((normalizedSale?.totalAmount ?? 0) + (normalizedSale?.totalReturnedAmount ?? 0)) }}
              </s>
              {{ formatCurrency(normalizedSale?.totalAmount ?? 0) }}
            </span>
          </div>

          <div v-if="(normalizedSale?.totalReturnedAmount || 0) > 0"
            class="flex justify-between text-sm text-destructive font-medium">
            <span>Returned Amount:</span>
            <span>-{{ formatCurrency(normalizedSale?.totalReturnedAmount) }}</span>
          </div>

          <div v-if="normalizedSale?.paymentType === 'cash' && normalizedSale?.cashReceived"
            class="pt-2 border-t space-y-1">
            <div class="flex justify-between">
              <span>Cash Received:</span>
              <span class="font-semibold">{{ formatCurrency(normalizedSale?.cashReceived) }}</span>
            </div>
            <div class="flex justify-between text-green-600 font-bold">
              <span>Change:</span>
              <span>
                <s v-if="(normalizedSale?.totalReturnedAmount || 0) > 0"
                  class="text-sm text-muted-foreground mr-2 font-normal">
                  {{ formatCurrency(Math.max(0, (normalizedSale?.cashReceived ?? 0) - ((normalizedSale?.totalAmount ??
                    0) +
                    (normalizedSale?.totalReturnedAmount ?? 0)))) }}
                </s>
                {{ formatCurrency(Math.max(0, (normalizedSale?.cashReceived ?? 0) - (normalizedSale?.totalAmount ?? 0)))
                }}
              </span>
            </div>
          </div>
          <div class="flex justify-between text-sm pt-2">
            <span>Payment Method:</span>
            <span class="uppercase font-medium">{{ normalizedSale?.paymentType }}</span>
          </div>
        </div>

        <div class="text-center mt-6 pt-4 border-t text-sm text-muted-foreground">
          <p>Thank you for your business!</p>
        </div>
      </div>

      <DialogFooter class="gap-2">
        <Button variant="outline" @click="emit('update:show', false)">
          Close
        </Button>
        <Button @click="printReceipt" class="gap-2">
          <Printer class="h-4 w-4" />
          Print Receipt
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
