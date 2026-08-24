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

export interface RegisterResult {
    /** True when the project requires email confirmation before a session exists. */
    needsConfirmation: boolean;
}

/**
 * Create the account without OAuth. When Supabase returns an immediate
 * session (email confirmation disabled), bridge it right away so the user
 * lands authenticated.
 */
export async function registerWithEmail(email: string, password: string, fullName: string): Promise<RegisterResult> {
    if (!supabase) {
        throw new Error('Autenticação não configurada neste ambiente.');
    }

    const { data, error } = await supabase.auth.signUp({
        email,
        password,
        options: { data: { full_name: fullName } },
    });

    if (error) {
        throw error;
    }

    if (!data.session) {
        return { needsConfirmation: true };
    }

    await axios.post('/api/auth/session', { token: data.session.access_token });

    return { needsConfirmation: false };
}
