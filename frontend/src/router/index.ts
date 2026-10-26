import { createRouter, createWebHistory } from 'vue-router'
import BoardPage from '@/pages/board/BoardPage.vue'
import HomePage from '@/pages/home/HomePage.vue'
import RegisterPage from '@/pages/auth/RegisterPage.vue'
import LoginPage from '@/pages/auth/LoginPage.vue'
import VerifyEmailPage from '@/pages/auth/VerifyEmailPage.vue'
import ForgotPasswordPage from '@/pages/auth/ForgotPasswordPage.vue'
import ResetPasswordPage from '@/pages/auth/ResetPasswordPage.vue'
import BoardsHubPage from '@/pages/app/BoardsHubPage.vue'
import BoardNewPage from '@/pages/app/BoardNewPage.vue'
import BoardEmbedPage from '@/pages/app/BoardEmbedPage.vue'
import BoardSettingsPage from '@/pages/app/BoardSettingsPage.vue'
import { useAuthStore } from '@/store/auth/useAuthStore'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'home', component: HomePage },
    { path: '/app/register', name: 'register', component: RegisterPage, meta: { guest: true } },
    { path: '/app/login', name: 'login', component: LoginPage, meta: { guest: true } },
    { path: '/app/forgot-password', name: 'forgot-password', component: ForgotPasswordPage, meta: { guest: true } },
    // the link from the reset letter; no meta: it works even if someone else is logged in on this device
    { path: '/app/reset-password/:token', name: 'reset-password', component: ResetPasswordPage },
    // the link from the confirmation letter; no meta: it opens with or without a session
    { path: '/app/verify-email/:id(\\d+)/:hash', name: 'verify-email', component: VerifyEmailPage },
    { path: '/app/boards', name: 'hub', component: BoardsHubPage, meta: { auth: true } },
    { path: '/app/boards/new', name: 'board-new', component: BoardNewPage, meta: { auth: true } },
    { path: '/app/boards/:slug/settings', name: 'board-settings', component: BoardSettingsPage, meta: { auth: true } },
    { path: '/app/boards/:slug/embed', name: 'board-embed', component: BoardEmbedPage, meta: { auth: true } },
    { path: '/:slug', name: 'board', component: BoardPage },
    // the post card opens as a modal on top of the board, with its own address (TZ 5.2)
    { path: '/:slug/posts/:id(\\d+)', name: 'post', component: BoardPage },
  ],
})

// Lazy auth check for /app/* routes. useAuthStore caches the result so we hit /me at most once per session.
router.beforeEach(async (to) => {
  if (!to.meta.auth && !to.meta.guest) return true
  const auth = useAuthStore()
  await auth.fetchMe()
  if (to.meta.auth && !auth.isAuth) {
    return { name: 'login', query: { next: to.fullPath } }
  }
  if (to.meta.guest && auth.isAuth) {
    return { name: 'hub' }
  }
  return true
})

export default router
