import { createRouter, createWebHistory } from 'vue-router';
import DashboardPage from './pages/DashboardPage.vue';
import LoginPage from './pages/LoginPage.vue';
import PaymentsPage from './pages/PaymentsPage.vue';
import PlansPage from './pages/PlansPage.vue';

export default createRouter({
    history: createWebHistory('/app'),
    routes: [
        { path: '/', name: 'dashboard', component: DashboardPage },
        { path: '/login', name: 'login', component: LoginPage },
        { path: '/plans', name: 'plans', component: PlansPage },
        { path: '/payments', name: 'payments', component: PaymentsPage },
    ],
});
