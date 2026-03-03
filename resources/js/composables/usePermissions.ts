import { usePage } from '@inertiajs/vue3';
import type { AppPageProps } from '../types';

export function usePermissions() {
  /**
   * Check if the user has the given permission.
   *
   * Handles exact matches (e.g. 'pos.access'), as well as handling wildcard
   * expansion if the user's permissions array grants broader access.
   */
  const can = (permission: string): boolean => {
    const page = usePage<AppPageProps>();
    const userPermissions = page.props.auth.permissions || [];

    // Ensure userPermissions is treated as a string array since getAllPermissions()
    // now returns an array of expanded permission strings.
    const permsArray: ReadonlyArray<string> = (Array.isArray(userPermissions) ? userPermissions : Object.values(userPermissions)) as readonly string[];

    // 1. Exact match
    if (permsArray.includes(permission)) {
      return true;
    }

    // 2. Wildcard check: Check if user has any action-specific permission for this module (e.g., 'users.manage:read')
    return permsArray.some(p => p.startsWith(`${permission}:`));
  };

  return { can };
}
