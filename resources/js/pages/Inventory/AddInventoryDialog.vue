<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import axios from 'axios';

const props = defineProps({
  branchId: String,
  tenantId: String
});

const open = ref(false);
const drugs = ref<any[]>([]);
const batches = ref<any[]>([]);
const loadingDrugs = ref(false);
const loadingBatches = ref(false);

const form = useForm({
  tenant_id: props.tenantId || '',
  branch_id: props.branchId || '',
  drug_id: '',
  batch_id: '',
  selling_price: 0,
  quantity_on_hand: 0
});

const openDialog = () => {
  open.value = true;
  fetchDrugs();
};

const fetchDrugs = async () => {
  loadingDrugs.value = true;
  try {
    const res = await axios.get('/app/drugs?wantsJson=1');
    drugs.value = res.data.data.data || res.data.data || [];
  } catch (e) {
    console.error(e);
  } finally {
    loadingDrugs.value = false;
  }
};

const fetchBatches = async (drugId: string) => {
  if (!drugId) return;
  loadingBatches.value = true;
  try {
    const res = await axios.get(`/app/batches?drug_id=${drugId}&wantsJson=1`);
    batches.value = res.data.data || res.data || [];
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
        <DialogTitle>Add Inventory</DialogTitle>
        <DialogDescription>
          Select a drug and batch to initialize stock at this branch.
        </DialogDescription>
      </DialogHeader>
      <div class="grid gap-4 py-4">
        <div class="space-y-2">
          <Label>Select Drug</Label>
          <Select v-model="form.drug_id">
            <SelectTrigger>
              <SelectValue :placeholder="loadingDrugs ? 'Loading drugs...' : 'Choose a drug'" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="drug in drugs" :key="drug.drug_id" :value="drug.drug_id">
                {{ drug.name }} ({{ drug.strength }})
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-2" v-if="form.drug_id">
          <Label>Select Batch</Label>
          <Select v-model="form.batch_id">
            <SelectTrigger>
              <SelectValue :placeholder="loadingBatches ? 'Loading batches...' : 'Choose a batch'" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="batch in batches" :key="batch.batch_id" :value="batch.batch_id">
                {{ batch.lot_number }} (Exp: {{ batch.expiry_date }})
              </SelectItem>
            </SelectContent>
          </Select>
          <p class="text-xs text-muted-foreground" v-if="batches.length === 0 && !loadingBatches">
            No batches found for this drug. Create one first.
          </p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-2">
            <Label>Selling Price</Label>
            <Input type="number" v-model="form.selling_price" step="0.01" />
          </div>
          <div class="space-y-2">
            <Label>Initial Quantity</Label>
            <Input type="number" v-model="form.quantity_on_hand" />
          </div>
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
