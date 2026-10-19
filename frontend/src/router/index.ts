import { createRouter, createWebHistory } from 'vue-router'
import BoardPage from '@/pages/board/BoardPage.vue'
import HomePage from '@/pages/home/HomePage.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'home', component: HomePage },
    { path: '/:slug', name: 'board', component: BoardPage },
    // the post card opens as a modal on top of the board, with its own address (TZ 5.2)
    { path: '/:slug/posts/:id(\\d+)', name: 'post', component: BoardPage },
  ],
})

export default router