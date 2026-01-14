<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const open = ref(false);
const form = useForm({
  name: '',
  strength: '',
  regulatory_code: ''
});

const openDialog = () => {
  open.value = true;
};

const closeDialog = () => {
  open.value = false;
  form.reset();
};

const submitDrug = () => {
  form.post('/api/v1/drugs', {
    onSuccess: () => {
      closeDialog();
    }
  });
};
</script>

<template>
  <Dialog :open="open" @update:open="open = $event">
    <DialogContent class="sm:max-w-[425px]">
      <DialogHeader>
        <DialogTitle>Add New Drug</DialogTitle>
        <DialogDescription>
          Enter drug details below.
        </DialogDescription>
      </DialogHeader>
      <div class="grid gap-4 py-4">
        <div class="grid grid-cols-4 items-center gap-4">
          <Label class="text-right">Name</Label>
          <Input v-model="form.name" class="col-span-3" placeholder="Drug Name" />
        </div>
        <div class="grid grid-cols-4 items-center gap-4">
          <Label class="text-right">Strength</Label>
          <Input v-model="form.strength" class="col-span-3" placeholder="e.g. 10mg" />
        </div>
        <div class="grid grid-cols-4 items-center gap-4">
          <Label class="text-right">Regulatory Code</Label>
          <Input v-model="form.regulatory_code" class="col-span-3" placeholder="e.g. RC-1234" />
        </div>
      </div>
      <DialogFooter>
        <Button type="submit" @click="submitDrug" :disabled="form.processing">Save Drug</Button>
        <Button variant="ghost" @click="closeDialog">Cancel</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
