<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ToastContainer from '@/components/ToastContainer.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
import { SyncManager } from '@/services/SyncManager';
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <div class="flex items-center justify-between px-4 py-2">
                <AppSidebarHeader :breadcrumbs="breadcrumbs" class="flex-1" />
                <!-- Online Status Indicator -->
                <div class="flex items-center gap-2 ml-4">
                    <div class="relative flex h-3 w-3">
                        <span v-if="SyncManager.isOnline.value"
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3"
                            :class="SyncManager.isOnline.value ? 'bg-green-500' : 'bg-red-500'"></span>
                    </div>
                </div>
            </div>
            <slot />
        </AppContent>
    </AppShell>
    <ToastContainer />
</template>
