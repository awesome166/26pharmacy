<script setup lang="ts">
import { SyncManager } from '@/services/SyncManager';
import { computed } from 'vue';

const statusLabel = computed(() => {
  if (SyncManager.isSyncing.value) return 'Syncing...';
  if (!SyncManager.isOnline.value) return 'Offline';
  if (SyncManager.syncStatus.value === 'error') return 'Sync failed';
  return 'Online';
});

const statusColor = computed(() => {
  if (SyncManager.isSyncing.value) return 'bg-yellow-400';
  if (!SyncManager.isOnline.value || SyncManager.syncStatus.value === 'error') return 'bg-red-500';
  return 'bg-green-500';
});

const lastSyncText = computed(() => {
  if (!SyncManager.lastSyncTime.value) return 'Never synced';
  const diff = Date.now() - SyncManager.lastSyncTime.value.getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'Just now';
  if (mins < 60) return `${mins}m ago`;
  const hours = Math.floor(mins / 60);
  return `${hours}h ago`;
});

async function handleSyncNow() {
  await SyncManager.pull();
  await SyncManager.push();
}
</script>

<template>
  <div class="flex items-center gap-3 px-3 py-2 text-xs text-muted-foreground border-t border-border">
    <button
      @click="handleSyncNow"
      :disabled="SyncManager.isSyncing.value || !SyncManager.isOnline.value"
      class="flex items-center gap-1.5 hover:text-foreground transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
      title="Sync now"
    >
      <div class="relative flex h-2 w-2">
        <span
          v-if="SyncManager.isOnline.value && !SyncManager.isSyncing.value"
          class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
          :class="statusColor"
        />
        <span
          class="relative inline-flex rounded-full h-2 w-2"
          :class="[statusColor, SyncManager.isSyncing.value ? 'animate-pulse' : '']"
        />
      </div>
      <span>{{ statusLabel }}</span>
    </button>
    <span class="text-[10px] text-muted-foreground/60">{{ lastSyncText }}</span>
  </div>
</template>
