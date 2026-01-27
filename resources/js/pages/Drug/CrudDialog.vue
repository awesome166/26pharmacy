<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import { DRUG_CATEGORIES } from '@/constants/drugCategories';

const open = ref(false);
const editingDrug = ref<any>(null);

const form = useForm({
  name: '',
  strength: '',
  regulatory_code: '',
  form: '',
  route: '',
  manufacturer: '',
  supplier: '',
  is_prescription: false,
  is_controlled: false,
  is_narcotic: false,
  drug_class: '',
  storage_conditions: '',
  description: '',
  side_effects: '',
  contraindications: '',
  schedule: ''
});

const openDialog = (drug: any = null) => {
  editingDrug.value = drug;
  if (drug) {
    form.name = drug.name || '';
    form.strength = drug.strength || '';
    form.regulatory_code = drug.regulatory_code || '';
    form.form = drug.form || '';
    form.route = drug.route || '';
    form.manufacturer = drug.manufacturer || '';
    form.supplier = drug.supplier || '';
    form.is_prescription = Boolean(drug.is_prescription);
    form.is_controlled = Boolean(drug.is_controlled);
    form.is_narcotic = Boolean(drug.is_narcotic);
    form.drug_class = drug.drug_class || '';
    form.storage_conditions = drug.storage_conditions || '';
    form.description = drug.description || '';
    form.side_effects = drug.side_effects || '';
    form.contraindications = drug.contraindications || '';
    form.schedule = drug.schedule || '';
  } else {
    form.reset();
  }
  open.value = true;
};

const closeDialog = () => {
  open.value = false;
  editingDrug.value = null;
  form.reset();
};

const submitDrug = () => {
  if (editingDrug.value) {
    form.put(`/app/drugs/${editingDrug.value.drug_id}`, {
      onSuccess: () => closeDialog()
    });
  } else {
    form.post('/app/drugs', {
      onSuccess: () => closeDialog()
    });
  }
};

defineExpose({
  openDialog
});
</script>

<template>
  <Dialog :open="open" @update:open="open = $event">
    <DialogContent class="sm:max-w-[800px] overflow-y-auto max-h-[90vh]">
      <DialogHeader>
        <DialogTitle>{{ editingDrug ? 'Edit Drug' : 'Add New Drug' }}</DialogTitle>
        <DialogDescription>
          {{ editingDrug ? 'Update the details for this drug.' : 'Enter the details for the new drug entry.' }}
        </DialogDescription>
      </DialogHeader>

      <div class="grid gap-6 py-4">
        <!-- Basic Info -->
        <div class="grid grid-cols-2 gap-4">
          <div class="grid gap-2">
            <Label>Name</Label>
            <Input v-model="form.name" placeholder="Drug Name" />
          </div>
          <div class="grid gap-2">
            <Label>Strength</Label>
            <Input v-model="form.strength" placeholder="e.g. 500mg" />
          </div>
        </div>

        <!-- Categorization -->
        <div class="grid grid-cols-2 gap-4">
          <div class="grid gap-2 overflow-hidden">
            <Label>Regulatory Code</Label>
            <Select v-model="form.regulatory_code">
              <SelectTrigger class="w-full overflow-hidden">
                <SelectValue placeholder="Select Category" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="cat in DRUG_CATEGORIES" :key="cat.code" :value="cat.code">
                  <div class="flex flex-col gap-1 py-1">
                    <span class="font-bold">{{ cat.code }} - {{ cat.category_name }}</span>
                    <span class="text-xs text-muted-foreground line-clamp-1">{{ cat.description }}</span>
                  </div>
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="grid gap-2">
            <Label>Drug Class</Label>
            <Input v-model="form.drug_class" placeholder="e.g. Analgesic" />
          </div>
        </div>

        <!-- Form & Route -->
        <div class="grid grid-cols-2 gap-4">
          <div class="grid gap-2">
            <Label>Form</Label>
            <Input v-model="form.form" placeholder="e.g. Tablet, Syrup" />
          </div>
          <div class="grid gap-2">
            <Label>Route</Label>
            <Input v-model="form.route" placeholder="e.g. Oral, IV" />
          </div>
        </div>

        <!-- Details -->
        <div class="grid grid-cols-2 gap-4">
          <div class="grid gap-2">
            <Label>Manufacturer</Label>
            <Input v-model="form.manufacturer" placeholder="Manufacturer Name" />
          </div>
          <div class="grid gap-2">
            <Label>Supplier</Label>
            <Input v-model="form.supplier" placeholder="Supplier Name" />
          </div>
        </div>

        <!-- Additional Info -->
        <div class="grid grid-cols-2 gap-4">
          <div class="grid gap-2">
            <Label>Schedule</Label>
            <Input v-model="form.schedule" placeholder="e.g. Schedule II" />
          </div>
          <div class="grid gap-2">
            <Label>Storage Conditions</Label>
            <Input v-model="form.storage_conditions" placeholder="e.g. Store below 25°C" />
          </div>
        </div>

        <!-- Long Text Fields -->
        <div class="grid gap-4">
          <div class="grid gap-2">
            <Label>Description</Label>
            <Textarea v-model="form.description" placeholder="Product description" />
          </div>
          <div class="grid gap-2">
            <Label>Side Effects</Label>
            <Textarea v-model="form.side_effects" placeholder="Common side effects" />
          </div>
          <div class="grid gap-2">
            <Label>Contraindications</Label>
            <Textarea v-model="form.contraindications" placeholder="When not to use" />
          </div>
        </div>

      </div>

      <DialogFooter>
        <Button variant="ghost" @click="closeDialog">Cancel</Button>
        <Button type="submit" @click="submitDrug" :disabled="form.processing">
          {{ editingDrug ? 'Save Changes' : 'Create Drug' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
