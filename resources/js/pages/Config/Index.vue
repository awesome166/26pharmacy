<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useAccount } from '@/composables/useAccount';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Info } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps<{
  config: Record<string, any>;
  exists: boolean;
}>();

const account = useAccount();

const saving = ref(false);
const exists = ref(props.exists);

// Work directly with config data from server
const config = ref({ ...props.config });

watch(() => props.config, (newVal) => {
  config.value = { ...newVal };
}, { deep: true });

watch(() => props.exists, (newVal) => {
  exists.value = newVal;
});

onMounted(() => {
  router.reload({ only: ['config'] });
});

const saveconfig = async () => {
  saving.value = true;
  try {
    const payload = { ...config.value };

    // isPlatformAdmin is true when currentAccountId is NOT null (i.e. it IS a Tenant)
    // We want to prevent Tenants from sending system_* keys
    if (isPlatformAdmin.value) {
      Object.keys(payload).forEach((key) => {
        if (key.startsWith('system_')) {
          delete payload[key];
        }
      });
    }

    let res;
    if (exists.value) {
      // Update
      res = await axios.patch('/app/config', { settings: payload });
    } else {
      // First time create
      res = await axios.post('/app/config', { settings: payload });
      exists.value = true;
    }

    // Reload Inertia page to refresh shared props (auth.settings) and config
    // This ensures all components get the updated settings
    router.reload({
      only: ['auth', 'config'],
      onSuccess: () => {
        config.value = { ...props.config };
      },
    });

  } catch (e) {
    console.error('Failed to save config', e);
  } finally {
    saving.value = false;
  }
};



const isPlatformAdmin = computed(() => {
  return !!account.currentAccountId.value;
});
</script>

