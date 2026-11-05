<template>
  <main class="container home-page">
    <div class="home-ground">
      <span class="home-tag slant">Обратная связь от пользователей</span>
      <h1>Feedback Board</h1>
      <!-- the promise first, then the board address shown like a browser bar, with a separate CTA to the demo -->
      <p class="home-lead">
        <span class="home-claim">Вместо разрозненных писем и чатов — одна доска. Что важнее, подскажут голоса ваших пользователей.</span>
        <span class="home-bar">
          <span>Адрес доски</span>
          <code>/название-проекта</code>
        </span>
        <RouterLink to="/demo" class="home-demo">Посмотреть пример <code>/demo</code> <span class="home-demo-arrow" aria-hidden="true">→</span></RouterLink>
      </p>
    </div>
    <!-- while /me is in flight the block keeps its height but shows nothing, so guest and signed-in buttons never flash -->
    <div class="home-cta" :aria-busy="!auth.loaded">
      <template v-if="auth.loaded && auth.isAuth">
        <div class="home-cta-actions">
          <!-- the brand slant, like the tags; the count says what's waiting inside -->
          <RouterLink to="/app/boards" class="btn btn-primary home-go slant">
            Мои доски
            <span v-if="auth.boardSummaries.length" class="home-count" :aria-label="`досок: ${auth.boardSummaries.length}`">{{ auth.boardSummaries.length }}</span>
            <span class="home-go-arrow" aria-hidden="true">→</span>
          </RouterLink>
        </div>
        <p class="home-signed-in">
          Вы вошли как {{ auth.me?.email }} ·
          <button type="button" class="link-btn" :disabled="loggingOut" @click="onLogout">Выйти</button>
        </p>
      </template>
      <div v-else-if="auth.loaded" class="home-cta-actions">
        <!-- hover rolls the label into «Поехали →» (shared .cta-roll), same as in the hub -->
        <RouterLink to="/app/register" class="btn btn-primary" aria-label="Создать доску">
          <span class="cta-roll" aria-hidden="true"><span>Создать доску</span><span>Поехали →</span></span>
        </RouterLink>
        <RouterLink to="/app/login" class="btn">Войти</RouterLink>
      </div>
    </div>
  </main>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useLogout } from '@/shared/composables/useLogout'

// Public landing. A signed-in admin isn't redirected away (the page stays reachable to share or look at),
// the buttons just switch: guests get «Создать доску» / «Войти», a signed-in admin gets «Мои доски».
const auth = useAuthStore()
// redirect: false — logging out from the landing just re-renders the page in guest mode;
// no reason to kick the user over to /login from a page they came to as a visitor
const { logout: onLogout, busy: loggingOut } = useLogout({ redirect: false })

onMounted(async () => {
  try {
    await auth.fetchMe()
  } catch {
    // network or server error: the guest buttons still work, the login page will say what's wrong
    auth.loaded = true
  }
})
</script>