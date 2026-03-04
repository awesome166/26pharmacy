<template>
  <Dialog :open="open" @update:open="val => !val && $emit('close')">
    <DialogContent class="sm:max-w-[500px]">
      <DialogHeader>
        <DialogTitle>{{ isEditing ? 'Edit Account' : 'New Account' }}</DialogTitle>
        <DialogDescription>
          {{ isEditing ? 'Update account details and hierarchy.' : 'Create a new chart of account entry.' }}
        </DialogDescription>
      </DialogHeader>

      <form @submit.prevent="submit" class="space-y-4 py-4">
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-2">
            <Label htmlFor="code">Account Code <span class="text-destructive">*</span></Label>
            <Input id="code" v-model="form.code" placeholder="e.g. 1001" :disabled="isEditing" />
            <span v-if="form.errors.code" class="text-xs text-destructive">{{ form.errors.code }}</span>
          </div>

          <div class="space-y-2">
            <Label htmlFor="type">Type <span class="text-destructive">*</span></Label>
            <Select v-model="form.type" :disabled="isEditing">
              <SelectTrigger>
                <SelectValue placeholder="Select type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Asset">Asset</SelectItem>
                <SelectItem value="Liability">Liability</SelectItem>
                <SelectItem value="Equity">Equity</SelectItem>
                <SelectItem value="Revenue">Revenue</SelectItem>
                <SelectItem value="Expense">Expense</SelectItem>
              </SelectContent>
            </Select>
            <span v-if="form.errors.type" class="text-xs text-destructive">{{ form.errors.type }}</span>
          </div>
        </div>

        <div class="space-y-2">
          <Label htmlFor="name">Account Name <span class="text-destructive">*</span></Label>
          <Input id="name" v-model="form.name" placeholder="e.g. Petty Cash" />
          <span v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</span>
        </div>

        <div class="space-y-2">
          <Label htmlFor="parent">Parent Account (Optional)</Label>
          <Select v-model="form.parent_id">
            <SelectTrigger>
              <SelectValue placeholder="Select parent" />
            </SelectTrigger>
            <SelectContent class="max-h-[200px]">
              <SelectItem value="">None (Top Level)</SelectItem>
              <!-- Flattened list needed here really, or filter by type -->
              <SelectItem v-for="acc in parentOptions" :key="acc.id" :value="acc.id" :disabled="acc.id === form.id">
                {{ acc.code }} - {{ acc.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-2">
          <Label htmlFor="description">Description</Label>
          <Textarea id="description" v-model="form.description" placeholder="Additional details..." />
        </div>

        <div class="flex items-center space-x-2 pt-2">
          <Checkbox id="is_group" :checked="form.is_group" @update:checked="val => form.is_group = val" />
          <Label htmlFor="is_group" class="font-normal cursor-pointer">
            Is Group? <span class="text-xs text-muted-foreground">(Cannot have transactions directly)</span>
          </Label>
        </div>
      </form>

      <DialogFooter>
        <Button variant="outline" @click="$emit('close')">Cancel</Button>
        <Button type="submit" @click="submit" :disabled="form.processing">
          {{ isEditing ? 'Save Changes' : 'Create Account' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const props = defineProps({
  open: Boolean,
  account: Object,
  accountsList: Array // For parent selection
});

const emit = defineEmits(['close', 'success']);

const isEditing = computed(() => !!props.account);

const form = useForm({
  id: props.account?.id || null,
  code: props.account?.code || '',
  name: props.account?.name || '',
  type: props.account?.type || '',
  parent_id: props.account?.parent_id || '',
  description: props.account?.description || '',
  is_group: props.account?.is_group || false,
});

watch(
  () => props.account,
  (account) => {
    form.id = account?.id || null;
    form.code = account?.code || '';
    form.name = account?.name || '';
    form.type = account?.type || '';
    form.parent_id = account?.parent_id || '';
    form.description = account?.description || '';
    form.is_group = account?.is_group || false;
    form.clearErrors();
  },
  { immediate: true },
);

// Filter accounts that can be parents (same type, usually)
const parentOptions = computed(() => {
  return props.accountsList.filter(a => a.id !== form.id); // Simple filter to prevent self-parenting
});

const submit = () => {
  const payload = {
    ...form.data(),
    parent_id: form.parent_id || null,
  };

  if (isEditing.value) {
    form.transform(() => payload).put(route('accounting.accounts.update', props.account.id), {
      onSuccess: () => {
        emit('success');
        emit('close');
      },
    });
  } else {
    form.transform(() => payload).post(route('accounting.accounts.store'), {
      onSuccess: () => {
        emit('success');
        emit('close');
      },
    });
  }
};
</script>
