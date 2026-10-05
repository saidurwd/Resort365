/**
 * Live updates on the POS and kitchen display (ARCHITECTURE AD-17, Step 3.5). Echo connects to Reverb
 * only on screens that use it (loaded on demand). A screen subscribes to its private channel and, on
 * an event, reloads its state from the server, which stays the only source of truth. While the
 * WebSocket is not connected (Reverb down, no network, channel refused), the screen polls instead.
 */
let echoPromise = null;

function echo() {
    const env = import.meta.env;

    if (!env.VITE_REVERB_APP_KEY) {
        return Promise.resolve(null);
    }

    echoPromise ??= Promise.all([import('laravel-echo'), import('pusher-js')])
        .then(([{ default: Echo }, { default: Pusher }]) => {
            window.Pusher = Pusher;
            const tls = (env.VITE_REVERB_SCHEME ?? 'https') === 'https';

            return new Echo({
                broadcaster: 'reverb',
                key: env.VITE_REVERB_APP_KEY,
                wsHost: env.VITE_REVERB_HOST,
                wsPort: env.VITE_REVERB_PORT ?? 80,
                wssPort: env.VITE_REVERB_PORT ?? 443,
                forceTLS: tls,
                enabledTransports: ['ws', 'wss'],
                // The tenant's own subdomain authorizes its channels.
                authEndpoint: '/broadcasting/auth',
                auth: { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' } },
            });
        })
        .catch(() => null);

    return echoPromise;
}

/**
 * Subscribes to a private channel.
 *
 * @param {object} options
 * @param {string} options.channel  e.g. "tenant.1.kitchen.3"
 * @param {Object<string, function(object): void>} options.on  event name (broadcastAs) => handler
 * @param {function(): Promise<void>|void} options.poll  reloads the screen's state; called every
 *        `interval` ms while live updates are not working, and once when they (re)connect
 * @param {function(boolean): void} [options.onState]  told whether live updates are working
 * @param {number} [options.interval]
 */
export function live({ channel, on, poll, onState = () => {}, interval = 5000 }) {
    const state = { live: false, connected: false, subscribed: false };
    const update = () => {
        const was = state.live;
        state.live = state.connected && state.subscribed;

        if (state.live !== was) {
            onState(state.live);
        }

        if (state.live && !was) {
            poll(); // catch up on anything missed while offline
        }
    };

    setInterval(() => {
        if (!state.live && document.visibilityState === 'visible') {
            poll();
        }
    }, interval);

    echo().then((instance) => {
        if (!instance) {
            return;
        }

        const connection = instance.connector.pusher.connection;
        connection.bind('state_change', ({ current }) => {
            state.connected = current === 'connected';
            update();
        });
        state.connected = connection.state === 'connected';

        const subscription = instance.private(channel);
        subscription.subscribed(() => {
            state.subscribed = true;
            update();
        });
        subscription.error(() => {
            state.subscribed = false;
            update();
        });
        Object.entries(on).forEach(([event, handler]) => subscription.listen(`.${event}`, handler));
    });
}

/** GET a POS/KDS JSON endpoint; resolves to the data or null. */
export async function getJson(url) {
    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });

        return response.ok ? await response.json() : null;
    } catch {
        return null;
    }
}
