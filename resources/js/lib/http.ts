import axios from "axios"
import { useAccount } from "@/composables/useAccount"

const api = axios.create({
  baseURL: "/api",
  headers: {
    "Content-Type": "application/json",
  },
})

api.interceptors.request.use((config) => {
  const { currentAccountId } = useAccount()

  if (currentAccountId.value) {
    config.headers["X-Account-ID"] = currentAccountId.value

    if (
      config.method &&
      ["post", "put", "patch", "delete"].includes(config.method)
    ) {
      config.data = {
        ...(config.data || {}),
        account_id: currentAccountId.value,
      }
    }
  }

  return config
})

export default api
