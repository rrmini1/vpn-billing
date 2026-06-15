import { api } from './client';

export const billingApi = {
    profile: () => api('/api/profile'),
    plans: () => api('/api/plans'),
    payments: () => api('/api/payments'),
    traffic: () => api('/api/traffic'),
    currentSubscription: () => api('/api/subscriptions/current'),
    trial: () => api('/api/subscriptions/trial', { method: 'POST' }),
    createPayment: (planCode) => api('/api/payments', {
        method: 'POST',
        body: { plan_code: planCode },
    }),
    simulatePaid: (paymentId) => api(`/api/payments/${paymentId}/simulate-paid`, {
        method: 'POST',
    }),
};
