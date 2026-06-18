import { api } from './client';

export const billingApi = {
    profile: () => api('/api/profile'),
    plans: () => api('/api/plans'),
    payments: () => api('/api/payments'),
    traffic: () => api('/api/traffic'),
    startEmailMerge: (email) => api('/api/account/email/start', {
        method: 'POST',
        body: { email },
    }),
    completeEmailAttach: (payload) => api('/api/account/email/complete', {
        method: 'POST',
        body: payload,
    }),
    currentSubscription: () => api('/api/subscriptions/current'),
    trial: () => api('/api/subscriptions/trial', { method: 'POST' }),
    createPayment: (planCode) => api('/api/payments', {
        method: 'POST',
        body: { plan_code: planCode },
    }),
    simulatePaid: (paymentId) => api(`/api/payments/${paymentId}/simulate-paid`, {
        method: 'POST',
    }),
    adminDashboard: () => api('/api/admin/dashboard'),
    adminUsers: (params = {}) => api(`/api/admin/users?${new URLSearchParams(params)}`),
    updateAdminUserMarzbanLimit: (userId, payload) => api(`/api/admin/users/${userId}/marzban-limit`, {
        method: 'PATCH',
        body: payload,
    }),
    adminPlans: () => api('/api/admin/plans'),
    createAdminPlan: (payload) => api('/api/admin/plans', {
        method: 'POST',
        body: payload,
    }),
    updateAdminPlan: (planId, payload) => api(`/api/admin/plans/${planId}`, {
        method: 'PATCH',
        body: payload,
    }),
    adminPayments: (params = {}) => api(`/api/admin/payments?${new URLSearchParams(params)}`),
};
