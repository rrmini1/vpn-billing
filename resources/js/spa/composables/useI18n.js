import { computed, reactive } from 'vue';

const storageKey = 'vpn-billing-locale';
const fallbackLocale = 'ru';
const supportedLocales = ['ru', 'en'];

const messages = {
    ru: {
        appName: 'Cors Port Solutions',
        navigation: {
            dashboard: 'Кабинет',
            plans: 'Тарифы',
            payments: 'Платежи',
            admin: 'Админ',
        },
        actions: {
            logout: 'Выйти',
        },
        auth: {
            loginTab: 'Вход',
            registerTab: 'Регистрация',
            telegramTitle: 'Вход через Telegram',
            telegramText: 'Мы используем ваш Telegram-профиль для входа. Email и пароль не нужны.',
            telegramRetry: 'Повторить вход',
            telegramOpenHint: 'Откройте кабинет через кнопку в Telegram-боте.',
            password: 'Пароль',
            name: 'Имя',
            passwordConfirmation: 'Повтор пароля',
            loginButton: 'Войти',
            registerButton: 'Создать аккаунт',
            forgotPassword: 'Забыли пароль?',
        },
        passwordReset: {
            forgotTitle: 'Восстановление пароля',
            forgotText: 'Укажите email, и мы отправим ссылку для смены пароля.',
            sendLink: 'Отправить ссылку',
            linkSent: 'Ссылка для восстановления отправлена на почту',
            resetTitle: 'Новый пароль',
            savePassword: 'Сохранить пароль',
            passwordChanged: 'Пароль изменен. Теперь можно войти.',
            missingToken: 'В ссылке нет токена восстановления.',
            backToLogin: 'Вернуться ко входу',
        },
        dashboard: {
            title: 'Кабинет',
            fallbackName: 'Solutions',
            emailVerified: 'email ok',
            emailPending: 'email',
            emailVerificationSent: 'Письмо отправлено',
            resendVerificationEmail: 'выслать повторное письмо',
        },
        emailVerified: {
            title: 'Email подтвержден',
            text: 'Адрес почты успешно подтвержден. Теперь можно вернуться в кабинет.',
            action: 'Перейти в кабинет',
        },
        subscription: {
            title: 'Подписка',
            emptyTitle: 'Нет активной подписки',
            emptyText: 'Выберите trial или тариф, чтобы получить ссылку подключения.',
            copyLink: 'Скопировать ссылку подписки',
            openLink: 'Открыть ссылку подписки',
        },
        traffic: {
            title: 'Трафик',
            used: 'Использовано',
            remaining: 'Осталось',
            limit: 'Лимит',
            mb: 'МБ',
            gb: 'ГБ',
        },
        plans: {
            title: 'Тарифы',
            heading: 'Выберите пакет',
            trialButton: 'Взять trial',
            buyButton: 'Купить',
            trialActivated: 'Trial активирован',
            paymentQueued: 'Платеж создан, активация отправлена в очередь',
            paymentCreated: 'Платеж создан',
        },
        payments: {
            title: 'Платежи',
            heading: 'История',
            empty: 'Платежей пока нет',
            local: 'local',
        },
        admin: {
            title: 'Админ',
            heading: 'Управление',
            dashboard: 'Сводка',
            users: 'Клиенты',
            plans: 'Тарифы',
            payments: 'Платежи',
            search: 'Поиск',
            save: 'Сохранить',
            saved: 'Сохранено',
            totalUsers: 'Клиенты',
            activeSubscriptions: 'Активные подписки',
            paidPayments: 'Оплачено',
            revenue: 'Выручка',
            telegram: 'Telegram',
            subscription: 'Подписка',
            amount: 'Сумма',
            status: 'Статус',
            provider: 'Провайдер',
            active: 'Активен',
            trafficGb: 'ГБ',
            priceRub: 'RUB',
            sortOrder: 'Порядок',
        },
        plansByCode: {
            trial: 'Тест',
            start: 'Старт',
            standard: 'Стандарт',
            premium: 'Премиум',
        },
    },
    en: {
        appName: 'Cors Port Solutions',
        navigation: {
            dashboard: 'Account',
            plans: 'Plans',
            payments: 'Payments',
            admin: 'Admin',
        },
        actions: {
            logout: 'Logout',
        },
        auth: {
            loginTab: 'Login',
            registerTab: 'Register',
            telegramTitle: 'Telegram login',
            telegramText: 'We use your Telegram profile to sign you in. No email or password needed.',
            telegramRetry: 'Try again',
            telegramOpenHint: 'Open the account from the Telegram bot button.',
            password: 'Password',
            name: 'Name',
            passwordConfirmation: 'Repeat password',
            loginButton: 'Login',
            registerButton: 'Create account',
            forgotPassword: 'Forgot password?',
        },
        passwordReset: {
            forgotTitle: 'Reset password',
            forgotText: 'Enter your email and we will send a password reset link.',
            sendLink: 'Send link',
            linkSent: 'Password reset link has been sent',
            resetTitle: 'New password',
            savePassword: 'Save password',
            passwordChanged: 'Password changed. You can log in now.',
            missingToken: 'The reset token is missing from this link.',
            backToLogin: 'Back to login',
        },
        dashboard: {
            title: 'Account',
            fallbackName: 'Solutions',
            emailVerified: 'email ok',
            emailPending: 'email',
            emailVerificationSent: 'Email sent',
            resendVerificationEmail: 'send verification email again',
        },
        emailVerified: {
            title: 'Email verified',
            text: 'Your email address has been verified. You can return to your account.',
            action: 'Go to account',
        },
        subscription: {
            title: 'Subscription',
            emptyTitle: 'No active subscription',
            emptyText: 'Choose trial or a plan to get your connection link.',
            copyLink: 'Copy subscription link',
            openLink: 'Open subscription link',
        },
        traffic: {
            title: 'Traffic',
            used: 'Used',
            remaining: 'Remaining',
            limit: 'Limit',
            mb: 'MB',
            gb: 'GB',
        },
        plans: {
            title: 'Plans',
            heading: 'Choose a package',
            trialButton: 'Start trial',
            buyButton: 'Buy',
            trialActivated: 'Trial activated',
            paymentQueued: 'Payment created, activation has been queued',
            paymentCreated: 'Payment created',
        },
        payments: {
            title: 'Payments',
            heading: 'History',
            empty: 'No payments yet',
            local: 'local',
        },
        admin: {
            title: 'Admin',
            heading: 'Management',
            dashboard: 'Dashboard',
            users: 'Users',
            plans: 'Plans',
            payments: 'Payments',
            search: 'Search',
            save: 'Save',
            saved: 'Saved',
            totalUsers: 'Users',
            activeSubscriptions: 'Active subscriptions',
            paidPayments: 'Paid payments',
            revenue: 'Revenue',
            telegram: 'Telegram',
            subscription: 'Subscription',
            amount: 'Amount',
            status: 'Status',
            provider: 'Provider',
            active: 'Active',
            trafficGb: 'GB',
            priceRub: 'RUB',
            sortOrder: 'Order',
        },
        plansByCode: {
            trial: 'Trial',
            start: 'Start',
            standard: 'Standard',
            premium: 'Premium',
        },
    },
};

const state = reactive({
    locale: initialLocale(),
});

export function useI18n() {
    const currentLocale = computed(() => state.locale);

    function t(key) {
        return getMessage(state.locale, key) ?? getMessage(fallbackLocale, key) ?? key;
    }

    function setLocale(locale) {
        state.locale = supportedLocales.includes(locale) ? locale : fallbackLocale;
        localStorage.setItem(storageKey, state.locale);
        document.documentElement.lang = state.locale;
    }

    function planName(plan) {
        if (!plan) {
            return '';
        }

        return t(`plansByCode.${plan.code}`) || plan.name;
    }

    document.documentElement.lang = state.locale;

    return {
        locale: currentLocale,
        supportedLocales,
        setLocale,
        t,
        planName,
    };
}

function initialLocale() {
    const savedLocale = localStorage.getItem(storageKey);

    if (supportedLocales.includes(savedLocale)) {
        return savedLocale;
    }

    const browserLocale = navigator.language?.slice(0, 2);

    return supportedLocales.includes(browserLocale) ? browserLocale : fallbackLocale;
}

function getMessage(locale, key) {
    return key
        .split('.')
        .reduce((value, segment) => value?.[segment], messages[locale]);
}
