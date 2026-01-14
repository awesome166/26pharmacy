<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const open = ref(false);
const editingDrug = ref<any>(null);

const form = useForm({
  name: '',
  strength: '',
  regulatory_code: ''
});

const openDialog = (drug: any = null) => {
  editingDrug.value = drug;
  if (drug) {
    form.name = drug.name;
    form.strength = drug.strength;
    form.regulatory_code = drug.regulatory_code;
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
    <DialogContent class="sm:max-w-[425px]">
      <DialogHeader>
        <DialogTitle>{{ editingDrug ? 'Edit Drug' : 'Add New Drug' }}</DialogTitle>
        <DialogDescription>
          {{ editingDrug ? 'Update the details for this drug.' : 'Enter the details for the new drug entry.' }}
        </DialogDescription>
      </DialogHeader>
      <div class="grid gap-4 py-4">
        <div class="grid grid-cols-4 items-center gap-4">
          <Label class="text-right">Name</Label>
          <Input v-model="form.name" class="col-span-3" placeholder="Drug Name" />
        </div>
        <div class="grid grid-cols-4 items-center gap-4">
          <Label class="text-right">Strength</Label>
          <Input v-model="form.strength" class="col-span-3" placeholder="e.g. 500mg" />
        </div>
        <div class="grid grid-cols-4 items-center gap-4">
          <Label class="text-right">Code</Label>
          <Input v-model="form.regulatory_code" class="col-span-3" placeholder="Regulatory Code" />
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
