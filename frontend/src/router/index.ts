import { createRouter, createWebHistory } from 'vue-router'
import BoardPage from '@/pages/board/BoardPage.vue'
import HomePage from '@/pages/home/HomePage.vue'
import RegisterPage from '@/pages/auth/RegisterPage.vue'
import LoginPage from '@/pages/auth/LoginPage.vue'
import VerifyEmailPage from '@/pages/auth/VerifyEmailPage.vue'
import ForgotPasswordPage from '@/pages/auth/ForgotPasswordPage.vue'
import ResetPasswordPage from '@/pages/auth/ResetPasswordPage.vue'
import BoardsHubPage from '@/pages/app/BoardsHubPage.vue'
import OnboardingPage from '@/pages/onboarding/OnboardingPage.vue'
import BoardAccessPage from '@/pages/app/BoardAccessPage.vue'
import BoardSettingsPage from '@/pages/app/BoardSettingsPage.vue'
import BoardStructurePage from '@/pages/app/BoardStructurePage.vue'
import BoardWidgetPage from '@/pages/app/BoardWidgetPage.vue'
import InviteAcceptPage from '@/pages/auth/InviteAcceptPage.vue'
import AccountPage from '@/pages/account/AccountPage.vue'
import AppShell from '@/shared/components/AppShell.vue'
import { useAuthStore } from '@/store/auth/useAuthStore'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'home', component: HomePage },
    { path: '/app/register', name: 'register', component: RegisterPage, meta: { guest: true } },
    { path: '/app/login', name: 'login', component: LoginPage, meta: { guest: true } },
    // no meta: the «Пароль изменён» letter links here, and the owner may still be logged in on this device
    { path: '/app/forgot-password', name: 'forgot-password', component: ForgotPasswordPage },
    // the link from the reset letter; no meta: it works even if someone else is logged in on this device
    { path: '/app/reset-password/:token', name: 'reset-password', component: ResetPasswordPage },
    // the link from the confirmation letter; no meta: it opens with or without a session
    { path: '/app/verify-email/:id(\\d+)/:hash', name: 'verify-email', component: VerifyEmailPage },
    // the new-board onboarding: a focused screen of its own, outside the cabinet's frame (no side menu);
    // «Новая доска» opens step 1, «Продолжить» on the hub card — the saved step of that board (TEST.md п. 20)
    { path: '/app/boards/new', name: 'board-new', component: OnboardingPage, meta: { auth: true } },
    { path: '/app/boards/:slug/setup', name: 'board-setup', component: OnboardingPage, meta: { auth: true } },
    // The signed-in cabinet: one frame (header + side menu) stays mounted while its pages change, so the menu's
    // nested sections fold in and out instead of the whole frame being rebuilt. Bare /app lands on «Мои доски»,
    // otherwise /:slug below would catch «app» as a board slug.
    {
      path: '/app',
      component: AppShell,
      children: [
        { path: '', redirect: '/app/boards' },
        // the list is the whole page: no side menu there
        { path: 'boards', name: 'hub', component: BoardsHubPage, meta: { auth: true, noSidebar: true } },
        { path: 'account', name: 'account', component: AccountPage, meta: { auth: true } },
        // the board's settings: four sections with an address each (TEST.md п. 42). The old anchors of the two former
        // pages («Настройки» with categories and statuses, «Доступ» with the link and the widget) land in the new ones
        {
          path: 'boards/:slug/settings',
          name: 'board-settings',
          component: BoardSettingsPage,
          meta: { auth: true },
          beforeEnter: (to) => {
            if (to.hash === '#categories' || to.hash === '#statuses') return { name: 'board-structure', params: to.params, hash: to.hash }
            if (to.hash === '#domains') return { name: 'board-widget', params: to.params }
            if (to.hash === '#access') return { name: 'board-access', params: to.params }
          },
        },
        {
          path: 'boards/:slug/access',
          name: 'board-access',
          component: BoardAccessPage,
          meta: { auth: true },
          beforeEnter: (to) => {
            if (to.hash === '#widget') return { name: 'board-widget', params: to.params }
            if (to.hash === '#link') return { name: 'board-settings', params: to.params, hash: '#link' }
          },
        },
        { path: 'boards/:slug/structure', name: 'board-structure', component: BoardStructurePage, meta: { auth: true } },
        { path: 'boards/:slug/widget', name: 'board-widget', component: BoardWidgetPage, meta: { auth: true } },
        // «Участники» and «Подключение» became one «Доступ» page (02.11.2026): old links and letters land there
        { path: 'boards/:slug/participants', redirect: (to) => ({ name: 'board-access', params: to.params, hash: '#participants' }) },
        { path: 'boards/:slug/embed', redirect: (to) => ({ name: 'board-access', params: to.params, query: to.query }) },
      ],
    },
    // the UI components catalogue: local development only, the build doesn't have the route
    ...(import.meta.env.DEV ? [{ path: '/dev/ui', name: 'dev-ui', component: () => import('@/pages/dev/UiKitPage.vue') }] : []),
    // the link from the invitation letter; works for guests and already-logged-in users (page branches by scenario)
    { path: '/app/invite/:token', name: 'invite-accept', component: InviteAcceptPage },
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
