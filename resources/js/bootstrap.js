import axios from 'axios';
window.axios = axios;

const crmPrefix = document.querySelector('meta[name="crm-prefix"]')?.getAttribute('content') || '/crm';
window.axios.defaults.baseURL = crmPrefix;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';

const csrf = document.head.querySelector('meta[name="csrf-token"]');

export function applyCsrfToken(token) {
    if (!token) {
        return;
    }
    if (csrf) {
        csrf.setAttribute('content', token);
    }
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    document.querySelectorAll('input[name="_token"]').forEach((input) => {
        input.value = token;
    });
}

if (csrf) {
    applyCsrfToken(csrf.getAttribute('content'));
}

window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419) {
            const message = 'Сессия истекла. Обновите страницу и повторите действие.';
            if (typeof window.Toast !== 'undefined') {
                window.Toast.error(message);
            } else {
                window.alert(message);
            }
            window.setTimeout(() => window.location.reload(), 800);
            return new Promise(() => {});
        }

        return Promise.reject(error);
    }
);
