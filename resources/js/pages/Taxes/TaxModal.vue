<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useForm } from '@inertiajs/vue3';
import { watch, ref } from 'vue';

interface TaxRate {
  id: string;
  tax_name: string;
  jurisdiction: string;
  percentage: number | string;
  minimum_taxable_amount?: number | string | null;
  maximum_taxable_amount?: number | string | null;
  calculation_order?: number | string;
  is_compound?: boolean;
  tax_type: string;
  effective_from: string;
  effective_to?: string | null;
  is_active: boolean;
  description?: string | null;
  applicable_categories?: string[] | null;
}

interface TaxForm {
  tax_name: string;
  jurisdiction: string;
  percentage: number;
  minimum_taxable_amount: number | string;
  maximum_taxable_amount: number | string;
  calculation_order: number;
  is_compound: boolean;
  tax_type: string;
  effective_from: string;
  effective_to: string;
  is_active: boolean;
  description: string;
  applicable_categories: string;
}

const props = defineProps<{ open: boolean; tax?: TaxRate | null }>();

const emit = defineEmits(['close']);

const form = useForm<TaxForm>({
  tax_name: '',
  jurisdiction: 'Default',
  percentage: 0,
  minimum_taxable_amount: '',
  maximum_taxable_amount: '',
  calculation_order: 0,
  is_compound: false,
  tax_type: 'sales',
  effective_from: new Date().toISOString().split('T')[0],
  effective_to: '',
  is_active: true,
  description: '',
  applicable_categories: '',
});

const isEditing = ref(false);

watch(() => props.open, (newVal) => {
  if (newVal) {
    isEditing.value = !!props.tax;
    if (props.tax) {
      form.tax_name = props.tax.tax_name;
      form.jurisdiction = props.tax.jurisdiction;
      form.percentage = parseFloat(String(props.tax.percentage));
      form.minimum_taxable_amount = props.tax.minimum_taxable_amount ?? '';
      form.maximum_taxable_amount = props.tax.maximum_taxable_amount ?? '';
      form.calculation_order = Number(props.tax.calculation_order || 0);
      form.is_compound = !!props.tax.is_compound;
      form.tax_type = props.tax.tax_type;
      form.effective_from = props.tax.effective_from;
      form.effective_to = props.tax.effective_to ?? '';
      form.is_active = !!props.tax.is_active;
      form.description = props.tax.description ?? '';
      // Convert array to comma-separated string
      form.applicable_categories = (props.tax.applicable_categories || []).join(', ');
    } else {
      form.reset();
      form.jurisdiction = 'Default'; // Default value
      form.percentage = 0;
      form.minimum_taxable_amount = '';
      form.maximum_taxable_amount = '';
      form.calculation_order = 0;
      form.is_compound = false;
      form.is_active = true;
      form.effective_from = new Date().toISOString().split('T')[0];
      form.applicable_categories = '';
    }
  }
});

const submit = () => {
  // Convert comma-separated string back to array
  const payload = {
    ...form.data(),
    applicable_categories: form.applicable_categories
      ? form.applicable_categories.split(',').map(s => s.trim()).filter(Boolean)
      : []
  };

  if (isEditing.value && props.tax) {
    form.transform(() => payload).put(`/app/taxes/${props.tax.id}`, {
      onSuccess: () => emit('close'),
    });
  } else {
    form.transform(() => payload).post('/app/taxes', {
      onSuccess: () => emit('close'),
    });
  }
};
</script>

<template>
  <Dialog :open="open" @update:open="$emit('close')">
    <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[560px]">
      <DialogHeader>
        <DialogTitle>{{ isEditing ? 'Edit Tax Rate' : 'Create Tax Rate' }}</DialogTitle>
        <DialogDescription>
          Configure the tax rate details.
        </DialogDescription>
      </DialogHeader>

      <form @submit.prevent="submit" class="grid gap-4 py-4">
        <div class="grid grid-cols-2 gap-4">
          <div class="grid gap-2">
            <Label for="tax_name">Tax Name</Label>
            <Input id="tax_name" v-model="form.tax_name" placeholder="e.g. VAT" required />
            <span v-if="form.errors.tax_name" class="text-xs text-red-500">{{ form.errors.tax_name }}</span>
          </div>
          <div class="grid gap-2">
            <Label for="jurisdiction">Jurisdiction</Label>
            <Input id="jurisdiction" v-model="form.jurisdiction" placeholder="e.g. CA, NY, National" required />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="grid gap-2">
            <Label for="minimum_taxable_amount">Bracket Minimum</Label>
            <Input id="minimum_taxable_amount" type="number" min="0" step="0.01" v-model="form.minimum_taxable_amount" placeholder="No minimum" />
            <span v-if="form.errors.minimum_taxable_amount" class="text-xs text-red-500">{{ form.errors.minimum_taxable_amount }}</span>
          </div>
          <div class="grid gap-2">
            <Label for="maximum_taxable_amount">Bracket Maximum</Label>
            <Input id="maximum_taxable_amount" type="number" min="0" step="0.01" v-model="form.maximum_taxable_amount" placeholder="No maximum" />
            <span v-if="form.errors.maximum_taxable_amount" class="text-xs text-red-500">{{ form.errors.maximum_taxable_amount }}</span>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="grid gap-2">
            <Label for="calculation_order">Calculation Order</Label>
            <Input id="calculation_order" type="number" min="0" v-model="form.calculation_order" />
          </div>
          <div class="flex items-end gap-2 pb-2">
            <Switch id="is_compound" :checked="form.is_compound" @update:checked="form.is_compound = $event" />
            <Label for="is_compound">Compound on prior taxes</Label>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="grid gap-2">
            <Label for="percentage">Rate (%)</Label>
            <Input id="percentage" type="number" step="0.01" v-model="form.percentage" required />
            <span v-if="form.errors.percentage" class="text-xs text-red-500">{{ form.errors.percentage }}</span>
          </div>
          <div class="grid gap-2">
            <Label for="tax_type">Type</Label>
            <Select v-model="form.tax_type">
              <SelectTrigger>
                <SelectValue placeholder="Select type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="sales">Sales</SelectItem>
                <SelectItem value="pharmacy">Pharmacy</SelectItem>
                <SelectItem value="service">Service</SelectItem>
                <SelectItem value="special">Special</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <div class="grid gap-2">
          <Label for="applicable_categories">Applicable Categories (Optional)</Label>
          <Input id="applicable_categories" v-model="form.applicable_categories"
            placeholder="e.g. antibiotic, narcotic (Leave empty for ALL)" />
          <p class="text-xs text-muted-foreground">Comma-separated list of drug classes. Leave empty to apply to
            everything.</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="grid gap-2">
            <Label for="effective_from">Effective From</Label>
            <Input id="effective_from" type="date" v-model="form.effective_from" required />
          </div>
          <div class="grid gap-2">
            <Label for="effective_to">Effective To (Optional)</Label>
            <Input id="effective_to" type="date" v-model="form.effective_to" />
          </div>
        </div>

        <div class="grid gap-2">
          <Label for="description">Description</Label>
          <Input id="description" v-model="form.description" />
        </div>

        <div class="flex items-center space-x-2">
          <Switch id="is_active" :checked="form.is_active" @update:checked="form.is_active = $event" />
          <Label for="is_active">Active</Label>
        </div>
      </form>

      <DialogFooter>
        <Button variant="outline" @click="$emit('close')">Cancel</Button>
        <Button type="submit" @click="submit" :disabled="form.processing">
          {{ isEditing ? 'Update' : 'Create' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
