<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, ShoppingCart, Package, FileText, ClipboardList } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: 'dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Store (POS)',
        href: '/app/store',
        icon: ShoppingCart,
    },
    {
        title: 'Sales History',
        href: '/app/sales',
        icon: LayoutGrid, // Or another icon
    },
    {
        title: 'Inventory',
        href: '/app/inventory',
        icon: Package,
    },
    {
        title: 'Reports',
        href: '/app/reports/daily-sales',
        icon: FileText,
    },
    {
        title: 'Audit Trail',
        href: '/app/audit-trail',
        icon: ClipboardList,
    },
    {
        title: 'Drugs',
        href: '/app/drugs',
        icon: BookOpen,
    },
];

const footerNavItems: NavItem[] = [
    // {
    //     title: 'Github Repo',
    //     href: 'https://github.com/laravel/vue-starter-kit',
    //     icon: Folder,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits#vue',
    //     icon: BookOpen,
    // },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="bg-slate-900 text-white">
        <SidebarHeader class="bg-slate-900 text-white">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="bg-slate-900 text-white">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="bg-slate-900 text-white">
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
