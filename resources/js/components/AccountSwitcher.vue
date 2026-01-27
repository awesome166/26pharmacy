<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"

import { useAccount } from "@/composables/useAccount"

const props = defineProps<{
  accounts: {
    id: string
    name: string
  }[]
}>()

const { currentAccountId, setAccount } = useAccount()
const page = usePage();

const hasPlatformRole = computed(() => {
  return (page.props.auth as any).user.roles?.some((r: any) => r.name === 'Super Admin' && r.zeus_level === 'system');
});

const onChange = (accountId: any) => {
  if (accountId === "platform_admin") {
    if (currentAccountId.value === null) return;
    setAccount(null);
    window.location.reload();
    return;
  }

  if (!accountId || accountId === currentAccountId.value) return

  setAccount(accountId)
  window.location.reload()
}

const selectValue = computed(() => {
  return currentAccountId.value ? currentAccountId.value.toString() : (hasPlatformRole.value ? 'platform_admin' : '');
});
</script>

<template>
  <Select :model-value="selectValue" @update:model-value="onChange">
    <SelectTrigger class="w-[220px]">
      <SelectValue placeholder="Select account" />
    </SelectTrigger>

    <SelectContent>
      <SelectItem v-if="hasPlatformRole" value="platform_admin">
        Platform Admin
      </SelectItem>
      <SelectItem v-for="account in accounts" :key="account.id" :value="account.id.toString()">
        {{ account.name }}
      </SelectItem>
    </SelectContent>
  </Select>
</template>
