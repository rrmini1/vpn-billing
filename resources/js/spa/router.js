import { createRouter, createWebHistory } from 'vue-router';
import DashboardPage from './pages/DashboardPage.vue';
import EmailVerifiedPage from './pages/EmailVerifiedPage.vue';
import LoginPage from './pages/LoginPage.vue';
import PaymentsPage from './pages/PaymentsPage.vue';
import PlansPage from './pages/PlansPage.vue';
import AdminPage from './pages/AdminPage.vue';

export default createRouter({
    history: createWebHistory('/app'),
    routes: [
        { path: '/', name: 'dashboard', component: DashboardPage },
        { path: '/login', name: 'login', component: LoginPage, meta: { public: true } },
        { path: '/email-verified', name: 'email-verified', component: EmailVerifiedPage, meta: { public: true } },
        { path: '/plans', name: 'plans', component: PlansPage },
        { path: '/payments', name: 'payments', component: PaymentsPage },
        { path: '/admin', name: 'admin', component: AdminPage },
    ],
});
