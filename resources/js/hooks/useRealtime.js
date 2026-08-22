import { useEffect, useState, useCallback } from 'react';
import { createClient } from '@supabase/supabase-js';

const supabaseUrl = import.meta.env.VITE_SUPABASE_URL;
const supabaseAnonKey = import.meta.env.VITE_SUPABASE_ANON_KEY;

let supabase = null;
if (supabaseUrl && supabaseAnonKey) {
    supabase = createClient(supabaseUrl, supabaseAnonKey);
}

export function useRealtime(channelName, { onInsert, onUpdate, onDelete } = {}) {
    const [status, setStatus] = useState('disconnected');
    const [data, setData] = useState([]);
    const channelRef = useState(null);

    const subscribe = useCallback(() => {
        if (!supabase) {
            setStatus('error');
            return;
        }

        setStatus('connecting');

        const channel = supabase
            .channel(channelName)
            .on(
                'postgres_changes',
                { event: '*', schema: 'public', table: channelName.split(':').pop() },
                (payload) => {
                    if (payload.eventType === 'INSERT' && onInsert) {
                        onInsert(payload);
                    } else if (payload.eventType === 'UPDATE' && onUpdate) {
                        onUpdate(payload);
                    } else if (payload.eventType === 'DELETE' && onDelete) {
                        onDelete(payload);
                    }
                    setData((prev) => [payload.new, ...prev.slice(0, 99)]);
                }
            )
            .subscribe((status) => {
                setStatus(status);
            });

        channelRef.current = channel;
    }, [channelName, onInsert, onUpdate, onDelete]);

    const unsubscribe = useCallback(() => {
        if (channelRef.current) {
            supabase.removeChannel(channelRef.current);
            channelRef.current = null;
            setStatus('disconnected');
        }
    }, []);

    useEffect(() => {
        subscribe();
        return () => unsubscribe();
    }, [subscribe, unsubscribe]);

    return { data, status, subscribe, unsubscribe };
}