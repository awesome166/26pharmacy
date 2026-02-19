<template>
  <Dialog :open="open" @update:open="val => !val && $emit('close')">
    <DialogContent class="sm:max-w-4xl max-h-[90vh] overflow-y-auto">
      <DialogHeader>
        <DialogTitle>New Journal Entry</DialogTitle>
        <DialogDescription>Create a manual double-entry record.</DialogDescription>
      </DialogHeader>

      <form @submit.prevent="submit" class="space-y-4 py-4">
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-2">
            <Label>Date <span class="text-destructive">*</span></Label>
            <Input type="date" v-model="form.date" />
            <span v-if="form.errors.date" class="text-xs text-destructive">{{ form.errors.date }}</span>
          </div>
          <div class="space-y-2">
            <Label>Reference</Label>
            <Input v-model="form.reference" placeholder="e.g. INV-2024-001" />
          </div>
        </div>

        <div class="space-y-2">
          <Label>Description <span class="text-destructive">*</span></Label>
          <Input v-model="form.description" placeholder="Brief description of the transaction" />
          <span v-if="form.errors.description" class="text-xs text-destructive">{{ form.errors.description }}</span>
        </div>

        <div class="space-y-2 border rounded-md p-4 bg-muted/20">
          <div class="flex justify-between items-center mb-2">
            <Label class="text-base font-semibold">Line Items</Label>
            <Button type="button" size="sm" variant="outline" @click="addLineItem">
              <i class="fas fa-plus mr-1"></i> Add Line
            </Button>
          </div>

          <div class="space-y-3">
            <div v-for="(item, index) in form.details" :key="index" class="grid grid-cols-12 gap-2 items-end group">
              <div class="col-span-1 text-center py-2 text-muted-foreground text-xs font-mono">{{ index + 1 }}</div>
              <div class="col-span-5">
                <Label v-if="index === 0" class="text-xs mb-1 block">Account</Label>
                <select v-model="item.chart_of_account_id"
                  class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50">
                  <option value="" disabled>Select Account</option>
                  <optgroup label="Assets">
                    <option v-for="acc in accountOptions['Asset']" :key="acc.id" :value="acc.id">{{ acc.code }} - {{
                      acc.name }}</option>
                  </optgroup>
                  <optgroup label="Liabilities">
                    <option v-for="acc in accountOptions['Liability']" :key="acc.id" :value="acc.id">{{ acc.code }} - {{
                      acc.name }}</option>
                  </optgroup>
                  <optgroup label="Equity">
                    <option v-for="acc in accountOptions['Equity']" :key="acc.id" :value="acc.id">{{ acc.code }} - {{
                      acc.name }}</option>
                  </optgroup>
                  <optgroup label="Revenue">
                    <option v-for="acc in accountOptions['Revenue']" :key="acc.id" :value="acc.id">{{ acc.code }} - {{
                      acc.name }}</option>
                  </optgroup>
                  <optgroup label="Expenses">
                    <option v-for="acc in accountOptions['Expense']" :key="acc.id" :value="acc.id">{{ acc.code }} - {{
                      acc.name }}</option>
                  </optgroup>
                </select>
              </div>
              <div class="col-span-2">
                <Label v-if="index === 0" class="text-xs mb-1 block">Debit</Label>
                <Input type="number" step="0.01" min="0" v-model="item.debit" @input="item.credit = 0"
                  class="text-right" />
              </div>
              <div class="col-span-2">
                <Label v-if="index === 0" class="text-xs mb-1 block">Credit</Label>
                <Input type="number" step="0.01" min="0" v-model="item.credit" @input="item.debit = 0"
                  class="text-right" />
              </div>
              <div class="col-span-1 text-center">
                <Button type="button" variant="ghost" size="icon"
                  class="h-9 w-9 text-muted-foreground hover:text-destructive" @click="removeLineItem(index)"
                  :disabled="form.details.length <= 2">
                  <i class="fas fa-trash-alt"></i>
                </Button>
              </div>
            </div>
          </div>

          <div class="border-t mt-4 pt-4 flex flex-col items-end gap-1">
            <div class="grid grid-cols-2 gap-8 text-sm w-[300px]">
              <span class="text-muted-foreground">Total Debit:</span>
              <span class="font-mono text-right" :class="{ 'text-destructive': !isBalanced }">{{
                formatCurrency(totalDebit) }}</span>

              <span class="text-muted-foreground">Total Credit:</span>
              <span class="font-mono text-right" :class="{ 'text-destructive': !isBalanced }">{{
                formatCurrency(totalCredit) }}</span>

              <span class="font-semibold pt-2 border-t mt-1">Difference:</span>
              <span class="font-mono text-right pt-2 border-t mt-1"
                :class="{ 'text-destructive': !isBalanced, 'text-green-600': isBalanced }">
                {{ formatCurrency(Math.abs(totalDebit - totalCredit)) }}
              </span>
            </div>
            <p v-if="!isBalanced" class="text-xs text-destructive mt-2">
              <i class="fas fa-exclamation-triangle mr-1"></i> Entry must be balanced before saving.
            </p>
          </div>
        </div>
      </form>

      <DialogFooter>
        <Button variant="outline" @click="$emit('close')">Cancel</Button>
        <Button type="submit" @click="submit" :disabled="form.processing || !isBalanced">Create Journal Entry</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';

const props = defineProps({
  open: Boolean,
  accounts: Array
});

const emit = defineEmits(['close', 'success']);

const form = useForm({
  date: new Date().toISOString().substr(0, 10),
  description: '',
  reference: '',
  details: [
    { chart_of_account_id: '', debit: 0, credit: 0 },
    { chart_of_account_id: '', debit: 0, credit: 0 }
  ]
});

// Group accounts by type for easier selection
const accountOptions = computed(() => {
  const grouped = { 'Asset': [], 'Liability': [], 'Equity': [], 'Revenue': [], 'Expense': [] };
  props.accounts.forEach(acc => {
    if (grouped[acc.type]) grouped[acc.type].push(acc);
  });
  return grouped;
});

const addLineItem = () => {
  form.details.push({ chart_of_account_id: '', debit: 0, credit: 0 });
};

const removeLineItem = (index) => {
  form.details.splice(index, 1);
};

const totalDebit = computed(() => {
  return form.details.reduce((sum, item) => sum + Number(item.debit || 0), 0);
});

const totalCredit = computed(() => {
  return form.details.reduce((sum, item) => sum + Number(item.credit || 0), 0);
});

const isBalanced = computed(() => {
  return Math.abs(totalDebit.value - totalCredit.value) < 0.01 && totalDebit.value > 0;
});

const submit = () => {
  form.post(route('accounting.journal-entries.store'), {
    onSuccess: () => {
      emit('success');
      emit('close');
    }
  });
};

const formatCurrency = (val) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
</script>
