<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Check, MailPlus } from '@lucide/vue';
import { authApi } from '../api/auth';
import { billingApi } from '../api/billing';
import SubscriptionCard from '../components/SubscriptionCard.vue';
import TrafficCard from '../components/TrafficCard.vue';
import { useI18n } from '../composables/useI18n';
import { useTelegram } from '../composables/useTelegram';

const { t } = useI18n();
const telegram = useTelegram();
const route = useRoute();
const router = useRouter();
const profile = ref(null);
const traffic = ref(null);
const loading = ref(true);
const resendingVerification = ref(false);
const startingEmailMerge = ref(false);
const linkingTelegram = ref(false);
const mergeEmail = ref('');
const error = ref(null);
const message = ref(null);
const isTelegramOnly = computed(() => profile.value?.user?.telegram?.linked && !profile.value?.user?.email);
const canLinkTelegram = computed(() => profile.value?.user?.email && !profile.value?.user?.telegram?.linked);

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

async function startEmailMerge() {
    startingEmailMerge.value = true;
    error.value = null;
    message.value = null;

    try {
        await billingApi.startEmailMerge(mergeEmail.value);
        message.value = t('dashboard.mergeEmailSent');
        mergeEmail.value = '';
    } catch (e) {
        error.value = e.message;
    } finally {
        startingEmailMerge.value = false;
    }
}

async function linkTelegram() {
    error.value = null;
    message.value = null;

    if (!telegram.isTelegramMiniApp || !telegram.initData) {
        linkingTelegram.value = true;

        try {
            const response = await authApi.createTelegramLinkToken();
            window.location.href = response.bot_url;
        } catch (e) {
            error.value = e.message;
            linkingTelegram.value = false;
        }

        return;
    }

    linkingTelegram.value = true;

    try {
        await authApi.telegramLink(telegram.initData);
        message.value = t('dashboard.telegramLinked');
        await load();
    } catch (e) {
        error.value = e.message;
    } finally {
        linkingTelegram.value = false;
    }
}

async function confirmTelegramLinkToken(token) {
    if (!telegram.isTelegramMiniApp || !telegram.initData) {
        return false;
    }

    linkingTelegram.value = true;
    error.value = null;
    message.value = null;

    try {
        await authApi.confirmTelegramLinkToken(token, telegram.initData);
        await router.replace({ name: 'dashboard' });
        message.value = t('dashboard.telegramLinked');
        await load();
        return true;
    } catch (e) {
        error.value = e.message;
        loading.value = false;
        return true;
    } finally {
        linkingTelegram.value = false;
    }
}

onMounted(async () => {
    const linkToken = route.query.link_token;

    if (typeof linkToken === 'string' && linkToken !== '') {
        if (await confirmTelegramLinkToken(linkToken)) {
            return;
        }
    }

    await load();
});
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
                    <span class="email-chip__text">{{ t('dashboard.emailPending') }}: {{ profile?.user?.email || t('common.notSpecified') }}</span>
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
            <v-card v-if="isTelegramOnly" class="account-link-card pa-4" border>
                <div>
                    <div class="account-link-title">{{ t('dashboard.addEmailTitle') }}</div>
                    <p>{{ t('dashboard.addEmailText') }}</p>
                </div>
                <form class="account-link-form" @submit.prevent="startEmailMerge">
                    <v-text-field
                        v-model="mergeEmail"
                        density="compact"
                        label="Email"
                        type="email"
                        autocomplete="email"
                        variant="outlined"
                        hide-details
                    />
                    <v-btn
                        color="primary"
                        type="submit"
                        :loading="startingEmailMerge"
                        :disabled="!mergeEmail"
                    >
                        {{ t('dashboard.sendMergeEmail') }}
                    </v-btn>
                </form>
            </v-card>
            <v-card v-if="canLinkTelegram" class="account-link-card pa-4" border>
                <div>
                    <div class="account-link-title">{{ t('dashboard.linkTelegramTitle') }}</div>
                    <p>{{ t('dashboard.linkTelegramText') }}</p>
                </div>
                <div class="account-link-actions">
                    <v-btn
                        color="primary"
                        variant="tonal"
                        :loading="linkingTelegram"
                        @click="linkTelegram"
                    >
                        {{ telegram.isTelegramMiniApp ? t('dashboard.linkTelegramButton') : t('dashboard.openTelegramBot') }}
                    </v-btn>
                </div>
            </v-card>
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

.account-link-card {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(260px, 420px);
    align-items: center;
    gap: 14px;
}

.account-link-title {
    color: #18252f;
    font-weight: 700;
}

.account-link-card p {
    margin: 4px 0 0;
    color: #61717d;
    font-size: 14px;
}

.account-link-form {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
}

.account-link-actions {
    display: flex;
    justify-content: flex-end;
}

@media (max-width: 720px) {
    .page-title,
    .account-link-card,
    .account-link-form {
        grid-template-columns: 1fr;
    }

    .page-title {
        display: grid;
    }

    .account-link-actions {
        justify-content: stretch;
    }

    .account-link-actions :deep(.v-btn) {
        width: 100%;
    }
}
</style>
