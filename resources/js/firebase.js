import { initializeApp } from 'firebase/app';
import { getAuth, onIdTokenChanged } from 'firebase/auth';

/**
 * Cliente Firebase do navegador.
 *
 * O Firebase é a autoridade de identidade; o Laravel apenas valida o ID Token.
 * Este módulo mantém window.__FIREBASE_TOKEN__ sempre com o token corrente —
 * ele expira em 1 hora e o SDK o renova sozinho, disparando onIdTokenChanged.
 * O authorizer do Echo (resources/js/echo.js) lê essa variável a cada
 * autorização de canal privado.
 */
const config = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
    storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    appId: import.meta.env.VITE_FIREBASE_APP_ID,
};

// Sem credenciais configuradas o SDK lançaria erro na inicialização e
// derrubaria o bundle inteiro. Ambientes ainda sem Firebase seguem sem auth.
const isConfigured = Boolean(config.apiKey && config.projectId && config.appId);

export const firebaseApp = isConfigured ? initializeApp(config) : null;
export const firebaseAuth = firebaseApp ? getAuth(firebaseApp) : null;

if (firebaseAuth) {
    onIdTokenChanged(firebaseAuth, async (user) => {
        window.__FIREBASE_TOKEN__ = user ? await user.getIdToken() : '';
    });
}

/**
 * Token corrente, forçando refresh quando o SDK ainda não o entregou.
 */
export async function currentIdToken() {
    if (!firebaseAuth?.currentUser) {
        return window.__FIREBASE_TOKEN__ ?? '';
    }

    window.__FIREBASE_TOKEN__ = await firebaseAuth.currentUser.getIdToken();

    return window.__FIREBASE_TOKEN__;
}
