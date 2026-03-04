import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
    accounts?: { id: number | string; name: string; plan?: string }[];
    current_account_id?: string | null;
    permissions?: string[];
    is_zeus?: boolean;
    is_system_zeus?: boolean;
    settings?: {
        settings?: Record<string, any>;
        exists?: boolean;
        inventory_batch_mode?: boolean;
        accounting_enabled?: boolean;
        [key: string]: any;
    };
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    show?: boolean;
    items?: NavItem[];
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    roles?: { id: number; name: string; zeus_level?: string }[];
    permissions?: string[];
    accounts?: { id: number; name: string; plan: string }[];
}

export type BreadcrumbItemType = BreadcrumbItem;

declare module 'vue' {
    interface ComponentCustomProperties {
        $can: (permission: string) => boolean;
    }
}
