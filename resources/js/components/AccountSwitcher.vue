<script setup lang="ts">
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
    id: number
    name: string
  }[]
}>()

const { currentAccountId, setAccount } = useAccount()

const onChange = (value: string) => {
  const accountId = Number(value)

  if (!accountId || accountId === currentAccountId.value) return

  setAccount(accountId)
  window.location.reload()
}
</script>

<template>
  <Select :model-value="currentAccountId?.toString()" @update:model-value="onChange">
    <SelectTrigger class="w-[220px]">
      <SelectValue placeholder="Select account" />
    </SelectTrigger>

    <SelectContent>
      <SelectItem v-for="account in accounts" :key="account.id" :value="account.id.toString()">
        {{ account.name }}
      </SelectItem>
    </SelectContent>
  </Select>
</template>
