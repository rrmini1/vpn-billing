<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { AlertCircle, CheckCircle2 } from '@lucide/vue';
import { useAuth } from '../composables/useAuth';
import { useI18n } from '../composables/useI18n';

const route = useRoute();
const router = useRouter();
const auth = useAuth();
const { t } = useI18n();

const isSuccess = computed(() => route.query.status === 'success');
const title = computed(() => isSuccess.value ? t('accountMerged.successTitle') : t('accountMerged.invalidTitle'));
const text = computed(() => isSuccess.value ? t('accountMerged.successText') : t('accountMerged.invalidText'));
const actionRoute = computed(() => (
    auth.state.user ? { name: 'dashboard' } : { name: 'login' }
));
</script>

<template>
    <div class="merge-page">
        <v-card class="merge-card pa-6" border>
            <CheckCircle2 v-if="isSuccess" class="merge-icon merge-icon--success" :size="42" />
            <AlertCircle v-else class="merge-icon merge-icon--error" :size="42" />
            <h1>{{ title }}</h1>
            <p>{{ text }}</p>
            <v-btn color="primary" block @click="router.replace(actionRoute)">
                {{ t('accountMerged.action') }}
            </v-btn>
        </v-card>
    </div>
</template>

<style scoped>
.merge-page {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 16px;
    background: #f6f8fb;
}

.merge-card {
    width: min(100%, 420px);
    text-align: center;
}

.merge-icon {
    margin-bottom: 12px;
}

.merge-icon--success {
    color: #1f9d63;
}

.merge-icon--error {
    color: #b42318;
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
