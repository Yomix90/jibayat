// Empreinte / clé d'accès (WebAuthn) via @laragear/webpass, servi en local (pas de CDN).
import Webpass from '@laragear/webpass';

const webpass = Webpass.create({ findCsrfToken: true });

window.jibayatPasskey = {
    supported: () => !Webpass.isUnsupported(),

    async register(alias) {
        const { success, error } = await webpass.attest('/webauthn/register/options', { path: '/webauthn/register', body: { alias } });
        if (!success) throw error ?? new Error('Enregistrement refusé');
    },

    async login(username) {
        const { success, data, error } = await webpass.assert({ path: '/webauthn/login/options', body: username ? { username } : {} }, '/webauthn/login');
        if (!success) throw error ?? new Error('Empreinte non reconnue');
        window.location.assign(data?.redirect ?? '/');
    },
};
