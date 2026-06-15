<script setup>
import { computed } from 'vue';
import { useI18n } from '../composables/useI18n';
import MetricTile from './MetricTile.vue';
import { formatTraffic } from '../utils/formatBytes';

defineProps({
    traffic: { type: Object, default: null },
});

const { locale, t } = useI18n();
const trafficFormatOptions = computed(() => ({
    locale: locale.value === 'ru' ? 'ru-RU' : 'en-US',
    units: {
        mb: t('traffic.mb'),
        gb: t('traffic.gb'),
    },
}));
</script>

<template>
    <v-card class="pa-4" border>
        <div class="section-head">
            <div>
                <div class="eyebrow">{{ t('traffic.title') }}</div>
                <h2>{{ traffic?.usage_percent ?? 0 }}%</h2>
            </div>
            <v-chip v-if="traffic?.status" color="primary" variant="tonal" size="small">
                {{ traffic.status }}
            </v-chip>
        </div>

        <v-progress-linear
            class="my-4"
            color="primary"
            height="10"
            rounded
            :model-value="traffic?.usage_percent || 0"
        />

        <div class="metric-grid">
            <MetricTile :label="t('traffic.used')" :value="formatTraffic(traffic?.used_traffic_bytes, trafficFormatOptions)" />
            <MetricTile :label="t('traffic.remaining')" :value="formatTraffic(traffic?.remaining_traffic_bytes, trafficFormatOptions)" />
            <MetricTile :label="t('traffic.limit')" :value="formatTraffic(traffic?.data_limit_bytes, trafficFormatOptions)" />
        </div>
    </v-card>
</template>

<style scoped>
.section-head {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
}

.eyebrow {
    color: #61717d;
    font-size: 13px;
}

h2 {
    margin: 2px 0 0;
    color: #18252f;
    font-size: 22px;
}

.metric-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

@media (max-width: 640px) {
    .metric-grid {
        grid-template-columns: 1fr;
    }
}
</style>
