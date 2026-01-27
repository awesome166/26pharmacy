import axios, { type AxiosStatic } from 'axios';

declare global {
  interface Window {
    axios: AxiosStatic;
  }
}

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Add account ID to headers if it exists
// defined in useAccount.ts: const STORAGE_KEY = "current_account_id"
const accountId = localStorage.getItem('current_account_id');
if (accountId) {
  window.axios.defaults.headers.common['X-Account-ID'] = accountId;
}

// Optional: Interceptor to update header if localStorage changes (though usually page reload happens on switch)
window.axios.interceptors.request.use((config) => {
  const currentAccountId = localStorage.getItem('current_account_id');
  if (currentAccountId) {
    config.headers['X-Account-ID'] = currentAccountId;

    // Also add to request body for mutations (POST, PUT, PATCH, DELETE)
    const method = config.method?.toLowerCase() || '';
    if (['post', 'put', 'patch', 'delete'].includes(method)) {
      if (config.data instanceof FormData) {
        if (!config.data.has('account_id')) {
          config.data.append('account_id', currentAccountId);
        }
      } else if (typeof config.data === 'object' && config.data !== null) {
        // Handle JSON object
        config.data = { ...config.data, account_id: currentAccountId };
      } else if (config.data === undefined) {
        // Create data object if it doesn't exist
        config.data = { account_id: currentAccountId };
      }
    }
  }
  return config;
});

// Vue Navigation Middleware
import { router } from '@inertiajs/vue3';

router.on('start', () => {
  const currentAccountId = localStorage.getItem('current_account_id');
  if (currentAccountId && window.axios) {
    window.axios.defaults.headers.common['X-Account-ID'] = currentAccountId;
  }
});
