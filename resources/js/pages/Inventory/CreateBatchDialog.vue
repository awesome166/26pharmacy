<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import axios from 'axios';

const open = ref(false);
const drugs = ref<any[]>([]);
const loadingDrugs = ref(false);

const form = useForm({
  drug_id: '',
  expiry_date: '',
  lot_number: '',
  manufacturer: '',
  cost_price: 0,
  quantity: 0,
  name: '' // Added as per platform migration requirement
});

const openDialog = (drugId: string = '') => {
  form.reset();
  form.drug_id = drugId;
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

const submit = () => {
  form.post('/app/batches', {
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
        <DialogTitle>Create New Batch</DialogTitle>
        <DialogDescription>
          Register a new manufacturer batch for a drug.
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
      </div>
      <DialogFooter>
        <Button variant="ghost" @click="open = false">Cancel</Button>
        <Button type="submit" @click="submit" :disabled="form.processing || !form.drug_id">
          Create Batch
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
