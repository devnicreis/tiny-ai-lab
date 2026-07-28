import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// O protocolo do Reverb é compatível com Pusher; o cliente precisa da
// referência global antes da inicialização do Echo.
window.Pusher = Pusher;

// Apenas variáveis prefixadas com VITE_ são expostas ao bundle. Elas espelham
// as REVERB_* do .env (ver .env.example).
const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

/**
 * Autorização de canais privados.
 *
 * A rota /broadcasting/auth roda sob o middleware `auth.firebase`, não sob
 * sessão PHP. Por isso o authorizer padrão do Echo não serve: cada requisição
 * precisa carregar o Firebase ID Token corrente no header Authorization.
 *
 * O token vive em window.__FIREBASE_TOKEN__ e é mantido atualizado por
 * resources/js/firebase.js (onIdTokenChanged), já que expira em 1 hora.
 */
const firebaseAuthorizer = (channel) => ({
    authorize: (socketId, callback) => {
        fetch('/broadcasting/auth', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                Authorization: `Bearer ${window.__FIREBASE_TOKEN__ ?? ''}`,
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                socket_id: socketId,
                channel_name: channel.name,
            }),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Broadcasting auth falhou (HTTP ${response.status}).`);
                }

                return response.json();
            })
            .then((data) => callback(null, data))
            .catch((error) => callback(error, null));
    },
});

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: firebaseAuthorizer,
});

export default window.Echo;
