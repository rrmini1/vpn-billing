import '@mdi/font/css/materialdesignicons.css';
import 'vuetify/styles';
import { createVuetify } from 'vuetify';
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';

export default createVuetify({
    components,
    directives,
    theme: {
        defaultTheme: 'light',
        themes: {
            light: {
                dark: false,
                colors: {
                    background: '#f7f8fb',
                    surface: '#ffffff',
                    primary: '#1f7a8c',
                    secondary: '#6c5ce7',
                    accent: '#f2a541',
                    error: '#c93c3c',
                    info: '#2f80ed',
                    success: '#23845b',
                    warning: '#b7791f',
                },
            },
        },
    },
    defaults: {
        VBtn: {
            rounded: 'lg',
            height: 44,
        },
        VCard: {
            rounded: 'lg',
            elevation: 0,
        },
        VTextField: {
            variant: 'outlined',
            density: 'comfortable',
        },
    },
});
