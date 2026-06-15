export function useTelegram() {
    const webApp = window.Telegram?.WebApp || null;

    if (webApp) {
        webApp.ready();
        webApp.expand();
    }

    return {
        isTelegramMiniApp: Boolean(webApp?.initData),
        initData: webApp?.initData || '',
        user: webApp?.initDataUnsafe?.user || null,
    };
}
