import { createApp } from 'vue';
import App from './spa/App.vue';
import router from './spa/router';
import vuetify from './spa/plugins/vuetify';

createApp(App)
    .use(router)
    .use(vuetify)
    .mount('#app');
