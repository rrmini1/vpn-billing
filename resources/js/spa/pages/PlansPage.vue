<script setup>
import { computed, onMounted, ref } from 'vue';
import { billingApi } from '../api/billing';
import { useI18n } from '../composables/useI18n';
import { formatTraffic } from '../utils/formatBytes';

const { locale, planName, t } = useI18n();
const plans = ref([]);
const loading = ref(true);
const busyCode = ref(null);
const message = ref(null);
const error = ref(null);
const trafficFormatOptions = computed(() => ({
    locale: locale.value === 'ru' ? 'ru-RU' : 'en-US',
    units: {
        mb: t('traffic.mb'),
        gb: t('traffic.gb'),
    },
}));

async function load() {
    loading.value = true;
    const response = await billingApi.plans();
    plans.value = response.data;
    loading.value = false;
}

async function activate(plan) {
    busyCode.value = plan.code;
    message.value = null;
    error.value = null;

    try {
        if (plan.code === 'trial') {
            await billingApi.trial();
            message.value = t('plans.trialActivated');
        } else {
            const payment = await billingApi.createPayment(plan.code);
            await billingApi.simulatePaid(payment.data.id);
            message.value = t('plans.paymentQueued');
        }
    } catch (e) {
        error.value = e.message;
    } finally {
        busyCode.value = null;
    }
}

onMounted(load);
</script>

<template>
    <div class="page-stack">
        <div class="page-title">
            <div>
                <div class="eyebrow">{{ t('plans.title') }}</div>
                <h1>{{ t('plans.heading') }}</h1>
            </div>
        </div>

        <v-alert v-if="message" type="success" variant="tonal">{{ message }}</v-alert>
        <v-alert v-if="error" type="error" variant="tonal">{{ error }}</v-alert>
        <v-skeleton-loader v-if="loading" type="card, card, card" />

        <div v-else class="plan-grid">
            <v-card v-for="plan in plans" :key="plan.code" class="pa-4" border>
                <div class="plan-head">
                    <div>
                        <h2>{{ planName(plan) }}</h2>
                        <div class="traffic">{{ formatTraffic(plan.traffic_limit_bytes, trafficFormatOptions) }}</div>
                    </div>
                    <v-chip color="primary" variant="tonal">{{ plan.currency }}</v-chip>
                </div>
                <div class="price">{{ plan.price_amount / 100 }} {{ plan.currency }}</div>
                <v-btn block color="primary" :loading="busyCode === plan.code" @click="activate(plan)">
                    {{ plan.code === 'trial' ? t('plans.trialButton') : t('plans.buyButton') }}
                </v-btn>
            </v-card>
        </div>
    </div>
</template>

<style scoped>
.page-stack {
    display: grid;
    gap: 14px;
}

.eyebrow {
    color: #61717d;
    font-size: 13px;
}

h1,
h2 {
    margin: 0;
    color: #18252f;
}

.plan-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
}

.plan-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.traffic {
    margin-top: 8px;
    color: #61717d;
}

.price {
    margin: 20px 0;
    color: #18252f;
    font-size: 24px;
    font-weight: 800;
}
</style>
