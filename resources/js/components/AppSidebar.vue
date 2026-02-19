<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import AccountSwitcher from '@/components/AccountSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarFooter,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    User,
    LayoutGrid,
    ShoppingCart,
    Package,
    FileText,
    ClipboardList,
    Settings,
    Archive,
    RotateCcw,
    Calculator, // For Accounting
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Accounting',
        href: '#', // Placeholder for parent
        icon: Calculator,
        isActive: usePage().url.startsWith('/accounting') || usePage().url.startsWith('/app/accounts'),
        items: [
            {
                title: 'Dashboard',
                href: '/accounting',
            },
            {
                title: 'Chart of Accounts',
                href: '/accounting/accounts',
            },
            {
                title: 'Journal Entries',
                href: '/accounting/journal-entries',
            },
            {
                title: 'Balance Sheet',
                href: '/accounting/reports/balance-sheet',
            },
            {
                title: 'Income Statement',
                href: '/accounting/reports/income-statement',
            },
        ],
    },
    {
        title: 'Store (POS)',
        href: '/app/store',
        icon: ShoppingCart,
    },
    {
        title: 'Sales Management',
        href: '#',
        icon: RotateCcw,
        isActive: usePage().url.startsWith('/sales') || usePage().url.startsWith('/app/sales'),
        items: [

            {
                title: 'Sales History',
                href: '/app/sales',
            },
            {
                title: 'Returns',
                href: '/app/returns',
            },
        ],
    },

    {
        title: 'Inventory',
        href: "#",
        icon: Package,
        items: [
            {
                title: 'Inventory',
                href: '/app/inventory',
            },

            {
                title: 'Batches',
                href: '/app/batches',
            },
            {
                title: 'Drugs',
                href: '/app/drugs',
            }
        ]


    },


    {
        title: 'Reports',
        href: '/app/reports/daily-sales',
        icon: FileText,
        items: [
            {
                title: 'Reports',
                href: '/app/reports/daily-sales',

            },
            {
                title: 'Audit Trail',
                href: '/app/audit-trail',
            }


        ]
    },

    {
        title: 'Settings',
        href: '#',
        icon: Settings,
        items: [
            {
                title: 'Users',
                href: '/app/users',
            },
            {
                title: 'Roles',
                href: '/app/roles',
            },
            {
                title: 'Configuration',
                href: '/app/config',
            },
            {
                title: 'Tax',
                href: '/app/taxes',
            },

            {
                title: 'Accounts',
                href: '/app/accounts',
            },
        ],
    },

];

const footerNavItems: NavItem[] = [];

const page = usePage();
const user = page.props.auth.user;
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
            <div class="p-4" v-if="user?.accounts && user.accounts.length > 1">
                <AccountSwitcher :accounts="user.accounts" />
            </div>

            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="bg-slate-900 text-white">
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
