<template>
  <main class="container home-page">
    <div class="home-page-inner">
      <h1>Feedback Board</h1>
      <p class="home-lead">
        Доска проекта открывается по адресу вида <code class="mono">/название-проекта</code>, например <RouterLink to="/demo">/demo</RouterLink>.
      </p>
      <div class="home-cta-actions">
        <RouterLink to="/app/register" class="btn btn-primary">Создать доску</RouterLink>
        <RouterLink to="/app/login" class="btn">Войти</RouterLink>
      </div>
    </div>
  </main>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>
