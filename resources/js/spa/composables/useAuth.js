import { reactive } from 'vue';
import { authApi } from '../api/auth';
import { useTelegram } from './useTelegram';

const state = reactive({
    booted: false,
    loading: false,
    user: null,
    error: null,
    isTelegramMiniApp: false,
    telegramUser: null,
});

export function useAuth() {
    async function bootstrap() {
        state.loading = true;
        state.error = null;

        try {
            const telegram = useTelegram();
            state.isTelegramMiniApp = telegram.isTelegramMiniApp;
            state.telegramUser = telegram.user;

            if (telegram.isTelegramMiniApp) {
                await loginWithTelegram(telegram.initData);
            } else {
                const response = await authApi.me();
                state.user = response.user;
            }
        } catch (error) {
            state.user = null;
            state.error = error.status === 401 ? null : error.message;
        } finally {
            state.booted = true;
            state.loading = false;
        }
    }

    async function loginWithTelegram(initData = null) {
        const telegram = useTelegram();
        const payload = initData || telegram.initData;

        state.isTelegramMiniApp = telegram.isTelegramMiniApp;
        state.telegramUser = telegram.user;

        if (!payload) {
            throw new Error('Telegram auth data is missing.');
        }

        const response = await authApi.telegramLogin(payload);
        state.user = response.user;
        state.error = null;
    }

    async function login(payload) {
        const response = await authApi.login(payload);
        state.user = response.user;
    }

    async function register(payload) {
        const response = await authApi.register(payload);
        state.user = response.user;
    }

    async function logout() {
        await authApi.logout();
        state.user = null;
    }

    return {
        state,
        bootstrap,
        login,
        register,
        loginWithTelegram,
        logout,
    };
}
