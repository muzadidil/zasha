import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';

const routes = [
  { path: '/', redirect: '/available' },
  { path: '/login', name: 'login', component: () => import('./views/Login.vue') },
  { path: '/register', name: 'register', component: () => import('./views/Register.vue') },
  { path: '/available', name: 'available', component: () => import('./views/AvailableFeed.vue'), meta: { auth: true } },
  { path: '/wallet', name: 'wallet', component: () => import('./views/Wallet.vue'), meta: { auth: true } },
  { path: '/profile', name: 'profile', component: () => import('./views/Profile.vue'), meta: { auth: true } },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to) => {
  const auth = useAuthStore();
  if (to.meta.auth && !auth.token) return { name: 'login' };
  if ((to.name === 'login' || to.name === 'register') && auth.token) return { name: 'available' };
  return true;
});

export default router;
