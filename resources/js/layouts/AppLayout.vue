<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import AccountSelectionModal from '@/components/AccountSelectionModal.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

import { onMounted } from 'vue';
import { SyncManager } from '@/services/SyncManager';

onMounted(() => {
    const accountId = localStorage.getItem('current_account_id');
    if (accountId) {
        SyncManager.initialize(accountId);
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
    <AccountSelectionModal />
</template>
