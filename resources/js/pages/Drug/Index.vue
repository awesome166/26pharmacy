<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import CrudDialog from './CrudDialog.vue';
import axios from 'axios';

const drugs = ref([]);
const search = ref('');
const dialogRef = ref();

const fetchDrugs = async () => {
  const response = await axios.get('/app/drugs');
  drugs.value = response.data.data || response.data;
};

onMounted(fetchDrugs);

const openAddDrug = () => {
  dialogRef.value.openDialog();
};

const editDrug = (drug) => {
  // Implement edit logic (open dialog with drug data)
};

const deleteDrug = async (drugId) => {
  await axios.delete(`/api/v1/drugs/${drugId}`);
  fetchDrugs();
};
</script>

<template>
  <Head title="Drugs" />
  <AppLayout>
    <div class="flex flex-col gap-4 p-4">
      <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold">Drugs</h2>
        <Button @click="openAddDrug">Add Drug</Button>
      </div>
      <div class="rounded-xl border bg-card text-card-foreground shadow">
        <div class="p-4">
          <Input v-model="search" placeholder="Search drugs..." class="w-64 mb-4" />
          <table class="w-full text-sm">
            <thead>
              <tr>
                <th>Name</th>
                <th>Strength</th>
                <th>Regulatory Code</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="drug in filteredDrugs" :key="drug.drug_id">
                <td>{{ drug.name }}</td>
                <td>{{ drug.strength }}</td>
                <td>{{ drug.regulatory_code }}</td>
                <td>
                  <Button size="sm" variant="outline" @click="editDrug(drug)">Edit</Button>
                  <Button size="sm" variant="destructive" @click="deleteDrug(drug.drug_id)">Delete</Button>
                </td>
              </tr>
              <tr v-if="filteredDrugs.length === 0">
                <td colspan="4" class="text-center text-muted-foreground">No drugs found.</td>
              </tr>
            </tbody>
          </table>
        import { computed } from 'vue';
        // ...existing code...
        const filteredDrugs = computed(() => {
          if (!search.value) return drugs.value;
          return drugs.value.filter(drug =>
            drug.name?.toLowerCase().includes(search.value.toLowerCase()) ||
            drug.strength?.toLowerCase().includes(search.value.toLowerCase()) ||
            drug.regulatory_code?.toLowerCase().includes(search.value.toLowerCase())
          );
        });
        </div>
      </div>
      <CrudDialog ref="dialogRef" />
    </div>
  </AppLayout>
</template>
