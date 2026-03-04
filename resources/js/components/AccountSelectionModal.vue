<script setup lang="ts">
import { usePage, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { useAccount } from '@/composables/useAccount';
import type { PageProps } from '@inertiajs/core';
import type { User } from '@/types';

interface Account {
  id: string;
  name: string;
}

interface Props extends PageProps {
  auth: {
    user: User;
    accounts?: Account[];
    current_account_id?: string;
  }
}

const page = usePage<Props>();
const isOpen = ref(false);
const { currentAccountId, setAccount } = useAccount();

const accounts = computed(() => {
  return (page.props.auth as any)?.accounts || [];
});

const hasPlatformRole = computed(() => {
  return Boolean(
    (page.props.auth as any).is_system_zeus
      || (page.props.auth as any).is_zeus
      || (page.props.auth as any).permissions?.includes('*')
  );
});

const isSelecting = ref(false);

const checkAccountRequirement = () => {
  // If not logged in, do nothing
  if (!page.props.auth.user) {
    isOpen.value = false;
    return;
  }

  // If Platform role and no account selected (currentAccountId is null), it is valid (Platform mode)
  if (hasPlatformRole.value && currentAccountId.value === null) {
    isOpen.value = false;
    return;
  }

  const accs = accounts.value;

  if (accs.length === 0 && !hasPlatformRole.value) {
    return;
  }

  // Auto-select if only one and not platform
  if (accs.length === 1 && !hasPlatformRole.value) {
    const singleAccount = accs[0];
    if (String(currentAccountId.value) !== String(singleAccount.id)) {
      selectAccount(singleAccount.id);
    }
    return;
  }

  // Multiple accounts or Platform available
  const isValidSelection = currentAccountId.value && accs.find((a: Account) => String(a.id) === String(currentAccountId.value));

  if (!isValidSelection && !hasPlatformRole.value) {
    isOpen.value = true;
  } else if (!isValidSelection && hasPlatformRole.value && currentAccountId.value !== null) {
    // If has platform role but selected account is invalid (and not null), open modal
    // But we already covered currentAccountId === null above.
    isOpen.value = true;
  } else {
    // If isValidSelection, we are good.
    // If hasPlatformRole and currentAccountId is null, checked above.
    isOpen.value = false;
  }
};

const selectAccount = (accountId: string | null) => {
  if (isSelecting.value) return;
  isSelecting.value = true;

  // Use the composable to set account (handles localStorage and ref update)
  setAccount(accountId);

  // Update Axios immediately for subsequent requests in this session
  if (window.axios) {
    if (accountId) {
      window.axios.defaults.headers.common['x-account-id'] = accountId;
    } else {
      delete window.axios.defaults.headers.common['x-account-id'];
    }
  }

  isOpen.value = false;
  isSelecting.value = false;

  // Reload to apply scope
  router.reload();
};

onMounted(() => {
  checkAccountRequirement();
});

watch(() => page.props.auth, () => {
  checkAccountRequirement();
}, { deep: true });
</script>

<template>
  <Dialog :open="isOpen">
    <DialogContent class="sm:max-w-md" @interact-outside.prevent @escape-key-down.prevent>
      <DialogHeader>
        <DialogTitle>Select Account</DialogTitle>
        <DialogDescription>
          Please select an account to continue.
        </DialogDescription>
      </DialogHeader>

      <div class="grid gap-4 py-4">
        <!-- Platform Option -->
        <Button v-if="hasPlatformRole" variant="outline" class="justify-between h-auto py-4 px-6"
          @click="selectAccount(null)" :disabled="isSelecting">
          <span class="font-medium">Platform Admin</span>
          <span v-if="currentAccountId === null" class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded">
            Current
          </span>
          <span v-else class="text-muted-foreground">&rarr;</span>
        </Button>

        <Button v-for="account in accounts" :key="account.id" variant="outline" class="justify-between h-auto py-4 px-6"
          @click="selectAccount(account.id)" :disabled="isSelecting">
          <span class="font-medium">{{ account.name }}</span>
          <span v-if="account.id === currentAccountId" class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded">
            Current
          </span>
          <span v-else class="text-muted-foreground">&rarr;</span>
        </Button>
      </div>
    </DialogContent>
  </Dialog>
</template>
