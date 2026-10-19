import { createRouter, createWebHistory } from 'vue-router'
import BoardView from '../views/BoardView.vue'
import HomeView from '../views/HomeView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'home', component: HomeView },
    { path: '/:slug', name: 'board', component: BoardView },
    // the post card opens as a modal on top of the board, with its own address (TZ 5.2)
    { path: '/:slug/posts/:id(\\d+)', name: 'post', component: BoardView },
  ],
})

export default router