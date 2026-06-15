<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuth } from '../composables/useAuth';
import { useI18n } from '../composables/useI18n';

const auth = useAuth();
const { locale, setLocale, t } = useI18n();
const router = useRouter();
const tab = ref('login');
const loading = ref(false);
const error = ref(null);

const loginForm = ref({ email: '', password: '' });
const registerForm = ref({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

async function submitLogin() {
    loading.value = true;
    error.value = null;

    try {
        await auth.login(loginForm.value);
        await router.replace({ name: 'dashboard' });
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function submitRegister() {
    loading.value = true;
    error.value = null;

    try {
        await auth.register(registerForm.value);
        await router.replace({ name: 'dashboard' });
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="auth-page">
        <v-card class="auth-card pa-5" border>
            <div class="auth-actions">
                <v-btn-toggle
                    :model-value="locale"
                    color="primary"
                    density="compact"
                    mandatory
                    variant="outlined"
                    @update:model-value="setLocale"
                >
                    <v-btn value="ru" size="small">RU</v-btn>
                    <v-btn value="en" size="small">EN</v-btn>
                </v-btn-toggle>
            </div>
            <div class="brand">{{ t('appName') }}</div>
            <v-tabs v-model="tab" color="primary" grow>
                <v-tab value="login">{{ t('auth.loginTab') }}</v-tab>
                <v-tab value="register">{{ t('auth.registerTab') }}</v-tab>
            </v-tabs>

            <v-alert v-if="error" type="error" variant="tonal" class="mt-4">
                {{ error }}
            </v-alert>

            <v-window v-model="tab" class="mt-4">
                <v-window-item value="login">
                    <v-form @submit.prevent="submitLogin">
                        <v-text-field v-model="loginForm.email" label="Email" type="email" autocomplete="email" />
                        <v-text-field v-model="loginForm.password" :label="t('auth.password')" type="password" autocomplete="current-password" />
                        <v-btn type="submit" block color="primary" :loading="loading">
                            {{ t('auth.loginButton') }}
                        </v-btn>
                    </v-form>
                </v-window-item>

                <v-window-item value="register">
                    <v-form @submit.prevent="submitRegister">
                        <v-text-field v-model="registerForm.name" :label="t('auth.name')" autocomplete="name" />
                        <v-text-field v-model="registerForm.email" label="Email" type="email" autocomplete="email" />
                        <v-text-field v-model="registerForm.password" :label="t('auth.password')" type="password" autocomplete="new-password" />
                        <v-text-field v-model="registerForm.password_confirmation" :label="t('auth.passwordConfirmation')" type="password" autocomplete="new-password" />
                        <v-btn type="submit" block color="primary" :loading="loading">
                            {{ t('auth.registerButton') }}
                        </v-btn>
                    </v-form>
                </v-window-item>
            </v-window>
        </v-card>
    </div>
</template>

<style scoped>
.auth-page {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 16px;
}

.auth-card {
    width: min(100%, 440px);
}

.auth-actions {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 8px;
}

.brand {
    margin-bottom: 18px;
    color: #18252f;
    font-size: 24px;
    font-weight: 800;
    text-align: center;
}
</style>
