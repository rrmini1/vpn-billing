export function formatTraffic(bytes, options = {}) {
    if (bytes === null || bytes === undefined) {
        return '—';
    }

    const value = Number(bytes);

    if (!Number.isFinite(value)) {
        return '—';
    }

    const megabyte = 1024 * 1024;
    const gigabyte = megabyte * 1024;
    const locale = options.locale || 'ru-RU';
    const units = options.units || { mb: 'МБ', gb: 'ГБ' };

    if (value < gigabyte) {
        return `${formatNumber(value / megabyte, locale)} ${units.mb}`;
    }

    return `${formatNumber(value / gigabyte, locale)} ${units.gb}`;
}

function formatNumber(value, locale) {
    return new Intl.NumberFormat(locale, {
        maximumFractionDigits: value >= 10 ? 0 : 2,
    }).format(value);
}
