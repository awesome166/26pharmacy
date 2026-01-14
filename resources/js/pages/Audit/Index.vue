<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps({
    audits: Object,
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Audit Trail', href: '/audit-trail' },
];

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString() + ' ' + new Date(dateString).toLocaleTimeString();
};
</script>

<template>

    <Head title="Audit Trail" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Audit Trail</h2>
                <p class="text-muted-foreground">System-wide immutable event log.</p>
            </div>

            <div class="rounded-xl border bg-card text-card-foreground shadow">
                <div class="p-0">
                    <div class="relative w-full overflow-auto">
                        <table class="w-full caption-bottom text-sm">
                            <thead class="[&_tr]:border-b">
                                <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                                        Timestamp</th>
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                                        Entity</th>
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                                        Action</th>
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">User
                                    </th>
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                                        Metadata</th>
                                </tr>
                            </thead>
                            <tbody class="[&_tr:last-child]:border-0">
                                <tr v-for="audit in audits.data" :key="audit.audit_id"
                                    class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                    <td class="p-4 align-middle">{{ formatDate(audit.timestamp) }}</td>
                                    <td class="p-4 align-middle font-medium">{{ audit.entity_type }}</td>
                                    <td class="p-4 align-middle uppercase tracking-wider text-xs font-bold">{{
                                        audit.action }}</td>
                                    <td class="p-4 align-middle text-muted-foreground">{{ audit.actor_user_id ||
                                        'System' }}</td>
                                    <td class="p-4 align-middle text-xs font-mono text-muted-foreground">
                                        {{ JSON.stringify(audit.metadata).substring(0, 50) }}...
                                    </td>
                                </tr>
                                <tr v-if="audits.data.length === 0">
                                    <td colspan="5" class="p-4 text-center text-muted-foreground">
                                        No audit logs found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination (Simple Previous/Next if needed, assuming standard Laravel links) -->
                <div class="flex items-center justify-end p-4 gap-2" v-if="audits.links && audits.links.length > 3">
                    <!-- Reuse pagination logic from other pages -->
                </div>
            </div>
        </div>
    </AppLayout>
</template>
