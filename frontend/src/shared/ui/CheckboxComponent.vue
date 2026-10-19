<template>
  <label class="check">
    <input v-model="model" type="checkbox" v-bind="$attrs" />
    {{ label }}
  </label>
</template>

<script setup lang="ts">
// Checkbox with its label on the right. Native attributes go to the <input>.
defineOptions({ inheritAttrs: false })
const model = defineModel<boolean>({ required: true })
defineProps<{
  label: string
}>()
</script>
