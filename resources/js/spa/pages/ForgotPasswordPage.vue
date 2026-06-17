<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { authApi } from '../api/auth';
import { useI18n } from '../composables/useI18n';

const router = useRouter();
const { locale, setLocale, t } = useI18n();
const email = ref('');
const loading = ref(false);
const error = ref(null);
const message = ref(null);

async function submit() {
    loading.value = true;
    error.value = null;
    message.value = null;

    try {
        await authApi.forgotPassword(email.value);
        message.value = t('passwordReset.linkSent');
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

            <h1>{{ t('passwordReset.forgotTitle') }}</h1>
            <p>{{ t('passwordReset.forgotText') }}</p>

            <v-alert v-if="message" type="success" variant="tonal" class="mb-4">{{ message }}</v-alert>
            <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>

            <v-form @submit.prevent="submit">
                <v-text-field v-model="email" label="Email" type="email" autocomplete="email" />
                <v-btn type="submit" block color="primary" :loading="loading">
                    {{ t('passwordReset.sendLink') }}
                </v-btn>
            </v-form>

            <v-btn class="mt-3" block variant="text" @click="router.replace({ name: 'login' })">
                {{ t('passwordReset.backToLogin') }}
            </v-btn>
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

h1 {
    margin: 0 0 10px;
    color: #18252f;
    font-size: 24px;
}

p {
    margin: 0 0 18px;
    color: #61717d;
    line-height: 1.45;
}
</style>
