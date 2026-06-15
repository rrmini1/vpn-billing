<script setup>
import { onMounted, ref } from 'vue';
import { billingApi } from '../api/billing';
import SubscriptionCard from '../components/SubscriptionCard.vue';
import TrafficCard from '../components/TrafficCard.vue';
import { useI18n } from '../composables/useI18n';

const { t } = useI18n();
const profile = ref(null);
const traffic = ref(null);
const loading = ref(true);
const error = ref(null);

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

onMounted(load);
</script>

<template>
    <div class="page-stack">
        <div class="page-title">
            <div>
                <div class="eyebrow">{{ t('dashboard.title') }}</div>
                <h1>{{ profile?.user?.name || t('dashboard.fallbackName') }}</h1>
            </div>
            <v-chip :color="profile?.user?.email_verified ? 'success' : 'warning'" variant="tonal">
                {{ profile?.user?.email_verified ? t('dashboard.emailVerified') : t('dashboard.emailPending') }}
            </v-chip>
        </div>

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

h1 {
    margin: 0;
    color: #18252f;
    font-size: 28px;
    line-height: 1.15;
}
</style>
