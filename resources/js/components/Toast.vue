<script setup lang="ts">
import { computed } from 'vue'
import { X, CheckCircle2, AlertCircle, Info, AlertTriangle } from 'lucide-vue-next'

export interface Toast {
  id: string
  type: 'success' | 'error' | 'warning' | 'info'
  title?: string
  message: string
  duration?: number
}

interface Props {
  toast: Toast
  onClose: (id: string) => void
}

const props = defineProps<Props>()

const icon = computed(() => {
  switch (props.toast.type) {
    case 'success':
      return CheckCircle2
    case 'error':
      return AlertCircle
    case 'warning':
      return AlertTriangle
    case 'info':
      return Info
    default:
      return Info
  }
})

const bgClass = computed(() => {
  switch (props.toast.type) {
    case 'success':
      return 'bg-green-50 border-green-200 dark:bg-green-950 dark:border-green-800'
    case 'error':
      return 'bg-red-50 border-red-200 dark:bg-red-950 dark:border-red-800'
    case 'warning':
      return 'bg-yellow-50 border-yellow-200 dark:bg-yellow-950 dark:border-yellow-800'
    case 'info':
      return 'bg-blue-50 border-blue-200 dark:bg-blue-950 dark:border-blue-800'
    default:
      return 'bg-gray-50 border-gray-200 dark:bg-gray-950 dark:border-gray-800'
  }
})

const iconClass = computed(() => {
  switch (props.toast.type) {
    case 'success':
      return 'text-green-600 dark:text-green-400'
    case 'error':
      return 'text-red-600 dark:text-red-400'
    case 'warning':
      return 'text-yellow-600 dark:text-yellow-400'
    case 'info':
      return 'text-blue-600 dark:text-blue-400'
    default:
      return 'text-gray-600 dark:text-gray-400'
  }
})

const textClass = computed(() => {
  switch (props.toast.type) {
    case 'success':
      return 'text-green-900 dark:text-green-100'
    case 'error':
      return 'text-red-900 dark:text-red-100'
    case 'warning':
      return 'text-yellow-900 dark:text-yellow-100'
    case 'info':
      return 'text-blue-900 dark:text-blue-100'
    default:
      return 'text-gray-900 dark:text-gray-100'
  }
})
</script>

<template>
  <div :class="[
    'flex items-start gap-3 p-4 rounded-lg border shadow-lg transition-all duration-300 min-w-[320px] max-w-md',
    bgClass
  ]" role="alert">
    <component :is="icon" :class="['h-5 w-5 flex-shrink-0 mt-0.5', iconClass]" />

    <div class="flex-1 min-w-0">
      <p v-if="toast.title" :class="['font-semibold text-sm', textClass]">
        {{ toast.title }}
      </p>
      <p :class="['text-sm', toast.title ? 'mt-1' : '', textClass]">
        {{ toast.message }}
      </p>
    </div>

    <button @click="onClose(toast.id)"
      :class="['flex-shrink-0 rounded-md p-1 hover:bg-black/5 dark:hover:bg-white/5 transition-colors', textClass]"
      aria-label="Close notification">
      <X class="h-4 w-4" />
    </button>
  </div>
</template>
