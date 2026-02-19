<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { SyncManager } from '@/services/SyncManager';
import { Monitor, Wifi, WifiOff } from 'lucide-vue-next';

const breadcrumbs = [
  { title: 'Settings', href: '/settings' },
  { title: 'Online Devices', href: '/settings/online-devices' },
];
</script>

<template>

  <Head title="Online Devices" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-6 max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Online Devices</h2>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Real-time list of devices connected to the sync network.
          </p>
        </div>
        <div class="flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium"
          :class="SyncManager.isOnline.value ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
          <component :is="SyncManager.isOnline.value ? Wifi : WifiOff" class="w-4 h-4" />
          <span>{{ SyncManager.isOnline.value ? 'Connected' : 'Disconnected' }}</span>
        </div>
      </div>

      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="device in SyncManager.onlineDevices.value" :key="device.id"
          class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700 p-6 flex flex-col gap-4 relative transition-all hover:shadow-md">

          <div class="absolute top-4 right-4">
            <span class="relative flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
            </span>
          </div>

          <div class="flex items-start gap-4">
            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-blue-600 dark:text-blue-400">
              <Monitor class="w-8 h-8" />
            </div>
            <div>
              <h3 class="font-semibold text-gray-900 dark:text-white">{{ device.name }}</h3>
              <p class="text-xs text-gray-500 dark:text-gray-400 break-all">{{ device.email }}</p>
            </div>
          </div>

          <div class="border-t border-gray-100 dark:border-gray-700 pt-4 mt-auto">
            <div class="grid grid-cols-2 gap-2 text-xs">
              <div>
                <span class="text-gray-400 block">Role</span>
                <span class="font-medium text-gray-700 dark:text-gray-300 uppercase">{{ device.role || 'Unknown'
                }}</span>
              </div>
              <div>
                <span class="text-gray-400 block">Client ID</span>
                <span class="font-medium text-gray-700 dark:text-gray-300 font-mono truncate"
                  :title="device.client_id">{{ device.client_id ? device.client_id.substring(0, 8) + '...' : '-'
                  }}</span>
              </div>
            </div>
            <div class="mt-3 text-xs text-gray-500">
              <span class="truncate block" :title="device.device">{{ device.device }}</span>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="SyncManager.onlineDevices.value.length === 0" class="col-span-full text-center py-12 text-gray-400">
          <WifiOff class="w-12 h-12 mx-auto mb-4 opacity-50" />
          <p>No devices detected via Presence Channel.</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
