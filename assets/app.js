import './styles/app.css';
import {createApp} from "vue";
import App from "./App.vue";
import {createRouter, createWebHistory} from "vue-router";
import {user} from "./user";
import HomeScola from "./pages/scola/HomeScola.vue";
import ImportDoc from "./pages/scola/ImportDoc.vue";
import MonitoringDoc from "./pages/scola/Monitoring.vue";
import SearchStudent from "./pages/scola/SearchStudent.vue";
import StudentView from "./pages/StudentView.vue";
import axios from "axios";
import Unauthorized from "./pages/Unauthorized.vue";
import User from "./pages/admin/User.vue";

const b64 = document.querySelector('#app').dataset.info;
const jsonUser = JSON.parse(atob(b64));

user.setName(jsonUser.username);
user.setRoles(jsonUser.roles);
user.setEncryptedName(jsonUser.encryptedUsername);
user.setNumero(jsonUser.numero);

const routes = [
    {path: '/scola', component: HomeScola, meta: {requiresScola: true}},

    {path: '/users', component: User, meta: {requiresAdmin: true}},

    {path: '/scola/import/rn', component: ImportDoc, meta: {requiresScola: true}, props: {mode: 0}},
    {path: '/scola/import/attest', component: ImportDoc, meta: {requiresScola: true}, props: {mode: 1}},

    {path: '/scola/monitoring/rn', component: MonitoringDoc, meta: {requiresScola: true}, props: {mode: 0}},
    {path: '/scola/monitoring/attest', component: MonitoringDoc, meta: {requiresScola: true}, props: {mode: 1}},

    {path: '/scola/search', component: SearchStudent, meta: {requiresScola: true}},

    {path: '/student/:num*', component: StudentView, meta: {requiresScola: false}},

    {path: '/unauthorized', component: Unauthorized, meta: {requiresScola: false}},

    {path: '/:pathMatch(.*)*', redirect: '/scola'}
]

// const BASE_URL = '/doc-scola';   // PROD
// const BASE_URL = '';        // DEV

// WebService.BASE_URL = BASE_URL;

const router = createRouter({
    history: createWebHistory(),
    routes,
});

axios.interceptors.response.use(function (response) {
    // Optional: Do something with response data
    return response;
}, function (error) {
    //Do whatever you want with the response error here:

    console.log('AXIOS ERR');

    // if (error.response.status === 403) {
    //     window.location.reload();
    // }

    //But, be SURE to return the rejected promise, so the caller still has
    //the option of additional specialized handling at the call-site:
    return Promise.reject(error);
});

router.beforeEach((to, from, next) => {
    if (user.isEtudiant() && to.path !== '/student') { // Si étudiant on force sur la page étudiant
        next({path: '/student'});
    } else if (user.isAnonym() && to.path !== '/unauthorized') { // Si anonyme on force sur la page unauthorized
        next({path: '/unauthorized'});
    } else if (!user.isAnonym() && to.path === '/unauthorized') { // Si autorisé on cancel la page unauthorized
        next({path: '/'});
    } else if (to.meta.requiresScola) {
        if (user.isScola()) next()
        else next({path: '/'})
    } else if (to.meta.requiresAdmin) {
        if (user.isAdmin()) next()
        else next({path: '/'})
    } else {
        next()
    }
});

const app = createApp(App, {
    url_login: jsonUser.url_login,
    url_logout: jsonUser.url_logout
})

app.config.compilerOptions.isCustomElement = (tag) => {
    return tag.startsWith('uca-')
}

app.use(router).mount('#app')