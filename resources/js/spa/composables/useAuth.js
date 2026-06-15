import { reactive } from 'vue';
import { authApi } from '../api/auth';
import { useTelegram } from './useTelegram';

const state = reactive({
    booted: false,
    loading: false,
    user: null,
    error: null,
});

export function useAuth() {
    async function bootstrap() {
        state.loading = true;
        state.error = null;

        try {
            const telegram = useTelegram();

            if (telegram.isTelegramMiniApp) {
                const response = await authApi.telegramLogin(telegram.initData);
                state.user = response.user;
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
        logout,
    };
}
