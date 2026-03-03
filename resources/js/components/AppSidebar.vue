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
import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

function filterNavItems(items: NavItem[]): NavItem[] {
    return items
        .filter(item => item.show !== false)
        .map(item => {
            if (item.items) {
                const filteredChildren = filterNavItems(item.items);
                // Return item with filtered children, but only if it still has children
                // OR it didn't have children originally (which is handled by other paths).
                return { ...item, items: filteredChildren };
            }
            return item;
        })
        .filter(item => !item.items || item.items.length > 0);
}

const page = usePage();
const user = page.props.auth.user;

const { can } = usePermissions();

const rawMainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
        show: can('dashboard.view'),
    },
    {
        title: 'Accounting',
        href: '#', // Placeholder for parent
        icon: Calculator,
        isActive: usePage().url.startsWith('/accounting') || usePage().url.startsWith('/app/accounts'),
        show: can('financial.manage') || can('financial.view'),
        items: [
            {
                title: 'Dashboard',
                href: '/accounting',
                show: can('financial.view'),
            },
            {
                title: 'Chart of Accounts',
                href: '/accounting/accounts',
                show: can('financial.manage'),
            },
            {
                title: 'Journal Entries',
                href: '/accounting/journal-entries',
                show: can('financial.manage'),
            },
            {
                title: 'Balance Sheet',
                href: '/accounting/reports/balance-sheet',
                show: can('financial.view'),
            },
            {
                title: 'Income Statement',
                href: '/accounting/reports/income-statement',
                show: can('financial.view'),
            },
        ],
    },
    {
        title: 'Store (POS)',
        href: '/app/store',
        icon: ShoppingCart,
        show: can('pos.access'),
    },
    {
        title: 'Sales Management',
        href: '#',
        icon: RotateCcw,
        isActive: usePage().url.startsWith('/sales') || usePage().url.startsWith('/app/sales'),
        show: can('sales.process') || can('returns.manage'),
        items: [

            {
                title: 'Sales History',
                href: '/app/sales',
                show: can('sales.process'),
            },
            {
                title: 'Returns',
                href: '/app/returns',
                show: can('returns.manage'),
            },
        ],
    },

    {
        title: 'Inventory',
        href: "#",
        icon: Package,
        show: can('inventory.manage') || can('batches.manage') || can('drugs.manage'),
        items: [
            {
                title: 'Inventory',
                href: '/app/inventory',
                show: can('inventory.manage'),
            },

            {
                title: 'Batches',
                href: '/app/batches',
                show: can('batches.manage'),
            },
            {
                title: 'Drugs',
                href: '/app/drugs',
                show: can('drugs.manage'),
            }
        ]


    },


    {
        title: 'Reports',
        href: '/app/reports/daily-sales',
        icon: FileText,
        show: can('reports.view') || can('audit_trail.view'),
        items: [
            {
                title: 'Reports',
                href: '/app/reports/daily-sales',
                show: can('reports.view'),

            },
            {
                title: 'Audit Trail',
                href: '/app/audit-trail',
                show: can('audit_trail.view'),
            }


        ]
    },

    {
        title: 'Settings',
        href: '#',
        icon: Settings,
        show: can('users.manage') || can('roles.manage') || can('settings.manage') || can('taxes.manage') || can('accounts.manage'),
        items: [
            {
                title: 'Users',
                href: '/app/users',
                show: can('users.manage'),
            },
            {
                title: 'Roles',
                href: '/app/roles',
                show: can('roles.manage'),
            },
            {
                title: 'Configuration',
                href: '/app/config',
                show: can('settings.manage'),
            },
            {
                title: 'Tax',
                href: '/app/taxes',
                show: can('taxes.manage'),
            },

            {
                title: 'Accounts',
                href: '/app/accounts',
                show: can('accounts.manage'),
            },
        ],
    },

]);

const mainNavItems = computed(() => filterNavItems(rawMainNavItems.value));

const footerNavItems: NavItem[] = [];
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
