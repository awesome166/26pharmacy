<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { debounce } from 'lodash';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Edit, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

const props = defineProps({
  customers: Object,
  filters: Object,
});

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Customers', href: '/app/customers' },
];

const search = ref(props.filters?.search || '');
const showUpdateModal = ref(false);
const selectedCustomer = ref<any>(null);
const form = ref({
  name: '',
  phone: '',
  email: '',
  dob: '',
});

watch(search, debounce((value: string) => {
  router.get('/app/customers', { search: value }, { preserveState: true, preserveScroll: true, replace: true });
}, 300));

const openUpdateModal = (customer: any) => {
  selectedCustomer.value = customer;
  form.value = {
    name: customer.name || '',
    phone: customer.phone || '',
    email: customer.email || '',
    dob: customer.dob || '',
  };
  showUpdateModal.value = true;
};

const closeModal = () => {
  showUpdateModal.value = false;
  selectedCustomer.value = null;
};

const updateCustomer = () => {
  if (!selectedCustomer.value) return;
  router.patch(`/app/customers/${selectedCustomer.value.id}`, form.value, {
    preserveScroll: true,
    onSuccess: () => closeModal(),
  });
};

const deleteCustomer = (customer: any) => {
  if (customer.sales_count > 0) {
    alert('This customer cannot be deleted because they already purchased items.');
    return;
  }

  if (!confirm('Delete this customer?')) return;

  router.delete(`/app/customers/${customer.id}`, {
    preserveScroll: true,
  });
};

const formatDate = (val: string | null) => {
  if (!val) return 'N/A';
  return new Date(val).toLocaleDateString();
};
</script>

<template>
  <Head title="Customers" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-col gap-4 p-4">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight">Customers</h1>
      </div>

      <div class="max-w-sm">
        <Input v-model="search" placeholder="Search by name, phone, or email..." />
      </div>

      <div class="rounded-md border">
        <div class="relative w-full overflow-auto">
          <table class="w-full caption-bottom text-sm">
            <thead class="[&_tr]:border-b">
              <tr class="border-b transition-colors">
                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Name</th>
                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Phone</th>
                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Email</th>
                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">DOB</th>
                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Purchases</th>
                <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="customer in customers.data" :key="customer.id" class="border-b transition-colors hover:bg-muted/50">
                <td class="p-4 align-middle font-medium">{{ customer.name || 'N/A' }}</td>
                <td class="p-4 align-middle">{{ customer.phone || 'N/A' }}</td>
                <td class="p-4 align-middle">{{ customer.email || 'N/A' }}</td>
                <td class="p-4 align-middle">{{ formatDate(customer.dob) }}</td>
                <td class="p-4 align-middle">{{ customer.sales_count }}</td>
                <td class="p-4 align-middle text-right">
                  <Button variant="ghost" size="icon" @click="openUpdateModal(customer)">
                    <Edit class="h-4 w-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    class="text-destructive"
                    :disabled="customer.sales_count > 0"
                    @click="deleteCustomer(customer)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </td>
              </tr>
              <tr v-if="customers.data.length === 0">
                <td colspan="6" class="p-4 text-center text-muted-foreground">No customers found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 py-2" v-if="customers.links?.length > 3">
        <Button variant="outline" size="sm" :disabled="!customers.prev_page_url" @click="router.get(customers.prev_page_url)">
          Previous
        </Button>
        <Button variant="outline" size="sm" :disabled="!customers.next_page_url" @click="router.get(customers.next_page_url)">
          Next
        </Button>
      </div>
    </div>

    <Dialog :open="showUpdateModal" @update:open="showUpdateModal = $event">
      <DialogContent class="sm:max-w-[460px]">
        <DialogHeader>
          <DialogTitle>Update Customer</DialogTitle>
          <DialogDescription>Update customer details. New info will be used for future sales.</DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2">
          <div>
            <Label for="name">Name</Label>
            <Input id="name" v-model="form.name" class="mt-1" />
          </div>
          <div>
            <Label for="phone">Phone Number</Label>
            <Input id="phone" v-model="form.phone" class="mt-1" required />
          </div>
          <div>
            <Label for="email">Email</Label>
            <Input id="email" v-model="form.email" type="email" class="mt-1" />
          </div>
          <div>
            <Label for="dob">Date of Birth</Label>
            <Input id="dob" v-model="form.dob" type="date" class="mt-1" />
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" @click="closeModal">Cancel</Button>
          <Button @click="updateCustomer">Update Customer</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
