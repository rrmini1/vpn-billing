<script setup>
import { onMounted, ref } from 'vue';
import { Check, MailPlus } from '@lucide/vue';
import { authApi } from '../api/auth';
import { billingApi } from '../api/billing';
import SubscriptionCard from '../components/SubscriptionCard.vue';
import TrafficCard from '../components/TrafficCard.vue';
import { useI18n } from '../composables/useI18n';

const { t } = useI18n();
const profile = ref(null);
const traffic = ref(null);
const loading = ref(true);
const resendingVerification = ref(false);
const error = ref(null);
const message = ref(null);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const [profileResponse, trafficResponse] = await Promise.all([
            billingApi.profile(),
            billingApi.traffic(),
        ]);

        profile.value = profileResponse.data;
        traffic.value = trafficResponse.data;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function resendVerificationEmail() {
    resendingVerification.value = true;
    error.value = null;
    message.value = null;

    try {
        await authApi.sendEmailVerification();
        message.value = t('dashboard.emailVerificationSent');
    } catch (e) {
        error.value = e.message;
    } finally {
        resendingVerification.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="page-stack">
        <div class="page-title">
            <div>
                <div class="eyebrow">{{ t('dashboard.title') }}</div>
                <h1>{{ profile?.user?.name || t('dashboard.fallbackName') }}</h1>
            </div>
            <div class="email-status">
                <v-chip
                    :class="profile?.user?.email_verified ? 'email-chip--verified' : 'email-chip--pending'"
                    :color="profile?.user?.email_verified ? 'success' : 'warning'"
                    variant="tonal"
                >
                    <span class="email-chip__text">{{ t('dashboard.emailPending') }}: {{ profile?.user?.email || '—' }}</span>
                    <Check v-if="profile?.user?.email_verified" :size="16" />
                </v-chip>
                <v-tooltip v-if="profile && !profile.user.email_verified" location="bottom">
                    <template #activator="{ props }">
                        <v-btn
                            v-bind="props"
                            :aria-label="t('dashboard.resendVerificationEmail')"
                            :loading="resendingVerification"
                            color="warning"
                            density="comfortable"
                            icon
                            size="small"
                            variant="tonal"
                            @click="resendVerificationEmail"
                        >
                            <MailPlus :size="16" />
                        </v-btn>
                    </template>
                    <span>{{ t('dashboard.resendVerificationEmail') }}</span>
                </v-tooltip>
            </div>
        </div>

        <v-alert v-if="message" type="success" variant="tonal">{{ message }}</v-alert>
        <v-alert v-if="error" type="error" variant="tonal">{{ error }}</v-alert>
        <v-skeleton-loader v-if="loading" type="card, card" />

        <template v-else>
            <SubscriptionCard :subscription="profile?.current_subscription" />
            <TrafficCard :traffic="traffic" />
        </template>
    </div>
</template>

<style scoped>
.page-stack {
    display: grid;
    gap: 14px;
}

.page-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.eyebrow {
    color: #61717d;
    font-size: 13px;
}

.email-status {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
    min-width: 0;
}

h1 {
    margin: 0;
    color: #18252f;
    font-size: 28px;
    line-height: 1.15;
}

.email-chip--verified {
    color: #137a4b;
}

.email-chip--pending {
    color: #9a620f;
}

.email-chip__text {
    overflow-wrap: anywhere;
}
</style>
