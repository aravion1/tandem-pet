import { createRouter, createWebHashHistory } from 'vue-router';
import ActivationForm from './components/ActivationForm.vue';
import LoginForm from './components/LoginForm.vue';
import DiscussionsPage from './components/DiscussionsPage.vue';

const emptyPage = { template: '<div />' };

export default createRouter({
    history: createWebHashHistory(),
    routes: [
        { path: '/', component: emptyPage },
        { path: '/login', component: LoginForm },
        { path: '/activate', component: ActivationForm },
        { path: '/reset', component: emptyPage },
        { path: '/residents', component: emptyPage },
        { path: '/roles', component: emptyPage },
        { path: '/discussions', component: DiscussionsPage },
    ],
});
