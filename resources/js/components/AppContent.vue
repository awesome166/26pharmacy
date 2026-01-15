<script setup lang="ts">
import { SidebarInset } from '@/components/ui/sidebar';
import { computed } from 'vue';

interface Props {
    variant?: 'header' | 'sidebar';
    class?: string;
}

const props = defineProps<Props>();
const className = computed(() => props.class);
</script>

<template>
    <SidebarInset v-if="props.variant === 'sidebar'" :class="className"
        class="m-0 p-0 md:peer-data-[variant=inset]:m-0 md:peer-data-[variant=inset]:p-0">
        <slot />
    </SidebarInset>
    <main v-else
        class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl m-0 p-0 md:peer-data-[variant=inset]:m-0 md:peer-data-[variant=inset]:p-0"
        :class="className">
        <slot />
    </main>
</template>
