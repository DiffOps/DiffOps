import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import { supabase } from './lib/supabase';

window.axios.interceptors.request.use(async (config) => {
    if (!supabase || config.url?.startsWith('/api/auth/session')) {
        return config;
    }

    const { data } = await supabase.auth.getSession();

    if (data?.session?.access_token) {
        config.headers.Authorization = `Bearer ${data.session.access_token}`;
    }

    return config;
});
