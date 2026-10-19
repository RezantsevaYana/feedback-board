<script setup lang="ts">
import { RouterView } from 'vue-router'
import { useToast } from '@/lib/toast'

const toast = useToast()
</script>

<template>
  <RouterView />
  <div v-if="toast.state.text" class="toast" :class="{ error: toast.state.error }" role="status">{{ toast.state.text }}</div>
</template>