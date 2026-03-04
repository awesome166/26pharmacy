<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue';
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

// const props = defineProps({
//   accountID: String
// });

const page = usePage();

const open = ref(false);
const openDrugSearch = ref(false);
const drugs = ref<any[]>([]);
const batches = ref<any[]>([]);
const loadingDrugs = ref(false);
const loadingBatches = ref(false);
const selectedDrug = ref<any>(null);
const batchMode = computed(() => page.props.auth.settings?.settings?.inventory_batch_mode || false);
const drugSearchQuery = ref('');

const form = useForm({
  //  account_id: props.accountID,
  drug_id: '',
  batch_id: '',
  selling_price: 0,
  quantity_on_hand: 0,
  location: ''
});

const openDialog = async (preSelectedBatch: any = null) => {
  open.value = true;
  await fetchDrugs();

  if (preSelectedBatch) {
    if (preSelectedBatch.drug) {
      selectedDrug.value = preSelectedBatch.drug;
      form.drug_id = preSelectedBatch.drug.id || preSelectedBatch.drug_id;
    }

    // We need to fetch batches for the selected drug to populate the dropdown
    // and then select the specific batch
    if (form.drug_id) {
      await fetchBatches(form.drug_id);
      form.batch_id = preSelectedBatch.id;

      // Pre-fill quantity if available/relevant
      if (preSelectedBatch.quantity) {
        form.quantity_on_hand = preSelectedBatch.quantity
      }
    }
  }
};

// const fetchSettings = async () => {
//   try {
//     const res = await axios.get('/app/config');
//     if (res.data.data && typeof res.data.config.inventory_batch_mode !== 'undefined') {
//      batchMode.value = !!res.data.config.inventory_batch_mode;
//     }
//   } catch (e) {
//     console.error('Failed to fetch settings', e);
//   }
// };

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

const fetchBatches = async (drugId: string) => {
  if (!drugId) return;
  loadingBatches.value = true;
  try {
    const res = await axios.get(`/app/batches?drug_id=${drugId}&wantsJson=1`);
    const payload = res.data?.data;
    batches.value = payload?.data || payload || [];
  } catch (e) {
    console.error(e);
  } finally {
    loadingBatches.value = false;
  }
};

watch(() => form.drug_id, (newVal) => {
  form.batch_id = '';
  batches.value = [];
  if (newVal) fetchBatches(newVal);
});

const submit = () => {
  form.post('/app/inventory', {
    onSuccess: () => {
      open.value = false;
      form.reset();
    }
  });
};

defineExpose({ openDialog });
</script>

<template>
  <Dialog :open="open" @update:open="open = $event">
    <DialogContent class="sm:max-w-[500px]">
      <DialogHeader>
        <DialogTitle>Add Inventory
          <span class="ml-2 text-xs font-normal text-muted-foreground">
            Batch Mode:
            <span v-if="batchMode" class="text-green-500">Enabled</span>
            <span v-else class="text-red-500">Disabled</span>
          </span>
        </DialogTitle>
        <DialogDescription>
          Select a drug from the list to initialize into stock at this branch.
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
                    <CommandItem
                      v-for="drug in drugs"
                      :key="drug.id"
                      :value="`${drug.name || ''} ${drug.generic_name || ''} ${drug.strength || ''}`.trim()"
                      @select="() => {
                      selectedDrug = drug;
                      form.drug_id = drug.id;
                      openDrugSearch = false;
                    }">
                      <Check :class="cn('mr-2 h-4 w-4', form.drug_id === drug.id ? 'opacity-100' : 'opacity-0')" />
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

        <div class="space-y-2" v-if="form.drug_id">
          <Label>Select Batch</Label>
          <Select v-model="form.batch_id">
            <SelectTrigger>
              <SelectValue :placeholder="loadingBatches ? 'Loading batches...' : 'Choose a batch'" />
            </SelectTrigger>
            <SelectContent>
            <SelectItem v-for="batch in batches" :key="batch.id" :value="batch.id">
                {{ batch.lot_number || batch.name || batch.id?.substring(0,8) }} (Exp: {{ batch.expiry_date }})
              </SelectItem>
            </SelectContent>
          </Select>
          <p class="text-xs text-muted-foreground" v-if="batches.length === 0 && !loadingBatches">
            No batches found for this drug. Create one first.
          </p>
        </div>

        <div class="grid grid-cols-2 gap-4" v-if="batchMode">
          <div class="space-y-2">
            <Label>Selling Price</Label>
            <Input type="number" v-model="form.selling_price" step="0.01" />
          </div>
          <div class="space-y-2">
            <Label>Initial Quantity</Label>
            <Input type="number" v-model="form.quantity_on_hand" />
          </div>
        </div>
        <div class="space-y-2" v-if="batchMode">
          <Label>Shelf / Location</Label>
          <Input v-model="form.location" placeholder="e.g. Shelf A-01" />
        </div>
        <div v-else class="p-3 bg-muted rounded-md text-sm">
          <p class="text-muted-foreground">
            <strong>Batch Mode: {{ batchMode ? 'Enabled' : 'Disabled' }}</strong> Selling price and quantity are managed
            at the batch level.
          </p>
        </div>
      </div>
      <DialogFooter>
        <Button variant="ghost" @click="open = false">Cancel</Button>
        <Button type="submit" @click="submit" :disabled="form.processing || !form.batch_id">
          Add to Stock
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
