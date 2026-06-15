<script setup>
import { onMounted, ref } from 'vue';
import { billingApi } from '../api/billing';
import { useI18n } from '../composables/useI18n';

const { planName, t } = useI18n();
const payments = ref([]);
const loading = ref(true);
const error = ref(null);

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const response = await billingApi.payments();
        payments.value = response.data;
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
                <div class="eyebrow">{{ t('payments.title') }}</div>
                <h1>{{ t('payments.heading') }}</h1>
            </div>
        </div>

        <v-alert v-if="error" type="error" variant="tonal">{{ error }}</v-alert>
        <v-skeleton-loader v-if="loading" type="list-item-three-line, list-item-three-line" />

        <v-card v-else-if="payments.length === 0" class="pa-4" border>
            {{ t('payments.empty') }}
        </v-card>

        <v-card v-for="payment in payments" v-else :key="payment.id" class="pa-4" border>
            <div class="payment-row">
                <div>
                    <h2>{{ planName(payment.plan) }}</h2>
                    <div class="meta">{{ payment.provider }} · {{ payment.provider_payment_id || t('payments.local') }}</div>
                </div>
                <div class="chips">
                    <v-chip color="primary" variant="tonal" size="small">{{ payment.status }}</v-chip>
                    <v-chip v-if="payment.activation_status" color="secondary" variant="tonal" size="small">
                        {{ payment.activation_status }}
                    </v-chip>
                </div>
            </div>
            <div class="amount">{{ payment.amount / 100 }} {{ payment.currency }}</div>
        </v-card>
    </div>
</template>

<style scoped>
.page-stack {
    display: grid;
    gap: 12px;
}

.eyebrow,
.meta {
    color: #61717d;
    font-size: 13px;
}

h1,
h2 {
    margin: 0;
    color: #18252f;
}

.payment-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: flex-end;
}

.amount {
    margin-top: 12px;
    font-size: 20px;
    font-weight: 800;
}
</style>