<template>

  <Head title="Configuration" />

  <AppLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
        System Configuration
      </h2>
    </template>

    <div class="py-12">
      <div class=" sm:px-6 lg:px-8">
        <div class="grid gap-6">
          <!-- {{ account.currentAccountId.value === null ? 'Admin' : account.currentAccountId.value }} -->
          <!-- Inventory Settings -->
          <Card v-if="isPlatformAdmin">
            <CardHeader>
              <CardTitle>Inventory & Stock </CardTitle>
              <CardDescription>Manage how stock is tracked and received.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-6">


              <div class="grid gap-3">
                <Label
                  class="hover:bg-accent/50 flex items-start gap-3 rounded-lg border p-3 has-[[aria-checked=true]]:border-blue-600 has-[[aria-checked=true]]:bg-blue-50 dark:has-[[aria-checked=true]]:border-blue-900 dark:has-[[aria-checked=true]]:bg-blue-950">
                  <Checkbox id="inventory_batch_mode" v-model="config.inventory_batch_mode"
                    class="data-[state=checked]:border-blue-600 data-[state=checked]:bg-blue-600 data-[state=checked]:text-white dark:data-[state=checked]:border-blue-700 dark:data-[state=checked]:bg-blue-700 mt-1" />
                  <div class="grid gap-1.5 font-normal">
                    <p class="text-sm leading-none font-medium">
                      Batch Adding Process
                    </p>
                    <p class="text-muted-foreground text-sm">
                      Enable two-step process: Create Batch -> Issue to Inventory.
                    </p>
                    <Alert class="mt-1">
                      <!-- <Info class="h-4 w-4" /> -->
                      <AlertTitle>How this works</AlertTitle>
                      <AlertDescription>
                        If enabled, you must first create a batch and then issue it. If disabled (Direct Mode), creating
                        a
                        batch
                        adds it to inventory immediately.
                      </AlertDescription>
                    </Alert>
                  </div>
                </Label>

              </div>

              <!-- Prevent Negative Stock -->
              <Label
                class="hover:bg-accent/50 flex items-start gap-3 rounded-lg border p-3 has-[[aria-checked=true]]:border-blue-600 has-[[aria-checked=true]]:bg-blue-50 dark:has-[[aria-checked=true]]:border-blue-900 dark:has-[[aria-checked=true]]:bg-blue-950">
                <Checkbox id="inventory_prevent_negative_stock" v-model="config.inventory_prevent_negative_stock"
                  class="data-[state=checked]:border-blue-600 data-[state=checked]:bg-blue-600 data-[state=checked]:text-white dark:data-[state=checked]:border-blue-700 dark:data-[state=checked]:bg-blue-700 mt-1" />
                <div class="grid gap-1.5 font-normal">
                  <p class="text-sm leading-none font-medium">
                    Prevent Negative Stock
                  </p>
                  <p class="text-muted-foreground text-sm">
                    Block sales if inventory quantity is insufficient.
                  </p>
                </div>
              </Label>

              <!-- Require Approval -->
              <Label
                class="hover:bg-accent/50 flex items-start gap-3 rounded-lg border p-3 has-[[aria-checked=true]]:border-blue-600 has-[[aria-checked=true]]:bg-blue-50 dark:has-[[aria-checked=true]]:border-blue-900 dark:has-[[aria-checked=true]]:bg-blue-950">
                <Checkbox id="inventory_require_approval_for_adjustments"
                  v-model="config.inventory_require_approval_for_adjustments"
                  class="data-[state=checked]:border-blue-600 data-[state=checked]:bg-blue-600 data-[state=checked]:text-white dark:data-[state=checked]:border-blue-700 dark:data-[state=checked]:bg-blue-700 mt-1" />
                <div class="grid gap-1.5 font-normal">
                  <p class="text-sm leading-none font-medium">
                    Require Approval for Adjustments
                  </p>
                  <p class="text-muted-foreground text-sm">
                    Manager approval needed for stock corrections.
                  </p>
                </div>
              </Label>
            </CardContent>
          </Card>

          <!-- Sales Settings -->
          <Card v-if="isPlatformAdmin">
            <CardHeader>
              <CardTitle>Sales & POS</CardTitle>
              <CardDescription>Configure point of sale behavior.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-6">
              <!-- Require Prescription -->
              <Label
                class="hover:bg-accent/50 flex items-start gap-3 rounded-lg border p-3 has-[[aria-checked=true]]:border-blue-600 has-[[aria-checked=true]]:bg-blue-50 dark:has-[[aria-checked=true]]:border-blue-900 dark:has-[[aria-checked=true]]:bg-blue-950">
                <Checkbox id="sales_require_prescription" v-model="config.sales_require_prescription"
                  class="data-[state=checked]:border-blue-600 data-[state=checked]:bg-blue-600 data-[state=checked]:text-white dark:data-[state=checked]:border-blue-700 dark:data-[state=checked]:bg-blue-700 mt-1" />
                <div class="grid gap-1.5 font-normal">
                  <p class="text-sm leading-none font-medium">
                    Require Prescription
                  </p>
                  <p class="text-muted-foreground text-sm">
                    Force prescription linkage for Rx drugs.
                  </p>
                </div>
              </Label>

              <!-- Loyalty Program -->
              <Label
                class="hover:bg-accent/50 flex items-start gap-3 rounded-lg border p-3 has-[[aria-checked=true]]:border-blue-600 has-[[aria-checked=true]]:bg-blue-50 dark:has-[[aria-checked=true]]:border-blue-900 dark:has-[[aria-checked=true]]:bg-blue-950">
                <Checkbox id="sales_enable_loyalty" v-model="config.sales_add_tax"
                  class="data-[state=checked]:border-blue-600 data-[state=checked]:bg-blue-600 data-[state=checked]:text-white dark:data-[state=checked]:border-blue-700 dark:data-[state=checked]:bg-blue-700 mt-1" />
                <div class="grid gap-1.5 font-normal">
                  <p class="text-sm leading-none font-medium">
                    Add Tax
                  </p>
                  <p class="text-muted-foreground text-sm">
                    Add taxes to sales.
                  </p>
                </div>
              </Label>
              <!-- Loyalty Program -->
              <Label
                class="hover:bg-accent/50 flex items-start gap-3 rounded-lg border p-3 has-[[aria-checked=true]]:border-blue-600 has-[[aria-checked=true]]:bg-blue-50 dark:has-[[aria-checked=true]]:border-blue-900 dark:has-[[aria-checked=true]]:bg-blue-950">
                <Checkbox id="sales_enable_loyalty" v-model="config.sales_enable_loyalty"
                  class="data-[state=checked]:border-blue-600 data-[state=checked]:bg-blue-600 data-[state=checked]:text-white dark:data-[state=checked]:border-blue-700 dark:data-[state=checked]:bg-blue-700 mt-1" />
                <div class="grid gap-1.5 font-normal">
                  <p class="text-sm leading-none font-medium">
                    Loyalty Program
                  </p>
                  <p class="text-muted-foreground text-sm">
                    Enable customer points accumulation.
                  </p>
                </div>
              </Label>

              <!-- Auto-Print Receipt -->
              <Label
                class="hover:bg-accent/50 flex items-start gap-3 rounded-lg border p-3 has-[[aria-checked=true]]:border-blue-600 has-[[aria-checked=true]]:bg-blue-50 dark:has-[[aria-checked=true]]:border-blue-900 dark:has-[[aria-checked=true]]:bg-blue-950">
                <Checkbox id="sales_print_auto_receipt" v-model="config.sales_print_auto_receipt"
                  class="data-[state=checked]:border-blue-600 data-[state=checked]:bg-blue-600 data-[state=checked]:text-white dark:data-[state=checked]:border-blue-700 dark:data-[state=checked]:bg-blue-700 mt-1" />
                <div class="grid gap-1.5 font-normal">
                  <p class="text-sm leading-none font-medium">
                    Auto-Print Receipt
                  </p>
                  <p class="text-muted-foreground text-sm">
                    Automatically trigger print dialog after sale.
                  </p>
                </div>
              </Label>
            </CardContent>
          </Card>

          <!-- System Settings -->
          <Card v-if="!isPlatformAdmin">
            <CardHeader>
              <CardTitle>System & Maintenance</CardTitle>
              <CardDescription>Advanced system controls.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-6">
              <!-- Maintenance Mode -->
              <Label
                class="hover:bg-accent/50 flex items-start gap-3 rounded-lg border p-3 has-[[aria-checked=true]]:border-blue-600 has-[[aria-checked=true]]:bg-blue-50 dark:has-[[aria-checked=true]]:border-blue-900 dark:has-[[aria-checked=true]]:bg-blue-950">
                <Checkbox id="system_maintenance_mode" v-model="config.system_maintenance_mode"
                  class="data-[state=checked]:border-blue-600 data-[state=checked]:bg-blue-600 data-[state=checked]:text-white dark:data-[state=checked]:border-blue-700 dark:data-[state=checked]:bg-blue-700 mt-1" />
                <div class="grid gap-1.5 font-normal">
                  <p class="text-sm leading-none font-medium text-destructive">
                    Maintenance Mode
                  </p>
                  <p class="text-muted-foreground text-sm">
                    Show maintenance page to non-admin users.
                  </p>
                </div>
              </Label>

              <!-- Debug Mode -->
              <Label
                class="hover:bg-accent/50 flex items-start gap-3 rounded-lg border p-3 has-[[aria-checked=true]]:border-blue-600 has-[[aria-checked=true]]:bg-blue-50 dark:has-[[aria-checked=true]]:border-blue-900 dark:has-[[aria-checked=true]]:bg-blue-950">
                <Checkbox id="system_debug_mode" v-model="config.system_debug_mode"
                  class="data-[state=checked]:border-blue-600 data-[state=checked]:bg-blue-600 data-[state=checked]:text-white dark:data-[state=checked]:border-blue-700 dark:data-[state=checked]:bg-blue-700 mt-1" />
                <div class="grid gap-1.5 font-normal">
                  <p class="text-sm leading-none font-medium">
                    Debug Mode
                  </p>
                  <p class="text-muted-foreground text-sm">
                    Show detailed error messages (Dev only).
                  </p>
                </div>
              </Label>
            </CardContent>
          </Card>

          <div class="flex justify-end pt-4 pb-12">
            <Button v-if="$can('settings.manage')" @click="saveconfig" :disabled="saving" size="lg">
              <span v-if="saving">Saving...</span>
              <span v-else>Save Configuration</span>
            </Button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
