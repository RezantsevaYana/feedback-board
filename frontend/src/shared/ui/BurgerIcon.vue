<template>
  <!-- three lines that fold into a cross: the menu's open / close mark, the same place for both -->
  <span class="burger-icon" :class="{ open }" aria-hidden="true">
    <span />
    <span />
    <span />
  </span>
</template>

<script setup lang="ts">
// The «Меню» mark of the header and the board (AppHeader, BoardPage) and the close mark of NavDrawer,
// which sits exactly over it: opening the menu folds the lines into a cross, closing unfolds them.
defineProps<{
  open?: boolean
}>()
</script>