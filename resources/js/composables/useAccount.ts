import { ref, computed } from "vue"

export const STORAGE_KEY = "current_account_id"

let storedId = localStorage.getItem(STORAGE_KEY)
// Clear stale integer IDs (ULIDs are 26 chars)
if (storedId && storedId.length < 20) {
  localStorage.removeItem(STORAGE_KEY)
  storedId = null
}

const currentAccountId = ref<string | null>(storedId)


// Helper to set cookie
function setCookie(name: string, value: string, days = 7) {
  const d = new Date();
  d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
  const expires = "expires=" + d.toUTCString();
  document.cookie = name + "=" + value + ";" + expires + ";path=/;SameSite=Lax";
}

// Helper to remove cookie
function eraseCookie(name: string) {
  document.cookie = name + '=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
}

export function useAccount() {
  const setAccount = (accountId: string | null) => {
    currentAccountId.value = accountId
    if (accountId) {
      localStorage.setItem(STORAGE_KEY, accountId)
      setCookie(STORAGE_KEY, accountId)
    } else {
      // If null, we clear it (Platform mode)
      localStorage.removeItem(STORAGE_KEY)
      eraseCookie(STORAGE_KEY)
    }
  }

  const clearAccount = () => {
    currentAccountId.value = null
    localStorage.removeItem(STORAGE_KEY)
    eraseCookie(STORAGE_KEY)
  }

  return {
    currentAccountId,
    setAccount,
    clearAccount,
  }
}
