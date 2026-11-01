import { useEffect } from 'react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { useAppSelector } from '@/store';

// Real-time notifications via Laravel Reverb.
// Configured for Railway internal networking:
//   - REVERB_HOST  = Railway internal hostname of the Reverb service
//   - REVERB_PORT  = 8080
//   - REVERB_APP_KEY from VITE_REVERB_APP_KEY
// The Pusher/Echo client only initializes when VITE_REVERB_HOST and
// VITE_REVERB_PORT are defined so the app degrades gracefully otherwise.

declare global {
  interface Window {
    Pusher?: typeof Pusher;
    Echo?: Echo<'reverb'>;
  }
}

let echoInstance: Echo<'reverb'> | null = null;

function initializeEcho(): Echo<'reverb'> | null {
  if (typeof window === 'undefined') return null;

  const host = (import.meta.env.VITE_REVERB_HOST as string | undefined)?.trim();
  const port = (import.meta.env.VITE_REVERB_PORT as string | undefined)?.trim();
  const key = (import.meta.env.VITE_REVERB_APP_KEY as string | undefined)?.trim();

  if (!host || !port || !key) {
    // Reverb not configured — silently skip real-time
    return null;
  }

  window.Pusher = Pusher;

  if (!echoInstance) {
    const instance: Echo<'reverb'> = new Echo<'reverb'>({
      broadcaster: 'reverb',
      key,
      wsHost: host,
      wsPort: Number(port),
      wssPort: Number(port),
      forceTLS: false,
      enabledTransports: ['ws', 'wss'],
    });
    echoInstance = instance;
    window.Echo = instance;
  }

  return echoInstance;
}

export function useEcho() {
  const user = useAppSelector((s) => s.auth.user);

  useEffect(() => {
    const echo = initializeEcho();
    if (!echo || !user?.id) return;

    const channel = echo.private(`user.${user.id}`) as unknown as {
      listen: (event: string, cb: (e: unknown) => void) => typeof channel;
      leave: (name?: string) => void;
    };
    // Subscribe to common broadcast events. The backend will fire these
    // on deposits, withdrawals, and order status changes.
    channel
      .listen('.order.updated', (e: unknown) => {
        // eslint-disable-next-line no-console
        console.log('Order updated', e);
      })
      .listen('.deposit.updated', (e: unknown) => {
        // eslint-disable-next-line no-console
        console.log('Deposit updated', e);
      })
      .listen('.withdrawal.updated', (e: unknown) => {
        // eslint-disable-next-line no-console
        console.log('Withdrawal updated', e);
      });

    return () => {
      echo.leave(`user.${user.id}`);
    };
  }, [user?.id]);
}