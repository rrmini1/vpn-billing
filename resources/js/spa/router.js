import { createRouter, createWebHistory } from 'vue-router';
import AccountMergedPage from './pages/AccountMergedPage.vue';
import DashboardPage from './pages/DashboardPage.vue';
import EmailVerifiedPage from './pages/EmailVerifiedPage.vue';
import ForgotPasswordPage from './pages/ForgotPasswordPage.vue';
import LoginPage from './pages/LoginPage.vue';
import PaymentsPage from './pages/PaymentsPage.vue';
import PlansPage from './pages/PlansPage.vue';
import ResetPasswordPage from './pages/ResetPasswordPage.vue';
import AdminPage from './pages/AdminPage.vue';

export default createRouter({
    history: createWebHistory('/app'),
    routes: [
        { path: '/', name: 'dashboard', component: DashboardPage },
        { path: '/login', name: 'login', component: LoginPage, meta: { public: true } },
        { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordPage, meta: { public: true } },
        { path: '/reset-password', name: 'reset-password', component: ResetPasswordPage, meta: { public: true } },
        { path: '/email-verified', name: 'email-verified', component: EmailVerifiedPage, meta: { public: true } },
        { path: '/account-merged', name: 'account-merged', component: AccountMergedPage, meta: { public: true } },
        { path: '/plans', name: 'plans', component: PlansPage },
        { path: '/payments', name: 'payments', component: PaymentsPage },
        { path: '/admin', name: 'admin', component: AdminPage },
    ],
});
