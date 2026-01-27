<script setup lang="ts">
import { computed, ref } from 'vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Printer, Pencil, Save, X } from 'lucide-vue-next';
import DosageSelector from '@/pages/Store/Partials/DosageSelector.vue';

interface DosageInstructions {
  frequency?: string;
  full_frequency?: string;
  route?: string;
  measurement?: string;
  special?: string[];
  duration?: string;
}

interface SaleItem {
  id?: string;
  drug_name?: string;
  drug?: { name?: string };
  quantity: number;
  dosage_instructions?: DosageInstructions;
}

interface Sale {
  id?: string;
  items: SaleItem[];
  finalized_at?: string;
  created_at?: string;
}

const props = defineProps<{
  sale: Sale | null;
  show: boolean;
}>();

const emit = defineEmits<{
  'update:show': [value: boolean];
  'dosageUpdated': [];
}>();

const editingItemIndex = ref<number | null>(null);
const editedDosage = ref<DosageInstructions | null>(null);
const saving = ref(false);

const itemsWithDosage = computed(() => {
  if (!props.sale?.items) return [];
  return props.sale.items.filter(item =>
    item.dosage_instructions && item.dosage_instructions.full_frequency
  );
});

const saleDate = computed(() => {
  if (!props.sale) return '';
  const date = props.sale.finalized_at || props.sale.created_at;
  return date ? new Date(date).toLocaleString() : new Date().toLocaleString();
});

const startEditing = (index: number) => {
  editingItemIndex.value = index;
  const item = itemsWithDosage.value[index];
  editedDosage.value = item.dosage_instructions ? { ...item.dosage_instructions } : null;
};

const cancelEditing = () => {
  editingItemIndex.value = null;
  editedDosage.value = null;
};

const saveDosage = async (index: number) => {
  const item = itemsWithDosage.value[index];
  if (!item.id || !editedDosage.value) return;

  saving.value = true;

  try {
    const { default: axios } = await import('axios');
    await axios.patch(`/app/sale-items/${item.id}/dosage`, {
      dosage_instructions: editedDosage.value
    }, {
      headers: { 'Accept': 'application/json' }
    });

    // Update local data
    if (item.dosage_instructions) {
      Object.assign(item.dosage_instructions, editedDosage.value);
    }

    cancelEditing();
    emit('dosageUpdated');
  } catch (error) {
    console.error('Failed to update dosage instructions', error);
    alert('Failed to update dosage instructions');
  } finally {
    saving.value = false;
  }
};

const printDosage = () => {
  const printWindow = window.open('', '_blank');
  if (!printWindow) return;

  const dosageHtml = `
    <!DOCTYPE html>
    <html>
    <head>
      <title>Dosage Instructions - ${props.sale?.id}</title>
      <style>
        body {
          font-family: Arial, sans-serif;
          max-width: 800px;
          margin: 20px auto;
          padding: 20px;
        }
        .header {
          text-align: center;
          border-bottom: 2px solid #000;
          padding-bottom: 10px;
          margin-bottom: 20px;
        }
        .medication {
          margin: 20px 0;
          padding: 15px;
          border: 1px solid #ccc;
          border-radius: 5px;
          page-break-inside: avoid;
        }
        .drug-name {
          font-size: 1.2em;
          font-weight: bold;
          margin-bottom: 10px;
        }
        .instructions {
          margin: 10px 0;
          line-height: 1.6;
        }
        .label {
          font-weight: bold;
          color: #333;
        }
        @media print {
          body { margin: 0; padding: 20px; }
        }
      </style>
    </head>
    <body>
      <!-- <div class="header">
        <h2>26 PHARMACY</h2>
        <h3>Medication Dosage Instructions</h3>
        <p>Date: ${saleDate.value}</p>
      </div> -->

      ${itemsWithDosage.value.map(item => {
    const dosage = item.dosage_instructions!;
    const drugName = item.drug?.name || item.drug_name || 'Unknown Medication';

    return `
          <div class="medication">
            <div class="drug-name">${drugName}</div>
            <div class="instructions">
              <p><span class="label">Dosage:</span> ${dosage.measurement} ${dosage.full_frequency}</p>
              ${dosage.route ? `<p><span class="label">Route:</span> ${dosage.route}</p>` : ''}
              ${dosage.special?.length ? `<p><span class="label">Special Instructions:</span> ${dosage.special.join(', ')}</p>` : ''}
              ${dosage.duration ? `<p><span class="label">Duration:</span> ${dosage.duration}</p>` : ''}
              <p><span class="label">Quantity:</span> ${item.quantity} unit(s)</p>
            </div>
          </div>
        `;
  }).join('')}

      <script>
        window.onload = () => {
          window.print();
        };
      <` + `/script>
    <` + `/body>
    <` + `/html>
  `;

  printWindow.document.write(dosageHtml);
  printWindow.document.close();
};
</script>

