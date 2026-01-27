<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Toast, { type Toast as ToastType } from '@/components/Toast.vue'

const toasts = ref<ToastType[]>([])
let toastIdCounter = 0

const page = usePage()

// Watch for Laravel flash messages from Inertia
watch(
  () => page.props,
  (props: any) => {
    // Handle success messages
    if (props.flash?.success) {
      addToast({
        type: 'success',
        message: props.flash.success,
      })
    }

    // Handle error messages
    if (props.flash?.error) {
      addToast({
        type: 'error',
        message: props.flash.error,
      })
    }

    // Handle warning messages
    if (props.flash?.warning) {
      addToast({
        type: 'warning',
        message: props.flash.warning,
      })
    }

    // Handle info messages
    if (props.flash?.info) {
      addToast({
        type: 'info',
        message: props.flash.info,
      })
    }

    // Handle validation errors
    if (props.errors && Object.keys(props.errors).length > 0) {
      const errorMessages = Object.values(props.errors).flat()
      errorMessages.forEach((message: any) => {
        addToast({
          type: 'error',
          title: 'Validation Error',
          message: String(message),
        })
      })
    }
  },
  { deep: true, immediate: true }
)

function addToast(options: Omit<ToastType, 'id'>) {
  const id = `toast-${++toastIdCounter}`
  const duration = options.duration || 5000

  const toast: ToastType = {
    id,
    ...options,
  }

  toasts.value.push(toast)

  // Auto-remove after duration
  if (duration > 0) {
    setTimeout(() => {
      removeToast(id)
    }, duration)
  }
}

function removeToast(id: string) {
  const index = toasts.value.findIndex(t => t.id === id)
  if (index > -1) {
    toasts.value.splice(index, 1)
  }
}

// Expose addToast globally for programmatic use
onMounted(() => {
  (window as any).addToast = addToast

  // Setup Axios interceptor to handle JSON responses automatically
  import('axios').then(({ default: axios }) => {
    // Response interceptor for success messages
    axios.interceptors.response.use(
      (response) => {
        // Check if response has a success message
        if (response.data?.message && response.config.method !== 'get') {
          addToast({
            type: 'success',
            message: response.data.message,
          })
        }
        return response
      },
      (error) => {
        // Handle error responses
        if (error.response) {
          const data = error.response.data

          // Handle validation errors (422)
          if (error.response.status === 422 && data.errors) {
            const errorMessages = Object.values(data.errors).flat()
            errorMessages.forEach((message: any) => {
              addToast({
                type: 'error',
                title: 'Validation Error',
                message: String(message),
              })
            })
          }
          // Handle general error messages
          else if (data?.message) {
            addToast({
              type: 'error',
              title: error.response.status >= 500 ? 'Server Error' : 'Error',
              message: data.message,
            })
          }
          // Fallback error message
          else {
            addToast({
              type: 'error',
              title: 'Error',
              message: `Request failed with status ${error.response.status}`,
            })
          }
        }
        // Network errors
        else if (error.request) {
          addToast({
            type: 'error',
            title: 'Network Error',
            message: 'Unable to connect to server. Please check your connection.',
          })
        }
        // Other errors
        else {
          addToast({
            type: 'error',
            title: 'Error',
            message: error.message || 'An unexpected error occurred',
          })
        }

        return Promise.reject(error)
      }
    )
  })
})
</script>

<template>
  <Teleport to="body">
    <div class="fixed top-4 right-4 z-50 flex flex-col gap-2 pointer-events-none z-100">
      <TransitionGroup enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="translate-x-full opacity-0" enter-to-class="translate-x-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in" leave-from-class="translate-x-0 opacity-100"
        leave-to-class="translate-x-full opacity-0">
        <div v-for="toast in toasts" :key="toast.id" class="pointer-events-auto">
          <Toast :toast="toast" :on-close="removeToast" />
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
