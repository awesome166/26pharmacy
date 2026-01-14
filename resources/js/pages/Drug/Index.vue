<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import CrudDialog from './CrudDialog.vue';
import { debounce } from 'lodash';

const props = defineProps({
  data: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const dialogRef = ref();

watch(search, debounce((value) => {
  router.get('/app/drugs', { search: value }, {
    preserveState: true,
    replace: true,
  });
}, 300));

const openAddDrug = () => {
  dialogRef.value.openDialog();
};

const editDrug = (drug) => {
  dialogRef.value.openDialog(drug);
};

const deleteDrug = async (drugId) => {
  if (confirm('Are you sure you want to delete this drug?')) {
    router.delete(`/app/drugs/${drugId}`);
  }
};

const drugs = computed(() => props.data?.data || []);
</script>

<template>

  <Head title="Drugs" />
  <AppLayout>
    <div class="flex flex-col gap-4 p-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-bold tracking-tight">Drugs</h2>
          <p class="text-muted-foreground">Manage the pharmacy drug catalog.</p>
        </div>
        <Button @click="openAddDrug">Add Drug</Button>
      </div>

      <div class="rounded-xl border bg-card text-card-foreground shadow">
        <div class="p-4">
          <Input v-model="search" placeholder="Search drugs..." class="w-64 mb-4" />

          <div class="relative w-full overflow-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b transition-colors hover:bg-muted/50">
                  <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Name</th>
                  <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Strength</th>
                  <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Regulatory Code</th>
                  <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="drug in drugs" :key="drug.drug_id" class="border-b transition-colors hover:bg-muted/50">
                  <td class="p-4 align-middle font-medium">{{ drug.name }}</td>
                  <td class="p-4 align-middle">{{ drug.strength }}</td>
                  <td class="p-4 align-middle">{{ drug.regulatory_code }}</td>
                  <td class="p-4 align-middle text-right flex justify-end gap-2">
                    <Button size="sm" variant="outline" @click="editDrug(drug)">Edit</Button>
                    <Button size="sm" variant="destructive" @click="deleteDrug(drug.drug_id)">Delete</Button>
                  </td>
                </tr>
                <tr v-if="drugs.length === 0">
                  <td colspan="4" class="p-4 text-center text-muted-foreground">No drugs found.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="flex items-center justify-end mt-4 gap-2" v-if="data.links.length > 3">
            <template v-for="(link, key) in data.links" :key="key">
              <Button v-if="link.url" variant="outline" size="sm" :disabled="link.active" as-child>
                <a :href="link.url" v-html="link.label"></a>
              </Button>
              <span v-else v-html="link.label" class="px-2 text-muted-foreground"></span>
            </template>
          </div>
        </div>
      </div>
      <CrudDialog ref="dialogRef" />
    </div>
  </AppLayout>
</template>
