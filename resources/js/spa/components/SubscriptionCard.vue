<script setup>
import { Copy, ExternalLink } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from '../composables/useI18n';
import { formatTraffic } from '../utils/formatBytes';

const props = defineProps({
    subscription: { type: Object, default: null },
});

const { locale, planName, t } = useI18n();
const trafficFormatOptions = computed(() => ({
    locale: locale.value === 'ru' ? 'ru-RU' : 'en-US',
    units: {
        mb: t('traffic.mb'),
        gb: t('traffic.gb'),
    },
}));

async function copyLink() {
    const url = props.subscription?.marzban_user?.subscription_url;

    if (url) {
        await navigator.clipboard.writeText(url);
    }
}
</script>

<template>
    <v-card class="pa-4" border>
        <div class="section-head">
            <div>
                <div class="eyebrow">{{ t('subscription.title') }}</div>
                <h2>{{ subscription ? planName(subscription.plan) : t('subscription.emptyTitle') }}</h2>
            </div>
            <v-chip v-if="subscription" color="success" variant="tonal" size="small">
                {{ subscription.status }}
            </v-chip>
        </div>

        <template v-if="subscription">
            <div class="subline">
                {{ formatTraffic(subscription.plan.traffic_limit_bytes, trafficFormatOptions) }}
            </div>

            <div class="link-row">
                <v-text-field
                    :model-value="subscription.marzban_user?.subscription_url"
                    readonly
                    hide-details
                    density="compact"
                />
                <v-btn icon variant="tonal" :aria-label="t('subscription.copyLink')" @click="copyLink">
                    <Copy :size="18" />
                </v-btn>
                <v-btn
                    icon
                    variant="tonal"
                    :aria-label="t('subscription.openLink')"
                    :href="subscription.marzban_user?.subscription_url"
                    target="_blank"
                >
                    <ExternalLink :size="18" />
                </v-btn>
            </div>
        </template>

        <v-alert v-else type="info" variant="tonal" class="mt-3">
            {{ t('subscription.emptyText') }}
        </v-alert>
    </v-card>
</template>

<style scoped>
.section-head,
.link-row {
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
    line-height: 1.2;
}

.subline {
    margin: 14px 0;
    color: #61717d;
}

.link-row {
    align-items: stretch;
}
</style>
