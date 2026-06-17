<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { CheckCircle2 } from '@lucide/vue';
import { useAuth } from '../composables/useAuth';
import { useI18n } from '../composables/useI18n';

const router = useRouter();
const auth = useAuth();
const { t } = useI18n();

const title = computed(() => t('emailVerified.title'));
const text = computed(() => t('emailVerified.text'));
const actionRoute = computed(() => (
    auth.state.user ? { name: 'dashboard' } : { name: 'login' }
));
</script>

<template>
    <div class="verify-page">
        <v-card class="verify-card pa-6" border>
            <CheckCircle2 class="verify-icon" :size="42" />
            <h1>{{ title }}</h1>
            <p>{{ text }}</p>
            <v-btn color="primary" block @click="router.replace(actionRoute)">
                {{ t('emailVerified.action') }}
            </v-btn>
        </v-card>
    </div>
</template>

<style scoped>
.verify-page {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 16px;
    background: #f6f8fb;
}

.verify-card {
    width: min(100%, 420px);
    text-align: center;
}

.verify-icon {
    color: #1f9d63;
    margin-bottom: 12px;
}

h1 {
    margin: 0;
    color: #18252f;
    font-size: 26px;
}

p {
    margin: 12px 0 22px;
    color: #61717d;
}
</style>
