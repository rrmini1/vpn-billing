<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { LogOut, Receipt, Server, UserRound } from '@lucide/vue';
import { useAuth } from './composables/useAuth';
import { useI18n } from './composables/useI18n';

const auth = useAuth();
const { locale, setLocale, t } = useI18n();
const route = useRoute();
const router = useRouter();

const isAuthed = computed(() => Boolean(auth.state.user));
const showShell = computed(() => auth.state.booted && isAuthed.value);

watch(locale, () => {
    document.title = t('appName');
}, { immediate: true });

onMounted(async () => {
    window.addEventListener('api:unauthorized', handleUnauthorized);

    await auth.bootstrap();

    if (!auth.state.user && route.name !== 'login') {
        await router.replace({ name: 'login' });
    }

    if (auth.state.user && route.name === 'login') {
        await router.replace({ name: 'dashboard' });
    }
});

onUnmounted(() => {
    window.removeEventListener('api:unauthorized', handleUnauthorized);
});

async function handleUnauthorized() {
    auth.state.user = null;

    if (router.currentRoute.value.name !== 'login') {
        await router.replace({ name: 'login' });
    }
}

async function logout() {
    await auth.logout();
    await router.replace({ name: 'login' });
}
</script>

<template>
    <v-app>
        <v-main>
            <div v-if="!auth.state.booted" class="app-loading">
                <v-progress-circular indeterminate color="primary" />
            </div>

            <template v-else>
                <v-layout v-if="showShell" class="app-shell">
                    <v-app-bar color="surface" elevation="0" border>
                        <v-app-bar-title class="font-weight-bold">{{ t('appName') }}</v-app-bar-title>
                        <v-btn-toggle
                            :model-value="locale"
                            class="locale-toggle"
                            color="primary"
                            density="compact"
                            mandatory
                            variant="outlined"
                            @update:model-value="setLocale"
                        >
                            <v-btn value="ru" size="small">RU</v-btn>
                            <v-btn value="en" size="small">EN</v-btn>
                        </v-btn-toggle>
                        <v-btn icon variant="text" :aria-label="t('actions.logout')" @click="logout">
                            <LogOut :size="20" />
                        </v-btn>
                    </v-app-bar>

                    <v-main>
                        <div class="app-content">
                            <router-view />
                        </div>
                    </v-main>

                    <v-bottom-navigation grow mandatory color="primary" elevation="8">
                        <v-btn :to="{ name: 'dashboard' }" value="dashboard">
                            <UserRound :size="20" />
                            <span>{{ t('navigation.dashboard') }}</span>
                        </v-btn>
                        <v-btn :to="{ name: 'plans' }" value="plans">
                            <Server :size="20" />
                            <span>{{ t('navigation.plans') }}</span>
                        </v-btn>
                        <v-btn :to="{ name: 'payments' }" value="payments">
                            <Receipt :size="20" />
                            <span>{{ t('navigation.payments') }}</span>
                        </v-btn>
                    </v-bottom-navigation>
                </v-layout>

                <router-view v-else />
            </template>
        </v-main>
    </v-app>
</template>

<style scoped>
.app-loading {
    min-height: 100vh;
    display: grid;
    place-items: center;
}

.app-shell {
    min-height: 100vh;
}

.app-content {
    width: min(100%, 960px);
    margin: 0 auto;
    padding: 16px 16px 96px;
}

.locale-toggle {
    margin-right: 8px;
}
</style>
