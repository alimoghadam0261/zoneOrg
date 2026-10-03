import axios from 'axios';
import '@fortawesome/fontawesome-free/css/all.css';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.ZONE = window.ZONE || {};
