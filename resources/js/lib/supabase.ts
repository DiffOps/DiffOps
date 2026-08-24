import axios from 'axios';
import { createClient } from '@supabase/supabase-js';

const url = import.meta.env.VITE_SUPABASE_URL;
const anonKey = import.meta.env.VITE_SUPABASE_ANON_KEY;

export const supabase = url && anonKey ? createClient(url, anonKey) : null;

/**
 * Read the current Supabase session and hand the access token to the backend,
 * which sets the HttpOnly session cookie used by Inertia page loads.
 */
export async function exchangeAndBridgeSession(): Promise<boolean> {
    if (!supabase) {
        return false;
    }

    const { data } = await supabase.auth.getSession();
    const token = data?.session?.access_token;

    if (!token) {
        return false;
    }

    await axios.post('/api/auth/session', { token });

    return true;
}
