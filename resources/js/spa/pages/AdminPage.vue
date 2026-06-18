<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { billingApi } from '../api/billing';
import { useI18n } from '../composables/useI18n';
import { formatTraffic } from '../utils/formatBytes';

const { locale, planName, t } = useI18n();
const tab = ref('dashboard');
const loading = ref(true);
const error = ref(null);
const message = ref(null);
const search = ref('');
const dashboard = ref(null);
const users = ref([]);
const plans = ref([]);
const payments = ref([]);
const planDrafts = reactive({});
const userLimitDrafts = reactive({});
const savingPlanId = ref(null);
const savingUserLimitId = ref(null);

const trafficFormatOptions = computed(() => ({
    locale: locale.value === 'ru' ? 'ru-RU' : 'en-US',
    units: {
        mb: t('traffic.mb'),
        gb: t('traffic.gb'),
    },
}));

async function loadAll() {
    loading.value = true;
    error.value = null;

    try {
        await Promise.all([
            loadDashboard(),
            loadUsers(),
            loadPlans(),
            loadPayments(),
        ]);
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function loadDashboard() {
    const response = await billingApi.adminDashboard();
    dashboard.value = response.data;
}

async function loadUsers() {
    const response = await billingApi.adminUsers({
        search: search.value || '',
        per_page: 20,
    });
    users.value = response.data;
    users.value.forEach((user) => {
        const dataLimitBytes = user.current_subscription?.marzban_user?.data_limit_bytes;

        userLimitDrafts[user.id] = dataLimitBytes ? bytesToGb(dataLimitBytes) : null;
    });
}

async function loadPlans() {
    const response = await billingApi.adminPlans();
    plans.value = response.data;
    plans.value.forEach((plan) => {
        planDrafts[plan.id] = {
            code: plan.code,
            name: plan.name,
            traffic_gb: bytesToGb(plan.traffic_limit_bytes),
            price_rub: plan.price_amount / 100,
            currency: plan.currency,
            is_active: plan.is_active,
            sort_order: plan.sort_order,
        };
    });
}

async function loadPayments() {
    const response = await billingApi.adminPayments({ per_page: 20 });
    payments.value = response.data;
}

async function savePlan(plan) {
    const draft = planDrafts[plan.id];
    savingPlanId.value = plan.id;
    error.value = null;
    message.value = null;

    try {
        await billingApi.updateAdminPlan(plan.id, {
            code: draft.code,
            name: draft.name,
            traffic_limit_bytes: gbToBytes(draft.traffic_gb),
            price_amount: rubToMinor(draft.price_rub),
            currency: draft.currency,
            is_active: Boolean(draft.is_active),
            sort_order: Number(draft.sort_order),
        });
        message.value = t('admin.saved');
        await Promise.all([loadPlans(), loadDashboard()]);
    } catch (e) {
        error.value = e.message;
    } finally {
        savingPlanId.value = null;
    }
}

async function saveUserLimit(user) {
    savingUserLimitId.value = user.id;
    error.value = null;
    message.value = null;

    try {
        const response = await billingApi.updateAdminUserMarzbanLimit(user.id, {
            data_limit_bytes: gbToBytes(userLimitDrafts[user.id]),
        });
        const index = users.value.findIndex((item) => item.id === user.id);

        if (index !== -1) {
            users.value[index] = response.data;
            userLimitDrafts[user.id] = bytesToGb(response.data.current_subscription?.marzban_user?.data_limit_bytes);
        }

        message.value = t('admin.limitSaved');
        await loadDashboard();
    } catch (e) {
        error.value = e.message;
    } finally {
        savingUserLimitId.value = null;
    }
}

watch(search, () => {
    loadUsers();
});

onMounted(loadAll);

function bytesToGb(bytes) {
    return Math.round((Number(bytes) / 1024 / 1024 / 1024) * 100) / 100;
}

function gbToBytes(gb) {
    return Math.round(Number(gb) * 1024 * 1024 * 1024);
}

function rubToMinor(value) {
    return Math.round(Number(value) * 100);
}

function money(amount, currency = 'RUB') {
    return `${new Intl.NumberFormat(locale.value === 'ru' ? 'ru-RU' : 'en-US').format(Number(amount) / 100)} ${currency}`;
}
</script>

<template>
    <div class="page-stack">
        <div class="page-title">
            <div>
                <div class="eyebrow">{{ t('admin.title') }}</div>
                <h1>{{ t('admin.heading') }}</h1>
            </div>
        </div>

        <v-alert v-if="message" type="success" variant="tonal">{{ message }}</v-alert>
        <v-alert v-if="error" type="error" variant="tonal">{{ error }}</v-alert>
        <v-skeleton-loader v-if="loading" type="card, table" />

        <template v-else>
            <v-tabs v-model="tab" color="primary" density="comfortable">
                <v-tab value="dashboard">{{ t('admin.dashboard') }}</v-tab>
                <v-tab value="users">{{ t('admin.users') }}</v-tab>
                <v-tab value="plans">{{ t('admin.plans') }}</v-tab>
                <v-tab value="payments">{{ t('admin.payments') }}</v-tab>
            </v-tabs>

            <v-window v-model="tab">
                <v-window-item value="dashboard">
                    <div class="stats-grid">
                        <v-card class="pa-4" border>
                            <div class="meta">{{ t('admin.totalUsers') }}</div>
                            <div class="stat">{{ dashboard.users.total }}</div>
                        </v-card>
                        <v-card class="pa-4" border>
                            <div class="meta">{{ t('admin.activeSubscriptions') }}</div>
                            <div class="stat">{{ dashboard.subscriptions.active }}</div>
                        </v-card>
                        <v-card class="pa-4" border>
                            <div class="meta">{{ t('admin.paidPayments') }}</div>
                            <div class="stat">{{ dashboard.payments.paid }}</div>
                        </v-card>
                        <v-card class="pa-4" border>
                            <div class="meta">{{ t('admin.revenue') }}</div>
                            <div class="stat">{{ money(dashboard.payments.revenue_amount, dashboard.payments.currency) }}</div>
                        </v-card>
                    </div>
                </v-window-item>

                <v-window-item value="users">
                    <v-text-field
                        v-model="search"
                        class="mt-3"
                        density="compact"
                        :label="t('admin.search')"
                        variant="outlined"
                        clearable
                    />
                    <v-card border>
                        <v-table density="comfortable">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>{{ t('admin.telegram') }}</th>
                                    <th>{{ t('admin.subscription') }}</th>
                                    <th>{{ t('admin.marzbanUser') }}</th>
                                    <th>{{ t('admin.limit') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="user in users" :key="user.id">
                                    <td>
                                        <strong>{{ user.name }}</strong>
                                        <div class="meta">{{ user.email || t('common.notSpecified') }}</div>
                                    </td>
                                    <td>{{ user.telegram.username ? `@${user.telegram.username}` : '—' }}</td>
                                    <td>
                                        <template v-if="user.current_subscription">
                                            {{ planName(user.current_subscription.plan) }}
                                            <div class="meta">
                                                {{ formatTraffic(user.current_subscription.plan.traffic_limit_bytes, trafficFormatOptions) }}
                                            </div>
                                        </template>
                                        <template v-else>—</template>
                                    </td>
                                    <td>
                                        <template v-if="user.current_subscription?.marzban_user">
                                            <code>{{ user.current_subscription.marzban_user.username }}</code>
                                            <div class="meta">{{ user.current_subscription.marzban_user.status }}</div>
                                        </template>
                                        <template v-else>—</template>
                                    </td>
                                    <td>
                                        <template v-if="user.current_subscription?.marzban_user">
                                            {{ formatTraffic(user.current_subscription.marzban_user.data_limit_bytes, trafficFormatOptions) }}
                                            <div class="limit-edit">
                                                <v-text-field
                                                    v-model.number="userLimitDrafts[user.id]"
                                                    class="limit-input"
                                                    density="compact"
                                                    type="number"
                                                    min="1"
                                                    step="1"
                                                    :label="t('admin.limitGb')"
                                                    variant="outlined"
                                                    hide-details
                                                />
                                            </div>
                                        </template>
                                        <template v-else>—</template>
                                    </td>
                                    <td>
                                        <v-btn
                                            v-if="user.current_subscription?.marzban_user"
                                            color="primary"
                                            size="small"
                                            variant="tonal"
                                            :loading="savingUserLimitId === user.id"
                                            @click="saveUserLimit(user)"
                                        >
                                            {{ t('admin.saveLimit') }}
                                        </v-btn>
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>
                </v-window-item>

                <v-window-item value="plans">
                    <div class="plan-list">
                        <v-card v-for="plan in plans" :key="plan.id" class="pa-4" border>
                            <div class="plan-edit-grid">
                                <v-text-field v-model="planDrafts[plan.id].code" label="Code" density="compact" variant="outlined" />
                                <v-text-field v-model="planDrafts[plan.id].name" label="Name" density="compact" variant="outlined" />
                                <v-text-field
                                    v-model.number="planDrafts[plan.id].traffic_gb"
                                    :label="t('admin.trafficGb')"
                                    type="number"
                                    density="compact"
                                    variant="outlined"
                                />
                                <v-text-field
                                    v-model.number="planDrafts[plan.id].price_rub"
                                    :label="t('admin.priceRub')"
                                    type="number"
                                    density="compact"
                                    variant="outlined"
                                />
                                <v-text-field v-model="planDrafts[plan.id].currency" label="Currency" density="compact" variant="outlined" />
                                <v-text-field
                                    v-model.number="planDrafts[plan.id].sort_order"
                                    :label="t('admin.sortOrder')"
                                    type="number"
                                    density="compact"
                                    variant="outlined"
                                />
                            </div>
                            <div class="plan-actions">
                                <v-switch
                                    v-model="planDrafts[plan.id].is_active"
                                    :label="t('admin.active')"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                />
                                <v-btn color="primary" :loading="savingPlanId === plan.id" @click="savePlan(plan)">
                                    {{ t('admin.save') }}
                                </v-btn>
                            </div>
                        </v-card>
                    </div>
                </v-window-item>

                <v-window-item value="payments">
                    <v-card class="mt-3" border>
                        <v-table density="comfortable">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>{{ t('admin.amount') }}</th>
                                    <th>{{ t('admin.status') }}</th>
                                    <th>{{ t('admin.provider') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="payment in payments" :key="payment.id">
                                    <td>{{ payment.user?.email || t('common.notSpecified') }}</td>
                                    <td>{{ money(payment.amount, payment.currency) }}</td>
                                    <td><v-chip size="small" color="primary" variant="tonal">{{ payment.status }}</v-chip></td>
                                    <td>
                                        {{ payment.provider }}
                                        <div class="meta">{{ payment.provider_payment_id || '—' }}</div>
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>
                </v-window-item>
            </v-window>
        </template>
    </div>
</template>

<style scoped>
.page-stack {
    display: grid;
    gap: 14px;
}

.eyebrow,
.meta {
    color: #61717d;
    font-size: 13px;
}

h1 {
    margin: 0;
    color: #18252f;
}

.stats-grid,
.plan-list {
    display: grid;
    gap: 12px;
    margin-top: 12px;
}

.stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

.stat {
    margin-top: 8px;
    color: #18252f;
    font-size: 26px;
    font-weight: 800;
}

.plan-edit-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
}

.plan-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.limit-edit {
    margin-top: 8px;
}

.limit-input {
    width: 120px;
}

code {
    color: #1d4f67;
    font-size: 13px;
}

th,
td {
    white-space: nowrap;
}
</style>
