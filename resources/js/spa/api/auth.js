import { api } from './client';

export const authApi = {
    me: () => api('/api/auth/me'),
    login: (payload) => api('/api/auth/login', { method: 'POST', body: payload }),
    register: (payload) => api('/api/auth/register', { method: 'POST', body: payload }),
    logout: () => api('/api/auth/logout', { method: 'POST' }),
    forgotPassword: (email) => api('/api/auth/forgot-password', {
        method: 'POST',
        body: { email },
    }),
    resetPassword: (payload) => api('/api/auth/reset-password', {
        method: 'POST',
        body: payload,
    }),
    telegramLogin: (initData) => api('/api/auth/telegram/login', {
        method: 'POST',
        body: { init_data: initData },
    }),
    sendEmailVerification: () => api('/api/email/verification-notification', {
        method: 'POST',
    }),
};
