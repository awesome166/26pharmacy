<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { debounce } from 'lodash';
import { useForm, usePage } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import { cn } from '@/lib/utils';
import axios from 'axios';

const page = usePage();

const open = ref(false);
const openDrugSearch = ref(false);
const drugs = ref<any[]>([]);
const loadingDrugs = ref(false);
const batchMode = computed(() => page.props.auth.settings?.settings?.inventory_batch_mode || false);
const selectedDrug = ref<any>(null);
const drugSearchQuery = ref('');

const form = useForm({
  drug_id: '',
  expiry_date: '',
  lot_number: '',
  manufacturer: '',
  cost_price: 0,
  selling_price: 0, // Added for Direct Mode
  quantity: 0,
  name: '' // Added as per platform migration requirement
});

const isEditing = ref(false);
const editingId = ref<string | null>(null);

const openDialog = async (drugId: string = '', batchToEdit: any = null) => {
  form.reset();
  form.clearErrors();

  if (batchToEdit) {
    isEditing.value = true;
    editingId.value = batchToEdit.id;
    form.drug_id = batchToEdit.drug_id;
    form.expiry_date = batchToEdit.expiry_date ? batchToEdit.expiry_date.split('T')[0] : '';
    form.lot_number = batchToEdit.lot_number;
    form.manufacturer = batchToEdit.manufacturer;
    form.cost_price = batchToEdit.cost_price;
    form.selling_price = batchToEdit.selling_price;
    form.quantity = batchToEdit.quantity;
    form.name = batchToEdit.name;

    // Set initial selected drug for display
    if (batchToEdit.drug) {
      selectedDrug.value = batchToEdit.drug;
    }
  } else {
    isEditing.value = false;
    editingId.value = null;
    form.drug_id = drugId;
    selectedDrug.value = null;
  }

  open.value = true;
  await Promise.all([fetchDrugs()]);
};


const fetchDrugs = async (searchTerm: string = '') => {
  loadingDrugs.value = true;
  try {
    const params: any = { wantsJson: 1 };
    if (searchTerm) {
      params.search = searchTerm;
    }
    const res = await axios.get('/app/drugs', { params });
    drugs.value = res.data.data.data || res.data.data || [];
  } catch (e) {
    console.error(e);
  } finally {
    loadingDrugs.value = false;
  }
};

const debouncedFetchDrugs = debounce((searchTerm: string) => {
  fetchDrugs(searchTerm);
}, 300);

watch(drugSearchQuery, (newValue) => {
  debouncedFetchDrugs(newValue);
});

const submit = () => {
  if (isEditing.value && editingId.value) {
    form.patch(`/app/batches/${editingId.value}`, {
      onSuccess: () => {
        open.value = false;
        form.reset();
        isEditing.value = false;
        editingId.value = null;
      }
    });
  } else {
    form.post('/app/batches', {
      onSuccess: () => {
        open.value = false;
        form.reset();
      }
    });
  }
};

defineExpose({ openDialog });
</script>

<template>
  <Dialog :open="open" @update:open="open = $event">
    <DialogContent class="sm:max-w-[500px]">
      <DialogHeader>
        <DialogTitle>{{ isEditing ? 'Edit Batch' : 'Create New Batch' }}
          <span class="ml-2 text-xs font-normal text-muted-foreground">
            Batch Mode:
            <span v-if="batchMode" class="text-green-500">Enabled</span>
            <span v-else class="text-red-500">Disabled</span>
          </span>
        </DialogTitle>
        <DialogDescription>
          Register a new batch for a drug.
        </DialogDescription>
      </DialogHeader>
      <div class="grid gap-4 py-4">
        <div class="space-y-2">
          <Label>Select Drug</Label>
          <Popover v-model:open="openDrugSearch">
            <PopoverTrigger as-child>
              <Button variant="outline" role="combobox" class="w-full justify-between">
                <span class="truncate">
                  {{ selectedDrug ? `${selectedDrug.name} (${selectedDrug.strength})` : 'Search for a drug...' }}
                </span>
                <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
              </Button>
            </PopoverTrigger>
            <PopoverContent class="w-[400px] p-0">
              <Command>
                <CommandInput placeholder="Search drugs..." v-model="drugSearchQuery" />
                <CommandList>
                  <CommandEmpty>{{ loadingDrugs ? 'Loading...' : 'No drug found.' }}</CommandEmpty>
                  <CommandGroup>
                    <CommandItem v-for="drug in drugs" :key="drug.drug_id" :value="drug.drug_id" @select="() => {
                      selectedDrug = drug;
                      form.drug_id = drug.drug_id;
                      openDrugSearch = false;
                    }">
                      <Check :class="cn('mr-2 h-4 w-4', form.drug_id === drug.drug_id ? 'opacity-100' : 'opacity-0')" />
                      <div class="flex flex-col">
                        <span>{{ drug.name }}</span>
                        <span class="text-xs text-muted-foreground">{{ drug.strength }}</span>
                      </div>
                    </CommandItem>
                  </CommandGroup>
                </CommandList>
              </Command>
            </PopoverContent>
          </Popover>
        </div>

        <div class="space-y-2">
          <Label>Batch Name / Tag</Label>
          <Input v-model="form.name" placeholder="e.g. Pfizer-2024-A" />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-2">
            <Label>Lot Number</Label>
            <Input v-model="form.lot_number" />
          </div>
          <div class="space-y-2">
            <Label>Expiry Date</Label>
            <Input type="date" v-model="form.expiry_date" />
          </div>
        </div>

        <div class="space-y-2">
          <Label>Manufacturer</Label>
          <Input v-model="form.manufacturer" />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-2">
            <Label>Cost Price (Global)</Label>
            <Input type="number" v-model="form.cost_price" step="0.01" />
          </div>
          <div class="space-y-2">
            <Label>Total Batch Quantity</Label>
            <Input type="number" v-model="form.quantity" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4" v-if="!batchMode">
          <div class="space-y-2">
            <Label>Selling Price</Label>
            <Input type="number" v-model="form.selling_price" step="0.01" />
          </div>
          <div class="space-y-2 flex items-end pb-2">
            <span class="text-xs text-muted-foreground">
              Batch mode {{ batchMode ? 'enabled' : 'disabled' }} - Direct: Item will be added to inventory immediately.
            </span>
          </div>
        </div>
      </div>
      <DialogFooter>
        <Button variant="ghost" @click="open = false">Cancel</Button>
        <Button type="submit" @click="submit" :disabled="form.processing || !form.drug_id">
          {{ isEditing ? 'Update Batch' : 'Create Batch' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
