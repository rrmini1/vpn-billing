const jsonHeaders = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

function xsrfToken() {
    const cookie = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : null;
}

async function csrf() {
    await fetch('/sanctum/csrf-cookie', {
        credentials: 'include',
        headers: { Accept: 'application/json' },
    });
}

export async function api(path, options = {}) {
    const method = options.method || 'GET';
    const headers = { ...jsonHeaders, ...(options.headers || {}) };

    if (method !== 'GET') {
        await csrf();
        const token = xsrfToken();

        if (token) {
            headers['X-XSRF-TOKEN'] = token;
        }
    }

    const response = await fetch(path, {
        ...options,
        method,
        credentials: 'include',
        headers,
        body: options.body ? JSON.stringify(options.body) : undefined,
    });

    if (response.status === 204) {
        return null;
    }

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(data.message || 'Request failed.');
        error.status = response.status;
        error.data = data;

        if (response.status === 401) {
            window.dispatchEvent(new CustomEvent('api:unauthorized'));
        }

        throw error;
    }

    return data;
}