<template>
  <Dialog :open="show" @update:open="emit('update:show', $event)">
    <DialogContent class="sm:max-w-[600px]">
      <DialogHeader>
        <DialogTitle>Dosage Instructions</DialogTitle>
        <DialogDescription>
          Medication instructions for sale #{{ sale?.id }}
        </DialogDescription>
      </DialogHeader>

      <div class="max-h-[60vh] overflow-y-auto p-4 border rounded-lg bg-muted/20">
        <div class="text-center mb-4 pb-4 border-b-2">
          <h3 class="text-xl font-bold">26 PHARMACY</h3>
          <p class="text-sm text-muted-foreground">{{ saleDate }}</p>
        </div>

        <div v-if="itemsWithDosage.length === 0" class="text-center py-10 text-muted-foreground">
          No dosage instructions available for this sale
        </div>

        <div v-else class="space-y-4">
          <div v-for="(item, index) in itemsWithDosage" :key="index" class="p-4 border rounded-lg bg-background">
            <div class="flex justify-between items-start mb-3">
              <div class="font-bold text-lg">
                {{ item.drug?.name || item.drug_name || 'Unknown Medication' }}
              </div>
              <Button v-if="editingItemIndex !== index" size="sm" variant="ghost" @click="startEditing(index)"
                class="h-8">
                <Pencil class="h-3 w-3 mr-1" />
                Edit
              </Button>
            </div>

            <!-- View Mode -->
            <div v-if="editingItemIndex !== index" class="space-y-1 text-sm">
              <div class="flex">
                <span class="font-semibold w-32">Dosage:</span>
                <span>{{ item.dosage_instructions?.measurement }} {{ item.dosage_instructions?.full_frequency }}</span>
              </div>

              <div v-if="item.dosage_instructions?.route" class="flex">
                <span class="font-semibold w-32">Route:</span>
                <span class="capitalize">{{ item.dosage_instructions.route }}</span>
              </div>

              <div v-if="item.dosage_instructions?.special?.length" class="flex">
                <span class="font-semibold w-32">Special Instructions:</span>
                <span>{{ item.dosage_instructions.special.join(', ') }}</span>
              </div>

              <div v-if="item.dosage_instructions?.duration" class="flex">
                <span class="font-semibold w-32">Duration:</span>
                <span>{{ item.dosage_instructions.duration }}</span>
              </div>

              <div class="flex">
                <span class="font-semibold w-32">Quantity:</span>
                <span>{{ item.quantity }} unit(s)</span>
              </div>
            </div>

            <!-- Edit Mode -->
            <div v-else class="space-y-3">
              <DosageSelector v-model="editedDosage" />

              <div class="flex gap-2 justify-end pt-2 border-t">
                <Button size="sm" variant="outline" @click="cancelEditing" :disabled="saving">
                  <X class="h-3 w-3 mr-1" />
                  Cancel
                </Button>
                <Button size="sm" @click="saveDosage(index)" :disabled="saving">
                  <Save class="h-3 w-3 mr-1" />
                  {{ saving ? 'Saving...' : 'Save' }}
                </Button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <DialogFooter class="gap-2">
        <Button variant="outline" @click="emit('update:show', false)">
          Close
        </Button>
        <Button v-if="itemsWithDosage.length > 0" @click="printDosage" class="gap-2">
          <Printer class="h-4 w-4" />
          Print Instructions
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
