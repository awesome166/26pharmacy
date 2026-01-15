import { ref, computed, watch } from "vue"

const STORAGE_KEY = "current_account_id"

const currentAccountId = ref<number | null>(
  localStorage.getItem(STORAGE_KEY)
    ? Number(localStorage.getItem(STORAGE_KEY))
    : null
)

export function useAccount() {
  const setAccount = (accountId: number) => {
    currentAccountId.value = accountId
    localStorage.setItem(STORAGE_KEY, String(accountId))
  }

  const clearAccount = () => {
    currentAccountId.value = null
    localStorage.removeItem(STORAGE_KEY)
  }

  return {
    currentAccountId: computed(() => currentAccountId.value),
    setAccount,
    clearAccount,
  }
}
